<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{ApiTokenService,BayerDataService};
use App\Validators\LoginValidator;
use DateTimeImmutable;
use Throwable;

final class ApiController
{
    private const VERSION = 'v1';

    private string $requestId;
    private ?array $apiUser=null;
    private ?string $activeToken=null;

    public function __construct()
    {
        $incoming=trim((string)($_SERVER['HTTP_X_REQUEST_ID']??''));
        $this->requestId=preg_match('/^[A-Za-z0-9._:-]{1,64}$/',$incoming)
            ? $incoming
            : bin2hex(random_bytes(12));
    }

    /**
     * Login de API: valida una cuenta real del sistema y devuelve un Bearer token temporal.
     * No crea sesión web ni usa cookies.
     */
    public function login(): void
    {
        if(!\config('app.api_enabled')){
            $this->error('API_DISABLED','API deshabilitada.',503);
        }

        try{
            [$email,$password]=$this->credentials();
            if(!(new LoginValidator())->valid($email,$password)){
                $this->error('INVALID_CREDENTIALS','Ingrese un correo y una contraseña válidos.',422);
            }

            $auth=(new ApiTokenService())->issue(trim($email),$password);
            $this->respond([
                'meta'=>[
                    'apiVersion'=>self::VERSION,
                    'requestId'=>$this->requestId,
                    'generatedAt'=>date(DATE_ATOM),
                ],
                'auth'=>$auth,
            ]);
        }catch(HttpException $error){
            $code=match($error->status){
                401=>'INVALID_CREDENTIALS',
                403=>'FORBIDDEN',
                429=>'TOO_MANY_ATTEMPTS',
                503=>'API_MISCONFIGURED',
                default=>'AUTH_ERROR',
            };
            $this->error($code,$error->getMessage(),$error->status);
        }catch(Throwable $error){
            \log_event('api_login_error',['request_id'=>$this->requestId,'type'=>get_class($error)]);
            $this->error('INTERNAL_ERROR','No se pudo completar el inicio de sesión de la API.',500);
        }
    }

    public function logout(): void
    {
        $this->guard();
        try{
            if($this->activeToken!==null){
                (new ApiTokenService())->revoke($this->activeToken);
            }
            $this->recordAccess('api_logout');
            $this->respond([
                'meta'=>[
                    'apiVersion'=>self::VERSION,
                    'requestId'=>$this->requestId,
                    'generatedAt'=>date(DATE_ATOM),
                ],
                'data'=>['revoked'=>true],
            ]);
        }catch(HttpException $error){
            $this->error('AUTH_ERROR',$error->getMessage(),$error->status);
        }
    }

    public function info(): void
    {
        $this->guard();
        $this->recordAccess('api_info');
        $this->respond([
            'meta'=>$this->meta('info',0,[]),
            'data'=>[
                'name'=>'Bayer Pucchún REST API',
                'publishedOnly'=>true,
                'endpoints'=>[
                    '/api/v1/bayer/all',
                    '/api/v1/bayer/sales',
                    '/api/v1/bayer/shipments',
                    '/api/v1/bayer/inventory',
                ],
                'optionalFilters'=>['from','to','branch','q'],
                'quantityModel'=>[
                    'quantity'=>'Número entero de productos o presentaciones.',
                    'quantityUnit'=>'NIU',
                    'measureUnit'=>'Unidad del catálogo/presentación del producto.',
                ],
            ],
        ]);
    }

    public function sales(): void
    {
        $this->serveDataset('sales');
    }

    public function shipments(): void
    {
        $this->serveDataset('shipments');
    }

    public function inventory(): void
    {
        $this->serveDataset('inventory');
    }

    /** One request for every published integration dataset. */
    public function all(): void
    {
        $this->guard();

        try {
            $filters=$this->filters();
            $service=new BayerDataService();

            $sales=$this->prepareRows('sales',$service->completeDataset('sales',$filters));
            $shipments=$this->prepareRows('shipments',$service->completeDataset('shipments',$filters));
            $inventory=$this->prepareRows('inventory',$service->completeDataset('inventory',$filters));
            $total=count($sales)+count($shipments)+count($inventory);

            $this->recordAccess('api_all');
            $this->respond([
                'meta'=>$this->meta('all',$total,$filters)+[
                    'datasets'=>[
                        'sales'=>count($sales),
                        'shipments'=>count($shipments),
                        'inventory'=>count($inventory),
                    ],
                ],
                'data'=>[
                    'sales'=>$sales,
                    'shipments'=>$shipments,
                    'inventory'=>$inventory,
                ],
            ]);
        } catch (\InvalidArgumentException $error) {
            $this->recordAccess('api_bad_request');
            $this->error('INVALID_FILTER',$error->getMessage(),422);
        } catch (Throwable $error) {
            \log_event('api_bayer_all_error',['request_id'=>$this->requestId,'type'=>get_class($error)]);
            $this->recordAccess('api_error');
            $this->error('INTERNAL_ERROR','No se pudo completar la consulta.',500);
        }
    }

    private function serveDataset(string $dataset): void
    {
        $this->guard();

        try {
            $filters=$this->filters();
            $rows=$this->prepareRows($dataset,(new BayerDataService())->completeDataset($dataset,$filters));

            $action=match($dataset){
                'sales'=>'api_sales',
                'shipments'=>'api_shipments',
                'inventory'=>'api_inventory',
                default=>'api_dataset',
            };
            $this->recordAccess($action);

            $this->respond([
                'meta'=>$this->meta($dataset,count($rows),$filters),
                'data'=>$rows,
            ]);
        } catch (\InvalidArgumentException $error) {
            $this->recordAccess('api_bad_request');
            $this->error('INVALID_FILTER',$error->getMessage(),422);
        } catch (Throwable $error) {
            \log_event('api_bayer_dataset_error',[
                'request_id'=>$this->requestId,
                'dataset'=>$dataset,
                'type'=>get_class($error),
            ]);
            $this->recordAccess('api_error');
            $this->error('INTERNAL_ERROR','No se pudo completar la consulta.',500);
        }
    }

    private function credentials(): array
    {
        $contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
        if(str_contains($contentType,'application/json')){
            $raw=file_get_contents('php://input');
            $payload=is_string($raw) && trim($raw)!=='' ? json_decode($raw,true) : null;
            if(!is_array($payload)){
                throw new HttpException(422,'JSON de autenticación inválido.');
            }
        }else{
            $payload=$_POST;
        }

        $email=$payload['email']??null;
        $password=$payload['password']??null;
        if(!is_string($email) || !is_string($password)){
            throw new HttpException(422,'Email y password son obligatorios.');
        }
        return [$email,$password];
    }

    private function filters(): array
    {
        $filters=[];

        foreach(['from','to'] as $key){
            $raw=$_GET[$key]??'';
            if(!is_string($raw)) throw new \InvalidArgumentException('Fecha inválida.');
            $value=trim($raw);
            if($value==='') continue;

            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
            if(!$date || $date->format('Y-m-d')!==$value){
                throw new \InvalidArgumentException('Las fechas deben usar el formato YYYY-MM-DD.');
            }
            $filters[$key]=$value;
        }

        if(isset($filters['from'],$filters['to']) && $filters['from']>$filters['to']){
            throw new \InvalidArgumentException('El rango de fechas es inválido.');
        }

        foreach(['branch','q'] as $key){
            $raw=$_GET[$key]??'';
            if(!is_string($raw)) throw new \InvalidArgumentException('Filtro inválido.');
            $value=trim($raw);
            if($value==='') continue;
            if(mb_strlen($value)>100) throw new \InvalidArgumentException('Filtro demasiado largo.');
            $filters[$key]=$value;
        }

        return $filters;
    }

    private function guard(): void
    {
        if(!\config('app.api_enabled')){
            $this->recordAccess('api_disabled');
            $this->error('API_DISABLED','API deshabilitada.',503);
        }

        $authorization=(string)(
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        );

        if(!preg_match('/^Bearer\s+(.+)$/i',trim($authorization),$match)){
            $this->recordAccess('api_unauthorized');
            header('WWW-Authenticate: Bearer realm="Bayer Pucchun API"');
            $this->error('UNAUTHORIZED','Primero inicie sesión en /api/v1/auth/login y envíe el token Bearer obtenido.',401);
        }

        $token=trim($match[1]);
        try{
            $user=(new ApiTokenService())->authenticate($token);
        }catch(HttpException $error){
            $this->recordAccess($error->status===403?'api_forbidden':'api_unauthorized');
            if($error->status===401) header('WWW-Authenticate: Bearer realm="Bayer Pucchun API"');
            $code=$error->status===403?'FORBIDDEN':($error->status===503?'API_MISCONFIGURED':'UNAUTHORIZED');
            $this->error($code,$error->getMessage(),$error->status);
        }

        $this->apiUser=$user;
        $this->activeToken=$token;
    }

    private function meta(string $dataset,int $count,array $filters): array
    {
        return [
            'apiVersion'=>self::VERSION,
            'requestId'=>$this->requestId,
            'dataset'=>$dataset,
            'generatedAt'=>date(DATE_ATOM),
            'dataStatus'=>'PUBLICADO',
            'recordCount'=>$count,
            'filters'=>$filters,
            'quantityModel'=>[
                'quantity'=>'Número entero de productos o presentaciones.',
                'quantityUnit'=>'NIU',
                'measureUnit'=>'Unidad del catálogo/presentación del producto; no multiplica automáticamente quantity.',
            ],
        ];
    }

    private function prepareRows(string $dataset,array $rows): array
    {
        $allowZero=$dataset==='inventory';
        foreach($rows as &$row){
            $quantity=$row['quantity']??null;
            $integer=\quantity_integer_value($quantity,$allowZero);
            if($integer!==null){
                $row['quantity']=$integer;
                $row['quantityUnit']='NIU';
                $row['quantityMeaning']='PRODUCT_COUNT';
                continue;
            }

            // Compatibilidad segura con datos históricos: nunca truncar ni redondear una cantidad fraccionaria.
            $row['quantityUnit']='NIU';
            $row['quantityMeaning']='LEGACY_FRACTIONAL_REVIEW_REQUIRED';
        }
        unset($row);
        return $rows;
    }

    private function error(string $code,string $message,int $status): never
    {
        $this->respond([
            'meta'=>[
                'apiVersion'=>self::VERSION,
                'requestId'=>$this->requestId,
                'generatedAt'=>date(DATE_ATOM),
            ],
            'error'=>[
                'code'=>$code,
                'message'=>$message,
            ],
        ],$status);
    }

    private function respond(array $payload,int $status=200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('X-API-Version: '.self::VERSION);
        header('X-Request-ID: '.$this->requestId);
        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT
        );
        exit;
    }

    /** Traza el consumo sin guardar el Bearer token ni las credenciales. */
    private function recordAccess(string $action): void
    {
        try {
            $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
            $agent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255);
            $userId=is_array($this->apiUser) ? (int)($this->apiUser['id']??0) : 0;
            $st=\db()->prepare('INSERT INTO bitacora_acceso(usuario_id,ip,user_agent,accion,fecha_hora) VALUES(?,?,?,?,NOW())');
            $st->execute([$userId>0?$userId:null,$ip!==''?$ip:null,$agent!==''?$agent:null,substr($action,0,30)]);
        } catch (Throwable $error) {
            \log_event('api_access_log_error',['request_id'=>$this->requestId]);
        }
    }
}

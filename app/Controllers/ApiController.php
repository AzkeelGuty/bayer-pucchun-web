<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\BayerDataService;
use DateTimeImmutable;
use Throwable;

final class ApiController
{
    private const VERSION = 'v1';

    private string $requestId;

    public function __construct()
    {
        $incoming=trim((string)($_SERVER['HTTP_X_REQUEST_ID']??''));
        $this->requestId=preg_match('/^[A-Za-z0-9._:-]{1,64}$/',$incoming)
            ? $incoming
            : bin2hex(random_bytes(12));
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

            $sales=$service->completeDataset('sales',$filters);
            $shipments=$service->completeDataset('shipments',$filters);
            $inventory=$service->completeDataset('inventory',$filters);
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
            $rows=(new BayerDataService())->completeDataset($dataset,$filters);

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

        $configured=trim((string)\config('app.api_token'));
        if($configured===''){
            \log_event('api_token_missing',['request_id'=>$this->requestId]);
            $this->recordAccess('api_misconfigured');
            $this->error('API_MISCONFIGURED','API no configurada correctamente.',503);
        }

        $authorization=(string)(
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        );

        if(!preg_match('/^Bearer\s+(.+)$/i',trim($authorization),$match)){
            $this->recordAccess('api_unauthorized');
            header('WWW-Authenticate: Bearer realm="Bayer Pucchun API"');
            $this->error('UNAUTHORIZED','Token Bearer requerido.',401);
        }

        $token=trim($match[1]);
        if($token==='' || !hash_equals($configured,$token)){
            $this->recordAccess('api_unauthorized');
            header('WWW-Authenticate: Bearer realm="Bayer Pucchun API"');
            $this->error('UNAUTHORIZED','Credencial inválida.',401);
        }
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
        ];
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

    /** API calls have no web session user, so access is traced without storing credentials. */
    private function recordAccess(string $action): void
    {
        try {
            $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
            $agent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255);
            $st=\db()->prepare('INSERT INTO bitacora_acceso(usuario_id,ip,user_agent,accion,fecha_hora) VALUES(NULL,?,?,?,NOW())');
            $st->execute([$ip!==''?$ip:null,$agent!==''?$agent:null,substr($action,0,30)]);
        } catch (Throwable $error) {
            \log_event('api_access_log_error',['request_id'=>$this->requestId]);
        }
    }
}

<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;

/** Resolución estricta, comprobación de nombres completos y alta controlada. */
final class WarehouseExcelResolver
{
    private array $resolved=[];

    public function __construct(private ?PDO $pdo=null){$this->pdo??=\db();}

    private function canon(string $s): string
    {
        $s=mb_strtolower(trim($s),'UTF-8');
        $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??'')??'');
    }

    public function inspect(array $data): array
    {
        $name=trim((string)($data['almacen_nombre']??''));
        $code=trim((string)($data['almacen_codigo']??''));
        $branch=trim((string)($data['sucursal_codigo']??''));
        if($name==='' && $code==='')return ['action'=>'none','id'=>null,'name'=>''];
        if(mb_strlen($name)>120||mb_strlen($code)>20||mb_strlen($branch)>20)
            throw new HttpException(422,'Almacén (máximo 120 caracteres) o sus códigos (máximo 20) demasiado largos.');
        if($code!==''&&!preg_match('/^[\p{L}\p{N}._-]+$/uD',$code))
            throw new HttpException(422,'Código de almacén no válido: '.$code);
        $branches=$this->pdo->query('SELECT id,codigo,nombre FROM sucursales WHERE estado=1')
            ->fetchAll(PDO::FETCH_ASSOC);
        $branchId=null;
        if($branch!==''){
            foreach($branches as $r){
                if($this->canon((string)$r['codigo'])===$this->canon($branch)){
                    if($branchId!==null)throw new HttpException(422,'Existen sucursales con códigos ambiguos.');
                    $branchId=(int)$r['id'];
                }
            }
            if($branchId===null)
                throw new HttpException(422,"No existe sucursal_codigo '$branch'. Registre primero esa sucursal.");
        }

        $warehouses=$this->pdo->query('SELECT id,codigo,nombre,sucursal_id,estado FROM almacenes')
            ->fetchAll(PDO::FETCH_ASSOC);
        $matches=[];
        foreach($warehouses as $existing){
            if($branchId!==null&&(int)$existing['sucursal_id']!==$branchId)continue;
            $codeEqual=$code!==''&&$this->canon((string)$existing['codigo'])===$this->canon($code);
            $nameEqual=$name!==''&&$this->canon((string)$existing['nombre'])===$this->canon($name);
            if($codeEqual||$nameEqual){
                if($code!=='' && !$codeEqual)
                    throw new HttpException(422,"Almacén '$name' ya existe con otro código. Verifique el Excel.");
                if($name!=='' && !$nameEqual)
                    throw new HttpException(422,"Código de almacén '$code' corresponde a otro nombre: ".$existing['nombre']);
                $matches[]=$existing;
            }
        }
        if(count($matches)>1)
            throw new HttpException(422,"El almacén '$name' corresponde a varias sucursales. Indique sucursal_codigo.");
        if($matches){
            if((int)$matches[0]['estado']!==1)
                throw new HttpException(422,'El almacén existe pero está inactivo: '.$matches[0]['nombre']);
            return ['action'=>'existing','id'=>(int)$matches[0]['id'],'name'=>(string)$matches[0]['nombre']];
        }
        // Proteger frente a códigos presentes en otra sucursal (código es único global).
        foreach($warehouses as $existing){
            if($code!=='' && $this->canon((string)$existing['codigo'])===$this->canon($code))
                throw new HttpException(422,"El código '$code' pertenece a otro almacén/sucursal.");
        }
        if($name==='')throw new HttpException(422,
            "Código '$code' no registrado. Indique también almacen_nombre para crear el almacén.");
        if($branchId===null){
            if(count($branches)!==1)
                throw new HttpException(422,"Almacén nuevo '$name': indique sucursal_codigo (hay ".
                    count($branches)." sucursales activas).");
            $branchId=(int)$branches[0]['id'];
        }
        $normal=$this->canon($name);
        // Los errores de una letra no deben crear un segundo almacén por accidente.
        foreach($warehouses as $existing){
            if((int)$existing['sucursal_id']!==$branchId)continue;
            $known=$this->canon((string)$existing['nombre']);
            $distance=levenshtein($normal,$known);
            if($distance<=2 || (mb_strlen($normal)>=11 && $distance<=3)){
                throw new HttpException(422,
                    "Nombre de almacén muy parecido a '".$existing['nombre'].
                    "'. Revise las letras antes de crear '$name'.");
            }
        }
        if($code==='')$code='AX-'.strtoupper(substr(hash('sha256',$branchId.'|'.$normal),0,14));
        foreach($warehouses as $existing){
            if($this->canon((string)$existing['codigo'])===$this->canon($code))
                throw new HttpException(422,"Código generado '$code' ya utilizado. Especifique almacen_codigo.");
        }
        return ['action'=>'create','id'=>null,'name'=>$name,'code'=>$code,'branch_id'=>$branchId];
    }

    /** Invocar desde la transacción de importación; nunca crea en la previsualización. */
    public function resolve(array $data): ?int
    {
        $key=json_encode(WarehouseExcelHeader::values($data),JSON_UNESCAPED_UNICODE);
        if(array_key_exists($key,$this->resolved))return $this->resolved[$key];
        $result=$this->inspect($data);
        if($result['action']==='none')return $this->resolved[$key]=null;
        if($result['action']==='existing')return $this->resolved[$key]=$result['id'];
        if(!$this->pdo->inTransaction())
            throw new \LogicException('Crear almacenes requiere una transacción activa.');
        $insert=$this->pdo->prepare(
            'INSERT INTO almacenes(sucursal_id,codigo,nombre,tipo,estado)
             VALUES(?,?,?, ?,1)'
        );
        $insert->execute([$result['branch_id'],$result['code'],$result['name'],'GENERAL']);
        return $this->resolved[$key]=(int)$this->pdo->lastInsertId();
    }
}

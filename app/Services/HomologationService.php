<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/** CRUD e importación transaccional de las seis homologaciones Bayer. */
final class HomologationService
{
    public function __construct(private ?PDO $pdo=null) { $this->pdo ??= \db(); }

    public static function definitions(): array
    {
        $base=[
            'productos'=>['title'=>'Productos Bayer','table'=>'homologacion_productos_bayer','local_table'=>'productos','local_key'=>'producto_id','local_code'=>'codigo','local_name'=>'nombre','lookup'=>'productos','external_key'=>'material_id','external_name'=>'material_name','code_max'=>40,'name_max'=>150,'required_code'=>true],
            'clientes'=>['title'=>'Clientes Bayer','table'=>'homologacion_clientes','local_table'=>'clientes','local_key'=>'cliente_id','local_code'=>'nro_doc','local_name'=>'razon_social','lookup'=>'clientes','external_key'=>'customer_id','external_name'=>'customer_name','code_max'=>40,'name_max'=>150],
            'unidades'=>['title'=>'Unidades Bayer','table'=>'homologacion_unidades','local_table'=>'unidades_medida','local_key'=>'unidad_id','local_code'=>'codigo','local_name'=>'nombre','lookup'=>'unidades','external_key'=>'external_unit_code','external_name'=>'external_unit_name','code_max'=>20,'name_max'=>60],
            'sucursales'=>['title'=>'Sucursales Bayer','table'=>'homologacion_sucursales','local_table'=>'sucursales','local_key'=>'sucursal_id','local_code'=>'codigo','local_name'=>'nombre','lookup'=>'sucursales','external_key'=>'branch_id','external_name'=>'branch_name','code_max'=>20,'name_max'=>120],
            'almacenes'=>['title'=>'Almacenes Bayer','table'=>'homologacion_almacenes','local_table'=>'almacenes','local_key'=>'almacen_id','local_code'=>'codigo','local_name'=>'nombre','lookup'=>'almacenes','external_key'=>'warehouse_id','external_name'=>'warehouse_name','code_max'=>20,'name_max'=>120],
            'vendedores'=>['title'=>'Vendedores Bayer','table'=>'homologacion_vendedores','local_table'=>'vendedores','local_key'=>'vendedor_id','local_code'=>'codigo','local_name'=>'nombres','lookup'=>'vendedores','external_key'=>'sales_id','external_name'=>'sales_name','code_max'=>20,'name_max'=>120],
        ];
        foreach($base as &$config){
            $columns=[
                'partner_codigo'=>['description'=>'Código del partner, registrado en Partners','example'=>'BAYER','required'=>true],
                'codigo_interno'=>['description'=>'Código del registro local existente','example'=>'MAT-001','required'=>true],
                'codigo_bayer'=>['description'=>'Identificador externo en Bayer','example'=>'BAY-001','required'=>!empty($config['required_code'])],
                'nombre_bayer'=>['description'=>'Denominación externa en Bayer','example'=>'Nombre homologado','required'=>false],
                'estado'=>['description'=>'1=Activo, 0=Inactivo','example'=>'1','required'=>false],
                'valid_from'=>['description'=>'Inicio vigencia YYYY-MM-DD','example'=>'2026-11-12','required'=>false],
                'valid_until'=>['description'=>'Fin vigencia YYYY-MM-DD','example'=>'','required'=>false],
            ];
            if($config['table']==='homologacion_productos_bayer'){
                $columns['unidad_codigo']=['description'=>'Código unidad Bayer opcional ya registrada','example'=>'NIU','required'=>false];
            }
            $config['import']=$columns;
        }
        unset($config);
        return $base;
    }

    public function definition(string $tab): array
    {
        $defs=self::definitions();
        if(!isset($defs[$tab])) throw new HttpException(404,'Homologación inexistente.');
        return $defs[$tab];
    }

    public function find(string $tab,int $id): array
    {
        $d=$this->definition($tab);
        if($id<1) throw new HttpException(404,'Registro de homologación inexistente.');
        $st=$this->pdo->prepare("SELECT * FROM {$d['table']} WHERE id=?");
        $st->execute([$id]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new HttpException(404,'Registro de homologación inexistente.');
        return [
            'partner_id'=>$row['partner_id'],
            'local_id'=>$row[$d['local_key']],
            'codigo_bayer'=>$row[$d['external_key']],
            'nombre_bayer'=>$row[$d['external_name']],
            'unidad_bayer_id'=>$row['unidad_bayer_id']??'',
            'estado'=>$row['estado'],
            'valid_from'=>$row['valid_from'],
            'valid_until'=>$row['valid_until'],
        ];
    }

    public function options(string $tab,array $record=[]): array
    {
        $d=$this->definition($tab);
        $partners=$this->pdo->query("SELECT id,CONCAT(codigo,' · ',nombre) label FROM partners ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        $localId=(int)($record['local_id']??0);
        $selected=[];
        if($localId>0){
            $st=$this->pdo->prepare("SELECT id,CONCAT({$d['local_code']},' · ',{$d['local_name']}) label FROM {$d['local_table']} WHERE id=?");
            $st->execute([$localId]);
            $selected=$st->fetchAll(PDO::FETCH_ASSOC);
        }
        $units=$this->pdo->query("SELECT id,CONCAT(codigo,' · ',nombre) label FROM unidades_medida ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        return ['partners'=>$partners,'local'=>$selected,'units'=>$units];
    }

    private function validDate(mixed $raw,string $label): ?string
    {
        if($raw===null || $raw==='') return null;
        if(!is_string($raw)) throw new HttpException(422,'Fecha '.$label.' inválida.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$raw);
        if(!$date || $date->format('Y-m-d')!==$raw) throw new HttpException(422,'Fecha '.$label.' inválida.');
        return $raw;
    }

    private function normalized(string $tab,array $input): array
    {
        $d=$this->definition($tab);
        $partner=filter_var($input['partner_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $local=filter_var($input['local_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($partner===false || $local===false) throw new HttpException(422,'Seleccione un partner y un registro local.');
        foreach([['partners',(int)$partner],[$d['local_table'],(int)$local]] as [$table,$id]){
            $st=$this->pdo->prepare("SELECT id FROM $table WHERE id=?");
            $st->execute([$id]);
            if($st->fetchColumn()===false) throw new HttpException(422,'El partner o código interno no existe.');
        }
        $code=trim((string)($input['codigo_bayer']??''));
        $name=trim((string)($input['nombre_bayer']??''));
        if(($d['required_code']??false) && $code==='') throw new HttpException(422,'El código Bayer es obligatorio.');
        if(mb_strlen($code)>$d['code_max'] || mb_strlen($name)>$d['name_max']){
            throw new HttpException(422,'El código o nombre Bayer supera la longitud permitida.');
        }
        $state=(string)($input['estado']??'1');
        if(!in_array($state,['0','1'],true)) throw new HttpException(422,'El estado debe ser 1 (activo) o 0 (inactivo).');
        $from=$this->validDate($input['valid_from']??null,'de inicio');
        $until=$this->validDate($input['valid_until']??null,'de fin');
        if($from!==null && $until!==null && $until<$from) throw new HttpException(422,'El fin de vigencia no puede preceder al inicio.');

        $data=[
            'partner_id'=>(int)$partner,
            $d['local_key']=>(int)$local,
            $d['external_key']=>$code!==''?$code:null,
            $d['external_name']=>$name!==''?$name:null,
            'estado'=>(int)$state,
            'valid_from'=>$from,
            'valid_until'=>$until,
        ];
        if($tab==='productos'){
            $unitRaw=$input['unidad_bayer_id']??'';
            $unit=$unitRaw===''?null:filter_var($unitRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($unit===false) throw new HttpException(422,'Unidad Bayer inválida.');
            if($unit!==null){
                $st=$this->pdo->prepare('SELECT id FROM unidades_medida WHERE id=?');
                $st->execute([$unit]);
                if($st->fetchColumn()===false) throw new HttpException(422,'La unidad Bayer no existe.');
            }
            $data['unidad_bayer_id']=$unit;
        }
        return $data;
    }

    /** @param array<string,mixed> $input */
    public function save(string $tab,array $input,int $actorId,?int $id=null): int
    {
        if($actorId<1) throw new HttpException(403,'Usuario no válido.');
        $d=$this->definition($tab);
        $data=$this->normalized($tab,$input);
        try{
            if($id===null){
                $data['created_by']=$actorId;
                $data['updated_by']=$actorId;
                $cols=array_keys($data);
                $st=$this->pdo->prepare("INSERT INTO {$d['table']}(".implode(',',$cols).") VALUES(".implode(',',array_fill(0,count($cols),'?')).")");
                $st->execute(array_values($data));
                return (int)$this->pdo->lastInsertId();
            }
            if($id<1) throw new HttpException(404,'Homologación inexistente.');
            $cols=array_keys($data);
            $updates=implode(',',array_map(static fn($c)=>"$c=?",$cols));
            $st=$this->pdo->prepare("UPDATE {$d['table']} SET $updates,updated_at=NOW(),updated_by=? WHERE id=?");
            $st->execute([...array_values($data),$actorId,$id]);
            $check=$this->pdo->prepare("SELECT id FROM {$d['table']} WHERE id=?");
            $check->execute([$id]);
            if($check->fetchColumn()===false) throw new HttpException(404,'Homologación inexistente.');
            return $id;
        }catch(PDOException $error){
            if((int)($error->errorInfo[1]??0)===1062) throw new HttpException(409,'El partner ya tiene homologado ese registro local.');
            throw $error;
        }
    }

    public function deactivate(string $tab,int $id,int $actorId): void
    {
        $d=$this->definition($tab);
        if($id<1 || $actorId<1) throw new HttpException(422,'Registro inválido.');
        $st=$this->pdo->prepare("UPDATE {$d['table']} SET estado=0,updated_at=NOW(),updated_by=? WHERE id=?");
        $st->execute([$actorId,$id]);
        if($st->rowCount()===0){
            $check=$this->pdo->prepare("SELECT id FROM {$d['table']} WHERE id=?");
            $check->execute([$id]);
            if($check->fetchColumn()===false) throw new HttpException(404,'Homologación inexistente.');
        }
    }

    private function resolveCode(string $table,string $column,string $code,int $row): int
    {
        $st=$this->pdo->prepare("SELECT id FROM $table WHERE $column=? LIMIT 1");
        $st->execute([$code]);
        $id=$st->fetchColumn();
        if($id===false) throw new HttpException(422,"Fila $row: el código '$code' no existe en $table.");
        return (int)$id;
    }

    /**
     * Registra exclusivamente productos ausentes cuando el administrador lo autorizó
     * en el formulario de importación. Quedan INACTIVOS hasta revisar unidad base,
     * categoría y datos maestros. No se inventa una homologación oficial de Bayer.
     *
     * @return array{id:int,created:bool}
     */
    private function resolveImportProduct(string $code,string $name,?int $unitId,int $row,bool $allowCreation): array
    {
        $find=$this->pdo->prepare('SELECT id FROM productos WHERE codigo=? LIMIT 1');
        $find->execute([$code]);
        $id=$find->fetchColumn();
        if($id!==false) return ['id'=>(int)$id,'created'=>false];

        if(!$allowCreation){
            throw new HttpException(422,
                "Fila $row: el código interno '$code' no existe en Catálogos maestros → Productos. "
                .'Registre el producto allí o active «Registrar productos faltantes» al importar.'
            );
        }
        if(mb_strlen($code)>30)
            throw new HttpException(422,"Fila $row: el código interno excede los 30 caracteres permitidos para un producto.");
        $name=trim($name);
        if($name==='' || mb_strlen($name)>150)
            throw new HttpException(422,
                "Fila $row: para registrar el producto '$code' debe completar nombre_bayer (máximo 150 caracteres).");
        try{
            $insert=$this->pdo->prepare(
                "INSERT INTO productos(codigo,nombre,tipo_art,unidad_base_id,estado) VALUES(?,?,'PRODUCTO',?,0)"
            );
            $insert->execute([$code,$name,$unitId]);
            return ['id'=>(int)$this->pdo->lastInsertId(),'created'=>true];
        }catch(PDOException $error){
            // Concurrencia: si otro administrador lo creó, reutilizar ese código.
            if((int)($error->errorInfo[1]??0)!==1062)throw $error;
            $find->execute([$code]);
            $id=$find->fetchColumn();
            if($id===false)throw $error;
            return ['id'=>(int)$id,'created'=>false];
        }
    }

    /** Importa todo o nada, actualizando por partner + elemento local sin duplicar. */
    public function importRows(string $tab,array $rows,int $actorId,bool $createMissingProducts=false): array
    {
        $d=$this->definition($tab);
        if($createMissingProducts && $tab!=='productos')
            throw new HttpException(422,'El registro automático de códigos faltantes solo corresponde a Productos Bayer.');
        if(count($rows)<1 || count($rows)>2000) throw new HttpException(422,'La plantilla admite de 1 a 2000 registros.');
        $expected=array_keys($d['import']);
        $headers=array_keys($rows[0]);
        if(array_diff($expected,$headers) || array_diff($headers,$expected)){
            throw new HttpException(422,'Los encabezados no coinciden con la plantilla XLSX de homologación.');
        }
        if($this->pdo->inTransaction()) throw new \LogicException('Importación ya en una transacción.');
        $this->pdo->beginTransaction();
        $inserted=0;$updated=0;$createdProducts=0;$seen=[];
        try{
            foreach($rows as $i=>$row){
                $line=$i+2;
                $partnerCode=trim((string)($row['partner_codigo']??''));
                $localCode=trim((string)($row['codigo_interno']??''));
                if($partnerCode==='' || $localCode==='') throw new HttpException(422,"Fila $line: complete partner_codigo y codigo_interno.");
                $partner=$this->resolveCode('partners','codigo',$partnerCode,$line);
                $unitId=null;
                if($tab==='productos'){
                    $unitCode=trim((string)($row['unidad_codigo']??''));
                    $unitId=$unitCode===''?null:$this->resolveCode('unidades_medida','codigo',$unitCode,$line);
                    $result=$this->resolveImportProduct(
                        $localCode,(string)($row['nombre_bayer']??''),$unitId,$line,$createMissingProducts
                    );
                    $local=$result['id'];
                    if($result['created'])$createdProducts++;
                }else{
                    $local=$this->resolveCode($d['local_table'],$d['local_code'],$localCode,$line);
                }
                $key="$partner:$local";
                if(isset($seen[$key])) throw new HttpException(422,"Fila $line: combinación duplicada dentro del Excel.");
                $seen[$key]=true;
                $input=[
                    'partner_id'=>$partner,'local_id'=>$local,
                    'codigo_bayer'=>$row['codigo_bayer']??'',
                    'nombre_bayer'=>$row['nombre_bayer']??'',
                    'estado'=>($row['estado']??'')===''?'1':$row['estado'],
                    'valid_from'=>$row['valid_from']??'',
                    'valid_until'=>$row['valid_until']??'',
                ];
                if($tab==='productos'){
                    $input['unidad_bayer_id']=$unitId??'';
                }
                $st=$this->pdo->prepare("SELECT id FROM {$d['table']} WHERE partner_id=? AND {$d['local_key']}=? LIMIT 1");
                $st->execute([$partner,$local]);
                $existing=$st->fetchColumn();
                try{
                    $this->save($tab,$input,$actorId,$existing===false?null:(int)$existing);
                }catch(HttpException $error){
                    throw new HttpException($error->status,"Fila $line: ".$error->getMessage());
                }
                if($existing===false) $inserted++; else $updated++;
            }
            $this->pdo->commit();
        }catch(Throwable $error){
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        return ['total'=>count($rows),'inserted'=>$inserted,'updated'=>$updated,'created_products'=>$createdProducts];
    }
}

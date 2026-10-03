<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use PDOException;

final class MasterCatalogService
{
    public function __construct(private ?PDO $pdo=null)
    {
        $this->pdo ??= \db();
    }

    public static function definitions(): array
    {
        $state=['type'=>'select','label'=>'Estado','required'=>true,'options'=>['1'=>'Activo','0'=>'Inactivo']];
        return [
            'clientes'=>[
                'title'=>'Clientes','table'=>'clientes','unique'=>'nro_doc',
                'fields'=>[
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>20,'required'=>false],
                    'tipo_doc'=>['label'=>'Tipo de documento','type'=>'text','max'=>2,'required'=>false,'help'=>'Ej.: 06 para RUC, 01 para DNI.'],
                    'nro_doc'=>['label'=>'N.º documento','type'=>'text','max'=>15,'required'=>true],
                    'razon_social'=>['label'=>'Razón social / nombre','type'=>'text','max'=>150,'required'=>true],
                    'distrito_id'=>['label'=>'Ubicación habitual','type'=>'select','source'=>'distritos','required'=>false,'help'=>'Al guardar se vinculan también provincia y departamento.'],
                ],
                'import'=>[
                    'codigo'=>['description'=>'Código interno del cliente','example'=>'CLI-100','required'=>false],
                    'tipo_doc'=>['description'=>'Código del tipo de documento','example'=>'06','required'=>false],
                    'nro_doc'=>['description'=>'DNI/RUC u otro documento único','example'=>'20609990011','required'=>true],
                    'razon_social'=>['description'=>'Razón social o nombre','example'=>'Agrícola Valle Verde S.A.C.','required'=>true],
                    'departamento'=>['description'=>'Departamento de ubicación habitual','example'=>'Ica','required'=>false],
                    'provincia'=>['description'=>'Provincia; completar junto con departamento y distrito','example'=>'Chincha','required'=>false],
                    'distrito'=>['description'=>'Distrito; completar los tres campos de ubicación','example'=>'Chincha Alta','required'=>false],
                ],
            ],
            'productos'=>[
                'title'=>'Productos','table'=>'productos','unique'=>'codigo',
                'fields'=>[
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>30,'required'=>true],
                    'nombre'=>['label'=>'Nombre','type'=>'text','max'=>150,'required'=>true],
                    'tipo_art'=>['label'=>'Tipo de artículo','type'=>'text','max'=>20,'required'=>false],
                    'unidad_base_id'=>['label'=>'Unidad base','type'=>'select','source'=>'unidades','required'=>true],
                    'categoria_id'=>['label'=>'Categoría','type'=>'select','source'=>'categorias','required'=>false],
                    'marca_id'=>['label'=>'Marca','type'=>'select','source'=>'marcas','required'=>false],
                    'estado'=>$state,
                ],
                'import'=>[
                    'codigo'=>['description'=>'Código único del producto','example'=>'MAT-100','required'=>true],
                    'nombre'=>['description'=>'Nombre del producto','example'=>'Producto agrícola 1 L','required'=>true],
                    'tipo_art'=>['description'=>'Tipo de artículo','example'=>'PRODUCTO','required'=>false],
                    'unidad_codigo'=>['description'=>'Código de unidad ya registrada','example'=>'NIU','required'=>true],
                    'categoria'=>['description'=>'Categoría; se crea si aún no existe','example'=>'Agrícola','required'=>false],
                    'marca'=>['description'=>'Marca; se crea si aún no existe','example'=>'Bayer','required'=>false],
                    'estado'=>['description'=>'1 activo / 0 inactivo','example'=>'1','required'=>false],
                ],
            ],
            'proveedores'=>[
                'title'=>'Proveedores','table'=>'proveedores','unique'=>'codigo',
                'fields'=>[
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>20,'required'=>true],
                    'nombre'=>['label'=>'Nombre','type'=>'text','max'=>150,'required'=>true],
                    'estado'=>$state,
                ],
                'import'=>[
                    'codigo'=>['description'=>'Código único','example'=>'PROV-001','required'=>true],
                    'nombre'=>['description'=>'Nombre o razón social','example'=>'Proveedor Ejemplo S.A.C.','required'=>true],
                    'estado'=>['description'=>'1 activo / 0 inactivo','example'=>'1','required'=>false],
                ],
            ],
            'vendedores'=>[
                'title'=>'Vendedores','table'=>'vendedores','unique'=>'codigo',
                'fields'=>[
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>20,'required'=>true],
                    'nombres'=>['label'=>'Nombres','type'=>'text','max'=>120,'required'=>true],
                    'apellidos'=>['label'=>'Apellidos','type'=>'text','max'=>120,'required'=>false],
                    'email'=>['label'=>'Correo','type'=>'email','max'=>120,'required'=>false],
                    'estado'=>$state,
                ],
                'import'=>[
                    'codigo'=>['description'=>'Código único del vendedor','example'=>'VEN-001','required'=>true],
                    'nombres'=>['description'=>'Nombres','example'=>'Luis Alberto','required'=>true],
                    'apellidos'=>['description'=>'Apellidos','example'=>'Ramírez Soto','required'=>false],
                    'email'=>['description'=>'Correo electrónico','example'=>'vendedor@ejemplo.pe','required'=>false],
                    'estado'=>['description'=>'1 activo / 0 inactivo','example'=>'1','required'=>false],
                ],
            ],
            'sucursales'=>[
                'title'=>'Sucursales','table'=>'sucursales','unique'=>'codigo',
                'fields'=>[
                    'empresa_id'=>['label'=>'Empresa','type'=>'select','source'=>'empresas','required'=>true],
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>20,'required'=>true],
                    'nombre'=>['label'=>'Nombre','type'=>'text','max'=>120,'required'=>true],
                    'direccion'=>['label'=>'Dirección','type'=>'text','max'=>180,'required'=>false],
                    'distrito_id'=>['label'=>'Ubicación','type'=>'select','source'=>'distritos','required'=>false],
                    'estado'=>$state,
                ],
                'import'=>[
                    'empresa_ruc'=>['description'=>'RUC de una empresa registrada','example'=>'20600000001','required'=>true],
                    'codigo'=>['description'=>'Código único de sucursal','example'=>'SUC-01','required'=>true],
                    'nombre'=>['description'=>'Nombre de sucursal','example'=>'Sede Chincha','required'=>true],
                    'direccion'=>['description'=>'Dirección','example'=>'Av. Principal 100','required'=>false],
                    'departamento'=>['description'=>'Departamento de la ubicación','example'=>'Ica','required'=>false],
                    'provincia'=>['description'=>'Provincia','example'=>'Chincha','required'=>false],
                    'distrito'=>['description'=>'Distrito','example'=>'Chincha Alta','required'=>false],
                    'estado'=>['description'=>'1 activo / 0 inactivo','example'=>'1','required'=>false],
                ],
            ],
            'almacenes'=>[
                'title'=>'Almacenes','table'=>'almacenes','unique'=>'codigo',
                'fields'=>[
                    'sucursal_id'=>['label'=>'Sucursal','type'=>'select','source'=>'sucursales','required'=>true],
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>20,'required'=>true],
                    'nombre'=>['label'=>'Nombre','type'=>'text','max'=>120,'required'=>true],
                    'tipo'=>['label'=>'Tipo','type'=>'text','max'=>40,'required'=>false],
                    'estado'=>$state,
                ],
                'import'=>[
                    'sucursal_codigo'=>['description'=>'Código de sucursal registrada','example'=>'SUC-01','required'=>true],
                    'codigo'=>['description'=>'Código único del almacén','example'=>'ALM-01','required'=>true],
                    'nombre'=>['description'=>'Nombre','example'=>'Almacén principal','required'=>true],
                    'tipo'=>['description'=>'Tipo de almacén','example'=>'GENERAL','required'=>false],
                    'estado'=>['description'=>'1 activo / 0 inactivo','example'=>'1','required'=>false],
                ],
            ],
            'unidades'=>[
                'title'=>'Unidades de medida','table'=>'unidades_medida','unique'=>'codigo',
                'fields'=>[
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>10,'required'=>true],
                    'nombre'=>['label'=>'Nombre','type'=>'text','max'=>40,'required'=>true],
                    'abreviatura'=>['label'=>'Abreviatura','type'=>'text','max'=>10,'required'=>false],
                    'factor_base'=>['label'=>'Factor base','type'=>'number','step'=>'0.0001','required'=>true],
                ],
                'import'=>[
                    'codigo'=>['description'=>'Código único','example'=>'NIU','required'=>true],
                    'nombre'=>['description'=>'Nombre','example'=>'Unidad','required'=>true],
                    'abreviatura'=>['description'=>'Abreviatura','example'=>'und','required'=>false],
                    'factor_base'=>['description'=>'Factor base positivo','example'=>'1','required'=>true],
                ],
            ],
            'empresas'=>[
                'title'=>'Empresas','table'=>'empresas','unique'=>'ruc',
                'fields'=>[
                    'ruc'=>['label'=>'RUC','type'=>'text','max'=>11,'required'=>true],
                    'razon_social'=>['label'=>'Razón social','type'=>'text','max'=>150,'required'=>true],
                    'nombre_comercial'=>['label'=>'Nombre comercial','type'=>'text','max'=>120,'required'=>false],
                    'estado'=>$state,
                ],
                'import'=>[
                    'ruc'=>['description'=>'RUC de 11 dígitos','example'=>'20600000001','required'=>true],
                    'razon_social'=>['description'=>'Razón social','example'=>'Empresa Ejemplo S.A.C.','required'=>true],
                    'nombre_comercial'=>['description'=>'Nombre comercial','example'=>'Empresa Ejemplo','required'=>false],
                    'estado'=>['description'=>'1 activo / 0 inactivo','example'=>'1','required'=>false],
                ],
            ],
            'tipos'=>[
                'title'=>'Tipos de documento','table'=>'tipos_documento','unique'=>'codigo',
                'fields'=>[
                    'codigo'=>['label'=>'Código','type'=>'text','max'=>4,'required'=>true],
                    'nombre'=>['label'=>'Nombre','type'=>'text','max'=>60,'required'=>true],
                    'sunat_code'=>['label'=>'Código SUNAT','type'=>'text','max'=>4,'required'=>false],
                ],
                'import'=>[
                    'codigo'=>['description'=>'Código interno único','example'=>'FAC','required'=>true],
                    'nombre'=>['description'=>'Nombre','example'=>'Factura','required'=>true],
                    'sunat_code'=>['description'=>'Código SUNAT','example'=>'01','required'=>false],
                ],
            ],
            'ubigeo'=>[
                'title'=>'Ubigeo','table'=>'distritos','unique'=>null,
                'fields'=>[
                    'departamento'=>['label'=>'Departamento','type'=>'text','max'=>60,'required'=>true],
                    'provincia'=>['label'=>'Provincia','type'=>'text','max'=>60,'required'=>true],
                    'distrito'=>['label'=>'Distrito','type'=>'text','max'=>60,'required'=>true],
                ],
                'import'=>[
                    'departamento'=>['description'=>'Nombre del departamento','example'=>'Ica','required'=>true],
                    'provincia'=>['description'=>'Nombre de la provincia','example'=>'Chincha','required'=>true],
                    'distrito'=>['description'=>'Nombre del distrito','example'=>'Chincha Alta','required'=>true],
                ],
            ],
        ];
    }

    public function definition(string $tab): array
    {
        $definitions=self::definitions();
        if(!isset($definitions[$tab])) throw new HttpException(404,'Catálogo no encontrado.');
        return $definitions[$tab];
    }

    public function tabs(): array
    {
        $tabs=[];
        foreach(self::definitions() as $key=>$definition) $tabs[$key]=$definition['title'];
        return $tabs;
    }

    public function formOptions(string $tab): array
    {
        $definition=$this->definition($tab);
        $options=[];
        foreach($definition['fields'] as $name=>$field){
            $source=$field['source']??null;
            if(!$source) continue;
            $options[$name]=match($source){
                'distritos'=>$this->rows("SELECT d.id,CONCAT(dp.nombre,' · ',p.nombre,' · ',d.nombre) label FROM distritos d JOIN provincias p ON p.id=d.provincia_id JOIN departamentos dp ON dp.id=p.departamento_id ORDER BY dp.nombre,p.nombre,d.nombre"),
                'unidades'=>$this->rows("SELECT id,CONCAT(codigo,' · ',nombre) label FROM unidades_medida ORDER BY nombre"),
                'categorias'=>$this->rows("SELECT id,nombre label FROM categorias_producto ORDER BY nombre"),
                'marcas'=>$this->rows("SELECT id,nombre label FROM marcas ORDER BY nombre"),
                'empresas'=>$this->rows("SELECT id,CONCAT(ruc,' · ',razon_social) label FROM empresas WHERE estado=1 ORDER BY razon_social"),
                'sucursales'=>$this->rows("SELECT id,CONCAT(codigo,' · ',nombre) label FROM sucursales WHERE estado=1 ORDER BY nombre"),
                default=>[],
            };
        }
        return $options;
    }

    public function find(string $tab,int $id): array
    {
        if($id<1) throw new HttpException(404,'Registro no encontrado.');
        $definition=$this->definition($tab);
        if($tab==='ubigeo'){
            $st=$this->pdo->prepare("SELECT d.id,dp.nombre departamento,p.nombre provincia,d.nombre distrito FROM distritos d JOIN provincias p ON p.id=d.provincia_id JOIN departamentos dp ON dp.id=p.departamento_id WHERE d.id=?");
            $st->execute([$id]);
        }else{
            $fields=array_keys($definition['fields']);
            $select=[];
            foreach($fields as $field){
                $select[]=$field;
            }
            $st=$this->pdo->prepare('SELECT id,'.implode(',',$select).' FROM '.$definition['table'].' WHERE id=?');
            $st->execute([$id]);
        }
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new HttpException(404,'Registro no encontrado.');
        return $row;
    }

    public function save(string $tab,array $input,?int $id=null): int
    {
        $definition=$this->definition($tab);
        $record=$this->normalize($tab,$input,$definition);
        if($tab==='ubigeo') return $this->saveUbigeo($record,$id);

        $table=$definition['table'];
        $unique=$definition['unique'];
        if($unique!==null){
            $sql="SELECT id FROM $table WHERE $unique=?";
            $params=[$record[$unique]];
            if($id!==null){$sql.=' AND id<>?';$params[]=$id;}
            $st=$this->pdo->prepare($sql.' LIMIT 1');
            $st->execute($params);
            if($st->fetchColumn()!==false) throw new HttpException(409,'Ya existe un registro con el mismo '.$unique.'.');
        }

        if($id===null){
            $cols=array_keys($record);
            $st=$this->pdo->prepare("INSERT INTO $table(".implode(',',$cols).') VALUES('.implode(',',array_fill(0,count($cols),'?')).')');
            $st->execute(array_values($record));
            return (int)$this->pdo->lastInsertId();
        }

        if($id<1) throw new HttpException(404,'Registro no encontrado.');
        $assign=implode(',',array_map(static fn(string $col):string=>$col.'=?',array_keys($record)));
        $st=$this->pdo->prepare("UPDATE $table SET $assign WHERE id=?");
        $st->execute([...array_values($record),$id]);
        $check=$this->pdo->prepare("SELECT id FROM $table WHERE id=?");
        $check->execute([$id]);
        if($check->fetchColumn()===false) throw new HttpException(404,'Registro no encontrado.');
        return $id;
    }

    public function delete(string $tab,int $id): void
    {
        $definition=$this->definition($tab);
        if($id<1) throw new HttpException(404,'Registro no encontrado.');
        $table=$definition['table'];
        try{
            $st=$this->pdo->prepare("DELETE FROM $table WHERE id=?");
            $st->execute([$id]);
            if($st->rowCount()!==1) throw new HttpException(404,'Registro no encontrado.');
        }catch(PDOException $error){
            if((int)($error->errorInfo[1]??0)===1451){
                throw new HttpException(409,'No se puede eliminar porque el registro está siendo usado. Edítelo y desactívelo cuando exista la opción Estado.');
            }
            throw $error;
        }
    }

    public function importRows(string $tab,array $rows): array
    {
        $definition=$this->definition($tab);
        $expected=array_keys($definition['import']);
        if(!$rows) throw new HttpException(422,'El archivo no contiene filas de datos.');

        $inserted=0;
        $updated=0;
        $this->pdo->beginTransaction();
        try{
            foreach($rows as $index=>$row){
                if(!is_array($row)) continue;
                $line=$index+2;
                $hasValue=false;
                foreach($row as $value) if(trim((string)$value)!==''){$hasValue=true;break;}
                if(!$hasValue) continue;

                foreach($expected as $column){
                    if(!array_key_exists($column,$row)){
                        throw new HttpException(422,"Falta la columna '$column' en el archivo.");
                    }
                }

                $payload=$this->importPayload($tab,$row,$line);
                $existing=$this->findExistingForImport($tab,$payload,$row);
                $this->save($tab,$payload,$existing);
                if($existing===null) $inserted++; else $updated++;
            }
            $this->pdo->commit();
        }catch(\Throwable $error){
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return ['inserted'=>$inserted,'updated'=>$updated,'total'=>$inserted+$updated];
    }

    private function importPayload(string $tab,array $row,int $line): array
    {
        $clean=static fn(string $key):string=>trim((string)($row[$key]??''));
        $state=static fn(string $value):string=>$value===''?'1':$value;

        return match($tab){
            'clientes'=>[
                'codigo'=>$clean('codigo'),
                'tipo_doc'=>$clean('tipo_doc'),
                'nro_doc'=>$clean('nro_doc'),
                'razon_social'=>$clean('razon_social'),
                'distrito_id'=>$this->resolveGeoImport($clean('departamento'),$clean('provincia'),$clean('distrito'),$line),
            ],
            'productos'=>[
                'codigo'=>$clean('codigo'),
                'nombre'=>$clean('nombre'),
                'tipo_art'=>$clean('tipo_art'),
                'unidad_base_id'=>$this->lookupId('unidades_medida','codigo',$clean('unidad_codigo'),$line,'unidad_codigo'),
                'categoria_id'=>$this->firstOrCreateName('categorias_producto',$clean('categoria')),
                'marca_id'=>$this->firstOrCreateName('marcas',$clean('marca')),
                'estado'=>$state($clean('estado')),
            ],
            'proveedores'=>[
                'codigo'=>$clean('codigo'),'nombre'=>$clean('nombre'),'estado'=>$state($clean('estado')),
            ],
            'vendedores'=>[
                'codigo'=>$clean('codigo'),'nombres'=>$clean('nombres'),'apellidos'=>$clean('apellidos'),
                'email'=>$clean('email'),'estado'=>$state($clean('estado')),
            ],
            'sucursales'=>[
                'empresa_id'=>$this->lookupId('empresas','ruc',$clean('empresa_ruc'),$line,'empresa_ruc'),
                'codigo'=>$clean('codigo'),'nombre'=>$clean('nombre'),'direccion'=>$clean('direccion'),
                'distrito_id'=>$this->resolveGeoImport($clean('departamento'),$clean('provincia'),$clean('distrito'),$line),
                'estado'=>$state($clean('estado')),
            ],
            'almacenes'=>[
                'sucursal_id'=>$this->lookupId('sucursales','codigo',$clean('sucursal_codigo'),$line,'sucursal_codigo'),
                'codigo'=>$clean('codigo'),'nombre'=>$clean('nombre'),'tipo'=>$clean('tipo'),'estado'=>$state($clean('estado')),
            ],
            'unidades'=>[
                'codigo'=>$clean('codigo'),'nombre'=>$clean('nombre'),'abreviatura'=>$clean('abreviatura'),'factor_base'=>$clean('factor_base'),
            ],
            'empresas'=>[
                'ruc'=>$clean('ruc'),'razon_social'=>$clean('razon_social'),'nombre_comercial'=>$clean('nombre_comercial'),'estado'=>$state($clean('estado')),
            ],
            'tipos'=>[
                'codigo'=>$clean('codigo'),'nombre'=>$clean('nombre'),'sunat_code'=>$clean('sunat_code'),
            ],
            'ubigeo'=>[
                'departamento'=>$clean('departamento'),'provincia'=>$clean('provincia'),'distrito'=>$clean('distrito'),
            ],
            default=>throw new HttpException(404,'Catálogo no encontrado.'),
        };
    }

    private function findExistingForImport(string $tab,array $payload,array $row): ?int
    {
        if($tab==='ubigeo'){
            $st=$this->pdo->prepare('SELECT d.id FROM distritos d JOIN provincias p ON p.id=d.provincia_id JOIN departamentos dp ON dp.id=p.departamento_id WHERE dp.nombre=? AND p.nombre=? AND d.nombre=? LIMIT 1');
            $st->execute([$payload['departamento'],$payload['provincia'],$payload['distrito']]);
            $id=$st->fetchColumn();
            return $id===false?null:(int)$id;
        }
        $definition=$this->definition($tab);
        $key=$definition['unique'];
        $st=$this->pdo->prepare('SELECT id FROM '.$definition['table'].' WHERE '.$key.'=? LIMIT 1');
        $st->execute([$payload[$key]]);
        $id=$st->fetchColumn();
        return $id===false?null:(int)$id;
    }

    private function normalize(string $tab,array $input,array $definition): array
    {
        if($tab==='ubigeo'){
            return [
                'departamento'=>$this->requiredText($input['departamento']??'',60,'Departamento'),
                'provincia'=>$this->requiredText($input['provincia']??'',60,'Provincia'),
                'distrito'=>$this->requiredText($input['distrito']??'',60,'Distrito'),
            ];
        }

        $result=[];
        foreach($definition['fields'] as $name=>$field){
            $value=$input[$name]??'';
            $required=(bool)($field['required']??false);
            $type=$field['type']??'text';

            if($type==='select' && isset($field['options'])){
                $value=(string)$value;
                if(!array_key_exists($value,$field['options'])) throw new HttpException(422,'Seleccione un valor válido para '.$field['label'].'.');
                $result[$name]=(int)$value;
                continue;
            }
            if($type==='select'){
                if($value==='' || $value===null){
                    if($required) throw new HttpException(422,'Seleccione '.$field['label'].'.');
                    $result[$name]=null;
                }else{
                    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    if($id===false) throw new HttpException(422,'Seleccione un valor válido para '.$field['label'].'.');
                    $result[$name]=(int)$id;
                }
                continue;
            }
            if($type==='number'){
                $raw=trim((string)$value);
                if(!preg_match('/^\d+(?:\.\d{1,4})?$/D',$raw) || (float)$raw<=0){
                    throw new HttpException(422,$field['label'].' debe ser un número positivo con hasta 4 decimales.');
                }
                $result[$name]=$raw;
                continue;
            }

            $text=trim((string)$value);
            if($required && $text==='') throw new HttpException(422,$field['label'].' es obligatorio.');
            $max=(int)($field['max']??255);
            if(mb_strlen($text)>$max) throw new HttpException(422,$field['label'].' supera el máximo de '.$max.' caracteres.');
            if($type==='email' && $text!=='' && !filter_var($text,FILTER_VALIDATE_EMAIL)){
                throw new HttpException(422,'Correo electrónico inválido.');
            }
            $result[$name]=$text==='' && in_array($name,['codigo','tipo_doc','apellidos','email','tipo_art','direccion','tipo','abreviatura','nombre_comercial','sunat_code'],true)
                ? null
                : $text;
        }

        if($tab==='clientes'){
            $district=$result['distrito_id']??null;
            unset($result['distrito_id']);
            if($district===null){
                $result['departamento_id']=null;$result['provincia_id']=null;$result['distrito_id']=null;
            }else{
                [$department,$province,$districtId]=$this->geoByDistrict((int)$district);
                $result['departamento_id']=$department;$result['provincia_id']=$province;$result['distrito_id']=$districtId;
            }
        }
        if($tab==='empresas' && !preg_match('/^\d{11}$/D',(string)$result['ruc'])){
            throw new HttpException(422,'El RUC debe tener exactamente 11 dígitos.');
        }

        return $result;
    }

    private function saveUbigeo(array $record,?int $id): int
    {
        $depId=$this->firstOrCreate('departamentos','nombre',$record['departamento'],[]);
        $st=$this->pdo->prepare('SELECT id FROM provincias WHERE departamento_id=? AND nombre=? LIMIT 1');
        $st->execute([$depId,$record['provincia']]);
        $provId=$st->fetchColumn();
        if($provId===false){
            $insert=$this->pdo->prepare('INSERT INTO provincias(departamento_id,nombre) VALUES(?,?)');
            $insert->execute([$depId,$record['provincia']]);
            $provId=(int)$this->pdo->lastInsertId();
        }else $provId=(int)$provId;

        if($id===null){
            $check=$this->pdo->prepare('SELECT id FROM distritos WHERE provincia_id=? AND nombre=? LIMIT 1');
            $check->execute([$provId,$record['distrito']]);
            if($check->fetchColumn()!==false) throw new HttpException(409,'Ese distrito ya está registrado en la provincia seleccionada.');
            $insert=$this->pdo->prepare('INSERT INTO distritos(provincia_id,nombre) VALUES(?,?)');
            $insert->execute([$provId,$record['distrito']]);
            return (int)$this->pdo->lastInsertId();
        }

        $check=$this->pdo->prepare('SELECT id FROM distritos WHERE provincia_id=? AND nombre=? AND id<>? LIMIT 1');
        $check->execute([$provId,$record['distrito'],$id]);
        if($check->fetchColumn()!==false) throw new HttpException(409,'Ese distrito ya está registrado en la provincia seleccionada.');
        $update=$this->pdo->prepare('UPDATE distritos SET provincia_id=?,nombre=? WHERE id=?');
        $update->execute([$provId,$record['distrito'],$id]);
        return $id;
    }

    private function resolveGeoImport(string $department,string $province,string $district,int $line): ?int
    {
        if($department==='' && $province==='' && $district==='') return null;
        if($department==='' || $province==='' || $district===''){
            throw new HttpException(422,"Fila $line: departamento, provincia y distrito deben completarse juntos.");
        }
        $st=$this->pdo->prepare('SELECT d.id FROM distritos d JOIN provincias p ON p.id=d.provincia_id JOIN departamentos dp ON dp.id=p.departamento_id WHERE dp.nombre=? AND p.nombre=? AND d.nombre=? LIMIT 1');
        $st->execute([$department,$province,$district]);
        $id=$st->fetchColumn();
        if($id===false) throw new HttpException(422,"Fila $line: la ubicación '$department / $province / $district' no existe en Ubigeo.");
        return (int)$id;
    }

    private function geoByDistrict(int $districtId): array
    {
        $st=$this->pdo->prepare('SELECT p.departamento_id,p.id provincia_id,d.id distrito_id FROM distritos d JOIN provincias p ON p.id=d.provincia_id WHERE d.id=?');
        $st->execute([$districtId]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new HttpException(422,'La ubicación seleccionada no existe.');
        return [(int)$row['departamento_id'],(int)$row['provincia_id'],(int)$row['distrito_id']];
    }

    private function lookupId(string $table,string $column,string $value,int $line,string $label): int
    {
        if($value==='') throw new HttpException(422,"Fila $line: $label es obligatorio.");
        $st=$this->pdo->prepare("SELECT id FROM $table WHERE $column=? LIMIT 1");
        $st->execute([$value]);
        $id=$st->fetchColumn();
        if($id===false) throw new HttpException(422,"Fila $line: no existe $label '$value'.");
        return (int)$id;
    }

    private function firstOrCreateName(string $table,string $name): ?int
    {
        if($name==='') return null;
        return $this->firstOrCreate($table,'nombre',$name,[]);
    }

    private function firstOrCreate(string $table,string $key,string $value,array $extra): int
    {
        $st=$this->pdo->prepare("SELECT id FROM $table WHERE $key=? LIMIT 1");
        $st->execute([$value]);
        $id=$st->fetchColumn();
        if($id!==false) return (int)$id;
        $data=[$key=>$value,...$extra];
        $cols=array_keys($data);
        $insert=$this->pdo->prepare("INSERT INTO $table(".implode(',',$cols).') VALUES('.implode(',',array_fill(0,count($cols),'?')).')');
        $insert->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    private function requiredText(mixed $value,int $max,string $label): string
    {
        $text=trim((string)$value);
        if($text==='') throw new HttpException(422,$label.' es obligatorio.');
        if(mb_strlen($text)>$max) throw new HttpException(422,$label.' supera el máximo de '.$max.' caracteres.');
        return $text;
    }

    private function rows(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

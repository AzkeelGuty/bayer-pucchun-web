<?php
namespace App\Services;

class MasterDataService
{
    private function firstOrCreate(string $table,string $key,array $data): int
    {
        $st=\db()->prepare("SELECT id FROM $table WHERE $key=? LIMIT 1");
        $st->execute([$data[$key]]);
        $id=$st->fetchColumn();
        if($id) return (int)$id;

        $cols=array_keys($data);
        $q=implode(',',array_fill(0,count($cols),'?'));
        $st=\db()->prepare("INSERT INTO $table(".implode(',',$cols).") VALUES($q)");
        $st->execute(array_values($data));
        return (int)\db()->lastInsertId();
    }

    public function company(array $d): int
    {
        return $this->firstOrCreate('empresas','ruc',[
            'ruc'=>$d['dealerId'],
            'razon_social'=>$d['dealerName'],
            'nombre_comercial'=>$d['dealerName'],
            'estado'=>1
        ]);
    }

    public function district(string $department,string $province,string $district): array
    {
        $dep=$this->firstOrCreate('departamentos','nombre',['nombre'=>$department]);
        $st=\db()->prepare('SELECT id FROM provincias WHERE departamento_id=? AND nombre=?');
        $st->execute([$dep,$province]);
        $prov=$st->fetchColumn();
        if(!$prov){
            $st=\db()->prepare('INSERT INTO provincias(departamento_id,nombre) VALUES(?,?)');
            $st->execute([$dep,$province]);
            $prov=\db()->lastInsertId();
        }

        $st=\db()->prepare('SELECT id FROM distritos WHERE provincia_id=? AND nombre=?');
        $st->execute([$prov,$district]);
        $dist=$st->fetchColumn();
        if(!$dist){
            $st=\db()->prepare('INSERT INTO distritos(provincia_id,nombre) VALUES(?,?)');
            $st->execute([$prov,$district]);
            $dist=\db()->lastInsertId();
        }
        return [(int)$dep,(int)$prov,(int)$dist];
    }

    public function branch(array $d,int $companyId,?int $districtId=null): int
    {
        return $this->firstOrCreate('sucursales','codigo',[
            'empresa_id'=>$companyId,
            'codigo'=>$d['branchId'],
            'nombre'=>$d['branchName'],
            'direccion'=>'',
            'distrito_id'=>$districtId,
            'estado'=>1
        ]);
    }

    public function warehouse(array $d,int $companyId): int
    {
        $branchCode='WH-'.$d['warehouseId'];
        $branch=$this->firstOrCreate('sucursales','codigo',[
            'empresa_id'=>$companyId,
            'codigo'=>$branchCode,
            'nombre'=>'Sucursal '.$d['warehouseName'],
            'direccion'=>'',
            'distrito_id'=>null,
            'estado'=>1
        ]);
        return $this->firstOrCreate('almacenes','codigo',[
            'sucursal_id'=>$branch,
            'codigo'=>$d['warehouseId'],
            'nombre'=>$d['warehouseName'],
            'tipo'=>'GENERAL',
            'estado'=>1
        ]);
    }

    public function client(array $d,?array $geo=null): int
    {
        return $this->firstOrCreate('clientes','nro_doc',[
            'codigo'=>$d['customerId'],
            'tipo_doc'=>strlen($d['customerId'])===11?'06':'01',
            'nro_doc'=>$d['customerId'],
            'razon_social'=>$d['customerName'],
            'departamento_id'=>$geo[0]??null,
            'provincia_id'=>$geo[1]??null,
            'distrito_id'=>$geo[2]??null
        ]);
    }

    public function seller(array $d): int
    {
        return $this->firstOrCreate('vendedores','codigo',[
            'codigo'=>$d['salesId'],
            'nombres'=>$d['salesName'],
            'apellidos'=>'',
            'email'=>'',
            'estado'=>1
        ]);
    }

    public function unit(array $d): int
    {
        return $this->firstOrCreate('unidades_medida','codigo',[
            'codigo'=>$d['measureUnit'],
            'nombre'=>$d['measureUnit'],
            'abreviatura'=>$d['measureUnit'],
            'factor_base'=>1
        ]);
    }

    public function product(array $d,int $unitId): int
    {
        return $this->firstOrCreate('productos','codigo',[
            'codigo'=>$d['materialId'],
            'nombre'=>$d['materialName'],
            'categoria_id'=>null,
            'marca_id'=>null,
            'unidad_base_id'=>$unitId,
            'estado'=>1
        ]);
    }

    public function docType(array $d): int
    {
        return $this->firstOrCreate('tipos_documento','codigo',[
            'codigo'=>$d['documentTypeId'],
            'nombre'=>$d['documentType'],
            'sunat_code'=>$d['documentTypeId']
        ]);
    }

    public function lot(array $d, int $productId): ?int
    {
        $code = trim((string)($d['batch'] ?? ''));
        if ($code === '') return null;

        $st=\db()->prepare('SELECT id FROM lotes WHERE producto_id=? AND codigo_lote=? LIMIT 1');
        $st->execute([$productId,$code]);
        $id=$st->fetchColumn();
        if($id) return (int)$id;

        $expiration = trim((string)($d['expirationDate'] ?? ''));
        $st=\db()->prepare('INSERT INTO lotes(producto_id,codigo_lote,fecha_vencimiento,estado) VALUES(?,?,?,1)');
        $st->execute([$productId,$code,$expiration !== '' ? $expiration : null]);
        return (int)\db()->lastInsertId();
    }
}

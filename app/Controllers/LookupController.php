<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use PDO;

final class LookupController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo=null)
    {
        $this->pdo=$pdo ?? \db();
    }

    public function search(): void
    {
        $rawType=\input('type','');
        $rawQ=\input('q','');
        $rawParent=\input('parent','0');
        $rawLimit=\input('limit','15');
        if(!is_scalar($rawType)||!is_scalar($rawQ)||!is_scalar($rawParent)||!is_scalar($rawLimit)){
            throw new HttpException(422,'Parámetros de búsqueda inválidos.');
        }
        $type=strtolower(trim((string)$rawType));
        $q=trim((string)$rawQ);
        if(mb_strlen($q)>80) $q=mb_substr($q,0,80);
        $parent=ctype_digit((string)$rawParent)?(int)$rawParent:0;
        $limit=ctype_digit((string)$rawLimit)?(int)$rawLimit:15;
        $limit=max(5,min(20,$limit));

        $items=match($type){
            'clientes'=>$this->clients($q,$limit),
            'productos'=>$this->products($q,$limit),
            'vendedores'=>$this->simple(
                "SELECT id,CONCAT(codigo,' · ',TRIM(CONCAT(nombres,' ',COALESCE(apellidos,'')))) label
                 FROM vendedores
                 WHERE estado=1 AND (codigo LIKE ? OR nombres LIKE ? OR apellidos LIKE ?)
                 ORDER BY nombres LIMIT ".$limit,
                [$q.'%','%'.$q.'%','%'.$q.'%']
            ),
            'sucursales'=>$this->simple(
                "SELECT id,CONCAT(codigo,' · ',nombre) label
                 FROM sucursales
                 WHERE estado=1 AND (codigo LIKE ? OR nombre LIKE ?)
                 ORDER BY nombre LIMIT ".$limit,
                [$q.'%','%'.$q.'%']
            ),
            'almacenes'=>$this->simple(
                "SELECT id,CONCAT(codigo,' · ',nombre) label
                 FROM almacenes
                 WHERE estado=1 AND (codigo LIKE ? OR nombre LIKE ?)
                 ORDER BY nombre LIMIT ".$limit,
                [$q.'%','%'.$q.'%']
            ),
            'provincias'=>$this->provinces($q,$parent,$limit),
            'distritos'=>$this->districts($q,$parent,$limit),
            'ubicaciones'=>$this->locations($q,$limit),
            'lotes'=>$this->lots($q,$parent,$limit),
            'tipos_documento'=>$this->simple(
                "SELECT id,CONCAT(codigo,' · ',nombre) label
                 FROM tipos_documento
                 WHERE codigo LIKE ? OR nombre LIKE ?
                 ORDER BY codigo LIMIT ".$limit,
                [$q.'%','%'.$q.'%']
            ),
            'unidades'=>$this->simple(
                "SELECT id,CONCAT(codigo,' · ',nombre) label
                 FROM unidades_medida
                 WHERE codigo LIKE ? OR nombre LIKE ?
                 ORDER BY nombre LIMIT ".$limit,
                [$q.'%','%'.$q.'%']
            ),
            default=>throw new HttpException(422,'Catálogo de búsqueda inválido.'),
        };

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=30');
        echo json_encode(['items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function clients(string $q,int $limit): array
    {
        // Búsqueda incremental por prefijo:
        // "b" devuelve clientes que comienzan con B, no nombres que tengan una
        // "b" en cualquier parte. Para números se prioriza DNI/RUC/código.
        $numeric=preg_match('/^\\d+$/D',$q)===1;
        $where=$numeric
            ? '(c.nro_doc LIKE ? OR c.codigo LIKE ?)'
            : 'c.razon_social LIKE ?';
        $params=$numeric
            ? [$q.'%',$q.'%']
            : [$q.'%'];

        $sql="SELECT
                c.id,
                CONCAT(c.nro_doc,' · ',c.razon_social) label,
                c.departamento_id,c.provincia_id,c.distrito_id,
                dp.nombre departamento,p.nombre provincia,ds.nombre distrito,
                COALESCE(
                    (SELECT g.vendedor_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                    (SELECT d.vendedor_id FROM documentos_cabecera d WHERE d.cliente_id=c.id ORDER BY d.fecha DESC,d.id DESC LIMIT 1)
                ) vendedor_id,
                COALESCE(
                    (SELECT g.sucursal_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                    (SELECT d.sucursal_id FROM documentos_cabecera d WHERE d.cliente_id=c.id ORDER BY d.fecha DESC,d.id DESC LIMIT 1)
                ) sucursal_id
              FROM clientes c
              LEFT JOIN departamentos dp ON dp.id=c.departamento_id
              LEFT JOIN provincias p ON p.id=c.provincia_id
              LEFT JOIN distritos ds ON ds.id=c.distrito_id
              WHERE ".$where."
              ORDER BY c.razon_social,c.id
              LIMIT ".$limit;
        $st=$this->pdo->prepare($sql);
        $st->execute($params);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);

        $sellerIds=[];$branchIds=[];
        foreach($rows as $row){
            if((int)($row['vendedor_id']??0)>0) $sellerIds[]=(int)$row['vendedor_id'];
            if((int)($row['sucursal_id']??0)>0) $branchIds[]=(int)$row['sucursal_id'];
        }
        $sellerLabels=$this->labelsByIds('vendedores',$sellerIds,"CONCAT(codigo,' · ',TRIM(CONCAT(nombres,' ',COALESCE(apellidos,''))))");
        $branchLabels=$this->labelsByIds('sucursales',$branchIds,"CONCAT(codigo,' · ',nombre)");

        return array_map(static function(array $row) use($sellerLabels,$branchLabels): array {
            $seller=(int)($row['vendedor_id']??0);
            $branch=(int)($row['sucursal_id']??0);
            return [
                'value'=>(string)$row['id'],
                'label'=>(string)$row['label'],
                'meta'=>[
                    'sellerId'=>$seller>0?(string)$seller:'',
                    'sellerLabel'=>$seller>0?($sellerLabels[$seller]??''):'',
                    'branchId'=>$branch>0?(string)$branch:'',
                    'branchLabel'=>$branch>0?($branchLabels[$branch]??''):'',
                    'departmentId'=>(string)($row['departamento_id']??''),
                    'departmentLabel'=>(string)($row['departamento']??''),
                    'provinceId'=>(string)($row['provincia_id']??''),
                    'provinceLabel'=>(string)($row['provincia']??''),
                    'districtId'=>(string)($row['distrito_id']??''),
                    'districtLabel'=>(string)($row['distrito']??''),
                ],
            ];
        },$rows);
    }

    private function products(string $q,int $limit): array
    {
        $st=$this->pdo->prepare(
            "SELECT p.id,CONCAT(p.codigo,' · ',p.nombre) label,p.unidad_base_id,
                    CONCAT(u.codigo,' · ',u.nombre) unidad_label
             FROM productos p
             LEFT JOIN unidades_medida u ON u.id=p.unidad_base_id
             WHERE p.estado=1 AND (p.codigo LIKE ? OR p.nombre LIKE ?)
             ORDER BY CASE WHEN p.codigo LIKE ? THEN 0 ELSE 1 END,p.nombre
             LIMIT ".$limit
        );
        $st->execute([$q.'%','%'.$q.'%',$q.'%']);
        return array_map(static fn(array $row):array=>[
            'value'=>(string)$row['id'],
            'label'=>(string)$row['label'],
            'meta'=>[
                'unitId'=>(string)($row['unidad_base_id']??''),
                'unitLabel'=>(string)($row['unidad_label']??''),
            ],
        ],$st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function provinces(string $q,int $parent,int $limit): array
    {
        if($parent<1) return [];
        return $this->simple(
            "SELECT id,nombre label FROM provincias
             WHERE departamento_id=? AND nombre LIKE ?
             ORDER BY nombre LIMIT ".$limit,
            [$parent,'%'.$q.'%']
        );
    }

    private function districts(string $q,int $parent,int $limit): array
    {
        if($parent<1) return [];
        return $this->simple(
            "SELECT id,nombre label FROM distritos
             WHERE provincia_id=? AND nombre LIKE ?
             ORDER BY nombre LIMIT ".$limit,
            [$parent,'%'.$q.'%']
        );
    }

    private function locations(string $q,int $limit): array
    {
        return $this->simple(
            "SELECT d.id,CONCAT(dp.nombre,' · ',p.nombre,' · ',d.nombre) label
             FROM distritos d
             JOIN provincias p ON p.id=d.provincia_id
             JOIN departamentos dp ON dp.id=p.departamento_id
             WHERE d.nombre LIKE ? OR p.nombre LIKE ? OR dp.nombre LIKE ?
             ORDER BY dp.nombre,p.nombre,d.nombre LIMIT ".$limit,
            ['%'.$q.'%','%'.$q.'%','%'.$q.'%']
        );
    }

    private function lots(string $q,int $parent,int $limit): array
    {
        if($parent<1) return [];
        return $this->simple(
            "SELECT id,CONCAT(codigo_lote,
                IF(fecha_vencimiento IS NULL,'',CONCAT(' · Vence ',DATE_FORMAT(fecha_vencimiento,'%d/%m/%Y')))) label
             FROM lotes
             WHERE estado=1 AND producto_id=? AND codigo_lote LIKE ?
             ORDER BY codigo_lote LIMIT ".$limit,
            [$parent,'%'.$q.'%']
        );
    }

    private function simple(string $sql,array $params): array
    {
        $st=$this->pdo->prepare($sql);
        $st->execute($params);
        return array_map(static fn(array $row):array=>[
            'value'=>(string)$row['id'],
            'label'=>(string)$row['label'],
            'meta'=>[],
        ],$st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function labelsByIds(string $table,array $ids,string $expression): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        if(!$ids) return [];
        $allowed=['vendedores','sucursales'];
        if(!in_array($table,$allowed,true)) return [];
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $st=$this->pdo->prepare("SELECT id,$expression label FROM $table WHERE id IN ($placeholders)");
        $st->execute($ids);
        $out=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row) $out[(int)$row['id']]=(string)$row['label'];
        return $out;
    }
}

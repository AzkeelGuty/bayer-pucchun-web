<?php
declare(strict_types=1);

namespace App\Services;

final class BayerDataService
{
    private function dateFilter(array $filters,array &$params,string $alias,string $date): array
    {
        $where=["$alias.estado_registro='PUBLICADO'"];
        foreach(['from'=>'>=','to'=>'<='] as $key=>$op){
            $value=trim((string)($filters[$key]??''));
            if($value!==''){
                $where[]="$alias.$date $op ?";
                $params[]=$value;
            }
        }
        return $where;
    }

    public function sales(array $filters=[],?int $limit=1000): array
    {
        $p=[];$w=$this->dateFilter($filters,$p,'dc','fecha');
        $branch=trim((string)($filters['branch']??''));
        if($branch!==''){$w[]='s.codigo=?';$p[]=$branch;}
        $q=trim((string)($filters['q']??''));
        if($q!==''){$w[]='(dc.numero LIKE ? OR c.razon_social LIKE ? OR pr.nombre LIKE ? OR pr.codigo LIKE ?)';$like='%'.$q.'%';array_push($p,$like,$like,$like,$like);}
        $sql="SELECT e.ruc dealerId,e.razon_social dealerName,td.codigo documentTypeId,td.nombre documentType,dc.numero documentNumber,DATE_FORMAT(dc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT(v.nombres,' ',COALESCE(v.apellidos,''))) salesName,s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,dd.cantidad quantity,dd.valor_unitario unitValue,pv.nombre province,dp.nombre department,ds.nombre district FROM documentos_cabecera dc JOIN tipos_documento td ON td.id=dc.tipo_documento_id JOIN clientes c ON c.id=dc.cliente_id JOIN vendedores v ON v.id=dc.vendedor_id JOIN sucursales s ON s.id=dc.sucursal_id JOIN empresas e ON e.id=s.empresa_id JOIN documentos_detalle dd ON dd.documento_id=dc.id JOIN productos pr ON pr.id=dd.producto_id JOIN unidades_medida um ON um.id=dd.unidad_id LEFT JOIN distritos ds ON ds.id=c.distrito_id LEFT JOIN provincias pv ON pv.id=c.provincia_id LEFT JOIN departamentos dp ON dp.id=c.departamento_id WHERE ".implode(' AND ',$w)." ORDER BY dc.fecha DESC,dc.id DESC".$this->limitClause($limit);
        $st=\db()->prepare($sql);$st->execute($p);return $st->fetchAll();
    }

    public function shipments(array $filters=[],?int $limit=1000): array
    {
        $p=[];$w=$this->dateFilter($filters,$p,'gc','fecha');
        $branch=trim((string)($filters['branch']??''));
        if($branch!==''){$w[]='s.codigo=?';$p[]=$branch;}
        $q=trim((string)($filters['q']??''));
        if($q!==''){$w[]='(gc.numero LIKE ? OR c.razon_social LIKE ? OR pr.nombre LIKE ? OR pr.codigo LIKE ?)';$like='%'.$q.'%';array_push($p,$like,$like,$like,$like);}
        $sql="SELECT e.ruc dealerId,e.razon_social dealerName,gc.numero documentNumber,DATE_FORMAT(gc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT(v.nombres,' ',COALESCE(v.apellidos,''))) salesName,s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,gd.cantidad quantity,pv.nombre province,dp.nombre department,ds.nombre district FROM guias_cabecera gc JOIN clientes c ON c.id=gc.cliente_id JOIN vendedores v ON v.id=gc.vendedor_id JOIN sucursales s ON s.id=gc.sucursal_id JOIN empresas e ON e.id=s.empresa_id JOIN guias_detalle gd ON gd.guia_id=gc.id JOIN productos pr ON pr.id=gd.producto_id JOIN unidades_medida um ON um.id=gd.unidad_id LEFT JOIN distritos ds ON ds.id=gc.distrito_id LEFT JOIN provincias pv ON pv.id=gc.provincia_id LEFT JOIN departamentos dp ON dp.id=gc.departamento_id WHERE ".implode(' AND ',$w)." ORDER BY gc.fecha DESC,gc.id DESC".$this->limitClause($limit);
        $st=\db()->prepare($sql);$st->execute($p);return $st->fetchAll();
    }

    public function inventory(array $filters=[],?int $limit=1000): array
    {
        $p=[];$w=$this->dateFilter($filters,$p,'sc','fecha_stock');
        $branch=trim((string)($filters['branch']??''));
        if($branch!==''){$w[]='s.codigo=?';$p[]=$branch;}
        $q=trim((string)($filters['q']??''));
        if($q!==''){$w[]='(a.nombre LIKE ? OR pr.nombre LIKE ? OR pr.codigo LIKE ? OR l.codigo_lote LIKE ?)';$like='%'.$q.'%';array_push($p,$like,$like,$like,$like);}
        $sql="SELECT e.ruc dealerId,e.razon_social dealerName,DATE_FORMAT(sc.fecha_stock,'%Y-%m-%d') stockDate,s.codigo branchId,s.nombre branchName,a.codigo warehouseId,a.nombre warehouseName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,l.codigo_lote batch,sd.cantidad quantity,DATE_FORMAT(l.fecha_vencimiento,'%Y-%m-%d') expirationDate FROM stock_cabecera sc JOIN almacenes a ON a.id=sc.almacen_id JOIN sucursales s ON s.id=a.sucursal_id JOIN empresas e ON e.id=s.empresa_id JOIN stock_detalle sd ON sd.stock_id=sc.id JOIN productos pr ON pr.id=sd.producto_id JOIN unidades_medida um ON um.id=sd.unidad_id LEFT JOIN lotes l ON l.id=sd.lote_id WHERE ".implode(' AND ',$w)." ORDER BY sc.fecha_stock DESC,sc.id DESC".$this->limitClause($limit);
        $st=\db()->prepare($sql);$st->execute($p);return $st->fetchAll();
    }

    public function dataset(string $type,array $filters=[],?int $limit=1000): array
    {
        return match($type){
            'documents','sales'=>$this->sales($filters,$limit),
            'guides','shipments'=>$this->shipments($filters,$limit),
            'stock','inventory'=>$this->inventory($filters,$limit),
            default=>[]
        };
    }

    /** API integration path: return every matching published row, without the portal's display cap. */
    public function completeDataset(string $type,array $filters=[]): array
    {
        return $this->dataset($type,$filters,null);
    }

    private function limitClause(?int $limit): string
    {
        if($limit===null) return '';
        $safe=max(1,min(5000,$limit));
        return ' LIMIT '.$safe;
    }

    public function branches(): array
    {
        return \db()->query("SELECT codigo,nombre FROM sucursales WHERE estado=1 ORDER BY nombre")->fetchAll();
    }
}

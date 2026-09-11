<?php
namespace App\Services;
class BayerDataService {
    private function filters(array &$params,string $alias='dc',string $date='fecha'): string {
        $w=["$alias.estado_registro='PUBLICADO'"];
        if(!empty($_GET['from'])){$w[]="$alias.$date>=?";$params[]=$_GET['from'];}
        if(!empty($_GET['to'])){$w[]="$alias.$date<=?";$params[]=$_GET['to'];}
        return ' WHERE '.implode(' AND ',$w);
    }
    public function sales(): array {
        $p=[];$w=$this->filters($p,'dc','fecha');
        $sql="SELECT e.ruc dealerId,e.razon_social dealerName,td.codigo documentTypeId,td.nombre documentType,dc.numero documentNumber,DATE_FORMAT(dc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT(v.nombres,' ',v.apellidos)) salesName,s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,dd.cantidad quantity,pv.nombre province,dp.nombre department,ds.nombre district FROM documentos_cabecera dc JOIN tipos_documento td ON td.id=dc.tipo_documento_id JOIN clientes c ON c.id=dc.cliente_id JOIN vendedores v ON v.id=dc.vendedor_id JOIN sucursales s ON s.id=dc.sucursal_id JOIN empresas e ON e.id=s.empresa_id JOIN documentos_detalle dd ON dd.documento_id=dc.id JOIN productos pr ON pr.id=dd.producto_id JOIN unidades_medida um ON um.id=dd.unidad_id LEFT JOIN distritos ds ON ds.id=c.distrito_id LEFT JOIN provincias pv ON pv.id=c.provincia_id LEFT JOIN departamentos dp ON dp.id=c.departamento_id $w ORDER BY dc.fecha DESC,dc.id DESC";
        $st=\db()->prepare($sql);$st->execute($p);return $st->fetchAll();
    }
    public function shipments(): array {
        $p=[];$w=$this->filters($p,'gc','fecha');
        $sql="SELECT e.ruc dealerId,e.razon_social dealerName,gc.numero documentNumber,DATE_FORMAT(gc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT(v.nombres,' ',v.apellidos)) salesName,s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,gd.cantidad quantity,pv.nombre province,dp.nombre department,ds.nombre district FROM guias_cabecera gc JOIN clientes c ON c.id=gc.cliente_id JOIN vendedores v ON v.id=gc.vendedor_id JOIN sucursales s ON s.id=gc.sucursal_id JOIN empresas e ON e.id=s.empresa_id JOIN guias_detalle gd ON gd.guia_id=gc.id JOIN productos pr ON pr.id=gd.producto_id JOIN unidades_medida um ON um.id=gd.unidad_id LEFT JOIN distritos ds ON ds.id=gc.distrito_id LEFT JOIN provincias pv ON pv.id=gc.provincia_id LEFT JOIN departamentos dp ON dp.id=gc.departamento_id $w ORDER BY gc.fecha DESC,gc.id DESC";
        $st=\db()->prepare($sql);$st->execute($p);return $st->fetchAll();
    }
    public function inventory(): array {
        $p=[];$w=$this->filters($p,'sc','fecha_stock');
        $sql="SELECT e.ruc dealerId,e.razon_social dealerName,DATE_FORMAT(sc.fecha_stock,'%Y-%m-%d') stockDate,a.codigo warehouseId,a.nombre warehouseName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,l.codigo_lote batch,sd.cantidad quantity,DATE_FORMAT(l.fecha_vencimiento,'%Y-%m-%d') expirationDate FROM stock_cabecera sc JOIN almacenes a ON a.id=sc.almacen_id JOIN sucursales s ON s.id=a.sucursal_id JOIN empresas e ON e.id=s.empresa_id JOIN stock_detalle sd ON sd.stock_id=sc.id JOIN productos pr ON pr.id=sd.producto_id JOIN unidades_medida um ON um.id=sd.unidad_id LEFT JOIN lotes l ON l.id=sd.lote_id $w ORDER BY sc.fecha_stock DESC,sc.id DESC";
        $st=\db()->prepare($sql);$st->execute($p);return $st->fetchAll();
    }
    public function dataset(string $type): array { return match($type){'documents','sales'=>$this->sales(),'guides','shipments'=>$this->shipments(),'stock','inventory'=>$this->inventory(),default=>[]}; }
}

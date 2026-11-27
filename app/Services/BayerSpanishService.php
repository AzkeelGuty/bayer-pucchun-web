<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Exceptions\HttpException;

/** Contrato español de entrega Bayer. Mantiene intacta la API v1. */
final class BayerSpanishService
{
    public function __construct(private PDO $pdo) {}
    private const FISCAL="CASE WHEN em.estado IN ('ACEPTADO','ACEPTADO_OBSERVADO') THEN CONCAT(em.serie,'-',LPAD(em.correlativo,8,'0')) WHEN em.id IS NULL THEN rf.numero_fiscal ELSE NULL END";
    private const SOURCE_FISCAL="CASE WHEN oe.estado IN ('ACEPTADO','ACEPTADO_OBSERVADO') THEN CONCAT(oe.serie,'-',LPAD(oe.correlativo,8,'0')) WHEN oe.id IS NULL THEN ofi.numero_fiscal ELSE NULL END";
    private const DEALER="NULLIF(e.ruc,'00000000000') ruc_distribuidor,CASE WHEN e.ruc='00000000000' THEN NULL ELSE e.razon_social END razon_social_distribuidor";
    private const PEOPLE="v.dni dni_vendedor,TRIM(CONCAT_WS(' ',v.nombres,NULLIF(v.apellidos,''))) nombre_vendedor,s.codigo codigo_sucursal,s.nombre nombre_sucursal";
    private const CUSTOMER="c.tipo_doc tipo_documento_cliente,c.nro_doc numero_documento_cliente,c.razon_social nombre_cliente";
    private const PRODUCT="pr.codigo codigo_producto,pr.nombre nombre_producto,um.codigo unidad_medida";
    private const GEO="p.nombre provincia,dpto.nombre departamento,di.nombre distrito";
    private const GEOJOIN=" LEFT JOIN distritos di ON di.id=COALESCE(rf.distrito_despacho_id,s.distrito_id) LEFT JOIN provincias p ON p.id=di.provincia_id LEFT JOIN departamentos dpto ON dpto.id=p.departamento_id";
    private const PEOPLEJOIN=" JOIN clientes c ON c.id=h.cliente_id JOIN vendedores v ON v.id=h.vendedor_id JOIN sucursales s ON s.id=h.sucursal_id JOIN empresas e ON e.id=s.empresa_id";
    private const PRODUCTJOIN=" JOIN productos pr ON pr.id=d.producto_id JOIN unidades_medida um ON um.id=d.unidad_id";

    public function rows(string $dataset,array $filters=[]): array
    {
        return $this->queryDataset($dataset,$filters,false);
    }

    /** Cuenta las mismas filas que entrega v2, sin descargar sus detalles. */
    public function countRows(string $dataset,array $filters=[]): int
    {
        return $this->queryDataset($dataset,$filters,true);
    }

    private function queryDataset(string $dataset,array $filters,bool $countOnly): array|int
    {
        if(!(new BayerFiscalReferenceService($this->pdo))->available())throw new HttpException(503,'Debe aplicar la migración 019 de integración Bayer.');
        $params=[];$where=["h.estado_registro='PUBLICADO'"];
        if($dataset==='facturas'){
            $number=self::FISCAL;
            $select=self::DEALER.",td.codigo codigo_tipo_documento,td.nombre tipo_documento,CASE h.estado_comprobante WHEN 'VIGENTE' THEN 1 WHEN 'ANULADO' THEN 0 END estado_documento,h.medio_pago medio_pago,$number numero_documento,h.fecha fecha_documento,".self::PEOPLE.','.self::CUSTOMER.','.self::PRODUCT.",d.cantidad cantidad,d.valor_unitario precio_unitario,ROUND(d.cantidad*d.valor_unitario,2) importe_linea,".self::GEO;
            $from="documentos_cabecera h JOIN tipos_documento td ON td.id=h.tipo_documento_id JOIN documentos_detalle d ON d.documento_id=h.id".self::PEOPLEJOIN.self::PRODUCTJOIN.
                " LEFT JOIN sunat_emisiones em ON em.documento_id=h.id AND em.ambiente='PRODUCCION' AND em.ruc_emisor=e.ruc LEFT JOIN bayer_referencias_fiscales rf ON rf.documento_id=h.id AND rf.ruc_emisor=e.ruc".self::GEOJOIN;
            $where[]="($number) IS NOT NULL";$date='h.fecha';
            if(isset($filters['document_status'])){$where[]='h.estado_comprobante=?';$params[]=$filters['document_status'];}
        }elseif($dataset==='guias'){
            $number='rf.numero_fiscal';
            $select=self::DEALER.",$number numero_documento,'EMISION_EXTERNA_DECLARADA' estado_sunat,h.documento_origen_id id_documento_origen,".self::SOURCE_FISCAL." numero_documento_origen,h.fecha fecha_documento,".self::PEOPLE.",CASE c.tipo_doc WHEN '01' THEN 'DNI' WHEN '06' THEN 'RUC' ELSE c.tipo_doc END tipo_documento_cliente,c.nro_doc numero_documento_cliente,c.razon_social nombre_cliente,".self::PRODUCT.",d.cantidad cantidad,".self::GEO;
            $from="guias_cabecera h JOIN guias_detalle d ON d.guia_id=h.id".self::PEOPLEJOIN.self::PRODUCTJOIN.
                " JOIN bayer_referencias_fiscales rf ON rf.guia_id=h.id AND rf.ruc_emisor=e.ruc LEFT JOIN documentos_cabecera od ON od.id=h.documento_origen_id LEFT JOIN sucursales os ON os.id=od.sucursal_id LEFT JOIN empresas oeempresa ON oeempresa.id=os.empresa_id LEFT JOIN sunat_emisiones oe ON oe.documento_id=od.id AND oe.ambiente='PRODUCCION' AND oe.ruc_emisor=oeempresa.ruc LEFT JOIN bayer_referencias_fiscales ofi ON ofi.documento_id=od.id AND ofi.ruc_emisor=oeempresa.ruc".self::GEOJOIN;
            $where[]="$number IS NOT NULL";$date='h.fecha';
        }elseif($dataset==='stock'){
            $number='a.codigo';
            $select=self::DEALER.",h.fecha_stock fecha_stock,a.codigo codigo_almacen,CASE WHEN di.id IS NULL THEN a.nombre ELSE CONCAT(a.nombre,' (',dpto.nombre,'-',p.nombre,'-',di.nombre,')') END nombre_almacen,".self::PRODUCT.",l.codigo_lote lote,d.cantidad cantidad,l.fecha_vencimiento fecha_vencimiento";
            $from="stock_cabecera h JOIN stock_detalle d ON d.stock_id=h.id JOIN almacenes a ON a.id=h.almacen_id JOIN sucursales s ON s.id=a.sucursal_id JOIN empresas e ON e.id=s.empresa_id".self::PRODUCTJOIN." LEFT JOIN lotes l ON l.id=d.lote_id LEFT JOIN distritos di ON di.id=COALESCE(a.distrito_id,s.distrito_id) LEFT JOIN provincias p ON p.id=di.provincia_id LEFT JOIN departamentos dpto ON dpto.id=p.departamento_id";
            $date='h.fecha_stock';
        }else throw new \InvalidArgumentException('Conjunto de datos inválido.');
        foreach(['from'=>'>=','to'=>'<='] as $key=>$op)if(isset($filters[$key])){$where[]="$date $op ?";$params[]=$filters[$key];}
        if(isset($filters['branch'])){$where[]='s.codigo=?';$params[]=$filters['branch'];}
        if(isset($filters['q'])){
            $search=$dataset==='stock'?'a.nombre':'c.razon_social';
            $where[]="($number LIKE ? OR $search LIKE ? OR pr.codigo LIKE ? OR pr.nombre LIKE ?)";
            $like=$filters['q'].'%';array_push($params,$like,$like,$like,$like);
        }
        $projection=$countOnly?'COUNT(*)':$select;
        $order=$countOnly?'':" ORDER BY $date DESC,h.id DESC,d.id";
        $st=$this->pdo->prepare("SELECT $projection FROM $from WHERE ".implode(' AND ',$where).$order);
        $st->execute($params);
        if($countOnly)return (int)$st->fetchColumn();
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){
            $quantity=\quantity_integer_value($row['cantidad'],$dataset==='stock');
            if($quantity!==null)$row['cantidad']=$quantity;
            if(array_key_exists('estado_documento',$row)&&$row['estado_documento']!==null)$row['estado_documento']=(int)$row['estado_documento'];
            foreach(['precio_unitario','importe_linea'] as $field)if(isset($row[$field]))$row[$field]=(float)$row[$field];
        }
        unset($row);return $rows;
    }
}

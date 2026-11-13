<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;

/** Solo facturas gravadas al IGV. Las boletas, notas y GRE siguen un flujo propio. */
final class SunatInvoiceBuilder
{
    public function __construct(private PDO $pdo) {}

    public function snapshot(int $documentId): array
    {
        if($documentId<1) throw new HttpException(422,'Documento inválido.');
        $st=$this->pdo->prepare(
            "SELECT d.*,t.codigo AS tipo_codigo,c.nro_doc AS cliente_documento,
                    c.razon_social AS cliente_nombre,e.ruc AS ruc_emisor,
                    e.razon_social AS emisor_nombre,e.nombre_comercial AS emisor_comercial
             FROM documentos_cabecera d
             JOIN tipos_documento t ON t.id=d.tipo_documento_id
             JOIN clientes c ON c.id=d.cliente_id
             JOIN sucursales s ON s.id=d.sucursal_id
             JOIN empresas e ON e.id=s.empresa_id
             WHERE d.id=?"
        );
        $st->execute([$documentId]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new HttpException(404,'Documento no encontrado.');
        if(!in_array(strtoupper((string)$row['tipo_codigo']),['FAC','01'],true))
            throw new HttpException(422,'La primera integración de emisión directa está habilitada sólo para facturas.');
        if((string)$row['estado_registro']!=='PUBLICADO' || (string)$row['estado_comprobante']!=='VIGENTE')
            throw new HttpException(422,'La factura debe estar publicada y vigente antes de emitir.');
        if(!preg_match('/^[0-9]{11}$/D',(string)$row['cliente_documento']))
            throw new HttpException(422,'La factura electrónica requiere RUC de 11 dígitos del cliente.');
        if(!preg_match('/^[0-9]{11}$/D',(string)$row['ruc_emisor']))
            throw new HttpException(422,'La sucursal no tiene un emisor con RUC válido.');
        if((string)$row['ruc_emisor']!==(string)\env('SUNAT_RUC',''))
            throw new HttpException(422,'El RUC de la sucursal no coincide con el emisor SUNAT configurado.');
        $lines=$this->pdo->prepare(
            'SELECT d.cantidad,d.valor_unitario,p.codigo AS producto_codigo,
                p.nombre AS producto_nombre,u.codigo AS unidad_codigo
             FROM documentos_detalle d
             JOIN productos p ON p.id=d.producto_id
             JOIN unidades_medida u ON u.id=d.unidad_id
             WHERE d.documento_id=? ORDER BY d.id'
        );
        $lines->execute([$documentId]);
        $row['lineas']=$lines->fetchAll(PDO::FETCH_ASSOC);
        if(!$row['lineas']) throw new HttpException(422,'La factura debe contener productos.');
        return $row;
    }

    /** Precios explícitos por factura: bruto con IGV o neto antes de IGV. */
    public static function totals(array $lines,string $mode): array
    {
        if(!in_array($mode,['PRECIO_INCLUYE_IGV','PRECIO_SIN_IGV'],true))
            throw new HttpException(422,'Seleccione si los precios incluyen IGV.');
        $unitMap=['UND'=>'NIU','UN'=>'NIU','NIU'=>'NIU','KGM'=>'KGM','KG'=>'KGM',
            'GRM'=>'GRM','G'=>'GRM','LTR'=>'LTR','LT'=>'LTR','L'=>'LTR',
            'MTR'=>'MTR','M'=>'MTR','TNE'=>'TNE'];
        $results=[]; $totalBase=0.;$totalTax=0.;$grand=0.;
        foreach($lines as $line) {
            $quantity=(float)($line['cantidad']??0);
            $price=(float)($line['valor_unitario']??0);
            if(!is_finite($quantity)||!is_finite($price)||$quantity<=0||$price<=0||$quantity>1e7||$price>1e9)
                throw new HttpException(422,'Los productos a emitir necesitan cantidad y precio positivos.');
            $unit=strtoupper(trim((string)($line['unidad_codigo']??'')));
            if(!isset($unitMap[$unit]))
                throw new HttpException(422,'Configurar unidad SUNAT válida para '.$unit.'.');
            if($mode==='PRECIO_INCLUYE_IGV') {
                $gross=round($quantity*$price,2);
                $base=round($gross/1.18,2);
                $igv=round($gross-$base,2);
            } else {
                $base=round($quantity*$price,2);
                $igv=round($base*0.18,2);
                $gross=round($base+$igv,2);
            }
            $results[]=[
                'codigo'=>(string)($line['producto_codigo']??''),
                'nombre'=>(string)($line['producto_nombre']??''),
                'unidad'=>$unitMap[$unit],'cantidad'=>$quantity,
                'valor_unitario'=>round($base/$quantity,6),
                'precio_unitario'=>round($gross/$quantity,6),
                'base'=>$base,'igv'=>$igv,'total'=>$gross,
            ];
            $totalBase+=$base; $totalTax+=$igv; $grand+=$gross;
        }
        $base=round($totalBase,2);$igv=round($totalTax,2);$grand=round($grand,2);
        if($grand<=0||$grand>999999999.99)
            throw new HttpException(422,'Importe fuera del rango admitido.');
        return ['lineas'=>$results,'gravada'=>$base,'igv'=>$igv,'total'=>$grand,'moneda'=>'PEN'];
    }

    public static function documentName(string $ruc,string $serie,int $correlativo): string
    {
        if(!preg_match('/^[0-9]{11}$/D',$ruc)||!preg_match('/^F[A-Z0-9]{3}$/D',$serie)||$correlativo<1||$correlativo>99999999)
            throw new HttpException(422,'Serie o correlativo fiscal inválidos.');
        return $ruc.'-01-'.$serie.'-'.$correlativo;
    }

    /** Greenter crea UBL 2.1, aplica XMLDSig y envía mediante el servicio oficial. */
    public static function createGreenterInvoice(array $document,array $totals,string $serie,int $correlativo): object
    {
        if(!class_exists(\Greenter\Model\Sale\Invoice::class))
            throw new HttpException(503,'Instalar las dependencias Greenter antes de emitir.');
        $address=(new \Greenter\Model\Company\Address())
            ->setUbigueo((string)\env('SUNAT_UBIGEO',''))
            ->setDepartamento((string)\env('SUNAT_DEPARTAMENTO',''))
            ->setProvincia((string)\env('SUNAT_PROVINCIA',''))
            ->setDistrito((string)\env('SUNAT_DISTRITO',''))
            ->setUrbanizacion('-')
            ->setDireccion((string)\env('SUNAT_DIRECCION',''))
            ->setCodLocal('0000');
        $company=(new \Greenter\Model\Company\Company())
            ->setRuc((string)$document['ruc_emisor'])
            ->setRazonSocial((string)$document['emisor_nombre'])
            ->setNombreComercial((string)(\env('SUNAT_NOMBRE_COMERCIAL',$document['emisor_comercial']?:$document['emisor_nombre'])))
            ->setAddress($address);
        $client=(new \Greenter\Model\Client\Client())
            ->setTipoDoc('6')
            ->setNumDoc((string)$document['cliente_documento'])
            ->setRznSocial((string)$document['cliente_nombre']);
        $invoice=(new \Greenter\Model\Sale\Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc('01')
            ->setSerie($serie)
            ->setCorrelativo((string)$correlativo)
            ->setFechaEmision(new \DateTimeImmutable((string)$document['fecha'].' 12:00:00',new \DateTimeZone('America/Lima')))
            ->setFormaPago(new \Greenter\Model\Sale\FormaPagos\FormaPagoContado())
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($totals['gravada'])
            ->setMtoIGV($totals['igv'])
            ->setTotalImpuestos($totals['igv'])
            ->setValorVenta($totals['gravada'])
            ->setSubTotal($totals['total'])
            ->setMtoImpVenta($totals['total']);
        $items=[];
        foreach($totals['lineas'] as $row) {
            $items[]=(new \Greenter\Model\Sale\SaleDetail())
                ->setCodProducto($row['codigo'])
                ->setUnidad($row['unidad'])
                ->setCantidad($row['cantidad'])
                ->setMtoValorUnitario($row['valor_unitario'])
                ->setDescripcion($row['nombre'])
                ->setMtoBaseIgv($row['base'])
                ->setPorcentajeIgv(18.0)
                ->setIgv($row['igv'])
                ->setTipAfeIgv('10')
                ->setTotalImpuestos($row['igv'])
                ->setMtoValorVenta($row['base'])
                ->setMtoPrecioUnitario($row['precio_unitario']);
        }
        if(!class_exists(\NumberFormatter::class))throw new HttpException(503,'Habilitar extensión PHP intl.');
        $formatter=new \NumberFormatter('es_PE',\NumberFormatter::SPELLOUT);
        $soles=(int)floor($totals['total']);
        $cents=(int)round(($totals['total']-$soles)*100);
        $words=strtoupper((string)$formatter->format($soles));
        $legend=(new \Greenter\Model\Sale\Legend())->setCode('1000')
            ->setValue('SON '.$words.' CON '.str_pad((string)$cents,2,'0',STR_PAD_LEFT).'/100 SOLES');
        return $invoice->setDetails($items)->setLegends([$legend]);
    }
}

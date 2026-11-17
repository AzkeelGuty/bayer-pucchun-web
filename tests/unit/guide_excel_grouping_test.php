<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Services/GuideExcelLayout.php';
use App\Services\GuideExcelLayout;

$rows=[
    ['documentNumber'=>'INT-GD-000001','documentDate'=>'2026-11-16',
     'customerId'=>'45149906','customerName'=>'CLIENTE DE PRUEBA',
     'sourceDocumentNumber'=>'INT-FAC-000001','salesName'=>'Vendedor 1',
     'branchName'=>'Chincha','district'=>'Grocio Prado','province'=>'Chincha',
     'department'=>'Ica','materialId'=>'000001','materialName'=>'Producto A',
     'measureUnit'=>'LTR','quantity'=>3,'sunatStatus'=>'NO_EMITIDA'],
    ['documentNumber'=>'INT-GD-000001','documentDate'=>'2026-11-16',
     'customerId'=>'45149906','customerName'=>'CLIENTE DE PRUEBA',
     'sourceDocumentNumber'=>'INT-FAC-000001','materialId'=>'000002',
     'materialName'=>'Producto B','measureUnit'=>'LTR','quantity'=>4,
     'sunatStatus'=>'NO_EMITIDA'],
    ['documentNumber'=>'INT-GD-000001','documentDate'=>'2026-11-16',
     'customerId'=>'45149906','customerName'=>'CLIENTE DE PRUEBA',
     'sourceDocumentNumber'=>'INT-FAC-000001','materialId'=>'000003',
     'materialName'=>'Producto C','measureUnit'=>'KGM','quantity'=>2,
     'sunatStatus'=>'NO_EMITIDA'],
    ['documentNumber'=>'INT-GD-000002','documentDate'=>'2026-11-17',
     'customerId'=>'70707070','customerName'=>'SEGUNDO CLIENTE',
     'materialId'=>'000004','materialName'=>'Producto D','measureUnit'=>'NIU',
     'quantity'=>5,'sunatStatus'=>'NO_EMITIDA'],
];
$out=GuideExcelLayout::build($rows);
$ensure=static function(bool $yes,string $error):void {
    if(!$yes)throw new RuntimeException($error);
};
$ensure($out['guide_count']===2 && $out['product_lines']===4,'Conteos incorrectos');
$ensure(!in_array('sunatStatus',$out['keys'],true),'Estado SUNAT incluido en Excel');
$guideRows=array_values(array_filter($out['rows'],static fn($r)=>$r['_kind']==='guide'));
$detailRows=array_values(array_filter($out['rows'],static fn($r)=>$r['_kind']==='detail'));
$subtotalRows=array_values(array_filter($out['rows'],static fn($r)=>$r['_kind']==='subtotal'));
$ensure(count($guideRows)===2 && count($detailRows)===4 && count($subtotalRows)===3,
    'La agrupación y los subtotales no corresponden');
$ensure($guideRows[0]['guideNumber']==='INT-GD-000001' &&
    $guideRows[0]['customerName']==='CLIENTE DE PRUEBA' &&
    $guideRows[0]['customerDocument']==='45149906',
    'Identidad y número de guía no coinciden');
$ensure($guideRows[0]['destination']==='Grocio Prado / Chincha / Ica',
    'Destino omitido');
$ensure($detailRows[0]['customerName']==='' &&
    $detailRows[1]['guideNumber']==='' &&
    $detailRows[2]['customerDocument']==='',
    'La identidad del cliente se repite por producto');
$ensure((float)$subtotalRows[0]['quantity']===7.0 &&
    $subtotalRows[0]['unitCode']==='LTR' &&
    (float)$subtotalRows[1]['quantity']===2.0 &&
    $subtotalRows[1]['unitCode']==='KGM',
    'Se agregaron cantidades de unidades incompatibles');
$ensure((float)$subtotalRows[2]['quantity']===5.0 &&
    $subtotalRows[2]['unitCode']==='NIU',
    'Total segunda guía incorrecto');
foreach($out['rows'] as $row){
    $ensure(!isset($row['sunatStatus']),'Estado SUNAT filtrado incorrectamente');
}
echo "GUIAS EXCEL AGRUPADAS OK (cliente único, cantidades por unidad y estado SUNAT excluido)\n";

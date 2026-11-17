<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Services/CommercialTotalsService.php';
require dirname(__DIR__,2).'/app/Services/GuideExcelLayout.php';
use App\Services\GuideExcelLayout;

$rows=[
    ['documentNumber'=>'INT-GD-000000000010','documentDate'=>'2026-11-12',
     'customerId'=>'29598416','customerName'=>'ABARCA CHAUPI ANGEL MOISES',
     'sourceDocumentNumber'=>'INT-FAC-00000002','salesName'=>'Luis Alberto Ramírez Soto',
     'branchName'=>'Centro Operativo Grocio Prado','district'=>'Grocio Prado',
     'province'=>'Chincha','department'=>'Ica','materialId'=>'0000077381',
     'materialName'=>'DAGGER 50 PM X 1 KG','measureUnit'=>'KGM','quantity'=>3,
     'unitValue'=>'10.00','guideTaxMode'=>'ADICIONAL'],
    ['documentNumber'=>'INT-GD-000000000010','documentDate'=>'2026-11-12',
     'customerId'=>'29598416','customerName'=>'ABARCA CHAUPI ANGEL MOISES',
     'materialId'=>'0000073025','materialName'=>'DAKUTEN X 1 LT',
     'measureUnit'=>'LTR','quantity'=>3,'unitValue'=>'20.00'],
    ['documentNumber'=>'INT-GD-000000000010','documentDate'=>'2026-11-12',
     'customerId'=>'29598416','customerName'=>'ABARCA CHAUPI ANGEL MOISES',
     'materialId'=>'0000075505','materialName'=>'FARMOZINE 500 SC X LT',
     'measureUnit'=>'NIU','quantity'=>5,'unitValue'=>'30.00'],
    ['documentNumber'=>'INT-GD-000000000011','documentDate'=>'2026-11-13',
     'customerId'=>'45149906','customerName'=>'DAVID JOSE TICONA PUMA',
     'materialId'=>'0000078946','materialName'=>'X-CYTE X 1 LT',
     'measureUnit'=>'LTR','quantity'=>3],
    ['documentNumber'=>'INT-GD-000000000011','documentDate'=>'2026-11-13',
     'customerId'=>'45149906','customerName'=>'DAVID JOSE TICONA PUMA',
     'materialId'=>'0000050176','materialName'=>'D-CANTO 100 EC X 1 LT',
     'measureUnit'=>'LTR','quantity'=>4],
];
$out=GuideExcelLayout::build($rows);
$check=static function(bool $yes,string $reason):void {
    if(!$yes)throw new RuntimeException($reason);
};
$check($out['guide_count']===2 && $out['product_lines']===5,'Contadores de guías y productos');
$check(!in_array('sunatStatus',$out['keys'],true),'Estado SUNAT no permitido en Excel');
$guideRows=array_values(array_filter($out['rows'],fn($r)=>$r['_kind']==='guide'));
$details=array_values(array_filter($out['rows'],fn($r)=>$r['_kind']==='detail'));
$totals=array_values(array_filter($out['rows'],fn($r)=>$r['_kind']==='total'));
$check(count($guideRows)===2 && count($details)===5 && count($totals)===2,
       'Debe aparecer exactamente un total por cada número de guía');
$check(count(array_filter($out['rows'],fn($r)=>$r['_kind']==='subtotal'))===0,
       'No deben repetirse subtotales con el texto TOTAL DE GUÍA');
$check($guideRows[0]['guideNumber']==='INT-GD-000000000010'
    && $guideRows[0]['customerName']==='ABARCA CHAUPI ANGEL MOISES'
    && $guideRows[0]['customerDocument']==='29598416'
    && $guideRows[0]['destination']==='Grocio Prado / Chincha / Ica',
    'Identidad y guía deben mostrarse una sola vez');
$check($details[0]['customerName']==='' && $details[1]['guideNumber']==='',
    'No repetir cliente o número de guía en cada producto');
$check((int)$totals[0]['quantity']===11 && $totals[0]['unitCode']==='MIXTA',
    'Guía 10: 3 KGM + 3 LTR + 5 NIU deben sumar 11 como indicador numérico');
$check($totals[0]['destination']==='' && $guideRows[0]['destination']==='Grocio Prado / Chincha / Ica',
    'Guía 10: destino solo en la cabecera, sin texto de desglose');
$check($totals[0]['productName']==='TOTAL DE GUÍA · 3 producto(s)',
    'Guía 10: debe contar tres referencias diferentes');
$check((int)$totals[1]['quantity']===7 && $totals[1]['unitCode']==='LTR'
    && $totals[1]['destination']==='',
    'Guía 11: cuando hay una sola unidad, total 7 LTR sin desglose redundante');
$check((int)round((float)$totals[0]['lineTotal']*100)===24000
    && (int)round((float)$totals[0]['taxAmount']*100)===4320
    && (int)round((float)$totals[0]['finalTotal']*100)===28320,
    'Guía 10: subtotal S/ 240.00 + IGV S/ 43.20 = total S/ 283.20');
$check((int)round((float)$details[0]['finalTotal']*100)===3540,
    'Línea: tres productos a S/ 10.00 más IGV = S/ 35.40');
$check($totals[1]['lineTotal']===null && $totals[1]['finalTotal']===null,
    'No inventar importe para una guía sin precio');
$incl=GuideExcelLayout::build([[
    'documentNumber'=>'GUIA-INCLUIDA','materialId'=>'PX','measureUnit'=>'NIU',
    'quantity'=>1,'unitValue'=>'11.80','guideTaxMode'=>'INCLUIDO',
]]);
$inclTotal=end($incl['rows']);
$check((int)round((float)$inclTotal['lineTotal']*100)===1180
    && (int)round((float)$inclTotal['taxAmount']*100)===180
    && (int)round((float)$inclTotal['finalTotal']*100)===1180,
    'No añadir dos veces el IGV incluido');
$incomplete=GuideExcelLayout::build([
    ['documentNumber'=>'INCOMPLETA','materialId'=>'X','measureUnit'=>'NIU','quantity'=>2,'unitValue'=>'15.00'],
    ['documentNumber'=>'INCOMPLETA','materialId'=>'Y','measureUnit'=>'NIU','quantity'=>1,'unitValue'=>null],
]);
$incompleteTotal=end($incomplete['rows']);
$check($incompleteTotal['finalTotal']===null,
    'Los precios incompletos no producen total monetario falso');
foreach($out['rows'] as $row)
    $check(!isset($row['sunatStatus']),'El Excel no debe contener Estado SUNAT');
echo 'GUIAS EXCEL TOTAL UNICO OK · precios, IGV y sin desglose' . PHP_EOL;

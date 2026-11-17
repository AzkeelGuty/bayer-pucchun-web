<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Services/GuideExcelLayout.php';
use App\Services\GuideExcelLayout;

$rows=[
    ['documentNumber'=>'INT-GD-000000000010','documentDate'=>'2026-11-12',
     'customerId'=>'29598416','customerName'=>'ABARCA CHAUPI ANGEL MOISES',
     'sourceDocumentNumber'=>'INT-FAC-00000002','salesName'=>'Luis Alberto Ramírez Soto',
     'branchName'=>'Centro Operativo Grocio Prado','district'=>'Grocio Prado',
     'province'=>'Chincha','department'=>'Ica','materialId'=>'0000077381',
     'materialName'=>'DAGGER 50 PM X 1 KG','measureUnit'=>'KGM','quantity'=>3],
    ['documentNumber'=>'INT-GD-000000000010','documentDate'=>'2026-11-12',
     'customerId'=>'29598416','customerName'=>'ABARCA CHAUPI ANGEL MOISES',
     'materialId'=>'0000073025','materialName'=>'DAKUTEN X 1 LT',
     'measureUnit'=>'LTR','quantity'=>3],
    ['documentNumber'=>'INT-GD-000000000010','documentDate'=>'2026-11-12',
     'customerId'=>'29598416','customerName'=>'ABARCA CHAUPI ANGEL MOISES',
     'materialId'=>'0000075505','materialName'=>'FARMOZINE 500 SC X LT',
     'measureUnit'=>'NIU','quantity'=>5],
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
$check((int)$totals[0]['quantity']===11 && $totals[0]['unitCode']==='VARIAS',
    'Guía 10: 3 KGM + 3 LTR + 5 NIU deben sumar 11 como indicador numérico');
$check($totals[0]['destination']==='Desglose: 3 KGM + 3 LTR + 5 NIU',
    'Guía 10: preservar diferencias de unidades en un solo renglón');
$check($totals[0]['productName']==='TOTAL DE GUÍA · 3 producto(s)',
    'Guía 10: debe contar tres referencias diferentes');
$check((int)$totals[1]['quantity']===7 && $totals[1]['unitCode']==='LTR'
    && $totals[1]['destination']==='',
    'Guía 11: cuando hay una sola unidad, total 7 LTR sin desglose redundante');
foreach($out['rows'] as $row)
    $check(!isset($row['sunatStatus']),'El Excel no debe contener Estado SUNAT');
echo 'GUIAS EXCEL TOTAL UNICO OK · 11 cantidades y 3 productos' . PHP_EOL;

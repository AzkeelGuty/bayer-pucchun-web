<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Services\CommercialTotalsService as Totals;
use App\Repositories\{DocumentRepository,GuideRepository};

$example=[
    ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'4','valor_unitario'=>'750.00'],
    ['producto_id'=>2,'unidad_id'=>2,'cantidad'=>'5','valor_unitario'=>'125.00'],
];
$additional=Totals::summarize($example,'ADICIONAL',['1'=>'KGM','2'=>'LTR']);
ensure($additional['importe']===362500,'Suma de importes en céntimos: 4×750 + 5×125');
ensure($additional['subtotal']===362500,'Subtotal sin IGV S/ 3,625.00');
ensure($additional['igv']===65250,'IGV e IPM 18 % S/ 652.50');
ensure($additional['total']===427750,'Total S/ 4,277.50');
ensure($additional['lineas'][0]['importe']===300000,'Primera línea S/ 3,000.00');
ensure($additional['lineas'][1]['importe']===62500,'Segunda línea S/ 625.00');
ensure($additional['cantidades']['KGM']===4 && $additional['cantidades']['LTR']===5,
    'No sumar unidades heterogéneas en una cifra única');
$included=Totals::summarize($example,'INCLUIDO',['1'=>'KGM','2'=>'LTR']);
ensure($included['total']===362500,'Precio con IGV no añade impuesto doble');
ensure($included['subtotal']===307203,'Base imponible desagregada en céntimos por línea');
ensure($included['igv']===55297,'IGV incluido desagregado por línea');
ensure(Totals::money(427750)==='S/ 4,277.50','Formato de moneda profesional');

rejects(fn()=>Totals::summarize([['cantidad'=>'2','valor_unitario'=>'2.999']],'ADICIONAL'),\InvalidArgumentException::class);
rejects(fn()=>Totals::summarize([['cantidad'=>'2.5','valor_unitario'=>'10']],'ADICIONAL'),\InvalidArgumentException::class);
rejects(fn()=>Totals::summarize([['cantidad'=>'2','valor_unitario'=>null],'ADICIONAL'),\InvalidArgumentException::class);
rejects(fn()=>Totals::summarize([
    ['cantidad'=>'2','valor_unitario'=>null],
    ['cantidad'=>'2','valor_unitario'=>'3'],
],null),\InvalidArgumentException::class);
$legacy=Totals::summarize([['cantidad'=>'3.000','valor_unitario'=>'18.00']],null);
ensure($legacy['total']===5400 && $legacy['igv']===null,
    'Histórico sin modalidad: mostrar importe, no inventar IGV');
$transport=Totals::summarize([
    ['unidad_id'=>1,'cantidad'=>'7.000','valor_unitario'=>null],
    ['unidad_id'=>2,'cantidad'=>'2.000','valor_unitario'=>null],
],null,['1'=>'KGM','2'=>'LTR']);
ensure(!$transport['valorizado'] && $transport['total']===null,'Guía sin precios es exclusivamente de traslado');
ensure($transport['cantidades']['KGM']===7 && $transport['cantidades']['LTR']===2,'Guía muestra cantidades por unidad');

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/013_calculos_totales_documentos_guias.sql');
    $db->load('database/migrations/013_calculos_totales_documentos_guias.sql');
    fixtures($db->pdo);
    $docs=new DocumentRepository($db->pdo);
    $guides=new GuideRepository($db->pdo);

    $id=$docs->create([...docHeader('TEST-MONTO-01'),'precio_igv_modalidad'=>'ADICIONAL'],
        $example,1);
    $saved=$docs->find($id);
    ensure($saved['header']['precio_igv_modalidad']==='ADICIONAL','Modalidad IGV persiste en documento');
    ensure(Totals::summarize($saved['details'],$saved['header']['precio_igv_modalidad'])['total']===427750,
        'El servidor recalcula totales desde líneas de la base');
    $newVersion=$docs->updateDraft($id,1,[...docHeader('TEST-MONTO-01'),'estado_comprobante'=>'VIGENTE',
        'precio_igv_modalidad'=>'INCLUIDO'], $example,1);
    ensure($newVersion===2,'Se edita y versiona el borrador existente');
    $updated=$docs->find($id);
    ensure(Totals::summarize($updated['details'],$updated['header']['precio_igv_modalidad'])['total']===362500,
        'Al editar, se conserva la modalidad correcta e importes recalculados');

    $gid=$guides->create([...guideHeader('GUIA-VAL-01'),'precio_igv_modalidad'=>'ADICIONAL'],
        $example,1);
    $guide=$guides->find($gid);
    ensure($guide['header']['precio_igv_modalidad']==='ADICIONAL','Guía conserva modalidad comercial');
    ensure($guide['details'][0]['valor_unitario']==='750.00','Guía conserva precio unitario referencial');
    ensure(Totals::summarize($guide['details'],'ADICIONAL')['total']===427750,
        'Guía valorizada presenta idéntico importe que documento');

    $transportId=$guides->create(guideHeader('GUIA-SIN-PRECIO'),lines(),1);
    $unpriced=$guides->find($transportId);
    ensure($unpriced['details'][0]['valor_unitario']===null &&
        $unpriced['header']['precio_igv_modalidad']===null,'Guía manual de transporte sin precios ficticios');
    rejects(fn()=>$guides->create([...guideHeader('GUIA-ERR-01'),'precio_igv_modalidad'=>'ADICIONAL'],
        lines(),1),\InvalidArgumentException::class);
    rejects(fn()=>$guides->create(guideHeader('GUIA-ERR-02'),[
        ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'1','valor_unitario'=>'2.50'],
        ['producto_id'=>2,'unidad_id'=>2,'cantidad'=>'1','valor_unitario'=>null],
    ],1),\InvalidArgumentException::class);

    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=13')->fetchColumn()===1,
        'Migración 013 reejecutable sin duplicados');
    echo 'COMMERCIAL TOTALS OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

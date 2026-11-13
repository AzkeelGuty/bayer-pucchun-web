<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Exceptions\HttpException;
use App\Repositories\{DocumentRepository,GuideRepository};
use App\Services\{GuideFromDocumentService,HomologationService};

if(!function_exists('has_role')){
    function has_role(string ...$roles): bool { return in_array('ADMIN',$roles,true); }
}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/013_calculos_totales_documentos_guias.sql');
    $db->load('database/migrations/009_guias_desde_documentos_y_pagos.sql');
    $db->load('database/migrations/009_guias_desde_documentos_y_pagos.sql');
    $db->load('database/migrations/010_numeracion_interna_automatica.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $pdo->exec("UPDATE tipos_documento SET codigo='FAC' WHERE id=1");

    ensure((int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version=9")->fetchColumn()===1,'Migración 009 reejecutable sin duplicar registro');
    $documents=new DocumentRepository($pdo);
    $guides=new GuideRepository($pdo);
    $created=$documents->create([...docHeader('F001-TEST'),'medio_pago'=>'YAPE','referencia_pago'=>'OPERACION-123','precio_igv_modalidad'=>'ADICIONAL'],[
        ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'2','valor_unitario'=>'10.00'],
        ['producto_id'=>2,'unidad_id'=>2,'cantidad'=>'1','valor_unitario'=>'15.50'],
    ],1);
    $saved=$documents->find($created);
    ensure($saved['header']['medio_pago']==='YAPE','Método Yape guardado');
    ensure($saved['header']['referencia_pago']==='OPERACION-123','Referencia guardada');
    rejects(fn()=>$documents->create([...docHeader('BAD-PAYMENT'),'medio_pago'=>'DESCONOCIDO'],[
        ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'2','valor_unitario'=>'10.00'],
    ],1),InvalidArgumentException::class);

    $result=(new GuideFromDocumentService($pdo))->generate($created,1);
    ensure($result['created']===true,'La guía se crea solo en la primera solicitud');
    $guideId=$result['guide_id'];
    $guide=$guides->find($guideId);
    ensure((int)$guide['header']['documento_origen_id']===$created,'La guía conserva documento origen');
    ensure($guide['header']['estado_sunat']==='NO_EMITIDA','La guía interna no aparece como aceptada por SUNAT');
    ensure(count($guide['details'])===2,'Se copian todos los productos');
    ensure((int)$guide['details'][0]['producto_id']===1 && (int)$guide['details'][1]['producto_id']===2,'Los productos coinciden');
    ensure((string)$guide['details'][0]['cantidad']==='2.000','Se mantiene cantidad del documento');
    ensure((string)$guide['details'][0]['valor_unitario']==='10.00'
        && (string)$guide['details'][1]['valor_unitario']==='15.50',
        'Guía automática copia precios comerciales de ambas líneas');
    ensure($guide['header']['precio_igv_modalidad']==='ADICIONAL',
        'Guía vinculada conserva la interpretación del IGV');
    ensure(\App\Services\CommercialTotalsService::summarize(
        $guide['details'],$guide['header']['precio_igv_modalidad'])['total']===4189,
        'Importe comercial de la guía vinculada coincide con el documento');

    $again=(new GuideFromDocumentService($pdo))->generate($created,1);
    ensure($again['created']===false && $again['guide_id']===$guideId,'Idempotencia: no duplicar la guía');
    ensure($guides->findBySourceDocument($created)===$guideId,'Se localiza guía por origen');
    $count=(int)$pdo->query('SELECT COUNT(*) FROM guias_cabecera WHERE documento_origen_id='.$created)->fetchColumn();
    ensure($count===1,'El mismo documento nunca genera dos guías');

    $cancelled=$documents->create([...docHeader('F001-VOID'),'estado_comprobante'=>'ANULADO'],[
        ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'1','valor_unitario'=>'10.00'],
    ],1);
    rejects(fn()=>(new GuideFromDocumentService($pdo))->generate($cancelled,1),HttpException::class);

    $hom=new HomologationService($pdo);
    ensure(count(HomologationService::definitions())===6,'Se habilitan seis catálogos homologados');
    $product=$hom->save('productos',[
        'partner_id'=>1,'local_id'=>1,'codigo_bayer'=>'BY-001','nombre_bayer'=>'Material Bayer',
        'unidad_bayer_id'=>1,'estado'=>'1','valid_from'=>'2026-11-01','valid_until'=>'2027-11-01',
    ],1);
    ensure($product>0,'Alta manual de homologación');
    $hom->save('productos',[
        'partner_id'=>1,'local_id'=>1,'codigo_bayer'=>'BY-001-A','nombre_bayer'=>'Material actualizado',
        'unidad_bayer_id'=>1,'estado'=>'1','valid_from'=>'','valid_until'=>'',
    ],1,$product);
    ensure($hom->find('productos',$product)['codigo_bayer']==='BY-001-A','Edición manual');
    $hom->deactivate('productos',$product,1);
    ensure((int)$hom->find('productos',$product)['estado']===0,'Desactivación sin borrar histórico');
    rejects(fn()=>$hom->save('productos',[
        'partner_id'=>1,'local_id'=>2,'codigo_bayer'=>'','nombre_bayer'=>'Incorrecto','estado'=>'1',
    ],1),HttpException::class);

    $template=$hom->definition('clientes')['import'];
    $row=[
        'partner_codigo'=>'BAYER','codigo_interno'=>'00000001',
        'codigo_bayer'=>'CUS-101','nombre_bayer'=>'Cliente homologado','estado'=>'1',
        'valid_from'=>'','valid_until'=>'',
    ];
    ensure(array_keys($row)===array_keys($template),'La estructura de Excel coincide con el contrato');
    $first=$hom->importRows('clientes',[$row],1);
    ensure($first['inserted']===1,'Importación alta');
    $second=$hom->importRows('clientes',[[...$row,'nombre_bayer'=>'Cliente actualizado']],1);
    ensure($second['updated']===1,'Importación actualiza en lugar de duplicar');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM homologacion_clientes')->fetchColumn()===1,'No hay homologaciones duplicadas');
    rejects(fn()=>$hom->importRows('clientes',[
        [...$row,'codigo_bayer'=>'CUS-102'],
        [...$row,'codigo_interno'=>'INEXISTENTE'],
    ],1),HttpException::class);
    ensure((string)$pdo->query('SELECT customer_id FROM homologacion_clientes LIMIT 1')->fetchColumn()==='CUS-101','Excel fallido revierte todos los cambios');
    echo "COMMERCE WORKFLOWS OK ({$GLOBALS['checks']} checks)\n";
} finally {
    $db->close();
}

<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Helpers/functions.php';

use App\Services\{BayerSpanishService,BayerFiscalReferenceService,MasterCatalogService};
use App\Exceptions\HttpException;

$db=new TestDatabase();
try{
    foreach(['database/schemas/002_schema_v2.sql','database/migrations/009_guias_desde_documentos_y_pagos.sql','database/migrations/010_numeracion_interna_automatica.sql','database/migrations/012_sunat_emision_cpe.sql','database/migrations/013_calculos_totales_documentos_guias.sql','database/migrations/019_integracion_bayer_espanol.sql','database/migrations/019_integracion_bayer_espanol.sql','database/migrations/020_numeracion_automatica_formato_sunat.sql','database/migrations/020_numeracion_automatica_formato_sunat.sql'] as $file)$db->load($file);
    $pdo=$db->pdo;fixtures($pdo);
    $pdo->exec("UPDATE empresas SET ruc='20123456789',razon_social='Distribuidor de prueba' WHERE id=1");
    $pdo->exec("UPDATE clientes SET tipo_doc='01',departamento_id=2,provincia_id=2,distrito_id=2 WHERE id=1");
    $master=new MasterCatalogService($pdo);
    $master->save('vendedores',['codigo'=>'V1','dni'=>'01234567','nombres'=>'Nombre','apellidos'=>'Apellido','estado'=>'1'],1);
    rejects(fn()=>$master->save('vendedores',['codigo'=>'V1','dni'=>'123','nombres'=>'Nombre','estado'=>'1'],1),HttpException::class);
    $master->importRows('vendedores',[['codigo'=>'V1','nombres'=>'Nombre','apellidos'=>'Apellido','estado'=>'1']]);
    ensure($master->find('vendedores',1)['dni']==='01234567','DNI se conserva al importar formato anterior');
    $master->save('almacenes',['sucursal_id'=>'1','codigo'=>'A1','nombre'=>'Almacén principal','tipo'=>'GENERAL','estado'=>'1','distrito_id'=>'2'],1);
    $pdo->exec("INSERT INTO documentos_cabecera(id,tipo_documento_id,numero,fecha,cliente_id,vendedor_id,sucursal_id,estado_registro,estado_comprobante,medio_pago) VALUES
        (1,1,'INT-FAC-00000001','2026-11-12',1,1,1,'PUBLICADO','VIGENTE','EFECTIVO'),
        (2,1,'INT-FAC-00000002','2026-11-12',1,1,1,'PUBLICADO','ANULADO','EFECTIVO'),
        (3,1,'INT-FAC-00000003','2026-11-12',1,1,1,'PUBLICADO','VIGENTE','EFECTIVO'),
        (4,1,'INT-FAC-00000004','2026-11-12',1,1,1,'BORRADOR','VIGENTE','EFECTIVO')");
    $pdo->exec("INSERT INTO documentos_detalle(documento_id,producto_id,unidad_id,cantidad,valor_unitario) VALUES(1,1,1,3,20),(2,1,1,2,10),(3,1,1,1,10),(4,1,1,1,10)");
    $pdo->exec("INSERT INTO sunat_emisiones(documento_id,usuario_id,ruc_emisor,tipo_cpe,serie,correlativo,ambiente,estado) VALUES
        (1,1,'20123456789','01','F001',101,'PRODUCCION','ACEPTADO'),
        (3,1,'20123456789','01','F001',102,'BETA','ACEPTADO')");
    $pdo->exec("INSERT INTO guias_cabecera(id,numero,fecha,cliente_id,vendedor_id,sucursal_id,departamento_id,provincia_id,distrito_id,estado_registro,documento_origen_id) VALUES
        (1,'INT-GD-00000001','2026-11-12',1,1,1,2,2,2,'PUBLICADO',1),
        (2,'INT-GD-00000002','2026-11-12',1,1,1,2,2,2,'PUBLICADO',3)");
    $pdo->exec("INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES(1,1,1,3),(2,1,1,1)");
    $pdo->exec("INSERT INTO stock_cabecera(id,fecha_stock,almacen_id,estado_registro,idempotency_key,request_hash) VALUES(1,'2026-11-12',1,'PUBLICADO','test-espanol',REPEAT('a',64))");
    $pdo->exec("INSERT INTO stock_detalle(stock_id,fecha_stock,almacen_id,producto_id,unidad_id,cantidad) VALUES(1,'2026-11-12',1,1,1,9)");
    $refs=new BayerFiscalReferenceService($pdo);
    rejects(fn()=>$refs->save('documentos',2,'INT-FAC-00000002',null,true,1),HttpException::class);
    rejects(fn()=>$refs->save('documentos',2,'F001-101',null,true,1),HttpException::class);
    rejects(fn()=>$refs->save('documentos',2,'F001-105',null,false,1),HttpException::class);
    $refs->save('documentos',2,'F001-105',null,true,1);
    rejects(fn()=>$refs->save('documentos',3,'F001-105',null,true,1),HttpException::class);
    rejects(fn()=>$refs->save('documentos',4,'F001-106',null,true,1),HttpException::class);
    $refs->save('guias',1,'T001-205',2,true,1);
    $api=new BayerSpanishService($pdo);
    $sales=$api->rows('facturas');$guides=$api->rows('guias');$stock=$api->rows('stock');
    foreach(['facturas','guias','stock'] as $dataset){
        ensure($api->countRows($dataset)===count($api->rows($dataset)),'Contador panel v2 coincide con filas: '.$dataset);
        ensure($api->countRows($dataset,['from'=>'2026-11-13'])===count($api->rows($dataset,['from'=>'2026-11-13'])),'Contador con filtros coincide: '.$dataset);
    }
    ensure(count($sales)===2&&count($guides)===1&&count($stock)===1,'Solo publicados con referencia fiscal en ventas y guías');
    $sale=$api->rows('facturas',['document_status'=>'VIGENTE'])[0];
    ensure(count($sale)===24 && count($guides[0])===21 && count($stock[0])===11,'24, 21 y 11 columnas');
    foreach(['facturas','guias','stock'] as $dataset){
        $all=$api->rows($dataset);
        ensure(array_keys($all[0])===BayerSpanishService::fields($dataset),'Encabezados exactos del portal y exportación: '.$dataset);
        ensure($api->rows($dataset,[],1,0)===array_slice($all,0,1),'Primera página v2: '.$dataset);
        ensure($api->rows($dataset,[],1,1)===array_slice($all,1,1),'Segunda página v2: '.$dataset);
    }
    ensure($sale['numero_documento']==='F001-00000101' && $sale['estado_documento']===1,'Usar correlativo aceptado y estado numérico');
    ensure($api->rows('facturas',['document_status'=>'ANULADO'])[0]['estado_documento']===0,'Estado anulado 0');
    ensure($sale['dni_vendedor']==='01234567' && $sale['ruc_distribuidor']==='20123456789','Identificadores como texto');
    ensure($sale['distrito']==='X1' && $sale['provincia']==='P1','Despacho de sucursal, no domicilio cliente');
    ensure($sale['cantidad']===3 && $sale['precio_unitario']===20.0 && $sale['importe_linea']===60.0,'Importe por línea');
    ensure($guides[0]['numero_documento']==='T001-00000205'&&$guides[0]['numero_documento_origen']==='F001-00000101','Vínculo fiscal sin referencias INT');
    ensure($guides[0]['distrito']==='X2'&&$guides[0]['tipo_documento_cliente']==='DNI','Despacho explícito y DNI');
    ensure($guides[0]['estado_sunat']==='EMISION_EXTERNA_DECLARADA','La declaración externa no simula un CDR');
    ensure(!isset($stock[0]['codigo_sucursal'])&&str_contains($stock[0]['nombre_almacen'],'D2-P2-X2')&&$stock[0]['lote']===null,'Almacén geográfico y lote opcional');
    ensure($api->rows('facturas',['from'=>'2026-11-13'])===[],'Filtro por fecha');
    ensure($pdo->query('SELECT numero FROM documentos_cabecera WHERE id=1')->fetchColumn()==='INT-FAC-00000001','No renumerar la base');
    $docs=new \App\Repositories\DocumentRepository($pdo);
    $draftHeader=['tipo_documento_id'=>1,'numero'=>'','fecha'=>'2026-11-26','cliente_id'=>1,'vendedor_id'=>1,'sucursal_id'=>1,'estado_comprobante'=>'VIGENTE','medio_pago'=>'EFECTIVO','precio_igv_modalidad'=>'ADICIONAL'];
    $draftLines=[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>1,'valor_unitario'=>10]];
    $draftId=$docs->createWithFiscalReference($draftHeader,$draftLines,1,'F001-999',true);
    ensure($refs->displayNumbers('documentos',[$draftId])[$draftId]==='F001-00000999','Número fiscal desde captura');
    ensure(count($api->rows('facturas'))===2,'Un borrador con referencia real no se publica automáticamente');
    $before=(int)$pdo->query('SELECT COUNT(*) FROM documentos_cabecera')->fetchColumn();
    rejects(fn()=>$docs->createWithFiscalReference($draftHeader,$draftLines,1,'F001-999',true),HttpException::class);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM documentos_cabecera')->fetchColumn()===$before,'Referencia duplicada revierte la creación completa');
    $guideRepo=new \App\Repositories\GuideRepository($pdo);
    $guideHeader=['numero'=>'','fecha'=>'2026-11-26','cliente_id'=>1,'vendedor_id'=>1,'sucursal_id'=>1,'departamento_id'=>null,'provincia_id'=>null,'distrito_id'=>null,'precio_igv_modalidad'=>null];
    $guideId=$guideRepo->createWithFiscalReference($guideHeader,[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>1,'valor_unitario'=>null]],1,'T001-999',true);
    ensure($refs->displayNumbers('guias',[$guideId])[$guideId]==='T001-00000999','Número fiscal desde nueva guía');
    ensure(count($api->rows('guias'))===1,'Guía en borrador no se publica automáticamente');
    ensure($refs->displayNumbers('documentos',[1,2,3])[1]==='F001-00000101','Mostrar número de producción');
    ensure($refs->displayNumbers('documentos',[3])[3]===null,'No mostrar el correlativo beta');
    ensure($refs->displayNumbers('guias',[1])[1]==='T001-00000205','Mostrar número fiscal de guía');
    ensure(!str_contains(BayerFiscalReferenceService::displayLabel(null,3),'INT-'),'No mostrar identificador interno como comprobante');
    $_ENV['SUNAT_SERIE_FACTURA']='F001';
    $_ENV['SUNAT_CORRELATIVO_INICIAL']='1';
    $_ENV['SUNAT_SERIE_GUIA']='T001';
    $_ENV['SUNAT_CORRELATIVO_GUIA']='1';
    $automatic=new \App\Services\AutomaticFiscalNumberService($pdo);
    $beforeCounter=$pdo->query("SELECT siguiente FROM sunat_series_fiscales WHERE ruc_emisor='20123456789' AND tipo_cpe='01' AND serie='F001' AND ambiente='PRODUCCION'")->fetchColumn();
    ensure($automatic->preview('documentos',1,0)==='F001-00001000','Vista previa al abrir con un solo emisor');
    ensure($automatic->preview('documentos',1,1)==='F001-00001000','Vista previa por sucursal');
    ensure($automatic->preview('guias',0,1)==='T001-00001000','Vista previa de guía');
    ensure($pdo->query("SELECT siguiente FROM sunat_series_fiscales WHERE ruc_emisor='20123456789' AND tipo_cpe='01' AND serie='F001' AND ambiente='PRODUCCION'")->fetchColumn()===$beforeCounter,'Vista previa no consume correlativos');
    $a=$docs->createAutoNumbered($draftHeader,$draftLines,1);
    $b=$docs->createAutoNumbered($draftHeader,$draftLines,1);
    ensure($automatic->labels('documentos',[$a])[$a]==='F001-00001000 · Pendiente de emisión','El contador continúa después de la referencia fiscal existente');
    ensure($automatic->labels('documentos',[$b])[$b]==='F001-00001001 · Pendiente de emisión','Correlativos automáticos únicos');
    ensure($refs->displayNumbers('documentos',[$a])[$a]===null,'Reserva no equivale a comprobante emitido');
    rejects(fn()=>$refs->save('documentos',$b,'F001-1000',null,true,1,true),HttpException::class);
    $g=$guideRepo->createAutoNumbered($guideHeader,[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>1,'valor_unitario'=>null]],1);
    ensure($automatic->labels('guias',[$g])[$g]==='T001-00001000 · Pendiente de emisión','Serie independiente para guías');
    ensure(count($api->rows('facturas'))===2&&count($api->rows('guias'))===1,'Reservas sin emitir no alteran datos fiscales entregados a Bayer');
    $docs->deleteDraft($a,1,1);
    $c=$docs->createAutoNumbered($draftHeader,$draftLines,1);
    ensure($automatic->labels('documentos',[$c])[$c]==='F001-00001002 · Pendiente de emisión','No reutilizar un número tras eliminar borrador');
    echo "BAYER API ESPAÑOL Y NUMERACION AUTOMATICA OK\n";
}finally{$db->close();}

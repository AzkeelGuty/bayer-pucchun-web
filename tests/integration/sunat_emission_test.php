<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require dirname(__DIR__,2).'/app/Helpers/functions.php';

use App\Repositories\DocumentRepository;
use App\Services\{SunatInvoiceBuilder,SunatEmissionService,SunatReadinessService};
use App\Exceptions\HttpException;

$oldRuc=getenv('SUNAT_RUC');
putenv('SUNAT_RUC=00000000001');
$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/009_guias_desde_documentos_y_pagos.sql');
    $db->load('database/migrations/010_numeracion_interna_automatica.sql');
    $db->load('database/migrations/012_sunat_emision_cpe.sql');
    $db->load('database/migrations/012_sunat_emision_cpe.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $pdo->exec("UPDATE tipos_documento SET codigo='FAC' WHERE id=1");
    $pdo->exec("UPDATE clientes SET nro_doc='20123456789' WHERE id=1");
    $repo=new DocumentRepository($pdo);
    $id=$repo->create(docHeader('INT-FAC-00000001'),[
        ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'2','valor_unitario'=>'10.00'],
        ['producto_id'=>2,'unidad_id'=>2,'cantidad'=>'1','valor_unitario'=>'20.00'],
    ],1);
    $repo->markValidated($id,1,1);
    $repo->markPublished($id,2,1);
    $builder=new SunatInvoiceBuilder($pdo);
    $doc=$builder->snapshot($id);
    ensure($doc['numero']==='INT-FAC-00000001','Factura publicada se obtiene sin alterar número interno');
    ensure($doc['cliente_documento']==='20123456789','Cliente con RUC real de la prueba');
    ensure($doc['ruc_emisor']==='00000000001','RUC de la empresa vinculada a sucursal');
    ensure(count($doc['lineas'])===2,'Se incluyen todos los productos');

    $totals=SunatInvoiceBuilder::totals($doc['lineas'],'PRECIO_SIN_IGV');
    ensure($totals['gravada']===40.0,'Valor neto correcto');
    ensure($totals['igv']===7.2,'IGV gravado de 18 %');
    ensure($totals['total']===47.2,'Importe final');
    ensure($totals['lineas'][0]['unidad']==='KGM','Unidad KG convertida a KGM');
    ensure($totals['lineas'][1]['unidad']==='LTR','Unidad LT convertida a LTR');
    $gross=SunatInvoiceBuilder::totals([
        ['cantidad'=>'2','valor_unitario'=>'11.80','unidad_codigo'=>'KG','producto_codigo'=>'M1','producto_nombre'=>'Producto'],
    ],'PRECIO_INCLUYE_IGV');
    ensure($gross['gravada']===20.0 && $gross['igv']===3.6 && $gross['total']===23.6,
        'Precio que ya incluye IGV se desagrega sin duplicar impuesto');

    rejects(fn()=>SunatInvoiceBuilder::totals($doc['lineas'],'MODO_NO_ADMITIDO'),HttpException::class);
    rejects(fn()=>SunatInvoiceBuilder::totals([
        ['cantidad'=>1,'valor_unitario'=>10,'unidad_codigo'=>'NOEXISTE']
    ],'PRECIO_SIN_IGV'),HttpException::class);
    rejects(fn()=>SunatInvoiceBuilder::totals([
        ['cantidad'=>0,'valor_unitario'=>10,'unidad_codigo'=>'KG']
    ],'PRECIO_SIN_IGV'),HttpException::class);
    ensure(SunatInvoiceBuilder::documentName('00000000001','F001',10)==='00000000001-01-F001-10',
        'Número tributario diferenciado de INT-FAC');
    rejects(fn()=>SunatInvoiceBuilder::documentName('00000000001','INT1',1),HttpException::class);

    $other=$repo->create([...docHeader('BORRADOR')],[
        ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'1','valor_unitario'=>'10.00']
    ],1);
    rejects(fn()=>$builder->snapshot($other),HttpException::class);
    $pdo->exec("UPDATE clientes SET nro_doc='12345678' WHERE id=1");
    rejects(fn()=>$builder->snapshot($id),HttpException::class);
    $pdo->exec("UPDATE clientes SET nro_doc='20123456789' WHERE id=1");

    $service=new SunatEmissionService($pdo);
    ensure($service->findForDocument($id)===null,'Sin intento registrado inicialmente');
    ensure(!(new SunatReadinessService())->isReady(),'No se emite sin credenciales y certificado real');
    rejects(fn()=>$service->emitInvoice($id,1,'PRECIO_SIN_IGV',true),HttpException::class);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM sunat_emisiones')->fetchColumn()===0,
        'Sin configuración real NO se crea emisión ni número fiscal');

    $pdo->exec("INSERT INTO sunat_series_fiscales(ruc_emisor,tipo_cpe,serie,ambiente,siguiente)
        VALUES('00000000001','01','F001','BETA',1),
              ('00000000001','01','F001','PRODUCCION',1)");
    ensure((int)$pdo->query('SELECT COUNT(*) FROM sunat_series_fiscales')->fetchColumn()===2,
        'Contadores BETA y PRODUCCION independientes');
    $pdo->exec("INSERT INTO sunat_emisiones(documento_id,usuario_id,ruc_emisor,tipo_cpe,serie,correlativo,ambiente)
        VALUES($id,1,'00000000001','01','F001',1,'BETA')");
    $saved=$service->findForDocument($id,'BETA');
    ensure($saved['estado']==='EN_PROCESO','Una emisión no se considera aceptada sin CDR');
    $pdo->exec("INSERT INTO sunat_emisiones(documento_id,usuario_id,ruc_emisor,tipo_cpe,serie,correlativo,ambiente)
        VALUES($id,1,'00000000001','01','F001',1,'PRODUCCION')");
    ensure($service->findForDocument($id,'PRODUCCION')['ambiente']==='PRODUCCION',
        'Una factura ensayada en BETA se puede emitir por separado en PRODUCCION');
    rejects(fn()=>$pdo->exec("INSERT INTO sunat_emisiones(documento_id,usuario_id,ruc_emisor,tipo_cpe,serie,correlativo,ambiente)
        VALUES($id,1,'00000000001','01','F001',2,'BETA')"),PDOException::class);
    rejects(fn()=>$service->evidenceFile((int)$saved['id'],'xml'),HttpException::class);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=12')->fetchColumn()===1,
        'Migración 012 es idempotente');
    echo 'SUNAT EMISSION DATA OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
} finally {
    if($oldRuc===false)putenv('SUNAT_RUC');else putenv('SUNAT_RUC='.$oldRuc);
    $db->close();
}

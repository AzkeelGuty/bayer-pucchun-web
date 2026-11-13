<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Repositories\{DocumentRepository,GuideRepository};
use App\Services\InternalNumberingService;

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/013_calculos_totales_documentos_guias.sql');
    $db->load('database/migrations/009_guias_desde_documentos_y_pagos.sql');
    $db->load('database/migrations/010_numeracion_interna_automatica.sql');
    $db->load('database/migrations/010_numeracion_interna_automatica.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;

    $pdo->exec("UPDATE tipos_documento SET codigo='FAC',nombre='Factura' WHERE id=1");
    $pdo->exec("INSERT INTO tipos_documento(codigo,nombre) VALUES
        ('BOL','Boleta'),('NCR','Nota de crédito'),('NDB','Nota de débito')");
    $types=[];
    foreach($pdo->query("SELECT id,codigo FROM tipos_documento") as $row) $types[$row['codigo']]=(int)$row['id'];

    $numbers=new InternalNumberingService($pdo);
    $documents=new DocumentRepository($pdo);
    $guides=new GuideRepository($pdo);
    $lines=[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'2','valor_unitario'=>'10.00']];
    $guideLines=[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'2']];

    ensure($numbers->previewDocument($types['FAC'])==='INT-FAC-00000001','Vista previa inicial de factura');
    ensure($numbers->previewDocument($types['BOL'])==='INT-BOL-00000001','Vista previa inicial de boleta');
    ensure($numbers->previewDocument($types['NCR'])==='INT-NCR-00000001','Vista previa inicial de nota de crédito');
    ensure($numbers->previewDocument($types['NDB'])==='INT-NDB-00000001','Vista previa inicial de nota de débito');

    // Conserva numeración histórica y no la reemplaza en documentos existentes.
    $legacy=$documents->create([...docHeader('INT-FAC-00000042'),'tipo_documento_id'=>$types['FAC']],$lines,1);
    ensure($numbers->previewDocument($types['FAC'])==='INT-FAC-00000043','Respeta el mayor correlativo ya utilizado');

    $fac=$documents->createAutoNumbered([...docHeader('FALSIFICADO'),'tipo_documento_id'=>$types['FAC']],$lines,1);
    $facNumber=$documents->find($fac)['header']['numero'];
    ensure($facNumber==='INT-FAC-00000043','Factura autogenerada e independiente del número enviado');
    ensure($documents->find($legacy)['header']['numero']==='INT-FAC-00000042','No modifica la numeración histórica');

    $bol=$documents->createAutoNumbered([...docHeader('MANUAL'),'tipo_documento_id'=>$types['BOL']],$lines,1);
    ensure($documents->find($bol)['header']['numero']==='INT-BOL-00000001','Boleta con contador independiente');

    $credit=$documents->createAutoNumbered([...docHeader('MANUAL'),'tipo_documento_id'=>$types['NCR']],$lines,1);
    ensure($documents->find($credit)['header']['numero']==='INT-NCR-00000001','Nota de crédito con contador independiente');
    $debit=$documents->createAutoNumbered([...docHeader('MANUAL'),'tipo_documento_id'=>$types['NDB']],$lines,1);
    ensure($documents->find($debit)['header']['numero']==='INT-NDB-00000001','Nota de débito con contador independiente');

    $manualGuide=$guides->create(guideHeader('INT-GD-000000000007'),$guideLines,1);
    ensure($manualGuide>0,'Existe guía interna histórica');
    ensure($numbers->previewGuide()==='INT-GD-000000000008','Vista previa salta guías anteriores');

    $guide=$guides->createAutoNumbered(guideHeader('CAMBIAR'),$guideLines,1);
    ensure($guides->find($guide)['header']['numero']==='INT-GD-000000000008','Guía automática con contador independiente');

    // El correlativo y el INSERT deben deshacerse juntos si falla cualquier detalle.
    $expected=$numbers->previewDocument($types['FAC']);
    rejects(fn()=>$documents->createAutoNumbered(
        [...docHeader('IGNORADO'),'tipo_documento_id'=>$types['FAC']],
        [['producto_id'=>99999999,'unidad_id'=>1,'cantidad'=>'1','valor_unitario'=>'1.00']],
        1
    ),PDOException::class);
    ensure($numbers->previewDocument($types['FAC'])===$expected,'No consume correlativo por transacción fallida');

    // Otra conexión comprueba la persistencia visible para solicitudes diferentes.
    $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $second=new PDO(
        'mysql:host='.(getenv('TEST_DB_HOST')?:'127.0.0.1').
        ';port='.(getenv('TEST_DB_PORT')?:'3306').
        ';dbname='.$database.';charset=utf8mb4',
        getenv('TEST_DB_USER')?:'root',
        getenv('TEST_DB_PASS')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    $otherDocuments=new DocumentRepository($second);
    $next=$otherDocuments->createAutoNumbered([...docHeader('MANUAL'),'tipo_documento_id'=>$types['FAC']],$lines,1);
    ensure($otherDocuments->find($next)['header']['numero']===$expected,'Segunda conexión usa siguiente número único');
    ensure($next!==$fac,'Registros diferentes no reciben mismo identificador');

    ensure((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=10')->fetchColumn()===1,'Migración 010 reejecutable');
    echo 'INTERNAL NUMBERING OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{
    $db->close();
}

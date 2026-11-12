<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Repositories\{DocumentRepository,GuideRepository,StockRepository};
use App\Services\{PermissionService,WorkflowService};

function db(): PDO
{
    return $GLOBALS['workflowValidationDb'];
}
function log_event(string $event,array $context=[]): void
{
    $GLOBALS['workflowValidationEvents'][]=[$event,$context];
}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/009_guias_desde_documentos_y_pagos.sql');
    $db->load('database/migrations/010_numeracion_interna_automatica.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $GLOBALS['workflowValidationDb']=$pdo;

    foreach(['ADMIN','SUPERVISOR','DIGITADOR','GERENCIA','BAYER'] as $name){
        $st=$pdo->prepare('INSERT INTO roles(nombre,descripcion) VALUES (?,?)');
        $st->execute([$name,$name]);
    }
    $db->load('database/migrations/003_operational_permissions.sql');
    $db->load('database/migrations/011_permisos_flujo_aprobacion.sql');
    $db->load('database/migrations/011_permisos_flujo_aprobacion.sql');

    $pdo->exec("INSERT INTO usuario_rol(usuario_id,rol_id) SELECT 1,id FROM roles WHERE nombre='ADMIN'");
    $permissions=new PermissionService($pdo);
    $user=['id'=>1,'roles'=>['ADMIN']];
    ensure($permissions->allows($user,'validation.review'),'Administrador puede validar borradores');
    ensure($permissions->allows($user,'publications.publish'),'Administrador puede publicar');
    $pdo->exec("INSERT INTO usuario_rol(usuario_id,rol_id) SELECT 2,id FROM roles WHERE nombre='SUPERVISOR'");
    $supervisor=['id'=>2,'roles'=>['SUPERVISOR']];
    ensure($permissions->allows($supervisor,'validation.review'),'Supervisor puede validar');
    ensure(!$permissions->allows($supervisor,'publications.publish'),'Supervisor no publica sin concesión adicional');
    foreach(['DIGITADOR','GERENCIA','BAYER'] as $role){
        $st=$pdo->prepare("SELECT COUNT(*) FROM rol_permiso rp
           JOIN roles r ON r.id=rp.rol_id JOIN permisos p ON p.id=rp.permiso_id
           WHERE r.nombre=? AND p.codigo IN ('validation.review','publications.publish')");
        $st->execute([$role]);
        ensure((int)$st->fetchColumn()===0,'No se asignaron permisos de aprobación a '.$role);
    }
    ensure((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=11')->fetchColumn()===1,'Migración 011 idempotente');

    $documents=new DocumentRepository($pdo);
    $guides=new GuideRepository($pdo);
    $stock=new StockRepository($pdo);
    $workflow=new WorkflowService();
    $docLines=[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'1','valor_unitario'=>'15.00']];
    $guideLines=[['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'1']];
    $stockLines=[['producto_id'=>1,'unidad_id'=>1,'lote_id'=>1,'cantidad'=>'1']];

    $doc1=$documents->create(docHeader('PRUEBA-VAL-1'),$docLines,1);
    $doc2=$documents->create(docHeader('PRUEBA-VAL-2'),$docLines,1);
    $guide1=$guides->create(guideHeader('PRUEBA-GUIA-1'),$guideLines,1);
    $stock1=$stock->create(stockHeader('PRUEBA-STOCK-1'),$stockLines,1);

    ensure($documents->countByState('BORRADOR')===2,'Documentos inicialmente borradores');
    ensure($workflow->transition($documents,$doc1,1,'VALIDADO',1)===2,'Botón Validar documento cambia estado');
    ensure($documents->find($doc1)['header']['estado_registro']==='VALIDADO','Documento validado persistido');
    $batch=$workflow->bulkTransition($documents,'VALIDADO',1);
    ensure($batch['success']===1 && $batch['failed']===0,'Validar todo procesa documentos pendientes');
    ensure($documents->countByState('BORRADOR')===0,'Validar todo no deja borradores');
    ensure($documents->countByState('VALIDADO')===2,'Validar todo conserva validados anteriores');

    $publish=$workflow->bulkTransition($documents,'PUBLICADO',1);
    ensure($publish['success']===2,'Publicar todo procesa validados');
    ensure($documents->countByState('PUBLICADO')===2,'Documentos publicados');
    $guideBulk=$workflow->bulkTransition($guides,'VALIDADO',1);
    ensure($guideBulk['success']===1 && $guides->countByState('VALIDADO')===1,'Validar todo guías');
    $stockBulk=$workflow->bulkTransition($stock,'VALIDADO',1);
    ensure($stockBulk['success']===1 && $stock->countByState('VALIDADO')===1,'Validar todo stock');
    ensure($workflow->transition($guides,$guide1,2,'PUBLICADO',1)===3,'Botón Publicar guía');
    ensure($workflow->transition($stock,$stock1,2,'PUBLICADO',1)===3,'Botón Publicar stock');

    $st=$pdo->query("SELECT COUNT(*) FROM validaciones WHERE resultado='VALIDADO'");
    ensure((int)$st->fetchColumn()===4,'Se registran auditorías de validación');
    $st=$pdo->query("SELECT COUNT(*) FROM detalle_publicacion");
    ensure((int)$st->fetchColumn()===4,'Se registran detalles de publicación');

    echo 'VALIDATION ACTIONS OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{
    unset($GLOBALS['workflowValidationDb'],$GLOBALS['workflowValidationEvents']);
    $db->close();
}

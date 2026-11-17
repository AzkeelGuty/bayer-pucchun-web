<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Services\{AuditService,AuthService};

$db=new TestDatabase();
$server=$_SERVER;
try {
    $db->load('database/schemas/002_schema_v2.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $audit=new AuditService($pdo);

    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR']='198.51.100.9';
    $_SERVER['HTTP_CF_CONNECTING_IP']='198.51.100.10';
    ensure(AuthService::ip()==='127.0.0.1','Nunca se aceptan cabeceras de IP no confiables');
    $audit->record(1,'documentos','crear',7,AuthService::ip(),'Mozilla/5.0 Test');
    $item=$pdo->query("SELECT * FROM auditoria_acciones WHERE accion='crear' ORDER BY id DESC LIMIT 1")->fetch();
    ensure($item['ip']==='127.0.0.1','Se conserva dirección IP de la conexión');
    ensure($item['user_agent']==='Mozilla/5.0 Test','Se conserva el navegador');
    ensure($item['resultado']==='COMPLETADO','Se guarda el resultado de una acción exitosa');
    ensure((int)$item['entidad_id']===7 && $item['modulo']==='documentos','Se guarda relación a entidad y módulo');

    $_SERVER['REMOTE_ADDR']='::1';
    ensure(AuthService::ip()==='::1','Se mantiene la dirección IPv6 loopback exacta');
    $audit->record(1,'guias','validar',5,AuthService::ip(),"Chrome\r\nInyectado");
    $second=$pdo->query("SELECT * FROM auditoria_acciones WHERE accion='validar' ORDER BY id DESC LIMIT 1")->fetch();
    ensure($second['ip']==='::1','IP IPv6 queda registrada sin convertirla ficticiamente');
    ensure(!str_contains($second['user_agent'],"\n"),'Agente de usuario sanitizado');

    $_SERVER['REMOTE_ADDR']='no-es-ip';
    ensure(AuthService::ip()===null,'IP inválida no se transforma en una dirección inventada');
    $audit->record(1,'stock','guardar',null,AuthService::ip(),null);
    $third=$pdo->query("SELECT * FROM auditoria_acciones WHERE accion='guardar' ORDER BY id DESC LIMIT 1")->fetch();
    ensure($third['ip']===null,'No se inventa IP en conexión desconocida');

    // Históricos sin campo ip mantienen valor NULL, no pueden reconstruirse.
    $pdo->exec("INSERT INTO auditoria_acciones(usuario_id,modulo,accion,fecha_hora)
                 VALUES(1,'documentos','historico',NOW())");
    $legacy=$pdo->query("SELECT ip FROM auditoria_acciones WHERE accion='historico'")->fetch();
    ensure($legacy['ip']===null,'Los registros antiguos permanecen sin IP');
    $helpers=file_get_contents(dirname(__DIR__,2).'/app/Helpers/functions.php');
    ensure(str_contains($helpers,'AuditService(db())') &&
        str_contains($helpers,'AuthService::ip()'),'La función audit principal utiliza el servicio y la IP de servidor');

    echo 'AUDIT IP OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
} finally {
    $_SERVER=$server;
    $db->close();
}

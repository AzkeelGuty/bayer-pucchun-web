<?php
declare(strict_types=1);

/**
 * Regresión HTTP 500: /homologaciones debe funcionar sin ?q= y con
 * parámetros de búsqueda mal formados. Ejecuta sobre BD de pruebas aislada.
 */
require __DIR__.'/bootstrap.php';

use App\Controllers\BackofficeController;

function db(): PDO
{
    return $GLOBALS['homologationsTestPdo'];
}

function require_role(string ...$roles): void
{
    // El control de permisos se comprueba en sus pruebas específicas.
    ensure(in_array('ADMIN',$roles,true),'Solo ADMIN gestiona las homologaciones');
}

function has_role(string ...$roles): bool
{
    return in_array('ADMIN',$roles,true);
}

function view(string $name,array $data=[]): void
{
    $GLOBALS['homologationsRendered']=['name'=>$name,'data'=>$data];
}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/013_calculos_totales_documentos_guias.sql');
    $db->load('database/migrations/004_catalogos_masivos_busqueda.sql');
    fixtures($db->pdo);
    $GLOBALS['homologationsTestPdo']=$db->pdo;
    $db->pdo->exec("INSERT INTO homologacion_productos_bayer(partner_id,producto_id,material_id,material_name) VALUES (1,1,'BAY-001','Primer producto'),(1,2,'BAY-002','Segundo producto')");

    $controller=new BackofficeController();

    $_GET=[];
    $controller->homologations();
    $result=$GLOBALS['homologationsRendered'];
    ensure($result['name']==='backoffice.table','La ruta usa el listado estándar');
    ensure($result['data']['q']==='','Sin ?q= se aplica búsqueda vacía');
    ensure($result['data']['pagination']['total']===2,'La lista sin filtros incluye registros');

    $_GET=['tab'=>'productos','q'=>'BAY-001'];
    $controller->homologations();
    $result=$GLOBALS['homologationsRendered'];
    ensure($result['data']['pagination']['total']===1,'Búsqueda por código Bayer');
    ensure($result['data']['rows'][0]['codigo_bayer']==='BAY-001','Resultado correcto');

    $_GET=['tab'=>'productos','q'=>['valor'=>'inesperado']];
    $controller->homologations();
    ensure($GLOBALS['homologationsRendered']['data']['q']==='','Un parámetro q array no causa 500');

    $_GET=['tab'=>['productos'],'q'=>''];
    $controller->homologations();
    ensure($GLOBALS['homologationsRendered']['data']['active']==='productos','Tab malformado vuelve a valor seguro');

    $_GET=['tab'=>'clientes','q'=>['valor'=>'invalido']];
    $controller->masters();
    ensure($GLOBALS['homologationsRendered']['data']['q']==='','Catálogos maestros manejan q malformado');

    echo "HOMOLOGATIONS WEB OK (". $GLOBALS['checks'] ." checks)\n";
}finally{
    $_GET=[];
    unset($GLOBALS['homologationsTestPdo'],$GLOBALS['homologationsRendered']);
    $db->close();
}

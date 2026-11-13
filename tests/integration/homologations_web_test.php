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

    // Los registros históricos de homologación se conservan en su tabla
    // sin confundirse con el catálogo propio del proveedor Bayer.
    $db->pdo->exec("INSERT INTO bayer_productos_catalogo(codigo_bayer,nombre_bayer,marca)
        VALUES('86216353','ABACUS EC18 4X4L BOT PE','ABACUS'),
              ('84939862','ALIETTE WG80 12X1KG BAG PE','ALIETTE')");

    $_GET=[];
    $controller->homologations();
    $result=$GLOBALS['homologationsRendered'];
    ensure($result['name']==='bayer_catalog.index','Productos Bayer usa catálogo independiente');
    ensure($result['data']['q']==='','Sin ?q= se aplica búsqueda vacía');
    ensure($result['data']['total']===2,'Solo se muestran códigos del proveedor Bayer, no códigos Pucchún');

    $_GET=['tab'=>'productos','q'=>'86216353'];
    $controller->homologations();
    $result=$GLOBALS['homologationsRendered'];
    ensure($result['data']['total']===1,'Búsqueda por código Bayer real');
    ensure($result['data']['rows'][0]['codigo_bayer']==='86216353','Resultado correcto');

    $_GET=['tab'=>'productos','q'=>['valor'=>'inesperado']];
    $controller->homologations();
    ensure($GLOBALS['homologationsRendered']['data']['q']==='','Parámetro q array no causa 500');

    $_GET=['tab'=>['productos'],'q'=>''];
    $controller->homologations();
    ensure($GLOBALS['homologationsRendered']['name']==='bayer_catalog.index',
        'Tab malformado vuelve a catálogo Bayer seguro');

    $_GET=['tab'=>'clientes','q'=>['valor'=>'invalido']];
    $controller->masters();
    ensure($GLOBALS['homologationsRendered']['data']['q']==='','Catálogos maestros manejan q malformado');

    echo "HOMOLOGATIONS WEB OK (". $GLOBALS['checks'] ." checks)\n";
}finally{
    $_GET=[];
    unset($GLOBALS['homologationsTestPdo'],$GLOBALS['homologationsRendered']);
    $db->close();
}

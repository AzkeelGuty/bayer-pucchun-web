<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Helpers/functions.php';
spl_autoload_register(static function(string $class):void{
    if(str_starts_with($class,'App\\')) {
        $path=dirname(__DIR__,2).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        if(is_file($path))require_once $path;
    }
});

$checks=0;
$check=static function(bool $ok,string $message)use(&$checks):void{
    if(!$ok)throw new RuntimeException('Seguridad SUNAT: '.$message);
    ++$checks;
};
$root=dirname(__DIR__,2);
$routes=(string)file_get_contents($root.'/routes/web.php');
$sidebar=(string)file_get_contents($root.'/app/Views/layouts/sidebar.php');
$view=(string)file_get_contents($root.'/app/Views/sunat/index.php');
$gateway=(string)file_get_contents($root.'/app/Services/SunatInvoiceGateway.php');
$issuer=(string)file_get_contents($root.'/app/Services/SunatEmissionService.php');
$ignore=(string)file_get_contents($root.'/.gitignore');
$config=(string)file_get_contents($root.'/.env.example');
$composer=(string)file_get_contents($root.'/composer.json');
$check(str_contains($routes,"$".'router->post(\'/sunat/emitir\''),
    'Emisión utiliza POST con verificación CSRF del Router');
$check(str_contains($routes,"new RoleMiddleware(['ADMIN'])"),
    'Rutas SUNAT sólo habilitadas a ADMIN');
foreach(['/sunat','/sunat/emitir','/sunat/descargar'] as $route)
    $check(str_contains($routes,$route),'Ruta configurada: '.$route);
$check(!str_contains($sidebar,"['/sunat','bi-receipt'"),'No mostrar Emisión SUNAT en el menú lateral');
$documentView=(string)file_get_contents($root.'/app/Views/documentos/show.php');
$check(!str_contains($documentView,"url('/sunat')"),'No mostrar acceso SUNAT en detalle de Documentos');
$check(str_contains($ignore,'/storage/private/'),'No incluir archivos o certificado privado en Git');
$check(str_contains($config,'SUNAT_ENABLED=false'),'Producción apagada por defecto');
$check(str_contains($config,'SUNAT_ALLOW_PRODUCTION=false'),'Segundo interruptor para producción');
$check(str_contains($composer,'"greenter/lite"'),'Librería de firma y emisión declarada');
$check(str_contains($gateway,'SunatEndpoints::FE_BETA'),'Servicio de pruebas oficial');
$check(str_contains($gateway,'SunatEndpoints::FE_PRODUCCION'),'Servicio real explícito');
$check(str_contains($gateway,'getCdrResponse()'),'CDR real es fuente de resultado');
$check(str_contains($issuer,'SIN_RESPUESTA'),'Respuesta incierta no supone rechazo ni aceptación');
$check(str_contains($issuer,'SELECT siguiente FROM sunat_series_fiscales'),'Correlativos reservados con bloqueo');
$check(str_contains($issuer,'FOR UPDATE'),'Bloqueo transaccional aplicado');
$check(!str_contains($view,'SUNAT_SOL_PASSWORD'),'No aparece clave SOL en la vista');
$check(!str_contains($view,'BEGIN PRIVATE KEY'),'No aparece contenido PEM en la vista');
$check(str_contains($view,'data-no-soft-nav'),'Formulario de emisión por POST nativo');
$check(str_contains($view,'EMITIR'),'Producción requiere confirmación explícita');
$check(str_contains($issuer,'storage/private/sunat/emisiones'),'El XML y CDR quedan fuera de carpeta pública');

$readiness=new App\Services\SunatReadinessService();
$check(!$readiness->isReady(),'Sin secretos reales no se habilita emisión');
$check($readiness->certificatePath()===null,'No se toma un certificado de ruta no configurada');

echo 'SUNAT SECURITY OK ('.$checks.' checks)'.PHP_EOL;

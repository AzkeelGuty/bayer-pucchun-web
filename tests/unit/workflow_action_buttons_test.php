<?php
declare(strict_types=1);

function checkActionUi(bool $ok,string $label): void
{
    if(!$ok) throw new RuntimeException('Acción de interfaz: '.$label);
    $GLOBALS['actionUiChecks']++;
}
$GLOBALS['actionUiChecks']=0;
$root=dirname(__DIR__,2);
$read=static function(string $path) use($root): string {
    $contents=file_get_contents($root.'/'.$path);
    if(!is_string($contents)) throw new RuntimeException('No se encontró '.$path);
    return $contents;
};

foreach([
    'app/Views/components/bulk_workflow.php',
    'app/Views/components/workflow_control.php',
    'app/Views/documentos/show.php',
    'app/Views/guias/show.php',
    'app/Views/stock/show.php',
] as $path){
    $source=$read($path);
    checkActionUi(str_contains($source,'data-no-soft-nav'),$path.' debe enviar POST de forma nativa');
    checkActionUi(str_contains($source,'btn-with-icon'),$path.' debe mantener iconos');
}

$bulk=$read('app/Views/components/bulk_workflow.php');
checkActionUi(str_contains($bulk,'data-bulk-workflow'),'Validar todo conserva confirmación de operación masiva');
checkActionUi(str_contains($bulk,'name="status" value="VALIDADO"'),'Validar todo envía destino VALIDADO');
checkActionUi(str_contains($bulk,'name="status" value="PUBLICADO"'),'Publicar todo envía destino PUBLICADO');

$js=$read('public/assets/js/navigation.js');
checkActionUi(str_contains($js,'showRequestError'),'El navegador debe mostrar errores POST');
checkActionUi(str_contains($js,'freshNotifications'),'Los avisos de éxito/fracaso deben actualizarse');

$form=$read('app/Views/documentos/form.php');
checkActionUi(!str_contains($form,'no constituye un comprobante emitido ante SUNAT.'),'No mostrar comentario tributario en la captura');
$guideForm=$read('app/Views/guias/form.php');
checkActionUi(!str_contains($guideForm,'Esta referencia interna no representa una GRE emitida ante SUNAT.'),'No mostrar comentario tributario en guía');

$css=$read('public/assets/css/app.css');
foreach(['btn-app-blue','btn-app-green','btn-app-amber','btn-app-red'] as $selector)
    checkActionUi(str_contains($css,'.btn.'.$selector),'Color '.$selector.' disponible');

checkActionUi(str_contains($read('app/Helpers/functions.php'),'function can_workflow_permission('),'Vista comprueba permisos efectivos');
checkActionUi(str_contains($read('app/Views/components/bulk_workflow.php'),'can_workflow_permission('),'Acción masiva exige permiso efectivo');
checkActionUi(str_contains($read('app/Views/components/workflow_control.php'),'can_workflow_permission('),'Control de aprobación usa permisos efectivos');
checkActionUi(str_contains($read('public/assets/js/app.js'),'Control de acciones supervisadas'),'Selección de observar requiere motivo mediante JS');
checkActionUi(str_contains($read('database/migrations/011_permisos_flujo_aprobacion.sql'),'publications.publish'),'Migración asigna privilegios de publicación');

echo 'WORKFLOW UI ACTIONS OK ('.$GLOBALS['actionUiChecks'].' checks)'.PHP_EOL;

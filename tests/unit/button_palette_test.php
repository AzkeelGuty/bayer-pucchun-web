<?php
declare(strict_types=1);

/** Regresión visual: botones activos totalmente opacos, disabled atenuados.
 * Basada en el CSS y componentes realmente servidos por el ERP.
 */
$root=dirname(__DIR__,2);
$checks=0;
$check=static function(bool $ok,string $label) use (&$checks): void {
    if(!$ok) throw new RuntimeException('Paleta de botones: '.$label);
    ++$checks;
};
$css=(string)file_get_contents($root.'/public/assets/css/app.css');
$buttons=[
    'blue'=>'var(--bp-blue)',
    'green'=>'var(--bp-green)',
    'amber'=>'#a35a00',
    'red'=>'#b42318',
];
foreach($buttons as $color=>$background){
    $expected='.btn.btn-app-'.$color.'{background:'.$background.';border-color:'.$background.'}';
    $check(str_contains($css,$expected),'El botón activo '.$color.' es sólido, no desaturado');
    $check(str_contains($css,'.btn.btn-app-'.$color.':hover:not(:disabled):not(.disabled)'),'El botón '.$color.' tiene hover propio');
}
$check(str_contains($css,'.btn-primary{background:var(--bp-blue);border-color:var(--bp-blue)}'),'Nueva guía usa azul corporativo');
$check(str_contains($css,'.btn.btn-app-blue{background:var(--bp-blue);border-color:var(--bp-blue)}'),'Validar y Nueva guía tienen exactamente el mismo azul');
$check(str_contains($css,'.btn.btn-app-green{background:var(--bp-green);border-color:var(--bp-green)}'),'Publicar usa verde corporativo');
$check(str_contains($css,'opacity:1;filter:none'),'Acciones activas con opacidad 100%');
$check(str_contains($css,'opacity:.42;filter:saturate(.48);cursor:not-allowed;box-shadow:none'),'Acciones deshabilitadas visualmente distintas');
$check(!str_contains($css,'.btn.btn-app-blue{background:#3c769e'),'No permanece azul lavado anterior');
$check(!str_contains($css,'.btn.btn-app-green{background:#387b65'),'No permanece verde lavado anterior');
$check(!str_contains($css,'filter:brightness(.93)'), 'Sin oscurecimiento por filtro en hover de botón activo');

foreach([
    'app/Views/components/bulk_workflow.php',
    'app/Views/components/workflow_control.php',
    'app/Views/guias/show.php',
    'app/Views/stock/show.php',
    'app/Views/documentos/show.php',
] as $file){
    $source=(string)file_get_contents($root.'/'.$file);
    $check(str_contains($source,'btn-app-action'),$file.' usa botones del sistema');
    $check(str_contains($source,'btn-app-blue'),$file.' usa validación azul');
    $check(str_contains($source,'btn-app-green'),$file.' usa publicación verde');
}
$bulk=(string)file_get_contents($root.'/app/Views/components/bulk_workflow.php');
$check(str_contains($bulk,'(!$canValidate||$drafts<1)'), 'Validar todo deshabilitado solo sin permiso o borradores');
$check(str_contains($bulk,'(!$canPublish||$validated<1)'), 'Publicar todo deshabilitado solo sin permiso o registros validados');
$check(str_contains($css,'.btn.btn-app-action:disabled,'),'El estado deshabilitado tiene estilo propio');
$check(str_contains($css,'.bulk-workflow-actions .btn:disabled,'),'Los botones masivos respetan estilo disabled');

echo 'BUTTON PALETTE OK ('.$checks.' checks)'.PHP_EOL;

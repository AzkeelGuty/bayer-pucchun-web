<?php
declare(strict_types=1);

// Verificación estructural sin acceder a la base de datos ni modificar inventarios.
$root=dirname(__DIR__,2);
$view=file_get_contents($root.'/app/Views/stock/index.php');
$css=file_get_contents($root.'/public/assets/css/app.css');
$service=file_get_contents($root.'/app/Services/UnifiedStockService.php');

function stockVisualAssert(bool $valid,string $message): void
{
    if(!$valid)throw new RuntimeException($message);
}

$states=[
    'CON_STOCK'=>['tone'=>'available','icon'=>'bi-check-circle-fill'],
    'BAJO'=>['tone'=>'low','icon'=>'bi-exclamation-triangle-fill'],
    'SIN_STOCK'=>['tone'=>'zero','icon'=>'bi-x-circle-fill'],
    'NEGATIVO'=>['tone'=>'negative','icon'=>'bi-arrow-down-circle-fill'],
    'SIN_DATO'=>['tone'=>'missing','icon'=>'bi-question-circle-fill'],
    'DESACTIVADO'=>['tone'=>'inactive','icon'=>'bi-slash-circle-fill'],
];
foreach($states as $code=>$style){
    stockVisualAssert(str_contains($view,"'".$code."'") &&
        str_contains($view,"'tone'=>'".$style['tone']."'") &&
        str_contains($view,"'icon'=>'".$style['icon']."'"),
        'Falta el estado con tono e icono: '.$code);
    stockVisualAssert(str_contains($css,'.stock-overview-card--'.$style['tone'])
        &&str_contains($css,'.stock-state-pill--'.$style['tone'])
        &&str_contains($css,'.stock-line--'.$style['tone']),
        'Falta diseño CSS de '.$code);
}
stockVisualAssert(str_contains($view,'stock-overview-grid')
    &&str_contains($view,'stock-inventory-table')
    &&str_contains($view,'stock-overview-card')
    &&str_contains($view,'stock-state-pill'),
    'La tabla y tarjetas deben usar estilos exclusivos de inventario');
stockVisualAssert(str_contains($view,'stockFilterUrl($metric[')
    &&str_contains($view,"$"."params['stock_alerta']")
    &&str_contains($view,"$"."params['stock_pagina']=1"),
    'Las tarjetas deben aplicar filtro y volver a la primera página');
stockVisualAssert(str_contains($view,'data-stock-unified-filter')
    &&str_contains($view,'name="stock_q"')
    &&str_contains($view,'name="fuente"')
    &&str_contains($view,'name="stock_alerta"')
    &&str_contains($view,'name="stock_umbral"'),
    'La mejora visual debe conservar los controles originales');
stockVisualAssert(str_contains($view,'15 por página')
    &&str_contains($service,'$perPage=15')
    &&str_contains($view,'stockUnifiedPage($unified['),
    'Se debe mantener la paginación de 15 por página');
stockVisualAssert(str_contains($view,"'cantidad']===null?'—'")
    &&str_contains($view,"'fuente']==='BAYER'"),
    'Sin dato no debe convertirse a cero y las fuentes deben mantenerse identificadas');
stockVisualAssert(str_contains($css,'@media(max-width:767.98px)')
    &&str_contains($css,'.stock-origin-tag--bayer')
    &&str_contains($css,'.stock-origin-tag--pucchun'),
    'El inventario debe ser responsive e identificar las dos fuentes');
stockVisualAssert(str_contains($view,'bulk_workflow_controls(')
    &&str_contains($view,'foreach ($rows as $r)'),
    'Las acciones del stock Pucchún deben permanecer sin cambios');
echo "STOCK VISUAL DESIGN OK\n";

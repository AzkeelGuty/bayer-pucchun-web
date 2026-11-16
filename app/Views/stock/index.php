<?php
require_once base_path('app/Views/components/workflow_control.php');
require_once base_path('app/Views/components/bulk_workflow.php');
$stockPageUrl=static function(int $n) use($filters): string {
    $params=$filters;
    if($n>1) $params['page']=$n;
    return url('/stock'.($params?'?'.http_build_query($params):''));
};
$firstStock=$total>0 ? (($page-1)*$perPage)+1 : 0;
$lastStock=$total>0 ? min($total,$page*$perPage) : 0;
?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">CAPTURA Y CONTROL</div>
        <h1 class="page-title">Stock</h1>
        <p class="page-subtitle">Consulta tus cargas de stock y continúa su revisión.</p>
    </div>
    <div class="module-header-actions">
        <?php bulk_workflow_controls('stock',$bulkCounts??[]); ?>
        <?php if (has_role('ADMIN', 'DIGITADOR')): ?><a class="btn btn-primary" href="<?= url('/stock/nuevo') ?>">+ Nuevo stock</a><?php endif; ?>
    </div>
</section>

<form method="get" action="<?= url('/stock') ?>" class="card card-body mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label" for="stock-estado">Estado</label>
            <select class="form-select" id="stock-estado" name="estado_registro">
                <option value="">Todos los estados</option>
                <?php foreach (['BORRADOR', 'VALIDADO', 'PUBLICADO', 'OBSERVADO', 'ANULADO'] as $estado): ?>
                    <option value="<?= $estado ?>" <?= ($filters['estado_registro'] ?? '') === $estado ? 'selected' : '' ?>><?= $estado ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="stock-desde">Desde</label>
            <input type="date" class="form-control" id="stock-desde" name="fecha_desde" value="<?= e($filters['fecha_desde'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="stock-hasta">Hasta</label>
            <input type="date" class="form-control" id="stock-hasta" name="fecha_hasta" value="<?= e($filters['fecha_hasta'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="stock-almacen">Almacén</label>
            <select class="form-select" id="stock-almacen" name="almacen_id" data-search-select data-search-url="<?=e(url('/lookups?type=almacenes'))?>" data-search-placeholder="Escribe código o inicio del almacén..." data-search-min="1">
                <option value="">Todos</option>
                <?php foreach ($almacenes as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= (string) ($filters['almacen_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary">Filtrar</button>
            <?php if ($filters): ?><a class="btn btn-outline-primary" href="<?= url('/stock') ?>">Limpiar</a><?php endif; ?>
        </div>
    </div>
</form>

<?php
$unified=$unifiedStock??['rows'=>[],'summary'=>[],'total'=>0,'pages'=>1,'page'=>1,'filters'=>[]];
$uf=$unified['filters'];$summary=$unified['summary'];
$stockUnifiedPage=static function(int $n):string{
    $params=$_GET;
    $params['stock_pagina']=$n;
    return url('/stock?'.http_build_query($params));
};
$statusLabels=[
    'CON_STOCK'=>'Disponible','BAJO'=>'Stock bajo','SIN_STOCK'=>'Sin stock (0)',
    'NEGATIVO'=>'Negativo','SIN_DATO'=>'Sin reporte','DESACTIVADO'=>'Desactivado'
];
$stockStatusDesign=[
    'CON_STOCK'=>['tone'=>'available','icon'=>'bi-check-circle-fill'],
    'BAJO'=>['tone'=>'low','icon'=>'bi-exclamation-triangle-fill'],
    'SIN_STOCK'=>['tone'=>'zero','icon'=>'bi-x-circle-fill'],
    'NEGATIVO'=>['tone'=>'negative','icon'=>'bi-arrow-down-circle-fill'],
    'SIN_DATO'=>['tone'=>'missing','icon'=>'bi-question-circle-fill'],
    'DESACTIVADO'=>['tone'=>'inactive','icon'=>'bi-slash-circle-fill'],
];
$knownStatusCounts=(int)($summary['bajo']??0)+(int)($summary['cero']??0)
    +(int)($summary['negativo']??0)+(int)($summary['sin_dato']??0)
    +(int)($summary['desactivado']??0);
$availableCount=max(0,(int)($summary['total']??0)-$knownStatusCounts);
$stockCards=[
    ['state'=>'CON_STOCK','label'=>'Disponibles','count'=>$availableCount,
     'note'=>'Cantidad suficiente','icon'=>'bi-check-circle-fill','tone'=>'available'],
    ['state'=>'BAJO','label'=>'Stock bajo','count'=>(int)($summary['bajo']??0),
     'note'=>'Revisar reposición','icon'=>'bi-exclamation-triangle-fill','tone'=>'low'],
    ['state'=>'SIN_STOCK','label'=>'En cero','count'=>(int)($summary['cero']??0),
     'note'=>'Sin existencias','icon'=>'bi-x-circle-fill','tone'=>'zero'],
    ['state'=>'NEGATIVO','label'=>'Negativo','count'=>(int)($summary['negativo']??0),
     'note'=>'Revisar saldos','icon'=>'bi-arrow-down-circle-fill','tone'=>'negative'],
    ['state'=>'SIN_DATO','label'=>'Sin reporte','count'=>(int)($summary['sin_dato']??0),
     'note'=>'Cantidad no informada','icon'=>'bi-question-circle-fill','tone'=>'missing'],
    ['state'=>'DESACTIVADO','label'=>'Desactivados','count'=>(int)($summary['desactivado']??0),
     'note'=>'Fuera del catálogo activo','icon'=>'bi-slash-circle-fill','tone'=>'inactive'],
];
$stockFilterUrl=static function(string $state):string{
    $params=$_GET;
    $params['stock_alerta']=$state;
    $params['stock_pagina']=1;
    return url('/stock?'.http_build_query($params));
};
?>
<div class="card mb-3 stock-dashboard"><div class="card-body stock-dashboard-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1 stock-dashboard-title"><i class="bi bi-boxes" aria-hidden="true"></i><span>Inventario consolidado: Pucchún y Bayer</span></h2>
            <p class="text-muted small mb-0">Dos fuentes identificadas por separado: stock físico Pucchún publicado
                e inventario mensual informado por Bayer. No se suman cantidades entre sistemas.</p>
        </div>
        <span class="stock-total-pill"><i class="bi bi-collection" aria-hidden="true"></i><?=e((string)$unified['total'])?> referencias encontradas</span>
    </div>
    <div class="stock-overview-grid" role="group" aria-label="Indicadores de inventario por situación">
        <?php foreach($stockCards as $metric): ?>
        <a class="stock-overview-card stock-overview-card--<?=e($metric['tone'])?>
               <?=$uf['estado']===$metric['state']?'is-active':''?>"
           href="<?=e($stockFilterUrl($metric['state']))?>"
           aria-label="<?=e($metric['label'].': '.$metric['count'].' referencias. Ver registros')?>">
            <div class="stock-overview-card-top">
                <span class="stock-overview-icon"><i class="bi <?=e($metric['icon'])?>" aria-hidden="true"></i></span>
                <i class="bi bi-arrow-up-right stock-overview-arrow" aria-hidden="true"></i>
            </div>
            <div class="stock-overview-label"><?=e($metric['label'])?></div>
            <strong class="stock-overview-value"><?=e(number_format($metric['count'],0,' ',','))?></strong>
            <span class="stock-overview-note"><?=e($metric['note'])?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="<?=url('/stock')?>" class="row g-2 align-items-end mb-3" data-stock-unified-filter>
        <?php foreach(['estado_registro','fecha_desde','fecha_hasta','almacen_id'] as $key): ?>
          <?php if(isset($_GET[$key]) && is_string($_GET[$key])): ?>
            <input type="hidden" name="<?=e($key)?>" value="<?=e($_GET[$key])?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <div class="col-12 col-lg-4">
            <label class="form-label" for="unified-q">Código o producto</label>
            <input class="form-control" id="unified-q" type="search" name="stock_q"
                   value="<?=e($uf['q'])?>" placeholder="Buscar en Bayer o Pucchún">
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="unified-source">Origen</label>
            <select class="form-select" id="unified-source" name="fuente">
                <option value="">Ambos</option>
                <option value="BAYER" <?=$uf['fuente']==='BAYER'?'selected':''?>>Bayer</option>
                <option value="PUCCHUN" <?=$uf['fuente']==='PUCCHUN'?'selected':''?>>Pucchún</option>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="unified-state">Situación</label>
            <select class="form-select" id="unified-state" name="stock_alerta">
                <option value="">Todas</option>
                <?php foreach($statusLabels as $value=>$label): ?>
                <option value="<?=e($value)?>" <?=$uf['estado']===$value?'selected':''?>><?=e($label)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="unified-low">Umbral bajo (unid.)</label>
            <input class="form-control" id="unified-low" name="stock_umbral"
                type="number" min="1" max="999999" value="<?=e((string)$uf['bajo'])?>">
        </div>
        <div class="col-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="<?=url('/stock')?>" class="btn btn-outline-primary">Limpiar</a>
        </div>
    </form>
    <div class="table-responsive stock-inventory-scroll">
        <table class="table table-sm app-table align-middle stock-inventory-table">
            <thead>
                <tr><th>Origen</th><th>Código</th><th>Producto / presentación</th>
                    <th>Unidad</th><th class="text-end">Cantidad</th><th>Fecha / mes</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach($unified['rows'] as $item):
                $design=$stockStatusDesign[$item['estado']]??$stockStatusDesign['SIN_DATO'];
                $isBayer=$item['fuente']==='BAYER';
            ?>
                <tr class="stock-line stock-line--<?=e($design['tone'])?>">
                    <td>
                        <span class="stock-origin-tag <?=$isBayer?'stock-origin-tag--bayer':'stock-origin-tag--pucchun'?>">
                            <i class="bi <?=$isBayer?'bi-box-seam':'bi-building'?>" aria-hidden="true"></i>
                            <?=e($isBayer?'Bayer':'Pucchún')?>
                        </span>
                    </td>
                    <td class="stock-sku"><?=e($item['codigo'])?></td>
                    <td class="stock-product-name"><?=e($item['producto'])?></td>
                    <td><?=e($item['unidad']?:'—')?></td>
                    <td class="text-end">
                        <span class="stock-quantity <?=$item['cantidad']===null?'stock-quantity--missing':''?>">
                            <?=($item['cantidad']===null?'—':e(format_quantity($item['cantidad'])))?>
                        </span>
                    </td>
                    <td><span class="stock-period"><?=e($item['periodo']?:'—')?></span></td>
                    <td>
                        <span class="stock-state-pill stock-state-pill--<?=e($design['tone'])?>">
                            <i class="bi <?=e($design['icon'])?>" aria-hidden="true"></i>
                            <?=e($statusLabels[$item['estado']]??$item['estado'])?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$unified['rows']): ?>
                <tr><td colspan="7"><div class="stock-empty"><i class="bi bi-search" aria-hidden="true"></i><strong>Sin coincidencias</strong><span>No hay productos que coincidan con los filtros seleccionados.</span></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <nav class="catalog-pagination stock-inventory-pagination" aria-label="Paginación del inventario consolidado">
        <a class="btn btn-sm btn-outline-primary <?=$unified['page']<=1?'disabled':''?>"
           href="<?=$unified['page']>1?e($stockUnifiedPage($unified['page']-1)):'#'?>">Anterior</a>
        <span>Página <?=e((string)$unified['page'])?> de <?=e((string)$unified['pages'])?> · 15 por página</span>
        <a class="btn btn-sm btn-outline-primary <?=$unified['page']>=$unified['pages']?'disabled':''?>"
           href="<?=$unified['page']<$unified['pages']?e($stockUnifiedPage($unified['page']+1)):'#'?>">Siguiente</a>
    </nav>
    <p class="text-muted small mt-2 mb-0">La alerta de stock bajo se calcula con el umbral indicado y
        solo se aplica a cantidades positivas. Sin reporte no equivale a cero.</p>
</div></div>

<div class="card"><div class="card-body">
    <p class="text-muted small"><?=e($total)?> registros de stock encontrados<?= $total>0 ? ' · Mostrando '.e($firstStock).'–'.e($lastStock) : '' ?> · Página <?=e($page)?> de <?=e($pages)?></p>
    <div class="table-responsive">
        <table class="table app-table align-middle">
            <thead><tr><th>Fecha</th><th>Almacén</th><th>Ítems</th><th>Cantidad</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['fecha_stock']) ?></td>
                    <td><?= e($r['almacen']) ?></td>
                    <td><?= e($r['items']) ?></td>
                    <td><?= e(format_quantity($r['cantidad'])) ?></td>
                    <td><span class="badge-status status-<?= e(strtolower($r['estado_registro'])) ?>"><?= e($r['estado_registro']) ?></span></td>
                    <td>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <a class="btn btn-sm btn-app-blue btn-with-icon" href="<?= url('/stock/ver?id=' . $r['id']) ?>"><i class="bi bi-eye" aria-hidden="true"></i><span>Ver</span></a>
                            <?php if (has_role('ADMIN', 'DIGITADOR') && in_array($r['estado_registro'], ['BORRADOR','OBSERVADO'], true)): ?>
                                <a class="btn btn-sm btn-app-amber btn-with-icon" href="<?= url('/stock/editar?id=' . $r['id']) ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i><span><?= $r['estado_registro']==='OBSERVADO' ? 'Corregir' : 'Editar' ?></span></a>
                            <?php endif; ?>
                            <?php if (has_role('ADMIN', 'SUPERVISOR')) workflow_control('stock',$r,'/stock'); ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <strong>No se encontraron registros de stock</strong>
                        <span><?= $filters ? 'Prueba otros filtros o limpia la búsqueda.' : 'Los nuevos registros aparecerán aquí.' ?></span>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <nav aria-label="Paginación de stock" class="catalog-pagination">
        <a class="btn btn-sm btn-outline-primary <?=$page<=1?'disabled':''?>" href="<?=$page>1?e($stockPageUrl($page-1)):'#'?>" <?=$page<=1?'aria-disabled="true" tabindex="-1"':''?>><i class="bi bi-chevron-left"></i><span>Anterior</span></a>
        <span>Página <?=e($page)?> de <?=e($pages)?></span>
        <a class="btn btn-sm btn-outline-primary <?=$page>=$pages?'disabled':''?>" href="<?=$page<$pages?e($stockPageUrl($page+1)):'#'?>" <?=$page>=$pages?'aria-disabled="true" tabindex="-1"':''?>><span>Siguiente</span><i class="bi bi-chevron-right"></i></a>
    </nav>
</div></div>

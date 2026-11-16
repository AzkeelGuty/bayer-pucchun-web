<?php
$unified=$unifiedStock??['rows'=>[],'summary'=>[],'total'=>0,'pages'=>1,'page'=>1,'filters'=>[]];
$uf=$unified['filters']??[];
$summary=$unified['summary']??[];
$warehouses=$almacenesStock??[];
$stockPageUrl=static function(int $n):string{
    $params=$_GET;$params['stock_pagina']=$n;
    return url('/stock?'.http_build_query($params));
};
$stockFilterUrl=static function(string $state):string{
    $params=$_GET;$params['stock_alerta']=$state;$params['stock_pagina']=1;
    return url('/stock?'.http_build_query($params));
};
$sourceFilterUrl=static function(string $source):string{
    $params=$_GET;$params['fuente']=$source;$params['stock_pagina']=1;
    return url('/stock?'.http_build_query($params));
};
$statuses=[
    'CON_STOCK'=>['label'=>'Disponible','tone'=>'available','icon'=>'bi-check-circle-fill'],
    'BAJO'=>['label'=>'Stock bajo','tone'=>'low','icon'=>'bi-exclamation-triangle-fill'],
    'SIN_STOCK'=>['label'=>'Sin stock (0)','tone'=>'zero','icon'=>'bi-x-circle-fill'],
    'RIESGO'=>['label'=>'Negativo / desactivado','tone'=>'negative','icon'=>'bi-exclamation-octagon-fill'],
    'SIN_DATO'=>['label'=>'Sin reporte','tone'=>'missing','icon'=>'bi-question-circle-fill'],
];
$cards=[
    ['code'=>'CON_STOCK','label'=>'Disponibles','count'=>(int)($summary['disponible']??0),'note'=>'Cantidad suficiente','tone'=>'available','icon'=>'bi-check-circle-fill'],
    ['code'=>'BAJO','label'=>'Stock bajo','count'=>(int)($summary['bajo']??0),'note'=>'Revisar reposición','tone'=>'low','icon'=>'bi-exclamation-triangle-fill'],
    ['code'=>'SIN_STOCK','label'=>'En cero','count'=>(int)($summary['cero']??0),'note'=>'Sin existencias','tone'=>'zero','icon'=>'bi-x-circle-fill'],
    ['code'=>'RIESGO','label'=>'Negativo / desactivado','count'=>(int)($summary['riesgo']??0),'note'=>'Saldos o productos inactivos','tone'=>'negative','icon'=>'bi-exclamation-octagon-fill'],
    ['code'=>'SIN_DATO','label'=>'Sin reporte','count'=>(int)($summary['sin_dato']??0),'note'=>'Cantidad no informada','tone'=>'missing','icon'=>'bi-question-circle-fill'],
];
$totals=[
    ['label'=>'Producto total de ambos','count'=>(int)($summary['total_productos']??0),'icon'=>'bi-boxes','tone'=>'all','url'=>$sourceFilterUrl('')],
    ['label'=>'Productos Bayer','count'=>(int)($summary['total_bayer']??0),'icon'=>'bi-box-seam','tone'=>'bayer','url'=>$sourceFilterUrl('BAYER')],
    ['label'=>'Productos Pucchún','count'=>(int)($summary['total_pucchun']??0),'icon'=>'bi-building','tone'=>'pucchun','url'=>$sourceFilterUrl('PUCCHUN')],
];
?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">CAPTURA Y CONTROL</div>
        <h1 class="page-title">Stock</h1>
        <p class="page-subtitle">Consulta productos de ambos sistemas, identifica sus almacenes y revisa las alertas.</p>
    </div>
    <div class="module-header-actions">
      <?php if(has_role('ADMIN','DIGITADOR')): ?>
        <a class="btn btn-primary btn-with-icon" href="<?=url('/stock/nuevo')?>">
            <i class="bi bi-plus-circle" aria-hidden="true"></i><span>Nuevo inventario Pucchún</span>
        </a>
      <?php endif; ?>
    </div>
</section>

<div class="card mb-3 stock-dashboard">
  <div class="card-body stock-dashboard-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <h2 class="h5 mb-1 stock-dashboard-title">
          <i class="bi bi-boxes" aria-hidden="true"></i>
          <span>Inventario consolidado: Pucchún y Bayer</span>
        </h2>
        <p class="small text-muted mb-0">El reporte Bayer y las existencias físicas de Pucchún se muestran por separado.
          Cada producto se identifica por su código y almacén, sin sumar cantidades entre catálogos.</p>
      </div>
      <span class="stock-total-pill">
        <i class="bi bi-collection" aria-hidden="true"></i>
        <?=e((string)$unified['total'])?> filas de inventario
      </span>
    </div>

    <div class="stock-product-totals" aria-label="Conteo de productos individuales por catálogo">
      <?php foreach($totals as $total): ?>
      <a class="stock-product-total stock-product-total--<?=e($total['tone'])?>"
         href="<?=e($total['url'])?>"
         aria-label="<?=e($total['label'].': '.$total['count'].' productos. Filtrar')?>">
        <span class="stock-product-total-icon"><i class="bi <?=e($total['icon'])?>" aria-hidden="true"></i></span>
        <span class="stock-product-total-text">
          <span><?=e($total['label'])?></span>
          <strong><?=e(number_format($total['count'],0,'.',','))?></strong>
        </span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="stock-overview-grid" role="group" aria-label="Indicadores de inventario por situación">
      <?php foreach($cards as $metric): ?>
      <a class="stock-overview-card stock-overview-card--<?=e($metric['tone'])?>
                 <?=($uf['estado']??'')===$metric['code']?'is-active':''?>"
         href="<?=e($stockFilterUrl($metric['code']))?>"
         aria-label="<?=e($metric['label'].': '.$metric['count'].' productos. Ver registros')?>">
        <div class="stock-overview-card-top">
          <span class="stock-overview-icon"><i class="bi <?=e($metric['icon'])?>" aria-hidden="true"></i></span>
          <i class="bi bi-arrow-up-right stock-overview-arrow" aria-hidden="true"></i>
        </div>
        <span class="stock-overview-label"><?=e($metric['label'])?></span>
        <strong class="stock-overview-value"><?=e(number_format($metric['count'],0,'.',','))?></strong>
        <span class="stock-overview-note"><?=e($metric['note'])?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <form method="get" action="<?=url('/stock')?>" class="stock-unified-controls row g-2 align-items-end mb-3" data-stock-unified-filter>
      <div class="col-12 col-lg-4">
        <label class="form-label" for="unified-q">Código o producto</label>
        <input class="form-control" id="unified-q" type="search" name="stock_q"
          value="<?=e((string)($uf['q']??''))?>" placeholder="Buscar productos de Bayer o Pucchún">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="unified-source">Origen</label>
        <select class="form-select" id="unified-source" name="fuente">
          <option value="">Ambos</option>
          <option value="BAYER" <?=($uf['fuente']??'')==='BAYER'?'selected':''?>>Bayer</option>
          <option value="PUCCHUN" <?=($uf['fuente']??'')==='PUCCHUN'?'selected':''?>>Pucchún</option>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="unified-warehouse">Almacén</label>
        <select class="form-select" id="unified-warehouse" name="almacen_id">
          <option value="">Todos los almacenes</option>
          <?php foreach($warehouses as $warehouse): ?>
            <option value="<?=e((string)$warehouse['id'])?>"
              <?=(int)($uf['almacen_id']??0)===(int)$warehouse['id']?'selected':''?>>
              <?=e($warehouse['nombre'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="unified-state">Situación</label>
        <select class="form-select" id="unified-state" name="stock_alerta">
          <option value="">Todas las situaciones</option>
          <?php foreach($statuses as $code=>$status): ?>
            <option value="<?=e($code)?>" <?=($uf['estado']??'')===$code?'selected':''?>><?=e($status['label'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="unified-low">Umbral bajo (unid.)</label>
        <input class="form-control" id="unified-low" name="stock_umbral" type="number"
               min="1" max="999999" value="<?=e((string)($uf['bajo']??10))?>">
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary btn-with-icon">
          <i class="bi bi-search" aria-hidden="true"></i><span>Buscar</span>
        </button>
        <a href="<?=url('/stock')?>" class="btn btn-outline-primary btn-with-icon">
          <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>Limpiar</span>
        </a>
        <?php if((int)($uf['almacen_id']??0)>0): ?>
          <span class="stock-warehouse-filter-note">
            <i class="bi bi-building-check" aria-hidden="true"></i>
            Resumen correspondiente al almacén seleccionado
          </span>
        <?php endif; ?>
      </div>
    </form>

    <div class="table-responsive stock-inventory-scroll">
      <table class="table table-sm app-table align-middle stock-inventory-table">
        <thead>
          <tr>
            <th>Origen</th><th>Código</th><th>Producto / presentación</th>
            <th>Almacén</th><th>Unidad</th><th class="text-end">Cantidad</th>
            <th>Fecha / mes</th><th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($unified['rows'] as $item):
            $design=$statuses[$item['estado']]??$statuses['SIN_DATO'];
            $isBayer=$item['fuente']==='BAYER';
          ?>
          <tr class="stock-line stock-line--<?=e($design['tone'])?>">
            <td><span class="stock-origin-tag <?=$isBayer?'stock-origin-tag--bayer':'stock-origin-tag--pucchun'?>">
              <i class="bi <?=$isBayer?'bi-box-seam':'bi-building'?>" aria-hidden="true"></i>
              <?=e($isBayer?'Bayer':'Pucchún')?>
            </span></td>
            <td class="stock-sku"><?=e($item['codigo'])?></td>
            <td class="stock-product-name"><?=e($item['producto'])?></td>
            <td>
              <span class="stock-warehouse-name">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <?=e($item['almacen'])?>
              </span>
            </td>
            <td><?=e($item['unidad']?:'—')?></td>
            <td class="text-end">
              <span class="stock-quantity <?=$item['cantidad']===null?'stock-quantity--missing':''?>">
                <?=$item['cantidad']===null?'—':e(format_quantity($item['cantidad']))?>
              </span>
            </td>
            <td><span class="stock-period"><?=e($item['periodo']?:'—')?></span></td>
            <td>
              <span class="stock-state-pill stock-state-pill--<?=e($design['tone'])?>">
                <i class="bi <?=e($design['icon'])?>" aria-hidden="true"></i>
                <?=e($design['label'])?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$unified['rows']): ?>
            <tr><td colspan="8">
              <div class="stock-empty"><i class="bi bi-search" aria-hidden="true"></i>
                <strong>No se encontraron productos</strong>
                <span>Selecciona otro almacén, origen o estado para ampliar los resultados.</span>
              </div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <nav class="catalog-pagination stock-inventory-pagination" aria-label="Paginación de inventario">
      <a class="btn btn-sm btn-outline-primary <?=$unified['page']<=1?'disabled':''?>"
         href="<?=$unified['page']>1?e($stockPageUrl($unified['page']-1)):'#'?>"
         <?=$unified['page']<=1?'aria-disabled="true" tabindex="-1"':''?>>
         <i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior
      </a>
      <span>Página <?=e((string)$unified['page'])?> de <?=e((string)$unified['pages'])?> · 15 por página</span>
      <a class="btn btn-sm btn-outline-primary <?=$unified['page']>=$unified['pages']?'disabled':''?>"
         href="<?=$unified['page']<$unified['pages']?e($stockPageUrl($unified['page']+1)):'#'?>"
         <?=$unified['page']>=$unified['pages']?'aria-disabled="true" tabindex="-1"':''?>>
         Siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i>
      </a>
    </nav>
    <p class="text-muted small mt-3 mb-0">Los totales individuales identifican cada código una sola vez por catálogo.
      Si un producto Pucchún figura en varios almacenes, puede aparecer en varias filas,
      pero no duplica el conteo individual. Sin reporte no equivale a cero.
      El almacén de referencia no genera existencias físicas.</p>
  </div>
</div>

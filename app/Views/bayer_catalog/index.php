<?php
$tabs=['productos'=>'Productos Bayer','clientes'=>'Clientes Bayer','unidades'=>'Unidades Bayer',
    'sucursales'=>'Sucursales Bayer','almacenes'=>'Almacenes Bayer','vendedores'=>'Vendedores Bayer'];
$icons=['productos'=>'bi-box-seam','clientes'=>'bi-people','unidades'=>'bi-rulers','sucursales'=>'bi-building',
    'almacenes'=>'bi-boxes','vendedores'=>'bi-person-vcard'];
$labelStatus=['CON_STOCK'=>'Con stock','SIN_STOCK'=>'Sin stock','SIN_DATO'=>'Sin dato','DESACTIVADO'=>'Desactivado'];
$qs=['tab'=>'productos','q'=>$q,'estado'=>$estado];
?>
<section class="page-header">
  <div><div class="page-eyebrow"><i class="bi bi-database"></i> ADMINISTRACIÓN</div>
    <h1 class="page-title">Productos Bayer</h1>
    <p class="page-subtitle">Catálogo original de Bayer, independiente del catálogo y stock de Pucchún.</p>
  </div>
</section>
<nav class="section-tabs section-tabs-strong" aria-label="Catálogos Bayer">
<?php foreach($tabs as $key=>$label): ?>
<a class="section-tab <?=$key==='productos'?'active':''?>" href="<?=url('/homologaciones?tab='.$key)?>"
  <?=$key==='productos'?'aria-current="page"':''?>>
    <i class="bi <?=e($icons[$key])?>" aria-hidden="true"></i><span><?=e($label)?></span>
    <?php if($key==='productos'): ?><i class="bi bi-check-circle-fill section-tab-check"></i><?php endif; ?>
</a>
<?php endforeach; ?>
</nav>
<div class="master-toolbar card mb-3"><div class="card-body py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
  <div><strong>Administrar productos Bayer</strong>
    <div class="form-text mt-0">El código es el que figura en el reporte Bayer. No es necesario ingresar partner ni un segundo código.</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <form method="post" action="<?=url('/bayer-productos/precios-cargar')?>" class="d-inline"
      onsubmit="return confirm('¿Cargar la lista Dealer Bayer del 02/11/2026 (99 referencias) sin modificar el stock?')">
      <?=csrf_field()?>
      <input type="hidden" name="confirmacion" value="CARGAR">
      <button class="btn btn-outline-primary btn-with-icon" type="submit">
        <i class="bi bi-currency-exchange"></i> Cargar precios Bayer 2026
      </button>
    </form>
    <a class="btn btn-primary btn-with-icon" href="<?=url('/bayer-productos/editar')?>"><i class="bi bi-plus-lg"></i> Agregar uno</a>
    <a class="btn btn-success btn-with-icon" href="<?=url('/bayer-productos/importar')?>"><i class="bi bi-file-earmark-spreadsheet"></i> Importar Excel original</a>
    <a class="btn btn-outline-primary btn-with-icon" data-native-navigation href="<?=url('/bayer-productos/plantilla')?>"><i class="bi bi-download"></i> Plantilla XLSX</a>
  </div>
</div></div>
<form class="card card-body mb-3" action="<?=url('/homologaciones')?>" method="get" data-master-search-form>
  <input type="hidden" name="tab" value="productos">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-6"><label class="form-label" for="bayer-q">Buscar productos Bayer</label>
      <input class="form-control" id="bayer-q" type="search" name="q" value="<?=e($q)?>" placeholder="Código Bayer, producto o marca..." autocomplete="off"></div>
    <div class="col-12 col-md-3"><label for="bayer-estado" class="form-label">Estado de inventario</label>
      <select class="form-select" id="bayer-estado" name="estado">
        <option value="">Todos</option>
        <?php foreach($labelStatus as $key=>$label): ?>
          <option value="<?=e($key)?>" <?=$estado===$key?'selected':''?>><?=e($label)?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
      <a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Limpiar</a>
    </div>
  </div>
</form>
<div class="card"><div class="card-body">
  <p class="text-muted small mb-3"><strong><?=e((string)$total)?> productos Bayer</strong> · página <?=e((string)$page)?> de <?=e((string)$pages)?>.
    Los inventarios Bayer son reportes del canal; las cantidades físicas de Pucchún se gestionan en Stock.</p>
  <div class="table-responsive">
    <table class="table app-table align-middle">
      <thead><tr><th>Código Bayer</th><th>Producto / presentación</th><th>Marca</th>
        <th class="text-end">Stock Bayer</th><th>Mes</th><th class="text-end">Precio sugerido c/IGV</th><th>Estado Bayer</th><th>Stock Pucchún</th><th>API</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach($rows as $item):
        $status=(string)$item['stock_estado'];
        if($item['estado_catalogo']==='INACTIVO')$status='DESACTIVADO';
        $stock=$status==='DESACTIVADO'?'—':($item['stock_reportado']===null?'':(string)$item['stock_reportado']);
      ?>
      <tr>
        <td class="fw-semibold"><?=e($item['codigo_bayer'])?></td>
        <td><?=e($item['nombre_bayer'])?></td>
        <td><?=e($item['marca']??'')?></td>
        <td class="text-end fw-semibold"><?=e($stock)?></td>
        <td><?=e($item['stock_periodo']??'')?></td>
        <td class="text-end"><?=($item['precio_referencia']!==null)?'S/ '.e((string)$item['precio_referencia']):''?></td>
        <td>
          <?php if($status==='DESACTIVADO'): ?>
            <span class="badge bg-danger">Desactivado (—)</span>
          <?php elseif($status==='SIN_DATO'): ?>
            <span class="badge bg-light text-dark border">Sin dato</span>
          <?php elseif($status==='SIN_STOCK'): ?>
            <span class="badge bg-secondary">Sin stock (0)</span>
          <?php else: ?>
            <span class="badge bg-success">Con stock</span>
          <?php endif; ?>
        </td>
        <td></td>
        <td><?=$item['publicado']?'Publicado':'Pendiente'?></td>
        <td><div class="d-flex align-items-center flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?=url('/bayer-productos/ver?id='.(int)$item['id'])?>"><i class="bi bi-eye"></i> Ver</a>
            <a class="btn btn-sm btn-app-blue" href="<?=url('/bayer-productos/editar?id='.(int)$item['id'])?>"><i class="bi bi-pencil-square"></i> Editar</a>
            <form action="<?=url('/bayer-productos/estado')?>" method="post" class="d-inline">
              <?=csrf_field()?>
              <input type="hidden" name="id" value="<?=e((string)$item['id'])?>">
              <?php if($item['estado_catalogo']==='ACTIVO'): ?>
                <button type="submit" name="accion" value="DESACTIVAR" class="btn btn-sm btn-app-amber"
                  onclick="return confirm('¿Desactivar este producto Bayer?')">Desactivar</button>
              <?php else: ?>
                <button type="submit" name="accion" value="ACTIVAR" class="btn btn-sm btn-outline-success">Activar</button>
              <?php endif; ?>
            </form>
            <?php if(!$item['publicado']): ?>
            <form action="<?=url('/bayer-productos/estado')?>" method="post" class="d-inline">
              <?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$item['id'])?>">
              <button type="submit" class="btn btn-sm btn-success" name="accion" value="PUBLICAR"
                onclick="return confirm('¿Publicar este producto Bayer en la API de consulta?')">Publicar API</button>
            </form>
            <?php endif; ?>
            <a class="btn btn-sm btn-app-red" href="<?=url('/bayer-productos/eliminar?id='.(int)$item['id'])?>"><i class="bi bi-trash"></i> Eliminar</a>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="10"><div class="empty-state">
        <strong>No se encontraron productos Bayer</strong>
        <span>Importa el Excel original del proveedor para cargar códigos, presentaciones y stock por mes.</span>
      </div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <nav class="catalog-pagination" aria-label="Paginación productos Bayer">
  <?php
  $pageLink=static function(int $n)use($qs):string{return url('/homologaciones?'.http_build_query(array_merge($qs,['page'=>$n])));};
  ?>
    <a class="btn btn-sm btn-outline-primary <?=$page<=1?'disabled':''?>" href="<?=$page>1?e($pageLink($page-1)):'#'?>" <?=$page<=1?'aria-disabled="true" tabindex="-1"':''?>><i class="bi bi-chevron-left"></i> Anterior</a>
    <span>Página <?=e((string)$page)?> de <?=e((string)$pages)?></span>
    <a class="btn btn-sm btn-outline-primary <?=$page>=$pages?'disabled':''?>" href="<?=$page<$pages?e($pageLink($page+1)):'#'?>" <?=$page>=$pages?'aria-disabled="true" tabindex="-1"':''?>>Siguiente <i class="bi bi-chevron-right"></i></a>
  </nav>
</div></div>

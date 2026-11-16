<?php
$editing=$id>0;
$back=url('/homologaciones?tab=productos');
$price=$activePrice??[];
$priceHistory=$prices??[];
$priceDate=(string)($price['vigencia_desde']??date('Y-m-d'));
$stockLabel=($record['stock_estado']??'SIN_DATO');
$stockNames=['SIN_DATO'=>'Sin reporte','SIN_STOCK'=>'Sin stock (0)',
    'CON_STOCK'=>'Con stock','DESACTIVADO'=>'Desactivado'];
?>
<section class="module-header has-back-navigation">
  <div>
    <div class="page-eyebrow">PRODUCTOS BAYER · ADMINISTRACIÓN</div>
    <h1 class="page-title"><?=$editing?'Editar producto Bayer':'Registrar producto Bayer'?></h1>
    <p class="page-subtitle">Administra la presentación comercial y sus precios utilizando el código oficial Bayer.</p>
  </div>
  <a class="btn btn-app-blue btn-with-icon btn-back-list" href="<?=e($back)?>">
    <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Volver al listado</span>
  </a>
</section>

<form class="card mb-3" method="post" action="<?=url('/bayer-productos/guardar')?>" data-bayer-price-editor>
  <?=csrf_field()?>
  <input type="hidden" name="id" value="<?=e((string)$id)?>">

  <div class="card-body p-4">
    <h2 class="h5 mb-3"><i class="bi bi-box-seam me-2" aria-hidden="true"></i>Información del producto</h2>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="bayer-codigo">Código Bayer *</label>
        <input class="form-control" id="bayer-codigo" name="codigo_bayer" maxlength="40"
               required <?=$editing?'readonly':''?>
               value="<?=e((string)($record['codigo_bayer']??''))?>">
        <div class="form-text"><?=$editing
            ?'Identificador de esta presentación. No se modifica para proteger su historial.'
            :'Indica el código oficial del proveedor, sin crear un código interno adicional.'?></div>
      </div>
      <div class="col-md-8">
        <label class="form-label" for="bayer-nombre">Producto / presentación *</label>
        <input class="form-control" id="bayer-nombre" name="nombre_bayer" maxlength="200"
               required value="<?=e((string)($record['nombre_bayer']??''))?>">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="bayer-marca">Marca</label>
        <input class="form-control" id="bayer-marca" name="marca" maxlength="100"
               value="<?=e((string)($record['marca']??''))?>">
      </div>
      <?php if($editing): ?>
      <div class="col-md-4">
        <label class="form-label">Último mes Bayer reportado</label>
        <div class="form-control bg-body-tertiary"><?=e((string)($record['stock_periodo']?:'Sin reporte'))?></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Última situación de inventario Bayer</label>
        <div class="form-control bg-body-tertiary">
          <?=e($stockNames[$stockLabel]??$stockLabel)?>
          <?php if($record['stock_reportado']!==null && $stockLabel!=='DESACTIVADO'): ?>
            · <?=e((string)$record['stock_reportado'])?>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <p class="form-text mt-3 mb-0">
      Los movimientos mensuales se actualizan desde <a href="<?=url('/bayer-productos/importar')?>">Importar Excel original</a>.
      La cantidad vacía no equivale a cero; el inventario Bayer es independiente del stock Pucchún.
    </p>
  </div>

  <div class="card-body p-4 border-top">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h2 class="h5 mb-1"><i class="bi bi-currency-exchange me-2" aria-hidden="true"></i>Precio sugerido Bayer</h2>
        <p class="text-muted small mb-0">Valores en soles por presentación comercial, no por litro, gramo o kilogramo.</p>
      </div>
      <?php if($editing && $priceHistory): ?>
        <span class="badge bg-light border text-dark"><?=count($priceHistory)?> vigencia(s) consultables</span>
      <?php endif; ?>
    </div>
    <?php if($editing && $price): ?>
      <p class="small text-muted">Estás editando la lista con fecha inicial
        <strong><?=e($price['vigencia_desde'])?></strong>.
        Mantén la fecha para modificarla o cámbiala para crear una nueva vigencia sin borrar la anterior.
      </p>
    <?php endif; ?>
    <div class="row g-3">
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="bayer-precio-desde">Vigente desde</label>
        <input class="form-control" id="bayer-precio-desde" type="date" name="precio_vigencia_desde"
               value="<?=e($priceDate)?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="bayer-precio-hasta">Vigente hasta (opcional)</label>
        <input class="form-control" id="bayer-precio-hasta" type="date" name="precio_vigencia_hasta"
               value="<?=e((string)($price['vigencia_hasta']??''))?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="bayer-precio-neto">Precio sugerido sin IGV (S/)</label>
        <input class="form-control" id="bayer-precio-neto" type="number" step="0.01" min="0"
               max="9999999999.99" inputmode="decimal" name="precio_sin_igv"
               value="<?=e((string)($price['sugerido_sin_igv']??''))?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="bayer-precio-igv">IGV (%)</label>
        <input class="form-control" id="bayer-precio-igv" type="number" step="0.01" min="0" max="100"
               inputmode="decimal" name="precio_igv"
               value="<?=e((string)($price['igv_porcentaje']??''))?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="bayer-precio-final">Precio sugerido con IGV (S/)</label>
        <input class="form-control" id="bayer-precio-final" type="number" step="0.01" min="0"
               max="9999999999.99" inputmode="decimal" name="precio_con_igv"
               value="<?=e((string)($price['sugerido_con_igv']??''))?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="bayer-precio-descuento">Descuento dealer (%)</label>
        <input class="form-control" id="bayer-precio-descuento" type="number" step="0.01" min="0" max="100"
               inputmode="decimal" name="precio_descuento"
               value="<?=e((string)($price['descuento_dealer']??''))?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">Moneda</label>
        <div class="form-control bg-body-tertiary">PEN · Sol peruano (S/)</div>
      </div>
    </div>
    <div class="form-text mt-3">
      Si ingresas el precio neto y la tasa IGV, el importe final se calcula al editar los valores.
      Si solo cuentas con los precios neto y final, el sistema deduce el IGV cuando corresponde.
      Deja los precios sin completar si únicamente deseas registrar el producto.
    </div>
  </div>

  <div class="card-body p-4 border-top">
    <h2 class="h5 mb-2"><i class="bi bi-boxes me-2" aria-hidden="true"></i>Inventario mensual informado por Bayer</h2>
    <p class="small text-muted">Puedes corregir manualmente un mes de inventario de esta presentación.
      Si no necesitas modificar existencias, selecciona <strong>Sin cambios</strong>.
      El historial Bayer no modifica el stock físico de almacenes Pucchún.</p>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="bayer-stock-accion">Actualización mensual</label>
        <select class="form-select" name="inventario_accion" id="bayer-stock-accion" data-bayer-stock-action>
          <option value="SIN_CAMBIOS" selected>Sin cambios</option>
          <option value="CANTIDAD">Registrar cantidad (incluido 0)</option>
          <option value="SIN_DATO">Registrar mes sin dato</option>
          <option value="DESACTIVADO">Desactivar en este mes (—)</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="bayer-stock-periodo">Mes del inventario Bayer</label>
        <input class="form-control" type="month" id="bayer-stock-periodo"
               name="inventario_periodo" value="<?=e((string)($record['stock_periodo']??date('Y-m')))?>">
      </div>
      <div class="col-md-4" data-bayer-stock-qty-wrap hidden>
        <label class="form-label" for="bayer-stock-cantidad">Cantidad reportada</label>
        <input class="form-control" id="bayer-stock-cantidad" type="number" min="0"
               step="1" max="999999999" name="inventario_cantidad"
               value="<?=e((string)($record['stock_reportado']??''))?>">
      </div>
    </div>
  </div>

  <?php if($editing && $priceHistory): ?>
  <div class="card-body p-4 border-top">
    <h2 class="h6 mb-2">Historial de precios de esta presentación</h2>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th>Vigente desde</th><th>Vigente hasta</th><th>Sin IGV</th><th>IGV</th>
            <th>Con IGV</th><th>Descuento</th><th>Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach($priceHistory as $entry): ?>
          <tr class="<?=$priceDate===(string)$entry['vigencia_desde']?'table-primary':''?>">
            <td><?=e($entry['vigencia_desde'])?></td>
            <td><?=e((string)($entry['vigencia_hasta']??'—'))?></td>
            <td>S/ <?=e((string)$entry['sugerido_sin_igv'])?></td>
            <td><?=e((string)$entry['igv_porcentaje'])?> %</td>
            <td>S/ <?=e((string)$entry['sugerido_con_igv'])?></td>
            <td><?=e((string)($entry['descuento_dealer']??'—'))?></td>
            <td><a class="btn btn-sm btn-outline-primary btn-with-icon"
                href="<?=url('/bayer-productos/editar?id='.$id.'&precio_fecha='.rawurlencode((string)$entry['vigencia_desde']))?>">
                <i class="bi bi-pencil-square" aria-hidden="true"></i><span>Editar</span>
            </a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="card-footer d-flex gap-2 flex-wrap">
    <button type="submit" class="btn btn-primary btn-with-icon">
      <i class="bi bi-floppy" aria-hidden="true"></i><span><?=$editing?'Guardar cambios':'Guardar producto'?></span>
    </button>
    <a href="<?=e($back)?>" class="btn btn-outline-primary">Cancelar</a>
    <?php if($editing): ?>
    <a href="<?=url('/bayer-productos/ver?id='.(int)$id)?>" class="btn btn-outline-primary btn-with-icon">
      <i class="bi bi-clock-history" aria-hidden="true"></i><span>Ver historial completo</span>
    </a>
    <?php endif; ?>
  </div>
</form>

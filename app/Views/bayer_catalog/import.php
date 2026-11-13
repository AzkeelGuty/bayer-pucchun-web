<?php
/** Importación en dos pasos: primero inspeccionar, después confirmar una sola pestaña. */
$hasPreview=is_array($preview??null)&&count($preview)>0;
$selectedSheet=$hasPreview?($preview[$selected]??$preview[0]):null;
$names=['CON_STOCK'=>'Con stock','SIN_STOCK'=>'Sin stock',
    'SIN_DATO'=>'Sin dato','DESACTIVADO'=>'Desactivado','INVALIDO'=>'Revisar'];
?>
<section class="module-header">
  <div>
    <div class="page-eyebrow">BAYER · CARGA DE DATOS</div>
    <h1 class="page-title">Importar inventario Bayer</h1>
    <p class="page-subtitle">Carga directamente el Excel original o CSV; revisa sus hojas y registra únicamente la que elijas.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Volver a Productos Bayer</a>
</section>

<div class="card mb-3"><div class="card-body p-4">
  <div class="d-flex align-items-center gap-2 mb-3">
    <span class="badge bg-primary">1</span>
    <h2 class="h5 mb-0">Seleccionar archivo original</h2>
  </div>
  <form method="post" action="<?=url('/bayer-productos/analizar')?>" enctype="multipart/form-data">
    <?=csrf_field()?>
    <div class="row g-3 align-items-end">
      <div class="col-xl-7">
        <label class="form-label" for="file-bayer">Reporte de inventario XLSX o CSV</label>
        <input class="form-control" id="file-bayer" type="file" name="excel" accept=".xlsx,.csv" required>
      </div>
      <div class="col-xl-3">
        <label class="form-label" for="bayer-periodo">Período alternativo (opcional)</label>
        <input class="form-control" id="bayer-periodo" type="month" name="periodo">
      </div>
      <div class="col-xl-2 d-grid">
        <button class="btn btn-primary btn-with-icon" type="submit">
          <i class="bi bi-search"></i><span>Analizar archivo</span>
        </button>
      </div>
    </div>
    <p class="small text-muted mt-3 mb-0">
      Detecta encabezados como <strong>CODIGO</strong>, <strong>PRODUCTO / PRESENTACION</strong>,
      <strong>MARCA</strong>, <strong>CANTIDAD / UNIDADES</strong>, Year y Month, incluso cuando
      comienzan después de una portada. Máximo 12 MB y 10 000 productos por hoja.
      No tienes que copiar nada a otra plantilla.
    </p>
  </form>
</div></div>

<?php if($hasPreview): ?>
<div class="card mb-3"><div class="card-body p-4">
  <div class="d-flex justify-content-between flex-wrap align-items-center gap-2 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-primary">2</span>
        <h2 class="h5 mb-0">Seleccionar pestaña y revisar datos</h2>
      </div>
      <p class="text-muted small mb-0">
        Archivo: <strong><?=e((string)$stage['name'])?></strong> ·
        <?=count($preview)?> hoja(s) con datos reconocidos.
        La vista previa no modifica ningún registro.
      </p>
    </div>
    <form method="post" action="<?=url('/bayer-productos/importar/cancelar')?>">
      <?=csrf_field()?>
      <button type="submit" class="btn btn-outline-secondary">Descartar archivo</button>
    </form>
  </div>

  <form method="get" action="<?=url('/bayer-productos/importar')?>" class="mb-3">
    <label class="form-label" for="bayer-hoja">Hoja del reporte que deseas registrar</label>
    <div class="row g-2 align-items-center">
      <div class="col-lg-9">
        <select class="form-select" id="bayer-hoja" name="hoja" onchange="this.form.submit()">
          <?php foreach($preview as $i=>$item): ?>
            <option value="<?=$i?>" <?=$i===$selected?'selected':''?>>
              <?=e($item['name'])?> · <?=e((string)$item['count'])?> productos
              <?=$item['errors']>0?' · Requiere revisión':''?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-3 d-grid">
        <button type="submit" class="btn btn-outline-primary">Ver hoja seleccionada</button>
      </div>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge bg-secondary">Pestaña: <?=e($selectedSheet['name'])?></span>
    <span class="badge bg-secondary">Registros: <?=e((string)$selectedSheet['count'])?></span>
    <span class="badge bg-secondary">Período(s):
      <?=e($selectedSheet['periods']?implode(', ',$selectedSheet['periods']):'No indicado')?>
    </span>
    <?php foreach($selectedSheet['statuses'] as $status=>$count): ?>
      <?php if($count>0): ?>
        <span class="badge bg-light text-dark border"><?=e($names[$status]??$status)?>: <?=e((string)$count)?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped table-hover align-middle">
      <thead class="table-light">
        <tr><th>Fila</th><th>Código Bayer</th><th>Producto / presentación</th>
            <th>Marca</th><th>Mes</th><th class="text-end">Cantidad</th><th>Interpretación</th></tr>
      </thead>
      <tbody>
      <?php foreach($selectedSheet['sample'] as $row): ?>
        <tr>
          <td><?=e((string)$row['fila'])?></td>
          <td class="fw-semibold"><?=e($row['codigo_bayer'])?></td>
          <td><?=e($row['nombre_bayer'])?></td>
          <td><?=e($row['marca'])?></td>
          <td><?=e($row['periodo']!==''?$row['periodo']:'—')?></td>
          <td class="text-end"><?=e($row['cantidad']!==''?$row['cantidad']:'—')?></td>
          <td><?=e($names[$row['estado_previo']]??$row['estado_previo'])?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if($selectedSheet['count']>count($selectedSheet['sample'])): ?>
    <p class="small text-muted mb-0">Se muestran los primeros
      <?=count($selectedSheet['sample'])?> registros para revisión.
      Al confirmar se procesan los <?=e((string)$selectedSheet['count'])?> de esta hoja.</p>
  <?php endif; ?>
</div></div>

<div class="card mb-3"><div class="card-body p-4">
  <div class="d-flex align-items-center gap-2 mb-3">
    <span class="badge bg-primary">3</span><h2 class="h5 mb-0">Confirmar importación</h2>
  </div>
  <?php if($selectedSheet['errors']>0): ?>
    <div class="alert alert-warning mb-0">
      Se detectaron <?=e((string)$selectedSheet['errors'])?> inconsistencias en esta pestaña:
      períodos faltantes, cantidades inválidas o códigos duplicados.
      Corrige el archivo y vuelve a analizarlo antes de registrar.
    </div>
  <?php else: ?>
    <p>Se importarán <strong><?=e((string)$selectedSheet['count'])?> productos</strong> de
       <strong><?=e($selectedSheet['name'])?></strong>. Las demás pestañas no se importarán.
       Los datos no se publican automáticamente en la API.</p>
    <form method="post" action="<?=url('/bayer-productos/importar')?>">
      <?=csrf_field()?>
      <input type="hidden" name="hoja" value="<?=$selected?>">
      <div class="form-check mb-3">
        <input type="checkbox" id="confirmar-hoja-bayer" name="confirmacion"
               class="form-check-input" value="IMPORTAR" required>
        <label class="form-check-label" for="confirmar-hoja-bayer">
          Confirmo que revisé esta pestaña y deseo registrar únicamente sus datos Bayer.
        </label>
      </div>
      <button type="submit" class="btn btn-success btn-with-icon">
        <i class="bi bi-check-circle"></i><span>Importar pestaña seleccionada</span>
      </button>
    </form>
  <?php endif; ?>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-body p-4">
  <h2 class="h5 mb-3">Interpretación del inventario del proveedor</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-2">
      <thead><tr><th>Dato original</th><th>Estado del producto</th></tr></thead>
      <tbody>
        <tr><td>274</td><td>Con stock (274)</td></tr>
        <tr><td>0</td><td>Sin stock (0)</td></tr>
        <tr><td>Celda vacía</td><td>Sin dato (no equivale a cero)</td></tr>
        <tr><td>— o -</td><td>Desactivado en ese período</td></tr>
      </tbody>
    </table>
  </div>
  <p class="small text-muted mb-0">
    El inventario del proveedor Bayer es independiente del stock físico Pucchún.
    La importación nunca modifica existencias de almacenes Pucchún.
  </p>
</div></div>

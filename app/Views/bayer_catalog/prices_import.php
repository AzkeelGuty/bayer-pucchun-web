<?php
$hasPreview=is_array($preview??null)&&count($preview)>0;
$current=$hasPreview?($preview[$selected]??$preview[0]):null;
$missing=$matches['missing']??[];
$issues=array_merge($current['issues']??[],$matches['issues']??[]);
$ready=$hasPreview && ($current['errors']??1)===0 && !$missing && !$issues;
?>
<section class="module-header">
  <div>
    <div class="page-eyebrow">PRODUCTOS BAYER · LISTAS COMERCIALES</div>
    <h1 class="page-title">Actualizar precios Bayer desde Excel</h1>
    <p class="page-subtitle">Los precios se identifican exclusivamente por código Bayer y presentación comercial.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Volver a Productos Bayer</a>
</section>

<div class="card mb-3"><div class="card-body p-4">
  <h2 class="h5 mb-3">1. Seleccionar lista de precios original</h2>
  <form method="post" action="<?=url('/bayer-productos/precios/analizar')?>" enctype="multipart/form-data">
    <?=csrf_field()?>
    <div class="row g-3 align-items-end">
      <div class="col-lg-9">
        <label class="form-label" for="archivo-precios-bayer">Archivo XLSX o CSV</label>
        <input class="form-control" id="archivo-precios-bayer" type="file" name="excel" accept=".xlsx,.csv" required>
      </div>
      <div class="col-lg-3 d-grid">
        <button class="btn btn-primary btn-with-icon" type="submit"><i class="bi bi-search"></i>
          <span>Analizar precios</span></button>
      </div>
    </div>
  </form>
  <p class="text-muted small mt-3 mb-0">
    Identifica las cabeceras incluso si están después de un título. Requiere código Bayer,
    precio sin IGV, tasa IGV, precio con IGV y fecha inicial de la lista. No solicita
    unidad de medida: el precio se refiere a la presentación (12X1L, 20X250ML, 1X20KG, etc.).
    Importar precios no altera inventario Bayer ni existencias Pucchún.
  </p>
</div></div>

<?php if($hasPreview): ?>
<div class="card mb-3"><div class="card-body p-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h2 class="h5 mb-1">2. Revisar hoja y coincidencias de códigos</h2>
      <p class="text-muted small mb-0">Archivo: <strong><?=e((string)$stage['name'])?></strong>.
        No se ha modificado la base de datos.</p>
    </div>
    <form method="post" action="<?=url('/bayer-productos/precios/cancelar')?>">
      <?=csrf_field()?>
      <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i>
        Cancelar y descartar</button>
    </form>
  </div>
  <form method="get" action="<?=url('/bayer-productos/precios/importar')?>" class="mb-3">
    <label class="form-label" for="hoja-precios-bayer">Hoja que se actualizará</label>
    <div class="row g-2">
      <div class="col-lg-9">
        <select id="hoja-precios-bayer" class="form-select" name="hoja">
          <?php foreach($preview as $index=>$sheet): ?>
          <option value="<?=$index?>" <?=$index===$selected?'selected':''?>>
            <?=e($sheet['name'])?> · <?=e((string)$sheet['count'])?> referencias
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-3 d-grid">
        <button class="btn btn-outline-primary" type="submit">Ver hoja</button>
      </div>
    </div>
  </form>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge bg-primary"><?=e((string)$current['count'])?> precios en Excel</span>
    <span class="badge bg-success"><?=e((string)($matches['matched']??0))?> códigos localizados</span>
    <?php if($missing): ?><span class="badge bg-danger"><?=count($missing)?> códigos sin registrar</span><?php endif; ?>
    <?php if($issues): ?><span class="badge bg-warning text-dark"><?=count($issues)?> observaciones</span><?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm align-middle">
      <thead class="table-light">
      <tr><th>Fila</th><th>Código Bayer</th><th>Producto / presentación</th><th>Desde</th>
          <th class="text-end">Sin IGV</th><th>IGV %</th>
          <th class="text-end">Con IGV</th><th>Moneda</th></tr>
      </thead>
      <tbody>
      <?php foreach($current['sample'] as $row): ?>
      <tr>
        <td><?=e($row['fila'])?></td>
        <td class="fw-semibold"><?=e($row['codigo_bayer'])?></td>
        <td><?=e($row['nombre_bayer'])?></td>
        <td><?=e($row['vigencia_desde'])?></td>
        <td class="text-end"><?=e($row['precio_sugerido_sin_igv'])?></td>
        <td><?=e($row['igv_porcentaje'])?></td>
        <td class="text-end"><?=e($row['precio_sugerido_con_igv'])?></td>
        <td><?=e($row['moneda'])?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if($current['count']>count($current['sample'])): ?>
  <p class="small text-muted mb-2">Vista previa de <?=count($current['sample'])?>
    filas. La confirmación procesará las <?=e((string)$current['count'])?> referencias de esta hoja.</p>
  <?php endif; ?>
  <?php if($missing): ?>
  <div class="alert alert-warning" role="alert">
    Hay códigos que aún no existen en Productos Bayer:
    <strong><?=e(implode(', ',array_slice($missing,0,15)))?></strong>.
    Registra primero esos productos o corrige los códigos del Excel. No se crearán productos automáticamente.
  </div>
  <?php endif; ?>
  <?php if($issues): ?>
  <div class="alert alert-danger" role="alert">
    <strong>La lista necesita corrección antes de actualizar.</strong>
    <ul class="mb-0">
    <?php foreach(array_slice($issues,0,8) as $issue): ?><li><?=e($issue)?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div></div>
<div class="card"><div class="card-body p-4">
  <h2 class="h5 mb-2">3. Confirmar actualización por código Bayer</h2>
  <?php if($ready): ?>
  <p>Se actualizarán únicamente los precios de <strong><?=e($current['name'])?></strong>,
    vinculándolos con sus códigos Bayer. Si existe un precio de la misma fecha, se actualizará;
    si la fecha es nueva, se conservará el histórico anterior.</p>
  <form method="post" action="<?=url('/bayer-productos/precios/confirmar')?>">
    <?=csrf_field()?>
    <input type="hidden" name="hoja" value="<?=$selected?>">
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" value="IMPORTAR"
             id="confirmacion-precios" name="confirmacion" required>
      <label class="form-check-label" for="confirmacion-precios">
        Confirmo que revisé los importes y que los códigos pertenecen a estos productos Bayer.
      </label>
    </div>
    <button class="btn btn-success btn-with-icon" type="submit"><i class="bi bi-check-circle"></i>
      <span>Actualizar precios de esta hoja</span></button>
  </form>
  <?php else: ?>
  <p class="text-muted mb-0">La actualización está deshabilitada hasta que todos
    los códigos y precios de la hoja sean válidos.</p>
  <?php endif; ?>
</div></div>
<?php endif; ?>

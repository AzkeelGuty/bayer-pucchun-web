<?php
$hasPreview=is_array($preview??null)&&count($preview)>0;
$sheet=$hasPreview?($preview[$selected]??$preview[0]):null;
$labels=['CON_STOCK'=>'Con stock','SIN_STOCK'=>'En cero','SIN_DATO'=>'Sin dato',
    'DESACTIVADO'=>'Desactivado','INVALIDO'=>'Revisar'];
$mode=['INVENTARIO'=>'Inventario mensual','PRECIOS'=>'Lista de precios','MIXTO'=>'Inventario y precios'];
?>
<section class="module-header">
 <div>
  <div class="page-eyebrow">BAYER · IMPORTACIÓN UNIFICADA</div>
  <h1 class="page-title">Importar Excel original Bayer</h1>
  <p class="page-subtitle">Un solo archivo para actualizar productos, inventario mensual y precios por código Bayer.</p>
 </div>
 <a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Volver a Productos Bayer</a>
</section>

<div class="card mb-3"><div class="card-body p-4">
 <h2 class="h5 mb-3"><span class="badge bg-primary">1</span> Seleccionar XLSX o CSV</h2>
 <form method="post" action="<?=url('/bayer-productos/analizar')?>" enctype="multipart/form-data">
  <?=csrf_field()?>
  <div class="row g-3 align-items-end">
   <div class="col-lg-6">
    <label class="form-label" for="file-bayer">Excel original Bayer (inventarios, precios o ambos)</label>
    <input class="form-control" id="file-bayer" name="excel" type="file" accept=".xlsx,.csv" required>
   </div>
   <div class="col-lg-2">
    <label class="form-label" for="bayer-periodo">Mes alternativo</label>
    <input class="form-control" type="month" name="periodo" id="bayer-periodo">
   </div>
   <div class="col-lg-2">
    <label class="form-label" for="bayer-fecha-precios">Fecha precios (opcional)</label>
    <input class="form-control" type="date" name="fecha_precios" id="bayer-fecha-precios">
   </div>
   <div class="col-lg-2 d-grid">
    <button class="btn btn-primary btn-with-icon" type="submit">
     <i class="bi bi-search"></i><span>Analizar Excel</span>
    </button>
   </div>
  </div>
 </form>
 <p class="small text-muted mt-3 mb-0">
   Detecta automáticamente las cabeceras, incluso en filas inferiores y en múltiples pestañas.
   Reconoce CÓDIGO, PRODUCTO / PRESENTACIÓN, CANTIDAD / UNIDADES y columnas de precios.
   El IGV no requiere una columna cuando es posible deducirlo de los dos importes.
   El mes y la fecha alternativos solo se utilizan si no aparecen en el archivo.
 </p>
</div></div>

<?php if($hasPreview): ?>
<div class="card mb-3"><div class="card-body p-4">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
   <h2 class="h5 mb-1"><span class="badge bg-primary">2</span> Elegir pestaña y revisar</h2>
   <p class="small text-muted mb-0"><?=e((string)$stage['name'])?> · <?=count($preview)?> hojas identificadas.
    Analizar no registra datos.</p>
  </div>
  <form method="post" action="<?=url('/bayer-productos/importar/cancelar')?>">
   <?=csrf_field()?>
   <button class="btn btn-outline-secondary" type="submit">Cancelar y descartar</button>
  </form>
 </div>
 <form method="get" action="<?=url('/bayer-productos/importar')?>" class="row g-2 align-items-end mb-3">
  <div class="col-lg-9">
   <label class="form-label" for="bayer-hoja">Pestaña que deseas importar</label>
   <select class="form-select" id="bayer-hoja" name="hoja" data-filter-autosubmit>
    <?php foreach($preview as $i=>$item): ?>
     <option value="<?=$i?>" <?=$i===$selected?'selected':''?>>
       <?=e($item['name'])?> — <?=e($mode[$item['tipo']]??$item['tipo'])?>
       (<?=e((string)$item['count'])?> registros)
     </option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="col-lg-3 d-grid">
   <button class="btn btn-outline-primary" type="submit">Ver selección</button>
  </div>
 </form>
 <div class="d-flex flex-wrap gap-2 mb-3">
  <span class="badge bg-primary"><?=e($mode[$sheet['tipo']]??$sheet['tipo'])?></span>
  <span class="badge bg-secondary">Registros: <?=e((string)$sheet['count'])?></span>
  <?php if(!empty($sheet['precios'])): ?>
   <span class="badge bg-info text-dark">Precios: <?=e((string)$sheet['precios'])?></span>
  <?php endif; ?>
  <?php if(!empty($sheet['periods'])): ?>
   <span class="badge bg-secondary">Mes: <?=e(implode(', ',$sheet['periods']))?></span>
  <?php endif; ?>
  <?php foreach($sheet['statuses']??[] as $status=>$count): ?>
   <?php if($count>0): ?><span class="badge bg-light text-dark border"><?=e($labels[$status]??$status)?>: <?=e((string)$count)?></span><?php endif; ?>
  <?php endforeach; ?>
 </div>

 <?php if($sheet['tipo']!=='PRECIOS'): ?>
 <h3 class="h6">Vista previa de productos e inventario</h3>
 <div class="table-responsive mb-3">
  <table class="table table-sm table-striped align-middle">
   <thead><tr><th>Fila</th><th>Código Bayer</th><th>Producto / presentación</th>
    <th>Marca</th><th>Período</th><th class="text-end">Cantidad</th><th>Estado</th></tr></thead>
   <tbody>
   <?php foreach($sheet['sample'] as $r): ?>
    <tr>
     <td><?=e((string)($r['fila']??''))?></td>
     <td><?=e($r['codigo_bayer'])?></td><td><?=e($r['nombre_bayer'])?></td>
     <td><?=e($r['marca']??'')?></td>
     <td><?=e($r['periodo']??'')?></td>
     <td class="text-end"><?=e($r['cantidad']??'')?></td>
     <td><?=e($labels[$r['estado_previo']??'']??'Sin reporte')?></td>
    </tr>
   <?php endforeach; ?>
   </tbody>
  </table>
 </div>
 <?php endif; ?>

 <?php if(!empty($sheet['priceSample'])): ?>
 <h3 class="h6">Vista previa de precios por código Bayer</h3>
 <div class="table-responsive">
  <table class="table table-sm table-striped align-middle">
   <thead><tr><th>Código Bayer</th><th>Presentación</th><th>Vigencia desde</th>
    <th class="text-end">Sin IGV</th><th>IGV</th><th class="text-end">Con IGV</th></tr></thead>
   <tbody>
   <?php foreach($sheet['priceSample'] as $r): ?>
    <tr>
     <td><?=e($r['codigo_bayer'])?></td><td><?=e($r['nombre_bayer'])?></td>
     <td><?=e($r['vigencia_desde'])?></td>
     <td class="text-end"><?=e($r['precio_sugerido_sin_igv'])?></td>
     <td><?=e($r['igv_porcentaje']?:'Calculado')?></td>
     <td class="text-end"><?=e($r['precio_sugerido_con_igv'])?></td>
    </tr>
   <?php endforeach; ?>
   </tbody>
  </table>
 </div>
 <?php endif; ?>
 <p class="small text-muted mb-0">Se muestran hasta 20 filas de cada tipo; al importar se procesarán todas las filas válidas de esta pestaña.</p>
</div></div>

<div class="card mb-3"><div class="card-body p-4">
 <h2 class="h5 mb-3"><span class="badge bg-primary">3</span> Importar pestaña seleccionada</h2>
 <?php if($sheet['errors']>0): ?>
  <div class="alert alert-warning mb-0" role="alert">
   Se encontraron <?=e((string)$sheet['errors'])?> inconsistencias. Corrige los datos y analiza nuevamente.
   <?php if(!empty($sheet['priceIssues'])): ?>
    <ul class="mb-0"><?php foreach($sheet['priceIssues'] as $issue): ?><li><?=e($issue)?></li><?php endforeach; ?></ul>
   <?php endif; ?>
  </div>
 <?php else: ?>
  <p>Se registrará solo <strong><?=e($sheet['name'])?></strong>. Las otras pestañas no se importarán.
   Los productos activos y los precios estarán disponibles automáticamente en la API interna.</p>
  <form method="post" action="<?=url('/bayer-productos/importar')?>">
   <?=csrf_field()?>
   <input type="hidden" name="hoja" value="<?=$selected?>">
   <button type="submit" class="btn btn-success btn-with-icon">
    <i class="bi bi-file-earmark-arrow-up"></i><span>Importar hoja seleccionada</span>
   </button>
  </form>
 <?php endif; ?>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-body p-4">
 <h2 class="h5">Cómo se interpretan los datos</h2>
 <p class="mb-2">Las cantidades 0, vacías y con guion se mantienen distintas.
  El precio se relaciona con <strong>codigo_bayer</strong> y la fecha inicial; una nueva fecha conserva el histórico.
  Las presentaciones 1L, 250ML, KG y GR no se convierten ni dividen al importar precios.</p>
 <p class="small text-muted mb-0">La cantidad del proveedor Bayer no altera el inventario físico ni las unidades utilizadas por Pucchún.</p>
</div></div>

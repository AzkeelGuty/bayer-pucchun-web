<?php
$hasPreview=is_array($preview??null)&&count($preview)>0;
$sheet=$hasPreview?($preview[$selected]??$preview[0]):null;
$labels=['CON_STOCK'=>'Con stock','SIN_STOCK'=>'En cero','SIN_DATO'=>'Sin dato',
    'DESACTIVADO'=>'Desactivado','INVALIDO'=>'Revisar'];
$mode=['INVENTARIO'=>'Inventario mensual','PRECIOS'=>'Lista de precios','MIXTO'=>'Inventario y precios'];
?>
<section class="module-header has-back-navigation">
 <div>
  <div class="page-eyebrow">BAYER · IMPORTACIÓN UNIFICADA</div>
  <h1 class="page-title">Importar Excel original Bayer</h1>
  <p class="page-subtitle">Un solo archivo para actualizar productos, inventario mensual y precios por código Bayer.</p>
 </div>
 <a class="btn btn-app-blue btn-with-icon btn-back-list" href="<?=url('/homologaciones?tab=productos')?>"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Volver a Productos Bayer</span></a>
</section>

<div class="card mb-3"><div class="card-body p-4">
 <h2 class="h5 mb-3"><span class="badge bg-primary">1</span> Seleccionar XLSX o CSV</h2>
 <form method="post" action="<?=url('/bayer-productos/analizar')?>" enctype="multipart/form-data">
  <?=csrf_field()?>
  <div class="row g-3 align-items-end">
   <div class="col-lg-9">
    <label class="form-label" for="file-bayer">Excel original Bayer (inventarios, precios o ambos)</label>
    <input class="form-control" id="file-bayer" name="excel" type="file" accept=".xlsx,.csv" required>
   </div>
   <div class="col-lg-3 d-grid">
    <button class="btn btn-primary btn-with-icon" type="submit">
     <i class="bi bi-search" aria-hidden="true"></i><span>Analizar Excel</span>
    </button>
   </div>
  </div>
 </form>
 <p class="small text-muted mt-3 mb-0">
   <strong>Cabeceras principales:</strong> CÓDIGO, PRODUCTO / PRESENTACIÓN, MARCA,
   CANTIDAD / UNIDADES, ALMACÉN, PRECIO SIN IGV, IGV y PRECIO CON IGV.
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
   <button class="btn btn-app-red btn-with-icon" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Cancelar y descartar</span></button>
  </form>
 </div>
 <form method="get" action="<?=url('/bayer-productos/importar')?>" class="row g-2 align-items-end mb-3">
  <div class="col-12">
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
 </form>
 <div class="d-flex flex-wrap gap-2 mb-3">
  <span class="badge bg-primary"><?=e($mode[$sheet['tipo']]??$sheet['tipo'])?></span>
  <span class="badge bg-secondary">Registros: <?=e((string)$sheet['count'])?></span>
  <?php if(!empty($sheet['precios'])): ?>
   <span class="badge bg-info text-dark">Precios: <?=e((string)$sheet['precios'])?></span>
  <?php endif; ?>
  <?php if(!empty($warehouseInfo['existing'])): ?>
    <span class="badge bg-success">Almacenes existentes: <?=e((string)count($warehouseInfo['existing']))?></span>
  <?php endif; ?>
  <?php if(!empty($warehouseInfo['new'])): ?>
    <span class="badge bg-info text-dark">Almacenes nuevos: <?=e((string)count($warehouseInfo['new']))?></span>
  <?php endif; ?>
  <?php if(!empty($sheet['periods'])): ?>
   <span class="badge bg-secondary">Mes: <?=e(implode(', ',$sheet['periods']))?></span>
  <?php endif; ?>
  <?php foreach($sheet['statuses']??[] as $status=>$count): ?>
   <?php if($count>0): ?><span class="badge bg-light text-dark border"><?=e($labels[$status]??$status)?>: <?=e((string)$count)?></span><?php endif; ?>
  <?php endforeach; ?>
 </div>

 <?php if(!empty($warehouseInfo['new']) || !empty($warehouseInfo['existing'])): ?>
  <div class="alert alert-info" role="status">
    <strong><i class="bi bi-building" aria-hidden="true"></i> Almacenes identificados</strong>
    <?php if(!empty($warehouseInfo['existing'])): ?>
      <p class="mb-1">Existentes: <?=e(implode(', ',array_slice($warehouseInfo['existing'],0,12)))?></p>
    <?php endif; ?>
    <?php if(!empty($warehouseInfo['new'])): ?>
      <p class="mb-0">Se crearán al confirmar: <?=e(implode(', ',array_slice($warehouseInfo['new'],0,12)))?></p>
    <?php endif; ?>
  </div>
 <?php endif; ?>
 <?php if(!empty($warehouseInfo['errors'])): ?>
  <div class="alert alert-warning" role="alert">
    <strong>Revise los nombres de almacén o la sucursal:</strong>
    <ul class="mb-0"><?php foreach(array_slice($warehouseInfo['errors'],0,10) as $issue): ?>
      <li><?=e($issue)?></li>
    <?php endforeach; ?></ul>
  </div>
 <?php endif; ?>

 <?php if($sheet['tipo']!=='PRECIOS'): ?>
 <h3 class="h6">Vista previa de productos e inventario</h3>
 <div class="table-responsive mb-3">
  <table class="table table-sm table-striped align-middle">
   <thead><tr><th>Fila</th><th>Código Bayer</th><th>Producto / presentación</th>
    <th>Marca</th><th>Almacén</th><th>Período</th><th class="text-end">Cantidad</th><th>Estado</th></tr></thead>
   <tbody>
   <?php foreach($sheet['sample'] as $r): ?>
    <tr>
     <td><?=e((string)($r['fila']??''))?></td>
     <td><?=e($r['codigo_bayer'])?></td><td><?=e($r['nombre_bayer'])?></td>
     <td><?=e($r['marca']??'')?></td>
     <td><?=e($r['almacen_nombre']??'')?></td>
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
   <thead><tr><th>Código Bayer</th><th>Presentación</th><th>Almacén</th><th>Vigencia desde</th>
    <th class="text-end">Sin IGV</th><th>IGV</th><th class="text-end">Con IGV</th></tr></thead>
   <tbody>
   <?php foreach($sheet['priceSample'] as $r): ?>
    <tr>
     <td><?=e($r['codigo_bayer'])?></td><td><?=e($r['nombre_bayer'])?></td>
     <td><?=e($r['almacen_nombre']??'')?></td>
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
 <?php if($sheet['errors']>0 || !empty($warehouseInfo['errors'])): ?>
  <div class="alert alert-warning mb-0" role="alert">
   Se encontraron <?=e((string)$sheet['errors'])?> inconsistencias. Corrige los datos, almacenes o sucursales y analiza nuevamente.
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



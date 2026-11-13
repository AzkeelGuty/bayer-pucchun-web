<section class="module-header"><div><div class="page-eyebrow">PRODUCTOS BAYER · DETALLE</div>
<h1 class="page-title"><?=e($product['nombre_bayer'])?></h1>
<p class="page-subtitle">Código Bayer: <?=e($product['codigo_bayer'])?> · Marca: <?=e($product['marca']??'Sin asignar')?></p></div>
<a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Volver al listado</a></section>
<div class="row g-3">
 <div class="col-xl-6"><div class="card"><div class="card-body p-4">
  <h2 class="h5">Inventario mensual reportado por Bayer</h2>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Periodo</th><th>Cantidad</th><th>Interpretación</th></tr></thead><tbody>
  <?php foreach($history as $r): ?>
    <tr><td><?=e($r['periodo'])?></td><td><?=e($r['marcador']==='GUION'?'—':($r['cantidad']===null?'':(string)$r['cantidad']))?></td>
     <td><?=e($r['marcador']==='GUION'?'Desactivado':($r['marcador']==='VACIO'?'Sin dato':((int)$r['cantidad']===0?'Sin stock':'Con stock')))?></td></tr>
  <?php endforeach; ?>
  <?php if(!$history): ?><tr><td colspan="3" class="text-muted">Sin reportes mensuales registrados.</td></tr><?php endif; ?>
  </tbody></table></div>
 </div></div></div>
 <div class="col-xl-6"><div class="card"><div class="card-body p-4">
  <h2 class="h5">Lista de precios referenciales Bayer</h2>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Desde</th><th>Hasta</th>
    <th>Sin IGV</th><th>IGV</th><th>Con IGV</th><th>Descuento</th></tr></thead><tbody>
  <?php foreach($prices as $r): ?><tr><td><?=e($r['vigencia_desde'])?></td><td><?=e($r['vigencia_hasta']??'')?></td>
    <td>S/ <?=e($r['sugerido_sin_igv'])?></td><td><?=e($r['igv_porcentaje'])?>%</td>
    <td>S/ <?=e($r['sugerido_con_igv'])?></td><td><?=e($r['descuento_dealer']??'')?>%</td></tr><?php endforeach; ?>
  <?php if(!$prices): ?><tr><td colspan="6" class="text-muted">No hay precios Bayer cargados para este producto.</td></tr><?php endif; ?>
  </tbody></table></div>
  <div class="small text-muted">Los precios sugeridos del proveedor son información comercial, no el stock físico Pucchún.</div>
 </div></div></div>
</div>

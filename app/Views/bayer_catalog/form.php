<section class="module-header"><div><div class="page-eyebrow">PRODUCTOS BAYER</div>
<h1 class="page-title"><?=$id>0?'Editar producto Bayer':'Registrar producto Bayer'?></h1>
<p class="page-subtitle">El código es el que entrega Bayer; no es el código interno de Pucchún.</p></div>
<a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Volver al listado</a></section>
<form class="card" method="post" action="<?=url('/bayer-productos/guardar')?>">
<?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$id)?>">
<div class="card-body p-4"><div class="row g-3">
 <div class="col-md-4"><label class="form-label" for="bayer-codigo">Código Bayer *</label>
   <input class="form-control" id="bayer-codigo" name="codigo_bayer" maxlength="40" required value="<?=e((string)($record['codigo_bayer']??''))?>">
   <div class="form-text">Usa el código oficial del proveedor. No se genera otro código duplicado.</div></div>
 <div class="col-md-8"><label class="form-label" for="bayer-nombre">Producto / presentación *</label>
   <input class="form-control" id="bayer-nombre" name="nombre_bayer" maxlength="200" required value="<?=e((string)($record['nombre_bayer']??''))?>"></div>
 <div class="col-md-4"><label class="form-label" for="bayer-marca">Marca</label>
   <input class="form-control" id="bayer-marca" name="marca" maxlength="100" value="<?=e((string)($record['marca']??''))?>"></div>
</div>
<p class="form-text mt-3 mb-0">Para actualizar cantidades y meses, importa el archivo original del inventario Bayer. La cantidad vacía no equivale a cero.</p></div>
<div class="card-footer d-flex gap-2"><button type="submit" class="btn btn-primary">Guardar producto</button>
<a href="<?=url('/homologaciones?tab=productos')?>" class="btn btn-outline-primary">Cancelar</a></div>
</form>

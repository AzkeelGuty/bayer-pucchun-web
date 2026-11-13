<section class="module-header"><div><div class="page-eyebrow">PRODUCTOS BAYER</div>
<h1 class="page-title">Eliminar definitivamente</h1></div>
<a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Cancelar</a></section>
<div class="card"><div class="card-body p-4">
 <p>Producto: <strong><?=e($product['nombre_bayer'])?></strong> · código <strong><?=e($product['codigo_bayer'])?></strong></p>
 <p class="text-danger fw-semibold">Eliminar este registro también borrará sus históricos de stock y precios Bayer. Los datos de Pucchún no se modificarán.</p>
 <form method="post" action="<?=url('/bayer-productos/eliminar')?>">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$product['id'])?>">
  <label class="form-label" for="confirm-bayer">Escribe el código Bayer exacto para confirmar la eliminación:</label>
  <input class="form-control mb-3" id="confirm-bayer" name="confirmacion" required maxlength="40" autocomplete="off">
  <button class="btn btn-danger" type="submit">Eliminar producto y sus registros Bayer</button>
  <a class="btn btn-outline-primary" href="<?=url('/homologaciones?tab=productos')?>">Cancelar</a>
 </form>
</div></div>

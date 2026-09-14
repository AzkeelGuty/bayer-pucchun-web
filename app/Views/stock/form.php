<?php
require base_path('app/Views/components/form_fields.php');
$mode=$mode??'create';$editing=$mode==='edit';
?>
<section class="module-header"><div><div class="page-eyebrow"><?=$editing?'EDITAR BORRADOR':'NUEVO REGISTRO'?></div><h1 class="page-title"><?=$editing?'Editar stock':'Stock'?></h1><p class="page-subtitle"><?=$editing?'Solo los registros en borrador pueden modificarse.':'Registra un snapshot de inventario por almacén y fecha.'?></p></div><a class="btn btn-outline-primary btn-with-icon" href="<?=url('/stock')?>"><i class="bi bi-arrow-left"></i><span>Volver</span></a></section>
<form method="post" action="<?=url($editing?'/stock/actualizar':'/stock/guardar')?>" class="card">
<?=csrf_field()?><?php if($editing): ?><input type="hidden" name="id" value="<?=e($defaults['id'])?>"><input type="hidden" name="version" value="<?=e($defaults['version'])?>"><input type="hidden" name="idempotencyKey" value="<?=e($defaults['idempotencyKey'])?>"><?php endif;?>
<div class="card-body p-4">
<div class="form-section-title">Distribuidor y almacén</div><div class="row g-3 mb-4"><?php field('dealerId','RUC distribuidor'); field('dealerName','Distribuidor'); field('stockDate','Fecha de stock','date'); field('warehouseId','Código almacén'); field('warehouseName','Almacén'); ?></div>
<div class="form-section-title">Producto</div><div class="row g-3"><?php field('materialId','Código producto'); field('materialName','Producto'); field('measureUnit','Unidad'); field('batch','Lote','text',false); field('quantity','Cantidad','number'); field('expirationDate','Vencimiento','date',false); ?></div>
</div>
<div class="card-footer bg-white border-0 px-4 pb-4 d-flex flex-wrap gap-2"><button class="btn btn-primary btn-with-icon"><i class="bi bi-floppy"></i><span><?=$editing?'Guardar cambios':'Guardar borrador'?></span></button><a class="btn btn-outline-primary btn-with-icon" href="<?=url('/stock')?>"><i class="bi bi-x-circle"></i><span>Cancelar</span></a></div>
</form>
<?php unset($_SESSION['_old'],$_SESSION['_errors']); ?>
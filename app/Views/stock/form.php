<?php require base_path('app/Views/components/form_fields.php'); ?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">NUEVO REGISTRO</div>
        <h1 class="page-title">Stock</h1>
        <p class="page-subtitle">Registra un snapshot de inventario por almacén y fecha.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?=url('/stock')?>">Volver</a>
</section>

<form method="post" action="<?=url('/stock/guardar')?>" class="card">
    <?=csrf_field()?>
    <div class="card-body p-4">
        <div class="form-section-title">Distribuidor y almacén</div>
        <div class="row g-3 mb-4">
            <?php field('dealerId','RUC distribuidor'); field('dealerName','Distribuidor'); field('stockDate','Fecha de stock','date'); field('warehouseId','Código almacén'); field('warehouseName','Almacén'); ?>
        </div>

        <div class="form-section-title">Producto</div>
        <div class="row g-3">
            <?php field('materialId','Código producto'); field('materialName','Producto'); field('measureUnit','Unidad'); field('batch','Lote','text',false); field('quantity','Cantidad','number'); field('expirationDate','Vencimiento','date',false); ?>
        </div>
    </div>
    <div class="card-footer bg-white border-0 px-4 pb-4 d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit">Guardar borrador</button>
        <a class="btn btn-outline-primary" href="<?=url('/stock')?>">Cancelar</a>
    </div>
</form>
<?php unset($_SESSION['_old'],$_SESSION['_errors']); ?>

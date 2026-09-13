<?php require base_path('app/Views/components/form_fields.php'); ?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">NUEVO REGISTRO</div>
        <h1 class="page-title">Documento</h1>
        <p class="page-subtitle">Registra la información inicial. El documento se guardará en estado BORRADOR.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Volver</a>
</section>

<form method="post" action="<?=url('/documentos/guardar')?>" class="card">
    <?=csrf_field()?>
    <div class="card-body p-4">
        <div class="form-section-title">Documento y distribuidor</div>
        <div class="row g-3 mb-4">
            <?php field('dealerId','RUC distribuidor'); field('dealerName','Distribuidor'); field('documentTypeId','Código tipo documento'); field('documentType','Tipo documento'); field('documentNumber','Número documento'); field('documentDate','Fecha','date'); ?>
        </div>

        <div class="form-section-title">Cliente, vendedor y sucursal</div>
        <div class="row g-3 mb-4">
            <?php field('salesId','Código vendedor'); field('salesName','Vendedor'); field('branchId','Código sucursal'); field('branchName','Sucursal'); field('customerId','RUC/DNI cliente'); field('customerName','Cliente'); ?>
        </div>

        <div class="form-section-title">Producto y ubicación</div>
        <div class="row g-3">
            <?php field('materialId','Código producto'); field('materialName','Producto'); field('measureUnit','Unidad'); field('quantity','Cantidad','number'); field('valorUnitario','Valor unitario','number',false); field('department','Departamento'); field('province','Provincia'); field('district','Distrito'); ?>
        </div>
    </div>
    <div class="card-footer bg-white border-0 px-4 pb-4 d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit">Guardar borrador</button>
        <a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Cancelar</a>
    </div>
</form>
<?php unset($_SESSION['_old'],$_SESSION['_errors']); ?>

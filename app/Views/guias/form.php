<?php
require base_path('app/Views/components/form_fields.php');
$editing = isset($record);
$correcting = $editing && (($record['header']['estado_registro'] ?? '') === 'OBSERVADO');
$detailRows = $editing ? $record['details'] : [['producto_id' => '', 'unidad_id' => '', 'cantidad' => '1']];
$numberValue = (string)old('numero');
$dateValue = (string)old('fecha');
if (!$editing && $dateValue === '') $dateValue = (string)($defaultDate ?? date('Y-m-d'));
$lookupClientes=e(url('/lookups?type=clientes'));
$lookupVendedores=e(url('/lookups?type=vendedores'));
$lookupSucursales=e(url('/lookups?type=sucursales'));
$lookupProvincias=e(url('/lookups?type=provincias'));
$lookupDistritos=e(url('/lookups?type=distritos'));
$lookupProductos=e(url('/lookups?type=productos'));
?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">GUÍAS · <?= $editing ? 'EDICIÓN' : 'CAPTURA' ?></div>
        <h1 class="page-title"><?= $correcting ? 'Corregir guía' : ($editing ? 'Editar borrador' : 'Nueva guía') ?></h1>
        <p class="page-subtitle">Selecciona los catálogos y agrega los productos de la guía.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= url('/guias') ?>">Volver al listado</a>
</section>

<form method="post" action="<?= url($editing ? '/guias/actualizar' : '/guias/guardar') ?>" class="card">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= $record['header']['id'] ?>">
        <input type="hidden" name="version" value="<?= $record['header']['version'] ?>">
    <?php endif; ?>
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Datos generales</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="guide-number">Número</label>
                <input class="form-control <?=form_error('numero')?'is-invalid':''?>" id="guide-number" name="numero" maxlength="25" value="<?=e($numberValue)?>" autocomplete="off" required>
                <div class="invalid-feedback"><?=e(form_error('numero'))?></div>
                <div class="form-text">Ingrese el número real de la guía. El sistema no genera correlativos.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="guide-date">Fecha</label>
                <input class="form-control <?=form_error('fecha')?'is-invalid':''?>" id="guide-date" type="date" name="fecha" value="<?=e($dateValue)?>" required>
                <div class="invalid-feedback"><?=e(form_error('fecha'))?></div>
            </div>
            <div class="col-md-4"></div>

            <?php select('cliente_id', 'Cliente', $clientes, 'id', 'label', true, 'data-role="cliente" data-search-select data-search-url="'.$lookupClientes.'" data-search-placeholder="Buscar por DNI/RUC o razón social..." data-search-min="1"'); ?>
            <?php select('vendedor_id', 'Vendedor', $vendedores, 'id', 'nombre', true, 'data-search-select data-search-url="'.$lookupVendedores.'" data-search-placeholder="Buscar vendedor..." data-search-min="1"'); ?>
            <?php select('sucursal_id', 'Sucursal', $sucursales, 'id', 'nombre', true, 'data-search-select data-search-url="'.$lookupSucursales.'" data-search-placeholder="Buscar sucursal..." data-search-min="1"'); ?>

            <div class="col-12" data-ubigeo-scope data-destination-shell data-client-location-url="<?=e(url('/guias/cliente-ubicacion'))?>">
                <label class="form-label mb-2">Destino de entrega</label>
                <div class="destination-card">
                    <div class="destination-copy">
                        <strong data-destination-summary>Selecciona un cliente para completar el destino.</strong>
                        <small data-destination-note>El sistema usa la ubicación registrada del cliente y solo pide cambios cuando sea necesario.</small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-success btn-sm" data-destination-save-client hidden><i class="bi bi-link-45deg"></i> Guardar ubicación en cliente</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-destination-toggle hidden>Cambiar destino</button>
                    </div>
                </div>
                <div class="row g-3 mt-1" data-destination-fields hidden>
                    <?php select('departamento_id', 'Departamento', $departamentos, 'id', 'nombre', false, 'data-role="departamento" data-old="' . e((string) old('departamento_id')) . '" data-search-select data-search-placeholder="Buscar departamento..." data-search-min="1"'); ?>
                    <?php select('provincia_id', 'Provincia', [], 'id', 'nombre', false, 'data-role="provincia" data-old="' . e((string) old('provincia_id')) . '" data-search-select data-search-url="' . e(url('/lookups?type=provincias')) . '" data-search-parent="[data-role=departamento]" data-search-placeholder="Buscar provincia..." data-search-min="1"'); ?>
                    <?php select('distrito_id', 'Distrito', [], 'id', 'nombre', false, 'data-role="distrito" data-old="' . e((string) old('distrito_id')) . '" data-search-select data-search-url="' . e(url('/lookups?type=distritos')) . '" data-search-parent="[data-role=provincia]" data-search-placeholder="Buscar distrito..." data-search-min="1"'); ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3">
            <h2 class="h5 mb-0">Detalle</h2>
            <button type="button" id="add-line-btn" class="btn btn-outline-primary">+ Añadir producto a la guía</button>
        </div>
        <div class="table-responsive">
            <table class="table app-table align-middle" id="detalle-table">
                <caption class="visually-hidden">Detalle de la guía</caption>
                <thead><tr><th>Producto</th><th>Unidad</th><th style="width:140px">Cantidad</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($detailRows as $i => $line): ?>
                    <tr>
                        <td><?php select_inline("detalle[$i][producto_id]", $productos, 'id', 'label', 'data-role="producto" data-search-select data-search-url="'.$lookupProductos.'" data-search-placeholder="Buscar por código o producto..." data-search-min="1"', 'Seleccione…', true, (string) $line['producto_id']); ?></td>
                        <td><?php select_inline("detalle[$i][unidad_id]", $unidades, 'id', 'nombre', 'data-auto-unit aria-readonly="true" tabindex="-1"', 'Seleccione…', true, (string) $line['unidad_id']); ?></td>
                        <td><input type="number" step="1" min="1" inputmode="numeric" class="form-control form-control-sm" name="detalle[<?= $i ?>][cantidad]" value="<?= e(($line['cantidad']??'')!=='' ? format_quantity($line['cantidad']) : '') ?>" required></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <template id="detalle-row-template">
            <tr>
                <td><?php select_inline('detalle[__IDX__][producto_id]', $productos, 'id', 'label', 'data-role="producto" data-search-select data-search-url="'.$lookupProductos.'" data-search-placeholder="Buscar por código o producto..." data-search-min="1"'); ?></td>
                <td><?php select_inline('detalle[__IDX__][unidad_id]', $unidades, 'id', 'nombre', 'data-auto-unit aria-readonly="true" tabindex="-1"'); ?></td>
                <td><input type="number" step="1" min="1" inputmode="numeric" class="form-control form-control-sm" name="detalle[__IDX__][cantidad]" value="1" required></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
            </tr>
        </template>
    </div>
    <div class="card-footer d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" type="submit"><?= $correcting ? 'Guardar corrección' : ($editing ? 'Actualizar borrador' : 'Guardar borrador') ?></button>
        <a class="btn btn-outline-primary" href="<?= url('/guias') ?>">Cancelar</a>
    </div>
</form>
<script>
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: false};
</script>
<script src="<?= asset_url('assets/js/forms.js') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
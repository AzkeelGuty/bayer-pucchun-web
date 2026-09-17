<?php
require base_path('app/Views/components/form_fields.php');
$editing = isset($record);
$detailRows = $editing ? $record['details'] : [['producto_id' => '', 'lote_id' => null, 'unidad_id' => '', 'cantidad' => '']];
?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">STOCK · <?= $editing ? 'EDICIÓN' : 'CAPTURA' ?></div>
        <h1 class="page-title"><?= $editing ? 'Editar borrador' : 'Nuevo stock' ?></h1>
        <p class="page-subtitle">Selecciona los catálogos y agrega los productos del stock.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= url('/stock') ?>">Volver al listado</a>
</section>

<form method="post" action="<?= url($editing ? '/stock/actualizar' : '/stock/guardar') ?>" class="card">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= $record['header']['id'] ?>">
        <input type="hidden" name="version" value="<?= $record['header']['version'] ?>">
    <?php endif; ?>
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Datos generales</h2>
        <div class="row g-3">
            <?php field('fecha_stock', 'Fecha de stock', 'date'); ?>
            <?php select('almacen_id', 'Almacén', $almacenes, 'id', 'nombre'); ?>
            <div class="col-md-4">
                <label class="form-label" for="idempotency_key">Clave de idempotencia</label>
                <input class="form-control <?= form_error('idempotency_key') ? 'is-invalid' : '' ?>" type="text" id="idempotency_key" name="idempotency_key"
                       value="<?= e((string) old('idempotency_key')) ?>" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._:-]{0,63}" required <?= $editing ? 'readonly' : '' ?>>
                <div class="form-text"><?= $editing ? 'Es inmutable una vez creado el borrador.' : 'Identifica esta carga; reenviar la misma clave con el mismo contenido no la duplica.' ?></div>
                <div class="invalid-feedback"><?= e(form_error('idempotency_key')) ?></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3">
            <h2 class="h5 mb-0">Detalle</h2>
            <button type="button" id="add-line-btn" class="btn btn-outline-primary">+ Agregar línea</button>
        </div>
        <div class="table-responsive">
            <table class="table app-table align-middle" id="detalle-table">
                <caption class="visually-hidden">Detalle del stock</caption>
                <thead><tr><th>Producto</th><th>Lote</th><th>Unidad</th><th style="width:140px">Cantidad</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($detailRows as $i => $line): ?>
                    <tr>
                        <td><?php select_inline("detalle[$i][producto_id]", $productos, 'id', 'nombre', 'data-role="producto"', 'Seleccione…', true, (string) $line['producto_id']); ?></td>
                        <td><?php select_inline("detalle[$i][lote_id]", [], 'id', 'codigo_lote', 'data-role="lote"', 'Sin lote', false, (string) ($line['lote_id'] ?? '')); ?></td>
                        <td><?php select_inline("detalle[$i][unidad_id]", $unidades, 'id', 'nombre', '', 'Seleccione…', true, (string) $line['unidad_id']); ?></td>
                        <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="detalle[<?= $i ?>][cantidad]" value="<?= e($line['cantidad']) ?>" required></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small">Agrega las líneas que necesites; la cantidad no puede ser negativa.</p>
        <template id="detalle-row-template">
            <tr>
                <td><?php select_inline('detalle[__IDX__][producto_id]', $productos, 'id', 'nombre', 'data-role="producto"'); ?></td>
                <td><?php select_inline('detalle[__IDX__][lote_id]', [], 'id', 'codigo_lote', 'data-role="lote"', 'Sin lote', false); ?></td>
                <td><?php select_inline('detalle[__IDX__][unidad_id]', $unidades, 'id', 'nombre'); ?></td>
                <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="detalle[__IDX__][cantidad]" required></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
            </tr>
        </template>
    </div>
    <div class="card-footer d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Actualizar borrador' : 'Guardar borrador' ?></button>
        <a class="btn btn-outline-primary" href="<?= url('/stock') ?>">Cancelar</a>
    </div>
</form>
<script>
window.BP_LOTES = <?= json_encode($lotes, JSON_UNESCAPED_UNICODE) ?>;
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: true};
</script>
<script src="<?= url('/assets/js/forms.js') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
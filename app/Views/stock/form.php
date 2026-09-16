<?php

require base_path('app/Views/components/form_fields.php');
$editing = isset($record);
$detailRows = $detailRows ?? ($editing ? $record['details'] : [['producto_id' => '', 'lote_id' => null, 'unidad_id' => '', 'cantidad' => '']]);
?>
<h2><?= $editing ? 'Editar stock (borrador)' : 'Nuevo stock' ?></h2>
<p class="text-muted">Complete los datos y agregue las líneas de detalle. <?= $editing ? 'Los cambios se guardan en <strong>Borrador</strong>.' : 'El registro se guardará como <strong>Borrador</strong>.' ?></p>
<?php if (!empty($errors)): ?><div class="alert alert-danger" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= url($editing ? '/stock/actualizar' : '/stock/guardar') ?>" class="row g-3">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= $record['header']['id'] ?>">
        <input type="hidden" name="version" value="<?= $record['header']['version'] ?>">
    <?php endif; ?>
    <?php field('fecha_stock', 'Fecha de stock', 'date'); ?>
    <?php select('almacen_id', 'Almacén', $almacenes, 'id', 'nombre'); ?>
    <div class="col-md-4">
        <label class="form-label">Clave de idempotencia</label>
        <input class="form-control <?= form_error('idempotency_key') ? 'is-invalid' : '' ?>" type="text" name="idempotency_key"
               value="<?= e((string) old('idempotency_key')) ?>" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._:-]{0,63}" required <?= $editing ? 'readonly' : '' ?>>
        <div class="form-text"><?= $editing ? 'Es inmutable una vez creado el borrador.' : 'Identifica esta carga; reenviar la misma clave con el mismo contenido no la duplica.' ?></div>
        <div class="invalid-feedback"><?= e(form_error('idempotency_key')) ?></div>
    </div>

    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mt-2">
            <h6 class="mb-0">Detalle</h6>
            <button type="button" id="add-line-btn" class="btn btn-sm btn-outline-primary">+ Agregar línea</button>
        </div>
        <table class="table table-sm align-middle" id="detalle-table">
            <thead><tr><th>Producto</th><th>Lote</th><th>Unidad</th><th style="width:140px">Cantidad</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($detailRows as $i => $line): ?>
                <tr>
                    <td><?php select_inline("detalle[$i][producto_id]", $productos, 'id', 'nombre', 'data-role="producto"', 'Seleccione…', true, (string) $line['producto_id']); ?></td>
                    <td><?php select_inline("detalle[$i][lote_id]", [], 'id', 'codigo_lote', 'data-role="lote"', 'Sin lote', false, (string) ($line['lote_id'] ?? '')); ?></td>
                    <td><?php select_inline("detalle[$i][unidad_id]", $unidades, 'id', 'nombre', '', 'Seleccione…', true, (string) $line['unidad_id']); ?></td>
                    <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="detalle[<?= $i ?>][cantidad]" value="<?= e($line['cantidad']) ?>" required></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line-btn">Quitar</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <template id="detalle-row-template">
            <tr>
                <td><?php select_inline('detalle[__IDX__][producto_id]', $productos, 'id', 'nombre', 'data-role="producto"'); ?></td>
                <td><?php select_inline('detalle[__IDX__][lote_id]', [], 'id', 'codigo_lote', 'data-role="lote"', 'Sin lote', false); ?></td>
                <td><?php select_inline('detalle[__IDX__][unidad_id]', $unidades, 'id', 'nombre'); ?></td>
                <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="detalle[__IDX__][cantidad]" required></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line-btn">Quitar</button></td>
            </tr>
        </template>
    </div>

    <div class="col-12"><button class="btn btn-primary"><?= $editing ? 'Actualizar borrador' : 'Guardar borrador' ?></button></div>
</form>
<script>
window.BP_LOTES = <?= json_encode($lotes, JSON_UNESCAPED_UNICODE) ?>;
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: true};
</script>
<script src="<?= url('/assets/js/forms.js') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
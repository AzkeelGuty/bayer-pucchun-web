<?php require base_path('app/Views/components/form_fields.php'); ?>
<h2>Nuevo stock</h2>
<p class="text-muted">Complete los datos y agregue las líneas de detalle. El registro se guardará como <strong>Borrador</strong>.</p><form method="post" action="<?= url('/stock/guardar') ?>" class="row g-3">
    <?= csrf_field() ?>
    <?php field('fecha_stock', 'Fecha de stock', 'date'); ?>
    <?php select('almacen_id', 'Almacén', $almacenes, 'id', 'nombre'); ?>
    <div class="col-md-4">
        <label class="form-label">Clave de idempotencia</label>
        <input class="form-control <?= form_error('idempotency_key') ? 'is-invalid' : '' ?>" type="text" name="idempotency_key"
               value="<?= e((string) old('idempotency_key')) ?>" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._:-]{0,63}" required>
        <div class="form-text">Identifica esta carga; reenviar la misma clave con el mismo contenido no la duplica.</div>
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
                <tr>
                    <td><?php select_inline('detalle[0][producto_id]', $productos, 'id', 'nombre', 'data-role="producto"'); ?></td>
                    <td><?php select_inline('detalle[0][lote_id]', [], 'id', 'codigo_lote', 'data-role="lote"', 'Sin lote', false); ?></td>
                    <td><?php select_inline('detalle[0][unidad_id]', $unidades, 'id', 'nombre'); ?></td>
                    <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="detalle[0][cantidad]" required></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line-btn">Quitar</button></td>
                </tr>
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

    <div class="col-12"><button class="btn btn-primary">Guardar borrador</button></div>
</form>
<script>
window.BP_LOTES = <?= json_encode($lotes, JSON_UNESCAPED_UNICODE) ?>;
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: true};
</script>
<script src="<?= url('/assets/js/forms.js') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
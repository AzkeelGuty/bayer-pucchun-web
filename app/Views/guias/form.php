<?php
require base_path('app/Views/components/form_fields.php');
$editing = isset($record);
$detailRows = $editing ? $record['details'] : [['producto_id' => '', 'unidad_id' => '', 'cantidad' => '']];
?>
<h2><?= $editing ? 'Editar guía (borrador)' : 'Nueva guía' ?></h2>
<p class="text-muted">Complete los datos y agregue las líneas de detalle. <?= $editing ? 'Los cambios se guardan en <strong>Borrador</strong>.' : 'El registro se guardará como <strong>Borrador</strong>.' ?></p>
<form method="post" action="<?= url($editing ? '/guias/actualizar' : '/guias/guardar') ?>" class="row g-3">
    <?= csrf_field() ?>
    <?php if ($editing): ?>
        <input type="hidden" name="id" value="<?= $record['header']['id'] ?>">
        <input type="hidden" name="version" value="<?= $record['header']['version'] ?>">
    <?php endif; ?>
    <?php field('numero', 'Número (serie-correlativo, ej. T001-000001)'); ?>
    <?php field('fecha', 'Fecha', 'date'); ?>
    <div class="col-md-4"></div>

    <?php select('cliente_id', 'Cliente', $clientes, 'id', 'razon_social'); ?>
    <?php select('vendedor_id', 'Vendedor', $vendedores, 'id', 'nombre'); ?>
    <?php select('sucursal_id', 'Sucursal', $sucursales, 'id', 'nombre'); ?>

    <div class="col-12" data-ubigeo-scope>
        <h6 class="mt-2">Ubicación (opcional en borrador; completa o vacía)</h6>
        <div class="row g-3">
            <?php select('departamento_id', 'Departamento', $departamentos, 'id', 'nombre', false, 'data-role="departamento" data-old="' . e((string) old('departamento_id')) . '"'); ?>
            <?php select('provincia_id', 'Provincia', [], 'id', 'nombre', false, 'data-role="provincia" data-old="' . e((string) old('provincia_id')) . '"'); ?>
            <?php select('distrito_id', 'Distrito', [], 'id', 'nombre', false, 'data-role="distrito" data-old="' . e((string) old('distrito_id')) . '"'); ?>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mt-2">
            <h6 class="mb-0">Detalle</h6>
            <button type="button" id="add-line-btn" class="btn btn-sm btn-outline-primary">+ Agregar línea</button>
        </div>
        <table class="table table-sm align-middle" id="detalle-table">
            <thead><tr><th>Producto</th><th>Unidad</th><th style="width:140px">Cantidad</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($detailRows as $i => $line): ?>
                <tr>
                    <td><?php select_inline("detalle[$i][producto_id]", $productos, 'id', 'nombre', 'data-role="producto"', 'Seleccione…', true, (string) $line['producto_id']); ?></td>
                    <td><?php select_inline("detalle[$i][unidad_id]", $unidades, 'id', 'nombre', '', 'Seleccione…', true, (string) $line['unidad_id']); ?></td>
                    <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" name="detalle[<?= $i ?>][cantidad]" value="<?= e($line['cantidad']) ?>" required></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line-btn">Quitar</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <template id="detalle-row-template">
            <tr>
                <td><?php select_inline('detalle[__IDX__][producto_id]', $productos, 'id', 'nombre', 'data-role="producto"'); ?></td>
                <td><?php select_inline('detalle[__IDX__][unidad_id]', $unidades, 'id', 'nombre'); ?></td>
                <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" name="detalle[__IDX__][cantidad]" required></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line-btn">Quitar</button></td>
            </tr>
        </template>
    </div>

    <div class="col-12"><button class="btn btn-primary"><?= $editing ? 'Actualizar borrador' : 'Guardar borrador' ?></button></div>
</form>
<script>
window.BP_GEO = <?= json_encode(['provincias' => $provincias, 'distritos' => $distritos], JSON_UNESCAPED_UNICODE) ?>;
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: false};
</script>
<script src="<?= url('/assets/js/forms.js') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
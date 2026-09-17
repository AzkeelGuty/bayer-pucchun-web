<?php
require base_path('app/Views/components/form_fields.php');
$editing = isset($record);
$detailRows = $editing ? $record['details'] : [['producto_id' => '', 'unidad_id' => '', 'cantidad' => '']];
?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">GUÍAS · <?= $editing ? 'EDICIÓN' : 'CAPTURA' ?></div>
        <h1 class="page-title"><?= $editing ? 'Editar borrador' : 'Nueva guía' ?></h1>
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
            <?php field('numero', 'Número (serie-correlativo, ej. T001-000001)'); ?>
            <?php field('fecha', 'Fecha', 'date'); ?>
            <div class="col-md-4"></div>

            <?php select('cliente_id', 'Cliente', $clientes, 'id', 'razon_social'); ?>
            <?php select('vendedor_id', 'Vendedor', $vendedores, 'id', 'nombre'); ?>
            <?php select('sucursal_id', 'Sucursal', $sucursales, 'id', 'nombre'); ?>

            <div class="col-12" data-ubigeo-scope>
                <h2 class="h6 mt-2">Ubicación (opcional en borrador; completa o vacía)</h2>
                <div class="row g-3">
                    <?php select('departamento_id', 'Departamento', $departamentos, 'id', 'nombre', false, 'data-role="departamento" data-old="' . e((string) old('departamento_id')) . '"'); ?>
                    <?php select('provincia_id', 'Provincia', [], 'id', 'nombre', false, 'data-role="provincia" data-old="' . e((string) old('provincia_id')) . '"'); ?>
                    <?php select('distrito_id', 'Distrito', [], 'id', 'nombre', false, 'data-role="distrito" data-old="' . e((string) old('distrito_id')) . '"'); ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3">
            <h2 class="h5 mb-0">Detalle</h2>
            <button type="button" id="add-line-btn" class="btn btn-outline-primary">+ Agregar línea</button>
        </div>
        <div class="table-responsive">
            <table class="table app-table align-middle" id="detalle-table">
                <caption class="visually-hidden">Detalle de la guía</caption>
                <thead><tr><th>Producto</th><th>Unidad</th><th style="width:140px">Cantidad</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($detailRows as $i => $line): ?>
                    <tr>
                        <td><?php select_inline("detalle[$i][producto_id]", $productos, 'id', 'nombre', 'data-role="producto"', 'Seleccione…', true, (string) $line['producto_id']); ?></td>
                        <td><?php select_inline("detalle[$i][unidad_id]", $unidades, 'id', 'nombre', '', 'Seleccione…', true, (string) $line['unidad_id']); ?></td>
                        <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" name="detalle[<?= $i ?>][cantidad]" value="<?= e($line['cantidad']) ?>" required></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small">Agrega las líneas que necesites; la cantidad debe ser mayor que cero.</p>
        <template id="detalle-row-template">
            <tr>
                <td><?php select_inline('detalle[__IDX__][producto_id]', $productos, 'id', 'nombre', 'data-role="producto"'); ?></td>
                <td><?php select_inline('detalle[__IDX__][unidad_id]', $unidades, 'id', 'nombre'); ?></td>
                <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" name="detalle[__IDX__][cantidad]" required></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
            </tr>
        </template>
    </div>
    <div class="card-footer d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Actualizar borrador' : 'Guardar borrador' ?></button>
        <a class="btn btn-outline-primary" href="<?= url('/guias') ?>">Cancelar</a>
    </div>
</form>
<script>
window.BP_GEO = <?= json_encode(['provincias' => $provincias, 'distritos' => $distritos], JSON_UNESCAPED_UNICODE) ?>;
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: false};
</script>
<script src="<?= url('/assets/js/forms.js') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
<?php
require base_path('app/Views/components/form_fields.php');
$editing = isset($record);
$detailRows = $editing ? $record['details'] : [['producto_id' => '', 'unidad_id' => '', 'cantidad' => '']];
$numberMode = $editing ? 'manual' : ((string)old('number_mode') === 'manual' ? 'manual' : 'auto');
$numberValue = $editing
    ? (string)old('numero')
    : ($numberMode === 'manual' ? (string)old('numero') : (string)($autoNumber ?? ''));
$dateValue = (string)old('fecha');
if (!$editing && $dateValue === '') $dateValue = (string)($defaultDate ?? date('Y-m-d'));
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
            <div class="col-md-4">
                <label class="form-label" for="guide-number">Número</label>
                <?php if($editing): ?>
                    <input class="form-control <?=form_error('numero')?'is-invalid':''?>" id="guide-number" name="numero" maxlength="25" value="<?=e($numberValue)?>" required>
                    <div class="invalid-feedback"><?=e(form_error('numero'))?></div>
                <?php else: ?>
                    <input type="hidden" name="number_mode" value="<?=e($numberMode)?>" data-number-mode>
                    <input class="form-control <?=form_error('numero')?'is-invalid':''?>" id="guide-number" name="numero" maxlength="25" value="<?=e($numberValue)?>" data-auto-number="<?=e((string)($autoNumber??''))?>" data-number-input <?= $numberMode==='auto'?'readonly':'' ?> required>
                    <div class="invalid-feedback"><?=e(form_error('numero'))?></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="guide-number-manual" data-number-manual <?= $numberMode==='manual'?'checked':'' ?>>
                        <label class="form-check-label small" for="guide-number-manual">Ingresar número externo/manual</label>
                    </div>
                    <div class="form-text" data-number-help><?= $numberMode==='auto'?'El correlativo se genera automáticamente al guardar.':'Modo manual para una guía que ya existe fuera del sistema.' ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="guide-date">Fecha</label>
                <input class="form-control <?=form_error('fecha')?'is-invalid':''?>" id="guide-date" type="date" name="fecha" value="<?=e($dateValue)?>" required>
                <div class="invalid-feedback"><?=e(form_error('fecha'))?></div>
            </div>
            <div class="col-md-4"></div>

            <?php select('cliente_id', 'Cliente', $clientes, 'id', 'razon_social', true, 'data-role="cliente"'); ?>
            <?php select('vendedor_id', 'Vendedor', $vendedores, 'id', 'nombre'); ?>
            <?php select('sucursal_id', 'Sucursal', $sucursales, 'id', 'nombre'); ?>

            <div class="col-12" data-ubigeo-scope>
                <h2 class="h6 mt-2">Ubicación (se completa sola al elegir el Cliente; puedes ajustarla)</h2>
                <div class="row g-3">
                    <?php select('departamento_id', 'Departamento', $departamentos, 'id', 'nombre', false, 'data-role="departamento" data-old="' . e((string) old('departamento_id')) . '"'); ?>
                    <?php select('provincia_id', 'Provincia', [], 'id', 'nombre', false, 'data-role="provincia" data-old="' . e((string) old('provincia_id')) . '"'); ?>
                    <?php select('distrito_id', 'Distrito', [], 'id', 'nombre', false, 'data-role="distrito" data-old="' . e((string) old('distrito_id')) . '"'); ?>
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
                        <td><?php select_inline("detalle[$i][producto_id]", $productos, 'id', 'nombre', 'data-role="producto"', 'Seleccione…', true, (string) $line['producto_id']); ?></td>
                        <td><?php select_inline("detalle[$i][unidad_id]", $unidades, 'id', 'nombre', '', 'Seleccione…', true, (string) $line['unidad_id']); ?></td>
                        <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" name="detalle[<?= $i ?>][cantidad]" value="<?= e($line['cantidad']) ?>" required></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" aria-label="Quitar línea">Quitar</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small">Añade las líneas que necesites. Al elegir un producto, su unidad base se completa automáticamente.</p>
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
window.BP_CLIENTES = <?= json_encode(index_by($clientes, 'id'), JSON_UNESCAPED_UNICODE) ?>;
window.BP_PRODUCTS = <?= json_encode(index_by($productos, 'id'), JSON_UNESCAPED_UNICODE) ?>;
window.BP_DETAIL_REPEATER = {tableId: 'detalle-table', templateId: 'detalle-row-template', addButtonId: 'add-line-btn', hasLote: false};
</script>
<script src="<?= url('/assets/js/forms.js?v=4') ?>"></script>
<?php unset($_SESSION['_old'], $_SESSION['_errors']); ?>
<div class="d-flex justify-content-between">
    <h2>Stock</h2>
    <?php if (has_role('ADMIN', 'DIGITADOR', 'SUPERVISOR')): ?><a class="btn btn-primary" href="<?= url('/stock/nuevo') ?>">Nuevo</a><?php endif; ?>
</div>
<p class="text-muted">Listado de cargas de stock. Estados: Borrador, Validado, Publicado, Observado, Anulado.</p>

<form method="get" action="<?= url('/stock') ?>" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label mb-0">Estado</label>
        <select name="estado_registro" class="form-select form-select-sm">
            <option value="">Todos</option>
            <?php foreach (['BORRADOR', 'VALIDADO', 'PUBLICADO', 'OBSERVADO', 'ANULADO'] as $estado): ?>
                <option value="<?= $estado ?>" <?= ($filters['estado_registro'] ?? '') === $estado ? 'selected' : '' ?>><?= $estado ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">Desde</label>
        <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= e($filters['fecha_desde'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">Hasta</label>
        <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= e($filters['fecha_hasta'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">Almacén</label>
        <select name="almacen_id" class="form-select form-select-sm">
            <option value="">Todos</option>
            <?php foreach ($almacenes as $a): ?>
                <option value="<?= $a['id'] ?>" <?= (string) ($filters['almacen_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div>
    <?php if ($filters): ?><div class="col-auto"><a class="btn btn-sm btn-link" href="<?= url('/stock') ?>">Limpiar</a></div><?php endif; ?>
</form>

<div class="table-responsive">
    <table class="table table-striped">
        <thead><tr><th>Fecha</th><th>Almacén</th><th>Items</th><th>Cantidad</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['fecha_stock']) ?></td>
                <td><?= e($r['almacen']) ?></td>
                <td><?= e($r['items']) ?></td>
                <td><?= e($r['cantidad']) ?></td>
                <td><?= estado_badge($r['estado_registro']) ?></td>
                <td class="d-flex gap-2 align-items-center">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/stock/ver?id=' . $r['id']) ?>">Ver</a>
                    <?php if (has_role('ADMIN', 'SUPERVISOR') && in_array($r['estado_registro'], ['BORRADOR', 'VALIDADO'], true)): ?>
                        <form method="post" action="<?= url('/stock/estado') ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <select name="status" class="form-select form-select-sm">
                                <option value="VALIDADO" <?= $r['estado_registro'] === 'BORRADOR' ? '' : 'disabled' ?>>Validar</option>
                                <option value="PUBLICADO" <?= $r['estado_registro'] === 'VALIDADO' ? '' : 'disabled' ?>>Publicar</option>
                            </select>
                            <button class="btn btn-sm btn-success">Aplicar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<section class="module-header">
    <div>
        <div class="page-eyebrow">CAPTURA Y CONTROL</div>
        <h1 class="page-title">Stock</h1>
        <p class="page-subtitle">Consulta tus cargas de stock y continúa su revisión.</p>
    </div>
    <?php if (has_role('ADMIN', 'DIGITADOR')): ?><a class="btn btn-primary" href="<?= url('/stock/nuevo') ?>">+ Nuevo stock</a><?php endif; ?>
</section>

<form method="get" action="<?= url('/stock') ?>" class="card card-body mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label" for="stock-estado">Estado</label>
            <select class="form-select" id="stock-estado" name="estado_registro">
                <option value="">Todos los estados</option>
                <?php foreach (['BORRADOR', 'VALIDADO', 'PUBLICADO', 'OBSERVADO', 'ANULADO'] as $estado): ?>
                    <option value="<?= $estado ?>" <?= ($filters['estado_registro'] ?? '') === $estado ? 'selected' : '' ?>><?= $estado ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="stock-desde">Desde</label>
            <input type="date" class="form-control" id="stock-desde" name="fecha_desde" value="<?= e($filters['fecha_desde'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="stock-hasta">Hasta</label>
            <input type="date" class="form-control" id="stock-hasta" name="fecha_hasta" value="<?= e($filters['fecha_hasta'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="stock-almacen">Almacén</label>
            <select class="form-select" id="stock-almacen" name="almacen_id">
                <option value="">Todos</option>
                <?php foreach ($almacenes as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= (string) ($filters['almacen_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary">Filtrar</button>
            <?php if ($filters): ?><a class="btn btn-outline-primary" href="<?= url('/stock') ?>">Limpiar</a><?php endif; ?>
        </div>
    </div>
</form>

<div class="card"><div class="card-body">
    <p class="text-muted small"><?= count($rows) ?> registros de stock encontrados</p>
    <div class="table-responsive">
        <table class="table app-table align-middle">
            <thead><tr><th>Fecha</th><th>Almacén</th><th>Items</th><th>Cantidad</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['fecha_stock']) ?></td>
                    <td><?= e($r['almacen']) ?></td>
                    <td><?= e($r['items']) ?></td>
                    <td><?= e($r['cantidad']) ?></td>
                    <td><span class="badge-status status-<?= e(strtolower($r['estado_registro'])) ?>"><?= e($r['estado_registro']) ?></span></td>
                    <td>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('/stock/ver?id=' . $r['id']) ?>">Ver</a>
                            <?php if (has_role('ADMIN', 'DIGITADOR') && $r['estado_registro'] === 'BORRADOR'): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('/stock/editar?id=' . $r['id']) ?>">Editar</a>
                            <?php endif; ?>
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
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <strong>No se encontraron registros de stock</strong>
                        <span><?= $filters ? 'Prueba otros filtros o limpia la búsqueda.' : 'Los nuevos registros aparecerán aquí.' ?></span>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div></div>

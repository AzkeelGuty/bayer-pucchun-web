<section class="module-header">
    <div>
        <div class="page-eyebrow">CAPTURA Y CONTROL</div>
        <h1 class="page-title">Guías</h1>
        <p class="page-subtitle">Consulta tus guías de remisión y continúa su revisión.</p>
    </div>
    <?php if (has_role('ADMIN', 'DIGITADOR')): ?><a class="btn btn-primary" href="<?= url('/guias/nuevo') ?>">+ Nueva guía</a><?php endif; ?>
</section>

<form method="get" action="<?= url('/guias') ?>" class="card card-body mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label" for="guia-estado">Estado</label>
            <select class="form-select" id="guia-estado" name="estado_registro">
                <option value="">Todos los estados</option>
                <?php foreach (['BORRADOR', 'VALIDADO', 'PUBLICADO', 'OBSERVADO', 'ANULADO'] as $estado): ?>
                    <option value="<?= $estado ?>" <?= ($filters['estado_registro'] ?? '') === $estado ? 'selected' : '' ?>><?= $estado ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="guia-desde">Desde</label>
            <input type="date" class="form-control" id="guia-desde" name="fecha_desde" value="<?= e($filters['fecha_desde'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="guia-hasta">Hasta</label>
            <input type="date" class="form-control" id="guia-hasta" name="fecha_hasta" value="<?= e($filters['fecha_hasta'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="guia-sucursal">Sucursal</label>
            <select class="form-select" id="guia-sucursal" name="sucursal_id">
                <option value="">Todas</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (string) ($filters['sucursal_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-primary">Filtrar</button>
            <?php if ($filters): ?><a class="btn btn-outline-primary" href="<?= url('/guias') ?>">Limpiar</a><?php endif; ?>
        </div>
    </div>
</form>

<div class="card"><div class="card-body">
    <p class="text-muted small"><?= count($rows) ?> guías encontradas</p>
    <div class="table-responsive">
        <table class="table app-table align-middle">
            <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Sucursal</th><th>Items</th><th>Cantidad</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['numero']) ?></strong></td>
                    <td><?= e($r['fecha']) ?></td>
                    <td><?= e($r['cliente']) ?></td>
                    <td><?= e($r['vendedor']) ?></td>
                    <td><?= e($r['sucursal']) ?></td>
                    <td><?= e($r['items']) ?></td>
                    <td><?= e($r['cantidad']) ?></td>
                    <td><span class="badge-status status-<?= e(strtolower($r['estado_registro'])) ?>"><?= e($r['estado_registro']) ?></span></td>
                    <td>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('/guias/ver?id=' . $r['id']) ?>">Ver</a>
                            <?php if (has_role('ADMIN', 'DIGITADOR') && $r['estado_registro'] === 'BORRADOR'): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('/guias/editar?id=' . $r['id']) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (has_role('ADMIN', 'SUPERVISOR') && in_array($r['estado_registro'], ['BORRADOR', 'VALIDADO'], true)): ?>
                                <form method="post" action="<?= url('/guias/estado') ?>" class="d-flex gap-1">
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
                <tr><td colspan="9">
                    <div class="empty-state">
                        <strong>No se encontraron guías</strong>
                        <span><?= $filters ? 'Prueba otros filtros o limpia la búsqueda.' : 'Los nuevos registros aparecerán aquí.' ?></span>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div></div>

<div class="d-flex justify-content-between">
    <h2>Guías</h2>
    <?php if (has_role('ADMIN', 'DIGITADOR', 'SUPERVISOR')): ?><a class="btn btn-primary" href="<?= url('/guias/nuevo') ?>">Nuevo</a><?php endif; ?>
</div>
<p class="text-muted">Listado de guías de remisión. Estados: Borrador, Validado, Publicado, Observado, Anulado.</p>

<form method="get" action="<?= url('/guias') ?>" class="row g-2 align-items-end mb-3">
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
        <label class="form-label mb-0">Sucursal</label>
        <select name="sucursal_id" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (string) ($filters['sucursal_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div>
    <?php if ($filters): ?><div class="col-auto"><a class="btn btn-sm btn-link" href="<?= url('/guias') ?>">Limpiar</a></div><?php endif; ?>
</form>

<?php $cols = ['numero' => 'Número', 'fecha' => 'Fecha', 'cliente' => 'Cliente', 'vendedor' => 'Vendedor', 'sucursal' => 'Sucursal', 'items' => 'Items', 'cantidad' => 'Cantidad']; ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead><tr><?php foreach ($cols as $l): ?><th><?= $l ?></th><?php endforeach; ?><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach (array_keys($cols) as $c): ?><td><?= e($r[$c]) ?></td><?php endforeach; ?>
                <td><?= estado_badge($r['estado_registro']) ?></td>
                <td class="d-flex gap-2 align-items-center">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/guias/ver?id=' . $r['id']) ?>">Ver</a>
                    <?php if (has_role('ADMIN', 'DIGITADOR', 'SUPERVISOR') && $r['estado_registro'] === 'BORRADOR'): ?>
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
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
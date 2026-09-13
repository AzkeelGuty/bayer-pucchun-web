<?php $h = $record['header']; ?>
<div class="d-flex justify-content-between align-items-center">
    <h2>Guía <?= e($h['numero']) ?></h2>
    <?= estado_badge($h['estado_registro']) ?>
</div>
<p class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/guias') ?>">&larr; Volver al listado</a>
    <?php if (has_role('ADMIN', 'DIGITADOR', 'SUPERVISOR') && $h['estado_registro'] === 'BORRADOR'): ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/guias/editar?id=' . $h['id']) ?>">Editar</a>
    <?php endif; ?>
</p>

<div class="card mb-3"><div class="card-body">
    <h6 class="card-title">Cabecera</h6>
    <div class="row">
        <div class="col-md-3"><strong>Fecha</strong><div><?= e($h['fecha']) ?></div></div>
        <div class="col-md-3"><strong>Cliente</strong><div><?= e($clientes[$h['cliente_id']]['razon_social'] ?? $h['cliente_id']) ?></div></div>
        <div class="col-md-3"><strong>Vendedor</strong><div><?= e($vendedores[$h['vendedor_id']]['nombre'] ?? $h['vendedor_id']) ?></div></div>
        <div class="col-md-3"><strong>Sucursal</strong><div><?= e($sucursales[$h['sucursal_id']]['nombre'] ?? $h['sucursal_id']) ?></div></div>
    </div>
    <div class="row mt-3">
        <div class="col-md-3"><strong>Departamento</strong><div><?= e($departamentos[$h['departamento_id']]['nombre'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>Provincia</strong><div><?= e($provincias[$h['provincia_id']]['nombre'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>Distrito</strong><div><?= e($distritos[$h['distrito_id']]['nombre'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>Versión</strong><div><?= e($h['version']) ?></div></div>
    </div>
    <?php if ($h['estado_registro'] === 'OBSERVADO' && $h['observation_reason']): ?>
        <div class="alert alert-warning mt-3 mb-0"><strong>Motivo de observación:</strong> <?= e($h['observation_reason']) ?></div>
    <?php endif; ?>
    <?php if ($h['estado_registro'] === 'ANULADO' && $h['cancellation_reason']): ?>
        <div class="alert alert-danger mt-3 mb-0"><strong>Motivo de anulación:</strong> <?= e($h['cancellation_reason']) ?></div>
    <?php endif; ?>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h6 class="card-title">Detalle</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Producto</th><th>Unidad</th><th>Cantidad</th></tr></thead>
            <tbody>
            <?php foreach ($record['details'] as $line): ?>
                <tr>
                    <td><?= e($productos[$line['producto_id']]['nombre'] ?? $line['producto_id']) ?></td>
                    <td><?= e($unidades[$line['unidad_id']]['nombre'] ?? $line['unidad_id']) ?></td>
                    <td><?= e($line['cantidad']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>

<?php if (has_role('ADMIN', 'SUPERVISOR')): ?>
<div class="card"><div class="card-body">
    <h6 class="card-title">Workflow</h6>
    <p class="text-muted small">Estas acciones dependen del Service de estados (en preparación); hasta entonces el backend responderá con un aviso temporal.</p>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($h['estado_registro'] === 'BORRADOR'): ?>
            <form method="post" action="<?= url('/guias/estado') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="status" value="VALIDADO"><button class="btn btn-sm btn-info">Validar</button></form>
        <?php endif; ?>
        <?php if ($h['estado_registro'] === 'VALIDADO'): ?>
            <form method="post" action="<?= url('/guias/estado') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="status" value="PUBLICADO"><button class="btn btn-sm btn-success">Publicar</button></form>
        <?php endif; ?>
    </div>
</div></div>
<?php endif; ?>
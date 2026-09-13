<?php require_once base_path('app/Views/components/workflow_control.php'); ?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">DESPACHO Y CONTROL</div>
        <h1 class="page-title">Guías de remisión</h1>
        <p class="page-subtitle">Seguimiento de guías, cantidades y estado de publicación.</p>
    </div>
    <?php if(has_role('ADMIN','DIGITADOR')): ?>
        <a class="btn btn-primary" href="<?=url('/guias/nuevo')?>">Nueva guía</a>
    <?php endif; ?>
</section>

<div class="card">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><strong>No hay guías registradas.</strong><span>Los nuevos registros aparecerán aquí.</span></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0">
                    <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Sucursal</th><th>Cantidad</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><strong><?=e($r['numero'])?></strong></td>
                            <td><?=e($r['fecha'])?></td>
                            <td><?=e($r['cliente'])?></td>
                            <td><?=e($r['vendedor'])?></td>
                            <td><?=e($r['sucursal'])?></td>
                            <td><?=e($r['cantidad'])?></td>
                            <td><span class="badge-status status-<?=e(strtolower($r['estado_registro']))?>"><?=e($r['estado_registro'])?></span></td>
                            <td>
    <div class="row-actions">
        <?php if(($r['estado_registro']??'')==='BORRADOR' && has_role('ADMIN','DIGITADOR')): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?=url('/guias/editar?id='.urlencode((string)$r['id']))?>">Editar</a>
            <form method="post" action="<?=url('/guias/eliminar')?>" onsubmit="return confirm('¿Eliminar este borrador?');">
                <?=csrf_field()?>
                <input type="hidden" name="id" value="<?=e($r['id'])?>">
                <input type="hidden" name="version" value="<?=e($r['version'])?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
            </form>
        <?php endif; ?>
        <?php workflow_control('guias',$r); ?>
    </div>
</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

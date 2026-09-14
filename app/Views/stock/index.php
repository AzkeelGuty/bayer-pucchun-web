<?php require_once base_path('app/Views/components/workflow_control.php'); ?>
<section class="module-header">
    <div>
        <div class="page-eyebrow">INVENTARIO</div>
        <h1 class="page-title">Stock</h1>
        <p class="page-subtitle">Snapshots de inventario por almacén y fecha.</p>
    </div>
    <?php if(has_role('ADMIN','DIGITADOR')): ?>
        <a class="btn btn-primary btn-with-icon" href="<?=url('/stock/nuevo')?>"><i class="bi bi-plus-circle"></i><span>Nuevo stock</span></a>
    <?php endif; ?>
</section>

<div class="card">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><strong>No hay stock registrado.</strong><span>Los nuevos snapshots aparecerán aquí.</span></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0">
                    <thead><tr><th>Fecha</th><th>Almacén</th><th>Ítems</th><th>Cantidad</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><strong><?=e($r['fecha_stock'])?></strong></td>
                            <td><?=e($r['almacen'])?></td>
                            <td><?=e($r['items'])?></td>
                            <td><?=e($r['cantidad'])?></td>
                            <td><span class="badge-status status-<?=e(strtolower($r['estado_registro']))?>"><?=e($r['estado_registro'])?></span></td>
                            <td>
    <div class="row-actions">
        <?php if(($r['estado_registro']??'')==='BORRADOR' && has_role('ADMIN','DIGITADOR')): ?>
            <a class="btn btn-sm btn-outline-primary btn-with-icon" href="<?=url('/stock/editar?id='.urlencode((string)$r['id']))?>"><i class="bi bi-pencil-square"></i><span>Editar</span></a>
            <form method="post" action="<?=url('/stock/eliminar')?>" onsubmit="return confirm('¿Eliminar este borrador?');">
                <?=csrf_field()?>
                <input type="hidden" name="id" value="<?=e($r['id'])?>">
                <input type="hidden" name="version" value="<?=e($r['version'])?>">
                <button class="btn btn-sm btn-outline-danger btn-with-icon" type="submit"><i class="bi bi-trash3"></i><span>Eliminar</span></button>
            </form>
        <?php endif; ?>
        <?php workflow_control('stock',$r); ?>
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

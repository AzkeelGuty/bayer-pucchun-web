<?php require_once base_path('app/Views/components/workflow_control.php'); ?>
<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-patch-check"></i> CALIDAD DEL DATO</div>
        <h1 class="page-title">Validación y publicación</h1>
        <p class="page-subtitle">Revisa y decide sin pasos innecesarios. Los observados vuelven al digitador para su corrección.</p>
    </div>
</section>

<div class="workflow-summary workflow-summary-four">
    <div><span><i class="bi bi-file-earmark"></i> Borrador</span><strong><?=count(array_filter($rows,fn($r)=>$r['estado_registro']==='BORRADOR'))?></strong></div>
    <i>→</i>
    <div><span><i class="bi bi-check2-circle"></i> Validado</span><strong><?=count(array_filter($rows,fn($r)=>$r['estado_registro']==='VALIDADO'))?></strong></div>
    <i>→</i>
    <div><span><i class="bi bi-cloud-check"></i> Publicación</span><strong>1 clic</strong></div>
    <i>·</i>
    <div><span><i class="bi bi-exclamation-circle"></i> Observado</span><strong><?=count(array_filter($rows,fn($r)=>$r['estado_registro']==='OBSERVADO'))?></strong></div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state">
                <strong>Sin pendientes.</strong>
                <span>No hay registros esperando revisión.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0">
                    <thead>
                        <tr><th>Conjunto de datos</th><th>Registro</th><th>Fecha</th><th>Referencia</th><th>Estado</th><th>Revisión</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><strong><?=e($r['dataset'])?></strong></td>
                            <td>#<?=e($r['id'])?></td>
                            <td><?=e($r['fecha'] ?? $r['fecha_stock'] ?? '—')?></td>
                            <td><?=e($r['numero'] ?? $r['almacen'] ?? '—')?></td>
                            <td><span class="badge-status status-<?=e(strtolower($r['estado_registro']))?>"><?=e($r['estado_registro'])?></span></td>
                            <td>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?=url('/'.$r['module'].'/ver?id='.$r['id'])?>">Ver</a>
                                    <?php workflow_control($r['module'],$r,'/validacion'); ?>
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

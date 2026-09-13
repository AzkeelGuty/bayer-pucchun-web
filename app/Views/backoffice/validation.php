<section class="page-header">
    <div>
        <div class="page-eyebrow">CALIDAD DEL DATO</div>
        <h1 class="page-title">Validación y publicación</h1>
        <p class="page-subtitle">Cola supervisada de registros en borrador, validados u observados antes de su publicación.</p>
    </div>
</section>
<div class="workflow-summary">
    <div><span>Borrador</span><strong><?=count(array_filter($rows,fn($r)=>$r['estado_registro']==='BORRADOR'))?></strong></div>
    <i>→</i>
    <div><span>Validado</span><strong><?=count(array_filter($rows,fn($r)=>$r['estado_registro']==='VALIDADO'))?></strong></div>
    <i>→</i>
    <div><span>Publicado</span><strong>Datos disponibles</strong></div>
</div>
<div class="card">
<div class="card-body p-0">
<?php if(!$rows): ?><div class="empty-state"><strong>Sin pendientes.</strong><span>No hay registros esperando revisión.</span></div>
<?php else: ?><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Dataset</th><th>Registro</th><th>Fecha</th><th>Estado</th><th>Referencia</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=e($r['dataset'])?></td><td>#<?=e($r['id'])?></td><td><?=e($r['fecha'] ?? $r['fecha_stock'] ?? '—')?></td><td><span class="badge-status status-<?=e(strtolower($r['estado_registro']))?>"><?=e($r['estado_registro'])?></span></td><td><?=e($r['numero'] ?? $r['almacen'] ?? '—')?></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
</div></div>
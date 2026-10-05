<?php require_once base_path('app/Views/components/workflow_control.php'); ?>
<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-patch-check"></i> CALIDAD DEL DATO</div>
        <h1 class="page-title">Validación y publicación</h1>
        <p class="page-subtitle">Revisión supervisada antes de poner la información a disposición de Bayer.</p>
    </div>
</section>

<div class="workflow-summary">
    <div>
        <span><i class="bi bi-file-earmark"></i> Borrador</span>
        <strong><?=e((string)($validationCounts['BORRADOR']??0))?></strong>
    </div>
    <i>→</i>
    <div>
        <span><i class="bi bi-check2-circle"></i> Validado</span>
        <strong><?=e((string)($validationCounts['VALIDADO']??0))?></strong>
    </div>
    <i>→</i>
    <div>
        <span><i class="bi bi-cloud-check"></i> Publicado</span>
        <strong>Disponible</strong>
    </div>
</div>

<?php
$firstRow=$total>0?(($page-1)*$perPage)+1:0;
$lastRow=$total>0?min($total,$page*$perPage):0;
$pageUrl=static fn(int $target): string => url('/validacion'.($target>1?'?page='.$target:''));
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <small class="text-muted"><?=e((string)$total)?> pendientes<?= $total>0 ? ' · Mostrando '.e((string)$firstRow).'–'.e((string)$lastRow) : '' ?></small>
    <small class="text-muted">Página <?=e((string)$page)?> de <?=e((string)$pages)?></small>
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
                        <tr>
                            <th>Conjunto de datos</th>
                            <th>Registro</th>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Estado</th>
                            <th>Acción supervisada</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><strong><?=e($r['dataset'])?></strong></td>
                            <td>#<?=e($r['id'])?></td>
                            <td><?=e($r['fecha'] ?? $r['fecha_stock'] ?? '—')?></td>
                            <td><?=e($r['numero'] ?? $r['almacen'] ?? '—')?></td>
                            <td><span class="badge-status status-<?=e(strtolower($r['estado_registro']))?>"><?=e($r['estado_registro'])?></span></td>
                            <td><?php workflow_control($r['module'],$r,'/validacion','supervised'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if($pages>1): ?>
    <nav class="catalog-pagination" aria-label="Paginación de validación">
        <a class="btn btn-sm btn-outline-primary <?=$page<=1?'disabled':''?>" href="<?=$page>1?e($pageUrl($page-1)):'#'?>" <?=$page<=1?'aria-disabled="true" tabindex="-1"':''?>><i class="bi bi-chevron-left"></i><span>Anterior</span></a>
        <span>Página <?=e((string)$page)?> de <?=e((string)$pages)?></span>
        <a class="btn btn-sm btn-outline-primary <?=$page>=$pages?'disabled':''?>" href="<?=$page<$pages?e($pageUrl($page+1)):'#'?>" <?=$page>=$pages?'aria-disabled="true" tabindex="-1"':''?>><span>Siguiente</span><i class="bi bi-chevron-right"></i></a>
    </nav>
    <?php endif; ?>
</div>

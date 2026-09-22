<?php
$metrics=[
 ['documentos','Documentos','Total registrados','bi-file-earmark-text','blue'],
 ['guias','Guías de remisión','Total registradas','bi-truck','green'],
 ['stock','Movimientos de stock','Total registrados','bi-box-seam','warning'],
 ['pendientes','Pendientes de revisión','Requieren atención','bi-exclamation-circle','danger'],
];
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-speedometer2"></i> GESTIÓN OPERATIVA</div>
        <h1 class="page-title">Panel Pucchún</h1>
        <p class="page-subtitle">Captura, validación, publicación y seguimiento de la información.</p>
    </div>
    <div class="quick-actions">
        <?php if(has_role('ADMIN','DIGITADOR')): ?><a class="btn btn-outline-primary btn-with-icon" href="<?=url('/documentos/nuevo')?>"><i class="bi bi-file-earmark-plus"></i><span>Nuevo documento</span></a><a class="btn btn-outline-primary btn-with-icon" href="<?=url('/guias/nuevo')?>"><i class="bi bi-truck"></i><span>Nueva guía</span></a><?php endif;?>
        <?php if(has_role('ADMIN','SUPERVISOR')): ?><a class="btn btn-primary btn-with-icon" href="<?=url('/validacion')?>"><i class="bi bi-patch-check"></i><span>Revisar pendientes</span></a><?php endif;?>
    </div>
</section>

<div class="kpi-grid kpi-grid-four">
<?php foreach($metrics as [$key,$label,$note,$icon,$accent]): ?>
<article class="surface-card kpi-card kpi-accent-<?=e($accent)?>">
    <div class="kpi-card-head"><span class="kpi-mini-icon"><i class="bi <?=e($icon)?>"></i></span><span class="kpi-label"><?=e($label)?></span></div>
    <div class="kpi-value"><?=number_format((int)($kpis[$key]??0))?></div>
    <div class="kpi-note"><?=e($note)?></div>
</article>
<?php endforeach;?>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <article class="card chart-card h-100"><div class="card-body"><h5><i class="bi bi-graph-up me-2"></i>Registros publicados por periodo</h5><div class="chart-subtitle">Tendencia de información disponible para analítica y entrega.</div><div class="chart-wrap"><canvas id="salesChart"></canvas></div></div></article>
    </div>
    <div class="col-xl-5">
        <article class="card chart-card h-100"><div class="card-body"><h5><i class="bi bi-pie-chart me-2"></i>Estado de los registros</h5><div class="chart-subtitle">Distribución del ciclo Borrador → Validado → Publicado.</div><div class="chart-wrap"><canvas id="statusChart"></canvas></div></div></article>
    </div>
</div>

<div class="row g-4 mt-0">
    <div class="col-xl-7">
        <article class="card h-100"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Últimos registros</strong><a href="<?=url('/documentos')?>" class="small text-decoration-none">Ver operación</a></div><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Estado</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?=e($r['fecha'])?></td><td><?=e($r['tipo'])?></td><td><?=e($r['referencia'])?></td><td><span class="badge-status status-<?=e(strtolower($r['estado']))?>"><?=e($r['estado'])?></span></td></tr><?php endforeach;?></tbody></table></div></article>
    </div>
    <div class="col-xl-5">
        <article class="card chart-card h-100"><div class="card-body"><h5><i class="bi bi-bar-chart me-2"></i>Productos con mayor movimiento</h5><div class="chart-subtitle">Ranking sobre información publicada.</div><div class="chart-wrap chart-wrap-compact"><canvas id="topChart"></canvas></div></div></article>
    </div>
</div>

<script>
window.dashboardData={
 series:<?=json_encode(array_reverse($series),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
 top:<?=json_encode($top,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
 statuses:<?=json_encode($statuses,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>
};
</script>
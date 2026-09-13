<?php
$metrics=[
 ['documentos','Documentos','Total registrados','DC','blue'],
 ['guias','Guías de remisión','Total registradas','GR','green'],
 ['stock','Movimientos de stock','Total registrados','ST','warning'],
 ['pendientes','Pendientes de revisión','Requieren atención','PE','danger'],
];
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow">GESTIÓN OPERATIVA</div>
        <h1 class="page-title">Dashboard Pucchún</h1>
        <p class="page-subtitle">Captura, validación, publicación y seguimiento de la información.</p>
    </div>
    <div class="quick-actions">
        <?php if(has_role('ADMIN','DIGITADOR')): ?><a class="btn btn-outline-primary" href="<?=url('/documentos/nuevo')?>">Nuevo documento</a><a class="btn btn-outline-primary" href="<?=url('/guias/nuevo')?>">Nueva guía</a><?php endif;?>
        <?php if(has_role('ADMIN','SUPERVISOR')): ?><a class="btn btn-primary" href="<?=url('/validacion')?>">Revisar pendientes</a><?php endif;?>
    </div>
</section>

<div class="kpi-grid kpi-grid-four">
<?php foreach($metrics as [$key,$label,$note,$icon,$accent]): ?>
<article class="surface-card kpi-card kpi-accent-<?=e($accent)?>">
    <div class="kpi-card-head"><span class="kpi-mini-icon"><?=e($icon)?></span><span class="kpi-label"><?=e($label)?></span></div>
    <div class="kpi-value"><?=number_format((int)($kpis[$key]??0))?></div>
    <div class="kpi-note"><?=e($note)?></div>
</article>
<?php endforeach;?>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <article class="card chart-card h-100"><div class="card-body"><h5>Registros publicados por periodo</h5><div class="chart-subtitle">Tendencia de información disponible para analítica y entrega.</div><div class="chart-wrap"><canvas id="salesChart"></canvas></div></div></article>
    </div>
    <div class="col-xl-5">
        <article class="card chart-card h-100"><div class="card-body"><h5>Estado de los registros</h5><div class="chart-subtitle">Distribución del ciclo Borrador → Validado → Publicado.</div><div class="chart-wrap"><canvas id="statusChart"></canvas></div></div></article>
    </div>
</div>

<div class="row g-4 mt-0">
    <div class="col-xl-7">
        <article class="card h-100"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Últimos registros</strong><a href="<?=url('/documentos')?>" class="small text-decoration-none">Ver operación</a></div><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Estado</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?=e($r['fecha'])?></td><td><?=e($r['tipo'])?></td><td><?=e($r['referencia'])?></td><td><span class="badge-status status-<?=e(strtolower($r['estado']))?>"><?=e($r['estado'])?></span></td></tr><?php endforeach;?></tbody></table></div></article>
    </div>
    <div class="col-xl-5">
        <article class="card chart-card h-100"><div class="card-body"><h5>Productos con mayor movimiento</h5><div class="chart-subtitle">Ranking sobre información publicada.</div><div class="chart-wrap chart-wrap-compact"><canvas id="topChart"></canvas></div></div></article>
    </div>
</div>

<script>
window.dashboardData={
 series:<?=json_encode(array_reverse($series),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
 top:<?=json_encode($top,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
 statuses:<?=json_encode($statuses,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>
};
</script>
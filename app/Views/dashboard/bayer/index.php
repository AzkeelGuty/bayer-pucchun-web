<?php
$metrics = [
    ['documentos', 'Documentos publicados', 'Información disponible'],
    ['guias', 'Guías publicadas', 'Información disponible'],
    ['stock', 'Stock publicado', 'Información disponible'],
    ['clientes', 'Clientes', 'Maestro publicado'],
    ['productos', 'Productos', 'Maestro publicado'],
];
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow">CONSULTA EXTERNA CONTROLADA</div>
        <h1 class="page-title">Portal Bayer</h1>
        <p class="page-subtitle">Acceso de solo consulta a información validada y publicada. Sin acceso al CRUD interno.</p>
    </div>
    <div class="quick-actions">
        <a class="btn btn-outline-primary" href="<?=url('/bayer/datos?type=documents')?>">Documentos</a>
        <a class="btn btn-outline-primary" href="<?=url('/bayer/datos?type=guides')?>">Guías</a>
        <a class="btn btn-outline-primary" href="<?=url('/bayer/datos?type=stock')?>">Stock</a>
    </div>
</section>

<div class="kpi-grid">
    <?php foreach($metrics as [$key,$label,$note]): if (!isset($kpis[$key])) continue; ?>
        <article class="surface-card kpi-card kpi-accent-green">
            <div class="kpi-label"><?=e($label)?></div>
            <div class="kpi-value"><?=e($kpis[$key])?></div>
            <div class="kpi-note"><?=e($note)?></div>
        </article>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5>Analítica comercial</h5>
                <div class="chart-subtitle">Tendencia basada exclusivamente en registros publicados.</div>
                <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5>Top productos</h5>
                <div class="chart-subtitle">Resumen de los productos con mayor movimiento.</div>
                <div class="chart-wrap"><canvas id="topChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div>
            <h5 class="mb-1">Entrega de información</h5>
            <div class="text-muted small">Consulta y descarga únicamente datos publicados.</div>
        </div>
        <div class="quick-actions">
            <a class="btn btn-outline-primary" href="<?=url('/bayer/datos?type=documents')?>">Vista previa Documentos</a>
            <a class="btn btn-outline-primary" href="<?=url('/bayer/datos?type=guides')?>">Vista previa Guías</a>
            <a class="btn btn-outline-primary" href="<?=url('/bayer/datos?type=stock')?>">Vista previa Stock</a>
        </div>
    </div>
</div>

<script>
window.dashboardData={
    series:<?=json_encode(array_reverse($series), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>,
    top:<?=json_encode($top, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>
};
</script>

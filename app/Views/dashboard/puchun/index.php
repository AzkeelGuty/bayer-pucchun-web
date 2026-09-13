<?php
$metrics = [
    ['documentos', 'Documentos', 'Registros operativos', 'blue'],
    ['guias', 'Guías', 'Despachos registrados', 'green'],
    ['stock', 'Stock', 'Movimientos registrados', 'warning'],
    ['clientes', 'Clientes', 'Maestro comercial', 'blue'],
    ['productos', 'Productos', 'Catálogo maestro', 'green'],
    ['publicados', 'Publicados', 'Disponibles para consulta', 'green'],
];
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow">CONTROL OPERATIVO</div>
        <h1 class="page-title">Dashboard Pucchún</h1>
        <p class="page-subtitle">Seguimiento de captura, validación, publicación y disponibilidad de información.</p>
    </div>
    <div class="quick-actions">
        <a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Documentos</a>
        <a class="btn btn-outline-primary" href="<?=url('/guias')?>">Guías</a>
        <a class="btn btn-outline-primary" href="<?=url('/stock')?>">Stock</a>
    </div>
</section>

<div class="kpi-grid">
    <?php foreach($metrics as [$key,$label,$note,$accent]): ?>
        <article class="surface-card kpi-card kpi-accent-<?=e($accent)?>">
            <div class="kpi-label"><?=e($label)?></div>
            <div class="kpi-value"><?=e($kpis[$key] ?? 0)?></div>
            <div class="kpi-note"><?=e($note)?></div>
        </article>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5>Tendencia de información publicada</h5>
                <div class="chart-subtitle">Evolución de registros disponibles para analítica.</div>
                <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card chart-card h-100">
            <div class="card-body">
                <h5>Productos con mayor movimiento</h5>
                <div class="chart-subtitle">Ranking por cantidad dentro del periodo disponible.</div>
                <div class="chart-wrap"><canvas id="topChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script>
window.dashboardData={
    series:<?=json_encode(array_reverse($series), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>,
    top:<?=json_encode($top, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>
};
</script>

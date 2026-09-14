<?php
$metrics=[
 ['documentos','Documentos publicados','Disponibles para consulta','bi-file-earmark-check'],
 ['guias','Guías publicadas','Disponibles para consulta','bi-truck'],
 ['stock','Movimientos de stock','Disponibles para consulta','bi-box-seam'],
 ['publicados','Total publicado','Fuente de datos compartida','bi-cloud-check'],
];
?>
<section class="page-header portal-page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-patch-check"></i> INFORMACIÓN VALIDADA Y PUBLICADA</div>
        <h1 class="page-title">Dashboard Bayer</h1>
        <p class="page-subtitle">Consulta externa de información publicada por Pucchún.</p>
    </div>
    <div class="portal-updated"><span>Última publicación</span><strong><?=e($lastUpdate??'Sin publicaciones')?></strong></div>
</section>

<div class="kpi-grid kpi-grid-four portal-kpis">
<?php foreach($metrics as [$key,$label,$note,$icon]): ?>
<article class="surface-card kpi-card kpi-accent-green"><div class="kpi-card-head"><span class="kpi-mini-icon"><i class="bi <?=e($icon)?>"></i></span><span class="kpi-label"><?=e($label)?></span></div><div class="kpi-value"><?=number_format((int)($kpis[$key]??0))?></div><div class="kpi-note"><?=e($note)?></div></article>
<?php endforeach;?>
</div>

<div class="row g-4">
<div class="col-xl-7"><article class="card chart-card h-100"><div class="card-body"><h5>Tendencia de registros publicados</h5><div class="chart-subtitle">Evolución del volumen disponible para consulta.</div><div class="chart-wrap"><canvas id="salesChart"></canvas></div></div></article></div>
<div class="col-xl-5"><article class="card chart-card h-100"><div class="card-body"><h5>Distribución de información publicada</h5><div class="chart-subtitle">Documentos, guías y stock disponibles.</div><div class="chart-wrap"><canvas id="distributionChart"></canvas></div></div></article></div>
</div>

<div class="portal-actions-grid mt-4">
<a class="portal-action-card" href="<?=url('/bayer/datos?type=documents')?>"><span><i class="bi bi-file-earmark-text"></i></span><div><strong>Documentos</strong><small>Consultar datos publicados</small></div><b><i class="bi bi-arrow-right"></i></b></a>
<a class="portal-action-card" href="<?=url('/bayer/datos?type=guides')?>"><span><i class="bi bi-truck"></i></span><div><strong>Guías de remisión</strong><small>Consultar datos publicados</small></div><b><i class="bi bi-arrow-right"></i></b></a>
<a class="portal-action-card" href="<?=url('/bayer/datos?type=stock')?>"><span><i class="bi bi-box-seam"></i></span><div><strong>Stock</strong><small>Consultar datos publicados</small></div><b><i class="bi bi-arrow-right"></i></b></a>
<a class="portal-action-card" href="<?=url('/bayer/exportaciones')?>"><span><i class="bi bi-download"></i></span><div><strong>Exportaciones</strong><small>XLSX, CSV, JSON, TXT y PDF</small></div><b><i class="bi bi-arrow-right"></i></b></a>
</div>

<script>
window.dashboardData={
 series:<?=json_encode(array_reverse($series),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
 top:<?=json_encode($top,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,
 distribution:<?=json_encode($distribution,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>
};
</script>
<?php
$apiReady = $apiEnabled && $apiTokenConfigured;
$totalRows = (int)($apiCounts['sales']??0) + (int)($apiCounts['shipments']??0) + (int)($apiCounts['inventory']??0);
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-plug"></i> INTEGRACIÓN ACTIVA</div>
        <h1 class="page-title">API REST Bayer</h1>
        <p class="page-subtitle">Canal automático sistema-a-sistema para entregar información publicada sin descargas manuales.</p>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="page-eyebrow mb-2"><i class="bi bi-activity"></i> ESTADO</div>
                <h5 class="mb-3"><?= $apiReady ? 'API operativa' : 'API pendiente de configuración' ?></h5>
                <div class="d-grid gap-2">
                    <div><strong>API habilitada:</strong> <?= $apiEnabled ? 'Sí' : 'No' ?></div>
                    <div><strong>Token configurado:</strong> <?= $apiTokenConfigured ? 'Sí' : 'No' ?></div>
                    <div><strong>Datos expuestos:</strong> Solo PUBLICADOS</div>
                    <div><strong>Formato:</strong> JSON</div>
                </div>
                <?php if(!$apiReady): ?>
                    <div class="alert alert-warning mt-3 mb-0">Revisa <code>API_ENABLED=true</code> y que <code>API_TOKEN</code> tenga un valor seguro en el <code>.env</code> del servidor.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="page-eyebrow mb-2"><i class="bi bi-link-45deg"></i> ENDPOINT PRINCIPAL</div>
                <h5 class="mb-2">Toda la información publicada</h5>
                <p class="text-muted mb-2">Este es el endpoint que Bayer puede programar para obtener Documentos, Guías y Stock en una sola llamada.</p>
                <div class="p-3 rounded border bg-light-subtle">
                    <code>GET <?=e($apiBase)?>/all</code>
                </div>
                <div class="mt-3">
                    <strong>Autenticación:</strong>
                    <code class="ms-1">Authorization: Bearer &lt;API_TOKEN&gt;</code>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h5><i class="bi bi-cloud-arrow-down me-2"></i>Endpoints disponibles</h5>
                <div class="d-grid gap-2 mt-3">
                    <code>GET <?=e($apiBase)?>/all</code>
                    <code>GET <?=e($apiBase)?>/sales</code>
                    <code>GET <?=e($apiBase)?>/shipments</code>
                    <code>GET <?=e($apiBase)?>/inventory</code>
                </div>
                <hr>
                <p class="mb-2"><strong>Filtros opcionales:</strong></p>
                <code>?from=2026-09-01&amp;to=2026-09-30&amp;branch=PUC-CHI&amp;q=maiz</code>
                <p class="text-muted small mt-2 mb-0">El cliente puede consumir la API desde su ERP, middleware, ETL, Power Automate, servicio Windows o cualquier cliente HTTP.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h5><i class="bi bi-database-check me-2"></i>Datos publicados disponibles</h5>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Documentos / ventas</span><strong><?=e((string)($apiCounts['sales']??0))?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Guías de remisión</span><strong><?=e((string)($apiCounts['shipments']??0))?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Stock</span><strong><?=e((string)($apiCounts['inventory']??0))?></strong></div>
                <div class="d-flex justify-content-between pt-3"><span>Total de filas API</span><strong><?=e((string)$totalRows)?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <h5><i class="bi bi-shield-lock me-2"></i>Cómo lo utilizará Bayer</h5>
        <div class="architecture-flow mt-3">
            <div class="architecture-node"><span><i class="bi bi-building"></i> BAYER</span><strong>Sistema interno</strong><small>Proceso programado</small></div><i>→</i>
            <div class="architecture-node"><span><i class="bi bi-key"></i> HTTPS + TOKEN</span><strong>API REST</strong><small>Autenticación Bearer</small></div><i>→</i>
            <div class="architecture-node"><span><i class="bi bi-database-check"></i> PUCCHÚN</span><strong>Datos publicados</strong><small>JSON automático</small></div>
        </div>
        <p class="text-muted mt-3 mb-0">No requiere iniciar sesión en el portal ni presionar XLSX, JSON, TXT o PDF. Las exportaciones manuales se mantienen como alternativa para usuarios humanos.</p>
    </div>
</div>

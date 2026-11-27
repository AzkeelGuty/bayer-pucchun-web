<?php
$apiReady = $apiEnabled && $apiStorageReady && $apiDataReady;
$totalRows = $apiDataReady ? array_sum($apiCounts) : null;
$ttlHours = max(1, (int)round(((int)$apiTokenTtl) / 3600));
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-plug"></i> INTEGRACIÓN ACTIVA</div>
        <h1 class="page-title">API REST Bayer v2</h1>
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
                    <div><strong>Autenticación:</strong> Usuario + Bearer temporal</div>
                    <div><strong>Almacén de tokens:</strong> <?= $apiStorageReady ? 'Listo' : 'Pendiente' ?></div>
                    <div><strong>Duración del token:</strong> <?=e((string)$ttlHours)?> h</div>
                    <div><strong>Datos expuestos:</strong> Registros publicados disponibles para la integración v2.</div>
                    <div><strong>Formato:</strong> JSON con campos en español</div>
                </div>
                <?php if(!$apiReady): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <?php if(!$apiEnabled): ?>
                            Activa <code>API_ENABLED=true</code> en el <code>.env</code> del servidor.
                        <?php elseif(!$apiStorageReady): ?>
                            <?=e((string)($apiStorageIssue ?: 'Ejecute la migración 005_api_tokens.sql.'))?>
                        <?php else: ?>
                            <?=e((string)$apiDataIssue)?>
                        <?php endif; ?>
                    </div>
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
                    <code>GET <?=e($apiBase)?>/todo</code>
                </div>
                <div class="mt-3">
                    <strong>1. Iniciar sesión:</strong>
                    <code class="ms-1">POST <?=e($apiAuthBase)?>/login</code>
                </div>
                <div class="mt-2">
                    <strong>2. Consumir datos:</strong>
                    <code class="ms-1">Authorization: Bearer &lt;token_temporal&gt;</code>
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
                    <code>GET <?=e($apiBase)?>/todo</code>
                    <code>GET <?=e($apiBase)?>/facturas</code>
                    <code>GET <?=e($apiBase)?>/guias</code>
                    <code>GET <?=e($apiBase)?>/stock</code>
                </div>
                <hr>
                <p class="mb-2"><strong>Filtros opcionales:</strong></p>
                <code>?desde=2026-09-01&amp;hasta=2026-09-30&amp;sucursal=PUC-CHI&amp;busqueda=maiz&amp;estado_documento=1</code>
                <p class="text-muted small mt-2 mb-0"><code>estado_documento</code> aplica a facturas y admite <strong>1 (Vigente)</strong> o <strong>0 (Anulado)</strong>.</p>
                <p class="text-muted small mt-2 mb-0">El cliente puede consumir la API desde su ERP, middleware, ETL, Power Automate, servicio Windows o cualquier cliente HTTP. La respuesta contiene <code>metadatos</code> y <code>datos</code>, con 24 campos para facturas, 21 para guías y 11 para stock.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h5><i class="bi bi-database-check me-2"></i>Datos disponibles en API v2</h5>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Facturas / documentos</span><strong><?=e((string)($apiCounts['facturas']??'—'))?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Guías de remisión</span><strong><?=e((string)($apiCounts['guias']??'—'))?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span>Stock</span><strong><?=e((string)($apiCounts['stock']??'—'))?></strong></div>
                <div class="d-flex justify-content-between pt-3"><span>Total de filas API</span><strong><?=e((string)($totalRows??'—'))?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <h5><i class="bi bi-shield-lock me-2"></i>Cómo lo utilizará Bayer</h5>
        <div class="architecture-flow mt-3">
            <div class="architecture-node"><span><i class="bi bi-person-lock"></i> LOGIN API</span><strong>Usuario autorizado</strong><small>Correo + contraseña</small></div><i>→</i>
            <div class="architecture-node"><span><i class="bi bi-key"></i> BEARER TEMPORAL</span><strong>Token con vencimiento</strong><small><?=e((string)$ttlHours)?> horas</small></div><i>→</i>
            <div class="architecture-node"><span><i class="bi bi-database-check"></i> PUCCHÚN</span><strong>Integración v2</strong><small>JSON automático</small></div>
        </div>
        <p class="text-muted mt-3 mb-0">La integración no usa la sesión web del portal: primero autentica un usuario autorizado mediante el endpoint de login de API, recibe un Bearer temporal y luego consulta los conjuntos de datos disponibles en la API v2. Las exportaciones manuales se mantienen como alternativa para usuarios humanos.</p>
    </div>
</div>

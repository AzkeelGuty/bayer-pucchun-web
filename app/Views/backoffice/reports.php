<?php
$datasets=[
    'documents'=>[
        'label'=>'Documentos',
        'description'=>'Ventas y documentos publicados listos para consulta o análisis.',
        'icon'=>'bi-file-earmark-text',
        'class'=>'documents',
    ],
    'guides'=>[
        'label'=>'Guías de remisión',
        'description'=>'Despachos publicados con cliente, sucursal, producto y cantidades.',
        'icon'=>'bi-truck',
        'class'=>'guides',
    ],
    'stock'=>[
        'label'=>'Stock',
        'description'=>'Existencias publicadas por almacén, producto, lote y fecha.',
        'icon'=>'bi-box-seam',
        'class'=>'stock',
    ],
];
$formats=[
    'xlsx'=>['label'=>'Excel','detail'=>'Hoja estructurada','icon'=>'bi-file-earmark-spreadsheet','class'=>'xlsx'],
    'csv'=>['label'=>'CSV','detail'=>'Intercambio de datos','icon'=>'bi-filetype-csv','class'=>'csv'],
    'json'=>['label'=>'JSON','detail'=>'Integración técnica','icon'=>'bi-braces','class'=>'json'],
    'txt'=>['label'=>'TXT','detail'=>'Texto plano','icon'=>'bi-filetype-txt','class'=>'txt'],
    'pdf'=>['label'=>'PDF','detail'=>'Reporte visual','icon'=>'bi-filetype-pdf','class'=>'pdf'],
];
$datasetLabel=static fn(string $type): string => $datasets[$type]['label'] ?? ucfirst($type);
?>
<section class="page-header reports-page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-box-arrow-down"></i> ENTREGA DE INFORMACIÓN</div>
        <h1 class="page-title">Reportes y exportaciones</h1>
        <p class="page-subtitle">Descarga información publicada con trazabilidad del usuario, fecha, filtros y formato utilizado.</p>
    </div>
    <div class="published-lock"><i class="bi bi-shield-check me-1"></i> Solo datos publicados</div>
</section>

<div class="export-grid export-grid-professional">
<?php foreach($datasets as $type=>$dataset): ?>
    <article class="card export-card export-card-professional export-card-<?=e($dataset['class'])?>">
        <div class="card-body">
            <div class="export-card-heading">
                <span class="export-card-icon"><i class="bi <?=e($dataset['icon'])?>"></i></span>
                <div>
                    <h3><?=e($dataset['label'])?></h3>
                    <p><?=e($dataset['description'])?></p>
                </div>
            </div>

            <div class="export-card-note">
                <i class="bi bi-info-circle"></i>
                <span>Cada descarga incluye información del responsable y fecha de generación.</span>
            </div>

            <div class="export-actions export-actions-rich" aria-label="Formatos para <?=e($dataset['label'])?>">
                <?php foreach($formats as $format=>$meta): ?>
                    <a
                        class="export-format export-format-<?=e($meta['class'])?>"
                        href="<?=url('/export?type='.$type.'&format='.$format)?>"
                        title="Descargar <?=e($dataset['label'])?> en <?=e($meta['label'])?>"
                        aria-label="Descargar <?=e($dataset['label'])?> en <?=e($meta['label'])?>"
                    >
                        <i class="bi <?=e($meta['icon'])?>" aria-hidden="true"></i>
                        <span>
                            <strong><?=e($meta['label'])?></strong>
                            <small><?=e($meta['detail'])?></small>
                        </span>
                        <i class="bi bi-download export-format-download" aria-hidden="true"></i>
                    </a>
                <?php endforeach;?>
            </div>
        </div>
    </article>
<?php endforeach;?>
</div>

<div class="card mt-4 export-history-card">
    <div class="card-header bg-white security-table-title security-table-title-between">
        <div><i class="bi bi-clock-history"></i><strong>Últimas exportaciones</strong></div>
        <small>Registro de descargas generadas desde la plataforma</small>
    </div>
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state">
                <i class="bi bi-download fs-3 mb-2"></i>
                <strong>Aún no hay exportaciones registradas.</strong>
                <span>Las descargas realizadas aparecerán aquí.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0 export-history-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Archivo</th>
                            <th>Dataset</th>
                            <th>Formato</th>
                            <th>Registros</th>
                            <th>Resultado</th>
                            <th>Fecha de generación</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $row):
                        $format=strtolower((string)($row['formato']??''));
                        $result=strtoupper((string)($row['resultado']??''));
                    ?>
                        <tr>
                            <td><span class="table-id">#<?=e($row['id'])?></span></td>
                            <td>
                                <span class="export-file-cell">
                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                    <span><?=e($row['nombre_archivo']??'—')?></span>
                                </span>
                            </td>
                            <td><span class="dataset-pill"><i class="bi bi-database"></i><?=e($datasetLabel((string)$row['tipo_dataset']))?></span></td>
                            <td><span class="format-badge format-badge-<?=e($format)?>"><i class="bi <?=e($formats[$format]['icon']??'bi-file-earmark')?>"></i><?=e(strtoupper($format))?></span></td>
                            <td><span class="record-count"><i class="bi bi-list-ol"></i><?=e($row['record_count'])?></span></td>
                            <td>
                                <span class="result-badge <?=$result==='GENERADO'||$result==='COMPLETADO'?'result-success':'result-neutral'?>">
                                    <i class="bi <?=$result==='GENERADO'||$result==='COMPLETADO'?'bi-check-circle-fill':'bi-info-circle'?>"></i>
                                    <?=$result==='GENERADO'||$result==='COMPLETADO'?'Completado':e(ucfirst(strtolower($result)))?>
                                </span>
                            </td>
                            <td><span class="date-cell"><i class="bi bi-calendar3"></i><?=e($row['generated_at'])?></span></td>
                            <td><span class="user-cell"><i class="bi bi-person-circle"></i><?=e($row['usuario'])?></span></td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        <?php endif;?>
    </div>
</div>
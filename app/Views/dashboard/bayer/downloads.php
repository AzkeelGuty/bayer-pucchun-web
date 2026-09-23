<?php
$datasetLabels=['documents'=>'Documentos','guides'=>'Guías de remisión','stock'=>'Stock'];
$resultLabels=['GENERADO'=>'Completado','COMPLETADO'=>'Completado','ERROR'=>'Error','FALLIDO'=>'Fallido'];
$formatDate=static function(mixed $value): string {
    $value=(string)($value??'');
    if($value==='') return '—';
    $time=strtotime($value);
    return $time!==false ? date('d/m/Y H:i',$time) : $value;
};
?>
<link rel="stylesheet" href="<?=url('/assets/css/bayer-portal.css?v=1')?>">
<section class="page-header portal-page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-clock-history"></i> TRAZABILIDAD</div>
        <h1 class="page-title">Historial de descargas</h1>
        <p class="page-subtitle">Registro de exportaciones generadas con tu cuenta.</p>
    </div>
</section>

<div class="card portal-history-card" data-live-refresh="4000" data-live-refresh-key="bayer-download-history">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><i class="bi bi-download fs-3 mb-2"></i><strong>Aún no has realizado descargas.</strong><span>Las exportaciones generadas aparecerán aquí.</span></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0 portal-history-table">
                    <thead>
                        <tr><th>Fecha y hora</th><th>Conjunto de datos</th><th>Formato</th><th>Archivo</th><th>Registros</th><th>Resultado</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r):
                        $dataset=(string)($r['tipo_dataset']??'');
                        $format=strtolower((string)($r['formato']??''));
                        $result=strtoupper((string)($r['resultado']??''));
                    ?>
                        <tr>
                            <td><span class="portal-history-date"><i class="bi bi-calendar3"></i><?=e($formatDate($r['generated_at']??null))?></span></td>
                            <td><span class="portal-dataset-pill"><i class="bi bi-database"></i><?=e($datasetLabels[$dataset]??ucfirst($dataset))?></span></td>
                            <td><span class="format-chip format-<?=e($format)?>"><?=e(strtoupper($format))?></span></td>
                            <td><span class="portal-file-name" title="<?=e($r['nombre_archivo']??'')?>"><i class="bi bi-file-earmark-arrow-down"></i><?=e($r['nombre_archivo']??'—')?></span></td>
                            <td><strong><?=e($r['record_count'])?></strong></td>
                            <td><span class="portal-result <?=$result==='GENERADO'||$result==='COMPLETADO'?'portal-result-ok':'portal-result-neutral'?>"><i class="bi <?=$result==='GENERADO'||$result==='COMPLETADO'?'bi-check-circle-fill':'bi-info-circle'?>"></i><?=e($resultLabels[$result]??ucfirst(strtolower($result)))?></span></td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        <?php endif;?>
    </div>
</div>

<?php
$labels=['documents'=>'Documentos publicados','guides'=>'Guías publicadas','stock'=>'Stock publicado'];
$queryBase=http_build_query(array_filter(['type'=>$type,'from'=>$filters['from']??'','to'=>$filters['to']??'','branch'=>$filters['branch']??'','q'=>$filters['q']??''],fn($v)=>$v!==''));
?>
<section class="page-header portal-page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-database-check"></i> INFORMACIÓN PUBLICADA</div>
        <h1 class="page-title"><?=e($labels[$type]??'Datos publicados')?></h1>
        <p class="page-subtitle">Consulta de solo lectura sobre información validada y publicada.</p>
    </div>
    <span class="published-lock"><i class="bi bi-lock-fill me-1"></i> Solo datos publicados</span>
</section>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" action="<?=url('/bayer/datos')?>" class="filter-grid">
            <input type="hidden" name="type" value="<?=e($type)?>">
            <div><label class="form-label" for="from">Desde</label><input class="form-control" id="from" type="date" name="from" value="<?=e($filters['from']??'')?>"></div>
            <div><label class="form-label" for="to">Hasta</label><input class="form-control" id="to" type="date" name="to" value="<?=e($filters['to']??'')?>"></div>
            <div><label class="form-label" for="branch">Sucursal</label><select class="form-select" id="branch" name="branch"><option value="">Todas</option><?php foreach($branches as $b): ?><option value="<?=e($b['codigo'])?>" <?=($filters['branch']??'')===$b['codigo']?'selected':''?>><?=e($b['nombre'])?></option><?php endforeach;?></select></div>
            <div class="filter-search"><label class="form-label" for="q">Buscar</label><input class="form-control" id="q" name="q" value="<?=e($filters['q']??'')?>" placeholder="Documento, cliente o producto"></div>
            <div class="filter-buttons"><button class="btn btn-primary btn-with-icon" type="submit"><i class="bi bi-funnel"></i><span>Aplicar filtros</span></button><a class="btn btn-outline-primary btn-with-icon" href="<?=url('/bayer/datos?type='.urlencode($type))?>"><i class="bi bi-x-circle"></i><span>Limpiar</span></a></div>
        </form>
    </div>
</div>

<div class="delivery-toolbar">
    <div><strong><?=number_format(count($rows))?></strong><span> registros en la vista</span></div>
    <div class="format-actions"><?php foreach(['xlsx'=>'bi-file-earmark-spreadsheet','json'=>'bi-braces','txt'=>'bi-filetype-txt','pdf'=>'bi-filetype-pdf'] as $format=>$icon): ?><a href="<?=url('/export?'.$queryBase.'&format='.$format)?>" class="format-chip format-<?=$format?>"><i class="bi <?=e($icon)?>"></i><?=strtoupper($format)?></a><?php endforeach;?></div>
</div>

<div class="card" data-live-refresh="5000" data-live-refresh-key="bayer-published-data">
    <div class="card-body p-0">
        <?php if(!$rows): ?><div class="empty-state"><strong>No hay resultados publicados.</strong><span>Ajusta el periodo o los filtros de consulta.</span></div>
        <?php else: ?><div class="table-responsive"><table class="table app-table published-table mb-0"><thead><tr><?php foreach(array_keys($rows[0]) as $h): ?><th><?=e(ucwords(preg_replace('/([a-z])([A-Z])/','$1 $2',$h)))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($row as $value): ?><td><?=e($value??'—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?>
    </div>
</div>
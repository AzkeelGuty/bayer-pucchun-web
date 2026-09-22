<?php
$datasetLabels=[
    'documents'=>'Documentos publicados',
    'guides'=>'Guías publicadas',
    'stock'=>'Stock publicado',
];

$columnLabels=[
    'dealerId'=>'RUC distribuidor',
    'dealerName'=>'Distribuidor',
    'documentTypeId'=>'Cód. tipo doc.',
    'documentType'=>'Tipo de documento',
    'documentNumber'=>'N.º documento',
    'documentDate'=>'Fecha del documento',
    'salesId'=>'Cód. vendedor',
    'salesName'=>'Vendedor',
    'branchId'=>'Cód. sucursal',
    'branchName'=>'Sucursal',
    'customerId'=>'Doc. cliente',
    'customerName'=>'Cliente',
    'materialId'=>'Cód. producto',
    'materialName'=>'Producto',
    'measureUnit'=>'Unidad',
    'quantity'=>'Cantidad',
    'unitValue'=>'Valor unitario',
    'province'=>'Provincia',
    'department'=>'Departamento',
    'district'=>'Distrito',
    'stockDate'=>'Fecha de stock',
    'warehouseId'=>'Cód. almacén',
    'warehouseName'=>'Almacén',
    'batch'=>'Lote',
    'expirationDate'=>'Vencimiento',
];

$dateColumns=['documentDate','stockDate','expirationDate'];
$longColumns=['dealerName','salesName','branchName','customerName','materialName','warehouseName'];
$codeColumns=['dealerId','documentTypeId','documentNumber','salesId','branchId','customerId','materialId','measureUnit','warehouseId','batch'];
$numericColumns=['quantity','unitValue'];

$formatValue=static function(string $key,mixed $value) use($dateColumns): string {
    if($value===null || $value==='') return '—';
    $display=(string)$value;
    if(in_array($key,$dateColumns,true) && preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/',$display,$parts)){
        return $parts[3].'/'.$parts[2].'/'.$parts[1];
    }
    return $display;
};

$queryBase=http_build_query(array_filter([
    'type'=>$type,
    'from'=>$filters['from']??'',
    'to'=>$filters['to']??'',
    'branch'=>$filters['branch']??'',
    'q'=>$filters['q']??'',
],fn($v)=>$v!==''));
?>
<link rel="stylesheet" href="<?=url('/assets/css/bayer-portal.css?v=2')?>">
<section class="page-header portal-page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-database-check"></i> INFORMACIÓN PUBLICADA</div>
        <h1 class="page-title"><?=e($datasetLabels[$type]??'Datos publicados')?></h1>
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

<div class="delivery-toolbar portal-delivery-toolbar">
    <div class="delivery-count"><strong><?=number_format(count($rows))?></strong><span> registros publicados en la vista</span></div>
    <div class="delivery-export">
        <span class="delivery-export-label">Exportar:</span>
        <div class="format-actions"><?php foreach(['xlsx'=>'bi-file-earmark-spreadsheet','json'=>'bi-braces','txt'=>'bi-filetype-txt','pdf'=>'bi-filetype-pdf'] as $format=>$icon): ?><a href="<?=url('/export?'.$queryBase.'&format='.$format)?>" class="format-chip format-<?=$format?>" title="Descargar en <?=strtoupper($format)?>"><i class="bi <?=e($icon)?>"></i><?=strtoupper($format)?></a><?php endforeach;?></div>
    </div>
</div>

<div class="card published-data-card" data-live-refresh="5000" data-live-refresh-key="bayer-published-data">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><i class="bi bi-inbox fs-3 mb-2"></i><strong>No hay resultados publicados.</strong><span>Ajusta el periodo o los filtros de consulta.</span></div>
        <?php else: ?>
            <div class="table-responsive published-table-shell">
                <table class="table app-table published-table published-table-portal mb-0">
                    <thead>
                        <tr>
                            <?php foreach(array_keys($rows[0]) as $header): ?>
                                <th scope="col" title="<?=e((string)$header)?>"><?=e($columnLabels[(string)$header]??ucfirst((string)$header))?></th>
                            <?php endforeach;?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $row): ?>
                        <tr>
                        <?php foreach($row as $key=>$value):
                            $key=(string)$key;
                            $classes=['published-cell'];
                            if(in_array($key,$longColumns,true)) $classes[]='published-cell-long';
                            if(in_array($key,$codeColumns,true)) $classes[]='published-cell-code';
                            if(in_array($key,$numericColumns,true)) $classes[]='published-cell-number';
                            if(in_array($key,$dateColumns,true)) $classes[]='published-cell-date';
                            $display=$formatValue($key,$value);
                        ?>
                            <td class="<?=e(implode(' ',$classes))?>" data-column="<?=e($key)?>"><?=e($display)?></td>
                        <?php endforeach;?>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <div class="published-table-footnote"><i class="bi bi-arrows-expand"></i><span>Desplázate horizontalmente para consultar todas las columnas del registro.</span></div>
        <?php endif;?>
    </div>
</div>

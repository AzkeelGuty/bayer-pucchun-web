<?php
$tabIcons=[
    'clientes'=>'bi-people',
    'productos'=>'bi-box-seam',
    'proveedores'=>'bi-truck',
    'vendedores'=>'bi-person-vcard',
    'sucursales'=>'bi-building',
    'almacenes'=>'bi-boxes',
    'unidades'=>'bi-rulers',
    'empresas'=>'bi-buildings',
    'tipos'=>'bi-file-earmark-text',
    'ubigeo'=>'bi-geo-alt',
];
$headingLabels=[
    'id'=>'ID',
    'codigo'=>'Código',
    'tipo_doc'=>'Tipo de documento',
    'nro_doc'=>'N.º documento',
    'razon_social'=>'Razón social',
    'nombre'=>'Nombre',
    'tipo_art'=>'Tipo de artículo',
    'nombre_comercial'=>'Nombre comercial',
    'codigo_interno'=>'Código interno',
    'codigo_bayer'=>'Código Bayer',
    'nombre_bayer'=>'Nombre Bayer',
    'partner'=>'Aliado',
    'documento'=>'Documento',
    'cliente'=>'Cliente',
    'producto'=>'Producto',
    'categoria'=>'Categoría',
    'marca'=>'Marca',
    'unidad'=>'Unidad',
    'empresa'=>'Empresa',
    'direccion'=>'Dirección',
    'distrito'=>'Distrito',
    'provincia'=>'Provincia',
    'departamento'=>'Departamento',
    'tipo'=>'Tipo',
    'abreviatura'=>'Abreviatura',
    'factor_base'=>'Factor base',
    'ruc'=>'RUC',
    'sunat_code'=>'Código SUNAT',
    'sucursal'=>'Sucursal',
    'almacen'=>'Almacén',
    'vendedor'=>'Vendedor',
    'estado'=>'Estado',
    'modulo'=>'Módulo',
    'usuario'=>'Usuario',
    'registros'=>'Registros',
    'accion'=>'Acción',
    'resultado'=>'Resultado',
    'ip'=>'Dirección IP',
    'valid_from'=>'Vigente desde',
    'valid_until'=>'Vigente hasta',
    'fecha_publicacion'=>'Fecha de publicación',
    'entidad_id'=>'ID de entidad',
    'fecha_hora'=>'Fecha y hora',
];
$headerLabel=static function(string $key) use($headingLabels): string {
    return $headingLabels[$key] ?? ucwords(str_replace('_',' ',$key));
};

/*
 * Estado activo robusto:
 * - Si existe ?tab=..., manda la URL actual.
 * - Si no existe, usa el valor enviado por el controlador.
 * - Como último respaldo usa la primera pestaña disponible.
 */
$requestedTab=isset($_GET['tab']) ? strtolower(trim((string)$_GET['tab'])) : '';
$activeTab=$requestedTab!=='' ? $requestedTab : strtolower(trim((string)($active ?? '')));
if($activeTab==='' && !empty($tabs)){
    $activeTab=(string)array_key_first($tabs);
}

$paginationData=is_array($pagination??null) ? $pagination : [];
$currentPage=max(1,(int)($paginationData['page']??1));
$totalPages=max(1,(int)($paginationData['pages']??1));
$totalRows=max(0,(int)($paginationData['total']??count($rows??[])));
$perPage=max(1,(int)($paginationData['perPage']??50));
$firstRow=$totalRows>0 ? (($currentPage-1)*$perPage)+1 : 0;
$lastRow=$totalRows>0 ? min($totalRows,$currentPage*$perPage) : 0;

$pageUrl=static function(int $page) use($base,$activeTab,$q): string {
    $params=['tab'=>$activeTab];
    if(trim((string)($q??''))!=='') $params['q']=(string)$q;
    if($page>1) $params['page']=$page;
    return url($base.'?'.http_build_query($params));
};
?>
<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-database"></i> <?=e(strtoupper($section))?></div>
        <h1 class="page-title"><?=e($title)?></h1>
        <p class="page-subtitle">Información centralizada para la operación y el control del sistema.</p>
    </div>
</section>

<?php if($tabs): ?>
<nav class="section-tabs section-tabs-strong" aria-label="<?=e($section)?>">
    <?php foreach($tabs as $key=>$label):
        $normalizedKey=strtolower(trim((string)$key));
        $isActive=$normalizedKey===$activeTab;
        $icon=$tabIcons[$key]??'bi-grid';
    ?>
        <a
            class="section-tab <?=$isActive?'active':''?>"
            href="<?=url($base.'?tab='.urlencode((string)$key))?>"
            data-tab="<?=e((string)$key)?>"
            aria-selected="<?=$isActive?'true':'false'?>"
            <?=$isActive?'aria-current="page"':''?>
        >
            <i class="bi <?=e($icon)?>" aria-hidden="true"></i>
            <span><?=e($label)?></span>
            <i class="bi bi-check-circle-fill section-tab-check" aria-hidden="true"></i>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if(!empty($catalogNotice)): ?>
<div class="alert alert-warning mb-3" role="status">
    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
    <?=e((string)$catalogNotice)?>
</div>
<?php endif; ?>

<?php if($base==='/maestros'): ?>
<div class="master-toolbar card mb-3">
    <div class="card-body py-3 d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div>
            <strong>Administrar <?=e(mb_strtolower((string)($masterMeta['title']??$title)))?></strong>
            <div class="form-text mt-0">Agrega, edita, elimina o carga varios registros desde Excel.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap master-toolbar-actions">
            <?php if(!empty($masterManageAvailable)): ?>
                <a class="btn btn-primary btn-with-icon" href="<?=url('/maestros/nuevo?tab='.urlencode($activeTab))?>">
                    <i class="bi bi-plus-lg"></i><span>Agregar uno</span>
                </a>
                <a class="btn btn-success btn-with-icon" href="<?=url('/maestros/importar?tab='.urlencode($activeTab))?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span>Importar Excel</span>
                </a>
                <a class="btn btn-outline-primary btn-with-icon" href="<?=url('/maestros/plantilla?tab='.urlencode($activeTab))?>" data-native-navigation>
                    <i class="bi bi-download"></i><span>Plantilla XLSX</span>
                </a>
            <?php elseif(empty($masterAdmin)): ?>
                <span class="text-muted small"><i class="bi bi-shield-lock me-1"></i>Solo el rol ADMIN puede modificar los catálogos.</span>
            <?php else: ?>
                <span class="text-muted small"><i class="bi bi-exclamation-triangle me-1"></i><?=e((string)($masterManageIssue??'Complete la migración requerida para administrar este catálogo.'))?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<form class="card mb-3" method="get" action="<?=url($base)?>" role="search" data-master-search-form>
    <div class="card-body py-3">
        <input type="hidden" name="tab" value="<?=e($activeTab)?>">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-8">
                <label class="form-label" for="master-search">Buscar en el catálogo</label>
                <input class="form-control" id="master-search" name="q" value="<?=e((string)($q??''))?>" placeholder="Código, DNI/RUC, nombre o descripción..." autocomplete="off">
            </div>
            <div class="col-12 col-lg-auto d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
                <a
                    class="btn btn-outline-primary <?=trim((string)($q??''))===''?'d-none':''?>"
                    href="<?=url($base.'?tab='.urlencode($activeTab))?>"
                    data-master-search-clear
                >Limpiar</a>
            </div>
        </div>
        <div class="form-text mt-2">La búsqueda consulta todo el catálogo. Para mayor velocidad se muestran <?=e((string)$perPage)?> registros por página.</div>
    </div>
</form>
<?php endif; ?>

<div class="card data-table-card"<?=$base==='/maestros'?'':' data-live-refresh="6000" data-live-refresh-key="backoffice-data-table"'?>>
    <?php if($base==='/maestros'): ?>
    <div class="catalog-result-bar">
        <div>
            <strong><?=number_format($totalRows,0,'.',',')?> registros</strong>
            <?php if($totalRows>0): ?>
                <span>Mostrando <?=number_format($firstRow,0,'.',',')?>–<?=number_format($lastRow,0,'.',',')?></span>
            <?php endif; ?>
        </div>
        <?php if($totalPages>1): ?>
            <span>Página <?=e((string)$currentPage)?> de <?=e((string)$totalPages)?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><i class="bi bi-inbox fs-3 mb-2"></i><strong>Sin información registrada.</strong><span>Los datos disponibles aparecerán aquí.</span></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0 enhanced-data-table">
                    <thead>
                        <tr>
                            <?php foreach(array_keys($rows[0]) as $h): ?><th><?=e($headerLabel((string)$h))?></th><?php endforeach;?>
                            <?php if($base==='/maestros' && !empty($masterManageAvailable)): ?><th class="text-end">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $row): ?>
                        <tr>
                        <?php foreach($row as $key=>$value):
                            $key=(string)$key;
                            $display=$value ?? '—';
                            $upper=strtoupper((string)$display);
                        ?>
                            <td>
                                <?php if($key==='estado'): ?>
                                    <span class="generic-status <?=in_array($upper,['ACTIVO','PUBLICADO','VALIDADO','OK'],true)?'generic-status-ok':(in_array($upper,['INACTIVO','ANULADO','ERROR'],true)?'generic-status-danger':'generic-status-neutral')?>">
                                        <i class="bi <?=in_array($upper,['ACTIVO','PUBLICADO','VALIDADO','OK'],true)?'bi-check-circle-fill':(in_array($upper,['INACTIVO','ANULADO','ERROR'],true)?'bi-x-circle-fill':'bi-info-circle')?>"></i>
                                        <?=e($display)?>
                                    </span>
                                <?php elseif($key==='usuario' || $key==='vendedor'): ?>
                                    <span class="table-icon-cell"><i class="bi bi-person-circle"></i><?=e($display)?></span>
                                <?php elseif($key==='ip'): ?>
                                    <span class="access-ip"><i class="bi bi-router"></i><?=e(access_ip_label((string)$display))?></span>
                                <?php elseif(str_starts_with($key,'fecha') || in_array($key,['valid_from','valid_until'],true)): ?>
                                    <span class="table-icon-cell table-date-cell"><i class="bi bi-calendar3"></i><?=e($display)?></span>
                                <?php elseif($key==='accion'): ?>
                                    <span class="table-action-cell"><i class="bi bi-activity"></i><?=e(ucfirst(str_replace('_',' ',(string)$display)))?></span>
                                <?php else: ?>
                                    <?=e($display)?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach;?>
                        <?php if($base==='/maestros' && !empty($masterManageAvailable)): ?>
                            <td class="text-end">
                                <div class="master-row-actions">
                                    <a class="btn btn-sm btn-outline-primary btn-with-icon" href="<?=url('/maestros/editar?tab='.urlencode($activeTab).'&id='.(int)$row['id'])?>">
                                        <i class="bi bi-pencil-square"></i><span>Editar</span>
                                    </a>
                                    <form method="post" action="<?=url('/maestros/eliminar')?>" class="d-inline" data-master-delete data-master-label="<?=e((string)($row['nombre']??$row['razon_social']??$row['vendedor']??$row['codigo']??$row['id']))?>">
                                        <?=csrf_field()?>
                                        <input type="hidden" name="tab" value="<?=e($activeTab)?>">
                                        <input type="hidden" name="id" value="<?=e((int)$row['id'])?>">
                                        <button class="btn btn-sm btn-outline-danger btn-with-icon" type="submit">
                                            <i class="bi bi-trash3"></i><span>Eliminar</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if($base==='/maestros' && $totalPages>1):
        $pageNumbers=[1,$totalPages];
        for($p=max(1,$currentPage-2);$p<=min($totalPages,$currentPage+2);$p++) $pageNumbers[]=$p;
        $pageNumbers=array_values(array_unique($pageNumbers));
        sort($pageNumbers);
        $previousPrinted=null;
    ?>
    <nav class="catalog-pagination" aria-label="Paginación de <?=e($title)?>">
        <a class="btn btn-sm btn-outline-primary <?=$currentPage<=1?'disabled':''?>" href="<?=$currentPage>1?e($pageUrl($currentPage-1)):'#'?>" <?=$currentPage<=1?'aria-disabled="true" tabindex="-1"':''?>>
            <i class="bi bi-chevron-left"></i><span>Anterior</span>
        </a>
        <div class="catalog-page-numbers">
            <?php foreach($pageNumbers as $pageNumber): ?>
                <?php if($previousPrinted!==null && $pageNumber>$previousPrinted+1): ?><span class="catalog-page-gap">…</span><?php endif; ?>
                <a class="catalog-page-link <?=$pageNumber===$currentPage?'active':''?>" href="<?=e($pageUrl($pageNumber))?>" <?=$pageNumber===$currentPage?'aria-current="page"':''?>><?=e((string)$pageNumber)?></a>
                <?php $previousPrinted=$pageNumber; ?>
            <?php endforeach; ?>
        </div>
        <a class="btn btn-sm btn-outline-primary <?=$currentPage>=$totalPages?'disabled':''?>" href="<?=$currentPage<$totalPages?e($pageUrl($currentPage+1)):'#'?>" <?=$currentPage>=$totalPages?'aria-disabled="true" tabindex="-1"':''?>>
            <span>Siguiente</span><i class="bi bi-chevron-right"></i>
        </a>
    </nav>
    <?php endif; ?>
</div>
<?php
$tabIcons=[
    'clientes'=>'bi-people',
    'productos'=>'bi-box-seam',
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
    'tipo_doc'=>'Tipo doc.',
    'nro_doc'=>'N.º documento',
    'razon_social'=>'Razón social',
    'nombre_comercial'=>'Nombre comercial',
    'codigo_interno'=>'Código interno',
    'codigo_bayer'=>'Código Bayer',
    'nombre_bayer'=>'Nombre Bayer',
    'valid_from'=>'Vigente desde',
    'valid_until'=>'Vigente hasta',
    'fecha_publicacion'=>'Fecha de publicación',
    'entidad_id'=>'Entidad ID',
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

<div class="card data-table-card">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><i class="bi bi-inbox fs-3 mb-2"></i><strong>Sin información registrada.</strong><span>Los datos disponibles aparecerán aquí.</span></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0 enhanced-data-table">
                    <thead>
                        <tr><?php foreach(array_keys($rows[0]) as $h): ?><th><?=e($headerLabel((string)$h))?></th><?php endforeach;?></tr>
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
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
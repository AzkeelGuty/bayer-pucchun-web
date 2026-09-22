<?php
$internal = !has_role('BAYER');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$currentQuery = [];
parse_str((string) (parse_url($requestUri, PHP_URL_QUERY) ?? ''), $currentQuery);
$basePath = rtrim((string) (parse_url((string) config('app.url', ''), PHP_URL_PATH) ?: ''), '/');
if ($basePath && ($currentPath === $basePath || str_starts_with($currentPath, $basePath . '/'))) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}

$isActive = static function (string $href) use ($currentPath, $currentQuery): bool {
    $pathOnly = parse_url($href, PHP_URL_PATH) ?: '/';
    $query = [];
    parse_str((string) (parse_url($href, PHP_URL_QUERY) ?? ''), $query);
    if ($query !== []) {
        if ($currentPath !== $pathOnly) return false;
        foreach ($query as $key => $value) if (($currentQuery[$key] ?? null) !== $value) return false;
        return true;
    }
    if (in_array($pathOnly, ['/dashboard', '/bayer'], true)) return $currentPath === $pathOnly;
    return $currentPath === $pathOnly || str_starts_with($currentPath, $pathOnly . '/');
};

$sections=[];
if (has_role('BAYER')) {
    $sections=[
        'PORTAL BAYER'=>[
            ['/bayer','bi-speedometer2','Panel principal'],
            ['/bayer/datos?type=documents','bi-file-earmark-check','Documentos publicados'],
            ['/bayer/datos?type=guides','bi-truck','Guías publicadas'],
            ['/bayer/datos?type=stock','bi-box-seam','Stock publicado'],
            ['/bayer/exportaciones','bi-download','Exportaciones'],
            ['/bayer/descargas','bi-clock-history','Historial de descargas'],
        ],
    ];
} else {
    if (has_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA')) {
        $sections['ANALÍTICA']=[['/dashboard','bi-speedometer2','Panel Pucchún']];
    }

    $operations=[];
    if (has_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA')) {
        $operations=[
            ['/documentos','bi-file-earmark-text','Documentos'],
            ['/guias','bi-truck','Guías de remisión'],
            ['/stock','bi-box-seam','Stock'],
        ];
    }
    if ($operations) $sections['OPERACIÓN']=$operations;

    if (has_role('ADMIN','SUPERVISOR')) {
        $sections['CALIDAD']=[
            ['/validacion','bi-patch-check','Validación y publicación'],
        ];
    }

    if (has_role('ADMIN')) {
        $sections['CONFIGURACIÓN BASE']=[
            ['/maestros','bi-database-gear','Catálogos maestros'],
            ['/homologaciones','bi-diagram-3','Homologaciones Bayer'],
        ];
    }

    if (has_role('ADMIN','SUPERVISOR','GERENCIA')) {
        $sections['INFORMACIÓN']=[
            ['/reportes','bi-bar-chart-line','Reportes y exportaciones'],
            ['/publicaciones','bi-cloud-check','Publicaciones'],
            ['/auditoria','bi-shield-check','Auditoría'],
            ['/bayer','bi-window-sidebar','Vista Portal Bayer'],
        ];
    }

    if (has_role('ADMIN')) {
        $sections['ADMINISTRACIÓN']=[
            ['/seguridad','bi-people','Usuarios y accesos'],
            ['/configuracion/identidad','bi-palette','Identidad visual'],
            ['/evolucion','bi-plug','API / ERP futuro'],
        ];
    }
}

$brand = branding();
$primaryLogo = branding_logo_url('logo_primary');
$partnerLogo = branding_logo_url('logo_partner');
$sidebarLogo = has_role('BAYER') ? ($partnerLogo ?: $primaryLogo) : $primaryLogo;
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Navegación principal">
    <div class="brand-block">
        <a class="brand-link" href="<?=url(App\Policies\AccessPolicy::landing($u))?>">
            <?php if($sidebarLogo): ?>
                <span class="brand-logo-box"><img src="<?=e($sidebarLogo)?>" alt=""></span>
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true"><?= has_role('BAYER') ? 'B' : 'P' ?></span>
            <?php endif; ?>
            <span class="brand-copy">
                <strong><?= has_role('BAYER') ? e($brand['partner_name']) : e($brand['system_name']) ?></strong>
                <small><?= has_role('BAYER') ? e($brand['partner_subtitle']) : e($brand['system_subtitle']) ?></small>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav" id="sidebarNav" data-scroll-key="<?=e((string)($u['id']??0).'-'.(string)($u['role']??'role'))?>">
        <?php foreach($sections as $section=>$items): ?>
            <div class="sidebar-label"><?=e($section)?></div>
            <?php foreach($items as [$href,$icon,$label]):
                $active=$isActive($href);
            ?>
                <a class="sidebar-link<?=$active?' active':''?>" href="<?=url($href)?>" <?=$active?'aria-current="page"':''?>>
                    <span class="sidebar-icon" aria-hidden="true"><i class="bi <?=e($icon)?>"></i></span>
                    <span><?=e($label)?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-status"><span class="status-dot" aria-hidden="true"></span><span>Sesión activa</span></div>
        <small><?=e($u['role'])?></small>
    </div>
</aside>

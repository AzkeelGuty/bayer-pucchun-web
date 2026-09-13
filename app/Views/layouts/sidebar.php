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

$items = [];
if (has_role('BAYER')) {
    $items = [
        ['/bayer', 'DB', 'Dashboard Bayer'],
        ['/bayer/datos?type=documents', 'DC', 'Documentos publicados'],
        ['/bayer/datos?type=guides', 'GR', 'Guías publicadas'],
        ['/bayer/datos?type=stock', 'ST', 'Stock publicado'],
    ];
} else {
    if (has_role('ADMIN','SUPERVISOR','GERENCIA')) $items[] = ['/dashboard', 'DB', 'Dashboard'];
    $items[] = ['/documentos', 'DC', 'Documentos'];
    $items[] = ['/guias', 'GR', 'Guías de remisión'];
    $items[] = ['/stock', 'ST', 'Stock'];
    if (has_role('ADMIN','SUPERVISOR','GERENCIA')) $items[] = ['/bayer', 'PB', 'Portal Bayer'];
    if (has_role('ADMIN')) $items[] = ['/configuracion/identidad', 'ID', 'Identidad visual'];
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

$brand = branding();
$primaryLogo = branding_logo_url('logo_primary');
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Navegación principal">
    <div class="brand-block">
        <a class="brand-link" href="<?=url(App\Policies\AccessPolicy::landing($u))?>">
            <?php if($primaryLogo): ?>
                <span class="brand-logo-box"><img src="<?=e($primaryLogo)?>" alt=""></span>
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true">P</span>
            <?php endif; ?>
            <span>
                <strong><?=e($brand['system_name'])?></strong>
                <small>Sistema de información</small>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-label"><?= $internal ? 'GESTIÓN' : 'CONSULTA' ?></div>
        <?php foreach ($items as [$href, $icon, $label]):
            $active = $isActive($href);
        ?>
            <a class="sidebar-link<?=$active ? ' active' : ''?>" href="<?=url($href)?>" <?=$active ? 'aria-current="page"' : ''?>>
                <span class="sidebar-icon" aria-hidden="true"><?=e($icon)?></span>
                <span><?=e($label)?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-status"><span class="status-dot" aria-hidden="true"></span><span>Conectado</span></div>
        <small><?=e($u['role'])?></small>
    </div>
</aside>

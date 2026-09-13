<?php
$internal = !has_role('BAYER');

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$currentQuery = [];
parse_str((string) (parse_url($requestUri, PHP_URL_QUERY) ?? ''), $currentQuery);

// REQUEST_URI includes the local subfolder when the app runs under XAMPP
// (e.g. /bayer-pucchun-web/public/dashboard). Normalize it so the active
// sidebar item is evaluated against application routes only.
$basePath = rtrim((string) (parse_url((string) config('app.url', ''), PHP_URL_PATH) ?: ''), '/');
if ($basePath && ($currentPath === $basePath || str_starts_with($currentPath, $basePath . '/'))) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}

$items = $internal
    ? [
        ['/dashboard', 'DB', 'Dashboard'],
        ['/documentos', 'DC', 'Documentos'],
        ['/guias', 'GR', 'Guías de remisión'],
        ['/stock', 'ST', 'Stock'],
        ['/bayer', 'PB', 'Portal Bayer'],
      ]
    : [
        ['/bayer', 'DB', 'Dashboard Bayer'],
        ['/bayer/datos?type=documents', 'DC', 'Documentos publicados'],
        ['/bayer/datos?type=guides', 'GR', 'Guías publicadas'],
        ['/bayer/datos?type=stock', 'ST', 'Stock publicado'],
      ];

$isActive = static function (string $href) use ($currentPath, $currentQuery): bool {
    $pathOnly = parse_url($href, PHP_URL_PATH) ?: '/';
    $query = [];
    parse_str((string) (parse_url($href, PHP_URL_QUERY) ?? ''), $query);

    if ($query !== []) {
        if ($currentPath !== $pathOnly) {
            return false;
        }
        foreach ($query as $key => $value) {
            if (($currentQuery[$key] ?? null) !== $value) {
                return false;
            }
        }
        return true;
    }

    if (in_array($pathOnly, ['/dashboard', '/bayer'], true)) {
        return $currentPath === $pathOnly;
    }

    return $currentPath === $pathOnly || str_starts_with($currentPath, $pathOnly . '/');
};
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Navegación principal">
    <div class="brand-block">
        <a class="brand-link" href="<?=url(App\Policies\AccessPolicy::landing($u))?>">
            <span class="brand-mark" aria-hidden="true">BP</span>
            <span>
                <strong>Bayer - Pucchún</strong>
                <small>Data Hub</small>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-label"><?= $internal ? 'GESTIÓN INTERNA' : 'PORTAL EXTERNO' ?></div>
        <?php foreach ($items as [$href, $icon, $label]):
            $active = $isActive($href);
        ?>
            <a
                class="sidebar-link<?=$active ? ' active' : ''?>"
                href="<?=url($href)?>"
                <?=$active ? 'aria-current="page"' : ''?>
            >
                <span class="sidebar-icon" aria-hidden="true"><?=e($icon)?></span>
                <span><?=e($label)?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-status">
            <span class="status-dot" aria-hidden="true"></span>
            <span>Sistema operativo</span>
        </div>
        <small>Información segura y trazable</small>
    </div>
</aside>

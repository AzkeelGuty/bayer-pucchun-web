<?php
$internal = !has_role('BAYER');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Navegación principal">
    <div class="brand-block">
        <a class="brand-link" href="<?=url(App\Policies\AccessPolicy::landing($u))?>">
            <span class="brand-mark">BP</span>
            <span>
                <strong>Bayer - Pucchún</strong>
                <small>Data Hub</small>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-label"><?= $internal ? 'GESTIÓN INTERNA' : 'PORTAL EXTERNO' ?></div>
        <?php foreach ($items as [$href, $icon, $label]):
            $pathOnly = strtok($href, '?');
            $active = $pathOnly === '/dashboard'
                ? $currentPath === '/dashboard'
                : str_starts_with($currentPath, $pathOnly);
        ?>
            <a class="sidebar-link<?=$active ? ' active' : ''?>" href="<?=url($href)?>">
                <span class="sidebar-icon"><?=e($icon)?></span>
                <span><?=e($label)?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-status">
            <span class="status-dot"></span>
            <span>Sistema operativo</span>
        </div>
        <small>Información segura y trazable</small>
    </div>
</aside>

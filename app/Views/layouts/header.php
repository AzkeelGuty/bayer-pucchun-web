<?php
$u=auth_user();
$brand = branding();
$favicon = branding_logo_url('favicon');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="<?=e($brand['primary_color'])?>">
    <title><?=e($brand['system_name'])?> · <?=e(has_role('BAYER') ? $brand['portal_title'] : $brand['internal_title'])?></title>
    <?php if($favicon): ?><link rel="icon" href="<?=e($favicon)?>"><?php endif; ?>
    <style>
        :root{
            --brand-primary:<?=e($brand['primary_color'])?>;
            --brand-accent:<?=e($brand['accent_color'])?>;
            --brand-sidebar:<?=e($brand['sidebar_color'])?>;
            --brand-background:<?=e($brand['background_color'])?>;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?=url('/assets/css/app.css')?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="<?= $u ? 'app-authenticated' : 'app-guest' ?> sidebar-theme-<?=e($brand['sidebar_theme'])?>">
<?php if($u): ?>
<div class="app-shell">
    <?php require base_path('app/Views/layouts/sidebar.php'); ?>
    <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Cerrar menú"></button>

    <section class="app-workspace">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" type="button" id="sidebarToggle" aria-label="Abrir menú" aria-controls="appSidebar" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="topbar-heading">
                    <div class="topbar-kicker"><?= has_role('BAYER') ? 'CONSULTA EXTERNA' : 'GESTIÓN INTERNA' ?></div>
                    <div class="topbar-title"><?=e(has_role('BAYER') ? $brand['portal_title'] : $brand['internal_title'])?></div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="user-chip" title="<?=e($u['nombre'])?> · <?=e($u['role'])?>">
                    <span class="user-avatar"><?=e(strtoupper(substr((string)$u['nombre'],0,1)))?></span>
                    <span class="user-copy">
                        <strong><?=e($u['nombre'])?></strong>
                        <small><?=e($u['role'])?></small>
                    </span>
                </div>
                <form method="post" action="<?=url('/logout')?>" class="m-0">
                    <?=csrf_field()?>
                    <button class="btn btn-logout btn-sm btn-with-icon" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Salir</span></button>
                </form>
            </div>
        </header>
        <main class="app-main">
<?php else: ?>
<main class="guest-main">
<?php endif; ?>

<div class="app-notifications" aria-live="polite" aria-atomic="true">
<?php if($m=flash('success')): ?>
    <div class="alert alert-success app-alert app-alert-floating alert-dismissible fade show js-auto-dismiss" role="alert">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <div><strong>Operación completada</strong><span><?=e($m)?></span></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>
<?php if($m=flash('error')): ?>
    <div class="alert alert-danger app-alert app-alert-floating alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div><strong>No se pudo completar</strong><span><?=e($m)?></span></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>
</div>

<?php $u=auth_user(); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?=e(config('app.name'))?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?=url('/assets/css/app.css')?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body class="<?= $u ? 'app-authenticated' : 'app-guest' ?>">
<?php if($u): ?>
<div class="app-shell">
    <?php require base_path('app/Views/layouts/sidebar.php'); ?>
    <section class="app-workspace">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" type="button" id="sidebarToggle" aria-label="Abrir o cerrar menú">☰</button>
                <div>
                    <div class="topbar-kicker">Sistema de Gestión de Información</div>
                    <div class="topbar-title"><?= has_role('BAYER') ? 'Portal Bayer' : 'Backoffice Pucchún' ?></div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="user-chip">
                    <span class="user-avatar"><?=e(strtoupper(substr((string)$u['nombre'],0,1)))?></span>
                    <span class="user-copy">
                        <strong><?=e($u['nombre'])?></strong>
                        <small><?=e($u['role'])?></small>
                    </span>
                </div>
                <form method="post" action="<?=url('/logout')?>" class="m-0">
                    <?=csrf_field()?>
                    <button class="btn btn-logout btn-sm" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </header>
        <main class="app-main">
<?php else: ?>
<main class="guest-main">
<?php endif; ?>

<?php if($m=flash('success')): ?>
    <div class="alert alert-success app-alert" role="alert"><?=e($m)?></div>
<?php endif; ?>
<?php if($m=flash('error')): ?>
    <div class="alert alert-danger app-alert" role="alert"><?=e($m)?></div>
<?php endif; ?>

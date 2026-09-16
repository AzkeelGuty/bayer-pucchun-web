<?php
$status = $status ?? 500;
$message = $message ?? 'Ocurrió un error inesperado.';
$title = match((int)$status) {
    403 => 'Acceso restringido',
    404 => 'Página no encontrada',
    419 => 'Sesión de formulario expirada',
    422 => 'No se pudo procesar la solicitud',
    503 => 'Módulo temporalmente no disponible',
    default => 'No pudimos completar la operación',
};
$home = auth_user() ? App\Policies\AccessPolicy::landing(auth_user()) : '/login';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($status)?> · <?=e(config('app.name'))?></title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f7fa;color:#17324d;font-family:Inter,Segoe UI,Arial,sans-serif}
        .error-card{width:min(620px,100%);background:#fff;border:1px solid #dbe5ee;border-radius:22px;box-shadow:0 18px 60px rgba(10,61,102,.12);padding:34px}
        .error-code{display:inline-flex;padding:7px 11px;border-radius:999px;background:#eaf4fb;color:#075b9f;font-size:.8rem;font-weight:800}
        h1{margin:18px 0 10px;color:#0a3d66;font-size:clamp(1.7rem,4vw,2.35rem)}
        p{margin:0;color:#62778a;line-height:1.65}
        .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
        a{display:inline-flex;align-items:center;justify-content:center;padding:11px 16px;border-radius:11px;text-decoration:none;font-weight:700}
        .primary{background:#075b9f;color:#fff}.secondary{border:1px solid #bcd0df;color:#075b9f}
        @media(max-width:520px){.error-card{padding:26px 20px;border-radius:16px}.actions{display:grid}.actions a{width:100%}}
    </style>
</head>
<body>
    <main class="error-card">
        <span class="error-code">Error <?=e($status)?></span>
        <h1><?=e($title)?></h1>
        <p><?=e($message)?></p>
        <div class="actions">
            <a class="primary" href="<?=e(url($home))?>">Volver al sistema</a>
            <a class="secondary" href="javascript:history.back()">Regresar</a>
        </div>
    </main>
</body>
</html>

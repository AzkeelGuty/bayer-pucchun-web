<?php
// Front controller compatible con Document Root = /public y con cPanel /public_html.
$initialBufferLevel = ob_get_level();
ob_start();
try {
    $publicParent = dirname(__DIR__);
    $projectRoot = $publicParent;

    if (!is_file($projectRoot . '/config/bootstrap.php')) {
        $configuredRoot = trim((string)(getenv('BAYER_APP_ROOT') ?: ''));
        $candidates = array_filter([
            $configuredRoot,
            $publicParent . '/bayer-pucchun-web',
        ]);
        $projectRoot = '';
        foreach ($candidates as $candidate) {
            $candidate = rtrim((string)$candidate, '/\\');
            if ($candidate !== '' && is_file($candidate . '/config/bootstrap.php')) {
                $projectRoot = $candidate;
                break;
            }
        }
        if ($projectRoot === '') {
            throw new RuntimeException('No se encontró la raíz privada de la aplicación.');
        }
    }

    require $projectRoot . '/config/bootstrap.php';
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    require $projectRoot . '/routes/Router.php';
    $router = new App\Routes\Router();
    require $projectRoot . '/routes/web.php';
    $router->dispatch(request_method(), $_SERVER['REQUEST_URI'] ?? '/');
    ob_end_flush();
} catch (Throwable $error) {
    while (ob_get_level() > $initialBufferLevel) ob_end_clean();
    if (class_exists(App\Services\ErrorHandler::class)) {
        App\Services\ErrorHandler::render($error);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Ocurrió un error interno.';
    }
}

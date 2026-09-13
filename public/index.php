<?php
// Buffer rendering so failures never leave a partial page containing internal output.
$initialBufferLevel = ob_get_level();
ob_start();
try {
    require dirname(__DIR__) . '/config/bootstrap.php';
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    require dirname(__DIR__) . '/routes/Router.php';
    $router = new App\Routes\Router();
    require dirname(__DIR__) . '/routes/web.php';
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

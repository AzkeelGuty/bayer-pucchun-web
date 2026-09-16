<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;

final class ErrorHandler
{
    public static function render(\Throwable $error): void
    {
        $status = $error instanceof HttpException ? $error->status : 500;
        $message = $error instanceof HttpException ? $error->getMessage() : 'Ocurrió un error interno. Inténtelo nuevamente.';
        if ($status === 500) {
            \log_event('internal_error', ['type' => get_class($error), 'code' => (string) $error->getCode()]);
        }
        http_response_code($status);
        header('Cache-Control: no-store');

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'code' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        $view = \base_path('app/Views/errors/error.php');
        if (is_file($view)) {
            require $view;
            return;
        }
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title><h1>' . $status . '</h1><p>' . \e($message) . '</p></html>';
    }
}

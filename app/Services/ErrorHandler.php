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
        $debugDetails = null;

        if ($status === 500) {
            // No incluir consultas, parámetros ni datos personales en el registro de producción.
            $context = [
                'type' => get_class($error),
                'code' => (string) $error->getCode(),
                'path' => (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
            ];
            // Las pruebas aisladas no cargan necesariamente todos los Helpers.
            $local = function_exists('is_local_env') && \is_local_env();
            if ($local) {
                $context['message'] = $error->getMessage();
                $context['file'] = $error->getFile();
                $context['line'] = $error->getLine();
                if (function_exists('config') && \config('app.debug', false)) {
                    $debugDetails = get_class($error) . ': ' . $error->getMessage()
                        . "\nArchivo: " . $error->getFile() . ':' . $error->getLine();
                }
            }
            \log_event('internal_error', $context);
        }

        http_response_code($status);
        header('Cache-Control: no-store');

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            $payload = ['success' => false, 'code' => $status, 'message' => $message];
            if ($debugDetails !== null) $payload['debug'] = $debugDetails;
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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

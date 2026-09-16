<?php
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
load_env(dirname(__DIR__) . '/.env');
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) {
        $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
date_default_timezone_set(config('app.timezone'));
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
App\Services\SessionService::start();

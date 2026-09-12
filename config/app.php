<?php
return [
    'name' => env('APP_NAME', 'Bayer-Pucchun Data Hub'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim(env('APP_URL', ''), '/'),
    'timezone' => env('APP_TIMEZONE', 'America/Lima'),
    'session_name' => env('SESSION_NAME', 'bayer_pucchun_session'),
    'session_timeout' => (int) env('SESSION_TIMEOUT', 1800),
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_window' => (int) env('LOGIN_WINDOW', 900),
    'api_enabled' => filter_var(env('API_ENABLED', false), FILTER_VALIDATE_BOOL),
    'api_token' => env('API_TOKEN', ''),
];

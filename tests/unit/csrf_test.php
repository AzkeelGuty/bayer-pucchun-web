<?php
declare(strict_types=1);

// Isolated CLI workers exercise exit() without loading .env, sessions or the database.
require dirname(__DIR__, 2) . '/app/Helpers/functions.php';

$token = str_repeat('a', 64);
$cases = [
    'valid' => ['POST', ['_csrf' => $token], ['_csrf' => $token], [], true],
    'both_missing' => ['POST', [], [], [], false],
    'session_missing' => ['POST', [], ['_csrf' => $token], [], false],
    'submitted_missing' => ['POST', ['_csrf' => $token], [], [], false],
    'both_empty' => ['POST', ['_csrf' => ''], ['_csrf' => ''], [], false],
    'session_empty' => ['POST', ['_csrf' => ''], ['_csrf' => $token], [], false],
    'submitted_empty' => ['POST', ['_csrf' => $token], ['_csrf' => ''], [], false],
    'different' => ['POST', ['_csrf' => $token], ['_csrf' => str_repeat('b', 64)], [], false],
    'submitted_array' => ['POST', ['_csrf' => $token], ['_csrf' => [$token]], [], false],
    'session_array' => ['POST', ['_csrf' => [$token]], ['_csrf' => $token], [], false],
    'numeric_tokens' => ['POST', ['_csrf' => 123], ['_csrf' => 123], [], false],
    'null_tokens' => ['POST', ['_csrf' => null], ['_csrf' => null], [], false],
    'query_token_only' => ['POST', ['_csrf' => $token], [], ['_csrf' => $token], false],
    'get_without_token' => ['GET', [], [], [], true],
];

if (($argv[1] ?? '') === '--case') {
    [$method, $session, $post, $get] = $cases[$argv[2]];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SESSION = $session;
    $_POST = $post;
    $_GET = $get;
    http_response_code(200);
    $continued = false;
    ob_start();
    register_shutdown_function(static function () use (&$continued): void {
        $body = ob_get_clean();
        echo json_encode(['status' => http_response_code(), 'continued' => $continued, 'body' => $body], JSON_THROW_ON_ERROR);
    });
    verify_csrf();
    $continued = true;
    exit;
}

foreach ($cases as $name => $case) {
    $process = proc_open([PHP_BINARY, __FILE__, '--case', $name],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start CSRF test worker.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    $allowed = $case[4];
    if ($exitCode !== 0 || $errors !== '' || $result !== [
        'status' => $allowed ? 200 : 419,
        'continued' => $allowed,
        'body' => $allowed ? '' : 'CSRF token inválido.',
    ]) {
        throw new RuntimeException('CSRF case failed: ' . $name);
    }
}

echo 'CSRF: ' . count($cases) . " cases OK\n";

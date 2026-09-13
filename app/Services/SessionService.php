<?php
declare(strict_types=1);

namespace App\Services;

final class SessionService
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name(\config('app.session_name'));
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $secure = ($https !== '' && $https !== 'off' && $https !== '0')
            || parse_url((string) \config('app.url'), PHP_URL_SCHEME) === 'https';
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('Session unavailable');
        }
    }

    public static function expired(int $now): bool
    {
        if (!isset($_SESSION['auth_user'])) {
            return false;
        }
        $last = $_SESSION['_last_activity'] ?? null;
        return !is_int($last) || $last > $now
            || $now - $last >= max(60, (int) \config('app.session_timeout', 1800));
    }

    public static function establish(array $user): void
    {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Session renewal failed');
        }
        $_SESSION['auth_user'] = $user;
        $_SESSION['_last_activity'] = time();
        \csrf_token();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600, 'path' => $cookie['path'],
                'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'], 'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
            session_destroy();
        }
    }
}

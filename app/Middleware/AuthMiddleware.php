<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Services\SessionService;

final class AuthMiddleware
{
    public function refresh(): void
    {
        if (!\auth_user()) {
            return;
        }
        $auth = new AuthService();
        $id = (int) \auth_user()['id'];
        if (SessionService::expired(time())) {
            $auth->recordAccess('session_timeout', $id);
            SessionService::destroy();
            SessionService::start();
            return;
        }
        $user = $auth->user($id);
        if (!$user) {
            $auth->recordAccess('session_revoked', null);
            SessionService::destroy();
            SessionService::start();
            return;
        }
        $_SESSION['auth_user'] = $user;
        $_SESSION['_last_activity'] = time();
    }

    public function handle(): void
    {
        \require_auth();
    }
}

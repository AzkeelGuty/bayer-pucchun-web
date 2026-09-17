<?php
declare(strict_types=1);
namespace App\Policies;

use App\Exceptions\HttpException;
use App\Services\PermissionService;

/** Additional persisted grant; Controllers retain their existing role restrictions. */
final class OperationalPermissionPolicy
{
    public static function require(string $permission): void
    {
        $user = \auth_user();
        if (!$user || !(new PermissionService())->allows($user, $permission)) {
            throw new HttpException(403, 'No tiene permisos para esta operación.');
        }
    }
}

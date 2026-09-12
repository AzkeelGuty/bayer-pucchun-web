<?php
declare(strict_types=1);

namespace App\Policies;

final class AccessPolicy
{
    public const INTERNAL = ['ADMIN', 'DIGITADOR', 'SUPERVISOR', 'GERENCIA'];
    public const CAPTURE = ['ADMIN', 'DIGITADOR'];
    public const REVIEW = ['ADMIN', 'SUPERVISOR'];
    public const PUBLISHED = ['ADMIN', 'SUPERVISOR', 'GERENCIA', 'BAYER'];

    public static function roles(?array $user): array
    {
        $roles = $user['roles'] ?? (isset($user['role']) ? [$user['role']] : []);
        return is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [];
    }

    public static function allows(?array $user, array $allowed): bool
    {
        $roles = self::roles($user);
        // An external account never gains internal access through an additional role.
        if (in_array('BAYER', $roles, true)) {
            return in_array('BAYER', $allowed, true);
        }
        return (bool) array_intersect($roles, $allowed);
    }

    public static function landing(?array $user): string
    {
        return in_array('BAYER', self::roles($user), true) ? '/bayer' : '/dashboard';
    }
}

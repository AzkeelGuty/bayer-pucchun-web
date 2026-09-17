<?php
declare(strict_types=1);
namespace App\Policies;

use App\Exceptions\HttpException;

/** Match the existing Document rule: a digitador without a broader internal role is scoped. */
final class OperationalOwnershipPolicy
{
    private static function ownerId(): ?int
    {
        if (!\has_role('DIGITADOR') || \has_role('ADMIN','SUPERVISOR','GERENCIA')) return null;
        $id = (int)(\auth_user()['id'] ?? 0);
        if ($id < 1) throw new HttpException(403, 'No tiene acceso a este registro.');
        return $id;
    }

    public static function scope(array $filters): array
    {
        $owner = self::ownerId();
        if ($owner !== null) $filters['created_by'] = $owner;
        return $filters;
    }

    public static function require(array $header): void
    {
        $owner = self::ownerId();
        if ($owner !== null && (int)($header['created_by'] ?? 0) !== $owner) {
            throw new HttpException(403, 'No tiene acceso a este registro.');
        }
    }
}

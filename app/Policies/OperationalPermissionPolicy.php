<?php
declare(strict_types=1);
namespace App\Policies;

use App\Exceptions\HttpException;
use App\Services\PermissionService;

/** Additional persisted grant; Controllers retain their existing role restrictions. */
final class OperationalPermissionPolicy
{
    /** Review includes returning observations; publication includes withdrawing published data. */
    public static function workflow(mixed $target): string
    {
        if (!is_string($target)) {
            throw new HttpException(422, 'Estado de destino inválido.');
        }
        $target = strtoupper(trim($target));
        $permission = match ($target) {
            'VALIDADO', 'OBSERVADO', 'BORRADOR' => 'validation.review',
            'PUBLICADO', 'ANULADO' => 'publications.publish',
            default => throw new HttpException(422, 'Estado de destino inválido.'),
        };
        self::require($permission);
        return $target;
    }

    public static function require(string $permission): void
    {
        $user = \auth_user();
        if (!$user || !(new PermissionService())->allows($user, $permission)) {
            throw new HttpException(403, 'No tiene permisos para esta operación.');
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Middleware;

final class PermissionMiddleware
{
    public function __construct(private string $permission) {}

    public function handle(): void
    {
        \require_auth();
        if (!(new \App\Services\PermissionService())->allows(\auth_user(), $this->permission)) {
            throw new \App\Exceptions\HttpException(403, 'No tiene permisos para esta operación.');
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Middleware;

final class RoleMiddleware
{
    public function __construct(private array $roles) {}

    public function handle(): void
    {
        \require_role(...$this->roles);
    }
}

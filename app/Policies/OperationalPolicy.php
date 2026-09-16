<?php
declare(strict_types=1);
namespace App\Policies;

use App\Exceptions\HttpException;
use App\Services\PermissionService;

final class OperationalPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function authorize(array $user, string $module, string $operation): void
    {
        if (!in_array($module, ['documents', 'guides', 'stock'], true)
            || !in_array($operation, ['read', 'create'], true)) {
            throw new HttpException(403, 'Operación no autorizada.');
        }
        $roles = $operation === 'create' ? AccessPolicy::CAPTURE : AccessPolicy::INTERNAL;
        if (!AccessPolicy::allows($user, $roles) || !$this->permissions->allows($user, "$module.$operation")) {
            throw new HttpException(403, 'No tiene permisos para esta operación.');
        }
    }

    public function workflow(array $user, string $target): void
    {
        $permission=in_array($target,['PUBLICADO','ANULADO'],true) ? 'publications.publish' : 'validation.review';
        if (!AccessPolicy::allows($user,AccessPolicy::REVIEW) || !$this->permissions->allows($user,$permission)) {
            throw new HttpException(403,'No tiene permisos para esta transición.');
        }
    }

    public function ownOnly(array $user): bool
    {
        return !AccessPolicy::allows($user, ['ADMIN', 'SUPERVISOR', 'GERENCIA']);
    }

    public function visible(array $user, array $header): void
    {
        if ($this->ownOnly($user) && (int) $header['created_by'] !== (int) $user['id']) {
            throw new HttpException(403, 'No tiene acceso a esta carga.');
        }
    }
}

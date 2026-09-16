<?php
declare(strict_types=1);

namespace App\Services;

use App\Policies\AccessPolicy;

/** Resolves only persisted grants. No implicit ADMIN grants or invented permission seed. */
final class PermissionService
{
    public function __construct(private ?\PDO $pdo = null) {}

    public function forUser(int $userId): array
    {
        $statement = ($this->pdo ?? \db())->prepare(
            'SELECT DISTINCT p.codigo FROM permisos p JOIN rol_permiso rp ON rp.permiso_id=p.id
             JOIN roles r ON r.id=rp.rol_id JOIN usuario_rol ur ON ur.rol_id=r.id
             JOIN usuarios u ON u.id=ur.usuario_id WHERE u.id=? AND u.estado=1 AND r.estado=1 ORDER BY p.codigo'
        );
        $statement->execute([$userId]);
        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function allows(array $user, string $permission): bool
    {
        if (in_array('BAYER', AccessPolicy::roles($user), true)
            && !in_array($permission, ['dashboard.bayer.view', 'published_data.view', 'exports.create', 'api.consume'], true)) {
            return false;
        }
        return in_array($permission, $this->forUser((int) $user['id']), true);
    }
}

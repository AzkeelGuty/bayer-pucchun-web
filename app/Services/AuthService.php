<?php
declare(strict_types=1);

namespace App\Services;

use App\Policies\AccessPolicy;

class AuthService
{
    public function __construct(private ?\PDO $pdo = null) {}

    private function connection(): \PDO
    {
        return $this->pdo ?? \db();
    }

    public function user(int $id): ?array
    {
        $st = $this->connection()->prepare('SELECT id,nombre,email FROM usuarios WHERE id=? AND estado=1');
        $st->execute([$id]);
        $user = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        $st = $this->connection()->prepare('SELECT r.nombre FROM roles r JOIN usuario_rol ur ON ur.rol_id=r.id WHERE ur.usuario_id=? AND r.estado=1 ORDER BY r.nombre');
        $st->execute([$id]);
        $roles = array_values(array_intersect($st->fetchAll(\PDO::FETCH_COLUMN), [...AccessPolicy::INTERNAL, 'BAYER']));
        if (!$roles) {
            return null;
        }
        $user['roles'] = $roles;
        $user['role'] = in_array('BAYER', $roles, true) ? 'BAYER' : $roles[0];
        return $user;
    }

    public function attempt(string $email, string $password): bool
    {
        $st = $this->connection()->prepare('SELECT id,password_hash,estado FROM usuarios WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $id = $row ? (int) $row['id'] : null;
        if ($this->blocked($id)) {
            $this->recordAccess('login_blocked', $id);
            return false;
        }
        // Dummy password verification for unknown accounts; never accepted as credentials.
        $hash = $row['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $hash);
        $user = $row && (int) $row['estado'] === 1 && $valid ? $this->user($id) : null;
        if (!$user) {
            $this->recordAccess('login_failed', $id);
            return false;
        }
        SessionService::establish($user);
        $this->recordAccess('login', $id);
        return true;
    }

    private function blocked(?int $userId): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(60, (int) \config('app.login_window', 900)));
        $st = $this->connection()->prepare("SELECT COUNT(*) FROM bitacora_acceso WHERE accion='login_failed' AND fecha_hora>=? AND (usuario_id=? OR ip=?)");
        $st->execute([$cutoff, $userId, self::ip()]);
        return (int) $st->fetchColumn() >= max(1, (int) \config('app.login_max_attempts', 5));
    }

    public static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    public function recordAccess(string $action, ?int $userId): void
    {
        try {
            $st = $this->connection()->prepare('INSERT INTO bitacora_acceso(usuario_id,ip,user_agent,accion,fecha_hora) VALUES(?,?,?,?,CURRENT_TIMESTAMP)');
            $agent = preg_replace('/[^\x20-\x7E]/', '?', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $st->execute([$userId, self::ip(), substr($agent, 0, 255), $action]);
        } catch (\Throwable $error) {
            \log_event('access_audit_error', ['type' => get_class($error)]);
        }
    }

    public function logout(): void
    {
        if (\auth_user()) {
            $this->recordAccess('logout', (int) \auth_user()['id']);
        }
        SessionService::destroy();
    }
}

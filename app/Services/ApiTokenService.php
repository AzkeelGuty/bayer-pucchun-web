<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Policies\AccessPolicy;
use PDO;

final class ApiTokenService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo=null)
    {
        $this->pdo=$pdo ?? \db();
    }

    public function issue(string $email,string $password): array
    {
        $this->assertStorageReady();

        $email=trim($email);
        $st=$this->pdo->prepare('SELECT id,password_hash,estado FROM usuarios WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        $userId=$row ? (int)$row['id'] : null;

        if($this->blocked($userId)){
            $this->record('api_login_blocked',$userId);
            throw new HttpException(429,'Demasiados intentos. Espere unos minutos antes de volver a intentar.');
        }

        // Verificación ficticia para cuentas inexistentes: evita diferencias claras de tiempo.
        $hash=$row['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid=password_verify($password,$hash);
        $user=$row && (int)$row['estado']===1 && $valid
            ? (new AuthService($this->pdo))->user($userId)
            : null;

        if(!$user){
            $this->record('api_login_failed',$userId);
            throw new HttpException(401,'Correo o contraseña incorrectos.');
        }

        if(!AccessPolicy::allows($user,AccessPolicy::PUBLISHED)){
            $this->record('api_login_denied',$userId);
            throw new HttpException(403,'La cuenta no tiene permiso para consumir la API publicada.');
        }

        $ttl=max(900,min(86400,(int)\config('app.api_token_ttl',28800)));
        $token='puc_'.bin2hex(random_bytes(32));
        $tokenHash=hash('sha256',$token);
        $ip=AuthService::ip();
        $agent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255);

        $this->pdo->beginTransaction();
        try{
            // Un nuevo login revoca los tokens anteriores de esa cuenta.
            $revoke=$this->pdo->prepare('UPDATE api_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE usuario_id=? AND revoked_at IS NULL');
            $revoke->execute([$userId]);

            $insert=$this->pdo->prepare(
                'INSERT INTO api_tokens(usuario_id,token_hash,expires_at,ip_created,user_agent_created)
                 VALUES(?,?,TIMESTAMPADD(SECOND,?,CURRENT_TIMESTAMP),?,?)'
            );
            $insert->execute([$userId,$tokenHash,$ttl,$ip,$agent!==''?$agent:null]);
            $id=(int)$this->pdo->lastInsertId();

            $expires=$this->pdo->prepare('SELECT expires_at FROM api_tokens WHERE id=?');
            $expires->execute([$id]);
            $expiresAt=(string)$expires->fetchColumn();

            $this->pdo->commit();
        }catch(\Throwable $error){
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $this->record('api_login',$userId);

        return [
            'access_token'=>$token,
            'token_type'=>'Bearer',
            'expires_in'=>$ttl,
            'expires_at'=>$expiresAt,
            'user'=>[
                'id'=>(int)$user['id'],
                'nombre'=>(string)$user['nombre'],
                'email'=>(string)$user['email'],
                'roles'=>AccessPolicy::roles($user),
            ],
        ];
    }

    public function authenticate(string $token): array
    {
        $this->assertStorageReady();

        $token=trim($token);
        if($token==='' || strlen($token)>160 || !str_starts_with($token,'puc_')){
            throw new HttpException(401,'Token Bearer inválido o vencido.');
        }

        $hash=hash('sha256',$token);
        $st=$this->pdo->prepare(
            'SELECT t.id,t.usuario_id
             FROM api_tokens t
             JOIN usuarios u ON u.id=t.usuario_id AND u.estado=1
             WHERE t.token_hash=?
               AND t.revoked_at IS NULL
               AND t.expires_at>CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $st->execute([$hash]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row){
            throw new HttpException(401,'Token Bearer inválido o vencido.');
        }

        $user=(new AuthService($this->pdo))->user((int)$row['usuario_id']);
        if(!$user || !AccessPolicy::allows($user,AccessPolicy::PUBLISHED)){
            $this->revoke($token);
            throw new HttpException(403,'La cuenta asociada al token ya no tiene acceso a la API.');
        }

        $touch=$this->pdo->prepare('UPDATE api_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE id=?');
        $touch->execute([(int)$row['id']]);

        return $user;
    }

    public function revoke(string $token): void
    {
        $this->assertStorageReady();
        $hash=hash('sha256',trim($token));
        $st=$this->pdo->prepare('UPDATE api_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE token_hash=? AND revoked_at IS NULL');
        $st->execute([$hash]);
    }

    public function assertStorageReady(): void
    {
        $st=$this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_tokens'"
        );
        $st->execute();
        if((int)$st->fetchColumn()!==1){
            throw new HttpException(503,'Falta ejecutar la migración 005_api_tokens.sql.');
        }
    }

    private function blocked(?int $userId): bool
    {
        $window=max(60,(int)\config('app.login_window',900));
        $limit=max(1,(int)\config('app.login_max_attempts',5));
        $ip=AuthService::ip();

        $st=$this->pdo->prepare(
            "SELECT COUNT(*) FROM bitacora_acceso
             WHERE accion='api_login_failed'
               AND fecha_hora>=TIMESTAMPADD(SECOND,-?,CURRENT_TIMESTAMP)
               AND (usuario_id=? OR ip=?)"
        );
        $st->execute([$window,$userId,$ip]);
        return (int)$st->fetchColumn()>=$limit;
    }

    private function record(string $action,?int $userId): void
    {
        (new AuthService($this->pdo))->recordAccess($action,$userId);
    }
}

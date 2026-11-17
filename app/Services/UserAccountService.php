<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use PDOException;
use Throwable;

/** Gestión administrativa y perfil propio. Las contraseñas no se devuelven en las consultas. */
final class UserAccountService
{
    public const ROLES=['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'];

    public function __construct(private ?PDO $pdo=null){$this->pdo??=\db();}

    public function get(int $id): array
    {
        if($id<1)throw new HttpException(404,'Usuario no encontrado.');
        $s=$this->pdo->prepare(
            "SELECT u.id,u.nombre,u.email,u.foto_perfil,u.cargo,u.telefono,u.estado,
                u.created_at,u.actualizado_en,
                (SELECT r.nombre FROM usuario_rol ur JOIN roles r ON r.id=ur.rol_id
                   WHERE ur.usuario_id=u.id AND r.estado=1 ORDER BY r.id LIMIT 1) AS role
             FROM usuarios u WHERE u.id=?"
        );
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: throw new HttpException(404,'Usuario no encontrado.');
    }

    private function data(array $data): array
    {
        $name=trim((string)($data['nombre']??''));
        $email=mb_strtolower(trim((string)($data['email']??'')),'UTF-8');
        $position=trim((string)($data['cargo']??''));
        $phone=trim((string)($data['telefono']??''));
        if($name===''||mb_strlen($name)>120)throw new HttpException(422,'Indique un nombre válido de máximo 120 caracteres.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>120)throw new HttpException(422,'El correo no es válido.');
        if(mb_strlen($position)>100)throw new HttpException(422,'El cargo debe tener como máximo 100 caracteres.');
        if($phone!==''&&!preg_match('/^[+\d ()-]{6,25}$/D',$phone))
            throw new HttpException(422,'El teléfono solo admite cifras, espacios, paréntesis, + y guiones.');
        return [$name,$email,$position?:null,$phone?:null];
    }

    /** Actualizar datos y rol como una sola operación. El rol no se cambia a sí mismo. */
    public function update(int $id,array $data,bool $asAdmin,int $actorId): void
    {
        $current=$this->get($id);
        if(!$asAdmin && $id!==$actorId)
            throw new HttpException(403,'Solo puede editar su propio perfil.');
        [$name,$email,$position,$phone]=$this->data($data);
        if(!$asAdmin)$position=$current['cargo']; // el cargo lo asigna el administrador
        $role=$current['role'];
        if($asAdmin && array_key_exists('role',$data)){
            $proposed=strtoupper(trim((string)$data['role']));
            if(!in_array($proposed,self::ROLES,true))throw new HttpException(422,'Rol no reconocido.');
            if($id===$actorId && $proposed!=='ADMIN')
                throw new HttpException(422,'No puede quitarse su propio rol ADMIN.');
            $role=$proposed;
        }
        $own=!$this->pdo->inTransaction();
        if($own)$this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare(
                'UPDATE usuarios SET nombre=?,email=?,cargo=?,telefono=?,actualizado_en=NOW() WHERE id=?'
            );
            $stmt->execute([$name,$email,$position,$phone,$id]);
            if($asAdmin && $role!==$current['role'])$this->changeRoleInTransaction($id,$role);
            if($own)$this->pdo->commit();
        }catch(PDOException $error){
            if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();
            if((int)($error->errorInfo[1]??0)===1062)
                throw new HttpException(409,'Ese correo ya pertenece a otra cuenta.');
            throw $error;
        }catch(Throwable $error){
            if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();
            throw $error;
        }
    }

    public function setRole(int $id,string $role,int $actorId): void
    {
        $role=strtoupper(trim($role));
        if(!in_array($role,self::ROLES,true))throw new HttpException(422,'Rol inválido.');
        $this->get($id);
        if($id===$actorId && $role!=='ADMIN')
            throw new HttpException(422,'No puede quitarse su propio rol ADMIN.');
        $this->pdo->beginTransaction();
        try{
            $this->changeRoleInTransaction($id,$role);
            $this->pdo->commit();
        }catch(Throwable $error){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $error;
        }
    }

    private function changeRoleInTransaction(int $id,string $role): void
    {
        $this->pdo->prepare('DELETE FROM usuario_rol WHERE usuario_id=?')->execute([$id]);
        $stmt=$this->pdo->prepare(
            'INSERT INTO usuario_rol(usuario_id,rol_id) SELECT ?,id FROM roles WHERE nombre=? AND estado=1'
        );
        $stmt->execute([$id,$role]);
        if($stmt->rowCount()!==1)throw new HttpException(422,'Rol no disponible.');
    }

    public function setStatus(int $id,bool $enabled,int $actorId): void
    {
        $user=$this->get($id);
        if(!$enabled&&$id===$actorId)throw new HttpException(422,'No puede desactivar su propia cuenta.');
        if(!$enabled && $user['role']==='ADMIN'){
            $n=(int)$this->pdo->query(
                "SELECT COUNT(DISTINCT u.id) FROM usuarios u JOIN usuario_rol ur ON ur.usuario_id=u.id
                 JOIN roles r ON r.id=ur.rol_id WHERE u.estado=1 AND r.nombre='ADMIN'"
            )->fetchColumn();
            if($n<=1)throw new HttpException(422,'No puede desactivar al último administrador activo.');
        }
        $st=$this->pdo->prepare('UPDATE usuarios SET estado=?,actualizado_en=NOW() WHERE id=?');
        $st->execute([$enabled?1:0,$id]);
        if($st->rowCount()===0 && (int)$user['estado']!==($enabled?1:0))
            throw new HttpException(404,'Usuario no encontrado.');
    }

    public function resetPassword(int $id,string $password): void
    {
        $this->get($id);
        if(strlen($password)<10||strlen($password)>256)
            throw new HttpException(422,'La contraseña debe tener entre 10 y 256 caracteres.');
        $stmt=$this->pdo->prepare(
            'UPDATE usuarios SET password_hash=?,actualizado_en=NOW() WHERE id=?'
        );
        $stmt->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        // Revocar tokens API del usuario tras el restablecimiento de credenciales.
        $this->pdo->prepare('UPDATE api_tokens SET revoked_at=NOW() WHERE usuario_id=? AND revoked_at IS NULL')
            ->execute([$id]);
    }

    public function changeOwnPassword(int $id,string $current,string $next): void
    {
        $this->get($id);
        $stmt=$this->pdo->prepare('SELECT password_hash FROM usuarios WHERE id=?');
        $stmt->execute([$id]);
        $hash=(string)$stmt->fetchColumn();
        if(!password_verify($current,$hash))
            throw new HttpException(422,'La contraseña actual no es correcta.');
        if(hash_equals($current,$next))
            throw new HttpException(422,'La nueva contraseña debe ser diferente.');
        $this->resetPassword($id,$next);
    }

    public function setPhoto(int $id,?string $file): ?string
    {
        $existing=$this->get($id);
        $old=$existing['foto_perfil'];
        $st=$this->pdo->prepare('UPDATE usuarios SET foto_perfil=?,actualizado_en=NOW() WHERE id=?');
        $st->execute([$file,$id]);
        return is_string($old)?$old:null;
    }
}

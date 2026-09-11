<?php
namespace App\Services;
class AuthService {
    public function attempt(string $email,string $password): bool {
        $sql='SELECT u.id,u.nombre,u.email,u.password_hash,r.nombre role FROM usuarios u JOIN usuario_rol ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id WHERE u.email=? AND u.estado=1 LIMIT 1';
        $st=\db()->prepare($sql);$st->execute([$email]);$u=$st->fetch();
        if(!$u || !password_verify($password,$u['password_hash'])) return false;
        unset($u['password_hash']); $_SESSION['auth_user']=$u; session_regenerate_id(true); \audit('auth','login',$u['id']); return true;
    }
    public function logout(): void { if(\auth_user()) \audit('auth','logout',\auth_user()['id']); unset($_SESSION['auth_user']); session_regenerate_id(true); }
}

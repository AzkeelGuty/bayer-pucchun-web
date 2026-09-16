<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\AccessPolicy;

final class UserAdminController
{
    private const ROLES=['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'];

    public function store(): void
    {
        \require_role('ADMIN');
        $name=trim((string)\input('nombre',''));
        $email=strtolower(trim((string)\input('email','')));
        $password=(string)\input('password','');
        $role=strtoupper(trim((string)\input('role','')));

        if($name==='' || mb_strlen($name)>120) throw new HttpException(422,'Nombre inválido.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>120) throw new HttpException(422,'Correo inválido.');
        if(strlen($password)<10) throw new HttpException(422,'La contraseña temporal debe tener al menos 10 caracteres.');
        if(!in_array($role,self::ROLES,true)) throw new HttpException(422,'Rol inválido.');

        $pdo=\db();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare('INSERT INTO usuarios(nombre,email,password_hash,estado,created_at) VALUES(?,?,?,1,CURRENT_TIMESTAMP)');
            $st->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $id=(int)$pdo->lastInsertId();
            $st=$pdo->prepare('INSERT INTO usuario_rol(usuario_id,rol_id) SELECT ?,id FROM roles WHERE nombre=? AND estado=1');
            $st->execute([$id,$role]);
            if($st->rowCount()!==1) throw new \RuntimeException('Rol no disponible.');
            $pdo->commit();
            \audit('usuarios','crear',$id);
            \flash('success','Usuario creado correctamente.');
        }catch(\PDOException $e){
            if($pdo->inTransaction())$pdo->rollBack();
            if((int)($e->errorInfo[1]??0)===1062) throw new HttpException(409,'Ya existe un usuario con ese correo.');
            throw $e;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        \redirect('/seguridad');
    }

    public function status(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $state=(int)\input('estado',0);
        if($id<1 || !in_array($state,[0,1],true)) throw new HttpException(422,'Solicitud inválida.');
        if($id===(int)\auth_user()['id'] && $state===0) throw new HttpException(422,'No puede desactivar su propia cuenta.');
        $st=\db()->prepare('UPDATE usuarios SET estado=? WHERE id=?');
        $st->execute([$state,$id]);
        \audit('usuarios',$state?'activar':'desactivar',$id);
        \flash('success','Estado del usuario actualizado.');
        \redirect('/seguridad');
    }

    public function role(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $role=strtoupper(trim((string)\input('role','')));
        if($id<1 || !in_array($role,self::ROLES,true)) throw new HttpException(422,'Solicitud inválida.');
        $pdo=\db();$pdo->beginTransaction();
        try{
            $pdo->prepare('DELETE FROM usuario_rol WHERE usuario_id=?')->execute([$id]);
            $st=$pdo->prepare('INSERT INTO usuario_rol(usuario_id,rol_id) SELECT ?,id FROM roles WHERE nombre=? AND estado=1');
            $st->execute([$id,$role]);
            if($st->rowCount()!==1) throw new \RuntimeException('Rol no disponible.');
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        \audit('usuarios','asignar_rol',$id);
        \flash('success','Rol actualizado.');
        \redirect('/seguridad');
    }
}

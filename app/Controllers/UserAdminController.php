<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\UserAccountService;
use App\Services\UserPhotoService;
use App\Services\AuthService;
use App\Services\SessionService;
use PDOException;
use Throwable;

/** Administración de cuentas y perfil propio, siempre con CSRF y sesión activa. */
final class UserAdminController
{
    public function store(): void
    {
        \require_role('ADMIN');
        $name=trim((string)\input('nombre',''));
        $email=mb_strtolower(trim((string)\input('email','')),'UTF-8');
        $password=(string)\input('password','');
        $role=strtoupper(trim((string)\input('role','')));
        $position=trim((string)\input('cargo',''));
        $phone=trim((string)\input('telefono',''));
        if($name===''||mb_strlen($name)>120)throw new HttpException(422,'Nombre inválido.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>120)
            throw new HttpException(422,'Correo inválido.');
        if(strlen($password)<10||strlen($password)>256)
            throw new HttpException(422,'La contraseña temporal debe tener entre 10 y 256 caracteres.');
        if(!in_array($role,UserAccountService::ROLES,true))
            throw new HttpException(422,'Rol inválido.');
        if(mb_strlen($position)>100 ||
            ($phone!==''&&!preg_match('/^[+\d ()-]{6,25}$/D',$phone)))
            throw new HttpException(422,'Revise el cargo o teléfono.');

        $photo=UserPhotoService::store($_FILES['foto']??[]);
        $pdo=\db();$pdo->beginTransaction();
        try{
            $st=$pdo->prepare(
                'INSERT INTO usuarios(nombre,email,password_hash,foto_perfil,cargo,telefono,estado,created_at)
                 VALUES(?,?,?,?,?,?,1,CURRENT_TIMESTAMP)'
            );
            $st->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$photo,
                $position?:null,$phone?:null]);
            $id=(int)$pdo->lastInsertId();
            $st=$pdo->prepare(
                'INSERT INTO usuario_rol(usuario_id,rol_id)
                 SELECT ?,id FROM roles WHERE nombre=? AND estado=1'
            );
            $st->execute([$id,$role]);
            if($st->rowCount()!==1)throw new HttpException(422,'Rol no disponible.');
            $pdo->commit();
        }catch(PDOException $error){
            if($pdo->inTransaction())$pdo->rollBack();
            UserPhotoService::delete($photo);
            if((int)($error->errorInfo[1]??0)===1062)
                throw new HttpException(409,'Ya existe un usuario con ese correo.');
            throw $error;
        }catch(Throwable $error){
            if($pdo->inTransaction())$pdo->rollBack();
            UserPhotoService::delete($photo);
            throw $error;
        }
        \audit('usuarios','crear',$id);
        \flash('success','Usuario creado correctamente.');
        \redirect('/seguridad');
    }

    public function edit(): void
    {
        \require_role('ADMIN');
        $user=(new UserAccountService())->get((int)\input('id',0));
        \view('backoffice.user_edit',['profile'=>$user,'selfEdit'=>false]);
    }

    public function profile(): void
    {
        \require_auth();
        $user=(new UserAccountService())->get((int)\auth_user()['id']);
        \view('backoffice.user_edit',['profile'=>$user,'selfEdit'=>true]);
    }

    public function update(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $this->updateData($id,true,'/seguridad/usuarios/editar?id='.$id);
    }

    public function updateProfile(): void
    {
        \require_auth();
        $this->updateData((int)\auth_user()['id'],false,'/mi-perfil');
    }

    private function updateData(int $id,bool $admin,string $back): void
    {
        $service=new UserAccountService();
        $current=$service->get($id);
        $uploaded=UserPhotoService::store($_FILES['foto']??[]);
        $remove=($admin||$id===(int)\auth_user()['id']) &&
            (string)\input('eliminar_foto','')==='1';
        $newPhoto=$uploaded ?? ($remove?null:$current['foto_perfil']);
        $changed=$uploaded!==null || $remove;
        $pdo=\db();$pdo->beginTransaction();
        try{
            $service->update($id,$_POST,$admin,(int)\auth_user()['id']);
            if($changed)$service->setPhoto($id,$newPhoto);
            if($admin && array_key_exists('estado',$_POST)){
                $enabled=(string)\input('estado','')==='1';
                $service->setStatus($id,$enabled,(int)\auth_user()['id']);
            }
            $pdo->commit();
        }catch(Throwable $error){
            if($pdo->inTransaction())$pdo->rollBack();
            UserPhotoService::delete($uploaded);
            throw $error;
        }
        if($changed && $current['foto_perfil']!==$newPhoto)
            UserPhotoService::delete($current['foto_perfil']);
        \audit('usuarios',$admin?'editar':'editar_perfil',$id);
        \flash('success',$admin?'Datos del usuario actualizados.':'Tu perfil se actualizó.');
        \redirect($back);
    }

    public function resetPassword(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $password=(string)\input('password_nueva','');
        if($password!==(string)\input('password_confirmar',''))
            throw new HttpException(422,'Las contraseñas no coinciden.');
        (new UserAccountService())->resetPassword($id,$password);
        \audit('usuarios','restablecer_password',$id);
        \flash('success','Contraseña restablecida. Comuníquela de forma segura.');
        \redirect('/seguridad/usuarios/editar?id='.$id);
    }

    public function changeMyPassword(): void
    {
        \require_auth();
        $id=(int)\auth_user()['id'];
        $next=(string)\input('password_nueva','');
        if($next!==(string)\input('password_confirmar',''))
            throw new HttpException(422,'Las contraseñas nuevas no coinciden.');
        (new UserAccountService())->changeOwnPassword(
            $id,(string)\input('password_actual',''),$next
        );
        \audit('usuarios','cambiar_password_propio',$id);
        \flash('success','Contraseña cambiada correctamente.');
        \redirect('/mi-perfil');
    }

    public function photo(): void
    {
        \require_auth();
        $id=(int)\input('id',0);
        if($id!==(int)\auth_user()['id'] && !\has_role('ADMIN'))
            throw new HttpException(403,'No está autorizado a consultar esta foto.');
        $user=(new UserAccountService())->get($id);
        UserPhotoService::show($user['foto_perfil']);
    }

    public function status(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $raw=(string)\input('estado','');
        if(!in_array($raw,['0','1'],true))throw new HttpException(422,'Estado inválido.');
        (new UserAccountService())->setStatus($id,$raw==='1',(int)\auth_user()['id']);
        \audit('usuarios',$raw==='1'?'activar':'desactivar',$id);
        \flash('success','Estado del usuario actualizado.');
        \redirect('/seguridad');
    }

    public function role(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        (new UserAccountService())->setRole($id,(string)\input('role',''),(int)\auth_user()['id']);
        \audit('usuarios','asignar_rol',$id);
        \flash('success','Rol actualizado.');
        \redirect('/seguridad');
    }
}

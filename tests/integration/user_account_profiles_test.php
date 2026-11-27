<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Exceptions\HttpException;
use App\Services\UserAccountService;
use App\Services\UserPhotoService;

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/016_perfiles_usuarios.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $pdo->exec("INSERT INTO roles(id,nombre) VALUES(10,'ADMIN'),(11,'DIGITADOR'),(12,'BAYER'),(13,'SUPERVISOR')");
    $pdo->exec("INSERT INTO usuario_rol(usuario_id,rol_id) VALUES(1,10),(2,11)");
    $pdo->exec("UPDATE usuarios SET password_hash='".password_hash('ClaveActual-123',PASSWORD_DEFAULT)."' WHERE id=2");

    $svc=new UserAccountService($pdo);
    ensure($svc->get(1)['role']==='ADMIN','Consulta rol ADMIN del perfil');
    ensure($svc->get(2)['role']==='DIGITADOR','Consulta rol de segundo usuario');
    $svc->update(2,[
        'nombre'=>'Usuario de Operaciones','email'=>'usuario2@example.invalid',
        'telefono'=>'+51 999 888 777','cargo'=>'Analista de inventario',
        'role'=>'SUPERVISOR'
    ],true,1);
    $second=$svc->get(2);
    ensure($second['nombre']==='Usuario de Operaciones' &&
        $second['email']==='usuario2@example.invalid' &&
        $second['cargo']==='Analista de inventario' &&
        $second['telefono']==='+51 999 888 777' &&
        $second['role']==='SUPERVISOR',
        'Edición de usuario guarda nombre, correo, cargo, teléfono y rol');

    rejects(fn()=>$svc->update(2,[
        'nombre'=>'Otro','email'=>'fixture@example.invalid'
    ],true,1),HttpException::class);
    ensure($svc->get(2)['email']==='usuario2@example.invalid',
        'Correo duplicado rechaza cambios sin actualizar parcialmente');

    rejects(fn()=>$svc->update(1,[
        'nombre'=>'Test','email'=>'fixture@example.invalid','role'=>'BAYER'
    ],true,1),HttpException::class);
    ensure($svc->get(1)['role']==='ADMIN',
        'ADMIN no puede quitarse a sí mismo el rol de administrador');
    rejects(fn()=>$svc->setRole(1,'DIGITADOR',1),HttpException::class);
    rejects(fn()=>$svc->setStatus(1,false,1),HttpException::class);
    ensure((int)$svc->get(1)['estado']===1,
        'ADMIN no puede desactivar su cuenta');
    rejects(fn()=>$svc->update(1,[
        'nombre'=>'Intruso','email'=>'intruso@example.invalid'
    ],false,2),HttpException::class);

    $svc->update(2,[
        'nombre'=>'Mi Perfil','email'=>'mi-perfil@example.invalid',
        'telefono'=>'999 111 222','cargo'=>'Cargo cambiado sin permiso','role'=>'ADMIN'
    ],false,2);
    $self=$svc->get(2);
    ensure($self['nombre']==='Mi Perfil' &&
        $self['role']==='SUPERVISOR' &&
        $self['cargo']==='Analista de inventario',
        'Un usuario edita sus datos pero no puede promocionarse ni cambiar cargo');
    rejects(fn()=>$svc->changeOwnPassword(2,'ClaveIncorrecta','ClaveNueva-456'),HttpException::class);
    rejects(fn()=>$svc->changeOwnPassword(2,'ClaveActual-123','ClaveActual-123'),HttpException::class);
    $svc->changeOwnPassword(2,'ClaveActual-123','ClaveNueva-456');
    $newHash=$pdo->query('SELECT password_hash FROM usuarios WHERE id=2')->fetchColumn();
    ensure(password_verify('ClaveNueva-456',(string)$newHash) &&
        !password_verify('ClaveActual-123',(string)$newHash),
        'Cambio de contraseña propia verifica la actual y guarda hash seguro');

    $svc->resetPassword(2,'ClaveRestablecida-789');
    $hash=$pdo->query('SELECT password_hash FROM usuarios WHERE id=2')->fetchColumn();
    ensure(password_verify('ClaveRestablecida-789',(string)$hash),
        'Administrador puede restablecer credenciales de otro usuario');
    rejects(fn()=>$svc->resetPassword(2,'123'),HttpException::class);

    $svc->setPhoto(2,str_repeat('a',32).'.jpg');
    ensure($svc->get(2)['foto_perfil']===str_repeat('a',32).'.jpg',
        'La referencia privada de la fotografía se guarda en el perfil');
    ensure(UserPhotoService::validateUpload([])===false,
        'Fotografía opcional no impide crear o editar cuentas');

    $svc->setStatus(2,false,1);
    ensure((int)$svc->get(2)['estado']===0,'ADMIN puede desactivar otra cuenta');
    $svc->setStatus(2,true,1);
    ensure((int)$svc->get(2)['estado']===1,'ADMIN puede reactivar otra cuenta');
    $root=dirname(__DIR__,2);
    $view=file_get_contents($root.'/app/Views/backoffice/security.php');
    $editor=file_get_contents($root.'/app/Views/backoffice/user_edit.php');
    $import=file_get_contents($root.'/app/Views/bayer_catalog/import.php');
    $routes=file_get_contents($root.'/routes/web.php');
    $header=file_get_contents($root.'/app/Views/layouts/header.php');
    ensure(str_contains($view,'usuarios/editar') &&
        str_contains($view,'btn-app-red') &&
        str_contains($view,'avatar-usuario') &&
        str_contains($view,'roleIcons'),
        'Usuarios muestra editar, desactivar rojo, foto y roles con iconos');
    ensure(str_contains($editor,'password_actual') &&
        str_contains($editor,'password_confirmar') &&
        str_contains($editor,'name="foto"') &&
        str_contains($editor,'name="telefono"') &&
        str_contains($editor,'name="cargo"'),
        'Formulario profesional separa datos, foto y contraseña');
    ensure(str_contains($routes,"'/mi-perfil'") &&
        str_contains($routes,"'/avatar-usuario'") &&
        str_contains($header,"'/mi-perfil'"),
        'Perfil propio y fotografías con rutas autenticadas');
    ensure(!str_contains($import,'name="periodo"') &&
        !str_contains($import,'name="fecha_precios"') &&
        str_contains($import,'Cabeceras principales:') &&
        str_contains($import,'btn-app-red btn-with-icon'),
        'Importación Bayer elimina fechas, acorta ayuda y usa botón rojo');
    echo 'USER ADMIN AND PROFILE OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__,2).'/app/Helpers/functions.php';

use App\Exceptions\HttpException;
use App\Services\ApiTokenService;

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/005_api_tokens.sql');
    $pdo=$db->pdo;
    fixtures($pdo);

    $password='Api-Test-'.bin2hex(random_bytes(6));
    $hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>4]);
    $pdo->prepare('UPDATE usuarios SET password_hash=? WHERE id IN (1,2)')->execute([$hash]);

    $pdo->exec("INSERT INTO roles(id,nombre) VALUES(10,'ADMIN'),(11,'DIGITADOR')");
    $pdo->exec("INSERT INTO usuario_rol(usuario_id,rol_id) VALUES(1,10),(2,11)");

    $_ENV['API_TOKEN_TTL']='900';
    putenv('API_TOKEN_TTL=900');
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_SERVER['HTTP_USER_AGENT']='ApiAuthTest/1.0';

    $service=new ApiTokenService($pdo);

    $auth=$service->issue('fixture@example.invalid',$password);
    ensure(str_starts_with((string)$auth['access_token'],'puc_'),'API login returns namespaced token');
    ensure($auth['token_type']==='Bearer','API token type');
    ensure((int)$auth['expires_in']===900,'API token TTL');
    ensure(!str_contains((string)$pdo->query('SELECT token_hash FROM api_tokens LIMIT 1')->fetchColumn(),(string)$auth['access_token']),'Raw token is never stored');

    $user=$service->authenticate((string)$auth['access_token']);
    ensure((int)$user['id']===1 && in_array('ADMIN',$user['roles'],true),'Valid token authenticates API user');
    ensure($pdo->query('SELECT last_used_at FROM api_tokens LIMIT 1')->fetchColumn()!==null,'Token usage timestamp updated');

    $old=(string)$auth['access_token'];
    $second=$service->issue('fixture@example.invalid',$password);
    rejects(fn()=>$service->authenticate($old),HttpException::class);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM api_tokens WHERE revoked_at IS NOT NULL')->fetchColumn()===1,'New login revokes previous token');

    $current=(string)$second['access_token'];
    $service->revoke($current);
    rejects(fn()=>$service->authenticate($current),HttpException::class);

    try{
        $service->issue('reviewer@example.invalid',$password);
        throw new RuntimeException('Digitador should not get API token');
    }catch(HttpException $error){
        ensure($error->status===403,'Digitador API login denied');
    }

    try{
        $service->issue('fixture@example.invalid','incorrecta');
        throw new RuntimeException('Invalid password should fail');
    }catch(HttpException $error){
        ensure($error->status===401,'Invalid API credentials rejected');
    }

    $active=$service->issue('fixture@example.invalid',$password);
    $pdo->exec("UPDATE usuarios SET estado=0 WHERE id=1");
    try{
        $service->authenticate((string)$active['access_token']);
        throw new RuntimeException('Inactive account should invalidate token');
    }catch(HttpException $error){
        ensure(in_array($error->status,[401,403],true),'Inactive API account rejected');
    }

    echo 'API auth: '.$GLOBALS['checks']." checks OK\n";
}finally{
    putenv('API_TOKEN_TTL');
    unset($_ENV['API_TOKEN_TTL']);
    $db->close();
}

<?php
declare(strict_types=1);

// Reuses Pedro's isolated database harness; never imports fixtures into the app database.
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/app/Helpers/functions.php';

$db = new TestDatabase();
$server = null;
$temp = sys_get_temp_dir() . '/bayer_core_' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
$password = bin2hex(random_bytes(16));
$root = dirname(__DIR__, 2);
$base = '';
$sessionName = 'bayer_core_test';
function request(string $path, array &$cookies, ?array $post = null, array $extraHeaders = []): array {
    $headers = ['Connection: close'];
    if ($cookies) $headers[] = 'Cookie: ' . implode('; ', array_map(static fn($k,$v)=>$k.'='.$v,array_keys($cookies),array_values($cookies)));
    $headers = [...$headers,...$extraHeaders];
    if ($post !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $context = stream_context_create(['http'=>[
        'method'=>$post === null ? 'GET' : 'POST', 'header'=>implode("\r\n",$headers),
        'content'=>$post === null ? '' : http_build_query($post),
        'ignore_errors'=>true, 'follow_location'=>0, 'timeout'=>15,
    ]]);
    $body = file_get_contents($GLOBALS['base'].$path, false, $context);
    if ($body === false) throw new RuntimeException('HTTP request failed');
    $responseHeaders = $http_response_header;
    preg_match('~HTTP/\S+ (\d+)~',$responseHeaders[0],$match);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$header,$cookie)) $cookies[$cookie[1]] = $cookie[2];
    }
    return ['status'=>(int)$match[1],'headers'=>implode("\n",$responseHeaders),'body'=>$body];
}
function token(array $response): string {
    preg_match('/name="_csrf" value="([^"]+)"/',$response['body'],$m);
    ensure(isset($m[1]), 'CSRF field present');
    return $m[1];
}
function loginAs(string $role, array &$cookies): array {
    $page = request('/login',$cookies);
    return request('/login',$cookies,['_csrf'=>token($page),'email'=>strtolower($role).'@example.invalid','password'=>$GLOBALS['password']]);
}
try {
    $db->load('database/schemas/002_schema_v2.sql');
    fixtures($db->pdo);
    $pdo = $db->pdo;
    $roles = ['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'];
    $ids = [];
    foreach ($roles as $i=>$role) {
        $id = $i+10; $ids[$role]=$id;
        $st=$pdo->prepare('INSERT INTO roles(id,nombre) VALUES(?,?)');$st->execute([$id,$role]);
        $st=$pdo->prepare('INSERT INTO usuarios(id,nombre,email,password_hash) VALUES(?,?,?,?)');
        $st->execute([$id,$role,strtolower($role).'@example.invalid',password_hash($password,PASSWORD_BCRYPT,['cost'=>4])]);
        $pdo->exec("INSERT INTO usuario_rol(usuario_id,rol_id) VALUES($id,$id)");
    }
    $docs = new App\Repositories\Operations\DocumentRepository($pdo);
    foreach (['BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO'] as $state) {
        $id = $docs->create(docHeader($state.'-ONLY'), lines(), $ids['ADMIN']);
        if ($state !== 'BORRADOR') $docs->markValidated($id,1,$ids['SUPERVISOR']);
        if (in_array($state,['PUBLICADO','ANULADO'],true)) $docs->markPublished($id,2,$ids['SUPERVISOR']);
        if ($state === 'OBSERVADO') $docs->markObserved($id,2,$ids['SUPERVISOR'],'Fixture');
        if ($state === 'ANULADO') $docs->markCancelled($id,3,$ids['SUPERVISOR'],'Fixture');
    }
    $pdo->exec("UPDATE productos SET nombre='</script><script>alert(1)</script>' WHERE id=1");
    $permissions = new App\Services\PermissionService($pdo);
    ensure($permissions->forUser($ids['ADMIN']) === [], 'No implicit ADMIN grants');
    $pdo->exec("INSERT INTO permisos(id,codigo,nombre) VALUES(1,'documents.create','Fixture'),(2,'published_data.view','Fixture')");
    $pdo->exec("INSERT INTO rol_permiso(rol_id,permiso_id) VALUES(10,1),(14,2)");
    ensure($permissions->allows(['id'=>10,'roles'=>['ADMIN']],'documents.create'), 'Persisted permission');
    ensure(!$permissions->allows(['id'=>10,'roles'=>['ADMIN','BAYER']],'documents.create'), 'External deny dominates permission');
    ensure($permissions->allows(['id'=>14,'roles'=>['BAYER']],'published_data.view'), 'Published permission');
    $pdo->exec('UPDATE roles SET estado=0 WHERE id=10');
    ensure($permissions->forUser(10) === [], 'Inactive role has no grants');
    $pdo->exec('UPDATE roles SET estado=1 WHERE id=10');

    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) throw new RuntimeException('No local port');
    $address=stream_socket_get_name($socket,false);fclose($socket);
    $base='http://'.$address;
    $env=getenv();
    $env['DB_HOST']=getenv('TEST_DB_HOST') ?: '127.0.0.1';
    $env['DB_PORT']=getenv('TEST_DB_PORT') ?: '3306';
    $env['DB_USER']=getenv('TEST_DB_USER') ?: 'root';
    $env['DB_PASS']=getenv('TEST_DB_PASS') ?: '';
    $env['DB_NAME']=$pdo->query('SELECT DATABASE()')->fetchColumn();
    $env['APP_URL']=$base;
    $env['APP_DEBUG']='true'; // Even with debug requested, HTTP errors must be sanitized.
    $env['SESSION_NAME']=$sessionName;
    $env['API_ENABLED']='false';
    $env['SESSION_TIMEOUT']='60';
    $env['LOGIN_MAX_ATTEMPTS']='5';
    $env['LOGIN_WINDOW']='900';
    $server=proc_open([PHP_BINARY,'-d','session.save_path='.$temp,'-S',$address,'-t',$root.'/public',$root.'/public/index.php'],
        [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,$root,$env);
    if (!is_resource($server)) throw new RuntimeException('HTTP server unavailable');
    fclose($pipes[0]);
    for($i=0;$i<50;++$i){$probe=@stream_socket_client('tcp://'.$address,$e,$m,0.1);if($probe){fclose($probe);break;}usleep(100000);}

    $guest=[];
    ensure(request('/dashboard',$guest)['status']===302,'Guest redirects to login');
    ensure(request('/login',$guest,['email'=>'x','password'=>'x'])['status']===419,'Missing CSRF rejected');
    $guest=[]; // Inspect Set-Cookie on a new session, not a reused cookie.
    $page=request('/login',$guest);
    ensure(str_contains(strtolower($page['headers']),'httponly') && str_contains(strtolower($page['headers']),'samesite=lax'),'Cookie flags');
    $oldId=$guest[$sessionName];$oldToken=token($page);
    ensure(request('/login',$guest,['_csrf'=>$oldToken,'email'=>['x'],'password'=>'x'])['status']===422,'Malformed credentials are 422');
    $r=request('/login',$guest,['_csrf'=>$oldToken,'email'=>'admin@example.invalid','password'=>$password]);
    ensure($r['status']===302 && str_contains($r['headers'],'/dashboard'),'Admin login destination');
    ensure($guest[$sessionName]!==$oldId,'Session ID regenerated');
    $replay=[$sessionName=>$oldId];
    ensure(request('/dashboard',$replay)['status']===302,'Pre-login session ID cannot authenticate');
    $dashboard=request('/dashboard',$guest);
    ensure($dashboard['status']===200,'Admin dashboard');
    ensure(!str_contains($dashboard['body'],'</script><script>alert(1)</script>'),'Chart names escaped');
    ensure(request('/logout',$guest,['_csrf'=>$oldToken])['status']===419,'Login rotates CSRF');
    $authId=$guest[$sessionName];
    ensure(request('/logout',$guest,['_csrf'=>token($dashboard)])['status']===302,'Logout redirects');
    $replay=[$sessionName=>$authId];
    ensure(request('/dashboard',$replay)['status']===302,'Logged-out session cannot replay');

    $bayer=[];
    $r=loginAs('BAYER',$bayer);
    ensure($r['status']===302 && str_contains($r['headers'],'/bayer'),'Bayer landing');
    foreach(['/documentos','/documentos/nuevo','/guias','/stock','/usuarios','/usuarios/1','/dashboard','/documentos/','/%64ocumentos','//documentos'] as $path) {
        ensure(request($path,$bayer)['status']===403,'Bayer denied '.$path);
    }
    foreach(['/documentos/guardar','/guias/estado','/stock/estado'] as $path) {
        ensure(request($path,$bayer,['status'=>'PUBLICADO'])['status']===403,'Permission checked before CSRF and Controller');
    }
    $page=request('/bayer',$bayer);
    ensure($page['status']===200,'Bayer portal');
    ensure(!str_contains($page['body'],'>Clientes<') && !str_contains($page['body'],'>Productos<'),'No global master counts');
    ensure(!str_contains($page['body'],'</script><script>alert(1)</script>'),'Bayer chart names escaped');
    foreach(['/bayer/datos?type=documents','/export?type=documents&format=csv'] as $path) {
        $page=request($path,$bayer);
        ensure($page['status']===200 && str_contains($page['body'],'PUBLICADO-ONLY'),'Published output');
        foreach(['BORRADOR','VALIDADO','OBSERVADO','ANULADO'] as $state) ensure(!str_contains($page['body'],$state.'-ONLY'),'Hidden state '.$state);
    }
    ensure(request('/bayer/datos?from=2026-02-30',$bayer)['status']===422,'Invalid filter date');
    ensure(request('/bayer/datos?type[]=documents',$bayer)['status']===422,'Invalid dataset type');
    ensure(request('/missing',$bayer)['status']===404,'Unknown URL');
    $pdo->exec('INSERT INTO usuario_rol(usuario_id,rol_id) VALUES(14,10)');
    ensure(request('/documentos',$bayer)['status']===403,'Multi-role Bayer never internal');
    $pdo->exec('UPDATE usuarios SET estado=0 WHERE id=14');
    ensure(request('/bayer',$bayer)['status']===302,'Inactive user revoked on next request');
    $pdo->exec('UPDATE usuarios SET estado=1 WHERE id=14');
    $bayer=[];loginAs('BAYER',$bayer);
    $file=$temp.'/sess_'.$bayer[$sessionName];
    $serialized=file_get_contents($file);
    $serialized=preg_replace('/_last_activity\|i:\d+;/','_last_activity|i:'.(time()-61).';',$serialized,1,$replaced);
    ensure($replaced===1,'Test ages only its session');
    file_put_contents($file,$serialized);
    ensure(request('/bayer',$bayer)['status']===302,'Idle timeout');
    $bayer=[];loginAs('BAYER',$bayer);
    $pdo->exec('DELETE FROM usuario_rol WHERE usuario_id=14');
    ensure(request('/bayer',$bayer)['status']===302,'Removed roles revoked');

    // Datos propios y ajenos para verificar el alcance en los tres listados.
    $docs->create(docHeader('DOC-PROPIO'), lines(), $ids['DIGITADOR']);
    $guides = new App\Repositories\Operations\GuideRepository($pdo);
    $guides->create(guideHeader('GUIA-PROPIA'), lines(), $ids['DIGITADOR']);
    $guides->create(guideHeader('GUIA-AJENA'), lines(), $ids['ADMIN']);
    $stocks = new App\Repositories\Operations\StockRepository($pdo);
    $stocks->create(stockHeader('stock-propio', '2026-08-20'), lines(true), $ids['DIGITADOR']);
    $stocks->create(stockHeader('stock-ajeno', '2026-08-21'), lines(true), $ids['ADMIN']);

    $digitador=[];loginAs('DIGITADOR',$digitador);
    ensure(request('/documentos',$digitador)['status']===200,'Digitador listing');
    $page=request('/documentos',$digitador);
    foreach (['documentos'=>['DOC-PROPIO','BORRADOR-ONLY'], 'guias'=>['GUIA-PROPIA','GUIA-AJENA'], 'stock'=>['2026-08-20','2026-08-21']] as $module=>$markers) {
        $own = request('/'.$module.'?created_by='.$ids['ADMIN'], $digitador);
        ensure($own['status'] === 200 && str_contains($own['body'], $markers[0]), 'Digitador consulta su carga: '.$module);
        ensure(!str_contains($own['body'], $markers[1]), 'El parámetro HTTP no permite consultar cargas ajenas: '.$module);
    }
    ensure(!str_contains($page['body'], '>Portal Bayer</a>'), 'El menú del Digitador respeta el acceso al portal');
    ensure(request('/export?type=documents&format=csv',$digitador)['status'] === 403, 'Digitador no exporta');
    ensure(request('/documentos/estado',$digitador,['_csrf'=>token($page),'status'=>'PUBLICADO'])['status']===403,'Digitador cannot publish');
    ensure(request('/documentos/guardar',$digitador,['_csrf'=>token($page)])['status']===503,'Day 2 capture is explicitly pending, no old create signature');
    $supervisor=[];loginAs('SUPERVISOR',$supervisor);
    ensure(request('/guias',$supervisor)['status']===200,'Supervisor review listing');
    ensure(request('/guias/nuevo',$supervisor)['status']===403,'Supervisor does not capture');
    $export = request('/export?type=documents&format=csv', $supervisor);
    ensure($export['status'] === 200 && str_contains($export['body'], 'PUBLICADO-ONLY'), 'Supervisor exporta datos publicados');
    ensure(!str_contains($export['body'], 'BORRADOR-ONLY'), 'Supervisor no exporta borradores');
    $reviewList = request('/guias', $supervisor);
    ensure(str_contains($reviewList['body'], 'GUIA-PROPIA') && str_contains($reviewList['body'], 'GUIA-AJENA'), 'Supervisor consulta las cargas del equipo');
    $gerencia=[];loginAs('GERENCIA',$gerencia);
    ensure(request('/stock',$gerencia)['status']===200,'Gerencia query');
    ensure(request('/stock/nuevo',$gerencia)['status']===403,'Gerencia does not capture');

    $admin=[];loginAs('ADMIN',$admin);
    $pdo->exec('RENAME TABLE documentos_detalle TO documentos_detalle_unavailable');
    $response=request('/documentos',$admin,null,['Accept: application/json']);
    ensure($response['status']===500 && !str_contains($response['body'],'SQLSTATE') && !str_contains($response['body'],'SELECT'),'500 sanitized despite debug');
    $pdo->exec('RENAME TABLE documentos_detalle_unavailable TO documentos_detalle');
    $pdo->exec("DELETE FROM bitacora_acceso WHERE accion='login_failed'");
    for($i=0;$i<5;++$i){$client=[];$page=request('/login',$client);request('/login',$client,['_csrf'=>token($page),'email'=>'admin@example.invalid','password'=>'wrong']);}
    $blocked=[];$response=loginAs('ADMIN',$blocked);
    ensure($response['status']===302 && str_contains($response['headers'],'/login'),'Temporary lock blocks correct credentials');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM bitacora_acceso WHERE accion='login_blocked'")->fetchColumn()>0,'Blocked attempts recorded');
    $pdo->exec("UPDATE bitacora_acceso SET fecha_hora=DATE_SUB(NOW(),INTERVAL 16 MINUTE) WHERE accion='login_failed'");
    $unblocked=[];$response=loginAs('ADMIN',$unblocked);
    ensure(str_contains($response['headers'],'/dashboard'),'Lock window expires');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM bitacora_acceso WHERE accion='session_timeout'")->fetchColumn()>0,'Timeout audited');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM bitacora_acceso WHERE accion='access_denied'")->fetchColumn()>0,'Denied access audited');
    echo 'Core HTTP/integration: '.$GLOBALS['checks']." checks OK\n";
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $db->close();
    foreach(glob($temp.'/*') ?: [] as $file) unlink($file);
    rmdir($temp);
}

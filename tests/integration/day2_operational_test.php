<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Services\OperationalService;
use App\Services\PermissionService;
use App\Exceptions\{HttpException,ValidationException};

function statusIs(callable $call, int $status): void {
    try { $call(); } catch (HttpException $e) { ensure($e->status===$status,'Expected HTTP '.$status); return; }
    throw new RuntimeException('Expected HTTP '.$status);
}
$db=new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql'); fixtures($db->pdo); $pdo=$db->pdo;
    $users=[];
    foreach(['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $i=>$role) {
        $id=$i+10;
        $pdo->exec("INSERT INTO roles(id,nombre) VALUES($id,'$role')");
        $pdo->exec("INSERT INTO usuarios(id,nombre,email,password_hash) VALUES($id,'$role','$role@example.invalid','not-a-password')");
        $pdo->exec("INSERT INTO usuario_rol(usuario_id,rol_id) VALUES($id,$id)");
        $users[$role]=['id'=>$id,'roles'=>[$role]];
    }
    $db->load('database/seeders/003_day2_operational_permissions.sql');
    $grants=$pdo->query('SELECT COUNT(*) FROM rol_permiso')->fetchColumn();
    $db->load('database/seeders/003_day2_operational_permissions.sql');
    ensure((int)$grants===18 && $pdo->query('SELECT COUNT(*) FROM rol_permiso')->fetchColumn()===$grants,'Seed replay preserves 18 grants');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM permisos')->fetchColumn()===6,'Only six Day 2 permissions');
    $permission=new PermissionService($pdo);
    foreach(['documents','guides','stock'] as $module) foreach($users as $role=>$user) {
        ensure($permission->allows($user,$module.'.create')===in_array($role,['ADMIN','DIGITADOR'],true),'Create grant '.$module.$role);
        ensure($permission->allows($user,$module.'.read')===($role!=='BAYER'),'Read grant '.$module.$role);
    }
    $masterCounts=$pdo->query('SELECT (SELECT COUNT(*) FROM productos)+(SELECT COUNT(*) FROM clientes)+(SELECT COUNT(*) FROM sucursales)')->fetchColumn();
    foreach(['documents','guides','stock'] as $module) {
        $service=new OperationalService($module,$pdo);
        $header=match($module){'documents'=>docHeader('D2-doc'),'guides'=>guideHeader('D2-guide'),'stock'=>stockHeader('D2-stock')};
        $input=['header'=>$header,'details'=>lines($module==='stock')];
        foreach(['SUPERVISOR','GERENCIA','BAYER'] as $role) statusIs(fn()=>$service->create($users[$role],$input),403);
        $external=$users['ADMIN'];$external['roles'][]='BAYER';
        statusIs(fn()=>$service->create($external,$input),403);
        statusIs(fn()=>$service->listing($users['BAYER'],[]),403);
        $id=$service->create($users['DIGITADOR'],$input);
        $saved=$service->show($users['DIGITADOR'],$id);
        ensure((int)$saved['header']['created_by']===11 && $saved['header']['estado_registro']==='BORRADOR','Session actor and draft '.$module);
        ensure(count($saved['details'])===2,'Full aggregate '.$module);
        ensure(!isset($saved['header']['request_hash']),'Persistence hash not exposed');
        ensure(count($service->listing($users['DIGITADOR'],['created_by'=>10]))===1,'Browser cannot override author');
        $other=$input;
        if($module==='stock') { $other['header']['idempotency_key'].='-other'; $other['header']['fecha_stock']='2026-09-12'; } else $other['header']['numero'].='-other';
        $otherId=$service->create($users['ADMIN'],$other);
        statusIs(fn()=>$service->show($users['DIGITADOR'],$otherId),403);
        ensure(count($service->listing($users['DIGITADOR'],[]))===1,'Other actor hidden');
        ensure(count($service->listing($users['SUPERVISOR'],[]))===2,'Supervisor reads team');
        ensure(count($service->listing($users['GERENCIA'],['limit'=>1,'page'=>2]))===1,'Server pagination');
        statusIs(fn()=>$service->show($users['ADMIN'],999999),404);
        foreach([0,-1,[],true,'1e2'] as $bad) rejects(fn()=>$service->show($users['ADMIN'],$bad),ValidationException::class);
        rejects(fn()=>$service->listing($users['ADMIN'],['fecha_desde'=>'2026-02-30']),ValidationException::class);
        rejects(fn()=>$service->listing($users['ADMIN'],['fecha_desde'=>'2026-09-12','fecha_hasta'=>'2026-09-01']),ValidationException::class);
        rejects(fn()=>$service->listing($users['ADMIN'],['limit'=>301]),ValidationException::class);
        rejects(fn()=>$service->listing($users['ADMIN'],['page'=>PHP_INT_MAX]),ValidationException::class);
        foreach(['estado_registro','created_by','version'] as $field) {
            $bad=$input;$bad['header'][$field]=$field==='estado_registro'?'PUBLICADO':10;
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        }
        foreach([[],['header'=>[],'details'=>[]],['header'=>'x','details'=>lines()],$input+['actor'=>10]] as $bad) rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        foreach(['-1','1e3','1.0001',true,[],0.1,'100000000000.000'] as $quantity) {
            $bad=$input;$bad['details'][0]['cantidad']=$quantity;
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        }
        foreach(['productos','vendedores','sucursales','empresas','almacenes','lotes'] as $table) {
            if($module!=='stock' && in_array($table,['almacenes','lotes'],true)) continue;
            if($module==='stock' && $table==='vendedores') continue;
            $pdo->exec("UPDATE $table SET estado=0 WHERE id=1");
            rejects(fn()=>$service->create($users['ADMIN'],$input),ValidationException::class);
            // Historical references remain readable.
            ensure($service->show($users['DIGITADOR'],$id)['header']['id']==$id,'History remains readable '.$table);
            $pdo->exec("UPDATE $table SET estado=1 WHERE id=1");
        }
        $bad=$input;$bad['details'][0]['producto_id']=999999;
        rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        $bad=$input;$bad['details'][0]['unidad_id']=999999;
        rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        $date=$module==='stock'?'fecha_stock':'fecha';
        $bad=$input;$bad['header'][$date]='2026-02-30';
        rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        if($module==='stock') {
            ensure($service->create($users['DIGITADOR'],$input)===$id,'Same actor replay returns same snapshot');
            statusIs(fn()=>$service->create($users['ADMIN'],$input),409);
            $bad=$input;$bad['details'][0]['cantidad']='9';
            statusIs(fn()=>$service->create($users['DIGITADOR'],$bad),409);
            $bad=$input;$bad['details'][0]['lote_id']=2;
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
            $bad=$input;$bad['details'][0]['lote_id']=3;
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
            $bad=$input;$bad['details'][]=$bad['details'][0];
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
            $zero=$input;$zero['header']['idempotency_key']='zero-stock';$zero['header']['fecha_stock']='2026-09-13';$zero['details'][0]['cantidad']='0';
            ensure($service->create($users['ADMIN'],$zero)>0,'Zero stock is valid');
        } else {
            statusIs(fn()=>$service->create($users['DIGITADOR'],$input),409);
            $bad=$input;$bad['details'][0]['cantidad']='0';
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
        }
        if($module==='guides') {
            $bad=$input;$bad['header']['distrito_id']=2;
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
            $bad=$input;unset($bad['header']['provincia_id']);
            rejects(fn()=>$service->create($users['ADMIN'],$bad),ValidationException::class);
            $emptyGeo=$input;$emptyGeo['header']['numero']='NO-GEO';
            foreach(['departamento_id','provincia_id','distrito_id'] as $f) unset($emptyGeo['header'][$f]);
            ensure($service->create($users['ADMIN'],$emptyGeo)>0,'Draft may omit all geography');
        }
        $sparse=$input;$sparse['details']=[2=>$input['details'][0],7=>$input['details'][1]];
        if($module==='stock') { $sparse['header']['idempotency_key']='sparse-stock'; $sparse['header']['fecha_stock']='2026-09-14'; } else $sparse['header']['numero']='SPARSE-'.$module;
        ensure($service->create($users['ADMIN'],$sparse)>0,'Sparse numeric frontend indexes normalized');
        ensure(count($service->masters($users['DIGITADOR'],['catalog'=>'productos']))===2,'Catalog query uses repository');
        statusIs(fn()=>$service->masters($users['BAYER'],['catalog'=>'productos']),403);
        rejects(fn()=>$service->masters($users['ADMIN'],['catalog'=>'usuarios']),ValidationException::class);
        $pdo->exec("DELETE rp FROM rol_permiso rp JOIN permisos p ON p.id=rp.permiso_id WHERE rp.rol_id=11 AND p.codigo='$module.create'");
        statusIs(fn()=>$service->create($users['DIGITADOR'],$input),403);
        ensure(count($service->listing($users['DIGITADOR'],[]))===1,'Read survives create revocation');
    }
    ensure($masterCounts===$pdo->query('SELECT (SELECT COUNT(*) FROM productos)+(SELECT COUNT(*) FROM clientes)+(SELECT COUNT(*) FROM sucursales)')->fetchColumn(),'Capture never creates masters');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro<>'BORRADOR'")->fetchColumn()===0,'No workflow executed');
    echo 'Day 2 operations: '.$GLOBALS['checks']." checks OK\n";
} finally { $db->close(); }

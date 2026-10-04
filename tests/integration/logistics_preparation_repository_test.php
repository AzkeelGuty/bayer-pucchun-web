<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Repositories\LogisticsPreparationRepository as Repo;

function prepHeader(string $key='initial'): array {
    return ['idempotency_key'=>$key,'fecha_preparacion'=>'2026-10-04 10:00:00','almacen_id'=>1,
        'direccion_destino'=>'Destino','origen_sistema'=>'G8','origen_documento_ref'=>'opaque:pedido'];
}
function detail(mixed $q='1', ?int $lot=null): array {
    return ['producto_id'=>1,'unidad_id'=>1,'lote_id'=>$lot,'cantidad_preparada'=>$q,'origen_linea_ref'=>'opaque:linea'];
}
function editable(array $line): array {
    return array_intersect_key($line,array_flip(['id','producto_id','unidad_id','lote_id','cantidad_preparada','origen_linea_ref']));
}
function editHeader(): array { $h=prepHeader(); unset($h['idempotency_key']); return $h; }
function conflict(callable $op,int $code): void {
    try { $op(); } catch(Throwable $e) {
        ensure($e instanceof ($code===Repo::INVALID_QUANTITY ? InvalidArgumentException::class : RuntimeException::class),'Conflict exception class');
        ensure($e->getCode()===$code,'Exact identifiable conflict: '.$e->getMessage()); return;
    }
    throw new RuntimeException('Expected conflict');
}
function connect(string $database): PDO {
    if(!preg_match('/^bayer_test_[a-f0-9]{16}$/D',$database)) throw new RuntimeException('Only isolated test schemas allowed');
    return new PDO('mysql:host='.(getenv('TEST_DB_HOST')?:'127.0.0.1').';port='.(getenv('TEST_DB_PORT')?:'3306').';dbname='.$database.';charset=utf8mb4',
        getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASS')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
if(($argv[1]??'')==='--worker') {
    $repo=new Repo(connect($argv[2])); $mode=$argv[3]; $id=(int)$argv[4];
    echo "READY\n"; flush(); fgets(STDIN);
    try {
        $result=$mode==='create'?$repo->create(prepHeader('race-create'),[detail()],1):
            ($mode==='state'?$repo->changeState($id,1,'EN_PREPARACION','PREPARADA',1):
            $repo->update($id,1,[...editHeader(),'observacion'=>'race'],array_map('editable',$repo->find($id)['details']),1));
        echo json_encode(['ok'=>$result],JSON_THROW_ON_ERROR)."\n";
    } catch(Throwable $e) { echo json_encode(['error'=>get_class($e),'code'=>$e->getCode(),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR)."\n"; }
    exit;
}
function workers(PDO $pdo,string $mode,int $id=0): array {
    $workers=[];
    try {
        for($i=0;$i<2;$i++) {
            $p=proc_open([PHP_BINARY,__FILE__,'--worker',$pdo->query('SELECT DATABASE()')->fetchColumn(),$mode,(string)$id],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($p)) throw new RuntimeException('Worker launch failed');
            stream_set_timeout($pipes[1],20); $workers[]=[$p,$pipes];
        }
        foreach($workers as [,$pipes]) ensure(trim((string)fgets($pipes[1]))==='READY','Independent worker ready');
        foreach($workers as [,$pipes]) { fwrite($pipes[0],"GO\n"); fflush($pipes[0]); }
        $results=[];
        foreach($workers as [,$pipes]) {
            $line=fgets($pipes[1]); ensure(is_string($line),'Worker completed');
            $results[]=json_decode($line,true,512,JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        foreach($workers as [$p,$pipes]) {
            foreach($pipes as $pipe) fclose($pipe);
            if(proc_get_status($p)['running']) proc_terminate($p);
            proc_close($p);
        }
    }
}
function snapshot(PDO $pdo): array {
    $out=[];
    foreach(['logistica_preparaciones_cabecera','logistica_preparaciones_detalle','logistica_historial'] as $t)
        $out[$t]=$pdo->query("SELECT * FROM $t ORDER BY id")->fetchAll();
    return $out;
}
function dispatch(PDO $pdo,int $prep,int $line,string $q,string $state='PENDIENTE'): int {
    $key='fixture-'.bin2hex(random_bytes(8));
    $pdo->prepare('INSERT INTO logistica_despachos_cabecera(preparacion_id,fecha_despacho,estado_fisico,created_by,idempotency_key,request_hash) VALUES(?,NOW(),?,1,?,?)')
        ->execute([$prep,$state,$key,str_repeat('a',64)]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO logistica_despachos_detalle(despacho_id,preparacion_id,preparacion_detalle_id,cantidad_despachada) VALUES(?,?,?,?)')->execute([$id,$prep,$line,$q]);
    return $id;
}
$db=new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql'); $db->load('database/migrations/006_logistica_persistencia.sql');
    $pdo=$db->pdo; fixtures($pdo); $repo=new Repo($pdo);
    $input=[detail(1,1),detail('1.5',3),detail('1.250'),detail('0'),detail('99999999999.999')];
    $id=$repo->create(prepHeader(),$input,1); $record=$repo->find($id);
    ensure(count($record['details'])===5,'Separate lines');
    ensure(array_column($record['details'],'cantidad_preparada')===['1.000','1.500','1.250','0.000','99999999999.999'],'Exact decimal strings and boundaries');
    ensure(array_column($record['details'],'lote_id')===[1,3,null,null,null],'Different and nullable lots preserved');
    ensure((int)$record['header']['version']===1 && $record['header']['estado_preparacion']==='EN_PREPARACION','Initial version/state');
    ensure($record['header']['origen_documento_ref']==='opaque:pedido','Opaque refs');
    $hist=$pdo->query("SELECT * FROM logistica_historial WHERE preparacion_id=$id")->fetchAll();
    ensure(count($hist)===1 && $hist[0]['accion']==='crear' && (int)$hist[0]['version']===1,'Atomic initial history');
    ensure(json_decode($hist[0]['metadata_json'],true)['details'][1]['cantidad_preparada']==='1.500','Exact history quantities');
    ensure($repo->find(999999)===null,'Missing read');
    ensure(count($repo->all(['almacen_id'=>1,'created_by'=>1,'estado_preparacion'=>'EN_PREPARACION']))===1,'Combined filters');
    ensure($repo->all(['fecha_desde'=>'2027-01-01 00:00:00'])===[],'Date filter');
    ensure($repo->all([],1,1)===[],'Offset');
    rejects(fn()=>$repo->all(['sql;'=>1]),InvalidArgumentException::class);
    rejects(fn()=>$repo->all([],301),InvalidArgumentException::class);
    foreach(['1.0000','-1','100000000000','1e2','1,5','',1.5,true,null] as $bad) {
        $before=snapshot($pdo);
        conflict(fn()=>$repo->create(prepHeader('invalid'),[detail($bad)],1),Repo::INVALID_QUANTITY);
        ensure(snapshot($pdo)===$before,'Invalid decimal no writes');
    }
    ensure($repo->create(prepHeader(),array_reverse($input),1)===$id,'Normalized order-independent replay');
    $before=snapshot($pdo);
    conflict(fn()=>$repo->create(prepHeader(),[detail('2')],1),Repo::IDEMPOTENCY_CONFLICT);
    conflict(fn()=>$repo->create(prepHeader(),$input,2),Repo::IDEMPOTENCY_CONFLICT);
    ensure(snapshot($pdo)===$before,'Replay conflict no writes');
    foreach([
        [prepHeader('empty'),[]],
        [[...prepHeader('geo'),'departamento_id'=>1,'provincia_id'=>1,'distrito_id'=>2],[detail()]],
        [[...prepHeader('date'),'fecha_preparacion'=>'2026-02-30 00:00:00'],[detail()]],
        [[...prepHeader('fields'),'request_hash'=>str_repeat('a',64)],[detail()]]
    ] as [$h,$d]) rejects(fn()=>$repo->create($h,$d,1),InvalidArgumentException::class);
    $before=snapshot($pdo);
    rejects(fn()=>$repo->create(prepHeader('bad-fk'),[detail(),[...detail(),'producto_id'=>9999]],1),PDOException::class,1452);
    ensure(snapshot($pdo)===$before,'Second detail FK failure rolls back header/first detail');
    rejects(fn()=>$repo->create(prepHeader('bad-lot'),[detail('1',2)],1),PDOException::class,1452);
    rejects(fn()=>$repo->create(prepHeader('bad-actor'),[detail()],9999),PDOException::class,1452);
    $lines=array_map('editable',$record['details']); $lines[0]['cantidad_preparada']='2.125';
    $lines[1]=[...$lines[1],'producto_id'=>2,'unidad_id'=>2,'lote_id'=>2];
    unset($lines[3]); $lines=array_values($lines); $lines[]=detail('0.125');
    ensure($repo->update($id,1,[...editHeader(),'observacion'=>'actualizada'],$lines,2,'edicion')===2,'Update next version');
    $after=$repo->find($id);
    ensure($after['details'][0]['id']===$record['details'][0]['id'],'Stable line ID');
    ensure((int)$after['details'][1]['producto_id']===2,'Unused identity editable');
    ensure(!in_array($record['details'][3]['id'],array_column($after['details'],'id'),true),'Unused omitted line deleted');
    ensure((int)$after['header']['updated_by']===2 && $after['header']['request_hash']===$record['header']['request_hash'],'Audit and immutable initial hash');
    ensure($repo->create(prepHeader(),$input,1)===$id,'Replay after edit initial hash');
    $before=snapshot($pdo);
    conflict(fn()=>$repo->update($id,1,editHeader(),$lines,1),Repo::VERSION_CONFLICT);
    conflict(fn()=>$repo->update(999999,1,editHeader(),[detail()],1),Repo::NOT_FOUND);
    conflict(fn()=>$repo->changeState($id,2,'PREPARADA','CANCELADA',1),Repo::STATE_CONFLICT);
    ensure(snapshot($pdo)===$before,'Conflicts atomic');
    ensure($repo->changeState($id,2,'EN_PREPARACION','PREPARADA',1,'lista')===3,'State write');
    conflict(fn()=>$repo->changeState($id,2,'EN_PREPARACION','CANCELADA',1),Repo::VERSION_CONFLICT);
    rejects(fn()=>$repo->changeState($id,3,'PREPARADA','PUBLICADO',1),InvalidArgumentException::class);
    ensure($pdo->query("SELECT GROUP_CONCAT(version ORDER BY version) FROM logistica_historial WHERE preparacion_id=$id")->fetchColumn()==='1,2,3','One history per version');
    $pdo->exec('CREATE TABLE repository_failure_probe(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO repository_failure_probe VALUES(1)');
    $pdo->exec('CREATE TRIGGER repository_history_failure BEFORE INSERT ON logistica_historial FOR EACH ROW INSERT INTO repository_failure_probe VALUES(1)');
    foreach([
        fn()=>$repo->create(prepHeader('history-failure'),[detail()],1),
        fn()=>$repo->update($id,3,editHeader(),array_map('editable',$repo->find($id)['details']),1),
        fn()=>$repo->changeState($id,3,'PREPARADA','CANCELADA',1)
    ] as $op) {
        $before=snapshot($pdo); rejects($op,PDOException::class,1062);
        ensure(snapshot($pdo)===$before,'Unrelated history UNIQUE propagates and rolls back');
    }
    $pdo->exec('DROP TRIGGER repository_history_failure');
    // Reject foreign/duplicated line IDs without mutation; failed UPDATE detail FK is atomic.
    $foreign=$repo->create(prepHeader('foreign'),[detail()],1);
    $foreignLine=editable($repo->find($foreign)['details'][0]);
    $before=snapshot($pdo);
    rejects(fn()=>$repo->update($id,3,editHeader(),[$foreignLine],1),InvalidArgumentException::class);
    $own=editable($repo->find($id)['details'][0]);
    rejects(fn()=>$repo->update($id,3,editHeader(),[$own,$own],1),InvalidArgumentException::class);
    rejects(fn()=>$repo->update($id,3,editHeader(),[[...$own,'producto_id'=>9999,'lote_id'=>null]],1),PDOException::class,1452);
    ensure(snapshot($pdo)===$before,'Foreign/duplicate/FK updates preserve header, all details and history');
    ensure(count($repo->all(['cliente_id'=>1]))===0,'Nullable client filter');
    ensure(count($repo->all(['fecha_hasta'=>'2026-10-04 10:00:00']))>0,'Inclusive upper date filter');
    conflict(fn()=>$repo->dispatchableDetails(999999),Repo::NOT_FOUND);

    $used=$repo->create(prepHeader('used'),[detail('10.125',1),detail('5')],1);
    $usedLines=array_map('editable',$repo->find($used)['details']);
    dispatch($pdo,$used,(int)$usedLines[0]['id'],'2.125');
    dispatch($pdo,$used,(int)$usedLines[0]['id'],'1.001','EN_TRANSITO');
    dispatch($pdo,$used,(int)$usedLines[0]['id'],'4.999','CANCELADO');
    $pending=$repo->dispatchableDetails($used);
    ensure($pending[0]['cantidad_asignada_a_despachos_vigentes']==='3.126' && $pending[0]['cantidad_pendiente']==='6.999','Partial exact sums exclude cancelled');
    ensure($pending[1]['cantidad_asignada_a_despachos_vigentes']==='0.000' && $pending[1]['cantidad_pendiente']==='5.000','Unused pending');
    foreach(['producto_id'=>2,'unidad_id'=>2,'lote_id'=>null,'cantidad_preparada'=>'3.125'] as $field=>$value) {
        $changed=$usedLines; $changed[0][$field]=$value; $before=snapshot($pdo);
        conflict(fn()=>$repo->update($used,1,editHeader(),$changed,1),Repo::USED_LINE_CONFLICT);
        ensure(snapshot($pdo)===$before,'Used line failure atomic');
    }
    conflict(fn()=>$repo->update($used,1,editHeader(),[$usedLines[1]],1),Repo::USED_LINE_CONFLICT);
    $changed=$usedLines; $changed[0]['cantidad_preparada']='3.126';
    ensure($repo->update($used,1,editHeader(),$changed,1)===2,'Committed boundary accepted');
    ensure($repo->dispatchableDetails($used)[0]['cantidad_pendiente']==='0.000','Zero pending');
    $cancel=$repo->create(prepHeader('only-cancel'),[detail('8',1)],1);
    $cancelLine=editable($repo->find($cancel)['details'][0]); dispatch($pdo,$cancel,(int)$cancelLine['id'],'7','CANCELADO');
    conflict(fn()=>$repo->update($cancel,1,editHeader(),[detail('0')],1),Repo::USED_LINE_CONFLICT);
    $cancelLine['cantidad_preparada']='0';
    ensure($repo->update($cancel,1,editHeader(),[$cancelLine],1)===2,'Cancelled retains identity consumes zero');
    $pdo->beginTransaction(); $pdo->exec("UPDATE usuarios SET nombre='caller' WHERE id=1");
    $outer=$repo->create(prepHeader('outer'),[detail()],1);
    ensure($pdo->inTransaction(),'Caller transaction not committed');
    $before=snapshot($pdo);
    rejects(fn()=>$repo->create(prepHeader('outer-failure'),[detail(),[...detail(),'producto_id'=>9999]],1),PDOException::class,1452);
    ensure($pdo->inTransaction() && snapshot($pdo)===$before,'Savepoint preserves caller writes');
    ensure($pdo->query('SELECT nombre FROM usuarios WHERE id=1')->fetchColumn()==='caller','Unrelated caller write retained');
    ensure($repo->create(prepHeader('outer'),[detail()],1)===$outer && $pdo->inTransaction(),'Replay inside caller transaction preserves transaction');
    $pdo->exec("INSERT INTO logistica_historial(preparacion_id,version,accion,usuario_id) VALUES($outer,2,'fixture',1)");
    $beforeHistoryFailure=snapshot($pdo);
    rejects(fn()=>$repo->update($outer,1,editHeader(),array_map('editable',$repo->find($outer)['details']),1),PDOException::class,1062);
    ensure($pdo->inTransaction() && snapshot($pdo)===$beforeHistoryFailure,'History collision rolls back operation savepoint without losing caller work');
    $pdo->exec("DELETE FROM logistica_historial WHERE preparacion_id=$outer AND version=2");
    $repo->update($outer,1,editHeader(),array_map('editable',$repo->find($outer)['details']),1);
    $repo->changeState($outer,2,'EN_PREPARACION','PREPARADA',1); $pdo->rollBack();
    ensure($repo->find($outer)===null && $pdo->query('SELECT nombre FROM usuarios WHERE id=1')->fetchColumn()==='Test','Caller rollback owns successful writes');
    $other=connect($pdo->query('SELECT DATABASE()')->fetchColumn());
    $stale=$repo->create(prepHeader('snapshot'),[detail('5')],1); $staleLine=(int)$repo->find($stale)['details'][0]['id'];
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction(); $pdo->query('SELECT COUNT(*) FROM logistica_despachos_cabecera')->fetchColumn();
    $other->beginTransaction(); $other->query("SELECT id FROM logistica_preparaciones_cabecera WHERE id=$stale FOR UPDATE")->fetchColumn();
    dispatch($other,$stale,$staleLine,'1.111'); $other->commit();
    ensure($repo->dispatchableDetails($stale)[0]['cantidad_pendiente']==='3.889','Current pending despite old caller snapshot');
    conflict(fn()=>$repo->update($stale,1,editHeader(),[[...detail('1.110'),'id'=>$staleLine]],1),Repo::USED_LINE_CONFLICT);
    $pdo->rollBack();
    $results=workers($pdo,'create');
    ensure(isset($results[0]['ok'],$results[1]['ok']) && $results[0]['ok']===$results[1]['ok'],'Concurrent idempotent creates same ID');
    $raceId=$results[0]['ok'];
    ensure((int)$pdo->query("SELECT COUNT(*) FROM logistica_historial WHERE preparacion_id=$raceId")->fetchColumn()===1,'Concurrent replay history once');
    foreach(['update','state'] as $mode) {
        $raceId=$repo->create(prepHeader('race-'.$mode),[detail()],1); $results=workers($pdo,$mode,$raceId);
        $ok=array_values(array_filter($results,fn($r)=>isset($r['ok']))); $fail=array_values(array_filter($results,fn($r)=>isset($r['error'])));
        ensure(count($ok)===1 && count($fail)===1 && $ok[0]['ok']===2 && $fail[0]['code']===Repo::VERSION_CONFLICT,'Concurrent '.$mode.' one winner '.json_encode($results));
        ensure((int)$repo->find($raceId)['header']['version']===2,'Concurrent final version');
        ensure((int)$pdo->query("SELECT COUNT(*) FROM logistica_historial WHERE preparacion_id=$raceId")->fetchColumn()===2,'Concurrent history once per version');
    }
    echo "Logistics preparation repository: {$GLOBALS['checks']} checks passed on ".$pdo->query('SELECT VERSION()')->fetchColumn()."\n";
} finally { $db->close(); }

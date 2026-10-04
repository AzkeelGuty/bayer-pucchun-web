<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/003_operational_permissions.sql');
    $db->load('database/migrations/004_catalogos_masivos_busqueda.sql');
    $db->load('database/migrations/005_api_tokens.sql');
    $pdo = $db->pdo;
    fixtures($pdo);
    (new App\Repositories\DocumentRepository($pdo))->create(docHeader(), lines(), 1);
    (new App\Repositories\GuideRepository($pdo))->create(guideHeader(), lines(), 1);
    (new App\Repositories\StockRepository($pdo))->create(stockHeader(), lines(true), 1);
    $originalTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $before = [];
    foreach ($originalTables as $table) {
        $before[$table] = [$pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1], $pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll()];
    }
    $migration = 'database/migrations/006_logistica_persistencia.sql';
    $db->load($migration);
    foreach ($before as $table => [$ddl, $rows]) {
        ensure($pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1] === $ddl, 'Existing DDL unchanged: ' . $table);
        if ($table === 'schema_migrations') {
            foreach($rows as $row) { $st=$pdo->prepare('SELECT * FROM schema_migrations WHERE version=?'); $st->execute([$row['version']]); ensure($st->fetch()===$row,'Previous migration record preserved'); }
        } else ensure($pdo->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll() === $rows, 'Existing data unchanged: ' . $table);
    }
    // Independent approved contract: never derive expected structures from migration SQL.
    // Column tuples: SQL type, nullable, default (normalized across MariaDB/MySQL).
    $audit = [
        'id'=>['bigint',false,null], 'version'=>['int unsigned',false,'1'],
        'created_at'=>['datetime',false,'current_timestamp'], 'created_by'=>['bigint',false,null],
        'updated_at'=>['datetime',true,null], 'updated_by'=>['bigint',true,null],
        'idempotency_key'=>['varchar(64)',false,null], 'request_hash'=>['char(64)',false,null],
    ];
    $contract = [
        'logistica_preparaciones_cabecera'=>$audit + [
            'fecha_preparacion'=>['datetime',false,null], 'almacen_id'=>['int',false,null],
            'cliente_id'=>['bigint',true,null], 'direccion_destino'=>['varchar(255)',false,null],
            'departamento_id'=>['int',true,null], 'provincia_id'=>['int',true,null], 'distrito_id'=>['int',true,null],
            'estado_preparacion'=>['varchar(20)',false,'EN_PREPARACION'], 'observacion'=>['text',true,null],
            'origen_sistema'=>['varchar(40)',true,null], 'origen_documento_ref'=>['varchar(100)',true,null],
        ],
        'logistica_preparaciones_detalle'=>[
            'id'=>['bigint',false,null], 'preparacion_id'=>['bigint',false,null], 'producto_id'=>['bigint',false,null],
            'unidad_id'=>['int',false,null], 'lote_id'=>['bigint',true,null], 'cantidad_preparada'=>['decimal(14,3)',false,null],
            'origen_linea_ref'=>['varchar(100)',true,null],
        ],
        'logistica_despachos_cabecera'=>$audit + [
            'preparacion_id'=>['bigint',false,null], 'fecha_despacho'=>['datetime',false,null], 'salida_at'=>['datetime',true,null],
            'estado_fisico'=>['varchar(25)',false,'PENDIENTE'], 'guia_id'=>['bigint',true,null],
            'guia_version'=>['int unsigned',true,null], 'observacion'=>['text',true,null],
        ],
        'logistica_despachos_detalle'=>[
            'id'=>['bigint',false,null], 'despacho_id'=>['bigint',false,null], 'preparacion_id'=>['bigint',false,null],
            'preparacion_detalle_id'=>['bigint',false,null], 'cantidad_despachada'=>['decimal(14,3)',false,null],
        ],
        'logistica_entregas_cabecera'=>$audit + [
            'despacho_id'=>['bigint',false,null], 'fecha_entrega'=>['datetime',false,null], 'resultado'=>['varchar(20)',false,null],
            'receptor_nombre'=>['varchar(150)',true,null], 'receptor_documento'=>['varchar(30)',true,null],
            'observacion'=>['text',true,null], 'anulada_at'=>['datetime',true,null], 'anulada_by'=>['bigint',true,null],
            'motivo_anulacion'=>['text',true,null],
        ],
        'logistica_entregas_detalle'=>[
            'id'=>['bigint',false,null], 'entrega_id'=>['bigint',false,null], 'despacho_id'=>['bigint',false,null],
            'despacho_detalle_id'=>['bigint',false,null], 'cantidad_aceptada'=>['decimal(14,3)',false,null],
            'cantidad_rechazada'=>['decimal(14,3)',false,'0.000'], 'motivo_rechazo'=>['varchar(255)',true,null],
        ],
        'logistica_incidencias'=>[
            'id'=>['bigint',false,null], 'despacho_id'=>['bigint',false,null], 'despacho_detalle_id'=>['bigint',true,null],
            'tipo'=>['varchar(30)',false,null], 'cantidad_afectada'=>['decimal(14,3)',true,null], 'descripcion'=>['text',false,null],
            'created_at'=>['datetime',false,'current_timestamp'], 'created_by'=>['bigint',false,null], 'version'=>['int unsigned',false,'1'],
            'resuelta_at'=>['datetime',true,null], 'resuelta_by'=>['bigint',true,null], 'resolucion'=>['varchar(40)',true,null],
            'observacion_resolucion'=>['text',true,null], 'referencia_externa'=>['varchar(100)',true,null],
        ],
        'logistica_historial'=>[
            'id'=>['bigint',false,null], 'preparacion_id'=>['bigint',true,null], 'despacho_id'=>['bigint',true,null],
            'entrega_id'=>['bigint',true,null], 'version'=>['int unsigned',false,null], 'accion'=>['varchar(40)',false,null],
            'usuario_id'=>['bigint',false,null], 'created_at'=>['datetime',false,'current_timestamp'],
            'motivo'=>['text',true,null], 'metadata_json'=>['json',true,null],
        ],
    ];
    $tables = array_keys($contract);
    $actualTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,10)='logistica_' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $sortedTables = $tables; sort($sortedTables);
    ensure($actualTables === $sortedTables, 'Exactly the eight approved logistics tables');
    $defaults = static function ($value) {
        if ($value === null || $value === 'NULL') return null;
        $value = trim((string)$value, "'");
        return in_array(strtolower($value), ['current_timestamp','current_timestamp()'], true) ? 'current_timestamp' : $value;
    };
    foreach ($contract as $table=>$expectedColumns) {
        $st=$pdo->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $st->execute([$table]); $meta=$st->fetch();
        ensure($meta['ENGINE']==='InnoDB', 'InnoDB: '.$table);
        ensure($meta['TABLE_COLLATION']==='utf8mb4_unicode_ci', 'Harness prerequisite: inherited utf8mb4_unicode_ci: '.$table);
        $columns=array_column($pdo->query('SHOW FULL COLUMNS FROM '.$table)->fetchAll(),null,'Field');
        $actualNames=array_keys($columns); $expectedNames=array_keys($expectedColumns); sort($actualNames); sort($expectedNames);
        ensure($actualNames===$expectedNames, 'Exact approved columns: '.$table);
        foreach ($expectedColumns as $name=>[$type,$nullable,$default]) {
            $actualType=preg_replace('/\b(bigint|int)\(\d+\)/','$1',$columns[$name]['Type']);
            // MariaDB implements JSON as LONGTEXT with JSON_VALID; behavior is tested below.
            if ($type==='json') ensure(in_array($actualType,['json','longtext'],true), 'JSON storage: '.$table.'.'.$name);
            else ensure($actualType===$type, 'Type: '.$table.'.'.$name);
            ensure(($columns[$name]['Null']==='YES')===$nullable, 'Nullability: '.$table.'.'.$name);
            ensure($defaults($columns[$name]['Default'])===$default, 'Default: '.$table.'.'.$name);
        }
        ensure($columns['id']['Key']==='PRI' && str_contains($columns['id']['Extra'],'auto_increment'), 'Auto increment PK: '.$table);
        foreach (['idempotency_key','request_hash'] as $name) if(isset($columns[$name])) ensure($columns[$name]['Collation']==='ascii_bin','ASCII binary collation: '.$table.'.'.$name);
    }
    $expectedUnique = [
        'logistica_preparaciones_cabecera'=>[['idempotency_key']],
        'logistica_preparaciones_detalle'=>[['id','preparacion_id']],
        'logistica_despachos_cabecera'=>[['idempotency_key'],['guia_id'],['id','preparacion_id']],
        'logistica_despachos_detalle'=>[['despacho_id','preparacion_detalle_id'],['id','despacho_id']],
        'logistica_entregas_cabecera'=>[['idempotency_key'],['id','despacho_id']],
        'logistica_entregas_detalle'=>[['entrega_id','despacho_detalle_id']],
        'logistica_historial'=>[['preparacion_id','version'],['despacho_id','version'],['entrega_id','version']],
    ];
    foreach($expectedUnique as $table=>$keys) {
        $indexes=[];
        foreach($pdo->query('SHOW INDEX FROM '.$table)->fetchAll() as $row) if((int)$row['Non_unique']===0) $indexes[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];
        $actual=[]; foreach($indexes as $key) { ksort($key); $actual[]=array_values($key); }
        foreach($keys as $key) ensure(in_array($key,$actual,true),'Unique ordered columns: '.$table.' '.implode(',',$key));
    }
    $expectedFks = [
        ['logistica_preparaciones_detalle','lote_id,producto_id','lotes','id,producto_id'],
        ['logistica_despachos_detalle','despacho_id,preparacion_id','logistica_despachos_cabecera','id,preparacion_id'],
        ['logistica_despachos_detalle','preparacion_detalle_id,preparacion_id','logistica_preparaciones_detalle','id,preparacion_id'],
        ['logistica_entregas_detalle','entrega_id,despacho_id','logistica_entregas_cabecera','id,despacho_id'],
        ['logistica_entregas_detalle','despacho_detalle_id,despacho_id','logistica_despachos_detalle','id,despacho_id'],
        ['logistica_incidencias','despacho_detalle_id,despacho_id','logistica_despachos_detalle','id,despacho_id'],
        ['logistica_despachos_cabecera','guia_id','guias_cabecera','id'],
        ['logistica_preparaciones_cabecera','almacen_id','almacenes','id'],
        ['logistica_preparaciones_cabecera','cliente_id','clientes','id'],
        ['logistica_preparaciones_detalle','preparacion_id','logistica_preparaciones_cabecera','id'],
        ['logistica_despachos_cabecera','preparacion_id','logistica_preparaciones_cabecera','id'],
        ['logistica_entregas_cabecera','despacho_id','logistica_despachos_cabecera','id'],
        ['logistica_incidencias','despacho_id','logistica_despachos_cabecera','id'],
    ];
    foreach(['preparacion_id'=>'logistica_preparaciones_cabecera','despacho_id'=>'logistica_despachos_cabecera','entrega_id'=>'logistica_entregas_cabecera'] as $column=>$parent) $expectedFks[]=['logistica_historial',$column,$parent,'id'];
    foreach($contract as $table=>$columns) foreach(['created_by','updated_by','anulada_by','resuelta_by','usuario_id'] as $column) if(isset($columns[$column])) $expectedFks[]=[$table,$column,'usuarios','id'];
    $actualFks=[];
    $fkRows=$pdo->query("SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND LEFT(TABLE_NAME,10)='logistica_' ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll();
    foreach($fkRows as $row) { $key=$row['TABLE_NAME'].'.'.$row['CONSTRAINT_NAME']; $actualFks[$key]['table']=$row['TABLE_NAME']; $actualFks[$key]['columns'][]=$row['COLUMN_NAME']; $actualFks[$key]['parent']=$row['REFERENCED_TABLE_NAME']; $actualFks[$key]['targets'][]=$row['REFERENCED_COLUMN_NAME']; }
    $actual=[]; foreach($actualFks as $fk) $actual[]=[$fk['table'],implode(',',$fk['columns']),$fk['parent'],implode(',',$fk['targets'])];
    foreach($expectedFks as $fk) ensure(in_array($fk,$actual,true),'Approved FK: '.implode(' -> ',$fk));
    $relations=$pdo->query("SELECT REFERENCED_TABLE_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,10)='logistica_'")->fetchAll();
    ensure(count($relations)>=count($expectedFks),'FK inventory not empty');
    foreach($relations as $relation) {
        ensure($relation['DELETE_RULE']==='RESTRICT' && $relation['UPDATE_RULE']==='RESTRICT','Restrictive logistics FK');
        ensure(!in_array($relation['REFERENCED_TABLE_NAME'],['guias_detalle','stock_detalle','stock_cabecera'],true),'No guide-line or stock coupling');
    }
    $exec = static fn(string $query) => $pdo->exec($query);
    // Assert the intended integrity error AND preservation after every rejected statement.
    $bad = static function(string $query, string $kind='check') use($pdo,$originalTables,$tables): void {
        $snapshot=static function() use($pdo,$originalTables,$tables): array {
            $rows=[]; foreach(array_merge($originalTables,$tables) as $table) $rows[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
            return $rows;
        };
        $before=$snapshot();
        try { $pdo->exec($query); } catch(PDOException $error) {
            $codes=['check'=>[3819,4025],'fk'=>[1451,1452],'unique'=>[1062],'json'=>[3140,4025]];
            ensure(in_array((int)($error->errorInfo[1]??0),$codes[$kind],true),'Expected '.$kind.' integrity violation, got '.($error->errorInfo[1]??'unknown'));
            ensure($snapshot()===$before,'Rejected statement preserves all existing rows');
            return;
        }
        throw new RuntimeException('Expected '.$kind.' failure: '.$query);
    };
    $hash = str_repeat('a', 64);
    foreach ([1,2] as $id) {
        $exec("INSERT INTO logistica_preparaciones_cabecera(id,fecha_preparacion,almacen_id,direccion_destino,created_by,idempotency_key,request_hash) VALUES($id,'2026-10-04 08:00:00',1,'Destino',1,'prep-$id','$hash')");
        $exec("INSERT INTO logistica_preparaciones_detalle(id,preparacion_id,producto_id,unidad_id,lote_id,cantidad_preparada) VALUES($id,$id,1,1,1,2.500)");
        $exec("INSERT INTO logistica_despachos_cabecera(id,preparacion_id,fecha_despacho,created_by,idempotency_key,request_hash) VALUES($id,$id,'2026-10-04 09:00:00',1,'desp-$id','$hash')");
        $exec("INSERT INTO logistica_despachos_detalle(id,despacho_id,preparacion_id,preparacion_detalle_id,cantidad_despachada) VALUES($id,$id,$id,$id,1.250)");
        $exec("INSERT INTO logistica_entregas_cabecera(id,despacho_id,fecha_entrega,resultado,created_by,idempotency_key,request_hash) VALUES($id,$id,'2026-10-04 10:00:00','FALLIDA',1,'ent-$id','$hash')");
    }
    ensure((string)$pdo->query('SELECT cantidad_preparada FROM logistica_preparaciones_detalle WHERE id=1')->fetchColumn() === '2.500', 'Fractional preparation preserved');
    $exec("INSERT INTO logistica_preparaciones_detalle(id,preparacion_id,producto_id,unidad_id,lote_id,cantidad_preparada) VALUES(3,1,1,1,NULL,0),(4,1,1,1,3,1.125)");
    ensure((int)$pdo->query('SELECT COUNT(*) FROM logistica_preparaciones_detalle WHERE preparacion_id=1')->fetchColumn() === 3, 'Same product with distinct lots and no lot');
    $bad('UPDATE logistica_preparaciones_detalle SET lote_id=2 WHERE id=1', 'fk');
    $bad('UPDATE logistica_preparaciones_detalle SET cantidad_preparada=-0.001 WHERE id=1');
    $bad('UPDATE logistica_despachos_detalle SET cantidad_despachada=0 WHERE id=1');
    $bad('UPDATE logistica_despachos_detalle SET cantidad_despachada=-1 WHERE id=1');
    $bad('UPDATE logistica_despachos_detalle SET preparacion_detalle_id=2 WHERE id=1', 'fk');
    $bad('UPDATE logistica_despachos_detalle SET preparacion_id=2 WHERE id=1', 'fk');
    $bad('UPDATE logistica_preparaciones_cabecera SET departamento_id=1 WHERE id=1');
    $bad("UPDATE logistica_preparaciones_cabecera SET origen_sistema='G8' WHERE id=1");
    $exec("UPDATE logistica_preparaciones_cabecera SET origen_sistema='G8',origen_documento_ref='opaque:not-local' WHERE id=1");
    foreach (['logistica_preparaciones_cabecera','logistica_despachos_cabecera','logistica_entregas_cabecera'] as $table) {
        $bad('UPDATE ' . $table . ' SET version=0 WHERE id=1');
        $bad('UPDATE ' . $table . " SET request_hash='invalid' WHERE id=1");
        $bad('UPDATE ' . $table . " SET idempotency_key='' WHERE id=1");
        $bad('UPDATE ' . $table . ' SET created_by=999 WHERE id=1', 'fk');
        $key = $pdo->query('SELECT idempotency_key FROM ' . $table . ' WHERE id=1')->fetchColumn();
        $bad('UPDATE ' . $table . " SET idempotency_key='$key' WHERE id=2", 'unique');
    }
    $bad("UPDATE logistica_preparaciones_cabecera SET estado_preparacion='preparada' WHERE id=1");
    $bad("UPDATE logistica_despachos_cabecera SET estado_fisico='DESPACHADO' WHERE id=1");
    $bad("UPDATE logistica_entregas_cabecera SET resultado='ENTREGA_PARCIAL' WHERE id=1");
    $bad('UPDATE logistica_despachos_cabecera SET guia_id=1 WHERE id=1');
    $bad('UPDATE logistica_despachos_cabecera SET guia_version=1 WHERE id=1');
    $bad('UPDATE logistica_despachos_cabecera SET guia_id=1,guia_version=0 WHERE id=1');
    $exec('UPDATE logistica_despachos_cabecera SET guia_id=1,guia_version=1 WHERE id=1');
    $bad('UPDATE logistica_despachos_cabecera SET guia_id=1,guia_version=1 WHERE id=2', 'unique');
    $bad('DELETE FROM guias_cabecera WHERE id=1', 'fk');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM logistica_entregas_detalle')->fetchColumn() === 0, 'Failed attempts allow no quantitative details');
    ensure($pdo->query('SELECT receptor_nombre FROM logistica_entregas_cabecera WHERE id=1')->fetchColumn() === null, 'Failed attempt allows no receiver');
    $exec("UPDATE logistica_entregas_cabecera SET resultado='CON_RECEPCION',receptor_nombre='Receptor' WHERE id=1");
    $exec('INSERT INTO logistica_entregas_detalle(id,entrega_id,despacho_id,despacho_detalle_id,cantidad_aceptada) VALUES(1,1,1,1,0.750)');
    $bad('UPDATE logistica_entregas_detalle SET despacho_detalle_id=2 WHERE id=1', 'fk');
    $bad('UPDATE logistica_entregas_detalle SET despacho_id=2 WHERE id=1', 'fk');
    $bad('UPDATE logistica_entregas_detalle SET cantidad_aceptada=-1 WHERE id=1');
    $bad('UPDATE logistica_entregas_detalle SET cantidad_rechazada=-1 WHERE id=1');
    $bad('UPDATE logistica_entregas_detalle SET cantidad_aceptada=0 WHERE id=1');
    $bad('UPDATE logistica_entregas_detalle SET cantidad_rechazada=0.250 WHERE id=1');
    $bad("UPDATE logistica_entregas_detalle SET cantidad_rechazada=0.250,motivo_rechazo=' ' WHERE id=1");
    $exec("UPDATE logistica_entregas_detalle SET cantidad_rechazada=0.250,motivo_rechazo='Dano' WHERE id=1");
    $exec("UPDATE logistica_entregas_detalle SET cantidad_aceptada=0 WHERE id=1");
    $bad("UPDATE logistica_entregas_cabecera SET anulada_at=CURRENT_TIMESTAMP WHERE id=1");
    $bad("UPDATE logistica_entregas_cabecera SET anulada_at=CURRENT_TIMESTAMP,anulada_by=1,motivo_anulacion=' ' WHERE id=1");
    $exec("UPDATE logistica_entregas_cabecera SET anulada_at=CURRENT_TIMESTAMP,anulada_by=1,motivo_anulacion='Error de captura' WHERE id=1");
    $exec("INSERT INTO logistica_incidencias(id,despacho_id,tipo,descripcion,created_by) VALUES(1,1,'RETRASO','Retraso general',1)");
    $exec("INSERT INTO logistica_incidencias(id,despacho_id,despacho_detalle_id,tipo,cantidad_afectada,descripcion,created_by) VALUES(2,1,1,'DANO',0.250,'Dano de linea',1)");
    $bad('UPDATE logistica_incidencias SET despacho_detalle_id=2 WHERE id=2', 'fk');
    $bad('UPDATE logistica_incidencias SET cantidad_afectada=-1 WHERE id=2');
    $exec('UPDATE logistica_incidencias SET cantidad_afectada=NULL WHERE id=2');
    ensure($pdo->query('SELECT cantidad_afectada FROM logistica_incidencias WHERE id=2')->fetchColumn() === null, 'Line incidence may have unknown quantity');
    $bad('UPDATE logistica_incidencias SET cantidad_afectada=1 WHERE id=1');
    $bad('UPDATE logistica_incidencias SET version=0 WHERE id=1');
    $bad("UPDATE logistica_incidencias SET tipo='INVALIDO' WHERE id=1");
    $bad('UPDATE logistica_incidencias SET resuelta_at=CURRENT_TIMESTAMP WHERE id=1');
    $exec("UPDATE logistica_incidencias SET resuelta_at=CURRENT_TIMESTAMP,resuelta_by=1,resolucion='SIN_EFECTO_EN_CANTIDAD' WHERE id=1");
    $exec("INSERT INTO logistica_historial(id,despacho_id,version,accion,usuario_id,metadata_json) VALUES(1,1,1,'crear',1,'{}')");
    $bad('UPDATE logistica_historial SET preparacion_id=1 WHERE id=1');
    $bad('UPDATE logistica_historial SET despacho_id=NULL WHERE id=1');
    $bad('UPDATE logistica_historial SET version=0 WHERE id=1');
    $bad("UPDATE logistica_historial SET metadata_json='not-json' WHERE id=1", 'json');
    $bad("INSERT INTO logistica_historial(despacho_id,version,accion,usuario_id) VALUES(1,1,'duplicado',1)", 'unique');
    foreach (['logistica_preparaciones_cabecera','logistica_preparaciones_detalle','logistica_despachos_cabecera','logistica_despachos_detalle','logistica_entregas_cabecera'] as $table) $bad('DELETE FROM ' . $table . ' WHERE id=1', 'fk');
    foreach (['logistica_preparaciones_cabecera','logistica_despachos_cabecera','logistica_entregas_cabecera'] as $table) {
        foreach ([str_repeat('0',64),str_repeat('abcdef0123456789',4)] as $validHash) {
            $exec("UPDATE $table SET request_hash='$validHash' WHERE id=1");
            ensure($pdo->query("SELECT request_hash FROM $table WHERE id=1")->fetchColumn()===$validHash,'Exact valid lowercase hash: '.$table);
        }
        foreach ([str_repeat('a',63),str_repeat('a',65),str_repeat('a',63).'g',str_repeat('A',64)] as $invalidHash) {
            // Oversize CHAR can fail before CHECK under strict mode.
            if(strlen($invalidHash)===65) {
                $beforeHash=$pdo->query("SELECT request_hash FROM $table WHERE id=1")->fetchColumn();
                rejects(fn()=>$pdo->exec("UPDATE $table SET request_hash='$invalidHash' WHERE id=1"),PDOException::class,1406);
                ensure($pdo->query("SELECT request_hash FROM $table WHERE id=1")->fetchColumn()===$beforeHash,'Oversize hash preserves value');
            } else $bad("UPDATE $table SET request_hash='$invalidHash' WHERE id=1");
        }
    }
    $states=[
        'logistica_preparaciones_cabecera'=>['estado_preparacion',['EN_PREPARACION','PREPARADA','CANCELADA']],
        'logistica_despachos_cabecera'=>['estado_fisico',['PENDIENTE','EN_TRANSITO','ENTREGADO','CERRADO_CON_INCIDENCIA','CANCELADO']],
        'logistica_entregas_cabecera'=>['resultado',['CON_RECEPCION','RECHAZADA','FALLIDA']],
    ];
    // SQL accepts these values; eligibility/result-detail consistency belong to Services.
    foreach($states as $table=>[$column,$values]) {
        foreach($values as $value) { $exec("UPDATE $table SET $column='$value' WHERE id=2"); ensure($pdo->query("SELECT $column FROM $table WHERE id=2")->fetchColumn()===$value,'Approved value: '.$value); }
        $bad("UPDATE $table SET $column='INVALIDO' WHERE id=2");
    }
    $bad('UPDATE logistica_despachos_cabecera SET guia_id=999999,guia_version=1 WHERE id=2','fk');
    $bad('INSERT INTO logistica_despachos_detalle(despacho_id,preparacion_id,preparacion_detalle_id,cantidad_despachada) VALUES(1,1,1,0.125)','unique');
    $bad('INSERT INTO logistica_entregas_detalle(entrega_id,despacho_id,despacho_detalle_id,cantidad_aceptada) VALUES(1,1,1,0.125)','unique');
    foreach(['preparacion_id','entrega_id'] as $column) {
        $exec("INSERT INTO logistica_historial($column,version,accion,usuario_id) VALUES(1,1,'crear',1)");
        ensure((int)$pdo->query("SELECT COUNT(*) FROM logistica_historial WHERE $column=1 AND version=1")->fetchColumn()===1,'Valid history: '.$column);
        $bad("INSERT INTO logistica_historial($column,version,accion,usuario_id) VALUES(1,1,'duplicado',1)",'unique');
    }
    // One history entry per aggregate/resulting version: combined facts go in metadata.
    $exec("INSERT INTO logistica_historial(despacho_id,version,accion,usuario_id,metadata_json) VALUES(1,2,'salida',1,'{\"estado_nuevo\":\"EN_TRANSITO\"}')");
    $bad("INSERT INTO logistica_historial(despacho_id,version,accion,usuario_id) VALUES(1,2,'cambio_estado',1)",'unique');
    foreach(['DOCUMENTAL','RETRASO','ENTREGA_FALLIDA','OTRO'] as $type) {
        $exec("INSERT INTO logistica_incidencias(despacho_id,despacho_detalle_id,tipo,descripcion,created_by) VALUES(1,1,'$type','Cantidad desconocida',1)");
        ensure((int)$pdo->query("SELECT COUNT(*) FROM logistica_incidencias WHERE despacho_id=1 AND despacho_detalle_id=1 AND tipo='$type' AND cantidad_afectada IS NULL")->fetchColumn()>=1,'Unknown quantity supported: '.$type);
    }
    $bad('UPDATE logistica_incidencias SET cantidad_afectada=0 WHERE id=2');
    $bad('UPDATE logistica_incidencias SET despacho_detalle_id=2 WHERE id=2','fk');
    $rowsBeforeReplay = [];
    foreach ($tables as $table) $rowsBeforeReplay[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    $ddlBeforeReplay = [];
    foreach ($tables as $table) $ddlBeforeReplay[$table] = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $db->load($migration);
    foreach ($ddlBeforeReplay as $table => $ddl) ensure($pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1] === $ddl, 'Migration replay preserves DDL: ' . $table);
    foreach ($rowsBeforeReplay as $table => $rows) ensure($pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll() === $rows, 'Migration replay preserves all data: ' . $table);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=6')->fetchColumn() === 1, 'Migration recorded once');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM logistica_preparaciones_cabecera')->fetchColumn() === 2, 'Replay preserves logistics data');
    // The same additive migration must work after upgrading the historical v1 base.
    $upgrade = new TestDatabase();
    try {
        $upgrade->load('database/migrations/001_initial_schema.sql');
        fixtures($upgrade->pdo);
        $upgrade->load('database/migrations/002_schema_v2.sql');
        $upgrade->load('database/migrations/003_operational_permissions.sql');
        $upgrade->load('database/migrations/004_catalogos_masivos_busqueda.sql');
        $upgrade->load('database/migrations/005_api_tokens.sql');
        $upgrade->load($migration);
        foreach ($tables as $table) {
                $normalize = static fn(string $ddl): string => preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl);
            $a = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
            $b = $upgrade->pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
            ensure($normalize($a) === $normalize($b), 'Clean/upgraded logistics schema matches: ' . $table);
        }
    } finally {
        $upgrade->close();
    }
    // Recovery means creating missing tables, not reconciling divergent existing DDL.
    $recovery=new TestDatabase();
    try {
        $recovery->load('database/schemas/002_schema_v2.sql');
        $recovery->load($migration);
        $recovery->pdo->exec('DROP TABLE logistica_historial');
        $recovery->load($migration);
        ensure((int)$recovery->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logistica_historial'")->fetchColumn()===1,'Version 6 with missing table: direct replay recreates table');
        ensure((int)$recovery->pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=6')->fetchColumn()===1,'Recovery does not duplicate version');
        $recovery->pdo->exec('ALTER TABLE logistica_historial ADD COLUMN divergence_probe INT NULL');
        $recovery->load($migration);
        ensure((int)$recovery->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logistica_historial' AND COLUMN_NAME='divergence_probe'")->fetchColumn()===1,'Replay intentionally does not reconcile divergent DDL');
    } finally { $recovery->close(); }
    echo 'Logistics schema: ' . $GLOBALS['checks'] . " checks OK\n";
} finally {
    $db->close();
}

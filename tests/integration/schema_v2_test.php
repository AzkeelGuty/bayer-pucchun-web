<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$databases = [];
try {
    $clean = $databases[] = new TestDatabase();
    $clean->load('database/schemas/002_schema_v2.sql');
    fixtures($clean->pdo);
    ensure((int) $clean->pdo->query('SELECT version FROM schema_migrations')->fetchColumn() === 2, 'Clean schema version');

    $upgrade = $databases[] = new TestDatabase();
    $upgrade->load('database/migrations/001_initial_schema.sql');
    fixtures($upgrade->pdo);
    $upgrade->pdo->exec("INSERT INTO documentos_cabecera(id,tipo_documento_id,numero,fecha,cliente_id,vendedor_id,sucursal_id,created_by) VALUES(1,1,'LEGACY-1','2026-09-11',1,1,1,1)");
    $upgrade->pdo->exec("INSERT INTO documentos_detalle(documento_id,producto_id,unidad_id,cantidad,valor_unitario) VALUES(1,1,1,2.5,10)");
    $upgrade->pdo->exec("INSERT INTO guias_cabecera(id,numero,fecha,cliente_id,vendedor_id,sucursal_id) VALUES(1,'LEGACY-G','2026-09-11',1,1,1)");
    $upgrade->pdo->exec("INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES(1,1,1,2.5)");
    $upgrade->pdo->exec("INSERT INTO stock_cabecera(id,fecha_stock,almacen_id) VALUES(1,'2026-09-11',1)");
    $upgrade->pdo->exec("INSERT INTO stock_detalle(stock_id,lote_id,producto_id,unidad_id,cantidad) VALUES(1,1,1,1,0)");
    // Same date/warehouse with a different logical line is valid legacy data.
    $upgrade->pdo->exec("INSERT INTO stock_cabecera(id,fecha_stock,almacen_id) VALUES(2,'2026-09-11',1)");
    $upgrade->pdo->exec("INSERT INTO stock_detalle(stock_id,producto_id,unidad_id,cantidad) VALUES(2,2,1,4)");
    $before = $upgrade->pdo->query('SELECT * FROM documentos_cabecera')->fetch();
    $upgrade->load('database/migrations/002_schema_v2.sql');
    $after = $upgrade->pdo->query('SELECT * FROM documentos_cabecera')->fetch();
    ensure(array_intersect_key($after, $before) === $before, 'Upgrade preserves original header values');
    ensure($after['updated_at'] === null && $after['validated_by'] === null && (int) $after['version'] === 1, 'Do not invent historical audit data');
    ensure($upgrade->pdo->query('SELECT idempotency_key FROM stock_cabecera')->fetchColumn() === 'legacy-stock-1', 'Legacy snapshot identity');
    ensure((string) $upgrade->pdo->query('SELECT cantidad FROM stock_detalle')->fetchColumn() === '0.000', 'Zero stock remains valid');
    ensure((int) $upgrade->pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn() === 2, 'Migration preserves separate same-day loads');
    $legacyStock = new App\Repositories\Operations\StockRepository($upgrade->pdo);
    ensure($legacyStock->updateDraft(1, 1, stockHeader('legacy-stock-1'), lines(true), 1) === 2, 'Migrated draft can be edited with its reserved key');
    rejects(fn() => $legacyStock->create(stockHeader('legacy-stock-999', '2026-09-12'), lines(true), 1), InvalidArgumentException::class);

    $tables = $clean->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $a = $clean->pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1];
        $b = $upgrade->pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1];
        $normalize = static function (string $sql): array {
            $definitions = array_map(static fn(string $line): string => rtrim(trim($line), ','), explode("\n", preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql)));
            sort($definitions); // ALTER may reorder otherwise identical indexes.
            return $definitions;
        };
        ensure($normalize($a) === $normalize($b), "Clean and upgraded table differ: $table\n$a\n$b");
    }
    rejects(fn() => $upgrade->load('database/migrations/002_schema_v2.sql'), PDOException::class, 1644);

    // Check constraints directly through SQL so passing repository validation cannot hide a broken schema.
    foreach (['INVALIDO', 'borrador'] as $state) {
        rejects(fn() => $upgrade->pdo->exec("UPDATE documentos_cabecera SET estado_registro='$state' WHERE id=1"), PDOException::class);
    }
    rejects(fn() => $upgrade->pdo->exec('UPDATE documentos_cabecera SET version=0 WHERE id=1'), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec('UPDATE documentos_cabecera SET updated_by=999 WHERE id=1'), PDOException::class, 1452);
    rejects(fn() => $upgrade->pdo->exec('UPDATE documentos_detalle SET cantidad=0'), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec('UPDATE guias_detalle SET cantidad=-1'), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec('UPDATE stock_detalle SET cantidad=-1'), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec('UPDATE stock_detalle SET lote_id=2'), PDOException::class, 1452);
    $upgrade->pdo->exec('UPDATE stock_detalle SET lote_id=NULL WHERE producto_id=1');
    rejects(fn() => $upgrade->pdo->exec('INSERT INTO stock_detalle(stock_id,producto_id,unidad_id,cantidad,fecha_stock,almacen_id) VALUES(1,1,1,2,\'2026-09-11\',1)'), PDOException::class, 1062);
    $upgrade->pdo->exec("INSERT INTO stock_cabecera(id,fecha_stock,almacen_id,idempotency_key,request_hash) VALUES(3,'2026-09-11',1,'another',REPEAT('a',64))");
    rejects(fn() => $upgrade->pdo->exec("INSERT INTO stock_detalle(stock_id,producto_id,unidad_id,cantidad,fecha_stock,almacen_id) VALUES(3,1,1,2,'2026-09-11',1)"), PDOException::class, 1062);
    rejects(fn() => $upgrade->pdo->exec("INSERT INTO stock_detalle(stock_id,producto_id,unidad_id,cantidad,fecha_stock,almacen_id) VALUES(3,1,2,2,'2026-09-12',1)"), PDOException::class, 1452);
    $upgrade->pdo->exec("INSERT INTO stock_detalle(stock_id,producto_id,unidad_id,cantidad,fecha_stock,almacen_id) VALUES(3,1,2,2,'2026-09-11',1)");
    ensure((int) $upgrade->pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn() === 3, 'Several headers on same date/warehouse allowed');
    rejects(fn() => $upgrade->pdo->exec("UPDATE stock_cabecera SET idempotency_key='' WHERE id=1"), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec("UPDATE stock_cabecera SET request_hash='invalid' WHERE id=1"), PDOException::class);
    $upgrade->pdo->exec("INSERT INTO publicaciones(id,modulo,usuario_id,estado) VALUES(1,'stock',1,'PUBLICADO'),(2,'stock',1,'PUBLICADO')");
    $upgrade->pdo->exec("INSERT INTO detalle_publicacion(publicacion_id,registro_id,dataset,version) VALUES(1,1,'stock',1)");
    rejects(fn() => $upgrade->pdo->exec("INSERT INTO detalle_publicacion(publicacion_id,registro_id,dataset,version) VALUES(2,1,'stock',1)"), PDOException::class, 1062);
    $upgrade->pdo->exec("INSERT INTO validaciones(id,modulo,registro_id,usuario_id,resultado) VALUES(1,'stock',1,1,'ERROR')");
    $upgrade->pdo->exec("INSERT INTO validaciones_detalle(validacion_id,codigo,campo,severidad,mensaje,resultado) VALUES(1,'VAL-007','cantidad','BLOQUEANTE','Fixture','ERROR')");
    rejects(fn() => $upgrade->pdo->exec("UPDATE validaciones_detalle SET codigo='VAL-999'"), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec("INSERT INTO homologacion_productos_bayer(partner_id,producto_id,material_id,valid_from,valid_until) VALUES(1,1,'M1','2026-10-01','2026-09-01')"), PDOException::class);
    rejects(fn() => $upgrade->pdo->exec("INSERT INTO auditoria_acciones(usuario_id,modulo,accion,metadata_json) VALUES(1,'test','test','invalid-json')"), PDOException::class);

    // Preflight rejects incompatible v1 data before touching any existing table.
    $bad = $databases[] = new TestDatabase();
    $bad->load('database/migrations/001_initial_schema.sql');
    fixtures($bad->pdo);
    $bad->pdo->exec("INSERT INTO guias_cabecera(id,numero,fecha,cliente_id,vendedor_id,sucursal_id) VALUES(1,'BAD','2026-09-11',1,1,1)");
    $bad->pdo->exec('INSERT INTO guias_detalle(guia_id,producto_id,unidad_id,cantidad) VALUES(1,1,1,0)');
    rejects(fn() => $bad->load('database/migrations/002_schema_v2.sql'), PDOException::class, 1644);
    ensure((int) $bad->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documentos_cabecera' AND COLUMN_NAME='version'")->fetchColumn() === 0, 'Preflight must precede DDL');
    $bad->pdo->exec('UPDATE guias_detalle SET cantidad=1');
    $bad->pdo->exec("INSERT INTO stock_cabecera(id,fecha_stock,almacen_id) VALUES(1,'2026-09-11',1),(2,'2026-09-11',1)");
    $bad->pdo->exec('INSERT INTO stock_detalle(stock_id,producto_id,unidad_id,cantidad) VALUES(1,1,1,1),(2,1,1,2)');
    rejects(fn() => $bad->load('database/migrations/002_schema_v2.sql'), PDOException::class, 1644);
    ensure((int) $bad->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stock_detalle' AND COLUMN_NAME='fecha_stock'")->fetchColumn() === 0, 'Cross-header duplicate rejected before schema changes');
    $bad->pdo->exec('UPDATE stock_detalle SET producto_id=2 WHERE stock_id=2');
    $bad->load('database/migrations/002_schema_v2.sql');
    ensure((int) $bad->pdo->query('SELECT version FROM schema_migrations')->fetchColumn() === 2, 'Retry after correcting legacy data');
    echo 'Schema v2: ' . $GLOBALS['checks'] . " checks OK\n";
} finally {
    foreach ($databases as $database) {
        $database->close();
    }
}

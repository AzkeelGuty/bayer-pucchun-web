<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Repositories\DocumentRepository;
use App\Repositories\GuideRepository;
use App\Repositories\StockRepository;

// Two independent PHP/PDO workers exercise real unique-key and optimistic-lock races.
if (($argv[1] ?? '') === '--worker') {
    $database = $argv[2] ?? '';
    if (!preg_match('/^bayer_test_[a-f0-9]{16}$/D', $database)) {
        throw new RuntimeException('Worker can only connect to a generated test database.');
    }
    $pdo = new PDO('mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('TEST_DB_PORT') ?: '3306')
        . ';dbname=' . $database . ';charset=utf8mb4', getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    echo "READY\n";
    flush();
    fgets(STDIN);
    try {
        $mode = $argv[3] ?? '';
        $result = $mode === 'logical'
            ? (new StockRepository($pdo))->create(stockHeader('logical-' . $argv[4], '2026-09-21'), lines(true), 1)
            : ($mode === 'stock'
            ? (new StockRepository($pdo))->create(stockHeader('concurrent', '2026-09-20'), lines(true), 1)
            : (new DocumentRepository($pdo))->markValidated((int) $argv[4], 1, 1));
        echo json_encode(['ok' => $result], JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['error' => get_class($error), 'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
    exit;
}

function race(PDO $pdo, string $mode, int $id = 0): array
{
    $workers = [];
    try {
        for ($i = 0; $i < 2; ++$i) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $pdo->query('SELECT DATABASE()')->fetchColumn(), $mode, (string) ($mode === 'logical' ? $i : $id)],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot launch concurrency worker');
            }
            stream_set_timeout($pipes[1], 20);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [, $pipes]) {
            ensure(trim((string) fgets($pipes[1])) === 'READY', 'Worker started');
        }
        foreach ($workers as [, $pipes]) {
            fwrite($pipes[0], "GO\n");
            fflush($pipes[0]);
        }
        $results = [];
        foreach ($workers as [, $pipes]) {
            $line = fgets($pipes[1]);
            ensure(is_string($line), 'Worker completed within timeout');
            $results[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
    }
}

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $pdo = $db->pdo;
    fixtures($pdo);
    $documents = new DocumentRepository($pdo);
    $guides = new GuideRepository($pdo);
    $stock = new StockRepository($pdo);

    $docId = $documents->create(docHeader(), lines(), 1);
    $guideId = $guides->create(guideHeader(), lines(), 1);
    $stockId = $stock->create(stockHeader(), lines(true), 1);
    foreach ([[$documents, $docId], [$guides, $guideId], [$stock, $stockId]] as [$repository, $id]) {
        $record = $repository->find($id);
        ensure(count($record['details']) === 2 && $record['header']['estado_registro'] === 'BORRADOR', 'Aggregate persisted in draft');
        ensure((int) $record['header']['created_by'] === 1 && (int) $record['header']['version'] === 1, 'Actor and initial version');
        ensure($repository->find(999999) === null, 'Missing ID returns null');
        ensure(count($repository->all(['estado_registro' => 'BORRADOR'])) === 1, 'Filtered listing');
        ensure((string) $repository->all()[0]['cantidad'] === '3.500', 'Aggregate total');
        ensure($repository->all(['fecha_desde' => '2027-01-01']) === [], 'Date filter');
        rejects(fn() => $repository->all(['estado_registro; DROP TABLE usuarios' => 'x']), InvalidArgumentException::class);
        rejects(fn() => $repository->all([], 301), InvalidArgumentException::class);
        rejects(fn() => $repository->markPublished($id, 1, 1), RuntimeException::class);
    }
    ensure($documents->findByNumber(1, 'F001-1')['header']['id'] === $docId, 'Document number lookup');
    ensure($guides->findByNumber('T001-1')['header']['id'] === $guideId, 'Guide number lookup');
    ensure($stock->findByIdempotencyKey('request-1')['header']['id'] === $stockId, 'Stock key lookup');
    ensure($documents->findByNumber(1, "' OR 1=1 --") === null, 'Number query is parameterized');
    ensure($guides->findByNumber('missing') === null && $stock->findByIdempotencyKey('missing') === null, 'Missing keys');

    rejects(fn() => $documents->create(docHeader(), lines(), 1), PDOException::class, 1062);
    rejects(fn() => $guides->create(guideHeader(), lines(), 1), PDOException::class, 1062);
    rejects(fn() => $documents->create(docHeader('EMPTY'), [], 1), InvalidArgumentException::class);
    rejects(fn() => $guides->create([...guideHeader('GEO'), 'distrito_id' => 2], lines(), 1), InvalidArgumentException::class);
    rejects(fn() => $documents->create([...docHeader('STATE'), 'estado_registro' => 'PUBLICADO'], lines(), 1), InvalidArgumentException::class);
    rejects(fn() => $documents->create([...docHeader('DATE'), 'fecha' => '2026-02-30'], lines(), 1), InvalidArgumentException::class);
    rejects(fn() => $documents->create(docHeader('ZERO'), [['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '0']], 1), InvalidArgumentException::class);
    rejects(fn() => $guides->create(guideHeader('ZERO'), [['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '0']], 1), InvalidArgumentException::class);
    rejects(fn() => $documents->create(docHeader('FLOAT'), [['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => 0.1]], 1), InvalidArgumentException::class);
    rejects(fn() => $documents->create(docHeader('OVERFLOW'), [['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '100000000000']], 1), InvalidArgumentException::class);
    rejects(fn() => $documents->create(docHeader('ACTOR'), lines(), 999), PDOException::class, 1452);

    // FK fails on the second detail: no partial insert, replacement, or parent version change.
    $badLines = lines();
    $badLines[1]['producto_id'] = 999;
    foreach ([[$documents, docHeader('FAIL'), 'documentos'], [$guides, guideHeader('FAIL'), 'guias'], [$stock, stockHeader('fail', '2026-09-12'), 'stock']] as [$repository, $header, $prefix]) {
        $beforeHeaders = $pdo->query("SELECT COUNT(*) FROM {$prefix}_cabecera")->fetchColumn();
        $beforeLines = $pdo->query("SELECT COUNT(*) FROM {$prefix}_detalle")->fetchColumn();
        rejects(fn() => $repository->create($header, $badLines, 1), PDOException::class, 1452);
        ensure($pdo->query("SELECT COUNT(*) FROM {$prefix}_cabecera")->fetchColumn() === $beforeHeaders, 'Failed insert rolls back parent');
        ensure($pdo->query("SELECT COUNT(*) FROM {$prefix}_detalle")->fetchColumn() === $beforeLines, 'Failed insert rolls back details');
        ensure(!$pdo->inTransaction(), 'Repository releases owned transaction');
    }
    $before = $documents->find($docId);
    rejects(fn() => $documents->updateDraft($docId, 1, docHeader('REPLACE-FAIL'), $badLines, 2), PDOException::class, 1452);
    ensure($documents->find($docId) === $before, 'Failed replacement restores original aggregate');

    $version = $documents->updateDraft($docId, 1, docHeader('UPDATED'), [lines()[0]], 2);
    ensure($version === 2 && count($documents->find($docId)['details']) === 1, 'Draft replacement advances version');
    ensure((int) $documents->find($docId)['header']['updated_by'] === 2, 'Update actor recorded');
    rejects(fn() => $documents->updateDraft($docId, 1, docHeader(), lines(), 1), RuntimeException::class);
    $version = $documents->markValidated($docId, $version, 2);
    rejects(fn() => $documents->updateDraft($docId, $version, docHeader(), lines(), 1), RuntimeException::class);
    rejects(fn() => $documents->markObserved($docId, $version, 2, ' '), InvalidArgumentException::class);
    $version = $documents->markObserved($docId, $version, 2, 'Corregir cantidad');
    rejects(fn() => $documents->updateDraft($docId, $version, docHeader(), lines(), 1), RuntimeException::class);
    $version = $documents->returnToDraft($docId, $version, 2);
    $version = $documents->markValidated($docId, $version, 2);
    $version = $documents->markPublished($docId, $version, 2);
    rejects(fn() => $documents->returnToDraft($docId, $version, 2), RuntimeException::class);
    rejects(fn() => $documents->updateDraft($docId, $version, docHeader(), lines(), 1), RuntimeException::class);
    $version = $documents->markCancelled($docId, $version, 2, 'Anulacion documentada');
    $record = $documents->find($docId)['header'];
    ensure($record['estado_registro'] === 'ANULADO' && $record['cancellation_reason'] === 'Anulacion documentada', 'Logical cancellation');
    ensure($record['published_at'] !== null && $record['cancelled_at'] !== null && (int) $record['cancelled_by'] === 2, 'Publication/cancellation audit');
    ensure(count($documents->find($docId)['details']) === 1, 'Cancellation preserves details');

    // Caller transaction survives both success and failure and can roll back the whole use case.
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO auditoria_acciones(usuario_id,modulo,accion) VALUES(1,'test','caller')");
    rejects(fn() => $guides->create(guideHeader('SAVEPOINT-FAIL'), $badLines, 1), PDOException::class, 1452);
    ensure($pdo->inTransaction(), 'Failure does not roll back caller transaction');
    ensure((int) $pdo->query("SELECT COUNT(*) FROM auditoria_acciones WHERE accion='caller'")->fetchColumn() === 1, 'Caller write survives failure');
    $nestedId = $guides->create(guideHeader('NESTED'), lines(), 1);
    ensure($pdo->inTransaction(), 'Success does not commit caller transaction');
    $guides->markValidated($nestedId, 1, 2);
    $pdo->rollBack();
    ensure($guides->find($nestedId) === null, 'Caller rollback removes repository changes');

    // Stock: exact decimal canonicalization, order-independent retry, mismatch, NULL lots, dates.
    $reordered = array_reverse(lines(true));
    $reordered[0]['cantidad'] = '01';
    ensure($stock->create(stockHeader(), $reordered, 1) === $stockId, 'Equivalent retry returns same ID');
    $different = lines(true);
    $different[0]['cantidad'] = '99';
    rejects(fn() => $stock->create(stockHeader(), $different, 1), RuntimeException::class);
    rejects(fn() => $stock->create(stockHeader('another-key'), lines(true), 1), PDOException::class, 1062);
    rejects(fn() => $stock->create(stockHeader('duplicate-line', '2026-09-12'), [lines()[0], lines()[0]], 1), InvalidArgumentException::class);
    rejects(fn() => $stock->create(stockHeader('wrong-lot', '2026-09-12'), [['producto_id' => 1, 'unidad_id' => 1, 'lote_id' => 2, 'cantidad' => '1']], 1), InvalidArgumentException::class);
    rejects(fn() => $stock->create(stockHeader('expired', '2026-09-12'), [['producto_id' => 1, 'unidad_id' => 1, 'lote_id' => 3, 'cantidad' => '1']], 1), InvalidArgumentException::class);
    rejects(fn() => $stock->create(stockHeader('negative', '2026-09-12'), [['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '-1']], 1), InvalidArgumentException::class);
    $zeroId = $stock->create(stockHeader('zero', '2026-09-12'), [['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '0']], 1);
    ensure($stock->find($zeroId)['details'][0]['lote_id'] === null, 'Nullable lot accepted');
    ensure($stock->find($zeroId)['details'][0]['cantidad'] === '0.000', 'Zero stock accepted');
    ensure($stock->updateDraft($stockId, 1, stockHeader(), $different, 2) === 2, 'Stock draft edited');
    ensure($stock->create(stockHeader(), lines(true), 1) === $stockId, 'Original request remains idempotent after edit');
    ensure($stock->find($stockId)['details'][0]['cantidad'] === '99.000', 'Retry does not overwrite corrected stock');
    rejects(fn() => $stock->updateDraft($stockId, 2, stockHeader('changed'), lines(true), 1), InvalidArgumentException::class);

    // Each component of the documented key distinguishes an existence record.
    $base = ['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '1'];
    $splitA = $stock->create(stockHeader('split-a', '2026-09-15'), [$base], 1);
    $splitB = $stock->create(stockHeader('split-b', '2026-09-15'), [[...$base, 'producto_id' => 2]], 1);
    $stock->create(stockHeader('split-lot', '2026-09-15'), [[...$base, 'lote_id' => 1]], 1);
    $stock->create(stockHeader('split-unit', '2026-09-15'), [[...$base, 'unidad_id' => 2]], 1);
    $stock->create([...stockHeader('split-warehouse', '2026-09-15'), 'almacen_id' => 2], [$base], 1);
    ensure(count($stock->all(['fecha_desde' => '2026-09-15', 'fecha_hasta' => '2026-09-15'])) === 5, 'Product, lot, unit and warehouse distinguish stock');
    $beforeSplit = $stock->find($splitB);
    rejects(fn() => $stock->updateDraft($splitB, 1, stockHeader('split-b', '2026-09-15'), [$base], 2), PDOException::class, 1062);
    ensure($stock->find($splitB) === $beforeSplit, 'Conflicting edit restores header, details and version');
    ensure($stock->updateDraft($splitA, 1, stockHeader('split-a', '2026-09-16'), [$base], 2) === 2, 'Draft date can change');
    ensure($stock->find($splitA)['details'][0]['fecha_stock'] === '2026-09-16', 'Detail context follows edited header');
    $stock->create(stockHeader('released-key', '2026-09-15'), [$base], 1);
    $concurrentLogical = race($pdo, 'logical');
    ensure(count(array_filter($concurrentLogical, static fn(array $r): bool => isset($r['ok']))) === 1, 'Only one concurrent writer of the full logical key succeeds');
    ensure(count(array_filter($concurrentLogical, static fn(array $r): bool => ($r['error'] ?? '') === PDOException::class)) === 1, 'Different request key cannot bypass logical uniqueness');
    ensure((int) $pdo->query("SELECT COUNT(*) FROM stock_cabecera WHERE fecha_stock='2026-09-21'")->fetchColumn() === 1, 'Losing concurrent load leaves no header');

    $concurrent = race($pdo, 'stock');
    ensure(isset($concurrent[0]['ok'], $concurrent[1]['ok']) && $concurrent[0]['ok'] === $concurrent[1]['ok'], 'Concurrent stock submissions return the same ID: ' . json_encode($concurrent));
    ensure((int) $pdo->query("SELECT COUNT(*) FROM stock_cabecera WHERE idempotency_key='concurrent'")->fetchColumn() === 1, 'Only one concurrent snapshot');
    $raceId = $documents->create(docHeader('RACE'), lines(), 1);
    $concurrent = race($pdo, 'state', $raceId);
    ensure(count(array_filter($concurrent, static fn(array $result): bool => isset($result['ok']))) === 1, 'Only one state writer succeeds');
    ensure(count(array_filter($concurrent, static fn(array $result): bool => ($result['error'] ?? '') === RuntimeException::class)) === 1, 'Stale state writer rejected');
    ensure((int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 2 && (int) $pdo->query('SELECT COUNT(*) FROM lotes')->fetchColumn() === 3, 'Repositories never create masters');
    echo 'Repositories: ' . $GLOBALS['checks'] . " checks OK (including concurrent workers)\n";
} finally {
    $db->close();
}

<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/logistics_fixtures.php';

use App\Exceptions\LogisticsPersistenceException;
use App\Repositories\GuideRepository;
use App\Repositories\LogisticsRepository;

// Workers accept only generated test database names, never the application .env.
if (($argv[1] ?? '') === '--worker') {
    $database = $argv[2] ?? '';
    if (!preg_match('/^bayer_test_[a-f0-9]{16}$/D', $database)) throw new RuntimeException('Unsafe test database name');
    $pdo = new PDO('mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('TEST_DB_PORT') ?: '3306')
        . ';dbname=' . $database . ';charset=utf8mb4', getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $repository = new LogisticsRepository($pdo);
    echo "READY\n";
    flush();
    fgets(STDIN);
    try {
        $mode = $argv[3] ?? '';
        $result = match ($mode) {
            'idem' => $repository->createPreparation(logisticsHeader('race-idem'), logisticsLines(), logisticsContext('race-idem')),
            'void' => $repository->voidDelivery((int) $argv[4], (int) $argv[6], (int) $argv[5], 'EN_TRANSITO',
                'Corrección simultánea', logisticsContext('race-void-' . $argv[7])),
            default => $repository->recordDelivery((int) $argv[4], (int) $argv[5], logisticsDeliveryHeader(),
                [['despacho_detalle_id' => (int) $argv[6], 'cantidad' => '6.000']], 'EN_TRANSITO', logisticsContext('race-delivery-' . $argv[7])),
        };
        echo json_encode(['ok' => $result], JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['error' => get_class($error), 'reason' => $error instanceof LogisticsPersistenceException ? $error->reasonCode() : null,
            'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
    exit;
}

function logisticsRace(PDO $pdo, string $mode, int $id = 0, int $version = 0, int $detailId = 0, int $workerCount = 2): array
{
    $workers = [];
    try {
        for ($i = 0; $i < $workerCount; ++$i) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $pdo->query('SELECT DATABASE()')->fetchColumn(), $mode,
                (string) $id, (string) $version, (string) $detailId, (string) $i],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot launch logistics concurrency worker');
            stream_set_timeout($pipes[1], 30);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [, $pipes]) ensure(trim((string) fgets($pipes[1])) === 'READY', 'Independent worker is ready');
        foreach ($workers as [, $pipes]) { fwrite($pipes[0], "GO\n"); fflush($pipes[0]); }
        $results = [];
        foreach ($workers as [, $pipes]) {
            $line = fgets($pipes[1]);
            ensure(is_string($line), 'Concurrency worker completed within timeout');
            $results[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    } finally {
        foreach ($workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) fclose($pipe);
            if (proc_get_status($process)['running']) proc_terminate($process);
            proc_close($process);
        }
    }
}

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $pdo = $db->pdo;
    $originalTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $originalGuideSchema = $pdo->query('SHOW CREATE TABLE guias_cabecera')->fetch(PDO::FETCH_NUM)[1];
    $originalStockSchema = $pdo->query('SHOW CREATE TABLE stock_detalle')->fetch(PDO::FETCH_NUM)[1];
    $db->load('database/migrations/006_logistics_persistence.sql');
    $db->load('database/migrations/007_logistics_incidents.sql');
    $allTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $added = array_values(array_diff($allTables, $originalTables));
    $expectedTables = logisticsTables();
    sort($added); sort($expectedTables);
    ensure($added === $expectedTables, 'Migrations add exactly ten logistics tables');
    ensure($pdo->query('SHOW CREATE TABLE guias_cabecera')->fetch(PDO::FETCH_NUM)[1] === $originalGuideSchema, 'Migration preserves existing guides schema');
    ensure($pdo->query('SHOW CREATE TABLE stock_detalle')->fetch(PDO::FETCH_NUM)[1] === $originalStockSchema, 'Migration preserves existing stock schema');
    foreach (logisticsTables() as $table) {
        $statement = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        ensure($statement->fetchColumn() === 'InnoDB', 'Transactional storage engine for ' . $table);
    }
    $snapshot = new TestDatabase();
    try {
        $snapshot->load('database/schemas/002_schema_v2.sql');
        $snapshot->load('database/schemas/006_logistics_persistence.sql');
        $snapshot->load('database/schemas/007_logistics_incidents.sql');
        foreach (logisticsTables() as $table) ensure(
            $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1] === $snapshot->pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM)[1],
            'Migration and snapshot produce the same ' . $table);
    } finally { $snapshot->close(); }
    $db->load('database/migrations/rollback/007_logistics_incidents_down.sql');
    $db->load('database/migrations/rollback/006_logistics_persistence_down.sql');
    ensure($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) === $originalTables, 'Rollback removes only logistics tables');
    $db->load('database/migrations/006_logistics_persistence.sql');
    $db->load('database/migrations/007_logistics_incidents.sql');
    logisticsFixtures($pdo);
    $repository = new LogisticsRepository($pdo);

    $created = $repository->createPreparation(logisticsHeader(), logisticsLines(), logisticsContext('initial'));
    $prepId = (int) $created['id'];
    ensure($created['estado'] === 'EN_PREPARACION' && (int) $created['version'] === 1, 'Preparation initial state/version');
    $prep = $repository->findPreparation($prepId, 1);
    ensure(count($prep['details']) === 2 && $prep['details'][0]['cantidad'] === '2.500', 'Preparation lines retain exact decimals');
    ensure((int) $prep['created_by'] === 1 && (int) $prep['almacen_id'] === 1, 'Preparation actor and warehouse persisted');
    $equivalent = array_reverse(logisticsLines());
    $equivalent[1]['cantidad'] = '0002.5';
    ensure($repository->createPreparation(logisticsHeader(), $equivalent, logisticsContext('initial')) === $created, 'Reordered canonical payload replays original result');
    logisticsRejects(fn() => $repository->createPreparation([...logisticsHeader(), 'destino' => 'Different'], logisticsLines(), logisticsContext('initial')), 'IDEMPOTENCY_CONFLICT');
    logisticsRejects(fn() => $repository->createPreparation(logisticsHeader(), logisticsLines(), logisticsContext('initial', 1, 2)), 'IDEMPOTENCY_CONFLICT');

    $branchTwo = $repository->createPreparation(logisticsHeader('branch-2', 2), logisticsLines(), logisticsContext('initial', 2));
    ensure((int) $branchTwo['id'] !== $prepId, 'Idempotency key is scoped to branch');
    ensure($repository->findPreparation($prepId, 2) === null && $repository->findPreparation(999999, 1) === null, 'Preparation reads are scoped and missing returns null');
    ensure(count($repository->listPreparations(2)) === 1 && count($repository->listPreparations(1, ['origen_ref' => 'sale-001'])) === 1, 'Listings filter within branch');
    ensure($repository->history('preparacion', $prepId, 2) === [], 'History cannot cross branch');
    logisticsRejects(fn() => $repository->transitionPreparation($prepId, 1, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext('wrong-scope', 2)), 'NOT_FOUND');
    logisticsRejects(fn() => $repository->listPreparations(1, ['estado; DROP TABLE usuarios' => 'x']), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->listPreparations(1, [], 201), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->listPreparations(1, [], 10, -1), 'INVALID_INPUT');

    // Invalid inputs and relational mismatches must not leave an operation row behind.
    $before = logisticsCounts($pdo);
    foreach ([2.5, '1.0001', '0', '-1', '100000000000'] as $index => $quantity) {
        $bad = logisticsLines(); $bad[0]['cantidad'] = $quantity;
        logisticsRejects(fn() => $repository->createPreparation(logisticsHeader('bad-q'), $bad, logisticsContext('bad-q-' . $index)), 'INVALID_INPUT');
    }
    logisticsRejects(fn() => $repository->createPreparation(logisticsHeader('bad-empty'), [], logisticsContext('bad-empty')), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->createPreparation(logisticsHeader('bad-actor'), logisticsLines(), logisticsContext('bad-actor', 1, 0)), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->createPreparation([...logisticsHeader(), 'fecha' => '2026-02-30'], logisticsLines(), logisticsContext('bad-date')), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->createPreparation([...logisticsHeader(), 'reserva_ref' => ''], logisticsLines(), logisticsContext('missing-reservation')), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->createPreparation([...logisticsHeader(), 'estado' => 'PREPARADA'], logisticsLines(), logisticsContext('state-injection')), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->createPreparation([...logisticsHeader(), 'almacen_id' => 3], logisticsLines(), logisticsContext('bad-warehouse')), 'REFERENCE_CONFLICT');
    $badLot = logisticsLines(); $badLot[0]['lote_id'] = 2;
    logisticsRejects(fn() => $repository->createPreparation(logisticsHeader(), $badLot, logisticsContext('bad-lot')), 'REFERENCE_CONFLICT');
    logisticsRejects(fn() => $repository->createPreparation(logisticsHeader(), [logisticsLines()[0], logisticsLines()[0]], logisticsContext('duplicate-line')), 'INVALID_INPUT');
    $badSecondLine = logisticsLines(); $badSecondLine[1]['producto_id'] = 999999;
    logisticsRejects(fn() => $repository->createPreparation(logisticsHeader(), $badSecondLine, logisticsContext('fk-failure')));
    ensure(logisticsCounts($pdo) === $before, 'Failures leave no partial header/detail/history/idempotency record');
    ensure(!$pdo->inTransaction(), 'Repository releases its owned failed transaction');
    // Fault injection is confined to this generated test schema; no production trigger or schema change.
    $pdo->exec('ALTER TABLE logistica_historial ADD CONSTRAINT ck_log_test_history_failure CHECK (actor_id <> 2)');
    try {
        $beforeLateFailure = logisticsCounts($pdo);
        logisticsRejects(fn() => $repository->createPreparation(logisticsHeader('late-failure'), logisticsLines(), logisticsContext('late-failure', 1, 2)));
        ensure(logisticsCounts($pdo) === $beforeLateFailure, 'History failure rolls back previously inserted aggregate and idempotency operation');
        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT INTO auditoria_acciones(usuario_id,modulo,accion) VALUES(1,'test','late-history-caller')");
            logisticsRejects(fn() => $repository->createPreparation(logisticsHeader('late-nested'), logisticsLines(), logisticsContext('late-nested', 1, 2)));
            ensure($pdo->inTransaction() && logisticsCounts($pdo) === $beforeLateFailure, 'Nested history failure leaves caller transaction active and no partial logistics');
            ensure((int) $pdo->query("SELECT COUNT(*) FROM auditoria_acciones WHERE accion='late-history-caller'")->fetchColumn() === 1, 'Caller write survives late history failure');
        } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    } finally {
        $dropSyntax = str_contains((string) $pdo->query('SELECT VERSION()')->fetchColumn(), 'MariaDB') ? 'DROP CONSTRAINT' : 'DROP CHECK';
        $pdo->exec('ALTER TABLE logistica_historial ' . $dropSyntax . ' ck_log_test_history_failure');
    }
    rejects(fn() => $pdo->exec('UPDATE logistica_preparacion_detalle SET cantidad=0 WHERE preparacion_id=' . $prepId), PDOException::class);
    rejects(fn() => $pdo->exec('UPDATE logistica_preparaciones SET version=0 WHERE id=' . $prepId), PDOException::class);
    rejects(fn() => $pdo->exec("UPDATE logistica_preparaciones SET estado='UNKNOWN' WHERE id=$prepId"), PDOException::class);
    $stmt = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logistica_preparacion_detalle' AND COLUMN_NAME='cantidad'");
    ensure(strtolower((string) $stmt->fetchColumn()) === 'decimal(14,3)', 'Persistence uses documented decimal precision');
    $split = [[...logisticsLines()[0], 'lote_id' => null, 'cantidad' => '1'], [...logisticsLines()[0], 'cantidad' => '1.5']];
    $splitResult = $repository->createPreparation(logisticsHeader('split-lot'), $split, logisticsContext('split-lot'));
    ensure(count($repository->findPreparation((int) $splitResult['id'], 1)['details']) === 2, 'One external line can split into distinct lot identities');
    $transfer = $repository->createPreparation([...logisticsHeader('transfer'), 'tipo_origen' => 'TRASLADO', 'cliente_id' => null, 'almacen_destino_id' => 3], logisticsLines(), logisticsContext('transfer'));
    ensure((int) $repository->findPreparation((int) $transfer['id'], 1)['almacen_destino_id'] === 3, 'Transfer stores destination warehouse without creating inventory');

    // Updates keep the original idempotency response while current versions advance.
    $updatedLines = logisticsLines(); $updatedLines[0]['cantidad'] = '3.125';
    $updated = $repository->updatePreparation($prepId, 1, logisticsHeader(), $updatedLines, logisticsContext('edit'));
    ensure((int) $updated['version'] === 2 && $repository->findPreparation($prepId, 1)['details'][0]['cantidad'] === '3.125', 'Update replaces preparation details atomically');
    ensure($repository->createPreparation(logisticsHeader(), logisticsLines(), logisticsContext('initial')) === $created, 'Original create retry returns original response after edit');
    $before = logisticsCounts($pdo); $current = $repository->findPreparation($prepId, 1);
    logisticsRejects(fn() => $repository->updatePreparation($prepId, 1, logisticsHeader(), logisticsLines(), logisticsContext('stale-edit')), 'CONFLICT');
    logisticsRejects(fn() => $repository->updatePreparation($prepId, 2, logisticsHeader(), $badSecondLine, logisticsContext('failed-edit')));
    ensure($repository->findPreparation($prepId, 1) === $current && logisticsCounts($pdo) === $before, 'Stale or invalid edit preserves aggregate and history');
    $prepared = $repository->transitionPreparation($prepId, 2, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext('ready'));
    logisticsRejects(fn() => $repository->updatePreparation($prepId, 3, logisticsHeader(), logisticsLines(), logisticsContext('edit-ready')), 'INVALID_STATE');
    ensure(count($repository->history('preparacion', $prepId, 1)) >= 3, 'Preparation changes have history');

    // Existing guides are linked, not copied into a new guide CRUD.
    $pending = $repository->createDispatch($prepId, ['fecha' => '2026-10-05', 'guia_id' => null, 'guia_version' => null], logisticsContext('pending'));
    $dispatchId = (int) $pending['id'];
    $dispatch = $repository->findDispatch($dispatchId, 1);
    ensure($pending['estado'] === 'PENDIENTE' && count($dispatch['details']) === 2, 'Pending dispatch freezes preparation lines');
    ensure($dispatch['details'][0]['cantidad'] === '3.125' && (int) $dispatch['details'][0]['preparacion_detalle_id'] === (int) $repository->findPreparation($prepId, 1)['details'][0]['id'], 'Copied dispatch retains preparation line identity');
    logisticsRejects(fn() => $repository->createDispatch($prepId, ['fecha' => '2026-10-05'], logisticsContext('another-dispatch')));
    logisticsRejects(fn() => $repository->transitionDispatch($dispatchId, 1, 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out'], logisticsContext('no-guide')), 'REFERENCE_CONFLICT');
    $foreignGuide = logisticsGuide($pdo, 'foreign', $updatedLines, 2);
    logisticsRejects(fn() => $repository->attachGuide($dispatchId, 1, (int) $foreignGuide['id'], (int) $foreignGuide['version'], logisticsContext('foreign-guide')), 'REFERENCE_CONFLICT');
    $otherClientGuide = logisticsGuide($pdo, 'client', $updatedLines, 1, 2);
    logisticsRejects(fn() => $repository->attachGuide($dispatchId, 1, (int) $otherClientGuide['id'], (int) $otherClientGuide['version'], logisticsContext('client-guide')), 'REFERENCE_CONFLICT');
    $guide = logisticsGuide($pdo, 'linked', $updatedLines);
    logisticsRejects(fn() => $repository->attachGuide($dispatchId, 1, (int) $guide['id'], 1, logisticsContext('old-guide')), 'CONFLICT');
    $attached = $repository->attachGuide($dispatchId, 1, (int) $guide['id'], (int) $guide['version'], logisticsContext('attach'));
    ensure((int) $attached['version'] === 2 && (int) $repository->findDispatch($dispatchId, 1)['guia_id'] === (int) $guide['id'], 'Validated existing guide attached with version');
    $secondPrep = $repository->createPreparation(logisticsHeader('guide-duplicate'), $updatedLines, logisticsContext('guide-duplicate-prep'));
    $repository->transitionPreparation((int) $secondPrep['id'], 1, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext('guide-duplicate-ready'));
    logisticsRejects(fn() => $repository->createDispatch((int) $secondPrep['id'], ['fecha' => '2026-10-05', 'guia_id' => $guide['id'], 'guia_version' => $guide['version']], logisticsContext('duplicate-guide')));
    logisticsRejects(fn() => $repository->transitionDispatch($dispatchId, 2, 'PENDIENTE', 'DESPACHADO', [], logisticsContext('no-movement')), 'REFERENCE_CONFLICT');
    $out = $repository->transitionDispatch($dispatchId, 2, 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out-linked'], logisticsContext('out'));
    $dispatch = $repository->findDispatch($dispatchId, 1);
    ensure($dispatch['guia_snapshot'] !== null && $dispatch['movimiento_salida_ref'] === 'out-linked', 'Departure records document snapshot and inventory reference');
    $documentSnapshot = is_string($dispatch['guia_snapshot']) ? json_decode($dispatch['guia_snapshot'], true, 512, JSON_THROW_ON_ERROR) : $dispatch['guia_snapshot'];
    ensure((int) $documentSnapshot['header']['id'] === (int) $guide['id'] && (int) $documentSnapshot['header']['version'] === (int) $guide['version'], 'Guide snapshot retains exact document and version used at departure');
    ensure(count($documentSnapshot['details']) === 2 && $documentSnapshot['details'][0]['cantidad'] === '3.125', 'Guide snapshot retains exact documentary quantities');
    (new GuideRepository($pdo))->markPublished((int) $guide['id'], (int) $guide['version'], 2);
    ensure($repository->findDispatch($dispatchId, 1)['guia_snapshot'] === $dispatch['guia_snapshot'], 'Later documentary publication does not rewrite departure snapshot');
    $transit = $repository->transitionDispatch($dispatchId, (int) $out['version'], 'DESPACHADO', 'EN_TRANSITO', [], logisticsContext('transit'));
    ensure($repository->transitionDispatch($dispatchId, 2, 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out-linked'], logisticsContext('out')) === $out, 'Departure retry remains idempotent after transit');
    logisticsRejects(fn() => $repository->transitionDispatch($dispatchId, (int) $transit['version'], 'EN_TRANSITO', 'ENTREGADO', [], logisticsContext('premature-complete')), 'INVALID_STATE');

    // Matching guide association is insufficient: its product/unit totals must match departure.
    $mismatchPrep = $repository->createPreparation(logisticsHeader('mismatch'), logisticsLines(), logisticsContext('mismatch-prep'));
    $repository->transitionPreparation((int) $mismatchPrep['id'], 1, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext('mismatch-ready'));
    $mismatchLines = logisticsLines(); $mismatchLines[0]['cantidad'] = '2.499';
    $mismatchGuide = logisticsGuide($pdo, 'mismatch', $mismatchLines);
    $mismatchDispatch = $repository->createDispatch((int) $mismatchPrep['id'], ['fecha' => '2026-10-05', 'guia_id' => $mismatchGuide['id'], 'guia_version' => $mismatchGuide['version']], logisticsContext('mismatch-dispatch'));
    $before = logisticsCounts($pdo);
    logisticsRejects(fn() => $repository->transitionDispatch((int) $mismatchDispatch['id'], 1, 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out-mismatch'], logisticsContext('mismatch-out')), 'REFERENCE_CONFLICT');
    ensure(logisticsCounts($pdo) === $before && $repository->findDispatch((int) $mismatchDispatch['id'], 1)['estado'] === 'PENDIENTE', 'One-thousandth documentary mismatch leaves dispatch unchanged');
    $mismatchUnits = logisticsLines(); $mismatchUnits[0]['unidad_id'] = 2;
    $unitGuide = logisticsGuide($pdo, 'wrong-unit', $mismatchUnits);
    $reattached = $repository->attachGuide((int) $mismatchDispatch['id'], 1, (int) $unitGuide['id'], (int) $unitGuide['version'], logisticsContext('unit-attach'));
    logisticsRejects(fn() => $repository->transitionDispatch((int) $mismatchDispatch['id'], (int) $reattached['version'], 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out-unit'], logisticsContext('unit-out')), 'REFERENCE_CONFLICT');

    // Partial delivery, overdelivery protection, completion and correction without deleting records.
    $ready = logisticsReadyDispatch($pdo, $repository, 'deliveries');
    $id = (int) $ready['dispatch']['id']; $version = (int) $ready['dispatch']['version'];
    $line1 = (int) $ready['details'][0]['id']; $line2 = (int) $ready['details'][1]['id'];
    $partialLines = [['despacho_detalle_id' => $line1, 'cantidad' => '1.000']];
    $partial = $repository->recordDelivery($id, $version, logisticsDeliveryHeader(), $partialLines, 'EN_TRANSITO', logisticsContext('partial'));
    $afterPartial = $repository->findDispatch($id, 1);
    ensure($afterPartial['details'][0]['cantidad_entregada'] === '1.000' && $afterPartial['details'][0]['cantidad_pendiente'] === '1.500', 'Partial delivery preserves exact pending balance');
    ensure($afterPartial['estado'] === 'EN_TRANSITO', 'Partial dispatch remains in transit');
    $before = logisticsCounts($pdo);
    logisticsRejects(fn() => $repository->recordDelivery($id, (int) $partial['despacho_version'], logisticsDeliveryHeader(), [['despacho_detalle_id' => $line1, 'cantidad' => '1.501']], 'EN_TRANSITO', logisticsContext('excess')), 'QUANTITY_EXCEEDED');
    logisticsRejects(fn() => $repository->recordDelivery($id, (int) $partial['despacho_version'], logisticsDeliveryHeader(), [['despacho_detalle_id' => $line1, 'cantidad' => '0.001']], 'ENTREGADO', logisticsContext('wrong-complete')), 'INVALID_STATE');
    logisticsRejects(fn() => $repository->recordDelivery($id, (int) $partial['despacho_version'], logisticsDeliveryHeader(), [['despacho_detalle_id' => (int) $dispatch['details'][0]['id'], 'cantidad' => '1']], 'EN_TRANSITO', logisticsContext('alien-line')), 'REFERENCE_CONFLICT');
    ensure(logisticsCounts($pdo) === $before, 'Invalid delivery does not leave header/lines/history/operation');
    rejects(fn() => $pdo->exec('UPDATE logistica_entrega_detalle SET despacho_detalle_id=' . (int) $dispatch['details'][0]['id'] . ' WHERE entrega_id=' . (int) $partial['id']), PDOException::class, 1452);
    $fullLines = [['despacho_detalle_id' => $line1, 'cantidad' => '1.500'], ['despacho_detalle_id' => $line2, 'cantidad' => '1.125']];
    $complete = $repository->recordDelivery($id, (int) $partial['despacho_version'], logisticsDeliveryHeader('Segundo receptor'), $fullLines, 'ENTREGADO', logisticsContext('complete'));
    ensure($complete['despacho_estado'] === 'ENTREGADO' && $repository->findDispatch($id, 1)['details'][1]['cantidad_pendiente'] === '0.000', 'Dispatch completes only with every line delivered');
    ensure($repository->recordDelivery($id, $version, logisticsDeliveryHeader(), $partialLines, 'EN_TRANSITO', logisticsContext('partial')) === $partial, 'Delivery retry returns original dispatch version after later completion');
    ensure(count($repository->listDeliveries($id, 1)) === 2 && $repository->listDeliveries($id, 2) === [], 'Delivery listing is dispatch and branch scoped');
    ensure($repository->findDelivery((int) $partial['id'], 2) === null && $repository->findDispatch($id, 2) === null, 'Delivery and dispatch reads cannot cross branch');
    logisticsRejects(fn() => $repository->voidDelivery((int) $complete['id'], 1, (int) $complete['despacho_version'], 'EN_TRANSITO', ' ', logisticsContext('blank-void')), 'INVALID_INPUT');
    logisticsRejects(fn() => $repository->voidDelivery((int) $complete['id'], 1, (int) $complete['despacho_version'], 'EN_TRANSITO', 'Error de digitación', logisticsContext('closed-void')), 'INVALID_STATE');
    $correctionReady = logisticsReadyDispatch($pdo, $repository, 'open-correction', [[...logisticsLines()[0], 'cantidad' => '10']]);
    $correctionId = (int) $correctionReady['dispatch']['id'];
    $correctionLines = [['despacho_detalle_id' => (int) $correctionReady['details'][0]['id'], 'cantidad' => '6']];
    $correctionDelivery = $repository->recordDelivery($correctionId, (int) $correctionReady['dispatch']['version'], logisticsDeliveryHeader(), $correctionLines, 'EN_TRANSITO', logisticsContext('correction-source'));
    $voided = $repository->voidDelivery((int) $correctionDelivery['id'], 1, (int) $correctionDelivery['despacho_version'], 'EN_TRANSITO', 'Error de digitación', logisticsContext('void'));
    $delivery = $repository->findDelivery((int) $correctionDelivery['id'], 1);
    ensure((bool) $delivery['anulada'] && count($delivery['details']) === 1 && $delivery['motivo_anulacion'] === 'Error de digitación', 'Voiding open unlinked delivery preserves its details and reason');
    ensure($repository->findDispatch($correctionId, 1)['details'][0]['cantidad_pendiente'] === '10.000', 'Void excludes old quantities from active balance');
    $replacement = $repository->recordDelivery($correctionId, (int) $voided['despacho_version'], logisticsDeliveryHeader('Corregido'), $correctionLines, 'EN_TRANSITO', logisticsContext('replacement'));
    ensure($repository->voidDelivery((int) $correctionDelivery['id'], 1, (int) $correctionDelivery['despacho_version'], 'EN_TRANSITO', 'Error de digitación', logisticsContext('void')) === $voided, 'Void retry returns original result after replacement');
    ensure(count($repository->history('entrega', (int) $correctionDelivery['id'], 1)) >= 2, 'Delivery creation and void are traceable');

    // Caller-owned transactions must survive both success and failure.
    $before = logisticsCounts($pdo);
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO auditoria_acciones(usuario_id,modulo,accion) VALUES(1,'test','logistics-caller')");
        $nested = $repository->createPreparation(logisticsHeader('nested'), logisticsLines(), logisticsContext('nested'));
        ensure($pdo->inTransaction(), 'Successful operation does not commit caller transaction');
        $inside = logisticsCounts($pdo);
        logisticsRejects(fn() => $repository->createPreparation(logisticsHeader('nested-fail'), $badSecondLine, logisticsContext('nested-fail')));
        ensure($pdo->inTransaction() && logisticsCounts($pdo) === $inside, 'Failed operation rolls back only its savepoint');
        ensure((int) $pdo->query("SELECT COUNT(*) FROM auditoria_acciones WHERE accion='logistics-caller'")->fetchColumn() === 1, 'Caller changes survive nested failure');
        $pdo->rollBack();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
    ensure(logisticsCounts($pdo) === $before && $repository->findPreparation((int) $nested['id'], 1) === null, 'Caller rollback removes all logistics changes and keys');
    ensure((int) $pdo->query("SELECT COUNT(*) FROM auditoria_acciones WHERE accion='logistics-caller'")->fetchColumn() === 0, 'Caller rollback also removes caller audit');
    $before = logisticsCounts($pdo);
    rejects(fn() => $repository->transaction(function () use ($repository): void {
        $repository->createPreparation(logisticsHeader('bundle'), logisticsLines(), logisticsContext('bundle'));
        throw new RuntimeException('Simulated failure in coordinated use case');
    }), RuntimeException::class);
    ensure(logisticsCounts($pdo) === $before && !$pdo->inTransaction(), 'Repository transaction reverts entire coordinated callback');

    // Services cancel the pending child and its preparation as one coordinated use case.
    $cancelPrep = $repository->createPreparation(logisticsHeader('cancel-before-out'), logisticsLines(), logisticsContext('cancel-prep-create'));
    $repository->transitionPreparation((int) $cancelPrep['id'], 1, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext('cancel-prep-ready'));
    $cancelDispatch = $repository->createDispatch((int) $cancelPrep['id'], ['fecha' => '2026-10-05'], logisticsContext('cancel-dispatch-create'));
    logisticsRejects(fn() => $repository->transitionPreparation((int) $cancelPrep['id'], 2, 'PREPARADA', 'CANCELADA', 'Pedido cancelado', logisticsContext('cancel-parent-first')), 'CONFLICT');
    $cancelledTogether = $repository->transaction(function () use ($repository, $cancelPrep, $cancelDispatch): array {
        $child = $repository->transitionDispatch((int) $cancelDispatch['id'], 1, 'PENDIENTE', 'CANCELADO', ['motivo' => 'Pedido cancelado antes de salir'], logisticsContext('cancel-child'));
        logisticsRejects(fn() => $repository->transitionPreparation((int) $cancelPrep['id'], 2, 'PREPARADA', 'EN_PREPARACION', '', logisticsContext('reopen-frozen-parent')), 'CONFLICT');
        $parent = $repository->transitionPreparation((int) $cancelPrep['id'], 2, 'PREPARADA', 'CANCELADA', 'Pedido cancelado antes de salir', logisticsContext('cancel-parent'));
        return ['child' => $child, 'parent' => $parent];
    });
    ensure($cancelledTogether['child']['estado'] === 'CANCELADO' && $cancelledTogether['parent']['estado'] === 'CANCELADA', 'Predeparture cancellation can coordinate dispatch and preparation');
    ensure($repository->findDispatch((int) $cancelDispatch['id'], 1)['movimiento_salida_ref'] === null, 'Predeparture cancellation never invents inventory movement');
    logisticsRejects(fn() => $repository->updatePreparation((int) $cancelPrep['id'], 3, logisticsHeader('cancel-before-out'), logisticsLines(), logisticsContext('edit-cancelled-prep')), 'INVALID_STATE');
    logisticsRejects(fn() => $repository->transitionDispatch($id, (int) $complete['despacho_version'], 'ENTREGADO', 'CANCELADO', ['motivo' => 'Late cancellation'], logisticsContext('cancel-physical')), 'INVALID_STATE');

    // Cross-entity state integrity is also checked before departure if database data is inconsistent.
    $blockedPrep = $repository->createPreparation(logisticsHeader('cancelled-origin'), logisticsLines(), logisticsContext('blocked-prep'));
    $repository->transitionPreparation((int) $blockedPrep['id'], 1, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext('blocked-ready'));
    $blockedGuide = logisticsGuide($pdo, 'cancelled-origin', logisticsLines());
    $blockedDispatch = $repository->createDispatch((int) $blockedPrep['id'], ['fecha' => '2026-10-05', 'guia_id' => $blockedGuide['id'], 'guia_version' => $blockedGuide['version']], logisticsContext('blocked-dispatch'));
    $pdo->exec('UPDATE logistica_preparaciones SET estado=\'CANCELADA\' WHERE id=' . (int) $blockedPrep['id']); // Deliberate invalid cross-entity fixture.
    logisticsRejects(fn() => $repository->transitionDispatch((int) $blockedDispatch['id'], 1, 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out-blocked'], logisticsContext('blocked-out')), 'INVALID_STATE');
    ensure($repository->findDispatch((int) $blockedDispatch['id'], 1)['estado'] === 'PENDIENTE', 'Cancelled preparation cannot acquire physical dispatch');

    // Repository timestamps are UTC even when it shares a caller connection in another timezone.
    $callerTimezone = (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn();
    $pdo->exec("SET SESSION time_zone='+03:00'");
    try {
        $utcReady = logisticsReadyDispatch($pdo, $repository, 'utc-case');
        $utcDispatchId = (int) $utcReady['dispatch']['id'];
        $utcDelivery = $repository->recordDelivery($utcDispatchId, (int) $utcReady['dispatch']['version'], logisticsDeliveryHeader(),
            [['despacho_detalle_id' => (int) $utcReady['details'][0]['id'], 'cantidad' => '1']], 'EN_TRANSITO', logisticsContext('utc-delivery'));
        ensure($pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+03:00', 'Repository never changes caller session timezone');
        $operationId = (int) $pdo->query("SELECT id FROM logistica_operaciones WHERE sucursal_id=1 AND clave='utc-delivery'")->fetchColumn();
        $historyId = (int) $pdo->query('SELECT id FROM logistica_historial WHERE operacion_id=' . $operationId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
        foreach ([['logistica_preparaciones', (int) $utcReady['preparation']['id']], ['logistica_despachos', $utcDispatchId],
            ['logistica_entregas', (int) $utcDelivery['id']], ['logistica_operaciones', $operationId], ['logistica_historial', $historyId]] as [$table, $rowId]) {
            ensure((int) $pdo->query("SELECT ABS(TIMESTAMPDIFF(SECOND,created_at,UTC_TIMESTAMP(6))) FROM $table WHERE id=$rowId")->fetchColumn() < 10,
                'UTC creation timestamp in ' . $table . ' despite caller timezone');
        }
        foreach ([['logistica_preparaciones', (int) $utcReady['preparation']['id']], ['logistica_despachos', $utcDispatchId]] as [$table, $rowId]) {
            ensure((int) $pdo->query("SELECT ABS(TIMESTAMPDIFF(SECOND,updated_at,UTC_TIMESTAMP(6))) FROM $table WHERE id=$rowId")->fetchColumn() < 10,
                'UTC update timestamp in ' . $table);
            ensure((int) $pdo->query("SELECT updated_at >= created_at FROM $table WHERE id=$rowId")->fetchColumn() === 1, 'Creation and update timestamp ordering in ' . $table);
        }
    } finally { $pdo->exec('SET SESSION time_zone=' . $pdo->quote($callerTimezone)); }

    // A caller's old REPEATABLE READ snapshot must not hide a delivery committed on another connection.
    $snapshotReady = logisticsReadyDispatch($pdo, $repository, 'old-snapshot', [[...logisticsLines()[0], 'cantidad' => '10.000']]);
    $snapshotId = (int) $snapshotReady['dispatch']['id']; $snapshotVersion = (int) $snapshotReady['dispatch']['version'];
    $snapshotLine = (int) $snapshotReady['details'][0]['id'];
    $connectionB = logisticsIndependentConnection($pdo);
    $connectionB->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $repositoryB = new LogisticsRepository($connectionB);
    $connectionB->beginTransaction();
    try {
        $connectionB->query('SELECT COUNT(*) FROM logistica_entregas')->fetchColumn(); // Establish the older read view.
        $committed = $repository->recordDelivery($snapshotId, $snapshotVersion, logisticsDeliveryHeader(),
            [['despacho_detalle_id' => $snapshotLine, 'cantidad' => '6']], 'EN_TRANSITO', logisticsContext('snapshot-first'));
        $currentFromB = $repositoryB->findDispatch($snapshotId, 1);
        ensure((int) $currentFromB['version'] === (int) $committed['despacho_version'] && $currentFromB['details'][0]['cantidad_entregada'] === '6.000'
            && $currentFromB['details'][0]['cantidad_pendiente'] === '4.000', 'Current header and quantities stay coherent inside caller older snapshot');
        $listedFromB = $repositoryB->listDeliveries($snapshotId, 1);
        ensure(count($listedFromB) === 1 && (int) $listedFromB[0]['id'] === (int) $committed['id'], 'Delivery listing observes current IDs inside older caller snapshot');
        logisticsRejects(fn() => $repositoryB->recordDelivery($snapshotId, (int) $committed['despacho_version'], logisticsDeliveryHeader(),
            [['despacho_detalle_id' => $snapshotLine, 'cantidad' => '6']], 'EN_TRANSITO', logisticsContext('snapshot-excess')), 'QUANTITY_EXCEEDED');
        ensure($connectionB->inTransaction(), 'Snapshot overdelivery rejection preserves caller transaction');
        $validInCaller = $repositoryB->recordDelivery($snapshotId, (int) $committed['despacho_version'], logisticsDeliveryHeader(),
            [['despacho_detalle_id' => $snapshotLine, 'cantidad' => '4']], 'ENTREGADO', logisticsContext('snapshot-rest'));
        ensure($validInCaller['despacho_estado'] === 'ENTREGADO', 'Current remaining quantity can be delivered within older caller transaction');
    } finally { if ($connectionB->inTransaction()) $connectionB->rollBack(); }
    ensure($repository->findDispatch($snapshotId, 1)['details'][0]['cantidad_entregada'] === '6.000', 'Rolling back second connection preserves only first committed delivery');

    // Real independent PDO connections: duplicate submit and competing partial deliveries.
    $race = logisticsRace($pdo, 'idem');
    ensure(isset($race[0]['ok'], $race[1]['ok']) && $race[0]['ok'] === $race[1]['ok'], 'Concurrent identical commands return the same original result: ' . json_encode($race));
    ensure((int) $pdo->query("SELECT COUNT(*) FROM logistica_operaciones WHERE clave='race-idem'")->fetchColumn() === 1, 'Concurrent duplicate has one operation');
    $originalRaceResult = $race[0]['ok'];
    $replays = logisticsRace($pdo, 'idem', 0, 0, 0, 3);
    ensure(count(array_filter($replays, static fn(array $r): bool => ($r['ok'] ?? null) === $originalRaceResult)) === 3,
        'Three simultaneous replays of existing key return original result without shared-lock upgrade deadlock: ' . json_encode($replays));
    $raceReady = logisticsReadyDispatch($pdo, $repository, 'race-delivery', [[...logisticsLines()[0], 'cantidad' => '10.000']]);
    $raceId = (int) $raceReady['dispatch']['id']; $raceVersion = (int) $raceReady['dispatch']['version']; $raceLine = (int) $raceReady['details'][0]['id'];
    $race = logisticsRace($pdo, 'delivery', $raceId, $raceVersion, $raceLine);
    ensure(count(array_filter($race, static fn(array $r): bool => isset($r['ok']))) === 1, 'Only one concurrent delivery with expected version succeeds: ' . json_encode($race));
    ensure(count(array_filter($race, static fn(array $r): bool => ($r['reason'] ?? '') === 'CONFLICT')) === 1, 'Other concurrent delivery has stable version conflict');
    $balance = $repository->findDispatch($raceId, 1);
    ensure($balance['details'][0]['cantidad_entregada'] === '6.000' && $balance['details'][0]['cantidad_pendiente'] === '4.000', 'Concurrent delivery cannot exceed available balance');
    logisticsRejects(fn() => $repository->recordDelivery($raceId, (int) $balance['version'], logisticsDeliveryHeader(), [['despacho_detalle_id' => $raceLine, 'cantidad' => '6']], 'EN_TRANSITO', logisticsContext('race-retry-excess')), 'QUANTITY_EXCEEDED');

    // Distinct commands compete to void the same delivery: one succeeds, one conflicts.
    $voidReady = logisticsReadyDispatch($pdo, $repository, 'race-void', [[...logisticsLines()[0], 'cantidad' => '10.000']]);
    $voidDispatchId = (int) $voidReady['dispatch']['id'];
    $voidSource = $repository->recordDelivery($voidDispatchId, (int) $voidReady['dispatch']['version'], logisticsDeliveryHeader(),
        [['despacho_detalle_id' => (int) $voidReady['details'][0]['id'], 'cantidad' => '6']], 'EN_TRANSITO', logisticsContext('race-void-source'));
    $beforeVoids = logisticsCounts($pdo);
    $voidRace = logisticsRace($pdo, 'void', (int) $voidSource['id'], (int) $voidSource['despacho_version'], 1);
    ensure(count(array_filter($voidRace, static fn(array $r): bool => isset($r['ok']))) === 1,
        'Exactly one simultaneous void succeeds: ' . json_encode($voidRace));
    ensure(count(array_filter($voidRace, static fn(array $r): bool => ($r['reason'] ?? '') === 'CONFLICT')) === 1,
        'Other simultaneous void returns stable conflict, including deadlock recovery');
    $voidBalance = $repository->findDispatch($voidDispatchId, 1);
    $voidDelivery = $repository->findDelivery((int) $voidSource['id'], 1);
    ensure($voidBalance['details'][0]['cantidad_entregada'] === '0.000' && $voidBalance['details'][0]['cantidad_pendiente'] === '10.000', 'Competing voids subtract the delivery only once');
    ensure($voidDelivery['anulada'] === true && (int) $voidDelivery['version'] === 2 && count($voidDelivery['details']) === 1, 'Competing voids preserve delivery and advance its version once');
    ensure((int) $voidBalance['version'] === (int) $voidSource['despacho_version'] + 1, 'Competing voids advance dispatch version once');
    ensure((int) $pdo->query("SELECT COUNT(*) FROM logistica_historial WHERE accion='VOID_DELIVERY' AND entrega_id=" . (int) $voidSource['id'])->fetchColumn() === 1, 'Competing voids persist one void event');
    ensure((int) $pdo->query("SELECT COUNT(*) FROM logistica_operaciones WHERE clave IN ('race-void-0','race-void-1') AND result_json IS NOT NULL")->fetchColumn() === 1, 'Competing voids commit one command result');
    $afterVoids = logisticsCounts($pdo);
    foreach (logisticsTables() as $table) {
        $increment = in_array($table, ['logistica_historial', 'logistica_operaciones'], true) ? 1 : 0;
        ensure($afterVoids[$table] === $beforeVoids[$table] + $increment, 'Losing void leaves no partial rows in ' . $table);
    }
    ensure((int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 2 && (int) $pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn() === 0, 'Logistics never creates products or stock snapshots');
    ensure((int) $pdo->query('SELECT COUNT(*) FROM logistica_operaciones WHERE result_json IS NULL')->fetchColumn() === 0, 'No incomplete operation is committed');
    $mastersBeforeDown = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
    $guidesBeforeDown = (int) $pdo->query('SELECT COUNT(*) FROM guias_cabecera')->fetchColumn();
    $countsBeforeUnsafeDown = logisticsCounts($pdo);
    rejects(fn() => $db->load('database/migrations/rollback/007_logistics_incidents_down.sql'), PDOException::class);
    ensure(logisticsCounts($pdo) === $countsBeforeUnsafeDown && (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=7')->fetchColumn() === 1,
        'Unsafe downgrade with new delivery audit payload is blocked without losing logistics facts');
    ensure((int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn() === $mastersBeforeDown && (int) $pdo->query('SELECT COUNT(*) FROM guias_cabecera')->fetchColumn() === $guidesBeforeDown, 'Guarded rollback preserves existing masters and guide records');
    echo 'Logistics persistence: ' . $GLOBALS['checks'] . " checks OK (migration, rollback, isolation and concurrent workers)\n";
} finally { $db->close(); }

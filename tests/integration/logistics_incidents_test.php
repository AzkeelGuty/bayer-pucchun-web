<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/logistics_fixtures.php';

use App\Contracts\LogisticsInventoryConfirmationProviderInterface;
use App\Exceptions\LogisticsPersistenceException;
use App\Repositories\LogisticsRepository;
use App\Support\LogisticsQuantity;

/** Test-only adapter. Facts come from this fixture registry, never from command payloads. */
final class IncidentTestInventory implements LogisticsInventoryConfirmationProviderInterface
{
    private array $facts = [];
    public array $lookups = [];

    public function register(string $id, array $fact): array
    {
        $reference = ['sistema' => 'INVENTARIOS-QA', 'id' => $id];
        $this->facts[$reference['sistema'] . ':' . $id] = [...$fact, ...$reference];
        return $reference;
    }

    public function confirmedFact(array $reference): array
    {
        $key = ($reference['sistema'] ?? '') . ':' . ($reference['id'] ?? '');
        $this->lookups[] = $key;
        return $this->facts[$key] ?? [];
    }
}

function incidentTestReady(PDO $pdo, LogisticsRepository $repository, string $label, string $quantity = '100.000'): array
{
    return logisticsReadyDispatch($pdo, $repository, $label, [[...logisticsLines()[0], 'cantidad' => $quantity]]);
}

function incidentTestVersion(LogisticsRepository $repository, int $dispatchId): int
{
    return (int) $repository->findDispatch($dispatchId, 1)['version'];
}

function incidentTestFact(array $dispatch, array $incident, string $quantity, string $type = 'RETORNO_CONFIRMADO'): array
{
    $line = $dispatch['details'][0];
    return ['confirmado' => true, 'autorizado' => true, 'tipo' => $type,
        'direccion' => $type === 'RETORNO_CONFIRMADO' ? 'ENTRADA' : 'SIN_NUEVO_EGRESO',
        'finalidad' => match ($type) { 'RETORNO_CONFIRMADO' => 'RETORNO', 'PERDIDA_CONFIRMADA' => 'PERDIDA', default => 'DISPOSICION_FINAL' },
        'sucursal_id' => 1, 'despacho_id' => (int) $dispatch['id'], 'incidencia_id' => (int) $incident['id'],
        'despacho_detalle_id' => (int) $line['id'], 'almacen_id' => 2, 'producto_id' => (int) $line['producto_id'],
        'unidad_id' => (int) $line['unidad_id'], 'lote_id' => $line['lote_id'] === null ? null : (int) $line['lote_id'],
        'cantidad' => $quantity, 'autorizacion_ref' => 'approval-fixture', 'evidencia' => ['documento' => 'signed-fixture']];
}

function incidentTestResolution(string $type, string $quantity, array $reference): array
{
    return ['tipo' => $type, 'cantidad' => $quantity, 'motivo' => 'Hecho verificado en escenario de prueba',
        'evidencia' => ['documento' => 'fixture-resolution'], 'confirmacion' => $reference];
}

function incidentTestCounts(PDO $pdo): array
{
    $counts = [];
    foreach (['logistica_preparaciones', 'logistica_preparacion_detalle', 'logistica_despachos', 'logistica_despacho_detalle',
        'logistica_entregas', 'logistica_entrega_detalle', 'logistica_incidencias', 'logistica_incidencia_resoluciones',
        'logistica_historial', 'logistica_operaciones'] as $table) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    return $counts;
}

function incidentTestBalance(LogisticsRepository $repository, int $dispatchId, string $accepted, string $returned,
    string $final, string $pending, string $assigned): array
{
    $dispatch = $repository->findDispatch($dispatchId, 1);
    $line = $dispatch['details'][0];
    foreach (['cantidad_entregada' => $accepted, 'cantidad_retornada' => $returned, 'cantidad_final' => $final,
        'cantidad_pendiente' => $pending, 'cantidad_asignada' => $assigned] as $field => $quantity) {
        ensure($line[$field] === LogisticsQuantity::normalize($quantity, true), $field . ' = ' . $quantity . ' in dispatch ' . $dispatchId);
    }
    $sum = LogisticsQuantity::toMillis($accepted, true) + LogisticsQuantity::toMillis($returned, true)
        + LogisticsQuantity::toMillis($final, true) + LogisticsQuantity::toMillis($pending, true);
    ensure($sum === LogisticsQuantity::toMillis($line['cantidad']), 'Q = A + R + F + P exactly');
    ensure(LogisticsQuantity::toMillis($assigned, true) <= LogisticsQuantity::toMillis($pending, true), 'Assigned pending never exceeds pending quantity');
    return $dispatch;
}

function incidentTestCase(LogisticsRepository $repository, int $dispatchId, int $detailId, string $quantity,
    string $key, string $type = 'FALTANTE'): array
{
    return $repository->createIncident($dispatchId, incidentTestVersion($repository, $dispatchId),
        ['tipo' => $type, 'modo' => 'CUANTITATIVA', 'despacho_detalle_id' => $detailId, 'cantidad' => $quantity,
            'causas' => [$type], 'motivo' => 'Caso operativo de prueba', 'evidencia' => ['registro' => $key]], logisticsContext($key));
}

// Workers only connect to generated disposable schemas; the application .env is never read.
if (($argv[1] ?? '') === '--worker') {
    $database = $argv[2] ?? '';
    if (!preg_match('/^bayer_test_[a-f0-9]{16}$/D', $database)) throw new RuntimeException('Unsafe incident test database');
    $pdo = new PDO('mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('TEST_DB_PORT') ?: '3306')
        . ';dbname=' . $database . ';charset=utf8mb4', getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $inventory = new IncidentTestInventory();
    $repository = new LogisticsRepository($pdo, $inventory);
    echo "READY\n"; flush();
    $config = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
    try {
        if ($config['mode'] === 'return') {
            $reference = $inventory->register('race-return', $config['confirmed_fixture']);
            $result = $repository->resolveIncident($config['incident_id'], $config['incident_version'], $config['dispatch_version'],
                incidentTestResolution('RETORNO_CONFIRMADO', '20.000', $reference), logisticsContext('race-return'));
        } else {
            $result = $repository->recordDelivery($config['dispatch_id'], $config['dispatch_version'], logisticsDeliveryHeader(),
                [['despacho_detalle_id' => $config['detail_id'], 'cantidad' => '20.000', 'cantidad_rechazada' => '0',
                    'aceptaciones_incidencias' => [['incidencia_id' => $config['incident_id'], 'cantidad' => '20.000']]]],
                'ENTREGADO', logisticsContext('race-retry'));
        }
        echo json_encode(['ok' => $result], JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['error' => get_class($error), 'reason' => $error instanceof LogisticsPersistenceException ? $error->reasonCode() : null,
            'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
    exit;
}

function incidentTestRace(PDO $pdo, array $config): array
{
    $workers = [];
    try {
        foreach (['return', 'retry'] as $mode) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot launch incident concurrency worker');
            stream_set_timeout($pipes[1], 30);
            $workers[] = [$process, $pipes, $mode];
        }
        foreach ($workers as [, $pipes]) ensure(trim((string) fgets($pipes[1])) === 'READY', 'Incident race worker ready');
        foreach ($workers as [, $pipes, $mode]) {
            fwrite($pipes[0], json_encode([...$config, 'mode' => $mode], JSON_THROW_ON_ERROR) . "\n"); fflush($pipes[0]);
        }
        $results = [];
        foreach ($workers as [, $pipes]) {
            $line = fgets($pipes[1]);
            ensure(is_string($line), 'Incident race finishes within timeout');
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
    $guideSchema = $pdo->query('SHOW CREATE TABLE guias_cabecera')->fetch(PDO::FETCH_NUM)[1];
    $stockSchema = $pdo->query('SHOW CREATE TABLE stock_detalle')->fetch(PDO::FETCH_NUM)[1];
    $db->load('database/migrations/006_logistics_persistence.sql');
    $beforeTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $db->load('database/migrations/007_logistics_incidents.sql');
    $added = array_values(array_diff($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), $beforeTables));
    sort($added);
    ensure($added === ['logistica_incidencia_resoluciones', 'logistica_incidencias'], 'Evolution adds exactly incidence and resolution tables');
    ensure($pdo->query('SHOW CREATE TABLE guias_cabecera')->fetch(PDO::FETCH_NUM)[1] === $guideSchema, 'Evolution preserves Guide CRUD schema');
    ensure($pdo->query('SHOW CREATE TABLE stock_detalle')->fetch(PDO::FETCH_NUM)[1] === $stockSchema, 'Evolution does not add parallel stock');
    logisticsFixtures($pdo);
    $inventory = new IncidentTestInventory();
    $repository = new LogisticsRepository($pdo, $inventory);

    // 100 dispatched; 80 accepted, 20 rejected. One physical allocation with two causes.
    $ready = incidentTestReady($pdo, $repository, 'partial-100');
    $id = (int) $ready['dispatch']['id']; $lineId = (int) $ready['details'][0]['id'];
    $partialPayload = [['despacho_detalle_id' => $lineId, 'cantidad' => '80.000', 'cantidad_rechazada' => '20.000',
        'motivo_rechazo' => 'Cliente rechaza envases dañados', 'causas_rechazo' => ['RECHAZO', 'DANO']]];
    $before = incidentTestCounts($pdo);
    logisticsRejects(fn() => $repository->recordDelivery($id, incidentTestVersion($repository, $id),
        [...logisticsDeliveryHeader(), 'resultado' => 'ACEPTADA'], $partialPayload, 'EN_RESOLUCION', logisticsContext('misreported-acceptance')), 'INVALID_INPUT');
    ensure(incidentTestCounts($pdo) === $before, 'The attempt result cannot contradict accepted/rejected facts');
    $partial = $repository->recordDelivery($id, incidentTestVersion($repository, $id), logisticsDeliveryHeader(), $partialPayload,
        'EN_RESOLUCION', logisticsContext('partial-100-attempt'));
    ensure($partial['resultado'] === 'PARCIAL', 'Accepted and rejected quantities produce PARCIAL');
    ensure(count($partial['incidencias_ids']) === 1, 'Rejection and damage share one quantity allocation');
    $caseId = (int) $partial['incidencias_ids'][0];
    $case = $repository->findIncident($caseId, 1);
    ensure($case['cantidad'] === '20.000' && $case['saldo'] === '20.000', 'Case tracks 20 rejected units awaiting resolution');
    $causes = is_array($case['causas_json']) ? $case['causas_json'] : json_decode((string) $case['causas_json'], true, 512, JSON_THROW_ON_ERROR);
    ensure(in_array('RECHAZO', $causes, true) && in_array('DANO', $causes, true), 'Both rejection and damage causes are recorded for the same 20 units');
    ensure($repository->findIncident($caseId, 2) === null && $repository->listIncidents($id, 2) === [], 'Case reads do not cross branches');
    $dispatch = incidentTestBalance($repository, $id, '80', '0', '0', '20', '20');
    ensure($dispatch['estado'] === 'EN_RESOLUCION', 'Blocking rejection makes resolution pending visible');
    $counts = incidentTestCounts($pdo);
    logisticsRejects(fn() => incidentTestCase($repository, $id, $lineId, '20', 'double-allocation', 'DANO'), 'QUANTITY_EXCEEDED');
    ensure(incidentTestCounts($pdo) === $counts, 'Discovering damage cannot allocate rejected units twice');
    logisticsRejects(fn() => $repository->recordDelivery($id, incidentTestVersion($repository, $id), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $lineId, 'cantidad' => '1']], 'EN_RESOLUCION', logisticsContext('assigned-as-free')), 'QUANTITY_EXCEEDED');
    logisticsRejects(fn() => $repository->closeDispatch($id, incidentTestVersion($repository, $id), 'EN_RESOLUCION', 'Aún pendiente', logisticsContext('close-pending')), 'INVALID_STATE');
    logisticsRejects(fn() => $repository->transitionDispatch($id, incidentTestVersion($repository, $id), 'EN_RESOLUCION', 'CERRADO_CON_INCIDENCIA',
        ['motivo' => 'Texto sin comprobación', 'resolucion_ref' => 'manual'], logisticsContext('generic-close')), 'INVALID_STATE');

    // Returning 12 only counts after a trusted granular Inventory confirmation.
    $returnRef = $inventory->register('return-12', incidentTestFact($dispatch, $case, '12.000'));
    $returnPayload = incidentTestResolution('RETORNO_CONFIRMADO', '12.000', $returnRef);
    $returned = $repository->resolveIncident($caseId, (int) $case['version'], incidentTestVersion($repository, $id), $returnPayload, logisticsContext('return-12'));
    $dispatch = incidentTestBalance($repository, $id, '80', '12', '0', '8', '8');
    $case = $repository->findIncident($caseId, 1);
    ensure($case['saldo'] === '8.000' && count($case['resoluciones']) === 1, 'Partial return preserves unresolved 8 and one factual resolution');
    ensure($repository->resolveIncident($caseId, 1, (int) $partial['despacho_version'], $returnPayload, logisticsContext('return-12')) === $returned,
        'Same resolution command replays original immutable result after version change');
    $before = incidentTestCounts($pdo);
    logisticsRejects(fn() => $repository->resolveIncident($caseId, (int) $case['version'], incidentTestVersion($repository, $id), $returnPayload, logisticsContext('duplicate-return-other-key')));
    ensure(incidentTestCounts($pdo) === $before, 'Another idempotency key cannot count the same Inventory confirmation again');
    logisticsRejects(fn() => $repository->resolveIncident($caseId, (int) $case['version'], incidentTestVersion($repository, $id),
        incidentTestResolution('RETORNO_CONFIRMADO', '8.001', ['sistema' => 'INVENTARIOS-QA', 'id' => 'unknown']), logisticsContext('over-resolve')), 'QUANTITY_EXCEEDED');
    $lostRef = $inventory->register('lost-8', incidentTestFact($dispatch, $case, '8.000', 'PERDIDA_CONFIRMADA'));
    $repository->resolveIncident($caseId, (int) $case['version'], incidentTestVersion($repository, $id),
        incidentTestResolution('PERDIDA_CONFIRMADA', '8.000', $lostRef), logisticsContext('lost-8'));
    $dispatch = incidentTestBalance($repository, $id, '80', '12', '8', '0', '0');
    ensure($repository->findIncident($caseId, 1)['estado'] === 'RESUELTA', 'All 20 accounted for resolves quantitative case');
    $closed = $repository->closeDispatch($id, (int) $dispatch['version'], $dispatch['estado'], 'Balance y casos comprobados', logisticsContext('safe-close'));
    ensure($closed['estado'] === 'CERRADO_CON_INCIDENCIA', 'Dedicated close permits confirmed return/loss with zero pending');
    $closedDispatch = $repository->findDispatch($id, 1);
    ensure($closedDispatch['cerrado_at'] !== null && (int) $closedDispatch['cerrado_by'] === 1, 'Final close stores actor and timestamp');
    logisticsRejects(fn() => $repository->voidDelivery((int) $partial['id'], 1, (int) $closed['version'], 'EN_TRANSITO', 'Intentar reabrir', logisticsContext('closed-void')), 'INVALID_STATE');

    // The duplicate proof must still fit remaining quantities, otherwise a quantity cap could mask the missing uniqueness guard.
    $dedup = incidentTestReady($pdo, $repository, 'confirmed-fact-dedup');
    $dedupId = (int) $dedup['dispatch']['id'];
    $dedupCase = incidentTestCase($repository, $dedupId, (int) $dedup['details'][0]['id'], '40', 'dedup-case');
    $dedupDispatch = $repository->findDispatch($dedupId, 1);
    $dedupRef = $inventory->register('dedup-return-ten', incidentTestFact($dedupDispatch, $dedupCase, '10'));
    $dedupPayload = incidentTestResolution('RETORNO_CONFIRMADO', '10', $dedupRef);
    $repository->resolveIncident((int) $dedupCase['id'], 1, (int) $dedupDispatch['version'], $dedupPayload, logisticsContext('dedup-first'));
    $before = incidentTestCounts($pdo); $dedupDispatch = $repository->findDispatch($dedupId, 1);
    logisticsRejects(fn() => $repository->resolveIncident((int) $dedupCase['id'], 2, (int) $dedupDispatch['version'],
        $dedupPayload, logisticsContext('dedup-second-with-another-key')));
    ensure(incidentTestCounts($pdo) === $before, 'Granular confirmed fact remains unique even when its quantity fits remaining balance');
    incidentTestBalance($repository, $dedupId, '0', '10', '0', '90', '30');

    // Requests and newly discovered causes are actions; they are not final quantity destinations.
    $actions = incidentTestReady($pdo, $repository, 'case-actions');
    $actionsId = (int) $actions['dispatch']['id'];
    $actionsCase = incidentTestCase($repository, $actionsId, (int) $actions['details'][0]['id'], '20', 'actions-case');
    $actionVersion = incidentTestVersion($repository, $actionsId);
    $causesPayload = ['tipo' => 'AGREGAR_CAUSAS', 'causas' => ['DANO', 'DANO'], 'motivo' => 'También se detectó daño', 'evidencia' => ['foto' => 'fixture-damage']];
    $causesResult = $repository->recordIncidentAction((int) $actionsCase['id'], 1, $actionVersion, $causesPayload, logisticsContext('actions-add-causes'));
    $actionsCase = $repository->findIncident((int) $actionsCase['id'], 1);
    $causes = is_array($actionsCase['causas_json']) ? $actionsCase['causas_json'] : json_decode($actionsCase['causas_json'], true, 512, JSON_THROW_ON_ERROR);
    ensure(count(array_filter($causes, static fn(string $cause): bool => $cause === 'DANO')) === 1, 'Adding the same cause twice retains one cause and one allocation');
    ensure(count($repository->listIncidents($actionsId, 1)) === 1 && $actionsCase['cantidad'] === '20.000', 'A new cause keeps the original case quantity');
    $repository->recordIncidentAction((int) $actionsCase['id'], (int) $actionsCase['version'], incidentTestVersion($repository, $actionsId),
        ['tipo' => 'SOLICITAR_DEVOLUCION', 'motivo' => 'Se coordinó recepción; todavía no se recibió', 'evidencia' => ['solicitud' => 'return-request-fixture']],
        logisticsContext('actions-request-return'));
    ensure($repository->recordIncidentAction((int) $actionsCase['id'], 1, $actionVersion, $causesPayload, logisticsContext('actions-add-causes')) === $causesResult,
        'Cause action replays original response after another action advances both versions');
    incidentTestBalance($repository, $actionsId, '0', '0', '0', '100', '20');
    ensure($repository->findIncident((int) $actionsCase['id'], 1)['resoluciones'] === [], 'Requested return does not masquerade as a confirmed resolution');
    $caseHistory = array_filter($repository->history('despacho', $actionsId, 1), static fn(array $event): bool => (int) ($event['incidencia_id'] ?? 0) === (int) $actionsCase['id']);
    ensure(count($caseHistory) >= 3, 'Case creation, added cause and return request have history');
    foreach ([null, 'DANO', ['causa' => 'DANO']] as $index => $malformedCauses) {
        logisticsRejects(fn() => $repository->createIncident($actionsId, incidentTestVersion($repository, $actionsId),
            ['tipo' => 'DANO', 'modo' => 'CUANTITATIVA', 'despacho_detalle_id' => (int) $actions['details'][0]['id'], 'cantidad' => '1',
                'causas' => $malformedCauses, 'motivo' => 'Forma inválida', 'evidencia' => ['registro' => 'fixture']], logisticsContext('invalid-causes-' . $index)), 'INVALID_INPUT');
    }

    // Rejecting everything is an attempt, not an automatic warehouse entry.
    $total = incidentTestReady($pdo, $repository, 'reject-total');
    $totalId = (int) $total['dispatch']['id']; $totalLine = (int) $total['details'][0]['id'];
    $rejected = $repository->recordDelivery($totalId, incidentTestVersion($repository, $totalId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $totalLine, 'cantidad' => '0', 'cantidad_rechazada' => '100', 'motivo_rechazo' => 'Pedido rechazado']],
        'EN_RESOLUCION', logisticsContext('reject-total-attempt'));
    ensure($rejected['resultado'] === 'RECHAZADA', 'Zero acceptance with presented rejected quantities produces RECHAZADA');
    incidentTestBalance($repository, $totalId, '0', '0', '0', '100', '100');
    logisticsRejects(fn() => $repository->voidDelivery((int) $rejected['id'], 1, (int) $rejected['despacho_version'], 'EN_TRANSITO',
        'Borrar rechazo', logisticsContext('reject-dependent-void')), 'REFERENCE_CONFLICT');

    // Absence permits a genuine zero-line attempt with no fictitious movement.
    $absence = incidentTestReady($pdo, $repository, 'absence');
    $absenceId = (int) $absence['dispatch']['id'];
    $failedHeader = [...logisticsDeliveryHeader(), 'recibido_por' => null, 'resultado' => 'FALLIDA', 'tipo_fallo' => 'AUSENCIA_RECEPTOR', 'motivo_fallo' => 'No había receptor',
        'observaciones' => 'Visita comprobada por registro del transportista'];
    $failed = $repository->recordDelivery($absenceId, incidentTestVersion($repository, $absenceId), $failedHeader, [],
        'EN_RESOLUCION', logisticsContext('absence-attempt'));
    ensure($failed['resultado'] === 'FALLIDA' && $repository->findDelivery((int) $failed['id'], 1)['details'] === [], 'FALLIDA persists without quantity lines');
    ensure($repository->findDelivery((int) $failed['id'], 1)['recibido_por'] === null, 'Absent receiver is not invented');
    incidentTestBalance($repository, $absenceId, '0', '0', '0', '100', '0');
    $absenceCase = $repository->findIncident((int) $failed['incidencias_ids'][0], 1);
    $repository->resolveIncident((int) $absenceCase['id'], (int) $absenceCase['version'], incidentTestVersion($repository, $absenceId),
        ['tipo' => 'RESOLUCION_OPERATIVA', 'motivo' => 'Nueva visita coordinada', 'evidencia' => ['agenda' => '2026-10-06']], logisticsContext('absence-reschedule'));
    logisticsRejects(fn() => $repository->closeDispatch($absenceId, incidentTestVersion($repository, $absenceId),
        $repository->findDispatch($absenceId, 1)['estado'], 'Reprogramado', logisticsContext('absence-close')), 'INVALID_STATE');
    logisticsRejects(fn() => $repository->recordDelivery($absenceId, incidentTestVersion($repository, $absenceId),
        [...$failedHeader, 'motivo_fallo' => ''], [], 'EN_RESOLUCION', logisticsContext('absence-no-reason')), 'INVALID_INPUT');
    $withoutFailureType = $failedHeader;
    unset($withoutFailureType['tipo_fallo']);
    logisticsRejects(fn() => $repository->recordDelivery($absenceId, incidentTestVersion($repository, $absenceId),
        $withoutFailureType, [], 'EN_RESOLUCION', logisticsContext('failed-without-type')), 'INVALID_INPUT');

    // A documentary delivery failure must retain its cause instead of becoming an invented absence.
    $documentFailed = incidentTestReady($pdo, $repository, 'failed-documentary');
    $documentFailedId = (int) $documentFailed['dispatch']['id'];
    $documentFailedResult = $repository->recordDelivery($documentFailedId, incidentTestVersion($repository, $documentFailedId),
        [...$failedHeader, 'tipo_fallo' => 'PROBLEMA_DOCUMENTAL', 'motivo_fallo' => 'Documento requerido no disponible',
            'observaciones' => 'Recepción impedida por documento faltante'], [], 'EN_RESOLUCION', logisticsContext('failed-documentary-attempt'));
    ensure($documentFailedResult['resultado'] === 'FALLIDA' && count($documentFailedResult['incidencias_ids']) === 1, 'Documentary failure produces one typed case');
    $documentFailedCase = $repository->findIncident((int) $documentFailedResult['incidencias_ids'][0], 1);
    ensure($documentFailedCase['tipo'] === 'PROBLEMA_DOCUMENTAL', 'A documentary failure keeps the documentary cause');
    $failureCauses = is_array($documentFailedCase['causas_json']) ? $documentFailedCase['causas_json'] : json_decode($documentFailedCase['causas_json'], true, 512, JSON_THROW_ON_ERROR);
    ensure($failureCauses === ['PROBLEMA_DOCUMENTAL'], 'Documentary failure does not acquire a false absent-receiver cause');
    ensure($documentFailedCase['cantidad'] === null && $repository->findDelivery((int) $documentFailedResult['id'], 1)['details'] === [],
        'Documentary failure invents no quantity allocation or zero-quantity line');
    incidentTestBalance($repository, $documentFailedId, '0', '0', '0', '100', '0');

    // The same 20 can be rejected again, then accepted partially, without a new allocation.
    $retry = incidentTestReady($pdo, $repository, 'retry');
    $retryId = (int) $retry['dispatch']['id']; $retryLine = (int) $retry['details'][0]['id'];
    $first = $repository->recordDelivery($retryId, incidentTestVersion($repository, $retryId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $retryLine, 'cantidad' => '80', 'cantidad_rechazada' => '20', 'motivo_rechazo' => 'Rechazo inicial']],
        'EN_RESOLUCION', logisticsContext('retry-first'));
    $retryCaseId = (int) $first['incidencias_ids'][0];
    $caseCount = count($repository->listIncidents($retryId, 1));
    $repository->recordDelivery($retryId, incidentTestVersion($repository, $retryId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $retryLine, 'cantidad' => '0', 'cantidad_rechazada' => '20', 'motivo_rechazo' => 'Rechazo repetido',
            'rechazos_incidencias' => [['incidencia_id' => $retryCaseId, 'cantidad' => '20']]]],
        'EN_RESOLUCION', logisticsContext('retry-rejected-again'));
    ensure(count($repository->listIncidents($retryId, 1)) === $caseCount, 'Repeated rejection records an attempt against the same case');
    incidentTestBalance($repository, $retryId, '80', '0', '0', '20', '20');
    $acceptedTwelve = $repository->recordDelivery($retryId, incidentTestVersion($repository, $retryId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $retryLine, 'cantidad' => '12', 'aceptaciones_incidencias' => [['incidencia_id' => $retryCaseId, 'cantidad' => '12']]]],
        'EN_RESOLUCION', logisticsContext('retry-accept-12'));
    incidentTestBalance($repository, $retryId, '92', '0', '0', '8', '8');
    ensure($repository->findIncident($retryCaseId, 1)['saldo'] === '8.000', 'Partial reacceptance resolves exactly 12');
    logisticsRejects(fn() => $repository->voidDelivery((int) $acceptedTwelve['id'], 1, (int) $acceptedTwelve['despacho_version'], 'EN_RESOLUCION',
        'Corrección con resolución dependiente', logisticsContext('retry-linked-void')), 'REFERENCE_CONFLICT');
    $acceptedEight = $repository->recordDelivery($retryId, incidentTestVersion($repository, $retryId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $retryLine, 'cantidad' => '8', 'aceptaciones_incidencias' => [['incidencia_id' => $retryCaseId, 'cantidad' => '8']]]],
        'ENTREGADO', logisticsContext('retry-accept-8'));
    ensure($acceptedEight['despacho_estado'] === 'ENTREGADO', 'All quantities reaccepted with no blocking case may finish delivered');
    incidentTestBalance($repository, $retryId, '100', '0', '0', '0', '0');

    // One acceptance detail cannot resolve two cases using more than it accepted.
    $split = incidentTestReady($pdo, $repository, 'split-cases', '30');
    $splitId = (int) $split['dispatch']['id']; $splitLine = (int) $split['details'][0]['id'];
    $caseOne = incidentTestCase($repository, $splitId, $splitLine, '10', 'split-case-one');
    $caseTwo = incidentTestCase($repository, $splitId, $splitLine, '10', 'split-case-two', 'DANO');
    $before = incidentTestCounts($pdo);
    logisticsRejects(fn() => $repository->recordDelivery($splitId, incidentTestVersion($repository, $splitId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $splitLine, 'cantidad' => '10', 'aceptaciones_incidencias' => [
            ['incidencia_id' => (int) $caseOne['id'], 'cantidad' => '10'], ['incidencia_id' => (int) $caseTwo['id'], 'cantidad' => '10']]]],
        'EN_RESOLUCION', logisticsContext('double-use-acceptance')), 'QUANTITY_EXCEEDED');
    ensure(incidentTestCounts($pdo) === $before, 'Bad allocation has no attempt, resolution or operation side effects');
    $repository->recordDelivery($splitId, incidentTestVersion($repository, $splitId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $splitLine, 'cantidad' => '30', 'aceptaciones_incidencias' => [
            ['incidencia_id' => (int) $caseOne['id'], 'cantidad' => '10'], ['incidencia_id' => (int) $caseTwo['id'], 'cantidad' => '10']]]],
        'ENTREGADO', logisticsContext('case-plus-free-acceptance'));
    incidentTestBalance($repository, $splitId, '30', '0', '0', '0', '0');

    // Full acceptance may still require resolving a documented claim before closure.
    $info = incidentTestReady($pdo, $repository, 'informative-damage');
    $infoId = (int) $info['dispatch']['id']; $infoLine = (int) $info['details'][0]['id'];
    $before = incidentTestCounts($pdo);
    logisticsRejects(fn() => $repository->recordDelivery($infoId, incidentTestVersion($repository, $infoId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $infoLine, 'cantidad' => '100', 'incidencias_informativas' => [
            ['tipo' => 'DANO', 'cantidad' => '101', 'motivo' => 'Reclamo excede lo aceptado', 'evidencia' => ['reclamo' => 'fixture']]]]],
        'EN_RESOLUCION', logisticsContext('informative-over-accepted')), 'QUANTITY_EXCEEDED');
    ensure(incidentTestCounts($pdo) === $before, 'Informative damage cannot affect more than accepted quantities or leave a partial attempt');
    $infoDelivery = $repository->recordDelivery($infoId, incidentTestVersion($repository, $infoId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $infoLine, 'cantidad' => '100', 'incidencias_informativas' => [
            ['tipo' => 'DANO', 'cantidad' => '20', 'causas' => ['DANO'], 'motivo' => 'Daño aceptado con reclamo',
                'evidencia' => ['reclamo' => '20 unidades aceptadas con daño']]]]], 'EN_RESOLUCION', logisticsContext('informative-accepted'));
    ensure(count($infoDelivery['incidencias_ids']) === 1, 'Acceptance and damage report persist atomically without a placeholder case');
    $infoCase = $repository->findIncident((int) $infoDelivery['incidencias_ids'][0], 1);
    ensure($infoCase['modo'] === 'INFORMATIVA' && $infoCase['cantidad'] === '20.000' && (int) $infoCase['entrega_id'] === (int) $infoDelivery['id'],
        'Informative damage retains structured affected accepted quantity and its attempt');
    $infoDispatch = incidentTestBalance($repository, $infoId, '100', '0', '0', '0', '0');
    ensure($infoDispatch['estado'] === 'EN_RESOLUCION', 'Zero quantity pending does not hide blocking informative claim');
    logisticsRejects(fn() => $repository->closeDispatch($infoId, (int) $infoDispatch['version'], 'EN_RESOLUCION', 'Todo recibido', logisticsContext('info-premature-close')), 'INVALID_STATE');
    $infoCase = $repository->findIncident((int) $infoCase['id'], 1);
    $repository->resolveIncident((int) $infoCase['id'], (int) $infoCase['version'], incidentTestVersion($repository, $infoId),
        ['tipo' => 'RESOLUCION_OPERATIVA', 'motivo' => 'Reclamo atendido', 'evidencia' => ['acuerdo' => 'aceptación del cliente']], logisticsContext('informative-resolved'));
    $infoDispatch = $repository->findDispatch($infoId, 1);
    ensure($repository->closeDispatch($infoId, (int) $infoDispatch['version'], $infoDispatch['estado'], 'Reclamo resuelto', logisticsContext('info-final-close'))['estado'] === 'ENTREGADO',
        'Resolved informative claim permits delivered final state without fictitious loss');
    logisticsRejects(fn() => $repository->voidDelivery((int) $infoDelivery['id'], 1, incidentTestVersion($repository, $infoId), 'EN_TRANSITO',
        'Corrección sobre cierre', logisticsContext('info-closed-void')), 'INVALID_STATE');

    // A qualitative documentary issue needs no invented merchandise quantity.
    $documentary = incidentTestReady($pdo, $repository, 'documentary-issue');
    $documentaryId = (int) $documentary['dispatch']['id'];
    $documentaryCase = $repository->createIncident($documentaryId, incidentTestVersion($repository, $documentaryId),
        ['tipo' => 'PROBLEMA_DOCUMENTAL', 'modo' => 'INFORMATIVA', 'motivo' => 'Documento de recepción pendiente',
            'evidencia' => ['requerimiento' => 'Constancia firmada']], logisticsContext('documentary-case'));
    $repository->recordDelivery($documentaryId, incidentTestVersion($repository, $documentaryId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => (int) $documentary['details'][0]['id'], 'cantidad' => '100']],
        'EN_RESOLUCION', logisticsContext('documentary-full-accepted'));
    $documentaryCase = $repository->findIncident((int) $documentaryCase['id'], 1);
    ensure($documentaryCase['cantidad'] === null && $documentaryCase['despacho_detalle_id'] === null, 'Documentary issue has no fictitious merchandise allocation');
    incidentTestBalance($repository, $documentaryId, '100', '0', '0', '0', '0');
    logisticsRejects(fn() => $repository->closeDispatch($documentaryId, incidentTestVersion($repository, $documentaryId),
        'EN_RESOLUCION', 'Falta documentación', logisticsContext('documentary-premature-close')), 'INVALID_STATE');
    $repository->resolveIncident((int) $documentaryCase['id'], (int) $documentaryCase['version'], incidentTestVersion($repository, $documentaryId),
        ['tipo' => 'RESOLUCION_OPERATIVA', 'motivo' => 'Documento verificado', 'evidencia' => ['documento' => 'Constancia firmada']], logisticsContext('documentary-solved'));
    $documentaryDispatch = $repository->findDispatch($documentaryId, 1);
    ensure($repository->closeDispatch($documentaryId, (int) $documentaryDispatch['version'], $documentaryDispatch['estado'],
        'Documentación completa', logisticsContext('documentary-final-close'))['estado'] === 'ENTREGADO', 'Documentary resolution permits final closure');

    // Trusted adapter identity, authority, evidence and granular uniqueness are mandatory.
    $invalid = incidentTestReady($pdo, $repository, 'invalid-confirmations');
    $invalidId = (int) $invalid['dispatch']['id']; $invalidLine = (int) $invalid['details'][0]['id'];
    $invalidCase = incidentTestCase($repository, $invalidId, $invalidLine, '20', 'invalid-case');
    $invalidDispatch = $repository->findDispatch($invalidId, 1);
    $facts = [
        'not-confirmed' => ['confirmado' => false], 'not-authorized' => ['autorizado' => false],
        'wrong-branch' => ['sucursal_id' => 2], 'wrong-warehouse' => ['almacen_id' => 3],
        'wrong-unit' => ['unidad_id' => 2], 'wrong-product' => ['producto_id' => 2], 'wrong-lot' => ['lote_id' => 2],
        'wrong-dispatch' => ['despacho_id' => $totalId], 'wrong-case' => ['incidencia_id' => (int) $rejected['incidencias_ids'][0]],
        'wrong-detail' => ['despacho_detalle_id' => $totalLine], 'wrong-direction' => ['direccion' => 'SALIDA'],
        'wrong-purpose' => ['finalidad' => 'COMPRA'], 'wrong-type' => ['tipo' => 'PERDIDA_CONFIRMADA'],
        'wrong-quantity' => ['cantidad' => '19.999'], 'no-evidence' => ['evidencia' => []],
    ];
    $before = incidentTestCounts($pdo);
    foreach ($facts as $label => $replacement) {
        $reference = $inventory->register($label, [...incidentTestFact($invalidDispatch, $invalidCase, '20'), ...$replacement]);
        logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
            incidentTestResolution('RETORNO_CONFIRMADO', '20', $reference), logisticsContext('bad-confirmation-' . $label)), 'REFERENCE_CONFLICT');
        ensure(incidentTestCounts($pdo) === $before, 'Rejected ' . $label . ' confirmation leaves no quantity/record');
    }
    $lossWithoutApproval = incidentTestFact($invalidDispatch, $invalidCase, '20', 'PERDIDA_CONFIRMADA');
    unset($lossWithoutApproval['autorizacion_ref']);
    $noApprovalReference = $inventory->register('no-loss-approval', $lossWithoutApproval);
    logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        incidentTestResolution('PERDIDA_CONFIRMADA', '20', $noApprovalReference), logisticsContext('loss-no-approval')), 'REFERENCE_CONFLICT');
    $withoutInventory = new LogisticsRepository($pdo);
    $validReference = $inventory->register('valid-after-invalid', incidentTestFact($invalidDispatch, $invalidCase, '20'));
    $lookupCount = count($inventory->lookups);
    foreach ([['sistema' => 'INVÉNTARIOS', 'id' => 'one'], ['sistema' => 'INVENTARIOS-QA', 'id' => 'retorno-ñ']] as $index => $invalidAsciiReference) {
        logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
            incidentTestResolution('RETORNO_CONFIRMADO', '20', $invalidAsciiReference), logisticsContext('non-ascii-reference-' . $index)), 'INVALID_INPUT');
    }
    ensure(count($inventory->lookups) === $lookupCount, 'Malformed ASCII identities are rejected before consulting the Inventory adapter');
    logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        incidentTestResolution('RETORNO_CONFIRMADO', '20', [...$validReference, 'confirmado' => true, 'cantidad' => '20']),
        logisticsContext('request-cannot-supply-confirmed-fact')), 'INVALID_INPUT');
    logisticsRejects(fn() => $withoutInventory->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        incidentTestResolution('RETORNO_CONFIRMADO', '20', $validReference), logisticsContext('missing-provider')), 'REFERENCE_CONFLICT');
    logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        incidentTestResolution('RETORNO_CONFIRMADO', '20', ['sistema' => 'INVENTARIOS-QA', 'id' => 'invented']), logisticsContext('invented-confirmation')), 'REFERENCE_CONFLICT');
    logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        incidentTestResolution('RETORNO_CONFIRMADO', '20', $validReference), logisticsContext('wrong-resolution-scope', 2)), 'NOT_FOUND');
    logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        ['tipo' => 'RESOLUCION_OPERATIVA', 'motivo' => 'Un texto no dispone mercadería', 'evidencia' => ['texto' => 'sin hecho físico']],
        logisticsContext('quantitative-case-cannot-be-marked-resolved')), 'INVALID_STATE');

    // An injected late history failure must also undo the external fact allocation.
    $pdo->exec('ALTER TABLE logistica_historial ADD CONSTRAINT ck_incident_test_history_failure CHECK (actor_id <> 2)');
    try {
        $before = incidentTestCounts($pdo); $beforeDispatch = $repository->findDispatch($invalidId, 1); $beforeCase = $repository->findIncident((int) $invalidCase['id'], 1);
        logisticsRejects(fn() => $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
            incidentTestResolution('RETORNO_CONFIRMADO', '20', $validReference), logisticsContext('history-failed-return', 1, 2)));
        ensure(incidentTestCounts($pdo) === $before && $repository->findDispatch($invalidId, 1) === $beforeDispatch
            && $repository->findIncident((int) $invalidCase['id'], 1) === $beforeCase, 'Late history failure atomically rolls back resolution, versions, balances and operation');
    } finally {
        $dropSyntax = str_contains((string) $pdo->query('SELECT VERSION()')->fetchColumn(), 'MariaDB') ? 'DROP CONSTRAINT' : 'DROP CHECK';
        $pdo->exec('ALTER TABLE logistica_historial ' . $dropSyntax . ' ck_incident_test_history_failure');
    }
    $repository->resolveIncident((int) $invalidCase['id'], 1, incidentTestVersion($repository, $invalidId),
        incidentTestResolution('RETORNO_CONFIRMADO', '20', $validReference), logisticsContext('same-fact-after-rollback'));
    incidentTestBalance($repository, $invalidId, '0', '20', '0', '80', '0');

    // Concurrent return and reacceptance cannot both consume the same 20 units.
    $race = incidentTestReady($pdo, $repository, 'return-vs-retry');
    $raceId = (int) $race['dispatch']['id']; $raceLine = (int) $race['details'][0]['id'];
    $raceAttempt = $repository->recordDelivery($raceId, incidentTestVersion($repository, $raceId), logisticsDeliveryHeader(),
        [['despacho_detalle_id' => $raceLine, 'cantidad' => '80', 'cantidad_rechazada' => '20', 'motivo_rechazo' => 'Caso de concurrencia']],
        'EN_RESOLUCION', logisticsContext('race-first'));
    $raceCase = $repository->findIncident((int) $raceAttempt['incidencias_ids'][0], 1);
    $raceDispatch = $repository->findDispatch($raceId, 1);
    $results = incidentTestRace($pdo, ['dispatch_id' => $raceId, 'dispatch_version' => (int) $raceDispatch['version'],
        'detail_id' => $raceLine, 'incident_id' => (int) $raceCase['id'], 'incident_version' => (int) $raceCase['version'],
        'confirmed_fixture' => incidentTestFact($raceDispatch, $raceCase, '20')]);
    ensure(count(array_filter($results, static fn(array $result): bool => isset($result['ok']))) === 1, 'Exactly one return/reacceptance wins');
    ensure(count(array_filter($results, static fn(array $result): bool => ($result['reason'] ?? null) === 'CONFLICT')) === 1, 'Losing writer gets version conflict');
    $raceDispatch = $repository->findDispatch($raceId, 1); $raceDetail = $raceDispatch['details'][0];
    ensure($raceDetail['cantidad_pendiente'] === '0.000' && $raceDetail['cantidad_asignada'] === '0.000', 'Race leaves no pending quantity or unresolved allocation');
    ensure(LogisticsQuantity::toMillis($raceDetail['cantidad_entregada'], true) + LogisticsQuantity::toMillis($raceDetail['cantidad_retornada'], true) === 100000,
        'Concurrent final facts conserve exactly 100 units');
    ensure(count($repository->findIncident((int) $raceCase['id'], 1)['resoluciones']) === 1, 'Only one resolution survives the race');

    echo 'Logistics incidents integration: ' . $GLOBALS['checks'] . " checks passed\n";
} finally {
    $db->close();
}

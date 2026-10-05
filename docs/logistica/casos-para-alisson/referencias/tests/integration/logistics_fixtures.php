<?php
declare(strict_types=1);

use App\Exceptions\LogisticsPersistenceException;
use App\Repositories\GuideRepository;
use App\Repositories\LogisticsRepository;

function logisticsTables(): array
{
    return ['logistica_preparaciones', 'logistica_preparacion_detalle', 'logistica_despachos',
        'logistica_despacho_detalle', 'logistica_entregas', 'logistica_entrega_detalle',
        'logistica_operaciones', 'logistica_historial', 'logistica_incidencias',
        'logistica_incidencia_resoluciones'];
}
function logisticsCounts(PDO $pdo): array
{
    $counts = [];
    foreach (logisticsTables() as $table) $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    return $counts;
}
function logisticsIndependentConnection(PDO $pdo): PDO
{
    $name = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($name) || !preg_match('/^bayer_test_[a-f0-9]{16}$/D', $name)) throw new RuntimeException('Independent connection requires generated test database');
    return new PDO('mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('TEST_DB_PORT') ?: '3306')
        . ';dbname=' . $name . ';charset=utf8mb4', getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
function logisticsFixtures(PDO $pdo): void
{
    fixtures($pdo);
    $pdo->exec("INSERT INTO sucursales(id,empresa_id,codigo,nombre,distrito_id) VALUES(2,1,'S2','Otra sucursal',2)");
    $pdo->exec("INSERT INTO almacenes(id,sucursal_id,codigo,nombre) VALUES(3,2,'A3','Otra sede')");
    $pdo->exec("INSERT INTO clientes(id,nro_doc,razon_social) VALUES(2,'00000002','Otro cliente')");
}
function logisticsContext(string $key, int $branch = 1, int $actor = 1): array
{
    return ['sucursal_id' => $branch, 'actor_id' => $actor, 'idempotency_key' => $key];
}
function logisticsHeader(string $reference = 'sale-001', int $branch = 1): array
{
    return ['almacen_id' => $branch === 1 ? 1 : 3, 'tipo_origen' => 'VENTA', 'origen_ref' => $reference,
        'fecha' => '2026-10-05', 'destino' => 'Dirección de entrega de prueba', 'cliente_id' => 1,
        'almacen_destino_id' => null, 'reserva_ref' => 'reservation-' . $reference];
}
function logisticsLines(): array
{
    return [['origen_linea_ref' => 'line-1', 'producto_id' => 1, 'unidad_id' => 1, 'lote_id' => 1, 'cantidad' => '2.500'],
        ['origen_linea_ref' => 'line-2', 'producto_id' => 2, 'unidad_id' => 2, 'lote_id' => 2, 'cantidad' => '1.125']];
}
function logisticsDeliveryHeader(string $receiver = 'Cliente de prueba'): array
{
    return ['fecha' => '2026-10-05', 'recibido_por' => $receiver, 'observaciones' => null, 'recepcion_ref' => null];
}
function logisticsRejects(callable $operation, ?string $reason = null): void
{
    try { $operation(); }
    catch (Throwable $error) {
        ensure($error instanceof LogisticsPersistenceException, 'Expected logistics error, got ' . get_class($error) . ': ' . $error->getMessage());
        if ($reason !== null) ensure($error->reasonCode() === $reason, 'Expected ' . $reason . ', got ' . $error->reasonCode() . ': ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected logistics operation to fail');
}
/** Create an existing validated guide; logistics never creates guide records itself. */
function logisticsGuide(PDO $pdo, string $label, array $lines, int $branch = 1, int $client = 1): array
{
    $guides = new GuideRepository($pdo);
    $header = [...guideHeader('G-' . $label), 'sucursal_id' => $branch, 'cliente_id' => $client];
    // The existing guide CRUD accepts integer quantities, while its DECIMAL schema
    // permits fractions. Build a normal draft, then prepare those valid database
    // quantities only inside this isolated fixture before documentary validation.
    $details = array_map(static fn(array $line): array => ['producto_id' => $line['producto_id'],
        'unidad_id' => $line['unidad_id'], 'cantidad' => '1'], $lines);
    $id = $guides->create($header, $details, 1);
    $persisted = $guides->find($id)['details'];
    $statement = $pdo->prepare('UPDATE guias_detalle SET cantidad=? WHERE id=? AND guia_id=?');
    foreach ($lines as $index => $line) $statement->execute([$line['cantidad'], $persisted[$index]['id'], $id]);
    $version = $guides->markValidated($id, 1, 2);
    return ['id' => $id, 'version' => $version];
}
/** Test fixture for a dispatch in transit, using a confirmed external movement reference. */
function logisticsReadyDispatch(PDO $pdo, LogisticsRepository $repository, string $label, ?array $lines = null): array
{
    $lines ??= logisticsLines();
    $prep = $repository->createPreparation(logisticsHeader($label), $lines, logisticsContext($label . '-prepare'));
    $prepared = $repository->transitionPreparation((int) $prep['id'], 1, 'EN_PREPARACION', 'PREPARADA', '', logisticsContext($label . '-ready'));
    $guide = logisticsGuide($pdo, $label, $lines);
    $dispatch = $repository->createDispatch((int) $prep['id'], ['fecha' => '2026-10-05', 'guia_id' => $guide['id'], 'guia_version' => $guide['version']], logisticsContext($label . '-dispatch'));
    $dispatched = $repository->transitionDispatch((int) $dispatch['id'], 1, 'PENDIENTE', 'DESPACHADO', ['movimiento_salida_ref' => 'out-' . $label], logisticsContext($label . '-out'));
    $transit = $repository->transitionDispatch((int) $dispatch['id'], (int) $dispatched['version'], 'DESPACHADO', 'EN_TRANSITO', [], logisticsContext($label . '-transit'));
    return ['preparation' => $prepared, 'dispatch' => $transit, 'guide' => $guide,
        'details' => $repository->findDispatch((int) $dispatch['id'], 1)['details']];
}

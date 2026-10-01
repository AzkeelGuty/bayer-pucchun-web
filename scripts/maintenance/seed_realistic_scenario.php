<?php
declare(strict_types=1);

use App\Repositories\DocumentRepository;
use App\Repositories\GuideRepository;
use App\Repositories\StockRepository;
use App\Services\WorkflowService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo disponible por CLI.\n");
}

$root = dirname(__DIR__, 2);
require $root . '/config/bootstrap.php';

$args = array_slice($argv, 1);
$mode = in_array('--apply', $args, true) ? 'apply' : (in_array('--cleanup', $args, true) ? 'cleanup' : 'preview');
$allowProduction = in_array('--allow-production', $args, true);

$environment = strtolower((string) config('app.env', 'production'));
if ($environment === 'production' && !$allowProduction) {
    fwrite(STDERR, "BLOQUEADO: este script no escribe en production sin --allow-production.\n");
    fwrite(STDERR, "Úsalo primero en XAMPP/local.\n");
    exit(2);
}

$pdo = db();

function one(PDO $pdo, string $sql, array $params = []): ?array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function all(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function actorForRole(PDO $pdo, string $role, ?int $fallback = null): int
{
    $row = one($pdo, "SELECT u.id
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id=u.id
        JOIN roles r ON r.id=ur.rol_id
        WHERE u.estado=1 AND r.nombre=?
        ORDER BY u.id
        LIMIT 1", [$role]);
    if ($row) return (int)$row['id'];
    if ($fallback !== null) return $fallback;

    $admin = one($pdo, "SELECT u.id
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id=u.id
        JOIN roles r ON r.id=ur.rol_id
        WHERE u.estado=1 AND r.nombre='ADMIN'
        ORDER BY u.id
        LIMIT 1");
    if ($admin) return (int)$admin['id'];

    $any = one($pdo, "SELECT id FROM usuarios WHERE estado=1 ORDER BY id LIMIT 1");
    if (!$any) throw new RuntimeException('No existe un usuario activo para crear la simulación.');
    return (int)$any['id'];
}

function simulationIds(PDO $pdo): array
{
    return [
        'documents' => array_map('intval', array_column(all($pdo, "SELECT id FROM documentos_cabecera WHERE numero LIKE 'SIM-REAL-%'"), 'id')),
        'guides' => array_map('intval', array_column(all($pdo, "SELECT id FROM guias_cabecera WHERE numero LIKE 'SIM-REAL-%'"), 'id')),
        'stock' => array_map('intval', array_column(all($pdo, "SELECT id FROM stock_cabecera WHERE idempotency_key LIKE 'sim-real-%'"), 'id')),
    ];
}

function deleteByIds(PDO $pdo, string $sqlPrefix, array $ids): void
{
    if (!$ids) return;
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare($sqlPrefix . " ($marks)");
    $st->execute($ids);
}

function cleanupSimulation(PDO $pdo): void
{
    $ids = simulationIds($pdo);

    $maps = [
        ['module'=>'documentos','dataset'=>'documentos','ids'=>$ids['documents']],
        ['module'=>'guias','dataset'=>'guias','ids'=>$ids['guides']],
        ['module'=>'stock','dataset'=>'stock','ids'=>$ids['stock']],
    ];

    foreach ($maps as $map) {
        $recordIds = $map['ids'];
        if (!$recordIds) continue;
        $marks = implode(',', array_fill(0, count($recordIds), '?'));

        $st = $pdo->prepare("SELECT DISTINCT publicacion_id FROM detalle_publicacion WHERE dataset=? AND registro_id IN ($marks)");
        $st->execute([$map['dataset'], ...$recordIds]);
        $publicationIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $st = $pdo->prepare("DELETE FROM detalle_publicacion WHERE dataset=? AND registro_id IN ($marks)");
        $st->execute([$map['dataset'], ...$recordIds]);

        deleteByIds($pdo, 'DELETE FROM publicaciones WHERE id IN', $publicationIds);

        $st = $pdo->prepare("DELETE FROM validaciones WHERE modulo=? AND registro_id IN ($marks)");
        $st->execute([$map['module'], ...$recordIds]);
    }

    deleteByIds($pdo, 'DELETE FROM documentos_cabecera WHERE id IN', $ids['documents']);
    deleteByIds($pdo, 'DELETE FROM guias_cabecera WHERE id IN', $ids['guides']);
    deleteByIds($pdo, 'DELETE FROM stock_cabecera WHERE id IN', $ids['stock']);
}

function describe(array $row, array $fields): string
{
    $parts = [];
    foreach ($fields as $field) {
        if (isset($row[$field]) && trim((string)$row[$field]) !== '') {
            $parts[] = (string)$row[$field];
        }
    }
    return implode(' · ', $parts);
}

$client = one($pdo, "SELECT c.*,d.nombre distrito,p.nombre provincia,dp.nombre departamento
    FROM clientes c
    LEFT JOIN distritos d ON d.id=c.distrito_id
    LEFT JOIN provincias p ON p.id=c.provincia_id
    LEFT JOIN departamentos dp ON dp.id=c.departamento_id
    WHERE c.nro_doc='20603875584'
    LIMIT 1");

if (!$client) {
    $client = one($pdo, "SELECT c.*,d.nombre distrito,p.nombre provincia,dp.nombre departamento
        FROM clientes c
        LEFT JOIN distritos d ON d.id=c.distrito_id
        LEFT JOIN provincias p ON p.id=c.provincia_id
        LEFT JOIN departamentos dp ON dp.id=c.departamento_id
        WHERE c.departamento_id IS NOT NULL AND c.provincia_id IS NOT NULL AND c.distrito_id IS NOT NULL
        ORDER BY c.id
        LIMIT 1");
}

if (!$client) {
    $client = one($pdo, "SELECT c.*,NULL distrito,NULL provincia,NULL departamento
        FROM clientes c
        ORDER BY c.id
        LIMIT 1");
}

if (!$client) {
    throw new RuntimeException('No hay clientes. Primero importa clientes.csv.');
}

$preferredProductCodes = ['0000072153','0000072146','0000074661'];
$products = [];
foreach ($preferredProductCodes as $code) {
    $row = one($pdo, "SELECT p.id,p.codigo,p.nombre,p.unidad_base_id,u.codigo unidad
        FROM productos p
        JOIN unidades_medida u ON u.id=p.unidad_base_id
        WHERE p.codigo=? AND p.estado=1
        LIMIT 1", [$code]);
    if ($row) $products[(int)$row['id']] = $row;
}
if (count($products) < 2) {
    foreach (all($pdo, "SELECT p.id,p.codigo,p.nombre,p.unidad_base_id,u.codigo unidad
        FROM productos p
        JOIN unidades_medida u ON u.id=p.unidad_base_id
        WHERE p.estado=1
        ORDER BY p.id
        LIMIT 5") as $row) {
        $products[(int)$row['id']] = $row;
        if (count($products) >= 3) break;
    }
}
$products = array_values($products);
if (count($products) < 1) {
    throw new RuntimeException('No hay productos activos con unidad. Primero importa productos.csv.');
}

$type = one($pdo, "SELECT id,codigo,nombre FROM tipos_documento WHERE codigo='FAC' LIMIT 1")
    ?? one($pdo, "SELECT id,codigo,nombre FROM tipos_documento ORDER BY id LIMIT 1");
if (!$type) throw new RuntimeException('No existe ningún tipo de documento.');

$seller = one($pdo, "SELECT id,codigo,TRIM(CONCAT(nombres,' ',COALESCE(apellidos,''))) nombre
    FROM vendedores WHERE estado=1 ORDER BY id LIMIT 1");
if (!$seller) throw new RuntimeException('No existe ningún vendedor activo.');

$branch = one($pdo, "SELECT s.id,s.codigo,s.nombre,e.ruc empresa_ruc,e.razon_social empresa
    FROM sucursales s JOIN empresas e ON e.id=s.empresa_id
    WHERE s.estado=1 ORDER BY s.id LIMIT 1");
if (!$branch) throw new RuntimeException('No existe ninguna sucursal activa.');

$warehouse = one($pdo, "SELECT id,codigo,nombre,sucursal_id
    FROM almacenes
    WHERE estado=1
    ORDER BY (sucursal_id=? ) DESC,id
    LIMIT 1", [(int)$branch['id']]);
if (!$warehouse) throw new RuntimeException('No existe ningún almacén activo.');

$creatorId = actorForRole($pdo, 'DIGITADOR');
$supervisorId = actorForRole($pdo, 'SUPERVISOR', actorForRole($pdo, 'ADMIN'));

$geoComplete = !empty($client['departamento_id']) && !empty($client['provincia_id']) && !empty($client['distrito_id']);

echo "SIMULACIÓN CON MAESTROS REALES\n";
echo "================================\n";
echo "Entorno       : {$environment}\n";
echo "Modo          : {$mode}\n";
echo "Cliente       : ".describe($client,['nro_doc','razon_social'])."\n";
echo "Ubicación     : ".($geoComplete ? describe($client,['distrito','provincia','departamento']) : 'NO REGISTRADA')."\n";
echo "Vendedor      : ".describe($seller,['codigo','nombre'])."\n";
echo "Sucursal      : ".describe($branch,['codigo','nombre'])."\n";
echo "Almacén       : ".describe($warehouse,['codigo','nombre'])."\n";
echo "Productos     :\n";
foreach ($products as $product) {
    echo "  - ".describe($product,['codigo','nombre','unidad'])."\n";
}
echo "\n";

if ($mode === 'cleanup') {
    $pdo->beginTransaction();
    try {
        cleanupSimulation($pdo);
        $pdo->commit();
        echo "OK: se eliminaron únicamente los registros SIM-REAL creados por este script.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    exit(0);
}

if ($mode === 'preview') {
    echo "No se escribió nada en la base de datos.\n";
    echo "Para crear el escenario: php scripts/maintenance/seed_realistic_scenario.php --apply\n";
    echo "Para limpiarlo después: php scripts/maintenance/seed_realistic_scenario.php --cleanup\n";
    if (!$geoComplete) {
        echo "\nADVERTENCIA: el cliente elegido no tiene Departamento/Provincia/Distrito completos.\n";
        echo "La guía se puede simular, pero no será una prueba completa del dataset Bayer hasta registrar una ubicación real.\n";
    }
    exit(0);
}

$today = date('Y-m-d');
$stamp = date('Ymd-His');

$docRepo = new DocumentRepository($pdo);
$guideRepo = new GuideRepository($pdo);
$stockRepo = new StockRepository($pdo);
$workflow = new WorkflowService();

$docDetails = [];
foreach (array_slice($products, 0, 2) as $i => $product) {
    $docDetails[] = [
        'producto_id' => (int)$product['id'],
        'unidad_id' => (int)$product['unidad_base_id'],
        'cantidad' => (string)($i + 1),
        // El Excel real no trae precio. No se inventa: se deja 0.00.
        'valor_unitario' => '0.00',
    ];
}

$guideDetails = [];
foreach (array_slice($products, 0, 2) as $i => $product) {
    $guideDetails[] = [
        'producto_id' => (int)$product['id'],
        'unidad_id' => (int)$product['unidad_base_id'],
        'cantidad' => (string)($i + 2),
    ];
}

$stockDetails = [];
foreach (array_slice($products, 0, 3) as $i => $product) {
    $stockDetails[] = [
        'producto_id' => (int)$product['id'],
        'unidad_id' => (int)$product['unidad_base_id'],
        'lote_id' => null,
        'cantidad' => (string)(10 + ($i * 5)),
    ];
}

$pdo->beginTransaction();
try {
    cleanupSimulation($pdo);

    $publishedDocId = $docRepo->create([
        'tipo_documento_id' => (int)$type['id'],
        'numero' => 'SIM-REAL-DOC-'.$stamp,
        'fecha' => $today,
        'cliente_id' => (int)$client['id'],
        'vendedor_id' => (int)$seller['id'],
        'sucursal_id' => (int)$branch['id'],
    ], $docDetails, $creatorId);
    $version = 1;
    $version = $workflow->transition($docRepo, $publishedDocId, $version, 'VALIDADO', $supervisorId);
    $version = $workflow->transition($docRepo, $publishedDocId, $version, 'PUBLICADO', $supervisorId);

    $observedDocId = $docRepo->create([
        'tipo_documento_id' => (int)$type['id'],
        'numero' => 'SIM-REAL-OBS-'.$stamp,
        'fecha' => $today,
        'cliente_id' => (int)$client['id'],
        'vendedor_id' => (int)$seller['id'],
        'sucursal_id' => (int)$branch['id'],
    ], [$docDetails[0]], $creatorId);
    $version = 1;
    $version = $workflow->transition($docRepo, $observedDocId, $version, 'VALIDADO', $supervisorId);
    $version = $workflow->transition($docRepo, $observedDocId, $version, 'OBSERVADO', $supervisorId, 'SIMULACIÓN: corregir y volver a validar este registro.');

    $guideId = $guideRepo->create([
        'numero' => 'SIM-REAL-GUI-'.$stamp,
        'fecha' => $today,
        'cliente_id' => (int)$client['id'],
        'vendedor_id' => (int)$seller['id'],
        'sucursal_id' => (int)$branch['id'],
        'departamento_id' => $geoComplete ? (int)$client['departamento_id'] : null,
        'provincia_id' => $geoComplete ? (int)$client['provincia_id'] : null,
        'distrito_id' => $geoComplete ? (int)$client['distrito_id'] : null,
    ], $guideDetails, $creatorId);
    $version = 1;
    $version = $workflow->transition($guideRepo, $guideId, $version, 'VALIDADO', $supervisorId);
    if ($geoComplete) {
        $version = $workflow->transition($guideRepo, $guideId, $version, 'PUBLICADO', $supervisorId);
    }

    $stockId = $stockRepo->create([
        'fecha_stock' => $today,
        'almacen_id' => (int)$warehouse['id'],
        'idempotency_key' => 'sim-real-'.$stamp,
    ], $stockDetails, $creatorId);
    $version = 1;
    $version = $workflow->transition($stockRepo, $stockId, $version, 'VALIDADO', $supervisorId);
    $version = $workflow->transition($stockRepo, $stockId, $version, 'PUBLICADO', $supervisorId);

    $pdo->commit();

    echo "ESCENARIO CREADO\n";
    echo "=================\n";
    echo "Documento publicado : #{$publishedDocId}\n";
    echo "Documento observado : #{$observedDocId}\n";
    echo "Guía                 : #{$guideId} · ".($geoComplete ? 'PUBLICADA' : 'VALIDADA (falta ubicación real para publicación de prueba completa)')."\n";
    echo "Stock publicado       : #{$stockId}\n";
    echo "\n";
    echo "Ahora prueba: Digitador -> corregir observado; Supervisor -> validar/publicar; Bayer -> ver publicados/API.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: ".$e->getMessage()."\n");
    exit(1);
}

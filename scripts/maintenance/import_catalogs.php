<?php
declare(strict_types=1);

// Importador CLI de los catálogos masivos entregados por el cliente.
// Uso normal: php scripts/maintenance/import_catalogs.php [directorio_csv]
// Reemplazo total de catálogos demo: php scripts/maintenance/import_catalogs.php --replace [directorio_csv]
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo disponible por CLI.\n");
}

$root = dirname(__DIR__, 2);
require $root . '/config/bootstrap.php';

$args = array_slice($argv, 1);
$replace = in_array('--replace', $args, true);
$args = array_values(array_filter($args, static fn($arg) => $arg !== '--replace'));
$dir = $args[0] ?? ($root . '/database/imports');

function readCsv(string $path): Generator
{
    if (!is_file($path)) throw new RuntimeException('No existe: ' . $path);
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('No se pudo abrir: ' . $path);
    $header = fgetcsv($handle);
    if (!$header) throw new RuntimeException('CSV sin cabecera: ' . $path);
    $header = array_map(static fn($v) => trim((string)$v), $header);
    // Excel/Windows suele guardar CSV UTF-8 con BOM. Si no se retira,
    // la primera columna queda como "\xEF\xBB\xBFcodigo" y no se reconoce.
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
    }
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === 1 && trim((string)$row[0]) === '') continue;
        $row = array_pad($row, count($header), '');
        $data = array_combine($header, array_slice($row, 0, count($header)));
        if (is_array($data)) yield array_map(static fn($v) => trim((string)$v), $data);
    }
    fclose($handle);
}

function digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

$pdo = db();
$stats = ['proveedores'=>0,'clientes'=>0,'productos'=>0,'omitidos'=>0];
$errors = [];

try {
    $pdo->beginTransaction();

    if ($replace) {
        // Los datos demo/transaccionales dependen de clientes y productos.
        // Se limpian dentro de la misma transacción para evitar huérfanos.
        $cleanup = [
            'DELETE FROM detalle_publicacion',
            'DELETE FROM publicaciones',
            'DELETE FROM validaciones_detalle',
            'DELETE FROM validaciones',
            'DELETE FROM exportaciones',
            'DELETE FROM homologacion_productos_bayer',
            'DELETE FROM homologacion_clientes',
            'DELETE FROM documentos_detalle',
            'DELETE FROM documentos_cabecera',
            'DELETE FROM guias_detalle',
            'DELETE FROM guias_cabecera',
            'DELETE FROM stock_detalle',
            'DELETE FROM lotes',
            'DELETE FROM stock_cabecera',
            'DELETE FROM proveedores',
            'DELETE FROM clientes',
            'DELETE FROM productos',
        ];
        foreach ($cleanup as $sql) $pdo->exec($sql);
    }

    $providerStmt = $pdo->prepare("INSERT INTO proveedores(codigo,nombre,estado) VALUES(?,?,1)
        ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),estado=1");
    foreach (readCsv($dir . '/proveedores.csv') as $line => $row) {
        $code = digits($row['codigo'] ?? '');
        $name = trim($row['nombre'] ?? '');
        if (strlen($code) !== 11 || $name === '') {
            $stats['omitidos']++;
            $errors[] = 'proveedores.csv fila ' . ($line + 2) . ': RUC/nombre inválido';
            continue;
        }
        $providerStmt->execute([$code, $name]);
        $stats['proveedores']++;
    }

    $clientStmt = $pdo->prepare("INSERT INTO clientes(codigo,tipo_doc,nro_doc,razon_social) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE codigo=VALUES(codigo),tipo_doc=VALUES(tipo_doc),razon_social=VALUES(razon_social)");
    foreach (readCsv($dir . '/clientes.csv') as $line => $row) {
        $code = digits($row['codigo'] ?? '');
        $name = trim($row['nombre'] ?? '');
        if (!in_array(strlen($code), [8,11], true) || $name === '') {
            $stats['omitidos']++;
            $errors[] = 'clientes.csv fila ' . ($line + 2) . ': documento/nombre inválido';
            continue;
        }
        $type = strlen($code) === 11 ? '06' : '01';
        $clientStmt->execute([$code, $type, $code, $name]);
        $stats['clientes']++;
    }

    $unitSelect = $pdo->prepare('SELECT id FROM unidades_medida WHERE codigo=? LIMIT 1');
    $unitInsert = $pdo->prepare("INSERT INTO unidades_medida(codigo,nombre,abreviatura,factor_base) VALUES(?,?,?,1)
        ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),abreviatura=VALUES(abreviatura)");
    $productStmt = $pdo->prepare("INSERT INTO productos(codigo,nombre,tipo_art,unidad_base_id,estado) VALUES(?,?,?,?,1)
        ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),tipo_art=VALUES(tipo_art),unidad_base_id=VALUES(unidad_base_id),estado=1");
    $unitCache = [];

    foreach (readCsv($dir . '/productos.csv') as $line => $row) {
        $code = trim($row['codigo'] ?? '');
        $name = trim($row['nombre'] ?? '');
        $unit = strtoupper(trim($row['unidad'] ?? ''));
        $type = strtoupper(trim($row['tipo_art'] ?? ''));

        if ($code === '' || $name === '' || $unit === '') {
            $stats['omitidos']++;
            $errors[] = 'productos.csv fila ' . ($line + 2) . ': código/nombre/unidad inválido';
            continue;
        }

        if (!isset($unitCache[$unit])) {
            $unitInsert->execute([$unit, $unit, $unit]);
            $unitSelect->execute([$unit]);
            $unitCache[$unit] = (int)$unitSelect->fetchColumn();
        }

        $productStmt->execute([$code, $name, $type !== '' ? $type : null, $unitCache[$unit]]);
        $stats['productos']++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo ($replace ? "Reemplazo e importación terminados" : "Importación terminada") . PHP_EOL;
foreach ($stats as $key => $value) echo str_pad($key, 14) . ': ' . $value . PHP_EOL;
if ($errors) {
    echo PHP_EOL . "Observaciones:" . PHP_EOL;
    foreach ($errors as $error) echo ' - ' . $error . PHP_EOL;
}

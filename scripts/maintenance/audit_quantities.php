<?php
declare(strict_types=1);

// Auditoría segura de cantidades históricas.
// No modifica datos. Reporta registros con cantidades fraccionarias para revisión.
// Uso: php scripts/maintenance/audit_quantities.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo disponible por CLI.\n");
}

require dirname(__DIR__, 2) . '/config/bootstrap.php';

$queries = [
    'documentos' => "SELECT documento_id registro_id,id detalle_id,cantidad
        FROM documentos_detalle
        WHERE cantidad <> FLOOR(cantidad)
        ORDER BY documento_id,id",
    'guias' => "SELECT guia_id registro_id,id detalle_id,cantidad
        FROM guias_detalle
        WHERE cantidad <> FLOOR(cantidad)
        ORDER BY guia_id,id",
    'stock' => "SELECT stock_id registro_id,id detalle_id,cantidad
        FROM stock_detalle
        WHERE cantidad <> FLOOR(cantidad)
        ORDER BY stock_id,id",
];

$total = 0;
echo "AUDITORÍA DE CANTIDADES ENTERAS\n";
echo str_repeat('=', 72) . "\n";

foreach ($queries as $module => $sql) {
    $rows = db()->query($sql)->fetchAll();
    $count = count($rows);
    $total += $count;

    echo strtoupper($module) . ": {$count} registro(s) fraccionario(s)\n";
    foreach ($rows as $row) {
        echo "  registro={$row['registro_id']} detalle={$row['detalle_id']} cantidad={$row['cantidad']}\n";
    }
}

echo str_repeat('-', 72) . "\n";
if ($total === 0) {
    echo "OK: no se encontraron cantidades fraccionarias.\n";
    exit(0);
}

echo "REVISAR: se encontraron {$total} detalle(s) fraccionario(s).\n";
echo "No se modificó ningún dato. Corrija solo después de confirmar la cantidad real.\n";
exit(2);

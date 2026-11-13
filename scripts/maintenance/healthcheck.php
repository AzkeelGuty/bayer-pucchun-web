<?php
declare(strict_types=1);

/**
 * Diagnóstico de solo lectura para instalaciones locales de Bayer-Pucchún.
 * Ejecutar desde la raíz del repositorio:
 *   php scripts/maintenance/healthcheck.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$failures = 0;

function check_health(bool $ok, string $label, string $detail = ''): void
{
    global $failures;
    echo ($ok ? '[OK] ' : '[ERROR] ') . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL;
    if (!$ok) ++$failures;
}

echo 'Bayer-Pucchun | Diagnostico local de errores 500' . PHP_EOL;
echo str_repeat('=', 51) . PHP_EOL;
check_health(PHP_VERSION_ID >= 80100, 'PHP >= 8.1', PHP_VERSION);
foreach (['PDO', 'pdo_mysql', 'mbstring', 'json', 'zip'] as $extension) {
    check_health(extension_loaded($extension), 'Extension ' . $extension);
}
check_health(is_file($root . '/.env'), 'Archivo .env presente');

$logDir = $root . '/storage/logs';
check_health(is_dir($logDir) && is_writable($logDir), 'Directorio de registros disponible', 'storage/logs');
$logFile = $logDir . '/app.log';
if (is_file($logFile)) {
    echo '[INFO] Registro de errores: storage/logs/app.log' . PHP_EOL;
}

try {
    require $root . '/config/bootstrap.php';
    echo '[INFO] APP_ENV=' . config('app.env') . ', APP_DEBUG=' . (config('app.debug') ? 'true' : 'false') . PHP_EOL;
    echo '[INFO] Base configurada: ' . config('database.name') . PHP_EOL;
    $pdo = db();
    check_health(true, 'Conexion MySQL/MariaDB', (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
} catch (Throwable $error) {
    check_health(false, 'Conexion o inicializacion', get_class($error) . ': ' . $error->getMessage());
    exit(1);
}

try {
    $versions = array_map('intval', $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
    $names = [
        2 => '002_schema_v2.sql',
        3 => '003_operational_permissions.sql',
        4 => '004_catalogos_masivos_busqueda.sql',
        5 => '005_api_tokens.sql',
        6 => '006_performance_indexes.sql',
        7 => '007_document_types.sql',
        8 => '008_estado_comprobante.sql',
        9 => '009_guias_desde_documentos_y_pagos.sql',
        10 => '010_numeracion_interna_automatica.sql',
        11 => '011_permisos_flujo_aprobacion.sql',
        12 => '012_sunat_emision_cpe.sql',
        13 => '013_calculos_totales_documentos_guias.sql',
    ];
    foreach ($names as $version => $filename) {
        check_health(in_array($version, $versions, true), 'Migracion ' . str_pad((string) $version, 3, '0', STR_PAD_LEFT), 'database/migrations/' . $filename);
    }
} catch (Throwable $error) {
    check_health(false, 'Historial de migraciones', $error->getMessage());
}

try {
    $schema = [];
    $stmt = $pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $schema[strtolower((string) $row['TABLE_NAME'])][strtolower((string) $row['COLUMN_NAME'])] = true;
    }

    $required = [
        'usuarios' => ['id'],
        'roles' => ['id'],
        'permisos' => ['id'],
        'clientes' => ['id', 'nro_doc', 'razon_social'],
        'productos' => ['id', 'codigo', 'nombre', 'tipo_art'],
        'proveedores' => ['id', 'codigo'],
        'sucursales' => ['id', 'codigo'],
        'documentos_cabecera' => ['id', 'estado_registro', 'estado_comprobante', 'medio_pago', 'referencia_pago'],
        'documentos_detalle' => ['documento_id', 'producto_id', 'cantidad'],
        'guias_cabecera' => ['id', 'estado_registro', 'documento_origen_id', 'estado_sunat'],
        'guias_detalle' => ['guia_id', 'producto_id', 'cantidad'],
        'stock_cabecera' => ['id', 'estado_registro'],
        'stock_detalle' => ['stock_id', 'producto_id', 'cantidad'],
        'partners' => ['id', 'codigo'],
        'homologacion_productos_bayer' => ['id', 'partner_id', 'material_id'],
        'homologacion_clientes' => ['id', 'partner_id', 'customer_id'],
        'homologacion_unidades' => ['id', 'partner_id', 'external_unit_code'],
        'homologacion_sucursales' => ['id', 'partner_id', 'branch_id'],
        'homologacion_almacenes' => ['id', 'partner_id', 'warehouse_id'],
        'homologacion_vendedores' => ['id', 'partner_id', 'sales_id'],
        'api_tokens' => ['id', 'usuario_id'],
        'numeraciones_internas' => ['ambito', 'siguiente'],
        'sunat_series_fiscales' => ['ruc_emisor','serie','ambiente','siguiente'],
        'sunat_emisiones' => ['documento_id','ambiente','estado','codigo_cdr','xml_archivo','cdr_archivo'],
        'documentos_cabecera' => ['id','precio_igv_modalidad'],
        'guias_cabecera' => ['id','precio_igv_modalidad'],
        'guias_detalle' => ['guia_id','valor_unitario'],
    ];
    foreach ($required as $table => $columns) {
        if (!isset($schema[$table])) {
            check_health(false, 'Tabla ausente: ' . $table);
            continue;
        }
        $missing = array_values(array_filter($columns, static fn(string $column): bool => !isset($schema[$table][$column])));
        check_health(!$missing, 'Estructura ' . $table, $missing ? 'Faltan columnas: ' . implode(', ', $missing) : '');
    }

    foreach ([['ADMIN','validation.review'],['ADMIN','publications.publish'],['SUPERVISOR','validation.review']] as [$role,$permission]) {
        try {
            $st=$pdo->prepare('SELECT COUNT(*) FROM rol_permiso rp JOIN roles r ON r.id=rp.rol_id JOIN permisos p ON p.id=rp.permiso_id WHERE r.nombre=? AND r.estado=1 AND p.codigo=?');
            $st->execute([$role,$permission]);
            check_health((int)$st->fetchColumn()>0,'Permiso '.$role.' / '.$permission);
        } catch (Throwable $error) {
            check_health(false,'Permiso '.$role.' / '.$permission,$error->getMessage());
        }
    }

    // Compilar las consultas que consumen las nuevas columnas sin modificar registros.
    foreach ([
        'Detalle de documentos' => 'SELECT dc.medio_pago,dc.referencia_pago,gc.id FROM documentos_cabecera dc LEFT JOIN guias_cabecera gc ON gc.documento_origen_id=dc.id LIMIT 0',
        'Portal Bayer - ventas' => 'SELECT dc.estado_comprobante,dc.medio_pago FROM documentos_cabecera dc LIMIT 0',
        'Portal Bayer - guias' => 'SELECT gc.estado_sunat,gc.documento_origen_id FROM guias_cabecera gc LIMIT 0',
        'Homologaciones Bayer' => 'SELECT p.codigo,h.material_id FROM homologacion_productos_bayer h JOIN partners p ON p.id=h.partner_id LIMIT 0',
    ] as $module => $sql) {
        try {
            $pdo->query($sql);
            check_health(true, 'Consulta: ' . $module);
        } catch (Throwable $error) {
            check_health(false, 'Consulta: ' . $module, $error->getMessage());
        }
    }
} catch (Throwable $error) {
    check_health(false, 'Inspeccion del esquema', $error->getMessage());
}

echo str_repeat('-', 51) . PHP_EOL;
echo $failures === 0
    ? 'Diagnostico estructural correcto. Si persiste el error 500, revise storage/logs/app.log.' . PHP_EOL
    : 'Se encontraron ' . $failures . ' problema(s). Corrija lo indicado antes de repetir las pruebas.' . PHP_EOL;
exit($failures === 0 ? 0 : 1);

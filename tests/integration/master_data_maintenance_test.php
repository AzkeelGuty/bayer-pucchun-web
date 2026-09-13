<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Repositories\Masters\MasterDataMaintenanceRepository;

$database = new TestDatabase();
try {
    $database->load('database/schemas/002_schema_v2.sql');
    fixtures($database->pdo);
    $pdo = $database->pdo;
    $repository = new MasterDataMaintenanceRepository($pdo);
    $examples = [
        'empresas' => ['ruc' => '00000000002', 'razon_social' => 'Prueba'],
        'sucursales' => ['empresa_id' => 1, 'codigo' => 'S-TEST', 'nombre' => 'Prueba'],
        'almacenes' => ['sucursal_id' => 1, 'codigo' => 'A-TEST', 'nombre' => 'Prueba'],
        'clientes' => ['nro_doc' => '00000002', 'razon_social' => 'Prueba'],
        'vendedores' => ['codigo' => 'V-TEST', 'nombres' => 'Prueba'],
        'productos' => ['codigo' => 'P-TEST', 'nombre' => 'Prueba', 'unidad_base_id' => 1],
        'unidades_medida' => ['codigo' => 'U-TEST', 'nombre' => 'Prueba', 'factor_base' => '1.0000'],
        'lotes' => ['producto_id' => 1, 'codigo_lote' => 'L-TEST'],
        'tipos_documento' => ['codigo' => 'TST', 'nombre' => 'Prueba'],
        'departamentos' => ['nombre' => 'Departamento prueba'],
        'provincias' => ['departamento_id' => 1, 'nombre' => 'Provincia prueba'],
        'distritos' => ['provincia_id' => 1, 'nombre' => 'Distrito prueba'],
        'categorias_producto' => ['nombre' => 'Prueba'],
        'marcas' => ['nombre' => 'Prueba'],
    ];
    foreach ($examples as $catalog => $data) {
        $id = $repository->create($catalog, $data, 1);
        ensure($id > 0, "Creación de $catalog");
        $field = array_key_last($data);
        $repository->update($catalog, $id, [$field => $data[$field]], 2);
        $audit = $pdo->query('SELECT * FROM auditoria_acciones ORDER BY id DESC LIMIT 1')->fetch();
        ensure((int) $audit['usuario_id'] === 2 && (int) $audit['entidad_id'] === $id, 'Actor y entidad auditados');
        $metadata = json_decode($audit['metadata_json'], true);
        ensure($metadata === ['catalogo' => $catalog, 'campos' => [$field]], 'Auditoría identifica catálogo y columnas');
    }

    $productId = $repository->create('productos', ['codigo' => 'EDIT', 'nombre' => 'Original', 'unidad_base_id' => 1], 1);
    $repository->update('productos', $productId, ['nombre' => "Nombre ' con texto SQL --", 'estado' => 0], 1);
    $product = $pdo->query("SELECT * FROM productos WHERE id=$productId")->fetch();
    ensure($product['codigo'] === 'EDIT' && $product['nombre'] === "Nombre ' con texto SQL --" && (int) $product['estado'] === 0, 'Edición parcial y valores parametrizados');

    $auditCount = (int) $pdo->query('SELECT COUNT(*) FROM auditoria_acciones')->fetchColumn();
    rejects(fn () => $repository->create('productos', ['codigo' => 'EDIT', 'nombre' => 'Duplicado'], 1), PDOException::class, 1062);
    rejects(fn () => $repository->create('productos', ['codigo' => 'BAD-FK', 'nombre' => 'Prueba', 'unidad_base_id' => 99999], 1), PDOException::class, 1452);
    rejects(fn () => $repository->update('productos', $productId, ['unidad_base_id' => 99999], 1), PDOException::class, 1452);
    rejects(fn () => $repository->update('productos', 99999, ['nombre' => 'Ausente'], 1), RuntimeException::class);
    rejects(fn () => $repository->create('productos', ['codigo' => 'NO-ACTOR', 'nombre' => 'Prueba'], 99999), PDOException::class, 1452);
    rejects(fn () => $repository->update('productos', $productId, ['nombre' => 'No guardar'], 99999), PDOException::class, 1452);
    ensure((int) $pdo->query("SELECT COUNT(*) FROM productos WHERE codigo IN ('NO-ACTOR','BAD-FK')")->fetchColumn() === 0, 'Fallo revierte el maestro');
    ensure($pdo->query("SELECT nombre FROM productos WHERE id=$productId")->fetchColumn() === $product['nombre'], 'Fallo de auditoría revierte la edición');
    ensure((int) $pdo->query('SELECT COUNT(*) FROM auditoria_acciones')->fetchColumn() === $auditCount, 'Fallos no dejan auditoría de éxito');

    foreach (['usuarios', 'productos; DROP TABLE productos'] as $catalog) {
        rejects(fn () => $repository->create($catalog, ['nombre' => 'Prueba'], 1), InvalidArgumentException::class);
    }
    foreach ([[], ['id' => 1], ['nombre' => []], ['estado' => 2], ['estado' => true], ['nombre' => new stdClass()]] as $invalid) {
        rejects(fn () => $repository->create('productos', $invalid, 1), InvalidArgumentException::class);
    }
    rejects(fn () => $repository->create('marcas', ['nombre' => 'Prueba'], 0), InvalidArgumentException::class);
    rejects(fn () => $repository->update('marcas', 0, ['nombre' => 'Prueba'], 1), InvalidArgumentException::class);

    // La transacción del servicio conserva el control de confirmar o deshacer.
    $pdo->beginTransaction();
    $outerId = $repository->create('marcas', ['nombre' => 'Exterior'], 1);
    rejects(fn () => $repository->create('marcas', ['nombre' => 'Fallida'], 99999), PDOException::class);
    ensure($pdo->inTransaction(), 'Se mantiene abierta la transacción exterior');
    ensure((int) $pdo->query("SELECT COUNT(*) FROM marcas WHERE id=$outerId")->fetchColumn() === 1, 'Se conserva el trabajo anterior');
    $pdo->rollBack();
    ensure((int) $pdo->query("SELECT COUNT(*) FROM marcas WHERE id=$outerId")->fetchColumn() === 0, 'Rollback exterior deshace el maestro');
    ensure((int) $pdo->query('SELECT COUNT(*) FROM auditoria_acciones')->fetchColumn() === $auditCount, 'Rollback exterior deshace también auditoría');
    echo 'OK: ' . $GLOBALS['checks'] . " comprobaciones de mantenimiento de maestros.\n";
} finally {
    $database->close();
}

<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Repositories\Masters\MasterDataRepository;

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    $pdo = $db->pdo;
    fixtures($pdo);
    $masters = new MasterDataRepository($pdo);
    $pdo->exec("INSERT INTO categorias_producto(id,nombre) VALUES(1,'Categoria')");
    $pdo->exec("INSERT INTO marcas(id,nombre) VALUES(1,'Marca')");
    $pdo->exec("UPDATE productos SET categoria_id=1,marca_id=1 WHERE id=1");
    $pdo->exec("INSERT INTO productos(id,codigo,nombre,estado) VALUES(3,'P-100%','Literal porcentaje',1),(4,'P_UNO','Literal subrayado',1),(5,'P!UNO','Literal admiracion',1),(6,'OFF','Inactivo',0)");
    $pdo->exec("INSERT INTO almacenes(id,sucursal_id,codigo,nombre,estado) VALUES(3,1,'OFF','Inactivo',0)");
    $pdo->exec("INSERT INTO sucursales(id,empresa_id,codigo,nombre) VALUES(2,1,'S2','Sucursal dos')");
    $pdo->exec("INSERT INTO almacenes(id,sucursal_id,codigo,nombre) VALUES(4,2,'A4','Almacen otra sucursal')");

    foreach (['empresas','sucursales','almacenes','clientes','vendedores','productos','unidades_medida','lotes','tipos_documento','departamentos','provincias','distritos','categorias_producto','marcas'] as $catalog) {
        $rows = $masters->search($catalog);
        ensure(count($rows) > 0 && isset($rows[0]['id'], $rows[0]['nombre']), "Catalog contract: $catalog");
        ensure($masters->find($catalog, (int) $rows[0]['id']) === $rows[0], "Lookup contract: $catalog");
        ensure($masters->find($catalog, 999999) === null, "Missing ID: $catalog");
    }
    ensure($masters->search('productos', 'M1')[0]['id'] === 1, 'Find product by code');
    ensure($masters->search('clientes', '00000001')[0]['id'] === 1, 'Find client by document');
    ensure($masters->search('vendedores', 'Vendedor')[0]['nombre'] === 'Vendedor', 'Seller label without NULL concatenation');
    ensure($masters->search('productos', 'Inactivo') === [], 'Inactive master excluded from selection');
    ensure((int) $masters->find('productos', 6)['estado'] === 0, 'Historical inactive master can be read');
    ensure(count($masters->search('almacenes', '', ['sucursal_id' => 1])) === 2, 'Branch filter and active selection');
    ensure($masters->search('almacenes', '', ['sucursal_id' => 2])[0]['id'] === 4, 'Other branch does not leak into selection');
    ensure(count($masters->search('lotes', '', ['producto_id' => 1])) === 2, 'Lots filtered by product; expiry decision is separate');
    ensure($masters->search('provincias', '', ['departamento_id' => 2])[0]['id'] === 2, 'Province parent filter');
    ensure($masters->search('distritos', '', ['provincia_id' => 1])[0]['id'] === 1, 'District parent filter');
    ensure(count($masters->search('productos', '', ['categoria_id' => 1, 'marca_id' => 1])) === 1, 'Combined master filters');
    ensure($masters->search('productos', '%')[0]['id'] === 3 && count($masters->search('productos', '%')) === 1, 'Percent searched literally');
    ensure($masters->search('productos', '_')[0]['id'] === 4 && count($masters->search('productos', '_')) === 1, 'Underscore searched literally');
    ensure($masters->search('productos', '!')[0]['id'] === 5, 'Escape character searched literally');
    ensure($masters->search('productos', "' OR 1=1 --") === [], 'SQL text is not executed');
    ensure($masters->search('productos', 'inexistente') === [], 'Empty search result');
    $all = $masters->search('productos');
    ensure($masters->search('productos', '', [], 2, 0) === array_slice($all, 0, 2), 'First bounded page');
    ensure($masters->search('productos', '', [], 2, 2) === array_slice($all, 2, 2), 'Second bounded page');
    ensure($masters->search('productos', '', [], 2, 999) === [], 'Offset beyond results');
    rejects(fn() => $masters->search('usuarios'), InvalidArgumentException::class);
    rejects(fn() => $masters->search('productos; DROP TABLE productos'), InvalidArgumentException::class);
    rejects(fn() => $masters->find('usuarios', 1), InvalidArgumentException::class);
    rejects(fn() => $masters->search('productos', '', ['estado' => 0]), InvalidArgumentException::class);
    rejects(fn() => $masters->search('clientes', '', ['empresa_id' => 1]), InvalidArgumentException::class);
    rejects(fn() => $masters->search('almacenes', '', ['sucursal_id' => '1 OR 1=1']), InvalidArgumentException::class);
    rejects(fn() => $masters->search('lotes', '', ['producto_id' => 0]), InvalidArgumentException::class);
    rejects(fn() => $masters->search('lotes', '', ['producto_id' => true]), InvalidArgumentException::class);
    rejects(fn() => $masters->search('productos', '', [], 101), InvalidArgumentException::class);
    rejects(fn() => $masters->search('productos', '', [], 0), InvalidArgumentException::class);
    rejects(fn() => $masters->search('productos', '', [], 10, -1), InvalidArgumentException::class);
    rejects(fn() => $masters->search('productos', str_repeat('x', 201)), InvalidArgumentException::class);
    rejects(fn() => $masters->find('productos', 0), InvalidArgumentException::class);
    ensure((int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 6, 'Search does not create masters');
    ensure((int) $pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn() === 0, 'Catalog queries do not create operations');
    echo 'Master data: ' . $GLOBALS['checks'] . " checks OK\n";
} finally {
    $db->close();
}

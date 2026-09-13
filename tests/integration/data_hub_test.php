<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\DTO\DataQuery;
use App\Repositories\DataHub\DataHubRepository;
use App\Repositories\Operations\DocumentRepository;
use App\Repositories\Operations\GuideRepository;
use App\Repositories\Operations\StockRepository;

$db = new TestDatabase();
try {
    $db->load('database/schemas/002_schema_v2.sql');
    fixtures($db->pdo);
    $hub = new DataHubRepository($db->pdo);
    $datasets = [
        'sales'=>[new DocumentRepository($db->pdo), docHeader(), lines()],
        'shipments'=>[new GuideRepository($db->pdo), guideHeader(), lines()],
        'inventory'=>[new StockRepository($db->pdo), stockHeader(), lines(true)],
    ];
    foreach ($datasets as $dataset => [$repository, $header, $details]) {
        $id = $repository->create($header, $details, 1);
        $query = new DataQuery(['dataset'=>$dataset]);
        ensure($hub->page($query)['items'] === [] && $hub->count($query) === 0, 'Borradores excluidos');
        $repository->markValidated($id, 1, 1);
        ensure($hub->count($query) === 0, 'Validados excluidos');
        $repository->markPublished($id, 2, 2);
        $result = $hub->page($query);
        ensure($result['total'] === 2 && count($result['items']) === 2 && $result['total_pages'] === 1, 'Se cuentan detalles publicados');
        ensure($hub->count($query) === $result['total'], 'Conteo comparte filtros');
        ensure(!isset($result['items'][0]['__header_id']) && !isset($result['items'][0]['__total']), 'No se filtran columnas internas');
        $pageOne = $hub->page(new DataQuery(['dataset'=>$dataset,'per_page'=>1,'page'=>1]));
        $pageTwo = $hub->page(new DataQuery(['dataset'=>$dataset,'per_page'=>1,'page'=>2]));
        ensure($pageOne['items'][0]['materialId'] !== $pageTwo['items'][0]['materialId'], 'Desempate por detalle evita repetir filas');
        ensure($pageOne['total_pages'] === 2 && $pageTwo['total'] === 2, 'Metadatos de paginación');
        $emptyPage = $hub->page(new DataQuery(['dataset'=>$dataset,'per_page'=>1,'page'=>3]));
        ensure($emptyPage['items'] === [] && $emptyPage['total'] === 2, 'Página fuera de rango conserva total');
        $filtered = new DataQuery(['dataset'=>$dataset,'from'=>'2026-09-11','to'=>'2026-09-11','sucursal'=>'1','producto'=>'1']);
        ensure($hub->count($filtered) === 1 && $hub->page($filtered)['items'][0]['materialId'] === 'M1', 'Filtros combinados e intervalo inclusivo');
        foreach ([['from'=>'2026-09-12'],['to'=>'2026-09-10'],['sucursal'=>999],['producto'=>999]] as $filter) {
            $none = $hub->page(new DataQuery(['dataset'=>$dataset]+$filter));
            ensure($none['items'] === [] && $none['total'] === 0 && $none['total_pages'] === 0, 'Filtros sin coincidencias');
        }
        foreach (['asc','desc'] as $direction) {
            $sorted = $hub->page(new DataQuery(['dataset'=>$dataset,'sort'=>'quantity','direction'=>$direction]));
            ensure((float) $sorted['items'][0]['quantity'] === ($direction === 'asc' ? 1.0 : 2.5), 'Orden numérico de cantidades');
        }
        // Los parámetros globales de HTTP no pueden alterar una consulta explícita.
        $_GET = ['from'=>'2099-01-01','producto'=>999];
        ensure($hub->count($query) === 2, 'Consulta independiente de GET');
        $_GET = [];
    }
    ensure($hub->count(new DataQuery(['dataset'=>'stock','almacen'=>1])) === 2, 'Stock por almacén');
    ensure($hub->count(new DataQuery(['dataset'=>'stock','almacen'=>2])) === 0, 'Otro almacén sin filas');
    ensure($hub->count(new DataQuery(['dataset'=>'stock','almacen'=>1,'sucursal'=>999])) === 0, 'Sucursal y almacén se combinan');
    foreach (['documents'=>'sales','guides'=>'shipments','stock'=>'inventory'] as $alias=>$canonical) {
        ensure((new DataQuery(['dataset'=>$alias]))->dataset === $canonical, 'Alias normalizado');
    }
    foreach ([['dataset'=>'unknown'],['dataset'=>[]],['from'=>'2026-02-30'],['from'=>'2026-2-01'],['from'=>'0999-01-01'],['from'=>[]],['from'=>'2026-09-12','to'=>'2026-09-11'],['page'=>0],['page'=>true],['page'=>1.5],['page'=>[]],['page'=>PHP_INT_MAX,'per_page'=>100],['per_page'=>101],['producto'=>-1],['sucursal'=>'1 OR 1=1'],['sort'=>'quantity; DROP TABLE productos'],['direction'=>'DESC; DELETE'],['almacen'=>1],['sort'=>'warehouseId'],['estado_registro'=>'BORRADOR']] as $invalid) {
        rejects(fn () => new DataQuery($invalid + ['dataset'=>'sales']), InvalidArgumentException::class);
    }
    rejects(fn () => new DataQuery(['dataset'=>'stock','sort'=>'documentNumber']), InvalidArgumentException::class);
    ensure((new DataQuery(['dataset'=>'sales','from'=>'','sucursal'=>'','direction'=>'ASC']))->direction === 'ASC', 'Opcionales vacíos y dirección normalizados');
    $immutable = new DataQuery(['dataset'=>'sales']);
    rejects(function () use ($immutable) {$immutable->sort = 'SQL arbitrario';}, Error::class);
    ensure((int) $db->pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn() === 2, 'Maestros conservados');
    echo 'OK: ' . $GLOBALS['checks'] . " comprobaciones del Data Hub.\n";
} finally {
    $db->close();
}

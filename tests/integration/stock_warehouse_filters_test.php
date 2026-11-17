<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Exceptions\HttpException;
use App\Services\{UnifiedStockService,MasterCatalogService,BayerCatalogService,ProductWarehouseService};

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/015_almacenes_referencia_productos.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;

    $warehouse=new ProductWarehouseService($pdo);
    ensure(count($warehouse->options())===2,'Almacenes activos disponibles para los dos editores');

    $masters=new MasterCatalogService($pdo);
    $masters->save('productos',[
        'codigo'=>'M1','nombre'=>'Producto 1',
        'tipo_art'=>'PRODUCTO','unidad_base_id'=>'1',
        'categoria_id'=>'','marca_id'=>'','estado'=>'1',
        'almacen_referencia_id'=>'1',
    ],1);
    $masters->save('productos',[
        'codigo'=>'M2','nombre'=>'Producto 2',
        'tipo_art'=>'PRODUCTO','unidad_base_id'=>'2',
        'categoria_id'=>'','marca_id'=>'','estado'=>'1',
        'almacen_referencia_id'=>'1',
    ],2);
    ensure($warehouse->find('PUCCHUN',1)===1 &&
           (int)$masters->find('productos',1)['almacen_referencia_id']===1,
           'Producto Pucchún guardado y reabierto con almacén de referencia');

    $catalog=new BayerCatalogService($pdo);
    $bayer=$catalog->saveWithPrice([
        'codigo_bayer'=>'BAY-200','nombre_bayer'=>'Demo Bayer 1 L',
        'marca'=>'Demo','inventario_accion'=>'SIN_CAMBIOS','almacen_referencia_id'=>'2',
    ]);
    ensure($warehouse->find('BAYER',$bayer)===2,
        'Bayer asigna almacén de referencia sin crear stock físico');

    $stock=new UnifiedStockService($pdo);
    $initial=$stock->browse([],15,1);
    ensure((int)$initial['summary']['total_productos']===3 &&
           (int)$initial['summary']['total_bayer']===1 &&
           (int)$initial['summary']['total_pucchun']===2,
        'Total de códigos individuales separado por catálogo');
    ensure($stock->browse(['almacen_id'=>1],15,1)['total']===2,
        'El almacén 1 muestra productos Pucchún asignados aunque no tengan reporte');
    $filtered=$stock->browse(['almacen_id'=>2],15,1);
    ensure($filtered['total']===1 && $filtered['rows'][0]['fuente']==='BAYER'
        && $filtered['rows'][0]['almacen']==='Almacen 2',
        'Almacén 2 filtra al producto Bayer realmente asignado');

    $warehouse->assign('BAYER',$bayer,1);
    ensure($stock->browse(['almacen_id'=>1,'fuente'=>'BAYER'],15,1)['total']===1
        && $stock->browse(['almacen_id'=>2,'fuente'=>'BAYER'],15,1)['total']===0,
        'Cambiar almacén de Bayer reubica su consulta sin duplicar existencias');
    rejects(fn()=>$warehouse->assign('BAYER',$bayer,99999),HttpException::class);
    ensure($warehouse->find('BAYER',$bayer)===1,
        'Almacén inexistente no modifica la relación anterior');

    // Dos almacenes físicos con un mismo producto; el total individual no se duplica.
    $hash=str_repeat('a',64);
    $pdo->exec("INSERT INTO stock_cabecera
       (id,fecha_stock,almacen_id,estado_registro,idempotency_key,request_hash)
       VALUES (9961,'2026-11-01',1,'PUBLICADO','stock-almacen-1','".$hash."'),
              (9962,'2026-11-02',2,'PUBLICADO','stock-almacen-2','".$hash."')");
    $pdo->exec("INSERT INTO stock_detalle
       (stock_id,fecha_stock,almacen_id,producto_id,lote_id,unidad_id,cantidad)
       VALUES (9961,'2026-11-01',1,1,1,1,2),
              (9962,'2026-11-02',2,1,1,1,8)");
    $both=$stock->browse([],15,1);
    ensure((int)$both['summary']['total_productos']===3 &&
           $both['total']===4,'Un SKU en dos almacenes genera dos filas pero un producto individual');
    ensure((int)$both['summary']['total_bayer']===1 &&
           (int)$both['summary']['total_pucchun']===2,
           'Conteo individual por origen se mantiene después del stock físico');
    $inOne=$stock->browse(['almacen_id'=>1,'fuente'=>'PUCCHUN'],15,1);
    $inTwo=$stock->browse(['almacen_id'=>2,'fuente'=>'PUCCHUN'],15,1);
    ensure($inOne['total']===2 && $inTwo['total']===1,
        'Cada almacén devuelve sus cantidades físicas más productos referenciales sin reporte');
    ensure((float)$inTwo['rows'][0]['cantidad']===8.0,
        'Cantidad de producto en almacén 2 no incluye las 2 unidades del almacén 1');

    $catalog->changeStatus($bayer,'DESACTIVAR');
    $warning=$stock->browse(['estado'=>'RIESGO','fuente'=>'BAYER'],15,1);
    ensure($warning['total']===1 &&
           (int)$warning['summary']['riesgo']===1,
        'Desactivado Bayer cuenta en el indicador unificado negativo/desactivado');
    ensure($stock->browse(['estado'=>'NEGATIVO','fuente'=>'BAYER'],15,1)['total']===1,
        'Enlaces antiguos a NEGATIVO se reconducen al indicador unificado');

    $warehouse->assign('BAYER',$bayer,'');
    ensure($warehouse->find('BAYER',$bayer)===null &&
           $stock->browse(['almacen_id'=>1,'fuente'=>'BAYER'],15,1)['total']===0,
           'Quitar referencia de almacén no borra el producto Bayer');

    $view=file_get_contents(dirname(__DIR__,2).'/app/Views/stock/index.php');
    ensure(str_contains($view,'name="almacen_id"') &&
           str_contains($view,'stock-product-totals') &&
           str_contains($view,'total_bayer') && str_contains($view,'total_pucchun'),
           'Pantalla stock filtra almacenes y muestra conteos individuales');
    ensure(!str_contains($view,'stock-header') &&
           !str_contains($view,'foreach ($rows as $r)'),
           'No se presenta la tabla duplicada de movimientos al final');
    $bayerForm=file_get_contents(dirname(__DIR__,2).'/app/Views/bayer_catalog/form.php');
    $masterForm=file_get_contents(dirname(__DIR__,2).'/app/Views/backoffice/master_form.php');
    ensure(str_contains($bayerForm,'name="almacen_referencia_id"') &&
           str_contains($masterForm,'record[<?=e($name)?>]'),
           'Los dos editores muestran el almacén en selector');

    echo 'WAREHOUSE FILTERS + INDIVIDUAL PRODUCTS OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

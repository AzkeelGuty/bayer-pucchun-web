<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Services\{BayerCatalogService,BayerUnifiedImportService,UnifiedStockService,BayerPriceImportService};
use App\Exceptions\HttpException;

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/015_almacenes_referencia_productos.sql');
    fixtures($db->pdo);

    $stock=new UnifiedStockService($db->pdo);
    $start=$stock->browse([],15,1);
    ensure($start['total']===2 && (int)$start['summary']['sin_dato']===2,
        'Productos Pucchún sin snapshot aparecen sin reporte, no en cero');

    $catalog=new BayerCatalogService($db->pdo);
    $rows=[
        ['codigo_bayer'=>'99000101','nombre_bayer'=>'PRODUCTO PRUEBA 12X1L BOT PE',
         'marca'=>'PRUEBA','cantidad'=>'5','tiene_columna_stock'=>'1',
         'periodo'=>'2026-11','hoja'=>'NOV_26','fila'=>'14'],
        ['codigo_bayer'=>'99000102','nombre_bayer'=>'PRODUCTO PRUEBA 12X1KG BAG PE',
         'marca'=>'PRUEBA','cantidad'=>'0','tiene_columna_stock'=>'1',
         'periodo'=>'2026-11','hoja'=>'NOV_26','fila'=>'15'],
    ];
    $prices=[
        ['codigo_bayer'=>'99000101','nombre_bayer'=>'PRODUCTO PRUEBA 12X1L BOT PE',
         'moneda'=>'PEN','precio_sugerido_sin_igv'=>'100.00','igv_porcentaje'=>'',
         'precio_sugerido_con_igv'=>'118.00','descuento_dealer_porcentaje'=>'',
         'vigencia_desde'=>'2026-11-02','vigencia_hasta'=>'','fila'=>'14'],
    ];
    $imported=$catalog->importRows($rows,'NOV_26.xlsx',$prices);
    ensure($imported['productos_nuevos']===2 && $imported['periodos']===2 &&
           $imported['precios']===1,'Carga mixta registra productos, stock y precio');
    ensure(count($catalog->publishedProducts())===2,
        'La API Bayer presenta inmediatamente los productos activos');
    ensure(count($catalog->publishedInventory())===2,
        'La API presenta cantidades mensuales sin botón de publicación');

    $priceRow=$db->pdo->query("SELECT x.igv_porcentaje,x.sugerido_con_igv
        FROM bayer_precios_referencia x JOIN bayer_productos_catalogo b ON b.id=x.producto_id
        WHERE b.codigo_bayer='99000101'")->fetch(PDO::FETCH_ASSOC);
    ensure((float)$priceRow['igv_porcentaje']===18.0 &&
           (float)$priceRow['sugerido_con_igv']===118.0,
        'IGV opcional se deduce de los importes sin inventar tasa');
    $repeat=$catalog->importRows($rows,'NOV_26.xlsx',$prices);
    ensure($repeat['productos_actualizados']===2 &&
       (int)$db->pdo->query('SELECT COUNT(*) FROM bayer_precios_referencia')->fetchColumn()===1,
        'Código Bayer y fecha hacen idempotente la actualización');

    $both=$stock->browse([],15,1);
    ensure($both['total']===4,'La consulta unificada muestra dos Pucchún y dos Bayer');
    ensure((int)$both['summary']['cero']===1 &&
           (int)$both['summary']['bajo']===1 &&
           (int)$both['summary']['sin_dato']===2,
        'Cuenta por separado stock bajo, cero y ausencia de reporte');
    ensure($stock->browse(['fuente'=>'BAYER'],15,1)['total']===2,
        'Filtro fuente Bayer no incluye Pucchún');
    ensure($stock->browse(['fuente'=>'PUCCHUN'],15,1)['total']===2,
        'Filtro fuente Pucchún no incluye Bayer');
    ensure($stock->browse(['estado'=>'SIN_STOCK'],15,1)['total']===1,
        'Filtro cero entrega solo cantidades registradas igual a 0');

    $hash=str_repeat('d',64);
    $db->pdo->exec("INSERT INTO stock_cabecera
        (id,fecha_stock,almacen_id,estado_registro,idempotency_key,request_hash)
        VALUES (9981,'2026-11-01',1,'PUBLICADO','unified-stock-test','".$hash."')");
    $db->pdo->exec("INSERT INTO stock_detalle
        (stock_id,fecha_stock,almacen_id,producto_id,lote_id,unidad_id,cantidad)
        VALUES(9981,'2026-11-01',1,1,1,1,4)");
    $physical=$stock->browse(['fuente'=>'PUCCHUN'],15,1);
    ensure((int)$physical['summary']['bajo']===1 &&
           (int)$physical['summary']['sin_dato']===1,
        'Pucchún usa último stock publicado y conserva otros productos sin reporte');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM stock_cabecera")->fetchColumn()===1,
        'Importación de Bayer no crea snapshots físicos Pucchún');

    for($i=3;$i<=21;$i++){
        $data=$rows[0];
        $data['codigo_bayer']=sprintf('9900%04d',$i);
        $data['fila']=(string)(15+$i);
        $catalog->importRows([$data],'TEST-PAGINACION.xlsx');
    }
    $first=$stock->browse(['fuente'=>'BAYER'],15,1);
    $second=$stock->browse(['fuente'=>'BAYER'],15,2);
    ensure($first['total']===21 && count($first['rows'])===15 &&
           count($second['rows'])===6,'Paginación SQL muestra 15 en primera página y 6 en segunda');

    $bad=$prices[0];$bad['codigo_bayer']='CODIGO-INEXISTENTE';
    rejects(fn()=>$catalog->importRows([$rows[0]],'INVALIDO.xlsx',[$bad]),HttpException::class);
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM bayer_precios_referencia')->fetchColumn()===1,
        'Error en precio revierte una importación conjunta y evita estados parciales');

    $base=dirname(__DIR__,2);
    $index=file_get_contents($base.'/app/Views/bayer_catalog/index.php');
    $import=file_get_contents($base.'/app/Views/bayer_catalog/import.php');
    $js=file_get_contents($base.'/public/assets/js/app.js');
    ensure(!str_contains($index,'Publicar todos en API') &&
           !str_contains($index,'Publicar API</button>') &&
           !str_contains($index,'Importar / actualizar precios Excel'),
        'El catálogo ya no muestra acciones separadas de publicación o precios');
    ensure(!str_contains($import,'type="checkbox"') &&
           str_contains($import,'fecha_precios') &&
           str_contains($import,'priceSample'),
        'Un único asistente permite hoja de precios sin checkbox');
    ensure(str_contains($js,'form.requestSubmit()') &&
           str_contains($js,"form.method.toLowerCase() !== 'get'"),
        'Selects de filtros GET ejecutan búsqueda automática sin tocar formularios POST');

    echo 'UNIFIED STOCK + AUTOMATIC BAYER API OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{
    $db->close();
}

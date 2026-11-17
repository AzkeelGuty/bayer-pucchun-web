<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Repositories\{DocumentRepository,GuideRepository,StockRepository};
use App\Services\{StockMovementService,UnifiedStockService,BayerCatalogService};
use App\Exceptions\HttpException;

if(!function_exists('has_role')){
    function has_role(string ...$roles): bool{return in_array('ADMIN',$roles,true);}
}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/013_calculos_totales_documentos_guias.sql');
    $db->load('database/migrations/009_guias_desde_documentos_y_pagos.sql');
    $db->load('database/migrations/010_numeracion_interna_automatica.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/015_almacenes_referencia_productos.sql');
    $db->load('database/migrations/017_movimientos_stock_publicados.sql');
    $db->load('database/migrations/018_productos_pucchun_precio_ajustes.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $pdo->exec("UPDATE tipos_documento SET codigo='FAC' WHERE id=1");

    $mov=new StockMovementService($pdo);
    ensure($mov->installed(),'La migración 017 habilita movimientos físicos');
    $stock=new StockRepository($pdo);
    $snapId=$stock->create(stockHeader('movimiento-kardex-test','2026-10-01'),[
       ['producto_id'=>1,'unidad_id'=>1,'lote_id'=>null,'cantidad'=>'15']
    ],1);
    $stock->markValidated($snapId,1,1);
    $stock->markPublished($snapId,2,1);
    [$snapshot,$base]=$mov->currentBalance(1,1,1);
    ensure($snapshot===$snapId&&$base===15,'Stock inicial Pucchún 15 unidades');

    $docs=new DocumentRepository($pdo);
    $id=$docs->create(docHeader('KARDEX-DOC-001'),[
       ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'4','valor_unitario'=>'10.00']
    ],1);
    $docs->markValidated($id,1,1);
    $pdo->beginTransaction();
    try{
        $docs->markPublished($id,2,1);
        $mov->issue('documentos',$id,1);
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    ensure($mov->currentBalance(1,1,1)[1]===11,
      'Factura PUBLICADA descuenta cuatro unidades físicamente');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM stock_movimientos WHERE tipo='SALIDA'")->fetchColumn()===1,
      'Una venta genera exactamente una salida auditable');
    $consolidated=new UnifiedStockService($pdo);
    $pucchun=$consolidated->reportRows(['fuente'=>'PUCCHUN']);
    ensure(count($pucchun)>=2 &&
        count(array_filter($pucchun,fn($r)=>$r['materialId']==='M1'&&(int)$r['quantity']===11))===1,
        'El reporte de Pucchún toma el saldo descontado');
    ensure((int)$consolidated->browse(['fuente'=>'PUCCHUN'],15,1)['summary']['disponible']>=1,
        'Los contadores de stock reflejan saldos');

    $guideResult=(new App\Services\GuideFromDocumentService($pdo))->generate($id,1);
    $guides=new GuideRepository($pdo);
    $gid=$guideResult['guide_id'];
    $guides->markValidated($gid,1,1);
    $pdo->beginTransaction();
    try{
        $guides->markPublished($gid,2,1);
        $mov->issue('guias',$gid,1);
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    ensure($mov->currentBalance(1,1,1)[1]===11,
        'La guía de la factura ya despachada no descuenta dos veces');

    // Una segunda venta no puede consumir lo que no hay.
    $id2=$docs->create(docHeader('KARDEX-DOC-002'),[
       ['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'12','valor_unitario'=>'1.00']
    ],1);
    $docs->markValidated($id2,1,1);
    $pdo->beginTransaction();
    try{
        $docs->markPublished($id2,2,1);
        rejects(fn()=>$mov->issue('documentos',$id2,1),HttpException::class);
    }finally{if($pdo->inTransaction())$pdo->rollBack();}
    ensure($mov->currentBalance(1,1,1)[1]===11,
      'No se vende por encima de saldo disponible');
    ensure($docs->find($id2)['header']['estado_registro']==='VALIDADO',
      'La venta sin stock no se publica parcialmente');

    $pdo->beginTransaction();
    try{
        $mov->reverse('documentos',$id,1);
        $mov->reverse('documentos',$id,1);
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    ensure($mov->currentBalance(1,1,1)[1]===15,
        'La anulación devuelve solo una vez las unidades al snapshot de origen');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM stock_movimientos WHERE tipo='REVERSION'")->fetchColumn()===1,
        'Reversión idempotente, sin duplicados');

    $newSnapshot=$stock->create(stockHeader('movimiento-kardex-next','2026-11-17'),[
       ['producto_id'=>1,'unidad_id'=>1,'lote_id'=>null,'cantidad'=>'20']
    ],1);
    $stock->markValidated($newSnapshot,1,1);
    $stock->markPublished($newSnapshot,2,1);
    ensure($mov->currentBalance(1,1,1)[1]===20,
        'Snapshot nuevo reconcilia las salidas del período anterior sin doble descuento');

    // El editor de productos Pucchún actualiza precio y stock por código local
    // y registra un ajuste sobre el snapshot vigente sin borrar otras existencias.
    $masters=new \App\Services\MasterCatalogService($pdo);
    $product=$pdo->query('SELECT codigo,nombre,unidad_base_id FROM productos WHERE id=1')
        ->fetch(PDO::FETCH_ASSOC);
    $masters->save('productos',[
        'codigo'=>(string)$product['codigo'],
        'nombre'=>(string)$product['nombre'],
        'tipo_art'=>'PRODUCTO',
        'unidad_base_id'=>(string)$product['unidad_base_id'],
        'categoria_id'=>'','marca_id'=>'',
        'almacen_referencia_id'=>'1',
        'precio_venta'=>'119.90',
        'stock_actual'=>'8',
        'estado'=>'1',
    ],1,1);
    ensure($mov->currentBalance(1,1,1)[1]===8,
        'El editor de Pucchún ajusta el stock y no modifica el snapshot publicado');
    ensure((string)$pdo->query('SELECT precio_venta FROM productos WHERE id=1')->fetchColumn()==='119.90',
        'Precio Pucchún actualizado en catálogo independiente');
    $detail=$masters->find('productos',1);
    ensure($detail['stock_actual']==='8' && $detail['precio_venta']==='119.90',
        'Editor muestra precio y saldo actual de su almacén');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM stock_movimientos WHERE tipo='AJUSTE'")->fetchColumn()===1,
        'Existe exactamente un ajuste trazable');
    $masters->save('productos',[
        'codigo'=>(string)$product['codigo'],
        'nombre'=>(string)$product['nombre'],
        'tipo_art'=>'PRODUCTO',
        'unidad_base_id'=>(string)$product['unidad_base_id'],
        'categoria_id'=>'','marca_id'=>'',
        'almacen_referencia_id'=>'1','precio_venta'=>'119.90',
        'stock_actual'=>'8','estado'=>'1',
    ],1,1);
    ensure((int)$pdo->query("SELECT COUNT(*) FROM stock_movimientos WHERE tipo='AJUSTE'")->fetchColumn()===1,
        'Repetir una edición sin cambios no duplica movimientos');
    ensure(count(array_filter($consolidated->reportRows(['fuente'=>'PUCCHUN']),
        fn($r)=>$r['materialId']===$product['codigo'] && (int)$r['quantity']===8))===1,
        'El consolidado exportado refleja el ajuste de stock de Pucchún');

    (new BayerCatalogService($pdo))->importRows([[
       'codigo_bayer'=>'99000321','nombre_bayer'=>'BAYER DEMO 12X1L BOT PE',
       'marca'=>'BAYER DEMO','cantidad'=>'0','periodo'=>'2026-11',
       'tiene_columna_stock'=>'1','hoja'=>'NOV_26','fila'=>'11'
    ]],'prueba.xlsx');
    $all=$consolidated->reportRows();
    ensure(count(array_filter($all,fn($r)=>$r['source']==='Bayer'))===1,
        'Exportar stock incluye Bayer y Pucchún en filas separadas');
    ensure(count(array_filter($all,fn($r)=>$r['source']==='Pucchún' && $r['materialId']==='M1'))===1,
        'Catálogo Pucchún conserva identidad y existencia propia');
    $zero=$consolidated->reportRows(['fuente'=>'BAYER','estado'=>'SIN_STOCK']);
    ensure(count($zero)===1 && $zero[0]['materialId']==='99000321',
        'Filtros Excel por origen y estado cero se aplican');

    $root=dirname(__DIR__,2);
    $export=file_get_contents($root.'/app/Controllers/ExportController.php');
    $view=file_get_contents($root.'/app/Views/backoffice/reports.php');
    $xlsx=file_get_contents($root.'/app/Services/SimpleXlsxExporter.php');
    ensure(str_contains($export,'reportRows($filters)') &&
        str_contains($view,'report-stock-stats') &&
        str_contains($view,'report-stock-source'),
        'Reportes usan el consolidado e indicadores por origen');
    ensure(str_contains($xlsx,'$baseCol=max(0,$columnCount-2)') &&
        str_contains($xlsx,'$maxW=52') &&
        str_contains($xlsx,'$banner1=array_fill(0,$columnCount'),
        'Logos de Excel se anclan al encabezado, no fuera de la tabla');

    echo 'STOCK MOVEMENTS AND CONSOLIDATED EXPORT OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

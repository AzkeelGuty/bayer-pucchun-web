<?php
declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Services\BayerCatalogService;
use App\Exceptions\HttpException;

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/015_almacenes_referencia_productos.sql');
    fixtures($db->pdo);
    $service=new BayerCatalogService($db->pdo);

    $first=[
        'codigo_bayer'=>'98765432',
        'nombre_bayer'=>'PRODUCTO DE PRUEBA 12X1L BOT PE',
        'marca'=>'MARCA PRUEBA',
        'precio_sin_igv'=>'100.00',
        'precio_igv'=>'18',
        'precio_con_igv'=>'118.00',
        'precio_descuento'=>'8',
        'precio_vigencia_desde'=>'2026-11-16',
        'precio_vigencia_hasta'=>'',
        'inventario_accion'=>'SIN_CAMBIOS',
    ];
    $id=$service->saveWithPrice($first);
    ensure($id>0,'Se crea producto Bayer junto con su primer precio');
    $product=$service->find($id);
    ensure($product['codigo_bayer']==='98765432',
        'Producto vinculado al código oficial Bayer y no a otro identificador');
    $prices=$service->prices($id);
    ensure(count($prices)===1 && (float)$prices[0]['sugerido_con_igv']===118.0,
        'Guarda precio neto/IGV/final con su fecha de vigencia');
    ensure(count($service->publishedPrices())===1,
        'Precio manual disponible automáticamente en la API Bayer');

    $updated=$first;
    $updated['nombre_bayer']='PRODUCTO DE PRUEBA EDICIÓN 12X1L';
    $updated['precio_sin_igv']='120.00';
    $updated['precio_con_igv']='141.60';
    $service->saveWithPrice($updated,$id);
    $prices=$service->prices($id);
    ensure(count($prices)===1 && (float)$prices[0]['sugerido_con_igv']===141.60,
        'Mismo codigo_bayer y misma fecha actualizan la fila sin duplicarla');
    ensure($service->find($id)['nombre_bayer']==='PRODUCTO DE PRUEBA EDICIÓN 12X1L',
        'Datos generales y precio se actualizan de forma coherente');

    $newDate=$updated;
    $newDate['precio_vigencia_desde']='2026-12-01';
    $newDate['precio_sin_igv']='130.00';
    $newDate['precio_con_igv']='153.40';
    $newDate['inventario_accion']='CANTIDAD';
    $newDate['inventario_periodo']='2026-11';
    $newDate['inventario_cantidad']='0';
    $service->saveWithPrice($newDate,$id);
    $prices=$service->prices($id);
    ensure(count($prices)===2 && (float)$prices[0]['sugerido_con_igv']===153.40 &&
        (float)$prices[1]['sugerido_con_igv']===141.60,
        'Nueva fecha conserva la vigencia anterior del precio');
    $product=$service->find($id);
    ensure($product['stock_periodo']==='2026-11' &&
        (int)$product['stock_reportado']===0 && $product['stock_estado']==='SIN_STOCK',
        'Edición mensual permite cantidad cero sin confundirla con sin dato');
    ensure(count($service->history($id))===1,
        'Actualización manual registra un histórico de inventario');

    $older=[
        'codigo_bayer'=>'98765432','nombre_bayer'=>'PRODUCTO PRUEBA EDITADO',
        'marca'=>'MARCA PRUEBA','inventario_accion'=>'CANTIDAD',
        'inventario_periodo'=>'2026-09','inventario_cantidad'=>'8',
        'precio_vigencia_desde'=>'2026-11-16',
    ];
    $service->saveWithPrice($older,$id);
    ensure($service->find($id)['stock_periodo']==='2026-11' &&
        (int)$service->find($id)['stock_reportado']===0,
        'Editar una fecha de inventario anterior no sobreescribe el mes más reciente');
    ensure(count($service->prices($id))===2,
        'Editar inventario sin importes no elimina ni modifica precios');
    ensure(count($service->history($id))===2,
        'Conserva ambos reportes mensuales Bayer');

    $noData=$older;
    $noData['inventario_accion']='SIN_DATO';
    $noData['inventario_periodo']='2026-12';
    unset($noData['inventario_cantidad']);
    $service->saveWithPrice($noData,$id);
    ensure($service->find($id)['stock_reportado']===null &&
        $service->find($id)['stock_estado']==='SIN_DATO',
        'La ausencia de dato es NULL y no cero');

    $disabled=$noData;
    $disabled['inventario_accion']='DESACTIVADO';
    $disabled['inventario_periodo']='2027-01';
    $service->saveWithPrice($disabled,$id);
    ensure($service->find($id)['estado_catalogo']==='INACTIVO' &&
        $service->publishedProducts()===[],
        'Desactivación mensual retira el producto de la API activa sin borrar históricos');

    $before=$service->find($id);
    $wrong=$older;
    $wrong['codigo_bayer']='OTROCODIGO';
    rejects(fn()=>$service->saveWithPrice($wrong,$id),HttpException::class);
    ensure($service->find($id)===$before,
        'La edición no permite cambiar el identificador Bayer con precio/historial asociado');
    $invalid=$older;
    $invalid['precio_sin_igv']='100';
    $invalid['precio_igv']='18';
    $invalid['precio_con_igv']='100';
    rejects(fn()=>$service->saveWithPrice($invalid,$id),HttpException::class);
    ensure($service->find($id)===$before,
        'Precio inconsistente rechaza la edición completa antes de cambiar el producto');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn()===0 &&
        (int)$db->pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn()===2,
        'Precio e inventario Bayer nunca alteran el stock o catálogo físico Pucchún');

    $root=dirname(__DIR__,2);
    $form=file_get_contents($root.'/app/Views/bayer_catalog/form.php');
    $detail=file_get_contents($root.'/app/Views/bayer_catalog/detail.php');
    $controller=file_get_contents($root.'/app/Controllers/BayerCatalogController.php');
    $styles=file_get_contents($root.'/public/assets/css/app.css');
    $scripts=file_get_contents($root.'/public/assets/js/app.js');
    foreach(['precio_sin_igv','precio_con_igv','precio_igv','precio_descuento',
             'precio_vigencia_desde','precio_vigencia_hasta','inventario_accion'] as $field)
        ensure(str_contains($form,'name="'.$field.'"'),'Campo editable '.$field.' disponible');
    ensure(str_contains($controller,'saveWithPrice($_POST'),
        'El controlador persiste producto/precio en una sola operación');
    ensure(str_contains($detail,"'&precio_fecha='") &&
        str_contains($form,'precio_fecha='),
        'Permite elegir una vigencia histórica para editar desde detalle y formulario');
    foreach([
        'app/Views/bayer_catalog/form.php',
        'app/Views/bayer_catalog/import.php',
        'app/Views/bayer_catalog/detail.php',
        'app/Views/backoffice/homologation_form.php',
        'app/Views/backoffice/master_form.php',
        'app/Views/backoffice/master_import.php',
        'app/Views/documentos/form.php',
        'app/Views/guias/form.php',
        'app/Views/stock/form.php',
    ] as $path){
        $html=file_get_contents($root.'/'.$path);
        ensure(str_contains($html,'has-back-navigation') &&
            str_contains($html,'btn-back-list') &&
            str_contains($html,'bi-arrow-left'),
            'Botón Volver consistente y con flecha en '.$path);
    }
    ensure(str_contains($styles,'.module-header.has-back-navigation') &&
        str_contains($styles,'order:-1') &&
        str_contains($styles,'background:var(--bp-blue)'),
        'Retorno se muestra a la izquierda con fondo azul corporativo');
    ensure(str_contains($scripts,'data-bayer-price-editor') &&
        str_contains($scripts,'data-bayer-stock-action'),
        'Cálculos de precios y selector de stock funcionan por delegación');

    echo 'BAYER PRODUCT EDITOR + NAVIGATION OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

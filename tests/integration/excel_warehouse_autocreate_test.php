<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Exceptions\HttpException;
use App\Services\{
    WarehouseExcelHeader,WarehouseExcelResolver,ProductWarehouseService,
    MasterCatalogService,BayerCatalogService,BayerWorkbookReader,
    BayerPriceWorkbookReader,SimpleXlsxTable
};

function warehouseRows(string $name,string $code='P-ALM-01'): array
{
    return [[
        'codigo'=>$code,'nombre'=>'Producto importado con almacén',
        'tipo_art'=>'PRODUCTO','unidad_codigo'=>'KG','categoria'=>'Agrícola',
        'marca'=>'Bayer','estado'=>'1',
        'almacen_nombre'=>$name,'almacen_codigo'=>'','sucursal_codigo'=>'S1',
    ]];
}

$matrix=[
    1=>[1=>'REPORTE BAYER'],
    3=>[1=>'Almacén',2=>'Almacén Nuevo de Prueba'],
    4=>[1=>'Sucursal Código:',2=>'S1'],
    9=>[1=>'CODIGO',2=>'PRODUCTO / PRESENTACIÓN',3=>'MARCA',4=>'CANTIDAD / UNIDADES',5=>'PERIODO'],
    10=>[1=>'99008764',2=>'Producto prueba Bayer 1L',3=>'DEMO',4=>'0',5=>'2026-09'],
];
$meta=WarehouseExcelHeader::metadata($matrix,9);
ensure($meta['almacen_nombre']==='Almacén Nuevo de Prueba' &&
       $meta['sucursal_codigo']==='S1',
       'Detecta nombre completo y sucursal en encabezado superior Bayer');
$parsed=(new BayerWorkbookReader())->parseMatrix($matrix,'SET_26');
ensure(count($parsed)===1 &&
       $parsed[0]['almacen_nombre']==='Almacén Nuevo de Prueba',
       'El lector Bayer transmite almacén desde cabecera a producto');
$matrix[9][6]='Almacenamiento';
$matrix[10][6]='Almacén Alterno por Fila';
$parsed=(new BayerWorkbookReader())->parseMatrix($matrix,'SET_26');
ensure($parsed[0]['almacen_nombre']==='Almacén Alterno por Fila',
       'La columna de almacén tiene prioridad frente al encabezado general');

$pricesMatrix=[
    1=>[1=>'Almacén: Almacén Precios'],
    2=>[1=>'Sucursal Código:',2=>'S1'],
    4=>[1=>'codigo_bayer',2=>'precio_sugerido_sin_igv',3=>'igv_porcentaje',
          4=>'precio_sugerido_con_igv',5=>'vigencia_desde'],
    5=>[1=>'99008764',2=>'100',3=>'18',4=>'118',5=>'2026-11-16'],
];
$priceParsed=(new BayerPriceWorkbookReader())->parseMatrix($pricesMatrix,'PRECIOS');
ensure(count($priceParsed)===1 &&
       $priceParsed[0]['almacen_nombre']==='Almacén Precios',
       'Las listas comerciales también detectan almacén de encabezado');

$tmp=tempnam(sys_get_temp_dir(),'pucchun-warehouse-');
$zip=new ZipArchive();
$zip->open($tmp,ZipArchive::OVERWRITE);
$matrixXml=[
    [1=>'',2=>''], // metadatos separados de tabla
    [1=>'Almacén',2=>'Almacén Importación'],
    [1=>'Sucursal código',2=>'S1'],
    [1=>''],
    [1=>'codigo',2=>'nombre',3=>'unidad_codigo',4=>'estado'],
    [1=>'P-ALM-04',2=>'Producto Pucchún importado',3=>'KG',4=>'1'],
];
$xml='<?xml version="1.0"?>'
    .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
foreach($matrixXml as $index=>$row){
    $n=$index+1;$xml.='<row r="'.$n.'">';
    foreach($row as $col=>$value){
        $ref=chr(64+$col).$n;
        $xml.='<c r="'.$ref.'" t="inlineStr"><is><t>'.
              htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').
              '</t></is></c>';
    }
    $xml.='</row>';
}
$xml.='</sheetData></worksheet>';
$zip->addFromString('xl/worksheets/sheet1.xml',$xml);
$zip->close();
try{
    $read=(new SimpleXlsxTable())->readFile(
        $tmp,MasterCatalogService::definitions()['productos']['import']);
    ensure(count($read)===1 && $read[0]['almacen_nombre']==='Almacén Importación'
        && $read[0]['sucursal_codigo']==='S1',
        'Pucchún reconoce encabezado en fila cinco, con almacén y sucursal superiores');
}finally{@unlink($tmp);}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/015_almacenes_referencia_productos.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $masters=new MasterCatalogService($pdo);
    $relation=new ProductWarehouseService($pdo);
    $warehouseResolver=new WarehouseExcelResolver($pdo);

    $before=(int)$pdo->query('SELECT COUNT(*) FROM almacenes')->fetchColumn();
    $preview=$warehouseResolver->inspect([
        'almacen_nombre'=>'Almacén Central Pucchún',
        'sucursal_codigo'=>'S1',
    ]);
    ensure($preview['action']==='create' &&
        (int)$pdo->query('SELECT COUNT(*) FROM almacenes')->fetchColumn()===$before,
        'Previsualizar un almacén nuevo no escribe en BD');

    $result=$masters->importRows('productos',warehouseRows('Almacén Central Pucchún'));
    ensure($result['inserted']===1,
        'Pucchún crea producto y almacén nuevo en una transacción');
    $after=(int)$pdo->query('SELECT COUNT(*) FROM almacenes')->fetchColumn();
    ensure($after===$before+1,'El almacén nuevo se registra una sola vez');
    $warehouse=$warehouseResolver->inspect([
        'almacen_nombre'=>'almacen central pucchun','sucursal_codigo'=>'s1',
    ]);
    ensure($warehouse['action']==='existing',
        'El nombre completo con cambios de tildes y mayúsculas se reconoce');
    $productId=(int)$pdo->query("SELECT id FROM productos WHERE codigo='P-ALM-01'")->fetchColumn();
    ensure($relation->find('PUCCHUN',$productId)===$warehouse['id'],
        'Producto importado Pucchún apunta al almacén correcto');
    $masters->importRows('productos',warehouseRows('ALMACEN CENTRAL PUCCHUN'));
    ensure((int)$pdo->query('SELECT COUNT(*) FROM almacenes')->fetchColumn()===$after,
        'Repetir importación no duplica almacenes existentes');
    rejects(fn()=>$warehouseResolver->inspect([
        'almacen_nombre'=>'Almacén Central Pucchu','sucursal_codigo'=>'S1',
    ]),HttpException::class);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM almacenes')->fetchColumn()===$after,
        'Una letra mal escrita no crea otro almacén casi idéntico');

    $catalog=new BayerCatalogService($pdo);
    $bayerRow=[
        'codigo_bayer'=>'99008764','nombre_bayer'=>'Producto prueba Bayer 1L',
        'marca'=>'PRUEBA','cantidad'=>'0','periodo'=>'2026-09',
        'tiene_columna_stock'=>'1','hoja'=>'SET_26','fila'=>'10',
        'almacen_nombre'=>'ALMACEN CENTRAL PUCCHUN','sucursal_codigo'=>'S1',
    ];
    $catalog->importRows([$bayerRow],'Bayer test.xlsx');
    $bayerId=(int)$pdo->query("SELECT id FROM bayer_productos_catalogo WHERE codigo_bayer='99008764'")->fetchColumn();
    ensure($relation->find('BAYER',$bayerId)===$warehouse['id'],
        'Bayer reutiliza el mismo almacén existente sin alterar stock físico');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn()===0,
        'Referencias de almacén no generan stock físico Pucchún');

    $priceRow=[
        'codigo_bayer'=>'99008764','nombre_bayer'=>'Producto prueba Bayer 1L',
        'moneda'=>'PEN','precio_sugerido_sin_igv'=>'100',
        'igv_porcentaje'=>'18','precio_sugerido_con_igv'=>'118',
        'descuento_dealer_porcentaje'=>'',
        'vigencia_desde'=>'2026-11-16','vigencia_hasta'=>'',
        'almacen_nombre'=>'Almacén Precios Nuevo','sucursal_codigo'=>'S1','fila'=>'2',
    ];
    (new \App\Services\BayerPriceImportService($pdo))
        ->importRows([$priceRow],'Lista precios test.xlsx');
    ensure($relation->find('BAYER',$bayerId)!==$warehouse['id'],
        'Excel de precios puede actualizar almacén asociado al producto existente');

    $wrong=warehouseRows('Almacén Fallido');
    $wrong[]=[
        'codigo'=>'P-INVALIDO','nombre'=>'Fila de error','unidad_codigo'=>'NO_EXISTE',
        'almacen_nombre'=>'Almacén Fallido','sucursal_codigo'=>'S1',
    ];
    rejects(fn()=>$masters->importRows('productos',$wrong),HttpException::class);
    $count=(int)$pdo->query("SELECT COUNT(*) FROM almacenes WHERE nombre='Almacén Fallido'")->fetchColumn();
    ensure($count===0,'Si falla un producto, se revierte también la creación del almacén');

    $base=dirname(__DIR__,2);
    $bayerTpl=file_get_contents($base.'/app/Controllers/BayerCatalogController.php');
    $master=file_get_contents($base.'/app/Services/MasterCatalogService.php');
    $xlsx=file_get_contents($base.'/app/Services/SimpleXlsxTable.php');
    ensure(str_contains($bayerTpl,"'almacen_nombre'=>") &&
        str_contains($bayerTpl,"'sucursal_codigo'=>") &&
        str_contains($bayerTpl,"'igv_porcentaje'=>"),
        'Plantilla Bayer contiene almacén, sucursal, precios e IGV');
    ensure(str_contains($master,"'almacen_nombre'=>") &&
        str_contains($master,"'unidad_codigo'=>"),
        'Plantilla Pucchún contiene almacén y su unidad de catálogo');
    ensure(str_contains($xlsx,"templateStyles") &&
        str_contains($xlsx,'Instrucciones') &&
        str_contains($xlsx,'No se reconocieron los encabezados'),
        'Plantillas distinguen carga/instrucciones y el importador detecta cabeceras');

    echo 'WAREHOUSE AUTO RESOLVE AND TEMPLATES OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

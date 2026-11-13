<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Services\{BayerCatalogService,BayerWorkbookReader,PucchunStockSummaryService};
use App\Exceptions\HttpException;

$reader=new BayerWorkbookReader();
$matrix=[
    1=>[1=>'REPORTE DE INVENTARIOS CANAL - CLIENTE'],
    5=>[8=>'Empresa',9=>'BAYER'],
    13=>[1=>'Year',2=>'Month',3=>'Country',9=>'CODIGO',
        10=>'PRODUCTO / PRESENTACION',11=>'MARCA',12=>'CANTIDAD / UNIDADES'],
    14=>[1=>'2026',2=>'9',9=>'86216353',10=>'ABACUS EC18 4X4L BOT PE',11=>'ABACUS',12=>'274'],
    15=>[1=>'2026',2=>'9',9=>'84939862',10=>'ALIETTE WG80 12X1KG BAG PE',11=>'ALIETTE',12=>'-'],
    16=>[1=>'2026',2=>'9',9=>'91113516',10=>'BAYFOLAN (11-8-6) 12X1L BOT PE',11=>'BAYFOLAN',12=>''],
    17=>[1=>'2026',2=>'9',9=>'91957137',10=>'AMINOFOL PLUS SL51 15X1L BOT PE',11=>'AMINOFOL',12=>'0'],
];
$rows=$reader->parseMatrix($matrix,'SET_26');
ensure(count($rows)===4,'Se reconoce la cabecera situada en la fila 13');
ensure($rows[0]['codigo_bayer']==='86216353','El identificador Bayer se lee sin otro código');
ensure($rows[0]['periodo']==='2026-09','Periodo detectado de columnas Year y Month');
ensure($rows[1]['cantidad']==='-','El guion es distinto de cero');
ensure($rows[2]['cantidad']==='','Celda vacía se mantiene vacía');
ensure($rows[3]['cantidad']==='0','El cero se mantiene como cero');
ensure($reader->parseMatrix([1=>[1=>'Sin productos']],'INICIO')===null,
    'Se omiten hojas de portada sin cabecera Bayer');

foreach([
    ['274','CON_STOCK',274],
    ['0','SIN_STOCK',0],
    ['','SIN_DATO',null],
    ['—','DESACTIVADO',null],
    ['-','DESACTIVADO',null]
] as [$value,$status,$quantity]){
    $state=BayerCatalogService::marker($value);
    ensure($state['estado']===$status && $state['cantidad']===$quantity,
        'Distinción correcta de cantidad '.$value);
}
rejects(fn()=>BayerCatalogService::marker('3.5'),HttpException::class);
rejects(fn()=>BayerCatalogService::marker('SIN STOCK'),HttpException::class);

// XLSX original con varias hojas. No usa la plantilla de homologación.
$temp=tempnam(sys_get_temp_dir(),'bayer-report-');
$zip=new ZipArchive();
$zip->open($temp,ZipArchive::OVERWRITE);
$zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="SET_26" sheetId="1"/><sheet name="OCT_26" sheetId="2"/></sheets></workbook>');
$asXml=static function(array $data):string{
    $xml='<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach($data as $rowNumber=>$cols){
        $xml.='<row r="'.$rowNumber.'">';
        foreach($cols as $col=>$value){
            $letter='';
            $colN=(int)$col;
            while($colN>0){$colN--;$letter=chr(65+$colN%26).$letter;$colN=intdiv($colN,26);}
            $xml.='<c r="'.$letter.$rowNumber.'" t="inlineStr"><is><t>'.
                htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
        }
        $xml.='</row>';
    }
    return $xml.'</sheetData></worksheet>';
};
$zip->addFromString('xl/worksheets/sheet1.xml',$asXml($matrix));
$zip->addFromString('xl/worksheets/sheet2.xml',$asXml([
    9=>[1=>'CODIGO',2=>'PRODUCTO / PRESENTACION',3=>'MARCA',4=>'CANTIDAD / UNIDADES'],
    10=>[1=>'86216353',2=>'ABACUS EC18 4X4L BOT PE',3=>'ABACUS',4=>'0'],
]));
$zip->close();
$multi=$reader->readFile($temp);
@unlink($temp);
ensure(count($multi)===5,'Lee todas las pestañas con contenido válido');
ensure($multi[4]['periodo']==='2026-10','Detecta mes OCT_26 del nombre de pestaña');

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    fixtures($db->pdo);
    $service=new BayerCatalogService($db->pdo);
    $pucchunStock=new PucchunStockSummaryService($db->pdo);
    ensure($pucchunStock->latest()===[],'Pucchún empieza sin snapshots publicados');
    $result=$service->importRows($multi,'inventario_canal_cliente.xlsx');
    ensure($result['productos_nuevos']===4 && $result['periodos']===5,
        'Importación transaccional de cuatro productos y cinco reportes mensuales');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn()===2,
        'No se crean productos Pucchún a partir del reporte Bayer');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM stock_cabecera')->fetchColumn()===0,
        'No se genera stock de almacén Pucchún desde Bayer');
    $listed=$service->list([],20,0);
    ensure($listed['total']===4,'El catálogo independiente contiene cuatro productos');
    $byCode=[];
    foreach($listed['rows'] as $product)$byCode[$product['codigo_bayer']]=$product;
    ensure($byCode['86216353']['stock_periodo']==='2026-10'
           && $byCode['86216353']['stock_estado']==='SIN_STOCK',
        'La cantidad de octubre reemplaza a septiembre en la vista vigente');
    ensure($byCode['84939862']['stock_estado']==='DESACTIVADO',
        'El guion de septiembre desactiva el producto de ese período');
    ensure($byCode['91113516']['stock_estado']==='SIN_DATO' &&
           $byCode['91113516']['stock_reportado']===null,
        'Stock Bayer vacío permanece NULL');
    ensure($byCode['91957137']['stock_estado']==='SIN_STOCK' &&
           (int)$byCode['91957137']['stock_reportado']===0,
        'Cero reportado significa Sin stock, no Sin dato');
    ensure(count($service->history((int)$byCode['86216353']['id']))===2,
        'Los dos meses se mantienen en histórico y no se suman');
    $previous=$service->importRows([$rows[0]],'reporte-sep.xlsx');
    ensure($previous['productos_actualizados']===1,'Una hoja anterior actualiza solo su período');
    $product=$service->find((int)$byCode['86216353']['id']);
    ensure($product['stock_periodo']==='2026-10','Carga de mes anterior no sobrescribe el último stock');

    rejects(fn()=>$service->delete((int)$byCode['86216353']['id'],'CODIGO INCORRECTO'),HttpException::class);
    $service->changeStatus((int)$byCode['86216353']['id'],'PUBLICAR');
    ensure(count($service->publishedProducts())===1,'API Bayer solo entrega productos publicados');
    ensure(count($service->publishedInventory())===2,'API Bayer entrega histórico separado de inventario');
    $service->changeStatus((int)$byCode['86216353']['id'],'DESACTIVAR');
    ensure(count($service->publishedProducts())===0,'Desactivar retira publicación API');
    $service->changeStatus((int)$byCode['86216353']['id'],'ACTIVAR');

    $pricing=$service->importBundledPrices();
    ensure($pricing['precios']===99,'Se cargan 99 precios de la lista Dealer Bayer');
    $record=$db->pdo->query("SELECT p.codigo_bayer,x.sugerido_sin_igv,x.igv_porcentaje,
        x.sugerido_con_igv,x.descuento_dealer
        FROM bayer_precios_referencia x
        JOIN bayer_productos_catalogo p ON p.id=x.producto_id
        WHERE p.codigo_bayer='86216353'")->fetch(PDO::FETCH_ASSOC);
    ensure($record!==false && $record['sugerido_sin_igv']==='207.95'
        && $record['sugerido_con_igv']==='245.38','Precio ABACUS coincide con el PDF');
    $zeroTax=$db->pdo->query("SELECT x.igv_porcentaje FROM bayer_precios_referencia x
        JOIN bayer_productos_catalogo p ON p.id=x.producto_id
        WHERE p.codigo_bayer='91957137'")->fetchColumn();
    ensure((float)$zeroTax===0.0,'Productos con IGV 0 % conservan su tasa');
    $service->importBundledPrices();
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM bayer_precios_referencia')->fetchColumn()===99,
        'Reimportación de precios no duplica referencias');
    $id=(int)$byCode['86216353']['id'];
    $service->delete($id,'86216353');
    rejects(fn()=>$service->find($id),HttpException::class);
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM bayer_inventario_periodos WHERE producto_id=$id")->fetchColumn()===0,
        'La eliminación confirmada borra el histórico Bayer vinculado');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn()===2,
        'Eliminar un producto Bayer nunca borra productos Pucchún');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE version=14')->fetchColumn()===1,
        'Migración 014 idempotente');
    echo 'BAYER NATIVE CATALOG OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

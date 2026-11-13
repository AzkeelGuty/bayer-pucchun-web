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

// El orden de workbook.xml es independiente del número físico sheetN.xml.
// También se leen las partes de texto enriquecido inline <is><r><t>.
$reorderedFile=tempnam(sys_get_temp_dir(),'bayer-rels-');
$reordered=new ZipArchive();
$reordered->open($reorderedFile,ZipArchive::OVERWRITE);
$reordered->addFromString('xl/workbook.xml', '<?xml version="1.0"?>'
    .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    .'<sheets><sheet name="OCT_26" sheetId="2" r:id="rOct"/>'
    .'<sheet name="SET_26" sheetId="1" r:id="rSet"/></sheets></workbook>');
$reordered->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rOct" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
    .'<Relationship Id="rSet" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    .'</Relationships>');
$reordered->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?>'
    .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
    .'<row r="13"><c r="A13" t="inlineStr"><is><t>CODIGO</t></is></c>'
    .'<c r="B13" t="inlineStr"><is><t>PRODUCTO / PRESENTACION</t></is></c>'
    .'<c r="C13" t="inlineStr"><is><t>CANTIDAD / UNIDADES</t></is></c></row>'
    .'<row r="14"><c r="A14" t="inlineStr"><is><t>86216353</t></is></c>'
    .'<c r="B14" t="inlineStr"><is><r><t>ABACUS </t></r><r><t>PE</t></r></is></c>'
    .'<c r="C14" t="inlineStr"><is><r><t>27</t></r><r><t>4</t></r></is></c></row>'
    .'</sheetData></worksheet>');
$reordered->addFromString('xl/worksheets/sheet2.xml',$asXml([
    1=>[1=>'CODIGO',2=>'PRODUCTO / PRESENTACION',3=>'CANTIDAD / UNIDADES'],
    2=>[1=>'86216353',2=>'ABACUS PE',3=>'0'],
]));
$reordered->close();
$mapped=$reader->readFile($reorderedFile);
@unlink($reorderedFile);
ensure(count($mapped)===2,'Se importan dos pestañas asociadas por relaciones OOXML');
ensure($mapped[0]['periodo']==='2026-10' && $mapped[0]['cantidad']==='0',
    'La hoja OCT se identifica por r:id y no por el número sheet2.xml');
ensure($mapped[1]['periodo']==='2026-09' && $mapped[1]['cantidad']==='274',
    'La hoja SET mantiene su mes y concatenación de cantidad rich text');
ensure($mapped[1]['nombre_bayer']==='ABACUS PE','Se concatena el nombre inline rich text');


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

    // Fecha de inventario manda sobre el ID de creación al elegir stock vigente.
    $hash=str_repeat('a',64);
    $insertHead=$db->pdo->prepare(
        'INSERT INTO stock_cabecera(id,fecha_stock,almacen_id,estado_registro,idempotency_key,request_hash)
         VALUES(?,?,?,?,?,?)');
    $insertHead->execute([9001,'2026-10-01',1,'PUBLICADO','bayer-stock-new',$hash]);
    $insertHead->execute([9002,'2026-09-01',1,'PUBLICADO','bayer-stock-old',$hash]);
    $insertLine=$db->pdo->prepare(
        'INSERT INTO stock_detalle(stock_id,fecha_stock,almacen_id,producto_id,lote_id,unidad_id,cantidad)
         VALUES(?,?,?,?,?,?,?)');
    $insertLine->execute([9001,'2026-10-01',1,1,1,1,50]);
    $insertLine->execute([9002,'2026-09-01',1,1,1,1,10]);
    $latest=$pucchunStock->latest();
    ensure(count($latest)===1 && $latest[0]['stockDate']==='2026-10-01'
           && (float)$latest[0]['quantity']===50.0,
        'El último stock Pucchún se elige por fecha, aunque su ID sea menor');
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

    // Una importación carga hasta 10 mil filas, pero el histórico publicado puede superarlas.
    $bulk=[];
    for($i=1;$i<=1001;$i++){
        $code=sprintf('CI-%04d',$i);
        $bulk[]="('".$code."','Producto de prueba','MARCA',1)";
    }
    $db->pdo->exec('INSERT INTO bayer_productos_catalogo'
        .'(codigo_bayer,nombre_bayer,marca,publicado) VALUES '.implode(',',$bulk));
    for($month=1;$month<=11;$month++){
        $period=sprintf('2025-%02d',$month);
        $db->pdo->exec("INSERT INTO bayer_inventario_periodos(producto_id,periodo,cantidad,marcador)
            SELECT id,'".$period."',1,'CANTIDAD' FROM bayer_productos_catalogo
            WHERE codigo_bayer LIKE 'CI-%'");
    }
    $allHistory=$service->publishedInventory();
    ensure(count($allHistory)>=11011,
        'La API entrega más de 10 mil filas históricas sin truncamiento silencioso');
    echo 'BAYER NATIVE CATALOG OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

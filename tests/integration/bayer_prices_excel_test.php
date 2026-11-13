<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Services\{BayerPriceWorkbookReader,BayerPriceImportService,BayerCatalogService};
use App\Exceptions\HttpException;

// No unit conversion is imposed: every codigo_bayer identifies its presentation.
$reader=new BayerPriceWorkbookReader();
$header=[1=>'codigo_bayer',2=>'nombre_bayer',3=>'moneda',
    4=>'precio_sugerido_sin_igv',5=>'igv_porcentaje',
    6=>'precio_sugerido_con_igv',7=>'descuento_dealer_porcentaje',
    8=>'vigencia_desde',9=>'vigencia_hasta',10=>'presentacion_referencia'];
$matrix=[
    1=>[1=>'LISTA DE PRECIOS BAYER'],
    5=>$header,
    6=>[1=>'86216353',2=>'ABACUS EC18 4X4L BOT PE',3=>'PEN',
         4=>'207.95',5=>'18',6=>'245.38',7=>'8',8=>'2026-11-02',9=>'',10=>'4X4L'],
    7=>[1=>'91957137',2=>'AMINOFOL PLUS SL51 15X1L BOT PE',3=>'PEN',
         4=>'236.24',5=>'0',6=>'236.24',7=>'8',8=>'2026-11-02',9=>'',10=>'15X1L'],
];
$rows=$reader->parseMatrix($matrix,'PRECIOS_BAYER');
ensure(is_array($rows)&&count($rows)===2,'Detecta cabeceras de precios en fila 5');
ensure($rows[0]['codigo_bayer']==='86216353' && $rows[0]['precio_sugerido_con_igv']==='245.38',
    'Identifica precio por codigo_bayer, sin unidad requerida');
ensure($rows[1]['igv_porcentaje']==='0','Mantiene el IGV 0 del proveedor');

$tmp=tempnam(sys_get_temp_dir(),'bayer-prices-');
$zip=new ZipArchive();
$zip->open($tmp,ZipArchive::OVERWRITE);
$zip->addFromString('xl/workbook.xml','<?xml version="1.0"?>'
    .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    .'<sheets><sheet name="INSTRUCCIONES" sheetId="2" r:id="rA"/>'
    .'<sheet name="PRECIOS_BAYER" sheetId="1" r:id="rB"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
    .'<Relationship Id="rB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    .'</Relationships>');
$xml='<?xml version="1.0"?>'
    .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
foreach($matrix as $i=>$values){
    $xml.='<row r="'.$i.'">';
    foreach($values as $col=>$value){
        $letter=chr(64+$col);
        $xml.='<c r="'.$letter.$i.'" t="inlineStr"><is><t>'.
            htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
    }
    $xml.='</row>';
}
$xml.='</sheetData></worksheet>';
$zip->addFromString('xl/worksheets/sheet1.xml',$xml);
$zip->addFromString('xl/worksheets/sheet2.xml','<?xml version="1.0"?>'
    .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    .'<sheetData><row r="1"><c r="A1" t="inlineStr">'
    .'<is><t>Instrucciones sin datos</t></is></c></row></sheetData></worksheet>');
$zip->close();
try{
    $sheets=$reader->inspectFile($tmp,'xlsx');
    ensure(count($sheets)===1 && $sheets[0]['name']==='PRECIOS_BAYER',
        'Omite instrucciones y resuelve nombres de hojas por relación XML');
    ensure($sheets[0]['errors']===0 && $sheets[0]['count']===2,
        'Previsualiza ambos precios sin errores');
    $actual=$reader->readSelectedFile($tmp,'xlsx','PRECIOS_BAYER');
    ensure($actual[0]['codigo_bayer']==='86216353','Selecciona solo la hoja elegida');
}finally{@unlink($tmp);}

$csv=tempnam(sys_get_temp_dir(),'bayer-csv-price-');
file_put_contents($csv,implode("\n",[
    "\xEF\xBB\xBFCOMERCIAL;BAYER",
    'codigo_bayer;nombre_bayer;moneda;precio_sugerido_sin_igv;igv_porcentaje;precio_sugerido_con_igv;descuento_dealer_porcentaje;vigencia_desde;vigencia_hasta',
    '86216353;ABACUS 4X4L;PEN;207.95;18;245.38;8;2026-11-02;',
]));
try{
    $csvRows=$reader->readSelectedFile($csv,'csv','Archivo CSV');
    ensure(count($csvRows)===1 && $csvRows[0]['precio_sugerido_con_igv']==='245.38',
        'CSV punto y coma detectado con BOM y títulos extra');
}finally{@unlink($csv);}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/014_catalogo_bayer_nativo.sql');
    fixtures($db->pdo);
    $db->pdo->exec("INSERT INTO bayer_productos_catalogo
       (codigo_bayer,nombre_bayer,publicado,stock_reportado,stock_periodo,stock_estado)
       VALUES
       ('86216353','ABACUS EC18 4X4L BOT PE',1,274,'2026-09','CON_STOCK'),
       ('91957137','AMINOFOL PLUS SL51 15X1L BOT PE',1,0,'2026-09','SIN_STOCK')");
    $service=new BayerPriceImportService($db->pdo);
    $check=$service->previewMatch($rows);
    ensure($check['matched']===2 && !$check['missing'] && !$check['issues'],
        'Ambos códigos Bayer se reconocen antes de confirmar');
    $result=$service->importRows($rows,'LISTA PRECIOS 02-11-26.xlsx');
    ensure($result['insertados']===2 && $result['actualizados']===0,
        'Guarda la lista original en los productos correctos');
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM bayer_precios_referencia')->fetchColumn()===2,
        'Una fila de precio por código y vigencia');
    $repeat=$service->importRows($rows,'LISTA PRECIOS 02-11-26.xlsx');
    ensure($repeat['sin_cambios']===2 && $repeat['insertados']===0,
        'Importar la misma lista no duplica precios');
    $changed=$rows;
    $changed[0]['precio_sugerido_sin_igv']='208.00';
    $changed[0]['precio_sugerido_con_igv']='245.44';
    $r=$service->importRows([$changed[0]],'PRECIOS ACTUALIZADOS.xlsx');
    ensure($r['actualizados']===1,'La misma fecha actualiza el precio del mismo código Bayer');
    $gross=$db->pdo->query("SELECT pr.sugerido_con_igv FROM bayer_precios_referencia pr
        JOIN bayer_productos_catalogo pc ON pc.id=pr.producto_id
        WHERE pc.codigo_bayer='86216353'")->fetchColumn();
    ensure((float)$gross===245.44,'El precio actualizado pertenece al SKU 86216353');
    $other=$db->pdo->query("SELECT pr.sugerido_con_igv FROM bayer_precios_referencia pr
        JOIN bayer_productos_catalogo pc ON pc.id=pr.producto_id
        WHERE pc.codigo_bayer='91957137'")->fetchColumn();
    ensure((float)$other===236.24,'El otro producto Bayer conserva su importe');
    $next=$changed[0];$next['vigencia_desde']='2026-12-01';
    $service->importRows([$next],'LISTA DICIEMBRE.xlsx');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM bayer_precios_referencia")->fetchColumn()===3,
        'Nueva fecha conserva el precio anterior en el histórico');
    ensure((int)$db->pdo->query("SELECT stock_reportado FROM bayer_productos_catalogo
        WHERE codigo_bayer='86216353'")->fetchColumn()===274,
        'Actualizar precio no modifica inventario mensual de Bayer');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM stock_cabecera")->fetchColumn()===0,
        'Actualizar precios nunca crea stock físico Pucchún');
    ensure((int)$db->pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn()===2,
        'El catálogo maestro Pucchún queda intacto');
    $invalid=$rows;
    $invalid[0]['codigo_bayer']='CODIGO-AUSENTE';
    rejects(fn()=>$service->importRows($invalid,'INVALIDO.xlsx'),HttpException::class);
    ensure((int)$db->pdo->query('SELECT COUNT(*) FROM bayer_precios_referencia')->fetchColumn()===3,
        'Código no registrado bloquea el lote sin cambios parciales');
    $wrong=$rows;
    $wrong[1]['precio_sugerido_con_igv']='300.00';
    rejects(fn()=>$service->importRows($wrong,'IGV INVALIDO.xlsx'),HttpException::class);
    $duplicate=[$rows[0],$rows[0]];
    rejects(fn()=>$service->importRows($duplicate,'DUPLICADOS.xlsx'),HttpException::class);

    $base=dirname(__DIR__,2);
    $index=file_get_contents($base.'/app/Views/bayer_catalog/index.php');
    $view=file_get_contents($base.'/app/Views/bayer_catalog/prices_import.php');
    $routes=file_get_contents($base.'/routes/web.php');
    ensure(!str_contains($index,'precios-cargar')&&!str_contains($index,'Cargar precios Bayer 2026')
        &&str_contains($index,'/bayer-productos/precios/importar'),
        'No existe el botón antiguo de cargar precios fijos');
    foreach(['precios/analizar','precios/confirmar','precios/cancelar'] as $action)
        ensure(str_contains($routes,"'/bayer-productos/".$action."'"),
            'Ruta protegida de botón '.$action);
    ensure(str_contains($view,'name="confirmacion"')
        &&str_contains($view,'/bayer-productos/precios/cancelar')
        &&str_contains($view,'type="submit"'),
        'Confirmación explícita y cancelar sin ventana nativa inconsistente');
    echo 'BAYER PRICE XLSX/CSV & UPSERT OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{$db->close();}

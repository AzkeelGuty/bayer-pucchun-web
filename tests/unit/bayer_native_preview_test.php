<?php
declare(strict_types=1);

require __DIR__.'/../integration/bootstrap.php';

use App\Services\BayerWorkbookReader;
use App\Exceptions\HttpException;

$reader=new BayerWorkbookReader();
$csv=tempnam(sys_get_temp_dir(),'bayer-csv-');
try{
    // CSV original, con separador Excel ';', BOM y encabezados después de la portada.
    $lines=[
        "\xEF\xBB\xBFEmpresa;BAYER",
        'REPORTE DE INVENTARIOS CANAL - CLIENTE',
        'Información preliminar',
        'Year;Month;CODIGO;PRODUCTO / PRESENTACION;MARCA;CANTIDAD / UNIDADES',
        '2026;9;0086216354;"ABACUS EC18 4X4L BOT PE";ABACUS;274',
        '2026;9;84939862;ALIETTE WG80;ALIETTE;-',
        '2026;9;91957137;AMINOFOL PLUS;AMINOFOL;0',
        '2026;9;91113516;BAYFOLAN;BAYFOLAN;',
    ];
    file_put_contents($csv,implode("\r\n",$lines));
    $sheets=$reader->inspectFile($csv,'csv');
    ensure(count($sheets)===1 && $sheets[0]['name']==='Archivo CSV',
        'CSV se identifica como fuente única sin múltiples pestañas');
    ensure($sheets[0]['count']===4 && $sheets[0]['errors']===0,
        'CSV encuentra la cabecera después de la portada');
    ensure($sheets[0]['statuses']['CON_STOCK']===1
        && $sheets[0]['statuses']['SIN_STOCK']===1
        && $sheets[0]['statuses']['DESACTIVADO']===1
        && $sheets[0]['statuses']['SIN_DATO']===1,
        'Previsualización CSV distingue cero, vacío, guion y cantidad');
    $selected=$reader->readSelectedFile($csv,'csv','Archivo CSV');
    ensure($selected[0]['codigo_bayer']==='0086216354',
        'CSV conserva ceros a la izquierda en el código Bayer');
    rejects(fn()=>$reader->readSelectedFile($csv,'csv','SET_26'),HttpException::class);

    // Libro multimes con orden de pestañas inverso al de los archivos XML.
    $xlsx=tempnam(sys_get_temp_dir(),'bayer-sheets-');
    $zip=new ZipArchive();
    $zip->open($xlsx,ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="OCT_26" sheetId="2" r:id="rOct"/>'
        .'<sheet name="SET_26" sheetId="1" r:id="rSet"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rOct" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
        .'<Relationship Id="rSet" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'</Relationships>');
    $makeSheet=static function(int $count,string $codePrefix,string $quantity): string{
        $xml='<?xml version="1.0"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $xml.='<row r="13"><c r="A13" t="inlineStr"><is><t>CODIGO</t></is></c>'
            .'<c r="B13" t="inlineStr"><is><t>PRODUCTO / PRESENTACION</t></is></c>'
            .'<c r="C13" t="inlineStr"><is><t>CANTIDAD / UNIDADES</t></is></c></row>';
        for($i=1;$i<=$count;$i++){
            $r=$i+13;$code=$codePrefix.sprintf('%05d',$i);
            $xml.='<row r="'.$r.'">'
                .'<c r="A'.$r.'" t="inlineStr"><is><t>'.$code.'</t></is></c>'
                .'<c r="B'.$r.'" t="inlineStr"><is><t>PRODUCTO '.$code.'</t></is></c>'
                .'<c r="C'.$r.'" t="inlineStr"><is><t>'.$quantity.'</t></is></c>'
                .'</row>';
        }
        return $xml.'</sheetData></worksheet>';
    };
    $zip->addFromString('xl/worksheets/sheet1.xml',$makeSheet(5200,'SET','274'));
    $zip->addFromString('xl/worksheets/sheet2.xml',$makeSheet(5200,'OCT','0'));
    $zip->close();
    try{
        $preview=$reader->inspectFile($xlsx,'xlsx');
        ensure(count($preview)===2 && $preview[0]['name']==='OCT_26'
            && $preview[1]['name']==='SET_26',
            'Selector muestra nombres reales en orden visible del libro');
        ensure($preview[0]['count']===5200 && $preview[1]['count']===5200,
            'Se previsualiza libro de más de 10000 registros totales por separado');
        ensure(count($preview[0]['sample'])===20 && $preview[0]['errors']===0,
            'Preview lista 20 filas sin almacenar todas las hojas simultáneamente');
        $onlyOctober=$reader->readSelectedFile($xlsx,'xlsx','OCT_26');
        ensure(count($onlyOctober)===5200 &&
            $onlyOctober[0]['periodo']==='2026-10' &&
            $onlyOctober[0]['cantidad']==='0' &&
            str_starts_with($onlyOctober[0]['codigo_bayer'],'OCT'),
            'Importar OCT solo entrega los registros OCT y nunca septiembre');
        $onlySeptember=$reader->readSelectedFile($xlsx,'xlsx','SET_26');
        ensure(count($onlySeptember)===5200 &&
            $onlySeptember[0]['periodo']==='2026-09' &&
            $onlySeptember[0]['cantidad']==='274' &&
            str_starts_with($onlySeptember[0]['codigo_bayer'],'SET'),
            'Importar SET solo entrega registros SET aunque sea sheet1.xml');
    }finally{@unlink($xlsx);}

    $view=(string)file_get_contents(dirname(__DIR__,2).'/app/Views/bayer_catalog/import.php');
    $routes=(string)file_get_contents(dirname(__DIR__,2).'/routes/web.php');
    ensure(str_contains($view,'/bayer-productos/analizar')
        && str_contains($view,'/bayer-productos/importar')
        && str_contains($view,'name="hoja"') && str_contains($view,'name="confirmacion"'),
        'Interfaz obliga análisis previo, elección y confirmación');
    ensure(str_contains($routes,"post('/bayer-productos/analizar'")
        && str_contains($routes,"post('/bayer-productos/importar/cancelar'"),
        'Rutas de analizar y cancelar permanecen protegidas por POST');
    echo 'BAYER PREVIEW XLSX/CSV OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
}finally{@unlink($csv);}

<?php
declare(strict_types=1);

/** Genera un XLSX verdadero y comprueba contenido, imágenes y anclajes XML. */
$root=dirname(__DIR__,2);
$png=base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',true
);
if(!is_string($png))throw new RuntimeException('PNG de pruebas inválido.');
$logo=tempnam(sys_get_temp_dir(),'guide-logo-').'.png';
file_put_contents($logo,$png);
$code='<?php'."\n"
    .'require '.var_export($root.'/app/Services/ExportPresentation.php',true).';'."\n"
    .'require '.var_export($root.'/app/Services/GuideExcelLayout.php',true).';'."\n"
    .'require '.var_export($root.'/app/Services/SimpleXlsxExporter.php',true).';'."\n"
    .'$rows='.var_export([
        ['documentNumber'=>'INT-GD-000000001','documentDate'=>'2026-11-16',
         'customerName'=>'CLIENTE UNICO EJEMPLO','customerId'=>'45149906',
         'sourceDocumentNumber'=>'INT-FAC-000001','salesName'=>'Luis',
         'branchName'=>'Chincha','materialId'=>'00000007946',
         'materialName'=>'Producto A','measureUnit'=>'LTR','quantity'=>3,
         'sunatStatus'=>'NO_EMITIDA'],
        ['documentNumber'=>'INT-GD-000000001','documentDate'=>'2026-11-16',
         'customerName'=>'CLIENTE UNICO EJEMPLO','customerId'=>'45149906',
         'materialId'=>'000000050176','materialName'=>'Producto B',
         'measureUnit'=>'LTR','quantity'=>4,'sunatStatus'=>'NO_EMITIDA'],
    ],true).';'."\n"
    .'(new \App\Services\SimpleXlsxExporter())->output($rows,"guias.xlsx",'
    .var_export(['type'=>'guides','title'=>'Reporte de guías de remisión',
        'dataset'=>'Guías de remisión','system'=>'Pucchún Data Hub','partner'=>'Bayer',
        'logo_primary_path'=>$logo,'logo_partner_path'=>$logo],true).');';
$runner=tempnam(sys_get_temp_dir(),'guide-xlsx-').'.php';
file_put_contents($runner,$code);
$xlsx=tempnam(sys_get_temp_dir(),'generated-guide-');
try{
    $process=proc_open([PHP_BINARY,$runner],
        [['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('No se pudo ejecutar exportador.');
    fclose($pipes[0]);
    $binary=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
    $exit=proc_close($process);
    if($exit!==0)throw new RuntimeException('Exportador XLSX falló: '.$stderr);
    file_put_contents($xlsx,$binary);
    $zip=new ZipArchive();
    if($zip->open($xlsx)!==true)
        throw new RuntimeException('El archivo exportado no es un XLSX válido.');
    try{
        $sheet=(string)$zip->getFromName('xl/worksheets/sheet1.xml');
        $drawing=(string)$zip->getFromName('xl/drawings/drawing1.xml');
        $styles=(string)$zip->getFromName('xl/styles.xml');
        $images=[
            $zip->getFromName('xl/media/image1.png'),
            $zip->getFromName('xl/media/image2.png'),
        ];
        $assert=static function(bool $ok,string $msg):void{
            if(!$ok)throw new RuntimeException($msg);
        };
        $assert(substr_count($sheet,'CLIENTE UNICO EJEMPLO')===1,
            'Cliente duplicado en cada producto de la misma guía.');
        $assert(substr_count($sheet,'INT-GD-000000001')===1,
            'Número único repetido en cada producto de la misma guía.');
        $assert(str_contains($sheet,'Producto A') && str_contains($sheet,'Producto B'),
            'Detalle de guía incompleto.');
        $assert(str_contains($sheet,'TOTAL DE GUÍA') &&
            str_contains($sheet,'<v>7</v>'),'Subtotal de guía no calculado.');
        $assert(!str_contains($sheet,'ESTADO SUNAT') &&
            !str_contains($sheet,'NO_EMITIDA'),
            'Estado SUNAT todavía visible en Excel.');
        // Dos últimas columnas K,L (índices 10 y 11) dentro del encabezado.
        $assert(str_contains($sheet,'<c r="K1" s="1"') &&
            str_contains($sheet,'<c r="L1" s="1"') &&
            str_contains($sheet,'<c r="K2" s="2"') &&
            str_contains($sheet,'<c r="L2" s="2"'),
            'Las celdas de logo quedan fuera de la franja azul.');
        preg_match_all('~<xdr:from><xdr:col>(\d+)</xdr:col>~',$drawing,$match);
        $assert(($match[1]??[])===['10','11'],
            'Anclajes de logos fuera del ancho de la tabla.');
        $assert(count(array_filter($images,'is_string'))===2,
            'No se insertaron ambos logotipos.');
        $assert(str_contains($styles,'cellXfs count="14"'),
            'No existen estilos para cabeceras y subtotales.');
    }finally{$zip->close();}
    echo "GUIAS XLSX REALES OK (grupos, totales, imágenes y cuadro institucional)\n";
}finally{
    @unlink($runner);@unlink($logo);@unlink($xlsx);
}

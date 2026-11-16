<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Services/SimpleXlsxExporter.php';
require dirname(__DIR__,2).'/app/Services/ExportPresentation.php';
use App\Services\SimpleXlsxExporter;
use App\Services\ExportPresentation;
$exporter=new SimpleXlsxExporter();
$method=(new ReflectionClass($exporter))->getMethod('drawingParts');
$method->setAccessible(true);
$imgs=[
    ['name'=>'logo1.png','label'=>'Pucchún','width'=>240,'height'=>240],
    ['name'=>'logo2.png','label'=>'Bayer','width'=>400,'height'=>300],
];
[$drawing,$rels]=$method->invoke($exporter,$imgs,14);
if(substr_count($drawing,'<xdr:oneCellAnchor>')!==2)
    throw new RuntimeException('Se esperaban dos logos reales en el encabezado.');
preg_match_all('~<xdr:from><xdr:col>(\d+)</xdr:col>~',$drawing,$m);
if(($m[1]??[])!==['12','13'])
    throw new RuntimeException('Logos fuera de las últimas columnas del reporte: '.json_encode($m[1]??[]));
if(!str_contains($drawing,'<a:ext cx=') ||
   !str_contains($drawing,'<xdr:row>0</xdr:row>') ||
   !str_contains($rels,'../media/logo1.png') ||
   !str_contains($rels,'../media/logo2.png'))
    throw new RuntimeException('Logos deformados o enlaces XLSX incorrectos.');
foreach(['source'=>'Origen','stockStatus'=>'Estado del stock',
         'sourceDocumentNumber'=>'Documento de origen',
         'customerDocumentType'=>'Tipo documento cliente',
         'lineTotal'=>'Importe de línea (S/)'] as $key=>$value){
    if(ExportPresentation::fieldLabel($key)!==$value)
        throw new RuntimeException('Encabezado del reporte no identificado: '.$key);
}
echo "XLSX logos and report labels OK\n";

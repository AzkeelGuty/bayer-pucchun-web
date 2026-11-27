<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/app/Services/SimplePdfExporter.php';

use App\Services\SimplePdfExporter;

$exporter=new SimplePdfExporter();
$ref=new ReflectionClass($exporter);

$invoke=function(string $method,array $args=[])use($ref,$exporter){
    $m=$ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($exporter,$args);
};

$keys=[
    'documentNumber','documentDate','customerName','salesName','branchName',
    'materialName','measureUnit','quantity','unitValue','district','province','department'
];
$widths=$invoke('columnWidths',[$keys,794.0]);
$customerIndex=array_search('customerName',$keys,true);
if($customerIndex===false) throw new RuntimeException('customerName missing');

$customerWidth=(float)$widths[$customerIndex];
$customer='AGRICOLA ZAPALKA SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA';
$lines=$invoke('wrapForWidth',[$customer,$customerWidth,5.8,'F1']);

if(count($lines)<2){
    throw new RuntimeException('El cliente largo debe dividirse en varias líneas.');
}

foreach($lines as $line){
    $measured=(float)$invoke('estimatedTextWidth',[$line,5.8,'F1']);
    if($measured>$customerWidth-9.5){
        throw new RuntimeException('Una línea del cliente excede el ancho útil de su celda: '.$line);
    }
}

$commands=$invoke('cellTextCommands',[100.0,200.0,$customerWidth,36.0,$lines,5.8,'F1',.12,.20,.28]);
if(!str_starts_with((string)$commands[0],'q ')){
    throw new RuntimeException('La celda debe iniciar un contexto gráfico con clipping.');
}
if(end($commands)!=='Q'){
    throw new RuntimeException('La celda debe cerrar el contexto gráfico con clipping.');
}

echo "PDF layout: cliente largo envuelto y recortado dentro de celda OK\n";

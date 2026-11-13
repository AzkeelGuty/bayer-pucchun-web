<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{
    if(!$ok)throw new RuntimeException('Limpieza de interfaz: '.$message);
    ++$checks;
};
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$guide=$read('app/Views/guias/show.php');
$stock=$read('app/Views/stock/show.php');
$form=$read('app/Views/documentos/form.php');
$detail=$read('app/Views/documentos/show.php');
$js=$read('public/assets/js/documentos-captura.js');
$controller=$read('app/Controllers/DocumentController.php');

$assert(!str_contains($guide,'Pendiente de emisión electrónica ante SUNAT.'),'Quitar frase circundada de guía');
$assert(!str_contains($guide,'Estado tributario · Emisión'),'Quitar banda informativa tributaria en guía');
$assert(str_contains($guide,'Ver documento de origen'),'Conservar vínculo a documento');
foreach([$guide,$stock] as $text){
    $assert(!str_contains($text,'Versión del registro'),'Ocultar versión de ficha');
    $assert(!str_contains($text,'Control interno de cambios'),'Ocultar explicación de versión');
}
$assert(!str_contains($form,'Indica la situación comercial del comprobante'),'Quitar comentario debajo del estado');
$assert(!str_contains($form,'doc-referencia-pago'),'Quitar campo de referencia de pago de captura');
$assert(!str_contains($form,'header[referencia_pago]'),'Quitar referencia del formulario');
$assert(!str_contains($detail,'Código o referencia del pago'),'No mostrar campo ocultado en el detalle');
$assert(!str_contains($js,'paymentReference'),'No ejecutar ayuda dinámica de campo retirado');
$assert(str_contains($form,'id="doc-medio-pago"'),'Conservar selector de medio de pago');
$assert(str_contains($controller,"$".'header[\'referencia_pago\']'),'Preservar referencia histórica almacenada');
$assert(is_file($root.'/app/Services/SunatEmissionService.php'),'Integración tributaria separada del flujo interno');
echo 'CLEAN ERP UX OK ('.$checks.' checks)'.PHP_EOL;

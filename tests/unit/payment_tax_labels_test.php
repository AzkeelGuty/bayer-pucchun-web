<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$checks=0;
$assert=function(bool $ok,string $message)use(&$checks):void{
    if(!$ok)throw new RuntimeException('UX tributaria y pagos: '.$message);
    $checks++;
};
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);

$guide=$read('app/Views/guias/show.php');
$stock=$read('app/Views/stock/show.php');
$form=$read('app/Views/documentos/form.php');
$detail=$read('app/Views/documentos/show.php');
$js=$read('public/assets/js/documentos-captura.js');

$assert(str_contains($guide,'Estado tributario · Emisión en SUNAT y el traslado de bienes:'),
    'Encabezado solicitado de emisión y traslado');
$assert(str_contains($guide,'Pendiente de emisión electrónica ante SUNAT.'),
    'Una guía interna no debe aparecer como emitida ante SUNAT');
$assert(str_contains($guide,"('estado_sunat'") || str_contains($guide,"['estado_sunat']"),
    'El estado se decide por el campo tributario persistido');
$assert(str_contains($guide,'Ver documento de origen'),
    'Conservar enlace al documento que originó la guía');
foreach([$guide,$stock] as $text){
    $assert(str_contains($text,'Versión del registro'), 'Etiqueta clara de versión interna');
    $assert(str_contains($text,'Control interno de cambios'), 'Explicación visible de la versión');
}
$assert(str_contains($form,'Código o referencia del pago (opcional)'),
    'Etiqueta precisa y opcional');
$assert(str_contains($form,'name="header[referencia_pago]"'),
    'No cambiar el nombre de campo que guarda el backend');
$assert(str_contains($form,'maxlength="80"'), 'Respetar longitud de referencia existente');
$assert(str_contains($form,'data-payment-reference-help'), 'Ayuda contextual de pago');
$assert(str_contains($form,'id="doc-medio-pago"'), 'Conservar selector del medio de pago');
$assert(str_contains($detail,'Código o referencia del pago'), 'Detalle coherente con formulario');
foreach(['EFECTIVO','YAPE','PLIN','TRANSFERENCIA','TARJETA','OTRO'] as $payment){
    $assert(str_contains($js,$payment.': ['), 'Ayuda para medio de pago '.$payment);
}
$assert(str_contains($js,"paymentMethod?.addEventListener('change', refreshPaymentHelp)"),
    'La ayuda debe cambiar al elegir otro medio de pago');
$assert(str_contains($js,"refreshPaymentHelp();"),
    'Mostrar explicación correcta al abrir el formulario');
$assert(!str_contains($js,'paymentReference.required = true'),
    'La referencia nunca se vuelve obligatoria');
echo 'PAYMENT AND TAX UX OK ('.$checks.' checks)'.PHP_EOL;

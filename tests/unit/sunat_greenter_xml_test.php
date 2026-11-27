<?php
declare(strict_types=1);

/**
 * Prueba aislada de formato UBL y firma XMLDSig con certificado temporal.
 * Nunca transmite información a SUNAT ni emplea claves del emisor real.
 */
require dirname(__DIR__,2).'/vendor/autoload.php';
spl_autoload_register(static function(string $class): void {
    if(str_starts_with($class,'App\\')) {
        $path=dirname(__DIR__,2).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        if(is_file($path))require_once $path;
    }
});
$values=[
    'SUNAT_UBIGEO'=>'150101',
    'SUNAT_DEPARTAMENTO'=>'LIMA',
    'SUNAT_PROVINCIA'=>'LIMA',
    'SUNAT_DISTRITO'=>'LIMA',
    'SUNAT_DIRECCION'=>'CALLE DE PRUEBAS 123',
    'SUNAT_NOMBRE_COMERCIAL'=>'EMISOR DE PRUEBAS',
];
foreach($values as $key=>$value)putenv($key.'='.$value);
function env(string $key,mixed $default=null): mixed {
    $value=getenv($key);
    return $value!==false && $value!==''?$value:$default;
}

$lines=[
    ['cantidad'=>'2','valor_unitario'=>'10.00','unidad_codigo'=>'UND',
     'producto_codigo'=>'PR001','producto_nombre'=>'PRODUCTO DE PRUEBA'],
];
$totals=App\Services\SunatInvoiceBuilder::totals($lines,'PRECIO_SIN_IGV');
$doc=[
    'ruc_emisor'=>'20123456789',
    'emisor_nombre'=>'EMPRESA DE PRUEBAS SAC',
    'emisor_comercial'=>'EMPRESA DE PRUEBAS',
    'cliente_documento'=>'20600000001',
    'cliente_nombre'=>'CLIENTE DEMOSTRACION SAC',
    'fecha'=>'2026-11-12',
];
$invoice=App\Services\SunatInvoiceBuilder::createGreenterInvoice($doc,$totals,'F001',1);
if(!($invoice instanceof Greenter\Model\Sale\Invoice))throw new RuntimeException('No se generó factura Greenter');

$key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048]);
if(!$key)throw new RuntimeException('No se creó clave temporal');
$csr=openssl_csr_new(['commonName'=>'CPE TEST CI'], $key);
$certificate=openssl_csr_sign($csr,null,$key,1);
openssl_pkey_export($key,$private);
openssl_x509_export($certificate,$public);
$see=new Greenter\See();
$see->setCertificate($private."\n".$public);
$xml=$see->getXmlSigned($invoice);
if(!is_string($xml)||$xml==='')throw new RuntimeException('No se generó XML');
$dom=new DOMDocument('1.0','UTF-8');
if(!$dom->loadXML($xml,LIBXML_NONET))throw new RuntimeException('El XML no es válido');
if($dom->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#','Signature')->length<1)
    throw new RuntimeException('No se incluyó firma XMLDSig');
if(!str_contains($xml,'F001-1'))throw new RuntimeException('Falta serie y correlativo fiscal');
if(!str_contains($xml,'2.1'))throw new RuntimeException('No se encontró la versión UBL 2.1');
if(!str_contains($xml,'20123456789'))throw new RuntimeException('Falta emisor');
echo 'SUNAT SIGNED UBL OK (5 checks)'.PHP_EOL;

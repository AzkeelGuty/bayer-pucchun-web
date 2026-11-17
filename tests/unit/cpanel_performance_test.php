<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Helpers/functions.php';

function perf_assert(bool $yes,string $reason): void {
    if(!$yes) throw new RuntimeException($reason);
}
$root=dirname(__DIR__,2);
$helpers=(string)file_get_contents($root.'/app/Helpers/functions.php');
$index=(string)file_get_contents($root.'/public/index.php');
$js=(string)file_get_contents($root.'/public/assets/js/branding.js');
$navigation=(string)file_get_contents($root.'/public/assets/js/navigation.js');
$apache=(string)file_get_contents($root.'/public/.htaccess');
perf_assert(!str_contains($helpers,'(string)time();'),'No debe invalidar recursos estáticos cada segundo');
perf_assert(str_contains($helpers,"config('app.asset_version','1')"),'Falta fallback estable para versión de activos');
perf_assert(str_contains($index,'Server-Timing:'),'Falta medición optativa de latencia de PHP');
perf_assert(str_contains($js,'window.BP_Branding={optimizeUploads}'),'Falta preparación de logos grandes');
perf_assert(str_contains($navigation,'await window.BP_Branding.optimizeUploads(data)'),
    'La navegación debe optimizar logos antes de enviar FormData');
perf_assert(str_contains($apache,'ExpiresByType image/png'),'Faltan cabeceras de caché condicionales');
$original=$_ENV['BAYER_PUBLIC_ROOT']??null;
$originalProcess=getenv('BAYER_PUBLIC_ROOT');
try {
    $_ENV['BAYER_PUBLIC_ROOT']=$root.'/storage/temp/public-root-no-existe';
    putenv('BAYER_PUBLIC_ROOT='.$_ENV['BAYER_PUBLIC_ROOT']);
    $url=asset_url('assets/js/branding.js');
    perf_assert($url===asset_url('assets/js/branding.js'),'URL de activo inestable');
    perf_assert(str_contains($url,'?v='),'El activo debe incluir versión');
} finally {
    if($original===null) unset($_ENV['BAYER_PUBLIC_ROOT']);
    else $_ENV['BAYER_PUBLIC_ROOT']=$original;
    if($originalProcess===false) putenv('BAYER_PUBLIC_ROOT');
    else putenv('BAYER_PUBLIC_ROOT='.$originalProcess);
}
echo "RENDIMIENTO CPANEL OK\n";

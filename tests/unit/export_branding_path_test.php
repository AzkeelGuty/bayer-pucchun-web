<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/app/Helpers/functions.php';
require dirname(__DIR__,2).'/app/Services/ExportPresentation.php';

use App\Services\ExportPresentation;

function export_branding_assert(bool $condition,string $message): void
{
    if(!$condition) throw new RuntimeException($message);
}

$root=dirname(__DIR__,2);
$configDir=$root.'/storage/config';
$configFile=$configDir.'/branding.json';
$originalConfig=is_file($configFile)?file_get_contents($configFile):null;
$originalEnv=$_ENV['BAYER_PUBLIC_ROOT']??null;
$originalProcess=getenv('BAYER_PUBLIC_ROOT');
$publicRoot=$root.'/storage/temp/export-cpanel-root-'.bin2hex(random_bytes(4));
$logoDir=$publicRoot.'/uploads/branding';

try{
    if(!is_dir($logoDir) && !mkdir($logoDir,0775,true) && !is_dir($logoDir)){
        throw new RuntimeException('No se pudo crear raíz pública temporal.');
    }
    if(!is_dir($configDir) && !mkdir($configDir,0775,true) && !is_dir($configDir)){
        throw new RuntimeException('No se pudo crear storage/config.');
    }

    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',true);
    export_branding_assert(is_string($png),'PNG fixture inválido.');
    $relative='uploads/branding/logo_primary-cpanel-test.png';
    $absolute=$publicRoot.'/'.$relative;
    file_put_contents($absolute,$png);

    $_ENV['BAYER_PUBLIC_ROOT']=$publicRoot;
    putenv('BAYER_PUBLIC_ROOT='.$publicRoot);
    file_put_contents($configFile,json_encode([
        'logo_primary'=>$relative,
        'logo_partner'=>null,
        'favicon'=>null,
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    $meta=ExportPresentation::metadata('documents',[],[],['nombre'=>'QA']);
    export_branding_assert(
        ($meta['logo_primary_path']??null)===$absolute,
        'La exportación no resolvió el logo desde BAYER_PUBLIC_ROOT/public_html.'
    );
    export_branding_assert(is_file((string)$meta['logo_primary_path']),'La ruta del logo para PDF/XLSX no existe.');

    echo "Export branding cPanel: OK\n";
}finally{
    if($originalConfig!==null) file_put_contents($configFile,$originalConfig);
    elseif(is_file($configFile)) unlink($configFile);

    if($originalEnv===null) unset($_ENV['BAYER_PUBLIC_ROOT']);
    else $_ENV['BAYER_PUBLIC_ROOT']=$originalEnv;
    if($originalProcess===false) putenv('BAYER_PUBLIC_ROOT');
    else putenv('BAYER_PUBLIC_ROOT='.$originalProcess);

    if(is_file($publicRoot.'/uploads/branding/logo_primary-cpanel-test.png')) unlink($publicRoot.'/uploads/branding/logo_primary-cpanel-test.png');
    @rmdir($publicRoot.'/uploads/branding');
    @rmdir($publicRoot.'/uploads');
    @rmdir($publicRoot);
}

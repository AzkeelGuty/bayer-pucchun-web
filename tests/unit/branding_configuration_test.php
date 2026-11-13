<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/app/Helpers/functions.php';

function branding_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$brand=branding();

$originalPublicRootEnv=$_ENV['BAYER_PUBLIC_ROOT'] ?? null;
$originalPublicRootProcess=getenv('BAYER_PUBLIC_ROOT');
$overrideRoot=base_path('storage/temp/branding-public-root-test');
if(!is_dir($overrideRoot) && !mkdir($overrideRoot,0775,true) && !is_dir($overrideRoot)){
    throw new RuntimeException('No se pudo preparar la raíz pública temporal.');
}
$_ENV['BAYER_PUBLIC_ROOT']=$overrideRoot;
putenv('BAYER_PUBLIC_ROOT='.$overrideRoot);
$expectedOverride=$overrideRoot.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'branding';
branding_test_assert(
    public_path('uploads/branding')===$expectedOverride,
    'BAYER_PUBLIC_ROOT no está controlando la raíz pública.'
);
if($originalPublicRootEnv===null) unset($_ENV['BAYER_PUBLIC_ROOT']);
else $_ENV['BAYER_PUBLIC_ROOT']=$originalPublicRootEnv;
if($originalPublicRootProcess===false) putenv('BAYER_PUBLIC_ROOT');
else putenv('BAYER_PUBLIC_ROOT='.$originalPublicRootProcess);
@rmdir($overrideRoot);

$required=[
    'system_name','system_subtitle','partner_name','internal_title','portal_title',
    'primary_color','accent_color','sidebar_color','background_color',
    'sidebar_theme','ui_density','corner_style','shadow_style','sidebar_size','topbar_style',
    'logo_primary','logo_partner','favicon',
];

foreach($required as $key){
    branding_test_assert(array_key_exists($key,$brand),'Falta clave de branding: '.$key);
}

$allowed=[
    'sidebar_theme'=>['dark','light'],
    'ui_density'=>['comfortable','compact'],
    'corner_style'=>['rounded','balanced','square'],
    'shadow_style'=>['soft','minimal','none'],
    'sidebar_size'=>['normal','compact'],
    'topbar_style'=>['glass','solid'],
];

foreach($allowed as $key=>$values){
    branding_test_assert(in_array($brand[$key],$values,true),'Valor de apariencia inválido en '.$key);
}

foreach(['primary_color','accent_color','sidebar_color','background_color'] as $key){
    branding_test_assert((bool)preg_match('/^#[0-9A-F]{6}$/',$brand[$key]),'Color inválido en '.$key);
}

$configDir=base_path('storage/config');
$configFile=$configDir.'/branding.json';
$uploadDir=public_path('uploads/branding');
$configDirExisted=is_dir($configDir);
$uploadDirExisted=is_dir($uploadDir);
$originalConfig=is_file($configFile) ? file_get_contents($configFile) : null;
$fixtures=[];

try {
    if(!is_dir($configDir) && !mkdir($configDir,0775,true) && !is_dir($configDir)){
        throw new RuntimeException('No se pudo preparar storage/config para la prueba.');
    }
    if(!is_dir($uploadDir) && !mkdir($uploadDir,0775,true) && !is_dir($uploadDir)){
        throw new RuntimeException('No se pudo preparar public/uploads/branding para la prueba.');
    }

    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',true);
    branding_test_assert(is_string($png) && $png!=='','No se pudo crear la imagen PNG de prueba.');

    foreach(['logo_primary','logo_partner','favicon'] as $asset){
        $relative='uploads/branding/'.$asset.'-test.png';
        $absolute=public_path($relative);
        branding_test_assert(file_put_contents($absolute,$png)!==false,'No se pudo crear fixture de '.$asset);
        $fixtures[]=$absolute;
        $brand[$asset]=$relative;
    }

    $expected=[
        'system_name'=>'Bayer QA Persistencia',
        'system_subtitle'=>'Prueba automática de identidad',
        'partner_name'=>'Bayer Perú QA',
        'partner_subtitle'=>'Portal de prueba',
        'internal_title'=>'Data Hub QA',
        'portal_title'=>'Portal QA',
        'login_kicker'=>'PRUEBA DE IDENTIDAD',
        'login_title'=>'Cambios persistidos correctamente',
        'login_message'=>'Validación automática de nombre, colores, apariencia e imágenes.',
        'footer_text'=>'Identidad visual QA',
        'primary_color'=>'#123456',
        'accent_color'=>'#2A9D8F',
        'sidebar_color'=>'#264653',
        'background_color'=>'#F1FAEE',
        'sidebar_theme'=>'light',
        'ui_density'=>'compact',
        'corner_style'=>'balanced',
        'shadow_style'=>'minimal',
        'sidebar_size'=>'compact',
        'topbar_style'=>'solid',
    ];
    $brand=array_merge($brand,$expected);

    $payload=json_encode($brand,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    branding_test_assert(is_string($payload),'No se pudo serializar branding de prueba.');
    branding_test_assert(file_put_contents($configFile,$payload.PHP_EOL,LOCK_EX)!==false,'No se pudo guardar branding.json de prueba.');

    $loaded=branding();
    foreach($expected as $key=>$value){
        branding_test_assert(($loaded[$key]??null)===$value,'No persistió correctamente '.$key);
    }

    foreach(['logo_primary','logo_partner','favicon'] as $asset){
        branding_test_assert(($loaded[$asset]??null)===$brand[$asset],'No persistió la ruta de '.$asset);
        branding_test_assert(branding_logo_url($asset)!==null,'No se resolvió el archivo guardado de '.$asset);
    }

    $css=@file_get_contents(public_path('assets/css/app.css'));
    branding_test_assert(is_string($css) && $css!=='','No se pudo leer app.css.');
    branding_test_assert(
        str_contains($css,'.sidebar-link.active{background:linear-gradient(90deg,var(--brand-primary,#075b9f),var(--brand-accent,#168c5b))'),
        'El menú lateral activo no está usando los colores configurables de identidad visual.'
    );

    $navigation=@file_get_contents(public_path('assets/js/navigation.js'));
    branding_test_assert(is_string($navigation) && $navigation!=='','No se pudo leer navigation.js.');
    $methodPos=strpos($navigation,"const method = String(options.method || 'GET').toUpperCase();");
    $guardPos=strpos($navigation,"if ((method === 'GET' || method === 'HEAD') && url.href === location.href");
    branding_test_assert($methodPos!==false && $guardPos!==false && $methodPos<$guardPos,'La navegación vuelve a bloquear POST enviados a la misma URL.');

    echo "Branding configuration: OK (textos, colores, apariencia, imágenes y POST misma URL)\n";
} finally {
    foreach($fixtures as $fixture){
        if(is_file($fixture)) @unlink($fixture);
    }

    if($originalConfig!==null){
        @file_put_contents($configFile,$originalConfig,LOCK_EX);
    }elseif(is_file($configFile)){
        @unlink($configFile);
    }

    if(!$uploadDirExisted && is_dir($uploadDir)){
        @rmdir($uploadDir);
        $uploadsParent=dirname($uploadDir);
        if(is_dir($uploadsParent) && count(scandir($uploadsParent)?:[])===2) @rmdir($uploadsParent);
    }
    if(!$configDirExisted && is_dir($configDir)){
        @rmdir($configDir);
    }
}

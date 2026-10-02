<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/app/Helpers/functions.php';

$brand=branding();

$required=[
    'system_name','system_subtitle','partner_name','internal_title','portal_title',
    'primary_color','accent_color','sidebar_color','background_color',
    'sidebar_theme','ui_density','corner_style','shadow_style','sidebar_size','topbar_style',
    'logo_primary','logo_partner','favicon',
];

foreach($required as $key){
    if(!array_key_exists($key,$brand)){
        throw new RuntimeException('Falta clave de branding: '.$key);
    }
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
    if(!in_array($brand[$key],$values,true)){
        throw new RuntimeException('Valor de apariencia inválido en '.$key);
    }
}

foreach(['primary_color','accent_color','sidebar_color','background_color'] as $key){
    if(!preg_match('/^#[0-9A-F]{6}$/',$brand[$key])){
        throw new RuntimeException('Color inválido en '.$key);
    }
}

echo "Branding configuration: OK\n";

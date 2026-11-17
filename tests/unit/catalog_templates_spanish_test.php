<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/Services/WarehouseExcelHeader.php';
require dirname(__DIR__,2).'/app/Services/CatalogTemplateLabels.php';
require dirname(__DIR__,2).'/app/Services/ProductExcelHeaderAliases.php';
require dirname(__DIR__,2).'/app/Services/SimpleXlsxTable.php';
require dirname(__DIR__,2).'/app/Services/MasterCatalogService.php';
require dirname(__DIR__,2).'/app/Services/HomologationService.php';

use App\Services\{CatalogTemplateLabels as Labels,MasterCatalogService,HomologationService,SimpleXlsxTable};

$check=function(bool $valid,string $msg):void{if(!$valid)throw new RuntimeException($msg);};
$master=MasterCatalogService::definitions();
$homolog=HomologationService::definitions();
$count=0;
foreach($master as $scope=>$definition){
    $titles=array_map(fn($key)=>Labels::label($key,$scope),array_keys($definition['import']));
    $check(count($titles)===count(array_unique($titles)),'Encabezado maestro duplicado: '.$scope);
    foreach($definition['import'] as $key=>$field){
        $title=Labels::label($key,$scope);
        $check(Labels::resolve($title,$definition['import'],$scope)===$key,
            'La cabecera maestra no se puede importar: '.$scope.': '.$title);
        $count++;
    }
}
foreach($homolog as $scope=>$definition){
    $expected=$definition['import'];
    $check(!isset($expected['partner_codigo']) && !isset($expected['valid_from']) &&
        !isset($expected['valid_until']),
        'La plantilla Bayer aún solicita partner y fechas técnicas: '.$scope);
    foreach($expected as $key=>$field){
        $title=Labels::label($key,$scope);
        $check(Labels::resolve($title,$expected,$scope)===$key,
            'No se resuelve el encabezado de homologación: '.$scope.': '.$title);
        $count++;
    }
}
$check(Labels::label('codigo_bayer','productos')==='CÓDIGO BAYER',
    'El código oficial del catálogo de productos Bayer debe seguir presente');
$check(Labels::label('cantidad_unidades','productos')==='CANTIDAD / UNIDADES',
    'El inventario Bayer debe utilizar la cabecera del reporte original');

$schemas=[
    ['clientes',$master['clientes']['import'],[
        'nro_doc'=>'20609990011','razon_social'=>'Cliente de prueba'
    ]],
    ['productos',$master['productos']['import'],[
        'codigo'=>'00007322','nombre'=>'Producto de prueba','unidad_codigo'=>'NIU',
        'precio_venta'=>'120.50','stock_actual'=>'9'
    ]],
    ['almacenes',$master['almacenes']['import'],[
        'sucursal_codigo'=>'S1','codigo'=>'A1','nombre'=>'Almacén prueba'
    ]],
    ['vendedores',$master['vendedores']['import'],[
        'codigo'=>'V1','nombres'=>'Juan'
    ]],
    ['clientes_homol',$homolog['clientes']['import'],[
        'codigo_interno'=>'20609990011','codigo_bayer'=>'C-002',
        'nombre_bayer'=>'Cliente externo'
    ]],
    ['unidades_homol',$homolog['unidades']['import'],[
        'codigo_interno'=>'NIU','codigo_bayer'=>'UNI'
    ]],
];
foreach($schemas as [$scope,$expected,$value]){
    $realScope=isset($expected['codigo_interno']) ? ($scope==='clientes_homol'?'clientes':'unidades') : $scope;
    $rows=[
        array_map(fn($key)=>Labels::label($key,$realScope),array_keys($expected)),
        array_map(fn($key)=>$value[$key]??'',array_keys($expected)),
    ];
    $xml='<?xml version="1.0" encoding="UTF-8"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach($rows as $rowIndex=>$cells){
        $xml.='<row r="'.($rowIndex+1).'">';
        foreach($cells as $cellIndex=>$text){
            $xml.='<c r="'.chr(65+$cellIndex).($rowIndex+1).'" t="inlineStr"><is><t>'
                .htmlspecialchars((string)$text,ENT_XML1|ENT_QUOTES,'UTF-8')
                .'</t></is></c>';
        }
        $xml.='</row>';
    }
    $xml.='</sheetData></worksheet>';
    $tmp=tempnam(sys_get_temp_dir(),'plantilla-es-');
    $zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml',$xml);$zip->close();
    try{
        $data=(new SimpleXlsxTable())->readFile($tmp,$expected);
        $check(count($data)===1,'No se importa la plantilla '.$scope);
        foreach($value as $k=>$v){
            $check(($data[0][$k]??null)===$v,
                'Valor mal detectado '.$scope.'.'.$k);
        }
    }finally{@unlink($tmp);}
    $count++;
}
echo "PLANTILLAS EN ESPAÑOL POR CATALOGO OK ($count cabeceras y escenarios)\n";

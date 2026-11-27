<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Services\{HomologationService,SimpleXlsxTable};
use App\Exceptions\HttpException;

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $db->load('database/migrations/004_catalogos_masivos_busqueda.sql');
    fixtures($db->pdo);
    $pdo=$db->pdo;
    $service=new HomologationService($pdo);

    // Archivo XLSX equivalente a la plantilla de ocho columnas, con código
    // numérico almacenado como texto para conservar también los ceros iniciales.
    $tmp=tempnam(sys_get_temp_dir(),'homolog-product-');
    $zip=new ZipArchive();
    $zip->open($tmp,ZipArchive::OVERWRITE);
    $columns=['partner_codigo','codigo_interno','codigo_bayer','nombre_bayer',
        'estado','valid_from','valid_until','unidad_codigo'];
    $rows=[
        $columns,
        ['BAYER','86216353','TEST-BAY-0003','ABACUS EC18 4X4L BOT PE','1','','',''],
        ['BAYER','0086216354','TEST-BAY-0004','ALIETTE WG80 12X1KG BAG PE','1','','','KG'],
    ];
    $xml='<?xml version="1.0" encoding="UTF-8"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach($rows as $r=>$cells){
        $xml.='<row r="'.($r+1).'">';
        foreach($cells as $i=>$value){
            $xml.='<c r="'.chr(65+$i).($r+1).'" t="inlineStr"><is><t>'
                .htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
        }
        $xml.='</row>';
    }
    $xml.='</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml',$xml);
    $zip->close();
    $parsed=(new SimpleXlsxTable())->readFile($tmp);
    @unlink($tmp);
    ensure(count($parsed)===2,'El Excel se lee con ocho columnas');
    ensure($parsed[0]['codigo_interno']==='86216353','Se conserva el código interno de la fila 2');
    ensure($parsed[1]['codigo_interno']==='0086216354','Se conservan ceros iniciales del código interno');

    // Por defecto no se generan registros maestros nuevos.
    rejects(fn()=>$service->importRows('productos',$parsed,1),HttpException::class);
    ensure((int)$pdo->query('SELECT COUNT(*) FROM homologacion_productos_bayer')->fetchColumn()===0,
        'Sin autorización, el Excel no altera homologaciones');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM productos WHERE codigo='86216353'")->fetchColumn()===0,
        'Sin autorización, no se crea un producto del catálogo');

    $result=$service->importRows('productos',$parsed,1,true);
    ensure($result['total']===2 && $result['inserted']===2,'Las dos homologaciones se importan');
    ensure($result['created_products']===2,'Se registran dos productos ausentes');
    $st=$pdo->query("SELECT codigo,nombre,estado,unidad_base_id FROM productos
        WHERE codigo IN ('86216353','0086216354') ORDER BY codigo");
    $products=$st->fetchAll(PDO::FETCH_ASSOC);
    ensure(count($products)===2,'Ambos códigos nuevos existen en productos');
    ensure($products[0]['codigo']==='0086216354','Los códigos con cero inicial no se transforman');
    ensure((int)$products[0]['estado']===0 && (int)$products[1]['estado']===0,
        'Productos nuevos quedan inactivos hasta completar datos maestros');
    ensure($products[0]['unidad_base_id']===null && $products[1]['unidad_base_id']===null,
        'No inferir unidad base de Pucchún desde unidad de Bayer');
    $st=$pdo->query("SELECT p.codigo,h.material_id,h.material_name,u.codigo AS unidad_bayer
        FROM homologacion_productos_bayer h
        JOIN productos p ON p.id=h.producto_id
        LEFT JOIN unidades_medida u ON u.id=h.unidad_bayer_id
        ORDER BY p.codigo");
    $homologations=$st->fetchAll(PDO::FETCH_ASSOC);
    ensure(count($homologations)===2,'Homologaciones quedan vinculadas a productos reales');
    ensure($homologations[0]['unidad_bayer']==='KG','La unidad Bayer se resuelve por código');
    ensure($homologations[1]['unidad_bayer']===null,'La unidad Bayer puede estar vacía');
    ensure($homologations[1]['material_id']==='TEST-BAY-0003',
        'Se conserva el código externo de ensayo sin inventar uno oficial');

    $repeat=$service->importRows('productos',$parsed,1,true);
    ensure($repeat['inserted']===0 && $repeat['updated']===2 && $repeat['created_products']===0,
        'Una segunda importación actualiza homologaciones sin crear duplicados');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM homologacion_productos_bayer')->fetchColumn()===2,
        'No se duplican homologaciones');

    // Si se produce cualquier error, tampoco deben conservarse los nuevos productos.
    $bad=[
        ['partner_codigo'=>'BAYER','codigo_interno'=>'PROD-TEMP',
         'codigo_bayer'=>'TEST-TEMP','nombre_bayer'=>'Producto temporal',
         'estado'=>'1','valid_from'=>'','valid_until'=>'','unidad_codigo'=>''],
        ['partner_codigo'=>'BAYER','codigo_interno'=>'PROD-NO-VAL',
         'codigo_bayer'=>'TEST-BAD','nombre_bayer'=>'Otro producto',
         'estado'=>'1','valid_from'=>'','valid_until'=>'','unidad_codigo'=>'INEXISTENTE'],
    ];
    rejects(fn()=>$service->importRows('productos',$bad,1,true),HttpException::class);
    ensure((int)$pdo->query("SELECT COUNT(*) FROM productos WHERE codigo='PROD-TEMP'")->fetchColumn()===0,
        'Ante error, se revierten las altas de productos de la misma importación');
    ensure((int)$pdo->query('SELECT COUNT(*) FROM homologacion_productos_bayer')->fetchColumn()===2,
        'Ante error, se revierten cambios de homologaciones');

    $missingName=[
        ['partner_codigo'=>'BAYER','codigo_interno'=>'PROD-SIN-NOMBRE',
         'codigo_bayer'=>'TEST','nombre_bayer'=>'','estado'=>'1','valid_from'=>'',
         'valid_until'=>'','unidad_codigo'=>''],
    ];
    rejects(fn()=>$service->importRows('productos',$missingName,1,true),HttpException::class);
    ensure((int)$pdo->query("SELECT COUNT(*) FROM productos WHERE codigo='PROD-SIN-NOMBRE'")->fetchColumn()===0,
        'No crear productos sin nombre externo proporcionado');

    rejects(fn()=>$service->importRows('clientes',$parsed,1,true),HttpException::class);
    echo 'HOMOLOGATION MISSING PRODUCTS OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;
} finally {
    $db->close();
}

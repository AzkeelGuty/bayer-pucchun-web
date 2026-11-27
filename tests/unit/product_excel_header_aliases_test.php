<?php
declare(strict_types=1);

require __DIR__.'/../integration/bootstrap.php';

use App\Services\{
    ProductExcelHeaderAliases,BayerWorkbookReader,
    BayerPriceWorkbookReader,WarehouseExcelHeader
};
use App\Exceptions\HttpException;

// No seleccionar códigos de país, región o canal como código de producto.
$inventory=[[
    'mode'=>'bayer_inventario',
    'expected'=>[
        'CÓDIGO'=>'codigo_bayer',
        'PRODUCTO / PRESENTACIÓN'=>'nombre_bayer',
        'MARCA'=>'marca',
        'CANTIDAD / UNIDADES'=>'cantidad',
        'YEAR'=>'year',
        'MONTH'=>'month',
        'Country Code'=>null,
        'Region Code'=>null,
        'Channel Code'=>null,
        'STOCK DISPONIBLE'=>'cantidad',
        'SKU BAYER'=>'codigo_bayer',
        'NOMBRE COMERCIAL'=>'nombre_bayer',
        'AÑO REPORTE'=>'year',
        'MES INVENTARIO'=>'month',
        'PERÍODO STOCK'=>'periodo',
        'CÓDIGO ALMACÉN'=>'almacen_codigo',
        'NOMBRE DEL ALMACÉN'=>'almacen_nombre',
        'CÓDIGO SUCURSAL'=>'sucursal_codigo',
    ]
],[
    'mode'=>'bayer_precios',
    'expected'=>[
        'CÓDIGO BAYER'=>'codigo_bayer',
        'PRODUCTO / PRESENTACIÓN'=>'nombre_bayer',
        'MONEDA'=>'moneda',
        'PRECIO SUGERIDO SIN IGV'=>'precio_sugerido_sin_igv',
        'PRECIO NETO'=>'precio_sugerido_sin_igv',
        'PRECIO LISTA SIN IGV'=>'precio_sugerido_sin_igv',
        'IGV %'=>'igv_porcentaje',
        'PORCENTAJE DE IGV'=>'igv_porcentaje',
        'PRECIO SUGERIDO CON IGV'=>'precio_sugerido_con_igv',
        'PRECIO FINAL CON IGV'=>'precio_sugerido_con_igv',
        'DESCUENTO DEALER %'=>'descuento_dealer_porcentaje',
        'DSCTO %'=>'descuento_dealer_porcentaje',
        'VIGENCIA DESDE'=>'vigencia_desde',
        'VIGENCIA HASTA'=>'vigencia_hasta',
        'Country Code'=>null,
    ]
],[
    'mode'=>'pucchun_productos',
    'expected'=>[
        'CÓDIGO PRODUCTO'=>'codigo',
        'NOMBRE DEL PRODUCTO'=>'nombre',
        'TIPO DE ARTÍCULO'=>'tipo_art',
        'UNIDAD DE MEDIDA'=>'unidad_codigo',
        'CATEGORÍA PRODUCTO'=>'categoria',
        'MARCA'=>'marca',
        'ESTADO PRODUCTO'=>'estado',
        'ALMACENAMIENTO'=>'almacen_nombre',
        'COD. ALMACEN'=>'almacen_codigo',
        'CÓDIGO SUCURSAL'=>'sucursal_codigo'
    ]
]];
foreach($inventory as $group){
    foreach($group['expected'] as $label=>$expected){
        $got=ProductExcelHeaderAliases::resolve($label,$group['mode']);
        ensure($got===$expected,
            "Cabecera '$label' en ".$group['mode']." debe ser ".var_export($expected,true).
            ', recibido '.var_export($got,true));
    }
}

// Validar que un alias no represente dos campos distintos dentro del mismo importador.
foreach(['bayer_inventario','bayer_precios','pucchun_productos'] as $mode){
    $aliases=[];
    foreach(ProductExcelHeaderAliases::fields($mode) as $field=>$names){
        foreach(array_unique(array_merge([$field],$names)) as $raw){
            $canonical=ProductExcelHeaderAliases::normalize($raw);
            ensure(!isset($aliases[$canonical]) || $aliases[$canonical]===$field,
                "Alias '$raw' ambiguo entre $field y ".($aliases[$canonical]??''));
            $aliases[$canonical]=$field;
        }
    }
}

// Encabezados grises reales desplazados a la columna I y fuera de la primera fila.
$sheet=[
    2=>[4=>'REPORTE DE INVENTARIOS CANAL - CLIENTE'],
    4=>[8=>'Empresa',9=>'BAYER',11=>'Año',12=>'2026'],
    5=>[8=>'País',9=>'PE',11=>'Último mes cerrado',12=>'9'],
    10=>[
        1=>'Year',2=>'Month',3=>'Country',4=>'Country Code',
        5=>'Region Code',6=>'Region',7=>'Channel Code',
        8=>'Channel Name (SAP)',9=>'CÓDIGO',
        10=>'PRODUCTO / PRESENTACIÓN',11=>'MARCA',
        12=>'CANTIDAD / UNIDADES'
    ],
    11=>[
        1=>'2026',2=>'9',3=>'PE',4=>'PE',5=>'-',6=>'-',7=>'-',8=>'-',
        9=>'DEMO990101',10=>'PRODUCTO DEMO 4X4L BOT PE',11=>'DEMO',12=>'274'
    ],
    12=>[
        1=>'2026',2=>'9',3=>'PE',4=>'PE',5=>'-',6=>'-',7=>'-',8=>'-',
        9=>'DEMO990102',10=>'PRODUCTO DEMO 12X1L BOT PE',11=>'DEMO',12=>'0'
    ]
];
$rows=(new BayerWorkbookReader())->parseMatrix($sheet,'SET_26');
ensure(count($rows)===2 &&
       $rows[0]['codigo_bayer']==='DEMO990101' &&
       $rows[0]['nombre_bayer']==='PRODUCTO DEMO 4X4L BOT PE' &&
       $rows[0]['cantidad']==='274' &&
       $rows[0]['periodo']==='2026-09',
       'Bayer identifica el encabezado gris de la muestra, no los códigos auxiliares');
ensure($rows[1]['cantidad']==='0','Se preserva el stock en cero');

// Precio y vigencia reconocidos en mayúscula, con tildes y signos.
$commercial=[
    2=>[1=>'LISTA COMERCIAL BAYER'],
    9=>[1=>'CÓDIGO BAYER',2=>'PRODUCTO / PRESENTACIÓN',
         3=>'MONEDA',4=>'PRECIO SUGERIDO SIN IGV',5=>'IGV %',
         6=>'PRECIO SUGERIDO CON IGV',7=>'DESCUENTO DEALER %',
         8=>'VIGENCIA DESDE',9=>'VIGENCIA HASTA'],
    10=>[1=>'DEMO990101',2=>'PRODUCTO DEMO 4X4L BOT PE',3=>'PEN',
          4=>'100.00',5=>'18',6=>'118.00',7=>'8',
          8=>'2026-11-02',9=>'2026-12-31']
];
$price=(new BayerPriceWorkbookReader())->parseMatrix($commercial,'PRECIOS_DEMO');
ensure(count($price)===1 &&
       $price[0]['codigo_bayer']==='DEMO990101' &&
       $price[0]['precio_sugerido_sin_igv']==='100.00' &&
       $price[0]['precio_sugerido_con_igv']==='118.00' &&
       $price[0]['descuento_dealer_porcentaje']==='8' &&
       $price[0]['vigencia_desde']==='2026-11-02',
       'Precio comercial reconocido por variantes de cabeceras');
$dup=$sheet;
$dup[10][13]='SKU BAYER';
rejects(fn()=>(new BayerWorkbookReader())->parseMatrix($dup,'SET_26'),HttpException::class);
ensure(WarehouseExcelHeader::columns([1=>'CÓDIGO DE ALMACÉN',2=>'NOMBRE DEL ALMACÉN'])[ 'almacen_nombre']===2,
    'Almacén admite variantes con acentos');

echo 'BAYER/Pucchun HEADER ALIASES OK ('.$GLOBALS['checks'].' checks)'.PHP_EOL;

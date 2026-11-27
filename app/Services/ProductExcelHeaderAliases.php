<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Diccionario controlado de cabeceras para archivos originales XLSX/CSV.
 * Una columna solo se relaciona si su nombre normalizado coincide
 * exactamente con un alias: nunca por prefijos ni por similitud.
 */
final class ProductExcelHeaderAliases
{
    public static function normalize(string $value): string
    {
        return WarehouseExcelHeader::key($value);
    }

    /** @return array<string,list<string>> */
    public static function fields(string $mode): array
    {
        $identity=[
            'codigo_bayer'=>[
                'codigo','cod','cod_bayer','codigo_bayer','cod_producto','codigo_producto',
                'codigo_de_producto','codigo_del_producto','codigo_producto_bayer',
                'codigo_material','cod_material','material_id','material_code',
                'material','sku','sku_bayer','codigo_sku','sku_producto',
                'codigo_sap','cod_sap','codigo_bayer_sap','sap_codigo',
                'codigo_articulo','cod_articulo','codigo_de_articulo',
                'item_code','product_code','id_producto','producto_codigo',
                'codigo_referencia','referencia_producto','codigo_interno',
                'codigo_n','codigo_numero'
            ],
            'nombre_bayer'=>[
                'nombre_bayer','producto','producto_presentacion','producto_y_presentacion',
                'producto_presentacion_comercial','producto_presentacion_sap',
                'presentacion','presentacion_comercial','nombre_producto',
                'nombre_del_producto','nombre_comercial','producto_descripcion',
                'producto_nombre','descripcion','descripcion_producto',
                'descripcion_del_producto','descripcion_comercial',
                'descripcion_material','material_description','product_name',
                'product_description','product_presentation','description',
                'articulo','nombre_articulo','producto_detalle'
            ],
        ];
        $warehouse=WarehouseExcelHeader::fields();
        $inventory=[
            'marca'=>['marca','brand','marca_producto','marca_comercial','nombre_marca'],
            'cantidad'=>[
                'cantidad','cant','cant_unidades','cantidad_unidad','cantidad_unidades',
                'cantidad_en_unidades','cantidad_de_unidades','cantidad_unidades_stock',
                'cantidad_stock','cantidad_reportada','cantidad_disponible',
                'cantidad_actual','cantidad_total','cantidad_existencias',
                'stock','stock_actual','stock_reportado','stock_bayer',
                'stock_unidades','stock_disponible','inventario','inventario_actual',
                'existencia','existencias','existencias_disponibles',
                'unidades','unidades_disponibles','unidades_en_stock',
                'saldo','saldo_unidades','qty','quantity','quantity_units',
                'stock_qty','stock_quantity','inventory_qty'
            ],
            'year'=>['year','ano','anio','ano_reporte','anio_reporte',
                'ano_inventario','anio_inventario','ejercicio','ejercicio_fiscal'],
            'month'=>['month','mes','mes_reporte','mes_inventario','mes_cerrado',
                'ultimo_mes_cerrado','mes_de_reporte'],
            'periodo'=>['periodo','periodo_stock','periodo_inventario',
                'periodo_reporte','mes_ano','mes_anio','year_month','reporting_period']
        ];
        $prices=[
            'moneda'=>['moneda','currency','divisa','tipo_moneda',
                'currency_code','codigo_moneda'],
            'precio_sugerido_sin_igv'=>[
                'precio_sugerido_sin_igv','precio_sin_igv','sugerido_sin_igv',
                'precio_neto','precio_neto_soles','precio_neto_pen',
                'precio_base_sin_igv','precio_lista_sin_igv',
                'precio_de_lista_sin_igv','precio_venta_sin_igv',
                'valor_venta_sin_igv','valor_unitario_sin_igv',
                'precio_sugerido_s_igv','precio_s_igv',
                'precio_sugerido_sin_impuesto','precio_sin_impuestos',
                'precio_referencial_sin_igv','precio_dealer_sin_igv',
                'net_price','price_ex_vat','price_ex_tax'
            ],
            'igv_porcentaje'=>[
                'igv_porcentaje','igv','igv_pct','igv_porc','tasa_igv',
                'tasa_de_igv','porcentaje_igv','porcentaje_de_igv',
                'igv_percent','igv_percentage','tax_rate','vat_rate'
            ],
            'precio_sugerido_con_igv'=>[
                'precio_sugerido_con_igv','precio_con_igv','sugerido_con_igv',
                'precio_bruto','precio_final','precio_final_con_igv',
                'precio_lista_con_igv','precio_de_lista_con_igv',
                'precio_venta_con_igv','precio_unitario_con_igv',
                'precio_sugerido_c_igv','precio_c_igv',
                'precio_sugerido_incluye_igv','precio_igv_incluido',
                'precio_referencial_con_igv','precio_dealer_con_igv',
                'gross_price','price_inc_vat','price_inc_tax'
            ],
            'descuento_dealer_porcentaje'=>[
                'descuento_dealer_porcentaje','descuento_dealer',
                'descuento_distribuidor','descuento_distribuidor_porcentaje',
                'descuento_porcentaje','descuento','descuento_pct',
                'porcentaje_descuento','descuento_comercial','descuento_comercial_pct',
                'dscto','dscto_pct','dcto','dcto_pct','discount','dealer_discount'
            ],
            'vigencia_desde'=>[
                'vigencia_desde','fecha_lista','fecha_inicio','desde','fecha_desde',
                'inicio_vigencia','fecha_inicio_vigencia','fecha_vigencia_inicio',
                'fecha_de_inicio','vigente_desde','fecha_de_lista',
                'valid_from','start_date','effective_date'
            ],
            'vigencia_hasta'=>[
                'vigencia_hasta','fecha_fin','hasta','fecha_hasta',
                'fin_vigencia','fecha_fin_vigencia','fecha_vigencia_fin',
                'fecha_final','fecha_vencimiento','vigente_hasta',
                'valid_until','end_date','expiration_date'
            ]
        ];

        $pucchun=[
            'codigo'=>['codigo','cod','cod_producto','codigo_producto','codigo_de_producto',
                'codigo_del_producto','codigo_interno','codigo_pucchun',
                'codigo_articulo','item_code','sku','sku_producto','id_producto'],
            'nombre'=>['nombre','nombre_producto','producto','descripcion',
                'descripcion_producto','nombre_del_producto','producto_descripcion',
                'producto_presentacion','producto_y_presentacion','articulo',
                'nombre_articulo','product_name','product_description'],
            'tipo_art'=>['tipo_art','tipo_articulo','tipo_de_articulo',
                'tipo_producto','clase_articulo'],
            'unidad_codigo'=>['unidad_codigo','codigo_unidad','codigo_unidad_medida',
                'unidad_base','unidad_medida','unidad_de_medida',
                'unidad','um','u_m','und_medida','unidad_comercial','uom'],
            'categoria'=>['categoria','categoria_producto','nombre_categoria',
                'familia','familia_producto','rubro','linea_producto'],
            'marca'=>['marca','nombre_marca','marca_producto','brand'],
            'estado'=>['estado','estado_producto','activo','estado_registro'],
        ];

        return match($mode){
            'bayer_inventario'=>array_merge($identity,$inventory,$warehouse),
            'bayer_precios'=>array_merge($identity,$prices,$warehouse),
            'pucchun_productos'=>array_merge($pucchun,$warehouse),
            default=>[],
        };
    }

    /** Resuelve un encabezado y devuelve el campo del importador, o null. */
    public static function resolve(string $header,string $mode): ?string
    {
        $normalized=self::normalize($header);
        if($normalized==='')return null;
        foreach(self::fields($mode) as $field=>$aliases)
            if($normalized===$field || in_array($normalized,$aliases,true))return $field;
        return null;
    }
}

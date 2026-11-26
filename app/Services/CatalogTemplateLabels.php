<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Etiquetas en español exclusivamente en la interfaz de Excel. Las claves
 * internas no se exponen, pero las plantillas antiguas siguen importándose.
 * La combinación de claves esperadas evita confundir "código" de cliente
 * con "código" de producto o una clave oficial Bayer.
 */
final class CatalogTemplateLabels
{
    private const COMMON = [
        'codigo'=>'Código',
        'nombre'=>'Nombre',
        'estado'=>'Estado (1 activo / 0 inactivo)',
        'tipo_doc'=>'Tipo de documento',
        'nro_doc'=>'Número de documento (DNI / RUC)',
        'razon_social'=>'Razón social o nombre completo',
        'departamento'=>'Departamento',
        'provincia'=>'Provincia',
        'distrito'=>'Distrito',
        'tipo_art'=>'Tipo de producto',
        'unidad_codigo'=>'Código de unidad de medida',
        'categoria'=>'Categoría',
        'marca'=>'Marca',
        'almacen_nombre'=>'Nombre del almacén',
        'almacen_codigo'=>'Código del almacén',
        'sucursal_codigo'=>'Código de sucursal',
        'precio_venta'=>'Precio de venta (S/)',
        'stock_actual'=>'Cantidad de existencias',
        'dni'=>'DNI del vendedor',
        'nombres'=>'Nombres',
        'apellidos'=>'Apellidos',
        'email'=>'Correo electrónico',
        'ruc'=>'RUC',
        'empresa_ruc'=>'RUC de la empresa',
        'nombre_comercial'=>'Nombre comercial',
        'direccion'=>'Dirección',
        'tipo'=>'Tipo',
        'abreviatura'=>'Abreviatura',
        'factor_base'=>'Factor de conversión',
        'sunat_code'=>'Código SUNAT',
        'codigo_interno'=>'Código Pucchún',
        'codigo_bayer'=>'Código externo Bayer',
        'nombre_bayer'=>'Nombre registrado en Bayer',
        'partner_codigo'=>'Código de empresa aliada',
        'valid_from'=>'Vigencia desde',
        'valid_until'=>'Vigencia hasta',
        'cantidad'=>'Cantidad / unidades',
        'year'=>'Año',
        'month'=>'Mes',
        'periodo'=>'Período de inventario',
        'moneda'=>'Moneda',
        'precio_sugerido_sin_igv'=>'Precio sin IGV (S/)',
        'igv_porcentaje'=>'IGV (%)',
        'precio_sugerido_con_igv'=>'Precio con IGV (S/)',
        'descuento_dealer_porcentaje'=>'Descuento (%)',
        'vigencia_desde'=>'Fecha inicial del precio',
        'vigencia_hasta'=>'Fecha final del precio',
    ];

    public static function label(string $key,string $scope=''): string
    {
        return match(true){
            $key==='codigo_bayer' && $scope==='productos'=>'CÓDIGO BAYER',
            $key==='nombre_bayer' && $scope==='productos'=>'PRODUCTO / PRESENTACIÓN',
            $key==='cantidad_unidades' && $scope==='productos'=>'CANTIDAD / UNIDADES',
            $key==='precio_sugerido_sin_igv' && $scope==='productos'=>'PRECIO SIN IGV',
            $key==='precio_sugerido_con_igv' && $scope==='productos'=>'PRECIO CON IGV',
            $key==='igv_porcentaje' && $scope==='productos'=>'IGV',
            $key==='vigencia_desde' && $scope==='productos'=>'FECHA INICIO',
            $key==='vigencia_hasta' && $scope==='productos'=>'FECHA FIN',
            $key==='descuento_dealer_porcentaje' && $scope==='productos'=>'DESCUENTO',
            $key==='marca' && $scope==='productos'=>'MARCA',
            $key==='cantidad' && $scope==='productos'=>'CANTIDAD / UNIDADES',
            $key==='periodo' && $scope==='productos'=>'PERÍODO',
            $key==='year' && $scope==='productos'=>'AÑO',
            $key==='month' && $scope==='productos'=>'MES',
            $key==='almacen_nombre' && $scope==='productos'=>'ALMACÉN',
            $key==='codigo' && $scope==='productos'=>'Código del producto Pucchún',
            $key==='codigo' && $scope==='clientes'=>'Código del cliente (opcional)',
            $key==='codigo' && $scope==='almacenes'=>'Código de almacén',
            $key==='codigo' && $scope==='sucursales'=>'Código de sucursal',
            $key==='codigo' && $scope==='vendedores'=>'Código de vendedor',
            $key==='codigo' && $scope==='proveedores'=>'Código de proveedor',
            $key==='codigo_interno' && $scope==='clientes'=>'Número de documento Pucchún',
            $key==='codigo_interno' && $scope==='unidades'=>'Código de unidad Pucchún',
            $key==='codigo_interno' && $scope==='almacenes'=>'Código de almacén Pucchún',
            $key==='codigo_interno' && $scope==='sucursales'=>'Código de sucursal Pucchún',
            $key==='codigo_interno' && $scope==='vendedores'=>'Código de vendedor Pucchún',
            $key==='codigo_bayer' && $scope==='clientes'=>'Identificador de cliente en Bayer',
            $key==='codigo_bayer' && $scope==='unidades'=>'Código de unidad en Bayer',
            $key==='codigo_bayer' && $scope==='almacenes'=>'Código de almacén en Bayer',
            $key==='codigo_bayer' && $scope==='sucursales'=>'Código de sucursal en Bayer',
            $key==='codigo_bayer' && $scope==='vendedores'=>'Código de vendedor en Bayer',
            $key==='codigo_bayer' && $scope==='productos'=>'Código oficial del producto Bayer',
            $key==='nombre_bayer' && $scope==='clientes'=>'Nombre del cliente en Bayer',
            $key==='nombre_bayer' && $scope==='unidades'=>'Nombre de unidad en Bayer',
            $key==='nombre_bayer' && $scope==='almacenes'=>'Nombre de almacén en Bayer',
            $key==='nombre_bayer' && $scope==='sucursales'=>'Nombre de sucursal en Bayer',
            $key==='nombre_bayer' && $scope==='vendedores'=>'Nombre de vendedor en Bayer',
            default=>self::COMMON[$key]??ucfirst(str_replace('_',' ',$key))
        };
    }

    public static function normalize(string $raw): string
    {
        return WarehouseExcelHeader::key($raw);
    }

    /**
     * Devuelve únicamente una clave presente en el tipo de catálogo seleccionado.
     * No se utilizan aproximaciones que puedan mezclar clientes y productos.
     */
    public static function resolve(string $heading,array $expected,string $scope=''): ?string
    {
        $normal=self::normalize($heading);
        if($normal==='')return null;
        foreach(array_keys($expected) as $key){
            if($normal===self::normalize($key)
               || $normal===self::normalize(self::label($key,$scope)))return $key;
        }
        return null;
    }

    public static function scope(string $title,array $columns): string
    {
        if(isset($columns['codigo_interno'])) {
            $name=mb_strtolower($title,'UTF-8');
            foreach(['clientes','unidades','almacenes','sucursales','vendedores','productos'] as $tab)
                if(str_contains($name,mb_substr($tab,0,5,'UTF-8')))return $tab;
            return 'homologacion';
        }
        $name=mb_strtolower($title,'UTF-8');
        foreach(['clientes','productos','proveedores','vendedores','sucursales','almacenes','unidades'] as $tab)
            if(str_contains($name,mb_substr($tab,0,5,'UTF-8')))return $tab;
        return '';
    }
}

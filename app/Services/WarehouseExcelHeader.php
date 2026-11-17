<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Ubicación de almacén desde una cabecera o una columna Excel.
 * Conserva los nombres completos: no se emparejan prefijos ni coincidencias parciales.
 */
final class WarehouseExcelHeader
{
    public static function key(string $text): string
    {
        $text=mb_strtolower(trim($text),'UTF-8');
        $text=strtr($text,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return trim(preg_replace('/[^a-z0-9]+/u','_',$text)??'','_');
    }

    public static function fields(): array
    {
        return [
            'almacen_nombre'=>[
                'almacen','almacen_nombre','nombre_almacen','nombre_del_almacen',
                'nombre_de_almacen','almacenamiento','nombre_almacenamiento',
                'almacen_destino','almacen_origen','almacen_principal',
                'ubicacion_almacen','ubicacion_de_almacen',
                'warehouse','warehouse_name','warehouse_location',
                'deposito','nombre_deposito','centro_almacenamiento',
                'centro_distribucion','almacen_bayer','almacen_pucchun'
            ],
            'almacen_codigo'=>[
                'almacen_codigo','codigo_almacen','codigo_de_almacen',
                'codigo_del_almacen','cod_almacen','cod_alm',
                'id_almacen','warehouse_code','warehouse_id'
            ],
            'sucursal_codigo'=>[
                'sucursal_codigo','codigo_sucursal','codigo_de_sucursal',
                'codigo_de_la_sucursal','cod_sucursal','cod_sede',
                'sede_codigo','codigo_sede','branch_code'
            ],
        ];
    }

    public static function columns(array $header): array
    {
        $result=[];
        foreach($header as $column=>$value){
            $k=self::key((string)$value);
            foreach(self::fields() as $field=>$aliases){
                if(in_array($k,$aliases,true))$result[$field]=(int)$column;
            }
        }
        return $result;
    }

    /**
     * Captura "Almacén: Principal Chincha" o "Almacén | Principal Chincha"
     * en filas anteriores al encabezado tabular de productos.
     */
    public static function metadata(array $matrix,int $headerRow): array
    {
        $found=[];
        foreach($matrix as $index=>$cells){
            if((int)$index >= $headerRow)break;
            ksort($cells);
            foreach($cells as $col=>$raw){
                $raw=trim((string)$raw);
                if($raw==='')continue;
                $label=$raw;$value='';
                if(preg_match('/^([^:：]+)\s*[:：]\s*(.+)$/u',$raw,$matches)){
                    $label=trim($matches[1]);$value=trim($matches[2]);
                }
                foreach(self::fields() as $field=>$aliases){
                    if(!in_array(self::key($label),$aliases,true))continue;
                    if($value===''){
                        $next=$cells[(int)$col+1]??'';
                        $value=trim((string)$next);
                    }
                    // No interprete otra etiqueta como valor accidental.
                    if($value!==''&&!in_array(self::key($value),array_merge(...array_values(self::fields())),true))
                        $found[$field]=$value;
                }
            }
        }
        return $found;
    }

    public static function values(array $row): array
    {
        $data=['almacen_nombre'=>'','almacen_codigo'=>'','sucursal_codigo'=>''];
        foreach($data as $key=>$_)$data[$key]=trim((string)($row[$key]??''));
        return $data;
    }

    public static function merge(array $cells,array $columns,array $metadata): array
    {
        $out=[];
        foreach(['almacen_nombre','almacen_codigo','sucursal_codigo'] as $field){
            $col=$columns[$field]??null;
            $columnValue=$col!==null?trim((string)($cells[$col]??'')):'';
            $out[$field]=$columnValue!==''?$columnValue:(string)($metadata[$field]??'');
        }
        return $out;
    }

    public static function hasValue(array $row): bool
    {
        foreach(['almacen_nombre','almacen_codigo'] as $field)
            if(trim((string)($row[$field]??''))!=='')return true;
        return false;
    }
}

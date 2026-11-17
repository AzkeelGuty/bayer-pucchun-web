<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Vista de control del Excel de guías: un encabezado por guía y sus
 * productos debajo. No agrega precios inexistentes ni mezcla unidades
 * incompatibles (p. ej., LTR con KGM).
 *
 * La salida JSON/API permanece con su estructura original por línea.
 */
final class GuideExcelLayout
{
    public static function build(array $records): array
    {
        $keys=[
            'guideNumber','guideDate','customerDocument','customerName',
            'sourceDocument','salesperson','branch','productCode',
            'productName','unitCode','quantity','destination'
        ];
        $labels=[
            'N.º DE GUÍA','FECHA','DOCUMENTO CLIENTE','CLIENTE',
            'DOCUMENTO DE VENTA','VENDEDOR','SUCURSAL','CÓDIGO PRODUCTO',
            'PRODUCTO / PRESENTACIÓN','UNIDAD','CANTIDAD','DESTINO'
        ];
        $groups=[];
        $index=0;
        foreach($records as $record){
            $index++;
            $number=trim((string)($record['documentNumber']??''));
            // Los documentos publicados deben tener número; si faltara, nunca
            // asociar accidentalmente dos guías distintas en una sola.
            $key=$number!==''?'numero:'.$number:'fila:'.$index;
            if(!isset($groups[$key]))$groups[$key]=[];
            $groups[$key][]=$record;
        }

        $rows=[];
        foreach($groups as $lines){
            $first=$lines[0];
            $number=(string)($first['documentNumber']??'');
            $customer=(string)($first['customerName']??'');
            $customerId=(string)($first['customerId']??'');
            $destination=array_values(array_filter(array_map(
                static fn(string $key):string=>trim((string)($first[$key]??'')),
                ['district','province','department']
            ),static fn(string $s):bool=>$s!==''));
            $rows[]=array_merge(['_kind'=>'guide'],array_fill_keys($keys,''),[
                'guideNumber'=>$number,
                'guideDate'=>(string)($first['documentDate']??''),
                'customerDocument'=>$customerId,
                'customerName'=>$customer,
                'sourceDocument'=>(string)($first['sourceDocumentNumber']??''),
                'salesperson'=>(string)($first['salesName']??''),
                'branch'=>(string)($first['branchName']??''),
                'destination'=>implode(' / ',$destination),
            ]);

            $totals=[];$products=[];
            foreach($lines as $line){
                $unit=trim((string)($line['measureUnit']??'')) ?: 'Sin unidad';
                $code=(string)($line['materialId']??'');
                $qty=(float)($line['quantity']??0);
                $totals[$unit]=($totals[$unit]??0)+$qty;
                $products[$code!==''?$code:(string)($line['materialName']??'')]=true;
                $rows[]=array_merge(['_kind'=>'detail'],array_fill_keys($keys,''),[
                    'productCode'=>$code,
                    'productName'=>(string)($line['materialName']??''),
                    'unitCode'=>$unit,
                    'quantity'=>$line['quantity']??null,
                ]);
            }
            foreach($totals as $unit=>$quantity){
                $rows[]=array_merge(['_kind'=>'subtotal'],array_fill_keys($keys,''),[
                    'productName'=>'TOTAL DE GUÍA · '.count($products).' producto(s)',
                    'unitCode'=>$unit,
                    'quantity'=>$quantity,
                ]);
            }
        }
        return [
            'keys'=>$keys,'labels'=>$labels,'rows'=>$rows,
            'guide_count'=>count($groups),'product_lines'=>count($records)
        ];
    }
}

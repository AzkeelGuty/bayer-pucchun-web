<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Vista de control del Excel de guías: un encabezado por guía y sus
 * productos debajo. Solo incluye precios reales registrados y no mezcla unidades
 * incompatibles (p. ej., LTR con KGM). El destino no contiene desgloses.
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
            'productName','unitCode','quantity','unitValue','lineTotal',
            'taxAmount','finalTotal','destination'
        ];
        $labels=[
            'N.º DE GUÍA','FECHA','DOCUMENTO CLIENTE','CLIENTE',
            'DOCUMENTO DE VENTA','VENDEDOR','SUCURSAL','CÓDIGO PRODUCTO',
            'PRODUCTO / PRESENTACIÓN','UNIDAD','CANTIDAD','VALOR UNITARIO (S/)',
            'IMPORTE (S/)','IGV (S/)','TOTAL (S/)','DESTINO'
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

            // No calcular totales monetarios si alguna línea no tiene precio.
            // Usar la misma regla de IGV y redondeo por línea que el ERP.
            $allPriced=count($lines)>0 && count(array_filter(
                $lines,static fn(array $line):bool=>
                    isset($line['unitValue']) && trim((string)$line['unitValue'])!==''
            ))===count($lines);
            $money=null;
            if($allPriced){
                $mode=(string)($first['guideTaxMode']??'');
                $mode=in_array($mode,['ADICIONAL','INCLUIDO'],true)?$mode:null;
                $money=CommercialTotalsService::summarize(array_map(
                    static fn(array $line):array=>[
                        'cantidad'=>(string)($line['quantity']??''),
                        'valor_unitario'=>(string)$line['unitValue'],
                    ],$lines
                ),$mode);
            }
            $totals=[];$products=[];$quantityTotal=0;
            foreach($lines as $i=>$line){
                $unit=trim((string)($line['measureUnit']??'')) ?: 'Sin unidad';
                $code=(string)($line['materialId']??'');
                $qty=(float)($line['quantity']??0);
                $totals[$unit]=($totals[$unit]??0)+$qty;
                $quantityTotal+=$qty;
                $products[$code!==''?$code:(string)($line['materialName']??'')]=true;
                $amounts=$money['lineas'][$i]??null;
                $price=$line['unitValue']??null;
                $rows[]=array_merge(['_kind'=>'detail'],array_fill_keys($keys,''),[
                    'productCode'=>$code,
                    'productName'=>(string)($line['materialName']??''),
                    'unitCode'=>$unit,
                    'quantity'=>$line['quantity']??null,
                    'unitValue'=>$price===null || $price===''?null:(float)$price,
                    'lineTotal'=>$amounts===null?null:$amounts['importe']/100,
                    'taxAmount'=>$money!==null && $money['igv']!==null
                        ? $amounts['igv']/100 : null,
                    'finalTotal'=>$amounts===null?null:$amounts['total']/100,
                ]);
            }
            // Una sola fila final por guía. La suma de cantidades es un
            // indicador de control: no convierte ni equipara LTR, KGM o NIU.
            $mixed=count($totals)>1;
            $rows[]=array_merge(['_kind'=>'total'],array_fill_keys($keys,''),[
                'productName'=>'TOTAL DE GUÍA · '.count($products).' producto(s)',
                'unitCode'=>$mixed?'MIXTA':(string)(array_key_first($totals)??''),
                'quantity'=>$quantityTotal,
                'lineTotal'=>$money===null?null:$money['importe']/100,
                'taxAmount'=>$money!==null && $money['igv']!==null
                    ? $money['igv']/100 : null,
                'finalTotal'=>$money===null?null:$money['total']/100,
                'destination'=>'',
            ]);
        }
        return [
            'keys'=>$keys,'labels'=>$labels,'rows'=>$rows,
            'guide_count'=>count($groups),'product_lines'=>count($records)
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Importe por línea = cantidad × valor unitario.
 * Cálculos enteros en céntimos para evitar diferencias entre navegador y BD.
 * El desagregado del 18 % solo se muestra si el usuario eligió la modalidad.
 */
final class CommercialTotalsService
{
    public const TAX_RATE=18;

    public static function priceCents(mixed $value): int
    {
        if(!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('El valor unitario debe ser un decimal.');
        }
        $s=trim((string)$value);
        if(!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D',$s))
            throw new InvalidArgumentException('El valor unitario debe tener hasta dos decimales.');
        [$whole,$fraction]=array_pad(explode('.',$s,2),2,'');
        $whole=ltrim($whole,'0')?:'0';
        if(strlen($whole)>12)throw new InvalidArgumentException('El valor unitario está fuera de rango.');
        return (int)$whole*100+(int)str_pad($fraction,2,'0');
    }

    public static function quantity(mixed $value): int
    {
        if(!is_string($value) && !is_int($value))
            throw new InvalidArgumentException('La cantidad debe ser un entero positivo.');
        $s=trim((string)$value);
        if(!preg_match('/^\d+(?:\.0{1,3})?$/D',$s))
            throw new InvalidArgumentException('La cantidad debe ser un entero positivo.');
        $number=ltrim(explode('.',$s,2)[0],'0')?:'0';
        if(strlen($number)>8 || (int)$number<1)
            throw new InvalidArgumentException('La cantidad está fuera de rango.');
        return (int)$number;
    }

    public static function money(int $cents): string
    {
        return 'S/ '.number_format($cents/100,2,'.',',');
    }

    /**
     * @return array{lineas:list<array>,subtotal:?int,igv:?int,total:?int,importe:int,modalidad:?string,
     *               valorizado:bool,items:int,cantidades:array<string,int>}
     */
    public static function summarize(array $lines,?string $mode,array $unitNames=[]): array
    {
        if($mode!==null && !in_array($mode,['INCLUIDO','ADICIONAL'],true))
            throw new InvalidArgumentException('Modalidad de IGV inválida.');
        $rows=[]; $base=0; $tax=0; $total=0; $rawTotal=0; $hasPrice=false; $hasMissing=false;
        $quantities=[];
        foreach($lines as $line) {
            if(!is_array($line))throw new InvalidArgumentException('Detalle inválido.');
            $q=self::quantity($line['cantidad']??null);
            $unitKey=(string)($line['unidad_id']??'');
            $unit=$unitNames[$unitKey]??($line['unidad_label']??($unitKey!==''?'Unidad '.$unitKey:'Unidades'));
            $unit=(string)$unit;
            $quantities[$unit]=($quantities[$unit]??0)+$q;
            $p=$line['valor_unitario']??null;
            if($p===null || $p==='') {
                $hasMissing=true;
                $rows[]=['cantidad'=>$q,'importe'=>null,'subtotal'=>null,'igv'=>null,'total'=>null];
                continue;
            }
            $hasPrice=true;
            $cents=self::priceCents($p);
            $amount=$cents*$q;
            // Evitar overflow en el redondeo exacto de la tasa impositiva.
            if($amount>90000000000000 || $amount<0)
                throw new InvalidArgumentException('El importe de una línea excede el rango disponible.');
            $lineBase=$amount;
            $lineIgv=0;
            $lineTotal=$amount;
            if($mode==='INCLUIDO'){
                $lineBase=intdiv($amount*100+59,118); // redondeo al céntimo
                $lineIgv=$amount-$lineBase;
            } elseif($mode==='ADICIONAL'){
                $lineIgv=intdiv($amount*18+50,100);
                $lineTotal=$amount+$lineIgv;
            }
            $base+=$lineBase;$tax+=$lineIgv;$total+=$lineTotal;$rawTotal+=$amount;
            $rows[]=['cantidad'=>$q,'importe'=>$amount,'subtotal'=>$lineBase,
                'igv'=>$lineIgv,'total'=>$lineTotal];
        }
        if($hasMissing && $hasPrice)
            throw new InvalidArgumentException('Todas las líneas deben incluir valor unitario o ninguna.');
        if($mode!==null && (!$hasPrice || $hasMissing))
            throw new InvalidArgumentException('Para mostrar IGV deben consignarse precios en todas las líneas.');
        if($total>900000000000000)
            throw new InvalidArgumentException('El total excede el rango disponible.');
        return [
            'lineas'=>$rows,
            'subtotal'=>$mode!==null && $hasPrice?$base:null,
            'igv'=>$mode!==null && $hasPrice?$tax:null,
            'total'=>$hasPrice?($mode!==null?$total:$rawTotal):null,
            'importe'=>$rawTotal,
            'modalidad'=>$mode,
            'valorizado'=>$hasPrice,
            'items'=>count($rows),
            'cantidades'=>$quantities,
        ];
    }
}

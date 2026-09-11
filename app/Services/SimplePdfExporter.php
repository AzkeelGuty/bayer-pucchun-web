<?php
namespace App\Services;
class SimplePdfExporter {
    public function output(array $rows,string $title,string $filename): never {
        $lines=[$title,'Generado: '.date('Y-m-d H:i:s'),''];
        if($rows){$headers=array_keys($rows[0]);$lines[]=implode(' | ',$headers);$lines[]=str_repeat('-',120);foreach($rows as $r){$parts=[];foreach($r as $v)$parts[]=mb_substr((string)$v,0,24);$lines[]=implode(' | ',$parts);}}
        else $lines[]='Sin datos para los filtros seleccionados.';
        $pages=array_chunk($lines,44);$objects=[];$kids=[];$obj=1;
        $catalog=$obj++;$pagesObj=$obj++;$font=$obj++;
        foreach($pages as $pi=>$page){$pageObj=$obj++;$contentObj=$obj++;$kids[]="$pageObj 0 R";$text="BT /F1 7 Tf 30 560 Td ";$first=true;foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);if(!$first)$text.=' 0 -12 Td ';$text.='('.$safe.') Tj';$first=false;}$text.=' ET';$objects[$pageObj]="<< /Type /Page /Parent $pagesObj 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 $font 0 R >> >> /Contents $contentObj 0 R >>";$objects[$contentObj]="<< /Length ".strlen($text)." >>\nstream\n$text\nendstream";}
        $objects[$catalog]="<< /Type /Catalog /Pages $pagesObj 0 R >>";$objects[$pagesObj]="<< /Type /Pages /Kids [".implode(' ',$kids)."] /Count ".count($kids).' >>';$objects[$font]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';ksort($objects);
        $pdf="%PDF-1.4\n";$offset=[0];for($i=1;$i<$obj;$i++){$offset[$i]=strlen($pdf);$pdf.="$i 0 obj\n".($objects[$i]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 $obj\n0000000000 65535 f \n";for($i=1;$i<$obj;$i++)$pdf.=sprintf('%010d 00000 n ', $offset[$i])."\n";$pdf.="trailer\n<< /Size $obj /Root $catalog 0 R >>\nstartxref\n$xref\n%%EOF";
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'"');echo $pdf;exit;
    }
}

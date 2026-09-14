<?php
declare(strict_types=1);

namespace App\Services;

final class SimplePdfExporter
{
    private const PAGE_W = 842;
    private const PAGE_H = 595;
    private const MARGIN = 24;

    public function output(array $rows,string $filename,array $meta=[]): never
    {
        $keys=ExportPresentation::pdfKeys((string)($meta['type']??''),$rows);
        if(!$keys && $rows) $keys=ExportPresentation::keys($rows);

        $perPage=15;
        $chunks=$rows ? array_chunk($rows,$perPage) : [[]];
        $pageCount=count($chunks);
        $objects=[];
        $kids=[];
        $obj=1;
        $catalog=$obj++;
        $pagesObj=$obj++;
        $fontRegular=$obj++;
        $fontBold=$obj++;

        foreach($chunks as $pageIndex=>$pageRows){
            $pageObj=$obj++;
            $contentObj=$obj++;
            $kids[]="$pageObj 0 R";
            $stream=$this->buildPage($pageRows,$keys,$meta,$pageIndex+1,$pageCount);

            $objects[$pageObj]="<< /Type /Page /Parent $pagesObj 0 R /MediaBox [0 0 ".self::PAGE_W." ".self::PAGE_H."] /Resources << /Font << /F1 $fontRegular 0 R /F2 $fontBold 0 R >> >> /Contents $contentObj 0 R >>";
            $objects[$contentObj]="<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $objects[$catalog]="<< /Type /Catalog /Pages $pagesObj 0 R >>";
        $objects[$pagesObj]="<< /Type /Pages /Kids [".implode(' ',$kids)."] /Count ".count($kids)." >>";
        $objects[$fontRegular]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);

        $pdf="%PDF-1.4\n";
        $offset=[0];
        for($i=1;$i<$obj;$i++){
            $offset[$i]=strlen($pdf);
            $pdf.="$i 0 obj\n".($objects[$i]??'<<>>')."\nendobj\n";
        }
        $xref=strlen($pdf);
        $pdf.="xref\n0 $obj\n0000000000 65535 f \n";
        for($i=1;$i<$obj;$i++) $pdf.=sprintf('%010d 00000 n ', $offset[$i])."\n";
        $pdf.="trailer\n<< /Size $obj /Root $catalog 0 R >>\nstartxref\n$xref\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
        exit;
    }

    private function buildPage(array $rows,array $keys,array $meta,int $page,int $pages): string
    {
        [$pr,$pg,$pb]=$this->hexRgb((string)($meta['primary_color']??'075B9F'));
        [$ar,$ag,$ab]=$this->hexRgb((string)($meta['accent_color']??'168C5B'));
        $w=self::PAGE_W;
        $h=self::PAGE_H;
        $m=self::MARGIN;
        $content=[];

        // Cabecera corporativa
        $content[]=$this->fillRect(0,$h-82,$w,82,$pr,$pg,$pb);
        $content[]=$this->fillRect(0,$h-86,$w,4,$ar,$ag,$ab);
        $content[]=$this->text($m,$h-37,(string)($meta['title']??'Reporte de datos'),19,'F2',1,1,1);
        $content[]=$this->text($m,$h-56,(string)($meta['system']??'Pucchún Data Hub').' · '.(string)($meta['partner']??'Bayer'),9,'F1',.86,.93,.98);
        $content[]=$this->text($w-$m-112,$h-37,'INFORMACIÓN PUBLICADA',7.5,'F2',.86,.98,.91);
        $content[]=$this->text($w-$m-112,$h-54,'Reporte oficial de consulta',7,'F1',.86,.93,.98);

        // Metadatos
        $metaY=$h-124;
        $metaItems=[
            ['Generado por',(string)($meta['generated_by']??'Usuario autorizado')],
            ['Fecha y hora',(string)($meta['generated_at']??date('Y-m-d H:i:s'))],
            ['Registros',(string)($meta['record_count']??count($rows))],
            ['Estado',(string)($meta['status']??'Información publicada')],
        ];
        $cardGap=8;
        $cardW=($w-2*$m-3*$cardGap)/4;
        foreach($metaItems as $i=>$item){
            $x=$m+$i*($cardW+$cardGap);
            $content[]=$this->fillRect($x,$metaY-30,$cardW,42,.96,.98,.99);
            $content[]=$this->strokeRect($x,$metaY-30,$cardW,42,.84,.90,.94,.6);
            $content[]=$this->text($x+9,$metaY+1,$item[0],6.5,'F2',.35,.47,.57);
            $content[]=$this->text($x+9,$metaY-14,$this->clip($item[1],36),8,'F2',.07,.20,.31);
        }

        $content[]=$this->text($m,$metaY-48,'Filtros: '.$this->clip((string)($meta['filters']??'Sin filtros adicionales'),130),7,'F1',.35,.47,.57);

        $tableTop=$metaY-72;
        if(!$keys){
            $content[]=$this->fillRect($m,$tableTop-66,$w-2*$m,58,.97,.98,.99);
            $content[]=$this->text($m+16,$tableTop-34,'Sin datos para los filtros seleccionados.',10,'F2',.35,.47,.57);
        }else{
            $widths=$this->columnWidths($keys,$w-2*$m);
            $headerH=27;
            $rowH=22;
            $x=$m;

            foreach($keys as $idx=>$key){
                $cw=$widths[$idx];
                $content[]=$this->fillRect($x,$tableTop-$headerH,$cw,$headerH,$pr,$pg,$pb);
                $content[]=$this->strokeRect($x,$tableTop-$headerH,$cw,$headerH,1,1,1,.18);
                $label=ExportPresentation::fieldLabel($key);
                $content[]=$this->text($x+5,$tableTop-17,$this->clipForWidth($label,$cw,6.2),6.2,'F2',1,1,1);
                $x+=$cw;
            }

            $y=$tableTop-$headerH;
            foreach($rows as $ri=>$row){
                $y-=$rowH;
                $shade=$ri%2===0 ? .99 : .965;
                $content[]=$this->fillRect($m,$y,$w-2*$m,$rowH,$shade,$shade+.005,min(1,$shade+.01));
                $x=$m;
                foreach($keys as $idx=>$key){
                    $cw=$widths[$idx];
                    $content[]=$this->strokeRect($x,$y,$cw,$rowH,.88,.92,.95,.45);
                    $value=ExportPresentation::displayValue($key,$row[$key]??null);
                    $content[]=$this->text($x+5,$y+8,$this->clipForWidth($value,$cw,6.2),6.2,'F1',.12,.20,.28);
                    $x+=$cw;
                }
            }
        }

        // Pie de página
        $content[]=$this->strokeLine($m,23,$w-$m,23,.82,.88,.92,.6);
        $content[]=$this->text($m,10,'Pucchún Data Hub · Exportación generada automáticamente · Solo información publicada',6.7,'F1',.42,.52,.60);
        $content[]=$this->text($w-$m-54,10,'Página '.$page.' de '.$pages,6.7,'F2',.35,.47,.57);

        return implode("\n",$content);
    }

    private function columnWidths(array $keys,float $available): array
    {
        $weights=[
            'documentNumber'=>1.1,'documentDate'=>.75,'stockDate'=>.75,
            'customerName'=>1.5,'salesName'=>1.15,'branchName'=>1.05,
            'warehouseName'=>1.2,'materialName'=>1.55,'measureUnit'=>.62,
            'batch'=>.8,'quantity'=>.72,'unitValue'=>.78,'expirationDate'=>.82,
            'district'=>.9,'province'=>.9,'department'=>.9,
        ];
        $sum=0.0;
        foreach($keys as $key) $sum+=(float)($weights[$key]??1);
        if($sum<=0) $sum=count($keys)?:1;
        $result=[];
        foreach($keys as $key) $result[]=$available*((float)($weights[$key]??1)/$sum);
        return $result;
    }

    private function clipForWidth(string $text,float $width,float $fontSize): string
    {
        $chars=max(3,(int)floor(($width-10)/($fontSize*.50)));
        return $this->clip($text,$chars);
    }

    private function clip(string $text,int $max): string
    {
        $text=trim(preg_replace('/\s+/u',' ',$text)??$text);
        if(mb_strlen($text)<= $max) return $text;
        return rtrim(mb_substr($text,0,max(1,$max-1))).'…';
    }

    private function pdfString(string $text): string
    {
        $converted=@iconv('UTF-8','Windows-1252//TRANSLIT',$text);
        if($converted===false) $converted=$text;
        return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)','',' '],$converted);
    }

    private function text(float $x,float $y,string $text,float $size,string $font,float $r,float $g,float $b): string
    {
        return sprintf("BT /%s %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET",$font,$size,$r,$g,$b,$x,$y,$this->pdfString($text));
    }

    private function fillRect(float $x,float $y,float $w,float $h,float $r,float $g,float $b): string
    {
        return sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f",$r,$g,$b,$x,$y,$w,$h);
    }

    private function strokeRect(float $x,float $y,float $w,float $h,float $r,float $g,float $b,float $lineWidth=.5): string
    {
        return sprintf("%.3f %.3f %.3f RG %.2f w %.2f %.2f %.2f %.2f re S",$r,$g,$b,$lineWidth,$x,$y,$w,$h);
    }

    private function strokeLine(float $x1,float $y1,float $x2,float $y2,float $r,float $g,float $b,float $lineWidth=.5): string
    {
        return sprintf("%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S",$r,$g,$b,$lineWidth,$x1,$y1,$x2,$y2);
    }

    private function hexRgb(string $hex): array
    {
        $hex=preg_replace('/[^0-9A-Fa-f]/','',$hex)??'075B9F';
        if(strlen($hex)!==6) $hex='075B9F';
        return [
            hexdec(substr($hex,0,2))/255,
            hexdec(substr($hex,2,2))/255,
            hexdec(substr($hex,4,2))/255,
        ];
    }
}

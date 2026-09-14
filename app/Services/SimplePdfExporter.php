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

        $images=$this->prepareImages($meta);
        $perPage=12;
        $chunks=$rows ? array_chunk($rows,$perPage) : [[]];
        $pageCount=count($chunks);

        $objects=[];
        $kids=[];
        $obj=1;
        $catalog=$obj++;
        $pagesObj=$obj++;
        $fontRegular=$obj++;
        $fontBold=$obj++;

        foreach($images as &$image){
            $image['object']=$obj++;
            $objects[$image['object']]="<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($image['data'])." >>\nstream\n".$image['data']."\nendstream";
        }
        unset($image);

        foreach($chunks as $pageIndex=>$pageRows){
            $pageObj=$obj++;
            $contentObj=$obj++;
            $kids[]="$pageObj 0 R";
            $stream=$this->buildPage($pageRows,$keys,$meta,$images,$pageIndex+1,$pageCount);

            $xObjects='';
            if($images){
                $parts=[];
                foreach($images as $i=>$image) $parts[]='/Im'.($i+1).' '.$image['object'].' 0 R';
                $xObjects=' /XObject << '.implode(' ',$parts).' >>';
            }

            $objects[$pageObj]="<< /Type /Page /Parent $pagesObj 0 R /MediaBox [0 0 ".self::PAGE_W." ".self::PAGE_H."] /Resources << /Font << /F1 $fontRegular 0 R /F2 $fontBold 0 R >>".$xObjects." >> /Contents $contentObj 0 R >>";
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

    private function buildPage(array $rows,array $keys,array $meta,array $images,int $page,int $pages): string
    {
        [$pr,$pg,$pb]=$this->hexRgb((string)($meta['primary_color']??'075B9F'));
        [$ar,$ag,$ab]=$this->hexRgb((string)($meta['accent_color']??'168C5B'));
        $w=self::PAGE_W;
        $h=self::PAGE_H;
        $m=self::MARGIN;
        $content=[];

        // Cabecera corporativa
        $content[]=$this->fillRect(0,$h-92,$w,92,$pr,$pg,$pb);
        $content[]=$this->fillRect(0,$h-96,$w,4,$ar,$ag,$ab);
        $content[]=$this->text($m,$h-34,'REPORTE OFICIAL',7.2,'F2',.75,.91,.98);
        $content[]=$this->text($m,$h-57,(string)($meta['title']??'Reporte de datos'),19.5,'F2',1,1,1);
        $content[]=$this->text($m,$h-76,(string)($meta['system']??'Pucchún Data Hub').' · '.(string)($meta['partner']??'Bayer'),8.5,'F1',.86,.93,.98);

        // Logos configurados: Pucchún y aliado, cuando el servidor puede procesarlos.
        if($images){
            $logoX=$w-$m-58;
            foreach(array_reverse($images,true) as $index=>$image){
                $boxW=52;
                $boxH=50;
                $content[]=$this->fillRect($logoX,$h-70,$boxW,$boxH,1,1,1);
                $content[]=$this->strokeRect($logoX,$h-70,$boxW,$boxH,.82,.90,.95,.5);
                [$iw,$ih]=$this->fitImage($image['width'],$image['height'],$boxW-10,$boxH-10);
                $ix=$logoX+($boxW-$iw)/2;
                $iy=$h-70+($boxH-$ih)/2;
                $content[]=$this->image($ix,$iy,$iw,$ih,'Im'.($index+1));
                $logoX-=$boxW+8;
            }
        }else{
            $content[]=$this->text($w-$m-132,$h-46,'INFORMACIÓN PUBLICADA',7.2,'F2',.86,.98,.91);
            $content[]=$this->text($w-$m-132,$h-63,'Consulta autorizada',6.8,'F1',.86,.93,.98);
        }

        // Banda de trazabilidad
        $statusY=$h-118;
        $content[]=$this->fillRect($m,$statusY-20,$w-2*$m,20,.95,.985,.97);
        $content[]=$this->strokeRect($m,$statusY-20,$w-2*$m,20,.80,.91,.85,.45);
        $content[]=$this->text($m+9,$statusY-13,'✓ DATOS VALIDADOS Y PUBLICADOS',7,'F2',$ar,$ag,$ab);
        $content[]=$this->text($m+185,$statusY-13,'Fuente: Pucchún Data Hub · Acceso según rol · Exportación trazable',6.9,'F1',.34,.47,.57);

        // Metadatos
        $metaY=$h-171;
        $metaItems=[
            ['Generado por',(string)($meta['generated_by']??'Usuario autorizado')],
            ['Fecha y hora',(string)($meta['generated_at']??date('Y-m-d H:i:s'))],
            ['Registros',(string)($meta['record_count']??count($rows))],
            ['Formato',(string)($meta['format']??'PDF')],
            ['Archivo',(string)($meta['filename']??'reporte.pdf')],
        ];
        $cardGap=7;
        $cardW=($w-2*$m-4*$cardGap)/5;
        foreach($metaItems as $i=>$item){
            $x=$m+$i*($cardW+$cardGap);
            $content[]=$this->fillRect($x,$metaY-28,$cardW,40,.965,.982,.992);
            $content[]=$this->strokeRect($x,$metaY-28,$cardW,40,.84,.90,.94,.55);
            $content[]=$this->text($x+8,$metaY+1,$item[0],6.1,'F2',.35,.47,.57);
            $content[]=$this->text($x+8,$metaY-13,$this->clip($item[1],28),7.4,'F2',.07,.20,.31);
        }

        $content[]=$this->text($m,$metaY-47,'Filtros aplicados: '.$this->clip((string)($meta['filters']??'Sin filtros adicionales'),125),6.8,'F1',.35,.47,.57);
        $content[]=$this->text($w-$m-120,$metaY-47,'Estado: '.(string)($meta['status']??'Información publicada'),6.8,'F2',$ar,$ag,$ab);

        $tableTop=$metaY-69;
        $content[]=$this->text($m,$tableTop+12,'DETALLE DE INFORMACIÓN PUBLICADA',7,'F2',.18,.35,.49);

        if(!$keys){
            $content[]=$this->fillRect($m,$tableTop-66,$w-2*$m,58,.97,.98,.99);
            $content[]=$this->text($m+16,$tableTop-34,'Sin datos para los filtros seleccionados.',10,'F2',.35,.47,.57);
        }else{
            $widths=$this->columnWidths($keys,$w-2*$m);
            $headerH=25;
            $rowH=21;
            $x=$m;

            foreach($keys as $idx=>$key){
                $cw=$widths[$idx];
                $content[]=$this->fillRect($x,$tableTop-$headerH,$cw,$headerH,$pr,$pg,$pb);
                $content[]=$this->strokeRect($x,$tableTop-$headerH,$cw,$headerH,1,1,1,.18);
                $label=ExportPresentation::fieldLabel($key);
                $content[]=$this->text($x+4.5,$tableTop-16,$this->clipForWidth($label,$cw,6.0),6.0,'F2',1,1,1);
                $x+=$cw;
            }

            $y=$tableTop-$headerH;
            foreach($rows as $ri=>$row){
                $y-=$rowH;
                $shade=$ri%2===0 ? .995 : .965;
                $content[]=$this->fillRect($m,$y,$w-2*$m,$rowH,$shade,min(1,$shade+.003),min(1,$shade+.008));
                $x=$m;
                foreach($keys as $idx=>$key){
                    $cw=$widths[$idx];
                    $content[]=$this->strokeRect($x,$y,$cw,$rowH,.88,.92,.95,.42);
                    $value=ExportPresentation::displayValue($key,$row[$key]??null);
                    $font=in_array($key,['documentNumber','materialId','batch'],true)?'F2':'F1';
                    $content[]=$this->text($x+4.5,$y+7.5,$this->clipForWidth($value,$cw,6.0),6.0,$font,.12,.20,.28);
                    $x+=$cw;
                }
            }
        }

        // Pie profesional
        $content[]=$this->strokeLine($m,28,$w-$m,28,.82,.88,.92,.6);
        $content[]=$this->text($m,16,'Pucchún Data Hub · Documento generado automáticamente · Información publicada y trazable',6.5,'F1',.42,.52,.60);
        $content[]=$this->text($m,7,$this->clip((string)($meta['filename']??'reporte.pdf'),80),5.9,'F1',.55,.62,.68);
        $content[]=$this->text($w-$m-58,13,'Página '.$page.' de '.$pages,6.6,'F2',.35,.47,.57);

        return implode("\n",$content);
    }

    private function prepareImages(array $meta): array
    {
        $images=[];
        foreach([
            $meta['logo_primary_path']??null,
            $meta['logo_partner_path']??null,
        ] as $path){
            $asset=$this->prepareJpegAsset(is_string($path)?$path:'');
            if($asset!==null) $images[]=$asset;
        }
        return $images;
    }

    private function prepareJpegAsset(string $path): ?array
    {
        if($path==='' || !is_file($path)) return null;
        $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));

        if(in_array($ext,['jpg','jpeg'],true)){
            $data=@file_get_contents($path);
            $size=@getimagesize($path);
            if(is_string($data) && $size){
                return ['data'=>$data,'width'=>(int)$size[0],'height'=>(int)$size[1]];
            }
        }

        // PNG/WEBP/JPG -> JPEG mediante GD, si está disponible.
        if(function_exists('imagecreatefromstring') && function_exists('imagejpeg')){
            $raw=@file_get_contents($path);
            $src=is_string($raw)?@imagecreatefromstring($raw):false;
            if($src){
                $w=imagesx($src); $h=imagesy($src);
                $canvas=imagecreatetruecolor($w,$h);
                $white=imagecolorallocate($canvas,255,255,255);
                imagefill($canvas,0,0,$white);
                imagealphablending($canvas,true);
                imagecopy($canvas,$src,0,0,0,0,$w,$h);
                ob_start();
                imagejpeg($canvas,null,90);
                $data=(string)ob_get_clean();
                imagedestroy($canvas);
                imagedestroy($src);
                if($data!=='') return ['data'=>$data,'width'=>$w,'height'=>$h];
            }
        }

        // SVG u otros formatos: Imagick como alternativa, si el hosting lo ofrece.
        if(class_exists('Imagick')){
            try{
                $img=new \Imagick();
                $img->setBackgroundColor(new \ImagickPixel('white'));
                $img->readImage($path);
                $img->setImageBackgroundColor('white');
                if(method_exists($img,'mergeImageLayers')) $img=$img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $img->setImageFormat('jpeg');
                $img->setImageCompressionQuality(90);
                $data=$img->getImagesBlob();
                $w=$img->getImageWidth(); $h=$img->getImageHeight();
                $img->clear();
                $img->destroy();
                if($data!=='') return ['data'=>$data,'width'=>$w,'height'=>$h];
            }catch(\Throwable){
                return null;
            }
        }

        return null;
    }

    private function fitImage(int $width,int $height,float $maxW,float $maxH): array
    {
        $ratio=min($maxW/max(1,$width),$maxH/max(1,$height));
        return [max(12,$width*$ratio),max(12,$height*$ratio)];
    }

    private function image(float $x,float $y,float $w,float $h,string $name): string
    {
        return sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q",$w,$h,$x,$y,$name);
    }

    private function columnWidths(array $keys,float $available): array
    {
        $weights=[
            'documentNumber'=>1.12,'documentDate'=>.78,'stockDate'=>.78,
            'customerName'=>1.48,'salesName'=>1.16,'branchName'=>1.06,
            'warehouseName'=>1.20,'materialName'=>1.52,'measureUnit'=>.64,
            'batch'=>.82,'quantity'=>.72,'unitValue'=>.80,'expirationDate'=>.85,
            'district'=>.90,'province'=>.90,'department'=>.90,
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
        $chars=max(3,(int)floor(($width-9)/($fontSize*.49)));
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

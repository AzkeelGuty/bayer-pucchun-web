<?php
declare(strict_types=1);

namespace App\Services;

final class SimpleXlsxExporter
{
    private function col(int $n): string
    {
        $s='';
        while($n>0){$n--; $s=chr(65+$n%26).$s; $n=intdiv($n,26);}
        return $s;
    }

    private function xmlEsc(mixed $value): string
    {
        return htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
    }

    public function output(array $rows,string $filename,array $meta=[]): never
    {
        if(!class_exists('ZipArchive')){
            http_response_code(500);
            exit('La extensión ZipArchive es necesaria para generar XLSX.');
        }

        $isGuide=in_array((string)($meta['type']??''),['guides','shipments'],true);
        $guideLayout=$isGuide?GuideExcelLayout::build($rows):null;
        $displayRows=$guideLayout['rows']??$rows;
        $keys=$guideLayout['keys']??ExportPresentation::keys($rows);
        $labels=$guideLayout['labels']??ExportPresentation::labels($rows);
        if($guideLayout!==null)$meta['record_count']=
            $guideLayout['guide_count'].' guías · '.$guideLayout['product_lines'].' productos';
        $columnCount=max(8,count($keys),1);
        $dataColumnCount=max(1,count($keys));
        $lastCol=$this->col($dataColumnCount);
        $sheetLastCol=$this->col($columnCount);
        $images=$this->prepareImages($meta);

        $titleEndIndex=$images ? max(4,$columnCount-count($images)) : $columnCount;
        $titleEndCol=$this->col($titleEndIndex);

        $sheetRows=[];
        // La franja azul también cubre las celdas de los logotipos.
        $banner1=array_fill(0,$columnCount,['value'=>'','style'=>1]);
        $banner2=array_fill(0,$columnCount,['value'=>'','style'=>2]);
        $banner1[0]=['value'=>(string)($meta['title']??'Reporte de datos'),'style'=>1];
        $banner2[0]=['value'=>(string)($meta['system']??'Pucchún Data Hub')
            .' · '.(string)($meta['partner']??'Bayer'),'style'=>2];
        $sheetRows[]=$this->row(1,$banner1,34);
        $sheetRows[]=$this->row(2,$banner2,22);
        $sheetRows[]=$this->row(3,[],9);

        // Bloque de metadata en dos columnas visuales.
        $sheetRows[]=$this->row(4,[
            ['value'=>'Generado por','style'=>3],
            ['value'=>(string)($meta['generated_by']??'Usuario autorizado'),'style'=>4],
            ['value'=>'','','style'=>0],
            ['value'=>'','','style'=>0],
            ['value'=>'Fecha y hora','style'=>3],
            ['value'=>(string)($meta['generated_at']??date('Y-m-d H:i:s')),'style'=>4],
        ],22);
        $sheetRows[]=$this->row(5,[
            ['value'=>'Registros','style'=>3],
            ['value'=>(string)($meta['record_count']??count($rows)),'style'=>4],
            ['value'=>'','','style'=>0],
            ['value'=>'','','style'=>0],
            ['value'=>'Estado','style'=>3],
            ['value'=>(string)($meta['status']??'Información publicada'),'style'=>4],
        ],22);
        $sheetRows[]=$this->row(6,[
            ['value'=>'Archivo','style'=>3],
            ['value'=>(string)($meta['filename']??$filename),'style'=>4],
            ['value'=>'','','style'=>0],
            ['value'=>'','','style'=>0],
            ['value'=>'Formato','style'=>3],
            ['value'=>(string)($meta['format']??'XLSX'),'style'=>4],
        ],22);
        $sheetRows[]=$this->row(7,[
            ['value'=>'Filtros','style'=>3],
            ['value'=>(string)($meta['filters']??'Sin filtros adicionales'),'style'=>4],
        ],22);
        $sheetRows[]=$this->row(8,[['value'=>'DATOS PUBLICADOS · INFORMACIÓN VALIDADA PARA CONSULTA Y ENTREGA','style'=>5]],24);

        if($displayRows){
            $headCells=[];
            foreach($labels as $label)$headCells[]=['value'=>$label,'style'=>6];
            $sheetRows[]=$this->row(9,$headCells,28);
            $rowNum=10;
            foreach($displayRows as $ri=>$record){
                $kind=(string)($record['_kind']??'detail');
                $cells=[];
                foreach($keys as $key){
                    $value=$record[$key]??null;
                    if($isGuide){
                        $style=match($kind){
                            'guide'=>11,'subtotal'=>12,default=>$ri%2===0?7:8
                        };
                        $numeric=$key==='quantity' && is_numeric($value)
                            && in_array($kind,['detail','subtotal'],true);
                        if($numeric){
                            $cells[]=['value'=>(string)$value,
                                'style'=>$kind==='subtotal'?13:9,'numeric'=>true];
                        }else{
                            $cells[]=['value'=>$value===null?'':(string)$value,'style'=>$style];
                        }
                    }else{
                        $style=$ri%2===0?7:8;
                        $numeric=in_array($key,['quantity','unitValue','lineTotal'],true)
                            && is_numeric($value);
                        if($numeric){
                            $style=in_array($key,['unitValue','lineTotal'],true)?10:9;
                            $cells[]=['value'=>(string)$value,'style'=>$style,'numeric'=>true];
                        }else{
                            $cells[]=['value'=>ExportPresentation::displayValue($key,$value),
                                'style'=>$style];
                        }
                    }
                }
                $sheetRows[]=$this->row($rowNum,$cells,$isGuide&&$kind==='guide'?29:22);
                $rowNum++;
            }
        }else{
            $sheetRows[]=$this->row(9,[['value'=>'Sin datos para los filtros seleccionados.',
                'style'=>7]],26);
        }

        $cols=$this->columnsXml($displayRows,$keys,$labels,$columnCount);
        $mergeRefs=[
            'A1:'.$titleEndCol.'1',
            'A2:'.$titleEndCol.'2',
            'B4:D4','F4:H4',
            'B5:D5','F5:H5',
            'B6:D6','F6:H6',
            'B7:'.$sheetLastCol.'7',
            'A8:'.$sheetLastCol.'8',
        ];
        $mergeXml='<mergeCells count="'.count($mergeRefs).'">';
        foreach($mergeRefs as $ref) $mergeXml.='<mergeCell ref="'.$ref.'"/>';
        $mergeXml.='</mergeCells>';

        $filterXml=$displayRows && !$isGuide ? '<autoFilter ref="A9:'.$lastCol.(9+count($displayRows)).'"/>' : '';
        $dimension='A1:'.$sheetLastCol.max(9,9+count($displayRows));

        $drawingXml='';
        $drawingRelXml='';
        $sheetRelXml='';
        $drawingNode='';
        if($images){
            [$drawingXml,$drawingRelXml]=$this->drawingParts($images,$columnCount);
            $sheetRelXml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
                .'</Relationships>';
            $drawingNode='<drawing r:id="rId1"/>';
        }

        // El orden de los nodos sigue el esquema SpreadsheetML:
        // autoFilter debe ir antes de mergeCells. Evita que Excel repare sheet1.xml.
        $sheetXml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="'.$dimension.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .$cols
            .'<sheetData>'.implode('',$sheetRows).'</sheetData>'
            .$filterXml
            .$mergeXml
            .'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .$drawingNode
            .'</worksheet>';

        $primary=strtoupper((string)($meta['primary_color']??'075B9F'));
        $accent=strtoupper((string)($meta['accent_color']??'168C5B'));
        $styles=$this->stylesXml($primary,$accent);

        $tmp=tempnam(sys_get_temp_dir(),'xlsx');
        $zip=new \ZipArchive();
        $zip->open($tmp,\ZipArchive::OVERWRITE);

        $contentTypes='<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>';
        foreach($this->imageContentTypes($images) as $ext=>$mime){
            $contentTypes.='<Default Extension="'.$ext.'" ContentType="'.$mime.'"/>';
        }
        $contentTypes.='<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        if($images){
            $contentTypes.='<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }
        $contentTypes.='</Types>';

        $zip->addFromString('[Content_Types].xml',$contentTypes);
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>'
        );
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->xmlEsc((string)($meta['dataset']??'Datos')).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>'
        );
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>'
        );
        $zip->addFromString('xl/styles.xml',$styles);
        $zip->addFromString('xl/worksheets/sheet1.xml',$sheetXml);

        if($images){
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels',$sheetRelXml);
            $zip->addFromString('xl/drawings/drawing1.xml',$drawingXml);
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels',$drawingRelXml);
            foreach($images as $image){
                $zip->addFromString('xl/media/'.$image['name'],$image['data']);
            }
        }

        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    private function row(int $rowNumber,array $cells,float $height=18): string
    {
        $xml='<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">';
        foreach($cells as $index=>$cell){
            $value=$cell['value']??'';
            $ref=$this->col($index+1).$rowNumber;
            $style=(int)($cell['style']??0);
            if(!empty($cell['numeric'])){
                $xml.='<c r="'.$ref.'" s="'.$style.'"><v>'.$this->xmlEsc($value).'</v></c>';
            }else{
                $xml.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xmlEsc($value).'</t></is></c>';
            }
        }
        return $xml.'</row>';
    }

    private function columnsXml(array $rows,array $keys,array $labels,int $columnCount): string
    {
        $xml='<cols>';
        for($i=0;$i<$columnCount;$i++){
            if(isset($keys[$i])){
                $key=$keys[$i];
                $max=mb_strlen((string)($labels[$i]??$key));
                foreach(array_slice($rows,0,150) as $row){
                    $max=max($max,mb_strlen(ExportPresentation::displayValue($key,$row[$key]??null)));
                }
                $width=min(34,max(11,$max+2));
            }else{
                $width=14;
            }
            $n=$i+1;
            $xml.='<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';
        }
        return $xml.'</cols>';
    }

    private function stylesXml(string $primary,string $accent): string
    {
        $primary=preg_match('/^[0-9A-F]{6}$/',$primary)?$primary:'075B9F';
        $accent=preg_match('/^[0-9A-F]{6}$/',$accent)?$accent:'168C5B';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.000"/><numFmt numFmtId="165" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="20"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF17324D"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="7">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.$primary.'"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEAF3F9"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.$accent.'"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD9E6EF"/></left><right style="thin"><color rgb="FFD9E6EF"/></right><top style="thin"><color rgb="FFD9E6EF"/></top><bottom style="thin"><color rgb="FFD9E6EF"/></bottom></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="6" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function prepareImages(array $meta): array
    {
        $paths=[
            ['path'=>$meta['logo_primary_path']??null,'label'=>'Logo Pucchún'],
            ['path'=>$meta['logo_partner_path']??null,'label'=>'Logo aliado'],
        ];
        $images=[];
        $i=1;
        foreach($paths as $entry){
            $asset=$this->prepareImage((string)($entry['path']??''),$i,(string)$entry['label']);
            if($asset!==null){
                $images[]=$asset;
                $i++;
            }
        }
        return $images;
    }

    private function prepareImage(string $path,int $index,string $label): ?array
    {
        if($path==='' || !is_file($path)) return null;
        $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));

        if(in_array($ext,['png','jpg','jpeg'],true)){
            $data=@file_get_contents($path);
            $size=@getimagesize($path);
            if(!is_string($data) || !$size) return null;
            $normalized=$ext==='jpeg'?'jpg':$ext;
            return [
                'name'=>'image'.$index.'.'.$normalized,
                'ext'=>$normalized,
                'mime'=>$normalized==='png'?'image/png':'image/jpeg',
                'data'=>$data,
                'width'=>(int)$size[0],
                'height'=>(int)$size[1],
                'label'=>$label,
            ];
        }

        // WEBP: convertir a PNG si GD está disponible.
        if($ext==='webp' && function_exists('imagecreatefromwebp') && function_exists('imagepng')){
            $img=@imagecreatefromwebp($path);
            if($img){
                ob_start();
                imagepng($img);
                $data=(string)ob_get_clean();
                $width=imagesx($img);
                $height=imagesy($img);
                imagedestroy($img);
                return [
                    'name'=>'image'.$index.'.png','ext'=>'png','mime'=>'image/png',
                    'data'=>$data,'width'=>$width,'height'=>$height,'label'=>$label,
                ];
            }
        }

        // SVG: convertir a PNG cuando Imagick esté disponible. Si no, se omite
        // para mantener máxima compatibilidad con Excel.
        if($ext==='svg' && class_exists('Imagick')){
            try{
                $img=new \Imagick();
                $img->setBackgroundColor(new \ImagickPixel('transparent'));
                $img->readImage($path);
                $img->setImageFormat('png32');
                $data=$img->getImagesBlob();
                $width=$img->getImageWidth();
                $height=$img->getImageHeight();
                $img->clear();
                $img->destroy();
                if($data!==''){
                    return [
                        'name'=>'image'.$index.'.png','ext'=>'png','mime'=>'image/png',
                        'data'=>$data,'width'=>$width,'height'=>$height,'label'=>$label,
                    ];
                }
            }catch(\Throwable){
                return null;
            }
        }

        return null;
    }

    private function imageContentTypes(array $images): array
    {
        $types=[];
        foreach($images as $image) $types[$image['ext']]=$image['mime'];
        return $types;
    }

    private function drawingParts(array $images,int $columnCount): array
    {
        $drawing='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        // Insertar ambas marcas dentro de las dos últimas columnas del propio reporte.
        $baseCol=max(0,$columnCount-count($images));
        foreach($images as $i=>$image){
            $relId=$i+1;
            $col=$baseCol+$i;
            $maxW=52;
            $maxH=46;
            $ratio=min($maxW/max(1,$image['width']),$maxH/max(1,$image['height']));
            $width=max(24,(int)round($image['width']*$ratio));
            $height=max(20,(int)round($image['height']*$ratio));
            $cx=$width*9525;
            $cy=$height*9525;

            $drawing.='<xdr:oneCellAnchor>'
                .'<xdr:from><xdr:col>'.$col.'</xdr:col><xdr:colOff>75000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>95000</xdr:rowOff></xdr:from>'
                .'<xdr:ext cx="'.$cx.'" cy="'.$cy.'"/>'
                .'<xdr:pic>'
                .'<xdr:nvPicPr><xdr:cNvPr id="'.$relId.'" name="'.$this->xmlEsc((string)$image['label']).'"/><xdr:cNvPicPr/></xdr:nvPicPr>'
                .'<xdr:blipFill><a:blip r:embed="rId'.$relId.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
                .'</xdr:pic><xdr:clientData/>'
                .'</xdr:oneCellAnchor>';

            $rels.='<Relationship Id="rId'.$relId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/'.$image['name'].'"/>';
        }

        $drawing.='</xdr:wsDr>';
        $rels.='</Relationships>';
        return [$drawing,$rels];
    }
}

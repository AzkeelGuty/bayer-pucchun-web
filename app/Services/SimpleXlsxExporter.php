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

        $keys=ExportPresentation::keys($rows);
        $labels=ExportPresentation::labels($rows);
        $columnCount=max(1,count($keys));
        $lastCol=$this->col($columnCount);
        $sheetRows=[];

        $sheetRows[]=$this->row(1,[['value'=>(string)($meta['title']??'Reporte de datos'),'style'=>1]],30);
        $sheetRows[]=$this->row(2,[['value'=>(string)($meta['system']??'Pucchún Data Hub').' · '.(string)($meta['partner']??'Bayer'),'style'=>2]],20);
        $sheetRows[]=$this->row(3,[],9);

        $metaRows=[
            ['Generado por',(string)($meta['generated_by']??'Usuario autorizado')],
            ['Fecha y hora',(string)($meta['generated_at']??date('Y-m-d H:i:s'))],
            ['Total de registros',(string)($meta['record_count']??count($rows))],
            ['Estado / filtros',(string)($meta['status']??'Información publicada').' · '.(string)($meta['filters']??'Sin filtros adicionales')],
        ];
        $r=4;
        foreach($metaRows as [$label,$value]){
            $sheetRows[]=$this->row($r,[
                ['value'=>$label,'style'=>3],
                ['value'=>$value,'style'=>4],
            ],20);
            $r++;
        }
        $sheetRows[]=$this->row(8,[],8);

        if($keys){
            $cells=[];
            foreach($labels as $label) $cells[]=['value'=>$label,'style'=>5];
            $sheetRows[]=$this->row(9,$cells,24);

            $rowNum=10;
            foreach($rows as $ri=>$row){
                $cells=[];
                foreach($keys as $key){
                    $value=$row[$key]??null;
                    $style=$ri%2===0?6:7;
                    $numeric=in_array($key,['quantity','unitValue'],true) && is_numeric($value);
                    if($numeric){
                        $style=$key==='unitValue'?9:8;
                        $cells[]=['value'=>(string)$value,'style'=>$style,'numeric'=>true];
                    }else{
                        $cells[]=['value'=>ExportPresentation::displayValue($key,$value),'style'=>$style];
                    }
                }
                $sheetRows[]=$this->row($rowNum,$cells,21);
                $rowNum++;
            }
        }else{
            $sheetRows[]=$this->row(9,[['value'=>'Sin datos para los filtros seleccionados.','style'=>6]],24);
        }

        $cols=$this->columnsXml($rows,$keys,$labels);
        $mergeXml='<mergeCells count="2"><mergeCell ref="A1:'.$lastCol.'1"/><mergeCell ref="A2:'.$lastCol.'2"/></mergeCells>';
        $filterXml=$keys ? '<autoFilter ref="A9:'.$lastCol.(9+count($rows)).'"/>' : '';
        $dimension='A1:'.$lastCol.max(9,9+count($rows));

        $sheetXml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="'.$dimension.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .$cols
            .'<sheetData>'.implode('',$sheetRows).'</sheetData>'
            .$mergeXml.$filterXml
            .'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';

        $primary=strtoupper((string)($meta['primary_color']??'075B9F'));
        $accent=strtoupper((string)($meta['accent_color']??'168C5B'));
        $styles=$this->stylesXml($primary,$accent);

        $tmp=tempnam(sys_get_temp_dir(),'xlsx');
        $zip=new \ZipArchive();
        $zip->open($tmp,\ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>'
        );
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
            $ref=$this->col($index+1).$rowNumber;
            $style=(int)($cell['style']??0);
            if(!empty($cell['numeric'])){
                $xml.='<c r="'.$ref.'" s="'.$style.'"><v>'.$this->xmlEsc($cell['value']).'</v></c>';
            }else{
                $xml.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xmlEsc($cell['value']).'</t></is></c>';
            }
        }
        return $xml.'</row>';
    }

    private function columnsXml(array $rows,array $keys,array $labels): string
    {
        if(!$keys) return '<cols><col min="1" max="1" width="36" customWidth="1"/></cols>';
        $xml='<cols>';
        foreach($keys as $i=>$key){
            $max=mb_strlen((string)($labels[$i]??$key));
            foreach(array_slice($rows,0,150) as $row){
                $max=max($max,mb_strlen(ExportPresentation::displayValue($key,$row[$key]??null)));
            }
            $width=min(34,max(11,$max+2));
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
            .'<fonts count="4">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF17324D"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.$primary.'"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF2F7FA"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEAF5EF"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD9E6EF"/></left><right style="thin"><color rgb="FFD9E6EF"/></right><top style="thin"><color rgb="FFD9E6EF"/></top><bottom style="thin"><color rgb="FFD9E6EF"/></bottom></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="10">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}

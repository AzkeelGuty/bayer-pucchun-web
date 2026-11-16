<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;

final class SimpleXlsxTable
{
    public function readUpload(array $file,array $expected=[]): array
    {
        if(!isset($file['error']) || (int)$file['error']!==UPLOAD_ERR_OK)
            throw new HttpException(422,'Seleccione un archivo XLSX válido.');
        if((int)($file['size']??0)<1 || (int)$file['size']>8*1024*1024)
            throw new HttpException(422,'El Excel debe pesar entre 1 byte y 8 MB.');
        $name=(string)($file['name']??'');
        if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='xlsx')
            throw new HttpException(422,'Formato no permitido. Use un archivo .xlsx.');
        $tmp=(string)($file['tmp_name']??'');
        if($tmp==='' || !is_uploaded_file($tmp))
            throw new HttpException(422,'El archivo cargado no es válido.');
        return $this->readFile($tmp,$expected);
    }

    /**
     * Cabeceras en las primeras 30 filas y metadatos de almacén por encima
     * de la tabla. Las hojas de instrucciones nunca se importan.
     */
    public function readFile(string $path,array $expected=[]): array
    {
        if(!class_exists('ZipArchive'))
            throw new HttpException(500,'ZipArchive es necesario para leer Excel.');
        $zip=new \ZipArchive();
        if($zip->open($path)!==true)
            throw new HttpException(422,'No se pudo abrir el archivo XLSX.');
        try{
            $shared=[];
            $sharedXml=$zip->getFromName('xl/sharedStrings.xml');
            if(is_string($sharedXml)){
                $dom=$this->xml($sharedXml);$xp=new \DOMXPath($dom);
                $xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach($xp->query('//m:si') as $si){
                    $v='';
                    foreach($xp->query('.//m:t',$si) as $t)$v.=$t->textContent;
                    $shared[]=$v;
                }
            }
            $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
            if(!is_string($sheet))throw new HttpException(422,'El XLSX no contiene hoja de carga.');
            if(strlen($sheet)>45*1024*1024)throw new HttpException(422,'Excel demasiado extenso.');
            $dom=$this->xml($sheet);$xp=new \DOMXPath($dom);
            $xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $matrix=[];
            foreach($xp->query('//m:sheetData/m:row') as $node){
                $index=(int)$node->getAttribute('r');
                $cells=[];
                foreach($xp->query('./m:c',$node) as $cell){
                    $ref=$cell->getAttribute('r');
                    if(!preg_match('/^([A-Z]+)\d+$/D',$ref,$match))continue;
                    $col=$this->columnIndex($match[1]);
                    if($col>150)continue;
                    $type=$cell->getAttribute('t');
                    $v='';
                    if($type==='inlineStr'){
                        foreach($xp->query('.//m:is//m:t',$cell) as $t)$v.=$t->textContent;
                    }else{
                        $raw=(string)($xp->query('./m:v',$cell)->item(0)?->textContent??'');
                        $v=$type==='s'?($shared[(int)$raw]??''):$raw;
                    }
                    $cells[$col]=trim((string)$v);
                }
                if($cells)$matrix[$index]=$cells;
            }
        }finally{$zip->close();}
        if(!$matrix)throw new HttpException(422,'La hoja de carga está vacía.');
        $headers=[];$headerIndex=null;
        $mandatory=[];
        foreach($expected as $key=>$settings)
            if(!empty($settings['required']))$mandatory[]=$key;
        $aliases=WarehouseExcelHeader::fields();
        foreach($matrix as $index=>$cells){
            if((int)$index>30)break;
            $candidate=[];
            foreach($cells as $col=>$raw){
                $key=$this->normalizeHeader((string)$raw);
                foreach($aliases as $field=>$names){
                    if(in_array($key,$names,true)){$key=$field;break;}
                }
                if($key!=='')$candidate[$col]=$key;
            }
            if(!$candidate)continue;
            $values=array_values($candidate);
            if($expected){
                if(array_diff($mandatory,$values))continue;
                if(count(array_intersect(array_keys($expected),$values))<count($mandatory))continue;
            }
            // Con metadatos, omitir filas informativas que accidentalmente se parecen.
            if(count($candidate)<2)continue;
            if(count(array_unique($values))!==count($values))
                throw new HttpException(422,'El Excel contiene encabezados duplicados.');
            $headers=$candidate;$headerIndex=(int)$index;break;
        }
        if($headerIndex===null)
            throw new HttpException(422,'No se reconocieron los encabezados obligatorios de la hoja de carga.');

        $metadata=WarehouseExcelHeader::metadata($matrix,$headerIndex);
        $hasWarehouseColumn=in_array('almacen_nombre',$headers,true)
            ||in_array('almacen_codigo',$headers,true);
        $rows=[];
        foreach($matrix as $index=>$cells){
            if((int)$index<=$headerIndex)continue;
            $row=[];
            foreach($headers as $col=>$header)$row[$header]=(string)($cells[$col]??'');
            if($expected && isset($expected['almacen_nombre'])){
                foreach(['almacen_nombre','almacen_codigo','sucursal_codigo'] as $field){
                    if(trim((string)($row[$field]??''))===''){
                        $row[$field]=(string)($metadata[$field]??'');
                    }
                }
            }
            if(!array_filter($row,static fn($v)=>trim((string)$v)!==''))
                continue;
            // Una nota o un total de pie sin clave obligatoria no es un producto.
            if($expected){
                $allEmpty=true;
                foreach($mandatory as $key)
                    if(trim((string)($row[$key]??''))!==''){$allEmpty=false;break;}
                if($allEmpty)continue;
            }
            $rows[]=$row;
            if(count($rows)>15000)throw new HttpException(422,'Excel supera el máximo de 15000 registros.');
        }
        if(!$rows)throw new HttpException(422,'La hoja no contiene registros válidos.');
        return $rows;
    }

    public function outputTemplate(string $title,array $columns,string $filename): never
    {
        if(!class_exists('ZipArchive')){http_response_code(500);exit('ZipArchive es necesario para generar XLSX.');}
        $tmp=tempnam(sys_get_temp_dir(),'master-xlsx-');
        $zip=new \ZipArchive();
        $zip->open($tmp,\ZipArchive::OVERWRITE);

        $keys=array_keys($columns);
        $isBayer=str_contains(mb_strtolower($title,'UTF-8'),'bayer');
        $productTemplate=$isBayer || isset($columns['almacen_nombre']);
        // "Carga" es la primera hoja; sólo contiene la cabecera, nunca datos
        // ficticios que podrían entrar accidentalmente en producción.
        $sheet1=$this->sheetXml([$keys],true,false);
        $instructions=[
            [$title.' | Plantilla de importación XLSX'],
            [$isBayer?'CATÁLOGO E INVENTARIOS BAYER':'CATÁLOGO INTERNO PUCCHÚN'],
            ['1. Complete únicamente la hoja Carga. Cada producto ocupa una fila.'],
            ['2. Los encabezados no deben modificarse; los campos opcionales pueden quedar vacíos.'],
            ['3. Para almacenes, usar el nombre exacto o código del registro. No se aceptan coincidencias aproximadas.'],
            ['4. Si el almacén es nuevo y hay varias sucursales, indique sucursal_codigo.'],
            [$isBayer
                ?'5. Stock vacío con período indica sin reporte; cero es 0; guion (-) desactiva. Los precios son por presentación.'
                :'5. Esta plantilla registra productos, NO crea saldos físicos. Para inventario real, use Nuevo inventario Pucchún.'],
            [$isBayer
                ?'6. Precio e IGV son opcionales: indique tasa+importe o ambos importes. Fecha YYYY-MM-DD.'
                :'6. unidad_codigo debe existir en Unidades. El producto se identifica por su código interno Pucchún.'],
            [''],
            ['COLUMNA','OBLIGATORIA','DESCRIPCIÓN','EJEMPLO'],
        ];
        foreach($columns as $key=>$meta)
            $instructions[]=[$key,!empty($meta['required'])?'Sí':'No',
                (string)($meta['description']??''),(string)($meta['example']??'')];
        $sheet2=$this->sheetXml($instructions,false,true);

        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Carga" sheetId="1" r:id="rId1"/><sheet name="Instrucciones" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml',$sheet1);
        $zip->addFromString('xl/worksheets/sheet2.xml',$sheet2);
        $zip->addFromString('xl/styles.xml',$this->templateStyles());
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function sheetXml(array $rows,bool $freeze,bool $instructions=false): string
    {
        $maxColumns=0;
        foreach($rows as $row)$maxColumns=max($maxColumns,count($row));
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if($freeze){
            $xml.='<sheetViews><sheetView workbookViewId="0">'
                .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                .'</sheetView></sheetViews>';
        }
        $xml.='<sheetFormatPr defaultRowHeight="18"/><cols>';
        for($i=1;$i<=$maxColumns;$i++){
            $heading=(string)($rows[0][$i-1]??'');
            $width=$instructions?($i===3?68:($i===4?34:($i===1?36:18)))
                :((str_contains($heading,'nombre')||str_contains($heading,'descripcion')
                    ||str_contains($heading,'almacen'))?34:23);
            $xml.='<col min="'.$i.'" max="'.$i.'" width="'.$width.'" customWidth="1"/>';
        }
        $xml.='</cols><sheetData>';
        foreach($rows as $r=>$values){
            $n=$r+1;
            $style=(!$instructions&&$n===1)||($instructions&&$n===10)?1
                :($instructions&&$n<=2?2:0);
            $xml.='<row r="'.$n.'"'.($style?' ht="25" customHeight="1"':'').'>';
            foreach(array_values($values) as $col=>$value){
                $ref=$this->columnName($col+1).$n;
                $xml.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr">'
                    .'<is><t xml:space="preserve">'
                    .htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8')
                    .'</t></is></c>';
            }
            $xml.='</row>';
        }
        $xml.='</sheetData>';
        if($freeze&&$maxColumns>0)
            $xml.='<autoFilter ref="A1:'.$this->columnName($maxColumns).'1"/>';
        return $xml.'</worksheet>';
    }

    private function templateStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
          .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
          .'<fonts count="3">'
          .'<font><sz val="11"/><name val="Arial"/></font>'
          .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
          .'<font><b/><sz val="13"/><color rgb="FF0C3962"/><name val="Arial"/></font>'
          .'</fonts>'
          .'<fills count="3"><fill><patternFill patternType="none"/></fill>'
          .'<fill><patternFill patternType="gray125"/></fill>'
          .'<fill><patternFill patternType="solid"><fgColor rgb="FF0B548E"/><bgColor indexed="64"/></patternFill></fill></fills>'
          .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
          .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
          .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
          .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
          .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
          .'</styleSheet>';
    }

    private function xml(string $xml): \DOMDocument
    {
        $dom=new \DOMDocument();
        $previous=libxml_use_internal_errors(true);
        $ok=$dom->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if(!$ok) throw new HttpException(422,'El XLSX contiene XML inválido.');
        return $dom;
    }

    private function normalizeHeader(string $value): string
    {
        $value=mb_strtolower(trim($value),'UTF-8');
        $value=strtr($value,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $value=preg_replace('/[^a-z0-9]+/','_',$value)??'';
        return trim($value,'_');
    }

    private function columnIndex(string $letters): int
    {
        $n=0;
        foreach(str_split($letters) as $letter) $n=$n*26+(ord($letter)-64);
        return $n;
    }

    private function columnName(int $n): string
    {
        $s='';
        while($n>0){$n--; $s=chr(65+$n%26).$s; $n=intdiv($n,26);}
        return $s;
    }
}

<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Lee directamente los reportes XLSX Bayer: busca el encabezado real en cada hoja.
 * No depende de que comience en la fila 1 ni exige plantilla de homologaciones.
 */
final class BayerWorkbookReader
{
    private const MAX_ROWS=10000;

    public function readUpload(array $file,?string $fallbackPeriod=null): array
    {
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
            (int)($file['size']??0)<1 || (int)($file['size']??0)>12*1024*1024 ||
            strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='xlsx' ||
            !is_uploaded_file((string)($file['tmp_name']??''))){
            throw new HttpException(422,'Seleccione el reporte Bayer XLSX original (máximo 12 MB).');
        }
        return $this->readFile((string)$file['tmp_name'],$fallbackPeriod);
    }

    /**
     * Conserva la API histórica para pruebas y otros consumidores existentes.
     * La vista previa, en cambio, recorre las hojas de forma independiente.
     */
    public function readFile(string $path,?string $fallbackPeriod=null): array
    {
        $all=[];
        $this->eachXlsxSheet($path,$fallbackPeriod,static function(string $name,array $rows)use(&$all):void{
            foreach($rows as $row){
                $all[]=$row;
                if(count($all)>self::MAX_ROWS)
                    throw new HttpException(422,'El reporte supera los 10000 registros permitidos.');
            }
        });
        return $all;
    }

    /**
     * Muestra todas las hojas reconocidas sin importarlas, incluso cuando el
     * libro completo contiene más de diez mil registros en varios meses.
     */
    public function inspectFile(string $path,string $extension,?string $fallbackPeriod=null): array
    {
        $sheets=[];
        $visit=function(string $name,array $rows)use(&$sheets):void{
            if($rows!==[])$sheets[]=$this->summarizeSheet($name,$rows);
        };
        if($extension==='xlsx'){
            $this->eachXlsxSheet($path,$fallbackPeriod,$visit);
        }elseif($extension==='csv'){
            $visit('Archivo CSV',$this->readCsvFile($path,$fallbackPeriod));
        }else{
            throw new HttpException(422,'Solo se admiten archivos XLSX o CSV.');
        }
        if(!$sheets)throw new HttpException(422,'No se encontraron productos Bayer para previsualizar.');
        return $sheets;
    }

    /** Solo devuelve los registros de la hoja que escogió el administrador. */
    public function readSelectedFile(
        string $path,string $extension,string $sheetName,?string $fallbackPeriod=null
    ): array {
        $selected=null;
        if($extension==='xlsx'){
            $this->eachXlsxSheet($path,$fallbackPeriod,
                static function(string $name,array $rows)use(&$selected):void{$selected=$rows;},
                $sheetName);
        }elseif($extension==='csv' && $sheetName==='Archivo CSV'){
            $selected=$this->readCsvFile($path,$fallbackPeriod);
        }
        if(!is_array($selected)||!$selected)
            throw new HttpException(422,'La hoja elegida no contiene productos Bayer reconocidos.');
        if(count($selected)>self::MAX_ROWS)
            throw new HttpException(422,'La hoja elegida supera los 10000 registros permitidos.');
        return $selected;
    }

    private function summarizeSheet(string $name,array $rows): array
    {
        $sample=[];$periods=[];$errors=0;$statuses=[
            'CON_STOCK'=>0,'SIN_STOCK'=>0,'SIN_DATO'=>0,'DESACTIVADO'=>0
        ];
        $seen=[];
        foreach($rows as $row){
            $period=$row['periodo']??'';
            if($period!=='')$periods[$period]=true;
            $status='SIN_DATO';
            if(($row['tiene_columna_stock']??'0')==='1'){
                if(!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$period))$errors++;
                try{$status=BayerCatalogService::marker((string)$row['cantidad'])['estado'];}
                catch(HttpException $error){$errors++;$status='INVALIDO';}
                $unique=$row['codigo_bayer'].'|'.$period;
            }else{
                $unique=$row['codigo_bayer'].'|CATALOGO';
            }
            if(isset($seen[$unique]))$errors++;
            $seen[$unique]=true;
            if(isset($statuses[$status]))$statuses[$status]++;
            if(count($sample)<20)$sample[]=$row+['estado_previo'=>$status];
        }
        return [
            'name'=>$name,'count'=>count($rows),'periods'=>array_keys($periods),
            'errors'=>$errors,'statuses'=>$statuses,'sample'=>$sample
        ];
    }

    /**
     * Encuentra cada pestaña a través de sus relaciones OOXML, nunca por
     * posición. La vista previa no acumula en RAM todas las pestañas.
     */
    private function eachXlsxSheet(
        string $path,?string $fallbackPeriod,callable $visitor,?string $onlySheet=null
    ): void {
        if(!class_exists(ZipArchive::class))throw new HttpException(503,'Habilite la extensión PHP zip.');
        $zip=new ZipArchive();
        if($zip->open($path)!==true)throw new HttpException(422,'El Excel XLSX no se puede abrir.');
        try {
            $shared=[];
            $strings=$zip->getFromName('xl/sharedStrings.xml');
            if(is_string($strings)){
                if(strlen($strings)>45*1024*1024)
                    throw new HttpException(422,'La tabla de textos del Excel supera el máximo.');
                $xp=$this->xpath($strings);
                foreach($xp->query('//m:si') as $si){
                    $value='';
                    foreach($xp->query('.//m:t',$si) as $t)$value.=$t->textContent;
                    $shared[]=$value;
                }
            }
            $sheets=[];
            $workbook=$zip->getFromName('xl/workbook.xml');
            $rels=$zip->getFromName('xl/_rels/workbook.xml.rels');
            if(is_string($workbook) && is_string($rels)){
                $relPaths=[];
                $relations=$this->xpath($rels);
                $relations->registerNamespace('pr','http://schemas.openxmlformats.org/package/2006/relationships');
                foreach($relations->query('//pr:Relationship') as $rel){
                    if($rel->getAttribute('TargetMode')==='External' ||
                        !str_ends_with($rel->getAttribute('Type'),'/worksheet'))continue;
                    $target=str_replace('\\','/',$rel->getAttribute('Target'));
                    $sheetPath=str_starts_with($target,'/')?ltrim($target,'/'):('xl/'.$target);
                    if(!preg_match('#^xl/worksheets/[^/]+\.xml$#iD',$sheetPath))continue;
                    $relPaths[$rel->getAttribute('Id')]=$sheetPath;
                }
                $workbookXp=$this->xpath($workbook);
                foreach($workbookXp->query('//m:sheet') as $sheet){
                    $relId=$sheet->getAttributeNS(
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');
                    if($relId!==''&&isset($relPaths[$relId]))
                        $sheets[]=['name'=>(string)$sheet->getAttribute('name'),
                                   'path'=>$relPaths[$relId]];
                }
                if(!$sheets)throw new HttpException(422,
                    'El Excel no contiene hojas de inventario con relaciones internas válidas.');
            }else{
                // Compatibilidad de libros sin relaciones generados por herramientas antiguas.
                $names=[];
                if(is_string($workbook)){
                    $xp=$this->xpath($workbook);
                    foreach($xp->query('//m:sheet') as $item)
                        $names[]=(string)$item->getAttribute('name');
                }
                for($i=1;$i<=100;$i++){
                    if($zip->locateName('xl/worksheets/sheet'.$i.'.xml')!==false)
                        $sheets[]=['name'=>$names[$i-1]??'Hoja '.$i,
                                   'path'=>'xl/worksheets/sheet'.$i.'.xml'];
                }
            }
            if(count($sheets)>100)throw new HttpException(422,'El reporte contiene demasiadas hojas.');
            $recognized=0;
            foreach($sheets as $sheet){
                if($onlySheet!==null && $sheet['name']!==$onlySheet)continue;
                $raw=$zip->getFromName($sheet['path']);
                if(!is_string($raw))throw new HttpException(422,
                    'Falta una hoja referenciada por el libro Excel.');
                if(strlen($raw)>45*1024*1024)
                    throw new HttpException(422,'Hoja Excel demasiado grande.');
                $matrix=$this->matrix($raw,$shared);
                $part=$this->parseMatrix($matrix,$sheet['name'],$fallbackPeriod);
                unset($matrix,$raw);
                if($part===null)continue;
                $recognized++;
                if(count($part)>self::MAX_ROWS)
                    throw new HttpException(422,'La hoja '.$sheet['name'].' supera los 10000 registros.');
                $visitor($sheet['name'],$part);
            }
            if($recognized===0)throw new HttpException(422,
                'No se identificaron columnas CODIGO y PRODUCTO/PRESENTACION en la hoja solicitada.');
        } finally {$zip->close();}
    }

    /** CSV exportado por Excel: coma, punto y coma o tabulador; BOM y CP1252. */
    public function readCsvFile(string $path,?string $fallbackPeriod=null): array
    {
        if(!is_file($path)||filesize($path)>12*1024*1024)
            throw new HttpException(422,'El CSV no existe o supera 12 MB.');
        $delimiter=null;
        foreach([',',';',"\t"] as $candidate){
            $handle=fopen($path,'rb');
            if(!$handle)throw new HttpException(422,'No se pudo abrir el archivo CSV.');
            try{
                $matrix=[];
                for($line=1;$line<=100 && ($cells=fgetcsv($handle,0,$candidate,'"','\\'))!==false;$line++){
                    $matrix[$line]=$this->csvCells($cells);
                }
                if($this->parseMatrix($matrix,'Archivo CSV',$fallbackPeriod)!==null){
                    $delimiter=$candidate;break;
                }
            }finally{fclose($handle);}
        }
        if($delimiter===null)
            throw new HttpException(422,'El CSV no contiene encabezados CODIGO y PRODUCTO/PRESENTACION.');
        $handle=fopen($path,'rb');
        if(!$handle)throw new HttpException(422,'No se pudo abrir el CSV.');
        try{
            $matrix=[];
            for($line=1;($cells=fgetcsv($handle,0,$delimiter,'"','\\'))!==false;$line++){
                if($line>15000)throw new HttpException(422,'El CSV supera el tamaño de filas permitido.');
                $matrix[$line]=$this->csvCells($cells);
            }
        }finally{fclose($handle);}
        $rows=$this->parseMatrix($matrix,'Archivo CSV',$fallbackPeriod);
        if($rows===null||$rows===[])
            throw new HttpException(422,'El CSV no contiene productos Bayer reconocidos.');
        if(count($rows)>self::MAX_ROWS)
            throw new HttpException(422,'El CSV supera los 10000 productos por carga.');
        return $rows;
    }

    private function csvCells(array $cells): array
    {
        $result=[];
        foreach(array_slice($cells,0,180) as $index=>$cell){
            $value=trim((string)$cell);
            if($index===0)$value=preg_replace('/^\xEF\xBB\xBF/','',$value)??$value;
            if(!mb_check_encoding($value,'UTF-8'))
                $value=mb_convert_encoding($value,'UTF-8','Windows-1252');
            $result[$index+1]=$value;
        }
        return $result;
    }

    private function xpath(string $raw): DOMXPath
    {
        $dom=new DOMDocument();
        $previous=libxml_use_internal_errors(true);
        $valid=$dom->loadXML($raw,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        if(!$valid)throw new HttpException(422,'El XLSX contiene una hoja XML inválida.');
        $xp=new DOMXPath($dom);
        $xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        return $xp;
    }

    private function matrix(string $xml,array $shared): array
    {
        $xp=$this->xpath($xml);$matrix=[];
        foreach($xp->query('//m:sheetData/m:row') as $row){
            $cells=[];
            foreach($xp->query('./m:c',$row) as $cell){
                $ref=$cell->getAttribute('r');
                if(!preg_match('/^([A-Z]+)[0-9]+$/D',$ref,$m))continue;
                $column=0;
                foreach(str_split($m[1]) as $char)$column=$column*26+ord($char)-64;
                if($column<1 || $column>180)continue;
                $type=$cell->getAttribute('t');$value='';
                if($type==='inlineStr'){
                    foreach($xp->query('./m:is//m:t',$cell) as $part)$value.=$part->textContent;
                }else{
                    $value=(string)($xp->query('./m:v',$cell)->item(0)?->textContent??'');
                    if($type==='s')$value=(string)($shared[(int)$value]??'');
                }
                $cells[$column]=trim($value);
            }
            if($cells)$matrix[(int)$row->getAttribute('r')]=$cells;
        }
        return $matrix;
    }

    private static function norm(string $text): string
    {
        $text=mb_strtolower(trim($text),'UTF-8');
        $text=strtr($text,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return trim(preg_replace('/[^a-z0-9]+/','_',$text)??'','_');
    }

    private static function period(?string $raw): ?string
    {
        $s=trim((string)$raw);
        return preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$s)?$s:null;
    }

    /** @return list<array<string,string>>|null  null significa hoja ajena al inventario. */
    public function parseMatrix(array $matrix,string $sheetName,?string $fallbackPeriod=null): ?array
    {
        $columnMap=null;$headerIndex=null;$hasStock=false;
        $codeNames=['codigo','codigo_bayer','codigo_producto','codigo_material','material_id','codigo_interno'];
        $nameNames=['producto_presentacion','producto','presentacion','descripcion','nombre_bayer','producto_descripcion','nombre_producto'];
        foreach($matrix as $index=>$cells){
            $keys=[];
            foreach($cells as $column=>$raw){
                $key=self::norm((string)$raw);
                if($key!=='')$keys[$column]=$key;
            }
            $codeCol=null;$nameCol=null;$stockCol=null;$brandCol=null;$yearCol=null;$monthCol=null;$periodCol=null;
            foreach($keys as $col=>$key){
                if(in_array($key,$codeNames,true))$codeCol=$col;
                if(in_array($key,$nameNames,true))$nameCol=$col;
                if(in_array($key,['marca','brand'],true))$brandCol=$col;
                if(in_array($key,['cantidad_unidades','cantidad_unidad','cantidad','stock','stock_bayer','unidades_disponibles','qty','quantity'],true) || str_starts_with($key,'cantidad_unidades'))$stockCol=$col;
                if(in_array($key,['year','ano','anio'],true))$yearCol=$col;
                if(in_array($key,['month','mes'],true))$monthCol=$col;
                if(in_array($key,['periodo','periodo_stock'],true))$periodCol=$col;
            }
            if($codeCol!==null&&$nameCol!==null){
                $columnMap=compact('codeCol','nameCol','stockCol','brandCol','yearCol','monthCol','periodCol');
                $headerIndex=(int)$index;$hasStock=$stockCol!==null;break;
            }
        }
        if($columnMap===null)return null;
        $rows=[];$errors=0;
        $sheetPeriod=null;
        $months=['ENE'=>1,'JAN'=>1,'FEB'=>2,'MAR'=>3,'ABR'=>4,'APR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AGO'=>8,'AUG'=>8,'SET'=>9,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DIC'=>12,'DEC'=>12];
        if(preg_match('/(ENE|JAN|FEB|MAR|ABR|APR|MAY|JUN|JUL|AGO|AUG|SET|SEP|OCT|NOV|DIC|DEC)[ _-]?(20[0-9]{2}|[0-9]{2})/i',$sheetName,$m)){
            $year=(int)$m[2];if($year<100)$year+=2000;
            $sheetPeriod=sprintf('%04d-%02d',$year,$months[strtoupper($m[1])]);
        }
        foreach($matrix as $index=>$cells){
            if((int)$index<=$headerIndex)continue;
            $value=static fn(?int $col):string=>$col===null?'':trim((string)($cells[$col]??''));
            $code=$value($columnMap['codeCol']);
            $name=$value($columnMap['nameCol']);
            if($code===''&&$name==='')continue;
            if($code===''||$name===''){
                // Totales y notas en reportes de proveedores no son productos.
                continue;
            }
            if(!preg_match('/^[A-Za-z0-9._-]{1,40}$/D',$code)){
                $errors++;continue;
            }
            $period=self::period($value($columnMap['periodCol']))??$sheetPeriod;
            $year=$value($columnMap['yearCol']);$month=$value($columnMap['monthCol']);
            if(ctype_digit($year)&&ctype_digit($month)&&(int)$month>=1&&(int)$month<=12)
                $period=sprintf('%04d-%02d',(int)$year,(int)$month);
            $period??=self::period($fallbackPeriod);
            $rows[]=[
                'codigo_bayer'=>$code,'nombre_bayer'=>$name,
                'marca'=>$value($columnMap['brandCol']),
                'cantidad'=>$hasStock?$value($columnMap['stockCol']):'',
                'tiene_columna_stock'=>$hasStock?'1':'0',
                'periodo'=>$period??'',
                'hoja'=>$sheetName,
                'fila'=>(string)$index,
            ];
        }
        if($errors>0 && !$rows)
            throw new HttpException(422,'La hoja '.$sheetName.' contiene códigos Bayer inválidos.');
        return $rows;
    }
}

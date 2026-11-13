<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use DOMDocument;
use DOMXPath;
use ZipArchive;

/** Importador de listas comerciales; ninguna cifra se confunde con stock. */
final class BayerPriceWorkbookReader
{
    private const MAX_ROWS=10000;
    private const MAX_XML=45*1024*1024;

    public function inspectFile(string $path,string $extension): array
    {
        $result=[];
        foreach($this->sheets($path,$extension) as $sheet){
            $sample=array_slice($sheet['rows'],0,20);
            $issues=[];$seen=[];
            foreach($sheet['rows'] as $r){
                $code=(string)$r['codigo_bayer'];$date=(string)$r['vigencia_desde'];
                if(!preg_match('/^[A-Za-z0-9._-]{1,40}$/D',$code) ||
                    !self::validDate($date) ||
                    ($r['vigencia_hasta']!=='' && (!self::validDate($r['vigencia_hasta']) ||
                        $r['vigencia_hasta']<$date)) ||
                    self::number($r['precio_sugerido_sin_igv'])===null ||
                    self::number($r['precio_sugerido_con_igv'])===null ||
                    self::number($r['igv_porcentaje'])===null ||
                    $r['moneda']!=='PEN'){
                    $issues[]='Fila '.$r['fila'].': campos de precio, fecha, código o moneda inválidos.';
                }
                $key=$code.'|'.$date;
                if(isset($seen[$key]))$issues[]='Fila '.$r['fila'].': código y fecha duplicados.';
                $seen[$key]=true;
            }
            $result[]=['name'=>$sheet['name'],'count'=>count($sheet['rows']),
                'sample'=>$sample,'issues'=>array_slice($issues,0,10),'errors'=>count($issues)];
        }
        return $result;
    }

    public function readSelectedFile(string $path,string $extension,string $selected): array
    {
        foreach($this->sheets($path,$extension) as $sheet)
            if($sheet['name']===$selected)return $sheet['rows'];
        throw new HttpException(422,'La hoja de precios elegida no contiene un formato reconocido.');
    }

    public static function number(string $value): ?string
    {
        $value=trim(str_replace(["S/","PEN"," "], '', $value));
        $value=rtrim($value,'%');
        // Excel exporta con punto decimal; las comas solo son separadores de miles.
        if(!preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$|^\d{1,10}(?:\.\d{1,2})?$/D',$value))
            return null;
        return str_replace(',','',$value);
    }

    public static function validDate(string $date): bool
    {
        if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/D',$date))
            return false;
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        return $d!==false && $d->format('Y-m-d')===$date;
    }

    /** @return array<int,array{name:string,rows:array}> */
    private function sheets(string $path,string $extension): array
    {
        if($extension==='csv'){
            $matrix=$this->csvMatrix($path);
            $rows=$this->parseMatrix($matrix,'Archivo CSV');
            if($rows===null)throw new HttpException(422,'El CSV no contiene columnas de precios Bayer.');
            return [['name'=>'Archivo CSV','rows'=>$rows]];
        }
        if($extension!=='xlsx')throw new HttpException(422,'Archivo no admitido: use XLSX o CSV.');
        if(!class_exists(ZipArchive::class))throw new HttpException(503,'Extensión PHP ZIP no habilitada.');
        $zip=new ZipArchive();
        if($zip->open($path)!==true)throw new HttpException(422,'No se pudo abrir el Excel de precios.');
        try{
            $shared=[];
            $strXml=$zip->getFromName('xl/sharedStrings.xml');
            if(is_string($strXml)){
                if(strlen($strXml)>self::MAX_XML)throw new HttpException(422,'Textos del Excel demasiado grandes.');
                $xp=$this->xpath($strXml);
                foreach($xp->query('//m:si') as $si){
                    $value='';
                    foreach($xp->query('.//m:t',$si) as $t)$value.=$t->textContent;
                    $shared[]=$value;
                }
            }
            $workbook=$zip->getFromName('xl/workbook.xml');
            if(!is_string($workbook))throw new HttpException(422,'Libro XLSX incompleto.');
            $workXp=$this->xpath($workbook);
            $rels=$zip->getFromName('xl/_rels/workbook.xml.rels');
            $parts=[];
            if(is_string($rels)){
                $relXp=$this->xpath($rels);
                $relXp->registerNamespace('pr','http://schemas.openxmlformats.org/package/2006/relationships');
                foreach($relXp->query('//pr:Relationship') as $rel){
                    if($rel->getAttribute('TargetMode')==='External' ||
                        !str_ends_with($rel->getAttribute('Type'),'/worksheet'))continue;
                    $target=str_replace('\\','/',$rel->getAttribute('Target'));
                    $target=str_starts_with($target,'/')?ltrim($target,'/'):('xl/'.$target);
                    if(preg_match('#^xl/worksheets/[^/]+\.xml$#iD',$target))
                        $parts[$rel->getAttribute('Id')]=$target;
                }
            }
            $result=[];$position=0;$sheetCount=0;
            foreach($workXp->query('//m:sheet') as $node){
                $sheetCount++;
                if($sheetCount>100)throw new HttpException(422,'El Excel contiene más de 100 hojas.');
                $position++;
                $rid=$node->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');
                $filename=$parts[$rid]??null;
                if($filename===null && !$parts)
                    $filename='xl/worksheets/sheet'.$position.'.xml'; // libros antiguos
                if($filename===null)continue;
                $xml=$zip->getFromName($filename);
                if(!is_string($xml))throw new HttpException(422,'Falta una hoja declarada en el libro.');
                if(strlen($xml)>self::MAX_XML)throw new HttpException(422,'Hoja demasiado grande.');
                $name=(string)$node->getAttribute('name');
                $rows=$this->parseMatrix($this->matrix($xml,$shared),$name);
                if($rows!==null)$result[]=['name'=>$name,'rows'=>$rows];
            }
            if(!$result)throw new HttpException(422,
                'No se encontró ninguna hoja con código Bayer y columnas de precios.');
            return $result;
        }finally{$zip->close();}
    }

    public function parseMatrix(array $matrix,string $sheet): ?array
    {
        $synonyms=[
            'codigo_bayer'=>['codigo_bayer','codigo','codigo_producto','codigo_material','material_id'],
            'nombre_bayer'=>['nombre_bayer','descripcion','producto_presentacion','producto_descripcion','producto'],
            'moneda'=>['moneda','currency'],
            'precio_sugerido_sin_igv'=>['precio_sugerido_sin_igv','precio_sin_igv','sugerido_sin_igv','precio_neto'],
            'igv_porcentaje'=>['igv_porcentaje','igv','tasa_igv','igv_pct'],
            'precio_sugerido_con_igv'=>['precio_sugerido_con_igv','precio_con_igv','sugerido_con_igv','precio_bruto'],
            'descuento_dealer_porcentaje'=>['descuento_dealer_porcentaje','descuento_dealer','descuento_porcentaje','descuento'],
            'vigencia_desde'=>['vigencia_desde','fecha_lista','fecha_inicio','desde'],
            'vigencia_hasta'=>['vigencia_hasta','fecha_fin','hasta']
        ];
        $header=null;$start=0;
        foreach($matrix as $line=>$cells){
            $keys=[];
            foreach($cells as $col=>$value){
                $key=self::normalize((string)$value);
                foreach($synonyms as $field=>$names)
                    if(in_array($key,$names,true))$keys[$field]=(int)$col;
            }
            if(isset($keys['codigo_bayer'],$keys['precio_sugerido_sin_igv'],
                $keys['igv_porcentaje'],$keys['precio_sugerido_con_igv'],
                $keys['vigencia_desde'])){
                $header=$keys;$start=(int)$line;break;
            }
        }
        if($header===null)return null;
        $rows=[];
        foreach($matrix as $line=>$cells){
            if((int)$line<=$start)continue;
            $get=static fn(string $key):string =>
                isset($header[$key])?trim((string)($cells[$header[$key]]??'')):'';
            $code=$get('codigo_bayer');
            if($code==='')continue;
            $rows[]=[
                'codigo_bayer'=>$code,'nombre_bayer'=>$get('nombre_bayer'),
                'moneda'=>$get('moneda')?:'PEN',
                'precio_sugerido_sin_igv'=>$get('precio_sugerido_sin_igv'),
                'igv_porcentaje'=>$get('igv_porcentaje'),
                'precio_sugerido_con_igv'=>$get('precio_sugerido_con_igv'),
                'descuento_dealer_porcentaje'=>$get('descuento_dealer_porcentaje'),
                'vigencia_desde'=>$get('vigencia_desde'),
                'vigencia_hasta'=>$get('vigencia_hasta'),
                'fila'=>(string)$line,'hoja'=>$sheet
            ];
            if(count($rows)>self::MAX_ROWS)throw new HttpException(422,'La hoja de precios supera las 10000 filas.');
        }
        return $rows===[]?null:$rows;
    }

    private static function normalize(string $value): string
    {
        $value=mb_strtolower(trim($value),'UTF-8');
        $value=strtr($value,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return trim(preg_replace('/[^a-z0-9]+/','_',$value)??'','_');
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom=new DOMDocument();
        $old=libxml_use_internal_errors(true);
        $valid=$dom->loadXML($xml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
        libxml_clear_errors();libxml_use_internal_errors($old);
        if(!$valid)throw new HttpException(422,'El Excel contiene XML inválido.');
        $xp=new DOMXPath($dom);
        $xp->registerNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        return $xp;
    }

    private function matrix(string $xml,array $shared): array
    {
        $xp=$this->xpath($xml);$result=[];
        foreach($xp->query('//m:sheetData/m:row') as $row){
            $values=[];
            foreach($xp->query('./m:c',$row) as $cell){
                $ref=$cell->getAttribute('r');
                if(!preg_match('/^([A-Z]+)\d+$/D',$ref,$matches))continue;
                $col=0;
                foreach(str_split($matches[1]) as $letter)$col=$col*26+(ord($letter)-64);
                if($col>180)continue;
                $type=$cell->getAttribute('t');
                $value='';
                if($type==='inlineStr'){
                    foreach($xp->query('./m:is//m:t',$cell) as $text)$value.=$text->textContent;
                }else{
                    $value=(string)($xp->query('./m:v',$cell)->item(0)?->textContent??'');
                    if($type==='s')$value=(string)($shared[(int)$value]??'');
                }
                $values[$col]=trim($value);
            }
            if($values)$result[(int)$row->getAttribute('r')]=$values;
        }
        return $result;
    }

    private function csvMatrix(string $path): array
    {
        if(!is_file($path)||filesize($path)>12*1024*1024)
            throw new HttpException(422,'CSV de precios inválido o demasiado grande.');
        foreach([',',';',"\t"] as $delimiter){
            $fp=fopen($path,'rb');
            if(!$fp)throw new HttpException(422,'No se pudo abrir el CSV.');
            $matrix=[];
            try{
                $line=0;
                while(($values=fgetcsv($fp,0,$delimiter,'"','\\'))!==false){
                    if(++$line>15000)throw new HttpException(422,'CSV demasiado extenso.');
                    foreach($values as $i=>$value){
                        $value=(string)$value;
                        if($i===0)$value=preg_replace('/^\xEF\xBB\xBF/','',$value)??$value;
                        if(!mb_check_encoding($value,'UTF-8'))
                            $value=mb_convert_encoding($value,'UTF-8','Windows-1252');
                        $matrix[$line][$i+1]=$value;
                    }
                }
            }finally{fclose($fp);}
            if($this->parseMatrix($matrix,'Archivo CSV')!==null)return $matrix;
        }
        throw new HttpException(422,'CSV sin cabeceras de precios Bayer reconocidas.');
    }
}

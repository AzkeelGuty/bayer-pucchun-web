<?php
namespace App\Services;
class SimpleXlsxExporter {
    private function col(int $n): string {$s='';while($n>0){$n--; $s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
    private function xmlEsc($v): string {return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');}
    public function output(array $rows,string $filename): never {
        if(!class_exists('ZipArchive')){http_response_code(500);exit('La extensión ZipArchive es necesaria para XLSX.');}
        $headers=$rows?array_keys($rows[0]):[];$all=$headers?array_merge([$headers],array_map('array_values',$rows)):[['Sin datos']];
        $sheet='';foreach($all as $r=>$row){$sheet.='<row r="'.($r+1).'">';foreach($row as $c=>$v){$cell=$this->col($c+1).($r+1);$sheet.='<c r="'.$cell.'" t="inlineStr"><is><t>'.$this->xmlEsc($v).'</t></is></c>';}$sheet.='</row>';}
        $tmp=tempnam(sys_get_temp_dir(),'xlsx');$zip=new \ZipArchive();$zip->open($tmp,\ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Datos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');$zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));readfile($tmp);unlink($tmp);exit;
    }
}

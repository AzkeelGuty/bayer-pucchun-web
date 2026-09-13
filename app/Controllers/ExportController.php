<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{BayerDataService,SimpleXlsxExporter,SimplePdfExporter};

final class ExportController
{
    public function export(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA','BAYER');

        $type=(string)\input('type','documents');
        $format=strtolower((string)\input('format','csv'));
        if(!in_array($type,['documents','guides','stock'],true)) throw new HttpException(422,'Dataset inválido.');
        if(!in_array($format,['xlsx','csv','json','txt','pdf'],true)) throw new HttpException(422,'Formato inválido.');

        $filters=[];
        foreach(['from','to','branch','q'] as $key){
            $value=trim((string)\input($key,''));
            if($value!=='') $filters[$key]=$value;
        }

        $started=microtime(true);
        $rows=(new BayerDataService())->dataset($type,$filters);
        $base='bayer_'.$type.'_'.date('Ymd_His');
        $filename=$base.'.'.$format;

        $st=\db()->prepare("INSERT INTO exportaciones(nombre_archivo,tipo_dataset,formato,filtro_json,usuario_id,record_count,resultado,duration_ms,generated_at) VALUES(?,?,?,?,?,?,?, ?,CURRENT_TIMESTAMP)");
        $duration=(int)round((microtime(true)-$started)*1000);
        $st->execute([$filename,$type,strtoupper($format),json_encode($filters,JSON_UNESCAPED_UNICODE),(int)\auth_user()['id'],count($rows),'GENERADO',$duration]);
        \audit('exportaciones','generar',(int)\db()->lastInsertId());

        if($format==='json'){
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=$filename");
            echo json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            exit;
        }
        if($format==='txt'){
            header('Content-Type: text/plain; charset=utf-8');
            header("Content-Disposition: attachment; filename=$filename");
            if($rows){
                echo implode("\t",array_keys($rows[0]))."\n";
                foreach($rows as $r) echo implode("\t",array_map(static fn($v)=>str_replace(["\r","\n","\t"],' ',(string)$v),array_values($r)))."\n";
            }
            exit;
        }
        if($format==='xlsx') (new SimpleXlsxExporter())->output($rows,$filename);
        if($format==='pdf') (new SimplePdfExporter())->output($rows,'Bayer - '.ucfirst($type),$filename);
        $this->csv($rows,$filename);
    }

    private function csv(array $rows,string $filename): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        $o=fopen('php://output','w');
        fwrite($o,"\xEF\xBB\xBF");
        if($rows){
            fputcsv($o,array_keys($rows[0]),';');
            foreach($rows as $r) fputcsv($o,array_values($r),';');
        }
        fclose($o);
        exit;
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{BayerDataService,ExportPresentation,SimpleXlsxExporter,SimplePdfExporter};

final class ExportController
{
    public function index(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA','BAYER');
        \view('exportaciones.index');
    }

    public function count(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA','BAYER');
        $type=(string)\input('type','documents');
        if(!in_array($type,['documents','guides','stock'],true)) throw new HttpException(422,'Dataset inválido.');
        $filters=[];
        foreach(['from','to','branch','q'] as $key){
            $value=trim((string)\input($key,''));
            if($value!=='') $filters[$key]=$value;
        }
        $rows=(new BayerDataService())->dataset($type,$filters);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>true,'count'=>count($rows)],JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function export(): void
    {
        \require_role('ADMIN','SUPERVISOR','GERENCIA','BAYER');

        $type=(string)\input('type','documents');
        $format=strtolower((string)\input('format','xlsx'));
        if(!in_array($type,['documents','guides','stock'],true)) throw new HttpException(422,'Dataset inválido.');
        if(!in_array($format,['xlsx','json','txt','pdf'],true)) throw new HttpException(422,'Formato inválido.');

        $filters=[];
        foreach(['from','to','branch','q'] as $key){
            $value=trim((string)\input($key,''));
            if($value!=='') $filters[$key]=$value;
        }

        $started=microtime(true);
        $rows=(new BayerDataService())->dataset($type,$filters);
        $generatedAt=date('Y-m-d H:i:s');
        $meta=ExportPresentation::metadata($type,$filters,$rows,(array)\auth_user(),$generatedAt);

        $slug=match($type){
            'documents'=>'documentos',
            'guides'=>'guias_remision',
            'stock'=>'stock',
            default=>$type,
        };
        $base='pucchun_'.$slug.'_'.date('Ymd_His');
        $filename=$base.'.'.$format;
        $meta['filename']=$filename;
        $meta['format']=strtoupper($format);

        $duration=(int)round((microtime(true)-$started)*1000);
        $st=\db()->prepare("INSERT INTO exportaciones(nombre_archivo,tipo_dataset,formato,filtro_json,usuario_id,record_count,resultado,duration_ms,generated_at) VALUES(?,?,?,?,?,?,?, ?,CURRENT_TIMESTAMP)");
        $st->execute([$filename,$type,strtoupper($format),json_encode($filters,JSON_UNESCAPED_UNICODE),(int)\auth_user()['id'],count($rows),'GENERADO',$duration]);
        \audit('exportaciones','generar',(int)\db()->lastInsertId());

        if($format==='json') $this->json($rows,$meta,$filename);
        if($format==='txt') $this->txt($rows,$meta,$filename);
        if($format==='xlsx') (new SimpleXlsxExporter())->output($rows,$filename,$meta);
        if($format==='pdf') (new SimplePdfExporter())->output($rows,$filename,$meta);
        throw new HttpException(422,'Formato no disponible.');
    }

    private function json(array $rows,array $meta,string $filename): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $payload=[
            'meta'=>[
                'sistema'=>$meta['system'],
                'aliado'=>$meta['partner'],
                'reporte'=>$meta['dataset'],
                'archivo'=>$meta['filename'],
                'formato'=>$meta['format'],
                'generado_por'=>$meta['generated_by'],
                'fecha_generacion'=>$meta['generated_at'],
                'total_registros'=>$meta['record_count'],
                'estado_datos'=>$meta['status'],
                'filtros'=>$meta['filters'],
            ],
            'datos'=>$rows,
        ];
        echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function txt(array $rows,array $meta,string $filename): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        $bar=str_repeat('=',96);
        $line=str_repeat('-',96);
        echo $bar."\n";
        echo "PUCCHÚN DATA HUB\n";
        echo mb_strtoupper('Reporte de '.(string)$meta['dataset'])."\n";
        echo $bar."\n";
        echo "Sistema       : {$meta['system']}\n";
        echo "Aliado        : {$meta['partner']}\n";
        echo "Archivo       : {$meta['filename']}\n";
        echo "Formato       : {$meta['format']}\n";
        echo "Generado por  : {$meta['generated_by']}\n";
        echo "Fecha y hora  : {$meta['generated_at']}\n";
        echo "Registros     : {$meta['record_count']}\n";
        echo "Estado        : {$meta['status']}\n";
        echo "Filtros       : {$meta['filters']}\n";
        echo $line."\n";
        echo "DATOS PUBLICADOS\n";
        echo $line."\n";

        if(!$rows){
            echo "Sin datos para los filtros seleccionados.\n";
            echo $bar."\n";
            exit;
        }

        $keys=ExportPresentation::keys($rows);
        echo implode(' | ',ExportPresentation::labels($rows))."\n";
        echo $line."\n";
        foreach($rows as $row){
            $values=[];
            foreach($keys as $key){
                $values[]=str_replace(["\r","\n","\t"],' ',ExportPresentation::displayValue($key,$row[$key]??null));
            }
            echo implode(' | ',$values)."\n";
        }
        echo $line."\n";
        echo "Fin del reporte · {$meta['record_count']} registro(s) exportado(s).\n";
        echo $bar."\n";
        exit;
    }


}

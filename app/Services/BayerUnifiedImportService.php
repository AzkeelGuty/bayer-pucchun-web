<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;

/** Un único asistente XLSX/CSV para catálogo, inventario y precios Bayer. */
final class BayerUnifiedImportService
{
    private function inventory(string $path,string $extension,?string $period): array
    {
        try{return (new BayerWorkbookReader())->inspectFile($path,$extension,$period);}
        catch(HttpException $error){return [];}
    }

    private function priceSheets(string $path,string $extension): array
    {
        try{return (new BayerPriceWorkbookReader())->inspectFile($path,$extension);}
        catch(HttpException $error){return [];}
    }

    /** Las hojas de inventario y listas comerciales se reconocen por cabeceras. */
    public function inspectFile(
        string $path,string $extension,?string $period=null,?string $priceDate=null
    ): array {
        $inventory=$this->inventory($path,$extension,$period);
        $prices=$this->priceSheets($path,$extension);
        $sheets=[];
        foreach($inventory as $sheet){
            $sheet['tipo']='INVENTARIO';
            $sheet['precios']=0;
            $sheet['priceIssues']=[];
            $sheets[$sheet['name']]=$sheet;
        }
        foreach($prices as $sheet){
            $priceRows=$this->readPriceRows($path,$extension,$sheet['name'],$priceDate);
            $validated=(new BayerPriceImportService())->validateRows($priceRows);
            $name=$sheet['name'];
            if(isset($sheets[$name])){
                $sheets[$name]['tipo']='MIXTO';
                $sheets[$name]['precios']=count($priceRows);
                $sheets[$name]['priceSample']=array_slice($priceRows,0,20);
                $sheets[$name]['errors']+=count($validated['issues']);
                $sheets[$name]['priceIssues']=array_slice($validated['issues'],0,10);
            }else{
                $sheets[$name]=[
                    'name'=>$name,'tipo'=>'PRECIOS','count'=>count($priceRows),
                    'precios'=>count($priceRows),'errors'=>count($validated['issues']),
                    'priceIssues'=>array_slice($validated['issues'],0,10),
                    'statuses'=>[],'periods'=>[],
                    'sample'=>array_slice($priceRows,0,20),
                    'priceSample'=>array_slice($priceRows,0,20),
                ];
            }
        }
        if(!$sheets)throw new HttpException(422,
            'No se reconocieron cabeceras de productos, inventarios o precios Bayer.');
        return array_values($sheets);
    }

    /** Devuelve exclusivamente las filas de la hoja seleccionada, sin las demás. */
    public function readSelectedFile(
        string $path,string $extension,string $name,string $type,
        ?string $period=null,?string $priceDate=null
    ): array {
        $result=['inventario'=>[],'precios'=>[]];
        if(in_array($type,['INVENTARIO','MIXTO'],true))
            $result['inventario']=(new BayerWorkbookReader())->readSelectedFile(
                $path,$extension,$name,$period);
        if(in_array($type,['PRECIOS','MIXTO'],true))
            $result['precios']=$this->readPriceRows($path,$extension,$name,$priceDate);
        if(!$result['inventario'] && !$result['precios'])
            throw new HttpException(422,'La hoja no contiene registros reconocibles.');
        return $result;
    }

    private function readPriceRows(
        string $path,string $extension,string $name,?string $fallbackDate
    ): array {
        $priceRows=(new BayerPriceWorkbookReader())->readSelectedFile($path,$extension,$name);
        $result=[];
        foreach($priceRows as $row){
            $net=trim((string)($row['precio_sugerido_sin_igv']??''));
            $gross=trim((string)($row['precio_sugerido_con_igv']??''));
            // Si una fila solo trae inventario, no crear ni sustituir un precio.
            if($net==='' && $gross==='')continue;
            if(trim((string)($row['vigencia_desde']??''))==='')
                $row['vigencia_desde']=(string)$fallbackDate;
            $result[]=$row;
        }
        return $result;
    }
}

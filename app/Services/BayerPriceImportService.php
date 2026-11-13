<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use Throwable;

/**
 * Actualiza exclusivamente precios del catálogo Bayer por codigo_bayer.
 * Nunca escribe productos ni stock físico Pucchún y conserva históricos de precios.
 */
final class BayerPriceImportService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo=null){$this->pdo=$pdo??\db();}

    private static function decimal(string $raw,string $field): string
    {
        $n=BayerPriceWorkbookReader::number($raw);
        if($n===null||!is_finite((float)$n))
            throw new HttpException(422,$field.' contiene un importe inválido.');
        return $n;
    }

    private static function rate(string $raw,string $field,bool $optional=false): ?string
    {
        if($optional && trim($raw)==='')return null;
        $value=self::decimal($raw,$field);
        if((float)$value>100)throw new HttpException(422,$field.' supera 100 %.');
        return $value;
    }

    /**
     * Comprueba antes de importar, usando la misma llave código/fecha que
     * se utiliza en SQL. No depende de la posición del producto en Excel.
     */
    public function validateRows(array $rows): array
    {
        $issues=[];$seen=[];$normalized=[];
        foreach($rows as $index=>$row){
            $line=(string)($row['fila']??($index+2));
            try{
                $code=trim((string)($row['codigo_bayer']??''));
                $name=trim((string)($row['nombre_bayer']??''));
                $currency=strtoupper(trim((string)($row['moneda']??'PEN')));
                $start=trim((string)($row['vigencia_desde']??''));
                $end=trim((string)($row['vigencia_hasta']??''));
                if(!preg_match('/^[A-Za-z0-9._-]{1,40}$/D',$code))
                    throw new HttpException(422,'Código Bayer inválido.');
                if($name!=='' && mb_strlen($name)>200)
                    throw new HttpException(422,'Nombre Bayer demasiado largo.');
                if($currency!=='PEN')
                    throw new HttpException(422,'Solo se permiten precios en PEN.');
                if(!BayerPriceWorkbookReader::validDate($start) ||
                    ($end!==''&&(!BayerPriceWorkbookReader::validDate($end)||$end<$start)))
                    throw new HttpException(422,'Fecha de vigencia inválida.');
                $net=self::decimal((string)($row['precio_sugerido_sin_igv']??''),'Precio sin IGV');
                $vat=self::rate((string)($row['igv_porcentaje']??''),'IGV');
                $gross=self::decimal((string)($row['precio_sugerido_con_igv']??''),'Precio con IGV');
                $discount=self::rate((string)($row['descuento_dealer_porcentaje']??''),'Descuento',true);
                if((float)$net>9999999999.99 || (float)$gross>9999999999.99)
                    throw new HttpException(422,'Importe mayor al máximo permitido.');
                if(abs(round((float)$net*(1+(float)$vat/100),2)-(float)$gross)>0.02)
                    throw new HttpException(422,'Precio con IGV no coincide con el neto y la tasa informada.');
                $key=$code.'|'.$start;
                if(isset($seen[$key]))
                    throw new HttpException(422,'Código Bayer y fecha duplicados en el mismo archivo.');
                $seen[$key]=true;
                $normalized[]=[
                    'codigo_bayer'=>$code,'nombre_bayer'=>$name,
                    'moneda'=>'PEN','precio_sugerido_sin_igv'=>number_format((float)$net,2,'.',''),
                    'igv_porcentaje'=>number_format((float)$vat,2,'.',''),
                    'precio_sugerido_con_igv'=>number_format((float)$gross,2,'.',''),
                    'descuento_dealer_porcentaje'=>$discount===null?null:number_format((float)$discount,2,'.',''),
                    'vigencia_desde'=>$start,'vigencia_hasta'=>$end===''?null:$end,
                ];
            }catch(HttpException $exception){
                $issues[]='Fila '.$line.': '.$exception->getMessage();
            }
        }
        return ['rows'=>$normalized,'issues'=>$issues];
    }

    public function previewMatch(array $rows): array
    {
        $validation=$this->validateRows($rows);
        $allCodes=array_values(array_unique(array_column($validation['rows'],'codigo_bayer')));
        $existing=[];
        foreach(array_chunk($allCodes,500) as $codes){
            $placeholders=implode(',',array_fill(0,count($codes),'?'));
            $query=$this->pdo->prepare('SELECT codigo_bayer FROM bayer_productos_catalogo
                WHERE codigo_bayer IN ('.$placeholders.')');
            $query->execute($codes);
            foreach($query->fetchAll(PDO::FETCH_COLUMN) as $code)$existing[(string)$code]=true;
        }
        $missing=[];
        foreach($allCodes as $code)if(!isset($existing[$code]))$missing[]=$code;
        return ['total'=>count($rows),'matched'=>count($allCodes)-count($missing),
            'missing'=>$missing,'issues'=>$validation['issues']];
    }

    public function importRows(array $rows,string $filename): array
    {
        if(!$rows || count($rows)>10000)
            throw new HttpException(422,'La carga debe contener de 1 a 10000 precios.');
        $validated=$this->validateRows($rows);
        if($validated['issues'])
            throw new HttpException(422,implode(' ',array_slice($validated['issues'],0,3)));
        $check=$this->previewMatch($rows);
        if($check['missing'])
            throw new HttpException(422,'Códigos Bayer no registrados: '.
                implode(', ',array_slice($check['missing'],0,8)).
                '. Registre primero esos productos en el catálogo Bayer.');
        if($this->pdo->inTransaction())throw new \LogicException('Transacción de importación en curso.');
        $this->pdo->beginTransaction();
        $inserted=0;$updated=0;$unchanged=0;
        try{
            $lookup=$this->pdo->prepare('SELECT id FROM bayer_productos_catalogo
                WHERE codigo_bayer=? FOR UPDATE');
            $find=$this->pdo->prepare('SELECT id,moneda,sugerido_sin_igv,igv_porcentaje,
                sugerido_con_igv,descuento_dealer,vigencia_hasta FROM bayer_precios_referencia
                WHERE producto_id=? AND vigencia_desde=? FOR UPDATE');
            $insert=$this->pdo->prepare('INSERT INTO bayer_precios_referencia
                (producto_id,vigencia_desde,vigencia_hasta,moneda,sugerido_sin_igv,
                 igv_porcentaje,sugerido_con_igv,descuento_dealer,fuente)
                VALUES(?,?,?,?,?,?,?,?,?)');
            $update=$this->pdo->prepare('UPDATE bayer_precios_referencia SET
                vigencia_hasta=?,moneda=?,sugerido_sin_igv=?,igv_porcentaje=?,
                sugerido_con_igv=?,descuento_dealer=?,fuente=? WHERE id=?');
            $unpublish=$this->pdo->prepare('UPDATE bayer_productos_catalogo
                SET publicado=0 WHERE id=?');
            $source=mb_substr($filename,0,180);
            foreach($validated['rows'] as $row){
                $lookup->execute([$row['codigo_bayer']]);
                $id=$lookup->fetchColumn();
                if($id===false)
                    throw new HttpException(422,'Código Bayer desconocido: '.$row['codigo_bayer']);
                $find->execute([(int)$id,$row['vigencia_desde']]);
                $old=$find->fetch(PDO::FETCH_ASSOC);
                $fields=[
                    'moneda'=>'PEN',
                    'sugerido_sin_igv'=>$row['precio_sugerido_sin_igv'],
                    'igv_porcentaje'=>$row['igv_porcentaje'],
                    'sugerido_con_igv'=>$row['precio_sugerido_con_igv'],
                    'descuento_dealer'=>$row['descuento_dealer_porcentaje'],
                    'vigencia_hasta'=>$row['vigencia_hasta']
                ];
                $changed=$old===false;
                if(!$changed){
                    foreach($fields as $field=>$value){
                        $previous=$old[$field]??null;
                        if($previous===null || $value===null){
                            if($previous!==$value){$changed=true;break;}
                        }elseif(in_array($field,['sugerido_sin_igv','igv_porcentaje',
                            'sugerido_con_igv','descuento_dealer'],true)){
                            if(abs((float)$previous-(float)$value)>0.00001){$changed=true;break;}
                        }elseif((string)$previous!==(string)$value){$changed=true;break;}
                    }
                }
                if(!$changed){$unchanged++;continue;}
                if($old===false){
                    $insert->execute([(int)$id,$row['vigencia_desde'],$row['vigencia_hasta'],
                        'PEN',$row['precio_sugerido_sin_igv'],$row['igv_porcentaje'],
                        $row['precio_sugerido_con_igv'],$row['descuento_dealer_porcentaje'],$source]);
                    $inserted++;
                }else{
                    $update->execute([$row['vigencia_hasta'],'PEN',
                        $row['precio_sugerido_sin_igv'],$row['igv_porcentaje'],
                        $row['precio_sugerido_con_igv'],$row['descuento_dealer_porcentaje'],
                        $source,$old['id']]);
                    $updated++;
                }
                // Publicación explícita tras la revisión, como en el inventario Bayer.
                $unpublish->execute([(int)$id]);
            }
            $this->pdo->commit();
        }catch(Throwable $error){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $error;
        }
        return ['insertados'=>$inserted,'actualizados'=>$updated,
            'sin_cambios'=>$unchanged];
    }
}

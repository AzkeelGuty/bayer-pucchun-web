<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use Throwable;

/**
 * Productos reportados por Bayer, independientes de productos y stock Pucchún.
 * Los estados son derivados del dato de inventario; - no equivale a 0 ni a vacío.
 */
final class BayerCatalogService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo=null){$this->pdo=$pdo??\db();}

    public static function marker(string $raw): array
    {
        $value=trim($raw);
        if($value==='-'||$value==='—'||$value==='–')
            return ['marcador'=>'GUION','cantidad'=>null,'estado'=>'DESACTIVADO'];
        if($value==='')return ['marcador'=>'VACIO','cantidad'=>null,'estado'=>'SIN_DATO'];
        if(!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.0{1,3})?$/D',$value))
            throw new HttpException(422,'La cantidad Bayer debe ser un entero, una celda vacía o un guion (-).');
        $number=(int)explode('.',$value,2)[0];
        if($number>999999999)throw new HttpException(422,'La cantidad supera el máximo permitido.');
        return ['marcador'=>'CANTIDAD','cantidad'=>$number,
            'estado'=>$number===0?'SIN_STOCK':'CON_STOCK'];
    }

    private static function validPeriod(string $period): bool
    {
        return (bool)preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$period);
    }

    public function list(array $filters=[],int $limit=15,int $offset=0): array
    {
        $where=[];$p=[];
        $query=trim((string)($filters['q']??''));
        if($query!==''){
            $where[]='(codigo_bayer LIKE ? OR nombre_bayer LIKE ? OR marca LIKE ?)';
            $p=array_fill(0,3,'%'.$query.'%');
        }
        $status=trim((string)($filters['estado']??''));
        if(in_array($status,['SIN_DATO','SIN_STOCK','CON_STOCK','DESACTIVADO'],true)){
            $where[]='stock_estado=?';$p[]=$status;
        }
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM bayer_productos_catalogo'.$whereSql);
        $count->execute($p);
        $limit=max(1,min(100,$limit));$offset=max(0,$offset);
        $stmt=$this->pdo->prepare('SELECT * FROM bayer_productos_catalogo'.$whereSql.
            ' ORDER BY codigo_bayer LIMIT '.$limit.' OFFSET '.$offset);
        $stmt->execute($p);
        return ['rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>(int)$count->fetchColumn()];
    }

    public function find(int $id): array
    {
        $s=$this->pdo->prepare('SELECT * FROM bayer_productos_catalogo WHERE id=?');
        $s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new HttpException(404,'Producto Bayer no encontrado.');
        return $r;
    }

    public function history(int $id): array
    {
        $s=$this->pdo->prepare('SELECT periodo,cantidad,marcador,fuente,registrado_en
            FROM bayer_inventario_periodos WHERE producto_id=? ORDER BY periodo DESC LIMIT 36');
        $s->execute([$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function prices(int $id): array
    {
        $s=$this->pdo->prepare('SELECT * FROM bayer_precios_referencia
            WHERE producto_id=? ORDER BY vigencia_desde DESC LIMIT 20');
        $s->execute([$id]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function upsertProduct(string $code,string $name,string $brand): int
    {
        $statement=$this->pdo->prepare(
            'INSERT INTO bayer_productos_catalogo(codigo_bayer,nombre_bayer,marca)
             VALUES(?,?,NULLIF(?,\'\'))
             ON DUPLICATE KEY UPDATE
                nombre_bayer=VALUES(nombre_bayer),
                marca=CASE WHEN VALUES(marca) IS NULL THEN marca ELSE VALUES(marca) END,
                publicado=CASE WHEN nombre_bayer<>VALUES(nombre_bayer)
                               OR (VALUES(marca) IS NOT NULL AND NOT(marca<=>VALUES(marca)))
                               THEN 0 ELSE publicado END'
        );
        $statement->execute([$code,$name,$brand]);
        $read=$this->pdo->prepare('SELECT id FROM bayer_productos_catalogo WHERE codigo_bayer=?');
        $read->execute([$code]);return (int)$read->fetchColumn();
    }

    public function importRows(array $rows,string $filename='Inventario Bayer'): array
    {
        if(!$rows||count($rows)>10000)throw new HttpException(422,'Se requieren de 1 a 10000 productos Bayer.');
        if($this->pdo->inTransaction())throw new \LogicException('Hay otra importación en curso.');
        $this->pdo->beginTransaction();$new=0;$updated=0;$snapshots=0;$seen=[];
        try{
            $exists=$this->pdo->prepare('SELECT id FROM bayer_productos_catalogo WHERE codigo_bayer=?');
            $snap=$this->pdo->prepare(
                'INSERT INTO bayer_inventario_periodos(producto_id,periodo,cantidad,marcador,fuente)
                 VALUES(?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad),marcador=VALUES(marcador),
                                         fuente=VALUES(fuente),registrado_en=NOW()'
            );
            $refresh=$this->pdo->prepare(
                'UPDATE bayer_productos_catalogo SET stock_reportado=?,stock_periodo=?,stock_estado=?,
                estado_catalogo=?,publicado=0 WHERE id=?'
            );
            foreach($rows as $i=>$row){
                $line=(string)($row['fila']??($i+2));$sheet=(string)($row['hoja']??'Carga');
                $code=trim((string)($row['codigo_bayer']??''));
                $name=trim((string)($row['nombre_bayer']??''));
                $brand=trim((string)($row['marca']??''));
                if(!preg_match('/^[A-Za-z0-9._-]{1,40}$/D',$code))
                    throw new HttpException(422,"Hoja $sheet, fila $line: código Bayer inválido.");
                if($name===''||mb_strlen($name)>200||mb_strlen($brand)>100)
                    throw new HttpException(422,"Hoja $sheet, fila $line: nombre o marca inválidos.");
                $period=trim((string)($row['periodo']??''));
                $stockProvided=($row['tiene_columna_stock']??'0')==='1';
                if($stockProvided && !self::validPeriod($period))
                    throw new HttpException(422,"Hoja $sheet, fila $line: indique mes YYYY-MM para registrar cantidades.");
                $unique=$code.'|'.($stockProvided?$period:'CATALOGO');
                if(isset($seen[$unique]))
                    throw new HttpException(422,"Hoja $sheet, fila $line: código y período duplicados en el Excel.");
                $seen[$unique]=true;

                $exists->execute([$code]);$wasThere=$exists->fetchColumn()!==false;
                $productId=$this->upsertProduct($code,$name,$brand);
                if($wasThere)$updated++;else $new++;

                if(!$stockProvided)continue;
                try{$parsed=self::marker((string)($row['cantidad']??''));}
                catch(HttpException $error){throw new HttpException(422,"Hoja $sheet, fila $line: ".$error->getMessage());}
                $snap->execute([$productId,$period,$parsed['cantidad'],$parsed['marcador'],
                    mb_substr($filename,0,180)]);
                $snapshots++;
                $current=$this->pdo->prepare('SELECT stock_periodo FROM bayer_productos_catalogo WHERE id=? FOR UPDATE');
                $current->execute([$productId]);$last=$current->fetchColumn();
                if($last===null||(string)$last<=$period){
                    $active=$parsed['marcador']==='GUION'?'INACTIVO':'ACTIVO';
                    $refresh->execute([$parsed['cantidad'],$period,$parsed['estado'],$active,$productId]);
                }
            }
            $this->pdo->commit();
        }catch(Throwable $error){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $error;
        }
        return ['productos_nuevos'=>$new,'productos_actualizados'=>$updated,
            'registros'=>count($rows),'periodos'=>$snapshots];
    }

    public function save(array $data,?int $id=null): int
    {
        $code=trim((string)($data['codigo_bayer']??''));
        $name=trim((string)($data['nombre_bayer']??''));
        $brand=trim((string)($data['marca']??''));
        if(!preg_match('/^[A-Za-z0-9._-]{1,40}$/D',$code)||$name===''||
           mb_strlen($name)>200||mb_strlen($brand)>100)
            throw new HttpException(422,'Código Bayer, nombre o marca incorrectos.');
        if($id===null){
            try{return $this->upsertProduct($code,$name,$brand);}
            catch(\PDOException $error){throw new HttpException(409,'El código Bayer ya está registrado.');}
        }
        $this->find($id);
        $s=$this->pdo->prepare(
            'UPDATE bayer_productos_catalogo
             SET codigo_bayer=?,nombre_bayer=?,marca=NULLIF(?,\'\'),publicado=0 WHERE id=?'
        );
        try{$s->execute([$code,$name,$brand,$id]);}
        catch(\PDOException $error){throw new HttpException(409,'El código Bayer ya está registrado.');}
        return $id;
    }

    public function changeStatus(int $id,string $action): void
    {
        $this->find($id);
        if($action==='PUBLICAR'){
            $sql='UPDATE bayer_productos_catalogo SET publicado=1 WHERE id=?';
        }elseif($action==='DESACTIVAR'){
            $sql="UPDATE bayer_productos_catalogo SET estado_catalogo='INACTIVO',stock_estado='DESACTIVADO',publicado=0 WHERE id=?";
        }elseif($action==='ACTIVAR'){
            $sql="UPDATE bayer_productos_catalogo SET estado_catalogo='ACTIVO',
                stock_estado=CASE WHEN stock_reportado IS NULL THEN 'SIN_DATO'
                  WHEN stock_reportado=0 THEN 'SIN_STOCK' ELSE 'CON_STOCK' END,
                publicado=0 WHERE id=?";
        }else throw new HttpException(422,'Acción no permitida.');
        $s=$this->pdo->prepare($sql);$s->execute([$id]);
    }

    /** Eliminación definitiva y explícita con código de confirmación.
     * Las líneas de stock y precios asociadas se eliminan junto al producto.
     */
    public function delete(int $id,string $confirmation): void
    {
        $product=$this->find($id);
        if(!hash_equals((string)$product['codigo_bayer'],trim($confirmation)))
            throw new HttpException(422,'Para eliminar escriba exactamente el código Bayer del producto.');
        $s=$this->pdo->prepare('DELETE FROM bayer_productos_catalogo WHERE id=?');
        $s->execute([$id]);
    }

    public function publishedProducts(array $filters=[]): array
    {
        $sql="SELECT codigo_bayer AS materialId,nombre_bayer AS materialName,marca AS brand,
                stock_reportado AS quantity,stock_periodo AS period,stock_estado AS stockStatus,
                estado_catalogo AS catalogStatus,publicado AS published
              FROM bayer_productos_catalogo WHERE publicado=1";
        $params=[];
        if(trim((string)($filters['q']??''))!==''){
            $sql.=' AND (codigo_bayer LIKE ? OR nombre_bayer LIKE ?)';
            $q='%'.trim((string)$filters['q']).'%';$params=[$q,$q];
        }
        $sql.=' ORDER BY codigo_bayer LIMIT 10000';
        $s=$this->pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function publishedInventory(array $filters=[]): array
    {
        $where=['p.publicado=1'];$params=[];
        if(isset($filters['from'])){$where[]="i.periodo>=?";$params[]=substr((string)$filters['from'],0,7);}
        if(isset($filters['to'])){$where[]="i.periodo<=?";$params[]=substr((string)$filters['to'],0,7);}
        $q=trim((string)($filters['q']??''));
        if($q!==''){$where[]='(p.codigo_bayer LIKE ? OR p.nombre_bayer LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
        $s=$this->pdo->prepare(
            'SELECT p.codigo_bayer AS materialId,p.nombre_bayer AS materialName,
            p.marca AS brand,i.periodo AS period,i.cantidad AS quantity,
            i.marcador AS stockMarker,
            CASE WHEN i.marcador=\'GUION\' THEN \'DESACTIVADO\'
                 WHEN i.marcador=\'VACIO\' THEN \'SIN_DATO\'
                 WHEN i.cantidad=0 THEN \'SIN_STOCK\' ELSE \'CON_STOCK\' END AS stockStatus
            FROM bayer_inventario_periodos i
            JOIN bayer_productos_catalogo p ON p.id=i.producto_id
            WHERE '.implode(' AND ',$where).' ORDER BY i.periodo DESC,p.codigo_bayer LIMIT 10000'
        );
        $s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function publishedPrices(): array
    {
        return $this->pdo->query(
            'SELECT p.codigo_bayer AS materialId,p.nombre_bayer AS materialName,
                x.vigencia_desde AS validFrom,x.vigencia_hasta AS validUntil,
                x.moneda AS currency,x.sugerido_sin_igv AS suggestedNet,
                x.igv_porcentaje AS vatRate,x.sugerido_con_igv AS suggestedGross,
                x.descuento_dealer AS dealerDiscount
             FROM bayer_precios_referencia x JOIN bayer_productos_catalogo p ON p.id=x.producto_id
             WHERE p.publicado=1 ORDER BY x.vigencia_desde DESC,p.codigo_bayer LIMIT 10000'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

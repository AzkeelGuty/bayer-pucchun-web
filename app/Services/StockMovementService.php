<?php
declare(strict_types=1);
namespace App\Services;

use App\Exceptions\HttpException;
use PDO;
use Throwable;

/**
 * Kardex de ventas y guías de salida. Los inventarios publicados son
 * snapshots inmutables; las salidas se descuentan SOLO del snapshot vigente.
 * Al registrar un nuevo snapshot, sus cantidades pasan a ser la nueva base.
 */
final class StockMovementService
{
    public function __construct(private ?PDO $pdo=null){$this->pdo??=\db();}

    public function installed(): bool
    {
        $stmt=$this->pdo->query("SHOW TABLES LIKE 'stock_movimientos'");
        return $stmt->fetchColumn()!==false;
    }

    /** Se llama dentro de la misma transacción de publicación. */
    public function issue(string $module,int $originId,int $userId): void
    {
        if(!in_array($module,['documentos','guias'],true))
            throw new \InvalidArgumentException('Módulo de movimiento inválido.');
        if(!$this->pdo->inTransaction())
            throw new \LogicException('La salida requiere transacción.');
        if(!$this->installed())return; // compatibilidad con esquema anterior a 017
        $table=$module==='documentos'?'documentos':'guias';
        $headerTable=$table.'_cabecera';
        $detailsTable=$table.'_detalle';
        $foreign=$module==='documentos'?'documento_id':'guia_id';
        $st=$this->pdo->prepare('SELECT * FROM '.$headerTable.' WHERE id=? FOR UPDATE');
        $st->execute([$originId]);
        $header=$st->fetch(PDO::FETCH_ASSOC);
        if(!$header)throw new HttpException(404,'Registro comercial no encontrado.');
        if($module==='documentos' && (string)$header['estado_comprobante']!=='VIGENTE')return;
        $linked=$this->linkedDocument($module,$header,$originId);
        if($linked!==null && $this->hasIssue($linked[0],$linked[1]))return;

        $st=$this->pdo->prepare('SELECT id,producto_id,unidad_id,cantidad FROM '.$detailsTable.' WHERE '.$foreign.'=? ORDER BY producto_id,unidad_id,id');
        $st->execute([$originId]);
        $lines=$st->fetchAll(PDO::FETCH_ASSOC);
        if(!$lines)throw new HttpException(422,'El documento o guía no contiene productos para descontar.');

        // Bloqueo determinista de productos: evita saldo negativo al publicar concurrentemente.
        $productIds=array_values(array_unique(array_map(static fn($r)=>(int)$r['producto_id'],$lines)));
        sort($productIds,SORT_NUMERIC);
        $lock=$this->pdo->prepare('SELECT id FROM productos WHERE id=? FOR UPDATE');
        foreach($productIds as $pid){$lock->execute([$pid]);if(!$lock->fetchColumn())throw new HttpException(422,'Producto no disponible.');}

        foreach($lines as $line){
            $product=(int)$line['producto_id'];
            $unit=(int)$line['unidad_id'];
            $quantity=(int)round((float)$line['cantidad']);
            if($quantity<=0 || abs((float)$line['cantidad']-$quantity)>0.0001)
                throw new HttpException(422,'La cantidad de salida debe ser un entero positivo.');
            $warehouse=$this->warehouseFor($product,(int)$header['sucursal_id']);
            [$snapshot,$balance]=$this->currentBalance($warehouse,$product,$unit);
            if($snapshot<1)throw new HttpException(409,'No existe inventario físico publicado para el producto y su almacén. Registre el stock inicial.');
            if($balance<$quantity)
                throw new HttpException(409,
                    'Stock insuficiente para el producto ID '.$product.'. Disponible: '.$balance.
                    ', solicitado: '.$quantity.'. Actualice el inventario o reduzca la cantidad.');
            $insert=$this->pdo->prepare(
                "INSERT INTO stock_movimientos(snapshot_id,almacen_id,producto_id,unidad_id,
                   documento_modulo,documento_id,detalle_id,tipo,delta,usuario_id)
                 VALUES(?,?,?,?,?,?,?,'SALIDA',?,?)"
            );
            $insert->execute([$snapshot,$warehouse,$product,$unit,$module,$originId,
                (int)$line['id'],(string)(-$quantity),$userId]);
        }
    }

    /**
     * Devuelve la cantidad al ANULAR, asociada al mismo snapshot del consumo.
     * La reversión no modifica un inventario físico posterior ya reconciliado.
     */
    public function reverse(string $module,int $originId,int $userId): void
    {
        if(!$this->pdo->inTransaction())
            throw new \LogicException('La reversión requiere transacción.');
        if(!$this->installed())return;
        $st=$this->pdo->prepare(
            "SELECT m.* FROM stock_movimientos m
             WHERE m.documento_modulo=? AND m.documento_id=? AND m.tipo='SALIDA'
             ORDER BY m.id FOR UPDATE"
        );
        $st->execute([$module,$originId]);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $m){
            $check=$this->pdo->prepare(
                "SELECT id FROM stock_movimientos WHERE documento_modulo=?
                 AND documento_id=? AND detalle_id=? AND tipo='REVERSION' LIMIT 1"
            );
            $check->execute([$module,$originId,$m['detalle_id']]);
            if($check->fetchColumn()!==false)continue;
            $insert=$this->pdo->prepare(
                "INSERT INTO stock_movimientos(snapshot_id,almacen_id,producto_id,unidad_id,
                   documento_modulo,documento_id,detalle_id,tipo,delta,usuario_id)
                 VALUES(?,?,?,?,?,?,?,'REVERSION',?,?)"
            );
            $insert->execute([$m['snapshot_id'],$m['almacen_id'],$m['producto_id'],$m['unidad_id'],
                $module,$originId,$m['detalle_id'],(string)(-(float)$m['delta']),$userId]);
        }
    }

    private function linkedDocument(string $module,array $header,int $id): ?array
    {
        if($module==='guias' && isset($header['documento_origen_id'])
            && (int)$header['documento_origen_id']>0)
            return ['documentos',(int)$header['documento_origen_id']];
        if($module==='documentos'){
            $query=$this->pdo->prepare('SELECT id FROM guias_cabecera WHERE documento_origen_id=? AND estado_registro IN (\'PUBLICADO\',\'ANULADO\') LIMIT 1');
            $query->execute([$id]);
            $guide=$query->fetchColumn();
            if($guide!==false)return ['guias',(int)$guide];
        }
        return null;
    }

    private function hasIssue(string $module,int $id): bool
    {
        $query=$this->pdo->prepare(
            "SELECT 1 FROM stock_movimientos WHERE documento_modulo=? AND documento_id=?
             AND tipo='SALIDA' LIMIT 1"
        );
        $query->execute([$module,$id]);
        return $query->fetchColumn()!==false;
    }

    private function warehouseFor(int $productId,int $branchId): int
    {
        // La asociación específica del producto tiene prioridad.
        $mapped=$this->pdo->prepare(
            'SELECT a.id FROM pucchun_producto_almacen r JOIN almacenes a ON a.id=r.almacen_id
             WHERE r.producto_id=? AND a.sucursal_id=? AND a.estado=1'
        );
        $mapped->execute([$productId,$branchId]);
        $found=$mapped->fetchAll(PDO::FETCH_COLUMN);
        if(count($found)===1)return (int)$found[0];
        if(count($found)>1)throw new HttpException(409,'Producto asignado a varios almacenes. Defina un único almacén de referencia.');
        // Si no tiene asignación, usar un único almacén con stock de ese producto en la sucursal.
        $select=$this->pdo->prepare(
            "SELECT DISTINCT a.id FROM almacenes a
             JOIN stock_cabecera sc ON sc.almacen_id=a.id AND sc.estado_registro='PUBLICADO'
             JOIN stock_detalle sd ON sd.stock_id=sc.id AND sd.producto_id=?
             WHERE a.sucursal_id=? AND a.estado=1 ORDER BY a.id"
        );
        $select->execute([$productId,$branchId]);
        $existing=$select->fetchAll(PDO::FETCH_COLUMN);
        if(count($existing)===1)return (int)$existing[0];
        if(count($existing)>1)throw new HttpException(409,
            'El producto tiene existencias en varios almacenes. Asigne uno en Catálogos maestros antes de publicar.');
        $select=$this->pdo->prepare('SELECT id FROM almacenes WHERE sucursal_id=? AND estado=1');
        $select->execute([$branchId]);
        $all=$select->fetchAll(PDO::FETCH_COLUMN);
        if(count($all)===1)return (int)$all[0];
        throw new HttpException(409,
            'No se puede identificar un almacén único para la venta. Asigne un almacén al producto en Catálogos maestros.');
    }

    /** @return array{int,int} snapshot_id, saldo */
    public function currentBalance(int $warehouseId,int $productId,int $unitId): array
    {
        $st=$this->pdo->prepare(
            "SELECT id FROM stock_cabecera
             WHERE almacen_id=? AND estado_registro='PUBLICADO'
             ORDER BY fecha_stock DESC,id DESC LIMIT 1"
        );
        $st->execute([$warehouseId]);
        $snapshot=(int)($st->fetchColumn()?:0);
        if($snapshot===0)return [0,0];
        $st=$this->pdo->prepare(
            'SELECT COALESCE(SUM(cantidad),0) FROM stock_detalle
             WHERE stock_id=? AND producto_id=? AND unidad_id=?'
        );
        $st->execute([$snapshot,$productId,$unitId]);
        $base=(float)$st->fetchColumn();
        $st=$this->pdo->prepare(
            'SELECT COALESCE(SUM(delta),0) FROM stock_movimientos
             WHERE snapshot_id=? AND producto_id=? AND unidad_id=?'
        );
        $st->execute([$snapshot,$productId,$unitId]);
        $delta=(float)$st->fetchColumn();
        return [$snapshot,(int)round($base+$delta)];
    }
}

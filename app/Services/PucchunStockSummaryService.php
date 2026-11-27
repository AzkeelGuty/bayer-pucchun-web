<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

/**
 * Último snapshot PUBLICADO por almacén Pucchún: agrupa unidades compatibles.
 * No mezcla reportes históricos ni los inventarios mensuales enviados por Bayer.
 */
final class PucchunStockSummaryService
{
    public function __construct(private ?PDO $pdo=null){$this->pdo??=\db();}

    public function latest(int $limit=100): array
    {
        $limit=max(1,min(1000,$limit));
        $ledger=(new StockMovementService($this->pdo))->installed();
        $movementJoin=$ledger?" LEFT JOIN (SELECT snapshot_id,producto_id,unidad_id,SUM(delta) AS delta
            FROM stock_movimientos GROUP BY snapshot_id,producto_id,unidad_id) mv
            ON mv.snapshot_id=sc.id AND mv.producto_id=sd.producto_id AND mv.unidad_id=sd.unidad_id":'';
        $amount=$ledger?'SUM(sd.cantidad)+COALESCE(MAX(mv.delta),0)':'SUM(sd.cantidad)';
        $sql="SELECT a.codigo warehouseId,a.nombre warehouseName,
                     p.codigo materialId,p.nombre materialName,
                     u.codigo measureUnit,{$amount} quantity,
                     DATE_FORMAT(sc.fecha_stock,'%Y-%m-%d') stockDate,
                     sc.id snapshotId
              FROM stock_cabecera sc
              JOIN stock_detalle sd ON sd.stock_id=sc.id
              {$movementJoin}
              JOIN almacenes a ON a.id=sc.almacen_id
              JOIN productos p ON p.id=sd.producto_id
              JOIN unidades_medida u ON u.id=sd.unidad_id
              WHERE sc.estado_registro='PUBLICADO'
                AND NOT EXISTS (
                  SELECT 1 FROM stock_cabecera newer
                  WHERE newer.almacen_id=sc.almacen_id
                    AND newer.estado_registro='PUBLICADO'
                    AND (newer.fecha_stock>sc.fecha_stock OR
                         (newer.fecha_stock=sc.fecha_stock AND newer.id>sc.id))
                )
              GROUP BY a.codigo,a.nombre,p.codigo,p.nombre,u.codigo,sc.fecha_stock,sc.id
              ORDER BY a.nombre,p.nombre,u.codigo
              LIMIT ".$limit;
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

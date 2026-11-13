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
        $sql="SELECT a.codigo warehouseId,a.nombre warehouseName,
                     p.codigo materialId,p.nombre materialName,
                     u.codigo measureUnit,SUM(sd.cantidad) quantity,
                     DATE_FORMAT(sc.fecha_stock,'%Y-%m-%d') stockDate,
                     sc.id snapshotId
              FROM stock_cabecera sc
              JOIN (
                SELECT almacen_id,MAX(id) last_id
                FROM stock_cabecera WHERE estado_registro='PUBLICADO'
                GROUP BY almacen_id
              ) last ON last.last_id=sc.id
              JOIN stock_detalle sd ON sd.stock_id=sc.id
              JOIN almacenes a ON a.id=sc.almacen_id
              JOIN productos p ON p.id=sd.producto_id
              JOIN unidades_medida u ON u.id=sd.unidad_id
              GROUP BY a.codigo,a.nombre,p.codigo,p.nombre,u.codigo,sc.fecha_stock,sc.id
              ORDER BY a.nombre,p.nombre,u.codigo
              LIMIT ".$limit;
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

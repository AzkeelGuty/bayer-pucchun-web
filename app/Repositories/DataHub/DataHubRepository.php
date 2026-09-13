<?php
declare(strict_types=1);

namespace App\Repositories\DataHub;

use App\DTO\DataQuery;
use PDO;

/** Consultas de datos internos publicados; la homologación Bayer se integra después. */
final class DataHubRepository
{
    private const DATASETS = [
        'sales' => [
            'header' => 'dc', 'date' => 'fecha',
            'sql' => <<<'SQL'
SELECT dc.id __header_id,dd.id __detail_id,e.ruc dealerId,e.razon_social dealerName,td.codigo documentTypeId,td.nombre documentType,dc.numero documentNumber,DATE_FORMAT(dc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT_WS(' ',v.nombres,v.apellidos)) salesName,s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,dd.cantidad quantity,pv.nombre province,dp.nombre department,ds.nombre district
FROM documentos_cabecera dc
JOIN tipos_documento td ON td.id=dc.tipo_documento_id
JOIN clientes c ON c.id=dc.cliente_id
JOIN vendedores v ON v.id=dc.vendedor_id
JOIN sucursales s ON s.id=dc.sucursal_id
JOIN empresas e ON e.id=s.empresa_id
JOIN documentos_detalle dd ON dd.documento_id=dc.id
JOIN productos pr ON pr.id=dd.producto_id
JOIN unidades_medida um ON um.id=dd.unidad_id
LEFT JOIN distritos ds ON ds.id=c.distrito_id
LEFT JOIN provincias pv ON pv.id=c.provincia_id
LEFT JOIN departamentos dp ON dp.id=c.departamento_id
SQL,
        ],
        'shipments' => [
            'header' => 'gc', 'date' => 'fecha',
            'sql' => <<<'SQL'
SELECT gc.id __header_id,gd.id __detail_id,e.ruc dealerId,e.razon_social dealerName,gc.numero documentNumber,DATE_FORMAT(gc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT_WS(' ',v.nombres,v.apellidos)) salesName,s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,gd.cantidad quantity,pv.nombre province,dp.nombre department,ds.nombre district
FROM guias_cabecera gc
JOIN clientes c ON c.id=gc.cliente_id
JOIN vendedores v ON v.id=gc.vendedor_id
JOIN sucursales s ON s.id=gc.sucursal_id
JOIN empresas e ON e.id=s.empresa_id
JOIN guias_detalle gd ON gd.guia_id=gc.id
JOIN productos pr ON pr.id=gd.producto_id
JOIN unidades_medida um ON um.id=gd.unidad_id
LEFT JOIN distritos ds ON ds.id=gc.distrito_id
LEFT JOIN provincias pv ON pv.id=gc.provincia_id
LEFT JOIN departamentos dp ON dp.id=gc.departamento_id
SQL,
        ],
        'inventory' => [
            'header' => 'sc', 'date' => 'fecha_stock',
            'sql' => <<<'SQL'
SELECT sc.id __header_id,sd.id __detail_id,e.ruc dealerId,e.razon_social dealerName,DATE_FORMAT(sc.fecha_stock,'%Y-%m-%d') stockDate,a.codigo warehouseId,a.nombre warehouseName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,l.codigo_lote batch,sd.cantidad quantity,DATE_FORMAT(l.fecha_vencimiento,'%Y-%m-%d') expirationDate
FROM stock_cabecera sc
JOIN almacenes a ON a.id=sc.almacen_id
JOIN sucursales s ON s.id=a.sucursal_id
JOIN empresas e ON e.id=s.empresa_id
JOIN stock_detalle sd ON sd.stock_id=sc.id
JOIN productos pr ON pr.id=sd.producto_id
JOIN unidades_medida um ON um.id=sd.unidad_id
LEFT JOIN lotes l ON l.id=sd.lote_id
SQL,
        ],
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    /** Cada fila representa una línea de detalle, no una cabecera. */
    public function page(DataQuery $query): array
    {
        [$base, $values] = $this->filtered($query);
        $sort = $query->sort === 'date'
            ? ($query->dataset === 'inventory' ? 'stockDate' : 'documentDate')
            : $query->sort;
        $direction = $query->direction;
        $order = "$sort $direction,__header_id $direction,__detail_id $direction";
        $outerOrder = "p.$sort $direction,p.__header_id $direction,p.__detail_id $direction";
        $limit = $query->perPage;
        $offset = $query->offset();
        // Una sola sentencia mantiene coherentes el total y la página, incluso vacía.
        $sql = "WITH filtered AS ($base)
            SELECT p.*,t.__total FROM (SELECT COUNT(*) __total FROM filtered) t
            LEFT JOIN (SELECT * FROM filtered ORDER BY $order LIMIT $limit OFFSET $offset) p ON 1=1
            ORDER BY $outerOrder";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
        $items = [];
        $total = 0;
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $total = (int) $row['__total'];
            if ($row['__detail_id'] === null) {
                continue;
            }
            unset($row['__total'], $row['__header_id'], $row['__detail_id']);
            $items[] = $row;
        }
        return ['items'=>$items, 'total'=>$total, 'page'=>$query->page, 'per_page'=>$limit,
            'total_pages'=>intdiv($total, $limit) + ($total % $limit === 0 ? 0 : 1)];
    }

    public function count(DataQuery $query): int
    {
        [$base, $values] = $this->filtered($query);
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM ($base) filtered");
        $statement->execute($values);
        return (int) $statement->fetchColumn();
    }

    private function filtered(DataQuery $query): array
    {
        $definition = self::DATASETS[$query->dataset];
        $header = $definition['header'];
        $date = $definition['date'];
        $where = ["$header.estado_registro='PUBLICADO'"];
        $values = [];
        foreach (['from'=>'>=', 'to'=>'<='] as $field => $operator) {
            if ($query->$field !== null) {
                $where[] = "$header.$date $operator ?";
                $values[] = $query->$field;
            }
        }
        // Estos filtros acotan resultados; no sustituyen los permisos del servicio.
        foreach (['sucursal'=>'s.id','almacen'=>'a.id','producto'=>'pr.id'] as $field => $column) {
            if ($query->$field !== null) {
                $where[] = "$column=?";
                $values[] = $query->$field;
            }
        }
        return [$definition['sql'] . ' WHERE ' . implode(' AND ', $where), $values];
    }
}

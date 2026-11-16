<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

/**
 * Inventario consolidado de consulta:
 * Pucchún: último snapshot PUBLICADO por almacén y producto (la asignación
 * de referencia se usa únicamente cuando falta un snapshot).
 * Bayer: reporte del canal vinculado al almacén de referencia opcional.
 * Nunca se suman stocks entre ambos sistemas.
 */
final class UnifiedStockService
{
    public function __construct(private ?PDO $pdo=null){$this->pdo??=\db();}

    private function unifiedSql(): string
    {
        $ledger=(new StockMovementService($this->pdo))->installed();
        $balance=$ledger
            ? 'SUM(sd.cantidad)+COALESCE(MAX(mv.delta),0)'
            : 'SUM(sd.cantidad)';
        $ledgerJoin=$ledger
            ? " LEFT JOIN (
                    SELECT snapshot_id,producto_id,unidad_id,SUM(delta) AS delta
                    FROM stock_movimientos GROUP BY snapshot_id,producto_id,unidad_id
                  ) mv ON mv.snapshot_id=sc.id
                    AND mv.producto_id=sd.producto_id AND mv.unidad_id=sd.unidad_id "
            : '';
        return "SELECT 'PUCCHUN' AS fuente,p.codigo AS codigo,p.nombre AS producto,
                   COALESCE(u.codigo,'') AS unidad,z.cantidad AS cantidad,
                   DATE_FORMAT(z.ultima_fecha,'%Y-%m-%d') AS periodo,
                   COALESCE(z.almacen_id,r.almacen_id) AS almacen_id,
                   COALESCE(a.nombre,'Sin almacén asignado') AS almacen,
                   CASE WHEN z.cantidad IS NULL THEN 'SIN_DATO'
                        WHEN z.cantidad<0 THEN 'RIESGO'
                        WHEN z.cantidad=0 THEN 'SIN_STOCK'
                        WHEN z.cantidad<=? THEN 'BAJO'
                        ELSE 'CON_STOCK' END AS estado
                FROM productos p
                LEFT JOIN (
                    SELECT sd.producto_id,sd.unidad_id,sc.almacen_id,
                           {$balance} cantidad,MAX(sc.fecha_stock) ultima_fecha
                    FROM stock_cabecera sc
                    JOIN stock_detalle sd ON sd.stock_id=sc.id
                    {$ledgerJoin}
                    WHERE sc.estado_registro='PUBLICADO'
                      AND NOT EXISTS (
                        SELECT 1 FROM stock_cabecera newer
                        WHERE newer.almacen_id=sc.almacen_id
                          AND newer.estado_registro='PUBLICADO'
                          AND (newer.fecha_stock>sc.fecha_stock OR
                               (newer.fecha_stock=sc.fecha_stock AND newer.id>sc.id))
                      )
                    GROUP BY sd.producto_id,sd.unidad_id,sc.almacen_id
                ) z ON z.producto_id=p.id
                LEFT JOIN pucchun_producto_almacen r ON r.producto_id=p.id
                LEFT JOIN almacenes a ON a.id=COALESCE(z.almacen_id,r.almacen_id)
                LEFT JOIN unidades_medida u ON u.id=COALESCE(z.unidad_id,p.unidad_base_id)
                UNION ALL
                SELECT 'BAYER' AS fuente,b.codigo_bayer AS codigo,
                       b.nombre_bayer AS producto,'' AS unidad,
                       b.stock_reportado AS cantidad,b.stock_periodo AS periodo,
                       r.almacen_id AS almacen_id,
                       COALESCE(a.nombre,'Sin almacén asignado') AS almacen,
                       CASE WHEN b.estado_catalogo='INACTIVO'
                                  OR b.stock_estado='DESACTIVADO'
                                  OR b.stock_reportado<0 THEN 'RIESGO'
                            WHEN b.stock_reportado IS NULL THEN 'SIN_DATO'
                            WHEN b.stock_reportado=0 THEN 'SIN_STOCK'
                            WHEN b.stock_reportado<=? THEN 'BAJO'
                            ELSE 'CON_STOCK' END AS estado
                FROM bayer_productos_catalogo b
                LEFT JOIN bayer_producto_almacen r ON r.producto_id=b.id
                LEFT JOIN almacenes a ON a.id=r.almacen_id";
    }

    public function warehouses(): array
    {
        $s=$this->pdo->query('SELECT id,codigo,nombre FROM almacenes WHERE estado=1 ORDER BY nombre,codigo');
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function browse(array $filters=[],int $perPage=15,int $page=1): array
    {
        $perPage=max(1,min(50,$perPage));
        $page=max(1,$page);
        $low=max(1,min(999999,(int)($filters['bajo']??10)));
        $source=strtoupper((string)($filters['fuente']??''));
        $state=strtoupper((string)($filters['estado']??''));
        // Compatibilidad con enlaces previos NEGATIVO/DESACTIVADO.
        if(in_array($state,['NEGATIVO','DESACTIVADO'],true))$state='RIESGO';
        $q=mb_substr(trim((string)($filters['q']??'')),0,100);
        $warehouse=(int)($filters['almacen_id']??0);
        if(!in_array($source,['BAYER','PUCCHUN'],true))$source='';
        if(!in_array($state,['RIESGO','BAJO','SIN_STOCK','SIN_DATO','CON_STOCK'],true))$state='';
        if($warehouse<1)$warehouse=0;

        $base=' FROM ('.$this->unifiedSql().') catalogo ';
        $conditions=[];$params=[$low,$low];
        if($source!==''){$conditions[]='catalogo.fuente=?';$params[]=$source;}
        if($warehouse>0){$conditions[]='catalogo.almacen_id=?';$params[]=$warehouse;}
        if($q!==''){
            $conditions[]='(catalogo.codigo LIKE ? OR catalogo.producto LIKE ?)';
            $params[]='%'.$q.'%';$params[]='%'.$q.'%';
        }
        $whereBase=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        // Totales únicos por catálogo y código: un SKU Pucchún puede estar en
        // varios almacenes o unidades, pero no se contabiliza varias veces.
        $unique="CONCAT(catalogo.fuente, ':', catalogo.codigo)";
        $stats=$this->pdo->prepare(
            "SELECT COUNT(*) AS total_filas,
              COUNT(DISTINCT $unique) AS total_productos,
              COUNT(DISTINCT CASE WHEN fuente='BAYER' THEN codigo END) AS total_bayer,
              COUNT(DISTINCT CASE WHEN fuente='PUCCHUN' THEN codigo END) AS total_pucchun,
              COUNT(DISTINCT CASE WHEN estado='CON_STOCK' THEN $unique END) AS disponible,
              COUNT(DISTINCT CASE WHEN estado='BAJO' THEN $unique END) AS bajo,
              COUNT(DISTINCT CASE WHEN estado='SIN_STOCK' THEN $unique END) AS cero,
              COUNT(DISTINCT CASE WHEN estado='RIESGO' THEN $unique END) AS riesgo,
              COUNT(DISTINCT CASE WHEN estado='SIN_DATO' THEN $unique END) AS sin_dato"
            .$base.$whereBase
        );
        $stats->execute($params);
        $summary=$stats->fetch(PDO::FETCH_ASSOC)?:[];
        if($state!==''){$conditions[]='catalogo.estado=?';$params[]=$state;}
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$base.$where);
        $count->execute($params);
        $total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($page,$pages);
        $offset=($page-1)*$perPage;
        $rows=$this->pdo->prepare('SELECT fuente,codigo,producto,unidad,cantidad,periodo,almacen_id,almacen,estado'
            .$base.$where
            ." ORDER BY FIELD(estado,'RIESGO','SIN_STOCK','BAJO','SIN_DATO','CON_STOCK'),
                  fuente,almacen,codigo LIMIT ".$perPage." OFFSET ".$offset);
        $rows->execute($params);
        return ['rows'=>$rows->fetchAll(PDO::FETCH_ASSOC),'summary'=>$summary,
                'total'=>$total,'pages'=>$pages,'page'=>$page,'perPage'=>$perPage,
                'filters'=>['fuente'=>$source,'estado'=>$state,'q'=>$q,'bajo'=>$low,
                            'almacen_id'=>$warehouse]];
    }

    /**
     * Exportación integral: fuentes independientes, último snapshot Pucchún
     * ajustado por ventas/guías y reporte mensual Bayer sin mezclar cantidades.
     * Se conserva una fila por producto/almacén/unidad cuando corresponde.
     */
    public function reportRows(array $filters=[]): array
    {
        $low=max(1,min(999999,(int)($filters['bajo']??10)));
        $source=strtoupper(trim((string)($filters['fuente']??'')));
        $state=strtoupper(trim((string)($filters['estado']??'')));
        $warehouse=(int)($filters['almacen_id']??0);
        $q=mb_substr(trim((string)($filters['q']??'')),0,100);
        $conditions=[];$params=[$low,$low];
        if(in_array($source,['PUCCHUN','BAYER'],true)){$conditions[]='fuente=?';$params[]=$source;}
        if(in_array($state,['CON_STOCK','BAJO','SIN_STOCK','SIN_DATO','RIESGO'],true)){
            $conditions[]='estado=?';$params[]=$state;
        }
        if($warehouse>0){$conditions[]='almacen_id=?';$params[]=$warehouse;}
        if($q!==''){$conditions[]='(codigo LIKE ? OR producto LIKE ?)';
            array_push($params,'%'.$q.'%','%'.$q.'%');}
        $sql='SELECT fuente,codigo,producto,unidad,cantidad,periodo,almacen,estado'
            .' FROM ('.$this->unifiedSql().') inventario'
            .($conditions?' WHERE '.implode(' AND ',$conditions):'')
            .' ORDER BY fuente,almacen,codigo,unidad';
        $st=$this->pdo->prepare($sql);$st->execute($params);
        $states=[
            'CON_STOCK'=>'Disponible',
            'BAJO'=>'Stock bajo',
            'SIN_STOCK'=>'En cero (0)',
            'SIN_DATO'=>'Sin reporte',
            'RIESGO'=>'Negativo / desactivado'
        ];
        $rows=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $line){
            $rows[]=[
                'source'=>$line['fuente']==='BAYER'?'Bayer':'Pucchún',
                'materialId'=>(string)$line['codigo'],
                'materialName'=>(string)$line['producto'],
                'warehouseName'=>(string)$line['almacen'],
                'measureUnit'=>(string)$line['unidad'],
                'quantity'=>$line['cantidad']===null?null:(float)$line['cantidad'],
                'stockDate'=>(string)($line['periodo']??''),
                'stockStatus'=>$states[$line['estado']]??$line['estado'],
            ];
        }
        return $rows;
    }
}

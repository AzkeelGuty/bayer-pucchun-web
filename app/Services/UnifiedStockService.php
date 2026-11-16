<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Consulta informativa con fuentes separadas. La cantidad de Bayer procede
 * de su último mes reportado; la de Pucchún suma los últimos snapshots
 * PUBLICADOS de cada almacén, nunca mezcla identidades entre catálogos.
 */
final class UnifiedStockService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo=null){$this->pdo=$pdo??\db();}

    private static function unifiedSql(): string
    {
        return "SELECT 'PUCCHUN' AS fuente,p.codigo AS codigo,p.nombre AS producto,
                    COALESCE(u.codigo,'') AS unidad,z.cantidad AS cantidad,
                    DATE_FORMAT(z.ultima_fecha,'%Y-%m-%d') AS periodo,
                    CASE WHEN z.cantidad IS NULL THEN 'SIN_DATO'
                         WHEN z.cantidad<0 THEN 'NEGATIVO'
                         WHEN z.cantidad=0 THEN 'SIN_STOCK'
                         WHEN z.cantidad<=? THEN 'BAJO'
                         ELSE 'CON_STOCK' END AS estado
                FROM productos p
                LEFT JOIN (
                    SELECT sd.producto_id,sd.unidad_id,SUM(sd.cantidad) cantidad,
                           MAX(sc.fecha_stock) ultima_fecha
                    FROM stock_cabecera sc
                    JOIN stock_detalle sd ON sd.stock_id=sc.id
                    WHERE sc.estado_registro='PUBLICADO'
                      AND NOT EXISTS (
                        SELECT 1 FROM stock_cabecera newer
                         WHERE newer.almacen_id=sc.almacen_id
                           AND newer.estado_registro='PUBLICADO'
                           AND (newer.fecha_stock>sc.fecha_stock OR
                             (newer.fecha_stock=sc.fecha_stock AND newer.id>sc.id))
                      )
                    GROUP BY sd.producto_id,sd.unidad_id
                ) z ON z.producto_id=p.id
                LEFT JOIN unidades_medida u ON u.id=COALESCE(z.unidad_id,p.unidad_base_id)
                UNION ALL
                SELECT 'BAYER' AS fuente,b.codigo_bayer AS codigo,
                       b.nombre_bayer AS producto,'' AS unidad,
                       b.stock_reportado AS cantidad,b.stock_periodo AS periodo,
                       CASE WHEN b.estado_catalogo='INACTIVO'
                                 OR b.stock_estado='DESACTIVADO' THEN 'DESACTIVADO'
                            WHEN b.stock_reportado IS NULL THEN 'SIN_DATO'
                            WHEN b.stock_reportado<0 THEN 'NEGATIVO'
                            WHEN b.stock_reportado=0 THEN 'SIN_STOCK'
                            WHEN b.stock_reportado<=? THEN 'BAJO'
                            ELSE 'CON_STOCK' END AS estado
                FROM bayer_productos_catalogo b";
    }

    public function browse(array $filters=[],int $perPage=15,int $page=1): array
    {
        $perPage=max(1,min(50,$perPage));
        $page=max(1,$page);
        $low=max(1,min(999999,(int)($filters['bajo']??10)));
        $source=strtoupper((string)($filters['fuente']??''));
        $state=strtoupper((string)($filters['estado']??''));
        $q=trim((string)($filters['q']??''));
        if(!in_array($source,['BAYER','PUCCHUN'],true))$source='';
        if(!in_array($state,['BAJO','SIN_STOCK','NEGATIVO','SIN_DATO',
             'CON_STOCK','DESACTIVADO'],true))$state='';
        $q=mb_substr($q,0,100);

        $base=' FROM ('.self::unifiedSql().') catalogo ';
        $conditions=[];$args=[$low,$low];
        if($source!==''){
            $conditions[]='catalogo.fuente=?';$args[]=$source;
        }
        if($q!==''){
            $conditions[]='(catalogo.codigo LIKE ? OR catalogo.producto LIKE ?)';
            $args[]='%'.$q.'%';$args[]='%'.$q.'%';
        }
        $baseWhere=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $stats=$this->pdo->prepare(
            'SELECT COUNT(*) AS total,
             COALESCE(SUM(estado=\'BAJO\'),0) AS bajo,
             COALESCE(SUM(estado=\'SIN_STOCK\'),0) AS cero,
             COALESCE(SUM(estado=\'NEGATIVO\'),0) AS negativo,
             COALESCE(SUM(estado=\'SIN_DATO\'),0) AS sin_dato,
             COALESCE(SUM(estado=\'DESACTIVADO\'),0) AS desactivado'
            .$base.$baseWhere
        );
        $stats->execute($args);
        $summary=$stats->fetch(PDO::FETCH_ASSOC)?:[];
        if($state!==''){
            $conditions[]='catalogo.estado=?';
            $args[]=$state;
        }
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$base.$where);
        $count->execute($args);
        $total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($page,$pages);
        $offset=($page-1)*$perPage;

        $rows=$this->pdo->prepare('SELECT fuente,codigo,producto,unidad,cantidad,periodo,estado'
            .$base.$where
            .' ORDER BY FIELD(estado,\'NEGATIVO\',\'SIN_STOCK\',\'BAJO\',\'DESACTIVADO\','
            .'\'SIN_DATO\',\'CON_STOCK\'),fuente,codigo'
            .' LIMIT '.$perPage.' OFFSET '.$offset);
        $rows->execute($args);
        return ['rows'=>$rows->fetchAll(PDO::FETCH_ASSOC),'summary'=>$summary,
            'total'=>$total,'pages'=>$pages,'page'=>$page,'perPage'=>$perPage,
            'filters'=>['fuente'=>$source,'estado'=>$state,'q'=>$q,'bajo'=>$low]];
    }
}

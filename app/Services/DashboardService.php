<?php
declare(strict_types=1);

namespace App\Services;

final class DashboardService
{
    private function ownerClause(?int $ownerId,string $prefix=''): string
    {
        if($ownerId===null || $ownerId<1) return '';
        $column=$prefix!=='' ? $prefix.'.created_by' : 'created_by';
        return $column.'='.(int)$ownerId;
    }

    public function kpis(bool $publishedOnly=false,?int $ownerId=null): array
    {
        if($publishedOnly && ($ownerId===null || $ownerId<1)){
            $row=\db()->query("SELECT
                (SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='PUBLICADO') documentos,
                (SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='PUBLICADO') guias,
                (SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro='PUBLICADO') stock")->fetch();
            $docs=(int)($row['documentos']??0);
            $guides=(int)($row['guias']??0);
            $stock=(int)($row['stock']??0);
            return [
                'documentos'=>$docs,
                'guias'=>$guides,
                'stock'=>$stock,
                'publicados'=>$docs+$guides+$stock,
            ];
        }

        // Una sola petición a MySQL para todos los KPI, en lugar de
        // múltiples round-trips con los mismos filtros de pertenencia.
        $owner=$this->ownerClause($ownerId);
        $owned=$owner!==''?' WHERE '.$owner:'';
        $published=static fn(string $condition):string=>($owned!==''?$owned.' AND ':' WHERE ').$condition;
        $sql="SELECT
            (SELECT COUNT(*) FROM documentos_cabecera{$owned}) AS documentos,
            (SELECT COUNT(*) FROM guias_cabecera{$owned}) AS guias,
            (SELECT COUNT(*) FROM stock_cabecera{$owned}) AS stock,
            (SELECT COUNT(*) FROM clientes) AS clientes,
            (SELECT COUNT(*) FROM productos) AS productos,
            (
              (SELECT COUNT(*) FROM documentos_cabecera".$published("estado_registro='PUBLICADO'").")+
              (SELECT COUNT(*) FROM guias_cabecera".$published("estado_registro='PUBLICADO'").")+
              (SELECT COUNT(*) FROM stock_cabecera".$published("estado_registro='PUBLICADO'").")
            ) AS publicados,
            (
              (SELECT COUNT(*) FROM documentos_cabecera".$published("estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO')").")+
              (SELECT COUNT(*) FROM guias_cabecera".$published("estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO')").")+
              (SELECT COUNT(*) FROM stock_cabecera".$published("estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO')").")
            ) AS pendientes";
        $out=\db()->query($sql)->fetch() ?: [];
        foreach(['documentos','guias','stock','clientes','productos','publicados','pendientes'] as $key){
            $out[$key]=(int)($out[$key]??0);
        }
        return $out;
    }

    public function salesByMonth(?int $ownerId=null): array
    {
        $owner=$this->ownerClause($ownerId,'dc');
        $ownerSql=$owner!==''?' AND '.$owner:'';
        return \db()->query("SELECT DATE_FORMAT(dc.fecha,'%Y-%m') periodo,SUM(dd.cantidad) cantidad
            FROM documentos_cabecera dc JOIN documentos_detalle dd ON dd.documento_id=dc.id
            WHERE dc.estado_registro='PUBLICADO'$ownerSql
            GROUP BY DATE_FORMAT(dc.fecha,'%Y-%m')
            ORDER BY periodo DESC LIMIT 12")->fetchAll();
    }

    public function topProducts(?int $ownerId=null): array
    {
        $owner=(int)($ownerId??0);
        $docOwner=$owner>0?' AND dc.created_by='.$owner:'';
        $guideOwner=$owner>0?' AND gc.created_by='.$owner:'';
        $stockOwner=$owner>0?' AND sc.created_by='.$owner:'';

        return \db()->query("SELECT p.nombre,SUM(x.cantidad) cantidad FROM (
            SELECT dd.producto_id,dd.cantidad FROM documentos_detalle dd JOIN documentos_cabecera dc ON dc.id=dd.documento_id WHERE dc.estado_registro='PUBLICADO'$docOwner
            UNION ALL
            SELECT gd.producto_id,gd.cantidad FROM guias_detalle gd JOIN guias_cabecera gc ON gc.id=gd.guia_id WHERE gc.estado_registro='PUBLICADO'$guideOwner
            UNION ALL
            SELECT sd.producto_id,sd.cantidad FROM stock_detalle sd JOIN stock_cabecera sc ON sc.id=sd.stock_id WHERE sc.estado_registro='PUBLICADO'$stockOwner
        ) x JOIN productos p ON p.id=x.producto_id GROUP BY p.id,p.nombre ORDER BY cantidad DESC LIMIT 10")->fetchAll();
    }

    public function publishedDistribution(): array
    {
        $k=$this->kpis(true);
        return [
            ['label'=>'Documentos','value'=>$k['documentos']],
            ['label'=>'Guías','value'=>$k['guias']],
            ['label'=>'Stock','value'=>$k['stock']],
        ];
    }

    public function statusDistribution(?int $ownerId=null): array
    {
        $owner=(int)($ownerId??0);
        $where=$owner>0?' WHERE created_by='.$owner:'';
        $sql="SELECT estado_registro,COUNT(*) total FROM (
            SELECT estado_registro FROM documentos_cabecera$where
            UNION ALL SELECT estado_registro FROM guias_cabecera$where
            UNION ALL SELECT estado_registro FROM stock_cabecera$where
        ) x GROUP BY estado_registro ORDER BY total DESC";
        return \db()->query($sql)->fetchAll();
    }

    public function lastPublishedAt(): ?string
    {
        $value=\db()->query("SELECT MAX(dt) FROM (
            SELECT published_at dt FROM documentos_cabecera
            UNION ALL SELECT published_at FROM guias_cabecera
            UNION ALL SELECT published_at FROM stock_cabecera
        ) x")->fetchColumn();
        return $value ?: null;
    }

    public function recentActivity(?int $ownerId=null): array
    {
        $owner=(int)($ownerId??0);
        $where=$owner>0?' WHERE created_by='.$owner:'';
        return \db()->query("SELECT * FROM (
            SELECT 'Documento' tipo,numero referencia,estado_registro estado,created_at fecha FROM documentos_cabecera$where
            UNION ALL SELECT 'Guía',numero,estado_registro,created_at FROM guias_cabecera$where
            UNION ALL SELECT 'Stock',CONCAT('Stock #',id),estado_registro,created_at FROM stock_cabecera$where
        ) x ORDER BY fecha DESC LIMIT 8")->fetchAll();
    }
}

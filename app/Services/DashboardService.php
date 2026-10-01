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
        $out=[];
        foreach(['documentos_cabecera'=>'documentos','guias_cabecera'=>'guias','stock_cabecera'=>'stock'] as $table=>$key){
            $where=[];
            if($publishedOnly) $where[]="estado_registro='PUBLICADO'";
            $owner=$this->ownerClause($ownerId);
            if($owner!=='') $where[]=$owner;
            $sql='SELECT COUNT(*) FROM '.$table.($where?' WHERE '.implode(' AND ',$where):'');
            $out[$key]=(int)\db()->query($sql)->fetchColumn();
        }

        $out['clientes']=(int)\db()->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
        $out['productos']=(int)\db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();

        $ownerDoc=$this->ownerClause($ownerId);
        $ownerGuide=$this->ownerClause($ownerId);
        $ownerStock=$this->ownerClause($ownerId);
        $docOwner=$ownerDoc!==''?' AND '.$ownerDoc:'';
        $guideOwner=$ownerGuide!==''?' AND '.$ownerGuide:'';
        $stockOwner=$ownerStock!==''?' AND '.$ownerStock:'';

        $out['publicados']=(int)\db()->query("SELECT
            (SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='PUBLICADO'$docOwner)+
            (SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='PUBLICADO'$guideOwner)+
            (SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro='PUBLICADO'$stockOwner)")->fetchColumn();

        $out['pendientes']=(int)\db()->query("SELECT
            (SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO')$docOwner)+
            (SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO')$guideOwner)+
            (SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO')$stockOwner)")->fetchColumn();

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

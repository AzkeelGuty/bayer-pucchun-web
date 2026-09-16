<?php
declare(strict_types=1);

namespace App\Services;

final class DashboardService
{
    public function kpis(bool $publishedOnly=false): array
    {
        $status=$publishedOnly?" WHERE estado_registro='PUBLICADO'":'';
        $out=[];
        foreach(['documentos_cabecera'=>'documentos','guias_cabecera'=>'guias','stock_cabecera'=>'stock'] as $t=>$k){
            $out[$k]=(int)\db()->query("SELECT COUNT(*) FROM $t$status")->fetchColumn();
        }
        $out['clientes']=(int)\db()->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
        $out['productos']=(int)\db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();
        $out['publicados']=(int)\db()->query("SELECT
            (SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='PUBLICADO')+
            (SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='PUBLICADO')+
            (SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro='PUBLICADO')")->fetchColumn();
        $out['pendientes']=(int)\db()->query("SELECT
            (SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO'))+
            (SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO'))+
            (SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro IN ('BORRADOR','VALIDADO','OBSERVADO'))")->fetchColumn();
        return $out;
    }

    public function salesByMonth(): array
    {
        return \db()->query("SELECT DATE_FORMAT(dc.fecha,'%Y-%m') periodo,SUM(dd.cantidad) cantidad
            FROM documentos_cabecera dc JOIN documentos_detalle dd ON dd.documento_id=dc.id
            WHERE dc.estado_registro='PUBLICADO'
            GROUP BY DATE_FORMAT(dc.fecha,'%Y-%m')
            ORDER BY periodo DESC LIMIT 12")->fetchAll();
    }

    public function topProducts(): array
    {
        return \db()->query("SELECT p.nombre,SUM(x.cantidad) cantidad FROM (
            SELECT dd.producto_id,dd.cantidad FROM documentos_detalle dd JOIN documentos_cabecera dc ON dc.id=dd.documento_id WHERE dc.estado_registro='PUBLICADO'
            UNION ALL
            SELECT gd.producto_id,gd.cantidad FROM guias_detalle gd JOIN guias_cabecera gc ON gc.id=gd.guia_id WHERE gc.estado_registro='PUBLICADO'
            UNION ALL
            SELECT sd.producto_id,sd.cantidad FROM stock_detalle sd JOIN stock_cabecera sc ON sc.id=sd.stock_id WHERE sc.estado_registro='PUBLICADO'
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

    public function statusDistribution(): array
    {
        $sql="SELECT estado_registro,COUNT(*) total FROM (
            SELECT estado_registro FROM documentos_cabecera
            UNION ALL SELECT estado_registro FROM guias_cabecera
            UNION ALL SELECT estado_registro FROM stock_cabecera
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

    public function recentActivity(): array
    {
        return \db()->query("SELECT * FROM (
            SELECT 'Documento' tipo,numero referencia,estado_registro estado,created_at fecha FROM documentos_cabecera
            UNION ALL SELECT 'Guía',numero,estado_registro,created_at FROM guias_cabecera
            UNION ALL SELECT 'Stock',CONCAT('Stock #',id),estado_registro,created_at FROM stock_cabecera
        ) x ORDER BY fecha DESC LIMIT 8")->fetchAll();
    }
}

<?php
namespace App\Services;
class DashboardService {
    public function kpis(bool $publishedOnly=false): array {
        $status=$publishedOnly?" WHERE estado_registro='PUBLICADO'":'';
        $out=[];
        foreach(['documentos_cabecera'=>'documentos','guias_cabecera'=>'guias','stock_cabecera'=>'stock'] as $t=>$k){$out[$k]=(int)\db()->query("SELECT COUNT(*) FROM $t$status")->fetchColumn();}
        $out['clientes']=(int)\db()->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
        $out['productos']=(int)\db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();
        $out['publicados']=(int)(\db()->query("SELECT (SELECT COUNT(*) FROM documentos_cabecera WHERE estado_registro='PUBLICADO')+(SELECT COUNT(*) FROM guias_cabecera WHERE estado_registro='PUBLICADO')+(SELECT COUNT(*) FROM stock_cabecera WHERE estado_registro='PUBLICADO')")->fetchColumn());
        return $out;
    }
    public function salesByMonth(): array {
        return \db()->query("SELECT DATE_FORMAT(dc.fecha,'%Y-%m') periodo,SUM(dd.cantidad) cantidad FROM documentos_cabecera dc JOIN documentos_detalle dd ON dd.documento_id=dc.id WHERE dc.estado_registro='PUBLICADO' GROUP BY periodo ORDER BY periodo DESC LIMIT 12")->fetchAll();
    }
    public function topProducts(): array {
        return \db()->query("SELECT p.nombre,SUM(dd.cantidad) cantidad FROM documentos_cabecera dc JOIN documentos_detalle dd ON dd.documento_id=dc.id JOIN productos p ON p.id=dd.producto_id WHERE dc.estado_registro='PUBLICADO' GROUP BY p.id ORDER BY cantidad DESC LIMIT 10")->fetchAll();
    }
}

<?php
namespace App\Repositories;
use App\Services\MasterDataService;
class DocumentRepository {
    public function create(array $d): int {
        $m=new MasterDataService(); $pdo=\db();$pdo->beginTransaction();
        try{$company=$m->company($d);$geo=$m->district($d['department'],$d['province'],$d['district']);$branch=$m->branch($d,$company,$geo[2]);$client=$m->client($d,$geo);$seller=$m->seller($d);$unit=$m->unit($d);$product=$m->product($d,$unit);$type=$m->docType($d);
        $st=$pdo->prepare('INSERT INTO documentos_cabecera(tipo_documento_id,numero,fecha,cliente_id,vendedor_id,sucursal_id,estado_registro,created_by,created_at) VALUES(?,?,?,?,?,?,\'BORRADOR\',?,NOW())');$st->execute([$type,$d['documentNumber'],$d['documentDate'],$client,$seller,$branch,\auth_user()['id']]);$id=(int)$pdo->lastInsertId();
        $st=$pdo->prepare('INSERT INTO documentos_detalle(documento_id,producto_id,unidad_id,cantidad,valor_unitario) VALUES(?,?,?,?,?)');$st->execute([$id,$product,$unit,$d['quantity'],$d['valorUnitario']??0]);$pdo->commit();return $id;}catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
    public function all(): array {return \db()->query("SELECT dc.id,dc.numero,dc.fecha,dc.estado_registro,c.razon_social cliente,v.nombres vendedor,s.nombre sucursal,COUNT(dd.id) items,SUM(dd.cantidad) cantidad FROM documentos_cabecera dc JOIN clientes c ON c.id=dc.cliente_id JOIN vendedores v ON v.id=dc.vendedor_id JOIN sucursales s ON s.id=dc.sucursal_id JOIN documentos_detalle dd ON dd.documento_id=dc.id GROUP BY dc.id ORDER BY dc.id DESC LIMIT 300")->fetchAll();}
    public function status(int $id,string $status): void {$st=\db()->prepare('UPDATE documentos_cabecera SET estado_registro=?,published_at=IF(?=\'PUBLICADO\',NOW(),published_at) WHERE id=?');$st->execute([$status,$status,$id]);}
}

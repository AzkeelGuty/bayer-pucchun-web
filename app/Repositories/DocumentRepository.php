<?php
declare(strict_types=1);

namespace App\Repositories;

class DocumentRepository extends OperationalRepository
{
    protected const HEADER = 'documentos_cabecera';
    protected const DETAIL = 'documentos_detalle';
    protected const PARENT_KEY = 'documento_id';
    protected const HEADER_FIELDS = ['tipo_documento_id', 'numero', 'fecha', 'cliente_id', 'vendedor_id', 'sucursal_id', 'estado_comprobante'];
    protected const DETAIL_FIELDS = ['producto_id', 'unidad_id', 'cantidad', 'valor_unitario'];

    protected function normalize(array $header, array $details): array
    {
        $status=is_scalar($header['estado_comprobante']??null)
            ? strtoupper(trim((string)$header['estado_comprobante']))
            : 'VIGENTE';
        if($status==='') $status='VIGENTE';
        if(!in_array($status,['VIGENTE','ANULADO'],true)){
            throw new \InvalidArgumentException('Estado de comprobante inválido.');
        }
        $header['estado_comprobante']=$status;
        return parent::normalize($header,$details);
    }

    public function findByNumber(int $typeId, string $number): ?array
    {
        $id = $this->execute('SELECT id FROM documentos_cabecera WHERE tipo_documento_id=? AND numero=?', [$typeId, $number])->fetchColumn();
        return $id === false ? null : $this->find((int) $id);
    }

    public function updateVoucherStatus(int $id, int $version, string $status, int $actorId): int
    {
        foreach ([$id,$version,$actorId] as $value) self::positiveId($value);
        $status=strtoupper(trim($status));
        if(!in_array($status,['VIGENTE','ANULADO'],true)){
            throw new \InvalidArgumentException('Estado de comprobante inválido.');
        }
        $st=$this->execute(
            'UPDATE documentos_cabecera
             SET estado_comprobante=?,version=version+1,updated_at=CURRENT_TIMESTAMP,updated_by=?
             WHERE id=? AND version=?',
            [$status,$actorId,$id,$version]
        );
        if($st->rowCount()!==1){
            throw new \RuntimeException('Registro inexistente, estado de origen distinto o version desactualizada.');
        }
        return $version+1;
    }

    public function all(array $filters = [], int $limit = 300, int $offset = 0): array
    {
        return $this->listing('SELECT h.id,h.numero,h.fecha,h.estado_comprobante,h.estado_registro,h.version,c.razon_social cliente,v.nombres vendedor,s.nombre sucursal,
            COALESCE(d.items,0) items,COALESCE(d.cantidad,0) cantidad
            FROM documentos_cabecera h JOIN clientes c ON c.id=h.cliente_id
            JOIN vendedores v ON v.id=h.vendedor_id JOIN sucursales s ON s.id=h.sucursal_id
            LEFT JOIN (SELECT documento_id,COUNT(*) items,SUM(cantidad) cantidad FROM documentos_detalle GROUP BY documento_id) d ON d.documento_id=h.id', $filters, $limit, $offset);
    }

}

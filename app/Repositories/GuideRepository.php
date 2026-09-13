<?php
declare(strict_types=1);

namespace App\Repositories;

class GuideRepository extends OperationalRepository
{
    protected const HEADER = 'guias_cabecera';
    protected const DETAIL = 'guias_detalle';
    protected const PARENT_KEY = 'guia_id';
    protected const HEADER_FIELDS = ['numero', 'fecha', 'cliente_id', 'vendedor_id', 'sucursal_id'];
    protected const OPTIONAL_FIELDS = ['departamento_id', 'provincia_id', 'distrito_id'];

    public function findByNumber(string $number): ?array
    {
        $id = $this->execute('SELECT id FROM guias_cabecera WHERE numero=?', [$number])->fetchColumn();
        return $id === false ? null : $this->find((int) $id);
    }

    public function all(array $filters = [], int $limit = 300, int $offset = 0): array
    {
        return $this->listing('SELECT h.id,h.numero,h.fecha,h.estado_registro,h.version,c.razon_social cliente,v.nombres vendedor,s.nombre sucursal,
            COALESCE(d.items,0) items,COALESCE(d.cantidad,0) cantidad
            FROM guias_cabecera h JOIN clientes c ON c.id=h.cliente_id
            JOIN vendedores v ON v.id=h.vendedor_id JOIN sucursales s ON s.id=h.sucursal_id
            LEFT JOIN (SELECT guia_id,COUNT(*) items,SUM(cantidad) cantidad FROM guias_detalle GROUP BY guia_id) d ON d.guia_id=h.id', $filters, $limit, $offset);
    }

    protected function checkReferences(array $header, array $details): void
    {
        $geo = [$header['departamento_id'], $header['provincia_id'], $header['distrito_id']];
        if ($geo === [null, null, null]) {
            return; // Drafts may omit geography; Services check mandatory fields before publication.
        }
        if (in_array(null, $geo, true)) {
            throw new \InvalidArgumentException('Ubigeo debe enviarse completo o completamente nulo.');
        }
        $exists = $this->execute('SELECT d.id FROM distritos d JOIN provincias p ON p.id=d.provincia_id
            WHERE p.departamento_id=? AND p.id=? AND d.id=? LOCK IN SHARE MODE', $geo)->fetchColumn();
        if ($exists === false) {
            throw new \InvalidArgumentException('Distrito, provincia y departamento inconsistentes.');
        }
    }

}

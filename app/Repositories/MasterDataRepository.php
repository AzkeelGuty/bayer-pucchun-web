<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Read-only lookups used to populate dropdowns in capture forms and filters. */
class MasterDataRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    /** Includes ubigeo and last known operational seller/branch for assisted capture. */
    public function clientes(): array
    {
        return $this->all('SELECT c.id,c.nro_doc,c.razon_social,c.departamento_id,c.provincia_id,c.distrito_id,
            COALESCE(
                (SELECT g.vendedor_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                (SELECT d.vendedor_id FROM documentos_cabecera d WHERE d.cliente_id=c.id ORDER BY d.fecha DESC,d.id DESC LIMIT 1)
            ) vendedor_sugerido_id,
            COALESCE(
                (SELECT g.sucursal_id FROM guias_cabecera g WHERE g.cliente_id=c.id ORDER BY g.fecha DESC,g.id DESC LIMIT 1),
                (SELECT d.sucursal_id FROM documentos_cabecera d WHERE d.cliente_id=c.id ORDER BY d.fecha DESC,d.id DESC LIMIT 1)
            ) sucursal_sugerida_id
            FROM clientes c ORDER BY c.razon_social');
    }

    public function vendedores(): array
    {
        return $this->all("SELECT id, codigo, TRIM(CONCAT(nombres, ' ', apellidos)) nombre FROM vendedores WHERE estado = 1 ORDER BY nombre");
    }

    public function sucursales(): array
    {
        return $this->all('SELECT id, codigo, nombre FROM sucursales WHERE estado = 1 ORDER BY nombre');
    }

    public function almacenes(): array
    {
        return $this->all('SELECT id, codigo, nombre FROM almacenes WHERE estado = 1 ORDER BY nombre');
    }

    public function productos(): array
    {
        return $this->all('SELECT id, codigo, nombre, unidad_base_id FROM productos WHERE estado = 1 ORDER BY nombre');
    }

    public function unidades(): array
    {
        return $this->all('SELECT id, codigo, nombre FROM unidades_medida ORDER BY nombre');
    }

    /** Every lote with its producto_id, so the form can filter them client-side per line. */
    public function lotes(): array
    {
        return $this->all("SELECT id, producto_id, codigo_lote, DATE_FORMAT(fecha_vencimiento, '%Y-%m-%d') fecha_vencimiento FROM lotes WHERE estado = 1 ORDER BY codigo_lote");
    }

    public function departamentos(): array
    {
        return $this->all('SELECT id, nombre FROM departamentos ORDER BY nombre');
    }

    /** Every provincia with its departamento_id, filtered client-side per selected departamento. */
    public function provincias(): array
    {
        return $this->all('SELECT id, departamento_id, nombre FROM provincias ORDER BY nombre');
    }

    /** Every distrito with its provincia_id, filtered client-side per selected provincia. */
    public function distritos(): array
    {
        return $this->all('SELECT id, provincia_id, nombre FROM distritos ORDER BY nombre');
    }

    private function all(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
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

    public function clientes(): array
    {
        return $this->all('SELECT id, nro_doc, razon_social FROM clientes ORDER BY razon_social');
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
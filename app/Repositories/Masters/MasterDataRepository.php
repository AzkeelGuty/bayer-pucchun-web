<?php
declare(strict_types=1);

namespace App\Repositories\Masters;

use InvalidArgumentException;
use PDO;


final class MasterDataRepository
{

    private const CATALOGS = [
        'empresas' => ['fields' => 'id,ruc AS codigo,razon_social AS nombre,estado', 'search' => ['ruc', 'razon_social'], 'filters' => [], 'active' => true],
        'sucursales' => ['fields' => 'id,codigo,nombre,empresa_id,distrito_id,estado', 'search' => ['codigo', 'nombre'], 'filters' => ['empresa_id', 'distrito_id'], 'active' => true],
        'almacenes' => ['fields' => 'id,codigo,nombre,sucursal_id,estado', 'search' => ['codigo', 'nombre'], 'filters' => ['sucursal_id'], 'active' => true],
        'clientes' => ['fields' => 'id,codigo,nro_doc,razon_social AS nombre,departamento_id,provincia_id,distrito_id', 'search' => ['codigo', 'nro_doc', 'razon_social'], 'filters' => ['departamento_id', 'provincia_id', 'distrito_id'], 'active' => false],
        'vendedores' => ['fields' => "id,codigo,TRIM(CONCAT_WS(' ',nombres,apellidos)) AS nombre,estado", 'search' => ['codigo', 'nombres', 'apellidos'], 'filters' => [], 'active' => true],
        'productos' => ['fields' => 'id,codigo,nombre,categoria_id,marca_id,unidad_base_id,estado', 'search' => ['codigo', 'nombre'], 'filters' => ['categoria_id', 'marca_id', 'unidad_base_id'], 'active' => true],
        'unidades_medida' => ['fields' => 'id,codigo,nombre,abreviatura,factor_base', 'search' => ['codigo', 'nombre'], 'filters' => [], 'active' => false],
        'lotes' => ['fields' => 'id,codigo_lote AS codigo,codigo_lote AS nombre,producto_id,fecha_vencimiento,estado', 'search' => ['codigo_lote'], 'filters' => ['producto_id'], 'active' => true],
        'tipos_documento' => ['fields' => 'id,codigo,nombre,sunat_code', 'search' => ['codigo', 'nombre'], 'filters' => [], 'active' => false],
        'departamentos' => ['fields' => 'id,nombre', 'search' => ['nombre'], 'filters' => [], 'active' => false],
        'provincias' => ['fields' => 'id,nombre,departamento_id', 'search' => ['nombre'], 'filters' => ['departamento_id'], 'active' => false],
        'distritos' => ['fields' => 'id,nombre,provincia_id', 'search' => ['nombre'], 'filters' => ['provincia_id'], 'active' => false],
        'categorias_producto' => ['fields' => 'id,nombre', 'search' => ['nombre'], 'filters' => [], 'active' => false],
        'marcas' => ['fields' => 'id,nombre', 'search' => ['nombre'], 'filters' => [], 'active' => false],
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function search(string $catalog, string $query = '', array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $definition = self::definition($catalog);
        $query = trim($query);
        if (strlen($query) > 200 || $limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Busqueda de hasta 200 bytes; limite entre 1 y 100; offset no negativo.');
        }
        if (array_diff(array_keys($filters), $definition['filters'])) {
            throw new InvalidArgumentException('Filtro no admitido para el catalogo.');
        }
        $where = $definition['active'] ? ['estado=1'] : [];
        $values = [];
        foreach ($filters as $field => $value) {
            $where[] = "$field=?";
            $values[] = self::positiveId($value);
        }
        if ($query !== '') {
            // Los caracteres % y _ del usuario se buscan literalmente, no como comodines.
            $pattern = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $terms = [];
            foreach ($definition['search'] as $field) {
                $terms[] = "$field LIKE ? ESCAPE '!'";
                $values[] = $pattern;
            }
            $where[] = '(' . implode(' OR ', $terms) . ')';
        }
        $sql = 'SELECT ' . $definition['fields'] . " FROM $catalog";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $statement = $this->pdo->prepare($sql . " ORDER BY nombre,id LIMIT $limit OFFSET $offset");
        $statement->execute($values);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $catalog, int $id): ?array
    {
        $definition = self::definition($catalog);
        self::positiveId($id);
        $statement = $this->pdo->prepare('SELECT ' . $definition['fields'] . " FROM $catalog WHERE id=?");
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private static function definition(string $catalog): array
    {
        return self::CATALOGS[$catalog] ?? throw new InvalidArgumentException('Catalogo no admitido.');
    }

    private static function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || is_bool($value) || is_float($value)) {
            throw new InvalidArgumentException('El filtro o identificador debe ser un entero positivo.');
        }
        return $id;
    }
}

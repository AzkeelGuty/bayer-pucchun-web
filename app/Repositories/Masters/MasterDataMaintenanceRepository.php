<?php
declare(strict_types=1);

namespace App\Repositories\Masters;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Persistencia del mantenimiento; el servicio debe autorizar y validar antes de llamar. */
final class MasterDataMaintenanceRepository
{
    // Solo estos catálogos y columnas pueden formar parte del SQL.
    private const FIELDS = [
        'empresas' => 'ruc razon_social nombre_comercial estado',
        'sucursales' => 'empresa_id codigo nombre direccion distrito_id estado',
        'almacenes' => 'sucursal_id codigo nombre tipo estado',
        'clientes' => 'codigo tipo_doc nro_doc razon_social departamento_id provincia_id distrito_id',
        'vendedores' => 'codigo nombres apellidos email estado',
        'productos' => 'codigo nombre categoria_id marca_id unidad_base_id estado',
        'unidades_medida' => 'codigo nombre abreviatura factor_base',
        'lotes' => 'producto_id codigo_lote fecha_vencimiento estado',
        'tipos_documento' => 'codigo nombre sunat_code',
        'departamentos' => 'nombre',
        'provincias' => 'departamento_id nombre',
        'distritos' => 'provincia_id nombre',
        'categorias_producto' => 'nombre descripcion',
        'marcas' => 'nombre fabricante',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    /** Crea explícitamente un maestro; nunca busca otro para reutilizarlo. */
    public function create(string $catalog, array $data, int $actorId): int
    {
        $this->validate($catalog, $data, $actorId);
        return $this->atomic(function () use ($catalog, $data, $actorId): int {
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $statement = $this->pdo->prepare("INSERT INTO $catalog ($columns) VALUES ($placeholders)");
            $statement->execute(array_values($data));
            $id = (int) $this->pdo->lastInsertId();
            $this->audit($catalog, $id, $actorId, 'CREAR', array_keys($data));
            return $id;
        });
    }

    /** Actualiza únicamente las columnas recibidas; no reemplaza la fila completa. */
    public function update(string $catalog, int $id, array $changes, int $actorId): void
    {
        $this->validate($catalog, $changes, $actorId);
        if ($id < 1) {
            throw new InvalidArgumentException('El identificador debe ser positivo.');
        }
        $this->atomic(function () use ($catalog, $id, $changes, $actorId): void {
            // El bloqueo evita que la fila desaparezca entre la comprobación y la escritura.
            $statement = $this->pdo->prepare("SELECT id FROM $catalog WHERE id=? FOR UPDATE");
            $statement->execute([$id]);
            if ($statement->fetchColumn() === false) {
                throw new RuntimeException('El maestro no existe.');
            }
            $assignments = implode(',', array_map(static fn (string $field): string => "$field=?", array_keys($changes)));
            $statement = $this->pdo->prepare("UPDATE $catalog SET $assignments WHERE id=?");
            $statement->execute([...array_values($changes), $id]);
            $this->audit($catalog, $id, $actorId, 'ACTUALIZAR', array_keys($changes));
        });
    }

    private function validate(string $catalog, array $data, int $actorId): void
    {
        $fields = self::FIELDS[$catalog] ?? throw new InvalidArgumentException('Catálogo no admitido.');
        if ($actorId < 1 || !$data || array_diff(array_keys($data), explode(' ', $fields))) {
            throw new InvalidArgumentException('Se requiere actor positivo y columnas admitidas, sin id.');
        }
        foreach ($data as $field => $value) {
            if (!is_null($value) && !is_string($value) && !is_int($value)) {
                throw new InvalidArgumentException('Usar texto, enteros o null; los decimales deben enviarse como texto.');
            }
            if ($field === 'estado' && !in_array($value, [0, 1, '0', '1'], true)) {
                throw new InvalidArgumentException('Estado debe ser 0 o 1.');
            }
        }
    }

    private function audit(string $catalog, int $id, int $actorId, string $action, array $fields): void
    {
        // Guardamos los nombres de columnas, sin copiar datos personales a la bitácora.
        $statement = $this->pdo->prepare('INSERT INTO auditoria_acciones (usuario_id,modulo,accion,entidad_id,resultado,metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$actorId, 'maestros', $action, $id, 'OK', json_encode(['catalogo' => $catalog, 'campos' => $fields], JSON_THROW_ON_ERROR)]);
    }

    private function atomic(callable $operation): mixed
    {
        $outerTransaction = $this->pdo->inTransaction();
        $savepoint = 'master_' . bin2hex(random_bytes(8));
        if ($outerTransaction) {
            $this->pdo->exec("SAVEPOINT $savepoint");
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($outerTransaction) {
                $this->pdo->exec("RELEASE SAVEPOINT $savepoint");
            } else {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $error) {
            // Nunca confirmamos ni revertimos trabajo ajeno al repositorio.
            if ($this->pdo->inTransaction()) {
                if ($outerTransaction) {
                    $this->pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
                    $this->pdo->exec("RELEASE SAVEPOINT $savepoint");
                } else {
                    $this->pdo->rollBack();
                }
            }
            throw $error;
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Persistence of normalized aggregates. Services own authorization and business validation. */
abstract class OperationalRepository
{
    protected const HEADER = '';
    protected const DETAIL = '';
    protected const PARENT_KEY = '';
    protected const HEADER_FIELDS = [];
    protected const OPTIONAL_FIELDS = [];
    protected const DETAIL_FIELDS = ['producto_id', 'unidad_id', 'cantidad'];
    protected const DATE_FIELD = 'fecha';
    protected const LOCATION_FIELD = 'sucursal_id';

    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function create(array $header, array $details, int $actorId): int
    {
        [$header, $details] = $this->normalize($header, $details);
        self::positiveId($actorId);
        return $this->atomic(function () use ($header, $details, $actorId): int {
            $this->checkReferences($header, $details);
            return $this->insertAggregate($header, $details, $actorId);
        });
    }

    /** Replace all details atomically. Return the new version. */
    public function updateDraft(int $id, int $expectedVersion, array $header, array $details, int $actorId): int
    {
        foreach ([$id, $expectedVersion, $actorId] as $value) {
            self::positiveId($value);
        }
        [$header, $details] = $this->normalize($header, $details);
        return $this->atomic(function () use ($id, $expectedVersion, $header, $details, $actorId): int {
            $current = $this->lockedHeader($id);
            if (!$current || $current['estado_registro'] !== 'BORRADOR' || (int) $current['version'] !== $expectedVersion) {
                throw new RuntimeException('Registro inexistente, fuera de BORRADOR o version desactualizada.');
            }
            $this->checkReferences($header, $details);
            $assignments = implode(',', array_map(static fn(string $field): string => "$field=?", array_keys($header)));
            $sql = 'UPDATE ' . static::HEADER . " SET $assignments,updated_by=?,updated_at=CURRENT_TIMESTAMP,version=version+1 WHERE id=? AND version=? AND estado_registro='BORRADOR'";
            $this->execute('DELETE FROM ' . static::DETAIL . ' WHERE ' . static::PARENT_KEY . '=?', [$id]);
            $this->execute($sql, [...array_values($header), $actorId, $id, $expectedVersion]);
            $this->insertDetails($id, $details);
            return $expectedVersion + 1;
        });
    }

    public function find(int $id): ?array
    {
        self::positiveId($id);
        return $this->atomic(function () use ($id): ?array {
            // Parent lock prevents mixing header/details from different concurrent edits.
            $header = $this->execute('SELECT * FROM ' . static::HEADER . ' WHERE id=? LOCK IN SHARE MODE', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$header) {
                return null;
            }
            $details = $this->execute('SELECT * FROM ' . static::DETAIL . ' WHERE ' . static::PARENT_KEY . '=? ORDER BY id', [$id])->fetchAll(PDO::FETCH_ASSOC);
            return ['header' => $header, 'details' => $details];
        });
    }

    // Fixed SQL source-state predicates guard stale writes; these are not authorization checks.
    public function deleteDraft(int $id, int $expectedVersion, int $actorId): void
    {
        foreach ([$id, $expectedVersion, $actorId] as $value) self::positiveId($value);
        $this->atomic(function () use ($id, $expectedVersion): void {
            $current=$this->lockedHeader($id);
            if(!$current || $current['estado_registro']!=='BORRADOR' || (int)$current['version']!==$expectedVersion){
                throw new RuntimeException('Solo se puede eliminar un borrador con la versión vigente.');
            }
            $statement=$this->execute('DELETE FROM '.static::HEADER." WHERE id=? AND version=? AND estado_registro='BORRADOR'",[$id,$expectedVersion]);
            if($statement->rowCount()!==1) throw new RuntimeException('No se pudo eliminar el borrador.');
        });
    }

    public function markValidated(int $id, int $version, int $actorId): int
    {
        return $this->persistState($id, $version, $actorId, 'BORRADOR', 'VALIDADO', 'validated');
    }

    public function markPublished(int $id, int $version, int $actorId): int
    {
        return $this->persistState($id, $version, $actorId, 'VALIDADO', 'PUBLICADO', 'published');
    }

    public function markObserved(int $id, int $version, int $actorId, string $reason): int
    {
        return $this->persistState($id, $version, $actorId, 'VALIDADO', 'OBSERVADO', 'observed', $reason);
    }

    public function returnToDraft(int $id, int $version, int $actorId): int
    {
        return $this->persistState($id, $version, $actorId, 'OBSERVADO', 'BORRADOR', null);
    }

    public function markCancelled(int $id, int $version, int $actorId, string $reason): int
    {
        return $this->persistState($id, $version, $actorId, 'PUBLICADO', 'ANULADO', 'cancelled', $reason);
    }

    private function persistState(int $id, int $version, int $actorId, string $from, string $to, ?string $event, ?string $reason = null): int
    {
        foreach ([$id, $version, $actorId] as $value) {
            self::positiveId($value);
        }
        $set = 'estado_registro=?,version=version+1,updated_at=CURRENT_TIMESTAMP,updated_by=?';
        $values = [$to, $actorId];
        if ($event !== null) {
            $set .= ",{$event}_at=CURRENT_TIMESTAMP,{$event}_by=?";
            $values[] = $actorId;
        }
        if ($to === 'OBSERVADO' || $to === 'ANULADO') {
            if ($reason === null || trim($reason) === '') {
                throw new InvalidArgumentException('El motivo es obligatorio.');
            }
            $set .= $to === 'OBSERVADO' ? ',observation_reason=?' : ',cancellation_reason=?';
            $values[] = trim($reason);
        }
        $sql = 'UPDATE ' . static::HEADER . " SET $set WHERE id=? AND version=? AND estado_registro=?";
        $statement = $this->execute($sql, [...$values, $id, $version, $from]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Registro inexistente, estado de origen distinto o version desactualizada.');
        }
        return $version + 1;
    }

    protected function normalize(array $header, array $details): array
    {
        $allowed = [...static::HEADER_FIELDS, ...static::OPTIONAL_FIELDS];
        if (array_diff(array_keys($header), $allowed)) {
            throw new InvalidArgumentException('Cabecera con campos no admitidos; enviar IDs normalizados, sin estado ni auditoria.');
        }
        $normalized = [];
        foreach ($allowed as $field) {
            $value = $header[$field] ?? null;
            if ($value === null) {
                if (!in_array($field, static::OPTIONAL_FIELDS, true)) {
                    throw new InvalidArgumentException("Campo obligatorio: $field");
                }
            } elseif (str_ends_with($field, '_id')) {
                $value = self::positiveId($value);
            } elseif ($field === static::DATE_FIELD) {
                $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
                if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
                    throw new InvalidArgumentException("Fecha invalida: $field");
                }
            } elseif ($field === 'numero') {
                if (!is_string($value) || trim($value) === '' || strlen($value) > 25) {
                    throw new InvalidArgumentException('Numero obligatorio (maximo 25 bytes).');
                }
                $value = trim($value);
            }
            $normalized[$field] = $value;
        }
        if (!$details || !array_is_list($details)) {
            throw new InvalidArgumentException('Se requiere una lista de al menos un detalle.');
        }
        $lines = [];
        foreach ($details as $detail) {
            if (!is_array($detail) || array_diff(array_keys($detail), static::DETAIL_FIELDS)) {
                throw new InvalidArgumentException('Detalle con campos no admitidos.');
            }
            $line = [];
            foreach (static::DETAIL_FIELDS as $field) {
                $value = $detail[$field] ?? null;
                if ($field === 'lote_id' && $value === null) {
                    $line[$field] = null;
                } elseif (str_ends_with($field, '_id')) {
                    $line[$field] = self::positiveId($value);
                } elseif ($field === 'cantidad') {
                    $line[$field] = self::decimal($value, 3, static::HEADER === 'stock_cabecera');
                } elseif ($field === 'valor_unitario') {
                    $line[$field] = self::decimal($value ?? 0, 2, true);
                }
            }
            $lines[] = $line;
        }
        return [$normalized, $lines];
    }

    protected static function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || is_bool($value) || is_float($value)) {
            throw new InvalidArgumentException('Identificador/version debe ser un entero positivo.');
        }
        return $id;
    }

    private static function decimal(mixed $value, int $scale, bool $allowZero): string
    {
        // Decimal strings/integers only: never round a binary float silently.
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^\d+(?:\.\d{1,' . $scale . '})?$/D', (string) $value)) {
            throw new InvalidArgumentException('Cantidad/valor debe ser un decimal no negativo con precision admitida.');
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad($fraction, $scale, '0');
        if (strlen($whole) > 14 - $scale || (!$allowZero && $whole === '0' && trim($fraction, '0') === '')) {
            throw new InvalidArgumentException('Cantidad/valor fuera de rango.');
        }
        return $whole . '.' . $fraction;
    }

    protected function checkReferences(array $header, array $details): void
    {
        // Foreign keys enforce existing masters; subclasses check cross-table consistency.
    }

    protected function insertAggregate(array $header, array $details, int $actorId): int
    {
        $header['created_by'] = $actorId;
        $this->insert(static::HEADER, $header);
        $id = (int) $this->pdo->lastInsertId();
        $this->insertDetails($id, $details);
        return $id;
    }

    protected function insertDetails(int $id, array $details): void
    {
        foreach ($details as $detail) {
            $this->insert(static::DETAIL, [static::PARENT_KEY => $id, ...$detail]);
        }
    }

    private function insert(string $table, array $data): void
    {
        $fields = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $this->execute("INSERT INTO $table ($fields) VALUES ($placeholders)", array_values($data));
    }

    protected function lockedHeader(int $id): array|false
    {
        return $this->execute('SELECT * FROM ' . static::HEADER . ' WHERE id=? FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
    }

    protected function execute(string $sql, array $values = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
        return $statement;
    }

    /** Never commit or roll back a transaction owned by the caller Service. */
    protected function atomic(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        $savepoint = 'repo_' . bin2hex(random_bytes(8));
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT $savepoint");
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec("RELEASE SAVEPOINT $savepoint");
            }
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                } else {
                    $this->pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
                    $this->pdo->exec("RELEASE SAVEPOINT $savepoint");
                }
            }
            throw $error;
        }
    }

    protected function listing(string $sql, array $filters, int $limit, int $offset): array
    {
        if ($limit < 1 || $limit > 300 || $offset < 0) {
            throw new InvalidArgumentException('Paginacion invalida: limite entre 1 y 300; offset no negativo.');
        }
        $fields = ['estado_registro' => 'h.estado_registro', 'fecha_desde' => 'h.' . static::DATE_FIELD,
            'fecha_hasta' => 'h.' . static::DATE_FIELD, static::LOCATION_FIELD => 'h.' . static::LOCATION_FIELD];
        if (array_diff(array_keys($filters), array_keys($fields))) {
            throw new InvalidArgumentException('Filtro no admitido.');
        }
        $where = [];
        $values = [];
        foreach ($filters as $key => $value) {
            $operator = $key === 'fecha_desde' ? '>=' : ($key === 'fecha_hasta' ? '<=' : '=');
            $where[] = $fields[$key] . $operator . '?';
            $values[] = $value;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        // Only bounded integers are interpolated; filter values are bound parameters.
        return $this->execute($sql . " ORDER BY h.id DESC LIMIT $limit OFFSET $offset", $values)->fetchAll(PDO::FETCH_ASSOC);
    }
}

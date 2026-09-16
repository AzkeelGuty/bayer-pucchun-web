<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class StockRepository extends OperationalRepository
{
    protected const HEADER = 'stock_cabecera';
    protected const DETAIL = 'stock_detalle';
    protected const PARENT_KEY = 'stock_id';
    protected const HEADER_FIELDS = ['fecha_stock', 'almacen_id', 'idempotency_key'];
    protected const DETAIL_FIELDS = ['producto_id', 'unidad_id', 'lote_id', 'cantidad'];
    protected const DATE_FIELD = 'fecha_stock';
    protected const LOCATION_FIELD = 'almacen_id';

    public function create(array $header, array $details, int $actorId): int
    {
        [$header, $details] = $this->normalize($header, $details);
        if (str_starts_with($header['idempotency_key'], 'legacy-stock-')) {
            throw new InvalidArgumentException('El prefijo legacy-stock- esta reservado para registros migrados.');
        }
        self::positiveId($actorId);
        return $this->atomic(function () use ($header, $details, $actorId): int {
            try {
                // Savepoint contains a failed insert even when the Service owns the transaction.
                return $this->atomic(function () use ($header, $details, $actorId): int {
                    $this->checkReferences($header, $details);
                    return $this->insertAggregate($header, $details, $actorId);
                });
            } catch (PDOException $error) {
                if ((int) ($error->errorInfo[1] ?? 0) !== 1062) {
                    throw $error;
                }
                // Current read sees a concurrent winner, including under REPEATABLE READ.
                $existing = $this->execute('SELECT id,request_hash FROM stock_cabecera WHERE idempotency_key=? FOR UPDATE', [$header['idempotency_key']])->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    throw $error; // A different snapshot/line constraint, not a replay.
                }
                if (!hash_equals($existing['request_hash'], $header['request_hash'])) {
                    throw new RuntimeException('La clave de idempotencia ya fue usada con otro contenido.', 0, $error);
                }
                return (int) $existing['id'];
            }
        });
    }

    protected function normalize(array $header, array $details): array
    {
        [$header, $details] = parent::normalize($header, $details);
        $key = $header['idempotency_key'];
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $key)) {
            throw new InvalidArgumentException('Clave de idempotencia invalida (1-64 caracteres ASCII).');
        }
        $seen = [];
        foreach ($details as $line) {
            $logicalKey = $line['producto_id'] . ':' . ($line['lote_id'] ?? 0) . ':' . $line['unidad_id'];
            if (isset($seen[$logicalKey])) {
                throw new InvalidArgumentException('Producto/lote/unidad duplicado en el snapshot.');
            }
            $seen[$logicalKey] = true;
        }
        $canonicalDetails = $details;
        usort($canonicalDetails, static fn(array $a, array $b): int => [$a['producto_id'], $a['lote_id'] ?? 0, $a['unidad_id']] <=> [$b['producto_id'], $b['lote_id'] ?? 0, $b['unidad_id']]);
        $header['request_hash'] = hash('sha256', json_encode([$header['fecha_stock'], $header['almacen_id'], $canonicalDetails], JSON_THROW_ON_ERROR));
        return [$header, $details];
    }

    protected function insertDetails(int $id, array $details): void
    {
        // Context is obtained from the locked parent, never accepted from a submitted line.
        $header = $this->execute('SELECT fecha_stock,almacen_id FROM stock_cabecera WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC);
        foreach ($details as &$detail) {
            $detail['fecha_stock'] = $header['fecha_stock'];
            $detail['almacen_id'] = $header['almacen_id'];
        }
        unset($detail);
        parent::insertDetails($id, $details);
    }

    protected function checkReferences(array $header, array $details): void
    {
        foreach ($details as $line) {
            if ($line['lote_id'] === null) {
                continue;
            }
            $lot = $this->execute('SELECT producto_id,fecha_vencimiento FROM lotes WHERE id=? LOCK IN SHARE MODE', [$line['lote_id']])->fetch(PDO::FETCH_ASSOC);
            if (!$lot || (int) $lot['producto_id'] !== $line['producto_id']) {
                throw new InvalidArgumentException('Lote inexistente o de otro producto.');
            }
            if ($lot['fecha_vencimiento'] !== null && $lot['fecha_vencimiento'] < $header['fecha_stock']) {
                throw new InvalidArgumentException('Vencimiento anterior a fecha de stock.');
            }
        }
    }

    /** The identity/hash of the initial request is immutable across draft edits. */
    public function updateDraft(int $id, int $expectedVersion, array $header, array $details, int $actorId): int
    {
        return $this->atomic(function () use ($id, $expectedVersion, $header, $details, $actorId): int {
            $current = $this->lockedHeader($id);
            if (!$current || ($header['idempotency_key'] ?? null) !== $current['idempotency_key']) {
                throw new InvalidArgumentException('La clave de idempotencia del snapshot es inmutable.');
            }
            $version = parent::updateDraft($id, $expectedVersion, $header, $details, $actorId);
            $this->execute('UPDATE stock_cabecera SET request_hash=? WHERE id=?', [$current['request_hash'], $id]);
            return $version;
        });
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $id = $this->execute('SELECT id FROM stock_cabecera WHERE idempotency_key=?', [$key])->fetchColumn();
        return $id === false ? null : $this->find((int) $id);
    }

    public function all(array $filters = [], int $limit = 300, int $offset = 0): array
    {
        return $this->listing('SELECT h.id,h.fecha_stock,h.estado_registro,h.version,a.nombre almacen,
            COALESCE(d.items,0) items,COALESCE(d.cantidad,0) cantidad
            FROM stock_cabecera h JOIN almacenes a ON a.id=h.almacen_id
            LEFT JOIN (SELECT stock_id,COUNT(*) items,SUM(cantidad) cantidad FROM stock_detalle GROUP BY stock_id) d ON d.stock_id=h.id', $filters, $limit, $offset);
    }
}

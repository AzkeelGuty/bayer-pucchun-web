<?php
declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Normalized logistics persistence. Services own authorization and transition rules. */
final class LogisticsPreparationRepository
{
    public const NOT_FOUND = 1001;
    public const VERSION_CONFLICT = 1002;
    public const STATE_CONFLICT = 1003;
    public const IDEMPOTENCY_CONFLICT = 1004;
    public const USED_LINE_CONFLICT = 1005;
    public const INVALID_QUANTITY = 2001;
    private const STATES = ['EN_PREPARACION', 'PREPARADA', 'CANCELADA'];
    private const HEADER_FIELDS = ['fecha_preparacion', 'almacen_id', 'cliente_id', 'direccion_destino',
        'departamento_id', 'provincia_id', 'distrito_id', 'observacion', 'origen_sistema', 'origen_documento_ref'];
    private const DETAIL_FIELDS = ['producto_id', 'unidad_id', 'lote_id', 'cantidad_preparada', 'origen_linea_ref'];
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? \db(); }

    /** Key is supplied in header; request_hash, actor, state and audit fields are never accepted. */
    public function create(array $header, array $details, int $actorId): int
    {
        self::positiveId($actorId);
        $key = $header['idempotency_key'] ?? null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $key)) {
            throw new InvalidArgumentException('Clave de idempotencia invalida.');
        }
        unset($header['idempotency_key']);
        $header = $this->normalizeHeader($header);
        $details = $this->normalizeDetails($details, false);
        $canonical = $details;
        usort($canonical, static fn(array $a, array $b): int => strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
        $hash = hash('sha256', json_encode([$header, $canonical], JSON_THROW_ON_ERROR));
        return $this->atomic(function () use ($header, $details, $actorId, $key, $hash): int {
            try {
                // Contain failed insertion inside a savepoint when the caller owns the transaction.
                return $this->atomic(function () use ($header, $details, $actorId, $key, $hash): int {
                    $this->checkGeography($header);
                    $this->insert('logistica_preparaciones_cabecera', $header + [
                        'created_by'=>$actorId, 'idempotency_key'=>$key, 'request_hash'=>$hash]);
                    $id = (int)$this->pdo->lastInsertId();
                    foreach ($details as $line) $this->insert('logistica_preparaciones_detalle', ['preparacion_id'=>$id] + $line);
                    $this->history($id, 1, 'crear', $actorId, null, ['header'=>$header, 'details'=>$details]);
                    return $id;
                });
            } catch (PDOException $error) {
                if ((int)($error->errorInfo[1] ?? 0) !== 1062) throw $error;
                // Only a collision on this header's key is a replay; other UNIQUE failures propagate.
                if (!str_contains((string)($error->errorInfo[2] ?? ''), 'uq_lg_prep_key')) throw $error;
                $existing = $this->execute('SELECT id,created_by,request_hash FROM logistica_preparaciones_cabecera WHERE idempotency_key=? FOR UPDATE', [$key])->fetch(PDO::FETCH_ASSOC);
                if (!$existing) throw $error;
                if ((int)$existing['created_by'] !== $actorId || !hash_equals((string)$existing['request_hash'], $hash)) {
                    throw new RuntimeException('Conflicto de idempotencia.', self::IDEMPOTENCY_CONFLICT);
                }
                return (int)$existing['id'];
            }
        });
    }

    public function find(int $id): ?array
    {
        self::positiveId($id);
        return $this->atomic(function () use ($id): ?array {
            $header = $this->execute('SELECT * FROM logistica_preparaciones_cabecera WHERE id=? LOCK IN SHARE MODE', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$header) return null;
            return ['header'=>$header, 'details'=>$this->execute('SELECT * FROM logistica_preparaciones_detalle WHERE preparacion_id=? ORDER BY id LOCK IN SHARE MODE', [$id])->fetchAll(PDO::FETCH_ASSOC)];
        });
    }

    public function all(array $filters = [], int $limit = 300, int $offset = 0): array
    {
        if ($limit < 1 || $limit > 300 || $offset < 0) throw new InvalidArgumentException('Paginacion invalida.');
        $allowed = ['estado_preparacion', 'almacen_id', 'cliente_id', 'created_by', 'fecha_desde', 'fecha_hasta'];
        self::fields($filters, $allowed);
        $where = []; $values = [];
        foreach ($filters as $field=>$value) {
            if (str_ends_with($field, '_id') || $field === 'created_by') $value = self::positiveId($value);
            elseif ($field === 'estado_preparacion') $value = self::state($value);
            else $value = self::dateTime($value);
            $column = in_array($field, ['fecha_desde','fecha_hasta'], true) ? 'fecha_preparacion' : $field;
            $operator = $field === 'fecha_desde' ? '>=' : ($field === 'fecha_hasta' ? '<=' : '=');
            $where[] = 'h.'.$column.$operator.'?'; $values[] = $value;
        }
        $sql = 'SELECT h.*,COALESCE(d.items,0) items,COALESCE(d.cantidad_preparada,0.000) cantidad_preparada
            FROM logistica_preparaciones_cabecera h LEFT JOIN
            (SELECT preparacion_id,COUNT(*) items,SUM(cantidad_preparada) cantidad_preparada FROM logistica_preparaciones_detalle GROUP BY preparacion_id) d ON d.preparacion_id=h.id';
        if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
        return $this->execute($sql." ORDER BY h.id DESC LIMIT $limit OFFSET $offset", $values)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Full replacement payload: existing lines carry id; omitted lines are deleted if never used. */
    public function update(int $id, int $expectedVersion, array $header, array $details, int $actorId, ?string $reason = null): int
    {
        foreach ([$id,$expectedVersion,$actorId] as $value) self::positiveId($value);
        $header = $this->normalizeHeader($header);
        $details = $this->normalizeDetails($details, true);
        $reason = self::text($reason, null, true);
        return $this->atomic(function () use ($id,$expectedVersion,$header,$details,$actorId,$reason): int {
            $current = $this->locked($id, $expectedVersion);
            $previous = $this->execute('SELECT * FROM logistica_preparaciones_detalle WHERE preparacion_id=? ORDER BY id FOR UPDATE', [$id])->fetchAll(PDO::FETCH_ASSOC);
            $indexed = array_column($previous, null, 'id');
            $usage = $this->usage($id);
            $keep = [];
            foreach ($details as $line) {
                if (!isset($line['id'])) continue;
                $lineId = $line['id'];
                if (!isset($indexed[$lineId])) throw new InvalidArgumentException('Linea inexistente o de otra preparacion.');
                $keep[$lineId] = true;
                if (($usage[$lineId]['used'] ?? false)) {
                    foreach (['producto_id','unidad_id','lote_id'] as $field) {
                        if (($indexed[$lineId][$field] === null ? null : (int)$indexed[$lineId][$field]) !== $line[$field]) {
                            throw new RuntimeException('Identidad de linea utilizada es inmutable.', self::USED_LINE_CONFLICT);
                        }
                    }
                    if (self::compareDecimal($line['cantidad_preparada'], $usage[$lineId]['assigned']) < 0) {
                        throw new RuntimeException('Cantidad inferior a lo comprometido.', self::USED_LINE_CONFLICT);
                    }
                }
            }
            foreach ($indexed as $lineId=>$line) {
                if (!isset($keep[$lineId]) && ($usage[$lineId]['used'] ?? false)) throw new RuntimeException('No se puede eliminar una linea utilizada.', self::USED_LINE_CONFLICT);
            }
            $this->checkGeography($header);
            $sets = implode(',', array_map(static fn(string $field): string => $field.'=?', array_keys($header)));
            $st = $this->execute('UPDATE logistica_preparaciones_cabecera SET '.$sets.',version=version+1,updated_at=CURRENT_TIMESTAMP,updated_by=? WHERE id=? AND version=?', [...array_values($header),$actorId,$id,$expectedVersion]);
            if ($st->rowCount() !== 1) throw new RuntimeException('Conflicto de version.', self::VERSION_CONFLICT);
            foreach ($indexed as $lineId=>$line) if (!isset($keep[$lineId])) $this->execute('DELETE FROM logistica_preparaciones_detalle WHERE id=? AND preparacion_id=?', [$lineId,$id]);
            foreach ($details as $line) {
                $lineId = $line['id'] ?? null; unset($line['id']);
                if ($lineId === null) $this->insert('logistica_preparaciones_detalle', ['preparacion_id'=>$id] + $line);
                else {
                    $sets = implode(',', array_map(static fn(string $field): string => $field.'=?', array_keys($line)));
                    $this->execute('UPDATE logistica_preparaciones_detalle SET '.$sets.' WHERE id=? AND preparacion_id=?', [...array_values($line),$lineId,$id]);
                }
            }
            $this->history($id, $expectedVersion+1, 'actualizar', $actorId, $reason, [
                'before'=>['header'=>array_intersect_key($current,$header),'details'=>$previous],
                'after'=>['header'=>$header,'details'=>$this->execute('SELECT * FROM logistica_preparaciones_detalle WHERE preparacion_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC)]]);
            return $expectedVersion+1;
        });
    }

    /** No transition map here: Services approve from/to and any required reason. */
    public function changeState(int $id, int $expectedVersion, string $expectedState, string $targetState, int $actorId, ?string $reason = null): int
    {
        foreach ([$id,$expectedVersion,$actorId] as $value) self::positiveId($value);
        self::state($expectedState); self::state($targetState); $reason = self::text($reason, null, true);
        return $this->atomic(function () use ($id,$expectedVersion,$expectedState,$targetState,$actorId,$reason): int {
            $current = $this->locked($id, $expectedVersion);
            if ($current['estado_preparacion'] !== $expectedState) throw new RuntimeException('Conflicto de estado esperado.', self::STATE_CONFLICT);
            $st = $this->execute('UPDATE logistica_preparaciones_cabecera SET estado_preparacion=?,version=version+1,updated_at=CURRENT_TIMESTAMP,updated_by=? WHERE id=? AND version=? AND estado_preparacion=?', [$targetState,$actorId,$id,$expectedVersion,$expectedState]);
            if ($st->rowCount() !== 1) throw new RuntimeException('Conflicto de version.', self::VERSION_CONFLICT);
            $this->history($id,$expectedVersion+1,'cambiar_estado',$actorId,$reason,['estado_anterior'=>$expectedState,'estado_nuevo'=>$targetState]);
            return $expectedVersion+1;
        });
    }

    /** Logistics pending quantities only. Dispatch writers must lock preparation first. */
    public function dispatchableDetails(int $id): array
    {
        self::positiveId($id);
        return $this->atomic(function () use ($id): array {
            $this->locked($id);
            $lines = $this->execute('SELECT * FROM logistica_preparaciones_detalle WHERE preparacion_id=? ORDER BY id FOR UPDATE',[$id])->fetchAll(PDO::FETCH_ASSOC);
            $usage = $this->usage($id);
            foreach ($lines as &$line) {
                $assigned = $usage[(int)$line['id']]['assigned'] ?? '0.000';
                $line['cantidad_asignada_a_despachos_vigentes'] = $assigned;
                $line['cantidad_pendiente'] = (string)$this->execute('SELECT CAST(? AS DECIMAL(65,3))-CAST(? AS DECIMAL(65,3))',[$line['cantidad_preparada'],$assigned])->fetchColumn();
            }
            unset($line);
            return $lines;
        });
    }

    private function usage(int $id): array
    {
        // Current locking reads; all writers must lock preparation before dependent rows.
        $dispatches = $this->execute('SELECT id,estado_fisico FROM logistica_despachos_cabecera WHERE preparacion_id=? ORDER BY id FOR UPDATE',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $usage=[];
        foreach ($dispatches as $dispatch) {
            $lines=$this->execute('SELECT preparacion_detalle_id,cantidad_despachada FROM logistica_despachos_detalle WHERE despacho_id=? ORDER BY id FOR UPDATE',[$dispatch['id']])->fetchAll(PDO::FETCH_ASSOC);
            foreach($lines as $line) {
                $lineId=(int)$line['preparacion_detalle_id'];
                $usage[$lineId] ??= ['used'=>true,'assigned'=>'0.000'];
                if($dispatch['estado_fisico'] !== 'CANCELADO') {
                    // Decimal arithmetic belongs to SQL, never PHP floating point.
                    $usage[$lineId]['assigned']=(string)$this->execute('SELECT CAST(? AS DECIMAL(65,3))+CAST(? AS DECIMAL(65,3))',[$usage[$lineId]['assigned'],$line['cantidad_despachada']])->fetchColumn();
                }
            }
        }
        return $usage;
    }

    private function locked(int $id, ?int $version = null): array
    {
        $header=$this->execute('SELECT * FROM logistica_preparaciones_cabecera WHERE id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$header) throw new RuntimeException('Preparacion inexistente.',self::NOT_FOUND);
        if($version !== null && (int)$header['version'] !== $version) throw new RuntimeException('Conflicto de version.',self::VERSION_CONFLICT);
        return $header;
    }

    private function normalizeHeader(array $header): array
    {
        self::fields($header,self::HEADER_FIELDS);
        $out=['fecha_preparacion'=>self::dateTime($header['fecha_preparacion']??null),
            'almacen_id'=>self::positiveId($header['almacen_id']??null),
            'cliente_id'=>isset($header['cliente_id'])?self::positiveId($header['cliente_id']):null,
            'direccion_destino'=>self::text($header['direccion_destino']??null,255,false)];
        foreach(['departamento_id','provincia_id','distrito_id'] as $field) $out[$field]=isset($header[$field])?self::positiveId($header[$field]):null;
        foreach(['observacion'=>null,'origen_sistema'=>40,'origen_documento_ref'=>100] as $field=>$max) $out[$field]=self::text($header[$field]??null,$max,true);
        $geo=[$out['departamento_id'],$out['provincia_id'],$out['distrito_id']];
        if($geo !== [null,null,null] && in_array(null,$geo,true)) throw new InvalidArgumentException('Ubigeo incompleto.');
        if(($out['origen_sistema']===null)!==($out['origen_documento_ref']===null)) throw new InvalidArgumentException('Referencia de origen incompleta.');
        return $out;
    }

    private function normalizeDetails(array $details, bool $editing): array
    {
        if(!$details || !array_is_list($details)) throw new InvalidArgumentException('Se requiere lista de detalles.');
        $out=[]; $ids=[];
        foreach($details as $line) {
            if(!is_array($line)) throw new InvalidArgumentException('Detalle invalido.');
            self::fields($line,$editing?[...self::DETAIL_FIELDS,'id']:self::DETAIL_FIELDS);
            $item=[];
            if($editing && isset($line['id'])) {
                $id=self::positiveId($line['id']);
                if(isset($ids[$id])) throw new InvalidArgumentException('ID de linea duplicado.');
                $ids[$id]=true; $item['id']=$id;
            }
            $item += ['producto_id'=>self::positiveId($line['producto_id']??null), 'unidad_id'=>self::positiveId($line['unidad_id']??null),
                'lote_id'=>isset($line['lote_id'])?self::positiveId($line['lote_id']):null,
                'cantidad_preparada'=>self::decimal($line['cantidad_preparada']??null),
                'origen_linea_ref'=>self::text($line['origen_linea_ref']??null,100,true)];
            $out[]=$item;
        }
        return $out;
    }

    private static function decimal(mixed $value): string
    {
        if((!is_string($value)&&!is_int($value)) || !preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/D',(string)$value)) throw new InvalidArgumentException('Cantidad decimal invalida.',self::INVALID_QUANTITY);
        [$whole,$fraction]=array_pad(explode('.',(string)$value,2),2,''); $whole=ltrim($whole,'0')?:'0';
        if(strlen($whole)>11) throw new InvalidArgumentException('Cantidad fuera de DECIMAL(14,3).',self::INVALID_QUANTITY);
        return $whole.'.'.str_pad($fraction,3,'0');
    }

    private static function compareDecimal(string $a, string $b): int
    {
        $a=ltrim(str_replace('.','',$a),'0')?:'0'; $b=ltrim(str_replace('.','',$b),'0')?:'0';
        return strlen($a)<=>strlen($b) ?: strcmp($a,$b);
    }

    private static function positiveId(mixed $value): int
    {
        if(is_bool($value)||is_float($value)||(!is_int($value)&&!is_string($value))) throw new InvalidArgumentException('ID/version invalido.');
        $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false) throw new InvalidArgumentException('ID/version invalido.');
        return $id;
    }

    private static function dateTime(mixed $value): string
    {
        $date=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value):false;
        if(!$date || $date->format('Y-m-d H:i:s')!==$value || $value<'1000-01-01 00:00:00') throw new InvalidArgumentException('Fecha invalida.');
        return $value;
    }

    private static function text(mixed $value, ?int $max, bool $nullable): ?string
    {
        if($value===null && $nullable) return null;
        if(!is_string($value)||preg_match('//u',$value)!==1) throw new InvalidArgumentException('Texto invalido.');
        $value=trim($value);
        if($value==='') { if($nullable) return null; throw new InvalidArgumentException('Texto obligatorio.'); }
        $length=preg_match_all('/./us',$value);
        if(($max!==null && $length>$max) || ($max===null && strlen($value)>65535)) throw new InvalidArgumentException('Texto fuera de rango.');
        return $value;
    }

    private static function fields(array $data, array $allowed): void
    {
        if(array_diff(array_keys($data),$allowed)) throw new InvalidArgumentException('Campos no admitidos.');
    }

    private static function state(mixed $state): string
    {
        if(!is_string($state)||!in_array($state,self::STATES,true)) throw new InvalidArgumentException('Estado invalido.');
        return $state;
    }

    private function checkGeography(array $header): void
    {
        if($header['distrito_id']===null) return;
        $exists=$this->execute('SELECT d.id FROM distritos d JOIN provincias p ON p.id=d.provincia_id WHERE d.id=? AND p.id=? AND p.departamento_id=? LOCK IN SHARE MODE',[$header['distrito_id'],$header['provincia_id'],$header['departamento_id']])->fetchColumn();
        if($exists===false) throw new InvalidArgumentException('Ubigeo inconsistente.');
    }

    private function history(int $id, int $version, string $action, int $actor, ?string $reason, array $metadata): void
    {
        $this->insert('logistica_historial',['preparacion_id'=>$id,'version'=>$version,'accion'=>$action,'usuario_id'=>$actor,'motivo'=>$reason,'metadata_json'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    private function insert(string $table, array $data): void
    {
        // Internal fixed table/field allowlists only; values are always bound.
        $this->execute('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
    }

    private function execute(string $sql, array $values = []): \PDOStatement
    {
        $st=$this->pdo->prepare($sql); $st->execute($values); return $st;
    }

    private function atomic(callable $operation): mixed
    {
        $owns=!$this->pdo->inTransaction(); $savepoint='repo_'.bin2hex(random_bytes(8));
        if($owns) $this->pdo->beginTransaction(); else $this->pdo->exec('SAVEPOINT '.$savepoint);
        try {
            $result=$operation();
            if($owns) $this->pdo->commit(); else $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            return $result;
        } catch(Throwable $error) {
            if($this->pdo->inTransaction()) {
                if($owns) $this->pdo->rollBack();
                else { $this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint); $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint); }
            }
            throw $error;
        }
    }
}

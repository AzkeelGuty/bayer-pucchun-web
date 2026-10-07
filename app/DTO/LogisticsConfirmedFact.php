<?php
declare(strict_types=1);

namespace App\DTO;

use App\Exceptions\LogisticsApplicationException;
use App\Exceptions\LogisticsErrorCode;
use App\Support\LogisticsQuantity;
use Throwable;

/** Validates the legacy Inventory confirmation array and its exact expected scope. */
final readonly class LogisticsConfirmedFact
{
    private function __construct(public array $data) {}

    /** @param array<string,mixed> $expectedScope subset of fact keys that must match exactly */
    public static function fromProvider(array $fact, array $expectedScope): self
    {
        $required = ['sistema', 'id', 'confirmado', 'autorizado', 'tipo', 'direccion', 'finalidad',
            'sucursal_id', 'despacho_id', 'incidencia_id', 'despacho_detalle_id', 'almacen_id',
            'producto_id', 'unidad_id', 'lote_id', 'cantidad', 'evidencia'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $fact)) {
                throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Inventory fact is missing required field: ' . $key);
            }
        }
        if ($fact['confirmado'] !== true || $fact['autorizado'] !== true
            || !is_array($fact['evidencia']) || $fact['evidencia'] === []
            || !is_string($fact['sistema']) || $fact['sistema'] === ''
            || !is_string($fact['id']) || $fact['id'] === ''
            || !in_array($fact['tipo'], ['RETORNO_CONFIRMADO', 'PERDIDA_CONFIRMADA', 'DISPOSICION_FINAL_CONFIRMADA'], true)
            || !in_array($fact['direccion'], ['ENTRADA', 'SIN_NUEVO_EGRESO'], true)
            || !in_array($fact['finalidad'], ['RETORNO', 'PERDIDA', 'DISPOSICION_FINAL'], true)) {
            throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Inventory fact must be confirmed, authorized, and evidenced.');
        }
        $expectedPurpose = match ($fact['tipo']) {
            'RETORNO_CONFIRMADO' => ['ENTRADA', 'RETORNO'],
            'PERDIDA_CONFIRMADA' => ['SIN_NUEVO_EGRESO', 'PERDIDA'],
            'DISPOSICION_FINAL_CONFIRMADA' => ['SIN_NUEVO_EGRESO', 'DISPOSICION_FINAL'],
        };
        if ([$fact['direccion'], $fact['finalidad']] !== $expectedPurpose) {
            throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Inventory fact direction and purpose do not match its resolution type.');
        }
        if ($fact['finalidad'] !== 'RETORNO'
            && (!isset($fact['autorizacion_ref']) || !is_string($fact['autorizacion_ref']) || trim($fact['autorizacion_ref']) === '')) {
            throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Loss or final disposition requires an authorization reference.');
        }
        $fact['autorizacion_ref'] ??= null;
        try {
            $fact['cantidad'] = LogisticsQuantity::normalize($fact['cantidad']);
        } catch (Throwable $error) {
            throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Inventory fact has invalid quantity.', $error);
        }
        foreach ($expectedScope as $key => $expected) {
            if ($key === 'cantidad') {
                try { $expected = LogisticsQuantity::normalize($expected); }
                catch (Throwable $error) {
                    throw new LogisticsApplicationException(LogisticsErrorCode::INVALID_INPUT, 'Expected fact quantity is invalid.', $error);
                }
            }
            if (!array_key_exists($key, $fact) || !self::sameValue($key, $fact[$key], $expected)) {
                throw new LogisticsApplicationException(LogisticsErrorCode::REFERENCE_CONFLICT, 'Inventory fact does not match expected scope: ' . $key);
            }
        }
        return new self($fact);
    }

    private static function sameValue(string $key, mixed $actual, mixed $expected): bool
    {
        if ($key === 'cantidad') return $actual === $expected;
        if (in_array($key, ['sucursal_id', 'despacho_id', 'incidencia_id', 'despacho_detalle_id',
            'almacen_id', 'producto_id', 'unidad_id', 'lote_id'], true)) {
            if ($actual === null || $expected === null) return $actual === $expected;
            if ((!is_int($actual) && !(is_string($actual) && preg_match('/^[0-9]+$/D', $actual)))
                || (!is_int($expected) && !(is_string($expected) && preg_match('/^[0-9]+$/D', $expected)))) return false;
            return (int) $actual === (int) $expected;
        }
        return $actual === $expected;
    }
}

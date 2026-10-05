<?php
declare(strict_types=1);

namespace App\Support;

use App\Exceptions\LogisticsPersistenceException;

/** Exact DECIMAL(14,3) arithmetic, independent of locale, floats and BCMath. */
final class LogisticsQuantity
{
    public const MAX_MILLIS = 99999999999999;

    public static function normalize(mixed $value, bool $allowZero = false): string
    {
        return self::fromMillis(self::toMillis($value, $allowZero));
    }

    public static function toMillis(mixed $value, bool $allowZero = false): int
    {
        if (PHP_INT_SIZE < 8) {
            throw new LogisticsPersistenceException('INVALID_INPUT', 'Las cantidades logísticas requieren PHP de 64 bits.');
        }
        if (!is_int($value) && !is_string($value)) {
            throw new LogisticsPersistenceException('INVALID_INPUT', 'La cantidad debe ser un entero o una cadena decimal exacta.');
        }
        $decimal = (string) $value;
        if (!preg_match('/\A([0-9]+)(?:\.([0-9]{1,3}))?\z/D', $decimal, $matches)) {
            throw new LogisticsPersistenceException('INVALID_INPUT', 'Cantidad inválida: utilice hasta tres decimales, sin redondeo.');
        }
        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 11) {
            throw new LogisticsPersistenceException('INVALID_INPUT', 'Cantidad fuera del rango DECIMAL(14,3).');
        }
        $fraction = str_pad($matches[2] ?? '', 3, '0');
        $millis = (int) $whole * 1000 + (int) $fraction;
        if ($millis > self::MAX_MILLIS || (!$allowZero && $millis === 0)) {
            throw new LogisticsPersistenceException('INVALID_INPUT', 'La cantidad debe ser positiva y respetar DECIMAL(14,3).');
        }
        return $millis;
    }

    public static function fromMillis(int $millis): string
    {
        if ($millis < 0 || $millis > self::MAX_MILLIS) {
            throw new LogisticsPersistenceException('INVALID_INPUT', 'Cantidad fuera del rango DECIMAL(14,3).');
        }
        return intdiv($millis, 1000) . '.' . str_pad((string) ($millis % 1000), 3, '0', STR_PAD_LEFT);
    }
}

<?php
declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

/** Criterios inmutables compartidos por consultas; no lee variables HTTP. */
final class DataQuery
{
    public readonly string $dataset;
    public readonly ?string $from;
    public readonly ?string $to;
    public readonly ?int $sucursal;
    public readonly ?int $almacen;
    public readonly ?int $producto;
    public readonly int $page;
    public readonly int $perPage;
    public readonly string $sort;
    public readonly string $direction;

    public function __construct(array $input)
    {
        if (array_diff(array_keys($input), ['dataset','from','to','sucursal','almacen','producto','page','per_page','sort','direction'])) {
            throw new InvalidArgumentException('Parámetro de consulta no admitido.');
        }
        $dataset = $input['dataset'] ?? null;
        $this->dataset = match ($dataset) {
            'sales', 'documents' => 'sales',
            'shipments', 'guides' => 'shipments',
            'inventory', 'stock' => 'inventory',
            default => throw new InvalidArgumentException('Dataset no admitido.'),
        };
        $this->from = self::date($input['from'] ?? null);
        $this->to = self::date($input['to'] ?? null);
        if ($this->from !== null && $this->to !== null && $this->from > $this->to) {
            throw new InvalidArgumentException('La fecha inicial supera la final.');
        }
        foreach (['sucursal','almacen','producto'] as $field) {
            $value = $input[$field] ?? null;
            $this->$field = $value === null || $value === '' ? null : self::integer($value);
        }
        if ($this->almacen !== null && $this->dataset !== 'inventory') {
            throw new InvalidArgumentException('El filtro almacén solo corresponde a stock.');
        }
        $this->page = self::integer($input['page'] ?? 1);
        $this->perPage = self::integer($input['per_page'] ?? 50, 100);
        if ($this->page - 1 > intdiv(PHP_INT_MAX, $this->perPage)) {
            throw new InvalidArgumentException('Desplazamiento fuera de rango.');
        }
        $sort = $input['sort'] ?? 'date';
        $allowed = ['date','materialId','quantity', $this->dataset === 'inventory' ? 'warehouseId' : 'documentNumber'];
        if (!is_string($sort) || !in_array($sort, $allowed, true)) {
            throw new InvalidArgumentException('Orden no admitido para el dataset.');
        }
        $this->sort = $sort;
        $direction = $input['direction'] ?? 'desc';
        if (!is_string($direction) || !in_array(strtolower($direction), ['asc','desc'], true)) {
            throw new InvalidArgumentException('La dirección debe ser asc o desc.');
        }
        $this->direction = strtoupper($direction);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    private static function integer(mixed $value, int $max = PHP_INT_MAX): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>$max]]) === false) {
            throw new InvalidArgumentException('Se requiere un entero positivo dentro de rango.');
        }
        return (int) $value;
    }

    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
            throw new InvalidArgumentException('Fecha esperada: AAAA-MM-DD.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
            throw new InvalidArgumentException('Fecha inválida.');
        }
        return $value;
    }
}

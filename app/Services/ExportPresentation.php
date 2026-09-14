<?php
declare(strict_types=1);

namespace App\Services;

final class ExportPresentation
{
    private const LABELS = [
        'dealerId' => 'RUC empresa',
        'dealerName' => 'Empresa',
        'documentTypeId' => 'Cód. tipo doc.',
        'documentType' => 'Tipo de documento',
        'documentNumber' => 'N.º documento',
        'documentDate' => 'Fecha documento',
        'salesId' => 'Cód. vendedor',
        'salesName' => 'Vendedor',
        'branchId' => 'Cód. sucursal',
        'branchName' => 'Sucursal',
        'customerId' => 'Documento cliente',
        'customerName' => 'Cliente',
        'materialId' => 'Cód. producto',
        'materialName' => 'Producto',
        'measureUnit' => 'Unidad',
        'quantity' => 'Cantidad',
        'unitValue' => 'Valor unitario',
        'province' => 'Provincia',
        'department' => 'Departamento',
        'district' => 'Distrito',
        'stockDate' => 'Fecha de stock',
        'warehouseId' => 'Cód. almacén',
        'warehouseName' => 'Almacén',
        'batch' => 'Lote',
        'expirationDate' => 'Vencimiento',
    ];

    public static function datasetTitle(string $type): string
    {
        return match ($type) {
            'documents', 'sales' => 'Documentos',
            'guides', 'shipments' => 'Guías de remisión',
            'stock', 'inventory' => 'Stock',
            default => ucfirst($type),
        };
    }

    public static function fieldLabel(string $key): string
    {
        if (isset(self::LABELS[$key])) return self::LABELS[$key];
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    public static function keys(array $rows): array
    {
        return $rows ? array_keys($rows[0]) : [];
    }

    public static function labels(array $rows): array
    {
        return array_map([self::class, 'fieldLabel'], self::keys($rows));
    }

    public static function displayValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') return '—';
        if ($key === 'quantity') {
            return number_format((float)$value, 3, '.', ',');
        }
        if ($key === 'unitValue') {
            return number_format((float)$value, 2, '.', ',');
        }
        return (string)$value;
    }

    public static function filtersLabel(array $filters): string
    {
        if (!$filters) return 'Sin filtros adicionales';
        $labels = [
            'from' => 'Desde',
            'to' => 'Hasta',
            'branch' => 'Sucursal',
            'q' => 'Búsqueda',
        ];
        $parts = [];
        foreach ($filters as $key => $value) {
            $parts[] = ($labels[$key] ?? self::fieldLabel((string)$key)) . ': ' . (string)$value;
        }
        return implode(' · ', $parts);
    }

    public static function metadata(string $type, array $filters, array $rows, array $user, ?string $generatedAt = null): array
    {
        $brand = \branding();
        return [
            'system' => $brand['system_name'] . ' Data Hub',
            'partner' => $brand['partner_name'],
            'title' => 'Reporte de ' . self::datasetTitle($type),
            'dataset' => self::datasetTitle($type),
            'generated_by' => (string)($user['nombre'] ?? $user['email'] ?? 'Usuario autorizado'),
            'generated_at' => $generatedAt ?: date('Y-m-d H:i:s'),
            'record_count' => count($rows),
            'status' => 'Información publicada',
            'filters' => self::filtersLabel($filters),
            'primary_color' => ltrim((string)($brand['primary_color'] ?? '#075B9F'), '#'),
            'accent_color' => ltrim((string)($brand['accent_color'] ?? '#168C5B'), '#'),
        ];
    }
}

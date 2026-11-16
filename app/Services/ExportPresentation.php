<?php
declare(strict_types=1);

namespace App\Services;

final class ExportPresentation
{
    private const LABELS = [
        'source' => 'Origen',
        'stockStatus' => 'Estado del stock',
        'customerDocumentType' => 'Tipo documento cliente',
        'sourceDocumentNumber' => 'Documento de origen',
        'sourceDocumentId' => 'ID documento origen',
        'sunatStatus' => 'Estado SUNAT',
        'paymentMethod' => 'Medio de pago',
        'lineTotal' => 'Importe de línea (S/)',
        'dealerId' => 'RUC empresa',
        'dealerName' => 'Empresa',
        'documentTypeId' => 'Cód. tipo doc.',
        'documentType' => 'Tipo de documento',
        'documentStatus' => 'Estado del comprobante',
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
        'quantity' => 'Cantidad de productos',
        'unitValue' => 'Valor unitario',
        'province' => 'Provincia',
        'department' => 'Departamento',
        'district' => 'Distrito',
        'stockDate' => 'Fecha / período de inventario',
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
            return \format_quantity($value);
        }
        if (in_array($key,['unitValue','lineTotal'],true)) {
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
            'document_status' => 'Estado del comprobante',
        ];
        $parts = [];
        foreach ($filters as $key => $value) {
            $parts[] = ($labels[$key] ?? self::fieldLabel((string)$key)) . ': ' . (string)$value;
        }
        return implode(' · ', $parts);
    }


    public static function pdfKeys(string $type,array $rows): array
    {
        $available = array_flip(self::keys($rows));
        $preferred = match ($type) {
            'documents', 'sales' => ['documentNumber','documentDate','documentStatus','customerDocumentType','customerId','customerName','salesName','branchName','materialId','materialName','measureUnit','quantity','unitValue','lineTotal','district','province','department'],
            'guides', 'shipments' => ['documentNumber','documentDate','sourceDocumentNumber','customerDocumentType','customerId','customerName','salesName','branchName','materialId','materialName','measureUnit','quantity','district','province','department'],
            'stock', 'inventory' => ['source','materialId','materialName','warehouseName','measureUnit','quantity','stockDate','stockStatus'],
            default => self::keys($rows),
        };
        return array_values(array_filter($preferred,static fn(string $key): bool => isset($available[$key])));
    }

    public static function metadata(string $type, array $filters, array $rows, array $user, ?string $generatedAt = null): array
    {
        $brand = \branding();
        $logoPath = static function (mixed $relative): ?string {
            if (!is_string($relative) || trim($relative) === '') return null;
            // Los logos se guardan en la raíz pública real. En cPanel puede ser
            // public_html (BAYER_PUBLIC_ROOT), no necesariamente <proyecto>/public.
            $absolute = \public_path(ltrim($relative, '/'));
            return is_file($absolute) ? $absolute : null;
        };

        return [
            'type' => $type,
            'system' => $brand['system_name'] . ' Data Hub',
            'partner' => $brand['partner_name'],
            'title' => 'Reporte de ' . self::datasetTitle($type),
            'dataset' => self::datasetTitle($type),
            'generated_by' => (string)($user['nombre'] ?? $user['email'] ?? 'Usuario autorizado'),
            'generated_at' => $generatedAt ?: date('Y-m-d H:i:s'),
            'record_count' => count($rows),
            'status' => $type==='stock' ? 'Pucchún publicado / inventario informado Bayer' : 'Información publicada',
            'filters' => self::filtersLabel($filters),
            'primary_color' => ltrim((string)($brand['primary_color'] ?? '#075B9F'), '#'),
            'accent_color' => ltrim((string)($brand['accent_color'] ?? '#168C5B'), '#'),
            'logo_primary_path' => $logoPath($brand['logo_primary'] ?? null),
            'logo_partner_path' => $logoPath($brand['logo_partner'] ?? null),
        ];
    }
}

<?php
declare(strict_types=1);
namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Repositories\Masters\MasterDataRepository;

final class OperationalValidator
{
    public function __construct(private MasterDataRepository $masters) {}

    /** Adapt integrated flat HTML forms without dropping unknown fields or coercing values. */
    public static function formPayload(string $module, array $input, bool $editing): array
    {
        if ($editing) unset($input['id'],$input['version']);
        if ($module==='documents' || array_key_exists('header',$input) || array_key_exists('details',$input)) return $input;
        $fields=$module==='stock' ? ['fecha_stock','almacen_id','idempotency_key']
            : ['numero','fecha','cliente_id','vendedor_id','sucursal_id','departamento_id','provincia_id','distrito_id'];
        $header=array_intersect_key($input,array_flip($fields));
        $extra=array_diff_key($input,array_flip([...$fields,'detalle','_csrf']));
        return ['header'=>$header,'details'=>$input['detalle'] ?? null]+$extra;
    }

    public static function id(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value))
            || filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) {
            throw new ValidationException(['id'=>'Se requiere un entero positivo.']);
        }
        return (int) $value;
    }

    public static function date(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value && $value >= '1000-01-01';
    }

    private function decimal(mixed $value, int $scale, bool $zero): bool
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+(?:\.[0-9]{1,'.$scale.'})?$/D', (string)$value)) return false;
        [$whole, $fraction] = array_pad(explode('.', (string)$value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        return strlen($whole) <= 14-$scale && ($zero || $whole !== '0' || trim($fraction, '0') !== '');
    }

    private function reference(string $catalog, mixed $value, string $path, array &$errors): ?array
    {
        try { $id = self::id($value); }
        catch (ValidationException) { $errors[$path] = 'Seleccione un identificador válido.'; return null; }
        $row = $this->masters->find($catalog, $id);
        if (!$row || (isset($row['estado']) && (int)$row['estado'] !== 1)) {
            $errors[$path] = 'El maestro no existe o está inactivo.';
            return null;
        }
        return $row;
    }

    public function capture(string $module, array $input, bool $editing = false): array
    {
        $errors = [];
        if (array_diff(array_keys($input), ['header','details','_csrf'])) $errors['payload'] = 'Campos no admitidos; no envíe estado ni actor.';
        $h = $input['header'] ?? null; $details = $input['details'] ?? null;
        if (!is_array($h)) $errors['header'] = 'Se requiere una cabecera.';
        if (!is_array($details) || !$details || count($details)>200) $errors['details'] = 'Envíe entre 1 y 200 detalles.';
        if ($errors) throw new ValidationException($errors);
        // HTML can remove rows without renumbering. Accept numeric indexes only, then compact.
        foreach (array_keys($details) as $key) if (!is_int($key) || $key < 0) $errors['details'] = 'Índices de detalle inválidos.';
        $details = array_values($details);
        $fields = $module === 'stock' ? ['fecha_stock','almacen_id','idempotency_key'] : ['numero','fecha','cliente_id','vendedor_id','sucursal_id'];
        if ($module === 'documents') $fields[] = 'tipo_documento_id';
        if ($module === 'guides') $fields = [...$fields,'departamento_id','provincia_id','distrito_id'];
        if (array_diff(array_keys($h), $fields)) $errors['header'] = 'Cabecera con campos no admitidos.';
        $date = $module === 'stock' ? 'fecha_stock' : 'fecha';
        if (!self::date($h[$date] ?? null)) $errors['header.'.$date] = 'Fecha inválida; utilice AAAA-MM-DD.';
        if ($module !== 'stock') {
            if (!is_string($h['numero'] ?? null) || trim($h['numero']) === '' || strlen($h['numero'])>25) $errors['header.numero'] = 'Número obligatorio, máximo 25 bytes.';
            else $h['numero'] = trim($h['numero']);
        } elseif (!is_string($h['idempotency_key'] ?? null) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $h['idempotency_key']) || (!$editing && str_starts_with($h['idempotency_key'], 'legacy-stock-'))) {
            $errors['header.idempotency_key'] = 'Clave de solicitud inválida o reservada.';
        }
        $refs = $module === 'stock' ? ['almacen_id'=>'almacenes'] : ['cliente_id'=>'clientes','vendedor_id'=>'vendedores','sucursal_id'=>'sucursales'];
        if ($module === 'documents') $refs['tipo_documento_id'] = 'tipos_documento';
        $rows = [];
        foreach ($refs as $field=>$catalog) {
            $rows[$field] = $this->reference($catalog, $h[$field] ?? null, 'header.'.$field, $errors);
            if ($rows[$field]) $h[$field] = (int)$rows[$field]['id'];
        }
        $branch = $rows['sucursal_id'] ?? null;
        if ($module === 'stock' && ($rows['almacen_id'] ?? null)) $branch = $this->reference('sucursales', $rows['almacen_id']['sucursal_id'], 'header.almacen_id', $errors);
        if ($branch) $this->reference('empresas', $branch['empresa_id'], 'header.'.($module === 'stock' ? 'almacen_id' : 'sucursal_id'), $errors);
        if ($module === 'guides') {
            $geo = [];
            foreach (['departamento_id'=>'departamentos','provincia_id'=>'provincias','distrito_id'=>'distritos'] as $field=>$catalog) {
                if (($h[$field] ?? '') === '' || $h[$field] === null) { $h[$field] = null; continue; }
                $geo[$field] = $this->reference($catalog, $h[$field], 'header.'.$field, $errors);
                if ($geo[$field]) $h[$field] = (int)$geo[$field]['id'];
            }
            if ($geo && (count($geo)!==3 || !$geo['provincia_id'] || !$geo['distrito_id']
                || (int)$geo['provincia_id']['departamento_id'] !== (int)($h['departamento_id'] ?? 0)
                || (int)$geo['distrito_id']['provincia_id'] !== (int)($h['provincia_id'] ?? 0))) $errors['header.geografia'] = 'Envíe departamento, provincia y distrito completos y relacionados.';
        }
        $allowed = ['producto_id','unidad_id','cantidad'];
        if ($module === 'documents') $allowed[] = 'valor_unitario';
        if ($module === 'stock') $allowed[] = 'lote_id';
        $seen = [];
        foreach ($details as $i=>&$line) {
            $path = 'details.'.$i;
            if (!is_array($line) || array_diff(array_keys($line), $allowed)) { $errors[$path] = 'Detalle con campos no admitidos.'; continue; }
            $product = $this->reference('productos', $line['producto_id'] ?? null, $path.'.producto_id', $errors);
            $unit = $this->reference('unidades_medida', $line['unidad_id'] ?? null, $path.'.unidad_id', $errors);
            if ($product) $line['producto_id'] = (int)$product['id'];
            if ($unit) $line['unidad_id'] = (int)$unit['id'];
            if (!$this->decimal($line['cantidad'] ?? null, 3, $module==='stock')) $errors[$path.'.cantidad'] = 'Cantidad fuera de rango o precisión (3 decimales); ventas y guías requieren más de cero.';
            if ($module === 'documents') {
                $line['valor_unitario'] = $line['valor_unitario'] ?? '0';
                if (!$this->decimal($line['valor_unitario'], 2, true)) $errors[$path.'.valor_unitario'] = 'Valor no negativo, máximo 2 decimales.';
            }
            if ($module === 'stock') {
                if (($line['lote_id'] ?? '') === '' || $line['lote_id'] === null) $line['lote_id'] = null;
                else {
                    $lot = $this->reference('lotes', $line['lote_id'], $path.'.lote_id', $errors);
                    if ($lot) {
                        $line['lote_id'] = (int)$lot['id'];
                        if (!$product || (int)$lot['producto_id'] !== (int)$product['id'] || (self::date($h[$date] ?? null) && $lot['fecha_vencimiento'] !== null && $lot['fecha_vencimiento'] < $h[$date])) $errors[$path.'.lote_id'] = 'Lote de otro producto o vencido en la fecha de stock.';
                    }
                }
                if ($product && $unit && (!isset($line['lote_id']) || is_int($line['lote_id']))) {
                    $key = $line['producto_id'].':'.($line['lote_id'] ?? 0).':'.$line['unidad_id'];
                    if (isset($seen[$key])) $errors[$path] = 'Producto/lote/unidad duplicados.';
                    $seen[$key] = true;
                }
            }
        }
        unset($line);
        if ($errors) throw new ValidationException($errors);
        return [$h,$details];
    }
}

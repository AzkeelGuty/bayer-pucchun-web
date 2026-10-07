<?php
declare(strict_types=1);

namespace App\Contracts;

/**
 * A trusted Inventory adapter, never a request-supplied confirmation or a production mock.
 * confirmedFact receives {sistema,id}, identifying ONE indivisible confirmed detail.
 * It returns the same identity, confirmado=true, autorizado=true, tipo, direccion,
 * finalidad, sucursal_id, despacho_id, incidencia_id, despacho_detalle_id, almacen_id,
 * producto_id, unidad_id, lote_id, cantidad, evidencia (nonempty structured array), and
 * autorizacion_ref for PERDIDA_CONFIRMADA / DISPOSICION_FINAL_CONFIRMADA.
 * Inventory must keep confirmed facts immutable or coordinate compensations explicitly.
 * Use this Repository's PDO transaction when the adapter writes in the same monolith.
 */
interface LogisticsInventoryConfirmationProviderInterface
{
    public function confirmedFact(array $reference): array;
}

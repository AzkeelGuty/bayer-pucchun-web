<?php
declare(strict_types=1);

namespace App\DTO;

use App\Support\LogisticsQuantity;

/** One idempotent Inventory operation with exact quantity and trusted execution context. */
final readonly class LogisticsInventoryCommand
{
    public string $quantity;

    public function __construct(
        public int $actorId,
        public int $branchId,
        public int $warehouseId,
        public ?int $destinationWarehouseId,
        public int $productId,
        public int $unitId,
        public ?int $lotId,
        string $quantity,
        public string $originType,
        public string $originReference,
        public string $originLineReference,
        public ?int $preparationId,
        public ?int $dispatchId,
        public string $idempotencyKey,
        public ?string $reservationReference = null,
        public ?string $movementReference = null,
        public ?string $providerOperationReference = null,
    ) {
        if ($actorId < 1 || $branchId < 1 || $warehouseId < 1 || $productId < 1 || $unitId < 1
            || ($lotId !== null && $lotId < 1) || ($preparationId !== null && $preparationId < 1)
            || ($destinationWarehouseId !== null && $destinationWarehouseId < 1)
            || ($dispatchId !== null && $dispatchId < 1)
            || !in_array($originType, ['VENTA', 'TRASLADO'], true)
            || $originReference === '' || $originLineReference === '' || $idempotencyKey === ''
            || strlen($idempotencyKey) > 128 || preg_match('/[^\x21-\x7E]/', $idempotencyKey) === 1) {
            throw new \InvalidArgumentException('Invalid Inventory operation command.');
        }
        if (($originType === 'TRASLADO' && ($destinationWarehouseId === null || $destinationWarehouseId === $warehouseId))
            || ($originType === 'VENTA' && $destinationWarehouseId !== null)) {
            throw new \InvalidArgumentException('Inventory command warehouse scope does not match origin type.');
        }
        $this->quantity = LogisticsQuantity::normalize($quantity);
    }
}

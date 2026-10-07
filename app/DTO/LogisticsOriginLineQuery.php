<?php
declare(strict_types=1);

namespace App\DTO;

use App\Support\LogisticsQuantity;

/** Candidate line data to verify against the owning Venta or Traslado module. */
final readonly class LogisticsOriginLineQuery
{
    public string $requestedQuantity;

    public function __construct(
        public string $originType,
        public string $originReference,
        public string $lineReference,
        public int $branchId,
        public int $sourceWarehouseId,
        public ?int $destinationWarehouseId,
        public int $productId,
        public int $unitId,
        public ?int $lotId,
        string $requestedQuantity,
    ) {
        if (!in_array($originType, ['VENTA', 'TRASLADO'], true)
            || $originReference === '' || $lineReference === '' || $branchId < 1
            || $sourceWarehouseId < 1 || $productId < 1 || $unitId < 1
            || ($destinationWarehouseId !== null && $destinationWarehouseId < 1)
            || ($lotId !== null && $lotId < 1)) {
            throw new \InvalidArgumentException('Invalid origin line query.');
        }
        $this->requestedQuantity = LogisticsQuantity::normalize($requestedQuantity);
        if ($originType === 'VENTA' && $destinationWarehouseId !== null) {
            throw new \InvalidArgumentException('A Venta origin cannot declare a transfer destination.');
        }
        if ($originType === 'TRASLADO' && ($destinationWarehouseId === null || $sourceWarehouseId === $destinationWarehouseId)) {
            throw new \InvalidArgumentException('A Traslado requires distinct source and destination warehouses.');
        }
    }
}

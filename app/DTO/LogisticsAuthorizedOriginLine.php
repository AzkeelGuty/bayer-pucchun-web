<?php
declare(strict_types=1);

namespace App\DTO;

use App\Support\LogisticsQuantity;

/** Authoritative line returned by the owner module, with exact decimal strings and Venta client. */
final readonly class LogisticsAuthorizedOriginLine
{
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
        public string $authorizedQuantity,
        public string $usedQuantity,
        public string $availableQuantity,
        public string $originState,
        public ?int $clientId,
    ) {
        foreach ([$authorizedQuantity, $usedQuantity, $availableQuantity] as $quantity) {
            LogisticsQuantity::normalize($quantity, true);
        }
        if (!in_array($originType, ['VENTA', 'TRASLADO'], true)
            || $branchId < 1 || $sourceWarehouseId < 1 || $productId < 1 || $unitId < 1
            || ($destinationWarehouseId !== null && $destinationWarehouseId < 1)
            || ($lotId !== null && $lotId < 1) || $originReference === '' || $lineReference === ''
            || $originState === '') {
            throw new \InvalidArgumentException('Invalid authoritative origin line.');
        }
        if (($originType === 'VENTA' && ($clientId === null || $clientId < 1))
            || ($originType === 'TRASLADO' && $clientId !== null)) {
            throw new \InvalidArgumentException('Authorized client must be present only for Venta origins.');
        }
        if ($originType === 'TRASLADO' && ($destinationWarehouseId === null || $sourceWarehouseId === $destinationWarehouseId)) {
            throw new \InvalidArgumentException('Authoritative transfer warehouses must be distinct.');
        }
        if (LogisticsQuantity::toMillis($usedQuantity, true) > LogisticsQuantity::toMillis($authorizedQuantity, true)
            || LogisticsQuantity::toMillis($availableQuantity, true)
                !== LogisticsQuantity::toMillis($authorizedQuantity, true) - LogisticsQuantity::toMillis($usedQuantity, true)) {
            throw new \InvalidArgumentException('Origin available quantity must equal authorized minus used.');
        }
    }
}

<?php
declare(strict_types=1);
require __DIR__ . '/ownership_test_support.php';
testOwnership('stock','stock',App\Controllers\StockController::class,OwnershipStockRepository::class);
echo "Stock ownership: $checks checks OK\n";

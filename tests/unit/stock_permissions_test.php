<?php
declare(strict_types=1);
require __DIR__ . '/operational_permissions_test_support.php';
testPermissions('stock', App\Controllers\StockController::class, PermissionStockRepository::class);
echo "Stock permissions: $checks checks OK\n";

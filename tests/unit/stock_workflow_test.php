<?php
declare(strict_types=1);
require __DIR__ . '/workflow_controller_test_support.php';
testController('stock', App\Controllers\StockController::class, WorkflowStockRepository::class);
echo "Stock workflow: $checks checks OK\n";

<?php
declare(strict_types=1);
require __DIR__ . '/operational_permissions_test_support.php';
testPermissions('documents', App\Controllers\DocumentController::class, PermissionDocumentRepository::class);
echo "Document permissions: $checks checks OK\n";

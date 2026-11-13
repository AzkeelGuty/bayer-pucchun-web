<?php
declare(strict_types=1);
require __DIR__ . '/ownership_test_support.php';
testOwnership('documentos','documents',App\Controllers\DocumentController::class,OwnershipDocumentRepository::class);
echo "Document ownership: $checks checks OK\n";

<?php
declare(strict_types=1);
require __DIR__ . '/operational_permissions_test_support.php';
testPermissions('guides', App\Controllers\GuideController::class, PermissionGuideRepository::class);
echo "Guide permissions: $checks checks OK\n";

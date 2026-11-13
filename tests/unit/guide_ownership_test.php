<?php
declare(strict_types=1);
require __DIR__ . '/ownership_test_support.php';
testOwnership('guias','guides',App\Controllers\GuideController::class,OwnershipGuideRepository::class);
echo "Guide ownership: $checks checks OK\n";

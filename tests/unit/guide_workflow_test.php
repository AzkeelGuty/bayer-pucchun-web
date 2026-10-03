<?php
declare(strict_types=1);
require __DIR__ . '/workflow_controller_test_support.php';
testController('guias', App\Controllers\GuideController::class, WorkflowGuideRepository::class);
echo "Guide workflow: $checks checks OK\n";

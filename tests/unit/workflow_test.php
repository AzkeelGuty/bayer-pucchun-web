<?php
declare(strict_types=1);
require __DIR__ . '/workflow_test_support.php';

use App\Exceptions\HttpException;
use App\Services\WorkflowService;

foreach ([WorkflowDocumentRepository::class, WorkflowGuideRepository::class, WorkflowStockRepository::class] as $class) {
    foreach (['invalid', 'reason', 'stale', 'state_race', 'http', 'pdo', 'runtime', 'unexpected', 'publication_pdo'] as $case) {
        $GLOBALS['workflowPdo'] = new WorkflowPDO();
        $repo = new $class(db());
        $target = 'VALIDADO';
        $version = 4;
        $expected = 500;
        if ($case === 'invalid') { $target = 'PUBLICADO'; $expected = 422; }
        if ($case === 'reason') { $repo->state = 'VALIDADO'; $target = 'OBSERVADO'; $expected = 422; }
        if ($case === 'stale') { $version = 3; $expected = 409; }
        if ($case === 'state_race') { $repo->concurrentChange = true; $expected = 409; }
        if ($case === 'http') { $repo->failure = new HttpException(422, 'Validación rechazada.'); $expected = 422; }
        if ($case === 'pdo') $repo->failure = new PDOException('SECRET SQL credentials');
        if ($case === 'runtime') $repo->failure = new RuntimeException('SECRET runtime');
        if ($case === 'unexpected') $repo->failure = new TypeError('SECRET type');
        if ($case === 'publication_pdo') { $repo->state = 'VALIDADO'; $target = 'PUBLICADO'; db()->failure = new PDOException('SECRET publication'); }
        [$status, $body, $error] = responseFor(fn() => (new WorkflowService())->transition($repo, 7, $version, $target, 23));
        check($status === $expected && $body['code'] === $expected, "$class $case HTTP classification");
        check(!str_contains(json_encode($body), 'SECRET'), "$class $case sanitized response");
        check(db()->rollbacks === 1 && db()->commits === 0 && !db()->active, "$class $case rollback");
        if ($case === 'http') check($error === $repo->failure, "$class HTTP exception preserved");
        if ($case === 'invalid') check($repo->writes === [], "$class invalid transition never writes");
        if ($expected === 500) check($body['message'] === 'Ocurrió un error interno. Inténtelo nuevamente.', "$class generic internal message");
    }
}
echo "Workflow errors: $checks checks OK\n";

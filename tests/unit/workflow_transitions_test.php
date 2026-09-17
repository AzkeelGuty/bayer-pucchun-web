<?php
declare(strict_types=1);
require __DIR__ . '/workflow_test_support.php';

$states = ['BORRADOR', 'VALIDADO', 'PUBLICADO', 'OBSERVADO', 'ANULADO'];
$allowed = ['BORRADOR>VALIDADO', 'VALIDADO>PUBLICADO', 'VALIDADO>OBSERVADO', 'OBSERVADO>BORRADOR', 'PUBLICADO>ANULADO'];
foreach ([WorkflowDocumentRepository::class, WorkflowGuideRepository::class, WorkflowStockRepository::class] as $class) {
    foreach ($states as $from) {
        foreach ([...$states, 'UNKNOWN'] as $target) {
            $GLOBALS['workflowPdo'] = new WorkflowPDO();
            $repo = new $class(db());
            $repo->state = $from;
            $operation = fn() => (new App\Services\WorkflowService())->transition($repo, 7, 4, $target, 23, 'Motivo');
            if (in_array("$from>$target", $allowed, true)) {
                check($operation() === 5, "$class $from>$target increments version");
                check(count($repo->writes) === 1 && db()->commits === 1 && db()->rollbacks === 0, "$class $from>$target succeeds once");
                [$sql, $values] = $repo->writes[0];
                check($values[0] === $target && $values[1] === 23, "$class correct target and actor");
                check(array_slice($values, -3) === [7,4,$from], "$class preserves id/version/source predicate");
                if (in_array($target, ['OBSERVADO','ANULADO'], true)) check(in_array('Motivo', $values, true), "$class forwards reason");
            } else {
                [$status] = responseFor($operation);
                check($status === 422, "$class rejects $from>$target");
                check($repo->writes === [] && db()->commits === 0, "$class rejected transition cannot write");
            }
        }
    }
    foreach ([['VALIDADO','OBSERVADO'], ['PUBLICADO','ANULADO']] as [$from,$target]) {
        foreach ([null, '', '   '] as $reason) {
            $GLOBALS['workflowPdo'] = new WorkflowPDO();
            $repo = new $class(db());
            $repo->state = $from;
            [$status] = responseFor(fn() => (new App\Services\WorkflowService())->transition($repo,7,4,$target,23,$reason));
            check($status === 422 && $repo->writes === [], "$class $target requires nonblank reason");
        }
    }
}
echo "Workflow transitions: $checks checks OK\n";

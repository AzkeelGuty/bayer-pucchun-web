<?php
declare(strict_types=1);
require __DIR__ . '/workflow_controller_test_support.php';

$modules = [
    'documentos' => [App\Controllers\DocumentController::class, WorkflowDocumentRepository::class],
    'guias' => [App\Controllers\GuideController::class, WorkflowGuideRepository::class],
    'stock' => [App\Controllers\StockController::class, WorkflowStockRepository::class],
];
$transitions = [
    ['BORRADOR','VALIDADO','validation.review'],
    ['VALIDADO','OBSERVADO','validation.review'],
    ['OBSERVADO','BORRADOR','validation.review'],
    ['VALIDADO','PUBLICADO','publications.publish'],
    ['PUBLICADO','ANULADO','publications.publish'],
];
foreach ($modules as $module => [$controllerClass,$repositoryClass]) {
    foreach ($transitions as [$from,$target,$permission]) {
        foreach ([['ADMIN'],['SUPERVISOR'],['DIGITADOR'],['GERENCIA'],['BAYER'],['ADMIN','BAYER'],['UNKNOWN']] as $roles) {
            $GLOBALS['workflowPdo'] = new WorkflowPDO();
            $repo = new $repositoryClass(db());
            $repo->state = $from;
            $controller = new $controllerClass($repo);
            $_SESSION = ['auth_user'=>['id'=>23,'roles'=>$roles,'permissions'=>[$permission]], '_csrf'=>'unchanged', '_last_activity'=>1000];
            $session = $_SESSION;
            $_POST = ['id'=>7,'version'=>'4','status'=>$target,'reason'=>'Motivo','actor_id'=>999]; $_GET = [];
            $roleAllowed = in_array($roles, [['ADMIN'],['SUPERVISOR']], true);
            // Revoke only the relevant permission while retaining the other one.
            $other = $permission === 'validation.review' ? 'publications.publish' : 'validation.review';
            foreach ([[$permission], [$other], [$permission]] as $grants) {
                db()->grants[23] = $grants;
                $repo->writes = []; $repo->reads = 0; $GLOBALS['audits'] = [];
                $previousCommits = db()->commits;
                if ($roleAllowed && in_array($permission,$grants,true)) {
                    try { $controller->changeStatus(); throw new RuntimeException('Missing redirect'); }
                    catch (WorkflowRedirect $redirect) { check($redirect->getMessage() === "/$module/ver?id=7", "$module $target allowed"); }
                    check(count($repo->writes) === 1 && db()->commits === $previousCommits+1, "$module $target executes once");
                    check(count($GLOBALS['audits']) === 1 && $repo->writes[0][1][1] === 23, "$module $target session actor and audit");
                } else {
                    [$status,$body] = responseFor(fn() => $controller->changeStatus());
                    check($status === 403 && $body['code'] === 403, "$module $target denied");
                    check($repo->reads === 0 && $repo->writes === [] && db()->commits === $previousCommits, "$module denied before record access");
                    check($GLOBALS['audits'] === [], "$module denied has no success audit");
                }
                check($_SESSION === $session, "$module $target preserves entire session including after revocation");
            }
            check(db()->permissionLookups === ($roleAllowed ? 3 : 0), "$module $target refreshes grants with same session/controller");
        }
    }
    foreach (['', 'UNKNOWN', [], 123] as $target) {
        $GLOBALS['workflowPdo'] = new WorkflowPDO();
        db()->grants[23] = ['validation.review','publications.publish'];
        $_SESSION = ['auth_user'=>['id'=>23,'roles'=>['SUPERVISOR']]];
        $_POST = ['id'=>7,'version'=>'4','status'=>$target];
        $repo = new $repositoryClass(db());
        [$status] = responseFor(fn() => (new $controllerClass($repo))->changeStatus());
        check($status === 422 && $repo->reads === 0 && $repo->writes === [], "$module malformed target never executes");
    }
}
echo "Workflow permissions: $checks checks OK\n";

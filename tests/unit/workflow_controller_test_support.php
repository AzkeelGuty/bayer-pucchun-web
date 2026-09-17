<?php
declare(strict_types=1);
require __DIR__ . '/workflow_test_support.php';

function input(string $key, mixed $default = null): mixed { return $_POST[$key] ?? $_GET[$key] ?? $default; }
function auth_user(): array { return $_SESSION['auth_user']; }
function has_role(string ...$roles): bool { return App\Policies\AccessPolicy::allows(auth_user(), $roles); }
function require_role(string ...$roles): void {
    if (!has_role(...$roles)) throw new App\Exceptions\HttpException(403, 'Acceso denegado.');
}
function audit(string $module, string $action, int $id): void { $GLOBALS['audits'][] = [$module, $action, $id]; }
function flash(string $key, string $value): void {}
function url(string $path): string { return $path; }
function csrf_field(): string { return '<input name="_csrf" value="test-token">'; }
function estado_badge(string $state): string { return e($state); }
final class WorkflowRedirect extends RuntimeException {}
function redirect(string $path): never { throw new WorkflowRedirect($path); }

function testController(string $module, string $controllerClass, string $repositoryClass): void {
    $_SESSION = ['auth_user' => ['id' => 23, 'roles' => ['SUPERVISOR']]];
    $invalid = [null, '', [], ['4'], true, false, 4.0, '4.0', '4abc', '4e0', '-1', '0', ' 4', '+4', '04', str_repeat('9', 30)];
    foreach ($invalid as $value) {
        $GLOBALS['workflowPdo'] = new WorkflowPDO();
        $repo = new $repositoryClass(db());
        $_POST = ['id' => '7', 'status' => 'VALIDADO'];
        if ($value !== null) $_POST['version'] = $value;
        $_GET = ['version' => '4']; // A query parameter must not substitute the POST version.
        $GLOBALS['audits'] = [];
        [$status] = responseFor(fn() => (new $controllerClass($repo))->changeStatus());
        check($status === 422, "$module rejects malformed/missing POST version");
        check($repo->writes === [] && $GLOBALS['audits'] === [], "$module invalid version has no write/audit");
    }
    $_GET = [];
    foreach ([['BORRADOR','VALIDADO'], ['VALIDADO','PUBLICADO'], ['VALIDADO','OBSERVADO'], ['OBSERVADO','BORRADOR'], ['PUBLICADO','ANULADO']] as [$from,$target]) {
        foreach ([3, 4] as $version) {
            $GLOBALS['workflowPdo'] = new WorkflowPDO();
            $repo = new $repositoryClass(db());
            $repo->state = $from;
            $_POST = ['id' => '7', 'status' => $target, 'version' => (string)$version, 'reason' => 'Motivo de prueba', 'actor_id' => 999];
            $GLOBALS['audits'] = [];
            $controller = new $controllerClass($repo);
            if ($version === 3) {
                [$status] = responseFor(fn() => $controller->changeStatus());
                check($status === 409, "$module $target stale version");
                check(db()->commits === 0 && $GLOBALS['audits'] === [], "$module stale version never succeeds");
            } else {
                try { $controller->changeStatus(); throw new RuntimeException('Missing redirect'); }
                catch (WorkflowRedirect $redirect) { check($redirect->getMessage() === "/$module/ver?id=7", "$module correct version succeeds"); }
                check(db()->commits === 1 && count($GLOBALS['audits']) === 1, "$module successful workflow");
            }
            $values = $repo->writes[0][1];
            check($values[count($values)-2] === $version, "$module forwards client version unchanged");
            check($values[1] === 23, "$module uses session actor, not submitted actor");
        }
    }
    foreach (['BORRADOR','VALIDADO','OBSERVADO','PUBLICADO','ANULADO'] as $state) {
        $record = ['header' => ['id'=>7, 'version'=>4, 'estado_registro'=>$state, 'numero'=>'G-7', 'fecha'=>'2026-09-17', 'fecha_stock'=>'2026-09-17', 'cliente_id'=>1, 'vendedor_id'=>1, 'sucursal_id'=>1, 'departamento_id'=>null, 'provincia_id'=>null, 'distrito_id'=>null, 'almacen_id'=>1, 'idempotency_key'=>'test', 'observation_reason'=>'Prueba', 'cancellation_reason'=>'Prueba'], 'details'=>[]];
        $clientes=$vendedores=$sucursales=$departamentos=$provincias=$distritos=$almacenes=$productos=$unidades=$lotes=[];
        ob_start();
        require base_path("app/Views/$module/show.php");
        $html = ob_get_clean();
        preg_match_all('~<form\b[^>]*action="/'.$module.'/estado"[^>]*>.*?</form>~s', $html, $forms);
        check(count($forms[0]) >= 2, "$module $state renders workflow forms");
        foreach ($forms[0] as $form) {
            check(substr_count($form, 'name="version"') === 1 && str_contains($form, 'name="version" value="4"'), "$module $state form submits displayed version");
        }
    }
}

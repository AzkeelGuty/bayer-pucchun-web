<?php
declare(strict_types=1);
require __DIR__ . '/workflow_controller_test_support.php';

// Real PermissionService; only its PDO boundary and downstream business work are doubled.
function view(string $name, array $data = []): void { operationProbe(); }
final class PermissionProbe extends RuntimeException {}
function operationProbe(): never { $GLOBALS['operationReached'] = true; throw new PermissionProbe('Business boundary'); }
final class PermissionRows extends PDOStatement {
    private int $userId = 0;
    public function __construct(private PermissionPDO $connection) {}
    public function execute(?array $params = null): bool { $this->userId = (int)$params[0]; return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->connection->grants[$this->userId] ?? [];
    }
}
final class PermissionPDO extends PDO {
    public array $grants = [];
    public int $lookups = 0;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'SELECT DISTINCT p.codigo')) { ++$this->lookups; return new PermissionRows($this); }
        operationProbe();
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { operationProbe(); }
}
trait PermissionRepositoryProbe {
    public function find(int $id): ?array { operationProbe(); }
    public function deleteDraft(int $id, int $expectedVersion, int $actorId): void { operationProbe(); }
}
class PermissionDocumentRepository extends App\Repositories\DocumentRepository { use PermissionRepositoryProbe; }
class PermissionGuideRepository extends App\Repositories\GuideRepository { use PermissionRepositoryProbe; }
class PermissionStockRepository extends App\Repositories\StockRepository { use PermissionRepositoryProbe; }

function testPermissions(string $module, string $controllerClass, string $repositoryClass): void {
    $actions = ['index'=>'read','show'=>'read','create'=>'create','edit'=>'create','store'=>'create','update'=>'create','destroy'=>'create'];
    foreach ($actions as $action => $suffix) {
        foreach (['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role) {
            $GLOBALS['workflowPdo'] = new PermissionPDO();
            $_SESSION = ['auth_user'=>['id'=>23,'roles'=>[$role],'permissions'=>["$module.$suffix"]]];
            $_POST = ['id'=>7,'version'=>4]; $_GET = [];
            $session = $_SESSION;
            $controller = new $controllerClass(new $repositoryClass(db()));
            $roleAllowed = in_array($role, $suffix === 'read' ? ['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'] : ['ADMIN','DIGITADOR'], true);
            // Grant, revoke, restore without replacing the Controller or session.
            foreach ([true, false, true] as $granted) {
                db()->grants[23] = $granted ? ["$module.$suffix"] : [];
                $GLOBALS['operationReached'] = false;
                $caught = null;
                try { $controller->$action(); } catch (Throwable $error) { $caught = $error; }
                if ($roleAllowed && $granted) {
                    check($GLOBALS['operationReached'], "$module $action $role granted reaches business boundary");
                } else {
                    check($caught instanceof App\Exceptions\HttpException && $caught->status === 403, "$module $action $role denied");
                    check(!$GLOBALS['operationReached'], "$module $action denied before business work");
                }
                check($_SESSION === $session, "$module $action denial/grant leaves same session intact");
            }
            check(db()->lookups === ($roleAllowed ? 3 : 0), "$module $action fresh persisted check on each allowed-role request");
        }
    }
}

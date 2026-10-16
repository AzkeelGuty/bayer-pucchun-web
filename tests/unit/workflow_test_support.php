<?php
declare(strict_types=1);

// In-memory doubles only: no bootstrap, .env, database connection, session or log files.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
$checks = 0;
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    ++$GLOBALS['checks'];
}
function db(): PDO { return $GLOBALS['workflowPdo']; }
function log_event(string $message, array $context = []): void { $GLOBALS['events'][] = [$message, $context]; }
function base_path(string $path = ''): string { return dirname(__DIR__, 2) . '/' . $path; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

final class WorkflowStatement extends PDOStatement {
    public function __construct(private int $affected = 1) {}
    public function execute(?array $params = null): bool { return true; }
    public function rowCount(): int { return $this->affected; }
}
final class WorkflowPermissionRows extends PDOStatement {
    private int $userId = 0;
    public function __construct(private WorkflowPDO $connection) {}
    public function execute(?array $params = null): bool { $this->userId = (int)$params[0]; return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->connection->grants[$this->userId] ?? [];
    }
}
final class WorkflowPDO extends PDO {
    public array $grants = [];
    public int $permissionLookups = 0;
    public bool $active = false;
    public int $commits = 0;
    public int $rollbacks = 0;
    public ?Throwable $failure = null;
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool { $this->active = true; return true; }
    public function commit(): bool { ++$this->commits; $this->active = false; return true; }
    public function rollBack(): bool { ++$this->rollbacks; $this->active = false; return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($this->failure) throw $this->failure;
        if (str_contains($query, 'SELECT DISTINCT p.codigo')) {
            ++$this->permissionLookups;
            return new WorkflowPermissionRows($this);
        }
        return new WorkflowStatement();
    }
    public function lastInsertId(?string $name = null): string|false { return '10'; }
}
trait WorkflowRepositoryDouble {
    public string $state = 'BORRADOR';
    public int $version = 4;
    public array $writes = [];
    public int $reads = 0;
    public ?Throwable $failure = null;
    public bool $concurrentChange = false;
    public function find(int $id): ?array {
        ++$this->reads;
        return ['header' => ['id' => $id, 'estado_registro' => $this->state, 'version' => $this->version], 'details' => []];
    }
    protected function execute(string $sql, array $values = []): PDOStatement {
        if ($this->failure) throw $this->failure;
        $this->writes[] = [$sql, $values];
        // Exercise the real persistState() error on a zero-row optimistic update.
        $matches = !$this->concurrentChange && $values[count($values)-2] === $this->version
            && $values[count($values)-1] === $this->state;
        return new WorkflowStatement($matches ? 1 : 0);
    }
}
class WorkflowDocumentRepository extends App\Repositories\DocumentRepository { use WorkflowRepositoryDouble; }
class WorkflowGuideRepository extends App\Repositories\GuideRepository { use WorkflowRepositoryDouble; }
class WorkflowStockRepository extends App\Repositories\StockRepository { use WorkflowRepositoryDouble; }

function responseFor(callable $operation): array {
    try { $operation(); }
    catch (Throwable $error) {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        ob_start();
        App\Services\ErrorHandler::render($error);
        $body = ob_get_clean();
        return [http_response_code(), json_decode($body, true, 512, JSON_THROW_ON_ERROR), $error];
    }
    throw new RuntimeException('Expected an error response.');
}

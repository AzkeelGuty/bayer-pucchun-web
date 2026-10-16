<?php
declare(strict_types=1);
require __DIR__ . '/workflow_controller_test_support.php';

// Real Controllers/Policies/PermissionService; all storage is in-memory, no SQL execution.
function view(string $name, array $data = []): void { $GLOBALS['rendered'] = [$name,$data]; }
function index_by(array $rows, string $key): array { return array_column($rows,null,$key); }
function quantity_integer_value(mixed $value, bool $allowZero=false): ?int {
    if ((!is_string($value) && !is_int($value)) || is_bool($value)) return null;
    $raw=trim((string)$value);
    if (!preg_match('/^\d+(?:\.0{1,3})?$/D',$raw)) return null;
    $whole=ltrim(explode('.',$raw,2)[0],'0');
    if ($whole==='') $whole='0';
    if (strlen($whole)>11) return null;
    $quantity=(int)$whole;
    if ($allowZero ? $quantity<0 : $quantity<1) return null;
    return $quantity;
}
final class OwnershipRows extends PDOStatement {
    private array $rows = [];
    public function __construct(private Closure $resolve) {}
    public function execute(?array $params = null): bool { $this->rows = ($this->resolve)($params ?? []); return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return array_values($this->rows[0] ?? [])[$column] ?? false; }
}
final class OwnershipPDO extends PDO {
    public array $grants = [];
    public array $records = [];
    public array $queries = [];
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $statement = new OwnershipRows(static fn(array $params): array => [['id'=>1,'label'=>'Fixture','nombre'=>'Fixture']]);
        $statement->execute();
        return $statement;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new OwnershipRows(function(array $params) use ($query): array {
            $this->queries[] = [$query,$params];
            if (str_contains($query,'SELECT DISTINCT p.codigo')) return $this->grants[(int)$params[0]] ?? [];
            // Lightweight lookup/catalog queries introduced for remote autocomplete.
            if (str_contains($query,'FROM clientes c')) return [['id'=>1,'codigo'=>'CLI-1','nro_doc'=>'00000001','razon_social'=>'Cliente Fixture','label'=>'00000001 · Cliente Fixture','departamento_id'=>1,'provincia_id'=>1,'distrito_id'=>1,'vendedor_sugerido_id'=>1,'sucursal_sugerida_id'=>1,'vendedor_sugerido_label'=>'VEN-1 · Vendedor Fixture','sucursal_sugerida_label'=>'SUC-1 · Sucursal Fixture']];
            if (str_contains($query,'FROM vendedores')) return [['id'=>1,'codigo'=>'VEN-1','nombre'=>'Vendedor Fixture','label'=>'VEN-1 · Vendedor Fixture']];
            if (str_contains($query,'FROM sucursales')) return [['id'=>1,'codigo'=>'SUC-1','nombre'=>'Sucursal Fixture','label'=>'SUC-1 · Sucursal Fixture']];
            if (str_contains($query,'FROM almacenes')) return [['id'=>1,'codigo'=>'ALM-1','nombre'=>'Almacén Fixture','label'=>'ALM-1 · Almacén Fixture']];
            if (str_contains($query,'FROM productos p')) return [['id'=>1,'codigo'=>'PRD-1','nombre'=>'Producto Fixture','label'=>'PRD-1 · Producto Fixture','unidad_base_id'=>1,'unidad_label'=>'NIU · Unidad']];
            if (str_contains($query,'FROM lotes')) return [['id'=>1,'producto_id'=>1,'codigo_lote'=>'L-1','fecha_vencimiento'=>null,'label'=>'L-1']];
            if (str_contains($query,'FROM provincias')) return [['id'=>1,'departamento_id'=>1,'nombre'=>'Provincia Fixture']];
            if (str_contains($query,'FROM distritos')) return [['id'=>1,'provincia_id'=>1,'nombre'=>'Distrito Fixture']];
            if (str_contains($query,'FROM unidades_medida')) return [['id'=>1,'label'=>'NIU · Unidad']];
            // DocumentScreenService's existing list/count are inspected and evaluated over fixtures.
            if (!str_contains($query,'FROM documentos_cabecera h')) throw new RuntimeException('Unexpected query in ownership test');
            $rows = array_column(array_values($this->records),'header');
            if (str_contains($query,'h.created_by=?')) $rows=array_values(array_filter($rows,static fn(array $row): bool => $row['created_by'] === $params[0]));
            if (str_contains($query,'SELECT COUNT(*)')) return [[count($rows)]];
            return $rows;
        });
    }
}
trait OwnershipRepositoryDouble {
    public array $records = [];
    public array $writes = [];
    public array $filters = [];
    public function find(int $id): ?array { return $this->records[$id] ?? null; }
    public function all(array $filters = [], int $limit = 300, int $offset = 0): array {
        $this->filters = $filters;
        $rows = array_column(array_values($this->records),'header');
        if (isset($filters['created_by'])) $rows=array_values(array_filter($rows,static fn(array $row): bool => $row['created_by'] === $filters['created_by']));
        return array_slice($rows,$offset,$limit);
    }
    public function countFiltered(array $filters = []): int {
        $rows = array_column(array_values($this->records),'header');
        if (isset($filters['created_by'])) $rows=array_values(array_filter($rows,static fn(array $row): bool => $row['created_by'] === $filters['created_by']));
        return count($rows);
    }
    public function countByState(string $state): int {
        return count(array_filter($this->records,static fn(array $record): bool => ($record['header']['estado_registro']??'')===$state));
    }
    public function updateDraft(int $id, int $expectedVersion, array $header, array $details, int $actorId): int {
        $this->writes[] = ['update',$id,$expectedVersion,$actorId,$header];
        if ($this->records[$id]['header']['version'] !== $expectedVersion) throw new RuntimeException('Stale fixture');
        $this->records[$id]['header']['version']++;
        return $expectedVersion+1;
    }
    public function deleteDraft(int $id, int $expectedVersion, int $actorId): void {
        $this->writes[] = ['delete',$id,$expectedVersion,$actorId];
        $record=$this->records[$id] ?? null;
        if (!$record || $record['header']['version'] !== $expectedVersion || $record['header']['estado_registro'] !== 'BORRADOR') throw new RuntimeException('Stale fixture');
        unset($this->records[$id]);
    }
}
class OwnershipDocumentRepository extends App\Repositories\DocumentRepository {
    use OwnershipRepositoryDouble;
    public function findByNumber(int $typeId, string $number): ?array { return null; }
}
class OwnershipGuideRepository extends App\Repositories\GuideRepository { use OwnershipRepositoryDouble; }
class OwnershipStockRepository extends App\Repositories\StockRepository { use OwnershipRepositoryDouble; }

function ownershipFixture(string $repositoryClass, string $permissionPrefix, array $roles = ['DIGITADOR']): object {
    $GLOBALS['workflowPdo'] = new OwnershipPDO();
    db()->grants[23] = ["$permissionPrefix.read","$permissionPrefix.create"];
    $_SESSION=['auth_user'=>['id'=>23,'roles'=>$roles], '_csrf'=>'retained'];
    $repo = new $repositoryClass(db());
    foreach ([7=>23,8=>24,9=>null] as $id=>$owner) {
        $repo->records[$id]=['header'=>['id'=>$id,'created_by'=>$owner,'version'=>4,'estado_registro'=>'BORRADOR','tipo_documento_id'=>1,'numero'=>'TEST-'.$id,'fecha'=>'2026-09-17','cliente_id'=>1,'vendedor_id'=>1,'sucursal_id'=>1,'fecha_stock'=>'2026-09-17','almacen_id'=>1,'idempotency_key'=>'stock-'.$id], 'details'=>[]];
    }
    db()->records =& $repo->records;
    $GLOBALS['audits']=[]; $GLOBALS['rendered']=null;
    return $repo;
}
function ownershipPost(int $id): array {
    $header=['tipo_documento_id'=>1,'numero'=>'TEST-'.$id,'fecha'=>'2026-09-17','cliente_id'=>1,'vendedor_id'=>1,'sucursal_id'=>1];
    $line=['producto_id'=>1,'unidad_id'=>1,'cantidad'=>'2','valor_unitario'=>'1'];
    return $header+['id'=>$id,'version'=>'4','created_by'=>23,'actor_id'=>999,'header'=>$header,'details'=>[$line],'detalle'=>[$line], 'fecha_stock'=>'2026-09-17','almacen_id'=>1,'idempotency_key'=>'stock-'.$id];
}
function testOwnership(string $module, string $prefix, string $controllerClass, string $repositoryClass): void {
    foreach (['show','edit','update','destroy'] as $action) {
        foreach ([7,8,9] as $id) {
            $repo=ownershipFixture($repositoryClass,$prefix);
            $before=$repo->records; $session=$_SESSION;
            $_GET=[]; $_POST=ownershipPost($id);
            $controller=new $controllerClass($repo);
            if ($id !== 7) {
                [$status]=responseFor(fn() => $controller->$action());
                check($status===403,"$module $action rejects foreign/unowned ID");
                check($repo->records===$before && $repo->writes===[],"$module $action leaves foreign record unchanged");
                check($GLOBALS['rendered']===null && $GLOBALS['audits']===[],"$module $action does not expose or audit success");
                check($_SESSION===$session,"$module $action denial preserves session");
            } else {
                try { $controller->$action(); } catch (WorkflowRedirect $redirect) {}
                if (in_array($action,['update','destroy'],true)) {
                    check(count($repo->writes)===1,"$module own $action reaches persistence once");
                    check(array_slice($repo->writes[0],1,3)===[7,4,23],"$module own $action uses session actor and submitted version");
                    check($action==='destroy' ? !isset($repo->records[7]) : $repo->records[7]['header']['version']===5,"$module own $action succeeds");
                } else {
                    check($GLOBALS['rendered']!==null,"$module own $action renders");
                    check($repo->writes===[],"$module own $action does not mutate");
                }
                check($repo->records[8]===$before[8] && $repo->records[9]===$before[9],"$module own $action preserves other records");
            }
        }
        $repo=ownershipFixture($repositoryClass,$prefix); $_POST=ownershipPost(999);
        [$status]=responseFor(fn() => (new $controllerClass($repo))->$action());
        check($status===404 && $repo->writes===[],"$module $action missing record");
    }
    foreach ([['ADMIN'],['SUPERVISOR'],['GERENCIA'],['BAYER'],['DIGITADOR','ADMIN'],['DIGITADOR','SUPERVISOR'],['DIGITADOR','GERENCIA'],['ADMIN','BAYER']] as $roles) {
        foreach (['show','edit','update','destroy'] as $action) {
            $repo=ownershipFixture($repositoryClass,$prefix,$roles); $_POST=ownershipPost(8);
            $controller=new $controllerClass($repo);
            $allowed=App\Policies\AccessPolicy::allows($_SESSION['auth_user'],$action==='show' ? App\Policies\AccessPolicy::INTERNAL : App\Policies\AccessPolicy::CAPTURE);
            if ($allowed) {
                try { $controller->$action(); } catch (WorkflowRedirect $redirect) {}
                check(in_array($action,['show','edit'],true) ? $GLOBALS['rendered']!==null : count($repo->writes)===1,"$module $action preserves existing mixed/internal role behavior");
            } else {
                [$status]=responseFor(fn() => $controller->$action());
                check($status===403 && $repo->writes===[],"$module $action keeps role denial");
            }
        }
    }
    foreach ([['DIGITADOR'],['ADMIN'],['SUPERVISOR'],['GERENCIA'],['DIGITADOR','ADMIN'],['DIGITADOR','SUPERVISOR'],['BAYER'],['ADMIN','BAYER']] as $roles) {
        $repo=ownershipFixture($repositoryClass,$prefix,$roles);
        $_POST=[]; $_GET=['created_by'=>24]; // Client cannot choose another owner or narrow a privileged role.
        if (in_array('BAYER',$roles,true)) {
            [$status]=responseFor(fn() => (new $controllerClass($repo))->index());
            check($status===403,"$module Bayer cannot list");
        } else {
            (new $controllerClass($repo))->index();
            $rows=$GLOBALS['rendered'][1]['rows'];
            check(array_column($rows,'id')===($roles===['DIGITADOR'] ? [7] : [7,8,9]),"$module listing applies session owner, not query owner");
            if ($module==='documentos') {
                check($GLOBALS['rendered'][1]['total']===count($rows),"Documents count matches scoped list");
            } else {
                check(($repo->filters['created_by'] ?? null)===($roles===['DIGITADOR'] ? 23 : null),"$module owner applied before Repository listing");
            }
        }
    }
    foreach ([3,4] as $version) {
        $repo=ownershipFixture($repositoryClass,$prefix); $_POST=ownershipPost(7); $_POST['version']=(string)$version;
        $controller=new $controllerClass($repo);
        if ($version===3) {
            [$status]=responseFor(fn() => $controller->destroy());
            check($status===409 && isset($repo->records[7]),"$module stale delete preserves own record");
        } else {
            try { $controller->destroy(); } catch (WorkflowRedirect $redirect) {}
            check(!isset($repo->records[7]),"$module versioned delete still works");
        }
    }
}

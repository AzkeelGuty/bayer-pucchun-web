<?php
declare(strict_types=1);
require __DIR__ . '/workflow_test_support.php';

// Inspect actual Repository SQL construction; a small in-memory evaluator replaces the driver.
final class ListingRows extends PDOStatement {
    public function __construct(private array $rows) {}
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
trait ListingRepositoryDouble {
    public array $records = [];
    public array $queries = [];
    protected function lockedHeader(int $id): array|false { return $this->records[$id] ?? false; }
    protected function execute(string $sql, array $values = []): PDOStatement {
        $this->queries[] = [$sql,$values];
        if (str_starts_with($sql,'DELETE FROM ')) {
            [$id,$version]=$values;
            $matches=isset($this->records[$id]) && $this->records[$id]['version']===$version && $this->records[$id]['estado_registro']==='BORRADOR';
            if ($matches) unset($this->records[$id]);
            return new WorkflowStatement($matches ? 1 : 0);
        }
        if (!preg_match('/ ORDER BY h.id DESC LIMIT (\d+) OFFSET (\d+)$/',$sql,$page)) throw new RuntimeException('Unexpected listing order/pagination');
        preg_match_all('/h\.(\w+)(>=|<=|=)\?/',$sql,$predicates,PREG_SET_ORDER);
        if (count($predicates)!==count($values)) throw new RuntimeException('Unmatched parameter');
        $rows=array_values($this->records);
        foreach ($predicates as $i=>$predicate) {
            [, $field,$operator]=$predicate; $value=$values[$i];
            $rows=array_values(array_filter($rows,static fn(array $row): bool => match($operator) {
                '=' => (string)$row[$field]===(string)$value,
                '>=' => $row[$field]>=$value,
                '<=' => $row[$field]<=$value,
            }));
        }
        usort($rows,static fn(array $a,array $b): int => $b['id']<=>$a['id']);
        return new ListingRows(array_slice($rows,(int)$page[2],(int)$page[1]));
    }
}
class ListingDocumentRepository extends App\Repositories\DocumentRepository { use ListingRepositoryDouble; }
class ListingGuideRepository extends App\Repositories\GuideRepository { use ListingRepositoryDouble; }
class ListingStockRepository extends App\Repositories\StockRepository { use ListingRepositoryDouble; }
function listingFixture(string $class): object {
    $GLOBALS['workflowPdo']=new WorkflowPDO(); $repo=new $class(db());
    foreach ([[9,24,'BORRADOR',1,'2026-01-01'],[8,23,'BORRADOR',2,'2026-01-02'],[7,24,'VALIDADO',1,'2026-01-02'],[6,23,'BORRADOR',1,'2026-01-02'],[5,23,'PUBLICADO',1,'2026-01-03'],[4,23,'BORRADOR',1,'2025-12-31']] as [$id,$owner,$state,$location,$date]) {
        $repo->records[$id]=['id'=>$id,'created_by'=>$owner,'estado_registro'=>$state,'version'=>4,'fecha'=>$date,'fecha_stock'=>$date,'sucursal_id'=>$location,'almacen_id'=>$location];
    }
    return $repo;
}
foreach ([ListingDocumentRepository::class,ListingGuideRepository::class,ListingStockRepository::class] as $class) {
    $repo=listingFixture($class);
    check(array_column($repo->all(),'id')===[9,8,7,6,5,4],"$class unfiltered behavior unchanged");
    [$sql,$params]=end($repo->queries);
    check(!str_contains($sql,'h.created_by=?') && $params===[],"$class no implicit owner filter");
    foreach ([23,'23'] as $owner) {
        check(array_column($repo->all(['created_by'=>$owner]),'id')===[8,6,5,4],"$class owner filter");
        [$sql,$params]=end($repo->queries);
        check($params===[23] && str_contains($sql,'WHERE h.created_by=? ORDER BY'),"$class binds validated owner before pagination");
    }
    check(array_column($repo->all(['created_by'=>23],2,1),'id')===[6,5],"$class offset applies to owned rows");
    $pages=[];
    foreach ([0,2,4] as $offset) $pages=array_merge($pages,array_column($repo->all(['created_by'=>23],2,$offset),'id'));
    check($pages===[8,6,5,4],"$class pages never mix foreign rows or skip owned rows");
    check($repo->all(['created_by'=>999])===[],"$class absent owner has no rows");
    $location=$class===ListingStockRepository::class ? 'almacen_id' : 'sucursal_id';
    $filters=['created_by'=>'23','estado_registro'=>'BORRADOR','fecha_desde'=>'2026-01-01','fecha_hasta'=>'2026-01-02',$location=>1];
    check(array_column($repo->all($filters),'id')===[6],"$class combines existing filters");
    [$sql,$params]=end($repo->queries);
    check($params===[23,'BORRADOR','2026-01-01','2026-01-02',1],"$class preserves filter binding order");
    unset($filters['created_by']);
    check(array_column($repo->all($filters),'id')===[9,6],"$class old filter combination still works");
    foreach ([null,0,-1,'',[],true,false,1.5,'23x','1 OR 1=1',str_repeat('9',30)] as $invalid) {
        $before=count($repo->queries);
        try { $repo->all(['created_by'=>$invalid]); throw new RuntimeException('Invalid owner accepted'); }
        catch (InvalidArgumentException $error) { check(count($repo->queries)===$before,"$class invalid owner rejected before execute"); }
    }
    foreach ([[0,0],[301,0],[1,-1]] as [$limit,$offset]) {
        try { $repo->all(['created_by'=>23],$limit,$offset); throw new RuntimeException('Invalid pagination accepted'); }
        catch (InvalidArgumentException $error) { check(true,"$class pagination validation preserved"); }
    }
    try { $repo->all(['owner'=>23]); throw new RuntimeException('Unknown filter accepted'); }
    catch (InvalidArgumentException $error) { check(true,"$class unknown filter rejected"); }
    // Exercise inherited deleteDraft(), not a replacement of that method.
    foreach ([[6,4,true],[6,3,false],[5,4,false],[999,4,false]] as [$id,$version,$allowed]) {
        $repo=listingFixture($class); $before=$repo->records;
        if ($allowed) {
            $repo->deleteDraft($id,$version,23);
            check(!isset($repo->records[$id]) && db()->commits===1,"$class real deleteDraft remains functional");
            [$sql,$params]=end($repo->queries);
            check(str_contains($sql,"WHERE id=? AND version=? AND estado_registro='BORRADOR'") && $params===[$id,$version],"$class delete retains version/state guard");
        } else {
            try { $repo->deleteDraft($id,$version,23); throw new LogicException('Invalid delete accepted'); }
            catch (RuntimeException $error) { check($repo->records===$before && db()->rollbacks===1,"$class stale/non-draft/missing delete rejected"); }
        }
    }
}
echo "Repository ownership: $checks checks OK\n";

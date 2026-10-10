<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Repositories\{DocumentRepository,GuideRepository,StockRepository};
use App\Services\WorkflowService;

function db(): PDO
{
    return $GLOBALS['workflow_test_pdo'];
}

$db=new TestDatabase();
try{
    $db->load('database/schemas/002_schema_v2.sql');
    $pdo=$db->pdo;
    $GLOBALS['workflow_test_pdo']=$pdo;
    fixtures($pdo);

    $docs=new DocumentRepository($pdo);
    $guides=new GuideRepository($pdo);
    $stock=new StockRepository($pdo);
    $workflow=new WorkflowService();

    $docs->create(docHeader('MANUAL-A'),lines(),1);
    $docs->create(docHeader('MANUAL-B'),lines(),1);
    $guides->create(guideHeader('GUIA-MANUAL-A'),lines(),1);
    $guides->create(guideHeader('GUIA-MANUAL-B'),lines(),1);
    $stock->create(stockHeader('bulk-stock-a','2026-10-01'),lines(true),1);
    $stock->create(stockHeader('bulk-stock-b','2026-10-02'),lines(true),1);

    foreach([$docs,$guides,$stock] as $repository){
        ensure($repository->countByState('BORRADOR')===2,'Two draft candidates');
        $result=$workflow->bulkTransition($repository,'VALIDADO',2);
        ensure($result['success']===2 && $result['failed']===0,'Bulk validation succeeds');
        ensure($repository->countByState('BORRADOR')===0 && $repository->countByState('VALIDADO')===2,'All drafts validated');

        $result=$workflow->bulkTransition($repository,'PUBLICADO',2);
        ensure($result['success']===2 && $result['failed']===0,'Bulk publication succeeds');
        ensure($repository->countByState('VALIDADO')===0 && $repository->countByState('PUBLICADO')===2,'All validated rows published');
    }

    ensure((int)$pdo->query("SELECT COUNT(*) FROM validaciones WHERE resultado='VALIDADO'")->fetchColumn()===6,'Bulk validation audit rows created');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM publicaciones WHERE estado='PUBLICADO'")->fetchColumn()===6,'Bulk publication rows created');

    echo 'Bulk workflow: '.$GLOBALS['checks']." checks OK\n";
}finally{
    unset($GLOBALS['workflow_test_pdo']);
    $db->close();
}

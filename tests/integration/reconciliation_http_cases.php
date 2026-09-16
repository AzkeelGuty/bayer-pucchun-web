<?php
declare(strict_types=1);

// Included by core_security_test.php: same disposable DB, HTTP server and counters.
$startReconciliation=$GLOBALS['checks'];
$jsonHeaders=['Accept: application/json'];
$reviewToken=token(request('/guias',$supervisor));
foreach (['documentos'=>'documents','guias'=>'guides','stock'=>'stock'] as $path=>$module) {
    $newPage=request('/'.$path.'/nuevo',$digitador);
    ensure($newPage['status']===200 && str_contains($newPage['body'],'<form'),'Integrated HTML form '.$module);
    $captureToken=token($newPage);
    $h=match($module){'documents'=>docHeader('P0-HTML-DOC'),'guides'=>guideHeader('P0-HTML-GUIDE'),'stock'=>stockHeader('p0-html-stock','2026-10-01')};
    $detail=lines($module==='stock');
    $payload=$module==='documents' ? ['header'=>$h,'details'=>$detail] : $h+['detalle'=>$detail];
    $payload['_csrf']=$captureToken;
    $invalid=$payload;
    if ($module==='documents') $invalid['details'][0]['cantidad']='-1';
    else $invalid['detalle'][0]['cantidad']='-1';
    $r=request('/'.$path.'/guardar',$digitador,$invalid);
    ensure($r['status']===422 && str_contains($r['body'],'<form'),'Invalid HTML redisplays form with 422 '.$module);
    ensure(str_contains($r['body'],'value="-1"'),'Invalid quantity preserved for correction '.$module);
    $injected=$payload; $injected['created_by']=$ids['ADMIN'];
    ensure(request('/'.$path.'/guardar',$digitador,$injected,$jsonHeaders)['status']===422,'Flat/nested actor injection rejected '.$module);
    $r=request('/'.$path.'/guardar',$digitador,$payload);
    ensure($r['status']===302,'Integrated HTML capture redirects '.$module);
    preg_match('~/'.$path.'/ver\?id=(\d+)~',$r['headers'],$m);
    ensure(isset($m[1]),'Capture redirects to integrated detail '.$module);
    $id=(int)$m[1];
    $repo=match($module){'documents'=>$docs,'guides'=>$guides,'stock'=>$stocks};
    $record=$repo->find($id);
    ensure((int)$record['header']['created_by']===$ids['DIGITADOR'] && $record['header']['estado_registro']==='BORRADOR','Capture actor/state '.$module);
    ensure(request('/'.$path.'/ver?id='.$id,$digitador)['status']===200,'Integrated HTML detail '.$module);
    $edit=request('/'.$path.'/editar?id='.$id,$digitador);
    ensure($edit['status']===200 && str_contains($edit['body'],'name="version" value="1"'),'Edit includes current version '.$module);
    $updated=$payload+['id'=>$id,'version'=>1];
    if ($module==='documents') $updated['details'][0]['cantidad']='3.500';
    else $updated['detalle'][0]['cantidad']='3.500';
    ensure(request('/'.$path.'/actualizar',$digitador,$updated)['status']===302,'HTML draft update '.$module);
    $record=$repo->find($id);
    ensure((int)$record['header']['version']===2 && $record['details'][0]['cantidad']==='3.500','Update persisted once '.$module);
    ensure(request('/'.$path.'/actualizar',$digitador,$updated,$jsonHeaders)['status']===409,'Stale update rejected '.$module);
    ensure(request('/'.$path.'/eliminar',$digitador,['_csrf'=>$captureToken,'id'=>$id,'version'=>1],$jsonHeaders)['status']===409,'Stale deletion rejected '.$module);
    $missingVersion=$updated; unset($missingVersion['version']);
    ensure(request('/'.$path.'/actualizar',$digitador,$missingVersion,$jsonHeaders)['status']===422,'Missing edit version rejected '.$module);
    $noCsrf=$updated; unset($noCsrf['_csrf']);
    ensure(request('/'.$path.'/actualizar',$digitador,$noCsrf)['status']===419,'Edit requires CSRF '.$module);
    ensure(request('/'.$path.'/eliminar',$digitador,['id'=>$id,'version'=>2])['status']===419,'Delete requires CSRF '.$module);

    $foreignHeader=match($module){'documents'=>docHeader('P0-FOREIGN-DOC'),'guides'=>guideHeader('P0-FOREIGN-GUIDE'),'stock'=>stockHeader('p0-foreign-stock','2026-10-02')};
    $foreign=$repo->create($foreignHeader,$detail,$ids['ADMIN']);
    foreach (['ver','editar'] as $action) ensure(request('/'.$path.'/'.$action.'?id='.$foreign,$digitador)['status']===403,'Foreign record denied '.$module.' '.$action);
    $foreignUpdate=$updated; $foreignUpdate['id']=$foreign;
    ensure(request('/'.$path.'/actualizar',$digitador,$foreignUpdate,$jsonHeaders)['status']===403,'Foreign update denied '.$module);
    ensure(request('/'.$path.'/eliminar',$digitador,['_csrf'=>$captureToken,'id'=>$foreign,'version'=>1],$jsonHeaders)['status']===403,'Foreign deletion denied '.$module);
    ensure($repo->find($foreign)!==null,'Denied delete preserves foreign data '.$module);

    foreach (['SUPERVISOR'=>$supervisor,'GERENCIA'=>$gerencia,'BAYER'=>$external] as $role=>$client) {
        ensure(request('/'.$path.'/editar?id='.$id,$client)['status']===403,'Noncapturing role cannot edit '.$role.' '.$module);
        ensure(request('/'.$path.'/eliminar',$client,['id'=>$id,'version'=>2])['status']===403,'Noncapturing role cannot delete '.$role.' '.$module);
    }
    $workflow=['_csrf'=>$reviewToken,'id'=>$id,'version'=>2,'status'=>'VALIDADO'];
    ensure(request('/'.$path.'/estado',$supervisor,['id'=>$id,'version'=>2,'status'=>'VALIDADO'])['status']===419,'Workflow requires CSRF '.$module);
    $missingVersion=$workflow; unset($missingVersion['version']);
    ensure(request('/'.$path.'/estado',$supervisor,$missingVersion,$jsonHeaders)['status']===422,'Workflow never falls back to DB version '.$module);
    $stale=$workflow; $stale['version']=1;
    ensure(request('/'.$path.'/estado',$supervisor,$stale,$jsonHeaders)['status']===409,'Workflow stale version rejected '.$module);
    $reviewPage=request('/'.$path.'/ver?id='.$id,$supervisor);
    ensure($reviewPage['status']===200 && str_contains($reviewPage['body'],'name="version" value="2"'),'Workflow UI submits version '.$module);
    $expectedVersion=2;
    foreach (['VALIDADO','OBSERVADO','BORRADOR','VALIDADO','PUBLICADO','ANULADO'] as $target) {
        $workflow['version']=$expectedVersion; $workflow['status']=$target;
        $workflow['reason']=in_array($target,['OBSERVADO','ANULADO'],true)?'P0 reason':'';
        if ($workflow['reason']!=='') {
            $noReason=$workflow; unset($noReason['reason']);
            ensure(request('/'.$path.'/estado',$supervisor,$noReason,$jsonHeaders)['status']===422,'Observation/cancellation requires reason '.$module.' '.$target);
        }
        $r=request('/'.$path.'/estado',$supervisor,$workflow,$jsonHeaders);
        ensure($r['status']===200,'Integrated transition '.$module.' '.$target.' (HTTP '.$r['status'].')');
        ++$expectedVersion;
        $record=$repo->find($id);
        ensure($record['header']['estado_registro']===$target && (int)$record['header']['version']===$expectedVersion,'Transition state/version '.$module.' '.$target);
        if ($target==='OBSERVADO') ensure($record['header']['observation_reason']==='P0 reason','Observation reason persisted '.$module);
        if ($target==='ANULADO') ensure($record['header']['cancellation_reason']==='P0 reason','Cancellation reason persisted '.$module);
    }
    ensure(request('/'.$path.'/editar?id='.$id,$digitador)['status']===409,'Non-draft editing rejected '.$module);
    ensure(request('/'.$path.'/eliminar',$digitador,['_csrf'=>$captureToken,'id'=>$id,'version'=>$expectedVersion],$jsonHeaders)['status']===409,'Non-draft deletion rejected '.$module);

    // Use the Day 2 own draft to verify a successful authorized deletion.
    $ownId=match($module){'documents'=>$docs->findByNumber(1,'HTTP-D2-DOC')['header']['id'],'guides'=>$guides->findByNumber('HTTP-D2-GUIDE')['header']['id'],'stock'=>$stocks->findByIdempotencyKey('http-day2-stock')['header']['id']};
    $r=request('/'.$path.'/eliminar',$digitador,['_csrf'=>$captureToken,'id'=>$ownId,'version'=>1],$jsonHeaders);
    ensure($r['status']===200 && $repo->find((int)$ownId)===null,'Own draft deletion '.$module);
}

// Live workflow permission revocation must affect the existing authenticated session.
$draft=$docs->create(docHeader('P0-WORKFLOW-ROLLBACK'),lines(),$ids['ADMIN']);
$workflow=['_csrf'=>$reviewToken,'id'=>$draft,'version'=>1,'status'=>'VALIDADO'];
$pdo->exec("DELETE rp FROM rol_permiso rp JOIN permisos p ON p.id=rp.permiso_id WHERE rp.rol_id=12 AND p.codigo='validation.review'");
ensure(request('/documentos/estado',$supervisor,$workflow,$jsonHeaders)['status']===403,'Workflow requires persisted review permission');
$pdo->exec("INSERT INTO rol_permiso(rol_id,permiso_id) SELECT 12,id FROM permisos WHERE codigo='validation.review'");

// Preserve existing atomic state/validation writes and propagate internal failures as safe 500.
$pdo->exec('RENAME TABLE validaciones TO validaciones_unavailable');
try {
    $r=request('/documentos/estado',$supervisor,$workflow,$jsonHeaders);
    ensure($r['status']===500 && !str_contains($r['body'],'SQLSTATE'),'Workflow persistence failure is sanitized 500');
    $record=$docs->find($draft);
    ensure($record['header']['estado_registro']==='BORRADOR' && (int)$record['header']['version']===1,'Failed workflow rolls back state and version');
} finally { $pdo->exec('RENAME TABLE validaciones_unavailable TO validaciones'); }

ensure(request('/validacion',$supervisor)['status']===200,'Integrated backoffice uses Operations repositories');
ensure(request('/export?type=documents&format=csv',$supervisor)['status']===422,'Retired CSV is not silently restored');
ensure(request('/export/count?type=documents',$supervisor)['status']===200,'Export count route exists');
ensure(request('/exportaciones',$supervisor)['status']===200,'Integrated exports screen exists');
foreach (['json','txt','xlsx','pdf'] as $format) {
    $r=request('/export?type=documents&format='.$format,$supervisor);
    ensure($r['status']===200 && str_contains($r['headers'],'attachment;'),'Integrated download '.$format);
    if ($format==='json' || $format==='txt') ensure(str_contains($r['body'],'PUBLICADO-ONLY') && !str_contains($r['body'],'BORRADOR-ONLY'),'Export only published '.$format);
    if ($format==='xlsx') ensure(str_starts_with($r['body'],'PK'),'XLSX archive');
    if ($format==='pdf') ensure(str_starts_with($r['body'],'%PDF'),'PDF document');
}
ensure(!preg_match('/PHP (Warning|Fatal error|Deprecated|Parse error):/',file_get_contents($temp.'/server.log')),'No PHP runtime warnings in HTTP suite');
echo 'Reconciliation HTTP subset: '.($GLOBALS['checks']-$startReconciliation)." checks OK (included in suite total)\n";

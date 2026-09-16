<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Repositories\MasterDataRepository;
use App\Services\{DocumentScreenService,OperationalService};
use App\Validators\OperationalValidator;

abstract class OperationalController
{
    protected const MODULE = '';
    protected const PATH = '';

    private function service(): OperationalService { return new OperationalService(static::MODULE); }
    private function actor(): array { \require_auth(); return \auth_user(); }
    private function wantsJson(): bool { return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'); }
    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }

    public function index(): void
    {
        $rows = $this->service()->listing($this->actor(),$_GET);
        if ($this->wantsJson()) { $this->json(['items'=>$rows]); return; }
        // Preserve the integrated document search/pagination presentation adapter.
        $data = static::MODULE === 'documents' ? (new DocumentScreenService())->listing($_GET)
            : ['rows'=>$rows,'filters'=>$_GET]+$this->catalogs();
        \view(static::PATH.'.index',$data);
    }

    public function create(): void
    {
        $context = $this->service()->captureContext($this->actor());
        if ($this->wantsJson()) { $context['_csrf']=\csrf_token(); $this->json($context); return; }
        $this->form(['idempotency_key'=>$context['idempotency_key']],[]);
    }

    public function show(): void
    {
        $record=$this->service()->show($this->actor(),$_GET['id'] ?? null);
        if ($this->wantsJson()) { $this->json($record); return; }
        if (static::MODULE === 'documents') {
            $data=['document'=>$record['header'],'details'=>$record['details'],'catalogs'=>(new DocumentScreenService())->catalogs()];
        } else {
            $data=['record'=>$record];
            foreach ($this->catalogs() as $key=>$rows) $data[$key]=\index_by($rows,'id');
        }
        \view(static::PATH.'.show',$data);
    }

    public function edit(): void
    {
        $record=$this->service()->editable($this->actor(),$_GET['id'] ?? null);
        if ($this->wantsJson()) { $this->json($record); return; }
        $this->form($record['header'],$record['details'],true);
    }

    public function store(): void { $this->save(false); }
    public function update(): void { $this->save(true); }

    private function save(bool $editing): void
    {
        $service=$this->service(); $user=$this->actor();
        $service->captureContext($user);
        $id=$editing ? OperationalValidator::id($_POST['id'] ?? null) : null;
        $version=$editing ? OperationalValidator::id($_POST['version'] ?? null) : null;
        if ($editing) $service->editable($user,$id);
        $input=OperationalValidator::formPayload(static::MODULE,$_POST,$editing);
        try {
            if ($editing) $service->update($user,$id,$version,$input);
            else $id=$service->create($user,$input);
        } catch (ValidationException $error) {
            if ($this->wantsJson()) throw $error;
            http_response_code(422);
            $header=is_array($input['header'] ?? null) ? $input['header'] : [];
            $details=is_array($input['details'] ?? null) ? $input['details'] : [];
            $this->form(array_merge($header,['id'=>$id,'version'=>$version]),$details,$editing,$error->errors);
            return;
        }
        \audit(static::PATH,$editing?'editar':'crear',$id);
        $link='/'.static::PATH.'/ver?id='.$id;
        if ($this->wantsJson()) {
            header('Location: '.\url($link));
            $this->json(['id'=>$id],$editing?200:201);
        } else {
            \flash('success','Carga guardada en borrador.');
            \redirect($link);
        }
    }

    public function destroy(): void
    {
        $id=OperationalValidator::id($_POST['id'] ?? null);
        $this->service()->delete($this->actor(),$id,$_POST['version'] ?? null);
        \audit(static::PATH,'eliminar',$id);
        if ($this->wantsJson()) { $this->json(['id'=>$id]); return; }
        \flash('success','Borrador eliminado.'); \redirect('/'.static::PATH);
    }

    public function changeStatus(): void
    {
        $id=OperationalValidator::id($_POST['id'] ?? null);
        $version=$this->service()->transition($this->actor(),$id,$_POST['version'] ?? null,$_POST['status'] ?? null,$_POST['reason'] ?? '');
        \audit(static::PATH,'estado_'.strtoupper(trim($_POST['status'])),$id);
        if ($this->wantsJson()) { $this->json(['id'=>$id,'version'=>$version]); return; }
        \flash('success','Estado actualizado.'); \redirect('/'.static::PATH.'/ver?id='.$id);
    }

    public function masters(): void { $this->json(['items'=>$this->service()->masters($this->actor(),$_GET)]); }

    private function catalogs(): array
    {
        // Existing read-only presentation catalogs; capture validation uses Pedro's Masters repository.
        $master=new MasterDataRepository();
        $keys=static::MODULE==='stock' ? ['almacenes','productos','unidades','lotes']
            : ['clientes','vendedores','sucursales','productos','unidades','departamentos','provincias','distritos'];
        $result=[];
        foreach ($keys as $key) $result[$key]=$master->$key();
        return $result;
    }

    private function form(array $header,array $details,bool $editing=false,array $errors=[]): void
    {
        if (static::MODULE==='documents') {
            \view('documentos.form',compact('header','details','editing','errors')+['catalogs'=>(new DocumentScreenService())->catalogs()]);
            return;
        }
        // Normalize only redisplayed values. Never coerce unvalidated input before saving.
        $_SESSION['_old']=array_map(static fn($v)=>is_scalar($v)?$v:'',$header);
        $_SESSION['_errors']=[];
        foreach ($errors as $key=>$message) $_SESSION['_errors'][str_starts_with($key,'header.')?substr($key,7):$key]=$message;
        $detailRows=[];
        foreach ($details as $line) {
            $row=[];
            foreach (['producto_id','unidad_id','cantidad','lote_id'] as $key) $row[$key]=is_scalar($line[$key] ?? null)?$line[$key]:'';
            $detailRows[]=$row;
        }
        $data=$this->catalogs()+['errors'=>$errors];
        if ($detailRows) $data['detailRows']=$detailRows;
        if ($editing) $data['record']=['header'=>$header,'details'=>$detailRows];
        try { \view(static::PATH.'.form',$data); }
        finally { unset($_SESSION['_old'],$_SESSION['_errors']); }
    }
}

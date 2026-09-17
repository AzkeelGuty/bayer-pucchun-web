<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Exceptions\HttpException;
use App\Policies\OperationalPermissionPolicy;
use App\Repositories\DocumentRepository;
use App\Services\{DocumentScreenService,WorkflowService};

final class DocumentController
{
    public function __construct(private ?DocumentRepository $repository=null) { $this->repository??=new DocumentRepository(); }
    private function record(int $id): array {
        if($id<1||!($record=$this->repository->find($id))) throw new HttpException(404,'Documento no encontrado.');
        if(\has_role('DIGITADOR')&&!\has_role('ADMIN','SUPERVISOR','GERENCIA')&&(int)$record['header']['created_by']!==(int)\auth_user()['id']) throw new HttpException(403,'No tiene acceso a este documento.');
        return $record;
    }
    public function index(): void {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('documents.read');
        \view('documentos.index',(new DocumentScreenService())->listing($_GET));
    }
    public function create(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('documents.create'); $this->form([],[]); }
    public function edit(): void {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('documents.create'); $r=$this->record((int)\input('id',0));
        if($r['header']['estado_registro']!=='BORRADOR') throw new HttpException(409,'Solo se pueden editar borradores.');
        $this->form($r['header'],$r['details'],true);
    }
    public function show(): void {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('documents.read'); $r=$this->record((int)\input('id',0));
        \view('documentos.show',['document'=>$r['header'],'details'=>$r['details'],'catalogs'=>(new DocumentScreenService())->catalogs()]);
    }
    private function form(array $header,array $details,bool $editing=false,array $errors=[]): void {
        \view('documentos.form',compact('header','details','editing','errors')+['catalogs'=>(new DocumentScreenService())->catalogs()]);
    }
    public function store(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('documents.create'); $this->save(false); }
    public function update(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('documents.create'); $this->save(true); }
    private function save(bool $editing): void {
        $header=is_array($_POST['header']??null)?$_POST['header']:[];
        $details=is_array($_POST['details']??null)?array_values($_POST['details']):[];
        $id=(int)\input('id',0); $version=(int)\input('version',0);
        if($editing) { $r=$this->record($id); if($r['header']['estado_registro']!=='BORRADOR'||(int)$r['header']['version']!==$version) throw new HttpException(409,'El documento cambió. Abre de nuevo su detalle antes de editar.'); }
        $screen=new DocumentScreenService(); $errors=$screen->validate($header,$details,$screen->catalogs());
        if(!$errors) {
            $duplicate=$this->repository->findByNumber((int)$header['tipo_documento_id'],trim($header['numero']));
            if($duplicate&&(!$editing||(int)$duplicate['header']['id']!==$id)) $errors['header.numero']='VAL-004: ya existe ese número para el tipo seleccionado.';
        }
        if($errors) { http_response_code(422); $this->form(array_merge($header,['id'=>$id,'version'=>$version]),$details,$editing,$errors); return; }
        $header=array_intersect_key($header,array_flip(['tipo_documento_id','numero','fecha','cliente_id','vendedor_id','sucursal_id']));
        $lines=[]; foreach($details as $line) $lines[]=array_intersect_key($line+['valor_unitario'=>'0'],array_flip(['producto_id','unidad_id','cantidad','valor_unitario']));
        try {
            if($editing) $this->repository->updateDraft($id,$version,$header,$lines,(int)\auth_user()['id']);
            else $id=$this->repository->create($header,$lines,(int)\auth_user()['id']);
        } catch(\Throwable $error) {
            \log_event('document_screen_save_error',['type'=>get_class($error)]);
            http_response_code(409); $this->form(array_merge($header,['id'=>$id,'version'=>$version]),$details,$editing,['save'=>'No se pudo guardar. Revisa duplicados o cambios simultáneos y vuelve a intentarlo.']); return;
        }
        \audit('documentos',$editing?'editar':'crear',$id); \flash('success','Documento guardado en borrador.'); \redirect('/documentos/ver?id='.$id);
    }

    public function destroy(): void {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('documents.create');
        $id=(int)\input('id',0);
        $version=(int)\input('version',0);
        try{
            $this->repository->deleteDraft($id,$version,(int)\auth_user()['id']);
            \audit('documentos','eliminar',$id);
            \flash('success','Documento en borrador eliminado.');
        }catch(\Throwable $error){
            throw new HttpException(409,'No se pudo eliminar. Actualice la página y verifique que siga en borrador.');
        }
        \redirect('/documentos');
    }

    public function changeStatus(): void {
        \require_role('ADMIN','SUPERVISOR');
        $id=(int)\input('id');
        $version=(int)\input('version');
        $status=strtoupper(trim((string)\input('status')));
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('documentos','estado_'.$status,$id);
        \flash('success','Estado del documento actualizado.');
        \redirect('/documentos/ver?id='.$id);
    }
}

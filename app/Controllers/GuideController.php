<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\OperationalPermissionPolicy;
use App\Policies\OperationalOwnershipPolicy;
use App\Repositories\{GuideRepository,MasterDataRepository};
use App\Services\{OperationalNumberingService,WorkflowService};
use App\Validators\WorkflowValidator;

final class GuideController
{
    public function __construct(private ?GuideRepository $repository=null)
    {
        $this->repository ??= new GuideRepository();
    }

    private function masters(): MasterDataRepository { return new MasterDataRepository(); }

    private function record(int $id): array
    {
        $record = $id > 0 ? $this->repository->find($id) : null;
        if (!$record) throw new HttpException(404, 'Guía no encontrada.');
        OperationalOwnershipPolicy::require($record['header']);
        return $record;
    }

    private function filters(): array
    {
        $filters=[];
        $estado=trim((string)\input('estado_registro',''));
        if(in_array($estado,['BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO'],true)) $filters['estado_registro']=$estado;
        foreach(['fecha_desde','fecha_hasta'] as $key){
            $value=trim((string)\input($key,''));
            if($value!=='') $filters[$key]=$value;
        }
        $sucursal=(string)\input('sucursal_id','');
        if(ctype_digit($sucursal) && (int)$sucursal>0) $filters['sucursal_id']=(int)$sucursal;
        return OperationalOwnershipPolicy::scope($filters);
    }

    private function viewData(): array
    {
        $m=$this->masters();
        return [
            'clientes'=>$m->clientes(),
            'vendedores'=>$m->vendedores(),
            'sucursales'=>$m->sucursales(),
            'productos'=>$m->productos(),
            'unidades'=>$m->unidades(),
            'departamentos'=>$m->departamentos(),
            'provincias'=>$m->provincias(),
            'distritos'=>$m->distritos(),
        ];
    }

    public function index(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('guides.read');
        $filters=$this->filters();
        \view('guias.index',[
            'rows'=>$this->repository->all($filters),
            'filters'=>$filters,
            'sucursales'=>$this->masters()->sucursales(),
        ]);
    }

    public function show(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('guides.read');
        $id=(int)\input('id',0);
        $record=$this->record($id);
        $m=$this->masters();
        \view('guias.show',[
            'record'=>$record,
            'clientes'=>\index_by($m->clientes(),'id'),
            'vendedores'=>\index_by($m->vendedores(),'id'),
            'sucursales'=>\index_by($m->sucursales(),'id'),
            'productos'=>\index_by($m->productos(),'id'),
            'unidades'=>\index_by($m->unidades(),'id'),
            'departamentos'=>\index_by($m->departamentos(),'id'),
            'provincias'=>\index_by($m->provincias(),'id'),
            'distritos'=>\index_by($m->distritos(),'id'),
        ]);
    }

    public function create(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create');
        \view('guias.form',$this->viewData()+[
            'autoNumber'=>(new OperationalNumberingService())->nextGuideNumber(),
            'defaultDate'=>date('Y-m-d'),
        ]);
    }

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create');
        $id=(int)\input('id',0);
        $record=$this->record($id);
        if(($record['header']['estado_registro']??'')!=='BORRADOR') throw new HttpException(409,'Solo se puede editar una guía en BORRADOR.');
        $_SESSION['_old']=[
            'numero'=>$record['header']['numero']??'',
            'fecha'=>$record['header']['fecha']??'',
            'cliente_id'=>$record['header']['cliente_id']??'',
            'vendedor_id'=>$record['header']['vendedor_id']??'',
            'sucursal_id'=>$record['header']['sucursal_id']??'',
            'departamento_id'=>$record['header']['departamento_id']??'',
            'provincia_id'=>$record['header']['provincia_id']??'',
            'distrito_id'=>$record['header']['distrito_id']??'',
        ];
        \view('guias.form',$this->viewData()+['record'=>$record]);
    }

    public function store(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create'); $this->save(false); }
    public function update(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create'); $this->save(true); }

    private function save(bool $editing): void
    {
        if ($editing) $this->record((int)\input('id',0));
        $details=is_array($_POST['detalle']??null)?array_values($_POST['detalle']):[];
        $numberMode=(!$editing && (string)\input('number_mode','auto')==='manual')?'manual':'auto';
        $header=[
            'numero'=>trim((string)\input('numero','')),
            'fecha'=>(string)\input('fecha',''),
            'cliente_id'=>(int)\input('cliente_id',0),
            'vendedor_id'=>(int)\input('vendedor_id',0),
            'sucursal_id'=>(int)\input('sucursal_id',0),
            'departamento_id'=>\input('departamento_id','')!==''?(int)\input('departamento_id'):null,
            'provincia_id'=>\input('provincia_id','')!==''?(int)\input('provincia_id'):null,
            'distrito_id'=>\input('distrito_id','')!==''?(int)\input('distrito_id'):null,
        ];
        if(!$editing && $numberMode==='auto'){
            $header['numero']=(new OperationalNumberingService())->nextGuideNumber();
        }
        $errors=[];
        foreach(['numero','fecha'] as $k) if(trim((string)$header[$k])==='') $errors[$k]='Campo obligatorio';
        foreach(['cliente_id','vendedor_id','sucursal_id'] as $k) if((int)$header[$k]<1) $errors[$k]='Seleccione una opción válida';
        if(!$details) $errors['detalle']='Agregue al menos una línea.';
        foreach($details as $i=>$line){
            if(!is_array($line) || (int)($line['producto_id']??0)<1 || (int)($line['unidad_id']??0)<1 || (float)($line['cantidad']??0)<=0){
                $errors['detalle']="Revise la línea ".($i+1)." del detalle.";
            }
        }
        if($errors){
            $_SESSION['_old']=$_POST; $_SESSION['_errors']=$errors;
            $target=$editing?'/guias/editar?id='.(int)\input('id',0):'/guias/nuevo';
            \redirect($target);
        }
        $lines=array_map(static fn(array $line):array=>[
            'producto_id'=>(int)$line['producto_id'],
            'unidad_id'=>(int)$line['unidad_id'],
            'cantidad'=>(string)$line['cantidad'],
        ],$details);
        try{
            if($editing){
                $id=(int)\input('id',0);
                $this->repository->updateDraft($id,(int)\input('version',0),$header,$lines,(int)\auth_user()['id']);
                \audit('guias','editar',$id); \flash('success','Guía actualizada correctamente.');
            }else{
                $id=$this->repository->create($header,$lines,(int)\auth_user()['id']);
                \audit('guias','crear',$id); \flash('success','Guía registrada en borrador.');
            }
        }catch(\Throwable $e){
            \log_event('guide_save_error',['type'=>get_class($e)]);
            throw new HttpException(422,'No se pudo guardar la guía. Revise los datos.');
        }
        \redirect('/guias/ver?id='.$id);
    }

    public function destroy(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create');
        $id=(int)\input('id',0);
        $this->record($id);
        try{
            $this->repository->deleteDraft($id,(int)\input('version',0),(int)\auth_user()['id']);
            \audit('guias','eliminar',$id); \flash('success','Guía en borrador eliminada.');
        }catch(\Throwable){ throw new HttpException(409,'No se pudo eliminar la guía.'); }
        \redirect('/guias');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $status=OperationalPermissionPolicy::workflow(\input('status',''));
        $id=(int)\input('id',0);
        $record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Guía no encontrada.');
        $version=WorkflowValidator::version($_POST['version'] ?? null);
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('guias','estado_'.$status,$id);
        \flash('success','Estado de la guía actualizado.');
        \redirect('/guias/ver?id='.$id);
    }
}

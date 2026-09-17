<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\OperationalPermissionPolicy;
use App\Repositories\{StockRepository,MasterDataRepository};
use App\Services\WorkflowService;
use App\Validators\WorkflowValidator;

final class StockController
{
    public function __construct(private ?StockRepository $repository=null)
    {
        $this->repository ??= new StockRepository();
    }

    private function masters(): MasterDataRepository { return new MasterDataRepository(); }

    private function filters(): array
    {
        $filters=[];
        $estado=trim((string)\input('estado_registro',''));
        if(in_array($estado,['BORRADOR','VALIDADO','PUBLICADO','OBSERVADO','ANULADO'],true)) $filters['estado_registro']=$estado;
        foreach(['fecha_desde','fecha_hasta'] as $key){
            $value=trim((string)\input($key,''));
            if($value!=='') $filters[$key]=$value;
        }
        $almacen=(string)\input('almacen_id','');
        if(ctype_digit($almacen) && (int)$almacen>0) $filters['almacen_id']=(int)$almacen;
        return $filters;
    }

    private function viewData(): array
    {
        $m=$this->masters();
        return ['almacenes'=>$m->almacenes(),'productos'=>$m->productos(),'unidades'=>$m->unidades(),'lotes'=>$m->lotes()];
    }

    public function index(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('stock.read');
        $filters=$this->filters();
        \view('stock.index',['rows'=>$this->repository->all($filters),'filters'=>$filters,'almacenes'=>$this->masters()->almacenes()]);
    }

    public function show(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('stock.read');
        $id=(int)\input('id',0); $record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Registro de stock no encontrado.');
        $m=$this->masters();
        \view('stock.show',['record'=>$record,'almacenes'=>\index_by($m->almacenes(),'id'),'productos'=>\index_by($m->productos(),'id'),'unidades'=>\index_by($m->unidades(),'id'),'lotes'=>\index_by($m->lotes(),'id')]);
    }

    public function create(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create'); \view('stock.form',$this->viewData()); }

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create');
        $id=(int)\input('id',0); $record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Stock no encontrado.');
        if(($record['header']['estado_registro']??'')!=='BORRADOR') throw new HttpException(409,'Solo se puede editar stock en BORRADOR.');
        $_SESSION['_old']=['fecha_stock'=>$record['header']['fecha_stock']??'','almacen_id'=>$record['header']['almacen_id']??'','idempotency_key'=>$record['header']['idempotency_key']??''];
        \view('stock.form',$this->viewData()+['record'=>$record]);
    }

    public function store(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create'); $this->save(false); }
    public function update(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create'); $this->save(true); }

    private function save(bool $editing): void
    {
        $details=is_array($_POST['detalle']??null)?array_values($_POST['detalle']):[];
        $header=[
            'fecha_stock'=>(string)\input('fecha_stock',''),
            'almacen_id'=>(int)\input('almacen_id',0),
            'idempotency_key'=>trim((string)\input('idempotency_key','')),
        ];
        $errors=[];
        if($header['fecha_stock']==='') $errors['fecha_stock']='Campo obligatorio';
        if($header['almacen_id']<1) $errors['almacen_id']='Seleccione un almacén.';
        if($header['idempotency_key']==='') $errors['idempotency_key']='Campo obligatorio';
        if(!$details) $errors['detalle']='Agregue al menos una línea.';
        foreach($details as $i=>$line){
            if(!is_array($line) || (int)($line['producto_id']??0)<1 || (int)($line['unidad_id']??0)<1 || (float)($line['cantidad']??0)<0){
                $errors['detalle']="Revise la línea ".($i+1)." del detalle.";
            }
        }
        if($errors){
            $_SESSION['_old']=$_POST; $_SESSION['_errors']=$errors;
            \redirect($editing?'/stock/editar?id='.(int)\input('id',0):'/stock/nuevo');
        }
        $lines=array_map(static fn(array $line):array=>[
            'producto_id'=>(int)$line['producto_id'],
            'lote_id'=>(isset($line['lote_id']) && $line['lote_id']!=='')?(int)$line['lote_id']:null,
            'unidad_id'=>(int)$line['unidad_id'],
            'cantidad'=>(string)$line['cantidad'],
        ],$details);
        try{
            if($editing){
                $id=(int)\input('id',0);
                $this->repository->updateDraft($id,(int)\input('version',0),$header,$lines,(int)\auth_user()['id']);
                \audit('stock','editar',$id); \flash('success','Stock actualizado correctamente.');
            }else{
                $id=$this->repository->create($header,$lines,(int)\auth_user()['id']);
                \audit('stock','crear',$id); \flash('success','Stock registrado en borrador.');
            }
        }catch(\Throwable $e){
            \log_event('stock_save_error',['type'=>get_class($e)]);
            throw new HttpException(422,'No se pudo guardar el stock. Revise los datos, lotes y cantidades.');
        }
        \redirect('/stock/ver?id='.$id);
    }

    public function destroy(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create');
        $id=(int)\input('id',0);
        try{
            $this->repository->deleteDraft($id,(int)\input('version',0),(int)\auth_user()['id']);
            \audit('stock','eliminar',$id); \flash('success','Stock en borrador eliminado.');
        }catch(\Throwable){ throw new HttpException(409,'No se pudo eliminar el stock.'); }
        \redirect('/stock');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $id=(int)\input('id',0); $record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Stock no encontrado.');
        $version=WorkflowValidator::version($_POST['version'] ?? null);
        $status=strtoupper(trim((string)\input('status','')));
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('stock','estado_'.$status,$id); \flash('success','Estado del stock actualizado.');
        \redirect('/stock/ver?id='.$id);
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\OperationalPermissionPolicy;
use App\Policies\OperationalOwnershipPolicy;
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

    private function record(int $id): array
    {
        $record = $id > 0 ? $this->repository->find($id) : null;
        if (!$record) throw new HttpException(404, 'Stock no encontrado.');
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
        $almacen=(string)\input('almacen_id','');
        if(ctype_digit($almacen) && (int)$almacen>0) $filters['almacen_id']=(int)$almacen;
        return OperationalOwnershipPolicy::scope($filters);
    }

    private function viewData(?array $record=null): array
    {
        $m=$this->masters();
        $old=is_array($_SESSION['_old']??null)?$_SESSION['_old']:[];
        $header=is_array($record['header']??null)?$record['header']:$old;
        $details=is_array($record['details']??null)?$record['details']:(is_array($old['detalle']??null)?$old['detalle']:[]);
        $productIds=[];$lotIds=[];
        foreach($details as $line){
            if(!is_array($line)) continue;
            $productIds[]=(int)($line['producto_id']??0);
            $lotIds[]=(int)($line['lote_id']??0);
        }
        return [
            'almacenes'=>$m->almacenesByIds([(int)($header['almacen_id']??0)]),
            'productos'=>$m->productosByIds($productIds),
            'unidades'=>$m->unidades(),
            'lotes'=>$m->lotesByIds($lotIds),
        ];
    }

    public function index(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('stock.read');
        $filters=$this->filters();
        $perPage=15;
        $total=$this->repository->countFiltered($filters);
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($pages,max(1,(int)\input('page',1)));
        \view('stock.index',[
            'rows'=>$this->repository->all($filters,$perPage,($page-1)*$perPage),
            'filters'=>$filters,
            'total'=>$total,
            'page'=>$page,
            'pages'=>$pages,
            'perPage'=>$perPage,
            'almacenes'=>$this->masters()->almacenesByIds([(int)($filters['almacen_id']??0)]),
            'bulkCounts'=>\has_role('ADMIN','SUPERVISOR') ? [
                'BORRADOR'=>$this->repository->countByState('BORRADOR'),
                'VALIDADO'=>$this->repository->countByState('VALIDADO'),
            ] : ['BORRADOR'=>0,'VALIDADO'=>0],
        ]);
    }

    public function show(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA'); OperationalPermissionPolicy::require('stock.read');
        $id=(int)\input('id',0); $record=$this->record($id);
        $m=$this->masters();
        $productIds=[];$lotIds=[];
        foreach($record['details'] as $line){
            $productIds[]=(int)($line['producto_id']??0);
            $lotIds[]=(int)($line['lote_id']??0);
        }
        \view('stock.show',[
            'record'=>$record,
            'almacenes'=>\index_by($m->almacenesByIds([(int)$record['header']['almacen_id']]),'id'),
            'productos'=>\index_by($m->productosByIds($productIds),'id'),
            'unidades'=>\index_by($m->unidades(),'id'),
            'lotes'=>\index_by($m->lotesByIds($lotIds),'id'),
        ]);
    }

    public function create(): void {
        \require_role('ADMIN','DIGITADOR');
        OperationalPermissionPolicy::require('stock.create');

        if(!isset($_SESSION['_old']['almacen_id'])){
            $st=\db()->prepare('SELECT almacen_id FROM stock_cabecera WHERE created_by=? ORDER BY id DESC LIMIT 1');
            $st->execute([(int)\auth_user()['id']]);
            $lastWarehouse=(int)($st->fetchColumn()?:0);
            if($lastWarehouse>0) $_SESSION['_old']['almacen_id']=$lastWarehouse;
        }

        \view('stock.form',$this->viewData(null)+[
            'defaultDate'=>date('Y-m-d'),
            'defaultIdempotency'=>'stock-'.date('Ymd-His').'-'.bin2hex(random_bytes(6)),
        ]);
    }

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create');
        $id=(int)\input('id',0); $record=$this->record($id);
        if(!in_array((string)($record['header']['estado_registro']??''),['BORRADOR','OBSERVADO'],true)) throw new HttpException(409,'Solo se puede editar stock en borrador u observado.');
        $_SESSION['_old']=['fecha_stock'=>$record['header']['fecha_stock']??'','almacen_id'=>$record['header']['almacen_id']??'','idempotency_key'=>$record['header']['idempotency_key']??''];
        \view('stock.form',$this->viewData($record)+['record'=>$record]);
    }

    private function normalizeDetails(array $details): array
    {
        $productIds=[];
        foreach($details as $line) if(is_array($line)) $productIds[]=(int)($line['producto_id']??0);
        $products=[];
        foreach($this->masters()->productosByIds($productIds) as $row) $products[(int)$row['id']]=$row;
        $normalized=[];
        $positions=[];

        foreach($details as $line){
            if(!is_array($line)){
                $normalized[]=$line;
                continue;
            }

            $productId=(int)($line['producto_id']??0);
            $lotId=(isset($line['lote_id']) && $line['lote_id']!=='')?(int)$line['lote_id']:null;
            if($productId>0 && isset($products[$productId]['unidad_base_id'])){
                $line['unidad_id']=(int)$products[$productId]['unidad_base_id'];
            }

            $quantity=\quantity_integer_value($line['cantidad']??'',true);
            if($productId>0 && $quantity!==null){
                $key=$productId.':'.($lotId??0);
                if(isset($positions[$key])){
                    $pos=$positions[$key];
                    $normalized[$pos]['cantidad']=(string)((int)$normalized[$pos]['cantidad']+$quantity);
                    continue;
                }
                $line['cantidad']=(string)$quantity;
                $positions[$key]=count($normalized);
            }

            $normalized[]=$line;
        }

        return array_values($normalized);
    }

    public function store(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create'); $this->save(false); }
    public function update(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('stock.create'); $this->save(true); }

    private function save(bool $editing): void
    {
        if ($editing) $this->record((int)\input('id',0));
        $details=$this->normalizeDetails(is_array($_POST['detalle']??null)?array_values($_POST['detalle']):[]);
        $idempotencyKey=trim((string)\input('idempotency_key',''));
        if(!$editing && $idempotencyKey===''){
            $idempotencyKey='stock-'.date('Ymd-His').'-'.bin2hex(random_bytes(6));
        }
        $header=[
            'fecha_stock'=>(string)\input('fecha_stock',''),
            'almacen_id'=>(int)\input('almacen_id',0),
            'idempotency_key'=>$idempotencyKey,
        ];
        $errors=[];
        if($header['fecha_stock']==='') $errors['fecha_stock']='Campo obligatorio';
        if($header['almacen_id']<1) $errors['almacen_id']='Seleccione un almacén.';
        if($header['idempotency_key']==='') $errors['idempotency_key']='Campo obligatorio';
        if(!$details) $errors['detalle']='Agregue al menos una línea.';
        foreach($details as $i=>$line){
            if(!is_array($line)
                || (int)($line['producto_id']??0)<1
                || (int)($line['unidad_id']??0)<1
                || \quantity_integer_value($line['cantidad']??'',true)===null){
                $errors['detalle']="Revise la línea ".($i+1).": la cantidad debe ser un entero igual o mayor que cero.";
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
        $this->record($id);
        try{
            $this->repository->deleteDraft($id,(int)\input('version',0),(int)\auth_user()['id']);
            \audit('stock','eliminar',$id); \flash('success','Stock en borrador eliminado.');
        }catch(\Throwable){ throw new HttpException(409,'No se pudo eliminar el stock.'); }
        \redirect('/stock');
    }

    public function bulkStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $status=OperationalPermissionPolicy::workflow(\input('status',''));
        if(!in_array($status,['VALIDADO','PUBLICADO'],true)){
            throw new HttpException(422,'La acción masiva solo permite validar borradores o publicar validados.');
        }
        $result=(new WorkflowService())->bulkTransition($this->repository,$status,(int)\auth_user()['id']);
        \audit('stock','estado_masivo_'.strtolower($status));
        $label=$status==='VALIDADO'?'validados':'publicados';
        \flash('success',$result['success'].' registros de stock '.$label.'.'.($result['failed']>0?' '.$result['failed'].' no pudieron procesarse.':''));
        \redirect('/stock');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $status=OperationalPermissionPolicy::workflow(\input('status',''));
        $id=(int)\input('id',0); $record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Stock no encontrado.');
        $version=WorkflowValidator::version($_POST['version'] ?? null);
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('stock','estado_'.$status,$id); \flash('success','Estado del stock actualizado.');
        $returnTo=trim((string)\input('return_to',''));
        if(in_array($returnTo,['/validacion','/stock'],true)) \redirect($returnTo);
        \redirect('/stock/ver?id='.$id);
    }
}

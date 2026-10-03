<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Policies\OperationalPermissionPolicy;
use App\Policies\OperationalOwnershipPolicy;
use App\Repositories\{GuideRepository,MasterDataRepository};
use App\Services\WorkflowService;
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
            'bulkCounts'=>\has_role('ADMIN','SUPERVISOR') ? [
                'BORRADOR'=>$this->repository->countByState('BORRADOR'),
                'VALIDADO'=>$this->repository->countByState('VALIDADO'),
            ] : ['BORRADOR'=>0,'VALIDADO'=>0],
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
            'defaultDate'=>date('Y-m-d'),
        ]);
    }

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create');
        $id=(int)\input('id',0);
        $record=$this->record($id);
        if(!in_array((string)($record['header']['estado_registro']??''),['BORRADOR','OBSERVADO'],true)) throw new HttpException(409,'Solo se puede editar una guía en borrador u observada.');
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

    private function applyClientDefaults(array $header): array
    {
        $clients=[];
        foreach($this->masters()->clientes() as $row) $clients[(int)$row['id']]=$row;
        $client=$clients[(int)($header['cliente_id']??0)]??null;
        if(!$client) return $header;

        if((int)($header['vendedor_id']??0)<1 && (int)($client['vendedor_sugerido_id']??0)>0){
            $header['vendedor_id']=(int)$client['vendedor_sugerido_id'];
        }
        if((int)($header['sucursal_id']??0)<1 && (int)($client['sucursal_sugerida_id']??0)>0){
            $header['sucursal_id']=(int)$client['sucursal_sugerida_id'];
        }

        $clientGeo=[
            $client['departamento_id']??null,
            $client['provincia_id']??null,
            $client['distrito_id']??null,
        ];
        $currentGeo=[
            $header['departamento_id']??null,
            $header['provincia_id']??null,
            $header['distrito_id']??null,
        ];
        if(!in_array(null,$clientGeo,true) && in_array(null,$currentGeo,true)){
            [$header['departamento_id'],$header['provincia_id'],$header['distrito_id']]=$clientGeo;
        }

        return $header;
    }

    private function normalizeDetails(array $details): array
    {
        $products=[];
        foreach($this->masters()->productos() as $row) $products[(int)$row['id']]=$row;
        $normalized=[];
        $positions=[];

        foreach($details as $i=>$line){
            if(!is_array($line)){
                $normalized[]=$line;
                continue;
            }

            $productId=(int)($line['producto_id']??0);
            if($productId>0 && isset($products[$productId]['unidad_base_id'])){
                $line['unidad_id']=(int)$products[$productId]['unidad_base_id'];
            }

            $quantity=\quantity_integer_value($line['cantidad']??'',false);
            if($productId>0 && $quantity!==null){
                $key=(string)$productId;
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

    public function store(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create'); $this->save(false); }
    public function update(): void { \require_role('ADMIN','DIGITADOR'); OperationalPermissionPolicy::require('guides.create'); $this->save(true); }

    private function save(bool $editing): void
    {
        if ($editing) $this->record((int)\input('id',0));
        $details=$this->normalizeDetails(is_array($_POST['detalle']??null)?array_values($_POST['detalle']):[]);
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
        $header=$this->applyClientDefaults($header);
        $errors=[];
        foreach(['numero','fecha'] as $k) if(trim((string)$header[$k])==='') $errors[$k]='Campo obligatorio';
        foreach(['cliente_id','vendedor_id','sucursal_id'] as $k) if((int)$header[$k]<1) $errors[$k]='Seleccione una opción válida';
        $geo=[$header['departamento_id'],$header['provincia_id'],$header['distrito_id']];
        if($geo!==[null,null,null] && in_array(null,$geo,true)){
            foreach(['departamento_id','provincia_id','distrito_id'] as $k) $errors[$k]='Completa el destino de entrega.';
        }
        if(!$details) $errors['detalle']='Agregue al menos una línea.';
        foreach($details as $i=>$line){
            if(!is_array($line)
                || (int)($line['producto_id']??0)<1
                || (int)($line['unidad_id']??0)<1
                || \quantity_integer_value($line['cantidad']??'',false)===null){
                $errors['detalle']="Revise la línea ".($i+1).": la cantidad debe ser un entero mayor que cero.";
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

    public function saveClientLocation(): void
    {
        \require_role('ADMIN','DIGITADOR');
        OperationalPermissionPolicy::require('guides.create');

        $clientId=(int)\input('cliente_id',0);
        $departmentId=(int)\input('departamento_id',0);
        $provinceId=(int)\input('provincia_id',0);
        $districtId=(int)\input('distrito_id',0);
        if($clientId<1 || $departmentId<1 || $provinceId<1 || $districtId<1){
            throw new HttpException(422,'Seleccione cliente, departamento, provincia y distrito.');
        }

        try{
            $this->masters()->updateClientLocation($clientId,$departmentId,$provinceId,$districtId);
        }catch(\InvalidArgumentException $error){
            throw new HttpException(422,$error->getMessage());
        }catch(\RuntimeException $error){
            throw new HttpException(404,$error->getMessage());
        }
        \audit('clientes','actualizar_ubicacion',$clientId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'=>true,
            'message'=>'Ubicación vinculada al cliente. Se autocompletará en futuras guías.',
            'client'=>[
                'id'=>$clientId,
                'departamento_id'=>$departmentId,
                'provincia_id'=>$provinceId,
                'distrito_id'=>$districtId,
            ],
        ],JSON_UNESCAPED_UNICODE);
    }

    public function bulkStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $status=OperationalPermissionPolicy::workflow(\input('status',''));
        if(!in_array($status,['VALIDADO','PUBLICADO'],true)){
            throw new HttpException(422,'La acción masiva solo permite validar borradores o publicar validados.');
        }
        $result=(new WorkflowService())->bulkTransition($this->repository,$status,(int)\auth_user()['id']);
        \audit('guias','estado_masivo_'.strtolower($status));
        $label=$status==='VALIDADO'?'validadas':'publicadas';
        \flash('success',$result['success'].' guías '.$label.'.'.($result['failed']>0?' '.$result['failed'].' no pudieron procesarse.':''));
        \redirect('/guias');
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
        $returnTo=trim((string)\input('return_to',''));
        if(in_array($returnTo,['/validacion','/guias'],true)) \redirect($returnTo);
        \redirect('/guias/ver?id='.$id);
    }
}

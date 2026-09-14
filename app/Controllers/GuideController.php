<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Repositories\GuideRepository;
use App\Services\{MasterDataService,WorkflowService};
use App\Validators\BayerDataValidator;
use Throwable;

final class GuideController
{
    public function __construct(private ?GuideRepository $repository = null){$this->repository??=new GuideRepository();}

    public function index(): void {\require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA');\view('guias.index',['rows'=>$this->repository->all()]);}
    public function create(): void {\require_role('ADMIN','DIGITADOR');\view('guias.form',['mode'=>'create','defaults'=>[]]);}

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $id=(int)\input('id',0);$record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Guía no encontrada.');
        if(($record['header']['estado_registro']??'')!=='BORRADOR') throw new HttpException(409,'Solo se puede editar una guía en BORRADOR.');
        \view('guias.form',['mode'=>'edit','defaults'=>$this->defaults($id)]);
    }

    public function store(): void {\require_role('ADMIN','DIGITADOR');$this->save(false);}
    public function update(): void {\require_role('ADMIN','DIGITADOR');$this->save(true);}

    public function destroy(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $id=(int)\input('id',0);$version=(int)\input('version',0);
        try{$this->repository->deleteDraft($id,$version,(int)\auth_user()['id']);\audit('guias','eliminar',$id);\flash('success','Guía en borrador eliminada.');}
        catch(Throwable $e){throw new HttpException(409,'No se pudo eliminar. Verifique que siga en borrador.');}
        \redirect('/guias');
    }

    private function save(bool $editing): void
    {
        $data=$_POST;$errors=(new BayerDataValidator())->validate($data,'guides');
        if($errors){$_SESSION['_old']=$data;$_SESSION['_errors']=$errors;\redirect($editing?'/guias/editar?id='.urlencode((string)($data['id']??'')):'/guias/nuevo');}
        $pdo=\db();$pdo->beginTransaction();
        try{
            [$header,$details]=$this->payload($data);
            if($editing){$id=(int)$data['id'];$this->repository->updateDraft($id,(int)$data['version'],$header,$details,(int)\auth_user()['id']);\audit('guias','editar',$id);\flash('success','Guía actualizada.');}
            else{$id=$this->repository->create($header,$details,(int)\auth_user()['id']);\audit('guias','crear',$id);\flash('success','Guía guardada en borrador.');}
            $pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();\log_event('guide_save_error',['type'=>get_class($error)]);throw new HttpException(422,'No se pudo guardar la guía. Revise los datos o posibles duplicados.');}
        \redirect('/guias');
    }

    private function payload(array $data): array
    {
        $m=new MasterDataService();$company=$m->company($data);$geo=$m->district($data['department'],$data['province'],$data['district']);
        $branch=$m->branch($data,$company,$geo[2]);$client=$m->client($data,$geo);$seller=$m->seller($data);$unit=$m->unit($data);$product=$m->product($data,$unit);
        return [[
            'numero'=>trim((string)$data['documentNumber']),'fecha'=>$data['documentDate'],'cliente_id'=>$client,'vendedor_id'=>$seller,'sucursal_id'=>$branch,
            'departamento_id'=>$geo[0],'provincia_id'=>$geo[1],'distrito_id'=>$geo[2],
        ],[['producto_id'=>$product,'unidad_id'=>$unit,'cantidad'=>(string)$data['quantity']]]];
    }

    private function defaults(int $id): array
    {
        $st=\db()->prepare("SELECT gc.id,gc.version,e.ruc dealerId,e.razon_social dealerName,gc.numero documentNumber,DATE_FORMAT(gc.fecha,'%Y-%m-%d') documentDate,
            v.codigo salesId,TRIM(CONCAT(v.nombres,' ',COALESCE(v.apellidos,''))) salesName,s.codigo branchId,s.nombre branchName,
            c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,gd.cantidad quantity,
            dp.nombre department,pv.nombre province,ds.nombre district
            FROM guias_cabecera gc JOIN clientes c ON c.id=gc.cliente_id JOIN vendedores v ON v.id=gc.vendedor_id JOIN sucursales s ON s.id=gc.sucursal_id
            JOIN empresas e ON e.id=s.empresa_id JOIN guias_detalle gd ON gd.guia_id=gc.id JOIN productos pr ON pr.id=gd.producto_id
            JOIN unidades_medida um ON um.id=gd.unidad_id LEFT JOIN distritos ds ON ds.id=gc.distrito_id LEFT JOIN provincias pv ON pv.id=gc.provincia_id
            LEFT JOIN departamentos dp ON dp.id=gc.departamento_id WHERE gc.id=? ORDER BY gd.id LIMIT 1");
        $st->execute([$id]);$row=$st->fetch();if(!$row)throw new HttpException(404,'Guía no encontrada.');return $row;
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');$id=(int)\input('id');$version=(int)\input('version');$status=strtoupper(trim((string)\input('status')));$reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);\audit('guias','estado_'.$status,$id);\flash('success','Estado de la guía actualizado.');\redirect('/guias');
    }
}

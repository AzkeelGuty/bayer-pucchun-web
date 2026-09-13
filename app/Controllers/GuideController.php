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
    public function __construct(private ?GuideRepository $repository = null)
    {
        $this->repository ??= new GuideRepository();
    }

    public function index(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA');
        \view('guias.index',['rows'=>$this->repository->all()]);
    }

    public function create(): void
    {
        \require_role('ADMIN','DIGITADOR');
        \view('guias.form');
    }

    public function store(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $data=$_POST;
        $errors=(new BayerDataValidator())->validate($data,'guides');
        if($errors){
            $_SESSION['_old']=$data;
            $_SESSION['_errors']=$errors;
            \redirect('/guias/nuevo');
        }

        $pdo=\db();
        $pdo->beginTransaction();
        try{
            $masters=new MasterDataService();
            $company=$masters->company($data);
            $geo=$masters->district($data['department'],$data['province'],$data['district']);
            $branch=$masters->branch($data,$company,$geo[2]);
            $client=$masters->client($data,$geo);
            $seller=$masters->seller($data);
            $unit=$masters->unit($data);
            $product=$masters->product($data,$unit);

            $id=$this->repository->create(
                [
                    'numero'=>trim((string)$data['documentNumber']),
                    'fecha'=>$data['documentDate'],
                    'cliente_id'=>$client,
                    'vendedor_id'=>$seller,
                    'sucursal_id'=>$branch,
                    'departamento_id'=>$geo[0],
                    'provincia_id'=>$geo[1],
                    'distrito_id'=>$geo[2],
                ],
                [[
                    'producto_id'=>$product,
                    'unidad_id'=>$unit,
                    'cantidad'=>(string)$data['quantity'],
                ]],
                (int)\auth_user()['id']
            );
            $pdo->commit();
            \audit('guias','crear',$id);
            \flash('success','Guía guardada en borrador.');
        }catch(Throwable $error){
            if($pdo->inTransaction()) $pdo->rollBack();
            \log_event('guide_store_error',['type'=>get_class($error)]);
            throw new HttpException(422,'No se pudo registrar la guía. Revise los datos ingresados o posibles duplicados.');
        }
        \redirect('/guias');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $id=(int)\input('id');
        $version=(int)\input('version');
        $status=strtoupper(trim((string)\input('status')));
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('guias','estado_'.$status,$id);
        \flash('success','Estado de la guía actualizado.');
        \redirect('/guias');
    }
}

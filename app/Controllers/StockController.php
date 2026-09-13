<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Repositories\StockRepository;
use App\Services\{MasterDataService,WorkflowService};
use App\Validators\BayerDataValidator;
use Throwable;

final class StockController
{
    public function __construct(private ?StockRepository $repository = null)
    {
        $this->repository ??= new StockRepository();
    }

    public function index(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA');
        \view('stock.index',['rows'=>$this->repository->all()]);
    }

    public function create(): void
    {
        \require_role('ADMIN','DIGITADOR');
        \view('stock.form');
    }

    public function store(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $data=$_POST;
        $errors=(new BayerDataValidator())->validate($data,'stock');
        if($errors){
            $_SESSION['_old']=$data;
            $_SESSION['_errors']=$errors;
            \redirect('/stock/nuevo');
        }

        $pdo=\db();
        $pdo->beginTransaction();
        try{
            $masters=new MasterDataService();
            $company=$masters->company($data);
            $warehouse=$masters->warehouse($data,$company);
            $unit=$masters->unit($data);
            $product=$masters->product($data,$unit);
            $lot=$masters->lot($data,$product);

            $id=$this->repository->create(
                [
                    'fecha_stock'=>$data['stockDate'],
                    'almacen_id'=>$warehouse,
                    'idempotency_key'=>'web-'.bin2hex(random_bytes(16)),
                ],
                [[
                    'producto_id'=>$product,
                    'unidad_id'=>$unit,
                    'lote_id'=>$lot,
                    'cantidad'=>(string)$data['quantity'],
                ]],
                (int)\auth_user()['id']
            );
            $pdo->commit();
            \audit('stock','crear',$id);
            \flash('success','Registro de stock guardado en borrador.');
        }catch(Throwable $error){
            if($pdo->inTransaction()) $pdo->rollBack();
            \log_event('stock_store_error',['type'=>get_class($error)]);
            throw new HttpException(422,'No se pudo registrar el stock. Revise producto, lote, fechas y cantidades.');
        }
        \redirect('/stock');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $id=(int)\input('id');
        $version=(int)\input('version');
        $status=strtoupper(trim((string)\input('status')));
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('stock','estado_'.$status,$id);
        \flash('success','Estado del stock actualizado.');
        \redirect('/stock');
    }
}

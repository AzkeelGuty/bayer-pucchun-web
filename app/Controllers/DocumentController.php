<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Repositories\DocumentRepository;
use App\Services\{MasterDataService,WorkflowService};
use App\Validators\BayerDataValidator;
use Throwable;

final class DocumentController
{
    public function __construct(private ?DocumentRepository $repository = null)
    {
        $this->repository ??= new DocumentRepository();
    }

    public function index(): void
    {
        \require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA');
        \view('documentos.index',['rows'=>$this->repository->all()]);
    }

    public function create(): void
    {
        \require_role('ADMIN','DIGITADOR');
        \view('documentos.form');
    }

    public function store(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $data=$_POST;
        $errors=(new BayerDataValidator())->validate($data,'documents');
        if($errors){
            $_SESSION['_old']=$data;
            $_SESSION['_errors']=$errors;
            \redirect('/documentos/nuevo');
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
            $type=$masters->docType($data);

            $id=$this->repository->create(
                [
                    'tipo_documento_id'=>$type,
                    'numero'=>trim((string)$data['documentNumber']),
                    'fecha'=>$data['documentDate'],
                    'cliente_id'=>$client,
                    'vendedor_id'=>$seller,
                    'sucursal_id'=>$branch,
                ],
                [[
                    'producto_id'=>$product,
                    'unidad_id'=>$unit,
                    'cantidad'=>(string)$data['quantity'],
                    'valor_unitario'=>trim((string)($data['valorUnitario'] ?? '')) !== '' ? (string)$data['valorUnitario'] : '0',
                ]],
                (int)\auth_user()['id']
            );
            $pdo->commit();
            \audit('documentos','crear',$id);
            \flash('success','Documento guardado en borrador.');
        }catch(Throwable $error){
            if($pdo->inTransaction()) $pdo->rollBack();
            \log_event('document_store_error',['type'=>get_class($error)]);
            throw new HttpException(422,'No se pudo registrar el documento. Revise los datos ingresados o posibles duplicados.');
        }
        \redirect('/documentos');
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');
        $id=(int)\input('id');
        $version=(int)\input('version');
        $status=strtoupper(trim((string)\input('status')));
        $reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);
        \audit('documentos','estado_'.$status,$id);
        \flash('success','Estado del documento actualizado.');
        \redirect('/documentos');
    }
}

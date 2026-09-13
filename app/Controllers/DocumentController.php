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
        \view('documentos.form',['mode'=>'create','defaults'=>[]]);
    }

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $id=(int)\input('id',0);
        $record=$this->repository->find($id);
        if(!$record) throw new HttpException(404,'Documento no encontrado.');
        if(($record['header']['estado_registro']??'')!=='BORRADOR') throw new HttpException(409,'Solo se puede editar un documento en BORRADOR.');
        \view('documentos.form',['mode'=>'edit','defaults'=>$this->defaults($id)]);
    }

    public function store(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $this->save(false);
    }

    public function update(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $this->save(true);
    }

    public function destroy(): void
    {
        \require_role('ADMIN','DIGITADOR');
        $id=(int)\input('id',0);
        $version=(int)\input('version',0);
        try{
            $this->repository->deleteDraft($id,$version,(int)\auth_user()['id']);
            \audit('documentos','eliminar',$id);
            \flash('success','Documento en borrador eliminado.');
        }catch(Throwable $e){
            throw new HttpException(409,'No se pudo eliminar. Actualice la página y verifique que siga en borrador.');
        }
        \redirect('/documentos');
    }

    private function save(bool $editing): void
    {
        $data=$_POST;
        $errors=(new BayerDataValidator())->validate($data,'documents');
        if($errors){
            $_SESSION['_old']=$data;
            $_SESSION['_errors']=$errors;
            $target=$editing?'/documentos/editar?id='.urlencode((string)($data['id']??'')):'/documentos/nuevo';
            \redirect($target);
        }

        $pdo=\db();$pdo->beginTransaction();
        try{
            [$header,$details]=$this->payload($data);
            if($editing){
                $id=(int)($data['id']??0);$version=(int)($data['version']??0);
                $this->repository->updateDraft($id,$version,$header,$details,(int)\auth_user()['id']);
                \audit('documentos','editar',$id);
                \flash('success','Documento actualizado.');
            }else{
                $id=$this->repository->create($header,$details,(int)\auth_user()['id']);
                \audit('documentos','crear',$id);
                \flash('success','Documento guardado en borrador.');
            }
            $pdo->commit();
        }catch(Throwable $error){
            if($pdo->inTransaction()) $pdo->rollBack();
            \log_event('document_save_error',['type'=>get_class($error)]);
            throw new HttpException(422,'No se pudo guardar el documento. Revise los datos o posibles duplicados.');
        }
        \redirect('/documentos');
    }

    private function payload(array $data): array
    {
        $masters=new MasterDataService();
        $company=$masters->company($data);
        $geo=$masters->district($data['department'],$data['province'],$data['district']);
        $branch=$masters->branch($data,$company,$geo[2]);
        $client=$masters->client($data,$geo);
        $seller=$masters->seller($data);
        $unit=$masters->unit($data);
        $product=$masters->product($data,$unit);
        $type=$masters->docType($data);

        return [[
            'tipo_documento_id'=>$type,
            'numero'=>trim((string)$data['documentNumber']),
            'fecha'=>$data['documentDate'],
            'cliente_id'=>$client,
            'vendedor_id'=>$seller,
            'sucursal_id'=>$branch,
        ],[[
            'producto_id'=>$product,
            'unidad_id'=>$unit,
            'cantidad'=>(string)$data['quantity'],
            'valor_unitario'=>trim((string)($data['valorUnitario']??''))!==''?(string)$data['valorUnitario']:'0',
        ]]];
    }

    private function defaults(int $id): array
    {
        $st=\db()->prepare("SELECT dc.id,dc.version,e.ruc dealerId,e.razon_social dealerName,td.codigo documentTypeId,td.nombre documentType,
            dc.numero documentNumber,DATE_FORMAT(dc.fecha,'%Y-%m-%d') documentDate,v.codigo salesId,TRIM(CONCAT(v.nombres,' ',COALESCE(v.apellidos,''))) salesName,
            s.codigo branchId,s.nombre branchName,c.nro_doc customerId,c.razon_social customerName,pr.codigo materialId,pr.nombre materialName,
            um.codigo measureUnit,dd.cantidad quantity,dd.valor_unitario valorUnitario,dp.nombre department,pv.nombre province,ds.nombre district
            FROM documentos_cabecera dc JOIN tipos_documento td ON td.id=dc.tipo_documento_id JOIN clientes c ON c.id=dc.cliente_id
            JOIN vendedores v ON v.id=dc.vendedor_id JOIN sucursales s ON s.id=dc.sucursal_id JOIN empresas e ON e.id=s.empresa_id
            JOIN documentos_detalle dd ON dd.documento_id=dc.id JOIN productos pr ON pr.id=dd.producto_id JOIN unidades_medida um ON um.id=dd.unidad_id
            LEFT JOIN distritos ds ON ds.id=c.distrito_id LEFT JOIN provincias pv ON pv.id=c.provincia_id LEFT JOIN departamentos dp ON dp.id=c.departamento_id
            WHERE dc.id=? ORDER BY dd.id LIMIT 1");
        $st->execute([$id]);
        $row=$st->fetch();
        if(!$row) throw new HttpException(404,'Documento no encontrado.');
        return $row;
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

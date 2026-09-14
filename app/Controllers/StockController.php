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
    public function __construct(private ?StockRepository $repository = null){$this->repository??=new StockRepository();}
    public function index(): void {\require_role('ADMIN','DIGITADOR','SUPERVISOR','GERENCIA');\view('stock.index',['rows'=>$this->repository->all()]);}
    public function create(): void {\require_role('ADMIN','DIGITADOR');\view('stock.form',['mode'=>'create','defaults'=>[]]);}

    public function edit(): void
    {
        \require_role('ADMIN','DIGITADOR');$id=(int)\input('id',0);$record=$this->repository->find($id);
        if(!$record)throw new HttpException(404,'Stock no encontrado.');
        if(($record['header']['estado_registro']??'')!=='BORRADOR')throw new HttpException(409,'Solo se puede editar stock en BORRADOR.');
        \view('stock.form',['mode'=>'edit','defaults'=>$this->defaults($id)]);
    }

    public function store(): void {\require_role('ADMIN','DIGITADOR');$this->save(false);}
    public function update(): void {\require_role('ADMIN','DIGITADOR');$this->save(true);}
    public function destroy(): void
    {
        \require_role('ADMIN','DIGITADOR');$id=(int)\input('id',0);$version=(int)\input('version',0);
        try{$this->repository->deleteDraft($id,$version,(int)\auth_user()['id']);\audit('stock','eliminar',$id);\flash('success','Stock en borrador eliminado.');}
        catch(Throwable $e){throw new HttpException(409,'No se pudo eliminar. Verifique que siga en borrador.');}
        \redirect('/stock');
    }

    private function save(bool $editing): void
    {
        $data=$_POST;$errors=(new BayerDataValidator())->validate($data,'stock');
        if($errors){$_SESSION['_old']=$data;$_SESSION['_errors']=$errors;\redirect($editing?'/stock/editar?id='.urlencode((string)($data['id']??'')):'/stock/nuevo');}
        $pdo=\db();$pdo->beginTransaction();
        try{
            [$header,$details]=$this->payload($data,$editing);
            if($editing){$id=(int)$data['id'];$this->repository->updateDraft($id,(int)$data['version'],$header,$details,(int)\auth_user()['id']);\audit('stock','editar',$id);\flash('success','Stock actualizado.');}
            else{$id=$this->repository->create($header,$details,(int)\auth_user()['id']);\audit('stock','crear',$id);\flash('success','Registro de stock guardado en borrador.');}
            $pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();\log_event('stock_save_error',['type'=>get_class($error)]);throw new HttpException(422,'No se pudo guardar el stock. Revise producto, lote, fechas y cantidades.');}
        \redirect('/stock');
    }

    private function payload(array $data,bool $editing): array
    {
        $m=new MasterDataService();$company=$m->company($data);$warehouse=$m->warehouse($data,$company);$unit=$m->unit($data);$product=$m->product($data,$unit);$lot=$m->lot($data,$product);
        $key=$editing?(string)($data['idempotencyKey']??''):'web-'.bin2hex(random_bytes(16));
        return [['fecha_stock'=>$data['stockDate'],'almacen_id'=>$warehouse,'idempotency_key'=>$key],[['producto_id'=>$product,'unidad_id'=>$unit,'lote_id'=>$lot,'cantidad'=>(string)$data['quantity']]]];
    }

    private function defaults(int $id): array
    {
        $st=\db()->prepare("SELECT sc.id,sc.version,sc.idempotency_key idempotencyKey,e.ruc dealerId,e.razon_social dealerName,DATE_FORMAT(sc.fecha_stock,'%Y-%m-%d') stockDate,
            a.codigo warehouseId,a.nombre warehouseName,pr.codigo materialId,pr.nombre materialName,um.codigo measureUnit,l.codigo_lote batch,sd.cantidad quantity,
            DATE_FORMAT(l.fecha_vencimiento,'%Y-%m-%d') expirationDate
            FROM stock_cabecera sc JOIN almacenes a ON a.id=sc.almacen_id JOIN sucursales s ON s.id=a.sucursal_id JOIN empresas e ON e.id=s.empresa_id
            JOIN stock_detalle sd ON sd.stock_id=sc.id JOIN productos pr ON pr.id=sd.producto_id JOIN unidades_medida um ON um.id=sd.unidad_id
            LEFT JOIN lotes l ON l.id=sd.lote_id WHERE sc.id=? ORDER BY sd.id LIMIT 1");
        $st->execute([$id]);$row=$st->fetch();if(!$row)throw new HttpException(404,'Stock no encontrado.');return $row;
    }

    public function changeStatus(): void
    {
        \require_role('ADMIN','SUPERVISOR');$id=(int)\input('id');$version=(int)\input('version');$status=strtoupper(trim((string)\input('status')));$reason=trim((string)\input('reason',''));
        (new WorkflowService())->transition($this->repository,$id,$version,$status,(int)\auth_user()['id'],$reason);\audit('stock','estado_'.$status,$id);\flash('success','Estado del stock actualizado.');\redirect('/stock');
    }
}

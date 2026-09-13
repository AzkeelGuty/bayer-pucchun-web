<?php
namespace App\Controllers; use App\Repositories\Operations\StockRepository; use App\Validators\BayerDataValidator;
class StockController {private StockRepository $r;public function __construct(){$this->r=new StockRepository();}public function index(): void
    {
        \require_role(...\App\Policies\AccessPolicy::INTERNAL);
        // El alcance procede de la sesión; nunca de parámetros enviados por el navegador.
        $filters = [];
        if (\has_role('DIGITADOR') && !\has_role('ADMIN', 'SUPERVISOR', 'GERENCIA')) {
            $filters['created_by'] = (int) \auth_user()['id'];
        }
        \view('stock.index', ['rows' => $this->r->all($filters)]);
    }
    public function create():void{\require_role('ADMIN','DIGITADOR','SUPERVISOR');\view('stock.form');}public function store():void{\require_role('ADMIN','DIGITADOR','SUPERVISOR');$d=$_POST;$e=(new BayerDataValidator())->validate($d,'stock');if($e){$_SESSION['_old']=$d;$_SESSION['_errors']=$e;\redirect('/stock/nuevo');}try{$id=$this->r->create($d);\audit('stock','crear',$id);\flash('success','Stock registrado en borrador.');}catch(\Throwable $x){\log_event('stock_store_error',['e'=>$x->getMessage()]);\flash('error','No se pudo registrar el stock.');}\redirect('/stock');}public function changeStatus():void{\require_role('ADMIN','SUPERVISOR');$id=(int)\input('id');$s=(string)\input('status');if(!in_array($s,['BORRADOR','VALIDADO','PUBLICADO'],true))$s='BORRADOR';$this->r->status($id,$s);\audit('stock','estado_'.$s,$id);\redirect('/stock');}}

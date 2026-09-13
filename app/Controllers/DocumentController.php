<?php
namespace App\Controllers; use App\Repositories\Operations\DocumentRepository; use App\Validators\BayerDataValidator;
class DocumentController {private DocumentRepository $r;public function __construct(){$this->r=new DocumentRepository();}public function index(): void
    {
        \require_role(...\App\Policies\AccessPolicy::INTERNAL);
        // El alcance procede de la sesión; nunca de parámetros enviados por el navegador.
        $filters = [];
        if (\has_role('DIGITADOR') && !\has_role('ADMIN', 'SUPERVISOR', 'GERENCIA')) {
            $filters['created_by'] = (int) \auth_user()['id'];
        }
        \view('documentos.index', ['rows' => $this->r->all($filters)]);
    }
    public function create():void{\require_role('ADMIN','DIGITADOR','SUPERVISOR');\view('documentos.form');}public function store():void{\require_role('ADMIN','DIGITADOR','SUPERVISOR');$d=$_POST;$e=(new BayerDataValidator())->validate($d,'documents');if($e){$_SESSION['_old']=$d;$_SESSION['_errors']=$e;\redirect('/documentos/nuevo');}try{$id=$this->r->create($d);\audit('documentos','crear',$id);\flash('success','Documento registrado en borrador.');}catch(\Throwable $x){\log_event('document_store_error',['e'=>$x->getMessage()]);\flash('error','No se pudo registrar el documento. Verifique duplicados y datos.');}\redirect('/documentos');}public function changeStatus():void{\require_role('ADMIN','SUPERVISOR');$id=(int)\input('id');$s=(string)\input('status');if(!in_array($s,['BORRADOR','VALIDADO','PUBLICADO'],true))$s='BORRADOR';$this->r->status($id,$s);\audit('documentos','estado_'.$s,$id);\redirect('/documentos');}}

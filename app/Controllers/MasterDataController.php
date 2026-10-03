<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{MasterCatalogService,SimpleXlsxTable};

final class MasterDataController
{
    public function __construct(private ?MasterCatalogService $service=null)
    {
        $this->service ??= new MasterCatalogService();
    }

    private function tab(): string
    {
        $tab=strtolower(trim((string)\input('tab','clientes')));
        $this->service->definition($tab);
        return $tab;
    }

    public function create(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $meta=$this->service->definition($tab);
        $this->service->assertReady($tab);
        $record=is_array($_SESSION['_master_old']??null)?$_SESSION['_master_old']:[];
        $error=(string)($_SESSION['_master_error']??'');
        unset($_SESSION['_master_old'],$_SESSION['_master_error']);
        \view('backoffice.master_form',[
            'tab'=>$tab,'meta'=>$meta,'record'=>$record,'editing'=>false,
            'options'=>$this->service->formOptions($tab),'error'=>$error,
        ]);
    }

    public function edit(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $id=(int)\input('id',0);
        $meta=$this->service->definition($tab);
        $this->service->assertReady($tab);
        $record=$this->service->find($tab,$id);
        if(is_array($_SESSION['_master_old']??null)) $record=$_SESSION['_master_old'];
        $error=(string)($_SESSION['_master_error']??'');
        unset($_SESSION['_master_old'],$_SESSION['_master_error']);
        \view('backoffice.master_form',[
            'tab'=>$tab,'meta'=>$meta,'record'=>$record,'editing'=>true,'id'=>$id,
            'options'=>$this->service->formOptions($tab),'error'=>$error,
        ]);
    }

    public function store(): void
    {
        \require_role('ADMIN');
        $this->save(false);
    }

    public function update(): void
    {
        \require_role('ADMIN');
        $this->save(true);
    }

    private function save(bool $editing): void
    {
        $tab=$this->tab();
        $this->service->assertReady($tab);
        $id=$editing?(int)\input('id',0):null;
        $record=is_array($_POST['record']??null)?$_POST['record']:[];
        try{
            $saved=$this->service->save($tab,$record,$id);
            \audit('maestros',$editing?'editar_'.$tab:'crear_'.$tab,$saved);
            \flash('success',($editing?'Registro actualizado':'Registro agregado').' correctamente en '.$this->service->definition($tab)['title'].'.');
            \redirect('/maestros?tab='.rawurlencode($tab));
        }catch(HttpException $error){
            $_SESSION['_master_old']=$record;
            $_SESSION['_master_error']=$error->getMessage();
            $target=$editing?'/maestros/editar?tab='.rawurlencode($tab).'&id='.(int)$id:'/maestros/nuevo?tab='.rawurlencode($tab);
            \redirect($target);
        }
    }

    public function destroy(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $this->service->assertReady($tab);
        $id=(int)\input('id',0);
        try{
            $this->service->delete($tab,$id);
            \audit('maestros','eliminar_'.$tab,$id);
            \flash('success','Registro eliminado correctamente.');
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
        }
        \redirect('/maestros?tab='.rawurlencode($tab));
    }

    public function importPage(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $meta=$this->service->definition($tab);
        $this->service->assertReady($tab);
        \view('backoffice.master_import',['tab'=>$tab,'meta'=>$meta]);
    }

    public function import(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $this->service->assertReady($tab);
        try{
            $rows=(new SimpleXlsxTable())->readUpload($_FILES['excel']??[]);
            $result=$this->service->importRows($tab,$rows);
            \audit('maestros','importar_'.$tab);
            \flash('success',$result['total'].' filas procesadas: '.$result['inserted'].' nuevas y '.$result['updated'].' actualizadas.');
            \redirect('/maestros?tab='.rawurlencode($tab));
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/maestros/importar?tab='.rawurlencode($tab));
        }
    }

    public function template(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $meta=$this->service->definition($tab);
        $filename='plantilla_'.$tab.'_pucchun.xlsx';
        (new SimpleXlsxTable())->outputTemplate($meta['title'],$meta['import'],$filename);
    }
}

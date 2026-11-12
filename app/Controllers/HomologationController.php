<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{HomologationService,SimpleXlsxTable};
use Throwable;

final class HomologationController
{
    public function __construct(private ?HomologationService $service=null)
    {
        $this->service ??= new HomologationService();
    }

    private function tab(): string
    {
        $tab=strtolower(trim((string)\input('tab','productos')));
        $this->service->definition($tab);
        return $tab;
    }

    private function form(bool $editing): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $id=(int)\input('id',0);
        $record=$editing?$this->service->find($tab,$id):[];
        if(is_array($_SESSION['_homologation_old']??null)) $record=$_SESSION['_homologation_old'];
        $error=(string)($_SESSION['_homologation_error']??'');
        unset($_SESSION['_homologation_old'],$_SESSION['_homologation_error']);
        \view('backoffice.homologation_form',[
            'tab'=>$tab,'meta'=>$this->service->definition($tab),
            'record'=>$record,'editing'=>$editing,'id'=>$id,
            'options'=>$this->service->options($tab,$record),'error'=>$error,
        ]);
    }

    public function create(): void { $this->form(false); }
    public function edit(): void { $this->form(true); }

    private function persist(bool $editing): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $id=$editing?(int)\input('id',0):null;
        $record=is_array($_POST['record']??null)?$_POST['record']:[];
        try{
            $saved=$this->service->save($tab,$record,(int)\auth_user()['id'],$id);
            \audit('homologaciones',$editing?'editar_'.$tab:'crear_'.$tab,$saved);
            \flash('success',$editing?'Homologación actualizada correctamente.':'Homologación creada correctamente.');
            \redirect('/homologaciones?tab='.rawurlencode($tab));
        }catch(HttpException $error){
            $_SESSION['_homologation_old']=$record;
            $_SESSION['_homologation_error']=$error->getMessage();
            $target=$editing?'/homologaciones/editar?tab='.rawurlencode($tab).'&id='.(int)$id
                :'/homologaciones/nuevo?tab='.rawurlencode($tab);
            \redirect($target);
        }
    }

    public function store(): void { $this->persist(false); }
    public function update(): void { $this->persist(true); }

    public function deactivate(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $id=(int)\input('id',0);
        try{
            $this->service->deactivate($tab,$id,(int)\auth_user()['id']);
            \audit('homologaciones','desactivar_'.$tab,$id);
            \flash('success','Homologación desactivada. Se conserva el historial.');
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
        }
        \redirect('/homologaciones?tab='.rawurlencode($tab));
    }

    public function importPage(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        \view('backoffice.homologation_import',['tab'=>$tab,'meta'=>$this->service->definition($tab)]);
    }

    public function import(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        try{
            $rows=(new SimpleXlsxTable())->readUpload($_FILES['excel']??[]);
            $result=$this->service->importRows($tab,$rows,(int)\auth_user()['id']);
            \audit('homologaciones','importar_'.$tab);
            \flash('success',$result['total'].' filas procesadas: '.$result['inserted'].' nuevas y '.$result['updated'].' actualizadas.');
            \redirect('/homologaciones?tab='.rawurlencode($tab));
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/homologaciones/importar?tab='.rawurlencode($tab));
        }catch(Throwable $error){
            \log_event('homologation_import_error',['type'=>get_class($error)]);
            \flash('error','No se pudo completar la importación. Ninguna fila fue confirmada.');
            \redirect('/homologaciones/importar?tab='.rawurlencode($tab));
        }
    }

    public function template(): void
    {
        \require_role('ADMIN');
        $tab=$this->tab();
        $meta=$this->service->definition($tab);
        (new SimpleXlsxTable())->outputTemplate($meta['title'],$meta['import'],'plantilla_homologacion_'.$tab.'_bayer.xlsx');
    }
}

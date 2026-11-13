<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{BayerCatalogService,BayerWorkbookReader,SimpleXlsxTable};
use Throwable;

/** Catálogo de productos Bayer; no comparte códigos ni existencias con Pucchún. */
final class BayerCatalogController
{
    private function service(): BayerCatalogService {return new BayerCatalogService();}

    public function index(): void
    {
        \require_role('ADMIN');
        $rawQ=$_GET['q']??'';
        $rawState=$_GET['estado']??'';
        $q=is_string($rawQ)?trim($rawQ):'';
        $estado=is_string($rawState)?trim($rawState):'';
        if(mb_strlen($q)>100)$q=mb_substr($q,0,100);
        $page=max(1,(int)\input('page',1));
        $data=$this->service()->list(['q'=>$q,'estado'=>$estado],15,($page-1)*15);
        $pages=max(1,(int)ceil($data['total']/15));
        if($page>$pages){
            $page=$pages;$data=$this->service()->list(['q'=>$q,'estado'=>$estado],15,($page-1)*15);
        }
        \view('bayer_catalog.index',['rows'=>$data['rows'],'total'=>$data['total'],
            'q'=>$q,'estado'=>$estado,'page'=>$page,'pages'=>$pages]);
    }

    public function form(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $record=$id>0?$this->service()->find($id):[];
        \view('bayer_catalog.form',['record'=>$record,'id'=>$id]);
    }

    public function save(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        try {
            $saved=$this->service()->save($_POST,$id>0?$id:null);
            \audit('bayer_productos',$id>0?'editar':'crear',$saved);
            \flash('success',$id>0?'Producto Bayer actualizado.':'Producto Bayer registrado.');
            \redirect('/homologaciones?tab=productos');
        } catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/bayer-productos/editar'.($id>0?'?id='.$id:''));
        }
    }

    public function importPage(): void
    {
        \require_role('ADMIN');
        \view('bayer_catalog.import');
    }

    public function import(): void
    {
        \require_role('ADMIN');
        try {
            $period=(string)\input('periodo','');
            $rows=(new BayerWorkbookReader())->readUpload($_FILES['excel']??[],$period);
            $result=$this->service()->importRows($rows,(string)($_FILES['excel']['name']??'Inventario Bayer'));
            \audit('bayer_productos','importar_excel');
            \flash('success','Importación terminada: '.$result['productos_nuevos'].' productos nuevos, '.
                $result['productos_actualizados'].' actualizados y '.$result['periodos'].
                ' registros mensuales. Los datos quedan pendientes de publicación en API.');
            \redirect('/homologaciones?tab=productos');
        } catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/bayer-productos/importar');
        } catch(Throwable $error){
            \log_event('bayer_native_import_error',['type'=>get_class($error)]);
            \flash('error','No se pudo completar la importación Bayer. No se confirmaron datos parciales.');
            \redirect('/bayer-productos/importar');
        }
    }

    public function loadPrices(): void
    {
        \require_role('ADMIN');
        try{
            if((string)\input('confirmacion','')!=='CARGAR')
                throw new HttpException(422,'Confirme la importación de los precios escribiendo CARGAR.');
            $result=$this->service()->importBundledPrices();
            \audit('bayer_precios','cargar_lista_2026');
            \flash('success','Se cargaron '.$result['precios'].' precios Dealer Bayer y '.
                $result['productos_nuevos'].' productos nuevos sin stock reportado.');
        }catch(HttpException $error){\flash('error',$error->getMessage());}
        \redirect('/homologaciones?tab=productos');
    }

    public function template(): void
    {
        \require_role('ADMIN');
        (new SimpleXlsxTable())->outputTemplate('Productos e inventario Bayer',[
            'codigo_bayer'=>['description'=>'Código REAL de Bayer (no es código Pucchún)','example'=>'86216353','required'=>true],
            'nombre_bayer'=>['description'=>'Producto o presentación Bayer','example'=>'ABACUS EC18 4X4L BOT PE','required'=>true],
            'marca'=>['description'=>'Marca reportada; opcional','example'=>'ABACUS','required'=>false],
            'cantidad_unidades'=>['description'=>'Número entero; guion (-) desactiva; vacío significa sin dato','example'=>'','required'=>false],
            'periodo'=>['description'=>'Periodo para cantidades (YYYY-MM); si no hay stock, opcional','example'=>'2026-09','required'=>false],
        ],'plantilla_productos_bayer.xlsx');
    }

    public function status(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        $action=(string)\input('accion','');
        try{
            $this->service()->changeStatus($id,$action);
            \audit('bayer_productos',strtolower($action),$id);
            \flash('success','Estado del producto Bayer actualizado.');
        }catch(HttpException $error){\flash('error',$error->getMessage());}
        \redirect('/homologaciones?tab=productos');
    }

    public function confirmDelete(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        \view('bayer_catalog.delete',['product'=>$this->service()->find($id)]);
    }

    public function delete(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        try{
            $this->service()->delete($id,(string)\input('confirmacion',''));
            \audit('bayer_productos','eliminar_definitivo',$id);
            \flash('success','Producto Bayer y sus inventarios/precios asociados eliminados.');
            \redirect('/homologaciones?tab=productos');
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/bayer-productos/eliminar?id='.$id);
        }
    }

    public function detail(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        \view('bayer_catalog.detail',[
            'product'=>$this->service()->find($id),
            'history'=>$this->service()->history($id),
            'prices'=>$this->service()->prices($id),
        ]);
    }
}

<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{BayerCatalogService,BayerImportPreviewService,BayerPriceImportPreviewService,BayerPriceImportService,SimpleXlsxTable};
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
        $pageRaw=$_GET['page']??1;
        $page=max(1,is_scalar($pageRaw)?(int)$pageRaw:1);
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
        $service=$this->service();
        $record=$id>0?$service->find($id):[];
        $prices=$id>0?$service->prices($id):[];
        $selectedDate=trim((string)($_GET['precio_fecha']??''));
        $activePrice=$prices[0]??[];
        if($selectedDate!==''){
            foreach($prices as $candidate){
                if((string)$candidate['vigencia_desde']===$selectedDate){
                    $activePrice=$candidate;
                    break;
                }
            }
        }
        $warehouses=new \App\Services\ProductWarehouseService();
        \view('bayer_catalog.form',[
            'record'=>$record,'id'=>$id,'prices'=>$prices,'activePrice'=>$activePrice,
            'warehouseOptions'=>$warehouses->options(),
            'selectedWarehouse'=>$id>0?$warehouses->find('BAYER',$id):null,
        ]);
    }

    public function save(): void
    {
        \require_role('ADMIN');
        $id=(int)\input('id',0);
        try {
            $saved=$this->service()->saveWithPrice($_POST,$id>0?$id:null);
            \audit('bayer_productos',$id>0?'editar':'crear',$saved);
            \flash('success',$id>0?'Producto Bayer y precios actualizados.':'Producto Bayer registrado correctamente.');
            \redirect('/homologaciones?tab=productos');
        } catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/bayer-productos/editar'.($id>0?'?id='.$id:''));
        }
    }

    public function importPage(): void
    {
        \require_role('ADMIN');
        $preview=null;$selected=0;$stage=null;
        try{
            $previewService=new BayerImportPreviewService();
            $stage=$previewService->current((int)\auth_user()['id']);
            if($stage!==null){
                $preview=$previewService->data($stage);
                $raw=$_GET['hoja']??'0';
                $selected=is_string($raw)&&ctype_digit($raw)?(int)$raw:0;
                if($selected<0||$selected>=count($preview))$selected=0;
            }
        }catch(Throwable $error){
            \log_event('bayer_preview_render_error',['type'=>get_class($error)]);
            \flash('error','No fue posible recuperar el archivo. Vuelva a seleccionarlo.');
            (new BayerImportPreviewService())->discard();
            $preview=null;$stage=null;
        }
        \view('bayer_catalog.import',[
            'preview'=>$preview,'selected'=>$selected,'stage'=>$stage
        ]);
    }

    /** Paso 1: analiza el archivo, sin modificar la base de datos. */
    public function analyze(): void
    {
        \require_role('ADMIN');
        try{
            $period=\input('periodo','');
            if(!is_string($period))throw new HttpException(422,'El período indicado no es válido.');
            (new BayerImportPreviewService())->stage(
                $_FILES['excel']??[],$period,(int)\auth_user()['id'],
                (string)\input('fecha_precios',''));
            \redirect('/bayer-productos/importar?hoja=0');
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
        }catch(Throwable $error){
            \log_event('bayer_native_preview_error',['type'=>get_class($error)]);
            \flash('error','No se pudo analizar el archivo. Revise su estructura XLSX o CSV.');
        }
        \redirect('/bayer-productos/importar');
    }

    /** Única importación XLSX/CSV: inventario, precios o combinación por hoja. */
    public function import(): void
    {
        \require_role('ADMIN');
        $raw=\input('hoja','');
        $selected=is_string($raw)&&ctype_digit($raw)?(int)$raw:-1;
        try {
            $staging=new BayerImportPreviewService();
            $stage=$staging->current((int)\auth_user()['id']);
            if($stage===null)throw new HttpException(422,
                'La vista previa ha vencido. Vuelva a subir el reporte original.');
            $sheets=$staging->data($stage);
            if(!isset($sheets[$selected]))throw new HttpException(422,
                'Seleccione una hoja válida de la vista previa.');
            $sheet=$sheets[$selected];
            if($sheet['errors']>0)throw new HttpException(422,
                'La hoja seleccionada contiene '.$sheet['errors'].
                ' inconsistencias. Revise códigos, precios, cantidades o fechas.');
            $data=$staging->selectedRows($stage,$sheet['name'],$sheet['tipo']);
            $source=(string)$stage['name'].' · '.$sheet['name'];
            if($sheet['tipo']==='PRECIOS'){
                $price=(new \App\Services\BayerPriceImportService())
                    ->importRows($data['precios'],$source);
                $message='Precios Bayer actualizados por código: '.
                    $price['insertados'].' nuevos períodos, '.
                    $price['actualizados'].' modificados y '.
                    $price['sin_cambios'].' sin cambios.';
            }else{
                $result=$this->service()->importRows(
                    $data['inventario'],$source,$data['precios']);
                $message='Hoja '.$sheet['name'].': '.
                    $result['productos_nuevos'].' productos nuevos, '.
                    $result['productos_actualizados'].' actualizados, '.
                    $result['periodos'].' cantidades mensuales y '.
                    $result['precios'].' registros de precios.';
            }
            \audit('bayer_productos','importar_excel_unificado');
            $staging->discard();
            \flash('success',$message.' Información disponible automáticamente en la API interna. Las demás hojas no se importaron.');
            \redirect('/homologaciones?tab=productos');
        }catch(HttpException $error){
            \flash('error',$error->getMessage());
            \redirect('/bayer-productos/importar'.($selected>=0?'?hoja='.$selected:''));
        }catch(Throwable $error){
            \log_event('bayer_native_import_error',['type'=>get_class($error)]);
            \flash('error','No se pudo completar la importación. No se guardaron cambios parciales.');
            \redirect('/bayer-productos/importar'.($selected>=0?'?hoja='.$selected:''));
        }
    }

    /** Descarta la carga pendiente sin crear productos ni inventarios. */
    public function discardImport(): void
    {
        \require_role('ADMIN');
        (new BayerImportPreviewService())->discard();
        \flash('success','Vista previa cancelada. No se importaron registros.');
        \redirect('/bayer-productos/importar');
    }

    public function pricesPage(): void
    {
        \require_role('ADMIN');
        \redirect('/bayer-productos/importar');
    }

    public function pricesAnalyze(): void
    {
        \require_role('ADMIN');
        $this->analyze();
    }

    public function pricesCancel(): void
    {
        \require_role('ADMIN');
        (new BayerPriceImportPreviewService())->discard();
        \redirect('/bayer-productos/importar');
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

    public function publishAll(): void
    {
        \require_role('ADMIN');
        if((string)\input('confirmacion','')!=='PUBLICAR'){
            \flash('error','Confirme la publicación masiva de productos Bayer revisados.');
            \redirect('/homologaciones?tab=productos');
        }
        $count=$this->service()->publishAll();
        \audit('bayer_productos','publicar_masivo');
        \flash('success',$count.' productos Bayer activos publicados para consulta API.');
        \redirect('/homologaciones?tab=productos');
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

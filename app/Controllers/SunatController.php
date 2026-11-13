<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Services\{SunatEmissionService,SunatReadinessService};
use Throwable;

/** Operaciones tributarias exclusivas de ADMIN, siempre separadas de publicar. */
final class SunatController
{
    public function index(): void
    {
        \require_role('ADMIN');
        $service=new SunatEmissionService(\db());
        $ambiente=(new SunatReadinessService())->mode()==='produccion'?'PRODUCCION':'BETA';
        $candidateQuery=\db()->prepare(
            "SELECT d.id,d.numero,d.fecha,d.precio_igv_modalidad,c.razon_social AS cliente,e.ruc AS emisor,
                    x.id AS sunat_id,x.estado AS sunat_estado
             FROM documentos_cabecera d
             JOIN tipos_documento t ON t.id=d.tipo_documento_id
             JOIN clientes c ON c.id=d.cliente_id
             JOIN sucursales s ON s.id=d.sucursal_id
             JOIN empresas e ON e.id=s.empresa_id
             LEFT JOIN sunat_emisiones x ON x.documento_id=d.id AND x.ambiente=?
             WHERE t.codigo IN ('FAC','01') AND d.estado_registro='PUBLICADO'
                   AND d.estado_comprobante='VIGENTE'
             ORDER BY d.id DESC LIMIT 50"
        );
        $candidateQuery->execute([$ambiente]);
        $candidates=$candidateQuery->fetchAll(\PDO::FETCH_ASSOC);
        \view('sunat.index',[
            'config'=>(new SunatReadinessService())->publicSummary(),
            'emissions'=>$service->recent(),
            'candidates'=>$candidates,
        ]);
    }

    public function emit(): void
    {
        \require_role('ADMIN');
        $id=filter_var(\input('documento_id',0),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $mode=(string)\input('modo_precio','');
        $taxable=\input('confirma_gravados')==='1';
        $cash=\input('confirma_contado')==='1';
        if($id===false||!in_array($mode,['PRECIO_INCLUYE_IGV','PRECIO_SIN_IGV'],true)||!$taxable||!$cash){
            \flash('error','Seleccione documento, interpretación del precio y confirme IGV gravado y pago al contado.');
            \redirect('/sunat');
        }
        $ready=new SunatReadinessService();
        if($ready->mode()==='produccion' && \input('confirmacion_produccion')!=='EMITIR'){
            \flash('error','Para enviar a producción escriba EMITIR en la confirmación.');
            \redirect('/sunat');
        }
        try {
            $row=(new SunatEmissionService(\db()))->emitInvoice(
                (int)$id,(int)\auth_user()['id'],$mode,$taxable,$cash
            );
            \audit('sunat','emitir_factura',$id);
            $status=(string)$row['estado'];
            $msg=match($status){
                'ACEPTADO','ACEPTADO_OBSERVADO'=>'Constancia CDR recibida: '.$status.
                    ($row['ambiente']==='BETA'?' (sólo pruebas).':' (producción).'),
                'RECHAZADO'=>'El CDR de SUNAT rechazó el comprobante. Revise el detalle.',
                default=>'Resultado pendiente o incierto: verifique en SUNAT antes de otro envío.',
            };
            \flash($status==='ACEPTADO'||$status==='ACEPTADO_OBSERVADO'?'success':'error',$msg);
        }catch(HttpException $e){
            \flash('error',$e->getMessage());
        }catch(Throwable $e){
            \log_event('sunat_emission_controller_error',['type'=>get_class($e)]);
            \flash('error','No se pudo confirmar el resultado. Revise los intentos registrados en Emisión SUNAT.');
        }
        \redirect('/sunat');
    }

    public function download(): void
    {
        \require_role('ADMIN');
        $id=filter_var(\input('id',0),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false)throw new HttpException(422,'Emisión no válida.');
        $data=(new SunatEmissionService(\db()))->evidenceFile((int)$id,(string)\input('tipo',''));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: '.$data['content_type']);
        header('Content-Length: '.(string)filesize($data['path']));
        header('Content-Disposition: attachment; filename="'.basename($data['filename']).'"');
        readfile($data['path']);
    }
}

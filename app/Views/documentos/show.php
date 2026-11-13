<?php
$label=function(string $catalog,mixed $id)use($catalogs):string{foreach($catalogs[$catalog]??[] as $item)if((string)$item['id']===(string)$id)return $item['label'];return 'Registro histórico #'.$id;};
$state=$document['estado_registro']; $workflowAvailable=true;
$canReview=can_workflow_permission('validation.review');
$canPublish=can_workflow_permission('publications.publish');
$options=match($state){
    'BORRADOR'=>$canReview?['VALIDADO'=>'Validar']:[],
    'VALIDADO'=>array_merge(
        $canReview?['OBSERVADO'=>'Observar']:[],
        $canPublish?['PUBLICADO'=>'Publicar']:[]
    ),
    default=>[]
};
$voucherState=(string)($document['estado_comprobante']??'VIGENTE');
?>
<link rel="stylesheet" href="<?=asset_url('assets/css/documentos-captura.css')?>">
<link rel="stylesheet" href="<?=asset_url('assets/css/commercial-totals.css')?>">
<section class="module-header"><div><div class="page-eyebrow">DETALLE DEL DOCUMENTO</div><h1 class="page-title"><?=e($document['numero'])?></h1><p class="page-subtitle"><?=e($label('tipo_documento_id',$document['tipo_documento_id']))?> · <?=e($document['fecha'])?></p></div><a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Volver al listado</a></section>
<section class="card mb-3"><div class="card-body p-4"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 class="h5">Datos generales</h2><div class="d-flex gap-2 flex-wrap"><span class="badge-status status-<?=e(strtolower($state))?>">Flujo: <?=e($state)?></span><span class="badge-status status-<?=$voucherState==='ANULADO'?'anulado':'publicado'?>">Comprobante: <?=e($voucherState)?></span></div></div><dl class="row"><div class="col-12 col-md-4"><dt>Cliente</dt><dd><?=e($label('cliente_id',$document['cliente_id']))?></dd></div><div class="col-12 col-md-4"><dt>Vendedor</dt><dd><?=e($label('vendedor_id',$document['vendedor_id']))?></dd></div><div class="col-12 col-md-4"><dt>Sucursal</dt><dd><?=e($label('sucursal_id',$document['sucursal_id']))?></dd></div>
<div class="col-12 col-md-4"><dt>Medio de pago</dt><dd><?=e($document['medio_pago']??'No especificado')?></dd></div>

</dl><?php if(has_role('ADMIN','SUPERVISOR')):?><form method="post" action="<?=url('/documentos/estado-comprobante')?>" class="row g-2 align-items-end mb-3"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($document['id'])?>"><input type="hidden" name="version" value="<?=e($document['version'])?>"><div class="col-12 col-md-4"><label class="form-label" for="voucher-status">Estado del comprobante</label><select class="form-select" id="voucher-status" name="estado_comprobante"><option value="VIGENTE" <?=$voucherState==='VIGENTE'?'selected':''?>>Vigente</option><option value="ANULADO" <?=$voucherState==='ANULADO'?'selected':''?>>Anulado</option></select></div><div class="col-12 col-md-auto"><button class="btn btn-outline-primary" type="submit">Actualizar comprobante</button></div><div class="col-12"><div class="form-text">Este estado es comercial y se mantiene separado del flujo BORRADOR/VALIDADO/OBSERVADO/PUBLICADO.</div></div></form><?php endif;?><?php if($document['observation_reason']??null):?><div class="alert alert-warning"><strong>Observación:</strong> <?=e($document['observation_reason'])?></div><?php endif;?><?php if($document['cancellation_reason']??null):?><div class="alert alert-secondary"><strong>Anulación histórica del flujo:</strong> <?=e($document['cancellation_reason'])?></div><?php endif;?></div></section>
<section class="card mb-3"><div class="card-body p-4">
    <h2 class="h5">Productos · <?=count($details)?> líneas</h2>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Producto</th><th>Unidad</th><th class="text-end">Cantidad</th>
            <th class="text-end">Valor unitario (S/)</th><th class="text-end">Importe (S/)</th></tr></thead>
        <tbody>
        <?php foreach($details as $i=>$line): ?>
        <tr>
            <td><?=e($label('producto_id',$line['producto_id']))?></td>
            <td><?=e($label('unidad_id',$line['unidad_id']))?></td>
            <td class="text-end"><?=e(format_quantity($line['cantidad']))?></td>
            <td class="text-end"><?=e(\App\Services\CommercialTotalsService::money(
                \App\Services\CommercialTotalsService::priceCents((string)$line['valor_unitario'])))?></td>
            <td class="text-end fw-semibold"><?=e(\App\Services\CommercialTotalsService::money($totals['lineas'][$i]['importe']))?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <section class="commercial-summary mt-3" aria-label="Resumen económico del documento">
        <div class="commercial-summary-heading"><i class="bi bi-calculator" aria-hidden="true"></i>
            <strong>Resumen del documento</strong></div>
        <div class="commercial-summary-grid">
            <div class="commercial-summary-note">
                <span><?=count($details)?> <?=count($details)===1?'producto':'productos'?></span>
                <?php if(($document['precio_igv_modalidad']??null)===null): ?>
                    <span class="text-muted small">Registro histórico sin modalidad de IGV consignada.</span>
                <?php else: ?>
                    <span class="text-muted small">Valores unitarios <?=($document['precio_igv_modalidad']==='INCLUIDO')?'con IGV incluido':'sin IGV'?>.</span>
                <?php endif; ?>
            </div>
            <dl class="commercial-summary-values">
                <?php if($totals['subtotal']!==null): ?>
                <div><dt>Subtotal sin IGV</dt><dd><?=e(\App\Services\CommercialTotalsService::money($totals['subtotal']))?></dd></div>
                <div><dt>IGV e IPM (18 %)</dt><dd><?=e(\App\Services\CommercialTotalsService::money($totals['igv']))?></dd></div>
                <?php endif; ?>
                <div class="commercial-grand-total">
                    <dt><?=$totals['subtotal']!==null?'Total a pagar':'Monto registrado (IGV no desagregado)'?></dt>
                    <dd><?=e(\App\Services\CommercialTotalsService::money($totals['total']))?></dd>
                </div>
            </dl>
        </div>
    </section>
</div></section>
<section class="card mb-3"><div class="card-body"><h2 class="h5">Entrega y guía de remisión</h2>
<p class="text-muted small mb-3">Puede crear una guía interna copiando automáticamente cliente, vendedor, sucursal y todos los productos de esta factura o boleta. La guía no se considera emitida ante SUNAT hasta completar el procedimiento oficial.</p>
<?php if(!empty($guideId)): ?>
<a class="btn btn-outline-success" href="<?=url('/guias/ver?id='.(int)$guideId)?>">Ver guía vinculada</a>
<?php elseif(!empty($canGenerateGuide) && has_role('ADMIN','DIGITADOR')): ?>
<form method="post" action="<?=url('/guias/desde-documento')?>"><?=csrf_field()?>
<input type="hidden" name="documento_id" value="<?=e((int)$document['id'])?>">
<button class="btn btn-success" type="submit">Generar guía automáticamente</button>
</form>
<?php else: ?><p class="text-muted mb-0">No disponible para este tipo o estado de documento.</p><?php endif; ?>
</div></section>
<section class="card"><div class="card-body d-flex flex-wrap align-items-center gap-2"><?php if(in_array($state,['BORRADOR','OBSERVADO'],true)&&has_role('ADMIN','DIGITADOR')):?><a class="btn btn-primary" href="<?=url('/documentos/editar?id='.$document['id'])?>"><?=$state==='OBSERVADO'?'Corregir observación':'Editar borrador'?></a><?php else:?><span class="text-muted">Consulta de solo lectura.</span><?php endif;?><?php foreach($options as $target=>$text):
    $buttonStyle=match($target){'VALIDADO'=>'btn-app-blue','PUBLICADO'=>'btn-app-green','OBSERVADO'=>'btn-app-amber',default=>'btn-app-blue'};
    $buttonIcon=match($target){'VALIDADO'=>'bi-check2-circle','PUBLICADO'=>'bi-cloud-arrow-up','OBSERVADO'=>'bi-exclamation-triangle',default=>'bi-arrow-right-circle'};
?><button type="button" class="btn btn-app-action <?=e($buttonStyle)?> btn-with-icon" <?=!$workflowAvailable?'disabled':''?> data-workflow-target="<?=e($target)?>" data-workflow-label="<?=e($text)?>"><i class="bi <?=e($buttonIcon)?>" aria-hidden="true"></i><span><?=e($text)?></span></button><?php endforeach;?></div></section>
<p class="text-muted mt-3">Las acciones disponibles dependen del estado del documento y del rol del usuario.</p>
<dialog class="doc-dialog" aria-labelledby="workflow-title" data-workflow-dialog><form method="post" data-no-soft-nav action="<?=url('/documentos/estado')?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($document['id'])?>"><input type="hidden" name="version" value="<?=e($document['version'])?>"><input type="hidden" name="status" data-workflow-state><h2 class="h4" id="workflow-title">Confirmar acción</h2><p>La acción se aplicará al documento <?=e($document['numero'])?>.</p><div data-reason-wrap><label class="form-label" for="workflow-reason">Motivo</label><textarea id="workflow-reason" class="form-control" name="reason" maxlength="500" rows="3"></textarea></div><div class="d-flex justify-content-end gap-2 mt-3"><button class="btn btn-outline-primary" type="button" data-close-dialog>Cancelar</button><button class="btn btn-primary" type="submit">Confirmar</button></div></form></dialog>
<script src="<?=asset_url('assets/js/documentos-captura.js')?>" defer></script>

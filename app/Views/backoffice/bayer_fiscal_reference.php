<?php
if(has_role('ADMIN') && ($fiscalHeader['estado_registro']??'')==='PUBLICADO'):
    $fiscalService=new \App\Services\BayerFiscalReferenceService(db());
    if($fiscalService->available()):
        $fiscalRef=$fiscalService->find($fiscalScope,(int)$fiscalHeader['id']);
?>
<section class="card mb-3"><div class="card-body">
    <h2 class="h6">Información fiscal y origen de despacho para Bayer</h2>
    <?php if(!empty($fiscalRef['numero_fiscal'])): ?>
        <p class="mb-2">Número fiscal externo registrado: <strong><?=e($fiscalRef['numero_fiscal'])?></strong></p>
    <?php endif; ?>
    <form method="post" action="<?=url('/sunat/referencia-bayer')?>" class="row g-3">
        <?=csrf_field()?>
        <input type="hidden" name="modulo" value="<?=e($fiscalScope)?>">
        <input type="hidden" name="id" value="<?=e($fiscalHeader['id'])?>">
        <div class="col-md-5"><label class="form-label" for="numero-fiscal-bayer">Número fiscal externo (si ya fue emitido)</label>
            <input class="form-control" id="numero-fiscal-bayer" name="numero_fiscal" maxlength="13" placeholder="<?=$fiscalScope==='guias'?'T001-00000001':'F001-00000001'?>">
            <div class="form-text">Dejar vacío conserva el número registrado. Las facturas aceptadas en producción por el emisor del ERP utilizan automáticamente su numeración fiscal.</div>
        </div>
        <div class="col-md-7"><label class="form-label" for="despacho-bayer">Lugar de despacho</label>
            <select class="form-select" id="despacho-bayer" name="distrito_despacho_id" data-search-select data-search-min="1" data-search-url="<?=e(url('/lookups?type=ubicaciones'))?>">
                <option value="">Usar ubicación de la sucursal</option>
                <?php if(!empty($fiscalRef['distrito_despacho_id'])): ?><option selected value="<?=e($fiscalRef['distrito_despacho_id'])?>"><?=e($fiscalRef['ubicacion'])?></option><?php endif;?>
            </select>
        </div>
        <div class="col-12"><label class="form-check-label"><input class="form-check-input me-2" type="checkbox" name="confirmado" value="1"> Confirmo que el número externo corresponde a un comprobante realmente emitido. Su registro no equivale a una validación automática por SUNAT.</label></div>
        <div class="col-12"><button type="submit" class="btn btn-app-blue"><i class="bi bi-floppy"></i> Guardar información para Bayer</button></div>
    </form>
</div></section>
<?php endif; endif; ?>

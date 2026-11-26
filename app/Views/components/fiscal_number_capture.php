<?php
$fiscalService=new \App\Services\BayerFiscalReferenceService(db());
$fiscalNumbers=$fiscalId>0?$fiscalService->displayNumbers($fiscalScope,[$fiscalId]):[];
$knownFiscal=$fiscalNumbers[$fiscalId]??null;
?>
<label class="form-label" for="capture-fiscal-number"><?=$fiscalScope==='guias'?'Número de guía de remisión':'Número de comprobante'?></label>
<?php if(!$editing && has_role('ADMIN') && $fiscalService->available()): ?>
<input class="form-control<?=$fiscalError?' is-invalid':''?>" id="capture-fiscal-number" name="numero_fiscal" maxlength="13" value="<?=e($fiscalCapture)?>" placeholder="<?=$fiscalScope==='guias'?'T001-00000001':'F001-00000001'?>" autocomplete="off">
<?php if($fiscalError): ?><div class="invalid-feedback"><?=e($fiscalError)?></div><?php endif;?>
<label class="form-check-label small mt-2"><input class="form-check-input me-1" type="checkbox" name="confirmado_fiscal" value="1" <?=$fiscalConfirmed?'checked':''?>> Comprobante ya emitido</label>
<div class="form-text">Dejar vacío si está pendiente de emisión.</div>
<?php else: ?>
<input class="form-control" id="capture-fiscal-number" value="<?=e(\App\Services\BayerFiscalReferenceService::displayLabel($knownFiscal))?>" readonly>
<?php endif; ?>

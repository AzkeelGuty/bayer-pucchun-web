<?php
$automatic=new \App\Services\AutomaticFiscalNumberService(db());
$fiscalLabels=$fiscalId>0?$automatic->labels($fiscalScope,[$fiscalId]):[];
$isGuide=$fiscalScope==='guias';
$previewType=$isGuide?0:(int)($header['tipo_documento_id']??0);
$previewBranch=$isGuide?(int)old('sucursal_id'):(int)($header['sucursal_id']??0);
$number=$fiscalId>0?($fiscalLabels[$fiscalId]??'Pendiente de número fiscal'):$automatic->preview($fiscalScope,$previewType,$previewBranch);
?>
<label class="form-label" for="capture-fiscal-number"><?=$isGuide?'Número de guía (automático)':'Número de comprobante (automático)'?></label>
<input class="form-control" id="capture-fiscal-number" value="<?=e($number)?>" readonly data-internal-number <?php if(!$editing):?>data-fiscal-preview-url="<?=e(url($isGuide?'/guias/siguiente-numero':'/documentos/siguiente-numero'))?>"<?php endif;?>>
<?php if(!$editing):?><div class="form-text"><i class="bi bi-shield-check me-1"></i>Número automático confirmado al guardar. Pendiente de emisión.</div><?php endif;?>

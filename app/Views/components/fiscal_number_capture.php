<?php
$automatic=new \App\Services\AutomaticFiscalNumberService(db());
$fiscalLabels=$fiscalId>0?$automatic->labels($fiscalScope,[$fiscalId]):[];
?>
<label class="form-label" for="capture-fiscal-number"><?=$fiscalScope==='guias'?'Número de guía (automático)':'Número de comprobante (automático)'?></label>
<input class="form-control" id="capture-fiscal-number" value="<?=e($fiscalId>0?($fiscalLabels[$fiscalId]??'Pendiente de número fiscal'):'Se asignará al guardar')?>" readonly>
<div class="form-text"><?php if(!$automatic->available()): ?>Instale la migración 020 para habilitar la numeración automática.<?php elseif(!$editing): ?>Serie y correlativo automáticos. Requiere el RUC del emisor configurado.<?php endif;?></div>

<section class="page-header">
<div><div class="page-eyebrow">ENTREGA DE INFORMACIÓN</div><h1 class="page-title">Reportes y exportaciones</h1><p class="page-subtitle">Generación multiformato desde la misma fuente de datos publicados.</p></div>
</section>
<div class="export-grid">
<?php foreach(['documents'=>'Documentos','guides'=>'Guías de remisión','stock'=>'Stock'] as $type=>$label): ?>
<article class="card export-card"><div class="card-body"><span class="export-card-icon"><?=strtoupper(substr($label,0,2))?></span><h3><?=e($label)?></h3><p>Exporta únicamente información publicada.</p><div class="export-actions"><?php foreach(['xlsx','csv','json','txt','pdf'] as $f): ?><a href="<?=url('/export?type='.$type.'&format='.$f)?>"><?=strtoupper($f)?></a><?php endforeach;?></div></div></article>
<?php endforeach;?>
</div>
<div class="card mt-4"><div class="card-header bg-white"><strong>Últimas exportaciones</strong></div><div class="card-body p-0"><?php if(!$rows): ?><div class="empty-state"><strong>Aún no hay exportaciones registradas.</strong></div><?php else: ?><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><?php foreach(array_keys($rows[0]) as $h): ?><th><?=e(ucwords(str_replace('_',' ',$h)))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $r): ?><tr><?php foreach($r as $v): ?><td><?=e($v??'—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></div>
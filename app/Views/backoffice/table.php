<section class="page-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-database"></i> <?=e(strtoupper($section))?></div>
        <h1 class="page-title"><?=e($title)?></h1>
        <p class="page-subtitle">Información centralizada para la operación y el control del sistema.</p>
    </div>
</section>

<?php if($tabs): ?>
<nav class="section-tabs" aria-label="<?=e($section)?>">
    <?php foreach($tabs as $key=>$label): ?>
        <a class="section-tab <?=$key===$active?'active':''?>" href="<?=url($base.'?tab='.urlencode((string)$key))?>"><?=e($label)?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <?php if(!$rows): ?>
            <div class="empty-state"><i class="bi bi-inbox fs-3 mb-2"></i><strong>Sin información registrada.</strong><span>Los datos disponibles aparecerán aquí.</span></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table app-table mb-0">
                    <thead><tr><?php foreach(array_keys($rows[0]) as $h): ?><th><?=e(ucwords(str_replace('_',' ',$h)))?></th><?php endforeach;?></tr></thead>
                    <tbody><?php foreach($rows as $row): ?><tr><?php foreach($row as $value): ?><td><?=e($value ?? '—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
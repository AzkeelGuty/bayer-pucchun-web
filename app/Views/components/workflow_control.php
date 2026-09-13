<?php
function workflow_control(string $module, array $row): void
{
    if (!has_role('ADMIN','SUPERVISOR')) return;

    $state=(string)($row['estado_registro'] ?? 'BORRADOR');
    $options=match($state){
        'BORRADOR'=>['VALIDADO'=>'Validar'],
        'VALIDADO'=>['PUBLICADO'=>'Publicar','OBSERVADO'=>'Observar'],
        'OBSERVADO'=>['BORRADOR'=>'Devolver a borrador'],
        'PUBLICADO'=>['ANULADO'=>'Anular'],
        default=>[]
    };
    if(!$options) return;
    $needsReason=array_intersect(array_keys($options),['OBSERVADO','ANULADO']);
    ?>
    <form method="post" action="<?=url('/'.$module.'/estado')?>" class="workflow-form">
        <?=csrf_field()?>
        <input type="hidden" name="id" value="<?=e($row['id'])?>">
        <input type="hidden" name="version" value="<?=e($row['version'])?>">
        <select name="status" class="form-select form-select-sm" required>
            <option value="">Acción...</option>
            <?php foreach($options as $value=>$label): ?>
                <option value="<?=e($value)?>"><?=e($label)?></option>
            <?php endforeach; ?>
        </select>
        <?php if($needsReason): ?>
            <input name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Motivo si aplica">
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-primary" type="submit">Aplicar</button>
    </form>
    <?php
}

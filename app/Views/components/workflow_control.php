<?php
function workflow_control(string $module, array $row, string $returnTo='/validacion', string $presentation='quick'): void
{
    if (!has_role('ADMIN','SUPERVISOR')) return;

    $state=(string)($row['estado_registro'] ?? 'BORRADOR');
    $id=(int)($row['id'] ?? 0);
    $version=(int)($row['version'] ?? 0);
    $safeReturn=in_array($returnTo,['/validacion','/documentos','/guias','/stock'],true)
        ? $returnTo
        : '/validacion';

    if($presentation==='supervised'){
        $options=match($state){
            'BORRADOR'=>['VALIDADO'=>'Validar'],
            'VALIDADO'=>['PUBLICADO'=>'Publicar','OBSERVADO'=>'Observar'],
            'OBSERVADO'=>['BORRADOR'=>'Devolver a borrador'],
            default=>[]
        };
        if(!$options) return;

        $default=(string)array_key_first($options);
        $needsReason=array_intersect(array_keys($options),['OBSERVADO','ANULADO']);
        ?>
        <form method="post" action="<?=url('/'.$module.'/estado')?>" class="workflow-form" data-workflow-supervised>
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=e($id)?>">
            <input type="hidden" name="version" value="<?=e($version)?>">
            <input type="hidden" name="return_to" value="<?=e($safeReturn)?>">
            <select name="status" class="form-select form-select-sm" required data-workflow-status>
                <?php foreach($options as $value=>$label): ?>
                    <option value="<?=e($value)?>" <?=$value===$default?'selected':''?>><?=e($label)?></option>
                <?php endforeach; ?>
            </select>
            <?php if($needsReason): ?>
                <input name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Motivo si aplica" data-workflow-reason>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary btn-with-icon" type="submit">
                <i class="bi bi-check2-circle"></i><span>Aplicar</span>
            </button>
        </form>
        <?php
        return;
    }

    $form=function(string $status,string $label,string $class,string $icon='bi-check2-circle') use($module,$id,$version,$safeReturn): void {
        ?>
        <form method="post" action="<?=url('/'.$module.'/estado')?>" class="workflow-quick-form">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=e($id)?>">
            <input type="hidden" name="version" value="<?=e($version)?>">
            <input type="hidden" name="status" value="<?=e($status)?>">
            <input type="hidden" name="return_to" value="<?=e($safeReturn)?>">
            <button class="btn btn-sm <?=e($class)?> btn-with-icon" type="submit">
                <i class="bi <?=e($icon)?>"></i><span><?=e($label)?></span>
            </button>
        </form>
        <?php
    };

    echo '<div class="workflow-actions">';

    if($state==='BORRADOR'){
        $form('VALIDADO','Validar','btn-success','bi-check2-circle');
    } elseif($state==='VALIDADO'){
        $form('PUBLICADO','Publicar','btn-success','bi-cloud-arrow-up');
        ?>
        <details class="workflow-observe">
            <summary class="btn btn-sm btn-outline-warning btn-with-icon">
                <i class="bi bi-exclamation-circle"></i><span>Observar</span>
            </summary>
            <form method="post" action="<?=url('/'.$module.'/estado')?>" class="workflow-reason-form">
                <?=csrf_field()?>
                <input type="hidden" name="id" value="<?=e($id)?>">
                <input type="hidden" name="version" value="<?=e($version)?>">
                <input type="hidden" name="status" value="OBSERVADO">
                <input type="hidden" name="return_to" value="<?=e($safeReturn)?>">
                <label class="visually-hidden" for="reason-<?=e($module.'-'.$id)?>">Motivo de observación</label>
                <input id="reason-<?=e($module.'-'.$id)?>" name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Motivo de observación" required>
                <button class="btn btn-sm btn-warning" type="submit">Confirmar</button>
            </form>
        </details>
        <?php
    } elseif($state==='OBSERVADO'){
        echo '<span class="workflow-waiting"><i class="bi bi-pencil-square"></i> Esperando corrección</span>';
    } elseif($state==='PUBLICADO'){
        echo '<span class="workflow-waiting workflow-ready"><i class="bi bi-cloud-check"></i> Publicado</span>';
    }

    echo '</div>';
}

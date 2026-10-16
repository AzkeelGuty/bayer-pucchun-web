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
        $dialogId='workflow-observe-'.$module.'-'.$id;
        ?>
        <button
            class="btn btn-sm btn-warning btn-with-icon"
            type="button"
            data-workflow-dialog-open="<?=e($dialogId)?>"
            aria-haspopup="dialog"
            aria-controls="<?=e($dialogId)?>"
        >
            <i class="bi bi-exclamation-circle"></i><span>Observar</span>
        </button>

        <dialog class="workflow-dialog" id="<?=e($dialogId)?>" data-workflow-dialog>
            <form method="post" action="<?=url('/'.$module.'/estado')?>" class="workflow-dialog-form">
                <?=csrf_field()?>
                <input type="hidden" name="id" value="<?=e($id)?>">
                <input type="hidden" name="version" value="<?=e($version)?>">
                <input type="hidden" name="status" value="OBSERVADO">
                <input type="hidden" name="return_to" value="<?=e($safeReturn)?>">

                <div class="workflow-dialog-header">
                    <span class="workflow-dialog-icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <div>
                        <h2>Observar registro</h2>
                        <p>Indica claramente qué debe corregirse antes de volver a validar.</p>
                    </div>
                </div>

                <div class="workflow-dialog-body">
                    <label class="form-label" for="reason-<?=e($module.'-'.$id)?>">Motivo de observación</label>
                    <textarea
                        id="reason-<?=e($module.'-'.$id)?>"
                        name="reason"
                        class="form-control"
                        rows="4"
                        maxlength="500"
                        placeholder="Ejemplo: corregir destino de entrega o revisar la cantidad del producto."
                        required
                        data-workflow-dialog-reason
                    ></textarea>
                    <div class="workflow-dialog-counter"><span data-workflow-reason-count>0</span>/500</div>
                </div>

                <div class="workflow-dialog-actions">
                    <button class="btn btn-outline-secondary" type="button" data-workflow-dialog-close>Cancelar</button>
                    <button class="btn btn-warning btn-with-icon" type="submit">
                        <i class="bi bi-send-check"></i><span>Enviar observación</span>
                    </button>
                </div>
            </form>
        </dialog>
        <?php
    } elseif($state==='OBSERVADO'){
        echo '<span class="workflow-waiting"><i class="bi bi-pencil-square"></i> Esperando corrección</span>';
    } elseif($state==='PUBLICADO'){
        echo '<span class="workflow-waiting workflow-ready"><i class="bi bi-cloud-check"></i> Publicado</span>';
    }

    echo '</div>';
}

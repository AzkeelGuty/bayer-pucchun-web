<?php
function bulk_workflow_controls(string $module,array $counts): void
{
    if(!has_role('ADMIN','SUPERVISOR')) return;

    $drafts=(int)($counts['BORRADOR']??0);
    $validated=(int)($counts['VALIDADO']??0);
    ?>
    <div class="bulk-workflow-actions" aria-label="Acciones masivas">
        <form method="post" action="<?=url('/'.$module.'/estado-masivo')?>" data-bulk-workflow data-bulk-label="validar todos los borradores">
            <?=csrf_field()?>
            <input type="hidden" name="status" value="VALIDADO">
            <button class="btn btn-outline-success btn-with-icon" type="submit" <?=$drafts<1?'disabled':''?> title="<?=$drafts?> borradores pendientes">
                <i class="bi bi-check2-all"></i>
                <span>Validar todo<?=$drafts>0?' ('.$drafts.')':''?></span>
            </button>
        </form>
        <form method="post" action="<?=url('/'.$module.'/estado-masivo')?>" data-bulk-workflow data-bulk-label="publicar todos los registros validados">
            <?=csrf_field()?>
            <input type="hidden" name="status" value="PUBLICADO">
            <button class="btn btn-success btn-with-icon" type="submit" <?=$validated<1?'disabled':''?> title="<?=$validated?> registros listos para publicar">
                <i class="bi bi-cloud-arrow-up"></i>
                <span>Publicar todo<?=$validated>0?' ('.$validated.')':''?></span>
            </button>
        </form>
    </div>
    <?php
}

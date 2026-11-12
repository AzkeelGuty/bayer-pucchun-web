<?php
function bulk_workflow_controls(string $module,array $counts): void
{
    if(!has_role('ADMIN','SUPERVISOR')) return;

    $canValidate=can_workflow_permission('validation.review');
    $canPublish=can_workflow_permission('publications.publish');

    $drafts=(int)($counts['BORRADOR']??0);
    $validated=(int)($counts['VALIDADO']??0);
    ?>
    <div class="bulk-workflow-actions" aria-label="Acciones masivas">
        <form method="post" action="<?=url('/'.$module.'/estado-masivo')?>" data-no-soft-nav data-bulk-workflow data-bulk-label="validar todos los borradores">
            <?=csrf_field()?>
            <input type="hidden" name="status" value="VALIDADO">
            <button class="btn btn-app-action btn-app-blue btn-with-icon" type="submit" <?=(!$canValidate||$drafts<1)?'disabled':''?> title="<?=$canValidate?$drafts.' borradores pendientes':'Sin permiso de revisión; ejecute la migración 011'?>">
                <i class="bi bi-check2-all"></i>
                <span>Validar todo<?=$drafts>0?' ('.$drafts.')':''?></span>
            </button>
        </form>
        <form method="post" action="<?=url('/'.$module.'/estado-masivo')?>" data-no-soft-nav data-bulk-workflow data-bulk-label="publicar todos los registros validados">
            <?=csrf_field()?>
            <input type="hidden" name="status" value="PUBLICADO">
            <button class="btn btn-app-action btn-app-green btn-with-icon" type="submit" <?=(!$canPublish||$validated<1)?'disabled':''?> title="<?=$canPublish?$validated.' registros listos para publicar':'Sin permiso de publicación; ejecute la migración 011'?>">
                <i class="bi bi-cloud-arrow-up"></i>
                <span>Publicar todo<?=$validated>0?' ('.$validated.')':''?></span>
            </button>
        </form>
    </div>
    <?php
}

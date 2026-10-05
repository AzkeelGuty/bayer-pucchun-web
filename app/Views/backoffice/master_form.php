<?php
$title=($editing?'Editar ':'Nuevo ').mb_strtolower((string)$meta['title']);
?>
<section class="module-header">
    <div>
        <div class="page-eyebrow"><i class="bi bi-database-gear"></i> CATÁLOGOS MAESTROS</div>
        <h1 class="page-title"><?=e($title)?></h1>
        <p class="page-subtitle"><?= $editing ? 'Actualiza los datos del registro seleccionado.' : 'Registra un elemento nuevo directamente en la base de datos.' ?></p>
    </div>
    <a class="btn btn-outline-primary" href="<?=url('/maestros?tab='.rawurlencode($tab))?>"><i class="bi bi-arrow-left"></i> Volver al catálogo</a>
</section>

<?php if($error!==''): ?>
<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?=e($error)?></div>
<?php endif; ?>

<form class="card master-edit-card" method="post" action="<?=url($editing?'/maestros/actualizar':'/maestros/guardar')?>">
    <?=csrf_field()?>
    <input type="hidden" name="tab" value="<?=e($tab)?>">
    <?php if($editing): ?><input type="hidden" name="id" value="<?=e($id)?>"><?php endif; ?>
    <div class="card-body p-4">
        <div class="master-form-grid">
        <?php foreach($meta['fields'] as $name=>$field):
            $value=$record[$name]??'';
            $required=!empty($field['required']);
            $type=(string)($field['type']??'text');
            $inputId='master-'.$name;
        ?>
            <div class="master-field">
                <label class="form-label" for="<?=e($inputId)?>"><?=e($field['label'])?><?=$required?' *':''?></label>
                <?php if($type==='select'): ?>
                    <select class="form-select" id="<?=e($inputId)?>" name="record[<?=e($name)?>]" <?=$required?'required':''?> <?=isset($field['source'])?'data-search-select data-search-placeholder="Buscar '.e(mb_strtolower((string)$field['label'])).'..." data-search-min="1"':''?>>
                        <?php if(!$required): ?><option value="">Sin asignar</option><?php endif; ?>
                        <?php if(isset($field['options'])): ?>
                            <?php foreach($field['options'] as $optionValue=>$optionLabel): ?>
                                <option value="<?=e($optionValue)?>" <?=((string)$value===(string)$optionValue)?'selected':''?>><?=e($optionLabel)?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach(($options[$name]??[]) as $option): ?>
                                <option value="<?=e($option['id'])?>" <?=((string)$value===(string)$option['id'])?'selected':''?>><?=e($option['label'])?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                <?php else: ?>
                    <input
                        class="form-control"
                        id="<?=e($inputId)?>"
                        name="record[<?=e($name)?>]"
                        type="<?=e($type)?>"
                        value="<?=e($value)?>"
                        <?=isset($field['max'])?'maxlength="'.e($field['max']).'"':''?>
                        <?=$type==='number'?'min="0.0001" step="'.e($field['step']??'1').'"':''?>
                        <?=$required?'required':''?>
                        autocomplete="off"
                    >
                <?php endif; ?>
                <?php if(!empty($field['help'])): ?><div class="form-text"><?=e($field['help'])?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end flex-wrap">
        <a class="btn btn-outline-secondary" href="<?=url('/maestros?tab='.rawurlencode($tab))?>">Cancelar</a>
        <button class="btn btn-primary btn-with-icon" type="submit"><i class="bi bi-floppy"></i><span><?=$editing?'Guardar cambios':'Agregar registro'?></span></button>
    </div>
</form>

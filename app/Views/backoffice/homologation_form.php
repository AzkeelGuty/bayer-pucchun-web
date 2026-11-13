<?php
$action=$editing?'/homologaciones/actualizar':'/homologaciones/guardar';
$back='/homologaciones?tab='.rawurlencode($tab);
?>
<section class="module-header">
  <div><div class="page-eyebrow">HOMOLOGACIONES BAYER</div>
    <h1 class="page-title"><?=$editing?'Editar':'Nueva'?> homologación · <?=e($meta['title'])?></h1>
    <p class="page-subtitle">Vincule el registro interno con su código externo de Bayer.</p>
  </div>
  <a class="btn btn-outline-primary" href="<?=url($back)?>">Volver al listado</a>
</section>
<?php if($error!==''): ?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif;?>
<form class="card master-edit-card" method="post" action="<?=url($action)?>">
  <?=csrf_field()?>
  <input type="hidden" name="tab" value="<?=e($tab)?>">
  <?php if($editing): ?><input type="hidden" name="id" value="<?=e((int)$id)?>"><?php endif;?>
  <div class="card-body p-4">
    <div class="master-form-grid">
      <div class="master-field">
        <label class="form-label" for="h-partner">Aliado / partner *</label>
        <select class="form-select" id="h-partner" name="record[partner_id]" data-search-select data-search-min="1" data-search-placeholder="Busca un partner..." required>
          <option value="">Seleccionar partner…</option>
          <?php foreach($options['partners'] as $item): ?>
            <option value="<?=e($item['id'])?>" <?=((string)($record['partner_id']??'')===(string)$item['id'])?'selected':''?>><?=e($item['label'])?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="master-field">
        <label class="form-label" for="h-local">Registro del catálogo interno *</label>
        <select class="form-select" id="h-local" name="record[local_id]" data-search-select data-search-min="1" data-search-url="<?=e(url('/lookups?type='.$meta['lookup']))?>" data-search-placeholder="Escribe el código o comienzo del nombre..." required>
          <option value="">Buscar registro…</option>
          <?php foreach($options['local'] as $item): ?>
            <option value="<?=e($item['id'])?>" <?=((string)($record['local_id']??'')===(string)$item['id'])?'selected':''?>><?=e($item['label'])?></option>
          <?php endforeach;?>
        </select>
        <div class="form-text">Solo se utilizan registros existentes del catálogo maestro.</div>
      </div>
      <div class="master-field">
        <label class="form-label" for="h-code">Código Bayer <?=$tab==='productos'?'*':''?></label>
        <input class="form-control" id="h-code" type="text" name="record[codigo_bayer]" maxlength="<?=e((string)$meta['code_max'])?>" value="<?=e((string)($record['codigo_bayer']??''))?>" <?=$tab==='productos'?'required':''?>>
      </div>
      <div class="master-field">
        <label class="form-label" for="h-name">Nombre Bayer</label>
        <input class="form-control" id="h-name" type="text" name="record[nombre_bayer]" maxlength="<?=e((string)$meta['name_max'])?>" value="<?=e((string)($record['nombre_bayer']??''))?>">
      </div>
      <?php if($tab==='productos'): ?>
      <div class="master-field">
        <label class="form-label" for="h-unit">Unidad Bayer (opcional)</label>
        <select id="h-unit" class="form-select" name="record[unidad_bayer_id]" data-search-select data-search-min="1" data-search-placeholder="Buscar unidad...">
          <option value="">Sin asignar</option>
          <?php foreach($options['units'] as $item): ?>
          <option value="<?=e($item['id'])?>" <?=((string)($record['unidad_bayer_id']??'')===(string)$item['id'])?'selected':''?>><?=e($item['label'])?></option>
          <?php endforeach;?>
        </select>
      </div>
      <?php endif;?>
      <div class="master-field">
        <label class="form-label" for="h-state">Estado *</label>
        <select id="h-state" class="form-select" name="record[estado]" required>
          <option value="1" <?=((string)($record['estado']??'1')==='1')?'selected':''?>>Activo</option>
          <option value="0" <?=((string)($record['estado']??'1')==='0')?'selected':''?>>Inactivo</option>
        </select>
      </div>
      <div class="master-field">
        <label class="form-label" for="h-from">Vigente desde</label>
        <input id="h-from" class="form-control" name="record[valid_from]" type="date" value="<?=e((string)($record['valid_from']??''))?>">
      </div>
      <div class="master-field">
        <label class="form-label" for="h-until">Vigente hasta</label>
        <input id="h-until" class="form-control" name="record[valid_until]" type="date" value="<?=e((string)($record['valid_until']??''))?>">
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?=url($back)?>">Cancelar</a>
    <button class="btn btn-primary" type="submit"><?=$editing?'Guardar cambios':'Crear homologación'?></button>
  </div>
</form>

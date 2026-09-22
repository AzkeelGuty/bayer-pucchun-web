<?php
$details=$details?:[['producto_id'=>'','unidad_id'=>'','cantidad'=>'','valor_unitario'=>'0']];
$field=function(string $name,string $label,string $key,mixed $value,string $catalog='',string $type='text',string $extra='')use($catalogs,$errors):void {
    $id='doc-'.str_replace('.','-',$key); $error=$errors[$key]??''; $value=is_scalar($value)?$value:'';
    echo '<label class="form-label" for="'.e($id).'">'.e($label).'</label>';
    $attrs=' id="'.e($id).'" name="'.e($name).'" required aria-invalid="'.($error?'true':'false').'"'.($error?' aria-describedby="'.e($id).'-error"':'');
    if($catalog){
        echo '<select class="form-select'.($error?' is-invalid':'').'"'.$attrs.'><option value="">Seleccionar…</option>';
        foreach($catalogs[$catalog] as $item){
            $meta='';
            if($catalog==='producto_id') $meta.=' data-unit-id="'.e((string)($item['unidad_base_id']??'')).'"';
            if($catalog==='cliente_id'){
                $meta.=' data-seller-id="'.e((string)($item['vendedor_sugerido_id']??'')).'"';
                $meta.=' data-branch-id="'.e((string)($item['sucursal_sugerida_id']??'')).'"';
            }
            echo '<option value="'.e($item['id']).'"'.$meta.((string)$value===(string)$item['id']?' selected':'').'>'.e($item['label']).'</option>';
        }
        echo '</select>';
    }
    else echo '<input class="form-control'.($error?' is-invalid':'').'" type="'.e($type).'" value="'.e(is_scalar($value)?$value:'').'"'.$attrs.' '.$extra.'>';
    if($error) echo '<div id="'.e($id).'-error" class="invalid-feedback">'.e($error).'</div>';
};
?>
<link rel="stylesheet" href="<?=url('/assets/css/documentos-captura.css')?>">
<section class="module-header"><div><div class="page-eyebrow">DOCUMENTOS · <?= $editing?'EDICIÓN':'CAPTURA'?></div><h1 class="page-title"><?=$editing?'Editar borrador':'Nuevo documento'?></h1><p class="page-subtitle">Selecciona los catálogos y agrega los productos del documento.</p></div><a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Volver al listado</a></section>
<?php if($errors): ?><div class="alert alert-danger" role="alert" tabindex="-1" data-error-summary><strong>Revisa la información antes de guardar.</strong><ul class="mb-0"><?php foreach($errors as $key=>$error): ?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<form class="card doc-screen" method="post" action="<?=url($editing?'/documentos/actualizar':'/documentos/guardar')?>" data-documents-form>
<?=csrf_field()?><?php if($editing):?><input type="hidden" name="id" value="<?=e($header['id'])?>"><input type="hidden" name="version" value="<?=e($header['version'])?>"><?php endif;?>
<div class="card-body p-4"><h2 class="h5 mb-3">Datos generales</h2><div class="row g-3">
<?php foreach(['tipo_documento_id'=>'Tipo de documento','numero'=>'Número','fecha'=>'Fecha','cliente_id'=>'Cliente','vendedor_id'=>'Vendedor','sucursal_id'=>'Sucursal'] as $key=>$label):?><div class="col-12 col-md-6 col-xl-4"><?php $field('header['.$key.']',$label,'header.'.$key,$header[$key]??'',isset($catalogs[$key])?$key:'',$key==='fecha'?'date':'text',$key==='numero'?'maxlength="25"':''); ?></div><?php endforeach;?>
</div><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3"><h2 class="h5 mb-0">Productos</h2><button type="button" class="btn btn-outline-primary" data-add-detail>+ Añadir producto al documento</button></div>
<div class="table-responsive"><table class="table align-middle doc-lines"><caption class="visually-hidden">Productos del documento</caption><thead><tr><th>Producto</th><th>Unidad</th><th>Cantidad</th><th>Valor unitario</th><th>Acción</th></tr></thead><tbody data-details-body>
<?php foreach($details as $i=>$line): $line=is_array($line)?$line:[]; ?><tr data-detail-row><td><?php $field("details[$i][producto_id]",'Producto','details.'.$i.'.producto_id',$line['producto_id']??'','producto_id');?></td><td><?php $field("details[$i][unidad_id]",'Unidad','details.'.$i.'.unidad_id',$line['unidad_id']??'','unidad_id');?></td><td><?php $field("details[$i][cantidad]",'Cantidad','details.'.$i.'.cantidad',$line['cantidad']??'','','number','min="0.001" step="0.001"');?></td><td><?php $field("details[$i][valor_unitario]",'Valor unitario','details.'.$i.'.valor_unitario',$line['valor_unitario']??'0','','number','min="0" step="0.01"');?></td><td><button type="button" class="btn btn-outline-danger btn-sm" data-remove-detail aria-label="Quitar producto">Quitar</button></td></tr><?php endforeach;?>
</tbody></table></div><p class="text-muted small" data-detail-feedback role="status">Puedes añadir hasta 200 productos. Al elegir un producto, su unidad base se completará automáticamente.</p>
</div><div class="card-footer d-flex gap-2 flex-wrap"><button class="btn btn-primary" type="submit" data-save>Guardar borrador</button><a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Cancelar</a></div></form>
<script src="<?=url('/assets/js/documentos-captura.js')?>" defer></script>

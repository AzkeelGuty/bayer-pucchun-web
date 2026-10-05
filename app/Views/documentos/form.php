<?php
$details=$details?:[['producto_id'=>'','unidad_id'=>'','cantidad'=>'1','valor_unitario'=>'0']];
$field=function(string $name,string $label,string $key,mixed $value,string $catalog='',string $type='text',string $extra='')use($catalogs,$errors):void {
    $id='doc-'.str_replace('.','-',$key); $error=$errors[$key]??''; $value=is_scalar($value)?$value:'';
    echo '<label class="form-label" for="'.e($id).'">'.e($label).'</label>';
    $attrs=' id="'.e($id).'" name="'.e($name).'" required aria-invalid="'.($error?'true':'false').'"'.($error?' aria-describedby="'.e($id).'-error"':'');
    if($catalog){
        $searchAttrs='';
        $searchable=[
            'tipo_documento_id'=>['Buscar tipo de documento...',''],
            'cliente_id'=>['Buscar por DNI/RUC o razón social...',url('/lookups?type=clientes')],
            'vendedor_id'=>['Buscar vendedor...',url('/lookups?type=vendedores')],
            'sucursal_id'=>['Buscar sucursal...',url('/lookups?type=sucursales')],
            'producto_id'=>['Buscar por código o producto...',url('/lookups?type=productos')],
        ];
        if(isset($searchable[$catalog])){
            [$placeholder,$remote]=$searchable[$catalog];
            $searchAttrs=' data-search-select data-search-placeholder="'.e($placeholder).'" data-search-min="1"';
            if($remote!=='') $searchAttrs.=' data-search-url="'.e($remote).'"';
        }
        echo '<select class="form-select'.($error?' is-invalid':'').'"'.$attrs.$searchAttrs.'><option value="">Seleccionar…</option>';
        foreach($catalogs[$catalog] as $item){
            $meta='';
            if($catalog==='producto_id'){
                $meta.=' data-unit-id="'.e((string)($item['unidad_base_id']??'')).'"';
                $meta.=' data-unit-label="'.e((string)($item['unidad_label']??'')).'"';
            }
            if($catalog==='cliente_id'){
                $meta.=' data-seller-id="'.e((string)($item['vendedor_sugerido_id']??'')).'"';
                $meta.=' data-seller-label="'.e((string)($item['vendedor_sugerido_label']??'')).'"';
                $meta.=' data-branch-id="'.e((string)($item['sucursal_sugerida_id']??'')).'"';
                $meta.=' data-branch-label="'.e((string)($item['sucursal_sugerida_label']??'')).'"';
            }
            echo '<option value="'.e($item['id']).'"'.$meta.((string)$value===(string)$item['id']?' selected':'').'>'.e($item['label']).'</option>';
        }
        echo '</select>';
    }
    else echo '<input class="form-control'.($error?' is-invalid':'').'" type="'.e($type).'" value="'.e(is_scalar($value)?$value:'').'"'.$attrs.' '.$extra.'>';
    if($error) echo '<div id="'.e($id).'-error" class="invalid-feedback">'.e($error).'</div>';
};
$correcting=$editing && (($header['estado_registro']??'')==='OBSERVADO');
?>
<link rel="stylesheet" href="<?=asset_url('assets/css/documentos-captura.css')?>">
<section class="module-header"><div><div class="page-eyebrow">DOCUMENTOS · <?= $editing?'EDICIÓN':'CAPTURA'?></div><h1 class="page-title"><?=$correcting?'Corregir documento':($editing?'Editar borrador':'Nuevo documento')?></h1><p class="page-subtitle">Selecciona los catálogos y agrega los productos del documento.</p></div><a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Volver al listado</a></section>
<?php if($errors): ?><div class="alert alert-danger" role="alert" tabindex="-1" data-error-summary><strong>Revisa la información antes de guardar.</strong><ul class="mb-0"><?php foreach($errors as $key=>$error): ?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<form class="card doc-screen" method="post" action="<?=url($editing?'/documentos/actualizar':'/documentos/guardar')?>" data-documents-form>
<?=csrf_field()?><?php if($editing):?><input type="hidden" name="id" value="<?=e($header['id'])?>"><input type="hidden" name="version" value="<?=e($header['version'])?>"><?php endif;?>
<div class="card-body p-4"><h2 class="h5 mb-3">Datos generales</h2><div class="row g-3">
<div class="col-12 col-md-6 col-xl-4"><?php $field('header[tipo_documento_id]','Tipo de documento','header.tipo_documento_id',$header['tipo_documento_id']??'','tipo_documento_id'); ?></div>
<div class="col-12 col-md-6 col-xl-4">
    <?php $field('header[numero]','Número','header.numero',$header['numero']??'','','text','maxlength="25" autocomplete="off"'); ?>
    <div class="form-text">Ingrese el número real del documento. El sistema no genera correlativos.</div>
</div>
<div class="col-12 col-md-6 col-xl-4"><?php $field('header[fecha]','Fecha','header.fecha',$header['fecha']??'','','date'); ?></div>
<div class="col-12 col-md-6 col-xl-4"><?php $field('header[cliente_id]','Cliente','header.cliente_id',$header['cliente_id']??'','cliente_id'); ?></div>
<div class="col-12 col-md-6 col-xl-4"><?php $field('header[vendedor_id]','Vendedor','header.vendedor_id',$header['vendedor_id']??'','vendedor_id'); ?></div>
<div class="col-12 col-md-6 col-xl-4"><?php $field('header[sucursal_id]','Sucursal','header.sucursal_id',$header['sucursal_id']??'','sucursal_id'); ?></div>
</div><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-3"><h2 class="h5 mb-0">Productos</h2><button type="button" class="btn btn-outline-primary" data-add-detail>+ Añadir producto al documento</button></div>
<div class="table-responsive"><table class="table align-middle doc-lines"><caption class="visually-hidden">Productos del documento</caption><thead><tr><th>Producto</th><th>Unidad</th><th>Cantidad</th><th>Valor unitario</th><th>Acción</th></tr></thead><tbody data-details-body>
<?php foreach($details as $i=>$line): $line=is_array($line)?$line:[]; ?><tr data-detail-row><td><?php $field("details[$i][producto_id]",'Producto','details.'.$i.'.producto_id',$line['producto_id']??'','producto_id');?></td><td><?php $field("details[$i][unidad_id]",'Unidad','details.'.$i.'.unidad_id',$line['unidad_id']??'','unidad_id');?></td><td><?php $field("details[$i][cantidad]",'Cantidad','details.'.$i.'.cantidad',(($line['cantidad']??'')!==''?format_quantity($line['cantidad']):''),'','number','min="1" step="1" inputmode="numeric"');?></td><td><?php $field("details[$i][valor_unitario]",'Valor unitario','details.'.$i.'.valor_unitario',$line['valor_unitario']??'0','','number','min="0" step="0.01"');?></td><td><button type="button" class="btn btn-outline-danger btn-sm" data-remove-detail aria-label="Quitar producto">Quitar</button></td></tr><?php endforeach;?>
</tbody></table></div>
</div><div class="card-footer d-flex gap-2 flex-wrap"><button class="btn btn-primary" type="submit" data-save><?=$correcting?'Guardar corrección':'Guardar borrador'?></button><a class="btn btn-outline-primary" href="<?=url('/documentos')?>">Cancelar</a></div></form>
<script src="<?=asset_url('assets/js/documentos-captura.js')?>" defer></script>

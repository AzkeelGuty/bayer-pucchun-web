<?php
function field($name,$label,$type='text',$required=true){$old=$_SESSION['_old'][$name]??'';$err=$_SESSION['_errors'][$name]??'';echo '<div class="col-md-4"><label class="form-label">'.e($label).'</label><input class="form-control '.($err?'is-invalid':'').'" type="'.e($type).'" name="'.e($name).'" value="'.e($old).'" '.($required?'required':'').'><div class="invalid-feedback">'.e($err).'</div></div>';}

function select_option_meta(array $opt): string {
    $map=[
        'unidad_base_id'=>'unit-id',
        'unidad_label'=>'unit-label',
        'vendedor_sugerido_id'=>'seller-id',
        'vendedor_sugerido_label'=>'seller-label',
        'sucursal_sugerida_id'=>'branch-id',
        'sucursal_sugerida_label'=>'branch-label',
        'departamento_id'=>'department-id',
        'departamento'=>'department-label',
        'provincia_id'=>'province-id',
        'provincia'=>'province-label',
        'distrito_id'=>'district-id',
        'distrito'=>'district-label',
    ];
    $attrs='';
    foreach($map as $key=>$dataKey){
        if(!array_key_exists($key,$opt) || $opt[$key]===null || $opt[$key]==='') continue;
        $attrs.=' data-'.$dataKey.'="'.e((string)$opt[$key]).'"';
    }
    return $attrs;
}

/**
 * Renders a <select> bound to a normalized master (id => label).
 * $options is a list of rows; $valueKey/$labelKey pick the columns to use.
 * $attrs is raw extra HTML attributes (e.g. data-* hooks for cascading JS).
 */
function select($name,$label,array $options,string $valueKey,string $labelKey,$required=true,string $attrs='',string $col='col-md-4'){
    $old=(string)old($name);$err=form_error($name);
    echo '<div class="'.e($col).'"><label class="form-label">'.e($label).'</label>';
    echo '<select class="form-select '.($err?'is-invalid':'').'" name="'.e($name).'" '.($required?'required':'').' '.$attrs.'>';
    echo '<option value="">Seleccione…</option>';
    foreach($options as $opt){
        $value=(string)$opt[$valueKey];
        $selected=$value===$old?' selected':'';
        echo '<option value="'.e($value).'"'.select_option_meta($opt).$selected.'>'.e($opt[$labelKey]).'</option>';
    }
    echo '</select><div class="invalid-feedback">'.e($err).'</div></div>';
}

/** Bare <select> (no label/wrapper), for repeatable detail-line rows inside a table. */
function select_inline(string $name,array $options,string $valueKey,string $labelKey,string $attrs='',string $placeholder='Seleccione…',bool $required=true,string $selected=''){
    echo '<select class="form-select form-select-sm" name="'.e($name).'" '.($required?'required':'').' '.$attrs.'>';
    echo '<option value="">'.e($placeholder).'</option>';
    foreach($options as $opt){
        $value=(string)$opt[$valueKey];
        $sel=$value===$selected?' selected':'';
        echo '<option value="'.e($value).'"'.select_option_meta($opt).$sel.'>'.e($opt[$labelKey]).'</option>';
    }
    echo '</select>';
}
?>
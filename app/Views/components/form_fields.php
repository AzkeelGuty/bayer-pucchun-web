<?php
function field($name,$label,$type='text',$required=true)
{
    $old=$_SESSION['_old'][$name]??'';
    $err=$_SESSION['_errors'][$name]??'';
    $step=$type==='number' ? ' step="any"' : '';
    $req=$required ? ' required' : '';
    echo '<div class="col-12 col-md-6 col-xl-4">';
    echo '<label class="form-label" for="'.e($name).'">'.e($label).'</label>';
    echo '<input id="'.e($name).'" class="form-control '.($err?'is-invalid':'').'" type="'.e($type).'" name="'.e($name).'" value="'.e($old).'"'.$step.$req.'>';
    if($err) echo '<div class="invalid-feedback">'.e($err).'</div>';
    echo '</div>';
}
?>
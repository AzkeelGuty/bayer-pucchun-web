<?php
require dirname(__DIR__,2).'/config/bootstrap.php';
$v=new App\Validators\BayerDataValidator();
$errors=$v->validate(['dealerId'=>'123','quantity'=>'-1'],'stock');
assert(isset($errors['dealerId'])); assert(isset($errors['quantity'])); echo "Validator tests OK\n";

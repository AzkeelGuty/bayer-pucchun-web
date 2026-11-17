<?php
require dirname(__DIR__,2).'/config/bootstrap.php';
$v=new App\Validators\BayerDataValidator();
$errors=$v->validate(['dealerId'=>'123','quantity'=>'-1'],'stock');
assert(isset($errors['dealerId']));
assert(isset($errors['quantity']));
assert(!isset($v->validate(['dealerId'=>'12345678901','quantity'=>'2'],'stock')['quantity']));
assert(!isset($v->validate(['dealerId'=>'12345678901','quantity'=>'2.000'],'stock')['quantity']));
assert(isset($v->validate(['dealerId'=>'12345678901','quantity'=>'2.500'],'stock')['quantity']));
echo "Validator tests OK\n";

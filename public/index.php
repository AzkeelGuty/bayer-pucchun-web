<?php
require dirname(__DIR__).'/config/bootstrap.php';
require dirname(__DIR__).'/routes/Router.php';
$router=new App\Routes\Router();
require dirname(__DIR__).'/routes/web.php';
if(request_method()==='POST') verify_csrf();
$router->dispatch(request_method(), $_SERVER['REQUEST_URI'] ?? '/');

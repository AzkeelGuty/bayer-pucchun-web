<?php
require_once dirname(__DIR__).'/app/Helpers/functions.php';
load_env(dirname(__DIR__).'/.env');
$app=config('app');
date_default_timezone_set($app['timezone']);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($app['session_name']);
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
    session_start();
}
if ($app['debug']) { ini_set('display_errors','1'); error_reporting(E_ALL); }
else { ini_set('display_errors','0'); error_reporting(E_ALL); }
spl_autoload_register(function($class){$prefix='App\\';if(str_starts_with($class,$prefix)){ $rel=str_replace('\\','/',substr($class,strlen($prefix))); $f=dirname(__DIR__).'/app/'.$rel.'.php'; if(is_file($f)) require $f; }});

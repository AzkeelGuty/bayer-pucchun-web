<?php
namespace App\Routes;
class Router {
    private array $routes=[];
    public function get(string $path, callable|array $handler): void {$this->routes['GET'][$path]=$handler;}
    public function post(string $path, callable|array $handler): void {$this->routes['POST'][$path]=$handler;}
    public function dispatch(string $method,string $uri): void {
        $path=parse_url($uri,PHP_URL_PATH) ?: '/';
        $base=parse_url(config('app.url',''),PHP_URL_PATH) ?: '';
        if($base && str_starts_with($path,$base)) $path=substr($path,strlen($base)) ?: '/';
        $handler=$this->routes[$method][$path]??null;
        if(!$handler){http_response_code(404); view('errors.404'); return;}
        if(is_array($handler)){[$class,$fn]=$handler; (new $class)->$fn();} else $handler();
    }
}

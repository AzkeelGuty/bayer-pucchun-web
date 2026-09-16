<?php
declare(strict_types=1);

namespace App\Routes;

use App\Exceptions\HttpException;

class Router
{
    private array $routes = [];
    private array $before = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes['GET'][$path] = [$handler, $middleware];
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes['POST'][$path] = [$handler, $middleware];
    }

    public function before(callable $middleware): void
    {
        $this->before[] = $middleware;
    }

    public function dispatch(string $method, string $uri): void
    {
        // REQUEST_URI is a path; parse_url treats //path as a hostname.
        if (!str_starts_with($uri, '/')) {
            throw new HttpException(404, 'Recurso no encontrado.');
        }
        $path = rawurldecode(explode('?', $uri, 2)[0]);
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new HttpException(404, 'Recurso no encontrado.');
        }
        // Normalize equivalent URL forms before enforcing protected namespaces.
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') array_pop($segments);
            else $segments[] = $segment;
        }
        $path = '/' . implode('/', $segments);
        $base = rtrim(parse_url(\config('app.url', ''), PHP_URL_PATH) ?: '', '/');
        if ($base && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        foreach ($this->before as $middleware) {
            $middleware($path);
        }
        $route = $this->routes[$method][$path] ?? null;
        if (!$route) {
            throw new HttpException(404, 'Recurso no encontrado.');
        }
        [$handler, $middleware] = $route;
        foreach ($middleware as $guard) {
            $guard->handle();
        }
        if ($method === 'POST') {
            \verify_csrf();
        }
        if (is_array($handler)) {
            [$class, $fn] = $handler;
            (new $class())->$fn();
        } else {
            $handler();
        }
    }
}

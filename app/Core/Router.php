<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\PlatformAdminMiddleware;
use App\Middleware\RoleMiddleware;

class Router
{
    private array $routes = [];

    private array $middlewareMap = [
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'platform_admin' => PlatformAdminMiddleware::class,
        'role' => RoleMiddleware::class,
    ];

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->register('POST', $path, $handler, $middleware);
    }

    private function register(string $method, string $path, mixed $handler, array $middleware): void
    {
        $normalizedPath = $this->normalizePath($path);
        $this->routes[$method][$normalizedPath] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $methodRoutes = $this->routes[$request->method()] ?? [];
        $route = $methodRoutes[$request->path()] ?? null;

        if ($route === null) {
            throw new HttpException(404, 'The requested page was not found.');
        }

        $destination = fn (Request $req) => $this->runHandler($route['handler'], $req);
        $pipeline = array_reduce(
            array_reverse($route['middleware']),
            fn (callable $next, string $middleware) => fn (Request $req) => $this->runMiddleware($middleware, $req, $next),
            $destination
        );

        $pipeline($request);
    }

    private function runMiddleware(string $specification, Request $request, callable $next): mixed
    {
        [$name, $parameters] = array_pad(explode(':', $specification, 2), 2, '');
        $class = $this->middlewareMap[$name] ?? $name;

        if (!class_exists($class)) {
            throw new HttpException(500, 'Middleware could not be resolved.');
        }

        $instance = new $class();

        return $instance->handle(
            request: $request,
            next: $next,
            parameters: $parameters !== '' ? array_map('trim', explode(',', $parameters)) : []
        );
    }

    private function runHandler(mixed $handler, Request $request): mixed
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass();

            return $controller->{$method}($request);
        }

        throw new HttpException(500, 'Route handler is invalid.');
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }
}

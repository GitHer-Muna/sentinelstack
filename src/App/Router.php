<?php
declare(strict_types=1);

namespace App;

/**
 * Tiny router with method+path matching.
 *
 * Routes are registered as closures or [Class, method] pairings.
 * Authentication / CSRF are enforced inside controllers.
 */
final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable|array}> */
    private array $routes = [];
    private string $method;
    private string $path;

    public function __construct(private string $root)
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // If the request URI starts with the project base (rare; mainly for shared-dev)
        // strip it. .htaccess sends everything to /index.php so this is unused
        // for most setups, but it makes the app robust if installed in a subdirectory.
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $this->path = '/' . ltrim($uri, '/');
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->routes[] = ['method' => 'GET',  'pattern' => $path, 'handler' => $handler];
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes[] = ['method' => 'POST', 'pattern' => $path, 'handler' => $handler];
    }

    public function dispatch(): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->method) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{([\w]+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $this->path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (!is_int($k)) {
                        $params[$k] = $v;
                    }
                }
                $this->invoke($route['handler'], $params);
                return;
            }
        }

        Response::abort(404, 'Page not found.');
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            [$class, $method] = $handler;
            $controller = new $class($this->root);
            $controller->$method(...array_values($params));
            return;
        }
        if (is_callable($handler)) {
            call_user_func_array($handler, array_values($params));
            return;
        }
        Response::abort(500, 'Route handler not callable.');
    }
}

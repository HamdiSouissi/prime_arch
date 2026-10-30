<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : routes statiques et paramètres {nom}.
 *
 * Exemple :
 *   $router->get('/clients/{id}', [ClientController::class, 'show']);
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:mixed,middleware:array}> */
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $pattern = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', '/' . trim($path, '/')) . '$#u';
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Trouve la route correspondante et exécute son contrôleur.
     */
    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            if (!preg_match($route['pattern'], $request->path(), $matches)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Middlewares : chaque classe expose handle(Request): ?Response
            foreach ($route['middleware'] as $middlewareClass) {
                $result = (new $middlewareClass())->handle($request);
                if ($result instanceof Response) {
                    return $result; // interruption (redirection, 403…)
                }
            }

            return $this->invoke($route['handler'], $request, $params);
        }

        return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
    }

    private function invoke(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $handler = [new $class(), $method];
        }
        $result = $handler($request, ...array_values($params));
        return $result instanceof Response ? $result : Response::html((string) $result);
    }
}

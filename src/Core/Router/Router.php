<?php

namespace App\Core\Router;

use App\Core\Container\Container;

/**
 * Router with support for dynamic parameters and middleware
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private array $groupMiddlewares = [];
    private string $prefix = '';
    private ?Container $container = null;
    private array $middlewareCache = [];
    private array $staticRoutes = [];

    /**
     * Set the container instance
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Add a GET route
     */
    public function get(string $uri, $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    /**
     * Add a POST route
     */
    public function post(string $uri, $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Add a route
     */
    public function addRoute(string $method, string $uri, $action): self
    {
        $uri = $this->prefix . $uri;
        $pattern = $this->convertToPattern($uri);

        $route = [
            'method' => $method,
            'uri' => $uri,
            'pattern' => $pattern,
            'action' => $action,
            'middlewares' => $this->groupMiddlewares,
        ];

        $this->routes[] = $route;

        // Cache static routes (no parameters like {id}) for O(1) lookup
        // Routes with parameters are matched using regex in the slow path
        if (!str_contains($uri, '{')) {
            $key = $method . ':' . $uri;
            $this->staticRoutes[$key] = $route;
        }

        return $this;
    }

    /**
     * Add middleware to last route
     */
    public function middleware($middleware): self
    {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            if (is_array($middleware)) {
                $this->routes[$lastIndex]['middlewares'] = array_merge(
                    $this->routes[$lastIndex]['middlewares'],
                    $middleware
                );
            } else {
                $this->routes[$lastIndex]['middlewares'][] = $middleware;
            }
        }
        return $this;
    }

    /**
     * Create a route group with prefix and middleware
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddlewares = $this->groupMiddlewares;

        if (isset($attributes['prefix'])) {
            $this->prefix .= $attributes['prefix'];
        }

        if (isset($attributes['middleware'])) {
            $this->groupMiddlewares = array_merge(
                $this->groupMiddlewares,
                is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']]
            );
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Dispatch the request
     */
    public function dispatch(string $method, string $uri): mixed
    {
        // Handle method override
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri = parse_url($uri, PHP_URL_PATH);

        // Fast path: Check static routes first (O(1) lookup)
        $staticKey = $method . ':' . $uri;
        if (isset($this->staticRoutes[$staticKey])) {
            $route = $this->staticRoutes[$staticKey];
            
            // Run middlewares
            foreach ($route['middlewares'] as $middleware) {
                $middlewareInstance = $this->resolveMiddleware($middleware);
                if ($middlewareInstance && !$middlewareInstance->handle()) {
                    return null;
                }
            }

            return $this->callAction($route['action'], []);
        }

        // Slow path: Check dynamic routes with parameters
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // Remove full match

                // Run middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $middlewareInstance = $this->resolveMiddleware($middleware);
                    if ($middlewareInstance && !$middlewareInstance->handle()) {
                        return null;
                    }
                }

                return $this->callAction($route['action'], $matches);
            }
        }

        // 404 Not Found
        http_response_code(404);
        throw new \Exception("Route not found: {$method} {$uri}");
    }

    /**
     * Convert URI to regex pattern
     */
    private function convertToPattern(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Call the route action
     */
    private function callAction($action, array $params): mixed
    {
        if (is_callable($action)) {
            return call_user_func_array($action, $params);
        }

        if (is_string($action)) {
            [$controller, $method] = explode('@', $action);
            $controllerClass = "App\\Presentation\\Controllers\\{$controller}";

            if (!class_exists($controllerClass)) {
                throw new \Exception("Controller not found: {$controllerClass}");
            }

            // Use container to resolve controller with dependencies
            if ($this->container !== null) {
                try {
                    $controllerInstance = $this->container->make($controllerClass);
                } catch (\Exception $e) {
                    throw new \Exception("Failed to resolve controller {$controllerClass}: " . $e->getMessage());
                }
            } else {
                // Fallback to direct instantiation (no DI)
                $controllerInstance = new $controllerClass();
            }

            if (!method_exists($controllerInstance, $method)) {
                throw new \Exception("Method not found: {$controllerClass}@{$method}");
            }

            return call_user_func_array([$controllerInstance, $method], $params);
        }

        throw new \Exception("Invalid route action");
    }

    /**
     * Resolve middleware instance with caching
     */
    private function resolveMiddleware(string $middleware)
    {
        // Return cached middleware instance if available
        if (isset($this->middlewareCache[$middleware])) {
            return $this->middlewareCache[$middleware];
        }

        $middlewareClass = "App\\Presentation\\Middlewares\\{$middleware}";

        if (!class_exists($middlewareClass)) {
            return null;
        }

        // Cache the middleware instance
        $this->middlewareCache[$middleware] = new $middlewareClass();
        return $this->middlewareCache[$middleware];
    }

    /**
     * Load routes from configuration
     */
    public function loadRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            [$method, $uri, $action] = $route;
            $this->addRoute($method, $uri, $action);
        }
    }
}

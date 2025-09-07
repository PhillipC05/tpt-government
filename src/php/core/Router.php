<?php
/**
 * TPT Government Platform - Router
 *
 * Simple and secure URL router for the government platform.
 * Supports route groups, middleware, and parameter extraction.
 */

namespace Core;

class Router
{
    /**
     * Request instance
     */
    private Request $request;

    /**
     * Response instance
     */
    private Response $response;

    /**
     * Route collection
     */
    private array $routes = [];

    /**
     * Route groups stack
     */
    private array $groupStack = [];

    /**
     * Constructor
     *
     * @param Request $request The request instance
     * @param Response $response The response instance
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Register a GET route
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function get(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function post(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function put(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function delete(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function patch(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Register an OPTIONS route
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function options(string $path, $handler, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Register routes for any HTTP method
     *
     * @param array $methods The HTTP methods
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function match(array $methods, string $path, $handler, array $middleware = []): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middleware);
        }
        return $this;
    }

    /**
     * Register routes for all HTTP methods
     *
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    public function any(string $path, $handler, array $middleware = []): self
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        return $this->match($methods, $path, $handler, $middleware);
    }

    /**
     * Create a route group
     *
     * @param string $prefix The route prefix
     * @param callable $callback The callback function
     * @param array $middleware The middleware array
     * @return self
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        // Add to group stack
        $this->groupStack[] = [
            'prefix' => $this->formatPrefix($prefix),
            'middleware' => $middleware
        ];

        // Execute callback
        $callback($this);

        // Remove from group stack
        array_pop($this->groupStack);

        return $this;
    }

    /**
     * Add a route to the collection
     *
     * @param string $method The HTTP method
     * @param string $path The route path
     * @param callable|array $handler The route handler
     * @param array $middleware The middleware array
     * @return self
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): self
    {
        // Apply group prefixes and middleware
        $fullPath = $this->applyGroupPrefix($path);
        $allMiddleware = $this->mergeGroupMiddleware($middleware);

        // Create route
        $route = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $allMiddleware,
            'parameters' => $this->extractParameters($fullPath)
        ];

        // Add to routes collection
        $this->routes[] = $route;

        return $this;
    }

    /**
     * Apply group prefix to path
     *
     * @param string $path The route path
     * @return string The prefixed path
     */
    private function applyGroupPrefix(string $path): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= rtrim($group['prefix'], '/');
        }
        return $prefix . $path;
    }

    /**
     * Merge group middleware with route middleware
     *
     * @param array $middleware The route middleware
     * @return array The merged middleware
     */
    private function mergeGroupMiddleware(array $middleware): array
    {
        $allMiddleware = [];
        foreach ($this->groupStack as $group) {
            $allMiddleware = array_merge($allMiddleware, $group['middleware']);
        }
        return array_merge($allMiddleware, $middleware);
    }

    /**
     * Format prefix for consistency
     *
     * @param string $prefix The prefix
     * @return string The formatted prefix
     */
    private function formatPrefix(string $prefix): string
    {
        return '/' . trim($prefix, '/');
    }

    /**
     * Extract parameters from route path
     *
     * @param string $path The route path
     * @return array The parameters
     */
    private function extractParameters(string $path): array
    {
        $parameters = [];
        $pattern = '/\{([^}]+)\}/';

        if (preg_match_all($pattern, $path, $matches)) {
            $parameters = $matches[1];
        }

        return $parameters;
    }

    /**
     * Dispatch the request to the appropriate route
     *
     * @return array|null The matched route or null if not found
     */
    public function dispatch(): ?array
    {
        $method = $this->request->getMethod();
        $path = $this->request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] === $method || $route['method'] === 'ANY') {
                $matches = $this->matchRoute($route['path'], $path);

                if ($matches !== false) {
                    // Extract parameters
                    $parameters = [];
                    if (!empty($route['parameters'])) {
                        foreach ($route['parameters'] as $param) {
                            $parameters[$param] = $matches[$param] ?? null;
                        }
                    }

                    return [
                        'controller' => $route['handler'],
                        'method' => $method,
                        'middleware' => $route['middleware'],
                        'parameters' => $parameters,
                        'route' => $route
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Match a route pattern against a path
     *
     * @param string $pattern The route pattern
     * @param string $path The request path
     * @return array|false The matches or false if not matched
     */
    private function matchRoute(string $pattern, string $path)
    {
        // Convert route parameters to regex
        $regex = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $pattern);

        // Add start and end anchors
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            return $matches;
        }

        return false;
    }

    /**
     * Get all registered routes
     *
     * @return array The routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clear all routes
     *
     * @return self
     */
    public function clear(): self
    {
        $this->routes = [];
        $this->groupStack = [];
        return $this;
    }

    /**
     * Generate URL for a named route
     *
     * @param string $name The route name
     * @param array $parameters The route parameters
     * @return string The generated URL
     */
    public function url(string $name, array $parameters = []): string
    {
        // This would require named routes implementation
        // For now, return the name as placeholder
        return '/' . $name;
    }
}

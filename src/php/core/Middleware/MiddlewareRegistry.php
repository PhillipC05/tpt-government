<?php
/**
 * TPT Government Platform - Middleware Registry
 *
 * Centralized middleware management system for security and request processing.
 */

namespace Core\Middleware;

use Core\DependencyInjection\Container;

class MiddlewareRegistry
{
    /**
     * Registered middleware classes
     */
    private array $middleware = [];

    /**
     * Middleware groups
     */
    private array $groups = [];

    /**
     * Middleware priorities
     */
    private array $priorities = [];

    /**
     * Dependency injection container
     */
    private ?Container $container = null;

    /**
     * Constructor
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container;
        $this->registerDefaultMiddleware();
    }

    /**
     * Register default middleware
     */
    private function registerDefaultMiddleware(): void
    {
        // Security middleware
        $this->register('csrf', CsrfMiddleware::class, 100);
        $this->register('security_headers', SecurityHeadersMiddleware::class, 90);
        $this->register('rate_limit', RateLimitMiddleware::class, 80);

        // Authentication middleware
        $this->register('auth', AuthMiddleware::class, 70);
        $this->register('admin', AdminMiddleware::class, 60);

        // Request processing middleware
        $this->register('cors', CorsMiddleware::class, 50);
        $this->register('json_parser', JsonParserMiddleware::class, 40);
        $this->register('input_sanitizer', InputSanitizerMiddleware::class, 30);

        // Define middleware groups
        $this->groups = [
            'api' => ['cors', 'rate_limit', 'json_parser', 'input_sanitizer', 'auth'],
            'web' => ['csrf', 'security_headers', 'input_sanitizer'],
            'admin' => ['auth', 'admin', 'security_headers'],
            'public' => ['cors', 'rate_limit']
        ];
    }

    /**
     * Register a middleware
     *
     * @param string $name Middleware name
     * @param string $class Middleware class
     * @param int $priority Priority (higher = executed first)
     * @return self
     */
    public function register(string $name, string $class, int $priority = 50): self
    {
        $this->middleware[$name] = $class;
        $this->priorities[$name] = $priority;
        return $this;
    }

    /**
     * Unregister a middleware
     *
     * @param string $name Middleware name
     * @return self
     */
    public function unregister(string $name): self
    {
        unset($this->middleware[$name], $this->priorities[$name]);
        return $this;
    }

    /**
     * Check if middleware is registered
     *
     * @param string $name Middleware name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->middleware[$name]);
    }

    /**
     * Get middleware class
     *
     * @param string $name Middleware name
     * @return string|null
     */
    public function get(string $name): ?string
    {
        return $this->middleware[$name] ?? null;
    }

    /**
     * Get all registered middleware
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->middleware;
    }

    /**
     * Get middleware for a group
     *
     * @param string $group Group name
     * @return array
     */
    public function getGroup(string $group): array
    {
        return $this->groups[$group] ?? [];
    }

    /**
     * Create middleware group
     *
     * @param string $name Group name
     * @param array $middleware Middleware names
     * @return self
     */
    public function createGroup(string $name, array $middleware): self
    {
        $this->groups[$name] = $middleware;
        return $this;
    }

    /**
     * Resolve middleware instance
     *
     * @param string $name Middleware name
     * @return MiddlewareInterface|null
     */
    public function resolve(string $name): ?MiddlewareInterface
    {
        $class = $this->get($name);
        if (!$class || !class_exists($class)) {
            return null;
        }

        // Use container if available
        if ($this->container && $this->container->has($class)) {
            return $this->container->get($class);
        }

        // Create instance manually
        return new $class();
    }

    /**
     * Execute middleware stack
     *
     * @param array $middleware Middleware names
     * @param mixed $request Request object
     * @param mixed $response Response object
     * @param callable $next Next handler
     * @return mixed
     */
    public function execute(array $middleware, $request, $response, callable $next)
    {
        // Sort middleware by priority
        $sortedMiddleware = $this->sortByPriority($middleware);

        // Create middleware chain
        $chain = $next;
        foreach (array_reverse($sortedMiddleware) as $middlewareName) {
            $middlewareInstance = $this->resolve($middlewareName);
            if ($middlewareInstance) {
                $chain = function ($req, $res) use ($middlewareInstance, $chain) {
                    return $middlewareInstance->handle($req, $res, $chain);
                };
            }
        }

        return $chain($request, $response);
    }

    /**
     * Sort middleware by priority
     *
     * @param array $middleware Middleware names
     * @return array Sorted middleware
     */
    private function sortByPriority(array $middleware): array
    {
        $sorted = [];
        $withPriority = [];

        foreach ($middleware as $name) {
            if (isset($this->priorities[$name])) {
                $withPriority[$name] = $this->priorities[$name];
            } else {
                $sorted[] = $name;
            }
        }

        // Sort by priority (higher first)
        arsort($withPriority);

        return array_merge(array_keys($withPriority), $sorted);
    }

    /**
     * Get middleware statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total_middleware' => count($this->middleware),
            'total_groups' => count($this->groups),
            'middleware_by_priority' => $this->priorities,
            'groups' => $this->groups
        ];
    }

    /**
     * Clear all middleware
     *
     * @return self
     */
    public function clear(): self
    {
        $this->middleware = [];
        $this->groups = [];
        $this->priorities = [];
        return $this;
    }
}

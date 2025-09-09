<?php
/**
 * TPT Government Platform - Optimized Router
 *
 * High-performance router with caching, pre-compiled patterns, and advanced matching.
 * Implements route result caching to avoid repeated regex matching.
 */

namespace Core;

use Core\Interfaces\CacheInterface;

class RouterOptimized extends Router
{
    /**
     * Route cache instance
     */
    private ?CacheInterface $cache = null;

    /**
     * Compiled route patterns cache
     */
    private array $compiledPatterns = [];

    /**
     * Route dispatch cache
     */
    private array $dispatchCache = [];

    /**
     * Cache TTL for route results
     */
    private int $cacheTtl = 3600; // 1 hour

    /**
     * Enable route caching
     */
    private bool $cachingEnabled = true;

    /**
     * Cache key prefix
     */
    private string $cachePrefix = 'router:';

    /**
     * Constructor
     *
     * @param Request $request The request instance
     * @param Response $response The response instance
     * @param CacheInterface|null $cache The cache instance
     */
    public function __construct(Request $request, Response $response, ?CacheInterface $cache = null)
    {
        parent::__construct($request, $response);
        $this->cache = $cache;
    }

    /**
     * Add a route to the collection with pre-compilation
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

        // Pre-compile the route pattern
        $compiledPattern = $this->preCompilePattern($fullPath);

        // Create route with compiled pattern
        $route = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $allMiddleware,
            'parameters' => $this->extractParameters($fullPath),
            'compiled_pattern' => $compiledPattern,
            'pattern_hash' => md5($fullPath)
        ];

        // Add to routes collection
        $this->routes[] = $route;

        // Clear dispatch cache when routes change
        $this->clearDispatchCache();

        return $this;
    }

    /**
     * Pre-compile route pattern for faster matching
     *
     * @param string $pattern The route pattern
     * @return string The compiled regex pattern
     */
    private function preCompilePattern(string $pattern): string
    {
        $patternHash = md5($pattern);

        // Check if already compiled
        if (isset($this->compiledPatterns[$patternHash])) {
            return $this->compiledPatterns[$patternHash];
        }

        // Convert route parameters to named capture groups
        $compiled = preg_replace_callback('/\{([^}]+)\}/', function($matches) {
            $param = $matches[1];
            return "(?P<{$param}>[^/]+)";
        }, $pattern);

        // Add start and end anchors
        $compiled = '#^' . $compiled . '$#';

        // Cache the compiled pattern
        $this->compiledPatterns[$patternHash] = $compiled;

        return $compiled;
    }

    /**
     * Dispatch the request with caching
     *
     * @return array|null The matched route or null if not found
     */
    public function dispatch(): ?array
    {
        $method = $this->request->getMethod();
        $path = $this->request->getPath();

        // Create cache key
        $cacheKey = $this->cachePrefix . 'dispatch:' . md5($method . ':' . $path);

        // Check cache first
        if ($this->cachingEnabled && $this->cache) {
            $cachedResult = $this->cache->get($cacheKey);
            if ($cachedResult !== null) {
                return $cachedResult === false ? null : $cachedResult;
            }
        }

        // Perform route matching
        $result = $this->performDispatch($method, $path);

        // Cache the result
        if ($this->cachingEnabled && $this->cache) {
            $this->cache->set($cacheKey, $result ?: false, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Perform the actual route dispatch
     *
     * @param string $method The HTTP method
     * @param string $path The request path
     * @return array|null The matched route or null if not found
     */
    private function performDispatch(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method || $route['method'] === 'ANY') {
                $matches = $this->matchRouteOptimized($route, $path);

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
     * Optimized route matching using pre-compiled patterns
     *
     * @param array $route The route configuration
     * @param string $path The request path
     * @return array|false The matches or false if not matched
     */
    private function matchRouteOptimized(array $route, string $path)
    {
        $pattern = $route['compiled_pattern'];

        if (preg_match($pattern, $path, $matches)) {
            return $matches;
        }

        return false;
    }

    /**
     * Clear dispatch cache
     *
     * @return void
     */
    private function clearDispatchCache(): void
    {
        $this->dispatchCache = [];

        // Clear cache if available
        if ($this->cache) {
            $this->cache->clear($this->cachePrefix . 'dispatch:*');
        }
    }

    /**
     * Warm up route cache for common routes
     *
     * @param array $commonRoutes Array of common route paths
     * @return void
     */
    public function warmRouteCache(array $commonRoutes): void
    {
        if (!$this->cache) {
            return;
        }

        foreach ($commonRoutes as $routePath) {
            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
                $cacheKey = $this->cachePrefix . 'dispatch:' . md5($method . ':' . $routePath);
                $result = $this->performDispatch($method, $routePath);
                $this->cache->set($cacheKey, $result ?: false, $this->cacheTtl);
            }
        }
    }

    /**
     * Get route matching statistics
     *
     * @return array Statistics about route matching performance
     */
    public function getRouteStats(): array
    {
        return [
            'total_routes' => count($this->routes),
            'compiled_patterns' => count($this->compiledPatterns),
            'cache_enabled' => $this->cachingEnabled && $this->cache !== null,
            'cache_ttl' => $this->cacheTtl,
            'cache_prefix' => $this->cachePrefix
        ];
    }

    /**
     * Enable or disable route caching
     *
     * @param bool $enabled Whether to enable caching
     * @return self
     */
    public function setCachingEnabled(bool $enabled): self
    {
        $this->cachingEnabled = $enabled;
        return $this;
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl Cache TTL in seconds
     * @return self
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Set cache instance
     *
     * @param CacheInterface $cache The cache instance
     * @return self
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Get compiled patterns for debugging
     *
     * @return array The compiled patterns
     */
    public function getCompiledPatterns(): array
    {
        return $this->compiledPatterns;
    }

    /**
     * Clear all caches
     *
     * @return void
     */
    public function clearAllCaches(): void
    {
        $this->compiledPatterns = [];
        $this->dispatchCache = [];

        if ($this->cache) {
            $this->cache->clear($this->cachePrefix . '*');
        }
    }

    /**
     * Override parent clear method to also clear caches
     *
     * @return self
     */
    public function clear(): self
    {
        parent::clear();
        $this->clearAllCaches();
        return $this;
    }
}

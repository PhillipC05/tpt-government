<?php
/**
 * TPT Government Platform - API Gateway
 *
 * Specialized manager for API routing, rate limiting, and request processing
 */

namespace Core\APIMarketplace;

use Core\Database;
use Core\RateLimiter;

class APIGateway
{
    /**
     * Gateway configuration
     */
    private array $config = [
        'enabled' => true,
        'rate_limiting' => true,
        'request_logging' => true,
        'cors_enabled' => true,
        'api_versioning' => true,
        'response_caching' => true,
        'request_timeout' => 30,
        'max_request_size' => 10485760, // 10MB
        'allowed_origins' => ['*'],
        'supported_versions' => ['v1', 'v2'],
        'default_version' => 'v1'
    ];

    /**
     * API routes registry
     */
    private array $routes = [];

    /**
     * Middleware stack
     */
    private array $middleware = [];

    /**
     * Rate limiter instance
     */
    private RateLimiter $rateLimiter;

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Request/response cache
     */
    private array $responseCache = [];

    /**
     * API metrics
     */
    private array $metrics = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'average_response_time' => 0,
        'requests_per_minute' => 0
    ];

    /**
     * Constructor
     */
    public function __construct(Database $database, RateLimiter $rateLimiter, array $config = [])
    {
        $this->database = $database;
        $this->rateLimiter = $rateLimiter;
        $this->config = array_merge($this->config, $config);
        $this->initializeGateway();
    }

    /**
     * Initialize API gateway
     */
    private function initializeGateway(): void
    {
        // Load API routes
        $this->loadRoutes();

        // Set up middleware
        $this->setupMiddleware();

        // Initialize metrics collection
        $this->initializeMetrics();

        // Set up CORS headers
        if ($this->config['cors_enabled']) {
            $this->setupCORS();
        }
    }

    /**
     * Handle incoming API request
     */
    public function handleRequest(string $method, string $path, array $data = [], array $headers = []): array
    {
        $startTime = microtime(true);

        try {
            // Parse request
            $parsedRequest = $this->parseRequest($method, $path, $data, $headers);

            // Apply middleware
            $middlewareResult = $this->applyMiddleware($parsedRequest);
            if (!$middlewareResult['success']) {
                return $middlewareResult;
            }

            // Check rate limits
            if ($this->config['rate_limiting']) {
                $rateLimitResult = $this->checkRateLimit($parsedRequest);
                if (!$rateLimitResult['allowed']) {
                    return [
                        'success' => false,
                        'error' => 'Rate limit exceeded',
                        'retry_after' => $rateLimitResult['retry_after'],
                        'status_code' => 429
                    ];
                }
            }

            // Check response cache
            if ($this->config['response_caching'] && $method === 'GET') {
                $cachedResponse = $this->getCachedResponse($parsedRequest);
                if ($cachedResponse) {
                    $this->updateMetrics($startTime, true);
                    return $cachedResponse;
                }
            }

            // Route request
            $routeResult = $this->routeRequest($parsedRequest);
            if (!$routeResult['success']) {
                return $routeResult;
            }

            // Execute request
            $response = $this->executeRequest($routeResult['route'], $parsedRequest);

            // Cache response if applicable
            if ($this->config['response_caching'] && $method === 'GET' && $response['success']) {
                $this->cacheResponse($parsedRequest, $response);
            }

            // Log request
            if ($this->config['request_logging']) {
                $this->logRequest($parsedRequest, $response, microtime(true) - $startTime);
            }

            $this->updateMetrics($startTime, $response['success']);
            return $response;

        } catch (\Exception $e) {
            $this->updateMetrics($startTime, false);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Register API route
     */
    public function registerRoute(string $method, string $path, callable $handler, array $config = []): void
    {
        $routeKey = $this->generateRouteKey($method, $path);

        $this->routes[$routeKey] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'config' => array_merge([
                'auth_required' => false,
                'rate_limit' => 100,
                'cache_ttl' => 300,
                'version' => $this->config['default_version']
            ], $config),
            'pattern' => $this->convertPathToPattern($path),
            'registered_at' => time()
        ];
    }

    /**
     * Register middleware
     */
    public function registerMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Get API metrics
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'uptime' => time() - $_SERVER['REQUEST_TIME'] ?? time(),
            'registered_routes' => count($this->routes),
            'active_middleware' => count($this->middleware),
            'cache_size' => count($this->responseCache)
        ]);
    }

    /**
     * Get registered routes
     */
    public function getRoutes(): array
    {
        $routes = [];

        foreach ($this->routes as $routeKey => $route) {
            $routes[] = [
                'method' => $route['method'],
                'path' => $route['path'],
                'config' => $route['config'],
                'registered_at' => $route['registered_at']
            ];
        }

        return $routes;
    }

    /**
     * Clear response cache
     */
    public function clearCache(): bool
    {
        $this->responseCache = [];
        return true;
    }

    /**
     * Get API documentation
     */
    public function getDocumentation(): array
    {
        $documentation = [
            'version' => '1.0',
            'base_url' => '/api',
            'supported_versions' => $this->config['supported_versions'],
            'authentication' => [
                'type' => 'Bearer Token',
                'header' => 'Authorization: Bearer {token}'
            ],
            'rate_limiting' => [
                'enabled' => $this->config['rate_limiting'],
                'limits' => '100 requests per minute per user'
            ],
            'endpoints' => []
        ];

        foreach ($this->routes as $route) {
            $documentation['endpoints'][] = [
                'method' => $route['method'],
                'path' => $route['path'],
                'description' => $route['config']['description'] ?? 'API endpoint',
                'auth_required' => $route['config']['auth_required'],
                'version' => $route['config']['version']
            ];
        }

        return $documentation;
    }

    // Private helper methods

    private function parseRequest(string $method, string $path, array $data, array $headers): array
    {
        // Extract API version from path or headers
        $version = $this->extractVersion($path, $headers);

        // Parse path parameters
        $pathParts = explode('/', trim($path, '/'));
        $endpoint = implode('/', array_slice($pathParts, 1)); // Remove 'api' prefix

        return [
            'method' => $method,
            'path' => $path,
            'endpoint' => $endpoint,
            'version' => $version,
            'data' => $data,
            'headers' => $headers,
            'query_params' => $_GET ?? [],
            'timestamp' => time(),
            'request_id' => uniqid('req_')
        ];
    }

    private function applyMiddleware(array $request): array
    {
        foreach ($this->middleware as $middleware) {
            $result = $middleware($request);
            if (!$result['success']) {
                return $result;
            }
        }

        return ['success' => true];
    }

    private function checkRateLimit(array $request): array
    {
        $identifier = $request['headers']['x-api-key'] ?? $request['headers']['authorization'] ?? 'anonymous';

        return $this->rateLimiter->checkLimit($identifier, 'api_requests', 100, 60); // 100 requests per minute
    }

    private function routeRequest(array $request): array
    {
        $routeKey = $this->generateRouteKey($request['method'], $request['endpoint']);

        if (!isset($this->routes[$routeKey])) {
            // Try pattern matching for parameterized routes
            foreach ($this->routes as $key => $route) {
                if ($route['method'] === $request['method'] &&
                    preg_match($route['pattern'], $request['endpoint'])) {
                    $routeKey = $key;
                    break;
                }
            }
        }

        if (!isset($this->routes[$routeKey])) {
            return [
                'success' => false,
                'error' => 'Route not found',
                'status_code' => 404
            ];
        }

        return [
            'success' => true,
            'route' => $this->routes[$routeKey],
            'route_key' => $routeKey
        ];
    }

    private function executeRequest(array $route, array $request): array
    {
        try {
            $handler = $route['handler'];
            $response = $handler($request);

            // Ensure response has success field
            if (!isset($response['success'])) {
                $response['success'] = true;
            }

            // Add standard response fields
            $response['request_id'] = $request['request_id'];
            $response['timestamp'] = time();

            return $response;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Handler execution failed',
                'message' => $e->getMessage(),
                'status_code' => 500,
                'request_id' => $request['request_id']
            ];
        }
    }

    private function getCachedResponse(array $request): ?array
    {
        $cacheKey = $this->generateCacheKey($request);

        if (isset($this->responseCache[$cacheKey])) {
            $cached = $this->responseCache[$cacheKey];

            // Check if cache is still valid
            if (time() - $cached['cached_at'] < ($cached['ttl'] ?? 300)) {
                return $cached['response'];
            } else {
                unset($this->responseCache[$cacheKey]);
            }
        }

        return null;
    }

    private function cacheResponse(array $request, array $response): void
    {
        $cacheKey = $this->generateCacheKey($request);
        $route = $this->routes[$this->generateRouteKey($request['method'], $request['endpoint'])] ?? null;

        if ($route && isset($route['config']['cache_ttl'])) {
            $this->responseCache[$cacheKey] = [
                'response' => $response,
                'cached_at' => time(),
                'ttl' => $route['config']['cache_ttl']
            ];
        }
    }

    private function logRequest(array $request, array $response, float $duration): void
    {
        // In real implementation, log to database or file
        $logEntry = [
            'request_id' => $request['request_id'],
            'method' => $request['method'],
            'path' => $request['path'],
            'status_code' => $response['status_code'] ?? 200,
            'duration' => round($duration * 1000, 2), // milliseconds
            'timestamp' => time(),
            'user_agent' => $request['headers']['user-agent'] ?? 'Unknown',
            'ip_address' => $request['headers']['x-forwarded-for'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];

        // Store log entry (in real implementation)
    }

    private function updateMetrics(float $startTime, bool $success): void
    {
        $duration = microtime(true) - $startTime;

        $this->metrics['total_requests']++;
        $this->metrics['average_response_time'] =
            ($this->metrics['average_response_time'] * ($this->metrics['total_requests'] - 1) + $duration) /
            $this->metrics['total_requests'];

        if ($success) {
            $this->metrics['successful_requests']++;
        } else {
            $this->metrics['failed_requests']++;
        }
    }

    private function extractVersion(string $path, array $headers): string
    {
        // Check Accept header for version
        $acceptHeader = $headers['accept'] ?? '';
        if (preg_match('/version=(\w+)/', $acceptHeader, $matches)) {
            return $matches[1];
        }

        // Check path for version prefix
        if (preg_match('#^/api/(v\d+)/#', $path, $matches)) {
            return $matches[1];
        }

        return $this->config['default_version'];
    }

    private function generateRouteKey(string $method, string $path): string
    {
        return strtolower($method) . ':' . trim($path, '/');
    }

    private function convertPathToPattern(string $path): string
    {
        // Convert {param} to regex pattern
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function generateCacheKey(array $request): string
    {
        return md5($request['method'] . ':' . $request['endpoint'] . ':' . serialize($request['query_params']));
    }

    private function loadRoutes(): void
    {
        // In real implementation, load routes from configuration or database
    }

    private function setupMiddleware(): void
    {
        // Set up default middleware
        $this->registerMiddleware(function($request) {
            // Authentication middleware
            return ['success' => true];
        });

        $this->registerMiddleware(function($request) {
            // Request validation middleware
            return ['success' => true];
        });
    }

    private function initializeMetrics(): void
    {
        // Initialize metrics collection
    }

    private function setupCORS(): void
    {
        // Set up CORS headers
        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: ' . implode(', ', $this->config['allowed_origins']));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        }
    }
}

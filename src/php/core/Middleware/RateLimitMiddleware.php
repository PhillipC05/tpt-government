<?php
/**
 * TPT Government Platform - Rate Limiting Middleware
 *
 * Protects against abuse by limiting request rates per IP/client.
 */

namespace Core\Middleware;

use Core\Request;
use Core\Response;
use Core\DependencyInjection\Container;

class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Rate limit storage
     */
    private array $limits = [];

    /**
     * Dependency injection container
     */
    private ?Container $container = null;

    /**
     * Default rate limits
     */
    private array $defaultLimits = [
        'api' => [
            'requests' => 1000,  // requests per window
            'window' => 3600     // 1 hour in seconds
        ],
        'auth' => [
            'requests' => 5,     // login attempts
            'window' => 900      // 15 minutes
        ],
        'general' => [
            'requests' => 100,   // general requests
            'window' => 60       // 1 minute
        ]
    ];

    /**
     * Constructor
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container;
        $this->loadLimits();
    }

    /**
     * Handle rate limiting
     *
     * @param Request $request Request object
     * @param Response $response Response object
     * @param callable $next Next middleware
     * @return mixed
     */
    public function handle($request, $response, callable $next)
    {
        $clientId = $this->getClientIdentifier($request);
        $endpoint = $this->getEndpointType($request);

        // Check rate limit
        if ($this->isRateLimited($clientId, $endpoint)) {
            return $this->handleRateLimitExceeded($response, $clientId, $endpoint);
        }

        // Record the request
        $this->recordRequest($clientId, $endpoint);

        // Add rate limit headers to response
        $this->addRateLimitHeaders($response, $clientId, $endpoint);

        return $next($request, $response);
    }

    /**
     * Get client identifier (IP + optional user ID)
     *
     * @param Request $request Request object
     * @return string Client identifier
     */
    private function getClientIdentifier(Request $request): string
    {
        $ip = $request->getClientIp();

        // For authenticated requests, include user ID to prevent user-level abuse
        $userId = $this->getCurrentUserId();
        if ($userId) {
            return $ip . ':' . $userId;
        }

        return $ip;
    }

    /**
     * Get endpoint type for rate limiting
     *
     * @param Request $request Request object
     * @return string Endpoint type
     */
    private function getEndpointType(Request $request): string
    {
        $path = $request->getPath();
        $method = $request->getMethod();

        // Authentication endpoints
        if (preg_match('#^/api/auth/(login|register|reset)#', $path)) {
            return 'auth';
        }

        // API endpoints
        if (strpos($path, '/api/') === 0) {
            return 'api';
        }

        // General web requests
        return 'general';
    }

    /**
     * Check if client is rate limited
     *
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return bool True if rate limited
     */
    private function isRateLimited(string $clientId, string $endpoint): bool
    {
        $limit = $this->getLimit($endpoint);
        $requests = $this->getRequestCount($clientId, $endpoint);

        return $requests >= $limit['requests'];
    }

    /**
     * Record a request for rate limiting
     *
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return void
     */
    private function recordRequest(string $clientId, string $endpoint): void
    {
        $key = $this->getStorageKey($clientId, $endpoint);
        $now = time();

        if (!isset($this->limits[$key])) {
            $this->limits[$key] = [];
        }

        // Add current request timestamp
        $this->limits[$key][] = $now;

        // Clean old requests outside the window
        $limit = $this->getLimit($endpoint);
        $windowStart = $now - $limit['window'];
        $this->limits[$key] = array_filter(
            $this->limits[$key],
            fn($timestamp) => $timestamp > $windowStart
        );

        // Limit stored timestamps to prevent memory issues
        if (count($this->limits[$key]) > $limit['requests'] * 2) {
            $this->limits[$key] = array_slice($this->limits[$key], -$limit['requests']);
        }
    }

    /**
     * Get request count for client
     *
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return int Number of requests in current window
     */
    private function getRequestCount(string $clientId, string $endpoint): int
    {
        $key = $this->getStorageKey($clientId, $endpoint);

        if (!isset($this->limits[$key])) {
            return 0;
        }

        $limit = $this->getLimit($endpoint);
        $now = time();
        $windowStart = $now - $limit['window'];

        // Count requests within the current window
        return count(array_filter(
            $this->limits[$key],
            fn($timestamp) => $timestamp > $windowStart
        ));
    }

    /**
     * Get rate limit configuration
     *
     * @param string $endpoint Endpoint type
     * @return array Limit configuration
     */
    private function getLimit(string $endpoint): array
    {
        return $this->defaultLimits[$endpoint] ?? $this->defaultLimits['general'];
    }

    /**
     * Get storage key for rate limit data
     *
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return string Storage key
     */
    private function getStorageKey(string $clientId, string $endpoint): string
    {
        return $endpoint . ':' . $clientId;
    }

    /**
     * Handle rate limit exceeded
     *
     * @param Response $response Response object
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return mixed
     */
    private function handleRateLimitExceeded(Response $response, string $clientId, string $endpoint)
    {
        // Log the rate limit violation
        error_log(sprintf(
            'Rate limit exceeded for client %s on endpoint %s at %s',
            $clientId,
            $endpoint,
            date('Y-m-d H:i:s')
        ));

        $limit = $this->getLimit($endpoint);
        $resetTime = $this->getResetTime($clientId, $endpoint);

        $response->setStatusCode(429);
        $response->setHeader('X-RateLimit-Limit', $limit['requests']);
        $response->setHeader('X-RateLimit-Remaining', 0);
        $response->setHeader('X-RateLimit-Reset', $resetTime);
        $response->setHeader('Retry-After', $resetTime - time());

        if ($this->isApiRequest()) {
            return $response->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $resetTime - time()
            ], 429);
        }

        return $response->html(
            '<h1>429 Too Many Requests</h1><p>Rate limit exceeded. Please try again later.</p>',
            429
        );
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response Response object
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return void
     */
    private function addRateLimitHeaders(Response $response, string $clientId, string $endpoint): void
    {
        $limit = $this->getLimit($endpoint);
        $requests = $this->getRequestCount($clientId, $endpoint);
        $remaining = max(0, $limit['requests'] - $requests);
        $resetTime = $this->getResetTime($clientId, $endpoint);

        $response->setHeader('X-RateLimit-Limit', $limit['requests']);
        $response->setHeader('X-RateLimit-Remaining', $remaining);
        $response->setHeader('X-RateLimit-Reset', $resetTime);
    }

    /**
     * Get reset time for rate limit
     *
     * @param string $clientId Client identifier
     * @param string $endpoint Endpoint type
     * @return int Reset timestamp
     */
    private function getResetTime(string $clientId, string $endpoint): int
    {
        $key = $this->getStorageKey($clientId, $endpoint);
        $limit = $this->getLimit($endpoint);

        if (!isset($this->limits[$key]) || empty($this->limits[$key])) {
            return time() + $limit['window'];
        }

        // Reset time is when the oldest request in window expires
        $oldestRequest = min($this->limits[$key]);
        return $oldestRequest + $limit['window'];
    }

    /**
     * Check if request is an API request
     *
     * @return bool
     */
    private function isApiRequest(): bool
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 ||
               isset($_SERVER['HTTP_ACCEPT']) &&
               strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }

    /**
     * Get current user ID (if authenticated)
     *
     * @return int|null User ID or null
     */
    private function getCurrentUserId(): ?int
    {
        // This would integrate with your authentication system
        // For now, return null
        return null;
    }

    /**
     * Load rate limit configuration
     *
     * @return void
     */
    private function loadLimits(): void
    {
        // Load from configuration file if it exists
        $configFile = CONFIG_PATH . '/rate_limits.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->defaultLimits = array_merge($this->defaultLimits, $config);
        }
    }

    /**
     * Get rate limit statistics
     *
     * @return array Statistics
     */
    public function getStats(): array
    {
        $stats = [
            'total_clients' => count($this->limits),
            'limits' => $this->defaultLimits,
            'active_limits' => []
        ];

        foreach ($this->limits as $key => $requests) {
            $parts = explode(':', $key, 2);
            $endpoint = $parts[0];
            $clientId = $parts[1] ?? 'unknown';

            if (!isset($stats['active_limits'][$endpoint])) {
                $stats['active_limits'][$endpoint] = [];
            }

            $stats['active_limits'][$endpoint][$clientId] = count($requests);
        }

        return $stats;
    }

    /**
     * Clear rate limit data for a client
     *
     * @param string $clientId Client identifier
     * @return void
     */
    public function clearClientLimits(string $clientId): void
    {
        foreach ($this->limits as $key => $requests) {
            if (strpos($key, ':' . $clientId) !== false) {
                unset($this->limits[$key]);
            }
        }
    }

    /**
     * Clear all rate limit data
     *
     * @return void
     */
    public function clearAllLimits(): void
    {
        $this->limits = [];
    }

    /**
     * Set custom rate limit for endpoint
     *
     * @param string $endpoint Endpoint type
     * @param int $requests Number of requests
     * @param int $window Window in seconds
     * @return void
     */
    public function setLimit(string $endpoint, int $requests, int $window): void
    {
        $this->defaultLimits[$endpoint] = [
            'requests' => $requests,
            'window' => $window
        ];
    }
}

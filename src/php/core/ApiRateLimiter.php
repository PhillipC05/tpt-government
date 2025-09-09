<?php
/**
 * TPT Government Platform - API Rate Limiter
 *
 * Advanced rate limiting system with per-endpoint, per-user, and per-IP limits
 */

class ApiRateLimiter
{
    private $logger;
    private $cache;
    private $config;
    private $rateLimits = [];

    /**
     * Rate limit types
     */
    const TYPE_USER = 'user';
    const TYPE_IP = 'ip';
    const TYPE_ENDPOINT = 'endpoint';
    const TYPE_GLOBAL = 'global';

    /**
     * Time windows
     */
    const WINDOW_SECOND = 1;
    const WINDOW_MINUTE = 60;
    const WINDOW_HOUR = 3600;
    const WINDOW_DAY = 86400;

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $cache, $config = [])
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->config = array_merge([
            'default_limits' => [
                'user' => ['requests' => 1000, 'window' => self::WINDOW_HOUR],
                'ip' => ['requests' => 100, 'window' => self::WINDOW_MINUTE],
                'endpoint' => ['requests' => 500, 'window' => self::WINDOW_HOUR],
                'global' => ['requests' => 10000, 'window' => self::WINDOW_MINUTE]
            ],
            'burst_allowance' => 1.2, // Allow 20% burst
            'cache_prefix' => 'rate_limit:',
            'cache_ttl' => 3600,
            'block_duration' => 900, // 15 minutes
            'whitelist' => [],
            'blacklist' => [],
            'enable_analytics' => true
        ], $config);

        $this->initializeRateLimits();
    }

    /**
     * Initialize rate limits configuration
     */
    private function initializeRateLimits()
    {
        // Define endpoint-specific limits
        $this->rateLimits = [
            // Authentication endpoints - stricter limits
            'POST:/api/auth/login' => [
                'user' => ['requests' => 5, 'window' => self::WINDOW_MINUTE],
                'ip' => ['requests' => 20, 'window' => self::WINDOW_MINUTE]
            ],
            'POST:/api/auth/register' => [
                'user' => ['requests' => 3, 'window' => self::WINDOW_HOUR],
                'ip' => ['requests' => 10, 'window' => self::WINDOW_HOUR]
            ],

            // User management - moderate limits
            'GET:/api/users' => [
                'user' => ['requests' => 100, 'window' => self::WINDOW_MINUTE],
                'endpoint' => ['requests' => 1000, 'window' => self::WINDOW_MINUTE]
            ],
            'POST:/api/users' => [
                'user' => ['requests' => 10, 'window' => self::WINDOW_HOUR],
                'ip' => ['requests' => 50, 'window' => self::WINDOW_HOUR]
            ],

            // Admin endpoints - very strict limits
            'POST:/api/admin/*' => [
                'user' => ['requests' => 50, 'window' => self::WINDOW_MINUTE],
                'ip' => ['requests' => 100, 'window' => self::WINDOW_MINUTE]
            ],

            // File upload endpoints - bandwidth conscious
            'POST:/api/upload/*' => [
                'user' => ['requests' => 20, 'window' => self::WINDOW_HOUR],
                'ip' => ['requests' => 100, 'window' => self::WINDOW_HOUR]
            ],

            // Search endpoints - higher limits for usability
            'GET:/api/search/*' => [
                'user' => ['requests' => 200, 'window' => self::WINDOW_MINUTE],
                'endpoint' => ['requests' => 2000, 'window' => self::WINDOW_MINUTE]
            ],

            // Analytics endpoints - moderate limits
            'GET:/api/analytics/*' => [
                'user' => ['requests' => 100, 'window' => self::WINDOW_MINUTE],
                'endpoint' => ['requests' => 500, 'window' => self::WINDOW_MINUTE]
            ]
        ];
    }

    /**
     * Check if request is allowed
     */
    public function isAllowed(Request $request, $userId = null)
    {
        $clientIp = $request->getClientIp();
        $endpoint = $this->getEndpointKey($request);
        $userId = $userId ?? $this->getUserIdFromRequest($request);

        // Check whitelist
        if ($this->isWhitelisted($clientIp, $userId)) {
            return ['allowed' => true, 'reason' => 'whitelisted'];
        }

        // Check blacklist
        if ($this->isBlacklisted($clientIp, $userId)) {
            return ['allowed' => false, 'reason' => 'blacklisted', 'retry_after' => $this->config['block_duration']];
        }

        // Check rate limits
        $limits = $this->getApplicableLimits($endpoint);

        foreach ($limits as $type => $limit) {
            $key = $this->getCacheKey($type, $endpoint, $clientIp, $userId);
            $isAllowed = $this->checkLimit($key, $limit);

            if (!$isAllowed['allowed']) {
                $this->logRateLimitExceeded($type, $endpoint, $clientIp, $userId, $isAllowed);

                // Record analytics if enabled
                if ($this->config['enable_analytics']) {
                    $this->recordAnalytics($type, $endpoint, $clientIp, $userId, false);
                }

                return [
                    'allowed' => false,
                    'reason' => 'rate_limit_exceeded',
                    'type' => $type,
                    'limit' => $limit['requests'],
                    'window' => $limit['window'],
                    'retry_after' => $isAllowed['retry_after']
                ];
            }
        }

        // Record successful request for analytics
        if ($this->config['enable_analytics']) {
            $this->recordAnalytics('request', $endpoint, $clientIp, $userId, true);
        }

        return ['allowed' => true];
    }

    /**
     * Check specific rate limit
     */
    private function checkLimit($key, $limit)
    {
        $current = $this->cache->get($key, 0);
        $maxRequests = $limit['requests'];
        $window = $limit['window'];

        // Apply burst allowance
        $burstLimit = $maxRequests * $this->config['burst_allowance'];

        if ($current >= $burstLimit) {
            // Calculate retry after time
            $retryAfter = $this->calculateRetryAfter($key, $window);
            return [
                'allowed' => false,
                'current' => $current,
                'limit' => $maxRequests,
                'retry_after' => $retryAfter
            ];
        }

        // Increment counter
        $this->cache->set($key, $current + 1, $window);

        return [
            'allowed' => true,
            'current' => $current + 1,
            'limit' => $maxRequests
        ];
    }

    /**
     * Get endpoint key for rate limiting
     */
    private function getEndpointKey(Request $request)
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Normalize path (remove query parameters)
        $path = preg_replace('/\?.*$/', '', $path);

        return $method . ':' . $path;
    }

    /**
     * Get applicable rate limits for endpoint
     */
    private function getApplicableLimits($endpoint)
    {
        $limits = [];

        // Check for exact endpoint match
        if (isset($this->rateLimits[$endpoint])) {
            $limits = array_merge($limits, $this->rateLimits[$endpoint]);
        }

        // Check for wildcard matches
        foreach ($this->rateLimits as $pattern => $patternLimits) {
            if ($this->matchesPattern($endpoint, $pattern)) {
                $limits = array_merge($limits, $patternLimits);
            }
        }

        // Apply default limits if no specific limits found
        if (empty($limits)) {
            $limits = $this->config['default_limits'];
        }

        return $limits;
    }

    /**
     * Check if endpoint matches pattern
     */
    private function matchesPattern($endpoint, $pattern)
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/', $endpoint);
    }

    /**
     * Get cache key for rate limiting
     */
    private function getCacheKey($type, $endpoint, $ip, $userId)
    {
        $keyParts = [$this->config['cache_prefix'], $type];

        switch ($type) {
            case self::TYPE_USER:
                if (!$userId) {
                    return null; // Cannot rate limit by user without user ID
                }
                $keyParts[] = 'user:' . $userId;
                break;
            case self::TYPE_IP:
                $keyParts[] = 'ip:' . $ip;
                break;
            case self::TYPE_ENDPOINT:
                $keyParts[] = 'endpoint:' . md5($endpoint);
                break;
            case self::TYPE_GLOBAL:
                $keyParts[] = 'global';
                break;
        }

        $keyParts[] = date('Y-m-d-H-i'); // Time window bucket

        return implode(':', $keyParts);
    }

    /**
     * Get user ID from request
     */
    private function getUserIdFromRequest(Request $request)
    {
        // Try to get from session
        $session = $request->getSession();
        if ($session && $session->has('user_id')) {
            return $session->get('user_id');
        }

        // Try to get from JWT token or other auth methods
        $authHeader = $request->getHeader('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            // In a real implementation, you'd decode the JWT and extract user ID
            return $this->decodeUserIdFromToken($matches[1]);
        }

        return null;
    }

    /**
     * Decode user ID from token (placeholder)
     */
    private function decodeUserIdFromToken($token)
    {
        // This would be implemented based on your JWT/token system
        return null;
    }

    /**
     * Check if client is whitelisted
     */
    private function isWhitelisted($ip, $userId)
    {
        if (in_array($ip, $this->config['whitelist'])) {
            return true;
        }

        if ($userId && in_array('user:' . $userId, $this->config['whitelist'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if client is blacklisted
     */
    private function isBlacklisted($ip, $userId)
    {
        if (in_array($ip, $this->config['blacklist'])) {
            return true;
        }

        if ($userId && in_array('user:' . $userId, $this->config['blacklist'])) {
            return true;
        }

        return false;
    }

    /**
     * Calculate retry after time
     */
    private function calculateRetryAfter($key, $window)
    {
        // Get TTL of the current key
        $ttl = $this->cache->ttl($key);
        return $ttl > 0 ? $ttl : $window;
    }

    /**
     * Log rate limit exceeded
     */
    private function logRateLimitExceeded($type, $endpoint, $ip, $userId, $limitInfo)
    {
        $this->logger->warning('Rate limit exceeded', [
            'type' => $type,
            'endpoint' => $endpoint,
            'ip' => $ip,
            'user_id' => $userId,
            'current' => $limitInfo['current'],
            'limit' => $limitInfo['limit'],
            'retry_after' => $limitInfo['retry_after']
        ]);
    }

    /**
     * Record analytics data
     */
    private function recordAnalytics($type, $endpoint, $ip, $userId, $allowed)
    {
        $analyticsKey = $this->config['cache_prefix'] . 'analytics:' . date('Y-m-d-H');

        $analytics = $this->cache->get($analyticsKey, [
            'total_requests' => 0,
            'allowed_requests' => 0,
            'blocked_requests' => 0,
            'by_endpoint' => [],
            'by_ip' => [],
            'by_user' => []
        ]);

        $analytics['total_requests']++;

        if ($allowed) {
            $analytics['allowed_requests']++;
        } else {
            $analytics['blocked_requests']++;
        }

        // Track by endpoint
        if (!isset($analytics['by_endpoint'][$endpoint])) {
            $analytics['by_endpoint'][$endpoint] = ['allowed' => 0, 'blocked' => 0];
        }
        $analytics['by_endpoint'][$endpoint][$allowed ? 'allowed' : 'blocked']++;

        // Track by IP
        if (!isset($analytics['by_ip'][$ip])) {
            $analytics['by_ip'][$ip] = ['allowed' => 0, 'blocked' => 0];
        }
        $analytics['by_ip'][$ip][$allowed ? 'allowed' : 'blocked']++;

        // Track by user if available
        if ($userId) {
            if (!isset($analytics['by_user'][$userId])) {
                $analytics['by_user'][$userId] = ['allowed' => 0, 'blocked' => 0];
            }
            $analytics['by_user'][$userId][$allowed ? 'allowed' : 'blocked']++;
        }

        $this->cache->set($analyticsKey, $analytics, 7200); // 2 hours
    }

    /**
     * Get rate limit status
     */
    public function getRateLimitStatus(Request $request, $userId = null)
    {
        $clientIp = $request->getClientIp();
        $endpoint = $this->getEndpointKey($request);
        $userId = $userId ?? $this->getUserIdFromRequest($request);

        $limits = $this->getApplicableLimits($endpoint);
        $status = [];

        foreach ($limits as $type => $limit) {
            $key = $this->getCacheKey($type, $endpoint, $clientIp, $userId);
            if ($key) {
                $current = $this->cache->get($key, 0);
                $status[$type] = [
                    'current' => $current,
                    'limit' => $limit['requests'],
                    'window' => $limit['window'],
                    'remaining' => max(0, $limit['requests'] - $current)
                ];
            }
        }

        return $status;
    }

    /**
     * Reset rate limits for a client
     */
    public function resetLimits($type, $identifier, $endpoint = null)
    {
        $pattern = $this->config['cache_prefix'] . $type . ':' . $identifier;

        if ($endpoint) {
            $pattern .= ':endpoint:' . md5($endpoint);
        }

        // In a real implementation, you'd need to clear all matching keys
        // This is a simplified version
        $this->logger->info('Rate limits reset', [
            'type' => $type,
            'identifier' => $identifier,
            'endpoint' => $endpoint
        ]);
    }

    /**
     * Get analytics data
     */
    public function getAnalytics($hours = 24)
    {
        $analytics = [];

        for ($i = 0; $i < $hours; $i++) {
            $hourKey = date('Y-m-d-H', strtotime("-{$i} hours"));
            $key = $this->config['cache_prefix'] . 'analytics:' . $hourKey;

            $hourData = $this->cache->get($key);
            if ($hourData) {
                $analytics[$hourKey] = $hourData;
            }
        }

        return $analytics;
    }

    /**
     * Add IP to whitelist
     */
    public function whitelistIp($ip)
    {
        if (!in_array($ip, $this->config['whitelist'])) {
            $this->config['whitelist'][] = $ip;
            $this->logger->info('IP added to whitelist', ['ip' => $ip]);
        }
    }

    /**
     * Add IP to blacklist
     */
    public function blacklistIp($ip, $duration = null)
    {
        if (!in_array($ip, $this->config['blacklist'])) {
            $this->config['blacklist'][] = $ip;

            if ($duration) {
                // In a real implementation, you'd schedule removal after duration
            }

            $this->logger->info('IP added to blacklist', ['ip' => $ip, 'duration' => $duration]);
        }
    }

    /**
     * Update rate limit configuration
     */
    public function updateLimits($endpoint, $type, $requests, $window)
    {
        if (!isset($this->rateLimits[$endpoint])) {
            $this->rateLimits[$endpoint] = [];
        }

        $this->rateLimits[$endpoint][$type] = [
            'requests' => $requests,
            'window' => $window
        ];

        $this->logger->info('Rate limit updated', [
            'endpoint' => $endpoint,
            'type' => $type,
            'requests' => $requests,
            'window' => $window
        ]);
    }
}

<?php
/**
 * TPT Government Platform - Rate Limiter & DDoS Protection
 *
 * Advanced rate limiting and DDoS protection system for government platform
 * Supports multiple algorithms, distributed caching, and real-time monitoring
 */

namespace TPT\Core;

class RateLimiter
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var array Rate limiting configuration
     */
    private array $config;

    /**
     * @var array Current request context
     */
    private array $requestContext = [];

    /**
     * Rate limiting algorithms
     */
    const ALGORITHMS = [
        'fixed_window' => 'Fixed Window',
        'sliding_window' => 'Sliding Window',
        'token_bucket' => 'Token Bucket',
        'leaky_bucket' => 'Leaky Bucket'
    ];

    /**
     * Risk levels for rate limiting
     */
    const RISK_LEVELS = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4
    ];

    /**
     * Constructor
     *
     * @param Database $database
     * @param array $config
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge([
            'default_algorithm' => 'sliding_window',
            'enable_distributed' => true,
            'redis_prefix' => 'rate_limit:',
            'cleanup_interval' => 300, // 5 minutes
            'ban_duration' => 3600, // 1 hour
            'whitelist_enabled' => true,
            'blacklist_enabled' => true,
            'ddos_protection' => true,
            'ddos_threshold' => 1000, // requests per minute
            'ddos_window' => 60, // seconds
            'alert_threshold' => 0.8, // 80% of limit
            'burst_allowance' => 1.2, // 20% burst allowance
            'grace_period' => 60, // 1 minute grace period
            'auto_unban' => true,
            'unban_interval' => 300 // 5 minutes
        ], $config);
    }

    /**
     * Set request context for rate limiting
     *
     * @param array $context
     * @return void
     */
    public function setRequestContext(array $context): void
    {
        $this->requestContext = $context;
    }

    /**
     * Check if request should be rate limited
     *
     * @param string $identifier
     * @param string $limitType
     * @return array
     */
    public function checkLimit(string $identifier, string $limitType = 'general'): array
    {
        // Check whitelist first
        if ($this->isWhitelisted($identifier)) {
            return [
                'allowed' => true,
                'remaining' => PHP_INT_MAX,
                'reset_time' => 0,
                'whitelisted' => true
            ];
        }

        // Check blacklist
        if ($this->isBlacklisted($identifier)) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => 0,
                'blacklisted' => true,
                'ban_duration' => $this->getBanDuration($identifier)
            ];
        }

        // Get rate limit configuration
        $limitConfig = $this->getLimitConfig($limitType);
        if (!$limitConfig) {
            return [
                'allowed' => true,
                'remaining' => PHP_INT_MAX,
                'reset_time' => 0
            ];
        }

        // Check rate limit based on algorithm
        $result = $this->checkRateLimit($identifier, $limitConfig);

        // Handle violations
        if (!$result['allowed']) {
            $this->handleViolation($identifier, $limitType, $result);
        }

        // Check for DDoS patterns
        if ($this->config['ddos_protection']) {
            $this->checkDDoSPatterns($identifier);
        }

        return $result;
    }

    /**
     * Check rate limit using specified algorithm
     *
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private function checkRateLimit(string $identifier, array $config): array
    {
        $algorithm = $config['algorithm'] ?? $this->config['default_algorithm'];

        switch ($algorithm) {
            case 'fixed_window':
                return $this->checkFixedWindow($identifier, $config);
            case 'sliding_window':
                return $this->checkSlidingWindow($identifier, $config);
            case 'token_bucket':
                return $this->checkTokenBucket($identifier, $config);
            case 'leaky_bucket':
                return $this->checkLeakyBucket($identifier, $config);
            default:
                return $this->checkSlidingWindow($identifier, $config);
        }
    }

    /**
     * Fixed window rate limiting
     *
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private function checkFixedWindow(string $identifier, array $config): array
    {
        $window = $config['window_seconds'];
        $limit = $config['requests_per_window'];
        $key = $this->getCacheKey($identifier, 'fixed', $window);

        try {
            // Get current count
            $current = $this->getCacheValue($key) ?? 0;
            $current = (int) $current;

            // Check if limit exceeded
            if ($current >= $limit) {
                $resetTime = $this->getWindowResetTime($window);
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $resetTime,
                    'retry_after' => $resetTime - time()
                ];
            }

            // Increment counter
            $this->setCacheValue($key, $current + 1, $window);

            return [
                'allowed' => true,
                'remaining' => $limit - $current - 1,
                'reset_time' => $this->getWindowResetTime($window)
            ];

        } catch (\Exception $e) {
            // Allow request on cache failure
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset_time' => time() + $window
            ];
        }
    }

    /**
     * Sliding window rate limiting
     *
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private function checkSlidingWindow(string $identifier, array $config): array
    {
        $window = $config['window_seconds'];
        $limit = $config['requests_per_window'];
        $precision = $config['precision_seconds'] ?? 10;

        try {
            $now = time();
            $currentWindow = floor($now / $precision);

            // Get request counts for sliding window
            $counts = [];
            for ($i = 0; $i < ceil($window / $precision); $i++) {
                $windowKey = $currentWindow - $i;
                $key = $this->getCacheKey($identifier, 'sliding', $window) . ':' . $windowKey;
                $counts[] = (int) ($this->getCacheValue($key) ?? 0);
            }

            $totalRequests = array_sum($counts);

            // Check if limit exceeded
            if ($totalRequests >= $limit) {
                $resetTime = $now + $precision;
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $resetTime,
                    'retry_after' => $precision
                ];
            }

            // Increment current window
            $currentKey = $this->getCacheKey($identifier, 'sliding', $window) . ':' . $currentWindow;
            $currentCount = (int) ($this->getCacheValue($currentKey) ?? 0);
            $this->setCacheValue($currentKey, $currentCount + 1, $window);

            return [
                'allowed' => true,
                'remaining' => $limit - $totalRequests - 1,
                'reset_time' => $now + $precision
            ];

        } catch (\Exception $e) {
            // Allow request on cache failure
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset_time' => time() + $precision
            ];
        }
    }

    /**
     * Token bucket rate limiting
     *
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private function checkTokenBucket(string $identifier, array $config): array
    {
        $capacity = $config['bucket_capacity'];
        $refillRate = $config['refill_rate_per_second'];
        $key = $this->getCacheKey($identifier, 'token_bucket', 0);

        try {
            $now = microtime(true);
            $bucketData = $this->getCacheValue($key);

            if (!$bucketData) {
                // Initialize bucket
                $bucket = [
                    'tokens' => $capacity,
                    'last_refill' => $now
                ];
            } else {
                $bucket = json_decode($bucketData, true);
                if (!$bucket) {
                    $bucket = [
                        'tokens' => $capacity,
                        'last_refill' => $now
                    ];
                }
            }

            // Refill tokens
            $timePassed = $now - $bucket['last_refill'];
            $tokensToAdd = $timePassed * $refillRate;
            $bucket['tokens'] = min($capacity, $bucket['tokens'] + $tokensToAdd);
            $bucket['last_refill'] = $now;

            // Check if request can be made
            if ($bucket['tokens'] < 1) {
                $waitTime = (1 - $bucket['tokens']) / $refillRate;
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $now + $waitTime,
                    'retry_after' => ceil($waitTime)
                ];
            }

            // Consume token
            $bucket['tokens'] -= 1;
            $this->setCacheValue($key, json_encode($bucket), 3600); // 1 hour TTL

            return [
                'allowed' => true,
                'remaining' => floor($bucket['tokens']),
                'reset_time' => $now + (1 / $refillRate)
            ];

        } catch (\Exception $e) {
            // Allow request on cache failure
            return [
                'allowed' => true,
                'remaining' => $capacity - 1,
                'reset_time' => time() + 1
            ];
        }
    }

    /**
     * Leaky bucket rate limiting
     *
     * @param string $identifier
     * @param array $config
     * @return array
     */
    private function checkLeakyBucket(string $identifier, array $config): array
    {
        $capacity = $config['bucket_capacity'];
        $leakRate = $config['leak_rate_per_second'];
        $key = $this->getCacheKey($identifier, 'leaky_bucket', 0);

        try {
            $now = microtime(true);
            $bucketData = $this->getCacheValue($key);

            if (!$bucketData) {
                // Initialize bucket
                $bucket = [
                    'level' => 0,
                    'last_leak' => $now
                ];
            } else {
                $bucket = json_decode($bucketData, true);
                if (!$bucket) {
                    $bucket = [
                        'level' => 0,
                        'last_leak' => $now
                    ];
                }
            }

            // Leak water from bucket
            $timePassed = $now - $bucket['last_leak'];
            $leaked = $timePassed * $leakRate;
            $bucket['level'] = max(0, $bucket['level'] - $leaked);
            $bucket['last_leak'] = $now;

            // Check if bucket is full
            if ($bucket['level'] >= $capacity) {
                $overflow = $bucket['level'] - $capacity;
                $waitTime = $overflow / $leakRate;
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $now + $waitTime,
                    'retry_after' => ceil($waitTime)
                ];
            }

            // Add water to bucket (request)
            $bucket['level'] += 1;
            $this->setCacheValue($key, json_encode($bucket), 3600); // 1 hour TTL

            return [
                'allowed' => true,
                'remaining' => $capacity - $bucket['level'],
                'reset_time' => $now + (1 / $leakRate)
            ];

        } catch (\Exception $e) {
            // Allow request on cache failure
            return [
                'allowed' => true,
                'remaining' => $capacity - 1,
                'reset_time' => time() + 1
            ];
        }
    }

    /**
     * Get rate limit configuration for a specific type
     *
     * @param string $type
     * @return array|null
     */
    private function getLimitConfig(string $type): ?array
    {
        $configs = [
            'general' => [
                'algorithm' => 'sliding_window',
                'requests_per_window' => 100,
                'window_seconds' => 60,
                'precision_seconds' => 10
            ],
            'api' => [
                'algorithm' => 'token_bucket',
                'bucket_capacity' => 100,
                'refill_rate_per_second' => 10
            ],
            'auth' => [
                'algorithm' => 'fixed_window',
                'requests_per_window' => 5,
                'window_seconds' => 300
            ],
            'admin' => [
                'algorithm' => 'sliding_window',
                'requests_per_window' => 1000,
                'window_seconds' => 60,
                'precision_seconds' => 10
            ]
        ];

        return $configs[$type] ?? $configs['general'];
    }

    /**
     * Handle rate limit violation
     *
     * @param string $identifier
     * @param string $limitType
     * @param array $result
     * @return void
     */
    private function handleViolation(string $identifier, string $limitType, array $result): void
    {
        // Log violation
        $this->logViolation($identifier, $limitType, $result);

        // Check if should ban
        $violationCount = $this->getViolationCount($identifier);
        if ($violationCount >= 3) {
            $this->banIdentifier($identifier, $this->config['ban_duration']);
        }

        // Send alert if threshold reached
        if ($result['remaining'] <= ($this->getLimitConfig($limitType)['requests_per_window'] ?? 100) * 0.1) {
            $this->sendRateLimitAlert($identifier, $limitType, $result);
        }
    }

    /**
     * Check for DDoS patterns
     *
     * @param string $identifier
     * @return void
     */
    private function checkDDoSPatterns(string $identifier): void
    {
        $window = $this->config['ddos_window'];
        $threshold = $this->config['ddos_threshold'];

        $key = $this->getCacheKey($identifier, 'ddos_check', $window);
        $count = (int) ($this->getCacheValue($key) ?? 0);

        if ($count >= $threshold) {
            // DDoS pattern detected
            $this->handleDDoSAttack($identifier);
            return;
        }

        $this->setCacheValue($key, $count + 1, $window);
    }

    /**
     * Handle DDoS attack detection
     *
     * @param string $identifier
     * @return void
     */
    private function handleDDoSAttack(string $identifier): void
    {
        // Ban the identifier
        $this->banIdentifier($identifier, $this->config['ban_duration'] * 2);

        // Log DDoS attack
        $this->logDDoSAttack($identifier);

        // Send critical alert
        $this->sendDDoSAlert($identifier);
    }

    /**
     * Check if identifier is whitelisted
     *
     * @param string $identifier
     * @return bool
     */
    private function isWhitelisted(string $identifier): bool
    {
        if (!$this->config['whitelist_enabled']) {
            return false;
        }

        try {
            $result = $this->database->selectOne("
                SELECT 1 FROM rate_limit_whitelist
                WHERE identifier = ? AND is_active = 1
                AND (expires_at IS NULL OR expires_at > NOW())
            ", [$identifier]);

            return $result !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if identifier is blacklisted
     *
     * @param string $identifier
     * @return bool
     */
    private function isBlacklisted(string $identifier): bool
    {
        if (!$this->config['blacklist_enabled']) {
            return false;
        }

        try {
            $result = $this->database->selectOne("
                SELECT 1 FROM rate_limit_blacklist
                WHERE identifier = ? AND is_active = 1
                AND (expires_at IS NULL OR expires_at > NOW())
            ", [$identifier]);

            return $result !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ban an identifier
     *
     * @param string $identifier
     * @param int $duration
     * @return void
     */
    private function banIdentifier(string $identifier, int $duration): void
    {
        try {
            $this->database->execute("
                INSERT INTO rate_limit_blacklist (identifier, reason, banned_by, expires_at, created_at)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
                ON DUPLICATE KEY UPDATE
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    updated_at = NOW()
            ", [
                $identifier,
                'Rate limit violation',
                $this->requestContext['user_id'] ?? null,
                $duration,
                $duration
            ]);
        } catch (\Exception $e) {
            error_log('Failed to ban identifier: ' . $e->getMessage());
        }
    }

    /**
     * Get ban duration for identifier
     *
     * @param string $identifier
     * @return int|null
     */
    private function getBanDuration(string $identifier): ?int
    {
        try {
            $result = $this->database->selectOne("
                SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining
                FROM rate_limit_blacklist
                WHERE identifier = ? AND is_active = 1
            ", [$identifier]);

            return $result ? max(0, (int) $result['remaining']) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get violation count for identifier
     *
     * @param string $identifier
     * @return int
     */
    private function getViolationCount(string $identifier): int
    {
        $key = $this->getCacheKey($identifier, 'violations', 3600);
        $count = (int) ($this->getCacheValue($key) ?? 0);
        $this->setCacheValue($key, $count + 1, 3600);
        return $count + 1;
    }

    /**
     * Get cache key for rate limiting
     *
     * @param string $identifier
     * @param string $type
     * @param int $window
     * @return string
     */
    private function getCacheKey(string $identifier, string $type, int $window = 0): string
    {
        $prefix = $this->config['redis_prefix'];
        $windowKey = $window > 0 ? ":{$window}" : '';
        return "{$prefix}{$type}:{$identifier}{$windowKey}";
    }

    /**
     * Get cache value (simplified - would use Redis in production)
     *
     * @param string $key
     * @return mixed
     */
    private function getCacheValue(string $key)
    {
        // In production, this would use Redis or Memcached
        // For now, using file-based cache as fallback
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key) . '.cache';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data || ($data['expires'] ?? 0) < time()) {
            unlink($cacheFile);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set cache value (simplified - would use Redis in production)
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     */
    private function setCacheValue(string $key, $value, int $ttl): void
    {
        // In production, this would use Redis or Memcached
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key) . '.cache';
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        file_put_contents($cacheFile, json_encode($data));
    }

    /**
     * Get window reset time
     *
     * @param int $window
     * @return int
     */
    private function getWindowResetTime(int $window): int
    {
        return ceil(time() / $window) * $window;
    }

    /**
     * Log rate limit violation
     *
     * @param string $identifier
     * @param string $limitType
     * @param array $result
     * @return void
     */
    private function logViolation(string $identifier, string $limitType, array $result): void
    {
        try {
            $this->database->execute("
                INSERT INTO rate_limit_violations (
                    identifier, limit_type, violation_type, ip_address,
                    user_agent, request_uri, user_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $identifier,
                $limitType,
                'rate_limit_exceeded',
                $this->requestContext['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $this->requestContext['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $this->requestContext['request_uri'] ?? $_SERVER['REQUEST_URI'] ?? 'unknown',
                $this->requestContext['user_id'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log('Failed to log rate limit violation: ' . $e->getMessage());
        }
    }

    /**
     * Log DDoS attack
     *
     * @param string $identifier
     * @return void
     */
    private function logDDoSAttack(string $identifier): void
    {
        try {
            $this->database->execute("
                INSERT INTO ddos_attacks (
                    identifier, attack_type, severity, affected_endpoints,
                    mitigation_action, detected_at, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $identifier,
                'rate_limit_based',
                'high',
                json_encode([$this->requestContext['request_uri'] ?? 'unknown']),
                'banned_identifier',
                date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log('Failed to log DDoS attack: ' . $e->getMessage());
        }
    }

    /**
     * Send rate limit alert
     *
     * @param string $identifier
     * @param string $limitType
     * @param array $result
     * @return void
     */
    private function sendRateLimitAlert(string $identifier, string $limitType, array $result): void
    {
        // In production, this would send email/SMS alerts
        error_log("RATE LIMIT ALERT: {$identifier} exceeded {$limitType} limit");
    }

    /**
     * Send DDoS alert
     *
     * @param string $identifier
     * @return void
     */
    private function sendDDoSAlert(string $identifier): void
    {
        // In production, this would send critical alerts
        error_log("DDoS ALERT: Potential attack detected from {$identifier}");
    }

    /**
     * Get rate limiting statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            $stats = [
                'total_violations' => 0,
                'active_bans' => 0,
                'ddos_attacks' => 0,
                'whitelisted_count' => 0,
                'blacklisted_count' => 0
            ];

            // Get violation count
            $violationResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM rate_limit_violations
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stats['total_violations'] = $violationResult['count'] ?? 0;

            // Get active bans
            $banResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM rate_limit_blacklist
                WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stats['active_bans'] = $banResult['count'] ?? 0;

            // Get DDoS attacks
            $ddosResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM ddos_attacks
                WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stats['ddos_attacks'] = $ddosResult['count'] ?? 0;

            // Get whitelist count
            $whitelistResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM rate_limit_whitelist
                WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stats['whitelisted_count'] = $whitelistResult['count'] ?? 0;

            // Get blacklist count
            $blacklistResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM rate_limit_blacklist
                WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stats['blacklisted_count'] = $blacklistResult['count'] ?? 0;

            return [
                'success' => true,
                'statistics' => $stats,
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clean up expired entries
     *
     * @return array
     */
    public function cleanup(): array
    {
        try {
            $cleaned = [
                'blacklist_entries' => 0,
                'whitelist_entries' => 0,
                'old_violations' => 0
            ];

            // Clean expired blacklist entries
            $cleaned['blacklist_entries'] = $this->database->execute("
                UPDATE rate_limit_blacklist
                SET is_active = 0
                WHERE expires_at < NOW() AND is_active = 1
            ");

            // Clean expired whitelist entries
            $cleaned['whitelist_entries'] = $this->database->execute("
                UPDATE rate_limit_whitelist
                SET is_active = 0
                WHERE expires_at < NOW() AND is_active = 1
            ");

            // Clean old violations (keep last 30 days)
            $cleaned['old_violations'] = $this->database->execute("
                DELETE FROM rate_limit_violations
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            return [
                'success' => true,
                'cleaned' => $cleaned,
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

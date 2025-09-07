<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;
use Redis;
use Memcached;

/**
 * Advanced Cache Management System
 *
 * This class provides multi-layer caching with Redis and Memcached support including:
 * - Multi-level cache hierarchy (L1: Memory, L2: Redis, L3: Memcached)
 * - Intelligent cache invalidation strategies
 * - Cache warming and preloading
 * - Performance monitoring and analytics
 * - Distributed cache synchronization
 * - Cache compression and serialization optimization
 */
class CacheManager
{
    private ?Redis $redis = null;
    private ?Memcached $memcached = null;
    private PDO $pdo;
    private array $config;
    private array $memoryCache = [];
    private array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'memory_cache_size' => 0
    ];

    private array $cacheLayers = [
        'memory' => true,    // L1: In-memory cache
        'redis' => false,    // L2: Redis cache
        'memcached' => false // L3: Memcached
    ];

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'memory_cache_size' => 1000,     // Max items in memory cache
            'memory_cache_ttl' => 300,       // 5 minutes default TTL
            'redis_host' => 'localhost',
            'redis_port' => 6379,
            'redis_password' => null,
            'redis_database' => 0,
            'redis_timeout' => 1.0,
            'memcached_host' => 'localhost',
            'memcached_port' => 11211,
            'compression_enabled' => true,
            'compression_threshold' => 1024, // Compress items > 1KB
            'serialization_method' => 'json', // json, igbinary, serialize
            'enable_cache_warming' => true,
            'cache_warming_interval' => 3600, // 1 hour
            'enable_performance_monitoring' => true
        ], $config);

        $this->initializeCacheLayers();
        $this->createCacheTables();
    }

    /**
     * Initialize available cache layers
     */
    private function initializeCacheLayers(): void
    {
        // Initialize Redis if available
        if (extension_loaded('redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect(
                    $this->config['redis_host'],
                    $this->config['redis_port'],
                    $this->config['redis_timeout']
                );

                if ($this->config['redis_password']) {
                    $this->redis->auth($this->config['redis_password']);
                }

                $this->redis->select($this->config['redis_database']);
                $this->redis->ping(); // Test connection

                $this->cacheLayers['redis'] = true;

                // Configure Redis for better performance
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
                $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);

            } catch (Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                $this->redis = null;
            }
        }

        // Initialize Memcached if available
        if (extension_loaded('memcached')) {
            try {
                $this->memcached = new Memcached();
                $this->memcached->addServer(
                    $this->config['memcached_host'],
                    $this->config['memcached_port']
                );

                // Test connection
                $this->memcached->set('test', 'connection', 10);
                $test = $this->memcached->get('test');

                if ($test === 'connection') {
                    $this->cacheLayers['memcached'] = true;
                    $this->memcached->delete('test');
                }

                // Configure Memcached
                $this->memcached->setOption(Memcached::OPT_COMPRESSION, $this->config['compression_enabled']);
                $this->memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

            } catch (Exception $e) {
                error_log("Memcached connection failed: " . $e->getMessage());
                $this->memcached = null;
            }
        }
    }

    /**
     * Create cache monitoring tables
     */
    private function createCacheTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS cache_performance (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) NOT NULL,
                cache_layer ENUM('memory', 'redis', 'memcached') NOT NULL,
                operation ENUM('get', 'set', 'delete', 'clear') NOT NULL,
                hit_miss ENUM('hit', 'miss') DEFAULT NULL,
                response_time DECIMAL(6,4) NOT NULL, -- in milliseconds
                data_size INT DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cache_key (cache_key),
                INDEX idx_operation (operation),
                INDEX idx_timestamp (timestamp),
                INDEX idx_cache_layer (cache_layer)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS cache_warming_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) NOT NULL UNIQUE,
                priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
                data_type VARCHAR(100) DEFAULT NULL,
                parameters TEXT DEFAULT NULL,
                last_attempt TIMESTAMP NULL,
                next_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_next_attempt (next_attempt),
                INDEX idx_priority (priority)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS cache_invalidation_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rule_name VARCHAR(255) NOT NULL UNIQUE,
                pattern VARCHAR(500) NOT NULL,
                invalidation_type ENUM('exact', 'pattern', 'prefix', 'suffix') DEFAULT 'pattern',
                conditions TEXT DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_is_active (is_active),
                INDEX idx_pattern (pattern(100))
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create cache tables: " . $e->getMessage());
        }
    }

    /**
     * Get value from cache with multi-layer lookup
     */
    public function get(string $key, $default = null)
    {
        $startTime = microtime(true);

        // Layer 1: Memory cache (fastest)
        if ($this->cacheLayers['memory'] && isset($this->memoryCache[$key])) {
            $cached = $this->memoryCache[$key];
            if ($cached['expires'] > time()) {
                $this->recordCacheOperation($key, 'memory', 'get', 'hit', (microtime(true) - $startTime) * 1000);
                $this->cacheStats['hits']++;
                return $this->unserializeData($cached['data']);
            } else {
                unset($this->memoryCache[$key]);
            }
        }

        // Layer 2: Redis cache
        if ($this->cacheLayers['redis'] && $this->redis) {
            try {
                $data = $this->redis->get($key);
                if ($data !== false) {
                    // Store in memory cache for faster future access
                    $this->setMemoryCache($key, $data, $this->config['memory_cache_ttl']);
                    $this->recordCacheOperation($key, 'redis', 'get', 'hit', (microtime(true) - $startTime) * 1000);
                    $this->cacheStats['hits']++;
                    return $this->unserializeData($data);
                }
            } catch (Exception $e) {
                error_log("Redis get failed: " . $e->getMessage());
            }
        }

        // Layer 3: Memcached
        if ($this->cacheLayers['memcached'] && $this->memcached) {
            try {
                $data = $this->memcached->get($key);
                if ($data !== false) {
                    // Store in higher layers for faster future access
                    $this->setMemoryCache($key, $data, $this->config['memory_cache_ttl']);
                    if ($this->redis) {
                        $this->redis->setex($key, $this->config['memory_cache_ttl'], $data);
                    }
                    $this->recordCacheOperation($key, 'memcached', 'get', 'hit', (microtime(true) - $startTime) * 1000);
                    $this->cacheStats['hits']++;
                    return $this->unserializeData($data);
                }
            } catch (Exception $e) {
                error_log("Memcached get failed: " . $e->getMessage());
            }
        }

        // Cache miss
        $this->recordCacheOperation($key, 'all', 'get', 'miss', (microtime(true) - $startTime) * 1000);
        $this->cacheStats['misses']++;
        return $default;
    }

    /**
     * Set value in all available cache layers
     */
    public function set(string $key, $value, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->config['memory_cache_ttl'];
        $serializedData = $this->serializeData($value);
        $success = false;

        // Layer 1: Memory cache
        if ($this->cacheLayers['memory']) {
            $this->setMemoryCache($key, $serializedData, $ttl);
            $success = true;
        }

        // Layer 2: Redis cache
        if ($this->cacheLayers['redis'] && $this->redis) {
            try {
                $redisSuccess = $this->redis->setex($key, $ttl, $serializedData);
                if ($redisSuccess) {
                    $success = true;
                }
            } catch (Exception $e) {
                error_log("Redis set failed: " . $e->getMessage());
            }
        }

        // Layer 3: Memcached
        if ($this->cacheLayers['memcached'] && $this->memcached) {
            try {
                $memcachedSuccess = $this->memcached->set($key, $serializedData, $ttl);
                if ($memcachedSuccess) {
                    $success = true;
                }
            } catch (Exception $e) {
                error_log("Memcached set failed: " . $e->getMessage());
            }
        }

        $this->cacheStats['sets']++;
        $this->recordCacheOperation($key, 'all', 'set', null, 0, strlen($serializedData));

        return $success;
    }

    /**
     * Delete value from all cache layers
     */
    public function delete(string $key): bool
    {
        $success = false;

        // Layer 1: Memory cache
        if ($this->cacheLayers['memory'] && isset($this->memoryCache[$key])) {
            unset($this->memoryCache[$key]);
            $success = true;
        }

        // Layer 2: Redis cache
        if ($this->cacheLayers['redis'] && $this->redis) {
            try {
                if ($this->redis->del($key) > 0) {
                    $success = true;
                }
            } catch (Exception $e) {
                error_log("Redis delete failed: " . $e->getMessage());
            }
        }

        // Layer 3: Memcached
        if ($this->cacheLayers['memcached'] && $this->memcached) {
            try {
                if ($this->memcached->delete($key)) {
                    $success = true;
                }
            } catch (Exception $e) {
                error_log("Memcached delete failed: " . $e->getMessage());
            }
        }

        $this->cacheStats['deletes']++;
        $this->recordCacheOperation($key, 'all', 'delete');

        return $success;
    }

    /**
     * Clear all cache layers
     */
    public function clear(): bool
    {
        $success = false;

        // Layer 1: Memory cache
        if ($this->cacheLayers['memory']) {
            $this->memoryCache = [];
            $this->cacheStats['memory_cache_size'] = 0;
            $success = true;
        }

        // Layer 2: Redis cache
        if ($this->cacheLayers['redis'] && $this->redis) {
            try {
                $this->redis->flushDB();
                $success = true;
            } catch (Exception $e) {
                error_log("Redis clear failed: " . $e->getMessage());
            }
        }

        // Layer 3: Memcached
        if ($this->cacheLayers['memcached'] && $this->memcached) {
            try {
                $this->memcached->flush();
                $success = true;
            } catch (Exception $e) {
                error_log("Memcached clear failed: " . $e->getMessage());
            }
        }

        $this->recordCacheOperation('*', 'all', 'clear');
        return $success;
    }

    /**
     * Get or set cache value with callback
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);

        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Cache database query results
     */
    public function rememberQuery(string $sql, array $params = [], int $ttl = null): array
    {
        $queryHash = $this->generateQueryHash($sql, $params);
        $cacheKey = "query:{$queryHash}";

        return $this->remember($cacheKey, $ttl ?? $this->config['memory_cache_ttl'], function() use ($sql, $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern(string $pattern): int
    {
        $invalidated = 0;

        // Memory cache
        foreach ($this->memoryCache as $key => $value) {
            if (fnmatch($pattern, $key)) {
                unset($this->memoryCache[$key]);
                $invalidated++;
            }
        }

        // Redis cache
        if ($this->redis) {
            try {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                    $invalidated += count($keys);
                }
            } catch (Exception $e) {
                error_log("Redis pattern invalidation failed: " . $e->getMessage());
            }
        }

        // Memcached doesn't support pattern deletion efficiently
        // Would need to track keys separately for full pattern support

        return $invalidated;
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmupCache(array $warmupData = []): array
    {
        $results = [
            'warmed_up' => 0,
            'errors' => []
        ];

        // Default warmup data if none provided
        if (empty($warmupData)) {
            $warmupData = $this->getDefaultWarmupData();
        }

        foreach ($warmupData as $item) {
            try {
                $key = $item['key'];
                $ttl = $item['ttl'] ?? $this->config['memory_cache_ttl'];

                if (isset($item['callback'])) {
                    $value = $item['callback']();
                } elseif (isset($item['value'])) {
                    $value = $item['value'];
                } else {
                    continue;
                }

                $this->set($key, $value, $ttl);
                $results['warmed_up']++;

            } catch (Exception $e) {
                $results['errors'][] = "Failed to warm up {$item['key']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get cache performance statistics
     */
    public function getCacheStats(): array
    {
        $totalRequests = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        $hitRate = $totalRequests > 0 ? ($this->cacheStats['hits'] / $totalRequests) * 100 : 0;

        $stats = [
            'cache_layers' => $this->cacheLayers,
            'performance' => [
                'hits' => $this->cacheStats['hits'],
                'misses' => $this->cacheStats['misses'],
                'sets' => $this->cacheStats['sets'],
                'deletes' => $this->cacheStats['deletes'],
                'hit_rate' => round($hitRate, 2) . '%',
                'total_requests' => $totalRequests
            ],
            'memory_cache' => [
                'size' => count($this->memoryCache),
                'max_size' => $this->config['memory_cache_size']
            ]
        ];

        // Add layer-specific stats
        if ($this->redis) {
            try {
                $stats['redis'] = [
                    'connected_clients' => $this->redis->info('CLIENTS')['connected_clients'] ?? 0,
                    'used_memory' => $this->redis->info('MEMORY')['used_memory_human'] ?? '0B',
                    'total_keys' => $this->redis->dbSize()
                ];
            } catch (Exception $e) {
                $stats['redis'] = ['error' => $e->getMessage()];
            }
        }

        if ($this->memcached) {
            try {
                $stats['memcached'] = $this->memcached->getStats();
            } catch (Exception $e) {
                $stats['memcached'] = ['error' => $e->getMessage()];
            }
        }

        return $stats;
    }

    /**
     * Get cache performance metrics from database
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = [];

        try {
            // Overall performance
            $stmt = $this->pdo->query("
                SELECT
                    cache_layer,
                    operation,
                    COUNT(*) as total_operations,
                    AVG(response_time) as avg_response_time,
                    MIN(response_time) as min_response_time,
                    MAX(response_time) as max_response_time,
                    SUM(CASE WHEN hit_miss = 'hit' THEN 1 ELSE 0 END) as hits,
                    SUM(CASE WHEN hit_miss = 'miss' THEN 1 ELSE 0 END) as misses
                FROM cache_performance
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY cache_layer, operation
                ORDER BY cache_layer, operation
            ");
            $metrics['performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cache hit rates by layer
            $stmt = $this->pdo->query("
                SELECT
                    cache_layer,
                    ROUND(
                        (SUM(CASE WHEN hit_miss = 'hit' THEN 1 ELSE 0 END) /
                         COUNT(*)) * 100, 2
                    ) as hit_rate,
                    COUNT(*) as total_requests
                FROM cache_performance
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    AND operation = 'get'
                GROUP BY cache_layer
            ");
            $metrics['hit_rates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Most accessed cache keys
            $stmt = $this->pdo->query("
                SELECT
                    cache_key,
                    COUNT(*) as access_count,
                    AVG(response_time) as avg_response_time,
                    MAX(timestamp) as last_accessed
                FROM cache_performance
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY cache_key
                ORDER BY access_count DESC
                LIMIT 20
            ");
            $metrics['popular_keys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Failed to get cache performance metrics: " . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Set memory cache (Layer 1)
     */
    private function setMemoryCache(string $key, $data, int $ttl): void
    {
        // Clean up expired entries if cache is full
        if (count($this->memoryCache) >= $this->config['memory_cache_size']) {
            $this->cleanupExpiredMemoryCache();
        }

        $this->memoryCache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];

        $this->cacheStats['memory_cache_size'] = count($this->memoryCache);
    }

    /**
     * Clean up expired memory cache entries
     */
    private function cleanupExpiredMemoryCache(): void
    {
        $currentTime = time();
        foreach ($this->memoryCache as $key => $item) {
            if ($item['expires'] <= $currentTime) {
                unset($this->memoryCache[$key]);
            }
        }
    }

    /**
     * Serialize data for storage
     */
    private function serializeData($data)
    {
        if ($this->config['serialization_method'] === 'json') {
            return json_encode($data);
        } elseif ($this->config['serialization_method'] === 'igbinary' && extension_loaded('igbinary')) {
            return igbinary_serialize($data);
        } else {
            return serialize($data);
        }
    }

    /**
     * Unserialize data from storage
     */
    private function unserializeData($data)
    {
        if ($this->config['serialization_method'] === 'json') {
            return json_decode($data, true);
        } elseif ($this->config['serialization_method'] === 'igbinary' && extension_loaded('igbinary')) {
            return igbinary_unserialize($data);
        } else {
            return unserialize($data);
        }
    }

    /**
     * Generate query hash for caching
     */
    private function generateQueryHash(string $sql, array $params): string
    {
        return hash('sha256', $sql . serialize($params));
    }

    /**
     * Record cache operation for monitoring
     */
    private function recordCacheOperation(
        string $key,
        string $layer,
        string $operation,
        ?string $hitMiss = null,
        float $responseTime = 0,
        int $dataSize = 0
    ): void {
        if (!$this->config['enable_performance_monitoring']) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_performance
                (cache_key, cache_layer, operation, hit_miss, response_time, data_size)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$key, $layer, $operation, $hitMiss, $responseTime, $dataSize]);
        } catch (PDOException $e) {
            // Don't log error to avoid infinite loop
        }
    }

    /**
     * Get default warmup data
     */
    private function getDefaultWarmupData(): array
    {
        return [
            [
                'key' => 'system_config',
                'ttl' => 3600,
                'callback' => function() {
                    // Load system configuration
                    return ['version' => '1.0.0', 'environment' => 'production'];
                }
            ],
            [
                'key' => 'user_roles',
                'ttl' => 1800,
                'callback' => function() {
                    // Load user roles from database
                    return ['admin', 'user', 'moderator'];
                }
            ]
        ];
    }

    /**
     * Check if cache layer is available
     */
    public function isLayerAvailable(string $layer): bool
    {
        return $this->cacheLayers[$layer] ?? false;
    }

    /**
     * Get all available cache layers
     */
    public function getAvailableLayers(): array
    {
        return array_filter($this->cacheLayers);
    }

    /**
     * Optimize cache configuration based on usage patterns
     */
    public function optimizeConfiguration(): array
    {
        $recommendations = [];

        $stats = $this->getCacheStats();

        // Memory cache optimization
        if ($stats['memory_cache']['size'] > $this->config['memory_cache_size'] * 0.9) {
            $recommendations[] = [
                'type' => 'memory_cache',
                'recommendation' => 'Increase memory cache size',
                'current' => $stats['memory_cache']['size'],
                'suggested' => $this->config['memory_cache_size'] * 1.5
            ];
        }

        // Cache hit rate optimization
        if ($stats['performance']['hit_rate'] < 70) {
            $recommendations[] = [
                'type' => 'cache_strategy',
                'recommendation' => 'Low cache hit rate detected. Consider adjusting TTL values or cache warming strategy.',
                'current_hit_rate' => $stats['performance']['hit_rate']
            ];
        }

        return $recommendations;
    }
}

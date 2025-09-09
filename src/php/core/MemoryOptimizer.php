<?php
/**
 * TPT Government Platform - Memory Optimizer
 *
 * Advanced memory management and optimization system
 * with intelligent garbage collection, memory profiling, and optimization patterns
 */

namespace Core;

use PDO;
use PDOException;
use Exception;

class MemoryOptimizer
{
    /**
     * Database connection
     */
    private PDO $pdo;

    /**
     * Memory configuration
     */
    private array $config;

    /**
     * Memory usage tracking
     */
    private array $memoryStats = [];

    /**
     * Object cache for reuse
     */
    private array $objectCache = [];

    /**
     * Memory pools
     */
    private array $memoryPools = [];

    /**
     * Garbage collection triggers
     */
    private array $gcTriggers = [];

    /**
     * Memory profiling data
     */
    private array $profilingData = [];

    /**
     * Constructor
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'enable_memory_profiling' => true,
            'enable_object_pooling' => true,
            'enable_lazy_loading' => true,
            'enable_memory_pools' => true,
            'gc_threshold_mb' => 50, // 50MB
            'memory_warning_threshold_mb' => 100, // 100MB
            'memory_critical_threshold_mb' => 200, // 200MB
            'object_cache_ttl' => 300, // 5 minutes
            'pool_cleanup_interval' => 60, // 1 minute
            'profiling_sample_rate' => 0.1, // 10%
            'enable_memory_alerts' => true,
            'memory_retention_days' => 7
        ], $config);

        $this->initializeMemoryOptimizer();
    }

    /**
     * Initialize memory optimization system
     */
    private function initializeMemoryOptimizer(): void
    {
        if ($this->config['enable_memory_profiling']) {
            $this->createMemoryTables();
        }

        if ($this->config['enable_object_pooling']) {
            $this->initializeObjectPooling();
        }

        if ($this->config['enable_memory_pools']) {
            $this->initializeMemoryPools();
        }

        // Register shutdown function for cleanup
        register_shutdown_function([$this, 'shutdownCleanup']);
    }

    /**
     * Track memory usage
     */
    public function trackMemoryUsage(string $context = 'general', array $metadata = []): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $timestamp = microtime(true);

        $memoryData = [
            'context' => $context,
            'current_usage' => $currentUsage,
            'peak_usage' => $peakUsage,
            'timestamp' => $timestamp,
            'metadata' => $metadata,
            'php_memory_limit' => $this->getMemoryLimit()
        ];

        $this->memoryStats[] = $memoryData;

        // Store in database if profiling is enabled
        if ($this->config['enable_memory_profiling']) {
            $this->storeMemoryData($memoryData);
        }

        // Check memory thresholds
        $this->checkMemoryThresholds($currentUsage, $peakUsage);

        // Keep stats within limit
        if (count($this->memoryStats) > 1000) {
            array_shift($this->memoryStats);
        }

        return $memoryData;
    }

    /**
     * Get object from pool or create new one
     */
    public function getPooledObject(string $className, array $constructorArgs = []): object
    {
        if (!$this->config['enable_object_pooling']) {
            return $this->createObject($className, $constructorArgs);
        }

        $poolKey = $this->getPoolKey($className, $constructorArgs);

        // Check if object exists in pool
        if (isset($this->objectCache[$poolKey])) {
            $cached = $this->objectCache[$poolKey];

            // Check if object is still valid
            if ($this->isObjectValid($cached)) {
                $cached['last_accessed'] = time();
                $cached['access_count']++;
                return $cached['object'];
            } else {
                // Remove invalid object
                unset($this->objectCache[$poolKey]);
            }
        }

        // Create new object and add to pool
        $object = $this->createObject($className, $constructorArgs);

        $this->objectCache[$poolKey] = [
            'object' => $object,
            'created_at' => time(),
            'last_accessed' => time(),
            'access_count' => 1,
            'class_name' => $className,
            'constructor_args' => $constructorArgs
        ];

        return $object;
    }

    /**
     * Return object to pool
     */
    public function returnToPool(object $object): void
    {
        if (!$this->config['enable_object_pooling']) {
            return;
        }

        $className = get_class($object);
        $poolKey = $this->getPoolKey($className, []);

        if (isset($this->objectCache[$poolKey])) {
            $this->objectCache[$poolKey]['last_accessed'] = time();
        }
    }

    /**
     * Allocate memory from pool
     */
    public function allocateFromPool(string $poolName, int $size): ?string
    {
        if (!$this->config['enable_memory_pools']) {
            return null;
        }

        if (!isset($this->memoryPools[$poolName])) {
            $this->initializePool($poolName);
        }

        $pool = &$this->memoryPools[$poolName];

        // Check if pool has available memory
        if ($pool['used'] + $size > $pool['size']) {
            // Try to expand pool
            if (!$this->expandPool($poolName, $size)) {
                return null;
            }
        }

        // Allocate memory
        $offset = $pool['used'];
        $pool['used'] += $size;
        $pool['allocations'][] = [
            'offset' => $offset,
            'size' => $size,
            'timestamp' => time()
        ];

        return $poolName . '_' . $offset;
    }

    /**
     * Deallocate memory from pool
     */
    public function deallocateFromPool(string $poolName, string $allocationId): bool
    {
        if (!$this->config['enable_memory_pools'] ||
            !isset($this->memoryPools[$poolName])) {
            return false;
        }

        $pool = &$this->memoryPools[$poolName];
        $parts = explode('_', $allocationId);

        if (count($parts) !== 2) {
            return false;
        }

        $offset = (int)$parts[1];

        // Find and remove allocation
        foreach ($pool['allocations'] as $key => $allocation) {
            if ($allocation['offset'] === $offset) {
                $pool['used'] -= $allocation['size'];
                unset($pool['allocations'][$key]);
                return true;
            }
        }

        return false;
    }

    /**
     * Optimize memory usage
     */
    public function optimizeMemory(): array
    {
        $optimizations = [
            'garbage_collected' => 0,
            'pools_cleaned' => 0,
            'cache_cleared' => 0,
            'objects_reused' => 0
        ];

        // Force garbage collection
        $optimizations['garbage_collected'] = gc_collect_cycles();

        // Clean memory pools
        $optimizations['pools_cleaned'] = $this->cleanupMemoryPools();

        // Clear expired cache entries
        $optimizations['cache_cleared'] = $this->cleanupObjectCache();

        // Optimize object reuse
        $optimizations['objects_reused'] = $this->optimizeObjectReuse();

        return $optimizations;
    }

    /**
     * Get memory statistics
     */
    public function getMemoryStatistics(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();

        return [
            'current_usage' => $currentUsage,
            'peak_usage' => $peakUsage,
            'memory_limit' => $limit,
            'usage_percentage' => $limit > 0 ? ($currentUsage / $limit) * 100 : 0,
            'available_memory' => $limit - $currentUsage,
            'object_cache_size' => count($this->objectCache),
            'memory_pools_count' => count($this->memoryPools),
            'profiling_enabled' => $this->config['enable_memory_profiling'],
            'object_pooling_enabled' => $this->config['enable_object_pooling'],
            'memory_pools_enabled' => $this->config['enable_memory_pools']
        ];
    }

    /**
     * Get memory recommendations
     */
    public function getMemoryRecommendations(): array
    {
        $stats = $this->getMemoryStatistics();
        $recommendations = [];

        // Memory usage recommendations
        if ($stats['usage_percentage'] > 80) {
            $recommendations[] = [
                'type' => 'critical',
                'recommendation' => 'High memory usage detected. Consider increasing memory limit or optimizing memory usage.',
                'current_usage' => $stats['current_usage'],
                'memory_limit' => $stats['memory_limit']
            ];
        }

        // Object cache recommendations
        if ($stats['object_cache_size'] > 1000) {
            $recommendations[] = [
                'type' => 'warning',
                'recommendation' => 'Large object cache detected. Consider implementing cache size limits.',
                'cache_size' => $stats['object_cache_size']
            ];
        }

        // Memory pool recommendations
        if ($stats['memory_pools_count'] > 10) {
            $recommendations[] = [
                'type' => 'info',
                'recommendation' => 'Multiple memory pools in use. Monitor for potential memory fragmentation.',
                'pools_count' => $stats['memory_pools_count']
            ];
        }

        return $recommendations;
    }

    /**
     * Create lazy loading proxy
     */
    public function createLazyProxy(string $className, array $constructorArgs = []): object
    {
        if (!$this->config['enable_lazy_loading']) {
            return $this->createObject($className, $constructorArgs);
        }

        return new class($className, $constructorArgs, $this) {
            private string $className;
            private array $constructorArgs;
            private MemoryOptimizer $memoryOptimizer;
            private ?object $realObject = null;
            private bool $initialized = false;

            public function __construct(string $className, array $constructorArgs, MemoryOptimizer $memoryOptimizer) {
                $this->className = $className;
                $this->constructorArgs = $constructorArgs;
                $this->memoryOptimizer = $memoryOptimizer;
            }

            public function __call(string $method, array $args) {
                $this->initialize();
                return $this->realObject->{$method}(...$args);
            }

            public function __get(string $property) {
                $this->initialize();
                return $this->realObject->{$property};
            }

            public function __set(string $property, $value) {
                $this->initialize();
                $this->realObject->{$property} = $value;
            }

            private function initialize(): void {
                if (!$this->initialized) {
                    $this->realObject = $this->memoryOptimizer->getPooledObject(
                        $this->className,
                        $this->constructorArgs
                    );
                    $this->initialized = true;
                }
            }
        };
    }

    /**
     * Memory-efficient data processing
     */
    public function processLargeDataset(iterable $data, callable $processor, int $batchSize = 100): array
    {
        $results = [];
        $batch = [];
        $batchCount = 0;

        foreach ($data as $item) {
            $batch[] = $item;

            if (count($batch) >= $batchSize) {
                $batchResults = $processor($batch);
                $results = array_merge($results, $batchResults);

                // Force garbage collection between batches
                if ($batchCount % 10 === 0) {
                    gc_collect_cycles();
                }

                $batch = [];
                $batchCount++;
            }
        }

        // Process remaining items
        if (!empty($batch)) {
            $batchResults = $processor($batch);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * Memory-efficient file processing
     */
    public function processLargeFile(string $filePath, callable $lineProcessor, int $bufferSize = 8192): array
    {
        $results = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new Exception("Unable to open file: {$filePath}");
        }

        $buffer = '';
        $lineCount = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $bufferSize);
            $buffer .= $chunk;

            // Process complete lines
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                $result = $lineProcessor($line, $lineCount);
                if ($result !== null) {
                    $results[] = $result;
                }

                $lineCount++;

                // Memory optimization: clear large result arrays periodically
                if (count($results) > 1000) {
                    // Process results in batches
                    $this->processResultBatch($results);
                    $results = [];
                }
            }

            // Force garbage collection for large files
            if ($lineCount % 10000 === 0) {
                gc_collect_cycles();
            }
        }

        // Process remaining buffer
        if (!empty($buffer)) {
            $result = $lineProcessor($buffer, $lineCount);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        fclose($handle);

        return $results;
    }

    // Private helper methods

    private function createMemoryTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS memory_profiling (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                context VARCHAR(100) NOT NULL,
                current_usage INT UNSIGNED NOT NULL,
                peak_usage INT UNSIGNED NOT NULL,
                timestamp DECIMAL(16,6) NOT NULL,
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_context (context),
                INDEX idx_timestamp (timestamp),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS memory_optimization_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                description TEXT,
                memory_before INT UNSIGNED,
                memory_after INT UNSIGNED,
                timestamp DECIMAL(16,6) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create memory tables: " . $e->getMessage());
        }
    }

    private function initializeObjectPooling(): void
    {
        // Set up periodic cleanup
        if (function_exists('pcntl_fork')) {
            // In production, implement background cleanup
        }
    }

    private function initializeMemoryPools(): void
    {
        // Initialize default pools
        $this->initializePool('default', 1024 * 1024); // 1MB
        $this->initializePool('large_objects', 10 * 1024 * 1024); // 10MB
    }

    private function initializePool(string $poolName, int $initialSize = 1048576): void
    {
        $this->memoryPools[$poolName] = [
            'size' => $initialSize,
            'used' => 0,
            'allocations' => [],
            'created_at' => time(),
            'last_cleanup' => time()
        ];
    }

    private function expandPool(string $poolName, int $requiredSize): bool
    {
        if (!isset($this->memoryPools[$poolName])) {
            return false;
        }

        $pool = &$this->memoryPools[$poolName];
        $newSize = max($pool['size'] * 2, $pool['size'] + $requiredSize);

        // Check memory limit
        $currentUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryLimit > 0 && $currentUsage + $newSize > $memoryLimit) {
            return false;
        }

        $pool['size'] = $newSize;
        return true;
    }

    private function getPoolKey(string $className, array $constructorArgs): string
    {
        return md5($className . serialize($constructorArgs));
    }

    private function isObjectValid(array $cachedObject): bool
    {
        $ttl = $this->config['object_cache_ttl'];
        return (time() - $cachedObject['last_accessed']) < $ttl;
    }

    private function createObject(string $className, array $constructorArgs): object
    {
        if (!class_exists($className)) {
            throw new Exception("Class {$className} does not exist");
        }

        return new $className(...$constructorArgs);
    }

    private function checkMemoryThresholds(int $currentUsage, int $peakUsage): void
    {
        $currentMB = $currentUsage / 1024 / 1024;
        $warningThreshold = $this->config['memory_warning_threshold_mb'];
        $criticalThreshold = $this->config['memory_critical_threshold_mb'];

        if ($currentMB >= $criticalThreshold) {
            $this->triggerMemoryAlert('critical', $currentMB, $criticalThreshold);
            $this->optimizeMemory();
        } elseif ($currentMB >= $warningThreshold) {
            $this->triggerMemoryAlert('warning', $currentMB, $warningThreshold);
        }
    }

    private function triggerMemoryAlert(string $severity, float $currentMB, float $thresholdMB): void
    {
        $message = "Memory {$severity} alert: {$currentMB}MB used, threshold: {$thresholdMB}MB";

        error_log("MEMORY ALERT: {$message}");

        // Store alert
        $this->storeMemoryEvent('memory_alert', $message, $currentMB * 1024 * 1024, 0);
    }

    private function cleanupMemoryPools(): int
    {
        $cleaned = 0;

        foreach ($this->memoryPools as $poolName => &$pool) {
            $cleanupTime = time() - $this->config['pool_cleanup_interval'];

            if ($pool['last_cleanup'] < $cleanupTime) {
                // Remove old allocations
                $pool['allocations'] = array_filter($pool['allocations'], function($allocation) use ($cleanupTime) {
                    return $allocation['timestamp'] > $cleanupTime;
                });

                $pool['last_cleanup'] = time();
                $cleaned++;
            }
        }

        return $cleaned;
    }

    private function cleanupObjectCache(): int
    {
        $cleaned = 0;
        $currentTime = time();
        $ttl = $this->config['object_cache_ttl'];

        foreach ($this->objectCache as $key => $cached) {
            if (($currentTime - $cached['last_accessed']) > $ttl) {
                unset($this->objectCache[$key]);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    private function optimizeObjectReuse(): int
    {
        // Analyze object usage patterns and optimize reuse
        return 0;
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 0; // Unlimited
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }

    private function processResultBatch(array &$results): void
    {
        // Process batch of results (e.g., save to database, send to queue, etc.)
        // This is a placeholder for actual batch processing logic
    }

    private function storeMemoryData(array $memoryData): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO memory_profiling
                (context, current_usage, peak_usage, timestamp, metadata)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $memoryData['context'],
                $memoryData['current_usage'],
                $memoryData['peak_usage'],
                $memoryData['timestamp'],
                json_encode($memoryData['metadata'] ?? [])
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store memory data: " . $e->getMessage());
        }
    }

    private function storeMemoryEvent(string $eventType, string $description, int $memoryBefore, int $memoryAfter): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO memory_optimization_events
                (event_type, description, memory_before, memory_after, timestamp)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $eventType,
                $description,
                $memoryBefore,
                $memoryAfter,
                microtime(true)
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store memory event: " . $e->getMessage());
        }
    }

    public function shutdownCleanup(): void
    {
        // Perform cleanup on shutdown
        $this->optimizeMemory();

        // Log final memory statistics
        $finalStats = $this->getMemoryStatistics();
        error_log("Memory cleanup completed. Final stats: " . json_encode($finalStats));
    }
}

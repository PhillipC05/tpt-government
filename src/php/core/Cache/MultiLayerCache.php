<?php
/**
 * TPT Government Platform - Multi-Layer Cache Implementation
 *
 * Implements a sophisticated multi-layer caching system with memory, file, and database layers
 * supporting various eviction policies and performance optimization
 */

class MultiLayerCache implements CacheInterface
{
    private $layers = [];
    private $evictionPolicy;
    private $cacheStats = [
        'layer_hits' => ['memory' => 0, 'file' => 0, 'database' => 0],
        'layer_misses' => ['memory' => 0, 'file' => 0, 'database' => 0],
        'promotions' => 0,
        'demotions' => 0,
        'evictions' => 0
    ];
    private $config;

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->config = array_merge([
            'memory_enabled' => true,
            'file_enabled' => true,
            'database_enabled' => true,
            'eviction_policy' => 'adaptive',
            'max_memory_size' => 1000,
            'max_file_size' => 10000,
            'max_db_size' => 50000,
            'promotion_threshold' => 3, // Access count to promote to higher layer
            'demotion_threshold' => 0.1, // Hit rate threshold to demote
            'write_through' => true, // Write to all layers immediately
            'read_through' => true, // Read from higher layers first
            'auto_cleanup' => true,
            'cleanup_interval' => 3600 // 1 hour
        ], $config);

        $this->initializeLayers();
        $this->initializeEvictionPolicy();

        if ($this->config['auto_cleanup']) {
            $this->scheduleCleanup();
        }
    }

    /**
     * Initialize cache layers
     */
    private function initializeLayers()
    {
        // Memory layer (fastest)
        if ($this->config['memory_enabled']) {
            $this->layers['memory'] = new MemoryCacheLayer([
                'max_size' => $this->config['max_memory_size'],
                'eviction_policy' => $this->config['eviction_policy']
            ]);
        }

        // File layer (medium speed)
        if ($this->config['file_enabled']) {
            $this->layers['file'] = new FileCacheLayer([
                'max_size' => $this->config['max_file_size'],
                'cache_dir' => CACHE_PATH . '/multi_layer/',
                'eviction_policy' => $this->config['eviction_policy']
            ]);
        }

        // Database layer (persistent, slowest)
        if ($this->config['database_enabled']) {
            $this->layers['database'] = new DatabaseCacheLayer([
                'max_size' => $this->config['max_db_size'],
                'table_name' => 'multi_layer_cache',
                'eviction_policy' => $this->config['eviction_policy']
            ]);
        }
    }

    /**
     * Initialize eviction policy
     */
    private function initializeEvictionPolicy()
    {
        $this->evictionPolicy = CacheEvictionPolicyFactory::create(
            $this->config['eviction_policy'],
            max($this->config['max_memory_size'], $this->config['max_file_size'], $this->config['max_db_size'])
        );
    }

    /**
     * Get an item from cache
     */
    public function get($key, $default = null)
    {
        // Try layers in order (memory -> file -> database)
        foreach ($this->layers as $layerName => $layer) {
            $result = $layer->get($key);

            if ($result !== null) {
                $this->cacheStats['layer_hits'][$layerName]++;

                // Promote to higher layers if accessed frequently
                $this->handlePromotion($key, $result, $layerName);

                return $result;
            } else {
                $this->cacheStats['layer_misses'][$layerName]++;
            }
        }

        return $default;
    }

    /**
     * Set an item in cache
     */
    public function set($key, $value, $ttl = 0)
    {
        $success = true;

        if ($this->config['write_through']) {
            // Write to all layers
            foreach ($this->layers as $layer) {
                if (!$layer->set($key, $value, $ttl)) {
                    $success = false;
                }
            }
        } else {
            // Write to first available layer
            foreach ($this->layers as $layer) {
                if ($layer->set($key, $value, $ttl)) {
                    break;
                }
            }
        }

        // Check for evictions
        $this->checkEvictions();

        return $success;
    }

    /**
     * Delete an item from cache
     */
    public function delete($key)
    {
        $success = false;

        foreach ($this->layers as $layer) {
            if ($layer->delete($key)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Clear all cache
     */
    public function clear()
    {
        $success = true;

        foreach ($this->layers as $layer) {
            if (!$layer->clear()) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if key exists
     */
    public function has($key)
    {
        foreach ($this->layers as $layer) {
            if ($layer->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get multiple items
     */
    public function getMultiple($keys)
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    /**
     * Set multiple items
     */
    public function setMultiple($values, $ttl = 0)
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Delete multiple items
     */
    public function deleteMultiple($keys)
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $stats = $this->cacheStats;

        // Add layer-specific stats
        foreach ($this->layers as $layerName => $layer) {
            if (method_exists($layer, 'getStats')) {
                $stats['layers'][$layerName] = $layer->getStats();
            }
        }

        // Calculate overall hit rate
        $totalHits = array_sum($stats['layer_hits']);
        $totalMisses = array_sum($stats['layer_misses']);
        $totalRequests = $totalHits + $totalMisses;

        $stats['overall_hit_rate'] = $totalRequests > 0 ? ($totalHits / $totalRequests) * 100 : 0;
        $stats['total_requests'] = $totalRequests;

        return $stats;
    }

    /**
     * Handle cache promotion (move frequently accessed items to higher layers)
     */
    private function handlePromotion($key, $value, $currentLayer)
    {
        $layerOrder = array_keys($this->layers);
        $currentIndex = array_search($currentLayer, $layerOrder);

        // If not in highest layer and meets promotion criteria
        if ($currentIndex > 0 && $this->shouldPromote($key, $currentLayer)) {
            $higherLayer = $this->layers[$layerOrder[$currentIndex - 1]];

            // Get TTL from current layer if available
            $ttl = 3600; // Default 1 hour
            if (method_exists($this->layers[$currentLayer], 'getTtl')) {
                $ttl = $this->layers[$currentLayer]->getTtl($key);
            }

            $higherLayer->set($key, $value, $ttl);
            $this->cacheStats['promotions']++;
        }
    }

    /**
     * Handle cache demotion (move infrequently accessed items to lower layers)
     */
    private function handleDemotion($key, $currentLayer)
    {
        $layerOrder = array_keys($this->layers);
        $currentIndex = array_search($currentLayer, $layerOrder);

        // If not in lowest layer and meets demotion criteria
        if ($currentIndex < count($layerOrder) - 1 && $this->shouldDemote($key, $currentLayer)) {
            $value = $this->layers[$currentLayer]->get($key);
            $lowerLayer = $this->layers[$layerOrder[$currentIndex + 1]];

            // Get TTL from current layer if available
            $ttl = 3600; // Default 1 hour
            if (method_exists($this->layers[$currentLayer], 'getTtl')) {
                $ttl = $this->layers[$currentLayer]->getTtl($key);
            }

            $lowerLayer->set($key, $value, $ttl);
            $this->layers[$currentLayer]->delete($key);
            $this->cacheStats['demotions']++;
        }
    }

    /**
     * Check if item should be promoted
     */
    private function shouldPromote($key, $currentLayer)
    {
        if (!isset($this->layers[$currentLayer]) ||
            !method_exists($this->layers[$currentLayer], 'getAccessCount')) {
            return false;
        }

        $accessCount = $this->layers[$currentLayer]->getAccessCount($key);
        return $accessCount >= $this->config['promotion_threshold'];
    }

    /**
     * Check if item should be demoted
     */
    private function shouldDemote($key, $currentLayer)
    {
        if (!isset($this->layers[$currentLayer]) ||
            !method_exists($this->layers[$currentLayer], 'getHitRate')) {
            return false;
        }

        $hitRate = $this->layers[$currentLayer]->getHitRate($key);
        return $hitRate < $this->config['demotion_threshold'];
    }

    /**
     * Check for and perform evictions
     */
    private function checkEvictions()
    {
        foreach ($this->layers as $layer) {
            if (method_exists($layer, 'shouldEvict') && $layer->shouldEvict()) {
                $evictedKeys = $layer->evict();
                $this->cacheStats['evictions'] += count($evictedKeys);
            }
        }
    }

    /**
     * Schedule automatic cleanup
     */
    private function scheduleCleanup()
    {
        // In a real application, this would be handled by a cron job or scheduled task
        register_shutdown_function([$this, 'cleanup']);
    }

    /**
     * Clean up expired items
     */
    public function cleanup()
    {
        foreach ($this->layers as $layer) {
            if (method_exists($layer, 'cleanup')) {
                $layer->cleanup();
            }
        }
    }

    /**
     * Get layer information
     */
    public function getLayerInfo()
    {
        $info = [];

        foreach ($this->layers as $layerName => $layer) {
            $info[$layerName] = [
                'enabled' => true,
                'size' => method_exists($layer, 'getSize') ? $layer->getSize() : 0,
                'max_size' => method_exists($layer, 'getMaxSize') ? $layer->getMaxSize() : 0
            ];
        }

        return $info;
    }

    /**
     * Optimize cache performance
     */
    public function optimize()
    {
        // Analyze access patterns and adjust policies
        $stats = $this->getStats();

        // Adjust promotion/demotion thresholds based on performance
        if ($stats['overall_hit_rate'] < 50) {
            $this->config['promotion_threshold'] = max(1, $this->config['promotion_threshold'] - 1);
        } elseif ($stats['overall_hit_rate'] > 80) {
            $this->config['promotion_threshold'] = min(10, $this->config['promotion_threshold'] + 1);
        }

        // Rebalance layers if needed
        $this->rebalanceLayers();
    }

    /**
     * Rebalance cache layers
     */
    private function rebalanceLayers()
    {
        $layerInfo = $this->getLayerInfo();

        foreach ($layerInfo as $layerName => $info) {
            if ($info['size'] > $info['max_size'] * 0.9) {
                // Layer is near capacity, trigger cleanup
                if (isset($this->layers[$layerName]) &&
                    method_exists($this->layers[$layerName], 'cleanup')) {
                    $this->layers[$layerName]->cleanup();
                }
            }
        }
    }
}

/**
 * Memory Cache Layer
 */
class MemoryCacheLayer
{
    private $cache = [];
    private $accessCounts = [];
    private $maxSize;
    private $evictionPolicy;

    public function __construct($config = [])
    {
        $this->maxSize = $config['max_size'] ?? 1000;
        $this->evictionPolicy = CacheEvictionPolicyFactory::create(
            $config['eviction_policy'] ?? 'lru',
            $this->maxSize
        );
    }

    public function get($key)
    {
        if (isset($this->cache[$key])) {
            $this->accessCounts[$key] = ($this->accessCounts[$key] ?? 0) + 1;
            $this->evictionPolicy->recordAccess($key);
            return $this->cache[$key]['value'];
        }

        return null;
    }

    public function set($key, $value, $ttl = 0)
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0
        ];

        $this->evictionPolicy->recordAddition($key);

        // Check for eviction
        if ($this->evictionPolicy->shouldEvict()) {
            $this->evict();
        }

        return true;
    }

    public function delete($key)
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            unset($this->accessCounts[$key]);
            $this->evictionPolicy->recordRemoval($key);
            return true;
        }

        return false;
    }

    public function clear()
    {
        $this->cache = [];
        $this->accessCounts = [];
        return true;
    }

    public function has($key)
    {
        return isset($this->cache[$key]) && !$this->isExpired($key);
    }

    public function getAccessCount($key)
    {
        return $this->accessCounts[$key] ?? 0;
    }

    public function getHitRate($key)
    {
        $accessCount = $this->getAccessCount($key);
        return $accessCount > 0 ? ($accessCount / (time() - ($this->cache[$key]['created'] ?? time()))) : 0;
    }

    public function shouldEvict()
    {
        return $this->evictionPolicy->shouldEvict();
    }

    public function evict()
    {
        $keysToEvict = $this->evictionPolicy->getKeysToEvict($this->cache);

        foreach ($keysToEvict as $key) {
            $this->delete($key);
        }

        return $keysToEvict;
    }

    public function cleanup()
    {
        foreach ($this->cache as $key => $item) {
            if ($this->isExpired($key)) {
                $this->delete($key);
            }
        }
    }

    public function getSize()
    {
        return count($this->cache);
    }

    public function getMaxSize()
    {
        return $this->maxSize;
    }

    public function getStats()
    {
        return [
            'size' => $this->getSize(),
            'max_size' => $this->maxSize(),
            'utilization' => ($this->getSize() / $this->maxSize) * 100
        ];
    }

    private function isExpired($key)
    {
        return isset($this->cache[$key]['expires']) &&
               $this->cache[$key]['expires'] > 0 &&
               $this->cache[$key]['expires'] < time();
    }
}

/**
 * File Cache Layer
 */
class FileCacheLayer
{
    private $cacheDir;
    private $maxSize;
    private $evictionPolicy;

    public function __construct($config = [])
    {
        $this->cacheDir = $config['cache_dir'] ?? CACHE_PATH . '/file_layer/';
        $this->maxSize = $config['max_size'] ?? 10000;
        $this->evictionPolicy = CacheEvictionPolicyFactory::create(
            $config['eviction_policy'] ?? 'lru',
            $this->maxSize
        );

        $this->ensureCacheDirectory();
    }

    private function ensureCacheDirectory()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get($key)
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $data = unserialize(file_get_contents($filePath));

        if ($this->isExpired($data)) {
            unlink($filePath);
            return null;
        }

        $this->evictionPolicy->recordAccess($key);
        return $data['value'];
    }

    public function set($key, $value, $ttl = 0)
    {
        $filePath = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time()
        ];

        if (file_put_contents($filePath, serialize($data)) === false) {
            return false;
        }

        $this->evictionPolicy->recordAddition($key);

        if ($this->evictionPolicy->shouldEvict()) {
            $this->evict();
        }

        return true;
    }

    public function delete($key)
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            unlink($filePath);
            $this->evictionPolicy->recordRemoval($key);
            return true;
        }

        return false;
    }

    public function clear()
    {
        $this->removeDirectory($this->cacheDir);
        $this->ensureCacheDirectory();
        return true;
    }

    public function has($key)
    {
        $filePath = $this->getFilePath($key);
        return file_exists($filePath) && !$this->isExpired(unserialize(file_get_contents($filePath)));
    }

    public function shouldEvict()
    {
        return $this->evictionPolicy->shouldEvict();
    }

    public function evict()
    {
        $files = glob($this->cacheDir . '*.cache');
        $cacheItems = [];

        foreach ($files as $file) {
            $key = basename($file, '.cache');
            $cacheItems[$key] = unserialize(file_get_contents($file));
        }

        $keysToEvict = $this->evictionPolicy->getKeysToEvict($cacheItems);

        foreach ($keysToEvict as $key) {
            $this->delete($key);
        }

        return $keysToEvict;
    }

    public function cleanup()
    {
        $files = glob($this->cacheDir . '*.cache');

        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($this->isExpired($data)) {
                unlink($file);
            }
        }
    }

    public function getSize()
    {
        $files = glob($this->cacheDir . '*.cache');
        return count($files);
    }

    public function getMaxSize()
    {
        return $this->maxSize;
    }

    private function getFilePath($key)
    {
        return $this->cacheDir . md5($key) . '.cache';
    }

    private function isExpired($data)
    {
        return isset($data['expires']) && $data['expires'] > 0 && $data['expires'] < time();
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

/**
 * Database Cache Layer
 */
class DatabaseCacheLayer
{
    private $db;
    private $tableName;
    private $maxSize;
    private $evictionPolicy;

    public function __construct($config = [])
    {
        $this->tableName = $config['table_name'] ?? 'cache_layer';
        $this->maxSize = $config['max_size'] ?? 50000;
        $this->evictionPolicy = CacheEvictionPolicyFactory::create(
            $config['eviction_policy'] ?? 'lru',
            $this->maxSize
        );

        $this->initializeDatabase();
    }

    private function initializeDatabase()
    {
        // Get database connection
        if (class_exists('Container') && Container::has('database')) {
            $this->db = Container::get('database');
        } else {
            $config = require CONFIG_PATH . '/database.php';
            $this->db = new PDO(
                "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
                $config['username'],
                $config['password'],
                $config['options']
            );
        }

        $this->createTable();
    }

    private function createTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value TEXT,
                expires_at TIMESTAMP,
                access_count INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $this->db->exec($sql);
    }

    public function get($key)
    {
        $stmt = $this->db->prepare("
            SELECT cache_value, expires_at
            FROM {$this->tableName}
            WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
        ");

        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        // Update access statistics
        $this->db->prepare("
            UPDATE {$this->tableName}
            SET access_count = access_count + 1, last_accessed = CURRENT_TIMESTAMP
            WHERE cache_key = ?
        ")->execute([$key]);

        $this->evictionPolicy->recordAccess($key);

        return unserialize($result['cache_value']);
    }

    public function set($key, $value, $ttl = 0)
    {
        $expiresAt = $ttl > 0 ? date('Y-m-d H:i:s', time() + $ttl) : null;
        $serializedValue = serialize($value);

        $stmt = $this->db->prepare("
            INSERT INTO {$this->tableName} (cache_key, cache_value, expires_at)
            VALUES (?, ?, ?)
            ON CONFLICT (cache_key) DO UPDATE SET
                cache_value = EXCLUDED.cache_value,
                expires_at = EXCLUDED.expires_at,
                last_accessed = CURRENT_TIMESTAMP
        ");

        $result = $stmt->execute([$key, $serializedValue, $expiresAt]);

        if ($result) {
            $this->evictionPolicy->recordAddition($key);

            if ($this->evictionPolicy->shouldEvict()) {
                $this->evict();
            }
        }

        return $result;
    }

    public function delete($key)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->tableName} WHERE cache_key = ?");
        $result = $stmt->execute([$key]);

        if ($result) {
            $this->evictionPolicy->recordRemoval($key);
        }

        return $result;
    }

    public function clear()
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->tableName}");
        return $stmt->execute();
    }

    public function has($key)
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM {$this->tableName}
            WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
        ");

        $stmt->execute([$key]);
        return $stmt->fetch() !== false;
    }

    public function shouldEvict()
    {
        return $this->evictionPolicy->shouldEvict();
    }

    public function evict()
    {
        // Get all cache items for eviction policy
        $stmt = $this->db->prepare("SELECT * FROM {$this->tableName}");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cacheItems = [];
        foreach ($items as $item) {
            $cacheItems[$item['cache_key']] = $item;
        }

        $keysToEvict = $this->evictionPolicy->getKeysToEvict($cacheItems);

        if (!empty($keysToEvict)) {
            $placeholders = str_repeat('?,', count($keysToEvict) - 1) . '?';
            $stmt = $this->db->prepare("DELETE FROM {$this->tableName} WHERE cache_key IN ({$placeholders})");
            $stmt->execute($keysToEvict);
        }

        return $keysToEvict;
    }

    public function cleanup()
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->tableName} WHERE expires_at <= CURRENT_TIMESTAMP");
        $stmt->execute();
    }

    public function getSize()
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->tableName}");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    public function getMaxSize()
    {
        return $this->maxSize;
    }
}

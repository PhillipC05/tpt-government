<?php
/**
 * TPT Government Platform - Module Cache Manager
 *
 * Provides comprehensive caching for module operations and data
 * with support for memory, file, and database caching layers
 */

class ModuleCacheManager
{
    private $memoryCache = [];
    private $fileCachePath;
    private $dbCache;
    private $cacheConfig;
    private $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'clears' => 0
    ];

    /**
     * Constructor
     */
    public function __construct($dbConnection = null, $config = [])
    {
        $this->dbCache = $dbConnection;
        $this->fileCachePath = $config['file_cache_path'] ?? CACHE_PATH . '/modules/';
        $this->cacheConfig = array_merge([
            'default_ttl' => 3600, // 1 hour
            'memory_cache_size' => 100, // Max items in memory
            'enable_file_cache' => true,
            'enable_db_cache' => true,
            'cache_compression' => true,
            'auto_cleanup' => true,
            'cleanup_interval' => 86400 // 24 hours
        ], $config);

        $this->ensureCacheDirectories();
        $this->initializeCacheTable();

        if ($this->cacheConfig['auto_cleanup']) {
            $this->scheduleCacheCleanup();
        }
    }

    /**
     * Ensure cache directories exist
     */
    private function ensureCacheDirectories()
    {
        if (!is_dir($this->fileCachePath)) {
            mkdir($this->fileCachePath, 0755, true);
        }

        $subDirs = ['metadata', 'config', 'data', 'stats'];
        foreach ($subDirs as $subDir) {
            $dir = $this->fileCachePath . '/' . $subDir;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Initialize database cache table
     */
    private function initializeCacheTable()
    {
        if (!$this->dbCache || !$this->cacheConfig['enable_db_cache']) {
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS module_cache (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value TEXT,
                cache_type VARCHAR(50),
                module_name VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP,
                compressed BOOLEAN DEFAULT FALSE
            );

            CREATE INDEX IF NOT EXISTS idx_module_cache_expires ON module_cache(expires_at);
            CREATE INDEX IF NOT EXISTS idx_module_cache_type ON module_cache(cache_type);
            CREATE INDEX IF NOT EXISTS idx_module_cache_module ON module_cache(module_name);
        ";

        try {
            $this->dbCache->exec($sql);
        } catch (Exception $e) {
            // Log error but don't fail
            error_log("Failed to initialize module cache table: " . $e->getMessage());
        }
    }

    /**
     * Get cache key for module data
     */
    private function getCacheKey($moduleName, $type, $key)
    {
        return "module:{$moduleName}:{$type}:{$key}";
    }

    /**
     * Get cached data
     */
    public function get($moduleName, $type, $key, $default = null)
    {
        $cacheKey = $this->getCacheKey($moduleName, $type, $key);

        // Try memory cache first
        if (isset($this->memoryCache[$cacheKey])) {
            $data = $this->memoryCache[$cacheKey];
            if ($this->isExpired($data)) {
                unset($this->memoryCache[$cacheKey]);
                $this->cacheStats['misses']++;
                return $default;
            }

            $this->cacheStats['hits']++;
            return $this->uncompressData($data['value']);
        }

        // Try file cache
        $fileData = $this->getFromFileCache($cacheKey);
        if ($fileData !== null) {
            // Store in memory cache for faster future access
            $this->memoryCache[$cacheKey] = $fileData;
            $this->cacheStats['hits']++;
            return $this->uncompressData($fileData['value']);
        }

        // Try database cache
        $dbData = $this->getFromDatabaseCache($cacheKey);
        if ($dbData !== null) {
            // Store in memory and file cache
            $this->memoryCache[$cacheKey] = $dbData;
            $this->setFileCache($cacheKey, $dbData);
            $this->cacheStats['hits']++;
            return $this->uncompressData($dbData['value']);
        }

        $this->cacheStats['misses']++;
        return $default;
    }

    /**
     * Set cached data
     */
    public function set($moduleName, $type, $key, $value, $ttl = null)
    {
        $cacheKey = $this->getCacheKey($moduleName, $type, $key);
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];

        $compressedValue = $this->compressData($value);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $cacheData = [
            'value' => $compressedValue,
            'expires_at' => $expiresAt,
            'module_name' => $moduleName,
            'type' => $type,
            'compressed' => $this->cacheConfig['cache_compression']
        ];

        // Store in memory cache
        $this->memoryCache[$cacheKey] = $cacheData;

        // Store in file cache
        $this->setFileCache($cacheKey, $cacheData);

        // Store in database cache
        $this->setDatabaseCache($cacheKey, $cacheData);

        // Maintain memory cache size
        $this->maintainMemoryCacheSize();

        $this->cacheStats['sets']++;
    }

    /**
     * Delete cached data
     */
    public function delete($moduleName, $type = null, $key = null)
    {
        if ($type === null) {
            // Delete all cache for module
            $this->deleteModuleCache($moduleName);
        } elseif ($key === null) {
            // Delete all cache for module and type
            $this->deleteModuleTypeCache($moduleName, $type);
        } else {
            // Delete specific cache entry
            $cacheKey = $this->getCacheKey($moduleName, $type, $key);
            $this->deleteCacheKey($cacheKey);
        }

        $this->cacheStats['deletes']++;
    }

    /**
     * Clear all cache
     */
    public function clear()
    {
        // Clear memory cache
        $this->memoryCache = [];

        // Clear file cache
        $this->clearFileCache();

        // Clear database cache
        $this->clearDatabaseCache();

        $this->cacheStats['clears']++;
    }

    /**
     * Check if cache key exists and is valid
     */
    public function has($moduleName, $type, $key)
    {
        $cacheKey = $this->getCacheKey($moduleName, $type, $key);

        // Check memory cache
        if (isset($this->memoryCache[$cacheKey]) && !$this->isExpired($this->memoryCache[$cacheKey])) {
            return true;
        }

        // Check file cache
        if ($this->getFromFileCache($cacheKey) !== null) {
            return true;
        }

        // Check database cache
        if ($this->getFromDatabaseCache($cacheKey) !== null) {
            return true;
        }

        return false;
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $totalRequests = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        $hitRate = $totalRequests > 0 ? ($this->cacheStats['hits'] / $totalRequests) * 100 : 0;

        return [
            'memory_cache_size' => count($this->memoryCache),
            'file_cache_enabled' => $this->cacheConfig['enable_file_cache'],
            'database_cache_enabled' => $this->cacheConfig['enable_db_cache'],
            'compression_enabled' => $this->cacheConfig['cache_compression'],
            'hits' => $this->cacheStats['hits'],
            'misses' => $this->cacheStats['misses'],
            'sets' => $this->cacheStats['sets'],
            'deletes' => $this->cacheStats['deletes'],
            'clears' => $this->cacheStats['clears'],
            'hit_rate' => round($hitRate, 2) . '%',
            'total_requests' => $totalRequests
        ];
    }

    /**
     * Get module-specific cache statistics
     */
    public function getModuleStats($moduleName)
    {
        $stats = [
            'module' => $moduleName,
            'memory_entries' => 0,
            'file_entries' => 0,
            'db_entries' => 0,
            'total_size' => 0
        ];

        // Count memory cache entries
        foreach ($this->memoryCache as $key => $data) {
            if (strpos($key, "module:{$moduleName}:") === 0) {
                $stats['memory_entries']++;
                $stats['total_size'] += strlen(serialize($data));
            }
        }

        // Count file cache entries
        $moduleCacheDir = $this->fileCachePath . '/data/' . $moduleName;
        if (is_dir($moduleCacheDir)) {
            $files = glob($moduleCacheDir . '/*.cache');
            $stats['file_entries'] = count($files);
        }

        // Count database cache entries
        if ($this->dbCache && $this->cacheConfig['enable_db_cache']) {
            try {
                $stmt = $this->dbCache->prepare("SELECT COUNT(*) as count FROM module_cache WHERE module_name = ?");
                $stmt->execute([$moduleName]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['db_entries'] = (int) $result['count'];
            } catch (Exception $e) {
                $stats['db_entries'] = 0;
            }
        }

        return $stats;
    }

    /**
     * Warm up cache for a module
     */
    public function warmupModule($moduleName, $moduleInstance)
    {
        // Cache metadata
        $metadata = $moduleInstance->getMetadata();
        $this->set($moduleName, 'metadata', 'info', $metadata, 86400); // 24 hours

        // Cache configuration
        $config = $moduleInstance->getConfig();
        $this->set($moduleName, 'config', 'main', $config, 3600); // 1 hour

        // Cache statistics
        $stats = $moduleInstance->getStatistics();
        $this->set($moduleName, 'stats', 'main', $stats, 1800); // 30 minutes

        // Cache endpoints if available
        if (method_exists($moduleInstance, 'getEndpoints')) {
            $endpoints = $moduleInstance->getEndpoints();
            $this->set($moduleName, 'endpoints', 'list', $endpoints, 3600);
        }

        // Cache permissions if available
        if (method_exists($moduleInstance, 'getPermissions')) {
            $permissions = $moduleInstance->getPermissions();
            $this->set($moduleName, 'permissions', 'list', $permissions, 3600);
        }
    }

    /**
     * Preload frequently accessed data
     */
    public function preloadCommonData()
    {
        // This would be implemented based on access patterns
        // For now, it's a placeholder for future optimization
    }

    /**
     * Clean up expired cache entries
     */
    public function cleanup()
    {
        // Clean memory cache
        $this->cleanupMemoryCache();

        // Clean file cache
        $this->cleanupFileCache();

        // Clean database cache
        $this->cleanupDatabaseCache();
    }

    // Private helper methods

    private function getFromFileCache($cacheKey)
    {
        if (!$this->cacheConfig['enable_file_cache']) {
            return null;
        }

        $filePath = $this->getFileCachePath($cacheKey);

        if (!file_exists($filePath)) {
            return null;
        }

        $data = unserialize(file_get_contents($filePath));

        if ($this->isExpired($data)) {
            unlink($filePath);
            return null;
        }

        return $data;
    }

    private function setFileCache($cacheKey, $data)
    {
        if (!$this->cacheConfig['enable_file_cache']) {
            return;
        }

        $filePath = $this->getFileCachePath($cacheKey);
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, serialize($data));
    }

    private function getFromDatabaseCache($cacheKey)
    {
        if (!$this->dbCache || !$this->cacheConfig['enable_db_cache']) {
            return null;
        }

        try {
            $stmt = $this->dbCache->prepare("
                SELECT cache_value, expires_at, compressed
                FROM module_cache
                WHERE cache_key = ? AND expires_at > CURRENT_TIMESTAMP
            ");
            $stmt->execute([$cacheKey]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            return [
                'value' => $result['cache_value'],
                'expires_at' => $result['expires_at'],
                'compressed' => (bool) $result['compressed']
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    private function setDatabaseCache($cacheKey, $data)
    {
        if (!$this->dbCache || !$this->cacheConfig['enable_db_cache']) {
            return;
        }

        try {
            // First try to update existing entry
            $stmt = $this->dbCache->prepare("
                UPDATE module_cache
                SET cache_value = ?, expires_at = ?, compressed = ?
                WHERE cache_key = ?
            ");
            $stmt->execute([
                $data['value'],
                $data['expires_at'],
                $data['compressed'],
                $cacheKey
            ]);

            // If no rows were updated, insert new entry
            if ($stmt->rowCount() === 0) {
                $stmt = $this->dbCache->prepare("
                    INSERT INTO module_cache (cache_key, cache_value, cache_type, module_name, expires_at, compressed)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $cacheKey,
                    $data['value'],
                    $data['type'],
                    $data['module_name'],
                    $data['expires_at'],
                    $data['compressed']
                ]);
            }
        } catch (Exception $e) {
            // Log error but don't fail
            error_log("Failed to set database cache: " . $e->getMessage());
        }
    }

    private function deleteCacheKey($cacheKey)
    {
        // Remove from memory cache
        unset($this->memoryCache[$cacheKey]);

        // Remove from file cache
        $filePath = $this->getFileCachePath($cacheKey);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Remove from database cache
        if ($this->dbCache && $this->cacheConfig['enable_db_cache']) {
            try {
                $stmt = $this->dbCache->prepare("DELETE FROM module_cache WHERE cache_key = ?");
                $stmt->execute([$cacheKey]);
            } catch (Exception $e) {
                // Log error but don't fail
            }
        }
    }

    private function deleteModuleCache($moduleName)
    {
        // Clear memory cache for module
        foreach (array_keys($this->memoryCache) as $key) {
            if (strpos($key, "module:{$moduleName}:") === 0) {
                unset($this->memoryCache[$key]);
            }
        }

        // Clear file cache for module
        $moduleCacheDir = $this->fileCachePath . '/data/' . $moduleName;
        if (is_dir($moduleCacheDir)) {
            $this->removeDirectory($moduleCacheDir);
        }

        // Clear database cache for module
        if ($this->dbCache && $this->cacheConfig['enable_db_cache']) {
            try {
                $stmt = $this->dbCache->prepare("DELETE FROM module_cache WHERE module_name = ?");
                $stmt->execute([$moduleName]);
            } catch (Exception $e) {
                // Log error but don't fail
            }
        }
    }

    private function deleteModuleTypeCache($moduleName, $type)
    {
        $prefix = "module:{$moduleName}:{$type}:";

        // Clear memory cache
        foreach (array_keys($this->memoryCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->memoryCache[$key]);
            }
        }

        // Clear file cache
        $typeCacheDir = $this->fileCachePath . '/data/' . $moduleName . '/' . $type;
        if (is_dir($typeCacheDir)) {
            $this->removeDirectory($typeCacheDir);
        }

        // Clear database cache
        if ($this->dbCache && $this->cacheConfig['enable_db_cache']) {
            try {
                $stmt = $this->dbCache->prepare("DELETE FROM module_cache WHERE module_name = ? AND cache_type = ?");
                $stmt->execute([$moduleName, $type]);
            } catch (Exception $e) {
                // Log error but don't fail
            }
        }
    }

    private function clearFileCache()
    {
        $this->removeDirectory($this->fileCachePath);
        $this->ensureCacheDirectories();
    }

    private function clearDatabaseCache()
    {
        if ($this->dbCache && $this->cacheConfig['enable_db_cache']) {
            try {
                $this->dbCache->exec("DELETE FROM module_cache");
            } catch (Exception $e) {
                // Log error but don't fail
            }
        }
    }

    private function getFileCachePath($cacheKey)
    {
        $hash = md5($cacheKey);
        $dir1 = substr($hash, 0, 2);
        $dir2 = substr($hash, 2, 2);

        return $this->fileCachePath . "/data/{$dir1}/{$dir2}/{$hash}.cache";
    }

    private function isExpired($data)
    {
        return isset($data['expires_at']) && strtotime($data['expires_at']) < time();
    }

    private function maintainMemoryCacheSize()
    {
        if (count($this->memoryCache) > $this->cacheConfig['memory_cache_size']) {
            // Remove oldest entries (simple LRU approximation)
            $keys = array_keys($this->memoryCache);
            $toRemove = count($this->memoryCache) - $this->cacheConfig['memory_cache_size'];

            for ($i = 0; $i < $toRemove; $i++) {
                unset($this->memoryCache[$keys[$i]]);
            }
        }
    }

    private function compressData($data)
    {
        if (!$this->cacheConfig['cache_compression']) {
            return serialize($data);
        }

        return gzcompress(serialize($data));
    }

    private function uncompressData($data)
    {
        if (!$this->cacheConfig['cache_compression']) {
            return unserialize($data);
        }

        return unserialize(gzuncompress($data));
    }

    private function cleanupMemoryCache()
    {
        foreach ($this->memoryCache as $key => $data) {
            if ($this->isExpired($data)) {
                unset($this->memoryCache[$key]);
            }
        }
    }

    private function cleanupFileCache()
    {
        $this->cleanupDirectory($this->fileCachePath, $this->cacheConfig['cleanup_interval']);
    }

    private function cleanupDatabaseCache()
    {
        if ($this->dbCache && $this->cacheConfig['enable_db_cache']) {
            try {
                $stmt = $this->dbCache->prepare("DELETE FROM module_cache WHERE expires_at <= CURRENT_TIMESTAMP");
                $stmt->execute();
            } catch (Exception $e) {
                // Log error but don't fail
            }
        }
    }

    private function cleanupDirectory($dir, $maxAge)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile() && (time() - $file->getMTime()) > $maxAge) {
                unlink($file->getPathname());
            }
        }
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
            }
        }

        // Remove empty directories
        $dirs = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($dirs as $subdir) {
            if ($subdir->isDir()) {
                rmdir($subdir->getPathname());
            }
        }

        rmdir($dir);
    }

    private function scheduleCacheCleanup()
    {
        // In a real application, this would be handled by a cron job or scheduled task
        // For now, we'll just clean up when the object is destroyed
        register_shutdown_function([$this, 'cleanup']);
    }
}

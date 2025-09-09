<?php
/**
 * TPT Government Platform - Optimized Service Module Base Class
 *
 * Memory-optimized base class for all government service modules
 * with improved lazy loading and method decomposition
 */

namespace Modules;

use Core\Interfaces\CacheInterface;

abstract class ServiceModuleOptimized extends ServiceModule
{
    /**
     * Cache instance for module data
     */
    protected ?CacheInterface $moduleCache = null;

    /**
     * Cache TTL for module data
     */
    protected int $moduleCacheTtl = 3600;

    /**
     * Memory usage tracker
     */
    protected array $memoryStats = [
        'initialization_time' => 0,
        'peak_memory_usage' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];

    /**
     * Constructor with memory optimization
     */
    public function __construct(array $config = [])
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        parent::__construct($config);

        // Track memory usage
        $this->memoryStats['initialization_time'] = microtime(true) - $startTime;
        $this->memoryStats['peak_memory_usage'] = memory_get_usage(true) - $startMemory;
    }

    /**
     * Optimized installation with progress tracking
     */
    public function install(): bool
    {
        try {
            $this->ensureInitialized();

            // Break down installation into smaller, manageable steps
            $steps = [
                'database_setup' => [$this, 'installDatabaseStep'],
                'directories' => [$this, 'installDirectoriesStep'],
                'permissions' => [$this, 'installPermissionsStep'],
                'data' => [$this, 'installDataStep'],
                'hooks' => [$this, 'installHooksStep']
            ];

            foreach ($steps as $stepName => $stepMethod) {
                if (!$this->executeInstallStep($stepName, $stepMethod)) {
                    throw new \Exception("Installation failed at step: {$stepName}");
                }
            }

            $this->onInstall();
            return true;

        } catch (\Exception $e) {
            $this->onInstallError($e);
            return false;
        }
    }

    /**
     * Execute individual installation step
     */
    protected function executeInstallStep(string $stepName, callable $stepMethod): bool
    {
        try {
            // Clean up memory before each step
            $this->cleanupMemory();

            $result = call_user_func($stepMethod);

            // Cache step completion
            $this->setModuleCache("install_step_{$stepName}", true, $this->moduleCacheTtl);

            return $result;

        } catch (\Exception $e) {
            $this->logInstallError($stepName, $e);
            return false;
        }
    }

    /**
     * Database setup step
     */
    protected function installDatabaseStep(): bool
    {
        if ($this->getModuleCache('install_step_database_setup')) {
            return true; // Already completed
        }

        foreach ($this->tables as $tableName => $tableSchema) {
            if (!$this->tableExists($tableName)) {
                $this->createTable($tableName, $tableSchema);
            }
        }

        return true;
    }

    /**
     * Directories setup step
     */
    protected function installDirectoriesStep(): bool
    {
        if ($this->getModuleCache('install_step_directories')) {
            return true; // Already completed
        }

        $directories = [
            UPLOAD_PATH . '/' . strtolower($this->getName()),
            CACHE_PATH . '/' . strtolower($this->getName()),
            LOG_PATH . '/' . strtolower($this->getName())
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }

        return true;
    }

    /**
     * Permissions setup step
     */
    protected function installPermissionsStep(): bool
    {
        if ($this->getModuleCache('install_step_permissions')) {
            return true; // Already completed
        }

        $this->ensurePermissionsInitialized();
        $this->setDefaultPermissions();

        return true;
    }

    /**
     * Data initialization step
     */
    protected function installDataStep(): bool
    {
        if ($this->getModuleCache('install_step_data')) {
            return true; // Already completed
        }

        $this->initializeData();

        return true;
    }

    /**
     * Hooks setup step
     */
    protected function installHooksStep(): bool
    {
        if ($this->getModuleCache('install_step_hooks')) {
            return true; // Already completed
        }

        $this->registerHooks();

        return true;
    }

    /**
     * Optimized uninstallation with cleanup
     */
    public function uninstall(): bool
    {
        try {
            $this->onUninstall();

            // Break down uninstallation
            $this->uninstallDatabase();
            $this->uninstallFiles();
            $this->uninstallPermissions();

            // Clear all caches
            $this->clearModuleCache();

            return true;

        } catch (\Exception $e) {
            $this->onUninstallError($e);
            return false;
        }
    }

    /**
     * Optimized update with rollback capability
     */
    public function update(string $newVersion): bool
    {
        $backup = $this->createUpdateBackup();

        try {
            $this->backupData();
            $this->runMigrations($newVersion);
            $this->updateConfiguration($newVersion);
            $this->onUpdate($newVersion);

            // Clear old caches
            $this->clearModuleCache();

            return true;

        } catch (\Exception $e) {
            $this->onUpdateError($e);
            $this->restoreUpdateBackup($backup);
            return false;
        }
    }

    /**
     * Create backup for update rollback
     */
    protected function createUpdateBackup(): array
    {
        return [
            'config' => $this->config,
            'cache' => $this->cache,
            'memory_stats' => $this->memoryStats,
            'timestamp' => time()
        ];
    }

    /**
     * Restore backup after failed update
     */
    protected function restoreUpdateBackup(array $backup): void
    {
        $this->config = $backup['config'];
        $this->cache = $backup['cache'];
        $this->memoryStats = $backup['memory_stats'];
        $this->saveConfiguration();
    }

    /**
     * Memory-efficient lazy loading for endpoints
     */
    public function getEndpoints(): array
    {
        $cacheKey = 'endpoints_' . $this->getName();

        if ($cached = $this->getModuleCache($cacheKey)) {
            $this->memoryStats['cache_hits']++;
            return $cached;
        }

        $this->memoryStats['cache_misses']++;
        $this->ensureEndpointsInitialized();

        $endpoints = $this->endpoints;
        $this->setModuleCache($cacheKey, $endpoints, $this->moduleCacheTtl);

        return $endpoints;
    }

    /**
     * Memory-efficient lazy loading for workflows
     */
    public function getWorkflows(): array
    {
        $cacheKey = 'workflows_' . $this->getName();

        if ($cached = $this->getModuleCache($cacheKey)) {
            $this->memoryStats['cache_hits']++;
            return $cached;
        }

        $this->memoryStats['cache_misses']++;
        $this->ensureWorkflowsInitialized();

        $workflows = $this->workflows;
        $this->setModuleCache($cacheKey, $workflows, $this->moduleCacheTtl);

        return $workflows;
    }

    /**
     * Memory-efficient lazy loading for forms
     */
    public function getForms(): array
    {
        $cacheKey = 'forms_' . $this->getName();

        if ($cached = $this->getModuleCache($cacheKey)) {
            $this->memoryStats['cache_hits']++;
            return $cached;
        }

        $this->memoryStats['cache_misses']++;
        $this->ensureFormsInitialized();

        $forms = $this->forms;
        $this->setModuleCache($cacheKey, $forms, $this->moduleCacheTtl);

        return $forms;
    }

    /**
     * Memory-efficient lazy loading for reports
     */
    public function getReports(): array
    {
        $cacheKey = 'reports_' . $this->getName();

        if ($cached = $this->getModuleCache($cacheKey)) {
            $this->memoryStats['cache_hits']++;
            return $cached;
        }

        $this->memoryStats['cache_misses']++;
        $this->ensureReportsInitialized();

        $reports = $this->reports;
        $this->setModuleCache($cacheKey, $reports, $this->moduleCacheTtl);

        return $reports;
    }

    /**
     * Optimized module cache getter
     */
    protected function getModuleCache(string $key)
    {
        if ($this->moduleCache) {
            return $this->moduleCache->get('module:' . $this->getName() . ':' . $key);
        }

        return $this->cache[$key] ?? null;
    }

    /**
     * Optimized module cache setter
     */
    protected function setModuleCache(string $key, $value, int $ttl = null): void
    {
        $ttl = $ttl ?? $this->moduleCacheTtl;

        if ($this->moduleCache) {
            $this->moduleCache->set('module:' . $this->getName() . ':' . $key, $value, $ttl);
        } else {
            $this->cache[$key] = [
                'value' => $value,
                'expires' => time() + $ttl
            ];
        }
    }

    /**
     * Clear module cache
     */
    protected function clearModuleCache(string $key = null): void
    {
        if ($key === null) {
            if ($this->moduleCache) {
                $this->moduleCache->clear('module:' . $this->getName() . ':*');
            }
            $this->cache = [];
        } else {
            if ($this->moduleCache) {
                $this->moduleCache->delete('module:' . $this->getName() . ':' . $key);
            }
            unset($this->cache[$key]);
        }
    }

    /**
     * Memory cleanup utility
     */
    protected function cleanupMemory(): void
    {
        // Clear expired cache entries
        $now = time();
        foreach ($this->cache as $key => $item) {
            if (isset($item['expires']) && $item['expires'] < $now) {
                unset($this->cache[$key]);
            }
        }

        // Force garbage collection if available
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Enhanced statistics with memory tracking
     */
    public function getStatistics(): array
    {
        $stats = parent::getStatistics();

        return array_merge($stats, [
            'memory_stats' => $this->memoryStats,
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'cache_enabled' => $this->moduleCache !== null,
            'cache_ttl' => $this->moduleCacheTtl
        ]);
    }

    /**
     * Set module cache instance
     */
    public function setModuleCacheInstance(CacheInterface $cache): self
    {
        $this->moduleCache = $cache;
        return $this;
    }

    /**
     * Set module cache TTL
     */
    public function setModuleCacheTtl(int $ttl): self
    {
        $this->moduleCacheTtl = $ttl;
        return $this;
    }

    /**
     * Log installation errors
     */
    protected function logInstallError(string $stepName, \Exception $e): void
    {
        $logMessage = sprintf(
            "[%s] Module %s installation error at step %s: %s",
            date('Y-m-d H:i:s'),
            $this->getName(),
            $stepName,
            $e->getMessage()
        );

        error_log($logMessage);

        if (defined('LOGS_PATH')) {
            file_put_contents(
                LOGS_PATH . '/modules.log',
                $logMessage . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    /**
     * Optimized export with memory management
     */
    public function exportData(string $format = 'json'): string
    {
        // Clean up memory before export
        $this->cleanupMemory();

        $exportData = [
            'metadata' => $this->metadata,
            'config' => $this->config,
            'statistics' => $this->getStatistics(),
            'exported_at' => date('c'),
            'memory_usage_at_export' => memory_get_usage(true)
        ];

        switch ($format) {
            case 'json':
                return json_encode($exportData, JSON_PRETTY_PRINT);
            case 'xml':
                return $this->exportToXML($exportData);
            default:
                throw new \Exception("Unsupported export format: {$format}");
        }
    }

    /**
     * Batch processing for large datasets
     */
    protected function processBatch(array $items, callable $processor, int $batchSize = 100): array
    {
        $results = [];
        $batches = array_chunk($items, $batchSize);

        foreach ($batches as $batch) {
            $batchResults = array_map($processor, $batch);
            $results = array_merge($results, $batchResults);

            // Clean up memory between batches
            $this->cleanupMemory();
        }

        return $results;
    }

    /**
     * Memory-efficient validation
     */
    public function validateConfiguration(): array
    {
        // Clean up memory before validation
        $this->cleanupMemory();

        return parent::validateConfiguration();
    }

    /**
     * Force full initialization with memory tracking
     */
    public function initializeFully(): void
    {
        $startMemory = memory_get_usage(true);
        $startTime = microtime(true);

        parent::initializeFully();

        $this->memoryStats['full_initialization_time'] = microtime(true) - $startTime;
        $this->memoryStats['full_initialization_memory'] = memory_get_usage(true) - $startMemory;
    }
}

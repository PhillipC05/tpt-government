<?php
/**
 * TPT Government Platform - Performance Optimizer
 *
 * Comprehensive performance optimization and monitoring system
 * for high-traffic government applications
 */

namespace Core;

class PerformanceOptimizer
{
    /**
     * Cache manager instance
     */
    private ?CacheManager $cache = null;

    /**
     * Database optimizer instance
     */
    private ?DatabaseOptimizer $dbOptimizer = null;

    /**
     * CDN manager instance
     */
    private ?CDNManager $cdn = null;

    /**
     * Performance metrics
     */
    private array $metrics = [];

    /**
     * Performance thresholds
     */
    private array $thresholds = [
        'response_time' => 200, // ms
        'memory_usage' => 128,  // MB
        'cpu_usage' => 70,      // percentage
        'cache_hit_ratio' => 85 // percentage
    ];

    /**
     * Performance monitoring enabled
     */
    private bool $monitoringEnabled = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initializeComponents();
        $this->loadConfiguration();
    }

    /**
     * Initialize performance components
     */
    private function initializeComponents(): void
    {
        $this->cache = new CacheManager();
        $this->dbOptimizer = new DatabaseOptimizer();
        $this->cdn = new CDNManager();
    }

    /**
     * Load performance configuration
     */
    private function loadConfiguration(): void
    {
        $configFile = CONFIG_PATH . '/performance.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->thresholds = array_merge($this->thresholds, $config['thresholds'] ?? []);
            $this->monitoringEnabled = $config['monitoring_enabled'] ?? true;
        }
    }

    /**
     * Optimize application performance
     */
    public function optimize(): array
    {
        $results = [
            'cache_optimization' => $this->optimizeCache(),
            'database_optimization' => $this->optimizeDatabase(),
            'cdn_optimization' => $this->optimizeCDN(),
            'code_optimization' => $this->optimizeCode(),
            'resource_optimization' => $this->optimizeResources()
        ];

        $this->logOptimizationResults($results);
        return $results;
    }

    /**
     * Optimize caching system
     */
    private function optimizeCache(): array
    {
        $results = [
            'status' => 'success',
            'optimizations' => [],
            'metrics' => []
        ];

        try {
            // Clear expired cache entries
            $clearedEntries = $this->cache->clearExpired();
            $results['optimizations'][] = "Cleared $clearedEntries expired cache entries";

            // Optimize cache storage
            $this->cache->optimizeStorage();
            $results['optimizations'][] = "Optimized cache storage structure";

            // Implement cache compression
            if ($this->cache->enableCompression()) {
                $results['optimizations'][] = "Enabled cache compression";
            }

            // Get cache metrics
            $results['metrics'] = $this->cache->getMetrics();

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Optimize database performance
     */
    private function optimizeDatabase(): array
    {
        $results = [
            'status' => 'success',
            'optimizations' => [],
            'metrics' => []
        ];

        try {
            // Analyze slow queries
            $slowQueries = $this->dbOptimizer->analyzeSlowQueries();
            if (!empty($slowQueries)) {
                $results['optimizations'][] = "Identified " . count($slowQueries) . " slow queries";
            }

            // Optimize table indexes
            $optimizedTables = $this->dbOptimizer->optimizeIndexes();
            $results['optimizations'][] = "Optimized indexes for " . count($optimizedTables) . " tables";

            // Implement query caching
            $this->dbOptimizer->enableQueryCache();
            $results['optimizations'][] = "Enabled database query caching";

            // Optimize connection pooling
            $this->dbOptimizer->optimizeConnectionPool();
            $results['optimizations'][] = "Optimized database connection pooling";

            // Get database metrics
            $results['metrics'] = $this->dbOptimizer->getMetrics();

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Optimize CDN performance
     */
    private function optimizeCDN(): array
    {
        $results = [
            'status' => 'success',
            'optimizations' => [],
            'metrics' => []
        ];

        try {
            // Optimize static asset delivery
            $this->cdn->optimizeAssetDelivery();
            $results['optimizations'][] = "Optimized static asset delivery";

            // Implement edge caching
            $this->cdn->enableEdgeCaching();
            $results['optimizations'][] = "Enabled edge caching for global distribution";

            // Configure cache invalidation
            $this->cdn->configureCacheInvalidation();
            $results['optimizations'][] = "Configured intelligent cache invalidation";

            // Get CDN metrics
            $results['metrics'] = $this->cdn->getMetrics();

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Optimize application code
     */
    private function optimizeCode(): array
    {
        $results = [
            'status' => 'success',
            'optimizations' => [],
            'metrics' => []
        ];

        try {
            // Enable OPcache
            if (function_exists('opcache_get_status')) {
                ini_set('opcache.enable', '1');
                ini_set('opcache.memory_consumption', '256');
                ini_set('opcache.max_accelerated_files', '7963');
                $results['optimizations'][] = "Enabled OPcache with optimized settings";
            }

            // Optimize autoloading
            $this->optimizeAutoloading();
            $results['optimizations'][] = "Optimized class autoloading";

            // Implement lazy loading
            $this->enableLazyLoading();
            $results['optimizations'][] = "Implemented lazy loading for heavy components";

            // Optimize memory usage
            $this->optimizeMemoryUsage();
            $results['optimizations'][] = "Optimized memory usage patterns";

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Optimize resources (CSS, JS, images)
     */
    private function optimizeResources(): array
    {
        $results = [
            'status' => 'success',
            'optimizations' => [],
            'metrics' => []
        ];

        try {
            // Minify CSS and JavaScript
            $this->minifyAssets();
            $results['optimizations'][] = "Minified CSS and JavaScript files";

            // Optimize images
            $imageOptimizer = new ImageOptimizer();
            $optimizedImages = $imageOptimizer->optimizeDirectory(PUBLIC_PATH . '/images');
            $results['optimizations'][] = "Optimized $optimizedImages images";

            // Enable GZIP compression
            $this->enableGzipCompression();
            $results['optimizations'][] = "Enabled GZIP compression for responses";

            // Implement resource hints
            $this->implementResourceHints();
            $results['optimizations'][] = "Implemented resource hints for faster loading";

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Optimize autoloading performance
     */
    private function optimizeAutoloading(): void
    {
        // Generate optimized autoload files
        if (file_exists(BASE_PATH . '/vendor/composer/autoload_classmap.php')) {
            // Class map is already optimized
            return;
        }

        // Generate class map for better performance
        $autoloadFile = BASE_PATH . '/vendor/composer/autoload_files.php';
        if (file_exists($autoloadFile)) {
            $files = require $autoloadFile;
            // Preload critical files
            foreach (array_slice($files, 0, 10) as $file) {
                if (file_exists($file)) {
                    include_once $file;
                }
            }
        }
    }

    /**
     * Enable lazy loading for heavy components
     */
    private function enableLazyLoading(): void
    {
        // Implement lazy loading for non-critical components
        spl_autoload_register(function ($class) {
            // Lazy load heavy components
            $lazyLoadClasses = [
                'Core\\AdvancedAnalytics' => 'src/php/core/AdvancedAnalytics.php',
                'Core\\RealTimeCollaboration' => 'src/php/core/RealTimeCollaboration.php',
                'Core\\BlockchainManager' => 'src/php/core/BlockchainManager.php'
            ];

            if (isset($lazyLoadClasses[$class])) {
                $file = BASE_PATH . '/' . $lazyLoadClasses[$class];
                if (file_exists($file)) {
                    include $file;
                }
            }
        });
    }

    /**
     * Optimize memory usage
     */
    private function optimizeMemoryUsage(): void
    {
        // Increase memory limit if needed
        $currentLimit = ini_get('memory_limit');
        $currentBytes = $this->convertToBytes($currentLimit);

        if ($currentBytes < 256 * 1024 * 1024) { // Less than 256MB
            ini_set('memory_limit', '256M');
        }

        // Enable garbage collection
        if (function_exists('gc_enable')) {
            gc_enable();
        }

        // Optimize session handling
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.gc_maxlifetime', '1440'); // 24 minutes
    }

    /**
     * Minify CSS and JavaScript assets
     */
    private function minifyAssets(): void
    {
        $publicDir = PUBLIC_PATH;
        $cssFiles = glob($publicDir . '/css/*.css');
        $jsFiles = glob($publicDir . '/js/*.js');

        foreach (array_merge($cssFiles, $jsFiles) as $file) {
            if (!str_contains($file, '.min.')) {
                $this->minifyFile($file);
            }
        }
    }

    /**
     * Minify a single file
     */
    private function minifyFile(string $file): void
    {
        $content = file_get_contents($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === 'css') {
            // Simple CSS minification
            $content = preg_replace('/\s+/', ' ', $content);
            $content = preg_replace('/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', '', $content);
        } elseif ($extension === 'js') {
            // Simple JavaScript minification
            $content = preg_replace('/\s+/', ' ', $content);
            $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        }

        $minifiedFile = str_replace('.' . $extension, '.min.' . $extension, $file);
        file_put_contents($minifiedFile, $content);
    }

    /**
     * Enable GZIP compression
     */
    private function enableGzipCompression(): void
    {
        // Enable output compression
        if (!ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'On');
        }

        // Set compression level
        ini_set('zlib.output_compression_level', '6');
    }

    /**
     * Implement resource hints
     */
    private function implementResourceHints(): void
    {
        // Add preload hints for critical resources
        $criticalResources = [
            '/css/main.min.css',
            '/js/app.min.js',
            '/images/logo.png'
        ];

        foreach ($criticalResources as $resource) {
            if (file_exists(PUBLIC_PATH . $resource)) {
                header("Link: <$resource>; rel=preload", false);
            }
        }
    }

    /**
     * Convert size string to bytes
     */
    private function convertToBytes(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Monitor performance metrics
     */
    public function monitorPerformance(): array
    {
        if (!$this->monitoringEnabled) {
            return ['status' => 'disabled'];
        }

        $metrics = [
            'response_time' => $this->measureResponseTime(),
            'memory_usage' => $this->measureMemoryUsage(),
            'cpu_usage' => $this->measureCpuUsage(),
            'cache_performance' => $this->measureCachePerformance(),
            'database_performance' => $this->measureDatabasePerformance(),
            'timestamp' => microtime(true)
        ];

        $this->metrics[] = $metrics;
        $this->checkThresholds($metrics);

        return $metrics;
    }

    /**
     * Measure response time
     */
    private function measureResponseTime(): float
    {
        return (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000; // Convert to milliseconds
    }

    /**
     * Measure memory usage
     */
    private function measureMemoryUsage(): float
    {
        return memory_get_peak_usage(true) / 1024 / 1024; // Convert to MB
    }

    /**
     * Measure CPU usage
     */
    private function measureCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] * 100; // Convert to percentage
        }

        return 0.0;
    }

    /**
     * Measure cache performance
     */
    private function measureCachePerformance(): array
    {
        if (!$this->cache) {
            return ['hit_ratio' => 0, 'miss_ratio' => 0];
        }

        $metrics = $this->cache->getMetrics();
        $total = ($metrics['hits'] ?? 0) + ($metrics['misses'] ?? 0);

        return [
            'hit_ratio' => $total > 0 ? ($metrics['hits'] ?? 0) / $total * 100 : 0,
            'miss_ratio' => $total > 0 ? ($metrics['misses'] ?? 0) / $total * 100 : 0,
            'total_requests' => $total
        ];
    }

    /**
     * Measure database performance
     */
    private function measureDatabasePerformance(): array
    {
        if (!$this->dbOptimizer) {
            return ['query_time' => 0, 'connection_count' => 0];
        }

        $metrics = $this->dbOptimizer->getMetrics();

        return [
            'query_time' => $metrics['avg_query_time'] ?? 0,
            'connection_count' => $metrics['active_connections'] ?? 0,
            'slow_queries' => $metrics['slow_queries'] ?? 0
        ];
    }

    /**
     * Check performance thresholds
     */
    private function checkThresholds(array $metrics): void
    {
        $alerts = [];

        if ($metrics['response_time'] > $this->thresholds['response_time']) {
            $alerts[] = "Response time ({$metrics['response_time']}ms) exceeds threshold ({$this->thresholds['response_time']}ms)";
        }

        if ($metrics['memory_usage'] > $this->thresholds['memory_usage']) {
            $alerts[] = "Memory usage ({$metrics['memory_usage']}MB) exceeds threshold ({$this->thresholds['memory_usage']}MB)";
        }

        if ($metrics['cpu_usage'] > $this->thresholds['cpu_usage']) {
            $alerts[] = "CPU usage ({$metrics['cpu_usage']}%) exceeds threshold ({$this->thresholds['cpu_usage']}%)";
        }

        if (isset($metrics['cache_performance']['hit_ratio']) &&
            $metrics['cache_performance']['hit_ratio'] < $this->thresholds['cache_hit_ratio']) {
            $alerts[] = "Cache hit ratio ({$metrics['cache_performance']['hit_ratio']}%) below threshold ({$this->thresholds['cache_hit_ratio']}%)";
        }

        if (!empty($alerts)) {
            $this->logPerformanceAlerts($alerts);
        }
    }

    /**
     * Log performance alerts
     */
    private function logPerformanceAlerts(array $alerts): void
    {
        $logFile = LOGS_PATH . '/performance_alerts.log';
        $timestamp = date('Y-m-d H:i:s');

        foreach ($alerts as $alert) {
            $logEntry = "[$timestamp] PERFORMANCE ALERT: $alert" . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport(): array
    {
        $report = [
            'summary' => $this->calculatePerformanceSummary(),
            'metrics_history' => array_slice($this->metrics, -100), // Last 100 measurements
            'recommendations' => $this->generateRecommendations(),
            'thresholds' => $this->thresholds
        ];

        return $report;
    }

    /**
     * Calculate performance summary
     */
    private function calculatePerformanceSummary(): array
    {
        if (empty($this->metrics)) {
            return ['status' => 'no_data'];
        }

        $summary = [
            'total_measurements' => count($this->metrics),
            'average_response_time' => 0,
            'peak_memory_usage' => 0,
            'average_cpu_usage' => 0,
            'cache_hit_ratio' => 0
        ];

        $responseTimes = [];
        $memoryUsages = [];
        $cpuUsages = [];
        $cacheHitRatios = [];

        foreach ($this->metrics as $metric) {
            $responseTimes[] = $metric['response_time'];
            $memoryUsages[] = $metric['memory_usage'];
            $cpuUsages[] = $metric['cpu_usage'];

            if (isset($metric['cache_performance']['hit_ratio'])) {
                $cacheHitRatios[] = $metric['cache_performance']['hit_ratio'];
            }
        }

        $summary['average_response_time'] = array_sum($responseTimes) / count($responseTimes);
        $summary['peak_memory_usage'] = max($memoryUsages);
        $summary['average_cpu_usage'] = array_sum($cpuUsages) / count($cpuUsages);
        $summary['cache_hit_ratio'] = !empty($cacheHitRatios) ? array_sum($cacheHitRatios) / count($cacheHitRatios) : 0;

        return $summary;
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        $summary = $this->calculatePerformanceSummary();

        if ($summary['average_response_time'] > $this->thresholds['response_time']) {
            $recommendations[] = "Consider implementing caching for frequently accessed data";
            $recommendations[] = "Optimize database queries and add appropriate indexes";
            $recommendations[] = "Implement CDN for static assets";
        }

        if ($summary['peak_memory_usage'] > $this->thresholds['memory_usage']) {
            $recommendations[] = "Implement memory-efficient data structures";
            $recommendations[] = "Enable OPcache for PHP optimization";
            $recommendations[] = "Implement lazy loading for heavy components";
        }

        if ($summary['cache_hit_ratio'] < $this->thresholds['cache_hit_ratio']) {
            $recommendations[] = "Increase cache size or implement better cache strategies";
            $recommendations[] = "Review cache invalidation policies";
            $recommendations[] = "Implement cache warming for frequently accessed data";
        }

        return $recommendations;
    }

    /**
     * Log optimization results
     */
    private function logOptimizationResults(array $results): void
    {
        $logFile = LOGS_PATH . '/performance_optimization.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] Optimization Results: " . json_encode($results) . PHP_EOL;

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Get cache manager instance
     */
    public function getCache(): ?CacheManager
    {
        return $this->cache;
    }

    /**
     * Get database optimizer instance
     */
    public function getDatabaseOptimizer(): ?DatabaseOptimizer
    {
        return $this->dbOptimizer;
    }

    /**
     * Get CDN manager instance
     */
    public function getCDN(): ?CDNManager
    {
        return $this->cdn;
    }

    /**
     * Set performance thresholds
     */
    public function setThresholds(array $thresholds): void
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);
    }

    /**
     * Enable or disable monitoring
     */
    public function setMonitoring(bool $enabled): void
    {
        $this->monitoringEnabled = $enabled;
    }
}

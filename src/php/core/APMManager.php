<?php
/**
 * TPT Government Platform - Application Performance Monitoring (APM) Manager
 *
 * Comprehensive performance monitoring system with real-time metrics,
 * database query tracking, memory monitoring, and performance analytics
 */

class APMManager
{
    private $logger;
    private $cache;
    private $database;
    private $metrics = [];
    private $thresholds = [];
    private $alerts = [];
    private $performanceData = [];
    private $isEnabled = true;

    /**
     * Performance metric types
     */
    const METRIC_RESPONSE_TIME = 'response_time';
    const METRIC_MEMORY_USAGE = 'memory_usage';
    const METRIC_CPU_USAGE = 'cpu_usage';
    const METRIC_DATABASE_QUERIES = 'database_queries';
    const METRIC_CACHE_HITS = 'cache_hits';
    const METRIC_ERROR_RATE = 'error_rate';
    const METRIC_ACTIVE_CONNECTIONS = 'active_connections';

    /**
     * Alert severity levels
     */
    const ALERT_LOW = 'low';
    const ALERT_MEDIUM = 'medium';
    const ALERT_HIGH = 'high';
    const ALERT_CRITICAL = 'critical';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->cache = CacheManager::getInstance();
        $this->database = Database::getInstance();

        $this->isEnabled = $config['enabled'] ?? true;

        // Set default performance thresholds
        $this->thresholds = array_merge([
            self::METRIC_RESPONSE_TIME => [
                'warning' => 1000, // 1 second
                'critical' => 5000 // 5 seconds
            ],
            self::METRIC_MEMORY_USAGE => [
                'warning' => 128 * 1024 * 1024, // 128MB
                'critical' => 256 * 1024 * 1024 // 256MB
            ],
            self::METRIC_DATABASE_QUERIES => [
                'warning' => 50, // queries per request
                'critical' => 100
            ],
            self::METRIC_ERROR_RATE => [
                'warning' => 0.05, // 5%
                'critical' => 0.10 // 10%
            ]
        ], $config['thresholds'] ?? []);

        // Initialize metrics storage
        $this->initializeMetricsStorage();

        // Set up periodic cleanup
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, [$this, 'cleanupOldMetrics']);
            pcntl_alarm(3600); // Clean up every hour
        }
    }

    /**
     * Start performance monitoring for a request
     */
    public function startRequest($requestId = null)
    {
        if (!$this->isEnabled) {
            return null;
        }

        $requestId = $requestId ?: $this->generateRequestId();

        $this->metrics[$requestId] = [
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true),
            'database_queries' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'errors' => 0,
            'warnings' => 0
        ];

        return $requestId;
    }

    /**
     * End performance monitoring for a request
     */
    public function endRequest($requestId, $responseCode = 200)
    {
        if (!$this->isEnabled || !isset($this->metrics[$requestId])) {
            return;
        }

        $metrics = $this->metrics[$requestId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Calculate performance metrics
        $responseTime = ($endTime - $metrics['start_time']) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $metrics['memory_start'];
        $peakMemoryUsed = $peakMemory - $metrics['peak_memory_start'];

        $performanceData = [
            'request_id' => $requestId,
            'timestamp' => $endTime,
            'response_time' => round($responseTime, 2),
            'memory_used' => $memoryUsed,
            'peak_memory' => $peakMemory,
            'database_queries' => $metrics['database_queries'],
            'cache_hits' => $metrics['cache_hits'],
            'cache_misses' => $metrics['cache_misses'],
            'cache_hit_rate' => $this->calculateCacheHitRate($metrics),
            'errors' => $metrics['errors'],
            'warnings' => $metrics['warnings'],
            'response_code' => $responseCode,
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        // Store performance data
        $this->storePerformanceData($performanceData);

        // Check for performance issues
        $this->checkPerformanceThresholds($performanceData);

        // Log performance data
        $this->logPerformanceData($performanceData);

        // Clean up
        unset($this->metrics[$requestId]);

        return $performanceData;
    }

    /**
     * Record database query execution
     */
    public function recordDatabaseQuery($requestId, $query, $executionTime, $success = true)
    {
        if (!$this->isEnabled || !isset($this->metrics[$requestId])) {
            return;
        }

        $this->metrics[$requestId]['database_queries']++;

        // Store detailed query information
        $queryData = [
            'request_id' => $requestId,
            'timestamp' => microtime(true),
            'query' => $this->sanitizeQuery($query),
            'execution_time' => $executionTime,
            'success' => $success
        ];

        $this->storeQueryData($queryData);

        // Check for slow queries
        if ($executionTime > 1000) { // More than 1 second
            $this->logger->warning('Slow database query detected', [
                'request_id' => $requestId,
                'execution_time' => $executionTime,
                'query' => substr($query, 0, 200) . '...'
            ]);
        }
    }

    /**
     * Record cache operation
     */
    public function recordCacheOperation($requestId, $operation, $hit = true)
    {
        if (!$this->isEnabled || !isset($this->metrics[$requestId])) {
            return;
        }

        if ($hit) {
            $this->metrics[$requestId]['cache_hits']++;
        } else {
            $this->metrics[$requestId]['cache_misses']++;
        }
    }

    /**
     * Record error or warning
     */
    public function recordError($requestId, $type = 'error')
    {
        if (!$this->isEnabled || !isset($this->metrics[$requestId])) {
            return;
        }

        if ($type === 'error') {
            $this->metrics[$requestId]['errors']++;
        } else {
            $this->metrics[$requestId]['warnings']++;
        }
    }

    /**
     * Get current performance metrics
     */
    public function getCurrentMetrics($timeframe = 3600)
    {
        $cutoff = time() - $timeframe;

        try {
            $result = $this->database->query(
                "SELECT
                    AVG(response_time) as avg_response_time,
                    MAX(response_time) as max_response_time,
                    MIN(response_time) as min_response_time,
                    AVG(memory_used) as avg_memory_used,
                    MAX(peak_memory) as max_peak_memory,
                    SUM(database_queries) as total_queries,
                    AVG(cache_hit_rate) as avg_cache_hit_rate,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as error_requests
                FROM performance_metrics
                WHERE timestamp >= ?",
                [$cutoff]
            );

            if ($result && $row = $result->fetch(PDO::FETCH_ASSOC)) {
                $errorRate = $row['total_requests'] > 0 ?
                    ($row['error_requests'] / $row['total_requests']) * 100 : 0;

                return [
                    'timeframe' => $timeframe,
                    'total_requests' => (int)$row['total_requests'],
                    'avg_response_time' => round($row['avg_response_time'], 2),
                    'max_response_time' => round($row['max_response_time'], 2),
                    'min_response_time' => round($row['min_response_time'], 2),
                    'avg_memory_used' => $this->formatBytes($row['avg_memory_used']),
                    'max_peak_memory' => $this->formatBytes($row['max_peak_memory']),
                    'total_database_queries' => (int)$row['total_queries'],
                    'avg_cache_hit_rate' => round($row['avg_cache_hit_rate'], 2),
                    'error_rate' => round($errorRate, 2),
                    'timestamp' => date('c')
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to get performance metrics: ' . $e->getMessage());
        }

        return [
            'timeframe' => $timeframe,
            'total_requests' => 0,
            'avg_response_time' => 0,
            'max_response_time' => 0,
            'min_response_time' => 0,
            'avg_memory_used' => '0 B',
            'max_peak_memory' => '0 B',
            'total_database_queries' => 0,
            'avg_cache_hit_rate' => 0,
            'error_rate' => 0,
            'timestamp' => date('c')
        ];
    }

    /**
     * Get performance trends
     */
    public function getPerformanceTrends($hours = 24)
    {
        $trends = [];

        for ($i = $hours; $i >= 0; $i--) {
            $timestamp = time() - ($i * 3600);
            $metrics = $this->getCurrentMetrics(3600); // Last hour

            $trends[] = [
                'timestamp' => date('c', $timestamp),
                'hour' => date('H', $timestamp),
                'metrics' => $metrics
            ];
        }

        return $trends;
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries($limit = 10, $minTime = 1000)
    {
        try {
            $result = $this->database->query(
                "SELECT * FROM database_queries
                WHERE execution_time > ? AND timestamp >= ?
                ORDER BY execution_time DESC
                LIMIT ?",
                [$minTime, time() - 86400, $limit] // Last 24 hours
            );

            $slowQueries = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $slowQueries[] = [
                    'request_id' => $row['request_id'],
                    'timestamp' => date('c', $row['timestamp']),
                    'query' => $row['query'],
                    'execution_time' => round($row['execution_time'], 2),
                    'success' => (bool)$row['success']
                ];
            }

            return $slowQueries;
        } catch (Exception $e) {
            $this->logger->error('Failed to get slow queries: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts($limit = 50)
    {
        return array_slice(array_reverse($this->alerts), 0, $limit);
    }

    /**
     * Export performance data
     */
    public function exportPerformanceData($format = 'json', $startTime = null, $endTime = null)
    {
        $startTime = $startTime ?: (time() - 86400); // Last 24 hours
        $endTime = $endTime ?: time();

        try {
            $result = $this->database->query(
                "SELECT * FROM performance_metrics
                WHERE timestamp BETWEEN ? AND ?
                ORDER BY timestamp ASC",
                [$startTime, $endTime]
            );

            $data = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $data[] = $row;
            }

            switch ($format) {
                case 'json':
                    return json_encode($data, JSON_PRETTY_PRINT);
                case 'csv':
                    return $this->convertToCSV($data);
                default:
                    return $data;
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to export performance data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check performance thresholds and create alerts
     */
    private function checkPerformanceThresholds($performanceData)
    {
        $alerts = [];

        // Check response time
        if ($performanceData['response_time'] > $this->thresholds[self::METRIC_RESPONSE_TIME]['critical']) {
            $alerts[] = $this->createAlert(
                self::ALERT_CRITICAL,
                'Critical response time detected',
                $performanceData
            );
        } elseif ($performanceData['response_time'] > $this->thresholds[self::METRIC_RESPONSE_TIME]['warning']) {
            $alerts[] = $this->createAlert(
                self::ALERT_HIGH,
                'High response time detected',
                $performanceData
            );
        }

        // Check memory usage
        if ($performanceData['peak_memory'] > $this->thresholds[self::METRIC_MEMORY_USAGE]['critical']) {
            $alerts[] = $this->createAlert(
                self::ALERT_CRITICAL,
                'Critical memory usage detected',
                $performanceData
            );
        } elseif ($performanceData['peak_memory'] > $this->thresholds[self::METRIC_MEMORY_USAGE]['warning']) {
            $alerts[] = $this->createAlert(
                self::ALERT_HIGH,
                'High memory usage detected',
                $performanceData
            );
        }

        // Check database queries
        if ($performanceData['database_queries'] > $this->thresholds[self::METRIC_DATABASE_QUERIES]['critical']) {
            $alerts[] = $this->createAlert(
                self::ALERT_CRITICAL,
                'High number of database queries detected',
                $performanceData
            );
        } elseif ($performanceData['database_queries'] > $this->thresholds[self::METRIC_DATABASE_QUERIES]['warning']) {
            $alerts[] = $this->createAlert(
                self::ALERT_MEDIUM,
                'Elevated number of database queries detected',
                $performanceData
            );
        }

        // Store alerts
        foreach ($alerts as $alert) {
            $this->alerts[] = $alert;
            $this->storeAlert($alert);
        }
    }

    /**
     * Create performance alert
     */
    private function createAlert($severity, $message, $performanceData)
    {
        return [
            'id' => $this->generateAlertId(),
            'timestamp' => time(),
            'severity' => $severity,
            'message' => $message,
            'request_id' => $performanceData['request_id'],
            'metrics' => $performanceData,
            'resolved' => false
        ];
    }

    /**
     * Store performance data in database
     */
    private function storePerformanceData($data)
    {
        try {
            $this->database->query(
                "INSERT INTO performance_metrics (
                    request_id, timestamp, response_time, memory_used, peak_memory,
                    database_queries, cache_hits, cache_misses, cache_hit_rate,
                    errors, warnings, response_code, uri, method, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['request_id'],
                    $data['timestamp'],
                    $data['response_time'],
                    $data['memory_used'],
                    $data['peak_memory'],
                    $data['database_queries'],
                    $data['cache_hits'],
                    $data['cache_misses'],
                    $data['cache_hit_rate'],
                    $data['errors'],
                    $data['warnings'],
                    $data['response_code'],
                    $data['uri'],
                    $data['method'],
                    $data['user_agent']
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to store performance data: ' . $e->getMessage());
        }
    }

    /**
     * Store query data in database
     */
    private function storeQueryData($data)
    {
        try {
            $this->database->query(
                "INSERT INTO database_queries (
                    request_id, timestamp, query, execution_time, success
                ) VALUES (?, ?, ?, ?, ?)",
                [
                    $data['request_id'],
                    $data['timestamp'],
                    $data['query'],
                    $data['execution_time'],
                    $data['success'] ? 1 : 0
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to store query data: ' . $e->getMessage());
        }
    }

    /**
     * Store alert in database
     */
    private function storeAlert($alert)
    {
        try {
            $this->database->query(
                "INSERT INTO performance_alerts (
                    alert_id, timestamp, severity, message, request_id, metrics, resolved
                ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $alert['id'],
                    $alert['timestamp'],
                    $alert['severity'],
                    $alert['message'],
                    $alert['request_id'],
                    json_encode($alert['metrics']),
                    $alert['resolved'] ? 1 : 0
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to store alert: ' . $e->getMessage());
        }
    }

    /**
     * Log performance data
     */
    private function logPerformanceData($data)
    {
        $this->logger->info('Performance metrics recorded', [
            'request_id' => $data['request_id'],
            'response_time' => $data['response_time'] . 'ms',
            'memory_used' => $this->formatBytes($data['memory_used']),
            'database_queries' => $data['database_queries'],
            'cache_hit_rate' => $data['cache_hit_rate'] . '%'
        ]);
    }

    /**
     * Initialize metrics storage tables
     */
    private function initializeMetricsStorage()
    {
        $tables = [
            "CREATE TABLE IF NOT EXISTS performance_metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id VARCHAR(255),
                timestamp INTEGER,
                response_time REAL,
                memory_used INTEGER,
                peak_memory INTEGER,
                database_queries INTEGER,
                cache_hits INTEGER,
                cache_misses INTEGER,
                cache_hit_rate REAL,
                errors INTEGER,
                warnings INTEGER,
                response_code INTEGER,
                uri TEXT,
                method VARCHAR(10),
                user_agent TEXT
            )",

            "CREATE TABLE IF NOT EXISTS database_queries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id VARCHAR(255),
                timestamp REAL,
                query TEXT,
                execution_time REAL,
                success BOOLEAN
            )",

            "CREATE TABLE IF NOT EXISTS performance_alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                alert_id VARCHAR(255),
                timestamp INTEGER,
                severity VARCHAR(20),
                message TEXT,
                request_id VARCHAR(255),
                metrics TEXT,
                resolved BOOLEAN
            )",

            "CREATE INDEX IF NOT EXISTS idx_performance_timestamp ON performance_metrics(timestamp)",
            "CREATE INDEX IF NOT EXISTS idx_queries_timestamp ON database_queries(timestamp)",
            "CREATE INDEX IF NOT EXISTS idx_alerts_timestamp ON performance_alerts(timestamp)"
        ];

        foreach ($tables as $sql) {
            try {
                $this->database->query($sql);
            } catch (Exception $e) {
                $this->logger->error('Failed to create performance table: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clean up old metrics data
     */
    public function cleanupOldMetrics()
    {
        $retentionDays = 30; // Keep data for 30 days
        $cutoff = time() - ($retentionDays * 86400);

        try {
            $this->database->query("DELETE FROM performance_metrics WHERE timestamp < ?", [$cutoff]);
            $this->database->query("DELETE FROM database_queries WHERE timestamp < ?", [$cutoff]);
            $this->database->query("DELETE FROM performance_alerts WHERE timestamp < ?", [$cutoff]);

            $this->logger->info('Old performance metrics cleaned up', [
                'retention_days' => $retentionDays,
                'cutoff_timestamp' => $cutoff
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup old metrics: ' . $e->getMessage());
        }

        // Reset alarm for next cleanup
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(3600);
        }
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateCacheHitRate($metrics)
    {
        $total = $metrics['cache_hits'] + $metrics['cache_misses'];
        return $total > 0 ? round(($metrics['cache_hits'] / $total) * 100, 2) : 0;
    }

    /**
     * Sanitize SQL query for logging
     */
    private function sanitizeQuery($query)
    {
        // Remove sensitive data from query for logging
        $query = preg_replace('/(password|token|key|secret)\s*=\s*[\'"][^\'"]*[\'"]/i', '$1=***', $query);
        return substr($query, 0, 1000); // Limit query length
    }

    /**
     * Convert data to CSV format
     */
    private function convertToCSV($data)
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId()
    {
        return uniqid('req_', true);
    }

    /**
     * Generate unique alert ID
     */
    private function generateAlertId()
    {
        return uniqid('alert_', true);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Enable or disable APM
     */
    public function setEnabled($enabled)
    {
        $this->isEnabled = $enabled;
    }

    /**
     * Check if APM is enabled
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Get system resource usage
     */
    public function getSystemResources()
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average' => sys_getloadavg(),
            'timestamp' => date('c')
        ];
    }

    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage()
    {
        // This is a simplified implementation
        // In production, you might use system calls or monitoring tools
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2); // Convert to percentage
        }

        return 0;
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        return [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => $this->formatBytes($this->getMemoryLimit())
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $used = $total - $free;

        return [
            'free' => $this->formatBytes($free),
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
            'usage_percent' => round(($used / $total) * 100, 2)
        ];
    }

    /**
     * Get memory limit
     */
    private function getMemoryLimit()
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024 * 1024;
            case 'k':
                return $value * 1024 * 1024;
            default:
                return (int)$limit;
        }
    }
}

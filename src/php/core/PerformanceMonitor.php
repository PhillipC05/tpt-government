<?php
/**
 * TPT Government Platform - Performance Monitor
 *
 * Comprehensive performance monitoring and benchmarking system
 * with real-time metrics, alerting, and optimization recommendations
 */

namespace Core;

use PDO;
use PDOException;
use Exception;

class PerformanceMonitor
{
    /**
     * Database connection
     */
    private PDO $pdo;

    /**
     * Monitoring configuration
     */
    private array $config;

    /**
     * Performance metrics storage
     */
    private array $metrics = [];

    /**
     * Active monitoring sessions
     */
    private array $activeSessions = [];

    /**
     * Performance baselines
     */
    private array $baselines = [];

    /**
     * Alert thresholds
     */
    private array $alertThresholds = [];

    /**
     * Performance reports
     */
    private array $reports = [];

    /**
     * Constructor
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'enable_real_time_monitoring' => true,
            'enable_performance_alerts' => true,
            'enable_automatic_optimization' => false,
            'monitoring_interval' => 60, // seconds
            'alert_cooldown' => 300, // 5 minutes
            'baseline_calculation_period' => 7, // days
            'performance_retention_days' => 30,
            'enable_detailed_tracing' => true,
            'trace_sampling_rate' => 0.1, // 10%
            'enable_memory_profiling' => true,
            'enable_database_monitoring' => true,
            'enable_cache_monitoring' => true,
            'enable_api_monitoring' => true
        ], $config);

        $this->initializeMonitoring();
        $this->loadBaselines();
        $this->setupAlertThresholds();
    }

    /**
     * Initialize monitoring system
     */
    private function initializeMonitoring(): void
    {
        if ($this->config['enable_real_time_monitoring']) {
            $this->createMonitoringTables();
        }

        if ($this->config['enable_performance_alerts']) {
            $this->initializeAlertSystem();
        }
    }

    /**
     * Start performance monitoring session
     */
    public function startMonitoring(string $sessionId, array $context = []): void
    {
        $this->activeSessions[$sessionId] = [
            'start_time' => microtime(true),
            'context' => $context,
            'metrics' => [],
            'memory_start' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'traces' => []
        ];

        if ($this->config['enable_detailed_tracing'] &&
            mt_rand(1, 100) <= ($this->config['trace_sampling_rate'] * 100)) {
            $this->startTracing($sessionId);
        }
    }

    /**
     * End performance monitoring session
     */
    public function endMonitoring(string $sessionId): array
    {
        if (!isset($this->activeSessions[$sessionId])) {
            return ['error' => 'Session not found'];
        }

        $session = $this->activeSessions[$sessionId];
        $endTime = microtime(true);
        $duration = $endTime - $session['start_time'];

        $finalMetrics = [
            'session_id' => $sessionId,
            'duration' => $duration,
            'memory_used' => memory_get_usage(true) - $session['memory_start'],
            'peak_memory' => memory_get_peak_usage(true),
            'context' => $session['context'],
            'timestamp' => time(),
            'traces' => $session['traces']
        ];

        // Store metrics
        $this->storeMetrics($finalMetrics);

        // Check for performance issues
        $this->analyzePerformance($finalMetrics);

        // Clean up
        unset($this->activeSessions[$sessionId]);

        return $finalMetrics;
    }

    /**
     * Record performance metric
     */
    public function recordMetric(string $name, $value, array $tags = [], string $sessionId = null): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
            'session_id' => $sessionId
        ];

        $this->metrics[] = $metric;

        // Add to active session if provided
        if ($sessionId && isset($this->activeSessions[$sessionId])) {
            $this->activeSessions[$sessionId]['metrics'][] = $metric;
        }

        // Check alert thresholds
        $this->checkAlertThresholds($metric);

        // Keep metrics within limit
        if (count($this->metrics) > 10000) {
            array_shift($this->metrics);
        }
    }

    /**
     * Start performance tracing
     */
    public function startTracing(string $sessionId): void
    {
        if (!isset($this->activeSessions[$sessionId])) {
            return;
        }

        // Start tracing (simplified implementation)
        $this->activeSessions[$sessionId]['tracing'] = true;
        $this->activeSessions[$sessionId]['trace_start'] = microtime(true);
    }

    /**
     * Add trace point
     */
    public function addTracePoint(string $sessionId, string $point, array $data = []): void
    {
        if (!isset($this->activeSessions[$sessionId]) ||
            !$this->activeSessions[$sessionId]['tracing']) {
            return;
        }

        $tracePoint = [
            'point' => $point,
            'timestamp' => microtime(true),
            'data' => $data,
            'memory' => memory_get_usage(true)
        ];

        $this->activeSessions[$sessionId]['traces'][] = $tracePoint;
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(array $filters = []): array
    {
        $metrics = $this->metrics;

        // Apply filters
        if (!empty($filters)) {
            $metrics = array_filter($metrics, function($metric) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (isset($metric[$key]) && $metric[$key] !== $value) {
                        return false;
                    }
                    if (isset($metric['tags'][$key]) && $metric['tags'][$key] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        return array_values($metrics);
    }

    /**
     * Get performance statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $metrics = $this->getMetrics($filters);

        if (empty($metrics)) {
            return [];
        }

        $stats = [];
        $groupedMetrics = [];

        // Group metrics by name
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            if (!isset($groupedMetrics[$name])) {
                $groupedMetrics[$name] = [];
            }
            $groupedMetrics[$name][] = $metric['value'];
        }

        // Calculate statistics for each metric type
        foreach ($groupedMetrics as $name => $values) {
            $stats[$name] = [
                'count' => count($values),
                'min' => min($values),
                'max' => max($values),
                'avg' => array_sum($values) / count($values),
                'median' => $this->calculateMedian($values),
                'p95' => $this->calculatePercentile($values, 95),
                'p99' => $this->calculatePercentile($values, 99)
            ];
        }

        return $stats;
    }

    /**
     * Generate performance report
     */
    public function generateReport(array $filters = []): array
    {
        $reportId = uniqid('perf_report_');

        $report = [
            'id' => $reportId,
            'generated_at' => time(),
            'period' => $filters['period'] ?? 'last_24h',
            'metrics' => $this->getStatistics($filters),
            'alerts' => $this->getRecentAlerts($filters),
            'recommendations' => $this->generateRecommendations($filters),
            'summary' => $this->generateSummary($filters)
        ];

        $this->reports[$reportId] = $report;
        $this->storeReport($report);

        return $report;
    }

    /**
     * Get performance baselines
     */
    public function getBaselines(): array
    {
        return $this->baselines;
    }

    /**
     * Update performance baseline
     */
    public function updateBaseline(string $metricName, array $baselineData): void
    {
        $this->baselines[$metricName] = array_merge([
            'updated_at' => time(),
            'calculation_period_days' => $this->config['baseline_calculation_period']
        ], $baselineData);

        $this->storeBaseline($metricName, $this->baselines[$metricName]);
    }

    /**
     * Set alert threshold
     */
    public function setAlertThreshold(string $metricName, array $thresholds): void
    {
        $this->alertThresholds[$metricName] = array_merge([
            'warning' => null,
            'critical' => null,
            'comparison' => '>', // > or <
            'cooldown' => $this->config['alert_cooldown']
        ], $thresholds);
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        // Simplified implementation
        return [];
    }

    /**
     * Get performance recommendations
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        $stats = $this->getStatistics();

        // Memory usage recommendations
        if (isset($stats['memory_usage'])) {
            $memoryStats = $stats['memory_usage'];
            if ($memoryStats['avg'] > 100 * 1024 * 1024) { // 100MB
                $recommendations[] = [
                    'type' => 'memory',
                    'priority' => 'high',
                    'recommendation' => 'High memory usage detected. Consider implementing memory optimization patterns.',
                    'current_avg' => $memoryStats['avg'],
                    'threshold' => 100 * 1024 * 1024
                ];
            }
        }

        // Response time recommendations
        if (isset($stats['response_time'])) {
            $responseStats = $stats['response_time'];
            if ($responseStats['p95'] > 1.0) { // 1 second
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'recommendation' => 'Slow response times detected. Consider database optimization and caching improvements.',
                    'p95_response_time' => $responseStats['p95'],
                    'threshold' => 1.0
                ];
            }
        }

        // Database query recommendations
        if (isset($stats['db_query_time'])) {
            $dbStats = $stats['db_query_time'];
            if ($dbStats['p95'] > 0.1) { // 100ms
                $recommendations[] = [
                    'type' => 'database',
                    'priority' => 'medium',
                    'recommendation' => 'Slow database queries detected. Consider adding indexes and query optimization.',
                    'p95_query_time' => $dbStats['p95'],
                    'threshold' => 0.1
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Export performance data
     */
    public function exportData(array $filters = []): array
    {
        return [
            'metrics' => $this->getMetrics($filters),
            'statistics' => $this->getStatistics($filters),
            'baselines' => $this->baselines,
            'alerts' => $this->getActiveAlerts(),
            'reports' => array_slice($this->reports, -10), // Last 10 reports
            'export_timestamp' => time(),
            'filters' => $filters
        ];
    }

    // Private helper methods

    private function createMonitoringTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS performance_metrics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(64),
                metric_name VARCHAR(100) NOT NULL,
                metric_value DECIMAL(15,4),
                tags JSON,
                timestamp DECIMAL(16,6) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session_id (session_id),
                INDEX idx_metric_name (metric_name),
                INDEX idx_timestamp (timestamp),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS performance_alerts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                threshold_value DECIMAL(15,4),
                actual_value DECIMAL(15,4),
                severity ENUM('warning', 'critical') DEFAULT 'warning',
                message TEXT,
                resolved BOOLEAN DEFAULT FALSE,
                resolved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_metric_name (metric_name),
                INDEX idx_severity (severity),
                INDEX idx_resolved (resolved),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS performance_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(64) NOT NULL UNIQUE,
                report_data JSON,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_report_id (report_id),
                INDEX idx_generated_at (generated_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS performance_baselines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL UNIQUE,
                baseline_data JSON,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_metric_name (metric_name),
                INDEX idx_updated_at (updated_at)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create monitoring tables: " . $e->getMessage());
        }
    }

    private function initializeAlertSystem(): void
    {
        // Set default alert thresholds
        $this->setAlertThreshold('response_time', [
            'warning' => 0.5,
            'critical' => 1.0
        ]);

        $this->setAlertThreshold('memory_usage', [
            'warning' => 50 * 1024 * 1024, // 50MB
            'critical' => 100 * 1024 * 1024 // 100MB
        ]);

        $this->setAlertThreshold('db_query_time', [
            'warning' => 0.05,
            'critical' => 0.1
        ]);
    }

    private function loadBaselines(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM performance_baselines");
            $baselines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($baselines as $baseline) {
                $this->baselines[$baseline['metric_name']] = json_decode($baseline['baseline_data'], true);
            }
        } catch (PDOException $e) {
            error_log("Failed to load baselines: " . $e->getMessage());
        }
    }

    private function setupAlertThresholds(): void
    {
        // Load alert thresholds from configuration or database
        // Simplified implementation
    }

    private function storeMetrics(array $metrics): void
    {
        if (!$this->config['enable_real_time_monitoring']) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_metrics
                (session_id, metric_name, metric_value, tags, timestamp)
                VALUES (?, ?, ?, ?, ?)
            ");

            if (isset($metrics['session_id'])) {
                // Single metric
                $stmt->execute([
                    $metrics['session_id'],
                    'session_duration',
                    $metrics['duration'],
                    json_encode($metrics['context'] ?? []),
                    $metrics['timestamp']
                ]);
            } else {
                // Multiple metrics
                foreach ($this->metrics as $metric) {
                    $stmt->execute([
                        $metric['session_id'] ?? null,
                        $metric['name'],
                        $metric['value'],
                        json_encode($metric['tags'] ?? []),
                        $metric['timestamp']
                    ]);
                }
            }
        } catch (PDOException $e) {
            error_log("Failed to store metrics: " . $e->getMessage());
        }
    }

    private function analyzePerformance(array $metrics): void
    {
        // Check against baselines
        $this->checkAgainstBaselines($metrics);

        // Generate insights
        $insights = $this->generateInsights($metrics);

        // Store analysis results
        $this->storeAnalysis($metrics['session_id'], $insights);
    }

    private function checkAlertThresholds(array $metric): void
    {
        $metricName = $metric['name'];
        $value = $metric['value'];

        if (!isset($this->alertThresholds[$metricName])) {
            return;
        }

        $thresholds = $this->alertThresholds[$metricName];
        $comparison = $thresholds['comparison'];

        // Check warning threshold
        if ($thresholds['warning'] !== null) {
            $triggerWarning = ($comparison === '>') ?
                ($value > $thresholds['warning']) :
                ($value < $thresholds['warning']);

            if ($triggerWarning) {
                $this->triggerAlert($metricName, 'warning', $value, $thresholds['warning']);
            }
        }

        // Check critical threshold
        if ($thresholds['critical'] !== null) {
            $triggerCritical = ($comparison === '>') ?
                ($value > $thresholds['critical']) :
                ($value < $thresholds['critical']);

            if ($triggerCritical) {
                $this->triggerAlert($metricName, 'critical', $value, $thresholds['critical']);
            }
        }
    }

    private function triggerAlert(string $metricName, string $severity, $actualValue, $thresholdValue): void
    {
        $alert = [
            'metric_name' => $metricName,
            'severity' => $severity,
            'threshold_value' => $thresholdValue,
            'actual_value' => $actualValue,
            'message' => "Performance alert: {$metricName} {$actualValue} exceeded threshold {$thresholdValue}",
            'created_at' => time()
        ];

        // Store alert
        $this->storeAlert($alert);

        // Send notification (simplified)
        error_log("PERFORMANCE ALERT: " . $alert['message']);
    }

    private function checkAgainstBaselines(array $metrics): void
    {
        // Compare current metrics against baselines
        // Simplified implementation
    }

    private function generateInsights(array $metrics): array
    {
        $insights = [];

        // Generate performance insights
        if ($metrics['duration'] > 1.0) {
            $insights[] = 'Session duration is above optimal threshold';
        }

        if ($metrics['memory_used'] > 10 * 1024 * 1024) { // 10MB
            $insights[] = 'High memory usage detected';
        }

        return $insights;
    }

    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        return $values[$index];
    }

    private function getRecentAlerts(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function generateRecommendations(array $filters = []): array
    {
        return $this->getRecommendations();
    }

    private function generateSummary(array $filters = []): array
    {
        $stats = $this->getStatistics($filters);

        return [
            'total_metrics' => count($this->metrics),
            'active_sessions' => count($this->activeSessions),
            'alerts_count' => count($this->getActiveAlerts()),
            'performance_score' => $this->calculatePerformanceScore($stats)
        ];
    }

    private function calculatePerformanceScore(array $stats): float
    {
        $score = 100.0;

        // Deduct points for poor performance
        if (isset($stats['response_time'])) {
            $responseStats = $stats['response_time'];
            if ($responseStats['p95'] > 1.0) {
                $score -= 20;
            } elseif ($responseStats['p95'] > 0.5) {
                $score -= 10;
            }
        }

        if (isset($stats['memory_usage'])) {
            $memoryStats = $stats['memory_usage'];
            if ($memoryStats['avg'] > 100 * 1024 * 1024) {
                $score -= 15;
            }
        }

        return max(0, $score);
    }

    // Database storage methods

    private function storeAlert(array $alert): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_alerts
                (metric_name, threshold_value, actual_value, severity, message)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $alert['metric_name'],
                $alert['threshold_value'],
                $alert['actual_value'],
                $alert['severity'],
                $alert['message']
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store alert: " . $e->getMessage());
        }
    }

    private function storeReport(array $report): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_reports
                (report_id, report_data, generated_at)
                VALUES (?, ?, FROM_UNIXTIME(?))
            ");

            $stmt->execute([
                $report['id'],
                json_encode($report),
                $report['generated_at']
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store report: " . $e->getMessage());
        }
    }

    private function storeBaseline(string $metricName, array $baselineData): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_baselines
                (metric_name, baseline_data, updated_at)
                VALUES (?, ?, FROM_UNIXTIME(?))
                ON DUPLICATE KEY UPDATE
                baseline_data = VALUES(baseline_data),
                updated_at = VALUES(updated_at)
            ");

            $stmt->execute([
                $metricName,
                json_encode($baselineData),
                $baselineData['updated_at']
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store baseline: " . $e->getMessage());
        }
    }

    private function storeAnalysis(string $sessionId, array $insights): void
    {
        // Store analysis results
        // Simplified implementation
    }
}

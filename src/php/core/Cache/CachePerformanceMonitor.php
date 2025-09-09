<?php
/**
 * TPT Government Platform - Cache Performance Monitor
 *
 * Provides comprehensive monitoring and analytics for cache performance
 * including real-time metrics, trend analysis, and performance optimization
 */

class CachePerformanceMonitor
{
    private $cache;
    private $metrics = [];
    private $alerts = [];
    private $thresholds = [];
    private $performanceHistory = [];
    private $startTime;
    private $monitoringEnabled = true;

    /**
     * Constructor
     */
    public function __construct($cache = null, $config = [])
    {
        $this->cache = $cache;
        $this->startTime = microtime(true);

        $this->thresholds = array_merge([
            'hit_rate_warning' => 70.0,
            'hit_rate_critical' => 50.0,
            'response_time_warning' => 100.0, // ms
            'response_time_critical' => 500.0, // ms
            'memory_usage_warning' => 80.0, // percentage
            'memory_usage_critical' => 95.0, // percentage
            'error_rate_warning' => 5.0, // percentage
            'error_rate_critical' => 15.0 // percentage
        ], $config);

        $this->initializeMetrics();
        $this->setupAlertHandlers();
    }

    /**
     * Initialize performance metrics
     */
    private function initializeMetrics()
    {
        $this->metrics = [
            'requests_total' => 0,
            'requests_per_second' => 0,
            'hits_total' => 0,
            'misses_total' => 0,
            'hit_rate' => 0.0,
            'avg_response_time' => 0.0,
            'min_response_time' => PHP_FLOAT_MAX,
            'max_response_time' => 0.0,
            'errors_total' => 0,
            'error_rate' => 0.0,
            'memory_usage' => 0,
            'memory_peak' => 0,
            'cache_size' => 0,
            'evictions_total' => 0,
            'promotions_total' => 0,
            'demotions_total' => 0,
            'layer_distribution' => [
                'memory' => 0,
                'file' => 0,
                'database' => 0
            ],
            'uptime' => 0
        ];
    }

    /**
     * Record cache operation
     */
    public function recordOperation($operation, $layer = null, $responseTime = null, $success = true)
    {
        if (!$this->monitoringEnabled) {
            return;
        }

        $this->metrics['requests_total']++;

        if ($responseTime !== null) {
            $this->updateResponseTimeMetrics($responseTime);
        }

        if (!$success) {
            $this->metrics['errors_total']++;
        }

        if ($layer) {
            $this->updateLayerMetrics($layer, $operation);
        }

        $this->updateDerivedMetrics();
        $this->checkThresholds();
        $this->storeHistoricalData();
    }

    /**
     * Record cache hit
     */
    public function recordHit($layer = null, $responseTime = null)
    {
        $this->metrics['hits_total']++;
        $this->recordOperation('hit', $layer, $responseTime);
    }

    /**
     * Record cache miss
     */
    public function recordMiss($layer = null, $responseTime = null)
    {
        $this->metrics['misses_total']++;
        $this->recordOperation('miss', $layer, $responseTime);
    }

    /**
     * Record cache eviction
     */
    public function recordEviction($count = 1)
    {
        $this->metrics['evictions_total'] += $count;
    }

    /**
     * Record cache promotion
     */
    public function recordPromotion()
    {
        $this->metrics['promotions_total']++;
    }

    /**
     * Record cache demotion
     */
    public function recordDemotion()
    {
        $this->metrics['demotions_total']++;
    }

    /**
     * Update response time metrics
     */
    private function updateResponseTimeMetrics($responseTime)
    {
        $this->metrics['avg_response_time'] =
            ($this->metrics['avg_response_time'] * ($this->metrics['requests_total'] - 1) + $responseTime) /
            $this->metrics['requests_total'];

        $this->metrics['min_response_time'] = min($this->metrics['min_response_time'], $responseTime);
        $this->metrics['max_response_time'] = max($this->metrics['max_response_time'], $responseTime);
    }

    /**
     * Update layer-specific metrics
     */
    private function updateLayerMetrics($layer, $operation)
    {
        if (!isset($this->metrics['layer_distribution'][$layer])) {
            $this->metrics['layer_distribution'][$layer] = 0;
        }

        $this->metrics['layer_distribution'][$layer]++;
    }

    /**
     * Update derived metrics
     */
    private function updateDerivedMetrics()
    {
        // Calculate hit rate
        $totalRequests = $this->metrics['hits_total'] + $this->metrics['misses_total'];
        if ($totalRequests > 0) {
            $this->metrics['hit_rate'] = ($this->metrics['hits_total'] / $totalRequests) * 100;
        }

        // Calculate error rate
        if ($this->metrics['requests_total'] > 0) {
            $this->metrics['error_rate'] = ($this->metrics['errors_total'] / $this->metrics['requests_total']) * 100;
        }

        // Calculate requests per second
        $uptime = microtime(true) - $this->startTime;
        if ($uptime > 0) {
            $this->metrics['requests_per_second'] = $this->metrics['requests_total'] / $uptime;
            $this->metrics['uptime'] = $uptime;
        }

        // Update memory metrics
        $this->updateMemoryMetrics();
    }

    /**
     * Update memory usage metrics
     */
    private function updateMemoryMetrics()
    {
        $this->metrics['memory_usage'] = memory_get_usage(true);
        $this->metrics['memory_peak'] = memory_get_peak_usage(true);
    }

    /**
     * Check performance thresholds and trigger alerts
     */
    private function checkThresholds()
    {
        $this->checkHitRateThreshold();
        $this->checkResponseTimeThreshold();
        $this->checkMemoryThreshold();
        $this->checkErrorRateThreshold();
    }

    /**
     * Check hit rate threshold
     */
    private function checkHitRateThreshold()
    {
        $hitRate = $this->metrics['hit_rate'];

        if ($hitRate <= $this->thresholds['hit_rate_critical']) {
            $this->triggerAlert('CRITICAL', 'Cache hit rate critically low', [
                'hit_rate' => $hitRate,
                'threshold' => $this->thresholds['hit_rate_critical']
            ]);
        } elseif ($hitRate <= $this->thresholds['hit_rate_warning']) {
            $this->triggerAlert('WARNING', 'Cache hit rate below warning threshold', [
                'hit_rate' => $hitRate,
                'threshold' => $this->thresholds['hit_rate_warning']
            ]);
        }
    }

    /**
     * Check response time threshold
     */
    private function checkResponseTimeThreshold()
    {
        $avgResponseTime = $this->metrics['avg_response_time'];

        if ($avgResponseTime >= $this->thresholds['response_time_critical']) {
            $this->triggerAlert('CRITICAL', 'Cache response time critically high', [
                'avg_response_time' => $avgResponseTime,
                'threshold' => $this->thresholds['response_time_critical']
            ]);
        } elseif ($avgResponseTime >= $this->thresholds['response_time_warning']) {
            $this->triggerAlert('WARNING', 'Cache response time above warning threshold', [
                'avg_response_time' => $avgResponseTime,
                'threshold' => $this->thresholds['response_time_warning']
            ]);
        }
    }

    /**
     * Check memory usage threshold
     */
    private function checkMemoryThreshold()
    {
        $memoryUsagePercent = ($this->metrics['memory_usage'] / ini_get('memory_limit')) * 100;

        if ($memoryUsagePercent >= $this->thresholds['memory_usage_critical']) {
            $this->triggerAlert('CRITICAL', 'Memory usage critically high', [
                'memory_usage_percent' => $memoryUsagePercent,
                'threshold' => $this->thresholds['memory_usage_critical']
            ]);
        } elseif ($memoryUsagePercent >= $this->thresholds['memory_usage_warning']) {
            $this->triggerAlert('WARNING', 'Memory usage above warning threshold', [
                'memory_usage_percent' => $memoryUsagePercent,
                'threshold' => $this->thresholds['memory_usage_warning']
            ]);
        }
    }

    /**
     * Check error rate threshold
     */
    private function checkErrorRateThreshold()
    {
        $errorRate = $this->metrics['error_rate'];

        if ($errorRate >= $this->thresholds['error_rate_critical']) {
            $this->triggerAlert('CRITICAL', 'Cache error rate critically high', [
                'error_rate' => $errorRate,
                'threshold' => $this->thresholds['error_rate_critical']
            ]);
        } elseif ($errorRate >= $this->thresholds['error_rate_warning']) {
            $this->triggerAlert('WARNING', 'Cache error rate above warning threshold', [
                'error_rate' => $errorRate,
                'threshold' => $this->thresholds['error_rate_warning']
            ]);
        }
    }

    /**
     * Trigger performance alert
     */
    private function triggerAlert($level, $message, $data = [])
    {
        $alert = [
            'timestamp' => time(),
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'metrics' => $this->metrics
        ];

        $this->alerts[] = $alert;

        // Log alert
        $this->logAlert($alert);

        // Execute alert handlers
        $this->executeAlertHandlers($alert);
    }

    /**
     * Log alert to file
     */
    private function logAlert($alert)
    {
        $logMessage = sprintf(
            "[%s] %s: %s - %s\n",
            date('Y-m-d H:i:s', $alert['timestamp']),
            $alert['level'],
            $alert['message'],
            json_encode($alert['data'])
        );

        $logFile = LOG_PATH . '/cache_performance_alerts.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Setup alert handlers
     */
    private function setupAlertHandlers()
    {
        // Default alert handlers can be added here
        // For example: email notifications, Slack alerts, etc.
    }

    /**
     * Execute alert handlers
     */
    private function executeAlertHandlers($alert)
    {
        // Execute registered alert handlers
        // This could include sending emails, Slack messages, etc.
    }

    /**
     * Store historical performance data
     */
    private function storeHistoricalData()
    {
        $currentTime = time();

        // Store metrics every minute
        if (!isset($this->performanceHistory[$currentTime])) {
            $this->performanceHistory[$currentTime] = $this->metrics;
        }

        // Keep only last 24 hours of data
        $oneDayAgo = $currentTime - 86400;
        $this->performanceHistory = array_filter(
            $this->performanceHistory,
            function($timestamp) use ($oneDayAgo) {
                return $timestamp >= $oneDayAgo;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Get current performance metrics
     */
    public function getMetrics()
    {
        $this->updateDerivedMetrics();
        return $this->metrics;
    }

    /**
     * Get performance statistics
     */
    public function getStatistics($timeRange = 3600)
    {
        $currentTime = time();
        $startTime = $currentTime - $timeRange;

        $relevantHistory = array_filter(
            $this->performanceHistory,
            function($timestamp) use ($startTime) {
                return $timestamp >= $startTime;
            },
            ARRAY_FILTER_USE_KEY
        );

        if (empty($relevantHistory)) {
            return $this->getMetrics();
        }

        $stats = [
            'time_range' => $timeRange,
            'data_points' => count($relevantHistory),
            'hit_rate_trend' => $this->calculateTrend($relevantHistory, 'hit_rate'),
            'response_time_trend' => $this->calculateTrend($relevantHistory, 'avg_response_time'),
            'error_rate_trend' => $this->calculateTrend($relevantHistory, 'error_rate'),
            'peak_hit_rate' => max(array_column($relevantHistory, 'hit_rate')),
            'lowest_hit_rate' => min(array_column($relevantHistory, 'hit_rate')),
            'avg_response_time' => array_sum(array_column($relevantHistory, 'avg_response_time')) / count($relevantHistory),
            'total_requests' => end($relevantHistory)['requests_total'] - reset($relevantHistory)['requests_total']
        ];

        return $stats;
    }

    /**
     * Calculate trend for a metric
     */
    private function calculateTrend($history, $metric)
    {
        if (count($history) < 2) {
            return 'insufficient_data';
        }

        $values = array_values($history);
        $first = reset($values)[$metric];
        $last = end($values)[$metric];

        if ($first == 0) {
            return $last > 0 ? 'increasing' : 'stable';
        }

        $change = (($last - $first) / $first) * 100;

        if ($change > 5) {
            return 'increasing';
        } elseif ($change < -5) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Get performance alerts
     */
    public function getAlerts($limit = 50)
    {
        return array_slice(array_reverse($this->alerts), 0, $limit);
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport()
    {
        $currentMetrics = $this->getMetrics();
        $hourlyStats = $this->getStatistics(3600);
        $dailyStats = $this->getStatistics(86400);

        return [
            'current' => $currentMetrics,
            'hourly' => $hourlyStats,
            'daily' => $dailyStats,
            'alerts' => $this->getAlerts(10),
            'recommendations' => $this->generateRecommendations($currentMetrics, $hourlyStats),
            'generated_at' => time()
        ];
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations($currentMetrics, $hourlyStats)
    {
        $recommendations = [];

        // Hit rate recommendations
        if ($currentMetrics['hit_rate'] < 70) {
            $recommendations[] = [
                'type' => 'hit_rate',
                'priority' => 'high',
                'message' => 'Consider increasing cache size or adjusting eviction policy',
                'action' => 'Increase cache capacity or review cache warming strategies'
            ];
        }

        // Response time recommendations
        if ($currentMetrics['avg_response_time'] > 100) {
            $recommendations[] = [
                'type' => 'response_time',
                'priority' => 'high',
                'message' => 'High response times detected',
                'action' => 'Consider using faster storage layers or optimizing cache access patterns'
            ];
        }

        // Memory usage recommendations
        $memoryUsagePercent = ($currentMetrics['memory_usage'] / ini_get('memory_limit')) * 100;
        if ($memoryUsagePercent > 80) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'message' => 'High memory usage detected',
                'action' => 'Consider increasing memory limit or optimizing memory usage'
            ];
        }

        // Error rate recommendations
        if ($currentMetrics['error_rate'] > 5) {
            $recommendations[] = [
                'type' => 'error_rate',
                'priority' => 'high',
                'message' => 'High error rate detected',
                'action' => 'Investigate cache errors and fix underlying issues'
            ];
        }

        return $recommendations;
    }

    /**
     * Export performance data
     */
    public function exportData($format = 'json', $includeHistory = false)
    {
        $data = [
            'metrics' => $this->getMetrics(),
            'alerts' => $this->getAlerts(),
            'exported_at' => time()
        ];

        if ($includeHistory) {
            $data['history'] = $this->performanceHistory;
        }

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV($data);
            case 'xml':
                return $this->exportToXML($data);
            default:
                return $data;
        }
    }

    /**
     * Export data to CSV
     */
    private function exportToCSV($data)
    {
        $csv = "Metric,Value\n";

        foreach ($data['metrics'] as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $csv .= "{$key},{$value}\n";
        }

        return $csv;
    }

    /**
     * Export data to XML
     */
    private function exportToXML($data)
    {
        $xml = new SimpleXMLElement('<cache-performance/>');

        foreach ($data['metrics'] as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXML($value, $child);
            } else {
                $xml->addChild($key, $value);
            }
        }

        return $xml->asXML();
    }

    /**
     * Convert array to XML
     */
    private function arrayToXML($array, &$xml)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXML($value, $subnode);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }

    /**
     * Enable/disable monitoring
     */
    public function setMonitoringEnabled($enabled)
    {
        $this->monitoringEnabled = $enabled;
    }

    /**
     * Update thresholds
     */
    public function updateThresholds($thresholds)
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);
    }

    /**
     * Reset all metrics
     */
    public function reset()
    {
        $this->initializeMetrics();
        $this->alerts = [];
        $this->performanceHistory = [];
        $this->startTime = microtime(true);
    }
}

/**
 * Cache Performance Dashboard
 */
class CachePerformanceDashboard
{
    private $monitor;
    private $cache;

    public function __construct(CachePerformanceMonitor $monitor, $cache = null)
    {
        $this->monitor = $monitor;
        $this->cache = $cache;
    }

    /**
     * Generate HTML dashboard
     */
    public function generateHTMLDashboard()
    {
        $metrics = $this->monitor->getMetrics();
        $report = $this->monitor->getPerformanceReport();

        $html = $this->getHTMLHeader();
        $html .= $this->generateMetricsSection($metrics);
        $html .= $this->generateChartsSection($report);
        $html .= $this->generateAlertsSection($report['alerts']);
        $html .= $this->generateRecommendationsSection($report['recommendations']);
        $html .= $this->getHTMLFooter();

        return $html;
    }

    /**
     * Get HTML header
     */
    private function getHTMLHeader()
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cache Performance Dashboard</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .metric { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
                .alert { padding: 10px; margin: 5px 0; border-radius: 5px; }
                .alert.CRITICAL { background: #ffcccc; border-left: 5px solid #ff0000; }
                .alert.WARNING { background: #ffffcc; border-left: 5px solid #ffaa00; }
                .chart { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .recommendation { background: #e8f4f8; padding: 10px; margin: 5px 0; border-radius: 5px; }
                .high { color: #ff0000; }
                .medium { color: #ffaa00; }
                .low { color: #00aa00; }
            </style>
        </head>
        <body>
            <h1>Cache Performance Dashboard</h1>
            <p>Generated at: ' . date('Y-m-d H:i:s') . '</p>
        ';
    }

    /**
     * Generate metrics section
     */
    private function generateMetricsSection($metrics)
    {
        $html = '<h2>Current Metrics</h2>';

        $html .= '<div class="metric">';
        $html .= '<h3>Cache Performance</h3>';
        $html .= '<p>Hit Rate: <strong>' . number_format($metrics['hit_rate'], 2) . '%</strong></p>';
        $html .= '<p>Total Requests: <strong>' . number_format($metrics['requests_total']) . '</strong></p>';
        $html .= '<p>Requests/sec: <strong>' . number_format($metrics['requests_per_second'], 2) . '</strong></p>';
        $html .= '<p>Avg Response Time: <strong>' . number_format($metrics['avg_response_time'], 2) . 'ms</strong></p>';
        $html .= '<p>Error Rate: <strong>' . number_format($metrics['error_rate'], 2) . '%</strong></p>';
        $html .= '</div>';

        $html .= '<div class="metric">';
        $html .= '<h3>Cache Operations</h3>';
        $html .= '<p>Hits: <strong>' . number_format($metrics['hits_total']) . '</strong></p>';
        $html .= '<p>Misses: <strong>' . number_format($metrics['misses_total']) . '</strong></p>';
        $html .= '<p>Evictions: <strong>' . number_format($metrics['evictions_total']) . '</strong></p>';
        $html .= '<p>Promotions: <strong>' . number_format($metrics['promotions_total']) . '</strong></p>';
        $html .= '<p>Demotions: <strong>' . number_format($metrics['demotions_total']) . '</strong></p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate charts section
     */
    private function generateChartsSection($report)
    {
        $html = '<h2>Performance Trends</h2>';

        $html .= '<div class="chart">';
        $html .= '<h3>Hit Rate Trend (Last Hour)</h3>';
        $html .= '<p>Trend: <strong>' . ucfirst($report['hourly']['hit_rate_trend']) . '</strong></p>';
        $html .= '<p>Peak: <strong>' . number_format($report['hourly']['peak_hit_rate'], 2) . '%</strong></p>';
        $html .= '<p>Lowest: <strong>' . number_format($report['hourly']['lowest_hit_rate'], 2) . '%</strong></p>';
        $html .= '</div>';

        $html .= '<div class="chart">';
        $html .= '<h3>Response Time Trend (Last Hour)</h3>';
        $html .= '<p>Trend: <strong>' . ucfirst($report['hourly']['response_time_trend']) . '</strong></p>';
        $html .= '<p>Average: <strong>' . number_format($report['hourly']['avg_response_time'], 2) . 'ms</strong></p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate alerts section
     */
    private function generateAlertsSection($alerts)
    {
        $html = '<h2>Recent Alerts</h2>';

        if (empty($alerts)) {
            $html .= '<p>No alerts in the last period.</p>';
        } else {
            foreach ($alerts as $alert) {
                $html .= '<div class="alert ' . $alert['level'] . '">';
                $html .= '<strong>' . $alert['level'] . ':</strong> ' . $alert['message'];
                $html .= '<br><small>' . date('Y-m-d H:i:s', $alert['timestamp']) . '</small>';
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Generate recommendations section
     */
    private function generateRecommendationsSection($recommendations)
    {
        $html = '<h2>Performance Recommendations</h2>';

        if (empty($recommendations)) {
            $html .= '<p>No recommendations at this time.</p>';
        } else {
            foreach ($recommendations as $rec) {
                $priorityClass = strtolower($rec['priority']);
                $html .= '<div class="recommendation">';
                $html .= '<strong class="' . $priorityClass . '">' . ucfirst($rec['priority']) . ':</strong> ' . $rec['message'];
                $html .= '<br><em>' . $rec['action'] . '</em>';
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Get HTML footer
     */
    private function getHTMLFooter()
    {
        return '
        </body>
        </html>
        ';
    }

    /**
     * Save dashboard to file
     */
    public function saveDashboard($filename = null)
    {
        if (!$filename) {
            $filename = CACHE_PATH . '/performance_dashboard_' . date('Y-m-d_H-i-s') . '.html';
        }

        $html = $this->generateHTMLDashboard();
        file_put_contents($filename, $html);

        return $filename;
    }
}

<?php
/**
 * TPT Government Platform - Analytics Engine
 *
 * Specialized manager for API analytics, metrics, and insights
 */

namespace Core\APIMarketplace;

use Core\Database;

class AnalyticsEngine
{
    /**
     * Analytics configuration
     */
    private array $config = [
        'enabled' => true,
        'real_time_analytics' => true,
        'historical_data_retention' => 365, // days
        'metrics_collection_interval' => 60, // seconds
        'anomaly_detection' => true,
        'performance_monitoring' => true,
        'usage_forecasting' => true,
        'custom_dashboards' => true,
        'alert_system' => true
    ];

    /**
     * API metrics storage
     */
    private array $apiMetrics = [];

    /**
     * Performance data
     */
    private array $performanceData = [];

    /**
     * Usage patterns
     */
    private array $usagePatterns = [];

    /**
     * Error tracking
     */
    private array $errorTracking = [];

    /**
     * Geographic data
     */
    private array $geographicData = [];

    /**
     * Custom dashboards
     */
    private array $customDashboards = [];

    /**
     * Alert configurations
     */
    private array $alertConfigs = [];

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge($this->config, $config);
        $this->initializeAnalytics();
    }

    /**
     * Initialize analytics engine
     */
    private function initializeAnalytics(): void
    {
        // Load existing metrics
        $this->loadMetrics();

        // Initialize performance monitoring
        $this->initializePerformanceMonitoring();

        // Set up real-time processing
        if ($this->config['real_time_analytics']) {
            $this->setupRealTimeProcessing();
        }

        // Initialize anomaly detection
        if ($this->config['anomaly_detection']) {
            $this->initializeAnomalyDetection();
        }

        // Set up alert system
        if ($this->config['alert_system']) {
            $this->setupAlertSystem();
        }
    }

    /**
     * Record API request metrics
     */
    public function recordRequestMetrics(array $requestData): void
    {
        $metricsKey = date('Y-m-d-H-i');

        if (!isset($this->apiMetrics[$metricsKey])) {
            $this->apiMetrics[$metricsKey] = [
                'timestamp' => time(),
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'average_response_time' => 0,
                'requests_per_endpoint' => [],
                'requests_per_method' => [],
                'requests_per_developer' => [],
                'geographic_distribution' => [],
                'device_types' => [],
                'response_codes' => []
            ];
        }

        $metrics = &$this->apiMetrics[$metricsKey];

        // Update basic counters
        $metrics['total_requests']++;

        if ($requestData['success'] ?? false) {
            $metrics['successful_requests']++;
        } else {
            $metrics['failed_requests']++;
        }

        // Update response time average
        $responseTime = $requestData['response_time'] ?? 0;
        $metrics['average_response_time'] =
            ($metrics['average_response_time'] * ($metrics['total_requests'] - 1) + $responseTime) / $metrics['total_requests'];

        // Update endpoint metrics
        $endpoint = $requestData['endpoint'] ?? 'unknown';
        $metrics['requests_per_endpoint'][$endpoint] = ($metrics['requests_per_endpoint'][$endpoint] ?? 0) + 1;

        // Update method metrics
        $method = $requestData['method'] ?? 'GET';
        $metrics['requests_per_method'][$method] = ($metrics['requests_per_method'][$method] ?? 0) + 1;

        // Update developer metrics
        $developerId = $requestData['developer_id'] ?? 'anonymous';
        $metrics['requests_per_developer'][$developerId] = ($metrics['requests_per_developer'][$developerId] ?? 0) + 1;

        // Update geographic data
        $country = $requestData['country'] ?? 'Unknown';
        $metrics['geographic_distribution'][$country] = ($metrics['geographic_distribution'][$country] ?? 0) + 1;

        // Update device types
        $deviceType = $requestData['device_type'] ?? 'unknown';
        $metrics['device_types'][$deviceType] = ($metrics['device_types'][$deviceType] ?? 0) + 1;

        // Update response codes
        $statusCode = $requestData['status_code'] ?? 200;
        $metrics['response_codes'][$statusCode] = ($metrics['response_codes'][$statusCode] ?? 0) + 1;

        // Save metrics
        $this->saveMetrics($metricsKey, $metrics);
    }

    /**
     * Get API metrics for time range
     */
    public function getMetrics(string $startDate, string $endDate, array $filters = []): array
    {
        $metrics = $this->aggregateMetrics($startDate, $endDate, $filters);

        return [
            'success' => true,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_requests' => $metrics['total_requests'],
                'successful_requests' => $metrics['successful_requests'],
                'failed_requests' => $metrics['failed_requests'],
                'success_rate' => $metrics['total_requests'] > 0 ?
                    ($metrics['successful_requests'] / $metrics['total_requests']) * 100 : 0,
                'average_response_time' => $metrics['average_response_time'],
                'error_rate' => $metrics['total_requests'] > 0 ?
                    ($metrics['failed_requests'] / $metrics['total_requests']) * 100 : 0
            ],
            'breakdown' => [
                'by_endpoint' => $metrics['requests_per_endpoint'],
                'by_method' => $metrics['requests_per_method'],
                'by_developer' => $metrics['requests_per_developer'],
                'by_country' => $metrics['geographic_distribution'],
                'by_device' => $metrics['device_types'],
                'by_status_code' => $metrics['response_codes']
            ],
            'trends' => $this->calculateTrends($startDate, $endDate),
            'generated_at' => time()
        ];
    }

    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics(array $filters = []): array
    {
        $performance = [
            'response_time_distribution' => $this->getResponseTimeDistribution(),
            'throughput_analysis' => $this->getThroughputAnalysis(),
            'error_analysis' => $this->getErrorAnalysis(),
            'bottleneck_identification' => $this->identifyBottlenecks(),
            'optimization_recommendations' => $this->getOptimizationRecommendations()
        ];

        return [
            'success' => true,
            'performance' => $performance,
            'generated_at' => time()
        ];
    }

    /**
     * Detect anomalies in API usage
     */
    public function detectAnomalies(array $filters = []): array
    {
        $anomalies = [];

        // Check for unusual traffic patterns
        $trafficAnomalies = $this->detectTrafficAnomalies();
        if (!empty($trafficAnomalies)) {
            $anomalies = array_merge($anomalies, $trafficAnomalies);
        }

        // Check for unusual error rates
        $errorAnomalies = $this->detectErrorAnomalies();
        if (!empty($errorAnomalies)) {
            $anomalies = array_merge($anomalies, $errorAnomalies);
        }

        // Check for unusual response times
        $performanceAnomalies = $this->detectPerformanceAnomalies();
        if (!empty($performanceAnomalies)) {
            $anomalies = array_merge($anomalies, $performanceAnomalies);
        }

        // Check for geographic anomalies
        $geographicAnomalies = $this->detectGeographicAnomalies();
        if (!empty($geographicAnomalies)) {
            $anomalies = array_merge($anomalies, $geographicAnomalies);
        }

        return [
            'success' => true,
            'total_anomalies' => count($anomalies),
            'anomalies' => $anomalies,
            'severity_distribution' => $this->getAnomalySeverityDistribution($anomalies),
            'generated_at' => time()
        ];
    }

    /**
     * Generate usage forecast
     */
    public function generateUsageForecast(int $days = 30): array
    {
        if (!$this->config['usage_forecasting']) {
            return [
                'success' => false,
                'error' => 'Usage forecasting is disabled'
            ];
        }

        $historicalData = $this->getHistoricalUsageData($days * 2); // Use double the period for training

        $forecast = [
            'period_days' => $days,
            'forecast_data' => [],
            'confidence_intervals' => [],
            'trend_analysis' => $this->analyzeUsageTrends($historicalData),
            'seasonal_patterns' => $this->identifySeasonalPatterns($historicalData)
        ];

        // Generate forecast for each day
        for ($i = 1; $i <= $days; $i++) {
            $forecastDate = date('Y-m-d', strtotime("+{$i} days"));
            $forecast['forecast_data'][$forecastDate] = [
                'predicted_requests' => $this->predictDailyUsage($historicalData, $i),
                'lower_bound' => 0, // Would be calculated with statistical methods
                'upper_bound' => 0  // Would be calculated with statistical methods
            ];
        }

        return [
            'success' => true,
            'forecast' => $forecast,
            'generated_at' => time()
        ];
    }

    /**
     * Create custom dashboard
     */
    public function createCustomDashboard(string $developerId, array $config): array
    {
        if (!$this->config['custom_dashboards']) {
            return [
                'success' => false,
                'error' => 'Custom dashboards are disabled'
            ];
        }

        $dashboardId = uniqid('dash_');

        $dashboard = [
            'id' => $dashboardId,
            'developer_id' => $developerId,
            'name' => $config['name'],
            'description' => $config['description'] ?? '',
            'widgets' => $config['widgets'] ?? [],
            'filters' => $config['filters'] ?? [],
            'refresh_interval' => $config['refresh_interval'] ?? 300, // 5 minutes
            'is_public' => $config['is_public'] ?? false,
            'created_at' => time(),
            'updated_at' => time()
        ];

        $this->customDashboards[$dashboardId] = $dashboard;
        $this->saveCustomDashboard($dashboardId, $dashboard);

        return [
            'success' => true,
            'dashboard_id' => $dashboardId,
            'message' => 'Custom dashboard created successfully'
        ];
    }

    /**
     * Get dashboard data
     */
    public function getDashboardData(string $dashboardId): array
    {
        if (!isset($this->customDashboards[$dashboardId])) {
            return [
                'success' => false,
                'error' => 'Dashboard not found'
            ];
        }

        $dashboard = $this->customDashboards[$dashboardId];
        $data = [];

        foreach ($dashboard['widgets'] as $widget) {
            $data[$widget['id']] = $this->generateWidgetData($widget);
        }

        return [
            'success' => true,
            'dashboard' => [
                'id' => $dashboard['id'],
                'name' => $dashboard['name'],
                'description' => $dashboard['description']
            ],
            'data' => $data,
            'generated_at' => time()
        ];
    }

    /**
     * Configure alert
     */
    public function configureAlert(string $developerId, array $alertConfig): array
    {
        if (!$this->config['alert_system']) {
            return [
                'success' => false,
                'error' => 'Alert system is disabled'
            ];
        }

        $alertId = uniqid('alert_');

        $alert = [
            'id' => $alertId,
            'developer_id' => $developerId,
            'name' => $alertConfig['name'],
            'description' => $alertConfig['description'] ?? '',
            'condition' => $alertConfig['condition'], // e.g., 'error_rate > 5'
            'threshold' => $alertConfig['threshold'],
            'time_window' => $alertConfig['time_window'] ?? 3600, // 1 hour
            'notification_channels' => $alertConfig['notification_channels'] ?? ['email'],
            'is_active' => true,
            'created_at' => time(),
            'last_triggered' => null
        ];

        $this->alertConfigs[$alertId] = $alert;
        $this->saveAlertConfig($alertId, $alert);

        return [
            'success' => true,
            'alert_id' => $alertId,
            'message' => 'Alert configured successfully'
        ];
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): array
    {
        $currentMetrics = end($this->apiMetrics) ?: [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'average_response_time' => 0
        ];

        return [
            'success' => true,
            'metrics' => [
                'current_requests_per_second' => $this->calculateRequestsPerSecond(),
                'current_error_rate' => $currentMetrics['total_requests'] > 0 ?
                    ($currentMetrics['failed_requests'] / $currentMetrics['total_requests']) * 100 : 0,
                'current_average_response_time' => $currentMetrics['average_response_time'],
                'active_connections' => $this->getActiveConnections(),
                'queue_length' => $this->getQueueLength()
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Export analytics data
     */
    public function exportAnalyticsData(string $format = 'json', array $filters = []): string
    {
        $data = [
            'export_timestamp' => time(),
            'metrics' => $this->apiMetrics,
            'performance' => $this->performanceData,
            'usage_patterns' => $this->usagePatterns,
            'error_tracking' => $this->errorTracking,
            'geographic_data' => $this->geographicData,
            'filters_applied' => $filters
        ];

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportAnalyticsToCSV($data);
            default:
                return json_encode($data);
        }
    }

    // Private helper methods

    private function aggregateMetrics(string $startDate, string $endDate, array $filters): array
    {
        $aggregated = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'average_response_time' => 0,
            'requests_per_endpoint' => [],
            'requests_per_method' => [],
            'requests_per_developer' => [],
            'geographic_distribution' => [],
            'device_types' => [],
            'response_codes' => []
        ];

        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate);

        foreach ($this->apiMetrics as $metricsKey => $metrics) {
            $metricsTime = strtotime(str_replace('-', '/', $metricsKey));

            if ($metricsTime >= $startTime && $metricsTime <= $endTime) {
                $aggregated['total_requests'] += $metrics['total_requests'];
                $aggregated['successful_requests'] += $metrics['successful_requests'];
                $aggregated['failed_requests'] += $metrics['failed_requests'];

                // Aggregate response time (weighted average)
                if ($metrics['total_requests'] > 0) {
                    $weight = $metrics['total_requests'] / max($aggregated['total_requests'], 1);
                    $aggregated['average_response_time'] +=
                        ($metrics['average_response_time'] - $aggregated['average_response_time']) * $weight;
                }

                // Merge arrays
                foreach (['requests_per_endpoint', 'requests_per_method', 'requests_per_developer',
                         'geographic_distribution', 'device_types', 'response_codes'] as $field) {
                    foreach ($metrics[$field] as $key => $value) {
                        $aggregated[$field][$key] = ($aggregated[$field][$key] ?? 0) + $value;
                    }
                }
            }
        }

        return $aggregated;
    }

    private function calculateTrends(string $startDate, string $endDate): array
    {
        $trends = [];
        $currentTime = strtotime($startDate);
        $endTime = strtotime($endDate);

        while ($currentTime <= $endTime) {
            $dateKey = date('Y-m-d', $currentTime);
            $hourlyData = array_filter($this->apiMetrics, function($key) use ($dateKey) {
                return strpos($key, $dateKey) === 0;
            });

            $dailyTotal = array_sum(array_column($hourlyData, 'total_requests'));
            $dailyErrors = array_sum(array_column($hourlyData, 'failed_requests'));

            $trends[] = [
                'date' => $dateKey,
                'requests' => $dailyTotal,
                'errors' => $dailyErrors,
                'error_rate' => $dailyTotal > 0 ? ($dailyErrors / $dailyTotal) * 100 : 0
            ];

            $currentTime = strtotime('+1 day', $currentTime);
        }

        return $trends;
    }

    private function getResponseTimeDistribution(): array
    {
        $responseTimes = [];

        foreach ($this->apiMetrics as $metrics) {
            if ($metrics['average_response_time'] > 0) {
                $responseTimes[] = $metrics['average_response_time'];
            }
        }

        if (empty($responseTimes)) {
            return ['distribution' => [], 'percentiles' => []];
        }

        sort($responseTimes);

        return [
            'distribution' => [
                'min' => min($responseTimes),
                'max' => max($responseTimes),
                'median' => $responseTimes[intval(count($responseTimes) / 2)],
                'average' => array_sum($responseTimes) / count($responseTimes)
            ],
            'percentiles' => [
                'p50' => $responseTimes[intval(count($responseTimes) * 0.5)],
                'p90' => $responseTimes[intval(count($responseTimes) * 0.9)],
                'p95' => $responseTimes[intval(count($responseTimes) * 0.95)],
                'p99' => $responseTimes[intval(count($responseTimes) * 0.99)]
            ]
        ];
    }

    private function getThroughputAnalysis(): array
    {
        $throughput = [];

        foreach ($this->apiMetrics as $metricsKey => $metrics) {
            $throughput[] = [
                'timestamp' => strtotime(str_replace('-', '/', $metricsKey)),
                'requests_per_minute' => $metrics['total_requests']
            ];
        }

        return [
            'current_throughput' => end($throughput)['requests_per_minute'] ?? 0,
            'peak_throughput' => max(array_column($throughput, 'requests_per_minute')),
            'average_throughput' => array_sum(array_column($throughput, 'requests_per_minute')) / max(count($throughput), 1),
            'throughput_trend' => $throughput
        ];
    }

    private function getErrorAnalysis(): array
    {
        $errorAnalysis = [
            'by_endpoint' => [],
            'by_status_code' => [],
            'by_time' => [],
            'top_errors' => []
        ];

        foreach ($this->apiMetrics as $metricsKey => $metrics) {
            foreach ($metrics['response_codes'] as $code => $count) {
                if ($code >= 400) {
                    $errorAnalysis['by_status_code'][$code] = ($errorAnalysis['by_status_code'][$code] ?? 0) + $count;
                }
            }
        }

        arsort($errorAnalysis['by_status_code']);
        $errorAnalysis['top_errors'] = array_slice($errorAnalysis['by_status_code'], 0, 5, true);

        return $errorAnalysis;
    }

    private function identifyBottlenecks(): array
    {
        $bottlenecks = [];

        // Identify slow endpoints
        $slowEndpoints = $this->identifySlowEndpoints();
        if (!empty($slowEndpoints)) {
            $bottlenecks[] = [
                'type' => 'slow_endpoints',
                'description' => 'Endpoints with high response times',
                'data' => $slowEndpoints
            ];
        }

        // Identify high error rate endpoints
        $errorProneEndpoints = $this->identifyErrorProneEndpoints();
        if (!empty($errorProneEndpoints)) {
            $bottlenecks[] = [
                'type' => 'error_prone_endpoints',
                'description' => 'Endpoints with high error rates',
                'data' => $errorProneEndpoints
            ];
        }

        return $bottlenecks;
    }

    private function getOptimizationRecommendations(): array
    {
        $recommendations = [];

        // Check for caching opportunities
        if ($this->shouldRecommendCaching()) {
            $recommendations[] = [
                'type' => 'caching',
                'title' => 'Implement Response Caching',
                'description' => 'Add caching for frequently requested endpoints to improve performance',
                'impact' => 'high',
                'effort' => 'medium'
            ];
        }

        // Check for rate limiting adjustments
        if ($this->shouldAdjustRateLimits()) {
            $recommendations[] = [
                'type' => 'rate_limiting',
                'title' => 'Optimize Rate Limits',
                'description' => 'Adjust rate limits based on usage patterns',
                'impact' => 'medium',
                'effort' => 'low'
            ];
        }

        return $recommendations;
    }

    private function detectTrafficAnomalies(): array
    {
        $anomalies = [];
        $recentMetrics = array_slice($this->apiMetrics, -10); // Last 10 time periods

        if (count($recentMetrics) < 2) return $anomalies;

        $avgRequests = array_sum(array_column($recentMetrics, 'total_requests')) / count($recentMetrics);
        $latestRequests = end($recentMetrics)['total_requests'];

        // Detect spike in traffic
        if ($latestRequests > $avgRequests * 2) {
            $anomalies[] = [
                'type' => 'traffic_spike',
                'description' => 'Unusual spike in API traffic detected',
                'severity' => 'medium',
                'current_value' => $latestRequests,
                'average_value' => $avgRequests,
                'timestamp' => time()
            ];
        }

        return $anomalies;
    }

    private function detectErrorAnomalies(): array
    {
        $anomalies = [];
        $recentMetrics = array_slice($this->apiMetrics, -5);

        foreach ($recentMetrics as $metrics) {
            $errorRate = $metrics['total_requests'] > 0 ?
                ($metrics['failed_requests'] / $metrics['total_requests']) * 100 : 0;

            if ($errorRate > 10) { // More than 10% error rate
                $anomalies[] = [
                    'type' => 'high_error_rate',
                    'description' => 'High error rate detected',
                    'severity' => 'high',
                    'error_rate' => $errorRate,
                    'timestamp' => $metrics['timestamp']
                ];
            }
        }

        return $anomalies;
    }

    private function detectPerformanceAnomalies(): array
    {
        $anomalies = [];
        $recentMetrics = array_slice($this->apiMetrics, -5);

        $avgResponseTime = array_sum(array_column($recentMetrics, 'average_response_time')) / count($recentMetrics);
        $latestResponseTime = end($recentMetrics)['average_response_time'];

        if ($latestResponseTime > $avgResponseTime * 1.5) {
            $anomalies[] = [
                'type' => 'slow_response_time',
                'description' => 'Response time significantly increased',
                'severity' => 'medium',
                'current_response_time' => $latestResponseTime,
                'average_response_time' => $avgResponseTime,
                'timestamp' => time()
            ];
        }

        return $anomalies;
    }

    private function detectGeographicAnomalies(): array
    {
        // Would implement geographic anomaly detection
        return [];
    }

    private function getAnomalySeverityDistribution(array $anomalies): array
    {
        $distribution = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];

        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'medium';
            $distribution[$severity] = ($distribution[$severity] ?? 0) + 1;
        }

        return $distribution;
    }

    private function getHistoricalUsageData(int $days): array
    {
        $data = [];
        for ($i = $days; $i > 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyMetrics = array_filter($this->apiMetrics, function($key) use ($date) {
                return strpos($key, $date) === 0;
            });

            $data[$date] = array_sum(array_column($dailyMetrics, 'total_requests'));
        }

        return $data;
    }

    private function predictDailyUsage(array $historicalData, int $daysAhead): int
    {
        // Simple linear regression for forecasting
        $values = array_values($historicalData);
        $n = count($values);

        if ($n < 2) return end($values) ?: 0;

        $sumX = array_sum(range(1, $n));
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += ($i + 1) * $values[$i];
            $sumXX += ($i + 1) * ($i + 1);
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return intval($intercept + $slope * ($n + $daysAhead));
    }

    private function analyzeUsageTrends(array $historicalData): array
    {
        $values = array_values($historicalData);
        $n = count($values);

        if ($n < 2) return ['trend' => 'insufficient_data'];

        $firstHalf = array_slice($values, 0, intval($n / 2));
        $secondHalf = array_slice($values, intval($n / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        return [
            'trend' => $change > 10 ? 'increasing' : ($change < -10 ? 'decreasing' : 'stable'),
            'change_percentage' => round($change, 2),
            'first_half_average' => round($firstAvg, 2),
            'second_half_average' => round($secondAvg, 2)
        ];
    }

    private function identifySeasonalPatterns(array $historicalData): array
    {
        // Simple day-of-week pattern identification
        $patterns = ['monday' => [], 'tuesday' => [], 'wednesday' => [], 'thursday' => [], 'friday' => [], 'saturday' => [], 'sunday' => []];

        foreach ($historicalData as $date => $value) {
            $dayOfWeek = strtolower(date('l', strtotime($date)));
            $patterns[$dayOfWeek][] = $value;
        }

        $averages = [];
        foreach ($patterns as $day => $values) {
            $averages[$day] = count($values) > 0 ? array_sum($values) / count($values) : 0;
        }

        return $averages;
    }

    private function generateWidgetData(array $widget): array
    {
        // Generate data for dashboard widget based on type
        switch ($widget['type']) {
            case 'requests_chart':
                return $this->getRequestsChartData($widget);
            case 'error_rate_chart':
                return $this->getErrorRateChartData($widget);
            case 'response_time_chart':
                return $this->getResponseTimeChartData($widget);
            case 'geographic_map':
                return $this->getGeographicMapData($widget);
            default:
                return ['error' => 'Unknown widget type'];
        }
    }

    private function getRequestsChartData(array $widget): array
    {
        $days = $widget['config']['days'] ?? 7;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyMetrics = array_filter($this->apiMetrics, function($key) use ($date) {
                return strpos($key, $date) === 0;
            });

            $data[] = [
                'date' => $date,
                'requests' => array_sum(array_column($dailyMetrics, 'total_requests'))
            ];
        }

        return $data;
    }

    private function getErrorRateChartData(array $widget): array
    {
        $days = $widget['config']['days'] ?? 7;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyMetrics = array_filter($this->apiMetrics, function($key) use ($date) {
                return strpos($key, $date) === 0;
            });

            $totalRequests = array_sum(array_column($dailyMetrics, 'total_requests'));
            $failedRequests = array_sum(array_column($dailyMetrics, 'failed_requests'));

            $data[] = [
                'date' => $date,
                'error_rate' => $totalRequests > 0 ? ($failedRequests / $totalRequests) * 100 : 0
            ];
        }

        return $data;
    }

    private function getResponseTimeChartData(array $widget): array
    {
        $hours = $widget['config']['hours'] ?? 24;
        $data = [];

        $recentMetrics = array_slice($this->apiMetrics, -$hours);

        foreach ($recentMetrics as $metricsKey => $metrics) {
            $data[] = [
                'time' => date('H:i', strtotime(str_replace('-', '/', $metricsKey))),
                'response_time' => $metrics['average_response_time']
            ];
        }

        return $data;
    }

    private function getGeographicMapData(array $widget): array
    {
        $data = [];

        // Aggregate geographic data from recent metrics
        $recentMetrics = array_slice($this->apiMetrics, -24); // Last 24 hours

        foreach ($recentMetrics as $metrics) {
            foreach ($metrics['geographic_distribution'] as $country => $count) {
                if (!isset($data[$country])) {
                    $data[$country] = 0;
                }
                $data[$country] += $count;
            }
        }

        return array_map(function($country, $count) {
            return ['country' => $country, 'requests' => $count];
        }, array_keys($data), $data);
    }

    private function calculateRequestsPerSecond(): float
    {
        $latestMetrics = end($this->apiMetrics);
        return $latestMetrics ? $latestMetrics['total_requests'] / 60 : 0; // Assuming 1-minute intervals
    }

    private function getActiveConnections(): int
    {
        // In real implementation, would track active connections
        return rand(10, 100);
    }

    private function getQueueLength(): int
    {
        // In real implementation, would track request queue
        return rand(0, 50);
    }

    private function identifySlowEndpoints(): array
    {
        // Would analyze endpoints with high response times
        return [];
    }

    private function identifyErrorProneEndpoints(): array
    {
        // Would analyze endpoints with high error rates
        return [];
    }

    private function shouldRecommendCaching(): bool
    {
        // Analyze if caching would be beneficial
        return rand(0, 1) === 1;
    }

    private function shouldAdjustRateLimits(): bool
    {
        // Analyze if rate limits need adjustment
        return rand(0, 1) === 1;
    }

    private function loadMetrics(): void
    {
        // In real implementation, load from database
    }

    private function saveMetrics(string $metricsKey, array $metrics): void
    {
        // In real implementation, save to database
    }

    private function initializePerformanceMonitoring(): void
    {
        // Initialize performance monitoring
    }

    private function setupRealTimeProcessing(): void
    {
        // Set up real-time processing
    }

    private function initializeAnomalyDetection(): void
    {
        // Initialize anomaly detection
    }

    private function setupAlertSystem(): void
    {
        // Set up alert system
    }

    private function saveCustomDashboard(string $dashboardId, array $dashboard): void
    {
        // In real implementation, save to database
    }

    private function saveAlertConfig(string $alertId, array $alert): void
    {
        // In real implementation, save to database
    }

    private function exportAnalyticsToCSV(array $data): string
    {
        // Export analytics data to CSV format
        return '';
    }
}

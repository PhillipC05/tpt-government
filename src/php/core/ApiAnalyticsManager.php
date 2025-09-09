<?php
/**
 * TPT Government Platform - API Analytics Manager
 *
 * Comprehensive API analytics and monitoring system
 */

class ApiAnalyticsManager
{
    private $logger;
    private $cache;
    private $database;
    private $config;

    /**
     * Analytics data types
     */
    const TYPE_REQUEST = 'request';
    const TYPE_RESPONSE = 'response';
    const TYPE_ERROR = 'error';
    const TYPE_PERFORMANCE = 'performance';

    /**
     * Time granularities
     */
    const GRANULARITY_MINUTE = 'minute';
    const GRANULARITY_HOUR = 'hour';
    const GRANULARITY_DAY = 'day';
    const GRANULARITY_WEEK = 'week';
    const GRANULARITY_MONTH = 'month';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $cache, $database, $config = [])
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->database = $database;
        $this->config = array_merge([
            'cache_prefix' => 'api_analytics:',
            'retention_days' => 90,
            'real_time_window' => 300, // 5 minutes
            'batch_size' => 100,
            'enable_real_time' => true,
            'enable_historical' => true,
            'alert_thresholds' => [
                'error_rate' => 5.0, // 5%
                'response_time' => 5000, // 5 seconds
                'requests_per_minute' => 1000
            ]
        ], $config);

        $this->initializeAnalyticsTables();
    }

    /**
     * Initialize analytics database tables
     */
    private function initializeAnalyticsTables()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS api_analytics_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL,
                method VARCHAR(10) NOT NULL,
                endpoint VARCHAR(500) NOT NULL,
                version VARCHAR(10),
                user_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                response_code INT,
                response_time INT,
                request_size INT,
                response_size INT,
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_endpoint (endpoint(100)),
                INDEX idx_user_id (user_id),
                INDEX idx_response_code (response_code)
            );

            CREATE TABLE IF NOT EXISTS api_analytics_summary (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                hour TINYINT,
                endpoint VARCHAR(500) NOT NULL,
                method VARCHAR(10) NOT NULL,
                total_requests INT DEFAULT 0,
                successful_requests INT DEFAULT 0,
                error_requests INT DEFAULT 0,
                avg_response_time INT DEFAULT 0,
                min_response_time INT DEFAULT 0,
                max_response_time INT DEFAULT 0,
                total_request_size BIGINT DEFAULT 0,
                total_response_size BIGINT DEFAULT 0,
                unique_users INT DEFAULT 0,
                unique_ips INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_summary (date, hour, endpoint(100), method),
                INDEX idx_date_endpoint (date, endpoint(100))
            );

            CREATE TABLE IF NOT EXISTS api_analytics_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alert_type VARCHAR(50) NOT NULL,
                severity VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                endpoint VARCHAR(500),
                threshold_value DECIMAL(10,2),
                actual_value DECIMAL(10,2),
                metadata JSON,
                resolved BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                INDEX idx_type_severity (alert_type, severity),
                INDEX idx_resolved (resolved)
            );
        ";

        try {
            $this->database->query($sql);
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize analytics tables', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record API request analytics
     */
    public function recordRequest(Request $request, Response $response = null, $startTime = null)
    {
        $endTime = microtime(true);
        $startTime = $startTime ?? $endTime;

        $analyticsData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $request->getMethod(),
            'endpoint' => $request->getPath(),
            'version' => $request->getAttribute('api_version') ?? 'v1',
            'user_id' => $this->getUserIdFromRequest($request),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->getHeader('User-Agent'),
            'response_code' => $response ? $response->getStatusCode() : 500,
            'response_time' => (int)(($endTime - $startTime) * 1000), // milliseconds
            'request_size' => strlen($request->getBody() ?? ''),
            'response_size' => $response ? strlen($response->getBody() ?? '') : 0,
            'error_message' => null
        ];

        // Store in real-time cache for immediate access
        if ($this->config['enable_real_time']) {
            $this->storeRealTimeAnalytics($analyticsData);
        }

        // Store in database for historical analysis
        if ($this->config['enable_historical']) {
            $this->storeHistoricalAnalytics($analyticsData);
        }

        // Check for alerts
        $this->checkForAlerts($analyticsData);

        return $analyticsData;
    }

    /**
     * Store real-time analytics in cache
     */
    private function storeRealTimeAnalytics($data)
    {
        $key = $this->config['cache_prefix'] . 'realtime:' . date('Y-m-d-H-i');

        $existing = $this->cache->get($key, [
            'requests' => 0,
            'errors' => 0,
            'avg_response_time' => 0,
            'by_endpoint' => []
        ]);

        $existing['requests']++;
        if ($data['response_code'] >= 400) {
            $existing['errors']++;
        }

        // Update average response time
        $existing['avg_response_time'] = (($existing['avg_response_time'] * ($existing['requests'] - 1)) + $data['response_time']) / $existing['requests'];

        // Track by endpoint
        $endpoint = $data['method'] . ' ' . $data['endpoint'];
        if (!isset($existing['by_endpoint'][$endpoint])) {
            $existing['by_endpoint'][$endpoint] = ['count' => 0, 'avg_time' => 0];
        }
        $existing['by_endpoint'][$endpoint]['count']++;
        $existing['by_endpoint'][$endpoint]['avg_time'] = (($existing['by_endpoint'][$endpoint]['avg_time'] * ($existing['by_endpoint'][$endpoint]['count'] - 1)) + $data['response_time']) / $existing['by_endpoint'][$endpoint]['count'];

        $this->cache->set($key, $existing, $this->config['real_time_window']);
    }

    /**
     * Store historical analytics in database
     */
    private function storeHistoricalAnalytics($data)
    {
        try {
            $sql = "INSERT INTO api_analytics_requests
                    (timestamp, method, endpoint, version, user_id, ip_address, user_agent,
                     response_code, response_time, request_size, response_size, error_message)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->database->query($sql, [
                $data['timestamp'],
                $data['method'],
                $data['endpoint'],
                $data['version'],
                $data['user_id'],
                $data['ip_address'],
                $data['user_agent'],
                $data['response_code'],
                $data['response_time'],
                $data['request_size'],
                $data['response_size'],
                $data['error_message']
            ]);

            // Update summary table
            $this->updateAnalyticsSummary($data);

        } catch (Exception $e) {
            $this->logger->error('Failed to store historical analytics', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * Update analytics summary table
     */
    private function updateAnalyticsSummary($data)
    {
        $date = date('Y-m-d', strtotime($data['timestamp']));
        $hour = (int)date('H', strtotime($data['timestamp']));

        $sql = "INSERT INTO api_analytics_summary
                (date, hour, endpoint, method, total_requests, successful_requests, error_requests,
                 avg_response_time, min_response_time, max_response_time, total_request_size, total_response_size)
                VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_requests = total_requests + 1,
                    successful_requests = successful_requests + VALUES(successful_requests),
                    error_requests = error_requests + VALUES(error_requests),
                    avg_response_time = ((avg_response_time * (total_requests - 1)) + VALUES(avg_response_time)) / total_requests,
                    min_response_time = LEAST(min_response_time, VALUES(min_response_time)),
                    max_response_time = GREATEST(max_response_time, VALUES(max_response_time)),
                    total_request_size = total_request_size + VALUES(total_request_size),
                    total_response_size = total_response_size + VALUES(total_response_size)";

        $isSuccess = $data['response_code'] < 400 ? 1 : 0;
        $isError = $data['response_code'] >= 400 ? 1 : 0;

        $this->database->query($sql, [
            $date,
            $hour,
            $data['endpoint'],
            $data['method'],
            $isSuccess,
            $isError,
            $data['response_time'],
            $data['response_time'],
            $data['response_time'],
            $data['request_size'],
            $data['response_size']
        ]);
    }

    /**
     * Check for alerts based on analytics data
     */
    private function checkForAlerts($data)
    {
        // Check error rate
        $errorRate = $this->calculateErrorRate($data['endpoint']);
        if ($errorRate > $this->config['alert_thresholds']['error_rate']) {
            $this->createAlert('high_error_rate', 'warning',
                "High error rate detected: {$errorRate}% for endpoint {$data['endpoint']}",
                $data['endpoint'], $this->config['alert_thresholds']['error_rate'], $errorRate);
        }

        // Check response time
        if ($data['response_time'] > $this->config['alert_thresholds']['response_time']) {
            $this->createAlert('slow_response', 'warning',
                "Slow response time: {$data['response_time']}ms for endpoint {$data['endpoint']}",
                $data['endpoint'], $this->config['alert_thresholds']['response_time'], $data['response_time']);
        }

        // Check request volume
        $requestCount = $this->getRecentRequestCount($data['endpoint']);
        if ($requestCount > $this->config['alert_thresholds']['requests_per_minute']) {
            $this->createAlert('high_traffic', 'info',
                "High traffic detected: {$requestCount} requests/minute for endpoint {$data['endpoint']}",
                $data['endpoint'], $this->config['alert_thresholds']['requests_per_minute'], $requestCount);
        }
    }

    /**
     * Calculate error rate for endpoint
     */
    private function calculateErrorRate($endpoint)
    {
        $sql = "SELECT
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as errors,
                    COUNT(*) as total
                FROM api_analytics_requests
                WHERE endpoint = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";

        $result = $this->database->query($sql, [$endpoint])->fetch();

        if ($result && $result['total'] > 0) {
            return ($result['errors'] / $result['total']) * 100;
        }

        return 0;
    }

    /**
     * Get recent request count for endpoint
     */
    private function getRecentRequestCount($endpoint)
    {
        $sql = "SELECT COUNT(*) as count
                FROM api_analytics_requests
                WHERE endpoint = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";

        $result = $this->database->query($sql, [$endpoint])->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Create an alert
     */
    private function createAlert($type, $severity, $message, $endpoint = null, $threshold = null, $actual = null)
    {
        $sql = "INSERT INTO api_analytics_alerts
                (alert_type, severity, message, endpoint, threshold_value, actual_value, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $metadata = json_encode([
            'timestamp' => date('c'),
            'endpoint' => $endpoint
        ]);

        $this->database->query($sql, [
            $type,
            $severity,
            $message,
            $endpoint,
            $threshold,
            $actual,
            $metadata
        ]);

        $this->logger->warning('Analytics alert created', [
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'endpoint' => $endpoint
        ]);
    }

    /**
     * Get user ID from request
     */
    private function getUserIdFromRequest(Request $request)
    {
        // Try to get from session
        $session = $request->getSession();
        if ($session && $session->has('user_id')) {
            return $session->get('user_id');
        }

        // Try to get from JWT token or other auth methods
        $authHeader = $request->getHeader('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            // In a real implementation, you'd decode the JWT and extract user ID
            return $this->decodeUserIdFromToken($matches[1]);
        }

        return null;
    }

    /**
     * Decode user ID from token (placeholder)
     */
    private function decodeUserIdFromToken($token)
    {
        // This would be implemented based on your JWT/token system
        return null;
    }

    /**
     * Get analytics data
     */
    public function getAnalytics($endpoint = null, $startDate = null, $endDate = null, $granularity = self::GRANULARITY_HOUR)
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $endDate ?? date('Y-m-d');

        $sql = "SELECT
                    DATE(timestamp) as date,
                    HOUR(timestamp) as hour,
                    endpoint,
                    method,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN response_code < 400 THEN 1 ELSE 0 END) as successful_requests,
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as error_requests,
                    AVG(response_time) as avg_response_time,
                    MIN(response_time) as min_response_time,
                    MAX(response_time) as max_response_time,
                    SUM(request_size) as total_request_size,
                    SUM(response_size) as total_response_size,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM api_analytics_requests
                WHERE timestamp BETWEEN ? AND ?
                " . ($endpoint ? "AND endpoint = ?" : "") . "
                GROUP BY DATE(timestamp), HOUR(timestamp)" . ($endpoint ? ", endpoint" : "") . ", method
                ORDER BY date DESC, hour DESC";

        $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        if ($endpoint) {
            $params[] = $endpoint;
        }

        $results = $this->database->query($sql, $params)->fetchAll();

        return $this->aggregateResults($results, $granularity);
    }

    /**
     * Aggregate results by granularity
     */
    private function aggregateResults($results, $granularity)
    {
        $aggregated = [];

        foreach ($results as $row) {
            $key = $row['date'];
            if ($granularity === self::GRANULARITY_HOUR) {
                $key .= '-' . str_pad($row['hour'], 2, '0', STR_PAD_LEFT);
            }

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'period' => $key,
                    'total_requests' => 0,
                    'successful_requests' => 0,
                    'error_requests' => 0,
                    'avg_response_time' => 0,
                    'min_response_time' => PHP_INT_MAX,
                    'max_response_time' => 0,
                    'total_request_size' => 0,
                    'total_response_size' => 0,
                    'unique_users' => 0,
                    'unique_ips' => 0,
                    'endpoints' => []
                ];
            }

            $aggregated[$key]['total_requests'] += $row['total_requests'];
            $aggregated[$key]['successful_requests'] += $row['successful_requests'];
            $aggregated[$key]['error_requests'] += $row['error_requests'];
            $aggregated[$key]['total_request_size'] += $row['total_request_size'];
            $aggregated[$key]['total_response_size'] += $row['total_response_size'];
            $aggregated[$key]['unique_users'] = max($aggregated[$key]['unique_users'], $row['unique_users']);
            $aggregated[$key]['unique_ips'] = max($aggregated[$key]['unique_ips'], $row['unique_ips']);

            // Calculate weighted average response time
            $weight = $row['total_requests'] / $aggregated[$key]['total_requests'];
            $aggregated[$key]['avg_response_time'] += $row['avg_response_time'] * $weight;

            $aggregated[$key]['min_response_time'] = min($aggregated[$key]['min_response_time'], $row['min_response_time']);
            $aggregated[$key]['max_response_time'] = max($aggregated[$key]['max_response_time'], $row['max_response_time']);

            // Track endpoint data
            $endpointKey = $row['method'] . ' ' . $row['endpoint'];
            $aggregated[$key]['endpoints'][$endpointKey] = [
                'method' => $row['method'],
                'endpoint' => $row['endpoint'],
                'requests' => $row['total_requests'],
                'success_rate' => $row['total_requests'] > 0 ? ($row['successful_requests'] / $row['total_requests']) * 100 : 0,
                'avg_response_time' => $row['avg_response_time']
            ];
        }

        // Fix min_response_time for periods with no data
        foreach ($aggregated as &$period) {
            if ($period['min_response_time'] === PHP_INT_MAX) {
                $period['min_response_time'] = 0;
            }
        }

        return array_values($aggregated);
    }

    /**
     * Get real-time analytics
     */
    public function getRealTimeAnalytics($minutes = 5)
    {
        $analytics = [];

        for ($i = 0; $i < $minutes; $i++) {
            $timeKey = date('Y-m-d-H-i', strtotime("-{$i} minutes"));
            $key = $this->config['cache_prefix'] . 'realtime:' . $timeKey;

            $data = $this->cache->get($key);
            if ($data) {
                $analytics[$timeKey] = $data;
            }
        }

        return $analytics;
    }

    /**
     * Get alerts
     */
    public function getAlerts($resolved = false, $limit = 50)
    {
        $sql = "SELECT * FROM api_analytics_alerts
                WHERE resolved = ?
                ORDER BY created_at DESC
                LIMIT ?";

        return $this->database->query($sql, [$resolved ? 1 : 0, $limit])->fetchAll();
    }

    /**
     * Resolve alert
     */
    public function resolveAlert($alertId)
    {
        $sql = "UPDATE api_analytics_alerts
                SET resolved = TRUE, resolved_at = NOW()
                WHERE id = ?";

        $this->database->query($sql, [$alertId]);

        $this->logger->info('Alert resolved', ['alert_id' => $alertId]);
    }

    /**
     * Clean up old analytics data
     */
    public function cleanupOldData()
    {
        $retentionDate = date('Y-m-d', strtotime("-{$this->config['retention_days']} days"));

        // Delete old request data
        $sql1 = "DELETE FROM api_analytics_requests WHERE DATE(timestamp) < ?";
        $this->database->query($sql1, [$retentionDate]);

        // Delete old summary data
        $sql2 = "DELETE FROM api_analytics_summary WHERE date < ?";
        $this->database->query($sql2, [$retentionDate]);

        // Delete old alerts
        $sql3 = "DELETE FROM api_analytics_alerts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND resolved = TRUE";
        $this->database->query($sql3);

        $this->logger->info('Old analytics data cleaned up', [
            'retention_days' => $this->config['retention_days'],
            'retention_date' => $retentionDate
        ]);
    }

    /**
     * Get analytics dashboard data
     */
    public function getDashboardData()
    {
        $data = [
            'real_time' => $this->getRealTimeAnalytics(5),
            'daily_stats' => $this->getAnalytics(null, date('Y-m-d'), date('Y-m-d')),
            'weekly_stats' => $this->getAnalytics(null, date('Y-m-d', strtotime('-7 days')), date('Y-m-d')),
            'active_alerts' => $this->getAlerts(false, 10),
            'top_endpoints' => $this->getTopEndpoints(),
            'error_rate_trend' => $this->getErrorRateTrend()
        ];

        return $data;
    }

    /**
     * Get top endpoints by request count
     */
    private function getTopEndpoints($limit = 10)
    {
        $sql = "SELECT
                    CONCAT(method, ' ', endpoint) as endpoint_key,
                    method,
                    endpoint,
                    COUNT(*) as total_requests,
                    AVG(response_time) as avg_response_time,
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) / COUNT(*) * 100 as error_rate
                FROM api_analytics_requests
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                GROUP BY method, endpoint
                ORDER BY total_requests DESC
                LIMIT ?";

        $results = $this->database->query($sql, [$limit])->fetchAll();

        return array_map(function($row) {
            return [
                'endpoint' => $row['endpoint_key'],
                'method' => $row['method'],
                'path' => $row['endpoint'],
                'requests' => $row['total_requests'],
                'avg_response_time' => round($row['avg_response_time'], 2),
                'error_rate' => round($row['error_rate'], 2)
            ];
        }, $results);
    }

    /**
     * Get error rate trend
     */
    private function getErrorRateTrend($hours = 24)
    {
        $sql = "SELECT
                    DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as hour,
                    SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) / COUNT(*) * 100 as error_rate,
                    COUNT(*) as total_requests
                FROM api_analytics_requests
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00')
                ORDER BY hour ASC";

        $results = $this->database->query($sql, [$hours])->fetchAll();

        return array_map(function($row) {
            return [
                'hour' => $row['hour'],
                'error_rate' => round($row['error_rate'], 2),
                'total_requests' => $row['total_requests']
            ];
        }, $results);
    }

    /**
     * Export analytics data
     */
    public function exportAnalytics($format = 'json', $startDate = null, $endDate = null)
    {
        $data = $this->getAnalytics(null, $startDate, $endDate);

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->convertToCsv($data);
            case 'xml':
                return $this->convertToXml($data);
            default:
                throw new Exception("Unsupported export format: {$format}");
        }
    }

    /**
     * Convert data to CSV
     */
    private function convertToCsv($data)
    {
        if (empty($data)) {
            return '';
        }

        $csv = "Period,Total Requests,Successful Requests,Error Requests,Avg Response Time,Min Response Time,Max Response Time,Unique Users,Unique IPs\n";

        foreach ($data as $row) {
            $csv .= sprintf(
                "%s,%d,%d,%d,%.2f,%d,%d,%d,%d\n",
                $row['period'],
                $row['total_requests'],
                $row['successful_requests'],
                $row['error_requests'],
                $row['avg_response_time'],
                $row['min_response_time'],
                $row['max_response_time'],
                $row['unique_users'],
                $row['unique_ips']
            );
        }

        return $csv;
    }

    /**
     * Convert data to XML
     */
    private function convertToXml($data)
    {
        $xml = new SimpleXMLElement('<analytics/>');

        foreach ($data as $row) {
            $period = $xml->addChild('period');
            $period->addAttribute('date', $row['period']);

            foreach ($row as $key => $value) {
                if ($key !== 'period' && $key !== 'endpoints') {
                    $period->addChild($key, $value);
                }
            }

            if (isset($row['endpoints'])) {
                $endpoints = $period->addChild('endpoints');
                foreach ($row['endpoints'] as $endpoint) {
                    $ep = $endpoints->addChild('endpoint');
                    foreach ($endpoint as $epKey => $epValue) {
                        $ep->addChild($epKey, $epValue);
                    }
                }
            }
        }

        return $xml->asXML();
    }
}

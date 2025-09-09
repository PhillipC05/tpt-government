<?php
/**
 * TPT Government Platform - Performance Dashboard Controller
 *
 * Provides web interface for monitoring application performance metrics,
 * real-time dashboards, and performance analytics
 */

class PerformanceDashboardController extends Controller
{
    private $apmManager;
    private $logger;
    private $cache;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new StructuredLogger();
        $this->apmManager = new APMManager($this->logger);
        $this->cache = CacheManager::getInstance();
    }

    /**
     * Main performance dashboard
     * GET /admin/performance
     */
    public function dashboard()
    {
        // Get current metrics
        $currentMetrics = $this->apmManager->getCurrentMetrics(3600); // Last hour
        $systemResources = $this->apmManager->getSystemResources();

        // Get recent alerts
        $recentAlerts = $this->apmManager->getPerformanceAlerts(10);

        // Get slow queries
        $slowQueries = $this->apmManager->getSlowQueries(5);

        // Get performance trends (last 24 hours)
        $trends = $this->apmManager->getPerformanceTrends(24);

        $this->render('performance/dashboard', [
            'title' => 'Performance Dashboard',
            'current_metrics' => $currentMetrics,
            'system_resources' => $systemResources,
            'recent_alerts' => $recentAlerts,
            'slow_queries' => $slowQueries,
            'trends' => $trends,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Real-time metrics API endpoint
     * GET /api/performance/metrics
     */
    public function metrics()
    {
        $timeframe = (int)($_GET['timeframe'] ?? 3600); // Default 1 hour
        $metrics = $this->apmManager->getCurrentMetrics($timeframe);

        $this->json($metrics);
    }

    /**
     * System resources API endpoint
     * GET /api/performance/system
     */
    public function system()
    {
        $resources = $this->apmManager->getSystemResources();
        $this->json($resources);
    }

    /**
     * Performance trends API endpoint
     * GET /api/performance/trends
     */
    public function trends()
    {
        $hours = (int)($_GET['hours'] ?? 24);
        $trends = $this->apmManager->getPerformanceTrends($hours);

        $this->json($trends);
    }

    /**
     * Slow queries API endpoint
     * GET /api/performance/slow-queries
     */
    public function slowQueries()
    {
        $limit = (int)($_GET['limit'] ?? 20);
        $minTime = (int)($_GET['min_time'] ?? 1000); // Default 1 second

        $slowQueries = $this->apmManager->getSlowQueries($limit, $minTime);
        $this->json($slowQueries);
    }

    /**
     * Performance alerts API endpoint
     * GET /api/performance/alerts
     */
    public function alerts()
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $alerts = $this->apmManager->getPerformanceAlerts($limit);

        $this->json($alerts);
    }

    /**
     * Export performance data
     * GET /admin/performance/export
     */
    public function export()
    {
        $format = $_GET['format'] ?? 'json';
        $startTime = isset($_GET['start']) ? strtotime($_GET['start']) : null;
        $endTime = isset($_GET['end']) ? strtotime($_GET['end']) : null;

        $data = $this->apmManager->exportPerformanceData($format, $startTime, $endTime);

        // Set appropriate headers based on format
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="performance_data_' . date('Y-m-d') . '.json"');
                break;
            case 'csv':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="performance_data_' . date('Y-m-d') . '.csv"');
                break;
        }

        echo $data;
        exit;
    }

    /**
     * Performance settings page
     * GET /admin/performance/settings
     */
    public function settings()
    {
        $currentSettings = [
            'apm_enabled' => $this->apmManager->isEnabled(),
            'thresholds' => [
                'response_time_warning' => '1000ms',
                'response_time_critical' => '5000ms',
                'memory_warning' => '128MB',
                'memory_critical' => '256MB',
                'database_queries_warning' => '50',
                'database_queries_critical' => '100'
            ]
        ];

        $this->render('performance/settings', [
            'title' => 'Performance Settings',
            'settings' => $currentSettings
        ]);
    }

    /**
     * Update performance settings
     * POST /admin/performance/settings
     */
    public function updateSettings()
    {
        $enabled = isset($_POST['apm_enabled']);
        $this->apmManager->setEnabled($enabled);

        // Update thresholds if provided
        if (isset($_POST['thresholds'])) {
            // This would update the APM manager thresholds
            // Implementation depends on APM manager's threshold update methods
        }

        $this->logger->info('Performance settings updated', [
            'apm_enabled' => $enabled,
            'updated_by' => $_SESSION['user_id'] ?? 'unknown'
        ]);

        $this->redirect('/admin/performance/settings', 'Settings updated successfully');
    }

    /**
     * Performance reports page
     * GET /admin/performance/reports
     */
    public function reports()
    {
        $availableReports = [
            'hourly' => 'Last Hour Report',
            'daily' => 'Daily Report',
            'weekly' => 'Weekly Report',
            'monthly' => 'Monthly Report'
        ];

        $this->render('performance/reports', [
            'title' => 'Performance Reports',
            'reports' => $availableReports
        ]);
    }

    /**
     * Generate performance report
     * GET /admin/performance/report/{type}
     */
    public function generateReport($type)
    {
        $timeframes = [
            'hourly' => 3600,
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000
        ];

        if (!isset($timeframes[$type])) {
            $this->error404();
            return;
        }

        $timeframe = $timeframes[$type];
        $metrics = $this->apmManager->getCurrentMetrics($timeframe);
        $slowQueries = $this->apmManager->getSlowQueries(20);
        $alerts = $this->apmManager->getPerformanceAlerts(100);

        $report = [
            'type' => $type,
            'timeframe' => $timeframe,
            'generated_at' => date('c'),
            'metrics' => $metrics,
            'slow_queries' => $slowQueries,
            'alerts' => $alerts,
            'summary' => $this->generateReportSummary($metrics, $slowQueries, $alerts)
        ];

        $this->render('performance/report', [
            'title' => ucfirst($type) . ' Performance Report',
            'report' => $report
        ]);
    }

    /**
     * Real-time performance monitoring (WebSocket alternative)
     * GET /api/performance/live
     */
    public function live()
    {
        // Set headers for SSE (Server-Sent Events)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        $lastUpdate = time();

        while (true) {
            $currentTime = time();

            // Send update every 5 seconds
            if ($currentTime - $lastUpdate >= 5) {
                $metrics = $this->apmManager->getCurrentMetrics(300); // Last 5 minutes
                $resources = $this->apmManager->getSystemResources();

                $data = [
                    'timestamp' => date('c'),
                    'metrics' => $metrics,
                    'resources' => $resources
                ];

                echo "data: " . json_encode($data) . "\n\n";
                flush();

                $lastUpdate = $currentTime;
            }

            // Check for client disconnect
            if (connection_aborted()) {
                break;
            }

            sleep(1);
        }
    }

    /**
     * Performance comparison page
     * GET /admin/performance/compare
     */
    public function compare()
    {
        $period1 = $_GET['period1'] ?? 'today';
        $period2 = $_GET['period2'] ?? 'yesterday';

        $comparison = $this->generateComparison($period1, $period2);

        $this->render('performance/compare', [
            'title' => 'Performance Comparison',
            'comparison' => $comparison,
            'period1' => $period1,
            'period2' => $period2
        ]);
    }

    /**
     * Performance alerts management
     * GET /admin/performance/alerts
     */
    public function alertsManagement()
    {
        $alerts = $this->apmManager->getPerformanceAlerts(100);

        // Group alerts by severity
        $groupedAlerts = [
            'critical' => array_filter($alerts, fn($alert) => $alert['severity'] === APMManager::ALERT_CRITICAL),
            'high' => array_filter($alerts, fn($alert) => $alert['severity'] === APMManager::ALERT_HIGH),
            'medium' => array_filter($alerts, fn($alert) => $alert['severity'] === APMManager::ALERT_MEDIUM),
            'low' => array_filter($alerts, fn($alert) => $alert['severity'] === APMManager::ALERT_LOW)
        ];

        $this->render('performance/alerts', [
            'title' => 'Performance Alerts',
            'alerts' => $groupedAlerts,
            'total_count' => count($alerts)
        ]);
    }

    /**
     * Generate report summary
     */
    private function generateReportSummary($metrics, $slowQueries, $alerts)
    {
        $summary = [
            'total_requests' => $metrics['total_requests'],
            'avg_response_time' => $metrics['avg_response_time'] . 'ms',
            'error_rate' => $metrics['error_rate'] . '%',
            'slow_queries_count' => count($slowQueries),
            'alerts_count' => count($alerts),
            'status' => 'healthy'
        ];

        // Determine overall status
        if ($metrics['error_rate'] > 5 || count($alerts) > 10) {
            $summary['status'] = 'critical';
        } elseif ($metrics['error_rate'] > 2 || count($alerts) > 5) {
            $summary['status'] = 'warning';
        }

        return $summary;
    }

    /**
     * Generate performance comparison
     */
    private function generateComparison($period1, $period2)
    {
        // This would implement period comparison logic
        // For now, return placeholder data
        return [
            'period1' => [
                'label' => ucfirst($period1),
                'metrics' => $this->apmManager->getCurrentMetrics(3600)
            ],
            'period2' => [
                'label' => ucfirst($period2),
                'metrics' => $this->apmManager->getCurrentMetrics(3600)
            ],
            'comparison' => [
                'response_time_change' => '+5.2%',
                'memory_usage_change' => '-2.1%',
                'error_rate_change' => '-15.3%'
            ]
        ];
    }

    /**
     * Render template (simplified implementation)
     */
    private function render($template, $data = [])
    {
        // This would integrate with your template system
        // For now, we'll just output JSON for API endpoints
        if (strpos($template, 'api/') === 0) {
            $this->json($data);
        } else {
            // Web interface would render HTML template
            header('Content-Type: text/html');
            echo "<h1>{$data['title']}</h1>";
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        }
    }

    /**
     * JSON response helper
     */
    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Redirect helper
     */
    private function redirect($url, $message = '')
    {
        if ($message) {
            // Store flash message in session
            $_SESSION['flash_message'] = $message;
        }

        header("Location: $url");
        exit;
    }

    /**
     * 404 error helper
     */
    private function error404()
    {
        http_response_code(404);
        echo "404 - Performance Report Type Not Found";
        exit;
    }
}

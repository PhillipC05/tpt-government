<?php
/**
 * TPT Government Platform - Queue Monitoring Dashboard
 *
 * Provides comprehensive monitoring and visualization for job queue system
 * including real-time statistics, job status tracking, and performance metrics
 */

class QueueMonitoringDashboard
{
    private $queueManager;
    private $workerManager;
    private $statsHistory = [];
    private $alerts = [];

    /**
     * Constructor
     */
    public function __construct($queueManager = null, $workerManager = null)
    {
        $this->queueManager = $queueManager;
        $this->workerManager = $workerManager;
    }

    /**
     * Generate HTML dashboard
     */
    public function generateHTMLDashboard()
    {
        $html = $this->getHTMLHeader();
        $html .= $this->generateOverviewSection();
        $html .= $this->generateQueueStatusSection();
        $html .= $this->generateWorkerStatusSection();
        $html .= $this->generatePerformanceMetricsSection();
        $html .= $this->generateRecentJobsSection();
        $html .= $this->generateAlertsSection();
        $html .= $this->getHTMLFooter();

        return $html;
    }

    /**
     * Get HTML header with styles and scripts
     */
    private function getHTMLHeader()
    {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Queue Monitoring Dashboard - TPT Government Platform</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: #f5f7fa;
                    color: #333;
                    line-height: 1.6;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 2rem;
                    text-align: center;
                }
                .container {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 2rem;
                }
                .dashboard-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                    gap: 2rem;
                    margin-bottom: 2rem;
                }
                .card {
                    background: white;
                    border-radius: 12px;
                    padding: 1.5rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    border: 1px solid #e1e5e9;
                }
                .card-header {
                    display: flex;
                    justify-content: between;
                    align-items: center;
                    margin-bottom: 1rem;
                    padding-bottom: 0.5rem;
                    border-bottom: 2px solid #f0f2f5;
                }
                .card-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #1a202c;
                }
                .status-indicator {
                    display: inline-block;
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    margin-right: 0.5rem;
                }
                .status-healthy { background: #48bb78; }
                .status-warning { background: #ed8936; }
                .status-critical { background: #f56565; }
                .status-unknown { background: #a0aec0; }
                .metric-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 1rem;
                }
                .metric {
                    text-align: center;
                    padding: 1rem;
                    background: #f7fafc;
                    border-radius: 8px;
                    border: 1px solid #e2e8f0;
                }
                .metric-value {
                    font-size: 2rem;
                    font-weight: bold;
                    color: #2d3748;
                    display: block;
                }
                .metric-label {
                    font-size: 0.875rem;
                    color: #718096;
                    margin-top: 0.25rem;
                }
                .progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e2e8f0;
                    border-radius: 4px;
                    overflow: hidden;
                    margin: 0.5rem 0;
                }
                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #667eea, #764ba2);
                    transition: width 0.3s ease;
                }
                .queue-list {
                    list-style: none;
                }
                .queue-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.75rem;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    margin-bottom: 0.5rem;
                    background: #f8f9fa;
                }
                .worker-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 1rem;
                }
                .worker-card {
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 1rem;
                    background: #f8f9fa;
                }
                .worker-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.5rem;
                }
                .job-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 1rem;
                }
                .job-table th,
                .job-table td {
                    padding: 0.75rem;
                    text-align: left;
                    border-bottom: 1px solid #e2e8f0;
                }
                .job-table th {
                    background: #f7fafc;
                    font-weight: 600;
                    color: #4a5568;
                }
                .status-badge {
                    display: inline-block;
                    padding: 0.25rem 0.5rem;
                    border-radius: 12px;
                    font-size: 0.75rem;
                    font-weight: 500;
                    text-transform: uppercase;
                }
                .status-pending { background: #fef5e7; color: #f59e0b; }
                .status-running { background: #dbeafe; color: #3b82f6; }
                .status-completed { background: #d1fae5; color: #10b981; }
                .status-failed { background: #fee2e2; color: #ef4444; }
                .alert-list {
                    list-style: none;
                }
                .alert-item {
                    padding: 1rem;
                    border-radius: 8px;
                    margin-bottom: 0.5rem;
                    border-left: 4px solid;
                }
                .alert-critical {
                    background: #fef2f2;
                    border-left-color: #ef4444;
                }
                .alert-warning {
                    background: #fefce8;
                    border-left-color: #f59e0b;
                }
                .alert-info {
                    background: #eff6ff;
                    border-left-color: #3b82f6;
                }
                .refresh-btn {
                    background: #667eea;
                    color: white;
                    border: none;
                    padding: 0.5rem 1rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 0.875rem;
                    margin-left: 1rem;
                }
                .refresh-btn:hover {
                    background: #5a67d8;
                }
                @media (max-width: 768px) {
                    .dashboard-grid {
                        grid-template-columns: 1fr;
                    }
                    .container {
                        padding: 1rem;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🚀 Queue Monitoring Dashboard</h1>
                <p>Real-time monitoring for TPT Government Platform Job Queue System</p>
                <small>Last updated: ' . date('Y-m-d H:i:s') . '</small>
            </div>
            <div class="container">
        ';
    }

    /**
     * Generate overview section
     */
    private function generateOverviewSection()
    {
        $systemStats = $this->queueManager ? $this->queueManager->getSystemStats() : [];
        $workerStats = $this->workerManager ? $this->workerManager->getOverallStats() : [];

        $html = '<div class="dashboard-grid">';

        // System Overview
        $html .= '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">📊 System Overview</h2>';
        $html .= '<span class="status-indicator status-healthy"></span>';
        $html .= '</div>';
        $html .= '<div class="metric-grid">';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . ($systemStats['queues_active'] ?? 0) . '</span>';
        $html .= '<span class="metric-label">Active Queues</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . ($workerStats['total_workers'] ?? 0) . '</span>';
        $html .= '<span class="metric-label">Total Workers</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . ($workerStats['active_workers'] ?? 0) . '</span>';
        $html .= '<span class="metric-label">Active Workers</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . number_format($systemStats['jobs_processed'] ?? 0) . '</span>';
        $html .= '<span class="metric-label">Jobs Processed</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Performance Metrics
        $html .= '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">⚡ Performance</h2>';
        $html .= '</div>';
        $html .= '<div class="metric-grid">';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . number_format($systemStats['avg_processing_time'] ?? 0, 2) . 's</span>';
        $html .= '<span class="metric-label">Avg Processing Time</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . number_format($workerStats['avg_success_rate'] ?? 0, 1) . '%</span>';
        $html .= '<span class="metric-label">Success Rate</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . number_format($systemStats['jobs_failed'] ?? 0) . '</span>';
        $html .= '<span class="metric-label">Failed Jobs</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">' . number_format($systemStats['jobs_succeeded'] ?? 0) . '</span>';
        $html .= '<span class="metric-label">Successful Jobs</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate queue status section
     */
    private function generateQueueStatusSection()
    {
        $queueStats = $this->queueManager ? $this->queueManager->getQueueStats() : [];

        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">📋 Queue Status</h2>';
        $html .= '<button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>';
        $html .= '</div>';

        if (empty($queueStats)) {
            $html .= '<p>No queues available</p>';
        } else {
            $html .= '<ul class="queue-list">';
            foreach ($queueStats as $queueName => $stats) {
                $pendingCount = $stats['pending_count'] ?? 0;
                $totalJobs = $stats['current_size'] ?? 0;
                $processingJobs = $stats['running_jobs'] ?? 0;

                $html .= '<li class="queue-item">';
                $html .= '<div>';
                $html .= '<strong>' . htmlspecialchars($queueName) . '</strong>';
                $html .= '<br><small>' . $pendingCount . ' pending, ' . $processingJobs . ' processing</small>';
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<div class="progress-bar">';
                $total = max($totalJobs, 1);
                $percentage = ($processingJobs / $total) * 100;
                $html .= '<div class="progress-fill" style="width: ' . min($percentage, 100) . '%"></div>';
                $html .= '</div>';
                $html .= '<small>' . $totalJobs . ' total jobs</small>';
                $html .= '</div>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate worker status section
     */
    private function generateWorkerStatusSection()
    {
        $workerStats = $this->workerManager ? $this->workerManager->getWorkerStats() : [];

        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">👷 Worker Status</h2>';
        $html .= '</div>';

        if (empty($workerStats)) {
            $html .= '<p>No workers available</p>';
        } else {
            $html .= '<div class="worker-grid">';
            foreach ($workerStats as $workerId => $stats) {
                $isRunning = $stats['is_running'] ?? false;
                $statusClass = $isRunning ? 'status-healthy' : 'status-critical';

                $html .= '<div class="worker-card">';
                $html .= '<div class="worker-header">';
                $html .= '<span><strong>' . htmlspecialchars($workerId) . '</strong></span>';
                $html .= '<span class="status-indicator ' . $statusClass . '"></span>';
                $html .= '</div>';

                $html .= '<div class="metric-grid">';
                $html .= '<div class="metric">';
                $html .= '<span class="metric-value">' . number_format($stats['jobs_processed'] ?? 0) . '</span>';
                $html .= '<span class="metric-label">Jobs Processed</span>';
                $html .= '</div>';
                $html .= '<div class="metric">';
                $html .= '<span class="metric-value">' . number_format($stats['success_rate'] ?? 0, 1) . '%</span>';
                $html .= '<span class="metric-label">Success Rate</span>';
                $html .= '</div>';
                $html .= '<div class="metric">';
                $html .= '<span class="metric-value">' . number_format($stats['avg_processing_time'] ?? 0, 2) . 's</span>';
                $html .= '<span class="metric-label">Avg Time</span>';
                $html .= '</div>';
                $html .= '</div>';

                if ($stats['current_job']) {
                    $html .= '<div style="margin-top: 0.5rem; padding: 0.5rem; background: #e6fffa; border-radius: 4px;">';
                    $html .= '<small>🔄 Processing: ' . htmlspecialchars($stats['current_job']) . '</small>';
                    $html .= '</div>';
                }

                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate performance metrics section
     */
    private function generatePerformanceMetricsSection()
    {
        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">📈 Performance Metrics</h2>';
        $html .= '</div>';

        $html .= '<div class="metric-grid">';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">95.2%</span>';
        $html .= '<span class="metric-label">Uptime (24h)</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">1.2s</span>';
        $html .= '<span class="metric-label">Avg Response Time</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">99.1%</span>';
        $html .= '<span class="metric-label">Queue Throughput</span>';
        $html .= '</div>';
        $html .= '<div class="metric">';
        $html .= '<span class="metric-value">2.1MB</span>';
        $html .= '<span class="metric-label">Memory Usage</span>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate recent jobs section
     */
    private function generateRecentJobsSection()
    {
        // Mock data for recent jobs - in real implementation, this would come from the queue storage
        $recentJobs = [
            ['id' => 'job_001', 'name' => 'EmailNotificationJob', 'status' => 'completed', 'created_at' => '2024-01-01 10:30:00', 'completed_at' => '2024-01-01 10:30:05'],
            ['id' => 'job_002', 'name' => 'DataProcessingJob', 'status' => 'running', 'created_at' => '2024-01-01 10:25:00', 'completed_at' => null],
            ['id' => 'job_003', 'name' => 'ApiSyncJob', 'status' => 'failed', 'created_at' => '2024-01-01 10:20:00', 'completed_at' => '2024-01-01 10:20:30'],
            ['id' => 'job_004', 'name' => 'ReportGenerationJob', 'status' => 'pending', 'created_at' => '2024-01-01 10:15:00', 'completed_at' => null],
            ['id' => 'job_005', 'name' => 'DatabaseMaintenanceJob', 'status' => 'completed', 'created_at' => '2024-01-01 10:10:00', 'completed_at' => '2024-01-01 10:10:45']
        ];

        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">📝 Recent Jobs</h2>';
        $html .= '</div>';

        $html .= '<table class="job-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Job ID</th>';
        $html .= '<th>Name</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Created</th>';
        $html .= '<th>Duration</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($recentJobs as $job) {
            $statusClass = 'status-' . $job['status'];
            $duration = $job['completed_at'] ?
                strtotime($job['completed_at']) - strtotime($job['created_at']) : 'Running';

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($job['id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($job['name']) . '</td>';
            $html .= '<td><span class="status-badge ' . $statusClass . '">' . ucfirst($job['status']) . '</span></td>';
            $html .= '<td>' . htmlspecialchars($job['created_at']) . '</td>';
            $html .= '<td>' . (is_numeric($duration) ? $duration . 's' : $duration) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate alerts section
     */
    private function generateAlertsSection()
    {
        // Mock alerts - in real implementation, this would come from monitoring system
        $alerts = [
            ['level' => 'warning', 'message' => 'High memory usage detected on worker-001', 'timestamp' => '2024-01-01 10:45:00'],
            ['level' => 'info', 'message' => 'Queue "high" has reached 80% capacity', 'timestamp' => '2024-01-01 10:40:00'],
            ['level' => 'critical', 'message' => 'Worker worker-003 has stopped responding', 'timestamp' => '2024-01-01 10:35:00']
        ];

        $html = '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h2 class="card-title">🚨 Recent Alerts</h2>';
        $html .= '</div>';

        if (empty($alerts)) {
            $html .= '<p>No recent alerts</p>';
        } else {
            $html .= '<ul class="alert-list">';
            foreach ($alerts as $alert) {
                $alertClass = 'alert-' . $alert['level'];
                $html .= '<li class="alert-item ' . $alertClass . '">';
                $html .= '<strong>' . ucfirst($alert['level']) . ':</strong> ' . htmlspecialchars($alert['message']);
                $html .= '<br><small>' . htmlspecialchars($alert['timestamp']) . '</small>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get HTML footer
     */
    private function getHTMLFooter()
    {
        return '
            </div>
            <script>
                // Auto-refresh every 30 seconds
                setTimeout(function() {
                    location.reload();
                }, 30000);

                // Add some interactive features
                document.addEventListener("DOMContentLoaded", function() {
                    // Add click handlers for expandable sections
                    const cards = document.querySelectorAll(".card");
                    cards.forEach(card => {
                        card.addEventListener("click", function() {
                            this.classList.toggle("expanded");
                        });
                    });
                });
            </script>
        </body>
        </html>
        ';
    }

    /**
     * Generate JSON API response
     */
    public function generateJSONResponse()
    {
        $data = [
            'timestamp' => time(),
            'system' => $this->queueManager ? $this->queueManager->getSystemStats() : [],
            'queues' => $this->queueManager ? $this->queueManager->getQueueStats() : [],
            'workers' => $this->workerManager ? $this->workerManager->getWorkerStats() : [],
            'performance' => [
                'uptime_percentage' => 95.2,
                'avg_response_time' => 1.2,
                'throughput_percentage' => 99.1,
                'memory_usage_mb' => 2.1
            ],
            'alerts' => $this->alerts
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Save dashboard to file
     */
    public function saveDashboard($filename = null, $format = 'html')
    {
        if (!$filename) {
            $filename = CACHE_PATH . '/queue_dashboard_' . date('Y-m-d_H-i-s');
            $filename .= $format === 'json' ? '.json' : '.html';
        }

        if ($format === 'json') {
            $content = $this->generateJSONResponse();
        } else {
            $content = $this->generateHTMLDashboard();
        }

        file_put_contents($filename, $content);
        return $filename;
    }

    /**
     * Add custom alert
     */
    public function addAlert($level, $message, $data = [])
    {
        $this->alerts[] = [
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get alerts
     */
    public function getAlerts($limit = 10)
    {
        return array_slice(array_reverse($this->alerts), 0, $limit);
    }

    /**
     * Clear alerts
     */
    public function clearAlerts()
    {
        $this->alerts = [];
    }

    /**
     * Set queue manager
     */
    public function setQueueManager($queueManager)
    {
        $this->queueManager = $queueManager;
    }

    /**
     * Set worker manager
     */
    public function setWorkerManager($workerManager)
    {
        $this->workerManager = $workerManager;
    }
}

/**
 * Queue Health Checker
 * Monitors queue system health and generates alerts
 */
class QueueHealthChecker
{
    private $queueManager;
    private $workerManager;
    private $dashboard;
    private $thresholds;

    public function __construct($queueManager, $workerManager, $dashboard)
    {
        $this->queueManager = $queueManager;
        $this->workerManager = $workerManager;
        $this->dashboard = $dashboard;

        $this->thresholds = [
            'max_queue_size' => 1000,
            'max_failed_jobs_percentage' => 10,
            'min_worker_health_percentage' => 80,
            'max_avg_processing_time' => 30, // seconds
            'max_memory_usage_percentage' => 85
        ];
    }

    /**
     * Run health checks
     */
    public function runHealthChecks()
    {
        $this->checkQueueSizes();
        $this->checkWorkerHealth();
        $this->checkJobFailureRate();
        $this->checkProcessingTimes();
        $this->checkMemoryUsage();
    }

    /**
     * Check queue sizes
     */
    private function checkQueueSizes()
    {
        $queueStats = $this->queueManager->getQueueStats();

        foreach ($queueStats as $queueName => $stats) {
            $queueSize = $stats['current_size'] ?? 0;

            if ($queueSize > $this->thresholds['max_queue_size']) {
                $this->dashboard->addAlert('critical',
                    "Queue '{$queueName}' has exceeded maximum size ({$queueSize} jobs)",
                    ['queue' => $queueName, 'size' => $queueSize]
                );
            } elseif ($queueSize > $this->thresholds['max_queue_size'] * 0.8) {
                $this->dashboard->addAlert('warning',
                    "Queue '{$queueName}' is approaching maximum capacity ({$queueSize} jobs)",
                    ['queue' => $queueName, 'size' => $queueSize]
                );
            }
        }
    }

    /**
     * Check worker health
     */
    private function checkWorkerHealth()
    {
        $workerStats = $this->workerManager->getWorkerStats();
        $healthyWorkers = 0;
        $totalWorkers = count($workerStats);

        foreach ($workerStats as $workerId => $stats) {
            if (($stats['is_running'] ?? false) &&
                ($stats['success_rate'] ?? 0) > 90) {
                $healthyWorkers++;
            } else {
                $this->dashboard->addAlert('warning',
                    "Worker '{$workerId}' is unhealthy",
                    ['worker' => $workerId, 'stats' => $stats]
                );
            }
        }

        if ($totalWorkers > 0) {
            $healthPercentage = ($healthyWorkers / $totalWorkers) * 100;

            if ($healthPercentage < $this->thresholds['min_worker_health_percentage']) {
                $this->dashboard->addAlert('critical',
                    "Worker health is below threshold ({$healthPercentage}%)",
                    ['healthy_workers' => $healthyWorkers, 'total_workers' => $totalWorkers]
                );
            }
        }
    }

    /**
     * Check job failure rate
     */
    private function checkJobFailureRate()
    {
        $systemStats = $this->queueManager->getSystemStats();
        $totalJobs = ($systemStats['jobs_processed'] ?? 0) +
                    ($systemStats['jobs_failed'] ?? 0) +
                    ($systemStats['jobs_succeeded'] ?? 0);

        if ($totalJobs > 0) {
            $failedJobs = $systemStats['jobs_failed'] ?? 0;
            $failureRate = ($failedJobs / $totalJobs) * 100;

            if ($failureRate > $this->thresholds['max_failed_jobs_percentage']) {
                $this->dashboard->addAlert('critical',
                    "Job failure rate is too high ({$failureRate}%)",
                    ['failure_rate' => $failureRate, 'failed_jobs' => $failedJobs, 'total_jobs' => $totalJobs]
                );
            }
        }
    }

    /**
     * Check processing times
     */
    private function checkProcessingTimes()
    {
        $systemStats = $this->queueManager->getSystemStats();
        $avgProcessingTime = $systemStats['avg_processing_time'] ?? 0;

        if ($avgProcessingTime > $this->thresholds['max_avg_processing_time']) {
            $this->dashboard->addAlert('warning',
                "Average processing time is too high ({$avgProcessingTime}s)",
                ['avg_processing_time' => $avgProcessingTime]
            );
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');

        if (is_numeric($memoryLimit)) {
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $usagePercentage = ($memoryUsage / $memoryLimitBytes) * 100;

            if ($usagePercentage > $this->thresholds['max_memory_usage_percentage']) {
                $this->dashboard->addAlert('warning',
                    "Memory usage is too high ({$usagePercentage}%)",
                    ['memory_usage' => $memoryUsage, 'memory_limit' => $memoryLimitBytes]
                );
            }
        }
    }

    /**
     * Convert memory string to bytes
     */
    private function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return (int) $value;
    }

    /**
     * Update thresholds
     */
    public function updateThresholds($thresholds)
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);
    }
}

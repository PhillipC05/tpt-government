<?php
/**
 * TPT Government Platform - Performance Metrics Collector
 *
 * Collects and calculates performance metrics during tests
 */

class PerformanceMetricsCollector
{
    private $logger;
    private $database;

    /**
     * Metric types
     */
    const METRIC_RESPONSE_TIME = 'response_time';
    const METRIC_THROUGHPUT = 'throughput';
    const METRIC_ERROR_RATE = 'error_rate';
    const METRIC_MEMORY_USAGE = 'memory_usage';
    const METRIC_CPU_USAGE = 'cpu_usage';
    const METRIC_DATABASE_QUERIES = 'database_queries';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $database)
    {
        $this->logger = $logger;
        $this->database = $database;
    }

    /**
     * Collect system metrics
     */
    public function collectSystemMetrics()
    {
        return [
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024, // MB
            'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024, // MB
            'cpu_usage' => $this->getCpuUsage()
        ];
    }

    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage()
    {
        // In a real implementation, you'd use system calls to get CPU usage
        return rand(10, 90);
    }

    /**
     * Get database connections
     */
    public function getDatabaseConnections()
    {
        try {
            $result = $this->database->query("SHOW PROCESSLIST");
            return $result ? count($result->fetchAll()) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Perform detailed system check
     */
    public function performDetailedCheck()
    {
        return [
            'memory_detailed' => $this->getDetailedMemoryInfo(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average' => $this->getLoadAverage(),
            'network_stats' => $this->getNetworkStats()
        ];
    }

    /**
     * Get detailed memory information
     */
    private function getDetailedMemoryInfo()
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $path = dirname(__FILE__);
        $total = disk_total_space($path);
        $free = disk_free_space($path);

        return [
            'total' => $total,
            'free' => $free,
            'used' => $total - $free,
            'percentage' => round((($total - $free) / $total) * 100, 2)
        ];
    }

    /**
     * Get system load average
     */
    private function getLoadAverage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0] ?? 0,
                '5min' => $load[1] ?? 0,
                '15min' => $load[2] ?? 0
            ];
        }

        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }

    /**
     * Get network statistics
     */
    private function getNetworkStats()
    {
        // In a real implementation, you'd collect network statistics
        return [
            'connections' => 0,
            'bytes_sent' => 0,
            'bytes_received' => 0
        ];
    }

    /**
     * Calculate performance metrics
     */
    public function calculateMetrics($results)
    {
        $metrics = [
            self::METRIC_RESPONSE_TIME => $this->calculateResponseTimeMetrics($results),
            self::METRIC_THROUGHPUT => $this->calculateThroughputMetrics($results),
            self::METRIC_ERROR_RATE => $this->calculateErrorRateMetrics($results),
            self::METRIC_MEMORY_USAGE => $this->calculateMemoryMetrics($results),
            self::METRIC_CPU_USAGE => $this->calculateCpuMetrics($results),
            self::METRIC_DATABASE_QUERIES => $this->calculateDatabaseMetrics($results)
        ];

        return $metrics;
    }

    /**
     * Calculate response time metrics
     */
    private function calculateResponseTimeMetrics($results)
    {
        $responseTimes = [];

        foreach ($results as $batch) {
            if (isset($batch['requests'])) {
                foreach ($batch['requests'] as $request) {
                    if (isset($request['response_time'])) {
                        $responseTimes[] = $request['response_time'];
                    }
                }
            }
        }

        if (empty($responseTimes)) {
            return ['avg' => 0, 'min' => 0, 'max' => 0, 'p95' => 0, 'p99' => 0];
        }

        sort($responseTimes);

        return [
            'avg' => array_sum($responseTimes) / count($responseTimes),
            'min' => min($responseTimes),
            'max' => max($responseTimes),
            'p95' => $this->calculatePercentile($responseTimes, 95),
            'p99' => $this->calculatePercentile($responseTimes, 99)
        ];
    }

    /**
     * Calculate throughput metrics
     */
    private function calculateThroughputMetrics($results)
    {
        $totalRequests = 0;
        $totalTime = 0;

        foreach ($results as $batch) {
            if (isset($batch['requests'])) {
                $totalRequests += count($batch['requests']);
            }
            if (isset($batch['batch_time'])) {
                $totalTime += $batch['batch_time'];
            }
        }

        $throughput = $totalTime > 0 ? $totalRequests / $totalTime : 0;

        return [
            'requests_per_second' => $throughput,
            'total_requests' => $totalRequests,
            'total_time' => $totalTime
        ];
    }

    /**
     * Calculate error rate metrics
     */
    private function calculateErrorRateMetrics($results)
    {
        $totalRequests = 0;
        $errorRequests = 0;

        foreach ($results as $batch) {
            if (isset($batch['requests'])) {
                foreach ($batch['requests'] as $request) {
                    $totalRequests++;
                    if (isset($request['success']) && !$request['success']) {
                        $errorRequests++;
                    }
                }
            }
        }

        $errorRate = $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;

        return [
            'error_rate' => $errorRate,
            'total_requests' => $totalRequests,
            'error_requests' => $errorRequests
        ];
    }

    /**
     * Calculate memory metrics
     */
    private function calculateMemoryMetrics($results)
    {
        $memoryUsages = [];

        foreach ($results as $batch) {
            if (isset($batch['metrics']['memory_usage'])) {
                $memoryUsages[] = $batch['metrics']['memory_usage'];
            }
        }

        if (empty($memoryUsages)) {
            return ['avg' => 0, 'min' => 0, 'max' => 0];
        }

        return [
            'avg' => array_sum($memoryUsages) / count($memoryUsages),
            'min' => min($memoryUsages),
            'max' => max($memoryUsages)
        ];
    }

    /**
     * Calculate CPU metrics
     */
    private function calculateCpuMetrics($results)
    {
        $cpuUsages = [];

        foreach ($results as $batch) {
            if (isset($batch['metrics']['cpu_usage'])) {
                $cpuUsages[] = $batch['metrics']['cpu_usage'];
            }
        }

        if (empty($cpuUsages)) {
            return ['avg' => 0, 'min' => 0, 'max' => 0];
        }

        return [
            'avg' => array_sum($cpuUsages) / count($cpuUsages),
            'min' => min($cpuUsages),
            'max' => max($cpuUsages)
        ];
    }

    /**
     * Calculate database metrics
     */
    private function calculateDatabaseMetrics($results)
    {
        $dbConnections = [];

        foreach ($results as $batch) {
            if (isset($batch['metrics']['database_connections'])) {
                $dbConnections[] = $batch['metrics']['database_connections'];
            }
        }

        if (empty($dbConnections)) {
            return ['avg' => 0, 'min' => 0, 'max' => 0];
        }

        return [
            'avg' => array_sum($dbConnections) / count($dbConnections),
            'min' => min($dbConnections),
            'max' => max($dbConnections)
        ];
    }

    /**
     * Calculate percentile
     */
    private function calculatePercentile($data, $percentile)
    {
        if (empty($data)) {
            return 0;
        }

        sort($data);
        $index = (int)ceil(($percentile / 100) * count($data)) - 1;

        return $data[max(0, min($index, count($data) - 1))];
    }
}

<?php
/**
 * TPT Government Platform - Performance Test Suite
 *
 * Comprehensive performance testing suite with load testing, stress testing, and benchmarking
 */

class PerformanceTestSuite
{
    private $logger;
    private $database;
    private $config;
    private $results = [];
    private $benchmarks = [];
    private $metrics = [];

    /**
     * Test types
     */
    const TEST_LOAD = 'load';
    const TEST_STRESS = 'stress';
    const TEST_SPIKE = 'spike';
    const TEST_VOLUME = 'volume';
    const TEST_ENDURANCE = 'endurance';

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
    public function __construct(StructuredLogger $logger, $database, $config = [])
    {
        $this->logger = $logger;
        $this->database = $database;
        $this->config = array_merge([
            'concurrency_levels' => [1, 5, 10, 25, 50, 100],
            'test_duration' => 60, // seconds
            'ramp_up_time' => 10, // seconds
            'warm_up_requests' => 10,
            'cooldown_time' => 5, // seconds
            'target_response_time' => 1000, // milliseconds
            'target_error_rate' => 1.0, // percentage
            'target_throughput' => 100, // requests per second
            'memory_threshold' => 128, // MB
            'cpu_threshold' => 80, // percentage
            'results_directory' => 'tests/performance/results/',
            'reports_directory' => 'tests/performance/reports/',
            'baseline_file' => 'tests/performance/baseline.json'
        ], $config);

        $this->initializeSuite();
    }

    /**
     * Initialize the performance test suite
     */
    private function initializeSuite()
    {
        // Create directories if they don't exist
        $directories = [
            $this->config['results_directory'],
            $this->config['reports_directory'],
            dirname($this->config['baseline_file'])
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Load baseline if it exists
        $this->loadBaseline();

        $this->logger->info('Performance Test Suite initialized', [
            'concurrency_levels' => $this->config['concurrency_levels'],
            'test_duration' => $this->config['test_duration']
        ]);
    }

    /**
     * Load performance baseline
     */
    private function loadBaseline()
    {
        if (file_exists($this->config['baseline_file'])) {
            $baseline = json_decode(file_get_contents($this->config['baseline_file']), true);
            if ($baseline) {
                $this->benchmarks = $baseline;
                $this->logger->info('Performance baseline loaded', [
                    'baseline_file' => $this->config['baseline_file']
                ]);
            }
        }
    }

    /**
     * Run load test
     */
    public function runLoadTest($endpoint, $concurrency = null, $duration = null)
    {
        $concurrency = $concurrency ?? max($this->config['concurrency_levels']);
        $duration = $duration ?? $this->config['test_duration'];

        $this->logger->info('Starting load test', [
            'endpoint' => $endpoint,
            'concurrency' => $concurrency,
            'duration' => $duration
        ]);

        $testConfig = [
            'type' => self::TEST_LOAD,
            'endpoint' => $endpoint,
            'concurrency' => $concurrency,
            'duration' => $duration,
            'pattern' => 'constant'
        ];

        return $this->executeTest($testConfig);
    }

    /**
     * Run stress test
     */
    public function runStressTest($endpoint, $maxConcurrency = null, $duration = null)
    {
        $maxConcurrency = $maxConcurrency ?? max($this->config['concurrency_levels']);
        $duration = $duration ?? $this->config['test_duration'];

        $this->logger->info('Starting stress test', [
            'endpoint' => $endpoint,
            'max_concurrency' => $maxConcurrency,
            'duration' => $duration
        ]);

        $testConfig = [
            'type' => self::TEST_STRESS,
            'endpoint' => $endpoint,
            'max_concurrency' => $maxConcurrency,
            'duration' => $duration,
            'pattern' => 'ramp_up'
        ];

        return $this->executeTest($testConfig);
    }

    /**
     * Run spike test
     */
    public function runSpikeTest($endpoint, $spikeConcurrency = null, $duration = null)
    {
        $spikeConcurrency = $spikeConcurrency ?? max($this->config['concurrency_levels']) * 2;
        $duration = $duration ?? $this->config['test_duration'];

        $this->logger->info('Starting spike test', [
            'endpoint' => $endpoint,
            'spike_concurrency' => $spikeConcurrency,
            'duration' => $duration
        ]);

        $testConfig = [
            'type' => self::TEST_SPIKE,
            'endpoint' => $endpoint,
            'spike_concurrency' => $spikeConcurrency,
            'duration' => $duration,
            'pattern' => 'spike'
        ];

        return $this->executeTest($testConfig);
    }

    /**
     * Run volume test
     */
    public function runVolumeTest($endpoint, $dataVolume = null, $duration = null)
    {
        $dataVolume = $dataVolume ?? 1000; // Number of records to process
        $duration = $duration ?? $this->config['test_duration'];

        $this->logger->info('Starting volume test', [
            'endpoint' => $endpoint,
            'data_volume' => $dataVolume,
            'duration' => $duration
        ]);

        $testConfig = [
            'type' => self::TEST_VOLUME,
            'endpoint' => $endpoint,
            'data_volume' => $dataVolume,
            'duration' => $duration,
            'pattern' => 'volume'
        ];

        return $this->executeTest($testConfig);
    }

    /**
     * Run endurance test
     */
    public function runEnduranceTest($endpoint, $concurrency = null, $duration = null)
    {
        $concurrency = $concurrency ?? (int)(max($this->config['concurrency_levels']) / 2);
        $duration = $duration ?? $this->config['test_duration'] * 5; // Longer duration

        $this->logger->info('Starting endurance test', [
            'endpoint' => $endpoint,
            'concurrency' => $concurrency,
            'duration' => $duration
        ]);

        $testConfig = [
            'type' => self::TEST_ENDURANCE,
            'endpoint' => $endpoint,
            'concurrency' => $concurrency,
            'duration' => $duration,
            'pattern' => 'constant'
        ];

        return $this->executeTest($testConfig);
    }

    /**
     * Execute performance test
     */
    private function executeTest($testConfig)
    {
        $testId = uniqid('perf_test_');
        $startTime = microtime(true);

        $this->logger->info('Executing performance test', [
            'test_id' => $testId,
            'config' => $testConfig
        ]);

        try {
            // Warm up the system
            $this->warmUpSystem($testConfig['endpoint']);

            // Execute the test based on type
            $results = $this->executeTestByType($testConfig);

            // Calculate metrics
            $metrics = $this->calculateMetrics($results, $testConfig);

            // Generate report
            $report = $this->generateReport($testId, $testConfig, $results, $metrics);

            // Compare with baseline
            $comparison = $this->compareWithBaseline($testConfig, $metrics);

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            $finalResult = [
                'test_id' => $testId,
                'config' => $testConfig,
                'results' => $results,
                'metrics' => $metrics,
                'report' => $report,
                'comparison' => $comparison,
                'execution_time' => $totalTime,
                'timestamp' => date('c')
            ];

            // Store results
            $this->storeResults($testId, $finalResult);

            $this->logger->info('Performance test completed', [
                'test_id' => $testId,
                'execution_time' => $totalTime . 'ms',
                'status' => 'success'
            ]);

            return $finalResult;

        } catch (Exception $e) {
            $this->logger->error('Performance test failed', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);

            return [
                'test_id' => $testId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Warm up the system
     */
    private function warmUpSystem($endpoint)
    {
        $this->logger->debug('Warming up system', [
            'endpoint' => $endpoint,
            'warm_up_requests' => $this->config['warm_up_requests']
        ]);

        for ($i = 0; $i < $this->config['warm_up_requests']; $i++) {
            $this->makeRequest('GET', $endpoint);
        }

        // Small delay to let system stabilize
        sleep(1);
    }

    /**
     * Execute test based on type
     */
    private function executeTestByType($testConfig)
    {
        switch ($testConfig['type']) {
            case self::TEST_LOAD:
                return $this->executeLoadTest($testConfig);
            case self::TEST_STRESS:
                return $this->executeStressTest($testConfig);
            case self::TEST_SPIKE:
                return $this->executeSpikeTest($testConfig);
            case self::TEST_VOLUME:
                return $this->executeVolumeTest($testConfig);
            case self::TEST_ENDURANCE:
                return $this->executeEnduranceTest($testConfig);
            default:
                throw new Exception("Unknown test type: {$testConfig['type']}");
        }
    }

    /**
     * Execute load test
     */
    private function executeLoadTest($testConfig)
    {
        $results = [];
        $endTime = time() + $testConfig['duration'];

        while (time() < $endTime) {
            $batchStart = microtime(true);

            // Execute concurrent requests
            $batchResults = $this->executeConcurrentRequests(
                $testConfig['endpoint'],
                $testConfig['concurrency']
            );

            $batchTime = microtime(true) - $batchStart;

            $results[] = [
                'timestamp' => time(),
                'batch_time' => $batchTime,
                'requests' => $batchResults,
                'metrics' => $this->collectSystemMetrics()
            ];

            // Small delay between batches
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Execute stress test
     */
    private function executeStressTest($testConfig)
    {
        $results = [];
        $endTime = time() + $testConfig['duration'];
        $currentConcurrency = 1;
        $maxConcurrency = $testConfig['max_concurrency'];
        $rampUpTime = $this->config['ramp_up_time'];

        $concurrencyIncrement = $maxConcurrency / $rampUpTime;

        while (time() < $endTime) {
            $batchStart = microtime(true);

            // Gradually increase concurrency
            if ($currentConcurrency < $maxConcurrency) {
                $currentConcurrency = min($maxConcurrency, $currentConcurrency + $concurrencyIncrement);
            }

            $batchResults = $this->executeConcurrentRequests(
                $testConfig['endpoint'],
                (int)$currentConcurrency
            );

            $batchTime = microtime(true) - $batchStart;

            $results[] = [
                'timestamp' => time(),
                'concurrency' => $currentConcurrency,
                'batch_time' => $batchTime,
                'requests' => $batchResults,
                'metrics' => $this->collectSystemMetrics()
            ];

            sleep(1); // 1 second intervals
        }

        return $results;
    }

    /**
     * Execute spike test
     */
    private function executeSpikeTest($testConfig)
    {
        $results = [];
        $endTime = time() + $testConfig['duration'];
        $normalConcurrency = (int)($testConfig['spike_concurrency'] / 4);
        $spikeConcurrency = $testConfig['spike_concurrency'];

        while (time() < $endTime) {
            // Alternate between normal and spike load
            $currentConcurrency = (time() % 60 < 30) ? $spikeConcurrency : $normalConcurrency;

            $batchStart = microtime(true);

            $batchResults = $this->executeConcurrentRequests(
                $testConfig['endpoint'],
                $currentConcurrency
            );

            $batchTime = microtime(true) - $batchStart;

            $results[] = [
                'timestamp' => time(),
                'concurrency' => $currentConcurrency,
                'is_spike' => ($currentConcurrency === $spikeConcurrency),
                'batch_time' => $batchTime,
                'requests' => $batchResults,
                'metrics' => $this->collectSystemMetrics()
            ];

            sleep(1);
        }

        return $results;
    }

    /**
     * Execute volume test
     */
    private function executeVolumeTest($testConfig)
    {
        $results = [];
        $dataVolume = $testConfig['data_volume'];
        $batchSize = 100;

        for ($i = 0; $i < $dataVolume; $i += $batchSize) {
            $batchStart = microtime(true);

            // Process batch of data
            $batchResults = $this->processDataBatch(
                $testConfig['endpoint'],
                min($batchSize, $dataVolume - $i)
            );

            $batchTime = microtime(true) - $batchStart;

            $results[] = [
                'timestamp' => time(),
                'batch_start' => $i,
                'batch_size' => min($batchSize, $dataVolume - $i),
                'batch_time' => $batchTime,
                'results' => $batchResults,
                'metrics' => $this->collectSystemMetrics()
            ];

            // Small delay between batches
            usleep(50000); // 50ms
        }

        return $results;
    }

    /**
     * Execute endurance test
     */
    private function executeEnduranceTest($testConfig)
    {
        $results = [];
        $endTime = time() + $testConfig['duration'];
        $checkInterval = 60; // Check every minute
        $lastCheck = time();

        while (time() < $endTime) {
            $batchStart = microtime(true);

            $batchResults = $this->executeConcurrentRequests(
                $testConfig['endpoint'],
                $testConfig['concurrency']
            );

            $batchTime = microtime(true) - $batchStart;

            $results[] = [
                'timestamp' => time(),
                'batch_time' => $batchTime,
                'requests' => $batchResults,
                'metrics' => $this->collectSystemMetrics()
            ];

            // Periodic detailed checks
            if (time() - $lastCheck >= $checkInterval) {
                $results[count($results) - 1]['detailed_check'] = $this->performDetailedCheck();
                $lastCheck = time();
            }

            sleep(1);
        }

        return $results;
    }

    /**
     * Execute concurrent requests
     */
    private function executeConcurrentRequests($endpoint, $concurrency)
    {
        $results = [];
        $channel = curl_multi_init();

        // Create concurrent requests
        for ($i = 0; $i < $concurrency; $i++) {
            $ch = $this->createCurlHandle($endpoint);
            curl_multi_add_handle($channel, $ch);
            $results[$i] = ['handle' => $ch, 'index' => $i];
        }

        // Execute requests
        $active = null;
        do {
            $mrc = curl_multi_exec($channel, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($channel) != -1) {
                do {
                    $mrc = curl_multi_exec($channel, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        // Collect results
        foreach ($results as &$result) {
            $response = curl_multi_getcontent($result['handle']);
            $httpCode = curl_getinfo($result['handle'], CURLINFO_HTTP_CODE);
            $totalTime = curl_getinfo($result['handle'], CURLINFO_TOTAL_TIME);

            $result['response'] = $response;
            $result['http_code'] = $httpCode;
            $result['response_time'] = $totalTime * 1000; // Convert to milliseconds
            $result['success'] = ($httpCode >= 200 && $httpCode < 300);

            curl_multi_remove_handle($channel, $result['handle']);
            curl_close($result['handle']);
            unset($result['handle']);
        }

        curl_multi_close($channel);

        return $results;
    }

    /**
     * Create cURL handle for request
     */
    private function createCurlHandle($endpoint)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'PerformanceTestSuite/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);

        return $ch;
    }

    /**
     * Process data batch for volume testing
     */
    private function processDataBatch($endpoint, $batchSize)
    {
        $results = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $startTime = microtime(true);

            // Simulate data processing
            $data = ['id' => $i, 'data' => str_repeat('x', 1000)];
            $response = $this->makeRequest('POST', $endpoint, $data);

            $responseTime = (microtime(true) - $startTime) * 1000;

            $results[] = [
                'record_id' => $i,
                'response_time' => $responseTime,
                'success' => ($response['http_code'] ?? 0) >= 200 && ($response['http_code'] ?? 0) < 300
            ];
        }

        return $results;
    }

    /**
     * Make HTTP request
     */
    private function makeRequest($method, $endpoint, $data = [])
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'PerformanceTestSuite/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ];

        if ($method === 'POST' && !empty($data)) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

        curl_close($ch);

        return [
            'response' => $response,
            'http_code' => $httpCode,
            'response_time' => $responseTime * 1000
        ];
    }

    /**
     * Collect system metrics
     */
    private function collectSystemMetrics()
    {
        $metrics = [
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true) / 1024 / 1024, // MB
            'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024, // MB
            'cpu_usage' => $this->getCpuUsage()
        ];

        // Database connection metrics
        if ($this->database) {
            $metrics['database_connections'] = $this->getDatabaseConnections();
        }

        return $metrics;
    }

    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage()
    {
        // In a real implementation, you'd use system calls to get CPU usage
        // For now, return a mock value
        return rand(10, 90);
    }

    /**
     * Get database connections
     */
    private function getDatabaseConnections()
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
    private function performDetailedCheck()
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
    private function calculateMetrics($results, $testConfig)
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

    /**
     * Generate performance report
     */
    private function generateReport($testId, $testConfig, $results, $metrics)
    {
        $report = [
            'test_id' => $testId,
            'test_type' => $testConfig['type'],
            'endpoint' => $testConfig['endpoint'],
            'configuration' => $testConfig,
            'summary' => [
                'total_batches' => count($results),
                'total_requests' => $this->countTotalRequests($results),
                'duration' => $testConfig['duration'],
                'average_response_time' => $metrics[self::METRIC_RESPONSE_TIME]['avg'],
                'p95_response_time' => $metrics[self::METRIC_RESPONSE_TIME]['p95'],
                'throughput' => $metrics[self::METRIC_THROUGHPUT]['requests_per_second'],
                'error_rate' => $metrics[self::METRIC_ERROR_RATE]['error_rate']
            ],
            'metrics' => $metrics,
            'thresholds' => [
                'response_time_target' => $this->config['target_response_time'],
                'error_rate_target' => $this->config['target_error_rate'],
                'throughput_target' => $this->config['target_throughput']
            ],
            'compliance' => $this->checkCompliance($metrics),
            'recommendations' => $this->generateRecommendations($metrics)
        ];

        return $report;
    }

    /**
     * Count total requests
     */
    private function countTotalRequests($results)
    {
        $total = 0;

        foreach ($results as $batch) {
            if (isset($batch['requests'])) {
                $total += count($batch['requests']);
            }
        }

        return $total;
    }

    /**
     * Check compliance with targets
     */
    private function checkCompliance($metrics)
    {
        return [
            'response_time_ok' => $metrics[self::METRIC_RESPONSE_TIME]['p95'] <= $this->config['target_response_time'],
            'error_rate_ok' => $metrics[self::METRIC_ERROR_RATE]['error_rate'] <= $this->config['target_error_rate'],
            'throughput_ok' => $metrics[self::METRIC_THROUGHPUT]['requests_per_second'] >= $this->config['target_throughput'],
            'memory_ok' => $metrics[self::METRIC_MEMORY_USAGE]['max'] <= $this->config['memory_threshold'],
            'cpu_ok' => $metrics[self::METRIC_CPU_USAGE]['max'] <= $this->config['cpu_threshold']
        ];
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations($metrics)
    {
        $recommendations = [];

        if ($metrics[self::METRIC_RESPONSE_TIME]['p95'] > $this->config['target_response_time']) {
            $recommendations[] = 'Consider optimizing database queries or implementing caching';
        }

        if ($metrics[self::METRIC_ERROR_RATE]['error_rate'] > $this->config['target_error_rate']) {
            $recommendations[] = 'Investigate error causes and improve error handling';
        }

        if ($metrics[self::METRIC_THROUGHPUT]['requests_per_second'] < $this->config['target_throughput']) {
            $recommendations[] = 'Consider horizontal scaling or performance optimization';
        }

        if ($metrics[self::METRIC_MEMORY_USAGE]['max'] > $this->config['memory_threshold']) {
            $recommendations[] = 'Monitor memory usage and consider memory optimization';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Performance is within acceptable limits';
        }

        return $recommendations;
    }

    /**
     * Compare with baseline
     */
    private function compareWithBaseline($testConfig, $metrics)
    {
        if (empty($this->benchmarks)) {
            return ['baseline_available' => false];
        }

        $endpoint = $testConfig['endpoint'];
        $baselineKey = md5($endpoint . $testConfig['type']);

        if (!isset($this->benchmarks[$baselineKey])) {
            return ['baseline_available' => false];
        }

        $baseline = $this->benchmarks[$baselineKey];

        return [
            'baseline_available' => true,
            'response_time_change' => $this->calculateChange(
                $baseline['response_time'] ?? 0,
                $metrics[self::METRIC_RESPONSE_TIME]['avg']
            ),
            'throughput_change' => $this->calculateChange(
                $baseline['throughput'] ?? 0,
                $metrics[self::METRIC_THROUGHPUT]['requests_per_second']
            ),
            'error_rate_change' => $this->calculateChange(
                $baseline['error_rate'] ?? 0,
                $metrics[self::METRIC_ERROR_RATE]['error_rate']
            )
        ];
    }

    /**
     * Calculate percentage change
     */
    private function calculateChange($oldValue, $newValue)
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Store test results
     */
    private function storeResults($testId, $results)
    {
        $this->results[$testId] = $results;

        // Save to file
        $filename = $this->config['results_directory'] . $testId . '.json';
        file_put_contents($filename, json_encode($results, JSON_PRETTY_PRINT));
    }

    /**
     * Update performance baseline
     */
    public function updateBaseline($testConfig, $metrics)
    {
        $endpoint = $testConfig['endpoint'];
        $baselineKey = md5($endpoint . $testConfig['type']);

        $this->benchmarks[$baselineKey] = [
            'endpoint' => $endpoint,
            'test_type' => $testConfig['type'],
            'response_time' => $metrics[self::METRIC_RESPONSE_TIME]['avg'],
            'throughput' => $metrics[self::METRIC_THROUGHPUT]['requests_per_second'],
            'error_rate' => $metrics[self::METRIC_ERROR_RATE]['error_rate'],
            'memory_usage' => $metrics[self::METRIC_MEMORY_USAGE]['avg'],
            'cpu_usage' => $metrics[self::METRIC_CPU_USAGE]['avg'],
            'updated_at' => date('c')
        ];

        // Save baseline
        file_put_contents(
            $this->config['baseline_file'],
            json_encode($this->benchmarks, JSON_PRETTY_PRINT)
        );

        $this->logger->info('Performance baseline updated', [
            'endpoint' => $endpoint,
            'test_type' => $testConfig['type']
        ]);
    }

    /**
     * Get test results
     */
    public function getResults($testId = null)
    {
        if ($testId) {
            return $this->results[$testId] ?? null;
        }

        return $this->results;
    }

    /**
     * Get performance baseline
     */
    public function getBaseline()
    {
        return $this->benchmarks;
    }

    /**
     * Generate HTML performance report
     */
    public function generateHtmlReport($testId)
    {
        $result = $this->getResults($testId);
        if (!$result) {
            return null;
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Performance Test Report - ' . $testId . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .metric { background: #fff; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; text-align: center; }
        .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
        .compliance { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .non-compliance { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .recommendations { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Performance Test Report</h1>
        <p><strong>Test ID:</strong> ' . $testId . '</p>
        <p><strong>Endpoint:</strong> ' . $result['config']['endpoint'] . '</p>
        <p><strong>Test Type:</strong> ' . ucfirst($result['config']['type']) . '</p>
        <p><strong>Generated:</strong> ' . $result['timestamp'] . '</p>
    </div>';

        // Summary metrics
        $html .= '<div class="metrics">';
        $html .= '<div class="metric">
            <div class="metric-value">' . round($result['metrics']['response_time']['avg'], 2) . 'ms</div>
            <div>Avg Response Time</div>
        </div>';
        $html .= '<div class="metric">
            <div class="metric-value">' . round($result['metrics']['throughput']['requests_per_second'], 2) . '</div>
            <div>Requests/sec</div>
        </div>';
        $html .= '<div class="metric">
            <div class="metric-value">' . round($result['metrics']['error_rate']['error_rate'], 2) . '%</div>
            <div>Error Rate</div>
        </div>';
        $html .= '<div class="metric">
            <div class="metric-value">' . $result['report']['summary']['total_requests'] . '</div>
            <div>Total Requests</div>
        </div>';
        $html .= '</div>';

        // Compliance status
        $compliance = $result['report']['compliance'];
        $complianceClass = 'compliance';
        $complianceTitle = 'Compliance Status';

        if (!$compliance['response_time_ok'] || !$compliance['error_rate_ok'] || !$compliance['throughput_ok']) {
            $complianceClass = 'non-compliance';
            $complianceTitle = 'Non-Compliance Issues';
        }

        $html .= '<div class="' . $complianceClass . '">
            <h3>' . $complianceTitle . '</h3>
            <ul>';

        if ($compliance['response_time_ok']) {
            $html .= '<li>✅ Response time within target (' . $result['metrics']['response_time']['p95'] . 'ms ≤ ' . $this->config['target_response_time'] . 'ms)</li>';
        } else {
            $html .= '<li>❌ Response time exceeds target (' . $result['metrics']['response_time']['p95'] . 'ms > ' . $this->config['target_response_time'] . 'ms)</li>';
        }

        if ($compliance['error_rate_ok']) {
            $html .= '<li>✅ Error rate within target (' . round($result['metrics']['error_rate']['error_rate'], 2) . '% ≤ ' . $this->config['target_error_rate'] . '%)</li>';
        } else {
            $html .= '<li>❌ Error rate exceeds target (' . round($result['metrics']['error_rate']['error_rate'], 2) . '% > ' . $this->config['target_error_rate'] . '%)</li>';
        }

        if ($compliance['throughput_ok']) {
            $html .= '<li>✅ Throughput meets target (' . round($result['metrics']['throughput']['requests_per_second'], 2) . ' ≥ ' . $this->config['target_throughput'] . ')</li>';
        } else {
            $html .= '<li>❌ Throughput below target (' . round($result['metrics']['throughput']['requests_per_second'], 2) . ' < ' . $this->config['target_throughput'] . ')</li>';
        }

        $html .= '</ul></div>';

        // Recommendations
        if (!empty($result['report']['recommendations'])) {
            $html .= '<div class="recommendations">
                <h3>Recommendations</h3>
                <ul>';

            foreach ($result['report']['recommendations'] as $recommendation) {
                $html .= '<li>' . htmlspecialchars($recommendation) . '</li>';
            }

            $html .= '</ul></div>';
        }

        $html .= '</body></html>';

        // Save HTML report
        $htmlFilename = $this->config['reports_directory'] . $testId . '_report.html';
        file_put_contents($htmlFilename, $html);

        return $htmlFilename;
    }
}

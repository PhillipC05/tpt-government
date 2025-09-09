<?php
/**
 * TPT Government Platform - Performance Test Suite
 *
 * Main performance testing suite that orchestrates different types of tests
 */

class PerformanceTestSuite
{
    private $logger;
    private $database;
    private $config;
    private $results = [];
    private $loadTest;
    private $stressTest;
    private $spikeTest;
    private $volumeTest;
    private $enduranceTest;
    private $metricsCollector;
    private $reportGenerator;
    private $baselineManager;

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $database, $config = [])
    {
        $this->logger = $logger;
        $this->database = $database;
        $this->config = array_merge([
            'concurrency_levels' => [1, 5, 10, 25, 50, 100],
            'test_duration' => 60,
            'ramp_up_time' => 10,
            'warm_up_requests' => 10,
            'cooldown_time' => 5,
            'target_response_time' => 1000,
            'target_error_rate' => 1.0,
            'target_throughput' => 100,
            'memory_threshold' => 128,
            'cpu_threshold' => 80,
            'results_directory' => 'tests/performance/results/',
            'reports_directory' => 'tests/performance/reports/',
            'baseline_file' => 'tests/performance/baseline.json'
        ], $config);

        $this->initializeComponents();
    }

    /**
     * Initialize test components
     */
    private function initializeComponents()
    {
        $this->metricsCollector = new PerformanceMetricsCollector($this->logger, $this->database);
        $this->reportGenerator = new PerformanceReportGenerator($this->config);
        $this->baselineManager = new PerformanceBaselineManager($this->config['baseline_file'], $this->logger);

        $this->loadTest = new LoadTest($this->logger, $this->metricsCollector, $this->config);
        $this->stressTest = new StressTest($this->logger, $this->metricsCollector, $this->config);
        $this->spikeTest = new SpikeTest($this->logger, $this->metricsCollector, $this->config);
        $this->volumeTest = new VolumeTest($this->logger, $this->metricsCollector, $this->config);
        $this->enduranceTest = new EnduranceTest($this->logger, $this->metricsCollector, $this->config);

        $this->logger->info('Performance Test Suite initialized');
    }

    /**
     * Run load test
     */
    public function runLoadTest($endpoint, $concurrency = null, $duration = null)
    {
        return $this->loadTest->execute($endpoint, $concurrency, $duration);
    }

    /**
     * Run stress test
     */
    public function runStressTest($endpoint, $maxConcurrency = null, $duration = null)
    {
        return $this->stressTest->execute($endpoint, $maxConcurrency, $duration);
    }

    /**
     * Run spike test
     */
    public function runSpikeTest($endpoint, $spikeConcurrency = null, $duration = null)
    {
        return $this->spikeTest->execute($endpoint, $spikeConcurrency, $duration);
    }

    /**
     * Run volume test
     */
    public function runVolumeTest($endpoint, $dataVolume = null, $duration = null)
    {
        return $this->volumeTest->execute($endpoint, $dataVolume, $duration);
    }

    /**
     * Run endurance test
     */
    public function runEnduranceTest($endpoint, $concurrency = null, $duration = null)
    {
        return $this->enduranceTest->execute($endpoint, $concurrency, $duration);
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
        return $this->baselineManager->getBaseline();
    }

    /**
     * Update performance baseline
     */
    public function updateBaseline($testConfig, $metrics)
    {
        $this->baselineManager->updateBaseline($testConfig, $metrics);
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

        return $this->reportGenerator->generateHtmlReport($result, $testId);
    }

    /**
     * Store test results
     */
    public function storeResults($testId, $results)
    {
        $this->results[$testId] = $results;

        // Save to file
        $filename = $this->config['results_directory'] . $testId . '.json';
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        file_put_contents($filename, json_encode($results, JSON_PRETTY_PRINT));
    }
}

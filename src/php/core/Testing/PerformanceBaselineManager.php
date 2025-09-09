<?php
/**
 * TPT Government Platform - Performance Baseline Manager
 *
 * Manages performance baselines for comparison
 */

class PerformanceBaselineManager
{
    private $baselineFile;
    private $logger;
    private $baselines = [];

    /**
     * Constructor
     */
    public function __construct($baselineFile, StructuredLogger $logger)
    {
        $this->baselineFile = $baselineFile;
        $this->logger = $logger;
        $this->loadBaselines();
    }

    /**
     * Load performance baselines
     */
    private function loadBaselines()
    {
        if (file_exists($this->baselineFile)) {
            $baselines = json_decode(file_get_contents($this->baselineFile), true);
            if ($baselines) {
                $this->baselines = $baselines;
                $this->logger->info('Performance baselines loaded', [
                    'baseline_file' => $this->baselineFile,
                    'baseline_count' => count($baselines)
                ]);
            }
        } else {
            $this->logger->info('No baseline file found, starting with empty baselines');
        }
    }

    /**
     * Get all baselines
     */
    public function getBaseline()
    {
        return $this->baselines;
    }

    /**
     * Update performance baseline
     */
    public function updateBaseline($testConfig, $metrics)
    {
        $endpoint = $testConfig['endpoint'];
        $baselineKey = md5($endpoint . $testConfig['type']);

        $this->baselines[$baselineKey] = [
            'endpoint' => $endpoint,
            'test_type' => $testConfig['type'],
            'response_time' => $metrics['response_time']['avg'],
            'throughput' => $metrics['throughput']['requests_per_second'],
            'error_rate' => $metrics['error_rate']['error_rate'],
            'memory_usage' => $metrics['memory_usage']['avg'],
            'cpu_usage' => $metrics['cpu_usage']['avg'],
            'updated_at' => date('c')
        ];

        // Save baselines
        $this->saveBaselines();

        $this->logger->info('Performance baseline updated', [
            'endpoint' => $endpoint,
            'test_type' => $testConfig['type'],
            'baseline_key' => $baselineKey
        ]);
    }

    /**
     * Compare with baseline
     */
    public function compareWithBaseline($testConfig, $metrics)
    {
        $endpoint = $testConfig['endpoint'];
        $baselineKey = md5($endpoint . $testConfig['type']);

        if (!isset($this->baselines[$baselineKey])) {
            return ['baseline_available' => false];
        }

        $baseline = $this->baselines[$baselineKey];

        return [
            'baseline_available' => true,
            'response_time_change' => $this->calculateChange(
                $baseline['response_time'] ?? 0,
                $metrics['response_time']['avg']
            ),
            'throughput_change' => $this->calculateChange(
                $baseline['throughput'] ?? 0,
                $metrics['throughput']['requests_per_second']
            ),
            'error_rate_change' => $this->calculateChange(
                $baseline['error_rate'] ?? 0,
                $metrics['error_rate']['error_rate']
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
     * Save baselines to file
     */
    private function saveBaselines()
    {
        $directory = dirname($this->baselineFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $this->baselineFile,
            json_encode($this->baselines, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Get baseline for specific endpoint and test type
     */
    public function getBaselineForTest($endpoint, $testType)
    {
        $baselineKey = md5($endpoint . $testType);
        return $this->baselines[$baselineKey] ?? null;
    }

    /**
     * Remove baseline
     */
    public function removeBaseline($endpoint, $testType)
    {
        $baselineKey = md5($endpoint . $testType);

        if (isset($this->baselines[$baselineKey])) {
            unset($this->baselines[$baselineKey]);
            $this->saveBaselines();

            $this->logger->info('Performance baseline removed', [
                'endpoint' => $endpoint,
                'test_type' => $testType
            ]);

            return true;
        }

        return false;
    }

    /**
     * Clear all baselines
     */
    public function clearAllBaselines()
    {
        $this->baselines = [];
        $this->saveBaselines();

        $this->logger->info('All performance baselines cleared');
    }

    /**
     * Get baseline summary
     */
    public function getBaselineSummary()
    {
        $summary = [
            'total_baselines' => count($this->baselines),
            'endpoints' => [],
            'test_types' => []
        ];

        foreach ($this->baselines as $baseline) {
            $endpoint = $baseline['endpoint'];
            $testType = $baseline['test_type'];

            if (!in_array($endpoint, $summary['endpoints'])) {
                $summary['endpoints'][] = $endpoint;
            }

            if (!in_array($testType, $summary['test_types'])) {
                $summary['test_types'][] = $testType;
            }
        }

        return $summary;
    }
}

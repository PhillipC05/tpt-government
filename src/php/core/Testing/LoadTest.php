<?php
/**
 * TPT Government Platform - Load Test
 *
 * Executes load testing with constant concurrency
 */

class LoadTest extends BasePerformanceTest
{
    /**
     * Execute load test
     */
    public function execute($endpoint, $concurrency = null, $duration = null)
    {
        $concurrency = $concurrency ?? max($this->config['concurrency_levels']);
        $duration = $duration ?? $this->config['test_duration'];

        $this->logger->info('Starting load test', [
            'endpoint' => $endpoint,
            'concurrency' => $concurrency,
            'duration' => $duration
        ]);

        $testConfig = [
            'type' => 'load',
            'endpoint' => $endpoint,
            'concurrency' => $concurrency,
            'duration' => $duration,
            'pattern' => 'constant'
        ];

        return $this->runTest($testConfig);
    }

    /**
     * Run the load test
     */
    private function runTest($testConfig)
    {
        $testId = uniqid('load_test_');
        $startTime = microtime(true);

        $this->logger->info('Executing load test', [
            'test_id' => $testId,
            'config' => $testConfig
        ]);

        try {
            // Warm up the system
            $this->warmUpSystem($testConfig['endpoint']);

            // Execute the test
            $results = $this->executeLoadTest($testConfig);

            // Calculate metrics
            $metrics = $this->metricsCollector->calculateMetrics($results);

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            $finalResult = [
                'test_id' => $testId,
                'config' => $testConfig,
                'results' => $results,
                'metrics' => $metrics,
                'execution_time' => $totalTime,
                'timestamp' => date('c')
            ];

            $this->logger->info('Load test completed', [
                'test_id' => $testId,
                'execution_time' => $totalTime . 'ms',
                'status' => 'success'
            ]);

            return $finalResult;

        } catch (Exception $e) {
            $this->logger->error('Load test failed', [
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
                'metrics' => $this->metricsCollector->collectSystemMetrics()
            ];

            // Small delay between batches
            usleep(100000); // 100ms
        }

        return $results;
    }
}

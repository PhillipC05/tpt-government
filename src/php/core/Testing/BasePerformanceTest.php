<?php
/**
 * TPT Government Platform - Base Performance Test
 *
 * Base class for all performance test types
 */

abstract class BasePerformanceTest
{
    protected $logger;
    protected $metricsCollector;
    protected $config;

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, PerformanceMetricsCollector $metricsCollector, $config)
    {
        $this->logger = $logger;
        $this->metricsCollector = $metricsCollector;
        $this->config = $config;
    }

    /**
     * Execute the test
     */
    abstract public function execute($endpoint, $param1 = null, $param2 = null);

    /**
     * Warm up the system
     */
    protected function warmUpSystem($endpoint)
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
     * Execute concurrent requests
     */
    protected function executeConcurrentRequests($endpoint, $concurrency)
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
    protected function createCurlHandle($endpoint)
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
     * Make HTTP request
     */
    protected function makeRequest($method, $endpoint, $data = [])
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
     * Process data batch for volume testing
     */
    protected function processDataBatch($endpoint, $batchSize)
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
     * Perform detailed system check
     */
    protected function performDetailedCheck()
    {
        return $this->metricsCollector->performDetailedCheck();
    }
}

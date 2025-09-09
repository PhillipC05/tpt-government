<?php
/**
 * TPT Government Platform - Performance Report Generator
 *
 * Generates performance reports and HTML output
 */

class PerformanceReportGenerator
{
    private $config;

    /**
     * Constructor
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Generate performance report
     */
    public function generateReport($testId, $testConfig, $results, $metrics)
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
                'average_response_time' => $metrics['response_time']['avg'],
                'p95_response_time' => $metrics['response_time']['p95'],
                'throughput' => $metrics['throughput']['requests_per_second'],
                'error_rate' => $metrics['error_rate']['error_rate']
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
            'response_time_ok' => $metrics['response_time']['p95'] <= $this->config['target_response_time'],
            'error_rate_ok' => $metrics['error_rate']['error_rate'] <= $this->config['target_error_rate'],
            'throughput_ok' => $metrics['throughput']['requests_per_second'] >= $this->config['target_throughput'],
            'memory_ok' => $metrics['memory_usage']['max'] <= $this->config['memory_threshold'],
            'cpu_ok' => $metrics['cpu_usage']['max'] <= $this->config['cpu_threshold']
        ];
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations($metrics)
    {
        $recommendations = [];

        if ($metrics['response_time']['p95'] > $this->config['target_response_time']) {
            $recommendations[] = 'Consider optimizing database queries or implementing caching';
        }

        if ($metrics['error_rate']['error_rate'] > $this->config['target_error_rate']) {
            $recommendations[] = 'Investigate error causes and improve error handling';
        }

        if ($metrics['throughput']['requests_per_second'] < $this->config['target_throughput']) {
            $recommendations[] = 'Consider horizontal scaling or performance optimization';
        }

        if ($metrics['memory_usage']['max'] > $this->config['memory_threshold']) {
            $recommendations[] = 'Monitor memory usage and consider memory optimization';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Performance is within acceptable limits';
        }

        return $recommendations;
    }

    /**
     * Generate HTML performance report
     */
    public function generateHtmlReport($result, $testId)
    {
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
        if (!is_dir(dirname($htmlFilename))) {
            mkdir(dirname($htmlFilename), 0755, true);
        }
        file_put_contents($htmlFilename, $html);

        return $htmlFilename;
    }
}

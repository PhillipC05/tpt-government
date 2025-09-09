<?php
/**
 * TPT Government Platform - Test Suite Manager
 *
 * Comprehensive test suite management with parallel execution,
 * coverage reporting, and automated test discovery
 */

class TestSuite
{
    private $logger;
    private $config;
    private $testCases = [];
    private $results = [];
    private $coverageData = [];
    private $performanceData = [];

    /**
     * Test suite configuration
     */
    const DEFAULT_CONFIG = [
        'parallel_execution' => false,
        'max_workers' => 4,
        'timeout' => 300, // 5 minutes
        'memory_limit' => '256M',
        'coverage_enabled' => true,
        'coverage_format' => 'html',
        'performance_enabled' => true,
        'fail_fast' => false,
        'verbose' => false,
        'test_patterns' => ['*Test.php', '*TestCase.php'],
        'exclude_patterns' => ['/vendor/', '/node_modules/', '/tests/fixtures/']
    ];

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);

        // Set PHP configuration for testing
        ini_set('memory_limit', $this->config['memory_limit']);
        set_time_limit($this->config['timeout']);
    }

    /**
     * Add a test case to the suite
     */
    public function addTestCase(TestCase $testCase)
    {
        $this->testCases[] = $testCase;
        return $this;
    }

    /**
     * Add multiple test cases
     */
    public function addTestCases(array $testCases)
    {
        foreach ($testCases as $testCase) {
            if ($testCase instanceof TestCase) {
                $this->addTestCase($testCase);
            }
        }
        return $this;
    }

    /**
     * Discover and load test cases from directories
     */
    public function discoverTests($directories = ['tests/'])
    {
        $this->logger->info('Discovering test cases', ['directories' => $directories]);

        $discoveredTests = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $this->logger->warning('Test directory not found', ['directory' => $directory]);
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($this->isTestFile($file)) {
                    $testCase = $this->loadTestCase($file->getPathname());
                    if ($testCase) {
                        $discoveredTests[] = $testCase;
                        $this->logger->debug('Discovered test case', [
                            'file' => $file->getPathname(),
                            'class' => get_class($testCase)
                        ]);
                    }
                }
            }
        }

        $this->addTestCases($discoveredTests);

        $this->logger->info('Test discovery completed', [
            'discovered_tests' => count($discoveredTests)
        ]);

        return $discoveredTests;
    }

    /**
     * Check if file is a test file
     */
    private function isTestFile($file)
    {
        if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'inc'])) {
            return false;
        }

        $fileName = $file->getFilename();
        $filePath = $file->getPathname();

        // Check include patterns
        foreach ($this->config['test_patterns'] as $pattern) {
            if (fnmatch($pattern, $fileName)) {
                // Check exclude patterns
                foreach ($this->config['exclude_patterns'] as $excludePattern) {
                    if (preg_match($excludePattern, $filePath)) {
                        return false;
                    }
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Load test case from file
     */
    private function loadTestCase($filePath)
    {
        try {
            // Include the file
            require_once $filePath;

            // Find test classes in the file
            $classes = $this->findTestClasses($filePath);

            if (empty($classes)) {
                return null;
            }

            // Use the first test class found
            $className = $classes[0];

            if (!class_exists($className)) {
                $this->logger->warning('Test class not found after loading', [
                    'file' => $filePath,
                    'class' => $className
                ]);
                return null;
            }

            // Create instance
            $testCase = new $className();

            if (!$testCase instanceof TestCase) {
                $this->logger->warning('Class does not extend TestCase', [
                    'file' => $filePath,
                    'class' => $className
                ]);
                return null;
            }

            return $testCase;

        } catch (Exception $e) {
            $this->logger->error('Failed to load test case', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find test classes in a file
     */
    private function findTestClasses($filePath)
    {
        $content = file_get_contents($filePath);
        $classes = [];

        // Match class declarations
        if (preg_match_all('/\bclass\s+(\w+)/i', $content, $matches)) {
            foreach ($matches[1] as $className) {
                // Check if class extends TestCase or has test methods
                if ($this->isTestClass($content, $className)) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }

    /**
     * Check if class is a test class
     */
    private function isTestClass($content, $className)
    {
        // Check if class extends TestCase
        if (preg_match('/class\s+' . preg_quote($className) . '\s+extends\s+TestCase/i', $content)) {
            return true;
        }

        // Check if class has test methods
        if (preg_match('/function\s+test\w*\s*\(/i', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Run all test cases in the suite
     */
    public function run()
    {
        $this->logger->info('Starting test suite execution', [
            'test_cases' => count($this->testCases),
            'parallel' => $this->config['parallel_execution']
        ]);

        $startTime = microtime(true);

        if ($this->config['parallel_execution']) {
            $this->results = $this->runParallel();
        } else {
            $this->results = $this->runSequential();
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        // Generate final report
        $report = $this->generateSuiteReport($totalTime);

        $this->logger->info('Test suite execution completed', [
            'total_time' => $totalTime . 'ms',
            'success_rate' => $report['summary']['success_rate'] . '%'
        ]);

        return $report;
    }

    /**
     * Run tests sequentially
     */
    private function runSequential()
    {
        $results = [];

        foreach ($this->testCases as $testCase) {
            $result = $this->runTestCase($testCase);
            $results[] = $result;

            if ($this->config['fail_fast'] && $result['status'] === TestCase::STATUS_FAILED) {
                $this->logger->info('Stopping execution due to fail-fast mode');
                break;
            }
        }

        return $results;
    }

    /**
     * Run tests in parallel (simplified implementation)
     */
    private function runParallel()
    {
        $results = [];
        $workers = [];
        $maxWorkers = min($this->config['max_workers'], count($this->testCases));

        // For simplicity, we'll implement a basic parallel execution
        // In a real implementation, you'd use proper process management
        $chunks = array_chunk($this->testCases, ceil(count($this->testCases) / $maxWorkers));

        foreach ($chunks as $chunk) {
            $workerResults = [];
            foreach ($chunk as $testCase) {
                $workerResults[] = $this->runTestCase($testCase);
            }
            $results = array_merge($results, $workerResults);
        }

        return $results;
    }

    /**
     * Run a single test case
     */
    private function runTestCase(TestCase $testCase)
    {
        $className = get_class($testCase);
        $this->logger->debug('Running test case', ['class' => $className]);

        try {
            $result = $testCase->run();

            if ($this->config['verbose']) {
                $this->logger->info('Test case completed', [
                    'class' => $className,
                    'status' => $result['status'],
                    'assertions' => $result['assertions']
                ]);
            }

            return array_merge($result, [
                'class' => $className,
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            $this->logger->error('Test case execution failed', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);

            return [
                'class' => $className,
                'status' => TestCase::STATUS_ERROR,
                'assertions' => 0,
                'errors' => [['message' => $e->getMessage()]],
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Generate comprehensive test suite report
     */
    public function generateSuiteReport($totalTime)
    {
        $summary = $this->calculateSummary();

        $report = [
            'timestamp' => date('c'),
            'execution_time' => $totalTime,
            'summary' => $summary,
            'test_results' => $this->results,
            'coverage' => $this->config['coverage_enabled'] ? $this->generateCoverageReport() : null,
            'performance' => $this->config['performance_enabled'] ? $this->performanceData : null,
            'recommendations' => $this->generateRecommendations($summary)
        ];

        return $report;
    }

    /**
     * Calculate test suite summary
     */
    private function calculateSummary()
    {
        $summary = [
            'total_test_cases' => count($this->results),
            'total_assertions' => 0,
            'passed' => 0,
            'failed' => 0,
            'errors' => 0,
            'skipped' => 0,
            'incomplete' => 0,
            'success_rate' => 0,
            'average_execution_time' => 0
        ];

        $totalExecutionTime = 0;

        foreach ($this->results as $result) {
            $summary['total_assertions'] += $result['assertions'] ?? 0;
            $summary[$result['status']]++;
            $totalExecutionTime += $result['execution_time'] ?? 0;
        }

        $summary['success_rate'] = $summary['total_test_cases'] > 0 ?
            round(($summary['passed'] / $summary['total_test_cases']) * 100, 2) : 0;

        $summary['average_execution_time'] = $summary['total_test_cases'] > 0 ?
            round($totalExecutionTime / $summary['total_test_cases'], 2) : 0;

        return $summary;
    }

    /**
     * Generate coverage report
     */
    private function generateCoverageReport()
    {
        // This would integrate with Xdebug or PCOV for actual coverage
        // For now, return a placeholder structure
        return [
            'total_lines' => 0,
            'covered_lines' => 0,
            'coverage_percentage' => 0,
            'files_coverage' => [],
            'note' => 'Coverage reporting requires Xdebug or PCOV extension'
        ];
    }

    /**
     * Generate test recommendations
     */
    private function generateRecommendations($summary)
    {
        $recommendations = [];

        if ($summary['success_rate'] < 80) {
            $recommendations[] = [
                'type' => 'test_quality',
                'priority' => 'high',
                'message' => 'Improve test success rate (currently ' . $summary['success_rate'] . '%)',
                'suggestion' => 'Review and fix failing tests, improve test isolation'
            ];
        }

        if ($summary['errors'] > 0) {
            $recommendations[] = [
                'type' => 'error_handling',
                'priority' => 'high',
                'message' => 'Fix ' . $summary['errors'] . ' test execution errors',
                'suggestion' => 'Check test setup, dependencies, and exception handling'
            ];
        }

        if ($summary['skipped'] > $summary['total_test_cases'] * 0.1) {
            $recommendations[] = [
                'type' => 'test_coverage',
                'priority' => 'medium',
                'message' => 'High number of skipped tests (' . $summary['skipped'] . ')',
                'suggestion' => 'Review skip conditions and implement skipped tests'
            ];
        }

        if ($summary['average_execution_time'] > 1000) { // More than 1 second per test
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Slow test execution (' . $summary['average_execution_time'] . 'ms average)',
                'suggestion' => 'Optimize test setup, reduce database operations, use mocks'
            ];
        }

        return $recommendations;
    }

    /**
     * Filter test cases by pattern
     */
    public function filterTests($pattern)
    {
        $filtered = [];

        foreach ($this->testCases as $testCase) {
            $className = get_class($testCase);
            if (preg_match($pattern, $className)) {
                $filtered[] = $testCase;
            }
        }

        $this->testCases = $filtered;
        return $this;
    }

    /**
     * Get test cases by status
     */
    public function getTestsByStatus($status)
    {
        return array_filter($this->results, function($result) use ($status) {
            return $result['status'] === $status;
        });
    }

    /**
     * Export test results
     */
    public function exportResults($format = 'json')
    {
        $report = $this->generateSuiteReport(0);

        switch ($format) {
            case 'json':
                return json_encode($report, JSON_PRETTY_PRINT);
            case 'xml':
                return $this->convertToXML($report);
            case 'html':
                return $this->generateHtmlReport($report);
            default:
                return $report;
        }
    }

    /**
     * Convert results to XML format
     */
    private function convertToXML($report)
    {
        $xml = new SimpleXMLElement('<testsuites/>');

        $suite = $xml->addChild('testsuite');
        $suite->addAttribute('name', 'TPT Test Suite');
        $suite->addAttribute('tests', $report['summary']['total_test_cases']);
        $suite->addAttribute('failures', $report['summary']['failed']);
        $suite->addAttribute('errors', $report['summary']['errors']);
        $suite->addAttribute('skipped', $report['summary']['skipped']);
        $suite->addAttribute('time', $report['execution_time'] / 1000);

        foreach ($report['test_results'] as $result) {
            $testcase = $suite->addChild('testcase');
            $testcase->addAttribute('name', $result['class']);
            $testcase->addAttribute('time', ($result['execution_time'] ?? 0) / 1000);

            if ($result['status'] === TestCase::STATUS_FAILED) {
                $failure = $testcase->addChild('failure');
                $failure->addAttribute('message', 'Test failed');
                if (isset($result['errors'])) {
                    $failure->addChild('description', json_encode($result['errors']));
                }
            } elseif ($result['status'] === TestCase::STATUS_ERROR) {
                $error = $testcase->addChild('error');
                $error->addAttribute('message', 'Test error');
                if (isset($result['errors'])) {
                    $error->addChild('description', json_encode($result['errors']));
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport($report)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Test Suite Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .summary { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d1ecf1; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; }
        .danger { background: #f8d7da; border: 1px solid #f5c6cb; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .status-passed { color: #28a745; }
        .status-failed { color: #dc3545; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-skipped { color: #6c757d; }
        .test-case { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Test Suite Report</h1>

    <div class="summary">
        <h2>Summary</h2>
        <p><strong>Generated:</strong> ' . $report['timestamp'] . '</p>
        <p><strong>Execution Time:</strong> ' . $report['execution_time'] . 'ms</p>
        <p><strong>Total Test Cases:</strong> ' . $report['summary']['total_test_cases'] . '</p>
        <p><strong>Success Rate:</strong> ' . $report['summary']['success_rate'] . '%</p>
        <p><strong>Total Assertions:</strong> ' . $report['summary']['total_assertions'] . '</p>
        <p><strong>Average Execution Time:</strong> ' . $report['summary']['average_execution_time'] . 'ms</p>
    </div>

    <h2>Test Results</h2>
    <table>
        <tr>
            <th>Status</th>
            <th>Count</th>
            <th>Percentage</th>
        </tr>
        <tr>
            <td class="status-passed">Passed</td>
            <td>' . $report['summary']['passed'] . '</td>
            <td>' . ($report['summary']['total_test_cases'] > 0 ? round(($report['summary']['passed'] / $report['summary']['total_test_cases']) * 100, 2) : 0) . '%</td>
        </tr>
        <tr>
            <td class="status-failed">Failed</td>
            <td>' . $report['summary']['failed'] . '</td>
            <td>' . ($report['summary']['total_test_cases'] > 0 ? round(($report['summary']['failed'] / $report['summary']['total_test_cases']) * 100, 2) : 0) . '%</td>
        </tr>
        <tr>
            <td class="status-error">Errors</td>
            <td>' . $report['summary']['errors'] . '</td>
            <td>' . ($report['summary']['total_test_cases'] > 0 ? round(($report['summary']['errors'] / $report['summary']['total_test_cases']) * 100, 2) : 0) . '%</td>
        </tr>
        <tr>
            <td class="status-skipped">Skipped</td>
            <td>' . $report['summary']['skipped'] . '</td>
            <td>' . ($report['summary']['total_test_cases'] > 0 ? round(($report['summary']['skipped'] / $report['summary']['total_test_cases']) * 100, 2) : 0) . '%</td>
        </tr>
    </table>';

        if (!empty($report['recommendations'])) {
            $html .= '<h2>Recommendations</h2>';
            foreach ($report['recommendations'] as $rec) {
                $html .= '<div class="test-case ' . $rec['priority'] . '">
                    <h3>' . ucfirst($rec['type']) . ' - ' . ucfirst($rec['priority']) . ' Priority</h3>
                    <p>' . $rec['message'] . '</p>
                    <p><em>' . $rec['suggestion'] . '</em></p>
                </div>';
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get test suite configuration
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * Set test suite configuration
     */
    public function setConfiguration($key, $value)
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Get number of test cases
     */
    public function count()
    {
        return count($this->testCases);
    }

    /**
     * Clear all test cases
     */
    public function clear()
    {
        $this->testCases = [];
        $this->results = [];
        return $this;
    }
}

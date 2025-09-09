<?php
/**
 * TPT Government Platform - Comprehensive Test Runner
 *
 * Advanced testing framework with unit tests, integration tests,
 * performance tests, and automated test execution
 */

class TestRunner
{
    private $logger;
    private $config;
    private $testResults = [];
    private $coverageData = [];
    private $performanceMetrics = [];

    /**
     * Test types
     */
    const TEST_UNIT = 'unit';
    const TEST_INTEGRATION = 'integration';
    const TEST_FUNCTIONAL = 'functional';
    const TEST_PERFORMANCE = 'performance';
    const TEST_SECURITY = 'security';
    const TEST_ACCEPTANCE = 'acceptance';

    /**
     * Test result statuses
     */
    const STATUS_PASSED = 'passed';
    const STATUS_FAILED = 'failed';
    const STATUS_ERROR = 'error';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_INCOMPLETE = 'incomplete';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'test_paths' => ['tests/'],
            'bootstrap_file' => 'tests/bootstrap.php',
            'coverage_enabled' => true,
            'coverage_path' => 'tests/coverage/',
            'performance_enabled' => true,
            'parallel_execution' => false,
            'max_execution_time' => 300, // 5 minutes
            'memory_limit' => '256M',
            'exclude_patterns' => ['/vendor/', '/node_modules/']
        ], $config);

        // Set memory limit for tests
        ini_set('memory_limit', $this->config['memory_limit']);
    }

    /**
     * Run all tests
     */
    public function runAllTests($types = null)
    {
        $types = $types ?: [self::TEST_UNIT, self::TEST_INTEGRATION, self::TEST_FUNCTIONAL];

        $this->logger->info('Starting comprehensive test suite', [
            'test_types' => $types,
            'timestamp' => date('c')
        ]);

        $startTime = microtime(true);
        $results = [];

        foreach ($types as $type) {
            $results[$type] = $this->runTestsByType($type);
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        // Generate comprehensive report
        $report = $this->generateTestReport($results, $totalTime);

        $this->logger->info('Test suite completed', [
            'total_execution_time' => $totalTime . 'ms',
            'overall_success_rate' => $report['summary']['success_rate'] . '%'
        ]);

        return $report;
    }

    /**
     * Run tests by type
     */
    public function runTestsByType($type)
    {
        $this->logger->info("Running {$type} tests");

        $testFiles = $this->discoverTestFiles($type);
        $results = [];

        foreach ($testFiles as $file) {
            $results[] = $this->runTestFile($file, $type);
        }

        return [
            'type' => $type,
            'files_tested' => count($testFiles),
            'results' => $results,
            'summary' => $this->summarizeResults($results)
        ];
    }

    /**
     * Run single test file
     */
    public function runTestFile($filePath, $type)
    {
        $startTime = microtime(true);

        try {
            // Include bootstrap if it exists
            if (file_exists($this->config['bootstrap_file'])) {
                require_once $this->config['bootstrap_file'];
            }

            // Start coverage collection if enabled
            if ($this->config['coverage_enabled'] && function_exists('xdebug_start_code_coverage')) {
                xdebug_start_code_coverage();
            }

            // Load and run the test file
            ob_start();
            $testResult = $this->executeTestFile($filePath, $type);
            $output = ob_get_clean();

            // Stop coverage collection
            if ($this->config['coverage_enabled'] && function_exists('xdebug_stop_code_coverage')) {
                $this->coverageData[$filePath] = xdebug_get_code_coverage();
                xdebug_stop_code_coverage();
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'file' => $filePath,
                'type' => $type,
                'status' => $testResult['status'],
                'execution_time' => $executionTime,
                'assertions' => $testResult['assertions'] ?? 0,
                'errors' => $testResult['errors'] ?? [],
                'output' => $output,
                'timestamp' => date('c')
            ];

        } catch (Exception $e) {
            return [
                'file' => $filePath,
                'type' => $type,
                'status' => self::STATUS_ERROR,
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Execute test file
     */
    private function executeTestFile($filePath, $type)
    {
        // Create test instance based on type
        switch ($type) {
            case self::TEST_UNIT:
                return $this->runUnitTest($filePath);
            case self::TEST_INTEGRATION:
                return $this->runIntegrationTest($filePath);
            case self::TEST_FUNCTIONAL:
                return $this->runFunctionalTest($filePath);
            case self::TEST_PERFORMANCE:
                return $this->runPerformanceTest($filePath);
            case self::TEST_SECURITY:
                return $this->runSecurityTest($filePath);
            default:
                throw new Exception("Unknown test type: {$type}");
        }
    }

    /**
     * Run unit test
     */
    private function runUnitTest($filePath)
    {
        require_once $filePath;

        // Assume the file contains a class that extends TestCase
        $className = $this->getClassNameFromFile($filePath);

        if (!class_exists($className)) {
            throw new Exception("Test class {$className} not found in {$filePath}");
        }

        $testInstance = new $className();
        return $testInstance->run();
    }

    /**
     * Run integration test
     */
    private function runIntegrationTest($filePath)
    {
        // Integration tests might need database setup, external services, etc.
        require_once $filePath;

        $className = $this->getClassNameFromFile($filePath);
        $testInstance = new $className();

        // Set up integration test environment
        $this->setupIntegrationEnvironment();

        $result = $testInstance->run();

        // Clean up integration test environment
        $this->cleanupIntegrationEnvironment();

        return $result;
    }

    /**
     * Run functional test
     */
    private function runFunctionalTest($filePath)
    {
        // Functional tests test the application from end to end
        require_once $filePath;

        $className = $this->getClassNameFromFile($filePath);
        $testInstance = new $className();

        // Start a test server or use existing one
        $this->setupFunctionalTestEnvironment();

        $result = $testInstance->run();

        $this->cleanupFunctionalTestEnvironment();

        return $result;
    }

    /**
     * Run performance test
     */
    private function runPerformanceTest($filePath)
    {
        require_once $filePath;

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $className = $this->getClassNameFromFile($filePath);
        $testInstance = new $className();
        $result = $testInstance->run();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        // Add performance metrics
        $result['performance'] = [
            'execution_time' => round(($endTime - $startTime) * 1000, 2),
            'memory_used' => $endMemory - $startMemory,
            'memory_peak' => memory_get_peak_usage(true)
        ];

        $this->performanceMetrics[$filePath] = $result['performance'];

        return $result;
    }

    /**
     * Run security test
     */
    private function runSecurityTest($filePath)
    {
        require_once $filePath;

        $className = $this->getClassNameFromFile($filePath);
        $testInstance = new $className();

        // Run security-specific setup
        $this->setupSecurityTestEnvironment();

        $result = $testInstance->run();

        $this->cleanupSecurityTestEnvironment();

        return $result;
    }

    /**
     * Discover test files by type
     */
    private function discoverTestFiles($type)
    {
        $files = [];
        $patterns = $this->getTestFilePatterns($type);

        foreach ($this->config['test_paths'] as $testPath) {
            if (!is_dir($testPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($testPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() &&
                    in_array($file->getExtension(), ['php', 'inc']) &&
                    $this->matchesTestPattern($file->getPathname(), $patterns)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Get test file patterns for type
     */
    private function getTestFilePatterns($type)
    {
        $patterns = [
            self::TEST_UNIT => ['*Test.php', 'Unit/*Test.php'],
            self::TEST_INTEGRATION => ['*IntegrationTest.php', 'Integration/*Test.php'],
            self::TEST_FUNCTIONAL => ['*FunctionalTest.php', 'Functional/*Test.php'],
            self::TEST_PERFORMANCE => ['*PerformanceTest.php', 'Performance/*Test.php'],
            self::TEST_SECURITY => ['*SecurityTest.php', 'Security/*Test.php'],
            self::TEST_ACCEPTANCE => ['*AcceptanceTest.php', 'Acceptance/*Test.php']
        ];

        return $patterns[$type] ?? ['*Test.php'];
    }

    /**
     * Check if file matches test patterns
     */
    private function matchesTestPattern($filePath, $patterns)
    {
        $fileName = basename($filePath);

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $fileName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get class name from file
     */
    private function getClassNameFromFile($filePath)
    {
        $content = file_get_contents($filePath);
        $pattern = '/\bclass\s+(\w+)/i';

        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }

        // Fallback to filename without extension
        return pathinfo($filePath, PATHINFO_FILENAME);
    }

    /**
     * Summarize test results
     */
    private function summarizeResults($results)
    {
        $summary = [
            'total_tests' => count($results),
            'passed' => 0,
            'failed' => 0,
            'errors' => 0,
            'skipped' => 0,
            'incomplete' => 0,
            'total_assertions' => 0,
            'total_execution_time' => 0
        ];

        foreach ($results as $result) {
            $summary[$result['status']]++;
            $summary['total_assertions'] += $result['assertions'] ?? 0;
            $summary['total_execution_time'] += $result['execution_time'];
        }

        $summary['success_rate'] = $summary['total_tests'] > 0 ?
            round(($summary['passed'] / $summary['total_tests']) * 100, 2) : 0;

        return $summary;
    }

    /**
     * Generate comprehensive test report
     */
    public function generateTestReport($results, $totalTime)
    {
        $overallSummary = [
            'total_execution_time' => $totalTime,
            'total_test_types' => count($results),
            'total_files_tested' => array_sum(array_column($results, 'files_tested')),
            'success_rate' => 0,
            'total_assertions' => 0
        ];

        $typeSummaries = [];
        foreach ($results as $type => $typeResult) {
            $typeSummaries[$type] = $typeResult['summary'];
            $overallSummary['total_assertions'] += $typeResult['summary']['total_assertions'];
        }

        $totalTests = array_sum(array_column($typeSummaries, 'total_tests'));
        $totalPassed = array_sum(array_column($typeSummaries, 'passed'));
        $overallSummary['success_rate'] = $totalTests > 0 ?
            round(($totalPassed / $totalTests) * 100, 2) : 0;

        return [
            'timestamp' => date('c'),
            'summary' => $overallSummary,
            'by_type' => $typeSummaries,
            'detailed_results' => $results,
            'coverage' => $this->config['coverage_enabled'] ? $this->generateCoverageReport() : null,
            'performance' => $this->performanceMetrics,
            'recommendations' => $this->generateTestRecommendations($results)
        ];
    }

    /**
     * Generate coverage report
     */
    private function generateCoverageReport()
    {
        if (empty($this->coverageData)) {
            return null;
        }

        $totalLines = 0;
        $coveredLines = 0;

        foreach ($this->coverageData as $file => $coverage) {
            foreach ($coverage as $line => $covered) {
                if ($covered > 0) {
                    $coveredLines++;
                }
                $totalLines++;
            }
        }

        return [
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'coverage_percentage' => $totalLines > 0 ?
                round(($coveredLines / $totalLines) * 100, 2) : 0,
            'files_coverage' => $this->coverageData
        ];
    }

    /**
     * Generate test recommendations
     */
    private function generateTestRecommendations($results)
    {
        $recommendations = [];

        foreach ($results as $type => $typeResult) {
            $summary = $typeResult['summary'];

            if ($summary['success_rate'] < 80) {
                $recommendations[] = [
                    'type' => 'test_quality',
                    'priority' => 'high',
                    'message' => "Improve {$type} test success rate (currently {$summary['success_rate']}%)",
                    'suggestion' => 'Review and fix failing tests, add more test cases'
                ];
            }

            if ($summary['errors'] > 0) {
                $recommendations[] = [
                    'type' => 'error_handling',
                    'priority' => 'high',
                    'message' => "Fix {$summary['errors']} test errors in {$type} tests",
                    'suggestion' => 'Check test setup, dependencies, and error handling'
                ];
            }
        }

        if ($this->config['coverage_enabled']) {
            $coverage = $this->generateCoverageReport();
            if ($coverage && $coverage['coverage_percentage'] < 70) {
                $recommendations[] = [
                    'type' => 'coverage',
                    'priority' => 'medium',
                    'message' => "Improve test coverage (currently {$coverage['coverage_percentage']}%)",
                    'suggestion' => 'Add more test cases to cover uncovered code paths'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Setup integration test environment
     */
    private function setupIntegrationEnvironment()
    {
        // Set up database connections, external services, etc.
        // This would be customized based on your integration test needs
    }

    /**
     * Cleanup integration test environment
     */
    private function cleanupIntegrationEnvironment()
    {
        // Clean up database, reset external services, etc.
    }

    /**
     * Setup functional test environment
     */
    private function setupFunctionalTestEnvironment()
    {
        // Start test web server, set up test database, etc.
    }

    /**
     * Cleanup functional test environment
     */
    private function cleanupFunctionalTestEnvironment()
    {
        // Stop test server, clean up test data, etc.
    }

    /**
     * Setup security test environment
     */
    private function setupSecurityTestEnvironment()
    {
        // Set up security testing tools, mock services, etc.
    }

    /**
     * Cleanup security test environment
     */
    private function cleanupSecurityTestEnvironment()
    {
        // Clean up security test artifacts
    }

    /**
     * Export test results
     */
    public function exportResults($format = 'json')
    {
        $results = $this->runAllTests();

        switch ($format) {
            case 'json':
                return json_encode($results, JSON_PRETTY_PRINT);
            case 'xml':
                return $this->convertToXML($results);
            case 'html':
                return $this->generateHtmlReport($results);
            default:
                return $results;
        }
    }

    /**
     * Convert results to XML
     */
    private function convertToXML($results)
    {
        $xml = new SimpleXMLElement('<testsuites/>');

        foreach ($results['by_type'] as $type => $typeResult) {
            $suite = $xml->addChild('testsuite');
            $suite->addAttribute('name', $type);
            $suite->addAttribute('tests', $typeResult['total_tests']);
            $suite->addAttribute('failures', $typeResult['failed']);
            $suite->addAttribute('errors', $typeResult['errors']);
            $suite->addAttribute('time', $typeResult['total_execution_time']);
        }

        return $xml->asXML();
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport($results)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Test Results Report</title>
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
    </style>
</head>
<body>
    <h1>Test Results Report</h1>

    <div class="summary">
        <h2>Overall Summary</h2>
        <p><strong>Generated:</strong> ' . $results['timestamp'] . '</p>
        <p><strong>Total Execution Time:</strong> ' . $results['summary']['total_execution_time'] . 'ms</p>
        <p><strong>Success Rate:</strong> ' . $results['summary']['success_rate'] . '%</p>
        <p><strong>Total Files Tested:</strong> ' . $results['summary']['total_files_tested'] . '</p>
        <p><strong>Total Assertions:</strong> ' . $results['summary']['total_assertions'] . '</p>
    </div>';

        foreach ($results['by_type'] as $type => $typeResult) {
            $html .= '<h2>' . ucfirst($type) . ' Tests</h2>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Total Tests</td>
                    <td>' . $typeResult['total_tests'] . '</td>
                </tr>
                <tr>
                    <td>Passed</td>
                    <td class="status-passed">' . $typeResult['passed'] . '</td>
                </tr>
                <tr>
                    <td>Failed</td>
                    <td class="status-failed">' . $typeResult['failed'] . '</td>
                </tr>
                <tr>
                    <td>Errors</td>
                    <td class="status-error">' . $typeResult['errors'] . '</td>
                </tr>
                <tr>
                    <td>Success Rate</td>
                    <td>' . $typeResult['success_rate'] . '%</td>
                </tr>
            </table>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get test configuration
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * Set test configuration
     */
    public function setConfiguration($key, $value)
    {
        $this->config[$key] = $value;
    }
}

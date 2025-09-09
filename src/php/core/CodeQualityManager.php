<?php
/**
 * TPT Government Platform - Code Quality Manager
 *
 * Comprehensive code quality analysis system with duplication detection,
 * coding standards enforcement, complexity analysis, and quality metrics
 */

class CodeQualityManager
{
    private $logger;
    private $config;
    private $qualityMetrics = [];
    private $duplicationResults = [];
    private $standardsViolations = [];
    private $complexityAnalysis = [];

    /**
     * Quality metric thresholds
     */
    const COMPLEXITY_WARNING = 10;
    const COMPLEXITY_CRITICAL = 20;
    const DUPLICATION_WARNING = 5;
    const DUPLICATION_CRITICAL = 10;
    const LINES_PER_METHOD_WARNING = 30;
    const LINES_PER_METHOD_CRITICAL = 50;

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'scan_paths' => [SRC_PATH],
            'exclude_patterns' => ['/vendor/', '/node_modules/', '/tests/'],
            'min_complexity' => self::COMPLEXITY_WARNING,
            'max_complexity' => self::COMPLEXITY_CRITICAL,
            'min_duplication' => self::DUPLICATION_WARNING,
            'max_duplication' => self::DUPLICATION_CRITICAL,
            'standards_config' => []
        ], $config);
    }

    /**
     * Run complete code quality analysis
     */
    public function analyzeCodebase()
    {
        $this->logger->info('Starting comprehensive code quality analysis');

        $startTime = microtime(true);

        // Reset analysis results
        $this->resetAnalysis();

        // Scan codebase
        $files = $this->scanCodebase();

        // Analyze each file
        foreach ($files as $file) {
            $this->analyzeFile($file);
        }

        // Generate quality report
        $report = $this->generateQualityReport();

        $analysisTime = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info('Code quality analysis completed', [
            'files_analyzed' => count($files),
            'analysis_time_ms' => $analysisTime,
            'quality_score' => $report['overall_score']
        ]);

        return $report;
    }

    /**
     * Analyze a single file
     */
    public function analyzeFile($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $analysis = [
            'file_path' => $filePath,
            'file_size' => strlen($content),
            'line_count' => count($lines),
            'complexity' => $this->calculateComplexity($content),
            'duplication_score' => 0,
            'standards_violations' => $this->checkCodingStandards($content, $filePath),
            'metrics' => [
                'methods_count' => $this->countMethods($content),
                'classes_count' => $this->countClasses($content),
                'functions_count' => $this->countFunctions($content),
                'comment_lines' => $this->countCommentLines($lines),
                'empty_lines' => $this->countEmptyLines($lines),
                'longest_method' => $this->findLongestMethod($content)
            ]
        ];

        // Calculate code-to-comment ratio
        $codeLines = $analysis['line_count'] - $analysis['metrics']['comment_lines'] - $analysis['metrics']['empty_lines'];
        $analysis['code_to_comment_ratio'] = $analysis['metrics']['comment_lines'] > 0 ?
            round($codeLines / $analysis['metrics']['comment_lines'], 2) : 0;

        $this->qualityMetrics[$filePath] = $analysis;

        return $analysis;
    }

    /**
     * Detect code duplication across the codebase
     */
    public function detectDuplication()
    {
        $this->logger->info('Starting code duplication analysis');

        $files = $this->scanCodebase();
        $codeBlocks = [];

        // Extract code blocks from all files
        foreach ($files as $file) {
            $blocks = $this->extractCodeBlocks($file);
            $codeBlocks[$file] = $blocks;
        }

        // Find duplicates
        $duplicates = $this->findDuplicates($codeBlocks);

        $this->duplicationResults = [
            'total_files' => count($files),
            'total_blocks' => array_sum(array_map('count', $codeBlocks)),
            'duplicates_found' => count($duplicates),
            'duplicate_blocks' => $duplicates,
            'duplication_percentage' => $this->calculateDuplicationPercentage($duplicates, $codeBlocks)
        ];

        $this->logger->info('Code duplication analysis completed', [
            'duplicates_found' => count($duplicates),
            'duplication_percentage' => $this->duplicationResults['duplication_percentage']
        ]);

        return $this->duplicationResults;
    }

    /**
     * Check coding standards compliance
     */
    public function checkStandards()
    {
        $this->logger->info('Starting coding standards check');

        $files = $this->scanCodebase();
        $violations = [];

        foreach ($files as $file) {
            $fileViolations = $this->checkCodingStandards(file_get_contents($file), $file);
            if (!empty($fileViolations)) {
                $violations[$file] = $fileViolations;
            }
        }

        $this->standardsViolations = [
            'total_files' => count($files),
            'files_with_violations' => count($violations),
            'total_violations' => array_sum(array_map('count', $violations)),
            'violations_by_file' => $violations,
            'compliance_percentage' => $this->calculateCompliancePercentage($violations, $files)
        ];

        $this->logger->info('Coding standards check completed', [
            'files_with_violations' => count($violations),
            'total_violations' => $this->standardsViolations['total_violations'],
            'compliance_percentage' => $this->standardsViolations['compliance_percentage']
        ]);

        return $this->standardsViolations;
    }

    /**
     * Generate quality report
     */
    public function generateQualityReport()
    {
        $metrics = $this->qualityMetrics;

        $report = [
            'timestamp' => date('c'),
            'files_analyzed' => count($metrics),
            'overall_score' => $this->calculateOverallScore($metrics),
            'complexity_analysis' => $this->analyzeComplexity($metrics),
            'duplication_analysis' => $this->duplicationResults,
            'standards_analysis' => $this->standardsViolations,
            'recommendations' => $this->generateRecommendations($metrics),
            'file_metrics' => $metrics
        ];

        return $report;
    }

    /**
     * Calculate cyclomatic complexity
     */
    private function calculateComplexity($content)
    {
        $complexity = 1; // Base complexity

        // Count control structures
        $patterns = [
            '/\b(if|else|elseif|for|foreach|while|do|switch|case|catch|try)\b/i',
            '/\b(\?|:)\b/', // Ternary operators
            '/\b(and|or|&&|\|\|)\b/', // Logical operators
        ];

        foreach ($patterns as $pattern) {
            $matches = preg_match_all($pattern, $content);
            $complexity += $matches;
        }

        return $complexity;
    }

    /**
     * Count methods in a file
     */
    private function countMethods($content)
    {
        $pattern = '/\bfunction\s+\w+\s*\(/i';
        return preg_match_all($pattern, $content);
    }

    /**
     * Count classes in a file
     */
    private function countClasses($content)
    {
        $pattern = '/\bclass\s+\w+/i';
        return preg_match_all($pattern, $content);
    }

    /**
     * Count functions in a file
     */
    private function countFunctions($content)
    {
        $pattern = '/\bfunction\s+\w+\s*\(/i';
        return preg_match_all($pattern, $content);
    }

    /**
     * Count comment lines
     */
    private function countCommentLines($lines)
    {
        $commentLines = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strpos($trimmed, '//') === 0 ||
                strpos($trimmed, '#') === 0 ||
                strpos($trimmed, '/*') === 0 ||
                strpos($trimmed, '*') === 0 ||
                strpos($trimmed, '*/') !== false) {
                $commentLines++;
            }
        }

        return $commentLines;
    }

    /**
     * Count empty lines
     */
    private function countEmptyLines($lines)
    {
        $emptyLines = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $emptyLines++;
            }
        }

        return $emptyLines;
    }

    /**
     * Find the longest method
     */
    private function findLongestMethod($content)
    {
        $pattern = '/function\s+\w+\s*\([^}]*\)(?:\s*{[^}]*})?/s';
        preg_match_all($pattern, $content, $matches);

        $longest = 0;
        foreach ($matches[0] as $method) {
            $lines = substr_count($method, "\n") + 1;
            $longest = max($longest, $lines);
        }

        return $longest;
    }

    /**
     * Check coding standards
     */
    private function checkCodingStandards($content, $filePath)
    {
        $violations = [];

        // Check for PHP opening tag
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php' &&
            strpos($content, '<?php') !== 0) {
            $violations[] = 'Missing or incorrect PHP opening tag';
        }

        // Check line length (max 120 characters)
        $lines = explode("\n", $content);
        foreach ($lines as $lineNumber => $line) {
            if (strlen($line) > 120) {
                $violations[] = "Line " . ($lineNumber + 1) . " exceeds 120 characters";
            }
        }

        // Check for trailing whitespace
        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/\s+$/', $line)) {
                $violations[] = "Line " . ($lineNumber + 1) . " has trailing whitespace";
            }
        }

        // Check for consistent indentation (spaces vs tabs)
        $hasSpaces = strpos($content, '    ') !== false;
        $hasTabs = strpos($content, "\t") !== false;

        if ($hasSpaces && $hasTabs) {
            $violations[] = 'Mixed use of spaces and tabs for indentation';
        }

        // Check for proper naming conventions
        if (preg_match('/\bclass\s+[a-z]/', $content)) {
            $violations[] = 'Class names should start with uppercase letter';
        }

        if (preg_match('/\bfunction\s+[A-Z]/', $content)) {
            $violations[] = 'Function names should start with lowercase letter';
        }

        return $violations;
    }

    /**
     * Extract code blocks for duplication analysis
     */
    private function extractCodeBlocks($filePath)
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $blocks = [];
        $currentBlock = [];
        $inBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip comments and empty lines
            if (empty($trimmed) ||
                strpos($trimmed, '//') === 0 ||
                strpos($trimmed, '#') === 0 ||
                strpos($trimmed, '/*') === 0) {
                continue;
            }

            // Start of a code block
            if (preg_match('/\b(function|class|if|for|foreach|while|do|switch)\b/', $trimmed)) {
                if ($inBlock && count($currentBlock) >= 3) {
                    $blocks[] = implode("\n", $currentBlock);
                }
                $currentBlock = [$line];
                $inBlock = true;
            } elseif ($inBlock) {
                $currentBlock[] = $line;

                // End of block
                if (strpos($trimmed, '}') === 0 || count($currentBlock) >= 10) {
                    if (count($currentBlock) >= 3) {
                        $blocks[] = implode("\n", $currentBlock);
                    }
                    $currentBlock = [];
                    $inBlock = false;
                }
            }
        }

        return $blocks;
    }

    /**
     * Find duplicate code blocks
     */
    private function findDuplicates($codeBlocks)
    {
        $duplicates = [];
        $processed = [];

        foreach ($codeBlocks as $file => $blocks) {
            foreach ($blocks as $blockIndex => $block) {
                $blockHash = md5($block);

                if (isset($processed[$blockHash])) {
                    $existing = $processed[$blockHash];

                    if (!isset($duplicates[$blockHash])) {
                        $duplicates[$blockHash] = [
                            'code' => $block,
                            'occurrences' => [$existing],
                            'lines' => substr_count($block, "\n") + 1
                        ];
                    }

                    $duplicates[$blockHash]['occurrences'][] = [
                        'file' => $file,
                        'block_index' => $blockIndex
                    ];
                } else {
                    $processed[$blockHash] = [
                        'file' => $file,
                        'block_index' => $blockIndex
                    ];
                }
            }
        }

        return $duplicates;
    }

    /**
     * Calculate duplication percentage
     */
    private function calculateDuplicationPercentage($duplicates, $codeBlocks)
    {
        $totalBlocks = array_sum(array_map('count', $codeBlocks));
        $duplicateBlocks = 0;

        foreach ($duplicates as $duplicate) {
            $duplicateBlocks += count($duplicate['occurrences']);
        }

        return $totalBlocks > 0 ? round(($duplicateBlocks / $totalBlocks) * 100, 2) : 0;
    }

    /**
     * Calculate compliance percentage
     */
    private function calculateCompliancePercentage($violations, $files)
    {
        $compliantFiles = count($files) - count($violations);
        return count($files) > 0 ? round(($compliantFiles / count($files)) * 100, 2) : 100;
    }

    /**
     * Analyze complexity across all files
     */
    private function analyzeComplexity($metrics)
    {
        $complexityStats = [
            'average_complexity' => 0,
            'max_complexity' => 0,
            'files_above_warning' => 0,
            'files_above_critical' => 0,
            'complex_files' => []
        ];

        $totalComplexity = 0;
        $fileCount = count($metrics);

        foreach ($metrics as $file => $data) {
            $complexity = $data['complexity'];
            $totalComplexity += $complexity;

            if ($complexity > $complexityStats['max_complexity']) {
                $complexityStats['max_complexity'] = $complexity;
            }

            if ($complexity >= self::COMPLEXITY_CRITICAL) {
                $complexityStats['files_above_critical']++;
                $complexityStats['complex_files'][] = [
                    'file' => $file,
                    'complexity' => $complexity,
                    'severity' => 'critical'
                ];
            } elseif ($complexity >= self::COMPLEXITY_WARNING) {
                $complexityStats['files_above_warning']++;
                $complexityStats['complex_files'][] = [
                    'file' => $file,
                    'complexity' => $complexity,
                    'severity' => 'warning'
                ];
            }
        }

        $complexityStats['average_complexity'] = $fileCount > 0 ?
            round($totalComplexity / $fileCount, 2) : 0;

        return $complexityStats;
    }

    /**
     * Calculate overall quality score
     */
    private function calculateOverallScore($metrics)
    {
        if (empty($metrics)) {
            return 0;
        }

        $scores = [];

        foreach ($metrics as $data) {
            $score = 100;

            // Deduct points for complexity
            if ($data['complexity'] >= self::COMPLEXITY_CRITICAL) {
                $score -= 30;
            } elseif ($data['complexity'] >= self::COMPLEXITY_WARNING) {
                $score -= 15;
            }

            // Deduct points for long methods
            if ($data['metrics']['longest_method'] >= self::LINES_PER_METHOD_CRITICAL) {
                $score -= 20;
            } elseif ($data['metrics']['longest_method'] >= self::LINES_PER_METHOD_WARNING) {
                $score -= 10;
            }

            // Deduct points for poor documentation
            if ($data['code_to_comment_ratio'] > 20) {
                $score -= 10;
            }

            // Deduct points for standards violations
            $violations = count($data['standards_violations']);
            $score -= min($violations * 5, 25);

            $scores[] = max(0, $score);
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations($metrics)
    {
        $recommendations = [];

        // Complexity recommendations
        $complexFiles = array_filter($metrics, function($data) {
            return $data['complexity'] >= self::COMPLEXITY_WARNING;
        });

        if (!empty($complexFiles)) {
            $recommendations[] = [
                'type' => 'complexity',
                'priority' => 'high',
                'message' => 'Refactor ' . count($complexFiles) . ' files with high complexity',
                'files' => array_keys($complexFiles)
            ];
        }

        // Duplication recommendations
        if (!empty($this->duplicationResults['duplicate_blocks'])) {
            $recommendations[] = [
                'type' => 'duplication',
                'priority' => 'medium',
                'message' => 'Extract ' . count($this->duplicationResults['duplicate_blocks']) . ' duplicate code blocks into shared functions',
                'details' => $this->duplicationResults['duplicate_blocks']
            ];
        }

        // Standards recommendations
        if ($this->standardsViolations['compliance_percentage'] < 90) {
            $recommendations[] = [
                'type' => 'standards',
                'priority' => 'medium',
                'message' => 'Fix coding standards violations in ' . $this->standardsViolations['files_with_violations'] . ' files',
                'violations' => $this->standardsViolations['violations_by_file']
            ];
        }

        return $recommendations;
    }

    /**
     * Scan codebase for PHP files
     */
    private function scanCodebase()
    {
        $files = [];
        $scanPaths = $this->config['scan_paths'];
        $excludePatterns = $this->config['exclude_patterns'];

        foreach ($scanPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() &&
                    in_array($file->getExtension(), ['php', 'inc']) &&
                    $this->shouldIncludeFile($file->getPathname(), $excludePatterns)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Check if file should be included in analysis
     */
    private function shouldIncludeFile($filePath, $excludePatterns)
    {
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reset analysis results
     */
    private function resetAnalysis()
    {
        $this->qualityMetrics = [];
        $this->duplicationResults = [];
        $this->standardsViolations = [];
        $this->complexityAnalysis = [];
    }

    /**
     * Export quality report
     */
    public function exportReport($format = 'json')
    {
        $report = $this->generateQualityReport();

        switch ($format) {
            case 'json':
                return json_encode($report, JSON_PRETTY_PRINT);
            case 'html':
                return $this->generateHtmlReport($report);
            default:
                return $report;
        }
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport($report)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Code Quality Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .metric { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; }
        .critical { background: #f8d7da; border: 1px solid #f5c6cb; }
        .good { background: #d1ecf1; border: 1px solid #bee5eb; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Code Quality Report</h1>
    <p><strong>Generated:</strong> ' . $report['timestamp'] . '</p>
    <p><strong>Files Analyzed:</strong> ' . $report['files_analyzed'] . '</p>
    <p><strong>Overall Score:</strong> ' . $report['overall_score'] . '/100</p>

    <h2>Complexity Analysis</h2>
    <div class="metric">
        <p><strong>Average Complexity:</strong> ' . $report['complexity_analysis']['average_complexity'] . '</p>
        <p><strong>Max Complexity:</strong> ' . $report['complexity_analysis']['max_complexity'] . '</p>
        <p><strong>Files Above Warning:</strong> ' . $report['complexity_analysis']['files_above_warning'] . '</p>
        <p><strong>Files Above Critical:</strong> ' . $report['complexity_analysis']['files_above_critical'] . '</p>
    </div>

    <h2>Duplication Analysis</h2>
    <div class="metric">
        <p><strong>Duplicate Blocks Found:</strong> ' . $report['duplication_analysis']['duplicates_found'] . '</p>
        <p><strong>Duplication Percentage:</strong> ' . $report['duplication_analysis']['duplication_percentage'] . '%</p>
    </div>

    <h2>Standards Compliance</h2>
    <div class="metric">
        <p><strong>Compliance Percentage:</strong> ' . $report['standards_analysis']['compliance_percentage'] . '%</p>
        <p><strong>Files with Violations:</strong> ' . $report['standards_analysis']['files_with_violations'] . '</p>
        <p><strong>Total Violations:</strong> ' . $report['standards_analysis']['total_violations'] . '</p>
    </div>

    <h2>Recommendations</h2>';

        foreach ($report['recommendations'] as $rec) {
            $html .= '<div class="metric ' . $rec['priority'] . '">
                <h3>' . ucfirst($rec['type']) . ' - ' . ucfirst($rec['priority']) . ' Priority</h3>
                <p>' . $rec['message'] . '</p>
            </div>';
        }

        $html .= '</body></html>';

        return $html;
    }
}

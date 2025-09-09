<?php
/**
 * TPT Government Platform - Code Standards Enforcer
 *
 * Automated coding standards enforcement with PSR-12 compliance,
 * custom rules, and automatic code formatting
 */

class CodeStandardsEnforcer
{
    private $logger;
    private $config;
    private $rules;
    private $autoFixEnabled = false;

    /**
     * PSR-12 and custom coding standards
     */
    const PSR12_RULES = [
        'php_opening_tag' => [
            'description' => 'PHP files must start with <?php opening tag',
            'pattern' => '/^<\?php/',
            'fix' => '<?php'
        ],
        'line_length' => [
            'description' => 'Lines must not exceed 120 characters',
            'max_length' => 120,
            'auto_fix' => true
        ],
        'indentation' => [
            'description' => 'Use 4 spaces for indentation, no tabs',
            'pattern' => '/^\t/',
            'fix' => '    ',
            'auto_fix' => true
        ],
        'trailing_whitespace' => [
            'description' => 'Remove trailing whitespace',
            'pattern' => '/\s+$/',
            'fix' => '',
            'auto_fix' => true
        ],
        'class_naming' => [
            'description' => 'Class names must use PascalCase',
            'pattern' => '/\bclass\s+[a-z]/',
            'fix' => 'Class'
        ],
        'method_naming' => [
            'description' => 'Method names must use camelCase',
            'pattern' => '/\bfunction\s+[A-Z]/',
            'fix' => 'function'
        ],
        'constant_naming' => [
            'description' => 'Constants must use UPPER_SNAKE_CASE',
            'pattern' => '/\bconst\s+[a-z]/',
            'fix' => 'const'
        ],
        'brace_placement' => [
            'description' => 'Opening braces must be on the same line',
            'pattern' => '/\b(class|function|if|for|foreach|while|do|switch)\s*\n\s*{/',
            'fix' => ' {'
        ],
        'namespace_declaration' => [
            'description' => 'Namespace declarations must be first',
            'pattern' => '/^<\?php\s*\n\s*(?!namespace)/',
            'fix' => "<?php\n\nnamespace "
        ]
    ];

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'auto_fix' => false,
            'strict_mode' => false,
            'custom_rules' => [],
            'exclude_patterns' => ['/vendor/', '/node_modules/', '/tests/']
        ], $config);

        $this->autoFixEnabled = $this->config['auto_fix'];
        $this->rules = array_merge(self::PSR12_RULES, $this->config['custom_rules']);
    }

    /**
     * Enforce coding standards on a file
     */
    public function enforceStandards($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['error' => 'File not found or not readable'];
        }

        $content = file_get_contents($filePath);
        $violations = [];
        $fixedContent = $content;

        // Check each rule
        foreach ($this->rules as $ruleName => $rule) {
            $result = $this->checkRule($content, $ruleName, $rule);
            if (!empty($result['violations'])) {
                $violations[$ruleName] = $result['violations'];

                if ($this->autoFixEnabled && isset($rule['auto_fix']) && $rule['auto_fix']) {
                    $fixedContent = $this->applyFix($fixedContent, $ruleName, $rule, $result['violations']);
                }
            }
        }

        $result = [
            'file' => $filePath,
            'violations_count' => count($violations),
            'violations' => $violations,
            'compliant' => empty($violations)
        ];

        // Save fixed content if auto-fix is enabled
        if ($this->autoFixEnabled && $fixedContent !== $content) {
            file_put_contents($filePath, $fixedContent);
            $result['auto_fixed'] = true;
            $this->logger->info('Auto-fixed coding standards violations', [
                'file' => $filePath,
                'violations_fixed' => count($violations)
            ]);
        }

        return $result;
    }

    /**
     * Enforce standards on entire codebase
     */
    public function enforceCodebase($path = null)
    {
        $path = $path ?: SRC_PATH;
        $files = $this->scanFiles($path);

        $this->logger->info('Starting coding standards enforcement', [
            'path' => $path,
            'files_to_check' => count($files)
        ]);

        $results = [];
        $totalViolations = 0;
        $filesWithViolations = 0;

        foreach ($files as $file) {
            $result = $this->enforceStandards($file);
            $results[$file] = $result;

            if (!$result['compliant']) {
                $totalViolations += $result['violations_count'];
                $filesWithViolations++;
            }
        }

        $summary = [
            'total_files' => count($files),
            'files_with_violations' => $filesWithViolations,
            'total_violations' => $totalViolations,
            'compliance_percentage' => count($files) > 0 ?
                round(((count($files) - $filesWithViolations) / count($files)) * 100, 2) : 100,
            'auto_fix_enabled' => $this->autoFixEnabled,
            'results' => $results
        ];

        $this->logger->info('Coding standards enforcement completed', [
            'files_checked' => count($files),
            'files_with_violations' => $filesWithViolations,
            'total_violations' => $totalViolations,
            'compliance_percentage' => $summary['compliance_percentage']
        ]);

        return $summary;
    }

    /**
     * Check a specific coding standard rule
     */
    private function checkRule($content, $ruleName, $rule)
    {
        $violations = [];

        switch ($ruleName) {
            case 'php_opening_tag':
                if (!preg_match($rule['pattern'], $content)) {
                    $violations[] = [
                        'line' => 1,
                        'message' => $rule['description'],
                        'code' => substr($content, 0, 50)
                    ];
                }
                break;

            case 'line_length':
                $lines = explode("\n", $content);
                foreach ($lines as $lineNumber => $line) {
                    if (strlen($line) > $rule['max_length']) {
                        $violations[] = [
                            'line' => $lineNumber + 1,
                            'message' => "Line exceeds {$rule['max_length']} characters",
                            'code' => substr($line, 0, 50) . '...'
                        ];
                    }
                }
                break;

            case 'indentation':
                $lines = explode("\n", $content);
                foreach ($lines as $lineNumber => $line) {
                    if (preg_match($rule['pattern'], $line)) {
                        $violations[] = [
                            'line' => $lineNumber + 1,
                            'message' => $rule['description'],
                            'code' => substr($line, 0, 50)
                        ];
                    }
                }
                break;

            case 'trailing_whitespace':
                $lines = explode("\n", $content);
                foreach ($lines as $lineNumber => $line) {
                    if (preg_match($rule['pattern'], $line)) {
                        $violations[] = [
                            'line' => $lineNumber + 1,
                            'message' => $rule['description'],
                            'code' => substr($line, 0, 50)
                        ];
                    }
                }
                break;

            case 'class_naming':
            case 'method_naming':
            case 'constant_naming':
            case 'brace_placement':
                if (preg_match_all($rule['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = [
                            'line' => $lineNumber,
                            'message' => $rule['description'],
                            'code' => substr($content, $match[1], 50)
                        ];
                    }
                }
                break;

            default:
                // Handle custom rules
                if (isset($rule['pattern']) && preg_match($rule['pattern'], $content)) {
                    $violations[] = [
                        'line' => 1,
                        'message' => $rule['description'] ?? 'Custom rule violation',
                        'code' => substr($content, 0, 50)
                    ];
                }
                break;
        }

        return ['violations' => $violations];
    }

    /**
     * Apply automatic fix for a rule
     */
    private function applyFix($content, $ruleName, $rule, $violations)
    {
        switch ($ruleName) {
            case 'php_opening_tag':
                if (!preg_match($rule['pattern'], $content)) {
                    $content = $rule['fix'] . "\n" . $content;
                }
                break;

            case 'line_length':
                $lines = explode("\n", $content);
                foreach ($lines as $i => $line) {
                    if (strlen($line) > $rule['max_length']) {
                        // Simple line wrapping (can be improved)
                        $lines[$i] = substr($line, 0, $rule['max_length'] - 3) . "...";
                    }
                }
                $content = implode("\n", $lines);
                break;

            case 'indentation':
                $content = preg_replace($rule['pattern'], $rule['fix'], $content);
                break;

            case 'trailing_whitespace':
                $lines = explode("\n", $content);
                foreach ($lines as $i => $line) {
                    $lines[$i] = rtrim($line);
                }
                $content = implode("\n", $lines);
                break;

            case 'brace_placement':
                // This is complex to auto-fix reliably, skip for now
                break;

            default:
                // Handle custom rules with auto-fix
                if (isset($rule['pattern']) && isset($rule['fix'])) {
                    $content = preg_replace($rule['pattern'], $rule['fix'], $content);
                }
                break;
        }

        return $content;
    }

    /**
     * Add custom coding standard rule
     */
    public function addRule($name, $rule)
    {
        $this->rules[$name] = $rule;
    }

    /**
     * Remove a coding standard rule
     */
    public function removeRule($name)
    {
        unset($this->rules[$name]);
    }

    /**
     * Enable or disable auto-fix
     */
    public function setAutoFix($enabled)
    {
        $this->autoFixEnabled = $enabled;
    }

    /**
     * Generate coding standards report
     */
    public function generateReport($results, $format = 'json')
    {
        $report = [
            'timestamp' => date('c'),
            'summary' => [
                'total_files' => $results['total_files'],
                'files_with_violations' => $results['files_with_violations'],
                'total_violations' => $results['total_violations'],
                'compliance_percentage' => $results['compliance_percentage'],
                'auto_fix_enabled' => $results['auto_fix_enabled']
            ],
            'violations_by_file' => $results['results']
        ];

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
    <title>Coding Standards Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .summary { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .violation { background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 5px 0; border-radius: 3px; }
        .compliant { background: #d1ecf1; border: 1px solid #bee5eb; }
        h2 { color: #333; }
        .file { margin: 20px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Coding Standards Report</h1>

    <div class="summary">
        <h2>Summary</h2>
        <p><strong>Generated:</strong> ' . $report['timestamp'] . '</p>
        <p><strong>Total Files:</strong> ' . $report['summary']['total_files'] . '</p>
        <p><strong>Files with Violations:</strong> ' . $report['summary']['files_with_violations'] . '</p>
        <p><strong>Total Violations:</strong> ' . $report['summary']['total_violations'] . '</p>
        <p><strong>Compliance Percentage:</strong> ' . $report['summary']['compliance_percentage'] . '%</p>
        <p><strong>Auto-fix Enabled:</strong> ' . ($report['summary']['auto_fix_enabled'] ? 'Yes' : 'No') . '</p>
    </div>';

        foreach ($report['violations_by_file'] as $file => $result) {
            if (!$result['compliant']) {
                $html .= '<div class="file">
                    <h3>' . htmlspecialchars($file) . '</h3>
                    <p><strong>Violations:</strong> ' . $result['violations_count'] . '</p>';

                foreach ($result['violations'] as $rule => $violations) {
                    foreach ($violations as $violation) {
                        $html .= '<div class="violation">
                            <strong>Line ' . $violation['line'] . ':</strong> ' . htmlspecialchars($violation['message']) . '<br>
                            <code>' . htmlspecialchars($violation['code']) . '</code>
                        </div>';
                    }
                }

                $html .= '</div>';
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Scan files in directory
     */
    private function scanFiles($path)
    {
        $files = [];

        if (!is_dir($path)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() &&
                in_array($file->getExtension(), ['php', 'inc']) &&
                $this->shouldIncludeFile($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Check if file should be included
     */
    private function shouldIncludeFile($filePath)
    {
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create backup before auto-fixing
     */
    private function createBackup($filePath)
    {
        $backupPath = $filePath . '.backup.' . date('Y-m-d_H-i-s');
        copy($filePath, $backupPath);

        $this->logger->info('Created backup before auto-fix', [
            'original_file' => $filePath,
            'backup_file' => $backupPath
        ]);

        return $backupPath;
    }

    /**
     * Validate PHP syntax
     */
    public function validatePHPSyntax($filePath)
    {
        $output = shell_exec("php -l \"$filePath\" 2>&1");
        $isValid = strpos($output, 'No syntax errors detected') !== false;

        return [
            'valid' => $isValid,
            'output' => $output,
            'file' => $filePath
        ];
    }

    /**
     * Get coding standards configuration
     */
    public function getConfiguration()
    {
        return [
            'rules' => $this->rules,
            'config' => $this->config,
            'auto_fix_enabled' => $this->autoFixEnabled
        ];
    }
}

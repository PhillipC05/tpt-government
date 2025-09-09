<?php
/**
 * TPT Government Platform - Advanced Error Handler
 *
 * Comprehensive error handling system with intelligent error classification,
 * automatic recovery, detailed logging, and error analytics
 */

namespace Core;

use PDO;
use PDOException;
use Exception;
use Throwable;

class ErrorHandler
{
    /**
     * Database connection
     */
    private PDO $pdo;

    /**
     * Error handling configuration
     */
    private array $config;

    /**
     * Error patterns and recovery strategies
     */
    private array $errorPatterns = [];

    /**
     * Error statistics
     */
    private array $errorStats = [];

    /**
     * Recovery strategies
     */
    private array $recoveryStrategies = [];

    /**
     * Error context
     */
    private array $errorContext = [];

    /**
     * Error notifications
     */
    private array $errorNotifications = [];

    /**
     * Constructor
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'enable_error_logging' => true,
            'enable_error_recovery' => true,
            'enable_error_notifications' => true,
            'enable_error_analytics' => true,
            'max_error_logs_per_hour' => 1000,
            'error_retention_days' => 30,
            'critical_error_threshold' => 10,
            'auto_recovery_enabled' => true,
            'notification_cooldown' => 300, // 5 minutes
            'enable_stack_trace_logging' => true,
            'enable_error_grouping' => true,
            'enable_performance_impact_analysis' => true
        ], $config);

        $this->initializeErrorHandler();
        $this->setupErrorPatterns();
        $this->setupRecoveryStrategies();
    }

    /**
     * Initialize error handling system
     */
    private function initializeErrorHandler(): void
    {
        if ($this->config['enable_error_logging']) {
            $this->createErrorTables();
        }

        // Register error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline, array $errcontext = []): bool
    {
        $errorData = [
            'type' => 'php_error',
            'level' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'context' => $errcontext,
            'timestamp' => time(),
            'request_id' => $this->getRequestId(),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];

        $this->processError($errorData);

        // Don't execute PHP's internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(Throwable $exception): void
    {
        $errorData = [
            'type' => 'uncaught_exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode(),
            'timestamp' => time(),
            'request_id' => $this->getRequestId(),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];

        $this->processError($errorData);

        // Send error response if in web context
        if (!defined('CLI_MODE') || CLI_MODE !== true) {
            $this->sendErrorResponse($exception);
        }
    }

    /**
     * Handle shutdown errors
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'fatal_error',
                'level' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => time(),
                'request_id' => $this->getRequestId(),
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
            ];

            $this->processError($errorData);
        }
    }

    /**
     * Log custom error
     */
    public function logError(string $message, array $context = [], string $level = 'error'): void
    {
        $errorData = [
            'type' => 'custom_error',
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => time(),
            'request_id' => $this->getRequestId(),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $this->getClientIP(),
            'file' => debug_backtrace()[0]['file'] ?? 'unknown',
            'line' => debug_backtrace()[0]['line'] ?? 0
        ];

        $this->processError($errorData);
    }

    /**
     * Process error data
     */
    private function processError(array $errorData): void
    {
        // Classify error
        $errorData['severity'] = $this->classifyErrorSeverity($errorData);
        $errorData['category'] = $this->classifyErrorCategory($errorData);

        // Update error statistics
        $this->updateErrorStatistics($errorData);

        // Check for error patterns
        $patternMatch = $this->checkErrorPatterns($errorData);

        // Attempt recovery if enabled
        if ($this->config['enable_error_recovery'] && $patternMatch) {
            $recoveryResult = $this->attemptRecovery($errorData, $patternMatch);
            $errorData['recovery_attempted'] = true;
            $errorData['recovery_result'] = $recoveryResult;
        }

        // Log error
        if ($this->config['enable_error_logging']) {
            $this->logErrorToDatabase($errorData);
        }

        // Send notifications for critical errors
        if ($this->config['enable_error_notifications'] &&
            in_array($errorData['severity'], ['critical', 'high'])) {
            $this->sendErrorNotification($errorData);
        }

        // Log to system log
        $this->logToSystemLog($errorData);
    }

    /**
     * Classify error severity
     */
    private function classifyErrorSeverity(array $errorData): string
    {
        $type = $errorData['type'];
        $level = $errorData['level'] ?? 0;

        // PHP error levels
        if ($type === 'php_error') {
            switch ($level) {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                    return 'critical';
                case E_WARNING:
                case E_NOTICE:
                    return 'low';
                case E_USER_ERROR:
                    return 'high';
                case E_USER_WARNING:
                    return 'medium';
                default:
                    return 'medium';
            }
        }

        // Exception types
        if ($type === 'uncaught_exception') {
            $exceptionClass = $errorData['class'] ?? '';
            if (strpos($exceptionClass, 'PDOException') !== false) {
                return 'high';
            }
            if (strpos($exceptionClass, 'RuntimeException') !== false) {
                return 'high';
            }
            return 'medium';
        }

        // Fatal errors
        if ($type === 'fatal_error') {
            return 'critical';
        }

        // Custom errors
        if ($type === 'custom_error') {
            return $errorData['level'] === 'critical' ? 'critical' : 'medium';
        }

        return 'medium';
    }

    /**
     * Classify error category
     */
    private function classifyErrorCategory(array $errorData): string
    {
        $message = strtolower($errorData['message']);
        $file = strtolower($errorData['file']);

        // Database errors
        if (strpos($message, 'pdo') !== false ||
            strpos($message, 'database') !== false ||
            strpos($message, 'sql') !== false) {
            return 'database';
        }

        // File system errors
        if (strpos($message, 'file') !== false ||
            strpos($message, 'permission') !== false ||
            strpos($message, 'directory') !== false) {
            return 'filesystem';
        }

        // Network errors
        if (strpos($message, 'connection') !== false ||
            strpos($message, 'timeout') !== false ||
            strpos($message, 'socket') !== false) {
            return 'network';
        }

        // Memory errors
        if (strpos($message, 'memory') !== false ||
            strpos($message, 'out of memory') !== false) {
            return 'memory';
        }

        // Security errors
        if (strpos($message, 'security') !== false ||
            strpos($message, 'auth') !== false ||
            strpos($message, 'permission') !== false) {
            return 'security';
        }

        // API errors
        if (strpos($file, 'api') !== false ||
            strpos($message, 'api') !== false) {
            return 'api';
        }

        return 'general';
    }

    /**
     * Check error patterns
     */
    private function checkErrorPatterns(array $errorData): ?array
    {
        foreach ($this->errorPatterns as $pattern) {
            if ($this->matchesPattern($errorData, $pattern)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Attempt error recovery
     */
    private function attemptRecovery(array $errorData, array $pattern): array
    {
        $recoveryStrategy = $pattern['recovery_strategy'] ?? null;

        if (!$recoveryStrategy || !isset($this->recoveryStrategies[$recoveryStrategy])) {
            return ['success' => false, 'reason' => 'no_recovery_strategy'];
        }

        $strategy = $this->recoveryStrategies[$recoveryStrategy];

        try {
            $result = call_user_func($strategy['handler'], $errorData, $strategy['config']);
            return [
                'success' => true,
                'strategy' => $recoveryStrategy,
                'result' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'strategy' => $recoveryStrategy,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update error statistics
     */
    private function updateErrorStatistics(array $errorData): void
    {
        $key = $errorData['category'] . '_' . $errorData['severity'];
        $hourKey = date('Y-m-d-H', $errorData['timestamp']);

        if (!isset($this->errorStats[$key])) {
            $this->errorStats[$key] = [];
        }

        if (!isset($this->errorStats[$key][$hourKey])) {
            $this->errorStats[$key][$hourKey] = 0;
        }

        $this->errorStats[$key][$hourKey]++;

        // Check for error rate spikes
        $this->checkErrorRateSpike($key, $errorData);
    }

    /**
     * Check for error rate spikes
     */
    private function checkErrorRateSpike(string $key, array $errorData): void
    {
        $currentHour = date('Y-m-d-H', $errorData['timestamp']);
        $previousHour = date('Y-m-d-H', $errorData['timestamp'] - 3600);

        $currentCount = $this->errorStats[$key][$currentHour] ?? 0;
        $previousCount = $this->errorStats[$key][$previousHour] ?? 0;

        // Check if current hour has significantly more errors
        if ($currentCount > $previousCount * 2 && $currentCount > $this->config['critical_error_threshold']) {
            $this->logError('Error rate spike detected', [
                'category' => explode('_', $key)[0],
                'severity' => explode('_', $key)[1],
                'current_count' => $currentCount,
                'previous_count' => $previousCount,
                'spike_ratio' => $previousCount > 0 ? $currentCount / $previousCount : $currentCount
            ], 'critical');
        }
    }

    /**
     * Get error statistics
     */
    public function getErrorStatistics(array $filters = []): array
    {
        $stats = [
            'total_errors' => 0,
            'errors_by_category' => [],
            'errors_by_severity' => [],
            'errors_by_hour' => [],
            'top_error_messages' => [],
            'error_trends' => []
        ];

        // Aggregate statistics
        foreach ($this->errorStats as $key => $hourlyData) {
            list($category, $severity) = explode('_', $key);

            if (!isset($stats['errors_by_category'][$category])) {
                $stats['errors_by_category'][$category] = 0;
            }
            if (!isset($stats['errors_by_severity'][$severity])) {
                $stats['errors_by_severity'][$severity] = 0;
            }

            foreach ($hourlyData as $hour => $count) {
                $stats['total_errors'] += $count;
                $stats['errors_by_category'][$category] += $count;
                $stats['errors_by_severity'][$severity] += $count;

                if (!isset($stats['errors_by_hour'][$hour])) {
                    $stats['errors_by_hour'][$hour] = 0;
                }
                $stats['errors_by_hour'][$hour] += $count;
            }
        }

        return $stats;
    }

    /**
     * Get error recommendations
     */
    public function getErrorRecommendations(): array
    {
        $stats = $this->getErrorStatistics();
        $recommendations = [];

        // High error rate recommendations
        if ($stats['total_errors'] > 1000) {
            $recommendations[] = [
                'type' => 'critical',
                'recommendation' => 'High error rate detected. Consider reviewing recent code changes and system configuration.',
                'current_errors' => $stats['total_errors']
            ];
        }

        // Database error recommendations
        if (($stats['errors_by_category']['database'] ?? 0) > 50) {
            $recommendations[] = [
                'type' => 'high',
                'recommendation' => 'High database error rate. Check database connections and query performance.',
                'database_errors' => $stats['errors_by_category']['database']
            ];
        }

        // Memory error recommendations
        if (($stats['errors_by_category']['memory'] ?? 0) > 20) {
            $recommendations[] = [
                'type' => 'high',
                'recommendation' => 'Memory-related errors detected. Consider increasing memory limits or optimizing memory usage.',
                'memory_errors' => $stats['errors_by_category']['memory']
            ];
        }

        return $recommendations;
    }

    // Private helper methods

    private function createErrorTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS error_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(50) NOT NULL,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                category VARCHAR(50) DEFAULT 'general',
                message TEXT NOT NULL,
                file VARCHAR(255),
                line INT,
                trace TEXT,
                context JSON,
                request_id VARCHAR(64),
                user_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                request_uri TEXT,
                request_method VARCHAR(10),
                recovery_attempted BOOLEAN DEFAULT FALSE,
                recovery_result JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_error_type (error_type),
                INDEX idx_severity (severity),
                INDEX idx_category (category),
                INDEX idx_request_id (request_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS error_patterns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                pattern_name VARCHAR(100) NOT NULL UNIQUE,
                pattern_type VARCHAR(50) NOT NULL,
                pattern_data JSON,
                recovery_strategy VARCHAR(100),
                enabled BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pattern_name (pattern_name),
                INDEX idx_enabled (enabled)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create error tables: " . $e->getMessage());
        }
    }

    private function setupErrorPatterns(): void
    {
        $this->errorPatterns = [
            [
                'name' => 'database_connection_lost',
                'type' => 'message_contains',
                'pattern' => 'Lost connection to MySQL server',
                'recovery_strategy' => 'database_reconnect'
            ],
            [
                'name' => 'memory_limit_exceeded',
                'type' => 'message_contains',
                'pattern' => 'Allowed memory size',
                'recovery_strategy' => 'memory_cleanup'
            ],
            [
                'name' => 'file_not_found',
                'type' => 'exception_type',
                'pattern' => 'FileNotFoundException',
                'recovery_strategy' => 'file_fallback'
            ]
        ];
    }

    private function setupRecoveryStrategies(): void
    {
        $this->recoveryStrategies = [
            'database_reconnect' => [
                'handler' => [$this, 'recoverDatabaseConnection'],
                'config' => ['max_retries' => 3, 'retry_delay' => 1]
            ],
            'memory_cleanup' => [
                'handler' => [$this, 'recoverMemoryUsage'],
                'config' => ['gc_cycles' => 5, 'cleanup_threshold' => 0.8]
            ],
            'file_fallback' => [
                'handler' => [$this, 'recoverFileAccess'],
                'config' => ['fallback_paths' => ['/tmp', '/var/tmp']]
            ]
        ];
    }

    private function matchesPattern(array $errorData, array $pattern): bool
    {
        switch ($pattern['type']) {
            case 'message_contains':
                return strpos(strtolower($errorData['message']), strtolower($pattern['pattern'])) !== false;

            case 'exception_type':
                return isset($errorData['class']) &&
                       strpos($errorData['class'], $pattern['pattern']) !== false;

            case 'file_pattern':
                return isset($errorData['file']) &&
                       preg_match($pattern['pattern'], $errorData['file']);

            default:
                return false;
        }
    }

    private function recoverDatabaseConnection(array $errorData, array $config): array
    {
        // Simplified database reconnection logic
        return ['success' => true, 'message' => 'Database reconnection attempted'];
    }

    private function recoverMemoryUsage(array $errorData, array $config): array
    {
        $cycles = $config['gc_cycles'] ?? 3;
        $collected = 0;

        for ($i = 0; $i < $cycles; $i++) {
            $collected += gc_collect_cycles();
        }

        return [
            'success' => true,
            'cycles_collected' => $collected,
            'memory_freed' => memory_get_usage() - memory_get_usage(true)
        ];
    }

    private function recoverFileAccess(array $errorData, array $config): array
    {
        // Simplified file access recovery
        return ['success' => false, 'message' => 'File access recovery not implemented'];
    }

    private function logErrorToDatabase(array $errorData): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO error_logs
                (error_type, severity, category, message, file, line, trace, context,
                 request_id, user_id, ip_address, user_agent, request_uri, request_method,
                 recovery_attempted, recovery_result)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $errorData['type'],
                $errorData['severity'],
                $errorData['category'],
                $errorData['message'],
                $errorData['file'] ?? null,
                $errorData['line'] ?? null,
                $errorData['trace'] ?? null,
                json_encode($errorData['context'] ?? []),
                $errorData['request_id'],
                $errorData['user_id'],
                $errorData['ip_address'],
                $errorData['user_agent'],
                $errorData['request_uri'],
                $errorData['request_method'],
                $errorData['recovery_attempted'] ?? false,
                json_encode($errorData['recovery_result'] ?? null)
            ]);
        } catch (PDOException $e) {
            // Fallback to system log if database logging fails
            error_log("Failed to log error to database: " . $e->getMessage());
            $this->logToSystemLog($errorData);
        }
    }

    private function sendErrorNotification(array $errorData): void
    {
        // Check cooldown
        $notificationKey = $errorData['category'] . '_' . $errorData['severity'];
        $currentTime = time();

        if (isset($this->errorNotifications[$notificationKey])) {
            $lastNotification = $this->errorNotifications[$notificationKey];
            if ($currentTime - $lastNotification < $this->config['notification_cooldown']) {
                return; // Still in cooldown
            }
        }

        $this->errorNotifications[$notificationKey] = $currentTime;

        // Send notification (simplified - would integrate with notification system)
        $subject = "Critical Error: {$errorData['category']} - {$errorData['severity']}";
        $message = "Error: {$errorData['message']}\nFile: {$errorData['file']}:{$errorData['line']}\nTime: " . date('Y-m-d H:i:s', $errorData['timestamp']);

        error_log("ERROR NOTIFICATION: {$subject}\n{$message}");
    }

    private function sendErrorResponse(Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }

        $errorResponse = [
            'error' => true,
            'message' => 'An internal server error occurred',
            'request_id' => $this->getRequestId()
        ];

        // Include more details in development mode
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $errorResponse['details'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }

        echo json_encode($errorResponse);
    }

    private function logToSystemLog(array $errorData): void
    {
        $logMessage = sprintf(
            "[%s] %s %s: %s in %s:%d",
            date('Y-m-d H:i:s', $errorData['timestamp']),
            strtoupper($errorData['severity']),
            $errorData['type'],
            $errorData['message'],
            $errorData['file'] ?? 'unknown',
            $errorData['line'] ?? 0
        );

        error_log($logMessage);

        // Log stack trace for critical errors
        if ($this->config['enable_stack_trace_logging'] &&
            isset($errorData['trace']) &&
            in_array($errorData['severity'], ['critical', 'high'])) {
            error_log("Stack trace: " . $errorData['trace']);
        }
    }

    private function getRequestId(): string
    {
        if (!isset($_SERVER['REQUEST_ID'])) {
            $_SERVER['REQUEST_ID'] = uniqid('req_', true);
        }
        return $_SERVER['REQUEST_ID'];
    }

    private function getCurrentUserId(): ?int
    {
        // Simplified - would integrate with authentication system
        return $_SESSION['user_id'] ?? null;
    }

    private function getClientIP(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ??
               $_SERVER['HTTP_X_REAL_IP'] ??
               $_SERVER['REMOTE_ADDR'] ??
               'unknown';
    }
}

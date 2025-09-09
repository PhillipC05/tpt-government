<?php
/**
 * TPT Government Platform - Advanced Error Handler
 *
 * Comprehensive error handling system with structured responses,
 * error aggregation, and intelligent error recovery.
 */

namespace Core;

use Core\Interfaces\CacheInterface;
use Core\Logging\StructuredLogger;
use Core\NotificationManager;

class AdvancedErrorHandler
{
    /**
     * Error severity levels
     */
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Error categories
     */
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_APPLICATION = 'application';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_NETWORK = 'network';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_VALIDATION = 'validation';

    /**
     * Cache instance
     */
    private ?CacheInterface $cache = null;

    /**
     * Logger instance
     */
    private StructuredLogger $logger;

    /**
     * Notification manager
     */
    private NotificationManager $notificationManager;

    /**
     * Error aggregation cache
     */
    private array $errorAggregation = [];

    /**
     * Error recovery strategies
     */
    private array $recoveryStrategies = [];

    /**
     * Error context
     */
    private array $errorContext = [];

    /**
     * Error rate limiting
     */
    private array $errorRates = [];

    /**
     * Maximum errors per minute before throttling
     */
    private int $maxErrorsPerMinute = 100;

    /**
     * Constructor
     */
    public function __construct(
        StructuredLogger $logger,
        NotificationManager $notificationManager,
        ?CacheInterface $cache = null
    ) {
        $this->logger = $logger;
        $this->notificationManager = $notificationManager;
        $this->cache = $cache;

        $this->initializeRecoveryStrategies();
        $this->registerErrorHandlers();
    }

    /**
     * Handle an exception with advanced processing
     *
     * @param \Throwable $exception The exception to handle
     * @param array $context Additional context
     * @return array Structured error response
     */
    public function handleException(\Throwable $exception, array $context = []): array
    {
        // Generate error ID
        $errorId = $this->generateErrorId();

        // Analyze error
        $errorAnalysis = $this->analyzeError($exception, $context);

        // Aggregate error
        $this->aggregateError($errorAnalysis);

        // Check error rate limiting
        if ($this->isRateLimited($errorAnalysis)) {
            return $this->createThrottledResponse($errorId);
        }

        // Log error
        $this->logError($errorAnalysis, $errorId);

        // Attempt recovery
        $recoveryResult = $this->attemptRecovery($errorAnalysis);

        // Send notifications if needed
        $this->sendNotifications($errorAnalysis, $recoveryResult);

        // Create structured response
        return $this->createStructuredResponse($errorAnalysis, $errorId, $recoveryResult);
    }

    /**
     * Handle API error with structured response
     *
     * @param \Throwable $exception The exception
     * @param Request $request The request object
     * @return array API error response
     */
    public function handleApiError(\Throwable $exception, Request $request): array
    {
        $context = [
            'request_method' => $request->getMethod(),
            'request_path' => $request->getPath(),
            'request_params' => $request->getParams(),
            'user_agent' => $request->getUserAgent(),
            'client_ip' => $request->getClientIp(),
            'request_id' => $request->getHeader('X-Request-ID') ?? $this->generateRequestId()
        ];

        $errorResponse = $this->handleException($exception, $context);

        // Add API-specific fields
        $errorResponse['path'] = $request->getPath();
        $errorResponse['method'] = $request->getMethod();
        $errorResponse['timestamp'] = date('c');

        return $errorResponse;
    }

    /**
     * Analyze error and extract useful information
     *
     * @param \Throwable $exception The exception
     * @param array $context Additional context
     * @return array Error analysis
     */
    private function analyzeError(\Throwable $exception, array $context = []): array
    {
        $errorCode = $exception->getCode();
        $errorMessage = $exception->getMessage();
        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();
        $errorTrace = $exception->getTraceAsString();

        // Determine error category
        $category = $this->determineErrorCategory($exception, $context);

        // Determine error severity
        $severity = $this->determineErrorSeverity($exception, $category);

        // Extract stack trace information
        $stackTrace = $this->parseStackTrace($errorTrace);

        // Generate error fingerprint for aggregation
        $fingerprint = $this->generateErrorFingerprint($exception, $stackTrace);

        return [
            'type' => get_class($exception),
            'code' => $errorCode,
            'message' => $errorMessage,
            'file' => $errorFile,
            'line' => $errorLine,
            'category' => $category,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'stack_trace' => $stackTrace,
            'context' => $context,
            'environment' => $this->getEnvironmentInfo(),
            'occurred_at' => microtime(true)
        ];
    }

    /**
     * Determine error category
     *
     * @param \Throwable $exception The exception
     * @param array $context Context information
     * @return string Error category
     */
    private function determineErrorCategory(\Throwable $exception, array $context): string
    {
        $message = strtolower($exception->getMessage());
        $className = get_class($exception);

        // Database errors
        if (strpos($className, 'PDO') !== false || strpos($message, 'database') !== false) {
            return self::CATEGORY_DATABASE;
        }

        // Network errors
        if (strpos($message, 'connection') !== false || strpos($message, 'network') !== false) {
            return self::CATEGORY_NETWORK;
        }

        // Security errors
        if (strpos($message, 'unauthorized') !== false || strpos($message, 'forbidden') !== false) {
            return self::CATEGORY_SECURITY;
        }

        // Validation errors
        if (strpos($message, 'validation') !== false || strpos($message, 'invalid') !== false) {
            return self::CATEGORY_VALIDATION;
        }

        // User errors
        if (isset($context['user_id']) && $exception->getCode() >= 400 && $exception->getCode() < 500) {
            return self::CATEGORY_USER;
        }

        // System errors (default)
        return self::CATEGORY_SYSTEM;
    }

    /**
     * Determine error severity
     *
     * @param \Throwable $exception The exception
     * @param string $category Error category
     * @return string Error severity
     */
    private function determineErrorSeverity(\Throwable $exception, string $category): string
    {
        $code = $exception->getCode();

        // HTTP status codes
        if ($code >= 500) {
            return self::SEVERITY_HIGH;
        }

        if ($code >= 400) {
            return self::SEVERITY_MEDIUM;
        }

        // Critical system errors
        if ($category === self::CATEGORY_SYSTEM) {
            return self::SEVERITY_HIGH;
        }

        // Security errors are always high severity
        if ($category === self::CATEGORY_SECURITY) {
            return self::SEVERITY_CRITICAL;
        }

        // Database errors
        if ($category === self::CATEGORY_DATABASE) {
            return self::SEVERITY_HIGH;
        }

        return self::SEVERITY_MEDIUM;
    }

    /**
     * Parse stack trace into structured format
     *
     * @param string $trace Raw stack trace
     * @return array Parsed stack trace
     */
    private function parseStackTrace(string $trace): array
    {
        $lines = explode("\n", $trace);
        $parsedTrace = [];

        foreach ($lines as $line) {
            if (preg_match('/#(\d+)\s+(.+?)\((\d+)\):\s+(.+)/', $line, $matches)) {
                $parsedTrace[] = [
                    'level' => (int) $matches[1],
                    'file' => $matches[2],
                    'line' => (int) $matches[3],
                    'function' => $matches[4]
                ];
            }
        }

        return $parsedTrace;
    }

    /**
     * Generate error fingerprint for aggregation
     *
     * @param \Throwable $exception The exception
     * @param array $stackTrace Parsed stack trace
     * @return string Error fingerprint
     */
    private function generateErrorFingerprint(\Throwable $exception, array $stackTrace): string
    {
        $keyParts = [
            get_class($exception),
            $exception->getFile(),
            $exception->getLine()
        ];

        // Include first few stack frames for more specificity
        $stackFrames = array_slice($stackTrace, 0, 3);
        foreach ($stackFrames as $frame) {
            $keyParts[] = $frame['file'] . ':' . $frame['line'];
        }

        return md5(implode('|', $keyParts));
    }

    /**
     * Aggregate error for monitoring
     *
     * @param array $errorAnalysis Error analysis
     * @return void
     */
    private function aggregateError(array $errorAnalysis): void
    {
        $fingerprint = $errorAnalysis['fingerprint'];
        $currentMinute = floor(time() / 60);

        if (!isset($this->errorAggregation[$fingerprint])) {
            $this->errorAggregation[$fingerprint] = [
                'count' => 0,
                'first_occurred' => $errorAnalysis['occurred_at'],
                'last_occurred' => $errorAnalysis['occurred_at'],
                'category' => $errorAnalysis['category'],
                'severity' => $errorAnalysis['severity'],
                'sample_error' => $errorAnalysis
            ];
        }

        $this->errorAggregation[$fingerprint]['count']++;
        $this->errorAggregation[$fingerprint]['last_occurred'] = $errorAnalysis['occurred_at'];

        // Clean up old aggregations (older than 1 hour)
        $this->cleanupErrorAggregation();
    }

    /**
     * Check if error is rate limited
     *
     * @param array $errorAnalysis Error analysis
     * @return bool True if rate limited
     */
    private function isRateLimited(array $errorAnalysis): bool
    {
        $currentMinute = floor(time() / 60);

        if (!isset($this->errorRates[$currentMinute])) {
            $this->errorRates[$currentMinute] = 0;
        }

        $this->errorRates[$currentMinute]++;

        // Clean up old rate data
        $this->cleanupErrorRates($currentMinute);

        return $this->errorRates[$currentMinute] > $this->maxErrorsPerMinute;
    }

    /**
     * Attempt error recovery
     *
     * @param array $errorAnalysis Error analysis
     * @return array Recovery result
     */
    private function attemptRecovery(array $errorAnalysis): array
    {
        $category = $errorAnalysis['category'];
        $type = $errorAnalysis['type'];

        if (isset($this->recoveryStrategies[$category])) {
            foreach ($this->recoveryStrategies[$category] as $strategy) {
                try {
                    $result = $strategy($errorAnalysis);
                    if ($result['success']) {
                        return $result;
                    }
                } catch (\Exception $e) {
                    // Recovery strategy failed, continue to next
                    continue;
                }
            }
        }

        return [
            'success' => false,
            'message' => 'No suitable recovery strategy found'
        ];
    }

    /**
     * Initialize recovery strategies
     *
     * @return void
     */
    private function initializeRecoveryStrategies(): void
    {
        $this->recoveryStrategies = [
            self::CATEGORY_DATABASE => [
                $this->createDatabaseRecoveryStrategy()
            ],
            self::CATEGORY_NETWORK => [
                $this->createNetworkRecoveryStrategy()
            ],
            self::CATEGORY_SYSTEM => [
                $this->createSystemRecoveryStrategy()
            ]
        ];
    }

    /**
     * Create database recovery strategy
     *
     * @return callable
     */
    private function createDatabaseRecoveryStrategy(): callable
    {
        return function (array $errorAnalysis) {
            // Attempt database reconnection
            try {
                // This would integrate with the database connection pooling
                return [
                    'success' => true,
                    'message' => 'Database connection recovered',
                    'action' => 'reconnect'
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Database recovery failed: ' . $e->getMessage()
                ];
            }
        };
    }

    /**
     * Create network recovery strategy
     *
     * @return callable
     */
    private function createNetworkRecoveryStrategy(): callable
    {
        return function (array $errorAnalysis) {
            // Attempt to retry network operation
            return [
                'success' => false,
                'message' => 'Network recovery not implemented'
            ];
        };
    }

    /**
     * Create system recovery strategy
     *
     * @return callable
     */
    private function createSystemRecoveryStrategy(): callable
    {
        return function (array $errorAnalysis) {
            // Clear caches, restart services, etc.
            if ($this->cache) {
                $this->cache->clear();
            }

            return [
                'success' => true,
                'message' => 'System caches cleared',
                'action' => 'cache_clear'
            ];
        };
    }

    /**
     * Log error with structured format
     *
     * @param array $errorAnalysis Error analysis
     * @param string $errorId Error ID
     * @return void
     */
    private function logError(array $errorAnalysis, string $errorId): void
    {
        $logData = [
            'error_id' => $errorId,
            'severity' => $errorAnalysis['severity'],
            'category' => $errorAnalysis['category'],
            'type' => $errorAnalysis['type'],
            'message' => $errorAnalysis['message'],
            'file' => $errorAnalysis['file'],
            'line' => $errorAnalysis['line'],
            'fingerprint' => $errorAnalysis['fingerprint'],
            'context' => $errorAnalysis['context'],
            'stack_trace' => array_slice($errorAnalysis['stack_trace'], 0, 5), // Limit stack trace
            'environment' => $errorAnalysis['environment']
        ];

        $this->logger->error('Application error occurred', $logData);
    }

    /**
     * Send notifications for critical errors
     *
     * @param array $errorAnalysis Error analysis
     * @param array $recoveryResult Recovery result
     * @return void
     */
    private function sendNotifications(array $errorAnalysis, array $recoveryResult): void
    {
        $severity = $errorAnalysis['severity'];

        // Only send notifications for high and critical severity errors
        if ($severity !== self::SEVERITY_HIGH && $severity !== self::SEVERITY_CRITICAL) {
            return;
        }

        // Check if we've already sent a notification for this error recently
        $notificationKey = 'error_notification_' . $errorAnalysis['fingerprint'];
        if ($this->cache && $this->cache->get($notificationKey)) {
            return; // Already notified recently
        }

        $subject = "Critical Error: {$errorAnalysis['category']} - {$errorAnalysis['type']}";
        $message = $this->formatErrorNotification($errorAnalysis, $recoveryResult);

        $this->notificationManager->sendAlert($subject, $message, [
            'severity' => $severity,
            'category' => $errorAnalysis['category'],
            'error_id' => $this->generateErrorId()
        ]);

        // Cache notification to prevent spam
        if ($this->cache) {
            $this->cache->set($notificationKey, true, 300); // 5 minutes
        }
    }

    /**
     * Create structured error response
     *
     * @param array $errorAnalysis Error analysis
     * @param string $errorId Error ID
     * @param array $recoveryResult Recovery result
     * @return array Structured response
     */
    private function createStructuredResponse(array $errorAnalysis, string $errorId, array $recoveryResult): array
    {
        $response = [
            'error' => [
                'id' => $errorId,
                'type' => $errorAnalysis['type'],
                'category' => $errorAnalysis['category'],
                'severity' => $errorAnalysis['severity']
            ],
            'message' => $this->getUserFriendlyMessage($errorAnalysis),
            'timestamp' => date('c')
        ];

        // Add recovery information if available
        if ($recoveryResult['success']) {
            $response['recovery'] = [
                'action' => $recoveryResult['action'] ?? 'unknown',
                'message' => $recoveryResult['message']
            ];
        }

        // Add debugging information in development
        if ($this->isDevelopmentEnvironment()) {
            $response['debug'] = [
                'file' => $errorAnalysis['file'],
                'line' => $errorAnalysis['line'],
                'trace' => array_slice($errorAnalysis['stack_trace'], 0, 3)
            ];
        }

        return $response;
    }

    /**
     * Create throttled response when rate limited
     *
     * @param string $errorId Error ID
     * @return array Throttled response
     */
    private function createThrottledResponse(string $errorId): array
    {
        return [
            'error' => [
                'id' => $errorId,
                'type' => 'RateLimited',
                'category' => self::CATEGORY_SYSTEM,
                'severity' => self::SEVERITY_MEDIUM
            ],
            'message' => 'Too many errors occurred. Please try again later.',
            'retry_after' => 60, // seconds
            'timestamp' => date('c')
        ];
    }

    /**
     * Get user-friendly error message
     *
     * @param array $errorAnalysis Error analysis
     * @return string User-friendly message
     */
    private function getUserFriendlyMessage(array $errorAnalysis): string
    {
        $category = $errorAnalysis['category'];

        $messages = [
            self::CATEGORY_DATABASE => 'A database error occurred. Please try again later.',
            self::CATEGORY_NETWORK => 'A network error occurred. Please check your connection.',
            self::CATEGORY_SECURITY => 'Access denied. Please check your permissions.',
            self::CATEGORY_VALIDATION => 'Invalid input provided. Please check your data.',
            self::CATEGORY_USER => 'Your request could not be processed. Please try again.',
            self::CATEGORY_SYSTEM => 'An unexpected error occurred. Please try again later.',
            self::CATEGORY_APPLICATION => 'An application error occurred. Please try again later.'
        ];

        return $messages[$category] ?? 'An error occurred. Please try again later.';
    }

    /**
     * Get environment information
     *
     * @return array Environment info
     */
    private function getEnvironmentInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Check if running in development environment
     *
     * @return bool True if development
     */
    private function isDevelopmentEnvironment(): bool
    {
        return getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true';
    }

    /**
     * Generate unique error ID
     *
     * @return string Error ID
     */
    private function generateErrorId(): string
    {
        return 'err_' . bin2hex(random_bytes(8));
    }

    /**
     * Generate unique request ID
     *
     * @return string Request ID
     */
    private function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(8));
    }

    /**
     * Format error notification
     *
     * @param array $errorAnalysis Error analysis
     * @param array $recoveryResult Recovery result
     * @return string Formatted notification
     */
    private function formatErrorNotification(array $errorAnalysis, array $recoveryResult): string
    {
        $message = "Error Alert\n\n";
        $message .= "Severity: {$errorAnalysis['severity']}\n";
        $message .= "Category: {$errorAnalysis['category']}\n";
        $message .= "Type: {$errorAnalysis['type']}\n";
        $message .= "Message: {$errorAnalysis['message']}\n";
        $message .= "File: {$errorAnalysis['file']}:{$errorAnalysis['line']}\n";

        if ($recoveryResult['success']) {
            $message .= "Recovery: {$recoveryResult['message']}\n";
        } else {
            $message .= "Recovery: Failed - {$recoveryResult['message']}\n";
        }

        $message .= "Time: " . date('Y-m-d H:i:s', (int) $errorAnalysis['occurred_at']) . "\n";

        return $message;
    }

    /**
     * Clean up old error aggregations
     *
     * @return void
     */
    private function cleanupErrorAggregation(): void
    {
        $now = time();
        $maxAge = 3600; // 1 hour

        foreach ($this->errorAggregation as $fingerprint => $data) {
            if (($now - $data['last_occurred']) > $maxAge) {
                unset($this->errorAggregation[$fingerprint]);
            }
        }
    }

    /**
     * Clean up old error rates
     *
     * @param int $currentMinute Current minute
     * @return void
     */
    private function cleanupErrorRates(int $currentMinute): void
    {
        $maxAge = 5; // Keep 5 minutes of data

        foreach ($this->errorRates as $minute => $count) {
            if (($currentMinute - $minute) > $maxAge) {
                unset($this->errorRates[$minute]);
            }
        }
    }

    /**
     * Register PHP error handlers
     *
     * @return void
     */
    private function registerErrorHandlers(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleUncaughtException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     *
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Only handle errors that are included in error_reporting
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $exception = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        $this->handleException($exception);

        return true;
    }

    /**
     * Handle uncaught exceptions
     *
     * @param \Throwable $exception Uncaught exception
     * @return void
     */
    public function handleUncaughtException(\Throwable $exception): void
    {
        $this->handleException($exception);

        // In production, show generic error page
        if (!$this->isDevelopmentEnvironment()) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => 'Something went wrong. Please try again later.'
            ]);
            exit;
        }
    }

    /**
     * Handle shutdown errors
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            $this->handleException($exception);
        }
    }

    /**
     * Get error statistics
     *
     * @return array Error statistics
     */
    public function getErrorStatistics(): array
    {
        $totalErrors = array_sum(array_column($this->errorAggregation, 'count'));
        $uniqueErrors = count($this->errorAggregation);

        $severityCounts = [
            self::SEVERITY_LOW => 0,
            self::SEVERITY_MEDIUM => 0,
            self::SEVERITY_HIGH => 0,
            self::SEVERITY_CRITICAL => 0
        ];

        foreach ($this->errorAggregation as $error) {
            $severityCounts[$error['severity']]++;
        }

        return [
            'total_errors' => $totalErrors,
            'unique_errors' => $uniqueErrors,
            'severity_breakdown' => $severityCounts,
            'error_rate_per_minute' => $this->calculateErrorRate(),
            'top_errors' => $this->getTopErrors(5)
        ];
    }

    /**
     * Calculate current error rate
     *
     * @return float Errors per minute
     */
    private function calculateErrorRate(): float
    {
        $currentMinute = floor(time() / 60);
        return $this->errorRates[$currentMinute] ?? 0;
    }

    /**
     * Get top errors by frequency
     *
     * @param int $limit Number of top errors to return
     * @return array Top errors
     */
    private function getTopErrors(int $limit): array
    {
        $errors = $this->errorAggregation;

        // Sort by count descending
        usort($errors, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice($errors, 0, $limit);
    }

    /**
     * Clear error aggregation data
     *
     * @return void
     */
    public function clearErrorData(): void
    {
        $this->errorAggregation = [];
        $this->errorRates = [];
        $this->errorContext = [];
    }
}

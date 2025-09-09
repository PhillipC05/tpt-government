<?php
/**
 * TPT Government Platform - Error Reporting Manager
 *
 * Comprehensive error reporting system with notifications, aggregation,
 * and incident management
 */

class ErrorReportingManager
{
    private $logger;
    private $config;
    private $errorBuffer = [];
    private $notificationThresholds;
    private $lastNotificationTime = 0;

    /**
     * Error severity levels
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'buffer_size' => 100,
            'flush_interval' => 300, // 5 minutes
            'notification_cooldown' => 3600, // 1 hour
            'email_enabled' => true,
            'slack_enabled' => false,
            'database_enabled' => true
        ], $config);

        $this->notificationThresholds = [
            self::SEVERITY_LOW => 10,
            self::SEVERITY_MEDIUM => 5,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_CRITICAL => 1
        ];

        // Set up periodic flushing
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, [$this, 'flushBuffer']);
            pcntl_alarm($this->config['flush_interval']);
        }
    }

    /**
     * Report an error
     */
    public function reportError($error, $severity = self::SEVERITY_MEDIUM, $context = [])
    {
        $errorData = [
            'timestamp' => microtime(true),
            'severity' => $severity,
            'message' => $error instanceof Exception ? $error->getMessage() : (string)$error,
            'file' => $error instanceof Exception ? $error->getFile() : null,
            'line' => $error instanceof Exception ? $error->getLine() : null,
            'trace' => $error instanceof Exception ? $error->getTraceAsString() : null,
            'context' => $context,
            'server' => $_SERVER,
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown'
            ]
        ];

        // Add to buffer
        $this->errorBuffer[] = $errorData;

        // Log the error
        $this->logError($errorData);

        // Check if notification is needed
        $this->checkNotificationThreshold($severity);

        // Flush buffer if it's full
        if (count($this->errorBuffer) >= $this->config['buffer_size']) {
            $this->flushBuffer();
        }

        return $errorData;
    }

    /**
     * Report a PHP error
     */
    public function reportPHPError($errno, $errstr, $errfile, $errline, $errcontext = [])
    {
        $severity = $this->mapPHPErrorLevel($errno);

        $error = [
            'type' => 'php_error',
            'code' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'context' => $errcontext
        ];

        return $this->reportError($error, $severity);
    }

    /**
     * Report an exception
     */
    public function reportException(Exception $exception, $context = [])
    {
        $severity = $this->determineExceptionSeverity($exception);
        return $this->reportError($exception, $severity, $context);
    }

    /**
     * Report a database error
     */
    public function reportDatabaseError($error, $query = null, $context = [])
    {
        $errorData = [
            'type' => 'database_error',
            'query' => $query,
            'error' => $error
        ];

        return $this->reportError($errorData, self::SEVERITY_HIGH, $context);
    }

    /**
     * Report an API error
     */
    public function reportAPIError($endpoint, $error, $statusCode = null, $context = [])
    {
        $errorData = [
            'type' => 'api_error',
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $error
        ];

        $severity = $statusCode >= 500 ? self::SEVERITY_HIGH : self::SEVERITY_MEDIUM;
        return $this->reportError($errorData, $severity, $context);
    }

    /**
     * Flush error buffer to storage
     */
    public function flushBuffer()
    {
        if (empty($this->errorBuffer)) {
            return;
        }

        // Store errors in database if enabled
        if ($this->config['database_enabled']) {
            $this->storeErrorsInDatabase();
        }

        // Send aggregated report if needed
        $this->sendAggregatedReport();

        // Clear buffer
        $this->errorBuffer = [];

        // Reset alarm for next flush
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm($this->config['flush_interval']);
        }
    }

    /**
     * Get error statistics
     */
    public function getErrorStats($timeframe = 3600)
    {
        $cutoff = time() - $timeframe;
        $stats = [
            'total' => 0,
            'by_severity' => [
                self::SEVERITY_LOW => 0,
                self::SEVERITY_MEDIUM => 0,
                self::SEVERITY_HIGH => 0,
                self::SEVERITY_CRITICAL => 0
            ],
            'by_type' => [],
            'recent_errors' => []
        ];

        foreach ($this->errorBuffer as $error) {
            if ($error['timestamp'] >= $cutoff) {
                $stats['total']++;
                $stats['by_severity'][$error['severity']]++;

                $type = $error['type'] ?? 'unknown';
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type]++;

                if (count($stats['recent_errors']) < 10) {
                    $stats['recent_errors'][] = $error;
                }
            }
        }

        return $stats;
    }

    /**
     * Log error using structured logger
     */
    private function logError($errorData)
    {
        $level = $this->mapSeverityToLogLevel($errorData['severity']);
        $message = $errorData['message'];

        $context = [
            'error_type' => $errorData['type'] ?? 'unknown',
            'severity' => $errorData['severity'],
            'file' => $errorData['file'],
            'line' => $errorData['line'],
            'trace' => $errorData['trace']
        ];

        $this->logger->log($level, $message, $context);
    }

    /**
     * Check if notification threshold is reached
     */
    private function checkNotificationThreshold($severity)
    {
        $threshold = $this->notificationThresholds[$severity] ?? 0;
        if ($threshold <= 0) {
            return;
        }

        $recentErrors = array_filter($this->errorBuffer, function($error) use ($severity) {
            return $error['severity'] === $severity &&
                   ($error['timestamp'] >= (time() - 300)); // Last 5 minutes
        });

        if (count($recentErrors) >= $threshold) {
            $this->sendNotification($severity, $recentErrors);
        }
    }

    /**
     * Send notification for error threshold breach
     */
    private function sendNotification($severity, $errors)
    {
        $now = time();
        if (($now - $this->lastNotificationTime) < $this->config['notification_cooldown']) {
            return;
        }

        $this->lastNotificationTime = $now;

        $subject = "TPT Platform - $severity Error Threshold Breached";
        $message = $this->formatNotificationMessage($severity, $errors);

        // Send email notification
        if ($this->config['email_enabled']) {
            $this->sendEmailNotification($subject, $message);
        }

        // Send Slack notification
        if ($this->config['slack_enabled']) {
            $this->sendSlackNotification($subject, $message);
        }

        // Log notification
        $this->logger->warning("Error notification sent: $subject", [
            'severity' => $severity,
            'error_count' => count($errors)
        ]);
    }

    /**
     * Store errors in database
     */
    private function storeErrorsInDatabase()
    {
        try {
            $db = Database::getInstance();

            foreach ($this->errorBuffer as $error) {
                $db->query(
                    "INSERT INTO error_logs (timestamp, severity, type, message, file, line, trace, context, server_data)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        date('Y-m-d H:i:s', (int)$error['timestamp']),
                        $error['severity'],
                        $error['type'] ?? 'unknown',
                        $error['message'],
                        $error['file'],
                        $error['line'],
                        $error['trace'],
                        json_encode($error['context']),
                        json_encode($error['server'] ?? [])
                    ]
                );
            }
        } catch (Exception $e) {
            // Fallback to logging if database fails
            $this->logger->error("Failed to store errors in database: " . $e->getMessage());
        }
    }

    /**
     * Send aggregated error report
     */
    private function sendAggregatedReport()
    {
        $stats = $this->getErrorStats(3600); // Last hour

        if ($stats['total'] > 0) {
            $this->logger->info("Error aggregation report", [
                'stats' => $stats,
                'period' => '1 hour'
            ]);
        }
    }

    /**
     * Map PHP error level to severity
     */
    private function mapPHPErrorLevel($errno)
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::SEVERITY_CRITICAL;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::SEVERITY_HIGH;

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::SEVERITY_LOW;

            default:
                return self::SEVERITY_MEDIUM;
        }
    }

    /**
     * Determine exception severity
     */
    private function determineExceptionSeverity(Exception $exception)
    {
        $class = get_class($exception);

        if (strpos($class, 'Critical') !== false ||
            strpos($class, 'Fatal') !== false ||
            $exception instanceof PDOException) {
            return self::SEVERITY_CRITICAL;
        }

        if (strpos($class, 'RuntimeException') !== false ||
            strpos($class, 'InvalidArgumentException') !== false) {
            return self::SEVERITY_HIGH;
        }

        return self::SEVERITY_MEDIUM;
    }

    /**
     * Map severity to log level
     */
    private function mapSeverityToLogLevel($severity)
    {
        switch ($severity) {
            case self::SEVERITY_CRITICAL:
                return StructuredLogger::CRITICAL;
            case self::SEVERITY_HIGH:
                return StructuredLogger::ERROR;
            case self::SEVERITY_MEDIUM:
                return StructuredLogger::WARNING;
            case self::SEVERITY_LOW:
                return StructuredLogger::NOTICE;
            default:
                return StructuredLogger::INFO;
        }
    }

    /**
     * Format notification message
     */
    private function formatNotificationMessage($severity, $errors)
    {
        $count = count($errors);
        $message = "Error threshold breached for severity: $severity\n";
        $message .= "Total errors in last 5 minutes: $count\n\n";

        $message .= "Recent errors:\n";
        foreach (array_slice($errors, 0, 5) as $error) {
            $message .= "- " . date('H:i:s', (int)$error['timestamp']) . ": " . $error['message'] . "\n";
        }

        return $message;
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification($subject, $message)
    {
        $to = $this->config['notification_email'] ?? 'admin@tpt-gov.local';
        $headers = "From: TPT Platform <noreply@tpt-gov.local>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        mail($to, $subject, $message, $headers);
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification($subject, $message)
    {
        // Implementation would depend on Slack webhook configuration
        // This is a placeholder for the actual Slack integration
        $this->logger->info("Slack notification would be sent: $subject");
    }
}

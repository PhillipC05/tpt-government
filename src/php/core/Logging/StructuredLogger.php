<?php
/**
 * TPT Government Platform - Structured Logger
 *
 * Provides comprehensive structured logging with multiple handlers,
 * log levels, context enrichment, and performance monitoring
 */

class StructuredLogger
{
    private $handlers = [];
    private $processors = [];
    private $context = [];
    private $minLevel;
    private $timezone;

    /**
     * Log levels (RFC 5424)
     */
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    /**
     * Log level names
     */
    private static $levelNames = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG'
    ];

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->minLevel = $config['min_level'] ?? self::DEBUG;
        $this->timezone = $config['timezone'] ?? 'UTC';

        // Set default timezone
        date_default_timezone_set($this->timezone);

        // Add default processors
        $this->addProcessor([$this, 'addTimestamp']);
        $this->addProcessor([$this, 'addRequestContext']);
        $this->addProcessor([$this, 'addSystemContext']);

        // Add default handlers
        $this->addHandler(new FileLogHandler($config['file_path'] ?? LOG_PATH . '/app.log'));
        $this->addHandler(new ErrorLogHandler());
    }

    /**
     * Add a log handler
     */
    public function addHandler(LogHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Add a log processor
     */
    public function addProcessor(callable $processor)
    {
        $this->processors[] = $processor;
    }

    /**
     * Set minimum log level
     */
    public function setMinLevel($level)
    {
        $this->minLevel = $level;
    }

    /**
     * Add context that will be included in all log entries
     */
    public function addContext($key, $value)
    {
        $this->context[$key] = $value;
    }

    /**
     * Remove context
     */
    public function removeContext($key)
    {
        unset($this->context[$key]);
    }

    /**
     * Clear all context
     */
    public function clearContext()
    {
        $this->context = [];
    }

    /**
     * Log emergency message
     */
    public function emergency($message, array $context = [])
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message
     */
    public function alert($message, array $context = [])
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical($message, array $context = [])
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, array $context = [])
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, array $context = [])
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message
     */
    public function notice($message, array $context = [])
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message
     */
    public function info($message, array $context = [])
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log debug message
     */
    public function debug($message, array $context = [])
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log message with specified level
     */
    public function log($level, $message, array $context = [])
    {
        if ($level > $this->minLevel) {
            return;
        }

        // Create log record
        $record = [
            'level' => $level,
            'level_name' => self::$levelNames[$level],
            'message' => $message,
            'context' => array_merge($this->context, $context),
            'timestamp' => microtime(true)
        ];

        // Apply processors
        foreach ($this->processors as $processor) {
            $record = call_user_func($processor, $record);
        }

        // Send to handlers
        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                $handler->handle($record);
            }
        }
    }

    /**
     * Add timestamp processor
     */
    private function addTimestamp($record)
    {
        $record['datetime'] = date('Y-m-d H:i:s', (int) $record['timestamp']);
        $record['datetime_micro'] = date('Y-m-d H:i:s.u', $record['timestamp']);
        return $record;
    }

    /**
     * Add request context processor
     */
    private function addRequestContext($record)
    {
        if (!isset($_SERVER)) {
            return $record;
        }

        $record['request'] = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown'
        ];

        // Add session info if available
        if (isset($_SESSION)) {
            $record['session'] = [
                'id' => session_id(),
                'user_id' => $_SESSION['user_id'] ?? null
            ];
        }

        return $record;
    }

    /**
     * Add system context processor
     */
    private function addSystemContext($record)
    {
        $record['system'] = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'php_version' => PHP_VERSION,
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'pid' => getmypid()
        ];

        return $record;
    }

    /**
     * Get level name
     */
    public static function getLevelName($level)
    {
        return self::$levelNames[$level] ?? 'UNKNOWN';
    }

    /**
     * Create child logger with additional context
     */
    public function withContext(array $context)
    {
        $childLogger = clone $this;
        $childLogger->context = array_merge($this->context, $context);
        return $childLogger;
    }
}

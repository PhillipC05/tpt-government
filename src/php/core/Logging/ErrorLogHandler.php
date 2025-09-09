<?php
/**
 * TPT Government Platform - Error Log Handler
 *
 * Handles logging to PHP's error_log function with formatting
 */

class ErrorLogHandler implements LogHandlerInterface
{
    private $minLevel;
    private $formatter;
    private $messageType;

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->minLevel = $config['min_level'] ?? StructuredLogger::WARNING;
        $this->formatter = $config['formatter'] ?? [$this, 'formatRecord'];
        $this->messageType = $config['message_type'] ?? 0; // 0 = error_log, 1 = email, etc.
    }

    /**
     * Check if this handler should handle the given log record
     */
    public function isHandling(array $record)
    {
        return $record['level'] <= $this->minLevel;
    }

    /**
     * Handle the log record
     */
    public function handle(array $record)
    {
        // Format and write the record
        $formatted = call_user_func($this->formatter, $record);
        $this->writeToErrorLog($formatted);
    }

    /**
     * Set the minimum log level for this handler
     */
    public function setMinLevel($level)
    {
        $this->minLevel = $level;
    }

    /**
     * Get the minimum log level for this handler
     */
    public function getMinLevel()
    {
        return $this->minLevel;
    }

    /**
     * Write formatted record to error log
     */
    private function writeToErrorLog($formatted)
    {
        error_log($formatted, $this->messageType);
    }

    /**
     * Default formatter for log records
     */
    private function formatRecord($record)
    {
        $timestamp = $record['datetime'];
        $level = $record['level_name'];
        $message = $record['message'];

        $output = "[$timestamp] $level: $message";

        // Add context if present
        if (!empty($record['context'])) {
            $context = json_encode($record['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $output .= " | Context: $context";
        }

        // Add request info if present
        if (isset($record['request'])) {
            $request = $record['request'];
            $output .= " | {$request['method']} {$request['uri']} from {$request['ip']}";
        }

        return $output;
    }

    /**
     * Set custom formatter
     */
    public function setFormatter(callable $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Set message type for error_log
     */
    public function setMessageType($type)
    {
        $this->messageType = $type;
    }
}

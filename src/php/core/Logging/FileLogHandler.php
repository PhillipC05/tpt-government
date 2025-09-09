<?php
/**
 * TPT Government Platform - File Log Handler
 *
 * Handles logging to files with rotation, formatting, and size management
 */

class FileLogHandler implements LogHandlerInterface
{
    private $filePath;
    private $minLevel;
    private $maxFileSize;
    private $maxFiles;
    private $formatter;

    /**
     * Constructor
     */
    public function __construct($filePath, $config = [])
    {
        $this->filePath = $filePath;
        $this->minLevel = $config['min_level'] ?? StructuredLogger::DEBUG;
        $this->maxFileSize = $config['max_file_size'] ?? 10485760; // 10MB
        $this->maxFiles = $config['max_files'] ?? 5;
        $this->formatter = $config['formatter'] ?? [$this, 'formatRecord'];

        // Ensure log directory exists
        $logDir = dirname($filePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
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
        // Check if file rotation is needed
        if ($this->shouldRotate()) {
            $this->rotate();
        }

        // Format and write the record
        $formatted = call_user_func($this->formatter, $record);
        $this->writeToFile($formatted);
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
     * Check if file rotation is needed
     */
    private function shouldRotate()
    {
        if (!file_exists($this->filePath)) {
            return false;
        }

        return filesize($this->filePath) >= $this->maxFileSize;
    }

    /**
     * Rotate log files
     */
    private function rotate()
    {
        // Remove oldest file if it exists
        $oldestFile = $this->filePath . '.' . $this->maxFiles;
        if (file_exists($oldestFile)) {
            unlink($oldestFile);
        }

        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $currentFile = $this->filePath . ($i > 1 ? '.' . ($i - 1) : '');
            $nextFile = $this->filePath . '.' . $i;

            if (file_exists($currentFile)) {
                rename($currentFile, $nextFile);
            }
        }

        // Move current file
        if (file_exists($this->filePath)) {
            rename($this->filePath, $this->filePath . '.1');
        }
    }

    /**
     * Write formatted record to file
     */
    private function writeToFile($formatted)
    {
        $result = file_put_contents($this->filePath, $formatted, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            // Fallback to error log if file write fails
            error_log("Failed to write to log file: " . $this->filePath);
        }
    }

    /**
     * Default formatter for log records
     */
    private function formatRecord($record)
    {
        $timestamp = $record['datetime_micro'];
        $level = str_pad($record['level_name'], 9);
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

        // Add system info if present
        if (isset($record['system'])) {
            $system = $record['system'];
            $memory = number_format($system['memory_usage'] / 1024 / 1024, 2) . 'MB';
            $output .= " | Memory: $memory | PID: {$system['pid']}";
        }

        return $output . PHP_EOL;
    }

    /**
     * Get current log file size
     */
    public function getFileSize()
    {
        return file_exists($this->filePath) ? filesize($this->filePath) : 0;
    }

    /**
     * Get log file path
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set custom formatter
     */
    public function setFormatter(callable $formatter)
    {
        $this->formatter = $formatter;
    }
}

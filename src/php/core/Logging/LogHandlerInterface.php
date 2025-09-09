<?php
/**
 * TPT Government Platform - Log Handler Interface
 *
 * Defines the contract for log handlers in the structured logging system
 */

interface LogHandlerInterface
{
    /**
     * Check if this handler should handle the given log record
     *
     * @param array $record The log record
     * @return bool True if this handler should handle the record
     */
    public function isHandling(array $record);

    /**
     * Handle the log record
     *
     * @param array $record The log record to handle
     * @return void
     */
    public function handle(array $record);

    /**
     * Set the minimum log level for this handler
     *
     * @param int $level The minimum log level
     * @return void
     */
    public function setMinLevel($level);

    /**
     * Get the minimum log level for this handler
     *
     * @return int The minimum log level
     */
    public function getMinLevel();
}

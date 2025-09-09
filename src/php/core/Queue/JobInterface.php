<?php
/**
 * TPT Government Platform - Job Queue Interface
 *
 * Defines the contract for job implementations in the queue system
 */

interface JobInterface
{
    /**
     * Execute the job
     *
     * @param array $data Job data payload
     * @return mixed Job execution result
     */
    public function execute($data = []);

    /**
     * Get job name/identifier
     *
     * @return string
     */
    public function getName();

    /**
     * Get job description
     *
     * @return string
     */
    public function getDescription();

    /**
     * Get maximum execution time in seconds
     *
     * @return int
     */
    public function getMaxExecutionTime();

    /**
     * Get maximum retry attempts
     *
     * @return int
     */
    public function getMaxRetries();

    /**
     * Handle job failure
     *
     * @param Exception $exception The exception that caused the failure
     * @param array $data Job data payload
     * @param int $attempt Current attempt number
     * @return bool Whether to retry the job
     */
    public function handleFailure(Exception $exception, $data = [], $attempt = 1);

    /**
     * Validate job data before execution
     *
     * @param array $data Job data payload
     * @return bool
     */
    public function validateData($data = []);

    /**
     * Get job priority (higher = more important)
     *
     * @return int
     */
    public function getPriority();

    /**
     * Check if job can be executed concurrently
     *
     * @return bool
     */
    public function canRunConcurrently();

    /**
     * Get job dependencies (other jobs that must complete first)
     *
     * @return array
     */
    public function getDependencies();
}

/**
 * Base Job Class
 */
abstract class BaseJob implements JobInterface
{
    protected $name;
    protected $description;
    protected $maxExecutionTime = 300; // 5 minutes
    protected $maxRetries = 3;
    protected $priority = 1; // 1-10 scale
    protected $canRunConcurrently = true;
    protected $dependencies = [];

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->configure($config);
    }

    /**
     * Configure job properties
     */
    protected function configure($config)
    {
        if (isset($config['maxExecutionTime'])) {
            $this->maxExecutionTime = $config['maxExecutionTime'];
        }
        if (isset($config['maxRetries'])) {
            $this->maxRetries = $config['maxRetries'];
        }
        if (isset($config['priority'])) {
            $this->priority = max(1, min(10, $config['priority']));
        }
        if (isset($config['canRunConcurrently'])) {
            $this->canRunConcurrently = $config['canRunConcurrently'];
        }
        if (isset($config['dependencies'])) {
            $this->dependencies = $config['dependencies'];
        }
    }

    /**
     * Get job name
     */
    public function getName()
    {
        return $this->name ?: get_class($this);
    }

    /**
     * Get job description
     */
    public function getDescription()
    {
        return $this->description ?: 'No description provided';
    }

    /**
     * Get maximum execution time
     */
    public function getMaxExecutionTime()
    {
        return $this->maxExecutionTime;
    }

    /**
     * Get maximum retry attempts
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * Handle job failure
     */
    public function handleFailure(Exception $exception, $data = [], $attempt = 1)
    {
        // Log the failure
        $this->logFailure($exception, $data, $attempt);

        // Default retry logic: exponential backoff
        return $attempt < $this->maxRetries;
    }

    /**
     * Validate job data
     */
    public function validateData($data = [])
    {
        // Basic validation - override in subclasses for specific validation
        return is_array($data);
    }

    /**
     * Get job priority
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Check if job can run concurrently
     */
    public function canRunConcurrently()
    {
        return $this->canRunConcurrently;
    }

    /**
     * Get job dependencies
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * Log job failure
     */
    protected function logFailure(Exception $exception, $data, $attempt)
    {
        $logMessage = sprintf(
            "Job %s failed on attempt %d: %s\nData: %s\nStack trace: %s",
            $this->getName(),
            $attempt,
            $exception->getMessage(),
            json_encode($data),
            $exception->getTraceAsString()
        );

        $logFile = LOG_PATH . '/job_failures.log';
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $logMessage . "\n\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Set job name
     */
    protected function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Set job description
     */
    protected function setDescription($description)
    {
        $this->description = $description;
    }
}

/**
 * Job Status Constants
 */
class JobStatus
{
    const PENDING = 'pending';
    const RUNNING = 'running';
    const COMPLETED = 'completed';
    const FAILED = 'failed';
    const RETRY = 'retry';
    const CANCELLED = 'cancelled';
    const TIMEOUT = 'timeout';
}

/**
 * Job Priority Constants
 */
class JobPriority
{
    const LOW = 1;
    const NORMAL = 5;
    const HIGH = 8;
    const CRITICAL = 10;
}

/**
 * Job Data Transfer Object
 */
class JobDTO
{
    public $id;
    public $name;
    public $data;
    public $priority;
    public $status;
    public $created_at;
    public $scheduled_at;
    public $started_at;
    public $completed_at;
    public $attempts;
    public $max_attempts;
    public $result;
    public $error_message;
    public $worker_id;
    public $queue_name;

    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Set defaults
        $this->id = $this->id ?? uniqid('job_', true);
        $this->status = $this->status ?? JobStatus::PENDING;
        $this->priority = $this->priority ?? JobPriority::NORMAL;
        $this->attempts = $this->attempts ?? 0;
        $this->max_attempts = $this->max_attempts ?? 3;
        $this->created_at = $this->created_at ?? date('Y-m-d H:i:s');
        $this->scheduled_at = $this->scheduled_at ?? $this->created_at;
        $this->data = $this->data ?? [];
        $this->queue_name = $this->queue_name ?? 'default';
    }

    /**
     * Convert to array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    /**
     * Convert from array
     */
    public static function fromArray($data)
    {
        return new self($data);
    }

    /**
     * Check if job is completed
     */
    public function isCompleted()
    {
        return in_array($this->status, [JobStatus::COMPLETED, JobStatus::FAILED, JobStatus::CANCELLED]);
    }

    /**
     * Check if job can be retried
     */
    public function canRetry()
    {
        return $this->status === JobStatus::FAILED && $this->attempts < $this->max_attempts;
    }

    /**
     * Mark job as started
     */
    public function markStarted($workerId = null)
    {
        $this->status = JobStatus::RUNNING;
        $this->started_at = date('Y-m-d H:i:s');
        $this->worker_id = $workerId;
        $this->attempts++;
    }

    /**
     * Mark job as completed
     */
    public function markCompleted($result = null)
    {
        $this->status = JobStatus::COMPLETED;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->result = $result;
    }

    /**
     * Mark job as failed
     */
    public function markFailed($errorMessage = null)
    {
        $this->status = JobStatus::FAILED;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->error_message = $errorMessage;
    }

    /**
     * Calculate delay for next retry (exponential backoff)
     */
    public function getRetryDelay()
    {
        return pow(2, $this->attempts) * 60; // Exponential backoff in seconds
    }
}

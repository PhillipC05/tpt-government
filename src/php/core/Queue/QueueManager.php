<?php
/**
 * TPT Government Platform - Queue Manager
 *
 * Manages job queues with support for multiple storage backends,
 * priority queues, and job scheduling
 */

class QueueManager
{
    private $storage;
    private $queues = [];
    private $workers = [];
    private $config;
    private $stats = [
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'jobs_succeeded' => 0,
        'avg_processing_time' => 0,
        'queues_active' => 0
    ];

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->config = array_merge([
            'storage_type' => 'database', // database, redis, file
            'max_workers' => 5,
            'worker_timeout' => 300, // 5 minutes
            'retry_delay' => 60, // 1 minute
            'max_retry_attempts' => 3,
            'cleanup_interval' => 3600, // 1 hour
            'enable_monitoring' => true
        ], $config);

        $this->initializeStorage();
        $this->initializeQueues();
        $this->scheduleCleanup();
    }

    /**
     * Initialize storage backend
     */
    private function initializeStorage()
    {
        switch ($this->config['storage_type']) {
            case 'database':
                $this->storage = new DatabaseQueueStorage($this->config);
                break;
            case 'redis':
                $this->storage = new RedisQueueStorage($this->config);
                break;
            case 'file':
                $this->storage = new FileQueueStorage($this->config);
                break;
            default:
                throw new Exception("Unsupported storage type: {$this->config['storage_type']}");
        }
    }

    /**
     * Initialize default queues
     */
    private function initializeQueues()
    {
        $this->createQueue('default', ['priority' => JobPriority::NORMAL]);
        $this->createQueue('high', ['priority' => JobPriority::HIGH]);
        $this->createQueue('low', ['priority' => JobPriority::LOW]);
        $this->createQueue('critical', ['priority' => JobPriority::CRITICAL]);
    }

    /**
     * Create a new queue
     */
    public function createQueue($name, $config = [])
    {
        $config = array_merge([
            'max_size' => 10000,
            'priority' => JobPriority::NORMAL,
            'retention_days' => 7,
            'enable_monitoring' => true
        ], $config);

        $this->queues[$name] = new Queue($name, $config, $this->storage);
        $this->stats['queues_active'] = count($this->queues);

        return $this->queues[$name];
    }

    /**
     * Get a queue by name
     */
    public function getQueue($name = 'default')
    {
        if (!isset($this->queues[$name])) {
            throw new Exception("Queue '{$name}' does not exist");
        }

        return $this->queues[$name];
    }

    /**
     * Add a job to a queue
     */
    public function addJob(JobInterface $job, $data = [], $queueName = 'default', $delay = 0)
    {
        $queue = $this->getQueue($queueName);

        $jobData = [
            'name' => $job->getName(),
            'data' => $data,
            'priority' => $job->getPriority(),
            'max_attempts' => $job->getMaxRetries(),
            'can_run_concurrently' => $job->canRunConcurrently(),
            'dependencies' => $job->getDependencies(),
            'scheduled_at' => $delay > 0 ? date('Y-m-d H:i:s', time() + $delay) : date('Y-m-d H:i:s')
        ];

        $jobDTO = new JobDTO($jobData);
        $queue->push($jobDTO);

        return $jobDTO->id;
    }

    /**
     * Schedule a job for later execution
     */
    public function scheduleJob(JobInterface $job, $data = [], $scheduleTime, $queueName = 'default')
    {
        $queue = $this->getQueue($queueName);

        $jobData = [
            'name' => $job->getName(),
            'data' => $data,
            'priority' => $job->getPriority(),
            'max_attempts' => $job->getMaxRetries(),
            'scheduled_at' => $scheduleTime
        ];

        $jobDTO = new JobDTO($jobData);
        $queue->push($jobDTO);

        return $jobDTO->id;
    }

    /**
     * Start a worker process
     */
    public function startWorker($queueNames = ['default'], $workerId = null)
    {
        $workerId = $workerId ?: uniqid('worker_', true);
        $worker = new QueueWorker($workerId, $queueNames, $this->config);

        $this->workers[$workerId] = $worker;
        $worker->start();

        return $workerId;
    }

    /**
     * Stop a worker process
     */
    public function stopWorker($workerId)
    {
        if (isset($this->workers[$workerId])) {
            $this->workers[$workerId]->stop();
            unset($this->workers[$workerId]);
        }
    }

    /**
     * Stop all workers
     */
    public function stopAllWorkers()
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
        $this->workers = [];
    }

    /**
     * Get job status
     */
    public function getJobStatus($jobId)
    {
        return $this->storage->getJob($jobId);
    }

    /**
     * Cancel a job
     */
    public function cancelJob($jobId)
    {
        $job = $this->storage->getJob($jobId);
        if ($job && $job->status === JobStatus::PENDING) {
            $job->status = JobStatus::CANCELLED;
            $this->storage->updateJob($job);
            return true;
        }

        return false;
    }

    /**
     * Retry a failed job
     */
    public function retryJob($jobId)
    {
        $job = $this->storage->getJob($jobId);
        if ($job && $job->canRetry()) {
            $job->status = JobStatus::PENDING;
            $job->scheduled_at = date('Y-m-d H:i:s', time() + $job->getRetryDelay());
            $this->storage->updateJob($job);
            return true;
        }

        return false;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats($queueName = null)
    {
        if ($queueName) {
            return isset($this->queues[$queueName]) ? $this->queues[$queueName]->getStats() : null;
        }

        $allStats = [];
        foreach ($this->queues as $name => $queue) {
            $allStats[$name] = $queue->getStats();
        }

        return $allStats;
    }

    /**
     * Get overall system statistics
     */
    public function getSystemStats()
    {
        $systemStats = $this->stats;
        $systemStats['workers_active'] = count($this->workers);
        $systemStats['storage_type'] = $this->config['storage_type'];
        $systemStats['queues'] = $this->getQueueStats();

        return $systemStats;
    }

    /**
     * Process pending jobs (for manual processing)
     */
    public function processJobs($queueName = 'default', $limit = 10)
    {
        $queue = $this->getQueue($queueName);
        $processed = 0;

        while ($processed < $limit) {
            $job = $queue->pop();
            if (!$job) {
                break;
            }

            try {
                $this->executeJob($job);
                $processed++;
            } catch (Exception $e) {
                $this->handleJobFailure($job, $e);
            }
        }

        return $processed;
    }

    /**
     * Execute a job
     */
    private function executeJob(JobDTO $job)
    {
        $startTime = microtime(true);

        // Load job class
        $jobClass = $this->loadJobClass($job->name);
        if (!$jobClass) {
            throw new Exception("Job class '{$job->name}' not found");
        }

        $jobInstance = new $jobClass();

        // Validate job data
        if (!$jobInstance->validateData($job->data)) {
            throw new Exception("Invalid job data for '{$job->name}'");
        }

        // Mark job as started
        $job->markStarted();
        $this->storage->updateJob($job);

        // Execute job with timeout
        $result = $this->executeJobWithTimeout($jobInstance, $job->data, $jobInstance->getMaxExecutionTime());

        // Mark job as completed
        $job->markCompleted($result);
        $this->storage->updateJob($job);

        $processingTime = microtime(true) - $startTime;
        $this->updateStats(true, $processingTime);

        return $result;
    }

    /**
     * Execute job with timeout protection
     */
    private function executeJobWithTimeout(JobInterface $job, $data, $timeout)
    {
        // Set up signal handler for timeout (Unix systems only)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, function() {
                throw new Exception("Job execution timed out");
            });
            pcntl_alarm($timeout);
        }

        try {
            $result = $job->execute($data);

            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0); // Cancel alarm
            }

            return $result;
        } catch (Exception $e) {
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0); // Cancel alarm
            }
            throw $e;
        }
    }

    /**
     * Handle job execution failure
     */
    private function handleJobFailure(JobDTO $job, Exception $exception)
    {
        $jobClass = $this->loadJobClass($job->name);
        $jobInstance = $jobClass ? new $jobClass() : null;

        $shouldRetry = false;
        if ($jobInstance) {
            $shouldRetry = $jobInstance->handleFailure($exception, $job->data, $job->attempts);
        }

        if ($shouldRetry && $job->canRetry()) {
            $job->status = JobStatus::RETRY;
            $job->scheduled_at = date('Y-m-d H:i:s', time() + $job->getRetryDelay());
        } else {
            $job->markFailed($exception->getMessage());
        }

        $this->storage->updateJob($job);
        $this->updateStats(false);
    }

    /**
     * Load job class
     */
    private function loadJobClass($jobName)
    {
        // Try to autoload the job class
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $jobName)));

        if (class_exists($className)) {
            return $className;
        }

        // Try with Job suffix
        $classNameWithSuffix = $className . 'Job';
        if (class_exists($classNameWithSuffix)) {
            return $classNameWithSuffix;
        }

        return null;
    }

    /**
     * Update system statistics
     */
    private function updateStats($success, $processingTime = null)
    {
        if ($success) {
            $this->stats['jobs_succeeded']++;
        } else {
            $this->stats['jobs_failed']++;
        }

        $this->stats['jobs_processed']++;

        if ($processingTime !== null) {
            $this->stats['avg_processing_time'] =
                ($this->stats['avg_processing_time'] * ($this->stats['jobs_processed'] - 1) + $processingTime) /
                $this->stats['jobs_processed'];
        }
    }

    /**
     * Schedule periodic cleanup
     */
    private function scheduleCleanup()
    {
        if ($this->config['cleanup_interval'] > 0) {
            // In a real application, this would be handled by a cron job or scheduled task
            register_shutdown_function([$this, 'cleanup']);
        }
    }

    /**
     * Clean up old completed jobs
     */
    public function cleanup()
    {
        foreach ($this->queues as $queue) {
            $queue->cleanup();
        }

        $this->storage->cleanup();
    }

    /**
     * Get failed jobs for retry
     */
    public function getFailedJobs($queueName = null, $limit = 100)
    {
        return $this->storage->getJobsByStatus(JobStatus::FAILED, $queueName, $limit);
    }

    /**
     * Bulk retry failed jobs
     */
    public function retryFailedJobs($queueName = null, $limit = 50)
    {
        $failedJobs = $this->getFailedJobs($queueName, $limit);
        $retried = 0;

        foreach ($failedJobs as $job) {
            if ($this->retryJob($job->id)) {
                $retried++;
            }
        }

        return $retried;
    }

    /**
     * Get pending jobs count
     */
    public function getPendingJobsCount($queueName = null)
    {
        if ($queueName) {
            return $this->getQueue($queueName)->getPendingCount();
        }

        $total = 0;
        foreach ($this->queues as $queue) {
            $total += $queue->getPendingCount();
        }

        return $total;
    }

    /**
     * Clear all queues
     */
    public function clearAllQueues()
    {
        foreach ($this->queues as $queue) {
            $queue->clear();
        }
    }

    /**
     * Export queue data
     */
    public function exportData($format = 'json')
    {
        $data = [
            'system_stats' => $this->getSystemStats(),
            'queues' => $this->getQueueStats(),
            'workers' => array_keys($this->workers),
            'exported_at' => date('Y-m-d H:i:s')
        ];

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV($data);
            default:
                return $data;
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCSV($data)
    {
        $csv = "Metric,Value\n";

        foreach ($data['system_stats'] as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $csv .= "{$key},{$value}\n";
        }

        return $csv;
    }
}

/**
 * Queue Class
 */
class Queue
{
    private $name;
    private $config;
    private $storage;
    private $stats = [
        'jobs_queued' => 0,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'avg_processing_time' => 0
    ];

    public function __construct($name, $config, $storage)
    {
        $this->name = $name;
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Add job to queue
     */
    public function push(JobDTO $job)
    {
        $job->queue_name = $this->name;
        $this->storage->storeJob($job);
        $this->stats['jobs_queued']++;
    }

    /**
     * Get next job from queue
     */
    public function pop()
    {
        $job = $this->storage->getNextJob($this->name);
        return $job;
    }

    /**
     * Peek at next job without removing it
     */
    public function peek()
    {
        return $this->storage->peekNextJob($this->name);
    }

    /**
     * Get queue size
     */
    public function size()
    {
        return $this->storage->getQueueSize($this->name);
    }

    /**
     * Check if queue is empty
     */
    public function isEmpty()
    {
        return $this->size() === 0;
    }

    /**
     * Get pending jobs count
     */
    public function getPendingCount()
    {
        return $this->storage->getPendingJobsCount($this->name);
    }

    /**
     * Clear queue
     */
    public function clear()
    {
        $this->storage->clearQueue($this->name);
        $this->stats = [
            'jobs_queued' => 0,
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'avg_processing_time' => 0
        ];
    }

    /**
     * Get queue statistics
     */
    public function getStats()
    {
        return array_merge($this->stats, [
            'current_size' => $this->size(),
            'pending_count' => $this->getPendingCount(),
            'name' => $this->name,
            'config' => $this->config
        ]);
    }

    /**
     * Update job processing statistics
     */
    public function updateJobStats($success, $processingTime = null)
    {
        if ($success) {
            $this->stats['jobs_processed']++;
        } else {
            $this->stats['jobs_failed']++;
        }

        if ($processingTime !== null) {
            $this->stats['avg_processing_time'] =
                ($this->stats['avg_processing_time'] * ($this->stats['jobs_processed'] + $this->stats['jobs_failed'] - 1) + $processingTime) /
                ($this->stats['jobs_processed'] + $this->stats['jobs_failed']);
        }
    }

    /**
     * Clean up old jobs
     */
    public function cleanup()
    {
        $this->storage->cleanupQueue($this->name, $this->config['retention_days']);
    }
}

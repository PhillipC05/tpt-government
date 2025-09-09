<?php
/**
 * TPT Government Platform - Queue Worker
 *
 * Background worker process for processing queued jobs
 */

class QueueWorker
{
    private $workerId;
    private $queueNames;
    private $config;
    private $isRunning = false;
    private $currentJob = null;
    private $stats = [
        'jobs_processed' => 0,
        'jobs_succeeded' => 0,
        'jobs_failed' => 0,
        'start_time' => null,
        'last_heartbeat' => null,
        'memory_usage' => 0,
        'processing_times' => []
    ];
    private $storage;
    private $logger;

    /**
     * Constructor
     */
    public function __construct($workerId, $queueNames = ['default'], $config = [])
    {
        $this->workerId = $workerId;
        $this->queueNames = (array) $queueNames;
        $this->config = array_merge([
            'sleep_time' => 1, // seconds between job checks
            'max_memory' => 128 * 1024 * 1024, // 128MB
            'max_execution_time' => 3600, // 1 hour
            'heartbeat_interval' => 30, // seconds
            'enable_logging' => true,
            'log_level' => 'INFO'
        ], $config);

        $this->initializeStorage();
        $this->initializeLogger();
        $this->stats['start_time'] = time();
    }

    /**
     * Initialize storage backend
     */
    private function initializeStorage()
    {
        // Use the same storage type as the queue manager
        $storageType = $this->config['storage_type'] ?? 'database';

        switch ($storageType) {
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
                throw new Exception("Unsupported storage type: {$storageType}");
        }
    }

    /**
     * Initialize logger
     */
    private function initializeLogger()
    {
        if ($this->config['enable_logging']) {
            $this->logger = new SharedLoggerService();
        }
    }

    /**
     * Start the worker
     */
    public function start()
    {
        $this->isRunning = true;
        $this->log("Starting worker {$this->workerId} for queues: " . implode(', ', $this->queueNames));

        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }

        $this->run();
    }

    /**
     * Stop the worker
     */
    public function stop()
    {
        $this->isRunning = false;
        $this->log("Stopping worker {$this->workerId}");

        if ($this->currentJob) {
            $this->failCurrentJob("Worker shutdown");
        }
    }

    /**
     * Main worker loop
     */
    private function run()
    {
        $this->log("Worker {$this->workerId} entering main loop");

        while ($this->isRunning) {
            try {
                // Check for shutdown signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Check memory usage
                if ($this->shouldRestart()) {
                    $this->log("Worker {$this->workerId} restarting due to high memory usage");
                    break;
                }

                // Update heartbeat
                $this->updateHeartbeat();

                // Try to get and process a job
                $job = $this->getNextJob();

                if ($job) {
                    $this->processJob($job);
                } else {
                    // No job available, sleep before checking again
                    sleep($this->config['sleep_time']);
                }

            } catch (Exception $e) {
                $this->log("Error in worker loop: " . $e->getMessage(), 'ERROR');
                sleep($this->config['sleep_time']);
            }
        }

        $this->cleanup();
    }

    /**
     * Get next job from available queues
     */
    private function getNextJob()
    {
        // Try queues in order of priority
        foreach ($this->queueNames as $queueName) {
            $job = $this->storage->getNextJob($queueName);
            if ($job) {
                $job->worker_id = $this->workerId;
                return $job;
            }
        }

        return null;
    }

    /**
     * Process a job
     */
    private function processJob(JobDTO $job)
    {
        $this->currentJob = $job;
        $startTime = microtime(true);

        $this->log("Processing job {$job->id} ({$job->name})");

        try {
            // Load and validate job class
            $jobClass = $this->loadJobClass($job->name);
            if (!$jobClass) {
                throw new Exception("Job class '{$job->name}' not found");
            }

            $jobInstance = new $jobClass();

            // Validate job data
            if (!$jobInstance->validateData($job->data)) {
                throw new Exception("Invalid job data for '{$job->name}'");
            }

            // Execute job with timeout protection
            $result = $this->executeJobWithTimeout($jobInstance, $job->data, $jobInstance->getMaxExecutionTime());

            // Mark job as completed
            $processingTime = microtime(true) - $startTime;
            $job->markCompleted($result);
            $this->storage->updateJob($job);

            $this->stats['jobs_processed']++;
            $this->stats['jobs_succeeded']++;
            $this->stats['processing_times'][] = $processingTime;

            $this->log("Job {$job->id} completed successfully in " . round($processingTime, 2) . "s");

        } catch (Exception $e) {
            $this->handleJobFailure($job, $e);
        } finally {
            $this->currentJob = null;
        }
    }

    /**
     * Execute job with timeout protection
     */
    private function executeJobWithTimeout(JobInterface $job, $data, $timeout)
    {
        // Set up timeout handler
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
        $this->stats['jobs_failed']++;

        // Load job class to handle failure
        $jobClass = $this->loadJobClass($job->name);
        $jobInstance = $jobClass ? new $jobClass() : null;

        $shouldRetry = false;
        if ($jobInstance) {
            $shouldRetry = $jobInstance->handleFailure($exception, $job->data, $job->attempts);
        }

        if ($shouldRetry && $job->canRetry()) {
            $job->status = JobStatus::RETRY;
            $job->scheduled_at = date('Y-m-d H:i:s', time() + $job->getRetryDelay());
            $this->log("Job {$job->id} scheduled for retry (attempt {$job->attempts})");
        } else {
            $job->markFailed($exception->getMessage());
            $this->log("Job {$job->id} failed permanently: " . $exception->getMessage(), 'ERROR');
        }

        $this->storage->updateJob($job);
    }

    /**
     * Fail current job with message
     */
    private function failCurrentJob($message)
    {
        if ($this->currentJob) {
            $this->currentJob->markFailed($message);
            $this->storage->updateJob($this->currentJob);
            $this->currentJob = null;
        }
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
     * Check if worker should restart
     */
    private function shouldRestart()
    {
        $memoryUsage = memory_get_usage(true);

        // Check memory limit
        if ($memoryUsage > $this->config['max_memory']) {
            return true;
        }

        // Check execution time limit
        if ($this->stats['start_time'] &&
            (time() - $this->stats['start_time']) > $this->config['max_execution_time']) {
            return true;
        }

        return false;
    }

    /**
     * Update heartbeat
     */
    private function updateHeartbeat()
    {
        $currentTime = time();

        if (!$this->stats['last_heartbeat'] ||
            ($currentTime - $this->stats['last_heartbeat']) >= $this->config['heartbeat_interval']) {

            $this->stats['last_heartbeat'] = $currentTime;
            $this->stats['memory_usage'] = memory_get_usage(true);

            // Store heartbeat in a way that other processes can check
            $this->storeHeartbeat();
        }
    }

    /**
     * Store heartbeat information
     */
    private function storeHeartbeat()
    {
        $heartbeatFile = QUEUE_PATH . "/workers/{$this->workerId}.heartbeat";

        if (!is_dir(dirname($heartbeatFile))) {
            mkdir(dirname($heartbeatFile), 0755, true);
        }

        $heartbeatData = [
            'worker_id' => $this->workerId,
            'timestamp' => $this->stats['last_heartbeat'],
            'memory_usage' => $this->stats['memory_usage'],
            'jobs_processed' => $this->stats['jobs_processed'],
            'current_job' => $this->currentJob ? $this->currentJob->id : null,
            'queues' => $this->queueNames
        ];

        file_put_contents($heartbeatFile, json_encode($heartbeatData), LOCK_EX);
    }

    /**
     * Log message
     */
    private function log($message, $level = 'INFO')
    {
        if ($this->logger) {
            $this->logger->log($level, "[Worker:{$this->workerId}] " . $message);
        }

        // Also log to worker-specific log file
        $logFile = LOG_PATH . '/workers.log';
        $logEntry = sprintf(
            "[%s] [%s] [Worker:%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $this->workerId,
            $message
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Shutdown handler
     */
    public function shutdown()
    {
        $this->log("Received shutdown signal");
        $this->stop();
    }

    /**
     * Cleanup on shutdown
     */
    private function cleanup()
    {
        // Clean up heartbeat file
        $heartbeatFile = QUEUE_PATH . "/workers/{$this->workerId}.heartbeat";
        if (file_exists($heartbeatFile)) {
            unlink($heartbeatFile);
        }

        // Log final statistics
        $runtime = time() - $this->stats['start_time'];
        $this->log("Worker shutdown after {$runtime}s. Processed: {$this->stats['jobs_processed']} jobs");

        $this->logFinalStats();
    }

    /**
     * Log final statistics
     */
    private function logFinalStats()
    {
        $stats = $this->getStats();

        $this->log("Final Statistics:");
        $this->log("  Jobs Processed: {$stats['jobs_processed']}");
        $this->log("  Jobs Succeeded: {$stats['jobs_succeeded']}");
        $this->log("  Jobs Failed: {$stats['jobs_failed']}");
        $this->log("  Success Rate: " . round($stats['success_rate'], 2) . "%");
        $this->log("  Average Processing Time: " . round($stats['avg_processing_time'], 2) . "s");
        $this->log("  Peak Memory Usage: " . round($stats['peak_memory_usage'] / 1024 / 1024, 2) . "MB");
    }

    /**
     * Get worker statistics
     */
    public function getStats()
    {
        $totalJobs = $this->stats['jobs_processed'];
        $successRate = $totalJobs > 0 ? ($this->stats['jobs_succeeded'] / $totalJobs) * 100 : 0;
        $avgProcessingTime = !empty($this->stats['processing_times']) ?
            array_sum($this->stats['processing_times']) / count($this->stats['processing_times']) : 0;

        return [
            'worker_id' => $this->workerId,
            'queues' => $this->queueNames,
            'is_running' => $this->isRunning,
            'start_time' => $this->stats['start_time'],
            'runtime' => time() - $this->stats['start_time'],
            'jobs_processed' => $this->stats['jobs_processed'],
            'jobs_succeeded' => $this->stats['jobs_succeeded'],
            'jobs_failed' => $this->stats['jobs_failed'],
            'success_rate' => $successRate,
            'avg_processing_time' => $avgProcessingTime,
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'last_heartbeat' => $this->stats['last_heartbeat'],
            'current_job' => $this->currentJob ? $this->currentJob->id : null
        ];
    }

    /**
     * Check if worker is healthy
     */
    public function isHealthy()
    {
        // Check if worker is running
        if (!$this->isRunning) {
            return false;
        }

        // Check heartbeat
        if ($this->stats['last_heartbeat'] &&
            (time() - $this->stats['last_heartbeat']) > ($this->config['heartbeat_interval'] * 2)) {
            return false;
        }

        // Check memory usage
        if (memory_get_usage(true) > $this->config['max_memory']) {
            return false;
        }

        return true;
    }

    /**
     * Get current job
     */
    public function getCurrentJob()
    {
        return $this->currentJob;
    }

    /**
     * Get worker ID
     */
    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     * Get queue names
     */
    public function getQueueNames()
    {
        return $this->queueNames;
    }
}

/**
 * Queue Worker Manager
 * Manages multiple worker processes
 */
class QueueWorkerManager
{
    private $workers = [];
    private $config;
    private $logger;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'max_workers' => 5,
            'worker_queues' => ['default'],
            'auto_restart' => true,
            'health_check_interval' => 60,
            'enable_logging' => true
        ], $config);

        $this->logger = $this->config['enable_logging'] ? new SharedLoggerService() : null;
    }

    /**
     * Start workers
     */
    public function startWorkers($count = null)
    {
        $count = $count ?? $this->config['max_workers'];

        for ($i = 0; $i < $count; $i++) {
            $this->startWorker();
        }

        if ($this->config['auto_restart']) {
            $this->startHealthChecker();
        }
    }

    /**
     * Start a single worker
     */
    public function startWorker($queueNames = null)
    {
        $queueNames = $queueNames ?? $this->config['worker_queues'];
        $workerId = uniqid('worker_', true);

        // In a real implementation, this would fork a new process
        // For now, we'll create the worker object
        $worker = new QueueWorker($workerId, $queueNames, $this->config);
        $this->workers[$workerId] = $worker;

        $this->log("Started worker {$workerId} for queues: " . implode(', ', $queueNames));

        return $workerId;
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
     * Stop specific worker
     */
    public function stopWorker($workerId)
    {
        if (isset($this->workers[$workerId])) {
            $this->workers[$workerId]->stop();
            unset($this->workers[$workerId]);
        }
    }

    /**
     * Get worker statistics
     */
    public function getWorkerStats($workerId = null)
    {
        if ($workerId) {
            return isset($this->workers[$workerId]) ? $this->workers[$workerId]->getStats() : null;
        }

        $allStats = [];
        foreach ($this->workers as $id => $worker) {
            $allStats[$id] = $worker->getStats();
        }

        return $allStats;
    }

    /**
     * Get overall statistics
     */
    public function getOverallStats()
    {
        $stats = [
            'total_workers' => count($this->workers),
            'active_workers' => 0,
            'total_jobs_processed' => 0,
            'total_jobs_succeeded' => 0,
            'total_jobs_failed' => 0,
            'avg_success_rate' => 0,
            'avg_processing_time' => 0
        ];

        $successRates = [];
        $processingTimes = [];

        foreach ($this->workers as $worker) {
            $workerStats = $worker->getStats();

            if ($workerStats['is_running']) {
                $stats['active_workers']++;
            }

            $stats['total_jobs_processed'] += $workerStats['jobs_processed'];
            $stats['total_jobs_succeeded'] += $workerStats['jobs_succeeded'];
            $stats['total_jobs_failed'] += $workerStats['jobs_failed'];

            if ($workerStats['jobs_processed'] > 0) {
                $successRates[] = $workerStats['success_rate'];
                $processingTimes[] = $workerStats['avg_processing_time'];
            }
        }

        if (!empty($successRates)) {
            $stats['avg_success_rate'] = array_sum($successRates) / count($successRates);
        }

        if (!empty($processingTimes)) {
            $stats['avg_processing_time'] = array_sum($processingTimes) / count($processingTimes);
        }

        return $stats;
    }

    /**
     * Scale workers based on queue load
     */
    public function scaleWorkers($queueManager)
    {
        $pendingJobs = $queueManager->getPendingJobsCount();
        $currentWorkers = count($this->workers);

        $targetWorkers = min($this->config['max_workers'],
            max(1, ceil($pendingJobs / 10))); // 1 worker per 10 pending jobs

        if ($targetWorkers > $currentWorkers) {
            // Scale up
            $workersToAdd = $targetWorkers - $currentWorkers;
            for ($i = 0; $i < $workersToAdd; $i++) {
                $this->startWorker();
            }
            $this->log("Scaled up to {$targetWorkers} workers");
        } elseif ($targetWorkers < $currentWorkers) {
            // Scale down
            $workersToRemove = $currentWorkers - $targetWorkers;
            $workerIds = array_keys($this->workers);
            for ($i = 0; $i < $workersToRemove; $i++) {
                $this->stopWorker($workerIds[$i]);
            }
            $this->log("Scaled down to {$targetWorkers} workers");
        }
    }

    /**
     * Start health checker
     */
    private function startHealthChecker()
    {
        // In a real implementation, this would run in a separate process/thread
        // For now, we'll just log that it would start
        $this->log("Health checker would start (not implemented in demo)");
    }

    /**
     * Log message
     */
    private function log($message, $level = 'INFO')
    {
        if ($this->logger) {
            $this->logger->log($level, "[WorkerManager] " . $message);
        }
    }

    /**
     * Get active workers count
     */
    public function getActiveWorkersCount()
    {
        return count(array_filter($this->workers, function($worker) {
            return $worker->isHealthy();
        }));
    }
}

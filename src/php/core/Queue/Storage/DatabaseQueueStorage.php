<?php
/**
 * TPT Government Platform - Database Queue Storage
 *
 * Database-based storage backend for job queues
 */

class DatabaseQueueStorage
{
    private $db;
    private $tableName;
    private $config;

    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        $this->config = $config;
        $this->tableName = $config['table_name'] ?? 'job_queue';
        $this->initializeDatabase();
        $this->createTable();
    }

    /**
     * Initialize database connection
     */
    private function initializeDatabase()
    {
        // Get database connection from container or create new one
        if (class_exists('Container') && Container::has('database')) {
            $this->db = Container::get('database');
        } else {
            $config = require CONFIG_PATH . '/database.php';
            $this->db = new PDO(
                "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
                $config['username'],
                $config['password'],
                $config['options']
            );
        }
    }

    /**
     * Create job queue table
     */
    private function createTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                data TEXT,
                priority INTEGER DEFAULT 5,
                status VARCHAR(50) DEFAULT 'pending',
                queue_name VARCHAR(100) DEFAULT 'default',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                attempts INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 3,
                result TEXT,
                error_message TEXT,
                worker_id VARCHAR(255),
                can_run_concurrently BOOLEAN DEFAULT TRUE,
                dependencies TEXT,
                INDEX idx_status (status),
                INDEX idx_queue_priority (queue_name, priority DESC, created_at ASC),
                INDEX idx_scheduled (scheduled_at),
                INDEX idx_created (created_at)
            )
        ";

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            // Log error but don't fail
            error_log("Failed to create job queue table: " . $e->getMessage());
        }
    }

    /**
     * Store a job
     */
    public function storeJob(JobDTO $job)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->tableName}
                (id, name, data, priority, status, queue_name, scheduled_at, max_attempts, can_run_concurrently, dependencies)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (id) DO UPDATE SET
                    name = EXCLUDED.name,
                    data = EXCLUDED.data,
                    priority = EXCLUDED.priority,
                    status = EXCLUDED.status,
                    scheduled_at = EXCLUDED.scheduled_at,
                    max_attempts = EXCLUDED.max_attempts,
                    can_run_concurrently = EXCLUDED.can_run_concurrently,
                    dependencies = EXCLUDED.dependencies
            ");

            $stmt->execute([
                $job->id,
                $job->name,
                json_encode($job->data),
                $job->priority,
                $job->status,
                $job->queue_name,
                $job->scheduled_at,
                $job->max_attempts,
                $job->can_run_concurrently ? 1 : 0,
                json_encode($job->dependencies ?? [])
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Failed to store job {$job->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a job by ID
     */
    public function getJob($jobId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->tableName} WHERE id = ?");
            $stmt->execute([$jobId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            return $this->createJobDTOFromRow($result);
        } catch (Exception $e) {
            error_log("Failed to get job {$jobId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a job
     */
    public function updateJob(JobDTO $job)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->tableName} SET
                    status = ?,
                    started_at = ?,
                    completed_at = ?,
                    attempts = ?,
                    result = ?,
                    error_message = ?,
                    worker_id = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $job->status,
                $job->started_at,
                $job->completed_at,
                $job->attempts,
                is_string($job->result) ? $job->result : json_encode($job->result),
                $job->error_message,
                $job->worker_id,
                $job->id
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Failed to update job {$job->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get next job from queue
     */
    public function getNextJob($queueName = 'default')
    {
        try {
            // Start transaction for atomic operation
            $this->db->beginTransaction();

            // Find the next available job
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tableName}
                WHERE queue_name = ?
                  AND status IN ('pending', 'retry')
                  AND scheduled_at <= CURRENT_TIMESTAMP
                  AND attempts < max_attempts
                ORDER BY priority DESC, created_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            ");

            $stmt->execute([$queueName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $this->db->rollBack();
                return null;
            }

            // Mark job as running
            $updateStmt = $this->db->prepare("
                UPDATE {$this->tableName}
                SET status = 'running', started_at = CURRENT_TIMESTAMP, attempts = attempts + 1
                WHERE id = ?
            ");
            $updateStmt->execute([$result['id']]);

            $this->db->commit();

            return $this->createJobDTOFromRow($result);
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to get next job from queue {$queueName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Peek at next job without removing it
     */
    public function peekNextJob($queueName = 'default')
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tableName}
                WHERE queue_name = ?
                  AND status IN ('pending', 'retry')
                  AND scheduled_at <= CURRENT_TIMESTAMP
                  AND attempts < max_attempts
                ORDER BY priority DESC, created_at ASC
                LIMIT 1
            ");

            $stmt->execute([$queueName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return null;
            }

            return $this->createJobDTOFromRow($result);
        } catch (Exception $e) {
            error_log("Failed to peek next job from queue {$queueName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get queue size
     */
    public function getQueueSize($queueName = null)
    {
        try {
            if ($queueName) {
                $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->tableName} WHERE queue_name = ?");
                $stmt->execute([$queueName]);
            } else {
                $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->tableName}");
                $stmt->execute();
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['count'];
        } catch (Exception $e) {
            error_log("Failed to get queue size: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get pending jobs count
     */
    public function getPendingJobsCount($queueName = null)
    {
        try {
            if ($queueName) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count FROM {$this->tableName}
                    WHERE queue_name = ? AND status IN ('pending', 'retry')
                ");
                $stmt->execute([$queueName]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count FROM {$this->tableName}
                    WHERE status IN ('pending', 'retry')
                ");
                $stmt->execute();
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['count'];
        } catch (Exception $e) {
            error_log("Failed to get pending jobs count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get jobs by status
     */
    public function getJobsByStatus($status, $queueName = null, $limit = 100)
    {
        try {
            $sql = "SELECT * FROM {$this->tableName} WHERE status = ?";
            $params = [$status];

            if ($queueName) {
                $sql .= " AND queue_name = ?";
                $params[] = $queueName;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $jobs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobs[] = $this->createJobDTOFromRow($row);
            }

            return $jobs;
        } catch (Exception $e) {
            error_log("Failed to get jobs by status {$status}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear queue
     */
    public function clearQueue($queueName = null)
    {
        try {
            if ($queueName) {
                $stmt = $this->db->prepare("DELETE FROM {$this->tableName} WHERE queue_name = ?");
                $stmt->execute([$queueName]);
            } else {
                $stmt = $this->db->prepare("DELETE FROM {$this->tableName}");
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            error_log("Failed to clear queue: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up old jobs
     */
    public function cleanup($retentionDays = 7)
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $stmt = $this->db->prepare("
                DELETE FROM {$this->tableName}
                WHERE status IN ('completed', 'failed', 'cancelled')
                  AND completed_at < ?
            ");
            $stmt->execute([$cutoffDate]);

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to cleanup old jobs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up specific queue
     */
    public function cleanupQueue($queueName, $retentionDays = 7)
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            $stmt = $this->db->prepare("
                DELETE FROM {$this->tableName}
                WHERE queue_name = ?
                  AND status IN ('completed', 'failed', 'cancelled')
                  AND completed_at < ?
            ");
            $stmt->execute([$queueName, $cutoffDate]);

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to cleanup queue {$queueName}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats($queueName = null)
    {
        try {
            $stats = [
                'total_jobs' => 0,
                'pending_jobs' => 0,
                'running_jobs' => 0,
                'completed_jobs' => 0,
                'failed_jobs' => 0,
                'avg_processing_time' => 0
            ];

            // Get status counts
            $statusSql = "SELECT status, COUNT(*) as count FROM {$this->tableName}";
            $params = [];

            if ($queueName) {
                $statusSql .= " WHERE queue_name = ?";
                $params[] = $queueName;
            }

            $statusSql .= " GROUP BY status";

            $stmt = $this->db->prepare($statusSql);
            $stmt->execute($params);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['total_jobs'] += $row['count'];

                switch ($row['status']) {
                    case 'pending':
                    case 'retry':
                        $stats['pending_jobs'] += $row['count'];
                        break;
                    case 'running':
                        $stats['running_jobs'] += $row['count'];
                        break;
                    case 'completed':
                        $stats['completed_jobs'] += $row['count'];
                        break;
                    case 'failed':
                    case 'timeout':
                        $stats['failed_jobs'] += $row['count'];
                        break;
                }
            }

            // Calculate average processing time
            $timeSql = "
                SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_time
                FROM {$this->tableName}
                WHERE status = 'completed' AND started_at IS NOT NULL AND completed_at IS NOT NULL
            ";

            if ($queueName) {
                $timeSql .= " AND queue_name = ?";
            }

            $timeStmt = $this->db->prepare($timeSql);
            $timeStmt->execute($queueName ? [$queueName] : []);
            $timeResult = $timeStmt->fetch(PDO::FETCH_ASSOC);

            $stats['avg_processing_time'] = $timeResult['avg_time'] ?? 0;

            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get queue stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create JobDTO from database row
     */
    private function createJobDTOFromRow($row)
    {
        $job = new JobDTO([
            'id' => $row['id'],
            'name' => $row['name'],
            'data' => json_decode($row['data'], true),
            'priority' => (int) $row['priority'],
            'status' => $row['status'],
            'queue_name' => $row['queue_name'],
            'created_at' => $row['created_at'],
            'scheduled_at' => $row['scheduled_at'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'attempts' => (int) $row['attempts'],
            'max_attempts' => (int) $row['max_attempts'],
            'result' => $row['result'] ? json_decode($row['result'], true) : null,
            'error_message' => $row['error_message'],
            'worker_id' => $row['worker_id'],
            'can_run_concurrently' => (bool) $row['can_run_concurrently'],
            'dependencies' => json_decode($row['dependencies'], true)
        ]);

        return $job;
    }

    /**
     * Get failed jobs for analysis
     */
    public function getFailedJobs($queueName = null, $limit = 100)
    {
        return $this->getJobsByStatus('failed', $queueName, $limit);
    }

    /**
     * Get stuck jobs (running but no heartbeat)
     */
    public function getStuckJobs($timeoutMinutes = 30)
    {
        try {
            $timeout = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));

            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tableName}
                WHERE status = 'running'
                  AND started_at < ?
                ORDER BY started_at ASC
            ");

            $stmt->execute([$timeout]);

            $jobs = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobs[] = $this->createJobDTOFromRow($row);
            }

            return $jobs;
        } catch (Exception $e) {
            error_log("Failed to get stuck jobs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Reset stuck jobs
     */
    public function resetStuckJobs($timeoutMinutes = 30)
    {
        try {
            $timeout = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));

            $stmt = $this->db->prepare("
                UPDATE {$this->tableName}
                SET status = 'pending', started_at = NULL, worker_id = NULL
                WHERE status = 'running' AND started_at < ?
            ");

            $stmt->execute([$timeout]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to reset stuck jobs: " . $e->getMessage());
            return 0;
        }
    }
}

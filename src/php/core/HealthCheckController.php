<?php
/**
 * TPT Government Platform - Health Check Controller
 *
 * Provides comprehensive health check endpoints for monitoring system status,
 * performance metrics, and service availability
 */

class HealthCheckController extends Controller
{
    private $logger;
    private $errorReporter;
    private $cache;
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new StructuredLogger();
        $this->errorReporter = new ErrorReportingManager($this->logger);
        $this->cache = CacheManager::getInstance();
        $this->database = Database::getInstance();
    }

    /**
     * Basic health check endpoint
     * GET /health
     */
    public function basic()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => TPT_VERSION ?? '1.0.0',
            'environment' => getenv('APP_ENV') ?: 'production'
        ];

        $this->json($health);
    }

    /**
     * Detailed health check endpoint
     * GET /health/detailed
     */
    public function detailed()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => TPT_VERSION ?? '1.0.0',
            'environment' => getenv('APP_ENV') ?: 'production',
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'filesystem' => $this->checkFilesystem(),
                'memory' => $this->checkMemory(),
                'load' => $this->checkSystemLoad(),
                'disk_space' => $this->checkDiskSpace()
            ]
        ];

        // Determine overall status
        $failedChecks = array_filter($health['checks'], function($check) {
            return $check['status'] !== 'healthy';
        });

        if (!empty($failedChecks)) {
            $health['status'] = 'unhealthy';
            $health['issues'] = array_keys($failedChecks);
        }

        $this->json($health);
    }

    /**
     * Performance metrics endpoint
     * GET /health/metrics
     */
    public function metrics()
    {
        $metrics = [
            'timestamp' => date('c'),
            'performance' => [
                'response_time' => $this->getResponseTime(),
                'memory_usage' => $this->getMemoryUsage(),
                'cpu_usage' => $this->getCpuUsage(),
                'active_connections' => $this->getActiveConnections()
            ],
            'system' => [
                'load_average' => sys_getloadavg(),
                'uptime' => $this->getSystemUptime(),
                'disk_usage' => $this->getDiskUsage()
            ],
            'application' => [
                'active_sessions' => $this->getActiveSessions(),
                'queued_jobs' => $this->getQueuedJobs(),
                'cache_hit_rate' => $this->getCacheHitRate()
            ]
        ];

        $this->json($metrics);
    }

    /**
     * Service dependencies health check
     * GET /health/services
     */
    public function services()
    {
        $services = [
            'timestamp' => date('c'),
            'services' => [
                'database' => $this->checkDatabaseConnection(),
                'redis' => $this->checkRedisConnection(),
                'elasticsearch' => $this->checkElasticsearchConnection(),
                'email' => $this->checkEmailService(),
                'external_apis' => $this->checkExternalAPIs()
            ]
        ];

        // Determine overall status
        $failedServices = array_filter($services['services'], function($service) {
            return $service['status'] !== 'healthy';
        });

        $services['status'] = empty($failedServices) ? 'healthy' : 'degraded';
        $services['failed_services'] = array_keys($failedServices);

        $this->json($services);
    }

    /**
     * Readiness probe for Kubernetes
     * GET /health/ready
     */
    public function ready()
    {
        $ready = [
            'status' => 'ready',
            'timestamp' => date('c'),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'migrations' => $this->checkMigrations()
            ]
        ];

        // Check if all critical services are ready
        $notReady = array_filter($ready['checks'], function($check) {
            return $check['status'] !== 'healthy';
        });

        if (!empty($notReady)) {
            http_response_code(503);
            $ready['status'] = 'not ready';
            $ready['issues'] = array_keys($notReady);
        }

        $this->json($ready);
    }

    /**
     * Liveness probe for Kubernetes
     * GET /health/live
     */
    public function live()
    {
        $alive = [
            'status' => 'alive',
            'timestamp' => date('c'),
            'uptime' => $this->getSystemUptime(),
            'memory' => $this->checkMemory()
        ];

        // Check if application is still responsive
        if ($alive['memory']['status'] !== 'healthy') {
            http_response_code(503);
            $alive['status'] = 'unhealthy';
        }

        $this->json($alive);
    }

    /**
     * Error statistics endpoint
     * GET /health/errors
     */
    public function errors()
    {
        $errorStats = $this->errorReporter->getErrorStats(3600); // Last hour

        $stats = [
            'timestamp' => date('c'),
            'period' => '1 hour',
            'total_errors' => $errorStats['total'],
            'errors_by_severity' => $errorStats['by_severity'],
            'errors_by_type' => $errorStats['by_type'],
            'recent_errors' => array_map(function($error) {
                return [
                    'timestamp' => date('c', (int)$error['timestamp']),
                    'severity' => $error['severity'],
                    'type' => $error['type'] ?? 'unknown',
                    'message' => $error['message']
                ];
            }, array_slice($errorStats['recent_errors'], 0, 10))
        ];

        $this->json($stats);
    }

    /**
     * Check database health
     */
    private function checkDatabase()
    {
        try {
            $start = microtime(true);
            $this->database->query("SELECT 1");
            $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds

            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'timestamp' => date('c')
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check cache health
     */
    private function checkCache()
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';

            $this->cache->set($testKey, $testValue, 60);
            $retrieved = $this->cache->get($testKey);
            $this->cache->delete($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'timestamp' => date('c')
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Cache read/write test failed',
                    'timestamp' => date('c')
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check filesystem health
     */
    private function checkFilesystem()
    {
        $testFile = sys_get_temp_dir() . '/tpt_health_check_' . time() . '.tmp';

        try {
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            if ($content === 'test') {
                return [
                    'status' => 'healthy',
                    'timestamp' => date('c')
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'File read/write test failed',
                    'timestamp' => date('c')
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check memory health
     */
    private function checkMemory()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        $usagePercent = ($memoryUsage / $memoryLimit) * 100;

        if ($usagePercent > 90) {
            return [
                'status' => 'critical',
                'usage' => $this->formatBytes($memoryUsage),
                'limit' => $this->formatBytes($memoryLimit),
                'usage_percent' => round($usagePercent, 2),
                'timestamp' => date('c')
            ];
        } elseif ($usagePercent > 75) {
            return [
                'status' => 'warning',
                'usage' => $this->formatBytes($memoryUsage),
                'limit' => $this->formatBytes($memoryLimit),
                'usage_percent' => round($usagePercent, 2),
                'timestamp' => date('c')
            ];
        } else {
            return [
                'status' => 'healthy',
                'usage' => $this->formatBytes($memoryUsage),
                'limit' => $this->formatBytes($memoryLimit),
                'usage_percent' => round($usagePercent, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check system load
     */
    private function checkSystemLoad()
    {
        $load = sys_getloadavg();

        if ($load[0] > 10) {
            return [
                'status' => 'critical',
                'load_1min' => $load[0],
                'load_5min' => $load[1],
                'load_15min' => $load[2],
                'timestamp' => date('c')
            ];
        } elseif ($load[0] > 5) {
            return [
                'status' => 'warning',
                'load_1min' => $load[0],
                'load_5min' => $load[1],
                'load_15min' => $load[2],
                'timestamp' => date('c')
            ];
        } else {
            return [
                'status' => 'healthy',
                'load_1min' => $load[0],
                'load_5min' => $load[1],
                'load_15min' => $load[2],
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace()
    {
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsed = $diskTotal - $diskFree;
        $usagePercent = ($diskUsed / $diskTotal) * 100;

        if ($usagePercent > 95) {
            return [
                'status' => 'critical',
                'free' => $this->formatBytes($diskFree),
                'used' => $this->formatBytes($diskUsed),
                'total' => $this->formatBytes($diskTotal),
                'usage_percent' => round($usagePercent, 2),
                'timestamp' => date('c')
            ];
        } elseif ($usagePercent > 85) {
            return [
                'status' => 'warning',
                'free' => $this->formatBytes($diskFree),
                'used' => $this->formatBytes($diskUsed),
                'total' => $this->formatBytes($diskTotal),
                'usage_percent' => round($usagePercent, 2),
                'timestamp' => date('c')
            ];
        } else {
            return [
                'status' => 'healthy',
                'free' => $this->formatBytes($diskFree),
                'used' => $this->formatBytes($diskUsed),
                'total' => $this->formatBytes($diskTotal),
                'usage_percent' => round($usagePercent, 2),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get response time (placeholder - would be measured by middleware)
     */
    private function getResponseTime()
    {
        return isset($_SERVER['REQUEST_TIME_FLOAT']) ?
            (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000 : 0;
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        return [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true))
        ];
    }

    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage()
    {
        // This is a simplified implementation
        // In production, you might use system calls or monitoring tools
        return 'N/A';
    }

    /**
     * Get active connections (placeholder)
     */
    private function getActiveConnections()
    {
        // This would typically come from your web server or load balancer
        return isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? 1 : 0;
    }

    /**
     * Get system uptime
     */
    private function getSystemUptime()
    {
        if (function_exists('posix_times')) {
            $times = posix_times();
            return $times['uptime'] ?? 0;
        }

        return time() - (file_exists('/proc/uptime') ?
            explode(' ', file_get_contents('/proc/uptime'))[0] : 0);
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $used = $total - $free;

        return [
            'free' => $this->formatBytes($free),
            'used' => $this->formatBytes($used),
            'total' => $this->formatBytes($total),
            'usage_percent' => round(($used / $total) * 100, 2)
        ];
    }

    /**
     * Get active sessions (placeholder)
     */
    private function getActiveSessions()
    {
        // This would typically query your session storage
        return session_status() === PHP_SESSION_ACTIVE ? 1 : 0;
    }

    /**
     * Get queued jobs (placeholder)
     */
    private function getQueuedJobs()
    {
        // This would query your job queue
        return 0;
    }

    /**
     * Get cache hit rate (placeholder)
     */
    private function getCacheHitRate()
    {
        // This would come from your cache monitoring
        return 'N/A';
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection()
    {
        return $this->checkDatabase();
    }

    /**
     * Check Redis connection (placeholder)
     */
    private function checkRedisConnection()
    {
        // Implement Redis health check if using Redis
        return [
            'status' => 'healthy',
            'message' => 'Redis not configured',
            'timestamp' => date('c')
        ];
    }

    /**
     * Check Elasticsearch connection (placeholder)
     */
    private function checkElasticsearchConnection()
    {
        // Implement Elasticsearch health check if using ES
        return [
            'status' => 'healthy',
            'message' => 'Elasticsearch not configured',
            'timestamp' => date('c')
        ];
    }

    /**
     * Check email service (placeholder)
     */
    private function checkEmailService()
    {
        // Implement email service health check
        return [
            'status' => 'healthy',
            'message' => 'Email service check not implemented',
            'timestamp' => date('c')
        ];
    }

    /**
     * Check external APIs (placeholder)
     */
    private function checkExternalAPIs()
    {
        // Implement external API health checks
        return [
            'status' => 'healthy',
            'message' => 'External API checks not implemented',
            'timestamp' => date('c')
        ];
    }

    /**
     * Check migrations status
     */
    private function checkMigrations()
    {
        // Implement migration status check
        return [
            'status' => 'healthy',
            'message' => 'Migrations check not implemented',
            'timestamp' => date('c')
        ];
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit()
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int)$limit;
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

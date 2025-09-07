<?php
/**
 * TPT Government Platform - Admin Controller
 *
 * Handles administrative functions and system management.
 */

namespace Core;

class AdminController extends Controller
{
    /**
     * Show admin dashboard
     *
     * @return void
     */
    public function index(): void
    {
        if (!$this->isAuthenticated() || !$this->hasRole('admin')) {
            $this->error('Access denied', 403);
            return;
        }

        $data = [
            'title' => 'Admin Dashboard - TPT Government Platform',
            'user' => $this->getCurrentUser(),
            'stats' => $this->getSystemStats(),
            'recent_logs' => $this->getRecentLogs(),
            'system_health' => $this->getSystemHealth()
        ];

        $this->view('admin.index', $data);
    }

    /**
     * Get system statistics
     *
     * @return array
     */
    private function getSystemStats(): array
    {
        $stats = [
            'total_users' => 0,
            'active_users' => 0,
            'total_applications' => 0,
            'pending_applications' => 0,
            'system_uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time())
        ];

        if ($this->database) {
            try {
                // Get user statistics
                $userStats = $this->database->selectOne('SELECT COUNT(*) as total FROM users');
                $stats['total_users'] = $userStats['total'] ?? 0;

                $activeUsers = $this->database->selectOne(
                    "SELECT COUNT(*) as active FROM users WHERE last_login > ?",
                    [date('Y-m-d H:i:s', strtotime('-30 days'))]
                );
                $stats['active_users'] = $activeUsers['active'] ?? 0;

                // Get application statistics (mock for now)
                $stats['total_applications'] = 150;
                $stats['pending_applications'] = 23;

            } catch (\Exception $e) {
                error_log('Admin stats error: ' . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Get recent system logs
     *
     * @return array
     */
    private function getRecentLogs(): array
    {
        if (!$this->database) {
            return [
                [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level' => 'info',
                    'message' => 'System started successfully',
                    'user' => 'system'
                ]
            ];
        }

        try {
            return $this->database->select(
                'SELECT timestamp, action, data, user_id, ip_address
                 FROM audit_logs
                 ORDER BY timestamp DESC
                 LIMIT 20'
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get system health status
     *
     * @return array
     */
    private function getSystemHealth(): array
    {
        $health = [
            'database' => $this->database ? 'healthy' : 'disconnected',
            'disk_space' => $this->getDiskSpace(),
            'memory_usage' => $this->getMemoryUsage(),
            'load_average' => $this->getLoadAverage()
        ];

        return $health;
    }

    /**
     * Get disk space information
     *
     * @return array
     */
    private function getDiskSpace(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;

        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage' => round(($used / $total) * 100, 1)
        ];
    }

    /**
     * Get memory usage
     *
     * @return array
     */
    private function getMemoryUsage(): array
    {
        $memory = [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->parseMemoryLimit(ini_get('memory_limit'))
        ];

        return [
            'used' => $this->formatBytes($memory['used']),
            'peak' => $this->formatBytes($memory['peak']),
            'limit' => $this->formatBytes($memory['limit'])
        ];
    }

    /**
     * Get system load average
     *
     * @return float|null
     */
    private function getLoadAverage(): ?float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0], 2);
        }
        return null;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
     * Parse memory limit string
     *
     * @param string $limit
     * @return int
     */
    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }

    /**
     * Clear system cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        if (!$this->isAuthenticated() || !$this->hasRole('admin')) {
            $this->error('Access denied', 403);
            return;
        }

        // Clear file cache
        $cacheFiles = glob(CACHE_PATH . '/*');
        foreach ($cacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->logAction('cache_cleared');
        $this->success([], 'Cache cleared successfully');
    }

    /**
     * Get system configuration
     *
     * @return void
     */
    public function getConfig(): void
    {
        if (!$this->isAuthenticated() || !$this->hasRole('admin')) {
            $this->error('Access denied', 403);
            return;
        }

        $config = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'database_connected' => $this->database ? $this->database->isConnected() : false,
            'session_save_path' => session_save_path(),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'max_post_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];

        $this->json(['config' => $config]);
    }
}

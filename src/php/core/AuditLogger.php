<?php
/**
 * TPT Government Platform - Audit Logger
 *
 * Comprehensive audit logging system for tracking all user actions,
 * system events, and security-relevant activities
 */

namespace TPT\Core;

class AuditLogger
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var array Audit configuration
     */
    private array $config;

    /**
     * @var array Current user context
     */
    private array $userContext = [];

    /**
     * @var array Current session context
     */
    private array $sessionContext = [];

    /**
     * Audit event types
     */
    const EVENT_TYPES = [
        // Authentication events
        'auth.login' => 'User login',
        'auth.logout' => 'User logout',
        'auth.failed_login' => 'Failed login attempt',
        'auth.password_change' => 'Password change',
        'auth.password_reset' => 'Password reset request',

        // User management events
        'user.create' => 'User account created',
        'user.update' => 'User account updated',
        'user.delete' => 'User account deleted',
        'user.role_change' => 'User role changed',
        'user.status_change' => 'User status changed',

        // Data access events
        'data.access' => 'Data accessed',
        'data.modify' => 'Data modified',
        'data.delete' => 'Data deleted',
        'data.export' => 'Data exported',
        'data.import' => 'Data imported',

        // Security events
        'security.suspicious_activity' => 'Suspicious activity detected',
        'security.brute_force_attempt' => 'Brute force attempt detected',
        'security.unauthorized_access' => 'Unauthorized access attempt',
        'security.encryption_key_access' => 'Encryption key accessed',
        'security.certificate_change' => 'SSL certificate changed',

        // System events
        'system.config_change' => 'System configuration changed',
        'system.backup_created' => 'System backup created',
        'system.backup_restored' => 'System backup restored',
        'system.service_restart' => 'System service restarted',
        'system.error' => 'System error occurred',

        // API events
        'api.request' => 'API request made',
        'api.rate_limit_exceeded' => 'API rate limit exceeded',
        'api.authentication_failure' => 'API authentication failure',

        // Workflow events
        'workflow.create' => 'Workflow created',
        'workflow.update' => 'Workflow updated',
        'workflow.delete' => 'Workflow deleted',
        'workflow.execute' => 'Workflow executed',
        'workflow.error' => 'Workflow error',

        // GDPR events
        'gdpr.access_request' => 'GDPR data access request',
        'gdpr.rectification_request' => 'GDPR data rectification request',
        'gdpr.erasure_request' => 'GDPR data erasure request',
        'gdpr.consent_change' => 'GDPR consent changed',

        // File system events
        'file.upload' => 'File uploaded',
        'file.download' => 'File downloaded',
        'file.delete' => 'File deleted',
        'file.access' => 'File accessed'
    ];

    /**
     * Risk levels
     */
    const RISK_LEVELS = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4
    ];

    /**
     * Constructor
     *
     * @param Database $database
     * @param array $config
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge([
            'retention_days' => 2555, // 7 years (default)
            'max_retention_days' => 10000, // 27+ years (configurable maximum)
            'min_retention_days' => 365, // 1 year (minimum)
            'max_log_size' => 1000000, // 1M records
            'enable_real_time_alerts' => true,
            'alert_thresholds' => [
                'failed_logins_per_hour' => 5,
                'suspicious_activities_per_hour' => 3,
                'unauthorized_access_per_hour' => 2
            ],
            'log_levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'sensitive_fields' => ['password', 'ssn', 'credit_card', 'api_key', 'secret']
        ], $config);

        // Load retention settings from database if available
        $this->loadRetentionSettings();
    }

    /**
     * Set user context for audit logging
     *
     * @param array $userContext
     * @return void
     */
    public function setUserContext(array $userContext): void
    {
        $this->userContext = $userContext;
    }

    /**
     * Set session context for audit logging
     *
     * @param array $sessionContext
     * @return void
     */
    public function setSessionContext(array $sessionContext): void
    {
        $this->sessionContext = $sessionContext;
    }

    /**
     * Log an audit event
     *
     * @param string $eventType
     * @param array $data
     * @param string $riskLevel
     * @param array $metadata
     * @return bool
     */
    public function logEvent(string $eventType, array $data = [], string $riskLevel = 'low', array $metadata = []): bool
    {
        try {
            // Validate event type
            if (!isset(self::EVENT_TYPES[$eventType])) {
                $this->logEvent('system.error', [
                    'error' => 'Invalid audit event type',
                    'event_type' => $eventType
                ], 'medium');
                return false;
            }

            // Validate risk level
            if (!isset(self::RISK_LEVELS[$riskLevel])) {
                $riskLevel = 'low';
            }

            // Sanitize sensitive data
            $data = $this->sanitizeData($data);

            // Prepare audit record
            $auditRecord = [
                'event_type' => $eventType,
                'event_description' => self::EVENT_TYPES[$eventType],
                'risk_level' => $riskLevel,
                'risk_score' => self::RISK_LEVELS[$riskLevel],
                'user_id' => $this->userContext['id'] ?? null,
                'user_email' => $this->userContext['email'] ?? null,
                'user_role' => $this->userContext['role'] ?? null,
                'session_id' => $this->sessionContext['id'] ?? null,
                'ip_address' => $this->getClientIP(),
                'user_agent' => $this->getUserAgent(),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'data' => json_encode($data),
                'metadata' => json_encode($metadata),
                'timestamp' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Insert audit record
            $stmt = $this->database->prepare("
                INSERT INTO audit_logs (
                    event_type, event_description, risk_level, risk_score,
                    user_id, user_email, user_role, session_id,
                    ip_address, user_agent, request_method, request_uri,
                    data, metadata, timestamp, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute(array_values($auditRecord));

            if ($result) {
                // Check for alerts
                $this->checkForAlerts($eventType, $auditRecord);

                // Archive old logs if needed
                $this->maintainLogRetention();
            }

            return $result;

        } catch (\Exception $e) {
            // Log the error (without recursion)
            error_log('Audit logging error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log authentication event
     *
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function logAuthEvent(string $event, array $data = []): bool
    {
        $eventType = 'auth.' . $event;
        $riskLevel = $this->determineAuthRiskLevel($event);

        return $this->logEvent($eventType, $data, $riskLevel);
    }

    /**
     * Log security event
     *
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function logSecurityEvent(string $event, array $data = []): bool
    {
        $eventType = 'security.' . $event;
        $riskLevel = $this->determineSecurityRiskLevel($event);

        return $this->logEvent($eventType, $data, $riskLevel);
    }

    /**
     * Log data access event
     *
     * @param string $operation
     * @param string $resourceType
     * @param int $resourceId
     * @param array $data
     * @return bool
     */
    public function logDataAccess(string $operation, string $resourceType, int $resourceId, array $data = []): bool
    {
        $eventType = 'data.' . $operation;
        $riskLevel = $this->determineDataRiskLevel($operation, $resourceType);

        $auditData = array_merge($data, [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'operation' => $operation
        ]);

        return $this->logEvent($eventType, $auditData, $riskLevel);
    }

    /**
     * Log GDPR compliance event
     *
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function logGDPREvent(string $event, array $data = []): bool
    {
        $eventType = 'gdpr.' . $event;
        return $this->logEvent($eventType, $data, 'medium');
    }

    /**
     * Get audit logs with filtering
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAuditLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        try {
            $whereConditions = [];
            $params = [];

            // Build WHERE conditions
            if (isset($filters['event_type'])) {
                $whereConditions[] = 'event_type = ?';
                $params[] = $filters['event_type'];
            }

            if (isset($filters['user_id'])) {
                $whereConditions[] = 'user_id = ?';
                $params[] = $filters['user_id'];
            }

            if (isset($filters['risk_level'])) {
                $whereConditions[] = 'risk_level = ?';
                $params[] = $filters['risk_level'];
            }

            if (isset($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= ?';
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= ?';
                $params[] = $filters['date_to'];
            }

            if (isset($filters['ip_address'])) {
                $whereConditions[] = 'ip_address = ?';
                $params[] = $filters['ip_address'];
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM audit_logs {$whereClause}";
            $totalResult = $this->database->selectOne($countQuery, $params);
            $total = $totalResult['total'] ?? 0;

            // Get logs
            $query = "
                SELECT * FROM audit_logs
                {$whereClause}
                ORDER BY timestamp DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $logs = $this->database->select($query, $params);

            return [
                'success' => true,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'logs' => $logs
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get audit statistics
     *
     * @param string $period
     * @return array
     */
    public function getAuditStatistics(string $period = '24 hours'): array
    {
        try {
            $dateCondition = $this->getDateCondition($period);

            $stats = [
                'total_events' => 0,
                'events_by_type' => [],
                'events_by_risk' => [],
                'top_users' => [],
                'recent_alerts' => [],
                'failed_logins' => 0,
                'suspicious_activities' => 0
            ];

            // Total events
            $totalResult = $this->database->selectOne("
                SELECT COUNT(*) as total FROM audit_logs
                WHERE timestamp >= {$dateCondition}
            ");
            $stats['total_events'] = $totalResult['total'] ?? 0;

            // Events by type
            $typeStats = $this->database->select("
                SELECT event_type, COUNT(*) as count
                FROM audit_logs
                WHERE timestamp >= {$dateCondition}
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['events_by_type'] = $typeStats;

            // Events by risk level
            $riskStats = $this->database->select("
                SELECT risk_level, COUNT(*) as count
                FROM audit_logs
                WHERE timestamp >= {$dateCondition}
                GROUP BY risk_level
                ORDER BY count DESC
            ");
            $stats['events_by_risk'] = $riskStats;

            // Top users by activity
            $userStats = $this->database->select("
                SELECT user_email, COUNT(*) as count
                FROM audit_logs
                WHERE timestamp >= {$dateCondition} AND user_email IS NOT NULL
                GROUP BY user_email
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['top_users'] = $userStats;

            // Failed logins
            $failedLoginResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM audit_logs
                WHERE event_type = 'auth.failed_login' AND timestamp >= {$dateCondition}
            ");
            $stats['failed_logins'] = $failedLoginResult['count'] ?? 0;

            // Suspicious activities
            $suspiciousResult = $this->database->selectOne("
                SELECT COUNT(*) as count FROM audit_logs
                WHERE event_type LIKE 'security.%' AND timestamp >= {$dateCondition}
            ");
            $stats['suspicious_activities'] = $suspiciousResult['count'] ?? 0;

            return [
                'success' => true,
                'period' => $period,
                'statistics' => $stats
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check for security alerts
     *
     * @param string $eventType
     * @param array $auditRecord
     * @return void
     */
    private function checkForAlerts(string $eventType, array $auditRecord): void
    {
        if (!$this->config['enable_real_time_alerts']) {
            return;
        }

        $alerts = [];

        // Check failed login threshold
        if ($eventType === 'auth.failed_login') {
            $failedLogins = $this->database->selectOne("
                SELECT COUNT(*) as count FROM audit_logs
                WHERE event_type = 'auth.failed_login'
                AND ip_address = ?
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ", [$auditRecord['ip_address']]);

            if (($failedLogins['count'] ?? 0) >= $this->config['alert_thresholds']['failed_logins_per_hour']) {
                $alerts[] = [
                    'type' => 'brute_force_attempt',
                    'severity' => 'high',
                    'message' => "Multiple failed login attempts from IP: {$auditRecord['ip_address']}",
                    'data' => $auditRecord
                ];
            }
        }

        // Check suspicious activity threshold
        if (str_starts_with($eventType, 'security.')) {
            $suspiciousActivities = $this->database->selectOne("
                SELECT COUNT(*) as count FROM audit_logs
                WHERE event_type LIKE 'security.%'
                AND ip_address = ?
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ", [$auditRecord['ip_address']]);

            if (($suspiciousActivities['count'] ?? 0) >= $this->config['alert_thresholds']['suspicious_activities_per_hour']) {
                $alerts[] = [
                    'type' => 'suspicious_activity_burst',
                    'severity' => 'high',
                    'message' => "High volume of suspicious activities from IP: {$auditRecord['ip_address']}",
                    'data' => $auditRecord
                ];
            }
        }

        // Send alerts
        foreach ($alerts as $alert) {
            $this->sendAlert($alert);
        }
    }

    /**
     * Send security alert
     *
     * @param array $alert
     * @return void
     */
    private function sendAlert(array $alert): void
    {
        // Log the alert
        $this->logEvent('security.alert_generated', $alert, $alert['severity']);

        // In a real implementation, this would:
        // - Send email notifications
        // - Trigger SMS alerts
        // - Create incident tickets
        // - Send webhook notifications

        error_log("SECURITY ALERT [{$alert['severity']}]: {$alert['message']}");
    }

    /**
     * Maintain log retention
     *
     * @return void
     */
    private function maintainLogRetention(): void
    {
        try {
            // Archive old logs (simplified - in production, you'd move to archive tables)
            $this->database->execute("
                DELETE FROM audit_logs
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$this->config['retention_days']]);

            // Check log size and archive if needed
            $logCount = $this->database->selectOne("SELECT COUNT(*) as count FROM audit_logs");
            if (($logCount['count'] ?? 0) > $this->config['max_log_size']) {
                // Archive oldest 20% of logs
                $archiveCount = intval($this->config['max_log_size'] * 0.2);
                $this->database->execute("
                    DELETE FROM audit_logs
                    ORDER BY timestamp ASC
                    LIMIT ?
                ", [$archiveCount]);
            }

        } catch (\Exception $e) {
            error_log('Log retention maintenance error: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize sensitive data from audit logs
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = $data;

        foreach ($this->config['sensitive_fields'] as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Determine risk level for authentication events
     *
     * @param string $event
     * @return string
     */
    private function determineAuthRiskLevel(string $event): string
    {
        $riskLevels = [
            'failed_login' => 'medium',
            'password_reset' => 'low',
            'password_change' => 'low'
        ];

        return $riskLevels[$event] ?? 'low';
    }

    /**
     * Determine risk level for security events
     *
     * @param string $event
     * @return string
     */
    private function determineSecurityRiskLevel(string $event): string
    {
        $riskLevels = [
            'suspicious_activity' => 'high',
            'brute_force_attempt' => 'critical',
            'unauthorized_access' => 'critical',
            'encryption_key_access' => 'high'
        ];

        return $riskLevels[$event] ?? 'medium';
    }

    /**
     * Determine risk level for data operations
     *
     * @param string $operation
     * @param string $resourceType
     * @return string
     */
    private function determineDataRiskLevel(string $operation, string $resourceType): string
    {
        $highRiskOperations = ['delete', 'export'];
        $sensitiveResources = ['users', 'financial', 'medical', 'personal'];

        if (in_array($operation, $highRiskOperations) || in_array($resourceType, $sensitiveResources)) {
            return 'high';
        }

        return 'low';
    }

    /**
     * Get date condition for queries
     *
     * @param string $period
     * @return string
     */
    private function getDateCondition(string $period): string
    {
        $conditions = [
            '1 hour' => 'DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            '24 hours' => 'DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            '7 days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30 days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90 days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)'
        ];

        return $conditions[$period] ?? $conditions['24 hours'];
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function getClientIP(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent string
     *
     * @return string|null
     */
    private function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * Export audit logs to file
     *
     * @param array $filters
     * @param string $format
     * @return array
     */
    public function exportAuditLogs(array $filters = [], string $format = 'json'): array
    {
        try {
            $logsResult = $this->getAuditLogs($filters, 10000, 0);

            if (!$logsResult['success']) {
                return $logsResult;
            }

            $exportData = [
                'metadata' => [
                    'export_date' => date('c'),
                    'total_records' => $logsResult['total'],
                    'filters' => $filters,
                    'format' => $format
                ],
                'logs' => $logsResult['logs']
            ];

            $filename = 'audit-logs-' . date('Y-m-d-H-i-s') . '.' . $format;
            $filepath = sys_get_temp_dir() . '/' . $filename;

            if ($format === 'json') {
                file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT));
            } elseif ($format === 'csv') {
                $csvContent = $this->convertToCSV($logsResult['logs']);
                file_put_contents($filepath, $csvContent);
            }

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'total_records' => $logsResult['total']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convert logs to CSV format
     *
     * @param array $logs
     * @return string
     */
    private function convertToCSV(array $logs): string
    {
        if (empty($logs)) {
            return '';
        }

        $headers = array_keys($logs[0]);
        $csv = implode(',', $headers) . "\n";

        foreach ($logs as $log) {
            $row = [];
            foreach ($headers as $header) {
                $value = $log[$header] ?? '';
                // Escape quotes and wrap in quotes if contains comma
                if (strpos($value, ',') !== false || strpos($value, '"') !== false) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
                $row[] = $value;
            }
            $csv .= implode(',', $row) . "\n";
        }

        return $csv;
    }

    /**
     * Load retention settings from database
     *
     * @return void
     */
    private function loadRetentionSettings(): void
    {
        try {
            // Try to load global retention settings
            $settings = $this->database->selectOne("
                SELECT setting_value FROM system_settings
                WHERE setting_key = 'audit_retention_days'
                LIMIT 1
            ");

            if ($settings && is_numeric($settings['setting_value'])) {
                $retentionDays = (int) $settings['setting_value'];

                // Validate retention period
                if ($retentionDays >= $this->config['min_retention_days'] &&
                    $retentionDays <= $this->config['max_retention_days']) {
                    $this->config['retention_days'] = $retentionDays;
                }
            }
        } catch (\Exception $e) {
            // Use default settings if database query fails
            error_log('Failed to load audit retention settings: ' . $e->getMessage());
        }
    }

    /**
     * Set retention period (administrative function)
     *
     * @param int $days
     * @param int $adminUserId
     * @return array
     */
    public function setRetentionPeriod(int $days, int $adminUserId): array
    {
        try {
            // Validate retention period
            if ($days < $this->config['min_retention_days']) {
                return [
                    'success' => false,
                    'error' => "Retention period cannot be less than {$this->config['min_retention_days']} days"
                ];
            }

            if ($days > $this->config['max_retention_days']) {
                return [
                    'success' => false,
                    'error' => "Retention period cannot exceed {$this->config['max_retention_days']} days"
                ];
            }

            // Store in database
            $stmt = $this->database->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
                VALUES ('audit_retention_days', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");

            $result = $stmt->execute([$days, $adminUserId]);

            if ($result) {
                // Update current configuration
                $this->config['retention_days'] = $days;

                // Log the change
                $this->logEvent('system.config_change', [
                    'setting' => 'audit_retention_days',
                    'old_value' => $this->config['retention_days'],
                    'new_value' => $days,
                    'changed_by' => $adminUserId
                ], 'medium');

                return [
                    'success' => true,
                    'message' => "Audit retention period set to {$days} days",
                    'retention_days' => $days
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to update retention settings'
            ];

        } catch (\Exception $e) {
            error_log('Set retention period error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Get current retention settings
     *
     * @return array
     */
    public function getRetentionSettings(): array
    {
        return [
            'current_retention_days' => $this->config['retention_days'],
            'min_retention_days' => $this->config['min_retention_days'],
            'max_retention_days' => $this->config['max_retention_days'],
            'default_retention_days' => 2555, // 7 years
            'retention_period_years' => round($this->config['retention_days'] / 365, 1),
            'next_cleanup_date' => date('Y-m-d', strtotime("-{$this->config['retention_days']} days"))
        ];
    }

    /**
     * Get retention policy information
     *
     * @return array
     */
    public function getRetentionPolicy(): array
    {
        try {
            // Get current log statistics
            $stats = $this->database->selectOne("
                SELECT
                    COUNT(*) as total_logs,
                    MIN(timestamp) as oldest_log,
                    MAX(timestamp) as newest_log,
                    SUM(CASE WHEN timestamp < DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as logs_to_delete
                FROM audit_logs
            ", [$this->config['retention_days']]);

            $policy = [
                'retention_days' => $this->config['retention_days'],
                'retention_years' => round($this->config['retention_days'] / 365, 1),
                'total_logs' => $stats['total_logs'] ?? 0,
                'oldest_log_date' => $stats['oldest_log'] ?? null,
                'newest_log_date' => $stats['newest_log'] ?? null,
                'logs_to_delete' => $stats['logs_to_delete'] ?? 0,
                'storage_mb' => round(($stats['total_logs'] ?? 0) * 0.5 / 1024, 2), // Rough estimate
                'cleanup_schedule' => 'Daily at 02:00 AM',
                'last_cleanup' => $this->getLastCleanupDate()
            ];

            return [
                'success' => true,
                'policy' => $policy
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get last cleanup date
     *
     * @return string|null
     */
    private function getLastCleanupDate(): ?string
    {
        try {
            $result = $this->database->selectOne("
                SELECT MAX(timestamp) as last_cleanup
                FROM audit_logs
                WHERE event_type = 'system.log_cleanup'
            ");

            return $result['last_cleanup'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Manually trigger log cleanup
     *
     * @param int $adminUserId
     * @return array
     */
    public function triggerLogCleanup(int $adminUserId): array
    {
        try {
            // Get count before cleanup
            $beforeCount = $this->database->selectOne("SELECT COUNT(*) as count FROM audit_logs");
            $beforeCount = $beforeCount['count'] ?? 0;

            // Perform cleanup
            $deletedCount = $this->database->execute("
                DELETE FROM audit_logs
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$this->config['retention_days']]);

            // Get count after cleanup
            $afterCount = $this->database->selectOne("SELECT COUNT(*) as count FROM audit_logs");
            $afterCount = $afterCount['count'] ?? 0;

            // Log the cleanup
            $this->logEvent('system.log_cleanup', [
                'retention_days' => $this->config['retention_days'],
                'records_before' => $beforeCount,
                'records_after' => $afterCount,
                'records_deleted' => $deletedCount,
                'triggered_by' => $adminUserId
            ], 'low');

            return [
                'success' => true,
                'message' => "Log cleanup completed successfully",
                'records_deleted' => $deletedCount,
                'records_remaining' => $afterCount
            ];

        } catch (\Exception $e) {
            error_log('Manual log cleanup error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to perform log cleanup'
            ];
        }
    }

    /**
     * Set maximum retention period (system administrator only)
     *
     * @param int $maxDays
     * @param int $adminUserId
     * @return array
     */
    public function setMaxRetentionPeriod(int $maxDays, int $adminUserId): array
    {
        try {
            // Validate maximum (reasonable upper limit)
            $absoluteMax = 365 * 50; // 50 years absolute maximum
            if ($maxDays > $absoluteMax) {
                return [
                    'success' => false,
                    'error' => "Maximum retention period cannot exceed {$absoluteMax} days (50 years)"
                ];
            }

            if ($maxDays < $this->config['min_retention_days']) {
                return [
                    'success' => false,
                    'error' => "Maximum retention period cannot be less than minimum ({$this->config['min_retention_days']} days)"
                ];
            }

            // Update configuration
            $this->config['max_retention_days'] = $maxDays;

            // Log the change
            $this->logEvent('system.config_change', [
                'setting' => 'audit_max_retention_days',
                'old_value' => $this->config['max_retention_days'],
                'new_value' => $maxDays,
                'changed_by' => $adminUserId
            ], 'high');

            return [
                'success' => true,
                'message' => "Maximum audit retention period set to {$maxDays} days",
                'max_retention_days' => $maxDays
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get retention period options for UI
     *
     * @return array
     */
    public function getRetentionOptions(): array
    {
        return [
            'presets' => [
                ['days' => 365, 'label' => '1 Year', 'description' => 'Basic compliance'],
                ['days' => 1095, 'label' => '3 Years', 'description' => 'Extended compliance'],
                ['days' => 1825, 'label' => '5 Years', 'description' => 'Government standard'],
                ['days' => 2555, 'label' => '7 Years', 'description' => 'GDPR compliance'],
                ['days' => 3650, 'label' => '10 Years', 'description' => 'Long-term retention'],
                ['days' => 7300, 'label' => '20 Years', 'description' => 'Extended archival'],
                ['days' => 10000, 'label' => '27+ Years', 'description' => 'Maximum retention']
            ],
            'current' => $this->config['retention_days'],
            'min' => $this->config['min_retention_days'],
            'max' => $this->config['max_retention_days'],
            'custom_allowed' => true
        ];
    }
}

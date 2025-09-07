<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Audit Trail Manager
 *
 * This class provides comprehensive audit trail capabilities including:
 * - User action logging and tracking
 * - System event monitoring
 * - Data change tracking and versioning
 * - Security event logging
 * - Compliance audit trail generation
 * - Audit trail integrity and tamper detection
 * - Historical data reconstruction
 * - Audit trail retention and archival
 * - Real-time audit monitoring
 * - Audit trail analytics and reporting
 */
class AuditTrailManager
{
    private PDO $pdo;
    private array $config;
    private EncryptionManager $encryptionManager;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->encryptionManager = new EncryptionManager();
        $this->config = array_merge([
            'audit_enabled' => true,
            'real_time_auditing' => true,
            'audit_retention_period' => 2555, // days (7 years)
            'audit_compression_enabled' => true,
            'audit_encryption_enabled' => true,
            'tamper_detection_enabled' => true,
            'audit_alerts_enabled' => true,
            'high_risk_actions' => [
                'user_login', 'user_logout', 'password_change', 'role_change',
                'data_export', 'data_deletion', 'admin_action', 'security_incident'
            ],
            'audit_levels' => ['minimal', 'standard', 'detailed', 'comprehensive'],
            'default_audit_level' => 'standard',
            'audit_storage_type' => 'database', // database, file, hybrid
            'audit_file_path' => '/var/log/audit/',
            'audit_batch_size' => 1000,
            'audit_cleanup_interval' => 86400, // 24 hours
            'audit_integrity_check_interval' => 3600, // 1 hour
            'max_audit_entries_per_hour' => 10000,
            'audit_data_masking' => true,
            'sensitive_fields' => ['password', 'ssn', 'credit_card', 'api_key']
        ], $config);

        $this->createAuditTables();
        $this->initializeAuditSystem();
    }

    /**
     * Create audit trail tables
     */
    private function createAuditTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS audit_trail (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                audit_id VARCHAR(255) NOT NULL UNIQUE,
                session_id VARCHAR(255) DEFAULT NULL,
                user_id VARCHAR(255) DEFAULT NULL,
                user_name VARCHAR(255) DEFAULT NULL,
                user_role VARCHAR(100) DEFAULT NULL,
                action_type VARCHAR(100) NOT NULL,
                action_category VARCHAR(50) NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                resource_id VARCHAR(255) DEFAULT NULL,
                resource_name VARCHAR(255) DEFAULT NULL,
                action_description TEXT DEFAULT NULL,
                action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                location_data JSON DEFAULT NULL,
                device_fingerprint VARCHAR(255) DEFAULT NULL,
                old_values JSON DEFAULT NULL,
                new_values JSON DEFAULT NULL,
                change_metadata JSON DEFAULT NULL,
                risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                compliance_flags JSON DEFAULT NULL,
                audit_level VARCHAR(20) DEFAULT 'standard',
                integrity_hash VARCHAR(128) DEFAULT NULL,
                previous_audit_id VARCHAR(255) DEFAULT NULL,
                batch_id VARCHAR(255) DEFAULT NULL,
                is_compressed BOOLEAN DEFAULT false,
                compression_method VARCHAR(50) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit (audit_id),
                INDEX idx_session (session_id),
                INDEX idx_user (user_id),
                INDEX idx_action (action_type),
                INDEX idx_category (action_category),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_timestamp (action_timestamp),
                INDEX idx_risk (risk_level),
                INDEX idx_batch (batch_id),
                INDEX idx_integrity (integrity_hash)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS audit_trail_archive (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                archive_id VARCHAR(255) NOT NULL UNIQUE,
                audit_ids JSON NOT NULL,
                archive_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archive_method VARCHAR(50) DEFAULT 'database',
                archive_path VARCHAR(1000) DEFAULT NULL,
                compression_ratio DECIMAL(5,2) DEFAULT NULL,
                integrity_hash VARCHAR(128) DEFAULT NULL,
                retention_period_days INT DEFAULT 2555,
                archive_status ENUM('active', 'expired', 'deleted') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_archive (archive_id),
                INDEX idx_date (archive_date),
                INDEX idx_status (archive_status)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS audit_trail_integrity (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                integrity_id VARCHAR(255) NOT NULL UNIQUE,
                check_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_audit_id VARCHAR(255) DEFAULT NULL,
                total_audit_entries BIGINT DEFAULT 0,
                integrity_status ENUM('valid', 'compromised', 'unknown') DEFAULT 'valid',
                integrity_hash VARCHAR(128) DEFAULT NULL,
                compromised_entries JSON DEFAULT NULL,
                remediation_actions TEXT DEFAULT NULL,
                check_duration_seconds DECIMAL(5,2) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_integrity (integrity_id),
                INDEX idx_timestamp (check_timestamp),
                INDEX idx_status (integrity_status)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS audit_trail_alerts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                alert_id VARCHAR(255) NOT NULL UNIQUE,
                audit_id VARCHAR(255) DEFAULT NULL,
                alert_type ENUM('anomaly_detected', 'integrity_compromised', 'high_risk_action', 'unusual_pattern', 'threshold_exceeded') NOT NULL,
                alert_title VARCHAR(255) NOT NULL,
                alert_description TEXT NOT NULL,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                alert_data JSON DEFAULT NULL,
                is_acknowledged BOOLEAN DEFAULT false,
                acknowledged_by VARCHAR(255) DEFAULT NULL,
                acknowledged_at TIMESTAMP NULL,
                resolution_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_alert (alert_id),
                INDEX idx_audit (audit_id),
                INDEX idx_type (alert_type),
                INDEX idx_severity (severity),
                INDEX idx_acknowledged (is_acknowledged)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS audit_trail_sessions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_audit_id VARCHAR(255) NOT NULL UNIQUE,
                session_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(255) DEFAULT NULL,
                session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                session_end TIMESTAMP NULL,
                session_duration_seconds INT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                device_info JSON DEFAULT NULL,
                location_data JSON DEFAULT NULL,
                risk_score DECIMAL(5,2) DEFAULT NULL,
                suspicious_activities JSON DEFAULT NULL,
                session_status ENUM('active', 'ended', 'terminated', 'expired') DEFAULT 'active',
                termination_reason VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session_audit (session_audit_id),
                INDEX idx_session (session_id),
                INDEX idx_user (user_id),
                INDEX idx_start (session_start),
                INDEX idx_status (session_status)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS audit_trail_patterns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                pattern_id VARCHAR(255) NOT NULL UNIQUE,
                pattern_name VARCHAR(255) NOT NULL,
                pattern_description TEXT DEFAULT NULL,
                pattern_type ENUM('anomaly', 'compliance', 'security', 'operational') DEFAULT 'anomaly',
                pattern_rules JSON NOT NULL,
                risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                alert_threshold INT DEFAULT 1,
                time_window_minutes INT DEFAULT 60,
                is_active BOOLEAN DEFAULT true,
                pattern_matches INT DEFAULT 0,
                last_match TIMESTAMP NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pattern (pattern_id),
                INDEX idx_type (pattern_type),
                INDEX idx_active (is_active),
                INDEX idx_matches (pattern_matches)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS audit_trail_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(255) NOT NULL UNIQUE,
                report_type ENUM('user_activity', 'security_events', 'compliance_audit', 'data_changes', 'system_events') NOT NULL,
                report_title VARCHAR(255) NOT NULL,
                report_description TEXT DEFAULT NULL,
                date_range_start TIMESTAMP NOT NULL,
                date_range_end TIMESTAMP NOT NULL,
                filters JSON DEFAULT NULL,
                report_data JSON DEFAULT NULL,
                report_file_path VARCHAR(1000) DEFAULT NULL,
                report_format ENUM('json', 'pdf', 'csv', 'html') DEFAULT 'json',
                generated_by VARCHAR(255) DEFAULT NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                report_status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
                INDEX idx_report (report_id),
                INDEX idx_type (report_type),
                INDEX idx_generated (generated_at),
                INDEX idx_status (report_status)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create audit tables: " . $e->getMessage());
        }
    }

    /**
     * Initialize audit system
     */
    private function initializeAuditSystem(): void
    {
        // Create initial integrity baseline
        $this->createIntegrityBaseline();

        // Set up audit cleanup schedule
        $this->scheduleAuditCleanup();

        // Initialize audit patterns
        $this->initializeDefaultAuditPatterns();
    }

    /**
     * Log user action
     */
    public function logUserAction(array $actionData): array
    {
        try {
            // Validate action data
            $this->validateActionData($actionData);

            $auditId = $this->generateAuditId();

            // Mask sensitive data
            $actionData = $this->maskSensitiveData($actionData);

            // Calculate risk level
            $riskLevel = $this->calculateActionRiskLevel($actionData);

            // Generate integrity hash
            $integrityHash = $this->generateIntegrityHash($actionData);

            $stmt = $this->pdo->prepare("
                INSERT INTO audit_trail
                (audit_id, session_id, user_id, user_name, user_role, action_type,
                 action_category, resource_type, resource_id, resource_name,
                 action_description, ip_address, user_agent, location_data,
                 device_fingerprint, old_values, new_values, change_metadata,
                 risk_level, compliance_flags, audit_level, integrity_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $auditId,
                $actionData['session_id'] ?? null,
                $actionData['user_id'] ?? null,
                $actionData['user_name'] ?? null,
                $actionData['user_role'] ?? null,
                $actionData['action_type'],
                $actionData['action_category'] ?? 'user_action',
                $actionData['resource_type'] ?? 'system',
                $actionData['resource_id'] ?? null,
                $actionData['resource_name'] ?? null,
                $actionData['action_description'] ?? null,
                $actionData['ip_address'] ?? null,
                $actionData['user_agent'] ?? null,
                isset($actionData['location_data']) ? json_encode($actionData['location_data']) : null,
                $actionData['device_fingerprint'] ?? null,
                isset($actionData['old_values']) ? json_encode($actionData['old_values']) : null,
                isset($actionData['new_values']) ? json_encode($actionData['new_values']) : null,
                isset($actionData['change_metadata']) ? json_encode($actionData['change_metadata']) : null,
                $riskLevel,
                isset($actionData['compliance_flags']) ? json_encode($actionData['compliance_flags']) : null,
                $actionData['audit_level'] ?? $this->config['default_audit_level'],
                $integrityHash
            ]);

            // Check for audit patterns
            $this->checkAuditPatterns($auditId, $actionData);

            // Generate alerts for high-risk actions
            if ($riskLevel === 'high' || $riskLevel === 'critical') {
                $this->generateAuditAlert($auditId, $actionData, $riskLevel);
            }

            // Update session tracking
            if (!empty($actionData['session_id'])) {
                $this->updateSessionTracking($actionData['session_id'], $actionData);
            }

            return [
                'success' => true,
                'audit_id' => $auditId,
                'risk_level' => $riskLevel
            ];

        } catch (PDOException $e) {
            error_log("Failed to log user action: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log system event
     */
    public function logSystemEvent(array $eventData): array
    {
        try {
            $eventData['action_category'] = 'system_event';
            $eventData['resource_type'] = 'system';

            return $this->logUserAction($eventData);

        } catch (Exception $e) {
            error_log("Failed to log system event: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log data change
     */
    public function logDataChange(array $changeData): array
    {
        try {
            $changeData['action_category'] = 'data_change';
            $changeData['action_type'] = 'data_modified';

            // Detect what changed
            $changeData['change_metadata'] = $this->detectDataChanges(
                $changeData['old_values'] ?? [],
                $changeData['new_values'] ?? []
            );

            return $this->logUserAction($changeData);

        } catch (Exception $e) {
            error_log("Failed to log data change: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate audit trail report
     */
    public function generateAuditReport(array $reportCriteria): array
    {
        try {
            $reportId = $this->generateReportId();

            $query = "
                SELECT
                    audit_id, session_id, user_id, user_name, action_type,
                    action_category, resource_type, resource_id, action_description,
                    action_timestamp, ip_address, risk_level, compliance_flags
                FROM audit_trail
                WHERE 1=1
            ";

            $params = [];
            $conditions = [];

            // Date range filter
            if (!empty($reportCriteria['date_from'])) {
                $conditions[] = "action_timestamp >= ?";
                $params[] = $reportCriteria['date_from'];
            }

            if (!empty($reportCriteria['date_to'])) {
                $conditions[] = "action_timestamp <= ?";
                $params[] = $reportCriteria['date_to'];
            }

            // User filter
            if (!empty($reportCriteria['user_id'])) {
                $conditions[] = "user_id = ?";
                $params[] = $reportCriteria['user_id'];
            }

            // Action type filter
            if (!empty($reportCriteria['action_type'])) {
                $conditions[] = "action_type = ?";
                $params[] = $reportCriteria['action_type'];
            }

            // Risk level filter
            if (!empty($reportCriteria['risk_level'])) {
                $conditions[] = "risk_level = ?";
                $params[] = $reportCriteria['risk_level'];
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            $query .= " ORDER BY action_timestamp DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $auditData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Generate report summary
            $summary = $this->generateReportSummary($auditData);

            // Save report
            $this->saveAuditReport($reportId, $reportCriteria, $auditData, $summary);

            return [
                'success' => true,
                'report_id' => $reportId,
                'total_entries' => count($auditData),
                'summary' => $summary
            ];

        } catch (PDOException $e) {
            error_log("Failed to generate audit report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate report'
            ];
        }
    }

    /**
     * Check audit trail integrity
     */
    public function checkIntegrity(): array
    {
        try {
            $integrityId = $this->generateIntegrityId();
            $startTime = microtime(true);

            // Get all audit entries since last check
            $stmt = $this->pdo->prepare("
                SELECT id, audit_id, integrity_hash
                FROM audit_trail
                WHERE created_at >= (
                    SELECT COALESCE(MAX(check_timestamp), '1970-01-01 00:00:00')
                    FROM audit_trail_integrity
                    WHERE integrity_status = 'valid'
                )
                ORDER BY id ASC
            ");
            $stmt->execute();

            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalEntries = count($entries);
            $compromisedEntries = [];

            // Verify integrity chain
            $previousHash = null;
            foreach ($entries as $entry) {
                $currentHash = $this->calculateEntryHash($entry);

                if ($previousHash !== null && $entry['integrity_hash'] !== $previousHash) {
                    $compromisedEntries[] = $entry['audit_id'];
                }

                $previousHash = $currentHash;
            }

            $integrityStatus = empty($compromisedEntries) ? 'valid' : 'compromised';
            $duration = round(microtime(true) - $startTime, 2);

            // Save integrity check result
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_trail_integrity
                (integrity_id, last_audit_id, total_audit_entries, integrity_status,
                 compromised_entries, check_duration_seconds)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $integrityId,
                !empty($entries) ? end($entries)['audit_id'] : null,
                $totalEntries,
                $integrityStatus,
                !empty($compromisedEntries) ? json_encode($compromisedEntries) : null,
                $duration
            ]);

            // Generate alert if integrity is compromised
            if ($integrityStatus === 'compromised') {
                $this->generateIntegrityAlert($integrityId, $compromisedEntries);
            }

            return [
                'success' => true,
                'integrity_id' => $integrityId,
                'status' => $integrityStatus,
                'total_entries_checked' => $totalEntries,
                'compromised_entries' => count($compromisedEntries),
                'check_duration' => $duration
            ];

        } catch (PDOException $e) {
            error_log("Failed to check audit integrity: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Integrity check failed'
            ];
        }
    }

    /**
     * Archive old audit entries
     */
    public function archiveOldEntries(): array
    {
        try {
            $archiveDate = date('Y-m-d H:i:s');
            $retentionDate = date('Y-m-d H:i:s', strtotime("-{$this->config['audit_retention_period']} days"));

            // Get entries to archive
            $stmt = $this->pdo->prepare("
                SELECT audit_id FROM audit_trail
                WHERE action_timestamp < ?
                ORDER BY action_timestamp ASC
            ");
            $stmt->execute([$retentionDate]);

            $auditIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($auditIds)) {
                return [
                    'success' => true,
                    'archived_count' => 0,
                    'message' => 'No entries to archive'
                ];
            }

            $archiveId = $this->generateArchiveId();

            // Create archive record
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_trail_archive
                (archive_id, audit_ids, archive_date, retention_period_days)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $archiveId,
                json_encode($auditIds),
                $archiveDate,
                $this->config['audit_retention_period']
            ]);

            // Move entries to archive storage
            $archivedCount = $this->moveEntriesToArchive($auditIds, $archiveId);

            // Delete archived entries from main table
            $stmt = $this->pdo->prepare("
                DELETE FROM audit_trail
                WHERE audit_id IN (" . str_repeat('?,', count($auditIds) - 1) . "?)
            ");
            $stmt->execute($auditIds);

            return [
                'success' => true,
                'archive_id' => $archiveId,
                'archived_count' => $archivedCount,
                'message' => "Successfully archived {$archivedCount} audit entries"
            ];

        } catch (PDOException $e) {
            error_log("Failed to archive audit entries: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Archive operation failed'
            ];
        }
    }

    /**
     * Get audit trail analytics
     */
    public function getAuditAnalytics(array $dateRange = []): array
    {
        try {
            $dateRange = array_merge([
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d')
            ], $dateRange);

            $analytics = [
                'period' => $dateRange,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // User activity summary
            $stmt = $this->pdo->prepare("
                SELECT
                    user_id,
                    user_name,
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT DATE(action_timestamp)) as active_days,
                    MAX(action_timestamp) as last_activity
                FROM audit_trail
                WHERE action_timestamp BETWEEN ? AND ?
                AND user_id IS NOT NULL
                GROUP BY user_id, user_name
                ORDER BY total_actions DESC
                LIMIT 20
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $analytics['user_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Action type distribution
            $stmt = $this->pdo->prepare("
                SELECT
                    action_type,
                    action_category,
                    COUNT(*) as count,
                    AVG(CASE
                        WHEN risk_level = 'low' THEN 1
                        WHEN risk_level = 'medium' THEN 2
                        WHEN risk_level = 'high' THEN 3
                        WHEN risk_level = 'critical' THEN 4
                        ELSE 1
                    END) as avg_risk_score
                FROM audit_trail
                WHERE action_timestamp BETWEEN ? AND ?
                GROUP BY action_type, action_category
                ORDER BY count DESC
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $analytics['action_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Risk level trends
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(action_timestamp) as date,
                    risk_level,
                    COUNT(*) as count
                FROM audit_trail
                WHERE action_timestamp BETWEEN ? AND ?
                GROUP BY DATE(action_timestamp), risk_level
                ORDER BY date ASC
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $analytics['risk_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Geographic distribution
            $stmt = $this->pdo->prepare("
                SELECT
                    JSON_UNQUOTE(JSON_EXTRACT(location_data, '$.country')) as country,
                    JSON_UNQUOTE(JSON_EXTRACT(location_data, '$.city')) as city,
                    COUNT(*) as count
                FROM audit_trail
                WHERE action_timestamp BETWEEN ? AND ?
                AND location_data IS NOT NULL
                GROUP BY country, city
                ORDER BY count DESC
                LIMIT 20
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $analytics['geographic_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'analytics' => $analytics
            ];

        } catch (PDOException $e) {
            error_log("Failed to get audit analytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate analytics'
            ];
        }
    }

    // Private helper methods

    private function generateAuditId(): string
    {
        return 'audit_' . uniqid() . '_' . time();
    }

    private function generateReportId(): string
    {
        return 'report_' . uniqid() . '_' . time();
    }

    private function generateIntegrityId(): string
    {
        return 'integrity_' . uniqid() . '_' . time();
    }

    private function generateArchiveId(): string
    {
        return 'archive_' . uniqid() . '_' . time();
    }

    private function validateActionData(array $data): void
    {
        if (empty($data['action_type'])) {
            throw new Exception('Action type is required');
        }

        if (empty($data['resource_type'])) {
            throw new Exception('Resource type is required');
        }
    }

    private function maskSensitiveData(array $data): array
    {
        if (!$this->config['audit_data_masking']) {
            return $data;
        }

        foreach ($this->config['sensitive_fields'] as $field) {
            if (isset($data['old_values'][$field])) {
                $data['old_values'][$field] = '***MASKED***';
            }
            if (isset($data['new_values'][$field])) {
                $data['new_values'][$field] = '***MASKED***';
            }
        }

        return $data;
    }

    private function calculateActionRiskLevel(array $actionData): string
    {
        $riskScore = 0;

        // High-risk actions
        if (in_array($actionData['action_type'], $this->config['high_risk_actions'])) {
            $riskScore += 3;
        }

        // Administrative actions
        if (strpos($actionData['action_type'], 'admin') !== false) {
            $riskScore += 2;
        }

        // Data modification actions
        if (in_array($actionData['action_type'], ['create', 'update', 'delete', 'export'])) {
            $riskScore += 1;
        }

        // Unusual time patterns
        $hour = (int)date('H', strtotime($actionData['action_timestamp'] ?? 'now'));
        if ($hour < 6 || $hour > 22) {
            $riskScore += 1;
        }

        if ($riskScore >= 4) return 'critical';
        if ($riskScore >= 3) return 'high';
        if ($riskScore >= 2) return 'medium';
        return 'low';
    }

    private function generateIntegrityHash(array $data): string
    {
        $dataString = json_encode($data, JSON_UNESCAPED_UNICODE);
        return hash('sha256', $dataString . time());
    }

    private function calculateEntryHash(array $entry): string
    {
        return hash('sha256', json_encode($entry));
    }

    private function detectDataChanges(array $oldValues, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'type' => $this->getChangeType($oldValue, $newValue)
                ];
            }
        }

        return $changes;
    }

    private function getChangeType($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) return 'added';
        if ($oldValue !== null && $newValue === null) return 'removed';
        return 'modified';
    }

    private function checkAuditPatterns(string $auditId, array $actionData): void
    {
        // Implementation would check for audit patterns
        // This is a placeholder
    }

    private function generateAuditAlert(string $auditId, array $actionData, string $riskLevel): void
    {
        // Implementation would generate audit alerts
        // This is a placeholder
    }

    private function updateSessionTracking(string $sessionId, array $actionData): void
    {
        // Implementation would update session tracking
        // This is a placeholder
    }

    private function createIntegrityBaseline(): void
    {
        // Implementation would create integrity baseline
        // This is a placeholder
    }

    private function scheduleAuditCleanup(): void
    {
        // Implementation would schedule audit cleanup
        // This is a placeholder
    }

    private function initializeDefaultAuditPatterns(): void
    {
        // Implementation would initialize default audit patterns
        // This is a placeholder
    }

    private function generateReportSummary(array $auditData): array
    {
        $summary = [
            'total_entries' => count($auditData),
            'date_range' => [
                'start' => !empty($auditData) ? min(array_column($auditData, 'action_timestamp')) : null,
                'end' => !empty($auditData) ? max(array_column($auditData, 'action_timestamp')) : null
            ],
            'unique_users' => count(array_unique(array_column($auditData, 'user_id'))),
            'action_types' => array_count_values(array_column($auditData, 'action_type')),
            'risk_levels' => array_count_values(array_column($auditData, 'risk_level'))
        ];

        return $summary;
    }

    private function saveAuditReport(string $reportId, array $criteria, array $data, array $summary): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_trail_reports
            (report_id, report_type, report_title, date_range_start, date_range_end,
             filters, report_data, generated_by, report_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $reportId,
            'user_activity',
            'Audit Trail Report',
            $criteria['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
            $criteria['date_to'] ?? date('Y-m-d'),
            json_encode($criteria),
            json_encode(['summary' => $summary, 'entries' => array_slice($data, 0, 1000)]), // Limit data size
            $criteria['generated_by'] ?? null,
            'completed'
        ]);
    }

    private function generateIntegrityAlert(string $integrityId, array $compromisedEntries): void
    {
        // Implementation would generate integrity alert
        // This is a placeholder
    }

    private function moveEntriesToArchive(array $auditIds, string $archiveId): int
    {
        // Implementation would move entries to archive storage
        // This is a placeholder
        return count($auditIds);
    }
}

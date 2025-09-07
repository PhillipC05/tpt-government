<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Data Retention Manager
 *
 * This class provides comprehensive data retention and lifecycle management including:
 * - Automated data retention policy enforcement
 * - Data classification and categorization
 * - Retention period management
 * - Data disposal and deletion scheduling
 * - Legal hold and preservation management
 * - Data archiving and backup integration
 * - Retention policy compliance monitoring
 * - Data lifecycle reporting and analytics
 * - Cross-border data transfer considerations
 * - Data minimization enforcement
 * - Retention policy versioning and auditing
 */
class DataRetentionManager
{
    private PDO $pdo;
    private array $config;
    private AuditTrailManager $auditTrailManager;
    private NotificationManager $notificationManager;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->auditTrailManager = new AuditTrailManager($pdo);
        $this->notificationManager = new NotificationManager($pdo);
        $this->config = array_merge([
            'auto_disposal_enabled' => true,
            'retention_compliance_check' => true,
            'legal_hold_preservation' => true,
            'data_archiving_enabled' => true,
            'disposal_notification_days' => [30, 7, 1],
            'default_retention_periods' => [
                'user_data' => 2555, // 7 years
                'transaction_data' => 2555,
                'audit_logs' => 2555,
                'communication_data' => 1825, // 5 years
                'temporary_data' => 90, // 90 days
                'session_data' => 30 // 30 days
            ],
            'data_categories' => [
                'personal_data', 'financial_data', 'health_data', 'communication_data',
                'transaction_data', 'audit_data', 'system_data', 'temporary_data'
            ],
            'disposal_methods' => [
                'delete', 'archive', 'anonymize', 'encrypt', 'shred'
            ],
            'jurisdictional_requirements' => [
                'GDPR' => ['personal_data' => 2555],
                'CCPA' => ['personal_data' => 2555],
                'HIPAA' => ['health_data' => 2555],
                'SOX' => ['financial_data' => 2555]
            ],
            'retention_policy_versioning' => true,
            'data_lineage_tracking' => true,
            'bulk_operations_enabled' => true,
            'max_disposal_batch_size' => 1000,
            'disposal_grace_period_days' => 30
        ], $config);

        $this->createRetentionTables();
    }

    /**
     * Create data retention management tables
     */
    private function createRetentionTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS retention_policies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                policy_id VARCHAR(255) NOT NULL UNIQUE,
                policy_name VARCHAR(255) NOT NULL,
                policy_description TEXT DEFAULT NULL,
                data_category VARCHAR(100) NOT NULL,
                jurisdiction VARCHAR(10) DEFAULT NULL,
                retention_period_days INT NOT NULL,
                disposal_method VARCHAR(50) DEFAULT 'delete',
                legal_basis TEXT DEFAULT NULL,
                business_justification TEXT DEFAULT NULL,
                responsible_party VARCHAR(255) DEFAULT NULL,
                contact_email VARCHAR(255) DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                is_default BOOLEAN DEFAULT false,
                version VARCHAR(20) DEFAULT '1.0.0',
                effective_date DATE NOT NULL,
                expiration_date DATE DEFAULT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_policy (policy_id),
                INDEX idx_category (data_category),
                INDEX idx_jurisdiction (jurisdiction),
                INDEX idx_active (is_active),
                INDEX idx_default (is_default)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_retention_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                record_id VARCHAR(255) NOT NULL UNIQUE,
                data_id VARCHAR(255) NOT NULL,
                data_type VARCHAR(100) NOT NULL,
                data_category VARCHAR(100) NOT NULL,
                retention_policy_id VARCHAR(255) DEFAULT NULL,
                creation_date TIMESTAMP NOT NULL,
                retention_start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                retention_end_date TIMESTAMP NULL,
                disposal_date TIMESTAMP NULL,
                disposal_method VARCHAR(50) DEFAULT NULL,
                disposal_status ENUM('active', 'scheduled', 'disposed', 'preserved', 'exempted') DEFAULT 'active',
                legal_hold BOOLEAN DEFAULT false,
                legal_hold_reason TEXT DEFAULT NULL,
                legal_hold_expiry TIMESTAMP NULL,
                data_size_bytes BIGINT DEFAULT NULL,
                data_location VARCHAR(500) DEFAULT NULL,
                encryption_status BOOLEAN DEFAULT false,
                backup_status BOOLEAN DEFAULT false,
                compliance_status ENUM('compliant', 'non_compliant', 'pending_review') DEFAULT 'pending_review',
                last_review_date TIMESTAMP NULL,
                review_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_record (record_id),
                INDEX idx_data (data_id),
                INDEX idx_type (data_type),
                INDEX idx_category (data_category),
                INDEX idx_policy (retention_policy_id),
                INDEX idx_status (disposal_status),
                INDEX idx_legal_hold (legal_hold),
                INDEX idx_retention_end (retention_end_date),
                INDEX idx_disposal (disposal_date)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS legal_holds (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                hold_id VARCHAR(255) NOT NULL UNIQUE,
                hold_title VARCHAR(255) NOT NULL,
                hold_description TEXT NOT NULL,
                hold_reason VARCHAR(100) NOT NULL,
                case_number VARCHAR(255) DEFAULT NULL,
                court_name VARCHAR(255) DEFAULT NULL,
                requesting_party VARCHAR(255) NOT NULL,
                contact_email VARCHAR(255) DEFAULT NULL,
                contact_phone VARCHAR(20) DEFAULT NULL,
                hold_start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                hold_end_date TIMESTAMP NULL,
                hold_status ENUM('active', 'expired', 'released', 'cancelled') DEFAULT 'active',
                affected_data_categories JSON DEFAULT NULL,
                affected_data_types JSON DEFAULT NULL,
                data_scope TEXT DEFAULT NULL,
                preservation_requirements TEXT DEFAULT NULL,
                release_conditions TEXT DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_hold (hold_id),
                INDEX idx_status (hold_status),
                INDEX idx_start (hold_start_date),
                INDEX idx_end (hold_end_date),
                INDEX idx_requesting_party (requesting_party)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_disposal_schedules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                schedule_id VARCHAR(255) NOT NULL UNIQUE,
                data_category VARCHAR(100) NOT NULL,
                disposal_method VARCHAR(50) NOT NULL,
                scheduled_date TIMESTAMP NOT NULL,
                estimated_records INT DEFAULT NULL,
                estimated_size_gb DECIMAL(10,2) DEFAULT NULL,
                priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                status ENUM('pending', 'in_progress', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                execution_start TIMESTAMP NULL,
                execution_end TIMESTAMP NULL,
                records_processed INT DEFAULT 0,
                records_failed INT DEFAULT 0,
                error_message TEXT DEFAULT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_schedule (schedule_id),
                INDEX idx_category (data_category),
                INDEX idx_date (scheduled_date),
                INDEX idx_status (status),
                INDEX idx_priority (priority)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS retention_policy_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version_id VARCHAR(255) NOT NULL UNIQUE,
                policy_id VARCHAR(255) NOT NULL,
                version_number VARCHAR(20) NOT NULL,
                version_changes TEXT DEFAULT NULL,
                effective_date DATE NOT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_version (version_id),
                INDEX idx_policy (policy_id),
                INDEX idx_version_num (version_number),
                INDEX idx_effective (effective_date)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_lineage (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                lineage_id VARCHAR(255) NOT NULL UNIQUE,
                data_id VARCHAR(255) NOT NULL,
                parent_data_id VARCHAR(255) DEFAULT NULL,
                data_source VARCHAR(255) DEFAULT NULL,
                transformation_type VARCHAR(100) DEFAULT NULL,
                transformation_details JSON DEFAULT NULL,
                retention_inherited BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lineage (lineage_id),
                INDEX idx_data (data_id),
                INDEX idx_parent (parent_data_id),
                INDEX idx_source (data_source)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS retention_compliance_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(255) NOT NULL UNIQUE,
                report_period_start DATE NOT NULL,
                report_period_end DATE NOT NULL,
                total_records_reviewed INT DEFAULT 0,
                compliant_records INT DEFAULT 0,
                non_compliant_records INT DEFAULT 0,
                records_under_legal_hold INT DEFAULT 0,
                disposal_actions_taken INT DEFAULT 0,
                policy_violations JSON DEFAULT NULL,
                recommendations TEXT DEFAULT NULL,
                generated_by VARCHAR(255) DEFAULT NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_report (report_id),
                INDEX idx_period (report_period_start, report_period_end),
                INDEX idx_generated (generated_at)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create retention tables: " . $e->getMessage());
        }
    }

    /**
     * Create retention policy
     */
    public function createRetentionPolicy(array $policyData): array
    {
        try {
            // Validate policy data
            $this->validatePolicyData($policyData);

            $policyId = $this->generatePolicyId();

            $stmt = $this->pdo->prepare("
                INSERT INTO retention_policies
                (policy_id, policy_name, policy_description, data_category,
                 jurisdiction, retention_period_days, disposal_method,
                 legal_basis, business_justification, responsible_party,
                 contact_email, effective_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $policyId,
                $policyData['policy_name'],
                $policyData['policy_description'] ?? null,
                $policyData['data_category'],
                $policyData['jurisdiction'] ?? null,
                $policyData['retention_period_days'],
                $policyData['disposal_method'] ?? 'delete',
                $policyData['legal_basis'] ?? null,
                $policyData['business_justification'] ?? null,
                $policyData['responsible_party'] ?? null,
                $policyData['contact_email'] ?? null,
                $policyData['effective_date'],
                $policyData['created_by'] ?? null
            ]);

            // Create policy version record
            $this->createPolicyVersion($policyId, '1.0.0', $policyData['created_by'] ?? null);

            // Log policy creation
            $this->auditTrailManager->logUserAction([
                'action_type' => 'retention_policy_created',
                'resource_type' => 'retention_policy',
                'resource_id' => $policyId,
                'action_description' => "Created retention policy: {$policyData['policy_name']}",
                'user_id' => $policyData['created_by'] ?? null,
                'new_values' => $policyData
            ]);

            return [
                'success' => true,
                'policy_id' => $policyId,
                'message' => 'Retention policy created successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to create retention policy: " . $e->getMessage());
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
     * Register data for retention tracking
     */
    public function registerDataForRetention(array $dataInfo): array
    {
        try {
            // Validate data info
            $this->validateDataInfo($dataInfo);

            $recordId = $this->generateRecordId();

            // Determine retention policy
            $policyId = $this->determineRetentionPolicy($dataInfo);

            // Calculate retention end date
            $retentionEndDate = $this->calculateRetentionEndDate(
                $dataInfo['creation_date'] ?? 'now',
                $policyId
            );

            $stmt = $this->pdo->prepare("
                INSERT INTO data_retention_records
                (record_id, data_id, data_type, data_category, retention_policy_id,
                 creation_date, retention_end_date, data_size_bytes, data_location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $recordId,
                $dataInfo['data_id'],
                $dataInfo['data_type'],
                $dataInfo['data_category'],
                $policyId,
                $dataInfo['creation_date'] ?? date('Y-m-d H:i:s'),
                $retentionEndDate,
                $dataInfo['data_size_bytes'] ?? null,
                $dataInfo['data_location'] ?? null
            ]);

            // Create data lineage if applicable
            if (!empty($dataInfo['parent_data_id'])) {
                $this->createDataLineage($recordId, $dataInfo);
            }

            return [
                'success' => true,
                'record_id' => $recordId,
                'retention_end_date' => $retentionEndDate,
                'message' => 'Data registered for retention tracking'
            ];

        } catch (PDOException $e) {
            error_log("Failed to register data for retention: " . $e->getMessage());
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
     * Apply legal hold
     */
    public function applyLegalHold(array $holdData): array
    {
        try {
            // Validate hold data
            $this->validateHoldData($holdData);

            $holdId = $this->generateHoldId();

            $stmt = $this->pdo->prepare("
                INSERT INTO legal_holds
                (hold_id, hold_title, hold_description, hold_reason,
                 case_number, court_name, requesting_party, contact_email,
                 contact_phone, hold_end_date, affected_data_categories,
                 affected_data_types, data_scope, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $holdId,
                $holdData['hold_title'],
                $holdData['hold_description'],
                $holdData['hold_reason'],
                $holdData['case_number'] ?? null,
                $holdData['court_name'] ?? null,
                $holdData['requesting_party'],
                $holdData['contact_email'] ?? null,
                $holdData['contact_phone'] ?? null,
                $holdData['hold_end_date'] ?? null,
                isset($holdData['affected_data_categories']) ? json_encode($holdData['affected_data_categories']) : null,
                isset($holdData['affected_data_types']) ? json_encode($holdData['affected_data_types']) : null,
                $holdData['data_scope'] ?? null,
                $holdData['created_by']
            ]);

            // Update affected retention records
            $this->updateRecordsUnderHold($holdId, $holdData);

            // Log legal hold creation
            $this->auditTrailManager->logUserAction([
                'action_type' => 'legal_hold_applied',
                'resource_type' => 'legal_hold',
                'resource_id' => $holdId,
                'action_description' => "Applied legal hold: {$holdData['hold_title']}",
                'user_id' => $holdData['created_by'],
                'new_values' => $holdData
            ]);

            return [
                'success' => true,
                'hold_id' => $holdId,
                'message' => 'Legal hold applied successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to apply legal hold: " . $e->getMessage());
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
     * Process data disposal
     */
    public function processDataDisposal(array $disposalCriteria): array
    {
        try {
            // Find data eligible for disposal
            $eligibleRecords = $this->findEligibleForDisposal($disposalCriteria);

            if (empty($eligibleRecords)) {
                return [
                    'success' => true,
                    'records_processed' => 0,
                    'message' => 'No data eligible for disposal'
                ];
            }

            $scheduleId = $this->generateScheduleId();

            // Create disposal schedule
            $stmt = $this->pdo->prepare("
                INSERT INTO data_disposal_schedules
                (schedule_id, data_category, disposal_method, scheduled_date,
                 estimated_records, priority, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $scheduleId,
                $disposalCriteria['data_category'] ?? 'mixed',
                $disposalCriteria['disposal_method'] ?? 'delete',
                $disposalCriteria['scheduled_date'] ?? date('Y-m-d H:i:s'),
                count($eligibleRecords),
                $disposalCriteria['priority'] ?? 'medium',
                $disposalCriteria['created_by'] ?? null
            ]);

            // Process disposal in batches
            $results = $this->processDisposalBatch($eligibleRecords, $scheduleId, $disposalCriteria);

            // Update schedule status
            $this->updateDisposalScheduleStatus($scheduleId, $results);

            // Send notifications
            $this->sendDisposalNotifications($results);

            return [
                'success' => true,
                'schedule_id' => $scheduleId,
                'records_processed' => $results['processed'],
                'records_failed' => $results['failed'],
                'message' => "Data disposal completed: {$results['processed']} records processed"
            ];

        } catch (PDOException $e) {
            error_log("Failed to process data disposal: " . $e->getMessage());
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
     * Generate retention compliance report
     */
    public function generateRetentionComplianceReport(array $dateRange = []): array
    {
        try {
            $dateRange = array_merge([
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d')
            ], $dateRange);

            $reportId = $this->generateReportId();

            // Gather compliance statistics
            $stats = $this->gatherComplianceStatistics($dateRange);

            $stmt = $this->pdo->prepare("
                INSERT INTO retention_compliance_reports
                (report_id, report_period_start, report_period_end,
                 total_records_reviewed, compliant_records, non_compliant_records,
                 records_under_legal_hold, disposal_actions_taken,
                 policy_violations, recommendations, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $reportId,
                $dateRange['start_date'],
                $dateRange['end_date'],
                $stats['total_records_reviewed'],
                $stats['compliant_records'],
                $stats['non_compliant_records'],
                $stats['records_under_legal_hold'],
                $stats['disposal_actions_taken'],
                json_encode($stats['policy_violations']),
                $stats['recommendations'] ?? null,
                $stats['generated_by'] ?? null
            ]);

            return [
                'success' => true,
                'report_id' => $reportId,
                'statistics' => $stats,
                'message' => 'Retention compliance report generated successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to generate retention compliance report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate report'
            ];
        }
    }

    /**
     * Check data retention compliance
     */
    public function checkRetentionCompliance(): array
    {
        try {
            $complianceIssues = [];

            // Check for overdue data
            $overdueData = $this->findOverdueData();
            if (!empty($overdueData)) {
                $complianceIssues[] = [
                    'type' => 'overdue_disposal',
                    'severity' => 'high',
                    'description' => "Found {$overdueData['count']} records past retention period",
                    'affected_records' => $overdueData['records']
                ];
            }

            // Check for data without retention policies
            $unassignedData = $this->findDataWithoutPolicies();
            if (!empty($unassignedData)) {
                $complianceIssues[] = [
                    'type' => 'missing_policy',
                    'severity' => 'medium',
                    'description' => "Found {$unassignedData['count']} records without retention policies",
                    'affected_records' => $unassignedData['records']
                ];
            }

            // Check legal hold compliance
            $holdIssues = $this->checkLegalHoldCompliance();
            if (!empty($holdIssues)) {
                $complianceIssues = array_merge($complianceIssues, $holdIssues);
            }

            // Generate alerts for issues
            foreach ($complianceIssues as $issue) {
                if ($issue['severity'] === 'high' || $issue['severity'] === 'critical') {
                    $this->auditTrailManager->logSystemEvent([
                        'action_type' => 'retention_compliance_issue',
                        'resource_type' => 'compliance',
                        'action_description' => $issue['description'],
                        'new_values' => $issue
                    ]);
                }
            }

            return [
                'success' => true,
                'compliance_status' => empty($complianceIssues) ? 'compliant' : 'issues_found',
                'issues_found' => count($complianceIssues),
                'issues' => $complianceIssues,
                'message' => empty($complianceIssues) ?
                    'All data retention policies are compliant' :
                    "Found {$complianceIssues} compliance issues"
            ];

        } catch (Exception $e) {
            error_log("Failed to check retention compliance: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Compliance check failed: ' . $e->getMessage()
            ];
        }
    }

    // Private helper methods

    private function generatePolicyId(): string
    {
        return 'policy_' . uniqid() . '_' . time();
    }

    private function generateRecordId(): string
    {
        return 'record_' . uniqid() . '_' . time();
    }

    private function generateHoldId(): string
    {
        return 'hold_' . uniqid() . '_' . time();
    }

    private function generateScheduleId(): string
    {
        return 'schedule_' . uniqid() . '_' . time();
    }

    private function generateReportId(): string
    {
        return 'retention_report_' . uniqid() . '_' . time();
    }

    private function validatePolicyData(array $data): void
    {
        $required = ['policy_name', 'data_category', 'retention_period_days', 'effective_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if (!in_array($data['data_category'], $this->config['data_categories'])) {
            throw new Exception("Invalid data category: {$data['data_category']}");
        }

        if (!in_array($data['disposal_method'], $this->config['disposal_methods'])) {
            throw new Exception("Invalid disposal method: {$data['disposal_method']}");
        }
    }

    private function validateDataInfo(array $data): void
    {
        $required = ['data_id', 'data_type', 'data_category'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    private function validateHoldData(array $data): void
    {
        $required = ['hold_title', 'hold_description', 'hold_reason', 'requesting_party', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    private function determineRetentionPolicy(array $dataInfo): ?string
    {
        // Try to find specific policy first
        $stmt = $this->pdo->prepare("
            SELECT policy_id FROM retention_policies
            WHERE data_category = ? AND jurisdiction = ? AND is_active = true
            ORDER BY is_default DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$dataInfo['data_category'], $dataInfo['jurisdiction'] ?? null]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($policy) {
            return $policy['policy_id'];
        }

        // Fall back to default policy for category
        $stmt = $this->pdo->prepare("
            SELECT policy_id FROM retention_policies
            WHERE data_category = ? AND is_default = true AND is_active = true
            LIMIT 1
        ");
        $stmt->execute([$dataInfo['data_category']]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        return $policy['policy_id'] ?? null;
    }

    private function calculateRetentionEndDate(string $startDate, ?string $policyId): ?string
    {
        if (!$policyId) {
            // Use default retention period
            $defaultPeriod = $this->config['default_retention_periods']['user_data'] ?? 2555;
            return date('Y-m-d H:i:s', strtotime($startDate . " +{$defaultPeriod} days"));
        }

        $stmt = $this->pdo->prepare("
            SELECT retention_period_days FROM retention_policies
            WHERE policy_id = ? AND is_active = true
        ");
        $stmt->execute([$policyId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($policy) {
            return date('Y-m-d H:i:s', strtotime($startDate . " +{$policy['retention_period_days']} days"));
        }

        return null;
    }

    private function createPolicyVersion(string $policyId, string $version, ?string $createdBy): void
    {
        $versionId = 'version_' . uniqid() . '_' . time();

        $stmt = $this->pdo->prepare("
            INSERT INTO retention_policy_versions
            (version_id, policy_id, version_number, effective_date, created_by)
            VALUES (?, ?, ?, CURDATE(), ?)
        ");
        $stmt->execute([$versionId, $policyId, $version, $createdBy]);
    }

    private function createDataLineage(string $recordId, array $dataInfo): void
    {
        $lineageId = 'lineage_' . uniqid() . '_' . time();

        $stmt = $this->pdo->prepare("
            INSERT INTO data_lineage
            (lineage_id, data_id, parent_data_id, data_source, retention_inherited)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $lineageId,
            $dataInfo['data_id'],
            $dataInfo['parent_data_id'],
            $dataInfo['data_source'] ?? null,
            true
        ]);
    }

    private function updateRecordsUnderHold(string $holdId, array $holdData): void
    {
        // Implementation would update retention records under legal hold
        // This is a placeholder
    }

    private function findEligibleForDisposal(array $criteria): array
    {
        // Implementation would find data eligible for disposal
        // This is a placeholder
        return [];
    }

    private function processDisposalBatch(array $records, string $scheduleId, array $criteria): array
    {
        // Implementation would process disposal batch
        // This is a placeholder
        return ['processed' => 0, 'failed' => 0];
    }

    private function updateDisposalScheduleStatus(string $scheduleId, array $results): void
    {
        // Implementation would update disposal schedule status
        // This is a placeholder
    }

    private function sendDisposalNotifications(array $results): void
    {
        // Implementation would send disposal notifications
        // This is a placeholder
    }

    private function gatherComplianceStatistics(array $dateRange): array
    {
        // Implementation would gather compliance statistics
        // This is a placeholder
        return [
            'total_records_reviewed' => 0,
            'compliant_records' => 0,
            'non_compliant_records' => 0,
            'records_under_legal_hold' => 0,
            'disposal_actions_taken' => 0,
            'policy_violations' => []
        ];
    }

    private function findOverdueData(): array
    {
        // Implementation would find overdue data
        // This is a placeholder
        return ['count' => 0, 'records' => []];
    }

    private function findDataWithoutPolicies(): array
    {
        // Implementation would find data without policies
        // This is a placeholder
        return ['count' => 0, 'records' => []];
    }

    private function checkLegalHoldCompliance(): array
    {
        // Implementation would check legal hold compliance
        // This is a placeholder
        return [];
    }
}

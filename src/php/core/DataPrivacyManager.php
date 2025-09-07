<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Data Privacy Manager for GDPR and Privacy Compliance
 *
 * This class provides comprehensive data privacy and consent management including:
 * - GDPR compliance and data subject rights
 * - Consent management and tracking
 * - Data processing records and audit trails
 * - Privacy impact assessments
 * - Data minimization and retention policies
 * - Privacy policy management and versioning
 * - Data breach notification system
 * - Cookie consent and tracking management
 * - Data portability and export features
 * - Right to be forgotten implementation
 */
class DataPrivacyManager
{
    private PDO $pdo;
    private array $config;
    private NotificationManager $notificationManager;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'gdpr_enabled' => true,
            'ccpa_enabled' => true,
            'data_retention_period' => 2555, // days (7 years for GDPR)
            'consent_retention_period' => 2555,
            'auto_data_deletion' => true,
            'privacy_audit_enabled' => true,
            'breach_notification_enabled' => true,
            'data_portability_enabled' => true,
            'cookie_consent_required' => true,
            'privacy_policy_versioning' => true,
            'data_processing_inventory' => true,
            'impact_assessment_required' => true,
            'international_data_transfers' => false,
            'default_jurisdiction' => 'EU',
            'breach_notification_hours' => 72,
            'dpo_contact_email' => null,
            'legal_basis_options' => [
                'consent', 'contract', 'legal_obligation', 'vital_interests',
                'public_task', 'legitimate_interests'
            ]
        ], $config);

        $this->notificationManager = new NotificationManager($pdo);
        $this->createPrivacyTables();
    }

    /**
     * Create data privacy management tables
     */
    private function createPrivacyTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS privacy_consent (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                consent_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(255) NOT NULL,
                consent_type VARCHAR(100) NOT NULL,
                consent_purpose TEXT NOT NULL,
                legal_basis VARCHAR(50) NOT NULL,
                consent_given BOOLEAN NOT NULL DEFAULT false,
                consent_withdrawn BOOLEAN DEFAULT false,
                consent_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                withdrawal_timestamp TIMESTAMP NULL,
                consent_expires TIMESTAMP NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                consent_version VARCHAR(20) DEFAULT '1.0.0',
                data_categories JSON DEFAULT NULL,
                processing_activities JSON DEFAULT NULL,
                third_party_recipients JSON DEFAULT NULL,
                retention_period_days INT DEFAULT NULL,
                INDEX idx_consent (consent_id),
                INDEX idx_user (user_id),
                INDEX idx_type (consent_type),
                INDEX idx_given (consent_given),
                INDEX idx_withdrawn (consent_withdrawn),
                INDEX idx_expires (consent_expires)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_subject_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(255) NOT NULL,
                request_type ENUM('access', 'rectification', 'erasure', 'restriction',
                                'portability', 'objection', 'withdraw_consent') NOT NULL,
                request_description TEXT DEFAULT NULL,
                request_status ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending',
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                rejected_at TIMESTAMP NULL,
                rejection_reason TEXT DEFAULT NULL,
                assigned_to VARCHAR(255) DEFAULT NULL,
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                verification_method VARCHAR(100) DEFAULT NULL,
                verification_status ENUM('pending', 'verified', 'failed') DEFAULT 'pending',
                response_data JSON DEFAULT NULL,
                INDEX idx_request (request_id),
                INDEX idx_user (user_id),
                INDEX idx_type (request_type),
                INDEX idx_status (request_status),
                INDEX idx_priority (priority),
                INDEX idx_submitted (submitted_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_processing_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                record_id VARCHAR(255) NOT NULL UNIQUE,
                processing_name VARCHAR(255) NOT NULL,
                processing_purpose TEXT NOT NULL,
                legal_basis VARCHAR(50) NOT NULL,
                data_categories JSON NOT NULL,
                data_subjects JSON NOT NULL,
                recipients JSON DEFAULT NULL,
                retention_period_days INT DEFAULT NULL,
                security_measures TEXT DEFAULT NULL,
                dpo_contact VARCHAR(255) DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                risk_assessment JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_record (record_id),
                INDEX idx_active (is_active),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS privacy_policies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                policy_id VARCHAR(255) NOT NULL UNIQUE,
                policy_version VARCHAR(20) NOT NULL,
                policy_title VARCHAR(255) NOT NULL,
                policy_content LONGTEXT NOT NULL,
                effective_date TIMESTAMP NOT NULL,
                expiration_date TIMESTAMP NULL,
                jurisdiction VARCHAR(10) DEFAULT 'EU',
                language VARCHAR(10) DEFAULT 'en',
                is_active BOOLEAN DEFAULT false,
                is_latest BOOLEAN DEFAULT false,
                change_summary TEXT DEFAULT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_policy (policy_id),
                INDEX idx_version (policy_version),
                INDEX idx_active (is_active),
                INDEX idx_latest (is_latest),
                INDEX idx_jurisdiction (jurisdiction),
                INDEX idx_language (language)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS cookie_consent (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                consent_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(255) DEFAULT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                cookie_categories JSON NOT NULL,
                consent_level ENUM('essential_only', 'functional', 'analytics', 'marketing') DEFAULT 'essential_only',
                consent_given BOOLEAN NOT NULL DEFAULT false,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                consent_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                consent_expires TIMESTAMP NULL,
                withdrawal_timestamp TIMESTAMP NULL,
                policy_version VARCHAR(20) DEFAULT NULL,
                INDEX idx_consent (consent_id),
                INDEX idx_user (user_id),
                INDEX idx_session (session_id),
                INDEX idx_level (consent_level),
                INDEX idx_given (consent_given),
                INDEX idx_expires (consent_expires)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_breach_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                breach_id VARCHAR(255) NOT NULL UNIQUE,
                breach_description TEXT NOT NULL,
                breach_date TIMESTAMP NOT NULL,
                discovery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                affected_data_subjects INT NOT NULL,
                data_categories_affected JSON NOT NULL,
                breach_risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                potential_consequences TEXT DEFAULT NULL,
                remedial_actions_taken TEXT DEFAULT NULL,
                notification_sent BOOLEAN DEFAULT false,
                notification_date TIMESTAMP NULL,
                supervisory_authority_notified BOOLEAN DEFAULT false,
                data_subjects_notified BOOLEAN DEFAULT false,
                reported_by VARCHAR(255) DEFAULT NULL,
                investigation_status ENUM('ongoing', 'completed', 'closed') DEFAULT 'ongoing',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_breach (breach_id),
                INDEX idx_date (breach_date),
                INDEX idx_risk (breach_risk_level),
                INDEX idx_status (investigation_status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS privacy_audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                audit_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(255) DEFAULT NULL,
                action_type VARCHAR(100) NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                resource_id VARCHAR(255) DEFAULT NULL,
                action_description TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                old_values JSON DEFAULT NULL,
                new_values JSON DEFAULT NULL,
                compliance_check BOOLEAN DEFAULT false,
                audit_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit (audit_id),
                INDEX idx_user (user_id),
                INDEX idx_action (action_type),
                INDEX idx_resource (resource_type),
                INDEX idx_timestamp (audit_timestamp)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_retention_policies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                policy_id VARCHAR(255) NOT NULL UNIQUE,
                policy_name VARCHAR(255) NOT NULL,
                data_category VARCHAR(100) NOT NULL,
                retention_period_days INT NOT NULL,
                retention_basis VARCHAR(100) NOT NULL,
                disposal_method VARCHAR(50) DEFAULT 'delete',
                legal_basis TEXT DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_policy (policy_id),
                INDEX idx_category (data_category),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create privacy tables: " . $e->getMessage());
        }
    }

    /**
     * Record user consent
     */
    public function recordConsent(array $consentData): array
    {
        try {
            // Validate consent data
            $this->validateConsentData($consentData);

            $consentId = $this->generateConsentId();

            $stmt = $this->pdo->prepare("
                INSERT INTO privacy_consent
                (consent_id, user_id, consent_type, consent_purpose, legal_basis,
                 consent_given, ip_address, user_agent, consent_version,
                 data_categories, processing_activities, third_party_recipients,
                 retention_period_days, consent_expires)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $expiresAt = isset($consentData['expires_days']) ?
                date('Y-m-d H:i:s', strtotime("+{$consentData['expires_days']} days")) : null;

            $stmt->execute([
                $consentId,
                $consentData['user_id'],
                $consentData['consent_type'],
                $consentData['consent_purpose'],
                $consentData['legal_basis'],
                $consentData['consent_given'],
                $consentData['ip_address'] ?? null,
                $consentData['user_agent'] ?? null,
                $consentData['consent_version'] ?? '1.0.0',
                isset($consentData['data_categories']) ? json_encode($consentData['data_categories']) : null,
                isset($consentData['processing_activities']) ? json_encode($consentData['processing_activities']) : null,
                isset($consentData['third_party_recipients']) ? json_encode($consentData['third_party_recipients']) : null,
                $consentData['retention_period_days'] ?? null,
                $expiresAt
            ]);

            // Log privacy audit
            $this->logPrivacyAudit([
                'user_id' => $consentData['user_id'],
                'action_type' => $consentData['consent_given'] ? 'consent_given' : 'consent_denied',
                'resource_type' => 'consent',
                'resource_id' => $consentId,
                'action_description' => "Consent recorded for: {$consentData['consent_purpose']}",
                'new_values' => $consentData
            ]);

            return [
                'success' => true,
                'consent_id' => $consentId,
                'message' => 'Consent recorded successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to record consent: " . $e->getMessage());
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
     * Withdraw user consent
     */
    public function withdrawConsent(string $userId, string $consentType): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE privacy_consent
                SET consent_withdrawn = true,
                    withdrawal_timestamp = NOW()
                WHERE user_id = ? AND consent_type = ? AND consent_withdrawn = false
            ");

            $stmt->execute([$userId, $consentType]);

            if ($stmt->rowCount() > 0) {
                // Log privacy audit
                $this->logPrivacyAudit([
                    'user_id' => $userId,
                    'action_type' => 'consent_withdrawn',
                    'resource_type' => 'consent',
                    'resource_id' => $consentType,
                    'action_description' => "Consent withdrawn for type: {$consentType}"
                ]);

                return [
                    'success' => true,
                    'message' => 'Consent withdrawn successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No active consent found for this type'
                ];
            }

        } catch (PDOException $e) {
            error_log("Failed to withdraw consent: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Submit data subject request (GDPR Article 15-22)
     */
    public function submitDataSubjectRequest(array $requestData): array
    {
        try {
            // Validate request data
            $this->validateDataSubjectRequest($requestData);

            $requestId = $this->generateRequestId();

            $stmt = $this->pdo->prepare("
                INSERT INTO data_subject_requests
                (request_id, user_id, request_type, request_description, priority)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $requestId,
                $requestData['user_id'],
                $requestData['request_type'],
                $requestData['request_description'] ?? null,
                $requestData['priority'] ?? 'medium'
            ]);

            // Send notification to DPO
            $this->notifyDPONewRequest($requestId, $requestData);

            // Log privacy audit
            $this->logPrivacyAudit([
                'user_id' => $requestData['user_id'],
                'action_type' => 'data_subject_request_submitted',
                'resource_type' => 'data_subject_request',
                'resource_id' => $requestId,
                'action_description' => "Data subject request submitted: {$requestData['request_type']}",
                'new_values' => $requestData
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Data subject request submitted successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to submit data subject request: " . $e->getMessage());
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
     * Process data subject request
     */
    public function processDataSubjectRequest(string $requestId, array $processingData): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE data_subject_requests
                SET request_status = ?,
                    completed_at = NOW(),
                    response_data = ?
                WHERE request_id = ?
            ");

            $stmt->execute([
                $processingData['status'],
                isset($processingData['response_data']) ? json_encode($processingData['response_data']) : null,
                $requestId
            ]);

            if ($stmt->rowCount() > 0) {
                // Log privacy audit
                $this->logPrivacyAudit([
                    'action_type' => 'data_subject_request_processed',
                    'resource_type' => 'data_subject_request',
                    'resource_id' => $requestId,
                    'action_description' => "Data subject request processed with status: {$processingData['status']}",
                    'new_values' => $processingData
                ]);

                return [
                    'success' => true,
                    'message' => 'Data subject request processed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Request not found'
                ];
            }

        } catch (PDOException $e) {
            error_log("Failed to process data subject request: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Implement right to be forgotten (GDPR Article 17)
     */
    public function implementRightToBeForgotten(string $userId, array $options = []): array
    {
        try {
            $options = array_merge([
                'anonymize_only' => false,
                'exclude_audit_logs' => false,
                'notify_third_parties' => true
            ], $options);

            // Start transaction
            $this->pdo->beginTransaction();

            try {
                $deletedRecords = 0;

                // Delete or anonymize user data based on retention policies
                $tablesToProcess = [
                    'privacy_consent',
                    'data_subject_requests',
                    'cookie_consent',
                    'privacy_audit_log'
                ];

                foreach ($tablesToProcess as $table) {
                    if ($options['anonymize_only']) {
                        // Anonymize data instead of deleting
                        $anonymized = $this->anonymizeUserData($table, $userId);
                        $deletedRecords += $anonymized;
                    } else {
                        // Delete data
                        $deleted = $this->deleteUserData($table, $userId);
                        $deletedRecords += $deleted;
                    }
                }

                // Handle third-party data deletion if requested
                if ($options['notify_third_parties']) {
                    $this->notifyThirdPartiesOfDeletion($userId);
                }

                // Log the deletion
                $this->logPrivacyAudit([
                    'user_id' => $userId,
                    'action_type' => 'right_to_be_forgotten_implemented',
                    'resource_type' => 'user_data',
                    'resource_id' => $userId,
                    'action_description' => "Right to be forgotten implemented for user {$userId}",
                    'old_values' => ['records_deleted' => $deletedRecords]
                ]);

                $this->pdo->commit();

                return [
                    'success' => true,
                    'records_processed' => $deletedRecords,
                    'message' => 'Right to be forgotten implemented successfully'
                ];

            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Failed to implement right to be forgotten: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to implement right to be forgotten: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record data breach
     */
    public function recordDataBreach(array $breachData): array
    {
        try {
            $breachId = $this->generateBreachId();

            $stmt = $this->pdo->prepare("
                INSERT INTO data_breach_notifications
                (breach_id, breach_description, breach_date, affected_data_subjects,
                 data_categories_affected, breach_risk_level, potential_consequences,
                 remedial_actions_taken, reported_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $breachId,
                $breachData['breach_description'],
                $breachData['breach_date'],
                $breachData['affected_data_subjects'],
                json_encode($breachData['data_categories_affected']),
                $breachData['breach_risk_level'] ?? 'medium',
                $breachData['potential_consequences'] ?? null,
                $breachData['remedial_actions_taken'] ?? null,
                $breachData['reported_by'] ?? null
            ]);

            // Send breach notifications if required
            if ($this->config['breach_notification_enabled']) {
                $this->sendBreachNotifications($breachId, $breachData);
            }

            // Log privacy audit
            $this->logPrivacyAudit([
                'action_type' => 'data_breach_recorded',
                'resource_type' => 'data_breach',
                'resource_id' => $breachId,
                'action_description' => "Data breach recorded: {$breachData['breach_description']}",
                'new_values' => $breachData
            ]);

            return [
                'success' => true,
                'breach_id' => $breachId,
                'message' => 'Data breach recorded successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to record data breach: " . $e->getMessage());
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
     * Record cookie consent
     */
    public function recordCookieConsent(array $consentData): array
    {
        try {
            $consentId = $this->generateCookieConsentId();

            $stmt = $this->pdo->prepare("
                INSERT INTO cookie_consent
                (consent_id, user_id, session_id, cookie_categories, consent_level,
                 consent_given, ip_address, user_agent, policy_version, consent_expires)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $expiresAt = isset($consentData['expires_days']) ?
                date('Y-m-d H:i:s', strtotime("+{$consentData['expires_days']} days")) : null;

            $stmt->execute([
                $consentId,
                $consentData['user_id'] ?? null,
                $consentData['session_id'] ?? null,
                json_encode($consentData['cookie_categories']),
                $consentData['consent_level'] ?? 'essential_only',
                $consentData['consent_given'],
                $consentData['ip_address'] ?? null,
                $consentData['user_agent'] ?? null,
                $consentData['policy_version'] ?? '1.0.0',
                $expiresAt
            ]);

            return [
                'success' => true,
                'consent_id' => $consentId,
                'message' => 'Cookie consent recorded successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to record cookie consent: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Create privacy policy version
     */
    public function createPrivacyPolicy(array $policyData): array
    {
        try {
            $policyId = $this->generatePolicyId();

            // Set previous versions as inactive
            if ($policyData['is_latest'] ?? false) {
                $this->pdo->prepare("
                    UPDATE privacy_policies
                    SET is_latest = false
                    WHERE jurisdiction = ? AND language = ?
                ")->execute([$policyData['jurisdiction'] ?? 'EU', $policyData['language'] ?? 'en']);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO privacy_policies
                (policy_id, policy_version, policy_title, policy_content,
                 effective_date, jurisdiction, language, is_active, is_latest,
                 change_summary, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $policyId,
                $policyData['policy_version'],
                $policyData['policy_title'],
                $policyData['policy_content'],
                $policyData['effective_date'],
                $policyData['jurisdiction'] ?? 'EU',
                $policyData['language'] ?? 'en',
                $policyData['is_active'] ?? false,
                $policyData['is_latest'] ?? false,
                $policyData['change_summary'] ?? null,
                $policyData['created_by'] ?? null
            ]);

            return [
                'success' => true,
                'policy_id' => $policyId,
                'message' => 'Privacy policy created successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to create privacy policy: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Get privacy compliance report
     */
    public function getPrivacyComplianceReport(array $dateRange = []): array
    {
        try {
            $dateRange = array_merge([
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d')
            ], $dateRange);

            $report = [
                'report_period' => $dateRange,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // Consent statistics
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_consents,
                    COUNT(CASE WHEN consent_given THEN 1 END) as consents_given,
                    COUNT(CASE WHEN consent_withdrawn THEN 1 END) as consents_withdrawn,
                    COUNT(DISTINCT user_id) as unique_users
                FROM privacy_consent
                WHERE consent_timestamp BETWEEN ? AND ?
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $report['consent_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Data subject requests
            $stmt = $this->pdo->prepare("
                SELECT
                    request_type,
                    COUNT(*) as count
                FROM data_subject_requests
                WHERE submitted_at BETWEEN ? AND ?
                GROUP BY request_type
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $report['data_subject_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Data breaches
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_breaches,
                    breach_risk_level,
                    COUNT(*) as count
                FROM data_breach_notifications
                WHERE breach_date BETWEEN ? AND ?
                GROUP BY breach_risk_level
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $report['data_breaches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cookie consent
            $stmt = $this->pdo->prepare("
                SELECT
                    consent_level,
                    COUNT(*) as count
                FROM cookie_consent
                WHERE consent_timestamp BETWEEN ? AND ?
                GROUP BY consent_level
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $report['cookie_consent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate compliance score
            $report['compliance_score'] = $this->calculateComplianceScore($report);

            return [
                'success' => true,
                'report' => $report
            ];

        } catch (PDOException $e) {
            error_log("Failed to generate privacy compliance report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate report'
            ];
        }
    }

    /**
     * Export user data for portability (GDPR Article 20)
     */
    public function exportUserData(string $userId, array $options = []): array
    {
        try {
            $options = array_merge([
                'format' => 'json',
                'include_audit_logs' => false,
                'anonymize_sensitive_data' => true
            ], $options);

            $exportData = [
                'export_timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'data_categories' => []
            ];

            // Export consent data
            $stmt = $this->pdo->prepare("
                SELECT * FROM privacy_consent
                WHERE user_id = ? AND consent_withdrawn = false
            ");
            $stmt->execute([$userId]);
            $exportData['data_categories']['consent_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Export data subject requests
            $stmt = $this->pdo->prepare("
                SELECT * FROM data_subject_requests
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $exportData['data_categories']['data_subject_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Export cookie consent
            $stmt = $this->pdo->prepare("
                SELECT * FROM cookie_consent
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $exportData['data_categories']['cookie_consent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Anonymize sensitive data if requested
            if ($options['anonymize_sensitive_data']) {
                $exportData = $this->anonymizeExportData($exportData);
            }

            // Format export
            switch ($options['format']) {
                case 'json':
                    $content = json_encode($exportData, JSON_PRETTY_PRINT);
                    $filename = "user_data_export_{$userId}_" . date('Y-m-d_H-i-s') . '.json';
                    $mimeType = 'application/json';
                    break;

                case 'xml':
                    $content = $this->convertExportToXML($exportData);
                    $filename = "user_data_export_{$userId}_" . date('Y-m-d_H-i-s') . '.xml';
                    $mimeType = 'application/xml';
                    break;

                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported export format'
                    ];
            }

            // Log the export
            $this->logPrivacyAudit([
                'user_id' => $userId,
                'action_type' => 'data_exported',
                'resource_type' => 'user_data',
                'resource_id' => $userId,
                'action_description' => "User data exported in {$options['format']} format"
            ]);

            return [
                'success' => true,
                'content' => $content,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size' => strlen($content),
                'data_categories' => count($exportData['data_categories'])
            ];

        } catch (Exception $e) {
            error_log("Failed to export user data: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }

    // Private helper methods

    private function generateConsentId(): string
    {
        return 'consent_' . uniqid() . '_' . time();
    }

    private function generateRequestId(): string
    {
        return 'dsr_' . uniqid() . '_' . time();
    }

    private function generateBreachId(): string
    {
        return 'breach_' . uniqid() . '_' . time();
    }

    private function generateCookieConsentId(): string
    {
        return 'cookie_' . uniqid() . '_' . time();
    }

    private function generatePolicyId(): string
    {
        return 'policy_' . uniqid() . '_' . time();
    }

    private function validateConsentData(array $data): void
    {
        $required = ['user_id', 'consent_type', 'consent_purpose', 'legal_basis'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if (!in_array($data['legal_basis'], $this->config['legal_basis_options'])) {
            throw new Exception("Invalid legal basis: {$data['legal_basis']}");
        }
    }

    private function validateDataSubjectRequest(array $data): void
    {
        if (empty($data['user_id'])) {
            throw new Exception('User ID is required');
        }

        if (empty($data['request_type'])) {
            throw new Exception('Request type is required');
        }

        $validTypes = ['access', 'rectification', 'erasure', 'restriction', 'portability', 'objection', 'withdraw_consent'];
        if (!in_array($data['request_type'], $validTypes)) {
            throw new Exception('Invalid request type');
        }
    }

    private function logPrivacyAudit(array $auditData): void
    {
        try {
            $auditId = 'audit_' . uniqid() . '_' . time();

            $stmt = $this->pdo->prepare("
                INSERT INTO privacy_audit_log
                (audit_id, user_id, action_type, resource_type, resource_id,
                 action_description, ip_address, user_agent, old_values, new_values)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $auditId,
                $auditData['user_id'] ?? null,
                $auditData['action_type'],
                $auditData['resource_type'],
                $auditData['resource_id'] ?? null,
                $auditData['action_description'] ?? null,
                $auditData['ip_address'] ?? null,
                $auditData['user_agent'] ?? null,
                isset($auditData['old_values']) ? json_encode($auditData['old_values']) : null,
                isset($auditData['new_values']) ? json_encode($auditData['new_values']) : null
            ]);

        } catch (PDOException $e) {
            error_log("Failed to log privacy audit: " . $e->getMessage());
        }
    }

    private function notifyDPONewRequest(string $requestId, array $requestData): void
    {
        // Implementation would notify DPO of new request
        // This is a placeholder
    }

    private function sendBreachNotifications(string $breachId, array $breachData): void
    {
        // Implementation would send breach notifications to authorities and affected users
        // This is a placeholder
    }

    private function deleteUserData(string $table, string $userId): int
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }

    private function anonymizeUserData(string $table, string $userId): int
    {
        // Implementation would anonymize user data instead of deleting
        // This is a placeholder
        return 0;
    }

    private function notifyThirdPartiesOfDeletion(string $userId): void
    {
        // Implementation would notify third parties of data deletion
        // This is a placeholder
    }

    private function calculateComplianceScore(array $report): float
    {
        // Simple compliance score calculation
        $consentScore = ($report['consent_stats']['consents_given'] ?? 0) /
                       max($report['consent_stats']['total_consents'] ?? 1, 1) * 100;

        $requestScore = 100 - (count($report['data_subject_requests'] ?? []) * 5); // Penalty for requests
        $breachScore = 100 - (count($report['data_breaches'] ?? []) * 10); // Penalty for breaches

        return round(min(100, max(0, ($consentScore + $requestScore + $breachScore) / 3)), 2);
    }

    private function anonymizeExportData(array $data): array
    {
        // Implementation would anonymize sensitive data in export
        // This is a placeholder
        return $data;
    }

    private function convertExportToXML(array $data): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><data_export></data_export>');

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXML($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }

        return $xml->asXML();
    }

    private function arrayToXML(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXML($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}

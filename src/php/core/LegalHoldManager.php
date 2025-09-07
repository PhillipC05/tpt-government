<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Legal Hold and E-Discovery Manager
 *
 * This class provides comprehensive legal hold and e-discovery capabilities including:
 * - Legal hold management and enforcement
 * - Data preservation and chain of custody
 * - E-discovery search and collection
 * - Legal review workflow management
 * - Privilege and confidentiality handling
 * - Data export for legal proceedings
 * - Hold notifications and acknowledgments
 * - Custodian management and tracking
 * - Hold lift and release procedures
 * - Audit trails for all hold activities
 * - Integration with external legal systems
 * - Cost tracking and reporting for e-discovery
 */
class LegalHoldManager
{
    private PDO $pdo;
    private array $config;
    private AuditTrailManager $auditTrailManager;
    private NotificationManager $notificationManager;
    private DataRetentionManager $dataRetentionManager;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->auditTrailManager = new AuditTrailManager($pdo);
        $this->notificationManager = new NotificationManager($pdo);
        $this->dataRetentionManager = new DataRetentionManager($pdo);
        $this->config = array_merge([
            'auto_hold_enforcement' => true,
            'preserve_chain_of_custody' => true,
            'enable_e_discovery' => true,
            'legal_review_workflow' => true,
            'privilege_log_management' => true,
            'data_export_encryption' => true,
            'hold_notification_required' => true,
            'custodian_acknowledgment_required' => true,
            'hold_audit_frequency' => 'daily',
            'max_search_results' => 10000,
            'supported_export_formats' => ['pst', 'mbox', 'csv', 'json', 'xml', 'pdf'],
            'hold_lift_approval_required' => true,
            'external_counsel_integration' => false,
            'cost_tracking_enabled' => true,
            'data_mapping_preservation' => true,
            'hold_escalation_enabled' => true,
            'automated_hold_reminders' => true,
            'reminder_intervals_days' => [7, 14, 30, 60]
        ], $config);

        $this->createLegalHoldTables();
    }

    /**
     * Create legal hold and e-discovery tables
     */
    private function createLegalHoldTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS legal_matters (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                matter_id VARCHAR(255) NOT NULL UNIQUE,
                matter_title VARCHAR(500) NOT NULL,
                matter_description TEXT NOT NULL,
                matter_type ENUM('litigation', 'investigation', 'regulatory', 'audit', 'compliance', 'other') NOT NULL,
                case_number VARCHAR(255) DEFAULT NULL,
                court_name VARCHAR(255) DEFAULT NULL,
                jurisdiction VARCHAR(100) DEFAULT NULL,
                filing_date DATE DEFAULT NULL,
                status ENUM('active', 'closed', 'settled', 'dismissed', 'on_hold') DEFAULT 'active',
                priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                lead_attorney VARCHAR(255) DEFAULT NULL,
                client_matter_number VARCHAR(255) DEFAULT NULL,
                estimated_value DECIMAL(15,2) DEFAULT NULL,
                actual_cost DECIMAL(15,2) DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                assigned_to VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_matter (matter_id),
                INDEX idx_type (matter_type),
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS legal_holds (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                hold_id VARCHAR(255) NOT NULL UNIQUE,
                matter_id VARCHAR(255) NOT NULL,
                hold_title VARCHAR(500) NOT NULL,
                hold_description TEXT NOT NULL,
                hold_reason VARCHAR(100) NOT NULL,
                hold_type ENUM('preservation', 'collection', 'processing', 'review', 'production') DEFAULT 'preservation',
                preservation_scope TEXT NOT NULL,
                data_sources JSON DEFAULT NULL,
                data_types JSON DEFAULT NULL,
                date_range_start DATE DEFAULT NULL,
                date_range_end DATE DEFAULT NULL,
                custodians JSON DEFAULT NULL,
                hold_status ENUM('draft', 'issued', 'active', 'modified', 'released', 'expired') DEFAULT 'draft',
                issued_date TIMESTAMP NULL,
                effective_date TIMESTAMP NULL,
                expiration_date TIMESTAMP NULL,
                release_date TIMESTAMP NULL,
                release_reason TEXT DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                released_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (matter_id) REFERENCES legal_matters(matter_id) ON DELETE CASCADE,
                INDEX idx_hold (hold_id),
                INDEX idx_matter (matter_id),
                INDEX idx_status (hold_status),
                INDEX idx_type (hold_type),
                INDEX idx_issued (issued_date),
                INDEX idx_expiration (expiration_date)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS hold_custodians (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                custodian_id VARCHAR(255) NOT NULL UNIQUE,
                hold_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(255) NOT NULL,
                user_name VARCHAR(255) DEFAULT NULL,
                user_email VARCHAR(255) DEFAULT NULL,
                user_role VARCHAR(100) DEFAULT NULL,
                department VARCHAR(100) DEFAULT NULL,
                acknowledgment_status ENUM('pending', 'acknowledged', 'declined', 'escalated') DEFAULT 'pending',
                acknowledgment_date TIMESTAMP NULL,
                acknowledgment_ip VARCHAR(45) DEFAULT NULL,
                reminder_count INT DEFAULT 0,
                last_reminder_sent TIMESTAMP NULL,
                data_sources_responsible JSON DEFAULT NULL,
                estimated_data_volume BIGINT DEFAULT NULL,
                compliance_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (hold_id) REFERENCES legal_holds(hold_id) ON DELETE CASCADE,
                INDEX idx_custodian (custodian_id),
                INDEX idx_hold (hold_id),
                INDEX idx_user (user_id),
                INDEX idx_status (acknowledgment_status)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS e_discovery_searches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                search_id VARCHAR(255) NOT NULL UNIQUE,
                matter_id VARCHAR(255) NOT NULL,
                search_title VARCHAR(255) NOT NULL,
                search_description TEXT DEFAULT NULL,
                search_query JSON NOT NULL,
                search_filters JSON DEFAULT NULL,
                data_sources JSON DEFAULT NULL,
                date_range_start DATE DEFAULT NULL,
                date_range_end DATE DEFAULT NULL,
                custodian_filter JSON DEFAULT NULL,
                keyword_terms JSON DEFAULT NULL,
                boolean_operators VARCHAR(1000) DEFAULT NULL,
                proximity_terms JSON DEFAULT NULL,
                file_type_filter JSON DEFAULT NULL,
                search_status ENUM('draft', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'draft',
                total_results BIGINT DEFAULT NULL,
                relevant_results BIGINT DEFAULT NULL,
                privileged_results BIGINT DEFAULT NULL,
                duplicate_results BIGINT DEFAULT NULL,
                execution_start TIMESTAMP NULL,
                execution_end TIMESTAMP NULL,
                execution_duration_seconds INT DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                assigned_to VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (matter_id) REFERENCES legal_matters(matter_id) ON DELETE CASCADE,
                INDEX idx_search (search_id),
                INDEX idx_matter (matter_id),
                INDEX idx_status (search_status),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS e_discovery_results (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                result_id VARCHAR(255) NOT NULL UNIQUE,
                search_id VARCHAR(255) NOT NULL,
                data_id VARCHAR(255) NOT NULL,
                data_type VARCHAR(100) NOT NULL,
                data_source VARCHAR(255) DEFAULT NULL,
                custodian_id VARCHAR(255) DEFAULT NULL,
                file_path VARCHAR(1000) DEFAULT NULL,
                file_name VARCHAR(500) DEFAULT NULL,
                file_size BIGINT DEFAULT NULL,
                file_hash VARCHAR(128) DEFAULT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                creation_date TIMESTAMP NULL,
                modification_date TIMESTAMP NULL,
                access_date TIMESTAMP NULL,
                content_preview TEXT DEFAULT NULL,
                keyword_matches JSON DEFAULT NULL,
                relevance_score DECIMAL(5,2) DEFAULT NULL,
                privilege_status ENUM('not_reviewed', 'privileged', 'not_privileged', 'redacted') DEFAULT 'not_reviewed',
                production_status ENUM('not_reviewed', 'responsive', 'not_responsive', 'withheld') DEFAULT 'not_reviewed',
                review_notes TEXT DEFAULT NULL,
                reviewed_by VARCHAR(255) DEFAULT NULL,
                reviewed_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (search_id) REFERENCES e_discovery_searches(search_id) ON DELETE CASCADE,
                INDEX idx_result (result_id),
                INDEX idx_search (search_id),
                INDEX idx_data (data_id),
                INDEX idx_custodian (custodian_id),
                INDEX idx_privilege (privilege_status),
                INDEX idx_production (production_status),
                INDEX idx_relevance (relevance_score)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS privilege_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                privilege_id VARCHAR(255) NOT NULL UNIQUE,
                matter_id VARCHAR(255) NOT NULL,
                document_id VARCHAR(255) NOT NULL,
                privilege_type ENUM('attorney_client', 'work_product', 'joint_defense', 'settlement', 'other') NOT NULL,
                privilege_description TEXT DEFAULT NULL,
                claimed_by VARCHAR(255) NOT NULL,
                claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                basis_for_privilege TEXT DEFAULT NULL,
                opposing_party_notified BOOLEAN DEFAULT false,
                notification_date TIMESTAMP NULL,
                court_ruling VARCHAR(500) DEFAULT NULL,
                ruling_date TIMESTAMP NULL,
                privilege_upheld BOOLEAN DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (matter_id) REFERENCES legal_matters(matter_id) ON DELETE CASCADE,
                INDEX idx_privilege (privilege_id),
                INDEX idx_matter (matter_id),
                INDEX idx_document (document_id),
                INDEX idx_type (privilege_type),
                INDEX idx_claimed_by (claimed_by)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS data_productions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                production_id VARCHAR(255) NOT NULL UNIQUE,
                matter_id VARCHAR(255) NOT NULL,
                production_title VARCHAR(255) NOT NULL,
                production_description TEXT DEFAULT NULL,
                production_number VARCHAR(50) DEFAULT NULL,
                production_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                total_documents BIGINT DEFAULT NULL,
                total_size_gb DECIMAL(10,2) DEFAULT NULL,
                production_format VARCHAR(50) DEFAULT NULL,
                encryption_used BOOLEAN DEFAULT false,
                hash_verification VARCHAR(128) DEFAULT NULL,
                chain_of_custody TEXT DEFAULT NULL,
                delivered_to VARCHAR(255) DEFAULT NULL,
                delivery_method VARCHAR(100) DEFAULT NULL,
                delivery_tracking VARCHAR(255) DEFAULT NULL,
                acknowledgment_received BOOLEAN DEFAULT false,
                acknowledgment_date TIMESTAMP NULL,
                production_cost DECIMAL(10,2) DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (matter_id) REFERENCES legal_matters(matter_id) ON DELETE CASCADE,
                INDEX idx_production (production_id),
                INDEX idx_matter (matter_id),
                INDEX idx_date (production_date),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS hold_communications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                communication_id VARCHAR(255) NOT NULL UNIQUE,
                hold_id VARCHAR(255) NOT NULL,
                communication_type ENUM('initial_notice', 'reminder', 'acknowledgment', 'status_update', 'release_notice') NOT NULL,
                recipient_id VARCHAR(255) NOT NULL,
                recipient_email VARCHAR(255) DEFAULT NULL,
                subject VARCHAR(500) DEFAULT NULL,
                message TEXT NOT NULL,
                sent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                delivery_status ENUM('sent', 'delivered', 'failed', 'bounced') DEFAULT 'sent',
                delivery_error TEXT DEFAULT NULL,
                opened_date TIMESTAMP NULL,
                response_received BOOLEAN DEFAULT false,
                response_date TIMESTAMP NULL,
                response_content TEXT DEFAULT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (hold_id) REFERENCES legal_holds(hold_id) ON DELETE CASCADE,
                INDEX idx_communication (communication_id),
                INDEX idx_hold (hold_id),
                INDEX idx_type (communication_type),
                INDEX idx_recipient (recipient_id),
                INDEX idx_sent (sent_date),
                INDEX idx_delivery (delivery_status)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS e_discovery_costs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cost_id VARCHAR(255) NOT NULL UNIQUE,
                matter_id VARCHAR(255) DEFAULT NULL,
                cost_category ENUM('collection', 'processing', 'review', 'production', 'consulting', 'software', 'other') NOT NULL,
                cost_description TEXT DEFAULT NULL,
                vendor_name VARCHAR(255) DEFAULT NULL,
                invoice_number VARCHAR(255) DEFAULT NULL,
                cost_amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                cost_date DATE NOT NULL,
                billing_period_start DATE DEFAULT NULL,
                billing_period_end DATE DEFAULT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                approval_date TIMESTAMP NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (matter_id) REFERENCES legal_matters(matter_id) ON DELETE SET NULL,
                INDEX idx_cost (cost_id),
                INDEX idx_matter (matter_id),
                INDEX idx_category (cost_category),
                INDEX idx_date (cost_date),
                INDEX idx_amount (cost_amount)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create legal hold tables: " . $e->getMessage());
        }
    }

    /**
     * Create legal matter
     */
    public function createLegalMatter(array $matterData): array
    {
        try {
            // Validate matter data
            $this->validateMatterData($matterData);

            $matterId = $this->generateMatterId();

            $stmt = $this->pdo->prepare("
                INSERT INTO legal_matters
                (matter_id, matter_title, matter_description, matter_type,
                 case_number, court_name, jurisdiction, filing_date, priority,
                 lead_attorney, client_matter_number, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $matterId,
                $matterData['matter_title'],
                $matterData['matter_description'],
                $matterData['matter_type'],
                $matterData['case_number'] ?? null,
                $matterData['court_name'] ?? null,
                $matterData['jurisdiction'] ?? null,
                $matterData['filing_date'] ?? null,
                $matterData['priority'] ?? 'medium',
                $matterData['lead_attorney'] ?? null,
                $matterData['client_matter_number'] ?? null,
                $matterData['created_by']
            ]);

            // Log matter creation
            $this->auditTrailManager->logUserAction([
                'action_type' => 'legal_matter_created',
                'resource_type' => 'legal_matter',
                'resource_id' => $matterId,
                'action_description' => "Created legal matter: {$matterData['matter_title']}",
                'user_id' => $matterData['created_by'],
                'new_values' => $matterData
            ]);

            return [
                'success' => true,
                'matter_id' => $matterId,
                'message' => 'Legal matter created successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to create legal matter: " . $e->getMessage());
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
     * Issue legal hold
     */
    public function issueLegalHold(array $holdData): array
    {
        try {
            // Validate hold data
            $this->validateHoldData($holdData);

            $holdId = $this->generateHoldId();

            $stmt = $this->pdo->prepare("
                INSERT INTO legal_holds
                (hold_id, matter_id, hold_title, hold_description, hold_reason,
                 hold_type, preservation_scope, data_sources, data_types,
                 date_range_start, date_range_end, custodians, issued_date,
                 effective_date, expiration_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
            ");

            $stmt->execute([
                $holdId,
                $holdData['matter_id'],
                $holdData['hold_title'],
                $holdData['hold_description'],
                $holdData['hold_reason'],
                $holdData['hold_type'] ?? 'preservation',
                $holdData['preservation_scope'],
                isset($holdData['data_sources']) ? json_encode($holdData['data_sources']) : null,
                isset($holdData['data_types']) ? json_encode($holdData['data_types']) : null,
                $holdData['date_range_start'] ?? null,
                $holdData['date_range_end'] ?? null,
                isset($holdData['custodians']) ? json_encode($holdData['custodians']) : null,
                $holdData['expiration_date'] ?? null,
                $holdData['created_by']
            ]);

            // Add custodians to hold
            if (!empty($holdData['custodians'])) {
                $this->addCustodiansToHold($holdId, $holdData['custodians']);
            }

            // Apply data retention hold
            $this->applyDataRetentionHold($holdId, $holdData);

            // Send hold notifications
            $this->sendHoldNotifications($holdId, $holdData);

            // Log hold issuance
            $this->auditTrailManager->logUserAction([
                'action_type' => 'legal_hold_issued',
                'resource_type' => 'legal_hold',
                'resource_id' => $holdId,
                'action_description' => "Issued legal hold: {$holdData['hold_title']}",
                'user_id' => $holdData['created_by'],
                'new_values' => $holdData
            ]);

            return [
                'success' => true,
                'hold_id' => $holdId,
                'message' => 'Legal hold issued successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to issue legal hold: " . $e->getMessage());
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
     * Execute e-discovery search
     */
    public function executeEDiscoverySearch(array $searchData): array
    {
        try {
            // Validate search data
            $this->validateSearchData($searchData);

            $searchId = $this->generateSearchId();

            $stmt = $this->pdo->prepare("
                INSERT INTO e_discovery_searches
                (search_id, matter_id, search_title, search_description,
                 search_query, search_filters, data_sources, date_range_start,
                 date_range_end, custodian_filter, keyword_terms, search_status,
                 created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'running', ?)
            ");

            $stmt->execute([
                $searchId,
                $searchData['matter_id'],
                $searchData['search_title'],
                $searchData['search_description'] ?? null,
                json_encode($searchData['search_query']),
                isset($searchData['search_filters']) ? json_encode($searchData['search_filters']) : null,
                isset($searchData['data_sources']) ? json_encode($searchData['data_sources']) : null,
                $searchData['date_range_start'] ?? null,
                $searchData['date_range_end'] ?? null,
                isset($searchData['custodian_filter']) ? json_encode($searchData['custodian_filter']) : null,
                isset($searchData['keyword_terms']) ? json_encode($searchData['keyword_terms']) : null,
                $searchData['created_by']
            ]);

            // Execute the search
            $searchResults = $this->performEDiscoverySearch($searchId, $searchData);

            // Update search with results
            $this->updateSearchResults($searchId, $searchResults);

            // Log search execution
            $this->auditTrailManager->logUserAction([
                'action_type' => 'e_discovery_search_executed',
                'resource_type' => 'e_discovery_search',
                'resource_id' => $searchId,
                'action_description' => "Executed e-discovery search: {$searchData['search_title']}",
                'user_id' => $searchData['created_by'],
                'new_values' => ['results_count' => count($searchResults)]
            ]);

            return [
                'success' => true,
                'search_id' => $searchId,
                'results_count' => count($searchResults),
                'message' => 'E-discovery search executed successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to execute e-discovery search: " . $e->getMessage());
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
     * Acknowledge legal hold
     */
    public function acknowledgeLegalHold(string $holdId, string $userId, array $acknowledgmentData): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE hold_custodians
                SET acknowledgment_status = 'acknowledged',
                    acknowledgment_date = NOW(),
                    acknowledgment_ip = ?,
                    compliance_notes = ?
                WHERE hold_id = ? AND user_id = ?
            ");

            $stmt->execute([
                $acknowledgmentData['ip_address'] ?? null,
                $acknowledgmentData['compliance_notes'] ?? null,
                $holdId,
                $userId
            ]);

            if ($stmt->rowCount() > 0) {
                // Log acknowledgment
                $this->auditTrailManager->logUserAction([
                    'action_type' => 'legal_hold_acknowledged',
                    'resource_type' => 'legal_hold',
                    'resource_id' => $holdId,
                    'action_description' => "User {$userId} acknowledged legal hold {$holdId}",
                    'user_id' => $userId,
                    'new_values' => $acknowledgmentData
                ]);

                return [
                    'success' => true,
                    'message' => 'Legal hold acknowledged successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Custodian not found for this hold'
                ];
            }

        } catch (PDOException $e) {
            error_log("Failed to acknowledge legal hold: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Release legal hold
     */
    public function releaseLegalHold(string $holdId, array $releaseData): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE legal_holds
                SET hold_status = 'released',
                    release_date = NOW(),
                    release_reason = ?,
                    released_by = ?
                WHERE hold_id = ?
            ");

            $stmt->execute([
                $releaseData['release_reason'],
                $releaseData['released_by'],
                $holdId
            ]);

            if ($stmt->rowCount() > 0) {
                // Update data retention records
                $this->releaseDataRetentionHold($holdId);

                // Send release notifications
                $this->sendHoldReleaseNotifications($holdId, $releaseData);

                // Log hold release
                $this->auditTrailManager->logUserAction([
                    'action_type' => 'legal_hold_released',
                    'resource_type' => 'legal_hold',
                    'resource_id' => $holdId,
                    'action_description' => "Released legal hold: {$releaseData['release_reason']}",
                    'user_id' => $releaseData['released_by'],
                    'new_values' => $releaseData
                ]);

                return [
                    'success' => true,
                    'message' => 'Legal hold released successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Hold not found'
                ];
            }

        } catch (PDOException $e) {
            error_log("Failed to release legal hold: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Generate data production
     */
    public function generateDataProduction(array $productionData): array
    {
        try {
            // Validate production data
            $this->validateProductionData($productionData);

            $productionId = $this->generateProductionId();

            $stmt = $this->pdo->prepare("
                INSERT INTO data_productions
                (production_id, matter_id, production_title, production_description,
                 production_number, production_format, encryption_used, delivered_to,
                 delivery_method, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $productionId,
                $productionData['matter_id'],
                $productionData['production_title'],
                $productionData['production_description'] ?? null,
                $productionData['production_number'] ?? null,
                $productionData['production_format'] ?? 'pst',
                $productionData['encryption_used'] ?? false,
                $productionData['delivered_to'] ?? null,
                $productionData['delivery_method'] ?? null,
                $productionData['created_by']
            ]);

            // Generate production files
            $productionFiles = $this->generateProductionFiles($productionId, $productionData);

            // Update production with file info
            $this->updateProductionFiles($productionId, $productionFiles);

            // Log production
            $this->auditTrailManager->logUserAction([
                'action_type' => 'data_production_generated',
                'resource_type' => 'data_production',
                'resource_id' => $productionId,
                'action_description' => "Generated data production: {$productionData['production_title']}",
                'user_id' => $productionData['created_by'],
                'new_values' => $productionData
            ]);

            return [
                'success' => true,
                'production_id' => $productionId,
                'files' => $productionFiles,
                'message' => 'Data production generated successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to generate data production: " . $e->getMessage());
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
     * Get legal hold status report
     */
    public function getLegalHoldStatusReport(array $filters = []): array
    {
        try {
            $filters = array_merge([
                'date_range' => '30_days',
                'status' => 'all',
                'matter_type' => 'all'
            ], $filters);

            $report = [
                'generated_at' => date('Y-m-d H:i:s'),
                'filters' => $filters
            ];

            // Active holds summary
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_holds,
                    SUM(CASE WHEN hold_status = 'active' THEN 1 ELSE 0 END) as active_holds,
                    SUM(CASE WHEN hold_status = 'expired' THEN 1 ELSE 0 END) as expired_holds,
                    SUM(CASE WHEN hold_status = 'released' THEN 1 ELSE 0 END) as released_holds
                FROM legal_holds
                WHERE issued_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $report['hold_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Custodian acknowledgment status
            $stmt = $this->pdo->prepare("
                SELECT
                    acknowledgment_status,
                    COUNT(*) as count
                FROM hold_custodians hc
                JOIN legal_holds lh ON hc.hold_id = lh.hold_id
                WHERE lh.issued_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY acknowledgment_status
            ");
            $stmt->execute();
            $report['custodian_acknowledgments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // E-discovery search summary
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_searches,
                    SUM(total_results) as total_results_found,
                    AVG(execution_duration_seconds) as avg_search_time
                FROM e_discovery_searches
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $report['search_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Cost tracking
            $stmt = $this->pdo->prepare("
                SELECT
                    cost_category,
                    SUM(cost_amount) as total_cost,
                    COUNT(*) as transaction_count
                FROM e_discovery_costs
                WHERE cost_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY cost_category
            ");
            $stmt->execute();
            $report['cost_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'report' => $report
            ];

        } catch (PDOException $e) {
            error_log("Failed to get legal hold status report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate report'
            ];
        }
    }

    // Private helper methods

    private function generateMatterId(): string
    {
        return 'matter_' . uniqid() . '_' . time();
    }

    private function generateHoldId(): string
    {
        return 'hold_' . uniqid() . '_' . time();
    }

    private function generateSearchId(): string
    {
        return 'search_' . uniqid() . '_' . time();
    }

    private function generateProductionId(): string
    {
        return 'production_' . uniqid() . '_' . time();
    }

    private function validateMatterData(array $data): void
    {
        $required = ['matter_title', 'matter_description', 'matter_type', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    private function validateHoldData(array $data): void
    {
        $required = ['matter_id', 'hold_title', 'hold_description', 'hold_reason', 'preservation_scope', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    private function validateSearchData(array $data): void
    {
        $required = ['matter_id', 'search_title', 'search_query', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    private function validateProductionData(array $data): void
    {
        $required = ['matter_id', 'production_title', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
    }

    private function addCustodiansToHold(string $holdId, array $custodians): void
    {
        foreach ($custodians as $custodian) {
            $custodianId = 'cust_' . uniqid() . '_' . time();

            $stmt = $this->pdo->prepare("
                INSERT INTO hold_custodians
                (custodian_id, hold_id, user_id, user_name, user_email, user_role, department)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $custodianId,
                $holdId,
                $custodian['user_id'],
                $custodian['user_name'] ?? null,
                $custodian['user_email'] ?? null,
                $custodian['user_role'] ?? null,
                $custodian['department'] ?? null
            ]);
        }
    }

    private function applyDataRetentionHold(string $holdId, array $holdData): void
    {
        // Apply legal hold through data retention manager
        $this->dataRetentionManager->applyLegalHold([
            'hold_id' => $holdId,
            'hold_title' => $holdData['hold_title'],
            'hold_description' => $holdData['hold_description'],
            'hold_reason' => $holdData['hold_reason'],
            'requesting_party' => $holdData['created_by'],
            'affected_data_categories' => $holdData['data_types'] ?? null,
            'data_scope' => $holdData['preservation_scope'],
            'created_by' => $holdData['created_by']
        ]);
    }

    private function sendHoldNotifications(string $holdId, array $holdData): void
    {
        // Implementation would send hold notifications
        // This is a placeholder
    }

    private function performEDiscoverySearch(string $searchId, array $searchData): array
    {
        // Implementation would perform actual e-discovery search
        // This is a placeholder
        return [];
    }

    private function updateSearchResults(string $searchId, array $results): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE e_discovery_searches
            SET search_status = 'completed',
                total_results = ?,
                execution_end = NOW(),
                execution_duration_seconds = TIMESTAMPDIFF(SECOND, execution_start, NOW())
            WHERE search_id = ?
        ");
        $stmt->execute([count($results), $searchId]);
    }

    private function releaseDataRetentionHold(string $holdId): void
    {
        // Implementation would release data retention hold
        // This is a placeholder
    }

    private function sendHoldReleaseNotifications(string $holdId, array $releaseData): void
    {
        // Implementation would send hold release notifications
        // This is a placeholder
    }

    private function generateProductionFiles(string $productionId, array $productionData): array
    {
        // Implementation would generate production files
        // This is a placeholder
        return [];
    }

    private function updateProductionFiles(string $productionId, array $files): void
    {
        // Implementation would update production with file info
        // This is a placeholder
    }
}

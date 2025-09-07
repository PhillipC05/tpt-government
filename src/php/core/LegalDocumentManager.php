<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Legal Document Management System
 *
 * This class provides comprehensive legal document management including:
 * - Document lifecycle management
 * - Version control and tracking
 * - Document classification and categorization
 * - Access control and permissions
 * - Document signing and approval workflows
 * - Compliance tracking and reporting
 * - Document retention and disposal
 * - Audit trails and change tracking
 * - Integration with e-signature services
 * - Document search and retrieval
 */
class LegalDocumentManager
{
    private PDO $pdo;
    private array $config;
    private NotificationManager $notificationManager;
    private WorkflowEngine $workflowEngine;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->notificationManager = new NotificationManager($pdo);
        $this->workflowEngine = new WorkflowEngine($pdo);
        $this->config = array_merge([
            'document_retention_period' => 2555, // days (7 years)
            'auto_versioning' => true,
            'require_approval_workflow' => true,
            'enable_electronic_signatures' => true,
            'document_watermarking' => true,
            'compliance_checking' => true,
            'audit_trail_enabled' => true,
            'document_sharing' => true,
            'bulk_operations' => true,
            'supported_formats' => ['pdf', 'docx', 'xlsx', 'txt', 'html'],
            'max_file_size' => 10485760, // 10MB
            'encryption_enabled' => true,
            'backup_enabled' => true,
            'search_enabled' => true,
            'ocr_enabled' => true,
            'auto_tagging' => true
        ], $config);

        $this->createLegalTables();
    }

    /**
     * Create legal document management tables
     */
    private function createLegalTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS legal_documents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id VARCHAR(255) NOT NULL UNIQUE,
                document_title VARCHAR(500) NOT NULL,
                document_description TEXT DEFAULT NULL,
                document_type ENUM('contract', 'agreement', 'policy', 'regulation', 'license', 'permit', 'complaint', 'other') NOT NULL,
                document_category VARCHAR(100) DEFAULT NULL,
                document_status ENUM('draft', 'review', 'approved', 'signed', 'active', 'expired', 'archived', 'deleted') DEFAULT 'draft',
                current_version VARCHAR(20) DEFAULT '1.0.0',
                file_path VARCHAR(1000) DEFAULT NULL,
                file_size BIGINT DEFAULT NULL,
                file_hash VARCHAR(128) DEFAULT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                owned_by VARCHAR(255) DEFAULT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                signed_by JSON DEFAULT NULL,
                effective_date TIMESTAMP NULL,
                expiration_date TIMESTAMP NULL,
                retention_period_days INT DEFAULT NULL,
                is_confidential BOOLEAN DEFAULT false,
                is_template BOOLEAN DEFAULT false,
                requires_signature BOOLEAN DEFAULT false,
                signature_deadline TIMESTAMP NULL,
                tags JSON DEFAULT NULL,
                metadata JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_document (document_id),
                INDEX idx_type (document_type),
                INDEX idx_category (document_category),
                INDEX idx_status (document_status),
                INDEX idx_created_by (created_by),
                INDEX idx_effective (effective_date),
                INDEX idx_expiration (expiration_date),
                INDEX idx_confidential (is_confidential)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version_id VARCHAR(255) NOT NULL UNIQUE,
                document_id VARCHAR(255) NOT NULL,
                version_number VARCHAR(20) NOT NULL,
                version_label VARCHAR(100) DEFAULT NULL,
                file_path VARCHAR(1000) NOT NULL,
                file_size BIGINT DEFAULT NULL,
                file_hash VARCHAR(128) DEFAULT NULL,
                changes_description TEXT DEFAULT NULL,
                created_by VARCHAR(255) NOT NULL,
                approved_by VARCHAR(255) DEFAULT NULL,
                is_major_version BOOLEAN DEFAULT false,
                is_published BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES legal_documents(document_id) ON DELETE CASCADE,
                INDEX idx_version (version_id),
                INDEX idx_document (document_id),
                INDEX idx_version_num (version_number),
                INDEX idx_created_by (created_by),
                INDEX idx_published (is_published)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_signatures (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                signature_id VARCHAR(255) NOT NULL UNIQUE,
                document_id VARCHAR(255) NOT NULL,
                signer_id VARCHAR(255) NOT NULL,
                signer_name VARCHAR(255) DEFAULT NULL,
                signer_email VARCHAR(255) DEFAULT NULL,
                signer_role VARCHAR(100) DEFAULT NULL,
                signature_type ENUM('electronic', 'digital', 'wet_ink') DEFAULT 'electronic',
                signature_data TEXT DEFAULT NULL,
                signature_hash VARCHAR(128) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                signed_at TIMESTAMP NULL,
                signature_status ENUM('pending', 'signed', 'rejected', 'expired') DEFAULT 'pending',
                rejection_reason TEXT DEFAULT NULL,
                reminder_count INT DEFAULT 0,
                last_reminder_sent TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES legal_documents(document_id) ON DELETE CASCADE,
                INDEX idx_signature (signature_id),
                INDEX idx_document (document_id),
                INDEX idx_signer (signer_id),
                INDEX idx_status (signature_status),
                INDEX idx_signed_at (signed_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                permission_id VARCHAR(255) NOT NULL UNIQUE,
                document_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(255) DEFAULT NULL,
                group_id VARCHAR(255) DEFAULT NULL,
                permission_type ENUM('view', 'edit', 'delete', 'sign', 'approve', 'admin') NOT NULL,
                granted_by VARCHAR(255) NOT NULL,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                is_active BOOLEAN DEFAULT true,
                FOREIGN KEY (document_id) REFERENCES legal_documents(document_id) ON DELETE CASCADE,
                INDEX idx_permission (permission_id),
                INDEX idx_document (document_id),
                INDEX idx_user (user_id),
                INDEX idx_group (group_id),
                INDEX idx_type (permission_type),
                INDEX idx_active (is_active),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                audit_id VARCHAR(255) NOT NULL UNIQUE,
                document_id VARCHAR(255) DEFAULT NULL,
                user_id VARCHAR(255) DEFAULT NULL,
                action_type VARCHAR(100) NOT NULL,
                action_description TEXT DEFAULT NULL,
                old_values JSON DEFAULT NULL,
                new_values JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                audit_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES legal_documents(document_id) ON DELETE SET NULL,
                INDEX idx_audit (audit_id),
                INDEX idx_document (document_id),
                INDEX idx_user (user_id),
                INDEX idx_action (action_type),
                INDEX idx_timestamp (audit_timestamp)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_workflows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                workflow_id VARCHAR(255) NOT NULL UNIQUE,
                document_id VARCHAR(255) NOT NULL,
                workflow_type ENUM('approval', 'review', 'signing', 'publication') NOT NULL,
                workflow_status ENUM('pending', 'in_progress', 'completed', 'cancelled', 'rejected') DEFAULT 'pending',
                current_step VARCHAR(100) DEFAULT NULL,
                next_step VARCHAR(100) DEFAULT NULL,
                assigned_users JSON DEFAULT NULL,
                required_approvals INT DEFAULT 1,
                current_approvals INT DEFAULT 0,
                deadline TIMESTAMP NULL,
                created_by VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                FOREIGN KEY (document_id) REFERENCES legal_documents(document_id) ON DELETE CASCADE,
                INDEX idx_workflow (workflow_id),
                INDEX idx_document (document_id),
                INDEX idx_type (workflow_type),
                INDEX idx_status (workflow_status),
                INDEX idx_assigned (assigned_users(100))
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id VARCHAR(255) NOT NULL UNIQUE,
                template_name VARCHAR(255) NOT NULL,
                template_description TEXT DEFAULT NULL,
                template_category VARCHAR(100) NOT NULL,
                template_file_path VARCHAR(1000) NOT NULL,
                template_variables JSON DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                usage_count INT DEFAULT 0,
                created_by VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_template (template_id),
                INDEX idx_category (template_category),
                INDEX idx_active (is_active),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS document_compliance (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                compliance_id VARCHAR(255) NOT NULL UNIQUE,
                document_id VARCHAR(255) NOT NULL,
                compliance_standard VARCHAR(100) NOT NULL,
                compliance_status ENUM('compliant', 'non_compliant', 'pending_review', 'exempted') DEFAULT 'pending_review',
                compliance_notes TEXT DEFAULT NULL,
                reviewed_by VARCHAR(255) DEFAULT NULL,
                reviewed_at TIMESTAMP NULL,
                next_review_date TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (document_id) REFERENCES legal_documents(document_id) ON DELETE CASCADE,
                INDEX idx_compliance (compliance_id),
                INDEX idx_document (document_id),
                INDEX idx_standard (compliance_standard),
                INDEX idx_status (compliance_status),
                INDEX idx_review_date (next_review_date)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create legal tables: " . $e->getMessage());
        }
    }

    /**
     * Create a new legal document
     */
    public function createDocument(array $documentData, string $filePath = null): array
    {
        try {
            // Validate document data
            $this->validateDocumentData($documentData);

            $documentId = $this->generateDocumentId();

            // Handle file upload if provided
            $fileInfo = null;
            if ($filePath) {
                $fileInfo = $this->processDocumentFile($filePath);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO legal_documents
                (document_id, document_title, document_description, document_type,
                 document_category, created_by, owned_by, file_path, file_size,
                 file_hash, mime_type, is_confidential, is_template,
                 requires_signature, tags, metadata, effective_date, expiration_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $documentId,
                $documentData['title'],
                $documentData['description'] ?? null,
                $documentData['type'],
                $documentData['category'] ?? null,
                $documentData['created_by'],
                $documentData['owned_by'] ?? $documentData['created_by'],
                $fileInfo['path'] ?? null,
                $fileInfo['size'] ?? null,
                $fileInfo['hash'] ?? null,
                $fileInfo['mime_type'] ?? null,
                $documentData['is_confidential'] ?? false,
                $documentData['is_template'] ?? false,
                $documentData['requires_signature'] ?? false,
                isset($documentData['tags']) ? json_encode($documentData['tags']) : null,
                isset($documentData['metadata']) ? json_encode($documentData['metadata']) : null,
                $documentData['effective_date'] ?? null,
                $documentData['expiration_date'] ?? null
            ]);

            // Create initial version
            $this->createDocumentVersion($documentId, '1.0.0', $documentData['created_by'], $fileInfo['path'] ?? null);

            // Start approval workflow if required
            if ($this->config['require_approval_workflow'] && !$documentData['is_template']) {
                $this->startApprovalWorkflow($documentId, $documentData);
            }

            // Log document creation
            $this->logDocumentAudit($documentId, $documentData['created_by'], 'document_created', [
                'document_data' => $documentData,
                'file_info' => $fileInfo
            ]);

            return [
                'success' => true,
                'document_id' => $documentId,
                'message' => 'Document created successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to create document: " . $e->getMessage());
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
     * Update document content and create new version
     */
    public function updateDocument(string $documentId, array $updateData, string $userId, string $filePath = null): array
    {
        try {
            // Get current document
            $document = $this->getDocumentById($documentId);
            if (!$document) {
                return [
                    'success' => false,
                    'error' => 'Document not found'
                ];
            }

            // Check permissions
            if (!$this->hasDocumentPermission($documentId, $userId, 'edit')) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions'
                ];
            }

            // Handle file update
            $fileInfo = null;
            if ($filePath) {
                $fileInfo = $this->processDocumentFile($filePath);
            }

            // Generate new version number
            $newVersion = $this->incrementVersion($document['current_version'], $updateData['is_major_version'] ?? false);

            // Update document
            $updateFields = [];
            $params = [];

            $allowedFields = [
                'document_title', 'document_description', 'document_category',
                'is_confidential', 'tags', 'metadata', 'effective_date', 'expiration_date'
            ];

            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = is_array($value) ? json_encode($value) : $value;
                }
            }

            if ($fileInfo) {
                $updateFields[] = "file_path = ?, file_size = ?, file_hash = ?, mime_type = ?";
                $params[] = $fileInfo['path'];
                $params[] = $fileInfo['size'];
                $params[] = $fileInfo['hash'];
                $params[] = $fileInfo['mime_type'];
            }

            $updateFields[] = "current_version = ?, updated_at = NOW()";
            $params[] = $newVersion;
            $params[] = $documentId;

            $stmt = $this->pdo->prepare("
                UPDATE legal_documents
                SET " . implode(', ', $updateFields) . "
                WHERE document_id = ?
            ");

            $stmt->execute($params);

            // Create new version record
            if ($fileInfo) {
                $this->createDocumentVersion($documentId, $newVersion, $userId, $fileInfo['path'], $updateData['changes_description'] ?? null);
            }

            // Log document update
            $this->logDocumentAudit($documentId, $userId, 'document_updated', [
                'old_values' => $document,
                'new_values' => $updateData,
                'new_version' => $newVersion
            ]);

            return [
                'success' => true,
                'new_version' => $newVersion,
                'message' => 'Document updated successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to update document: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Request document signature
     */
    public function requestSignature(string $documentId, array $signerData, string $requesterId): array
    {
        try {
            // Validate signature request
            $this->validateSignatureRequest($documentId, $signerData);

            $signatureId = $this->generateSignatureId();

            $stmt = $this->pdo->prepare("
                INSERT INTO document_signatures
                (signature_id, document_id, signer_id, signer_name, signer_email,
                 signer_role, signature_type, signature_deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $deadline = isset($signerData['deadline_days']) ?
                date('Y-m-d H:i:s', strtotime("+{$signerData['deadline_days']} days")) : null;

            $stmt->execute([
                $signatureId,
                $documentId,
                $signerData['signer_id'],
                $signerData['signer_name'] ?? null,
                $signerData['signer_email'] ?? null,
                $signerData['signer_role'] ?? null,
                $signerData['signature_type'] ?? 'electronic',
                $deadline
            ]);

            // Send signature request notification
            $this->sendSignatureRequestNotification($signatureId, $signerData);

            // Log signature request
            $this->logDocumentAudit($documentId, $requesterId, 'signature_requested', [
                'signature_id' => $signatureId,
                'signer_data' => $signerData
            ]);

            return [
                'success' => true,
                'signature_id' => $signatureId,
                'message' => 'Signature request sent successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to request signature: " . $e->getMessage());
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
     * Sign document
     */
    public function signDocument(string $signatureId, array $signatureData, string $signerId): array
    {
        try {
            // Get signature request
            $signature = $this->getSignatureById($signatureId);
            if (!$signature || $signature['signer_id'] !== $signerId) {
                return [
                    'success' => false,
                    'error' => 'Invalid signature request'
                ];
            }

            if ($signature['signature_status'] !== 'pending') {
                return [
                    'success' => false,
                    'error' => 'Signature request is not pending'
                ];
            }

            // Generate signature hash
            $signatureHash = $this->generateSignatureHash($signatureData);

            $stmt = $this->pdo->prepare("
                UPDATE document_signatures
                SET signature_data = ?,
                    signature_hash = ?,
                    ip_address = ?,
                    user_agent = ?,
                    signed_at = NOW(),
                    signature_status = 'signed'
                WHERE signature_id = ?
            ");

            $stmt->execute([
                json_encode($signatureData),
                $signatureHash,
                $signatureData['ip_address'] ?? null,
                $signatureData['user_agent'] ?? null,
                $signatureId
            ]);

            // Update document signatures
            $this->updateDocumentSignatures($signature['document_id']);

            // Check if all required signatures are complete
            $this->checkDocumentSignaturesComplete($signature['document_id']);

            // Log signature
            $this->logDocumentAudit($signature['document_id'], $signerId, 'document_signed', [
                'signature_id' => $signatureId,
                'signature_data' => $signatureData
            ]);

            return [
                'success' => true,
                'message' => 'Document signed successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to sign document: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Search legal documents
     */
    public function searchDocuments(array $searchCriteria, array $options = []): array
    {
        try {
            $options = array_merge([
                'page' => 1,
                'page_size' => 20,
                'sort_by' => 'updated_at',
                'sort_order' => 'desc',
                'include_versions' => false
            ], $options);

            $query = "
                SELECT d.*,
                       u1.name as created_by_name,
                       u2.name as owned_by_name,
                       COUNT(v.id) as version_count
                FROM legal_documents d
                LEFT JOIN users u1 ON d.created_by = u1.id
                LEFT JOIN users u2 ON d.owned_by = u2.id
                LEFT JOIN document_versions v ON d.document_id = v.document_id
                WHERE 1=1
            ";

            $params = [];
            $conditions = [];

            // Search by title or description
            if (!empty($searchCriteria['query'])) {
                $conditions[] = "(d.document_title LIKE ? OR d.document_description LIKE ?)";
                $searchTerm = '%' . $searchCriteria['query'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Filter by type
            if (!empty($searchCriteria['type'])) {
                $conditions[] = "d.document_type = ?";
                $params[] = $searchCriteria['type'];
            }

            // Filter by category
            if (!empty($searchCriteria['category'])) {
                $conditions[] = "d.document_category = ?";
                $params[] = $searchCriteria['category'];
            }

            // Filter by status
            if (!empty($searchCriteria['status'])) {
                $conditions[] = "d.document_status = ?";
                $params[] = $searchCriteria['status'];
            }

            // Filter by date range
            if (!empty($searchCriteria['date_from'])) {
                $conditions[] = "d.created_at >= ?";
                $params[] = $searchCriteria['date_from'];
            }

            if (!empty($searchCriteria['date_to'])) {
                $conditions[] = "d.created_at <= ?";
                $params[] = $searchCriteria['date_to'];
            }

            // Filter by confidentiality
            if (isset($searchCriteria['confidential_only'])) {
                $conditions[] = "d.is_confidential = ?";
                $params[] = $searchCriteria['confidential_only'];
            }

            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            $query .= " GROUP BY d.id";

            // Add sorting
            $allowedSortFields = ['document_title', 'document_type', 'created_at', 'updated_at', 'document_status'];
            if (in_array($options['sort_by'], $allowedSortFields)) {
                $query .= " ORDER BY d.{$options['sort_by']} {$options['sort_order']}";
            }

            // Add pagination
            $offset = ($options['page'] - 1) * $options['page_size'];
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $options['page_size'];
            $params[] = $offset;

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'documents' => $documents,
                'total' => $this->getSearchTotal($searchCriteria),
                'page' => $options['page'],
                'page_size' => $options['page_size']
            ];

        } catch (PDOException $e) {
            error_log("Failed to search documents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Search failed'
            ];
        }
    }

    /**
     * Get document compliance report
     */
    public function getComplianceReport(array $dateRange = []): array
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

            // Document status summary
            $stmt = $this->pdo->prepare("
                SELECT document_status, COUNT(*) as count
                FROM legal_documents
                WHERE created_at BETWEEN ? AND ?
                GROUP BY document_status
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $report['document_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Signature status summary
            $stmt = $this->pdo->prepare("
                SELECT signature_status, COUNT(*) as count
                FROM document_signatures ds
                JOIN legal_documents d ON ds.document_id = d.document_id
                WHERE d.created_at BETWEEN ? AND ?
                GROUP BY signature_status
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $report['signature_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compliance status summary
            $stmt = $this->pdo->prepare("
                SELECT compliance_status, COUNT(*) as count
                FROM document_compliance dc
                JOIN legal_documents d ON dc.document_id = d.document_id
                WHERE d.created_at BETWEEN ? AND ?
                GROUP BY compliance_status
            ");
            $stmt->execute([$dateRange['start_date'] . ' 00:00:00', $dateRange['end_date'] . ' 23:59:59']);
            $report['compliance_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Documents requiring attention
            $stmt = $this->pdo->prepare("
                SELECT document_id, document_title, document_status,
                       DATEDIFF(expiration_date, NOW()) as days_until_expiry
                FROM legal_documents
                WHERE expiration_date IS NOT NULL
                AND expiration_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
                AND document_status = 'active'
                ORDER BY expiration_date ASC
                LIMIT 10
            ");
            $stmt->execute();
            $report['expiring_documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'report' => $report
            ];

        } catch (PDOException $e) {
            error_log("Failed to generate compliance report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate report'
            ];
        }
    }

    /**
     * Archive expired documents
     */
    public function archiveExpiredDocuments(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE legal_documents
                SET document_status = 'archived',
                    updated_at = NOW()
                WHERE expiration_date < NOW()
                AND document_status = 'active'
            ");

            $stmt->execute();
            $archivedCount = $stmt->rowCount();

            // Log archiving
            $this->logDocumentAudit(null, 'system', 'documents_archived', [
                'archived_count' => $archivedCount,
                'archive_date' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'archived_count' => $archivedCount,
                'message' => "Successfully archived {$archivedCount} expired documents"
            ];

        } catch (PDOException $e) {
            error_log("Failed to archive expired documents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to archive documents'
            ];
        }
    }

    // Private helper methods

    private function generateDocumentId(): string
    {
        return 'doc_' . uniqid() . '_' . time();
    }

    private function generateSignatureId(): string
    {
        return 'sig_' . uniqid() . '_' . time();
    }

    private function validateDocumentData(array $data): void
    {
        $required = ['title', 'type', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $validTypes = ['contract', 'agreement', 'policy', 'regulation', 'license', 'permit', 'complaint', 'other'];
        if (!in_array($data['type'], $validTypes)) {
            throw new Exception("Invalid document type: {$data['type']}");
        }
    }

    private function validateSignatureRequest(string $documentId, array $signerData): void
    {
        if (empty($signerData['signer_id'])) {
            throw new Exception('Signer ID is required');
        }

        // Check if document exists and requires signature
        $document = $this->getDocumentById($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }

        if (!$document['requires_signature']) {
            throw new Exception('Document does not require signatures');
        }
    }

    private function processDocumentFile(string $filePath): array
    {
        // Implementation would process uploaded file
        // This is a placeholder
        return [
            'path' => $filePath,
            'size' => filesize($filePath),
            'hash' => hash_file('sha256', $filePath),
            'mime_type' => mime_content_type($filePath)
        ];
    }

    private function createDocumentVersion(string $documentId, string $version, string $createdBy, string $filePath = null, string $changes = null): void
    {
        $versionId = 'ver_' . uniqid() . '_' . time();

        $stmt = $this->pdo->prepare("
            INSERT INTO document_versions
            (version_id, document_id, version_number, created_by, file_path,
             file_size, file_hash, changes_description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $fileInfo = $filePath ? $this->processDocumentFile($filePath) : null;

        $stmt->execute([
            $versionId,
            $documentId,
            $version,
            $createdBy,
            $filePath,
            $fileInfo['size'] ?? null,
            $fileInfo['hash'] ?? null,
            $changes
        ]);
    }

    private function incrementVersion(string $currentVersion, bool $isMajor = false): string
    {
        $parts = explode('.', $currentVersion);
        if ($isMajor) {
            $parts[0] = (int)$parts[0] + 1;
            $parts[1] = 0;
            $parts[2] = 0;
        } else {
            $parts[2] = (int)$parts[2] + 1;
        }

        return implode('.', $parts);
    }

    private function getDocumentById(string $documentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM legal_documents WHERE document_id = ?
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getSignatureById(string $signatureId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM document_signatures WHERE signature_id = ?
        ");
        $stmt->execute([$signatureId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function hasDocumentPermission(string $documentId, string $userId, string $permission): bool
    {
        // Implementation would check user permissions
        // This is a placeholder
        return true;
    }

    private function startApprovalWorkflow(string $documentId, array $documentData): void
    {
        // Implementation would start approval workflow
        // This is a placeholder
    }

    private function updateDocumentSignatures(string $documentId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE legal_documents
            SET signed_by = (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'signer_id', signer_id,
                        'signed_at', signed_at,
                        'signature_hash', signature_hash
                    )
                )
                FROM document_signatures
                WHERE document_id = ? AND signature_status = 'signed'
            )
            WHERE document_id = ?
        ");
        $stmt->execute([$documentId, $documentId]);
    }

    private function checkDocumentSignaturesComplete(string $documentId): void
    {
        // Implementation would check if all required signatures are complete
        // This is a placeholder
    }

    private function generateSignatureHash(array $signatureData): string
    {
        return hash('sha256', json_encode($signatureData) . time());
    }

    private function getSearchTotal(array $criteria): int
    {
        // Implementation would return total count for search results
        // This is a placeholder
        return 0;
    }

    private function sendSignatureRequestNotification(string $signatureId, array $signerData): void
    {
        // Implementation would send signature request notification
        // This is a placeholder
    }

    private function logDocumentAudit(?string $documentId, ?string $userId, string $action, array $data = []): void
    {
        try {
            $auditId = 'audit_' . uniqid() . '_' . time();

            $stmt = $this->pdo->prepare("
                INSERT INTO document_audit_log
                (audit_id, document_id, user_id, action_type, action_description,
                 old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $auditId,
                $documentId,
                $userId,
                $action,
                $data['action_description'] ?? null,
                isset($data['old_values']) ? json_encode($data['old_values']) : null,
                isset($data['new_values']) ? json_encode($data['new_values']) : null,
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null
            ]);

        } catch (PDOException $e) {
            error_log("Failed to log document audit: " . $e->getMessage());
        }
    }
}

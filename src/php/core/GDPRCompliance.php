<?php
/**
 * TPT Government Platform - GDPR Compliance Manager
 *
 * Handles GDPR compliance features including data subject rights,
 * consent management, data processing records, and privacy controls.
 */

namespace TPT\Core;

class GDPRCompliance
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var array GDPR configuration
     */
    private array $config;

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
            'retention_period' => 2555, // 7 years in days
            'consent_validity' => 365, // 1 year in days
            'data_controller' => 'TPT Government Platform',
            'data_protection_officer' => 'dpo@gov.local',
            'automated_decisions' => false,
            'international_transfers' => false
        ], $config);
    }

    /**
     * Record data processing activity
     *
     * @param int $userId
     * @param string $purpose
     * @param string $dataCategories
     * @param string $processingType
     * @param array $metadata
     * @return bool
     */
    public function recordDataProcessing(int $userId, string $purpose, string $dataCategories, string $processingType, array $metadata = []): bool
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO gdpr_data_processing (
                    user_id, purpose, data_categories, processing_type,
                    legal_basis, metadata, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([
                $userId,
                $purpose,
                $dataCategories,
                $processingType,
                $metadata['legal_basis'] ?? 'consent',
                json_encode($metadata)
            ]);
        } catch (\Exception $e) {
            error_log('GDPR Data Processing Recording Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle data subject access request (DSAR)
     *
     * @param int $userId
     * @return array
     */
    public function handleDataAccessRequest(int $userId): array
    {
        try {
            // Get user profile data
            $userData = $this->database->selectOne("
                SELECT id, email, first_name, last_name, phone, department,
                       position, created_at, updated_at
                FROM users WHERE id = ?
            ", [$userId]);

            if (!$userData) {
                return ['success' => false, 'message' => 'User not found'];
            }

            // Get data processing history
            $processingHistory = $this->database->select("
                SELECT purpose, data_categories, processing_type,
                       legal_basis, created_at
                FROM gdpr_data_processing
                WHERE user_id = ?
                ORDER BY created_at DESC
            ", [$userId]);

            // Get consent records
            $consents = $this->database->select("
                SELECT consent_type, granted, consent_date, expiry_date,
                       consent_text, ip_address
                FROM gdpr_consents
                WHERE user_id = ?
                ORDER BY consent_date DESC
            ", [$userId]);

            // Get audit logs (anonymized)
            $auditLogs = $this->database->select("
                SELECT action, resource_type, created_at
                FROM audit_logs
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)
                ORDER BY created_at DESC
                LIMIT 100
            ", [$userId]);

            return [
                'success' => true,
                'data' => [
                    'profile' => $userData,
                    'processing_history' => $processingHistory,
                    'consents' => $consents,
                    'audit_summary' => $auditLogs,
                    'export_date' => date('c'),
                    'data_controller' => $this->config['data_controller']
                ]
            ];

        } catch (\Exception $e) {
            error_log('GDPR Data Access Request Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing request'];
        }
    }

    /**
     * Handle data portability request
     *
     * @param int $userId
     * @return array
     */
    public function handleDataPortabilityRequest(int $userId): array
    {
        $accessResult = $this->handleDataAccessRequest($userId);

        if (!$accessResult['success']) {
            return $accessResult;
        }

        try {
            // Generate machine-readable export
            $exportData = [
                'metadata' => [
                    'export_date' => date('c'),
                    'data_controller' => $this->config['data_controller'],
                    'data_subject_id' => $userId,
                    'gdpr_version' => 'GDPR Article 20',
                    'format' => 'JSON'
                ],
                'data' => $accessResult['data']
            ];

            // Store export record
            $stmt = $this->database->prepare("
                INSERT INTO gdpr_data_exports (
                    user_id, export_data, export_date, status
                ) VALUES (?, ?, NOW(), 'completed')
            ");

            $stmt->execute([
                $userId,
                json_encode($exportData),
                date('c')
            ]);

            return [
                'success' => true,
                'export_id' => $this->database->lastInsertId(),
                'data' => $exportData,
                'download_url' => "/api/gdpr/export/{$this->database->lastInsertId()}"
            ];

        } catch (\Exception $e) {
            error_log('GDPR Data Portability Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating export'];
        }
    }

    /**
     * Handle data erasure request (Right to be Forgotten)
     *
     * @param int $userId
     * @param string $reason
     * @return array
     */
    public function handleDataErasureRequest(int $userId, string $reason): array
    {
        try {
            // Start transaction
            $this->database->beginTransaction();

            // Check if user exists
            $user = $this->database->selectOne("SELECT id, email FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                $this->database->rollBack();
                return ['success' => false, 'message' => 'User not found'];
            }

            // Record erasure request
            $stmt = $this->database->prepare("
                INSERT INTO gdpr_erasure_requests (
                    user_id, user_email, reason, status, requested_at
                ) VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $user['email'], $reason]);
            $requestId = $this->database->lastInsertId();

            // Anonymize user data (don't delete, anonymize for legal/compliance reasons)
            $this->anonymizeUserData($userId);

            // Mark erasure as completed
            $stmt = $this->database->prepare("
                UPDATE gdpr_erasure_requests
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$requestId]);

            $this->database->commit();

            return [
                'success' => true,
                'message' => 'Data erasure request processed successfully',
                'request_id' => $requestId,
                'erasure_date' => date('c')
            ];

        } catch (\Exception $e) {
            $this->database->rollBack();
            error_log('GDPR Data Erasure Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing erasure request'];
        }
    }

    /**
     * Anonymize user data for erasure
     *
     * @param int $userId
     * @return void
     */
    private function anonymizeUserData(int $userId): void
    {
        // Anonymize personal data
        $this->database->execute("
            UPDATE users SET
                first_name = CONCAT('User_', id),
                last_name = CONCAT('Deleted_', id),
                email = CONCAT('deleted_', id, '@anonymized.local'),
                phone = NULL,
                department = 'Anonymized',
                position = 'Anonymized',
                last_login_at = NULL,
                email_verified_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ", [$userId]);

        // Anonymize audit logs
        $this->database->execute("
            UPDATE audit_logs SET
                old_values = NULL,
                new_values = NULL,
                ip_address = '0.0.0.0',
                user_agent = 'Anonymized'
            WHERE user_id = ?
        ", [$userId]);

        // Remove sensitive notifications
        $this->database->execute("
            DELETE FROM notifications
            WHERE user_id = ? AND type IN ('personal', 'sensitive')
        ", [$userId]);

        // Anonymize webhook data
        $this->database->execute("
            UPDATE webhooks SET
                secret = 'anonymized'
            WHERE created_by = ?
        ", [$userId]);
    }

    /**
     * Handle consent management
     *
     * @param int $userId
     * @param string $consentType
     * @param bool $granted
     * @param string $consentText
     * @return bool
     */
    public function manageConsent(int $userId, string $consentType, bool $granted, string $consentText): bool
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO gdpr_consents (
                    user_id, consent_type, granted, consent_date,
                    expiry_date, consent_text, ip_address
                ) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?)
                ON DUPLICATE KEY UPDATE
                    granted = VALUES(granted),
                    consent_date = NOW(),
                    expiry_date = DATE_ADD(NOW(), INTERVAL ? DAY),
                    consent_text = VALUES(consent_text)
            ");

            return $stmt->execute([
                $userId,
                $consentType,
                $granted,
                $this->config['consent_validity'],
                $consentText,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $this->config['consent_validity']
            ]);
        } catch (\Exception $e) {
            error_log('GDPR Consent Management Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has given consent
     *
     * @param int $userId
     * @param string $consentType
     * @return bool
     */
    public function hasConsent(int $userId, string $consentType): bool
    {
        try {
            $consent = $this->database->selectOne("
                SELECT granted, expiry_date
                FROM gdpr_consents
                WHERE user_id = ? AND consent_type = ?
                ORDER BY consent_date DESC
                LIMIT 1
            ", [$userId, $consentType]);

            if (!$consent) {
                return false;
            }

            // Check if consent is still valid
            $expiryDate = strtotime($consent['expiry_date']);
            $currentTime = time();

            return $consent['granted'] && $currentTime < $expiryDate;
        } catch (\Exception $e) {
            error_log('GDPR Consent Check Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle data rectification request
     *
     * @param int $userId
     * @param array $corrections
     * @return array
     */
    public function handleDataRectification(int $userId, array $corrections): array
    {
        try {
            $this->database->beginTransaction();

            // Record rectification request
            $stmt = $this->database->prepare("
                INSERT INTO gdpr_rectification_requests (
                    user_id, requested_changes, status, requested_at
                ) VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, json_encode($corrections)]);

            // Apply corrections
            $updateFields = [];
            $updateValues = [];

            foreach ($corrections as $field => $value) {
                // Only allow specific fields to be corrected
                $allowedFields = ['first_name', 'last_name', 'phone', 'department', 'position'];
                if (in_array($field, $allowedFields)) {
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $value;
                }
            }

            if (!empty($updateFields)) {
                $updateValues[] = $userId;
                $this->database->execute("
                    UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW()
                    WHERE id = ?
                ", $updateValues);
            }

            // Mark as completed
            $stmt = $this->database->prepare("
                UPDATE gdpr_rectification_requests
                SET status = 'completed', completed_at = NOW()
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$userId]);

            $this->database->commit();

            return [
                'success' => true,
                'message' => 'Data rectification completed successfully',
                'corrected_fields' => array_keys($corrections)
            ];

        } catch (\Exception $e) {
            $this->database->rollBack();
            error_log('GDPR Data Rectification Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing rectification request'];
        }
    }

    /**
     * Handle data processing restriction request
     *
     * @param int $userId
     * @param bool $restrict
     * @param string $reason
     * @return array
     */
    public function handleProcessingRestriction(int $userId, bool $restrict, string $reason): array
    {
        try {
            $stmt = $this->database->prepare("
                INSERT INTO gdpr_processing_restrictions (
                    user_id, restriction_type, reason, status, created_at
                ) VALUES (?, 'general', ?, 'active', NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    reason = VALUES(reason),
                    created_at = NOW()
            ");

            $stmt->execute([$userId, $reason]);

            // Update user processing restriction status
            $this->database->execute("
                UPDATE users SET
                    processing_restricted = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [$restrict, $userId]);

            return [
                'success' => true,
                'message' => $restrict ? 'Data processing restricted' : 'Data processing restriction lifted',
                'restriction_date' => date('c')
            ];

        } catch (\Exception $e) {
            error_log('GDPR Processing Restriction Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing restriction request'];
        }
    }

    /**
     * Generate privacy policy content
     *
     * @return array
     */
    public function generatePrivacyPolicy(): array
    {
        return [
            'data_controller' => $this->config['data_controller'],
            'contact_email' => $this->config['data_protection_officer'],
            'last_updated' => date('c'),
            'sections' => [
                'data_collection' => [
                    'title' => 'Data We Collect',
                    'content' => 'We collect personal information necessary for government service delivery...'
                ],
                'data_usage' => [
                    'title' => 'How We Use Your Data',
                    'content' => 'Your data is used to provide government services, ensure compliance...'
                ],
                'data_sharing' => [
                    'title' => 'Data Sharing and Third Parties',
                    'content' => 'We may share data with authorized government agencies...'
                ],
                'data_retention' => [
                    'title' => 'Data Retention',
                    'content' => "We retain your data for {$this->config['retention_period']} days as required by law..."
                ],
                'your_rights' => [
                    'title' => 'Your Rights',
                    'content' => 'You have the right to access, rectify, erase, and port your data...'
                ],
                'contact_us' => [
                    'title' => 'Contact Us',
                    'content' => 'For privacy-related inquiries, contact our Data Protection Officer...'
                ]
            ]
        ];
    }

    /**
     * Check data retention compliance
     *
     * @return array
     */
    public function checkDataRetentionCompliance(): array
    {
        try {
            // Find data older than retention period
            $oldData = $this->database->select("
                SELECT id, email, created_at
                FROM users
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND processing_restricted = 0
                LIMIT 100
            ", [$this->config['retention_period']]);

            return [
                'success' => true,
                'records_to_review' => count($oldData),
                'retention_period_days' => $this->config['retention_period'],
                'sample_records' => array_slice($oldData, 0, 5)
            ];

        } catch (\Exception $e) {
            error_log('GDPR Retention Check Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error checking retention compliance'];
        }
    }

    /**
     * Generate GDPR compliance report
     *
     * @return array
     */
    public function generateComplianceReport(): array
    {
        try {
            $report = [
                'generated_at' => date('c'),
                'data_controller' => $this->config['data_controller'],
                'compliance_status' => []
            ];

            // Check consent compliance
            $expiredConsents = $this->database->selectOne("
                SELECT COUNT(*) as count
                FROM gdpr_consents
                WHERE expiry_date < NOW()
            ");
            $report['compliance_status']['expired_consents'] = $expiredConsents['count'];

            // Check data processing records
            $processingRecords = $this->database->selectOne("
                SELECT COUNT(*) as count
                FROM gdpr_data_processing
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ");
            $report['compliance_status']['processing_records_last_year'] = $processingRecords['count'];

            // Check erasure requests
            $pendingErasures = $this->database->selectOne("
                SELECT COUNT(*) as count
                FROM gdpr_erasure_requests
                WHERE status = 'pending'
            ");
            $report['compliance_status']['pending_erasure_requests'] = $pendingErasures['count'];

            // Check data retention
            $retentionCheck = $this->checkDataRetentionCompliance();
            $report['compliance_status']['data_retention_compliance'] = $retentionCheck;

            return [
                'success' => true,
                'report' => $report
            ];

        } catch (\Exception $e) {
            error_log('GDPR Compliance Report Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating compliance report'];
        }
    }
}

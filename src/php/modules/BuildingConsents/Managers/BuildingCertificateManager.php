<?php
/**
 * TPT Government Platform - Building Certificate Manager
 *
 * Handles building certificate issuance and management
 */

namespace Modules\BuildingConsents\Managers;

use Core\Database;
use Core\NotificationManager;
use Exception;

class BuildingCertificateManager
{
    private Database $db;
    private NotificationManager $notificationManager;
    private array $certificateTypes;

    public function __construct(Database $db, NotificationManager $notificationManager)
    {
        $this->db = $db;
        $this->notificationManager = $notificationManager;
        $this->initializeCertificateTypes();
    }

    /**
     * Issue building certificate
     */
    public function issueCertificate(string $applicationId, string $certificateType, array $certificateData = []): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        if ($application['status'] !== 'approved') {
            return [
                'success' => false,
                'error' => 'Application must be approved before certificate can be issued'
            ];
        }

        // Check if all requirements are met for certificate issuance
        $requirementsCheck = $this->checkCertificateRequirements($applicationId, $certificateType);
        if (!$requirementsCheck['met']) {
            return [
                'success' => false,
                'error' => 'Certificate requirements not met',
                'missing_requirements' => $requirementsCheck['missing']
            ];
        }

        // Generate certificate number
        $certificateNumber = $this->generateCertificateNumber($certificateType);

        // Calculate expiry date (if applicable)
        $expiryDate = $this->calculateCertificateExpiry($certificateType);

        // Create certificate record
        $certificate = [
            'application_id' => $applicationId,
            'certificate_type' => $certificateType,
            'certificate_number' => $certificateNumber,
            'issue_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $expiryDate,
            'issued_by' => $certificateData['issued_by'] ?? null,
            'conditions' => $certificateData['conditions'] ?? [],
            'limitations' => $certificateData['limitations'] ?? '',
            'status' => 'active'
        ];

        // Save to database
        if (!$this->saveCertificate($certificate)) {
            return [
                'success' => false,
                'error' => 'Failed to save certificate'
            ];
        }

        // Send notification
        $this->sendNotification('certificate_issued', $application['applicant_id'], [
            'application_id' => $applicationId,
            'certificate_number' => $certificateNumber,
            'certificate_type' => $certificateType
        ]);

        return [
            'success' => true,
            'certificate_number' => $certificateNumber,
            'certificate_type' => $certificateType,
            'issue_date' => $certificate['issue_date'],
            'expiry_date' => $expiryDate,
            'message' => 'Building certificate issued'
        ];
    }

    /**
     * Get building certificates
     */
    public function getCertificates(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM building_certificates WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['certificate_type'])) {
                $sql .= " AND certificate_type = ?";
                $params[] = $filters['certificate_type'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY issue_date DESC";

            $results = $this->db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting certificates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve certificates'
            ];
        }
    }

    /**
     * Get building certificate
     */
    public function getCertificate(string $certificateNumber): ?array
    {
        try {
            $sql = "SELECT * FROM building_certificates WHERE certificate_number = ?";
            $result = $this->db->fetch($sql, [$certificateNumber]);

            if ($result) {
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error getting building certificate: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Revoke building certificate
     */
    public function revokeCertificate(string $certificateNumber, string $reason): array
    {
        $certificate = $this->getCertificate($certificateNumber);
        if (!$certificate) {
            return [
                'success' => false,
                'error' => 'Certificate not found'
            ];
        }

        if ($certificate['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Certificate is not active'
            ];
        }

        // Update certificate
        if (!$this->updateCertificate($certificateNumber, [
            'status' => 'revoked',
            'revocation_reason' => $reason
        ])) {
            return [
                'success' => false,
                'error' => 'Failed to revoke certificate'
            ];
        }

        // Send notification
        $application = $this->getApplication($certificate['application_id']);
        $this->sendNotification('certificate_revoked', $application['applicant_id'], [
            'certificate_number' => $certificateNumber,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => 'Certificate revoked successfully'
        ];
    }

    /**
     * Renew building certificate
     */
    public function renewCertificate(string $certificateNumber, array $renewalData = []): array
    {
        $certificate = $this->getCertificate($certificateNumber);
        if (!$certificate) {
            return [
                'success' => false,
                'error' => 'Certificate not found'
            ];
        }

        if ($certificate['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Certificate is not active'
            ];
        }

        // Check if renewal is allowed
        if (!$this->isRenewalAllowed($certificate)) {
            return [
                'success' => false,
                'error' => 'Certificate renewal not allowed'
            ];
        }

        // Generate new certificate number for renewal
        $newCertificateNumber = $this->generateCertificateNumber($certificate['certificate_type']);

        // Calculate new expiry date
        $newExpiryDate = $this->calculateCertificateExpiry($certificate['certificate_type']);

        // Create renewal certificate
        $renewalCertificate = [
            'application_id' => $certificate['application_id'],
            'certificate_type' => $certificate['certificate_type'],
            'certificate_number' => $newCertificateNumber,
            'issue_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $newExpiryDate,
            'issued_by' => $renewalData['issued_by'] ?? null,
            'conditions' => $renewalData['conditions'] ?? $certificate['conditions'],
            'limitations' => $renewalData['limitations'] ?? '',
            'status' => 'active'
        ];

        // Save renewal certificate
        if (!$this->saveCertificate($renewalCertificate)) {
            return [
                'success' => false,
                'error' => 'Failed to save renewal certificate'
            ];
        }

        // Update original certificate status
        $this->updateCertificate($certificateNumber, [
            'status' => 'superseded'
        ]);

        // Send notification
        $application = $this->getApplication($certificate['application_id']);
        $this->sendNotification('certificate_renewed', $application['applicant_id'], [
            'old_certificate' => $certificateNumber,
            'new_certificate' => $newCertificateNumber,
            'expiry_date' => $newExpiryDate
        ]);

        return [
            'success' => true,
            'old_certificate' => $certificateNumber,
            'new_certificate' => $newCertificateNumber,
            'expiry_date' => $newExpiryDate,
            'message' => 'Certificate renewed successfully'
        ];
    }

    /**
     * Get expiring certificates
     */
    public function getExpiringCertificates(int $daysAhead = 30): array
    {
        try {
            $sql = "SELECT * FROM building_certificates
                    WHERE status = 'active'
                    AND expiry_date IS NOT NULL
                    AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                    ORDER BY expiry_date ASC";

            $results = $this->db->fetchAll($sql, [$daysAhead]);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting expiring certificates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve expiring certificates'
            ];
        }
    }

    /**
     * Get expired certificates
     */
    public function getExpiredCertificates(): array
    {
        try {
            $sql = "SELECT * FROM building_certificates
                    WHERE status = 'active'
                    AND expiry_date IS NOT NULL
                    AND expiry_date < NOW()
                    ORDER BY expiry_date DESC";

            $results = $this->db->fetchAll($sql);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting expired certificates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve expired certificates'
            ];
        }
    }

    /**
     * Update expired certificates status
     */
    public function updateExpiredCertificates(): array
    {
        try {
            $expiredCertificates = $this->getExpiredCertificates();

            if (empty($expiredCertificates['data'])) {
                return [
                    'success' => true,
                    'message' => 'No expired certificates to update',
                    'updated_count' => 0
                ];
            }

            $updatedCount = 0;
            foreach ($expiredCertificates['data'] as $certificate) {
                $this->updateCertificate($certificate['certificate_number'], [
                    'status' => 'expired'
                ]);
                $updatedCount++;
            }

            return [
                'success' => true,
                'message' => 'Expired certificates updated successfully',
                'updated_count' => $updatedCount
            ];
        } catch (Exception $e) {
            error_log("Error updating expired certificates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update expired certificates'
            ];
        }
    }

    /**
     * Generate certificate report
     */
    public function generateCertificateReport(array $filters = []): array
    {
        try {
            $sql = "SELECT
                        certificate_type,
                        status,
                        COUNT(*) as count,
                        DATE_FORMAT(issue_date, '%Y-%m') as issue_month
                    FROM building_certificates
                    WHERE 1=1";

            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND issue_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND issue_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['certificate_type'])) {
                $sql .= " AND certificate_type = ?";
                $params[] = $filters['certificate_type'];
            }

            $sql .= " GROUP BY certificate_type, status, DATE_FORMAT(issue_date, '%Y-%m')
                     ORDER BY issue_month DESC, certificate_type";

            $results = $this->db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'filters' => $filters,
                'generated_at' => date('c')
            ];
        } catch (Exception $e) {
            error_log("Error generating certificate report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate certificate report'
            ];
        }
    }

    /**
     * Validate certificate data
     */
    private function validateCertificateData(array $data): array
    {
        $errors = [];

        $requiredFields = ['application_id', 'certificate_type'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate certificate type
        if (!isset($this->certificateTypes[$data['certificate_type'] ?? ''])) {
            $errors[] = "Invalid certificate type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check certificate requirements
     */
    private function checkCertificateRequirements(string $applicationId, string $certificateType): array
    {
        $certificateTypeDetails = $this->certificateTypes[$certificateType] ?? null;

        if (!$certificateTypeDetails) {
            return [
                'met' => false,
                'missing' => ['Invalid certificate type']
            ];
        }

        $requirements = $certificateTypeDetails['requirements'] ?? [];
        $missing = [];

        foreach ($requirements as $requirement) {
            if (!$this->checkRequirement($applicationId, $requirement)) {
                $missing[] = $requirement;
            }
        }

        return [
            'met' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Check specific requirement
     */
    private function checkRequirement(string $applicationId, string $requirement): bool
    {
        switch ($requirement) {
            case 'all_inspections_passed':
                return $this->areAllInspectionsPassed($applicationId);
            case 'code_compliance_verified':
                return $this->isCodeComplianceVerified($applicationId);
            case 'final_inspection_completed':
                return $this->isFinalInspectionCompleted($applicationId);
            case 'fees_paid':
                return $this->areFeesPaid($applicationId);
            default:
                return true; // Assume requirement is met if unknown
        }
    }

    /**
     * Check if all inspections are passed
     */
    private function areAllInspectionsPassed(string $applicationId): bool
    {
        try {
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN result = 'pass' THEN 1 ELSE 0 END) as passed
                    FROM building_inspections
                    WHERE application_id = ? AND status = 'completed'";

            $result = $this->db->fetch($sql, [$applicationId]);

            return $result && $result['total'] > 0 && $result['total'] === $result['passed'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if code compliance is verified
     */
    private function isCodeComplianceVerified(string $applicationId): bool
    {
        // This would check for compliance verification records
        // For now, return true as placeholder
        return true;
    }

    /**
     * Check if final inspection is completed
     */
    private function isFinalInspectionCompleted(string $applicationId): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM building_inspections
                    WHERE application_id = ? AND inspection_type = 'final'
                    AND status = 'completed' AND result = 'pass'";

            $result = $this->db->fetch($sql, [$applicationId]);

            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if fees are paid
     */
    private function areFeesPaid(string $applicationId): bool
    {
        try {
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid
                    FROM building_fees WHERE application_id = ?";

            $result = $this->db->fetch($sql, [$applicationId]);

            return $result && $result['total'] > 0 && $result['total'] === $result['paid'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Save building certificate
     */
    private function saveCertificate(array $certificate): bool
    {
        try {
            $sql = "INSERT INTO building_certificates (
                application_id, certificate_type, certificate_number,
                issue_date, expiry_date, conditions, limitations, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $certificate['application_id'],
                $certificate['certificate_type'],
                $certificate['certificate_number'],
                $certificate['issue_date'],
                $certificate['expiry_date'],
                json_encode($certificate['conditions']),
                $certificate['limitations'],
                $certificate['status']
            ];

            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error saving building certificate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update certificate
     */
    private function updateCertificate(string $certificateNumber, array $data): bool
    {
        try {
            $setParts = [];
            $params = [];

            foreach ($data as $field => $value) {
                if (is_array($value)) {
                    $setParts[] = "{$field} = ?";
                    $params[] = json_encode($value);
                } else {
                    $setParts[] = "{$field} = ?";
                    $params[] = $value;
                }
            }

            $params[] = $certificateNumber;

            $sql = "UPDATE building_certificates SET " . implode(', ', $setParts) . " WHERE certificate_number = ?";
            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error updating certificate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get application
     */
    private function getApplication(string $applicationId): ?array
    {
        try {
            $sql = "SELECT * FROM building_consent_applications WHERE application_id = ?";
            return $this->db->fetch($sql, [$applicationId]);
        } catch (Exception $e) {
            error_log("Error getting application: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate certificate number
     */
    private function generateCertificateNumber(string $certificateType): string
    {
        $prefix = match($certificateType) {
            'code_compliance' => 'CC',
            'completion' => 'CO',
            'occupancy' => 'OC',
            'compliance_schedule' => 'CS',
            default => 'BC'
        };

        return $prefix . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate certificate expiry
     */
    private function calculateCertificateExpiry(string $certificateType): ?string
    {
        $certificateTypeDetails = $this->certificateTypes[$certificateType] ?? null;

        if (!$certificateTypeDetails || !isset($certificateTypeDetails['validity_years'])) {
            return null;
        }

        $validityYears = $certificateTypeDetails['validity_years'];
        return date('Y-m-d H:i:s', strtotime("+{$validityYears} years"));
    }

    /**
     * Check if renewal is allowed
     */
    private function isRenewalAllowed(array $certificate): bool
    {
        $certificateTypeDetails = $this->certificateTypes[$certificate['certificate_type']] ?? null;

        if (!$certificateTypeDetails) {
            return false;
        }

        // Check if certificate type allows renewal
        return $certificateTypeDetails['renewable'] ?? false;
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, int $userId, array $data = []): bool
    {
        try {
            return $this->notificationManager->sendNotification($type, $userId, $data);
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize certificate types
     */
    private function initializeCertificateTypes(): void
    {
        $this->certificateTypes = [
            'code_compliance' => [
                'name' => 'Code Compliance Certificate',
                'description' => 'Certificate confirming compliance with building code',
                'validity_years' => 10,
                'renewable' => true,
                'requirements' => [
                    'all_inspections_passed',
                    'code_compliance_verified',
                    'final_inspection_completed',
                    'fees_paid'
                ]
            ],
            'completion' => [
                'name' => 'Completion Certificate',
                'description' => 'Certificate confirming project completion',
                'validity_years' => null, // No expiry
                'renewable' => false,
                'requirements' => [
                    'all_inspections_passed',
                    'final_inspection_completed',
                    'fees_paid'
                ]
            ],
            'occupancy' => [
                'name' => 'Certificate of Occupancy',
                'description' => 'Certificate allowing building occupancy',
                'validity_years' => null, // No expiry
                'renewable' => false,
                'requirements' => [
                    'all_inspections_passed',
                    'final_inspection_completed',
                    'code_compliance_verified',
                    'fees_paid'
                ]
            ],
            'compliance_schedule' => [
                'name' => 'Compliance Schedule Certificate',
                'description' => 'Certificate for compliance schedule requirements',
                'validity_years' => 5,
                'renewable' => true,
                'requirements' => [
                    'code_compliance_verified',
                    'fees_paid'
                ]
            ]
        ];
    }

    /**
     * Get certificate types
     */
    public function getCertificateTypes(): array
    {
        return $this->certificateTypes;
    }
}

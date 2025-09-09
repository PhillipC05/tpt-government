<?php
/**
 * TPT Government Platform - Building Compliance Manager
 *
 * Handles building compliance requirements, monitoring, and tracking
 */

namespace Modules\BuildingConsents\Managers;

use Core\Database;
use Core\NotificationManager;
use Exception;

class BuildingComplianceManager
{
    private Database $db;
    private NotificationManager $notificationManager;
    private array $complianceRequirements;

    public function __construct(Database $db, NotificationManager $notificationManager)
    {
        $this->db = $db;
        $this->notificationManager = $notificationManager;
        $this->initializeComplianceRequirements();
    }

    /**
     * Create compliance requirements for application
     */
    public function createComplianceRequirements(string $applicationId, string $consentType): bool
    {
        try {
            foreach ($this->complianceRequirements as $requirementType => $requirement) {
                $sql = "INSERT INTO building_compliance (
                    application_id, requirement_type, description, due_date
                ) VALUES (?, ?, ?, ?)";

                $dueDate = $this->calculateComplianceDueDate($requirement);

                $params = [
                    $applicationId,
                    $requirementType,
                    $requirement['description'],
                    $dueDate
                ];

                if (!$this->db->execute($sql, $params)) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error creating compliance requirements: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update compliance status
     */
    public function updateComplianceStatus(int $complianceId, string $status, array $evidence = []): array
    {
        $compliance = $this->getCompliance($complianceId);
        if (!$compliance) {
            return [
                'success' => false,
                'error' => 'Compliance requirement not found'
            ];
        }

        $updateData = [
            'status' => $status,
            'evidence' => $evidence
        ];

        if ($status === 'completed') {
            $updateData['completion_date'] = date('Y-m-d H:i:s');
        }

        if (!$this->updateCompliance($complianceId, $updateData)) {
            return [
                'success' => false,
                'error' => 'Failed to update compliance status'
            ];
        }

        // Send notification if status changed to overdue or completed
        if ($status === 'overdue' || $status === 'completed') {
            $application = $this->getApplication($compliance['application_id']);
            $this->sendNotification('compliance_status_changed', $application['applicant_id'], [
                'application_id' => $compliance['application_id'],
                'requirement_type' => $compliance['requirement_type'],
                'status' => $status
            ]);
        }

        return [
            'success' => true,
            'message' => 'Compliance status updated successfully'
        ];
    }

    /**
     * Get compliance requirements
     */
    public function getComplianceRequirements(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM building_compliance WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['requirement_type'])) {
                $sql .= " AND requirement_type = ?";
                $params[] = $filters['requirement_type'];
            }

            $sql .= " ORDER BY due_date ASC";

            $results = $this->db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting compliance requirements: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve compliance requirements'
            ];
        }
    }

    /**
     * Get compliance requirement
     */
    public function getCompliance(int $complianceId): ?array
    {
        try {
            $sql = "SELECT * FROM building_compliance WHERE id = ?";
            return $this->db->fetch($sql, [$complianceId]);
        } catch (Exception $e) {
            error_log("Error getting compliance requirement: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update compliance
     */
    public function updateCompliance(int $complianceId, array $data): bool
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

            $params[] = $complianceId;

            $sql = "UPDATE building_compliance SET " . implode(', ', $setParts) . " WHERE id = ?";
            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error updating compliance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get overdue compliance requirements
     */
    public function getOverdueCompliance(): array
    {
        try {
            $sql = "SELECT * FROM building_compliance
                    WHERE status IN ('pending', 'overdue')
                    AND due_date < CURDATE()
                    ORDER BY due_date ASC";

            $results = $this->db->fetchAll($sql);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting overdue compliance: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve overdue compliance'
            ];
        }
    }

    /**
     * Get compliance due soon
     */
    public function getComplianceDueSoon(int $daysAhead = 7): array
    {
        try {
            $sql = "SELECT * FROM building_compliance
                    WHERE status = 'pending'
                    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY due_date ASC";

            $results = $this->db->fetchAll($sql, [$daysAhead]);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting compliance due soon: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve compliance due soon'
            ];
        }
    }

    /**
     * Mark compliance as overdue
     */
    public function markComplianceOverdue(): array
    {
        try {
            $overdueItems = $this->getOverdueCompliance();

            if (!$overdueItems['success']) {
                return $overdueItems;
            }

            $updatedCount = 0;
            foreach ($overdueItems['data'] as $item) {
                if ($item['status'] === 'pending') {
                    $this->updateCompliance($item['id'], ['status' => 'overdue']);
                    $updatedCount++;
                }
            }

            return [
                'success' => true,
                'message' => 'Compliance items marked as overdue',
                'updated_count' => $updatedCount
            ];
        } catch (Exception $e) {
            error_log("Error marking compliance overdue: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to mark compliance overdue'
            ];
        }
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(array $filters = []): array
    {
        try {
            $sql = "SELECT
                        requirement_type,
                        status,
                        COUNT(*) as count,
                        DATE_FORMAT(created_at, '%Y-%m') as month
                    FROM building_compliance
                    WHERE 1=1";

            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['requirement_type'])) {
                $sql .= " AND requirement_type = ?";
                $params[] = $filters['requirement_type'];
            }

            $sql .= " GROUP BY requirement_type, status, DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month DESC, requirement_type";

            $results = $this->db->fetchAll($sql, $params);

            // Calculate compliance rates
            $complianceStats = $this->calculateComplianceStats($results);

            return [
                'success' => true,
                'data' => $results,
                'compliance_stats' => $complianceStats,
                'filters' => $filters,
                'generated_at' => date('c')
            ];
        } catch (Exception $e) {
            error_log("Error generating compliance report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate compliance report'
            ];
        }
    }

    /**
     * Calculate compliance statistics
     */
    private function calculateComplianceStats(array $data): array
    {
        $stats = [
            'total_requirements' => 0,
            'completed_requirements' => 0,
            'overdue_requirements' => 0,
            'pending_requirements' => 0,
            'compliance_rate' => 0
        ];

        foreach ($data as $item) {
            $stats['total_requirements'] += $item['count'];

            if ($item['status'] === 'completed') {
                $stats['completed_requirements'] += $item['count'];
            } elseif ($item['status'] === 'overdue') {
                $stats['overdue_requirements'] += $item['count'];
            } elseif ($item['status'] === 'pending') {
                $stats['pending_requirements'] += $item['count'];
            }
        }

        if ($stats['total_requirements'] > 0) {
            $stats['compliance_rate'] = round(
                ($stats['completed_requirements'] / $stats['total_requirements']) * 100,
                2
            );
        }

        return $stats;
    }

    /**
     * Send compliance reminder
     */
    public function sendComplianceReminder(int $complianceId): array
    {
        $compliance = $this->getCompliance($complianceId);
        if (!$compliance) {
            return [
                'success' => false,
                'error' => 'Compliance requirement not found'
            ];
        }

        // Send reminder notification
        $application = $this->getApplication($compliance['application_id']);
        $this->sendNotification('compliance_reminder', $application['applicant_id'], [
            'application_id' => $compliance['application_id'],
            'requirement_type' => $compliance['requirement_type'],
            'due_date' => $compliance['due_date'],
            'description' => $compliance['description']
        ]);

        // Log reminder
        $this->logComplianceAction($complianceId, 'reminder_sent', 'Compliance reminder sent');

        return [
            'success' => true,
            'message' => 'Compliance reminder sent successfully'
        ];
    }

    /**
     * Bulk send compliance reminders
     */
    public function sendBulkComplianceReminders(): array
    {
        $dueSoonItems = $this->getComplianceDueSoon(3); // 3 days

        if (!$dueSoonItems['success']) {
            return $dueSoonItems;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($dueSoonItems['data'] as $item) {
            $result = $this->sendComplianceReminder($item['id']);
            if ($result['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'success' => true,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'message' => "Sent {$sentCount} compliance reminders, {$failedCount} failed"
        ];
    }

    /**
     * Extend compliance due date
     */
    public function extendComplianceDueDate(int $complianceId, string $newDueDate, string $reason): array
    {
        $compliance = $this->getCompliance($complianceId);
        if (!$compliance) {
            return [
                'success' => false,
                'error' => 'Compliance requirement not found'
            ];
        }

        if (!$this->updateCompliance($complianceId, [
            'due_date' => $newDueDate,
            'notes' => ($compliance['notes'] ?? '') . "\nDue date extended to {$newDueDate}. Reason: {$reason}"
        ])) {
            return [
                'success' => false,
                'error' => 'Failed to extend due date'
            ];
        }

        // Log extension
        $this->logComplianceAction($complianceId, 'due_date_extended', $reason);

        return [
            'success' => true,
            'message' => 'Compliance due date extended successfully',
            'new_due_date' => $newDueDate
        ];
    }

    /**
     * Waive compliance requirement
     */
    public function waiveComplianceRequirement(int $complianceId, string $reason): array
    {
        $compliance = $this->getCompliance($complianceId);
        if (!$compliance) {
            return [
                'success' => false,
                'error' => 'Compliance requirement not found'
            ];
        }

        if ($compliance['status'] === 'completed') {
            return [
                'success' => false,
                'error' => 'Cannot waive completed requirement'
            ];
        }

        if (!$this->updateCompliance($complianceId, [
            'status' => 'waived',
            'notes' => ($compliance['notes'] ?? '') . "\nRequirement waived. Reason: {$reason}"
        ])) {
            return [
                'success' => false,
                'error' => 'Failed to waive requirement'
            ];
        }

        // Log waiver
        $this->logComplianceAction($complianceId, 'waived', $reason);

        return [
            'success' => true,
            'message' => 'Compliance requirement waived successfully'
        ];
    }

    /**
     * Get application compliance status
     */
    public function getApplicationComplianceStatus(string $applicationId): array
    {
        try {
            $sql = "SELECT
                        COUNT(*) as total_requirements,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requirements,
                        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_requirements,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requirements,
                        MIN(due_date) as next_due_date
                    FROM building_compliance
                    WHERE application_id = ?";

            $result = $this->db->fetch($sql, [$applicationId]);

            if ($result) {
                $result['compliance_percentage'] = $result['total_requirements'] > 0
                    ? round(($result['completed_requirements'] / $result['total_requirements']) * 100, 2)
                    : 0;

                $result['overall_status'] = $this->determineOverallComplianceStatus($result);
            }

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Error getting application compliance status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to get application compliance status'
            ];
        }
    }

    /**
     * Determine overall compliance status
     */
    private function determineOverallComplianceStatus(array $stats): string
    {
        if ($stats['overdue_requirements'] > 0) {
            return 'overdue';
        }

        if ($stats['pending_requirements'] > 0) {
            return 'pending';
        }

        if ($stats['completed_requirements'] === $stats['total_requirements']) {
            return 'compliant';
        }

        return 'in_progress';
    }

    /**
     * Log compliance action
     */
    private function logComplianceAction(int $complianceId, string $action, string $reason): void
    {
        try {
            // In a real implementation, this would log to an audit table
            $logData = [
                'compliance_id' => $complianceId,
                'action' => $action,
                'reason' => $reason,
                'timestamp' => date('c'),
                'user_id' => null // Would be set from session/auth
            ];

            error_log("Compliance action logged: " . json_encode($logData));
        } catch (Exception $e) {
            error_log("Error logging compliance action: " . $e->getMessage());
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
     * Calculate compliance due date
     */
    private function calculateComplianceDueDate(array $requirement): string
    {
        $baseDate = date('Y-m-d');

        if (isset($requirement['frequency'])) {
            if ($requirement['frequency'] === 'annual') {
                return date('Y-m-d', strtotime($baseDate . ' +' . ($requirement['due_month'] ?? 12) . ' months'));
            } elseif ($requirement['frequency'] === 'biennial') {
                return date('Y-m-d', strtotime($baseDate . ' +2 years +' . ($requirement['due_month'] ?? 12) . ' months'));
            }
        }

        return date('Y-m-d', strtotime($baseDate . '+1 year'));
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
     * Initialize compliance requirements
     */
    private function initializeComplianceRequirements(): void
    {
        $this->complianceRequirements = [
            'building_code' => [
                'name' => 'Building Code Compliance',
                'description' => 'Compliance with building code requirements',
                'frequency' => 'ongoing',
                'verification_method' => 'inspection',
                'due_month' => 12
            ],
            'resource_consent' => [
                'name' => 'Resource Consent Compliance',
                'description' => 'Compliance with resource consent conditions',
                'frequency' => 'ongoing',
                'verification_method' => 'documentation',
                'due_month' => 6
            ],
            'engineer_certification' => [
                'name' => 'Engineer Certification',
                'description' => 'Structural engineer certification',
                'frequency' => 'one_time',
                'verification_method' => 'documentation',
                'due_month' => 3
            ],
            'insurance' => [
                'name' => 'Construction Insurance',
                'description' => 'Required construction insurance coverage',
                'frequency' => 'annual',
                'verification_method' => 'documentation',
                'due_month' => 12
            ],
            'maintenance' => [
                'name' => 'Building Maintenance',
                'description' => 'Regular building maintenance compliance',
                'frequency' => 'annual',
                'verification_method' => 'inspection',
                'due_month' => 12
            ],
            'safety_audit' => [
                'name' => 'Safety Audit',
                'description' => 'Building safety audit compliance',
                'frequency' => 'biennial',
                'verification_method' => 'inspection',
                'due_month' => 6
            ]
        ];
    }



    /**
     * Update compliance requirement
     */
    public function updateComplianceRequirement(string $requirementType, array $requirement): bool
    {
        try {
            $this->complianceRequirements[$requirementType] = array_merge(
                $this->complianceRequirements[$requirementType] ?? [],
                $requirement
            );
            return true;
        } catch (Exception $e) {
            error_log("Error updating compliance requirement: " . $e->getMessage());
            return false;
        }
    }
}

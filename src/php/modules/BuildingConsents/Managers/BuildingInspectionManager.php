<?php
/**
 * TPT Government Platform - Building Inspection Manager
 *
 * Handles building inspection scheduling, completion, and results
 */

namespace Modules\BuildingConsents\Managers;

use Core\Database;
use Core\NotificationManager;
use Exception;

class BuildingInspectionManager
{
    private Database $db;
    private NotificationManager $notificationManager;
    private array $inspectionTypes;

    public function __construct(Database $db, NotificationManager $notificationManager)
    {
        $this->db = $db;
        $this->notificationManager = $notificationManager;
        $this->initializeInspectionTypes();
    }

    /**
     * Schedule building inspection
     */
    public function scheduleInspection(array $inspectionData): array
    {
        // Validate inspection data
        $validation = $this->validateInspectionData($inspectionData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check if inspection type is required for this application
        $consentType = $this->getApplicationConsentType($inspectionData['application_id']);
        if (!$this->isInspectionRequired($inspectionData['inspection_type'], $consentType)) {
            return [
                'success' => false,
                'error' => 'Inspection type not required for this consent type'
            ];
        }

        // Create inspection record
        $inspection = [
            'application_id' => $inspectionData['application_id'],
            'inspection_type' => $inspectionData['inspection_type'],
            'scheduled_date' => $inspectionData['preferred_date'] . ' ' . $inspectionData['preferred_time'],
            'status' => 'scheduled',
            'notes' => $inspectionData['special_requirements'] ?? ''
        ];

        // Save to database
        if (!$this->saveInspection($inspection)) {
            return [
                'success' => false,
                'error' => 'Failed to schedule inspection'
            ];
        }

        // Send notification
        $application = $this->getApplication($inspectionData['application_id']);
        $this->sendNotification('inspection_scheduled', $application['applicant_id'], [
            'application_id' => $inspectionData['application_id'],
            'scheduled_date' => $inspection['scheduled_date']
        ]);

        return [
            'success' => true,
            'message' => 'Building inspection scheduled',
            'scheduled_date' => $inspection['scheduled_date']
        ];
    }

    /**
     * Complete building inspection
     */
    public function completeInspection(int $inspectionId, array $inspectionResult): array
    {
        $inspection = $this->getInspection($inspectionId);
        if (!$inspection) {
            return [
                'success' => false,
                'error' => 'Inspection not found'
            ];
        }

        // Update inspection
        $updateData = [
            'status' => 'completed',
            'actual_date' => date('Y-m-d H:i:s'),
            'result' => $inspectionResult['result'],
            'findings' => $inspectionResult['findings'] ?? [],
            'recommendations' => $inspectionResult['recommendations'] ?? '',
            'follow_up_required' => $inspectionResult['follow_up_required'] ?? false,
            'notes' => $inspectionResult['notes'] ?? ''
        ];

        if (!$this->updateInspection($inspectionId, $updateData)) {
            return [
                'success' => false,
                'error' => 'Failed to update inspection'
            ];
        }

        // If follow-up required, schedule it
        if ($inspectionResult['follow_up_required']) {
            $this->scheduleFollowUpInspection($inspectionId, $inspectionResult['follow_up_date'] ?? null);
        }

        // Send notification
        $application = $this->getApplication($inspection['application_id']);
        $this->sendNotification('inspection_completed', $application['applicant_id'], [
            'application_id' => $inspection['application_id'],
            'result' => $inspectionResult['result']
        ]);

        return [
            'success' => true,
            'result' => $inspectionResult['result'],
            'message' => 'Building inspection completed'
        ];
    }

    /**
     * Get building inspections
     */
    public function getInspections(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM building_inspections WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['inspection_type'])) {
                $sql .= " AND inspection_type = ?";
                $params[] = $filters['inspection_type'];
            }

            $sql .= " ORDER BY scheduled_date DESC";

            $results = $this->db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting inspections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve inspections'
            ];
        }
    }

    /**
     * Get building inspection
     */
    public function getInspection(int $inspectionId): ?array
    {
        try {
            $sql = "SELECT * FROM building_inspections WHERE id = ?";
            $result = $this->db->fetch($sql, [$inspectionId]);

            if ($result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error getting building inspection: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update inspection
     */
    public function updateInspection(int $inspectionId, array $data): bool
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

            $params[] = $inspectionId;

            $sql = "UPDATE building_inspections SET " . implode(', ', $setParts) . " WHERE id = ?";
            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error updating inspection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel inspection
     */
    public function cancelInspection(int $inspectionId, string $reason): array
    {
        $inspection = $this->getInspection($inspectionId);
        if (!$inspection) {
            return [
                'success' => false,
                'error' => 'Inspection not found'
            ];
        }

        if ($inspection['status'] === 'completed') {
            return [
                'success' => false,
                'error' => 'Cannot cancel completed inspection'
            ];
        }

        if (!$this->updateInspection($inspectionId, [
            'status' => 'cancelled',
            'notes' => $inspection['notes'] . "\nCancellation reason: " . $reason
        ])) {
            return [
                'success' => false,
                'error' => 'Failed to cancel inspection'
            ];
        }

        return [
            'success' => true,
            'message' => 'Inspection cancelled successfully'
        ];
    }

    /**
     * Reschedule inspection
     */
    public function rescheduleInspection(int $inspectionId, string $newDate, string $newTime): array
    {
        $inspection = $this->getInspection($inspectionId);
        if (!$inspection) {
            return [
                'success' => false,
                'error' => 'Inspection not found'
            ];
        }

        if ($inspection['status'] === 'completed') {
            return [
                'success' => false,
                'error' => 'Cannot reschedule completed inspection'
            ];
        }

        $newScheduledDate = $newDate . ' ' . $newTime;

        if (!$this->updateInspection($inspectionId, [
            'scheduled_date' => $newScheduledDate,
            'status' => 'rescheduled',
            'notes' => $inspection['notes'] . "\nRescheduled from {$inspection['scheduled_date']} to {$newScheduledDate}"
        ])) {
            return [
                'success' => false,
                'error' => 'Failed to reschedule inspection'
            ];
        }

        return [
            'success' => true,
            'message' => 'Inspection rescheduled successfully',
            'new_date' => $newScheduledDate
        ];
    }

    /**
     * Schedule required inspections for application
     */
    public function scheduleRequiredInspections(string $applicationId, string $consentType): bool
    {
        try {
            $consentTypeDetails = $this->getConsentTypeDetails($consentType);
            $inspections = $consentTypeDetails['inspections_required'] ?? [];

            foreach ($inspections as $inspectionType) {
                $inspectionData = [
                    'application_id' => $applicationId,
                    'inspection_type' => $inspectionType,
                    'scheduled_date' => $this->calculateInspectionDate($inspectionType),
                    'status' => 'scheduled'
                ];

                if (!$this->saveInspection($inspectionData)) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error scheduling required inspections: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get inspection checklist
     */
    public function getInspectionChecklist(string $inspectionType): array
    {
        $inspectionTypeDetails = $this->inspectionTypes[$inspectionType] ?? null;

        if (!$inspectionTypeDetails) {
            return [
                'success' => false,
                'error' => 'Invalid inspection type'
            ];
        }

        return [
            'success' => true,
            'inspection_type' => $inspectionType,
            'checklist' => $inspectionTypeDetails['checklist'] ?? [],
            'estimated_duration' => $inspectionTypeDetails['estimated_duration'] ?? 60
        ];
    }

    /**
     * Get upcoming inspections
     */
    public function getUpcomingInspections(int $daysAhead = 7): array
    {
        try {
            $sql = "SELECT * FROM building_inspections
                    WHERE status IN ('scheduled', 'confirmed')
                    AND scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                    ORDER BY scheduled_date ASC";

            $results = $this->db->fetchAll($sql, [$daysAhead]);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting upcoming inspections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve upcoming inspections'
            ];
        }
    }

    /**
     * Get overdue inspections
     */
    public function getOverdueInspections(): array
    {
        try {
            $sql = "SELECT * FROM building_inspections
                    WHERE status IN ('scheduled', 'confirmed')
                    AND scheduled_date < NOW()
                    ORDER BY scheduled_date ASC";

            $results = $this->db->fetchAll($sql);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting overdue inspections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve overdue inspections'
            ];
        }
    }

    /**
     * Validate inspection data
     */
    private function validateInspectionData(array $data): array
    {
        $errors = [];

        $requiredFields = ['application_id', 'inspection_type', 'preferred_date', 'preferred_time'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate inspection type
        if (!isset($this->inspectionTypes[$data['inspection_type'] ?? ''])) {
            $errors[] = "Invalid inspection type";
        }

        // Validate date format
        if (isset($data['preferred_date']) && !strtotime($data['preferred_date'])) {
            $errors[] = "Invalid date format";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Save building inspection
     */
    private function saveInspection(array $inspection): bool
    {
        try {
            $sql = "INSERT INTO building_inspections (
                application_id, inspection_type, scheduled_date, status
            ) VALUES (?, ?, ?, ?)";

            $params = [
                $inspection['application_id'],
                $inspection['inspection_type'],
                $inspection['scheduled_date'],
                $inspection['status']
            ];

            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error saving building inspection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule follow-up inspection
     */
    private function scheduleFollowUpInspection(int $inspectionId, ?string $followUpDate): bool
    {
        try {
            $db = $this->db;

            if (!$followUpDate) {
                $followUpDate = date('Y-m-d H:i:s', strtotime('+1 week'));
            }

            $sql = "UPDATE building_inspections SET follow_up_required = TRUE, follow_up_date = ? WHERE id = ?";
            return $db->execute($sql, [$followUpDate, $inspectionId]);
        } catch (Exception $e) {
            error_log("Error scheduling follow-up inspection: " . $e->getMessage());
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
     * Get application consent type
     */
    private function getApplicationConsentType(string $applicationId): ?string
    {
        $application = $this->getApplication($applicationId);
        return $application ? $application['building_consent_type'] : null;
    }

    /**
     * Check if inspection is required
     */
    private function isInspectionRequired(string $inspectionType, ?string $consentType): bool
    {
        if (!$consentType) {
            return false;
        }

        $consentTypeDetails = $this->getConsentTypeDetails($consentType);
        $requiredInspections = $consentTypeDetails['inspections_required'] ?? [];

        return in_array($inspectionType, $requiredInspections);
    }

    /**
     * Get consent type details
     */
    private function getConsentTypeDetails(string $consentType): array
    {
        $consentTypes = [
            'full' => [
                'name' => 'Full Building Consent',
                'inspections_required' => ['foundation', 'frame', 'insulation', 'plumbing', 'electrical', 'final']
            ],
            'outline' => [
                'name' => 'Outline Building Consent',
                'inspections_required' => []
            ],
            'discretionary' => [
                'name' => 'Discretionary Building Consent',
                'inspections_required' => ['foundation', 'frame', 'final']
            ],
            'non-notified' => [
                'name' => 'Non-notified Building Consent',
                'inspections_required' => ['foundation', 'final']
            ],
            'limited' => [
                'name' => 'Limited Building Consent',
                'inspections_required' => ['final']
            ]
        ];

        return $consentTypes[$consentType] ?? [];
    }

    /**
     * Calculate inspection date
     */
    private function calculateInspectionDate(string $inspectionType): string
    {
        $baseDate = date('Y-m-d H:i:s');

        // Different inspection types have different scheduling requirements
        switch ($inspectionType) {
            case 'foundation':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +2 weeks'));
            case 'frame':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +4 weeks'));
            case 'insulation':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +6 weeks'));
            case 'plumbing':
            case 'electrical':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +8 weeks'));
            case 'final':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +12 weeks'));
            default:
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +4 weeks'));
        }
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
     * Initialize inspection types
     */
    private function initializeInspectionTypes(): void
    {
        $this->inspectionTypes = [
            'foundation' => [
                'name' => 'Foundation Inspection',
                'description' => 'Inspection of foundation and footings',
                'required_documents' => ['foundation_plans', 'engineer_certification'],
                'estimated_duration' => 60, // minutes
                'checklist' => [
                    'Foundation depth meets specifications',
                    'Reinforcement properly installed',
                    'Concrete quality and curing',
                    'Setback requirements met'
                ]
            ],
            'frame' => [
                'name' => 'Frame Inspection',
                'description' => 'Inspection of structural framing',
                'required_documents' => ['framing_plans', 'engineer_certification'],
                'estimated_duration' => 90,
                'checklist' => [
                    'Framing meets structural requirements',
                    'Connections properly secured',
                    'Load-bearing elements correct',
                    'Temporary bracing in place'
                ]
            ],
            'insulation' => [
                'name' => 'Insulation Inspection',
                'description' => 'Inspection of thermal insulation',
                'required_documents' => ['insulation_specifications'],
                'estimated_duration' => 45,
                'checklist' => [
                    'Insulation R-value meets requirements',
                    'Vapor barrier properly installed',
                    'Insulation continuous and complete',
                    'Penetrations sealed'
                ]
            ],
            'plumbing' => [
                'name' => 'Plumbing Inspection',
                'description' => 'Inspection of plumbing systems',
                'required_documents' => ['plumbing_plans'],
                'estimated_duration' => 60,
                'checklist' => [
                    'Pipe sizing correct',
                    'Fixtures properly installed',
                    'Pressure testing completed',
                    'Backflow prevention installed'
                ]
            ],
            'electrical' => [
                'name' => 'Electrical Inspection',
                'description' => 'Inspection of electrical systems',
                'required_documents' => ['electrical_plans'],
                'estimated_duration' => 75,
                'checklist' => [
                    'Wiring meets code requirements',
                    'Grounding properly installed',
                    'GFCI protection where required',
                    'Panel capacity adequate'
                ]
            ],
            'final' => [
                'name' => 'Final Inspection',
                'description' => 'Final inspection before occupancy',
                'required_documents' => ['certificate_of_compliance'],
                'estimated_duration' => 120,
                'checklist' => [
                    'All required inspections completed',
                    'Building meets all code requirements',
                    'Safety features operational',
                    'Documentation complete'
                ]
            ]
        ];
    }

    /**
     * Get inspection types
     */
    public function getInspectionTypes(): array
    {
        return $this->inspectionTypes;
    }
}

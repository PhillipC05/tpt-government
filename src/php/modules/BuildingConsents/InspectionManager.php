<?php
/**
 * TPT Government Platform - Building Inspection Manager
 *
 * Specialized manager for building inspection operations
 */

namespace Modules\BuildingConsents;

use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class InspectionManager
{
    /**
     * Database connection
     */
    private Database $database;

    /**
     * Workflow engine
     */
    private WorkflowEngine $workflowEngine;

    /**
     * Notification manager
     */
    private NotificationManager $notificationManager;

    /**
     * Inspection types configuration
     */
    private array $inspectionTypes;

    /**
     * Constructor
     */
    public function __construct(Database $database, WorkflowEngine $workflowEngine, NotificationManager $notificationManager)
    {
        $this->database = $database;
        $this->workflowEngine = $workflowEngine;
        $this->notificationManager = $notificationManager;
        $this->initializeInspectionTypes();
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
        $application = $this->getApplication($inspectionData['application_id']);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        $consentType = $application['building_consent_type'];
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
            'notes' => $inspectionData['special_requirements'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Save to database
        $inspectionId = $this->saveInspection($inspection);

        // Send notification
        $this->sendNotification('inspection_scheduled', $application['applicant_id'], [
            'application_id' => $inspectionData['application_id'],
            'scheduled_date' => $inspection['scheduled_date'],
            'inspection_type' => $inspectionData['inspection_type']
        ]);

        return [
            'success' => true,
            'inspection_id' => $inspectionId,
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

        // Validate inspection result
        $validation = $this->validateInspectionResult($inspectionResult);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
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
            'notes' => $inspectionResult['notes'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->updateInspection($inspectionId, $updateData);

        // If follow-up required, schedule it
        if ($inspectionResult['follow_up_required']) {
            $this->scheduleFollowUpInspection($inspectionId, $inspectionResult['follow_up_date'] ?? null);
        }

        // Send notification
        $application = $this->getApplication($inspection['application_id']);
        $this->sendNotification('inspection_completed', $application['applicant_id'], [
            'application_id' => $inspection['application_id'],
            'result' => $inspectionResult['result'],
            'inspection_type' => $inspection['inspection_type']
        ]);

        return [
            'success' => true,
            'result' => $inspectionResult['result'],
            'message' => 'Building inspection completed'
        ];
    }

    /**
     * Get inspection details
     */
    public function getInspection(int $inspectionId): ?array
    {
        try {
            $sql = "SELECT * FROM building_inspections WHERE id = ?";
            $result = $this->database->selectOne($sql, [$inspectionId]);

            if ($result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting inspection: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get inspections for application
     */
    public function getApplicationInspections(string $applicationId): array
    {
        try {
            $sql = "SELECT * FROM building_inspections WHERE application_id = ? ORDER BY scheduled_date DESC";
            $results = $this->database->select($sql, [$applicationId]);

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
        } catch (\Exception $e) {
            error_log("Error getting application inspections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve inspections'
            ];
        }
    }

    /**
     * Get inspections by status
     */
    public function getInspectionsByStatus(string $status, array $filters = []): array
    {
        try {
            $sql = "SELECT i.*, a.project_name, a.property_address
                    FROM building_inspections i
                    LEFT JOIN building_consent_applications a ON i.application_id = a.application_id
                    WHERE i.status = ?";
            $params = [$status];

            if (isset($filters['date_from'])) {
                $sql .= " AND i.scheduled_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND i.scheduled_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['inspection_type'])) {
                $sql .= " AND i.inspection_type = ?";
                $params[] = $filters['inspection_type'];
            }

            $sql .= " ORDER BY i.scheduled_date ASC";

            $results = $this->database->select($sql, $params);

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
        } catch (\Exception $e) {
            error_log("Error getting inspections by status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve inspections'
            ];
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

        if ($inspection['status'] !== 'scheduled') {
            return [
                'success' => false,
                'error' => 'Only scheduled inspections can be cancelled'
            ];
        }

        $this->updateInspection($inspectionId, [
            'status' => 'cancelled',
            'notes' => ($inspection['notes'] ? $inspection['notes'] . "\n" : '') . "Cancelled: {$reason}",
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Send notification
        $application = $this->getApplication($inspection['application_id']);
        $this->sendNotification('inspection_cancelled', $application['applicant_id'], [
            'application_id' => $inspection['application_id'],
            'inspection_type' => $inspection['inspection_type'],
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => 'Inspection cancelled successfully'
        ];
    }

    /**
     * Reschedule inspection
     */
    public function rescheduleInspection(int $inspectionId, string $newDate, string $newTime, string $reason = ''): array
    {
        $inspection = $this->getInspection($inspectionId);
        if (!$inspection) {
            return [
                'success' => false,
                'error' => 'Inspection not found'
            ];
        }

        if (!in_array($inspection['status'], ['scheduled', 'confirmed'])) {
            return [
                'success' => false,
                'error' => 'Only scheduled or confirmed inspections can be rescheduled'
            ];
        }

        $newScheduledDate = $newDate . ' ' . $newTime;

        $this->updateInspection($inspectionId, [
            'scheduled_date' => $newScheduledDate,
            'status' => 'scheduled',
            'notes' => ($inspection['notes'] ? $inspection['notes'] . "\n" : '') . "Rescheduled from {$inspection['scheduled_date']} to {$newScheduledDate}. Reason: {$reason}",
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Send notification
        $application = $this->getApplication($inspection['application_id']);
        $this->sendNotification('inspection_rescheduled', $application['applicant_id'], [
            'application_id' => $inspection['application_id'],
            'inspection_type' => $inspection['inspection_type'],
            'old_date' => $inspection['scheduled_date'],
            'new_date' => $newScheduledDate
        ]);

        return [
            'success' => true,
            'message' => 'Inspection rescheduled successfully',
            'new_scheduled_date' => $newScheduledDate
        ];
    }

    /**
     * Get inspection statistics
     */
    public function getInspectionStatistics(array $filters = []): array
    {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];

            if (isset($filters['date_from'])) {
                $whereClause .= " AND scheduled_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $whereClause .= " AND scheduled_date <= ?";
                $params[] = $filters['date_to'];
            }

            // Total inspections
            $sql = "SELECT COUNT(*) as total FROM building_inspections {$whereClause}";
            $totalResult = $this->database->selectOne($sql, $params);
            $total = $totalResult['total'] ?? 0;

            // By status
            $sql = "SELECT status, COUNT(*) as count FROM building_inspections {$whereClause} GROUP BY status";
            $statusResults = $this->database->select($sql, $params);
            $byStatus = [];
            foreach ($statusResults as $result) {
                $byStatus[$result['status']] = $result['count'];
            }

            // By type
            $sql = "SELECT inspection_type, COUNT(*) as count FROM building_inspections {$whereClause} GROUP BY inspection_type";
            $typeResults = $this->database->select($sql, $params);
            $byType = [];
            foreach ($typeResults as $result) {
                $byType[$result['inspection_type']] = $result['count'];
            }

            // By result
            $sql = "SELECT result, COUNT(*) as count FROM building_inspections {$whereClause} AND result IS NOT NULL GROUP BY result";
            $resultResults = $this->database->select($sql, $params);
            $byResult = [];
            foreach ($resultResults as $result) {
                $byResult[$result['result']] = $result['count'];
            }

            // Average completion time
            $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, scheduled_date, actual_date)) as avg_completion_time
                    FROM building_inspections
                    {$whereClause} AND actual_date IS NOT NULL AND scheduled_date IS NOT NULL";
            $timeResult = $this->database->selectOne($sql, $params);
            $avgCompletionTime = $timeResult['avg_completion_time'] ?? 0;

            return [
                'success' => true,
                'statistics' => [
                    'total_inspections' => $total,
                    'by_status' => $byStatus,
                    'by_type' => $byType,
                    'by_result' => $byResult,
                    'average_completion_time_minutes' => round($avgCompletionTime, 2),
                    'pass_rate' => $total > 0 ? round(($byResult['pass'] ?? 0) / $total * 100, 2) : 0
                ]
            ];
        } catch (\Exception $e) {
            error_log("Error getting inspection statistics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve inspection statistics'
            ];
        }
    }

    /**
     * Get inspection checklist
     */
    public function getInspectionChecklist(string $inspectionType): array
    {
        if (!isset($this->inspectionTypes[$inspectionType])) {
            return [
                'success' => false,
                'error' => 'Invalid inspection type'
            ];
        }

        $inspectionTypeData = $this->inspectionTypes[$inspectionType];

        return [
            'success' => true,
            'inspection_type' => $inspectionType,
            'name' => $inspectionTypeData['name'],
            'description' => $inspectionTypeData['description'],
            'checklist' => $inspectionTypeData['checklist'],
            'required_documents' => $inspectionTypeData['required_documents'],
            'estimated_duration' => $inspectionTypeData['estimated_duration']
        ];
    }

    /**
     * Schedule required inspections for application
     */
    public function scheduleRequiredInspections(string $applicationId, string $consentType): array
    {
        $consentTypeDetails = $this->getConsentTypeDetails($consentType);
        $requiredInspections = $consentTypeDetails['inspections_required'] ?? [];

        $scheduled = [];
        $errors = [];

        foreach ($requiredInspections as $inspectionType) {
            try {
                $scheduledDate = $this->calculateInspectionDate($inspectionType);

                $inspection = [
                    'application_id' => $applicationId,
                    'inspection_type' => $inspectionType,
                    'scheduled_date' => $scheduledDate,
                    'status' => 'scheduled',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $inspectionId = $this->saveInspection($inspection);
                $scheduled[] = [
                    'inspection_id' => $inspectionId,
                    'type' => $inspectionType,
                    'scheduled_date' => $scheduledDate
                ];

            } catch (\Exception $e) {
                $errors[] = "Failed to schedule {$inspectionType} inspection: " . $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'scheduled' => $scheduled,
            'errors' => $errors,
            'total_scheduled' => count($scheduled)
        ];
    }

    // Private helper methods

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

    private function validateInspectionResult(array $result): array
    {
        $errors = [];

        if (!isset($result['result']) || !in_array($result['result'], ['pass', 'fail', 'conditional', 'not_inspected'])) {
            $errors[] = "Invalid inspection result";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function isInspectionRequired(string $inspectionType, string $consentType): bool
    {
        $consentTypeDetails = $this->getConsentTypeDetails($consentType);
        return in_array($inspectionType, $consentTypeDetails['inspections_required'] ?? []);
    }

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
            ]
        ];

        return $consentTypes[$consentType] ?? ['name' => 'Unknown', 'inspections_required' => []];
    }

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

    private function saveInspection(array $inspection): int
    {
        try {
            $sql = "INSERT INTO building_inspections (
                application_id, inspection_type, scheduled_date, status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $params = [
                $inspection['application_id'],
                $inspection['inspection_type'],
                $inspection['scheduled_date'],
                $inspection['status'],
                $inspection['notes'],
                $inspection['created_at']
            ];

            $this->database->query($sql, $params);
            return $this->database->lastInsertId();
        } catch (\Exception $e) {
            error_log("Error saving inspection: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateInspection(int $inspectionId, array $data): bool
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
            return $this->database->query($sql, $params) !== false;
        } catch (\Exception $e) {
            error_log("Error updating inspection: " . $e->getMessage());
            return false;
        }
    }

    private function scheduleFollowUpInspection(int $inspectionId, ?string $followUpDate): bool
    {
        try {
            if (!$followUpDate) {
                $followUpDate = date('Y-m-d H:i:s', strtotime('+1 week'));
            }

            $sql = "UPDATE building_inspections SET follow_up_required = TRUE, follow_up_date = ? WHERE id = ?";
            return $this->database->query($sql, [$followUpDate, $inspectionId]) !== false;
        } catch (\Exception $e) {
            error_log("Error scheduling follow-up inspection: " . $e->getMessage());
            return false;
        }
    }

    private function getApplication(string $applicationId): ?array
    {
        try {
            $sql = "SELECT * FROM building_consent_applications WHERE application_id = ?";
            return $this->database->selectOne($sql, [$applicationId]);
        } catch (\Exception $e) {
            error_log("Error getting application: " . $e->getMessage());
            return null;
        }
    }

    private function sendNotification(string $type, ?int $userId, array $data): bool
    {
        try {
            return $this->notificationManager->sendNotification($type, $userId, $data);
        } catch (\Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }
}

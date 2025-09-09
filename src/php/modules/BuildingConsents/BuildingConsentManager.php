<?php
/**
 * TPT Government Platform - Building Consent Manager
 *
 * Core business logic for building consent applications
 */

namespace Modules\BuildingConsents;

use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class BuildingConsentManager
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
     * Constructor
     */
    public function __construct(Database $database, WorkflowEngine $workflowEngine, NotificationManager $notificationManager)
    {
        $this->database = $database;
        $this->workflowEngine = $workflowEngine;
        $this->notificationManager = $notificationManager;
    }

    /**
     * Create building consent application
     */
    public function createApplication(array $applicationData): array
    {
        // Validate application data
        $validation = $this->validateApplicationData($applicationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate application ID
        $applicationId = $this->generateApplicationId();

        // Get consent type details
        $consentType = $this->getConsentTypeDetails($applicationData['building_consent_type']);

        // Calculate processing deadline
        $lodgementDate = date('Y-m-d H:i:s');
        $processingDeadline = date('Y-m-d H:i:s', strtotime("+{$consentType['processing_time_days']} days"));

        // Create application record
        $application = [
            'application_id' => $applicationId,
            'project_name' => $applicationData['project_name'],
            'project_type' => $applicationData['project_type'],
            'property_address' => $applicationData['property_address'],
            'property_type' => $applicationData['property_type'],
            'owner_id' => $applicationData['owner_id'],
            'applicant_id' => $applicationData['applicant_id'],
            'architect_id' => $applicationData['architect_id'] ?? null,
            'contractor_id' => $applicationData['contractor_id'] ?? null,
            'building_consent_type' => $applicationData['building_consent_type'],
            'estimated_cost' => $applicationData['estimated_cost'],
            'floor_area' => $applicationData['floor_area'] ?? null,
            'storeys' => $applicationData['storeys'],
            'status' => 'draft',
            'lodgement_date' => $lodgementDate,
            'documents' => $applicationData['documents'] ?? [],
            'requirements' => $consentType['requirements'],
            'notes' => $applicationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveApplication($application);

        return [
            'success' => true,
            'application_id' => $applicationId,
            'consent_type' => $consentType['name'],
            'processing_deadline' => $processingDeadline,
            'requirements' => $consentType['requirements']
        ];
    }

    /**
     * Submit building consent application
     */
    public function submitApplication(string $applicationId): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        if ($application['status'] !== 'draft') {
            return [
                'success' => false,
                'error' => 'Application already submitted'
            ];
        }

        // Validate all requirements are met
        $validation = $this->validateApplicationRequirements($application);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Update application status
        $this->updateApplicationStatus($applicationId, 'submitted');

        // Set lodgement date
        $this->updateApplicationLodgementDate($applicationId, date('Y-m-d H:i:s'));

        // Start workflow
        $this->startConsentWorkflow($applicationId);

        // Send notification
        $this->sendNotification('application_submitted', $application['applicant_id'], [
            'application_id' => $applicationId
        ]);

        return [
            'success' => true,
            'message' => 'Application submitted successfully',
            'lodgement_date' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Review building consent application
     */
    public function reviewApplication(string $applicationId, array $reviewData): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Update application status
        $this->updateApplicationStatus($applicationId, 'under_review');

        // Log review notes
        $this->addApplicationNote($applicationId, 'Review started: ' . ($reviewData['notes'] ?? ''));

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'under_review');

        return [
            'success' => true,
            'message' => 'Application moved to review'
        ];
    }

    /**
     * Approve building consent application
     */
    public function approveApplication(string $applicationId, array $approvalData = []): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Generate consent number
        $consentNumber = $this->generateConsentNumber();

        // Calculate expiry date
        $expiryDate = date('Y-m-d H:i:s', strtotime('+1 year'));

        // Update application
        $this->updateApplication($applicationId, [
            'status' => 'approved',
            'decision_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $expiryDate,
            'consent_number' => $consentNumber,
            'conditions' => $approvalData['conditions'] ?? [],
            'notes' => $approvalData['notes'] ?? ''
        ]);

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'approved');

        // Send notification
        $this->sendNotification('consent_approved', $application['applicant_id'], [
            'application_id' => $applicationId,
            'consent_number' => $consentNumber,
            'expiry_date' => $expiryDate
        ]);

        return [
            'success' => true,
            'consent_number' => $consentNumber,
            'expiry_date' => $expiryDate,
            'message' => 'Building consent approved'
        ];
    }

    /**
     * Reject building consent application
     */
    public function rejectApplication(string $applicationId, string $reason): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Update application
        $this->updateApplication($applicationId, [
            'status' => 'rejected',
            'decision_date' => date('Y-m-d H:i:s'),
            'notes' => $reason
        ]);

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'rejected');

        // Send notification
        $this->sendNotification('consent_rejected', $application['applicant_id'], [
            'application_id' => $applicationId,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => 'Building consent rejected'
        ];
    }

    /**
     * Get building consent application
     */
    public function getApplication(string $applicationId): ?array
    {
        try {
            $sql = "SELECT * FROM building_consent_applications WHERE application_id = ?";
            $result = $this->database->selectOne($sql, [$applicationId]);

            if ($result) {
                $result['documents'] = json_decode($result['documents'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting building consent application: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get applications with filters
     */
    public function getApplications(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM building_consent_applications WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['consent_type'])) {
                $sql .= " AND building_consent_type = ?";
                $params[] = $filters['consent_type'];
            }

            if (isset($filters['owner_id'])) {
                $sql .= " AND owner_id = ?";
                $params[] = $filters['owner_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $this->database->select($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['documents'] = json_decode($result['documents'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting building consents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve building consents'
            ];
        }
    }

    /**
     * Update building consent application
     */
    public function updateApplication(string $applicationId, array $data): bool
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

            $params[] = $applicationId;

            $sql = "UPDATE building_consent_applications SET " . implode(', ', $setParts) . " WHERE application_id = ?";
            return $this->database->query($sql, $params) !== false;
        } catch (\Exception $e) {
            error_log("Error updating application: " . $e->getMessage());
            return false;
        }
    }

    // Private helper methods

    private function validateApplicationData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'project_name', 'project_type', 'property_address',
            'property_type', 'building_consent_type', 'estimated_cost', 'storeys'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate building consent type
        if (!isset($this->getConsentTypeDetails($data['building_consent_type'] ?? '')['name'])) {
            $errors[] = "Invalid building consent type";
        }

        // Validate estimated cost
        if (isset($data['estimated_cost']) && (!is_numeric($data['estimated_cost']) || $data['estimated_cost'] <= 0)) {
            $errors[] = "Estimated cost must be a positive number";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function generateApplicationId(): string
    {
        return 'BC' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateConsentNumber(): string
    {
        return 'BCN' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function getConsentTypeDetails(string $consentType): array
    {
        $consentTypes = [
            'full' => [
                'name' => 'Full Building Consent',
                'processing_time_days' => 20,
                'requirements' => [
                    'site_plan', 'floor_plans', 'elevations', 'specifications',
                    'engineer_reports', 'resource_consent'
                ]
            ],
            'outline' => [
                'name' => 'Outline Building Consent',
                'processing_time_days' => 10,
                'requirements' => [
                    'site_plan', 'concept_plans', 'specifications'
                ]
            ],
            'discretionary' => [
                'name' => 'Discretionary Building Consent',
                'processing_time_days' => 30,
                'requirements' => [
                    'site_plan', 'floor_plans', 'elevations', 'specifications',
                    'impact_assessment', 'public_notification'
                ]
            ]
        ];

        return $consentTypes[$consentType] ?? ['name' => 'Unknown', 'processing_time_days' => 15, 'requirements' => []];
    }

    private function saveApplication(array $application): bool
    {
        try {
            $sql = "INSERT INTO building_consent_applications (
                application_id, project_name, project_type, property_address,
                property_type, owner_id, applicant_id, architect_id, contractor_id,
                building_consent_type, estimated_cost, floor_area, storeys,
                status, documents, requirements, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $application['application_id'],
                $application['project_name'],
                $application['project_type'],
                $application['property_address'],
                $application['property_type'],
                $application['owner_id'],
                $application['applicant_id'],
                $application['architect_id'],
                $application['contractor_id'],
                $application['building_consent_type'],
                $application['estimated_cost'],
                $application['floor_area'],
                $application['storeys'],
                $application['status'],
                json_encode($application['documents']),
                json_encode($application['requirements']),
                $application['notes']
            ];

            return $this->database->query($sql, $params) !== false;
        } catch (\Exception $e) {
            error_log("Error saving building consent application: " . $e->getMessage());
            return false;
        }
    }

    private function updateApplicationStatus(string $applicationId, string $status): bool
    {
        try {
            $sql = "UPDATE building_consent_applications SET status = ? WHERE application_id = ?";
            return $this->database->query($sql, [$status, $applicationId]) !== false;
        } catch (\Exception $e) {
            error_log("Error updating application status: " . $e->getMessage());
            return false;
        }
    }

    private function updateApplicationLodgementDate(string $applicationId, string $date): bool
    {
        try {
            $sql = "UPDATE building_consent_applications SET lodgement_date = ? WHERE application_id = ?";
            return $this->database->query($sql, [$date, $applicationId]) !== false;
        } catch (\Exception $e) {
            error_log("Error updating application lodgement date: " . $e->getMessage());
            return false;
        }
    }

    private function addApplicationNote(string $applicationId, string $note): bool
    {
        try {
            $currentNotes = $this->getApplicationNotes($applicationId);
            $newNote = date('Y-m-d H:i:s') . ": " . $note;
            $updatedNotes = $currentNotes ? $currentNotes . "\n" . $newNote : $newNote;

            $sql = "UPDATE building_consent_applications SET notes = ? WHERE application_id = ?";
            return $this->database->query($sql, [$updatedNotes, $applicationId]) !== false;
        } catch (\Exception $e) {
            error_log("Error adding application note: " . $e->getMessage());
            return false;
        }
    }

    private function getApplicationNotes(string $applicationId): ?string
    {
        try {
            $sql = "SELECT notes FROM building_consent_applications WHERE application_id = ?";
            $result = $this->database->selectOne($sql, [$applicationId]);
            return $result ? $result['notes'] : null;
        } catch (\Exception $e) {
            error_log("Error getting application notes: " . $e->getMessage());
            return null;
        }
    }

    private function validateApplicationRequirements(array $application): array
    {
        $errors = [];
        $requirements = $application['requirements'] ?? [];

        foreach ($requirements as $requirement) {
            if (!isset($application['documents'][$requirement]) || empty($application['documents'][$requirement])) {
                $errors[] = "Required document missing: {$requirement}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function startConsentWorkflow(string $applicationId): bool
    {
        try {
            return $this->workflowEngine->startWorkflow('building_consent_process', $applicationId);
        } catch (\Exception $e) {
            error_log("Error starting consent workflow: " . $e->getMessage());
            return false;
        }
    }

    private function advanceWorkflow(string $applicationId, string $step): bool
    {
        try {
            return $this->workflowEngine->advanceWorkflow($applicationId, $step);
        } catch (\Exception $e) {
            error_log("Error advancing workflow: " . $e->getMessage());
            return false;
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

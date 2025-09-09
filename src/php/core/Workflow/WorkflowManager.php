<?php
/**
 * TPT Government Platform - Workflow Manager
 *
 * Orchestrates business processes and state transitions across the application
 */

namespace Core\Workflow;

use Core\Database;
use Core\Logging\StructuredLogger;
use Exception;

class WorkflowManager
{
    private Database $db;
    private StructuredLogger $logger;
    private array $workflows = [];
    private array $currentStates = [];

    public function __construct(Database $db, StructuredLogger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->loadWorkflowDefinitions();
    }

    /**
     * Load workflow definitions from configuration
     */
    private function loadWorkflowDefinitions(): void
    {
        // Define workflow configurations
        $this->workflows = [
            'building_consent' => [
                'states' => [
                    'draft' => ['label' => 'Draft', 'color' => 'gray'],
                    'submitted' => ['label' => 'Submitted', 'color' => 'blue'],
                    'under_review' => ['label' => 'Under Review', 'color' => 'yellow'],
                    'approved' => ['label' => 'Approved', 'color' => 'green'],
                    'rejected' => ['label' => 'Rejected', 'color' => 'red'],
                    'completed' => ['label' => 'Completed', 'color' => 'green']
                ],
                'transitions' => [
                    'draft' => ['submitted'],
                    'submitted' => ['under_review', 'draft'],
                    'under_review' => ['approved', 'rejected', 'submitted'],
                    'approved' => ['completed'],
                    'rejected' => ['draft', 'submitted']
                ],
                'guards' => [
                    'submitted' => ['checkRequiredDocuments', 'validateApplicationData'],
                    'approved' => ['checkAllInspectionsCompleted', 'validateCompliance'],
                    'completed' => ['checkAllCertificatesIssued', 'validateFinalRequirements']
                ],
                'actions' => [
                    'submitted' => ['notifyReviewers', 'scheduleInitialInspection'],
                    'approved' => ['generateConsentNumber', 'notifyApplicant'],
                    'rejected' => ['notifyApplicant', 'logRejectionReason'],
                    'completed' => ['archiveApplication', 'sendCompletionCertificate']
                ]
            ],
            'inspection' => [
                'states' => [
                    'scheduled' => ['label' => 'Scheduled', 'color' => 'blue'],
                    'in_progress' => ['label' => 'In Progress', 'color' => 'yellow'],
                    'completed' => ['label' => 'Completed', 'color' => 'green'],
                    'cancelled' => ['label' => 'Cancelled', 'color' => 'red']
                ],
                'transitions' => [
                    'scheduled' => ['in_progress', 'cancelled'],
                    'in_progress' => ['completed', 'scheduled'],
                    'completed' => [],
                    'cancelled' => ['scheduled']
                ],
                'guards' => [
                    'in_progress' => ['checkInspectorAvailability', 'validateInspectionRequirements'],
                    'completed' => ['validateInspectionResults', 'checkRequiredEvidence']
                ],
                'actions' => [
                    'scheduled' => ['notifyInspector', 'updateCalendar'],
                    'in_progress' => ['logInspectionStart', 'updateStatus'],
                    'completed' => ['generateInspectionReport', 'scheduleFollowUpIfNeeded'],
                    'cancelled' => ['notifyAffectedParties', 'rescheduleIfPossible']
                ]
            ],
            'certificate' => [
                'states' => [
                    'pending' => ['label' => 'Pending', 'color' => 'yellow'],
                    'issued' => ['label' => 'Issued', 'color' => 'green'],
                    'revoked' => ['label' => 'Revoked', 'color' => 'red'],
                    'expired' => ['label' => 'Expired', 'color' => 'gray']
                ],
                'transitions' => [
                    'pending' => ['issued', 'revoked'],
                    'issued' => ['revoked', 'expired'],
                    'revoked' => ['pending'],
                    'expired' => ['pending']
                ],
                'guards' => [
                    'issued' => ['checkApplicationEligibility', 'validateAllRequirements'],
                    'revoked' => ['checkRevocationReasons', 'validateAuthority']
                ],
                'actions' => [
                    'issued' => ['generateCertificateNumber', 'sendToApplicant', 'logIssuance'],
                    'revoked' => ['notifyApplicant', 'logRevocationReason', 'updateRelatedRecords'],
                    'expired' => ['notifyApplicant', 'archiveCertificate']
                ]
            ]
        ];
    }

    /**
     * Get current state of a workflow instance
     */
    public function getCurrentState(string $workflowType, string $instanceId): ?string
    {
        try {
            $sql = "SELECT current_state FROM workflow_instances
                    WHERE workflow_type = ? AND instance_id = ?";
            $result = $this->db->fetch($sql, [$workflowType, $instanceId]);

            return $result ? $result['current_state'] : null;
        } catch (Exception $e) {
            $this->logger->error('Failed to get current workflow state', [
                'workflow_type' => $workflowType,
                'instance_id' => $instanceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Initialize a new workflow instance
     */
    public function initializeWorkflow(string $workflowType, string $instanceId, array $initialData = []): bool
    {
        try {
            if (!isset($this->workflows[$workflowType])) {
                throw new Exception("Workflow type '{$workflowType}' not found");
            }

            $workflow = $this->workflows[$workflowType];
            $initialState = array_key_first($workflow['states']);

            // Check if instance already exists
            $existingState = $this->getCurrentState($workflowType, $instanceId);
            if ($existingState !== null) {
                $this->logger->warning('Workflow instance already exists', [
                    'workflow_type' => $workflowType,
                    'instance_id' => $instanceId,
                    'current_state' => $existingState
                ]);
                return false;
            }

            // Create workflow instance
            $sql = "INSERT INTO workflow_instances
                    (workflow_type, instance_id, current_state, initial_data, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())";

            $result = $this->db->execute($sql, [
                $workflowType,
                $instanceId,
                $initialState,
                json_encode($initialData)
            ]);

            if ($result) {
                $this->logger->info('Workflow instance initialized', [
                    'workflow_type' => $workflowType,
                    'instance_id' => $instanceId,
                    'initial_state' => $initialState
                ]);

                // Execute initial state actions
                $this->executeStateActions($workflowType, $instanceId, $initialState, 'initialize');
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize workflow', [
                'workflow_type' => $workflowType,
                'instance_id' => $instanceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transition workflow to a new state
     */
    public function transition(string $workflowType, string $instanceId, string $newState, array $transitionData = []): bool
    {
        try {
            if (!isset($this->workflows[$workflowType])) {
                throw new Exception("Workflow type '{$workflowType}' not found");
            }

            $workflow = $this->workflows[$workflowType];
            $currentState = $this->getCurrentState($workflowType, $instanceId);

            if ($currentState === null) {
                throw new Exception("Workflow instance not found");
            }

            // Validate transition
            if (!$this->isValidTransition($workflow, $currentState, $newState)) {
                $this->logger->warning('Invalid workflow transition attempted', [
                    'workflow_type' => $workflowType,
                    'instance_id' => $instanceId,
                    'current_state' => $currentState,
                    'new_state' => $newState
                ]);
                return false;
            }

            // Check guards
            if (!$this->checkGuards($workflow, $instanceId, $newState, $transitionData)) {
                $this->logger->warning('Workflow transition blocked by guards', [
                    'workflow_type' => $workflowType,
                    'instance_id' => $instanceId,
                    'new_state' => $newState
                ]);
                return false;
            }

            // Execute transition
            $this->db->beginTransaction();

            try {
                // Update workflow state
                $sql = "UPDATE workflow_instances
                        SET current_state = ?, updated_at = NOW()
                        WHERE workflow_type = ? AND instance_id = ?";

                $this->db->execute($sql, [$newState, $workflowType, $instanceId]);

                // Log transition
                $this->logTransition($workflowType, $instanceId, $currentState, $newState, $transitionData);

                // Execute state actions
                $this->executeStateActions($workflowType, $instanceId, $newState, 'transition', $transitionData);

                $this->db->commit();

                $this->logger->info('Workflow transition completed', [
                    'workflow_type' => $workflowType,
                    'instance_id' => $instanceId,
                    'from_state' => $currentState,
                    'to_state' => $newState
                ]);

                return true;
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->logger->error('Failed to transition workflow', [
                'workflow_type' => $workflowType,
                'instance_id' => $instanceId,
                'new_state' => $newState,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if transition is valid
     */
    private function isValidTransition(array $workflow, string $currentState, string $newState): bool
    {
        return isset($workflow['transitions'][$currentState]) &&
               in_array($newState, $workflow['transitions'][$currentState]);
    }

    /**
     * Check transition guards
     */
    private function checkGuards(array $workflow, string $instanceId, string $newState, array $transitionData): bool
    {
        if (!isset($workflow['guards'][$newState])) {
            return true; // No guards for this transition
        }

        foreach ($workflow['guards'][$newState] as $guard) {
            if (!$this->executeGuard($guard, $instanceId, $transitionData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a guard function
     */
    private function executeGuard(string $guardName, string $instanceId, array $transitionData): bool
    {
        // Map guard names to actual validation methods
        $guardMethods = [
            'checkRequiredDocuments' => 'validateRequiredDocuments',
            'validateApplicationData' => 'validateApplicationData',
            'checkAllInspectionsCompleted' => 'validateAllInspectionsCompleted',
            'validateCompliance' => 'validateComplianceRequirements',
            'checkAllCertificatesIssued' => 'validateAllCertificatesIssued',
            'validateFinalRequirements' => 'validateFinalRequirements',
            'checkInspectorAvailability' => 'validateInspectorAvailability',
            'validateInspectionRequirements' => 'validateInspectionRequirements',
            'validateInspectionResults' => 'validateInspectionResults',
            'checkRequiredEvidence' => 'validateRequiredEvidence',
            'checkApplicationEligibility' => 'validateApplicationEligibility',
            'validateAllRequirements' => 'validateAllRequirements',
            'checkRevocationReasons' => 'validateRevocationReasons',
            'validateAuthority' => 'validateAuthority'
        ];

        if (!isset($guardMethods[$guardName])) {
            $this->logger->warning('Unknown guard method', ['guard' => $guardName]);
            return false;
        }

        $method = $guardMethods[$guardName];

        if (!method_exists($this, $method)) {
            $this->logger->error('Guard method not implemented', ['method' => $method]);
            return false;
        }

        return $this->$method($instanceId, $transitionData);
    }

    /**
     * Execute state actions
     */
    private function executeStateActions(string $workflowType, string $instanceId, string $state, string $trigger, array $data = []): void
    {
        $workflow = $this->workflows[$workflowType];

        if (!isset($workflow['actions'][$state])) {
            return; // No actions for this state
        }

        foreach ($workflow['actions'][$state] as $action) {
            try {
                $this->executeAction($action, $workflowType, $instanceId, $data);
            } catch (Exception $e) {
                $this->logger->error('Failed to execute workflow action', [
                    'action' => $action,
                    'workflow_type' => $workflowType,
                    'instance_id' => $instanceId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Execute a workflow action
     */
    private function executeAction(string $actionName, string $workflowType, string $instanceId, array $data): void
    {
        // Map action names to actual methods
        $actionMethods = [
            'notifyReviewers' => 'notifyReviewers',
            'scheduleInitialInspection' => 'scheduleInitialInspection',
            'generateConsentNumber' => 'generateConsentNumber',
            'notifyApplicant' => 'notifyApplicant',
            'logRejectionReason' => 'logRejectionReason',
            'archiveApplication' => 'archiveApplication',
            'sendCompletionCertificate' => 'sendCompletionCertificate',
            'notifyInspector' => 'notifyInspector',
            'updateCalendar' => 'updateCalendar',
            'logInspectionStart' => 'logInspectionStart',
            'updateStatus' => 'updateStatus',
            'generateInspectionReport' => 'generateInspectionReport',
            'scheduleFollowUpIfNeeded' => 'scheduleFollowUpIfNeeded',
            'notifyAffectedParties' => 'notifyAffectedParties',
            'rescheduleIfPossible' => 'rescheduleIfPossible',
            'generateCertificateNumber' => 'generateCertificateNumber',
            'sendToApplicant' => 'sendToApplicant',
            'logIssuance' => 'logIssuance',
            'logRevocationReason' => 'logRevocationReason',
            'updateRelatedRecords' => 'updateRelatedRecords'
        ];

        if (!isset($actionMethods[$actionName])) {
            $this->logger->warning('Unknown action method', ['action' => $actionName]);
            return;
        }

        $method = $actionMethods[$actionName];

        if (!method_exists($this, $method)) {
            $this->logger->error('Action method not implemented', ['method' => $method]);
            return;
        }

        $this->$method($workflowType, $instanceId, $data);
    }

    /**
     * Log workflow transition
     */
    private function logTransition(string $workflowType, string $instanceId, string $fromState, string $toState, array $data): void
    {
        $sql = "INSERT INTO workflow_transitions
                (workflow_type, instance_id, from_state, to_state, transition_data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";

        $this->db->execute($sql, [
            $workflowType,
            $instanceId,
            $fromState,
            $toState,
            json_encode($data)
        ]);
    }

    /**
     * Get workflow history
     */
    public function getWorkflowHistory(string $workflowType, string $instanceId): array
    {
        try {
            $sql = "SELECT from_state, to_state, transition_data, created_at
                    FROM workflow_transitions
                    WHERE workflow_type = ? AND instance_id = ?
                    ORDER BY created_at DESC";

            $results = $this->db->fetchAll($sql, [$workflowType, $instanceId]);

            return array_map(function($result) {
                return [
                    'from_state' => $result['from_state'],
                    'to_state' => $result['to_state'],
                    'data' => json_decode($result['transition_data'], true),
                    'timestamp' => $result['created_at']
                ];
            }, $results);
        } catch (Exception $e) {
            $this->logger->error('Failed to get workflow history', [
                'workflow_type' => $workflowType,
                'instance_id' => $instanceId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get available transitions for current state
     */
    public function getAvailableTransitions(string $workflowType, string $instanceId): array
    {
        $currentState = $this->getCurrentState($workflowType, $instanceId);

        if (!$currentState || !isset($this->workflows[$workflowType])) {
            return [];
        }

        $workflow = $this->workflows[$workflowType];

        return $workflow['transitions'][$currentState] ?? [];
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStatistics(string $workflowType = null): array
    {
        try {
            $sql = "SELECT
                        workflow_type,
                        current_state,
                        COUNT(*) as count
                    FROM workflow_instances";

            $params = [];
            if ($workflowType) {
                $sql .= " WHERE workflow_type = ?";
                $params[] = $workflowType;
            }

            $sql .= " GROUP BY workflow_type, current_state ORDER BY workflow_type, current_state";

            $results = $this->db->fetchAll($sql, $params);

            $stats = [];
            foreach ($results as $result) {
                $type = $result['workflow_type'];
                $state = $result['workflow_state'];
                $count = (int)$result['count'];

                if (!isset($stats[$type])) {
                    $stats[$type] = ['total' => 0, 'by_state' => []];
                }

                $stats[$type]['by_state'][$state] = $count;
                $stats[$type]['total'] += $count;
            }

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Failed to get workflow statistics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    // Guard Methods (Validation)

    private function validateRequiredDocuments(string $instanceId, array $data): bool
    {
        // Implementation would check if all required documents are uploaded
        return true; // Placeholder
    }

    private function validateApplicationData(string $instanceId, array $data): bool
    {
        // Implementation would validate application data completeness
        return true; // Placeholder
    }

    private function validateAllInspectionsCompleted(string $instanceId, array $data): bool
    {
        // Implementation would check if all required inspections are completed
        return true; // Placeholder
    }

    private function validateComplianceRequirements(string $instanceId, array $data): bool
    {
        // Implementation would validate compliance requirements
        return true; // Placeholder
    }

    private function validateAllCertificatesIssued(string $instanceId, array $data): bool
    {
        // Implementation would check if all certificates are issued
        return true; // Placeholder
    }

    private function validateFinalRequirements(string $instanceId, array $data): bool
    {
        // Implementation would validate final completion requirements
        return true; // Placeholder
    }

    private function validateInspectorAvailability(string $instanceId, array $data): bool
    {
        // Implementation would check inspector availability
        return true; // Placeholder
    }

    private function validateInspectionRequirements(string $instanceId, array $data): bool
    {
        // Implementation would validate inspection requirements
        return true; // Placeholder
    }

    private function validateInspectionResults(string $instanceId, array $data): bool
    {
        // Implementation would validate inspection results
        return true; // Placeholder
    }

    private function validateRequiredEvidence(string $instanceId, array $data): bool
    {
        // Implementation would check required evidence
        return true; // Placeholder
    }

    private function validateApplicationEligibility(string $instanceId, array $data): bool
    {
        // Implementation would check application eligibility
        return true; // Placeholder
    }

    private function validateAllRequirements(string $instanceId, array $data): bool
    {
        // Implementation would validate all requirements
        return true; // Placeholder
    }

    private function validateRevocationReasons(string $instanceId, array $data): bool
    {
        // Implementation would validate revocation reasons
        return true; // Placeholder
    }

    private function validateAuthority(string $instanceId, array $data): bool
    {
        // Implementation would validate user authority
        return true; // Placeholder
    }

    // Action Methods

    private function notifyReviewers(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would send notifications to reviewers
        $this->logger->info('Notifying reviewers', ['instance_id' => $instanceId]);
    }

    private function scheduleInitialInspection(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would schedule initial inspection
        $this->logger->info('Scheduling initial inspection', ['instance_id' => $instanceId]);
    }

    private function generateConsentNumber(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would generate consent number
        $this->logger->info('Generating consent number', ['instance_id' => $instanceId]);
    }

    private function notifyApplicant(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would notify applicant
        $this->logger->info('Notifying applicant', ['instance_id' => $instanceId]);
    }

    private function logRejectionReason(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would log rejection reason
        $this->logger->info('Logging rejection reason', ['instance_id' => $instanceId]);
    }

    private function archiveApplication(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would archive application
        $this->logger->info('Archiving application', ['instance_id' => $instanceId]);
    }

    private function sendCompletionCertificate(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would send completion certificate
        $this->logger->info('Sending completion certificate', ['instance_id' => $instanceId]);
    }

    private function notifyInspector(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would notify inspector
        $this->logger->info('Notifying inspector', ['instance_id' => $instanceId]);
    }

    private function updateCalendar(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would update calendar
        $this->logger->info('Updating calendar', ['instance_id' => $instanceId]);
    }

    private function logInspectionStart(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would log inspection start
        $this->logger->info('Logging inspection start', ['instance_id' => $instanceId]);
    }

    private function updateStatus(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would update status
        $this->logger->info('Updating status', ['instance_id' => $instanceId]);
    }

    private function generateInspectionReport(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would generate inspection report
        $this->logger->info('Generating inspection report', ['instance_id' => $instanceId]);
    }

    private function scheduleFollowUpIfNeeded(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would schedule follow-up if needed
        $this->logger->info('Scheduling follow-up if needed', ['instance_id' => $instanceId]);
    }

    private function notifyAffectedParties(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would notify affected parties
        $this->logger->info('Notifying affected parties', ['instance_id' => $instanceId]);
    }

    private function rescheduleIfPossible(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would reschedule if possible
        $this->logger->info('Rescheduling if possible', ['instance_id' => $instanceId]);
    }

    private function generateCertificateNumber(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would generate certificate number
        $this->logger->info('Generating certificate number', ['instance_id' => $instanceId]);
    }

    private function sendToApplicant(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would send to applicant
        $this->logger->info('Sending to applicant', ['instance_id' => $instanceId]);
    }

    private function logIssuance(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would log issuance
        $this->logger->info('Logging issuance', ['instance_id' => $instanceId]);
    }

    private function logRevocationReason(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would log revocation reason
        $this->logger->info('Logging revocation reason', ['instance_id' => $instanceId]);
    }

    private function updateRelatedRecords(string $workflowType, string $instanceId, array $data): void
    {
        // Implementation would update related records
        $this->logger->info('Updating related records', ['instance_id' => $instanceId]);
    }
}

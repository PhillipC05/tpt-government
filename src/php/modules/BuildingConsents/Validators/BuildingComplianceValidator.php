<?php
/**
 * TPT Government Platform - Building Compliance Validator
 *
 * Validator for building compliance requirement data
 */

namespace Modules\BuildingConsents\Validators;

use Core\Validation\BaseValidator;

class BuildingComplianceValidator extends BaseValidator
{
    /**
     * Validation rules for creating a compliance requirement
     */
    public function getCreateRules(): array
    {
        return [
            'application_id' => 'required|string|regex:/^BC\d{4}\d{6}$/',
            'requirement_type' => 'required|in:documentation,inspection,safety,environmental,legal,administrative',
            'requirement_name' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10|max:1000',
            'due_date' => 'required|date|after:today',
            'assigned_to' => 'integer|min:1',
            'priority' => 'required|in:low,medium,high,critical',
            'category' => 'required|in:pre_construction,construction,completion,post_completion',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for updating compliance status
     */
    public function getUpdateStatusRules(): array
    {
        return [
            'status' => 'required|in:pending,in_progress,completed,overdue,waived',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for adding evidence
     */
    public function getAddEvidenceRules(): array
    {
        return [
            'evidence' => 'required|array',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for assigning compliance requirement
     */
    public function getAssignRules(): array
    {
        return [
            'assigned_to' => 'required|integer|min:1',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Custom validation messages
     */
    public function getCustomMessages(): array
    {
        return [
            'application_id.required' => 'Application ID is required',
            'application_id.regex' => 'Invalid application ID format',
            'requirement_type.required' => 'Requirement type is required',
            'requirement_type.in' => 'Invalid requirement type selected',
            'requirement_name.required' => 'Requirement name is required',
            'requirement_name.min' => 'Requirement name must be at least 5 characters',
            'requirement_name.max' => 'Requirement name cannot exceed 255 characters',
            'description.required' => 'Description is required',
            'description.min' => 'Description must be at least 10 characters',
            'description.max' => 'Description cannot exceed 1000 characters',
            'due_date.required' => 'Due date is required',
            'due_date.date' => 'Due date must be a valid date',
            'due_date.after' => 'Due date must be in the future',
            'assigned_to.integer' => 'Assigned to must be a valid user ID',
            'assigned_to.min' => 'Assigned to must be greater than 0',
            'priority.required' => 'Priority is required',
            'priority.in' => 'Invalid priority level selected',
            'category.required' => 'Category is required',
            'category.in' => 'Invalid category selected',
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
            'evidence.required' => 'Evidence is required',
            'evidence.array' => 'Evidence must be provided as an array',
            'notes.string' => 'Notes must be text',
            'notes.max' => 'Notes cannot exceed 500 characters'
        ];
    }

    /**
     * Validate compliance requirement creation
     */
    public function validateCreate(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getCreateRules());
    }

    /**
     * Validate compliance status update
     */
    public function validateUpdateStatus(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getUpdateStatusRules());
    }

    /**
     * Validate evidence addition
     */
    public function validateAddEvidence(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getAddEvidenceRules());
    }

    /**
     * Validate compliance assignment
     */
    public function validateAssign(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getAssignRules());
    }

    /**
     * Additional validation for business rules
     */
    protected function validateRequirementType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['documentation', 'inspection', 'safety', 'environmental', 'legal', 'administrative'];
        return in_array($value, $validTypes, true);
    }

    protected function validatePriority(string $field, mixed $value, array $parameters): bool
    {
        $validPriorities = ['low', 'medium', 'high', 'critical'];
        return in_array($value, $validPriorities, true);
    }

    protected function validateCategory(string $field, mixed $value, array $parameters): bool
    {
        $validCategories = ['pre_construction', 'construction', 'completion', 'post_completion'];
        return in_array($value, $validCategories, true);
    }

    protected function validateStatus(string $field, mixed $value, array $parameters): bool
    {
        $validStatuses = ['pending', 'in_progress', 'completed', 'overdue', 'waived'];
        return in_array($value, $validStatuses, true);
    }

    /**
     * Validate due date based on priority
     */
    public function validateDueDateByPriority(array $data): bool
    {
        if (!isset($data['priority']) || !isset($data['due_date'])) {
            return true; // Skip if data not available
        }

        $priority = $data['priority'];
        $dueDate = date_create($data['due_date']);
        $now = date_create();

        if (!$dueDate || !$now) {
            return false;
        }

        $interval = date_diff($now, $dueDate);
        $days = (int)$interval->format('%r%a');

        // Different priorities have different minimum timeframes
        $minDays = match ($priority) {
            'critical' => 1,    // Critical items due within 1 day
            'high' => 3,        // High priority within 3 days
            'medium' => 7,      // Medium priority within 1 week
            'low' => 14,        // Low priority within 2 weeks
            default => 7
        };

        return $days >= $minDays;
    }

    /**
     * Validate that high priority items are assigned
     */
    public function validateHighPriorityAssignment(array $data): bool
    {
        if (!isset($data['priority'])) {
            return true; // Skip if priority not set
        }

        // High and critical priority items must be assigned
        if (in_array($data['priority'], ['high', 'critical'])) {
            return isset($data['assigned_to']) && !empty($data['assigned_to']);
        }

        return true;
    }

    /**
     * Validate evidence format
     */
    public function validateEvidenceFormat(array $evidence): bool
    {
        if (empty($evidence)) {
            return false; // Evidence array cannot be empty
        }

        foreach ($evidence as $item) {
            if (!is_array($item)) {
                return false;
            }

            // Check required fields
            if (!isset($item['type']) || !isset($item['description'])) {
                return false;
            }

            // Validate evidence type
            $validTypes = ['document', 'photo', 'report', 'certificate', 'inspection_report', 'other'];
            if (!in_array($item['type'], $validTypes)) {
                return false;
            }

            // Validate description length
            if (strlen($item['description']) < 5 || strlen($item['description']) > 500) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate compliance requirement dependencies
     */
    public function validateRequirementDependencies(string $applicationId, string $requirementType, string $category): bool
    {
        // This would typically check the database to ensure that prerequisite
        // requirements are met before creating new ones

        // For example, you can't have construction requirements without pre-construction ones
        $dependencies = [
            'construction' => ['pre_construction'],
            'completion' => ['construction'],
            'post_completion' => ['completion']
        ];

        if (!isset($dependencies[$category])) {
            return true; // No dependencies for this category
        }

        // In a real implementation, you would check if the prerequisite
        // requirements exist and are completed in the database

        return true; // Simplified for this example
    }

    /**
     * Comprehensive validation with business rules
     */
    public function validateWithBusinessRules(array $data, string $operation = 'create'): array
    {
        $errors = [];

        // Basic validation
        $rules = match ($operation) {
            'create' => $this->getCreateRules(),
            'update_status' => $this->getUpdateStatusRules(),
            'add_evidence' => $this->getAddEvidenceRules(),
            'assign' => $this->getAssignRules(),
            default => []
        };

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Business rule validations
        if ($operation === 'create') {
            if (!$this->validateDueDateByPriority($data)) {
                $errors[] = 'Due date does not meet minimum timeframe for the selected priority level';
            }

            if (!$this->validateHighPriorityAssignment($data)) {
                $errors[] = 'High or critical priority requirements must be assigned to a user';
            }

            if (isset($data['application_id']) && isset($data['requirement_type']) && isset($data['category'])) {
                if (!$this->validateRequirementDependencies($data['application_id'], $data['requirement_type'], $data['category'])) {
                    $errors[] = 'Prerequisite requirements must be completed before creating this requirement';
                }
            }
        }

        if ($operation === 'add_evidence') {
            if (isset($data['evidence']) && is_array($data['evidence'])) {
                if (!$this->validateEvidenceFormat($data['evidence'])) {
                    $errors[] = 'Evidence format is invalid or incomplete';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate bulk compliance operations
     */
    public function validateBulkOperation(array $complianceIds, string $operation): array
    {
        $errors = [];

        if (empty($complianceIds)) {
            $errors[] = 'At least one compliance requirement must be selected';
            return $errors;
        }

        if (count($complianceIds) > 50) {
            $errors[] = 'Cannot process more than 50 compliance requirements at once';
        }

        $validOperations = ['update_status', 'assign', 'delete'];
        if (!in_array($operation, $validOperations)) {
            $errors[] = 'Invalid bulk operation specified';
        }

        return $errors;
    }

    /**
     * Validate compliance waiver request
     */
    public function validateWaiverRequest(array $data): array
    {
        $errors = [];

        $rules = [
            'compliance_id' => 'required|integer|min:1',
            'waiver_reason' => 'required|string|min:20|max:1000',
            'justification' => 'required|string|min:50|max:2000',
            'approved_by' => 'required|integer|min:1',
            'alternative_requirements' => 'array',
            'review_date' => 'date|after:today'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for waiver
        if (isset($data['compliance_id'])) {
            // Check if the requirement can be waived
            // Some requirements are mandatory and cannot be waived
        }

        return $errors;
    }

    /**
     * Validate compliance extension request
     */
    public function validateExtensionRequest(array $data): array
    {
        $errors = [];

        $rules = [
            'compliance_id' => 'required|integer|min:1',
            'extension_reason' => 'required|string|min:20|max:1000',
            'requested_extension_days' => 'required|integer|min:1|max:90',
            'justification' => 'required|string|min:50|max:2000',
            'approved_by' => 'required|integer|min:1',
            'new_due_date' => 'required|date|after:today'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for extension
        if (isset($data['requested_extension_days'])) {
            $days = (int)$data['requested_extension_days'];

            // Extensions over 30 days require senior approval
            if ($days > 30 && !isset($data['senior_approval'])) {
                $errors[] = 'Extensions over 30 days require senior management approval';
            }

            // Extensions over 60 days are not allowed
            if ($days > 60) {
                $errors[] = 'Extensions cannot exceed 60 days';
            }
        }

        return $errors;
    }

    /**
     * Validate compliance audit
     */
    public function validateAudit(array $data): array
    {
        $errors = [];

        $rules = [
            'compliance_id' => 'required|integer|min:1',
            'audit_type' => 'required|in:internal,external,regulatory',
            'audit_date' => 'required|date',
            'auditor_name' => 'required|string|min:2|max:255',
            'audit_findings' => 'required|string|min:50|max:5000',
            'audit_recommendations' => 'string|max:5000',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'date|after:audit_date'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for audit
        if (isset($data['follow_up_required']) && $data['follow_up_required']) {
            if (!isset($data['follow_up_date'])) {
                $errors[] = 'Follow-up date is required when follow-up is required';
            }
        }

        return $errors;
    }

    /**
     * Validate compliance reporting
     */
    public function validateReport(array $data): array
    {
        $errors = [];

        $rules = [
            'report_type' => 'required|in:monthly,quarterly,annual,ad_hoc',
            'report_period_start' => 'required|date',
            'report_period_end' => 'required|date|after:report_period_start',
            'compliance_status' => 'required|in:compliant,non_compliant,partially_compliant',
            'total_requirements' => 'required|integer|min:0',
            'completed_requirements' => 'required|integer|min:0',
            'overdue_requirements' => 'required|integer|min:0',
            'critical_issues' => 'integer|min:0',
            'recommendations' => 'string|max:5000',
            'prepared_by' => 'required|integer|min:1',
            'approved_by' => 'integer|min:1'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for reporting
        if (isset($data['completed_requirements']) && isset($data['total_requirements'])) {
            $completed = (int)$data['completed_requirements'];
            $total = (int)$data['total_requirements'];

            if ($completed > $total) {
                $errors[] = 'Completed requirements cannot exceed total requirements';
            }
        }

        return $errors;
    }
}

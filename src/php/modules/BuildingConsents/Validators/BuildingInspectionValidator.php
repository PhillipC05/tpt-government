<?php
/**
 * TPT Government Platform - Building Inspection Validator
 *
 * Validator for building inspection data
 */

namespace Modules\BuildingConsents\Validators;

use Core\Validation\BaseValidator;

class BuildingInspectionValidator extends BaseValidator
{
    /**
     * Validation rules for scheduling an inspection
     */
    public function getScheduleRules(): array
    {
        return [
            'application_id' => 'required|string|regex:/^BC\d{4}\d{6}$/',
            'inspection_type' => 'required|in:foundation,frame,insulation,linings,final,compliance',
            'scheduled_date' => 'required|date|after:today',
            'scheduled_time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'inspector_id' => 'required|integer|min:1',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for completing an inspection
     */
    public function getCompleteRules(): array
    {
        return [
            'result' => 'required|in:pass,fail,conditional',
            'findings' => 'array',
            'recommendations' => 'array',
            'follow_up_required' => 'boolean',
            'notes' => 'string|max:1000'
        ];
    }

    /**
     * Validation rules for rescheduling an inspection
     */
    public function getRescheduleRules(): array
    {
        return [
            'scheduled_date' => 'required|date|after:today',
            'scheduled_time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'reason' => 'required|string|min:10|max:500'
        ];
    }

    /**
     * Validation rules for cancelling an inspection
     */
    public function getCancelRules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:500'
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
            'inspection_type.required' => 'Inspection type is required',
            'inspection_type.in' => 'Invalid inspection type selected',
            'scheduled_date.required' => 'Scheduled date is required',
            'scheduled_date.date' => 'Scheduled date must be a valid date',
            'scheduled_date.after' => 'Scheduled date must be in the future',
            'scheduled_time.required' => 'Scheduled time is required',
            'scheduled_time.regex' => 'Scheduled time must be in HH:MM format',
            'inspector_id.required' => 'Inspector ID is required',
            'inspector_id.integer' => 'Inspector ID must be a valid number',
            'inspector_id.min' => 'Inspector ID must be greater than 0',
            'result.required' => 'Inspection result is required',
            'result.in' => 'Invalid inspection result',
            'findings.array' => 'Findings must be provided as an array',
            'recommendations.array' => 'Recommendations must be provided as an array',
            'follow_up_required.boolean' => 'Follow-up required must be true or false',
            'notes.string' => 'Notes must be text',
            'notes.max' => 'Notes cannot exceed specified length',
            'reason.required' => 'Reason is required',
            'reason.min' => 'Reason must be at least 10 characters',
            'reason.max' => 'Reason cannot exceed 500 characters'
        ];
    }

    /**
     * Validate inspection scheduling
     */
    public function validateSchedule(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getScheduleRules());
    }

    /**
     * Validate inspection completion
     */
    public function validateComplete(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getCompleteRules());
    }

    /**
     * Validate inspection rescheduling
     */
    public function validateReschedule(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getRescheduleRules());
    }

    /**
     * Validate inspection cancellation
     */
    public function validateCancel(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getCancelRules());
    }

    /**
     * Additional validation for business rules
     */
    protected function validateInspectionType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['foundation', 'frame', 'insulation', 'linings', 'final', 'compliance'];
        return in_array($value, $validTypes, true);
    }

    protected function validateResult(string $field, mixed $value, array $parameters): bool
    {
        $validResults = ['pass', 'fail', 'conditional'];
        return in_array($value, $validResults, true);
    }

    protected function validateScheduledTime(string $field, mixed $value, array $parameters): bool
    {
        // Check HH:MM format
        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            return false;
        }

        // Check valid time range (8 AM to 6 PM)
        [$hours, $minutes] = explode(':', $value);
        $hours = (int)$hours;
        $minutes = (int)$minutes;

        return $hours >= 8 && $hours <= 18 && $minutes >= 0 && $minutes <= 59;
    }

    /**
     * Validate that inspection date is not on weekend
     */
    public function validateNotWeekend(array $data): bool
    {
        if (!isset($data['scheduled_date'])) {
            return true; // Skip if date not provided
        }

        $date = date_create($data['scheduled_date']);
        if (!$date) {
            return false;
        }

        $dayOfWeek = (int)$date->format('w'); // 0 = Sunday, 6 = Saturday
        return $dayOfWeek >= 1 && $dayOfWeek <= 5; // Monday to Friday
    }

    /**
     * Validate that inspection time is within business hours
     */
    public function validateBusinessHours(array $data): bool
    {
        if (!isset($data['scheduled_time'])) {
            return true; // Skip if time not provided
        }

        return $this->validateScheduledTime('scheduled_time', $data['scheduled_time'], []);
    }

    /**
     * Validate inspection sequence (certain inspections must be completed before others)
     */
    public function validateInspectionSequence(string $applicationId, string $inspectionType): bool
    {
        // This would typically check against the database to ensure proper sequence
        // For example, foundation inspection should be done before frame inspection

        $sequenceRules = [
            'foundation' => [], // Can be first
            'frame' => ['foundation'],
            'insulation' => ['frame'],
            'linings' => ['insulation'],
            'final' => ['linings'],
            'compliance' => ['final']
        ];

        if (!isset($sequenceRules[$inspectionType])) {
            return false;
        }

        $requiredInspections = $sequenceRules[$inspectionType];

        // In a real implementation, you would check the database
        // to ensure all required inspections have been completed
        // For now, we'll return true as this is a simplified example

        return true;
    }

    /**
     * Comprehensive validation with business rules
     */
    public function validateWithBusinessRules(array $data, string $operation = 'schedule'): array
    {
        $errors = [];

        // Basic validation
        $rules = match ($operation) {
            'schedule' => $this->getScheduleRules(),
            'complete' => $this->getCompleteRules(),
            'reschedule' => $this->getRescheduleRules(),
            'cancel' => $this->getCancelRules(),
            default => []
        };

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Business rule validations
        if ($operation === 'schedule') {
            if (!$this->validateNotWeekend($data)) {
                $errors[] = 'Inspections cannot be scheduled on weekends';
            }

            if (!$this->validateBusinessHours($data)) {
                $errors[] = 'Inspection time must be between 8:00 AM and 6:00 PM';
            }

            if (isset($data['application_id']) && isset($data['inspection_type'])) {
                if (!$this->validateInspectionSequence($data['application_id'], $data['inspection_type'])) {
                    $errors[] = 'Inspection sequence requirements not met';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate bulk inspection operations
     */
    public function validateBulkOperation(array $inspectionIds, string $operation): array
    {
        $errors = [];

        if (empty($inspectionIds)) {
            $errors[] = 'At least one inspection must be selected';
            return $errors;
        }

        if (count($inspectionIds) > 50) {
            $errors[] = 'Cannot process more than 50 inspections at once';
        }

        $validOperations = ['reschedule', 'cancel', 'complete'];
        if (!in_array($operation, $validOperations)) {
            $errors[] = 'Invalid bulk operation specified';
        }

        return $errors;
    }

    /**
     * Validate inspection findings format
     */
    public function validateFindingsFormat(array $findings): bool
    {
        if (empty($findings)) {
            return true; // Empty findings are allowed
        }

        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                return false;
            }

            // Check required fields in each finding
            if (!isset($finding['description']) || !is_string($finding['description'])) {
                return false;
            }

            if (isset($finding['severity']) &&
                !in_array($finding['severity'], ['low', 'medium', 'high', 'critical'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate inspection recommendations format
     */
    public function validateRecommendationsFormat(array $recommendations): bool
    {
        if (empty($recommendations)) {
            return true; // Empty recommendations are allowed
        }

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                return false;
            }

            // Check required fields in each recommendation
            if (!isset($recommendation['description']) || !is_string($recommendation['description'])) {
                return false;
            }

            if (isset($recommendation['priority']) &&
                !in_array($recommendation['priority'], ['low', 'medium', 'high'])) {
                return false;
            }
        }

        return true;
    }
}

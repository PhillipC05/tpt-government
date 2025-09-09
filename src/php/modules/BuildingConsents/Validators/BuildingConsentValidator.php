<?php
/**
 * TPT Government Platform - Building Consent Validator
 *
 * Validator for building consent application data
 */

namespace Modules\BuildingConsents\Validators;

use Core\Validation\BaseValidator;

class BuildingConsentValidator extends BaseValidator
{
    /**
     * Validation rules for creating a building consent application
     */
    public function getCreateRules(): array
    {
        return [
            'project_name' => 'required|string|min:3|max:255',
            'project_type' => 'required|in:new_construction,addition,alteration,demolition',
            'property_address' => 'required|string|min:10|max:500',
            'property_type' => 'required|in:residential,commercial,industrial,mixed_use',
            'consent_type' => 'required|in:full,outline,discretionary',
            'estimated_cost' => 'required|numeric|min:0',
            'floor_area' => 'required|numeric|min:1',
            'storeys' => 'required|integer|min:1|max:50',
            'architect_id' => 'integer|min:1',
            'contractor_id' => 'integer|min:1',
            'owner_id' => 'required|integer|min:1',
            'documents' => 'array',
            'notes' => 'string|max:1000'
        ];
    }

    /**
     * Validation rules for updating a building consent application
     */
    public function getUpdateRules(): array
    {
        return [
            'project_name' => 'string|min:3|max:255',
            'project_type' => 'in:new_construction,addition,alteration,demolition',
            'property_address' => 'string|min:10|max:500',
            'property_type' => 'in:residential,commercial,industrial,mixed_use',
            'consent_type' => 'in:full,outline,discretionary',
            'estimated_cost' => 'numeric|min:0',
            'floor_area' => 'numeric|min:1',
            'storeys' => 'integer|min:1|max:50',
            'architect_id' => 'integer|min:1',
            'contractor_id' => 'integer|min:1',
            'documents' => 'array',
            'notes' => 'string|max:1000'
        ];
    }

    /**
     * Validation rules for submitting an application
     */
    public function getSubmitRules(): array
    {
        return [
            'application_id' => 'required|string|regex:/^BC\d{4}\d{6}$/',
            'status' => 'required|in:draft'
        ];
    }

    /**
     * Validation rules for reviewing an application
     */
    public function getReviewRules(): array
    {
        return [
            'notes' => 'required|string|min:10|max:1000',
            'decision' => 'required|in:approve,reject,request_info'
        ];
    }

    /**
     * Validation rules for approving an application
     */
    public function getApproveRules(): array
    {
        return [
            'conditions' => 'array',
            'notes' => 'string|max:500',
            'consent_number' => 'required|string|regex:/^BCN\d{4}\d{6}$/'
        ];
    }

    /**
     * Validation rules for rejecting an application
     */
    public function getRejectRules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:1000',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Custom validation messages
     */
    public function getCustomMessages(): array
    {
        return [
            'project_name.required' => 'Project name is required',
            'project_name.min' => 'Project name must be at least 3 characters',
            'project_name.max' => 'Project name cannot exceed 255 characters',
            'project_type.required' => 'Project type is required',
            'project_type.in' => 'Invalid project type selected',
            'property_address.required' => 'Property address is required',
            'property_address.min' => 'Property address must be at least 10 characters',
            'property_address.max' => 'Property address cannot exceed 500 characters',
            'property_type.required' => 'Property type is required',
            'property_type.in' => 'Invalid property type selected',
            'consent_type.required' => 'Consent type is required',
            'consent_type.in' => 'Invalid consent type selected',
            'estimated_cost.required' => 'Estimated cost is required',
            'estimated_cost.numeric' => 'Estimated cost must be a valid number',
            'estimated_cost.min' => 'Estimated cost cannot be negative',
            'floor_area.required' => 'Floor area is required',
            'floor_area.numeric' => 'Floor area must be a valid number',
            'floor_area.min' => 'Floor area must be at least 1 square meter',
            'storeys.required' => 'Number of storeys is required',
            'storeys.integer' => 'Number of storeys must be a whole number',
            'storeys.min' => 'Building must have at least 1 storey',
            'storeys.max' => 'Building cannot exceed 50 storeys',
            'architect_id.integer' => 'Architect ID must be a valid number',
            'architect_id.min' => 'Architect ID must be greater than 0',
            'contractor_id.integer' => 'Contractor ID must be a valid number',
            'contractor_id.min' => 'Contractor ID must be greater than 0',
            'owner_id.required' => 'Owner ID is required',
            'owner_id.integer' => 'Owner ID must be a valid number',
            'owner_id.min' => 'Owner ID must be greater than 0',
            'documents.array' => 'Documents must be provided as an array',
            'notes.string' => 'Notes must be text',
            'notes.max' => 'Notes cannot exceed 1000 characters',
            'application_id.required' => 'Application ID is required',
            'application_id.regex' => 'Invalid application ID format',
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
            'decision.required' => 'Decision is required',
            'decision.in' => 'Invalid decision value',
            'reason.required' => 'Rejection reason is required',
            'reason.min' => 'Rejection reason must be at least 10 characters',
            'reason.max' => 'Rejection reason cannot exceed 1000 characters',
            'consent_number.required' => 'Consent number is required',
            'consent_number.regex' => 'Invalid consent number format'
        ];
    }

    /**
     * Validate building consent creation
     */
    public function validateCreate(array $data): bool
    {
        return $this->validate($data, $this->getCreateRules());
    }

    /**
     * Validate building consent update
     */
    public function validateUpdate(array $data): bool
    {
        return $this->validate($data, $this->getUpdateRules());
    }

    /**
     * Validate application submission
     */
    public function validateSubmit(array $data): bool
    {
        return $this->validate($data, $this->getSubmitRules());
    }

    /**
     * Validate application review
     */
    public function validateReview(array $data): bool
    {
        return $this->validate($data, $this->getReviewRules());
    }

    /**
     * Validate application approval
     */
    public function validateApprove(array $data): bool
    {
        return $this->validate($data, $this->getApproveRules());
    }

    /**
     * Validate application rejection
     */
    public function validateReject(array $data): bool
    {
        return $this->validate($data, $this->getRejectRules());
    }

    /**
     * Validate with custom messages
     */
    public function validateWithMessages(array $data, array $rules): bool
    {
        $this->setMessages($this->getCustomMessages());
        return $this->validate($data, $rules);
    }

    /**
     * Additional validation for business rules
     */
    protected function validateProjectType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['new_construction', 'addition', 'alteration', 'demolition'];
        return in_array($value, $validTypes, true);
    }

    protected function validatePropertyType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['residential', 'commercial', 'industrial', 'mixed_use'];
        return in_array($value, $validTypes, true);
    }

    protected function validateConsentType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['full', 'outline', 'discretionary'];
        return in_array($value, $validTypes, true);
    }

    protected function validateApplicationId(string $field, mixed $value, array $parameters): bool
    {
        // BC + 4 digits year + 6 digits sequential number
        return preg_match('/^BC\d{4}\d{6}$/', $value) === 1;
    }

    protected function validateConsentNumber(string $field, mixed $value, array $parameters): bool
    {
        // BCN + 4 digits year + 6 digits sequential number
        return preg_match('/^BCN\d{4}\d{6}$/', $value) === 1;
    }

    /**
     * Validate that estimated cost is reasonable for floor area
     */
    public function validateCostPerArea(array $data): bool
    {
        if (!isset($data['estimated_cost']) || !isset($data['floor_area'])) {
            return true; // Skip if data not available
        }

        $cost = (float)$data['estimated_cost'];
        $area = (float)$data['floor_area'];

        if ($area <= 0) {
            return false;
        }

        $costPerSqm = $cost / $area;

        // Reasonable range: $500 - $10,000 per square meter
        return $costPerSqm >= 500 && $costPerSqm <= 10000;
    }

    /**
     * Validate that storeys are reasonable for building type
     */
    public function validateStoreysForType(array $data): bool
    {
        if (!isset($data['storeys']) || !isset($data['property_type'])) {
            return true; // Skip if data not available
        }

        $storeys = (int)$data['storeys'];
        $propertyType = $data['property_type'];

        switch ($propertyType) {
            case 'residential':
                return $storeys <= 3; // Most residential buildings are 1-3 storeys
            case 'commercial':
                return $storeys <= 20; // Commercial can be taller
            case 'industrial':
                return $storeys <= 5; // Industrial usually lower
            case 'mixed_use':
                return $storeys <= 15; // Mixed use moderate height
            default:
                return true;
        }
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
            'update' => $this->getUpdateRules(),
            'submit' => $this->getSubmitRules(),
            'review' => $this->getReviewRules(),
            'approve' => $this->getApproveRules(),
            'reject' => $this->getRejectRules(),
            default => []
        };

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Business rule validations
        if (!empty($data)) {
            if (!$this->validateCostPerArea($data)) {
                $errors[] = 'Estimated cost per square meter is outside reasonable range ($500-$10,000/m²)';
            }

            if (!$this->validateStoreysForType($data)) {
                $errors[] = 'Number of storeys is not reasonable for the selected property type';
            }
        }

        return $errors;
    }
}

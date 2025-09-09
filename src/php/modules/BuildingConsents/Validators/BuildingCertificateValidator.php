<?php
/**
 * TPT Government Platform - Building Certificate Validator
 *
 * Validator for building certificate data
 */

namespace Modules\BuildingConsents\Validators;

use Core\Validation\BaseValidator;

class BuildingCertificateValidator extends BaseValidator
{
    /**
     * Validation rules for issuing a certificate
     */
    public function getIssueRules(): array
    {
        return [
            'application_id' => 'required|string|regex:/^BC\d{4}\d{6}$/',
            'certificate_type' => 'required|in:code_compliance,building_consent,occupancy,completion',
            'issued_by' => 'required|integer|min:1',
            'conditions' => 'array',
            'limitations' => 'string|max:1000',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for renewing a certificate
     */
    public function getRenewRules(): array
    {
        return [
            'new_expiry_date' => 'required|date|after:today',
            'conditions' => 'array',
            'limitations' => 'string|max:1000',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for revoking a certificate
     */
    public function getRevokeRules(): array
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
            'application_id.required' => 'Application ID is required',
            'application_id.regex' => 'Invalid application ID format',
            'certificate_type.required' => 'Certificate type is required',
            'certificate_type.in' => 'Invalid certificate type selected',
            'issued_by.required' => 'Issuer ID is required',
            'issued_by.integer' => 'Issuer ID must be a valid number',
            'issued_by.min' => 'Issuer ID must be greater than 0',
            'new_expiry_date.required' => 'New expiry date is required',
            'new_expiry_date.date' => 'New expiry date must be a valid date',
            'new_expiry_date.after' => 'New expiry date must be in the future',
            'conditions.array' => 'Conditions must be provided as an array',
            'limitations.string' => 'Limitations must be text',
            'limitations.max' => 'Limitations cannot exceed 1000 characters',
            'notes.string' => 'Notes must be text',
            'notes.max' => 'Notes cannot exceed 500 characters',
            'reason.required' => 'Revocation reason is required',
            'reason.min' => 'Revocation reason must be at least 10 characters',
            'reason.max' => 'Revocation reason cannot exceed 1000 characters'
        ];
    }

    /**
     * Validate certificate issuance
     */
    public function validateIssue(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getIssueRules());
    }

    /**
     * Validate certificate renewal
     */
    public function validateRenew(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getRenewRules());
    }

    /**
     * Validate certificate revocation
     */
    public function validateRevoke(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getRevokeRules());
    }

    /**
     * Additional validation for business rules
     */
    protected function validateCertificateType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['code_compliance', 'building_consent', 'occupancy', 'completion'];
        return in_array($value, $validTypes, true);
    }

    /**
     * Validate that application is eligible for certificate
     */
    public function validateApplicationEligibility(string $applicationId, string $certificateType): bool
    {
        // This would typically check the database to ensure:
        // 1. Application exists and is approved
        // 2. All required inspections are completed
        // 3. All fees are paid
        // 4. All compliance requirements are met

        // For different certificate types, different requirements apply
        $requirements = match ($certificateType) {
            'code_compliance' => ['approved', 'inspections_completed', 'fees_paid'],
            'building_consent' => ['approved', 'inspections_completed'],
            'occupancy' => ['approved', 'inspections_completed', 'fees_paid', 'final_inspection_passed'],
            'completion' => ['approved', 'inspections_completed', 'fees_paid', 'final_inspection_passed'],
            default => []
        };

        // In a real implementation, you would check these requirements
        // For now, we'll return true as this is a simplified example

        return true;
    }

    /**
     * Validate certificate expiry date based on type
     */
    public function validateExpiryDate(string $certificateType, ?string $expiryDate): bool
    {
        // Some certificates don't expire (e.g., code compliance)
        $noExpiryTypes = ['code_compliance'];

        if (in_array($certificateType, $noExpiryTypes)) {
            return $expiryDate === null;
        }

        // Other certificates must have a reasonable expiry date
        if (!$expiryDate) {
            return false;
        }

        $expiry = date_create($expiryDate);
        $now = date_create();

        if (!$expiry || !$now) {
            return false;
        }

        // Certificate should expire within 1-10 years from now
        $interval = date_diff($now, $expiry);
        $years = (int)$interval->format('%r%y');

        return $years >= 1 && $years <= 10;
    }

    /**
     * Validate conditions format
     */
    public function validateConditionsFormat(array $conditions): bool
    {
        if (empty($conditions)) {
            return true; // Empty conditions are allowed
        }

        foreach ($conditions as $condition) {
            if (!is_string($condition) || empty(trim($condition))) {
                return false;
            }

            // Check minimum and maximum length
            $length = strlen(trim($condition));
            if ($length < 10 || $length > 500) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate certificate number format
     */
    public function validateCertificateNumber(string $certificateNumber): bool
    {
        // Certificate number format: PREFIX + YYYY + MMDD + SEQUENCE
        // Example: CC20241215001 (Code Compliance, Dec 15 2024, sequence 001)

        $pattern = '/^(CC|BC|OC|CP)\d{8}\d{3}$/';
        return preg_match($pattern, $certificateNumber) === 1;
    }

    /**
     * Comprehensive validation with business rules
     */
    public function validateWithBusinessRules(array $data, string $operation = 'issue'): array
    {
        $errors = [];

        // Basic validation
        $rules = match ($operation) {
            'issue' => $this->getIssueRules(),
            'renew' => $this->getRenewRules(),
            'revoke' => $this->getRevokeRules(),
            default => []
        };

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Business rule validations
        if ($operation === 'issue') {
            if (isset($data['application_id']) && isset($data['certificate_type'])) {
                if (!$this->validateApplicationEligibility($data['application_id'], $data['certificate_type'])) {
                    $errors[] = 'Application is not eligible for this certificate type';
                }
            }

            if (isset($data['certificate_type']) && isset($data['expiry_date'])) {
                if (!$this->validateExpiryDate($data['certificate_type'], $data['expiry_date'])) {
                    $errors[] = 'Invalid expiry date for certificate type';
                }
            }

            if (isset($data['conditions']) && is_array($data['conditions'])) {
                if (!$this->validateConditionsFormat($data['conditions'])) {
                    $errors[] = 'Certificate conditions format is invalid';
                }
            }
        }

        if ($operation === 'renew') {
            if (isset($data['certificate_type']) && isset($data['new_expiry_date'])) {
                if (!$this->validateExpiryDate($data['certificate_type'], $data['new_expiry_date'])) {
                    $errors[] = 'Invalid renewal expiry date for certificate type';
                }
            }

            if (isset($data['conditions']) && is_array($data['conditions'])) {
                if (!$this->validateConditionsFormat($data['conditions'])) {
                    $errors[] = 'Renewal conditions format is invalid';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate bulk certificate operations
     */
    public function validateBulkOperation(array $certificateIds, string $operation): array
    {
        $errors = [];

        if (empty($certificateIds)) {
            $errors[] = 'At least one certificate must be selected';
            return $errors;
        }

        if (count($certificateIds) > 20) {
            $errors[] = 'Cannot process more than 20 certificates at once';
        }

        $validOperations = ['renew', 'revoke'];
        if (!in_array($operation, $validOperations)) {
            $errors[] = 'Invalid bulk operation specified';
        }

        return $errors;
    }

    /**
     * Validate certificate transfer
     */
    public function validateTransfer(array $data): array
    {
        $errors = [];

        $rules = [
            'certificate_id' => 'required|string',
            'new_owner_id' => 'required|integer|min:1',
            'transfer_reason' => 'required|string|min:10|max:500',
            'transfer_documents' => 'array'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for transfer
        if (isset($data['certificate_id'])) {
            // Check if certificate exists and is active
            // This would typically involve a database check
        }

        return $errors;
    }

    /**
     * Validate certificate amendment
     */
    public function validateAmendment(array $data): array
    {
        $errors = [];

        $rules = [
            'certificate_id' => 'required|string',
            'amendment_type' => 'required|in:conditions,limitations,expiry_date',
            'amendment_reason' => 'required|string|min:10|max:500',
            'new_value' => 'required'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional validation based on amendment type
        if (isset($data['amendment_type']) && isset($data['new_value'])) {
            switch ($data['amendment_type']) {
                case 'conditions':
                    if (!is_array($data['new_value']) || !$this->validateConditionsFormat($data['new_value'])) {
                        $errors[] = 'New conditions format is invalid';
                    }
                    break;
                case 'limitations':
                    if (!is_string($data['new_value']) || strlen($data['new_value']) > 1000) {
                        $errors[] = 'New limitations format is invalid';
                    }
                    break;
                case 'expiry_date':
                    if (!$this->validateExpiryDate('generic', $data['new_value'])) {
                        $errors[] = 'New expiry date is invalid';
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Validate certificate duplicate request
     */
    public function validateDuplicateRequest(array $data): array
    {
        $errors = [];

        $rules = [
            'certificate_id' => 'required|string',
            'request_reason' => 'required|string|min:10|max:500',
            'delivery_method' => 'required|in:email,post,pickup',
            'recipient_details' => 'required|array'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Validate recipient details based on delivery method
        if (isset($data['delivery_method']) && isset($data['recipient_details'])) {
            $recipientDetails = $data['recipient_details'];

            switch ($data['delivery_method']) {
                case 'email':
                    if (!isset($recipientDetails['email']) || !filter_var($recipientDetails['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Valid email address is required for email delivery';
                    }
                    break;
                case 'post':
                    $requiredFields = ['name', 'address_line_1', 'city', 'postcode'];
                    foreach ($requiredFields as $field) {
                        if (!isset($recipientDetails[$field]) || empty($recipientDetails[$field])) {
                            $errors[] = "Recipient {$field} is required for postal delivery";
                        }
                    }
                    break;
                case 'pickup':
                    if (!isset($recipientDetails['name']) || empty($recipientDetails['name'])) {
                        $errors[] = 'Recipient name is required for pickup delivery';
                    }
                    break;
            }
        }

        return $errors;
    }
}

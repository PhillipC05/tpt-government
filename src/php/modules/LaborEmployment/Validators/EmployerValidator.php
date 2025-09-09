<?php
/**
 * Labor & Employment Module - Employer Validator
 */

namespace Modules\LaborEmployment\Validators;

use Core\InputValidator;

class EmployerValidator extends InputValidator
{
    /**
     * Validate employer data
     */
    public function validate(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'company_name', 'company_type', 'industry',
            'company_size', 'business_address', 'contact_person',
            'contact_phone', 'contact_email', 'business_description', 'employee_count'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate email format
        if (isset($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Validate website URL if provided
        if (isset($data['website']) && !empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid website URL format";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

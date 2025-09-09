<?php
/**
 * Labor & Employment Module - Job Seeker Validator
 */

namespace Modules\LaborEmployment\Validators;

use Core\InputValidator;

class JobSeekerValidator extends InputValidator
{
    /**
     * Validate job seeker data
     */
    public function validate(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'full_name', 'date_of_birth', 'contact_phone',
            'contact_email', 'address', 'education_level', 'work_experience_years',
            'skills', 'preferred_job_type', 'availability_date'
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

        // Validate date of birth (must be at least 16 years old)
        if (isset($data['date_of_birth'])) {
            $dob = strtotime($data['date_of_birth']);
            $age = (time() - $dob) / (365.25 * 24 * 60 * 60);
            if ($age < 16) {
                $errors[] = "Must be at least 16 years old";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

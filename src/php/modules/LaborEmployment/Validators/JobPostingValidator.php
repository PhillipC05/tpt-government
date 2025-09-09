<?php
/**
 * Labor & Employment Module - Job Posting Validator
 */

namespace Modules\LaborEmployment\Validators;

use Core\InputValidator;

class JobPostingValidator extends InputValidator
{
    /**
     * Validate job posting data
     */
    public function validate(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'employer_id', 'title', 'description', 'job_type',
            'category', 'location', 'requirements'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate salary range
        if (isset($data['salary_min']) && isset($data['salary_max'])) {
            if ($data['salary_min'] > $data['salary_max']) {
                $errors[] = "Minimum salary cannot be greater than maximum salary";
            }
        }

        // Validate application deadline
        if (isset($data['application_deadline'])) {
            $deadline = strtotime($data['application_deadline']);
            if ($deadline < time()) {
                $errors[] = "Application deadline cannot be in the past";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

<?php
/**
 * Labor & Employment Module - Application Validator
 */

namespace Modules\LaborEmployment\Validators;

use Core\InputValidator;

class ApplicationValidator extends InputValidator
{
    /**
     * Validate application data
     */
    public function validate(array $data): array
    {
        $errors = [];

        $requiredFields = ['seeker_id'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

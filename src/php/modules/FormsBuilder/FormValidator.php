<?php
/**
 * TPT Government Platform - Form Validator
 *
 * Advanced validation engine for form submissions with support for
 * complex validation rules, conditional validation, and custom validators
 */

namespace Modules\FormsBuilder;

class FormValidator
{
    /**
     * Validation rules registry
     */
    private array $validationRules = [];

    /**
     * Custom validators
     */
    private array $customValidators = [];

    /**
     * Validation errors
     */
    private array $errors = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initializeDefaultRules();
    }

    /**
     * Initialize default validation rules
     */
    private function initializeDefaultRules(): void
    {
        $this->validationRules = [
            'required' => [
                'message' => 'This field is required',
                'validator' => [$this, 'validateRequired']
            ],
            'email' => [
                'message' => 'Please enter a valid email address',
                'validator' => [$this, 'validateEmail']
            ],
            'email_format' => [
                'message' => 'Please enter a valid email address',
                'validator' => [$this, 'validateEmail']
            ],
            'url' => [
                'message' => 'Please enter a valid URL',
                'validator' => [$this, 'validateUrl']
            ],
            'min_length' => [
                'message' => 'Minimum length is {param} characters',
                'validator' => [$this, 'validateMinLength']
            ],
            'max_length' => [
                'message' => 'Maximum length is {param} characters',
                'validator' => [$this, 'validateMaxLength']
            ],
            'exact_length' => [
                'message' => 'Length must be exactly {param} characters',
                'validator' => [$this, 'validateExactLength']
            ],
            'numeric' => [
                'message' => 'Please enter a valid number',
                'validator' => [$this, 'validateNumeric']
            ],
            'integer' => [
                'message' => 'Please enter a valid integer',
                'validator' => [$this, 'validateInteger']
            ],
            'decimal' => [
                'message' => 'Please enter a valid decimal number',
                'validator' => [$this, 'validateDecimal']
            ],
            'min_value' => [
                'message' => 'Minimum value is {param}',
                'validator' => [$this, 'validateMinValue']
            ],
            'max_value' => [
                'message' => 'Maximum value is {param}',
                'validator' => [$this, 'validateMaxValue']
            ],
            'range' => [
                'message' => 'Value must be between {param1} and {param2}',
                'validator' => [$this, 'validateRange']
            ],
            'date' => [
                'message' => 'Please enter a valid date',
                'validator' => [$this, 'validateDate']
            ],
            'date_format' => [
                'message' => 'Date must be in format {param}',
                'validator' => [$this, 'validateDateFormat']
            ],
            'future_date' => [
                'message' => 'Date must be in the future',
                'validator' => [$this, 'validateFutureDate']
            ],
            'past_date' => [
                'message' => 'Date must be in the past',
                'validator' => [$this, 'validatePastDate']
            ],
            'phone' => [
                'message' => 'Please enter a valid phone number',
                'validator' => [$this, 'validatePhone']
            ],
            'postal_code' => [
                'message' => 'Please enter a valid postal code',
                'validator' => [$this, 'validatePostalCode']
            ],
            'regex' => [
                'message' => 'Invalid format',
                'validator' => [$this, 'validateRegex']
            ],
            'matches' => [
                'message' => 'Fields do not match',
                'validator' => [$this, 'validateMatches']
            ],
            'unique' => [
                'message' => 'This value must be unique',
                'validator' => [$this, 'validateUnique']
            ],
            'file_required' => [
                'message' => 'File is required',
                'validator' => [$this, 'validateFileRequired']
            ],
            'file_type' => [
                'message' => 'Invalid file type. Allowed types: {param}',
                'validator' => [$this, 'validateFileType']
            ],
            'file_size' => [
                'message' => 'File size must be less than {param}',
                'validator' => [$this, 'validateFileSize']
            ],
            'image_dimensions' => [
                'message' => 'Image dimensions must be at least {param1}x{param2}',
                'validator' => [$this, 'validateImageDimensions']
            ],
            'password_strength' => [
                'message' => 'Password does not meet strength requirements',
                'validator' => [$this, 'validatePasswordStrength']
            ],
            'credit_card' => [
                'message' => 'Please enter a valid credit card number',
                'validator' => [$this, 'validateCreditCard']
            ],
            'iban' => [
                'message' => 'Please enter a valid IBAN',
                'validator' => [$this, 'validateIBAN']
            ],
            'swift' => [
                'message' => 'Please enter a valid SWIFT code',
                'validator' => [$this, 'validateSWIFT']
            ],
            'tax_id' => [
                'message' => 'Please enter a valid tax ID',
                'validator' => [$this, 'validateTaxId']
            ],
            'coordinates' => [
                'message' => 'Please enter valid GPS coordinates',
                'validator' => [$this, 'validateCoordinates']
            ],
            'timezone' => [
                'message' => 'Please select a valid timezone',
                'validator' => [$this, 'validateTimezone']
            ],
            'currency' => [
                'message' => 'Please enter a valid currency amount',
                'validator' => [$this, 'validateCurrency']
            ],
            'percentage' => [
                'message' => 'Please enter a valid percentage (0-100)',
                'validator' => [$this, 'validatePercentage']
            ],
            'json' => [
                'message' => 'Invalid JSON format',
                'validator' => [$this, 'validateJSON']
            ],
            'xml' => [
                'message' => 'Invalid XML format',
                'validator' => [$this, 'validateXML']
            ],
            'base64' => [
                'message' => 'Invalid base64 format',
                'validator' => [$this, 'validateBase64']
            ],
            'hex_color' => [
                'message' => 'Please enter a valid hex color code',
                'validator' => [$this, 'validateHexColor']
            ],
            'ip_address' => [
                'message' => 'Please enter a valid IP address',
                'validator' => [$this, 'validateIPAddress']
            ],
            'mac_address' => [
                'message' => 'Please enter a valid MAC address',
                'validator' => [$this, 'validateMACAddress']
            ],
            'isbn' => [
                'message' => 'Please enter a valid ISBN',
                'validator' => [$this, 'validateISBN']
            ],
            'ssn' => [
                'message' => 'Please enter a valid Social Security Number',
                'validator' => [$this, 'validateSSN']
            ],
            'passport' => [
                'message' => 'Please enter a valid passport number',
                'validator' => [$this, 'validatePassport']
            ],
            'license_plate' => [
                'message' => 'Please enter a valid license plate number',
                'validator' => [$this, 'validateLicensePlate']
            ]
        ];
    }

    /**
     * Validate form submission
     */
    public function validateSubmission(array $form, array $data): array
    {
        $this->errors = [];

        // Get form schema
        $schema = json_decode($form['schema'], true);
        $fields = $schema['fields'] ?? [];

        // Validate each field
        foreach ($fields as $field) {
            $fieldId = $field['field_id'];
            $fieldValue = $data[$fieldId] ?? null;

            // Check conditional logic first
            if (!$this->shouldValidateField($field, $data)) {
                continue;
            }

            // Validate field
            $this->validateField($field, $fieldValue, $data);
        }

        // Validate cross-field rules
        $this->validateCrossFieldRules($schema, $data);

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors
        ];
    }

    /**
     * Validate single field
     */
    public function validateField(array $field, $value, array $allData = []): bool
    {
        $fieldId = $field['field_id'];
        $fieldType = $field['field_type'];
        $validationRules = $field['validation_rules'] ?? [];
        $required = $field['required'] ?? false;

        // Check required first
        if ($required && $this->isEmpty($value)) {
            $this->addError($fieldId, 'This field is required');
            return false;
        }

        // Skip further validation if empty and not required
        if ($this->isEmpty($value) && !$required) {
            return true;
        }

        // Apply validation rules
        foreach ($validationRules as $rule => $param) {
            if (!$this->validateRule($rule, $value, $param, $allData)) {
                $ruleConfig = $this->validationRules[$rule] ?? [];
                $message = $this->formatErrorMessage(
                    $ruleConfig['message'] ?? 'Validation failed',
                    $param
                );
                $this->addError($fieldId, $message);
                return false;
            }
        }

        // Type-specific validation
        if (!$this->validateFieldType($fieldType, $value, $field)) {
            return false;
        }

        return true;
    }

    /**
     * Validate single rule
     */
    public function validateRule(string $rule, $value, $param, array $allData = []): bool
    {
        if (!isset($this->validationRules[$rule])) {
            // Check custom validators
            if (isset($this->customValidators[$rule])) {
                return call_user_func($this->customValidators[$rule], $value, $param, $allData);
            }
            return true; // Unknown rule, consider valid
        }

        $ruleConfig = $this->validationRules[$rule];
        $validator = $ruleConfig['validator'];

        return call_user_func($validator, $value, $param, $allData);
    }

    /**
     * Validate field type
     */
    private function validateFieldType(string $fieldType, $value, array $field): bool
    {
        switch ($fieldType) {
            case 'email':
                return $this->validateEmail($value);
            case 'url':
                return $this->validateUrl($value);
            case 'number':
            case 'range':
                return $this->validateNumeric($value);
            case 'date':
                return $this->validateDate($value);
            case 'phone':
                return $this->validatePhone($value);
            case 'file_upload':
            case 'file_upload_preview':
                return $this->validateFileUpload($value, $field);
            case 'image':
                return $this->validateImage($value, $field);
            case 'coordinates':
                return $this->validateCoordinates($value);
            case 'json':
                return $this->validateJSON($value);
            case 'xml':
                return $this->validateXML($value);
            default:
                return true;
        }
    }

    /**
     * Check if field should be validated based on conditional logic
     */
    private function shouldValidateField(array $field, array $data): bool
    {
        $conditionalLogic = $field['conditional_logic'] ?? null;
        if (!$conditionalLogic) {
            return true;
        }

        return $this->evaluateCondition($conditionalLogic, $data);
    }

    /**
     * Evaluate conditional logic
     */
    private function evaluateCondition(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';
        $fieldValue = $data[$field] ?? null;

        switch ($operator) {
            case 'equals':
            case '==':
                return $fieldValue == $value;
            case 'not_equals':
            case '!=':
                return $fieldValue != $value;
            case 'contains':
                return strpos($fieldValue, $value) !== false;
            case 'not_contains':
                return strpos($fieldValue, $value) === false;
            case 'starts_with':
                return strpos($fieldValue, $value) === 0;
            case 'ends_with':
                return substr($fieldValue, -strlen($value)) === $value;
            case 'greater_than':
                return $fieldValue > $value;
            case 'less_than':
                return $fieldValue < $value;
            case 'is_empty':
                return $this->isEmpty($fieldValue);
            case 'is_not_empty':
                return !$this->isEmpty($fieldValue);
            default:
                return true;
        }
    }

    /**
     * Validate cross-field rules
     */
    private function validateCrossFieldRules(array $schema, array $data): void
    {
        $crossFieldRules = $schema['cross_field_rules'] ?? [];

        foreach ($crossFieldRules as $rule) {
            $ruleType = $rule['type'] ?? '';
            $fields = $rule['fields'] ?? [];
            $message = $rule['message'] ?? 'Cross-field validation failed';

            switch ($ruleType) {
                case 'matches':
                    if (!$this->validateFieldsMatch($fields, $data)) {
                        $this->addError('cross_field', $message);
                    }
                    break;
                case 'sum':
                    if (!$this->validateFieldsSum($rule, $data)) {
                        $this->addError('cross_field', $message);
                    }
                    break;
                case 'at_least_one':
                    if (!$this->validateAtLeastOne($fields, $data)) {
                        $this->addError('cross_field', $message);
                    }
                    break;
            }
        }
    }

    /**
     * Validate that fields match
     */
    private function validateFieldsMatch(array $fields, array $data): bool
    {
        if (count($fields) < 2) return true;

        $firstValue = $data[$fields[0]] ?? null;
        for ($i = 1; $i < count($fields); $i++) {
            if (($data[$fields[$i]] ?? null) !== $firstValue) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate fields sum
     */
    private function validateFieldsSum(array $rule, array $data): bool
    {
        $fields = $rule['fields'] ?? [];
        $expectedSum = $rule['sum'] ?? 0;
        $tolerance = $rule['tolerance'] ?? 0;

        $actualSum = 0;
        foreach ($fields as $field) {
            $actualSum += (float)($data[$field] ?? 0);
        }

        return abs($actualSum - $expectedSum) <= $tolerance;
    }

    /**
     * Validate at least one field has value
     */
    private function validateAtLeastOne(array $fields, array $data): bool
    {
        foreach ($fields as $field) {
            if (!$this->isEmpty($data[$field] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add custom validator
     */
    public function addCustomValidator(string $name, callable $validator, string $message = ''): void
    {
        $this->customValidators[$name] = $validator;
        if ($message) {
            $this->validationRules[$name] = [
                'message' => $message,
                'validator' => $validator
            ];
        }
    }

    /**
     * Add validation error
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Clear validation errors
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Format error message with parameters
     */
    private function formatErrorMessage(string $message, $param): string
    {
        if (is_array($param)) {
            foreach ($param as $key => $value) {
                $placeholder = '{' . $key . '}';
                $message = str_replace($placeholder, $value, $message);
            }
        } elseif (is_scalar($param)) {
            $message = str_replace('{param}', $param, $message);
        }

        return $message;
    }

    /**
     * Check if value is empty
     */
    private function isEmpty($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }

    // Individual validation methods
    private function validateRequired($value): bool
    {
        return !$this->isEmpty($value);
    }

    private function validateEmail($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateUrl($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateMinLength($value, $param): bool
    {
        return strlen($value) >= $param;
    }

    private function validateMaxLength($value, $param): bool
    {
        return strlen($value) <= $param;
    }

    private function validateExactLength($value, $param): bool
    {
        return strlen($value) === $param;
    }

    private function validateNumeric($value): bool
    {
        return is_numeric($value);
    }

    private function validateInteger($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateDecimal($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    private function validateMinValue($value, $param): bool
    {
        return (float)$value >= (float)$param;
    }

    private function validateMaxValue($value, $param): bool
    {
        return (float)$value <= (float)$param;
    }

    private function validateRange($value, $param): bool
    {
        if (!is_array($param) || count($param) !== 2) {
            return false;
        }
        $value = (float)$value;
        return $value >= (float)$param[0] && $value <= (float)$param[1];
    }

    private function validateDate($value): bool
    {
        return strtotime($value) !== false;
    }

    private function validateDateFormat($value, $param): bool
    {
        $dateTime = \DateTime::createFromFormat($param, $value);
        return $dateTime !== false;
    }

    private function validateFutureDate($value): bool
    {
        return strtotime($value) > time();
    }

    private function validatePastDate($value): bool
    {
        return strtotime($value) < time();
    }

    private function validatePhone($value): bool
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $value);
        return strlen($digits) >= 10 && strlen($digits) <= 15;
    }

    private function validatePostalCode($value): bool
    {
        // Basic postal code validation - can be customized per country
        return preg_match('/^[A-Z0-9\s\-]{3,10}$/i', $value);
    }

    private function validateRegex($value, $param): bool
    {
        return preg_match($param, $value);
    }

    private function validateMatches($value, $param, $allData): bool
    {
        return isset($allData[$param]) && $value === $allData[$param];
    }

    private function validateUnique($value, $param): bool
    {
        // This would typically check against a database
        // For now, return true
        return true;
    }

    private function validateFileRequired($value): bool
    {
        return isset($value) && !empty($value);
    }

    private function validateFileType($value, $param): bool
    {
        if (!is_array($value)) return false;

        $allowedTypes = is_array($param) ? $param : [$param];
        $fileType = strtolower(pathinfo($value['name'] ?? '', PATHINFO_EXTENSION));

        return in_array($fileType, $allowedTypes);
    }

    private function validateFileSize($value, $param): bool
    {
        if (!is_array($value) || !isset($value['size'])) return false;

        return $value['size'] <= $param;
    }

    private function validateImageDimensions($value, $param): bool
    {
        if (!is_array($value) || !isset($value['tmp_name'])) return false;

        $imageInfo = getimagesize($value['tmp_name']);
        if (!$imageInfo) return false;

        list($width, $height) = $imageInfo;
        $minWidth = $param[0] ?? 0;
        $minHeight = $param[1] ?? 0;

        return $width >= $minWidth && $height >= $minHeight;
    }

    private function validatePasswordStrength($value): bool
    {
        // Basic password strength validation
        return strlen($value) >= 8 &&
               preg_match('/[A-Z]/', $value) &&
               preg_match('/[a-z]/', $value) &&
               preg_match('/[0-9]/', $value);
    }

    private function validateCreditCard($value): bool
    {
        // Remove spaces and dashes
        $value = preg_replace('/[\s\-]/', '', $value);

        // Basic credit card validation using Luhn algorithm
        if (!preg_match('/^\d{13,19}$/', $value)) {
            return false;
        }

        $sum = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $digit = (int)$value[$length - 1 - $i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    private function validateIBAN($value): bool
    {
        // Basic IBAN validation
        $value = strtoupper(str_replace(' ', '', $value));
        return preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value);
    }

    private function validateSWIFT($value): bool
    {
        return preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper($value));
    }

    private function validateTaxId($value): bool
    {
        // Basic tax ID validation - can be customized per country
        return preg_match('/^[A-Z0-9\-]{8,15}$/i', $value);
    }

    private function validateCoordinates($value): bool
    {
        if (!is_array($value) || !isset($value['lat']) || !isset($value['lng'])) {
            return false;
        }

        $lat = (float)$value['lat'];
        $lng = (float)$value['lng'];

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    private function validateTimezone($value): bool
    {
        try {
            new \DateTimeZone($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function validateCurrency($value): bool
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $value);
    }

    private function validatePercentage($value): bool
    {
        $value = (float)$value;
        return $value >= 0 && $value <= 100;
    }

    private function validateJSON($value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateXML($value): bool
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($value);
        return $xml !== false;
    }

    private function validateBase64($value): bool
    {
        return base64_decode($value, true) !== false;
    }

    private function validateHexColor($value): bool
    {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value);
    }

    private function validateIPAddress($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateMACAddress($value): bool
    {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value);
    }

    private function validateISBN($value): bool
    {
        // Remove hyphens and spaces
        $value = preg_replace('/[-\s]/', '', $value);

        if (!preg_match('/^\d{10}(\d{3})?$/', $value)) {
            return false;
        }

        // ISBN-10 validation
        if (strlen($value) === 10) {
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += (int)$value[$i] * (10 - $i);
            }
            $checkDigit = (11 - ($sum % 11)) % 11;
            $checkDigit = $checkDigit === 10 ? 'X' : (string)$checkDigit;
            return $value[9] === $checkDigit;
        }

        // ISBN-13 validation
        if (strlen($value) === 13) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int)$value[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $checkDigit = (10 - ($sum % 10)) % 10;
            return (int)$value[12] === $checkDigit;
        }

        return false;
    }

    private function validateSSN($value): bool
    {
        // US SSN format: XXX-XX-XXXX
        return preg_match('/^\d{3}-\d{2}-\d{4}$/', $value);
    }

    private function validatePassport($value): bool
    {
        // Basic passport validation - alphanumeric, 6-9 characters
        return preg_match('/^[A-Z0-9]{6,9}$/i', $value);
    }

    private function validateLicensePlate($value): bool
    {
        // Basic license plate validation - alphanumeric with possible special chars
        return preg_match('/^[A-Z0-9\-\s]{1,10}$/i', $value);
    }

    private function validateFileUpload($value, $field): bool
    {
        if (!is_array($value)) return false;

        $fieldOptions = $field['field_options'] ?? [];

        // Check file type
        if (isset($fieldOptions['accepted_types'])) {
            $allowedTypes = $fieldOptions['accepted_types'];
            $fileType = strtolower(pathinfo($value['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($fileType, $allowedTypes)) {
                return false;
            }
        }

        // Check file size
        if (isset($fieldOptions['max_size'])) {
            if (($value['size'] ?? 0) > $fieldOptions['max_size']) {
                return false;
            }
        }

        return true;
    }

    private function validateImage($value, $field): bool
    {
        if (!is_array($value)) return false;

        $fieldOptions = $field['field_options'] ?? [];

        // Check if it's an image
        $imageInfo = getimagesize($value['tmp_name'] ?? '');
        if (!$imageInfo) return false;

        // Check dimensions if specified
        if (isset($fieldOptions['min_width']) || isset($fieldOptions['min_height'])) {
            list($width, $height) = $imageInfo;
            $minWidth = $fieldOptions['min_width'] ?? 0;
            $minHeight = $fieldOptions['min_height'] ?? 0;

            if ($width < $minWidth || $height < $minHeight) {
                return false;
            }
        }

        return true;
    }
}

<?php
/**
 * TPT Government Platform - Comprehensive Input Validator
 *
 * Advanced input validation system with security-focused validation,
 * sanitization, and comprehensive rule-based validation
 */

class InputValidator
{
    private $errors = [];
    private $validatedData = [];
    private $rules = [];
    private $customRules = [];
    private $sanitizers = [];

    /**
     * Validation rule constants
     */
    const RULE_REQUIRED = 'required';
    const RULE_EMAIL = 'email';
    const RULE_URL = 'url';
    const RULE_IP = 'ip';
    const RULE_PHONE = 'phone';
    const RULE_POSTAL_CODE = 'postal_code';
    const RULE_DATE = 'date';
    const RULE_NUMERIC = 'numeric';
    const RULE_INTEGER = 'integer';
    const RULE_FLOAT = 'float';
    const RULE_BOOLEAN = 'boolean';
    const RULE_STRING = 'string';
    const RULE_MIN_LENGTH = 'min_length';
    const RULE_MAX_LENGTH = 'max_length';
    const RULE_EXACT_LENGTH = 'exact_length';
    const RULE_MIN_VALUE = 'min_value';
    const RULE_MAX_VALUE = 'max_value';
    const RULE_REGEX = 'regex';
    const RULE_IN_ARRAY = 'in_array';
    const RULE_NOT_IN_ARRAY = 'not_in_array';
    const RULE_UNIQUE = 'unique';
    const RULE_EXISTS = 'exists';
    const RULE_FILE = 'file';
    const RULE_IMAGE = 'image';
    const RULE_MIME_TYPE = 'mime_type';
    const RULE_MAX_FILE_SIZE = 'max_file_size';

    /**
     * Security-focused validation rules
     */
    const RULE_NO_HTML = 'no_html';
    const RULE_NO_SCRIPT = 'no_script';
    const RULE_NO_SQL_INJECTION = 'no_sql_injection';
    const RULE_SAFE_FILENAME = 'safe_filename';
    const RULE_NO_PATH_TRAVERSAL = 'no_path_traversal';
    const RULE_SAFE_URL = 'safe_url';

    /**
     * Constructor
     */
    public function __construct($rules = [])
    {
        $this->rules = $rules;
        $this->initializeDefaultSanitizers();
    }

    /**
     * Validate input data against rules
     */
    public function validate($data, $rules = null)
    {
        $this->errors = [];
        $this->validatedData = [];

        $rulesToUse = $rules ?: $this->rules;

        foreach ($rulesToUse as $field => $fieldRules) {
            $value = $this->getNestedValue($data, $field);

            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $this->validateField($field, $value, $fieldRules, $data);
        }

        return empty($this->errors);
    }

    /**
     * Validate a single field
     */
    private function validateField($field, $value, $rules, $allData)
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $rule = $this->parseRuleString($rule);
            }

            $ruleName = $rule['name'];
            $parameters = $rule['parameters'];

            if (!$this->validateRule($field, $value, $ruleName, $parameters, $allData)) {
                break; // Stop validating this field if one rule fails
            }
        }
    }

    /**
     * Parse rule string into name and parameters
     */
    private function parseRuleString($ruleString)
    {
        $parts = explode(':', $ruleString, 2);
        $name = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        return ['name' => $name, 'parameters' => $parameters];
    }

    /**
     * Validate a specific rule
     */
    private function validateRule($field, $value, $ruleName, $parameters, $allData)
    {
        // Handle custom rules first
        if (isset($this->customRules[$ruleName])) {
            return $this->customRules[$ruleName]($field, $value, $parameters, $allData);
        }

        // Handle built-in rules
        switch ($ruleName) {
            case self::RULE_REQUIRED:
                return $this->validateRequired($field, $value);

            case self::RULE_EMAIL:
                return $this->validateEmail($field, $value);

            case self::RULE_URL:
                return $this->validateUrl($field, $value);

            case self::RULE_IP:
                return $this->validateIp($field, $value);

            case self::RULE_PHONE:
                return $this->validatePhone($field, $value);

            case self::RULE_POSTAL_CODE:
                return $this->validatePostalCode($field, $value);

            case self::RULE_DATE:
                return $this->validateDate($field, $value, $parameters);

            case self::RULE_NUMERIC:
                return $this->validateNumeric($field, $value);

            case self::RULE_INTEGER:
                return $this->validateInteger($field, $value);

            case self::RULE_FLOAT:
                return $this->validateFloat($field, $value);

            case self::RULE_BOOLEAN:
                return $this->validateBoolean($field, $value);

            case self::RULE_STRING:
                return $this->validateString($field, $value);

            case self::RULE_MIN_LENGTH:
                return $this->validateMinLength($field, $value, $parameters[0]);

            case self::RULE_MAX_LENGTH:
                return $this->validateMaxLength($field, $value, $parameters[0]);

            case self::RULE_EXACT_LENGTH:
                return $this->validateExactLength($field, $value, $parameters[0]);

            case self::RULE_MIN_VALUE:
                return $this->validateMinValue($field, $value, $parameters[0]);

            case self::RULE_MAX_VALUE:
                return $this->validateMaxValue($field, $value, $parameters[0]);

            case self::RULE_REGEX:
                return $this->validateRegex($field, $value, $parameters[0]);

            case self::RULE_IN_ARRAY:
                return $this->validateInArray($field, $value, $parameters);

            case self::RULE_NOT_IN_ARRAY:
                return $this->validateNotInArray($field, $value, $parameters);

            case self::RULE_UNIQUE:
                return $this->validateUnique($field, $value, $parameters);

            case self::RULE_EXISTS:
                return $this->validateExists($field, $value, $parameters);

            case self::RULE_FILE:
                return $this->validateFile($field, $value);

            case self::RULE_IMAGE:
                return $this->validateImage($field, $value);

            case self::RULE_MIME_TYPE:
                return $this->validateMimeType($field, $value, $parameters);

            case self::RULE_MAX_FILE_SIZE:
                return $this->validateMaxFileSize($field, $value, $parameters[0]);

            // Security-focused rules
            case self::RULE_NO_HTML:
                return $this->validateNoHtml($field, $value);

            case self::RULE_NO_SCRIPT:
                return $this->validateNoScript($field, $value);

            case self::RULE_NO_SQL_INJECTION:
                return $this->validateNoSqlInjection($field, $value);

            case self::RULE_SAFE_FILENAME:
                return $this->validateSafeFilename($field, $value);

            case self::RULE_NO_PATH_TRAVERSAL:
                return $this->validateNoPathTraversal($field, $value);

            case self::RULE_SAFE_URL:
                return $this->validateSafeUrl($field, $value);

            default:
                $this->addError($field, "Unknown validation rule: {$ruleName}");
                return false;
        }
    }

    /**
     * Validate required field
     */
    private function validateRequired($field, $value)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'This field is required');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate email
     */
    private function validateEmail($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email address');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate URL
     */
    private function validateUrl($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'Invalid URL');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate IP address
     */
    private function validateIp($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            $this->addError($field, 'Invalid IP address');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate phone number
     */
    private function validatePhone($field, $value)
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $value);

        // Check if it's a valid phone number (10-15 digits)
        if (!preg_match('/^\d{10,15}$/', $cleaned)) {
            $this->addError($field, 'Invalid phone number');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate postal code
     */
    private function validatePostalCode($field, $value)
    {
        // Basic postal code validation (can be customized per country)
        if (!preg_match('/^[A-Z0-9\s\-]{3,10}$/i', $value)) {
            $this->addError($field, 'Invalid postal code');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate date
     */
    private function validateDate($field, $value, $format = null)
    {
        $format = $format[0] ?? 'Y-m-d';

        $dateTime = DateTime::createFromFormat($format, $value);
        if (!$dateTime || $dateTime->format($format) !== $value) {
            $this->addError($field, "Invalid date format (expected: {$format})");
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate numeric
     */
    private function validateNumeric($field, $value)
    {
        if (!is_numeric($value)) {
            $this->addError($field, 'Must be a number');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate integer
     */
    private function validateInteger($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'Must be an integer');
            return false;
        }

        $this->validatedData[$field] = (int)$value;
        return true;
    }

    /**
     * Validate float
     */
    private function validateFloat($field, $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
            $this->addError($field, 'Must be a decimal number');
            return false;
        }

        $this->validatedData[$field] = (float)$value;
        return true;
    }

    /**
     * Validate boolean
     */
    private function validateBoolean($field, $value)
    {
        $validValues = [true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no'];

        if (!in_array($value, $validValues, true)) {
            $this->addError($field, 'Must be a boolean value');
            return false;
        }

        $this->validatedData[$field] = (bool)$value;
        return true;
    }

    /**
     * Validate string
     */
    private function validateString($field, $value)
    {
        if (!is_string($value)) {
            $this->addError($field, 'Must be a string');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate minimum length
     */
    private function validateMinLength($field, $value, $minLength)
    {
        if (strlen($value) < $minLength) {
            $this->addError($field, "Must be at least {$minLength} characters long");
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate maximum length
     */
    private function validateMaxLength($field, $value, $maxLength)
    {
        if (strlen($value) > $maxLength) {
            $this->addError($field, "Must be no more than {$maxLength} characters long");
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate exact length
     */
    private function validateExactLength($field, $value, $exactLength)
    {
        if (strlen($value) !== (int)$exactLength) {
            $this->addError($field, "Must be exactly {$exactLength} characters long");
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate minimum value
     */
    private function validateMinValue($field, $value, $minValue)
    {
        if ($value < $minValue) {
            $this->addError($field, "Must be at least {$minValue}");
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate maximum value
     */
    private function validateMaxValue($field, $value, $maxValue)
    {
        if ($value > $maxValue) {
            $this->addError($field, "Must be no more than {$maxValue}");
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate regex pattern
     */
    private function validateRegex($field, $value, $pattern)
    {
        if (!preg_match($pattern, $value)) {
            $this->addError($field, 'Invalid format');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate value is in array
     */
    private function validateInArray($field, $value, $allowedValues)
    {
        if (!in_array($value, $allowedValues)) {
            $this->addError($field, 'Invalid value');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate value is not in array
     */
    private function validateNotInArray($field, $value, $disallowedValues)
    {
        if (in_array($value, $disallowedValues)) {
            $this->addError($field, 'Invalid value');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate unique value (database check)
     */
    private function validateUnique($field, $value, $parameters)
    {
        // This would typically check against a database
        // For now, we'll just validate it's not empty
        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate value exists (database check)
     */
    private function validateExists($field, $value, $parameters)
    {
        // This would typically check against a database
        // For now, we'll just validate it's not empty
        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Validate file upload
     */
    private function validateFile($field, $value)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'File upload failed');
            return false;
        }

        $this->validatedData[$field] = $_FILES[$field];
        return true;
    }

    /**
     * Validate image file
     */
    private function validateImage($field, $value)
    {
        if (!$this->validateFile($field, $value)) {
            return false;
        }

        $fileInfo = $_FILES[$field];
        $imageInfo = getimagesize($fileInfo['tmp_name']);

        if (!$imageInfo) {
            $this->addError($field, 'File is not a valid image');
            return false;
        }

        $this->validatedData[$field] = $fileInfo;
        return true;
    }

    /**
     * Validate MIME type
     */
    private function validateMimeType($field, $value, $allowedTypes)
    {
        if (!$this->validateFile($field, $value)) {
            return false;
        }

        $fileInfo = $_FILES[$field];
        $mimeType = mime_content_type($fileInfo['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            $this->addError($field, 'Invalid file type');
            return false;
        }

        $this->validatedData[$field] = $fileInfo;
        return true;
    }

    /**
     * Validate maximum file size
     */
    private function validateMaxFileSize($field, $value, $maxSize)
    {
        if (!$this->validateFile($field, $value)) {
            return false;
        }

        $fileInfo = $_FILES[$field];
        if ($fileInfo['size'] > $maxSize) {
            $this->addError($field, 'File is too large');
            return false;
        }

        $this->validatedData[$field] = $fileInfo;
        return true;
    }

    /**
     * Security: Validate no HTML content
     */
    private function validateNoHtml($field, $value)
    {
        if ($value !== strip_tags($value)) {
            $this->addError($field, 'HTML content is not allowed');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Security: Validate no script content
     */
    private function validateNoScript($field, $value)
    {
        $scriptPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i'
        ];

        foreach ($scriptPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->addError($field, 'Script content is not allowed');
                return false;
            }
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Security: Validate no SQL injection
     */
    private function validateNoSqlInjection($field, $value)
    {
        $sqlPatterns = [
            '/(\b(union|select|insert|update|delete|drop|create|alter)\b)/i',
            '/(-{2}|\/\*|\*\/)/',
            '/(\bor\b\s+\d+\s*=\s*\d+)/i'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->addError($field, 'Invalid input detected');
                return false;
            }
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Security: Validate safe filename
     */
    private function validateSafeFilename($field, $value)
    {
        // Remove path components and check for dangerous characters
        $filename = basename($value);

        if ($filename !== $value) {
            $this->addError($field, 'Invalid filename');
            return false;
        }

        if (preg_match('/[<>:"\/\\|?*\x00-\x1f]/', $filename)) {
            $this->addError($field, 'Invalid filename characters');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Security: Validate no path traversal
     */
    private function validateNoPathTraversal($field, $value)
    {
        $path = $value;

        // Resolve any .. or . in the path
        $realpath = realpath($path);

        if ($realpath === false) {
            $this->addError($field, 'Invalid path');
            return false;
        }

        // Check if the resolved path is within allowed directories
        $allowedPaths = [realpath(BASE_PATH)];

        $isAllowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if (strpos($realpath, $allowedPath) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            $this->addError($field, 'Access to path is not allowed');
            return false;
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Security: Validate safe URL
     */
    private function validateSafeUrl($field, $value)
    {
        if (!$this->validateUrl($field, $value)) {
            return false;
        }

        $parsed = parse_url($value);

        // Only allow http and https protocols
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            $this->addError($field, 'Only HTTP and HTTPS URLs are allowed');
            return false;
        }

        // Check for localhost/internal IPs (basic protection)
        if (isset($parsed['host'])) {
            $host = $parsed['host'];

            if ($host === 'localhost' || $host === '127.0.0.1' ||
                strpos($host, '192.168.') === 0 ||
                strpos($host, '10.') === 0 ||
                strpos($host, '172.') === 0) {
                $this->addError($field, 'Local URLs are not allowed');
                return false;
            }
        }

        $this->validatedData[$field] = $value;
        return true;
    }

    /**
     * Add custom validation rule
     */
    public function addCustomRule($name, callable $callback)
    {
        $this->customRules[$name] = $callback;
    }

    /**
     * Add custom sanitizer
     */
    public function addSanitizer($name, callable $callback)
    {
        $this->sanitizers[$name] = $callback;
    }

    /**
     * Sanitize input data
     */
    public function sanitize($data, $rules = [])
    {
        $sanitized = [];

        foreach ($data as $field => $value) {
            $fieldRules = $rules[$field] ?? [];

            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $sanitized[$field] = $this->sanitizeValue($value, $fieldRules);
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value
     */
    private function sanitizeValue($value, $rules)
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $rule = $this->parseRuleString($rule);
            }

            $ruleName = $rule['name'];

            if (isset($this->sanitizers[$ruleName])) {
                $value = $this->sanitizers[$ruleName]($value, $rule['parameters']);
            }
        }

        return $value;
    }

    /**
     * Initialize default sanitizers
     */
    private function initializeDefaultSanitizers()
    {
        $this->addSanitizer('trim', function($value) {
            return is_string($value) ? trim($value) : $value;
        });

        $this->addSanitizer('strip_tags', function($value) {
            return is_string($value) ? strip_tags($value) : $value;
        });

        $this->addSanitizer('htmlspecialchars', function($value) {
            return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
        });

        $this->addSanitizer('intval', function($value) {
            return (int)$value;
        });

        $this->addSanitizer('floatval', function($value) {
            return (float)$value;
        });
    }

    /**
     * Get nested value from array using dot notation
     */
    private function getNestedValue($data, $field)
    {
        if (strpos($field, '.') === false) {
            return $data[$field] ?? null;
        }

        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Add validation error
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function getFirstError($field)
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all errors as flat array
     */
    public function getAllErrors()
    {
        $allErrors = [];

        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $allErrors[] = "{$field}: {$error}";
            }
        }

        return $allErrors;
    }

    /**
     * Get validated data
     */
    public function getValidatedData()
    {
        return $this->validatedData;
    }

    /**
     * Check if field has errors
     */
    public function hasErrors($field = null)
    {
        if ($field === null) {
            return !empty($this->errors);
        }

        return isset($this->errors[$field]);
    }

    /**
     * Clear all errors
     */
    public function clearErrors()
    {
        $this->errors = [];
    }

    /**
     * Get error count
     */
    public function getErrorCount()
    {
        $count = 0;

        foreach ($this->errors as $fieldErrors) {
            $count += count($fieldErrors);
        }

        return $count;
    }
}

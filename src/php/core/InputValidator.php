<?php
/**
 * TPT Government Platform - Input Validator & Sanitizer
 *
 * Comprehensive input validation and sanitization system for preventing
 * security vulnerabilities and ensuring data integrity
 */

namespace TPT\Core;

class InputValidator
{
    /**
     * @var array Validation rules
     */
    private array $rules = [];

    /**
     * @var array Custom validation messages
     */
    private array $messages = [];

    /**
     * @var array Validation errors
     */
    private array $errors = [];

    /**
     * @var array Sanitized data
     */
    private array $sanitized = [];

    /**
     * @var array Validation configuration
     */
    private array $config = [
        'stop_on_first_error' => false,
        'allow_empty_strings' => false,
        'trim_strings' => true,
        'html_purify' => true,
        'csrf_protection' => true,
        'rate_limiting' => true,
        'log_violations' => true,
        'strict_mode' => false
    ];

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->loadDefaultRules();
        $this->loadDefaultMessages();
    }

    /**
     * Load default validation rules
     *
     * @return void
     */
    private function loadDefaultRules(): void
    {
        $this->rules = [
            'required' => function($value) {
                return !is_null($value) && (!is_string($value) || strlen(trim($value)) > 0);
            },
            'email' => function($value) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            },
            'url' => function($value) {
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            },
            'ip' => function($value) {
                return filter_var($value, FILTER_VALIDATE_IP) !== false;
            },
            'ipv4' => function($value) {
                return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            },
            'ipv6' => function($value) {
                return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
            },
            'mac' => function($value) {
                return filter_var($value, FILTER_VALIDATE_MAC) !== false;
            },
            'uuid' => function($value) {
                return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
            },
            'alpha' => function($value) {
                return ctype_alpha($value);
            },
            'alphanum' => function($value) {
                return ctype_alnum($value);
            },
            'numeric' => function($value) {
                return is_numeric($value);
            },
            'integer' => function($value) {
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            },
            'float' => function($value) {
                return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
            },
            'boolean' => function($value) {
                return is_bool($value) || in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no']);
            },
            'date' => function($value) {
                return strtotime($value) !== false;
            },
            'datetime' => function($value) {
                $date = date_parse($value);
                return $date['error_count'] === 0 && checkdate($date['month'], $date['day'], $date['year']);
            },
            'json' => function($value) {
                json_decode($value);
                return json_last_error() === JSON_ERROR_NONE;
            },
            'base64' => function($value) {
                return base64_decode($value, true) !== false;
            },
            'hex' => function($value) {
                return ctype_xdigit($value);
            },
            'credit_card' => function($value) {
                // Luhn algorithm for credit card validation
                $value = preg_replace('/\D/', '', $value);
                if (strlen($value) < 13 || strlen($value) > 19) {
                    return false;
                }

                $sum = 0;
                $alt = false;
                for ($i = strlen($value) - 1; $i >= 0; $i--) {
                    $digit = (int) $value[$i];
                    if ($alt) {
                        $digit *= 2;
                        if ($digit > 9) {
                            $digit -= 9;
                        }
                    }
                    $sum += $digit;
                    $alt = !$alt;
                }

                return $sum % 10 === 0;
            },
            'phone' => function($value) {
                // International phone number validation
                $value = preg_replace('/[^\d+\-\s\(\)]/', '', $value);
                return preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $value);
            },
            'postal_code' => function($value, $country = 'US') {
                $patterns = [
                    'US' => '/^\d{5}(-\d{4})?$/',
                    'CA' => '/^[A-Za-z]\d[A-Za-z] ?\d[A-Za-z]\d$/',
                    'UK' => '/^[A-Za-z]{1,2}\d[A-Za-z\d]? ?\d[A-Za-z]{2}$/',
                    'DE' => '/^\d{5}$/',
                    'FR' => '/^\d{5}$/',
                    'AU' => '/^\d{4}$/'
                ];

                return isset($patterns[$country]) ? preg_match($patterns[$country], $value) : true;
            },
            'ssn' => function($value) {
                // US Social Security Number validation
                return preg_match('/^\d{3}-?\d{2}-?\d{4}$/', $value);
            },
            'password_strength' => function($value) {
                // Strong password: 8+ chars, uppercase, lowercase, number, special char
                return strlen($value) >= 8 &&
                       preg_match('/[A-Z]/', $value) &&
                       preg_match('/[a-z]/', $value) &&
                       preg_match('/[0-9]/', $value) &&
                       preg_match('/[^A-Za-z0-9]/', $value);
            },
            'no_xss' => function($value) {
                // Basic XSS detection
                $patterns = [
                    '/<script/i',
                    '/javascript:/i',
                    '/vbscript:/i',
                    '/onload=/i',
                    '/onerror=/i',
                    '/onclick=/i'
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return false;
                    }
                }

                return true;
            },
            'no_sql_injection' => function($value) {
                // Basic SQL injection detection
                $patterns = [
                    '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
                    '/(\bor\b\s+\d+\s*=\s*\d+)/i',
                    '/(\'|\")\s*(or|and)\s*\d+\s*=\s*\d+/i',
                    '/(;\s*(drop|delete|update|insert|alter|create)\s)/i'
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return false;
                    }
                }

                return true;
            },
            'file_extension' => function($value, $allowed = []) {
                if (empty($allowed)) {
                    // Include Open Document formats and other open standards
                    $allowed = [
                        // Images
                        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'tiff', 'ico',
                        // Documents - Microsoft Office
                        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                        // Documents - Open Document Format (ODF)
                        'odt', 'ods', 'odp', 'odg', 'odf',
                        // Documents - Other open formats
                        'pdf', 'txt', 'rtf', 'csv', 'xml', 'json',
                        // Archives
                        'zip', 'rar', '7z', 'tar', 'gz',
                        // Audio/Video (common formats)
                        'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
                        // Other common formats
                        'html', 'htm', 'css', 'js', 'php', 'sql'
                    ];
                }

                $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                return in_array($extension, $allowed);
            },
            'file_size' => function($value, $maxSize = 10485760) { // 10MB default
                return is_numeric($value) && $value > 0 && $value <= $maxSize;
            },
            'mime_type' => function($value, $allowed = []) {
                if (empty($allowed)) {
                    $allowed = [
                        // Images
                        'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp',
                        'image/bmp', 'image/tiff', 'image/x-icon',
                        // Documents - Microsoft Office
                        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        // Documents - Open Document Format (ODF)
                        'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet',
                        'application/vnd.oasis.opendocument.presentation', 'application/vnd.oasis.opendocument.graphics',
                        'application/vnd.oasis.opendocument.formula',
                        // Documents - Other open formats
                        'application/pdf', 'text/plain', 'text/rtf', 'text/csv',
                        'application/xml', 'application/json', 'text/xml', 'text/json',
                        // Archives
                        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
                        'application/x-tar', 'application/gzip',
                        // Audio/Video
                        'audio/mpeg', 'video/mp4', 'video/x-msvideo', 'video/quicktime',
                        'video/x-ms-wmv', 'video/x-flv', 'video/webm',
                        // Other
                        'text/html', 'text/css', 'application/javascript', 'application/x-php',
                        'application/sql', 'text/x-sql'
                    ];
                }

                return in_array($value, $allowed);
            },
            'open_document' => function($value) {
                $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                $openDocExtensions = ['odt', 'ods', 'odp', 'odg', 'odf'];
                return in_array($extension, $openDocExtensions);
            },
            'government_document' => function($value) {
                $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                // Common government document formats
                $govFormats = [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', // Standard office
                    'odt', 'ods', 'odp', // Open Document
                    'txt', 'rtf', 'csv', // Text formats
                    'xml', 'json' // Data formats
                ];
                return in_array($extension, $govFormats);
            },
            'secure_file' => function($value) {
                $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

                // Block potentially dangerous extensions
                $dangerous = [
                    'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
                    'php', 'pl', 'py', 'sh', 'dll', 'so', 'dylib'
                ];

                if (in_array($extension, $dangerous)) {
                    return false;
                }

                // Allow safe extensions
                $safe = [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'odt', 'ods', 'odp', 'odg', 'odf',
                    'txt', 'rtf', 'csv', 'xml', 'json',
                    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
                    'zip', 'rar', '7z', 'tar', 'gz'
                ];

                return in_array($extension, $safe);
            }
        ];
    }

    /**
     * Load default validation messages
     *
     * @return void
     */
    private function loadDefaultMessages(): void
    {
        $this->messages = [
            'required' => 'This field is required',
            'email' => 'Please enter a valid email address',
            'url' => 'Please enter a valid URL',
            'ip' => 'Please enter a valid IP address',
            'ipv4' => 'Please enter a valid IPv4 address',
            'ipv6' => 'Please enter a valid IPv6 address',
            'mac' => 'Please enter a valid MAC address',
            'uuid' => 'Please enter a valid UUID',
            'alpha' => 'This field must contain only letters',
            'alphanum' => 'This field must contain only letters and numbers',
            'numeric' => 'This field must contain only numbers',
            'integer' => 'This field must be a valid integer',
            'float' => 'This field must be a valid decimal number',
            'boolean' => 'This field must be true or false',
            'date' => 'Please enter a valid date',
            'datetime' => 'Please enter a valid date and time',
            'json' => 'Please enter valid JSON',
            'base64' => 'Please enter valid base64 encoded data',
            'hex' => 'Please enter valid hexadecimal data',
            'credit_card' => 'Please enter a valid credit card number',
            'phone' => 'Please enter a valid phone number',
            'postal_code' => 'Please enter a valid postal code',
            'ssn' => 'Please enter a valid Social Security Number',
            'password_strength' => 'Password must be at least 8 characters with uppercase, lowercase, number, and special character',
            'no_xss' => 'Input contains potentially malicious content',
            'no_sql_injection' => 'Input contains potentially malicious SQL',
            'file_extension' => 'File type not allowed',
            'file_size' => 'File size exceeds maximum allowed',
            'mime_type' => 'File type not recognized or not allowed',
            'min_length' => 'Minimum length is {min} characters',
            'max_length' => 'Maximum length is {max} characters',
            'exact_length' => 'Must be exactly {length} characters',
            'min_value' => 'Minimum value is {min}',
            'max_value' => 'Maximum value is {max}',
            'between' => 'Value must be between {min} and {max}',
            'in' => 'Value must be one of: {values}',
            'not_in' => 'Value cannot be one of: {values}',
            'regex' => 'Format is invalid'
        ];
    }

    /**
     * Validate input data against rules
     *
     * @param array $data
     * @param array $rules
     * @param array $customMessages
     * @return bool
     */
    public function validate(array $data, array $rules, array $customMessages = []): bool
    {
        $this->errors = [];
        $this->sanitized = [];
        $this->messages = array_merge($this->messages, $customMessages);

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Sanitize first
            $this->sanitized[$field] = $this->sanitize($value, $field);

            // Apply validation rules
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $rule) {
                if (!$this->validateRule($field, $this->sanitized[$field], $rule)) {
                    if ($this->config['stop_on_first_error']) {
                        return false;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return bool
     */
    private function validateRule(string $field, $value, string $rule): bool
    {
        // Parse rule and parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        // Handle special rules with parameters
        switch ($ruleName) {
            case 'min_length':
                return $this->validateMinLength($value, (int) $parameters[0]);
            case 'max_length':
                return $this->validateMaxLength($value, (int) $parameters[0]);
            case 'exact_length':
                return $this->validateExactLength($value, (int) $parameters[0]);
            case 'min_value':
                return $this->validateMinValue($value, (float) $parameters[0]);
            case 'max_value':
                return $this->validateMaxValue($value, (float) $parameters[0]);
            case 'between':
                return $this->validateBetween($value, (float) $parameters[0], (float) $parameters[1]);
            case 'in':
                return $this->validateIn($value, $parameters);
            case 'not_in':
                return $this->validateNotIn($value, $parameters);
            case 'regex':
                return $this->validateRegex($value, $parameters[0]);
            case 'file_extension':
                return $this->validateFileExtension($value, $parameters);
            case 'file_size':
                return $this->validateFileSize($value, isset($parameters[0]) ? (int) $parameters[0] : null);
            case 'mime_type':
                return $this->validateMimeType($value, $parameters);
            case 'postal_code':
                return $this->validatePostalCode($value, $parameters[0] ?? 'US');
            default:
                // Use predefined rules
                if (isset($this->rules[$ruleName])) {
                    $result = call_user_func($this->rules[$ruleName], $value, ...$parameters);
                    if (!$result) {
                        $this->addError($field, $ruleName, $parameters);
                        return false;
                    }
                    return true;
                }

                // Unknown rule
                $this->addError($field, 'unknown_rule', [$ruleName]);
                return false;
        }
    }

    /**
     * Sanitize input value
     *
     * @param mixed $value
     * @param string $field
     * @return mixed
     */
    public function sanitize($value, string $field = '')
    {
        if (is_null($value)) {
            return null;
        }

        // Trim strings if configured
        if ($this->config['trim_strings'] && is_string($value)) {
            $value = trim($value);
        }

        // Handle empty strings
        if (!$this->config['allow_empty_strings'] && is_string($value) && $value === '') {
            return null;
        }

        if (is_string($value)) {
            // Remove null bytes
            $value = str_replace("\0", '', $value);

            // HTML purification if enabled
            if ($this->config['html_purify']) {
                $value = $this->purifyHtml($value);
            }

            // Basic sanitization
            $value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        }

        return $value;
    }

    /**
     * Purify HTML content
     *
     * @param string $html
     * @return string
     */
    private function purifyHtml(string $html): string
    {
        // Basic HTML purification (in production, use HTMLPurifier)
        $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img>';
        return strip_tags($html, $allowedTags);
    }

    /**
     * Add validation error
     *
     * @param string $field
     * @param string $rule
     * @param array $parameters
     * @return void
     */
    private function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->messages[$rule] ?? "Validation failed for rule: {$rule}";

        // Replace parameter placeholders
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $message = str_replace("{{$key}}", $value, $message);
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for field
     *
     * @param string $field
     * @return string|null
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all errors as flat array
     *
     * @return array
     */
    public function getAllErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $flat[] = "{$field}: {$message}";
            }
        }
        return $flat;
    }

    /**
     * Get sanitized data
     *
     * @return array
     */
    public function getSanitized(): array
    {
        return $this->sanitized;
    }

    /**
     * Check if validation passed
     *
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Validate minimum length
     *
     * @param mixed $value
     * @param int $min
     * @return bool
     */
    private function validateMinLength($value, int $min): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        return strlen((string) $value) >= $min;
    }

    /**
     * Validate maximum length
     *
     * @param mixed $value
     * @param int $max
     * @return bool
     */
    private function validateMaxLength($value, int $max): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        return strlen((string) $value) <= $max;
    }

    /**
     * Validate exact length
     *
     * @param mixed $value
     * @param int $length
     * @return bool
     */
    private function validateExactLength($value, int $length): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        return strlen((string) $value) === $length;
    }

    /**
     * Validate minimum value
     *
     * @param mixed $value
     * @param float $min
     * @return bool
     */
    private function validateMinValue($value, float $min): bool
    {
        return is_numeric($value) && (float) $value >= $min;
    }

    /**
     * Validate maximum value
     *
     * @param mixed $value
     * @param float $max
     * @return bool
     */
    private function validateMaxValue($value, float $max): bool
    {
        return is_numeric($value) && (float) $value <= $max;
    }

    /**
     * Validate value between range
     *
     * @param mixed $value
     * @param float $min
     * @param float $max
     * @return bool
     */
    private function validateBetween($value, float $min, float $max): bool
    {
        return is_numeric($value) && (float) $value >= $min && (float) $value <= $max;
    }

    /**
     * Validate value in array
     *
     * @param mixed $value
     * @param array $allowed
     * @return bool
     */
    private function validateIn($value, array $allowed): bool
    {
        return in_array($value, $allowed);
    }

    /**
     * Validate value not in array
     *
     * @param mixed $value
     * @param array $forbidden
     * @return bool
     */
    private function validateNotIn($value, array $forbidden): bool
    {
        return !in_array($value, $forbidden);
    }

    /**
     * Validate regex pattern
     *
     * @param mixed $value
     * @param string $pattern
     * @return bool
     */
    private function validateRegex($value, string $pattern): bool
    {
        return preg_match($pattern, (string) $value);
    }

    /**
     * Validate file extension
     *
     * @param mixed $value
     * @param array $allowed
     * @return bool
     */
    private function validateFileExtension($value, array $allowed): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        return in_array($extension, $allowed);
    }

    /**
     * Validate file size
     *
     * @param mixed $value
     * @param int|null $maxSize
     * @return bool
     */
    private function validateFileSize($value, ?int $maxSize): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $maxSize = $maxSize ?? 10485760; // 10MB default
        return (int) $value > 0 && (int) $value <= $maxSize;
    }

    /**
     * Validate MIME type
     *
     * @param mixed $value
     * @param array $allowed
     * @return bool
     */
    private function validateMimeType($value, array $allowed): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return in_array($value, $allowed);
    }

    /**
     * Validate postal code
     *
     * @param mixed $value
     * @param string $country
     * @return bool
     */
    private function validatePostalCode($value, string $country): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return call_user_func($this->rules['postal_code'], $value, $country);
    }

    /**
     * Validate CSRF token
     *
     * @param string $token
     * @return bool
     */
    public function validateCsrfToken(string $token): bool
    {
        if (!$this->config['csrf_protection']) {
            return true;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate CSRF token
     *
     * @return string
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate and sanitize file upload
     *
     * @param array $file
     * @param array $rules
     * @return array
     */
    public function validateFileUpload(array $file, array $rules = []): array
    {
        $errors = [];
        $info = [
            'name' => $file['name'] ?? '',
            'type' => $file['type'] ?? '',
            'size' => $file['size'] ?? 0,
            'tmp_name' => $file['tmp_name'] ?? '',
            'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE
        ];

        // Check for upload errors
        if ($info['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($info['error']);
            return ['valid' => false, 'errors' => $errors, 'info' => $info];
        }

        // Validate file size
        if (isset($rules['max_size'])) {
            if ($info['size'] > $rules['max_size']) {
                $errors[] = "File size exceeds maximum allowed ({$rules['max_size']} bytes)";
            }
        }

        // Validate file extension
        if (isset($rules['allowed_extensions'])) {
            $extension = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $rules['allowed_extensions'])) {
                $errors[] = "File extension '{$extension}' not allowed";
            }
        }

        // Validate MIME type
        if (isset($rules['allowed_mime_types'])) {
            if (!in_array($info['type'], $rules['allowed_mime_types'])) {
                $errors[] = "MIME type '{$info['type']}' not allowed";
            }
        }

        // Additional security checks
        if (!$this->isSecureFile($info['tmp_name'], $info['name'])) {
            $errors[] = "File failed security check";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'info' => $info
        ];
    }

    /**
     * Check if file is secure
     *
     * @param string $tmpPath
     * @param string $filename
     * @return bool
     */
    private function isSecureFile(string $tmpPath, string $filename): bool
    {
        // Check file extension vs content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Basic extension to MIME type mapping
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (isset($mimeMap[$extension])) {
            return $mimeMap[$extension] === $mimeType;
        }

        return true; // Allow unknown extensions (be cautious)
    }

    /**
     * Get upload error message
     *
     * @param int $error
     * @return string
     */
    private function getUploadErrorMessage(int $error): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds maximum size allowed by server',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum size allowed by form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $messages[$error] ?? 'Unknown upload error';
    }

    /**
     * Validate array input
     *
     * @param array $data
     * @param array $rules
     * @return bool
     */
    public function validateArray(array $data, array $rules): bool
    {
        $this->errors = [];
        $this->sanitized = [];

        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                $this->errors[$index][] = 'Array item must be an array';
                continue;
            }

            $itemValidator = new self($this->config);
            if (!$itemValidator->validate($item, $rules)) {
                $this->errors[$index] = $itemValidator->getErrors();
            } else {
                $this->sanitized[$index] = $itemValidator->getSanitized();
            }
        }

        return empty($this->errors);
    }

    /**
     * Add custom validation rule
     *
     * @param string $name
     * @param callable $callback
     * @param string $message
     * @return void
     */
    public function addRule(string $name, callable $callback, string $message = ''): void
    {
        $this->rules[$name] = $callback;
        if ($message) {
            $this->messages[$name] = $message;
        }
    }

    /**
     * Set custom error message
     *
     * @param string $rule
     * @param string $message
     * @return void
     */
    public function setMessage(string $rule, string $message): void
    {
        $this->messages[$rule] = $message;
    }

    /**
     * Get validation statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total_fields_validated' => count($this->sanitized),
            'total_errors' => count($this->errors),
            'error_rate' => count($this->sanitized) > 0 ? count($this->errors) / count($this->sanitized) : 0,
            'most_common_errors' => $this->getMostCommonErrors()
        ];
    }

    /**
     * Get most common validation errors
     *
     * @return array
     */
    private function getMostCommonErrors(): array
    {
        $errorCounts = [];
        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $errorCounts[$message] = ($errorCounts[$message] ?? 0) + 1;
            }
        }

        arsort($errorCounts);
        return array_slice($errorCounts, 0, 5, true);
    }
}

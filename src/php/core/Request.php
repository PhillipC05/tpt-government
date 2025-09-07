<?php
/**
 * TPT Government Platform - HTTP Request Handler
 *
 * Handles HTTP request data with security validation and sanitization.
 */

namespace Core;

class Request
{
    /**
     * GET parameters
     */
    private array $get;

    /**
     * POST parameters
     */
    private array $post;

    /**
     * Server variables
     */
    private array $server;

    /**
     * Request headers
     */
    private array $headers;

    /**
     * Raw request body
     */
    private ?string $body = null;

    /**
     * Parsed JSON body
     */
    private ?array $jsonBody = null;

    /**
     * Files uploaded
     */
    private array $files;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->get = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->server = $_SERVER ?? [];
        $this->files = $_FILES ?? [];
        $this->headers = $this->getHeaders();
    }

    /**
     * Get all headers
     *
     * @return array
     */
    private function getHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback for servers that don't have getallheaders
            foreach ($this->server as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Get request method
     *
     * @return string
     */
    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get request path
     *
     * @return string
     */
    public function getPath(): string
    {
        $path = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($path, PHP_URL_PATH);
        return $path ?? '/';
    }

    /**
     * Get query string
     *
     * @return string
     */
    public function getQueryString(): string
    {
        return $this->server['QUERY_STRING'] ?? '';
    }

    /**
     * Get a GET parameter
     *
     * @param string $key The parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->sanitize($this->get[$key] ?? $default);
    }

    /**
     * Get all GET parameters
     *
     * @return array
     */
    public function getAll(): array
    {
        return array_map([$this, 'sanitize'], $this->get);
    }

    /**
     * Get a POST parameter
     *
     * @param string $key The parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function post(string $key, $default = null)
    {
        return $this->sanitize($this->post[$key] ?? $default);
    }

    /**
     * Get all POST parameters
     *
     * @return array
     */
    public function postAll(): array
    {
        return array_map([$this, 'sanitize'], $this->post);
    }

    /**
     * Get a request header
     *
     * @param string $key The header key
     * @param mixed $default Default value
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get raw request body
     *
     * @return string
     */
    public function getBody(): string
    {
        if ($this->body === null) {
            $this->body = file_get_contents('php://input');
        }
        return $this->body;
    }

    /**
     * Get JSON body as array
     *
     * @return array|null
     */
    public function getJsonBody(): ?array
    {
        if ($this->jsonBody === null) {
            $body = $this->getBody();
            if (!empty($body)) {
                $this->jsonBody = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->jsonBody = null;
                }
            }
        }
        return $this->jsonBody;
    }

    /**
     * Get a file upload
     *
     * @param string $key The file key
     * @return array|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Get all uploaded files
     *
     * @return array
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check if request is API request
     *
     * @return bool
     */
    public function isApiRequest(): bool
    {
        return strpos($this->getPath(), '/api/') === 0;
    }

    /**
     * Check if request is HTTPS
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return isset($this->server['HTTPS']) && $this->server['HTTPS'] === 'on';
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (isset($this->server[$header])) {
                $ip = $this->server[$header];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Sanitize input data
     *
     * @param mixed $data The data to sanitize
     * @return mixed
     */
    private function sanitize($data)
    {
        if (is_string($data)) {
            // Remove null bytes and sanitize
            $data = str_replace("\0", '', $data);
            $data = trim($data);
        } elseif (is_array($data)) {
            $data = array_map([$this, 'sanitize'], $data);
        }

        return $data;
    }

    /**
     * Validate input data
     *
     * @param array $rules Validation rules
     * @param array $data Data to validate (defaults to POST)
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validate(array $rules, array $data = null): array
    {
        if ($data === null) {
            $data = $this->postAll();
        }

        $errors = [];
        $valid = true;

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldErrors = $this->validateField($value, $fieldRules);

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
                $valid = false;
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Validate a single field
     *
     * @param mixed $value The field value
     * @param array|string $rules The validation rules
     * @return array Array of error messages
     */
    private function validateField($value, $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $errors = [];

        foreach ($rules as $rule) {
            $ruleParts = explode(':', $rule);
            $ruleName = $ruleParts[0];
            $ruleParam = $ruleParts[1] ?? null;

            $error = $this->applyRule($value, $ruleName, $ruleParam);
            if ($error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Apply a validation rule
     *
     * @param mixed $value The field value
     * @param string $rule The rule name
     * @param string|null $param The rule parameter
     * @return string|null Error message or null if valid
     */
    private function applyRule($value, string $rule, ?string $param): ?string
    {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    return 'This field is required';
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Invalid email address';
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < (int)$param) {
                    return "Minimum length is {$param} characters";
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > (int)$param) {
                    return "Maximum length is {$param} characters";
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    return 'This field must be numeric';
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    return 'This field must contain only letters';
                }
                break;

            case 'alphanum':
                if (!empty($value) && !ctype_alnum($value)) {
                    return 'This field must contain only letters and numbers';
                }
                break;
        }

        return null;
    }
}

<?php
/**
 * TPT Government Platform - Generic ERP Connector
 *
 * Generic connector for ERP systems that don't have specific implementations.
 * Provides a flexible interface for custom ERP integrations.
 */

namespace Core;

class ERPGenericConnector
{
    /**
     * Configuration
     */
    protected array $config;

    /**
     * HTTP client
     */
    protected HttpClient $httpClient;

    /**
     * Connection status
     */
    protected bool $connected = false;

    /**
     * Connection ID
     */
    protected ?string $connectionId = null;

    /**
     * Base API URL
     */
    protected ?string $baseUrl = null;

    /**
     * Authentication token
     */
    protected ?string $authToken = null;

    /**
     * Constructor
     */
    public function __construct(array $config, HttpClient $httpClient)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;

        // Set base URL if provided
        if (isset($config['base_url'])) {
            $this->baseUrl = rtrim($config['base_url'], '/');
        }
    }

    /**
     * Connect to ERP system
     */
    public function connect()
    {
        try {
            // Validate configuration
            $this->validateConfig();

            // Test connection
            $testResult = $this->testConnection();

            if ($testResult['success']) {
                $this->connected = true;
                $this->connectionId = uniqid('erp_', true);

                // Authenticate if needed
                if (isset($this->config['username']) && isset($this->config['password'])) {
                    $this->authenticate();
                }

                return $this->connectionId;
            }

            return false;

        } catch (\Exception $e) {
            error_log("ERP connection error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect from ERP system
     */
    public function disconnect(): void
    {
        $this->connected = false;
        $this->connectionId = null;
        $this->authToken = null;
    }

    /**
     * Get connection status
     */
    public function getStatus(): array
    {
        if (!$this->connected) {
            return [
                'connected' => false,
                'status' => 'disconnected',
                'message' => 'Not connected to ERP system'
            ];
        }

        try {
            $testResult = $this->testConnection();

            return [
                'connected' => $testResult['success'],
                'status' => $testResult['success'] ? 'healthy' : 'error',
                'message' => $testResult['message'] ?? '',
                'response_time' => $testResult['response_time'] ?? null
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to ERP system
     */
    public function testConnection(): array
    {
        if (!$this->baseUrl) {
            return [
                'success' => false,
                'message' => 'Base URL not configured'
            ];
        }

        $startTime = microtime(true);

        try {
            // Try to access a health/status endpoint
            $testUrl = $this->baseUrl . (isset($this->config['health_endpoint']) ?
                $this->config['health_endpoint'] : '/health');

            $headers = [];
            if ($this->authToken) {
                $headers['Authorization'] = 'Bearer ' . $this->authToken;
            }

            $response = $this->httpClient->get($testUrl, $headers);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'response_time' => $responseTime,
                    'http_code' => $response['http_code']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['error'] ?? 'Connection failed',
                    'response_time' => $responseTime
                ];
            }

        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response_time' => $responseTime
            ];
        }
    }

    /**
     * Fetch data from ERP system
     */
    public function fetchData(string $entityType, array $filters = []): array
    {
        $this->ensureConnected();

        try {
            $endpoint = $this->getEntityEndpoint($entityType);
            $url = $this->baseUrl . $endpoint;

            // Add filters as query parameters
            $params = [];
            foreach ($filters as $key => $value) {
                if (is_array($value)) {
                    $params[$key] = implode(',', $value);
                } else {
                    $params[$key] = $value;
                }
            }

            $headers = $this->getAuthHeaders();

            $response = $this->httpClient->get($url, $headers, ['params' => $params]);

            if ($response['success']) {
                $data = $this->parseResponse($response['body']);
                return $this->normalizeData($data, $entityType);
            } else {
                throw new \Exception("Failed to fetch {$entityType}: " . ($response['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            error_log("ERP fetch error for {$entityType}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send data to ERP system
     */
    public function sendData(string $entityType, array $data): array
    {
        $this->ensureConnected();

        try {
            $endpoint = $this->getEntityEndpoint($entityType);
            $url = $this->baseUrl . $endpoint;

            $headers = $this->getAuthHeaders();
            $headers['Content-Type'] = 'application/json';

            $jsonData = json_encode($data);

            $response = $this->httpClient->post($url, $jsonData, $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $this->parseResponse($response['body']),
                    'http_code' => $response['http_code']
                ];
            } else {
                throw new \Exception("Failed to send {$entityType}: " . ($response['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            error_log("ERP send error for {$entityType}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update data in ERP system
     */
    public function updateData(string $entityType, string $id, array $data): array
    {
        $this->ensureConnected();

        try {
            $endpoint = $this->getEntityEndpoint($entityType);
            $url = $this->baseUrl . $endpoint . '/' . $id;

            $headers = $this->getAuthHeaders();
            $headers['Content-Type'] = 'application/json';

            $jsonData = json_encode($data);

            $response = $this->httpClient->put($url, $jsonData, $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $this->parseResponse($response['body']),
                    'http_code' => $response['http_code']
                ];
            } else {
                throw new \Exception("Failed to update {$entityType}: " . ($response['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            error_log("ERP update error for {$entityType}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete data from ERP system
     */
    public function deleteData(string $entityType, string $id): array
    {
        $this->ensureConnected();

        try {
            $endpoint = $this->getEntityEndpoint($entityType);
            $url = $this->baseUrl . $endpoint . '/' . $id;

            $headers = $this->getAuthHeaders();

            $response = $this->httpClient->delete($url, $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'http_code' => $response['http_code']
                ];
            } else {
                throw new \Exception("Failed to delete {$entityType}: " . ($response['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            error_log("ERP delete error for {$entityType}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Authenticate with ERP system
     */
    protected function authenticate(): void
    {
        if (!isset($this->config['auth_endpoint'])) {
            return;
        }

        $authUrl = $this->baseUrl . $this->config['auth_endpoint'];

        $authData = [
            'username' => $this->config['username'],
            'password' => $this->config['password']
        ];

        // Add any additional auth fields
        if (isset($this->config['auth_fields'])) {
            $authData = array_merge($authData, $this->config['auth_fields']);
        }

        $response = $this->httpClient->post($authUrl, json_encode($authData), [
            'Content-Type' => 'application/json'
        ]);

        if ($response['success']) {
            $authResponse = $this->parseResponse($response['body']);

            // Extract token from response
            $tokenField = $this->config['token_field'] ?? 'token';
            if (isset($authResponse[$tokenField])) {
                $this->authToken = $authResponse[$tokenField];
            }
        } else {
            throw new \Exception("Authentication failed: " . ($response['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Get authentication headers
     */
    protected function getAuthHeaders(): array
    {
        $headers = [];

        if ($this->authToken) {
            $authType = $this->config['auth_type'] ?? 'Bearer';
            $headers['Authorization'] = $authType . ' ' . $this->authToken;
        } elseif (isset($this->config['api_key'])) {
            $headers['X-API-Key'] = $this->config['api_key'];
        }

        return $headers;
    }

    /**
     * Get entity endpoint
     */
    protected function getEntityEndpoint(string $entityType): string
    {
        // Use custom endpoint mapping if provided
        if (isset($this->config['endpoints'][$entityType])) {
            return $this->config['endpoints'][$entityType];
        }

        // Default endpoint pattern
        return '/' . $entityType;
    }

    /**
     * Parse API response
     */
    protected function parseResponse(string $body)
    {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, return as-is
            return $body;
        }

        return $data;
    }

    /**
     * Normalize data from ERP format
     */
    protected function normalizeData($data, string $entityType): array
    {
        // If data is not an array, wrap it
        if (!is_array($data)) {
            return [$data];
        }

        // If data has a specific field containing the records
        if (isset($this->config['data_field']) && isset($data[$this->config['data_field']])) {
            $data = $data[$this->config['data_field']];
        }

        // Ensure we return an array of records
        if (!is_array($data)) {
            return [$data];
        }

        // If it's an associative array with numeric keys, it's already an array of records
        if (array_keys($data) === range(0, count($data) - 1)) {
            return $data;
        }

        // If it's a single record, wrap it in an array
        return [$data];
    }

    /**
     * Validate configuration
     */
    protected function validateConfig(): void
    {
        $required = ['base_url'];

        foreach ($required as $field) {
            if (!isset($this->config[$field]) || empty($this->config[$field])) {
                throw new \Exception("Missing required configuration: {$field}");
            }
        }

        // Validate URL format
        if (!filter_var($this->config['base_url'], FILTER_VALIDATE_URL)) {
            throw new \Exception("Invalid base URL format");
        }
    }

    /**
     * Ensure connection is established
     */
    protected function ensureConnected(): void
    {
        if (!$this->connected) {
            throw new \Exception("Not connected to ERP system");
        }
    }

    /**
     * Get configuration value
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set configuration value
     */
    protected function setConfig(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Execute custom query
     */
    public function executeQuery(string $query, array $params = []): array
    {
        $this->ensureConnected();

        try {
            $endpoint = $this->config['query_endpoint'] ?? '/query';
            $url = $this->baseUrl . $endpoint;

            $data = [
                'query' => $query,
                'parameters' => $params
            ];

            $headers = $this->getAuthHeaders();
            $headers['Content-Type'] = 'application/json';

            $response = $this->httpClient->post($url, json_encode($data), $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $this->parseResponse($response['body'])
                ];
            } else {
                throw new \Exception("Query execution failed: " . ($response['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            error_log("ERP query error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get ERP system information
     */
    public function getSystemInfo(): array
    {
        $this->ensureConnected();

        try {
            $endpoint = $this->config['info_endpoint'] ?? '/info';
            $url = $this->baseUrl . $endpoint;

            $headers = $this->getAuthHeaders();

            $response = $this->httpClient->get($url, $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'info' => $this->parseResponse($response['body'])
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to get system info'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

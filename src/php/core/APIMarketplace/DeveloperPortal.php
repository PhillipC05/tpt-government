<?php
/**
 * TPT Government Platform - Developer Portal
 *
 * Specialized manager for API developer experience and documentation
 */

namespace Core\APIMarketplace;

use Core\Database;

class DeveloperPortal
{
    /**
     * Portal configuration
     */
    private array $config = [
        'enabled' => true,
        'registration_required' => true,
        'api_key_generation' => true,
        'documentation_auto_generation' => true,
        'interactive_console' => true,
        'rate_limit_monitoring' => true,
        'usage_analytics' => true,
        'support_ticketing' => true,
        'webhook_testing' => true
    ];

    /**
     * Registered developers
     */
    private array $developers = [];

    /**
     * API applications
     */
    private array $applications = [];

    /**
     * API documentation
     */
    private array $documentation = [];

    /**
     * Usage statistics
     */
    private array $usageStats = [];

    /**
     * Support tickets
     */
    private array $supportTickets = [];

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge($this->config, $config);
        $this->initializePortal();
    }

    /**
     * Initialize developer portal
     */
    private function initializePortal(): void
    {
        // Load developers and applications
        $this->loadDevelopers();
        $this->loadApplications();

        // Initialize documentation
        $this->initializeDocumentation();

        // Set up usage tracking
        $this->initializeUsageTracking();
    }

    /**
     * Register new developer
     */
    public function registerDeveloper(array $developerData): array
    {
        // Validate developer data
        $validation = $this->validateDeveloperData($developerData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid developer data: ' . $validation['error']
            ];
        }

        $developerId = uniqid('dev_');
        $developer = [
            'id' => $developerId,
            'name' => $developerData['name'],
            'email' => $developerData['email'],
            'company' => $developerData['company'] ?? null,
            'website' => $developerData['website'] ?? null,
            'description' => $developerData['description'] ?? null,
            'api_keys' => [],
            'applications' => [],
            'status' => 'pending',
            'registered_at' => time(),
            'last_login' => null,
            'verification_token' => $this->generateVerificationToken()
        ];

        $this->developers[$developerId] = $developer;
        $this->saveDeveloper($developerId, $developer);

        // Send verification email
        $this->sendVerificationEmail($developer);

        return [
            'success' => true,
            'developer_id' => $developerId,
            'message' => 'Developer registered successfully. Please check your email for verification.'
        ];
    }

    /**
     * Create API application
     */
    public function createApplication(string $developerId, array $appData): array
    {
        if (!isset($this->developers[$developerId])) {
            return [
                'success' => false,
                'error' => 'Developer not found'
            ];
        }

        // Validate application data
        $validation = $this->validateApplicationData($appData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid application data: ' . $validation['error']
            ];
        }

        $appId = uniqid('app_');
        $application = [
            'id' => $appId,
            'developer_id' => $developerId,
            'name' => $appData['name'],
            'description' => $appData['description'],
            'website' => $appData['website'] ?? null,
            'redirect_uris' => $appData['redirect_uris'] ?? [],
            'scopes' => $appData['scopes'] ?? ['read'],
            'api_key' => $this->generateApiKey(),
            'client_secret' => $this->generateClientSecret(),
            'status' => 'active',
            'created_at' => time(),
            'last_used' => null,
            'usage_stats' => [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'average_response_time' => 0
            ]
        ];

        $this->applications[$appId] = $application;
        $this->developers[$developerId]['applications'][] = $appId;

        $this->saveApplication($appId, $application);
        $this->updateDeveloper($developerId, $this->developers[$developerId]);

        return [
            'success' => true,
            'application_id' => $appId,
            'api_key' => $application['api_key'],
            'client_secret' => $application['client_secret']
        ];
    }

    /**
     * Get API documentation
     */
    public function getDocumentation(string $version = 'latest', string $format = 'json'): array
    {
        $docs = $this->documentation[$version] ?? $this->documentation['latest'] ?? [];

        if ($format === 'openapi') {
            return $this->convertToOpenAPI($docs);
        }

        return [
            'success' => true,
            'version' => $version,
            'documentation' => $docs,
            'generated_at' => time()
        ];
    }

    /**
     * Test API endpoint
     */
    public function testEndpoint(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        // Simulate API call for testing
        $testResult = [
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $data,
            'request_headers' => $headers,
            'response' => [
                'status_code' => 200,
                'body' => ['message' => 'Test successful'],
                'headers' => ['Content-Type' => 'application/json']
            ],
            'execution_time' => rand(50, 200),
            'timestamp' => time()
        ];

        // Log test execution
        $this->logApiTest($testResult);

        return [
            'success' => true,
            'test_result' => $testResult
        ];
    }

    /**
     * Get developer dashboard data
     */
    public function getDeveloperDashboard(string $developerId): array
    {
        if (!isset($this->developers[$developerId])) {
            return [
                'success' => false,
                'error' => 'Developer not found'
            ];
        }

        $developer = $this->developers[$developerId];
        $applications = [];

        foreach ($developer['applications'] as $appId) {
            if (isset($this->applications[$appId])) {
                $applications[] = [
                    'id' => $appId,
                    'name' => $this->applications[$appId]['name'],
                    'status' => $this->applications[$appId]['status'],
                    'usage_stats' => $this->applications[$appId]['usage_stats'],
                    'last_used' => $this->applications[$appId]['last_used']
                ];
            }
        }

        return [
            'success' => true,
            'developer' => [
                'id' => $developer['id'],
                'name' => $developer['name'],
                'email' => $developer['email'],
                'status' => $developer['status'],
                'registered_at' => $developer['registered_at']
            ],
            'applications' => $applications,
            'total_applications' => count($applications),
            'active_applications' => count(array_filter($applications, fn($app) => $app['status'] === 'active')),
            'total_api_calls' => array_sum(array_column($applications, 'usage_stats.total_requests'))
        ];
    }

    /**
     * Get usage analytics
     */
    public function getUsageAnalytics(string $developerId = null, array $filters = []): array
    {
        $analytics = [
            'total_developers' => count($this->developers),
            'total_applications' => count($this->applications),
            'active_applications' => count(array_filter($this->applications, fn($app) => $app['status'] === 'active')),
            'total_api_calls' => array_sum(array_map(fn($app) => $app['usage_stats']['total_requests'], $this->applications)),
            'average_response_time' => $this->calculateAverageResponseTime(),
            'top_endpoints' => $this->getTopEndpoints(),
            'error_rate' => $this->calculateErrorRate(),
            'usage_trends' => $this->getUsageTrends($filters)
        ];

        if ($developerId) {
            $analytics['developer_specific'] = $this->getDeveloperSpecificAnalytics($developerId);
        }

        return [
            'success' => true,
            'analytics' => $analytics,
            'generated_at' => time()
        ];
    }

    /**
     * Create support ticket
     */
    public function createSupportTicket(string $developerId, array $ticketData): array
    {
        if (!isset($this->developers[$developerId])) {
            return [
                'success' => false,
                'error' => 'Developer not found'
            ];
        }

        $ticketId = uniqid('ticket_');
        $ticket = [
            'id' => $ticketId,
            'developer_id' => $developerId,
            'subject' => $ticketData['subject'],
            'description' => $ticketData['description'],
            'category' => $ticketData['category'] ?? 'general',
            'priority' => $ticketData['priority'] ?? 'medium',
            'status' => 'open',
            'created_at' => time(),
            'updated_at' => time(),
            'responses' => []
        ];

        $this->supportTickets[$ticketId] = $ticket;
        $this->saveSupportTicket($ticketId, $ticket);

        return [
            'success' => true,
            'ticket_id' => $ticketId,
            'message' => 'Support ticket created successfully'
        ];
    }

    /**
     * Get API status
     */
    public function getApiStatus(): array
    {
        return [
            'success' => true,
            'status' => 'operational',
            'version' => '1.0.0',
            'uptime' => time() - $_SERVER['REQUEST_TIME'] ?? time(),
            'total_endpoints' => count($this->documentation['latest']['endpoints'] ?? []),
            'active_developers' => count(array_filter($this->developers, fn($dev) => $dev['status'] === 'active')),
            'total_applications' => count($this->applications),
            'last_updated' => time()
        ];
    }

    /**
     * Generate API key for application
     */
    public function regenerateApiKey(string $appId): array
    {
        if (!isset($this->applications[$appId])) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        $newApiKey = $this->generateApiKey();
        $this->applications[$appId]['api_key'] = $newApiKey;
        $this->applications[$appId]['updated_at'] = time();

        $this->saveApplication($appId, $this->applications[$appId]);

        return [
            'success' => true,
            'new_api_key' => $newApiKey,
            'message' => 'API key regenerated successfully'
        ];
    }

    /**
     * Validate API key
     */
    public function validateApiKey(string $apiKey): array
    {
        foreach ($this->applications as $appId => $app) {
            if ($app['api_key'] === $apiKey && $app['status'] === 'active') {
                return [
                    'valid' => true,
                    'application_id' => $appId,
                    'developer_id' => $app['developer_id'],
                    'application_name' => $app['name'],
                    'scopes' => $app['scopes']
                ];
            }
        }

        return [
            'valid' => false,
            'error' => 'Invalid API key'
        ];
    }

    // Private helper methods

    private function validateDeveloperData(array $data): array
    {
        if (empty($data['name']) || empty($data['email'])) {
            return ['valid' => false, 'error' => 'Name and email are required'];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }

        // Check if email already exists
        foreach ($this->developers as $developer) {
            if ($developer['email'] === $data['email']) {
                return ['valid' => false, 'error' => 'Email already registered'];
            }
        }

        return ['valid' => true];
    }

    private function validateApplicationData(array $data): array
    {
        if (empty($data['name']) || empty($data['description'])) {
            return ['valid' => false, 'error' => 'Name and description are required'];
        }

        if (strlen($data['name']) < 3 || strlen($data['name']) > 100) {
            return ['valid' => false, 'error' => 'Application name must be between 3 and 100 characters'];
        }

        return ['valid' => true];
    }

    private function generateApiKey(): string
    {
        return 'ak_' . bin2hex(random_bytes(16));
    }

    private function generateClientSecret(): string
    {
        return 'sk_' . bin2hex(random_bytes(32));
    }

    private function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function sendVerificationEmail(array $developer): void
    {
        // In real implementation, send verification email
        // For demo purposes, just log it
    }

    private function convertToOpenAPI(array $docs): array
    {
        // Convert internal documentation to OpenAPI format
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'TPT Government API',
                'version' => '1.0.0',
                'description' => 'Government service API documentation'
            ],
            'paths' => $docs['endpoints'] ?? [],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key'
                    ]
                ]
            ]
        ];
    }

    private function logApiTest(array $testResult): void
    {
        // Log API test execution
    }

    private function calculateAverageResponseTime(): float
    {
        $totalTime = 0;
        $totalRequests = 0;

        foreach ($this->applications as $app) {
            $stats = $app['usage_stats'];
            $totalTime += $stats['average_response_time'] * $stats['total_requests'];
            $totalRequests += $stats['total_requests'];
        }

        return $totalRequests > 0 ? $totalTime / $totalRequests : 0;
    }

    private function getTopEndpoints(): array
    {
        // In real implementation, track endpoint usage
        return [
            ['endpoint' => '/api/v1/services', 'calls' => 1250],
            ['endpoint' => '/api/v1/users', 'calls' => 980],
            ['endpoint' => '/api/v1/documents', 'calls' => 750]
        ];
    }

    private function calculateErrorRate(): float
    {
        $totalRequests = array_sum(array_map(fn($app) => $app['usage_stats']['total_requests'], $this->applications));
        $failedRequests = array_sum(array_map(fn($app) => $app['usage_stats']['failed_requests'], $this->applications));

        return $totalRequests > 0 ? ($failedRequests / $totalRequests) * 100 : 0;
    }

    private function getUsageTrends(array $filters): array
    {
        // Generate usage trends data
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $trends[] = [
                'date' => $date,
                'requests' => rand(1000, 5000),
                'errors' => rand(10, 100)
            ];
        }

        return $trends;
    }

    private function getDeveloperSpecificAnalytics(string $developerId): array
    {
        $developerApps = array_filter($this->applications, fn($app) => $app['developer_id'] === $developerId);

        return [
            'total_applications' => count($developerApps),
            'total_requests' => array_sum(array_map(fn($app) => $app['usage_stats']['total_requests'], $developerApps)),
            'average_response_time' => $this->calculateAverageResponseTime(),
            'error_rate' => $this->calculateErrorRate(),
            'most_used_application' => $this->getMostUsedApplication($developerApps)
        ];
    }

    private function getMostUsedApplication(array $applications): ?array
    {
        if (empty($applications)) return null;

        $mostUsed = null;
        $maxRequests = 0;

        foreach ($applications as $app) {
            if ($app['usage_stats']['total_requests'] > $maxRequests) {
                $maxRequests = $app['usage_stats']['total_requests'];
                $mostUsed = [
                    'id' => $app['id'],
                    'name' => $app['name'],
                    'requests' => $maxRequests
                ];
            }
        }

        return $mostUsed;
    }

    private function loadDevelopers(): void
    {
        // In real implementation, load from database
    }

    private function loadApplications(): void
    {
        // In real implementation, load from database
    }

    private function initializeDocumentation(): void
    {
        // Initialize API documentation
        $this->documentation['latest'] = [
            'version' => '1.0.0',
            'base_url' => '/api/v1',
            'endpoints' => [
                '/services' => [
                    'get' => [
                        'description' => 'Get list of government services',
                        'parameters' => [],
                        'responses' => ['200' => ['description' => 'Success']]
                    ]
                ],
                '/users' => [
                    'get' => [
                        'description' => 'Get user information',
                        'parameters' => [['name' => 'id', 'type' => 'integer']],
                        'responses' => ['200' => ['description' => 'Success']]
                    ]
                ]
            ]
        ];
    }

    private function initializeUsageTracking(): void
    {
        // Initialize usage tracking
    }

    private function saveDeveloper(string $developerId, array $developer): void
    {
        // In real implementation, save to database
    }

    private function saveApplication(string $appId, array $application): void
    {
        // In real implementation, save to database
    }

    private function updateDeveloper(string $developerId, array $developer): void
    {
        // In real implementation, update in database
    }

    private function saveSupportTicket(string $ticketId, array $ticket): void
    {
        // In real implementation, save to database
    }
}

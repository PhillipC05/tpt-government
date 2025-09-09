<?php
/**
 * TPT Government Platform - API Version Manager
 *
 * Handles API versioning, backward compatibility, and version routing
 */

class ApiVersionManager
{
    private $logger;
    private $config;
    private $supportedVersions = ['v1', 'v2'];
    private $defaultVersion = 'v1';
    private $versionMappings = [];

    /**
     * Version compatibility matrix
     */
    private $compatibilityMatrix = [
        'v1' => ['v1'],
        'v2' => ['v1', 'v2']
    ];

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'version_header' => 'X-API-Version',
            'version_param' => 'api_version',
            'default_version' => 'v1',
            'version_cache_ttl' => 3600,
            'enable_backward_compatibility' => true,
            'deprecation_warnings' => true
        ], $config);

        $this->defaultVersion = $this->config['default_version'];

        // Initialize version mappings
        $this->initializeVersionMappings();

        $this->logger->info('API Version Manager initialized', [
            'supported_versions' => $this->supportedVersions,
            'default_version' => $this->defaultVersion
        ]);
    }

    /**
     * Initialize version mappings
     */
    private function initializeVersionMappings()
    {
        // Define endpoint mappings for different versions
        $this->versionMappings = [
            'v1' => [
                'users' => 'src/php/core/UserController.php',
                'auth' => 'src/php/core/AuthController.php',
                'dashboard' => 'src/php/core/DashboardController.php',
                'admin' => 'src/php/core/AdminController.php'
            ],
            'v2' => [
                'users' => 'src/php/core/UserController.php', // Same controller, enhanced methods
                'auth' => 'src/php/core/AuthController.php',
                'dashboard' => 'src/php/core/DashboardController.php',
                'admin' => 'src/php/core/AdminController.php',
                'analytics' => 'src/php/core/Analytics/KPIManager.php',
                'reports' => 'src/php/core/RegulatoryReportingManager.php'
            ]
        ];
    }

    /**
     * Detect API version from request
     */
    public function detectVersion(Request $request)
    {
        // Check header first
        $version = $request->getHeader($this->config['version_header']);

        if (!$version) {
            // Check query parameter
            $version = $request->getQueryParam($this->config['version_param']);
        }

        if (!$version) {
            // Check URL path (e.g., /api/v2/users)
            $path = $request->getPath();
            if (preg_match('#/api/(v\d+)/#', $path, $matches)) {
                $version = $matches[1];
            }
        }

        // Validate version
        if (!$this->isValidVersion($version)) {
            if ($this->config['deprecation_warnings']) {
                $this->logger->warning('Invalid API version requested', [
                    'requested_version' => $version,
                    'defaulting_to' => $this->defaultVersion
                ]);
            }
            $version = $this->defaultVersion;
        }

        return $version;
    }

    /**
     * Validate API version
     */
    public function isValidVersion($version)
    {
        return in_array($version, $this->supportedVersions);
    }

    /**
     * Get controller for version and endpoint
     */
    public function getControllerForVersion($version, $endpoint)
    {
        if (!isset($this->versionMappings[$version])) {
            throw new Exception("Version {$version} not supported");
        }

        if (!isset($this->versionMappings[$version][$endpoint])) {
            // Try fallback to compatible version
            if ($this->config['enable_backward_compatibility']) {
                $compatibleVersions = $this->getCompatibleVersions($version);
                foreach ($compatibleVersions as $compatVersion) {
                    if (isset($this->versionMappings[$compatVersion][$endpoint])) {
                        if ($this->config['deprecation_warnings'] && $compatVersion !== $version) {
                            $this->logger->warning('Using backward compatible endpoint', [
                                'requested_version' => $version,
                                'using_version' => $compatVersion,
                                'endpoint' => $endpoint
                            ]);
                        }
                        return $this->versionMappings[$compatVersion][$endpoint];
                    }
                }
            }

            throw new Exception("Endpoint {$endpoint} not found for version {$version}");
        }

        return $this->versionMappings[$version][$endpoint];
    }

    /**
     * Get compatible versions for a given version
     */
    public function getCompatibleVersions($version)
    {
        return $this->compatibilityMatrix[$version] ?? [$version];
    }

    /**
     * Route request to appropriate version
     */
    public function routeRequest(Request $request, Router $router)
    {
        $version = $this->detectVersion($request);
        $path = $request->getPath();

        // Remove version from path if present
        $path = preg_replace('#/api/(v\d+)/#', '/api/', $path);

        // Add version context to request
        $request->setAttribute('api_version', $version);

        $this->logger->info('Routing API request', [
            'version' => $version,
            'original_path' => $request->getPath(),
            'routed_path' => $path
        ]);

        // Route to version-specific handler
        return $this->handleVersionedRequest($version, $request, $router);
    }

    /**
     * Handle versioned request
     */
    private function handleVersionedRequest($version, Request $request, Router $router)
    {
        $path = $request->getPath();

        // Extract endpoint from path
        if (preg_match('#/api/([^/?]+)#', $path, $matches)) {
            $endpoint = $matches[1];

            try {
                $controllerPath = $this->getControllerForVersion($version, $endpoint);

                // Load and instantiate controller
                require_once $controllerPath;
                $controllerClass = $this->getControllerClassFromPath($controllerPath);

                if (!class_exists($controllerClass)) {
                    throw new Exception("Controller class {$controllerClass} not found");
                }

                $controller = new $controllerClass();

                // Route to appropriate method
                return $this->dispatchToController($controller, $request, $version);

            } catch (Exception $e) {
                $this->logger->error('Error routing versioned request', [
                    'version' => $version,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);

                return $this->createErrorResponse($e->getMessage(), 404);
            }
        }

        return $this->createErrorResponse('Invalid API endpoint', 400);
    }

    /**
     * Dispatch request to controller method
     */
    private function dispatchToController($controller, Request $request, $version)
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Extract action from path
        $action = $this->extractActionFromPath($path);

        // Apply version-specific transformations
        $request = $this->applyVersionTransformations($request, $version);

        // Call controller method
        if (method_exists($controller, $action)) {
            return $controller->{$action}($request);
        } elseif (method_exists($controller, 'handle')) {
            return $controller->handle($request, $action);
        } else {
            throw new Exception("Action {$action} not found in controller");
        }
    }

    /**
     * Extract action from request path
     */
    private function extractActionFromPath($path)
    {
        // Remove API prefix and version
        $path = preg_replace('#/api(/v\d+)?/?#', '', $path);

        if (empty($path)) {
            return 'index';
        }

        // Split path and get first segment as action
        $segments = explode('/', trim($path, '/'));
        $action = $segments[0];

        // Convert common REST actions
        $actionMap = [
            'create' => 'store',
            'edit' => 'update',
            'new' => 'create'
        ];

        return $actionMap[$action] ?? $action;
    }

    /**
     * Apply version-specific transformations to request
     */
    private function applyVersionTransformations(Request $request, $version)
    {
        switch ($version) {
            case 'v2':
                // V2 specific transformations
                $request = $this->applyV2Transformations($request);
                break;
            case 'v1':
            default:
                // V1 transformations (legacy support)
                $request = $this->applyV1Transformations($request);
                break;
        }

        return $request;
    }

    /**
     * Apply V1 specific transformations
     */
    private function applyV1Transformations(Request $request)
    {
        // Legacy field name mappings
        $legacyMappings = [
            'user_id' => 'id',
            'created_at' => 'date_created',
            'updated_at' => 'date_modified'
        ];

        $data = $request->getBody();
        if (is_array($data)) {
            $data = $this->applyFieldMappings($data, $legacyMappings);
            $request->setBody($data);
        }

        return $request;
    }

    /**
     * Apply V2 specific transformations
     */
    private function applyV2Transformations(Request $request)
    {
        // V2 enhancements
        $data = $request->getBody();
        if (is_array($data)) {
            // Add version metadata
            $data['_metadata'] = [
                'api_version' => 'v2',
                'request_time' => date('c'),
                'client_ip' => $request->getClientIp()
            ];
            $request->setBody($data);
        }

        return $request;
    }

    /**
     * Apply field mappings for backward compatibility
     */
    private function applyFieldMappings($data, $mappings)
    {
        foreach ($mappings as $newField => $oldField) {
            if (isset($data[$oldField]) && !isset($data[$newField])) {
                $data[$newField] = $data[$oldField];
            }
        }
        return $data;
    }

    /**
     * Get controller class from file path
     */
    private function getControllerClassFromPath($path)
    {
        $filename = basename($path, '.php');
        return $filename;
    }

    /**
     * Create error response
     */
    private function createErrorResponse($message, $statusCode = 500)
    {
        $response = new Response();
        $response->setStatusCode($statusCode);
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody(json_encode([
            'error' => true,
            'message' => $message,
            'timestamp' => date('c')
        ]));

        return $response;
    }

    /**
     * Get version information
     */
    public function getVersionInfo()
    {
        return [
            'supported_versions' => $this->supportedVersions,
            'default_version' => $this->defaultVersion,
            'compatibility_matrix' => $this->compatibilityMatrix,
            'version_mappings' => $this->versionMappings,
            'current_time' => date('c')
        ];
    }

    /**
     * Add new API version
     */
    public function addVersion($version, $mappings = [], $compatibleVersions = [])
    {
        if ($this->isValidVersion($version)) {
            throw new Exception("Version {$version} already exists");
        }

        $this->supportedVersions[] = $version;
        $this->versionMappings[$version] = $mappings;
        $this->compatibilityMatrix[$version] = $compatibleVersions;

        $this->logger->info('New API version added', [
            'version' => $version,
            'mappings_count' => count($mappings)
        ]);
    }

    /**
     * Deprecate API version
     */
    public function deprecateVersion($version, $sunsetDate = null)
    {
        if (!$this->isValidVersion($version)) {
            throw new Exception("Version {$version} does not exist");
        }

        $sunsetDate = $sunsetDate ?? date('c', strtotime('+6 months'));

        $this->logger->warning('API version deprecated', [
            'version' => $version,
            'sunset_date' => $sunsetDate
        ]);

        // In a real implementation, you might store deprecation info
        // and add deprecation headers to responses
    }

    /**
     * Get API version statistics
     */
    public function getVersionStatistics()
    {
        // In a real implementation, this would track usage statistics
        return [
            'total_requests' => 0,
            'version_usage' => [
                'v1' => 0,
                'v2' => 0
            ],
            'error_rate' => 0,
            'avg_response_time' => 0
        ];
    }
}

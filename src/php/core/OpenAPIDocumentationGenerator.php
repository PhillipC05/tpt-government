<?php
/**
 * TPT Government Platform - OpenAPI Documentation Generator
 *
 * Automatically generates OpenAPI/Swagger documentation from controllers and routes.
 * Supports multiple output formats and interactive documentation.
 */

namespace Core;

use ReflectionClass;
use ReflectionMethod;
use Core\Interfaces\CacheInterface;

class OpenAPIDocumentationGenerator
{
    /**
     * OpenAPI specification version
     */
    private const OPENAPI_VERSION = '3.0.3';

    /**
     * Cache instance
     */
    private ?CacheInterface $cache = null;

    /**
     * Cache TTL
     */
    private int $cacheTtl = 3600;

    /**
     * API documentation cache key
     */
    private string $cacheKey = 'openapi:documentation';

    /**
     * Base API information
     */
    private array $apiInfo = [
        'title' => 'TPT Government Platform API',
        'version' => '1.0.0',
        'description' => 'Comprehensive API for government service platform',
        'contact' => [
            'name' => 'TPT Development Team',
            'email' => 'dev@tpt.gov'
        ],
        'license' => [
            'name' => 'MIT',
            'url' => 'https://opensource.org/licenses/MIT'
        ]
    ];

    /**
     * API servers configuration
     */
    private array $servers = [
        [
            'url' => 'https://api.tpt.gov/v1',
            'description' => 'Production server'
        ],
        [
            'url' => 'https://staging-api.tpt.gov/v1',
            'description' => 'Staging server'
        ],
        [
            'url' => 'http://localhost:8000/v1',
            'description' => 'Development server'
        ]
    ];

    /**
     * Security schemes
     */
    private array $securitySchemes = [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT'
        ],
        'apiKeyAuth' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key'
        ]
    ];

    /**
     * Global security requirements
     */
    private array $security = [
        ['bearerAuth' => []]
    ];

    /**
     * Constructor
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Generate OpenAPI documentation
     *
     * @param array $controllers Array of controller classes to document
     * @param array $routes Array of routes to document
     * @return array OpenAPI specification
     */
    public function generateDocumentation(array $controllers = [], array $routes = []): array
    {
        // Check cache first
        if ($this->cache) {
            $cached = $this->cache->get($this->cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $documentation = [
            'openapi' => self::OPENAPI_VERSION,
            'info' => $this->apiInfo,
            'servers' => $this->servers,
            'security' => $this->security,
            'components' => [
                'securitySchemes' => $this->securitySchemes,
                'schemas' => $this->generateSchemas(),
                'responses' => $this->generateCommonResponses()
            ],
            'paths' => $this->generatePaths($controllers, $routes),
            'tags' => $this->generateTags($controllers)
        ];

        // Cache the documentation
        if ($this->cache) {
            $this->cache->set($this->cacheKey, $documentation, $this->cacheTtl);
        }

        return $documentation;
    }

    /**
     * Generate API paths from controllers and routes
     *
     * @param array $controllers
     * @param array $routes
     * @return array
     */
    private function generatePaths(array $controllers, array $routes): array
    {
        $paths = [];

        // Generate paths from routes if provided
        if (!empty($routes)) {
            $paths = array_merge($paths, $this->generatePathsFromRoutes($routes));
        }

        // Generate paths from controllers
        foreach ($controllers as $controllerClass) {
            $controllerPaths = $this->generatePathsFromController($controllerClass);
            $paths = array_merge($paths, $controllerPaths);
        }

        return $paths;
    }

    /**
     * Generate paths from routes
     *
     * @param array $routes
     * @return array
     */
    private function generatePathsFromRoutes(array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $path = $this->convertRouteToOpenAPIPath($route['path']);
            $method = strtolower($route['method']);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path][$method] = $this->generateOperation($route);
        }

        return $paths;
    }

    /**
     * Generate paths from controller
     *
     * @param string $controllerClass
     * @return array
     */
    private function generatePathsFromController(string $controllerClass): array
    {
        $paths = [];

        try {
            $reflection = new ReflectionClass($controllerClass);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $docComment = $method->getDocComment();
                if (!$docComment) {
                    continue;
                }

                $apiInfo = $this->parseApiDocComment($docComment);
                if (!$apiInfo) {
                    continue;
                }

                $path = $apiInfo['path'];
                $httpMethod = $apiInfo['method'];

                if (!isset($paths[$path])) {
                    $paths[$path] = [];
                }

                $paths[$path][$httpMethod] = $this->generateOperationFromMethod($method, $apiInfo, $controllerClass);
            }
        } catch (\Exception $e) {
            // Log error but continue
            error_log("Error generating documentation for controller {$controllerClass}: " . $e->getMessage());
        }

        return $paths;
    }

    /**
     * Parse API documentation from doc comment
     *
     * @param string $docComment
     * @return array|null
     */
    private function parseApiDocComment(string $docComment): ?array
    {
        $lines = explode("\n", $docComment);
        $apiInfo = null;

        foreach ($lines as $line) {
            $line = trim($line, " \t/*");

            if (strpos($line, '@api') === 0) {
                $parts = preg_split('/\s+/', $line, 3);
                if (count($parts) >= 3) {
                    $apiInfo = [
                        'method' => strtoupper($parts[1]),
                        'path' => $parts[2],
                        'summary' => '',
                        'description' => '',
                        'parameters' => [],
                        'responses' => []
                    ];
                }
            } elseif (isset($apiInfo)) {
                if (strpos($line, '@apiSummary') === 0) {
                    $apiInfo['summary'] = trim(substr($line, 12));
                } elseif (strpos($line, '@apiDescription') === 0) {
                    $apiInfo['description'] = trim(substr($line, 16));
                } elseif (strpos($line, '@apiParam') === 0) {
                    $param = $this->parseApiParam($line);
                    if ($param) {
                        $apiInfo['parameters'][] = $param;
                    }
                } elseif (strpos($line, '@apiResponse') === 0) {
                    $response = $this->parseApiResponse($line);
                    if ($response) {
                        $apiInfo['responses'][$response['code']] = $response;
                    }
                }
            }
        }

        return $apiInfo;
    }

    /**
     * Parse API parameter from doc comment
     *
     * @param string $line
     * @return array|null
     */
    private function parseApiParam(string $line): ?array
    {
        // @apiParam (type) name description
        $pattern = '/@apiParam\s*\(([^)]+)\)\s*(\w+)\s*(.*)/';
        if (preg_match($pattern, $line, $matches)) {
            return [
                'name' => $matches[2],
                'in' => 'query', // Default to query parameter
                'description' => trim($matches[3]),
                'schema' => [
                    'type' => $this->mapTypeToOpenAPI($matches[1])
                ]
            ];
        }

        return null;
    }

    /**
     * Parse API response from doc comment
     *
     * @param string $line
     * @return array|null
     */
    private function parseApiResponse(string $line): ?array
    {
        // @apiResponse (code) description
        $pattern = '/@apiResponse\s*\((\d+)\)\s*(.*)/';
        if (preg_match($pattern, $line, $matches)) {
            return [
                'code' => (int) $matches[1],
                'description' => trim($matches[2])
            ];
        }

        return null;
    }

    /**
     * Map PHP type to OpenAPI type
     *
     * @param string $phpType
     * @return string
     */
    private function mapTypeToOpenAPI(string $phpType): string
    {
        $typeMap = [
            'int' => 'integer',
            'integer' => 'integer',
            'float' => 'number',
            'double' => 'number',
            'string' => 'string',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object'
        ];

        return $typeMap[strtolower($phpType)] ?? 'string';
    }

    /**
     * Generate operation from route
     *
     * @param array $route
     * @return array
     */
    private function generateOperation(array $route): array
    {
        return [
            'summary' => $this->generateRouteSummary($route),
            'description' => $this->generateRouteDescription($route),
            'operationId' => $this->generateOperationId($route),
            'tags' => $this->generateRouteTags($route),
            'parameters' => $this->generateRouteParameters($route),
            'responses' => $this->generateRouteResponses($route)
        ];
    }

    /**
     * Generate operation from method
     *
     * @param ReflectionMethod $method
     * @param array $apiInfo
     * @param string $controllerClass
     * @return array
     */
    private function generateOperationFromMethod(ReflectionMethod $method, array $apiInfo, string $controllerClass): array
    {
        return [
            'summary' => $apiInfo['summary'] ?: $method->getName(),
            'description' => $apiInfo['description'] ?: '',
            'operationId' => $this->generateOperationIdFromMethod($method, $controllerClass),
            'tags' => $this->generateMethodTags($controllerClass),
            'parameters' => $apiInfo['parameters'],
            'responses' => $this->generateMethodResponses($apiInfo['responses'])
        ];
    }

    /**
     * Generate route summary
     *
     * @param array $route
     * @return string
     */
    private function generateRouteSummary(array $route): string
    {
        $method = $route['method'];
        $path = $route['path'];

        return "{$method} {$path}";
    }

    /**
     * Generate route description
     *
     * @param array $route
     * @return string
     */
    private function generateRouteDescription(array $route): string
    {
        return "Route handler for {$route['path']}";
    }

    /**
     * Generate operation ID
     *
     * @param array $route
     * @return string
     */
    private function generateOperationId(array $route): string
    {
        $method = strtolower($route['method']);
        $path = str_replace(['/', '{', '}'], ['_', '', ''], $route['path']);

        return "{$method}{$path}";
    }

    /**
     * Generate operation ID from method
     *
     * @param ReflectionMethod $method
     * @param string $controllerClass
     * @return string
     */
    private function generateOperationIdFromMethod(ReflectionMethod $method, string $controllerClass): string
    {
        $controllerName = basename(str_replace('\\', '/', $controllerClass));
        $controllerName = str_replace('Controller', '', $controllerName);

        return lcfirst($controllerName) . ucfirst($method->getName());
    }

    /**
     * Generate route tags
     *
     * @param array $route
     * @return array
     */
    private function generateRouteTags(array $route): array
    {
        $path = $route['path'];
        $segments = explode('/', trim($path, '/'));

        if (count($segments) > 0 && !empty($segments[0])) {
            return [ucfirst($segments[0])];
        }

        return ['API'];
    }

    /**
     * Generate method tags
     *
     * @param string $controllerClass
     * @return array
     */
    private function generateMethodTags(string $controllerClass): array
    {
        $controllerName = basename(str_replace('\\', '/', $controllerClass));
        $controllerName = str_replace('Controller', '', $controllerName);

        return [ucfirst($controllerName)];
    }

    /**
     * Generate route parameters
     *
     * @param array $route
     * @return array
     */
    private function generateRouteParameters(array $route): array
    {
        $parameters = [];

        // Extract path parameters
        if (isset($route['parameters']) && is_array($route['parameters'])) {
            foreach ($route['parameters'] as $param) {
                $parameters[] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [
                        'type' => 'string'
                    ],
                    'description' => "Path parameter: {$param}"
                ];
            }
        }

        return $parameters;
    }

    /**
     * Generate route responses
     *
     * @param array $route
     * @return array
     */
    private function generateRouteResponses(array $route): array
    {
        return [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object'
                        ]
                    ]
                ]
            ],
            '400' => [
                'description' => 'Bad request',
                '$ref' => '#/components/responses/BadRequest'
            ],
            '401' => [
                'description' => 'Unauthorized',
                '$ref' => '#/components/responses/Unauthorized'
            ],
            '500' => [
                'description' => 'Internal server error',
                '$ref' => '#/components/responses/InternalServerError'
            ]
        ];
    }

    /**
     * Generate method responses
     *
     * @param array $responses
     * @return array
     */
    private function generateMethodResponses(array $responses): array
    {
        $defaultResponses = $this->generateRouteResponses([]);

        // Merge custom responses with defaults
        foreach ($responses as $code => $response) {
            $defaultResponses[(string) $code] = [
                'description' => $response['description'],
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object'
                        ]
                    ]
                ]
            ];
        }

        return $defaultResponses;
    }

    /**
     * Generate common schemas
     *
     * @return array
     */
    private function generateSchemas(): array
    {
        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string',
                        'description' => 'Error message'
                    ],
                    'code' => [
                        'type' => 'integer',
                        'description' => 'Error code'
                    ]
                ]
            ],
            'Success' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => true
                    ],
                    'data' => [
                        'type' => 'object',
                        'description' => 'Response data'
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate common responses
     *
     * @return array
     */
    private function generateCommonResponses(): array
    {
        return [
            'BadRequest' => [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Error'
                        ]
                    ]
                ]
            ],
            'Unauthorized' => [
                'description' => 'Unauthorized access',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Error'
                        ]
                    ]
                ]
            ],
            'InternalServerError' => [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Error'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate tags from controllers
     *
     * @param array $controllers
     * @return array
     */
    private function generateTags(array $controllers): array
    {
        $tags = [];

        foreach ($controllers as $controllerClass) {
            $controllerName = basename(str_replace('\\', '/', $controllerClass));
            $controllerName = str_replace('Controller', '', $controllerName);

            $tags[] = [
                'name' => ucfirst($controllerName),
                'description' => "Operations related to {$controllerName}"
            ];
        }

        return $tags;
    }

    /**
     * Convert route path to OpenAPI path
     *
     * @param string $routePath
     * @return string
     */
    private function convertRouteToOpenAPIPath(string $routePath): string
    {
        // Convert {param} to {param} (already OpenAPI format)
        return $routePath;
    }

    /**
     * Export documentation to JSON
     *
     * @param array $controllers
     * @param array $routes
     * @return string
     */
    public function exportToJson(array $controllers = [], array $routes = []): string
    {
        $documentation = $this->generateDocumentation($controllers, $routes);
        return json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export documentation to YAML
     *
     * @param array $controllers
     * @param array $routes
     * @return string
     */
    public function exportToYaml(array $controllers = [], array $routes = []): string
    {
        $documentation = $this->generateDocumentation($controllers, $routes);

        // Simple YAML conversion (in production, use a proper YAML library)
        return $this->arrayToYaml($documentation);
    }

    /**
     * Convert array to YAML (simple implementation)
     *
     * @param array $data
     * @param int $indent
     * @return string
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $indentStr . $key . ':' . PHP_EOL;
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $indentStr . $key . ': ' . (is_string($value) ? '"' . $value . '"' : $value) . PHP_EOL;
            }
        }

        return $yaml;
    }

    /**
     * Generate HTML documentation
     *
     * @param array $controllers
     * @param array $routes
     * @return string
     */
    public function generateHtmlDocumentation(array $controllers = [], array $routes = []): string
    {
        $documentation = $this->generateDocumentation($controllers, $routes);

        $html = '<!DOCTYPE html>
<html>
<head>
    <title>' . $documentation['info']['title'] . ' - API Documentation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .endpoint { margin: 20px 0; padding: 10px; border: 1px solid #ddd; }
        .method { font-weight: bold; color: #007bff; }
        .path { font-family: monospace; }
        .description { color: #666; }
    </style>
</head>
<body>
    <h1>' . $documentation['info']['title'] . '</h1>
    <p>' . $documentation['info']['description'] . '</p>
    <h2>Endpoints</h2>';

        foreach ($documentation['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $html .= '<div class="endpoint">';
                $html .= '<div class="method">' . strtoupper($method) . '</div>';
                $html .= '<div class="path">' . $path . '</div>';
                $html .= '<div class="description">' . ($operation['summary'] ?? '') . '</div>';
                $html .= '</div>';
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Clear documentation cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->delete($this->cacheKey);
        }
    }

    /**
     * Set cache TTL
     *
     * @param int $ttl
     * @return self
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Set API info
     *
     * @param array $info
     * @return self
     */
    public function setApiInfo(array $info): self
    {
        $this->apiInfo = array_merge($this->apiInfo, $info);
        return $this;
    }

    /**
     * Set servers
     *
     * @param array $servers
     * @return self
     */
    public function setServers(array $servers): self
    {
        $this->servers = $servers;
        return $this;
    }

    /**
     * Add security scheme
     *
     * @param string $name
     * @param array $scheme
     * @return self
     */
    public function addSecurityScheme(string $name, array $scheme): self
    {
        $this->securitySchemes[$name] = $scheme;
        return $this;
    }
}

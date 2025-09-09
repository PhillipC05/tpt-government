<?php
/**
 * TPT Government Platform - Documentation Generator
 *
 * Comprehensive documentation generation system with API docs,
 * code documentation, deployment guides, and CI/CD documentation
 */

class DocumentationGenerator
{
    private $logger;
    private $config;
    private $apiDocs = [];
    private $codeDocs = [];
    private $deploymentDocs = [];
    private $ciCdDocs = [];

    /**
     * Documentation types
     */
    const DOC_API = 'api';
    const DOC_CODE = 'code';
    const DOC_DEPLOYMENT = 'deployment';
    const DOC_CICD = 'cicd';
    const DOC_USER = 'user';
    const DOC_ADMIN = 'admin';

    /**
     * Output formats
     */
    const FORMAT_HTML = 'html';
    const FORMAT_MARKDOWN = 'markdown';
    const FORMAT_JSON = 'json';
    const FORMAT_PDF = 'pdf';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'source_paths' => ['src/'],
            'output_path' => 'docs/generated/',
            'templates_path' => 'docs/templates/',
            'api_base_url' => 'https://api.tpt-gov.local',
            'include_private' => false,
            'generate_diagrams' => true,
            'auto_update' => true,
            'exclude_patterns' => ['/vendor/', '/node_modules/', '/tests/']
        ], $config);

        // Create output directory if it doesn't exist
        if (!is_dir($this->config['output_path'])) {
            mkdir($this->config['output_path'], 0755, true);
        }
    }

    /**
     * Generate all documentation
     */
    public function generateAllDocumentation($types = null)
    {
        $types = $types ?: [self::DOC_API, self::DOC_CODE, self::DOC_DEPLOYMENT, self::DOC_CICD];

        $this->logger->info('Starting comprehensive documentation generation', [
            'types' => $types,
            'timestamp' => date('c')
        ]);

        $startTime = microtime(true);
        $results = [];

        foreach ($types as $type) {
            $results[$type] = $this->generateDocumentation($type);
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        // Generate index file
        $this->generateIndexFile($results);

        $this->logger->info('Documentation generation completed', [
            'total_time' => $totalTime . 'ms',
            'types_generated' => count($types)
        ]);

        return [
            'timestamp' => date('c'),
            'execution_time' => $totalTime,
            'results' => $results,
            'index_file' => $this->config['output_path'] . 'index.html'
        ];
    }

    /**
     * Generate documentation for specific type
     */
    public function generateDocumentation($type)
    {
        $this->logger->info("Generating {$type} documentation");

        switch ($type) {
            case self::DOC_API:
                return $this->generateApiDocumentation();
            case self::DOC_CODE:
                return $this->generateCodeDocumentation();
            case self::DOC_DEPLOYMENT:
                return $this->generateDeploymentDocumentation();
            case self::DOC_CICD:
                return $this->generateCiCdDocumentation();
            default:
                throw new Exception("Unknown documentation type: {$type}");
        }
    }

    /**
     * Generate API documentation
     */
    private function generateApiDocumentation()
    {
        $this->logger->info('Analyzing API endpoints for documentation');

        $controllers = $this->discoverControllers();
        $apiDocs = [];

        foreach ($controllers as $controller) {
            $endpoints = $this->analyzeController($controller);
            $apiDocs[$controller] = $endpoints;
        }

        $this->apiDocs = $apiDocs;

        // Generate API documentation files
        $this->generateApiDocFiles($apiDocs);

        return [
            'type' => self::DOC_API,
            'controllers_analyzed' => count($controllers),
            'endpoints_documented' => array_sum(array_map('count', $apiDocs)),
            'files_generated' => [
                'api-reference.html',
                'api-endpoints.json',
                'postman-collection.json'
            ]
        ];
    }

    /**
     * Generate code documentation
     */
    private function generateCodeDocumentation()
    {
        $this->logger->info('Analyzing source code for documentation');

        $files = $this->discoverSourceFiles();
        $codeDocs = [];

        foreach ($files as $file) {
            $documentation = $this->analyzeSourceFile($file);
            if (!empty($documentation)) {
                $codeDocs[$file] = $documentation;
            }
        }

        $this->codeDocs = $codeDocs;

        // Generate code documentation files
        $this->generateCodeDocFiles($codeDocs);

        return [
            'type' => self::DOC_CODE,
            'files_analyzed' => count($files),
            'files_documented' => count($codeDocs),
            'classes_documented' => $this->countDocumentedClasses($codeDocs),
            'functions_documented' => $this->countDocumentedFunctions($codeDocs),
            'files_generated' => [
                'code-reference.html',
                'class-diagram.html',
                'code-metrics.json'
            ]
        ];
    }

    /**
     * Generate deployment documentation
     */
    private function generateDeploymentDocumentation()
    {
        $this->logger->info('Generating deployment documentation');

        $deploymentDocs = [
            'local' => $this->generateLocalDeploymentDocs(),
            'staging' => $this->generateStagingDeploymentDocs(),
            'production' => $this->generateProductionDeploymentDocs(),
            'cloud' => $this->generateCloudDeploymentDocs()
        ];

        $this->deploymentDocs = $deploymentDocs;

        // Generate deployment documentation files
        $this->generateDeploymentDocFiles($deploymentDocs);

        return [
            'type' => self::DOC_DEPLOYMENT,
            'environments_covered' => count($deploymentDocs),
            'files_generated' => [
                'deployment-guide.html',
                'environment-setup.md',
                'docker-deployment.md',
                'kubernetes-deployment.md'
            ]
        ];
    }

    /**
     * Generate CI/CD documentation
     */
    private function generateCiCdDocumentation()
    {
        $this->logger->info('Generating CI/CD documentation');

        $ciCdDocs = [
            'pipelines' => $this->analyzeCiCdPipelines(),
            'workflows' => $this->analyzeGitHubWorkflows(),
            'testing' => $this->generateTestingDocs(),
            'deployment' => $this->generateAutomatedDeploymentDocs()
        ];

        $this->ciCdDocs = $ciCdDocs;

        // Generate CI/CD documentation files
        $this->generateCiCdDocFiles($ciCdDocs);

        return [
            'type' => self::DOC_CICD,
            'pipelines_analyzed' => count($ciCdDocs['pipelines']),
            'workflows_analyzed' => count($ciCdDocs['workflows']),
            'files_generated' => [
                'ci-cd-guide.html',
                'pipeline-config.md',
                'testing-strategy.md',
                'deployment-automation.md'
            ]
        ];
    }

    /**
     * Discover controller files
     */
    private function discoverControllers()
    {
        $controllers = [];

        foreach ($this->config['source_paths'] as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() &&
                    in_array($file->getExtension(), ['php', 'inc']) &&
                    $this->isControllerFile($file->getPathname())) {
                    $controllers[] = $file->getPathname();
                }
            }
        }

        return $controllers;
    }

    /**
     * Check if file is a controller
     */
    private function isControllerFile($filePath)
    {
        $content = file_get_contents($filePath);
        return preg_match('/class\s+\w+Controller\s+extends/i', $content) ||
               strpos($filePath, 'Controller.php') !== false;
    }

    /**
     * Analyze controller for API endpoints
     */
    private function analyzeController($controllerPath)
    {
        $content = file_get_contents($controllerPath);
        $endpoints = [];

        // Extract class name
        if (preg_match('/class\s+(\w+)/i', $content, $matches)) {
            $className = $matches[1];
        } else {
            $className = basename($controllerPath, '.php');
        }

        // Extract methods
        if (preg_match_all('/public\s+function\s+(\w+)\s*\(/i', $content, $methodMatches)) {
            foreach ($methodMatches[1] as $methodName) {
                $endpoint = $this->analyzeMethod($content, $methodName, $className);
                if ($endpoint) {
                    $endpoints[] = $endpoint;
                }
            }
        }

        return $endpoints;
    }

    /**
     * Analyze method for API documentation
     */
    private function analyzeMethod($content, $methodName, $className)
    {
        // Extract method documentation
        $pattern = '/\/\*\*\s*\n\s*\*\s*@.*?\*\//s';
        $methodPattern = '/function\s+' . preg_quote($methodName) . '\s*\(/i';

        if (preg_match($methodPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodPos = $matches[0][1];

            // Look for documentation comment before the method
            $docPattern = '/\/\*\*\s*\n(?:\s*\*\s*.*?\n)*?\s*\*\//s';
            $beforeMethod = substr($content, 0, $methodPos);

            if (preg_match_all($docPattern, $beforeMethod, $docMatches)) {
                $lastDoc = end($docMatches[0]);
                $documentation = $this->parseDocComment($lastDoc);

                return [
                    'method' => $methodName,
                    'class' => $className,
                    'http_method' => $this->inferHttpMethod($methodName),
                    'route' => $this->inferRoute($className, $methodName),
                    'documentation' => $documentation,
                    'parameters' => $this->extractMethodParameters($content, $methodName),
                    'return_type' => $documentation['return'] ?? 'mixed'
                ];
            }
        }

        return null;
    }

    /**
     * Parse PHPDoc comment
     */
    private function parseDocComment($docComment)
    {
        $lines = explode("\n", $docComment);
        $documentation = [
            'description' => '',
            'params' => [],
            'return' => 'void',
            'throws' => [],
            'deprecated' => false
        ];

        $inDescription = true;

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\*\s?/', '', $line);

            if (empty($line) || $line === '/**' || $line === '*/') {
                continue;
            }

            if (strpos($line, '@param') === 0) {
                $inDescription = false;
                $param = $this->parseParamTag($line);
                if ($param) {
                    $documentation['params'][] = $param;
                }
            } elseif (strpos($line, '@return') === 0) {
                $inDescription = false;
                $documentation['return'] = $this->parseReturnTag($line);
            } elseif (strpos($line, '@throws') === 0) {
                $inDescription = false;
                $documentation['throws'][] = $this->parseThrowsTag($line);
            } elseif (strpos($line, '@deprecated') === 0) {
                $inDescription = false;
                $documentation['deprecated'] = true;
            } elseif ($inDescription) {
                $documentation['description'] .= $line . ' ';
            }
        }

        $documentation['description'] = trim($documentation['description']);

        return $documentation;
    }

    /**
     * Parse @param tag
     */
    private function parseParamTag($line)
    {
        if (preg_match('/@param\s+(\w+)\s+\$(\w+)\s+(.+)/', $line, $matches)) {
            return [
                'type' => $matches[1],
                'name' => $matches[2],
                'description' => trim($matches[3])
            ];
        }
        return null;
    }

    /**
     * Parse @return tag
     */
    private function parseReturnTag($line)
    {
        if (preg_match('/@return\s+(\w+)/', $line, $matches)) {
            return $matches[1];
        }
        return 'void';
    }

    /**
     * Parse @throws tag
     */
    private function parseThrowsTag($line)
    {
        if (preg_match('/@throws\s+(\w+)/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Infer HTTP method from method name
     */
    private function inferHttpMethod($methodName)
    {
        $methodName = strtolower($methodName);

        if (strpos($methodName, 'get') === 0 || $methodName === 'index' || $methodName === 'show') {
            return 'GET';
        } elseif (strpos($methodName, 'post') === 0 || $methodName === 'create' || $methodName === 'store') {
            return 'POST';
        } elseif (strpos($methodName, 'put') === 0 || $methodName === 'update') {
            return 'PUT';
        } elseif (strpos($methodName, 'delete') === 0 || $methodName === 'destroy') {
            return 'DELETE';
        } elseif (strpos($methodName, 'patch') === 0) {
            return 'PATCH';
        }

        return 'GET'; // Default
    }

    /**
     * Infer route from class and method name
     */
    private function inferRoute($className, $methodName)
    {
        $resource = strtolower(str_replace('Controller', '', $className));
        $action = strtolower($methodName);

        // Convert common method names to RESTful routes
        $routeMap = [
            'index' => "/{$resource}",
            'show' => "/{$resource}/{id}",
            'create' => "/{$resource}",
            'store' => "/{$resource}",
            'edit' => "/{$resource}/{id}/edit",
            'update' => "/{$resource}/{id}",
            'destroy' => "/{$resource}/{id}",
            'delete' => "/{$resource}/{id}"
        ];

        return $routeMap[$action] ?? "/{$resource}/{$action}";
    }

    /**
     * Extract method parameters
     */
    private function extractMethodParameters($content, $methodName)
    {
        $pattern = '/function\s+' . preg_quote($methodName) . '\s*\((.*?)\)/i';

        if (preg_match($pattern, $content, $matches)) {
            $paramsString = $matches[1];
            $parameters = [];

            if (!empty($paramsString)) {
                $params = explode(',', $paramsString);
                foreach ($params as $param) {
                    $param = trim($param);
                    if (preg_match('/(\w+)\s+\$(\w+)/', $param, $paramMatches)) {
                        $parameters[] = [
                            'type' => $paramMatches[1],
                            'name' => $paramMatches[2]
                        ];
                    }
                }
            }

            return $parameters;
        }

        return [];
    }

    /**
     * Discover source files for documentation
     */
    private function discoverSourceFiles()
    {
        $files = [];

        foreach ($this->config['source_paths'] as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() &&
                    in_array($file->getExtension(), ['php', 'inc']) &&
                    $this->shouldIncludeFile($file->getPathname())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Check if file should be included in documentation
     */
    private function shouldIncludeFile($filePath)
    {
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Analyze source file for documentation
     */
    private function analyzeSourceFile($filePath)
    {
        $content = file_get_contents($filePath);
        $documentation = [
            'classes' => [],
            'functions' => [],
            'constants' => [],
            'file_info' => [
                'path' => $filePath,
                'size' => strlen($content),
                'lines' => substr_count($content, "\n") + 1
            ]
        ];

        // Extract classes
        if (preg_match_all('/class\s+(\w+)/i', $content, $classMatches)) {
            foreach ($classMatches[1] as $className) {
                $classDoc = $this->analyzeClass($content, $className);
                if ($classDoc) {
                    $documentation['classes'][$className] = $classDoc;
                }
            }
        }

        // Extract functions
        if (preg_match_all('/function\s+(\w+)\s*\(/i', $content, $functionMatches)) {
            foreach ($functionMatches[1] as $functionName) {
                $functionDoc = $this->analyzeFunction($content, $functionName);
                if ($functionDoc) {
                    $documentation['functions'][$functionName] = $functionDoc;
                }
            }
        }

        // Extract constants
        if (preg_match_all('/const\s+(\w+)/i', $content, $constantMatches)) {
            foreach ($constantMatches[1] as $constantName) {
                $documentation['constants'][] = $constantName;
            }
        }

        return $documentation;
    }

    /**
     * Analyze class for documentation
     */
    private function analyzeClass($content, $className)
    {
        $pattern = '/(\/\*\*\s*\n(?:\s*\*\s*.*?\n)*?\s*\*\/\s*\n)?class\s+' . preg_quote($className) . '/s';

        if (preg_match($pattern, $content, $matches)) {
            $documentation = [
                'name' => $className,
                'description' => '',
                'properties' => [],
                'methods' => []
            ];

            if (!empty($matches[1])) {
                $docComment = $this->parseDocComment($matches[1]);
                $documentation['description'] = $docComment['description'];
            }

            // Extract properties
            $propertiesPattern = '/(?:\/\*\*\s*\n(?:\s*\*\s*.*?\n)*?\s*\*\/\s*\n)?\s*(?:public|protected|private)\s+\$(\w+)/s';
            if (preg_match_all($propertiesPattern, $content, $propertyMatches)) {
                foreach ($propertyMatches[1] as $propertyName) {
                    $documentation['properties'][] = $propertyName;
                }
            }

            // Extract methods
            $methodsPattern = '/(?:\/\*\*\s*\n(?:\s*\*\s*.*?\n)*?\s*\*\/\s*\n)?\s*(?:public|protected|private)\s+function\s+(\w+)/s';
            if (preg_match_all($methodsPattern, $content, $methodMatches)) {
                foreach ($methodMatches[1] as $methodName) {
                    $methodDoc = $this->analyzeMethod($content, $methodName, $className);
                    if ($methodDoc) {
                        $documentation['methods'][$methodName] = $methodDoc;
                    }
                }
            }

            return $documentation;
        }

        return null;
    }

    /**
     * Analyze function for documentation
     */
    private function analyzeFunction($content, $functionName)
    {
        $pattern = '/(\/\*\*\s*\n(?:\s*\*\s*.*?\n)*?\s*\*\/\s*\n)?function\s+' . preg_quote($functionName) . '/s';

        if (preg_match($pattern, $content, $matches)) {
            $documentation = [
                'name' => $functionName,
                'description' => '',
                'parameters' => [],
                'return_type' => 'void'
            ];

            if (!empty($matches[1])) {
                $docComment = $this->parseDocComment($matches[1]);
                $documentation['description'] = $docComment['description'];
                $documentation['parameters'] = $docComment['params'];
                $documentation['return_type'] = $docComment['return'];
            }

            return $documentation;
        }

        return null;
    }

    /**
     * Count documented classes
     */
    private function countDocumentedClasses($codeDocs)
    {
        $count = 0;
        foreach ($codeDocs as $fileDoc) {
            $count += count($fileDoc['classes']);
        }
        return $count;
    }

    /**
     * Count documented functions
     */
    private function countDocumentedFunctions($codeDocs)
    {
        $count = 0;
        foreach ($codeDocs as $fileDoc) {
            $count += count($fileDoc['functions']);
        }
        return $count;
    }

    /**
     * Generate API documentation files
     */
    private function generateApiDocFiles($apiDocs)
    {
        // Generate HTML API reference
        $htmlContent = $this->generateApiHtml($apiDocs);
        file_put_contents($this->config['output_path'] . 'api-reference.html', $htmlContent);

        // Generate JSON API endpoints
        $jsonContent = json_encode($apiDocs, JSON_PRETTY_PRINT);
        file_put_contents($this->config['output_path'] . 'api-endpoints.json', $jsonContent);

        // Generate Postman collection
        $postmanContent = $this->generatePostmanCollection($apiDocs);
        file_put_contents($this->config['output_path'] . 'postman-collection.json', $postmanContent);
    }

    /**
     * Generate code documentation files
     */
    private function generateCodeDocFiles($codeDocs)
    {
        // Generate HTML code reference
        $htmlContent = $this->generateCodeHtml($codeDocs);
        file_put_contents($this->config['output_path'] . 'code-reference.html', $htmlContent);

        // Generate class diagram (simplified)
        $diagramContent = $this->generateClassDiagram($codeDocs);
        file_put_contents($this->config['output_path'] . 'class-diagram.html', $diagramContent);

        // Generate code metrics JSON
        $metricsContent = $this->generateCodeMetrics($codeDocs);
        file_put_contents($this->config['output_path'] . 'code-metrics.json', $metricsContent);
    }

    /**
     * Generate deployment documentation files
     */
    private function generateDeploymentDocFiles($deploymentDocs)
    {
        // Generate HTML deployment guide
        $htmlContent = $this->generateDeploymentHtml($deploymentDocs);
        file_put_contents($this->config['output_path'] . 'deployment-guide.html', $htmlContent);

        // Generate environment setup markdown
        $envContent = $this->generateEnvironmentMarkdown($deploymentDocs);
        file_put_contents($this->config['output_path'] . 'environment-setup.md', $envContent);

        // Generate Docker deployment markdown
        $dockerContent = $this->generateDockerMarkdown($deploymentDocs);
        file_put_contents($this->config['output_path'] . 'docker-deployment.md', $dockerContent);

        // Generate Kubernetes deployment markdown
        $k8sContent = $this->generateKubernetesMarkdown($deploymentDocs);
        file_put_contents($this->config['output_path'] . 'kubernetes-deployment.md', $k8sContent);
    }

    /**
     * Generate CI/CD documentation files
     */
    private function generateCiCdDocFiles($ciCdDocs)
    {
        // Generate HTML CI/CD guide
        $htmlContent = $this->generateCiCdHtml($ciCdDocs);
        file_put_contents($this->config['output_path'] . 'ci-cd-guide.html', $htmlContent);

        // Generate pipeline config markdown
        $pipelineContent = $this->generatePipelineMarkdown($ciCdDocs);
        file_put_contents($this->config['output_path'] . 'pipeline-config.md', $pipelineContent);

        // Generate testing strategy markdown
        $testingContent = $this->generateTestingMarkdown($ciCdDocs);
        file_put_contents($this->config['output_path'] . 'testing-strategy.md', $testingContent);

        // Generate deployment automation markdown
        $automationContent = $this->generateAutomationMarkdown($ciCdDocs);
        file_put_contents($this->config['output_path'] . 'deployment-automation.md', $automationContent);
    }

    /**
     * Generate API HTML documentation
     */
    private function generateApiHtml($apiDocs)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>API Reference - TPT Government Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .endpoint { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .method { font-weight: bold; color: #007bff; }
        .route { font-family: monospace; background: #e9ecef; padding: 2px 5px; border-radius: 3px; }
        .description { margin: 10px 0; }
        .parameters { background: #fff3cd; padding: 10px; border-radius: 3px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>API Reference - TPT Government Platform</h1>
    <p><strong>Generated:</strong> ' . date('c') . '</p>
    <p><strong>Base URL:</strong> ' . $this->config['api_base_url'] . '</p>';

        foreach ($apiDocs as $controller => $endpoints) {
            $html .= "<h2>{$controller}</h2>";
            foreach ($endpoints as $endpoint) {
                $html .= '<div class="endpoint">
                    <h3><span class="method">' . $endpoint['http_method'] . '</span> <span class="route">' . $endpoint['route'] . '</span></h3>
                    <div class="description">' . ($endpoint['documentation']['description'] ?? 'No description available') . '</div>';

                if (!empty($endpoint['parameters'])) {
                    $html .= '<div class="parameters">
                        <h4>Parameters:</h4>
                        <ul>';
                    foreach ($endpoint['parameters'] as $param) {
                        $html .= "<li><code>{$param['name']}</code> ({$param['type']}) - {$param['description']}</li>";
                    }
                    $html .= '</ul></div>';
                }

                $html .= '<p><strong>Return Type:</strong> ' . $endpoint['return_type'] . '</p>
                </div>';
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Generate Postman collection
     */
    private function generatePostmanCollection($apiDocs)
    {
        $collection = [
            'info' => [
                'name' => 'TPT Government Platform API',
                'description' => 'API collection for TPT Government Platform',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'item' => []
        ];

        foreach ($apiDocs as $controller => $endpoints) {
            $folder = [
                'name' => $controller,
                'item' => []
            ];

            foreach ($endpoints as $endpoint) {
                $request = [
                    'name' => $endpoint['method'],
                    'request' => [
                        'method' => $endpoint['http_method'],
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json']
                        ],
                        'url' => [
                            'raw' => $this->config['api_base_url'] . $endpoint['route'],
                            'host' => [$this->config['api_base_url']],
                            'path' => explode('/', trim($endpoint['route'], '/'))
                        ]
                    ]
                ];

                $folder['item'][] = $request;
            }

            $collection['item'][] = $folder;
        }

        return json_encode($collection, JSON_PRETTY_PRINT);
    }

    /**
     * Generate code HTML documentation
     */
    private function generateCodeHtml($codeDocs)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Code Reference - TPT Government Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .class { background: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .function { background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 3px; }
        .property { background: #fff3cd; padding: 5px; margin: 2px 0; border-radius: 2px; }
    </style>
</head>
<body>
    <h1>Code Reference - TPT Government Platform</h1>
    <p><strong>Generated:</strong> ' . date('c') . '</p>';

        foreach ($codeDocs as $file => $fileDoc) {
            $html .= "<h2>File: " . basename($file) . "</h2>";

            if (!empty($fileDoc['classes'])) {
                foreach ($fileDoc['classes'] as $className => $classDoc) {
                    $html .= '<div class="class">
                        <h3>Class: ' . $className . '</h3>
                        <p>' . ($classDoc['description'] ?? 'No description available') . '</p>';

                    if (!empty($classDoc['properties'])) {
                        $html .= '<h4>Properties:</h4>';
                        foreach ($classDoc['properties'] as $property) {
                            $html .= '<div class="property">$' . $property . '</div>';
                        }
                    }

                    if (!empty($classDoc['methods'])) {
                        $html .= '<h4>Methods:</h4>';
                        foreach ($classDoc['methods'] as $methodName => $methodDoc) {
                            $html .= '<div class="function">
                                <strong>' . $methodName . '</strong>: ' . ($methodDoc['documentation']['description'] ?? 'No description')
                            . '</div>';
                        }
                    }

                    $html .= '</div>';
                }
            }

            if (!empty($fileDoc['functions'])) {
                $html .= '<h3>Functions:</h3>';
                foreach ($fileDoc['functions'] as $functionName => $functionDoc) {
                    $html .= '<div class="function">
                        <strong>' . $functionName . '</strong>: ' . ($functionDoc['description'] ?? 'No description')
                    . '</div>';
                }
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Generate class diagram (simplified HTML representation)
     */
    private function generateClassDiagram($codeDocs)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Class Diagram - TPT Government Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .diagram { display: flex; flex-wrap: wrap; gap: 20px; }
        .class-box { border: 2px solid #333; border-radius: 5px; padding: 10px; background: #f8f9fa; min-width: 200px; }
        .class-name { font-weight: bold; text-align: center; border-bottom: 1px solid #333; margin-bottom: 10px; }
        .class-property { font-style: italic; margin: 2px 0; }
        .class-method { margin: 2px 0; }
    </style>
</head>
<body>
    <h1>Class Diagram - TPT Government Platform</h1>
    <p><strong>Generated:</strong> ' . date('c') . '</p>
    <div class="diagram">';

        foreach ($codeDocs as $fileDoc) {
            if (!empty($fileDoc['classes'])) {
                foreach ($fileDoc['classes'] as $className => $classDoc) {
                    $html .= '<div class="class-box">
                        <div class="class-name">' . $className . '</div>';

                    if (!empty($classDoc['properties'])) {
                        foreach ($classDoc['properties'] as $property) {
                            $html .= '<div class="class-property">+ $' . $property . '</div>';
                        }
                    }

                    if (!empty($classDoc['methods'])) {
                        foreach ($classDoc['methods'] as $methodName => $methodDoc) {
                            $html .= '<div class="class-method">+ ' . $methodName . '()</div>';
                        }
                    }

                    $html .= '</div>';
                }
            }
        }

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Generate code metrics
     */
    private function generateCodeMetrics($codeDocs)
    {
        $metrics = [
            'timestamp' => date('c'),
            'total_files' => count($codeDocs),
            'total_classes' => 0,
            'total_functions' => 0,
            'total_constants' => 0,
            'average_complexity' => 0,
            'file_metrics' => []
        ];

        foreach ($codeDocs as $file => $fileDoc) {
            $fileMetrics = [
                'file' => basename($file),
                'classes' =>

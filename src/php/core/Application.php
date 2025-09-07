<?php
/**
 * TPT Government Platform - Main Application Class
 *
 * The main application controller that handles routing and request processing.
 * Implements a simple MVC-like architecture without external frameworks.
 */

namespace Core;

class Application
{
    /**
     * Router instance
     */
    private Router $router;

    /**
     * Request instance
     */
    private Request $request;

    /**
     * Response instance
     */
    private Response $response;

    /**
     * Database instance
     */
    private ?Database $database = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);

        // Initialize database if configured
        $this->initializeDatabase();

        // Register routes
        $this->registerRoutes();
    }

    /**
     * Initialize database connection
     *
     * @return void
     */
    private function initializeDatabase(): void
    {
        $configFile = CONFIG_PATH . '/database.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->database = new Database($config);
        }
    }

    /**
     * Register application routes
     *
     * @return void
     */
    private function registerRoutes(): void
    {
        // API routes
        $this->router->group('/api', function($router) {
            $router->get('/health', [ApiController::class, 'health']);
            $router->post('/auth/login', [AuthController::class, 'login']);
            $router->post('/auth/logout', [AuthController::class, 'logout']);
            $router->get('/user/profile', [UserController::class, 'profile'], ['auth']);
        });

        // Web routes
        $this->router->get('/', [HomeController::class, 'index']);
        $this->router->get('/login', [AuthController::class, 'showLogin']);
        $this->router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
        $this->router->get('/admin', [AdminController::class, 'index'], ['auth', 'admin']);
    }

    /**
     * Run the application
     *
     * @return void
     */
    public function run(): void
    {
        try {
            // Handle CORS for API requests
            $this->handleCors();

            // Route the request
            $route = $this->router->dispatch();

            if ($route) {
                // Execute the route
                $this->executeRoute($route);
            } else {
                // Handle 404
                $this->handle404();
            }

        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle CORS headers for API requests
     *
     * @return void
     */
    private function handleCors(): void
    {
        if (strpos($this->request->getPath(), '/api/') === 0) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');

            if ($this->request->getMethod() === 'OPTIONS') {
                exit(0);
            }
        }
    }

    /**
     * Execute a route
     *
     * @param array $route The route configuration
     * @return void
     */
    private function executeRoute(array $route): void
    {
        $controller = $route['controller'];
        $method = $route['method'];
        $middleware = $route['middleware'] ?? [];

        // Execute middleware
        foreach ($middleware as $middlewareName) {
            $this->executeMiddleware($middlewareName);
        }

        // Execute controller method
        if (is_array($controller)) {
            $controllerInstance = new $controller[0]();
            $methodName = $controller[1];
        } else {
            $controllerInstance = new $controller();
            $methodName = $method;
        }

        // Inject dependencies
        if (method_exists($controllerInstance, 'setRequest')) {
            $controllerInstance->setRequest($this->request);
        }
        if (method_exists($controllerInstance, 'setResponse')) {
            $controllerInstance->setResponse($this->response);
        }
        if (method_exists($controllerInstance, 'setDatabase') && $this->database) {
            $controllerInstance->setDatabase($this->database);
        }

        // Call the controller method
        $result = $controllerInstance->$methodName();

        // Handle the response
        if ($result !== null) {
            $this->response->json($result);
        }
    }

    /**
     * Execute middleware
     *
     * @param string $middlewareName The middleware name
     * @return void
     */
    private function executeMiddleware(string $middlewareName): void
    {
        $middlewareClass = 'Middleware\\' . ucfirst($middlewareName) . 'Middleware';

        if (class_exists($middlewareClass)) {
            $middleware = new $middlewareClass();
            $middleware->handle($this->request, $this->response);
        }
    }

    /**
     * Handle 404 errors
     *
     * @return void
     */
    private function handle404(): void
    {
        $this->response->setStatusCode(404);

        if ($this->request->isApiRequest()) {
            $this->response->json(['error' => 'Not Found'], 404);
        } else {
            $this->response->html('<h1>404 Not Found</h1><p>The requested page was not found.</p>');
        }
    }

    /**
     * Handle exceptions
     *
     * @param \Exception $e The exception
     * @return void
     */
    private function handleException(\Exception $e): void
    {
        // Log the error
        error_log('[' . date('Y-m-d H:i:s') . '] Exception: ' . $e->getMessage() .
                  ' in ' . $e->getFile() . ':' . $e->getLine());

        $this->response->setStatusCode(500);

        if ($this->request->isApiRequest()) {
            $this->response->json([
                'error' => 'Internal Server Error',
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        } else {
            $this->response->html('<h1>500 Internal Server Error</h1><p>Something went wrong. Please try again later.</p>');
        }
    }

    /**
     * Get the router instance
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the request instance
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get the response instance
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Get the database instance
     *
     * @return Database|null
     */
    public function getDatabase(): ?Database
    {
        return $this->database;
    }
}

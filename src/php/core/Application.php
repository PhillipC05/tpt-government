<?php
/**
 * TPT Government Platform - Main Application Class
 *
 * The main application controller that handles routing and request processing.
 * Implements a simple MVC-like architecture with dependency injection.
 */

namespace Core;

use Core\DependencyInjection\Container;
use Core\DependencyInjection\DatabaseServiceProvider;
use Core\Interfaces\CacheInterface;

class Application
{
    /**
     * Dependency injection container
     */
    private Container $container;

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
    public function __construct(?Container $container = null)
    {
        // Initialize dependency injection container
        $this->container = $container ?? $this->createContainer();

        // Get core services from container
        $this->request = $this->container->get(Request::class);
        $this->response = $this->container->get(Response::class);
        $this->router = $this->container->get('router.optimized');

        // Try to get database from container (may not be available in some contexts)
        try {
            $this->database = $this->container->get('database');
        } catch (\Exception $e) {
            // Database not configured, continue without it
            $this->database = null;
        }

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
            $controllerClass = $controller[0];
            $methodName = $controller[1];
        } else {
            $controllerClass = $controller;
            $methodName = $method;
        }

        // Create controller instance with dependency injection
        if ($this->container->has($controllerClass)) {
            $controllerInstance = $this->container->get($controllerClass);
        } else {
            // Fallback to manual instantiation with container
            $controllerInstance = new $controllerClass($this->container);
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

    /**
     * Get the dependency injection container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Create the dependency injection container
     *
     * @return Container
     */
    private function createContainer(): Container
    {
        $container = new Container();

        // Register core services
        $container->singleton(Request::class, function () {
            return new Request();
        });

        $container->singleton(Response::class, function () {
            return new Response();
        });

        $container->singleton(Router::class, function ($container) {
            return new Router(
                $container->get(Request::class),
                $container->get(Response::class)
            );
        });

        // Register optimized router with cache support
        $container->singleton('router.optimized', function ($container) {
            $cache = null;
            try {
                $cache = $container->get(CacheInterface::class);
            } catch (\Exception $e) {
                // Cache not available, continue without it
            }

            return new RouterOptimized(
                $container->get(Request::class),
                $container->get(Response::class),
                $cache
            );
        });

        // Register controllers
        $container->singleton(ApiController::class, function ($container) {
            return new ApiController($container);
        });

        $container->singleton(AuthController::class, function ($container) {
            return new AuthController($container);
        });

        $container->singleton(UserController::class, function ($container) {
            return new UserController($container);
        });

        $container->singleton(HomeController::class, function ($container) {
            return new HomeController($container);
        });

        $container->singleton(DashboardController::class, function ($container) {
            return new DashboardController($container);
        });

        $container->singleton(AdminController::class, function ($container) {
            return new AdminController($container);
        });

        // Register service providers
        $container->register(new DatabaseServiceProvider());

        return $container;
    }
}

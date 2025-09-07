<?php
/**
 * Performance benchmarks for core components
 *
 * @package TPT
 * @subpackage Benchmarks
 */

namespace TPT\Benchmarks;

use TPT\Core\Database;
use TPT\Core\Auth;
use TPT\Core\Router;
use TPT\Core\Request;
use TPT\Core\Response;
use TPT\Core\Session;

/**
 * CoreBench class
 *
 * Benchmarks core application components
 */
class CoreBench
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var Auth
     */
    private Auth $auth;

    /**
     * @var Router
     */
    private Router $router;

    /**
     * Set up benchmark environment
     */
    public function __construct()
    {
        // Initialize database connection
        $this->database = new Database();

        // Initialize auth service
        $this->auth = new Auth($this->database);

        // Initialize router
        $this->router = new Router();
    }

    /**
     * Benchmark database connection
     */
    public function benchDatabaseConnection()
    {
        $connection = $this->database->getConnection();
        $connection->query('SELECT 1');
    }

    /**
     * Benchmark simple database query
     */
    public function benchSimpleQuery()
    {
        $stmt = $this->database->query('SELECT 1 as test');
        $result = $stmt->fetch();
    }

    /**
     * Benchmark prepared statement
     */
    public function benchPreparedStatement()
    {
        $stmt = $this->database->prepare('SELECT ? as value');
        $stmt->execute([42]);
        $result = $stmt->fetch();
    }

    /**
     * Benchmark user authentication
     */
    public function benchUserAuthentication()
    {
        // This would test authentication logic
        $result = $this->auth->validateCredentials('test@example.com', 'password123');
    }

    /**
     * Benchmark session creation
     */
    public function benchSessionCreation()
    {
        $sessionId = Session::create();
        Session::set('test_key', 'test_value');
        $value = Session::get('test_key');
        Session::destroy($sessionId);
    }

    /**
     * Benchmark router matching
     */
    public function benchRouterMatching()
    {
        $this->router->addRoute('GET', '/api/users/{id}', 'UserController@show');
        $this->router->addRoute('POST', '/api/users', 'UserController@store');
        $this->router->addRoute('GET', '/api/users/{id}/posts/{postId}', 'PostController@show');

        $match = $this->router->match('GET', '/api/users/123');
    }

    /**
     * Benchmark complex router matching
     */
    public function benchComplexRouterMatching()
    {
        // Add many routes
        for ($i = 0; $i < 100; $i++) {
            $this->router->addRoute('GET', "/api/resource{$i}/{id}", 'Controller@action');
        }

        $match = $this->router->match('GET', '/api/resource50/123');
    }

    /**
     * Benchmark JSON response creation
     */
    public function benchJsonResponse()
    {
        $data = [
            'users' => array_fill(0, 100, [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'roles' => ['user', 'admin'],
                'metadata' => [
                    'created_at' => '2023-01-01T00:00:00Z',
                    'updated_at' => '2023-01-01T00:00:00Z'
                ]
            ]),
            'pagination' => [
                'page' => 1,
                'per_page' => 25,
                'total' => 1000,
                'total_pages' => 40
            ]
        ];

        $json = json_encode($data);
        $decoded = json_decode($json, true);
    }

    /**
     * Benchmark array operations
     */
    public function benchArrayOperations()
    {
        $array = range(1, 1000);

        // Various array operations
        shuffle($array);
        sort($array);
        array_filter($array, fn($n) => $n % 2 === 0);
        array_map(fn($n) => $n * 2, $array);
        array_unique($array);
    }

    /**
     * Benchmark string operations
     */
    public function benchStringOperations()
    {
        $string = str_repeat('Hello World ', 100);

        // Various string operations
        strlen($string);
        strpos($string, 'World');
        str_replace('Hello', 'Hi', $string);
        explode(' ', $string);
        implode(' ', array_reverse(explode(' ', $string)));
    }

    /**
     * Benchmark file operations
     */
    public function benchFileOperations()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bench_');
        $data = str_repeat('Benchmark data content. ', 1000);

        // Write file
        file_put_contents($tempFile, $data);

        // Read file
        $content = file_get_contents($tempFile);

        // Get file info
        $size = filesize($tempFile);
        $modified = filemtime($tempFile);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Benchmark memory usage
     */
    public function benchMemoryUsage()
    {
        $arrays = [];

        // Create memory pressure
        for ($i = 0; $i < 100; $i++) {
            $arrays[] = range(1, 1000);
        }

        // Process arrays
        $total = 0;
        foreach ($arrays as $array) {
            $total += array_sum($array);
        }

        // Clean up
        unset($arrays);
    }

    /**
     * Benchmark exception handling
     */
    public function benchExceptionHandling()
    {
        try {
            throw new \Exception('Test exception');
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
            $trace = $e->getTraceAsString();
        }
    }

    /**
     * Benchmark date/time operations
     */
    public function benchDateTimeOperations()
    {
        $now = new \DateTime();

        // Various date operations
        $now->format('Y-m-d H:i:s');
        $now->modify('+1 day');
        $now->modify('-1 month');

        $timestamp = $now->getTimestamp();
        $fromTimestamp = new \DateTime("@{$timestamp}");

        $interval = $now->diff($fromTimestamp);
        $formatted = $interval->format('%d days %h hours %i minutes');
    }

    /**
     * Benchmark regex operations
     */
    public function benchRegexOperations()
    {
        $text = 'Email: test@example.com, Phone: +1-555-123-4567, URL: https://example.com/path?param=value';

        // Various regex operations
        preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $emailMatches);
        preg_match('/\+?[\d\s\-\(\)]+/', $text, $phoneMatches);
        preg_match('/https?:\/\/[^\s]+/', $text, $urlMatches);

        $replaced = preg_replace('/\d+/', 'XXX', $text);
        $split = preg_split('/[,;]/', $text);
    }

    /**
     * Benchmark serialization
     */
    public function benchSerialization()
    {
        $data = [
            'users' => array_fill(0, 50, [
                'id' => rand(1, 1000),
                'name' => 'User ' . rand(1, 1000),
                'email' => 'user' . rand(1, 1000) . '@example.com',
                'profile' => [
                    'bio' => str_repeat('Bio content ', 20),
                    'avatar' => 'https://example.com/avatar.jpg',
                    'preferences' => [
                        'theme' => 'dark',
                        'notifications' => true,
                        'language' => 'en'
                    ]
                ]
            ])
        ];

        // Serialize
        $serialized = serialize($data);

        // Unserialize
        $unserialized = unserialize($serialized);

        // JSON encode/decode
        $json = json_encode($data);
        $decoded = json_decode($json, true);
    }
}

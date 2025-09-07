<?php
/**
 * Integration tests for API Controller
 *
 * @package TPT
 * @subpackage Tests
 */

namespace TPT\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPT\Core\ApiController;
use TPT\Core\Database;
use TPT\Core\Request;
use TPT\Core\Response;
use TPT\Core\Router;
use TPT\Core\Application;
use PDO;

/**
 * ApiControllerTest class
 *
 * Tests API controller endpoints integration
 */
class ApiControllerTest extends TestCase
{
    /**
     * @var ApiController
     */
    private ApiController $apiController;

    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private Response $response;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table for testing
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ");

        // Mock Database class
        $this->database = $this->getMockBuilder(Database::class)
            ->onlyMethods(['getConnection', 'selectOne'])
            ->getMock();

        $this->database->method('getConnection')
            ->willReturn($this->pdo);

        $this->database->method('selectOne')
            ->willReturn(['count' => 5]); // Mock user count

        // Create mock request and response
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create API controller instance
        $this->apiController = new ApiController($this->database, $this->request, $this->response);
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->database = null;
        $this->request = null;
        $this->response = null;
        $this->apiController = null;
    }

    /**
     * Test health endpoint
     */
    public function testHealthEndpoint(): void
    {
        // Capture output
        ob_start();
        $this->apiController->health();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertEquals('healthy', $response['status']);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('services', $response);
        $this->assertEquals('1.0.0', $response['version']);
    }

    /**
     * Test info endpoint
     */
    public function testInfoEndpoint(): void
    {
        ob_start();
        $this->apiController->info();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertEquals('TPT Government Platform API', $response['name']);
        $this->assertEquals('1.0.0', $response['version']);
        $this->assertArrayHasKey('description', $response);
        $this->assertArrayHasKey('endpoints', $response);
        $this->assertArrayHasKey('features', $response);
        $this->assertIsArray($response['endpoints']);
        $this->assertIsArray($response['features']);
    }

    /**
     * Test services endpoint
     */
    public function testServicesEndpoint(): void
    {
        ob_start();
        $this->apiController->services();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('services', $response);
        $this->assertIsArray($response['services']);
        $this->assertCount(5, $response['services']); // Should have 5 services

        // Check first service structure
        $firstService = $response['services'][0];
        $this->assertArrayHasKey('id', $firstService);
        $this->assertArrayHasKey('name', $firstService);
        $this->assertArrayHasKey('description', $firstService);
        $this->assertArrayHasKey('category', $firstService);
        $this->assertEquals('permits', $firstService['id']);
        $this->assertEquals('Permit Applications', $firstService['name']);
    }

    /**
     * Test stats endpoint with admin role
     */
    public function testStatsEndpointWithAdminRole(): void
    {
        // Mock hasRole method to return true for admin
        $apiController = $this->getMockBuilder(ApiController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['hasRole'])
            ->getMock();

        $apiController->method('hasRole')
            ->with('admin')
            ->willReturn(true);

        ob_start();
        $apiController->stats();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('total_users', $response);
        $this->assertArrayHasKey('active_sessions', $response);
        $this->assertArrayHasKey('api_requests_today', $response);
        $this->assertArrayHasKey('database_connections', $response);
        $this->assertArrayHasKey('uptime', $response);
    }

    /**
     * Test stats endpoint without admin role
     */
    public function testStatsEndpointWithoutAdminRole(): void
    {
        // Mock hasRole method to return false for admin
        $apiController = $this->getMockBuilder(ApiController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['hasRole', 'error'])
            ->getMock();

        $apiController->method('hasRole')
            ->with('admin')
            ->willReturn(false);

        $apiController->expects($this->once())
            ->method('error')
            ->with('Access denied', 403);

        $apiController->stats();
    }

    /**
     * Test API response format
     */
    public function testApiResponseFormat(): void
    {
        ob_start();
        $this->apiController->health();
        $output = ob_get_clean();

        // Verify it's valid JSON
        $response = json_decode($output, true);
        $this->assertNotNull($response);

        // Verify JSON structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('services', $response);
    }

    /**
     * Test services data structure
     */
    public function testServicesDataStructure(): void
    {
        ob_start();
        $this->apiController->services();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        foreach ($response['services'] as $service) {
            $this->assertArrayHasKey('id', $service);
            $this->assertArrayHasKey('name', $service);
            $this->assertArrayHasKey('description', $service);
            $this->assertArrayHasKey('category', $service);

            $this->assertIsString($service['id']);
            $this->assertIsString($service['name']);
            $this->assertIsString($service['description']);
            $this->assertIsString($service['category']);

            $this->assertNotEmpty($service['id']);
            $this->assertNotEmpty($service['name']);
            $this->assertNotEmpty($service['description']);
            $this->assertNotEmpty($service['category']);
        }
    }

    /**
     * Test API info features structure
     */
    public function testApiInfoFeaturesStructure(): void
    {
        ob_start();
        $this->apiController->info();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('features', $response);
        $this->assertIsArray($response['features']);

        // Check that features contains expected AI integration
        $this->assertArrayHasKey('AI Integration', $response['features']);
        $this->assertStringContains('OpenAI', $response['features']['AI Integration']);
        $this->assertStringContains('Anthropic', $response['features']['AI Integration']);
        $this->assertStringContains('Gemini', $response['features']['AI Integration']);
    }

    /**
     * Test API endpoints documentation
     */
    public function testApiEndpointsDocumentation(): void
    {
        ob_start();
        $this->apiController->info();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('endpoints', $response);
        $this->assertIsArray($response['endpoints']);

        // Check that essential endpoints are documented
        $this->assertArrayHasKey('GET /api/health', $response['endpoints']);
        $this->assertArrayHasKey('POST /api/auth/login', $response['endpoints']);
        $this->assertArrayHasKey('GET /api/user/profile', $response['endpoints']);
        $this->assertArrayHasKey('GET /api/services', $response['endpoints']);
        $this->assertArrayHasKey('POST /api/webhooks', $response['endpoints']);
    }

    /**
     * Test health check with database connection
     */
    public function testHealthCheckWithDatabaseConnection(): void
    {
        ob_start();
        $this->apiController->health();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertEquals('connected', $response['services']['database']);
    }

    /**
     * Test health check services structure
     */
    public function testHealthCheckServicesStructure(): void
    {
        ob_start();
        $this->apiController->health();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('services', $response);
        $this->assertIsArray($response['services']);
        $this->assertArrayHasKey('database', $response['services']);
        $this->assertArrayHasKey('session', $response['services']);
    }
}

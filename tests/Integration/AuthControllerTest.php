<?php
/**
 * Integration tests for Authentication Controller
 *
 * @package TPT
 * @subpackage Tests
 */

namespace TPT\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPT\Core\AuthController;
use TPT\Core\Database;
use TPT\Core\Request;
use TPT\Core\Response;
use TPT\Core\Session;
use PDO;

/**
 * AuthControllerTest class
 *
 * Tests authentication controller endpoints integration
 */
class AuthControllerTest extends TestCase
{
    /**
     * @var AuthController
     */
    private AuthController $authController;

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
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(100),
                roles TEXT,
                department VARCHAR(100),
                active BOOLEAN DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ");

        // Create sessions table
        $this->pdo->exec("
            CREATE TABLE sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id VARCHAR(255) NOT NULL UNIQUE,
                user_id INTEGER,
                ip_address VARCHAR(45),
                user_agent TEXT,
                expires_at DATETIME,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Insert test user
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        $this->pdo->prepare("
            INSERT INTO users (email, password_hash, name, roles, department, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            'test@example.com',
            $passwordHash,
            'Test User',
            '["user"]',
            'Test Department',
            1,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        // Mock Database class
        $this->database = $this->getMockBuilder(Database::class)
            ->onlyMethods(['getConnection', 'selectOne'])
            ->getMock();

        $this->database->method('getConnection')
            ->willReturn($this->pdo);

        // Mock Request class
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock Response class
        $this->response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create Auth controller instance
        $this->authController = new AuthController($this->database, $this->request, $this->response);
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
        $this->authController = null;
    }

    /**
     * Test successful login
     */
    public function testSuccessfulLogin(): void
    {
        // Mock request post data
        $this->request->method('post')
            ->willReturnMap([
                ['email', 'test@example.com'],
                ['password', 'password123']
            ]);

        // Mock validation to return valid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'success', 'error', 'logAction'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => true]);

        $authController->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function($data) {
                    return isset($data['user']) && isset($data['redirect']);
                }),
                'Login successful'
            );

        $authController->expects($this->once())
            ->method('logAction')
            ->with('login', ['email' => 'test@example.com']);

        $authController->login();
    }

    /**
     * Test login with invalid credentials
     */
    public function testLoginWithInvalidCredentials(): void
    {
        // Mock request post data with wrong password
        $this->request->method('post')
            ->willReturnMap([
                ['email', 'test@example.com'],
                ['password', 'wrongpassword']
            ]);

        // Mock validation to return valid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'success', 'error', 'logAction'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => true]);

        $authController->expects($this->once())
            ->method('error')
            ->with('Invalid email or password', 401);

        $authController->expects($this->once())
            ->method('logAction')
            ->with('login_failed', ['email' => 'test@example.com']);

        $authController->login();
    }

    /**
     * Test login with validation failure
     */
    public function testLoginWithValidationFailure(): void
    {
        // Mock validation to return invalid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'error'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => false]);

        $authController->expects($this->once())
            ->method('error')
            ->with('Validation failed', 422);

        $authController->login();
    }

    /**
     * Test logout functionality
     */
    public function testLogout(): void
    {
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['getCurrentUserId', 'success', 'logAction'])
            ->getMock();

        $authController->method('getCurrentUserId')
            ->willReturn(1);

        $authController->expects($this->once())
            ->method('logAction')
            ->with('logout');

        $authController->expects($this->once())
            ->method('success')
            ->with(['redirect' => '/'], 'Logout successful');

        $authController->logout();
    }

    /**
     * Test session endpoint when authenticated
     */
    public function testSessionEndpointWhenAuthenticated(): void
    {
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['isAuthenticated', 'getCurrentUser', 'json'])
            ->getMock();

        $authController->method('isAuthenticated')
            ->willReturn(true);

        $userData = [
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];

        $authController->method('getCurrentUser')
            ->willReturn($userData);

        $authController->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['authenticated']) &&
                       isset($data['user']) &&
                       isset($data['session_id']) &&
                       $data['authenticated'] === true;
            }));

        $authController->session();
    }

    /**
     * Test session endpoint when not authenticated
     */
    public function testSessionEndpointWhenNotAuthenticated(): void
    {
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['isAuthenticated', 'error'])
            ->getMock();

        $authController->method('isAuthenticated')
            ->willReturn(false);

        $authController->expects($this->once())
            ->method('error')
            ->with('Not authenticated', 401);

        $authController->session();
    }

    /**
     * Test authentication with demo credentials
     */
    public function testAuthenticationWithDemoCredentials(): void
    {
        // Mock request post data with demo credentials
        $this->request->method('post')
            ->willReturnMap([
                ['email', 'admin@gov.local'],
                ['password', 'password']
            ]);

        // Mock validation to return valid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'success', 'error', 'logAction'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => true]);

        $authController->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function($data) {
                    return isset($data['user']) &&
                           $data['user']['email'] === 'admin@gov.local' &&
                           isset($data['redirect']);
                }),
                'Login successful'
            );

        $authController->login();
    }

    /**
     * Test authentication with database user
     */
    public function testAuthenticationWithDatabaseUser(): void
    {
        // Mock request post data
        $this->request->method('post')
            ->willReturnMap([
                ['email', 'test@example.com'],
                ['password', 'password123']
            ]);

        // Mock validation to return valid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'success', 'error', 'logAction'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => true]);

        // Mock database selectOne method
        $this->database->method('selectOne')
            ->willReturn([
                'id' => 1,
                'email' => 'test@example.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'name' => 'Test User',
                'roles' => '["user"]',
                'department' => 'Test Department',
                'active' => 1
            ]);

        $authController->expects($this->once())
            ->method('success')
            ->with(
                $this->callback(function($data) {
                    return isset($data['user']) &&
                           !isset($data['user']['password_hash']) && // Password should be removed
                           isset($data['redirect']);
                }),
                'Login successful'
            );

        $authController->login();
    }

    /**
     * Test login with inactive user
     */
    public function testLoginWithInactiveUser(): void
    {
        // Mock request post data
        $this->request->method('post')
            ->willReturnMap([
                ['email', 'inactive@example.com'],
                ['password', 'password123']
            ]);

        // Mock validation to return valid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'success', 'error', 'logAction'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => true]);

        // Mock database to return inactive user
        $this->database->method('selectOne')
            ->willReturn([
                'id' => 2,
                'email' => 'inactive@example.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'name' => 'Inactive User',
                'roles' => '["user"]',
                'department' => 'Test Department',
                'active' => 0 // Inactive user
            ]);

        $authController->expects($this->once())
            ->method('error')
            ->with('Invalid email or password', 401);

        $authController->login();
    }

    /**
     * Test login with non-existent user
     */
    public function testLoginWithNonExistentUser(): void
    {
        // Mock request post data
        $this->request->method('post')
            ->willReturnMap([
                ['email', 'nonexistent@example.com'],
                ['password', 'password123']
            ]);

        // Mock validation to return valid
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['validate', 'success', 'error', 'logAction'])
            ->getMock();

        $authController->method('validate')
            ->willReturn(['valid' => true]);

        // Mock database to return null (user not found)
        $this->database->method('selectOne')
            ->willReturn(null);

        $authController->expects($this->once())
            ->method('error')
            ->with('Invalid email or password', 401);

        $authController->login();
    }

    /**
     * Test password verification
     */
    public function testPasswordVerification(): void
    {
        $password = 'testpassword123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrongpassword', $hash));
    }

    /**
     * Test session data structure
     */
    public function testSessionDataStructure(): void
    {
        $authController = $this->getMockBuilder(AuthController::class)
            ->setConstructorArgs([$this->database, $this->request, $this->response])
            ->onlyMethods(['isAuthenticated', 'getCurrentUser', 'json'])
            ->getMock();

        $authController->method('isAuthenticated')
            ->willReturn(true);

        $userData = [
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
            'roles' => ['user'],
            'department' => 'Test Department'
        ];

        $authController->method('getCurrentUser')
            ->willReturn($userData);

        $authController->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return is_array($data) &&
                       isset($data['authenticated']) &&
                       isset($data['user']) &&
                       isset($data['session_id']) &&
                       isset($data['login_time']) &&
                       $data['authenticated'] === true &&
                       is_array($data['user']);
            }));

        $authController->session();
    }
}

<?php
/**
 * Unit tests for Auth class
 *
 * @package TPT
 * @subpackage Tests
 */

namespace TPT\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use TPT\Core\Auth;
use TPT\Core\Database;
use PDO;

/**
 * AuthTest class
 *
 * Tests the Auth class functionality
 */
class AuthTest extends TestCase
{
    /**
     * @var Auth
     */
    private Auth $auth;

    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * @var Database
     */
    private Database $database;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create users table
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                email_verified_at DATETIME,
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

        // Mock Database class
        $this->database = $this->getMockBuilder(Database::class)
            ->onlyMethods(['getConnection'])
            ->getMock();

        $this->database->method('getConnection')
            ->willReturn($this->pdo);

        // Create Auth instance
        $this->auth = new Auth($this->database);
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->database = null;
        $this->auth = null;
    }

    /**
     * Test user registration
     */
    public function testUserRegistration(): void
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User'
        ];

        $result = $this->auth->register($userData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertIsInt($result['user_id']);

        // Verify user was created in database
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$result['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('testuser', $user['username']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Test', $user['first_name']);
        $this->assertEquals('User', $user['last_name']);
        $this->assertEquals('user', $user['role']);
        $this->assertEquals('active', $user['status']);
    }

    /**
     * Test user login with valid credentials
     */
    public function testUserLoginValidCredentials(): void
    {
        // First register a user
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $this->auth->register($userData);

        // Now try to login
        $result = $this->auth->login('testuser', 'password123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertEquals('testuser', $result['user']['username']);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    /**
     * Test user login with invalid credentials
     */
    public function testUserLoginInvalidCredentials(): void
    {
        // First register a user
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $this->auth->register($userData);

        // Try to login with wrong password
        $result = $this->auth->login('testuser', 'wrongpassword');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid credentials', $result['message']);
    }

    /**
     * Test user login with non-existent user
     */
    public function testUserLoginNonExistentUser(): void
    {
        $result = $this->auth->login('nonexistent', 'password123');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid credentials', $result['message']);
    }

    /**
     * Test password hashing and verification
     */
    public function testPasswordHashing(): void
    {
        $password = 'testpassword123';

        // Test password hashing
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->assertNotEquals($password, $hash);

        // Test password verification
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrongpassword', $hash));
    }

    /**
     * Test session creation
     */
    public function testSessionCreation(): void
    {
        // Register and login user
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $this->auth->register($userData);
        $loginResult = $this->auth->login('testuser', 'password123');

        $this->assertTrue($loginResult['success']);
        $this->assertArrayHasKey('session_id', $loginResult);

        // Verify session was created in database
        $stmt = $this->pdo->prepare("SELECT * FROM sessions WHERE session_id = ?");
        $stmt->execute([$loginResult['session_id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($session);
        $this->assertEquals($loginResult['user']['id'], $session['user_id']);
    }

    /**
     * Test user logout
     */
    public function testUserLogout(): void
    {
        // Register and login user
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $this->auth->register($userData);
        $loginResult = $this->auth->login('testuser', 'password123');

        $sessionId = $loginResult['session_id'];

        // Logout user
        $result = $this->auth->logout($sessionId);

        $this->assertTrue($result['success']);

        // Verify session was removed
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(0, $row['count']);
    }

    /**
     * Test user authentication check
     */
    public function testUserAuthenticationCheck(): void
    {
        // Register and login user
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $this->auth->register($userData);
        $loginResult = $this->auth->login('testuser', 'password123');

        $sessionId = $loginResult['session_id'];

        // Check authentication
        $result = $this->auth->check($sessionId);

        $this->assertTrue($result['authenticated']);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('testuser', $result['user']['username']);
    }

    /**
     * Test authentication check with invalid session
     */
    public function testAuthenticationCheckInvalidSession(): void
    {
        $result = $this->auth->check('invalid_session_id');

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid session', $result['message']);
    }

    /**
     * Test user role checking
     */
    public function testUserRoleChecking(): void
    {
        // Register admin user
        $userData = [
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'role' => 'admin'
        ];

        $this->auth->register($userData);
        $loginResult = $this->auth->login('admin', 'admin123');

        $this->assertTrue($loginResult['success']);
        $this->assertEquals('admin', $loginResult['user']['role']);
    }

    /**
     * Test duplicate user registration
     */
    public function testDuplicateUserRegistration(): void
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        // Register user first time
        $result1 = $this->auth->register($userData);
        $this->assertTrue($result1['success']);

        // Try to register same user again
        $result2 = $this->auth->register($userData);
        $this->assertFalse($result2['success']);
        $this->assertStringContains('already exists', $result2['message']);
    }
}

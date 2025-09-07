<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded before running tests to set up the testing environment.
 *
 * @package TPT
 * @subpackage Tests
 */

// Define application constants for testing
define('APP_ROOT', dirname(__DIR__));
define('TESTS_ROOT', __DIR__);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Include the autoloader
require_once APP_ROOT . '/src/php/core/Autoloader.php';

// Register the autoloader
$autoloader = new TPT\Core\Autoloader();
$autoloader->register();

// Set up test database if needed
if (!defined('TEST_DB_SETUP')) {
    define('TEST_DB_SETUP', true);

    // Create in-memory SQLite database for testing
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create test tables
    $schema = file_get_contents(TESTS_ROOT . '/schema/test_schema.sql');
    if ($schema) {
        $pdo->exec($schema);
    }

    // Store PDO instance for tests
    global $testDb;
    $testDb = $pdo;
}

/**
 * Get test database connection
 *
 * @return PDO
 */
function getTestDatabase(): PDO
{
    global $testDb;
    return $testDb;
}

/**
 * Clean up test data
 *
 * @param PDO $pdo
 * @return void
 */
function cleanupTestData(PDO $pdo): void
{
    $tables = [
        'users',
        'sessions',
        'audit_logs',
        'notifications',
        'webhooks',
        'workflow_instances',
        'erp_connections',
        'plugins'
    ];

    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM {$table}");
    }

    // Reset auto-increment counters
    $pdo->exec("DELETE FROM sqlite_sequence");
}

/**
 * Create test user
 *
 * @param PDO $pdo
 * @param array $userData
 * @return int User ID
 */
function createTestUser(PDO $pdo, array $userData = []): int
{
    $defaults = [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'user',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $data = array_merge($defaults, $userData);

    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, role, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['username'],
        $data['email'],
        $data['password_hash'],
        $data['role'],
        $data['status'],
        $data['created_at'],
        $data['updated_at']
    ]);

    return $pdo->lastInsertId();
}

/**
 * Create test session
 *
 * @param PDO $pdo
 * @param int $userId
 * @param array $sessionData
 * @return string Session ID
 */
function createTestSession(PDO $pdo, int $userId, array $sessionData = []): string
{
    $defaults = [
        'session_id' => bin2hex(random_bytes(32)),
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Browser',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'created_at' => date('Y-m-d H:i:s')
    ];

    $data = array_merge($defaults, $sessionData);

    $stmt = $pdo->prepare("
        INSERT INTO sessions (session_id, user_id, ip_address, user_agent, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['session_id'],
        $data['user_id'],
        $data['ip_address'],
        $data['user_agent'],
        $data['expires_at'],
        $data['created_at']
    ]);

    return $data['session_id'];
}

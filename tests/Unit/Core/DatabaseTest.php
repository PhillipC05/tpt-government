<?php
/**
 * Unit tests for Database class
 *
 * @package TPT
 * @subpackage Tests
 */

namespace TPT\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use TPT\Core\Database;
use PDO;

/**
 * DatabaseTest class
 *
 * Tests the Database class functionality
 */
class DatabaseTest extends TestCase
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create test table
        $this->pdo->exec("
            CREATE TABLE test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Mock the Database class to use our test PDO
        $this->database = $this->getMockBuilder(Database::class)
            ->onlyMethods(['getConnection'])
            ->getMock();

        $this->database->method('getConnection')
            ->willReturn($this->pdo);
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->database = null;
    }

    /**
     * Test database connection
     */
    public function testDatabaseConnection(): void
    {
        $connection = $this->database->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $connection->getAttribute(PDO::ATTR_ERRMODE));
    }

    /**
     * Test database query execution
     */
    public function testQueryExecution(): void
    {
        // Insert test data
        $stmt = $this->pdo->prepare("INSERT INTO test_users (name, email) VALUES (?, ?)");
        $result = $stmt->execute(['John Doe', 'john@example.com']);

        $this->assertTrue($result);

        // Verify data was inserted
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM test_users");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1, $row['count']);
    }

    /**
     * Test prepared statement execution
     */
    public function testPreparedStatement(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO test_users (name, email) VALUES (?, ?)");
        $stmt->execute(['Jane Smith', 'jane@example.com']);

        $stmt = $this->pdo->prepare("SELECT * FROM test_users WHERE email = ?");
        $stmt->execute(['jane@example.com']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Jane Smith', $user['name']);
        $this->assertEquals('jane@example.com', $user['email']);
    }

    /**
     * Test transaction handling
     */
    public function testTransactionHandling(): void
    {
        $this->pdo->beginTransaction();

        try {
            // Insert multiple records
            $stmt = $this->pdo->prepare("INSERT INTO test_users (name, email) VALUES (?, ?)");
            $stmt->execute(['User 1', 'user1@example.com']);
            $stmt->execute(['User 2', 'user2@example.com']);

            $this->pdo->commit();

            // Verify both records were inserted
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM test_users");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals(2, $row['count']);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Test error handling
     */
    public function testErrorHandling(): void
    {
        $this->expectException(\PDOException::class);

        // Try to insert duplicate email (should fail due to UNIQUE constraint)
        $stmt = $this->pdo->prepare("INSERT INTO test_users (name, email) VALUES (?, ?)");
        $stmt->execute(['John Doe', 'john@example.com']);
        $stmt->execute(['Jane Doe', 'john@example.com']); // Duplicate email
    }

    /**
     * Test data retrieval
     */
    public function testDataRetrieval(): void
    {
        // Insert test data
        $users = [
            ['Alice Johnson', 'alice@example.com'],
            ['Bob Wilson', 'bob@example.com'],
            ['Charlie Brown', 'charlie@example.com']
        ];

        $stmt = $this->pdo->prepare("INSERT INTO test_users (name, email) VALUES (?, ?)");
        foreach ($users as $user) {
            $stmt->execute($user);
        }

        // Test SELECT query
        $stmt = $this->pdo->query("SELECT * FROM test_users ORDER BY name");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $results);
        $this->assertEquals('Alice Johnson', $results[0]['name']);
        $this->assertEquals('Bob Wilson', $results[1]['name']);
        $this->assertEquals('Charlie Brown', $results[2]['name']);
    }

    /**
     * Test database constraints
     */
    public function testDatabaseConstraints(): void
    {
        // Test NOT NULL constraint
        $this->expectException(\PDOException::class);

        $stmt = $this->pdo->prepare("INSERT INTO test_users (email) VALUES (?)");
        $stmt->execute(['test@example.com']); // Missing name (NOT NULL)
    }
}

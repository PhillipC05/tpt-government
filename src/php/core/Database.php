<?php
/**
 * TPT Government Platform - Database Handler
 *
 * Secure database abstraction layer for PostgreSQL.
 * Supports prepared statements, transactions, and connection pooling.
 */

namespace Core;

class Database
{
    /**
     * PDO instance
     */
    private ?\PDO $pdo = null;

    /**
     * Database configuration
     */
    private array $config;

    /**
     * Connection status
     */
    private bool $connected = false;

    /**
     * Last query executed
     */
    private ?string $lastQuery = null;

    /**
     * Query execution time
     */
    private float $queryTime = 0.0;

    /**
     * Constructor
     *
     * @param array $config Database configuration
     */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'tpt_gov',
            'username' => 'postgres',
            'password' => '',
            'charset' => 'utf8',
            'options' => [],
            'pool_size' => 10
        ], $config);
    }

    /**
     * Connect to database
     *
     * @return bool True if connected successfully
     * @throws \Exception If connection fails
     */
    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['username'],
                $this->config['password']
            );

            $options = array_merge([
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_PERSISTENT => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ], $this->config['options']);

            $this->pdo = new \PDO($dsn, $this->config['username'], $this->config['password'], $options);
            $this->connected = true;

            // Set charset
            $this->pdo->exec("SET NAMES '{$this->config['charset']}'");

            return true;

        } catch (\PDOException $e) {
            $this->connected = false;
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect from database
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
    }

    /**
     * Check if connected
     *
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }

    /**
     * Execute a query
     *
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @return \PDOStatement The PDO statement
     * @throws \Exception If query fails
     */
    public function query(string $query, array $params = []): \PDOStatement
    {
        $this->ensureConnection();

        $startTime = microtime(true);

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $this->lastQuery = $query;
            $this->queryTime = microtime(true) - $startTime;

            return $stmt;

        } catch (\PDOException $e) {
            $this->logError($query, $params, $e);
            throw new \Exception('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute a SELECT query and return all results
     *
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @return array The results
     */
    public function select(string $query, array $params = []): array
    {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SELECT query and return first result
     *
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @return array|null The first result or null
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute an INSERT query
     *
     * @param string $table The table name
     * @param array $data The data to insert
     * @return int The inserted ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $this->query($query, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Execute an UPDATE query
     *
     * @param string $table The table name
     * @param array $data The data to update
     * @param array $conditions The WHERE conditions
     * @return int The number of affected rows
     */
    public function update(string $table, array $data, array $conditions): int
    {
        $setParts = [];
        $params = array_values($data);

        foreach (array_keys($data) as $column) {
            $setParts[] = $this->quoteIdentifier($column) . ' = ?';
        }

        $whereParts = [];
        foreach ($conditions as $column => $value) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->query($query, $params);
        return $stmt->rowCount();
    }

    /**
     * Execute a DELETE query
     *
     * @param string $table The table name
     * @param array $conditions The WHERE conditions
     * @return int The number of affected rows
     */
    public function delete(string $table, array $conditions): int
    {
        $whereParts = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(' AND ', $whereParts)
        );

        $stmt = $this->query($query, $params);
        return $stmt->rowCount();
    }

    /**
     * Start a transaction
     *
     * @return bool True if transaction started
     */
    public function beginTransaction(): bool
    {
        $this->ensureConnection();
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool True if committed
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool True if rolled back
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in transaction
     *
     * @return bool True if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Get last insert ID
     *
     * @param string|null $name The sequence name
     * @return string The last insert ID
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Quote an identifier (table/column name)
     *
     * @param string $identifier The identifier
     * @return string The quoted identifier
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * Get table information
     *
     * @param string $table The table name
     * @return array The table information
     */
    public function getTableInfo(string $table): array
    {
        $query = "SELECT column_name, data_type, is_nullable, column_default
                  FROM information_schema.columns
                  WHERE table_name = ?
                  ORDER BY ordinal_position";

        return $this->select($query, [$table]);
    }

    /**
     * Get database statistics
     *
     * @return array The database statistics
     */
    public function getStats(): array
    {
        return [
            'connected' => $this->connected,
            'last_query' => $this->lastQuery,
            'query_time' => $this->queryTime,
            'server_info' => $this->pdo ? $this->pdo->getAttribute(\PDO::ATTR_SERVER_INFO) : null
        ];
    }

    /**
     * Ensure database connection
     *
     * @return void
     * @throws \Exception If not connected
     */
    private function ensureConnection(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    /**
     * Log database errors
     *
     * @param string $query The query
     * @param array $params The parameters
     * @param \PDOException $e The exception
     * @return void
     */
    private function logError(string $query, array $params, \PDOException $e): void
    {
        $logMessage = sprintf(
            "[%s] Database Error: %s\nQuery: %s\nParams: %s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $query,
            json_encode($params)
        );

        error_log($logMessage);

        // Log to file if configured
        if (defined('LOGS_PATH')) {
            file_put_contents(
                LOGS_PATH . '/database.log',
                $logMessage,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    /**
     * Get PDO instance (for advanced usage)
     *
     * @return \PDO The PDO instance
     */
    public function getPdo(): \PDO
    {
        $this->ensureConnection();
        return $this->pdo;
    }
}

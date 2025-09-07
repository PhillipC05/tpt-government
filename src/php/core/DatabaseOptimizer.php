<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Database Optimization and Performance Monitoring
 *
 * This class provides comprehensive database optimization features including:
 * - Query performance monitoring
 * - Index recommendations
 * - Connection pooling
 * - Query caching
 * - Slow query analysis
 * - Database health monitoring
 */
class DatabaseOptimizer
{
    private PDO $pdo;
    private array $config;
    private array $queryCache = [];
    private array $performanceMetrics = [];
    private float $slowQueryThreshold = 1.0; // seconds

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'cache_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
            'max_connections' => 10,
            'connection_timeout' => 30,
            'query_timeout' => 30,
            'enable_query_logging' => true,
            'slow_query_log' => '/var/log/tpt-gov/slow-queries.log'
        ], $config);

        $this->initializeConnectionPool();
        $this->createPerformanceTables();
    }

    /**
     * Initialize connection pooling for better performance
     */
    private function initializeConnectionPool(): void
    {
        try {
            // Set connection attributes for better performance
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Enable query caching if supported
            if ($this->config['cache_enabled']) {
                $this->pdo->exec("SET SESSION query_cache_type = ON");
                $this->pdo->exec("SET SESSION query_cache_size = 268435456"); // 256MB
            }

            // Set timeouts
            $this->pdo->exec("SET SESSION wait_timeout = {$this->config['connection_timeout']}");
            $this->pdo->exec("SET SESSION interactive_timeout = {$this->config['connection_timeout']}");
            $this->pdo->exec("SET SESSION max_execution_time = {$this->config['query_timeout']}000"); // Convert to milliseconds

        } catch (PDOException $e) {
            error_log("Database optimization initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Create performance monitoring tables
     */
    private function createPerformanceTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS query_performance_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                query_hash VARCHAR(64) NOT NULL,
                query_text TEXT NOT NULL,
                execution_time DECIMAL(10,6) NOT NULL,
                rows_affected INT DEFAULT 0,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_id INT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                INDEX idx_query_hash (query_hash),
                INDEX idx_timestamp (timestamp),
                INDEX idx_execution_time (execution_time)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS database_metrics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                metric_value DECIMAL(15,6) NOT NULL,
                collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_metric_name (metric_name),
                INDEX idx_collected_at (collected_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS index_recommendations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL,
                column_name VARCHAR(100) NOT NULL,
                recommendation_type ENUM('missing_index', 'unused_index', 'duplicate_index') NOT NULL,
                impact_score DECIMAL(5,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                applied_at TIMESTAMP NULL,
                INDEX idx_table_column (table_name, column_name),
                INDEX idx_recommendation_type (recommendation_type)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create performance tables: " . $e->getMessage());
        }
    }

    /**
     * Execute optimized query with performance monitoring
     */
    public function executeOptimizedQuery(string $sql, array $params = [], bool $useCache = true): array
    {
        $startTime = microtime(true);
        $queryHash = $this->generateQueryHash($sql, $params);

        // Check cache first
        if ($useCache && $this->config['cache_enabled'] && isset($this->queryCache[$queryHash])) {
            $cached = $this->queryCache[$queryHash];
            if ($cached['expires'] > time()) {
                return $cached['result'];
            } else {
                unset($this->queryCache[$queryHash]);
            }
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rowsAffected = $stmt->rowCount();

            $executionTime = microtime(true) - $startTime;

            // Log performance metrics
            $this->logQueryPerformance($queryHash, $sql, $executionTime, $rowsAffected);

            // Check for slow queries
            if ($executionTime > $this->slowQueryThreshold) {
                $this->logSlowQuery($sql, $params, $executionTime);
            }

            // Cache result if appropriate
            if ($useCache && $this->config['cache_enabled'] && $this->isCacheableQuery($sql)) {
                $this->queryCache[$queryHash] = [
                    'result' => $result,
                    'expires' => time() + $this->config['cache_ttl']
                ];
            }

            return $result;

        } catch (PDOException $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logQueryError($sql, $params, $e, $executionTime);
            throw $e;
        }
    }

    /**
     * Analyze and optimize database indexes
     */
    public function analyzeIndexes(): array
    {
        $recommendations = [];

        try {
            // Find tables without primary keys
            $stmt = $this->pdo->query("
                SELECT TABLE_NAME
                FROM information_schema.TABLES t
                LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu
                    ON t.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                    AND t.TABLE_NAME = kcu.TABLE_NAME
                    AND kcu.CONSTRAINT_NAME = 'PRIMARY'
                WHERE t.TABLE_SCHEMA = DATABASE()
                    AND kcu.COLUMN_NAME IS NULL
                    AND t.TABLE_NAME NOT LIKE 'query_performance_log'
                    AND t.TABLE_NAME NOT LIKE 'database_metrics'
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recommendations[] = [
                    'type' => 'missing_primary_key',
                    'table' => $row['TABLE_NAME'],
                    'recommendation' => "Add PRIMARY KEY to table {$row['TABLE_NAME']}",
                    'impact' => 'high'
                ];
            }

            // Analyze slow queries and suggest indexes
            $stmt = $this->pdo->query("
                SELECT query_text, COUNT(*) as frequency, AVG(execution_time) as avg_time
                FROM query_performance_log
                WHERE execution_time > 0.5
                    AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY query_hash
                HAVING frequency > 5
                ORDER BY avg_time DESC
                LIMIT 10
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $table = $this->extractTableFromQuery($row['query_text']);
                $columns = $this->extractColumnsFromQuery($row['query_text']);

                if ($table && $columns) {
                    $recommendations[] = [
                        'type' => 'missing_index',
                        'table' => $table,
                        'columns' => $columns,
                        'recommendation' => "Add index on {$table}({$columns}) for slow query",
                        'impact' => 'high',
                        'frequency' => $row['frequency'],
                        'avg_time' => $row['avg_time']
                    ];
                }
            }

        } catch (PDOException $e) {
            error_log("Index analysis failed: " . $e->getMessage());
        }

        return $recommendations;
    }

    /**
     * Optimize database configuration
     */
    public function optimizeConfiguration(): array
    {
        $optimizations = [];

        try {
            // Check current MySQL variables
            $variables = $this->getMySQLVariables();

            // InnoDB buffer pool size
            $innodbBufferPoolSize = $variables['innodb_buffer_pool_size'] ?? 0;
            $totalMemory = $this->getTotalMemory();

            if ($innodbBufferPoolSize < $totalMemory * 0.7) {
                $recommendedSize = (int)($totalMemory * 0.7);
                $optimizations[] = [
                    'parameter' => 'innodb_buffer_pool_size',
                    'current' => $innodbBufferPoolSize,
                    'recommended' => $recommendedSize,
                    'sql' => "SET GLOBAL innodb_buffer_pool_size = {$recommendedSize};"
                ];
            }

            // Query cache size
            if ($this->config['cache_enabled']) {
                $queryCacheSize = $variables['query_cache_size'] ?? 0;
                if ($queryCacheSize < 268435456) { // 256MB
                    $optimizations[] = [
                        'parameter' => 'query_cache_size',
                        'current' => $queryCacheSize,
                        'recommended' => 268435456,
                        'sql' => "SET GLOBAL query_cache_size = 268435456;"
                    ];
                }
            }

            // Max connections
            $maxConnections = $variables['max_connections'] ?? 0;
            if ($maxConnections < $this->config['max_connections']) {
                $optimizations[] = [
                    'parameter' => 'max_connections',
                    'current' => $maxConnections,
                    'recommended' => $this->config['max_connections'],
                    'sql' => "SET GLOBAL max_connections = {$this->config['max_connections']};"
                ];
            }

        } catch (PDOException $e) {
            error_log("Configuration optimization failed: " . $e->getMessage());
        }

        return $optimizations;
    }

    /**
     * Get database health metrics
     */
    public function getHealthMetrics(): array
    {
        $metrics = [];

        try {
            // Connection count
            $stmt = $this->pdo->query("SHOW PROCESSLIST");
            $connections = $stmt->rowCount();
            $metrics['active_connections'] = $connections;

            // Slow queries
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as slow_queries
                FROM query_performance_log
                WHERE execution_time > {$this->slowQueryThreshold}
                    AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $metrics['slow_queries_last_hour'] = $stmt->fetch(PDO::FETCH_ASSOC)['slow_queries'];

            // Database size
            $stmt = $this->pdo->query("
                SELECT
                    SUM(data_length + index_length) / 1024 / 1024 as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
            ");
            $metrics['database_size_mb'] = round($stmt->fetch(PDO::FETCH_ASSOC)['size_mb'], 2);

            // Table statistics
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) as total_tables,
                    SUM(table_rows) as total_rows,
                    AVG(table_rows) as avg_rows_per_table
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                    AND table_type = 'BASE TABLE'
            ");
            $tableStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $metrics = array_merge($metrics, $tableStats);

        } catch (PDOException $e) {
            error_log("Health metrics collection failed: " . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Create optimized indexes based on analysis
     */
    public function createOptimizedIndexes(): array
    {
        $createdIndexes = [];
        $recommendations = $this->analyzeIndexes();

        foreach ($recommendations as $rec) {
            if ($rec['type'] === 'missing_index' && isset($rec['table']) && isset($rec['columns'])) {
                try {
                    $indexName = "idx_" . str_replace(',', '_', $rec['columns']) . "_opt";
                    $sql = "CREATE INDEX {$indexName} ON {$rec['table']} ({$rec['columns']})";

                    $this->pdo->exec($sql);

                    $createdIndexes[] = [
                        'table' => $rec['table'],
                        'index' => $indexName,
                        'columns' => $rec['columns'],
                        'sql' => $sql
                    ];

                    // Log the index creation
                    $this->pdo->prepare("
                        INSERT INTO index_recommendations
                        (table_name, column_name, recommendation_type, applied_at)
                        VALUES (?, ?, 'missing_index', NOW())
                    ")->execute([$rec['table'], $rec['columns']]);

                } catch (PDOException $e) {
                    error_log("Failed to create index on {$rec['table']}: " . $e->getMessage());
                }
            }
        }

        return $createdIndexes;
    }

    /**
     * Generate query hash for caching
     */
    private function generateQueryHash(string $sql, array $params): string
    {
        return hash('sha256', $sql . serialize($params));
    }

    /**
     * Check if query is cacheable
     */
    private function isCacheableQuery(string $sql): bool
    {
        $sql = strtolower(trim($sql));

        // Only cache SELECT queries
        if (!str_starts_with($sql, 'select')) {
            return false;
        }

        // Don't cache queries with NOW(), RAND(), etc.
        $nonCacheable = ['now()', 'rand()', 'current_timestamp', 'uuid()'];
        foreach ($nonCacheable as $func) {
            if (str_contains($sql, $func)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log query performance
     */
    private function logQueryPerformance(string $queryHash, string $sql, float $executionTime, int $rowsAffected): void
    {
        if (!$this->config['enable_query_logging']) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO query_performance_log
                (query_hash, query_text, execution_time, rows_affected)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$queryHash, $sql, $executionTime, $rowsAffected]);
        } catch (PDOException $e) {
            error_log("Failed to log query performance: " . $e->getMessage());
        }
    }

    /**
     * Log slow queries
     */
    private function logSlowQuery(string $sql, array $params, float $executionTime): void
    {
        $logEntry = sprintf(
            "[%s] Slow Query (%.6f seconds): %s | Params: %s\n",
            date('Y-m-d H:i:s'),
            $executionTime,
            $sql,
            json_encode($params)
        );

        if ($this->config['slow_query_log']) {
            file_put_contents($this->config['slow_query_log'], $logEntry, FILE_APPEND | LOCK_EX);
        }

        error_log("Slow Query: " . $logEntry);
    }

    /**
     * Log query errors
     */
    private function logQueryError(string $sql, array $params, PDOException $e, float $executionTime): void
    {
        $logEntry = sprintf(
            "[%s] Query Error (%.6f seconds): %s | Params: %s | Error: %s\n",
            date('Y-m-d H:i:s'),
            $executionTime,
            $sql,
            json_encode($params),
            $e->getMessage()
        );

        error_log("Query Error: " . $logEntry);
    }

    /**
     * Extract table name from SQL query
     */
    private function extractTableFromQuery(string $sql): ?string
    {
        $patterns = [
            '/FROM\s+([`\w]+)/i',
            '/UPDATE\s+([`\w]+)/i',
            '/INSERT\s+INTO\s+([`\w]+)/i',
            '/DELETE\s+FROM\s+([`\w]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $matches)) {
                return trim($matches[1], '`');
            }
        }

        return null;
    }

    /**
     * Extract columns from WHERE clause
     */
    private function extractColumnsFromQuery(string $sql): ?string
    {
        if (preg_match('/WHERE\s+(.+?)(?:\s+(?:ORDER|GROUP|LIMIT)|\s*$)/i', $sql, $matches)) {
            $whereClause = $matches[1];

            // Extract column names from WHERE conditions
            $columns = [];
            if (preg_match_all('/([`\w]+)\s*[=<>!]+\s*[\'"\w]/i', $whereClause, $columnMatches)) {
                $columns = array_unique($columnMatches[1]);
                $columns = array_map(function($col) {
                    return trim($col, '`');
                }, $columns);
            }

            return $columns ? implode(',', $columns) : null;
        }

        return null;
    }

    /**
     * Get MySQL system variables
     */
    private function getMySQLVariables(): array
    {
        $variables = [];
        try {
            $stmt = $this->pdo->query("SHOW VARIABLES");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $variables[$row['Variable_name']] = $row['Value'];
            }
        } catch (PDOException $e) {
            error_log("Failed to get MySQL variables: " . $e->getMessage());
        }
        return $variables;
    }

    /**
     * Get system total memory
     */
    private function getTotalMemory(): int
    {
        if (PHP_OS === 'Linux') {
            $memInfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $memInfo, $matches)) {
                return (int)$matches[1] * 1024; // Convert KB to bytes
            }
        }
        return 1073741824; // Default 1GB
    }

    /**
     * Clear query cache
     */
    public function clearCache(): void
    {
        $this->queryCache = [];
        try {
            $this->pdo->exec("RESET QUERY CACHE");
        } catch (PDOException $e) {
            error_log("Failed to clear query cache: " . $e->getMessage());
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache_size' => count($this->queryCache),
            'health_metrics' => $this->getHealthMetrics(),
            'index_recommendations' => $this->analyzeIndexes(),
            'config_optimizations' => $this->optimizeConfiguration()
        ];
    }
}

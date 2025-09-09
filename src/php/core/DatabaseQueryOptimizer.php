<?php
/**
 * TPT Government Platform - Database Query Optimizer
 *
 * Advanced database query optimization with intelligent caching,
 * query analysis, and performance monitoring
 */

namespace Core;

use PDO;
use PDOException;
use Exception;

class DatabaseQueryOptimizer
{
    /**
     * Database connection
     */
    private PDO $pdo;

    /**
     * Query cache
     */
    private array $queryCache = [];

    /**
     * Query performance metrics
     */
    private array $performanceMetrics = [];

    /**
     * Query analysis results
     */
    private array $queryAnalysis = [];

    /**
     * Optimization configuration
     */
    private array $config;

    /**
     * Constructor
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'enable_query_cache' => true,
            'cache_ttl' => 300, // 5 minutes
            'enable_performance_monitoring' => true,
            'slow_query_threshold' => 0.1, // 100ms
            'enable_query_analysis' => true,
            'max_cache_size' => 1000,
            'enable_index_suggestions' => true,
            'enable_query_rewriting' => true
        ], $config);

        $this->initializeOptimizer();
    }

    /**
     * Initialize the optimizer
     */
    private function initializeOptimizer(): void
    {
        if ($this->config['enable_performance_monitoring']) {
            $this->createPerformanceTables();
        }

        if ($this->config['enable_query_cache']) {
            $this->initializeQueryCache();
        }
    }

    /**
     * Execute optimized query with caching and analysis
     */
    public function executeOptimizedQuery(string $sql, array $params = [], array $options = []): array
    {
        $startTime = microtime(true);
        $queryHash = $this->generateQueryHash($sql, $params);

        // Check cache first
        if ($this->config['enable_query_cache'] && empty($options['skip_cache'])) {
            $cachedResult = $this->getCachedQuery($queryHash);
            if ($cachedResult !== null) {
                $this->recordPerformanceMetric($sql, microtime(true) - $startTime, true);
                return $cachedResult;
            }
        }

        try {
            // Analyze query before execution
            if ($this->config['enable_query_analysis']) {
                $analysis = $this->analyzeQuery($sql, $params);
                $this->queryAnalysis[$queryHash] = $analysis;

                // Apply query optimizations
                if ($this->config['enable_query_rewriting']) {
                    $sql = $this->optimizeQuery($sql, $analysis);
                }
            }

            // Execute query
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $executionTime = microtime(true) - $startTime;

            // Cache result if appropriate
            if ($this->config['enable_query_cache'] &&
                $this->shouldCacheQuery($sql, $result, $options)) {
                $this->cacheQueryResult($queryHash, $result, $options['cache_ttl'] ?? $this->config['cache_ttl']);
            }

            // Record performance metrics
            $this->recordPerformanceMetric($sql, $executionTime, false, $analysis ?? null);

            // Check for slow queries
            if ($executionTime > $this->config['slow_query_threshold']) {
                $this->logSlowQuery($sql, $params, $executionTime);
            }

            return [
                'success' => true,
                'data' => $result,
                'execution_time' => $executionTime,
                'cached' => false,
                'query_hash' => $queryHash
            ];

        } catch (PDOException $e) {
            $executionTime = microtime(true) - $startTime;
            $this->recordPerformanceMetric($sql, $executionTime, false, null, $e->getMessage());

            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute optimized SELECT query
     */
    public function executeOptimizedSelect(string $sql, array $params = [], array $options = []): array
    {
        $result = $this->executeOptimizedQuery($sql, $params, $options);

        if ($result['cached']) {
            return $result['data'];
        }

        return $result['data'];
    }

    /**
     * Execute optimized INSERT/UPDATE/DELETE query
     */
    public function executeOptimizedMutation(string $sql, array $params = [], array $options = []): array
    {
        $startTime = microtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $executionTime = microtime(true) - $startTime;
            $affectedRows = $stmt->rowCount();

            // Invalidate related caches
            if ($this->config['enable_query_cache']) {
                $this->invalidateRelatedCaches($sql, $params);
            }

            $this->recordPerformanceMetric($sql, $executionTime, false);

            return [
                'success' => true,
                'affected_rows' => $affectedRows,
                'execution_time' => $executionTime,
                'last_insert_id' => $this->pdo->lastInsertId()
            ];

        } catch (PDOException $e) {
            $executionTime = microtime(true) - $startTime;
            $this->recordPerformanceMetric($sql, $executionTime, false, null, $e->getMessage());

            throw new Exception('Database mutation failed: ' . $e->getMessage());
        }
    }

    /**
     * Analyze query for optimization opportunities
     */
    public function analyzeQuery(string $sql, array $params = []): array
    {
        $analysis = [
            'query_type' => $this->determineQueryType($sql),
            'tables' => $this->extractTables($sql),
            'columns' => $this->extractColumns($sql),
            'joins' => $this->extractJoins($sql),
            'where_conditions' => $this->extractWhereConditions($sql),
            'order_by' => $this->extractOrderBy($sql),
            'group_by' => $this->extractGroupBy($sql),
            'limit' => $this->extractLimit($sql),
            'has_indexes' => false,
            'missing_indexes' => [],
            'optimization_suggestions' => []
        ];

        // Check for index optimization opportunities
        if ($this->config['enable_index_suggestions']) {
            $analysis['missing_indexes'] = $this->suggestIndexes($sql, $analysis);
        }

        // Generate optimization suggestions
        $analysis['optimization_suggestions'] = $this->generateOptimizationSuggestions($analysis);

        return $analysis;
    }

    /**
     * Optimize query based on analysis
     */
    public function optimizeQuery(string $sql, array $analysis): string
    {
        $optimizedSql = $sql;

        // Add index hints if beneficial
        if (!empty($analysis['missing_indexes'])) {
            $optimizedSql = $this->addIndexHints($optimizedSql, $analysis);
        }

        // Optimize JOIN order
        if (!empty($analysis['joins'])) {
            $optimizedSql = $this->optimizeJoinOrder($optimizedSql, $analysis);
        }

        // Optimize WHERE conditions
        if (!empty($analysis['where_conditions'])) {
            $optimizedSql = $this->optimizeWhereConditions($optimizedSql, $analysis);
        }

        return $optimizedSql;
    }

    /**
     * Get query performance metrics
     */
    public function getPerformanceMetrics(array $filters = []): array
    {
        $metrics = [];

        try {
            $sql = "SELECT * FROM query_performance_metrics WHERE 1=1";
            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND executed_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND executed_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['slow_queries_only'])) {
                $sql .= " AND execution_time > ?";
                $params[] = $this->config['slow_query_threshold'];
            }

            $sql .= " ORDER BY executed_at DESC LIMIT 1000";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Failed to get performance metrics: " . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Get database index recommendations
     */
    public function getIndexRecommendations(): array
    {
        $recommendations = [];

        try {
            // Analyze slow queries for missing indexes
            $sql = "
                SELECT sql_text, execution_time, executed_at
                FROM query_performance_metrics
                WHERE execution_time > ?
                AND executed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY execution_time DESC
                LIMIT 50
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->config['slow_query_threshold']]);
            $slowQueries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($slowQueries as $query) {
                $analysis = $this->analyzeQuery($query['sql_text']);
                if (!empty($analysis['missing_indexes'])) {
                    $recommendations[] = [
                        'query' => $query['sql_text'],
                        'execution_time' => $query['execution_time'],
                        'missing_indexes' => $analysis['missing_indexes'],
                        'impact' => $this->estimateIndexImpact($analysis)
                    ];
                }
            }

        } catch (PDOException $e) {
            error_log("Failed to get index recommendations: " . $e->getMessage());
        }

        return $recommendations;
    }

    /**
     * Create database indexes based on recommendations
     */
    public function createRecommendedIndexes(array $recommendations): array
    {
        $results = [];

        foreach ($recommendations as $recommendation) {
            foreach ($recommendation['missing_indexes'] as $index) {
                try {
                    $sql = "CREATE INDEX {$index['name']} ON {$index['table']} ({$index['columns']})";
                    $this->pdo->exec($sql);

                    $results[] = [
                        'index' => $index['name'],
                        'table' => $index['table'],
                        'status' => 'created',
                        'estimated_impact' => $recommendation['impact']
                    ];

                } catch (PDOException $e) {
                    $results[] = [
                        'index' => $index['name'],
                        'table' => $index['table'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get query cache statistics
     */
    public function getCacheStatistics(): array
    {
        return [
            'cache_size' => count($this->queryCache),
            'max_cache_size' => $this->config['max_cache_size'],
            'cache_hit_ratio' => $this->calculateCacheHitRatio(),
            'memory_usage' => $this->estimateCacheMemoryUsage(),
            'oldest_entry' => $this->getOldestCacheEntry(),
            'newest_entry' => $this->getNewestCacheEntry()
        ];
    }

    /**
     * Clear query cache
     */
    public function clearCache(string $pattern = null): int
    {
        if ($pattern === null) {
            $cleared = count($this->queryCache);
            $this->queryCache = [];
            return $cleared;
        }

        $cleared = 0;
        foreach ($this->queryCache as $key => $value) {
            if (fnmatch($pattern, $key)) {
                unset($this->queryCache[$key]);
                $cleared++;
            }
        }

        return $cleared;
    }

    // Private helper methods

    private function generateQueryHash(string $sql, array $params): string
    {
        return hash('sha256', $sql . serialize($params));
    }

    private function getCachedQuery(string $hash): ?array
    {
        if (!isset($this->queryCache[$hash])) {
            return null;
        }

        $cached = $this->queryCache[$hash];

        // Check if cache is expired
        if (time() > $cached['expires']) {
            unset($this->queryCache[$hash]);
            return null;
        }

        $cached['access_count'] = ($cached['access_count'] ?? 0) + 1;
        $cached['last_accessed'] = time();

        return $cached['result'];
    }

    private function cacheQueryResult(string $hash, array $result, int $ttl): void
    {
        // Clean up expired entries if cache is full
        if (count($this->queryCache) >= $this->config['max_cache_size']) {
            $this->cleanupExpiredCache();
        }

        $this->queryCache[$hash] = [
            'result' => $result,
            'expires' => time() + $ttl,
            'created' => time(),
            'last_accessed' => time(),
            'access_count' => 1,
            'size' => strlen(serialize($result))
        ];
    }

    private function shouldCacheQuery(string $sql, array $result, array $options): bool
    {
        // Don't cache mutations
        if (preg_match('/^(INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)/i', trim($sql))) {
            return false;
        }

        // Don't cache if explicitly disabled
        if (isset($options['no_cache']) && $options['no_cache']) {
            return false;
        }

        // Don't cache large result sets
        if (count($result) > 1000) {
            return false;
        }

        // Don't cache if result contains sensitive data
        if ($this->containsSensitiveData($result)) {
            return false;
        }

        return true;
    }

    private function containsSensitiveData(array $result): bool
    {
        $sensitiveFields = ['password', 'ssn', 'credit_card', 'api_key', 'secret'];

        foreach ($result as $row) {
            if (is_array($row)) {
                foreach ($row as $key => $value) {
                    if (in_array(strtolower($key), $sensitiveFields)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function cleanupExpiredCache(): void
    {
        $currentTime = time();
        foreach ($this->queryCache as $key => $value) {
            if ($currentTime > $value['expires']) {
                unset($this->queryCache[$key]);
            }
        }
    }

    private function invalidateRelatedCaches(string $sql, array $params): void
    {
        // Extract table names from the query
        $tables = $this->extractTables($sql);

        // Invalidate caches that reference these tables
        foreach ($this->queryCache as $key => $value) {
            foreach ($tables as $table) {
                if (strpos($value['query'] ?? '', $table) !== false) {
                    unset($this->queryCache[$key]);
                    break;
                }
            }
        }
    }

    private function determineQueryType(string $sql): string
    {
        $sql = trim($sql);

        if (preg_match('/^SELECT/i', $sql)) return 'SELECT';
        if (preg_match('/^INSERT/i', $sql)) return 'INSERT';
        if (preg_match('/^UPDATE/i', $sql)) return 'UPDATE';
        if (preg_match('/^DELETE/i', $sql)) return 'DELETE';
        if (preg_match('/^(CREATE|ALTER|DROP)/i', $sql)) return 'DDL';

        return 'UNKNOWN';
    }

    private function extractTables(string $sql): array
    {
        $tables = [];

        // Extract from FROM clause
        if (preg_match_all('/FROM\s+([`\w]+)(?:\s+AS\s+\w+)?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Extract from JOIN clauses
        if (preg_match_all('/JOIN\s+([`\w]+)(?:\s+AS\s+\w+)?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Extract from UPDATE/INSERT/DELETE
        if (preg_match('/^(UPDATE|INSERT INTO|DELETE FROM)\s+([`\w]+)/i', $sql, $matches)) {
            $tables[] = $matches[2];
        }

        return array_unique($tables);
    }

    private function extractColumns(string $sql): array
    {
        $columns = [];

        // Extract column names from SELECT
        if (preg_match('/SELECT\s+(.*?)\s+FROM/i', $sql, $matches)) {
            $selectPart = $matches[1];

            // Split by commas, but be careful with functions
            $columnList = preg_split('/,(?![^(]*\))/', $selectPart);

            foreach ($columnList as $column) {
                $column = trim($column);

                // Extract column name (handle aliases)
                if (preg_match('/([`\w]+(?:\.[`\w]+)?)(?:\s+AS\s+\w+)?/i', $column, $colMatch)) {
                    $columns[] = $colMatch[1];
                }
            }
        }

        return array_unique($columns);
    }

    private function extractJoins(string $sql): array
    {
        $joins = [];

        if (preg_match_all('/(LEFT|RIGHT|INNER|OUTER)?\s*JOIN\s+([`\w]+)(?:\s+AS\s+\w+)?\s+ON\s+(.+?)(?=LEFT|RIGHT|INNER|OUTER|WHERE|GROUP|ORDER|LIMIT|$)/i', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $joins[] = [
                    'type' => trim($match[1] ?? 'INNER'),
                    'table' => $match[2],
                    'condition' => trim($match[3])
                ];
            }
        }

        return $joins;
    }

    private function extractWhereConditions(string $sql): array
    {
        $conditions = [];

        if (preg_match('/WHERE\s+(.*?)(?=GROUP|ORDER|LIMIT|$)/i', $sql, $matches)) {
            $whereClause = $matches[1];

            // Split by AND/OR, but be careful with parentheses
            $conditionList = preg_split('/(AND|OR)(?![^(]*\))/i', $whereClause);

            foreach ($conditionList as $condition) {
                $condition = trim($condition);
                if (!empty($condition) && !preg_match('/^(AND|OR)$/i', $condition)) {
                    $conditions[] = $condition;
                }
            }
        }

        return $conditions;
    }

    private function extractOrderBy(string $sql): array
    {
        $orderBy = [];

        if (preg_match('/ORDER BY\s+(.*?)(?=LIMIT|$)/i', $sql, $matches)) {
            $orderClause = $matches[1];
            $orderItems = explode(',', $orderClause);

            foreach ($orderItems as $item) {
                $item = trim($item);
                if (preg_match('/([`\w]+(?:\.[`\w]+)?)\s*(ASC|DESC)?/i', $item, $match)) {
                    $orderBy[] = [
                        'column' => $match[1],
                        'direction' => strtoupper($match[2] ?? 'ASC')
                    ];
                }
            }
        }

        return $orderBy;
    }

    private function extractGroupBy(string $sql): array
    {
        $groupBy = [];

        if (preg_match('/GROUP BY\s+(.*?)(?=ORDER|LIMIT|$)/i', $sql, $matches)) {
            $groupClause = $matches[1];
            $groupItems = explode(',', $groupClause);

            foreach ($groupItems as $item) {
                $item = trim($item);
                $groupBy[] = $item;
            }
        }

        return $groupBy;
    }

    private function extractLimit(string $sql): ?array
    {
        if (preg_match('/LIMIT\s+(\d+)(?:\s*,\s*(\d+))?/i', $sql, $matches)) {
            return [
                'offset' => isset($matches[2]) ? (int)$matches[1] : 0,
                'count' => isset($matches[2]) ? (int)$matches[2] : (int)$matches[1]
            ];
        }

        return null;
    }

    private function suggestIndexes(string $sql, array $analysis): array
    {
        $suggestions = [];

        // Suggest indexes for WHERE conditions
        foreach ($analysis['where_conditions'] as $condition) {
            if (preg_match('/([`\w]+(?:\.[`\w]+)?)\s*[<>=!]+\s*[^<>=!]/', $condition, $match)) {
                $column = $match[1];
                if (strpos($column, '.') !== false) {
                    list($table, $columnName) = explode('.', $column);
                } else {
                    $table = $analysis['tables'][0] ?? 'unknown_table';
                    $columnName = $column;
                }

                $suggestions[] = [
                    'table' => $table,
                    'columns' => $columnName,
                    'name' => "idx_{$table}_{$columnName}",
                    'reason' => 'WHERE condition optimization'
                ];
            }
        }

        // Suggest indexes for JOIN conditions
        foreach ($analysis['joins'] as $join) {
            if (preg_match_all('/([`\w]+(?:\.[`\w]+)?)\s*=\s*([`\w]+(?:\.[`\w]+)?)/', $join['condition'], $matches)) {
                foreach ($matches[1] as $i => $leftCol) {
                    foreach ($matches[2] as $j => $rightCol) {
                        if ($i === $j) {
                            // Determine which table each column belongs to
                            $leftTable = $this->determineTableForColumn($leftCol, $analysis['tables']);
                            $rightTable = $this->determineTableForColumn($rightCol, $analysis['tables']);

                            if ($leftTable && $rightTable && $leftTable !== $rightTable) {
                                $leftColName = $this->extractColumnName($leftCol);
                                $rightColName = $this->extractColumnName($rightCol);

                                $suggestions[] = [
                                    'table' => $leftTable,
                                    'columns' => $leftColName,
                                    'name' => "idx_{$leftTable}_{$leftColName}",
                                    'reason' => 'JOIN optimization'
                                ];

                                $suggestions[] = [
                                    'table' => $rightTable,
                                    'columns' => $rightColName,
                                    'name' => "idx_{$rightTable}_{$rightColName}",
                                    'reason' => 'JOIN optimization'
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Suggest indexes for ORDER BY
        foreach ($analysis['order_by'] as $orderItem) {
            $table = $this->determineTableForColumn($orderItem['column'], $analysis['tables']);
            if ($table) {
                $columnName = $this->extractColumnName($orderItem['column']);
                $suggestions[] = [
                    'table' => $table,
                    'columns' => $columnName,
                    'name' => "idx_{$table}_{$columnName}",
                    'reason' => 'ORDER BY optimization'
                ];
            }
        }

        return array_unique($suggestions, SORT_REG);
    }

    private function determineTableForColumn(string $column, array $tables): ?string
    {
        if (strpos($column, '.') !== false) {
            list($table, $columnName) = explode('.', $column);
            return in_array($table, $tables) ? $table : null;
        }

        // If no table prefix, assume first table
        return $tables[0] ?? null;
    }

    private function extractColumnName(string $column): string
    {
        if (strpos($column, '.') !== false) {
            return explode('.', $column)[1];
        }
        return $column;
    }

    private function generateOptimizationSuggestions(array $analysis): array
    {
        $suggestions = [];

        // Check for SELECT *
        if ($analysis['query_type'] === 'SELECT' && in_array('*', $analysis['columns'])) {
            $suggestions[] = 'Avoid SELECT * - specify only required columns';
        }

        // Check for missing WHERE clause on large tables
        if ($analysis['query_type'] === 'SELECT' && empty($analysis['where_conditions'])) {
            $suggestions[] = 'Consider adding WHERE conditions to limit result set';
        }

        // Check for too many JOINs
        if (count($analysis['joins']) > 5) {
            $suggestions[] = 'Query has many JOINs - consider denormalization or separate queries';
        }

        // Check for ORDER BY without LIMIT
        if (!empty($analysis['order_by']) && !$analysis['limit']) {
            $suggestions[] = 'ORDER BY without LIMIT may be inefficient for large result sets';
        }

        return $suggestions;
    }

    private function addIndexHints(string $sql, array $analysis): string
    {
        // This is a simplified implementation
        // In practice, you'd need more sophisticated logic
        return $sql;
    }

    private function optimizeJoinOrder(string $sql, array $analysis): string
    {
        // This is a simplified implementation
        // In practice, you'd need more sophisticated logic
        return $sql;
    }

    private function optimizeWhereConditions(string $sql, array $analysis): string
    {
        // This is a simplified implementation
        // In practice, you'd need more sophisticated logic
        return $sql;
    }

    private function estimateIndexImpact(array $analysis): string
    {
        $impact = 'medium';

        if (count($analysis['where_conditions']) > 3) {
            $impact = 'high';
        } elseif (count($analysis['joins']) > 2) {
            $impact = 'high';
        } elseif (!empty($analysis['order_by'])) {
            $impact = 'medium';
        }

        return $impact;
    }

    private function recordPerformanceMetric(string $sql, float $executionTime, bool $cached, ?array $analysis = null, ?string $error = null): void
    {
        if (!$this->config['enable_performance_monitoring']) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO query_performance_metrics
                (query_hash, sql_text, execution_time, cached, analysis_data, error_message, executed_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->generateQueryHash($sql, []),
                substr($sql, 0, 1000), // Truncate long queries
                $executionTime,
                $cached ? 1 : 0,
                $analysis ? json_encode($analysis) : null,
                $error
            ]);

        } catch (PDOException $e) {
            error_log("Failed to record performance metric: " . $e->getMessage());
        }
    }

    private function logSlowQuery(string $sql, array $params, float $executionTime): void
    {
        $logMessage = sprintf(
            "[%s] SLOW QUERY: %s\nParams: %s\nExecution Time: %.4f seconds\n",
            date('Y-m-d H:i:s'),
            $sql,
            json_encode($params),
            $executionTime
        );

        error_log($logMessage);

        // Log to file if configured
        if (defined('LOGS_PATH')) {
            file_put_contents(
                LOGS_PATH . '/slow_queries.log',
                $logMessage,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    private function createPerformanceTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS query_performance_metrics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                query_hash VARCHAR(64) NOT NULL,
                sql_text TEXT NOT NULL,
                execution_time DECIMAL(6,4) NOT NULL,
                cached BOOLEAN DEFAULT FALSE,
                analysis_data JSON,
                error_message TEXT,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_query_hash (query_hash),
                INDEX idx_execution_time (execution_time),
                INDEX idx_executed_at (executed_at),
                INDEX idx_cached (cached)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS index_recommendations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL,
                column_name VARCHAR(100) NOT NULL,
                index_name VARCHAR(100) NOT NULL,
                recommendation_reason TEXT,
                estimated_impact ENUM('low', 'medium', 'high') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                implemented BOOLEAN DEFAULT FALSE,
                implemented_at TIMESTAMP NULL,
                INDEX idx_table_name (table_name),
                INDEX idx_implemented (implemented)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create performance tables: " . $e->getMessage());
        }
    }

    private function initializeQueryCache(): void
    {
        // Initialize cache cleanup routine
        if (function_exists('pcntl_fork')) {
            // In production, you'd want a more robust cache cleanup mechanism
            // For now, we'll clean up on each request if cache is getting full
        }
    }

    private function calculateCacheHitRatio(): float
    {
        $totalRequests = count($this->performanceMetrics);
        $cacheHits = count(array_filter($this->performanceMetrics, fn($m) => $m['cached']));

        return $totalRequests > 0 ? ($cacheHits / $totalRequests) * 100 : 0;
    }

    private function estimateCacheMemoryUsage(): int
    {
        $totalSize = 0;
        foreach ($this->queryCache as $entry) {
            $totalSize += $entry['size'] ?? 0;
        }
        return $totalSize;
    }

    private function getOldestCacheEntry(): ?int
    {
        $oldest = null;
        foreach ($this->queryCache as $entry) {
            if ($oldest === null || $entry['created'] < $oldest) {
                $oldest = $entry['created'];
            }
        }
        return $oldest;
    }

    private function getNewestCacheEntry(): ?int
    {
        $newest = null;
        foreach ($this->queryCache as $entry) {
            if ($newest === null || $entry['last_accessed'] > $newest) {
                $newest = $entry['last_accessed'];
            }
        }
        return $newest;
    }
}

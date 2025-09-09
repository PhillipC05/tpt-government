<?php
/**
 * TPT Government Platform - Optimized Database Handler
 *
 * High-performance database abstraction layer with connection pooling,
 * prepared statement caching, and query result caching.
 */

namespace Core;

use Core\Interfaces\CacheInterface;

class DatabaseOptimized extends Database
{
    /**
     * Connection pool
     */
    private array $connectionPool = [];

    /**
     * Active connections
     */
    private array $activeConnections = [];

    /**
     * Prepared statement cache
     */
    private array $preparedStatements = [];

    /**
     * Query result cache
     */
    private ?CacheInterface $queryCache = null;

    /**
     * Query cache TTL
     */
    private int $queryCacheTtl = 300; // 5 minutes

    /**
     * Connection pool size
     */
    private int $poolSize = 10;

    /**
     * Connection timeout
     */
    private int $connectionTimeout = 30;

    /**
     * Query cache enabled
     */
    private bool $queryCachingEnabled = true;

    /**
     * Query cache prefix
     */
    private string $queryCachePrefix = 'db:query:';

    /**
     * Performance statistics
     */
    private array $stats = [
        'connections_created' => 0,
        'connections_reused' => 0,
        'queries_executed' => 0,
        'queries_cached' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];

    /**
     * Constructor
     *
     * @param array $config Database configuration
     * @param CacheInterface|null $queryCache Query cache instance
     */
    public function __construct(array $config, ?CacheInterface $queryCache = null)
    {
        parent::__construct($config);
        $this->queryCache = $queryCache;

        // Override pool size from config
        if (isset($config['pool_size'])) {
            $this->poolSize = (int) $config['pool_size'];
        }

        // Override cache TTL from config
        if (isset($config['query_cache_ttl'])) {
            $this->queryCacheTtl = (int) $config['query_cache_ttl'];
        }

        // Initialize connection pool
        $this->initializeConnectionPool();
    }

    /**
     * Initialize connection pool
     *
     * @return void
     */
    private function initializeConnectionPool(): void
    {
        // Pre-create some connections
        $initialConnections = min(3, $this->poolSize);
        for ($i = 0; $i < $initialConnections; $i++) {
            $this->createPooledConnection();
        }
    }

    /**
     * Create a new pooled connection
     *
     * @return \PDO
     */
    private function createPooledConnection(): \PDO
    {
        $connectionId = uniqid('db_conn_', true);

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
        ], $this->config['options'] ?? []);

        $pdo = new \PDO($dsn, $this->config['username'], $this->config['password'], $options);

        // Set charset
        $pdo->exec("SET NAMES '{$this->config['charset']}'");

        $this->connectionPool[$connectionId] = [
            'pdo' => $pdo,
            'created_at' => time(),
            'last_used' => time(),
            'in_use' => false
        ];

        $this->stats['connections_created']++;

        return $pdo;
    }

    /**
     * Get a connection from the pool
     *
     * @return \PDO
     */
    private function getPooledConnection(): \PDO
    {
        // First, try to find an available connection
        foreach ($this->connectionPool as $connectionId => &$connection) {
            if (!$connection['in_use']) {
                $connection['in_use'] = true;
                $connection['last_used'] = time();
                $this->activeConnections[$connectionId] = &$connection;
                $this->stats['connections_reused']++;
                return $connection['pdo'];
            }
        }

        // If no available connection and pool not full, create new one
        if (count($this->connectionPool) < $this->poolSize) {
            $pdo = $this->createPooledConnection();
            $connectionId = array_key_last($this->connectionPool);
            $this->connectionPool[$connectionId]['in_use'] = true;
            $this->activeConnections[$connectionId] = &$this->connectionPool[$connectionId];
            return $pdo;
        }

        // Wait for a connection to become available
        return $this->waitForAvailableConnection();
    }

    /**
     * Wait for an available connection
     *
     * @return \PDO
     */
    private function waitForAvailableConnection(): \PDO
    {
        $startTime = time();

        while (time() - $startTime < $this->connectionTimeout) {
            foreach ($this->connectionPool as $connectionId => &$connection) {
                if (!$connection['in_use']) {
                    $connection['in_use'] = true;
                    $connection['last_used'] = time();
                    $this->activeConnections[$connectionId] = &$connection;
                    $this->stats['connections_reused']++;
                    return $connection['pdo'];
                }
            }
            usleep(10000); // Wait 10ms before checking again
        }

        throw new \Exception('Connection pool timeout: no available connections');
    }

    /**
     * Release a connection back to the pool
     *
     * @param \PDO $pdo
     * @return void
     */
    private function releaseConnection(\PDO $pdo): void
    {
        foreach ($this->activeConnections as $connectionId => &$connection) {
            if ($connection['pdo'] === $pdo) {
                $connection['in_use'] = false;
                $connection['last_used'] = time();
                unset($this->activeConnections[$connectionId]);
                break;
            }
        }
    }

    /**
     * Execute a query with optimizations
     *
     * @param string $query The SQL query
     * @param array $params The query parameters
     * @return \PDOStatement The PDO statement
     * @throws \Exception If query fails
     */
    public function query(string $query, array $params = []): \PDOStatement
    {
        $this->stats['queries_executed']++;

        // Check query cache for SELECT queries
        if ($this->queryCachingEnabled && $this->isSelectQuery($query) && $this->queryCache) {
            $cacheKey = $this->getQueryCacheKey($query, $params);
            $cachedResult = $this->queryCache->get($cacheKey);

            if ($cachedResult !== null) {
                $this->stats['cache_hits']++;
                // Return cached result as PDOStatement-like object
                return $this->createCachedStatement($cachedResult);
            }

            $this->stats['cache_misses']++;
        }

        // Get connection from pool
        $pdo = $this->getPooledConnection();

        try {
            $startTime = microtime(true);

            // Use prepared statement caching
            $stmt = $this->getPreparedStatement($pdo, $query);
            $stmt->execute($params);

            $this->lastQuery = $query;
            $this->queryTime = microtime(true) - $startTime;

            // Cache result for SELECT queries
            if ($this->queryCachingEnabled && $this->isSelectQuery($query) && $this->queryCache) {
                $result = $stmt->fetchAll();
                $cacheKey = $this->getQueryCacheKey($query, $params);
                $this->queryCache->set($cacheKey, $result, $this->queryCacheTtl);
                $this->stats['queries_cached']++;

                // Return fresh statement with cached data
                return $this->createCachedStatement($result);
            }

            // Wrap statement to handle connection release
            return new OptimizedStatement($stmt, function() use ($pdo) {
                $this->releaseConnection($pdo);
            });

        } catch (\PDOException $e) {
            $this->releaseConnection($pdo);
            $this->logError($query, $params, $e);
            throw new \Exception('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Get cached prepared statement or create new one
     *
     * @param \PDO $pdo
     * @param string $query
     * @return \PDOStatement
     */
    private function getPreparedStatement(\PDO $pdo, string $query): \PDOStatement
    {
        $queryHash = md5($query);

        if (!isset($this->preparedStatements[$queryHash])) {
            $this->preparedStatements[$queryHash] = $pdo->prepare($query);
        }

        return $this->preparedStatements[$queryHash];
    }

    /**
     * Check if query is a SELECT query
     *
     * @param string $query
     * @return bool
     */
    private function isSelectQuery(string $query): bool
    {
        return stripos(trim($query), 'SELECT') === 0;
    }

    /**
     * Get query cache key
     *
     * @param string $query
     * @param array $params
     * @return string
     */
    private function getQueryCacheKey(string $query, array $params): string
    {
        return $this->queryCachePrefix . md5($query . serialize($params));
    }

    /**
     * Create cached statement
     *
     * @param array $data
     * @return CachedStatement
     */
    private function createCachedStatement(array $data): CachedStatement
    {
        return new CachedStatement($data);
    }

    /**
     * Execute SELECT query with caching
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
     * Execute SELECT query returning first result with caching
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
     * Get database statistics
     *
     * @return array The database statistics
     */
    public function getStats(): array
    {
        $poolStats = [
            'pool_size' => count($this->connectionPool),
            'active_connections' => count($this->activeConnections),
            'available_connections' => count($this->connectionPool) - count($this->activeConnections)
        ];

        return array_merge(parent::getStats(), $this->stats, $poolStats, [
            'query_caching_enabled' => $this->queryCachingEnabled,
            'query_cache_ttl' => $this->queryCacheTtl,
            'connection_timeout' => $this->connectionTimeout
        ]);
    }

    /**
     * Clear query cache
     *
     * @return void
     */
    public function clearQueryCache(): void
    {
        if ($this->queryCache) {
            $this->queryCache->clear($this->queryCachePrefix . '*');
        }
    }

    /**
     * Set query caching enabled
     *
     * @param bool $enabled
     * @return self
     */
    public function setQueryCachingEnabled(bool $enabled): self
    {
        $this->queryCachingEnabled = $enabled;
        return $this;
    }

    /**
     * Set query cache TTL
     *
     * @param int $ttl
     * @return self
     */
    public function setQueryCacheTtl(int $ttl): self
    {
        $this->queryCacheTtl = $ttl;
        return $this;
    }

    /**
     * Set query cache instance
     *
     * @param CacheInterface $cache
     * @return self
     */
    public function setQueryCache(CacheInterface $cache): self
    {
        $this->queryCache = $cache;
        return $this;
    }

    /**
     * Clean up old connections
     *
     * @return void
     */
    public function cleanupConnections(): void
    {
        $now = time();
        $maxIdleTime = 300; // 5 minutes

        foreach ($this->connectionPool as $connectionId => $connection) {
            if (!$connection['in_use'] && ($now - $connection['last_used']) > $maxIdleTime) {
                unset($this->connectionPool[$connectionId]);
            }
        }
    }
}

/**
 * Optimized PDO Statement wrapper
 */
class OptimizedStatement implements \Iterator
{
    private \PDOStatement $statement;
    private ?\Closure $releaseCallback;
    private bool $executed = false;

    public function __construct(\PDOStatement $statement, ?\Closure $releaseCallback = null)
    {
        $this->statement = $statement;
        $this->releaseCallback = $releaseCallback;
    }

    public function execute(?array $params = null): bool
    {
        $this->executed = true;
        return $this->statement->execute($params);
    }

    public function fetch(int $mode = \PDO::FETCH_ASSOC, ...$args)
    {
        return $this->statement->fetch($mode, ...$args);
    }

    public function fetchAll(int $mode = \PDO::FETCH_ASSOC, ...$args): array
    {
        return $this->statement->fetchAll($mode, ...$args);
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function __destruct()
    {
        if ($this->releaseCallback) {
            ($this->releaseCallback)();
        }
    }

    // Iterator interface implementation
    public function current()
    {
        return $this->statement->current();
    }

    public function key()
    {
        return $this->statement->key();
    }

    public function next(): void
    {
        $this->statement->next();
    }

    public function rewind(): void
    {
        $this->statement->rewind();
    }

    public function valid(): bool
    {
        return $this->statement->valid();
    }
}

/**
 * Cached Statement for query results
 */
class CachedStatement implements \Iterator
{
    private array $data;
    private int $position = 0;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function execute(?array $params = null): bool
    {
        return true; // Always successful for cached results
    }

    public function fetch(int $mode = \PDO::FETCH_ASSOC, ...$args)
    {
        if ($this->position < count($this->data)) {
            return $this->data[$this->position++];
        }
        return false;
    }

    public function fetchAll(int $mode = \PDO::FETCH_ASSOC, ...$args): array
    {
        return $this->data;
    }

    public function rowCount(): int
    {
        return count($this->data);
    }

    // Iterator interface implementation
    public function current()
    {
        return $this->data[$this->position] ?? null;
    }

    public function key()
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }
}

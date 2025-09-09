<?php
/**
 * TPT Government Platform - Cache Warming Strategies
 *
 * Implements various strategies for warming cache with frequently accessed data
 * to improve application performance and reduce initial load times
 */

abstract class CacheWarmingStrategy
{
    protected $cache;
    protected $dataSources = [];
    protected $warmingStats = [
        'items_warmed' => 0,
        'time_taken' => 0,
        'memory_used' => 0,
        'errors' => 0
    ];

    public function __construct($cache)
    {
        $this->cache = $cache;
    }

    /**
     * Execute cache warming
     */
    abstract public function warm();

    /**
     * Get strategy name
     */
    abstract public function getName();

    /**
     * Add data source for warming
     */
    public function addDataSource($name, callable $dataProvider)
    {
        $this->dataSources[$name] = $dataProvider;
    }

    /**
     * Get warming statistics
     */
    public function getStats()
    {
        return $this->warmingStats;
    }

    /**
     * Reset statistics
     */
    public function resetStats()
    {
        $this->warmingStats = [
            'items_warmed' => 0,
            'time_taken' => 0,
            'memory_used' => 0,
            'errors' => 0
        ];
    }

    /**
     * Measure execution time and memory
     */
    protected function executeWithMetrics(callable $callback)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $result = $callback();
            $this->warmingStats['time_taken'] = microtime(true) - $startTime;
            $this->warmingStats['memory_used'] = memory_get_usage() - $startMemory;
            return $result;
        } catch (Exception $e) {
            $this->warmingStats['errors']++;
            $this->warmingStats['time_taken'] = microtime(true) - $startTime;
            throw $e;
        }
    }
}

/**
 * Static Cache Warming Strategy
 * Warms cache with predefined static data
 */
class StaticCacheWarmingStrategy extends CacheWarmingStrategy
{
    private $staticData = [];

    public function __construct($cache, $staticData = [])
    {
        parent::__construct($cache);
        $this->staticData = $staticData;
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            foreach ($this->staticData as $key => $data) {
                $ttl = isset($data['ttl']) ? $data['ttl'] : 3600;
                $this->cache->set($key, $data['value'], $ttl);
                $this->warmingStats['items_warmed']++;
            }
        });
    }

    public function getName()
    {
        return 'Static Data Warming';
    }

    public function addStaticData($key, $value, $ttl = 3600)
    {
        $this->staticData[$key] = [
            'value' => $value,
            'ttl' => $ttl
        ];
    }
}

/**
 * Database Query Result Warming Strategy
 * Warms cache with results of frequently executed database queries
 */
class DatabaseQueryWarmingStrategy extends CacheWarmingStrategy
{
    private $queries = [];
    private $db;

    public function __construct($cache, $dbConnection, $queries = [])
    {
        parent::__construct($cache);
        $this->db = $dbConnection;
        $this->queries = $queries;
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            foreach ($this->queries as $cacheKey => $queryConfig) {
                try {
                    $stmt = $this->db->prepare($queryConfig['sql']);
                    $stmt->execute($queryConfig['params'] ?? []);

                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $ttl = $queryConfig['ttl'] ?? 1800; // 30 minutes default

                    $this->cache->set($cacheKey, $result, $ttl);
                    $this->warmingStats['items_warmed']++;
                } catch (Exception $e) {
                    $this->warmingStats['errors']++;
                    // Log error but continue with other queries
                    error_log("Cache warming query failed for key {$cacheKey}: " . $e->getMessage());
                }
            }
        });
    }

    public function getName()
    {
        return 'Database Query Warming';
    }

    public function addQuery($cacheKey, $sql, $params = [], $ttl = 1800)
    {
        $this->queries[$cacheKey] = [
            'sql' => $sql,
            'params' => $params,
            'ttl' => $ttl
        ];
    }
}

/**
 * File-based Warming Strategy
 * Warms cache with data from files (JSON, PHP arrays, etc.)
 */
class FileBasedWarmingStrategy extends CacheWarmingStrategy
{
    private $files = [];

    public function __construct($cache, $files = [])
    {
        parent::__construct($cache);
        $this->files = $files;
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            foreach ($this->files as $cacheKey => $fileConfig) {
                try {
                    if (!file_exists($fileConfig['path'])) {
                        $this->warmingStats['errors']++;
                        continue;
                    }

                    $data = $this->loadFileData($fileConfig['path'], $fileConfig['format'] ?? 'auto');
                    $ttl = $fileConfig['ttl'] ?? 3600;

                    $this->cache->set($cacheKey, $data, $ttl);
                    $this->warmingStats['items_warmed']++;
                } catch (Exception $e) {
                    $this->warmingStats['errors']++;
                    error_log("Cache warming file failed for key {$cacheKey}: " . $e->getMessage());
                }
            }
        });
    }

    public function getName()
    {
        return 'File-based Warming';
    }

    public function addFile($cacheKey, $filePath, $format = 'auto', $ttl = 3600)
    {
        $this->files[$cacheKey] = [
            'path' => $filePath,
            'format' => $format,
            'ttl' => $ttl
        ];
    }

    private function loadFileData($filePath, $format)
    {
        $extension = $format === 'auto' ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : $format;

        switch ($extension) {
            case 'php':
                return require $filePath;
            case 'json':
                return json_decode(file_get_contents($filePath), true);
            case 'yaml':
            case 'yml':
                if (!function_exists('yaml_parse_file')) {
                    throw new Exception("YAML extension not available for file: {$filePath}");
                }
                return yaml_parse_file($filePath);
            case 'csv':
                return $this->parseCSV($filePath);
            case 'xml':
                return $this->parseXML($filePath);
            default:
                return file_get_contents($filePath);
        }
    }

    private function parseCSV($filePath)
    {
        $data = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data[] = array_combine($headers, $row);
            }
            fclose($handle);
        }
        return $data;
    }

    private function parseXML($filePath)
    {
        return simplexml_load_file($filePath);
    }
}

/**
 * API Response Warming Strategy
 * Warms cache with responses from external APIs
 */
class ApiResponseWarmingStrategy extends CacheWarmingStrategy
{
    private $apiCalls = [];
    private $httpClient;

    public function __construct($cache, $httpClient = null, $apiCalls = [])
    {
        parent::__construct($cache);
        $this->httpClient = $httpClient ?: new SharedHttpClientService();
        $this->apiCalls = $apiCalls;
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            foreach ($this->apiCalls as $cacheKey => $apiConfig) {
                try {
                    $response = $this->httpClient->get($apiConfig['url'], $apiConfig['options'] ?? []);

                    if ($response['success']) {
                        $data = $this->processApiResponse($response['response'], $apiConfig['format'] ?? 'json');
                        $ttl = $apiConfig['ttl'] ?? 1800;

                        $this->cache->set($cacheKey, $data, $ttl);
                        $this->warmingStats['items_warmed']++;
                    } else {
                        $this->warmingStats['errors']++;
                    }
                } catch (Exception $e) {
                    $this->warmingStats['errors']++;
                    error_log("Cache warming API call failed for key {$cacheKey}: " . $e->getMessage());
                }
            }
        });
    }

    public function getName()
    {
        return 'API Response Warming';
    }

    public function addApiCall($cacheKey, $url, $options = [], $format = 'json', $ttl = 1800)
    {
        $this->apiCalls[$cacheKey] = [
            'url' => $url,
            'options' => $options,
            'format' => $format,
            'ttl' => $ttl
        ];
    }

    private function processApiResponse($response, $format)
    {
        switch ($format) {
            case 'json':
                return json_decode($response, true);
            case 'xml':
                return simplexml_load_string($response);
            default:
                return $response;
        }
    }
}

/**
 * Access Pattern Based Warming Strategy
 * Analyzes access logs to determine which data to warm
 */
class AccessPatternWarmingStrategy extends CacheWarmingStrategy
{
    private $accessLogPath;
    private $analysisPeriod = 86400; // 24 hours
    private $topItemsCount = 100;

    public function __construct($cache, $accessLogPath = null)
    {
        parent::__construct($cache);
        $this->accessLogPath = $accessLogPath ?: LOG_PATH . '/access.log';
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            $accessPatterns = $this->analyzeAccessPatterns();

            foreach ($accessPatterns as $pattern) {
                // Use data sources to fetch frequently accessed data
                if (isset($this->dataSources[$pattern['source']])) {
                    try {
                        $data = call_user_func($this->dataSources[$pattern['source']], $pattern['params']);
                        $this->cache->set($pattern['cache_key'], $data, $pattern['ttl']);
                        $this->warmingStats['items_warmed']++;
                    } catch (Exception $e) {
                        $this->warmingStats['errors']++;
                    }
                }
            }
        });
    }

    public function getName()
    {
        return 'Access Pattern Based Warming';
    }

    private function analyzeAccessPatterns()
    {
        if (!file_exists($this->accessLogPath)) {
            return [];
        }

        $logs = file($this->accessLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $patterns = [];

        foreach ($logs as $log) {
            $pattern = $this->parseLogEntry($log);
            if ($pattern) {
                $key = $pattern['source'] . ':' . serialize($pattern['params']);
                if (!isset($patterns[$key])) {
                    $patterns[$key] = $pattern;
                    $patterns[$key]['count'] = 0;
                }
                $patterns[$key]['count']++;
            }
        }

        // Sort by access count and return top items
        usort($patterns, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice($patterns, 0, $this->topItemsCount);
    }

    private function parseLogEntry($log)
    {
        // Parse log entry to extract access pattern
        // This would depend on your log format
        // Example implementation:
        if (preg_match('/ACCESS: (\w+):(.+)/', $log, $matches)) {
            return [
                'source' => $matches[1],
                'params' => json_decode($matches[2], true),
                'cache_key' => 'access_' . $matches[1] . '_' . md5($matches[2]),
                'ttl' => 3600
            ];
        }

        return null;
    }
}

/**
 * Predictive Warming Strategy
 * Uses machine learning or statistical models to predict which data will be accessed
 */
class PredictiveWarmingStrategy extends CacheWarmingStrategy
{
    private $model;
    private $historicalData = [];
    private $predictionThreshold = 0.7;

    public function __construct($cache, $model = null)
    {
        parent::__construct($cache);
        $this->model = $model;
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            $predictions = $this->generatePredictions();

            foreach ($predictions as $prediction) {
                if ($prediction['confidence'] >= $this->predictionThreshold) {
                    if (isset($this->dataSources[$prediction['source']])) {
                        try {
                            $data = call_user_func($this->dataSources[$prediction['source']], $prediction['params']);
                            $this->cache->set($prediction['cache_key'], $data, $prediction['ttl']);
                            $this->warmingStats['items_warmed']++;
                        } catch (Exception $e) {
                            $this->warmingStats['errors']++;
                        }
                    }
                }
            }
        });
    }

    public function getName()
    {
        return 'Predictive Warming';
    }

    public function addHistoricalData($data)
    {
        $this->historicalData = array_merge($this->historicalData, $data);
    }

    private function generatePredictions()
    {
        // Simple prediction based on historical patterns
        // In a real implementation, this would use ML models
        $predictions = [];
        $accessCounts = [];

        // Count access patterns
        foreach ($this->historicalData as $access) {
            $key = $access['source'] . ':' . serialize($access['params']);
            $accessCounts[$key] = ($accessCounts[$key] ?? 0) + 1;
        }

        // Generate predictions based on access frequency
        foreach ($accessCounts as $key => $count) {
            list($source, $params) = explode(':', $key, 2);
            $params = unserialize($params);

            $predictions[] = [
                'source' => $source,
                'params' => $params,
                'cache_key' => 'predictive_' . md5($key),
                'confidence' => min($count / 100, 1.0), // Simple confidence calculation
                'ttl' => 1800
            ];
        }

        return $predictions;
    }
}

/**
 * Composite Warming Strategy
 * Combines multiple warming strategies
 */
class CompositeWarmingStrategy extends CacheWarmingStrategy
{
    private $strategies = [];

    public function __construct($cache)
    {
        parent::__construct($cache);
    }

    public function addStrategy(CacheWarmingStrategy $strategy)
    {
        $this->strategies[] = $strategy;
    }

    public function warm()
    {
        return $this->executeWithMetrics(function() {
            foreach ($this->strategies as $strategy) {
                try {
                    $strategy->warm();
                    $stats = $strategy->getStats();
                    $this->warmingStats['items_warmed'] += $stats['items_warmed'];
                    $this->warmingStats['time_taken'] += $stats['time_taken'];
                    $this->warmingStats['memory_used'] += $stats['memory_used'];
                    $this->warmingStats['errors'] += $stats['errors'];
                } catch (Exception $e) {
                    $this->warmingStats['errors']++;
                }
            }
        });
    }

    public function getName()
    {
        return 'Composite Warming';
    }
}

/**
 * Cache Warming Scheduler
 * Manages when and how cache warming is executed
 */
class CacheWarmingScheduler
{
    private $cache;
    private $strategies = [];
    private $schedule = [];
    private $lastRun = [];

    public function __construct($cache)
    {
        $this->cache = $cache;
    }

    public function addStrategy($name, CacheWarmingStrategy $strategy, $schedule = '0 */4 * * *')
    {
        $this->strategies[$name] = $strategy;
        $this->schedule[$name] = $schedule; // Cron expression
        $this->lastRun[$name] = 0;
    }

    public function runScheduledWarming()
    {
        $currentTime = time();

        foreach ($this->strategies as $name => $strategy) {
            if ($this->shouldRun($name, $currentTime)) {
                try {
                    $strategy->warm();
                    $this->lastRun[$name] = $currentTime;
                } catch (Exception $e) {
                    error_log("Scheduled cache warming failed for {$name}: " . $e->getMessage());
                }
            }
        }
    }

    public function runWarming($strategyName)
    {
        if (!isset($this->strategies[$strategyName])) {
            throw new Exception("Strategy {$strategyName} not found");
        }

        return $this->strategies[$strategyName]->warm();
    }

    public function getStats($strategyName = null)
    {
        if ($strategyName) {
            return isset($this->strategies[$strategyName]) ?
                $this->strategies[$strategyName]->getStats() : null;
        }

        $allStats = [];
        foreach ($this->strategies as $name => $strategy) {
            $allStats[$name] = $strategy->getStats();
        }

        return $allStats;
    }

    private function shouldRun($strategyName, $currentTime)
    {
        $schedule = $this->schedule[$strategyName];
        $lastRun = $this->lastRun[$strategyName];

        // Simple cron-like check (in production, use a proper cron parser)
        return ($currentTime - $lastRun) >= 14400; // Run every 4 hours by default
    }
}

/**
 * Cache Warming Factory
 */
class CacheWarmingFactory
{
    public static function createStaticStrategy($cache, $staticData = [])
    {
        return new StaticCacheWarmingStrategy($cache, $staticData);
    }

    public static function createDatabaseStrategy($cache, $db, $queries = [])
    {
        return new DatabaseQueryWarmingStrategy($cache, $db, $queries);
    }

    public static function createFileStrategy($cache, $files = [])
    {
        return new FileBasedWarmingStrategy($cache, $files);
    }

    public static function createApiStrategy($cache, $httpClient = null, $apiCalls = [])
    {
        return new ApiResponseWarmingStrategy($cache, $httpClient, $apiCalls);
    }

    public static function createAccessPatternStrategy($cache, $logPath = null)
    {
        return new AccessPatternWarmingStrategy($cache, $logPath);
    }

    public static function createPredictiveStrategy($cache, $model = null)
    {
        return new PredictiveWarmingStrategy($cache, $model);
    }

    public static function createCompositeStrategy($cache)
    {
        return new CompositeWarmingStrategy($cache);
    }
}

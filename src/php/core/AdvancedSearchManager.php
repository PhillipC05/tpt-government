<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Advanced Search Manager
 *
 * This class provides comprehensive advanced search and filtering capabilities including:
 * - Full-text search across multiple data sources
 * - Faceted search and filtering
 * - Search result ranking and relevance scoring
 * - Search analytics and optimization
 * - Search suggestions and auto-complete
 * - Advanced query parsing and processing
 * - Search result caching and performance optimization
 * - Multi-language search support
 * - Search result export and sharing
 */
class AdvancedSearchManager
{
    private PDO $pdo;
    private array $config;
    private AIService $aiService;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->aiService = new AIService($pdo);
        $this->config = array_merge([
            'search_enabled' => true,
            'full_text_search' => true,
            'faceted_search' => true,
            'search_suggestions' => true,
            'search_analytics' => true,
            'search_cache_enabled' => true,
            'search_cache_ttl' => 3600,
            'max_search_results' => 1000,
            'default_page_size' => 20,
            'search_timeout' => 30,
            'min_search_term_length' => 2,
            'max_search_term_length' => 100,
            'supported_search_types' => ['documents', 'users', 'content', 'transactions', 'logs'],
            'search_relevance_weighting' => true,
            'search_boost_factors' => [
                'title_match' => 3.0,
                'exact_match' => 2.5,
                'partial_match' => 1.0,
                'recent_content' => 1.2,
                'user_generated' => 1.1
            ],
            'searchable_fields' => [
                'documents' => ['title', 'content', 'description', 'tags', 'metadata'],
                'users' => ['name', 'email', 'profile', 'bio', 'skills'],
                'content' => ['title', 'body', 'excerpt', 'categories', 'tags'],
                'transactions' => ['description', 'reference', 'amount', 'status'],
                'logs' => ['message', 'level', 'source', 'user_id']
            ]
        ], $config);

        $this->createSearchTables();
    }

    /**
     * Create advanced search tables
     */
    private function createSearchTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS search_queries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                query_id VARCHAR(255) NOT NULL UNIQUE,
                search_term TEXT NOT NULL,
                search_filters JSON DEFAULT NULL,
                search_type VARCHAR(50) DEFAULT 'general',
                user_id VARCHAR(255) DEFAULT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                result_count INT DEFAULT 0,
                search_time DECIMAL(6,3) DEFAULT NULL,
                is_successful BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_query (query_id),
                INDEX idx_user (user_id),
                INDEX idx_type (search_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS search_results (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                result_id VARCHAR(255) NOT NULL UNIQUE,
                query_id VARCHAR(255) NOT NULL,
                result_type VARCHAR(50) NOT NULL,
                result_data JSON NOT NULL,
                relevance_score DECIMAL(5,4) DEFAULT 0,
                result_position INT DEFAULT 0,
                is_clicked BOOLEAN DEFAULT false,
                click_timestamp TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (query_id) REFERENCES search_queries(query_id) ON DELETE CASCADE,
                INDEX idx_result (result_id),
                INDEX idx_query (query_id),
                INDEX idx_type (result_type),
                INDEX idx_score (relevance_score),
                INDEX idx_clicked (is_clicked)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS search_suggestions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                suggestion_id VARCHAR(255) NOT NULL UNIQUE,
                suggestion_text VARCHAR(255) NOT NULL,
                suggestion_type ENUM('autocomplete', 'related', 'popular', 'trending') DEFAULT 'autocomplete',
                search_term VARCHAR(255) DEFAULT NULL,
                usage_count INT DEFAULT 0,
                last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT true,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_suggestion (suggestion_id),
                INDEX idx_text (suggestion_text),
                INDEX idx_type (suggestion_type),
                INDEX idx_term (search_term),
                INDEX idx_active (is_active),
                INDEX idx_usage (usage_count)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS search_filters (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filter_id VARCHAR(255) NOT NULL UNIQUE,
                filter_name VARCHAR(100) NOT NULL,
                filter_type ENUM('category', 'date_range', 'numeric_range', 'boolean', 'multiselect') NOT NULL,
                filter_field VARCHAR(100) NOT NULL,
                filter_options JSON DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_filter (filter_id),
                INDEX idx_name (filter_name),
                INDEX idx_field (filter_field),
                INDEX idx_active (is_active),
                INDEX idx_sort (sort_order)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS search_analytics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                analytic_id VARCHAR(255) NOT NULL UNIQUE,
                metric_type VARCHAR(50) NOT NULL,
                metric_value DECIMAL(10,2) NOT NULL,
                metric_context JSON DEFAULT NULL,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_analytic (analytic_id),
                INDEX idx_type (metric_type),
                INDEX idx_recorded (recorded_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS search_cache (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) NOT NULL UNIQUE,
                search_query TEXT NOT NULL,
                search_filters JSON DEFAULT NULL,
                search_results JSON NOT NULL,
                result_count INT DEFAULT 0,
                cache_hits INT DEFAULT 0,
                last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cache (cache_key),
                INDEX idx_accessed (last_accessed),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS search_indices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                index_id VARCHAR(255) NOT NULL UNIQUE,
                index_name VARCHAR(100) NOT NULL,
                index_type VARCHAR(50) NOT NULL,
                index_fields JSON NOT NULL,
                index_status ENUM('building', 'ready', 'updating', 'failed') DEFAULT 'building',
                document_count INT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_index (index_id),
                INDEX idx_name (index_name),
                INDEX idx_type (index_type),
                INDEX idx_status (index_status)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create search tables: " . $e->getMessage());
        }
    }

    /**
     * Perform advanced search
     */
    public function performSearch(string $searchTerm, array $filters = [], array $options = []): array
    {
        try {
            $startTime = microtime(true);

            // Validate search term
            if (!$this->validateSearchTerm($searchTerm)) {
                return [
                    'success' => false,
                    'error' => 'Invalid search term'
                ];
            }

            $options = array_merge([
                'search_type' => 'general',
                'page' => 1,
                'page_size' => $this->config['default_page_size'],
                'sort_by' => 'relevance',
                'sort_order' => 'desc',
                'include_facets' => true,
                'user_id' => null,
                'session_id' => null,
                'ip_address' => null,
                'user_agent' => null
            ], $options);

            // Check cache first
            if ($this->config['search_cache_enabled']) {
                $cachedResult = $this->getCachedSearch($searchTerm, $filters, $options);
                if ($cachedResult) {
                    $this->logSearchQuery($searchTerm, $filters, $options, $cachedResult, microtime(true) - $startTime, true);
                    return $cachedResult;
                }
            }

            // Parse search query
            $parsedQuery = $this->parseSearchQuery($searchTerm);

            // Build search query
            $searchResults = $this->executeSearchQuery($parsedQuery, $filters, $options);

            // Apply relevance scoring
            $scoredResults = $this->applyRelevanceScoring($searchResults, $parsedQuery);

            // Sort results
            $sortedResults = $this->sortSearchResults($scoredResults, $options['sort_by'], $options['sort_order']);

            // Paginate results
            $paginatedResults = $this->paginateResults($sortedResults, $options['page'], $options['page_size']);

            // Generate facets if requested
            $facets = [];
            if ($options['include_facets']) {
                $facets = $this->generateSearchFacets($searchResults, $filters);
            }

            // Generate suggestions
            $suggestions = $this->generateSearchSuggestions($searchTerm, $searchResults);

            $result = [
                'success' => true,
                'query' => $searchTerm,
                'parsed_query' => $parsedQuery,
                'total_results' => count($sortedResults),
                'results' => $paginatedResults,
                'facets' => $facets,
                'suggestions' => $suggestions,
                'page' => $options['page'],
                'page_size' => $options['page_size'],
                'total_pages' => ceil(count($sortedResults) / $options['page_size']),
                'search_time' => microtime(true) - $startTime
            ];

            // Cache results
            if ($this->config['search_cache_enabled']) {
                $this->cacheSearchResults($searchTerm, $filters, $options, $result);
            }

            // Log search query
            $this->logSearchQuery($searchTerm, $filters, $options, $result, $result['search_time'], false);

            return $result;

        } catch (Exception $e) {
            error_log("Search failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Perform faceted search
     */
    public function performFacetedSearch(array $facets, array $options = []): array
    {
        try {
            $options = array_merge([
                'search_type' => 'faceted',
                'page' => 1,
                'page_size' => $this->config['default_page_size'],
                'sort_by' => 'relevance',
                'sort_order' => 'desc'
            ], $options);

            // Build facet query
            $facetQuery = $this->buildFacetQuery($facets);

            // Execute faceted search
            $searchResults = $this->executeFacetQuery($facetQuery, $options);

            // Apply scoring and sorting
            $scoredResults = $this->applyRelevanceScoring($searchResults, []);
            $sortedResults = $this->sortSearchResults($scoredResults, $options['sort_by'], $options['sort_order']);
            $paginatedResults = $this->paginateResults($sortedResults, $options['page'], $options['page_size']);

            return [
                'success' => true,
                'facets' => $facets,
                'total_results' => count($sortedResults),
                'results' => $paginatedResults,
                'page' => $options['page'],
                'page_size' => $options['page_size'],
                'total_pages' => ceil(count($sortedResults) / $options['page_size'])
            ];

        } catch (Exception $e) {
            error_log("Faceted search failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Faceted search failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(string $partialTerm, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT suggestion_text, suggestion_type, usage_count
                FROM search_suggestions
                WHERE suggestion_text LIKE ? AND is_active = true
                ORDER BY usage_count DESC, last_used DESC
                LIMIT ?
            ");

            $stmt->execute(["{$partialTerm}%", $limit]);
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'suggestions' => $suggestions
            ];

        } catch (PDOException $e) {
            error_log("Failed to get search suggestions: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve suggestions'
            ];
        }
    }

    /**
     * Add search filter
     */
    public function addSearchFilter(array $filterData): array
    {
        try {
            $filterId = $this->generateFilterId();

            $stmt = $this->pdo->prepare("
                INSERT INTO search_filters
                (filter_id, filter_name, filter_type, filter_field, filter_options, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $filterId,
                $filterData['name'],
                $filterData['type'],
                $filterData['field'],
                isset($filterData['options']) ? json_encode($filterData['options']) : null,
                $filterData['sort_order'] ?? 0
            ]);

            return [
                'success' => true,
                'filter_id' => $filterId,
                'message' => 'Search filter added successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to add search filter: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to add search filter'
            ];
        }
    }

    /**
     * Get available search filters
     */
    public function getSearchFilters(string $searchType = null): array
    {
        try {
            $query = "
                SELECT * FROM search_filters
                WHERE is_active = true
            ";

            $params = [];
            if ($searchType) {
                $query .= " AND filter_field IN (
                    SELECT JSON_UNQUOTE(JSON_EXTRACT(value, '$'))
                    FROM JSON_TABLE((SELECT JSON_EXTRACT(?, '$')), '$[*]' COLUMNS (value VARCHAR(100) PATH '$')) as jt
                )";
                $params[] = json_encode($this->config['searchable_fields'][$searchType] ?? []);
            }

            $query .= " ORDER BY sort_order ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            return [
                'success' => true,
                'filters' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];

        } catch (PDOException $e) {
            error_log("Failed to get search filters: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve search filters'
            ];
        }
    }

    /**
     * Track search result click
     */
    public function trackResultClick(string $resultId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE search_results
                SET is_clicked = true, click_timestamp = NOW()
                WHERE result_id = ?
            ");

            $stmt->execute([$resultId]);
            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Failed to track result click: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get search analytics
     */
    public function getSearchAnalytics(array $dateRange = []): array
    {
        try {
            $dateRange = array_merge([
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d')
            ], $dateRange);

            $analytics = [];

            // Query statistics
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_queries,
                    COUNT(CASE WHEN is_successful THEN 1 END) as successful_queries,
                    AVG(result_count) as avg_results_per_query,
                    AVG(search_time) as avg_search_time
                FROM search_queries
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $analytics['queries'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Popular search terms
            $stmt = $this->pdo->prepare("
                SELECT
                    LEFT(search_term, 50) as search_term,
                    COUNT(*) as search_count,
                    AVG(result_count) as avg_results
                FROM search_queries
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY LEFT(search_term, 50)
                ORDER BY search_count DESC
                LIMIT 20
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $analytics['popular_terms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Click-through rates
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_results,
                    COUNT(CASE WHEN is_clicked THEN 1 END) as clicked_results,
                    AVG(relevance_score) as avg_relevance_score
                FROM search_results sr
                JOIN search_queries sq ON sr.query_id = sq.query_id
                WHERE DATE(sr.created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $analytics['click_through'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Search types distribution
            $stmt = $this->pdo->prepare("
                SELECT
                    search_type,
                    COUNT(*) as query_count
                FROM search_queries
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY search_type
                ORDER BY query_count DESC
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $analytics['search_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'analytics' => $analytics,
                'date_range' => $dateRange
            ];

        } catch (PDOException $e) {
            error_log("Failed to get search analytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve search analytics'
            ];
        }
    }

    /**
     * Export search results
     */
    public function exportSearchResults(array $searchResults, string $format = 'json'): array
    {
        try {
            $exportData = [
                'metadata' => [
                    'export_timestamp' => date('Y-m-d H:i:s'),
                    'total_results' => count($searchResults['results'] ?? []),
                    'format' => $format
                ],
                'results' => $searchResults['results'] ?? []
            ];

            switch ($format) {
                case 'json':
                    $content = json_encode($exportData, JSON_PRETTY_PRINT);
                    $filename = 'search_results_' . date('Y-m-d_H-i-s') . '.json';
                    $mimeType = 'application/json';
                    break;

                case 'csv':
                    $content = $this->convertToCSV($exportData['results']);
                    $filename = 'search_results_' . date('Y-m-d_H-i-s') . '.csv';
                    $mimeType = 'text/csv';
                    break;

                case 'xml':
                    $content = $this->convertToXML($exportData);
                    $filename = 'search_results_' . date('Y-m-d_H-i-s') . '.xml';
                    $mimeType = 'application/xml';
                    break;

                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported export format'
                    ];
            }

            return [
                'success' => true,
                'content' => $content,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size' => strlen($content)
            ];

        } catch (Exception $e) {
            error_log("Failed to export search results: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }

    // Private helper methods

    private function validateSearchTerm(string $term): bool
    {
        $length = strlen(trim($term));
        return $length >= $this->config['min_search_term_length'] &&
               $length <= $this->config['max_search_term_length'];
    }

    private function parseSearchQuery(string $query): array
    {
        // Basic query parsing - can be enhanced with more sophisticated parsing
        $parsed = [
            'original' => $query,
            'terms' => explode(' ', trim($query)),
            'operators' => [],
            'phrases' => [],
            'filters' => []
        ];

        // Extract quoted phrases
        preg_match_all('/"([^"]+)"/', $query, $matches);
        if (!empty($matches[1])) {
            $parsed['phrases'] = $matches[1];
        }

        // Extract operators
        $operators = ['AND', 'OR', 'NOT', '+', '-'];
        foreach ($operators as $op) {
            if (stripos($query, $op) !== false) {
                $parsed['operators'][] = $op;
            }
        }

        return $parsed;
    }

    private function executeSearchQuery(array $parsedQuery, array $filters, array $options): array
    {
        // Implementation would execute search against various data sources
        // This is a placeholder
        return [];
    }

    private function applyRelevanceScoring(array $results, array $parsedQuery): array
    {
        foreach ($results as &$result) {
            $score = 0;

            // Title match boost
            if (isset($result['title'])) {
                $score += $this->calculateMatchScore($result['title'], $parsedQuery) * $this->config['search_boost_factors']['title_match'];
            }

            // Content match
            if (isset($result['content'])) {
                $score += $this->calculateMatchScore($result['content'], $parsedQuery) * $this->config['search_boost_factors']['partial_match'];
            }

            // Recency boost
            if (isset($result['created_at'])) {
                $daysOld = (time() - strtotime($result['created_at'])) / (60 * 60 * 24);
                if ($daysOld <= 7) {
                    $score *= $this->config['search_boost_factors']['recent_content'];
                }
            }

            $result['relevance_score'] = min($score, 10.0); // Cap at 10
        }

        return $results;
    }

    private function calculateMatchScore(string $text, array $parsedQuery): float
    {
        $score = 0;
        $text = strtolower($text);

        foreach ($parsedQuery['terms'] as $term) {
            $term = strtolower(trim($term));
            if (strpos($text, $term) !== false) {
                // Exact match gets higher score
                if (strpos($text, $term) === 0 || strpos($text, ' ' . $term) !== false) {
                    $score += $this->config['search_boost_factors']['exact_match'];
                } else {
                    $score += $this->config['search_boost_factors']['partial_match'];
                }
            }
        }

        return $score;
    }

    private function sortSearchResults(array $results, string $sortBy, string $sortOrder): array
    {
        usort($results, function($a, $b) use ($sortBy, $sortOrder) {
            $valueA = $a[$sortBy] ?? 0;
            $valueB = $b[$sortBy] ?? 0;

            if ($sortOrder === 'desc') {
                return $valueB <=> $valueA;
            } else {
                return $valueA <=> $valueB;
            }
        });

        return $results;
    }

    private function paginateResults(array $results, int $page, int $pageSize): array
    {
        $offset = ($page - 1) * $pageSize;
        return array_slice($results, $offset, $pageSize);
    }

    private function generateSearchFacets(array $results, array $appliedFilters): array
    {
        $facets = [];

        // Generate category facets
        $categories = [];
        foreach ($results as $result) {
            if (isset($result['category'])) {
                $categories[$result['category']] = ($categories[$result['category']] ?? 0) + 1;
            }
        }
        if (!empty($categories)) {
            $facets['category'] = $categories;
        }

        // Generate date range facets
        $dateRanges = [
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'year' => 0
        ];

        foreach ($results as $result) {
            if (isset($result['created_at'])) {
                $days = (time() - strtotime($result['created_at'])) / (60 * 60 * 24);
                if ($days <= 1) $dateRanges['today']++;
                if ($days <= 7) $dateRanges['week']++;
                if ($days <= 30) $dateRanges['month']++;
                if ($days <= 365) $dateRanges['year']++;
            }
        }

        $facets['date_range'] = array_filter($dateRanges);

        return $facets;
    }

    private function generateSearchSuggestions(string $searchTerm, array $results): array
    {
        $suggestions = [];

        // Generate related terms based on results
        $relatedTerms = [];
        foreach ($results as $result) {
            if (isset($result['tags'])) {
                $relatedTerms = array_merge($relatedTerms, $result['tags']);
            }
        }

        $relatedTerms = array_unique(array_slice($relatedTerms, 0, 5));
        foreach ($relatedTerms as $term) {
            $suggestions[] = [
                'text' => $term,
                'type' => 'related'
            ];
        }

        return $suggestions;
    }

    private function getCachedSearch(string $searchTerm, array $filters, array $options): ?array
    {
        try {
            $cacheKey = $this->generateCacheKey($searchTerm, $filters, $options);

            $stmt = $this->pdo->prepare("
                SELECT search_results, result_count, cache_hits
                FROM search_cache
                WHERE cache_key = ? AND expires_at > NOW()
            ");

            $stmt->execute([$cacheKey]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cached) {
                // Update cache hits
                $this->pdo->prepare("
                    UPDATE search_cache
                    SET cache_hits = cache_hits + 1, last_accessed = NOW()
                    WHERE cache_key = ?
                ")->execute([$cacheKey]);

                $results = json_decode($cached['search_results'], true);
                $results['cached'] = true;
                $results['cache_hits'] = $cached['cache_hits'] + 1;

                return $results;
            }

            return null;

        } catch (PDOException $e) {
            return null;
        }
    }

    private function cacheSearchResults(string $searchTerm, array $filters, array $options, array $results): void
    {
        try {
            $cacheKey = $this->generateCacheKey($searchTerm, $filters, $options);
            $expiresAt = date('Y-m-d H:i:s', time() + $this->config['search_cache_ttl']);

            $stmt = $this->pdo->prepare("
                INSERT INTO search_cache
                (cache_key, search_query, search_filters, search_results, result_count, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                search_results = VALUES(search_results),
                result_count = VALUES(result_count),
                expires_at = VALUES(expires_at),
                last_accessed = NOW()
            ");

            $stmt->execute([
                $cacheKey,
                $searchTerm,
                json_encode($filters),
                json_encode($results),
                $results['total_results'] ?? 0,
                $expiresAt
            ]);

        } catch (PDOException $e) {
            // Continue without caching
        }
    }

    private function generateCacheKey(string $searchTerm, array $filters, array $options): string
    {
        $keyData = [
            'term' => $searchTerm,
            'filters' => $filters,
            'type' => $options['search_type'] ?? 'general',
            'page' => $options['page'] ?? 1,
            'page_size' => $options['page_size'] ?? $this->config['default_page_size']
        ];

        return 'search_' . md5(json_encode($keyData));
    }

    private function logSearchQuery(string $searchTerm, array $filters, array $options, array $results, float $searchTime, bool $fromCache): void
    {
        try {
            $queryId = $this->generateQueryId();

            $stmt = $this->pdo->prepare("
                INSERT INTO search_queries
                (query_id, search_term, search_filters, search_type, user_id, session_id,
                 ip_address, user_agent, result_count, search_time, is_successful)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $queryId,
                $searchTerm,
                json_encode($filters),
                $options['search_type'] ?? 'general',
                $options['user_id'] ?? null,
                $options['session_id'] ?? null,
                $options['ip_address'] ?? null,
                $options['user_agent'] ?? null,
                $results['total_results'] ?? 0,
                $searchTime,
                $results['success'] ?? true
            ]);

            // Log individual results
            if (isset($results['results']) && is_array($results['results'])) {
                $this->logSearchResults($queryId, $results['results']);
            }

        } catch (PDOException $e) {
            error_log("Failed to log search query: " . $e->getMessage());
        }
    }

    private function logSearchResults(string $queryId, array $results): void
    {
        foreach ($results as $position => $result) {
            try {
                $resultId = $this->generateResultId();

                $stmt = $this->pdo->prepare("
                    INSERT INTO search_results
                    (result_id, query_id, result_type, result_data, relevance_score, result_position)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $resultId,
                    $queryId,
                    $result['type'] ?? 'unknown',
                    json_encode($result),
                    $result['relevance_score'] ?? 0,
                    $position + 1
                ]);

            } catch (PDOException $e) {
                // Continue logging other results
            }
        }
    }

    private function buildFacetQuery(array $facets): array
    {
        // Implementation would build facet-based query
        // This is a placeholder
        return [];
    }

    private function executeFacetQuery(array $facetQuery, array $options): array
    {
        // Implementation would execute facet query
        // This is a placeholder
        return [];
    }

    private function generateQueryId(): string
    {
        return 'query_' . uniqid() . '_' . time();
    }

    private function generateResultId(): string
    {
        return 'result_' . uniqid() . '_' . time();
    }

    private function generateFilterId(): string
    {
        return 'filter_' . uniqid() . '_' . time();
    }

    private function convertToCSV(array $data): string
    {
        if (empty($data)) return '';

        $csv = '';
        $headers = array_keys($data[0]);
        $csv .= implode(',', array_map(function($header) {
            return '"' . str_replace('"', '""', $header) . '"';
        }, $headers)) . "\n";

        foreach ($data as $row) {
            $csv .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', (string)$value) . '"';
            }, $row)) . "\n";
        }

        return $csv;
    }

    private function convertToXML(array $data): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><search_export></search_export>');

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXML($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }

        return $xml->asXML();
    }

    private function arrayToXML(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXML($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}

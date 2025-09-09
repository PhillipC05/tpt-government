<?php
/**
 * TPT Government Platform - Example Job Implementations
 *
 * Sample job classes demonstrating various use cases for the queue system
 */

/**
 * Email Notification Job
 * Sends email notifications asynchronously
 */
class EmailNotificationJob extends BaseJob
{
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->setName('EmailNotification');
        $this->setDescription('Send email notifications to users');
        $this->maxExecutionTime = 60; // 1 minute
        $this->priority = JobPriority::NORMAL;
    }

    public function execute($data = [])
    {
        // Validate required data
        if (!isset($data['to']) || !isset($data['subject']) || !isset($data['message'])) {
            throw new Exception("Missing required email data: to, subject, message");
        }

        $to = $data['to'];
        $subject = $data['subject'];
        $message = $data['message'];
        $headers = $data['headers'] ?? [];

        // Set default headers
        $headers[] = 'From: ' . ($data['from'] ?? 'noreply@tpt-gov.local');
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Send email
        $result = mail($to, $subject, $message, implode("\r\n", $headers));

        if (!$result) {
            throw new Exception("Failed to send email to {$to}");
        }

        return [
            'success' => true,
            'recipient' => $to,
            'subject' => $subject,
            'sent_at' => date('Y-m-d H:i:s')
        ];
    }

    public function validateData($data = [])
    {
        return isset($data['to']) && isset($data['subject']) && isset($data['message']) &&
               filter_var($data['to'], FILTER_VALIDATE_EMAIL);
    }

    public function handleFailure(Exception $exception, $data = [], $attempt = 1)
    {
        // Log failure
        error_log("Email notification failed for {$data['to']}: " . $exception->getMessage());

        // Retry up to 3 times with increasing delay
        return $attempt < 3;
    }
}

/**
 * Data Processing Job
 * Processes large datasets asynchronously
 */
class DataProcessingJob extends BaseJob
{
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->setName('DataProcessing');
        $this->setDescription('Process large datasets and generate reports');
        $this->maxExecutionTime = 300; // 5 minutes
        $this->priority = JobPriority::HIGH;
        $this->canRunConcurrently = false; // Process one at a time
    }

    public function execute($data = [])
    {
        if (!isset($data['dataset']) || !isset($data['operation'])) {
            throw new Exception("Missing required data: dataset, operation");
        }

        $dataset = $data['dataset'];
        $operation = $data['operation'];
        $outputPath = $data['output_path'] ?? null;

        $results = [];

        switch ($operation) {
            case 'aggregate':
                $results = $this->aggregateData($dataset, $data['group_by'] ?? []);
                break;
            case 'filter':
                $results = $this->filterData($dataset, $data['filters'] ?? []);
                break;
            case 'transform':
                $results = $this->transformData($dataset, $data['transformations'] ?? []);
                break;
            case 'export':
                $results = $this->exportData($dataset, $outputPath, $data['format'] ?? 'json');
                break;
            default:
                throw new Exception("Unknown operation: {$operation}");
        }

        // Save results if output path specified
        if ($outputPath) {
            $this->saveResults($results, $outputPath, $data['format'] ?? 'json');
        }

        return [
            'operation' => $operation,
            'records_processed' => count($dataset),
            'results_count' => count($results),
            'output_path' => $outputPath,
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }

    private function aggregateData($dataset, $groupBy)
    {
        $results = [];

        foreach ($dataset as $record) {
            $key = '';
            foreach ($groupBy as $field) {
                $key .= $record[$field] ?? '' . '_';
            }
            $key = rtrim($key, '_');

            if (!isset($results[$key])) {
                $results[$key] = [
                    'count' => 0,
                    'sum' => [],
                    'avg' => []
                ];
                foreach ($groupBy as $field) {
                    $results[$key][$field] = $record[$field] ?? null;
                }
            }

            $results[$key]['count']++;

            // Calculate sums and averages for numeric fields
            foreach ($record as $field => $value) {
                if (is_numeric($value)) {
                    if (!isset($results[$key]['sum'][$field])) {
                        $results[$key]['sum'][$field] = 0;
                    }
                    $results[$key]['sum'][$field] += $value;
                }
            }
        }

        // Calculate averages
        foreach ($results as &$result) {
            foreach ($result['sum'] as $field => $sum) {
                $result['avg'][$field] = $sum / $result['count'];
            }
        }

        return $results;
    }

    private function filterData($dataset, $filters)
    {
        return array_filter($dataset, function($record) use ($filters) {
            foreach ($filters as $field => $condition) {
                $value = $record[$field] ?? null;

                if (isset($condition['equals']) && $value !== $condition['equals']) {
                    return false;
                }

                if (isset($condition['not_equals']) && $value === $condition['not_equals']) {
                    return false;
                }

                if (isset($condition['greater_than']) && $value <= $condition['greater_than']) {
                    return false;
                }

                if (isset($condition['less_than']) && $value >= $condition['less_than']) {
                    return false;
                }

                if (isset($condition['contains']) && strpos($value, $condition['contains']) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    private function transformData($dataset, $transformations)
    {
        $results = [];

        foreach ($dataset as $record) {
            $transformed = $record;

            foreach ($transformations as $field => $transformation) {
                if (isset($record[$field])) {
                    $value = $record[$field];

                    switch ($transformation['type']) {
                        case 'uppercase':
                            $transformed[$field] = strtoupper($value);
                            break;
                        case 'lowercase':
                            $transformed[$field] = strtolower($value);
                            break;
                        case 'multiply':
                            if (is_numeric($value) && isset($transformation['factor'])) {
                                $transformed[$field] = $value * $transformation['factor'];
                            }
                            break;
                        case 'date_format':
                            if (isset($transformation['format'])) {
                                $timestamp = is_numeric($value) ? $value : strtotime($value);
                                $transformed[$field] = date($transformation['format'], $timestamp);
                            }
                            break;
                        case 'concat':
                            if (isset($transformation['fields'])) {
                                $concatValue = '';
                                foreach ($transformation['fields'] as $concatField) {
                                    $concatValue .= ($record[$concatField] ?? '') . ($transformation['separator'] ?? ' ');
                                }
                                $transformed[$field] = rtrim($concatValue, $transformation['separator'] ?? ' ');
                            }
                            break;
                    }
                }
            }

            $results[] = $transformed;
        }

        return $results;
    }

    private function exportData($dataset, $outputPath, $format = 'json')
    {
        $results = [
            'metadata' => [
                'total_records' => count($dataset),
                'exported_at' => date('Y-m-d H:i:s'),
                'format' => $format
            ],
            'data' => $dataset
        ];

        if ($outputPath) {
            $this->saveResults($results, $outputPath, $format);
        }

        return $results;
    }

    private function saveResults($results, $outputPath, $format = 'json')
    {
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        switch ($format) {
            case 'json':
                file_put_contents($outputPath, json_encode($results, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->saveAsCSV($results, $outputPath);
                break;
            case 'xml':
                $this->saveAsXML($results, $outputPath);
                break;
            default:
                file_put_contents($outputPath, serialize($results));
        }
    }

    private function saveAsCSV($results, $outputPath)
    {
        if (!isset($results['data']) || empty($results['data'])) {
            return;
        }

        $data = $results['data'];
        $fp = fopen($outputPath, 'w');

        // Write headers
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
        }

        // Write data
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    private function saveAsXML($results, $outputPath)
    {
        $xml = new SimpleXMLElement('<results/>');

        foreach ($results as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXML($value, $child);
            } else {
                $xml->addChild($key, $value);
            }
        }

        $xml->asXML($outputPath);
    }

    private function arrayToXML($array, &$xml)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild(is_numeric($key) ? 'item' : $key);
                $this->arrayToXML($value, $subnode);
            } else {
                $xml->addChild(is_numeric($key) ? 'item' : $key, $value);
            }
        }
    }

    public function validateData($data = [])
    {
        return isset($data['dataset']) && isset($data['operation']) &&
               is_array($data['dataset']) && in_array($data['operation'],
               ['aggregate', 'filter', 'transform', 'export']);
    }
}

/**
 * API Sync Job
 * Synchronizes data with external APIs
 */
class ApiSyncJob extends BaseJob
{
    private $httpClient;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->setName('ApiSync');
        $this->setDescription('Synchronize data with external APIs');
        $this->maxExecutionTime = 120; // 2 minutes
        $this->priority = JobPriority::HIGH;
        $this->httpClient = new SharedHttpClientService();
    }

    public function execute($data = [])
    {
        if (!isset($data['endpoint']) || !isset($data['method'])) {
            throw new Exception("Missing required data: endpoint, method");
        }

        $endpoint = $data['endpoint'];
        $method = strtoupper($data['method']);
        $payload = $data['payload'] ?? [];
        $headers = $data['headers'] ?? [];

        // Add authentication if provided
        if (isset($data['auth'])) {
            $headers[] = 'Authorization: ' . $data['auth'];
        }

        $options = [
            'headers' => $headers,
            'timeout' => $data['timeout'] ?? 30
        ];

        // Execute API call
        $response = null;
        switch ($method) {
            case 'GET':
                $response = $this->httpClient->get($endpoint, $options);
                break;
            case 'POST':
                $response = $this->httpClient->post($endpoint, $payload, $options);
                break;
            case 'PUT':
                $response = $this->httpClient->put($endpoint, $payload, $options);
                break;
            case 'DELETE':
                $response = $this->httpClient->delete($endpoint, $options);
                break;
            default:
                throw new Exception("Unsupported HTTP method: {$method}");
        }

        if (!$response['success']) {
            throw new Exception("API call failed: " . ($response['error'] ?? 'Unknown error'));
        }

        // Process response
        $result = [
            'endpoint' => $endpoint,
            'method' => $method,
            'http_code' => $response['http_code'],
            'response_size' => strlen($response['response']),
            'synced_at' => date('Y-m-d H:i:s')
        ];

        // Parse response if JSON
        if (isset($response['response'])) {
            $parsedResponse = json_decode($response['response'], true);
            if ($parsedResponse !== null) {
                $result['parsed_response'] = $parsedResponse;
            } else {
                $result['raw_response'] = $response['response'];
            }
        }

        // Store sync result if requested
        if (isset($data['store_result']) && $data['store_result']) {
            $this->storeSyncResult($result, $data['result_key'] ?? null);
        }

        return $result;
    }

    private function storeSyncResult($result, $key = null)
    {
        $cache = new SharedCacheService();
        $cacheKey = $key ?? 'api_sync_' . md5($result['endpoint'] . $result['method']);

        $cache->set($cacheKey, $result, 3600); // Cache for 1 hour
    }

    public function validateData($data = [])
    {
        return isset($data['endpoint']) && isset($data['method']) &&
               filter_var($data['endpoint'], FILTER_VALIDATE_URL) &&
               in_array(strtoupper($data['method']), ['GET', 'POST', 'PUT', 'DELETE']);
    }

    public function handleFailure(Exception $exception, $data = [], $attempt = 1)
    {
        // Log failure
        error_log("API sync failed for {$data['endpoint']}: " . $exception->getMessage());

        // Retry with exponential backoff, up to 5 attempts
        return $attempt < 5;
    }
}

/**
 * Report Generation Job
 * Generates various reports asynchronously
 */
class ReportGenerationJob extends BaseJob
{
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->setName('ReportGeneration');
        $this->setDescription('Generate reports from system data');
        $this->maxExecutionTime = 600; // 10 minutes
        $this->priority = JobPriority::NORMAL;
    }

    public function execute($data = [])
    {
        if (!isset($data['report_type']) || !isset($data['output_path'])) {
            throw new Exception("Missing required data: report_type, output_path");
        }

        $reportType = $data['report_type'];
        $outputPath = $data['output_path'];
        $parameters = $data['parameters'] ?? [];

        $reportData = [];

        switch ($reportType) {
            case 'user_activity':
                $reportData = $this->generateUserActivityReport($parameters);
                break;
            case 'system_performance':
                $reportData = $this->generateSystemPerformanceReport($parameters);
                break;
            case 'financial_summary':
                $reportData = $this->generateFinancialSummaryReport($parameters);
                break;
            case 'audit_trail':
                $reportData = $this->generateAuditTrailReport($parameters);
                break;
            default:
                throw new Exception("Unknown report type: {$reportType}");
        }

        // Generate report file
        $format = $data['format'] ?? 'pdf';
        $filePath = $this->generateReportFile($reportData, $outputPath, $format);

        return [
            'report_type' => $reportType,
            'format' => $format,
            'records_processed' => count($reportData['data'] ?? []),
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'generated_at' => date('Y-m-d H:i:s'),
            'parameters' => $parameters
        ];
    }

    private function generateUserActivityReport($parameters)
    {
        // Simulate user activity data retrieval
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');

        $data = [
            ['date' => '2024-01-01', 'active_users' => 150, 'page_views' => 2500, 'actions' => 1200],
            ['date' => '2024-01-02', 'active_users' => 165, 'page_views' => 2700, 'actions' => 1350],
            // ... more data
        ];

        return [
            'title' => 'User Activity Report',
            'period' => "{$startDate} to {$endDate}",
            'data' => $data,
            'summary' => [
                'total_active_users' => array_sum(array_column($data, 'active_users')),
                'total_page_views' => array_sum(array_column($data, 'page_views')),
                'total_actions' => array_sum(array_column($data, 'actions')),
                'avg_daily_users' => round(array_sum(array_column($data, 'active_users')) / count($data), 2)
            ]
        ];
    }

    private function generateSystemPerformanceReport($parameters)
    {
        // Simulate system performance data
        $data = [
            ['timestamp' => '2024-01-01 10:00:00', 'cpu_usage' => 45.2, 'memory_usage' => 62.8, 'response_time' => 245],
            ['timestamp' => '2024-01-01 11:00:00', 'cpu_usage' => 52.1, 'memory_usage' => 68.3, 'response_time' => 312],
            // ... more data
        ];

        return [
            'title' => 'System Performance Report',
            'data' => $data,
            'summary' => [
                'avg_cpu_usage' => round(array_sum(array_column($data, 'cpu_usage')) / count($data), 2),
                'avg_memory_usage' => round(array_sum(array_column($data, 'memory_usage')) / count($data), 2),
                'avg_response_time' => round(array_sum(array_column($data, 'response_time')) / count($data), 2),
                'peak_cpu_usage' => max(array_column($data, 'cpu_usage')),
                'peak_memory_usage' => max(array_column($data, 'memory_usage'))
            ]
        ];
    }

    private function generateFinancialSummaryReport($parameters)
    {
        // Simulate financial data
        $data = [
            ['month' => 'January', 'revenue' => 50000, 'expenses' => 35000, 'profit' => 15000],
            ['month' => 'February', 'revenue' => 55000, 'expenses' => 38000, 'profit' => 17000],
            // ... more data
        ];

        return [
            'title' => 'Financial Summary Report',
            'data' => $data,
            'summary' => [
                'total_revenue' => array_sum(array_column($data, 'revenue')),
                'total_expenses' => array_sum(array_column($data, 'expenses')),
                'total_profit' => array_sum(array_column($data, 'profit')),
                'profit_margin' => round((array_sum(array_column($data, 'profit')) / array_sum(array_column($data, 'revenue'))) * 100, 2)
            ]
        ];
    }

    private function generateAuditTrailReport($parameters)
    {
        // Simulate audit trail data
        $data = [
            ['timestamp' => '2024-01-01 09:15:00', 'user' => 'admin', 'action' => 'login', 'resource' => 'system'],
            ['timestamp' => '2024-01-01 09:30:00', 'user' => 'user1', 'action' => 'create', 'resource' => 'document'],
            // ... more data
        ];

        return [
            'title' => 'Audit Trail Report',
            'data' => $data,
            'summary' => [
                'total_events' => count($data),
                'unique_users' => count(array_unique(array_column($data, 'user'))),
                'most_common_action' => $this->getMostCommon(array_column($data, 'action')),
                'date_range' => $parameters['date_range'] ?? 'Last 30 days'
            ]
        ];
    }

    private function getMostCommon($array)
    {
        $counts = array_count_values($array);
        arsort($counts);
        return key($counts);
    }

    private function generateReportFile($reportData, $outputPath, $format)
    {
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        switch ($format) {
            case 'pdf':
                return $this->generatePDFReport($reportData, $outputPath);
            case 'excel':
                return $this->generateExcelReport($reportData, $outputPath);
            case 'json':
                file_put_contents($outputPath, json_encode($reportData, JSON_PRETTY_PRINT));
                return $outputPath;
            case 'html':
                return $this->generateHTMLReport($reportData, $outputPath);
            default:
                throw new Exception("Unsupported report format: {$format}");
        }
    }

    private function generatePDFReport($reportData, $outputPath)
    {
        // In a real implementation, use a PDF library like TCPDF or FPDF
        // For now, create a simple text-based PDF-like file
        $content = "Report: {$reportData['title']}\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        if (isset($reportData['summary'])) {
            $content .= "Summary:\n";
            foreach ($reportData['summary'] as $key => $value) {
                $content .= "  {$key}: {$value}\n";
            }
            $content .= "\n";
        }

        if (isset($reportData['data'])) {
            $content .= "Data:\n";
            foreach ($reportData['data'] as $row) {
                $content .= "  " . json_encode($row) . "\n";
            }
        }

        file_put_contents($outputPath, $content);
        return $outputPath;
    }

    private function generateExcelReport($reportData, $outputPath)
    {
        // In a real implementation, use a library like PhpSpreadsheet
        // For now, create a simple CSV file
        $csvPath = str_replace('.xlsx', '.csv', $outputPath);

        if (isset($reportData['data']) && !empty($reportData['data'])) {
            $fp = fopen($csvPath, 'w');

            // Write headers
            fputcsv($fp, array_keys($reportData['data'][0]));

            // Write data
            foreach ($reportData['data'] as $row) {
                fputcsv($fp, $row);
            }

            fclose($fp);
        }

        return $csvPath;
    }

    private function generateHTMLReport($reportData, $outputPath)
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>' . htmlspecialchars($reportData['title']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .summary { background-color: #f9f9f9; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($reportData['title']) . '</h1>
            <p>Generated: ' . date('Y-m-d H:i:s') . '</p>';

        if (isset($reportData['summary'])) {
            $html .= '<div class="summary"><h2>Summary</h2><ul>';
            foreach ($reportData['summary'] as $key => $value) {
                $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
            }
            $html .= '</ul></div>';
        }

        if (isset($reportData['data']) && !empty($reportData['data'])) {
            $html .= '<h2>Data</h2><table><thead><tr>';

            // Headers
            foreach (array_keys($reportData['data'][0]) as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }

            $html .= '</tr></thead><tbody>';

            // Data rows
            foreach ($reportData['data'] as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '</body></html>';

        file_put_contents($outputPath, $html);
        return $outputPath;
    }

    public function validateData($data = [])
    {
        return isset($data['report_type']) && isset($data['output_path']) &&
               in_array($data['report_type'], ['user_activity', 'system_performance', 'financial_summary', 'audit_trail']);
    }
}

/**
 * Database Maintenance Job
 * Performs database maintenance tasks
 */
class DatabaseMaintenanceJob extends BaseJob
{
    private $db;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->setName('DatabaseMaintenance');
        $this->setDescription('Perform database maintenance tasks');
        $this->maxExecutionTime = 1800; // 30 minutes
        $this->priority = JobPriority::LOW;
        $this->canRunConcurrently = false; // Only one maintenance job at a time

        // Initialize database connection
        if (class_exists('Container') && Container::has('database')) {
            $this->db = Container::get('database');
        } else {
            $config = require CONFIG_PATH . '/database.php';
            $this->db = new PDO(
                "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
                $config['username'],
                $config['password'],
                $config['options']
            );
        }
    }

    public function execute($data = [])
    {
        $tasks = $data['tasks'] ?? ['analyze', 'vacuum', 'reindex'];
        $results = [];

        foreach ($tasks as $task) {
            switch ($task) {
                case 'analyze':
                    $results['analyze'] = $this->analyzeTables();
                    break;
                case 'vacuum':
                    $results['vacuum'] = $this->vacuumTables();
                    break;
                case 'reindex':
                    $results['reindex'] = $this->reindexTables();
                    break;
                case 'cleanup':
                    $results['cleanup'] = $this->cleanupOldData();
                    break;
                default:
                    $results[$task] = ['error' => 'Unknown task'];
            }
        }

        return [
            'tasks_executed' => $tasks,
            'results' => $results,
            'maintenance_completed_at' => date('Y-m-d H:i:s')
        ];
    }

    private function analyzeTables()
    {
        try {
            // Get all tables
            $stmt = $this->db->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $analyzed = 0;
            foreach ($tables as $table) {
                $this->db->exec("ANALYZE {$table}");
                $analyzed++;
            }

            return ['tables_analyzed' => $analyzed, 'status' => 'success'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 'failed'];
        }
    }

    private function vacuumTables()
    {
        try {
            // VACUUM all tables
            $stmt = $this->db->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $vacuumed = 0;
            foreach ($tables as $table) {
                $this->db->exec("VACUUM {$table}");
                $vacuumed++;
            }

            return ['tables_vacuumed' => $vacuumed, 'status' => 'success'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 'failed'];
        }
    }

    private function reindexTables()
    {
        try {
            // Get all indexes
            $stmt = $this->db->query("SELECT indexname FROM pg_indexes WHERE schemaname = 'public'");
            $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $reindexed = 0;
            foreach ($indexes as $index) {
                $this->db->exec("REINDEX INDEX {$index}");
                $reindexed++;
            }

            return ['indexes_reindexed' => $reindexed, 'status' => 'success'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 'failed'];
        }
    }

    private function cleanupOldData()
    {
        try {
            $deleted = [];

            // Clean up old job queue entries (older than 30 days)
            $stmt = $this->db->prepare("DELETE FROM job_queue WHERE status IN ('completed', 'failed') AND completed_at < ?");
            $stmt->execute([date('Y-m-d H:i:s', strtotime('-30 days'))]);
            $deleted['old_job_queue_entries'] = $stmt->rowCount();

            // Clean up old audit logs (older than 90 days)
            if ($this->tableExists('audit_log')) {
                $stmt = $this->db->prepare("DELETE FROM audit_log WHERE created_at < ?");
                $stmt->execute([date('Y-m-d H:i:s', strtotime('-90 days'))]);
                $deleted['old_audit_logs'] = $stmt->rowCount();
            }

            // Clean up old cache entries
            if ($this->tableExists('cache')) {
                $stmt = $this->db->prepare("DELETE FROM cache WHERE expires_at < CURRENT_TIMESTAMP");
                $stmt->execute();
                $deleted['expired_cache_entries'] = $stmt->rowCount();
            }

            return ['deleted_records' => $deleted, 'status' => 'success'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'status' => 'failed'];
        }
    }

    private function tableExists($tableName)
    {
        try {
            $stmt = $this->db->prepare("SELECT 1 FROM {$tableName} LIMIT 1");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function validateData($data = [])
    {
        if (!isset($data['tasks'])) {
            return true; // Use default tasks
        }

        $validTasks = ['analyze', 'vacuum', 'reindex', 'cleanup'];
        return is_array($data['tasks']) &&
               empty(array_diff($data['tasks'], $validTasks));
    }

    public function handleFailure(Exception $exception, $data = [], $attempt = 1)
    {
        // Log failure
        error_log("Database maintenance failed: " . $exception->getMessage());

        // Don't retry database maintenance jobs as they might cause issues
        return false;
    }
}

/**
 * File Cleanup Job
 * Cleans up temporary and old files
 */
class FileCleanupJob extends BaseJob
{
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->setName('FileCleanup');
        $this->setDescription('Clean up temporary and old files');
        $this->maxExecutionTime = 300; // 5 minutes
        $this->priority = JobPriority::LOW;
    }

    public function execute($data = [])
    {
        $directories = $data['directories'] ?? [
            CACHE_PATH . '/temp',
            LOG_PATH . '/old',
            UPLOAD_PATH . '/temp'
        ];

        $maxAge = $data['max_age_days'] ?? 7; // 7 days
        $results = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $results[$directory] = ['error' => 'Directory does not exist'];
                continue;
            }

            $results[$directory] = $this->cleanupDirectory($directory, $maxAge);
        }

        return [
            'directories_cleaned' => $directories,
            'max_age_days' => $maxAge,
            'results' => $results,
            'total_files_deleted' => array_sum(array_column($results, 'files_deleted')),
            'total_space_freed' => array_sum(array_column($results, 'space_freed')),
            'cleanup_completed_at' => date('Y-m-d H:i:s')
        ];
    }

    private function cleanupDirectory($directory, $maxAgeDays)
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $deletedFiles = 0;
        $spaceFreed = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file->isFile()) {
                $fileAge = (time() - $file->getMTime()) / (60 * 60 * 24); // Age in days

                if ($fileAge > $maxAgeDays) {
                    $fileSize = $file->getSize();

                    if (unlink($file->getPathname())) {
                        $deletedFiles++;
                        $spaceFreed += $fileSize;
                    } else {
                        $errors[] = "Failed to delete: {$file->getPathname()}";
                    }
                }
            }
        }

        return [
            'files_deleted' => $deletedFiles,
            'space_freed' => $spaceFreed,
            'errors' => $errors,
            'status' => empty($errors) ? 'success' : 'partial'
        ];
    }

    public function validateData($data = [])
    {
        if (isset($data['directories'])) {
            return is_array($data['directories']);
        }

        return true; // Use default directories
    }
}

<?php
/**
 * TPT Government Platform - Shared Service Components
 *
 * Provides common services and utilities that can be shared across modules
 * to reduce code duplication and improve maintainability
 */

class SharedServiceComponents
{
    private static $instance = null;
    private $services = [];
    private $serviceProviders = [];

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->initializeCoreServices();
    }

    /**
     * Initialize core services
     */
    private function initializeCoreServices()
    {
        // Register core service providers
        $this->registerServiceProvider('logger', [$this, 'createLogger']);
        $this->registerServiceProvider('cache', [$this, 'createCache']);
        $this->registerServiceProvider('validator', [$this, 'createValidator']);
        $this->registerServiceProvider('encryptor', [$this, 'createEncryptor']);
        $this->registerServiceProvider('http_client', [$this, 'createHttpClient']);
        $this->registerServiceProvider('file_manager', [$this, 'createFileManager']);
        $this->registerServiceProvider('notification', [$this, 'createNotificationService']);
        $this->registerServiceProvider('audit', [$this, 'createAuditService']);
    }

    /**
     * Register a service provider
     */
    public function registerServiceProvider($serviceName, callable $provider)
    {
        $this->serviceProviders[$serviceName] = $provider;
    }

    /**
     * Get a service instance
     */
    public function getService($serviceName)
    {
        if (!isset($this->services[$serviceName])) {
            if (!isset($this->serviceProviders[$serviceName])) {
                throw new Exception("Service provider not found: {$serviceName}");
            }

            $this->services[$serviceName] = call_user_func($this->serviceProviders[$serviceName]);
        }

        return $this->services[$serviceName];
    }

    /**
     * Check if service exists
     */
    public function hasService($serviceName)
    {
        return isset($this->serviceProviders[$serviceName]);
    }

    /**
     * Remove a service instance (force recreation on next access)
     */
    public function removeService($serviceName)
    {
        unset($this->services[$serviceName]);
    }

    /**
     * Clear all service instances
     */
    public function clearServices()
    {
        $this->services = [];
    }

    // Core service providers

    /**
     * Create logger service
     */
    private function createLogger()
    {
        return new SharedLoggerService();
    }

    /**
     * Create cache service
     */
    private function createCache()
    {
        return new SharedCacheService();
    }

    /**
     * Create validator service
     */
    private function createValidator()
    {
        return new SharedValidationService();
    }

    /**
     * Create encryptor service
     */
    private function createEncryptor()
    {
        return new SharedEncryptionService();
    }

    /**
     * Create HTTP client service
     */
    private function createHttpClient()
    {
        return new SharedHttpClientService();
    }

    /**
     * Create file manager service
     */
    private function createFileManager()
    {
        return new SharedFileManagerService();
    }

    /**
     * Create notification service
     */
    private function createNotificationService()
    {
        return new SharedNotificationService();
    }

    /**
     * Create audit service
     */
    private function createAuditService()
    {
        return new SharedAuditService();
    }
}

/**
 * Shared Logger Service
 */
class SharedLoggerService
{
    private $logFile;
    private $logLevel;

    public function __construct()
    {
        $this->logFile = LOG_PATH . '/shared_services.log';
        $this->logLevel = getenv('LOG_LEVEL') ?: 'INFO';
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function log($level, $message, $context = [])
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('WARNING', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    private function shouldLog($level)
    {
        $levels = ['DEBUG' => 1, 'INFO' => 2, 'WARNING' => 3, 'ERROR' => 4];
        return $levels[$level] >= $levels[$this->logLevel];
    }
}

/**
 * Shared Cache Service
 */
class SharedCacheService
{
    private $cache = [];
    private $cacheFile;

    public function __construct()
    {
        $this->cacheFile = CACHE_PATH . '/shared_cache.ser';
        $this->loadCache();
    }

    public function get($key, $default = null)
    {
        if (isset($this->cache[$key]) && !$this->isExpired($this->cache[$key])) {
            return $this->cache[$key]['value'];
        }

        unset($this->cache[$key]);
        return $default;
    }

    public function set($key, $value, $ttl = 3600)
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        $this->saveCache();
    }

    public function has($key)
    {
        return isset($this->cache[$key]) && !$this->isExpired($this->cache[$key]);
    }

    public function delete($key)
    {
        unset($this->cache[$key]);
        $this->saveCache();
    }

    public function clear()
    {
        $this->cache = [];
        $this->saveCache();
    }

    private function isExpired($item)
    {
        return isset($item['expires']) && $item['expires'] < time();
    }

    private function loadCache()
    {
        if (file_exists($this->cacheFile)) {
            $this->cache = unserialize(file_get_contents($this->cacheFile)) ?: [];
        }
    }

    private function saveCache()
    {
        file_put_contents($this->cacheFile, serialize($this->cache), LOCK_EX);
    }
}

/**
 * Shared Validation Service
 */
class SharedValidationService
{
    private $rules = [];

    public function addRule($field, callable $validator, $message = '')
    {
        $this->rules[$field] = [
            'validator' => $validator,
            'message' => $message ?: "Validation failed for {$field}"
        ];
    }

    public function validate($data)
    {
        $errors = [];

        foreach ($this->rules as $field => $rule) {
            if (!isset($data[$field])) {
                $errors[$field] = "Field {$field} is required";
                continue;
            }

            if (!call_user_func($rule['validator'], $data[$field])) {
                $errors[$field] = $rule['message'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function validateRequired($value)
    {
        return !empty($value) && trim($value) !== '';
    }

    public function validateNumeric($value)
    {
        return is_numeric($value);
    }

    public function validateLength($value, $min = null, $max = null)
    {
        $length = strlen($value);

        if ($min !== null && $length < $min) {
            return false;
        }

        if ($max !== null && $length > $max) {
            return false;
        }

        return true;
    }

    public function validateRegex($value, $pattern)
    {
        return preg_match($pattern, $value) === 1;
    }
}

/**
 * Shared Encryption Service
 */
class SharedEncryptionService
{
    private $key;
    private $cipher;

    public function __construct()
    {
        $this->key = getenv('ENCRYPTION_KEY') ?: 'default-key-change-in-production';
        $this->cipher = 'AES-256-CBC';
    }

    public function encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decrypt($encryptedData)
    {
        $data = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length($this->cipher);

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        return openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
    }

    public function hash($data, $algorithm = 'sha256')
    {
        return hash($algorithm, $data);
    }

    public function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }
}

/**
 * Shared HTTP Client Service
 */
class SharedHttpClientService
{
    private $defaultOptions = [
        'timeout' => 30,
        'headers' => [],
        'user_agent' => 'TPT-Government-Platform/1.0'
    ];

    public function get($url, $options = [])
    {
        return $this->request('GET', $url, $options);
    }

    public function post($url, $data = null, $options = [])
    {
        $options['data'] = $data;
        return $this->request('POST', $url, $options);
    }

    public function put($url, $data = null, $options = [])
    {
        $options['data'] = $data;
        return $this->request('PUT', $url, $options);
    }

    public function delete($url, $options = [])
    {
        return $this->request('DELETE', $url, $options);
    }

    private function request($method, $url, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
        curl_setopt($ch, CURLOPT_USERAGENT, $options['user_agent']);

        // Set method
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        // Set headers
        $headers = $options['headers'];
        if (!empty($options['data'])) {
            if (is_array($options['data'])) {
                $options['data'] = http_build_query($options['data']);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } elseif (is_string($options['data'])) {
                $headers[] = 'Content-Type: application/json';
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Set data
        if (!empty($options['data'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['data']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [
            'success' => $error === '' && $httpCode >= 200 && $httpCode < 300,
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error
        ];
    }
}

/**
 * Shared File Manager Service
 */
class SharedFileManagerService
{
    private $basePath;

    public function __construct()
    {
        $this->basePath = UPLOAD_PATH;
        $this->ensureDirectory($this->basePath);
    }

    public function upload($file, $destination = '', $allowedTypes = [])
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Invalid file upload'];
        }

        // Check file type
        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return ['success' => false, 'error' => 'File type not allowed'];
            }
        }

        $filename = $this->generateUniqueFilename($file['name']);
        $fullPath = $this->basePath . '/' . $destination . '/' . $filename;

        $this->ensureDirectory(dirname($fullPath));

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $fullPath,
                'size' => $file['size'],
                'type' => $file['type']
            ];
        }

        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    public function download($filename, $destination = '')
    {
        $filePath = $this->basePath . '/' . $destination . '/' . $filename;

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }

    public function delete($filename, $path = '')
    {
        $filePath = $this->basePath . '/' . $path . '/' . $filename;

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    public function listFiles($path = '', $pattern = '*')
    {
        $directory = $this->basePath . '/' . $path;

        if (!is_dir($directory)) {
            return [];
        }

        return glob($directory . '/' . $pattern);
    }

    public function getFileInfo($filename, $path = '')
    {
        $filePath = $this->basePath . '/' . $path . '/' . $filename;

        if (!file_exists($filePath)) {
            return null;
        }

        return [
            'name' => basename($filename),
            'path' => $filePath,
            'size' => filesize($filePath),
            'type' => mime_content_type($filePath),
            'modified' => filemtime($filePath),
            'readable' => is_readable($filePath),
            'writable' => is_writable($filePath)
        ];
    }

    private function generateUniqueFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);

        do {
            $uniqueName = $basename . '_' . uniqid() . '.' . $extension;
        } while (file_exists($this->basePath . '/' . $uniqueName));

        return $uniqueName;
    }

    private function ensureDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}

/**
 * Shared Notification Service
 */
class SharedNotificationService
{
    private $transports = [];

    public function __construct()
    {
        $this->registerTransport('email', [$this, 'sendEmail']);
        $this->registerTransport('sms', [$this, 'sendSms']);
        $this->registerTransport('push', [$this, 'sendPush']);
    }

    public function registerTransport($type, callable $handler)
    {
        $this->transports[$type] = $handler;
    }

    public function send($type, $recipient, $message, $options = [])
    {
        if (!isset($this->transports[$type])) {
            return ['success' => false, 'error' => "Transport {$type} not available"];
        }

        return call_user_func($this->transports[$type], $recipient, $message, $options);
    }

    private function sendEmail($recipient, $message, $options = [])
    {
        $subject = $options['subject'] ?? 'Notification';
        $headers = $options['headers'] ?? [];

        $headers[] = 'From: ' . ($options['from'] ?? 'noreply@tpt-gov.local');
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        return [
            'success' => mail($recipient, $subject, $message, implode("\r\n", $headers)),
            'transport' => 'email'
        ];
    }

    private function sendSms($recipient, $message, $options = [])
    {
        // Placeholder for SMS sending implementation
        // In production, integrate with SMS gateway
        return [
            'success' => true,
            'transport' => 'sms',
            'message' => 'SMS sending not implemented'
        ];
    }

    private function sendPush($recipient, $message, $options = [])
    {
        // Placeholder for push notification implementation
        // In production, integrate with push notification service
        return [
            'success' => true,
            'transport' => 'push',
            'message' => 'Push notification sending not implemented'
        ];
    }
}

/**
 * Shared Audit Service
 */
class SharedAuditService
{
    private $db;
    private $tableName = 'audit_log';

    public function __construct()
    {
        // Get database connection from container or create new one
        $this->db = $this->getDatabaseConnection();
        $this->ensureAuditTable();
    }

    public function log($action, $userId = null, $resource = null, $details = [], $ipAddress = null)
    {
        $ipAddress = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->tableName}
                (action, user_id, resource, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $action,
                $userId,
                $resource,
                json_encode($details),
                $ipAddress
            ]);

            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getLogs($filters = [], $limit = 100, $offset = 0)
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['resource'])) {
            $where[] = 'resource LIKE ?';
            $params[] = '%' . $filters['resource'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tableName}
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;

            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON details
            foreach ($logs as &$log) {
                $log['details'] = json_decode($log['details'], true);
            }

            return ['success' => true, 'logs' => $logs];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getLogStats($period = '30 days')
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    action,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM {$this->tableName}
                WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL {$period})
                GROUP BY action, DATE(created_at)
                ORDER BY date DESC, count DESC
            ");

            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => true, 'stats' => $stats];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function ensureAuditTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id SERIAL PRIMARY KEY,
                action VARCHAR(100) NOT NULL,
                user_id INTEGER,
                resource VARCHAR(255),
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_user (user_id),
                INDEX idx_resource (resource),
                INDEX idx_created (created_at)
            )
        ";

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            // Log error but don't fail
            error_log("Failed to create audit table: " . $e->getMessage());
        }
    }

    private function getDatabaseConnection()
    {
        // Try to get from container, fallback to direct connection
        if (class_exists('Container') && Container::has('database')) {
            return Container::get('database');
        }

        // Fallback: create direct connection
        $config = require CONFIG_PATH . '/database.php';
        return new PDO(
            "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
            $config['options']
        );
    }
}

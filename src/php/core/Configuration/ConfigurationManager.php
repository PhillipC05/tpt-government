<?php
/**
 * TPT Government Platform - Configuration Management System
 *
 * Web-based interface for managing application configuration
 * with validation, backup/restore, and environment management
 */

namespace Core\Configuration;

use Core\Database;
use Core\Logging\StructuredLogger;
use Core\EnvironmentManager;
use Exception;

class ConfigurationManager
{
    private Database $db;
    private StructuredLogger $logger;
    private EnvironmentManager $envManager;
    private array $configSchemas = [];
    private string $configPath;

    public function __construct(Database $db, StructuredLogger $logger, EnvironmentManager $envManager)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->envManager = $envManager;
        $this->configPath = dirname(__DIR__, 2) . '/config';
        $this->loadConfigurationSchemas();
    }

    /**
     * Load configuration schemas for validation
     */
    private function loadConfigurationSchemas(): void
    {
        $this->configSchemas = [
            'database' => [
                'host' => ['type' => 'string', 'required' => true, 'pattern' => '/^[a-zA-Z0-9.-]+$/'],
                'port' => ['type' => 'integer', 'required' => true, 'min' => 1, 'max' => 65535],
                'database' => ['type' => 'string', 'required' => true, 'pattern' => '/^[a-zA-Z0-9_-]+$/'],
                'username' => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => true, 'sensitive' => true],
                'charset' => ['type' => 'string', 'required' => false, 'default' => 'utf8mb4'],
                'collation' => ['type' => 'string', 'required' => false, 'default' => 'utf8mb4_unicode_ci']
            ],
            'application' => [
                'name' => ['type' => 'string', 'required' => true],
                'version' => ['type' => 'string', 'required' => true, 'pattern' => '/^\d+\.\d+\.\d+$/'],
                'environment' => ['type' => 'enum', 'required' => true, 'values' => ['development', 'staging', 'production']],
                'debug' => ['type' => 'boolean', 'required' => false, 'default' => false],
                'timezone' => ['type' => 'string', 'required' => false, 'default' => 'UTC'],
                'locale' => ['type' => 'string', 'required' => false, 'default' => 'en_US']
            ],
            'security' => [
                'session_lifetime' => ['type' => 'integer', 'required' => false, 'min' => 300, 'max' => 86400, 'default' => 3600],
                'password_min_length' => ['type' => 'integer', 'required' => false, 'min' => 8, 'max' => 128, 'default' => 12],
                'password_require_uppercase' => ['type' => 'boolean', 'required' => false, 'default' => true],
                'password_require_lowercase' => ['type' => 'boolean', 'required' => false, 'default' => true],
                'password_require_numbers' => ['type' => 'boolean', 'required' => false, 'default' => true],
                'password_require_symbols' => ['type' => 'boolean', 'required' => false, 'default' => true],
                'max_login_attempts' => ['type' => 'integer', 'required' => false, 'min' => 3, 'max' => 10, 'default' => 5],
                'lockout_duration' => ['type' => 'integer', 'required' => false, 'min' => 300, 'max' => 3600, 'default' => 900]
            ],
            'api' => [
                'enabled' => ['type' => 'boolean', 'required' => false, 'default' => true],
                'version' => ['type' => 'string', 'required' => false, 'default' => 'v1'],
                'rate_limit_requests' => ['type' => 'integer', 'required' => false, 'min' => 10, 'max' => 10000, 'default' => 1000],
                'rate_limit_window' => ['type' => 'integer', 'required' => false, 'min' => 60, 'max' => 3600, 'default' => 3600],
                'cors_origins' => ['type' => 'array', 'required' => false, 'default' => ['*']],
                'cors_methods' => ['type' => 'array', 'required' => false, 'default' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']],
                'cors_headers' => ['type' => 'array', 'required' => false, 'default' => ['Content-Type', 'Authorization', 'X-Requested-With']]
            ],
            'email' => [
                'smtp_host' => ['type' => 'string', 'required' => false],
                'smtp_port' => ['type' => 'integer', 'required' => false, 'min' => 1, 'max' => 65535, 'default' => 587],
                'smtp_username' => ['type' => 'string', 'required' => false],
                'smtp_password' => ['type' => 'string', 'required' => false, 'sensitive' => true],
                'smtp_encryption' => ['type' => 'enum', 'required' => false, 'values' => ['tls', 'ssl', 'none'], 'default' => 'tls'],
                'from_address' => ['type' => 'string', 'required' => false, 'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
                'from_name' => ['type' => 'string', 'required' => false]
            ],
            'cache' => [
                'enabled' => ['type' => 'boolean', 'required' => false, 'default' => true],
                'driver' => ['type' => 'enum', 'required' => false, 'values' => ['file', 'redis', 'memcached', 'database'], 'default' => 'file'],
                'ttl' => ['type' => 'integer', 'required' => false, 'min' => 60, 'max' => 86400, 'default' => 3600],
                'prefix' => ['type' => 'string', 'required' => false, 'default' => 'tpt_'],
                'redis_host' => ['type' => 'string', 'required' => false],
                'redis_port' => ['type' => 'integer', 'required' => false, 'min' => 1, 'max' => 65535, 'default' => 6379],
                'redis_password' => ['type' => 'string', 'required' => false, 'sensitive' => true]
            ],
            'logging' => [
                'level' => ['type' => 'enum', 'required' => false, 'values' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], 'default' => 'info'],
                'max_files' => ['type' => 'integer', 'required' => false, 'min' => 1, 'max' => 100, 'default' => 30],
                'max_size' => ['type' => 'integer', 'required' => false, 'min' => 1048576, 'max' => 1073741824, 'default' => 10485760], // 1MB to 1GB
                'format' => ['type' => 'enum', 'required' => false, 'values' => ['json', 'text', 'xml'], 'default' => 'json']
            ]
        ];
    }

    /**
     * Get configuration for a specific section
     */
    public function getConfiguration(string $section): array
    {
        try {
            $sql = "SELECT config_key, config_value, is_sensitive, updated_at, updated_by
                    FROM configuration
                    WHERE section = ? AND environment = ?
                    ORDER BY config_key";

            $results = $this->db->fetchAll($sql, [$section, $this->envManager->getCurrentEnvironment()]);

            $config = [];
            foreach ($results as $result) {
                $value = json_decode($result['config_value'], true);
                if ($result['is_sensitive']) {
                    $value = '***HIDDEN***';
                }
                $config[$result['config_key']] = [
                    'value' => $value,
                    'sensitive' => $result['is_sensitive'],
                    'updated_at' => $result['updated_at'],
                    'updated_by' => $result['updated_by']
                ];
            }

            return $config;
        } catch (Exception $e) {
            $this->logger->error('Failed to get configuration', [
                'section' => $section,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Update configuration value
     */
    public function updateConfiguration(string $section, string $key, $value, int $userId): bool
    {
        try {
            // Validate configuration
            $validation = $this->validateConfigurationValue($section, $key, $value);
            if (!$validation['valid']) {
                $this->logger->warning('Configuration validation failed', [
                    'section' => $section,
                    'key' => $key,
                    'errors' => $validation['errors']
                ]);
                return false;
            }

            $environment = $this->envManager->getCurrentEnvironment();
            $isSensitive = $this->isSensitiveField($section, $key);

            // Check if configuration exists
            $existing = $this->db->fetch(
                "SELECT id FROM configuration WHERE section = ? AND config_key = ? AND environment = ?",
                [$section, $key, $environment]
            );

            if ($existing) {
                // Update existing
                $sql = "UPDATE configuration
                        SET config_value = ?, is_sensitive = ?, updated_at = NOW(), updated_by = ?
                        WHERE section = ? AND config_key = ? AND environment = ?";
                $result = $this->db->execute($sql, [
                    json_encode($value),
                    $isSensitive,
                    $userId,
                    $section,
                    $key,
                    $environment
                ]);
            } else {
                // Insert new
                $sql = "INSERT INTO configuration
                        (section, config_key, config_value, is_sensitive, environment, created_at, updated_at, updated_by)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";
                $result = $this->db->execute($sql, [
                    $section,
                    $key,
                    json_encode($value),
                    $isSensitive,
                    $environment,
                    $userId
                ]);
            }

            if ($result) {
                $this->logger->info('Configuration updated', [
                    'section' => $section,
                    'key' => $key,
                    'user_id' => $userId,
                    'environment' => $environment
                ]);

                // Create backup
                $this->createConfigurationBackup($section, $userId);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to update configuration', [
                'section' => $section,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Bulk update configuration
     */
    public function bulkUpdateConfiguration(string $section, array $configData, int $userId): array
    {
        $results = ['success' => [], 'errors' => []];

        $this->db->beginTransaction();

        try {
            foreach ($configData as $key => $value) {
                $result = $this->updateConfiguration($section, $key, $value, $userId);
                if ($result) {
                    $results['success'][] = $key;
                } else {
                    $results['errors'][] = $key;
                }
            }

            $this->db->commit();

            $this->logger->info('Bulk configuration update completed', [
                'section' => $section,
                'success_count' => count($results['success']),
                'error_count' => count($results['errors']),
                'user_id' => $userId
            ]);

        } catch (Exception $e) {
            $this->db->rollback();
            $results['errors'][] = 'Transaction failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Validate configuration value
     */
    private function validateConfigurationValue(string $section, string $key, $value): array
    {
        $errors = [];

        if (!isset($this->configSchemas[$section][$key])) {
            $errors[] = "Unknown configuration key: {$key}";
            return ['valid' => false, 'errors' => $errors];
        }

        $schema = $this->configSchemas[$section][$key];

        // Type validation
        switch ($schema['type']) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "Value must be a string";
                } elseif (isset($schema['pattern']) && !preg_match($schema['pattern'], $value)) {
                    $errors[] = "Value does not match required pattern";
                }
                break;

            case 'integer':
                if (!is_int($value) && !is_numeric($value)) {
                    $errors[] = "Value must be an integer";
                } else {
                    $value = (int)$value;
                    if (isset($schema['min']) && $value < $schema['min']) {
                        $errors[] = "Value must be at least {$schema['min']}";
                    }
                    if (isset($schema['max']) && $value > $schema['max']) {
                        $errors[] = "Value must be at most {$schema['max']}";
                    }
                }
                break;

            case 'boolean':
                if (!is_bool($value)) {
                    $errors[] = "Value must be a boolean";
                }
                break;

            case 'enum':
                if (!in_array($value, $schema['values'])) {
                    $errors[] = "Value must be one of: " . implode(', ', $schema['values']);
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    $errors[] = "Value must be an array";
                }
                break;
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Check if field is sensitive
     */
    private function isSensitiveField(string $section, string $key): bool
    {
        return isset($this->configSchemas[$section][$key]['sensitive']) &&
               $this->configSchemas[$section][$key]['sensitive'];
    }

    /**
     * Create configuration backup
     */
    private function createConfigurationBackup(string $section, int $userId): void
    {
        try {
            $environment = $this->envManager->getCurrentEnvironment();

            // Get all configuration for the section
            $config = $this->db->fetchAll(
                "SELECT config_key, config_value, is_sensitive FROM configuration
                 WHERE section = ? AND environment = ?",
                [$section, $environment]
            );

            $backupData = [
                'section' => $section,
                'environment' => $environment,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'configuration' => $config
            ];

            // Save backup to database
            $sql = "INSERT INTO configuration_backups
                    (section, environment, backup_data, created_at, created_by)
                    VALUES (?, ?, ?, NOW(), ?)";

            $this->db->execute($sql, [
                $section,
                $environment,
                json_encode($backupData),
                $userId
            ]);

            // Keep only last 10 backups
            $this->cleanupOldBackups($section, $environment);

        } catch (Exception $e) {
            $this->logger->error('Failed to create configuration backup', [
                'section' => $section,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clean up old backups
     */
    private function cleanupOldBackups(string $section, string $environment): void
    {
        try {
            $sql = "DELETE FROM configuration_backups
                    WHERE section = ? AND environment = ? AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM configuration_backups
                            WHERE section = ? AND environment = ?
                            ORDER BY created_at DESC
                            LIMIT 10
                        ) t
                    )";

            $this->db->execute($sql, [$section, $environment, $section, $environment]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to cleanup old configuration backups', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Restore configuration from backup
     */
    public function restoreConfiguration(int $backupId, int $userId): bool
    {
        try {
            // Get backup data
            $backup = $this->db->fetch(
                "SELECT * FROM configuration_backups WHERE id = ?",
                [$backupId]
            );

            if (!$backup) {
                return false;
            }

            $backupData = json_decode($backup['backup_data'], true);

            $this->db->beginTransaction();

            try {
                // Delete current configuration
                $this->db->execute(
                    "DELETE FROM configuration WHERE section = ? AND environment = ?",
                    [$backupData['section'], $backupData['environment']]
                );

                // Restore from backup
                foreach ($backupData['configuration'] as $config) {
                    $sql = "INSERT INTO configuration
                            (section, config_key, config_value, is_sensitive, environment, created_at, updated_at, updated_by)
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)";

                    $this->db->execute($sql, [
                        $backupData['section'],
                        $config['config_key'],
                        $config['config_value'],
                        $config['is_sensitive'],
                        $backupData['environment'],
                        $userId
                    ]);
                }

                $this->db->commit();

                $this->logger->info('Configuration restored from backup', [
                    'backup_id' => $backupId,
                    'section' => $backupData['section'],
                    'user_id' => $userId
                ]);

                return true;
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            $this->logger->error('Failed to restore configuration', [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get configuration backups
     */
    public function getConfigurationBackups(string $section = null): array
    {
        try {
            $sql = "SELECT id, section, environment, created_at, created_by
                    FROM configuration_backups";

            $params = [];
            if ($section) {
                $sql .= " WHERE section = ?";
                $params[] = $section;
            }

            $sql .= " ORDER BY created_at DESC";

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            $this->logger->error('Failed to get configuration backups', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Export configuration
     */
    public function exportConfiguration(string $section, string $format = 'json'): string
    {
        try {
            $config = $this->getConfiguration($section);

            switch ($format) {
                case 'json':
                    return json_encode($config, JSON_PRETTY_PRINT);

                case 'php':
                    return "<?php\nreturn " . var_export($config, true) . ";\n";

                case 'yaml':
                    return $this->arrayToYaml($config);

                default:
                    throw new Exception("Unsupported export format: {$format}");
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to export configuration', [
                'section' => $section,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Import configuration
     */
    public function importConfiguration(string $section, string $data, string $format, int $userId): bool
    {
        try {
            switch ($format) {
                case 'json':
                    $config = json_decode($data, true);
                    break;

                case 'php':
                    $config = include 'data://text/plain;base64,' . base64_encode($data);
                    break;

                case 'yaml':
                    $config = $this->yamlToArray($data);
                    break;

                default:
                    throw new Exception("Unsupported import format: {$format}");
            }

            if (!is_array($config)) {
                throw new Exception("Invalid configuration data");
            }

            return $this->bulkUpdateConfiguration($section, $config, $userId)['errors'] === [];
        } catch (Exception $e) {
            $this->logger->error('Failed to import configuration', [
                'section' => $section,
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get configuration schema
     */
    public function getConfigurationSchema(string $section = null): array
    {
        if ($section) {
            return $this->configSchemas[$section] ?? [];
        }

        return $this->configSchemas;
    }

    /**
     * Get configuration sections
     */
    public function getConfigurationSections(): array
    {
        return array_keys($this->configSchemas);
    }

    /**
     * Get configuration history
     */
    public function getConfigurationHistory(string $section, string $key, int $limit = 50): array
    {
        try {
            $sql = "SELECT config_value, updated_at, updated_by
                    FROM configuration_history
                    WHERE section = ? AND config_key = ?
                    ORDER BY updated_at DESC
                    LIMIT ?";

            $results = $this->db->fetchAll($sql, [$section, $key, $limit]);

            return array_map(function($result) {
                return [
                    'value' => json_decode($result['config_value'], true),
                    'updated_at' => $result['updated_at'],
                    'updated_by' => $result['updated_by']
                ];
            }, $results);
        } catch (Exception $e) {
            $this->logger->error('Failed to get configuration history', [
                'section' => $section,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Convert array to YAML (simplified)
     */
    private function arrayToYaml(array $array, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            $yaml .= $indentStr . $key . ': ';

            if (is_array($value)) {
                $yaml .= "\n" . $this->arrayToYaml($value, $indent + 1);
            } elseif (is_bool($value)) {
                $yaml .= $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                $yaml .= '"' . str_replace('"', '\\"', $value) . '"';
            } else {
                $yaml .= $value;
            }

            $yaml .= "\n";
        }

        return $yaml;
    }

    /**
     * Convert YAML to array (simplified)
     */
    private function yamlToArray(string $yaml): array
    {
        // This is a simplified YAML parser
        // In production, you would use a proper YAML library
        $lines = explode("\n", $yaml);
        $result = [];
        $current = &$result;
        $stack = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));
            $line = ltrim($line);

            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line, 2);

                // Adjust current reference based on indentation
                while (count($stack) > 0 && $stack[count($stack) - 1]['indent'] >= $indent) {
                    array_pop($stack);
                }

                if (count($stack) > 0) {
                    $current = &$stack[count($stack) - 1]['ref'];
                } else {
                    $current = &$result;
                }

                if (empty($value)) {
                    $current[$key] = [];
                    $stack[] = ['indent' => $indent, 'ref' => &$current[$key]];
                } else {
                    $current[$key] = $this->parseYamlValue($value);
                }
            }
        }

        return $result;
    }

    /**
     * Parse YAML value
     */
    private function parseYamlValue(string $value)
    {
        $value = trim($value);

        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
}

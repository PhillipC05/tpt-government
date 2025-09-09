<?php
/**
 * TPT Government Platform - Environment Manager
 *
 * Environment-based configuration management system
 */

class EnvironmentManager
{
    private $logger;
    private $config;
    private $environment;
    private $configCache = [];
    private $configFiles = [];

    /**
     * Environment types
     */
    const ENV_LOCAL = 'local';
    const ENV_DEVELOPMENT = 'development';
    const ENV_STAGING = 'staging';
    const ENV_PRODUCTION = 'production';
    const ENV_TESTING = 'testing';

    /**
     * Configuration file types
     */
    const CONFIG_TYPE_PHP = 'php';
    const CONFIG_TYPE_JSON = 'json';
    const CONFIG_TYPE_YAML = 'yaml';
    const CONFIG_TYPE_ENV = 'env';

    /**
     * Constructor
     */
    public function __construct(StructuredLogger $logger, $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'config_paths' => [
                'config/',
                'src/php/config/',
                '../config/'
            ],
            'env_file' => '.env',
            'cache_config' => true,
            'config_cache_ttl' => 3600,
            'validate_config' => true,
            'allow_env_override' => true,
            'default_environment' => self::ENV_LOCAL
        ], $config);

        $this->environment = $this->detectEnvironment();
        $this->initializeConfigFiles();

        $this->logger->info('Environment Manager initialized', [
            'environment' => $this->environment,
            'config_files_found' => count($this->configFiles)
        ]);
    }

    /**
     * Detect current environment
     */
    private function detectEnvironment()
    {
        // Check environment variable first
        $env = getenv('APP_ENV') ?: getenv('ENVIRONMENT');

        if ($env) {
            return $this->validateEnvironment($env);
        }

        // Check for environment-specific files
        $envFiles = [
            self::ENV_PRODUCTION => '.env.production',
            self::ENV_STAGING => '.env.staging',
            self::ENV_DEVELOPMENT => '.env.development',
            self::ENV_TESTING => '.env.testing'
        ];

        foreach ($envFiles as $envName => $fileName) {
            if (file_exists($fileName)) {
                return $envName;
            }
        }

        // Check hostname patterns
        $hostname = gethostname();
        if (preg_match('/prod/i', $hostname)) {
            return self::ENV_PRODUCTION;
        } elseif (preg_match('/stag/i', $hostname)) {
            return self::ENV_STAGING;
        } elseif (preg_match('/dev/i', $hostname)) {
            return self::ENV_DEVELOPMENT;
        } elseif (preg_match('/test/i', $hostname)) {
            return self::ENV_TESTING;
        }

        return $this->config['default_environment'];
    }

    /**
     * Validate environment name
     */
    private function validateEnvironment($env)
    {
        $validEnvs = [
            self::ENV_LOCAL,
            self::ENV_DEVELOPMENT,
            self::ENV_STAGING,
            self::ENV_PRODUCTION,
            self::ENV_TESTING
        ];

        return in_array($env, $validEnvs) ? $env : $this->config['default_environment'];
    }

    /**
     * Initialize configuration files
     */
    private function initializeConfigFiles()
    {
        $this->configFiles = [];

        foreach ($this->config['config_paths'] as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '*.{php,json,yaml,yml}', GLOB_BRACE);
            foreach ($files as $file) {
                $this->configFiles[] = $file;
            }
        }

        // Add .env file if it exists
        if (file_exists($this->config['env_file'])) {
            $this->configFiles[] = $this->config['env_file'];
        }
    }

    /**
     * Get configuration value
     */
    public function get($key, $default = null)
    {
        // Check cache first
        if ($this->config['cache_config'] && isset($this->configCache[$key])) {
            return $this->configCache[$key];
        }

        $value = $this->loadConfigValue($key);

        if ($value === null) {
            $value = $default;
        }

        // Cache the value
        if ($this->config['cache_config']) {
            $this->configCache[$key] = $value;
        }

        return $value;
    }

    /**
     * Load configuration value
     */
    private function loadConfigValue($key)
    {
        $keys = explode('.', $key);
        $mainKey = array_shift($keys);
        $subKeys = $keys;

        // Check environment variables first (if allowed)
        if ($this->config['allow_env_override']) {
            $envKey = strtoupper(str_replace('.', '_', $key));
            $envValue = getenv($envKey);
            if ($envValue !== false) {
                return $this->castValue($envValue);
            }
        }

        // Load from configuration files
        foreach ($this->configFiles as $file) {
            $config = $this->loadConfigFile($file);
            if ($config && isset($config[$mainKey])) {
                $value = $config[$mainKey];

                // Navigate through sub-keys
                foreach ($subKeys as $subKey) {
                    if (is_array($value) && isset($value[$subKey])) {
                        $value = $value[$subKey];
                    } else {
                        $value = null;
                        break;
                    }
                }

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Load configuration file
     */
    private function loadConfigFile($file)
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'php':
                return $this->loadPhpConfig($file);
            case 'json':
                return $this->loadJsonConfig($file);
            case 'yaml':
            case 'yml':
                return $this->loadYamlConfig($file);
            case 'env':
                return $this->loadEnvConfig($file);
            default:
                return null;
        }
    }

    /**
     * Load PHP configuration file
     */
    private function loadPhpConfig($file)
    {
        if (!file_exists($file)) {
            return null;
        }

        try {
            $config = include $file;
            return is_array($config) ? $config : null;
        } catch (Exception $e) {
            $this->logger->error('Failed to load PHP config file', [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Load JSON configuration file
     */
    private function loadJsonConfig($file)
    {
        if (!file_exists($file)) {
            return null;
        }

        try {
            $content = file_get_contents($file);
            return json_decode($content, true);
        } catch (Exception $e) {
            $this->logger->error('Failed to load JSON config file', [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Load YAML configuration file
     */
    private function loadYamlConfig($file)
    {
        if (!file_exists($file)) {
            return null;
        }

        try {
            // In a real implementation, you'd use a YAML parser like Symfony Yaml
            // For now, we'll return null as YAML support requires additional dependencies
            $this->logger->warning('YAML config support not implemented', ['file' => $file]);
            return null;
        } catch (Exception $e) {
            $this->logger->error('Failed to load YAML config file', [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Load .env configuration file
     */
    private function loadEnvConfig($file)
    {
        if (!file_exists($file)) {
            return null;
        }

        try {
            $config = [];
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Remove quotes if present
                    $value = trim($value, '"\'');

                    $config[$key] = $this->castValue($value);
                }
            }

            return $config;
        } catch (Exception $e) {
            $this->logger->error('Failed to load .env config file', [
                'file' => $file,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cast configuration value to appropriate type
     */
    private function castValue($value)
    {
        // Handle boolean values
        if (strtolower($value) === 'true') {
            return true;
        } elseif (strtolower($value) === 'false') {
            return false;
        }

        // Handle null values
        if (strtolower($value) === 'null') {
            return null;
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public function set($key, $value)
    {
        // Update cache
        if ($this->config['cache_config']) {
            $this->configCache[$key] = $value;
        }

        // In a real implementation, you might want to persist this to a file
        // For now, we'll just log it
        $this->logger->info('Configuration value set', [
            'key' => $key,
            'value' => $value,
            'environment' => $this->environment
        ]);
    }

    /**
     * Get all configuration values
     */
    public function all()
    {
        $config = [];

        // Load all configuration files
        foreach ($this->configFiles as $file) {
            $fileConfig = $this->loadConfigFile($file);
            if ($fileConfig) {
                $config = array_merge_recursive($config, $fileConfig);
            }
        }

        // Apply environment variable overrides
        if ($this->config['allow_env_override']) {
            $envVars = $this->getEnvironmentVariables();
            $config = array_merge_recursive($config, $envVars);
        }

        return $config;
    }

    /**
     * Get environment variables as config
     */
    private function getEnvironmentVariables()
    {
        $config = [];
        $envVars = getenv();

        foreach ($envVars as $key => $value) {
            // Convert environment variable names to config keys
            $configKey = strtolower(str_replace('_', '.', $key));
            $config[$configKey] = $this->castValue($value);
        }

        return $config;
    }

    /**
     * Validate configuration
     */
    public function validate()
    {
        if (!$this->config['validate_config']) {
            return true;
        }

        $errors = [];

        // Check required configuration values
        $requiredKeys = [
            'app.name',
            'app.version',
            'database.host',
            'database.name'
        ];

        foreach ($requiredKeys as $key) {
            if ($this->get($key) === null) {
                $errors[] = "Required configuration key '{$key}' is missing";
            }
        }

        // Validate database configuration
        $dbHost = $this->get('database.host');
        if ($dbHost && !filter_var($dbHost, FILTER_VALIDATE_IP) && !filter_var($dbHost, FILTER_VALIDATE_DOMAIN)) {
            $errors[] = "Invalid database host: {$dbHost}";
        }

        // Validate email configuration
        $email = $this->get('mail.from');
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address: {$email}";
        }

        if (!empty($errors)) {
            $this->logger->error('Configuration validation failed', [
                'errors' => $errors,
                'environment' => $this->environment
            ]);
            return false;
        }

        $this->logger->info('Configuration validation passed', [
            'environment' => $this->environment
        ]);

        return true;
    }

    /**
     * Get current environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Check if current environment is production
     */
    public function isProduction()
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    /**
     * Check if current environment is development
     */
    public function isDevelopment()
    {
        return $this->environment === self::ENV_DEVELOPMENT;
    }

    /**
     * Check if current environment is testing
     */
    public function isTesting()
    {
        return $this->environment === self::ENV_TESTING;
    }

    /**
     * Check if current environment is local
     */
    public function isLocal()
    {
        return $this->environment === self::ENV_LOCAL;
    }

    /**
     * Get environment-specific configuration
     */
    public function getEnvironmentConfig()
    {
        $envConfig = [];

        // Load environment-specific configuration files
        $envFiles = [
            "config/{$this->environment}.php",
            "config/{$this->environment}.json",
            ".env.{$this->environment}"
        ];

        foreach ($envFiles as $file) {
            if (file_exists($file)) {
                $config = $this->loadConfigFile($file);
                if ($config) {
                    $envConfig = array_merge_recursive($envConfig, $config);
                }
            }
        }

        return $envConfig;
    }

    /**
     * Create configuration backup
     */
    public function createBackup($backupPath = null)
    {
        $backupPath = $backupPath ?? 'backups/config/' . date('Y-m-d-H-i-s') . '.json';

        // Create backup directory if it doesn't exist
        $dir = dirname($backupPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config = $this->all();
        $backupData = [
            'timestamp' => date('c'),
            'environment' => $this->environment,
            'config' => $config
        ];

        $result = file_put_contents($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));

        if ($result === false) {
            $this->logger->error('Failed to create configuration backup', [
                'path' => $backupPath
            ]);
            return false;
        }

        $this->logger->info('Configuration backup created', [
            'path' => $backupPath,
            'size' => $result
        ]);

        return $backupPath;
    }

    /**
     * Restore configuration from backup
     */
    public function restoreBackup($backupPath)
    {
        if (!file_exists($backupPath)) {
            $this->logger->error('Backup file not found', ['path' => $backupPath]);
            return false;
        }

        try {
            $content = file_get_contents($backupPath);
            $backupData = json_decode($content, true);

            if (!$backupData || !isset($backupData['config'])) {
                throw new Exception('Invalid backup file format');
            }

            // Clear current cache
            $this->configCache = [];

            // In a real implementation, you might want to write the config back to files
            // For now, we'll just log the restoration
            $this->logger->info('Configuration restored from backup', [
                'path' => $backupPath,
                'timestamp' => $backupData['timestamp'],
                'environment' => $backupData['environment']
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Failed to restore configuration backup', [
                'path' => $backupPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get configuration info
     */
    public function getInfo()
    {
        return [
            'environment' => $this->environment,
            'config_files' => $this->configFiles,
            'cache_enabled' => $this->config['cache_config'],
            'env_override_allowed' => $this->config['allow_env_override'],
            'validation_enabled' => $this->config['validate_config'],
            'cached_keys' => array_keys($this->configCache)
        ];
    }

    /**
     * Clear configuration cache
     */
    public function clearCache()
    {
        $cachedKeys = count($this->configCache);
        $this->configCache = [];

        $this->logger->info('Configuration cache cleared', [
            'keys_cleared' => $cachedKeys
        ]);

        return $cachedKeys;
    }

    /**
     * Reload configuration
     */
    public function reload()
    {
        $this->clearCache();
        $this->initializeConfigFiles();

        $this->logger->info('Configuration reloaded', [
            'environment' => $this->environment,
            'config_files' => count($this->configFiles)
        ]);

        return true;
    }
}

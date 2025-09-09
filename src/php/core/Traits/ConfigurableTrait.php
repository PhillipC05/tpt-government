<?php
/**
 * TPT Government Platform - Configurable Trait
 *
 * Provides configuration management capabilities for modules and services
 */

trait ConfigurableTrait
{
    protected $config = [];
    protected $configFile;
    protected $configCache = [];
    protected $configValidators = [];

    /**
     * Set configuration
     */
    public function setConfig($config)
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        } elseif (is_string($config)) {
            $this->loadConfigFromFile($config);
        }

        $this->validateConfig();
        return $this;
    }

    /**
     * Get configuration value
     */
    public function getConfig($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        // Check cache first
        if (isset($this->configCache[$key])) {
            return $this->configCache[$key];
        }

        // Navigate through nested array with dot notation
        $value = $this->getNestedConfigValue($key, $default);

        // Cache the result
        $this->configCache[$key] = $value;

        return $value;
    }

    /**
     * Set configuration value
     */
    public function setConfigValue($key, $value)
    {
        $this->setNestedConfigValue($key, $value);

        // Clear cache for this key and any parent keys
        $this->clearConfigCache($key);

        $this->validateConfig();
        return $this;
    }

    /**
     * Check if configuration key exists
     */
    public function hasConfig($key)
    {
        return $this->getNestedConfigValue($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    /**
     * Load configuration from file
     */
    public function loadConfigFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Configuration file not found: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'php':
                $config = require $filePath;
                break;
            case 'json':
                $config = json_decode(file_get_contents($filePath), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON in configuration file: {$filePath}");
                }
                break;
            case 'yaml':
            case 'yml':
                if (!function_exists('yaml_parse_file')) {
                    throw new Exception("YAML extension not available for file: {$filePath}");
                }
                $config = yaml_parse_file($filePath);
                break;
            case 'ini':
                $config = parse_ini_file($filePath, true);
                break;
            default:
                throw new Exception("Unsupported configuration file format: {$extension}");
        }

        if (!is_array($config)) {
            throw new Exception("Configuration file must return an array: {$filePath}");
        }

        $this->config = array_merge($this->config, $config);
        $this->configFile = $filePath;

        $this->validateConfig();
        return $this;
    }

    /**
     * Save configuration to file
     */
    public function saveConfigToFile($filePath = null)
    {
        $filePath = $filePath ?: $this->configFile;

        if (!$filePath) {
            throw new Exception("No configuration file path specified");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'php':
                $content = "<?php\nreturn " . var_export($this->config, true) . ";\n";
                break;
            case 'json':
                $content = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;
            case 'yaml':
            case 'yml':
                if (!function_exists('yaml_emit')) {
                    throw new Exception("YAML extension not available for file: {$filePath}");
                }
                $content = yaml_emit($this->config);
                break;
            case 'ini':
                $content = $this->arrayToIni($this->config);
                break;
            default:
                throw new Exception("Unsupported configuration file format: {$extension}");
        }

        if (file_put_contents($filePath, $content) === false) {
            throw new Exception("Failed to save configuration to file: {$filePath}");
        }

        return $this;
    }

    /**
     * Merge configuration with another array
     */
    public function mergeConfig($config)
    {
        if (!is_array($config)) {
            throw new Exception("Configuration must be an array");
        }

        $this->config = array_merge_recursive($this->config, $config);
        $this->configCache = []; // Clear cache

        $this->validateConfig();
        return $this;
    }

    /**
     * Get configuration section
     */
    public function getConfigSection($section)
    {
        return $this->getConfig($section, []);
    }

    /**
     * Set configuration section
     */
    public function setConfigSection($section, $config)
    {
        $this->setConfigValue($section, $config);
        return $this;
    }

    /**
     * Add configuration validator
     */
    public function addConfigValidator($key, callable $validator)
    {
        $this->configValidators[$key] = $validator;
        return $this;
    }

    /**
     * Remove configuration validator
     */
    public function removeConfigValidator($key)
    {
        unset($this->configValidators[$key]);
        return $this;
    }

    /**
     * Validate configuration
     */
    protected function validateConfig()
    {
        foreach ($this->configValidators as $key => $validator) {
            $value = $this->getConfig($key);

            if (!$validator($value, $this->config)) {
                throw new Exception("Configuration validation failed for key: {$key}");
            }
        }
    }

    /**
     * Get nested configuration value using dot notation
     */
    private function getNestedConfigValue($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set nested configuration value using dot notation
     */
    private function setNestedConfigValue($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Clear configuration cache for a key and its parents
     */
    private function clearConfigCache($key)
    {
        // Clear exact key
        unset($this->configCache[$key]);

        // Clear parent keys
        $keys = explode('.', $key);
        $parentKey = '';

        foreach ($keys as $i => $k) {
            if ($i > 0) {
                $parentKey .= '.';
            }
            $parentKey .= $k;
            unset($this->configCache[$parentKey]);
        }

        // Clear child keys (this is expensive, but necessary for correctness)
        $prefix = $key . '.';
        foreach (array_keys($this->configCache) as $cacheKey) {
            if (strpos($cacheKey, $prefix) === 0) {
                unset($this->configCache[$cacheKey]);
            }
        }
    }

    /**
     * Convert array to INI format
     */
    private function arrayToIni($array, $prefix = '')
    {
        $result = '';

        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                $result .= "\n[{$fullKey}]\n";
                $result .= $this->arrayToIni($value);
            } else {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif ($value === null) {
                    $value = '';
                }

                $result .= "{$key} = \"{$value}\"\n";
            }
        }

        return $result;
    }

    /**
     * Get configuration file path
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }

    /**
     * Set configuration file path
     */
    public function setConfigFile($filePath)
    {
        $this->configFile = $filePath;
        return $this;
    }

    /**
     * Reload configuration from file
     */
    public function reloadConfig()
    {
        if ($this->configFile) {
            $this->config = [];
            $this->configCache = [];
            $this->loadConfigFromFile($this->configFile);
        }

        return $this;
    }

    /**
     * Get all configuration keys
     */
    public function getConfigKeys($prefix = '')
    {
        $keys = [];

        $config = $prefix ? $this->getNestedConfigValue($prefix, []) : $this->config;

        $this->collectConfigKeys($config, $prefix, $keys);

        return $keys;
    }

    /**
     * Recursively collect configuration keys
     */
    private function collectConfigKeys($config, $prefix, &$keys)
    {
        if (!is_array($config)) {
            $keys[] = $prefix;
            return;
        }

        foreach ($config as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                $this->collectConfigKeys($value, $fullKey, $keys);
            } else {
                $keys[] = $fullKey;
            }
        }
    }

    /**
     * Export configuration as environment variables
     */
    public function exportToEnv($prefix = '')
    {
        $envVars = [];

        foreach ($this->getConfigKeys() as $key) {
            $envKey = $prefix . strtoupper(str_replace('.', '_', $key));
            $value = $this->getConfig($key);

            if (is_scalar($value)) {
                $envVars[$envKey] = (string) $value;
            }
        }

        return $envVars;
    }

    /**
     * Import configuration from environment variables
     */
    public function importFromEnv($prefix = '')
    {
        $prefixLength = strlen($prefix);

        foreach ($_ENV as $envKey => $envValue) {
            if (strpos($envKey, $prefix) === 0) {
                $configKey = strtolower(str_replace('_', '.', substr($envKey, $prefixLength)));
                $this->setConfigValue($configKey, $envValue);
            }
        }

        return $this;
    }
}

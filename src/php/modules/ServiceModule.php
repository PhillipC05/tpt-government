<?php
/**
 * TPT Government Platform - Service Module Base Class
 *
 * Standardized base class for all government service modules
 * providing common functionality, interfaces, and lifecycle management
 */

namespace Modules;

abstract class ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [];

    /**
     * Module configuration
     */
    protected array $config = [];

    /**
     * Module dependencies
     */
    protected array $dependencies = [];

    /**
     * Module hooks
     */
    protected array $hooks = [];

    /**
     * Module permissions
     */
    protected array $permissions = [];

    /**
     * Module database tables
     */
    protected array $tables = [];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [];

    /**
     * Module workflows
     */
    protected array $workflows = [];

    /**
     * Module forms
     */
    protected array $forms = [];

    /**
     * Module reports
     */
    protected array $reports = [];

    /**
     * Module notifications
     */
    protected array $notifications = [];

    /**
     * Module cache
     */
    protected array $cache = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeModule();
        $this->loadConfiguration();
        $this->setupDependencies();
        $this->registerHooks();
        $this->initializePermissions();
        $this->setupDatabase();
        $this->registerEndpoints();
        $this->initializeWorkflows();
        $this->setupForms();
        $this->configureReports();
        $this->setupNotifications();
    }

    /**
     * Get module metadata
     */
    abstract public function getMetadata(): array;

    /**
     * Get default configuration
     */
    abstract protected function getDefaultConfig(): array;

    /**
     * Initialize module
     */
    abstract protected function initializeModule(): void;

    /**
     * Get module name
     */
    public function getName(): string
    {
        return $this->metadata['name'] ?? 'Unknown Module';
    }

    /**
     * Get module version
     */
    public function getVersion(): string
    {
        return $this->metadata['version'] ?? '1.0.0';
    }

    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return $this->metadata['description'] ?? '';
    }

    /**
     * Check if module is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Enable module
     */
    public function enable(): bool
    {
        $this->config['enabled'] = true;
        $this->saveConfiguration();
        $this->onEnable();
        return true;
    }

    /**
     * Disable module
     */
    public function disable(): bool
    {
        $this->config['enabled'] = false;
        $this->saveConfiguration();
        $this->onDisable();
        return true;
    }

    /**
     * Install module
     */
    public function install(): bool
    {
        try {
            $this->createDatabaseTables();
            $this->createDirectories();
            $this->setDefaultPermissions();
            $this->initializeData();
            $this->onInstall();
            return true;
        } catch (\Exception $e) {
            $this->onInstallError($e);
            return false;
        }
    }

    /**
     * Uninstall module
     */
    public function uninstall(): bool
    {
        try {
            $this->onUninstall();
            $this->removeDatabaseTables();
            $this->cleanupFiles();
            $this->removePermissions();
            return true;
        } catch (\Exception $e) {
            $this->onUninstallError($e);
            return false;
        }
    }

    /**
     * Update module
     */
    public function update(string $newVersion): bool
    {
        try {
            $this->backupData();
            $this->runMigrations($newVersion);
            $this->updateConfiguration($newVersion);
            $this->onUpdate($newVersion);
            return true;
        } catch (\Exception $e) {
            $this->onUpdateError($e);
            $this->restoreBackup();
            return false;
        }
    }

    /**
     * Load module configuration
     */
    protected function loadConfiguration(): void
    {
        $configFile = $this->getConfigFilePath();
        if (file_exists($configFile)) {
            $savedConfig = require $configFile;
            $this->config = array_merge($this->config, $savedConfig);
        }
    }

    /**
     * Save module configuration
     */
    protected function saveConfiguration(): void
    {
        $configFile = $this->getConfigFilePath();
        $configDir = dirname($configFile);

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $configContent = "<?php\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($configFile, $configContent);
    }

    /**
     * Get configuration file path
     */
    protected function getConfigFilePath(): string
    {
        $moduleName = strtolower(str_replace('Module', '', basename(get_class($this))));
        return CONFIG_PATH . '/modules/' . $moduleName . '.php';
    }

    /**
     * Setup module dependencies
     */
    protected function setupDependencies(): void
    {
        foreach ($this->dependencies as $dependency) {
            if (!$this->isDependencyAvailable($dependency)) {
                throw new \Exception("Required dependency not available: {$dependency['name']}");
            }
        }
    }

    /**
     * Check if dependency is available
     */
    protected function isDependencyAvailable(array $dependency): bool
    {
        // Check if required module is installed and enabled
        // This would integrate with the PluginManager
        return true; // Placeholder
    }

    /**
     * Register module hooks
     */
    protected function registerHooks(): void
    {
        foreach ($this->hooks as $hookName => $callback) {
            // Register hook with the system
            // This would integrate with the PluginManager
        }
    }

    /**
     * Initialize module permissions
     */
    protected function initializePermissions(): void
    {
        foreach ($this->permissions as $permission) {
            // Register permission with the system
            // This would integrate with the Auth system
        }
    }

    /**
     * Setup module database
     */
    protected function setupDatabase(): void
    {
        foreach ($this->tables as $tableName => $tableSchema) {
            if (!$this->tableExists($tableName)) {
                $this->createTable($tableName, $tableSchema);
            }
        }
    }

    /**
     * Register API endpoints
     */
    protected function registerEndpoints(): void
    {
        foreach ($this->endpoints as $endpoint) {
            // Register endpoint with the Router
            // This would integrate with the Router system
        }
    }

    /**
     * Initialize workflows
     */
    protected function initializeWorkflows(): void
    {
        foreach ($this->workflows as $workflowName => $workflowConfig) {
            // Register workflow with the WorkflowEngine
            // This would integrate with the WorkflowEngine
        }
    }

    /**
     * Setup forms
     */
    protected function setupForms(): void
    {
        foreach ($this->forms as $formName => $formConfig) {
            // Register form with the form system
            // This would integrate with the form management system
        }
    }

    /**
     * Configure reports
     */
    protected function configureReports(): void
    {
        foreach ($this->reports as $reportName => $reportConfig) {
            // Register report with the reporting system
            // This would integrate with the AdvancedAnalytics system
        }
    }

    /**
     * Setup notifications
     */
    protected function setupNotifications(): void
    {
        foreach ($this->notifications as $notificationName => $notificationConfig) {
            // Register notification with the NotificationManager
            // This would integrate with the NotificationManager
        }
    }

    /**
     * Create database tables
     */
    protected function createDatabaseTables(): void
    {
        foreach ($this->tables as $tableName => $tableSchema) {
            $this->createTable($tableName, $tableSchema);
        }
    }

    /**
     * Create directories
     */
    protected function createDirectories(): void
    {
        $directories = [
            UPLOAD_PATH . '/' . strtolower($this->getName()),
            CACHE_PATH . '/' . strtolower($this->getName()),
            LOG_PATH . '/' . strtolower($this->getName())
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * Set default permissions
     */
    protected function setDefaultPermissions(): void
    {
        // Set default permissions for the module
        // This would integrate with the Auth system
    }

    /**
     * Initialize module data
     */
    protected function initializeData(): void
    {
        // Initialize default data for the module
        // This would typically insert default records into database
    }

    /**
     * Remove database tables
     */
    protected function removeDatabaseTables(): void
    {
        foreach ($this->tables as $tableName => $tableSchema) {
            $this->dropTable($tableName);
        }
    }

    /**
     * Cleanup files
     */
    protected function cleanupFiles(): void
    {
        $directories = [
            UPLOAD_PATH . '/' . strtolower($this->getName()),
            CACHE_PATH . '/' . strtolower($this->getName()),
            LOG_PATH . '/' . strtolower($this->getName())
        ];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $this->removeDirectory($directory);
            }
        }
    }

    /**
     * Remove permissions
     */
    protected function removePermissions(): void
    {
        // Remove module permissions
        // This would integrate with the Auth system
    }

    /**
     * Backup data
     */
    protected function backupData(): void
    {
        // Create backup of module data
        // This would integrate with the BackupManager
    }

    /**
     * Run migrations
     */
    protected function runMigrations(string $newVersion): void
    {
        // Run database migrations for the module
        // This would integrate with a migration system
    }

    /**
     * Update configuration
     */
    protected function updateConfiguration(string $newVersion): void
    {
        $this->config['version'] = $newVersion;
        $this->saveConfiguration();
    }

    /**
     * Restore backup
     */
    protected function restoreBackup(): void
    {
        // Restore data from backup
        // This would integrate with the BackupManager
    }

    /**
     * Check if table exists
     */
    protected function tableExists(string $tableName): bool
    {
        // Check if table exists in database
        // This would integrate with the Database system
        return false; // Placeholder
    }

    /**
     * Create table
     */
    protected function createTable(string $tableName, array $schema): void
    {
        // Create database table
        // This would integrate with the Database system
    }

    /**
     * Drop table
     */
    protected function dropTable(string $tableName): void
    {
        // Drop database table
        // This would integrate with the Database system
    }

    /**
     * Remove directory
     */
    protected function removeDirectory(string $directory): void
    {
        // Recursively remove directory
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }

    /**
     * Get module cache
     */
    public function getCache(string $key)
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * Set module cache
     */
    public function setCache(string $key, $value, int $ttl = 3600): void
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }

    /**
     * Clear module cache
     */
    public function clearCache(string $key = null): void
    {
        if ($key === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$key]);
        }
    }

    /**
     * Get module statistics
     */
    public function getStatistics(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'enabled' => $this->isEnabled(),
            'tables_count' => count($this->tables),
            'endpoints_count' => count($this->endpoints),
            'workflows_count' => count($this->workflows),
            'forms_count' => count($this->forms),
            'reports_count' => count($this->reports),
            'cache_size' => count($this->cache)
        ];
    }

    /**
     * Validate module configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Validate required configuration
        $requiredFields = $this->getRequiredConfigFields();
        foreach ($requiredFields as $field) {
            if (!isset($this->config[$field]) || empty($this->config[$field])) {
                $errors[] = "Required configuration field missing: {$field}";
            }
        }

        // Validate configuration values
        $validationErrors = $this->validateConfigValues();
        $errors = array_merge($errors, $validationErrors);

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get required configuration fields
     */
    protected function getRequiredConfigFields(): array
    {
        return ['enabled'];
    }

    /**
     * Validate configuration values
     */
    protected function validateConfigValues(): array
    {
        return []; // Override in subclasses
    }

    /**
     * Export module data
     */
    public function exportData(string $format = 'json'): string
    {
        $exportData = [
            'metadata' => $this->metadata,
            'config' => $this->config,
            'statistics' => $this->getStatistics(),
            'exported_at' => date('c')
        ];

        switch ($format) {
            case 'json':
                return json_encode($exportData, JSON_PRETTY_PRINT);
            case 'xml':
                return $this->exportToXML($exportData);
            default:
                throw new \Exception("Unsupported export format: {$format}");
        }
    }

    /**
     * Import module data
     */
    public function importData(string $data, string $format = 'json'): bool
    {
        try {
            switch ($format) {
                case 'json':
                    $importData = json_decode($data, true);
                    break;
                case 'xml':
                    $importData = $this->parseXML($data);
                    break;
                default:
                    throw new \Exception("Unsupported import format: {$format}");
            }

            if (isset($importData['config'])) {
                $this->config = array_merge($this->config, $importData['config']);
                $this->saveConfiguration();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Export to XML
     */
    protected function exportToXML(array $data): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<module>' . "\n";
        $xml .= $this->arrayToXML($data, 1);
        $xml .= '</module>' . "\n";
        return $xml;
    }

    /**
     * Parse XML
     */
    protected function parseXML(string $xml): array
    {
        // Simple XML parsing - in production, use proper XML parser
        return [];
    }

    /**
     * Convert array to XML
     */
    protected function arrayToXML(array $data, int $indent = 0): string
    {
        $xml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= $indentStr . "<{$key}>\n";
                $xml .= $this->arrayToXML($value, $indent + 1);
                $xml .= $indentStr . "</{$key}>\n";
            } else {
                $xml .= $indentStr . "<{$key}>" . htmlspecialchars($value) . "</{$key}>\n";
            }
        }

        return $xml;
    }

    /**
     * Lifecycle hooks
     */
    protected function onEnable(): void {}
    protected function onDisable(): void {}
    protected function onInstall(): void {}
    protected function onUninstall(): void {}
    protected function onUpdate(string $newVersion): void {}
    protected function onInstallError(\Exception $e): void {}
    protected function onUninstallError(\Exception $e): void {}
    protected function onUpdateError(\Exception $e): void {}

    /**
     * Get module configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set module configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->saveConfiguration();
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Get module permissions
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get module endpoints
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * Get module workflows
     */
    public function getWorkflows(): array
    {
        return $this->workflows;
    }

    /**
     * Get module forms
     */
    public function getForms(): array
    {
        return $this->forms;
    }

    /**
     * Get module reports
     */
    public function getReports(): array
    {
        return $this->reports;
    }
}

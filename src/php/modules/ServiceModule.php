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
     * Constructor - Lazy initialization
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->metadata = $this->getMetadata();

        // Mark as not fully initialized
        $this->initialized = false;
        $this->dependenciesInitialized = false;
        $this->hooksInitialized = false;
        $this->permissionsInitialized = false;
        $this->databaseInitialized = false;
        $this->endpointsInitialized = false;
        $this->workflowsInitialized = false;
        $this->formsInitialized = false;
        $this->reportsInitialized = false;
        $this->notificationsInitialized = false;
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
     * Ensure module is fully initialized
     */
    protected function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->initializeModule();
            $this->loadConfiguration();
            $this->initialized = true;
        }
    }

    /**
     * Ensure dependencies are initialized
     */
    protected function ensureDependenciesInitialized(): void
    {
        if (!$this->dependenciesInitialized) {
            $this->setupDependencies();
            $this->dependenciesInitialized = true;
        }
    }

    /**
     * Ensure hooks are initialized
     */
    protected function ensureHooksInitialized(): void
    {
        if (!$this->hooksInitialized) {
            $this->registerHooks();
            $this->hooksInitialized = true;
        }
    }

    /**
     * Ensure permissions are initialized
     */
    protected function ensurePermissionsInitialized(): void
    {
        if (!$this->permissionsInitialized) {
            $this->initializePermissions();
            $this->permissionsInitialized = true;
        }
    }

    /**
     * Ensure database is initialized
     */
    protected function ensureDatabaseInitialized(): void
    {
        if (!$this->databaseInitialized) {
            $this->setupDatabase();
            $this->databaseInitialized = true;
        }
    }

    /**
     * Ensure endpoints are initialized
     */
    protected function ensureEndpointsInitialized(): void
    {
        if (!$this->endpointsInitialized) {
            $this->registerEndpoints();
            $this->endpointsInitialized = true;
        }
    }

    /**
     * Ensure workflows are initialized
     */
    protected function ensureWorkflowsInitialized(): void
    {
        if (!$this->workflowsInitialized) {
            $this->initializeWorkflows();
            $this->workflowsInitialized = true;
        }
    }

    /**
     * Ensure forms are initialized
     */
    protected function ensureFormsInitialized(): void
    {
        if (!$this->formsInitialized) {
            $this->setupForms();
            $this->formsInitialized = true;
        }
    }

    /**
     * Ensure reports are initialized
     */
    protected function ensureReportsInitialized(): void
    {
        if (!$this->reportsInitialized) {
            $this->configureReports();
            $this->reportsInitialized = true;
        }
    }

    /**
     * Ensure notifications are initialized
     */
    protected function ensureNotificationsInitialized(): void
    {
        if (!$this->notificationsInitialized) {
            $this->setupNotifications();
            $this->notificationsInitialized = true;
        }
    }

    /**
     * Get module endpoints (lazy initialization)
     */
    public function getEndpoints(): array
    {
        $this->ensureEndpointsInitialized();
        return $this->endpoints;
    }

    /**
     * Get module workflows (lazy initialization)
     */
    public function getWorkflows(): array
    {
        $this->ensureWorkflowsInitialized();
        return $this->workflows;
    }

    /**
     * Get module forms (lazy initialization)
     */
    public function getForms(): array
    {
        $this->ensureFormsInitialized();
        return $this->forms;
    }

    /**
     * Get module reports (lazy initialization)
     */
    public function getReports(): array
    {
        $this->ensureReportsInitialized();
        return $this->reports;
    }

    /**
     * Get module permissions (lazy initialization)
     */
    public function getPermissions(): array
    {
        $this->ensurePermissionsInitialized();
        return $this->permissions;
    }

    /**
     * Get module dependencies (lazy initialization)
     */
    public function getDependencies(): array
    {
        $this->ensureDependenciesInitialized();
        return $this->dependencies;
    }

    /**
     * Enable module (lazy initialization)
     */
    public function enable(): bool
    {
        $this->ensureInitialized();
        $this->config['enabled'] = true;
        $this->saveConfiguration();
        $this->onEnable();
        return true;
    }

    /**
     * Disable module (lazy initialization)
     */
    public function disable(): bool
    {
        $this->ensureInitialized();
        $this->config['enabled'] = false;
        $this->saveConfiguration();
        $this->onDisable();
        return true;
    }

    /**
     * Install module (lazy initialization)
     */
    public function install(): bool
    {
        try {
            $this->ensureInitialized();
            $this->ensureDatabaseInitialized();
            $this->createDatabaseTables();
            $this->createDirectories();
            $this->ensurePermissionsInitialized();
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
     * Get module statistics (lazy initialization)
     */
    public function getStatistics(): array
    {
        $this->ensureInitialized();
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'enabled' => $this->isEnabled(),
            'tables_count' => count($this->tables),
            'endpoints_count' => count($this->getEndpoints()),
            'workflows_count' => count($this->getWorkflows()),
            'forms_count' => count($this->getForms()),
            'reports_count' => count($this->getReports()),
            'cache_size' => count($this->cache),
            'fully_initialized' => $this->initialized
        ];
    }

    /**
     * Validate module configuration (lazy initialization)
     */
    public function validateConfiguration(): array
    {
        $this->ensureInitialized();
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
     * Export module data (lazy initialization)
     */
    public function exportData(string $format = 'json'): string
    {
        $this->ensureInitialized();
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
     * Force full initialization
     */
    public function initializeFully(): void
    {
        $this->ensureInitialized();
        $this->ensureDependenciesInitialized();
        $this->ensureHooksInitialized();
        $this->ensurePermissionsInitialized();
        $this->ensureDatabaseInitialized();
        $this->ensureEndpointsInitialized();
        $this->ensureWorkflowsInitialized();
        $this->ensureFormsInitialized();
        $this->ensureReportsInitialized();
        $this->ensureNotificationsInitialized();
    }

    /**
     * Check if module is fully initialized
     */
    public function isFullyInitialized(): bool
    {
        return $this->initialized &&
               $this->dependenciesInitialized &&
               $this->hooksInitialized &&
               $this->permissionsInitialized &&
               $this->databaseInitialized &&
               $this->endpointsInitialized &&
               $this->workflowsInitialized &&
               $this->formsInitialized &&
               $this->reportsInitialized &&
               $this->notificationsInitialized;
    }
}

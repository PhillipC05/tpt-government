<?php
/**
 * TPT Government Platform - Configuration Management Controller
 *
 * Web interface for managing application configuration settings.
 * Provides real-time configuration editing, validation, and backup/restore.
 */

namespace Core\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Configuration\ConfigurationManager;
use Core\AuditTrailManager;
use Core\BackupManager;
use Core\InputValidator;

class ConfigurationManagementController extends Controller
{
    /**
     * Configuration manager instance
     */
    private ConfigurationManager $configManager;

    /**
     * Audit trail manager
     */
    private AuditTrailManager $auditManager;

    /**
     * Backup manager
     */
    private BackupManager $backupManager;

    /**
     * Input validator
     */
    private InputValidator $validator;

    /**
     * Constructor
     */
    public function __construct(
        ConfigurationManager $configManager,
        AuditTrailManager $auditManager,
        BackupManager $backupManager,
        InputValidator $validator
    ) {
        $this->configManager = $configManager;
        $this->auditManager = $auditManager;
        $this->backupManager = $backupManager;
        $this->validator = $validator;
    }

    /**
     * Display configuration management dashboard
     */
    public function index(): void
    {
        $categories = $this->configManager->getConfigurationCategories();
        $stats = $this->getConfigurationStats();

        $this->render('config/dashboard', [
            'categories' => $categories,
            'stats' => $stats,
            'recent_changes' => $this->auditManager->getRecentConfigurationChanges(10)
        ]);
    }

    /**
     * Get configuration for a specific category
     */
    public function getCategory(Request $request): void
    {
        $category = $request->getParam('category');

        if (!$this->configManager->categoryExists($category)) {
            $this->response->json(['error' => 'Configuration category not found'], 404);
            return;
        }

        $config = $this->configManager->getCategoryConfiguration($category);
        $schema = $this->configManager->getCategorySchema($category);

        $this->response->json([
            'category' => $category,
            'config' => $config,
            'schema' => $schema,
            'last_modified' => $this->configManager->getLastModified($category)
        ]);
    }

    /**
     * Update configuration for a specific category
     */
    public function updateCategory(Request $request): void
    {
        $category = $request->getParam('category');
        $newConfig = $request->getJsonBody();

        if (!$this->configManager->categoryExists($category)) {
            $this->response->json(['error' => 'Configuration category not found'], 404);
            return;
        }

        // Validate configuration
        $validationResult = $this->validateConfiguration($category, $newConfig);
        if (!$validationResult['valid']) {
            $this->response->json([
                'error' => 'Configuration validation failed',
                'details' => $validationResult['errors']
            ], 400);
            return;
        }

        // Create backup before changes
        $backupId = $this->createConfigurationBackup($category);

        try {
            // Update configuration
            $oldConfig = $this->configManager->getCategoryConfiguration($category);
            $this->configManager->updateCategoryConfiguration($category, $newConfig);

            // Log the change
            $this->auditManager->logConfigurationChange([
                'category' => $category,
                'old_config' => $oldConfig,
                'new_config' => $newConfig,
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->getUserAgent(),
                'backup_id' => $backupId
            ]);

            $this->response->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'backup_id' => $backupId
            ]);

        } catch (\Exception $e) {
            // Restore from backup on error
            $this->restoreConfigurationBackup($backupId);

            $this->response->json([
                'error' => 'Failed to update configuration',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset configuration category to defaults
     */
    public function resetCategory(Request $request): void
    {
        $category = $request->getParam('category');

        if (!$this->configManager->categoryExists($category)) {
            $this->response->json(['error' => 'Configuration category not found'], 404);
            return;
        }

        // Create backup before reset
        $backupId = $this->createConfigurationBackup($category);

        try {
            $oldConfig = $this->configManager->getCategoryConfiguration($category);
            $this->configManager->resetCategoryToDefaults($category);

            // Log the reset
            $this->auditManager->logConfigurationChange([
                'category' => $category,
                'action' => 'reset',
                'old_config' => $oldConfig,
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $request->getClientIp(),
                'backup_id' => $backupId
            ]);

            $this->response->json([
                'success' => true,
                'message' => 'Configuration reset to defaults',
                'backup_id' => $backupId
            ]);

        } catch (\Exception $e) {
            $this->response->json([
                'error' => 'Failed to reset configuration',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration change history
     */
    public function getHistory(Request $request): void
    {
        $category = $request->getParam('category');
        $limit = (int) ($request->getQueryParam('limit') ?? 50);
        $offset = (int) ($request->getQueryParam('offset') ?? 0);

        $history = $this->auditManager->getConfigurationHistory($category, $limit, $offset);

        $this->response->json([
            'history' => $history,
            'total' => $this->auditManager->getConfigurationHistoryCount($category)
        ]);
    }

    /**
     * Restore configuration from backup
     */
    public function restoreBackup(Request $request): void
    {
        $backupId = $request->getParam('backup_id');

        if (!$this->backupManager->backupExists($backupId)) {
            $this->response->json(['error' => 'Backup not found'], 404);
            return;
        }

        try {
            // Create backup of current state before restore
            $currentBackupId = $this->backupManager->createBackup([
                'type' => 'configuration_pre_restore',
                'description' => 'Backup before restoring configuration from backup: ' . $backupId
            ]);

            $this->backupManager->restoreBackup($backupId);

            // Log the restore
            $this->auditManager->logConfigurationChange([
                'action' => 'restore',
                'backup_id' => $backupId,
                'current_backup_id' => $currentBackupId,
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $request->getClientIp()
            ]);

            $this->response->json([
                'success' => true,
                'message' => 'Configuration restored from backup',
                'current_backup_id' => $currentBackupId
            ]);

        } catch (\Exception $e) {
            $this->response->json([
                'error' => 'Failed to restore configuration',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export configuration
     */
    public function exportConfiguration(Request $request): void
    {
        $format = $request->getQueryParam('format', 'json');
        $categories = $request->getQueryParam('categories');

        if ($categories) {
            $categories = explode(',', $categories);
            $config = [];
            foreach ($categories as $category) {
                if ($this->configManager->categoryExists($category)) {
                    $config[$category] = $this->configManager->getCategoryConfiguration($category);
                }
            }
        } else {
            $config = $this->configManager->getAllConfiguration();
        }

        $filename = 'configuration_export_' . date('Y-m-d_H-i-s');

        if ($format === 'json') {
            $this->response->setHeader('Content-Type', 'application/json');
            $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.json"');
            $this->response->setContent(json_encode($config, JSON_PRETTY_PRINT));
        } elseif ($format === 'yaml') {
            $this->response->setHeader('Content-Type', 'application/x-yaml');
            $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.yaml"');
            $this->response->setContent($this->arrayToYaml($config));
        }

        // Log the export
        $this->auditManager->logConfigurationChange([
            'action' => 'export',
            'format' => $format,
            'categories' => $categories,
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => $request->getClientIp()
        ]);
    }

    /**
     * Import configuration
     */
    public function importConfiguration(Request $request): void
    {
        $file = $request->getFile('config_file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->response->json(['error' => 'No valid configuration file uploaded'], 400);
            return;
        }

        try {
            $content = file_get_contents($file['tmp_name']);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->response->json(['error' => 'Invalid JSON format'], 400);
                return;
            }

            // Create backup before import
            $backupId = $this->backupManager->createBackup([
                'type' => 'configuration_pre_import',
                'description' => 'Backup before importing configuration'
            ]);

            // Validate imported configuration
            $validationResult = $this->validateImportedConfiguration($config);
            if (!$validationResult['valid']) {
                $this->response->json([
                    'error' => 'Configuration validation failed',
                    'details' => $validationResult['errors']
                ], 400);
                return;
            }

            // Import configuration
            $this->configManager->importConfiguration($config);

            // Log the import
            $this->auditManager->logConfigurationChange([
                'action' => 'import',
                'backup_id' => $backupId,
                'user_id' => $this->getCurrentUserId(),
                'ip_address' => $request->getClientIp()
            ]);

            $this->response->json([
                'success' => true,
                'message' => 'Configuration imported successfully',
                'backup_id' => $backupId
            ]);

        } catch (\Exception $e) {
            $this->response->json([
                'error' => 'Failed to import configuration',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration statistics
     */
    private function getConfigurationStats(): array
    {
        return [
            'total_categories' => count($this->configManager->getConfigurationCategories()),
            'total_settings' => $this->configManager->getTotalSettingsCount(),
            'recent_changes' => $this->auditManager->getRecentConfigurationChangesCount(24), // Last 24 hours
            'backups_count' => $this->backupManager->getConfigurationBackupsCount(),
            'last_backup' => $this->backupManager->getLastConfigurationBackupTime()
        ];
    }

    /**
     * Validate configuration
     */
    private function validateConfiguration(string $category, array $config): array
    {
        $schema = $this->configManager->getCategorySchema($category);
        return $this->validator->validateConfiguration($config, $schema);
    }

    /**
     * Validate imported configuration
     */
    private function validateImportedConfiguration(array $config): array
    {
        $errors = [];

        foreach ($config as $category => $categoryConfig) {
            if (!$this->configManager->categoryExists($category)) {
                $errors[] = "Unknown configuration category: {$category}";
                continue;
            }

            $validation = $this->validateConfiguration($category, $categoryConfig);
            if (!$validation['valid']) {
                $errors = array_merge($errors, array_map(
                    fn($error) => "{$category}: {$error}",
                    $validation['errors']
                ));
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Create configuration backup
     */
    private function createConfigurationBackup(string $category): string
    {
        return $this->backupManager->createBackup([
            'type' => 'configuration_category',
            'category' => $category,
            'description' => "Backup of {$category} configuration before changes"
        ]);
    }

    /**
     * Restore configuration backup
     */
    private function restoreConfigurationBackup(string $backupId): void
    {
        $this->backupManager->restoreBackup($backupId);
    }

    /**
     * Get current user ID (placeholder - integrate with auth system)
     */
    private function getCurrentUserId(): ?int
    {
        // TODO: Integrate with authentication system
        return 1; // Placeholder
    }

    /**
     * Convert array to YAML (simple implementation)
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $indentStr . $key . ':' . PHP_EOL;
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $indentStr . $key . ': ' . (is_string($value) ? '"' . $value . '"' : $value) . PHP_EOL;
            }
        }

        return $yaml;
    }

    /**
     * Render view (placeholder - integrate with template system)
     */
    private function render(string $view, array $data = []): void
    {
        // TODO: Integrate with template system
        $this->response->html('<h1>Configuration Management</h1><p>Web interface coming soon...</p>');
    }
}

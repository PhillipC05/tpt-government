<?php
/**
 * TPT Government Platform - Base Plugin Class
 *
 * Base class for all plugins. Provides common functionality and interface.
 */

namespace Core;

abstract class Plugin
{
    /**
     * Plugin name
     */
    protected string $name;

    /**
     * Plugin version
     */
    protected string $version = '1.0.0';

    /**
     * Plugin description
     */
    protected string $description = '';

    /**
     * Plugin author
     */
    protected string $author = '';

    /**
     * Required platform version
     */
    protected string $requires = '1.0.0';

    /**
     * Plugin settings
     */
    protected array $settings = [];

    /**
     * Plugin manager instance
     */
    protected ?PluginManager $pluginManager = null;

    /**
     * Database instance
     */
    protected ?Database $database = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = $this->getPluginName();
        $this->initialize();
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    protected function initialize(): void
    {
        // Override in child classes
    }

    /**
     * Get plugin name
     *
     * @return string Plugin name
     */
    abstract public function getPluginName(): string;

    /**
     * Activate plugin
     *
     * @return bool True if activation successful
     */
    public function activate(): bool
    {
        // Create plugin settings table if needed
        $this->createSettingsTable();

        // Run activation tasks
        return $this->onActivate();
    }

    /**
     * Deactivate plugin
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->onDeactivate();
    }

    /**
     * Register plugin hooks
     *
     * @param PluginManager $pluginManager Plugin manager instance
     * @return void
     */
    public function registerHooks(PluginManager $pluginManager): void
    {
        $this->pluginManager = $pluginManager;
        $this->database = $this->getDatabase();

        $this->onRegisterHooks();
    }

    /**
     * Get plugin version
     *
     * @return string Plugin version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get plugin description
     *
     * @return string Plugin description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get plugin author
     *
     * @return string Plugin author
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Get required platform version
     *
     * @return string Required platform version
     */
    public function getRequiredVersion(): string
    {
        return $this->requires;
    }

    /**
     * Get plugin settings
     *
     * @return array Plugin settings
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Set plugin setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return void
     */
    public function setSetting(string $key, $value): void
    {
        $this->settings[$key] = $value;

        // Save to database if available
        if ($this->database) {
            $this->saveSetting($key, $value);
        }
    }

    /**
     * Get plugin setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function getSetting(string $key, $default = null)
    {
        // Check memory cache first
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }

        // Load from database if available
        if ($this->database) {
            $value = $this->loadSetting($key);
            if ($value !== null) {
                $this->settings[$key] = $value;
                return $value;
            }
        }

        return $default;
    }

    /**
     * Register a hook
     *
     * @param string $hookName Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @return void
     */
    protected function registerHook(string $hookName, callable $callback, int $priority = 10): void
    {
        if ($this->pluginManager) {
            $this->pluginManager->registerHook($hookName, $callback, $priority);
        }
    }

    /**
     * Execute a hook
     *
     * @param string $hookName Hook name
     * @param mixed $data Hook data
     * @return mixed Modified data
     */
    protected function executeHook(string $hookName, $data = null)
    {
        if ($this->pluginManager) {
            return $this->pluginManager->executeHook($hookName, $data);
        }
        return $data;
    }

    /**
     * Get database instance
     *
     * @return Database|null Database instance
     */
    protected function getDatabase(): ?Database
    {
        // Try to get from plugin manager or create new instance
        if ($this->pluginManager && method_exists($this->pluginManager, 'getDatabase')) {
            return $this->pluginManager->getDatabase();
        }

        // Fallback: try to create database instance from config
        $configFile = CONFIG_PATH . '/database.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            return new Database($config);
        }

        return null;
    }

    /**
     * Create plugin settings table
     *
     * @return void
     */
    private function createSettingsTable(): void
    {
        if (!$this->database) {
            return;
        }

        try {
            $tableName = 'plugin_' . strtolower($this->name) . '_settings';

            // Create settings table if it doesn't exist
            $this->database->query("
                CREATE TABLE IF NOT EXISTS {$tableName} (
                    id SERIAL PRIMARY KEY,
                    setting_key VARCHAR(255) NOT NULL UNIQUE,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Load existing settings
            $this->loadSettings();

        } catch (\Exception $e) {
            error_log("Failed to create settings table for plugin {$this->name}: " . $e->getMessage());
        }
    }

    /**
     * Load plugin settings from database
     *
     * @return void
     */
    private function loadSettings(): void
    {
        if (!$this->database) {
            return;
        }

        try {
            $tableName = 'plugin_' . strtolower($this->name) . '_settings';
            $settings = $this->database->select("SELECT setting_key, setting_value FROM {$tableName}");

            foreach ($settings as $setting) {
                $value = json_decode($setting['setting_value'], true);
                if ($value === null && !is_array($setting['setting_value'])) {
                    $value = $setting['setting_value'];
                }
                $this->settings[$setting['setting_key']] = $value;
            }
        } catch (\Exception $e) {
            // Settings table may not exist yet
        }
    }

    /**
     * Save setting to database
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return void
     */
    private function saveSetting(string $key, $value): void
    {
        if (!$this->database) {
            return;
        }

        try {
            $tableName = 'plugin_' . strtolower($this->name) . '_settings';
            $jsonValue = is_array($value) ? json_encode($value) : $value;

            $this->database->query(
                "INSERT INTO {$tableName} (setting_key, setting_value, updated_at)
                 VALUES (?, ?, CURRENT_TIMESTAMP)
                 ON CONFLICT (setting_key) DO UPDATE SET
                 setting_value = EXCLUDED.setting_value,
                 updated_at = CURRENT_TIMESTAMP",
                [$key, $jsonValue]
            );
        } catch (\Exception $e) {
            error_log("Failed to save setting {$key} for plugin {$this->name}: " . $e->getMessage());
        }
    }

    /**
     * Load setting from database
     *
     * @param string $key Setting key
     * @return mixed|null Setting value or null
     */
    private function loadSetting(string $key)
    {
        if (!$this->database) {
            return null;
        }

        try {
            $tableName = 'plugin_' . strtolower($this->name) . '_settings';
            $result = $this->database->selectOne(
                "SELECT setting_value FROM {$tableName} WHERE setting_key = ?",
                [$key]
            );

            if ($result) {
                $value = json_decode($result['setting_value'], true);
                return $value === null && !is_array($result['setting_value']) ? $result['setting_value'] : $value;
            }
        } catch (\Exception $e) {
            // Setting may not exist
        }

        return null;
    }

    /**
     * Called when plugin is activated
     *
     * @return bool True if activation successful
     */
    protected function onActivate(): bool
    {
        return true;
    }

    /**
     * Called when plugin is deactivated
     *
     * @return void
     */
    protected function onDeactivate(): void
    {
        // Override in child classes
    }

    /**
     * Called to register plugin hooks
     *
     * @return void
     */
    protected function onRegisterHooks(): void
    {
        // Override in child classes
    }

    /**
     * Add admin menu item
     *
     * @param string $title Menu title
     * @param string $url Menu URL
     * @param string $icon Menu icon
     * @param int $priority Menu priority
     * @return void
     */
    protected function addAdminMenu(string $title, string $url, string $icon = '', int $priority = 10): void
    {
        $this->registerHook('admin_menu', function($menu) use ($title, $url, $icon, $priority) {
            $menu[] = [
                'title' => $title,
                'url' => $url,
                'icon' => $icon,
                'priority' => $priority,
                'plugin' => $this->name
            ];
            return $menu;
        }, $priority);
    }

    /**
     * Add API endpoint
     *
     * @param string $path API path
     * @param callable $handler API handler
     * @param array $middleware Middleware array
     * @return void
     */
    protected function addApiEndpoint(string $path, callable $handler, array $middleware = []): void
    {
        $this->registerHook('api_routes', function($routes) use ($path, $handler, $middleware) {
            $routes[] = [
                'path' => $path,
                'handler' => $handler,
                'middleware' => $middleware,
                'plugin' => $this->name
            ];
            return $routes;
        });
    }

    /**
     * Add dashboard widget
     *
     * @param string $title Widget title
     * @param callable $callback Widget callback
     * @param int $priority Widget priority
     * @return void
     */
    protected function addDashboardWidget(string $title, callable $callback, int $priority = 10): void
    {
        $this->registerHook('dashboard_widgets', function($widgets) use ($title, $callback, $priority) {
            $widgets[] = [
                'title' => $title,
                'callback' => $callback,
                'priority' => $priority,
                'plugin' => $this->name
            ];
            return $widgets;
        }, $priority);
    }
}

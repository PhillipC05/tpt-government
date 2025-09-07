<?php
/**
 * TPT Government Platform - Plugin Manager
 *
 * Manages loading, activation, and execution of plugins.
 * Provides a modular architecture for extending platform functionality.
 */

namespace Core;

class PluginManager
{
    /**
     * Plugin directory
     */
    private string $pluginDir;

    /**
     * Loaded plugins
     */
    private array $plugins = [];

    /**
     * Active plugins
     */
    private array $activePlugins = [];

    /**
     * Plugin hooks
     */
    private array $hooks = [];

    /**
     * Database instance
     */
    private ?Database $database = null;

    /**
     * Constructor
     */
    public function __construct(string $pluginDir = SRC_PATH . '/plugins', ?Database $database = null)
    {
        $this->pluginDir = rtrim($pluginDir, '/\\');
        $this->database = $database;

        // Ensure plugin directory exists
        if (!is_dir($this->pluginDir)) {
            mkdir($this->pluginDir, 0755, true);
        }
    }

    /**
     * Load all available plugins
     *
     * @return void
     */
    public function loadPlugins(): void
    {
        $pluginFiles = glob($this->pluginDir . '/*/*.php');

        foreach ($pluginFiles as $pluginFile) {
            $this->loadPlugin($pluginFile);
        }

        // Load active plugins from database
        $this->loadActivePlugins();
    }

    /**
     * Load a specific plugin
     *
     * @param string $pluginFile Plugin file path
     * @return bool True if loaded successfully
     */
    public function loadPlugin(string $pluginFile): bool
    {
        if (!file_exists($pluginFile)) {
            return false;
        }

        $pluginName = basename(dirname($pluginFile));
        $className = $this->getPluginClassName($pluginFile);

        if (!$className) {
            return false;
        }

        try {
            require_once $pluginFile;

            if (class_exists($className)) {
                $plugin = new $className();
                $this->plugins[$pluginName] = $plugin;

                // Register plugin hooks
                if (method_exists($plugin, 'registerHooks')) {
                    $plugin->registerHooks($this);
                }

                return true;
            }
        } catch (\Exception $e) {
            error_log("Failed to load plugin {$pluginName}: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get plugin class name from file
     *
     * @param string $pluginFile Plugin file path
     * @return string|null Class name or null if not found
     */
    private function getPluginClassName(string $pluginFile): ?string
    {
        $content = file_get_contents($pluginFile);
        $pattern = '/class\s+(\w+)\s+extends\s+Plugin/';

        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Load active plugins from database
     *
     * @return void
     */
    private function loadActivePlugins(): void
    {
        if (!$this->database) {
            return;
        }

        try {
            $activePlugins = $this->database->select(
                'SELECT plugin_name FROM active_plugins WHERE active = true'
            );

            foreach ($activePlugins as $plugin) {
                $pluginName = $plugin['plugin_name'];
                if (isset($this->plugins[$pluginName])) {
                    $this->activePlugins[$pluginName] = $this->plugins[$pluginName];
                }
            }
        } catch (\Exception $e) {
            // Plugins table may not exist yet
        }
    }

    /**
     * Activate a plugin
     *
     * @param string $pluginName Plugin name
     * @return bool True if activated successfully
     */
    public function activatePlugin(string $pluginName): bool
    {
        if (!isset($this->plugins[$pluginName])) {
            return false;
        }

        try {
            $plugin = $this->plugins[$pluginName];

            // Call plugin activation method
            if (method_exists($plugin, 'activate')) {
                $result = $plugin->activate();
                if ($result === false) {
                    return false;
                }
            }

            // Add to active plugins
            $this->activePlugins[$pluginName] = $plugin;

            // Save to database
            if ($this->database) {
                $this->database->insert('active_plugins', [
                    'plugin_name' => $pluginName,
                    'active' => true,
                    'activated_at' => date('Y-m-d H:i:s')
                ], true); // Upsert
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to activate plugin {$pluginName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate a plugin
     *
     * @param string $pluginName Plugin name
     * @return bool True if deactivated successfully
     */
    public function deactivatePlugin(string $pluginName): bool
    {
        if (!isset($this->activePlugins[$pluginName])) {
            return false;
        }

        try {
            $plugin = $this->activePlugins[$pluginName];

            // Call plugin deactivation method
            if (method_exists($plugin, 'deactivate')) {
                $plugin->deactivate();
            }

            // Remove from active plugins
            unset($this->activePlugins[$pluginName]);

            // Update database
            if ($this->database) {
                $this->database->update('active_plugins', [
                    'active' => false,
                    'deactivated_at' => date('Y-m-d H:i:s')
                ], ['plugin_name' => $pluginName]);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to deactivate plugin {$pluginName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register a hook
     *
     * @param string $hookName Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority (lower numbers execute first)
     * @return void
     */
    public function registerHook(string $hookName, callable $callback, int $priority = 10): void
    {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }

        $this->hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort by priority
        usort($this->hooks[$hookName], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Execute a hook
     *
     * @param string $hookName Hook name
     * @param mixed $data Data to pass to hook callbacks
     * @return mixed Modified data
     */
    public function executeHook(string $hookName, $data = null)
    {
        if (!isset($this->hooks[$hookName])) {
            return $data;
        }

        foreach ($this->hooks[$hookName] as $hook) {
            try {
                $data = call_user_func($hook['callback'], $data);
            } catch (\Exception $e) {
                error_log("Hook execution error for {$hookName}: " . $e->getMessage());
            }
        }

        return $data;
    }

    /**
     * Check if a plugin is active
     *
     * @param string $pluginName Plugin name
     * @return bool True if active
     */
    public function isPluginActive(string $pluginName): bool
    {
        return isset($this->activePlugins[$pluginName]);
    }

    /**
     * Get a plugin instance
     *
     * @param string $pluginName Plugin name
     * @return mixed|null Plugin instance or null
     */
    public function getPlugin(string $pluginName)
    {
        return $this->plugins[$pluginName] ?? null;
    }

    /**
     * Get all loaded plugins
     *
     * @return array Plugin list
     */
    public function getPlugins(): array
    {
        $plugins = [];
        foreach ($this->plugins as $name => $plugin) {
            $plugins[$name] = [
                'name' => $name,
                'active' => isset($this->activePlugins[$name]),
                'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : '1.0.0',
                'description' => method_exists($plugin, 'getDescription') ? $plugin->getDescription() : ''
            ];
        }
        return $plugins;
    }

    /**
     * Get active plugins
     *
     * @return array Active plugin instances
     */
    public function getActivePlugins(): array
    {
        return $this->activePlugins;
    }

    /**
     * Install a plugin from file
     *
     * @param string $zipFile Path to plugin zip file
     * @return array Result with success/error information
     */
    public function installPlugin(string $zipFile): array
    {
        if (!file_exists($zipFile) || !class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'Zip file not found or ZipArchive not available'];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['success' => false, 'error' => 'Cannot open zip file'];
        }

        // Extract to temporary directory
        $tempDir = sys_get_temp_dir() . '/tpt_plugin_' . uniqid();
        mkdir($tempDir);

        $zip->extractTo($tempDir);
        $zip->close();

        // Find plugin file
        $pluginFiles = glob($tempDir . '/*/*.php');
        if (empty($pluginFiles)) {
            $this->removeDirectory($tempDir);
            return ['success' => false, 'error' => 'No plugin file found in zip'];
        }

        $pluginFile = $pluginFiles[0];
        $pluginName = basename(dirname($pluginFile));

        // Move to plugins directory
        $pluginDir = $this->pluginDir . '/' . $pluginName;
        if (is_dir($pluginDir)) {
            $this->removeDirectory($pluginDir);
        }

        rename(dirname($pluginFile), $pluginDir);
        $this->removeDirectory($tempDir);

        // Load the plugin
        $newPluginFile = $pluginDir . '/' . basename($pluginFile);
        if ($this->loadPlugin($newPluginFile)) {
            return [
                'success' => true,
                'plugin_name' => $pluginName,
                'message' => 'Plugin installed successfully'
            ];
        }

        return ['success' => false, 'error' => 'Failed to load installed plugin'];
    }

    /**
     * Uninstall a plugin
     *
     * @param string $pluginName Plugin name
     * @return bool True if uninstalled successfully
     */
    public function uninstallPlugin(string $pluginName): bool
    {
        // Deactivate first
        if ($this->isPluginActive($pluginName)) {
            $this->deactivatePlugin($pluginName);
        }

        // Remove plugin files
        $pluginDir = $this->pluginDir . '/' . $pluginName;
        if (is_dir($pluginDir)) {
            $this->removeDirectory($pluginDir);
        }

        // Remove from loaded plugins
        unset($this->plugins[$pluginName]);

        // Remove from database
        if ($this->database) {
            $this->database->delete('active_plugins', ['plugin_name' => $pluginName]);
        }

        return true;
    }

    /**
     * Remove a directory recursively
     *
     * @param string $dir Directory path
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Get plugin directory
     *
     * @return string Plugin directory path
     */
    public function getPluginDir(): string
    {
        return $this->pluginDir;
    }
}

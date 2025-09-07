<?php
/**
 * TPT Government Platform - Autoloader
 *
 * Custom autoloader for the government platform.
 * Implements PSR-4 autoloading without external dependencies.
 */

namespace Core;

class Autoloader
{
    /**
     * Base directory for source files
     */
    private string $baseDir;

    /**
     * Namespace to directory mapping
     */
    private array $namespaces = [];

    /**
     * Constructor
     *
     * @param string $baseDir Base directory for source files
     */
    public function __construct(string $baseDir = SRC_PATH . '/php')
    {
        $this->baseDir = rtrim($baseDir, '/\\');

        // Register default namespace mappings
        $this->addNamespace('Core', $this->baseDir . '/core');
        $this->addNamespace('Modules', $this->baseDir . '/modules');
        $this->addNamespace('Api', $this->baseDir . '/api');
    }

    /**
     * Add a namespace to directory mapping
     *
     * @param string $namespace The namespace prefix
     * @param string $directory The directory path
     * @return void
     */
    public function addNamespace(string $namespace, string $directory): void
    {
        $this->namespaces[$namespace] = rtrim($directory, '/\\');
    }

    /**
     * Register the autoloader
     *
     * @return void
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Unregister the autoloader
     *
     * @return void
     */
    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Load a class file
     *
     * @param string $className The fully qualified class name
     * @return bool True if the class was loaded, false otherwise
     */
    public function loadClass(string $className): bool
    {
        // Convert namespace separators to directory separators
        $className = ltrim($className, '\\');

        // Find the matching namespace
        foreach ($this->namespaces as $namespace => $directory) {
            if (strpos($className, $namespace) === 0) {
                $relativeClass = substr($className, strlen($namespace));
                $relativeClass = ltrim($relativeClass, '\\');
                $filePath = $directory . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                if ($this->loadFile($filePath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Load a PHP file
     *
     * @param string $filePath The file path to load
     * @return bool True if the file was loaded, false otherwise
     */
    private function loadFile(string $filePath): bool
    {
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }

        return false;
    }

    /**
     * Get all registered namespaces
     *
     * @return array Array of namespace => directory mappings
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Check if a namespace is registered
     *
     * @param string $namespace The namespace to check
     * @return bool True if registered, false otherwise
     */
    public function hasNamespace(string $namespace): bool
    {
        return isset($this->namespaces[$namespace]);
    }
}

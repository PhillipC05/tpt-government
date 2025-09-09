<?php
/**
 * TPT Government Platform - Database Service Provider
 *
 * Service provider for database-related services.
 */

namespace Core\DependencyInjection;

use Core\Database;
use Core\DatabaseOptimized;
use Core\Interfaces\CacheInterface;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services with the container
     *
     * @param Container $container Dependency injection container
     * @return void
     */
    public function register(Container $container): void
    {
        // Register database configuration
        $container->singleton('database.config', function ($container) {
            $configFile = CONFIG_PATH . '/database.php';
            if (file_exists($configFile)) {
                return require $configFile;
            }
            return [
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'tpt_gov',
                'username' => 'postgres',
                'password' => '',
                'charset' => 'utf8',
                'options' => [],
                'pool_size' => 10
            ];
        });

        // Register main database connection
        $container->singleton('database', function ($container) {
            $config = $container->get('database.config');
            $cache = null;
            try {
                $cache = $container->get(CacheInterface::class);
            } catch (\Exception $e) {
                // Cache not available, continue without it
            }
            return new DatabaseOptimized($config, $cache);
        });

        // Register legacy database alias for backward compatibility
        $container->singleton('database.legacy', function ($container) {
            $config = $container->get('database.config');
            return new Database($config);
        });

        // Register database alias
        $container->alias(Database::class, 'database');
        $container->alias('db', 'database');
    }

    /**
     * Get the services provided by this provider
     *
     * @return array Array of service IDs provided by this provider
     */
    public function provides(): array
    {
        return [
            'database.config',
            'database',
            Database::class,
            'db'
        ];
    }

    /**
     * Check if this provider is deferred (lazy-loaded)
     *
     * @return bool True if provider is deferred
     */
    public function isDeferred(): bool
    {
        return false;
    }
}

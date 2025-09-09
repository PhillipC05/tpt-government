<?php
/**
 * TPT Government Platform - Service Provider Interface
 *
 * Interface for service providers that register services with the DI container.
 */

namespace Core\DependencyInjection;

interface ServiceProviderInterface
{
    /**
     * Register services with the container
     *
     * @param Container $container Dependency injection container
     * @return void
     */
    public function register(Container $container): void;

    /**
     * Get the services provided by this provider
     *
     * @return array Array of service IDs provided by this provider
     */
    public function provides(): array;

    /**
     * Check if this provider is deferred (lazy-loaded)
     *
     * @return bool True if provider is deferred
     */
    public function isDeferred(): bool;
}

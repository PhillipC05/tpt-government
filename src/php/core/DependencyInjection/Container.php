<?php
/**
 * TPT Government Platform - Dependency Injection Container
 *
 * PSR-11 compliant dependency injection container for managing application services.
 */

namespace Core\DependencyInjection;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Exception;

class Container implements ContainerInterface
{
    /**
     * Service definitions
     */
    private array $definitions = [];

    /**
     * Service instances
     */
    private array $instances = [];

    /**
     * Service providers
     */
    private array $providers = [];

    /**
     * Aliases for services
     */
    private array $aliases = [];

    /**
     * Constructor
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions;
    }

    /**
     * Register a service definition
     *
     * @param string $id Service identifier
     * @param mixed $definition Service definition (closure, class name, or instance)
     * @param bool $shared Whether the service should be shared (singleton)
     * @return self
     */
    public function set(string $id, $definition, bool $shared = true): self
    {
        $this->definitions[$id] = [
            'definition' => $definition,
            'shared' => $shared
        ];
        return $this;
    }

    /**
     * Register a shared service (singleton)
     *
     * @param string $id Service identifier
     * @param mixed $definition Service definition
     * @return self
     */
    public function singleton(string $id, $definition): self
    {
        return $this->set($id, $definition, true);
    }

    /**
     * Register a factory service (new instance each time)
     *
     * @param string $id Service identifier
     * @param mixed $definition Service definition
     * @return self
     */
    public function factory(string $id, $definition): self
    {
        return $this->set($id, $definition, false);
    }

    /**
     * Register a service provider
     *
     * @param ServiceProviderInterface $provider Service provider
     * @return self
     */
    public function register(ServiceProviderInterface $provider): self
    {
        $provider->register($this);
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Create an alias for a service
     *
     * @param string $alias Alias name
     * @param string $id Service identifier
     * @return self
     */
    public function alias(string $alias, string $id): self
    {
        $this->aliases[$alias] = $id;
        return $this;
    }

    /**
     * Resolve a service alias
     *
     * @param string $id Service identifier
     * @return string Resolved identifier
     */
    private function resolveAlias(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }

    /**
     * Check if a service exists
     *
     * @param string $id Service identifier
     * @return bool True if service exists
     */
    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);
        return isset($this->definitions[$id]) || isset($this->instances[$id]);
    }

    /**
     * Get a service instance
     *
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws NotFoundExceptionInterface If service not found
     */
    public function get(string $id)
    {
        $id = $this->resolveAlias($id);

        // Return existing instance if it's shared
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is defined
        if (!isset($this->definitions[$id])) {
            throw new class("Service '{$id}' not found") extends Exception implements NotFoundExceptionInterface {};
        }

        $definition = $this->definitions[$id];
        $instance = $this->resolve($definition['definition']);

        // Store instance if it's shared
        if ($definition['shared']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Resolve a service definition
     *
     * @param mixed $definition Service definition
     * @return mixed Resolved service instance
     */
    private function resolve($definition)
    {
        if (is_callable($definition)) {
            return $definition($this);
        }

        if (is_string($definition)) {
            return $this->resolveClass($definition);
        }

        if (is_object($definition)) {
            return $definition;
        }

        throw new Exception('Invalid service definition');
    }

    /**
     * Resolve a class definition
     *
     * @param string $className Class name
     * @return object Class instance
     */
    private function resolveClass(string $className)
    {
        if (!class_exists($className)) {
            throw new Exception("Class '{$className}' not found");
        }

        $reflection = new \ReflectionClass($className);

        // If no constructor, create instance directly
        if (!$reflection->getConstructor()) {
            return new $className();
        }

        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        // If constructor has no parameters, create instance directly
        if (empty($parameters)) {
            return new $className();
        }

        // Resolve constructor parameters
        $args = [];
        foreach ($parameters as $parameter) {
            $args[] = $this->resolveParameter($parameter);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Resolve a constructor parameter
     *
     * @param \ReflectionParameter $parameter Parameter reflection
     * @return mixed Resolved parameter value
     */
    private function resolveParameter(\ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        // If parameter has a type hint, try to resolve it
        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // Check if it's an interface or class we can resolve
            if ($this->has($typeName)) {
                return $this->get($typeName);
            }

            // Try to resolve the class directly
            if (class_exists($typeName) || interface_exists($typeName)) {
                return $this->resolveClass($typeName);
            }
        }

        // If parameter has a default value, use it
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // If parameter is optional, return null
        if ($parameter->isOptional()) {
            return null;
        }

        throw new Exception("Cannot resolve parameter '{$parameter->getName()}' for service");
    }

    /**
     * Make a service instance (resolve dependencies but don't store)
     *
     * @param string $className Class name
     * @param array $parameters Additional parameters
     * @return object Service instance
     */
    public function make(string $className, array $parameters = [])
    {
        if (!class_exists($className)) {
            throw new Exception("Class '{$className}' not found");
        }

        $reflection = new \ReflectionClass($className);

        if (!$reflection->getConstructor()) {
            return new $className();
        }

        $constructor = $reflection->getConstructor();
        $methodParams = $constructor->getParameters();

        $args = [];
        foreach ($methodParams as $param) {
            $paramName = $param->getName();

            // Use provided parameter if available
            if (array_key_exists($paramName, $parameters)) {
                $args[] = $parameters[$paramName];
                continue;
            }

            // Try to resolve from container
            try {
                $args[] = $this->resolveParameter($param);
            } catch (Exception $e) {
                // If parameter has default value, use it
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Call a method with dependency injection
     *
     * @param callable $callable Method to call
     * @param array $parameters Additional parameters
     * @return mixed Method result
     */
    public function call($callable, array $parameters = [])
    {
        if (is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }

        $methodParams = $reflection->getParameters();
        $args = [];

        foreach ($methodParams as $param) {
            $paramName = $param->getName();

            // Use provided parameter if available
            if (array_key_exists($paramName, $parameters)) {
                $args[] = $parameters[$paramName];
                continue;
            }

            // Try to resolve from container
            try {
                $args[] = $this->resolveParameter($param);
            } catch (Exception $e) {
                // If parameter has default value, use it
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }

        return $callable(...$args);
    }

    /**
     * Get all registered service IDs
     *
     * @return array Service IDs
     */
    public function getServiceIds(): array
    {
        return array_unique(array_merge(
            array_keys($this->definitions),
            array_keys($this->instances),
            array_keys($this->aliases)
        ));
    }

    /**
     * Clear all services and instances
     *
     * @return void
     */
    public function clear(): void
    {
        $this->definitions = [];
        $this->instances = [];
        $this->aliases = [];
    }

    /**
     * Extend a service definition
     *
     * @param string $id Service identifier
     * @param callable $extender Extension function
     * @return self
     */
    public function extend(string $id, callable $extender): self
    {
        $id = $this->resolveAlias($id);

        if (!isset($this->definitions[$id])) {
            throw new Exception("Service '{$id}' not found");
        }

        $originalDefinition = $this->definitions[$id]['definition'];
        $shared = $this->definitions[$id]['shared'];

        $this->definitions[$id] = [
            'definition' => function ($container) use ($originalDefinition, $extender) {
                $instance = $container->resolve($originalDefinition);
                return $extender($instance, $container);
            },
            'shared' => $shared
        ];

        // Clear existing instance if it exists
        unset($this->instances[$id]);

        return $this;
    }
}

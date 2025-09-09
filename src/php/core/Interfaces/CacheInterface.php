<?php
/**
 * TPT Government Platform - Cache Interface
 *
 * Defines the contract for cache implementations
 */

interface CacheInterface
{
    /**
     * Get an item from the cache
     *
     * @param string $key The cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Store an item in the cache
     *
     * @param string $key The cache key
     * @param mixed $value The value to store
     * @param int $ttl Time to live in seconds (0 = forever)
     * @return bool
     */
    public function set($key, $value, $ttl = 0);

    /**
     * Delete an item from the cache
     *
     * @param string $key The cache key
     * @return bool
     */
    public function delete($key);

    /**
     * Clear all items from the cache
     *
     * @return bool
     */
    public function clear();

    /**
     * Check if a cache key exists
     *
     * @param string $key The cache key
     * @return bool
     */
    public function has($key);

    /**
     * Get multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return array Array of key => value pairs
     */
    public function getMultiple($keys);

    /**
     * Store multiple items in the cache
     *
     * @param array $values Array of key => value pairs
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function setMultiple($values, $ttl = 0);

    /**
     * Delete multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return bool
     */
    public function deleteMultiple($keys);

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats();
}

/**
 * Cache Item Interface for PSR-6 compatibility
 */
interface CacheItemInterface
{
    /**
     * Returns the key for the current cache item
     *
     * @return string
     */
    public function getKey();

    /**
     * Retrieves the value of the item from the cache
     *
     * @return mixed
     */
    public function get();

    /**
     * Confirms if the cache item lookup resulted in a cache hit
     *
     * @return bool
     */
    public function isHit();

    /**
     * Sets the value represented by this cache item
     *
     * @param mixed $value
     * @return static
     */
    public function set($value);

    /**
     * Sets the expiration time for this cache item
     *
     * @param \DateTimeInterface $expiration
     * @return static
     */
    public function expiresAt($expiration);

    /**
     * Sets the expiration time for this cache item
     *
     * @param int|\DateInterval $time
     * @return static
     */
    public function expiresAfter($time);
}

/**
 * Cache Item Pool Interface for PSR-6 compatibility
 */
interface CacheItemPoolInterface
{
    /**
     * Returns a Cache Item representing the specified key
     *
     * @param string $key
     * @return CacheItemInterface
     */
    public function getItem($key);

    /**
     * Returns a traversable set of cache items
     *
     * @param string[] $keys
     * @return array|\Traversable
     */
    public function getItems(array $keys = []);

    /**
     * Confirms if the cache contains specified cache item
     *
     * @param string $key
     * @return bool
     */
    public function hasItem($key);

    /**
     * Deletes all items in the pool
     *
     * @return bool
     */
    public function clear();

    /**
     * Removes the item from the pool
     *
     * @param string $key
     * @return bool
     */
    public function deleteItem($key);

    /**
     * Removes multiple items from the pool
     *
     * @param string[] $keys
     * @return bool
     */
    public function deleteItems(array $keys);

    /**
     * Persists a cache item immediately
     *
     * @param CacheItemInterface $item
     * @return bool
     */
    public function save(CacheItemInterface $item);

    /**
     * Sets a cache item to be persisted later
     *
     * @param CacheItemInterface $item
     * @return static
     */
    public function saveDeferred(CacheItemInterface $item);

    /**
     * Persists any deferred cache items
     *
     * @return bool
     */
    public function commit();
}

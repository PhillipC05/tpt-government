<?php
/**
 * TPT Government Platform - Cache Eviction Policies
 *
 * Implements various cache eviction strategies for optimal cache management
 */

abstract class CacheEvictionPolicy
{
    protected $maxSize;
    protected $currentSize = 0;
    protected $accessOrder = [];

    public function __construct($maxSize = 1000)
    {
        $this->maxSize = $maxSize;
    }

    /**
     * Check if eviction is needed
     */
    abstract public function shouldEvict();

    /**
     * Get keys to evict
     */
    abstract public function getKeysToEvict($cacheItems);

    /**
     * Record access to a cache item
     */
    public function recordAccess($key)
    {
        // Remove from current position
        $this->accessOrder = array_diff($this->accessOrder, [$key]);
        // Add to end (most recently used)
        $this->accessOrder[] = $key;
    }

    /**
     * Record addition of a cache item
     */
    public function recordAddition($key)
    {
        $this->currentSize++;
        $this->recordAccess($key);
    }

    /**
     * Record removal of a cache item
     */
    public function recordRemoval($key)
    {
        $this->currentSize--;
        $this->accessOrder = array_diff($this->accessOrder, [$key]);
    }

    /**
     * Get current cache size
     */
    public function getCurrentSize()
    {
        return $this->currentSize;
    }

    /**
     * Set maximum cache size
     */
    public function setMaxSize($size)
    {
        $this->maxSize = $size;
    }
}

/**
 * Least Recently Used (LRU) Eviction Policy
 */
class LRUEvictionPolicy extends CacheEvictionPolicy
{
    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $keysToEvict = [];
        $evictCount = $this->currentSize - $this->maxSize + 1;

        // Get least recently used keys
        for ($i = 0; $i < $evictCount && !empty($this->accessOrder); $i++) {
            $key = array_shift($this->accessOrder);
            if (isset($cacheItems[$key])) {
                $keysToEvict[] = $key;
            }
        }

        return $keysToEvict;
    }
}

/**
 * First In, First Out (FIFO) Eviction Policy
 */
class FIFOEvictionPolicy extends CacheEvictionPolicy
{
    private $insertionOrder = [];

    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $keysToEvict = [];
        $evictCount = $this->currentSize - $this->maxSize + 1;

        // Get oldest inserted keys
        for ($i = 0; $i < $evictCount && !empty($this->insertionOrder); $i++) {
            $key = array_shift($this->insertionOrder);
            if (isset($cacheItems[$key])) {
                $keysToEvict[] = $key;
            }
        }

        return $keysToEvict;
    }

    public function recordAddition($key)
    {
        parent::recordAddition($key);
        $this->insertionOrder[] = $key;
    }

    public function recordRemoval($key)
    {
        parent::recordRemoval($key);
        $this->insertionOrder = array_diff($this->insertionOrder, [$key]);
    }
}

/**
 * Least Frequently Used (LFU) Eviction Policy
 */
class LFUEvictionPolicy extends CacheEvictionPolicy
{
    private $accessFrequency = [];

    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $keysToEvict = [];
        $evictCount = $this->currentSize - $this->maxSize + 1;

        // Sort by access frequency (ascending)
        asort($this->accessFrequency);

        foreach ($this->accessFrequency as $key => $frequency) {
            if (isset($cacheItems[$key]) && count($keysToEvict) < $evictCount) {
                $keysToEvict[] = $key;
            }
        }

        return $keysToEvict;
    }

    public function recordAccess($key)
    {
        parent::recordAccess($key);
        if (!isset($this->accessFrequency[$key])) {
            $this->accessFrequency[$key] = 0;
        }
        $this->accessFrequency[$key]++;
    }

    public function recordRemoval($key)
    {
        parent::recordRemoval($key);
        unset($this->accessFrequency[$key]);
    }
}

/**
 * Time-based Eviction Policy (TTL-based)
 */
class TimeBasedEvictionPolicy extends CacheEvictionPolicy
{
    private $defaultTtl;

    public function __construct($maxSize = 1000, $defaultTtl = 3600)
    {
        parent::__construct($maxSize);
        $this->defaultTtl = $defaultTtl;
    }

    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $keysToEvict = [];
        $evictCount = $this->currentSize - $this->maxSize + 1;

        // Find expired items first
        $expiredKeys = [];
        foreach ($cacheItems as $key => $item) {
            if ($this->isExpired($item)) {
                $expiredKeys[] = $key;
            }
        }

        // If we have enough expired items, evict them
        if (count($expiredKeys) >= $evictCount) {
            return array_slice($expiredKeys, 0, $evictCount);
        }

        // Otherwise, add expired items and find oldest items
        $keysToEvict = $expiredKeys;
        $remainingCount = $evictCount - count($expiredKeys);

        // Sort by expiration time (oldest first)
        uasort($cacheItems, function($a, $b) {
            $aTime = isset($a['expires_at']) ? strtotime($a['expires_at']) : time() + $this->defaultTtl;
            $bTime = isset($b['expires_at']) ? strtotime($b['expires_at']) : time() + $this->defaultTtl;
            return $aTime <=> $bTime;
        });

        foreach ($cacheItems as $key => $item) {
            if (!in_array($key, $expiredKeys) && count($keysToEvict) < $evictCount) {
                $keysToEvict[] = $key;
            }
        }

        return $keysToEvict;
    }

    private function isExpired($item)
    {
        if (!isset($item['expires_at'])) {
            return false;
        }

        return strtotime($item['expires_at']) < time();
    }
}

/**
 * Adaptive Eviction Policy (combines multiple strategies)
 */
class AdaptiveEvictionPolicy extends CacheEvictionPolicy
{
    private $lruPolicy;
    private $lfuPolicy;
    private $timePolicy;
    private $weights = [
        'lru' => 0.4,
        'lfu' => 0.3,
        'time' => 0.3
    ];

    public function __construct($maxSize = 1000)
    {
        parent::__construct($maxSize);
        $this->lruPolicy = new LRUEvictionPolicy($maxSize);
        $this->lfuPolicy = new LFUEvictionPolicy($maxSize);
        $this->timePolicy = new TimeBasedEvictionPolicy($maxSize);
    }

    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $allKeys = [];

        // Get candidates from each policy
        $lruKeys = $this->lruPolicy->getKeysToEvict($cacheItems);
        $lfuKeys = $this->lfuPolicy->getKeysToEvict($cacheItems);
        $timeKeys = $this->timePolicy->getKeysToEvict($cacheItems);

        // Combine and weight the results
        $keyScores = [];

        foreach ($lruKeys as $key) {
            $keyScores[$key] = ($keyScores[$key] ?? 0) + $this->weights['lru'];
        }

        foreach ($lfuKeys as $key) {
            $keyScores[$key] = ($keyScores[$key] ?? 0) + $this->weights['lfu'];
        }

        foreach ($timeKeys as $key) {
            $keyScores[$key] = ($keyScores[$key] ?? 0) + $this->weights['time'];
        }

        // Sort by score (highest first)
        arsort($keyScores);

        $evictCount = $this->currentSize - $this->maxSize + 1;
        return array_slice(array_keys($keyScores), 0, $evictCount);
    }

    public function recordAccess($key)
    {
        parent::recordAccess($key);
        $this->lruPolicy->recordAccess($key);
        $this->lfuPolicy->recordAccess($key);
    }

    public function recordAddition($key)
    {
        parent::recordAddition($key);
        $this->lruPolicy->recordAddition($key);
        $this->lfuPolicy->recordAddition($key);
        $this->timePolicy->recordAddition($key);
    }

    public function recordRemoval($key)
    {
        parent::recordRemoval($key);
        $this->lruPolicy->recordRemoval($key);
        $this->lfuPolicy->recordRemoval($key);
        $this->timePolicy->recordRemoval($key);
    }
}

/**
 * Size-based Eviction Policy (evicts largest items)
 */
class SizeBasedEvictionPolicy extends CacheEvictionPolicy
{
    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $keysToEvict = [];
        $evictCount = $this->currentSize - $this->maxSize + 1;

        // Sort by item size (largest first)
        uasort($cacheItems, function($a, $b) {
            $aSize = isset($a['size']) ? $a['size'] : strlen(serialize($a['value'] ?? ''));
            $bSize = isset($b['size']) ? $b['size'] : strlen(serialize($b['value'] ?? ''));
            return $bSize <=> $aSize;
        });

        foreach ($cacheItems as $key => $item) {
            if (count($keysToEvict) < $evictCount) {
                $keysToEvict[] = $key;
            }
        }

        return $keysToEvict;
    }
}

/**
 * Random Eviction Policy (for comparison/benchmarking)
 */
class RandomEvictionPolicy extends CacheEvictionPolicy
{
    public function shouldEvict()
    {
        return $this->currentSize >= $this->maxSize;
    }

    public function getKeysToEvict($cacheItems)
    {
        $keysToEvict = [];
        $evictCount = $this->currentSize - $this->maxSize + 1;
        $availableKeys = array_keys($cacheItems);

        for ($i = 0; $i < $evictCount && !empty($availableKeys); $i++) {
            $randomIndex = array_rand($availableKeys);
            $keysToEvict[] = $availableKeys[$randomIndex];
            unset($availableKeys[$randomIndex]);
        }

        return $keysToEvict;
    }
}

/**
 * Cache Eviction Policy Factory
 */
class CacheEvictionPolicyFactory
{
    public static function create($policyType, $maxSize = 1000, $options = [])
    {
        switch (strtolower($policyType)) {
            case 'lru':
                return new LRUEvictionPolicy($maxSize);
            case 'fifo':
                return new FIFOEvictionPolicy($maxSize);
            case 'lfu':
                return new LFUEvictionPolicy($maxSize);
            case 'time':
            case 'ttl':
                $ttl = $options['default_ttl'] ?? 3600;
                return new TimeBasedEvictionPolicy($maxSize, $ttl);
            case 'adaptive':
                return new AdaptiveEvictionPolicy($maxSize);
            case 'size':
                return new SizeBasedEvictionPolicy($maxSize);
            case 'random':
                return new RandomEvictionPolicy($maxSize);
            default:
                throw new Exception("Unknown eviction policy: {$policyType}");
        }
    }

    public static function getAvailablePolicies()
    {
        return [
            'lru' => 'Least Recently Used',
            'fifo' => 'First In, First Out',
            'lfu' => 'Least Frequently Used',
            'time' => 'Time-based (TTL)',
            'adaptive' => 'Adaptive (LRU + LFU + Time)',
            'size' => 'Size-based',
            'random' => 'Random'
        ];
    }
}

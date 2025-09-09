<?php
/**
 * TPT Government Platform - Collection Utility
 *
 * Advanced collection manipulation and data processing utilities
 */

namespace Core\Utility;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The items contained in the collection
     */
    protected array $items = [];

    /**
     * Create a new collection
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Create a collection from array
     */
    public static function make(array $items = []): self
    {
        return new static($items);
    }

    /**
     * Create collection from JSON
     */
    public static function fromJson(string $json): self
    {
        return new static(json_decode($json, true) ?? []);
    }

    /**
     * Get all items
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get item at index
     */
    public function get(int|string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Set item at index
     */
    public function set(int|string $key, $value): self
    {
        $this->items[$key] = $value;
        return $this;
    }

    /**
     * Check if key exists
     */
    public function has(int|string $key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Remove item by key
     */
    public function forget(int|string $key): self
    {
        unset($this->items[$key]);
        return $this;
    }

    /**
     * Get first item
     */
    public function first(callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return empty($this->items) ? $default : reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get last item
     */
    public function last(callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        return $this->reverse()->first($callback, $default);
    }

    /**
     * Filter collection
     */
    public function filter(callable $callback = null): self
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }

        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Map collection
     */
    public function map(callable $callback): self
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Transform each item
     */
    public function transform(callable $callback): self
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

    /**
     * Reduce collection to single value
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Sort collection
     */
    public function sort(callable $callback = null): self
    {
        if ($callback === null) {
            sort($this->items);
        } else {
            usort($this->items, $callback);
        }

        return $this;
    }

    /**
     * Sort by key
     */
    public function sortBy(string $key, int $direction = SORT_ASC): self
    {
        return $this->sort(function ($a, $b) use ($key, $direction) {
            $aValue = is_array($a) ? ($a[$key] ?? null) : (is_object($a) ? ($a->{$key} ?? null) : $a);
            $bValue = is_array($b) ? ($b[$key] ?? null) : (is_object($b) ? ($b->{$key} ?? null) : $b);

            if ($direction === SORT_DESC) {
                return $bValue <=> $aValue;
            }

            return $aValue <=> $bValue;
        });
    }

    /**
     * Group by key
     */
    public function groupBy(string $key): self
    {
        $groups = [];

        foreach ($this->items as $item) {
            $groupKey = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : $item);
            $groups[$groupKey][] = $item;
        }

        return new static(array_map(fn($group) => new static($group), $groups));
    }

    /**
     * Take n items
     */
    public function take(int $count): self
    {
        return new static(array_slice($this->items, 0, $count));
    }

    /**
     * Skip n items
     */
    public function skip(int $count): self
    {
        return new static(array_slice($this->items, $count));
    }

    /**
     * Chunk collection
     */
    public function chunk(int $size): self
    {
        $chunks = array_chunk($this->items, $size);
        return new static(array_map(fn($chunk) => new static($chunk), $chunks));
    }

    /**
     * Get unique items
     */
    public function unique(string $key = null): self
    {
        if ($key === null) {
            return new static(array_unique($this->items));
        }

        $unique = [];
        $seen = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : $item);

            if (!in_array($value, $seen)) {
                $seen[] = $value;
                $unique[] = $item;
            }
        }

        return new static($unique);
    }

    /**
     * Reverse collection
     */
    public function reverse(): self
    {
        return new static(array_reverse($this->items));
    }

    /**
     * Merge with another collection
     */
    public function merge($items): self
    {
        $items = $items instanceof self ? $items->all() : $items;
        return new static(array_merge($this->items, $items));
    }

    /**
     * Concatenate collections
     */
    public function concat($items): self
    {
        return $this->merge($items);
    }

    /**
     * Get items matching condition
     */
    public function where(string $key, $value, string $operator = '='): self
    {
        return $this->filter(function ($item) use ($key, $value, $operator) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : $item);

            switch ($operator) {
                case '=':
                case '==':
                    return $itemValue == $value;
                case '===':
                    return $itemValue === $value;
                case '!=':
                case '<>':
                    return $itemValue != $value;
                case '!==':
                    return $itemValue !== $value;
                case '<':
                    return $itemValue < $value;
                case '<=':
                    return $itemValue <= $value;
                case '>':
                    return $itemValue > $value;
                case '>=':
                    return $itemValue >= $value;
                case 'in':
                    return in_array($itemValue, (array) $value);
                case 'not_in':
                    return !in_array($itemValue, (array) $value);
                case 'contains':
                    return strpos($itemValue, $value) !== false;
                default:
                    return false;
            }
        });
    }

    /**
     * Pluck values from collection
     */
    public function pluck(string $key, string $indexKey = null): self
    {
        $results = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);

            if ($indexKey !== null) {
                $index = is_array($item) ? ($item[$indexKey] ?? null) : (is_object($item) ? ($item->{$indexKey} ?? null) : null);
                $results[$index] = $value;
            } else {
                $results[] = $value;
            }
        }

        return new static($results);
    }

    /**
     * Sum values
     */
    public function sum(string $key = null)
    {
        if ($key === null) {
            return array_sum($this->items);
        }

        return $this->pluck($key)->sum();
    }

    /**
     * Get average
     */
    public function avg(string $key = null)
    {
        $count = $this->count();
        if ($count === 0) {
            return 0;
        }

        return $this->sum($key) / $count;
    }

    /**
     * Get minimum value
     */
    public function min(string $key = null)
    {
        if ($key === null) {
            return empty($this->items) ? null : min($this->items);
        }

        return $this->pluck($key)->min();
    }

    /**
     * Get maximum value
     */
    public function max(string $key = null)
    {
        if ($key === null) {
            return empty($this->items) ? null : max($this->items);
        }

        return $this->pluck($key)->max();
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->items, $options);
    }

    /**
     * Convert to string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->items);
    }

    // IteratorAggregate implementation
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    // JsonSerializable implementation
    public function jsonSerialize()
    {
        return $this->items;
    }
}

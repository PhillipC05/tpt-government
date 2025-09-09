<?php
/**
 * TPT Government Platform - Base Repository
 *
 * Abstract base class for all repository implementations
 * Provides common database operations and query building
 */

namespace Core\Repository;

use Core\Database;
use Exception;
use PDO;

abstract class BaseRepository
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find record by primary key
     */
    public function find(int|string $id): ?array
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
            $result = $this->db->fetch($sql, [$id]);

            return $result ? $this->castAttributes($result) : null;
        } catch (Exception $e) {
            error_log("Error finding record in {$this->table}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find record by specific field
     */
    public function findBy(string $field, mixed $value): ?array
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE {$field} = ?";
            $result = $this->db->fetch($sql, [$value]);

            return $result ? $this->castAttributes($result) : null;
        } catch (Exception $e) {
            error_log("Error finding record by {$field} in {$this->table}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find multiple records by criteria
     */
    public function findWhere(array $conditions, array $options = []): array
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE " . $this->buildWhereClause($conditions);
            $params = array_values($conditions);

            // Add ordering
            if (isset($options['orderBy'])) {
                $sql .= " ORDER BY {$options['orderBy']}";
                if (isset($options['orderDirection'])) {
                    $sql .= " {$options['orderDirection']}";
                }
            }

            // Add limit
            if (isset($options['limit'])) {
                $sql .= " LIMIT {$options['limit']}";
                if (isset($options['offset'])) {
                    $sql .= " OFFSET {$options['offset']}";
                }
            }

            $results = $this->db->fetchAll($sql, $params);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (Exception $e) {
            error_log("Error finding records in {$this->table}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all records
     */
    public function all(array $options = []): array
    {
        try {
            $sql = "SELECT * FROM {$this->table}";

            // Add ordering
            if (isset($options['orderBy'])) {
                $sql .= " ORDER BY {$options['orderBy']}";
                if (isset($options['orderDirection'])) {
                    $sql .= " {$options['orderDirection']}";
                }
            }

            // Add limit
            if (isset($options['limit'])) {
                $sql .= " LIMIT {$options['limit']}";
                if (isset($options['offset'])) {
                    $sql .= " OFFSET {$options['offset']}";
                }
            }

            $results = $this->db->fetchAll($sql);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (Exception $e) {
            error_log("Error getting all records from {$this->table}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create new record
     */
    public function create(array $data): int|string|false
    {
        try {
            // Filter fillable attributes
            $data = $this->filterFillable($data);

            // Cast attributes before saving
            $data = $this->castAttributesForStorage($data);

            $columns = array_keys($data);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';

            $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";

            $result = $this->db->execute($sql, array_values($data));

            if ($result) {
                return $this->db->lastInsertId();
            }

            return false;
        } catch (Exception $e) {
            error_log("Error creating record in {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update existing record
     */
    public function update(int|string $id, array $data): bool
    {
        try {
            // Filter fillable attributes
            $data = $this->filterFillable($data);

            // Cast attributes before saving
            $data = $this->castAttributesForStorage($data);

            $setParts = [];
            $params = [];

            foreach ($data as $column => $value) {
                $setParts[] = "{$column} = ?";
                $params[] = $value;
            }

            $params[] = $id;

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = ?";

            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error updating record in {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete record
     */
    public function delete(int|string $id): bool
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
            return $this->db->execute($sql, [$id]);
        } catch (Exception $e) {
            error_log("Error deleting record from {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if record exists
     */
    public function exists(int|string $id): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE {$this->primaryKey} = ?";
            $result = $this->db->fetch($sql, [$id]);

            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking if record exists in {$this->table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table}";

            if (!empty($conditions)) {
                $sql .= " WHERE " . $this->buildWhereClause($conditions);
                $params = array_values($conditions);
            } else {
                $params = [];
            }

            $result = $this->db->fetch($sql, $params);

            return $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            error_log("Error counting records in {$this->table}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Paginate results
     */
    public function paginate(int $page = 1, int $perPage = 20, array $conditions = [], array $options = []): array
    {
        $offset = ($page - 1) * $perPage;

        $options['limit'] = $perPage;
        $options['offset'] = $offset;

        $items = $this->findWhere($conditions, $options);
        $total = $this->count($conditions);
        $totalPages = ceil($total / $perPage);

        return [
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null
            ]
        ];
    }

    /**
     * Execute raw query
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error executing query on {$this->table}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute raw query returning single result
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            return $this->db->fetch($sql, $params);
        } catch (Exception $e) {
            error_log("Error executing query on {$this->table}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        try {
            return $this->db->beginTransaction();
        } catch (Exception $e) {
            error_log("Error beginning transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        try {
            return $this->db->commit();
        } catch (Exception $e) {
            error_log("Error committing transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        try {
            return $this->db->rollback();
        } catch (Exception $e) {
            error_log("Error rolling back transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Filter fillable attributes
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Cast attributes for retrieval
     */
    protected function castAttributes(array $attributes): array
    {
        foreach ($this->casts as $attribute => $type) {
            if (isset($attributes[$attribute])) {
                $attributes[$attribute] = $this->castAttribute($attributes[$attribute], $type);
            }
        }

        // Remove hidden attributes
        foreach ($this->hidden as $attribute) {
            unset($attributes[$attribute]);
        }

        return $attributes;
    }

    /**
     * Cast attributes for storage
     */
    protected function castAttributesForStorage(array $attributes): array
    {
        foreach ($this->casts as $attribute => $type) {
            if (isset($attributes[$attribute])) {
                $attributes[$attribute] = $this->castAttributeForStorage($attributes[$attribute], $type);
            }
        }

        return $attributes;
    }

    /**
     * Cast individual attribute
     */
    protected function castAttribute(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => (int)$value,
            'float', 'double' => (float)$value,
            'bool', 'boolean' => (bool)$value,
            'json' => json_decode($value, true),
            'datetime' => $value ? new \DateTime($value) : null,
            'date' => $value ? new \DateTime($value) : null,
            default => $value
        };
    }

    /**
     * Cast attribute for storage
     */
    protected function castAttributeForStorage(mixed $value, string $type): mixed
    {
        return match ($type) {
            'json' => json_encode($value),
            'datetime', 'date' => $value instanceof \DateTime ? $value->format('Y-m-d H:i:s') : $value,
            default => $value
        };
    }

    /**
     * Build WHERE clause from conditions
     */
    protected function buildWhereClause(array $conditions): string
    {
        $parts = [];
        foreach (array_keys($conditions) as $field) {
            $parts[] = "{$field} = ?";
        }

        return implode(' AND ', $parts);
    }

    /**
     * Get table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get primary key
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get fillable attributes
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Set fillable attributes
     */
    public function setFillable(array $fillable): void
    {
        $this->fillable = $fillable;
    }
}

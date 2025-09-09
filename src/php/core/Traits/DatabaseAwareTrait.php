<?php
/**
 * TPT Government Platform - Database Aware Trait
 *
 * Provides standardized database access for modules and services
 */

trait DatabaseAwareTrait
{
    protected $db;
    protected $dbConfig;

    /**
     * Set database connection
     */
    public function setDatabase($db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Get database connection
     */
    public function getDatabase()
    {
        if (!$this->db) {
            throw new Exception('Database connection not set. Call setDatabase() first.');
        }
        return $this->db;
    }

    /**
     * Set database configuration
     */
    public function setDatabaseConfig($config)
    {
        $this->dbConfig = $config;
        return $this;
    }

    /**
     * Get database configuration
     */
    public function getDatabaseConfig()
    {
        return $this->dbConfig;
    }

    /**
     * Execute a query with error handling
     */
    protected function executeQuery($sql, $params = [])
    {
        try {
            $stmt = $this->getDatabase()->prepare($sql);

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $paramType = $this->getParamType($value);
                    $stmt->bindValue($key, $value, $paramType);
                }
            }

            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            $this->logDatabaseError($e, $sql, $params);
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute a SELECT query and return all results
     */
    protected function fetchAll($sql, $params = [])
    {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a SELECT query and return first result
     */
    protected function fetchOne($sql, $params = [])
    {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Execute an INSERT query and return last insert ID
     */
    protected function insert($sql, $params = [])
    {
        $this->executeQuery($sql, $params);
        return $this->getDatabase()->lastInsertId();
    }

    /**
     * Execute an UPDATE or DELETE query and return affected rows
     */
    protected function execute($sql, $params = [])
    {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin database transaction
     */
    protected function beginTransaction()
    {
        return $this->getDatabase()->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    protected function commit()
    {
        return $this->getDatabase()->commit();
    }

    /**
     * Rollback database transaction
     */
    protected function rollback()
    {
        return $this->getDatabase()->rollBack();
    }

    /**
     * Execute query within a transaction
     */
    protected function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get PDO parameter type
     */
    private function getParamType($value)
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * Log database errors
     */
    private function logDatabaseError($exception, $sql, $params)
    {
        $errorMessage = sprintf(
            "Database Error: %s\nSQL: %s\nParams: %s",
            $exception->getMessage(),
            $sql,
            json_encode($params)
        );

        // Log to error log if available
        if (method_exists($this, 'logError')) {
            $this->logError($errorMessage);
        } else {
            error_log($errorMessage);
        }
    }

    /**
     * Build WHERE clause from array of conditions
     */
    protected function buildWhereClause($conditions)
    {
        if (empty($conditions)) {
            return ['WHERE 1=1', []];
        }

        $whereParts = [];
        $params = [];

        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // Handle operators like ['>', 10], ['IN', [1,2,3]], etc.
                $operator = strtoupper($value[0]);
                $actualValue = $value[1];

                switch ($operator) {
                    case 'IN':
                        $placeholders = [];
                        foreach ($actualValue as $i => $val) {
                            $paramName = ":{$field}_{$i}";
                            $placeholders[] = $paramName;
                            $params[$paramName] = $val;
                        }
                        $whereParts[] = "{$field} IN (" . implode(',', $placeholders) . ")";
                        break;

                    case 'BETWEEN':
                        $whereParts[] = "{$field} BETWEEN :{$field}_start AND :{$field}_end";
                        $params[":{$field}_start"] = $actualValue[0];
                        $params[":{$field}_end"] = $actualValue[1];
                        break;

                    case 'LIKE':
                        $whereParts[] = "{$field} LIKE :{$field}";
                        $params[":{$field}"] = $actualValue;
                        break;

                    default:
                        $whereParts[] = "{$field} {$operator} :{$field}";
                        $params[":{$field}"] = $actualValue;
                        break;
                }
            } else {
                $whereParts[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
        }

        return ['WHERE ' . implode(' AND ', $whereParts), $params];
    }

    /**
     * Build ORDER BY clause
     */
    protected function buildOrderByClause($orderBy)
    {
        if (empty($orderBy)) {
            return '';
        }

        if (is_string($orderBy)) {
            return "ORDER BY {$orderBy}";
        }

        if (is_array($orderBy)) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $direction = strtoupper($direction);
                if (!in_array($direction, ['ASC', 'DESC'])) {
                    $direction = 'ASC';
                }
                $orderParts[] = "{$field} {$direction}";
            }
            return 'ORDER BY ' . implode(', ', $orderParts);
        }

        return '';
    }

    /**
     * Build LIMIT clause
     */
    protected function buildLimitClause($limit, $offset = null)
    {
        if (!$limit) {
            return '';
        }

        $clause = "LIMIT {$limit}";
        if ($offset !== null) {
            $clause .= " OFFSET {$offset}";
        }

        return $clause;
    }

    /**
     * Generic find method
     */
    protected function find($table, $conditions = [], $orderBy = null, $limit = null, $offset = null)
    {
        list($whereClause, $params) = $this->buildWhereClause($conditions);
        $orderByClause = $this->buildOrderByClause($orderBy);
        $limitClause = $this->buildLimitClause($limit, $offset);

        $sql = "SELECT * FROM {$table} {$whereClause} {$orderByClause} {$limitClause}";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Generic findOne method
     */
    protected function findOne($table, $conditions = [])
    {
        $results = $this->find($table, $conditions, null, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Generic count method
     */
    protected function count($table, $conditions = [])
    {
        list($whereClause, $params) = $this->buildWhereClause($conditions);

        $sql = "SELECT COUNT(*) as count FROM {$table} {$whereClause}";

        $result = $this->fetchOne($sql, $params);
        return (int) $result['count'];
    }

    /**
     * Generic insert method
     */
    protected function create($table, $data)
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ":{$field}";
        }, $fields);

        $sql = "INSERT INTO {$table} (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";

        return $this->insert($sql, array_combine($placeholders, array_values($data)));
    }

    /**
     * Generic update method
     */
    protected function update($table, $data, $conditions)
    {
        $setParts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $setParts[] = "{$field} = :set_{$field}";
            $params[":set_{$field}"] = $value;
        }

        list($whereClause, $whereParams) = $this->buildWhereClause($conditions);
        $params = array_merge($params, $whereParams);

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " {$whereClause}";

        return $this->execute($sql, $params);
    }

    /**
     * Generic delete method
     */
    protected function delete($table, $conditions)
    {
        list($whereClause, $params) = $this->buildWhereClause($conditions);

        $sql = "DELETE FROM {$table} {$whereClause}";

        return $this->execute($sql, $params);
    }
}

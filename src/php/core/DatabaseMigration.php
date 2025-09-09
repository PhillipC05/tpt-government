<?php
/**
 * TPT Government Platform - Database Migration System
 *
 * Handles database schema migrations with rollback capabilities
 * and migration tracking.
 */

class DatabaseMigration
{
    private $db;
    private $migrationTable = 'schema_migrations';
    private $migrationPath;

    public function __construct($dbConnection, $migrationPath = null)
    {
        $this->db = $dbConnection;
        $this->migrationPath = $migrationPath ?: __DIR__ . '/../migrations/';
        $this->ensureMigrationTable();
    }

    /**
     * Ensure migration tracking table exists
     */
    private function ensureMigrationTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->migrationTable} (
                id SERIAL PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            throw new Exception("Failed to create migration table: " . $e->getMessage());
        }
    }

    /**
     * Get list of pending migrations
     */
    public function getPendingMigrations()
    {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrations();

        return array_diff($all, $executed);
    }

    /**
     * Get list of executed migrations
     */
    public function getExecutedMigrations()
    {
        $stmt = $this->db->prepare("SELECT migration_name FROM {$this->migrationTable} ORDER BY id");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all available migration files
     */
    public function getAllMigrations()
    {
        $migrations = [];

        if (!is_dir($this->migrationPath)) {
            return $migrations;
        }

        $files = glob($this->migrationPath . '*.php');
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (preg_match('/^\d{14}_(.+)$/', $filename, $matches)) {
                $migrations[] = $matches[1];
            }
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Run pending migrations
     */
    public function migrate()
    {
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            echo "No pending migrations.\n";
            return true;
        }

        $batch = $this->getNextBatchNumber();

        foreach ($pending as $migration) {
            echo "Running migration: {$migration}\n";

            try {
                $this->db->beginTransaction();

                $this->runMigration($migration, 'up');

                // Record migration
                $stmt = $this->db->prepare("INSERT INTO {$this->migrationTable} (migration_name, batch) VALUES (?, ?)");
                $stmt->execute([$migration, $batch]);

                $this->db->commit();

                echo "✓ Migration completed: {$migration}\n";
            } catch (Exception $e) {
                $this->db->rollBack();
                echo "✗ Migration failed: {$migration} - " . $e->getMessage() . "\n";
                return false;
            }
        }

        echo "All migrations completed successfully.\n";
        return true;
    }

    /**
     * Rollback last batch of migrations
     */
    public function rollback($steps = 1)
    {
        $batches = $this->getMigrationBatches();

        if (empty($batches)) {
            echo "No migrations to rollback.\n";
            return true;
        }

        // Get the last batch
        $lastBatch = end($batches);
        $migrations = $lastBatch['migrations'];

        foreach ($migrations as $migration) {
            echo "Rolling back migration: {$migration}\n";

            try {
                $this->db->beginTransaction();

                $this->runMigration($migration, 'down');

                // Remove migration record
                $stmt = $this->db->prepare("DELETE FROM {$this->migrationTable} WHERE migration_name = ?");
                $stmt->execute([$migration]);

                $this->db->commit();

                echo "✓ Rollback completed: {$migration}\n";
            } catch (Exception $e) {
                $this->db->rollBack();
                echo "✗ Rollback failed: {$migration} - " . $e->getMessage() . "\n";
                return false;
            }
        }

        echo "Rollback completed successfully.\n";
        return true;
    }

    /**
     * Get migration batches
     */
    private function getMigrationBatches()
    {
        $stmt = $this->db->prepare("
            SELECT batch, migration_name
            FROM {$this->migrationTable}
            ORDER BY batch DESC, id DESC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $batches = [];
        foreach ($results as $result) {
            $batches[$result['batch']][] = $result['migration_name'];
        }

        return $batches;
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber()
    {
        $stmt = $this->db->prepare("SELECT MAX(batch) as max_batch FROM {$this->migrationTable}");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Run a specific migration
     */
    private function runMigration($migrationName, $direction = 'up')
    {
        $file = $this->migrationPath . $this->getMigrationFileName($migrationName);

        if (!file_exists($file)) {
            throw new Exception("Migration file not found: {$file}");
        }

        require_once $file;

        $className = $this->getMigrationClassName($migrationName);

        if (!class_exists($className)) {
            throw new Exception("Migration class not found: {$className}");
        }

        $migration = new $className();

        if (!method_exists($migration, $direction)) {
            throw new Exception("Migration method '{$direction}' not found in {$className}");
        }

        $migration->{$direction}($this->db);
    }

    /**
     * Get migration file name from migration name
     */
    private function getMigrationFileName($migrationName)
    {
        // Find the file that contains this migration
        $files = glob($this->migrationPath . '*.php');
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            if (preg_match('/^\d{14}_(.+)$/', $filename, $matches)) {
                if ($matches[1] === $migrationName) {
                    return $filename . '.php';
                }
            }
        }

        return null;
    }

    /**
     * Get migration class name from migration name
     */
    private function getMigrationClassName($migrationName)
    {
        return 'Migration_' . str_replace(['-', '_'], ['', ''], ucwords($migrationName, '-_'));
    }

    /**
     * Create a new migration file
     */
    public function createMigration($name)
    {
        $timestamp = date('YmdHis');
        $filename = $timestamp . '_' . $name . '.php';
        $filepath = $this->migrationPath . $filename;

        $className = $this->getMigrationClassName($name);

        $template = "<?php
/**
 * Migration: {$name}
 * Created: " . date('Y-m-d H:i:s') . "
 */

class {$className}
{
    public function up(\$db)
    {
        // Migration logic goes here
        // Example:
        // \$db->exec(\"
        //     CREATE TABLE example (
        //         id SERIAL PRIMARY KEY,
        //         name VARCHAR(255) NOT NULL,
        //         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        //     )
        // \");
    }

    public function down(\$db)
    {
        // Rollback logic goes here
        // Example:
        // \$db->exec(\"DROP TABLE IF EXISTS example\");
    }
}
";

        if (file_put_contents($filepath, $template)) {
            echo "Migration created: {$filepath}\n";
            return $filepath;
        } else {
            throw new Exception("Failed to create migration file: {$filepath}");
        }
    }

    /**
     * Get migration status
     */
    public function status()
    {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrations();
        $pending = array_diff($all, $executed);

        echo "Migration Status:\n";
        echo "================\n";
        echo "Executed: " . count($executed) . "\n";
        echo "Pending: " . count($pending) . "\n";
        echo "Total: " . count($all) . "\n\n";

        if (!empty($pending)) {
            echo "Pending Migrations:\n";
            foreach ($pending as $migration) {
                echo "  - {$migration}\n";
            }
        }
    }
}

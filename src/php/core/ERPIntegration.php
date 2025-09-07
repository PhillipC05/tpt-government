<?php
/**
 * TPT Government Platform - ERP Integration
 *
 * Comprehensive ERP integration system supporting multiple ERP providers.
 * Handles data synchronization, API connections, and business process integration.
 */

namespace Core;

class ERPIntegration
{
    /**
     * Supported ERP systems
     */
    private const SUPPORTED_ERPS = [
        'sap' => 'SAP',
        'oracle' => 'Oracle E-Business Suite',
        'dynamics' => 'Microsoft Dynamics',
        'workday' => 'Workday',
        'peoplesoft' => 'PeopleSoft',
        'custom' => 'Custom ERP'
    ];

    /**
     * ERP configurations
     */
    private array $erpConfigs;

    /**
     * Database instance
     */
    private Database $database;

    /**
     * HTTP client
     */
    private HttpClient $httpClient;

    /**
     * Data mapper
     */
    private ERPDataMapper $dataMapper;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $erpConfigs = [])
    {
        $this->database = $database;
        $this->erpConfigs = $erpConfigs;
        $this->httpClient = new HttpClient();
        $this->dataMapper = new ERPDataMapper();
    }

    /**
     * Connect to ERP system
     */
    public function connect(string $erpType, array $config): array
    {
        if (!isset(self::SUPPORTED_ERPS[$erpType])) {
            return [
                'success' => false,
                'error' => "Unsupported ERP system: {$erpType}"
            ];
        }

        try {
            $connector = $this->createConnector($erpType, $config);
            $connection = $connector->connect();

            if ($connection) {
                // Store connection configuration
                $this->saveERPConfig($erpType, $config);

                return [
                    'success' => true,
                    'message' => "Successfully connected to {$erpType}",
                    'connection_id' => $connection
                ];
            }

            return [
                'success' => false,
                'error' => "Failed to connect to {$erpType}"
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Connection error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Disconnect from ERP system
     */
    public function disconnect(string $erpType): array
    {
        try {
            $connector = $this->getConnector($erpType);
            if ($connector) {
                $connector->disconnect();
            }

            // Remove stored configuration
            $this->removeERPConfig($erpType);

            return [
                'success' => true,
                'message' => "Disconnected from {$erpType}"
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Disconnection error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Sync data from ERP system
     */
    public function syncFromERP(string $erpType, string $entityType, array $filters = []): array
    {
        try {
            $connector = $this->getConnector($erpType);
            if (!$connector) {
                return [
                    'success' => false,
                    'error' => "No connection configured for {$erpType}"
                ];
            }

            // Fetch data from ERP
            $erpData = $connector->fetchData($entityType, $filters);

            // Transform data
            $transformedData = $this->dataMapper->transformFromERP($erpType, $entityType, $erpData);

            // Store in local database
            $syncResult = $this->storeSyncedData($erpType, $entityType, $transformedData);

            // Log sync operation
            $this->logSyncOperation($erpType, $entityType, 'from_erp', count($transformedData));

            return [
                'success' => true,
                'message' => "Synced {$syncResult['records_processed']} records from {$erpType}",
                'data' => $syncResult
            ];

        } catch (\Exception $e) {
            $this->logSyncError($erpType, $entityType, 'from_erp', $e->getMessage());
            return [
                'success' => false,
                'error' => "Sync error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Sync data to ERP system
     */
    public function syncToERP(string $erpType, string $entityType, array $data): array
    {
        try {
            $connector = $this->getConnector($erpType);
            if (!$connector) {
                return [
                    'success' => false,
                    'error' => "No connection configured for {$erpType}"
                ];
            }

            // Transform data for ERP
            $erpData = $this->dataMapper->transformToERP($erpType, $entityType, $data);

            // Send data to ERP
            $result = $connector->sendData($entityType, $erpData);

            // Log sync operation
            $this->logSyncOperation($erpType, $entityType, 'to_erp', count($data));

            return [
                'success' => true,
                'message' => "Synced " . count($data) . " records to {$erpType}",
                'result' => $result
            ];

        } catch (\Exception $e) {
            $this->logSyncError($erpType, $entityType, 'to_erp', $e->getMessage());
            return [
                'success' => false,
                'error' => "Sync error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get ERP system status
     */
    public function getStatus(string $erpType): array
    {
        try {
            $connector = $this->getConnector($erpType);
            if (!$connector) {
                return [
                    'connected' => false,
                    'status' => 'not_configured',
                    'message' => "No connection configured for {$erpType}"
                ];
            }

            $status = $connector->getStatus();

            return [
                'connected' => $status['connected'] ?? false,
                'status' => $status['status'] ?? 'unknown',
                'last_sync' => $this->getLastSyncTime($erpType),
                'message' => $status['message'] ?? ''
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available ERP entities
     */
    public function getEntities(string $erpType): array
    {
        $entities = [
            'employees' => 'Employee Data',
            'departments' => 'Department Structure',
            'budget' => 'Budget Information',
            'vendors' => 'Vendor Data',
            'purchase_orders' => 'Purchase Orders',
            'invoices' => 'Invoice Data',
            'assets' => 'Asset Management',
            'projects' => 'Project Data'
        ];

        return $entities;
    }

    /**
     * Create ERP connector
     */
    private function createConnector(string $erpType, array $config)
    {
        $connectorClass = 'ERP' . ucfirst($erpType) . 'Connector';

        if (class_exists($connectorClass)) {
            return new $connectorClass($config, $this->httpClient);
        }

        // Fallback to generic connector
        return new ERPGenericConnector($config, $this->httpClient);
    }

    /**
     * Get existing ERP connector
     */
    private function getConnector(string $erpType)
    {
        $config = $this->getERPConfig($erpType);
        if (!$config) {
            return null;
        }

        return $this->createConnector($erpType, $config);
    }

    /**
     * Save ERP configuration
     */
    private function saveERPConfig(string $erpType, array $config): void
    {
        try {
            $this->database->query(
                "INSERT INTO erp_configurations (erp_type, config_data, created_at, updated_at)
                 VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                 ON CONFLICT (erp_type) DO UPDATE SET
                 config_data = EXCLUDED.config_data,
                 updated_at = CURRENT_TIMESTAMP",
                [$erpType, json_encode($config)]
            );
        } catch (\Exception $e) {
            error_log("Failed to save ERP config for {$erpType}: " . $e->getMessage());
        }
    }

    /**
     * Get ERP configuration
     */
    private function getERPConfig(string $erpType): ?array
    {
        try {
            $result = $this->database->selectOne(
                "SELECT config_data FROM erp_configurations WHERE erp_type = ?",
                [$erpType]
            );

            return $result ? json_decode($result['config_data'], true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Remove ERP configuration
     */
    private function removeERPConfig(string $erpType): void
    {
        try {
            $this->database->delete('erp_configurations', ['erp_type' => $erpType]);
        } catch (\Exception $e) {
            error_log("Failed to remove ERP config for {$erpType}: " . $e->getMessage());
        }
    }

    /**
     * Store synced data
     */
    private function storeSyncedData(string $erpType, string $entityType, array $data): array
    {
        $tableName = 'erp_' . strtolower($erpType) . '_' . strtolower($entityType);
        $recordsProcessed = 0;

        try {
            // Ensure table exists
            $this->createSyncTable($tableName, $entityType);

            // Insert/update records
            foreach ($data as $record) {
                $record['erp_type'] = $erpType;
                $record['entity_type'] = $entityType;
                $record['synced_at'] = date('Y-m-d H:i:s');

                $this->database->query(
                    "INSERT INTO {$tableName} (erp_id, data, synced_at)
                     VALUES (?, ?, CURRENT_TIMESTAMP)
                     ON CONFLICT (erp_id) DO UPDATE SET
                     data = EXCLUDED.data,
                     synced_at = CURRENT_TIMESTAMP",
                    [$record['id'], json_encode($record)]
                );

                $recordsProcessed++;
            }

        } catch (\Exception $e) {
            error_log("Failed to store synced data: " . $e->getMessage());
        }

        return [
            'records_processed' => $recordsProcessed,
            'table_name' => $tableName
        ];
    }

    /**
     * Create sync table
     */
    private function createSyncTable(string $tableName, string $entityType): void
    {
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id SERIAL PRIMARY KEY,
                erp_id VARCHAR(255) UNIQUE NOT NULL,
                data JSONB NOT NULL,
                synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_{$tableName}_synced_at
            ON {$tableName} (synced_at);

            CREATE INDEX IF NOT EXISTS idx_{$tableName}_erp_id
            ON {$tableName} (erp_id);
        ";

        $this->database->query($createTableSQL);
    }

    /**
     * Get last sync time
     */
    private function getLastSyncTime(string $erpType): ?string
    {
        try {
            $result = $this->database->selectOne(
                "SELECT MAX(synced_at) as last_sync FROM erp_sync_log WHERE erp_type = ?",
                [$erpType]
            );

            return $result['last_sync'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log sync operation
     */
    private function logSyncOperation(string $erpType, string $entityType, string $direction, int $recordCount): void
    {
        try {
            $this->database->insert('erp_sync_log', [
                'erp_type' => $erpType,
                'entity_type' => $entityType,
                'direction' => $direction,
                'record_count' => $recordCount,
                'status' => 'success',
                'synced_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log sync operation: " . $e->getMessage());
        }
    }

    /**
     * Log sync error
     */
    private function logSyncError(string $erpType, string $entityType, string $direction, string $error): void
    {
        try {
            $this->database->insert('erp_sync_log', [
                'erp_type' => $erpType,
                'entity_type' => $entityType,
                'direction' => $direction,
                'status' => 'error',
                'error_message' => $error,
                'synced_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log sync error: " . $e->getMessage());
        }
    }

    /**
     * Get supported ERP systems
     */
    public function getSupportedERPs(): array
    {
        return self::SUPPORTED_ERPS;
    }

    /**
     * Get configured ERP systems
     */
    public function getConfiguredERPs(): array
    {
        $configured = [];

        foreach (self::SUPPORTED_ERPS as $type => $name) {
            $config = $this->getERPConfig($type);
            if ($config) {
                $configured[$type] = [
                    'name' => $name,
                    'status' => $this->getStatus($type),
                    'last_sync' => $this->getLastSyncTime($type)
                ];
            }
        }

        return $configured;
    }

    /**
     * Test ERP connection
     */
    public function testConnection(string $erpType): array
    {
        $connector = $this->getConnector($erpType);
        if (!$connector) {
            return [
                'success' => false,
                'error' => "No connection configured for {$erpType}"
            ];
        }

        try {
            $result = $connector->testConnection();
            return [
                'success' => $result['success'] ?? false,
                'response_time' => $result['response_time'] ?? null,
                'message' => $result['message'] ?? ''
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

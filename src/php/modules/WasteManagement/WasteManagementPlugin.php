<?php
/**
 * TPT Government Platform - Waste Management Module
 *
 * Comprehensive waste collection and management system
 * for local government environmental and public health services.
 */

namespace Core\Modules;

use Core\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class WasteManagementPlugin extends ServiceModule
{
    /**
     * Service category
     */
    protected string $serviceCategory = 'infrastructure';

    /**
     * Required permissions
     */
    protected array $requiredPermissions = [
        'waste_management.view',
        'waste_management.create',
        'waste_management.manage',
        'waste_collection.schedule'
    ];

    /**
     * Database tables
     */
    protected array $databaseTables = [
        'waste_collection_schedules' => [
            'id SERIAL PRIMARY KEY',
            'schedule_name VARCHAR(100) NOT NULL',
            'collection_type VARCHAR(50) NOT NULL',
            'collection_day VARCHAR(20) NOT NULL',
            'collection_frequency VARCHAR(20) DEFAULT "weekly"',
            'service_area TEXT NOT NULL',
            'start_time TIME NOT NULL',
            'end_time TIME',
            'active BOOLEAN DEFAULT TRUE',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'waste_service_requests' => [
            'id SERIAL PRIMARY KEY',
            'request_number VARCHAR(50) UNIQUE NOT NULL',
            'request_type VARCHAR(50) NOT NULL',
            'requester_name VARCHAR(100) NOT NULL',
            'requester_address TEXT NOT NULL',
            'requester_phone VARCHAR(20)',
            'requester_email VARCHAR(100)',
            'description TEXT NOT NULL',
            'priority VARCHAR(20) DEFAULT "normal"',
            'status VARCHAR(50) DEFAULT "pending"',
            'assigned_to INT NULL',
            'scheduled_date DATE NULL',
            'scheduled_time TIME NULL',
            'completed_at TIMESTAMP NULL',
            'completion_notes TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'waste_collection_zones' => [
            'id SERIAL PRIMARY KEY',
            'zone_name VARCHAR(100) NOT NULL',
            'zone_code VARCHAR(20) UNIQUE NOT NULL',
            'boundary_coordinates TEXT',
            'collection_schedule_id INT',
            'waste_types TEXT',
            'population_served INT',
            'active BOOLEAN DEFAULT TRUE',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (collection_schedule_id) REFERENCES waste_collection_schedules(id)'
        ],
        'waste_billing_accounts' => [
            'id SERIAL PRIMARY KEY',
            'account_number VARCHAR(50) UNIQUE NOT NULL',
            'property_address TEXT NOT NULL',
            'owner_name VARCHAR(100) NOT NULL',
            'owner_contact VARCHAR(100)',
            'service_type VARCHAR(50) NOT NULL',
            'billing_cycle VARCHAR(20) DEFAULT "monthly"',
            'rate_per_collection DECIMAL(8,2) DEFAULT 0',
            'fixed_monthly_rate DECIMAL(8,2) DEFAULT 0',
            'current_balance DECIMAL(8,2) DEFAULT 0',
            'last_billed DATE NULL',
            'next_billing_date DATE NULL',
            'status VARCHAR(50) DEFAULT "active"',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'waste_collection_records' => [
            'id SERIAL PRIMARY KEY',
            'collection_date DATE NOT NULL',
            'collection_time TIME NOT NULL',
            'zone_id INT NOT NULL',
            'collection_type VARCHAR(50) NOT NULL',
            'vehicle_id VARCHAR(50)',
            'driver_id INT',
            'weight_collected DECIMAL(8,2)',
            'containers_emptied INT DEFAULT 0',
            'issues_reported TEXT',
            'completion_status VARCHAR(50) DEFAULT "completed"',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (zone_id) REFERENCES waste_collection_zones(id)'
        ],
        'waste_recycling_programs' => [
            'id SERIAL PRIMARY KEY',
            'program_name VARCHAR(100) NOT NULL',
            'program_type VARCHAR(50) NOT NULL',
            'description TEXT',
            'eligibility_criteria TEXT',
            'benefits TEXT',
            'application_required BOOLEAN DEFAULT FALSE',
            'active BOOLEAN DEFAULT TRUE',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * API endpoints
     */
    protected array $apiEndpoints = [
        [
            'path' => '/api/waste-collection-schedules',
            'method' => 'GET',
            'handler' => [$this, 'getCollectionSchedules'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/waste-service-requests',
            'method' => 'GET',
            'handler' => [$this, 'getServiceRequests'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/waste-service-requests',
            'method' => 'POST',
            'handler' => [$this, 'createServiceRequest'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/waste-service-requests/{id}',
            'method' => 'GET',
            'handler' => [$this, 'getServiceRequest'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/waste-service-requests/{id}/update',
            'method' => 'POST',
            'handler' => [$this, 'updateServiceRequest'],
            'middleware' => ['auth', 'permission:waste_management.manage']
        ],
        [
            'path' => '/api/waste-collection-zones',
            'method' => 'GET',
            'handler' => [$this, 'getCollectionZones'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/waste-billing/{account}',
            'method' => 'GET',
            'handler' => [$this, 'getBillingAccount'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/waste-recycling-programs',
            'method' => 'GET',
            'handler' => [$this, 'getRecyclingPrograms'],
            'middleware' => []
        ]
    ];

    /**
     * Workflow definitions
     */
    protected array $workflows = [
        'waste_service_request' => [
            'name' => 'Waste Service Request Process',
            'description' => 'Standard workflow for processing waste service requests',
            'steps' => [
                'pending' => ['next' => 'assigned', 'roles' => ['dispatcher']],
                'assigned' => ['next' => 'scheduled', 'roles' => ['dispatcher']],
                'scheduled' => ['next' => 'in_progress', 'roles' => ['crew_member']],
                'in_progress' => ['next' => 'completed', 'roles' => ['crew_member']],
                'completed' => ['final' => true, 'roles' => ['crew_member']],
                'cancelled' => ['final' => true, 'roles' => ['dispatcher']]
            ]
        ]
    ];

    /**
     * Form definitions
     */
    protected array $forms = [
        'waste_service_request' => [
            'name' => 'Waste Service Request Form',
            'description' => 'Form for submitting waste service requests',
            'fields' => [
                'request_type' => ['type' => 'select', 'required' => true, 'label' => 'Request Type'],
                'requester_name' => ['type' => 'text', 'required' => true, 'label' => 'Your Name'],
                'requester_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Property Address'],
                'requester_phone' => ['type' => 'tel', 'required' => false, 'label' => 'Phone Number'],
                'requester_email' => ['type' => 'email', 'required' => false, 'label' => 'Email Address'],
                'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Description'],
                'priority' => ['type' => 'select', 'required' => true, 'label' => 'Priority']
            ]
        ],
        'waste_collection_record' => [
            'name' => 'Waste Collection Record Form',
            'description' => 'Form for recording waste collection activities',
            'fields' => [
                'collection_date' => ['type' => 'date', 'required' => true, 'label' => 'Collection Date'],
                'collection_time' => ['type' => 'time', 'required' => true, 'label' => 'Collection Time'],
                'zone_id' => ['type' => 'select', 'required' => true, 'label' => 'Collection Zone'],
                'collection_type' => ['type' => 'select', 'required' => true, 'label' => 'Waste Type'],
                'weight_collected' => ['type' => 'number', 'required' => false, 'label' => 'Weight Collected (kg)'],
                'containers_emptied' => ['type' => 'number', 'required' => true, 'label' => 'Containers Emptied']
            ]
        ]
    ];

    /**
     * Report definitions
     */
    protected array $reports = [
        'collection_efficiency' => [
            'name' => 'Waste Collection Efficiency Report',
            'description' => 'Report on collection schedules and completion rates',
            'type' => 'operational'
        ],
        'service_request_summary' => [
            'name' => 'Service Request Summary Report',
            'description' => 'Summary of service requests and response times',
            'type' => 'operational'
        ],
        'waste_billing_revenue' => [
            'name' => 'Waste Billing Revenue Report',
            'description' => 'Report on waste collection billing and revenue',
            'type' => 'financial'
        ],
        'environmental_impact' => [
            'name' => 'Environmental Impact Report',
            'description' => 'Report on waste diversion and recycling rates',
            'type' => 'environmental'
        ]
    ];

    /**
     * Get plugin name
     */
    public function getPluginName(): string
    {
        return 'waste-management';
    }

    /**
     * Get service icon
     */
    public function getServiceIcon(): string
    {
        return 'fas fa-trash';
    }

    /**
     * Get collection schedules
     */
    public function getCollectionSchedules(array $filters = []): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $whereClause = 'WHERE active = TRUE';
            $params = [];

            if (!empty($filters['collection_type'])) {
                $whereClause .= ' AND collection_type = ?';
                $params[] = $filters['collection_type'];
            }

            $schedules = $this->database->select(
                "SELECT * FROM waste_collection_schedules {$whereClause} ORDER BY collection_day, start_time",
                $params
            );

            return [
                'success' => true,
                'data' => $schedules
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get service requests
     */
    public function getServiceRequests(array $filters = []): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $whereClause = '';
            $params = [];

            if (!empty($filters['status'])) {
                $whereClause = 'WHERE status = ?';
                $params[] = $filters['status'];
            }

            if (!empty($filters['request_type'])) {
                $whereClause = $whereClause ? $whereClause . ' AND' : 'WHERE';
                $whereClause .= ' request_type = ?';
                $params[] = $filters['request_type'];
            }

            $requests = $this->database->select(
                "SELECT * FROM waste_service_requests {$whereClause} ORDER BY created_at DESC",
                $params
            );

            return [
                'success' => true,
                'data' => $requests
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create service request
     */
    public function createServiceRequest(array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Generate request number
            $requestNumber = $this->generateRequestNumber();

            $requestId = $this->database->insert('waste_service_requests', [
                'request_number' => $requestNumber,
                'request_type' => $data['request_type'],
                'requester_name' => $data['requester_name'],
                'requester_address' => $data['requester_address'],
                'requester_phone' => $data['requester_phone'] ?? null,
                'requester_email' => $data['requester_email'] ?? null,
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'pending'
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'request_number' => $requestNumber
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get single service request
     */
    public function getServiceRequest(int $id): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $request = $this->database->selectOne(
                'SELECT * FROM waste_service_requests WHERE id = ?',
                [$id]
            );

            if (!$request) {
                return [
                    'success' => false,
                    'error' => 'Service request not found'
                ];
            }

            return [
                'success' => true,
                'request' => $request
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update service request
     */
    public function updateServiceRequest(int $id, array $updateData): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $updateFields = [];

            if (isset($updateData['status'])) {
                $updateFields['status'] = $updateData['status'];
            }

            if (isset($updateData['assigned_to'])) {
                $updateFields['assigned_to'] = $updateData['assigned_to'];
            }

            if (isset($updateData['scheduled_date'])) {
                $updateFields['scheduled_date'] = $updateData['scheduled_date'];
            }

            if (isset($updateData['scheduled_time'])) {
                $updateFields['scheduled_time'] = $updateData['scheduled_time'];
            }

            if (isset($updateData['completion_notes'])) {
                $updateFields['completion_notes'] = $updateData['completion_notes'];
                $updateFields['completed_at'] = date('Y-m-d H:i:s');
            }

            $this->database->update(
                'waste_service_requests',
                $updateFields,
                ['id' => $id]
            );

            return [
                'success' => true,
                'message' => 'Service request updated successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get collection zones
     */
    public function getCollectionZones(array $filters = []): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $whereClause = 'WHERE active = TRUE';
            $params = [];

            $zones = $this->database->select(
                "SELECT * FROM waste_collection_zones {$whereClause} ORDER BY zone_name",
                $params
            );

            return [
                'success' => true,
                'data' => $zones
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get billing account
     */
    public function getBillingAccount(string $accountNumber): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $account = $this->database->selectOne(
                'SELECT * FROM waste_billing_accounts WHERE account_number = ?',
                [$accountNumber]
            );

            if (!$account) {
                return [
                    'success' => false,
                    'error' => 'Billing account not found'
                ];
            }

            return [
                'success' => true,
                'account' => $account
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get recycling programs
     */
    public function getRecyclingPrograms(): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $programs = $this->database->select(
                'SELECT * FROM waste_recycling_programs WHERE active = TRUE ORDER BY program_name'
            );

            return [
                'success' => true,
                'data' => $programs
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate request number
     */
    private function generateRequestNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $sequence = rand(10000, 99999);
        return "WSR{$year}{$month}{$sequence}";
    }

    /**
     * Get service statistics
     */
    protected function getServiceStatistics(): array
    {
        if (!$this->database) {
            return parent::getServiceStatistics();
        }

        try {
            $totalRequests = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM waste_service_requests'
            )['count'] ?? 0;

            $pendingRequests = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM waste_service_requests WHERE status IN ("pending", "assigned", "scheduled")'
            )['count'] ?? 0;

            $completedToday = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM waste_service_requests WHERE DATE(completed_at) = CURDATE()'
            )['count'] ?? 0;

            $totalCollections = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM waste_collection_records WHERE DATE(created_at) = CURDATE()'
            )['count'] ?? 0;

            return [
                'total_requests' => $totalRequests,
                'pending_requests' => $pendingRequests,
                'completed_today' => $completedToday,
                'collections_today' => $totalCollections
            ];
        } catch (\Exception $e) {
            return parent::getServiceStatistics();
        }
    }

    /**
     * Initialize default configuration
     */
    protected function initializeDefaultConfig(): void
    {
        $this->setServiceConfig('collection_reminder_days', 1);
        $this->setServiceConfig('service_response_target_hours', 48);
        $this->setServiceConfig('billing_cycle_day', 1);
        $this->setServiceConfig('recycling_targets_enabled', true);
        $this->setServiceConfig('environmental_reporting_enabled', true);
    }
}

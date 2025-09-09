<?php
/**
 * TPT Government Platform - Agriculture & Rural Development Module
 *
 * Comprehensive agricultural services and rural development support system
 * supporting farm registration, subsidies, rural development programs, and compliance
 */

namespace Modules\AgricultureRuralDevelopment;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class AgricultureModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Agriculture & Rural Development',
        'version' => '1.0.0',
        'description' => 'Comprehensive agricultural services and rural development support system',
        'author' => 'TPT Government Platform',
        'category' => 'agricultural_services',
        'dependencies' => ['database', 'workflow', 'payment', 'notification']
    ];

    /**
     * Module dependencies
     */
    protected array $dependencies = [
        ['name' => 'Database', 'version' => '>=1.0.0'],
        ['name' => 'WorkflowEngine', 'version' => '>=1.0.0'],
        ['name' => 'PaymentGateway', 'version' => '>=1.0.0'],
        ['name' => 'NotificationManager', 'version' => '>=1.0.0']
    ];

    /**
     * Module permissions
     */
    protected array $permissions = [
        'agriculture.view' => 'View agricultural applications and records',
        'agriculture.create' => 'Create new agricultural applications',
        'agriculture.edit' => 'Edit agricultural applications',
        'agriculture.review' => 'Review agricultural applications',
        'agriculture.approve' => 'Approve agricultural applications',
        'agriculture.reject' => 'Reject agricultural applications',
        'agriculture.subsidies' => 'Manage agricultural subsidies',
        'agriculture.compliance' => 'Monitor agricultural compliance',
        'agriculture.reports' => 'Generate agricultural reports'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'farm_registrations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'registration_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'farmer_id' => 'INT NOT NULL',
            'farm_name' => 'VARCHAR(255) NOT NULL',
            'farm_address' => 'TEXT NOT NULL',
            'farm_size_hectares' => 'DECIMAL(10,2) NOT NULL',
            'farm_type' => "ENUM('crop_farming','livestock','mixed','horticulture','dairy','poultry','aquaculture','forestry') NOT NULL",
            'ownership_type' => "ENUM('individual','partnership','company','cooperative','leasehold') NOT NULL",
            'registration_date' => 'DATETIME NOT NULL',
            'status' => "ENUM('pending','active','suspended','cancelled') DEFAULT 'pending'",
            'certification_level' => "ENUM('basic','organic','sustainable','premium') DEFAULT 'basic'",
            'contact_details' => 'JSON',
            'farm_details' => 'JSON',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'agricultural_subsidies' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'subsidy_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'farmer_id' => 'INT NOT NULL',
            'farm_id' => 'VARCHAR(20) NOT NULL',
            'subsidy_type' => "ENUM('crop_insurance','equipment_grant','seed_subsidy','fertilizer_subsidy','irrigation_support','organic_conversion','disaster_relief','research_grant') NOT NULL",
            'application_date' => 'DATETIME NOT NULL',
            'approval_date' => 'DATETIME NULL',
            'amount_requested' => 'DECIMAL(12,2) NOT NULL',
            'amount_approved' => 'DECIMAL(12,2) NULL',
            'status' => "ENUM('draft','submitted','under_review','approved','rejected','paid','cancelled') DEFAULT 'draft'",
            'eligibility_criteria' => 'JSON',
            'supporting_documents' => 'JSON',
            'payment_date' => 'DATETIME NULL',
            'conditions' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'GET',
            'path' => '/api/agriculture/farms',
            'handler' => 'getFarms',
            'auth' => true,
            'permissions' => ['agriculture.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/agriculture/farms',
            'handler' => 'registerFarm',
            'auth' => true,
            'permissions' => ['agriculture.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/agriculture/subsidies',
            'handler' => 'getSubsidies',
            'auth' => true,
            'permissions' => ['agriculture.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/agriculture/subsidies',
            'handler' => 'applyForSubsidy',
            'auth' => true,
            'permissions' => ['agriculture.create']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'farm_registration_process' => [
            'name' => 'Farm Registration Process',
            'description' => 'Complete workflow for farm registration and certification',
            'steps' => [
                'draft' => ['name' => 'Application Draft', 'next' => 'submitted'],
                'submitted' => ['name' => 'Application Submitted', 'next' => 'under_review'],
                'under_review' => ['name' => 'Under Review', 'next' => ['approved', 'additional_info']],
                'additional_info' => ['name' => 'Additional Information Required', 'next' => 'under_review'],
                'approved' => ['name' => 'Registration Approved', 'next' => 'certified'],
                'certified' => ['name' => 'Farm Certified', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'farm_registration' => [
            'name' => 'Farm Registration Application',
            'fields' => [
                'farm_name' => ['type' => 'text', 'required' => true, 'label' => 'Farm Name'],
                'farm_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Farm Address'],
                'farm_size_hectares' => ['type' => 'number', 'required' => true, 'label' => 'Farm Size (Hectares)', 'step' => '0.01'],
                'farm_type' => ['type' => 'select', 'required' => true, 'label' => 'Farm Type'],
                'ownership_type' => ['type' => 'select', 'required' => true, 'label' => 'Ownership Type'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                'certification_level' => ['type' => 'select', 'required' => true, 'label' => 'Desired Certification Level']
            ],
            'documents' => [
                'land_title' => ['required' => true, 'label' => 'Land Title/Deed'],
                'id_proof' => ['required' => true, 'label' => 'Identification Proof'],
                'farm_photos' => ['required' => true, 'label' => 'Farm Photographs']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'farm_overview' => [
            'name' => 'Farm Registration Overview Report',
            'description' => 'Summary of registered farms and their status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'farm_type' => ['type' => 'select', 'required' => false],
                'certification_level' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'registration_id', 'farm_name', 'farm_type', 'farm_size_hectares',
                'certification_level', 'registration_date', 'status'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'farm_registration_submitted' => [
            'name' => 'Farm Registration Application Submitted',
            'template' => 'Your farm registration application {registration_id} has been submitted successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['farm_registration_created']
        ]
    ];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Get module metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'registration_processing_days' => 14,
            'subsidy_processing_days' => 21,
            'inspection_lead_time_days' => 7,
            'max_farm_size_hectares' => 1000,
            'min_farm_size_hectares' => 0.1,
            'subsidy_budget_limit' => 50000.00,
            'auto_approval_threshold' => 5000.00
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        // Initialize module-specific data
    }

    /**
     * Register farm (API handler)
     */
    public function registerFarm(array $farmData): array
    {
        // Validate farm data
        $validation = $this->validateFarmData($farmData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate registration ID
        $registrationId = $this->generateRegistrationId();

        // Create farm registration record
        $farm = [
            'registration_id' => $registrationId,
            'farmer_id' => $farmData['farmer_id'],
            'farm_name' => $farmData['farm_name'],
            'farm_address' => $farmData['farm_address'],
            'farm_size_hectares' => $farmData['farm_size_hectares'],
            'farm_type' => $farmData['farm_type'],
            'ownership_type' => $farmData['ownership_type'],
            'registration_date' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'certification_level' => $farmData['certification_level'],
            'contact_details' => json_encode([
                'phone' => $farmData['contact_phone'],
                'email' => $farmData['contact_email']
            ]),
            'farm_details' => json_encode([]),
            'documents' => json_encode($farmData['documents'] ?? []),
            'notes' => $farmData['notes'] ?? ''
        ];

        // Save to database
        $this->saveFarmRegistration($farm);

        return [
            'success' => true,
            'registration_id' => $registrationId,
            'farm_name' => $farmData['farm_name'],
            'status' => 'pending',
            'message' => 'Farm registration submitted successfully'
        ];
    }

    /**
     * Apply for subsidy (API handler)
     */
    public function applyForSubsidy(array $subsidyData): array
    {
        // Validate subsidy data
        $validation = $this->validateSubsidyData($subsidyData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate subsidy ID
        $subsidyId = $this->generateSubsidyId();

        // Create subsidy application record
        $subsidy = [
            'subsidy_id' => $subsidyId,
            'farmer_id' => $subsidyData['farmer_id'],
            'farm_id' => $subsidyData['farm_id'],
            'subsidy_type' => $subsidyData['subsidy_type'],
            'application_date' => date('Y-m-d H:i:s'),
            'amount_requested' => $subsidyData['amount_requested'],
            'status' => 'draft',
            'eligibility_criteria' => json_encode([]),
            'supporting_documents' => json_encode($subsidyData['documents'] ?? []),
            'notes' => $subsidyData['notes'] ?? ''
        ];

        // Save to database
        $this->saveSubsidyApplication($subsidy);

        return [
            'success' => true,
            'subsidy_id' => $subsidyId,
            'subsidy_type' => $subsidyData['subsidy_type'],
            'amount_requested' => $subsidyData['amount_requested'],
            'status' => 'draft',
            'message' => 'Subsidy application created successfully'
        ];
    }

    /**
     * Get farms (API handler)
     */
    public function getFarms(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM farm_registrations WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['farm_type'])) {
                $sql .= " AND farm_type = ?";
                $params[] = $filters['farm_type'];
            }

            if (isset($filters['farmer_id'])) {
                $sql .= " AND farmer_id = ?";
                $params[] = $filters['farmer_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['contact_details'] = json_decode($result['contact_details'], true);
                $result['farm_details'] = json_decode($result['farm_details'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting farms: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve farms'
            ];
        }
    }

    /**
     * Get subsidies (API handler)
     */
    public function getSubsidies(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM agricultural_subsidies WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['subsidy_type'])) {
                $sql .= " AND subsidy_type = ?";
                $params[] = $filters['subsidy_type'];
            }

            if (isset($filters['farmer_id'])) {
                $sql .= " AND farmer_id = ?";
                $params[] = $filters['farmer_id'];
            }

            $sql .= " ORDER BY application_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['eligibility_criteria'] = json_decode($result['eligibility_criteria'], true);
                $result['supporting_documents'] = json_decode($result['supporting_documents'], true);
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting subsidies: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve subsidies'
            ];
        }
    }

    /**
     * Generate registration ID
     */
    private function generateRegistrationId(): string
    {
        return 'FR' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate subsidy ID
     */
    private function generateSubsidyId(): string
    {
        return 'SUB' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save farm registration
     */
    private function saveFarmRegistration(array $farm): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO farm_registrations (
                registration_id, farmer_id, farm_name, farm_address,
                farm_size_hectares, farm_type, ownership_type, registration_date,
                status, certification_level, contact_details, farm_details,
                documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $farm['registration_id'],
                $farm['farmer_id'],
                $farm['farm_name'],
                $farm['farm_address'],
                $farm['farm_size_hectares'],
                $farm['farm_type'],
                $farm['ownership_type'],
                $farm['registration_date'],
                $farm['status'],
                $farm['certification_level'],
                $farm['contact_details'],
                $farm['farm_details'],
                $farm['documents'],
                $farm['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving farm registration: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save subsidy application
     */
    private function saveSubsidyApplication(array $subsidy): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO agricultural_subsidies (
                subsidy_id, farmer_id, farm_id, subsidy_type,
                application_date, amount_requested, status,
                eligibility_criteria, supporting_documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $subsidy['subsidy_id'],
                $subsidy['farmer_id'],
                $subsidy['farm_id'],
                $subsidy['subsidy_type'],
                $subsidy['application_date'],
                $subsidy['amount_requested'],
                $subsidy['status'],
                $subsidy['eligibility_criteria'],
                $subsidy['supporting_documents'],
                $subsidy['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving subsidy application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate farm data
     */
    private function validateFarmData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'farmer_id', 'farm_name', 'farm_address',
            'farm_size_hectares', 'farm_type', 'ownership_type',
            'contact_phone', 'contact_email', 'certification_level'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate farm size
        if (isset($data['farm_size_hectares'])) {
            $size = floatval($data['farm_size_hectares']);
            if ($size < $this->config['min_farm_size_hectares'] || $size > $this->config['max_farm_size_hectares']) {
                $errors[] = "Farm size must be between {$this->config['min_farm_size_hectares']} and {$this->config['max_farm_size_hectares']} hectares";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate subsidy data
     */
    private function validateSubsidyData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'farmer_id', 'farm_id', 'subsidy_type', 'amount_requested'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate amount
        if (isset($data['amount_requested'])) {
            $amount = floatval($data['amount_requested']);
            if ($amount <= 0 || $amount > $this->config['subsidy_budget_limit']) {
                $errors[] = "Amount must be between 0 and {$this->config['subsidy_budget_limit']}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

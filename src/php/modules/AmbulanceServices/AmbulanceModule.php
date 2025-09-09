<?php
/**
 * TPT Government Platform - Ambulance Services Module
 *
 * Comprehensive emergency medical response and ambulance management system
 * for government emergency medical services and healthcare coordination
 */

namespace Modules\AmbulanceServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\AIService;

class AmbulanceModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Ambulance Services',
        'version' => '2.0.0',
        'description' => 'Comprehensive emergency medical response and ambulance management system',
        'author' => 'TPT Government Platform',
        'category' => 'emergency_services',
        'dependencies' => ['database', 'workflow', 'notification', 'ai']
    ];

    /**
     * Module dependencies
     */
    protected array $dependencies = [
        ['name' => 'Database', 'version' => '>=1.0.0'],
        ['name' => 'WorkflowEngine', 'version' => '>=1.0.0'],
        ['name' => 'NotificationManager', 'version' => '>=1.0.0'],
        ['name' => 'AIService', 'version' => '>=1.0.0']
    ];

    /**
     * Module permissions
     */
    protected array $permissions = [
        'ambulance.view' => 'View ambulance calls and patient information',
        'ambulance.create' => 'Create emergency medical calls',
        'ambulance.update' => 'Update call information and patient records',
        'ambulance.dispatch' => 'Dispatch ambulances and manage response',
        'ambulance.medical' => 'Access medical information and treatment records',
        'ambulance.fleet' => 'Manage ambulance fleet and equipment',
        'ambulance.training' => 'Manage paramedic training and certification',
        'ambulance.reports' => 'Access ambulance service reports and analytics',
        'ambulance.admin' => 'Full administrative access to ambulance services',
        'ambulance.emergency' => 'Handle emergency medical situations'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'ambulance_calls' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'call_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'priority' => "ENUM('critical','urgent','non_urgent','routine') DEFAULT 'urgent'",
            'status' => "ENUM('received','dispatched','en_route','on_scene','transporting','at_hospital','completed','cancelled') DEFAULT 'received'",
            'caller_type' => "ENUM('patient','family','bystander','medical_facility','police','fire','other') DEFAULT 'other'",
            'caller_name' => 'VARCHAR(255)',
            'caller_phone' => 'VARCHAR(20)',
            'patient_name' => 'VARCHAR(255)',
            'patient_age' => 'INT',
            'patient_gender' => "ENUM('male','female','other','unknown')",
            'chief_complaint' => 'TEXT',
            'location_address' => 'TEXT',
            'location_coordinates' => 'VARCHAR(100)',
            'destination_hospital' => 'VARCHAR(255)',
            'ambulance_unit' => 'VARCHAR(20)',
            'paramedic_team' => 'JSON',
            'dispatch_time' => 'DATETIME',
            'en_route_time' => 'DATETIME',
            'on_scene_time' => 'DATETIME',
            'at_hospital_time' => 'DATETIME',
            'completed_time' => 'DATETIME',
            'response_time_minutes' => 'INT',
            'scene_time_minutes' => 'INT',
            'transport_time_minutes' => 'INT',
            'total_time_minutes' => 'INT',
            'treatment_provided' => 'JSON',
            'medications_administered' => 'JSON',
            'vital_signs' => 'JSON',
            'patient_condition' => 'TEXT',
            'outcome' => "ENUM('transported','treated_on_scene','refused_care','deceased','transferred_to_other_service')",
            'notes' => 'TEXT',
            'attachments' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'ambulance_units' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'unit_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'unit_type' => "ENUM('als','bls','critical_care','neonatal','pediatric','other') DEFAULT 'bls'",
            'license_plate' => 'VARCHAR(20)',
            'vehicle_make' => 'VARCHAR(50)',
            'vehicle_model' => 'VARCHAR(50)',
            'vehicle_year' => 'YEAR',
            'station_id' => 'INT',
            'status' => "ENUM('available','dispatched','on_scene','transporting','maintenance','out_of_service') DEFAULT 'available'",
            'current_location' => 'VARCHAR(100)',
            'mileage' => 'INT DEFAULT 0',
            'last_service' => 'DATE',
            'next_service' => 'DATE',
            'equipment_status' => 'JSON',
            'medication_inventory' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'paramedics' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'badge_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'first_name' => 'VARCHAR(100) NOT NULL',
            'last_name' => 'VARCHAR(100) NOT NULL',
            'certification_level' => "ENUM('emt','aemt','paramedic','critical_care','other') DEFAULT 'emt'",
            'license_number' => 'VARCHAR(50)',
            'license_expiry' => 'DATE',
            'station_id' => 'INT',
            'shift_schedule' => 'JSON',
            'specialties' => 'JSON',
            'emergency_contact' => 'JSON',
            'medical_clearance' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'medical_equipment' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'equipment_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'equipment_type' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'manufacturer' => 'VARCHAR(100)',
            'model' => 'VARCHAR(100)',
            'serial_number' => 'VARCHAR(100)',
            'unit_id' => 'VARCHAR(20)',
            'status' => "ENUM('operational','maintenance','calibration','expired','disposed') DEFAULT 'operational'",
            'purchase_date' => 'DATE',
            'warranty_expiry' => 'DATE',
            'last_calibration' => 'DATE',
            'next_calibration' => 'DATE',
            'maintenance_schedule' => 'VARCHAR(100)',
            'specifications' => 'JSON',
            'maintenance_history' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'hospitals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'hospital_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'hospital_name' => 'VARCHAR(255) NOT NULL',
            'address' => 'TEXT',
            'coordinates' => 'VARCHAR(100)',
            'phone' => 'VARCHAR(20)',
            'emergency_department' => 'BOOLEAN DEFAULT TRUE',
            'trauma_level' => "ENUM('1','2','3','4','5')",
            'specialties' => 'JSON',
            'capacity_status' => 'JSON',
            'transport_protocols' => 'JSON',
            'contact_persons' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'training_records' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'paramedic_id' => 'INT NOT NULL',
            'training_type' => 'VARCHAR(255) NOT NULL',
            'training_provider' => 'VARCHAR(255)',
            'certification_number' => 'VARCHAR(100)',
            'issue_date' => 'DATE',
            'expiry_date' => 'DATE',
            'training_hours' => 'DECIMAL(5,2)',
            'instructor' => 'VARCHAR(255)',
            'status' => "ENUM('completed','in_progress','expired','revoked') DEFAULT 'completed'",
            'score' => 'DECIMAL(5,2)',
            'skills_assessed' => 'JSON',
            'notes' => 'TEXT',
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'patient_records' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'patient_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'first_name' => 'VARCHAR(100)',
            'last_name' => 'VARCHAR(100)',
            'date_of_birth' => 'DATE',
            'gender' => "ENUM('male','female','other')",
            'address' => 'TEXT',
            'phone' => 'VARCHAR(20)',
            'emergency_contact' => 'JSON',
            'medical_history' => 'JSON',
            'allergies' => 'JSON',
            'medications' => 'JSON',
            'insurance_info' => 'JSON',
            'consent_given' => 'BOOLEAN DEFAULT FALSE',
            'last_updated' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ambulance_analytics' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'date' => 'DATE NOT NULL',
            'station_id' => 'INT',
            'calls_received' => 'INT DEFAULT 0',
            'calls_completed' => 'INT DEFAULT 0',
            'avg_response_time' => 'INT DEFAULT 0', // minutes
            'avg_scene_time' => 'INT DEFAULT 0', // minutes
            'avg_transport_time' => 'INT DEFAULT 0', // minutes
            'critical_calls' => 'INT DEFAULT 0',
            'patients_transported' => 'INT DEFAULT 0',
            'patients_refused' => 'INT DEFAULT 0',
            'cardiac_arrests' => 'INT DEFAULT 0',
            'successful_resuscitations' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        // Emergency Calls
        ['method' => 'GET', 'path' => '/api/ambulance/calls', 'handler' => 'getCalls', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/ambulance/calls', 'handler' => 'createCall', 'auth' => true, 'permissions' => ['ambulance.create']],
        ['method' => 'GET', 'path' => '/api/ambulance/calls/{id}', 'handler' => 'getCall', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/ambulance/calls/{id}', 'handler' => 'updateCall', 'auth' => true, 'permissions' => ['ambulance.update']],
        ['method' => 'POST', 'path' => '/api/ambulance/calls/{id}/complete', 'handler' => 'completeCall', 'auth' => true, 'permissions' => ['ambulance.update']],

        // Ambulance Units
        ['method' => 'GET', 'path' => '/api/ambulance/units', 'handler' => 'getUnits', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/ambulance/units', 'handler' => 'createUnit', 'auth' => true, 'permissions' => ['ambulance.fleet']],
        ['method' => 'GET', 'path' => '/api/ambulance/units/{id}', 'handler' => 'getUnit', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/ambulance/units/{id}', 'handler' => 'updateUnit', 'auth' => true, 'permissions' => ['ambulance.fleet']],

        // Paramedics
        ['method' => 'GET', 'path' => '/api/ambulance/paramedics', 'handler' => 'getParamedics', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/ambulance/paramedics', 'handler' => 'createParamedic', 'auth' => true, 'permissions' => ['ambulance.admin']],
        ['method' => 'GET', 'path' => '/api/ambulance/paramedics/{id}', 'handler' => 'getParamedic', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/ambulance/paramedics/{id}', 'handler' => 'updateParamedic', 'auth' => true, 'permissions' => ['ambulance.admin']],

        // Equipment
        ['method' => 'GET', 'path' => '/api/ambulance/equipment', 'handler' => 'getEquipment', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/ambulance/equipment', 'handler' => 'createEquipment', 'auth' => true, 'permissions' => ['ambulance.fleet']],
        ['method' => 'GET', 'path' => '/api/ambulance/equipment/{id}', 'handler' => 'getEquipmentItem', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/ambulance/equipment/{id}', 'handler' => 'updateEquipment', 'auth' => true, 'permissions' => ['ambulance.fleet']],

        // Hospitals
        ['method' => 'GET', 'path' => '/api/ambulance/hospitals', 'handler' => 'getHospitals', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/ambulance/hospitals', 'handler' => 'createHospital', 'auth' => true, 'permissions' => ['ambulance.admin']],

        // Training
        ['method' => 'GET', 'path' => '/api/ambulance/training', 'handler' => 'getTrainingRecords', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/ambulance/training', 'handler' => 'createTrainingRecord', 'auth' => true, 'permissions' => ['ambulance.training']],

        // Patient Records
        ['method' => 'GET', 'path' => '/api/ambulance/patients', 'handler' => 'getPatients', 'auth' => true, 'permissions' => ['ambulance.medical']],
        ['method' => 'POST', 'path' => '/api/ambulance/patients', 'handler' => 'createPatient', 'auth' => true, 'permissions' => ['ambulance.medical']],
        ['method' => 'GET', 'path' => '/api/ambulance/patients/{id}', 'handler' => 'getPatient', 'auth' => true, 'permissions' => ['ambulance.medical']],

        // Analytics and Reporting
        ['method' => 'GET', 'path' => '/api/ambulance/analytics', 'handler' => 'getAnalytics', 'auth' => true, 'permissions' => ['ambulance.reports']],
        ['method' => 'GET', 'path' => '/api/ambulance/reports', 'handler' => 'getReports', 'auth' => true, 'permissions' => ['ambulance.reports']],

        // Public Emergency Reporting
        ['method' => 'POST', 'path' => '/api/public/medical-emergency', 'handler' => 'reportMedicalEmergency', 'auth' => false]
    ];

    /**
     * Response time standards (in minutes)
     */
    private array $responseStandards = [
        'critical' => 8,    // 8 minutes
        'urgent' => 15,     // 15 minutes
        'non_urgent' => 30, // 30 minutes
        'routine' => 60     // 60 minutes
    ];

    /**
     * Medical emergency types
     */
    private array $emergencyTypes = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->initializeEmergencyTypes();
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
            'auto_dispatch' => true,
            'hospital_integration' => true,
            'patient_records' => true,
            'equipment_tracking' => true,
            'training_management' => true,
            'analytics_enabled' => true,
            'emergency_notifications' => true,
            'public_reporting' => true,
            'response_time_tracking' => true,
            'quality_assurance' => true,
            'billing_integration' => true,
            'telemedicine_support' => true
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeEmergencyTypes();
        $this->setupDefaultStations();
        $this->initializeHospitals();
        $this->setupNotificationTemplates();
    }

    /**
     * Initialize emergency types
     */
    private function initializeEmergencyTypes(): void
    {
        $this->emergencyTypes = [
            'cardiac_arrest' => [
                'name' => 'Cardiac Arrest',
                'priority' => 'critical',
                'requires' => ['als_unit', 'defibrillator'],
                'estimated_response' => 5,
                'protocols' => ['cpr', 'defibrillation', 'airway_management']
            ],
            'chest_pain' => [
                'name' => 'Chest Pain',
                'priority' => 'urgent',
                'requires' => ['als_unit', 'ecg_monitor'],
                'estimated_response' => 8,
                'protocols' => ['ecg', 'oxygen', 'pain_management']
            ],
            'breathing_difficulty' => [
                'name' => 'Breathing Difficulty',
                'priority' => 'urgent',
                'requires' => ['als_unit', 'oxygen'],
                'estimated_response' => 8,
                'protocols' => ['oxygen_therapy', 'airway_management']
            ],
            'severe_bleeding' => [
                'name' => 'Severe Bleeding',
                'priority' => 'urgent',
                'requires' => ['als_unit', 'trauma_kit'],
                'estimated_response' => 8,
                'protocols' => ['hemorrhage_control', 'fluid_resuscitation']
            ],
            'stroke_symptoms' => [
                'name' => 'Stroke Symptoms',
                'priority' => 'urgent',
                'requires' => ['als_unit'],
                'estimated_response' => 10,
                'protocols' => ['stroke_assessment', 'time_critical_transport']
            ],
            'unconscious' => [
                'name' => 'Unconscious',
                'priority' => 'urgent',
                'requires' => ['als_unit'],
                'estimated_response' => 8,
                'protocols' => ['airway_management', 'vital_signs']
            ],
            'motor_vehicle_accident' => [
                'name' => 'Motor Vehicle Accident',
                'priority' => 'urgent',
                'requires' => ['als_unit', 'trauma_kit'],
                'estimated_response' => 10,
                'protocols' => ['trauma_assessment', 'multiple_casualty']
            ],
            'fall_injury' => [
                'name' => 'Fall Injury',
                'priority' => 'non_urgent',
                'requires' => ['bls_unit'],
                'estimated_response' => 15,
                'protocols' => ['fall_assessment', 'pain_management']
            ]
        ];
    }

    /**
     * Setup default ambulance stations
     */
    private function setupDefaultStations(): void
    {
        // This would create default ambulance station records
        // Implementation would depend on specific jurisdiction
    }

    /**
     * Initialize hospitals
     */
    private function initializeHospitals(): void
    {
        // This would set up hospital integration
        // Implementation would depend on healthcare system
    }

    /**
     * Setup notification templates
     */
    private function setupNotificationTemplates(): void
    {
        // Setup SMS and email templates for medical emergencies
    }

    /**
     * Create emergency call
     */
    public function createCall(array $callData, array $metadata = []): array
    {
        try {
            // Validate call data
            $validation = $this->validateCallData($callData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate call ID
            $callId = $this->generateCallId();

            // Determine priority using AI analysis
            $aiAnalysis = $this->analyzeEmergencyContent($callData);
            $priority = $aiAnalysis['priority'] ?? $this->assessCallPriority($callData);

            // Prepare call data
            $call = [
                'call_id' => $callId,
                'priority' => $priority,
                'status' => 'received',
                'caller_type' => $callData['caller_type'] ?? 'other',
                'caller_name' => $callData['caller_name'] ?? '',
                'caller_phone' => $callData['caller_phone'] ?? '',
                'patient_name' => $callData['patient_name'] ?? '',
                'patient_age' => $callData['patient_age'] ?? null,
                'patient_gender' => $callData['patient_gender'] ?? 'unknown',
                'chief_complaint' => $callData['chief_complaint'],
                'location_address' => $callData['location_address'],
                'location_coordinates' => $callData['location_coordinates'] ?? null,
                'metadata' => json_encode($metadata)
            ];

            // Save to database
            $this->saveCall($call);

            // Auto-dispatch ambulance if enabled
            if ($this->config['auto_dispatch']) {
                $this->autoDispatchAmbulance($callId, $call);
            }

            // Send emergency notifications
            $this->sendEmergencyNotifications($call);

            // Log call creation
            $this->logCallEvent($callId, 'created', 'Emergency call received and logged');

            return [
                'success' => true,
                'call_id' => $callId,
                'priority' => $priority,
                'estimated_response_time' => $this->responseStandards[$priority] ?? 15,
                'ambulance_dispatched' => $this->getDispatchedAmbulance($callId),
                'message' => 'Emergency call received successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error creating emergency call: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create emergency call'
            ];
        }
    }

    /**
     * Update call
     */
    public function updateCall(string $callId, array $updateData, int $updatedBy): array
    {
        try {
            $call = $this->getCallById($callId);
            if (!$call) {
                return [
                    'success' => false,
                    'error' => 'Call not found'
                ];
            }

            // Track status changes
            if (isset($updateData['status'])) {
                $this->handleStatusChange($callId, $call['status'], $updateData['status'], $updatedBy);
            }

            // Update timestamps based on status
            $updateData = $this->updateCallTimestamps($callId, $updateData);

            // Update call
            $updateData['updated_by'] = $updatedBy;
            $this->updateCallInDatabase($callId, $updateData);

            // Calculate response times
            $this->calculateResponseTimes($callId);

            // Send notifications if needed
            $this->sendCallNotifications('updated', array_merge($call, $updateData));

            return [
                'success' => true,
                'message' => 'Call updated successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error updating call: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update call'
            ];
        }
    }

    /**
     * Complete call
     */
    public function completeCall(string $callId, array $completionData, int $completedBy): array
    {
        try {
            $updateData = [
                'status' => 'completed',
                'completed_time' => date('Y-m-d H:i:s'),
                'outcome' => $completionData['outcome'] ?? 'transported',
                'treatment_provided' => json_encode($completionData['treatment_provided'] ?? []),
                'medications_administered' => json_encode($completionData['medications_administered'] ?? []),
                'vital_signs' => json_encode($completionData['vital_signs'] ?? []),
                'patient_condition' => $completionData['patient_condition'] ?? '',
                'notes' => $completionData['notes'] ?? ''
            ];

            $result = $this->updateCall($callId, $updateData, $completedBy);

            if ($result['success']) {
                // Update ambulance status
                $this->updateAmbulanceStatus($callId, 'available');

                // Send completion notifications
                $this->sendCompletionNotifications($callId, $completionData);

                // Update patient records if applicable
                if (isset($completionData['patient_id'])) {
                    $this->updatePatientRecord($completionData['patient_id'], $completionData);
                }
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error completing call: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to complete call'
            ];
        }
    }

    /**
     * Get emergency calls
     */
    public function getCalls(array $filters = [], array $pagination = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM ambulance_calls WHERE 1=1";
            $params = [];

            // Apply filters
            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['priority'])) {
                $sql .= " AND priority = ?";
                $params[] = $filters['priority'];
            }

            if (isset($filters['ambulance_unit'])) {
                $sql .= " AND ambulance_unit = ?";
                $params[] = $filters['ambulance_unit'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND dispatch_time >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND dispatch_time <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY dispatch_time DESC";

            // Add pagination
            $limit = $pagination['limit'] ?? 50;
            $offset = $pagination['offset'] ?? 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $calls = $db->fetchAll($sql, $params);

            // Decode JSON fields and enrich data
            foreach ($calls as &$call) {
                $call['paramedic_team'] = json_decode($call['paramedic_team'], true);
                $call['treatment_provided'] = json_decode($call['treatment_provided'], true);
                $call['medications_administered'] = json_decode($call['medications_administered'], true);
                $call['vital_signs'] = json_decode($call['vital_signs'], true);
                $call['attachments'] = json_decode($call['attachments'], true);
                $call['metadata'] = json_decode($call['metadata'], true);

                // Add calculated fields
                $call['total_response_time'] = $this->calculateTotalResponseTime($call);
                $call['performance_rating'] = $this->calculatePerformanceRating($call);
            }

            return [
                'success' => true,
                'data' => $calls,
                'count' => count($calls)
            ];

        } catch (\Exception $e) {
            error_log("Error getting calls: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve calls'
            ];
        }
    }

    /**
     * Get ambulance units
     */
    public function getUnits(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM ambulance_units WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['unit_type'])) {
                $sql .= " AND unit_type = ?";
                $params[] = $filters['unit_type'];
            }

            if (isset($filters['station_id'])) {
                $sql .= " AND station_id = ?";
                $params[] = $filters['station_id'];
            }

            $sql .= " ORDER BY unit_id ASC";

            $units = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($units as &$unit) {
                $unit['equipment_status'] = json_decode($unit['equipment_status'], true);
                $unit['medication_inventory'] = json_decode($unit['medication_inventory'], true);
            }

            return [
                'success' => true,
                'data' => $units,
                'count' => count($units)
            ];

        } catch (\Exception $e) {
            error_log("Error getting units: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve units'
            ];
        }
    }

    /**
     * Get paramedics
     */
    public function getParamedics(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM paramedics WHERE 1=1";
            $params = [];

            if (isset($filters['station_id'])) {
                $sql .= " AND station_id = ?";
                $params[] = $filters['station_id'];
            }

            if (isset($filters['certification_level'])) {
                $sql .= " AND certification_level = ?";
                $params[] = $filters['certification_level'];
            }

            if (isset($filters['is_active'])) {
                $sql .= " AND is_active = ?";
                $params[] = $filters['is_active'];
            }

            $sql .= " ORDER BY last_name ASC";

            $paramedics = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($paramedics as &$paramedic) {
                $paramedic['shift_schedule'] = json_decode($paramedic['shift_schedule'], true);
                $paramedic['specialties'] = json_decode($paramedic['specialties'], true);
                $paramedic['emergency_contact'] = json_decode($paramedic['emergency_contact'], true);
                $paramedic['medical_clearance'] = json_decode($paramedic['medical_clearance'], true);
            }

            return [
                'success' => true,
                'data' => $paramedics,
                'count' => count($paramedics)
            ];

        } catch (\Exception $e) {
            error_log("Error getting paramedics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve paramedics'
            ];
        }
    }

    /**
     * Get ambulance analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT
                        COUNT(*) as total_calls,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as calls_completed,
                        COUNT(CASE WHEN outcome = 'transported' THEN 1 END) as patients_transported,
                        AVG(response_time_minutes) as avg_response_time,
                        AVG(scene_time_minutes) as avg_scene_time,
                        AVG(transport_time_minutes) as avg_transport_time,
                        COUNT(CASE WHEN priority = 'critical' THEN 1 END) as critical_calls,
                        COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_calls,
                        SUM(CASE WHEN outcome = 'deceased' THEN 1 ELSE 0 END) as fatalities
                    FROM ambulance_calls
                    WHERE 1=1";

            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND dispatch_time >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND dispatch_time <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['station_id'])) {
                $sql .= " AND ambulance_unit IN (SELECT unit_id FROM ambulance_units WHERE station_id = ?)";
                $params[] = $filters['station_id'];
            }

            $result = $db->fetch($sql, $params);

            // Calculate additional metrics
            $result['completion_rate'] = $result['total_calls'] > 0
                ? round(($result['calls_completed'] / $result['total_calls']) * 100, 2)
                : 0;

            $result['transport_rate'] = $result['calls_completed'] > 0
                ? round(($result['patients_transported'] / $result['calls_completed']) * 100, 2)
                : 0;

            return [
                'success' => true,
                'data' => $result,
                'filters' => $filters
            ];

        } catch (\Exception $e) {
            error_log("Error getting analytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve analytics'
            ];
        }
    }

    /**
     * Report medical emergency (public endpoint)
     */
    public function reportMedicalEmergency(array $emergencyData): array
    {
        try {
            // Validate emergency data
            $validation = $this->validateEmergencyData($emergencyData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Create call from emergency report
            $callData = [
                'caller_type' => $emergencyData['caller_type'] ?? 'bystander',
                'caller_name' => $emergencyData['caller_name'] ?? 'Anonymous',
                'caller_phone' => $emergencyData['caller_phone'],
                'patient_name' => $emergencyData['patient_name'] ?? 'Unknown',
                'patient_age' => $emergencyData['patient_age'] ?? null,
                'patient_gender' => $emergencyData['patient_gender'] ?? 'unknown',
                'chief_complaint' => $emergencyData['chief_complaint'],
                'location_address' => $emergencyData['address'],
                'location_coordinates' => $emergencyData['coordinates'] ?? null
            ];

            $metadata = [
                'channel' => 'public_app',
                'emergency_level' => 'high',
                'reported_symptoms' => $emergencyData['symptoms'] ?? [],
                'conscious' => $emergencyData['conscious'] ?? 'unknown',
                'breathing' => $emergencyData['breathing'] ?? 'unknown'
            ];

            $result = $this->createCall($callData, $metadata);

            if ($result['success']) {
                // Send immediate confirmation
                $this->sendEmergencyConfirmation($result['call_id'], $emergencyData);
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error reporting medical emergency: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to report medical emergency'
            ];
        }
    }

    // Helper methods (implementations would be added)

    private function generateCallId(): string
    {
        return 'AMB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function validateCallData(array $data): array
    {
        $errors = [];

        if (empty($data['chief_complaint'])) {
            $errors[] = 'Chief complaint is required';
        }

        if (empty($data['location_address'])) {
            $errors[] = 'Location address is required';
        }

        if (empty($data['caller_phone'])) {
            $errors[] = 'Caller phone number is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateEmergencyData(array $data): array
    {
        $errors = [];

        if (empty($data['address'])) {
            $errors[] = 'Address is required';
        }

        if (empty($data['caller_phone'])) {
            $errors[] = 'Phone number is required for emergency reporting';
        }

        if (empty($data['chief_complaint'])) {
            $errors[] = 'Description of emergency is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function assessCallPriority(array $callData): string
    {
        $complaint = strtolower($callData['chief_complaint']);

        // Critical conditions
        if (strpos($complaint, 'cardiac arrest') !== false ||
            strpos($complaint, 'not breathing') !== false ||
            strpos($complaint, 'unconscious') !== false) {
            return 'critical';
        }

        // Urgent conditions
        if (strpos($complaint, 'chest pain') !== false ||
            strpos($complaint, 'severe bleeding') !== false ||
            strpos($complaint, 'stroke') !== false ||
            strpos($complaint, 'breathing difficulty') !== false) {
            return 'urgent';
        }

        // Non-urgent conditions
        if (strpos($complaint, 'fall') !== false ||
            strpos($complaint, 'minor injury') !== false) {
            return 'non_urgent';
        }

        return 'urgent'; // Default to urgent for safety
    }

    private function analyzeEmergencyContent(array $callData): array
    {
        // This would use AI to analyze the emergency content
        // For now, return basic analysis
        return [
            'priority' => $this->assessCallPriority($callData),
            'suggested_unit_type' => 'als',
            'estimated_response_time' => 8,
            'keywords_identified' => []
        ];
    }

    private function autoDispatchAmbulance(string $callId, array $call): void
    {
        // Implementation for automatic ambulance dispatch
    }

    private function sendEmergencyNotifications(array $call): void
    {
        // Implementation for sending emergency notifications
    }

    private function logCallEvent(string $callId, string $event, string $description): void
    {
        // Implementation for logging call events
    }

    private function getDispatchedAmbulance(string $callId): ?string
    {
        // Implementation for getting dispatched ambulance
        return null;
    }

    private function handleStatusChange(string $callId, string $oldStatus, string $newStatus, int $updatedBy): void
    {
        // Implementation for handling status changes
    }

    private function updateCallTimestamps(string $callId, array $updateData): array
    {
        // Implementation for updating call timestamps
        return $updateData;
    }

    private function calculateResponseTimes(string $callId): void
    {
        // Implementation for calculating response times
    }

    private function sendCallNotifications(string $event, array $call): void
    {
        // Implementation for sending call notifications
    }

    private function updateAmbulanceStatus(string $callId, string $status): void
    {
        // Implementation for updating ambulance status
    }

    private function sendCompletionNotifications(string $callId, array $completionData): void
    {
        // Implementation for sending completion notifications
    }

    private function updatePatientRecord(string $patientId, array $data): void
    {
        // Implementation for updating patient records
    }

    private function calculateTotalResponseTime(array $call): ?int
    {
        // Implementation for calculating total response time
        return null;
    }

    private function calculatePerformanceRating(array $call): string
    {
        // Implementation for calculating performance rating
        return 'good';
    }

    private function saveCall(array $call): void
    {
        // Implementation for saving call
    }

    private function getCallById(string $callId): ?array
    {
        // Implementation for getting call by ID
        return null;
    }

    private function updateCallInDatabase(string $callId, array $data): void
    {
        // Implementation for database update
    }

    private function sendEmergencyConfirmation(string $callId, array $emergencyData): void
    {
        // Implementation for sending emergency confirmation
    }

    // Additional helper methods would be implemented...
}

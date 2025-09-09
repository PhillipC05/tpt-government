<?php
/**
 * TPT Government Platform - Fire Services Module
 *
 * Comprehensive fire prevention, response, and safety management system
 * for government fire departments and emergency services
 */

namespace Modules\FireServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\AIService;

class FireModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Fire Services',
        'version' => '2.0.0',
        'description' => 'Comprehensive fire prevention, response, and safety management system',
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
        'fire.view' => 'View fire incidents and reports',
        'fire.create' => 'Create fire incident reports',
        'fire.update' => 'Update fire incident information',
        'fire.close' => 'Close fire incident reports',
        'fire.inspect' => 'Conduct fire safety inspections',
        'fire.training' => 'Manage firefighter training records',
        'fire.equipment' => 'Manage fire equipment and maintenance',
        'fire.stations' => 'Manage fire station operations',
        'fire.prevention' => 'Manage fire prevention programs',
        'fire.admin' => 'Full administrative access to fire services',
        'fire.reports' => 'Access to fire service reports and analytics'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'fire_incidents' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'incident_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'incident_type' => "ENUM('structure_fire','vehicle_fire','wildfire','chemical_fire','electrical_fire','other') NOT NULL",
            'severity' => "ENUM('minor','moderate','major','critical') DEFAULT 'moderate'",
            'status' => "ENUM('reported','responding','on_scene','contained','extinguished','investigation','closed') DEFAULT 'reported'",
            'location_address' => 'TEXT',
            'location_coordinates' => 'VARCHAR(100)',
            'reported_by' => 'INT',
            'reported_via' => "ENUM('phone','app','alarm','witness','other') DEFAULT 'phone'",
            'reported_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'responded_at' => 'DATETIME',
            'contained_at' => 'DATETIME',
            'extinguished_at' => 'DATETIME',
            'closed_at' => 'DATETIME',
            'fire_station_id' => 'INT',
            'units_assigned' => 'JSON',
            'casualties' => 'JSON',
            'property_damage' => 'DECIMAL(12,2)',
            'estimated_loss' => 'DECIMAL(12,2)',
            'cause' => 'VARCHAR(255)',
            'investigator_id' => 'INT',
            'investigation_notes' => 'TEXT',
            'weather_conditions' => 'JSON',
            'hazardous_materials' => 'JSON',
            'attachments' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_stations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'station_number' => 'VARCHAR(10) UNIQUE NOT NULL',
            'station_name' => 'VARCHAR(255) NOT NULL',
            'address' => 'TEXT NOT NULL',
            'coordinates' => 'VARCHAR(100)',
            'phone' => 'VARCHAR(20)',
            'fax' => 'VARCHAR(20)',
            'email' => 'VARCHAR(255)',
            'district' => 'VARCHAR(100)',
            'station_chief' => 'INT',
            'deputy_chief' => 'INT',
            'capacity' => 'INT DEFAULT 0',
            'apparatus_count' => 'INT DEFAULT 0',
            'personnel_count' => 'INT DEFAULT 0',
            'service_area' => 'TEXT',
            'response_zone' => 'VARCHAR(50)',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'firefighters' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'badge_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'first_name' => 'VARCHAR(100) NOT NULL',
            'last_name' => 'VARCHAR(100) NOT NULL',
            'rank' => "ENUM('probationary','firefighter','driver','lieutenant','captain','battalion_chief','deputy_chief','fire_chief') DEFAULT 'firefighter'",
            'station_id' => 'INT',
            'hire_date' => 'DATE',
            'certifications' => 'JSON',
            'specialties' => 'JSON',
            'emergency_contact' => 'JSON',
            'medical_info' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_apparatus' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'apparatus_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'type' => "ENUM('engine','ladder','rescue','ambulance','command','utility','other') NOT NULL",
            'model' => 'VARCHAR(100)',
            'year' => 'YEAR',
            'station_id' => 'INT',
            'status' => "ENUM('in_service','out_of_service','maintenance','retired') DEFAULT 'in_service'",
            'mileage' => 'INT DEFAULT 0',
            'last_service' => 'DATE',
            'next_service' => 'DATE',
            'equipment' => 'JSON',
            'specifications' => 'JSON',
            'maintenance_history' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_inspections' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'inspection_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'property_address' => 'TEXT NOT NULL',
            'property_type' => "ENUM('residential','commercial','industrial','mixed_use','other') NOT NULL",
            'inspection_type' => "ENUM('routine','complaint','pre_occupancy','follow_up','other') DEFAULT 'routine'",
            'scheduled_date' => 'DATETIME',
            'completed_date' => 'DATETIME',
            'inspector_id' => 'INT',
            'status' => "ENUM('scheduled','in_progress','completed','cancelled','rescheduled') DEFAULT 'scheduled'",
            'findings' => 'JSON',
            'violations' => 'JSON',
            'recommendations' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATETIME',
            'occupancy_type' => 'VARCHAR(100)',
            'building_age' => 'INT',
            'fire_systems' => 'JSON',
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_prevention_programs' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'program_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'program_name' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'program_type' => "ENUM('education','inspection','enforcement','community_outreach','other') NOT NULL",
            'target_audience' => 'VARCHAR(255)',
            'start_date' => 'DATE',
            'end_date' => 'DATE',
            'budget' => 'DECIMAL(10,2)',
            'coordinator_id' => 'INT',
            'status' => "ENUM('planning','active','completed','cancelled') DEFAULT 'planning'",
            'objectives' => 'JSON',
            'activities' => 'JSON',
            'metrics' => 'JSON',
            'outcomes' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_training_records' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'firefighter_id' => 'INT NOT NULL',
            'training_type' => 'VARCHAR(255) NOT NULL',
            'training_provider' => 'VARCHAR(255)',
            'certification_number' => 'VARCHAR(100)',
            'issue_date' => 'DATE',
            'expiry_date' => 'DATE',
            'training_hours' => 'DECIMAL(5,2)',
            'instructor' => 'VARCHAR(255)',
            'status' => "ENUM('completed','in_progress','expired','revoked') DEFAULT 'completed'",
            'score' => 'DECIMAL(5,2)',
            'notes' => 'TEXT',
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_equipment_inventory' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'equipment_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'equipment_type' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'manufacturer' => 'VARCHAR(100)',
            'model' => 'VARCHAR(100)',
            'serial_number' => 'VARCHAR(100)',
            'station_id' => 'INT',
            'location' => 'VARCHAR(255)',
            'purchase_date' => 'DATE',
            'purchase_cost' => 'DECIMAL(10,2)',
            'warranty_expiry' => 'DATE',
            'maintenance_schedule' => 'VARCHAR(100)',
            'last_maintenance' => 'DATE',
            'next_maintenance' => 'DATE',
            'status' => "ENUM('active','maintenance','retired','lost') DEFAULT 'active'",
            'condition' => "ENUM('excellent','good','fair','poor','unserviceable') DEFAULT 'good'",
            'assigned_to' => 'INT',
            'specifications' => 'JSON',
            'maintenance_history' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'fire_analytics' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'date' => 'DATE NOT NULL',
            'station_id' => 'INT',
            'district' => 'VARCHAR(100)',
            'incidents_reported' => 'INT DEFAULT 0',
            'incidents_responded' => 'INT DEFAULT 0',
            'average_response_time' => 'INT DEFAULT 0', // minutes
            'inspections_completed' => 'INT DEFAULT 0',
            'violations_found' => 'INT DEFAULT 0',
            'training_sessions' => 'INT DEFAULT 0',
            'equipment_maintenance' => 'INT DEFAULT 0',
            'false_alarms' => 'INT DEFAULT 0',
            'property_damage_total' => 'DECIMAL(12,2) DEFAULT 0.00',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        // Incident Management
        ['method' => 'GET', 'path' => '/api/fire/incidents', 'handler' => 'getIncidents', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/incidents', 'handler' => 'createIncident', 'auth' => true, 'permissions' => ['fire.create']],
        ['method' => 'GET', 'path' => '/api/fire/incidents/{id}', 'handler' => 'getIncident', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/fire/incidents/{id}', 'handler' => 'updateIncident', 'auth' => true, 'permissions' => ['fire.update']],
        ['method' => 'POST', 'path' => '/api/fire/incidents/{id}/close', 'handler' => 'closeIncident', 'auth' => true, 'permissions' => ['fire.close']],

        // Fire Stations
        ['method' => 'GET', 'path' => '/api/fire/stations', 'handler' => 'getStations', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/stations', 'handler' => 'createStation', 'auth' => true, 'permissions' => ['fire.stations']],
        ['method' => 'GET', 'path' => '/api/fire/stations/{id}', 'handler' => 'getStation', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/fire/stations/{id}', 'handler' => 'updateStation', 'auth' => true, 'permissions' => ['fire.stations']],

        // Firefighters
        ['method' => 'GET', 'path' => '/api/fire/firefighters', 'handler' => 'getFirefighters', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/firefighters', 'handler' => 'createFirefighter', 'auth' => true, 'permissions' => ['fire.admin']],
        ['method' => 'GET', 'path' => '/api/fire/firefighters/{id}', 'handler' => 'getFirefighter', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/fire/firefighters/{id}', 'handler' => 'updateFirefighter', 'auth' => true, 'permissions' => ['fire.admin']],

        // Equipment Management
        ['method' => 'GET', 'path' => '/api/fire/equipment', 'handler' => 'getEquipment', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/equipment', 'handler' => 'createEquipment', 'auth' => true, 'permissions' => ['fire.equipment']],
        ['method' => 'GET', 'path' => '/api/fire/equipment/{id}', 'handler' => 'getEquipmentItem', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/fire/equipment/{id}', 'handler' => 'updateEquipment', 'auth' => true, 'permissions' => ['fire.equipment']],

        // Inspections
        ['method' => 'GET', 'path' => '/api/fire/inspections', 'handler' => 'getInspections', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/inspections', 'handler' => 'createInspection', 'auth' => true, 'permissions' => ['fire.inspect']],
        ['method' => 'GET', 'path' => '/api/fire/inspections/{id}', 'handler' => 'getInspection', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/fire/inspections/{id}', 'handler' => 'updateInspection', 'auth' => true, 'permissions' => ['fire.inspect']],

        // Training
        ['method' => 'GET', 'path' => '/api/fire/training', 'handler' => 'getTrainingRecords', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/training', 'handler' => 'createTrainingRecord', 'auth' => true, 'permissions' => ['fire.training']],
        ['method' => 'GET', 'path' => '/api/fire/training/{id}', 'handler' => 'getTrainingRecord', 'auth' => true],

        // Prevention Programs
        ['method' => 'GET', 'path' => '/api/fire/prevention', 'handler' => 'getPreventionPrograms', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/fire/prevention', 'handler' => 'createPreventionProgram', 'auth' => true, 'permissions' => ['fire.prevention']],

        // Analytics and Reporting
        ['method' => 'GET', 'path' => '/api/fire/analytics', 'handler' => 'getAnalytics', 'auth' => true, 'permissions' => ['fire.reports']],
        ['method' => 'GET', 'path' => '/api/fire/reports', 'handler' => 'getReports', 'auth' => true, 'permissions' => ['fire.reports']],

        // Public Emergency Reporting
        ['method' => 'POST', 'path' => '/api/public/fire-emergency', 'handler' => 'reportFireEmergency', 'auth' => false]
    ];

    /**
     * Incident response priorities
     */
    private array $responsePriorities = [
        'critical' => ['response_time' => 5, 'units_required' => 4], // 5 minutes
        'major' => ['response_time' => 8, 'units_required' => 3],    // 8 minutes
        'moderate' => ['response_time' => 10, 'units_required' => 2], // 10 minutes
        'minor' => ['response_time' => 15, 'units_required' => 1]     // 15 minutes
    ];

    /**
     * Fire incident types and their characteristics
     */
    private array $incidentTypes = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->initializeIncidentTypes();
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
            'emergency_notification' => true,
            'public_reporting' => true,
            'inspection_scheduling' => true,
            'training_tracking' => true,
            'equipment_maintenance' => true,
            'analytics_enabled' => true,
            'response_time_tracking' => true,
            'weather_integration' => true,
            'gis_integration' => true,
            'sms_alerts' => true,
            'email_notifications' => true,
            'api_integrations' => true
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeIncidentTypes();
        $this->setupDefaultStations();
        $this->initializePreventionPrograms();
        $this->setupNotificationTemplates();
    }

    /**
     * Initialize incident types
     */
    private function initializeIncidentTypes(): void
    {
        $this->incidentTypes = [
            'structure_fire' => [
                'name' => 'Structure Fire',
                'priority' => 'major',
                'requires' => ['engine', 'ladder', 'rescue'],
                'estimated_duration' => 120, // minutes
                'risk_factors' => ['occupancy', 'construction_type', 'fire_load']
            ],
            'vehicle_fire' => [
                'name' => 'Vehicle Fire',
                'priority' => 'moderate',
                'requires' => ['engine'],
                'estimated_duration' => 30,
                'risk_factors' => ['fuel_type', 'location', 'traffic_conditions']
            ],
            'wildfire' => [
                'name' => 'Wildfire',
                'priority' => 'critical',
                'requires' => ['engine', 'water_tender', 'command'],
                'estimated_duration' => 480, // 8 hours
                'risk_factors' => ['wind_speed', 'humidity', 'fuel_type', 'terrain']
            ],
            'chemical_fire' => [
                'name' => 'Chemical Fire',
                'priority' => 'critical',
                'requires' => ['hazmat_team', 'engine', 'rescue'],
                'estimated_duration' => 180,
                'risk_factors' => ['chemical_type', 'quantity', 'ventilation']
            ],
            'electrical_fire' => [
                'name' => 'Electrical Fire',
                'priority' => 'moderate',
                'requires' => ['engine'],
                'estimated_duration' => 45,
                'risk_factors' => ['voltage', 'equipment_type', 'building_age']
            ]
        ];
    }

    /**
     * Setup default fire stations
     */
    private function setupDefaultStations(): void
    {
        // This would create default fire station records
        // Implementation would depend on specific jurisdiction
    }

    /**
     * Initialize prevention programs
     */
    private function initializePreventionPrograms(): void
    {
        $programs = [
            [
                'program_name' => 'Home Fire Safety Education',
                'program_type' => 'education',
                'target_audience' => 'homeowners',
                'objectives' => ['Reduce home fire incidents by 20%', 'Increase smoke detector installation']
            ],
            [
                'program_name' => 'Business Fire Prevention',
                'program_type' => 'inspection',
                'target_audience' => 'business_owners',
                'objectives' => ['Ensure compliance with fire codes', 'Reduce business fire losses']
            ],
            [
                'program_name' => 'School Fire Safety Program',
                'program_type' => 'education',
                'target_audience' => 'schools',
                'objectives' => ['Educate students about fire safety', 'Conduct school fire drills']
            ]
        ];

        // Save to database
        $this->savePreventionPrograms($programs);
    }

    /**
     * Setup notification templates
     */
    private function setupNotificationTemplates(): void
    {
        // Setup SMS and email templates for fire emergencies
    }

    /**
     * Create fire incident
     */
    public function createIncident(array $incidentData, array $metadata = []): array
    {
        try {
            // Validate incident data
            $validation = $this->validateIncidentData($incidentData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate incident ID
            $incidentId = $this->generateIncidentId();

            // Determine severity and priority
            $severity = $this->assessIncidentSeverity($incidentData);
            $priority = $this->incidentTypes[$incidentData['incident_type']]['priority'] ?? 'moderate';

            // Prepare incident data
            $incident = [
                'incident_id' => $incidentId,
                'incident_type' => $incidentData['incident_type'],
                'severity' => $severity,
                'status' => 'reported',
                'location_address' => $incidentData['location_address'],
                'location_coordinates' => $incidentData['location_coordinates'] ?? null,
                'reported_by' => $incidentData['reported_by'] ?? null,
                'reported_via' => $metadata['channel'] ?? 'phone',
                'weather_conditions' => $this->getCurrentWeather($incidentData['location_coordinates']),
                'metadata' => json_encode($metadata)
            ];

            // Save to database
            $this->saveIncident($incident);

            // Auto-dispatch units if enabled
            if ($this->config['auto_dispatch']) {
                $this->autoDispatchUnits($incidentId, $incident);
            }

            // Send emergency notifications
            $this->sendEmergencyNotifications($incident);

            // Log incident creation
            $this->logIncidentEvent($incidentId, 'created', 'Incident reported and logged');

            return [
                'success' => true,
                'incident_id' => $incidentId,
                'severity' => $severity,
                'estimated_response_time' => $this->responsePriorities[$priority]['response_time'] ?? 10,
                'units_dispatched' => $this->getDispatchedUnits($incidentId),
                'message' => 'Fire incident reported successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error creating fire incident: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create fire incident'
            ];
        }
    }

    /**
     * Update incident
     */
    public function updateIncident(string $incidentId, array $updateData, int $updatedBy): array
    {
        try {
            $incident = $this->getIncidentById($incidentId);
            if (!$incident) {
                return [
                    'success' => false,
                    'error' => 'Incident not found'
                ];
            }

            // Track status changes
            if (isset($updateData['status'])) {
                $this->handleStatusChange($incidentId, $incident['status'], $updateData['status'], $updatedBy);
            }

            // Update incident
            $updateData['updated_by'] = $updatedBy;
            $this->updateIncidentInDatabase($incidentId, $updateData);

            // Log the update
            $this->logIncidentEvent($incidentId, 'updated', 'Incident information updated');

            return [
                'success' => true,
                'message' => 'Incident updated successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error updating incident: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update incident'
            ];
        }
    }

    /**
     * Create fire inspection
     */
    public function createInspection(array $inspectionData, int $createdBy): array
    {
        try {
            // Validate inspection data
            $validation = $this->validateInspectionData($inspectionData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate inspection ID
            $inspectionId = $this->generateInspectionId();

            // Prepare inspection data
            $inspection = [
                'inspection_id' => $inspectionId,
                'property_address' => $inspectionData['property_address'],
                'property_type' => $inspectionData['property_type'],
                'inspection_type' => $inspectionData['inspection_type'] ?? 'routine',
                'scheduled_date' => $inspectionData['scheduled_date'] ?? date('Y-m-d H:i:s'),
                'inspector_id' => $createdBy,
                'status' => 'scheduled',
                'occupancy_type' => $inspectionData['occupancy_type'] ?? null,
                'building_age' => $inspectionData['building_age'] ?? null
            ];

            // Save to database
            $this->saveInspection($inspection);

            // Schedule follow-up if needed
            if ($inspectionData['follow_up_required'] ?? false) {
                $this->scheduleFollowUpInspection($inspectionId, $inspectionData['follow_up_date']);
            }

            return [
                'success' => true,
                'inspection_id' => $inspectionId,
                'scheduled_date' => $inspection['scheduled_date'],
                'message' => 'Fire inspection scheduled successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error creating inspection: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create inspection'
            ];
        }
    }

    /**
     * Get fire incidents
     */
    public function getIncidents(array $filters = [], array $pagination = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM fire_incidents WHERE 1=1";
            $params = [];

            // Apply filters
            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['incident_type'])) {
                $sql .= " AND incident_type = ?";
                $params[] = $filters['incident_type'];
            }

            if (isset($filters['severity'])) {
                $sql .= " AND severity = ?";
                $params[] = $filters['severity'];
            }

            if (isset($filters['station_id'])) {
                $sql .= " AND fire_station_id = ?";
                $params[] = $filters['station_id'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND reported_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND reported_at <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY reported_at DESC";

            // Add pagination
            $limit = $pagination['limit'] ?? 50;
            $offset = $pagination['offset'] ?? 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $incidents = $db->fetchAll($sql, $params);

            // Decode JSON fields and enrich data
            foreach ($incidents as &$incident) {
                $incident['units_assigned'] = json_decode($incident['units_assigned'], true);
                $incident['casualties'] = json_decode($incident['casualties'], true);
                $incident['weather_conditions'] = json_decode($incident['weather_conditions'], true);
                $incident['hazardous_materials'] = json_decode($incident['hazardous_materials'], true);
                $incident['attachments'] = json_decode($incident['attachments'], true);
                $incident['metadata'] = json_decode($incident['metadata'], true);

                // Add calculated fields
                $incident['response_time'] = $this->calculateResponseTime($incident);
                $incident['duration'] = $this->calculateIncidentDuration($incident);
            }

            return [
                'success' => true,
                'data' => $incidents,
                'count' => count($incidents)
            ];

        } catch (\Exception $e) {
            error_log("Error getting incidents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve incidents'
            ];
        }
    }

    /**
     * Get fire stations
     */
    public function getStations(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM fire_stations WHERE 1=1";
            $params = [];

            if (isset($filters['district'])) {
                $sql .= " AND district = ?";
                $params[] = $filters['district'];
            }

            if (isset($filters['is_active'])) {
                $sql .= " AND is_active = ?";
                $params[] = $filters['is_active'];
            }

            $sql .= " ORDER BY station_number ASC";

            $stations = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $stations,
                'count' => count($stations)
            ];

        } catch (\Exception $e) {
            error_log("Error getting stations: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve stations'
            ];
        }
    }

    /**
     * Get firefighters
     */
    public function getFirefighters(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT f.*, s.station_name FROM firefighters f
                    LEFT JOIN fire_stations s ON f.station_id = s.id
                    WHERE 1=1";
            $params = [];

            if (isset($filters['station_id'])) {
                $sql .= " AND f.station_id = ?";
                $params[] = $filters['station_id'];
            }

            if (isset($filters['rank'])) {
                $sql .= " AND f.rank = ?";
                $params[] = $filters['rank'];
            }

            if (isset($filters['is_active'])) {
                $sql .= " AND f.is_active = ?";
                $params[] = $filters['is_active'];
            }

            $sql .= " ORDER BY f.last_name ASC";

            $firefighters = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($firefighters as &$firefighter) {
                $firefighter['certifications'] = json_decode($firefighter['certifications'], true);
                $firefighter['specialties'] = json_decode($firefighter['specialties'], true);
                $firefighter['emergency_contact'] = json_decode($firefighter['emergency_contact'], true);
                $firefighter['medical_info'] = json_decode($firefighter['medical_info'], true);
            }

            return [
                'success' => true,
                'data' => $firefighters,
                'count' => count($firefighters)
            ];

        } catch (\Exception $e) {
            error_log("Error getting firefighters: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve firefighters'
            ];
        }
    }

    /**
     * Get fire analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT
                        COUNT(*) as total_incidents,
                        COUNT(CASE WHEN status = 'extinguished' THEN 1 END) as incidents_extinguished,
                        COUNT(CASE WHEN status = 'contained' THEN 1 END) as incidents_contained,
                        AVG(TIMESTAMPDIFF(MINUTE, reported_at, responded_at)) as avg_response_time,
                        AVG(TIMESTAMPDIFF(MINUTE, responded_at, extinguished_at)) as avg_extinguish_time,
                        SUM(property_damage) as total_property_damage,
                        COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_incidents,
                        COUNT(CASE WHEN severity = 'major' THEN 1 END) as major_incidents
                    FROM fire_incidents
                    WHERE 1=1";

            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND reported_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND reported_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['station_id'])) {
                $sql .= " AND fire_station_id = ?";
                $params[] = $filters['station_id'];
            }

            $result = $db->fetch($sql, $params);

            // Calculate additional metrics
            $result['resolution_rate'] = $result['total_incidents'] > 0
                ? round(($result['incidents_extinguished'] / $result['total_incidents']) * 100, 2)
                : 0;

            $result['containment_rate'] = $result['total_incidents'] > 0
                ? round(($result['incidents_contained'] / $result['total_incidents']) * 100, 2)
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
     * Report fire emergency (public endpoint)
     */
    public function reportFireEmergency(array $emergencyData): array
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

            // Create incident from emergency report
            $incidentData = [
                'incident_type' => $emergencyData['incident_type'] ?? 'structure_fire',
                'location_address' => $emergencyData['address'],
                'location_coordinates' => $emergencyData['coordinates'] ?? null,
                'reported_by' => null, // Public report
                'description' => $emergencyData['description'] ?? ''
            ];

            $metadata = [
                'channel' => 'public_app',
                'caller_name' => $emergencyData['caller_name'] ?? 'Anonymous',
                'caller_phone' => $emergencyData['caller_phone'] ?? '',
                'emergency_level' => 'high'
            ];

            $result = $this->createIncident($incidentData, $metadata);

            if ($result['success']) {
                // Send immediate confirmation
                $this->sendEmergencyConfirmation($result['incident_id'], $emergencyData);
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error reporting fire emergency: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to report emergency'
            ];
        }
    }

    // Helper methods (implementations would be added)

    private function generateIncidentId(): string
    {
        return 'FIRE-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateInspectionId(): string
    {
        return 'INSP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function validateIncidentData(array $data): array
    {
        $errors = [];

        if (empty($data['incident_type'])) {
            $errors[] = 'Incident type is required';
        }

        if (empty($data['location_address'])) {
            $errors[] = 'Location address is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateInspectionData(array $data): array
    {
        $errors = [];

        if (empty($data['property_address'])) {
            $errors[] = 'Property address is required';
        }

        if (empty($data['property_type'])) {
            $errors[] = 'Property type is required';
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

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function assessIncidentSeverity(array $incidentData): string
    {
        // Basic severity assessment based on incident type and other factors
        $typeSeverity = [
            'wildfire' => 'critical',
            'chemical_fire' => 'critical',
            'structure_fire' => 'major',
            'vehicle_fire' => 'moderate',
            'electrical_fire' => 'moderate',
            'other' => 'minor'
        ];

        return $typeSeverity[$incidentData['incident_type']] ?? 'moderate';
    }

    private function autoDispatchUnits(string $incidentId, array $incident): void
    {
        // Implementation for automatic unit dispatch
    }

    private function sendEmergencyNotifications(array $incident): void
    {
        // Implementation for sending emergency notifications
    }

    private function logIncidentEvent(string $incidentId, string $event, string $description): void
    {
        // Implementation for logging incident events
    }

    private function getDispatchedUnits(string $incidentId): array
    {
        // Implementation for getting dispatched units
        return [];
    }

    private function handleStatusChange(string $incidentId, string $oldStatus, string $newStatus, int $updatedBy): void
    {
        // Implementation for handling status changes
    }

    private function updateIncidentInDatabase(string $incidentId, array $data): void
    {
        // Implementation for database update
    }

    private function saveIncident(array $incident): void
    {
        // Implementation for saving incident
    }

    private function saveInspection(array $inspection): void
    {
        // Implementation for saving inspection
    }

    private function savePreventionPrograms(array $programs): void
    {
        // Implementation for saving prevention programs
    }

    private function getIncidentById(string $incidentId): ?array
    {
        // Implementation for getting incident by ID
        return null;
    }

    private function scheduleFollowUpInspection(string $inspectionId, string $followUpDate): void
    {
        // Implementation for scheduling follow-up inspection
    }

    private function calculateResponseTime(array $incident): ?int
    {
        // Implementation for calculating response time
        return null;
    }

    private function calculateIncidentDuration(array $incident): ?int
    {
        // Implementation for calculating incident duration
        return null;
    }

    private function getCurrentWeather(?string $coordinates): array
    {
        // Implementation for getting current weather
        return [];
    }

    private function sendEmergencyConfirmation(string $incidentId, array $emergencyData): void
    {
        // Implementation for sending emergency confirmation
    }

    // Additional helper methods would be implemented...
}

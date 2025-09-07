<?php
/**
 * TPT Government Platform - Waste Management Module
 *
 * Comprehensive waste collection, disposal, and environmental management system
 * supporting collection scheduling, billing, environmental monitoring, and compliance
 */

namespace Modules\WasteManagement;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class WasteManagementModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Waste Management',
        'version' => '2.1.0',
        'description' => 'Comprehensive waste collection, disposal, and environmental management system',
        'author' => 'TPT Government Platform',
        'category' => 'public_infrastructure',
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
        'waste_services.view' => 'View waste management services and schedules',
        'waste_services.create' => 'Create waste collection requests',
        'waste_services.edit' => 'Edit waste service information',
        'waste_services.schedule' => 'Schedule waste collection services',
        'waste_services.dispatch' => 'Dispatch waste collection crews',
        'waste_services.billing' => 'Manage waste service billing',
        'waste_services.compliance' => 'Monitor environmental compliance',
        'waste_services.reporting' => 'Generate waste management reports'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'waste_collection_schedules' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'service_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'customer_id' => 'INT NOT NULL',
            'service_type' => "ENUM('residential','commercial','industrial','hazardous','recycling') NOT NULL",
            'collection_frequency' => "ENUM('daily','weekly','biweekly','monthly') NOT NULL",
            'collection_day' => "ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL",
            'collection_time' => 'TIME NOT NULL',
            'address' => 'TEXT NOT NULL',
            'coordinates' => 'VARCHAR(100)',
            'bin_size' => "ENUM('small','medium','large','extra_large') DEFAULT 'medium'",
            'waste_types' => 'JSON',
            'special_instructions' => 'TEXT',
            'status' => "ENUM('active','suspended','cancelled','on_hold') DEFAULT 'active'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'waste_collection_requests' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'request_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'customer_id' => 'INT NOT NULL',
            'request_type' => "ENUM('one_time','emergency','bulk','special') NOT NULL",
            'waste_type' => 'VARCHAR(100) NOT NULL',
            'quantity' => 'DECIMAL(10,2)',
            'unit' => "ENUM('kg','tons','cubic_meters','items') DEFAULT 'kg'",
            'pickup_address' => 'TEXT NOT NULL',
            'pickup_date' => 'DATE NOT NULL',
            'pickup_time_slot' => 'VARCHAR(50)',
            'special_handling' => 'BOOLEAN DEFAULT FALSE',
            'hazardous_materials' => 'JSON',
            'status' => "ENUM('pending','scheduled','in_progress','completed','cancelled') DEFAULT 'pending'",
            'assigned_crew' => 'INT NULL',
            'estimated_cost' => 'DECIMAL(8,2) DEFAULT 0.00',
            'actual_cost' => 'DECIMAL(8,2) DEFAULT 0.00',
            'completion_date' => 'DATETIME NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'waste_billing' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'customer_id' => 'INT NOT NULL',
            'service_id' => 'VARCHAR(20)',
            'billing_period' => 'VARCHAR(20) NOT NULL',
            'service_type' => 'VARCHAR(50) NOT NULL',
            'base_fee' => 'DECIMAL(8,2) NOT NULL',
            'additional_fees' => 'DECIMAL(8,2) DEFAULT 0.00',
            'taxes' => 'DECIMAL(8,2) DEFAULT 0.00',
            'total_amount' => 'DECIMAL(8,2) NOT NULL',
            'due_date' => 'DATE NOT NULL',
            'payment_date' => 'DATE NULL',
            'payment_method' => 'VARCHAR(50)',
            'status' => "ENUM('unpaid','paid','overdue','cancelled') DEFAULT 'unpaid'",
            'invoice_number' => 'VARCHAR(20) UNIQUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'waste_disposal_sites' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'site_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'site_name' => 'VARCHAR(255) NOT NULL',
            'site_type' => "ENUM('landfill','incinerator','recycling_center','transfer_station','composting') NOT NULL",
            'location' => 'TEXT NOT NULL',
            'coordinates' => 'VARCHAR(100)',
            'capacity' => 'DECIMAL(15,2)',
            'current_usage' => 'DECIMAL(15,2) DEFAULT 0.00',
            'waste_types_accepted' => 'JSON',
            'operating_hours' => 'JSON',
            'contact_info' => 'JSON',
            'environmental_permits' => 'JSON',
            'status' => "ENUM('active','maintenance','closed','full') DEFAULT 'active'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'environmental_monitoring' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'site_id' => 'VARCHAR(20) NOT NULL',
            'monitoring_type' => "ENUM('air_quality','water_quality','soil_quality','noise_level','odour') NOT NULL",
            'sensor_id' => 'VARCHAR(50)',
            'reading_value' => 'DECIMAL(10,4)',
            'unit' => 'VARCHAR(20)',
            'threshold_min' => 'DECIMAL(10,4)',
            'threshold_max' => 'DECIMAL(10,4)',
            'is_compliant' => 'BOOLEAN',
            'recorded_at' => 'DATETIME NOT NULL',
            'recorded_by' => 'VARCHAR(100)',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'recycling_programs' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'program_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'program_name' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'target_materials' => 'JSON',
            'participation_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'collection_points' => 'JSON',
            'incentives' => 'JSON',
            'start_date' => 'DATE NOT NULL',
            'end_date' => 'DATE NULL',
            'status' => "ENUM('active','inactive','completed') DEFAULT 'active'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'GET',
            'path' => '/api/waste-services',
            'handler' => 'getWasteServices',
            'auth' => true,
            'permissions' => ['waste_services.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/waste-services',
            'handler' => 'createWasteService',
            'auth' => true,
            'permissions' => ['waste_services.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/waste-collection-requests',
            'handler' => 'getCollectionRequests',
            'auth' => true,
            'permissions' => ['waste_services.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/waste-collection-requests',
            'handler' => 'createCollectionRequest',
            'auth' => true,
            'permissions' => ['waste_services.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/waste-billing',
            'handler' => 'getBillingInfo',
            'auth' => true,
            'permissions' => ['waste_services.billing']
        ],
        [
            'method' => 'GET',
            'path' => '/api/waste-disposal-sites',
            'handler' => 'getDisposalSites',
            'auth' => true,
            'permissions' => ['waste_services.view']
        ],
        [
            'method' => 'GET',
            'path' => '/api/environmental-monitoring',
            'handler' => 'getEnvironmentalData',
            'auth' => true,
            'permissions' => ['waste_services.compliance']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'waste_collection_request' => [
            'name' => 'Waste Collection Request',
            'description' => 'Workflow for processing waste collection requests',
            'steps' => [
                'submitted' => ['name' => 'Request Submitted', 'next' => 'assessment'],
                'assessment' => ['name' => 'Risk Assessment', 'next' => ['approved', 'rejected', 'additional_info']],
                'additional_info' => ['name' => 'Additional Information Required', 'next' => 'assessment'],
                'approved' => ['name' => 'Request Approved', 'next' => 'scheduled'],
                'scheduled' => ['name' => 'Collection Scheduled', 'next' => 'in_progress'],
                'in_progress' => ['name' => 'Collection In Progress', 'next' => 'completed'],
                'completed' => ['name' => 'Collection Completed', 'next' => null],
                'rejected' => ['name' => 'Request Rejected', 'next' => null]
            ]
        ],
        'environmental_compliance' => [
            'name' => 'Environmental Compliance',
            'description' => 'Workflow for environmental monitoring and compliance',
            'steps' => [
                'monitoring' => ['name' => 'Environmental Monitoring', 'next' => ['compliant', 'non_compliant']],
                'compliant' => ['name' => 'Compliant', 'next' => 'monitoring'],
                'non_compliant' => ['name' => 'Non-Compliant', 'next' => 'investigation'],
                'investigation' => ['name' => 'Investigation', 'next' => ['resolved', 'escalated']],
                'resolved' => ['name' => 'Issue Resolved', 'next' => 'monitoring'],
                'escalated' => ['name' => 'Issue Escalated', 'next' => 'legal_action']
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'waste_collection_request' => [
            'name' => 'Waste Collection Request',
            'fields' => [
                'request_type' => ['type' => 'select', 'required' => true, 'label' => 'Request Type'],
                'waste_type' => ['type' => 'select', 'required' => true, 'label' => 'Waste Type'],
                'quantity' => ['type' => 'number', 'required' => true, 'label' => 'Quantity', 'step' => '0.01'],
                'unit' => ['type' => 'select', 'required' => true, 'label' => 'Unit'],
                'pickup_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Pickup Address'],
                'pickup_date' => ['type' => 'date', 'required' => true, 'label' => 'Preferred Pickup Date'],
                'pickup_time_slot' => ['type' => 'select', 'required' => false, 'label' => 'Preferred Time Slot'],
                'special_handling' => ['type' => 'checkbox', 'required' => false, 'label' => 'Special Handling Required'],
                'description' => ['type' => 'textarea', 'required' => false, 'label' => 'Description']
            ],
            'documents' => [
                'waste_manifest' => ['required' => false, 'label' => 'Waste Manifest'],
                'safety_data_sheet' => ['required' => false, 'label' => 'Safety Data Sheet'],
                'photos' => ['required' => false, 'label' => 'Waste Photos']
            ]
        ],
        'waste_service_registration' => [
            'name' => 'Waste Service Registration',
            'fields' => [
                'service_type' => ['type' => 'select', 'required' => true, 'label' => 'Service Type'],
                'collection_frequency' => ['type' => 'select', 'required' => true, 'label' => 'Collection Frequency'],
                'collection_day' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Collection Day'],
                'collection_time' => ['type' => 'time', 'required' => true, 'label' => 'Preferred Collection Time'],
                'address' => ['type' => 'textarea', 'required' => true, 'label' => 'Service Address'],
                'bin_size' => ['type' => 'select', 'required' => true, 'label' => 'Bin Size'],
                'waste_types' => ['type' => 'multiselect', 'required' => true, 'label' => 'Waste Types'],
                'special_instructions' => ['type' => 'textarea', 'required' => false, 'label' => 'Special Instructions']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'waste_collection_summary' => [
            'name' => 'Waste Collection Summary Report',
            'description' => 'Summary of waste collection activities and volumes',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'service_type' => ['type' => 'select', 'required' => false],
                'waste_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'collection_date', 'service_type', 'waste_type', 'quantity',
                'customer_count', 'total_collections', 'efficiency_rate'
            ]
        ],
        'environmental_compliance' => [
            'name' => 'Environmental Compliance Report',
            'description' => 'Environmental monitoring and compliance status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'monitoring_type' => ['type' => 'select', 'required' => false],
                'site_id' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'site_name', 'monitoring_type', 'reading_value', 'is_compliant',
                'recorded_at', 'compliance_rate', 'issues_identified'
            ]
        ],
        'revenue_report' => [
            'name' => 'Waste Management Revenue Report',
            'description' => 'Revenue generated from waste management services',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'service_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'service_type', 'total_services', 'total_revenue', 'average_fee',
                'collection_rate', 'monthly_trend', 'profit_margin'
            ]
        ],
        'recycling_performance' => [
            'name' => 'Recycling Performance Report',
            'description' => 'Performance metrics for recycling programs',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'program_id' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'program_name', 'materials_collected', 'participation_rate',
                'diversion_rate', 'cost_savings', 'environmental_impact'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'collection_request_submitted' => [
            'name' => 'Collection Request Submitted',
            'template' => 'Your waste collection request has been submitted successfully. Request ID: {request_id}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['request_created']
        ],
        'collection_scheduled' => [
            'name' => 'Collection Scheduled',
            'template' => 'Your waste collection has been scheduled for {pickup_date} between {time_slot}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['collection_scheduled']
        ],
        'collection_completed' => [
            'name' => 'Collection Completed',
            'template' => 'Your waste collection has been completed successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['collection_completed']
        ],
        'billing_reminder' => [
            'name' => 'Billing Reminder',
            'template' => 'Your waste management bill of ${amount} is due on {due_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['billing_due']
        ],
        'environmental_alert' => [
            'name' => 'Environmental Alert',
            'template' => 'Environmental monitoring alert at {site_name}: {alert_type} - {reading_value}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['environmental_alert']
        ],
        'service_disruption' => [
            'name' => 'Service Disruption Notice',
            'template' => 'Waste collection service will be disrupted on {date} due to {reason}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['service_disruption']
        ]
    ];

    /**
     * Waste types and handling requirements
     */
    private array $wasteTypes = [];

    /**
     * Service pricing
     */
    private array $servicePricing = [];

    /**
     * Environmental thresholds
     */
    private array $environmentalThresholds = [];

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
            'collection_scheduling' => [
                'advance_booking_days' => 14,
                'max_requests_per_day' => 50,
                'emergency_response_hours' => 24
            ],
            'billing' => [
                'billing_cycle' => 'monthly',
                'payment_grace_period' => 15,
                'late_fee_percentage' => 0.05,
                'payment_gateway' => 'stripe'
            ],
            'environmental_monitoring' => [
                'alert_threshold' => 0.8,
                'monitoring_frequency' => 3600, // 1 hour
                'data_retention_days' => 365
            ],
            'notification_settings' => [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true
            ]
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeWasteTypes();
        $this->initializeServicePricing();
        $this->initializeEnvironmentalThresholds();
    }

    /**
     * Initialize waste types
     */
    private function initializeWasteTypes(): void
    {
        $this->wasteTypes = [
            'municipal_solid_waste' => [
                'name' => 'Municipal Solid Waste',
                'category' => 'non_hazardous',
                'disposal_method' => 'landfill',
                'handling_requirements' => ['standard_collection', 'compaction'],
                'environmental_impact' => 'moderate'
            ],
            'recyclables' => [
                'name' => 'Recyclable Materials',
                'category' => 'recyclable',
                'disposal_method' => 'recycling_center',
                'handling_requirements' => ['sorting', 'cleaning', 'processing'],
                'environmental_impact' => 'low'
            ],
            'organic_waste' => [
                'name' => 'Organic Waste',
                'category' => 'compostable',
                'disposal_method' => 'composting_facility',
                'handling_requirements' => ['separate_collection', 'aerobic_digestion'],
                'environmental_impact' => 'low'
            ],
            'construction_debris' => [
                'name' => 'Construction & Demolition Waste',
                'category' => 'non_hazardous',
                'disposal_method' => 'landfill',
                'handling_requirements' => ['size_reduction', 'separation'],
                'environmental_impact' => 'moderate'
            ],
            'hazardous_waste' => [
                'name' => 'Hazardous Waste',
                'category' => 'hazardous',
                'disposal_method' => 'specialized_facility',
                'handling_requirements' => ['specialized_equipment', 'protective_gear', 'documentation'],
                'environmental_impact' => 'high'
            ],
            'electronic_waste' => [
                'name' => 'Electronic Waste',
                'category' => 'hazardous',
                'disposal_method' => 'e_waste_facility',
                'handling_requirements' => ['data_destruction', 'component_separation', 'recycling'],
                'environmental_impact' => 'high'
            ],
            'medical_waste' => [
                'name' => 'Medical Waste',
                'category' => 'hazardous',
                'disposal_method' => 'medical_waste_facility',
                'handling_requirements' => ['sterilization', 'secure_transport', 'documentation'],
                'environmental_impact' => 'high'
            ]
        ];
    }

    /**
     * Initialize service pricing
     */
    private function initializeServicePricing(): void
    {
        $this->servicePricing = [
            'residential' => [
                'base_fee' => 25.00,
                'frequency_multipliers' => [
                    'weekly' => 1.0,
                    'biweekly' => 0.6,
                    'monthly' => 0.3
                ],
                'bin_size_multipliers' => [
                    'small' => 0.8,
                    'medium' => 1.0,
                    'large' => 1.3,
                    'extra_large' => 1.6
                ]
            ],
            'commercial' => [
                'base_fee' => 150.00,
                'frequency_multipliers' => [
                    'daily' => 2.0,
                    'weekly' => 1.0,
                    'biweekly' => 0.7
                ],
                'volume_based' => true
            ],
            'industrial' => [
                'base_fee' => 500.00,
                'custom_pricing' => true,
                'volume_based' => true,
                'special_handling_surcharge' => 200.00
            ]
        ];
    }

    /**
     * Initialize environmental thresholds
     */
    private function initializeEnvironmentalThresholds(): void
    {
        $this->environmentalThresholds = [
            'air_quality' => [
                'pm25' => ['min' => 0, 'max' => 35, 'unit' => 'µg/m³'],
                'pm10' => ['min' => 0, 'max' => 50, 'unit' => 'µg/m³'],
                'no2' => ['min' => 0, 'max' => 40, 'unit' => 'µg/m³']
            ],
            'water_quality' => [
                'ph' => ['min' => 6.5, 'max' => 8.5, 'unit' => 'pH'],
                'turbidity' => ['min' => 0, 'max' => 5, 'unit' => 'NTU'],
                'bod' => ['min' => 0, 'max' => 30, 'unit' => 'mg/L']
            ],
            'noise_level' => [
                'daytime' => ['min' => 0, 'max' => 55, 'unit' => 'dB'],
                'nighttime' => ['min' => 0, 'max' => 45, 'unit' => 'dB']
            ],
            'odour' => [
                'threshold' => ['min' => 0, 'max' => 3, 'unit' => 'OU/m³']
            ]
        ];
    }

    /**
     * Create waste collection request
     */
    public function createCollectionRequest(array $requestData): array
    {
        // Validate request data
        $validation = $this->validateCollectionRequest($requestData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate request ID
        $requestId = $this->generateRequestId();

        // Calculate estimated cost
        $estimatedCost = $this->calculateCollectionCost($requestData);

        // Create request record
        $request = [
            'request_id' => $requestId,
            'customer_id' => $requestData['customer_id'],
            'request_type' => $requestData['request_type'],
            'waste_type' => $requestData['waste_type'],
            'quantity' => $requestData['quantity'] ?? null,
            'unit' => $requestData['unit'] ?? 'kg',
            'pickup_address' => $requestData['pickup_address'],
            'pickup_date' => $requestData['pickup_date'],
            'pickup_time_slot' => $requestData['pickup_time_slot'] ?? null,
            'special_handling' => $requestData['special_handling'] ?? false,
            'hazardous_materials' => $requestData['hazardous_materials'] ?? [],
            'status' => 'pending',
            'estimated_cost' => $estimatedCost,
            'notes' => $requestData['notes'] ?? ''
        ];

        // Save to database
        $this->saveCollectionRequest($request);

        // Start workflow
        $this->startCollectionWorkflow($requestId);

        // Send notification
        $this->sendNotification('collection_request_submitted', $requestData['customer_id'], [
            'request_id' => $requestId
        ]);

        return [
            'success' => true,
            'request_id' => $requestId,
            'estimated_cost' => $estimatedCost,
            'pickup_date' => $requestData['pickup_date']
        ];
    }

    /**
     * Schedule waste collection service
     */
    public function scheduleWasteService(array $serviceData): array
    {
        // Validate service data
        $validation = $this->validateServiceData($serviceData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate service ID
        $serviceId = $this->generateServiceId();

        // Calculate service fee
        $serviceFee = $this->calculateServiceFee($serviceData);

        // Create service record
        $service = [
            'service_id' => $serviceId,
            'customer_id' => $serviceData['customer_id'],
            'service_type' => $serviceData['service_type'],
            'collection_frequency' => $serviceData['collection_frequency'],
            'collection_day' => $serviceData['collection_day'],
            'collection_time' => $serviceData['collection_time'],
            'address' => $serviceData['address'],
            'coordinates' => $serviceData['coordinates'] ?? null,
            'bin_size' => $serviceData['bin_size'],
            'waste_types' => $serviceData['waste_types'],
            'special_instructions' => $serviceData['special_instructions'] ?? '',
            'status' => 'active'
        ];

        // Save to database
        $this->saveWasteService($service);

        // Schedule initial collection
        $this->scheduleInitialCollection($service);

        return [
            'success' => true,
            'service_id' => $serviceId,
            'service_fee' => $serviceFee,
            'next_collection_date' => $this->calculateNextCollectionDate($service)
        ];
    }

    /**
     * Process waste collection billing
     */
    public function processWasteBilling(int $customerId, string $billingPeriod): array
    {
        // Get customer services
        $services = $this->getCustomerServices($customerId);

        $totalAmount = 0;
        $billingItems = [];

        foreach ($services as $service) {
            $serviceAmount = $this->calculateServiceFee($service);
            $billingItems[] = [
                'service_id' => $service['service_id'],
                'service_type' => $service['service_type'],
                'amount' => $serviceAmount,
                'description' => ucfirst($service['service_type']) . ' waste collection service'
            ];
            $totalAmount += $serviceAmount;
        }

        // Add taxes
        $taxes = $totalAmount * 0.08; // 8% tax
        $totalAmount += $taxes;

        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber();

        // Create billing record
        $billing = [
            'customer_id' => $customerId,
            'billing_period' => $billingPeriod,
            'service_type' => 'waste_management',
            'base_fee' => $totalAmount - $taxes,
            'taxes' => $taxes,
            'total_amount' => $totalAmount,
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'status' => 'unpaid',
            'invoice_number' => $invoiceNumber
        ];

        // Save billing record
        $this->saveBillingRecord($billing);

        // Send billing notification
        $this->sendNotification('billing_reminder', $customerId, [
            'amount' => number_format($totalAmount, 2),
            'due_date' => $billing['due_date']
        ]);

        return [
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'total_amount' => $totalAmount,
            'due_date' => $billing['due_date'],
            'billing_items' => $billingItems
        ];
    }

    /**
     * Send environmental alert
     */
    private function sendEnvironmentalAlert(array $record): void
    {
        $this->sendNotification('environmental_alert', null, [
            'site_name' => $record['site_id'],
            'alert_type' => $record['monitoring_type'],
            'reading_value' => $record['reading_value'] . ' ' . $record['unit']
        ]);
    }

    /**
     * Record environmental monitoring data
     */
    public function recordEnvironmentalData(array $monitoringData): array
    {
        // Validate monitoring data
        $validation = $this->validateEnvironmentalData($monitoringData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check compliance
        $isCompliant = $this->checkEnvironmentalCompliance($monitoringData);

        // Create monitoring record
        $record = [
            'site_id' => $monitoringData['site_id'],
            'monitoring_type' => $monitoringData['monitoring_type'],
            'sensor_id' => $monitoringData['sensor_id'] ?? null,
            'reading_value' => $monitoringData['reading_value'],
            'unit' => $monitoringData['unit'],
            'is_compliant' => $isCompliant,
            'recorded_at' => $monitoringData['recorded_at'] ?? date('Y-m-d H:i:s'),
            'recorded_by' => $monitoringData['recorded_by'],
            'notes' => $monitoringData['notes'] ?? ''
        ];

        // Add threshold information
        $thresholds = $this->getEnvironmentalThresholds($monitoringData['monitoring_type']);
        $record['threshold_min'] = $thresholds['min'];
        $record['threshold_max'] = $thresholds['max'];

        // Save to database
        $this->saveEnvironmentalRecord($record);

        // Send alert if non-compliant
        if (!$isCompliant) {
            $this->sendEnvironmentalAlert($record);
        }

        return [
            'success' => true,
            'is_compliant' => $isCompliant,
            'threshold_min' => $thresholds['min'],
            'threshold_max' => $thresholds['max']
        ];
    }

    /**
     * Get waste collection schedule
     */
    public function getCollectionSchedule(int $customerId, array $filters = []): array
    {
        $services = $this->getCustomerServices($customerId);

        $schedule = [];
        foreach ($services as $service) {
            $nextCollection = $this->calculateNextCollectionDate($service);
            $schedule[] = [
                'service_id' => $service['service_id'],
                'service_type' => $service['service_type'],
                'collection_day' => $service['collection_day'],
                'collection_time' => $service['collection_time'],
                'next_collection' => $nextCollection,
                'address' => $service['address'],
                'status' => $service['status']
            ];
        }

        return [
            'customer_id' => $customerId,
            'services' => $schedule,
            'total_services' => count($schedule)
        ];
    }

    /**
     * Generate waste management report
     */
    public function generateWasteReport(array $filters = []): array
    {
        $query = "SELECT * FROM waste_collection_requests WHERE 1=1";

        if (isset($filters['status'])) {
            $query .= " AND status = '{$filters['status']}'";
        }

        if (isset($filters['waste_type'])) {
            $query .= " AND waste_type = '{$filters['waste_type']}'";
        }

        if (isset($filters['date_from'])) {
            $query .= " AND pickup_date >= '{$filters['date_from']}'";
        }

        if (isset($filters['date_to'])) {
            $query .= " AND pickup_date <= '{$filters['date_to']}'";
        }

        // Execute query and return results
        return [
            'filters' => $filters,
            'data' => [], // Would contain actual query results
            'generated_at' => date('c')
        ];
    }

    /**
     * Validate collection request data
     */
    private function validateCollectionRequest(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'customer_id', 'request_type', 'waste_type', 'pickup_address', 'pickup_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!isset($this->wasteTypes[$data['waste_type'] ?? ''])) {
            $errors[] = "Invalid waste type";
        }

        // Check pickup date is not in the past
        if (isset($data['pickup_date']) && strtotime($data['pickup_date']) < strtotime('today')) {
            $errors[] = "Pickup date cannot be in the past";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate service data
     */
    private function validateServiceData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'customer_id', 'service_type', 'collection_frequency',
            'collection_day', 'collection_time', 'address', 'bin_size'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!isset($this->servicePricing[$data['service_type'] ?? ''])) {
            $errors[] = "Invalid service type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate environmental data
     */
    private function validateEnvironmentalData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'site_id', 'monitoring_type', 'reading_value', 'recorded_by'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!isset($this->environmentalThresholds[$data['monitoring_type'] ?? ''])) {
            $errors[] = "Invalid monitoring type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate request ID
     */
    private function generateRequestId(): string
    {
        return 'WCR' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate service ID
     */
    private function generateServiceId(): string
    {
        return 'WCS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        return 'WINV' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate collection cost
     */
    private function calculateCollectionCost(array $requestData): float
    {
        $wasteType = $this->wasteTypes[$requestData['waste_type']];
        $baseCost = 50.00; // Base collection cost

        // Add special handling surcharge
        if ($requestData['special_handling'] ?? false) {
            $baseCost += 100.00;
        }

        // Add hazardous waste surcharge
        if ($wasteType['category'] === 'hazardous') {
            $baseCost += 200.00;
        }

        // Add quantity-based cost
        if (isset($requestData['quantity'])) {
            $quantityCost = $requestData['quantity'] * 0.5; // $0.50 per kg
            $baseCost += $quantityCost;
        }

        return round($baseCost, 2);
    }

    /**
     * Calculate service fee
     */
    private function calculateServiceFee(array $serviceData): float
    {
        $pricing = $this->servicePricing[$serviceData['service_type']];

        $baseFee = $pricing['base_fee'];
        $frequencyMultiplier = $pricing['frequency_multipliers'][$serviceData['collection_frequency']] ?? 1.0;
        $binSizeMultiplier = $pricing['bin_size_multipliers'][$serviceData['bin_size']] ?? 1.0;

        return round($baseFee * $frequencyMultiplier * $binSizeMultiplier, 2);
    }

    /**
     * Calculate next collection date
     */
    private function calculateNextCollectionDate(array $service): string
    {
        $today = date('Y-m-d');
        $collectionDay = $service['collection_day'];

        // Find next occurrence of collection day
        $nextDate = date('Y-m-d', strtotime("next {$collectionDay}", strtotime($today)));

        // If collection day is today and time hasn't passed, use today
        if (date('l', strtotime($today)) === ucfirst($collectionDay)) {
            $currentTime = date('H:i:s');
            if ($currentTime < $service['collection_time']) {
                $nextDate = $today;
            }
        }

        return $nextDate;
    }

    /**
     * Check environmental compliance
     */
    private function checkEnvironmentalCompliance(array $data): bool
    {
        $thresholds = $this->getEnvironmentalThresholds($data['monitoring_type']);

        $value = $data['reading_value'];
        $min = $thresholds['min'];
        $max = $thresholds['max'];

        return ($value >= $min && $value <= $max);
    }

    /**
     * Get environmental thresholds
     */
    private function getEnvironmentalThresholds(string $monitoringType): array
    {
        return $this->environmentalThresholds[$monitoringType] ?? ['min' => 0, 'max' => 100];
    }

    /**
     * Send environmental alert
     */
    private function sendEnvironmentalAlert(array $record): void
    {
        $this->sendNotification('environmental_alert', null, [
            'site_name' => $record['site_id'],
            'alert_type' => $record['monitoring_type'],
            'reading_value' => $record['reading_value'] . ' ' . $record['unit']
        ]);
    }

    /**
     * Save collection request to database
     */
    private function saveCollectionRequest(array $request): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO waste_collection_requests (
                request_id, customer_id, request_type, waste_type, quantity,
                unit, pickup_address, pickup_date, pickup_time_slot,
                special_handling, hazardous_materials, status, estimated_cost, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $request['request_id'],
                $request['customer_id'],
                $request['request_type'],
                $request['waste_type'],
                $request['quantity'],
                $request['unit'],
                $request['pickup_address'],
                $request['pickup_date'],
                $request['pickup_time_slot'],
                $request['special_handling'],
                json_encode($request['hazardous_materials']),
                $request['status'],
                $request['estimated_cost'],
                $request['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving collection request: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start collection workflow
     */
    private function startCollectionWorkflow(string $requestId): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            return $workflowEngine->startWorkflow('waste_collection_request', $requestId);
        } catch (\Exception $e) {
            error_log("Error starting collection workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, ?int $userId, array $data): bool
    {
        try {
            $notificationManager = new NotificationManager();
            return $notificationManager->sendNotification($type, $userId, $data);
        } catch (\Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save waste service to database
     */
    private function saveWasteService(array $service): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO waste_collection_schedules (
                service_id, customer_id, service_type, collection_frequency,
                collection_day, collection_time, address, coordinates,
                bin_size, waste_types, special_instructions, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $service['service_id'],
                $service['customer_id'],
                $service['service_type'],
                $service['collection_frequency'],
                $service['collection_day'],
                $service['collection_time'],
                $service['address'],
                $service['coordinates'],
                $service['bin_size'],
                json_encode($service['waste_types']),
                $service['special_instructions'],
                $service['status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving waste service: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule initial collection
     */
    private function scheduleInitialCollection(array $service): bool
    {
        try {
            $nextCollectionDate = $this->calculateNextCollectionDate($service);

            $db = Database::getInstance();
            $sql = "UPDATE waste_collection_schedules SET created_at = ? WHERE service_id = ?";
            return $db->execute($sql, [date('Y-m-d H:i:s'), $service['service_id']]);
        } catch (\Exception $e) {
            error_log("Error scheduling initial collection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get customer services
     */
    private function getCustomerServices(int $customerId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM waste_collection_schedules WHERE customer_id = ? AND status = 'active'";
            $results = $db->fetchAll($sql, [$customerId]);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['waste_types'] = json_decode($result['waste_types'], true);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting customer services: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Save billing record to database
     */
    private function saveBillingRecord(array $billing): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO waste_billing (
                customer_id, billing_period, service_type, base_fee,
                additional_fees, taxes, total_amount, due_date, status, invoice_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $billing['customer_id'],
                $billing['billing_period'],
                $billing['service_type'],
                $billing['base_fee'],
                $billing['additional_fees'] ?? 0.00,
                $billing['taxes'],
                $billing['total_amount'],
                $billing['due_date'],
                $billing['status'],
                $billing['invoice_number']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving billing record: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get billing record by invoice number
     */
    private function getBillingRecord(string $invoiceNumber): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM waste_billing WHERE invoice_number = ?";
            return $db->fetch($sql, [$invoiceNumber]);
        } catch (\Exception $e) {
            error_log("Error getting billing record: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update billing status
     */
    private function updateBillingStatus(string $invoiceNumber, string $status, string $paymentDate, string $paymentMethod): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE waste_billing SET status = ?, payment_date = ?, payment_method = ? WHERE invoice_number = ?";
            return $db->execute($sql, [$status, $paymentDate, $paymentMethod, $invoiceNumber]);
        } catch (\Exception $e) {
            error_log("Error updating billing status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save environmental record to database
     */
    private function saveEnvironmentalRecord(array $record): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO environmental_monitoring (
                site_id, monitoring_type, sensor_id, reading_value,
                unit, threshold_min, threshold_max, is_compliant,
                recorded_at, recorded_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $record['site_id'],
                $record['monitoring_type'],
                $record['sensor_id'],
                $record['reading_value'],
                $record['unit'],
                $record['threshold_min'],
                $record['threshold_max'],
                $record['is_compliant'],
                $record['recorded_at'],
                $record['recorded_by'],
                $record['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving environmental record: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get environmental thresholds
     */
    private function getEnvironmentalThresholds(string $type): array
    {
        return $this->environmentalThresholds[$type] ?? ['min' => 0, 'max' => 100];
    }

    /**
     * Get waste services (API handler)
     */
    public function getWasteServices(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM waste_collection_schedules WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['service_type'])) {
                $sql .= " AND service_type = ?";
                $params[] = $filters['service_type'];
            }

            if (isset($filters['customer_id'])) {
                $sql .= " AND customer_id = ?";
                $params[] = $filters['customer_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['waste_types'] = json_decode($result['waste_types'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting waste services: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve waste services'
            ];
        }
    }

    /**
     * Create waste service (API handler)
     */
    public function createWasteService(array $data): array
    {
        return $this->scheduleWasteService($data);
    }

    /**
     * Get collection requests (API handler)
     */
    public function getCollectionRequests(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM waste_collection_requests WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['request_type'])) {
                $sql .= " AND request_type = ?";
                $params[] = $filters['request_type'];
            }

            if (isset($filters['customer_id'])) {
                $sql .= " AND customer_id = ?";
                $params[] = $filters['customer_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['hazardous_materials'] = json_decode($result['hazardous_materials'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting collection requests: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve collection requests'
            ];
        }
    }

    /**
     * Create collection request (API handler)
     */
    public function createCollectionRequest(array $data): array
    {
        return $this->createCollectionRequest($data);
    }

    /**
     * Get billing info (API handler)
     */
    public function getBillingInfo(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM waste_billing WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['customer_id'])) {
                $sql .= " AND customer_id = ?";
                $params[] = $filters['customer_id'];
            }

            if (isset($filters['billing_period'])) {
                $sql .= " AND billing_period = ?";
                $params[] = $filters['billing_period'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting billing info: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve billing information'
            ];
        }
    }

    /**
     * Get disposal sites (API handler)
     */
    public function getDisposalSites(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM waste_disposal_sites WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['site_type'])) {
                $sql .= " AND site_type = ?";
                $params[] = $filters['site_type'];
            }

            $sql .= " ORDER BY site_name ASC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['waste_types_accepted'] = json_decode($result['waste_types_accepted'], true);
                $result['operating_hours'] = json_decode($result['operating_hours'], true);
                $result['contact_info'] = json_decode($result['contact_info'], true);
                $result['environmental_permits'] = json_decode($result['environmental_permits'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting disposal sites: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve disposal sites'
            ];
        }
    }

    /**
     * Get environmental data (API handler)
     */
    public function getEnvironmentalData(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM environmental_monitoring WHERE 1=1";
            $params = [];

            if (isset($filters['site_id'])) {
                $sql .= " AND site_id = ?";
                $params[] = $filters['site_id'];
            }

            if (isset($filters['monitoring_type'])) {
                $sql .= " AND monitoring_type = ?";
                $params[] = $filters['monitoring_type'];
            }

            if (isset($filters['is_compliant'])) {
                $sql .= " AND is_compliant = ?";
                $params[] = $filters['is_compliant'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND recorded_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND recorded_at <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY recorded_at DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting environmental data: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve environmental data'
            ];
        }
    }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        try {
            $db = Database::getInstance();

            // Get collection request statistics
            $requestStats = $db->fetch("
                SELECT
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                    AVG(estimated_cost) as average_cost
                FROM waste_collection_requests
            ");

            // Get service statistics
            $serviceStats = $db->fetch("
                SELECT
                    COUNT(*) as total_services,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_services
                FROM waste_collection_schedules
            ");

            // Get billing statistics
            $billingStats = $db->fetch("
                SELECT
                    SUM(total_amount) as total_revenue,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices
                FROM waste_billing
            ");

            // Get environmental compliance statistics
            $environmentalStats = $db->fetch("
                SELECT
                    COUNT(*) as total_readings,
                    COUNT(CASE WHEN is_compliant = 1 THEN 1 END) as compliant_readings
                FROM environmental_monitoring
            ");

            $complianceRate = $environmentalStats['total_readings'] > 0 ?
                round(($environmentalStats['compliant_readings'] / $environmentalStats['total_readings']) * 100, 2) : 0;

            return [
                'total_collection_requests' => $requestStats['total_requests'] ?? 0,
                'completed_requests' => $requestStats['completed_requests'] ?? 0,
                'pending_requests' => $requestStats['pending_requests'] ?? 0,
                'active_services' => $serviceStats['active_services'] ?? 0,
                'total_revenue' => $billingStats['total_revenue'] ?? 0.00,
                'environmental_compliance_rate' => $complianceRate,
                'average_request_cost' => round($requestStats['average_cost'] ?? 0, 2)
            ];
        } catch (\Exception $e) {
            error_log("Error getting module statistics: " . $e->getMessage());
            return [
                'total_collection_requests' => 0,
                'completed_requests' => 0,
                'pending_requests' => 0,
                'active_services' => 0,
                'total_revenue' => 0.00,
                'environmental_compliance_rate' => 0.0,
                'average_request_cost' => 0.00
            ];
        }
    }
}

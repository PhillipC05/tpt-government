<?php
/**
 * TPT Government Platform - Property Services Module
 *
 * Comprehensive property management system supporting property search, valuation,
 * rates billing, objection processing, and property transfer tracking
 */

namespace Modules\PropertyServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class PropertyServicesModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Property Services',
        'version' => '2.1.0',
        'description' => 'Comprehensive property management system for search, valuation, and billing',
        'author' => 'TPT Government Platform',
        'category' => 'public_services',
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
        'property.view' => 'View property information and records',
        'property.search' => 'Search property database',
        'property.valuation' => 'Access property valuation information',
        'property.rates' => 'View and manage rates billing',
        'property.objection' => 'File and manage rates objections',
        'property.transfer' => 'Process property transfers',
        'property.admin' => 'Administrative property management functions',
        'property.report' => 'Generate property reports and analytics'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'properties' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'property_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'legal_description' => 'TEXT NOT NULL',
            'street_address' => 'VARCHAR(255) NOT NULL',
            'suburb' => 'VARCHAR(100)',
            'city' => 'VARCHAR(100) NOT NULL',
            'postcode' => 'VARCHAR(10)',
            'land_area' => 'DECIMAL(10,2)',
            'floor_area' => 'DECIMAL(10,2)',
            'property_type' => "ENUM('residential','commercial','industrial','rural','vacant','other') NOT NULL",
            'zoning' => 'VARCHAR(50)',
            'valuation_date' => 'DATE',
            'capital_value' => 'DECIMAL(12,2)',
            'land_value' => 'DECIMAL(12,2)',
            'improvement_value' => 'DECIMAL(12,2)',
            'rating_category' => 'VARCHAR(50)',
            'rating_unit' => 'VARCHAR(20)',
            'rating_factor' => 'DECIMAL(8,4)',
            'owner_id' => 'INT',
            'owner_name' => 'VARCHAR(255)',
            'owner_address' => 'TEXT',
            'owner_phone' => 'VARCHAR(20)',
            'owner_email' => 'VARCHAR(255)',
            'occupancy_status' => "ENUM('owner_occupied','tenant_occupied','vacant','business_use') DEFAULT 'owner_occupied'",
            'building_details' => 'JSON',
            'coordinates' => 'VARCHAR(100)',
            'last_updated' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'property_rates' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'property_id' => 'INT NOT NULL',
            'rating_year' => 'YEAR NOT NULL',
            'rates_amount' => 'DECIMAL(10,2) NOT NULL',
            'due_date' => 'DATE NOT NULL',
            'payment_status' => "ENUM('pending','paid','overdue','written_off') DEFAULT 'pending'",
            'payment_date' => 'DATE NULL',
            'payment_method' => 'VARCHAR(50)',
            'penalty_amount' => 'DECIMAL(8,2) DEFAULT 0.00',
            'discount_amount' => 'DECIMAL(8,2) DEFAULT 0.00',
            'total_amount' => 'DECIMAL(10,2) NOT NULL',
            'instalment_plan' => 'BOOLEAN DEFAULT FALSE',
            'instalment_number' => 'INT DEFAULT 1',
            'instalment_total' => 'INT DEFAULT 1',
            'generated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'rates_objections' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'objection_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'property_id' => 'INT NOT NULL',
            'objector_id' => 'INT NOT NULL',
            'objector_name' => 'VARCHAR(255) NOT NULL',
            'objector_address' => 'TEXT NOT NULL',
            'objector_phone' => 'VARCHAR(20)',
            'objector_email' => 'VARCHAR(255)',
            'rating_year' => 'YEAR NOT NULL',
            'grounds_for_objection' => 'TEXT NOT NULL',
            'requested_valuation' => 'DECIMAL(12,2)',
            'evidence_provided' => 'JSON',
            'status' => "ENUM('lodged','under_review','hearing_scheduled','decided','withdrawn','dismissed') DEFAULT 'lodged'",
            'decision' => 'TEXT',
            'decision_date' => 'DATE NULL',
            'adjustment_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
            'refund_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
            'hearing_date' => 'DATE NULL',
            'hearing_officer' => 'VARCHAR(100)',
            'lodged_date' => 'DATE NOT NULL',
            'processing_officer' => 'INT NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'property_transfers' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'transfer_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'property_id' => 'INT NOT NULL',
            'seller_id' => 'INT NOT NULL',
            'seller_name' => 'VARCHAR(255) NOT NULL',
            'seller_address' => 'TEXT NOT NULL',
            'buyer_id' => 'INT NOT NULL',
            'buyer_name' => 'VARCHAR(255) NOT NULL',
            'buyer_address' => 'TEXT NOT NULL',
            'transfer_price' => 'DECIMAL(12,2)',
            'transfer_date' => 'DATE NOT NULL',
            'settlement_date' => 'DATE NULL',
            'transfer_type' => "ENUM('sale','gift','inheritance','court_order','mortgage','other') NOT NULL",
            'stamp_duty' => 'DECIMAL(10,2) DEFAULT 0.00',
            'stamp_duty_paid' => 'BOOLEAN DEFAULT FALSE',
            'stamp_duty_payment_date' => 'DATE NULL',
            'legal_documents' => 'JSON',
            'status' => "ENUM('pending','settled','cancelled','disputed') DEFAULT 'pending'",
            'dispute_reason' => 'TEXT',
            'dispute_resolution' => 'TEXT',
            'registered_by' => 'INT NOT NULL',
            'registration_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'property_valuations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'property_id' => 'INT NOT NULL',
            'valuation_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'valuation_date' => 'DATE NOT NULL',
            'valuation_type' => "ENUM('annual','supplementary','objection','court','sale') NOT NULL",
            'valuer_id' => 'INT NOT NULL',
            'capital_value' => 'DECIMAL(12,2) NOT NULL',
            'land_value' => 'DECIMAL(12,2)',
            'improvement_value' => 'DECIMAL(12,2)',
            'valuation_method' => "ENUM('comparison','income','cost','other') NOT NULL",
            'comparable_sales' => 'JSON',
            'valuation_report' => 'TEXT',
            'attachments' => 'JSON',
            'status' => "ENUM('draft','completed','reviewed','approved','superseded') DEFAULT 'draft'",
            'reviewed_by' => 'INT NULL',
            'review_date' => 'DATE NULL',
            'review_comments' => 'TEXT',
            'effective_date' => 'DATE NOT NULL',
            'expiry_date' => 'DATE NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'development_contributions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'contribution_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'property_id' => 'INT NOT NULL',
            'development_type' => "ENUM('subdivision','building','infrastructure','other') NOT NULL",
            'contribution_amount' => 'DECIMAL(10,2) NOT NULL',
            'due_date' => 'DATE NOT NULL',
            'payment_status' => "ENUM('pending','paid','overdue','waived','written_off') DEFAULT 'pending'",
            'payment_date' => 'DATE NULL',
            'payment_method' => 'VARCHAR(50)',
            'assessment_date' => 'DATE NOT NULL',
            'assessment_officer' => 'INT NOT NULL',
            'assessment_basis' => 'TEXT',
            'appeal_status' => "ENUM('none','lodged','heard','upheld','dismissed') DEFAULT 'none'",
            'appeal_date' => 'DATE NULL',
            'appeal_decision' => 'TEXT',
            'waiver_reason' => 'TEXT',
            'waiver_approved_by' => 'INT NULL',
            'waiver_date' => 'DATE NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'property_inspections' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'inspection_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'property_id' => 'INT NOT NULL',
            'inspection_type' => "ENUM('routine','complaint','pre_sale','building','health_safety','other') NOT NULL",
            'scheduled_date' => 'DATE NOT NULL',
            'actual_date' => 'DATE NULL',
            'inspector_id' => 'INT NOT NULL',
            'status' => "ENUM('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled'",
            'findings' => 'JSON',
            'recommendations' => 'JSON',
            'compliance_status' => "ENUM('compliant','non_compliant','partial','not_applicable') NULL",
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATE NULL',
            'attachments' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'property_documents' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'property_id' => 'INT NOT NULL',
            'document_type' => "ENUM('title','survey','valuation','inspection','permit','other') NOT NULL",
            'document_number' => 'VARCHAR(50)',
            'document_name' => 'VARCHAR(255) NOT NULL',
            'file_path' => 'VARCHAR(500) NOT NULL',
            'file_size' => 'INT',
            'mime_type' => 'VARCHAR(100)',
            'uploaded_by' => 'INT NOT NULL',
            'upload_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'document_date' => 'DATE NULL',
            'expiry_date' => 'DATE NULL',
            'is_public' => 'BOOLEAN DEFAULT FALSE',
            'access_level' => "ENUM('public','owner','staff','restricted') DEFAULT 'staff'",
            'tags' => 'JSON',
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
            'path' => '/api/property/search',
            'handler' => 'searchProperties',
            'auth' => true,
            'permissions' => ['property.search']
        ],
        [
            'method' => 'GET',
            'path' => '/api/property/{id}',
            'handler' => 'getProperty',
            'auth' => true,
            'permissions' => ['property.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/property/{id}',
            'handler' => 'updateProperty',
            'auth' => true,
            'permissions' => ['property.admin']
        ],
        [
            'method' => 'GET',
            'path' => '/api/property/{id}/valuation',
            'handler' => 'getPropertyValuation',
            'auth' => true,
            'permissions' => ['property.valuation']
        ],
        [
            'method' => 'GET',
            'path' => '/api/property/{id}/rates',
            'handler' => 'getPropertyRates',
            'auth' => true,
            'permissions' => ['property.rates']
        ],
        [
            'method' => 'POST',
            'path' => '/api/property/{id}/rates',
            'handler' => 'generatePropertyRates',
            'auth' => true,
            'permissions' => ['property.admin']
        ],
        [
            'method' => 'POST',
            'path' => '/api/property/{id}/objection',
            'handler' => 'lodgeRatesObjection',
            'auth' => true,
            'permissions' => ['property.objection']
        ],
        [
            'method' => 'GET',
            'path' => '/api/property/objections',
            'handler' => 'getRatesObjections',
            'auth' => true,
            'permissions' => ['property.objection']
        ],
        [
            'method' => 'POST',
            'path' => '/api/property/transfer',
            'handler' => 'registerPropertyTransfer',
            'auth' => true,
            'permissions' => ['property.transfer']
        ],
        [
            'method' => 'GET',
            'path' => '/api/property/transfers',
            'handler' => 'getPropertyTransfers',
            'auth' => true,
            'permissions' => ['property.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/property/{id}/valuation',
            'handler' => 'createPropertyValuation',
            'auth' => true,
            'permissions' => ['property.valuation']
        ],
        [
            'method' => 'GET',
            'path' => '/api/property/{id}/documents',
            'handler' => 'getPropertyDocuments',
            'auth' => true,
            'permissions' => ['property.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/property/{id}/documents',
            'handler' => 'uploadPropertyDocument',
            'auth' => true,
            'permissions' => ['property.admin']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'rates_objection_process' => [
            'name' => 'Rates Objection Process',
            'description' => 'Workflow for handling rates assessment objections',
            'steps' => [
                'lodged' => ['name' => 'Objection Lodged', 'next' => 'initial_review'],
                'initial_review' => ['name' => 'Initial Review', 'next' => 'assessment_review'],
                'assessment_review' => ['name' => 'Assessment Review', 'next' => 'hearing_scheduled'],
                'hearing_scheduled' => ['name' => 'Hearing Scheduled', 'next' => 'hearing_held'],
                'hearing_held' => ['name' => 'Hearing Held', 'next' => 'decision_made'],
                'decision_made' => ['name' => 'Decision Made', 'next' => ['objection_upheld', 'objection_dismissed']],
                'objection_upheld' => ['name' => 'Objection Upheld', 'next' => 'valuation_adjusted'],
                'valuation_adjusted' => ['name' => 'Valuation Adjusted', 'next' => 'rates_recalculated'],
                'rates_recalculated' => ['name' => 'Rates Recalculated', 'next' => 'refund_processed'],
                'refund_processed' => ['name' => 'Refund Processed', 'next' => 'objection_closed'],
                'objection_dismissed' => ['name' => 'Objection Dismissed', 'next' => 'objection_closed'],
                'objection_closed' => ['name' => 'Objection Closed', 'next' => null]
            ]
        ],
        'property_transfer_process' => [
            'name' => 'Property Transfer Process',
            'description' => 'Workflow for processing property transfers and registrations',
            'steps' => [
                'application_received' => ['name' => 'Application Received', 'next' => 'document_verification'],
                'document_verification' => ['name' => 'Document Verification', 'next' => 'stamp_duty_assessment'],
                'stamp_duty_assessment' => ['name' => 'Stamp Duty Assessment', 'next' => 'stamp_duty_payment'],
                'stamp_duty_payment' => ['name' => 'Stamp Duty Payment', 'next' => 'transfer_registration'],
                'transfer_registration' => ['name' => 'Transfer Registration', 'next' => 'title_updated'],
                'title_updated' => ['name' => 'Title Updated', 'next' => 'transfer_completed'],
                'transfer_completed' => ['name' => 'Transfer Completed', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'rates_objection_form' => [
            'name' => 'Rates Objection Form',
            'fields' => [
                'property_id' => ['type' => 'hidden', 'required' => true],
                'objector_name' => ['type' => 'text', 'required' => true, 'label' => 'Your Full Name'],
                'objector_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Your Address'],
                'objector_phone' => ['type' => 'tel', 'required' => false, 'label' => 'Phone Number'],
                'objector_email' => ['type' => 'email', 'required' => false, 'label' => 'Email Address'],
                'rating_year' => ['type' => 'number', 'required' => true, 'label' => 'Rating Year', 'min' => '2000', 'max' => '2050'],
                'grounds_for_objection' => ['type' => 'textarea', 'required' => true, 'label' => 'Grounds for Objection'],
                'requested_valuation' => ['type' => 'number', 'required' => false, 'label' => 'Requested Capital Value', 'step' => '0.01', 'min' => '0'],
                'additional_comments' => ['type' => 'textarea', 'required' => false, 'label' => 'Additional Comments']
            ],
            'documents' => [
                'valuation_evidence' => ['required' => false, 'label' => 'Supporting Evidence'],
                'comparable_sales' => ['required' => false, 'label' => 'Comparable Sales Data'],
                'professional_report' => ['required' => false, 'label' => 'Professional Valuation Report']
            ]
        ],
        'property_transfer_form' => [
            'name' => 'Property Transfer Application',
            'fields' => [
                'property_id' => ['type' => 'hidden', 'required' => true],
                'transfer_type' => ['type' => 'select', 'required' => true, 'label' => 'Transfer Type'],
                'seller_name' => ['type' => 'text', 'required' => true, 'label' => 'Seller Name'],
                'seller_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Seller Address'],
                'buyer_name' => ['type' => 'text', 'required' => true, 'label' => 'Buyer Name'],
                'buyer_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Buyer Address'],
                'transfer_price' => ['type' => 'number', 'required' => false, 'label' => 'Transfer Price', 'step' => '0.01', 'min' => '0'],
                'transfer_date' => ['type' => 'date', 'required' => true, 'label' => 'Transfer Date'],
                'settlement_date' => ['type' => 'date', 'required' => false, 'label' => 'Settlement Date']
            ],
            'documents' => [
                'transfer_agreement' => ['required' => true, 'label' => 'Transfer Agreement'],
                'title_documents' => ['required' => true, 'label' => 'Title Documents'],
                'identification' => ['required' => true, 'label' => 'Identification Documents'],
                'stamp_duty_payment' => ['required' => false, 'label' => 'Stamp Duty Payment Receipt']
            ]
        ],
        'property_search_form' => [
            'name' => 'Property Search',
            'fields' => [
                'search_type' => ['type' => 'select', 'required' => true, 'label' => 'Search Type', 'options' => ['address', 'owner', 'legal_description', 'property_id']],
                'search_term' => ['type' => 'text', 'required' => true, 'label' => 'Search Term'],
                'suburb' => ['type' => 'text', 'required' => false, 'label' => 'Suburb'],
                'property_type' => ['type' => 'select', 'required' => false, 'label' => 'Property Type'],
                'valuation_range_min' => ['type' => 'number', 'required' => false, 'label' => 'Min Capital Value', 'step' => '0.01'],
                'valuation_range_max' => ['type' => 'number', 'required' => false, 'label' => 'Max Capital Value', 'step' => '0.01']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'property_valuation_report' => [
            'name' => 'Property Valuation Report',
            'description' => 'Property valuations by area, type, and value range',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'suburb' => ['type' => 'select', 'required' => false],
                'property_type' => ['type' => 'select', 'required' => false],
                'valuation_range' => ['type' => 'number_range', 'required' => false]
            ],
            'columns' => [
                'property_id', 'street_address', 'suburb', 'property_type',
                'capital_value', 'land_value', 'valuation_date'
            ]
        ],
        'rates_collection_report' => [
            'name' => 'Rates Collection Report',
            'description' => 'Rates collection status and outstanding amounts',
            'parameters' => [
                'rating_year' => ['type' => 'number', 'required' => true, 'default' => date('Y')],
                'collection_status' => ['type' => 'select', 'required' => false],
                'overdue_period' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'property_id', 'owner_name', 'rates_amount', 'due_date',
                'payment_status', 'payment_date', 'outstanding_amount'
            ]
        ],
        'objection_statistics_report' => [
            'name' => 'Objection Statistics Report',
            'description' => 'Analysis of rates objections and outcomes',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'objection_status' => ['type' => 'select', 'required' => false],
                'decision_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'objection_number', 'property_id', 'grounds_for_objection',
                'status', 'decision', 'adjustment_amount', 'processing_time'
            ]
        ],
        'property_transfer_report' => [
            'name' => 'Property Transfer Report',
            'description' => 'Property transfers and stamp duty collection',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'transfer_type' => ['type' => 'select', 'required' => false],
                'value_range' => ['type' => 'number_range', 'required' => false]
            ],
            'columns' => [
                'transfer_number', 'property_id', 'transfer_price',
                'stamp_duty', 'transfer_date', 'buyer_name', 'seller_name'
            ]
        ],
        'vacant_land_report' => [
            'name' => 'Vacant Land Report',
            'description' => 'Identification of vacant properties for development',
            'parameters' => [
                'suburb' => ['type' => 'select', 'required' => false],
                'land_area_min' => ['type' => 'number', 'required' => false],
                'zoning_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'property_id', 'street_address', 'land_area', 'zoning',
                'capital_value', 'owner_name', 'occupancy_status'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'rates_bill_generated' => [
            'name' => 'Rates Bill Generated',
            'template' => 'Your rates bill for {property_address} has been generated. Amount: ${rates_amount}, Due date: {due_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['rates_generated']
        ],
        'rates_payment_overdue' => [
            'name' => 'Rates Payment Overdue',
            'template' => 'Your rates payment for {property_address} is overdue. Amount due: ${outstanding_amount}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['rates_overdue']
        ],
        'objection_lodged' => [
            'name' => 'Objection Lodged',
            'template' => 'Your rates objection for {property_address} has been lodged. Reference: {objection_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['objection_created']
        ],
        'objection_decision' => [
            'name' => 'Objection Decision',
            'template' => 'A decision has been made on your rates objection {objection_number}. Status: {decision_status}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['objection_decided']
        ],
        'property_valuation_updated' => [
            'name' => 'Property Valuation Updated',
            'template' => 'The valuation for {property_address} has been updated. New capital value: ${capital_value}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['valuation_updated']
        ],
        'transfer_registered' => [
            'name' => 'Property Transfer Registered',
            'template' => 'Property transfer for {property_address} has been registered. Transfer number: {transfer_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['transfer_completed']
        ],
        'stamp_duty_due' => [
            'name' => 'Stamp Duty Due',
            'template' => 'Stamp duty payment is due for property transfer {transfer_number}. Amount: ${stamp_duty}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['stamp_duty_assessed']
        ],
        'development_contribution_due' => [
            'name' => 'Development Contribution Due',
            'template' => 'Development contribution is due for {property_address}. Amount: ${contribution_amount}, Due date: {due_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['contribution_assessed']
        ]
    ];

    /**
     * Property types configuration
     */
    private array $propertyTypes = [];

    /**
     * Rating categories and factors
     */
    private array $ratingCategories = [];

    /**
     * Valuation methods
     */
    private array $valuationMethods = [];

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
            'rating_year' => date('Y'),
            'rates_due_day' => 31,
            'rates_due_month' => 7, // July
            'penalty_rate' => 0.05, // 5% per annum
            'instalment_options' => [1, 2, 4, 6, 12],
            'objection_deadline_days' => 30,
            'valuation_cycle_years' => 3,
            'stamp_duty_threshold' => 10000.00,
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
        $this->initializePropertyTypes();
        $this->initializeRatingCategories();
        $this->initializeValuationMethods();
    }

    /**
     * Initialize property types
     */
    private function initializePropertyTypes(): void
    {
        $this->propertyTypes = [
            'residential' => [
                'name' => 'Residential Property',
                'description' => 'Houses, apartments, and residential units',
                'rating_category' => 'residential',
                'valuation_method' => 'comparison'
            ],
            'commercial' => [
                'name' => 'Commercial Property',
                'description' => 'Offices, retail spaces, and commercial buildings',
                'rating_category' => 'commercial',
                'valuation_method' => 'income'
            ],
            'industrial' => [
                'name' => 'Industrial Property',
                'description' => 'Factories, warehouses, and industrial facilities',
                'rating_category' => 'industrial',
                'valuation_method' => 'income'
            ],
            'rural' => [
                'name' => 'Rural Property',
                'description' => 'Farms, lifestyle blocks, and rural land',
                'rating_category' => 'rural',
                'valuation_method' => 'comparison'
            ],
            'vacant' => [
                'name' => 'Vacant Land',
                'description' => 'Empty land available for development',
                'rating_category' => 'vacant',
                'valuation_method' => 'comparison'
            ]
        ];
    }

    /**
     * Initialize rating categories
     */
    private function initializeRatingCategories(): void
    {
        $this->ratingCategories = [
            'residential' => [
                'base_rate' => 0.0035, // 0.35% of capital value
                'minimum_rate' => 500.00,
                'maximum_rate' => 15000.00
            ],
            'commercial' => [
                'base_rate' => 0.0055, // 0.55% of capital value
                'minimum_rate' => 1000.00,
                'maximum_rate' => 50000.00
            ],
            'industrial' => [
                'base_rate' => 0.0045, // 0.45% of capital value
                'minimum_rate' => 800.00,
                'maximum_rate' => 30000.00
            ],
            'rural' => [
                'base_rate' => 0.0025, // 0.25% of capital value
                'minimum_rate' => 300.00,
                'maximum_rate' => 8000.00
            ],
            'vacant' => [
                'base_rate' => 0.0015, // 0.15% of capital value
                'minimum_rate' => 200.00,
                'maximum_rate' => 5000.00
            ]
        ];
    }

    /**
     * Initialize valuation methods
     */
    private function initializeValuationMethods(): void
    {
        $this->valuationMethods = [
            'comparison' => [
                'name' => 'Sales Comparison Approach',
                'description' => 'Valuation based on comparable property sales',
                'factors' => ['location', 'size', 'condition', 'market_trends']
            ],
            'income' => [
                'name' => 'Income Capitalization Approach',
                'description' => 'Valuation based on income generation potential',
                'factors' => ['rental_income', 'operating_expenses', 'capitalization_rate']
            ],
            'cost' => [
                'name' => 'Cost Approach',
                'description' => 'Valuation based on replacement cost',
                'factors' => ['land_cost', 'construction_cost', 'depreciation']
            ]
        ];
    }

    /**
     * Search properties (API handler)
     */
    public function searchProperties(array $searchCriteria): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM properties WHERE 1=1";
            $params = [];

            // Build search conditions based on criteria
            if (isset($searchCriteria['property_id'])) {
                $sql .= " AND property_id = ?";
                $params[] = $searchCriteria['property_id'];
            }

            if (isset($searchCriteria['street_address'])) {
                $sql .= " AND street_address LIKE ?";
                $params[] = '%' . $searchCriteria['street_address'] . '%';
            }

            if (isset($searchCriteria['suburb'])) {
                $sql .= " AND suburb = ?";
                $params[] = $searchCriteria['suburb'];
            }

            if (isset($searchCriteria['owner_name'])) {
                $sql .= " AND owner_name LIKE ?";
                $params[] = '%' . $searchCriteria['owner_name'] . '%';
            }

            if (isset($searchCriteria['property_type'])) {
                $sql .= " AND property_type = ?";
                $params[] = $searchCriteria['property_type'];
            }

            if (isset($searchCriteria['valuation_min'])) {
                $sql .= " AND capital_value >= ?";
                $params[] = $searchCriteria['valuation_min'];
            }

            if (isset($searchCriteria['valuation_max'])) {
                $sql .= " AND capital_value <= ?";
                $params[] = $searchCriteria['valuation_max'];
            }

            $sql .= " ORDER BY street_address LIMIT 100";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['building_details'] = json_decode($result['building_details'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'search_criteria' => $searchCriteria
            ];
        } catch (\Exception $e) {
            error_log("Error searching properties: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to search properties'
            ];
        }
    }

    /**
     * Get property (API handler)
     */
    public function getProperty(string $propertyId): array
    {
        $property = $this->getPropertyById($propertyId);

        if (!$property) {
            return [
                'success' => false,
                'error' => 'Property not found'
            ];
        }

        return [
            'success' => true,
            'data' => $property
        ];
    }

    /**
     * Get property valuation (API handler)
     */
    public function getPropertyValuation(string $propertyId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM property_valuations WHERE property_id = ? ORDER BY valuation_date DESC LIMIT 1";
            $valuation = $db->fetch($sql, [$propertyId]);

            if (!$valuation) {
                return [
                    'success' => false,
                    'error' => 'No valuation found for this property'
                ];
            }

            // Decode JSON fields
            $valuation['comparable_sales'] = json_decode($valuation['comparable_sales'], true);
            $valuation['attachments'] = json_decode($valuation['attachments'], true);

            return [
                'success' => true,
                'data' => $valuation
            ];
        } catch (\Exception $e) {
            error_log("Error getting property valuation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve property valuation'
            ];
        }
    }

    /**
     * Get property rates (API handler)
     */
    public function getPropertyRates(string $propertyId, array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM property_rates WHERE property_id = ?";
            $params = [$propertyId];

            if (isset($filters['rating_year'])) {
                $sql .= " AND rating_year = ?";
                $params[] = $filters['rating_year'];
            }

            $sql .= " ORDER BY rating_year DESC, due_date DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting property rates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve property rates'
            ];
        }
    }

    /**
     * Generate property rates (API handler)
     */
    public function generatePropertyRates(string $propertyId, array $rateData): array
    {
        try {
            $property = $this->getPropertyById($propertyId);

            if (!$property) {
                return [
                    'success' => false,
                    'error' => 'Property not found'
                ];
            }

            // Calculate rates amount
            $ratesAmount = $this->calculateRatesAmount($property);

            // Create rates record
            $rates = [
                'property_id' => $property['id'],
                'rating_year' => $rateData['rating_year'] ?? $this->config['rating_year'],
                'rates_amount' => $ratesAmount,
                'due_date' => $this->calculateDueDate($rateData['rating_year'] ?? $this->config['rating_year']),
                'total_amount' => $ratesAmount,
                'instalment_plan' => $rateData['instalment_plan'] ?? false,
                'instalment_number' => $rateData['instalment_number'] ?? 1,
                'instalment_total' => $rateData['instalment_total'] ?? 1
            ];

            // Save to database
            $this->savePropertyRates($rates);

            // Send notification
            $this->sendNotification('rates_bill_generated', $property['owner_id'], [
                'property_address' => $property['street_address'],
                'rates_amount' => $ratesAmount,
                'due_date' => $rates['due_date']
            ]);

            return [
                'success' => true,
                'rates_id' => $this->getLastInsertId(),
                'rates_amount' => $ratesAmount,
                'due_date' => $rates['due_date'],
                'message' => 'Property rates generated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error generating property rates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate property rates'
            ];
        }
    }

    /**
     * Lodge rates objection (API handler)
     */
    public function lodgeRatesObjection(string $propertyId, array $objectionData): array
    {
        try {
            $property = $this->getPropertyById($propertyId);

            if (!$property) {
                return [
                    'success' => false,
                    'error' => 'Property not found'
                ];
            }

            // Generate objection number
            $objectionNumber = $this->generateObjectionNumber();

            // Create objection record
            $objection = [
                'objection_number' => $objectionNumber,
                'property_id' => $property['id'],
                'objector_id' => $objectionData['objector_id'],
                'objector_name' => $objectionData['objector_name'],
                'objector_address' => $objectionData['objector_address'],
                'objector_phone' => $objectionData['objector_phone'] ?? null,
                'objector_email' => $objectionData['objector_email'] ?? null,
                'rating_year' => $objectionData['rating_year'],
                'grounds_for_objection' => $objectionData['grounds_for_objection'],
                'requested_valuation' => $objectionData['requested_valuation'] ?? null,
                'evidence_provided' => $objectionData['evidence_provided'] ?? [],
                'lodged_date' => date('Y-m-d')
            ];

            // Save to database
            $this->saveRatesObjection($objection);

            // Start objection workflow
            $this->startObjectionWorkflow($objectionNumber);

            // Send notification
            $this->sendNotification('objection_lodged', $objectionData['objector_id'], [
                'property_address' => $property['street_address'],
                'objection_number' => $objectionNumber
            ]);

            return [
                'success' => true,
                'objection_number' => $objectionNumber,
                'message' => 'Rates objection lodged successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error lodging rates objection: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to lodge rates objection'
            ];
        }
    }

    /**
     * Get rates objections (API handler)
     */
    public function getRatesObjections(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM rates_objections WHERE 1=1";
            $params = [];

            if (isset($filters['property_id'])) {
                $sql .= " AND property_id = ?";
                $params[] = $filters['property_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['objector_id'])) {
                $sql .= " AND objector_id = ?";
                $params[] = $filters['objector_id'];
            }

            $sql .= " ORDER BY lodged_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['evidence_provided'] = json_decode($result['evidence_provided'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting rates objections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve rates objections'
            ];
        }
    }

    /**
     * Register property transfer (API handler)
     */
    public function registerPropertyTransfer(array $transferData): array
    {
        try {
            // Generate transfer number
            $transferNumber = $this->generateTransferNumber();

            // Calculate stamp duty
            $stampDuty = $this->calculateStampDuty($transferData['transfer_price'] ?? 0);

            // Create transfer record
            $transfer = [
                'transfer_number' => $transferNumber,
                'property_id' => $transferData['property_id'],
                'seller_id' => $transferData['seller_id'],
                'seller_name' => $transferData['seller_name'],
                'seller_address' => $transferData['seller_address'],
                'buyer_id' => $transferData['buyer_id'],
                'buyer_name' => $transferData['buyer_name'],
                'buyer_address' => $transferData['buyer_address'],
                'transfer_price' => $transferData['transfer_price'] ?? null,
                'transfer_date' => $transferData['transfer_date'],
                'settlement_date' => $transferData['settlement_date'] ?? null,
                'transfer_type' => $transferData['transfer_type'],
                'stamp_duty' => $stampDuty,
                'legal_documents' => $transferData['legal_documents'] ?? [],
                'registered_by' => $transferData['registered_by'] ?? 1
            ];

            // Save to database
            $this->savePropertyTransfer($transfer);

            // Start transfer workflow
            $this->startTransferWorkflow($transferNumber);

            // Send notifications
            if ($stampDuty > 0) {
                $this->sendNotification('stamp_duty_due', $transferData['buyer_id'], [
                    'transfer_number' => $transferNumber,
                    'stamp_duty' => $stampDuty
                ]);
            }

            $property = $this->getPropertyById($transferData['property_id']);
            $this->sendNotification('transfer_registered', $transferData['buyer_id'], [
                'property_address' => $property['street_address'],
                'transfer_number' => $transferNumber
            ]);

            return [
                'success' => true,
                'transfer_number' => $transferNumber,
                'stamp_duty' => $stampDuty,
                'message' => 'Property transfer registered successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error registering property transfer: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to register property transfer'
            ];
        }
    }

    /**
     * Get property transfers (API handler)
     */
    public function getPropertyTransfers(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM property_transfers WHERE 1=1";
            $params = [];

            if (isset($filters['property_id'])) {
                $sql .= " AND property_id = ?";
                $params[] = $filters['property_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['buyer_id'])) {
                $sql .= " AND buyer_id = ?";
                $params[] = $filters['buyer_id'];
            }

            if (isset($filters['seller_id'])) {
                $sql .= " AND seller_id = ?";
                $params[] = $filters['seller_id'];
            }

            $sql .= " ORDER BY transfer_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['legal_documents'] = json_decode($result['legal_documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting property transfers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve property transfers'
            ];
        }
    }

    /**
     * Create property valuation (API handler)
     */
    public function createPropertyValuation(string $propertyId, array $valuationData): array
    {
        try {
            $property = $this->getPropertyById($propertyId);

            if (!$property) {
                return [
                    'success' => false,
                    'error' => 'Property not found'
                ];
            }

            // Generate valuation number
            $valuationNumber = $this->generateValuationNumber();

            // Create valuation record
            $valuation = [
                'valuation_number' => $valuationNumber,
                'property_id' => $property['id'],
                'valuation_date' => date('Y-m-d'),
                'valuation_type' => $valuationData['valuation_type'] ?? 'annual',
                'valuer_id' => $valuationData['valuer_id'],
                'capital_value' => $valuationData['capital_value'],
                'land_value' => $valuationData['land_value'] ?? null,
                'improvement_value' => $valuationData['improvement_value'] ?? null,
                'valuation_method' => $valuationData['valuation_method'] ?? 'comparison',
                'comparable_sales' => $valuationData['comparable_sales'] ?? [],
                'valuation_report' => $valuationData['valuation_report'] ?? '',
                'attachments' => $valuationData['attachments'] ?? [],
                'effective_date' => $valuationData['effective_date'] ?? date('Y-m-d'),
                'status' => 'completed'
            ];

            // Save to database
            $this->savePropertyValuation($valuation);

            // Update property with new valuation
            $this->updateProperty($property['id'], [
                'capital_value' => $valuationData['capital_value'],
                'land_value' => $valuationData['land_value'],
                'improvement_value' => $valuationData['improvement_value'],
                'valuation_date' => date('Y-m-d')
            ]);

            // Send notification
            $this->sendNotification('property_valuation_updated', $property['owner_id'], [
                'property_address' => $property['street_address'],
                'capital_value' => $valuationData['capital_value']
            ]);

            return [
                'success' => true,
                'valuation_number' => $valuationNumber,
                'message' => 'Property valuation created successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error creating property valuation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create property valuation'
            ];
        }
    }

    /**
     * Get property documents (API handler)
     */
    public function getPropertyDocuments(string $propertyId, array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM property_documents WHERE property_id = ?";
            $params = [$propertyId];

            if (isset($filters['document_type'])) {
                $sql .= " AND document_type = ?";
                $params[] = $filters['document_type'];
            }

            if (isset($filters['access_level'])) {
                $sql .= " AND access_level = ?";
                $params[] = $filters['access_level'];
            }

            $sql .= " ORDER BY upload_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['tags'] = json_decode($result['tags'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting property documents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve property documents'
            ];
        }
    }

    /**
     * Upload property document (API handler)
     */
    public function uploadPropertyDocument(string $propertyId, array $documentData): array
    {
        try {
            $property = $this->getPropertyById($propertyId);

            if (!$property) {
                return [
                    'success' => false,
                    'error' => 'Property not found'
                ];
            }

            // Create document record
            $document = [
                'property_id' => $property['id'],
                'document_type' => $documentData['document_type'],
                'document_number' => $documentData['document_number'] ?? null,
                'document_name' => $documentData['document_name'],
                'file_path' => $documentData['file_path'],
                'file_size' => $documentData['file_size'] ?? null,
                'mime_type' => $documentData['mime_type'] ?? null,
                'uploaded_by' => $documentData['uploaded_by'],
                'document_date' => $documentData['document_date'] ?? null,
                'expiry_date' => $documentData['expiry_date'] ?? null,
                'is_public' => $documentData['is_public'] ?? false,
                'access_level' => $documentData['access_level'] ?? 'staff',
                'tags' => $documentData['tags'] ?? [],
                'notes' => $documentData['notes'] ?? ''
            ];

            // Save to database
            $this->savePropertyDocument($document);

            return [
                'success' => true,
                'document_id' => $this->getLastInsertId(),
                'message' => 'Property document uploaded successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error uploading property document: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to upload property document'
            ];
        }
    }

    /**
     * Update property (API handler)
     */
    public function updateProperty(string $propertyId, array $propertyData): array
    {
        try {
            $property = $this->getPropertyById($propertyId);

            if (!$property) {
                return [
                    'success' => false,
                    'error' => 'Property not found'
                ];
            }

            $this->updateProperty($property['id'], $propertyData);

            return [
                'success' => true,
                'message' => 'Property updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating property: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update property'
            ];
        }
    }

    /**
     * Validate property search criteria
     */
    private function validatePropertySearch(array $criteria): array
    {
        $errors = [];

        if (isset($criteria['valuation_min']) && isset($criteria['valuation_max'])) {
            if ($criteria['valuation_min'] > $criteria['valuation_max']) {
                $errors[] = "Minimum valuation cannot be greater than maximum valuation";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Calculate rates amount for property
     */
    private function calculateRatesAmount(array $property): float
    {
        $ratingCategory = $property['rating_category'] ?? 'residential';
        $capitalValue = $property['capital_value'] ?? 0;

        if (!isset($this->ratingCategories[$ratingCategory])) {
            $ratingCategory = 'residential';
        }

        $category = $this->ratingCategories[$ratingCategory];
        $ratesAmount = $capitalValue * $category['base_rate'];

        // Apply minimum and maximum limits
        $ratesAmount = max($ratesAmount, $category['minimum_rate']);
        $ratesAmount = min($ratesAmount, $category['maximum_rate']);

        return round($ratesAmount, 2);
    }

    /**
     * Calculate due date for rates
     */
    private function calculateDueDate(int $ratingYear): string
    {
        return date('Y-m-d', strtotime("{$ratingYear}-{$this->config['rates_due_month']}-{$this->config['rates_due_day']}"));
    }

    /**
     * Calculate stamp duty for property transfer
     */
    private function calculateStampDuty(float $transferPrice): float
    {
        if ($transferPrice <= $this->config['stamp_duty_threshold']) {
            return 0.00;
        }

        // Simplified stamp duty calculation (would be more complex in reality)
        $stampDuty = $transferPrice * 0.04; // 4% stamp duty

        return round($stampDuty, 2);
    }

    /**
     * Generate objection number
     */
    private function generateObjectionNumber(): string
    {
        return 'OBJ' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate transfer number
     */
    private function generateTransferNumber(): string
    {
        return 'TRF' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate valuation number
     */
    private function generateValuationNumber(): string
    {
        return 'VAL' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get property by ID
     */
    private function getPropertyById(string $propertyId): ?array
    {
        try {
            $db = Database::getInstance();
            $sql = "SELECT * FROM properties WHERE property_id = ? OR id = ?";
            $property = $db->fetch($sql, [$propertyId, $propertyId]);

            if ($property) {
                $property['building_details'] = json_decode($property['building_details'], true);
            }

            return $property;
        } catch (\Exception $e) {
            error_log("Error getting property by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update property
     */
    private function updateProperty(int $propertyId, array $data): bool
    {
        try {
            $db = Database::getInstance();

            // Handle JSON fields
            if (isset($data['building_details'])) {
                $data['building_details'] = json_encode($data['building_details']);
            }

            $db->update('properties', $data, ['id' => $propertyId]);
            return true;
        } catch (\Exception $e) {
            error_log("Error updating property: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start objection workflow
     */
    private function startObjectionWorkflow(string $objectionNumber): bool
    {
        // Implementation would start the workflow engine
        return true;
    }

    /**
     * Start transfer workflow
     */
    private function startTransferWorkflow(string $transferNumber): bool
    {
        // Implementation would start the workflow engine
        return true;
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, ?int $userId, array $data): bool
    {
        // Implementation would use the notification manager
        return true;
    }

    /**
     * Placeholder methods (would be implemented with actual database operations)
     */
    private function savePropertyRates(array $rates): bool { return true; }
    private function saveRatesObjection(array $objection): bool { return true; }
    private function savePropertyTransfer(array $transfer): bool { return true; }
    private function savePropertyValuation(array $valuation): bool { return true; }
    private function savePropertyDocument(array $document): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_properties' => 0, // Would query database
            'active_rates' => 0,
            'pending_objections' => 0,
            'transfers_this_year' => 0,
            'total_stamp_duty_collected' => 0.00,
            'average_property_value' => 0.00,
            'rates_collection_rate' => 0.00
        ];
    }
}

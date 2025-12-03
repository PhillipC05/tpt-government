<?php
/**
 * TPT Government Platform - Building Consents Module
 *
 * Comprehensive building consent and permitting system
 * supporting application submission, plan review, inspections, and compliance
 *
 * INTEGRATES:
 * - Managers: Application, Inspection, Certificate, Fee, Compliance
 * - Repositories: Data access layer with standardized patterns
 * - Validators: Input validation with business rules
 * - Workflow: State management and business process orchestration
 */

namespace Modules\BuildingConsents;

use Modules\ServiceModule;
use Core\Database;
use Core\Workflow\WorkflowManager;
use Core\Logging\StructuredLogger;
use Core\NotificationManager;
use Core\PaymentGateway;

// Import new components
use Modules\BuildingConsents\Managers\BuildingConsentApplicationManager;
use Modules\BuildingConsents\Managers\BuildingInspectionManager;
use Modules\BuildingConsents\Managers\BuildingCertificateManager;
use Modules\BuildingConsents\Managers\BuildingFeeManager;
use Modules\BuildingConsents\Managers\BuildingComplianceManager;

use Modules\BuildingConsents\Repositories\BuildingConsentRepository;
use Modules\BuildingConsents\Repositories\BuildingInspectionRepository;
use Modules\BuildingConsents\Repositories\BuildingCertificateRepository;
use Modules\BuildingConsents\Repositories\BuildingFeeRepository;
use Modules\BuildingConsents\Repositories\BuildingComplianceRepository;

use Modules\BuildingConsents\Validators\BuildingConsentValidator;
use Modules\BuildingConsents\Validators\BuildingInspectionValidator;
use Modules\BuildingConsents\Validators\BuildingCertificateValidator;
use Modules\BuildingConsents\Validators\BuildingFeeValidator;
    /**
     * Module permissions
     */
    protected array $permissions = [
        'building_consents.view' => 'View building consent applications',
        'building_consents.create' => 'Create new building consent applications',
        'building_consents.edit' => 'Edit building consent applications',
        'building_consents.review' => 'Review building consent applications',
        'building_consents.approve' => 'Approve building consent applications',
        'building_consents.reject' => 'Reject building consent applications',
        'building_consents.inspect' => 'Schedule and conduct inspections',
        'building_consents.certify' => 'Issue building certificates',
        'building_consents.compliance' => 'Monitor building compliance'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'building_consent_applications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'project_name' => 'VARCHAR(255) NOT NULL',
            'project_type' => "ENUM('new_construction','renovation','addition','demolition','pool','deck','fence','signage','other') NOT NULL",
            'property_address' => 'TEXT NOT NULL',
            'property_type' => "ENUM('residential','commercial','industrial','mixed_use') NOT NULL",
            'owner_id' => 'INT NOT NULL',
            'applicant_id' => 'INT NOT NULL',
            'architect_id' => 'INT NULL',
            'contractor_id' => 'INT NULL',
            'building_consent_type' => "ENUM('full','outline','discretionary','non-notified','limited') DEFAULT 'full'",
            'estimated_cost' => 'DECIMAL(12,2) NOT NULL',
            'floor_area' => 'DECIMAL(10,2) NULL',
            'storeys' => 'INT DEFAULT 1',
            'status' => "ENUM('draft','submitted','lodged','processing','awaiting_info','under_review','approved','rejected','withdrawn','expired') DEFAULT 'draft'",
            'lodgement_date' => 'DATETIME NULL',
            'decision_date' => 'DATETIME NULL',
            'expiry_date' => 'DATETIME NULL',
            'consent_number' => 'VARCHAR(20) NULL',
            'documents' => 'JSON',
            'requirements' => 'JSON',
            'conditions' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'building_plans' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) NOT NULL',
            'plan_type' => "ENUM('site_plan','floor_plan','elevation','section','detail','specification') NOT NULL",
            'plan_number' => 'VARCHAR(50) NOT NULL',
            'revision' => 'INT DEFAULT 1',
            'file_path' => 'VARCHAR(500) NOT NULL',
            'file_name' => 'VARCHAR(255) NOT NULL',
            'file_size' => 'INT NOT NULL',
            'uploaded_by' => 'INT NOT NULL',
            'upload_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'status' => "ENUM('pending','approved','rejected','revision_required') DEFAULT 'pending'",
            'reviewer_id' => 'INT NULL',
            'review_date' => 'DATETIME NULL',
            'review_notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'building_inspections' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) NOT NULL',
            'inspection_type' => "ENUM('foundation','frame','insulation','plumbing','electrical','final','special') NOT NULL",
            'scheduled_date' => 'DATETIME NOT NULL',
            'actual_date' => 'DATETIME NULL',
            'inspector_id' => 'INT NULL',
            'status' => "ENUM('scheduled','confirmed','in_progress','completed','cancelled','rescheduled') DEFAULT 'scheduled'",
            'result' => "ENUM('pass','fail','conditional','not_inspected') NULL",
            'findings' => 'JSON',
            'recommendations' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATETIME NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'building_certificates' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) NOT NULL',
            'certificate_type' => "ENUM('code_compliance','completion','occupancy','compliance_schedule') NOT NULL",
            'certificate_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'issue_date' => 'DATETIME NOT NULL',
            'expiry_date' => 'DATETIME NULL',
            'issued_by' => 'INT NOT NULL',
            'conditions' => 'JSON',
            'limitations' => 'TEXT',
            'status' => "ENUM('active','expired','revoked','superseded') DEFAULT 'active'",
            'revocation_reason' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'building_fees' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) NOT NULL',
            'fee_type' => "ENUM('lodgement','processing','inspection','certification','administration') NOT NULL",
            'amount' => 'DECIMAL(8,2) NOT NULL',
            'description' => 'VARCHAR(255) NOT NULL',
            'due_date' => 'DATE NOT NULL',
            'payment_date' => 'DATE NULL',
            'payment_method' => 'VARCHAR(50)',
            'status' => "ENUM('unpaid','paid','overdue','waived') DEFAULT 'unpaid'",
            'invoice_number' => 'VARCHAR(20)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'building_compliance' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) NOT NULL',
            'requirement_type' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'due_date' => 'DATETIME NOT NULL',
            'completion_date' => 'DATETIME NULL',
            'status' => "ENUM('pending','completed','overdue','waived','non_compliant') DEFAULT 'pending'",
            'evidence' => 'JSON',
            'notes' => 'TEXT',
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
            'path' => '/api/building-consents',
            'handler' => 'getBuildingConsents',
            'auth' => true,
            'permissions' => ['building_consents.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/building-consents',
            'handler' => 'createBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/building-consents/{id}',
            'handler' => 'getBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/building-consents/{id}',
            'handler' => 'updateBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.edit']
        ],
        [
            'method' => 'POST',
            'path' => '/api/building-consents/{id}/submit',
            'handler' => 'submitBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.create']
        ],
        [
            'method' => 'POST',
            'path' => '/api/building-consents/{id}/review',
            'handler' => 'reviewBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.review']
        ],
        [
            'method' => 'POST',
            'path' => '/api/building-consents/{id}/approve',
            'handler' => 'approveBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.approve']
        ],
        [
            'method' => 'POST',
            'path' => '/api/building-consents/{id}/reject',
            'handler' => 'rejectBuildingConsent',
            'auth' => true,
            'permissions' => ['building_consents.reject']
        ],
        [
            'method' => 'GET',
            'path' => '/api/building-inspections',
            'handler' => 'getInspections',
            'auth' => true,
            'permissions' => ['building_consents.inspect']
        ],
        [
            'method' => 'POST',
            'path' => '/api/building-inspections',
            'handler' => 'scheduleInspection',
            'auth' => true,
            'permissions' => ['building_consents.inspect']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'building_consent_process' => [
            'name' => 'Building Consent Application Process',
            'description' => 'Complete workflow for building consent applications',
            'steps' => [
                'draft' => ['name' => 'Application Draft', 'next' => 'submitted'],
                'submitted' => ['name' => 'Application Submitted', 'next' => 'lodged'],
                'lodged' => ['name' => 'Application Lodged', 'next' => 'processing'],
                'processing' => ['name' => 'Processing', 'next' => ['awaiting_info', 'under_review']],
                'awaiting_info' => ['name' => 'Awaiting Additional Information', 'next' => 'processing'],
                'under_review' => ['name' => 'Under Review', 'next' => ['approved', 'rejected']],
                'approved' => ['name' => 'Consent Approved', 'next' => 'issued'],
                'issued' => ['name' => 'Consent Issued', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null],
                'withdrawn' => ['name' => 'Application Withdrawn', 'next' => null],
                'expired' => ['name' => 'Application Expired', 'next' => null]
            ]
        ],
        'inspection_process' => [
            'name' => 'Building Inspection Process',
            'description' => 'Workflow for building inspections',
            'steps' => [
                'scheduled' => ['name' => 'Inspection Scheduled', 'next' => 'confirmed'],
                'confirmed' => ['name' => 'Inspection Confirmed', 'next' => 'in_progress'],
                'in_progress' => ['name' => 'Inspection In Progress', 'next' => 'completed'],
                'completed' => ['name' => 'Inspection Completed', 'next' => null],
                'cancelled' => ['name' => 'Inspection Cancelled', 'next' => null],
                'rescheduled' => ['name' => 'Inspection Rescheduled', 'next' => 'scheduled']
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'building_consent_application' => [
            'name' => 'Building Consent Application',
            'fields' => [
                'project_name' => ['type' => 'text', 'required' => true, 'label' => 'Project Name'],
                'project_type' => ['type' => 'select', 'required' => true, 'label' => 'Project Type'],
                'property_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Property Address'],
                'property_type' => ['type' => 'select', 'required' => true, 'label' => 'Property Type'],
                'building_consent_type' => ['type' => 'select', 'required' => true, 'label' => 'Consent Type'],
                'estimated_cost' => ['type' => 'number', 'required' => true, 'label' => 'Estimated Cost', 'step' => '0.01'],
                'floor_area' => ['type' => 'number', 'required' => false, 'label' => 'Floor Area (m²)', 'step' => '0.01'],
                'storeys' => ['type' => 'number', 'required' => true, 'label' => 'Number of Storeys'],
                'architect_name' => ['type' => 'text', 'required' => false, 'label' => 'Architect Name'],
                'contractor_name' => ['type' => 'text', 'required' => false, 'label' => 'Contractor Name'],
                'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Project Description']
            ],
            'documents' => [
                'site_plan' => ['required' => true, 'label' => 'Site Plan'],
                'floor_plans' => ['required' => true, 'label' => 'Floor Plans'],
                'elevations' => ['required' => true, 'label' => 'Elevations'],
                'specifications' => ['required' => true, 'label' => 'Building Specifications'],
                'engineer_reports' => ['required' => false, 'label' => 'Engineer Reports'],
                'resource_consent' => ['required' => false, 'label' => 'Resource Consent']
            ]
        ],
        'inspection_request' => [
            'name' => 'Building Inspection Request',
            'fields' => [
                'application_id' => ['type' => 'hidden', 'required' => true],
                'inspection_type' => ['type' => 'select', 'required' => true, 'label' => 'Inspection Type'],
                'preferred_date' => ['type' => 'date', 'required' => true, 'label' => 'Preferred Date'],
                'preferred_time' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Time'],
                'contact_person' => ['type' => 'text', 'required' => true, 'label' => 'Contact Person'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'special_requirements' => ['type' => 'textarea', 'required' => false, 'label' => 'Special Requirements']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'consent_overview' => [
            'name' => 'Building Consent Overview Report',
            'description' => 'Summary of building consent applications and status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'consent_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'application_id', 'project_name', 'consent_type', 'status',
                'lodgement_date', 'decision_date', 'estimated_cost'
            ]
        ],
        'inspection_summary' => [
            'name' => 'Building Inspection Summary Report',
            'description' => 'Summary of building inspections and results',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'inspection_type' => ['type' => 'select', 'required' => false],
                'result' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'application_id', 'inspection_type', 'scheduled_date',
                'actual_date', 'result', 'inspector_name'
            ]
        ],
        'compliance_report' => [
            'name' => 'Building Compliance Report',
            'description' => 'Compliance status and requirements tracking',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'compliance_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'application_id', 'requirement_type', 'due_date',
                'status', 'completion_date', 'notes'
            ]
        ],
        'revenue_report' => [
            'name' => 'Building Consent Revenue Report',
            'description' => 'Revenue generated from building consent fees',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'fee_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'fee_type', 'total_applications', 'total_revenue',
                'average_fee', 'monthly_trend', 'collection_rate'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'application_submitted' => [
            'name' => 'Building Consent Application Submitted',
            'template' => 'Your building consent application {application_id} has been submitted successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_created']
        ],
        'application_lodged' => [
            'name' => 'Building Consent Application Lodged',
            'template' => 'Your building consent application {application_id} has been lodged and is being processed.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_lodged']
        ],
        'additional_info_required' => [
            'name' => 'Additional Information Required',
            'template' => 'Additional information is required for your building consent application {application_id}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['additional_info_required']
        ],
        'consent_approved' => [
            'name' => 'Building Consent Approved',
            'template' => 'Congratulations! Your building consent application {application_id} has been approved.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['consent_approved']
        ],
        'consent_rejected' => [
            'name' => 'Building Consent Rejected',
            'template' => 'Your building consent application {application_id} has been rejected.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['consent_rejected']
        ],
        'inspection_scheduled' => [
            'name' => 'Building Inspection Scheduled',
            'template' => 'A building inspection has been scheduled for {scheduled_date} at your property.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['inspection_scheduled']
        ],
        'inspection_completed' => [
            'name' => 'Building Inspection Completed',
            'template' => 'Your building inspection has been completed. Result: {result}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['inspection_completed']
        ],
        'certificate_issued' => [
            'name' => 'Building Certificate Issued',
            'template' => 'Your building certificate {certificate_number} has been issued.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['certificate_issued']
        ]
    ];

    /**
     * Building consent types and requirements
     */
    private array $consentTypes = [];

    /**
     * Inspection types and requirements
     */
    private array $inspectionTypes = [];

    /**
     * Fee structures
     */
    private array $feeStructures = [];

    /**
     * Compliance requirements
     */
    private array $complianceRequirements = [];

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
            'consent_processing_days' => 20,
            'inspection_lead_time_days' => 5,
            'consent_validity_years' => 1,
            'max_upload_size' => 52428800, // 50MB
            'allowed_file_types' => ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png'],
            'auto_approval_threshold' => 50000, // $50,000
            'fee_calculation' => 'tiered', // fixed, percentage, tiered
            'payment_gateway' => 'stripe',
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
        $this->initializeConsentTypes();
        $this->initializeInspectionTypes();
        $this->initializeFeeStructures();
        $this->initializeComplianceRequirements();
    }

    /**
     * Initialize building consent types
     */
    private function initializeConsentTypes(): void
    {
        $this->consentTypes = [
            'full' => [
                'name' => 'Full Building Consent',
                'description' => 'Complete building consent for new construction or major alterations',
                'processing_time_days' => 20,
                'requirements' => [
                    'site_plan', 'floor_plans', 'elevations', 'specifications',
                    'engineer_reports', 'resource_consent'
                ],
                'inspections_required' => ['foundation', 'frame', 'insulation', 'plumbing', 'electrical', 'final']
            ],
            'outline' => [
                'name' => 'Outline Building Consent',
                'description' => 'Preliminary consent for concept approval',
                'processing_time_days' => 10,
                'requirements' => [
                    'site_plan', 'concept_plans', 'specifications'
                ],
                'inspections_required' => []
            ],
            'discretionary' => [
                'name' => 'Discretionary Building Consent',
                'description' => 'Consent requiring special consideration',
                'processing_time_days' => 30,
                'requirements' => [
                    'site_plan', 'floor_plans', 'elevations', 'specifications',
                    'impact_assessment', 'public_notification'
                ],
                'inspections_required' => ['foundation', 'frame', 'final']
            ],
            'non-notified' => [
                'name' => 'Non-notified Building Consent',
                'description' => 'Consent with limited public notification',
                'processing_time_days' => 15,
                'requirements' => [
                    'site_plan', 'floor_plans', 'elevations', 'specifications'
                ],
                'inspections_required' => ['foundation', 'final']
            ],
            'limited' => [
                'name' => 'Limited Building Consent',
                'description' => 'Consent for minor works',
                'processing_time_days' => 5,
                'requirements' => [
                    'site_plan', 'plans', 'specifications'
                ],
                'inspections_required' => ['final']
            ]
        ];
    }

    /**
     * Initialize inspection types
     */
    private function initializeInspectionTypes(): void
    {
        $this->inspectionTypes = [
            'foundation' => [
                'name' => 'Foundation Inspection',
                'description' => 'Inspection of foundation and footings',
                'required_documents' => ['foundation_plans', 'engineer_certification'],
                'estimated_duration' => 60, // minutes
                'checklist' => [
                    'Foundation depth meets specifications',
                    'Reinforcement properly installed',
                    'Concrete quality and curing',
                    'Setback requirements met'
                ]
            ],
            'frame' => [
                'name' => 'Frame Inspection',
                'description' => 'Inspection of structural framing',
                'required_documents' => ['framing_plans', 'engineer_certification'],
                'estimated_duration' => 90,
                'checklist' => [
                    'Framing meets structural requirements',
                    'Connections properly secured',
                    'Load-bearing elements correct',
                    'Temporary bracing in place'
                ]
            ],
            'insulation' => [
                'name' => 'Insulation Inspection',
                'description' => 'Inspection of thermal insulation',
                'required_documents' => ['insulation_specifications'],
                'estimated_duration' => 45,
                'checklist' => [
                    'Insulation R-value meets requirements',
                    'Vapor barrier properly installed',
                    'Insulation continuous and complete',
                    'Penetrations sealed'
                ]
            ],
            'plumbing' => [
                'name' => 'Plumbing Inspection',
                'description' => 'Inspection of plumbing systems',
                'required_documents' => ['plumbing_plans'],
                'estimated_duration' => 60,
                'checklist' => [
                    'Pipe sizing correct',
                    'Fixtures properly installed',
                    'Pressure testing completed',
                    'Backflow prevention installed'
                ]
            ],
            'electrical' => [
                'name' => 'Electrical Inspection',
                'description' => 'Inspection of electrical systems',
                'required_documents' => ['electrical_plans'],
                'estimated_duration' => 75,
                'checklist' => [
                    'Wiring meets code requirements',
                    'Grounding properly installed',
                    'GFCI protection where required',
                    'Panel capacity adequate'
                ]
            ],
            'final' => [
                'name' => 'Final Inspection',
                'description' => 'Final inspection before occupancy',
                'required_documents' => ['certificate_of_compliance'],
                'estimated_duration' => 120,
                'checklist' => [
                    'All required inspections completed',
                    'Building meets all code requirements',
                    'Safety features operational',
                    'Documentation complete'
                ]
            ]
        ];
    }

    /**
     * Initialize fee structures
     */
    private function initializeFeeStructures(): void
    {
        $this->feeStructures = [
            'lodgement_fee' => [
                'type' => 'fixed',
                'amount' => 500.00,
                'description' => 'Application lodgement fee'
            ],
            'processing_fee' => [
                'type' => 'percentage',
                'percentage' => 0.003, // 0.3%
                'min_amount' => 200.00,
                'max_amount' => 5000.00,
                'description' => 'Processing fee based on project cost'
            ],
            'inspection_fee' => [
                'type' => 'fixed',
                'amount' => 150.00,
                'description' => 'Fee per inspection'
            ],
            'certification_fee' => [
                'type' => 'fixed',
                'amount' => 300.00,
                'description' => 'Certificate issuance fee'
            ]
        ];
    }

    /**
     * Initialize compliance requirements
     */
    private function initializeComplianceRequirements(): void
    {
        $this->complianceRequirements = [
            'building_code' => [
                'name' => 'Building Code Compliance',
                'description' => 'Compliance with building code requirements',
                'frequency' => 'ongoing',
                'verification_method' => 'inspection'
            ],
            'resource_consent' => [
                'name' => 'Resource Consent Compliance',
                'description' => 'Compliance with resource consent conditions',
                'frequency' => 'ongoing',
                'verification_method' => 'documentation'
            ],
            'engineer_certification' => [
                'name' => 'Engineer Certification',
                'description' => 'Structural engineer certification',
                'frequency' => 'one_time',
                'verification_method' => 'documentation'
            ],
            'insurance' => [
                'name' => 'Construction Insurance',
                'description' => 'Required construction insurance coverage',
                'frequency' => 'ongoing',
                'verification_method' => 'documentation'
            ]
        ];
    }

    /**
     * Create building consent application
     */
    public function createBuildingConsentApplication(array $applicationData): array
    {
        // Validate application data
        $validation = $this->validateApplicationData($applicationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate application ID
        $applicationId = $this->generateApplicationId();

        // Get consent type details
        $consentType = $this->consentTypes[$applicationData['building_consent_type']];

        // Calculate processing deadline
        $lodgementDate = date('Y-m-d H:i:s');
        $processingDeadline = date('Y-m-d H:i:s', strtotime("+{$consentType['processing_time_days']} days"));

        // Calculate fees
        $fees = $this->calculateApplicationFees($applicationData, $consentType);

        // Create application record
        $application = [
            'application_id' => $applicationId,
            'project_name' => $applicationData['project_name'],
            'project_type' => $applicationData['project_type'],
            'property_address' => $applicationData['property_address'],
            'property_type' => $applicationData['property_type'],
            'owner_id' => $applicationData['owner_id'],
            'applicant_id' => $applicationData['applicant_id'],
            'architect_id' => $applicationData['architect_id'] ?? null,
            'contractor_id' => $applicationData['contractor_id'] ?? null,
            'building_consent_type' => $applicationData['building_consent_type'],
            'estimated_cost' => $applicationData['estimated_cost'],
            'floor_area' => $applicationData['floor_area'] ?? null,
            'storeys' => $applicationData['storeys'],
            'status' => 'draft',
            'lodgement_date' => $lodgementDate,
            'documents' => $applicationData['documents'] ?? [],
            'requirements' => $consentType['requirements'],
            'notes' => $applicationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveBuildingConsentApplication($application);

        // Create fee records
        $this->createApplicationFees($applicationId, $fees);

        return [
            'success' => true,
            'application_id' => $applicationId,
            'consent_type' => $consentType['name'],
            'processing_deadline' => $processingDeadline,
            'total_fees' => array_sum(array_column($fees, 'amount')),
            'requirements' => $consentType['requirements']
        ];
    }

    /**
     * Submit building consent application
     */
    public function submitBuildingConsentApplication(string $applicationId): array
    {
        $application = $this->getBuildingConsentApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        if ($application['status'] !== 'draft') {
            return [
                'success' => false,
                'error' => 'Application already submitted'
            ];
        }

        // Validate all requirements are met
        $validation = $this->validateApplicationRequirements($application);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Update application status
        $this->updateApplicationStatus($applicationId, 'submitted');

        // Set lodgement date
        $this->updateApplicationLodgementDate($applicationId, date('Y-m-d H:i:s'));

        // Start workflow
        $this->startConsentWorkflow($applicationId);

        // Send notification
        $this->sendNotification('application_submitted', $application['applicant_id'], [
            'application_id' => $applicationId
        ]);

        return [
            'success' => true,
            'message' => 'Application submitted successfully',
            'lodgement_date' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Review building consent application
     */
    public function reviewBuildingConsentApplication(string $applicationId, array $reviewData): array
    {
        $application = $this->getBuildingConsentApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Update application status
        $this->updateApplicationStatus($applicationId, 'under_review');

        // Log review notes
        $this->addApplicationNote($applicationId, 'Review started: ' . ($reviewData['notes'] ?? ''));

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'under_review');

        return [
            'success' => true,
            'message' => 'Application moved to review'
        ];
    }

    /**
     * Approve building consent application
     */
    public function approveBuildingConsentApplication(string $applicationId, array $approvalData = []): array
    {
        $application = $this->getBuildingConsentApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Generate consent number
        $consentNumber = $this->generateConsentNumber();

        // Calculate expiry date
        $expiryDate = date('Y-m-d H:i:s', strtotime("+{$this->config['consent_validity_years']} years"));

        // Update application
        $this->updateApplication($applicationId, [
            'status' => 'approved',
            'decision_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $expiryDate,
            'consent_number' => $consentNumber,
            'conditions' => $approvalData['conditions'] ?? [],
            'notes' => $approvalData['notes'] ?? ''
        ]);

        // Create compliance requirements
        $this->createComplianceRequirements($applicationId, $application['building_consent_type']);

        // Schedule required inspections
        $this->scheduleRequiredInspections($applicationId, $application['building_consent_type']);

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'approved');

        // Send notification
        $this->sendNotification('consent_approved', $application['applicant_id'], [
            'application_id' => $applicationId,
            'consent_number' => $consentNumber,
            'expiry_date' => $expiryDate
        ]);

        return [
            'success' => true,
            'consent_number' => $consentNumber,
            'expiry_date' => $expiryDate,
            'message' => 'Building consent approved'
        ];
    }

    /**
     * Reject building consent application
     */
    public function rejectBuildingConsentApplication(string $applicationId, string $reason): array
    {
        $application = $this->getBuildingConsentApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Update application
        $this->updateApplication($applicationId, [
            'status' => 'rejected',
            'decision_date' => date('Y-m-d H:i:s'),
            'notes' => $reason
        ]);

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'rejected');

        // Send notification
        $this->sendNotification('consent_rejected', $application['applicant_id'], [
            'application_id' => $applicationId,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => 'Building consent rejected'
        ];
    }

    /**
     * Schedule building inspection
     */
    public function scheduleBuildingInspection(array $inspectionData): array
    {
        // Validate inspection data
        $validation = $this->validateInspectionData($inspectionData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check if inspection type is required for this application
        $application = $this->getBuildingConsentApplication($inspectionData['application_id']);
        $consentType = $this->consentTypes[$application['building_consent_type']];

        if (!in_array($inspectionData['inspection_type'], $consentType['inspections_required'])) {
            return [
                'success' => false,
                'error' => 'Inspection type not required for this consent type'
            ];
        }

        // Create inspection record
        $inspection = [
            'application_id' => $inspectionData['application_id'],
            'inspection_type' => $inspectionData['inspection_type'],
            'scheduled_date' => $inspectionData['preferred_date'] . ' ' . $inspectionData['preferred_time'],
            'status' => 'scheduled',
            'notes' => $inspectionData['special_requirements'] ?? ''
        ];

        // Save to database
        $this->saveBuildingInspection($inspection);

        // Send notification
        $this->sendNotification('inspection_scheduled', $application['applicant_id'], [
            'application_id' => $inspectionData['application_id'],
            'scheduled_date' => $inspection['scheduled_date']
        ]);

        return [
            'success' => true,
            'message' => 'Building inspection scheduled',
            'scheduled_date' => $inspection['scheduled_date']
        ];
    }

    /**
     * Complete building inspection
     */
    public function completeBuildingInspection(int $inspectionId, array $inspectionResult): array
    {
        $inspection = $this->getBuildingInspection($inspectionId);
        if (!$inspection) {
            return [
                'success' => false,
                'error' => 'Inspection not found'
            ];
        }

        // Update inspection
        $this->updateInspection($inspectionId, [
            'status' => 'completed',
            'actual_date' => date('Y-m-d H:i:s'),
            'result' => $inspectionResult['result'],
            'findings' => $inspectionResult['findings'] ?? [],
            'recommendations' => $inspectionResult['recommendations'] ?? '',
            'follow_up_required' => $inspectionResult['follow_up_required'] ?? false,
            'notes' => $inspectionResult['notes'] ?? ''
        ]);

        // If follow-up required, schedule it
        if ($inspectionResult['follow_up_required']) {
            $this->scheduleFollowUpInspection($inspectionId, $inspectionResult['follow_up_date'] ?? null);
        }

        // Send notification
        $application = $this->getBuildingConsentApplication($inspection['application_id']);
        $this->sendNotification('inspection_completed', $application['applicant_id'], [
            'application_id' => $inspection['application_id'],
            'result' => $inspectionResult['result']
        ]);

        return [
            'success' => true,
            'result' => $inspectionResult['result'],
            'message' => 'Building inspection completed'
        ];
    }

    /**
     * Issue building certificate
     */
    public function issueBuildingCertificate(string $applicationId, string $certificateType, array $certificateData = []): array
    {
        $application = $this->getBuildingConsentApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        if ($application['status'] !== 'approved') {
            return [
                'success' => false,
                'error' => 'Application must be approved before certificate can be issued'
            ];
        }

        // Generate certificate number
        $certificateNumber = $this->generateCertificateNumber($certificateType);

        // Calculate expiry date (if applicable)
        $expiryDate = null;
        if ($certificateType === 'code_compliance') {
            $expiryDate = date('Y-m-d H:i:s', strtotime('+10 years'));
        }

        // Create certificate record
        $certificate = [
            'application_id' => $applicationId,
            'certificate_type' => $certificateType,
            'certificate_number' => $certificateNumber,
            'issue_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $expiryDate,
            'issued_by' => $certificateData['issued_by'] ?? null,
            'conditions' => $certificateData['conditions'] ?? [],
            'limitations' => $certificateData['limitations'] ?? '',
            'status' => 'active'
        ];

        // Save to database
        $this->saveBuildingCertificate($certificate);

        // Send notification
        $this->sendNotification('certificate_issued', $application['applicant_id'], [
            'application_id' => $applicationId,
            'certificate_number' => $certificateNumber
        ]);

        return [
            'success' => true,
            'certificate_number' => $certificateNumber,
            'certificate_type' => $certificateType,
            'issue_date' => $certificate['issue_date'],
            'expiry_date' => $expiryDate,
            'message' => 'Building certificate issued'
        ];
    }

    /**
     * Process fee payment
     */
    public function processFeePayment(string $invoiceNumber, array $paymentData): array
    {
        $fee = $this->getBuildingFee($invoiceNumber);
        if (!$fee) {
            return [
                'success' => false,
                'error' => 'Invoice not found'
            ];
        }

        if ($fee['status'] === 'paid') {
            return [
                'success' => false,
                'error' => 'Invoice already paid'
            ];
        }

        // Process payment
        $paymentGateway = new PaymentGateway();
        $paymentResult = $paymentGateway->processPayment([
            'amount' => $fee['amount'],
            'currency' => 'USD',
            'method' => $paymentData['method'],
            'description' => "Building Consent Fee - {$fee['fee_type']}",
            'metadata' => [
                'invoice_number' => $invoiceNumber,
                'application_id' => $fee['application_id']
            ]
        ]);

        if (!$paymentResult['success']) {
            return [
                'success' => false,
                'error' => 'Payment processing failed'
            ];
        }

        // Update fee status
        $this->updateFeeStatus($invoiceNumber, 'paid', date('Y-m-d'), $paymentData['method']);

        return [
            'success' => true,
            'transaction_id' => $paymentResult['transaction_id'],
            'message' => 'Fee payment processed successfully'
        ];
    }

    /**
     * Generate building consent report
     */
    public function generateBuildingConsentReport(array $filters = []): array
    {
        $query = "SELECT * FROM building_consent_applications WHERE 1=1";

        if (isset($filters['status'])) {
            $query .= " AND status = '{$filters['status']}'";
        }

        if (isset($filters['consent_type'])) {
            $query .= " AND building_consent_type = '{$filters['consent_type']}'";
        }

        if (isset($filters['date_from'])) {
            $query .= " AND lodgement_date >= '{$filters['date_from']}'";
        }

        if (isset($filters['date_to'])) {
            $query .= " AND lodgement_date <= '{$filters['date_to']}'";
        }

        // Execute query and return results
        return [
            'filters' => $filters,
            'data' => [], // Would contain actual query results
            'generated_at' => date('c')
        ];
    }

    /**
     * Validate application data
     */
    private function validateApplicationData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'project_name', 'project_type', 'property_address',
            'property_type', 'building_consent_type', 'estimated_cost', 'storeys'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate building consent type
        if (!isset($this->consentTypes[$data['building_consent_type'] ?? ''])) {
            $errors[] = "Invalid building consent type";
        }

        // Validate estimated cost
        if (isset($data['estimated_cost']) && (!is_numeric($data['estimated_cost']) || $data['estimated_cost'] <= 0)) {
            $errors[] = "Estimated cost must be a positive number";
        }

        // Validate floor area if provided
        if (isset($data['floor_area']) && (!is_numeric($data['floor_area']) || $data['floor_area'] <= 0)) {
            $errors[] = "Floor area must be a positive number";
        }

        // Validate storeys
        if (isset($data['storeys']) && (!is_numeric($data['storeys']) || $data['storeys'] < 1)) {
            $errors[] = "Storeys must be at least 1";
        }

        // Validate project type
        $validProjectTypes = ['new_construction', 'renovation', 'addition', 'demolition', 'pool', 'deck', 'fence', 'signage', 'other'];
        if (!in_array($data['project_type'] ?? '', $validProjectTypes)) {
            $errors[] = "Invalid project type";
        }

        // Validate property type
        $validPropertyTypes = ['residential', 'commercial', 'industrial', 'mixed_use'];
        if (!in_array($data['property_type'] ?? '', $validPropertyTypes)) {
            $errors[] = "Invalid property type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get building consents (API handler)
     */
    public function getBuildingConsents(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM building_consent_applications WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['consent_type'])) {
                $sql .= " AND building_consent_type = ?";
                $params[] = $filters['consent_type'];
            }

            if (isset($filters['owner_id'])) {
                $sql .= " AND owner_id = ?";
                $params[] = $filters['owner_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['documents'] = json_decode($result['documents'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting building consents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve building consents'
            ];
        }
    }

    /**
     * Get building consent (API handler)
     */
    public function getBuildingConsent(string $applicationId): array
    {
        $application = $this->getBuildingConsentApplication($applicationId);

        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        return [
            'success' => true,
            'data' => $application
        ];
    }

    /**
     * Create building consent (API handler)
     */
    public function createBuildingConsent(array $data): array
    {
        return $this->createBuildingConsentApplication($data);
    }

    /**
     * Update building consent (API handler)
     */
    public function updateBuildingConsent(string $applicationId, array $data): array
    {
        try {
            $application = $this->getBuildingConsentApplication($applicationId);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            if ($application['status'] !== 'draft') {
                return [
                    'success' => false,
                    'error' => 'Application cannot be modified'
                ];
            }

            $this->updateApplication($applicationId, $data);

            return [
                'success' => true,
                'message' => 'Application updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating building consent: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update application'
            ];
        }
    }

    /**
     * Submit building consent (API handler)
     */
    public function submitBuildingConsent(string $applicationId): array
    {
        return $this->submitBuildingConsentApplication($applicationId);
    }

    /**
     * Review building consent (API handler)
     */
    public function reviewBuildingConsent(string $applicationId, array $reviewData): array
    {
        return $this->reviewBuildingConsentApplication($applicationId, $reviewData);
    }

    /**
     * Approve building consent (API handler)
     */
    public function approveBuildingConsent(string $applicationId, array $approvalData): array
    {
        return $this->approveBuildingConsentApplication($applicationId, $approvalData);
    }

    /**
     * Reject building consent (API handler)
     */
    public function rejectBuildingConsent(string $applicationId, string $reason): array
    {
        return $this->rejectBuildingConsentApplication($applicationId, $reason);
    }

    /**
     * Get inspections (API handler)
     */
    public function getInspections(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM building_inspections WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['inspection_type'])) {
                $sql .= " AND inspection_type = ?";
                $params[] = $filters['inspection_type'];
            }

            $sql .= " ORDER BY scheduled_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting inspections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve inspections'
            ];
        }
    }

    /**
     * Schedule inspection (API handler)
     */
    public function scheduleInspection(array $inspectionData): array
    {
        return $this->scheduleBuildingInspection($inspectionData);
    }

    /**
     * Generate application ID
     */
    private function generateApplicationId(): string
    {
        return 'BC' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate application fees
     */
    private function calculateApplicationFees(array $applicationData, array $consentType): array
    {
        $fees = [];

        // Lodgement fee
        $fees[] = [
            'fee_type' => 'lodgement',
            'amount' => $this->feeStructures['lodgement_fee']['amount'],
            'description' => $this->feeStructures['lodgement_fee']['description'],
            'due_date' => date('Y-m-d', strtotime('+30 days'))
        ];

        // Processing fee (percentage based on estimated cost)
        $processingFee = $applicationData['estimated_cost'] * $this->feeStructures['processing_fee']['percentage'];
        $processingFee = max($processingFee, $this->feeStructures['processing_fee']['min_amount']);
        $processingFee = min($processingFee, $this->feeStructures['processing_fee']['max_amount']);

        $fees[] = [
            'fee_type' => 'processing',
            'amount' => $processingFee,
            'description' => $this->feeStructures['processing_fee']['description'],
            'due_date' => date('Y-m-d', strtotime('+30 days'))
        ];

        // Inspection fees based on required inspections
        foreach ($consentType['inspections_required'] as $inspectionType) {
            $fees[] = [
                'fee_type' => 'inspection',
                'amount' => $this->feeStructures['inspection_fee']['amount'],
                'description' => "Inspection fee for {$inspectionType}",
                'due_date' => date('Y-m-d', strtotime('+60 days'))
            ];
        }

        return $fees;
    }

    /**
     * Save building consent application
     */
    private function saveBuildingConsentApplication(array $application): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO building_consent_applications (
                application_id, project_name, project_type, property_address,
                property_type, owner_id, applicant_id, architect_id, contractor_id,
                building_consent_type, estimated_cost, floor_area, storeys,
                status, documents, requirements, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $application['application_id'],
                $application['project_name'],
                $application['project_type'],
                $application['property_address'],
                $application['property_type'],
                $application['owner_id'],
                $application['applicant_id'],
                $application['architect_id'],
                $application['contractor_id'],
                $application['building_consent_type'],
                $application['estimated_cost'],
                $application['floor_area'],
                $application['storeys'],
                $application['status'],
                json_encode($application['documents']),
                json_encode($application['requirements']),
                $application['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving building consent application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get building consent application
     */
    private function getBuildingConsentApplication(string $applicationId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM building_consent_applications WHERE application_id = ?";
            $result = $db->fetch($sql, [$applicationId]);

            if ($result) {
                $result['documents'] = json_decode($result['documents'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
                $result['conditions'] = json_decode($result['conditions'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting building consent application: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update application status
     */
    private function updateApplicationStatus(string $applicationId, string $status): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE building_consent_applications SET status = ? WHERE application_id = ?";
            return $db->execute($sql, [$status, $applicationId]);
        } catch (\Exception $e) {
            error_log("Error updating application status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update application lodgement date
     */
    private function updateApplicationLodgementDate(string $applicationId, string $date): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE building_consent_applications SET lodgement_date = ? WHERE application_id = ?";
            return $db->execute($sql, [$date, $applicationId]);
        } catch (\Exception $e) {
            error_log("Error updating application lodgement date: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update application
     */
    private function updateApplication(string $applicationId, array $data): bool
    {
        try {
            $db = Database::getInstance();

            $setParts = [];
            $params = [];

            foreach ($data as $field => $value) {
                if (is_array($value)) {
                    $setParts[] = "{$field} = ?";
                    $params[] = json_encode($value);
                } else {
                    $setParts[] = "{$field} = ?";
                    $params[] = $value;
                }
            }

            $params[] = $applicationId;

            $sql = "UPDATE building_consent_applications SET " . implode(', ', $setParts) . " WHERE application_id = ?";
            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error updating application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add application note
     */
    private function addApplicationNote(string $applicationId, string $note): bool
    {
        try {
            $db = Database::getInstance();

            $currentNotes = $this->getApplicationNotes($applicationId);
            $newNote = date('Y-m-d H:i:s') . ": " . $note;
            $updatedNotes = $currentNotes ? $currentNotes . "\n" . $newNote : $newNote;

            $sql = "UPDATE building_consent_applications SET notes = ? WHERE application_id = ?";
            return $db->execute($sql, [$updatedNotes, $applicationId]);
        } catch (\Exception $e) {
            error_log("Error adding application note: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get application notes
     */
    private function getApplicationNotes(string $applicationId): ?string
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT notes FROM building_consent_applications WHERE application_id = ?";
            $result = $db->fetch($sql, [$applicationId]);

            return $result ? $result['notes'] : null;
        } catch (\Exception $e) {
            error_log("Error getting application notes: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate application requirements
     */
    private function validateApplicationRequirements(array $application): array
    {
        $errors = [];
        $requirements = $application['requirements'] ?? [];

        // Check if all required documents are uploaded
        foreach ($requirements as $requirement) {
            if (!isset($application['documents'][$requirement]) || empty($application['documents'][$requirement])) {
                $errors[] = "Required document missing: {$requirement}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Start consent workflow
     */
    private function startConsentWorkflow(string $applicationId): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            return $workflowEngine->startWorkflow('building_consent_process', $applicationId);
        } catch (\Exception $e) {
            error_log("Error starting consent workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Advance workflow
     */
    private function advanceWorkflow(string $applicationId, string $step): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            return $workflowEngine->advanceWorkflow($applicationId, $step);
        } catch (\Exception $e) {
            error_log("Error advancing workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, int $userId, array $data = []): bool
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
     * Create application fees
     */
    private function createApplicationFees(string $applicationId, array $fees): bool
    {
        try {
            $db = Database::getInstance();

            foreach ($fees as $fee) {
                $invoiceNumber = $this->generateInvoiceNumber();

                $sql = "INSERT INTO building_fees (
                    application_id, fee_type, amount, description, due_date, invoice_number
                ) VALUES (?, ?, ?, ?, ?, ?)";

                $params = [
                    $applicationId,
                    $fee['fee_type'],
                    $fee['amount'],
                    $fee['description'],
                    $fee['due_date'],
                    $invoiceNumber
                ];

                if (!$db->execute($sql, $params)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error creating application fees: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        return 'INV' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate consent number
     */
    private function generateConsentNumber(): string
    {
        return 'BCN' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create compliance requirements
     */
    private function createComplianceRequirements(string $applicationId, string $consentType): bool
    {
        try {
            $db = Database::getInstance();

            foreach ($this->complianceRequirements as $requirementType => $requirement) {
                $sql = "INSERT INTO building_compliance (
                    application_id, requirement_type, description, due_date
                ) VALUES (?, ?, ?, ?)";

                $dueDate = $this->calculateComplianceDueDate($requirement);

                $params = [
                    $applicationId,
                    $requirementType,
                    $requirement['description'],
                    $dueDate
                ];

                if (!$db->execute($sql, $params)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error creating compliance requirements: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate compliance due date
     */
    private function calculateComplianceDueDate(array $requirement): string
    {
        $baseDate = date('Y-m-d');

        if ($requirement['frequency'] === 'annual') {
            return date('Y-m-d', strtotime($baseDate . ' +' . $requirement['due_month'] . ' months'));
        } elseif ($requirement['frequency'] === 'biennial') {
            return date('Y-m-d', strtotime($baseDate . ' +2 years +' . $requirement['due_month'] . ' months'));
        }

        return date('Y-m-d', strtotime($baseDate . ' +1 year'));
    }

    /**
     * Schedule required inspections
     */
    private function scheduleRequiredInspections(string $applicationId, string $consentType): bool
    {
        try {
            $consentTypeDetails = $this->consentTypes[$consentType];
            $inspections = $consentTypeDetails['inspections_required'];

            foreach ($inspections as $inspectionType) {
                $inspectionData = [
                    'application_id' => $applicationId,
                    'inspection_type' => $inspectionType,
                    'scheduled_date' => $this->calculateInspectionDate($inspectionType),
                    'status' => 'scheduled'
                ];

                if (!$this->saveBuildingInspection($inspectionData)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error scheduling required inspections: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate inspection date
     */
    private function calculateInspectionDate(string $inspectionType): string
    {
        $baseDate = date('Y-m-d H:i:s');

        // Different inspection types have different scheduling requirements
        switch ($inspectionType) {
            case 'foundation':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +2 weeks'));
            case 'frame':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +4 weeks'));
            case 'insulation':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +6 weeks'));
            case 'plumbing':
            case 'electrical':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +8 weeks'));
            case 'final':
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +12 weeks'));
            default:
                return date('Y-m-d H:i:s', strtotime($baseDate . ' +4 weeks'));
        }
    }

    /**
     * Save building inspection
     */
    private function saveBuildingInspection(array $inspection): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO building_inspections (
                application_id, inspection_type, scheduled_date, status
            ) VALUES (?, ?, ?, ?)";

            $params = [
                $inspection['application_id'],
                $inspection['inspection_type'],
                $inspection['scheduled_date'],
                $inspection['status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving building inspection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get building inspection
     */
    private function getBuildingInspection(int $inspectionId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM building_inspections WHERE id = ?";
            $result = $db->fetch($sql, [$inspectionId]);

            if ($result) {
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting building inspection: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update inspection
     */
    private function updateInspection(int $inspectionId, array $data): bool
    {
        try {
            $db = Database::getInstance();

            $setParts = [];
            $params = [];

            foreach ($data as $field => $value) {
                if (is_array($value)) {
                    $setParts[] = "{$field} = ?";
                    $params[] = json_encode($value);
                } else {
                    $setParts[] = "{$field} = ?";
                    $params[] = $value;
                }
            }

            $params[] = $inspectionId;

            $sql = "UPDATE building_inspections SET " . implode(', ', $setParts) . " WHERE id = ?";
            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error updating inspection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule follow-up inspection
     */
    private function scheduleFollowUpInspection(int $inspectionId, ?string $followUpDate): bool
    {
        try {
            $db = Database::getInstance();

            if (!$followUpDate) {
                $followUpDate = date('Y-m-d H:i:s', strtotime('+1 week'));
            }

            $sql = "UPDATE building_inspections SET follow_up_required = TRUE, follow_up_date = ? WHERE id = ?";
            return $db->execute($sql, [$followUpDate, $inspectionId]);
        } catch (\Exception $e) {
            error_log("Error scheduling follow-up inspection: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save building certificate
     */
    private function saveBuildingCertificate(array $certificate): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO building_certificates (
                application_id, certificate_type, certificate_number,
                issue_date, expiry_date, conditions, limitations, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $certificate['application_id'],
                $certificate['certificate_type'],
                $certificate['certificate_number'],
                $certificate['issue_date'],
                $certificate['expiry_date'],
                json_encode($certificate['conditions']),
                $certificate['limitations'],
                $certificate['status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving building certificate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate certificate number
     */
    private function generateCertificateNumber(string $certificateType): string
    {
        $prefix = match($certificateType) {
            'code_compliance' => 'CC',
            'completion' => 'CO',
            'occupancy' => 'OC',
            'compliance_schedule' => 'CS',
            default => 'BC'
        };

        return $prefix . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get building fee
     */
    private function getBuildingFee(string $invoiceNumber): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM building_fees WHERE invoice_number = ?";
            return $db->fetch($sql, [$invoiceNumber]);
        } catch (\Exception $e) {
            error_log("Error getting building fee: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update fee status
     */
    private function updateFeeStatus(string $invoiceNumber, string $status, string $paymentDate, string $paymentMethod): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE building_fees SET status = ?, payment_date = ?, payment_method = ? WHERE invoice_number = ?";
            return $db->execute($sql, [$status, $paymentDate, $paymentMethod, $invoiceNumber]);
        } catch (\Exception $e) {
            error_log("Error updating fee status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate inspection data
     */
    private function validateInspectionData(array $data): array
    {
        $errors = [];

        $requiredFields = ['application_id', 'inspection_type', 'preferred_date', 'preferred_time'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate inspection type
        if (!isset($this->inspectionTypes[$data['inspection_type'] ?? ''])) {
            $errors[] = "Invalid inspection type";
        }

        // Validate date format
        if (isset($data['preferred_date']) && !strtotime($data['preferred_date'])) {
            $errors[] = "Invalid date format";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

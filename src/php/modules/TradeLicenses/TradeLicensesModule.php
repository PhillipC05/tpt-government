<?php
/**
 * TPT Government Platform - Trade Licenses Module
 *
 * Comprehensive trade licensing and professional certification management system
 * supporting qualification verification, certification tracking, and regulatory compliance
 */

namespace Modules\TradeLicenses;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class TradeLicensesModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Trade Licenses',
        'version' => '2.1.0',
        'description' => 'Comprehensive trade licensing and professional certification management system',
        'author' => 'TPT Government Platform',
        'category' => 'business_services',
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
        'trade_licenses.view' => 'View trade license applications and certifications',
        'trade_licenses.create' => 'Create new trade license applications',
        'trade_licenses.edit' => 'Edit trade license information',
        'trade_licenses.approve' => 'Approve trade license applications',
        'trade_licenses.reject' => 'Reject trade license applications',
        'trade_licenses.renew' => 'Renew trade licenses',
        'trade_licenses.revoke' => 'Revoke trade licenses',
        'trade_licenses.disciplinary' => 'Manage disciplinary actions',
        'trade_licenses.compliance' => 'Monitor continuing education compliance',
        'trade_licenses.certification' => 'Manage professional certifications'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'trade_licenses' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'applicant_id' => 'INT NOT NULL',
            'trade_type' => 'VARCHAR(100) NOT NULL',
            'license_class' => "ENUM('apprentice','journeyman','master','specialist') NOT NULL",
            'status' => "ENUM('application_pending','under_review','approved','rejected','active','expired','suspended','revoked') DEFAULT 'application_pending'",
            'application_date' => 'DATETIME NOT NULL',
            'approval_date' => 'DATETIME NULL',
            'expiry_date' => 'DATETIME NULL',
            'renewal_date' => 'DATETIME NULL',
            'fee_amount' => 'DECIMAL(8,2) DEFAULT 0.00',
            'bond_amount' => 'DECIMAL(8,2) DEFAULT 0.00',
            'insurance_required' => 'BOOLEAN DEFAULT TRUE',
            'supervisor_id' => 'INT NULL',
            'work_address' => 'TEXT',
            'specializations' => 'JSON',
            'qualifications' => 'JSON',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'trade_certifications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'certification_type' => 'VARCHAR(100) NOT NULL',
            'certification_number' => 'VARCHAR(50) UNIQUE NOT NULL',
            'issuing_authority' => 'VARCHAR(255) NOT NULL',
            'issue_date' => 'DATE NOT NULL',
            'expiry_date' => 'DATE NULL',
            'status' => "ENUM('active','expired','revoked','suspended') DEFAULT 'active'",
            'verification_date' => 'DATE NULL',
            'verification_status' => "ENUM('pending','verified','failed') DEFAULT 'pending'",
            'document_path' => 'VARCHAR(500)',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'continuing_education' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'course_name' => 'VARCHAR(255) NOT NULL',
            'provider' => 'VARCHAR(255) NOT NULL',
            'course_type' => "ENUM('classroom','online','workshop','conference','self_study') NOT NULL",
            'completion_date' => 'DATE NOT NULL',
            'credit_hours' => 'DECIMAL(5,2) NOT NULL',
            'certificate_number' => 'VARCHAR(50)',
            'verification_status' => "ENUM('pending','verified','rejected') DEFAULT 'pending'",
            'document_path' => 'VARCHAR(500)',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'disciplinary_actions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'action_type' => "ENUM('warning','fine','suspension','revocation','probation') NOT NULL",
            'violation_description' => 'TEXT NOT NULL',
            'action_date' => 'DATE NOT NULL',
            'effective_date' => 'DATE NOT NULL',
            'end_date' => 'DATE NULL',
            'fine_amount' => 'DECIMAL(8,2) DEFAULT 0.00',
            'investigator_id' => 'INT NOT NULL',
            'hearing_date' => 'DATE NULL',
            'hearing_officer' => 'VARCHAR(100)',
            'decision' => 'TEXT',
            'appeal_deadline' => 'DATE NULL',
            'status' => "ENUM('pending','active','completed','appealed','overturned') DEFAULT 'pending'",
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'trade_examinations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'exam_type' => "ENUM('written','practical','oral','continuing_education') NOT NULL",
            'exam_date' => 'DATE NOT NULL',
            'exam_location' => 'VARCHAR(255)',
            'examiner_id' => 'INT NOT NULL',
            'score' => 'DECIMAL(5,2) NULL',
            'passing_score' => 'DECIMAL(5,2) NOT NULL',
            'result' => "ENUM('pass','fail','incomplete','withdrawn') NULL",
            'certificate_issued' => 'BOOLEAN DEFAULT FALSE',
            'retake_eligible' => 'BOOLEAN DEFAULT FALSE',
            'next_retake_date' => 'DATE NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'trade_types' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'trade_code' => 'VARCHAR(10) UNIQUE NOT NULL',
            'trade_name' => 'VARCHAR(255) NOT NULL',
            'category' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'license_required' => 'BOOLEAN DEFAULT TRUE',
            'insurance_required' => 'BOOLEAN DEFAULT TRUE',
            'bond_required' => 'BOOLEAN DEFAULT FALSE',
            'continuing_education_required' => 'BOOLEAN DEFAULT TRUE',
            'education_hours_per_year' => 'INT DEFAULT 8',
            'exam_required' => 'BOOLEAN DEFAULT TRUE',
            'supervision_required' => 'BOOLEAN DEFAULT FALSE',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'license_renewals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'renewal_period_start' => 'DATE NOT NULL',
            'renewal_period_end' => 'DATE NOT NULL',
            'renewal_fee' => 'DECIMAL(8,2) NOT NULL',
            'continuing_education_completed' => 'BOOLEAN DEFAULT FALSE',
            'insurance_verified' => 'BOOLEAN DEFAULT FALSE',
            'status' => "ENUM('pending','approved','rejected','expired') DEFAULT 'pending'",
            'processed_date' => 'DATE NULL',
            'processed_by' => 'INT NULL',
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
            'path' => '/api/trade-licenses',
            'handler' => 'getTradeLicenses',
            'auth' => true,
            'permissions' => ['trade_licenses.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/trade-licenses',
            'handler' => 'createTradeLicense',
            'auth' => true,
            'permissions' => ['trade_licenses.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/trade-licenses/{id}',
            'handler' => 'getTradeLicense',
            'auth' => true,
            'permissions' => ['trade_licenses.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/trade-licenses/{id}',
            'handler' => 'updateTradeLicense',
            'auth' => true,
            'permissions' => ['trade_licenses.edit']
        ],
        [
            'method' => 'POST',
            'path' => '/api/trade-licenses/{id}/approve',
            'handler' => 'approveTradeLicense',
            'auth' => true,
            'permissions' => ['trade_licenses.approve']
        ],
        [
            'method' => 'POST',
            'path' => '/api/trade-licenses/{id}/renew',
            'handler' => 'renewTradeLicense',
            'auth' => true,
            'permissions' => ['trade_licenses.renew']
        ],
        [
            'method' => 'GET',
            'path' => '/api/trade-certifications',
            'handler' => 'getCertifications',
            'auth' => true,
            'permissions' => ['trade_licenses.certification']
        ],
        [
            'method' => 'POST',
            'path' => '/api/trade-certifications',
            'handler' => 'addCertification',
            'auth' => true,
            'permissions' => ['trade_licenses.certification']
        ],
        [
            'method' => 'GET',
            'path' => '/api/continuing-education',
            'handler' => 'getContinuingEducation',
            'auth' => true,
            'permissions' => ['trade_licenses.compliance']
        ],
        [
            'method' => 'POST',
            'path' => '/api/continuing-education',
            'handler' => 'addContinuingEducation',
            'auth' => true,
            'permissions' => ['trade_licenses.compliance']
        ],
        [
            'method' => 'GET',
            'path' => '/api/disciplinary-actions',
            'handler' => 'getDisciplinaryActions',
            'auth' => true,
            'permissions' => ['trade_licenses.disciplinary']
        ],
        [
            'method' => 'POST',
            'path' => '/api/disciplinary-actions',
            'handler' => 'createDisciplinaryAction',
            'auth' => true,
            'permissions' => ['trade_licenses.disciplinary']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'license_application' => [
            'name' => 'Trade License Application',
            'description' => 'Workflow for processing trade license applications',
            'steps' => [
                'application_pending' => ['name' => 'Application Submitted', 'next' => 'document_review'],
                'document_review' => ['name' => 'Document Review', 'next' => 'qualification_verification'],
                'qualification_verification' => ['name' => 'Qualification Verification', 'next' => 'background_check'],
                'background_check' => ['name' => 'Background Check', 'next' => 'exam_scheduling'],
                'exam_scheduling' => ['name' => 'Exam Scheduling', 'next' => 'exam_completion'],
                'exam_completion' => ['name' => 'Exam Completed', 'next' => 'final_review'],
                'final_review' => ['name' => 'Final Review', 'next' => ['approved', 'rejected', 'additional_requirements']],
                'additional_requirements' => ['name' => 'Additional Requirements', 'next' => 'final_review'],
                'approved' => ['name' => 'License Approved', 'next' => 'license_issued'],
                'license_issued' => ['name' => 'License Issued', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null]
            ]
        ],
        'license_renewal' => [
            'name' => 'License Renewal',
            'description' => 'Workflow for trade license renewal process',
            'steps' => [
                'renewal_due' => ['name' => 'Renewal Due', 'next' => 'ce_verification'],
                'ce_verification' => ['name' => 'Continuing Education Verification', 'next' => 'insurance_verification'],
                'insurance_verification' => ['name' => 'Insurance Verification', 'next' => 'fee_payment'],
                'fee_payment' => ['name' => 'Fee Payment', 'next' => 'renewal_approval'],
                'renewal_approval' => ['name' => 'Renewal Approved', 'next' => 'license_renewed'],
                'license_renewed' => ['name' => 'License Renewed', 'next' => null]
            ]
        ],
        'disciplinary_process' => [
            'name' => 'Disciplinary Process',
            'description' => 'Workflow for handling disciplinary actions',
            'steps' => [
                'complaint_received' => ['name' => 'Complaint Received', 'next' => 'investigation'],
                'investigation' => ['name' => 'Investigation', 'next' => 'hearing_scheduled'],
                'hearing_scheduled' => ['name' => 'Hearing Scheduled', 'next' => 'hearing_completed'],
                'hearing_completed' => ['name' => 'Hearing Completed', 'next' => 'decision_made'],
                'decision_made' => ['name' => 'Decision Made', 'next' => ['action_implemented', 'appeal_period']],
                'appeal_period' => ['name' => 'Appeal Period', 'next' => ['appeal_upheld', 'appeal_overturned']],
                'appeal_upheld' => ['name' => 'Appeal Upheld', 'next' => 'action_implemented'],
                'appeal_overturned' => ['name' => 'Appeal Overturned', 'next' => null],
                'action_implemented' => ['name' => 'Action Implemented', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'trade_license_application' => [
            'name' => 'Trade License Application',
            'fields' => [
                'trade_type' => ['type' => 'select', 'required' => true, 'label' => 'Trade Type'],
                'license_class' => ['type' => 'select', 'required' => true, 'label' => 'License Class'],
                'applicant_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                'applicant_email' => ['type' => 'email', 'required' => true, 'label' => 'Email Address'],
                'applicant_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Phone Number'],
                'date_of_birth' => ['type' => 'date', 'required' => true, 'label' => 'Date of Birth'],
                'ssn' => ['type' => 'text', 'required' => true, 'label' => 'Social Security Number'],
                'address' => ['type' => 'textarea', 'required' => true, 'label' => 'Residential Address'],
                'work_address' => ['type' => 'textarea', 'required' => false, 'label' => 'Work Address'],
                'employer_name' => ['type' => 'text', 'required' => false, 'label' => 'Employer Name'],
                'supervisor_name' => ['type' => 'text', 'required' => false, 'label' => 'Supervisor Name'],
                'supervisor_license' => ['type' => 'text', 'required' => false, 'label' => 'Supervisor License Number'],
                'education_level' => ['type' => 'select', 'required' => true, 'label' => 'Education Level'],
                'training_program' => ['type' => 'text', 'required' => false, 'label' => 'Training Program'],
                'years_experience' => ['type' => 'number', 'required' => true, 'label' => 'Years of Experience'],
                'specializations' => ['type' => 'multiselect', 'required' => false, 'label' => 'Specializations'],
                'criminal_history' => ['type' => 'radio', 'required' => true, 'options' => ['yes', 'no'], 'label' => 'Criminal History'],
                'criminal_details' => ['type' => 'textarea', 'required' => false, 'label' => 'Criminal History Details'],
                'insurance_provider' => ['type' => 'text', 'required' => false, 'label' => 'Insurance Provider'],
                'policy_number' => ['type' => 'text', 'required' => false, 'label' => 'Policy Number']
            ],
            'documents' => [
                'photo_id' => ['required' => true, 'label' => 'Government Issued Photo ID'],
                'proof_of_address' => ['required' => true, 'label' => 'Proof of Address'],
                'education_certificates' => ['required' => true, 'label' => 'Education Certificates'],
                'training_certificates' => ['required' => false, 'label' => 'Training Certificates'],
                'experience_letters' => ['required' => true, 'label' => 'Experience Letters'],
                'background_check' => ['required' => true, 'label' => 'Background Check Results'],
                'insurance_certificate' => ['required' => false, 'label' => 'Insurance Certificate'],
                'medical_certificate' => ['required' => false, 'label' => 'Medical Certificate']
            ]
        ],
        'continuing_education' => [
            'name' => 'Continuing Education Submission',
            'fields' => [
                'course_name' => ['type' => 'text', 'required' => true, 'label' => 'Course Name'],
                'provider' => ['type' => 'text', 'required' => true, 'label' => 'Course Provider'],
                'course_type' => ['type' => 'select', 'required' => true, 'label' => 'Course Type'],
                'completion_date' => ['type' => 'date', 'required' => true, 'label' => 'Completion Date'],
                'credit_hours' => ['type' => 'number', 'required' => true, 'label' => 'Credit Hours', 'step' => '0.5'],
                'certificate_number' => ['type' => 'text', 'required' => false, 'label' => 'Certificate Number'],
                'course_description' => ['type' => 'textarea', 'required' => false, 'label' => 'Course Description']
            ],
            'documents' => [
                'certificate' => ['required' => true, 'label' => 'Course Certificate'],
                'transcript' => ['required' => false, 'label' => 'Course Transcript']
            ]
        ],
        'disciplinary_complaint' => [
            'name' => 'Disciplinary Complaint',
            'fields' => [
                'license_number' => ['type' => 'text', 'required' => true, 'label' => 'License Number'],
                'complaint_type' => ['type' => 'select', 'required' => true, 'label' => 'Complaint Type'],
                'complaint_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Complaint Description'],
                'incident_date' => ['type' => 'date', 'required' => true, 'label' => 'Incident Date'],
                'incident_location' => ['type' => 'textarea', 'required' => true, 'label' => 'Incident Location'],
                'complainant_name' => ['type' => 'text', 'required' => true, 'label' => 'Complainant Name'],
                'complainant_contact' => ['type' => 'text', 'required' => true, 'label' => 'Complainant Contact'],
                'witnesses' => ['type' => 'textarea', 'required' => false, 'label' => 'Witnesses'],
                'evidence_description' => ['type' => 'textarea', 'required' => false, 'label' => 'Evidence Description']
            ],
            'documents' => [
                'complaint_documents' => ['required' => false, 'label' => 'Supporting Documents'],
                'evidence_photos' => ['required' => false, 'label' => 'Evidence Photos']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'license_overview' => [
            'name' => 'Trade License Overview Report',
            'description' => 'Summary of all trade licenses by type and status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'trade_type' => ['type' => 'select', 'required' => false],
                'license_class' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'license_number', 'trade_type', 'license_class', 'status',
                'application_date', 'approval_date', 'expiry_date'
            ]
        ],
        'certification_compliance' => [
            'name' => 'Certification Compliance Report',
            'description' => 'Certification status and compliance tracking',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'certification_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'license_number', 'certification_type', 'issue_date',
                'expiry_date', 'status', 'verification_status'
            ]
        ],
        'continuing_education' => [
            'name' => 'Continuing Education Report',
            'description' => 'Continuing education completion and compliance',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'trade_type' => ['type' => 'select', 'required' => false],
                'course_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'license_number', 'course_name', 'provider', 'completion_date',
                'credit_hours', 'verification_status'
            ]
        ],
        'disciplinary_actions' => [
            'name' => 'Disciplinary Actions Report',
            'description' => 'Summary of disciplinary actions taken',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'action_type' => ['type' => 'select', 'required' => false],
                'trade_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'license_number', 'action_type', 'violation_description',
                'action_date', 'status', 'investigator_id'
            ]
        ],
        'renewal_compliance' => [
            'name' => 'License Renewal Compliance Report',
            'description' => 'License renewal status and compliance',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'renewal_status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'license_number', 'renewal_period', 'continuing_education_completed',
                'insurance_verified', 'status', 'processed_date'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'application_submitted' => [
            'name' => 'License Application Submitted',
            'template' => 'Your trade license application has been submitted successfully. Application will be reviewed within 5-7 business days.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_created']
        ],
        'application_approved' => [
            'name' => 'License Application Approved',
            'template' => 'Congratulations! Your {trade_type} license application has been approved. License Number: {license_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_approved']
        ],
        'application_rejected' => [
            'name' => 'License Application Rejected',
            'template' => 'Your trade license application has been rejected. Please review the feedback and contact us for clarification.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_rejected']
        ],
        'exam_scheduled' => [
            'name' => 'Examination Scheduled',
            'template' => 'Your trade examination has been scheduled for {exam_date} at {exam_location}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['exam_scheduled']
        ],
        'exam_results' => [
            'name' => 'Examination Results',
            'template' => 'Your examination results are available. Result: {exam_result}. Score: {exam_score}%',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['exam_completed']
        ],
        'renewal_reminder' => [
            'name' => 'License Renewal Reminder',
            'template' => 'Your {trade_type} license expires on {expiry_date}. Please ensure continuing education requirements are met.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['renewal_due']
        ],
        'renewal_overdue' => [
            'name' => 'License Renewal Overdue',
            'template' => 'Your trade license renewal is overdue. Immediate action is required to avoid suspension.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['renewal_overdue']
        ],
        'disciplinary_notice' => [
            'name' => 'Disciplinary Action Notice',
            'template' => 'A disciplinary action has been initiated against your license. Type: {action_type}. Hearing Date: {hearing_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['disciplinary_action']
        ],
        'ce_requirement_reminder' => [
            'name' => 'Continuing Education Reminder',
            'template' => 'You need {required_hours} continuing education hours by {deadline_date}. Completed: {completed_hours} hours.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['ce_due']
        ],
        'license_suspended' => [
            'name' => 'License Suspended',
            'template' => 'Your trade license has been suspended due to {reason}. Contact us immediately for resolution.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['license_suspended']
        ]
    ];

    /**
     * Trade types configuration
     */
    private array $tradeTypes = [];

    /**
     * Certification types
     */
    private array $certificationTypes = [];

    /**
     * Continuing education requirements
     */
    private array $ceRequirements = [];

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
            'application_processing_days' => 30,
            'renewal_reminder_days' => 60,
            'grace_period_days' => 30,
            'max_upload_size' => 10485760, // 10MB
            'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
            'exam_retake_fee' => 150.00,
            'disciplinary_hearing_days' => 30,
            'appeal_period_days' => 14,
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
        $this->initializeTradeTypes();
        $this->initializeCertificationTypes();
        $this->initializeCeRequirements();
    }

    /**
     * Initialize trade types
     */
    private function initializeTradeTypes(): void
    {
        $this->tradeTypes = [
            'electrical' => [
                'code' => 'ELE',
                'name' => 'Electrical',
                'category' => 'construction',
                'license_required' => true,
                'insurance_required' => true,
                'bond_required' => true,
                'continuing_education_required' => true,
                'education_hours_per_year' => 8,
                'exam_required' => true,
                'supervision_required' => false
            ],
            'plumbing' => [
                'code' => 'PLU',
                'name' => 'Plumbing',
                'category' => 'construction',
                'license_required' => true,
                'insurance_required' => true,
                'bond_required' => true,
                'continuing_education_required' => true,
                'education_hours_per_year' => 8,
                'exam_required' => true,
                'supervision_required' => false
            ],
            'hvac' => [
                'code' => 'HVAC',
                'name' => 'Heating, Ventilation, and Air Conditioning',
                'category' => 'construction',
                'license_required' => true,
                'insurance_required' => true,
                'bond_required' => false,
                'continuing_education_required' => true,
                'education_hours_per_year' => 6,
                'exam_required' => true,
                'supervision_required' => false
            ],
            'carpentry' => [
                'code' => 'CAR',
                'name' => 'Carpentry',
                'category' => 'construction',
                'license_required' => true,
                'insurance_required' => true,
                'bond_required' => false,
                'continuing_education_required' => true,
                'education_hours_per_year' => 4,
                'exam_required' => false,
                'supervision_required' => false
            ],
            'roofing' => [
                'code' => 'ROO',
                'name' => 'Roofing',
                'category' => 'construction',
                'license_required' => true,
                'insurance_required' => true,
                'bond_required' => true,
                'continuing_education_required' => true,
                'education_hours_per_year' => 4,
                'exam_required' => false,
                'supervision_required' => false
            ],
            'landscaping' => [
                'code' => 'LAN',
                'name' => 'Landscaping',
                'category' => 'landscaping',
                'license_required' => false,
                'insurance_required' => true,
                'bond_required' => false,
                'continuing_education_required' => false,
                'education_hours_per_year' => 0,
                'exam_required' => false,
                'supervision_required' => false
            ],
            'painting' => [
                'code' => 'PAI',
                'name' => 'Painting and Decorating',
                'category' => 'finishing',
                'license_required' => false,
                'insurance_required' => true,
                'bond_required' => false,
                'continuing_education_required' => false,
                'education_hours_per_year' => 0,
                'exam_required' => false,
                'supervision_required' => false
            ]
        ];
    }

    /**
     * Initialize certification types
     */
    private function initializeCertificationTypes(): void
    {
        $this->certificationTypes = [
            'osha_10' => [
                'name' => 'OSHA 10-Hour Construction Safety',
                'issuing_authority' => 'Occupational Safety and Health Administration',
                'validity_years' => 5,
                'renewable' => true
            ],
            'osha_30' => [
                'name' => 'OSHA 30-Hour Construction Safety',
                'issuing_authority' => 'Occupational Safety and Health Administration',
                'validity_years' => 5,
                'renewable' => true
            ],
            'first_aid_cpr' => [
                'name' => 'First Aid and CPR Certification',
                'issuing_authority' => 'American Red Cross / American Heart Association',
                'validity_years' => 2,
                'renewable' => true
            ],
            'epa_certification' => [
                'name' => 'EPA Lead-Based Paint Certification',
                'issuing_authority' => 'Environmental Protection Agency',
                'validity_years' => 5,
                'renewable' => true
            ],
            'asbestos_certification' => [
                'name' => 'Asbestos Abatement Certification',
                'issuing_authority' => 'Environmental Protection Agency',
                'validity_years' => 5,
                'renewable' => true
            ]
        ];
    }

    /**
     * Initialize continuing education requirements
     */
    private function initializeCeRequirements(): void
    {
        $this->ceRequirements = [
            'annual' => [
                'frequency' => 'annual',
                'minimum_hours' => 8,
                'maximum_carryover' => 16,
                'deadline_month' => 12
            ],
            'biennial' => [
                'frequency' => 'biennial',
                'minimum_hours' => 16,
                'maximum_carryover' => 32,
                'deadline_month' => 12
            ]
        ];
    }

    /**
     * Create trade license application
     */
    public function createTradeLicenseApplication(array $applicationData): array
    {
        // Validate application data
        $validation = $this->validateLicenseApplication($applicationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate license number
        $licenseNumber = $this->generateLicenseNumber();

        // Calculate fees
        $fees = $this->calculateLicenseFees($applicationData);

        // Create application record
        $application = [
            'license_number' => $licenseNumber,
            'applicant_id' => $applicationData['applicant_id'],
            'trade_type' => $applicationData['trade_type'],
            'license_class' => $applicationData['license_class'],
            'status' => 'application_pending',
            'application_date' => date('Y-m-d H:i:s'),
            'fee_amount' => $fees['application_fee'],
            'bond_amount' => $fees['bond_amount'],
            'insurance_required' => $this->tradeTypes[$applicationData['trade_type']]['insurance_required'],
            'supervisor_id' => $applicationData['supervisor_id'] ?? null,
            'work_address' => $applicationData['work_address'] ?? '',
            'specializations' => $applicationData['specializations'] ?? [],
            'qualifications' => $applicationData['qualifications'] ?? [],
            'documents' => $applicationData['documents'] ?? [],
            'notes' => $applicationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveLicenseApplication($application);

        // Start workflow
        $this->startLicenseWorkflow($licenseNumber);

        // Send notification
        $this->sendNotification('application_submitted', $applicationData['applicant_id'], [
            'license_number' => $licenseNumber
        ]);

        return [
            'success' => true,
            'license_number' => $licenseNumber,
            'application_fee' => $fees['application_fee'],
            'bond_amount' => $fees['bond_amount'],
            'processing_time' => $this->config['application_processing_days'] . ' days'
        ];
    }

    /**
     * Approve trade license application
     */
    public function approveTradeLicenseApplication(string $licenseNumber, array $approvalData = []): array
    {
        $license = $this->getTradeLicense($licenseNumber);
        if (!$license) {
            return [
                'success' => false,
                'error' => 'License not found'
            ];
        }

        // Calculate expiry date (1 year from approval)
        $expiryDate = date('Y-m-d H:i:s', strtotime('+1 year'));

        // Update license status
        $this->updateLicenseStatus($licenseNumber, 'approved', [
            'approval_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $expiryDate
        ]);

        // Advance workflow
        $this->advanceWorkflow($licenseNumber, 'approved');

        // Send notification
        $this->sendNotification('application_approved', $license['applicant_id'], [
            'license_number' => $licenseNumber,
            'trade_type' => $license['trade_type']
        ]);

        return [
            'success' => true,
            'license_number' => $licenseNumber,
            'expiry_date' => $expiryDate,
            'message' => 'License application approved successfully'
        ];
    }

    /**
     * Add certification to license
     */
    public function addCertification(array $certificationData): array
    {
        // Validate certification data
        $validation = $this->validateCertificationData($certificationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Create certification record
        $certification = [
            'license_id' => $certificationData['license_id'],
            'certification_type' => $certificationData['certification_type'],
            'certification_number' => $certificationData['certification_number'],
            'issuing_authority' => $certificationData['issuing_authority'],
            'issue_date' => $certificationData['issue_date'],
            'expiry_date' => $certificationData['expiry_date'] ?? null,
            'verification_status' => 'pending',
            'document_path' => $certificationData['document_path'] ?? null,
            'notes' => $certificationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveCertification($certification);

        return [
            'success' => true,
            'certification_id' => $this->getLastInsertId(),
            'message' => 'Certification added successfully'
        ];
    }

    /**
     * Add continuing education record
     */
    public function addContinuingEducation(array $ceData): array
    {
        // Validate CE data
        $validation = $this->validateCeData($ceData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Create CE record
        $ceRecord = [
            'license_id' => $ceData['license_id'],
            'course_name' => $ceData['course_name'],
            'provider' => $ceData['provider'],
            'course_type' => $ceData['course_type'],
            'completion_date' => $ceData['completion_date'],
            'credit_hours' => $ceData['credit_hours'],
            'certificate_number' => $ceData['certificate_number'] ?? null,
            'verification_status' => 'pending',
            'document_path' => $ceData['document_path'] ?? null,
            'notes' => $ceData['notes'] ?? ''
        ];

        // Save to database
        $this->saveContinuingEducation($ceRecord);

        return [
            'success' => true,
            'ce_id' => $this->getLastInsertId(),
            'message' => 'Continuing education record added successfully'
        ];
    }

    /**
     * Create disciplinary action
     */
    public function createDisciplinaryAction(array $disciplinaryData): array
    {
        // Validate disciplinary data
        $validation = $this->validateDisciplinaryData($disciplinaryData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Get license information
        $license = $this->getTradeLicense($disciplinaryData['license_number']);
        if (!$license) {
            return [
                'success' => false,
                'error' => 'License not found'
            ];
        }

        // Create disciplinary record
        $disciplinary = [
            'license_id' => $license['id'],
            'action_type' => $disciplinaryData['action_type'],
            'violation_description' => $disciplinaryData['violation_description'],
            'action_date' => date('Y-m-d'),
            'effective_date' => $disciplinaryData['effective_date'] ?? date('Y-m-d'),
            'end_date' => $disciplinaryData['end_date'] ?? null,
            'fine_amount' => $disciplinaryData['fine_amount'] ?? 0.00,
            'investigator_id' => $disciplinaryData['investigator_id'],
            'hearing_date' => $disciplinaryData['hearing_date'] ?? null,
            'status' => 'pending',
            'documents' => $disciplinaryData['documents'] ?? [],
            'notes' => $disciplinaryData['notes'] ?? ''
        ];

        // Save to database
        $this->saveDisciplinaryAction($disciplinary);

        // Update license status if suspension or revocation
        if (in_array($disciplinaryData['action_type'], ['suspension', 'revocation'])) {
            $this->updateLicenseStatus($disciplinaryData['license_number'], $disciplinaryData['action_type'] === 'suspension' ? 'suspended' : 'revoked');
        }

        // Send notification
        $this->sendNotification('disciplinary_notice', $license['applicant_id'], [
            'action_type' => $disciplinaryData['action_type'],
            'hearing_date' => $disciplinaryData['hearing_date'] ?? 'TBD'
        ]);

        return [
            'success' => true,
            'disciplinary_id' => $this->getLastInsertId(),
            'message' => 'Disciplinary action created successfully'
        ];
    }

    /**
     * Renew trade license
     */
    public function renewTradeLicense(string $licenseNumber, array $renewalData = []): array
    {
        $license = $this->getTradeLicense($licenseNumber);
        if (!$license) {
            return [
                'success' => false,
                'error' => 'License not found'
            ];
        }

        // Check if renewal is allowed
        if (!$this->canRenewLicense($license)) {
            return [
                'success' => false,
                'error' => 'License cannot be renewed at this time'
            ];
        }

        // Check continuing education compliance
        $ceCompliance = $this->checkCeCompliance($licenseNumber);
        if (!$ceCompliance['compliant']) {
            return [
                'success' => false,
                'error' => 'Continuing education requirements not met',
                'ce_details' => $ceCompliance
            ];
        }

        // Calculate renewal fee
        $renewalFee = $this->calculateRenewalFee($license);

        // Create renewal record
        $renewal = [
            'license_id' => $license['id'],
            'renewal_period_start' => $license['expiry_date'],
            'renewal_period_end' => date('Y-m-d H:i:s', strtotime($license['expiry_date'] . ' +1 year')),
            'renewal_fee' => $renewalFee,
            'continuing_education_completed' => true,
            'insurance_verified' => $renewalData['insurance_verified'] ?? false,
            'status' => 'pending'
        ];

        // Save renewal record
        $this->saveRenewalRecord($renewal);

        return [
            'success' => true,
            'renewal_id' => $this->getLastInsertId(),
            'renewal_fee' => $renewalFee,
            'message' => 'License renewal initiated successfully'
        ];
    }

    /**
     * Get trade licenses (API handler)
     */
    public function getTradeLicenses(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM trade_licenses WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['trade_type'])) {
                $sql .= " AND trade_type = ?";
                $params[] = $filters['trade_type'];
            }

            if (isset($filters['applicant_id'])) {
                $sql .= " AND applicant_id = ?";
                $params[] = $filters['applicant_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['specializations'] = json_decode($result['specializations'], true);
                $result['qualifications'] = json_decode($result['qualifications'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting trade licenses: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve trade licenses'
            ];
        }
    }

    /**
     * Get trade license (API handler)
     */
    public function getTradeLicense(string $licenseNumber): array
    {
        $license = $this->getTradeLicense($licenseNumber);

        if (!$license) {
            return [
                'success' => false,
                'error' => 'License not found'
            ];
        }

        return [
            'success' => true,
            'data' => $license
        ];
    }

    /**
     * Create trade license (API handler)
     */
    public function createTradeLicense(array $data): array
    {
        return $this->createTradeLicenseApplication($data);
    }

    /**
     * Update trade license (API handler)
     */
    public function updateTradeLicense(string $licenseNumber, array $data): array
    {
        try {
            $license = $this->getTradeLicense($licenseNumber);

            if (!$license) {
                return [
                    'success' => false,
                    'error' => 'License not found'
                ];
            }

            if ($license['status'] !== 'application_pending') {
                return [
                    'success' => false,
                    'error' => 'License cannot be modified'
                ];
            }

            $this->updateLicense($licenseNumber, $data);

            return [
                'success' => true,
                'message' => 'License updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating license: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update license'
            ];
        }
    }

    /**
     * Approve trade license (API handler)
     */
    public function approveTradeLicense(string $licenseNumber, array $approvalData): array
    {
        return $this->approveTradeLicenseApplication($licenseNumber, $approvalData);
    }

    /**
     * Renew trade license (API handler)
     */
    public function renewTradeLicense(string $licenseNumber, array $renewalData): array
    {
        return $this->renewTradeLicense($licenseNumber, $renewalData);
    }

    /**
     * Get certifications (API handler)
     */
    public function getCertifications(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM trade_certifications WHERE 1=1";
            $params = [];

            if (isset($filters['license_id'])) {
                $sql .= " AND license_id = ?";
                $params[] = $filters['license_id'];
            }

            if (isset($filters['certification_type'])) {
                $sql .= " AND certification_type = ?";
                $params[] = $filters['certification_type'];
            }

            if (isset($filters['verification_status'])) {
                $sql .= " AND verification_status = ?";
                $params[] = $filters['verification_status'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting certifications: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve certifications'
            ];
        }
    }

    /**
     * Add certification (API handler)
     */
    public function addCertification(array $data): array
    {
        return $this->addCertification($data);
    }

    /**
     * Get continuing education (API handler)
     */
    public function getContinuingEducation(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM continuing_education WHERE 1=1";
            $params = [];

            if (isset($filters['license_id'])) {
                $sql .= " AND license_id = ?";
                $params[] = $filters['license_id'];
            }

            if (isset($filters['verification_status'])) {
                $sql .= " AND verification_status = ?";
                $params[] = $filters['verification_status'];
            }

            if (isset($filters['course_type'])) {
                $sql .= " AND course_type = ?";
                $params[] = $filters['course_type'];
            }

            $sql .= " ORDER BY completion_date DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting continuing education: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve continuing education records'
            ];
        }
    }

    /**
     * Add continuing education (API handler)
     */
    public function addContinuingEducation(array $data): array
    {
        return $this->addContinuingEducation($data);
    }

    /**
     * Get disciplinary actions (API handler)
     */
    public function getDisciplinaryActions(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM disciplinary_actions WHERE 1=1";
            $params = [];

            if (isset($filters['license_id'])) {
                $sql .= " AND license_id = ?";
                $params[] = $filters['license_id'];
            }

            if (isset($filters['action_type'])) {
                $sql .= " AND action_type = ?";
                $params[] = $filters['action_type'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY action_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting disciplinary actions: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve disciplinary actions'
            ];
        }
    }

    /**
     * Create disciplinary action (API handler)
     */
    public function createDisciplinaryAction(array $data): array
    {
        return $this->createDisciplinaryAction($data);
    }

    /**
     * Validate license application data
     */
    private function validateLicenseApplication(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'applicant_id', 'trade_type', 'license_class'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!isset($this->tradeTypes[$data['trade_type'] ?? ''])) {
            $errors[] = "Invalid trade type";
        }

        if (!in_array($data['license_class'] ?? '', ['apprentice', 'journeyman', 'master', 'specialist'])) {
            $errors[] = "Invalid license class";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate certification data
     */
    private function validateCertificationData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'license_id', 'certification_type', 'certification_number',
            'issuing_authority', 'issue_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!isset($this->certificationTypes[$data['certification_type'] ?? ''])) {
            $errors[] = "Invalid certification type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate continuing education data
     */
    private function validateCeData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'license_id', 'course_name', 'provider', 'course_type',
            'completion_date', 'credit_hours'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!is_numeric($data['credit_hours']) || $data['credit_hours'] <= 0) {
            $errors[] = "Credit hours must be a positive number";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate disciplinary data
     */
    private function validateDisciplinaryData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'license_number', 'action_type', 'violation_description', 'investigator_id'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['action_type'] ?? '', ['warning', 'fine', 'suspension', 'revocation', 'probation'])) {
            $errors[] = "Invalid action type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate license number
     */
    private function generateLicenseNumber(): string
    {
        return 'TL' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate license fees
     */
    private function calculateLicenseFees(array $applicationData): array
    {
        $tradeType = $this->tradeTypes[$applicationData['trade_type']];
        $licenseClass = $applicationData['license_class'];

        // Base fees by license class
        $baseFees = [
            'apprentice' => 100.00,
            'journeyman' => 250.00,
            'master' => 500.00,
            'specialist' => 750.00
        ];

        $applicationFee = $baseFees[$licenseClass] ?? 250.00;

        // Bond amount (if required)
        $bondAmount = $tradeType['bond_required'] ? 10000.00 : 0.00;

        return [
            'application_fee' => $applicationFee,
            'bond_amount' => $bondAmount
        ];
    }

    /**
     * Calculate renewal fee
     */
    private function calculateRenewalFee(array $license): float
    {
        $tradeType = $this->tradeTypes[$license['trade_type']];

        // Renewal fee is typically 50% of application fee
        $fees = $this->calculateLicenseFees([
            'trade_type' => $license['trade_type'],
            'license_class' => $license['license_class']
        ]);

        return $fees['application_fee'] * 0.5;
    }

    /**
     * Check if license can be renewed
     */
    private function canRenewLicense(array $license): bool
    {
        $currentDate = date('Y-m-d');
        $expiryDate = $license['expiry_date'];

        // Allow renewal up to 90 days after expiry
        $renewalDeadline = date('Y-m-d', strtotime($expiryDate . ' +90 days'));

        return $currentDate <= $renewalDeadline && !in_array($license['status'], ['revoked', 'suspended']);
    }

    /**
     * Check continuing education compliance
     */
    private function checkCeCompliance(string $licenseNumber): array
    {
        $license = $this->getTradeLicense($licenseNumber);
        if (!$license) {
            return ['compliant' => false, 'error' => 'License not found'];
        }

        $tradeType = $this->tradeTypes[$license['trade_type']];
        if (!$tradeType['continuing_education_required']) {
            return ['compliant' => true];
        }

        // Get CE hours for the current year
        $currentYear = date('Y');
        $ceHours = $this->getCeHoursForYear($license['id'], $currentYear);

        $requiredHours = $tradeType['education_hours_per_year'];

        return [
            'compliant' => $ceHours >= $requiredHours,
            'required_hours' => $requiredHours,
            'completed_hours' => $ceHours,
            'deficit' => max(0, $requiredHours - $ceHours)
        ];
    }

    /**
     * Get CE hours for a specific year
     */
    private function getCeHoursForYear(int $licenseId, int $year): float
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT SUM(credit_hours) as total_hours FROM continuing_education
                    WHERE license_id = ? AND YEAR(completion_date) = ? AND verification_status = 'verified'";

            $result = $db->fetch($sql, [$licenseId, $year]);

            return (float) ($result['total_hours'] ?? 0);
        } catch (\Exception $e) {
            error_log("Error getting CE hours: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Placeholder methods (would be implemented with actual database operations)
     */
    private function saveLicenseApplication(array $application): bool { return true; }
    private function startLicenseWorkflow(string $licenseNumber): bool { return true; }
    private function sendNotification(string $type, ?int $userId, array $data): bool { return true; }
    private function getTradeLicense(string $licenseNumber): ?array { return null; }
    private function updateLicenseStatus(string $licenseNumber, string $status, array $additionalData = []): bool { return true; }
    private function advanceWorkflow(string $licenseNumber, string $step): bool { return true; }
    private function updateLicense(string $licenseNumber, array $data): bool { return true; }
    private function saveCertification(array $certification): bool { return true; }
    private function saveContinuingEducation(array $ceRecord): bool { return true; }
    private function saveDisciplinaryAction(array $disciplinary): bool { return true; }
    private function saveRenewalRecord(array $renewal): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_applications' => 0, // Would query database
            'approved_licenses' => 0,
            'pending_applications' => 0,
            'active_licenses' => 0,
            'expired_licenses' => 0,
            'suspended_licenses' => 0,
            'revoked_licenses' => 0,
            'total_certifications' => 0,
            'ce_compliance_rate' => 0.0,
            'disciplinary_actions' => 0
        ];
    }
}

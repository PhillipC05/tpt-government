<?php
/**
 * TPT Government Platform - Social Services Module
 *
 * Comprehensive social services management system supporting benefit applications,
 * case management workflows, document verification, payment processing,
 * eligibility assessment, and appeal process management
 */

namespace Modules\SocialServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;
use Core\AdvancedAnalytics;

class SocialServicesModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Social Services',
        'version' => '2.1.0',
        'description' => 'Comprehensive social services management and benefit administration system',
        'author' => 'TPT Government Platform',
        'category' => 'citizen_services',
        'dependencies' => ['database', 'workflow', 'payment', 'notification', 'analytics']
    ];

    /**
     * Module dependencies
     */
    protected array $dependencies = [
        ['name' => 'Database', 'version' => '>=1.0.0'],
        ['name' => 'WorkflowEngine', 'version' => '>=1.0.0'],
        ['name' => 'PaymentGateway', 'version' => '>=1.0.0'],
        ['name' => 'NotificationManager', 'version' => '>=1.0.0'],
        ['name' => 'AdvancedAnalytics', 'version' => '>=1.0.0']
    ];

    /**
     * Module permissions
     */
    protected array $permissions = [
        'social.view' => 'View social services information and applications',
        'social.apply' => 'Apply for social services and benefits',
        'social.assess' => 'Assess eligibility for social services',
        'social.approve' => 'Approve or reject social service applications',
        'social.pay' => 'Process social service payments',
        'social.case_manage' => 'Manage social service cases',
        'social.report' => 'Generate social services reports and analytics',
        'social.admin' => 'Administrative social services functions'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'benefit_applications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'benefit_type' => "ENUM('unemployment','disability','pension','family','housing','medical','education','other') NOT NULL",
            'application_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'status' => "ENUM('draft','submitted','under_review','approved','rejected','appealed','closed') DEFAULT 'draft'",
            'priority_level' => "ENUM('low','medium','high','urgent') DEFAULT 'medium'",
            'eligibility_score' => 'DECIMAL(5,2)',
            'monthly_amount' => 'DECIMAL(10,2)',
            'start_date' => 'DATE',
            'end_date' => 'DATE',
            'application_data' => 'JSON',
            'supporting_documents' => 'JSON',
            'assessment_officer' => 'INT NULL',
            'approval_officer' => 'INT NULL',
            'rejection_reason' => 'TEXT',
            'appeal_deadline' => 'DATE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'benefit_payments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'payment_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'payment_date' => 'DATE NOT NULL',
            'payment_period_start' => 'DATE NOT NULL',
            'payment_period_end' => 'DATE NOT NULL',
            'gross_amount' => 'DECIMAL(10,2) NOT NULL',
            'deductions' => 'DECIMAL(8,2) DEFAULT 0.00',
            'net_amount' => 'DECIMAL(10,2) NOT NULL',
            'payment_method' => "ENUM('bank_transfer','direct_deposit','cheque','card') DEFAULT 'bank_transfer'",
            'payment_status' => "ENUM('pending','processed','failed','cancelled') DEFAULT 'pending'",
            'bank_reference' => 'VARCHAR(50)',
            'processed_by' => 'INT NOT NULL',
            'processed_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'reconciliation_status' => "ENUM('unreconciled','reconciled','disputed') DEFAULT 'unreconciled'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'eligibility_assessments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'assessment_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'assessment_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'assessor_id' => 'INT NOT NULL',
            'assessment_type' => "ENUM('initial','review','appeal','supplementary') DEFAULT 'initial'",
            'income_details' => 'JSON',
            'asset_details' => 'JSON',
            'family_details' => 'JSON',
            'medical_details' => 'JSON',
            'employment_details' => 'JSON',
            'housing_details' => 'JSON',
            'eligibility_criteria' => 'JSON',
            'assessment_score' => 'DECIMAL(5,2)',
            'eligibility_status' => "ENUM('eligible','ineligible','conditional','under_review') NOT NULL",
            'recommendation' => 'TEXT',
            'confidence_level' => "ENUM('high','medium','low') DEFAULT 'medium'",
            'review_required' => 'BOOLEAN DEFAULT FALSE',
            'supervisor_review' => 'BOOLEAN DEFAULT FALSE',
            'supervisor_id' => 'INT NULL',
            'supervisor_notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'benefit_appeals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'appeal_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'application_id' => 'INT NOT NULL',
            'person_id' => 'INT NOT NULL',
            'appeal_type' => "ENUM('eligibility','amount','termination','other') NOT NULL",
            'appeal_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'grounds_for_appeal' => 'TEXT NOT NULL',
            'additional_evidence' => 'JSON',
            'status' => "ENUM('lodged','under_review','hearing_scheduled','decided','withdrawn') DEFAULT 'lodged'",
            'hearing_date' => 'DATE NULL',
            'hearing_officer' => 'INT NULL',
            'decision' => 'TEXT',
            'decision_date' => 'DATE NULL',
            'decision_officer' => 'INT NULL',
            'appeal_outcome' => "ENUM('upheld','partially_upheld','dismissed','remitted') NULL",
            'new_eligibility_score' => 'DECIMAL(5,2)',
            'new_monthly_amount' => 'DECIMAL(10,2)',
            'processing_officer' => 'INT NULL',
            'processing_notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'case_management' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'case_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'case_type' => "ENUM('benefit','support','intervention','monitoring','other') NOT NULL",
            'case_status' => "ENUM('active','inactive','closed','transferred') DEFAULT 'active'",
            'priority' => "ENUM('low','medium','high','critical') DEFAULT 'medium'",
            'case_manager' => 'INT NOT NULL',
            'opened_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'closed_date' => 'DATETIME NULL',
            'target_date' => 'DATE',
            'case_summary' => 'TEXT',
            'goals_objectives' => 'JSON',
            'action_plan' => 'JSON',
            'progress_notes' => 'JSON',
            'referrals' => 'JSON',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATE NULL',
            'escalation_level' => 'INT DEFAULT 0',
            'supervisor_id' => 'INT NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'case_notes' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'case_id' => 'INT NOT NULL',
            'note_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'author_id' => 'INT NOT NULL',
            'note_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'note_type' => "ENUM('progress','assessment','meeting','phone_call','email','other') NOT NULL",
            'subject' => 'VARCHAR(255) NOT NULL',
            'content' => 'TEXT NOT NULL',
            'confidential' => 'BOOLEAN DEFAULT FALSE',
            'attachments' => 'JSON',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATE NULL',
            'escalation_required' => 'BOOLEAN DEFAULT FALSE',
            'escalation_reason' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'benefit_fraud_alerts' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'alert_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'application_id' => 'INT',
            'alert_type' => "ENUM('income_discrepancy','address_mismatch','duplicate_application','unusual_activity','other') NOT NULL",
            'severity' => "ENUM('low','medium','high','critical') DEFAULT 'medium'",
            'description' => 'TEXT NOT NULL',
            'detected_data' => 'JSON',
            'expected_data' => 'JSON',
            'investigation_status' => "ENUM('open','investigating','resolved','closed') DEFAULT 'open'",
            'investigator_id' => 'INT NULL',
            'investigation_notes' => 'TEXT',
            'resolution' => 'TEXT',
            'false_positive' => 'BOOLEAN DEFAULT FALSE',
            'detected_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'resolved_at' => 'DATETIME NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'social_service_providers' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'provider_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'provider_name' => 'VARCHAR(255) NOT NULL',
            'provider_type' => "ENUM('government','ngo','private','community') NOT NULL",
            'services_offered' => 'JSON',
            'contact_details' => 'JSON',
            'service_areas' => 'JSON',
            'accreditation_status' => "ENUM('accredited','pending','suspended','revoked') DEFAULT 'pending'",
            'accreditation_date' => 'DATE',
            'contract_start_date' => 'DATE',
            'contract_end_date' => 'DATE',
            'performance_rating' => 'DECIMAL(3,2)',
            'active_clients' => 'INT DEFAULT 0',
            'total_referrals' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'referral_tracking' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'referral_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'referring_officer' => 'INT NOT NULL',
            'referring_service' => 'VARCHAR(100) NOT NULL',
            'receiving_provider' => 'INT NOT NULL',
            'receiving_service' => 'VARCHAR(100) NOT NULL',
            'referral_reason' => 'TEXT NOT NULL',
            'urgency_level' => "ENUM('routine','urgent','emergency') DEFAULT 'routine'",
            'referral_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'status' => "ENUM('pending','accepted','declined','completed','follow_up') DEFAULT 'pending'",
            'response_date' => 'DATETIME NULL',
            'outcome' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATE NULL',
            'feedback' => 'TEXT',
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
            'path' => '/api/social/applications',
            'handler' => 'getBenefitApplications',
            'auth' => true,
            'permissions' => ['social.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/applications',
            'handler' => 'submitBenefitApplication',
            'auth' => true,
            'permissions' => ['social.apply']
        ],
        [
            'method' => 'GET',
            'path' => '/api/social/applications/{id}',
            'handler' => 'getBenefitApplication',
            'auth' => true,
            'permissions' => ['social.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/social/applications/{id}',
            'handler' => 'updateBenefitApplication',
            'auth' => true,
            'permissions' => ['social.admin']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/applications/{id}/assess',
            'handler' => 'assessEligibility',
            'auth' => true,
            'permissions' => ['social.assess']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/applications/{id}/appeal',
            'handler' => 'submitAppeal',
            'auth' => true,
            'permissions' => ['social.apply']
        ],
        [
            'method' => 'GET',
            'path' => '/api/social/appeals',
            'handler' => 'getAppeals',
            'auth' => true,
            'permissions' => ['social.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/payments',
            'handler' => 'processPayment',
            'auth' => true,
            'permissions' => ['social.pay']
        ],
        [
            'method' => 'GET',
            'path' => '/api/social/payments',
            'handler' => 'getPayments',
            'auth' => true,
            'permissions' => ['social.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/cases',
            'handler' => 'createCase',
            'auth' => true,
            'permissions' => ['social.case_manage']
        ],
        [
            'method' => 'GET',
            'path' => '/api/social/cases',
            'handler' => 'getCases',
            'auth' => true,
            'permissions' => ['social.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/cases/{id}/notes',
            'handler' => 'addCaseNote',
            'auth' => true,
            'permissions' => ['social.case_manage']
        ],
        [
            'method' => 'POST',
            'path' => '/api/social/referrals',
            'handler' => 'createReferral',
            'auth' => true,
            'permissions' => ['social.case_manage']
        ],
        [
            'method' => 'GET',
            'path' => '/api/social/providers',
            'handler' => 'getServiceProviders',
            'auth' => true,
            'permissions' => ['social.view']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'benefit_application_process' => [
            'name' => 'Benefit Application Process',
            'description' => 'Workflow for processing benefit applications from submission to approval',
            'steps' => [
                'submitted' => ['name' => 'Application Submitted', 'next' => 'initial_review'],
                'initial_review' => ['name' => 'Initial Review', 'next' => 'document_verification'],
                'document_verification' => ['name' => 'Document Verification', 'next' => 'eligibility_assessment'],
                'eligibility_assessment' => ['name' => 'Eligibility Assessment', 'next' => 'supervisor_review'],
                'supervisor_review' => ['name' => 'Supervisor Review', 'next' => ['approved', 'rejected', 'additional_info']],
                'additional_info' => ['name' => 'Additional Information Required', 'next' => 'eligibility_assessment'],
                'approved' => ['name' => 'Application Approved', 'next' => 'payment_setup'],
                'payment_setup' => ['name' => 'Payment Setup', 'next' => 'first_payment'],
                'first_payment' => ['name' => 'First Payment Processed', 'next' => 'active'],
                'active' => ['name' => 'Benefit Active', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null]
            ]
        ],
        'appeal_process' => [
            'name' => 'Benefit Appeal Process',
            'description' => 'Workflow for handling benefit application appeals',
            'steps' => [
                'lodged' => ['name' => 'Appeal Lodged', 'next' => 'initial_review'],
                'initial_review' => ['name' => 'Initial Review', 'next' => 'evidence_review'],
                'evidence_review' => ['name' => 'Evidence Review', 'next' => 'hearing_preparation'],
                'hearing_preparation' => ['name' => 'Hearing Preparation', 'next' => 'hearing_scheduled'],
                'hearing_scheduled' => ['name' => 'Hearing Scheduled', 'next' => 'hearing_held'],
                'hearing_held' => ['name' => 'Hearing Held', 'next' => 'decision_made'],
                'decision_made' => ['name' => 'Decision Made', 'next' => ['appeal_upheld', 'appeal_dismissed']],
                'appeal_upheld' => ['name' => 'Appeal Upheld', 'next' => 'benefit_adjusted'],
                'benefit_adjusted' => ['name' => 'Benefit Adjusted', 'next' => 'appeal_closed'],
                'appeal_dismissed' => ['name' => 'Appeal Dismissed', 'next' => 'appeal_closed'],
                'appeal_closed' => ['name' => 'Appeal Closed', 'next' => null]
            ]
        ],
        'case_management_process' => [
            'name' => 'Case Management Process',
            'description' => 'Workflow for managing social service cases',
            'steps' => [
                'opened' => ['name' => 'Case Opened', 'next' => 'assessment'],
                'assessment' => ['name' => 'Initial Assessment', 'next' => 'plan_development'],
                'plan_development' => ['name' => 'Plan Development', 'next' => 'intervention'],
                'intervention' => ['name' => 'Intervention', 'next' => 'monitoring'],
                'monitoring' => ['name' => 'Monitoring', 'next' => ['progress', 'escalation', 'closure']],
                'progress' => ['name' => 'Progress Made', 'next' => 'monitoring'],
                'escalation' => ['name' => 'Case Escalated', 'next' => 'supervisor_review'],
                'supervisor_review' => ['name' => 'Supervisor Review', 'next' => 'monitoring'],
                'closure' => ['name' => 'Case Closed', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'benefit_application_form' => [
            'name' => 'Benefit Application Form',
            'fields' => [
                'benefit_type' => ['type' => 'select', 'required' => true, 'label' => 'Type of Benefit'],
                'application_reason' => ['type' => 'textarea', 'required' => true, 'label' => 'Reason for Application'],
                'priority_level' => ['type' => 'select', 'required' => false, 'label' => 'Priority Level'],
                'preferred_contact_method' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Contact Method']
            ],
            'sections' => [
                'personal_details' => ['title' => 'Personal Details', 'required' => true],
                'income_details' => ['title' => 'Income Information', 'required' => true],
                'asset_details' => ['title' => 'Assets and Savings', 'required' => true],
                'family_details' => ['title' => 'Family Information', 'required' => true],
                'housing_details' => ['title' => 'Housing Information', 'required' => true],
                'medical_details' => ['title' => 'Medical Information', 'required' => false]
            ],
            'documents' => [
                'identification' => ['required' => true, 'label' => 'Identification Documents'],
                'income_proof' => ['required' => true, 'label' => 'Proof of Income'],
                'bank_details' => ['required' => true, 'label' => 'Bank Account Details'],
                'medical_reports' => ['required' => false, 'label' => 'Medical Reports'],
                'supporting_evidence' => ['required' => false, 'label' => 'Supporting Evidence']
            ]
        ],
        'appeal_form' => [
            'name' => 'Benefit Appeal Form',
            'fields' => [
                'appeal_type' => ['type' => 'select', 'required' => true, 'label' => 'Type of Appeal'],
                'grounds_for_appeal' => ['type' => 'textarea', 'required' => true, 'label' => 'Grounds for Appeal'],
                'desired_outcome' => ['type' => 'textarea', 'required' => true, 'label' => 'Desired Outcome'],
                'additional_information' => ['type' => 'textarea', 'required' => false, 'label' => 'Additional Information']
            ],
            'documents' => [
                'appeal_evidence' => ['required' => false, 'label' => 'New Evidence'],
                'supporting_documents' => ['required' => false, 'label' => 'Supporting Documents'],
                'witness_statements' => ['required' => false, 'label' => 'Witness Statements']
            ]
        ],
        'case_note_form' => [
            'name' => 'Case Note Form',
            'fields' => [
                'note_type' => ['type' => 'select', 'required' => true, 'label' => 'Note Type'],
                'subject' => ['type' => 'text', 'required' => true, 'label' => 'Subject'],
                'content' => ['type' => 'textarea', 'required' => true, 'label' => 'Note Content'],
                'confidential' => ['type' => 'checkbox', 'required' => false, 'label' => 'Mark as Confidential'],
                'follow_up_required' => ['type' => 'checkbox', 'required' => false, 'label' => 'Follow-up Required'],
                'follow_up_date' => ['type' => 'date', 'required' => false, 'label' => 'Follow-up Date']
            ],
            'documents' => [
                'attachments' => ['required' => false, 'label' => 'Attachments']
            ]
        ],
        'referral_form' => [
            'name' => 'Service Referral Form',
            'fields' => [
                'receiving_provider' => ['type' => 'select', 'required' => true, 'label' => 'Receiving Provider'],
                'receiving_service' => ['type' => 'select', 'required' => true, 'label' => 'Receiving Service'],
                'referral_reason' => ['type' => 'textarea', 'required' => true, 'label' => 'Reason for Referral'],
                'urgency_level' => ['type' => 'select', 'required' => true, 'label' => 'Urgency Level'],
                'additional_notes' => ['type' => 'textarea', 'required' => false, 'label' => 'Additional Notes']
            ],
            'documents' => [
                'referral_documents' => ['required' => false, 'label' => 'Referral Documents'],
                'assessment_reports' => ['required' => false, 'label' => 'Assessment Reports']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'benefit_application_report' => [
            'name' => 'Benefit Application Report',
            'description' => 'Applications by type, status, and processing time',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'benefit_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false],
                'priority_level' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'application_number', 'person_name', 'benefit_type',
                'status', 'application_date', 'processing_time', 'monthly_amount'
            ]
        ],
        'payment_distribution_report' => [
            'name' => 'Payment Distribution Report',
            'description' => 'Benefit payments by type, amount, and recipient',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'benefit_type' => ['type' => 'select', 'required' => false],
                'payment_status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'payment_date', 'benefit_type', 'recipient_count',
                'total_amount', 'average_amount', 'payment_method'
            ]
        ],
        'eligibility_assessment_report' => [
            'name' => 'Eligibility Assessment Report',
            'description' => 'Assessment outcomes and processing statistics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'assessment_type' => ['type' => 'select', 'required' => false],
                'eligibility_status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'assessment_date', 'assessor_name', 'assessment_type',
                'eligibility_status', 'assessment_score', 'processing_time'
            ]
        ],
        'appeal_statistics_report' => [
            'name' => 'Appeal Statistics Report',
            'description' => 'Appeal lodgements, outcomes, and processing times',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'appeal_type' => ['type' => 'select', 'required' => false],
                'appeal_outcome' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'appeal_date', 'appeal_type', 'status',
                'decision_date', 'appeal_outcome', 'processing_time'
            ]
        ],
        'case_management_report' => [
            'name' => 'Case Management Report',
            'description' => 'Case statistics, outcomes, and performance metrics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'case_type' => ['type' => 'select', 'required' => false],
                'case_status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'case_number', 'case_type', 'case_manager',
                'opened_date', 'status', 'duration', 'outcome'
            ]
        ],
        'fraud_detection_report' => [
            'name' => 'Fraud Detection Report',
            'description' => 'Fraud alerts, investigations, and outcomes',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'alert_type' => ['type' => 'select', 'required' => false],
                'severity' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'alert_date', 'alert_type', 'severity',
                'investigation_status', 'false_positive', 'resolution'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'application_submitted' => [
            'name' => 'Application Submitted',
            'template' => 'Your benefit application {application_number} has been submitted successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_created']
        ],
        'application_approved' => [
            'name' => 'Application Approved',
            'template' => 'Your benefit application {application_number} has been approved. Monthly amount: ${monthly_amount}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_approved']
        ],
        'application_rejected' => [
            'name' => 'Application Rejected',
            'template' => 'Your benefit application {application_number} has been rejected. Reason: {rejection_reason}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_rejected']
        ],
        'payment_processed' => [
            'name' => 'Payment Processed',
            'template' => 'Your benefit payment of ${net_amount} has been processed for {payment_period}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['payment_processed']
        ],
        'appeal_lodged' => [
            'name' => 'Appeal Lodged',
            'template' => 'Your appeal {appeal_number} has been lodged successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appeal_created']
        ],
        'appeal_decision' => [
            'name' => 'Appeal Decision',
            'template' => 'A decision has been made on your appeal {appeal_number}. Outcome: {appeal_outcome}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appeal_decided']
        ],
        'case_assigned' => [
            'name' => 'Case Assigned',
            'template' => 'You have been assigned as case manager for case {case_number}.',
            'channels' => ['email', 'in_app'],
            'triggers' => ['case_assigned']
        ],
        'follow_up_required' => [
            'name' => 'Follow-up Required',
            'template' => 'Follow-up is required for your case {case_number} on {follow_up_date}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['follow_up_due']
        ],
        'document_required' => [
            'name' => 'Additional Documents Required',
            'template' => 'Additional documents are required for your application {application_number}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['additional_info_required']
        ]
    ];

    /**
     * Benefit types configuration
     */
    private array $benefitTypes = [];

    /**
     * Eligibility criteria configuration
     */
    private array $eligibilityCriteria = [];

    /**
     * Fraud detection rules
     */
    private array $fraudRules = [];

    /**
     * Payment schedules
     */
    private array $paymentSchedules = [];

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
            'auto_approval_threshold' => 0.85,
            'appeal_deadline_days' => 30,
            'payment_processing_day' => 25,
            'case_review_interval_days' => 90,
            'fraud_detection_enabled' => true,
            'maximum_monthly_benefits' => [
                'unemployment' => 1500.00,
                'disability' => 2000.00,
                'pension' => 1200.00,
                'family' => 800.00,
                'housing' => 600.00,
                'medical' => 500.00
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
        $this->initializeBenefitTypes();
        $this->initializeEligibilityCriteria();
        $this->initializeFraudRules();
        $this->initializePaymentSchedules();
    }

    /**
     * Initialize benefit types
     */
    private function initializeBenefitTypes(): void
    {
        $this->benefitTypes = [
            'unemployment' => [
                'name' => 'Unemployment Benefit',
                'description' => 'Financial support for unemployed individuals',
                'eligibility_criteria' => ['employment_status', 'income_level', 'residence'],
                'maximum_duration' => 365, // days
                'requires_assessment' => true
            ],
            'disability' => [
                'name' => 'Disability Support',
                'description' => 'Support for individuals with disabilities',
                'eligibility_criteria' => ['medical_assessment', 'disability_level', 'income_level'],
                'maximum_duration' => null, // Ongoing
                'requires_assessment' => true
            ],
            'pension' => [
                'name' => 'Pension Support',
                'description' => 'Financial support for pensioners',
                'eligibility_criteria' => ['age', 'contribution_history', 'income_level'],
                'maximum_duration' => null, // Ongoing
                'requires_assessment' => false
            ],
            'family' => [
                'name' => 'Family Support',
                'description' => 'Support for families with children',
                'eligibility_criteria' => ['family_size', 'income_level', 'children'],
                'maximum_duration' => 730, // 2 years
                'requires_assessment' => true
            ],
            'housing' => [
                'name' => 'Housing Assistance',
                'description' => 'Support for housing costs',
                'eligibility_criteria' => ['income_level', 'housing_costs', 'family_size'],
                'maximum_duration' => 1095, // 3 years
                'requires_assessment' => true
            ],
            'medical' => [
                'name' => 'Medical Assistance',
                'description' => 'Support for medical expenses',
                'eligibility_criteria' => ['medical_condition', 'income_level', 'existing_coverage'],
                'maximum_duration' => 365, // 1 year
                'requires_assessment' => true
            ]
        ];
    }

    /**
     * Initialize eligibility criteria
     */
    private function initializeEligibilityCriteria(): void
    {
        $this->eligibilityCriteria = [
            'income_level' => [
                'type' => 'threshold',
                'threshold' => 30000.00, // Annual income threshold
                'operator' => '<=',
                'weight' => 0.4
            ],
            'family_size' => [
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'weight' => 0.2
            ],
            'age' => [
                'type' => 'threshold',
                'threshold' => 65,
                'operator' => '>=',
                'weight' => 0.3
            ],
            'medical_condition' => [
                'type' => 'boolean',
                'required' => true,
                'weight' => 0.5
            ],
            'employment_status' => [
                'type' => 'enum',
                'values' => ['unemployed', 'underemployed'],
                'weight' => 0.3
            ]
        ];
    }

    /**
     * Initialize fraud detection rules
     */
    private function initializeFraudRules(): void
    {
        $this->fraudRules = [
            'income_discrepancy' => [
                'name' => 'Income Discrepancy',
                'description' => 'Declared income does not match external records',
                'severity' => 'high',
                'threshold' => 0.2 // 20% discrepancy
            ],
            'address_mismatch' => [
                'name' => 'Address Mismatch',
                'description' => 'Address information inconsistent across records',
                'severity' => 'medium',
                'threshold' => 0.5 // 50% confidence of mismatch
            ],
            'duplicate_application' => [
                'name' => 'Duplicate Application',
                'description' => 'Multiple applications from same person',
                'severity' => 'high',
                'threshold' => 0.8 // 80% confidence of duplication
            ],
            'unusual_activity' => [
                'name' => 'Unusual Activity',
                'description' => 'Activity patterns outside normal parameters',
                'severity' => 'medium',
                'threshold' => 0.7 // 70% confidence of unusual activity
            ]
        ];
    }

    /**
     * Initialize payment schedules
     */
    private function initializePaymentSchedules(): void
    {
        $this->paymentSchedules = [
            'monthly' => [
                'name' => 'Monthly Payment',
                'frequency' => 'monthly',
                'processing_day' => 25,
                'advance_payment' => false
            ],
            'fortnightly' => [
                'name' => 'Fortnightly Payment',
                'frequency' => 'fortnightly',
                'processing_day' => 15,
                'advance_payment' => false
            ],
            'weekly' => [
                'name' => 'Weekly Payment',
                'frequency' => 'weekly',
                'processing_day' => 5,
                'advance_payment' => false
            ]
        ];
    }

    /**
     * Submit benefit application (API handler)
     */
    public function submitBenefitApplication(array $applicationData): array
    {
        try {
            // Generate application number
            $applicationNumber = $this->generateApplicationNumber();

            // Validate application data
            $validation = $this->validateApplicationData($applicationData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ];
            }

            // Create application record
            $application = [
                'application_number' => $applicationNumber,
                'person_id' => $applicationData['person_id'],
                'benefit_type' => $applicationData['benefit_type'],
                'application_date' => date('Y-m-d H:i:s'),
                'status' => 'submitted',
                'priority_level' => $applicationData['priority_level'] ?? 'medium',
                'application_data' => $applicationData,
                'supporting_documents' => $applicationData['supporting_documents'] ?? []
            ];

            // Save to database
            $this->saveBenefitApplication($application);

            // Start application workflow
            $this->startApplicationWorkflow($applicationNumber);

            // Send notification
            $this->sendNotification('application_submitted', $applicationData['person_id'], [
                'application_number' => $applicationNumber
            ]);

            return [
                'success' => true,
                'application_number' => $applicationNumber,
                'message' => 'Benefit application submitted successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error submitting benefit application: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to submit benefit application'
            ];
        }
    }

    /**
     * Assess eligibility (API handler)
     */
    public function assessEligibility(string $applicationId, array $assessmentData): array
    {
        try {
            $application = $this->getBenefitApplication($applicationId);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            // Generate assessment number
            $assessmentNumber = $this->generateAssessmentNumber();

            // Perform eligibility assessment
            $assessmentResult = $this->performEligibilityAssessment($application, $assessmentData);

            // Create assessment record
            $assessment = [
                'application_id' => $application['id'],
                'assessment_number' => $assessmentNumber,
                'assessment_date' => date('Y-m-d H:i:s'),
                'assessor_id' => $assessmentData['assessor_id'],
                'assessment_type' => $assessmentData['assessment_type'] ?? 'initial',
                'income_details' => $assessmentData['income_details'] ?? [],
                'asset_details' => $assessmentData['asset_details'] ?? [],
                'family_details' => $assessmentData['family_details'] ?? [],
                'medical_details' => $assessmentData['medical_details'] ?? [],
                'employment_details' => $assessmentData['employment_details'] ?? [],
                'housing_details' => $assessmentData['housing_details'] ?? [],
                'eligibility_criteria' => $assessmentResult['criteria'],
                'assessment_score' => $assessmentResult['score'],
                'eligibility_status' => $assessmentResult['status'],
                'recommendation' => $assessmentResult['recommendation'],
                'confidence_level' => $assessmentResult['confidence']
            ];

            // Save assessment
            $this->saveEligibilityAssessment($assessment);

            // Update application
            $updateData = ['eligibility_score' => $assessmentResult['score']];
            if ($assessmentResult['status'] === 'eligible' && $assessmentResult['score'] >= $this->config['auto_approval_threshold']) {
                $updateData['status'] = 'approved';
                $updateData['monthly_amount'] = $this->calculateBenefitAmount($application, $assessmentResult);
                $updateData['start_date'] = date('Y-m-d');
            } elseif ($assessmentResult['status'] === 'ineligible') {
                $updateData['status'] = 'rejected';
                $updateData['rejection_reason'] = $assessmentResult['recommendation'];
            }

            $this->updateBenefitApplication($application['id'], $updateData);

            // Send notifications
            if ($assessmentResult['status'] === 'eligible') {
                $this->sendNotification('application_approved', $application['person_id'], [
                    'application_number' => $application['application_number'],
                    'monthly_amount' => $updateData['monthly_amount'] ?? 0
                ]);
            } elseif ($assessmentResult['status'] === 'ineligible') {
                $this->sendNotification('application_rejected', $application['person_id'], [
                    'application_number' => $application['application_number'],
                    'rejection_reason' => $assessmentResult['recommendation']
                ]);
            }

            return [
                'success' => true,
                'assessment_number' => $assessmentNumber,
                'eligibility_status' => $assessmentResult['status'],
                'assessment_score' => $assessmentResult['score'],
                'recommendation' => $assessmentResult['recommendation']
            ];
        } catch (\Exception $e) {
            error_log("Error assessing eligibility: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to assess eligibility'
            ];
        }
    }

    /**
     * Process payment (API handler)
     */
    public function processPayment(array $paymentData): array
    {
        try {
            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber();

            // Calculate payment details
            $application = $this->getBenefitApplication($paymentData['application_id']);
            $paymentAmount = $this->calculatePaymentAmount($application, $paymentData);

            // Create payment record
            $payment = [
                'application_id' => $paymentData['application_id'],
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentData['payment_date'] ?? date('Y-m-d'),
                'payment_period_start' => $paymentData['period_start'],
                'payment_period_end' => $paymentData['period_end'],
                'gross_amount' => $paymentAmount['gross'],
                'deductions' => $paymentAmount['deductions'],
                'net_amount' => $paymentAmount['net'],
                'payment_method' => $paymentData['payment_method'] ?? 'bank_transfer',
                'processed_by' => $paymentData['processed_by']
            ];

            // Save payment
            $this->saveBenefitPayment($payment);

            // Process payment through gateway
            $paymentResult = $this->processPaymentThroughGateway($payment);

            // Update payment status
            $this->updateBenefitPayment($payment['id'], ['payment_status' => 'processed']);

            // Send notification
            $this->sendNotification('payment_processed', $application['person_id'], [
                'net_amount' => $paymentAmount['net'],
                'payment_period' => $paymentData['period_start'] . ' to ' . $paymentData['period_end']
            ]);

            return [
                'success' => true,
                'payment_number' => $paymentNumber,
                'net_amount' => $paymentAmount['net'],
                'message' => 'Payment processed successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error processing payment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to process payment'
            ];
        }
    }

    /**
     * Submit appeal (API handler)
     */
    public function submitAppeal(string $applicationId, array $appealData): array
    {
        try {
            $application = $this->getBenefitApplication($applicationId);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            // Generate appeal number
            $appealNumber = $this->generateAppealNumber();

            // Create appeal record
            $appeal = [
                'appeal_number' => $appealNumber,
                'application_id' => $application['id'],
                'person_id' => $application['person_id'],
                'appeal_type' => $appealData['appeal_type'],
                'grounds_for_appeal' => $appealData['grounds_for_appeal'],
                'additional_evidence' => $appealData['additional_evidence'] ?? []
            ];

            // Save appeal
            $this->saveBenefitAppeal($appeal);

            // Start appeal workflow
            $this->startAppealWorkflow($appealNumber);

            // Send notification
            $this->sendNotification('appeal_lodged', $application['person_id'], [
                'appeal_number' => $appealNumber
            ]);

            return [
                'success' => true,
                'appeal_number' => $appealNumber,
                'message' => 'Appeal submitted successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error submitting appeal: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to submit appeal'
            ];
        }
    }

    /**
     * Create case (API handler)
     */
    public function createCase(array $caseData): array
    {
        try {
            // Generate case number
            $caseNumber = $this->generateCaseNumber();

            // Create case record
            $case = [
                'case_number' => $caseNumber,
                'person_id' => $caseData['person_id'],
                'case_type' => $caseData['case_type'],
                'case_manager' => $caseData['case_manager'],
                'priority' => $caseData['priority'] ?? 'medium',
                'case_summary' => $caseData['case_summary'],
                'goals_objectives' => $caseData['goals_objectives'] ?? [],
                'action_plan' => $caseData['action_plan'] ?? []
            ];

            // Save case
            $this->saveCase($case);

            // Start case workflow
            $this->startCaseWorkflow($caseNumber);

            // Send notification
            $this->sendNotification('case_assigned', $caseData['case_manager'], [
                'case_number' => $caseNumber
            ]);

            return [
                'success' => true,
                'case_number' => $caseNumber,
                'message' => 'Case created successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error creating case: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create case'
            ];
        }
    }

    /**
     * Add case note (API handler)
     */
    public function addCaseNote(string $caseId, array $noteData): array
    {
        try {
            $case = $this->getCase($caseId);

            if (!$case) {
                return [
                    'success' => false,
                    'error' => 'Case not found'
                ];
            }

            // Generate note number
            $noteNumber = $this->generateCaseNoteNumber();

            // Create note record
            $note = [
                'case_id' => $case['id'],
                'note_number' => $noteNumber,
                'author_id' => $noteData['author_id'],
                'note_type' => $noteData['note_type'],
                'subject' => $noteData['subject'],
                'content' => $noteData['content'],
                'confidential' => $noteData['confidential'] ?? false,
                'attachments' => $noteData['attachments'] ?? [],
                'follow_up_required' => $noteData['follow_up_required'] ?? false,
                'follow_up_date' => $noteData['follow_up_date'] ?? null
            ];

            // Save note
            $this->saveCaseNote($note);

            // Send follow-up notification if required
            if ($noteData['follow_up_required']) {
                $this->sendNotification('follow_up_required', $case['person_id'], [
                    'case_number' => $case['case_number'],
                    'follow_up_date' => $noteData['follow_up_date']
                ]);
            }

            return [
                'success' => true,
                'note_number' => $noteNumber,
                'message' => 'Case note added successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error adding case note: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to add case note'
            ];
        }
    }

    /**
     * Create referral (API handler)
     */
    public function createReferral(array $referralData): array
    {
        try {
            // Generate referral number
            $referralNumber = $this->generateReferralNumber();

            // Create referral record
            $referral = [
                'referral_number' => $referralNumber,
                'person_id' => $referralData['person_id'],
                'referring_officer' => $referralData['referring_officer'],
                'referring_service' => $referralData['referring_service'],
                'receiving_provider' => $referralData['receiving_provider'],
                'receiving_service' => $referralData['receiving_service'],
                'referral_reason' => $referralData['referral_reason'],
                'urgency_level' => $referralData['urgency_level'] ?? 'routine'
            ];

            // Save referral
            $this->saveReferral($referral);

            return [
                'success' => true,
                'referral_number' => $referralNumber,
                'message' => 'Referral created successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error creating referral: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create referral'
            ];
        }
    }

    /**
     * Get benefit applications (API handler)
     */
    public function getBenefitApplications(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM benefit_applications WHERE 1=1";
            $params = [];

            if (isset($filters['person_id'])) {
                $sql .= " AND person_id = ?";
                $params[] = $filters['person_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['benefit_type'])) {
                $sql .= " AND benefit_type = ?";
                $params[] = $filters['benefit_type'];
            }

            $sql .= " ORDER BY application_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['application_data'] = json_decode($result['application_data'], true);
                $result['supporting_documents'] = json_decode($result['supporting_documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting benefit applications: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve benefit applications'
            ];
        }
    }

    /**
     * Get benefit application (API handler)
     */
    public function getBenefitApplication(string $applicationId): ?array
    {
        try {
            $db = Database::getInstance();
            $sql = "SELECT * FROM benefit_applications WHERE application_number = ? OR id = ?";
            $application = $db->fetch($sql, [$applicationId, $applicationId]);

            if ($application) {
                $application['application_data'] = json_decode($application['application_data'], true);
                $application['supporting_documents'] = json_decode($application['supporting_documents'], true);
            }

            return $application;
        } catch (\Exception $e) {
            error_log("Error getting benefit application: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get payments (API handler)
     */
    public function getPayments(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM benefit_payments WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['payment_status'])) {
                $sql .= " AND payment_status = ?";
                $params[] = $filters['payment_status'];
            }

            $sql .= " ORDER BY payment_date DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting payments: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve payments'
            ];
        }
    }

    /**
     * Get appeals (API handler)
     */
    public function getAppeals(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM benefit_appeals WHERE 1=1";
            $params = [];

            if (isset($filters['person_id'])) {
                $sql .= " AND person_id = ?";
                $params[] = $filters['person_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['appeal_type'])) {
                $sql .= " AND appeal_type = ?";
                $params[] = $filters['appeal_type'];
            }

            $sql .= " ORDER BY appeal_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['additional_evidence'] = json_decode($result['additional_evidence'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting appeals: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve appeals'
            ];
        }
    }

    /**
     * Get cases (API handler)
     */
    public function getCases(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM case_management WHERE 1=1";
            $params = [];

            if (isset($filters['person_id'])) {
                $sql .= " AND person_id = ?";
                $params[] = $filters['person_id'];
            }

            if (isset($filters['case_manager'])) {
                $sql .= " AND case_manager = ?";
                $params[] = $filters['case_manager'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND case_status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY opened_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['goals_objectives'] = json_decode($result['goals_objectives'], true);
                $result['action_plan'] = json_decode($result['action_plan'], true);
                $result['progress_notes'] = json_decode($result['progress_notes'], true);
                $result['referrals'] = json_decode($result['referrals'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting cases: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve cases'
            ];
        }
    }

    /**
     * Get service providers (API handler)
     */
    public function getServiceProviders(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM social_service_providers WHERE 1=1";
            $params = [];

            if (isset($filters['provider_type'])) {
                $sql .= " AND provider_type = ?";
                $params[] = $filters['provider_type'];
            }

            if (isset($filters['accreditation_status'])) {
                $sql .= " AND accreditation_status = ?";
                $params[] = $filters['accreditation_status'];
            }

            $sql .= " ORDER BY provider_name";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['services_offered'] = json_decode($result['services_offered'], true);
                $result['contact_details'] = json_decode($result['contact_details'], true);
                $result['service_areas'] = json_decode($result['service_areas'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting service providers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve service providers'
            ];
        }
    }

    /**
     * Update benefit application (API handler)
     */
    public function updateBenefitApplication(string $applicationId, array $applicationData): array
    {
        try {
            $application = $this->getBenefitApplication($applicationId);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            $this->updateBenefitApplication($application['id'], $applicationData);

            return [
                'success' => true,
                'message' => 'Application updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating benefit application: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update application'
            ];
        }
    }

    /**
     * Validate application data
     */
    private function validateApplicationData(array $data): array
    {
        $errors = [];

        if (empty($data['person_id'])) {
            $errors[] = "Person ID is required";
        }

        if (empty($data['benefit_type']) || !isset($this->benefitTypes[$data['benefit_type']])) {
            $errors[] = "Valid benefit type is required";
        }

        if (empty($data['application_reason'])) {
            $errors[] = "Application reason is required";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Perform eligibility assessment
     */
    private function performEligibilityAssessment(array $application, array $assessmentData): array
    {
        $benefitType = $application['benefit_type'];
        $benefitConfig = $this->benefitTypes[$benefitType];

        $scores = [];
        $totalScore = 0;
        $totalWeight = 0;

        // Assess each eligibility criterion
        foreach ($benefitConfig['eligibility_criteria'] as $criterion) {
            if (isset($this->eligibilityCriteria[$criterion])) {
                $criterionConfig = $this->eligibilityCriteria[$criterion];
                $score = $this->assessCriterion($criterion, $assessmentData, $criterionConfig);
                $scores[$criterion] = $score;
                $totalScore += $score * $criterionConfig['weight'];
                $totalWeight += $criterionConfig['weight'];
            }
        }

        $finalScore = $totalWeight > 0 ? $totalScore / $totalWeight : 0;

        // Determine eligibility status
        $status = 'ineligible';
        $recommendation = 'Does not meet eligibility criteria';

        if ($finalScore >= 0.8) {
            $status = 'eligible';
            $recommendation = 'Meets all eligibility criteria';
        } elseif ($finalScore >= 0.6) {
            $status = 'conditional';
            $recommendation = 'May be eligible with additional information or conditions';
        }

        return [
            'score' => round($finalScore, 2),
            'status' => $status,
            'recommendation' => $recommendation,
            'confidence' => $finalScore >= 0.7 ? 'high' : ($finalScore >= 0.5 ? 'medium' : 'low'),
            'criteria' => $scores
        ];
    }

    /**
     * Assess individual eligibility criterion
     */
    private function assessCriterion(string $criterion, array $assessmentData, array $config): float
    {
        // Implementation would assess each criterion based on provided data
        // This is a simplified version
        switch ($config['type']) {
            case 'threshold':
                $value = $assessmentData[$criterion] ?? 0;
                $operator = $config['operator'];
                $threshold = $config['threshold'];

                if ($operator === '<=' && $value <= $threshold) {
                    return 1.0;
                } elseif ($operator === '>=' && $value >= $threshold) {
                    return 1.0;
                }
                return 0.0;

            case 'boolean':
                return ($assessmentData[$criterion] ?? false) ? 1.0 : 0.0;

            case 'range':
                $value = $assessmentData[$criterion] ?? 0;
                return ($value >= $config['min'] && $value <= $config['max']) ? 1.0 : 0.0;

            default:
                return 0.5; // Neutral score for unknown types
        }
    }

    /**
     * Calculate benefit amount
     */
    private function calculateBenefitAmount(array $application, array $assessmentResult): float
    {
        $benefitType = $application['benefit_type'];
        $maxAmount = $this->config['maximum_monthly_benefits'][$benefitType] ?? 1000.00;

        // Base amount on assessment score
        $baseAmount = $maxAmount * $assessmentResult['score'];

        // Apply any additional calculations based on family size, etc.
        $applicationData = json_decode($application['application_data'], true);
        $familySize = $applicationData['family_size'] ?? 1;

        // Simple family size adjustment
        $adjustment = min($familySize * 0.1, 0.5); // Max 50% increase
        $finalAmount = $baseAmount * (1 + $adjustment);

        return round(min($finalAmount, $maxAmount), 2);
    }

    /**
     * Calculate payment amount
     */
    private function calculatePaymentAmount(array $application, array $paymentData): array
    {
        $monthlyAmount = $application['monthly_amount'] ?? 0;

        // Calculate period length (simplified to monthly)
        $grossAmount = $monthlyAmount;

        // Calculate deductions (tax, etc.)
        $deductions = $grossAmount * 0.1; // 10% deduction for simplicity

        $netAmount = $grossAmount - $deductions;

        return [
            'gross' => round($grossAmount, 2),
            'deductions' => round($deductions, 2),
            'net' => round($netAmount, 2)
        ];
    }

    /**
     * Process payment through gateway
     */
    private function processPaymentThroughGateway(array $payment): array
    {
        // Implementation would integrate with payment gateway
        return [
            'success' => true,
            'transaction_id' => 'TXN_' . time(),
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate application number
     */
    private function generateApplicationNumber(): string
    {
        return 'APP' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate assessment number
     */
    private function generateAssessmentNumber(): string
    {
        return 'ASS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate payment number
     */
    private function generatePaymentNumber(): string
    {
        return 'PAY' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate appeal number
     */
    private function generateAppealNumber(): string
    {
        return 'APL' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate case number
     */
    private function generateCaseNumber(): string
    {
        return 'CASE' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate case note number
     */
    private function generateCaseNoteNumber(): string
    {
        return 'NOTE' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate referral number
     */
    private function generateReferralNumber(): string
    {
        return 'REF' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get case
     */
    private function getCase(string $caseId): ?array
    {
        try {
            $db = Database::getInstance();
            $sql = "SELECT * FROM case_management WHERE case_number = ? OR id = ?";
            $case = $db->fetch($sql, [$caseId, $caseId]);

            if ($case) {
                $case['goals_objectives'] = json_decode($case['goals_objectives'], true);
                $case['action_plan'] = json_decode($case['action_plan'], true);
                $case['progress_notes'] = json_decode($case['progress_notes'], true);
                $case['referrals'] = json_decode($case['referrals'], true);
            }

            return $case;
        } catch (\Exception $e) {
            error_log("Error getting case: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update benefit application
     */
    private function updateBenefitApplication(int $applicationId, array $data): bool
    {
        try {
            $db = Database::getInstance();

            // Handle JSON fields
            if (isset($data['application_data'])) {
                $data['application_data'] = json_encode($data['application_data']);
            }

            if (isset($data['supporting_documents'])) {
                $data['supporting_documents'] = json_encode($data['supporting_documents']);
            }

            $db->update('benefit_applications', $data, ['id' => $applicationId]);
            return true;
        } catch (\Exception $e) {
            error_log("Error updating benefit application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update benefit payment
     */
    private function updateBenefitPayment(int $paymentId, array $data): bool
    {
        try {
            $db = Database::getInstance();
            $db->update('benefit_payments', $data, ['id' => $paymentId]);
            return true;
        } catch (\Exception $e) {
            error_log("Error updating benefit payment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start application workflow
     */
    private function startApplicationWorkflow(string $applicationNumber): bool
    {
        // Implementation would start the workflow engine
        return true;
    }

    /**
     * Start appeal workflow
     */
    private function startAppealWorkflow(string $appealNumber): bool
    {
        // Implementation would start the workflow engine
        return true;
    }

    /**
     * Start case workflow
     */
    private function startCaseWorkflow(string $caseNumber): bool
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
    private function saveBenefitApplication(array $application): bool { return true; }
    private function saveEligibilityAssessment(array $assessment): bool { return true; }
    private function saveBenefitPayment(array $payment): bool { return true; }
    private function saveBenefitAppeal(array $appeal): bool { return true; }
    private function saveCase(array $case): bool { return true; }
    private function saveCaseNote(array $note): bool { return true; }
    private function saveReferral(array $referral): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_applications' => 0, // Would query database
            'approved_applications' => 0,
            'pending_assessments' => 0,
            'active_benefits' => 0,
            'total_payments_this_month' => 0.00,
            'average_processing_time' => 0,
            'appeal_success_rate' => 0.00,
            'case_resolution_rate' => 0.00
        ];
    }
}

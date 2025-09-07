<?php
/**
 * TPT Government Platform - Environmental Permits Module
 *
 * Comprehensive environmental permitting and compliance management system
 * supporting resource consent applications, environmental impact assessment, and regulatory compliance
 */

namespace Modules\EnvironmentalPermits;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class EnvironmentalPermitsModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Environmental Permits',
        'version' => '2.1.0',
        'description' => 'Comprehensive environmental permitting and compliance management system',
        'author' => 'TPT Government Platform',
        'category' => 'regulatory_services',
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
        'environmental_permits.view' => 'View environmental permit applications',
        'environmental_permits.create' => 'Create new environmental permit applications',
        'environmental_permits.edit' => 'Edit environmental permit applications',
        'environmental_permits.approve' => 'Approve environmental permit applications',
        'environmental_permits.reject' => 'Reject environmental permit applications',
        'environmental_permits.assess' => 'Conduct environmental impact assessments',
        'environmental_permits.monitor' => 'Monitor environmental compliance',
        'environmental_permits.report' => 'Generate environmental reports and analytics'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'environmental_permits' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'applicant_id' => 'INT NOT NULL',
            'permit_type' => "ENUM('land_use','water_discharge','air_emission','waste_disposal','resource_extraction','hazardous_substances','coastal_marine','wetlands','endangered_species','other') NOT NULL",
            'permit_category' => "ENUM('minor','moderate','major','significant') NOT NULL",
            'project_name' => 'VARCHAR(255) NOT NULL',
            'project_description' => 'TEXT NOT NULL',
            'location_address' => 'TEXT NOT NULL',
            'location_coordinates' => 'VARCHAR(100)',
            'property_owner' => 'VARCHAR(255)',
            'site_area' => 'DECIMAL(10,2)',
            'status' => "ENUM('draft','submitted','under_review','assessment','public_notification','decision_pending','approved','rejected','conditions_applied','expired','surrendered') DEFAULT 'draft'",
            'application_date' => 'DATETIME NOT NULL',
            'assessment_start_date' => 'DATE NULL',
            'assessment_completion_date' => 'DATE NULL',
            'decision_date' => 'DATE NULL',
            'approval_date' => 'DATETIME NULL',
            'expiry_date' => 'DATE NULL',
            'permit_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'assessment_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'monitoring_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'requires_eia' => 'BOOLEAN DEFAULT FALSE',
            'requires_public_notification' => 'BOOLEAN DEFAULT FALSE',
            'requires_cultural_impact' => 'BOOLEAN DEFAULT FALSE',
            'requires_archaeological' => 'BOOLEAN DEFAULT FALSE',
            'has_conditions' => 'BOOLEAN DEFAULT FALSE',
            'conditions_summary' => 'TEXT',
            'monitoring_required' => 'BOOLEAN DEFAULT FALSE',
            'monitoring_frequency' => "ENUM('daily','weekly','monthly','quarterly','annually') NULL",
            'inspector_id' => 'INT NULL',
            'assessor_id' => 'INT NULL',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'environmental_assessments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'assessment_type' => "ENUM('screening','scoping','full_eia','supplemental') NOT NULL",
            'assessor_id' => 'INT NOT NULL',
            'assessment_start_date' => 'DATE NOT NULL',
            'assessment_completion_date' => 'DATE NULL',
            'status' => "ENUM('pending','in_progress','completed','requires_revision') DEFAULT 'pending'",
            'scope_of_work' => 'TEXT',
            'methodology' => 'TEXT',
            'baseline_conditions' => 'JSON',
            'impact_assessment' => 'JSON',
            'mitigation_measures' => 'JSON',
            'monitoring_plan' => 'JSON',
            'residual_impacts' => 'JSON',
            'conclusions' => 'TEXT',
            'recommendations' => 'TEXT',
            'public_consultation_required' => 'BOOLEAN DEFAULT FALSE',
            'consultation_summary' => 'TEXT',
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'environmental_conditions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'condition_number' => 'VARCHAR(20) NOT NULL',
            'condition_type' => "ENUM('operational','monitoring','reporting','mitigation','restoration','other') NOT NULL",
            'condition_description' => 'TEXT NOT NULL',
            'compliance_deadline' => 'DATE NULL',
            'monitoring_frequency' => "ENUM('daily','weekly','monthly','quarterly','annually') NULL",
            'responsible_party' => 'VARCHAR(255)',
            'status' => "ENUM('active','complied','modified','suspended','cancelled') DEFAULT 'active'",
            'compliance_status' => "ENUM('not_started','in_progress','compliant','non_compliant','overdue') DEFAULT 'not_started'",
            'last_inspection_date' => 'DATE NULL',
            'next_inspection_date' => 'DATE NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'environmental_monitoring' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'condition_id' => 'INT NULL',
            'monitoring_type' => "ENUM('water_quality','air_quality','noise','soil','wildlife','vegetation','erosion','other') NOT NULL",
            'scheduled_date' => 'DATE NOT NULL',
            'actual_date' => 'DATE NULL',
            'monitor_id' => 'INT NOT NULL',
            'monitoring_results' => 'JSON',
            'compliance_status' => "ENUM('compliant','non_compliant','marginal','not_applicable') NULL",
            'issues_identified' => 'TEXT',
            'corrective_actions' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'next_monitoring_date' => 'DATE NULL',
            'attachments' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'public_consultations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'consultation_type' => "ENUM('public_notice','hearing','meeting','workshop','survey') NOT NULL",
            'start_date' => 'DATE NOT NULL',
            'end_date' => 'DATE NOT NULL',
            'notification_method' => 'VARCHAR(255)',
            'target_audience' => 'VARCHAR(255)',
            'consultation_materials' => 'JSON',
            'responses_received' => 'INT DEFAULT 0',
            'supporting_responses' => 'INT DEFAULT 0',
            'opposing_responses' => 'INT DEFAULT 0',
            'neutral_responses' => 'INT DEFAULT 0',
            'key_concerns' => 'JSON',
            'consultation_summary' => 'TEXT',
            'status' => "ENUM('planned','active','completed','cancelled') DEFAULT 'planned'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'environmental_reports' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'report_type' => "ENUM('annual_compliance','monitoring','incident','amendment','renewal') NOT NULL",
            'report_period_start' => 'DATE NOT NULL',
            'report_period_end' => 'DATE NOT NULL',
            'due_date' => 'DATE NOT NULL',
            'submission_date' => 'DATE NULL',
            'status' => "ENUM('pending','submitted','approved','rejected','overdue') DEFAULT 'pending'",
            'report_content' => 'JSON',
            'attachments' => 'JSON',
            'reviewer_comments' => 'TEXT',
            'approval_date' => 'DATE NULL',
            'next_report_due' => 'DATE NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'environmental_incidents' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'incident_date' => 'DATE NOT NULL',
            'incident_time' => 'TIME NULL',
            'incident_type' => "ENUM('spill','discharge','emission','erosion','wildlife_impact','other') NOT NULL",
            'severity' => "ENUM('minor','moderate','major','critical') NOT NULL",
            'description' => 'TEXT NOT NULL',
            'location' => 'VARCHAR(255)',
            'immediate_actions' => 'TEXT',
            'environmental_impact' => 'TEXT',
            'cleanup_actions' => 'TEXT',
            'reported_to_authorities' => 'BOOLEAN DEFAULT FALSE',
            'authority_notification_date' => 'DATE NULL',
            'corrective_actions' => 'TEXT',
            'preventive_measures' => 'TEXT',
            'status' => "ENUM('reported','investigating','resolved','ongoing') DEFAULT 'reported'",
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'permit_amendments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'permit_id' => 'INT NOT NULL',
            'amendment_type' => "ENUM('minor','major','administrative') NOT NULL",
            'request_date' => 'DATE NOT NULL',
            'description' => 'TEXT NOT NULL',
            'justification' => 'TEXT',
            'proposed_changes' => 'JSON',
            'environmental_impact' => 'TEXT',
            'status' => "ENUM('pending','under_review','approved','rejected','withdrawn') DEFAULT 'pending'",
            'review_date' => 'DATE NULL',
            'approval_date' => 'DATE NULL',
            'effective_date' => 'DATE NULL',
            'reviewer_id' => 'INT NULL',
            'reviewer_comments' => 'TEXT',
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'GET',
            'path' => '/api/environmental-permits',
            'handler' => 'getEnvironmentalPermits',
            'auth' => true,
            'permissions' => ['environmental_permits.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/environmental-permits',
            'handler' => 'createEnvironmentalPermit',
            'auth' => true,
            'permissions' => ['environmental_permits.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/environmental-permits/{id}',
            'handler' => 'getEnvironmentalPermit',
            'auth' => true,
            'permissions' => ['environmental_permits.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/environmental-permits/{id}',
            'handler' => 'updateEnvironmentalPermit',
            'auth' => true,
            'permissions' => ['environmental_permits.edit']
        ],
        [
            'method' => 'POST',
            'path' => '/api/environmental-permits/{id}/assess',
            'handler' => 'assessEnvironmentalPermit',
            'auth' => true,
            'permissions' => ['environmental_permits.assess']
        ],
        [
            'method' => 'POST',
            'path' => '/api/environmental-permits/{id}/approve',
            'handler' => 'approveEnvironmentalPermit',
            'auth' => true,
            'permissions' => ['environmental_permits.approve']
        ],
        [
            'method' => 'GET',
            'path' => '/api/environmental-assessments',
            'handler' => 'getEnvironmentalAssessments',
            'auth' => true,
            'permissions' => ['environmental_permits.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/environmental-assessments',
            'handler' => 'createEnvironmentalAssessment',
            'auth' => true,
            'permissions' => ['environmental_permits.assess']
        ],
        [
            'method' => 'GET',
            'path' => '/api/environmental-monitoring',
            'handler' => 'getEnvironmentalMonitoring',
            'auth' => true,
            'permissions' => ['environmental_permits.monitor']
        ],
        [
            'method' => 'POST',
            'path' => '/api/environmental-monitoring',
            'handler' => 'createEnvironmentalMonitoring',
            'auth' => true,
            'permissions' => ['environmental_permits.monitor']
        ],
        [
            'method' => 'GET',
            'path' => '/api/public-consultations',
            'handler' => 'getPublicConsultations',
            'auth' => true,
            'permissions' => ['environmental_permits.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/public-consultations',
            'handler' => 'createPublicConsultation',
            'auth' => true,
            'permissions' => ['environmental_permits.edit']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'environmental_permit_process' => [
            'name' => 'Environmental Permit Process',
            'description' => 'Standard workflow for environmental permit applications',
            'steps' => [
                'draft' => ['name' => 'Application Draft', 'next' => 'submitted'],
                'submitted' => ['name' => 'Application Submitted', 'next' => 'initial_review'],
                'initial_review' => ['name' => 'Initial Review', 'next' => 'screening_assessment'],
                'screening_assessment' => ['name' => 'Screening Assessment', 'next' => ['requires_full_eia', 'public_notification', 'decision_pending']],
                'requires_full_eia' => ['name' => 'Requires Full EIA', 'next' => 'full_assessment'],
                'full_assessment' => ['name' => 'Full Environmental Assessment', 'next' => 'public_notification'],
                'public_notification' => ['name' => 'Public Notification', 'next' => 'public_consultation'],
                'public_consultation' => ['name' => 'Public Consultation', 'next' => 'decision_pending'],
                'decision_pending' => ['name' => 'Decision Pending', 'next' => ['approved', 'rejected', 'conditions_applied']],
                'approved' => ['name' => 'Permit Approved', 'next' => 'permit_issued'],
                'permit_issued' => ['name' => 'Permit Issued', 'next' => 'monitoring_active'],
                'monitoring_active' => ['name' => 'Monitoring Active', 'next' => 'permit_active'],
                'permit_active' => ['name' => 'Permit Active', 'next' => ['renewal_due', 'amendment_requested', 'permit_expired']],
                'renewal_due' => ['name' => 'Renewal Due', 'next' => 'renewal_application'],
                'renewal_application' => ['name' => 'Renewal Application', 'next' => 'permit_renewed'],
                'permit_renewed' => ['name' => 'Permit Renewed', 'next' => 'permit_active'],
                'amendment_requested' => ['name' => 'Amendment Requested', 'next' => 'amendment_review'],
                'amendment_review' => ['name' => 'Amendment Review', 'next' => 'amendment_approved'],
                'amendment_approved' => ['name' => 'Amendment Approved', 'next' => 'permit_active'],
                'conditions_applied' => ['name' => 'Conditions Applied', 'next' => 'permit_issued'],
                'rejected' => ['name' => 'Application Rejected', 'next' => null],
                'permit_expired' => ['name' => 'Permit Expired', 'next' => null]
            ]
        ],
        'environmental_monitoring_process' => [
            'name' => 'Environmental Monitoring Process',
            'description' => 'Workflow for environmental monitoring and compliance',
            'steps' => [
                'monitoring_scheduled' => ['name' => 'Monitoring Scheduled', 'next' => 'monitoring_completed'],
                'monitoring_completed' => ['name' => 'Monitoring Completed', 'next' => ['compliant', 'non_compliant']],
                'compliant' => ['name' => 'Compliant', 'next' => 'next_monitoring_scheduled'],
                'non_compliant' => ['name' => 'Non-compliant', 'next' => 'corrective_actions'],
                'corrective_actions' => ['name' => 'Corrective Actions', 'next' => 'follow_up_monitoring'],
                'follow_up_monitoring' => ['name' => 'Follow-up Monitoring', 'next' => ['compliant', 'escalated']],
                'escalated' => ['name' => 'Escalated', 'next' => 'permit_review'],
                'permit_review' => ['name' => 'Permit Review', 'next' => ['permit_modified', 'permit_suspended']],
                'permit_modified' => ['name' => 'Permit Modified', 'next' => 'next_monitoring_scheduled'],
                'permit_suspended' => ['name' => 'Permit Suspended', 'next' => 'next_monitoring_scheduled'],
                'next_monitoring_scheduled' => ['name' => 'Next Monitoring Scheduled', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'environmental_permit_application' => [
            'name' => 'Environmental Permit Application',
            'fields' => [
                'permit_type' => ['type' => 'select', 'required' => true, 'label' => 'Permit Type'],
                'permit_category' => ['type' => 'select', 'required' => true, 'label' => 'Permit Category'],
                'project_name' => ['type' => 'text', 'required' => true, 'label' => 'Project Name'],
                'project_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Project Description'],
                'location_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Location Address'],
                'property_owner' => ['type' => 'text', 'required' => false, 'label' => 'Property Owner'],
                'site_area' => ['type' => 'number', 'required' => false, 'label' => 'Site Area (sq meters)', 'step' => '0.01'],
                'applicant_name' => ['type' => 'text', 'required' => true, 'label' => 'Applicant Name'],
                'applicant_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Applicant Address'],
                'applicant_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Applicant Phone'],
                'applicant_email' => ['type' => 'email', 'required' => true, 'label' => 'Applicant Email'],
                'consultant_name' => ['type' => 'text', 'required' => false, 'label' => 'Environmental Consultant'],
                'consultant_qualification' => ['type' => 'text', 'required' => false, 'label' => 'Consultant Qualification'],
                'requires_eia' => ['type' => 'checkbox', 'required' => false, 'label' => 'Requires Environmental Impact Assessment'],
                'requires_cultural_impact' => ['type' => 'checkbox', 'required' => false, 'label' => 'Requires Cultural Impact Assessment'],
                'requires_archaeological' => ['type' => 'checkbox', 'required' => false, 'label' => 'Requires Archaeological Assessment']
            ],
            'documents' => [
                'site_plan' => ['required' => true, 'label' => 'Site Plan'],
                'project_description' => ['required' => true, 'label' => 'Detailed Project Description'],
                'environmental_assessment' => ['required' => false, 'label' => 'Environmental Assessment Report'],
                'baseline_studies' => ['required' => false, 'label' => 'Baseline Environmental Studies'],
                'mitigation_plan' => ['required' => false, 'label' => 'Environmental Mitigation Plan'],
                'monitoring_plan' => ['required' => false, 'label' => 'Environmental Monitoring Plan'],
                'cultural_impact' => ['required' => false, 'label' => 'Cultural Impact Assessment'],
                'archaeological_report' => ['required' => false, 'label' => 'Archaeological Assessment']
            ]
        ],
        'environmental_assessment_form' => [
            'name' => 'Environmental Impact Assessment',
            'fields' => [
                'assessment_type' => ['type' => 'select', 'required' => true, 'label' => 'Assessment Type'],
                'scope_of_work' => ['type' => 'textarea', 'required' => true, 'label' => 'Scope of Work'],
                'methodology' => ['type' => 'textarea', 'required' => true, 'label' => 'Assessment Methodology'],
                'baseline_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Baseline Environmental Conditions'],
                'impact_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Environmental Impacts'],
                'mitigation_measures' => ['type' => 'textarea', 'required' => true, 'label' => 'Mitigation Measures'],
                'residual_impacts' => ['type' => 'textarea', 'required' => false, 'label' => 'Residual Impacts'],
                'monitoring_requirements' => ['type' => 'textarea', 'required' => false, 'label' => 'Monitoring Requirements'],
                'conclusions' => ['type' => 'textarea', 'required' => true, 'label' => 'Assessment Conclusions'],
                'recommendations' => ['type' => 'textarea', 'required' => true, 'label' => 'Recommendations']
            ]
        ],
        'monitoring_report_form' => [
            'name' => 'Environmental Monitoring Report',
            'fields' => [
                'monitoring_type' => ['type' => 'select', 'required' => true, 'label' => 'Monitoring Type'],
                'monitoring_date' => ['type' => 'date', 'required' => true, 'label' => 'Monitoring Date'],
                'monitoring_results' => ['type' => 'textarea', 'required' => true, 'label' => 'Monitoring Results'],
                'compliance_status' => ['type' => 'select', 'required' => true, 'label' => 'Compliance Status'],
                'issues_identified' => ['type' => 'textarea', 'required' => false, 'label' => 'Issues Identified'],
                'corrective_actions' => ['type' => 'textarea', 'required' => false, 'label' => 'Corrective Actions Taken'],
                'follow_up_required' => ['type' => 'checkbox', 'required' => false, 'label' => 'Follow-up Monitoring Required'],
                'next_monitoring_date' => ['type' => 'date', 'required' => false, 'label' => 'Next Monitoring Date']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'permit_status_report' => [
            'name' => 'Environmental Permit Status Report',
            'description' => 'Summary of environmental permits by status and type',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'permit_type' => ['type' => 'select', 'required' => false],
                'permit_category' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'permit_number', 'permit_type', 'permit_category', 'status',
                'application_date', 'decision_date', 'expiry_date'
            ]
        ],
        'environmental_assessment_report' => [
            'name' => 'Environmental Assessment Report',
            'description' => 'Environmental impact assessments and their outcomes',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'assessment_type' => ['type' => 'select', 'required' => false],
                'permit_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'permit_number', 'assessment_type', 'assessment_start_date',
                'assessment_completion_date', 'status', 'conclusions'
            ]
        ],
        'compliance_monitoring_report' => [
            'name' => 'Compliance Monitoring Report',
            'description' => 'Environmental monitoring results and compliance status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'monitoring_type' => ['type' => 'select', 'required' => false],
                'compliance_status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'permit_number', 'monitoring_type', 'scheduled_date',
                'compliance_status', 'issues_identified', 'corrective_actions'
            ]
        ],
        'public_consultation_report' => [
            'name' => 'Public Consultation Report',
            'description' => 'Public consultation activities and responses',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'consultation_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'permit_number', 'consultation_type', 'start_date',
                'responses_received', 'supporting_responses', 'key_concerns'
            ]
        ],
        'environmental_incident_report' => [
            'name' => 'Environmental Incident Report',
            'description' => 'Environmental incidents and their resolution',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'incident_type' => ['type' => 'select', 'required' => false],
                'severity' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'permit_number', 'incident_date', 'incident_type',
                'severity', 'environmental_impact', 'status'
            ]
        ],
        'permit_amendment_report' => [
            'name' => 'Permit Amendment Report',
            'description' => 'Permit amendments and their approval status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'amendment_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'permit_number', 'amendment_type', 'request_date',
                'status', 'effective_date', 'description'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'permit_application_submitted' => [
            'name' => 'Permit Application Submitted',
            'template' => 'Your environmental permit application for {project_name} has been submitted successfully. Permit Number: {permit_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_created']
        ],
        'assessment_started' => [
            'name' => 'Environmental Assessment Started',
            'template' => 'Environmental assessment has begun for your permit application {permit_number}. Assessment will be completed by {assessment_completion_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['assessment_started']
        ],
        'public_notification' => [
            'name' => 'Public Notification Period',
            'template' => 'Public notification period is now open for permit {permit_number}. Public comments accepted until {consultation_end_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['public_notification_started']
        ],
        'permit_approved' => [
            'name' => 'Environmental Permit Approved',
            'template' => 'Congratulations! Your environmental permit {permit_number} has been approved. Permit is valid until {expiry_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['permit_approved']
        ],
        'permit_rejected' => [
            'name' => 'Environmental Permit Rejected',
            'template' => 'Your environmental permit application {permit_number} has been rejected. Please review the decision details.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['permit_rejected']
        ],
        'monitoring_due' => [
            'name' => 'Environmental Monitoring Due',
            'template' => 'Environmental monitoring is due for permit {permit_number}. Monitoring type: {monitoring_type}, Due date: {due_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['monitoring_due']
        ],
        'non_compliance_detected' => [
            'name' => 'Non-compliance Detected',
            'template' => 'Non-compliance has been detected for permit {permit_number}. Issue: {compliance_issue}. Please take corrective action.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['non_compliance_detected']
        ],
        'permit_expiring' => [
            'name' => 'Permit Expiring Soon',
            'template' => 'Your environmental permit {permit_number} expires on {expiry_date}. Please submit renewal application if needed.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['permit_expiring']
        ],
        'incident_reported' => [
            'name' => 'Environmental Incident Reported',
            'template' => 'An environmental incident has been reported for permit {permit_number}. Type: {incident_type}, Severity: {severity}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['incident_reported']
        ],
        'amendment_requested' => [
            'name' => 'Permit Amendment Requested',
            'template' => 'A permit amendment has been requested for {permit_number}. Type: {amendment_type}. Status: {amendment_status}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['amendment_requested']
        ]
    ];

    /**
     * Permit types configuration
     */
    private array $permitTypes = [];

    /**
     * Assessment criteria
     */
    private array $assessmentCriteria = [];

    /**
     * Monitoring requirements
     */
    private array $monitoringRequirements = [];

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
            'processing_days_minor' => 30,
            'processing_days_moderate' => 60,
            'processing_days_major' => 120,
            'processing_days_significant' => 180,
            'public_notification_days' => 20,
            'assessment_fee_minor' => 500.00,
            'assessment_fee_moderate' => 2000.00,
            'assessment_fee_major' => 10000.00,
            'assessment_fee_significant' => 50000.00,
            'monitoring_fee_monthly' => 200.00,
            'renewal_notice_days' => 90,
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
        $this->initializePermitTypes();
        $this->initializeAssessmentCriteria();
        $this->initializeMonitoringRequirements();
    }

    /**
     * Initialize permit types
     */
    private function initializePermitTypes(): void
    {
        $this->permitTypes = [
            'land_use' => [
                'name' => 'Land Use Consent',
                'description' => 'Consent for land use activities with environmental impact',
                'requires_eia' => false,
                'requires_public_notification' => true,
                'processing_days' => 60
            ],
            'water_discharge' => [
                'name' => 'Water Discharge Permit',
                'description' => 'Permit for discharging contaminants into water bodies',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 90
            ],
            'air_emission' => [
                'name' => 'Air Emission Permit',
                'description' => 'Permit for emitting pollutants into the air',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 90
            ],
            'waste_disposal' => [
                'name' => 'Waste Disposal Permit',
                'description' => 'Permit for waste disposal and treatment activities',
                'requires_eia' => false,
                'requires_public_notification' => false,
                'processing_days' => 45
            ],
            'resource_extraction' => [
                'name' => 'Resource Extraction Permit',
                'description' => 'Permit for mining, quarrying, and resource extraction',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 120
            ],
            'hazardous_substances' => [
                'name' => 'Hazardous Substances Permit',
                'description' => 'Permit for handling and storing hazardous substances',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 90
            ],
            'coastal_marine' => [
                'name' => 'Coastal Marine Permit',
                'description' => 'Permit for activities affecting coastal and marine environments',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 120
            ],
            'wetlands' => [
                'name' => 'Wetlands Permit',
                'description' => 'Permit for activities affecting wetlands and riparian areas',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 90
            ],
            'endangered_species' => [
                'name' => 'Endangered Species Permit',
                'description' => 'Permit for activities affecting endangered or threatened species',
                'requires_eia' => true,
                'requires_public_notification' => true,
                'processing_days' => 150
            ]
        ];
    }

    /**
     * Initialize assessment criteria
     */
    private function initializeAssessmentCriteria(): void
    {
        $this->assessmentCriteria = [
            'minor' => [
                'thresholds' => [
                    'site_area' => 1000, // sq meters
                    'duration' => 12, // months
                    'public_concern' => 'low'
                ],
                'required_assessments' => ['screening'],
                'processing_time' => 30 // days
            ],
            'moderate' => [
                'thresholds' => [
                    'site_area' => 5000,
                    'duration' => 24,
                    'public_concern' => 'medium'
                ],
                'required_assessments' => ['screening', 'scoping'],
                'processing_time' => 60
            ],
            'major' => [
                'thresholds' => [
                    'site_area' => 20000,
                    'duration' => 60,
                    'public_concern' => 'high'
                ],
                'required_assessments' => ['screening', 'scoping', 'full_eia'],
                'processing_time' => 120
            ],
            'significant' => [
                'thresholds' => [
                    'site_area' => 100000,
                    'duration' => 120,
                    'public_concern' => 'very_high'
                ],
                'required_assessments' => ['screening', 'scoping', 'full_eia', 'supplemental'],
                'processing_time' => 180
            ]
        ];
    }

    /**
     * Initialize monitoring requirements
     */
    private function initializeMonitoringRequirements(): void
    {
        $this->monitoringRequirements = [
            'water_quality' => [
                'frequency' => 'monthly',
                'parameters' => ['pH', 'turbidity', 'dissolved_oxygen', 'temperature', 'conductivity'],
                'thresholds' => [
                    'pH_min' => 6.5,
                    'pH_max' => 8.5,
                    'turbidity_max' => 5.0,
                    'do_min' => 5.0
                ]
            ],
            'air_quality' => [
                'frequency' => 'quarterly',
                'parameters' => ['PM10', 'PM2.5', 'NOx', 'SO2', 'CO'],
                'thresholds' => [
                    'pm10_max' => 50,
                    'pm25_max' => 25,
                    'nox_max' => 40,
                    'so2_max' => 20,
                    'co_max' => 10
                ]
            ],
            'noise' => [
                'frequency' => 'monthly',
                'parameters' => ['day_noise', 'night_noise'],
                'thresholds' => [
                    'day_max' => 55, // dB
                    'night_max' => 45
                ]
            ],
            'soil' => [
                'frequency' => 'annually',
                'parameters' => ['pH', 'organic_matter', 'nutrients', 'contamination'],
                'thresholds' => [
                    'ph_min' => 5.5,
                    'ph_max' => 7.5,
                    'organic_matter_min' => 2.0
                ]
            ]
        ];
    }

    /**
     * Create environmental permit application
     */
    public function createEnvironmentalPermit(array $permitData): array
    {
        // Validate permit data
        $validation = $this->validateEnvironmentalPermit($permitData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate permit number
        $permitNumber = $this->generatePermitNumber();

        // Determine permit category and requirements
        $permitType = $this->permitTypes[$permitData['permit_type']] ?? null;
        if (!$permitType) {
            return [
                'success' => false,
                'error' => 'Invalid permit type'
            ];
        }

        // Calculate processing timeline
        $processingDays = $this->calculateProcessingDays($permitData['permit_category']);
        $assessmentCompletionDate = date('Y-m-d', strtotime("+{$processingDays} days"));

        // Calculate fees
        $fees = $this->calculatePermitFees($permitData['permit_category']);

        // Create permit record
        $permit = [
            'permit_number' => $permitNumber,
            'applicant_id' => $permitData['applicant_id'],
            'permit_type' => $permitData['permit_type'],
            'permit_category' => $permitData['permit_category'],
            'project_name' => $permitData['project_name'],
            'project_description' => $permitData['project_description'],
            'location_address' => $permitData['location_address'],
            'location_coordinates' => $permitData['location_coordinates'] ?? null,
            'property_owner' => $permitData['property_owner'] ?? null,
            'site_area' => $permitData['site_area'] ?? null,
            'status' => 'submitted',
            'application_date' => date('Y-m-d H:i:s'),
            'assessment_start_date' => date('Y-m-d'),
            'assessment_completion_date' => $assessmentCompletionDate,
            'permit_fee' => $fees['permit_fee'],
            'assessment_fee' => $fees['assessment_fee'],
            'monitoring_fee' => $fees['monitoring_fee'],
            'requires_eia' => $permitType['requires_eia'],
            'requires_public_notification' => $permitType['requires_public_notification'],
            'requires_cultural_impact' => $permitData['requires_cultural_impact'] ?? false,
            'requires_archaeological' => $permitData['requires_archaeological'] ?? false,
            'documents' => $permitData['documents'] ?? [],
            'notes' => $permitData['notes'] ?? ''
        ];

        // Save to database
        $this->saveEnvironmentalPermit($permit);

        // Start workflow
        $this->startPermitWorkflow($permitNumber);

        // Send notification
        $this->sendNotification('permit_application_submitted', $permitData['applicant_id'], [
            'permit_number' => $permitNumber,
            'project_name' => $permitData['project_name']
        ]);

        return [
            'success' => true,
            'permit_number' => $permitNumber,
            'assessment_completion_date' => $assessmentCompletionDate,
            'total_fees' => $fees['total'],
            'message' => 'Environmental permit application submitted successfully'
        ];
    }

    /**
     * Assess environmental permit
     */
    public function assessEnvironmentalPermit(string $permitNumber, array $assessmentData): array
    {
        try {
            $permit = $this->getEnvironmentalPermit($permitNumber);

            if (!$permit) {
                return [
                    'success' => false,
                    'error' => 'Permit not found'
                ];
            }

            // Create assessment record
            $assessment = [
                'permit_id' => $permit['id'],
                'assessment_type' => $assessmentData['assessment_type'],
                'assessor_id' => $assessmentData['assessor_id'],
                'assessment_start_date' => date('Y-m-d'),
                'status' => 'in_progress',
                'scope_of_work' => $assessmentData['scope_of_work'],
                'methodology' => $assessmentData['methodology'],
                'baseline_conditions' => $assessmentData['baseline_conditions'] ?? [],
                'impact_assessment' => $assessmentData['impact_assessment'] ?? [],
                'mitigation_measures' => $assessmentData['mitigation_measures'] ?? [],
                'monitoring_plan' => $assessmentData['monitoring_plan'] ?? [],
                'residual_impacts' => $assessmentData['residual_impacts'] ?? [],
                'conclusions' => $assessmentData['conclusions'],
                'recommendations' => $assessmentData['recommendations'],
                'attachments' => $assessmentData['attachments'] ?? []
            ];

            // Save assessment
            $this->saveEnvironmentalAssessment($assessment);

            // Update permit status
            $this->updatePermitStatus($permit['id'], 'assessment');

            // Send notification
            $this->sendNotification('assessment_started', $permit['applicant_id'], [
                'permit_number' => $permitNumber,
                'assessment_completion_date' => $permit['assessment_completion_date']
            ]);

            return [
                'success' => true,
                'assessment_id' => $this->getLastInsertId(),
                'message' => 'Environmental assessment started successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error assessing permit: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to start assessment'
            ];
        }
    }

    /**
     * Approve environmental permit
     */
    public function approveEnvironmentalPermit(string $permitNumber, array $approvalData): array
    {
        try {
            $permit = $this->getEnvironmentalPermit($permitNumber);

            if (!$permit) {
                return [
                    'success' => false,
                    'error' => 'Permit not found'
                ];
            }

            // Calculate expiry date (typically 5 years for environmental permits)
            $expiryDate = date('Y-m-d', strtotime('+5 years'));

            // Update permit with approval details
            $this->updateEnvironmentalPermit($permitNumber, [
                'status' => 'approved',
                'decision_date' => date('Y-m-d'),
                'approval_date' => date('Y-m-d H:i:s'),
                'expiry_date' => $expiryDate,
                'has_conditions' => !empty($approvalData['conditions']),
                'conditions_summary' => $approvalData['conditions_summary'] ?? '',
                'monitoring_required' => $approvalData['monitoring_required'] ?? false,
                'monitoring_frequency' => $approvalData['monitoring_frequency'] ?? null
            ]);

            // Send notification
            $this->sendNotification('permit_approved', $permit['applicant_id'], [
                'permit_number' => $permitNumber,
                'expiry_date' => $expiryDate
            ]);

            return [
                'success' => true,
                'expiry_date' => $expiryDate,
                'message' => 'Environmental permit approved successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error approving permit: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to approve permit'
            ];
        }
    }

    /**
     * Get environmental permits (API handler)
     */
    public function getEnvironmentalPermits(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM environmental_permits WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['permit_type'])) {
                $sql .= " AND permit_type = ?";
                $params[] = $filters['permit_type'];
            }

            if (isset($filters['applicant_id'])) {
                $sql .= " AND applicant_id = ?";
                $params[] = $filters['applicant_id'];
            }

            $sql .= " ORDER BY application_date DESC";

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
            error_log("Error getting environmental permits: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve environmental permits'
            ];
        }
    }

    /**
     * Get environmental permit (API handler)
     */
    public function getEnvironmentalPermit(string $permitNumber): array
    {
        $permit = $this->getEnvironmentalPermit($permitNumber);

        if (!$permit) {
            return [
                'success' => false,
                'error' => 'Permit not found'
            ];
        }

        return [
            'success' => true,
            'data' => $permit
        ];
    }

    /**
     * Create environmental permit (API handler)
     */
    public function createEnvironmentalPermit(array $data): array
    {
        return $this->createEnvironmentalPermit($data);
    }

    /**
     * Update environmental permit (API handler)
     */
    public function updateEnvironmentalPermit(string $permitNumber, array $data): array
    {
        try {
            $permit = $this->getEnvironmentalPermit($permitNumber);

            if (!$permit) {
                return [
                    'success' => false,
                    'error' => 'Permit not found'
                ];
            }

            $this->updateEnvironmentalPermit($permitNumber, $data);

            return [
                'success' => true,
                'message' => 'Permit updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating permit: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update permit'
            ];
        }
    }

    /**
     * Assess environmental permit (API handler)
     */
    public function assessEnvironmentalPermit(string $permitNumber, array $data): array
    {
        return $this->assessEnvironmentalPermit($permitNumber, $data);
    }

    /**
     * Approve environmental permit (API handler)
     */
    public function approveEnvironmentalPermit(string $permitNumber, array $data): array
    {
        return $this->approveEnvironmentalPermit($permitNumber, $data);
    }

    /**
     * Get environmental assessments (API handler)
     */
    public function getEnvironmentalAssessments(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM environmental_assessments WHERE 1=1";
            $params = [];

            if (isset($filters['permit_id'])) {
                $sql .= " AND permit_id = ?";
                $params[] = $filters['permit_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY assessment_start_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['baseline_conditions'] = json_decode($result['baseline_conditions'], true);
                $result['impact_assessment'] = json_decode($result['impact_assessment'], true);
                $result['mitigation_measures'] = json_decode($result['mitigation_measures'], true);
                $result['monitoring_plan'] = json_decode($result['monitoring_plan'], true);
                $result['residual_impacts'] = json_decode($result['residual_impacts'], true);
                $result['attachments'] = json_decode($result['attachments'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting environmental assessments: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve environmental assessments'
            ];
        }
    }

    /**
     * Create environmental assessment (API handler)
     */
    public function createEnvironmentalAssessment(array $data): array
    {
        return $this->assessEnvironmentalPermit($data['permit_number'], $data);
    }

    /**
     * Get environmental monitoring (API handler)
     */
    public function getEnvironmentalMonitoring(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM environmental_monitoring WHERE 1=1";
            $params = [];

            if (isset($filters['permit_id'])) {
                $sql .= " AND permit_id = ?";
                $params[] = $filters['permit_id'];
            }

            if (isset($filters['monitoring_type'])) {
                $sql .= " AND monitoring_type = ?";
                $params[] = $filters['monitoring_type'];
            }

            $sql .= " ORDER BY scheduled_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['monitoring_results'] = json_decode($result['monitoring_results'], true);
                $result['attachments'] = json_decode($result['attachments'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting environmental monitoring: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve environmental monitoring data'
            ];
        }
    }

    /**
     * Create environmental monitoring (API handler)
     */
    public function createEnvironmentalMonitoring(array $data): array
    {
        try {
            // Validate monitoring data
            $validation = $this->validateEnvironmentalMonitoring($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Create monitoring record
            $monitoring = [
                'permit_id' => $data['permit_id'],
                'condition_id' => $data['condition_id'] ?? null,
                'monitoring_type' => $data['monitoring_type'],
                'scheduled_date' => $data['scheduled_date'],
                'actual_date' => date('Y-m-d'),
                'monitor_id' => $data['monitor_id'],
                'monitoring_results' => $data['monitoring_results'] ?? [],
                'compliance_status' => $data['compliance_status'],
                'issues_identified' => $data['issues_identified'] ?? '',
                'corrective_actions' => $data['corrective_actions'] ?? '',
                'follow_up_required' => $data['follow_up_required'] ?? false,
                'next_monitoring_date' => $data['next_monitoring_date'] ?? null,
                'attachments' => $data['attachments'] ?? [],
                'notes' => $data['notes'] ?? ''
            ];

            // Save to database
            $this->saveEnvironmentalMonitoring($monitoring);

            // Check for non-compliance
            if ($data['compliance_status'] === 'non_compliant') {
                $permit = $this->getEnvironmentalPermitById($data['permit_id']);
                $this->sendNotification('non_compliance_detected', $permit['applicant_id'], [
                    'permit_number' => $permit['permit_number'],
                    'compliance_issue' => $data['issues_identified']
                ]);
            }

            return [
                'success' => true,
                'monitoring_id' => $this->getLastInsertId(),
                'message' => 'Environmental monitoring record created successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error creating environmental monitoring: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create environmental monitoring record'
            ];
        }
    }

    /**
     * Get public consultations (API handler)
     */
    public function getPublicConsultations(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM public_consultations WHERE 1=1";
            $params = [];

            if (isset($filters['permit_id'])) {
                $sql .= " AND permit_id = ?";
                $params[] = $filters['permit_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY start_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['consultation_materials'] = json_decode($result['consultation_materials'], true);
                $result['key_concerns'] = json_decode($result['key_concerns'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting public consultations: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve public consultations'
            ];
        }
    }

    /**
     * Create public consultation (API handler)
     */
    public function createPublicConsultation(array $data): array
    {
        try {
            // Validate consultation data
            $validation = $this->validatePublicConsultation($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Create consultation record
            $consultation = [
                'permit_id' => $data['permit_id'],
                'consultation_type' => $data['consultation_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'notification_method' => $data['notification_method'],
                'target_audience' => $data['target_audience'],
                'consultation_materials' => $data['consultation_materials'] ?? [],
                'status' => 'planned'
            ];

            // Save to database
            $this->savePublicConsultation($consultation);

            // Send notification
            $permit = $this->getEnvironmentalPermitById($data['permit_id']);
            $this->sendNotification('public_notification', $permit['applicant_id'], [
                'permit_number' => $permit['permit_number'],
                'consultation_end_date' => $data['end_date']
            ]);

            return [
                'success' => true,
                'consultation_id' => $this->getLastInsertId(),
                'message' => 'Public consultation created successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error creating public consultation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create public consultation'
            ];
        }
    }

    /**
     * Validate environmental permit data
     */
    private function validateEnvironmentalPermit(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'applicant_id', 'permit_type', 'permit_category', 'project_name',
            'project_description', 'location_address'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['permit_type'] ?? '', array_keys($this->permitTypes))) {
            $errors[] = "Invalid permit type";
        }

        if (!in_array($data['permit_category'] ?? '', ['minor', 'moderate', 'major', 'significant'])) {
            $errors[] = "Invalid permit category";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate environmental monitoring data
     */
    private function validateEnvironmentalMonitoring(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'permit_id', 'monitoring_type', 'scheduled_date', 'monitor_id', 'compliance_status'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['monitoring_type'] ?? '', array_keys($this->monitoringRequirements))) {
            $errors[] = "Invalid monitoring type";
        }

        if (!in_array($data['compliance_status'] ?? '', ['compliant', 'non_compliant', 'marginal', 'not_applicable'])) {
            $errors[] = "Invalid compliance status";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate public consultation data
     */
    private function validatePublicConsultation(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'permit_id', 'consultation_type', 'start_date', 'end_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['consultation_type'] ?? '', ['public_notice', 'hearing', 'meeting', 'workshop', 'survey'])) {
            $errors[] = "Invalid consultation type";
        }

        if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
            $errors[] = "End date must be after start date";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Calculate processing days based on permit category
     */
    private function calculateProcessingDays(string $category): int
    {
        $days = [
            'minor' => $this->config['processing_days_minor'],
            'moderate' => $this->config['processing_days_moderate'],
            'major' => $this->config['processing_days_major'],
            'significant' => $this->config['processing_days_significant']
        ];

        return $days[$category] ?? 60;
    }

    /**
     * Calculate permit fees based on category
     */
    private function calculatePermitFees(string $category): array
    {
        $baseFees = [
            'minor' => $this->config['assessment_fee_minor'],
            'moderate' => $this->config['assessment_fee_moderate'],
            'major' => $this->config['assessment_fee_major'],
            'significant' => $this->config['assessment_fee_significant']
        ];

        $assessmentFee = $baseFees[$category] ?? 2000.00;
        $permitFee = 500.00; // Base permit fee
        $monitoringFee = $this->config['monitoring_fee_monthly'] * 12; // Annual monitoring fee

        return [
            'permit_fee' => $permitFee,
            'assessment_fee' => $assessmentFee,
            'monitoring_fee' => $monitoringFee,
            'total' => $permitFee + $assessmentFee + $monitoringFee
        ];
    }

    /**
     * Generate permit number
     */
    private function generatePermitNumber(): string
    {
        return 'EP' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update permit status
     */
    private function updatePermitStatus(int $permitId, string $status): bool
    {
        try {
            $db = Database::getInstance();
            $db->update('environmental_permits', ['status' => $status], ['id' => $permitId]);
            return true;
        } catch (\Exception $e) {
            error_log("Error updating permit status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Placeholder methods (would be implemented with actual database operations)
     */
    private function saveEnvironmentalPermit(array $permit): bool { return true; }
    private function startPermitWorkflow(string $permitNumber): bool { return true; }
    private function sendNotification(string $type, ?int $userId, array $data): bool { return true; }
    private function getEnvironmentalPermit(string $permitNumber): ?array { return null; }
    private function updateEnvironmentalPermit(string $permitNumber, array $data): bool { return true; }
    private function getEnvironmentalPermitById(int $permitId): ?array { return null; }
    private function saveEnvironmentalAssessment(array $assessment): bool { return true; }
    private function saveEnvironmentalMonitoring(array $monitoring): bool { return true; }
    private function savePublicConsultation(array $consultation): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_permits' => 0, // Would query database
            'active_permits' => 0,
            'expired_permits' => 0,
            'pending_assessments' => 0,
            'completed_assessments' => 0,
            'public_consultations' => 0,
            'compliance_rate' => 0.00,
            'average_processing_time' => 0
        ];
    }
}

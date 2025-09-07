<?php
/**
 * TPT Government Platform - Health & Safety Module
 *
 * Comprehensive health and safety compliance management system
 * supporting certification applications, inspection scheduling, incident reporting, and regulatory compliance
 */

namespace Modules\HealthSafety;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class HealthSafetyModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Health & Safety',
        'version' => '2.1.0',
        'description' => 'Comprehensive health and safety compliance management system',
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
        'health_safety.view' => 'View health and safety applications and records',
        'health_safety.create' => 'Create new health and safety applications',
        'health_safety.edit' => 'Edit health and safety applications',
        'health_safety.approve' => 'Approve health and safety applications',
        'health_safety.inspect' => 'Conduct health and safety inspections',
        'health_safety.certify' => 'Issue health and safety certificates',
        'health_safety.report' => 'Report health and safety incidents',
        'health_safety.monitor' => 'Monitor compliance and training records'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'health_safety_applications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'applicant_id' => 'INT NOT NULL',
            'application_type' => "ENUM('workplace_safety','food_safety','public_health','environmental_health','occupational_health','construction_safety','pool_safety','amusement_ride','other') NOT NULL",
            'business_name' => 'VARCHAR(255) NOT NULL',
            'business_address' => 'TEXT NOT NULL',
            'business_type' => 'VARCHAR(100)',
            'contact_name' => 'VARCHAR(255) NOT NULL',
            'contact_phone' => 'VARCHAR(20) NOT NULL',
            'contact_email' => 'VARCHAR(255) NOT NULL',
            'facility_type' => 'VARCHAR(100)',
            'facility_size' => 'VARCHAR(50)',
            'employee_count' => 'INT',
            'risk_level' => "ENUM('low','medium','high','critical') DEFAULT 'medium'",
            'status' => "ENUM('draft','submitted','under_review','inspection_scheduled','inspection_completed','approved','rejected','conditional_approval','expired','suspended') DEFAULT 'draft'",
            'application_date' => 'DATETIME NOT NULL',
            'inspection_date' => 'DATE NULL',
            'approval_date' => 'DATETIME NULL',
            'expiry_date' => 'DATE NULL',
            'certificate_number' => 'VARCHAR(20)',
            'application_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'inspection_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'annual_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'requires_inspection' => 'BOOLEAN DEFAULT TRUE',
            'requires_training' => 'BOOLEAN DEFAULT FALSE',
            'has_conditions' => 'BOOLEAN DEFAULT FALSE',
            'conditions_summary' => 'TEXT',
            'inspector_id' => 'INT NULL',
            'reviewer_id' => 'INT NULL',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'health_safety_inspections' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'inspection_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'inspector_id' => 'INT NOT NULL',
            'inspection_type' => "ENUM('initial','follow_up','compliance','re_inspection','complaint_based','random') NOT NULL",
            'scheduled_date' => 'DATE NOT NULL',
            'actual_date' => 'DATE NULL',
            'start_time' => 'TIME NULL',
            'end_time' => 'TIME NULL',
            'status' => "ENUM('scheduled','in_progress','completed','cancelled','rescheduled') DEFAULT 'scheduled'",
            'overall_rating' => "ENUM('excellent','good','satisfactory','poor','critical') NULL",
            'critical_findings' => 'INT DEFAULT 0',
            'major_findings' => 'INT DEFAULT 0',
            'minor_findings' => 'INT DEFAULT 0',
            'recommendations' => 'TEXT',
            'corrective_actions_required' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATE NULL',
            'compliance_deadline' => 'DATE NULL',
            'inspection_report' => 'JSON',
            'attachments' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'health_safety_violations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'inspection_id' => 'INT NOT NULL',
            'violation_code' => 'VARCHAR(20) NOT NULL',
            'violation_description' => 'TEXT NOT NULL',
            'regulation_section' => 'VARCHAR(100) NOT NULL',
            'severity' => "ENUM('critical','major','minor') NOT NULL",
            'status' => "ENUM('open','corrected','escalated','dismissed') DEFAULT 'open'",
            'correction_required' => 'TEXT',
            'correction_deadline' => 'DATE NULL',
            'date_corrected' => 'DATE NULL',
            'fine_amount' => 'DECIMAL(8,2) DEFAULT 0.00',
            'evidence_photos' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'health_safety_certificates' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'certificate_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'certificate_type' => 'VARCHAR(100) NOT NULL',
            'issue_date' => 'DATE NOT NULL',
            'expiry_date' => 'DATE NOT NULL',
            'status' => "ENUM('active','expired','suspended','revoked','renewed') DEFAULT 'active'",
            'issued_by' => 'INT NOT NULL',
            'conditions' => 'TEXT',
            'renewal_required' => 'BOOLEAN DEFAULT TRUE',
            'renewal_date' => 'DATE NULL',
            'certificate_document' => 'VARCHAR(255)',
            'qr_code' => 'VARCHAR(255)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'health_safety_incidents' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'incident_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'application_id' => 'INT NULL',
            'reporter_id' => 'INT NOT NULL',
            'incident_type' => "ENUM('accident','near_miss','hazard','complaint','illness','injury','property_damage','environmental','other') NOT NULL",
            'severity' => "ENUM('minor','moderate','major','critical','fatal') NOT NULL",
            'incident_date' => 'DATE NOT NULL',
            'incident_time' => 'TIME NULL',
            'location' => 'VARCHAR(255)',
            'description' => 'TEXT NOT NULL',
            'immediate_actions' => 'TEXT',
            'injuries' => 'INT DEFAULT 0',
            'fatalities' => 'INT DEFAULT 0',
            'property_damage' => 'DECIMAL(10,2) DEFAULT 0.00',
            'environmental_impact' => 'TEXT',
            'witnesses' => 'JSON',
            'investigation_required' => 'BOOLEAN DEFAULT TRUE',
            'investigator_id' => 'INT NULL',
            'investigation_status' => "ENUM('pending','in_progress','completed','closed') DEFAULT 'pending'",
            'investigation_findings' => 'TEXT',
            'preventive_measures' => 'TEXT',
            'status' => "ENUM('reported','investigating','resolved','closed') DEFAULT 'reported'",
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'health_safety_training' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'training_type' => "ENUM('food_safety','workplace_safety','first_aid','hazard_communication','emergency_response','equipment_specific','other') NOT NULL",
            'training_provider' => 'VARCHAR(255)',
            'instructor_name' => 'VARCHAR(255)',
            'training_date' => 'DATE NOT NULL',
            'expiry_date' => 'DATE NULL',
            'participants' => 'JSON',
            'training_topics' => 'JSON',
            'certificates_issued' => 'INT DEFAULT 0',
            'status' => "ENUM('scheduled','completed','cancelled','postponed') DEFAULT 'scheduled'",
            'training_materials' => 'JSON',
            'assessment_results' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'health_safety_audits' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'audit_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'auditor_id' => 'INT NOT NULL',
            'audit_type' => "ENUM('internal','external','regulatory','compliance','surveillance') NOT NULL",
            'audit_scope' => 'TEXT',
            'scheduled_date' => 'DATE NOT NULL',
            'actual_date' => 'DATE NULL',
            'status' => "ENUM('planned','in_progress','completed','cancelled') DEFAULT 'planned'",
            'overall_score' => 'DECIMAL(5,2)',
            'findings' => 'JSON',
            'recommendations' => 'JSON',
            'corrective_actions' => 'JSON',
            'follow_up_date' => 'DATE NULL',
            'audit_report' => 'JSON',
            'attachments' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'health_safety_compliance_history' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'INT NOT NULL',
            'action_type' => "ENUM('application','inspection','violation','certificate','incident','training','audit','renewal') NOT NULL",
            'action_date' => 'DATE NOT NULL',
            'action_description' => 'TEXT NOT NULL',
            'performed_by' => 'INT NOT NULL',
            'status_before' => 'VARCHAR(50)',
            'status_after' => 'VARCHAR(50)',
            'compliance_score' => 'DECIMAL(5,2)',
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
            'path' => '/api/health-safety/applications',
            'handler' => 'getHealthSafetyApplications',
            'auth' => true,
            'permissions' => ['health_safety.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health-safety/applications',
            'handler' => 'createHealthSafetyApplication',
            'auth' => true,
            'permissions' => ['health_safety.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health-safety/applications/{id}',
            'handler' => 'getHealthSafetyApplication',
            'auth' => true,
            'permissions' => ['health_safety.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/health-safety/applications/{id}',
            'handler' => 'updateHealthSafetyApplication',
            'auth' => true,
            'permissions' => ['health_safety.edit']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health-safety/applications/{id}/approve',
            'handler' => 'approveHealthSafetyApplication',
            'auth' => true,
            'permissions' => ['health_safety.approve']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health-safety/inspections',
            'handler' => 'getHealthSafetyInspections',
            'auth' => true,
            'permissions' => ['health_safety.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health-safety/inspections',
            'handler' => 'createHealthSafetyInspection',
            'auth' => true,
            'permissions' => ['health_safety.inspect']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health-safety/certificates',
            'handler' => 'getHealthSafetyCertificates',
            'auth' => true,
            'permissions' => ['health_safety.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health-safety/certificates',
            'handler' => 'issueHealthSafetyCertificate',
            'auth' => true,
            'permissions' => ['health_safety.certify']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health-safety/incidents',
            'handler' => 'getHealthSafetyIncidents',
            'auth' => true,
            'permissions' => ['health_safety.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health-safety/incidents',
            'handler' => 'reportHealthSafetyIncident',
            'auth' => true,
            'permissions' => ['health_safety.report']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health-safety/training',
            'handler' => 'getHealthSafetyTraining',
            'auth' => true,
            'permissions' => ['health_safety.monitor']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health-safety/training',
            'handler' => 'scheduleHealthSafetyTraining',
            'auth' => true,
            'permissions' => ['health_safety.edit']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'health_safety_application_process' => [
            'name' => 'Health & Safety Application Process',
            'description' => 'Standard workflow for health and safety certification applications',
            'steps' => [
                'draft' => ['name' => 'Application Draft', 'next' => 'submitted'],
                'submitted' => ['name' => 'Application Submitted', 'next' => 'initial_review'],
                'initial_review' => ['name' => 'Initial Review', 'next' => 'risk_assessment'],
                'risk_assessment' => ['name' => 'Risk Assessment', 'next' => 'inspection_scheduled'],
                'inspection_scheduled' => ['name' => 'Inspection Scheduled', 'next' => 'inspection_completed'],
                'inspection_completed' => ['name' => 'Inspection Completed', 'next' => 'decision_pending'],
                'decision_pending' => ['name' => 'Decision Pending', 'next' => ['approved', 'rejected', 'conditional_approval', 're_inspection_required']],
                'approved' => ['name' => 'Application Approved', 'next' => 'certificate_issued'],
                'certificate_issued' => ['name' => 'Certificate Issued', 'next' => 'active'],
                'active' => ['name' => 'Certificate Active', 'next' => ['renewal_due', 'complaint_received', 'audit_required', 'expired']],
                'renewal_due' => ['name' => 'Renewal Due', 'next' => 'renewal_application'],
                'renewal_application' => ['name' => 'Renewal Application', 'next' => 'renewal_inspection'],
                'renewal_inspection' => ['name' => 'Renewal Inspection', 'next' => 'renewal_approved'],
                'renewal_approved' => ['name' => 'Renewal Approved', 'next' => 'active'],
                'complaint_received' => ['name' => 'Complaint Received', 'next' => 'complaint_investigation'],
                'complaint_investigation' => ['name' => 'Complaint Investigation', 'next' => ['complaint_resolved', 'enforcement_action']],
                'complaint_resolved' => ['name' => 'Complaint Resolved', 'next' => 'active'],
                'enforcement_action' => ['name' => 'Enforcement Action', 'next' => 'active'],
                'audit_required' => ['name' => 'Audit Required', 'next' => 'audit_completed'],
                'audit_completed' => ['name' => 'Audit Completed', 'next' => 'active'],
                'conditional_approval' => ['name' => 'Conditional Approval', 'next' => 'certificate_issued'],
                're_inspection_required' => ['name' => 'Re-inspection Required', 'next' => 'inspection_scheduled'],
                'rejected' => ['name' => 'Application Rejected', 'next' => null],
                'expired' => ['name' => 'Certificate Expired', 'next' => null]
            ]
        ],
        'incident_response_process' => [
            'name' => 'Incident Response Process',
            'description' => 'Workflow for handling health and safety incidents',
            'steps' => [
                'reported' => ['name' => 'Incident Reported', 'next' => 'initial_assessment'],
                'initial_assessment' => ['name' => 'Initial Assessment', 'next' => 'investigation_required'],
                'investigation_required' => ['name' => 'Investigation Required', 'next' => 'investigation_started'],
                'investigation_started' => ['name' => 'Investigation Started', 'next' => 'investigation_completed'],
                'investigation_completed' => ['name' => 'Investigation Completed', 'next' => 'findings_reviewed'],
                'findings_reviewed' => ['name' => 'Findings Reviewed', 'next' => 'corrective_actions'],
                'corrective_actions' => ['name' => 'Corrective Actions', 'next' => 'preventive_measures'],
                'preventive_measures' => ['name' => 'Preventive Measures', 'next' => 'follow_up_monitoring'],
                'follow_up_monitoring' => ['name' => 'Follow-up Monitoring', 'next' => 'incident_closed'],
                'incident_closed' => ['name' => 'Incident Closed', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'health_safety_application' => [
            'name' => 'Health & Safety Application',
            'fields' => [
                'application_type' => ['type' => 'select', 'required' => true, 'label' => 'Application Type'],
                'business_name' => ['type' => 'text', 'required' => true, 'label' => 'Business Name'],
                'business_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Address'],
                'business_type' => ['type' => 'text', 'required' => false, 'label' => 'Business Type'],
                'contact_name' => ['type' => 'text', 'required' => true, 'label' => 'Contact Name'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                'facility_type' => ['type' => 'text', 'required' => false, 'label' => 'Facility Type'],
                'facility_size' => ['type' => 'text', 'required' => false, 'label' => 'Facility Size'],
                'employee_count' => ['type' => 'number', 'required' => false, 'label' => 'Number of Employees'],
                'risk_level' => ['type' => 'select', 'required' => true, 'label' => 'Risk Level'],
                'requires_inspection' => ['type' => 'checkbox', 'required' => false, 'label' => 'Requires Inspection'],
                'requires_training' => ['type' => 'checkbox', 'required' => false, 'label' => 'Requires Training']
            ],
            'documents' => [
                'business_registration' => ['required' => true, 'label' => 'Business Registration'],
                'facility_layout' => ['required' => true, 'label' => 'Facility Layout/Plans'],
                'safety_manual' => ['required' => false, 'label' => 'Safety Manual'],
                'training_records' => ['required' => false, 'label' => 'Training Records'],
                'previous_inspections' => ['required' => false, 'label' => 'Previous Inspection Reports'],
                'insurance_certificate' => ['required' => false, 'label' => 'Insurance Certificate'],
                'emergency_plans' => ['required' => false, 'label' => 'Emergency Response Plans']
            ]
        ],
        'inspection_report_form' => [
            'name' => 'Health & Safety Inspection Report',
            'fields' => [
                'inspection_type' => ['type' => 'select', 'required' => true, 'label' => 'Inspection Type'],
                'scheduled_date' => ['type' => 'date', 'required' => true, 'label' => 'Scheduled Date'],
                'actual_date' => ['type' => 'date', 'required' => true, 'label' => 'Actual Date'],
                'start_time' => ['type' => 'time', 'required' => false, 'label' => 'Start Time'],
                'end_time' => ['type' => 'time', 'required' => false, 'label' => 'End Time'],
                'overall_rating' => ['type' => 'select', 'required' => true, 'label' => 'Overall Rating'],
                'critical_findings' => ['type' => 'number', 'required' => false, 'label' => 'Critical Findings', 'min' => '0'],
                'major_findings' => ['type' => 'number', 'required' => false, 'label' => 'Major Findings', 'min' => '0'],
                'minor_findings' => ['type' => 'number', 'required' => false, 'label' => 'Minor Findings', 'min' => '0'],
                'recommendations' => ['type' => 'textarea', 'required' => false, 'label' => 'Recommendations'],
                'corrective_actions_required' => ['type' => 'textarea', 'required' => false, 'label' => 'Corrective Actions Required'],
                'follow_up_required' => ['type' => 'checkbox', 'required' => false, 'label' => 'Follow-up Required'],
                'follow_up_date' => ['type' => 'date', 'required' => false, 'label' => 'Follow-up Date'],
                'compliance_deadline' => ['type' => 'date', 'required' => false, 'label' => 'Compliance Deadline']
            ]
        ],
        'incident_report_form' => [
            'name' => 'Health & Safety Incident Report',
            'fields' => [
                'incident_type' => ['type' => 'select', 'required' => true, 'label' => 'Incident Type'],
                'severity' => ['type' => 'select', 'required' => true, 'label' => 'Severity Level'],
                'incident_date' => ['type' => 'date', 'required' => true, 'label' => 'Incident Date'],
                'incident_time' => ['type' => 'time', 'required' => false, 'label' => 'Incident Time'],
                'location' => ['type' => 'text', 'required' => true, 'label' => 'Location'],
                'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Incident Description'],
                'immediate_actions' => ['type' => 'textarea', 'required' => false, 'label' => 'Immediate Actions Taken'],
                'injuries' => ['type' => 'number', 'required' => false, 'label' => 'Number of Injuries', 'min' => '0'],
                'fatalities' => ['type' => 'number', 'required' => false, 'label' => 'Number of Fatalities', 'min' => '0'],
                'property_damage' => ['type' => 'number', 'required' => false, 'label' => 'Property Damage ($)', 'step' => '0.01', 'min' => '0'],
                'environmental_impact' => ['type' => 'textarea', 'required' => false, 'label' => 'Environmental Impact'],
                'witnesses' => ['type' => 'textarea', 'required' => false, 'label' => 'Witnesses'],
                'investigation_required' => ['type' => 'checkbox', 'required' => false, 'label' => 'Investigation Required']
            ],
            'documents' => [
                'incident_photos' => ['required' => false, 'label' => 'Incident Photos'],
                'witness_statements' => ['required' => false, 'label' => 'Witness Statements'],
                'medical_reports' => ['required' => false, 'label' => 'Medical Reports'],
                'damage_assessment' => ['required' => false, 'label' => 'Damage Assessment']
            ]
        ],
        'training_record_form' => [
            'name' => 'Health & Safety Training Record',
            'fields' => [
                'training_type' => ['type' => 'select', 'required' => true, 'label' => 'Training Type'],
                'training_provider' => ['type' => 'text', 'required' => true, 'label' => 'Training Provider'],
                'instructor_name' => ['type' => 'text', 'required' => true, 'label' => 'Instructor Name'],
                'training_date' => ['type' => 'date', 'required' => true, 'label' => 'Training Date'],
                'expiry_date' => ['type' => 'date', 'required' => false, 'label' => 'Certificate Expiry Date'],
                'participants' => ['type' => 'textarea', 'required' => true, 'label' => 'Participants'],
                'training_topics' => ['type' => 'textarea', 'required' => true, 'label' => 'Training Topics'],
                'certificates_issued' => ['type' => 'number', 'required' => false, 'label' => 'Certificates Issued', 'min' => '0']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'application_status_report' => [
            'name' => 'Health & Safety Application Status Report',
            'description' => 'Summary of health and safety applications by status and type',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'application_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false],
                'risk_level' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'application_number', 'application_type', 'business_name', 'status',
                'application_date', 'inspection_date', 'approval_date', 'expiry_date'
            ]
        ],
        'inspection_compliance_report' => [
            'name' => 'Inspection Compliance Report',
            'description' => 'Health and safety inspection results and compliance status',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'inspection_type' => ['type' => 'select', 'required' => false],
                'overall_rating' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'inspection_number', 'business_name', 'inspection_type', 'scheduled_date',
                'overall_rating', 'critical_findings', 'major_findings', 'minor_findings'
            ]
        ],
        'incident_analysis_report' => [
            'name' => 'Incident Analysis Report',
            'description' => 'Analysis of health and safety incidents by type and severity',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'incident_type' => ['type' => 'select', 'required' => false],
                'severity' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'incident_number', 'incident_type', 'severity', 'incident_date',
                'business_name', 'injuries', 'fatalities', 'property_damage'
            ]
        ],
        'certificate_expiry_report' => [
            'name' => 'Certificate Expiry Report',
            'description' => 'Health and safety certificates expiring within specified period',
            'parameters' => [
                'expiry_months' => ['type' => 'number', 'required' => true, 'default' => 3, 'min' => 1, 'max' => 12],
                'certificate_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'certificate_number', 'business_name', 'certificate_type', 'issue_date',
                'expiry_date', 'days_until_expiry', 'contact_details'
            ]
        ],
        'training_compliance_report' => [
            'name' => 'Training Compliance Report',
            'description' => 'Health and safety training records and compliance',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'training_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'business_name', 'training_type', 'training_date', 'participants',
                'certificates_issued', 'expiry_date', 'status'
            ]
        ],
        'violation_trends_report' => [
            'name' => 'Violation Trends Report',
            'description' => 'Analysis of health and safety violations and trends',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'violation_code' => ['type' => 'select', 'required' => false],
                'severity' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'violation_code', 'violation_description', 'severity', 'frequency',
                'business_type', 'correction_rate', 'average_correction_time'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'application_submitted' => [
            'name' => 'Application Submitted',
            'template' => 'Your health and safety application for {business_name} has been submitted successfully. Application Number: {application_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_created']
        ],
        'inspection_scheduled' => [
            'name' => 'Inspection Scheduled',
            'template' => 'A health and safety inspection has been scheduled for {business_name} on {inspection_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['inspection_scheduled']
        ],
        'inspection_completed' => [
            'name' => 'Inspection Completed',
            'template' => 'Health and safety inspection completed for {business_name}. Rating: {overall_rating}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['inspection_completed']
        ],
        'application_approved' => [
            'name' => 'Application Approved',
            'template' => 'Congratulations! Your health and safety application {application_number} has been approved',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_approved']
        ],
        'application_rejected' => [
            'name' => 'Application Rejected',
            'template' => 'Your health and safety application {application_number} has been rejected. Please review the decision details.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_rejected']
        ],
        'certificate_issued' => [
            'name' => 'Certificate Issued',
            'template' => 'Health and safety certificate {certificate_number} has been issued for {business_name}. Valid until {expiry_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['certificate_issued']
        ],
        'certificate_expiring' => [
            'name' => 'Certificate Expiring',
            'template' => 'Your health and safety certificate {certificate_number} expires on {expiry_date}. Please renew.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['certificate_expiring']
        ],
        'violation_detected' => [
            'name' => 'Violation Detected',
            'template' => 'A health and safety violation has been detected at {business_name}. Code: {violation_code}. Correction required by {correction_deadline}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['violation_detected']
        ],
        'incident_reported' => [
            'name' => 'Incident Reported',
            'template' => 'A health and safety incident has been reported. Type: {incident_type}, Severity: {severity}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['incident_reported']
        ],
        'training_required' => [
            'name' => 'Training Required',
            'template' => 'Health and safety training is required for {business_name}. Type: {training_type}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['training_required']
        ],
        'follow_up_required' => [
            'name' => 'Follow-up Required',
            'template' => 'Follow-up inspection required for {business_name} on {follow_up_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['follow_up_required']
        ]
    ];

    /**
     * Application types configuration
     */
    private array $applicationTypes = [];

    /**
     * Risk assessment criteria
     */
    private array $riskCriteria = [];

    /**
     * Violation codes and penalties
     */
    private array $violationCodes = [];

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
            'processing_days_low_risk' => 15,
            'processing_days_medium_risk' => 30,
            'processing_days_high_risk' => 60,
            'processing_days_critical_risk' => 90,
            'inspection_notice_days' => 7,
            'certificate_validity_years' => 1,
            'renewal_notice_months' => 3,
            'application_fee_base' => 150.00,
            'inspection_fee_base' => 200.00,
            'annual_fee_base' => 100.00,
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
        $this->initializeApplicationTypes();
        $this->initializeRiskCriteria();
        $this->initializeViolationCodes();
    }

    /**
     * Initialize application types
     */
    private function initializeApplicationTypes(): void
    {
        $this->applicationTypes = [
            'workplace_safety' => [
                'name' => 'Workplace Safety Certificate',
                'description' => 'Certificate for general workplace safety compliance',
                'requires_inspection' => true,
                'requires_training' => true,
                'validity_period' => 1
            ],
            'food_safety' => [
                'name' => 'Food Safety Certificate',
                'description' => 'Certificate for food handling and preparation facilities',
                'requires_inspection' => true,
                'requires_training' => true,
                'validity_period' => 1
            ],
            'public_health' => [
                'name' => 'Public Health Certificate',
                'description' => 'Certificate for public health and sanitation compliance',
                'requires_inspection' => true,
                'requires_training' => false,
                'validity_period' => 1
            ],
            'environmental_health' => [
                'name' => 'Environmental Health Certificate',
                'description' => 'Certificate for environmental health compliance',
                'requires_inspection' => true,
                'requires_training' => false,
                'validity_period' => 1
            ],
            'occupational_health' => [
                'name' => 'Occupational Health Certificate',
                'description' => 'Certificate for occupational health and safety',
                'requires_inspection' => true,
                'requires_training' => true,
                'validity_period' => 1
            ],
            'construction_safety' => [
                'name' => 'Construction Safety Certificate',
                'description' => 'Certificate for construction site safety',
                'requires_inspection' => true,
                'requires_training' => true,
                'validity_period' => 1
            ],
            'pool_safety' => [
                'name' => 'Pool Safety Certificate',
                'description' => 'Certificate for swimming pool safety',
                'requires_inspection' => true,
                'requires_training' => false,
                'validity_period' => 1
            ],
            'amusement_ride' => [
                'name' => 'Amusement Ride Safety Certificate',
                'description' => 'Certificate for amusement ride safety',
                'requires_inspection' => true,
                'requires_training' => true,
                'validity_period' => 1
            ]
        ];
    }

    /**
     * Initialize risk criteria
     */
    private function initializeRiskCriteria(): void
    {
        $this->riskCriteria = [
            'low' => [
                'employee_threshold' => 5,
                'facility_size_threshold' => 500,
                'processing_days' => 15,
                'inspection_frequency' => 'annual'
            ],
            'medium' => [
                'employee_threshold' => 20,
                'facility_size_threshold' => 2000,
                'processing_days' => 30,
                'inspection_frequency' => 'annual'
            ],
            'high' => [
                'employee_threshold' => 50,
                'facility_size_threshold' => 5000,
                'processing_days' => 60,
                'inspection_frequency' => 'semi-annual'
            ],
            'critical' => [
                'employee_threshold' => 100,
                'facility_size_threshold' => 10000,
                'processing_days' => 90,
                'inspection_frequency' => 'quarterly'
            ]
        ];
    }

    /**
     * Initialize violation codes
     */
    private function initializeViolationCodes(): void
    {
        $this->violationCodes = [
            'WS001' => [
                'description' => 'Inadequate emergency exits',
                'category' => 'workplace_safety',
                'severity' => 'critical',
                'base_fine' => 2500.00,
                'regulation' => 'Workplace Safety Act Section 5.1'
            ],
            'WS002' => [
                'description' => 'Missing safety signage',
                'category' => 'workplace_safety',
                'severity' => 'minor',
                'base_fine' => 150.00,
                'regulation' => 'Workplace Safety Act Section 7.2'
            ],
            'FS001' => [
                'description' => 'Improper food storage temperatures',
                'category' => 'food_safety',
                'severity' => 'major',
                'base_fine' => 1000.00,
                'regulation' => 'Food Safety Act Section 12.1'
            ],
            'FS002' => [
                'description' => 'Lack of handwashing facilities',
                'category' => 'food_safety',
                'severity' => 'critical',
                'base_fine' => 2000.00,
                'regulation' => 'Food Safety Act Section 8.3'
            ],
            'PH001' => [
                'description' => 'Inadequate sanitation facilities',
                'category' => 'public_health',
                'severity' => 'major',
                'base_fine' => 750.00,
                'regulation' => 'Public Health Act Section 15.2'
            ],
            'CS001' => [
                'description' => 'Missing hard hats on construction site',
                'category' => 'construction_safety',
                'severity' => 'major',
                'base_fine' => 1200.00,
                'regulation' => 'Construction Safety Act Section 22.1'
            ]
        ];
    }

    /**
     * Create health and safety application
     */
    public function createHealthSafetyApplication(array $applicationData): array
    {
        // Validate application data
        $validation = $this->validateHealthSafetyApplication($applicationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate application number
        $applicationNumber = $this->generateApplicationNumber();

        // Determine risk level and processing timeline
        $riskLevel = $this->assessRiskLevel($applicationData);
        $processingDays = $this->calculateProcessingDays($riskLevel);

        // Calculate fees
        $fees = $this->calculateApplicationFees($applicationData['application_type'], $riskLevel);

        // Create application record
        $application = [
            'application_number' => $applicationNumber,
            'applicant_id' => $applicationData['applicant_id'],
            'application_type' => $applicationData['application_type'],
            'business_name' => $applicationData['business_name'],
            'business_address' => $applicationData['business_address'],
            'business_type' => $applicationData['business_type'] ?? null,
            'contact_name' => $applicationData['contact_name'],
            'contact_phone' => $applicationData['contact_phone'],
            'contact_email' => $applicationData['contact_email'],
            'facility_type' => $applicationData['facility_type'] ?? null,
            'facility_size' => $applicationData['facility_size'] ?? null,
            'employee_count' => $applicationData['employee_count'] ?? null,
            'risk_level' => $riskLevel,
            'status' => 'submitted',
            'application_date' => date('Y-m-d H:i:s'),
            'application_fee' => $fees['application_fee'],
            'inspection_fee' => $fees['inspection_fee'],
            'annual_fee' => $fees['annual_fee'],
            'requires_inspection' => $applicationData['requires_inspection'] ?? true,
            'requires_training' => $applicationData['requires_training'] ?? false,
            'documents' => $applicationData['documents'] ?? [],
            'notes' => $applicationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveHealthSafetyApplication($application);

        // Start workflow
        $this->startApplicationWorkflow($applicationNumber);

        // Send notification
        $this->sendNotification('application_submitted', $applicationData['applicant_id'], [
            'application_number' => $applicationNumber,
            'business_name' => $applicationData['business_name']
        ]);

        return [
            'success' => true,
            'application_number' => $applicationNumber,
            'risk_level' => $riskLevel,
            'processing_days' => $processingDays,
            'total_fees' => $fees['total'],
            'message' => 'Health and safety application submitted successfully'
        ];
    }

    /**
     * Create health safety inspection
     */
    public function createHealthSafetyInspection(array $inspectionData): array
    {
        // Validate inspection data
        $validation = $this->validateHealthSafetyInspection($inspectionData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate inspection number
        $inspectionNumber = $this->generateInspectionNumber();

        // Create inspection record
        $inspection = [
            'inspection_number' => $inspectionNumber,
            'application_id' => $inspectionData['application_id'],
            'inspector_id' => $inspectionData['inspector_id'],
            'inspection_type' => $inspectionData['inspection_type'],
            'scheduled_date' => $inspectionData['scheduled_date'],
            'status' => 'scheduled',
            'inspection_report' => $inspectionData['inspection_report'] ?? [],
            'attachments' => $inspectionData['attachments'] ?? [],
            'notes' => $inspectionData['notes'] ?? ''
        ];

        // Save to database
        $this->saveHealthSafetyInspection($inspection);

        // Update application status
        $this->updateApplicationStatus($inspectionData['application_id'], 'inspection_scheduled');

        // Send notification
        $application = $this->getHealthSafetyApplicationById($inspectionData['application_id']);
        $this->sendNotification('inspection_scheduled', $application['applicant_id'], [
            'business_name' => $application['business_name'],
            'inspection_date' => $inspectionData['scheduled_date']
        ]);

        return [
            'success' => true,
            'inspection_number' => $inspectionNumber,
            'message' => 'Health and safety inspection scheduled successfully'
        ];
    }

    /**
     * Report health safety incident
     */
    public function reportHealthSafetyIncident(array $incidentData): array
    {
        // Validate incident data
        $validation = $this->validateHealthSafetyIncident($incidentData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate incident number
        $incidentNumber = $this->generateIncidentNumber();

        // Create incident record
        $incident = [
            'incident_number' => $incidentNumber,
            'application_id' => $incidentData['application_id'] ?? null,
            'reporter_id' => $incidentData['reporter_id'],
            'incident_type' => $incidentData['incident_type'],
            'severity' => $incidentData['severity'],
            'incident_date' => $incidentData['incident_date'],
            'incident_time' => $incidentData['incident_time'] ?? null,
            'location' => $incidentData['location'],
            'description' => $incidentData['description'],
            'immediate_actions' => $incidentData['immediate_actions'] ?? '',
            'injuries' => $incidentData['injuries'] ?? 0,
            'fatalities' => $incidentData['fatalities'] ?? 0,
            'property_damage' => $incidentData['property_damage'] ?? 0.00,
            'environmental_impact' => $incidentData['environmental_impact'] ?? '',
            'witnesses' => $incidentData['witnesses'] ?? [],
            'investigation_required' => $incidentData['investigation_required'] ?? true,
            'status' => 'reported',
            'attachments' => $incidentData['attachments'] ?? []
        ];

        // Save to database
        $this->saveHealthSafetyIncident($incident);

        // Start incident workflow
        $this->startIncidentWorkflow($incidentNumber);

        // Send notification
        $this->sendNotification('incident_reported', $incidentData['reporter_id'], [
            'incident_type' => $incidentData['incident_type'],
            'severity' => $incidentData['severity']
        ]);

        return [
            'success' => true,
            'incident_number' => $incidentNumber,
            'message' => 'Health and safety incident reported successfully'
        ];
    }

    /**
     * Issue health safety certificate
     */
    public function issueHealthSafetyCertificate(array $certificateData): array
    {
        // Validate certificate data
        $validation = $this->validateHealthSafetyCertificate($certificateData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate certificate number
        $certificateNumber = $this->generateCertificateNumber();

        // Calculate expiry date
        $expiryDate = date('Y-m-d', strtotime("+{$this->config['certificate_validity_years']} years"));

        // Create certificate record
        $certificate = [
            'certificate_number' => $certificateNumber,
            'application_id' => $certificateData['application_id'],
            'certificate_type' => $certificateData['certificate_type'],
            'issue_date' => date('Y-m-d'),
            'expiry_date' => $expiryDate,
            'issued_by' => $certificateData['issued_by'],
            'conditions' => $certificateData['conditions'] ?? '',
            'certificate_document' => $certificateData['certificate_document'] ?? '',
            'qr_code' => $certificateData['qr_code'] ?? ''
        ];

        // Save to database
        $this->saveHealthSafetyCertificate($certificate);

        // Update application with certificate info
        $this->updateHealthSafetyApplication($certificateData['application_id'], [
            'status' => 'approved',
            'approval_date' => date('Y-m-d H:i:s'),
            'certificate_number' => $certificateNumber,
            'expiry_date' => $expiryDate
        ]);

        // Send notification
        $application = $this->getHealthSafetyApplicationById($certificateData['application_id']);
        $this->sendNotification('certificate_issued', $application['applicant_id'], [
            'certificate_number' => $certificateNumber,
            'business_name' => $application['business_name'],
            'expiry_date' => $expiryDate
        ]);

        return [
            'success' => true,
            'certificate_number' => $certificateNumber,
            'expiry_date' => $expiryDate,
            'message' => 'Health and safety certificate issued successfully'
        ];
    }

    /**
     * Get health safety applications (API handler)
     */
    public function getHealthSafetyApplications(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM health_safety_applications WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['application_type'])) {
                $sql .= " AND application_type = ?";
                $params[] = $filters['application_type'];
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
            error_log("Error getting health safety applications: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve health safety applications'
            ];
        }
    }

    /**
     * Get health safety application (API handler)
     */
    public function getHealthSafetyApplication(string $applicationNumber): array
    {
        $application = $this->getHealthSafetyApplication($applicationNumber);

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
     * Create health safety application (API handler)
     */
    public function createHealthSafetyApplicationApi(array $data): array
    {
        return $this->createHealthSafetyApplication($data);
    }

    /**
     * Update health safety application (API handler)
     */
    public function updateHealthSafetyApplication(string $applicationNumber, array $data): array
    {
        try {
            $application = $this->getHealthSafetyApplication($applicationNumber);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            $this->updateHealthSafetyApplication($application['id'], $data);

            return [
                'success' => true,
                'message' => 'Application updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating application: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update application'
            ];
        }
    }

    /**
     * Approve health safety application (API handler)
     */
    public function approveHealthSafetyApplication(string $applicationNumber, array $data): array
    {
        try {
            $application = $this->getHealthSafetyApplication($applicationNumber);

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            // Issue certificate
            $certificateData = array_merge($data, [
                'application_id' => $application['id'],
                'certificate_type' => $application['application_type'],
                'issued_by' => $data['approved_by'] ?? 1
            ]);

            return $this->issueHealthSafetyCertificate($certificateData);
        } catch (\Exception $e) {
            error_log("Error approving application: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to approve application'
            ];
        }
    }

    /**
     * Get health safety inspections (API handler)
     */
    public function getHealthSafetyInspections(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM health_safety_inspections WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['inspector_id'])) {
                $sql .= " AND inspector_id = ?";
                $params[] = $filters['inspector_id'];
            }

            $sql .= " ORDER BY scheduled_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['inspection_report'] = json_decode($result['inspection_report'], true);
                $result['attachments'] = json_decode($result['attachments'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting health safety inspections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve health safety inspections'
            ];
        }
    }

    /**
     * Create health safety inspection (API handler)
     */
    public function createHealthSafetyInspection(array $data): array
    {
        return $this->createHealthSafetyInspection($data);
    }

    /**
     * Get health safety certificates (API handler)
     */
    public function getHealthSafetyCertificates(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM health_safety_certificates WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY issue_date DESC";

            $results = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting health safety certificates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve health safety certificates'
            ];
        }
    }

    /**
     * Issue health safety certificate (API handler)
     */
    public function issueHealthSafetyCertificate(array $data): array
    {
        return $this->issueHealthSafetyCertificate($data);
    }

    /**
     * Get health safety incidents (API handler)
     */
    public function getHealthSafetyIncidents(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM health_safety_incidents WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['incident_type'])) {
                $sql .= " AND incident_type = ?";
                $params[] = $filters['incident_type'];
            }

            $sql .= " ORDER BY incident_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['witnesses'] = json_decode($result['witnesses'], true);
                $result['attachments'] = json_decode($result['attachments'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting health safety incidents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve health safety incidents'
            ];
        }
    }

    /**
     * Report health safety incident (API handler)
     */
    public function reportHealthSafetyIncident(array $data): array
    {
        return $this->reportHealthSafetyIncident($data);
    }

    /**
     * Get health safety training (API handler)
     */
    public function getHealthSafetyTraining(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM health_safety_training WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['training_type'])) {
                $sql .= " AND training_type = ?";
                $params[] = $filters['training_type'];
            }

            $sql .= " ORDER BY training_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['participants'] = json_decode($result['participants'], true);
                $result['training_topics'] = json_decode($result['training_topics'], true);
                $result['training_materials'] = json_decode($result['training_materials'], true);
                $result['assessment_results'] = json_decode($result['assessment_results'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting health safety training: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve health safety training records'
            ];
        }
    }

    /**
     * Schedule health safety training (API handler)
     */
    public function scheduleHealthSafetyTraining(array $data): array
    {
        try {
            // Validate training data
            $validation = $this->validateHealthSafetyTraining($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Create training record
            $training = [
                'application_id' => $data['application_id'],
                'training_type' => $data['training_type'],
                'training_provider' => $data['training_provider'],
                'instructor_name' => $data['instructor_name'],
                'training_date' => $data['training_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'participants' => $data['participants'] ?? [],
                'training_topics' => $data['training_topics'] ?? [],
                'status' => 'scheduled',
                'training_materials' => $data['training_materials'] ?? [],
                'notes' => $data['notes'] ?? ''
            ];

            // Save to database
            $this->saveHealthSafetyTraining($training);

            // Send notification
            $application = $this->getHealthSafetyApplicationById($data['application_id']);
            $this->sendNotification('training_required', $application['applicant_id'], [
                'business_name' => $application['business_name'],
                'training_type' => $data['training_type']
            ]);

            return [
                'success' => true,
                'training_id' => $this->getLastInsertId(),
                'message' => 'Health and safety training scheduled successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error scheduling training: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to schedule health and safety training'
            ];
        }
    }

    /**
     * Validate health safety application data
     */
    private function validateHealthSafetyApplication(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'applicant_id', 'application_type', 'business_name',
            'business_address', 'contact_name', 'contact_phone', 'contact_email'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['application_type'] ?? '', array_keys($this->applicationTypes))) {
            $errors[] = "Invalid application type";
        }

        if (!in_array($data['risk_level'] ?? '', ['low', 'medium', 'high', 'critical'])) {
            $errors[] = "Invalid risk level";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate health safety inspection data
     */
    private function validateHealthSafetyInspection(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'application_id', 'inspector_id', 'inspection_type', 'scheduled_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['inspection_type'] ?? '', ['initial', 'follow_up', 'compliance', 're_inspection', 'complaint_based', 'random'])) {
            $errors[] = "Invalid inspection type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate health safety incident data
     */
    private function validateHealthSafetyIncident(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'reporter_id', 'incident_type', 'severity', 'incident_date', 'location', 'description'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['incident_type'] ?? '', ['accident', 'near_miss', 'hazard', 'complaint', 'illness', 'injury', 'property_damage', 'environmental', 'other'])) {
            $errors[] = "Invalid incident type";
        }

        if (!in_array($data['severity'] ?? '', ['minor', 'moderate', 'major', 'critical', 'fatal'])) {
            $errors[] = "Invalid severity level";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate health safety certificate data
     */
    private function validateHealthSafetyCertificate(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'application_id', 'certificate_type', 'issued_by'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate health safety training data
     */
    private function validateHealthSafetyTraining(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'application_id', 'training_type', 'training_provider',
            'instructor_name', 'training_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!in_array($data['training_type'] ?? '', ['food_safety', 'workplace_safety', 'first_aid', 'hazard_communication', 'emergency_response', 'equipment_specific', 'other'])) {
            $errors[] = "Invalid training type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Assess risk level based on application data
     */
    private function assessRiskLevel(array $data): string
    {
        $employeeCount = $data['employee_count'] ?? 0;
        $facilitySize = $this->parseFacilitySize($data['facility_size'] ?? 'small');

        if ($employeeCount >= $this->riskCriteria['critical']['employee_threshold'] ||
            $facilitySize >= $this->riskCriteria['critical']['facility_size_threshold']) {
            return 'critical';
        } elseif ($employeeCount >= $this->riskCriteria['high']['employee_threshold'] ||
                  $facilitySize >= $this->riskCriteria['high']['facility_size_threshold']) {
            return 'high';
        } elseif ($employeeCount >= $this->riskCriteria['medium']['employee_threshold'] ||
                  $facilitySize >= $this->riskCriteria['medium']['facility_size_threshold']) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Parse facility size string to numeric value
     */
    private function parseFacilitySize(string $size): int
    {
        $sizeMap = [
            'small' => 500,
            'medium' => 2000,
            'large' => 5000,
            'extra_large' => 10000
        ];

        return $sizeMap[$size] ?? 500;
    }

    /**
     * Calculate processing days based on risk level
     */
    private function calculateProcessingDays(string $riskLevel): int
    {
        $days = [
            'low' => $this->config['processing_days_low_risk'],
            'medium' => $this->config['processing_days_medium_risk'],
            'high' => $this->config['processing_days_high_risk'],
            'critical' => $this->config['processing_days_critical_risk']
        ];

        return $days[$riskLevel] ?? 30;
    }

    /**
     * Calculate application fees
     */
    private function calculateApplicationFees(string $applicationType, string $riskLevel): array
    {
        $baseFees = [
            'low' => $this->config['application_fee_base'],
            'medium' => $this->config['application_fee_base'] * 1.5,
            'high' => $this->config['application_fee_base'] * 2.0,
            'critical' => $this->config['application_fee_base'] * 3.0
        ];

        $applicationFee = $baseFees[$riskLevel] ?? $this->config['application_fee_base'];
        $inspectionFee = $this->config['inspection_fee_base'];
        $annualFee = $this->config['annual_fee_base'];

        return [
            'application_fee' => $applicationFee,
            'inspection_fee' => $inspectionFee,
            'annual_fee' => $annualFee,
            'total' => $applicationFee + $inspectionFee + $annualFee
        ];
    }

    /**
     * Generate application number
     */
    private function generateApplicationNumber(): string
    {
        return 'HS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate inspection number
     */
    private function generateInspectionNumber(): string
    {
        return 'HI' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate incident number
     */
    private function generateIncidentNumber(): string
    {
        return 'IN' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate certificate number
     */
    private function generateCertificateNumber(): string
    {
        return 'HC' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update application status
     */
    private function updateApplicationStatus(int $applicationId, string $status): bool
    {
        try {
            $db = Database::getInstance();
            $db->update('health_safety_applications', ['status' => $status], ['id' => $applicationId]);
            return true;
        } catch (\Exception $e) {
            error_log("Error updating application status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Placeholder methods (would be implemented with actual database operations)
     */
    private function saveHealthSafetyApplication(array $application): bool { return true; }
    private function startApplicationWorkflow(string $applicationNumber): bool { return true; }
    private function sendNotification(string $type, ?int $userId, array $data): bool { return true; }
    private function getHealthSafetyApplication(string $applicationNumber): ?array { return null; }
    private function updateHealthSafetyApplication(int $applicationId, array $data): bool { return true; }
    private function getHealthSafetyApplicationById(int $applicationId): ?array { return null; }
    private function saveHealthSafetyInspection(array $inspection): bool { return true; }
    private function saveHealthSafetyIncident(array $incident): bool { return true; }
    private function startIncidentWorkflow(string $incidentNumber): bool { return true; }
    private function saveHealthSafetyCertificate(array $certificate): bool { return true; }
    private function saveHealthSafetyTraining(array $training): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_applications' => 0, // Would query database
            'active_certificates' => 0,
            'pending_inspections' => 0,
            'completed_inspections' => 0,
            'reported_incidents' => 0,
            'training_sessions' => 0,
            'compliance_rate' => 0.00,
            'average_processing_time' => 0
        ];
    }
}

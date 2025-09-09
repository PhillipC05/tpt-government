<?php
/**
 * TPT Government Platform - Health Services Module
 *
 * Comprehensive health services management system supporting appointment booking,
 * medical record management, vaccination tracking, prescription management,
 * and telehealth integration
 */

namespace Modules\HealthServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;
use Core\AdvancedAnalytics;

class HealthServicesModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Health Services',
        'version' => '2.1.0',
        'description' => 'Comprehensive health services management and patient administration system',
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
        'health.view' => 'View health records and information',
        'health.book' => 'Book health appointments',
        'health.prescribe' => 'Prescribe medications and treatments',
        'health.records' => 'Access and manage medical records',
        'health.vaccinate' => 'Administer vaccinations',
        'health.emergency' => 'Access emergency health services',
        'health.admin' => 'Administrative health services functions',
        'health.report' => 'Generate health service reports'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'patients' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'patient_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'registration_date' => 'DATE NOT NULL',
            'patient_status' => "ENUM('active','inactive','deceased','transferred') DEFAULT 'active'",
            'blood_type' => "ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') DEFAULT 'unknown'",
            'emergency_contact_name' => 'VARCHAR(100)',
            'emergency_contact_phone' => 'VARCHAR(20)',
            'emergency_contact_relationship' => 'VARCHAR(50)',
            'preferred_language' => 'VARCHAR(50) DEFAULT \'English\'',
            'insurance_provider' => 'VARCHAR(100)',
            'insurance_number' => 'VARCHAR(50)',
            'allergies' => 'JSON',
            'chronic_conditions' => 'JSON',
            'current_medications' => 'JSON',
            'family_medical_history' => 'JSON',
            'lifestyle_factors' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'healthcare_providers' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'provider_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'provider_type' => "ENUM('doctor','nurse','specialist','dentist','pharmacist','therapist','other') NOT NULL",
            'specialization' => 'VARCHAR(100)',
            'license_number' => 'VARCHAR(50) UNIQUE NOT NULL',
            'license_expiry' => 'DATE NOT NULL',
            'practice_location' => 'INT',
            'availability_schedule' => 'JSON',
            'consultation_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'emergency_contact' => 'VARCHAR(100)',
            'qualifications' => 'JSON',
            'certifications' => 'JSON',
            'status' => "ENUM('active','inactive','suspended','retired') DEFAULT 'active'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'healthcare_facilities' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'facility_code' => 'VARCHAR(10) UNIQUE NOT NULL',
            'facility_name' => 'VARCHAR(255) NOT NULL',
            'facility_type' => "ENUM('hospital','clinic','pharmacy','laboratory','emergency','specialist_center','other') NOT NULL",
            'address' => 'TEXT NOT NULL',
            'contact_details' => 'JSON',
            'operating_hours' => 'JSON',
            'services_offered' => 'JSON',
            'capacity' => 'INT',
            'current_occupancy' => 'INT DEFAULT 0',
            'emergency_services' => 'BOOLEAN DEFAULT FALSE',
            'accreditation_status' => "ENUM('accredited','pending','suspended','revoked') DEFAULT 'accredited'",
            'equipment_inventory' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'appointments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'appointment_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'facility_id' => 'INT NOT NULL',
            'appointment_date' => 'DATETIME NOT NULL',
            'appointment_type' => "ENUM('consultation','follow_up','emergency','vaccination','screening','therapy','other') NOT NULL",
            'appointment_status' => "ENUM('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled'",
            'reason_for_visit' => 'TEXT',
            'urgency_level' => "ENUM('routine','urgent','emergency') DEFAULT 'routine'",
            'estimated_duration' => 'INT DEFAULT 30', // minutes
            'actual_duration' => 'INT',
            'consultation_fee' => 'DECIMAL(8,2)',
            'payment_status' => "ENUM('pending','paid','waived','insurance') DEFAULT 'pending'",
            'notes' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATETIME',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'medical_records' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'record_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'appointment_id' => 'INT',
            'record_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'record_type' => "ENUM('consultation','diagnosis','treatment','prescription','test_result','vaccination','surgery','other') NOT NULL",
            'chief_complaint' => 'TEXT',
            'history_of_present_illness' => 'TEXT',
            'physical_examination' => 'JSON',
            'diagnosis' => 'JSON',
            'treatment_plan' => 'JSON',
            'medications_prescribed' => 'JSON',
            'lab_tests_ordered' => 'JSON',
            'imaging_studies' => 'JSON',
            'referrals' => 'JSON',
            'follow_up_instructions' => 'TEXT',
            'confidential' => 'BOOLEAN DEFAULT FALSE',
            'blockchain_hash' => 'VARCHAR(128)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'prescriptions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'prescription_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'appointment_id' => 'INT',
            'medication_name' => 'VARCHAR(255) NOT NULL',
            'generic_name' => 'VARCHAR(255)',
            'dosage' => 'VARCHAR(100) NOT NULL',
            'frequency' => 'VARCHAR(100) NOT NULL',
            'duration' => 'VARCHAR(100)',
            'quantity' => 'INT NOT NULL',
            'refills_allowed' => 'INT DEFAULT 0',
            'refills_remaining' => 'INT DEFAULT 0',
            'instructions' => 'TEXT',
            'side_effects' => 'TEXT',
            'interactions' => 'TEXT',
            'prescription_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'status' => "ENUM('active','completed','cancelled','expired') DEFAULT 'active'",
            'pharmacy_id' => 'INT',
            'dispensed_date' => 'DATETIME',
            'verification_code' => 'VARCHAR(50) UNIQUE',
            'blockchain_hash' => 'VARCHAR(128)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'vaccinations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'vaccination_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'vaccine_name' => 'VARCHAR(255) NOT NULL',
            'vaccine_type' => 'VARCHAR(100)',
            'manufacturer' => 'VARCHAR(100)',
            'batch_number' => 'VARCHAR(50)',
            'vaccination_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'dosage' => 'VARCHAR(50)',
            'administration_site' => 'VARCHAR(50)',
            'next_due_date' => 'DATE',
            'adverse_reactions' => 'TEXT',
            'certificate_issued' => 'BOOLEAN DEFAULT FALSE',
            'certificate_number' => 'VARCHAR(50)',
            'verification_code' => 'VARCHAR(50) UNIQUE',
            'blockchain_hash' => 'VARCHAR(128)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'lab_tests' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'test_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'appointment_id' => 'INT',
            'test_type' => 'VARCHAR(100) NOT NULL',
            'test_category' => "ENUM('blood','urine','imaging','cardiac','respiratory','other') NOT NULL",
            'test_name' => 'VARCHAR(255) NOT NULL',
            'specimen_type' => 'VARCHAR(100)',
            'collection_date' => 'DATETIME',
            'lab_facility_id' => 'INT',
            'results' => 'JSON',
            'reference_ranges' => 'JSON',
            'interpretation' => 'TEXT',
            'status' => "ENUM('ordered','collected','processing','completed','cancelled') DEFAULT 'ordered'",
            'urgent' => 'BOOLEAN DEFAULT FALSE',
            'cost' => 'DECIMAL(8,2)',
            'payment_status' => "ENUM('pending','paid','waived','insurance') DEFAULT 'pending'",
            'report_date' => 'DATETIME',
            'verified_by' => 'INT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'telehealth_sessions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'session_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'appointment_id' => 'INT',
            'session_type' => "ENUM('video','audio','chat','screen_share') DEFAULT 'video'",
            'session_date' => 'DATETIME NOT NULL',
            'duration' => 'INT', // minutes
            'platform_used' => 'VARCHAR(50)',
            'session_link' => 'VARCHAR(500)',
            'recording_available' => 'BOOLEAN DEFAULT FALSE',
            'recording_path' => 'VARCHAR(500)',
            'consultation_summary' => 'TEXT',
            'prescription_issued' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'satisfaction_rating' => 'TINYINT',
            'technical_issues' => 'TEXT',
            'status' => "ENUM('scheduled','in_progress','completed','cancelled','failed') DEFAULT 'scheduled'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'health_insurance_claims' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'claim_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'patient_id' => 'INT NOT NULL',
            'provider_id' => 'INT NOT NULL',
            'appointment_id' => 'INT',
            'insurance_provider' => 'VARCHAR(100) NOT NULL',
            'policy_number' => 'VARCHAR(50) NOT NULL',
            'service_date' => 'DATE NOT NULL',
            'diagnosis_codes' => 'JSON',
            'procedure_codes' => 'JSON',
            'claimed_amount' => 'DECIMAL(10,2) NOT NULL',
            'approved_amount' => 'DECIMAL(10,2)',
            'paid_amount' => 'DECIMAL(10,2)',
            'denied_amount' => 'DECIMAL(10,2)',
            'denial_reason' => 'TEXT',
            'status' => "ENUM('submitted','processing','approved','partially_approved','denied','paid','appealed') DEFAULT 'submitted'",
            'submission_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'processing_date' => 'DATETIME',
            'payment_date' => 'DATETIME',
            'appeal_deadline' => 'DATE',
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
            'path' => '/api/health/patients',
            'handler' => 'getPatients',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health/patients',
            'handler' => 'registerPatient',
            'auth' => true,
            'permissions' => ['health.admin']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/patients/{id}',
            'handler' => 'getPatient',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health/appointments',
            'handler' => 'bookAppointment',
            'auth' => true,
            'permissions' => ['health.book']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/appointments',
            'handler' => 'getAppointments',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/appointments/{id}',
            'handler' => 'getAppointment',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/health/appointments/{id}',
            'handler' => 'updateAppointment',
            'auth' => true,
            'permissions' => ['health.admin']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health/records',
            'handler' => 'createMedicalRecord',
            'auth' => true,
            'permissions' => ['health.records']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/records/{patient_id}',
            'handler' => 'getMedicalRecords',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health/prescriptions',
            'handler' => 'createPrescription',
            'auth' => true,
            'permissions' => ['health.prescribe']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/prescriptions/{patient_id}',
            'handler' => 'getPrescriptions',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health/vaccinations',
            'handler' => 'recordVaccination',
            'auth' => true,
            'permissions' => ['health.vaccinate']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/vaccinations/{patient_id}',
            'handler' => 'getVaccinations',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/health/telehealth',
            'handler' => 'scheduleTelehealthSession',
            'auth' => true,
            'permissions' => ['health.book']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/providers',
            'handler' => 'getProviders',
            'auth' => true,
            'permissions' => ['health.view']
        ],
        [
            'method' => 'GET',
            'path' => '/api/health/facilities',
            'handler' => 'getFacilities',
            'auth' => true,
            'permissions' => ['health.view']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'patient_registration_process' => [
            'name' => 'Patient Registration Process',
            'description' => 'Workflow for patient registration and verification',
            'steps' => [
                'application_submitted' => ['name' => 'Application Submitted', 'next' => 'document_verification'],
                'document_verification' => ['name' => 'Document Verification', 'next' => 'medical_history_review'],
                'medical_history_review' => ['name' => 'Medical History Review', 'next' => 'insurance_verification'],
                'insurance_verification' => ['name' => 'Insurance Verification', 'next' => 'registration_complete'],
                'registration_complete' => ['name' => 'Registration Complete', 'next' => 'initial_consultation'],
                'initial_consultation' => ['name' => 'Initial Consultation Scheduled', 'next' => 'active'],
                'active' => ['name' => 'Patient Active', 'next' => null]
            ]
        ],
        'appointment_booking_process' => [
            'name' => 'Appointment Booking Process',
            'description' => 'Workflow for appointment scheduling and confirmation',
            'steps' => [
                'appointment_requested' => ['name' => 'Appointment Requested', 'next' => 'provider_availability_check'],
                'provider_availability_check' => ['name' => 'Provider Availability Check', 'next' => 'facility_availability_check'],
                'facility_availability_check' => ['name' => 'Facility Availability Check', 'next' => 'insurance_preapproval'],
                'insurance_preapproval' => ['name' => 'Insurance Pre-approval', 'next' => 'appointment_confirmed'],
                'appointment_confirmed' => ['name' => 'Appointment Confirmed', 'next' => 'reminder_sent'],
                'reminder_sent' => ['name' => 'Reminder Sent', 'next' => 'appointment_completed'],
                'appointment_completed' => ['name' => 'Appointment Completed', 'next' => 'follow_up_scheduled'],
                'follow_up_scheduled' => ['name' => 'Follow-up Scheduled', 'next' => null]
            ]
        ],
        'prescription_management_process' => [
            'name' => 'Prescription Management Process',
            'description' => 'Workflow for prescription creation and dispensing',
            'steps' => [
                'prescription_written' => ['name' => 'Prescription Written', 'next' => 'drug_interaction_check'],
                'drug_interaction_check' => ['name' => 'Drug Interaction Check', 'next' => 'allergy_check'],
                'allergy_check' => ['name' => 'Allergy Check', 'next' => 'insurance_authorization'],
                'insurance_authorization' => ['name' => 'Insurance Authorization', 'next' => 'ready_for_pickup'],
                'ready_for_pickup' => ['name' => 'Ready for Pickup', 'next' => 'dispensed'],
                'dispensed' => ['name' => 'Dispensed', 'next' => 'patient_education'],
                'patient_education' => ['name' => 'Patient Education Completed', 'next' => 'active'],
                'active' => ['name' => 'Prescription Active', 'next' => null]
            ]
        ],
        'vaccination_process' => [
            'name' => 'Vaccination Process',
            'description' => 'Workflow for vaccination administration and tracking',
            'steps' => [
                'vaccination_scheduled' => ['name' => 'Vaccination Scheduled', 'next' => 'consent_obtained'],
                'consent_obtained' => ['name' => 'Consent Obtained', 'next' => 'pre_vaccination_screening'],
                'pre_vaccination_screening' => ['name' => 'Pre-vaccination Screening', 'next' => 'vaccination_administered'],
                'vaccination_administered' => ['name' => 'Vaccination Administered', 'next' => 'post_vaccination_monitoring'],
                'post_vaccination_monitoring' => ['name' => 'Post-vaccination Monitoring', 'next' => 'adverse_reactions_check'],
                'adverse_reactions_check' => ['name' => 'Adverse Reactions Check', 'next' => 'certificate_issued'],
                'certificate_issued' => ['name' => 'Certificate Issued', 'next' => 'follow_up_scheduled'],
                'follow_up_scheduled' => ['name' => 'Follow-up Scheduled', 'next' => 'completed'],
                'completed' => ['name' => 'Vaccination Completed', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'patient_registration_form' => [
            'name' => 'Patient Registration Form',
            'fields' => [
                'person_id' => ['type' => 'hidden', 'required' => true],
                'blood_type' => ['type' => 'select', 'required' => false, 'label' => 'Blood Type'],
                'emergency_contact_name' => ['type' => 'text', 'required' => true, 'label' => 'Emergency Contact Name'],
                'emergency_contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Emergency Contact Phone'],
                'emergency_contact_relationship' => ['type' => 'text', 'required' => true, 'label' => 'Relationship'],
                'preferred_language' => ['type' => 'select', 'required' => false, 'label' => 'Preferred Language'],
                'insurance_provider' => ['type' => 'text', 'required' => false, 'label' => 'Insurance Provider'],
                'insurance_number' => ['type' => 'text', 'required' => false, 'label' => 'Insurance Number'],
                'allergies' => ['type' => 'textarea', 'required' => false, 'label' => 'Known Allergies'],
                'current_medications' => ['type' => 'textarea', 'required' => false, 'label' => 'Current Medications'],
                'chronic_conditions' => ['type' => 'textarea', 'required' => false, 'label' => 'Chronic Conditions']
            ],
            'sections' => [
                'personal_information' => ['title' => 'Personal Information', 'required' => true],
                'emergency_contacts' => ['title' => 'Emergency Contacts', 'required' => true],
                'insurance_information' => ['title' => 'Insurance Information', 'required' => false],
                'medical_history' => ['title' => 'Medical History', 'required' => false]
            ],
            'documents' => [
                'identification' => ['required' => true, 'label' => 'Identification Documents'],
                'insurance_card' => ['required' => false, 'label' => 'Insurance Card'],
                'medical_records' => ['required' => false, 'label' => 'Previous Medical Records']
            ]
        ],
        'appointment_booking_form' => [
            'name' => 'Appointment Booking Form',
            'fields' => [
                'patient_id' => ['type' => 'hidden', 'required' => true],
                'provider_id' => ['type' => 'select', 'required' => true, 'label' => 'Healthcare Provider'],
                'facility_id' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Facility'],
                'appointment_type' => ['type' => 'select', 'required' => true, 'label' => 'Appointment Type'],
                'preferred_date' => ['type' => 'date', 'required' => true, 'label' => 'Preferred Date'],
                'preferred_time' => ['type' => 'time', 'required' => false, 'label' => 'Preferred Time'],
                'urgency_level' => ['type' => 'select', 'required' => true, 'label' => 'Urgency Level'],
                'reason_for_visit' => ['type' => 'textarea', 'required' => true, 'label' => 'Reason for Visit'],
                'insurance_coverage' => ['type' => 'checkbox', 'required' => false, 'label' => 'Use Insurance Coverage']
            ],
            'documents' => [
                'referral_letter' => ['required' => false, 'label' => 'Referral Letter'],
                'medical_reports' => ['required' => false, 'label' => 'Medical Reports']
            ]
        ],
        'prescription_request_form' => [
            'name' => 'Prescription Request Form',
            'fields' => [
                'patient_id' => ['type' => 'hidden', 'required' => true],
                'medication_name' => ['type' => 'text', 'required' => true, 'label' => 'Medication Name'],
                'dosage' => ['type' => 'text', 'required' => true, 'label' => 'Dosage'],
                'frequency' => ['type' => 'text', 'required' => true, 'label' => 'Frequency'],
                'duration' => ['type' => 'text', 'required' => false, 'label' => 'Duration'],
                'quantity' => ['type' => 'number', 'required' => true, 'label' => 'Quantity', 'min' => '1'],
                'refills' => ['type' => 'number', 'required' => false, 'label' => 'Refills Needed', 'min' => '0'],
                'reason' => ['type' => 'textarea', 'required' => true, 'label' => 'Reason for Prescription'],
                'pharmacy_preference' => ['type' => 'select', 'required' => false, 'label' => 'Preferred Pharmacy']
            ],
            'documents' => [
                'prescription_form' => ['required' => true, 'label' => 'Prescription Form'],
                'lab_results' => ['required' => false, 'label' => 'Lab Results']
            ]
        ],
        'vaccination_consent_form' => [
            'name' => 'Vaccination Consent Form',
            'fields' => [
                'patient_id' => ['type' => 'hidden', 'required' => true],
                'vaccine_name' => ['type' => 'select', 'required' => true, 'label' => 'Vaccine'],
                'consent_given' => ['type' => 'checkbox', 'required' => true, 'label' => 'I consent to vaccination'],
                'guardian_consent' => ['type' => 'checkbox', 'required' => false, 'label' => 'Guardian Consent (if minor)'],
                'medical_conditions' => ['type' => 'textarea', 'required' => false, 'label' => 'Current Medical Conditions'],
                'previous_reactions' => ['type' => 'textarea', 'required' => false, 'label' => 'Previous Vaccine Reactions'],
                'emergency_contact' => ['type' => 'text', 'required' => true, 'label' => 'Emergency Contact'],
                'emergency_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Emergency Phone']
            ],
            'sections' => [
                'consent' => ['title' => 'Consent', 'required' => true],
                'medical_information' => ['title' => 'Medical Information', 'required' => false],
                'emergency_contacts' => ['title' => 'Emergency Contacts', 'required' => true]
            ],
            'documents' => [
                'consent_form' => ['required' => true, 'label' => 'Signed Consent Form'],
                'medical_history' => ['required' => false, 'label' => 'Medical History']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'patient_demographics_report' => [
            'name' => 'Patient Demographics Report',
            'description' => 'Patient demographic statistics and trends',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'facility_id' => ['type' => 'select', 'required' => false],
                'age_group' => ['type' => 'select', 'required' => false],
                'gender' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_patients', 'new_registrations', 'age_distribution',
                'gender_distribution', 'ethnicity_distribution', 'insurance_coverage'
            ]
        ],
        'appointment_utilization_report' => [
            'name' => 'Appointment Utilization Report',
            'description' => 'Healthcare appointment booking and utilization statistics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'facility_id' => ['type' => 'select', 'required' => false],
                'provider_id' => ['type' => 'select', 'required' => false],
                'appointment_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_appointments', 'completed_appointments', 'no_show_rate',
                'cancellation_rate', 'average_wait_time', 'utilization_rate'
            ]
        ],
        'health_outcomes_report' => [
            'name' => 'Health Outcomes Report',
            'description' => 'Patient health outcomes and treatment effectiveness',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'condition_type' => ['type' => 'select', 'required' => false],
                'treatment_type' => ['type' => 'select', 'required' => false],
                'facility_id' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'condition_prevalence', 'treatment_success_rate',
                'recovery_time', 'complication_rate', 'readmission_rate'
            ]
        ],
        'vaccination_coverage_report' => [
            'name' => 'Vaccination Coverage Report',
            'description' => 'Vaccination coverage and compliance statistics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'vaccine_type' => ['type' => 'select', 'required' => false],
                'age_group' => ['type' => 'select', 'required' => false],
                'facility_id' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'vaccination_rate', 'coverage_by_age', 'coverage_by_vaccine',
                'adverse_reactions', 'compliance_rate', 'target_achievement'
            ]
        ],
        'prescription_trends_report' => [
            'name' => 'Prescription Trends Report',
            'description' => 'Medication prescription patterns and trends',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'medication_type' => ['type' => 'select', 'required' => false],
                'provider_id' => ['type' => 'select', 'required' => false],
                'age_group' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'most_prescribed_medications', 'prescription_volume',
                'cost_trends', 'generic_vs_brand', 'adherence_rates'
            ]
        ],
        'telehealth_utilization_report' => [
            'name' => 'Telehealth Utilization Report',
            'description' => 'Telehealth service usage and effectiveness',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'service_type' => ['type' => 'select', 'required' => false],
                'provider_id' => ['type' => 'select', 'required' => false],
                'patient_demographics' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_sessions', 'session_types', 'completion_rate',
                'patient_satisfaction', 'cost_savings', 'technical_issues'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'appointment_confirmed' => [
            'name' => 'Appointment Confirmed',
            'template' => 'Your appointment with {provider_name} is confirmed for {appointment_date} at {facility_name}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appointment_booked']
        ],
        'appointment_reminder' => [
            'name' => 'Appointment Reminder',
            'template' => 'Reminder: You have an appointment with {provider_name} tomorrow at {appointment_time}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appointment_reminder']
        ],
        'prescription_ready' => [
            'name' => 'Prescription Ready',
            'template' => 'Your prescription for {medication_name} is ready for pickup at {pharmacy_name}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['prescription_ready']
        ],
        'vaccination_due' => [
            'name' => 'Vaccination Due',
            'template' => 'Your {vaccine_name} vaccination is due on {due_date}. Please schedule an appointment.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['vaccination_due']
        ],
        'lab_results_available' => [
            'name' => 'Lab Results Available',
            'template' => 'Your lab results for {test_name} are now available. Please log in to view them.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['lab_results_ready']
        ],
        'telehealth_session_scheduled' => [
            'name' => 'Telehealth Session Scheduled',
            'template' => 'Your telehealth session with {provider_name} is scheduled for {session_date}. Join link: {session_link}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['telehealth_scheduled']
        ],
        'insurance_claim_status' => [
            'name' => 'Insurance Claim Status',
            'template' => 'Your insurance claim {claim_number} status has been updated to: {status}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['claim_status_update']
        ],
        'medication_refill_reminder' => [
            'name' => 'Medication Refill Reminder',
            'template' => 'Your prescription for {medication_name} needs to be refilled. Refills remaining: {refills_remaining}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['refill_reminder']
        ]
    ];

    /**
     * Healthcare provider types
     */
    private array $providerTypes = [];

    /**
     * Medical specialties
     */
    private array $specialties = [];

    /**
     * Vaccine schedules
     */
    private array $vaccineSchedules = [];

    /**
     * Common medications
     */
    private array $commonMedications = [];

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
            'default_appointment_duration' => 30, // minutes
            'max_advance_booking_days' => 90,
            'reminder_hours_before' => 24,
            'telehealth_enabled' => true,
            'emergency_services_enabled' => true,
            'vaccination_tracking_enabled' => true,
            'prescription_refill_reminder_days' => 7,
            'consultation_fees' => [
                'general_practitioner' => 75.00,
                'specialist' => 150.00,
                'emergency' => 200.00
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
        $this->initializeProviderTypes();
        $this->initializeSpecialties();
        $this->initializeVaccineSchedules();
        $this->initializeCommonMedications();
    }

    /**
     * Initialize provider types
     */
    private function initializeProviderTypes(): void
    {
        $this->providerTypes = [
            'doctor' => [
                'name' => 'Medical Doctor',
                'qualifications' => ['MD', 'MBBS', 'DO'],
                'specialties_allowed' => true
            ],
            'nurse' => [
                'name' => 'Registered Nurse',
                'qualifications' => ['RN', 'LPN', 'NP'],
                'specialties_allowed' => false
            ],
            'specialist' => [
                'name' => 'Medical Specialist',
                'qualifications' => ['MD with specialization'],
                'specialties_allowed' => true
            ],
            'dentist' => [
                'name' => 'Dentist',
                'qualifications' => ['DDS', 'DMD'],
                'specialties_allowed' => true
            ],
            'pharmacist' => [
                'name' => 'Pharmacist',
                'qualifications' => ['PharmD', 'RPh'],
                'specialties_allowed' => false
            ],
            'therapist' => [
                'name' => 'Therapist',
                'qualifications' => ['PT', 'OT', 'ST'],
                'specialties_allowed' => true
            ]
        ];
    }

    /**
     * Initialize medical specialties
     */
    private function initializeSpecialties(): void
    {
        $this->specialties = [
            'cardiology' => 'Cardiology',
            'dermatology' => 'Dermatology',
            'emergency_medicine' => 'Emergency Medicine',
            'endocrinology' => 'Endocrinology',
            'gastroenterology' => 'Gastroenterology',
            'general_practice' => 'General Practice',
            'geriatrics' => 'Geriatrics',
            'hematology' => 'Hematology',
            'infectious_diseases' => 'Infectious Diseases',
            'internal_medicine' => 'Internal Medicine',
            'nephrology' => 'Nephrology',
            'neurology' => 'Neurology',
            'obstetrics_gynecology' => 'Obstetrics & Gynecology',
            'oncology' => 'Oncology',
            'ophthalmology' => 'Ophthalmology',
            'orthopedics' => 'Orthopedics',
            'pediatrics' => 'Pediatrics',
            'psychiatry' => 'Psychiatry',
            'pulmonology' => 'Pulmonology',
            'radiology' => 'Radiology',
            'rheumatology' => 'Rheumatology',
            'surgery' => 'Surgery',
            'urology' => 'Urology'
        ];
    }

    /**
     * Initialize vaccine schedules
     */
    private function initializeVaccineSchedules(): void
    {
        $this->vaccineSchedules = [
            'covid19' => [
                'name' => 'COVID-19 Vaccine',
                'doses_required' => 2,
                'intervals' => [21], // days between doses
                'booster_required' => true,
                'booster_interval_months' => 6
            ],
            'influenza' => [
                'name' => 'Influenza Vaccine',
                'doses_required' => 1,
                'intervals' => [],
                'booster_required' => true,
                'booster_interval_months' => 12
            ],
            'dtap' => [
                'name' => 'DTaP Vaccine',
                'doses_required' => 5,
                'intervals' => [30, 30, 180, 180], // days between doses
                'booster_required' => true,
                'booster_interval_months' => 120 // 10 years
            ],
            'mmr' => [
                'name' => 'MMR Vaccine',
                'doses_required' => 2,
                'intervals' => [28], // days between doses
                'booster_required' => false,
                'booster_interval_months' => null
            ],
            'polio' => [
                'name' => 'Polio Vaccine',
                'doses_required' => 4,
                'intervals' => [30, 30, 180], // days between doses
                'booster_required' => false,
                'booster_interval_months' => null
            ]
        ];
    }

    /**
     * Initialize common medications
     */
    private function initializeCommonMedications(): void
    {
        $this->commonMedications = [
            'paracetamol' => [
                'generic_name' => 'Acetaminophen',
                'category' => 'analgesic',
                'common_dosages' => ['500mg', '1000mg'],
                'max_daily_dose' => '4000mg'
            ],
            'ibuprofen' => [
                'generic_name' => 'Ibuprofen',
                'category' => 'nsaid',
                'common_dosages' => ['200mg', '400mg', '600mg'],
                'max_daily_dose' => '2400mg'
            ],
            'amoxicillin' => [
                'generic_name' => 'Amoxicillin',
                'category' => 'antibiotic',
                'common_dosages' => ['250mg', '500mg'],
                'max_daily_dose' => '3000mg'
            ],
            'lisinopril' => [
                'generic_name' => 'Lisinopril',
                'category' => 'ace_inhibitor',
                'common_dosages' => ['5mg', '10mg', '20mg'],
                'max_daily_dose' => '40mg'
            ],
            'metformin' => [
                'generic_name' => 'Metformin',
                'category' => 'antidiabetic',
                'common_dosages' => ['500mg', '850mg', '1000mg'],
                'max_daily_dose' => '2550mg'
            ],
            'atorvastatin' => [
                'generic_name' => 'Atorvastatin',
                'category' => 'statin',
                'common_dosages' => ['10mg', '20mg', '40mg'],
                'max_daily_dose' => '80mg'
            ]
        ];
    }

    /**
     * Book appointment (API handler)
     */
    public function bookAppointment(array $appointmentData): array
    {
        try {
            // Generate appointment number
            $appointmentNumber = $this->generateAppointmentNumber();

            // Validate appointment data
            $validation = $this->validateAppointmentData($appointmentData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ];
            }

            // Check provider availability
            if (!$this->checkProviderAvailability($appointmentData['provider_id'], $appointmentData['appointment_date'])) {
                return [
                    'success' => false,
                    'error' => 'Provider not available at requested time'
                ];
            }

            // Create appointment record
            $appointment = [
                'appointment_number' => $appointmentNumber,
                'patient_id' => $appointmentData['patient_id'],
                'provider_id' => $appointmentData['provider_id'],
                'facility_id' => $appointmentData['facility_id'],
                'appointment_date' => $appointmentData['appointment_date'],
                'appointment_type' => $appointmentData['appointment_type'],
                'appointment_status' => 'scheduled',
                'reason_for_visit' => $appointmentData['reason_for_visit'] ?? '',
                'urgency_level' => $appointmentData['urgency_level'] ?? 'routine',
                'estimated_duration' => $this->config['default_appointment_duration'],
                'consultation_fee' => $this->calculateConsultationFee($appointmentData['provider_id'], $appointmentData['appointment_type']),
                'payment_status' => 'pending',
                'notes' => $appointmentData['notes'] ?? '',
                'follow_up_required' => false
            ];

            // Save appointment
            $this->saveAppointment($appointment);

            // Start appointment workflow
            $this->startAppointmentWorkflow($appointmentNumber);

            // Send confirmation notification
            $this->sendNotification('appointment_confirmed', $appointmentData['patient_id'], [
                'appointment_number' => $appointmentNumber,
                'appointment_date' => $appointmentData['appointment_date'],
                'provider_name' => 'Provider Name', // Would be fetched from database
                'facility_name' => 'Facility Name' // Would be fetched from database
            ]);

            return [
                'success' => true,
                'appointment_number' => $appointmentNumber,
                'appointment_date' => $appointmentData['appointment_date'],
                'message' => 'Appointment booked successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error booking appointment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to book appointment'
            ];
        }
    }

    /**
     * Validate appointment data
     */
    private function validateAppointmentData(array $data): array
    {
        $errors = [];

        if (empty($data['patient_id'])) {
            $errors[] = "Patient ID is required";
        }

        if (empty($data['provider_id'])) {
            $errors[] = "Provider ID is required";
        }

        if (empty($data['facility_id'])) {
            $errors[] = "Facility ID is required";
        }

        if (empty($data['appointment_date'])) {
            $errors[] = "Appointment date is required";
        } elseif (strtotime($data['appointment_date']) < time()) {
            $errors[] = "Appointment date cannot be in the past";
        }

        if (empty($data['appointment_type'])) {
            $errors[] = "Appointment type is required";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate appointment number
     */
    private function generateAppointmentNumber(): string
    {
        return 'APT' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check provider availability
     */
    private function checkProviderAvailability(int $providerId, string $dateTime): bool
    {
        // Implementation would check provider schedule
        return true;
    }

    /**
     * Calculate consultation fee
     */
    private function calculateConsultationFee(int $providerId, string $appointmentType): float
    {
        // Implementation would calculate based on provider type and appointment type
        return $this->config['consultation_fees']['general_practitioner'] ?? 75.00;
    }

    /**
     * Start appointment workflow
     */
    private function startAppointmentWorkflow(string $appointmentNumber): bool
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
    private function saveAppointment(array $appointment): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_patients' => 0, // Would query database
            'active_appointments' => 0,
            'total_providers' => 0,
            'total_facilities' => 0,
            'vaccinations_administered' => 0,
            'prescriptions_issued' => 0,
            'telehealth_sessions' => 0,
            'average_wait_time' => 0.00,
            'patient_satisfaction' => 0.00
        ];
    }
}

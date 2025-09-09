<?php
/**
 * TPT Government Platform - Education Services Module
 *
 * Comprehensive education services management system supporting student enrollment,
 * certification tracking, transcript management, course registration,
 * and financial aid applications
 */

namespace Modules\EducationServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;
use Core\AdvancedAnalytics;

class EducationServicesModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Education Services',
        'version' => '2.1.0',
        'description' => 'Comprehensive education services management and student administration system',
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
        'education.view' => 'View education records and information',
        'education.enroll' => 'Enroll students in educational programs',
        'education.certify' => 'Issue educational certifications',
        'education.transcript' => 'Access and manage transcripts',
        'education.register' => 'Register for courses and programs',
        'education.financial_aid' => 'Apply for and manage financial aid',
        'education.admin' => 'Administrative education services functions',
        'education.report' => 'Generate education service reports'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'students' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'student_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'person_id' => 'INT NOT NULL',
            'enrollment_date' => 'DATE NOT NULL',
            'student_status' => "ENUM('active','inactive','graduated','withdrawn','suspended') DEFAULT 'active'",
            'student_type' => "ENUM('full_time','part_time','international','domestic','mature','special_needs') DEFAULT 'full_time'",
            'enrollment_level' => "ENUM('primary','secondary','tertiary','vocational','postgraduate','adult_education') NOT NULL",
            'current_year_level' => 'INT',
            'gpa' => 'DECIMAL(4,2)',
            'credits_completed' => 'INT DEFAULT 0',
            'total_credits_required' => 'INT',
            'expected_graduation_date' => 'DATE',
            'academic_advisor' => 'INT',
            'emergency_contact' => 'JSON',
            'medical_info' => 'JSON',
            'disability_support' => 'BOOLEAN DEFAULT FALSE',
            'financial_aid_eligible' => 'BOOLEAN DEFAULT FALSE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'educational_institutions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'institution_code' => 'VARCHAR(10) UNIQUE NOT NULL',
            'institution_name' => 'VARCHAR(255) NOT NULL',
            'institution_type' => "ENUM('primary_school','secondary_school','university','polytechnic','private_college','training_provider','other') NOT NULL",
            'accreditation_status' => "ENUM('accredited','pending','suspended','revoked') DEFAULT 'accredited'",
            'address' => 'TEXT',
            'contact_details' => 'JSON',
            'principal_director' => 'VARCHAR(100)',
            'enrollment_capacity' => 'INT',
            'current_enrollment' => 'INT DEFAULT 0',
            'funding_type' => "ENUM('public','private','charter','international') DEFAULT 'public'",
            'specializations' => 'JSON',
            'performance_rating' => 'DECIMAL(3,2)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'courses' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'course_code' => 'VARCHAR(20) UNIQUE NOT NULL',
            'course_name' => 'VARCHAR(255) NOT NULL',
            'course_description' => 'TEXT',
            'institution_id' => 'INT NOT NULL',
            'subject_area' => 'VARCHAR(100)',
            'course_level' => "ENUM('introductory','intermediate','advanced','graduate','professional') DEFAULT 'introductory'",
            'credit_value' => 'INT DEFAULT 15',
            'prerequisites' => 'JSON',
            'corequisites' => 'JSON',
            'max_enrollment' => 'INT',
            'current_enrollment' => 'INT DEFAULT 0',
            'semester_offered' => "ENUM('semester_1','semester_2','year_long','summer','winter') DEFAULT 'semester_1'",
            'course_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'assessment_method' => 'VARCHAR(100)',
            'learning_outcomes' => 'JSON',
            'status' => "ENUM('active','inactive','cancelled','proposed') DEFAULT 'active'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'enrollments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'enrollment_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'student_id' => 'INT NOT NULL',
            'course_id' => 'INT NOT NULL',
            'enrollment_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'enrollment_status' => "ENUM('enrolled','waitlisted','dropped','completed','failed','withdrawn') DEFAULT 'enrolled'",
            'grade' => 'VARCHAR(5)',
            'grade_points' => 'DECIMAL(4,2)',
            'completion_date' => 'DATE',
            'withdrawal_date' => 'DATE',
            'withdrawal_reason' => 'TEXT',
            'attendance_percentage' => 'DECIMAL(5,2)',
            'final_exam_score' => 'DECIMAL(5,2)',
            'assignment_scores' => 'JSON',
            'feedback' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'certifications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'certification_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'student_id' => 'INT NOT NULL',
            'certification_type' => "ENUM('diploma','degree','certificate','qualification','transcript','other') NOT NULL",
            'qualification_name' => 'VARCHAR(255) NOT NULL',
            'qualification_level' => "ENUM('level_1','level_2','level_3','level_4','level_5','level_6','level_7','level_8','bachelor','masters','doctorate') NOT NULL",
            'issuing_institution' => 'INT NOT NULL',
            'issue_date' => 'DATE NOT NULL',
            'graduation_date' => 'DATE',
            'gpa' => 'DECIMAL(4,2)',
            'classification' => "ENUM('first_class','second_class_upper','second_class_lower','third_class','pass','merit','distinction','other')",
            'specializations' => 'JSON',
            'verification_code' => 'VARCHAR(50) UNIQUE',
            'blockchain_hash' => 'VARCHAR(128)',
            'status' => "ENUM('issued','pending','revoked','superseded') DEFAULT 'issued'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'transcripts' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'transcript_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'student_id' => 'INT NOT NULL',
            'academic_year' => 'VARCHAR(20) NOT NULL',
            'semester' => 'VARCHAR(20)',
            'courses' => 'JSON',
            'overall_gpa' => 'DECIMAL(4,2)',
            'credits_attempted' => 'INT',
            'credits_earned' => 'INT',
            'academic_standing' => "ENUM('good','warning','probation','suspension','expulsion') DEFAULT 'good'",
            'deans_list' => 'BOOLEAN DEFAULT FALSE',
            'honors' => 'BOOLEAN DEFAULT FALSE',
            'notes' => 'TEXT',
            'issued_date' => 'DATE',
            'verification_code' => 'VARCHAR(50) UNIQUE',
            'blockchain_hash' => 'VARCHAR(128)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'financial_aid_applications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'student_id' => 'INT NOT NULL',
            'aid_type' => "ENUM('scholarship','grant','loan','bursary','work_study','other') NOT NULL",
            'academic_year' => 'VARCHAR(20) NOT NULL',
            'application_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'status' => "ENUM('submitted','under_review','approved','rejected','awarded','disbursed','cancelled') DEFAULT 'submitted'",
            'amount_requested' => 'DECIMAL(10,2)',
            'amount_awarded' => 'DECIMAL(10,2)',
            'amount_disbursed' => 'DECIMAL(10,2)',
            'eligibility_criteria' => 'JSON',
            'supporting_documents' => 'JSON',
            'review_officer' => 'INT',
            'approval_officer' => 'INT',
            'disbursement_date' => 'DATE',
            'repayment_terms' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'academic_records' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'record_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'student_id' => 'INT NOT NULL',
            'record_type' => "ENUM('enrollment','transfer','withdrawal','graduation','discipline','honor','other') NOT NULL",
            'record_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'academic_year' => 'VARCHAR(20)',
            'semester' => 'VARCHAR(20)',
            'description' => 'TEXT NOT NULL',
            'details' => 'JSON',
            'recorded_by' => 'INT NOT NULL',
            'confidential' => 'BOOLEAN DEFAULT FALSE',
            'requires_follow_up' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATE',
            'resolution' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'course_materials' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'material_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'course_id' => 'INT NOT NULL',
            'material_type' => "ENUM('syllabus','lecture_notes','assignment','exam','resource','other') NOT NULL",
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'file_path' => 'VARCHAR(500)',
            'file_size' => 'INT',
            'mime_type' => 'VARCHAR(100)',
            'uploaded_by' => 'INT NOT NULL',
            'upload_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'version' => 'VARCHAR(20) DEFAULT \'1.0\'',
            'is_required' => 'BOOLEAN DEFAULT FALSE',
            'access_level' => "ENUM('public','enrolled_students','instructor_only') DEFAULT 'enrolled_students'",
            'download_count' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'assessment_results' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'enrollment_id' => 'INT NOT NULL',
            'assessment_type' => "ENUM('assignment','quiz','midterm','final_exam','project','presentation','other') NOT NULL",
            'assessment_name' => 'VARCHAR(255) NOT NULL',
            'assessment_date' => 'DATE NOT NULL',
            'maximum_score' => 'DECIMAL(6,2)',
            'student_score' => 'DECIMAL(6,2)',
            'percentage_score' => 'DECIMAL(5,2)',
            'grade' => 'VARCHAR(5)',
            'grade_points' => 'DECIMAL(4,2)',
            'weighting_percentage' => 'DECIMAL(5,2)',
            'feedback' => 'TEXT',
            'marked_by' => 'INT NOT NULL',
            'marked_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'appeal_status' => "ENUM('none','requested','approved','denied') DEFAULT 'none'",
            'appeal_reason' => 'TEXT',
            'appeal_resolution' => 'TEXT',
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
            'path' => '/api/education/students',
            'handler' => 'getStudents',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/students',
            'handler' => 'enrollStudent',
            'auth' => true,
            'permissions' => ['education.enroll']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/students/{id}',
            'handler' => 'getStudent',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/education/students/{id}',
            'handler' => 'updateStudent',
            'auth' => true,
            'permissions' => ['education.admin']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/courses',
            'handler' => 'getCourses',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/courses',
            'handler' => 'createCourse',
            'auth' => true,
            'permissions' => ['education.admin']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/enrollments',
            'handler' => 'enrollInCourse',
            'auth' => true,
            'permissions' => ['education.register']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/enrollments/{id}',
            'handler' => 'getEnrollment',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/certifications',
            'handler' => 'issueCertification',
            'auth' => true,
            'permissions' => ['education.certify']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/certifications/{id}',
            'handler' => 'getCertification',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/transcripts',
            'handler' => 'generateTranscript',
            'auth' => true,
            'permissions' => ['education.transcript']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/transcripts/{id}',
            'handler' => 'getTranscript',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/financial-aid',
            'handler' => 'applyForFinancialAid',
            'auth' => true,
            'permissions' => ['education.financial_aid']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/financial-aid',
            'handler' => 'getFinancialAidApplications',
            'auth' => true,
            'permissions' => ['education.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/education/assessments',
            'handler' => 'recordAssessment',
            'auth' => true,
            'permissions' => ['education.admin']
        ],
        [
            'method' => 'GET',
            'path' => '/api/education/institutions',
            'handler' => 'getInstitutions',
            'auth' => true,
            'permissions' => ['education.view']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'student_enrollment_process' => [
            'name' => 'Student Enrollment Process',
            'description' => 'Workflow for student enrollment and registration',
            'steps' => [
                'application_submitted' => ['name' => 'Application Submitted', 'next' => 'document_verification'],
                'document_verification' => ['name' => 'Document Verification', 'next' => 'eligibility_assessment'],
                'eligibility_assessment' => ['name' => 'Eligibility Assessment', 'next' => 'interview_scheduled'],
                'interview_scheduled' => ['name' => 'Interview Scheduled', 'next' => 'interview_completed'],
                'interview_completed' => ['name' => 'Interview Completed', 'next' => 'final_approval'],
                'final_approval' => ['name' => 'Final Approval', 'next' => ['enrolled', 'rejected', 'waitlisted']],
                'enrolled' => ['name' => 'Student Enrolled', 'next' => 'orientation'],
                'orientation' => ['name' => 'Orientation Completed', 'next' => 'active'],
                'active' => ['name' => 'Student Active', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null],
                'waitlisted' => ['name' => 'Waitlisted', 'next' => 'enrolled']
            ]
        ],
        'course_enrollment_process' => [
            'name' => 'Course Enrollment Process',
            'description' => 'Workflow for course registration and enrollment',
            'steps' => [
                'prerequisites_check' => ['name' => 'Prerequisites Check', 'next' => 'availability_check'],
                'availability_check' => ['name' => 'Course Availability Check', 'next' => 'fee_payment'],
                'fee_payment' => ['name' => 'Fee Payment', 'next' => 'enrollment_confirmation'],
                'enrollment_confirmation' => ['name' => 'Enrollment Confirmed', 'next' => 'enrolled'],
                'enrolled' => ['name' => 'Successfully Enrolled', 'next' => null]
            ]
        ],
        'graduation_certification_process' => [
            'name' => 'Graduation Certification Process',
            'description' => 'Workflow for graduation and certification issuance',
            'steps' => [
                'graduation_eligible' => ['name' => 'Graduation Eligible', 'next' => 'thesis_defense'],
                'thesis_defense' => ['name' => 'Thesis Defense Completed', 'next' => 'final_exams'],
                'final_exams' => ['name' => 'Final Exams Completed', 'next' => 'results_approved'],
                'results_approved' => ['name' => 'Results Approved', 'next' => 'graduation_ceremony'],
                'graduation_ceremony' => ['name' => 'Graduation Ceremony', 'next' => 'certificate_issued'],
                'certificate_issued' => ['name' => 'Certificate Issued', 'next' => 'transcript_generated'],
                'transcript_generated' => ['name' => 'Transcript Generated', 'next' => 'records_complete'],
                'records_complete' => ['name' => 'Academic Records Complete', 'next' => null]
            ]
        ],
        'financial_aid_application_process' => [
            'name' => 'Financial Aid Application Process',
            'description' => 'Workflow for financial aid applications and awards',
            'steps' => [
                'application_submitted' => ['name' => 'Application Submitted', 'next' => 'eligibility_verification'],
                'eligibility_verification' => ['name' => 'Eligibility Verification', 'next' => 'document_review'],
                'document_review' => ['name' => 'Document Review', 'next' => 'interview_assessment'],
                'interview_assessment' => ['name' => 'Interview Assessment', 'next' => 'final_decision'],
                'final_decision' => ['name' => 'Final Decision', 'next' => ['approved', 'rejected', 'conditional']],
                'approved' => ['name' => 'Aid Approved', 'next' => 'award_letter'],
                'award_letter' => ['name' => 'Award Letter Sent', 'next' => 'disbursement'],
                'disbursement' => ['name' => 'Aid Disbursed', 'next' => 'active'],
                'active' => ['name' => 'Aid Active', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null],
                'conditional' => ['name' => 'Conditional Approval', 'next' => 'conditions_met'],
                'conditions_met' => ['name' => 'Conditions Met', 'next' => 'approved']
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'student_enrollment_form' => [
            'name' => 'Student Enrollment Form',
            'fields' => [
                'person_id' => ['type' => 'hidden', 'required' => true],
                'enrollment_level' => ['type' => 'select', 'required' => true, 'label' => 'Education Level'],
                'student_type' => ['type' => 'select', 'required' => true, 'label' => 'Student Type'],
                'preferred_institution' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Institution'],
                'program_of_study' => ['type' => 'select', 'required' => true, 'label' => 'Program of Study'],
                'previous_education' => ['type' => 'textarea', 'required' => true, 'label' => 'Previous Education'],
                'emergency_contact_name' => ['type' => 'text', 'required' => true, 'label' => 'Emergency Contact Name'],
                'emergency_contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Emergency Contact Phone'],
                'medical_conditions' => ['type' => 'textarea', 'required' => false, 'label' => 'Medical Conditions'],
                'disability_support_needed' => ['type' => 'checkbox', 'required' => false, 'label' => 'Disability Support Needed']
            ],
            'sections' => [
                'personal_information' => ['title' => 'Personal Information', 'required' => true],
                'educational_background' => ['title' => 'Educational Background', 'required' => true],
                'program_selection' => ['title' => 'Program Selection', 'required' => true],
                'support_services' => ['title' => 'Support Services', 'required' => false],
                'emergency_contacts' => ['title' => 'Emergency Contacts', 'required' => true]
            ],
            'documents' => [
                'birth_certificate' => ['required' => true, 'label' => 'Birth Certificate'],
                'academic_transcripts' => ['required' => true, 'label' => 'Academic Transcripts'],
                'identification' => ['required' => true, 'label' => 'Identification Documents'],
                'medical_reports' => ['required' => false, 'label' => 'Medical Reports'],
                'recommendation_letters' => ['required' => false, 'label' => 'Recommendation Letters']
            ]
        ],
        'course_registration_form' => [
            'name' => 'Course Registration Form',
            'fields' => [
                'student_id' => ['type' => 'hidden', 'required' => true],
                'course_id' => ['type' => 'select', 'required' => true, 'label' => 'Select Course'],
                'semester' => ['type' => 'select', 'required' => true, 'label' => 'Semester'],
                'registration_type' => ['type' => 'select', 'required' => true, 'label' => 'Registration Type'],
                'payment_method' => ['type' => 'select', 'required' => true, 'label' => 'Payment Method'],
                'financial_aid_applied' => ['type' => 'checkbox', 'required' => false, 'label' => 'Apply Financial Aid']
            ],
            'documents' => [
                'prerequisite_proof' => ['required' => false, 'label' => 'Prerequisite Completion Proof'],
                'payment_receipt' => ['required' => true, 'label' => 'Payment Receipt']
            ]
        ],
        'financial_aid_application_form' => [
            'name' => 'Financial Aid Application Form',
            'fields' => [
                'student_id' => ['type' => 'hidden', 'required' => true],
                'aid_type' => ['type' => 'select', 'required' => true, 'label' => 'Type of Aid'],
                'academic_year' => ['type' => 'select', 'required' => true, 'label' => 'Academic Year'],
                'amount_requested' => ['type' => 'number', 'required' => true, 'label' => 'Amount Requested', 'step' => '0.01', 'min' => '0'],
                'reason_for_aid' => ['type' => 'textarea', 'required' => true, 'label' => 'Reason for Financial Aid'],
                'employment_status' => ['type' => 'select', 'required' => true, 'label' => 'Employment Status'],
                'household_income' => ['type' => 'number', 'required' => true, 'label' => 'Household Income', 'step' => '0.01', 'min' => '0'],
                'number_of_dependents' => ['type' => 'number', 'required' => true, 'label' => 'Number of Dependents', 'min' => '0']
            ],
            'sections' => [
                'financial_information' => ['title' => 'Financial Information', 'required' => true],
                'household_details' => ['title' => 'Household Details', 'required' => true],
                'aid_justification' => ['title' => 'Aid Justification', 'required' => true]
            ],
            'documents' => [
                'income_proof' => ['required' => true, 'label' => 'Proof of Income'],
                'tax_returns' => ['required' => true, 'label' => 'Tax Returns'],
                'bank_statements' => ['required' => true, 'label' => 'Bank Statements'],
                'expense_records' => ['required' => false, 'label' => 'Expense Records']
            ]
        ],
        'transcript_request_form' => [
            'name' => 'Transcript Request Form',
            'fields' => [
                'student_id' => ['type' => 'hidden', 'required' => true],
                'transcript_type' => ['type' => 'select', 'required' => true, 'label' => 'Transcript Type'],
                'delivery_method' => ['type' => 'select', 'required' => true, 'label' => 'Delivery Method'],
                'number_of_copies' => ['type' => 'number', 'required' => true, 'label' => 'Number of Copies', 'min' => '1', 'max' => '10'],
                'purpose' => ['type' => 'textarea', 'required' => true, 'label' => 'Purpose of Transcript'],
                'rush_processing' => ['type' => 'checkbox', 'required' => false, 'label' => 'Rush Processing']
            ],
            'documents' => [
                'authorization_letter' => ['required' => false, 'label' => 'Authorization Letter'],
                'payment_receipt' => ['required' => true, 'label' => 'Payment Receipt']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'student_enrollment_report' => [
            'name' => 'Student Enrollment Report',
            'description' => 'Student enrollment statistics by institution, program, and demographics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'institution_id' => ['type' => 'select', 'required' => false],
                'enrollment_level' => ['type' => 'select', 'required' => false],
                'student_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'institution_name', 'enrollment_level', 'student_type',
                'total_enrolled', 'new_enrollments', 'withdrawals', 'graduations'
            ]
        ],
        'academic_performance_report' => [
            'name' => 'Academic Performance Report',
            'description' => 'Student academic performance and GPA statistics',
            'parameters' => [
                'academic_year' => ['type' => 'select', 'required' => true],
                'institution_id' => ['type' => 'select', 'required' => false],
                'course_id' => ['type' => 'select', 'required' => false],
                'grade_range' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'course_code', 'course_name', 'enrolled_students',
                'average_gpa', 'pass_rate', 'withdrawal_rate', 'grade_distribution'
            ]
        ],
        'certification_issuance_report' => [
            'name' => 'Certification Issuance Report',
            'description' => 'Certification and qualification issuance statistics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'certification_type' => ['type' => 'select', 'required' => false],
                'qualification_level' => ['type' => 'select', 'required' => false],
                'institution_id' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'certification_type', 'qualification_level', 'total_issued',
                'average_gpa', 'classification_distribution', 'issuance_trends'
            ]
        ],
        'financial_aid_distribution_report' => [
            'name' => 'Financial Aid Distribution Report',
            'description' => 'Financial aid awards and distribution statistics',
            'parameters' => [
                'academic_year' => ['type' => 'select', 'required' => true],
                'aid_type' => ['type' => 'select', 'required' => false],
                'institution_id' => ['type' => 'select', 'required' => false],
                'amount_range' => ['type' => 'number_range', 'required' => false]
            ],
            'columns' => [
                'aid_type', 'total_applications', 'total_awarded',
                'total_amount', 'average_amount', 'approval_rate'
            ]
        ],
        'course_completion_report' => [
            'name' => 'Course Completion Report',
            'description' => 'Course enrollment and completion statistics',
            'parameters' => [
                'academic_year' => ['type' => 'select', 'required' => true],
                'course_id' => ['type' => 'select', 'required' => false],
                'institution_id' => ['type' => 'select', 'required' => false],
                'completion_status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'course_code', 'course_name', 'enrolled_students',
                'completed_students', 'completion_rate', 'average_grade',
                'withdrawal_rate', 'drop_rate'
            ]
        ],
        'institution_performance_report' => [
            'name' => 'Institution Performance Report',
            'description' => 'Educational institution performance metrics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'institution_type' => ['type' => 'select', 'required' => false],
                'performance_metric' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'institution_name', 'institution_type', 'enrollment_numbers',
                'graduation_rate', 'student_satisfaction', 'employment_rate',
                'performance_rating', 'accreditation_status'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'enrollment_confirmed' => [
            'name' => 'Enrollment Confirmed',
            'template' => 'Your enrollment at {institution_name} has been confirmed. Student ID: {student_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['student_enrolled']
        ],
        'course_registration_successful' => [
            'name' => 'Course Registration Successful',
            'template' => 'You have been successfully registered for {course_name} ({course_code})',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['course_enrolled']
        ],
        'certification_issued' => [
            'name' => 'Certification Issued',
            'template' => 'Your {certification_type} certification has been issued. Reference: {certification_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['certification_issued']
        ],
        'transcript_ready' => [
            'name' => 'Transcript Ready',
            'template' => 'Your academic transcript is ready for collection. Reference: {transcript_number}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['transcript_generated']
        ],
        'financial_aid_approved' => [
            'name' => 'Financial Aid Approved',
            'template' => 'Your financial aid application has been approved. Amount: ${amount_awarded}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['aid_approved']
        ],
        'financial_aid_rejected' => [
            'name' => 'Financial Aid Application Rejected',
            'template' => 'Your financial aid application has been rejected. Reason: {rejection_reason}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['aid_rejected']
        ],
        'grade_available' => [
            'name' => 'Grade Available',
            'template' => 'Your grade for {course_name} is now available. Grade: {grade}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['grade_posted']
        ],
        'graduation_eligible' => [
            'name' => 'Graduation Eligible',
            'template' => 'Congratulations! You are eligible for graduation. Expected graduation date: {graduation_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['graduation_eligible']
        ],
        'course_withdrawal_deadline' => [
            'name' => 'Course Withdrawal Deadline',
            'template' => 'The course withdrawal deadline for {course_name} is approaching: {deadline_date}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['withdrawal_deadline']
        ],
        'academic_warning' => [
            'name' => 'Academic Warning',
            'template' => 'You have received an academic warning. Please contact your academic advisor.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['academic_warning']
        ]
    ];

    /**
     * Education levels configuration
     */
    private array $educationLevels = [];

    /**
     * Qualification levels configuration
     */
    private array $qualificationLevels = [];

    /**
     * Grading scales configuration
     */
    private array $gradingScales = [];

    /**
     * Course prerequisites configuration
     */
    private array $coursePrerequisites = [];

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
            'default_academic_year' => date('Y'),
            'max_course_enrollment' => 200,
            'min_gpa_for_graduation' => 2.0,
            'transcript_retention_years' => 10,
            'certification_fees' => [
                'diploma' => 50.00,
                'degree' => 75.00,
                'certificate' => 25.00,
                'transcript' => 15.00
            ],
            'withdrawal_deadlines' => [
                'semester_1' => '2024-03-15',
                'semester_2' => '2024-08-15'
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
        $this->initializeEducationLevels();
        $this->initializeQualificationLevels();
        $this->initializeGradingScales();
        $this->initializeCoursePrerequisites();
    }

    /**
     * Initialize education levels
     */
    private function initializeEducationLevels(): void
    {
        $this->educationLevels = [
            'primary' => [
                'name' => 'Primary Education',
                'years' => 8,
                'age_range' => '5-12',
                'required_subjects' => ['english', 'mathematics', 'science', 'social_studies']
            ],
            'secondary' => [
                'name' => 'Secondary Education',
                'years' => 5,
                'age_range' => '13-17',
                'required_subjects' => ['english', 'mathematics', 'science', 'history', 'geography']
            ],
            'tertiary' => [
                'name' => 'Tertiary Education',
                'years' => 3,
                'age_range' => '18-21',
                'degree_types' => ['bachelor', 'associate', 'diploma']
            ],
            'vocational' => [
                'name' => 'Vocational Education',
                'years' => 2,
                'age_range' => '16-18',
                'focus_areas' => ['trades', 'technology', 'business', 'healthcare']
            ]
        ];
    }

    /**
     * Initialize qualification levels
     */
    private function initializeQualificationLevels(): void
    {
        $this->qualificationLevels = [
            'level_1' => ['name' => 'Level 1 Certificate', 'description' => 'Basic vocational qualification'],
            'level_2' => ['name' => 'Level 2 Certificate', 'description' => 'Intermediate vocational qualification'],
            'level_3' => ['name' => 'Level 3 Certificate', 'description' => 'Advanced vocational qualification'],
            'level_4' => ['name' => 'Level 4 Certificate', 'description' => 'Associate degree level'],
            'level_5' => ['name' => 'Level 5 Diploma', 'description' => 'Diploma level qualification'],
            'level_6' => ['name' => 'Level 6 Advanced Diploma', 'description' => 'Advanced diploma'],
            'level_7' => ['name' => 'Level 7 Bachelor Degree', 'description' => 'Bachelor degree'],
            'level_8' => ['name' => 'Level 8 Postgraduate', 'description' => 'Postgraduate qualification'],
            'bachelor' => ['name' => 'Bachelor Degree', 'description' => 'Undergraduate degree'],
            'masters' => ['name' => 'Masters Degree', 'description' => 'Postgraduate masters degree'],
            'doctorate' => ['name' => 'Doctorate', 'description' => 'Doctoral degree']
        ];
    }

    /**
     * Initialize grading scales
     */
    private function initializeGradingScales(): void
    {
        $this->gradingScales = [
            'standard' => [
                'A+' => ['min' => 90, 'max' => 100, 'points' => 4.0],
                'A' => ['min' => 85, 'max' => 89, 'points' => 4.0],
                'A-' => ['min' => 80, 'max' => 84, 'points' => 3.7],
                'B+' => ['min' => 75, 'max' => 79, 'points' => 3.3],
                'B' => ['min' => 70, 'max' => 74, 'points' => 3.0],
                'B-' => ['min' => 65, 'max' => 69, 'points' => 2.7],
                'C+' => ['min' => 60, 'max' => 64, 'points' => 2.3],
                'C' => ['min' => 55, 'max' => 59, 'points' => 2.0],
                'C-' => ['min' => 50, 'max' => 54, 'points' => 1.7],
                'D' => ['min' => 40, 'max' => 49, 'points' => 1.0],
                'F' => ['min' => 0, 'max' => 39, 'points' => 0.0]
            ],
            'pass_fail' => [
                'P' => ['description' => 'Pass', 'points' => 1.0],
                'F' => ['description' => 'Fail', 'points' => 0.0]
            ]
        ];
    }

    /**
     * Initialize course prerequisites
     */
    private function initializeCoursePrerequisites(): void
    {
        $this->coursePrerequisites = [
            'MATH101' => ['required' => [], 'recommended' => []],
            'MATH102' => ['required' => ['MATH101'], 'recommended' => []],
            'PHYS101' => ['required' => ['MATH101'], 'recommended' => []],
            'CHEM101' => ['required' => ['MATH101'], 'recommended' => ['PHYS101']],
            'CS101' => ['required' => [], 'recommended' => ['MATH101']],
            'CS201' => ['required' => ['CS101'], 'recommended' => ['MATH102']]
        ];
    }

    /**
     * Enroll student (API handler)
     */
    public function enrollStudent(array $enrollmentData): array
    {
        try {
            // Generate student number
            $studentNumber = $this->generateStudentNumber();

            // Validate enrollment data
            $validation = $this->validateEnrollmentData($enrollmentData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors']
                ];
            }

            // Create student record
            $student = [
                'student_number' => $studentNumber,
                'person_id' => $enrollmentData['person_id'],
                'enrollment_date' => date('Y-m-d'),
                'student_status' => 'active',
                'student_type' => $enrollmentData['student_type'] ?? 'full_time',
                'enrollment_level' => $enrollmentData['enrollment_level'],
                'current_year_level' => 1,
                'emergency_contact' => $enrollmentData['emergency_contact'] ?? [],
                'medical_info' => $enrollmentData['medical_info'] ?? [],
                'disability_support' => $enrollmentData['disability_support'] ?? false
            ];

            // Save to database
            $this->saveStudent($student);

            // Start enrollment workflow
            $this->startEnrollmentWorkflow($studentNumber);

            // Send notification
            $this->sendNotification('enrollment_confirmed', $enrollmentData['person_id'], [
                'student_number' => $studentNumber,
                'institution_name' => 'Institution Name' // Placeholder
            ]);

            return [
                'success' => true,
                'student_number' => $studentNumber,
                'message' => 'Student enrolled successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error enrolling student: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to enroll student'
            ];
        }
    }

    /**
     * Validate enrollment data
     */
    private function validateEnrollmentData(array $data): array
    {
        $errors = [];

        if (empty($data['person_id'])) {
            $errors[] = "Person ID is required";
        }

        if (empty($data['enrollment_level']) || !isset($this->educationLevels[$data['enrollment_level']])) {
            $errors[] = "Valid enrollment level is required";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate student number
     */
    private function generateStudentNumber(): string
    {
        return 'STU' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Start enrollment workflow
     */
    private function startEnrollmentWorkflow(string $studentNumber): bool
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
    private function saveStudent(array $student): bool { return true; }
    private function getLastInsertId(): int { return mt_rand(1, 999999); }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        return [
            'total_students' => 0, // Would query database
            'active_enrollments' => 0,
            'courses_offered' => 0,
            'certifications_issued' => 0,
            'financial_aid_awarded' => 0.00,
            'average_gpa' => 0.00,
            'graduation_rate' => 0.00,
            'enrollment_trends' => []
        ];
    }
}

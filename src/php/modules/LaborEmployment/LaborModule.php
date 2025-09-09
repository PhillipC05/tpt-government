<?php
/**
 * TPT Government Platform - Labor & Employment Module
 *
 * Comprehensive workforce development and employment services system
 * supporting job matching, training programs, labor compliance, and career development
 */

namespace Modules\LaborEmployment;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class LaborModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Labor & Employment',
        'version' => '1.0.0',
        'description' => 'Comprehensive workforce development and employment services system',
        'author' => 'TPT Government Platform',
        'category' => 'employment_services',
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
        'labor.view' => 'View labor and employment records',
        'labor.create' => 'Create employment applications and programs',
        'labor.edit' => 'Edit labor and employment data',
        'labor.review' => 'Review employment applications',
        'labor.approve' => 'Approve employment programs and benefits',
        'labor.reject' => 'Reject employment applications',
        'labor.compliance' => 'Monitor labor compliance',
        'labor.reports' => 'Generate labor and employment reports'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'job_seekers' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'seeker_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'user_id' => 'INT NOT NULL',
            'profile_complete' => 'BOOLEAN DEFAULT FALSE',
            'personal_info' => 'JSON',
            'skills_experience' => 'JSON',
            'education_background' => 'JSON',
            'work_preferences' => 'JSON',
            'availability_status' => "ENUM('actively_looking','open_to_offers','not_looking','employed') DEFAULT 'actively_looking'",
            'registration_date' => 'DATETIME NOT NULL',
            'last_active' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'profile_visibility' => 'BOOLEAN DEFAULT TRUE',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'employers' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'employer_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'user_id' => 'INT NOT NULL',
            'company_name' => 'VARCHAR(255) NOT NULL',
            'company_type' => "ENUM('private','government','non_profit','educational','healthcare','other') NOT NULL",
            'industry' => 'VARCHAR(100) NOT NULL',
            'company_size' => "ENUM('startup','small','medium','large','enterprise') NOT NULL",
            'location' => 'TEXT NOT NULL',
            'contact_info' => 'JSON',
            'verification_status' => "ENUM('pending','verified','rejected') DEFAULT 'pending'",
            'registration_date' => 'DATETIME NOT NULL',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'job_postings' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'job_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'employer_id' => 'VARCHAR(20) NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'job_type' => "ENUM('full_time','part_time','contract','temporary','internship','freelance') NOT NULL",
            'category' => 'VARCHAR(100) NOT NULL',
            'location' => 'TEXT NOT NULL',
            'remote_work' => 'BOOLEAN DEFAULT FALSE',
            'salary_range' => 'JSON',
            'requirements' => 'JSON',
            'benefits' => 'JSON',
            'application_deadline' => 'DATE NULL',
            'status' => "ENUM('draft','active','paused','closed','filled') DEFAULT 'draft'",
            'posted_date' => 'DATETIME NOT NULL',
            'views_count' => 'INT DEFAULT 0',
            'applications_count' => 'INT DEFAULT 0',
            'featured' => 'BOOLEAN DEFAULT FALSE',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'job_applications' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'job_id' => 'VARCHAR(20) NOT NULL',
            'seeker_id' => 'VARCHAR(20) NOT NULL',
            'cover_letter' => 'TEXT',
            'resume_file' => 'VARCHAR(500)',
            'additional_documents' => 'JSON',
            'application_date' => 'DATETIME NOT NULL',
            'status' => "ENUM('submitted','under_review','shortlisted','interviewed','offered','hired','rejected','withdrawn') DEFAULT 'submitted'",
            'employer_notes' => 'TEXT',
            'seeker_notes' => 'TEXT',
            'interview_scheduled' => 'DATETIME NULL',
            'offer_details' => 'JSON',
            'feedback' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'training_programs' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'program_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'category' => "ENUM('technical','soft_skills','leadership','industry_specific','certification','language','digital_skills') NOT NULL",
            'provider_id' => 'INT NOT NULL',
            'duration_hours' => 'INT NOT NULL',
            'cost_per_participant' => 'DECIMAL(8,2) DEFAULT 0',
            'funding_available' => 'BOOLEAN DEFAULT FALSE',
            'max_participants' => 'INT NOT NULL',
            'enrolled_count' => 'INT DEFAULT 0',
            'start_date' => 'DATE NOT NULL',
            'end_date' => 'DATE NOT NULL',
            'location' => 'TEXT',
            'online_available' => 'BOOLEAN DEFAULT FALSE',
            'prerequisites' => 'JSON',
            'learning_objectives' => 'JSON',
            'certification_offered' => 'BOOLEAN DEFAULT FALSE',
            'status' => "ENUM('planned','active','completed','cancelled') DEFAULT 'planned'",
            'evaluation_results' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'workforce_development' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'program_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'program_name' => 'VARCHAR(255) NOT NULL',
            'program_type' => "ENUM('apprenticeship','internship','job_training','career_counseling','entrepreneurship','retraining') NOT NULL",
            'target_group' => 'VARCHAR(100)',
            'description' => 'TEXT',
            'duration_months' => 'INT NOT NULL',
            'funding_amount' => 'DECIMAL(12,2)',
            'funding_source' => 'VARCHAR(100)',
            'partners' => 'JSON',
            'objectives' => 'JSON',
            'success_metrics' => 'JSON',
            'start_date' => 'DATE NOT NULL',
            'end_date' => 'DATE NOT NULL',
            'coordinator_id' => 'INT NOT NULL',
            'status' => "ENUM('planning','active','completed','cancelled','on_hold') DEFAULT 'planning'",
            'progress_reports' => 'JSON',
            'evaluation' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'labor_compliance' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'compliance_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'employer_id' => 'VARCHAR(20) NOT NULL',
            'inspection_type' => "ENUM('routine','complaint','follow_up') NOT NULL",
            'scheduled_date' => 'DATETIME NOT NULL',
            'actual_date' => 'DATETIME NULL',
            'inspector_id' => 'INT NULL',
            'findings' => 'JSON',
            'violations' => 'JSON',
            'recommendations' => 'TEXT',
            'compliance_status' => "ENUM('compliant','minor_violations','major_violations','critical_violations') NULL",
            'corrective_actions' => 'JSON',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'follow_up_date' => 'DATETIME NULL',
            'penalty_amount' => 'DECIMAL(10,2) DEFAULT 0',
            'status' => "ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled'",
            'report_file' => 'VARCHAR(500)',
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
            'path' => '/api/labor/job-seekers',
            'handler' => 'getJobSeekers',
            'auth' => true,
            'permissions' => ['labor.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/labor/job-seekers',
            'handler' => 'registerJobSeeker',
            'auth' => true,
            'permissions' => ['labor.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/labor/employers',
            'handler' => 'getEmployers',
            'auth' => true,
            'permissions' => ['labor.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/labor/employers',
            'handler' => 'registerEmployer',
            'auth' => true,
            'permissions' => ['labor.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/labor/jobs',
            'handler' => 'getJobPostings',
            'auth' => true,
            'permissions' => ['labor.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/labor/jobs',
            'handler' => 'createJobPosting',
            'auth' => true,
            'permissions' => ['labor.create']
        ],
        [
            'method' => 'POST',
            'path' => '/api/labor/jobs/{jobId}/apply',
            'handler' => 'applyForJob',
            'auth' => true,
            'permissions' => ['labor.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/labor/training',
            'handler' => 'getTrainingPrograms',
            'auth' => true,
            'permissions' => ['labor.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/labor/training/{programId}/enroll',
            'handler' => 'enrollInTraining',
            'auth' => true,
            'permissions' => ['labor.create']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'job_application_process' => [
            'name' => 'Job Application Process',
            'description' => 'Complete workflow for job applications and hiring',
            'steps' => [
                'submitted' => ['name' => 'Application Submitted', 'next' => 'under_review'],
                'under_review' => ['name' => 'Under Review', 'next' => ['shortlisted', 'rejected']],
                'shortlisted' => ['name' => 'Shortlisted', 'next' => 'interviewed'],
                'interviewed' => ['name' => 'Interviewed', 'next' => ['offered', 'rejected']],
                'offered' => ['name' => 'Job Offered', 'next' => ['hired', 'rejected']],
                'hired' => ['name' => 'Hired', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null],
                'withdrawn' => ['name' => 'Application Withdrawn', 'next' => null]
            ]
        ],
        'employer_verification_process' => [
            'name' => 'Employer Verification Process',
            'description' => 'Workflow for employer registration and verification',
            'steps' => [
                'pending' => ['name' => 'Verification Pending', 'next' => ['verified', 'rejected']],
                'verified' => ['name' => 'Employer Verified', 'next' => null],
                'rejected' => ['name' => 'Verification Rejected', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'job_seeker_registration' => [
            'name' => 'Job Seeker Registration',
            'fields' => [
                'full_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                'date_of_birth' => ['type' => 'date', 'required' => true, 'label' => 'Date of Birth'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                'address' => ['type' => 'textarea', 'required' => true, 'label' => 'Address'],
                'education_level' => ['type' => 'select', 'required' => true, 'label' => 'Education Level'],
                'field_of_study' => ['type' => 'text', 'required' => false, 'label' => 'Field of Study'],
                'work_experience_years' => ['type' => 'number', 'required' => true, 'label' => 'Years of Work Experience'],
                'skills' => ['type' => 'textarea', 'required' => true, 'label' => 'Skills and Competencies'],
                'preferred_job_type' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Job Type'],
                'expected_salary_range' => ['type' => 'text', 'required' => false, 'label' => 'Expected Salary Range'],
                'availability_date' => ['type' => 'date', 'required' => true, 'label' => 'Available From'],
                'willing_to_relocate' => ['type' => 'checkbox', 'required' => false, 'label' => 'Willing to Relocate']
            ],
            'documents' => [
                'resume' => ['required' => true, 'label' => 'Resume/CV'],
                'certificates' => ['required' => false, 'label' => 'Educational Certificates'],
                'references' => ['required' => false, 'label' => 'References'],
                'portfolio' => ['required' => false, 'label' => 'Portfolio/Work Samples']
            ]
        ],
        'employer_registration' => [
            'name' => 'Employer Registration',
            'fields' => [
                'company_name' => ['type' => 'text', 'required' => true, 'label' => 'Company Name'],
                'company_type' => ['type' => 'select', 'required' => true, 'label' => 'Company Type'],
                'industry' => ['type' => 'text', 'required' => true, 'label' => 'Industry'],
                'company_size' => ['type' => 'select', 'required' => true, 'label' => 'Company Size'],
                'business_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Address'],
                'contact_person' => ['type' => 'text', 'required' => true, 'label' => 'Contact Person'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                'website' => ['type' => 'url', 'required' => false, 'label' => 'Company Website'],
                'business_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Description'],
                'employee_count' => ['type' => 'number', 'required' => true, 'label' => 'Current Employee Count']
            ],
            'documents' => [
                'business_registration' => ['required' => true, 'label' => 'Business Registration Certificate'],
                'tax_certificate' => ['required' => true, 'label' => 'Tax Certificate'],
                'financial_statements' => ['required' => false, 'label' => 'Financial Statements'],
                'company_profile' => ['required' => false, 'label' => 'Company Profile/Brochure']
            ]
        ],
        'job_posting' => [
            'name' => 'Job Posting Form',
            'fields' => [
                'title' => ['type' => 'text', 'required' => true, 'label' => 'Job Title'],
                'category' => ['type' => 'select', 'required' => true, 'label' => 'Job Category'],
                'job_type' => ['type' => 'select', 'required' => true, 'label' => 'Job Type'],
                'location' => ['type' => 'text', 'required' => true, 'label' => 'Job Location'],
                'remote_work' => ['type' => 'checkbox', 'required' => false, 'label' => 'Remote Work Available'],
                'salary_min' => ['type' => 'number', 'required' => false, 'label' => 'Minimum Salary', 'step' => '0.01'],
                'salary_max' => ['type' => 'number', 'required' => false, 'label' => 'Maximum Salary', 'step' => '0.01'],
                'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Job Description'],
                'requirements' => ['type' => 'textarea', 'required' => true, 'label' => 'Job Requirements'],
                'benefits' => ['type' => 'textarea', 'required' => false, 'label' => 'Benefits and Perks'],
                'application_deadline' => ['type' => 'date', 'required' => false, 'label' => 'Application Deadline']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'employment_overview' => [
            'name' => 'Employment Overview Report',
            'description' => 'Summary of job market and employment statistics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'industry' => ['type' => 'select', 'required' => false],
                'region' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_job_postings', 'total_applications', 'placement_rate',
                'average_salary', 'top_industries', 'unemployment_trends'
            ]
        ],
        'training_programs_report' => [
            'name' => 'Training Programs Report',
            'description' => 'Analysis of training program effectiveness and participation',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'program_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'program_name', 'participants', 'completion_rate',
                'employment_rate', 'cost_per_participant', 'roi_analysis'
            ]
        ],
        'labor_compliance_report' => [
            'name' => 'Labor Compliance Report',
            'description' => 'Monitoring of labor law compliance and violations',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'compliance_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'inspections_count', 'compliance_rate', 'violations_found',
                'penalties_issued', 'industry_compliance', 'trends'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'job_seeker_registered' => [
            'name' => 'Job Seeker Registration Confirmed',
            'template' => 'Welcome! Your job seeker profile has been created. Start exploring job opportunities.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['job_seeker_registration']
        ],
        'employer_verified' => [
            'name' => 'Employer Verification Complete',
            'template' => 'Congratulations! Your employer account has been verified. You can now post jobs.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['employer_verification']
        ],
        'job_application_submitted' => [
            'name' => 'Job Application Submitted',
            'template' => 'Your application for "{job_title}" has been submitted successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['job_application_created']
        ],
        'job_application_update' => [
            'name' => 'Job Application Update',
            'template' => 'Update on your application for "{job_title}": {status}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['job_application_status_change']
        ],
        'new_job_matches' => [
            'name' => 'New Job Matches Available',
            'template' => 'We found {count} new job opportunities matching your profile.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['job_matches_found']
        ],
        'training_enrollment_confirmed' => [
            'name' => 'Training Enrollment Confirmed',
            'template' => 'Your enrollment in "{program_name}" has been confirmed. Program starts on {start_date}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['training_enrollment']
        ]
    ];

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
            'job_posting_duration_days' => 30,
            'application_review_days' => 7,
            'max_job_applications_per_day' => 10,
            'training_enrollment_deadline_days' => 14,
            'compliance_inspection_interval_months' => 12,
            'auto_match_jobs' => true,
            'featured_job_cost' => 50.00
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        // Initialize module-specific data
    }

    /**
     * Register job seeker (API handler)
     */
    public function registerJobSeeker(array $seekerData): array
    {
        // Validate seeker data
        $validation = $this->validateJobSeekerData($seekerData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate seeker ID
        $seekerId = $this->generateSeekerId();

        // Create job seeker record
        $seeker = [
            'seeker_id' => $seekerId,
            'user_id' => $seekerData['user_id'],
            'profile_complete' => true,
            'personal_info' => json_encode([
                'full_name' => $seekerData['full_name'],
                'date_of_birth' => $seekerData['date_of_birth'],
                'contact_phone' => $seekerData['contact_phone'],
                'contact_email' => $seekerData['contact_email'],
                'address' => $seekerData['address']
            ]),
            'skills_experience' => json_encode([
                'education_level' => $seekerData['education_level'],
                'field_of_study' => $seekerData['field_of_study'] ?? '',
                'work_experience_years' => $seekerData['work_experience_years'],
                'skills' => $seekerData['skills']
            ]),
            'education_background' => json_encode([]),
            'work_preferences' => json_encode([
                'job_type' => $seekerData['preferred_job_type'],
                'salary_range' => $seekerData['expected_salary_range'] ?? '',
                'availability_date' => $seekerData['availability_date'],
                'willing_to_relocate' => $seekerData['willing_to_relocate'] ?? false
            ]),
            'availability_status' => 'actively_looking',
            'registration_date' => date('Y-m-d H:i:s'),
            'documents' => json_encode($seekerData['documents'] ?? []),
            'notes' => $seekerData['notes'] ?? ''
        ];

        // Save to database
        $this->saveJobSeeker($seeker);

        // Send notification
        $this->sendNotification('job_seeker_registered', $seekerData['user_id'], [
            'seeker_id' => $seekerId
        ]);

        return [
            'success' => true,
            'seeker_id' => $seekerId,
            'message' => 'Job seeker profile created successfully'
        ];
    }

    /**
     * Register employer (API handler)
     */
    public function registerEmployer(array $employerData): array
    {
        // Validate employer data
        $validation = $this->validateEmployerData($employerData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate employer ID
        $employerId = $this->generateEmployerId();

        // Create employer record
        $employer = [
            'employer_id' => $employerId,
            'user_id' => $employerData['user_id'],
            'company_name' => $employerData['company_name'],
            'company_type' => $employerData['company_type'],
            'industry' => $employerData['industry'],
            'company_size' => $employerData['company_size'],
            'location' => $employerData['business_address'],
            'contact_info' => json_encode([
                'contact_person' => $employerData['contact_person'],
                'phone' => $employerData['contact_phone'],
                'email' => $employerData['contact_email'],
                'website' => $employerData['website'] ?? ''
            ]),
            'verification_status' => 'pending',
            'registration_date' => date('Y-m-d H:i:s'),
            'documents' => json_encode($employerData['documents'] ?? []),
            'notes' => $employerData['notes'] ?? ''
        ];

        // Save to database
        $this->saveEmployer($employer);

        return [
            'success' => true,
            'employer_id' => $employerId,
            'verification_status' => 'pending',
            'message' => 'Employer registration submitted for verification'
        ];
    }

    /**
     * Create job posting (API handler)
     */
    public function createJobPosting(array $jobData): array
    {
        // Validate job data
        $validation = $this->validateJobData($jobData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate job ID
        $jobId = $this->generateJobId();

        // Create job posting record
        $job = [
            'job_id' => $jobId,
            'employer_id' => $jobData['employer_id'],
            'title' => $jobData['title'],
            'description' => $jobData['description'],
            'job_type' => $jobData['job_type'],
            'category' => $jobData['category'],
            'location' => $jobData['location'],
            'remote_work' => $jobData['remote_work'] ?? false,
            'salary_range' => json_encode([
                'min' => $jobData['salary_min'] ?? null,
                'max' => $jobData['salary_max'] ?? null
            ]),
            'requirements' => json_encode(explode("\n", $jobData['requirements'])),
            'benefits' => json_encode(explode("\n", $jobData['benefits'] ?? '')),
            'application_deadline' => $jobData['application_deadline'] ?? null,
            'status' => 'active',
            'posted_date' => date('Y-m-d H:i:s'),
            'documents' => json_encode([]),
            'notes' => $jobData['notes'] ?? ''
        ];

        // Save to database
        $this->saveJobPosting($job);

        return [
            'success' => true,
            'job_id' => $jobId,
            'status' => 'active',
            'message' => 'Job posting created successfully'
        ];
    }

    /**
     * Apply for job (API handler)
     */
    public function applyForJob(string $jobId, array $applicationData): array
    {
        // Validate application data
        $validation = $this->validateApplicationData($applicationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check if job exists and is active
        $job = $this->getJobPosting($jobId);
        if (!$job || $job['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Job posting not found or not active'
            ];
        }

        // Check application deadline
        if ($job['application_deadline'] && strtotime($job['application_deadline']) < time()) {
            return [
                'success' => false,
                'error' => 'Application deadline has passed'
            ];
        }

        // Generate application ID
        $applicationId = $this->generateApplicationId();

        // Create job application record
        $application = [
            'application_id' => $applicationId,
            'job_id' => $jobId,
            'seeker_id' => $applicationData['seeker_id'],
            'cover_letter' => $applicationData['cover_letter'] ?? '',
            'resume_file' => $applicationData['resume_file'] ?? '',
            'additional_documents' => json_encode($applicationData['additional_documents'] ?? []),
            'application_date' => date('Y-m-d H:i:s'),
            'status' => 'submitted',
            'employer_notes' => '',
            'seeker_notes' => $applicationData['notes'] ?? '',
            'interview_scheduled' => null,
            'offer_details' => json_encode([]),
            'feedback' => ''
        ];

        // Save to database
        $this->saveJobApplication($application);

        // Update job posting application count
        $this->incrementJobApplicationCount($jobId);

        // Send notification
        $job = $this->getJobPosting($jobId);
        $this->sendNotification('job_application_submitted', $applicationData['seeker_id'], [
            'job_title' => $job['title']
        ]);

        return [
            'success' => true,
            'application_id' => $applicationId,
            'message' => 'Job application submitted successfully'
        ];
    }

    /**
     * Get job seekers (API handler)
     */
    public function getJobSeekers(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM job_seekers WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND availability_status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            $sql .= " ORDER BY registration_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['personal_info'] = json_decode($result['personal_info'], true);
                $result['skills_experience'] = json_decode($result['skills_experience'], true);
                $result['education_background'] = json_decode($result['education_background'], true);
                $result['work_preferences'] = json_decode($result['work_preferences'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting job seekers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve job seekers'
            ];
        }
    }

    /**
     * Get employers (API handler)
     */
    public function getEmployers(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM employers WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND verification_status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            $sql .= " ORDER BY registration_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['contact_info'] = json_decode($result['contact_info'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting employers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve employers'
            ];
        }
    }

    /**
     * Get job postings (API handler)
     */
    public function getJobPostings(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM job_postings WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['employer_id'])) {
                $sql .= " AND employer_id = ?";
                $params[] = $filters['employer_id'];
            }

            if (isset($filters['category'])) {
                $sql .= " AND category = ?";
                $params[] = $filters['category'];
            }

            $sql .= " ORDER BY posted_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['salary_range'] = json_decode($result['salary_range'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
                $result['benefits'] = json_decode($result['benefits'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting job postings: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve job postings'
            ];
        }
    }

    /**
     * Get training programs (API handler)
     */
    public function getTrainingPrograms(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM training_programs WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['category'])) {
                $sql .= " AND category = ?";
                $params[] = $filters['category'];
            }

            $sql .= " ORDER BY start_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['prerequisites'] = json_decode($result['prerequisites'], true);
                $result['learning_objectives'] = json_decode($result['learning_objectives'], true);
                $result['evaluation_results'] = json_decode($result['evaluation_results'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting training programs: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve training programs'
            ];
        }
    }

    /**
     * Enroll in training (API handler)
     */
    public function enrollInTraining(string $programId, array $enrollmentData): array
    {
        // Check if program exists and is active
        $program = $this->getTrainingProgram($programId);
        if (!$program || $program['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Training program not found or not active'
            ];
        }

        // Check capacity
        if ($program['enrolled_count'] >= $program['max_participants']) {
            return [
                'success' => false,
                'error' => 'Training program is full'
            ];
        }

        // Check enrollment deadline
        $deadline = date('Y-m-d', strtotime($program['start_date'] . ' -' . $this->config['training_enrollment_deadline_days'] . ' days'));
        if (date('Y-m-d') > $deadline) {
            return [
                'success' => false,
                'error' => 'Enrollment deadline has passed'
            ];
        }

        // Update enrollment count
        $this->incrementTrainingEnrollment($programId);

        // Send notification
        $this->sendNotification('training_enrollment_confirmed', $enrollmentData['user_id'], [
            'program_name' => $program['title'],
            'start_date' => $program['start_date']
        ]);

        return [
            'success' => true,
            'message' => 'Successfully enrolled in training program',
            'program_name' => $program['title'],
            'start_date' => $program['start_date']
        ];
    }

    /**
     * Generate seeker ID
     */
    private function generateSeekerId(): string
    {
        return 'JS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate employer ID
     */
    private function generateEmployerId(): string
    {
        return 'EMP' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate job ID
     */
    private function generateJobId(): string
    {
        return 'JOB' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate application ID
     */
    private function generateApplicationId(): string
    {
        return 'APP' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save job seeker
     */
    private function saveJobSeeker(array $seeker): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO job_seekers (
                seeker_id, user_id, profile_complete, personal_info,
                skills_experience, education_background, work_preferences,
                availability_status, registration_date, documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $seeker['seeker_id'],
                $seeker['user_id'],
                $seeker['profile_complete'],
                $seeker['personal_info'],
                $seeker['skills_experience'],
                $seeker['education_background'],
                $seeker['work_preferences'],
                $seeker['availability_status'],
                $seeker['registration_date'],
                $seeker['documents'],
                $seeker['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving job seeker: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save employer
     */
    private function saveEmployer(array $employer): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO employers (
                employer_id, user_id, company_name, company_type,
                industry, company_size, location, contact_info,
                verification_status, registration_date, documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $employer['employer_id'],
                $employer['user_id'],
                $employer['company_name'],
                $employer['company_type'],
                $employer['industry'],
                $employer['company_size'],
                $employer['location'],
                $employer['contact_info'],
                $employer['verification_status'],
                $employer['registration_date'],
                $employer['documents'],
                $employer['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving employer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save job posting
     */
    private function saveJobPosting(array $job): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO job_postings (
                job_id, employer_id, title, description, job_type,
                category, location, remote_work, salary_range,
                requirements, benefits, application_deadline, status,
                posted_date, documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $job['job_id'],
                $job['employer_id'],
                $job['title'],
                $job['description'],
                $job['job_type'],
                $job['category'],
                $job['location'],
                $job['remote_work'],
                $job['salary_range'],
                $job['requirements'],
                $job['benefits'],
                $job['application_deadline'],
                $job['status'],
                $job['posted_date'],
                $job['documents'],
                $job['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving job posting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save job application
     */
    private function saveJobApplication(array $application): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO job_applications (
                application_id, job_id, seeker_id, cover_letter,
                resume_file, additional_documents, application_date,
                status, employer_notes, seeker_notes, interview_scheduled,
                offer_details, feedback
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $application['application_id'],
                $application['job_id'],
                $application['seeker_id'],
                $application['cover_letter'],
                $application['resume_file'],
                $application['additional_documents'],
                $application['application_date'],
                $application['status'],
                $application['employer_notes'],
                $application['seeker_notes'],
                $application['interview_scheduled'],
                $application['offer_details'],
                $application['feedback']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving job application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get job posting
     */
    private function getJobPosting(string $jobId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM job_postings WHERE job_id = ?";
            $result = $db->fetch($sql, [$jobId]);

            if ($result) {
                $result['salary_range'] = json_decode($result['salary_range'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
                $result['benefits'] = json_decode($result['benefits'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting job posting: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get training program
     */
    private function getTrainingProgram(string $programId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM training_programs WHERE program_id = ?";
            $result = $db->fetch($sql, [$programId]);

            if ($result) {
                $result['prerequisites'] = json_decode($result['prerequisites'], true);
                $result['learning_objectives'] = json_decode($result['learning_objectives'], true);
                $result['evaluation_results'] = json_decode($result['evaluation_results'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting training program: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Increment job application count
     */
    private function incrementJobApplicationCount(string $jobId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE job_postings SET applications_count = applications_count + 1 WHERE job_id = ?";
            return $db->execute($sql, [$jobId]);
        } catch (\Exception $e) {
            error_log("Error incrementing job application count: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment training enrollment
     */
    private function incrementTrainingEnrollment(string $programId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE training_programs SET enrolled_count = enrolled_count + 1 WHERE program_id = ?";
            return $db->execute($sql, [$programId]);
        } catch (\Exception $e) {
            error_log("Error incrementing training enrollment: " . $e->getMessage());
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
     * Validate job seeker data
     */
    private function validateJobSeekerData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'full_name', 'date_of_birth', 'contact_phone',
            'contact_email', 'address', 'education_level', 'work_experience_years',
            'skills', 'preferred_job_type', 'availability_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate email format
        if (isset($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Validate date of birth (must be at least 16 years old)
        if (isset($data['date_of_birth'])) {
            $dob = strtotime($data['date_of_birth']);
            $age = (time() - $dob) / (365.25 * 24 * 60 * 60);
            if ($age < 16) {
                $errors[] = "Must be at least 16 years old";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate employer data
     */
    private function validateEmployerData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'company_name', 'company_type', 'industry',
            'company_size', 'business_address', 'contact_person',
            'contact_phone', 'contact_email', 'business_description', 'employee_count'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate email format
        if (isset($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Validate website URL if provided
        if (isset($data['website']) && !empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid website URL format";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate job data
     */
    private function validateJobData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'employer_id', 'title', 'description', 'job_type',
            'category', 'location', 'requirements'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate salary range
        if (isset($data['salary_min']) && isset($data['salary_max'])) {
            if ($data['salary_min'] > $data['salary_max']) {
                $errors[] = "Minimum salary cannot be greater than maximum salary";
            }
        }

        // Validate application deadline
        if (isset($data['application_deadline'])) {
            $deadline = strtotime($data['application_deadline']);
            if ($deadline < time()) {
                $errors[] = "Application deadline cannot be in the past";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate application data
     */
    private function validateApplicationData(array $data): array
    {
        $errors = [];

        $requiredFields = ['seeker_id'];

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
}

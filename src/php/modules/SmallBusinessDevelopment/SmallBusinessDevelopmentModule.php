<?php
/**
 * Small Business Development Module
 * Handles business incubation, microfinance, and entrepreneurship support
 */

require_once __DIR__ . '/../ServiceModule.php';

class SmallBusinessDevelopmentModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Small Business Development Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive small business development services including business incubation, microfinance, entrepreneurship training, and SME support programs',
            'dependencies' => ['FinancialManagement', 'BusinessLicenses', 'Procurement'],
            'permissions' => [
                'business.incubation' => 'Access business incubation services',
                'business.microfinance' => 'Access microfinance services',
                'business.training' => 'Access entrepreneurship training',
                'business.admin' => 'Administrative functions'
            ]
        ];
    }

    public function install() {
        $this->createTables();
        $this->setupWorkflows();
        $this->createDirectories();
        return true;
    }

    public function uninstall() {
        $this->dropTables();
        return true;
    }

    private function createTables() {
        $sql = "
            CREATE TABLE IF NOT EXISTS business_incubators (
                id INT PRIMARY KEY AUTO_INCREMENT,
                incubator_code VARCHAR(20) UNIQUE NOT NULL,
                incubator_name VARCHAR(200) NOT NULL,
                incubator_description TEXT,

                -- Incubator Details
                incubator_type ENUM('general', 'technology', 'agritech', 'healthtech', 'fintech', 'other') DEFAULT 'general',
                focus_industries TEXT,
                target_business_stage ENUM('idea', 'prototype', 'mvp', 'early_revenue', 'scaling') DEFAULT 'idea',

                -- Location and Facilities
                address TEXT NOT NULL,
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100),
                country VARCHAR(100) DEFAULT 'Domestic',
                total_space_sqft INT,
                available_space_sqft INT,

                -- Services Offered
                mentorship_available BOOLEAN DEFAULT TRUE,
                funding_access BOOLEAN DEFAULT TRUE,
                workspace_provided BOOLEAN DEFAULT TRUE,
                technical_support BOOLEAN DEFAULT TRUE,
                legal_services BOOLEAN DEFAULT FALSE,
                accounting_services BOOLEAN DEFAULT FALSE,

                -- Program Details
                program_duration_months INT DEFAULT 12,
                graduation_requirements TEXT,
                success_metrics TEXT,

                -- Fees and Costs
                monthly_fee DECIMAL(8,2),
                application_fee DECIMAL(6,2),
                equity_stake_percentage DECIMAL(5,2),

                -- Capacity and Status
                max_residents INT,
                current_residents INT DEFAULT 0,
                incubator_status ENUM('planning', 'operational', 'full', 'closed') DEFAULT 'operational',

                -- Contact Information
                contact_person VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),
                website VARCHAR(255),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_incubator_code (incubator_code),
                INDEX idx_incubator_type (incubator_type),
                INDEX idx_city (city),
                INDEX idx_incubator_status (incubator_status)
            );

            CREATE TABLE IF NOT EXISTS incubator_residents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                resident_code VARCHAR(20) UNIQUE NOT NULL,
                incubator_id INT NOT NULL,
                business_id INT NOT NULL,

                -- Resident Details
                company_name VARCHAR(200) NOT NULL,
                founder_name VARCHAR(200) NOT NULL,
                founder_email VARCHAR(255),
                founder_phone VARCHAR(20),

                -- Business Information
                business_description TEXT,
                industry_sector VARCHAR(100),
                business_stage ENUM('idea', 'prototype', 'mvp', 'early_revenue', 'scaling') DEFAULT 'idea',
                incorporation_date DATE,

                -- Program Enrollment
                enrollment_date DATE NOT NULL,
                expected_graduation_date DATE,
                program_status ENUM('active', 'graduated', 'terminated', 'extended') DEFAULT 'active',

                -- Progress Tracking
                milestones_completed TEXT,
                next_milestone TEXT,
                mentor_assigned VARCHAR(200),

                -- Resources Utilized
                workspace_assigned VARCHAR(100),
                funding_received DECIMAL(10,2) DEFAULT 0,
                services_used TEXT,

                -- Performance Metrics
                revenue_generated DECIMAL(12,2) DEFAULT 0,
                employees_hired INT DEFAULT 0,
                customers_acquired INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (incubator_id) REFERENCES business_incubators(id) ON DELETE CASCADE,
                FOREIGN KEY (business_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_resident_code (resident_code),
                INDEX idx_incubator_id (incubator_id),
                INDEX idx_business_id (business_id),
                INDEX idx_program_status (program_status),
                INDEX idx_enrollment_date (enrollment_date)
            );

            CREATE TABLE IF NOT EXISTS microfinance_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('microloan', 'microgrant', 'microcredit', 'microinsurance', 'savings_program') DEFAULT 'microloan',
                target_beneficiaries TEXT,
                eligibility_criteria TEXT,

                -- Financial Parameters
                minimum_loan_amount DECIMAL(8,2) DEFAULT 100,
                maximum_loan_amount DECIMAL(10,2) DEFAULT 50000,
                interest_rate DECIMAL(5,3),
                repayment_period_months INT DEFAULT 12,

                -- Program Rules
                collateral_required BOOLEAN DEFAULT FALSE,
                credit_score_required BOOLEAN DEFAULT FALSE,
                business_plan_required BOOLEAN DEFAULT TRUE,
                training_required BOOLEAN DEFAULT TRUE,

                -- Funding and Capacity
                total_fund_available DECIMAL(15,2),
                funds_disbursed DECIMAL(15,2) DEFAULT 0,
                active_loans INT DEFAULT 0,
                default_rate DECIMAL(5,3) DEFAULT 0,

                -- Program Status
                program_status ENUM('active', 'inactive', 'full', 'closed') DEFAULT 'active',
                start_date DATE,
                end_date DATE,

                -- Contact Information
                program_manager VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_program_status (program_status)
            );

            CREATE TABLE IF NOT EXISTS microfinance_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                program_id INT NOT NULL,
                applicant_id INT NOT NULL,

                -- Applicant Information
                applicant_name VARCHAR(200) NOT NULL,
                applicant_email VARCHAR(255),
                applicant_phone VARCHAR(20),
                applicant_address TEXT,

                -- Business Information
                business_name VARCHAR(200),
                business_type VARCHAR(100),
                business_age_months INT,
                number_of_employees INT DEFAULT 0,

                -- Financial Information
                monthly_revenue DECIMAL(10,2),
                monthly_expenses DECIMAL(10,2),
                existing_debt DECIMAL(10,2) DEFAULT 0,
                requested_amount DECIMAL(8,2) NOT NULL,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'disbursed', 'repaid', 'defaulted') DEFAULT 'draft',
                review_date DATE,
                approval_date DATE,

                -- Loan Details (if approved)
                approved_amount DECIMAL(8,2),
                interest_rate DECIMAL(5,3),
                repayment_period_months INT,
                disbursement_date DATE,

                -- Repayment Tracking
                total_repaid DECIMAL(8,2) DEFAULT 0,
                next_payment_date DATE,
                payment_schedule TEXT,
                repayment_status ENUM('current', 'late', 'defaulted', 'completed') DEFAULT 'current',

                -- Supporting Documents
                business_plan TEXT,
                financial_statements TEXT,
                collateral_details TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (program_id) REFERENCES microfinance_programs(id) ON DELETE CASCADE,
                FOREIGN KEY (applicant_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_program_id (program_id),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS entrepreneurship_training (
                id INT PRIMARY KEY AUTO_INCREMENT,
                training_code VARCHAR(20) UNIQUE NOT NULL,
                training_name VARCHAR(200) NOT NULL,
                training_description TEXT,

                -- Training Details
                training_category ENUM('business_basics', 'financial_management', 'marketing', 'operations', 'leadership', 'technical_skills', 'other') DEFAULT 'business_basics',
                training_format ENUM('workshop', 'course', 'mentorship', 'webinar', 'bootcamp', 'certificate_program') DEFAULT 'workshop',

                -- Target Audience
                target_audience TEXT,
                prerequisite_skills TEXT,
                minimum_participants INT DEFAULT 1,
                maximum_participants INT,

                -- Schedule and Duration
                duration_hours DECIMAL(6,2),
                number_of_sessions INT,
                session_schedule TEXT,
                start_date DATE,
                end_date DATE,

                -- Delivery Method
                delivery_method ENUM('in_person', 'online', 'hybrid') DEFAULT 'in_person',
                location VARCHAR(200),
                virtual_platform VARCHAR(100),

                -- Resources and Materials
                training_materials TEXT,
                required_materials TEXT,
                certification_provided BOOLEAN DEFAULT FALSE,

                -- Instructor Information
                instructor_name VARCHAR(200),
                instructor_credentials TEXT,
                instructor_contact VARCHAR(255),

                -- Fees and Costs
                course_fee DECIMAL(8,2) DEFAULT 0,
                material_fee DECIMAL(6,2) DEFAULT 0,
                scholarship_available BOOLEAN DEFAULT TRUE,

                -- Status and Capacity
                training_status ENUM('planned', 'open', 'full', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
                enrolled_participants INT DEFAULT 0,
                waitlist_count INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_training_code (training_code),
                INDEX idx_training_category (training_category),
                INDEX idx_training_format (training_format),
                INDEX idx_delivery_method (delivery_method),
                INDEX idx_training_status (training_status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS training_enrollments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                enrollment_code VARCHAR(20) UNIQUE NOT NULL,
                training_id INT NOT NULL,
                participant_id INT NOT NULL,

                -- Enrollment Details
                enrollment_date DATE NOT NULL,
                enrollment_status ENUM('enrolled', 'confirmed', 'attended', 'completed', 'dropped', 'waitlisted') DEFAULT 'enrolled',

                -- Participant Information
                participant_name VARCHAR(200) NOT NULL,
                participant_email VARCHAR(255),
                participant_phone VARCHAR(20),

                -- Payment Information
                fee_paid DECIMAL(8,2) DEFAULT 0,
                payment_date DATE,
                payment_method VARCHAR(50),

                -- Attendance Tracking
                sessions_attended INT DEFAULT 0,
                total_sessions INT,
                attendance_percentage DECIMAL(5,2) DEFAULT 0,

                -- Assessment and Certification
                pre_assessment_score DECIMAL(5,2),
                post_assessment_score DECIMAL(5,2),
                certification_earned BOOLEAN DEFAULT FALSE,
                certificate_issued_date DATE,

                -- Feedback
                participant_feedback TEXT,
                instructor_feedback TEXT,
                overall_rating DECIMAL(3,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (training_id) REFERENCES entrepreneurship_training(id) ON DELETE CASCADE,
                FOREIGN KEY (participant_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_enrollment_code (enrollment_code),
                INDEX idx_training_id (training_id),
                INDEX idx_participant_id (participant_id),
                INDEX idx_enrollment_status (enrollment_status),
                INDEX idx_enrollment_date (enrollment_date)
            );

            CREATE TABLE IF NOT EXISTS business_mentorship (
                id INT PRIMARY KEY AUTO_INCREMENT,
                mentorship_code VARCHAR(20) UNIQUE NOT NULL,
                mentor_id INT NOT NULL,
                mentee_id INT NOT NULL,

                -- Mentorship Details
                mentorship_program VARCHAR(100),
                mentorship_focus TEXT,
                mentorship_goals TEXT,

                -- Relationship Details
                start_date DATE NOT NULL,
                end_date DATE,
                mentorship_status ENUM('active', 'completed', 'terminated', 'on_hold') DEFAULT 'active',

                -- Meeting Schedule
                meeting_frequency VARCHAR(50),
                preferred_meeting_times TEXT,
                meeting_format ENUM('in_person', 'virtual', 'hybrid') DEFAULT 'virtual',

                -- Progress Tracking
                meetings_completed INT DEFAULT 0,
                next_meeting_date DATE,
                meeting_notes TEXT,

                -- Goals and Milestones
                goals_set TEXT,
                milestones_achieved TEXT,
                challenges_faced TEXT,

                -- Feedback and Evaluation
                mentee_feedback TEXT,
                mentor_feedback TEXT,
                overall_satisfaction DECIMAL(3,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (mentor_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                FOREIGN KEY (mentee_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_mentorship_code (mentorship_code),
                INDEX idx_mentor_id (mentor_id),
                INDEX idx_mentee_id (mentee_id),
                INDEX idx_mentorship_status (mentorship_status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS business_grants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                grant_code VARCHAR(20) UNIQUE NOT NULL,
                grant_name VARCHAR(200) NOT NULL,
                grant_description TEXT,

                -- Grant Details
                grant_category ENUM('startup', 'expansion', 'innovation', 'export', 'rural', 'minority_owned', 'other') DEFAULT 'startup',
                grant_type ENUM('competitive', 'formula', 'discretionary') DEFAULT 'competitive',

                -- Eligibility
                eligible_businesses TEXT,
                minimum_business_age_months INT DEFAULT 0,
                maximum_business_age_months INT,
                minimum_employees INT DEFAULT 0,
                maximum_employees INT,

                -- Financial Details
                minimum_grant_amount DECIMAL(8,2) DEFAULT 1000,
                maximum_grant_amount DECIMAL(10,2) DEFAULT 50000,
                total_funding_available DECIMAL(15,2),

                -- Application Process
                application_deadline DATE,
                review_process TEXT,
                award_notification_date DATE,

                -- Grant Terms
                grant_period_months INT DEFAULT 12,
                reporting_requirements TEXT,
                matching_funds_required BOOLEAN DEFAULT FALSE,
                matching_funds_percentage DECIMAL(5,2),

                -- Status and Tracking
                grant_status ENUM('open', 'closed', 'awards_pending', 'completed') DEFAULT 'open',
                applications_received INT DEFAULT 0,
                awards_made INT DEFAULT 0,
                funds_disbursed DECIMAL(15,2) DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_grant_code (grant_code),
                INDEX idx_grant_category (grant_category),
                INDEX idx_grant_status (grant_status),
                INDEX idx_application_deadline (application_deadline)
            );

            CREATE TABLE IF NOT EXISTS grant_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                grant_id INT NOT NULL,
                applicant_id INT NOT NULL,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'awarded', 'completed', 'terminated') DEFAULT 'draft',

                -- Business Information
                business_name VARCHAR(200) NOT NULL,
                business_description TEXT,
                business_age_months INT,
                number_of_employees INT DEFAULT 0,

                -- Grant Request
                requested_amount DECIMAL(10,2) NOT NULL,
                grant_purpose TEXT,
                project_description TEXT,

                -- Financial Information
                current_revenue DECIMAL(12,2) DEFAULT 0,
                projected_revenue DECIMAL(12,2),
                matching_funds_available DECIMAL(10,2) DEFAULT 0,

                -- Review Information
                review_date DATE,
                reviewer_notes TEXT,
                approval_date DATE,

                -- Award Information
                awarded_amount DECIMAL(10,2) DEFAULT 0,
                award_date DATE,
                award_conditions TEXT,

                -- Reporting and Compliance
                progress_reports TEXT,
                final_report TEXT,
                compliance_status ENUM('compliant', 'warning', 'non_compliant') DEFAULT 'compliant',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (grant_id) REFERENCES business_grants(id) ON DELETE CASCADE,
                FOREIGN KEY (applicant_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_grant_id (grant_id),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS business_networking_events (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_code VARCHAR(20) UNIQUE NOT NULL,
                event_name VARCHAR(200) NOT NULL,
                event_description TEXT,

                -- Event Details
                event_type ENUM('workshop', 'conference', 'networking', 'pitch_event', 'trade_show', 'webinar', 'other') DEFAULT 'networking',
                event_category ENUM('general', 'technology', 'finance', 'marketing', 'legal', 'industry_specific') DEFAULT 'general',

                -- Schedule and Location
                event_date DATE NOT NULL,
                start_time TIME,
                end_time TIME,
                timezone VARCHAR(50) DEFAULT 'UTC',

                -- Location Details
                venue_name VARCHAR(200),
                address TEXT,
                city VARCHAR(100),
                virtual_platform VARCHAR(100),
                event_format ENUM('in_person', 'virtual', 'hybrid') DEFAULT 'in_person',

                -- Capacity and Registration
                max_attendees INT,
                min_attendees INT DEFAULT 1,
                registration_deadline DATE,
                registration_fee DECIMAL(6,2) DEFAULT 0,

                -- Target Audience
                target_business_stage TEXT,
                target_industries TEXT,
                target_participants TEXT,

                -- Event Content
                agenda TEXT,
                speakers_presenters TEXT,
                sponsors TEXT,

                -- Status and Tracking
                event_status ENUM('planned', 'open', 'full', 'confirmed', 'completed', 'cancelled', 'postponed') DEFAULT 'planned',
                registered_attendees INT DEFAULT 0,
                actual_attendees INT DEFAULT 0,

                -- Contact Information
                organizer_name VARCHAR(200),
                organizer_email VARCHAR(255),
                organizer_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_event_code (event_code),
                INDEX idx_event_type (event_type),
                INDEX idx_event_date (event_date),
                INDEX idx_event_status (event_status)
            );

            CREATE TABLE IF NOT EXISTS event_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                registration_code VARCHAR(20) UNIQUE NOT NULL,
                event_id INT NOT NULL,
                attendee_id INT NOT NULL,

                -- Registration Details
                registration_date DATE NOT NULL,
                registration_status ENUM('registered', 'confirmed', 'attended', 'no_show', 'cancelled') DEFAULT 'registered',

                -- Attendee Information
                attendee_name VARCHAR(200) NOT NULL,
                attendee_email VARCHAR(255),
                attendee_phone VARCHAR(20),
                attendee_company VARCHAR(200),

                -- Payment Information
                registration_fee DECIMAL(6,2) DEFAULT 0,
                payment_date DATE,
                payment_method VARCHAR(50),

                -- Attendance Tracking
                check_in_time DATETIME,
                check_out_time DATETIME,
                session_attendance TEXT,

                -- Networking and Follow-up
                connections_made TEXT,
                follow_up_actions TEXT,
                event_feedback TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (event_id) REFERENCES business_networking_events(id) ON DELETE CASCADE,
                FOREIGN KEY (attendee_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_registration_code (registration_code),
                INDEX idx_event_id (event_id),
                INDEX idx_attendee_id (attendee_id),
                INDEX idx_registration_status (registration_status),
                INDEX idx_registration_date (registration_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup business incubation workflow
        $incubationWorkflow = [
            'name' => 'Business Incubation Application Process',
            'description' => 'Complete workflow for business incubation program applications',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'entrepreneur',
                    'form' => 'incubation_application_form'
                ],
                [
                    'name' => 'Initial Screening',
                    'type' => 'service_task',
                    'service' => 'application_screening_service'
                ],
                [
                    'name' => 'Business Plan Review',
                    'type' => 'user_task',
                    'assignee' => 'incubation_manager',
                    'form' => 'business_plan_review_form'
                ],
                [
                    'name' => 'Interview Process',
                    'type' => 'user_task',
                    'assignee' => 'interview_committee',
                    'form' => 'entrepreneur_interview_form'
                ],
                [
                    'name' => 'Final Selection',
                    'type' => 'user_task',
                    'assignee' => 'incubation_director',
                    'form' => 'incubation_selection_form'
                ],
                [
                    'name' => 'Program Onboarding',
                    'type' => 'user_task',
                    'assignee' => 'program_coordinator',
                    'form' => 'program_onboarding_form'
                ]
            ]
        ];

        // Setup microfinance application workflow
        $microfinanceWorkflow = [
            'name' => 'Microfinance Application Process',
            'description' => 'Complete workflow for microfinance loan applications',
            'steps' => [
                [
                    'name' => 'Loan Application',
                    'type' => 'user_task',
                    'assignee' => 'borrower',
                    'form' => 'loan_application_form'
                ],
                [
                    'name' => 'Credit Assessment',
                    'type' => 'service_task',
                    'service' => 'credit_assessment_service'
                ],
                [
                    'name' => 'Business Evaluation',
                    'type' => 'user_task',
                    'assignee' => 'loan_officer',
                    'form' => 'business_evaluation_form'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'user_task',
                    'assignee' => 'risk_officer',
                    'form' => 'risk_assessment_form'
                ],
                [
                    'name' => 'Loan Approval',
                    'type' => 'user_task',
                    'assignee' => 'loan_committee',
                    'form' => 'loan_approval_form'
                ],
                [
                    'name' => 'Loan Disbursement',
                    'type' => 'service_task',
                    'service' => 'loan_disbursement_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/incubation_workflow.json', json_encode($incubationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/microfinance_workflow.json', json_encode($microfinanceWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/incubation_applications',
            __DIR__ . '/uploads/microfinance_applications',
            __DIR__ . '/uploads/training_materials',
            __DIR__ . '/uploads/grant_applications',
            __DIR__ . '/uploads/event_materials',
            __DIR__ . '/templates',
            __DIR__ . '/config'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function dropTables() {
        $tables = [
            'event_registrations',
            'business_networking_events',
            'grant_applications',
            'business_grants',
            'business_mentorship',
            'training_enrollments',
            'entrepreneurship_training',
            'microfinance_applications',
            'microfinance_programs',
            'incubator_residents',
            'business_incubators'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createBusinessIncubator($data) {
        try {
            $this->validateIncubatorData($data);
            $incubatorCode = $this->generateIncubatorCode();

            $sql = "INSERT INTO business_incubators (
                incubator_code, incubator_name, incubator_description, incubator_type,
                focus_industries, target_business_stage, address, city, state_province,
                country, total_space_sqft, available_space_sqft, mentorship_available,
                funding_access, workspace_provided, technical_support, legal_services,
                accounting_services, program_duration_months, graduation_requirements,
                success_metrics, monthly_fee, application_fee, equity_stake_percentage,
                max_residents, incubator_status, contact_person, contact_email,
                contact_phone, website, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $incubatorId = $this->db->insert($sql, [
                $incubatorCode, $data['incubator_name'], $data['incubator_description'] ?? null,
                $data['incubator_type'] ?? 'general', $data['focus_industries'] ?? null,
                $data['target_business_stage'] ?? 'idea', json_encode($data['address']),
                $data['city'], $data['state_province'] ?? null, $data['country'] ?? 'Domestic',
                $data['total_space_sqft'] ?? null, $data['available_space_sqft'] ?? null,
                $data['mentorship_available'] ?? true, $data['funding_access'] ?? true,
                $data['workspace_provided'] ?? true, $data['technical_support'] ?? true,
                $data['legal_services'] ?? false, $data['accounting_services'] ?? false,
                $data['program_duration_months'] ?? 12, $data['graduation_requirements'] ?? null,
                $data['success_metrics'] ?? null, $data['monthly_fee'] ?? null,
                $data['application_fee'] ?? null, $data['equity_stake_percentage'] ?? null,
                $data['max_residents'] ?? null, $data['incubator_status'] ?? 'operational',
                $data['contact_person'] ?? null, $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null, $data['website'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'incubator_id' => $incubatorId,
                'incubator_code' => $incubatorCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForIncubation($data) {
        try {
            $this->validateIncubationApplicationData($data);
            $residentCode = $this->generateResidentCode();

            $sql = "INSERT INTO incubator_residents (
                resident_code, incubator_id, business_id, company_name, founder_name,
                founder_email, founder_phone, business_description, industry_sector,
                business_stage, incorporation_date, enrollment_date, expected_graduation_date,
                program_status, milestones_completed, next_milestone, mentor_assigned,
                workspace_assigned, funding_received, services_used, revenue_generated,
                employees_hired, customers_acquired, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $residentId = $this->db->insert($sql, [
                $residentCode, $data['incubator_id'], $data['business_id'],
                $data['company_name'], $data['founder_name'], $data['founder_email'] ?? null,
                $data['founder_phone'] ?? null, $data['business_description'] ?? null,
                $data['industry_sector'] ?? null, $data['business_stage'] ?? 'idea',
                $data['incorporation_date'] ?? null, $data['enrollment_date'],
                $data['expected_graduation_date'] ?? null, $data['program_status'] ?? 'active',
                $data['milestones_completed'] ?? null, $data['next_milestone'] ?? null,
                $data['mentor_assigned'] ?? null, $data['workspace_assigned'] ?? null,
                $data['funding_received'] ?? 0, $data['services_used'] ?? null,
                $data['revenue_generated'] ?? 0, $data['employees_hired'] ?? 0,
                $data['customers_acquired'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'resident_id' => $residentId,
                'resident_code' => $residentCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createMicrofinanceProgram($data) {
        try {
            $this->validateMicrofinanceProgramData($data);
            $programCode = $this->generateMicrofinanceProgramCode();

            $sql = "INSERT INTO microfinance_programs (
                program_code, program_name, program_description, program_type,
                target_beneficiaries, eligibility_criteria, minimum_loan_amount,
                maximum_loan_amount, interest_rate, repayment_period_months,
                collateral_required, credit_score_required, business_plan_required,
                training_required, total_fund_available, program_status,
                start_date, end_date, program_manager, contact_email,
                contact_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'] ?? 'microloan', $data['target_beneficiaries'] ?? null,
                $data['eligibility_criteria'] ?? null, $data['minimum_loan_amount'] ?? 100,
                $data['maximum_loan_amount'] ?? 50000, $data['interest_rate'] ?? null,
                $data['repayment_period_months'] ?? 12, $data['collateral_required'] ?? false,
                $data['credit_score_required'] ?? false, $data['business_plan_required'] ?? true,
                $data['training_required'] ?? true, $data['total_fund_available'] ?? null,
                $data['program_status'] ?? 'active', $data['start_date'] ?? null,
                $data['end_date'] ?? null, $data['program_manager'] ?? null,
                $data['contact_email'] ?? null, $data['contact_phone'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'program_id' => $programId,
                'program_code' => $programCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForMicrofinance($data) {
        try {
            $this->validateMicrofinanceApplicationData($data);
            $applicationNumber = $this->generateMicrofinanceApplicationNumber();

            $sql = "INSERT INTO microfinance_applications (
                application_number, program_id, applicant_id, applicant_name,
                applicant_email, applicant_phone, applicant_address, business_name,
                business_type, business_age_months, number_of_employees,
                monthly_revenue, monthly_expenses, existing_debt, requested_amount,
                application_date, business_plan, financial_statements,
                collateral_details, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['program_id'], $data['applicant_id'],
                $data['applicant_name'], $data['applicant_email'] ?? null,
                $data['applicant_phone'] ?? null, json_encode($data['applicant_address'] ?? []),
                $data['business_name'] ?? null, $data['business_type'] ?? null,
                $data['business_age_months'] ?? null, $data['number_of_employees'] ?? 0,
                $data['monthly_revenue'] ?? null, $data['monthly_expenses'] ?? null,
                $data['existing_debt'] ?? 0, $data['requested_amount'],
                $data['application_date'], $data['business_plan'] ?? null,
                $data['financial_statements'] ?? null, $data['collateral_details'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'application_id' => $applicationId,
                'application_number' => $applicationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createEntrepreneurshipTraining($data) {
        try {
            $this->validateTrainingData($data);
            $trainingCode = $this->generateTrainingCode();

            $sql = "INSERT INTO entrepreneurship_training (
                training_code, training_name, training_description, training_category,
                training_format, target_audience, prerequisite_skills,
                minimum_participants, maximum_participants, duration_hours,
                number_of_sessions, session_schedule, start_date, end_date,
                delivery_method, location, virtual_platform, training_materials,
                required_materials, certification_provided, instructor_name,
                instructor_credentials, instructor_contact, course_fee,
                material_fee, scholarship_available, training_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $trainingId = $this->db->insert($sql, [
                $trainingCode, $data['training_name'], $data['training_description'] ?? null,
                $data['training_category'] ?? 'business_basics', $data['training_format'] ?? 'workshop',
                $data['target_audience'] ?? null, $data['prerequisite_skills'] ?? null,
                $data['minimum_participants'] ?? 1, $data['maximum_participants'] ?? null,
                $data['duration_hours'] ?? null, $data['number_of_sessions'] ?? null,
                $data['session_schedule'] ?? null, $data['start_date'] ?? null,
                $data['end_date'] ?? null, $data['delivery_method'] ?? 'in_person',
                $data['location'] ?? null, $data['virtual_platform'] ?? null,
                $data['training_materials'] ?? null, $data['required_materials'] ?? null,
                $data['certification_provided'] ?? false, $data['instructor_name'] ?? null,
                $data['instructor_credentials'] ?? null, $data['instructor_contact'] ?? null,
                $data['course_fee'] ?? 0, $data['material_fee'] ?? 0,
                $data['scholarship_available'] ?? true, $data['training_status'] ?? 'planned',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'training_id' => $trainingId,
                'training_code' => $trainingCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function enrollInTraining($data) {
        try {
            $this->validateTrainingEnrollmentData($data);
            $enrollmentCode = $this->generateEnrollmentCode();

            $sql = "INSERT INTO training_enrollments (
                enrollment_code, training_id, participant_id, enrollment_date,
                participant_name, participant_email, participant_phone,
                fee_paid, payment_date, payment_method, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $enrollmentId = $this->db->insert($sql, [
                $enrollmentCode, $data['training_id'], $data['participant_id'],
                $data['enrollment_date'], $data['participant_name'],
                $data['participant_email'] ?? null, $data['participant_phone'] ?? null,
                $data['fee_paid'] ?? 0, $data['payment_date'] ?? null,
                $data['payment_method'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'enrollment_id' => $enrollmentId,
                'enrollment_code' => $enrollmentCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createBusinessMentorship($data) {
        try {
            $this->validateMentorshipData($data);
            $mentorshipCode = $this->generateMentorshipCode();

            $sql = "INSERT INTO business_mentorship (
                mentorship_code, mentor_id, mentee_id, mentorship_program,
                mentorship_focus, mentorship_goals, start_date, end_date,
                mentorship_status, meeting_frequency, preferred_meeting_times,
                meeting_format, goals_set, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $mentorshipId = $this->db->insert($sql, [
                $mentorshipCode, $data['mentor_id'], $data['mentee_id'],
                $data['mentorship_program'] ?? null, $data['mentorship_focus'] ?? null,
                $data['mentorship_goals'] ?? null, $data['start_date'],
                $data['end_date'] ?? null, $data['mentorship_status'] ?? 'active',
                $data['meeting_frequency'] ?? null, $data['preferred_meeting_times'] ?? null,
                $data['meeting_format'] ?? 'virtual', $data['goals_set'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'mentorship_id' => $mentorshipId,
                'mentorship_code' => $mentorshipCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createBusinessGrant($data) {
        try {
            $this->validateGrantData($data);
            $grantCode = $this->generateGrantCode();

            $sql = "INSERT INTO business_grants (
                grant_code, grant_name, grant_description, grant_category,
                grant_type, eligible_businesses, minimum_business_age_months,
                maximum_business_age_months, minimum_employees, maximum_employees,
                minimum_grant_amount, maximum_grant_amount, total_funding_available,
                application_deadline, review_process, award_notification_date,
                grant_period_months, reporting_requirements, matching_funds_required,
                matching_funds_percentage, grant_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $grantId = $this->db->insert($sql, [
                $grantCode, $data['grant_name'], $data['grant_description'] ?? null,
                $data['grant_category'] ?? 'startup', $data['grant_type'] ?? 'competitive',
                $data['eligible_businesses'] ?? null, $data['minimum_business_age_months'] ?? 0,
                $data['maximum_business_age_months'] ?? null, $data['minimum_employees'] ?? 0,
                $data['maximum_employees'] ?? null, $data['minimum_grant_amount'] ?? 1000,
                $data['maximum_grant_amount'] ?? 50000, $data['total_funding_available'] ?? null,
                $data['application_deadline'] ?? null, $data['review_process'] ?? null,
                $data['award_notification_date'] ?? null, $data['grant_period_months'] ?? 12,
                $data['reporting_requirements'] ?? null, $data['matching_funds_required'] ?? false,
                $data['matching_funds_percentage'] ?? null, $data['grant_status'] ?? 'open',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'grant_id' => $grantId,
                'grant_code' => $grantCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForBusinessGrant($data) {
        try {
            $this->validateGrantApplicationData($data);
            $applicationNumber = $this->generateGrantApplicationNumber();

            $sql = "INSERT INTO grant_applications (
                application_number, grant_id, applicant_id, application_date,
                business_name, business_description, business_age_months,
                number_of_employees, requested_amount, grant_purpose,
                project_description, current_revenue, projected_revenue,
                matching_funds_available, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['grant_id'], $data['applicant_id'],
                $data['application_date'], $data['business_name'],
                $data['business_description'] ?? null, $data['business_age_months'] ?? null,
                $data['number_of_employees'] ?? 0, $data['requested_amount'],
                $data['grant_purpose'] ?? null, $data['project_description'] ?? null,
                $data['current_revenue'] ?? 0, $data['projected_revenue'] ?? null,
                $data['matching_funds_available'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'application_id' => $applicationId,
                'application_number' => $applicationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createNetworkingEvent($data) {
        try {
            $this->validateEventData($data);
            $eventCode = $this->generateEventCode();

            $sql = "INSERT INTO business_networking_events (
                event_code, event_name, event_description, event_type,
                event_category, event_date, start_time, end_time, timezone,
                venue_name, address, city, virtual_platform, event_format,
                max_attendees, registration_deadline, registration_fee,
                target_business_stage, target_industries, target_participants,
                agenda, speakers_presenters, sponsors, event_status,
                organizer_name, organizer_email, organizer_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $eventId = $this->db->insert($sql, [
                $eventCode, $data['event_name'], $data['event_description'] ?? null,
                $data['event_type'] ?? 'networking', $data['event_category'] ?? 'general',
                $data['event_date'], $data['start_time'] ?? null, $data['end_time'] ?? null,
                $data['timezone'] ?? 'UTC', $data['venue_name'] ?? null,
                json_encode($data['address'] ?? []), $data['city'] ?? null,
                $data['virtual_platform'] ?? null, $data['event_format'] ?? 'in_person',
                $data['max_attendees'] ?? null, $data['registration_deadline'] ?? null,
                $data['registration_fee'] ?? 0, $data['target_business_stage'] ?? null,
                $data['target_industries'] ?? null, $data['target_participants'] ?? null,
                $data['agenda'] ?? null, $data['speakers_presenters'] ?? null,
                $data['sponsors'] ?? null, $data['event_status'] ?? 'planned',
                $data['organizer_name'] ?? null, $data['organizer_email'] ?? null,
                $data['organizer_phone'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'event_id' => $eventId,
                'event_code' => $eventCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerForEvent($data) {
        try {
            $this->validateEventRegistrationData($data);
            $registrationCode = $this->generateRegistrationCode();

            $sql = "INSERT INTO event_registrations (
                registration_code, event_id, attendee_id, registration_date,
                attendee_name, attendee_email, attendee_phone, attendee_company,
                registration_fee, payment_date, payment_method, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $registrationId = $this->db->insert($sql, [
                $registrationCode, $data['event_id'], $data['attendee_id'],
                $data['registration_date'], $data['attendee_name'],
                $data['attendee_email'] ?? null, $data['attendee_phone'] ?? null,
                $data['attendee_company'] ?? null, $data['registration_fee'] ?? 0,
                $data['payment_date'] ?? null, $data['payment_method'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'registration_id' => $registrationId,
                'registration_code' => $registrationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Validation Methods
    private function validateIncubatorData($data) {
        if (empty($data['incubator_name'])) {
            throw new Exception('Incubator name is required');
        }
        if (empty($data['address']) || empty($data['city'])) {
            throw new Exception('Address and city are required');
        }
        if (!empty($data['monthly_fee']) && $data['monthly_fee'] < 0) {
            throw new Exception('Monthly fee cannot be negative');
        }
        if (!empty($data['application_fee']) && $data['application_fee'] < 0) {
            throw new Exception('Application fee cannot be negative');
        }
        if (!empty($data['equity_stake_percentage']) &&
            ($data['equity_stake_percentage'] < 0 || $data['equity_stake_percentage'] > 100)) {
            throw new Exception('Equity stake percentage must be between 0 and 100');
        }
    }

    private function validateIncubationApplicationData($data) {
        if (empty($data['incubator_id'])) {
            throw new Exception('Incubator ID is required');
        }
        if (empty($data['business_id'])) {
            throw new Exception('Business ID is required');
        }
        if (empty($data['company_name'])) {
            throw new Exception('Company name is required');
        }
        if (empty($data['founder_name'])) {
            throw new Exception('Founder name is required');
        }
        if (empty($data['enrollment_date'])) {
            throw new Exception('Enrollment date is required');
        }
    }

    private function validateMicrofinanceProgramData($data) {
        if (empty($data['program_name'])) {
            throw new Exception('Program name is required');
        }
        if (!empty($data['minimum_loan_amount']) && !empty($data['maximum_loan_amount']) &&
            $data['minimum_loan_amount'] > $data['maximum_loan_amount']) {
            throw new Exception('Minimum loan amount cannot be greater than maximum loan amount');
        }
        if (!empty($data['interest_rate']) && ($data['interest_rate'] < 0 || $data['interest_rate'] > 100)) {
            throw new Exception('Interest rate must be between 0 and 100');
        }
    }

    private function validateMicrofinanceApplicationData($data) {
        if (empty($data['program_id'])) {
            throw new Exception('Program ID is required');
        }
        if (empty($data['applicant_id'])) {
            throw new Exception('Applicant ID is required');
        }
        if (empty($data['applicant_name'])) {
            throw new Exception('Applicant name is required');
        }
        if (empty($data['requested_amount']) || $data['requested_amount'] <= 0) {
            throw new Exception('Valid requested amount is required');
        }
        if (empty($data['application_date'])) {
            throw new Exception('Application date is required');
        }
    }

    private function validateTrainingData($data) {
        if (empty($data['training_name'])) {
            throw new Exception('Training name is required');
        }
        if (!empty($data['minimum_participants']) && !empty($data['maximum_participants']) &&
            $data['minimum_participants'] > $data['maximum_participants']) {
            throw new Exception('Minimum participants cannot be greater than maximum participants');
        }
        if (!empty($data['course_fee']) && $data['course_fee'] < 0) {
            throw new Exception('Course fee cannot be negative');
        }
        if (!empty($data['material_fee']) && $data['material_fee'] < 0) {
            throw new Exception('Material fee cannot be negative');
        }
    }

    private function validateTrainingEnrollmentData($data) {
        if (empty($data['training_id'])) {
            throw new Exception('Training ID is required');
        }
        if (empty($data['participant_id'])) {
            throw new Exception('Participant ID is required');
        }
        if (empty($data['participant_name'])) {
            throw new Exception('Participant name is required');
        }
        if (empty($data['enrollment_date'])) {
            throw new Exception('Enrollment date is required');
        }
        if (!empty($data['fee_paid']) && $data['fee_paid'] < 0) {
            throw new Exception('Fee paid cannot be negative');
        }
    }

    private function validateMentorshipData($data) {
        if (empty($data['mentor_id'])) {
            throw new Exception('Mentor ID is required');
        }
        if (empty($data['mentee_id'])) {
            throw new Exception('Mentee ID is required');
        }
        if (empty($data['start_date'])) {
            throw new Exception('Start date is required');
        }
        if ($data['mentor_id'] == $data['mentee_id']) {
            throw new Exception('Mentor and mentee cannot be the same person');
        }
    }

    private function validateGrantData($data) {
        if (empty($data['grant_name'])) {
            throw new Exception('Grant name is required');
        }
        if (!empty($data['minimum_grant_amount']) && !empty($data['maximum_grant_amount']) &&
            $data['minimum_grant_amount'] > $data['maximum_grant_amount']) {
            throw new Exception('Minimum grant amount cannot be greater than maximum grant amount');
        }
        if (!empty($data['matching_funds_percentage']) &&
            ($data['matching_funds_percentage'] < 0 || $data['matching_funds_percentage'] > 100)) {
            throw new Exception('Matching funds percentage must be between 0 and 100');
        }
    }

    private function validateGrantApplicationData($data) {
        if (empty($data['grant_id'])) {
            throw new Exception('Grant ID is required');
        }
        if (empty($data['applicant_id'])) {
            throw new Exception('Applicant ID is required');
        }
        if (empty($data['business_name'])) {
            throw new Exception('Business name is required');
        }
        if (empty($data['requested_amount']) || $data['requested_amount'] <= 0) {
            throw new Exception('Valid requested amount is required');
        }
        if (empty($data['application_date'])) {
            throw new Exception('Application date is required');
        }
    }

    private function validateEventData($data) {
        if (empty($data['event_name'])) {
            throw new Exception('Event name is required');
        }
        if (empty($data['event_date'])) {
            throw new Exception('Event date is required');
        }
        if (!empty($data['registration_fee']) && $data['registration_fee'] < 0) {
            throw new Exception('Registration fee cannot be negative');
        }
        if (!empty($data['max_attendees']) && $data['max_attendees'] <= 0) {
            throw new Exception('Maximum attendees must be greater than 0');
        }
    }

    private function validateEventRegistrationData($data) {
        if (empty($data['event_id'])) {
            throw new Exception('Event ID is required');
        }
        if (empty($data['attendee_id'])) {
            throw new Exception('Attendee ID is required');
        }
        if (empty($data['attendee_name'])) {
            throw new Exception('Attendee name is required');
        }
        if (empty($data['registration_date'])) {
            throw new Exception('Registration date is required');
        }
        if (!empty($data['registration_fee']) && $data['registration_fee'] < 0) {
            throw new Exception('Registration fee cannot be negative');
        }
    }

    // Code Generation Methods
    private function generateIncubatorCode() {
        return 'INC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateResidentCode() {
        return 'RES-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateMicrofinanceProgramCode() {
        return 'MFP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateMicrofinanceApplicationNumber() {
        return 'MFA-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateTrainingCode() {
        return 'TRN-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateEnrollmentCode() {
        return 'ENR-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateMentorshipCode() {
        return 'MEN-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateGrantCode() {
        return 'GRT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateGrantApplicationNumber() {
        return 'GRA-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateEventCode() {
        return 'EVT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateRegistrationCode() {
        return 'REG-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Reporting Methods
    public function getIncubatorStats($incubatorId = null) {
        try {
            $stats = [];

            // Overall incubator statistics
            $sql = "SELECT
                COUNT(*) as total_incubators,
                SUM(max_residents) as total_capacity,
                SUM(current_residents) as total_occupancy,
                AVG(monthly_fee) as avg_monthly_fee
                FROM business_incubators
                WHERE incubator_status = 'operational'";

            if ($incubatorId) {
                $sql .= " AND id = ?";
                $stats['incubator'] = $this->db->fetch($sql, [$incubatorId]);
            } else {
                $stats['overall'] = $this->db->fetch($sql);
            }

            // Resident statistics
            $sql = "SELECT
                COUNT(*) as total_residents,
                AVG(revenue_generated) as avg_revenue,
                SUM(employees_hired) as total_employees_hired,
                SUM(customers_acquired) as total_customers_acquired
                FROM incubator_residents
                WHERE program_status = 'active'";

            if ($incubatorId) {
                $sql .= " AND incubator_id = ?";
                $stats['residents'] = $this->db->fetch($sql, [$incubatorId]);
            } else {
                $stats['residents'] = $this->db->fetch($sql);
            }

            return ['success' => true, 'stats' => $stats];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getMicrofinanceStats($programId = null) {
        try {
            $stats = [];

            // Program statistics
            $sql = "SELECT
                COUNT(*) as total_programs,
                SUM(total_fund_available) as total_funds_available,
                SUM(funds_disbursed) as total_funds_disbursed,
                AVG(interest_rate) as avg_interest_rate
                FROM microfinance_programs
                WHERE program_status = 'active'";

            if ($programId) {
                $sql .= " AND id = ?";
                $stats['program'] = $this->db->fetch($sql, [$programId]);
            } else {
                $stats['overall'] = $this->db->fetch($sql);
            }

            // Application statistics
            $sql = "SELECT
                COUNT(*) as total_applications,
                COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as approved_applications,
                COUNT(CASE WHEN application_status = 'disbursed' THEN 1 END) as disbursed_loans,
                COUNT(CASE WHEN repayment_status = 'defaulted' THEN 1 END) as defaulted_loans,
                AVG(approved_amount) as avg_loan_amount,
                SUM(total_repaid) as total_repaid
                FROM microfinance_applications";

            if ($programId) {
                $sql .= " WHERE program_id = ?";
                $stats['applications'] = $this->db->fetch($sql, [$programId]);
            } else {
                $stats['applications'] = $this->db->fetch($sql);
            }

            return ['success' => true, 'stats' => $stats];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getTrainingStats($trainingId = null) {
        try {
            $stats = [];

            // Training program statistics
            $sql = "SELECT
                COUNT(*) as total_trainings,
                SUM(enrolled_participants) as total_enrolled,
                AVG(course_fee) as avg_course_fee,
                COUNT(CASE WHEN training_status = 'completed' THEN 1 END) as completed_trainings
                FROM entrepreneurship_training
                WHERE training_status IN ('open', 'in_progress', 'completed')";

            if ($trainingId) {
                $sql .= " AND id = ?";
                $stats['training'] = $this->db->fetch($sql, [$trainingId]);
            } else {
                $stats['overall'] = $this->db->fetch($sql);
            }

            // Enrollment statistics
            $sql = "SELECT
                COUNT(*) as total_enrollments,
                COUNT(CASE WHEN enrollment_status = 'completed' THEN 1 END) as completed_enrollments,
                COUNT(CASE WHEN certification_earned = 1 THEN 1 END) as certifications_earned,
                AVG(attendance_percentage) as avg_attendance,
                AVG(overall_rating) as avg_rating
                FROM training_enrollments";

            if ($trainingId) {
                $sql .= " WHERE training_id = ?";
                $stats['enrollments'] = $this->db->fetch($sql, [$trainingId]);
            } else {
                $stats['enrollments'] = $this->db->fetch($sql);
            }

            return ['success' => true, 'stats' => $stats];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getGrantStats($grantId = null) {
        try {
            $stats = [];

            // Grant program statistics
            $sql = "SELECT
                COUNT(*) as total_grants,
                SUM(total_funding_available) as total_funding_available,
                SUM(funds_disbursed) as total_funds_disbursed,
                AVG(maximum_grant_amount) as avg_max_grant_amount
                FROM business_grants
                WHERE grant_status IN ('open', 'awards_pending', 'completed')";

            if ($grantId) {
                $sql .= " AND id = ?";
                $stats['grant'] = $this->db->fetch($sql, [$grantId]);
            } else {
                $stats['overall'] = $this->db->fetch($sql);
            }

            // Application statistics
            $sql = "SELECT
                COUNT(*) as total_applications,
                COUNT(CASE WHEN application_status = 'awarded' THEN 1 END) as awarded_applications,
                AVG(awarded_amount) as avg_award_amount,
                SUM(awarded_amount) as total_awarded
                FROM grant_applications";

            if ($grantId) {
                $sql .= " WHERE grant_id = ?";
                $stats['applications'] = $this->db->fetch($sql, [$grantId]);
            } else {
                $stats['applications'] = $this->db->fetch($sql);
            }

            return ['success' => true, 'stats' => $stats];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getEventStats($eventId = null) {
        try {
            $stats = [];

            // Event statistics
            $sql = "SELECT
                COUNT(*) as total_events,
                SUM(registered_attendees) as total_registered,
                SUM(actual_attendees) as total_attended,
                AVG(registration_fee) as avg_registration_fee
                FROM business_networking_events
                WHERE event_status IN ('planned', 'open', 'confirmed', 'completed')";

            if ($eventId) {
                $sql .= " AND id = ?";
                $stats['event'] = $this->db->fetch($sql, [$eventId]);
            } else {
                $stats['overall'] = $this->db->fetch($sql);
            }

            // Registration statistics
            $sql = "SELECT
                COUNT(*) as total_registrations,
                COUNT(CASE WHEN registration_status = 'attended' THEN 1 END) as total_attended,
                SUM(registration_fee) as total_revenue,
                AVG(registration_fee) as avg_fee_paid
                FROM event_registrations";

            if ($eventId) {
                $sql .= " WHERE event_id = ?";
                $stats['registrations'] = $this->db->fetch($sql, [$eventId]);
            } else {
                $stats['registrations'] = $this->db->fetch($sql);
            }

            return ['success' => true, 'stats' => $stats];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Configuration loading
    private function loadConfig() {
        $configFile = __DIR__ . '/config/module_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [
            'default_incubator_duration' => 12,
            'default_loan_repayment_period' => 12,
            'default_grant_period' => 12,
            'max_file_upload_size' => 5242880, // 5MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_templates' => [
                'incubation_approved' => 'Your business incubation application has been approved.',
                'loan_approved' => 'Your microfinance loan application has been approved.',
                'training_enrolled' => 'You have been enrolled in the training program.',
                'grant_awarded' => 'Congratulations! Your grant application has been awarded.'
            ]
        ];
    }

    // Utility Methods
    public function getDashboardData() {
        try {
            $dashboard = [];

            // Quick stats
            $dashboard['incubators'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM business_incubators WHERE incubator_status = 'operational'"
            )['count'];

            $dashboard['active_residents'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM incubator_residents WHERE program_status = 'active'"
            )['count'];

            $dashboard['microfinance_programs'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM microfinance_programs WHERE program_status = 'active'"
            )['count'];

            $dashboard['active_loans'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM microfinance_applications WHERE application_status = 'disbursed'"
            )['count'];

            $dashboard['training_programs'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM entrepreneurship_training WHERE training_status IN ('open', 'in_progress')"
            )['count'];

            $dashboard['total_enrollments'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM training_enrollments WHERE enrollment_status = 'enrolled'"
            )['count'];

            $dashboard['active_grants'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM business_grants WHERE grant_status = 'open'"
            )['count'];

            $dashboard['upcoming_events'] = $this->db->fetch(
                "SELECT COUNT(*) as count FROM business_networking_events WHERE event_date >= CURDATE() AND event_status IN ('planned', 'open', 'confirmed')"
            )['count'];

            return ['success' => true, 'dashboard' => $dashboard];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function exportData($type, $format = 'csv') {
        try {
            $data = [];

            switch ($type) {
                case 'incubators':
                    $data = $this->db->fetchAll("SELECT * FROM business_incubators");
                    break;
                case 'residents':
                    $data = $this->db->fetchAll("SELECT * FROM incubator_residents");
                    break;
                case 'microfinance':
                    $data = $this->db->fetchAll("SELECT * FROM microfinance_applications");
                    break;
                case 'training':
                    $data = $this->db->fetchAll("SELECT * FROM training_enrollments");
                    break;
                case 'grants':
                    $data = $this->db->fetchAll("SELECT * FROM grant_applications");
                    break;
                case 'events':
                    $data = $this->db->fetchAll("SELECT * FROM event_registrations");
                    break;
                default:
                    throw new Exception('Invalid export type');
            }

            if ($format === 'csv') {
                return $this->exportToCSV($data, $type);
            } elseif ($format === 'json') {
                return $this->exportToJSON($data, $type);
            } else {
                throw new Exception('Unsupported export format');
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function exportToCSV($data, $type) {
        if (empty($data)) {
            return ['success' => false, 'error' => 'No data to export'];
        }

        $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/exports/' . $filename;

        if (!is_dir(__DIR__ . '/exports')) {
            mkdir(__DIR__ . '/exports', 0755, true);
        }

        $fp = fopen($filepath, 'w');

        // Write headers
        fputcsv($fp, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);

        return [
            'success' => true,
            'file' => $filename,
            'path' => $filepath,
            'records' => count($data)
        ];
    }

    private function exportToJSON($data, $type) {
        $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = __DIR__ . '/exports/' . $filename;

        if (!is_dir(__DIR__ . '/exports')) {
            mkdir(__DIR__ . '/exports', 0755, true);
        }

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return [
            'success' => true,
            'file' => $filename,
            'path' => $filepath,
            'records' => count($data)
        ];
    }
}

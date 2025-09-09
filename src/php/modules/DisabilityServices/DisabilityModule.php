<?php
/**
 * Disability Services Module
 * Handles disability benefits, accessibility services, and support programs
 */

require_once __DIR__ . '/../ServiceModule.php';

class DisabilityModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Disability Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive disability services including benefits, accessibility support, and rehabilitation programs',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'disability.register' => 'Register for disability services',
                'disability.benefits' => 'Apply for disability benefits',
                'disability.assessment' => 'Request disability assessment',
                'disability.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS disability_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                registration_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NOT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NOT NULL,
                nationality VARCHAR(50),

                -- Contact Information
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                alternate_phone VARCHAR(20),
                address TEXT NOT NULL,

                -- Disability Information
                primary_disability VARCHAR(100) NOT NULL,
                disability_type ENUM('physical', 'intellectual', 'psychiatric', 'sensory', 'neurological', 'developmental', 'chronic_illness', 'other') NOT NULL,
                disability_severity ENUM('mild', 'moderate', 'severe', 'profound') NOT NULL,
                date_of_onset DATE,
                cause_of_disability TEXT,

                -- Functional Limitations
                mobility_limitations TEXT,
                communication_limitations TEXT,
                self_care_limitations TEXT,
                learning_limitations TEXT,
                work_limitations TEXT,

                -- Assistive Devices
                uses_wheelchair BOOLEAN DEFAULT FALSE,
                uses_prosthetic BOOLEAN DEFAULT FALSE,
                uses_hearing_aid BOOLEAN DEFAULT FALSE,
                uses_visual_aid BOOLEAN DEFAULT FALSE,
                other_assistive_devices TEXT,

                -- Medical Information
                primary_physician VARCHAR(200),
                physician_phone VARCHAR(20),
                medical_conditions TEXT,
                current_medications TEXT,

                -- Support Services
                requires_home_care BOOLEAN DEFAULT FALSE,
                requires_transportation BOOLEAN DEFAULT FALSE,
                requires_interpreter BOOLEAN DEFAULT FALSE,
                requires_personal_assistant BOOLEAN DEFAULT FALSE,
                other_support_needs TEXT,

                -- Employment Information
                employment_status ENUM('employed', 'unemployed', 'retired', 'student', 'unable_to_work') DEFAULT 'unemployed',
                occupation VARCHAR(100),
                employer VARCHAR(200),

                -- Legal Information
                has_guardianship BOOLEAN DEFAULT FALSE,
                guardian_name VARCHAR(200),
                guardian_relationship VARCHAR(50),
                legal_representative VARCHAR(200),

                -- Registration Status
                registration_status ENUM('pending', 'under_review', 'approved', 'denied', 'inactive') DEFAULT 'pending',
                registration_date DATE NOT NULL,
                approval_date DATE NULL,
                denial_reason TEXT,

                -- Case Management
                case_worker_id INT NULL,
                case_worker_name VARCHAR(200),
                service_coordinator_id INT NULL,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_registration_number (registration_number),
                INDEX idx_user_id (user_id),
                INDEX idx_disability_type (disability_type),
                INDEX idx_registration_status (registration_status),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS disability_assessments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assessment_number VARCHAR(20) UNIQUE NOT NULL,
                registration_id INT NOT NULL,

                -- Assessment Details
                assessment_type ENUM('initial', 'functional', 'vocational', 'medical', 'psychological', 'annual_review') NOT NULL,
                assessment_date DATE NOT NULL,
                assessor_name VARCHAR(200) NOT NULL,
                assessor_credentials VARCHAR(200),

                -- Assessment Results
                functional_capacity TEXT,
                work_capacity TEXT,
                independence_level ENUM('complete', 'modified', 'extensive_support', 'total_support') NOT NULL,
                recommended_services TEXT,
                equipment_needs TEXT,

                -- Medical Assessment
                medical_findings TEXT,
                prognosis TEXT,
                treatment_recommendations TEXT,

                -- Recommendations
                vocational_rehabilitation BOOLEAN DEFAULT FALSE,
                supported_employment BOOLEAN DEFAULT FALSE,
                independent_living BOOLEAN DEFAULT FALSE,
                assistive_technology BOOLEAN DEFAULT FALSE,
                personal_assistance BOOLEAN DEFAULT FALSE,

                -- Disability Rating
                disability_rating_percentage INT CHECK (disability_rating_percentage >= 0 AND disability_rating_percentage <= 100),
                rating_effective_date DATE,
                rating_expiry_date DATE,

                -- Review Information
                next_review_date DATE,
                review_frequency ENUM('annual', 'biannual', 'triennial', 'as_needed') DEFAULT 'annual',

                -- Status
                assessment_status ENUM('completed', 'pending_review', 'approved', 'requires_additional_info') DEFAULT 'completed',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (registration_id) REFERENCES disability_registrations(id) ON DELETE CASCADE,
                INDEX idx_assessment_number (assessment_number),
                INDEX idx_registration_id (registration_id),
                INDEX idx_assessment_type (assessment_type),
                INDEX idx_assessment_date (assessment_date)
            );

            CREATE TABLE IF NOT EXISTS disability_benefits_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                registration_id INT NOT NULL,

                -- Application Details
                benefit_type ENUM('disability_benefit', 'supplemental_security_income', 'medicare', 'medicaid', 'vocational_rehab', 'housing_assistance', 'transportation_allowance', 'personal_care_allowance') NOT NULL,
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'denied', 'appeal_pending') DEFAULT 'draft',

                -- Financial Information
                monthly_income DECIMAL(10,2) DEFAULT 0,
                monthly_expenses DECIMAL(10,2) DEFAULT 0,
                assets_value DECIMAL(12,2) DEFAULT 0,
                dependents_count INT DEFAULT 0,

                -- Eligibility Information
                meets_medical_criteria BOOLEAN DEFAULT FALSE,
                meets_financial_criteria BOOLEAN DEFAULT FALSE,
                work_history TEXT,
                education_level VARCHAR(50),

                -- Benefit Details
                requested_benefit_amount DECIMAL(10,2),
                approved_benefit_amount DECIMAL(10,2) DEFAULT 0,
                benefit_start_date DATE NULL,
                benefit_end_date DATE NULL,

                -- Payment Information
                payment_method ENUM('direct_deposit', 'check', 'debit_card') DEFAULT 'direct_deposit',
                bank_account_number VARCHAR(50),
                routing_number VARCHAR(20),

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                denied_at TIMESTAMP NULL,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                -- Appeal Information
                appeal_filed BOOLEAN DEFAULT FALSE,
                appeal_date DATE NULL,
                appeal_result ENUM('pending', 'granted', 'denied', 'partially_granted') NULL,
                appeal_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (registration_id) REFERENCES disability_registrations(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_registration_id (registration_id),
                INDEX idx_benefit_type (benefit_type),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS disability_service_plans (
                id INT PRIMARY KEY AUTO_INCREMENT,
                plan_number VARCHAR(20) UNIQUE NOT NULL,
                registration_id INT NOT NULL,

                -- Plan Details
                plan_type ENUM('individualized_service_plan', 'vocational_plan', 'independent_living_plan', 'medical_management_plan') NOT NULL,
                plan_start_date DATE NOT NULL,
                plan_end_date DATE,
                plan_status ENUM('active', 'completed', 'discontinued', 'under_review') DEFAULT 'active',

                -- Goals and Objectives
                short_term_goals TEXT,
                long_term_goals TEXT,
                measurable_objectives TEXT,

                -- Services and Supports
                medical_services TEXT,
                rehabilitation_services TEXT,
                personal_assistance_services TEXT,
                transportation_services TEXT,
                housing_services TEXT,
                employment_services TEXT,
                educational_services TEXT,

                -- Service Providers
                primary_service_provider VARCHAR(200),
                secondary_providers TEXT,
                case_manager VARCHAR(200),

                -- Budget and Funding
                annual_budget DECIMAL(12,2),
                funding_source VARCHAR(100),
                cost_sharing BOOLEAN DEFAULT FALSE,
                client_contribution DECIMAL(8,2) DEFAULT 0,

                -- Progress Tracking
                progress_reviews TEXT,
                last_review_date DATE,
                next_review_date DATE,

                -- Review Information
                reviewed_by VARCHAR(200),
                review_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (registration_id) REFERENCES disability_registrations(id) ON DELETE CASCADE,
                INDEX idx_plan_number (plan_number),
                INDEX idx_registration_id (registration_id),
                INDEX idx_plan_type (plan_type),
                INDEX idx_plan_status (plan_status),
                INDEX idx_plan_start_date (plan_start_date)
            );

            CREATE TABLE IF NOT EXISTS disability_accessibility_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                request_number VARCHAR(20) UNIQUE NOT NULL,
                registration_id INT NOT NULL,

                -- Request Details
                request_type ENUM('home_modification', 'vehicle_modification', 'workplace_accommodation', 'education_accommodation', 'public_accessibility', 'assistive_technology') NOT NULL,
                request_date DATE NOT NULL,
                request_status ENUM('submitted', 'under_review', 'approved', 'denied', 'completed', 'cancelled') DEFAULT 'submitted',

                -- Request Description
                description TEXT NOT NULL,
                urgency_level ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
                estimated_cost DECIMAL(10,2),

                -- Location Information
                location_type ENUM('home', 'workplace', 'school', 'vehicle', 'public_space') NOT NULL,
                address TEXT,
                specific_location TEXT,

                -- Assessment Information
                accessibility_assessment_required BOOLEAN DEFAULT TRUE,
                assessment_date DATE NULL,
                assessor_name VARCHAR(200),

                -- Approval and Implementation
                approved_date DATE NULL,
                approved_amount DECIMAL(10,2) DEFAULT 0,
                implementation_start_date DATE NULL,
                implementation_end_date DATE NULL,
                contractor_name VARCHAR(200),

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,
                satisfaction_rating INT CHECK (satisfaction_rating >= 1 AND satisfaction_rating <= 5),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (registration_id) REFERENCES disability_registrations(id) ON DELETE CASCADE,
                INDEX idx_request_number (request_number),
                INDEX idx_registration_id (registration_id),
                INDEX idx_request_type (request_type),
                INDEX idx_request_status (request_status),
                INDEX idx_request_date (request_date)
            );

            CREATE TABLE IF NOT EXISTS disability_training_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('vocational_training', 'life_skills', 'computer_skills', 'communication_skills', 'mobility_training', 'adaptive_techniques') NOT NULL,
                target_disabilities TEXT,
                duration_weeks INT,
                session_frequency ENUM('daily', 'weekly', 'biweekly') DEFAULT 'weekly',

                -- Capacity and Requirements
                max_participants INT,
                minimum_age INT,
                maximum_age INT,
                prerequisite_skills TEXT,

                -- Resources
                required_facilities TEXT,
                required_equipment TEXT,
                instructor_requirements TEXT,

                -- Outcomes
                expected_outcomes TEXT,
                success_metrics TEXT,
                certification_provided BOOLEAN DEFAULT FALSE,

                -- Status
                program_status ENUM('active', 'inactive', 'pilot', 'discontinued') DEFAULT 'active',
                start_date DATE,
                end_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_program_status (program_status)
            );

            CREATE TABLE IF NOT EXISTS disability_support_providers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                provider_number VARCHAR(20) UNIQUE NOT NULL,
                provider_name VARCHAR(200) NOT NULL,
                provider_type ENUM('medical', 'rehabilitation', 'vocational', 'housing', 'legal', 'transportation', 'personal_assistance', 'other') NOT NULL,

                -- Contact Information
                contact_person VARCHAR(100),
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(255),
                website VARCHAR(255),
                address TEXT NOT NULL,

                -- Services Offered
                services_offered TEXT,
                specialties TEXT,
                service_areas TEXT,

                -- Credentials and Licensing
                license_number VARCHAR(50),
                license_expiry DATE,
                accreditations TEXT,
                insurance_coverage TEXT,

                -- Capacity and Availability
                current_capacity INT DEFAULT 0,
                maximum_capacity INT,
                waitlist_size INT DEFAULT 0,
                service_hours TEXT,

                -- Quality Metrics
                average_rating DECIMAL(3,2),
                total_clients_served INT DEFAULT 0,
                success_rate DECIMAL(5,2),

                -- Status
                provider_status ENUM('active', 'inactive', 'suspended', 'under_review') DEFAULT 'active',
                contract_start_date DATE,
                contract_end_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_provider_number (provider_number),
                INDEX idx_provider_type (provider_type),
                INDEX idx_provider_status (provider_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup disability registration workflow
        $registrationWorkflow = [
            'name' => 'Disability Registration Process',
            'description' => 'Complete workflow for disability registration and assessment',
            'steps' => [
                [
                    'name' => 'Initial Application',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'disability_registration_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Medical Assessment',
                    'type' => 'user_task',
                    'assignee' => 'medical_professional',
                    'form' => 'medical_assessment_form'
                ],
                [
                    'name' => 'Functional Assessment',
                    'type' => 'user_task',
                    'assignee' => 'assessment_specialist',
                    'form' => 'functional_assessment_form'
                ],
                [
                    'name' => 'Eligibility Determination',
                    'type' => 'service_task',
                    'service' => 'eligibility_determination_service'
                ],
                [
                    'name' => 'Service Plan Development',
                    'type' => 'user_task',
                    'assignee' => 'case_manager',
                    'form' => 'service_plan_form'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'disability_officer',
                    'form' => 'final_approval_form'
                ]
            ]
        ];

        // Setup benefits application workflow
        $benefitsWorkflow = [
            'name' => 'Disability Benefits Application Process',
            'description' => 'Complete workflow for disability benefits applications',
            'steps' => [
                [
                    'name' => 'Benefits Application',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'benefits_application_form'
                ],
                [
                    'name' => 'Financial Assessment',
                    'type' => 'service_task',
                    'service' => 'financial_assessment_service'
                ],
                [
                    'name' => 'Medical Review',
                    'type' => 'user_task',
                    'assignee' => 'medical_reviewer',
                    'form' => 'medical_review_form'
                ],
                [
                    'name' => 'Eligibility Review',
                    'type' => 'service_task',
                    'service' => 'eligibility_review_service'
                ],
                [
                    'name' => 'Benefit Calculation',
                    'type' => 'service_task',
                    'service' => 'benefit_calculation_service'
                ],
                [
                    'name' => 'Final Determination',
                    'type' => 'user_task',
                    'assignee' => 'benefits_officer',
                    'form' => 'final_determination_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/registration_workflow.json', json_encode($registrationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/benefits_workflow.json', json_encode($benefitsWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/medical_records',
            __DIR__ . '/uploads/assessments',
            __DIR__ . '/uploads/benefits_docs',
            __DIR__ . '/uploads/accessibility',
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
            'disability_support_providers',
            'disability_training_programs',
            'disability_accessibility_requests',
            'disability_service_plans',
            'disability_benefits_applications',
            'disability_assessments',
            'disability_registrations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerDisability($data) {
        try {
            $this->validateDisabilityRegistrationData($data);
            $registrationNumber = $this->generateRegistrationNumber();

            $sql = "INSERT INTO disability_registrations (
                registration_number, user_id, first_name, middle_name, last_name,
                date_of_birth, gender, nationality, email, phone, alternate_phone,
                address, primary_disability, disability_type, disability_severity,
                date_of_onset, cause_of_disability, mobility_limitations,
                communication_limitations, self_care_limitations, learning_limitations,
                work_limitations, uses_wheelchair, uses_prosthetic, uses_hearing_aid,
                uses_visual_aid, other_assistive_devices, primary_physician,
                physician_phone, medical_conditions, current_medications,
                requires_home_care, requires_transportation, requires_interpreter,
                requires_personal_assistant, other_support_needs, employment_status,
                occupation, employer, has_guardianship, guardian_name,
                guardian_relationship, legal_representative, registration_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $registrationNumber, $data['user_id'], $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['nationality'] ?? null, $data['email'],
                $data['phone'], $data['alternate_phone'] ?? null, json_encode($data['address']),
                $data['primary_disability'], $data['disability_type'], $data['disability_severity'],
                $data['date_of_onset'] ?? null, $data['cause_of_disability'] ?? null,
                $data['mobility_limitations'] ?? null, $data['communication_limitations'] ?? null,
                $data['self_care_limitations'] ?? null, $data['learning_limitations'] ?? null,
                $data['work_limitations'] ?? null, $data['uses_wheelchair'] ?? false,
                $data['uses_prosthetic'] ?? false, $data['uses_hearing_aid'] ?? false,
                $data['uses_visual_aid'] ?? false, $data['other_assistive_devices'] ?? null,
                $data['primary_physician'] ?? null, $data['physician_phone'] ?? null,
                $data['medical_conditions'] ?? null, $data['current_medications'] ?? null,
                $data['requires_home_care'] ?? false, $data['requires_transportation'] ?? false,
                $data['requires_interpreter'] ?? false, $data['requires_personal_assistant'] ?? false,
                $data['other_support_needs'] ?? null, $data['employment_status'] ?? 'unemployed',
                $data['occupation'] ?? null, $data['employer'] ?? null,
                $data['has_guardianship'] ?? false, $data['guardian_name'] ?? null,
                $data['guardian_relationship'] ?? null, $data['legal_representative'] ?? null,
                date('Y-m-d'), $data['created_by']
            ];

            $registrationId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'registration_id' => $registrationId,
                'registration_number' => $registrationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForBenefits($data) {
        try {
            $this->validateBenefitsApplicationData($data);
            $applicationNumber = $this->generateBenefitsApplicationNumber();

            $sql = "INSERT INTO disability_benefits_applications (
                application_number, registration_id, benefit_type, application_date,
                monthly_income, monthly_expenses, assets_value, dependents_count,
                meets_medical_criteria, meets_financial_criteria, work_history,
                education_level, requested_benefit_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['registration_id'], $data['benefit_type'],
                $data['application_date'], $data['monthly_income'] ?? 0,
                $data['monthly_expenses'] ?? 0, $data['assets_value'] ?? 0,
                $data['dependents_count'] ?? 0, $data['meets_medical_criteria'] ?? false,
                $data['meets_financial_criteria'] ?? false, $data['work_history'] ?? null,
                $data['education_level'] ?? null, $data['requested_benefit_amount'] ?? null,
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

    public function requestAccessibilitySupport($data) {
        try {
            $this->validateAccessibilityRequestData($data);
            $requestNumber = $this->generateAccessibilityRequestNumber();

            $sql = "INSERT INTO disability_accessibility_requests (
                request_number, registration_id, request_type, request_date,
                description, urgency_level, estimated_cost, location_type,
                address, specific_location, accessibility_assessment_required,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $requestId = $this->db->insert($sql, [
                $requestNumber, $data['registration_id'], $data['request_type'],
                $data['request_date'], $data['description'], $data['urgency_level'] ?? 'medium',
                $data['estimated_cost'] ?? null, $data['location_type'],
                json_encode($data['address'] ?? []), $data['specific_location'] ?? null,
                $data['accessibility_assessment_required'] ?? true, $data['created_by']
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'request_number' => $requestNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createServicePlan($data) {
        try {
            $this->validateServicePlanData($data);
            $planNumber = $this->generateServicePlanNumber();

            $sql = "INSERT INTO disability_service_plans (
                plan_number, registration_id, plan_type, plan_start_date,
                plan_end_date, short_term_goals, long_term_goals, measurable_objectives,
                medical_services, rehabilitation_services, personal_assistance_services,
                transportation_services, housing_services, employment_services,
                educational_services, primary_service_provider, case_manager,
                annual_budget, funding_source, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $planId = $this->db->insert($sql, [
                $planNumber, $data['registration_id'], $data['plan_type'],
                $data['plan_start_date'], $data['plan_end_date'] ?? null,
                $data['short_term_goals'] ?? null, $data['long_term_goals'] ?? null,
                $data['measurable_objectives'] ?? null, $data['medical_services'] ?? null,
                $data['rehabilitation_services'] ?? null, $data['personal_assistance_services'] ?? null,
                $data['transportation_services'] ?? null, $data['housing_services'] ?? null,
                $data['employment_services'] ?? null, $data['educational_services'] ?? null,
                $data['primary_service_provider'] ?? null, $data['case_manager'] ?? null,
                $data['annual_budget'] ?? null, $data['funding_source'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'plan_id' => $planId,
                'plan_number' => $planNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function scheduleAssessment($data) {
        try {
            $this->validateAssessmentData($data);
            $assessmentNumber = $this->generateAssessmentNumber();

            $sql = "INSERT INTO disability_assessments (
                assessment_number, registration_id, assessment_type, assessment_date,
                assessor_name, assessor_credentials, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $assessmentId = $this->db->insert($sql, [
                $assessmentNumber, $data['registration_id'], $data['assessment_type'],
                $data['assessment_date'], $data['assessor_name'], $data['assessor_credentials'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'assessment_id' => $assessmentId,
                'assessment_number' => $assessmentNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getRegistrationRecord($registrationNumber, $userId) {
        $sql = "SELECT * FROM disability_registrations WHERE registration_number = ?";
        $registration = $this->db->fetch($sql, [$registrationNumber]);

        if (!$registration) {
            return ['success' => false, 'error' => 'Registration not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToRegistration($registration, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $assessments = $this->getRegistrationAssessments($registration['id']);
        $benefitsApplications = $this->getRegistrationBenefitsApplications($registration['id']);
        $servicePlans = $this->getRegistrationServicePlans($registration['id']);
        $accessibilityRequests = $this->getRegistrationAccessibilityRequests($registration['id']);

        return [
            'success' => true,
            'registration' => $registration,
            'assessments' => $assessments,
            'benefits_applications' => $benefitsApplications,
            'service_plans' => $servicePlans,
            'accessibility_requests' => $accessibilityRequests
        ];
    }

    public function getSupportProviders($providerType = null) {
        $where = ["provider_status = 'active'"];
        $params = [];

        if ($providerType) {
            $where[] = "provider_type = ?";
            $params[] = $providerType;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM disability_support_providers WHERE $whereClause ORDER BY provider_name";

        $providers = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'providers' => $providers
        ];
    }

    public function getTrainingPrograms($programType = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($programType) {
            $where[] = "program_type = ?";
            $params[] = $programType;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM disability_training_programs WHERE $whereClause ORDER BY program_name";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    // Helper Methods
    private function validateDisabilityRegistrationData($data) {
        $required = [
            'user_id', 'first_name', 'last_name', 'date_of_birth',
            'gender', 'email', 'phone', 'address', 'primary_disability',
            'disability_type', 'disability_severity', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 0 || $age > 120) {
            throw new Exception('Invalid date of birth');
        }
    }

    private function validateBenefitsApplicationData($data) {
        $required = ['registration_id', 'benefit_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateAccessibilityRequestData($data) {
        $required = [
            'registration_id', 'request_type', 'request_date',
            'description', 'location_type', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateServicePlanData($data) {
        $required = [
            'registration_id', 'plan_type', 'plan_start_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateAssessmentData($data) {
        $required = [
            'registration_id', 'assessment_type', 'assessment_date',
            'assessor_name', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateRegistrationNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "DIS{$date}{$random}";
    }

    private function generateBenefitsApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BEN{$date}{$random}";
    }

    private function generateAccessibilityRequestNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ACC{$date}{$random}";
    }

    private function generateServicePlanNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PLAN{$date}{$random}";
    }

    private function generateAssessmentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ASS{$date}{$random}";
    }

    private function hasAccessToRegistration($registration, $userId) {
        // Check if user is the registrant, authorized representative, or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getRegistrationAssessments($registrationId) {
        $sql = "SELECT * FROM disability_assessments WHERE registration_id = ? ORDER BY assessment_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getRegistrationBenefitsApplications($registrationId) {
        $sql = "SELECT * FROM disability_benefits_applications WHERE registration_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getRegistrationServicePlans($registrationId) {
        $sql = "SELECT * FROM disability_service_plans WHERE registration_id = ? ORDER BY plan_start_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getRegistrationAccessibilityRequests($registrationId) {
        $sql = "SELECT * FROM disability_accessibility_requests WHERE registration_id = ? ORDER BY request_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

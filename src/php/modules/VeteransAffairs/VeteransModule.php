<?php
/**
 * Veterans Affairs Module
 * Handles military veteran support, benefits, and healthcare services
 */

require_once __DIR__ . '/../ServiceModule.php';

class VeteransModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Veterans Affairs Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive veterans affairs services including benefits, healthcare, and support programs',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'veterans.register' => 'Register as veteran',
                'veterans.benefits' => 'Apply for veterans benefits',
                'veterans.healthcare' => 'Access veterans healthcare',
                'veterans.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS veterans_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                veteran_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NOT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                nationality VARCHAR(50),

                -- Contact Information
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                alternate_phone VARCHAR(20),
                address TEXT NOT NULL,

                -- Military Service Information
                service_branch ENUM('army', 'navy', 'air_force', 'marines', 'coast_guard', 'space_force', 'national_guard') NOT NULL,
                service_component ENUM('active_duty', 'reserve', 'national_guard') NOT NULL,
                rank VARCHAR(50) NOT NULL,
                pay_grade VARCHAR(10),
                military_occupation_specialty VARCHAR(20),

                -- Service Dates
                enlistment_date DATE NOT NULL,
                discharge_date DATE,
                service_years DECIMAL(5,2),
                service_status ENUM('active_duty', 'retired', 'discharged', 'reserve', 'national_guard') DEFAULT 'discharged',

                -- Discharge Information
                discharge_type ENUM('honorable', 'general', 'other_than_honorable', 'dishonorable', 'medical') NOT NULL,
                dd214_number VARCHAR(50),
                separation_code VARCHAR(20),

                -- Combat and Deployment
                combat_veteran BOOLEAN DEFAULT FALSE,
                deployment_count INT DEFAULT 0,
                total_deployment_months INT DEFAULT 0,
                combat_zones TEXT,

                -- Disabilities and Injuries
                service_connected_disability BOOLEAN DEFAULT FALSE,
                disability_rating_percentage INT CHECK (disability_rating_percentage >= 0 AND disability_rating_percentage <= 100),
                primary_service_disability VARCHAR(200),
                secondary_disabilities TEXT,

                -- Benefits Eligibility
                eligible_for_va_healthcare BOOLEAN DEFAULT FALSE,
                eligible_for_gi_bill BOOLEAN DEFAULT FALSE,
                eligible_for_home_loan BOOLEAN DEFAULT FALSE,
                eligible_for_disability_compensation BOOLEAN DEFAULT FALSE,

                -- Emergency Contact
                emergency_contact_name VARCHAR(200),
                emergency_contact_relationship VARCHAR(50),
                emergency_contact_phone VARCHAR(20),

                -- Registration Status
                registration_status ENUM('pending', 'verified', 'active', 'inactive') DEFAULT 'pending',
                verification_date DATE NULL,
                case_worker_id INT NULL,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_veteran_number (veteran_number),
                INDEX idx_user_id (user_id),
                INDEX idx_service_branch (service_branch),
                INDEX idx_service_status (service_status),
                INDEX idx_registration_status (registration_status),
                INDEX idx_discharge_date (discharge_date)
            );

            CREATE TABLE IF NOT EXISTS veterans_benefits_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                veteran_id INT NOT NULL,

                -- Application Details
                benefit_type ENUM('disability_compensation', 'pension', 'education_gi_bill', 'vocational_rehab', 'home_loan', 'life_insurance', 'burial_benefits', 'dependency_indemnity', 'other') NOT NULL,
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'denied', 'appeal_pending') DEFAULT 'draft',

                -- Benefit Specific Information
                claimed_disability_rating INT CHECK (claimed_disability_rating >= 0 AND claimed_disability_rating <= 100),
                effective_date DATE,
                education_benefit_type ENUM('montgomery_gi_bill', 'post_911_gi_bill', 'voc_rehab') NULL,
                home_loan_amount DECIMAL(12,2),

                -- Financial Information
                monthly_income DECIMAL(10,2) DEFAULT 0,
                spouse_income DECIMAL(10,2) DEFAULT 0,
                dependents_count INT DEFAULT 0,

                -- Supporting Documentation
                dd214_attached BOOLEAN DEFAULT FALSE,
                medical_records_attached BOOLEAN DEFAULT FALSE,
                service_records_attached BOOLEAN DEFAULT FALSE,
                supporting_documents JSON,

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                denied_at TIMESTAMP NULL,

                -- Benefit Details
                approved_benefit_amount DECIMAL(10,2) DEFAULT 0,
                benefit_start_date DATE NULL,
                benefit_end_date DATE NULL,

                -- Payment Information
                payment_method ENUM('direct_deposit', 'check') DEFAULT 'direct_deposit',
                bank_account_number VARCHAR(50),
                routing_number VARCHAR(20),

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

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_benefit_type (benefit_type),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS veterans_healthcare_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                appointment_number VARCHAR(20) UNIQUE NOT NULL,
                veteran_id INT NOT NULL,

                -- Appointment Details
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                duration_minutes INT DEFAULT 30,
                facility_name VARCHAR(200) NOT NULL,
                facility_address TEXT,
                provider_name VARCHAR(200) NOT NULL,
                provider_specialty VARCHAR(100),

                -- Appointment Type
                appointment_type ENUM('primary_care', 'specialty_care', 'mental_health', 'dental', 'vision', 'physical_therapy', 'occupational_therapy', 'prosthetics', 'other') NOT NULL,
                service_connected BOOLEAN DEFAULT FALSE,

                -- Purpose and Notes
                appointment_purpose TEXT,
                chief_complaint TEXT,
                medical_notes TEXT,

                -- Status
                status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                check_in_time TIMESTAMP NULL,
                check_out_time TIMESTAMP NULL,

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,
                follow_up_notes TEXT,

                -- Billing
                copay_amount DECIMAL(8,2) DEFAULT 0,
                billing_status ENUM('pending', 'paid', 'covered_by_va', 'waived') DEFAULT 'pending',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_appointment_number (appointment_number),
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_appointment_type (appointment_type),
                INDEX idx_status (status),
                INDEX idx_appointment_date (appointment_date)
            );

            CREATE TABLE IF NOT EXISTS veterans_service_records (
                id INT PRIMARY KEY AUTO_INCREMENT,
                veteran_id INT NOT NULL,
                record_type ENUM('dd214', 'service_medical', 'performance_report', 'award_citation', 'deployment_record', 'training_record', 'other') NOT NULL,

                -- Record Details
                record_title VARCHAR(200) NOT NULL,
                record_date DATE NOT NULL,
                record_number VARCHAR(50),
                issuing_authority VARCHAR(100),

                -- Content
                record_description TEXT,
                key_details TEXT,

                -- File Information
                file_path VARCHAR(500),
                file_size INT,
                mime_type VARCHAR(100),

                -- Verification
                verified BOOLEAN DEFAULT FALSE,
                verification_date DATE NULL,
                verifier_name VARCHAR(100),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_record_type (record_type),
                INDEX idx_record_date (record_date),
                INDEX idx_verified (verified)
            );

            CREATE TABLE IF NOT EXISTS veterans_counseling_sessions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                session_number VARCHAR(20) UNIQUE NOT NULL,
                veteran_id INT NOT NULL,
                counselor_id INT NOT NULL,

                -- Session Details
                session_date DATE NOT NULL,
                session_time TIME NOT NULL,
                duration_minutes INT DEFAULT 60,
                session_type ENUM('individual', 'group', 'family', 'ptsd', 'substance_abuse', 'grief', 'other') DEFAULT 'individual',
                session_format ENUM('in_person', 'video', 'phone') DEFAULT 'in_person',

                -- Session Content
                session_objectives TEXT,
                topics_discussed TEXT,
                progress_made TEXT,
                homework_assigned TEXT,

                -- Assessment
                ptsd_symptoms TEXT,
                depression_level INT CHECK (depression_level >= 1 AND depression_level <= 10),
                anxiety_level INT CHECK (anxiety_level >= 1 AND anxiety_level <= 10),
                risk_assessment TEXT,

                -- Follow-up
                next_session_date DATE NULL,
                recommendations TEXT,

                -- Status
                status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                session_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_session_number (session_number),
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_counselor_id (counselor_id),
                INDEX idx_session_date (session_date),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS veterans_housing_assistance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assistance_number VARCHAR(20) UNIQUE NOT NULL,
                veteran_id INT NOT NULL,

                -- Assistance Details
                assistance_type ENUM('home_loan_guarantee', 'adapted_housing_grant', 'special_housing_grant', 'temporary_housing', 'homeless_assistance', 'home_modification') NOT NULL,
                application_date DATE NOT NULL,
                assistance_status ENUM('applied', 'approved', 'denied', 'completed', 'cancelled') DEFAULT 'applied',

                -- Housing Information
                property_address TEXT,
                property_type ENUM('single_family', 'condo', 'townhouse', 'apartment', 'mobile_home', 'other'),
                purchase_price DECIMAL(12,2),
                loan_amount DECIMAL(12,2),

                -- Special Needs
                mobility_impairments BOOLEAN DEFAULT FALSE,
                medical_equipment_needs TEXT,
                accessibility_requirements TEXT,

                -- Financial Information
                veteran_income DECIMAL(10,2),
                spouse_income DECIMAL(10,2),
                total_assets DECIMAL(12,2),

                -- Processing
                approved_date DATE NULL,
                approved_amount DECIMAL(10,2) DEFAULT 0,
                completion_date DATE NULL,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_assistance_number (assistance_number),
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_assistance_type (assistance_type),
                INDEX idx_assistance_status (assistance_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS veterans_education_benefits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                benefit_number VARCHAR(20) UNIQUE NOT NULL,
                veteran_id INT NOT NULL,

                -- Benefit Details
                benefit_type ENUM('montgomery_gi_bill', 'post_911_gi_bill', 'vocational_rehab', 'work_study', 'tutorial_assistance') NOT NULL,
                application_date DATE NOT NULL,
                benefit_status ENUM('applied', 'approved', 'active', 'exhausted', 'denied') DEFAULT 'applied',

                -- Education Information
                institution_name VARCHAR(200),
                institution_type ENUM('university', 'college', 'trade_school', 'online', 'other'),
                degree_program VARCHAR(200),
                enrollment_status ENUM('full_time', 'part_time', 'less_than_half') DEFAULT 'full_time',

                -- Benefit Usage
                entitlement_used DECIMAL(8,2) DEFAULT 0,
                entitlement_remaining DECIMAL(8,2),
                monthly_benefit_amount DECIMAL(8,2),

                -- Academic Information
                gpa DECIMAL(4,2),
                credits_completed INT DEFAULT 0,
                expected_graduation_date DATE,

                -- Processing
                approved_date DATE NULL,
                benefit_start_date DATE NULL,
                benefit_end_date DATE NULL,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_benefit_number (benefit_number),
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_benefit_type (benefit_type),
                INDEX idx_benefit_status (benefit_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS veterans_support_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_number VARCHAR(20) UNIQUE NOT NULL,
                veteran_id INT NOT NULL,
                service_type ENUM('employment_assistance', 'financial_counseling', 'legal_services', 'peer_support', 'family_support', 'transportation', 'other') NOT NULL,

                -- Service Details
                service_description TEXT,
                service_provider VARCHAR(200),
                contact_person VARCHAR(100),
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),

                -- Service Timeline
                requested_date DATE NOT NULL,
                approved_date DATE NULL,
                service_start_date DATE NULL,
                service_end_date DATE NULL,
                expected_duration_days INT,

                -- Status and Progress
                status ENUM('requested', 'approved', 'in_progress', 'completed', 'cancelled', 'denied') DEFAULT 'requested',
                progress_notes TEXT,
                outcome TEXT,

                -- Financial Aspects
                service_cost DECIMAL(10,2) DEFAULT 0,
                funding_source VARCHAR(100),

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,
                follow_up_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (veteran_id) REFERENCES veterans_registrations(id) ON DELETE CASCADE,
                INDEX idx_service_number (service_number),
                INDEX idx_veteran_id (veteran_id),
                INDEX idx_service_type (service_type),
                INDEX idx_status (status),
                INDEX idx_requested_date (requested_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup veterans registration workflow
        $registrationWorkflow = [
            'name' => 'Veterans Registration Process',
            'description' => 'Complete workflow for veterans registration and verification',
            'steps' => [
                [
                    'name' => 'Initial Application',
                    'type' => 'user_task',
                    'assignee' => 'veteran',
                    'form' => 'veterans_registration_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Service Verification',
                    'type' => 'service_task',
                    'service' => 'military_service_verification_service'
                ],
                [
                    'name' => 'Eligibility Determination',
                    'type' => 'service_task',
                    'service' => 'eligibility_determination_service'
                ],
                [
                    'name' => 'Registration Approval',
                    'type' => 'user_task',
                    'assignee' => 'veterans_officer',
                    'form' => 'registration_approval_form'
                ]
            ]
        ];

        // Setup benefits application workflow
        $benefitsWorkflow = [
            'name' => 'Veterans Benefits Application Process',
            'description' => 'Complete workflow for veterans benefits applications',
            'steps' => [
                [
                    'name' => 'Benefits Application',
                    'type' => 'user_task',
                    'assignee' => 'veteran',
                    'form' => 'benefits_application_form'
                ],
                [
                    'name' => 'Document Review',
                    'type' => 'service_task',
                    'service' => 'document_review_service'
                ],
                [
                    'name' => 'Eligibility Review',
                    'type' => 'service_task',
                    'service' => 'eligibility_review_service'
                ],
                [
                    'name' => 'Medical Assessment',
                    'type' => 'user_task',
                    'assignee' => 'medical_reviewer',
                    'form' => 'medical_assessment_form'
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
            __DIR__ . '/uploads/service_records',
            __DIR__ . '/uploads/medical_records',
            __DIR__ . '/uploads/benefits_docs',
            __DIR__ . '/uploads/housing_docs',
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
            'veterans_support_services',
            'veterans_education_benefits',
            'veterans_housing_assistance',
            'veterans_counseling_sessions',
            'veterans_service_records',
            'veterans_healthcare_appointments',
            'veterans_benefits_applications',
            'veterans_registrations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerVeteran($data) {
        try {
            $this->validateVeteranRegistrationData($data);
            $veteranNumber = $this->generateVeteranNumber();

            $sql = "INSERT INTO veterans_registrations (
                veteran_number, user_id, first_name, middle_name, last_name,
                date_of_birth, gender, nationality, email, phone, alternate_phone,
                address, service_branch, service_component, rank, pay_grade,
                military_occupation_specialty, enlistment_date, discharge_date,
                service_years, service_status, discharge_type, dd214_number,
                separation_code, combat_veteran, deployment_count, total_deployment_months,
                combat_zones, service_connected_disability, disability_rating_percentage,
                primary_service_disability, secondary_disabilities,
                eligible_for_va_healthcare, eligible_for_gi_bill, eligible_for_home_loan,
                eligible_for_disability_compensation, emergency_contact_name,
                emergency_contact_relationship, emergency_contact_phone,
                registration_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Calculate service years
            $enlistmentDate = new DateTime($data['enlistment_date']);
            $dischargeDate = isset($data['discharge_date']) ? new DateTime($data['discharge_date']) : new DateTime();
            $serviceYears = $dischargeDate->diff($enlistmentDate)->days / 365.25;

            $params = [
                $veteranNumber, $data['user_id'], $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['nationality'] ?? null, $data['email'],
                $data['phone'], $data['alternate_phone'] ?? null, json_encode($data['address']),
                $data['service_branch'], $data['service_component'], $data['rank'],
                $data['pay_grade'] ?? null, $data['military_occupation_specialty'] ?? null,
                $data['enlistment_date'], $data['discharge_date'] ?? null,
                round($serviceYears, 2), $data['service_status'] ?? 'discharged',
                $data['discharge_type'], $data['dd214_number'] ?? null,
                $data['separation_code'] ?? null, $data['combat_veteran'] ?? false,
                $data['deployment_count'] ?? 0, $data['total_deployment_months'] ?? 0,
                json_encode($data['combat_zones'] ?? []), $data['service_connected_disability'] ?? false,
                $data['disability_rating_percentage'] ?? null, $data['primary_service_disability'] ?? null,
                json_encode($data['secondary_disabilities'] ?? []),
                $data['eligible_for_va_healthcare'] ?? false, $data['eligible_for_gi_bill'] ?? false,
                $data['eligible_for_home_loan'] ?? false, $data['eligible_for_disability_compensation'] ?? false,
                $data['emergency_contact_name'] ?? null, $data['emergency_contact_relationship'] ?? null,
                $data['emergency_contact_phone'] ?? null, date('Y-m-d'), $data['created_by']
            ];

            $veteranId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'veteran_id' => $veteranId,
                'veteran_number' => $veteranNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForBenefits($data) {
        try {
            $this->validateBenefitsApplicationData($data);
            $applicationNumber = $this->generateBenefitsApplicationNumber();

            $sql = "INSERT INTO veterans_benefits_applications (
                application_number, veteran_id, benefit_type, application_date,
                claimed_disability_rating, effective_date, education_benefit_type,
                home_loan_amount, monthly_income, spouse_income, dependents_count,
                dd214_attached, medical_records_attached, service_records_attached,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['veteran_id'], $data['benefit_type'],
                $data['application_date'], $data['claimed_disability_rating'] ?? null,
                $data['effective_date'] ?? null, $data['education_benefit_type'] ?? null,
                $data['home_loan_amount'] ?? null, $data['monthly_income'] ?? 0,
                $data['spouse_income'] ?? 0, $data['dependents_count'] ?? 0,
                $data['dd214_attached'] ?? false, $data['medical_records_attached'] ?? false,
                $data['service_records_attached'] ?? false, $data['created_by']
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

    public function scheduleHealthcareAppointment($data) {
        try {
            $this->validateHealthcareAppointmentData($data);
            $appointmentNumber = $this->generateHealthcareAppointmentNumber();

            $sql = "INSERT INTO veterans_healthcare_appointments (
                appointment_number, veteran_id, appointment_date, appointment_time,
                duration_minutes, facility_name, facility_address, provider_name,
                provider_specialty, appointment_type, service_connected,
                appointment_purpose, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $appointmentId = $this->db->insert($sql, [
                $appointmentNumber, $data['veteran_id'], $data['appointment_date'],
                $data['appointment_time'], $data['duration_minutes'] ?? 30,
                $data['facility_name'], json_encode($data['facility_address'] ?? []),
                $data['provider_name'], $data['provider_specialty'] ?? null,
                $data['appointment_type'], $data['service_connected'] ?? false,
                $data['appointment_purpose'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'appointment_id' => $appointmentId,
                'appointment_number' => $appointmentNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function scheduleCounselingSession($data) {
        try {
            $this->validateCounselingSessionData($data);
            $sessionNumber = $this->generateCounselingSessionNumber();

            $sql = "INSERT INTO veterans_counseling_sessions (
                session_number, veteran_id, counselor_id, session_date,
                session_time, duration_minutes, session_type, session_format,
                session_objectives, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $sessionId = $this->db->insert($sql, [
                $sessionNumber, $data['veteran_id'], $data['counselor_id'],
                $data['session_date'], $data['session_time'], $data['duration_minutes'] ?? 60,
                $data['session_type'] ?? 'individual', $data['session_format'] ?? 'in_person',
                $data['session_objectives'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'session_id' => $sessionId,
                'session_number' => $sessionNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForHousingAssistance($data) {
        try {
            $this->validateHousingAssistanceData($data);
            $assistanceNumber = $this->generateHousingAssistanceNumber();

            $sql = "INSERT INTO veterans_housing_assistance (
                assistance_number, veteran_id, assistance_type, application_date,
                property_address, property_type, purchase_price, loan_amount,
                mobility_impairments, medical_equipment_needs, accessibility_requirements,
                veteran_income, spouse_income, total_assets, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $assistanceId = $this->db->insert($sql, [
                $assistanceNumber, $data['veteran_id'], $data['assistance_type'],
                $data['application_date'], json_encode($data['property_address'] ?? []),
                $data['property_type'] ?? null, $data['purchase_price'] ?? null,
                $data['loan_amount'] ?? null, $data['mobility_impairments'] ?? false,
                $data['medical_equipment_needs'] ?? null, $data['accessibility_requirements'] ?? null,
                $data['veteran_income'] ?? null, $data['spouse_income'] ?? null,
                $data['total_assets'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'assistance_id' => $assistanceId,
                'assistance_number' => $assistanceNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForEducationBenefits($data) {
        try {
            $this->validateEducationBenefitsData($data);
            $benefitNumber = $this->generateEducationBenefitNumber();

            $sql = "INSERT INTO veterans_education_benefits (
                benefit_number, veteran_id, benefit_type, application_date,
                institution_name, institution_type, degree_program, enrollment_status,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $benefitId = $this->db->insert($sql, [
                $benefitNumber, $data['veteran_id'], $data['benefit_type'],
                $data['application_date'], $data['institution_name'] ?? null,
                $data['institution_type'] ?? null, $data['degree_program'] ?? null,
                $data['enrollment_status'] ?? 'full_time', $data['created_by']
            ]);

            return [
                'success' => true,
                'benefit_id' => $benefitId,
                'benefit_number' => $benefitNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getVeteranRecord($veteranNumber, $userId) {
        $sql = "SELECT * FROM veterans_registrations WHERE veteran_number = ?";
        $veteran = $this->db->fetch($sql, [$veteranNumber]);

        if (!$veteran) {
            return ['success' => false, 'error' => 'Veteran record not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToVeteranRecord($veteran, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $benefitsApplications = $this->getVeteranBenefitsApplications($veteran['id']);
        $healthcareAppointments = $this->getVeteranHealthcareAppointments($veteran['id']);
        $counselingSessions = $this->getVeteranCounselingSessions($veteran['id']);
        $serviceRecords = $this->getVeteranServiceRecords($veteran['id']);
        $housingAssistance = $this->getVeteranHousingAssistance($veteran['id']);
        $educationBenefits = $this->getVeteranEducationBenefits($veteran['id']);
        $supportServices = $this->getVeteranSupportServices($veteran['id']);

        return [
            'success' => true,
            'veteran' => $veteran,
            'benefits_applications' => $benefitsApplications,
            'healthcare_appointments' => $healthcareAppointments,
            'counseling_sessions' => $counselingSessions,
            'service_records' => $serviceRecords,
            'housing_assistance' => $housingAssistance,
            'education_benefits' => $educationBenefits,
            'support_services' => $supportServices
        ];
    }

    public function uploadServiceRecord($veteranId, $recordType, $file, $recordData) {
        $this->validateDocument($file, $recordType);

        $fileName = $this->generateFileName($veteranId, $recordType, $file['name']);
        $filePath = __DIR__ . '/uploads/service_records/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $sql = "INSERT INTO veterans_service_records (
                veteran_id, record_type, record_title, record_date,
                record_number, issuing_authority, record_description,
                file_path, file_size, mime_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $recordId = $this->db->insert($sql, [
                $veteranId, $recordType, $recordData['record_title'],
                $recordData['record_date'], $recordData['record_number'] ?? null,
                $recordData['issuing_authority'] ?? null, $recordData['record_description'] ?? null,
                $filePath, $file['size'], $file['type']
            ]);

            return ['success' => true, 'record_id' => $recordId, 'file_path' => $filePath];
        }

        return ['success' => false, 'error' => 'File upload failed'];
    }

    // Helper Methods
    private function validateVeteranRegistrationData($data) {
        $required = [
            'user_id', 'first_name', 'last_name', 'date_of_birth',
            'gender', 'email', 'phone', 'address', 'service_branch',
            'service_component', 'rank', 'enlistment_date', 'discharge_type',
            'created_by'
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

        if ($age < 18 || $age > 120) {
            throw new Exception('Invalid date of birth');
        }

        // Validate service dates
        $enlistmentDate = new DateTime($data['enlistment_date']);
        if (isset($data['discharge_date'])) {
            $dischargeDate = new DateTime($data['discharge_date']);
            if ($dischargeDate < $enlistmentDate) {
                throw new Exception('Discharge date cannot be before enlistment date');
            }
        }
    }

    private function validateBenefitsApplicationData($data) {
        $required = ['veteran_id', 'benefit_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateHealthcareAppointmentData($data) {
        $required = [
            'veteran_id', 'appointment_date', 'appointment_time',
            'facility_name', 'provider_name', 'appointment_type', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate future date
        $appointmentDate = new DateTime($data['appointment_date'] . ' ' . $data['appointment_time']);
        $now = new DateTime();

        if ($appointmentDate <= $now) {
            throw new Exception('Appointment must be scheduled for a future date and time');
        }
    }

    private function validateCounselingSessionData($data) {
        $required = [
            'veteran_id', 'counselor_id', 'session_date', 'session_time', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate future date
        $sessionDate = new DateTime($data['session_date'] . ' ' . $data['session_time']);
        $now = new DateTime();

        if ($sessionDate <= $now) {
            throw new Exception('Session must be scheduled for a future date and time');
        }
    }

    private function validateHousingAssistanceData($data) {
        $required = ['veteran_id', 'assistance_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateEducationBenefitsData($data) {
        $required = ['veteran_id', 'benefit_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateVeteranNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "VET{$date}{$random}";
    }

    private function generateBenefitsApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BEN{$date}{$random}";
    }

    private function generateHealthcareAppointmentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "HCA{$date}{$random}";
    }

    private function generateCounselingSessionNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "SES{$date}{$random}";
    }

    private function generateHousingAssistanceNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "HOU{$date}{$random}";
    }

    private function generateEducationBenefitNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "EDU{$date}{$random}";
    }

    private function hasAccessToVeteranRecord($veteran, $userId) {
        // Check if user is the veteran, authorized representative, or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getVeteranBenefitsApplications($veteranId) {
        $sql = "SELECT * FROM veterans_benefits_applications WHERE veteran_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function getVeteranHealthcareAppointments($veteranId) {
        $sql = "SELECT * FROM veterans_healthcare_appointments WHERE veteran_id = ? ORDER BY appointment_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function getVeteranCounselingSessions($veteranId) {
        $sql = "SELECT * FROM veterans_counseling_sessions WHERE veteran_id = ? ORDER BY session_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function getVeteranServiceRecords($veteranId) {
        $sql = "SELECT * FROM veterans_service_records WHERE veteran_id = ? ORDER BY record_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function getVeteranHousingAssistance($veteranId) {
        $sql = "SELECT * FROM veterans_housing_assistance WHERE veteran_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function getVeteranEducationBenefits($veteranId) {
        $sql = "SELECT * FROM veterans_education_benefits WHERE veteran_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function getVeteranSupportServices($veteranId) {
        $sql = "SELECT * FROM veterans_support_services WHERE veteran_id = ? ORDER BY requested_date DESC";
        return $this->db->fetchAll($sql, [$veteranId]);
    }

    private function validateDocument($file, $recordType) {
        $allowedTypes = [
            'dd214' => ['application/pdf', 'image/jpeg', 'image/png'],
            'service_medical' => ['application/pdf', 'image/jpeg', 'image/png'],
            'performance_report' => ['application/pdf', 'image/jpeg', 'image/png'],
            'award_citation' => ['application/pdf', 'image/jpeg', 'image/png'],
            'deployment_record' => ['application/pdf', 'image/jpeg', 'image/png'],
            'training_record' => ['application/pdf', 'image/jpeg', 'image/png'],
            'other' => ['application/pdf', 'image/jpeg', 'image/png']
        ];

        if (!isset($allowedTypes[$recordType]) || !in_array($file['type'], $allowedTypes[$recordType])) {
            throw new Exception('Invalid file type for this record type');
        }

        if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception('File size exceeds maximum limit of 10MB');
        }
    }

    private function generateFileName($veteranId, $recordType, $originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $timestamp = date('Ymd_His');
        return "veteran_{$veteranId}_{$recordType}_{$timestamp}.{$extension}";
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

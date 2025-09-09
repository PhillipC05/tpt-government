<?php
/**
 * Senior Citizen Services Module
 * Handles elderly care, pension services, and senior support programs
 */

require_once __DIR__ . '/../ServiceModule.php';

class SeniorCitizenModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Senior Citizen Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive senior citizen services including pension management, elderly care, and support programs',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'senior.register' => 'Register as senior citizen',
                'senior.pension' => 'Apply for pension benefits',
                'senior.care' => 'Access elderly care services',
                'senior.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS senior_citizen_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                senior_number VARCHAR(20) UNIQUE NOT NULL,
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

                -- Emergency Contact
                emergency_contact_name VARCHAR(200) NOT NULL,
                emergency_contact_relationship VARCHAR(50) NOT NULL,
                emergency_contact_phone VARCHAR(20) NOT NULL,
                emergency_contact_address TEXT,

                -- Health Information
                medical_conditions TEXT,
                medications TEXT,
                allergies TEXT,
                mobility_status ENUM('independent', 'assisted', 'wheelchair', 'bedridden') DEFAULT 'independent',
                cognitive_status ENUM('normal', 'mild_impairment', 'moderate_impairment', 'severe_impairment') DEFAULT 'normal',

                -- Living Situation
                living_arrangement ENUM('alone', 'with_spouse', 'with_family', 'assisted_living', 'nursing_home', 'other') DEFAULT 'alone',
                household_size INT DEFAULT 1,
                caregiver_available BOOLEAN DEFAULT FALSE,
                caregiver_name VARCHAR(200),
                caregiver_relationship VARCHAR(50),

                -- Financial Information
                monthly_income DECIMAL(10,2) DEFAULT 0,
                monthly_expenses DECIMAL(10,2) DEFAULT 0,
                assets_value DECIMAL(12,2) DEFAULT 0,
                pension_eligible BOOLEAN DEFAULT FALSE,
                pension_type ENUM('old_age', 'disability', 'survivor', 'retirement') NULL,

                -- Social Services
                requires_home_care BOOLEAN DEFAULT FALSE,
                requires_transportation BOOLEAN DEFAULT FALSE,
                requires_meal_delivery BOOLEAN DEFAULT FALSE,
                requires_medical_transport BOOLEAN DEFAULT FALSE,
                social_isolation_risk ENUM('low', 'medium', 'high') DEFAULT 'low',

                -- Registration Status
                registration_status ENUM('pending', 'verified', 'active', 'inactive') DEFAULT 'pending',
                registration_date DATE NOT NULL,
                verification_date DATE NULL,
                case_worker_id INT NULL,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_senior_number (senior_number),
                INDEX idx_user_id (user_id),
                INDEX idx_date_of_birth (date_of_birth),
                INDEX idx_registration_status (registration_status),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS senior_pension_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,

                -- Application Details
                pension_type ENUM('old_age', 'disability', 'survivor', 'retirement') NOT NULL,
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'denied', 'appeal_pending') DEFAULT 'draft',

                -- Eligibility Information
                age_at_application INT,
                work_history_years INT DEFAULT 0,
                contribution_years INT DEFAULT 0,
                retirement_date DATE NULL,

                -- Financial Information
                monthly_pension_amount DECIMAL(8,2) DEFAULT 0,
                annual_pension_amount DECIMAL(10,2) DEFAULT 0,
                effective_date DATE NULL,
                payment_frequency ENUM('monthly', 'quarterly', 'annually') DEFAULT 'monthly',

                -- Supporting Documentation
                birth_certificate_attached BOOLEAN DEFAULT FALSE,
                work_history_attached BOOLEAN DEFAULT FALSE,
                medical_records_attached BOOLEAN DEFAULT FALSE,
                supporting_documents JSON,

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                denied_at TIMESTAMP NULL,

                -- Payment Information
                payment_method ENUM('direct_deposit', 'check', 'debit_card') DEFAULT 'direct_deposit',
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

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_pension_type (pension_type),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS senior_care_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,
                service_type ENUM('home_care', 'adult_day_care', 'respite_care', 'hospice_care', 'nursing_home', 'assisted_living', 'meal_delivery', 'transportation', 'housekeeping', 'personal_care', 'medical_alert', 'other') NOT NULL,

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

                -- Service Frequency and Cost
                service_frequency ENUM('daily', 'weekly', 'biweekly', 'monthly', 'as_needed') DEFAULT 'weekly',
                hours_per_visit DECIMAL(4,2) DEFAULT 1,
                visits_per_week INT DEFAULT 1,
                cost_per_hour DECIMAL(6,2),
                total_monthly_cost DECIMAL(8,2),

                -- Status and Progress
                status ENUM('requested', 'approved', 'in_progress', 'completed', 'cancelled', 'denied') DEFAULT 'requested',
                progress_notes TEXT,
                outcome TEXT,

                -- Quality Assessment
                satisfaction_rating INT CHECK (satisfaction_rating >= 1 AND satisfaction_rating <= 5),
                service_quality_rating INT CHECK (service_quality_rating >= 1 AND service_quality_rating <= 5),

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,
                follow_up_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_service_number (service_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_service_type (service_type),
                INDEX idx_status (status),
                INDEX idx_requested_date (requested_date)
            );

            CREATE TABLE IF NOT EXISTS senior_health_monitoring (
                id INT PRIMARY KEY AUTO_INCREMENT,
                monitoring_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,

                -- Health Assessment
                assessment_date DATE NOT NULL,
                assessor_name VARCHAR(200) NOT NULL,
                assessment_type ENUM('annual', 'quarterly', 'monthly', 'emergency', 'follow_up') DEFAULT 'annual',

                -- Vital Signs
                blood_pressure_systolic INT,
                blood_pressure_diastolic INT,
                heart_rate INT,
                temperature DECIMAL(4,1),
                weight_kg DECIMAL(5,2),
                height_cm INT,

                -- Health Metrics
                mobility_score INT CHECK (mobility_score >= 0 AND mobility_score <= 10),
                cognitive_score INT CHECK (cognitive_score >= 0 AND cognitive_score <= 30),
                depression_score INT CHECK (depression_score >= 0 AND depression_score <= 30),
                pain_level INT CHECK (pain_level >= 0 AND pain_level <= 10),

                -- Health Status
                overall_health_status ENUM('excellent', 'good', 'fair', 'poor', 'critical') DEFAULT 'good',
                health_concerns TEXT,
                medication_compliance ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',

                -- Recommendations
                medical_recommendations TEXT,
                lifestyle_recommendations TEXT,
                follow_up_required BOOLEAN DEFAULT FALSE,
                next_assessment_date DATE,

                -- Emergency Contacts
                emergency_called BOOLEAN DEFAULT FALSE,
                emergency_response TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_monitoring_number (monitoring_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_assessment_date (assessment_date),
                INDEX idx_assessment_type (assessment_type)
            );

            CREATE TABLE IF NOT EXISTS senior_social_activities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                activity_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,

                -- Activity Details
                activity_type ENUM('day_center', 'senior_club', 'exercise_class', 'arts_crafts', 'educational_class', 'social_outing', 'volunteer_work', 'religious_activity', 'other') NOT NULL,
                activity_name VARCHAR(200) NOT NULL,
                activity_description TEXT,

                -- Schedule Information
                start_date DATE NOT NULL,
                end_date DATE NULL,
                frequency ENUM('daily', 'weekly', 'biweekly', 'monthly', 'one_time') DEFAULT 'weekly',
                day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
                start_time TIME,
                duration_hours DECIMAL(4,2),

                -- Location and Provider
                location_name VARCHAR(200),
                location_address TEXT,
                activity_provider VARCHAR(200),
                contact_phone VARCHAR(20),

                -- Participation Status
                status ENUM('registered', 'active', 'completed', 'withdrawn', 'waitlisted') DEFAULT 'registered',
                registration_date DATE NOT NULL,
                last_attendance_date DATE NULL,

                -- Progress and Feedback
                attendance_rate DECIMAL(5,2),
                participation_level ENUM('high', 'medium', 'low') DEFAULT 'medium',
                feedback_notes TEXT,
                benefits_observed TEXT,

                -- Cost and Funding
                activity_cost DECIMAL(6,2) DEFAULT 0,
                subsidized BOOLEAN DEFAULT FALSE,
                subsidy_amount DECIMAL(6,2) DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_activity_number (activity_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_activity_type (activity_type),
                INDEX idx_status (status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS senior_emergency_alerts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                alert_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,

                -- Alert Details
                alert_date DATE NOT NULL,
                alert_time TIME NOT NULL,
                alert_type ENUM('medical_emergency', 'fall', 'medication_miss', 'no_activity', 'equipment_malfunction', 'other') NOT NULL,

                -- Alert Source
                alert_source ENUM('medical_alert_system', 'caregiver', 'family_member', 'neighbor', 'self', 'automatic_monitoring') DEFAULT 'automatic_monitoring',
                device_id VARCHAR(50),

                -- Response Information
                response_time_minutes INT,
                responder_name VARCHAR(200),
                responder_type ENUM('ambulance', 'police', 'fire_department', 'family', 'caregiver', 'medical_staff') DEFAULT 'ambulance',

                -- Incident Details
                incident_description TEXT,
                injury_sustained BOOLEAN DEFAULT FALSE,
                injury_description TEXT,
                hospitalization_required BOOLEAN DEFAULT FALSE,

                -- Resolution
                resolution_status ENUM('resolved', 'ongoing', 'transferred', 'false_alarm') DEFAULT 'resolved',
                resolution_notes TEXT,
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_actions TEXT,

                -- Emergency Contacts Notified
                emergency_contacts_notified JSON,
                notification_method ENUM('phone', 'text', 'email', 'app_notification') DEFAULT 'phone',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_alert_number (alert_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_alert_type (alert_type),
                INDEX idx_alert_date (alert_date),
                INDEX idx_resolution_status (resolution_status)
            );

            CREATE TABLE IF NOT EXISTS senior_financial_assistance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assistance_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,

                -- Assistance Details
                assistance_type ENUM('utility_assistance', 'food_assistance', 'medical_bill_assistance', 'housing_assistance', 'transportation_assistance', 'prescription_assistance', 'other') NOT NULL,
                application_date DATE NOT NULL,
                assistance_status ENUM('applied', 'approved', 'denied', 'completed', 'cancelled') DEFAULT 'applied',

                -- Financial Information
                monthly_income DECIMAL(10,2) NOT NULL,
                monthly_expenses DECIMAL(10,2) NOT NULL,
                requested_amount DECIMAL(8,2) NOT NULL,
                approved_amount DECIMAL(8,2) DEFAULT 0,

                -- Eligibility Criteria
                meets_income_guidelines BOOLEAN DEFAULT FALSE,
                meets_asset_guidelines BOOLEAN DEFAULT FALSE,
                special_circumstances TEXT,

                -- Processing
                approved_date DATE NULL,
                assistance_start_date DATE NULL,
                assistance_end_date DATE NULL,

                -- Payment Information
                payment_method ENUM('direct_deposit', 'check', 'voucher', 'debit_card') DEFAULT 'check',
                payment_schedule ENUM('one_time', 'monthly', 'quarterly') DEFAULT 'one_time',

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                -- Follow-up
                renewal_required BOOLEAN DEFAULT FALSE,
                renewal_date DATE NULL,
                outcome_assessment TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_assistance_number (assistance_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_assistance_type (assistance_type),
                INDEX idx_assistance_status (assistance_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS senior_legal_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_number VARCHAR(20) UNIQUE NOT NULL,
                senior_id INT NOT NULL,

                -- Legal Service Details
                service_type ENUM('estate_planning', 'power_of_attorney', 'guardianship', 'elder_law', 'consumer_protection', 'housing_law', 'medical_decision_making', 'other') NOT NULL,
                service_description TEXT,
                legal_issue TEXT,

                -- Legal Professional
                attorney_name VARCHAR(200),
                attorney_firm VARCHAR(200),
                attorney_phone VARCHAR(20),
                attorney_email VARCHAR(255),
                bar_number VARCHAR(50),

                -- Case Information
                case_number VARCHAR(50),
                court_name VARCHAR(200),
                case_status ENUM('consultation', 'preparing', 'filed', 'in_progress', 'resolved', 'dismissed') DEFAULT 'consultation',

                -- Services Provided
                consultation_provided BOOLEAN DEFAULT FALSE,
                document_preparation BOOLEAN DEFAULT FALSE,
                representation_provided BOOLEAN DEFAULT FALSE,
                mediation_services BOOLEAN DEFAULT FALSE,

                -- Financial Aspects
                legal_fees DECIMAL(10,2) DEFAULT 0,
                fees_paid_by_senior DECIMAL(10,2) DEFAULT 0,
                fees_covered_by_program DECIMAL(10,2) DEFAULT 0,
                pro_bono BOOLEAN DEFAULT FALSE,

                -- Outcome
                case_outcome TEXT,
                senior_satisfaction_rating INT CHECK (senior_satisfaction_rating >= 1 AND senior_satisfaction_rating <= 5),

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,
                follow_up_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (senior_id) REFERENCES senior_citizen_registrations(id) ON DELETE CASCADE,
                INDEX idx_service_number (service_number),
                INDEX idx_senior_id (senior_id),
                INDEX idx_service_type (service_type),
                INDEX idx_case_status (case_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup senior citizen registration workflow
        $registrationWorkflow = [
            'name' => 'Senior Citizen Registration Process',
            'description' => 'Complete workflow for senior citizen registration and verification',
            'steps' => [
                [
                    'name' => 'Initial Application',
                    'type' => 'user_task',
                    'assignee' => 'senior_applicant',
                    'form' => 'senior_registration_form'
                ],
                [
                    'name' => 'Age Verification',
                    'type' => 'service_task',
                    'service' => 'age_verification_service'
                ],
                [
                    'name' => 'Eligibility Assessment',
                    'type' => 'service_task',
                    'service' => 'eligibility_assessment_service'
                ],
                [
                    'name' => 'Health Assessment',
                    'type' => 'user_task',
                    'assignee' => 'health_assessor',
                    'form' => 'health_assessment_form'
                ],
                [
                    'name' => 'Registration Approval',
                    'type' => 'user_task',
                    'assignee' => 'senior_services_officer',
                    'form' => 'registration_approval_form'
                ]
            ]
        ];

        // Setup pension application workflow
        $pensionWorkflow = [
            'name' => 'Senior Pension Application Process',
            'description' => 'Complete workflow for senior pension applications',
            'steps' => [
                [
                    'name' => 'Pension Application',
                    'type' => 'user_task',
                    'assignee' => 'senior_applicant',
                    'form' => 'pension_application_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Eligibility Review',
                    'type' => 'service_task',
                    'service' => 'eligibility_review_service'
                ],
                [
                    'name' => 'Financial Assessment',
                    'type' => 'service_task',
                    'service' => 'financial_assessment_service'
                ],
                [
                    'name' => 'Final Determination',
                    'type' => 'user_task',
                    'assignee' => 'pension_officer',
                    'form' => 'pension_determination_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/registration_workflow.json', json_encode($registrationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/pension_workflow.json', json_encode($pensionWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/medical_records',
            __DIR__ . '/uploads/financial_docs',
            __DIR__ . '/uploads/legal_docs',
            __DIR__ . '/uploads/health_assessments',
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
            'senior_legal_services',
            'senior_financial_assistance',
            'senior_emergency_alerts',
            'senior_social_activities',
            'senior_health_monitoring',
            'senior_care_services',
            'senior_pension_applications',
            'senior_citizen_registrations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerSeniorCitizen($data) {
        try {
            $this->validateSeniorRegistrationData($data);
            $seniorNumber = $this->generateSeniorNumber();

            $sql = "INSERT INTO senior_citizen_registrations (
                senior_number, user_id, first_name, middle_name, last_name,
                date_of_birth, gender, nationality, email, phone, alternate_phone,
                address, emergency_contact_name, emergency_contact_relationship,
                emergency_contact_phone, emergency_contact_address, medical_conditions,
                medications, allergies, mobility_status, cognitive_status,
                living_arrangement, household_size, caregiver_available,
                caregiver_name, caregiver_relationship, monthly_income,
                monthly_expenses, assets_value, pension_eligible, pension_type,
                requires_home_care, requires_transportation, requires_meal_delivery,
                requires_medical_transport, social_isolation_risk, registration_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $seniorNumber, $data['user_id'], $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['nationality'] ?? null, $data['email'],
                $data['phone'], $data['alternate_phone'] ?? null, json_encode($data['address']),
                $data['emergency_contact_name'], $data['emergency_contact_relationship'],
                $data['emergency_contact_phone'], json_encode($data['emergency_contact_address'] ?? []),
                $data['medical_conditions'] ?? null, $data['medications'] ?? null,
                $data['allergies'] ?? null, $data['mobility_status'] ?? 'independent',
                $data['cognitive_status'] ?? 'normal', $data['living_arrangement'] ?? 'alone',
                $data['household_size'] ?? 1, $data['caregiver_available'] ?? false,
                $data['caregiver_name'] ?? null, $data['caregiver_relationship'] ?? null,
                $data['monthly_income'] ?? 0, $data['monthly_expenses'] ?? 0,
                $data['assets_value'] ?? 0, $data['pension_eligible'] ?? false,
                $data['pension_type'] ?? null, $data['requires_home_care'] ?? false,
                $data['requires_transportation'] ?? false, $data['requires_meal_delivery'] ?? false,
                $data['requires_medical_transport'] ?? false, $data['social_isolation_risk'] ?? 'low',
                date('Y-m-d'), $data['created_by']
            ];

            $seniorId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'senior_id' => $seniorId,
                'senior_number' => $seniorNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForPension($data) {
        try {
            $this->validatePensionApplicationData($data);
            $applicationNumber = $this->generatePensionApplicationNumber();

            // Calculate age at application
            $sql = "SELECT date_of_birth FROM senior_citizen_registrations WHERE id = ?";
            $senior = $this->db->fetch($sql, [$data['senior_id']]);

            if (!$senior) {
                throw new Exception('Senior citizen record not found');
            }

            $dob = new DateTime($senior['date_of_birth']);
            $applicationDate = new DateTime($data['application_date']);
            $age = $applicationDate->diff($dob)->y;

            $sql = "INSERT INTO senior_pension_applications (
                application_number, senior_id, pension_type, application_date,
                age_at_application, work_history_years, contribution_years,
                retirement_date, submitted_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['senior_id'], $data['pension_type'],
                $data['application_date'], $age, $data['work_history_years'] ?? 0,
                $data['contribution_years'] ?? 0, $data['retirement_date'] ?? null,
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

    public function requestCareService($data) {
        try {
            $this->validateCareServiceData($data);
            $serviceNumber = $this->generateCareServiceNumber();

            $sql = "INSERT INTO senior_care_services (
                service_number, senior_id, service_type, service_description,
                service_provider, contact_person, contact_phone, contact_email,
                requested_date, service_frequency, hours_per_visit, visits_per_week,
                cost_per_hour, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $serviceId = $this->db->insert($sql, [
                $serviceNumber, $data['senior_id'], $data['service_type'],
                $data['service_description'] ?? null, $data['service_provider'] ?? null,
                $data['contact_person'] ?? null, $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null, $data['requested_date'],
                $data['service_frequency'] ?? 'weekly', $data['hours_per_visit'] ?? 1,
                $data['visits_per_week'] ?? 1, $data['cost_per_hour'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'service_id' => $serviceId,
                'service_number' => $serviceNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function scheduleHealthMonitoring($data) {
        try {
            $this->validateHealthMonitoringData($data);
            $monitoringNumber = $this->generateHealthMonitoringNumber();

            $sql = "INSERT INTO senior_health_monitoring (
                monitoring_number, senior_id, assessment_date, assessor_name,
                assessment_type, blood_pressure_systolic, blood_pressure_diastolic,
                heart_rate, temperature, weight_kg, height_cm, mobility_score,
                cognitive_score, depression_score, pain_level, overall_health_status,
                health_concerns, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $monitoringId = $this->db->insert($sql, [
                $monitoringNumber, $data['senior_id'], $data['assessment_date'],
                $data['assessor_name'], $data['assessment_type'] ?? 'annual',
                $data['blood_pressure_systolic'] ?? null, $data['blood_pressure_diastolic'] ?? null,
                $data['heart_rate'] ?? null, $data['temperature'] ?? null,
                $data['weight_kg'] ?? null, $data['height_cm'] ?? null,
                $data['mobility_score'] ?? null, $data['cognitive_score'] ?? null,
                $data['depression_score'] ?? null, $data['pain_level'] ?? null,
                $data['overall_health_status'] ?? 'good', $data['health_concerns'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'monitoring_id' => $monitoringId,
                'monitoring_number' => $monitoringNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerSocialActivity($data) {
        try {
            $this->validateSocialActivityData($data);
            $activityNumber = $this->generateSocialActivityNumber();

            $sql = "INSERT INTO senior_social_activities (
                activity_number, senior_id, activity_type, activity_name,
                activity_description, start_date, end_date, frequency,
                day_of_week, start_time, duration_hours, location_name,
                location_address, activity_provider, contact_phone,
                registration_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $activityId = $this->db->insert($sql, [
                $activityNumber, $data['senior_id'], $data['activity_type'],
                $data['activity_name'], $data['activity_description'] ?? null,
                $data['start_date'], $data['end_date'] ?? null, $data['frequency'] ?? 'weekly',
                $data['day_of_week'] ?? null, $data['start_time'] ?? null,
                $data['duration_hours'] ?? null, $data['location_name'] ?? null,
                json_encode($data['location_address'] ?? []), $data['activity_provider'] ?? null,
                $data['contact_phone'] ?? null, date('Y-m-d'), $data['created_by']
            ]);

            return [
                'success' => true,
                'activity_id' => $activityId,
                'activity_number' => $activityNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForFinancialAssistance($data) {
        try {
            $this->validateFinancialAssistanceData($data);
            $assistanceNumber = $this->generateFinancialAssistanceNumber();

            $sql = "INSERT INTO senior_financial_assistance (
                assistance_number, senior_id, assistance_type, application_date,
                monthly_income, monthly_expenses, requested_amount,
                meets_income_guidelines, meets_asset_guidelines, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $assistanceId = $this->db->insert($sql, [
                $assistanceNumber, $data['senior_id'], $data['assistance_type'],
                $data['application_date'], $data['monthly_income'], $data['monthly_expenses'],
                $data['requested_amount'], $data['meets_income_guidelines'] ?? false,
                $data['meets_asset_guidelines'] ?? false, $data['created_by']
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

    public function recordEmergencyAlert($data) {
        try {
            $this->validateEmergencyAlertData($data);
            $alertNumber = $this->generateEmergencyAlertNumber();

            $sql = "INSERT INTO senior_emergency_alerts (
                alert_number, senior_id, alert_date, alert_time, alert_type,
                alert_source, device_id, response_time_minutes, responder_name,
                responder_type, incident_description, injury_sustained,
                hospitalization_required, resolution_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $alertId = $this->db->insert($sql, [
                $alertNumber, $data['senior_id'], $data['alert_date'], $data['alert_time'],
                $data['alert_type'], $data['alert_source'] ?? 'automatic_monitoring',
                $data['device_id'] ?? null, $data['response_time_minutes'] ?? null,
                $data['responder_name'] ?? null, $data['responder_type'] ?? 'ambulance',
                $data['incident_description'] ?? null, $data['injury_sustained'] ?? false,
                $data['hospitalization_required'] ?? false, $data['resolution_status'] ?? 'resolved',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'alert_id' => $alertId,
                'alert_number' => $alertNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getSeniorRecord($seniorNumber, $userId) {
        $sql = "SELECT * FROM senior_citizen_registrations WHERE senior_number = ?";
        $senior = $this->db->fetch($sql, [$seniorNumber]);

        if (!$senior) {
            return ['success' => false, 'error' => 'Senior citizen record not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToSeniorRecord($senior, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $pensionApplications = $this->getSeniorPensionApplications($senior['id']);
        $careServices = $this->getSeniorCareServices($senior['id']);
        $healthMonitoring = $this->getSeniorHealthMonitoring($senior['id']);
        $socialActivities = $this->getSeniorSocialActivities($senior['id']);
        $emergencyAlerts = $this->getSeniorEmergencyAlerts($senior['id']);
        $financialAssistance = $this->getSeniorFinancialAssistance($senior['id']);
        $legalServices = $this->getSeniorLegalServices($senior['id']);

        return [
            'success' => true,
            'senior' => $senior,
            'pension_applications' => $pensionApplications,
            'care_services' => $careServices,
            'health_monitoring' => $healthMonitoring,
            'social_activities' => $socialActivities,
            'emergency_alerts' => $emergencyAlerts,
            'financial_assistance' => $financialAssistance,
            'legal_services' => $legalServices
        ];
    }

    // Helper Methods
    private function validateSeniorRegistrationData($data) {
        $required = [
            'user_id', 'first_name', 'last_name', 'date_of_birth',
            'gender', 'email', 'phone', 'address', 'emergency_contact_name',
            'emergency_contact_relationship', 'emergency_contact_phone', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age (must be 60+ for senior services)
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 60) {
            throw new Exception('Must be at least 60 years old for senior citizen services');
        }
    }

    private function validatePensionApplicationData($data) {
        $required = ['senior_id', 'pension_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCareServiceData($data) {
        $required = ['senior_id', 'service_type', 'requested_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateHealthMonitoringData($data) {
        $required = ['senior_id', 'assessment_date', 'assessor_name', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateSocialActivityData($data) {
        $required = ['senior_id', 'activity_type', 'activity_name', 'start_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateFinancialAssistanceData($data) {
        $required = ['senior_id', 'assistance_type', 'application_date', 'monthly_income', 'monthly_expenses', 'requested_amount', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateEmergencyAlertData($data) {
        $required = ['senior_id', 'alert_date', 'alert_time', 'alert_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateSeniorNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "SEN{$date}{$random}";
    }

    private function generatePensionApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PEN{$date}{$random}";
    }

    private function generateCareServiceNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CAR{$date}{$random}";
    }

    private function generateHealthMonitoringNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "MON{$date}{$random}";
    }

    private function generateSocialActivityNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ACT{$date}{$random}";
    }

    private function generateFinancialAssistanceNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "FIN{$date}{$random}";
    }

    private function generateEmergencyAlertNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ALT{$date}{$random}";
    }

    private function hasAccessToSeniorRecord($senior, $userId) {
        // Check if user is the senior citizen, authorized representative, or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getSeniorPensionApplications($seniorId) {
        $sql = "SELECT * FROM senior_pension_applications WHERE senior_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function getSeniorCareServices($seniorId) {
        $sql = "SELECT * FROM senior_care_services WHERE senior_id = ? ORDER BY requested_date DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function getSeniorHealthMonitoring($seniorId) {
        $sql = "SELECT * FROM senior_health_monitoring WHERE senior_id = ? ORDER BY assessment_date DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function getSeniorSocialActivities($seniorId) {
        $sql = "SELECT * FROM senior_social_activities WHERE senior_id = ? ORDER BY start_date DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function getSeniorEmergencyAlerts($seniorId) {
        $sql = "SELECT * FROM senior_emergency_alerts WHERE senior_id = ? ORDER BY alert_date DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function getSeniorFinancialAssistance($seniorId) {
        $sql = "SELECT * FROM senior_financial_assistance WHERE senior_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function getSeniorLegalServices($seniorId) {
        $sql = "SELECT * FROM senior_legal_services WHERE senior_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$seniorId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

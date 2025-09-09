<?php
/**
 * Victim Support Services Module
 * Handles crime victim assistance, compensation, and support services
 */

require_once __DIR__ . '/../ServiceModule.php';

class VictimSupportModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Victim Support Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive victim support services including compensation, counseling, and legal assistance',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'victim.register' => 'Register as crime victim',
                'victim.compensation' => 'Apply for victim compensation',
                'victim.support' => 'Access support services',
                'victim.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS crime_victims (
                id INT PRIMARY KEY AUTO_INCREMENT,
                victim_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NULL,

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

                -- Emergency Contact
                emergency_contact_name VARCHAR(200),
                emergency_contact_relationship VARCHAR(50),
                emergency_contact_phone VARCHAR(20),

                -- Incident Information
                incident_date DATE NOT NULL,
                incident_time TIME,
                incident_location VARCHAR(255) NOT NULL,
                police_report_number VARCHAR(50),
                police_station VARCHAR(100),
                investigating_officer VARCHAR(100),

                -- Crime Details
                crime_type ENUM('assault', 'sexual_assault', 'robbery', 'burglary', 'theft', 'fraud', 'domestic_violence', 'stalking', 'homicide', 'other') NOT NULL,
                crime_description TEXT NOT NULL,
                injuries_sustained TEXT,
                medical_treatment_received BOOLEAN DEFAULT FALSE,
                hospitalization_required BOOLEAN DEFAULT FALSE,

                -- Impact Assessment
                physical_impact TEXT,
                emotional_impact TEXT,
                financial_impact TEXT,
                property_damage BOOLEAN DEFAULT FALSE,
                property_damage_description TEXT,

                -- Support Services
                counseling_requested BOOLEAN DEFAULT FALSE,
                legal_assistance_requested BOOLEAN DEFAULT FALSE,
                emergency_housing_requested BOOLEAN DEFAULT FALSE,
                financial_assistance_requested BOOLEAN DEFAULT FALSE,

                -- Registration Status
                registration_status ENUM('pending', 'verified', 'active', 'closed') DEFAULT 'pending',
                verification_date DATE NULL,
                case_worker_id INT NULL,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_victim_number (victim_number),
                INDEX idx_user_id (user_id),
                INDEX idx_incident_date (incident_date),
                INDEX idx_crime_type (crime_type),
                INDEX idx_registration_status (registration_status)
            );

            CREATE TABLE IF NOT EXISTS victim_compensation_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                victim_id INT NOT NULL,

                -- Application Details
                application_date DATE NOT NULL,
                application_type ENUM('emergency', 'standard', 'supplemental') DEFAULT 'standard',
                compensation_category ENUM('medical', 'lost_wages', 'property', 'funeral', 'counseling', 'relocation', 'other') NOT NULL,

                -- Financial Information
                claimed_amount DECIMAL(10,2) NOT NULL,
                approved_amount DECIMAL(10,2) DEFAULT 0,
                payment_amount DECIMAL(10,2) DEFAULT 0,

                -- Supporting Documentation
                police_report_attached BOOLEAN DEFAULT FALSE,
                medical_records_attached BOOLEAN DEFAULT FALSE,
                financial_records_attached BOOLEAN DEFAULT FALSE,
                supporting_documents JSON,

                -- Processing
                status ENUM('draft', 'submitted', 'under_review', 'approved', 'denied', 'paid') DEFAULT 'draft',
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                paid_at TIMESTAMP NULL,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                -- Payment Information
                payment_method ENUM('check', 'direct_deposit', 'wire_transfer') DEFAULT 'check',
                payment_reference VARCHAR(100),

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (victim_id) REFERENCES crime_victims(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_victim_id (victim_id),
                INDEX idx_status (status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS victim_support_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_number VARCHAR(20) UNIQUE NOT NULL,
                victim_id INT NOT NULL,
                service_type ENUM('counseling', 'legal_aid', 'emergency_housing', 'financial_assistance', 'medical_assistance', 'transportation', 'childcare', 'other') NOT NULL,

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
                reimbursement_amount DECIMAL(10,2) DEFAULT 0,

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,
                follow_up_notes TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (victim_id) REFERENCES crime_victims(id) ON DELETE CASCADE,
                INDEX idx_service_number (service_number),
                INDEX idx_victim_id (victim_id),
                INDEX idx_service_type (service_type),
                INDEX idx_status (status),
                INDEX idx_requested_date (requested_date)
            );

            CREATE TABLE IF NOT EXISTS victim_counseling_sessions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                session_number VARCHAR(20) UNIQUE NOT NULL,
                victim_id INT NOT NULL,
                counselor_id INT NOT NULL,

                -- Session Details
                session_date DATE NOT NULL,
                session_time TIME NOT NULL,
                duration_minutes INT DEFAULT 60,
                session_type ENUM('individual', 'group', 'family', 'crisis') DEFAULT 'individual',
                session_format ENUM('in_person', 'phone', 'video', 'chat') DEFAULT 'in_person',

                -- Session Content
                session_objectives TEXT,
                interventions_used TEXT,
                progress_made TEXT,
                homework_assigned TEXT,

                -- Assessment
                victim_mood_rating INT CHECK (victim_mood_rating >= 1 AND victim_mood_rating <= 10),
                session_effectiveness_rating INT CHECK (session_effectiveness_rating >= 1 AND session_effectiveness_rating <= 5),
                risk_assessment TEXT,

                -- Follow-up
                next_session_date DATE NULL,
                recommendations TEXT,

                -- Status
                status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                session_notes TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (victim_id) REFERENCES crime_victims(id) ON DELETE CASCADE,
                INDEX idx_session_number (session_number),
                INDEX idx_victim_id (victim_id),
                INDEX idx_counselor_id (counselor_id),
                INDEX idx_session_date (session_date),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS victim_legal_assistance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assistance_number VARCHAR(20) UNIQUE NOT NULL,
                victim_id INT NOT NULL,

                -- Legal Assistance Details
                assistance_type ENUM('criminal_prosecution', 'civil_action', 'restraining_order', 'divorce_support', 'child_custody', 'other') NOT NULL,
                case_description TEXT,
                desired_outcome TEXT,

                -- Legal Professional
                attorney_name VARCHAR(200),
                attorney_firm VARCHAR(200),
                attorney_phone VARCHAR(20),
                attorney_email VARCHAR(255),
                bar_number VARCHAR(50),

                -- Case Information
                court_case_number VARCHAR(50),
                court_name VARCHAR(200),
                judge_name VARCHAR(100),
                case_status ENUM('preparing', 'filed', 'in_progress', 'resolved', 'dismissed') DEFAULT 'preparing',

                -- Services Provided
                consultation_provided BOOLEAN DEFAULT FALSE,
                representation_provided BOOLEAN DEFAULT FALSE,
                court_accompaniment BOOLEAN DEFAULT FALSE,
                document_preparation BOOLEAN DEFAULT FALSE,

                -- Financial Aspects
                legal_fees DECIMAL(10,2) DEFAULT 0,
                fees_paid_by_victim DECIMAL(10,2) DEFAULT 0,
                fees_covered_by_program DECIMAL(10,2) DEFAULT 0,

                -- Outcome
                case_outcome TEXT,
                victim_satisfaction_rating INT CHECK (victim_satisfaction_rating >= 1 AND victim_satisfaction_rating <= 5),

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (victim_id) REFERENCES crime_victims(id) ON DELETE CASCADE,
                INDEX idx_assistance_number (assistance_number),
                INDEX idx_victim_id (victim_id),
                INDEX idx_assistance_type (assistance_type),
                INDEX idx_case_status (case_status)
            );

            CREATE TABLE IF NOT EXISTS victim_emergency_housing (
                id INT PRIMARY KEY AUTO_INCREMENT,
                housing_number VARCHAR(20) UNIQUE NOT NULL,
                victim_id INT NOT NULL,

                -- Housing Request Details
                request_date DATE NOT NULL,
                urgency_level ENUM('immediate', 'within_24_hours', 'within_72_hours', 'within_week') DEFAULT 'within_72_hours',
                household_size INT DEFAULT 1,
                special_needs TEXT,

                -- Housing Details
                housing_type ENUM('shelter', 'transitional_housing', 'rental_assistance', 'hotel', 'family_friends') NOT NULL,
                facility_name VARCHAR(200),
                facility_address TEXT,
                facility_phone VARCHAR(20),

                -- Stay Information
                check_in_date DATE NULL,
                check_out_date DATE NULL,
                expected_duration_days INT,
                actual_duration_days INT,

                -- Services Provided
                meals_provided BOOLEAN DEFAULT FALSE,
                transportation_provided BOOLEAN DEFAULT FALSE,
                counseling_available BOOLEAN DEFAULT FALSE,
                security_measures TEXT,

                -- Status
                status ENUM('requested', 'approved', 'occupied', 'completed', 'cancelled') DEFAULT 'requested',
                approval_date DATE NULL,
                approved_by VARCHAR(100),

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT TRUE,
                follow_up_date DATE NULL,
                permanent_housing_secured BOOLEAN DEFAULT FALSE,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (victim_id) REFERENCES crime_victims(id) ON DELETE CASCADE,
                INDEX idx_housing_number (housing_number),
                INDEX idx_victim_id (victim_id),
                INDEX idx_status (status),
                INDEX idx_request_date (request_date),
                INDEX idx_urgency_level (urgency_level)
            );

            CREATE TABLE IF NOT EXISTS victim_compensation_funds (
                id INT PRIMARY KEY AUTO_INCREMENT,
                fund_code VARCHAR(20) UNIQUE NOT NULL,
                fund_name VARCHAR(200) NOT NULL,
                fund_description TEXT,

                -- Fund Details
                total_allocation DECIMAL(15,2) NOT NULL,
                current_balance DECIMAL(15,2) NOT NULL,
                fund_type ENUM('state', 'federal', 'private', 'mixed') DEFAULT 'state',

                -- Eligibility Criteria
                eligible_crimes TEXT,
                minimum_compensation DECIMAL(8,2) DEFAULT 0,
                maximum_compensation DECIMAL(8,2) DEFAULT 0,
                application_deadline_days INT DEFAULT 365,

                -- Status
                status ENUM('active', 'inactive', 'depleted', 'closed') DEFAULT 'active',
                start_date DATE NOT NULL,
                end_date DATE NULL,

                -- Administration
                administrator VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_fund_code (fund_code),
                INDEX idx_status (status),
                INDEX idx_fund_type (fund_type)
            );

            CREATE TABLE IF NOT EXISTS victim_support_hotlines (
                id INT PRIMARY KEY AUTO_INCREMENT,
                hotline_number VARCHAR(20) NOT NULL,
                hotline_name VARCHAR(100) NOT NULL,
                description TEXT,

                -- Contact Information
                phone_number VARCHAR(20) NOT NULL,
                alternate_phone VARCHAR(20),
                email VARCHAR(255),
                website VARCHAR(255),

                -- Services
                services_offered TEXT,
                languages_supported TEXT,
                specializations TEXT,
                availability_hours TEXT,

                -- Operational Details
                staffed_by VARCHAR(100),
                response_time_minutes INT DEFAULT 5,
                call_volume_daily INT,

                -- Quality Metrics
                average_response_time INT,
                satisfaction_rating DECIMAL(3,2),
                calls_handled_monthly INT,

                -- Status
                status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
                accreditation_status VARCHAR(100),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_hotline_number (hotline_number),
                INDEX idx_status (status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup victim registration workflow
        $registrationWorkflow = [
            'name' => 'Victim Registration Process',
            'description' => 'Complete workflow for crime victim registration and verification',
            'steps' => [
                [
                    'name' => 'Initial Registration',
                    'type' => 'user_task',
                    'assignee' => 'victim',
                    'form' => 'victim_registration_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Police Report Verification',
                    'type' => 'service_task',
                    'service' => 'police_report_verification_service'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'service_task',
                    'service' => 'risk_assessment_service'
                ],
                [
                    'name' => 'Case Worker Assignment',
                    'type' => 'service_task',
                    'service' => 'case_worker_assignment_service'
                ],
                [
                    'name' => 'Registration Approval',
                    'type' => 'user_task',
                    'assignee' => 'victim_support_officer',
                    'form' => 'registration_approval_form'
                ]
            ]
        ];

        // Setup compensation application workflow
        $compensationWorkflow = [
            'name' => 'Victim Compensation Process',
            'description' => 'Complete workflow for victim compensation applications',
            'steps' => [
                [
                    'name' => 'Compensation Application',
                    'type' => 'user_task',
                    'assignee' => 'victim',
                    'form' => 'compensation_application_form'
                ],
                [
                    'name' => 'Document Review',
                    'type' => 'service_task',
                    'service' => 'document_review_service'
                ],
                [
                    'name' => 'Eligibility Assessment',
                    'type' => 'service_task',
                    'service' => 'eligibility_assessment_service'
                ],
                [
                    'name' => 'Compensation Calculation',
                    'type' => 'service_task',
                    'service' => 'compensation_calculation_service'
                ],
                [
                    'name' => 'Final Review',
                    'type' => 'user_task',
                    'assignee' => 'compensation_officer',
                    'form' => 'compensation_review_form'
                ],
                [
                    'name' => 'Payment Processing',
                    'type' => 'service_task',
                    'service' => 'payment_processing_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/registration_workflow.json', json_encode($registrationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/compensation_workflow.json', json_encode($compensationWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/police_reports',
            __DIR__ . '/uploads/medical_records',
            __DIR__ . '/uploads/legal_documents',
            __DIR__ . '/uploads/compensation_docs',
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
            'victim_support_hotlines',
            'victim_compensation_funds',
            'victim_emergency_housing',
            'victim_legal_assistance',
            'victim_counseling_sessions',
            'victim_support_services',
            'victim_compensation_applications',
            'crime_victims'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerVictim($data) {
        try {
            $this->validateVictimRegistrationData($data);
            $victimNumber = $this->generateVictimNumber();

            $sql = "INSERT INTO crime_victims (
                victim_number, user_id, first_name, middle_name, last_name,
                date_of_birth, gender, nationality, email, phone, alternate_phone,
                address, emergency_contact_name, emergency_contact_relationship,
                emergency_contact_phone, incident_date, incident_time, incident_location,
                police_report_number, police_station, investigating_officer,
                crime_type, crime_description, injuries_sustained, medical_treatment_received,
                hospitalization_required, physical_impact, emotional_impact, financial_impact,
                property_damage, property_damage_description, counseling_requested,
                legal_assistance_requested, emergency_housing_requested, financial_assistance_requested,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $victimNumber, $data['user_id'] ?? null, $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['nationality'] ?? null, $data['email'],
                $data['phone'], $data['alternate_phone'] ?? null, json_encode($data['address']),
                $data['emergency_contact_name'] ?? null, $data['emergency_contact_relationship'] ?? null,
                $data['emergency_contact_phone'] ?? null, $data['incident_date'],
                $data['incident_time'] ?? null, $data['incident_location'],
                $data['police_report_number'] ?? null, $data['police_station'] ?? null,
                $data['investigating_officer'] ?? null, $data['crime_type'],
                $data['crime_description'], $data['injuries_sustained'] ?? null,
                $data['medical_treatment_received'] ?? false, $data['hospitalization_required'] ?? false,
                $data['physical_impact'] ?? null, $data['emotional_impact'] ?? null,
                $data['financial_impact'] ?? null, $data['property_damage'] ?? false,
                $data['property_damage_description'] ?? null, $data['counseling_requested'] ?? false,
                $data['legal_assistance_requested'] ?? false, $data['emergency_housing_requested'] ?? false,
                $data['financial_assistance_requested'] ?? false, $data['created_by']
            ];

            $victimId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'victim_id' => $victimId,
                'victim_number' => $victimNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitCompensationApplication($data) {
        try {
            $this->validateCompensationApplicationData($data);
            $applicationNumber = $this->generateCompensationApplicationNumber();

            $sql = "INSERT INTO victim_compensation_applications (
                application_number, victim_id, application_date, application_type,
                compensation_category, claimed_amount, police_report_attached,
                medical_records_attached, financial_records_attached, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['victim_id'], $data['application_date'],
                $data['application_type'] ?? 'standard', $data['compensation_category'],
                $data['claimed_amount'], $data['police_report_attached'] ?? false,
                $data['medical_records_attached'] ?? false, $data['financial_records_attached'] ?? false,
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

    public function requestSupportService($data) {
        try {
            $this->validateSupportServiceData($data);
            $serviceNumber = $this->generateSupportServiceNumber();

            $sql = "INSERT INTO victim_support_services (
                service_number, victim_id, service_type, service_description,
                service_provider, contact_person, contact_phone, contact_email,
                requested_date, expected_duration_days, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $serviceId = $this->db->insert($sql, [
                $serviceNumber, $data['victim_id'], $data['service_type'],
                $data['service_description'] ?? null, $data['service_provider'] ?? null,
                $data['contact_person'] ?? null, $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null, $data['requested_date'],
                $data['expected_duration_days'] ?? null, $data['created_by']
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

    public function scheduleCounselingSession($data) {
        try {
            $this->validateCounselingSessionData($data);
            $sessionNumber = $this->generateCounselingSessionNumber();

            $sql = "INSERT INTO victim_counseling_sessions (
                session_number, victim_id, counselor_id, session_date,
                session_time, duration_minutes, session_type, session_format,
                session_objectives, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $sessionId = $this->db->insert($sql, [
                $sessionNumber, $data['victim_id'], $data['counselor_id'],
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

    public function requestEmergencyHousing($data) {
        try {
            $this->validateEmergencyHousingData($data);
            $housingNumber = $this->generateEmergencyHousingNumber();

            $sql = "INSERT INTO victim_emergency_housing (
                housing_number, victim_id, request_date, urgency_level,
                household_size, special_needs, housing_type, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $housingId = $this->db->insert($sql, [
                $housingNumber, $data['victim_id'], $data['request_date'],
                $data['urgency_level'] ?? 'within_72_hours', $data['household_size'] ?? 1,
                $data['special_needs'] ?? null, $data['housing_type'], $data['created_by']
            ]);

            return [
                'success' => true,
                'housing_id' => $housingId,
                'housing_number' => $housingNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getVictimRecord($victimNumber, $userId) {
        $sql = "SELECT * FROM crime_victims WHERE victim_number = ?";
        $victim = $this->db->fetch($sql, [$victimNumber]);

        if (!$victim) {
            return ['success' => false, 'error' => 'Victim record not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToVictimRecord($victim, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $compensationApplications = $this->getVictimCompensationApplications($victim['id']);
        $supportServices = $this->getVictimSupportServices($victim['id']);
        $counselingSessions = $this->getVictimCounselingSessions($victim['id']);
        $legalAssistance = $this->getVictimLegalAssistance($victim['id']);
        $emergencyHousing = $this->getVictimEmergencyHousing($victim['id']);

        return [
            'success' => true,
            'victim' => $victim,
            'compensation_applications' => $compensationApplications,
            'support_services' => $supportServices,
            'counseling_sessions' => $counselingSessions,
            'legal_assistance' => $legalAssistance,
            'emergency_housing' => $emergencyHousing
        ];
    }

    public function getHotlineInformation() {
        $sql = "SELECT * FROM victim_support_hotlines WHERE status = 'active' ORDER BY hotline_name";
        $hotlines = $this->db->fetchAll($sql);

        return [
            'success' => true,
            'hotlines' => $hotlines
        ];
    }

    public function getCompensationFunds() {
        $sql = "SELECT * FROM victim_compensation_funds WHERE status = 'active' ORDER BY fund_name";
        $funds = $this->db->fetchAll($sql);

        return [
            'success' => true,
            'funds' => $funds
        ];
    }

    // Helper Methods
    private function validateVictimRegistrationData($data) {
        $required = [
            'first_name', 'last_name', 'date_of_birth', 'gender',
            'email', 'phone', 'address', 'incident_date', 'incident_location',
            'crime_type', 'crime_description', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate dates
        $incidentDate = new DateTime($data['incident_date']);
        $now = new DateTime();

        if ($incidentDate > $now) {
            throw new Exception('Incident date cannot be in the future');
        }

        // Validate age (must be 18+ for some services)
        if (isset($data['date_of_birth'])) {
            $dob = new DateTime($data['date_of_birth']);
            $age = $now->diff($dob)->y;

            if ($age < 0 || $age > 120) {
                throw new Exception('Invalid date of birth');
            }
        }
    }

    private function validateCompensationApplicationData($data) {
        $required = ['victim_id', 'application_date', 'compensation_category', 'claimed_amount', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        if ($data['claimed_amount'] <= 0) {
            throw new Exception('Claimed amount must be greater than zero');
        }
    }

    private function validateSupportServiceData($data) {
        $required = ['victim_id', 'service_type', 'requested_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCounselingSessionData($data) {
        $required = ['victim_id', 'counselor_id', 'session_date', 'session_time', 'created_by'];

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

    private function validateEmergencyHousingData($data) {
        $required = ['victim_id', 'request_date', 'housing_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateVictimNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "VIC{$date}{$random}";
    }

    private function generateCompensationApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "COMP{$date}{$random}";
    }

    private function generateSupportServiceNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "SUPP{$date}{$random}";
    }

    private function generateCounselingSessionNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "SESS{$date}{$random}";
    }

    private function generateEmergencyHousingNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "HOUS{$date}{$random}";
    }

    private function hasAccessToVictimRecord($victim, $userId) {
        // Check if user is the victim, authorized representative, or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getVictimCompensationApplications($victimId) {
        $sql = "SELECT * FROM victim_compensation_applications WHERE victim_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$victimId]);
    }

    private function getVictimSupportServices($victimId) {
        $sql = "SELECT * FROM victim_support_services WHERE victim_id = ? ORDER BY requested_date DESC";
        return $this->db->fetchAll($sql, [$victimId]);
    }

    private function getVictimCounselingSessions($victimId) {
        $sql = "SELECT * FROM victim_counseling_sessions WHERE victim_id = ? ORDER BY session_date DESC";
        return $this->db->fetchAll($sql, [$victimId]);
    }

    private function getVictimLegalAssistance($victimId) {
        $sql = "SELECT * FROM victim_legal_assistance WHERE victim_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$victimId]);
    }

    private function getVictimEmergencyHousing($victimId) {
        $sql = "SELECT * FROM victim_emergency_housing WHERE victim_id = ? ORDER BY request_date DESC";
        return $this->db->fetchAll($sql, [$victimId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

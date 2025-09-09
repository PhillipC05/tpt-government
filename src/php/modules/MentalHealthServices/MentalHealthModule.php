<?php
/**
 * Mental Health Services Module
 * Handles mental health counseling, crisis intervention, and psychiatric care
 */

require_once __DIR__ . '/../ServiceModule.php';

class MentalHealthModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Mental Health Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive mental health services including counseling, crisis intervention, and psychiatric care',
            'dependencies' => ['IdentityServices', 'HealthServices', 'PaymentGateway'],
            'permissions' => [
                'mental_health.book_appointment' => 'Book mental health appointments',
                'mental_health.crisis_support' => 'Access crisis support services',
                'mental_health.records' => 'Access mental health records',
                'mental_health.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS mental_health_clients (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                client_number VARCHAR(20) UNIQUE NOT NULL,
                registration_date DATE NOT NULL,

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
                emergency_contact_name VARCHAR(200),
                emergency_contact_phone VARCHAR(20),
                emergency_contact_relationship VARCHAR(50),

                -- Address
                address TEXT NOT NULL,

                -- Medical Information
                medical_conditions TEXT,
                current_medications TEXT,
                allergies TEXT,
                previous_mental_health_history TEXT,

                -- Service Preferences
                preferred_language VARCHAR(50) DEFAULT 'English',
                communication_preferences JSON,
                accessibility_needs TEXT,

                -- Consent and Privacy
                consent_to_treatment BOOLEAN DEFAULT FALSE,
                consent_date DATE NULL,
                privacy_preferences JSON,

                -- Status
                status ENUM('active', 'inactive', 'transferred', 'deceased') DEFAULT 'active',
                risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_user_id (user_id),
                INDEX idx_client_number (client_number),
                INDEX idx_status (status),
                INDEX idx_risk_level (risk_level),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS mental_health_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                client_id INT NOT NULL,
                appointment_number VARCHAR(20) UNIQUE NOT NULL,
                appointment_type ENUM('initial_assessment', 'therapy_session', 'crisis_intervention', 'medication_review', 'group_therapy', 'family_session', 'follow_up') NOT NULL,

                -- Appointment Details
                scheduled_date DATE NOT NULL,
                scheduled_time TIME NOT NULL,
                duration_minutes INT DEFAULT 60,
                location VARCHAR(255) NOT NULL,
                session_format ENUM('in_person', 'video', 'phone', 'chat') DEFAULT 'in_person',

                -- Provider Information
                provider_id INT NOT NULL,
                provider_name VARCHAR(200) NOT NULL,
                provider_specialty VARCHAR(100),

                -- Status and Outcome
                status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                actual_start_time TIMESTAMP NULL,
                actual_end_time TIMESTAMP NULL,
                session_notes TEXT,
                treatment_plan_updates TEXT,
                homework_assignments TEXT,

                -- Assessment
                client_mood_rating INT CHECK (client_mood_rating >= 1 AND client_mood_rating <= 10),
                progress_rating ENUM('worsening', 'no_change', 'improving', 'significant_improvement'),
                risk_assessment TEXT,

                -- Follow-up
                next_appointment_date DATE NULL,
                next_appointment_notes TEXT,

                -- Billing
                fee_amount DECIMAL(10,2) NOT NULL,
                payment_status ENUM('pending', 'paid', 'covered_by_insurance', 'waived') DEFAULT 'pending',
                insurance_claim_number VARCHAR(50),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (client_id) REFERENCES mental_health_clients(id) ON DELETE CASCADE,
                INDEX idx_client_id (client_id),
                INDEX idx_provider_id (provider_id),
                INDEX idx_appointment_type (appointment_type),
                INDEX idx_status (status),
                INDEX idx_scheduled_date (scheduled_date)
            );

            CREATE TABLE IF NOT EXISTS mental_health_crisis_interventions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                intervention_number VARCHAR(20) UNIQUE NOT NULL,
                client_id INT NULL,
                caller_name VARCHAR(200),
                caller_relationship VARCHAR(50),
                caller_phone VARCHAR(20) NOT NULL,

                -- Crisis Details
                crisis_type ENUM('suicidal_threat', 'self_harm', 'violent_behavior', 'severe_anxiety', 'psychotic_episode', 'substance_abuse', 'other') NOT NULL,
                severity_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                description TEXT NOT NULL,
                immediate_danger BOOLEAN DEFAULT FALSE,

                -- Location and Response
                location VARCHAR(255),
                response_required ENUM('immediate', 'within_hours', 'within_days', 'monitoring_only') DEFAULT 'immediate',
                response_team_assigned VARCHAR(100),

                -- Intervention Details
                intervention_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                intervention_end TIMESTAMP NULL,
                actions_taken TEXT,
                outcome TEXT,
                follow_up_required BOOLEAN DEFAULT TRUE,

                -- Resources Provided
                resources_provided JSON,
                referrals_made JSON,

                -- Legal Aspects
                police_involvement BOOLEAN DEFAULT FALSE,
                involuntary_commitment BOOLEAN DEFAULT FALSE,
                court_order_number VARCHAR(50),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (client_id) REFERENCES mental_health_clients(id) ON DELETE SET NULL,
                INDEX idx_intervention_number (intervention_number),
                INDEX idx_client_id (client_id),
                INDEX idx_crisis_type (crisis_type),
                INDEX idx_severity_level (severity_level),
                INDEX idx_response_required (response_required)
            );

            CREATE TABLE IF NOT EXISTS mental_health_treatment_plans (
                id INT PRIMARY KEY AUTO_INCREMENT,
                client_id INT NOT NULL,
                plan_number VARCHAR(20) UNIQUE NOT NULL,
                plan_type ENUM('individual', 'group', 'family', 'crisis') NOT NULL,

                -- Plan Details
                diagnosis_codes TEXT,
                presenting_problems TEXT,
                treatment_goals TEXT,
                interventions_planned TEXT,
                estimated_duration_weeks INT,

                -- Progress Tracking
                progress_notes TEXT,
                goals_achieved TEXT,
                challenges_encountered TEXT,

                -- Medication
                prescribed_medications JSON,
                medication_monitoring TEXT,

                -- Status
                status ENUM('active', 'completed', 'discontinued', 'transferred') DEFAULT 'active',
                start_date DATE NOT NULL,
                end_date DATE NULL,
                review_date DATE NULL,

                -- Provider Information
                primary_provider_id INT NOT NULL,
                primary_provider_name VARCHAR(200) NOT NULL,
                supervising_provider_id INT,
                supervising_provider_name VARCHAR(200),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (client_id) REFERENCES mental_health_clients(id) ON DELETE CASCADE,
                INDEX idx_client_id (client_id),
                INDEX idx_plan_number (plan_number),
                INDEX idx_plan_type (plan_type),
                INDEX idx_status (status),
                INDEX idx_primary_provider_id (primary_provider_id)
            );

            CREATE TABLE IF NOT EXISTS mental_health_providers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                provider_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NOT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,

                -- Professional Information
                license_number VARCHAR(50) UNIQUE NOT NULL,
                license_type ENUM('psychiatrist', 'psychologist', 'licensed_counselor', 'social_worker', 'therapist', 'crisis_counselor') NOT NULL,
                specialties TEXT,
                years_experience INT,

                -- Qualifications
                education_background TEXT,
                certifications TEXT,
                professional_memberships TEXT,

                -- Availability
                availability_schedule JSON,
                emergency_contact BOOLEAN DEFAULT FALSE,
                languages_spoken TEXT,

                -- Status
                status ENUM('active', 'inactive', 'suspended', 'retired') DEFAULT 'active',
                approval_date DATE,
                background_check_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_provider_number (provider_number),
                INDEX idx_user_id (user_id),
                INDEX idx_license_type (license_type),
                INDEX idx_status (status),
                INDEX idx_emergency_contact (emergency_contact)
            );

            CREATE TABLE IF NOT EXISTS mental_health_hotlines (
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

            CREATE TABLE IF NOT EXISTS mental_health_support_groups (
                id INT PRIMARY KEY AUTO_INCREMENT,
                group_number VARCHAR(20) UNIQUE NOT NULL,
                group_name VARCHAR(200) NOT NULL,
                description TEXT,

                -- Group Details
                group_type ENUM('support', 'therapy', 'educational', 'peer_support') NOT NULL,
                target_conditions TEXT,
                age_range VARCHAR(50),
                max_participants INT,

                -- Schedule
                meeting_schedule JSON,
                duration_minutes INT DEFAULT 90,
                location VARCHAR(255),
                virtual_link VARCHAR(500),

                -- Facilitator
                facilitator_id INT,
                facilitator_name VARCHAR(200),
                facilitator_credentials TEXT,

                -- Status and Capacity
                status ENUM('active', 'inactive', 'full', 'forming') DEFAULT 'active',
                current_participants INT DEFAULT 0,
                waitlist_count INT DEFAULT 0,

                -- Requirements
                participation_requirements TEXT,
                cost_per_session DECIMAL(8,2) DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_group_number (group_number),
                INDEX idx_group_type (group_type),
                INDEX idx_status (status),
                INDEX idx_facilitator_id (facilitator_id)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup mental health service workflow
        $serviceWorkflow = [
            'name' => 'Mental Health Service Process',
            'description' => 'Complete workflow for mental health service delivery',
            'steps' => [
                [
                    'name' => 'Initial Assessment',
                    'type' => 'user_task',
                    'assignee' => 'client',
                    'form' => 'mental_health_assessment_form'
                ],
                [
                    'name' => 'Provider Assignment',
                    'type' => 'service_task',
                    'service' => 'provider_assignment_service'
                ],
                [
                    'name' => 'Treatment Planning',
                    'type' => 'user_task',
                    'assignee' => 'mental_health_provider',
                    'form' => 'treatment_plan_form'
                ],
                [
                    'name' => 'Service Delivery',
                    'type' => 'user_task',
                    'assignee' => 'mental_health_provider',
                    'form' => 'service_delivery_form'
                ],
                [
                    'name' => 'Progress Monitoring',
                    'type' => 'service_task',
                    'service' => 'progress_monitoring_service'
                ],
                [
                    'name' => 'Treatment Review',
                    'type' => 'user_task',
                    'assignee' => 'mental_health_provider',
                    'form' => 'treatment_review_form'
                ]
            ]
        ];

        // Setup crisis intervention workflow
        $crisisWorkflow = [
            'name' => 'Crisis Intervention Process',
            'description' => 'Emergency response workflow for mental health crises',
            'steps' => [
                [
                    'name' => 'Crisis Assessment',
                    'type' => 'user_task',
                    'assignee' => 'crisis_responder',
                    'form' => 'crisis_assessment_form'
                ],
                [
                    'name' => 'Risk Evaluation',
                    'type' => 'service_task',
                    'service' => 'risk_evaluation_service'
                ],
                [
                    'name' => 'Intervention Planning',
                    'type' => 'user_task',
                    'assignee' => 'crisis_team',
                    'form' => 'intervention_plan_form'
                ],
                [
                    'name' => 'Immediate Response',
                    'type' => 'service_task',
                    'service' => 'immediate_response_service'
                ],
                [
                    'name' => 'Follow-up Care',
                    'type' => 'user_task',
                    'assignee' => 'care_coordinator',
                    'form' => 'follow_up_care_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/service_workflow.json', json_encode($serviceWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/crisis_workflow.json', json_encode($crisisWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/assessments',
            __DIR__ . '/uploads/treatment_plans',
            __DIR__ . '/uploads/session_notes',
            __DIR__ . '/uploads/crisis_reports',
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
            'mental_health_support_groups',
            'mental_health_hotlines',
            'mental_health_providers',
            'mental_health_treatment_plans',
            'mental_health_crisis_interventions',
            'mental_health_appointments',
            'mental_health_clients'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerClient($data) {
        try {
            $this->validateClientRegistrationData($data);
            $clientNumber = $this->generateClientNumber();

            $sql = "INSERT INTO mental_health_clients (
                user_id, client_number, registration_date,
                first_name, middle_name, last_name, date_of_birth, gender, nationality,
                email, phone, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                address, medical_conditions, current_medications, allergies,
                preferred_language, accessibility_needs, consent_to_treatment,
                created_by
            ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['user_id'], $clientNumber,
                $data['first_name'], $data['middle_name'] ?? null, $data['last_name'],
                $data['date_of_birth'], $data['gender'], $data['nationality'] ?? null,
                $data['email'], $data['phone'], $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null, $data['emergency_contact_relationship'] ?? null,
                json_encode($data['address']), $data['medical_conditions'] ?? null,
                $data['current_medications'] ?? null, $data['allergies'] ?? null,
                $data['preferred_language'] ?? 'English', $data['accessibility_needs'] ?? null,
                $data['consent_to_treatment'] ?? false, $data['user_id']
            ];

            $clientId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'client_id' => $clientId,
                'client_number' => $clientNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function bookAppointment($data) {
        try {
            $this->validateAppointmentData($data);
            $appointmentNumber = $this->generateAppointmentNumber();

            // Check provider availability
            if (!$this->isProviderAvailable($data['provider_id'], $data['scheduled_date'], $data['scheduled_time'])) {
                return ['success' => false, 'error' => 'Provider not available at this time'];
            }

            $sql = "INSERT INTO mental_health_appointments (
                client_id, appointment_number, appointment_type,
                scheduled_date, scheduled_time, duration_minutes, location, session_format,
                provider_id, provider_name, provider_specialty, fee_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $providerInfo = $this->getProviderInfo($data['provider_id']);

            $appointmentId = $this->db->insert($sql, [
                $data['client_id'], $appointmentNumber, $data['appointment_type'],
                $data['scheduled_date'], $data['scheduled_time'], $data['duration_minutes'] ?? 60,
                $data['location'], $data['session_format'] ?? 'in_person',
                $data['provider_id'], $providerInfo['name'], $providerInfo['specialty'] ?? null,
                $data['fee_amount'] ?? 0
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

    public function recordCrisisIntervention($data) {
        try {
            $this->validateCrisisData($data);
            $interventionNumber = $this->generateInterventionNumber();

            $sql = "INSERT INTO mental_health_crisis_interventions (
                intervention_number, client_id, caller_name, caller_relationship,
                caller_phone, crisis_type, severity_level, description, immediate_danger,
                location, response_required, response_team_assigned
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $interventionId = $this->db->insert($sql, [
                $interventionNumber, $data['client_id'] ?? null, $data['caller_name'] ?? null,
                $data['caller_relationship'] ?? null, $data['caller_phone'],
                $data['crisis_type'], $data['severity_level'], $data['description'],
                $data['immediate_danger'] ?? false, $data['location'] ?? null,
                $data['response_required'] ?? 'immediate', $data['response_team_assigned'] ?? null
            ]);

            // Trigger emergency response if critical
            if ($data['severity_level'] === 'critical' || $data['immediate_danger']) {
                $this->triggerEmergencyResponse($interventionId);
            }

            return [
                'success' => true,
                'intervention_id' => $interventionId,
                'intervention_number' => $interventionNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTreatmentPlan($data) {
        try {
            $this->validateTreatmentPlanData($data);
            $planNumber = $this->generatePlanNumber();

            $sql = "INSERT INTO mental_health_treatment_plans (
                client_id, plan_number, plan_type, diagnosis_codes, presenting_problems,
                treatment_goals, interventions_planned, estimated_duration_weeks,
                primary_provider_id, primary_provider_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $providerInfo = $this->getProviderInfo($data['primary_provider_id']);

            $planId = $this->db->insert($sql, [
                $data['client_id'], $planNumber, $data['plan_type'],
                json_encode($data['diagnosis_codes'] ?? []), $data['presenting_problems'],
                $data['treatment_goals'], $data['interventions_planned'],
                $data['estimated_duration_weeks'] ?? null, $data['primary_provider_id'],
                $providerInfo['name']
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

    public function getClientRecords($clientId, $userId) {
        // Verify access permissions
        if (!$this->hasAccessToClientRecords($clientId, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        $client = $this->getClientInfo($clientId);
        $appointments = $this->getClientAppointments($clientId);
        $treatmentPlans = $this->getClientTreatmentPlans($clientId);
        $crisisInterventions = $this->getClientCrisisInterventions($clientId);

        return [
            'success' => true,
            'client' => $client,
            'appointments' => $appointments,
            'treatment_plans' => $treatmentPlans,
            'crisis_interventions' => $crisisInterventions
        ];
    }

    public function findAvailableProviders($specialty = null, $date = null, $time = null) {
        $where = ["status = 'active'"];
        $params = [];

        if ($specialty) {
            $where[] = "FIND_IN_SET(?, specialties)";
            $params[] = $specialty;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT id, provider_number, first_name, last_name, license_type, specialties,
                       languages_spoken, emergency_contact
                FROM mental_health_providers WHERE $whereClause
                ORDER BY emergency_contact DESC, first_name";

        $providers = $this->db->fetchAll($sql, $params);

        // Filter by availability if date/time specified
        if ($date && $time) {
            $providers = array_filter($providers, function($provider) use ($date, $time) {
                return $this->isProviderAvailable($provider['id'], $date, $time);
            });
        }

        return [
            'success' => true,
            'providers' => array_values($providers)
        ];
    }

    public function getHotlineInformation() {
        $sql = "SELECT * FROM mental_health_hotlines WHERE status = 'active' ORDER BY hotline_name";
        $hotlines = $this->db->fetchAll($sql);

        return [
            'success' => true,
            'hotlines' => $hotlines
        ];
    }

    public function joinSupportGroup($clientId, $groupId) {
        // Check if group has capacity
        $group = $this->getSupportGroupInfo($groupId);
        if (!$group || $group['status'] !== 'active' || $group['current_participants'] >= $group['max_participants']) {
            return ['success' => false, 'error' => 'Group is full or inactive'];
        }

        // Add client to group (this would be a separate table in a real implementation)
        // For now, just update the count
        $sql = "UPDATE mental_health_support_groups SET current_participants = current_participants + 1 WHERE id = ?";
        $this->db->query($sql, [$groupId]);

        return [
            'success' => true,
            'message' => 'Successfully joined support group'
        ];
    }

    // Helper Methods
    private function validateClientRegistrationData($data) {
        $required = [
            'user_id', 'first_name', 'last_name', 'date_of_birth',
            'gender', 'email', 'phone', 'address', 'consent_to_treatment'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age (must be 13+ for mental health services)
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 13) {
            throw new Exception('Must be at least 13 years old for mental health services');
        }
    }

    private function validateAppointmentData($data) {
        $required = [
            'client_id', 'appointment_type', 'scheduled_date',
            'scheduled_time', 'location', 'provider_id'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate future date
        $appointmentDate = new DateTime($data['scheduled_date'] . ' ' . $data['scheduled_time']);
        $now = new DateTime();

        if ($appointmentDate <= $now) {
            throw new Exception('Appointment must be scheduled for a future date and time');
        }
    }

    private function validateCrisisData($data) {
        $required = ['caller_phone', 'crisis_type', 'severity_level', 'description'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateTreatmentPlanData($data) {
        $required = [
            'client_id', 'plan_type', 'presenting_problems',
            'treatment_goals', 'primary_provider_id'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateClientNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "MH{$date}{$random}";
    }

    private function generateAppointmentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "APPT{$date}{$random}";
    }

    private function generateInterventionNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CRISIS{$date}{$random}";
    }

    private function generatePlanNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PLAN{$date}{$random}";
    }

    private function isProviderAvailable($providerId, $date, $time) {
        // This would check the provider's availability schedule
        // For now, return true
        return true;
    }

    private function getProviderInfo($providerId) {
        $sql = "SELECT CONCAT(first_name, ' ', last_name) as name, specialties as specialty
                FROM mental_health_providers WHERE id = ?";
        return $this->db->fetch($sql, [$providerId]);
    }

    private function triggerEmergencyResponse($interventionId) {
        // This would trigger emergency services, notifications, etc.
        // For now, just log the emergency
        error_log("Emergency response triggered for intervention ID: $interventionId");
    }

    private function hasAccessToClientRecords($clientId, $userId) {
        // Check if user is the client, their authorized representative, or a provider
        $sql = "SELECT COUNT(*) as count FROM mental_health_clients
                WHERE id = ? AND (user_id = ? OR emergency_contact_phone = (
                    SELECT phone FROM users WHERE id = ?
                ))";
        $result = $this->db->fetch($sql, [$clientId, $userId, $userId]);
        return $result['count'] > 0;
    }

    private function getClientInfo($clientId) {
        $sql = "SELECT * FROM mental_health_clients WHERE id = ?";
        return $this->db->fetch($sql, [$clientId]);
    }

    private function getClientAppointments($clientId) {
        $sql = "SELECT * FROM mental_health_appointments WHERE client_id = ? ORDER BY scheduled_date DESC";
        return $this->db->fetchAll($sql, [$clientId]);
    }

    private function getClientTreatmentPlans($clientId) {
        $sql = "SELECT * FROM mental_health_treatment_plans WHERE client_id = ? ORDER BY start_date DESC";
        return $this->db->fetchAll($sql, [$clientId]);
    }

    private function getClientCrisisInterventions($clientId) {
        $sql = "SELECT * FROM mental_health_crisis_interventions WHERE client_id = ? ORDER BY intervention_start DESC";
        return $this->db->fetchAll($sql, [$clientId]);
    }

    private function getSupportGroupInfo($groupId) {
        $sql = "SELECT * FROM mental_health_support_groups WHERE id = ?";
        return $this->db->fetch($sql, [$groupId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

<?php
/**
 * Youth Services Module
 * Handles child welfare, youth programs, and juvenile services
 */

require_once __DIR__ . '/../ServiceModule.php';

class YouthModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Youth Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive youth services including child welfare, juvenile justice, and youth development programs',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'youth.register' => 'Register youth for services',
                'youth.welfare' => 'Access child welfare services',
                'youth.juvenile' => 'Access juvenile justice services',
                'youth.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS youth_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                youth_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NOT NULL,
                nationality VARCHAR(50),

                -- Contact Information
                email VARCHAR(255),
                phone VARCHAR(20),
                alternate_phone VARCHAR(20),
                address TEXT NOT NULL,

                -- Guardian/Legal Information
                guardian_name VARCHAR(200) NOT NULL,
                guardian_relationship ENUM('parent', 'grandparent', 'aunt', 'uncle', 'sibling', 'foster_parent', 'legal_guardian', 'other') NOT NULL,
                guardian_phone VARCHAR(20) NOT NULL,
                guardian_email VARCHAR(255),
                guardian_address TEXT,

                -- School Information
                school_name VARCHAR(200),
                school_grade VARCHAR(50),
                school_district VARCHAR(100),
                student_id VARCHAR(50),
                academic_status ENUM('enrolled', 'not_enrolled', 'home_schooled', 'graduated', 'dropped_out') DEFAULT 'enrolled',

                -- Health Information
                medical_conditions TEXT,
                medications TEXT,
                allergies TEXT,
                primary_physician VARCHAR(200),
                physician_phone VARCHAR(20),

                -- Family Information
                family_structure ENUM('two_parent', 'single_parent', 'extended_family', 'foster_care', 'group_home', 'other') DEFAULT 'two_parent',
                siblings_count INT DEFAULT 0,
                household_income DECIMAL(10,2),
                receives_public_assistance BOOLEAN DEFAULT FALSE,

                -- Risk Assessment
                risk_level ENUM('low', 'moderate', 'high', 'critical') DEFAULT 'low',
                abuse_concern BOOLEAN DEFAULT FALSE,
                neglect_concern BOOLEAN DEFAULT FALSE,
                behavioral_concerns TEXT,
                substance_abuse_risk BOOLEAN DEFAULT FALSE,

                -- Service Needs
                requires_educational_support BOOLEAN DEFAULT FALSE,
                requires_mental_health_services BOOLEAN DEFAULT FALSE,
                requires_family_support BOOLEAN DEFAULT FALSE,
                requires_after_school_programs BOOLEAN DEFAULT FALSE,
                requires_mentoring BOOLEAN DEFAULT FALSE,

                -- Legal Status
                legal_status ENUM('no_legal_issues', 'child_protective_services', 'juvenile_court', 'probation', 'foster_care', 'adoption_pending', 'emancipation') DEFAULT 'no_legal_issues',
                case_worker_id INT NULL,
                case_worker_name VARCHAR(200),

                -- Registration Status
                registration_status ENUM('pending', 'active', 'inactive', 'graduated', 'transferred') DEFAULT 'pending',
                registration_date DATE NOT NULL,
                last_contact_date DATE,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_youth_number (youth_number),
                INDEX idx_user_id (user_id),
                INDEX idx_date_of_birth (date_of_birth),
                INDEX idx_guardian_name (guardian_name),
                INDEX idx_registration_status (registration_status),
                INDEX idx_risk_level (risk_level),
                INDEX idx_legal_status (legal_status)
            );

            CREATE TABLE IF NOT EXISTS youth_service_plans (
                id INT PRIMARY KEY AUTO_INCREMENT,
                plan_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Plan Details
                plan_type ENUM('individual_service_plan', 'treatment_plan', 'education_plan', 'family_support_plan', 'transition_plan') NOT NULL,
                plan_start_date DATE NOT NULL,
                plan_end_date DATE,
                plan_status ENUM('active', 'completed', 'discontinued', 'under_review') DEFAULT 'active',

                -- Goals and Objectives
                short_term_goals TEXT,
                long_term_goals TEXT,
                measurable_objectives TEXT,
                success_criteria TEXT,

                -- Services and Interventions
                educational_services TEXT,
                mental_health_services TEXT,
                family_counseling TEXT,
                behavioral_interventions TEXT,
                skill_building_programs TEXT,

                -- Service Providers
                primary_case_worker VARCHAR(200),
                secondary_providers TEXT,
                school_liaison VARCHAR(200),
                mental_health_provider VARCHAR(200),

                -- Progress Tracking
                progress_reviews TEXT,
                last_review_date DATE,
                next_review_date DATE,
                progress_rating ENUM('excellent', 'good', 'satisfactory', 'needs_improvement', 'poor') DEFAULT 'satisfactory',

                -- Review Information
                reviewed_by VARCHAR(200),
                review_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_plan_number (plan_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_plan_type (plan_type),
                INDEX idx_plan_status (plan_status),
                INDEX idx_plan_start_date (plan_start_date)
            );

            CREATE TABLE IF NOT EXISTS youth_program_participation (
                id INT PRIMARY KEY AUTO_INCREMENT,
                participation_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Program Details
                program_type ENUM('after_school', 'summer_camp', 'mentoring', 'sports_recreation', 'arts_culture', 'educational_enrichment', 'leadership_development', 'community_service', 'other') NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Provider
                program_provider VARCHAR(200),
                contact_person VARCHAR(100),
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),

                -- Participation Details
                enrollment_date DATE NOT NULL,
                start_date DATE,
                end_date DATE,
                participation_status ENUM('enrolled', 'active', 'completed', 'withdrawn', 'waitlisted') DEFAULT 'enrolled',

                -- Schedule and Attendance
                meeting_schedule TEXT,
                attendance_required BOOLEAN DEFAULT TRUE,
                attendance_rate DECIMAL(5,2),

                -- Progress and Outcomes
                skills_developed TEXT,
                achievements TEXT,
                challenges_encountered TEXT,
                program_feedback TEXT,

                -- Cost and Funding
                program_cost DECIMAL(8,2) DEFAULT 0,
                funding_source VARCHAR(100),
                scholarship_awarded BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_participation_number (participation_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_program_type (program_type),
                INDEX idx_participation_status (participation_status),
                INDEX idx_enrollment_date (enrollment_date)
            );

            CREATE TABLE IF NOT EXISTS youth_incidents_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                incident_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Incident Details
                incident_date DATE NOT NULL,
                incident_time TIME,
                incident_location VARCHAR(255) NOT NULL,
                incident_type ENUM('behavioral_issue', 'substance_abuse', 'truancy', 'bullying', 'family_conflict', 'school_violation', 'legal_issue', 'medical_emergency', 'other') NOT NULL,

                -- Incident Description
                incident_description TEXT NOT NULL,
                immediate_actions_taken TEXT,
                witnesses TEXT,

                -- Involved Parties
                reported_by VARCHAR(200) NOT NULL,
                reported_to VARCHAR(200),
                school_official_notified BOOLEAN DEFAULT FALSE,
                law_enforcement_notified BOOLEAN DEFAULT FALSE,

                -- Response and Follow-up
                response_actions TEXT,
                follow_up_required BOOLEAN DEFAULT TRUE,
                follow_up_actions TEXT,
                follow_up_date DATE,

                -- Severity and Risk Assessment
                severity_level ENUM('low', 'moderate', 'high', 'critical') DEFAULT 'low',
                risk_to_self BOOLEAN DEFAULT FALSE,
                risk_to_others BOOLEAN DEFAULT FALSE,

                -- Resolution
                resolution_status ENUM('open', 'under_investigation', 'resolved', 'referred', 'escalated') DEFAULT 'open',
                resolution_date DATE,
                resolution_notes TEXT,

                -- Review Information
                reviewed_by VARCHAR(200),
                review_date DATE,
                review_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_incident_number (incident_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_incident_type (incident_type),
                INDEX idx_incident_date (incident_date),
                INDEX idx_severity_level (severity_level),
                INDEX idx_resolution_status (resolution_status)
            );

            CREATE TABLE IF NOT EXISTS youth_educational_assessments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assessment_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Assessment Details
                assessment_date DATE NOT NULL,
                assessment_type ENUM('initial', 'annual', 'specialized', 'transition', 'follow_up') DEFAULT 'annual',
                assessor_name VARCHAR(200) NOT NULL,
                assessor_credentials VARCHAR(200),

                -- Academic Performance
                reading_level VARCHAR(50),
                math_level VARCHAR(50),
                writing_level VARCHAR(50),
                overall_gpa DECIMAL(3,2),

                -- Learning Style and Needs
                learning_style ENUM('visual', 'auditory', 'kinesthetic', 'mixed') DEFAULT 'mixed',
                special_education_needs BOOLEAN DEFAULT FALSE,
                iep_status BOOLEAN DEFAULT FALSE,
                learning_disabilities TEXT,

                -- Social and Emotional Development
                social_skills_rating INT CHECK (social_skills_rating >= 1 AND social_skills_rating <= 5),
                emotional_regulation_rating INT CHECK (emotional_regulation_rating >= 1 AND emotional_regulation_rating <= 5),
                behavioral_concerns TEXT,

                -- Career and Vocational Interests
                career_interests TEXT,
                vocational_skills TEXT,
                work_experience TEXT,

                -- Recommendations
                educational_recommendations TEXT,
                support_services_needed TEXT,
                accommodations_required TEXT,

                -- Follow-up
                next_assessment_date DATE,
                progress_goals TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_assessment_number (assessment_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_assessment_type (assessment_type),
                INDEX idx_assessment_date (assessment_date)
            );

            CREATE TABLE IF NOT EXISTS youth_family_contacts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                contact_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Contact Details
                contact_date DATE NOT NULL,
                contact_time TIME,
                contact_type ENUM('home_visit', 'phone_call', 'office_visit', 'school_visit', 'court_appearance', 'emergency_contact', 'other') NOT NULL,
                contact_method ENUM('in_person', 'phone', 'video_call', 'email', 'text_message') DEFAULT 'in_person',

                -- Contact Purpose and Outcome
                contact_purpose TEXT,
                contact_outcome TEXT,
                issues_discussed TEXT,
                action_items TEXT,

                -- Participants
                case_worker_present VARCHAR(200),
                family_members_present TEXT,
                other_participants TEXT,

                -- Family Assessment
                family_engagement_level ENUM('high', 'moderate', 'low', 'no_show') DEFAULT 'moderate',
                family_strengths TEXT,
                family_concerns TEXT,
                safety_concerns_identified BOOLEAN DEFAULT FALSE,

                -- Follow-up Actions
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE,
                follow_up_assigned_to VARCHAR(200),

                -- Documentation
                contact_notes TEXT,
                attachments JSON,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_contact_number (contact_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_contact_type (contact_type),
                INDEX idx_contact_date (contact_date)
            );

            CREATE TABLE IF NOT EXISTS youth_transitions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                transition_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Transition Details
                transition_type ENUM('school_to_work', 'foster_care_exit', 'independent_living', 'higher_education', 'military_service', 'other') NOT NULL,
                transition_start_date DATE NOT NULL,
                transition_end_date DATE,
                transition_status ENUM('planning', 'in_progress', 'completed', 'on_hold', 'discontinued') DEFAULT 'planning',

                -- Transition Goals
                short_term_goals TEXT,
                long_term_goals TEXT,
                success_criteria TEXT,

                -- Support Services
                housing_assistance BOOLEAN DEFAULT FALSE,
                employment_support BOOLEAN DEFAULT FALSE,
                education_support BOOLEAN DEFAULT FALSE,
                financial_literacy_training BOOLEAN DEFAULT FALSE,
                life_skills_training BOOLEAN DEFAULT FALSE,

                -- Resources and Referrals
                housing_resources TEXT,
                employment_resources TEXT,
                educational_resources TEXT,
                community_resources TEXT,

                -- Progress Tracking
                milestones_achieved TEXT,
                challenges_encountered TEXT,
                support_services_utilized TEXT,

                -- Outcome Assessment
                transition_success_rating INT CHECK (transition_success_rating >= 1 AND transition_success_rating <= 5),
                outcome_notes TEXT,
                follow_up_required BOOLEAN DEFAULT TRUE,
                follow_up_date DATE,

                -- Review Information
                transition_coordinator VARCHAR(200),
                review_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_transition_number (transition_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_transition_type (transition_type),
                INDEX idx_transition_status (transition_status),
                INDEX idx_transition_start_date (transition_start_date)
            );

            CREATE TABLE IF NOT EXISTS youth_emergency_contacts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                contact_number VARCHAR(20) UNIQUE NOT NULL,
                youth_id INT NOT NULL,

                -- Emergency Details
                emergency_date DATE NOT NULL,
                emergency_time TIME NOT NULL,
                emergency_type ENUM('medical_emergency', 'family_crisis', 'behavioral_crisis', 'runaway', 'substance_abuse', 'suicidal_threat', 'other') NOT NULL,

                -- Emergency Description
                emergency_description TEXT NOT NULL,
                immediate_response TEXT,
                location_of_incident VARCHAR(255),

                -- Response Details
                response_time_minutes INT,
                responder_name VARCHAR(200),
                responder_agency ENUM('police', 'ambulance', 'crisis_team', 'child_protective_services', 'hospital', 'other') DEFAULT 'crisis_team',

                -- Medical/Behavioral Assessment
                medical_attention_required BOOLEAN DEFAULT FALSE,
                medical_facility VARCHAR(200),
                behavioral_assessment_conducted BOOLEAN DEFAULT FALSE,
                risk_level ENUM('low', 'moderate', 'high', 'critical') DEFAULT 'moderate',

                -- Family Notification
                family_notified BOOLEAN DEFAULT TRUE,
                family_response TEXT,
                guardian_present BOOLEAN DEFAULT FALSE,

                -- Resolution and Follow-up
                resolution_actions TEXT,
                follow_up_plan TEXT,
                additional_services_recommended TEXT,

                -- Documentation
                emergency_report TEXT,
                attachments JSON,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (youth_id) REFERENCES youth_registrations(id) ON DELETE CASCADE,
                INDEX idx_contact_number (contact_number),
                INDEX idx_youth_id (youth_id),
                INDEX idx_emergency_type (emergency_type),
                INDEX idx_emergency_date (emergency_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup youth registration workflow
        $registrationWorkflow = [
            'name' => 'Youth Registration Process',
            'description' => 'Complete workflow for youth registration and assessment',
            'steps' => [
                [
                    'name' => 'Initial Intake',
                    'type' => 'user_task',
                    'assignee' => 'guardian',
                    'form' => 'youth_intake_form'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'service_task',
                    'service' => 'risk_assessment_service'
                ],
                [
                    'name' => 'Family Assessment',
                    'type' => 'user_task',
                    'assignee' => 'family_assessor',
                    'form' => 'family_assessment_form'
                ],
                [
                    'name' => 'Service Plan Development',
                    'type' => 'user_task',
                    'assignee' => 'case_worker',
                    'form' => 'service_plan_form'
                ],
                [
                    'name' => 'Registration Approval',
                    'type' => 'user_task',
                    'assignee' => 'youth_services_supervisor',
                    'form' => 'registration_approval_form'
                ]
            ]
        ];

        // Setup child welfare workflow
        $welfareWorkflow = [
            'name' => 'Child Welfare Investigation Process',
            'description' => 'Complete workflow for child welfare investigations',
            'steps' => [
                [
                    'name' => 'Report Intake',
                    'type' => 'user_task',
                    'assignee' => 'intake_worker',
                    'form' => 'welfare_report_form'
                ],
                [
                    'name' => 'Initial Assessment',
                    'type' => 'service_task',
                    'service' => 'initial_assessment_service'
                ],
                [
                    'name' => 'Family Investigation',
                    'type' => 'user_task',
                    'assignee' => 'investigator',
                    'form' => 'family_investigation_form'
                ],
                [
                    'name' => 'Safety Plan Development',
                    'type' => 'user_task',
                    'assignee' => 'case_worker',
                    'form' => 'safety_plan_form'
                ],
                [
                    'name' => 'Case Determination',
                    'type' => 'user_task',
                    'assignee' => 'supervisor',
                    'form' => 'case_determination_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/registration_workflow.json', json_encode($registrationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/welfare_workflow.json', json_encode($welfareWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/medical_records',
            __DIR__ . '/uploads/school_records',
            __DIR__ . '/uploads/assessment_reports',
            __DIR__ . '/uploads/incident_reports',
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
            'youth_emergency_contacts',
            'youth_transitions',
            'youth_family_contacts',
            'youth_educational_assessments',
            'youth_incidents_reports',
            'youth_program_participation',
            'youth_service_plans',
            'youth_registrations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerYouth($data) {
        try {
            $this->validateYouthRegistrationData($data);
            $youthNumber = $this->generateYouthNumber();

            $sql = "INSERT INTO youth_registrations (
                youth_number, user_id, first_name, middle_name, last_name,
                date_of_birth, gender, nationality, email, phone, alternate_phone,
                address, guardian_name, guardian_relationship, guardian_phone,
                guardian_email, guardian_address, school_name, school_grade,
                school_district, student_id, academic_status, medical_conditions,
                medications, allergies, primary_physician, physician_phone,
                family_structure, siblings_count, household_income,
                receives_public_assistance, risk_level, abuse_concern,
                neglect_concern, behavioral_concerns, substance_abuse_risk,
                requires_educational_support, requires_mental_health_services,
                requires_family_support, requires_after_school_programs,
                requires_mentoring, legal_status, registration_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $youthNumber, $data['user_id'] ?? null, $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['nationality'] ?? null, $data['email'] ?? null,
                $data['phone'] ?? null, $data['alternate_phone'] ?? null, json_encode($data['address']),
                $data['guardian_name'], $data['guardian_relationship'], $data['guardian_phone'],
                $data['guardian_email'] ?? null, json_encode($data['guardian_address'] ?? []),
                $data['school_name'] ?? null, $data['school_grade'] ?? null,
                $data['school_district'] ?? null, $data['student_id'] ?? null,
                $data['academic_status'] ?? 'enrolled', $data['medical_conditions'] ?? null,
                $data['medications'] ?? null, $data['allergies'] ?? null,
                $data['primary_physician'] ?? null, $data['physician_phone'] ?? null,
                $data['family_structure'] ?? 'two_parent', $data['siblings_count'] ?? 0,
                $data['household_income'] ?? null, $data['receives_public_assistance'] ?? false,
                $data['risk_level'] ?? 'low', $data['abuse_concern'] ?? false,
                $data['neglect_concern'] ?? false, $data['behavioral_concerns'] ?? null,
                $data['substance_abuse_risk'] ?? false, $data['requires_educational_support'] ?? false,
                $data['requires_mental_health_services'] ?? false, $data['requires_family_support'] ?? false,
                $data['requires_after_school_programs'] ?? false, $data['requires_mentoring'] ?? false,
                $data['legal_status'] ?? 'no_legal_issues', date('Y-m-d'), $data['created_by']
            ];

            $youthId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'youth_id' => $youthId,
                'youth_number' => $youthNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createServicePlan($data) {
        try {
            $this->validateServicePlanData($data);
            $planNumber = $this->generateServicePlanNumber();

            $sql = "INSERT INTO youth_service_plans (
                plan_number, youth_id, plan_type, plan_start_date,
                plan_end_date, short_term_goals, long_term_goals,
                measurable_objectives, success_criteria, educational_services,
                mental_health_services, family_counseling, behavioral_interventions,
                skill_building_programs, primary_case_worker, school_liaison,
                mental_health_provider, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $planId = $this->db->insert($sql, [
                $planNumber, $data['youth_id'], $data['plan_type'],
                $data['plan_start_date'], $data['plan_end_date'] ?? null,
                $data['short_term_goals'] ?? null, $data['long_term_goals'] ?? null,
                $data['measurable_objectives'] ?? null, $data['success_criteria'] ?? null,
                $data['educational_services'] ?? null, $data['mental_health_services'] ?? null,
                $data['family_counseling'] ?? null, $data['behavioral_interventions'] ?? null,
                $data['skill_building_programs'] ?? null, $data['primary_case_worker'] ?? null,
                $data['school_liaison'] ?? null, $data['mental_health_provider'] ?? null,
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

    public function enrollInProgram($data) {
        try {
            $this->validateProgramEnrollmentData($data);
            $participationNumber = $this->generateParticipationNumber();

            $sql = "INSERT INTO youth_program_participation (
                participation_number, youth_id, program_type, program_name,
                program_description, program_provider, contact_person,
                contact_phone, contact_email, enrollment_date, start_date,
                end_date, meeting_schedule, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $participationId = $this->db->insert($sql, [
                $participationNumber, $data['youth_id'], $data['program_type'],
                $data['program_name'], $data['program_description'] ?? null,
                $data['program_provider'] ?? null, $data['contact_person'] ?? null,
                $data['contact_phone'] ?? null, $data['contact_email'] ?? null,
                $data['enrollment_date'], $data['start_date'] ?? null,
                $data['end_date'] ?? null, $data['meeting_schedule'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'participation_id' => $participationId,
                'participation_number' => $participationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function reportIncident($data) {
        try {
            $this->validateIncidentReportData($data);
            $incidentNumber = $this->generateIncidentNumber();

            $sql = "INSERT INTO youth_incidents_reports (
                incident_number, youth_id, incident_date, incident_time,
                incident_location, incident_type, incident_description,
                immediate_actions_taken, witnesses, reported_by, reported_to,
                school_official_notified, law_enforcement_notified, severity_level,
                risk_to_self, risk_to_others, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $incidentId = $this->db->insert($sql, [
                $incidentNumber, $data['youth_id'], $data['incident_date'],
                $data['incident_time'] ?? null, $data['incident_location'],
                $data['incident_type'], $data['incident_description'],
                $data['immediate_actions_taken'] ?? null, $data['witnesses'] ?? null,
                $data['reported_by'], $data['reported_to'] ?? null,
                $data['school_official_notified'] ?? false, $data['law_enforcement_notified'] ?? false,
                $data['severity_level'] ?? 'low', $data['risk_to_self'] ?? false,
                $data['risk_to_others'] ?? false, $data['created_by']
            ]);

            return [
                'success' => true,
                'incident_id' => $incidentId,
                'incident_number' => $incidentNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function conductEducationalAssessment($data) {
        try {
            $this->validateEducationalAssessmentData($data);
            $assessmentNumber = $this->generateAssessmentNumber();

            $sql = "INSERT INTO youth_educational_assessments (
                assessment_number, youth_id, assessment_date, assessment_type,
                assessor_name, assessor_credentials, reading_level, math_level,
                writing_level, overall_gpa, learning_style, special_education_needs,
                iep_status, learning_disabilities, social_skills_rating,
                emotional_regulation_rating, behavioral_concerns, career_interests,
                vocational_skills, work_experience, educational_recommendations,
                support_services_needed, accommodations_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $assessmentId = $this->db->insert($sql, [
                $assessmentNumber, $data['youth_id'], $data['assessment_date'],
                $data['assessment_type'] ?? 'annual', $data['assessor_name'],
                $data['assessor_credentials'] ?? null, $data['reading_level'] ?? null,
                $data['math_level'] ?? null, $data['writing_level'] ?? null,
                $data['overall_gpa'] ?? null, $data['learning_style'] ?? 'mixed',
                $data['special_education_needs'] ?? false, $data['iep_status'] ?? false,
                $data['learning_disabilities'] ?? null, $data['social_skills_rating'] ?? null,
                $data['emotional_regulation_rating'] ?? null, $data['behavioral_concerns'] ?? null,
                $data['career_interests'] ?? null, $data['vocational_skills'] ?? null,
                $data['work_experience'] ?? null, $data['educational_recommendations'] ?? null,
                $data['support_services_needed'] ?? null, $data['accommodations_required'] ?? null,
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

    public function recordFamilyContact($data) {
        try {
            $this->validateFamilyContactData($data);
            $contactNumber = $this->generateContactNumber();

            $sql = "INSERT INTO youth_family_contacts (
                contact_number, youth_id, contact_date, contact_time,
                contact_type, contact_method, contact_purpose, contact_outcome,
                issues_discussed, action_items, case_worker_present,
                family_members_present, other_participants, family_engagement_level,
                family_strengths, family_concerns, safety_concerns_identified,
                follow_up_required, follow_up_date, follow_up_assigned_to,
                contact_notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $contactId = $this->db->insert($sql, [
                $contactNumber, $data['youth_id'], $data['contact_date'],
                $data['contact_time'] ?? null, $data['contact_type'],
                $data['contact_method'] ?? 'in_person', $data['contact_purpose'] ?? null,
                $data['contact_outcome'] ?? null, $data['issues_discussed'] ?? null,
                $data['action_items'] ?? null, $data['case_worker_present'] ?? null,
                $data['family_members_present'] ?? null, $data['other_participants'] ?? null,
                $data['family_engagement_level'] ?? 'moderate', $data['family_strengths'] ?? null,
                $data['family_concerns'] ?? null, $data['safety_concerns_identified'] ?? false,
                $data['follow_up_required'] ?? false, $data['follow_up_date'] ?? null,
                $data['follow_up_assigned_to'] ?? null, $data['contact_notes'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'contact_id' => $contactId,
                'contact_number' => $contactNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTransitionPlan($data) {
        try {
            $this->validateTransitionPlanData($data);
            $transitionNumber = $this->generateTransitionNumber();

            $sql = "INSERT INTO youth_transitions (
                transition_number, youth_id, transition_type, transition_start_date,
                transition_end_date, short_term_goals, long_term_goals,
                success_criteria, housing_assistance, employment_support,
                education_support, financial_literacy_training, life_skills_training,
                housing_resources, employment_resources, educational_resources,
                community_resources, transition_coordinator, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $transitionId = $this->db->insert($sql, [
                $transitionNumber, $data['youth_id'], $data['transition_type'],
                $data['transition_start_date'], $data['transition_end_date'] ?? null,
                $data['short_term_goals'] ?? null, $data['long_term_goals'] ?? null,
                $data['success_criteria'] ?? null, $data['housing_assistance'] ?? false,
                $data['employment_support'] ?? false, $data['education_support'] ?? false,
                $data['financial_literacy_training'] ?? false, $data['life_skills_training'] ?? false,
                $data['housing_resources'] ?? null, $data['employment_resources'] ?? null,
                $data['educational_resources'] ?? null, $data['community_resources'] ?? null,
                $data['transition_coordinator'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'transition_id' => $transitionId,
                'transition_number' => $transitionNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recordEmergencyContact($data) {
        try {
            $this->validateEmergencyContactData($data);
            $contactNumber = $this->generateEmergencyContactNumber();

            $sql = "INSERT INTO youth_emergency_contacts (
                contact_number, youth_id, emergency_date, emergency_time,
                emergency_type, emergency_description, immediate_response,
                location_of_incident, response_time_minutes, responder_name,
                responder_agency, medical_attention_required, medical_facility,
                behavioral_assessment_conducted, risk_level, family_notified,
                family_response, guardian_present, resolution_actions,
                follow_up_plan, additional_services_recommended, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $contactId = $this->db->insert($sql, [
                $contactNumber, $data['youth_id'], $data['emergency_date'],
                $data['emergency_time'], $data['emergency_type'],
                $data['emergency_description'], $data['immediate_response'] ?? null,
                $data['location_of_incident'] ?? null, $data['response_time_minutes'] ?? null,
                $data['responder_name'] ?? null, $data['responder_agency'] ?? 'crisis_team',
                $data['medical_attention_required'] ?? false, $data['medical_facility'] ?? null,
                $data['behavioral_assessment_conducted'] ?? false, $data['risk_level'] ?? 'moderate',
                $data['family_notified'] ?? true, $data['family_response'] ?? null,
                $data['guardian_present'] ?? false, $data['resolution_actions'] ?? null,
                $data['follow_up_plan'] ?? null, $data['additional_services_recommended'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'contact_id' => $contactId,
                'contact_number' => $contactNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getYouthRecord($youthNumber, $userId) {
        $sql = "SELECT * FROM youth_registrations WHERE youth_number = ?";
        $youth = $this->db->fetch($sql, [$youthNumber]);

        if (!$youth) {
            return ['success' => false, 'error' => 'Youth record not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToYouthRecord($youth, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $servicePlans = $this->getYouthServicePlans($youth['id']);
        $programParticipation = $this->getYouthProgramParticipation($youth['id']);
        $incidents = $this->getYouthIncidents($youth['id']);
        $educationalAssessments = $this->getYouthEducationalAssessments($youth['id']);
        $familyContacts = $this->getYouthFamilyContacts($youth['id']);
        $transitions = $this->getYouthTransitions($youth['id']);
        $emergencyContacts = $this->getYouthEmergencyContacts($youth['id']);

        return [
            'success' => true,
            'youth' => $youth,
            'service_plans' => $servicePlans,
            'program_participation' => $programParticipation,
            'incidents' => $incidents,
            'educational_assessments' => $educationalAssessments,
            'family_contacts' => $familyContacts,
            'transitions' => $transitions,
            'emergency_contacts' => $emergencyContacts
        ];
    }

    // Helper Methods
    private function validateYouthRegistrationData($data) {
        $required = [
            'first_name', 'last_name', 'date_of_birth', 'gender',
            'address', 'guardian_name', 'guardian_relationship', 'guardian_phone',
            'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age (must be under 18 for youth services)
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age >= 18) {
            throw new Exception('Must be under 18 years old for youth services');
        }
    }

    private function validateServicePlanData($data) {
        $required = ['youth_id', 'plan_type', 'plan_start_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateProgramEnrollmentData($data) {
        $required = ['youth_id', 'program_type', 'program_name', 'enrollment_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateIncidentReportData($data) {
        $required = [
            'youth_id', 'incident_date', 'incident_location',
            'incident_type', 'incident_description', 'reported_by', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateEducationalAssessmentData($data) {
        $required = ['youth_id', 'assessment_date', 'assessor_name', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateFamilyContactData($data) {
        $required = ['youth_id', 'contact_date', 'contact_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateTransitionPlanData($data) {
        $required = ['youth_id', 'transition_type', 'transition_start_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateEmergencyContactData($data) {
        $required = [
            'youth_id', 'emergency_date', 'emergency_time',
            'emergency_type', 'emergency_description', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateYouthNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "YTH{$date}{$random}";
    }

    private function generateServicePlanNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PLAN{$date}{$random}";
    }

    private function generateParticipationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PART{$date}{$random}";
    }

    private function generateIncidentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "INC{$date}{$random}";
    }

    private function generateAssessmentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ASS{$date}{$random}";
    }

    private function generateContactNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CON{$date}{$random}";
    }

    private function generateTransitionNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "TRN{$date}{$random}";
    }

    private function generateEmergencyContactNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "EMG{$date}{$random}";
    }

    private function hasAccessToYouthRecord($youth, $userId) {
        // Check if user is the youth, guardian, authorized representative, or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getYouthServicePlans($youthId) {
        $sql = "SELECT * FROM youth_service_plans WHERE youth_id = ? ORDER BY plan_start_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function getYouthProgramParticipation($youthId) {
        $sql = "SELECT * FROM youth_program_participation WHERE youth_id = ? ORDER BY enrollment_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function getYouthIncidents($youthId) {
        $sql = "SELECT * FROM youth_incidents_reports WHERE youth_id = ? ORDER BY incident_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function getYouthEducationalAssessments($youthId) {
        $sql = "SELECT * FROM youth_educational_assessments WHERE youth_id = ? ORDER BY assessment_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function getYouthFamilyContacts($youthId) {
        $sql = "SELECT * FROM youth_family_contacts WHERE youth_id = ? ORDER BY contact_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function getYouthTransitions($youthId) {
        $sql = "SELECT * FROM youth_transitions WHERE youth_id = ? ORDER BY transition_start_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function getYouthEmergencyContacts($youthId) {
        $sql = "SELECT * FROM youth_emergency_contacts WHERE youth_id = ? ORDER BY emergency_date DESC";
        return $this->db->fetchAll($sql, [$youthId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

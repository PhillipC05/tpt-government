<?php
/**
 * Corrections & Rehabilitation Module
 * Handles prison management, inmate rehabilitation, and reentry services
 */

require_once __DIR__ . '/../ServiceModule.php';

class CorrectionsModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Corrections & Rehabilitation Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive corrections management system for inmate rehabilitation and reentry services',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'corrections.intake' => 'Process inmate intake',
                'corrections.records' => 'Access inmate records',
                'corrections.rehab' => 'Manage rehabilitation programs',
                'corrections.release' => 'Process inmate release',
                'corrections.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS inmates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                inmate_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                nationality VARCHAR(50),

                -- Physical Description
                height_cm INT,
                weight_kg INT,
                eye_color VARCHAR(20),
                hair_color VARCHAR(20),
                distinguishing_marks TEXT,

                -- Contact Information
                emergency_contact_name VARCHAR(200),
                emergency_contact_relationship VARCHAR(50),
                emergency_contact_phone VARCHAR(20),
                emergency_contact_address TEXT,

                -- Legal Information
                booking_number VARCHAR(50) UNIQUE NOT NULL,
                booking_date DATE NOT NULL,
                booking_officer VARCHAR(100),
                arresting_agency VARCHAR(100),
                arrest_warrant_number VARCHAR(50),

                -- Sentence Information
                sentence_start_date DATE,
                sentence_end_date DATE,
                sentence_type ENUM('determinate', 'indeterminate', 'life', 'death_penalty') NULL,
                minimum_sentence_years INT,
                maximum_sentence_years INT,
                parole_eligibility_date DATE,
                good_time_credits INT DEFAULT 0,

                -- Classification
                security_level ENUM('minimum', 'medium', 'maximum', 'supermax') DEFAULT 'medium',
                risk_level ENUM('low', 'medium', 'high', 'extreme') DEFAULT 'medium',
                custody_status ENUM('active', 'parole', 'probation', 'escaped', 'deceased', 'transferred') DEFAULT 'active',

                -- Facility Information
                current_facility_id INT,
                cell_block VARCHAR(20),
                cell_number VARCHAR(20),

                -- Medical Information
                medical_conditions TEXT,
                medications TEXT,
                allergies TEXT,
                mental_health_status TEXT,

                -- Program Participation
                rehabilitation_programs JSON,
                work_assignment VARCHAR(100),
                education_level VARCHAR(50),
                vocational_skills TEXT,

                -- Disciplinary Record
                disciplinary_incidents INT DEFAULT 0,
                last_incident_date DATE,

                -- Visitation
                visitation_privileges ENUM('full', 'restricted', 'none') DEFAULT 'full',
                approved_visitors JSON,

                -- Release Planning
                release_plan TEXT,
                housing_arrangement TEXT,
                employment_plan TEXT,
                supervision_requirements TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_inmate_number (inmate_number),
                INDEX idx_booking_number (booking_number),
                INDEX idx_current_facility_id (current_facility_id),
                INDEX idx_custody_status (custody_status),
                INDEX idx_sentence_end_date (sentence_end_date),
                INDEX idx_parole_eligibility_date (parole_eligibility_date)
            );

            CREATE TABLE IF NOT EXISTS correctional_facilities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                facility_code VARCHAR(20) UNIQUE NOT NULL,
                facility_name VARCHAR(200) NOT NULL,
                facility_type ENUM('prison', 'jail', 'juvenile', 'work_release', 'halfway_house') NOT NULL,

                -- Location
                address TEXT NOT NULL,
                city VARCHAR(100) NOT NULL,
                state VARCHAR(50) NOT NULL,
                zip_code VARCHAR(20),
                phone VARCHAR(20),
                emergency_contact VARCHAR(20),

                -- Capacity and Status
                design_capacity INT NOT NULL,
                current_population INT DEFAULT 0,
                security_level ENUM('minimum', 'medium', 'maximum', 'mixed') NOT NULL,
                operational_status ENUM('active', 'maintenance', 'closed') DEFAULT 'active',

                -- Administration
                warden_name VARCHAR(100),
                deputy_warden VARCHAR(100),
                contact_email VARCHAR(255),

                -- Programs and Services
                available_programs JSON,
                medical_facilities BOOLEAN DEFAULT FALSE,
                educational_facilities BOOLEAN DEFAULT FALSE,
                vocational_training BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_facility_code (facility_code),
                INDEX idx_facility_type (facility_type),
                INDEX idx_operational_status (operational_status)
            );

            CREATE TABLE IF NOT EXISTS rehabilitation_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_type ENUM('education', 'vocational', 'counseling', 'substance_abuse', 'anger_management', 'life_skills', 'religious', 'recreation') NOT NULL,

                -- Program Details
                description TEXT,
                target_population TEXT,
                duration_weeks INT,
                session_frequency ENUM('daily', 'weekly', 'biweekly', 'monthly') DEFAULT 'weekly',
                max_participants INT,

                -- Requirements
                prerequisites TEXT,
                minimum_age INT,
                maximum_age INT,
                risk_level_restriction ENUM('none', 'low_only', 'medium_only', 'high_only') DEFAULT 'none',

                -- Resources
                required_staff INT DEFAULT 1,
                facility_requirements TEXT,
                materials_needed TEXT,

                -- Outcomes
                success_metrics TEXT,
                completion_rate DECIMAL(5,2),

                -- Status
                status ENUM('active', 'inactive', 'pilot', 'discontinued') DEFAULT 'active',
                start_date DATE,
                end_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS inmate_program_participation (
                id INT PRIMARY KEY AUTO_INCREMENT,
                inmate_id INT NOT NULL,
                program_id INT NOT NULL,
                enrollment_date DATE NOT NULL,
                completion_date DATE NULL,
                status ENUM('enrolled', 'in_progress', 'completed', 'dropped', 'terminated') DEFAULT 'enrolled',

                -- Progress Tracking
                sessions_completed INT DEFAULT 0,
                total_sessions INT,
                progress_notes TEXT,
                attendance_rate DECIMAL(5,2),

                -- Assessment
                pre_assessment_score INT,
                post_assessment_score INT,
                skills_learned TEXT,

                -- Staff Information
                assigned_staff_id INT,
                supervising_staff_id INT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (inmate_id) REFERENCES inmates(id) ON DELETE CASCADE,
                FOREIGN KEY (program_id) REFERENCES rehabilitation_programs(id) ON DELETE CASCADE,
                INDEX idx_inmate_id (inmate_id),
                INDEX idx_program_id (program_id),
                INDEX idx_status (status),
                INDEX idx_enrollment_date (enrollment_date)
            );

            CREATE TABLE IF NOT EXISTS disciplinary_incidents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                incident_number VARCHAR(20) UNIQUE NOT NULL,
                inmate_id INT NOT NULL,
                incident_date DATE NOT NULL,
                incident_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,

                -- Incident Details
                incident_type ENUM('violence', 'drugs', 'weapons', 'escape', 'property_damage', 'rule_violation', 'other') NOT NULL,
                severity ENUM('minor', 'moderate', 'major', 'critical') NOT NULL,
                description TEXT NOT NULL,

                -- Involved Parties
                reporting_officer VARCHAR(100) NOT NULL,
                witnesses TEXT,
                injured_parties TEXT,

                -- Investigation
                investigation_status ENUM('pending', 'in_progress', 'completed', 'dismissed') DEFAULT 'pending',
                investigation_notes TEXT,
                investigator_id INT,

                -- Disciplinary Action
                disciplinary_action ENUM('verbal_warning', 'loss_of_privileges', 'solitary_confinement', 'loss_of_good_time', 'transfer', 'other') NULL,
                action_duration_days INT,
                action_start_date DATE,
                action_end_date DATE,

                -- Appeal
                appeal_filed BOOLEAN DEFAULT FALSE,
                appeal_date DATE NULL,
                appeal_result ENUM('pending', 'upheld', 'overturned', 'modified') NULL,
                appeal_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (inmate_id) REFERENCES inmates(id) ON DELETE CASCADE,
                INDEX idx_incident_number (incident_number),
                INDEX idx_inmate_id (inmate_id),
                INDEX idx_incident_date (incident_date),
                INDEX idx_severity (severity),
                INDEX idx_investigation_status (investigation_status)
            );

            CREATE TABLE IF NOT EXISTS parole_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                inmate_id INT NOT NULL,
                application_date DATE NOT NULL,
                hearing_date DATE,
                decision_date DATE,

                -- Application Details
                reason_for_parole TEXT,
                rehabilitation_progress TEXT,
                risk_assessment TEXT,
                community_support_plan TEXT,

                -- Hearing Information
                hearing_officer VARCHAR(100),
                hearing_location VARCHAR(255),
                hearing_notes TEXT,

                -- Decision
                decision ENUM('approved', 'denied', 'deferred', 'pending') DEFAULT 'pending',
                decision_reason TEXT,
                conditions_of_release TEXT,

                -- Supervision
                supervising_officer VARCHAR(100),
                supervision_level ENUM('minimum', 'medium', 'maximum') DEFAULT 'medium',
                reporting_schedule TEXT,

                -- Appeal
                appeal_filed BOOLEAN DEFAULT FALSE,
                appeal_date DATE NULL,
                appeal_result ENUM('pending', 'granted', 'denied') NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (inmate_id) REFERENCES inmates(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_inmate_id (inmate_id),
                INDEX idx_decision (decision),
                INDEX idx_hearing_date (hearing_date)
            );

            CREATE TABLE IF NOT EXISTS inmate_visits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                visit_number VARCHAR(20) UNIQUE NOT NULL,
                inmate_id INT NOT NULL,
                visitor_name VARCHAR(200) NOT NULL,
                visitor_relationship VARCHAR(50) NOT NULL,
                visitor_id_number VARCHAR(50),

                -- Visit Details
                visit_date DATE NOT NULL,
                visit_time TIME NOT NULL,
                duration_minutes INT DEFAULT 60,
                visit_type ENUM('family', 'legal', 'social_worker', 'religious', 'other') DEFAULT 'family',
                location VARCHAR(255) NOT NULL,

                -- Approval and Status
                approval_status ENUM('approved', 'pending', 'denied', 'cancelled') DEFAULT 'pending',
                approved_by VARCHAR(100),
                approval_date DATE,

                -- Visit Outcome
                visit_completed BOOLEAN DEFAULT FALSE,
                visit_notes TEXT,
                security_incidents TEXT,

                -- Monitoring
                monitored_visit BOOLEAN DEFAULT TRUE,
                recording_required BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (inmate_id) REFERENCES inmates(id) ON DELETE CASCADE,
                INDEX idx_visit_number (visit_number),
                INDEX idx_inmate_id (inmate_id),
                INDEX idx_visit_date (visit_date),
                INDEX idx_approval_status (approval_status)
            );

            CREATE TABLE IF NOT EXISTS inmate_work_assignments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                inmate_id INT NOT NULL,
                assignment_type ENUM('kitchen', 'laundry', 'maintenance', 'library', 'education', 'industry', 'grounds', 'other') NOT NULL,
                job_title VARCHAR(100) NOT NULL,
                department VARCHAR(100),
                supervisor VARCHAR(100),

                -- Assignment Details
                start_date DATE NOT NULL,
                end_date DATE NULL,
                hourly_rate DECIMAL(5,2) DEFAULT 0,
                weekly_hours INT DEFAULT 40,

                -- Performance
                performance_rating ENUM('excellent', 'good', 'satisfactory', 'needs_improvement', 'unsatisfactory') DEFAULT 'satisfactory',
                performance_notes TEXT,
                disciplinary_actions INT DEFAULT 0,

                -- Skills and Training
                skills_learned TEXT,
                certifications_earned TEXT,

                -- Status
                status ENUM('active', 'completed', 'terminated', 'transferred') DEFAULT 'active',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (inmate_id) REFERENCES inmates(id) ON DELETE CASCADE,
                INDEX idx_inmate_id (inmate_id),
                INDEX idx_assignment_type (assignment_type),
                INDEX idx_status (status),
                INDEX idx_start_date (start_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup inmate intake workflow
        $intakeWorkflow = [
            'name' => 'Inmate Intake Process',
            'description' => 'Complete workflow for inmate intake and classification',
            'steps' => [
                [
                    'name' => 'Initial Booking',
                    'type' => 'user_task',
                    'assignee' => 'booking_officer',
                    'form' => 'inmate_booking_form'
                ],
                [
                    'name' => 'Medical Screening',
                    'type' => 'service_task',
                    'service' => 'medical_screening_service'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'service_task',
                    'service' => 'risk_assessment_service'
                ],
                [
                    'name' => 'Classification',
                    'type' => 'user_task',
                    'assignee' => 'classification_officer',
                    'form' => 'classification_form'
                ],
                [
                    'name' => 'Facility Assignment',
                    'type' => 'service_task',
                    'service' => 'facility_assignment_service'
                ],
                [
                    'name' => 'Orientation',
                    'type' => 'user_task',
                    'assignee' => 'orientation_officer',
                    'form' => 'orientation_form'
                ]
            ]
        ];

        // Setup parole application workflow
        $paroleWorkflow = [
            'name' => 'Parole Application Process',
            'description' => 'Complete workflow for parole applications and hearings',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'inmate',
                    'form' => 'parole_application_form'
                ],
                [
                    'name' => 'Initial Review',
                    'type' => 'user_task',
                    'assignee' => 'parole_officer',
                    'form' => 'parole_review_form'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'service_task',
                    'service' => 'risk_assessment_service'
                ],
                [
                    'name' => 'Hearing Preparation',
                    'type' => 'user_task',
                    'assignee' => 'parole_officer',
                    'form' => 'hearing_preparation_form'
                ],
                [
                    'name' => 'Parole Hearing',
                    'type' => 'user_task',
                    'assignee' => 'parole_board',
                    'form' => 'parole_hearing_form'
                ],
                [
                    'name' => 'Decision Implementation',
                    'type' => 'service_task',
                    'service' => 'decision_implementation_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/intake_workflow.json', json_encode($intakeWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/parole_workflow.json', json_encode($paroleWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/photos',
            __DIR__ . '/uploads/documents',
            __DIR__ . '/uploads/incidents',
            __DIR__ . '/uploads/programs',
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
            'inmate_work_assignments',
            'inmate_visits',
            'parole_applications',
            'disciplinary_incidents',
            'inmate_program_participation',
            'rehabilitation_programs',
            'correctional_facilities',
            'inmates'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function processInmateIntake($data) {
        try {
            $this->validateInmateIntakeData($data);
            $inmateNumber = $this->generateInmateNumber();
            $bookingNumber = $this->generateBookingNumber();

            $sql = "INSERT INTO inmates (
                inmate_number, user_id, first_name, middle_name, last_name,
                date_of_birth, gender, nationality, height_cm, weight_kg,
                eye_color, hair_color, emergency_contact_name, emergency_contact_relationship,
                emergency_contact_phone, emergency_contact_address,
                booking_number, booking_date, booking_officer, arresting_agency,
                security_level, risk_level, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $inmateNumber, $data['user_id'] ?? null, $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['nationality'] ?? null, $data['height_cm'] ?? null,
                $data['weight_kg'] ?? null, $data['eye_color'] ?? null, $data['hair_color'] ?? null,
                $data['emergency_contact_name'] ?? null, $data['emergency_contact_relationship'] ?? null,
                $data['emergency_contact_phone'] ?? null, json_encode($data['emergency_contact_address'] ?? []),
                $bookingNumber, $data['booking_date'], $data['booking_officer'],
                $data['arresting_agency'] ?? null, $data['security_level'] ?? 'medium',
                $data['risk_level'] ?? 'medium', $data['created_by']
            ];

            $inmateId = $this->db->insert($sql, $params);

            // Assign to facility
            $this->assignToFacility($inmateId, $data['facility_id'] ?? null);

            return [
                'success' => true,
                'inmate_id' => $inmateId,
                'inmate_number' => $inmateNumber,
                'booking_number' => $bookingNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getInmateRecord($inmateNumber, $userId) {
        $sql = "SELECT i.*, cf.facility_name, cf.facility_type
                FROM inmates i
                LEFT JOIN correctional_facilities cf ON i.current_facility_id = cf.id
                WHERE i.inmate_number = ?";

        $inmate = $this->db->fetch($sql, [$inmateNumber]);

        if (!$inmate) {
            return ['success' => false, 'error' => 'Inmate not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToInmateRecord($inmate, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $disciplinaryIncidents = $this->getInmateDisciplinaryIncidents($inmate['id']);
        $programParticipation = $this->getInmateProgramParticipation($inmate['id']);
        $visits = $this->getInmateVisits($inmate['id']);
        $workAssignments = $this->getInmateWorkAssignments($inmate['id']);

        return [
            'success' => true,
            'inmate' => $inmate,
            'disciplinary_incidents' => $disciplinaryIncidents,
            'program_participation' => $programParticipation,
            'visits' => $visits,
            'work_assignments' => $workAssignments
        ];
    }

    public function enrollInRehabilitationProgram($inmateId, $programId, $staffId) {
        try {
            // Check if inmate is eligible for the program
            if (!$this->isInmateEligibleForProgram($inmateId, $programId)) {
                return ['success' => false, 'error' => 'Inmate not eligible for this program'];
            }

            $sql = "INSERT INTO inmate_program_participation (
                inmate_id, program_id, enrollment_date, assigned_staff_id
            ) VALUES (?, ?, CURDATE(), ?)";

            $participationId = $this->db->insert($sql, [$inmateId, $programId, $staffId]);

            return [
                'success' => true,
                'participation_id' => $participationId
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recordDisciplinaryIncident($data) {
        try {
            $this->validateDisciplinaryData($data);
            $incidentNumber = $this->generateIncidentNumber();

            $sql = "INSERT INTO disciplinary_incidents (
                incident_number, inmate_id, incident_date, incident_time,
                location, incident_type, severity, description, reporting_officer
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $incidentId = $this->db->insert($sql, [
                $incidentNumber, $data['inmate_id'], $data['incident_date'],
                $data['incident_time'], $data['location'], $data['incident_type'],
                $data['severity'], $data['description'], $data['reporting_officer']
            ]);

            // Update inmate's disciplinary record
            $this->updateInmateDisciplinaryRecord($data['inmate_id']);

            return [
                'success' => true,
                'incident_id' => $incidentId,
                'incident_number' => $incidentNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitParoleApplication($data) {
        try {
            $this->validateParoleApplicationData($data);
            $applicationNumber = $this->generateParoleApplicationNumber();

            $sql = "INSERT INTO parole_applications (
                application_number, inmate_id, application_date,
                reason_for_parole, rehabilitation_progress, risk_assessment,
                community_support_plan
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['inmate_id'], $data['application_date'],
                $data['reason_for_parole'], $data['rehabilitation_progress'],
                $data['risk_assessment'], $data['community_support_plan']
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

    public function scheduleVisit($data) {
        try {
            $this->validateVisitData($data);
            $visitNumber = $this->generateVisitNumber();

            $sql = "INSERT INTO inmate_visits (
                visit_number, inmate_id, visitor_name, visitor_relationship,
                visitor_id_number, visit_date, visit_time, duration_minutes,
                visit_type, location
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $visitId = $this->db->insert($sql, [
                $visitNumber, $data['inmate_id'], $data['visitor_name'],
                $data['visitor_relationship'], $data['visitor_id_number'] ?? null,
                $data['visit_date'], $data['visit_time'], $data['duration_minutes'] ?? 60,
                $data['visit_type'] ?? 'family', $data['location']
            ]);

            return [
                'success' => true,
                'visit_id' => $visitId,
                'visit_number' => $visitNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function assignWorkAssignment($data) {
        try {
            $this->validateWorkAssignmentData($data);

            $sql = "INSERT INTO inmate_work_assignments (
                inmate_id, assignment_type, job_title, department,
                supervisor, start_date, hourly_rate, weekly_hours
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $assignmentId = $this->db->insert($sql, [
                $data['inmate_id'], $data['assignment_type'], $data['job_title'],
                $data['department'] ?? null, $data['supervisor'] ?? null,
                $data['start_date'], $data['hourly_rate'] ?? 0, $data['weekly_hours'] ?? 40
            ]);

            return [
                'success' => true,
                'assignment_id' => $assignmentId
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getFacilityStatus($facilityId) {
        $sql = "SELECT cf.*, COUNT(i.id) as current_inmate_count
                FROM correctional_facilities cf
                LEFT JOIN inmates i ON cf.id = i.current_facility_id AND i.custody_status = 'active'
                WHERE cf.id = ?
                GROUP BY cf.id";

        $facility = $this->db->fetch($sql, [$facilityId]);

        if (!$facility) {
            return ['success' => false, 'error' => 'Facility not found'];
        }

        // Get facility programs
        $programs = $this->getFacilityPrograms($facilityId);

        return [
            'success' => true,
            'facility' => $facility,
            'programs' => $programs
        ];
    }

    // Helper Methods
    private function validateInmateIntakeData($data) {
        $required = [
            'first_name', 'last_name', 'date_of_birth', 'gender',
            'booking_date', 'booking_officer', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age (must be 18+ for adult facilities)
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 18) {
            throw new Exception('Must be at least 18 years old for adult correctional facilities');
        }
    }

    private function validateDisciplinaryData($data) {
        $required = [
            'inmate_id', 'incident_date', 'incident_time', 'location',
            'incident_type', 'severity', 'description', 'reporting_officer'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateParoleApplicationData($data) {
        $required = [
            'inmate_id', 'application_date', 'reason_for_parole'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateVisitData($data) {
        $required = [
            'inmate_id', 'visitor_name', 'visitor_relationship',
            'visit_date', 'visit_time', 'location'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate future date
        $visitDate = new DateTime($data['visit_date'] . ' ' . $data['visit_time']);
        $now = new DateTime();

        if ($visitDate <= $now) {
            throw new Exception('Visit must be scheduled for a future date and time');
        }
    }

    private function validateWorkAssignmentData($data) {
        $required = [
            'inmate_id', 'assignment_type', 'job_title', 'start_date'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateInmateNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "INM{$date}{$random}";
    }

    private function generateBookingNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BK{$date}{$random}";
    }

    private function generateIncidentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "INC{$date}{$random}";
    }

    private function generateParoleApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PAR{$date}{$random}";
    }

    private function generateVisitNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "VIS{$date}{$random}";
    }

    private function assignToFacility($inmateId, $facilityId = null) {
        if (!$facilityId) {
            // Auto-assign to appropriate facility based on security level and capacity
            $facilityId = $this->findAvailableFacility($inmateId);
        }

        if ($facilityId) {
            $sql = "UPDATE inmates SET current_facility_id = ? WHERE id = ?";
            $this->db->query($sql, [$facilityId, $inmateId]);
        }
    }

    private function findAvailableFacility($inmateId) {
        // Get inmate's security level
        $sql = "SELECT security_level FROM inmates WHERE id = ?";
        $inmate = $this->db->fetch($sql, [$inmateId]);

        if (!$inmate) return null;

        // Find facility with capacity
        $sql = "SELECT id FROM correctional_facilities
                WHERE security_level = ? AND operational_status = 'active'
                AND current_population < design_capacity
                ORDER BY current_population ASC LIMIT 1";

        $facility = $this->db->fetch($sql, [$inmate['security_level']]);
        return $facility ? $facility['id'] : null;
    }

    private function hasAccessToInmateRecord($inmate, $userId) {
        // Check if user has appropriate permissions
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function isInmateEligibleForProgram($inmateId, $programId) {
        // Check program requirements against inmate profile
        $sql = "SELECT p.prerequisites, p.risk_level_restriction, i.risk_level
                FROM rehabilitation_programs p
                CROSS JOIN inmates i ON i.id = ?
                WHERE p.id = ?";

        $eligibility = $this->db->fetch($sql, [$inmateId, $programId]);

        if (!$eligibility) return false;

        // Check risk level restrictions
        if ($eligibility['risk_level_restriction'] !== 'none') {
            if ($eligibility['risk_level_restriction'] === 'low_only' && $eligibility['risk_level'] !== 'low') {
                return false;
            }
            if ($eligibility['risk_level_restriction'] === 'medium_only' && $eligibility['risk_level'] !== 'medium') {
                return false;
            }
            if ($eligibility['risk_level_restriction'] === 'high_only' && $eligibility['risk_level'] !== 'high') {
                return false;
            }
        }

        return true;
    }

    private function updateInmateDisciplinaryRecord($inmateId) {
        $sql = "UPDATE inmates SET
                disciplinary_incidents = disciplinary_incidents + 1,
                last_incident_date = CURDATE()
                WHERE id = ?";
        $this->db->query($sql, [$inmateId]);
    }

    private function getInmateDisciplinaryIncidents($inmateId) {
        $sql = "SELECT * FROM disciplinary_incidents WHERE inmate_id = ? ORDER BY incident_date DESC";
        return $this->db->fetchAll($sql, [$inmateId]);
    }

    private function getInmateProgramParticipation($inmateId) {
        $sql = "SELECT ipp.*, rp.program_name, rp.program_type
                FROM inmate_program_participation ipp
                JOIN rehabilitation_programs rp ON ipp.program_id = rp.id
                WHERE ipp.inmate_id = ? ORDER BY ipp.enrollment_date DESC";
        return $this->db->fetchAll($sql, [$inmateId]);
    }

    private function getInmateVisits($inmateId) {
        $sql = "SELECT * FROM inmate_visits WHERE inmate_id = ? ORDER BY visit_date DESC";
        return $this->db->fetchAll($sql, [$inmateId]);
    }

    private function getInmateWorkAssignments($inmateId) {
        $sql = "SELECT * FROM inmate_work_assignments WHERE inmate_id = ? ORDER BY start_date DESC";
        return $this->db->fetchAll($sql, [$inmateId]);
    }

    private function getFacilityPrograms($facilityId) {
        $sql = "SELECT rp.* FROM rehabilitation_programs rp
                JOIN correctional_facilities cf ON JSON_CONTAINS(cf.available_programs, CAST(rp.id AS JSON))
                WHERE cf.id = ? AND rp.status = 'active'";
        return $this->db->fetchAll($sql, [$facilityId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

<?php
/**
 * Event Permits Module
 * Handles event application forms, risk assessment workflow, and public notification system
 *
 * @package TPTGovernment\Modules\EventPermits
 * @version 1.0.0
 * @author TPT Government System
 */

require_once __DIR__ . '/../ServiceModule.php';

class EventPermitsModule extends ServiceModule {
    private Database $db;
    private array $config;
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_FILE_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    public function __construct(Database $db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Event Permits Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive event permitting system including application processing, risk assessment, public notification, insurance verification, and permit tracking',
            'dependencies' => ['IdentityServices', 'FinancialManagement', 'RecordsManagement'],
            'permissions' => [
                'event.view' => 'View event permit information',
                'event.apply' => 'Apply for event permits',
                'event.manage' => 'Manage event permit applications',
                'event.admin' => 'Administrative functions for event permitting'
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
            CREATE TABLE IF NOT EXISTS event_permits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permit_number VARCHAR(20) UNIQUE NOT NULL,
                applicant_id INT,

                -- Event Information
                event_name VARCHAR(200) NOT NULL,
                event_description TEXT,
                event_type ENUM('concert', 'festival', 'sports', 'parade', 'protest', 'wedding', 'corporate', 'charity', 'private', 'other') DEFAULT 'private',
                event_category ENUM('public', 'private', 'commercial', 'non_profit', 'government') DEFAULT 'private',

                -- Event Details
                event_date DATE NOT NULL,
                event_start_time TIME NOT NULL,
                event_end_time TIME NOT NULL,
                expected_attendance INT,
                actual_attendance INT,

                -- Location Information
                venue_name VARCHAR(200) NOT NULL,
                venue_address TEXT NOT NULL,
                venue_city VARCHAR(100) NOT NULL,
                venue_state_province VARCHAR(100),
                venue_postal_code VARCHAR(20),
                venue_coordinates VARCHAR(50), -- latitude,longitude

                -- Contact Information
                organizer_name VARCHAR(200) NOT NULL,
                organizer_email VARCHAR(255) NOT NULL,
                organizer_phone VARCHAR(20) NOT NULL,
                organizer_address TEXT,

                -- Permit Status
                permit_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled', 'expired', 'completed') DEFAULT 'draft',
                application_date DATE NOT NULL,
                review_date DATE,
                approval_date DATE,
                expiry_date DATE,

                -- Financial Information
                application_fee DECIMAL(8,2) DEFAULT 0,
                permit_fee DECIMAL(8,2) DEFAULT 0,
                insurance_fee DECIMAL(8,2) DEFAULT 0,
                total_amount DECIMAL(8,2) DEFAULT 0,
                payment_status ENUM('pending', 'paid', 'refunded', 'waived') DEFAULT 'pending',

                -- Risk Assessment
                risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                risk_assessment_date DATE,
                risk_assessment_notes TEXT,

                -- Insurance Information
                insurance_required BOOLEAN DEFAULT TRUE,
                insurance_provider VARCHAR(200),
                insurance_policy_number VARCHAR(50),
                insurance_coverage_amount DECIMAL(10,2),
                insurance_expiry_date DATE,

                -- Supporting Documents
                application_documents TEXT, -- JSON array of document paths
                insurance_documents TEXT, -- JSON array of document paths
                risk_assessment_documents TEXT, -- JSON array of document paths

                -- Review Information
                reviewer_id INT,
                review_notes TEXT,
                approval_conditions TEXT,

                -- Public Notification
                public_notification_required BOOLEAN DEFAULT TRUE,
                public_notification_date DATE,
                public_notification_method ENUM('newspaper', 'website', 'social_media', 'radio', 'tv', 'multiple') DEFAULT 'website',

                -- Emergency Contacts
                emergency_contact_name VARCHAR(200),
                emergency_contact_phone VARCHAR(20),
                emergency_contact_email VARCHAR(255),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_permit_number (permit_number),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_event_date (event_date),
                INDEX idx_permit_status (permit_status),
                INDEX idx_event_type (event_type),
                INDEX idx_risk_level (risk_level),
                INDEX idx_venue_city (venue_city)
            );

            CREATE TABLE IF NOT EXISTS event_permit_requirements (
                id INT PRIMARY KEY AUTO_INCREMENT,
                requirement_code VARCHAR(20) UNIQUE NOT NULL,
                event_type VARCHAR(50) NOT NULL,

                -- Requirement Details
                requirement_name VARCHAR(200) NOT NULL,
                requirement_description TEXT,
                requirement_category ENUM('insurance', 'safety', 'health', 'environmental', 'traffic', 'noise', 'waste', 'other') DEFAULT 'other',

                -- Applicability
                minimum_attendance INT DEFAULT 0,
                risk_level_required ENUM('low', 'medium', 'high', 'critical', 'all') DEFAULT 'all',
                venue_type_required VARCHAR(100),

                -- Requirement Rules
                is_mandatory BOOLEAN DEFAULT TRUE,
                is_conditional BOOLEAN DEFAULT FALSE,
                condition_description TEXT,

                -- Documentation
                documents_required TEXT, -- JSON array of required documents
                verification_method ENUM('document_review', 'inspection', 'third_party', 'self_certification') DEFAULT 'document_review',

                -- Status
                requirement_status ENUM('active', 'inactive', 'deprecated') DEFAULT 'active',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_requirement_code (requirement_code),
                INDEX idx_event_type (event_type),
                INDEX idx_requirement_category (requirement_category),
                INDEX idx_requirement_status (requirement_status)
            );

            CREATE TABLE IF NOT EXISTS event_permit_inspections (
                id INT PRIMARY KEY AUTO_INCREMENT,
                inspection_code VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NOT NULL,

                -- Inspection Details
                inspection_type ENUM('pre_event', 'during_event', 'post_event', 'follow_up') DEFAULT 'pre_event',
                inspection_date DATE NOT NULL,
                inspection_time TIME,
                inspector_name VARCHAR(100),

                -- Inspection Results
                inspection_status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
                compliance_status ENUM('compliant', 'non_compliant', 'partial_compliance', 'pending_review') DEFAULT 'pending_review',

                -- Findings
                inspection_findings TEXT,
                violations_found TEXT, -- JSON array of violations
                corrective_actions_required TEXT, -- JSON array of required actions

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE,
                follow_up_notes TEXT,

                -- Documentation
                inspection_report TEXT, -- Path to inspection report
                photos_evidence TEXT, -- JSON array of photo paths

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (permit_id) REFERENCES event_permits(id) ON DELETE CASCADE,
                INDEX idx_inspection_code (inspection_code),
                INDEX idx_permit_id (permit_id),
                INDEX idx_inspection_date (inspection_date),
                INDEX idx_inspection_status (inspection_status),
                INDEX idx_compliance_status (compliance_status)
            );

            CREATE TABLE IF NOT EXISTS event_permit_violations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                violation_code VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NOT NULL,
                inspection_id INT,

                -- Violation Details
                violation_type ENUM('safety', 'health', 'environmental', 'traffic', 'noise', 'waste', 'insurance', 'other') DEFAULT 'other',
                violation_description TEXT NOT NULL,
                severity_level ENUM('minor', 'moderate', 'major', 'critical') DEFAULT 'minor',

                -- Violation Status
                violation_status ENUM('identified', 'corrected', 'pending_correction', 'escalated', 'resolved') DEFAULT 'identified',
                identified_date DATE NOT NULL,
                correction_deadline DATE,
                actual_correction_date DATE,

                -- Corrective Actions
                corrective_actions_required TEXT,
                corrective_actions_taken TEXT,

                -- Penalties
                penalty_assessed BOOLEAN DEFAULT FALSE,
                penalty_type ENUM('warning', 'fine', 'permit_suspension', 'permit_revocation', 'other') DEFAULT 'warning',
                penalty_amount DECIMAL(8,2) DEFAULT 0,
                penalty_description TEXT,

                -- Officer Information
                identified_by VARCHAR(100),
                reviewed_by VARCHAR(100),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (permit_id) REFERENCES event_permits(id) ON DELETE CASCADE,
                FOREIGN KEY (inspection_id) REFERENCES event_permit_inspections(id) ON DELETE SET NULL,
                INDEX idx_violation_code (violation_code),
                INDEX idx_permit_id (permit_id),
                INDEX idx_violation_status (violation_status),
                INDEX idx_severity_level (severity_level),
                INDEX idx_identified_date (identified_date)
            );

            CREATE TABLE IF NOT EXISTS event_public_notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                notification_code VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NOT NULL,

                -- Notification Details
                notification_type ENUM('initial', 'update', 'cancellation', 'emergency') DEFAULT 'initial',
                notification_date DATE NOT NULL,
                publication_date DATE,

                -- Publication Information
                publication_method ENUM('newspaper', 'website', 'social_media', 'radio', 'tv', 'public_notice_board') NOT NULL,
                publication_name VARCHAR(200), -- e.g., newspaper name, website URL
                publication_reference VARCHAR(100), -- e.g., issue number, post ID

                -- Notification Content
                notification_title VARCHAR(200) NOT NULL,
                notification_content TEXT NOT NULL,
                notification_summary TEXT,

                -- Coverage Area
                coverage_area VARCHAR(100), -- e.g., city-wide, district-specific
                estimated_readership INT,

                -- Status and Verification
                notification_status ENUM('draft', 'submitted', 'published', 'verified', 'expired') DEFAULT 'draft',
                verification_date DATE,
                verification_method ENUM('screenshot', 'publication_confirmation', 'third_party_verification') DEFAULT 'screenshot',

                -- Documentation
                proof_of_publication TEXT, -- JSON array of proof documents

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (permit_id) REFERENCES event_permits(id) ON DELETE CASCADE,
                INDEX idx_notification_code (notification_code),
                INDEX idx_permit_id (permit_id),
                INDEX idx_notification_date (notification_date),
                INDEX idx_publication_method (publication_method),
                INDEX idx_notification_status (notification_status)
            );

            CREATE TABLE IF NOT EXISTS event_permit_appeals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                appeal_code VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NOT NULL,

                -- Appeal Details
                appeal_type ENUM('permit_denial', 'violation_penalty', 'inspection_findings', 'other') DEFAULT 'permit_denial',
                appeal_date DATE NOT NULL,
                appeal_description TEXT NOT NULL,

                -- Appeal Grounds
                appeal_grounds TEXT,
                supporting_evidence TEXT, -- JSON array of evidence documents

                -- Appeal Status
                appeal_status ENUM('submitted', 'under_review', 'approved', 'denied', 'withdrawn') DEFAULT 'submitted',
                review_date DATE,
                decision_date DATE,

                -- Review Information
                reviewer_name VARCHAR(100),
                review_notes TEXT,
                decision_description TEXT,

                -- Appeal Outcome
                appeal_outcome TEXT,
                conditions_imposed TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (permit_id) REFERENCES event_permits(id) ON DELETE CASCADE,
                INDEX idx_appeal_code (appeal_code),
                INDEX idx_permit_id (permit_id),
                INDEX idx_appeal_status (appeal_status),
                INDEX idx_appeal_date (appeal_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup event permit application workflow
        $permitApplicationWorkflow = [
            'name' => 'Event Permit Application Workflow',
            'description' => 'Complete workflow for event permit applications and approvals',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'permit_application_form'
                ],
                [
                    'name' => 'Initial Review',
                    'type' => 'user_task',
                    'assignee' => 'permit_officer',
                    'form' => 'initial_review_form'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'user_task',
                    'assignee' => 'risk_assessor',
                    'form' => 'risk_assessment_form'
                ],
                [
                    'name' => 'Insurance Verification',
                    'type' => 'service_task',
                    'service' => 'insurance_verification_service'
                ],
                [
                    'name' => 'Public Notification',
                    'type' => 'user_task',
                    'assignee' => 'notification_officer',
                    'form' => 'public_notification_form'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'permitting_manager',
                    'form' => 'final_approval_form'
                ],
                [
                    'name' => 'Permit Issuance',
                    'type' => 'service_task',
                    'service' => 'permit_issuance_service'
                ],
                [
                    'name' => 'Applicant Notification',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'permit_notification_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/event_workflow.json', json_encode($permitApplicationWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/applications',
            __DIR__ . '/uploads/insurance',
            __DIR__ . '/uploads/inspections',
            __DIR__ . '/uploads/notifications',
            __DIR__ . '/uploads/appeals',
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
            'event_permit_appeals',
            'event_public_notifications',
            'event_permit_violations',
            'event_permit_inspections',
            'event_permit_requirements',
            'event_permits'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    /**
     * Create a new event permit application
     *
     * @param array $data Application data
     * @return array Result with success status and permit information
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function createPermitApplication(array $data): array {
        $this->db->beginTransaction();

        try {
            // Validate and sanitize input data
            $sanitizedData = $this->sanitizePermitApplicationData($data);
            $this->validatePermitApplicationData($sanitizedData);

            // Generate unique permit number
            $permitNumber = $this->generateUniquePermitNumber();

            // Calculate fees and risk level
            $fees = $this->calculateApplicationFees($sanitizedData);
            $riskLevel = $this->calculateEventRisk($sanitizedData);

            // Prepare permit data
            $permitData = $this->preparePermitData($sanitizedData, $permitNumber, $fees, $riskLevel);

            // Insert permit record
            $permitId = $this->insertPermitRecord($permitData);

            // Log the creation
            $this->logPermitCreation($permitId, $sanitizedData);

            $this->db->commit();

            return [
                'success' => true,
                'permit_id' => $permitId,
                'permit_number' => $permitNumber,
                'risk_level' => $riskLevel,
                'total_fees' => $fees['total']
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError('Permit creation failed', ['error' => $e->getMessage(), 'data' => $data]);
            throw new RuntimeException('Failed to create permit application: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize permit application data
     */
    private function sanitizePermitApplicationData(array $data): array {
        $sanitized = [];

        // Sanitize text fields
        $textFields = [
            'event_name', 'event_description', 'venue_name', 'venue_address',
            'venue_city', 'venue_state_province', 'venue_postal_code',
            'organizer_name', 'organizer_address', 'emergency_contact_name'
        ];

        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $this->sanitizeString($data[$field]);
            }
        }

        // Sanitize email fields
        $emailFields = ['organizer_email', 'emergency_contact_email'];
        foreach ($emailFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = filter_var($data[$field], FILTER_SANITIZE_EMAIL);
            }
        }

        // Sanitize phone fields
        $phoneFields = ['organizer_phone', 'emergency_contact_phone'];
        foreach ($phoneFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $this->sanitizePhoneNumber($data[$field]);
            }
        }

        // Copy numeric and date fields as-is (will be validated separately)
        $numericFields = ['applicant_id', 'expected_attendance', 'created_by'];
        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = (int) $data[$field];
            }
        }

        $dateFields = ['event_date', 'application_date'];
        foreach ($dateFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field]; // Will be validated as date
            }
        }

        $timeFields = ['event_start_time', 'event_end_time'];
        foreach ($timeFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field]; // Will be validated as time
            }
        }

        // Copy enum fields
        $enumFields = ['event_type', 'event_category'];
        foreach ($enumFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field];
            }
        }

        // Handle arrays and complex data
        if (isset($data['application_documents']) && is_array($data['application_documents'])) {
            $sanitized['application_documents'] = $data['application_documents'];
        }

        if (isset($data['venue_coordinates'])) {
            $sanitized['venue_coordinates'] = $data['venue_coordinates'];
        }

        return $sanitized;
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString(string $input): string {
        return trim(strip_tags($input));
    }

    /**
     * Sanitize phone number
     */
    private function sanitizePhoneNumber(string $phone): string {
        return preg_replace('/[^\d\s\-\+\(\)\.]/', '', $phone);
    }

    /**
     * Generate unique permit number
     */
    private function generateUniquePermitNumber(): string {
        do {
            $permitNumber = $this->generatePermitNumber();
            $exists = $this->db->query(
                "SELECT id FROM event_permits WHERE permit_number = ?",
                [$permitNumber]
            )->fetch(PDO::FETCH_ASSOC);
        } while ($exists);

        return $permitNumber;
    }

    /**
     * Calculate application fees
     */
    private function calculateApplicationFees(array $data): array {
        $baseFees = $this->config['default_fees'];
        $eventType = $data['event_type'] ?? 'private';
        $attendance = $data['expected_attendance'] ?? 0;

        // Adjust fees based on event type and attendance
        $multiplier = 1.0;
        if ($attendance > 500) {
            $multiplier = 1.5;
        } elseif ($attendance > 1000) {
            $multiplier = 2.0;
        }

        if (in_array($eventType, ['concert', 'festival', 'sports'])) {
            $multiplier *= 1.2;
        }

        return [
            'application_fee' => $baseFees['application_fee'] * $multiplier,
            'permit_fee' => $baseFees['permit_fee'] * $multiplier,
            'insurance_fee' => $baseFees['insurance_fee'] * $multiplier,
            'total' => ($baseFees['application_fee'] + $baseFees['permit_fee'] + $baseFees['insurance_fee']) * $multiplier
        ];
    }

    /**
     * Prepare permit data for insertion
     */
    private function preparePermitData(array $data, string $permitNumber, array $fees, string $riskLevel): array {
        return [
            'permit_number' => $permitNumber,
            'applicant_id' => $data['applicant_id'] ?? null,
            'event_name' => $data['event_name'],
            'event_description' => $data['event_description'] ?? null,
            'event_type' => $data['event_type'] ?? 'private',
            'event_category' => $data['event_category'] ?? 'private',
            'event_date' => $data['event_date'],
            'event_start_time' => $data['event_start_time'],
            'event_end_time' => $data['event_end_time'],
            'expected_attendance' => $data['expected_attendance'] ?? null,
            'venue_name' => $data['venue_name'],
            'venue_address' => $data['venue_address'],
            'venue_city' => $data['venue_city'],
            'venue_state_province' => $data['venue_state_province'] ?? null,
            'venue_postal_code' => $data['venue_postal_code'] ?? null,
            'venue_coordinates' => $data['venue_coordinates'] ?? null,
            'organizer_name' => $data['organizer_name'],
            'organizer_email' => $data['organizer_email'],
            'organizer_phone' => $data['organizer_phone'],
            'organizer_address' => $data['organizer_address'] ?? null,
            'application_date' => $data['application_date'],
            'application_fee' => $fees['application_fee'],
            'permit_fee' => $fees['permit_fee'],
            'insurance_fee' => $fees['insurance_fee'],
            'total_amount' => $fees['total'],
            'insurance_required' => $this->checkInsuranceRequirements($riskLevel),
            'risk_level' => $riskLevel,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'emergency_contact_email' => $data['emergency_contact_email'] ?? null,
            'application_documents' => json_encode($data['application_documents'] ?? []),
            'created_by' => $data['created_by']
        ];
    }

    /**
     * Insert permit record into database
     */
    private function insertPermitRecord(array $data): int {
        $sql = "INSERT INTO event_permits (
            permit_number, applicant_id, event_name, event_description,
            event_type, event_category, event_date, event_start_time,
            event_end_time, expected_attendance, venue_name, venue_address,
            venue_city, venue_state_province, venue_postal_code, venue_coordinates,
            organizer_name, organizer_email, organizer_phone, organizer_address,
            application_date, application_fee, permit_fee, insurance_fee,
            total_amount, insurance_required, risk_level, emergency_contact_name,
            emergency_contact_phone, emergency_contact_email, application_documents,
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['permit_number'], $data['applicant_id'], $data['event_name'],
            $data['event_description'], $data['event_type'], $data['event_category'],
            $data['event_date'], $data['event_start_time'], $data['event_end_time'],
            $data['expected_attendance'], $data['venue_name'], $data['venue_address'],
            $data['venue_city'], $data['venue_state_province'], $data['venue_postal_code'],
            $data['venue_coordinates'], $data['organizer_name'], $data['organizer_email'],
            $data['organizer_phone'], $data['organizer_address'], $data['application_date'],
            $data['application_fee'], $data['permit_fee'], $data['insurance_fee'],
            $data['total_amount'], $data['insurance_required'], $data['risk_level'],
            $data['emergency_contact_name'], $data['emergency_contact_phone'],
            $data['emergency_contact_email'], $data['application_documents'],
            $data['created_by']
        ];

        return $this->db->insert($sql, $params);
    }

    /**
     * Log permit creation
     */
    private function logPermitCreation(int $permitId, array $data): void {
        // Implementation would integrate with AuditLogger
        // This is a placeholder for audit logging
        error_log("Permit created: ID {$permitId}, Event: {$data['event_name']}");
    }

    /**
     * Log errors
     */
    private function logError(string $message, array $context = []): void {
        error_log($message . ': ' . json_encode($context));
    }

    public function updatePermitStatus($permitId, $status, $data = []) {
        try {
            $updateFields = ['permit_status = ?'];
            $params = [$status];

            if ($status === 'approved' && isset($data['approval_date'])) {
                $updateFields[] = 'approval_date = ?';
                $params[] = $data['approval_date'];

                if (isset($data['expiry_date'])) {
                    $updateFields[] = 'expiry_date = ?';
                    $params[] = $data['expiry_date'];
                }
            }

            if ($status === 'completed' && isset($data['actual_attendance'])) {
                $updateFields[] = 'actual_attendance = ?';
                $params[] = $data['actual_attendance'];
            }

            if (isset($data['reviewer_id'])) {
                $updateFields[] = 'reviewer_id = ?';
                $params[] = $data['reviewer_id'];
            }

            if (isset($data['review_date'])) {
                $updateFields[] = 'review_date = ?';
                $params[] = $data['review_date'];
            }

            if (isset($data['review_notes'])) {
                $updateFields[] = 'review_notes = ?';
                $params[] = $data['review_notes'];
            }

            $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';

            $sql = "UPDATE event_permits SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $params[] = $permitId;

            $this->db->query($sql, $params);

            return [
                'success' => true,
                'message' => 'Permit status updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function assessEventRisk($permitId, $data) {
        try {
            $this->validateRiskAssessmentData($data);

            $sql = "UPDATE event_permits SET
                    risk_level = ?,
                    risk_assessment_date = ?,
                    risk_assessment_notes = ?,
                    approval_conditions = ?,
                    reviewer_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $this->db->query($sql, [
                $data['risk_level'], $data['assessment_date'],
                $data['assessment_notes'] ?? null, json_encode($data['approval_conditions'] ?? []),
                $data['reviewer_id'], $permitId
            ]);

            return [
                'success' => true,
                'message' => 'Risk assessment completed successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createPermitRequirement($data) {
        try {
            $this->validatePermitRequirementData($data);
            $requirementCode = $this->generateRequirementCode();

            $sql = "INSERT INTO event_permit_requirements (
                requirement_code, event_type, requirement_name,
                requirement_description, requirement_category, minimum_attendance,
                risk_level_required, venue_type_required, is_mandatory,
                is_conditional, condition_description, documents_required,
                verification_method, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $requirementId = $this->db->insert($sql, [
                $requirementCode, $data['event_type'], $data['requirement_name'],
                $data['requirement_description'] ?? null, $data['requirement_category'] ?? 'other',
                $data['minimum_attendance'] ?? 0, $data['risk_level_required'] ?? 'all',
                $data['venue_type_required'] ?? null, $data['is_mandatory'] ?? true,
                $data['is_conditional'] ?? false, $data['condition_description'] ?? null,
                json_encode($data['documents_required'] ?? []), $data['verification_method'] ?? 'document_review',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'requirement_id' => $requirementId,
                'requirement_code' => $requirementCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function scheduleInspection($data) {
        try {
            $this->validateInspectionData($data);
            $inspectionCode = $this->generateInspectionCode();

            $sql = "INSERT INTO event_permit_inspections (
                inspection_code, permit_id, inspection_type, inspection_date,
                inspection_time, inspector_name, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $inspectionId = $this->db->insert($sql, [
                $inspectionCode, $data['permit_id'], $data['inspection_type'] ?? 'pre_event',
                $data['inspection_date'], $data['inspection_time'] ?? null,
                $data['inspector_name'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'inspection_id' => $inspectionId,
                'inspection_code' => $inspectionCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recordInspectionResults($inspectionId, $data) {
        try {
            $this->validateInspectionResultsData($data);

            $sql = "UPDATE event_permit_inspections SET
                    inspection_status = 'completed',
                    compliance_status = ?,
                    inspection_findings = ?,
                    violations_found = ?,
                    corrective_actions_required = ?,
                    follow_up_required = ?,
                    follow_up_date = ?,
                    follow_up_notes = ?,
                    inspection_report = ?,
                    photos_evidence = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $this->db->query($sql, [
                $data['compliance_status'], $data['inspection_findings'] ?? null,
                json_encode($data['violations_found'] ?? []), json_encode($data['corrective_actions_required'] ?? []),
                $data['follow_up_required'] ?? false, $data['follow_up_date'] ?? null,
                $data['follow_up_notes'] ?? null, $data['inspection_report'] ?? null,
                json_encode($data['photos_evidence'] ?? []), $inspectionId
            ]);

            return [
                'success' => true,
                'message' => 'Inspection results recorded successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createPublicNotification($data) {
        try {
            $this->validateNotificationData($data);
            $notificationCode = $this->generateNotificationCode();

            $sql = "INSERT INTO event_public_notifications (
                notification_code, permit_id, notification_type, notification_date,
                publication_date, publication_method, publication_name, publication_reference,
                notification_title, notification_content, notification_summary,
                coverage_area, estimated_readership, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $notificationId = $this->db->insert($sql, [
                $notificationCode, $data['permit_id'], $data['notification_type'] ?? 'initial',
                $data['notification_date'], $data['publication_date'] ?? null,
                $data['publication_method'], $data['publication_name'] ?? null,
                $data['publication_reference'] ?? null, $data['notification_title'],
                $data['notification_content'], $data['notification_summary'] ?? null,
                $data['coverage_area'] ?? null, $data['estimated_readership'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'notification_id' => $notificationId,
                'notification_code' => $notificationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitPermitAppeal($data) {
        try {
            $this->validateAppealData($data);
            $appealCode = $this->generateAppealCode();

            $sql = "INSERT INTO event_permit_appeals (
                appeal_code, permit_id, appeal_type, appeal_date,
                appeal_description, appeal_grounds, supporting_evidence, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $appealId = $this->db->insert($sql, [
                $appealCode, $data['permit_id'], $data['appeal_type'] ?? 'permit_denial',
                $data['appeal_date'], $data['appeal_description'],
                $data['appeal_grounds'] ?? null, json_encode($data['supporting_evidence'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'appeal_id' => $appealId,
                'appeal_code' => $appealCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recordPermitViolation($data) {
        try {
            $this->validateViolationData($data);
            $violationCode = $this->generateViolationCode();

            $sql = "INSERT INTO event_permit_violations (
                violation_code, permit_id, inspection_id, violation_type,
                violation_description, severity_level, identified_date,
                correction_deadline, corrective_actions_required, identified_by, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $violationId = $this->db->insert($sql, [
                $violationCode, $data['permit_id'], $data['inspection_id'] ?? null,
                $data['violation_type'] ?? 'other', $data['violation_description'],
                $data['severity_level'] ?? 'minor', $data['identified_date'],
                $data['correction_deadline'] ?? null, $data['corrective_actions_required'] ?? null,
                $data['identified_by'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'violation_id' => $violationId,
                'violation_code' => $violationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Validation Methods
    private function validatePermitApplicationData($data) {
        if (empty($data['event_name'])) {
            throw new Exception('Event name is required');
        }
        if (empty($data['event_date'])) {
            throw new Exception('Event date is required');
        }
        if (empty($data['event_start_time'])) {
            throw new Exception('Event start time is required');
        }
        if (empty($data['event_end_time'])) {
            throw new Exception('Event end time is required');
        }
        if (empty($data['venue_name'])) {
            throw new Exception('Venue name is required');
        }
        if (empty($data['venue_address'])) {
            throw new Exception('Venue address is required');
        }
        if (empty($data['venue_city'])) {
            throw new Exception('Venue city is required');
        }
        if (empty($data['organizer_name'])) {
            throw new Exception('Organizer name is required');
        }
        if (empty($data['organizer_email'])) {
            throw new Exception('Organizer email is required');
        }
        if (empty($data['organizer_phone'])) {
            throw new Exception('Organizer phone is required');
        }
        if (empty($data['application_date'])) {
            throw new Exception('Application date is required');
        }
    }

    private function validateRiskAssessmentData($data) {
        if (empty($data['risk_level'])) {
            throw new Exception('Risk level is required');
        }
        if (empty($data['assessment_date'])) {
            throw new Exception('Assessment date is required');
        }
        if (empty($data['reviewer_id'])) {
            throw new Exception('Reviewer ID is required');
        }
    }

    private function validatePermitRequirementData($data) {
        if (empty($data['event_type'])) {
            throw new Exception('Event type is required');
        }
        if (empty($data['requirement_name'])) {
            throw new Exception('Requirement name is required');
        }
    }

    private function validateInspectionData($data) {
        if (empty($data['permit_id'])) {
            throw new Exception('Permit ID is required');
        }
        if (empty($data['inspection_date'])) {
            throw new Exception('Inspection date is required');
        }
    }

    private function validateInspectionResultsData($data) {
        if (empty($data['compliance_status'])) {
            throw new Exception('Compliance status is required');
        }
    }

    private function validateNotificationData($data) {
        if (empty($data['permit_id'])) {
            throw new Exception('Permit ID is required');
        }
        if (empty($data['notification_date'])) {
            throw new Exception('Notification date is required');
        }
        if (empty($data['publication_method'])) {
            throw new Exception('Publication method is required');
        }
        if (empty($data['notification_title'])) {
            throw new Exception('Notification title is required');
        }
        if (empty($data['notification_content'])) {
            throw new Exception('Notification content is required');
        }
    }

    private function validateAppealData($data) {
        if (empty($data['permit_id'])) {
            throw new Exception('Permit ID is required');
        }
        if (empty($data['appeal_date'])) {
            throw new Exception('Appeal date is required');
        }
        if (empty($data['appeal_description'])) {
            throw new Exception('Appeal description is required');
        }
    }

    private function validateViolationData($data) {
        if (empty($data['permit_id'])) {
            throw new Exception('Permit ID is required');
        }
        if (empty($data['violation_description'])) {
            throw new Exception('Violation description is required');
        }
        if (empty($data['identified_date'])) {
            throw new Exception('Identified date is required');
        }
    }

    // Code Generation Methods
    private function generatePermitNumber() {
        return 'EVT-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function generateRequirementCode() {
        return 'REQ-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateInspectionCode() {
        return 'INSP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateNotificationCode() {
        return 'NOTIF-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateAppealCode() {
        return 'APPEAL-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateViolationCode() {
        return 'VIOL-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Additional API Methods
    public function getPermitDetails($permitNumber) {
        try {
            $sql = "SELECT * FROM event_permits WHERE permit_number = ?";
            $permit = $this->db->query($sql, [$permitNumber])->fetch(PDO::FETCH_ASSOC);

            if ($permit) {
                $permit['application_documents'] = json_decode($permit['application_documents'], true);
                $permit['insurance_documents'] = json_decode($permit['insurance_documents'], true);
                $permit['risk_assessment_documents'] = json_decode($permit['risk_assessment_documents'], true);
                $permit['approval_conditions'] = json_decode($permit['approval_conditions'], true);
            }

            return $permit;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPermitsByStatus($status, $limit = 100) {
        try {
            $sql = "SELECT * FROM event_permits WHERE permit_status = ? ORDER BY application_date DESC LIMIT ?";
            return $this->db->query($sql, [$status, $limit])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getUpcomingEvents($daysAhead = 30) {
        try {
            $sql = "SELECT * FROM event_permits
                    WHERE permit_status = 'approved'
                    AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY event_date ASC, event_start_time ASC";

            return $this->db->query($sql, [$daysAhead])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPermitRequirements($eventType, $attendance = 0, $riskLevel = 'low') {
        try {
            $sql = "SELECT * FROM event_permit_requirements
                    WHERE event_type = ?
                    AND requirement_status = 'active'
                    AND (minimum_attendance <= ? OR minimum_attendance = 0)
                    AND (risk_level_required = ? OR risk_level_required = 'all')
                    ORDER BY is_mandatory DESC, requirement_category ASC";

            return $this->db->query($sql, [$eventType, $attendance, $riskLevel])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPermitInspections($permitId) {
        try {
            $sql = "SELECT * FROM event_permit_inspections
                    WHERE permit_id = ?
                    ORDER BY inspection_date DESC";

            return $this->db->query($sql, [$permitId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPermitViolations($permitId) {
        try {
            $sql = "SELECT * FROM event_permit_violations
                    WHERE permit_id = ?
                    ORDER BY identified_date DESC";

            return $this->db->query($sql, [$permitId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPublicNotifications($permitId) {
        try {
            $sql = "SELECT * FROM event_public_notifications
                    WHERE permit_id = ?
                    ORDER BY notification_date DESC";

            return $this->db->query($sql, [$permitId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPermitAppeals($permitId) {
        try {
            $sql = "SELECT * FROM event_permit_appeals
                    WHERE permit_id = ?
                    ORDER BY appeal_date DESC";

            return $this->db->query($sql, [$permitId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function searchPermits($filters = []) {
        try {
            $sql = "SELECT * FROM event_permits WHERE 1=1";
            $params = [];

            if (!empty($filters['event_type'])) {
                $sql .= " AND event_type = ?";
                $params[] = $filters['event_type'];
            }

            if (!empty($filters['permit_status'])) {
                $sql .= " AND permit_status = ?";
                $params[] = $filters['permit_status'];
            }

            if (!empty($filters['venue_city'])) {
                $sql .= " AND venue_city = ?";
                $params[] = $filters['venue_city'];
            }

            if (!empty($filters['organizer_name'])) {
                $sql .= " AND organizer_name LIKE ?";
                $params[] = '%' . $filters['organizer_name'] . '%';
            }

            if (!empty($filters['event_date_from'])) {
                $sql .= " AND event_date >= ?";
                $params[] = $filters['event_date_from'];
            }

            if (!empty($filters['event_date_to'])) {
                $sql .= " AND event_date <= ?";
                $params[] = $filters['event_date_to'];
            }

            $sql .= " ORDER BY application_date DESC LIMIT 100";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [
            'database_table_prefix' => 'event_',
            'upload_path' => __DIR__ . '/uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_email' => 'admin@example.com',
            'currency' => 'USD',
            'default_fees' => [
                'application_fee' => 50.00,
                'permit_fee' => 100.00,
                'insurance_fee' => 25.00
            ],
            'risk_levels' => [
                'low' => ['max_attendance' => 100, 'insurance_required' => false],
                'medium' => ['max_attendance' => 500, 'insurance_required' => true],
                'high' => ['max_attendance' => 1000, 'insurance_required' => true],
                'critical' => ['max_attendance' => 10000, 'insurance_required' => true]
            ],
            'notification_period_days' => 14,
            'appeal_period_days' => 30
        ];
    }

    // Utility Methods
    public function formatCurrency($amount) {
        return number_format($amount, 2, '.', ',') . ' ' . $this->config['currency'];
    }

    public function validateFileUpload($file) {
        if ($file['size'] > $this->config['max_file_size']) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed size'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['allowed_file_types'])) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }

        return ['valid' => true];
    }

    public function sendNotification($type, $recipient, $data) {
        // Implementation would integrate with NotificationManager
        // This is a placeholder for the notification system
        return true;
    }

    public function calculateEventRisk($eventData) {
        $attendance = $eventData['expected_attendance'] ?? 0;
        $eventType = $eventData['event_type'] ?? 'private';

        if ($attendance <= 100) {
            return 'low';
        } elseif ($attendance <= 500) {
            return 'medium';
        } elseif ($attendance <= 1000) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    public function checkInsuranceRequirements($riskLevel) {
        $riskConfig = $this->config['risk_levels'][$riskLevel] ?? [];
        return $riskConfig['insurance_required'] ?? true;
    }
}

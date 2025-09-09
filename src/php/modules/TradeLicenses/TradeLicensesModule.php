<?php
/**
 * Trade Licenses Module
 * Handles professional qualification verification, certification tracking, and continuing education
 */

require_once __DIR__ . '/../ServiceModule.php';

class TradeLicensesModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Trade Licenses Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive trade licensing system for professional qualification verification, certification tracking, continuing education monitoring, and disciplinary action management',
            'dependencies' => ['IdentityServices', 'FinancialManagement', 'RecordsManagement'],
            'permissions' => [
                'trade.view' => 'View trade license information',
                'trade.apply' => 'Apply for trade licenses',
                'trade.manage' => 'Manage trade license applications',
                'trade.admin' => 'Administrative functions for trade licensing'
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
            CREATE TABLE IF NOT EXISTS trade_licenses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                license_number VARCHAR(20) UNIQUE NOT NULL,
                applicant_id INT,

                -- Applicant Information
                applicant_name VARCHAR(200) NOT NULL,
                applicant_email VARCHAR(255),
                applicant_phone VARCHAR(20),
                date_of_birth DATE,
                address TEXT,

                -- Trade Information
                trade_category VARCHAR(100) NOT NULL,
                trade_specialty VARCHAR(100),
                license_type ENUM('full', 'limited', 'temporary', 'apprentice', 'master') DEFAULT 'full',
                license_class VARCHAR(50),

                -- Qualification Details
                qualification_level ENUM('certificate', 'diploma', 'degree', 'master', 'other') DEFAULT 'certificate',
                institution_name VARCHAR(200),
                qualification_date DATE,
                experience_years INT DEFAULT 0,

                -- License Status
                license_status ENUM('application_pending', 'under_review', 'approved', 'rejected', 'active', 'suspended', 'revoked', 'expired', 'renewal_pending') DEFAULT 'application_pending',
                application_date DATE NOT NULL,
                approval_date DATE,
                expiry_date DATE,
                suspension_date DATE,
                suspension_reason TEXT,

                -- Financial Information
                application_fee DECIMAL(8,2) DEFAULT 0,
                license_fee DECIMAL(8,2) DEFAULT 0,
                renewal_fee DECIMAL(8,2) DEFAULT 0,
                payment_status ENUM('pending', 'paid', 'overdue', 'waived') DEFAULT 'pending',

                -- Documents
                qualification_documents TEXT, -- JSON array of document paths
                identity_documents TEXT, -- JSON array of document paths
                experience_documents TEXT, -- JSON array of document paths

                -- Review Information
                reviewer_id INT,
                review_date DATE,
                review_notes TEXT,
                approval_conditions TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_license_number (license_number),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_trade_category (trade_category),
                INDEX idx_license_status (license_status),
                INDEX idx_expiry_date (expiry_date),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS trade_qualifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                qualification_code VARCHAR(20) UNIQUE NOT NULL,
                trade_category VARCHAR(100) NOT NULL,

                -- Qualification Details
                qualification_name VARCHAR(200) NOT NULL,
                qualification_description TEXT,
                qualification_level ENUM('entry', 'intermediate', 'advanced', 'master') DEFAULT 'entry',

                -- Requirements
                minimum_age INT DEFAULT 18,
                minimum_education VARCHAR(100),
                required_experience_years INT DEFAULT 0,
                required_training_hours INT DEFAULT 0,

                -- Prerequisites
                prerequisite_qualifications TEXT, -- JSON array of qualification codes
                required_exams TEXT, -- JSON array of exam codes

                -- Validity
                validity_years INT DEFAULT 5,
                renewal_requirements TEXT, -- JSON array of renewal requirements

                -- Status
                qualification_status ENUM('active', 'inactive', 'deprecated') DEFAULT 'active',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_qualification_code (qualification_code),
                INDEX idx_trade_category (trade_category),
                INDEX idx_qualification_level (qualification_level),
                INDEX idx_qualification_status (qualification_status)
            );

            CREATE TABLE IF NOT EXISTS trade_exams (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_code VARCHAR(20) UNIQUE NOT NULL,
                exam_name VARCHAR(200) NOT NULL,

                -- Exam Details
                trade_category VARCHAR(100) NOT NULL,
                exam_type ENUM('written', 'practical', 'oral', 'combined') DEFAULT 'written',
                exam_level ENUM('entry', 'intermediate', 'advanced', 'master') DEFAULT 'entry',

                -- Scheduling
                exam_duration_minutes INT DEFAULT 120,
                passing_score DECIMAL(5,2) DEFAULT 70.00,
                max_attempts INT DEFAULT 3,

                -- Fees
                exam_fee DECIMAL(8,2) DEFAULT 0,
                retake_fee DECIMAL(8,2) DEFAULT 0,

                -- Status
                exam_status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_exam_code (exam_code),
                INDEX idx_trade_category (trade_category),
                INDEX idx_exam_type (exam_type),
                INDEX idx_exam_status (exam_status)
            );

            CREATE TABLE IF NOT EXISTS trade_exam_results (
                id INT PRIMARY KEY AUTO_INCREMENT,
                result_code VARCHAR(20) UNIQUE NOT NULL,
                license_id INT NOT NULL,
                exam_id INT NOT NULL,

                -- Exam Details
                exam_date DATE NOT NULL,
                exam_score DECIMAL(5,2),
                passing_score DECIMAL(5,2),
                result_status ENUM('pass', 'fail', 'absent', 'cancelled') DEFAULT 'fail',

                -- Attempt Information
                attempt_number INT DEFAULT 1,
                max_attempts INT DEFAULT 3,

                -- Examiner Details
                examiner_name VARCHAR(100),
                examiner_notes TEXT,

                -- Certificate
                certificate_issued BOOLEAN DEFAULT FALSE,
                certificate_number VARCHAR(30),
                certificate_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (license_id) REFERENCES trade_licenses(id) ON DELETE CASCADE,
                FOREIGN KEY (exam_id) REFERENCES trade_exams(id),
                INDEX idx_result_code (result_code),
                INDEX idx_license_id (license_id),
                INDEX idx_exam_id (exam_id),
                INDEX idx_result_status (result_status),
                INDEX idx_exam_date (exam_date)
            );

            CREATE TABLE IF NOT EXISTS trade_continuing_education (
                id INT PRIMARY KEY AUTO_INCREMENT,
                education_code VARCHAR(20) UNIQUE NOT NULL,
                license_id INT NOT NULL,

                -- Education Details
                course_name VARCHAR(200) NOT NULL,
                course_provider VARCHAR(200) NOT NULL,
                course_type ENUM('workshop', 'seminar', 'conference', 'online_course', 'certification', 'other') DEFAULT 'workshop',

                -- Course Information
                course_date DATE NOT NULL,
                completion_date DATE,
                course_hours DECIMAL(5,2) NOT NULL,
                course_description TEXT,

                -- Verification
                verification_method ENUM('certificate', 'attendance_record', 'provider_confirmation', 'exam') DEFAULT 'certificate',
                verification_document VARCHAR(255),

                -- Status
                education_status ENUM('completed', 'in_progress', 'cancelled', 'rejected') DEFAULT 'completed',
                approval_date DATE,
                approved_by INT,

                -- Renewal Credits
                renewal_credits DECIMAL(5,2) DEFAULT 0,
                credit_expiry_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (license_id) REFERENCES trade_licenses(id) ON DELETE CASCADE,
                INDEX idx_education_code (education_code),
                INDEX idx_license_id (license_id),
                INDEX idx_course_type (course_type),
                INDEX idx_education_status (education_status),
                INDEX idx_course_date (course_date)
            );

            CREATE TABLE IF NOT EXISTS trade_disciplinary_actions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                action_code VARCHAR(20) UNIQUE NOT NULL,
                license_id INT NOT NULL,

                -- Action Details
                action_type ENUM('warning', 'fine', 'suspension', 'revocation', 'license_modification', 'other') NOT NULL,
                action_date DATE NOT NULL,
                effective_date DATE,

                -- Violation Information
                violation_description TEXT NOT NULL,
                violation_code VARCHAR(20),
                severity_level ENUM('minor', 'moderate', 'major', 'critical') DEFAULT 'moderate',

                -- Action Details
                suspension_period_days INT,
                fine_amount DECIMAL(8,2) DEFAULT 0,
                action_description TEXT,

                -- Resolution
                resolution_date DATE,
                resolution_description TEXT,
                appeal_status ENUM('none', 'pending', 'approved', 'denied') DEFAULT 'none',

                -- Officer Information
                action_officer VARCHAR(100),
                review_officer VARCHAR(100),

                -- Status
                action_status ENUM('active', 'resolved', 'appealed', 'overturned') DEFAULT 'active',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (license_id) REFERENCES trade_licenses(id) ON DELETE CASCADE,
                INDEX idx_action_code (action_code),
                INDEX idx_license_id (license_id),
                INDEX idx_action_type (action_type),
                INDEX idx_severity_level (severity_level),
                INDEX idx_action_status (action_status),
                INDEX idx_action_date (action_date)
            );

            CREATE TABLE IF NOT EXISTS trade_renewals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                renewal_code VARCHAR(20) UNIQUE NOT NULL,
                license_id INT NOT NULL,

                -- Renewal Details
                renewal_period_start DATE NOT NULL,
                renewal_period_end DATE NOT NULL,
                renewal_due_date DATE NOT NULL,
                renewal_date DATE,

                -- Requirements Check
                continuing_education_completed BOOLEAN DEFAULT FALSE,
                required_credits_earned DECIMAL(5,2) DEFAULT 0,
                required_credits_needed DECIMAL(5,2) DEFAULT 0,

                -- Financial Information
                renewal_fee DECIMAL(8,2) DEFAULT 0,
                late_fee DECIMAL(8,2) DEFAULT 0,
                total_amount DECIMAL(8,2) DEFAULT 0,
                payment_status ENUM('pending', 'paid', 'overdue', 'waived') DEFAULT 'pending',

                -- Status
                renewal_status ENUM('pending', 'approved', 'rejected', 'expired', 'cancelled') DEFAULT 'pending',
                approval_date DATE,
                rejection_reason TEXT,

                -- Notifications
                reminder_sent BOOLEAN DEFAULT FALSE,
                reminder_date DATE,
                final_notice_sent BOOLEAN DEFAULT FALSE,
                final_notice_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (license_id) REFERENCES trade_licenses(id) ON DELETE CASCADE,
                INDEX idx_renewal_code (renewal_code),
                INDEX idx_license_id (license_id),
                INDEX idx_renewal_status (renewal_status),
                INDEX idx_renewal_due_date (renewal_due_date),
                INDEX idx_renewal_date (renewal_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup license application workflow
        $licenseApplicationWorkflow = [
            'name' => 'Trade License Application Workflow',
            'description' => 'Complete workflow for trade license applications and approvals',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'license_application_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'user_task',
                    'assignee' => 'document_verifier',
                    'form' => 'document_verification_form'
                ],
                [
                    'name' => 'Qualification Assessment',
                    'type' => 'user_task',
                    'assignee' => 'qualification_assessor',
                    'form' => 'qualification_assessment_form'
                ],
                [
                    'name' => 'Background Check',
                    'type' => 'service_task',
                    'service' => 'background_check_service'
                ],
                [
                    'name' => 'Exam Scheduling',
                    'type' => 'user_task',
                    'assignee' => 'exam_coordinator',
                    'form' => 'exam_scheduling_form'
                ],
                [
                    'name' => 'Exam Administration',
                    'type' => 'user_task',
                    'assignee' => 'exam_officer',
                    'form' => 'exam_administration_form'
                ],
                [
                    'name' => 'Final Review',
                    'type' => 'user_task',
                    'assignee' => 'licensing_officer',
                    'form' => 'final_review_form'
                ],
                [
                    'name' => 'License Issuance',
                    'type' => 'service_task',
                    'service' => 'license_issuance_service'
                ],
                [
                    'name' => 'Notification',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'license_notification_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/trade_workflow.json', json_encode($licenseApplicationWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/applications',
            __DIR__ . '/uploads/certificates',
            __DIR__ . '/uploads/exam_results',
            __DIR__ . '/uploads/education_records',
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
            'trade_renewals',
            'trade_disciplinary_actions',
            'trade_continuing_education',
            'trade_exam_results',
            'trade_exams',
            'trade_qualifications',
            'trade_licenses'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createLicenseApplication($data) {
        try {
            $this->validateLicenseApplicationData($data);
            $licenseNumber = $this->generateLicenseNumber();

            $sql = "INSERT INTO trade_licenses (
                license_number, applicant_id, applicant_name, applicant_email,
                applicant_phone, date_of_birth, address, trade_category,
                trade_specialty, license_type, license_class, qualification_level,
                institution_name, qualification_date, experience_years,
                application_date, application_fee, qualification_documents,
                identity_documents, experience_documents, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $licenseId = $this->db->insert($sql, [
                $licenseNumber, $data['applicant_id'] ?? null, $data['applicant_name'],
                $data['applicant_email'] ?? null, $data['applicant_phone'] ?? null,
                $data['date_of_birth'] ?? null, $data['address'] ?? null,
                $data['trade_category'], $data['trade_specialty'] ?? null,
                $data['license_type'] ?? 'full', $data['license_class'] ?? null,
                $data['qualification_level'] ?? 'certificate', $data['institution_name'] ?? null,
                $data['qualification_date'] ?? null, $data['experience_years'] ?? 0,
                $data['application_date'], $data['application_fee'] ?? 0,
                json_encode($data['qualification_documents'] ?? []),
                json_encode($data['identity_documents'] ?? []),
                json_encode($data['experience_documents'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'license_id' => $licenseId,
                'license_number' => $licenseNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateLicenseStatus($licenseId, $status, $data = []) {
        try {
            $updateFields = ['license_status = ?'];
            $params = [$status];

            if ($status === 'approved' && isset($data['approval_date'])) {
                $updateFields[] = 'approval_date = ?';
                $params[] = $data['approval_date'];

                if (isset($data['expiry_date'])) {
                    $updateFields[] = 'expiry_date = ?';
                    $params[] = $data['expiry_date'];
                }
            }

            if ($status === 'suspended' && isset($data['suspension_date'])) {
                $updateFields[] = 'suspension_date = ?';
                $params[] = $data['suspension_date'];

                if (isset($data['suspension_reason'])) {
                    $updateFields[] = 'suspension_reason = ?';
                    $params[] = $data['suspension_reason'];
                }
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

            $sql = "UPDATE trade_licenses SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $params[] = $licenseId;

            $this->db->query($sql, $params);

            return [
                'success' => true,
                'message' => 'License status updated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createQualification($data) {
        try {
            $this->validateQualificationData($data);
            $qualificationCode = $this->generateQualificationCode();

            $sql = "INSERT INTO trade_qualifications (
                qualification_code, trade_category, qualification_name,
                qualification_description, qualification_level, minimum_age,
                minimum_education, required_experience_years, required_training_hours,
                prerequisite_qualifications, required_exams, validity_years,
                renewal_requirements, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $qualificationId = $this->db->insert($sql, [
                $qualificationCode, $data['trade_category'], $data['qualification_name'],
                $data['qualification_description'] ?? null, $data['qualification_level'] ?? 'entry',
                $data['minimum_age'] ?? 18, $data['minimum_education'] ?? null,
                $data['required_experience_years'] ?? 0, $data['required_training_hours'] ?? 0,
                json_encode($data['prerequisite_qualifications'] ?? []),
                json_encode($data['required_exams'] ?? []), $data['validity_years'] ?? 5,
                json_encode($data['renewal_requirements'] ?? []), $data['created_by']
            ]);

            return [
                'success' => true,
                'qualification_id' => $qualificationId,
                'qualification_code' => $qualificationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createExam($data) {
        try {
            $this->validateExamData($data);
            $examCode = $this->generateExamCode();

            $sql = "INSERT INTO trade_exams (
                exam_code, exam_name, trade_category, exam_type,
                exam_level, exam_duration_minutes, passing_score,
                max_attempts, exam_fee, retake_fee, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $examId = $this->db->insert($sql, [
                $examCode, $data['exam_name'], $data['trade_category'],
                $data['exam_type'] ?? 'written', $data['exam_level'] ?? 'entry',
                $data['exam_duration_minutes'] ?? 120, $data['passing_score'] ?? 70.00,
                $data['max_attempts'] ?? 3, $data['exam_fee'] ?? 0,
                $data['retake_fee'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'exam_id' => $examId,
                'exam_code' => $examCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recordExamResult($data) {
        try {
            $this->validateExamResultData($data);
            $resultCode = $this->generateResultCode();

            $sql = "INSERT INTO trade_exam_results (
                result_code, license_id, exam_id, exam_date, exam_score,
                passing_score, result_status, attempt_number, max_attempts,
                examiner_name, examiner_notes, certificate_issued,
                certificate_number, certificate_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $resultId = $this->db->insert($sql, [
                $resultCode, $data['license_id'], $data['exam_id'], $data['exam_date'],
                $data['exam_score'], $data['passing_score'], $data['result_status'],
                $data['attempt_number'] ?? 1, $data['max_attempts'] ?? 3,
                $data['examiner_name'] ?? null, $data['examiner_notes'] ?? null,
                $data['certificate_issued'] ?? false, $data['certificate_number'] ?? null,
                $data['certificate_date'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'result_id' => $resultId,
                'result_code' => $resultCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function addContinuingEducation($data) {
        try {
            $this->validateContinuingEducationData($data);
            $educationCode = $this->generateEducationCode();

            $sql = "INSERT INTO trade_continuing_education (
                education_code, license_id, course_name, course_provider,
                course_type, course_date, completion_date, course_hours,
                course_description, verification_method, verification_document,
                education_status, renewal_credits, credit_expiry_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $educationId = $this->db->insert($sql, [
                $educationCode, $data['license_id'], $data['course_name'],
                $data['course_provider'], $data['course_type'] ?? 'workshop',
                $data['course_date'], $data['completion_date'] ?? null,
                $data['course_hours'], $data['course_description'] ?? null,
                $data['verification_method'] ?? 'certificate',
                $data['verification_document'] ?? null, $data['education_status'] ?? 'completed',
                $data['renewal_credits'] ?? $data['course_hours'],
                $data['credit_expiry_date'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'education_id' => $educationId,
                'education_code' => $educationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createDisciplinaryAction($data) {
        try {
            $this->validateDisciplinaryActionData($data);
            $actionCode = $this->generateActionCode();

            $sql = "INSERT INTO trade_disciplinary_actions (
                action_code, license_id, action_type, action_date,
                effective_date, violation_description, violation_code,
                severity_level, suspension_period_days, fine_amount,
                action_description, action_officer, action_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $actionId = $this->db->insert($sql, [
                $actionCode, $data['license_id'], $data['action_type'],
                $data['action_date'], $data['effective_date'] ?? null,
                $data['violation_description'], $data['violation_code'] ?? null,
                $data['severity_level'] ?? 'moderate', $data['suspension_period_days'] ?? null,
                $data['fine_amount'] ?? 0, $data['action_description'] ?? null,
                $data['action_officer'] ?? null, $data['action_status'] ?? 'active',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'action_id' => $actionId,
                'action_code' => $actionCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createRenewalRecord($data) {
        try {
            $this->validateRenewalData($data);
            $renewalCode = $this->generateRenewalCode();

            $sql = "INSERT INTO trade_renewals (
                renewal_code, license_id, renewal_period_start, renewal_period_end,
                renewal_due_date, continuing_education_completed, required_credits_earned,
                required_credits_needed, renewal_fee, late_fee, total_amount,
                payment_status, renewal_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $renewalId = $this->db->insert($sql, [
                $renewalCode, $data['license_id'], $data['renewal_period_start'],
                $data['renewal_period_end'], $data['renewal_due_date'],
                $data['continuing_education_completed'] ?? false,
                $data['required_credits_earned'] ?? 0, $data['required_credits_needed'] ?? 0,
                $data['renewal_fee'] ?? 0, $data['late_fee'] ?? 0,
                $data['total_amount'] ?? 0, $data['payment_status'] ?? 'pending',
                $data['renewal_status'] ?? 'pending', $data['created_by']
            ]);

            return [
                'success' => true,
                'renewal_id' => $renewalId,
                'renewal_code' => $renewalCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Validation Methods
    private function validateLicenseApplicationData($data) {
        if (empty($data['applicant_name'])) {
            throw new Exception('Applicant name is required');
        }
        if (empty($data['trade_category'])) {
            throw new Exception('Trade category is required');
        }
        if (empty($data['application_date'])) {
            throw new Exception('Application date is required');
        }
    }

    private function validateQualificationData($data) {
        if (empty($data['trade_category'])) {
            throw new Exception('Trade category is required');
        }
        if (empty($data['qualification_name'])) {
            throw new Exception('Qualification name is required');
        }
    }

    private function validateExamData($data) {
        if (empty($data['exam_name'])) {
            throw new Exception('Exam name is required');
        }
        if (empty($data['trade_category'])) {
            throw new Exception('Trade category is required');
        }
    }

    private function validateExamResultData($data) {
        if (empty($data['license_id'])) {
            throw new Exception('License ID is required');
        }
        if (empty($data['exam_id'])) {
            throw new Exception('Exam ID is required');
        }
        if (empty($data['exam_date'])) {
            throw new Exception('Exam date is required');
        }
        if (!isset($data['exam_score'])) {
            throw new Exception('Exam score is required');
        }
    }

    private function validateContinuingEducationData($data) {
        if (empty($data['license_id'])) {
            throw new Exception('License ID is required');
        }
        if (empty($data['course_name'])) {
            throw new Exception('Course name is required');
        }
        if (empty($data['course_provider'])) {
            throw new Exception('Course provider is required');
        }
        if (empty($data['course_date'])) {
            throw new Exception('Course date is required');
        }
        if (empty($data['course_hours'])) {
            throw new Exception('Course hours is required');
        }
    }

    private function validateDisciplinaryActionData($data) {
        if (empty($data['license_id'])) {
            throw new Exception('License ID is required');
        }
        if (empty($data['action_type'])) {
            throw new Exception('Action type is required');
        }
        if (empty($data['action_date'])) {
            throw new Exception('Action date is required');
        }
        if (empty($data['violation_description'])) {
            throw new Exception('Violation description is required');
        }
    }

    private function validateRenewalData($data) {
        if (empty($data['license_id'])) {
            throw new Exception('License ID is required');
        }
        if (empty($data['renewal_period_start'])) {
            throw new Exception('Renewal period start date is required');
        }
        if (empty($data['renewal_period_end'])) {
            throw new Exception('Renewal period end date is required');
        }
        if (empty($data['renewal_due_date'])) {
            throw new Exception('Renewal due date is required');
        }
    }

    // Code Generation Methods
    private function generateLicenseNumber() {
        return 'TL-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function generateQualificationCode() {
        return 'QUAL-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateExamCode() {
        return 'EXAM-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateResultCode() {
        return 'RESULT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateEducationCode() {
        return 'EDU-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateActionCode() {
        return 'ACTION-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateRenewalCode() {
        return 'RENEW-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Additional API Methods
    public function getLicenseDetails($licenseNumber) {
        try {
            $sql = "SELECT * FROM trade_licenses WHERE license_number = ?";
            $license = $this->db->query($sql, [$licenseNumber])->fetch(PDO::FETCH_ASSOC);

            if ($license) {
                $license['qualification_documents'] = json_decode($license['qualification_documents'], true);
                $license['identity_documents'] = json_decode($license['identity_documents'], true);
                $license['experience_documents'] = json_decode($license['experience_documents'], true);
            }

            return $license;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getLicensesByStatus($status, $limit = 100) {
        try {
            $sql = "SELECT * FROM trade_licenses WHERE license_status = ? ORDER BY application_date DESC LIMIT ?";
            return $this->db->query($sql, [$status, $limit])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getExpiringLicenses($daysAhead = 30) {
        try {
            $sql = "SELECT * FROM trade_licenses
                    WHERE license_status = 'active'
                    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY expiry_date ASC";

            return $this->db->query($sql, [$daysAhead])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getQualificationsByTrade($tradeCategory) {
        try {
            $sql = "SELECT * FROM trade_qualifications
                    WHERE trade_category = ? AND qualification_status = 'active'
                    ORDER BY qualification_level ASC";

            return $this->db->query($sql, [$tradeCategory])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getExamResults($licenseId) {
        try {
            $sql = "SELECT ter.*, te.exam_name, te.exam_type
                    FROM trade_exam_results ter
                    JOIN trade_exams te ON ter.exam_id = te.id
                    WHERE ter.license_id = ?
                    ORDER BY ter.exam_date DESC";

            return $this->db->query($sql, [$licenseId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getContinuingEducation($licenseId) {
        try {
            $sql = "SELECT * FROM trade_continuing_education
                    WHERE license_id = ?
                    ORDER BY course_date DESC";

            return $this->db->query($sql, [$licenseId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getDisciplinaryHistory($licenseId) {
        try {
            $sql = "SELECT * FROM trade_disciplinary_actions
                    WHERE license_id = ?
                    ORDER BY action_date DESC";

            return $this->db->query($sql, [$licenseId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getRenewalHistory($licenseId) {
        try {
            $sql = "SELECT * FROM trade_renewals
                    WHERE license_id = ?
                    ORDER BY renewal_period_start DESC";

            return $this->db->query($sql, [$licenseId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function searchLicenses($filters = []) {
        try {
            $sql = "SELECT * FROM trade_licenses WHERE 1=1";
            $params = [];

            if (!empty($filters['trade_category'])) {
                $sql .= " AND trade_category = ?";
                $params[] = $filters['trade_category'];
            }

            if (!empty($filters['license_status'])) {
                $sql .= " AND license_status = ?";
                $params[] = $filters['license_status'];
            }

            if (!empty($filters['applicant_name'])) {
                $sql .= " AND applicant_name LIKE ?";
                $params[] = '%' . $filters['applicant_name'] . '%';
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
            'database_table_prefix' => 'trade_',
            'upload_path' => __DIR__ . '/uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_email' => 'admin@example.com',
            'currency' => 'USD',
            'default_fees' => [
                'application_fee' => 50.00,
                'license_fee' => 100.00,
                'renewal_fee' => 75.00,
                'exam_fee' => 25.00,
                'retake_fee' => 15.00
            ],
            'trade_categories' => [
                'electrical', 'plumbing', 'carpentry', 'masonry', 'painting',
                'roofing', 'hvac', 'landscaping', 'automotive', 'welding'
            ]
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
}

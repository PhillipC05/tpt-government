<?php
/**
 * Vital Records Module
 * Handles birth, death, and other vital record registrations
 */

require_once __DIR__ . '/../ServiceModule.php';

class VitalRecordsModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Vital Records Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive vital records management for births, deaths, and other life events',
            'dependencies' => ['IdentityServices', 'PaymentGateway'],
            'permissions' => [
                'vital_records.register_birth' => 'Register birth',
                'vital_records.register_death' => 'Register death',
                'vital_records.amend_record' => 'Amend vital record',
                'vital_records.view' => 'View vital records',
                'vital_records.certify' => 'Certify vital records',
                'vital_records.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS vital_records (
                id INT PRIMARY KEY AUTO_INCREMENT,
                record_number VARCHAR(20) UNIQUE NOT NULL,
                record_type ENUM('birth', 'death', 'stillbirth', 'marriage', 'divorce', 'adoption', 'name_change', 'gender_change') NOT NULL,
                status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'certified', 'amended') DEFAULT 'draft',

                -- Event Details
                event_date DATE NOT NULL,
                event_time TIME NULL,
                event_place VARCHAR(255) NOT NULL,
                registration_date DATE NOT NULL,
                registration_place VARCHAR(255) NOT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NULL,
                place_of_birth VARCHAR(255) NULL,
                gender ENUM('male', 'female', 'other', 'unknown') NULL,
                nationality VARCHAR(50),

                -- Parent/Guardian Information (for births)
                father_first_name VARCHAR(100),
                father_middle_name VARCHAR(100),
                father_last_name VARCHAR(100),
                father_date_of_birth DATE,
                father_nationality VARCHAR(50),
                father_occupation VARCHAR(100),

                mother_first_name VARCHAR(100),
                mother_middle_name VARCHAR(100),
                mother_last_name VARCHAR(100),
                mother_maiden_name VARCHAR(100),
                mother_date_of_birth DATE,
                mother_nationality VARCHAR(50),
                mother_occupation VARCHAR(100),

                -- Spouse Information (for marriages/divorces)
                spouse_first_name VARCHAR(100),
                spouse_middle_name VARCHAR(100),
                spouse_last_name VARCHAR(100),
                spouse_date_of_birth DATE,
                spouse_nationality VARCHAR(50),

                -- Death Information
                date_of_death DATE,
                place_of_death VARCHAR(255),
                cause_of_death TEXT,
                manner_of_death ENUM('natural', 'accident', 'suicide', 'homicide', 'undetermined'),
                attending_physician VARCHAR(100),
                funeral_home VARCHAR(100),

                -- Informant Details
                informant_name VARCHAR(200) NOT NULL,
                informant_relationship VARCHAR(50) NOT NULL,
                informant_address TEXT NOT NULL,
                informant_phone VARCHAR(20),
                informant_email VARCHAR(255),

                -- Medical Information
                birth_weight_grams INT,
                gestational_age_weeks INT,
                multiple_birth BOOLEAN DEFAULT FALSE,
                birth_order INT,
                congenital_anomalies TEXT,

                -- Legal Information
                court_order_number VARCHAR(50),
                court_order_date DATE,
                adoption_agency VARCHAR(100),

                -- Documents
                documents_submitted JSON,
                supporting_documents JSON,

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                certified_at TIMESTAMP NULL,
                amended_at TIMESTAMP NULL,

                -- Certificate Information
                certificate_number VARCHAR(20) UNIQUE NULL,
                certificate_issued_date DATE NULL,
                certificate_collected BOOLEAN DEFAULT FALSE,
                certificate_collected_date DATE NULL,

                -- Fees and Payment
                fee_amount DECIMAL(10,2) NOT NULL,
                payment_status ENUM('pending', 'paid', 'refunded', 'waived') DEFAULT 'pending',
                payment_reference VARCHAR(100),

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                rejection_reason TEXT,
                amendment_reason TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_record_number (record_number),
                INDEX idx_record_type (record_type),
                INDEX idx_status (status),
                INDEX idx_event_date (event_date),
                INDEX idx_registration_date (registration_date),
                INDEX idx_first_name (first_name),
                INDEX idx_last_name (last_name),
                INDEX idx_date_of_birth (date_of_birth),
                INDEX idx_submitted_at (submitted_at)
            );

            CREATE TABLE IF NOT EXISTS vital_record_amendments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                record_id INT NOT NULL,
                amendment_number VARCHAR(20) UNIQUE NOT NULL,
                amendment_type ENUM('correction', 'addition', 'deletion', 'update') NOT NULL,
                amendment_reason TEXT NOT NULL,
                previous_data JSON,
                new_data JSON,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

                -- Processing
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                rejection_reason TEXT,

                -- Supporting Documents
                supporting_documents JSON,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (record_id) REFERENCES vital_records(id) ON DELETE CASCADE,
                INDEX idx_record_id (record_id),
                INDEX idx_amendment_number (amendment_number),
                INDEX idx_status (status),
                INDEX idx_requested_at (requested_at)
            );

            CREATE TABLE IF NOT EXISTS vital_record_certificates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                record_id INT NOT NULL,
                certificate_type ENUM('birth', 'death', 'marriage', 'divorce', 'amended') NOT NULL,
                certificate_number VARCHAR(20) UNIQUE NOT NULL,
                issue_date DATE NOT NULL,
                expiry_date DATE NULL,
                status ENUM('active', 'expired', 'revoked', 'replaced') DEFAULT 'active',

                -- Request Information
                requested_by INT NOT NULL,
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                purpose_of_request TEXT,

                -- Delivery Information
                delivery_method ENUM('pickup', 'mail', 'electronic') DEFAULT 'pickup',
                delivery_address TEXT,
                tracking_number VARCHAR(50),

                -- Processing
                processed_at TIMESTAMP NULL,
                processed_by INT NULL,

                -- Fees
                fee_amount DECIMAL(10,2) NOT NULL,
                payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (record_id) REFERENCES vital_records(id) ON DELETE CASCADE,
                INDEX idx_record_id (record_id),
                INDEX idx_certificate_number (certificate_number),
                INDEX idx_certificate_type (certificate_type),
                INDEX idx_status (status),
                INDEX idx_requested_by (requested_by)
            );

            CREATE TABLE IF NOT EXISTS vital_record_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                record_id INT NULL,
                appointment_type ENUM('registration', 'amendment', 'certificate_collection', 'interview') NOT NULL,
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,
                status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                notes TEXT,

                -- Related Records
                related_record_id INT NULL,
                related_record_type ENUM('birth', 'death', 'marriage', 'divorce') NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (record_id) REFERENCES vital_records(id) ON DELETE SET NULL,
                INDEX idx_record_id (record_id),
                INDEX idx_appointment_date (appointment_date),
                INDEX idx_status (status),
                INDEX idx_related_record (related_record_id, related_record_type)
            );

            CREATE TABLE IF NOT EXISTS vital_record_documents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                record_id INT NOT NULL,
                document_type ENUM('birth_certificate', 'death_certificate', 'marriage_certificate', 'medical_report', 'court_order', 'id_proof', 'affidavit', 'other') NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                verified BOOLEAN DEFAULT FALSE,
                verification_date TIMESTAMP NULL,
                verifier_id INT NULL,
                verification_notes TEXT,

                FOREIGN KEY (record_id) REFERENCES vital_records(id) ON DELETE CASCADE,
                INDEX idx_record_id (record_id),
                INDEX idx_document_type (document_type),
                INDEX idx_verified (verified)
            );

            CREATE TABLE IF NOT EXISTS vital_record_fees (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_type ENUM('birth_registration', 'death_registration', 'marriage_registration', 'divorce_registration', 'amendment', 'certificate_copy', 'certified_copy', 'search_fee') NOT NULL,
                record_type ENUM('birth', 'death', 'marriage', 'divorce', 'other') DEFAULT 'other',
                fee_amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                effective_date DATE NOT NULL,
                expiry_date DATE NULL,
                is_active BOOLEAN DEFAULT TRUE,
                description TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_service_type (service_type),
                INDEX idx_record_type (record_type),
                INDEX idx_effective_date (effective_date),
                INDEX idx_is_active (is_active)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup birth registration workflow
        $birthWorkflow = [
            'name' => 'Birth Registration Process',
            'description' => 'Complete workflow for birth registration',
            'steps' => [
                [
                    'name' => 'Initial Registration',
                    'type' => 'user_task',
                    'assignee' => 'parent',
                    'form' => 'birth_registration_form'
                ],
                [
                    'name' => 'Medical Verification',
                    'type' => 'service_task',
                    'service' => 'medical_verification_service'
                ],
                [
                    'name' => 'Parent Verification',
                    'type' => 'user_task',
                    'assignee' => 'parent',
                    'form' => 'parent_verification_form'
                ],
                [
                    'name' => 'Document Review',
                    'type' => 'service_task',
                    'service' => 'document_review_service'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'registrar',
                    'form' => 'final_approval_form'
                ],
                [
                    'name' => 'Certificate Issuance',
                    'type' => 'service_task',
                    'service' => 'certificate_issuance_service'
                ]
            ]
        ];

        // Setup death registration workflow
        $deathWorkflow = [
            'name' => 'Death Registration Process',
            'description' => 'Complete workflow for death registration',
            'steps' => [
                [
                    'name' => 'Initial Report',
                    'type' => 'user_task',
                    'assignee' => 'informant',
                    'form' => 'death_report_form'
                ],
                [
                    'name' => 'Medical Verification',
                    'type' => 'service_task',
                    'service' => 'medical_verification_service'
                ],
                [
                    'name' => 'Coroner Review',
                    'type' => 'user_task',
                    'assignee' => 'coroner',
                    'form' => 'coroner_review_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'registrar',
                    'form' => 'final_approval_form'
                ],
                [
                    'name' => 'Certificate Issuance',
                    'type' => 'service_task',
                    'service' => 'certificate_issuance_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/birth_workflow.json', json_encode($birthWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/death_workflow.json', json_encode($deathWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/certificates',
            __DIR__ . '/uploads/documents',
            __DIR__ . '/uploads/amendments',
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
            'vital_record_fees',
            'vital_record_documents',
            'vital_record_appointments',
            'vital_record_certificates',
            'vital_record_amendments',
            'vital_records'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerBirth($data) {
        try {
            $this->validateBirthRegistrationData($data);
            $recordNumber = $this->generateRecordNumber('birth');
            $fee = $this->calculateVitalRecordFee('birth_registration', 'birth');

            $sql = "INSERT INTO vital_records (
                record_number, record_type, event_date, event_time, event_place,
                registration_date, registration_place,
                first_name, middle_name, last_name, date_of_birth, place_of_birth, gender, nationality,
                father_first_name, father_middle_name, father_last_name, father_date_of_birth, father_nationality, father_occupation,
                mother_first_name, mother_middle_name, mother_last_name, mother_maiden_name, mother_date_of_birth, mother_nationality, mother_occupation,
                informant_name, informant_relationship, informant_address, informant_phone, informant_email,
                birth_weight_grams, gestational_age_weeks, multiple_birth, birth_order,
                fee_amount, created_by
            ) VALUES (?, 'birth', ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $recordNumber, $data['event_date'], $data['event_time'] ?? null, $data['event_place'],
                $data['registration_place'], $data['first_name'], $data['middle_name'] ?? null, $data['last_name'],
                $data['date_of_birth'], $data['place_of_birth'], $data['gender'], $data['nationality'] ?? null,
                $data['father_first_name'] ?? null, $data['father_middle_name'] ?? null, $data['father_last_name'] ?? null,
                $data['father_date_of_birth'] ?? null, $data['father_nationality'] ?? null, $data['father_occupation'] ?? null,
                $data['mother_first_name'] ?? null, $data['mother_middle_name'] ?? null, $data['mother_last_name'] ?? null,
                $data['mother_maiden_name'] ?? null, $data['mother_date_of_birth'] ?? null, $data['mother_nationality'] ?? null,
                $data['mother_occupation'] ?? null, $data['informant_name'], $data['informant_relationship'],
                json_encode($data['informant_address']), $data['informant_phone'] ?? null, $data['informant_email'] ?? null,
                $data['birth_weight_grams'] ?? null, $data['gestational_age_weeks'] ?? null,
                $data['multiple_birth'] ?? false, $data['birth_order'] ?? null,
                $fee, $data['created_by']
            ];

            $recordId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'record_id' => $recordId,
                'record_number' => $recordNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerDeath($data) {
        try {
            $this->validateDeathRegistrationData($data);
            $recordNumber = $this->generateRecordNumber('death');
            $fee = $this->calculateVitalRecordFee('death_registration', 'death');

            $sql = "INSERT INTO vital_records (
                record_number, record_type, event_date, event_place,
                registration_date, registration_place,
                first_name, middle_name, last_name, date_of_birth, place_of_birth, gender, nationality,
                date_of_death, place_of_death, cause_of_death, manner_of_death,
                attending_physician, funeral_home,
                informant_name, informant_relationship, informant_address, informant_phone, informant_email,
                fee_amount, created_by
            ) VALUES (?, 'death', ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $recordNumber, $data['event_date'], $data['event_place'],
                $data['registration_place'], $data['first_name'], $data['middle_name'] ?? null, $data['last_name'],
                $data['date_of_birth'], $data['place_of_birth'], $data['gender'], $data['nationality'] ?? null,
                $data['date_of_death'], $data['place_of_death'], $data['cause_of_death'], $data['manner_of_death'] ?? null,
                $data['attending_physician'] ?? null, $data['funeral_home'] ?? null,
                $data['informant_name'], $data['informant_relationship'],
                json_encode($data['informant_address']), $data['informant_phone'] ?? null, $data['informant_email'] ?? null,
                $fee, $data['created_by']
            ];

            $recordId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'record_id' => $recordId,
                'record_number' => $recordNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getVitalRecord($recordNumber, $userId) {
        $sql = "SELECT * FROM vital_records WHERE record_number = ?";
        $record = $this->db->fetch($sql, [$recordNumber]);

        if (!$record) {
            return ['success' => false, 'error' => 'Vital record not found'];
        }

        // Check if user has access to this record
        if (!$this->hasAccessToRecord($record, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related data
        $amendments = $this->getRecordAmendments($record['id']);
        $certificates = $this->getRecordCertificates($record['id']);
        $documents = $this->getRecordDocuments($record['id']);

        return [
            'success' => true,
            'record' => $record,
            'amendments' => $amendments,
            'certificates' => $certificates,
            'documents' => $documents
        ];
    }

    public function requestAmendment($recordId, $amendmentData, $userId) {
        try {
            $this->validateAmendmentData($amendmentData);

            // Get current record data
            $sql = "SELECT * FROM vital_records WHERE id = ?";
            $currentRecord = $this->db->fetch($sql, [$recordId]);

            if (!$currentRecord) {
                return ['success' => false, 'error' => 'Record not found'];
            }

            $amendmentNumber = $this->generateAmendmentNumber();

            $sql = "INSERT INTO vital_record_amendments (
                record_id, amendment_number, amendment_type, amendment_reason,
                previous_data, new_data, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $amendmentId = $this->db->insert($sql, [
                $recordId, $amendmentNumber, $amendmentData['amendment_type'],
                $amendmentData['amendment_reason'], json_encode($currentRecord),
                json_encode($amendmentData['new_data']), $userId
            ]);

            return [
                'success' => true,
                'amendment_id' => $amendmentId,
                'amendment_number' => $amendmentNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function requestCertificate($recordId, $certificateData, $userId) {
        try {
            $this->validateCertificateRequestData($certificateData);
            $certificateNumber = $this->generateCertificateNumber();
            $fee = $this->calculateVitalRecordFee('certificate_copy', 'other');

            $sql = "INSERT INTO vital_record_certificates (
                record_id, certificate_type, certificate_number, issue_date,
                requested_by, purpose_of_request, delivery_method, delivery_address,
                fee_amount
            ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)";

            $certificateId = $this->db->insert($sql, [
                $recordId, $certificateData['certificate_type'], $certificateNumber,
                $userId, $certificateData['purpose_of_request'] ?? null,
                $certificateData['delivery_method'] ?? 'pickup',
                json_encode($certificateData['delivery_address'] ?? []),
                $fee
            ]);

            return [
                'success' => true,
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function uploadDocument($recordId, $documentType, $file) {
        $this->validateDocument($file, $documentType);

        $fileName = $this->generateFileName($recordId, $documentType, $file['name']);
        $filePath = __DIR__ . '/uploads/documents/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $sql = "INSERT INTO vital_record_documents (
                record_id, document_type, document_name, file_path,
                file_size, mime_type
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $this->db->insert($sql, [
                $recordId, $documentType, $file['name'], $filePath,
                $file['size'], $file['type']
            ]);

            return ['success' => true, 'file_path' => $filePath];
        }

        return ['success' => false, 'error' => 'File upload failed'];
    }

    public function searchRecords($searchCriteria, $userId) {
        $where = [];
        $params = [];

        if (!empty($searchCriteria['first_name'])) {
            $where[] = "first_name LIKE ?";
            $params[] = '%' . $searchCriteria['first_name'] . '%';
        }

        if (!empty($searchCriteria['last_name'])) {
            $where[] = "last_name LIKE ?";
            $params[] = '%' . $searchCriteria['last_name'] . '%';
        }

        if (!empty($searchCriteria['record_type'])) {
            $where[] = "record_type = ?";
            $params[] = $searchCriteria['record_type'];
        }

        if (!empty($searchCriteria['date_of_birth'])) {
            $where[] = "date_of_birth = ?";
            $params[] = $searchCriteria['date_of_birth'];
        }

        if (!empty($searchCriteria['event_date_from']) && !empty($searchCriteria['event_date_to'])) {
            $where[] = "event_date BETWEEN ? AND ?";
            $params[] = $searchCriteria['event_date_from'];
            $params[] = $searchCriteria['event_date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT id, record_number, record_type, first_name, middle_name, last_name,
                       date_of_birth, event_date, status
                FROM vital_records $whereClause
                ORDER BY created_at DESC LIMIT 100";

        $records = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'records' => $records,
            'total' => count($records)
        ];
    }

    // Helper Methods
    private function validateBirthRegistrationData($data) {
        $required = [
            'event_date', 'event_place', 'registration_place',
            'first_name', 'last_name', 'date_of_birth', 'place_of_birth', 'gender',
            'informant_name', 'informant_relationship', 'informant_address', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate dates
        $eventDate = new DateTime($data['event_date']);
        $birthDate = new DateTime($data['date_of_birth']);
        $now = new DateTime();

        if ($eventDate > $now) {
            throw new Exception('Event date cannot be in the future');
        }

        if ($birthDate > $now) {
            throw new Exception('Date of birth cannot be in the future');
        }

        // Validate gestational age if provided
        if (isset($data['gestational_age_weeks']) && ($data['gestational_age_weeks'] < 20 || $data['gestational_age_weeks'] > 45)) {
            throw new Exception('Invalid gestational age');
        }
    }

    private function validateDeathRegistrationData($data) {
        $required = [
            'event_date', 'event_place', 'registration_place',
            'first_name', 'last_name', 'date_of_birth', 'place_of_birth', 'gender',
            'date_of_death', 'place_of_death', 'cause_of_death',
            'informant_name', 'informant_relationship', 'informant_address', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate dates
        $deathDate = new DateTime($data['date_of_death']);
        $birthDate = new DateTime($data['date_of_birth']);
        $now = new DateTime();

        if ($deathDate > $now) {
            throw new Exception('Date of death cannot be in the future');
        }

        if ($deathDate < $birthDate) {
            throw new Exception('Date of death cannot be before date of birth');
        }
    }

    private function validateAmendmentData($data) {
        $required = ['amendment_type', 'amendment_reason', 'new_data'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCertificateRequestData($data) {
        $required = ['certificate_type'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateRecordNumber($type) {
        $prefix = strtoupper(substr($type, 0, 3));
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "{$prefix}{$date}{$random}";
    }

    private function generateAmendmentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "AMD{$date}{$random}";
    }

    private function generateCertificateNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CERT{$date}{$random}";
    }

    private function calculateVitalRecordFee($serviceType, $recordType) {
        $sql = "SELECT fee_amount FROM vital_record_fees
                WHERE service_type = ? AND (record_type = ? OR record_type = 'other')
                AND is_active = TRUE AND effective_date <= CURDATE()
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY effective_date DESC LIMIT 1";

        $fee = $this->db->fetch($sql, [$serviceType, $recordType]);
        return $fee ? $fee['fee_amount'] : 0;
    }

    private function hasAccessToRecord($record, $userId) {
        // Check if user is the informant or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getRecordAmendments($recordId) {
        $sql = "SELECT * FROM vital_record_amendments WHERE record_id = ? ORDER BY requested_at DESC";
        return $this->db->fetchAll($sql, [$recordId]);
    }

    private function getRecordCertificates($recordId) {
        $sql = "SELECT * FROM vital_record_certificates WHERE record_id = ? ORDER BY issue_date DESC";
        return $this->db->fetchAll($sql, [$recordId]);
    }

    private function getRecordDocuments($recordId) {
        $sql = "SELECT * FROM vital_record_documents WHERE record_id = ? ORDER BY upload_date DESC";
        return $this->db->fetchAll($sql, [$recordId]);
    }

    private function validateDocument($file, $documentType) {
        $allowedTypes = [
            'birth_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
            'death_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
            'medical_report' => ['application/pdf', 'image/jpeg', 'image/png'],
            'court_order' => ['application/pdf', 'image/jpeg', 'image/png'],
            'id_proof' => ['application/pdf', 'image/jpeg', 'image/png']
        ];

        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!isset($allowedTypes[$documentType]) || !in_array($file['type'], $allowedTypes[$documentType])) {
            throw new Exception('Invalid document type');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds limit');
        }
    }

    private function generateFileName($recordId, $documentType, $originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return "{$recordId}_{$documentType}_" . time() . ".{$extension}";
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

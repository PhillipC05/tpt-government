<?php
/**
 * Marriage & Civil Registry Module
 * Handles marriage licenses, civil unions, divorces, and vital records
 */

require_once __DIR__ . '/../ServiceModule.php';

class MarriageModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Marriage & Civil Registry Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive civil registry services for marriages, divorces, and legal unions',
            'dependencies' => ['IdentityServices', 'PaymentGateway'],
            'permissions' => [
                'marriage.apply' => 'Apply for marriage license',
                'marriage.register' => 'Register marriage',
                'divorce.apply' => 'Apply for divorce',
                'civil_union.apply' => 'Apply for civil union',
                'registry.view' => 'View registry records',
                'registry.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS marriage_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                application_type ENUM('marriage', 'civil_union', 'divorce', 'annulment', 'legal_separation') NOT NULL,
                status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'registered', 'dissolved') DEFAULT 'draft',

                -- Applicant Information
                applicant1_id INT NOT NULL,
                applicant1_first_name VARCHAR(100) NOT NULL,
                applicant1_middle_name VARCHAR(100),
                applicant1_last_name VARCHAR(100) NOT NULL,
                applicant1_date_of_birth DATE NOT NULL,
                applicant1_nationality VARCHAR(50) NOT NULL,
                applicant1_address TEXT NOT NULL,
                applicant1_email VARCHAR(255) NOT NULL,
                applicant1_phone VARCHAR(20) NOT NULL,

                -- Partner Information (for marriage/civil union)
                applicant2_id INT NULL,
                applicant2_first_name VARCHAR(100),
                applicant2_middle_name VARCHAR(100),
                applicant2_last_name VARCHAR(100),
                applicant2_date_of_birth DATE,
                applicant2_nationality VARCHAR(50),
                applicant2_address TEXT,
                applicant2_email VARCHAR(255),
                applicant2_phone VARCHAR(20),

                -- Marriage/Civil Union Details
                ceremony_type ENUM('civil', 'religious', 'cultural', 'other') NULL,
                ceremony_date DATE NULL,
                ceremony_location VARCHAR(255) NULL,
                officiant_name VARCHAR(100) NULL,
                officiant_license VARCHAR(50) NULL,
                witnesses JSON NULL,

                -- Divorce Details
                divorce_type ENUM('contested', 'uncontested', 'mutual_consent') NULL,
                filing_date DATE NULL,
                grounds_for_divorce TEXT NULL,
                separation_date DATE NULL,
                child_custody_arrangement TEXT NULL,
                property_settlement TEXT NULL,

                -- Legal Information
                court_case_number VARCHAR(50) NULL,
                lawyer1_name VARCHAR(100) NULL,
                lawyer1_contact VARCHAR(255) NULL,
                lawyer2_name VARCHAR(100) NULL,
                lawyer2_contact VARCHAR(255) NULL,

                -- Documents
                documents_submitted JSON,
                marriage_certificate_path VARCHAR(255) NULL,
                divorce_decree_path VARCHAR(255) NULL,

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                registered_at TIMESTAMP NULL,
                certificate_issued_at TIMESTAMP NULL,

                -- Fees and Payment
                fee_amount DECIMAL(10,2) NOT NULL,
                payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
                payment_reference VARCHAR(100),

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                rejection_reason TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_application_number (application_number),
                INDEX idx_applicant1_id (applicant1_id),
                INDEX idx_applicant2_id (applicant2_id),
                INDEX idx_status (status),
                INDEX idx_ceremony_date (ceremony_date),
                INDEX idx_submitted_at (submitted_at)
            );

            CREATE TABLE IF NOT EXISTS marriage_licenses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                license_number VARCHAR(20) UNIQUE NOT NULL,
                issue_date DATE NOT NULL,
                expiry_date DATE NOT NULL,
                status ENUM('active', 'expired', 'revoked', 'transferred') DEFAULT 'active',

                -- License Details
                license_type ENUM('marriage', 'civil_union') NOT NULL,
                issuing_authority VARCHAR(100) NOT NULL,
                issuing_officer VARCHAR(100) NOT NULL,

                -- Validity
                validity_period_months INT DEFAULT 3,
                extension_count INT DEFAULT 0,
                last_extension_date DATE NULL,

                -- Usage
                used BOOLEAN DEFAULT FALSE,
                used_date DATE NULL,
                ceremony_date DATE NULL,
                ceremony_location VARCHAR(255) NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (application_id) REFERENCES marriage_applications(id) ON DELETE CASCADE,
                INDEX idx_license_number (license_number),
                INDEX idx_application_id (application_id),
                INDEX idx_status (status),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS marriage_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                registration_number VARCHAR(20) UNIQUE NOT NULL,
                registration_type ENUM('marriage', 'civil_union', 'divorce', 'annulment') NOT NULL,
                registration_date DATE NOT NULL,
                status ENUM('active', 'dissolved', 'annulled') DEFAULT 'active',

                -- Registration Details
                place_of_registration VARCHAR(255) NOT NULL,
                registering_officer VARCHAR(100) NOT NULL,
                registration_method ENUM('in_person', 'mail', 'online') DEFAULT 'in_person',

                -- Certificate Information
                certificate_number VARCHAR(20) UNIQUE NULL,
                certificate_issued_date DATE NULL,
                certificate_collected BOOLEAN DEFAULT FALSE,
                certificate_collected_date DATE NULL,

                -- Additional Information
                remarks TEXT,
                confidential BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (application_id) REFERENCES marriage_applications(id) ON DELETE CASCADE,
                INDEX idx_registration_number (registration_number),
                INDEX idx_application_id (application_id),
                INDEX idx_registration_type (registration_type),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS marriage_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                appointment_type ENUM('counseling', 'ceremony', 'interview', 'hearing', 'certificate_collection') NOT NULL,
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,
                status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                notes TEXT,
                officiant_id INT NULL,
                officiant_name VARCHAR(100) NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (application_id) REFERENCES marriage_applications(id) ON DELETE CASCADE,
                INDEX idx_application_id (application_id),
                INDEX idx_appointment_date (appointment_date),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS marriage_documents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                document_type ENUM('birth_certificate', 'id_proof', 'divorce_decree', 'marriage_certificate', 'affidavit', 'medical_certificate', 'consent_form', 'other') NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                verified BOOLEAN DEFAULT FALSE,
                verification_date TIMESTAMP NULL,
                verifier_id INT NULL,
                verification_notes TEXT,

                FOREIGN KEY (application_id) REFERENCES marriage_applications(id) ON DELETE CASCADE,
                INDEX idx_application_id (application_id),
                INDEX idx_document_type (document_type),
                INDEX idx_verified (verified)
            );

            CREATE TABLE IF NOT EXISTS marriage_fees (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_type ENUM('marriage_license', 'civil_union_license', 'marriage_registration', 'divorce_filing', 'annulment', 'certificate_copy', 'name_change') NOT NULL,
                fee_amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                effective_date DATE NOT NULL,
                expiry_date DATE NULL,
                is_active BOOLEAN DEFAULT TRUE,
                description TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_service_type (service_type),
                INDEX idx_effective_date (effective_date),
                INDEX idx_is_active (is_active)
            );

            CREATE TABLE IF NOT EXISTS marriage_officiants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                license_number VARCHAR(50) UNIQUE NOT NULL,
                license_type ENUM('civil', 'religious', 'cultural') NOT NULL,
                issuing_authority VARCHAR(100) NOT NULL,
                license_issue_date DATE NOT NULL,
                license_expiry_date DATE NOT NULL,
                contact_email VARCHAR(255) NOT NULL,
                contact_phone VARCHAR(20) NOT NULL,
                address TEXT NOT NULL,
                status ENUM('active', 'suspended', 'revoked', 'expired') DEFAULT 'active',
                specializations TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_license_number (license_number),
                INDEX idx_status (status),
                INDEX idx_license_expiry_date (license_expiry_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup marriage application workflow
        $marriageWorkflow = [
            'name' => 'Marriage License Application Process',
            'description' => 'Complete workflow for marriage license applications',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'marriage_application_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Background Check',
                    'type' => 'service_task',
                    'service' => 'background_check_service'
                ],
                [
                    'name' => 'Counseling Session',
                    'type' => 'user_task',
                    'assignee' => 'couple',
                    'form' => 'counseling_session_form'
                ],
                [
                    'name' => 'Medical Examination',
                    'type' => 'service_task',
                    'service' => 'medical_examination_service'
                ],
                [
                    'name' => 'Final Review',
                    'type' => 'user_task',
                    'assignee' => 'marriage_officer',
                    'form' => 'final_review_form'
                ],
                [
                    'name' => 'License Issuance',
                    'type' => 'service_task',
                    'service' => 'license_issuance_service'
                ]
            ]
        ];

        // Setup divorce application workflow
        $divorceWorkflow = [
            'name' => 'Divorce Application Process',
            'description' => 'Complete workflow for divorce applications',
            'steps' => [
                [
                    'name' => 'Petition Filing',
                    'type' => 'user_task',
                    'assignee' => 'petitioner',
                    'form' => 'divorce_petition_form'
                ],
                [
                    'name' => 'Service of Process',
                    'type' => 'service_task',
                    'service' => 'service_of_process_service'
                ],
                [
                    'name' => 'Response Period',
                    'type' => 'timer_event',
                    'duration' => '30_days'
                ],
                [
                    'name' => 'Mediation/Counseling',
                    'type' => 'user_task',
                    'assignee' => 'couple',
                    'form' => 'mediation_form'
                ],
                [
                    'name' => 'Court Hearing',
                    'type' => 'user_task',
                    'assignee' => 'judge',
                    'form' => 'court_hearing_form'
                ],
                [
                    'name' => 'Decree Issuance',
                    'type' => 'service_task',
                    'service' => 'decree_issuance_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/marriage_workflow.json', json_encode($marriageWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/divorce_workflow.json', json_encode($divorceWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/certificates',
            __DIR__ . '/uploads/licenses',
            __DIR__ . '/uploads/documents',
            __DIR__ . '/uploads/decrees',
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
            'marriage_officiants',
            'marriage_fees',
            'marriage_documents',
            'marriage_appointments',
            'marriage_registrations',
            'marriage_licenses',
            'marriage_applications'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function submitMarriageApplication($data) {
        try {
            $this->validateMarriageApplicationData($data);
            $applicationNumber = $this->generateApplicationNumber();
            $fee = $this->calculateMarriageFee($data['application_type']);

            $sql = "INSERT INTO marriage_applications (
                application_number, application_type,
                applicant1_id, applicant1_first_name, applicant1_middle_name, applicant1_last_name,
                applicant1_date_of_birth, applicant1_nationality, applicant1_address,
                applicant1_email, applicant1_phone,
                applicant2_id, applicant2_first_name, applicant2_middle_name, applicant2_last_name,
                applicant2_date_of_birth, applicant2_nationality, applicant2_address,
                applicant2_email, applicant2_phone,
                ceremony_type, ceremony_date, ceremony_location,
                fee_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $applicationNumber, $data['application_type'],
                $data['applicant1_id'], $data['applicant1_first_name'],
                $data['applicant1_middle_name'] ?? null, $data['applicant1_last_name'],
                $data['applicant1_date_of_birth'], $data['applicant1_nationality'],
                json_encode($data['applicant1_address']), $data['applicant1_email'], $data['applicant1_phone'],
                $data['applicant2_id'] ?? null, $data['applicant2_first_name'] ?? null,
                $data['applicant2_middle_name'] ?? null, $data['applicant2_last_name'] ?? null,
                $data['applicant2_date_of_birth'] ?? null, $data['applicant2_nationality'] ?? null,
                json_encode($data['applicant2_address'] ?? []), $data['applicant2_email'] ?? null,
                $data['applicant2_phone'] ?? null,
                $data['ceremony_type'] ?? null, $data['ceremony_date'] ?? null,
                $data['ceremony_location'] ?? null,
                $fee, $data['applicant1_id']
            ];

            $applicationId = $this->db->insert($sql, $params);

            // Schedule counseling appointment
            if ($data['application_type'] === 'marriage' || $data['application_type'] === 'civil_union') {
                $this->scheduleCounselingAppointment($applicationId);
            }

            return [
                'success' => true,
                'application_id' => $applicationId,
                'application_number' => $applicationNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitDivorceApplication($data) {
        try {
            $this->validateDivorceApplicationData($data);
            $applicationNumber = $this->generateApplicationNumber();
            $fee = $this->calculateMarriageFee('divorce_filing');

            $sql = "INSERT INTO marriage_applications (
                application_number, application_type,
                applicant1_id, applicant1_first_name, applicant1_middle_name, applicant1_last_name,
                applicant1_date_of_birth, applicant1_nationality, applicant1_address,
                applicant1_email, applicant1_phone,
                divorce_type, filing_date, grounds_for_divorce, separation_date,
                child_custody_arrangement, property_settlement,
                court_case_number, lawyer1_name, lawyer1_contact,
                fee_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $applicationNumber, 'divorce',
                $data['petitioner_id'], $data['petitioner_first_name'],
                $data['petitioner_middle_name'] ?? null, $data['petitioner_last_name'],
                $data['petitioner_date_of_birth'], $data['petitioner_nationality'],
                json_encode($data['petitioner_address']), $data['petitioner_email'], $data['petitioner_phone'],
                $data['divorce_type'], $data['filing_date'], $data['grounds_for_divorce'],
                $data['separation_date'] ?? null, $data['child_custody_arrangement'] ?? null,
                $data['property_settlement'] ?? null, $data['court_case_number'] ?? null,
                $data['lawyer_name'] ?? null, $data['lawyer_contact'] ?? null,
                $fee, $data['petitioner_id']
            ];

            $applicationId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'application_id' => $applicationId,
                'application_number' => $applicationNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getApplicationStatus($applicationNumber, $userId) {
        $sql = "SELECT * FROM marriage_applications
                WHERE application_number = ? AND (applicant1_id = ? OR applicant2_id = ?)";
        $application = $this->db->fetch($sql, [$applicationNumber, $userId, $userId]);

        if (!$application) {
            return ['success' => false, 'error' => 'Application not found'];
        }

        // Get associated records
        $appointments = $this->getApplicationAppointments($application['id']);
        $documents = $this->getApplicationDocuments($application['id']);
        $license = $this->getApplicationLicense($application['id']);
        $registration = $this->getApplicationRegistration($application['id']);

        return [
            'success' => true,
            'application' => $application,
            'appointments' => $appointments,
            'documents' => $documents,
            'license' => $license,
            'registration' => $registration
        ];
    }

    public function issueMarriageLicense($applicationId, $reviewerId) {
        $sql = "UPDATE marriage_applications SET
                status = 'approved',
                reviewer_id = ?,
                approved_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND status = 'under_review'";

        $this->db->query($sql, [$reviewerId, $applicationId]);

        // Generate license
        $licenseNumber = $this->generateLicenseNumber();
        $issueDate = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime('+3 months'));

        $sql = "INSERT INTO marriage_licenses (
            application_id, license_number, issue_date, expiry_date,
            license_type, issuing_authority, issuing_officer
        ) VALUES (?, ?, ?, ?, 'marriage', 'Civil Registry Office', ?)";

        $this->db->insert($sql, [
            $applicationId, $licenseNumber, $issueDate, $expiryDate, 'Civil Registrar'
        ]);

        return [
            'success' => true,
            'license_number' => $licenseNumber,
            'expiry_date' => $expiryDate
        ];
    }

    public function registerMarriage($applicationId, $ceremonyDetails) {
        // Update application with ceremony details
        $sql = "UPDATE marriage_applications SET
                ceremony_date = ?,
                ceremony_location = ?,
                officiant_name = ?,
                officiant_license = ?,
                witnesses = ?,
                registered_at = CURRENT_TIMESTAMP,
                status = 'registered'
                WHERE id = ?";

        $this->db->query($sql, [
            $ceremonyDetails['ceremony_date'],
            $ceremonyDetails['ceremony_location'],
            $ceremonyDetails['officiant_name'],
            $ceremonyDetails['officiant_license'],
            json_encode($ceremonyDetails['witnesses']),
            $applicationId
        ]);

        // Create registration record
        $registrationNumber = $this->generateRegistrationNumber();

        $sql = "INSERT INTO marriage_registrations (
            application_id, registration_number, registration_type,
            registration_date, place_of_registration, registering_officer
        ) VALUES (?, ?, 'marriage', CURDATE(), ?, ?)";

        $this->db->insert($sql, [
            $applicationId, $registrationNumber,
            $ceremonyDetails['ceremony_location'], $ceremonyDetails['officiant_name']
        ]);

        return [
            'success' => true,
            'registration_number' => $registrationNumber
        ];
    }

    public function uploadDocument($applicationId, $documentType, $file) {
        $this->validateDocument($file, $documentType);

        $fileName = $this->generateFileName($applicationId, $documentType, $file['name']);
        $filePath = __DIR__ . '/uploads/documents/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $sql = "INSERT INTO marriage_documents (
                application_id, document_type, document_name, file_path,
                file_size, mime_type
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $this->db->insert($sql, [
                $applicationId, $documentType, $file['name'], $filePath,
                $file['size'], $file['type']
            ]);

            return ['success' => true, 'file_path' => $filePath];
        }

        return ['success' => false, 'error' => 'File upload failed'];
    }

    public function scheduleAppointment($applicationId, $appointmentType, $date, $time, $location) {
        $sql = "INSERT INTO marriage_appointments (
            application_id, appointment_type, appointment_date,
            appointment_time, location
        ) VALUES (?, ?, ?, ?, ?)";

        $appointmentId = $this->db->insert($sql, [
            $applicationId, $appointmentType, $date, $time, $location
        ]);

        return [
            'success' => true,
            'appointment_id' => $appointmentId
        ];
    }

    // Helper Methods
    private function validateMarriageApplicationData($data) {
        $required = [
            'application_type', 'applicant1_id', 'applicant1_first_name',
            'applicant1_last_name', 'applicant1_date_of_birth', 'applicant1_nationality',
            'applicant1_address', 'applicant1_email', 'applicant1_phone'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age (minimum 18 for marriage)
        $dob = new DateTime($data['applicant1_date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 18) {
            throw new Exception('Minimum age for marriage is 18 years');
        }

        // For marriage/civil union, validate second applicant
        if (in_array($data['application_type'], ['marriage', 'civil_union'])) {
            $required2 = [
                'applicant2_first_name', 'applicant2_last_name',
                'applicant2_date_of_birth', 'applicant2_nationality'
            ];

            foreach ($required2 as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Required field missing for second applicant: $field");
                }
            }
        }
    }

    private function validateDivorceApplicationData($data) {
        $required = [
            'petitioner_id', 'petitioner_first_name', 'petitioner_last_name',
            'petitioner_date_of_birth', 'petitioner_nationality',
            'petitioner_address', 'petitioner_email', 'petitioner_phone',
            'divorce_type', 'filing_date', 'grounds_for_divorce'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "MAR{$date}{$random}";
    }

    private function generateLicenseNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ML{$date}{$random}";
    }

    private function generateRegistrationNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "MR{$date}{$random}";
    }

    private function calculateMarriageFee($serviceType) {
        $sql = "SELECT fee_amount FROM marriage_fees
                WHERE service_type = ? AND is_active = TRUE
                AND effective_date <= CURDATE()
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY effective_date DESC LIMIT 1";

        $fee = $this->db->fetch($sql, [$serviceType]);
        return $fee ? $fee['fee_amount'] : 0;
    }

    private function scheduleCounselingAppointment($applicationId) {
        $nextSlot = $this->getNextAvailableSlot('counseling');

        if ($nextSlot) {
            $this->scheduleAppointment(
                $applicationId,
                'counseling',
                $nextSlot['date'],
                $nextSlot['time'],
                $nextSlot['location']
            );
        }
    }

    private function getNextAvailableSlot($appointmentType) {
        // This would integrate with appointment scheduling system
        return [
            'date' => date('Y-m-d', strtotime('+7 days')),
            'time' => '10:00:00',
            'location' => 'Civil Registry Office'
        ];
    }

    private function getApplicationAppointments($applicationId) {
        $sql = "SELECT * FROM marriage_appointments WHERE application_id = ? ORDER BY appointment_date, appointment_time";
        return $this->db->fetchAll($sql, [$applicationId]);
    }

    private function getApplicationDocuments($applicationId) {
        $sql = "SELECT * FROM marriage_documents WHERE application_id = ? ORDER BY upload_date DESC";
        return $this->db->fetchAll($sql, [$applicationId]);
    }

    private function getApplicationLicense($applicationId) {
        $sql = "SELECT * FROM marriage_licenses WHERE application_id = ?";
        return $this->db->fetch($sql, [$applicationId]);
    }

    private function getApplicationRegistration($applicationId) {
        $sql = "SELECT * FROM marriage_registrations WHERE application_id = ?";
        return $this->db->fetch($sql, [$applicationId]);
    }

    private function validateDocument($file, $documentType) {
        $allowedTypes = [
            'birth_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
            'id_proof' => ['application/pdf', 'image/jpeg', 'image/png'],
            'marriage_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
            'divorce_decree' => ['application/pdf', 'image/jpeg', 'image/png']
        ];

        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!isset($allowedTypes[$documentType]) || !in_array($file['type'], $allowedTypes[$documentType])) {
            throw new Exception('Invalid document type');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds limit');
        }
    }

    private function generateFileName($applicationId, $documentType, $originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return "{$applicationId}_{$documentType}_" . time() . ".{$extension}";
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

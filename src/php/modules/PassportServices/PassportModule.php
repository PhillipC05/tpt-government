<?php
/**
 * Passport Services Module
 * Handles passport applications, renewals, and issuance
 */

require_once __DIR__ . '/../ServiceModule.php';

class PassportModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Passport Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive passport application, renewal, and issuance system',
            'dependencies' => ['IdentityServices', 'PaymentGateway'],
            'permissions' => [
                'passport.apply' => 'Apply for passport',
                'passport.renew' => 'Renew passport',
                'passport.status' => 'Check application status',
                'passport.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS passport_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                application_type ENUM('new', 'renewal', 'replacement', 'emergency') NOT NULL,
                passport_type ENUM('ordinary', 'diplomatic', 'official', 'emergency') NOT NULL,
                application_number VARCHAR(50) UNIQUE NOT NULL,
                status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'issued', 'collected') DEFAULT 'draft',
                priority ENUM('normal', 'express', 'emergency') DEFAULT 'normal',

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                place_of_birth VARCHAR(100) NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                nationality VARCHAR(50) NOT NULL,
                marital_status ENUM('single', 'married', 'divorced', 'widowed') NOT NULL,

                -- Contact Information
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                address TEXT NOT NULL,

                -- Identification
                national_id VARCHAR(50),
                previous_passport_number VARCHAR(50),

                -- Application Details
                purpose_of_travel TEXT,
                destination_countries TEXT,
                travel_dates JSON,
                emergency_contact JSON,

                -- Documents
                documents_submitted JSON,
                photo_path VARCHAR(255),
                signature_path VARCHAR(255),

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                issued_at TIMESTAMP NULL,
                collected_at TIMESTAMP NULL,
                expiry_date DATE NULL,
                passport_number VARCHAR(20) NULL,

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

                INDEX idx_user_id (user_id),
                INDEX idx_application_number (application_number),
                INDEX idx_status (status),
                INDEX idx_passport_number (passport_number),
                INDEX idx_submitted_at (submitted_at)
            );

            CREATE TABLE IF NOT EXISTS passport_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                appointment_type ENUM('biometric', 'interview', 'collection') NOT NULL,
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,
                status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (application_id) REFERENCES passport_applications(id) ON DELETE CASCADE,
                INDEX idx_application_id (application_id),
                INDEX idx_appointment_date (appointment_date),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS passport_documents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT NOT NULL,
                document_type ENUM('birth_certificate', 'marriage_certificate', 'divorce_decree', 'national_id', 'previous_passport', 'police_clearance', 'medical_certificate', 'other') NOT NULL,
                document_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                verified BOOLEAN DEFAULT FALSE,
                verification_date TIMESTAMP NULL,
                verifier_id INT NULL,
                verification_notes TEXT,

                FOREIGN KEY (application_id) REFERENCES passport_applications(id) ON DELETE CASCADE,
                INDEX idx_application_id (application_id),
                INDEX idx_document_type (document_type),
                INDEX idx_verified (verified)
            );

            CREATE TABLE IF NOT EXISTS passport_fees (
                id INT PRIMARY KEY AUTO_INCREMENT,
                passport_type ENUM('ordinary', 'diplomatic', 'official', 'emergency') NOT NULL,
                application_type ENUM('new', 'renewal', 'replacement', 'emergency') NOT NULL,
                priority ENUM('normal', 'express', 'emergency') NOT NULL,
                fee_amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                effective_date DATE NOT NULL,
                expiry_date DATE NULL,
                is_active BOOLEAN DEFAULT TRUE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_passport_type (passport_type),
                INDEX idx_application_type (application_type),
                INDEX idx_effective_date (effective_date),
                INDEX idx_is_active (is_active)
            );

            CREATE TABLE IF NOT EXISTS passport_templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                template_name VARCHAR(100) NOT NULL,
                template_type ENUM('application_form', 'appointment_letter', 'approval_letter', 'rejection_letter', 'collection_notice') NOT NULL,
                template_content TEXT NOT NULL,
                language VARCHAR(10) DEFAULT 'en',
                is_default BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_template_type (template_type),
                INDEX idx_language (language),
                INDEX idx_is_default (is_default),
                INDEX idx_is_active (is_active)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup passport application workflow
        $workflowConfig = [
            'name' => 'Passport Application Process',
            'description' => 'Complete workflow for passport applications from submission to issuance',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'passport_application_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Biometric Appointment',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'biometric_appointment_form'
                ],
                [
                    'name' => 'Security Clearance',
                    'type' => 'service_task',
                    'service' => 'security_clearance_service'
                ],
                [
                    'name' => 'Final Review',
                    'type' => 'user_task',
                    'assignee' => 'passport_officer',
                    'form' => 'final_review_form'
                ],
                [
                    'name' => 'Passport Issuance',
                    'type' => 'service_task',
                    'service' => 'passport_issuance_service'
                ]
            ]
        ];

        // Save workflow configuration
        file_put_contents(__DIR__ . '/config/workflow.json', json_encode($workflowConfig, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/photos',
            __DIR__ . '/uploads/signatures',
            __DIR__ . '/uploads/documents',
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
            'passport_templates',
            'passport_fees',
            'passport_documents',
            'passport_appointments',
            'passport_applications'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function submitApplication($data) {
        try {
            // Validate application data
            $this->validateApplicationData($data);

            // Generate application number
            $applicationNumber = $this->generateApplicationNumber();

            // Calculate fee
            $fee = $this->calculateFee($data['passport_type'], $data['application_type'], $data['priority']);

            // Insert application
            $sql = "INSERT INTO passport_applications (
                user_id, application_type, passport_type, application_number,
                first_name, middle_name, last_name, date_of_birth, place_of_birth,
                gender, nationality, marital_status, email, phone, address,
                national_id, previous_passport_number, purpose_of_travel,
                destination_countries, travel_dates, emergency_contact,
                fee_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['user_id'],
                $data['application_type'],
                $data['passport_type'],
                $applicationNumber,
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['date_of_birth'],
                $data['place_of_birth'],
                $data['gender'],
                $data['nationality'],
                $data['marital_status'],
                $data['email'],
                $data['phone'],
                json_encode($data['address']),
                $data['national_id'] ?? null,
                $data['previous_passport_number'] ?? null,
                $data['purpose_of_travel'] ?? null,
                json_encode($data['destination_countries'] ?? []),
                json_encode($data['travel_dates'] ?? []),
                json_encode($data['emergency_contact'] ?? []),
                $fee,
                $data['user_id']
            ];

            $applicationId = $this->db->insert($sql, $params);

            // Schedule biometric appointment
            $this->scheduleBiometricAppointment($applicationId);

            return [
                'success' => true,
                'application_id' => $applicationId,
                'application_number' => $applicationNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getApplicationStatus($applicationId, $userId) {
        $sql = "SELECT * FROM passport_applications WHERE id = ? AND user_id = ?";
        $application = $this->db->fetch($sql, [$applicationId, $userId]);

        if (!$application) {
            return ['success' => false, 'error' => 'Application not found'];
        }

        // Get associated appointments
        $appointments = $this->getApplicationAppointments($applicationId);

        // Get processing timeline
        $timeline = $this->getProcessingTimeline($applicationId);

        return [
            'success' => true,
            'application' => $application,
            'appointments' => $appointments,
            'timeline' => $timeline
        ];
    }

    public function uploadDocument($applicationId, $documentType, $file) {
        // Validate file
        $this->validateDocument($file, $documentType);

        // Generate file path
        $fileName = $this->generateFileName($applicationId, $documentType, $file['name']);
        $filePath = __DIR__ . '/uploads/documents/' . $fileName;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Save document record
            $sql = "INSERT INTO passport_documents (
                application_id, document_type, document_name, file_path,
                file_size, mime_type
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $this->db->insert($sql, [
                $applicationId,
                $documentType,
                $file['name'],
                $filePath,
                $file['size'],
                $file['type']
            ]);

            return ['success' => true, 'file_path' => $filePath];
        }

        return ['success' => false, 'error' => 'File upload failed'];
    }

    public function scheduleAppointment($applicationId, $appointmentType, $date, $time, $location) {
        $sql = "INSERT INTO passport_appointments (
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
    private function validateApplicationData($data) {
        $required = ['user_id', 'application_type', 'passport_type', 'first_name', 'last_name', 'date_of_birth', 'place_of_birth', 'gender', 'nationality', 'marital_status', 'email', 'phone', 'address'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Validate date of birth
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 0 || $age > 120) {
            throw new Exception('Invalid date of birth');
        }
    }

    private function generateApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PAS{$date}{$random}";
    }

    private function calculateFee($passportType, $applicationType, $priority) {
        $sql = "SELECT fee_amount FROM passport_fees
                WHERE passport_type = ? AND application_type = ? AND priority = ?
                AND is_active = TRUE AND effective_date <= CURDATE()
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY effective_date DESC LIMIT 1";

        $fee = $this->db->fetch($sql, [$passportType, $applicationType, $priority]);

        return $fee ? $fee['fee_amount'] : 0;
    }

    private function scheduleBiometricAppointment($applicationId) {
        // Get next available appointment slot
        $nextSlot = $this->getNextAvailableSlot();

        if ($nextSlot) {
            $this->scheduleAppointment(
                $applicationId,
                'biometric',
                $nextSlot['date'],
                $nextSlot['time'],
                $nextSlot['location']
            );
        }
    }

    private function getNextAvailableSlot() {
        // This would integrate with appointment scheduling system
        // For now, return a default slot
        return [
            'date' => date('Y-m-d', strtotime('+7 days')),
            'time' => '10:00:00',
            'location' => 'Central Passport Office'
        ];
    }

    private function getApplicationAppointments($applicationId) {
        $sql = "SELECT * FROM passport_appointments WHERE application_id = ? ORDER BY appointment_date, appointment_time";
        return $this->db->fetchAll($sql, [$applicationId]);
    }

    private function getProcessingTimeline($applicationId) {
        $sql = "SELECT status, submitted_at, reviewed_at, approved_at, issued_at, collected_at
                FROM passport_applications WHERE id = ?";
        $application = $this->db->fetch($sql, [$applicationId]);

        $timeline = [];

        if ($application['submitted_at']) {
            $timeline[] = ['status' => 'submitted', 'date' => $application['submitted_at']];
        }
        if ($application['reviewed_at']) {
            $timeline[] = ['status' => 'under_review', 'date' => $application['reviewed_at']];
        }
        if ($application['approved_at']) {
            $timeline[] = ['status' => 'approved', 'date' => $application['approved_at']];
        }
        if ($application['issued_at']) {
            $timeline[] = ['status' => 'issued', 'date' => $application['issued_at']];
        }
        if ($application['collected_at']) {
            $timeline[] = ['status' => 'collected', 'date' => $application['collected_at']];
        }

        return $timeline;
    }

    private function validateDocument($file, $documentType) {
        $allowedTypes = [
            'birth_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
            'marriage_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
            'national_id' => ['application/pdf', 'image/jpeg', 'image/png'],
            'previous_passport' => ['application/pdf', 'image/jpeg', 'image/png'],
            'police_clearance' => ['application/pdf', 'image/jpeg', 'image/png']
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

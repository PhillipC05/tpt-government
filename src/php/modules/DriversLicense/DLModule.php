<?php
/**
 * Driver's License & Vehicle Registration Module
 * Handles driver's license applications, renewals, and vehicle registration
 */

require_once __DIR__ . '/../ServiceModule.php';

class DLModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Driver\'s License & Vehicle Registration Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive DMV services for driver\'s licenses and vehicle registration',
            'dependencies' => ['IdentityServices', 'PaymentGateway'],
            'permissions' => [
                'dl.apply' => 'Apply for driver\'s license',
                'dl.renew' => 'Renew driver\'s license',
                'dl.status' => 'Check license status',
                'vehicle.register' => 'Register vehicle',
                'vehicle.renew' => 'Renew vehicle registration',
                'dl.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS drivers_licenses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                license_number VARCHAR(20) UNIQUE NOT NULL,
                license_class ENUM('A', 'B', 'C', 'D', 'E') NOT NULL,
                application_type ENUM('new', 'renewal', 'replacement', 'upgrade') NOT NULL,
                status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'issued', 'expired', 'suspended', 'revoked') DEFAULT 'draft',

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender ENUM('male', 'female', 'other') NOT NULL,
                nationality VARCHAR(50) NOT NULL,

                -- Contact Information
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                address TEXT NOT NULL,

                -- Physical Description
                height_cm INT,
                weight_kg INT,
                eye_color VARCHAR(20),
                hair_color VARCHAR(20),

                -- Medical Information
                medical_conditions TEXT,
                vision_test_date DATE,
                vision_test_result ENUM('pass', 'fail', 'conditional'),

                -- License Details
                issue_date DATE NULL,
                expiry_date DATE NULL,
                restrictions TEXT,
                endorsements TEXT,

                -- Documents
                photo_path VARCHAR(255),
                signature_path VARCHAR(255),
                documents_submitted JSON,

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                issued_at TIMESTAMP NULL,
                collected_at TIMESTAMP NULL,

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
                INDEX idx_license_number (license_number),
                INDEX idx_status (status),
                INDEX idx_expiry_date (expiry_date),
                INDEX idx_submitted_at (submitted_at)
            );

            CREATE TABLE IF NOT EXISTS vehicle_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                registration_number VARCHAR(20) UNIQUE NOT NULL,
                vehicle_type ENUM('car', 'motorcycle', 'truck', 'bus', 'trailer', 'other') NOT NULL,
                make VARCHAR(50) NOT NULL,
                model VARCHAR(50) NOT NULL,
                year INT NOT NULL,
                color VARCHAR(30),
                vin VARCHAR(17) UNIQUE NOT NULL,
                engine_number VARCHAR(50),
                chassis_number VARCHAR(50),

                -- Registration Details
                registration_type ENUM('new', 'renewal', 'transfer', 'change') NOT NULL,
                status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'active', 'expired', 'suspended') DEFAULT 'draft',
                issue_date DATE NULL,
                expiry_date DATE NULL,

                -- Vehicle Specifications
                fuel_type ENUM('petrol', 'diesel', 'electric', 'hybrid', 'other'),
                engine_capacity INT,
                seating_capacity INT,
                gross_weight INT,

                -- Insurance
                insurance_policy_number VARCHAR(50),
                insurance_provider VARCHAR(100),
                insurance_expiry_date DATE,

                -- Documents
                registration_document_path VARCHAR(255),
                insurance_document_path VARCHAR(255),
                ownership_document_path VARCHAR(255),
                documents_submitted JSON,

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                approved_at TIMESTAMP NULL,
                issued_at TIMESTAMP NULL,

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
                INDEX idx_registration_number (registration_number),
                INDEX idx_vin (vin),
                INDEX idx_status (status),
                INDEX idx_expiry_date (expiry_date),
                INDEX idx_submitted_at (submitted_at)
            );

            CREATE TABLE IF NOT EXISTS driving_tests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                license_application_id INT NULL,
                test_type ENUM('written', 'practical', 'vision', 'medical') NOT NULL,
                test_date DATE NOT NULL,
                test_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,
                status ENUM('scheduled', 'completed', 'passed', 'failed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                score INT NULL,
                max_score INT NULL,
                result ENUM('pass', 'fail', 'conditional') NULL,
                examiner_id INT NULL,
                examiner_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (license_application_id) REFERENCES drivers_licenses(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_license_application_id (license_application_id),
                INDEX idx_test_date (test_date),
                INDEX idx_status (status)
            );

            CREATE TABLE IF NOT EXISTS traffic_violations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                license_number VARCHAR(20),
                violation_type VARCHAR(100) NOT NULL,
                violation_date DATE NOT NULL,
                location VARCHAR(255),
                description TEXT,
                fine_amount DECIMAL(10,2) NOT NULL,
                points_deducted INT DEFAULT 0,
                status ENUM('pending', 'paid', 'overdue', 'waived', 'contested') DEFAULT 'pending',
                payment_date DATE NULL,
                due_date DATE NOT NULL,

                -- Officer Information
                issuing_officer_id INT,
                issuing_officer_name VARCHAR(100),

                -- Appeal Information
                appeal_filed BOOLEAN DEFAULT FALSE,
                appeal_date DATE NULL,
                appeal_result ENUM('pending', 'approved', 'denied') NULL,
                appeal_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_user_id (user_id),
                INDEX idx_license_number (license_number),
                INDEX idx_violation_date (violation_date),
                INDEX idx_status (status),
                INDEX idx_due_date (due_date)
            );

            CREATE TABLE IF NOT EXISTS dl_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                appointment_type ENUM('written_test', 'practical_test', 'vision_test', 'license_collection', 'vehicle_registration') NOT NULL,
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,
                status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                notes TEXT,
                related_application_id INT NULL,
                related_application_type ENUM('license', 'vehicle') NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_user_id (user_id),
                INDEX idx_appointment_date (appointment_date),
                INDEX idx_status (status),
                INDEX idx_related_application (related_application_id, related_application_type)
            );

            CREATE TABLE IF NOT EXISTS dl_fees (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_type ENUM('license_new', 'license_renewal', 'license_replacement', 'vehicle_registration', 'vehicle_renewal', 'written_test', 'practical_test', 'duplicate_plate') NOT NULL,
                license_class ENUM('A', 'B', 'C', 'D', 'E', 'all') DEFAULT 'all',
                vehicle_type ENUM('car', 'motorcycle', 'truck', 'bus', 'trailer', 'other', 'all') DEFAULT 'all',
                fee_amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                effective_date DATE NOT NULL,
                expiry_date DATE NULL,
                is_active BOOLEAN DEFAULT TRUE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_service_type (service_type),
                INDEX idx_license_class (license_class),
                INDEX idx_vehicle_type (vehicle_type),
                INDEX idx_effective_date (effective_date),
                INDEX idx_is_active (is_active)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup driver's license application workflow
        $licenseWorkflow = [
            'name' => 'Driver\'s License Application Process',
            'description' => 'Complete workflow for driver\'s license applications',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'dl_application_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Vision Test',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'vision_test_form'
                ],
                [
                    'name' => 'Written Test',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'written_test_form'
                ],
                [
                    'name' => 'Practical Test',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'practical_test_form'
                ],
                [
                    'name' => 'Medical Review',
                    'type' => 'service_task',
                    'service' => 'medical_review_service'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'dl_officer',
                    'form' => 'final_approval_form'
                ],
                [
                    'name' => 'License Issuance',
                    'type' => 'service_task',
                    'service' => 'license_issuance_service'
                ]
            ]
        ];

        // Setup vehicle registration workflow
        $vehicleWorkflow = [
            'name' => 'Vehicle Registration Process',
            'description' => 'Complete workflow for vehicle registration',
            'steps' => [
                [
                    'name' => 'Registration Application',
                    'type' => 'user_task',
                    'assignee' => 'owner',
                    'form' => 'vehicle_registration_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Vehicle Inspection',
                    'type' => 'user_task',
                    'assignee' => 'owner',
                    'form' => 'vehicle_inspection_form'
                ],
                [
                    'name' => 'Insurance Verification',
                    'type' => 'service_task',
                    'service' => 'insurance_verification_service'
                ],
                [
                    'name' => 'Registration Approval',
                    'type' => 'user_task',
                    'assignee' => 'registration_officer',
                    'form' => 'registration_approval_form'
                ],
                [
                    'name' => 'Plate/Decal Issuance',
                    'type' => 'service_task',
                    'service' => 'plate_issuance_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/license_workflow.json', json_encode($licenseWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/vehicle_workflow.json', json_encode($vehicleWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/photos',
            __DIR__ . '/uploads/signatures',
            __DIR__ . '/uploads/vehicle_docs',
            __DIR__ . '/uploads/insurance',
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
            'dl_fees',
            'dl_appointments',
            'traffic_violations',
            'driving_tests',
            'vehicle_registrations',
            'drivers_licenses'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods for Driver's License
    public function submitLicenseApplication($data) {
        try {
            $this->validateLicenseApplicationData($data);
            $licenseNumber = $this->generateLicenseNumber();
            $fee = $this->calculateDLFee($data['application_type'], $data['license_class']);

            $sql = "INSERT INTO drivers_licenses (
                user_id, license_number, license_class, application_type,
                first_name, middle_name, last_name, date_of_birth, gender, nationality,
                email, phone, address, height_cm, weight_kg, eye_color, hair_color,
                fee_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['user_id'], $licenseNumber, $data['license_class'], $data['application_type'],
                $data['first_name'], $data['middle_name'] ?? null, $data['last_name'],
                $data['date_of_birth'], $data['gender'], $data['nationality'],
                $data['email'], $data['phone'], json_encode($data['address']),
                $data['height_cm'] ?? null, $data['weight_kg'] ?? null,
                $data['eye_color'] ?? null, $data['hair_color'] ?? null,
                $fee, $data['user_id']
            ];

            $applicationId = $this->db->insert($sql, $params);

            // Schedule tests if new application
            if ($data['application_type'] === 'new') {
                $this->scheduleDrivingTests($applicationId);
            }

            return [
                'success' => true,
                'application_id' => $applicationId,
                'license_number' => $licenseNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitVehicleRegistration($data) {
        try {
            $this->validateVehicleRegistrationData($data);
            $registrationNumber = $this->generateRegistrationNumber();
            $fee = $this->calculateVehicleFee($data['registration_type'], $data['vehicle_type']);

            $sql = "INSERT INTO vehicle_registrations (
                user_id, registration_number, vehicle_type, make, model, year, color,
                vin, engine_number, chassis_number, registration_type,
                fuel_type, engine_capacity, seating_capacity, gross_weight,
                insurance_policy_number, insurance_provider, insurance_expiry_date,
                fee_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['user_id'], $registrationNumber, $data['vehicle_type'],
                $data['make'], $data['model'], $data['year'], $data['color'] ?? null,
                $data['vin'], $data['engine_number'] ?? null, $data['chassis_number'] ?? null,
                $data['registration_type'], $data['fuel_type'] ?? null,
                $data['engine_capacity'] ?? null, $data['seating_capacity'] ?? null,
                $data['gross_weight'] ?? null, $data['insurance_policy_number'] ?? null,
                $data['insurance_provider'] ?? null, $data['insurance_expiry_date'] ?? null,
                $fee, $data['user_id']
            ];

            $registrationId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'registration_id' => $registrationId,
                'registration_number' => $registrationNumber,
                'fee' => $fee
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getLicenseStatus($licenseNumber, $userId) {
        $sql = "SELECT * FROM drivers_licenses WHERE license_number = ? AND user_id = ?";
        $license = $this->db->fetch($sql, [$licenseNumber, $userId]);

        if (!$license) {
            return ['success' => false, 'error' => 'License not found'];
        }

        $violations = $this->getLicenseViolations($licenseNumber);
        $tests = $this->getLicenseTests($license['id']);

        return [
            'success' => true,
            'license' => $license,
            'violations' => $violations,
            'tests' => $tests
        ];
    }

    public function getVehicleStatus($registrationNumber, $userId) {
        $sql = "SELECT * FROM vehicle_registrations WHERE registration_number = ? AND user_id = ?";
        $vehicle = $this->db->fetch($sql, [$registrationNumber, $userId]);

        if (!$vehicle) {
            return ['success' => false, 'error' => 'Vehicle registration not found'];
        }

        return ['success' => true, 'vehicle' => $vehicle];
    }

    public function scheduleDrivingTest($userId, $testType, $date, $time, $location) {
        $sql = "INSERT INTO driving_tests (user_id, test_type, test_date, test_time, location)
                VALUES (?, ?, ?, ?, ?)";

        $testId = $this->db->insert($sql, [$userId, $testType, $date, $time, $location]);

        return [
            'success' => true,
            'test_id' => $testId
        ];
    }

    public function recordTestResult($testId, $result, $score, $maxScore, $notes) {
        $sql = "UPDATE driving_tests SET
                status = 'completed',
                result = ?,
                score = ?,
                max_score = ?,
                examiner_notes = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $this->db->query($sql, [$result, $score, $maxScore, $notes, $testId]);

        return ['success' => true];
    }

    public function recordTrafficViolation($data) {
        $sql = "INSERT INTO traffic_violations (
            user_id, license_number, violation_type, violation_date, location,
            description, fine_amount, points_deducted, due_date,
            issuing_officer_id, issuing_officer_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $violationId = $this->db->insert($sql, [
            $data['user_id'], $data['license_number'] ?? null, $data['violation_type'],
            $data['violation_date'], $data['location'] ?? null, $data['description'],
            $data['fine_amount'], $data['points_deducted'] ?? 0,
            date('Y-m-d', strtotime('+30 days', strtotime($data['violation_date']))),
            $data['issuing_officer_id'] ?? null, $data['issuing_officer_name'] ?? null
        ]);

        return [
            'success' => true,
            'violation_id' => $violationId
        ];
    }

    // Helper Methods
    private function validateLicenseApplicationData($data) {
        $required = ['user_id', 'application_type', 'license_class', 'first_name', 'last_name', 'date_of_birth', 'gender', 'nationality', 'email', 'phone', 'address'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate age (minimum 16 for most licenses)
        $dob = new DateTime($data['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        $minAge = ($data['license_class'] === 'A' || $data['license_class'] === 'B') ? 16 : 18;
        if ($age < $minAge) {
            throw new Exception("Minimum age for {$data['license_class']} license is $minAge years");
        }
    }

    private function validateVehicleRegistrationData($data) {
        $required = ['user_id', 'registration_type', 'vehicle_type', 'make', 'model', 'year', 'vin'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate VIN format (17 characters)
        if (strlen($data['vin']) !== 17) {
            throw new Exception('VIN must be 17 characters long');
        }
    }

    private function generateLicenseNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 8));
        return "DL{$date}{$random}";
    }

    private function generateRegistrationNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "REG{$date}{$random}";
    }

    private function calculateDLFee($applicationType, $licenseClass) {
        $serviceType = "license_{$applicationType}";
        $sql = "SELECT fee_amount FROM dl_fees
                WHERE service_type = ? AND (license_class = ? OR license_class = 'all')
                AND is_active = TRUE AND effective_date <= CURDATE()
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY effective_date DESC LIMIT 1";

        $fee = $this->db->fetch($sql, [$serviceType, $licenseClass]);
        return $fee ? $fee['fee_amount'] : 0;
    }

    private function calculateVehicleFee($registrationType, $vehicleType) {
        $serviceType = "vehicle_{$registrationType}";
        $sql = "SELECT fee_amount FROM dl_fees
                WHERE service_type = ? AND (vehicle_type = ? OR vehicle_type = 'all')
                AND is_active = TRUE AND effective_date <= CURDATE()
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY effective_date DESC LIMIT 1";

        $fee = $this->db->fetch($sql, [$serviceType, $vehicleType]);
        return $fee ? $fee['fee_amount'] : 0;
    }

    private function scheduleDrivingTests($applicationId) {
        $testTypes = ['vision', 'written', 'practical'];
        $baseDate = strtotime('+7 days');

        foreach ($testTypes as $index => $testType) {
            $testDate = date('Y-m-d', strtotime("+{$index} weeks", $baseDate));
            $testTime = '09:00:00';
            $location = 'DMV Testing Center';

            $sql = "INSERT INTO driving_tests (
                user_id, license_application_id, test_type,
                test_date, test_time, location
            ) SELECT user_id, ?, ?, ?, ?, ? FROM drivers_licenses WHERE id = ?";

            $this->db->query($sql, [$applicationId, $testType, $testDate, $testTime, $location, $applicationId]);
        }
    }

    private function getLicenseViolations($licenseNumber) {
        $sql = "SELECT * FROM traffic_violations
                WHERE license_number = ? AND status != 'paid'
                ORDER BY violation_date DESC";
        return $this->db->fetchAll($sql, [$licenseNumber]);
    }

    private function getLicenseTests($applicationId) {
        $sql = "SELECT * FROM driving_tests WHERE license_application_id = ? ORDER BY test_date";
        return $this->db->fetchAll($sql, [$applicationId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

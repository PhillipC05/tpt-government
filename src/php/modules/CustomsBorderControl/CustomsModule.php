<?php
/**
 * Customs & Border Control Module
 * Handles import/export controls, border security, and customs declarations
 */

require_once __DIR__ . '/../ServiceModule.php';

class CustomsModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Customs & Border Control Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive customs and border control management system',
            'dependencies' => ['IdentityServices', 'PaymentGateway', 'ImmigrationCitizenship'],
            'permissions' => [
                'customs.declare' => 'Submit customs declarations',
                'customs.inspect' => 'Perform customs inspections',
                'customs.clear' => 'Clear goods for import/export',
                'border.cross' => 'Process border crossings',
                'customs.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS customs_declarations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                declaration_number VARCHAR(20) UNIQUE NOT NULL,
                declaration_type ENUM('import', 'export', 'transit', 'temporary_import', 'customs_warehouse') NOT NULL,
                status ENUM('draft', 'submitted', 'under_review', 'cleared', 'held', 'rejected', 'seized') DEFAULT 'draft',

                -- Declaration Details
                declarant_id INT NOT NULL,
                declarant_name VARCHAR(200) NOT NULL,
                declarant_address TEXT NOT NULL,
                declarant_phone VARCHAR(20),
                declarant_email VARCHAR(255),

                -- Importer/Exporter Information
                importer_name VARCHAR(200),
                importer_address TEXT,
                importer_tax_id VARCHAR(50),
                exporter_name VARCHAR(200),
                exporter_address TEXT,
                exporter_tax_id VARCHAR(50),

                -- Shipment Information
                shipment_reference VARCHAR(50),
                transport_mode ENUM('air', 'sea', 'land', 'rail', 'postal') NOT NULL,
                carrier_name VARCHAR(100),
                vessel_name VARCHAR(100),
                flight_number VARCHAR(20),
                vehicle_plate VARCHAR(20),
                origin_country VARCHAR(50) NOT NULL,
                destination_country VARCHAR(50) NOT NULL,
                port_of_entry VARCHAR(100) NOT NULL,
                port_of_exit VARCHAR(100),

                -- Goods Information
                total_packages INT NOT NULL,
                total_weight DECIMAL(10,2),
                total_value DECIMAL(15,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'USD',
                incoterms VARCHAR(10),

                -- Processing
                submitted_at TIMESTAMP NULL,
                reviewed_at TIMESTAMP NULL,
                cleared_at TIMESTAMP NULL,
                released_at TIMESTAMP NULL,

                -- Duties and Taxes
                duty_amount DECIMAL(10,2) DEFAULT 0,
                tax_amount DECIMAL(10,2) DEFAULT 0,
                total_amount DECIMAL(10,2) DEFAULT 0,
                payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
                payment_reference VARCHAR(100),

                -- Risk Assessment
                risk_score INT DEFAULT 0,
                risk_category ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                inspection_required BOOLEAN DEFAULT FALSE,
                inspection_type ENUM('documentary', 'physical', 'chemical', 'xray') NULL,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                rejection_reason TEXT,
                seizure_reason TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_declaration_number (declaration_number),
                INDEX idx_declarant_id (declarant_id),
                INDEX idx_status (status),
                INDEX idx_shipment_reference (shipment_reference),
                INDEX idx_submitted_at (submitted_at),
                INDEX idx_risk_score (risk_score)
            );

            CREATE TABLE IF NOT EXISTS customs_goods (
                id INT PRIMARY KEY AUTO_INCREMENT,
                declaration_id INT NOT NULL,
                line_number INT NOT NULL,
                hs_code VARCHAR(10) NOT NULL,
                description TEXT NOT NULL,
                origin_country VARCHAR(50) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit VARCHAR(20) NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                total_value DECIMAL(12,2) NOT NULL,

                -- Customs Information
                duty_rate DECIMAL(5,2) DEFAULT 0,
                duty_amount DECIMAL(10,2) DEFAULT 0,
                tax_rate DECIMAL(5,2) DEFAULT 0,
                tax_amount DECIMAL(10,2) DEFAULT 0,

                -- Additional Information
                weight_kg DECIMAL(8,2),
                package_type VARCHAR(50),
                marks_numbers TEXT,
                license_required BOOLEAN DEFAULT FALSE,
                license_number VARCHAR(50),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (declaration_id) REFERENCES customs_declarations(id) ON DELETE CASCADE,
                INDEX idx_declaration_id (declaration_id),
                INDEX idx_hs_code (hs_code),
                INDEX idx_line_number (line_number)
            );

            CREATE TABLE IF NOT EXISTS customs_inspections (
                id INT PRIMARY KEY AUTO_INCREMENT,
                declaration_id INT NOT NULL,
                inspection_type ENUM('documentary', 'physical', 'chemical', 'xray', 'sampling') NOT NULL,
                status ENUM('scheduled', 'in_progress', 'completed', 'failed') DEFAULT 'scheduled',
                scheduled_date DATE NOT NULL,
                scheduled_time TIME NOT NULL,
                location VARCHAR(255) NOT NULL,

                -- Inspection Details
                inspector_id INT NULL,
                inspector_name VARCHAR(100) NULL,
                inspection_start TIMESTAMP NULL,
                inspection_end TIMESTAMP NULL,
                inspection_result ENUM('pass', 'fail', 'conditional', 'seized') NULL,

                -- Findings
                findings TEXT,
                corrective_actions TEXT,
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE NULL,

                -- Documentation
                inspection_report_path VARCHAR(255),
                photos_evidence JSON,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (declaration_id) REFERENCES customs_declarations(id) ON DELETE CASCADE,
                INDEX idx_declaration_id (declaration_id),
                INDEX idx_inspection_type (inspection_type),
                INDEX idx_status (status),
                INDEX idx_scheduled_date (scheduled_date)
            );

            CREATE TABLE IF NOT EXISTS customs_seizures (
                id INT PRIMARY KEY AUTO_INCREMENT,
                declaration_id INT NOT NULL,
                seizure_number VARCHAR(20) UNIQUE NOT NULL,
                seizure_date DATE NOT NULL,
                seizure_reason TEXT NOT NULL,
                seized_by VARCHAR(100) NOT NULL,
                seizure_location VARCHAR(255) NOT NULL,

                -- Goods Information
                goods_description TEXT NOT NULL,
                quantity_seized DECIMAL(10,2),
                estimated_value DECIMAL(12,2),

                -- Legal Information
                case_number VARCHAR(50),
                court_reference VARCHAR(50),
                legal_action ENUM('pending', 'forfeiture', 'destruction', 'return', 'released') DEFAULT 'pending',

                -- Processing
                notice_issued BOOLEAN DEFAULT FALSE,
                notice_date DATE NULL,
                appeal_filed BOOLEAN DEFAULT FALSE,
                appeal_date DATE NULL,
                appeal_result ENUM('pending', 'approved', 'denied') NULL,

                -- Documentation
                seizure_report_path VARCHAR(255),
                evidence_photos JSON,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (declaration_id) REFERENCES customs_declarations(id) ON DELETE CASCADE,
                INDEX idx_declaration_id (declaration_id),
                INDEX idx_seizure_number (seizure_number),
                INDEX idx_seizure_date (seizure_date),
                INDEX idx_legal_action (legal_action)
            );

            CREATE TABLE IF NOT EXISTS customs_licenses_permits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                license_number VARCHAR(20) UNIQUE NOT NULL,
                license_type ENUM('import', 'export', 'broker', 'warehouse', 'transit') NOT NULL,
                holder_name VARCHAR(200) NOT NULL,
                holder_address TEXT NOT NULL,
                holder_tax_id VARCHAR(50),
                holder_phone VARCHAR(20),
                holder_email VARCHAR(255),

                -- License Details
                issue_date DATE NOT NULL,
                expiry_date DATE NOT NULL,
                status ENUM('active', 'suspended', 'revoked', 'expired') DEFAULT 'active',
                license_category VARCHAR(50),

                -- Conditions and Restrictions
                conditions TEXT,
                restricted_goods TEXT,
                authorized_countries TEXT,

                -- Processing
                issued_by VARCHAR(100) NOT NULL,
                approved_by VARCHAR(100),
                review_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_license_number (license_number),
                INDEX idx_license_type (license_type),
                INDEX idx_holder_name (holder_name),
                INDEX idx_status (status),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS customs_tariffs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                hs_code VARCHAR(10) UNIQUE NOT NULL,
                description TEXT NOT NULL,
                duty_rate DECIMAL(5,2) NOT NULL,
                tax_rate DECIMAL(5,2) DEFAULT 0,
                unit VARCHAR(20),
                effective_date DATE NOT NULL,
                expiry_date DATE NULL,
                is_active BOOLEAN DEFAULT TRUE,

                -- Additional Information
                category VARCHAR(100),
                subcategory VARCHAR(100),
                special_conditions TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_hs_code (hs_code),
                INDEX idx_category (category),
                INDEX idx_effective_date (effective_date),
                INDEX idx_is_active (is_active)
            );

            CREATE TABLE IF NOT EXISTS border_crossings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                crossing_number VARCHAR(20) UNIQUE NOT NULL,
                traveler_id INT NOT NULL,
                traveler_name VARCHAR(200) NOT NULL,
                traveler_passport VARCHAR(20),
                traveler_nationality VARCHAR(50) NOT NULL,

                -- Crossing Details
                crossing_type ENUM('entry', 'exit', 'transit') NOT NULL,
                border_post VARCHAR(100) NOT NULL,
                crossing_date DATE NOT NULL,
                crossing_time TIME NOT NULL,
                transport_mode ENUM('air', 'sea', 'land', 'rail') NOT NULL,

                -- Travel Information
                purpose_of_visit VARCHAR(100),
                intended_stay_duration INT,
                destination_address TEXT,
                return_ticket BOOLEAN DEFAULT FALSE,

                -- Processing
                processed_by VARCHAR(100) NOT NULL,
                processing_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                clearance_status ENUM('approved', 'denied', 'secondary_inspection', 'detained') DEFAULT 'approved',

                -- Additional Information
                visa_number VARCHAR(20),
                visa_type VARCHAR(50),
                visa_expiry DATE,
                prohibited_items TEXT,
                notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_crossing_number (crossing_number),
                INDEX idx_traveler_id (traveler_id),
                INDEX idx_border_post (border_post),
                INDEX idx_crossing_date (crossing_date),
                INDEX idx_clearance_status (clearance_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup import declaration workflow
        $importWorkflow = [
            'name' => 'Import Declaration Process',
            'description' => 'Complete workflow for import customs declarations',
            'steps' => [
                [
                    'name' => 'Declaration Submission',
                    'type' => 'user_task',
                    'assignee' => 'importer',
                    'form' => 'customs_declaration_form'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'service_task',
                    'service' => 'risk_assessment_service'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Duty Calculation',
                    'type' => 'service_task',
                    'service' => 'duty_calculation_service'
                ],
                [
                    'name' => 'Payment Processing',
                    'type' => 'user_task',
                    'assignee' => 'importer',
                    'form' => 'payment_form'
                ],
                [
                    'name' => 'Inspection Decision',
                    'type' => 'service_task',
                    'service' => 'inspection_decision_service'
                ],
                [
                    'name' => 'Final Clearance',
                    'type' => 'user_task',
                    'assignee' => 'customs_officer',
                    'form' => 'clearance_form'
                ]
            ]
        ];

        // Setup border crossing workflow
        $borderWorkflow = [
            'name' => 'Border Crossing Process',
            'description' => 'Complete workflow for border crossing processing',
            'steps' => [
                [
                    'name' => 'Document Presentation',
                    'type' => 'user_task',
                    'assignee' => 'traveler',
                    'form' => 'travel_document_form'
                ],
                [
                    'name' => 'Biometric Verification',
                    'type' => 'service_task',
                    'service' => 'biometric_verification_service'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Risk Assessment',
                    'type' => 'service_task',
                    'service' => 'risk_assessment_service'
                ],
                [
                    'name' => 'Secondary Inspection',
                    'type' => 'user_task',
                    'assignee' => 'border_officer',
                    'form' => 'secondary_inspection_form'
                ],
                [
                    'name' => 'Clearance Decision',
                    'type' => 'user_task',
                    'assignee' => 'border_officer',
                    'form' => 'clearance_decision_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/import_workflow.json', json_encode($importWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/border_workflow.json', json_encode($borderWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/declarations',
            __DIR__ . '/uploads/inspections',
            __DIR__ . '/uploads/seizures',
            __DIR__ . '/uploads/licenses',
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
            'border_crossings',
            'customs_tariffs',
            'customs_licenses_permits',
            'customs_seizures',
            'customs_inspections',
            'customs_goods',
            'customs_declarations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function submitDeclaration($data) {
        try {
            $this->validateDeclarationData($data);
            $declarationNumber = $this->generateDeclarationNumber();
            $riskScore = $this->calculateRiskScore($data);

            // Calculate duties and taxes
            $duties = $this->calculateDutiesAndTaxes($data['goods']);
            $totalAmount = $duties['total_duty'] + $duties['total_tax'];

            $sql = "INSERT INTO customs_declarations (
                declaration_number, declaration_type, declarant_id, declarant_name,
                declarant_address, declarant_phone, declarant_email,
                importer_name, importer_address, importer_tax_id,
                exporter_name, exporter_address, exporter_tax_id,
                transport_mode, carrier_name, vessel_name, flight_number, vehicle_plate,
                origin_country, destination_country, port_of_entry, port_of_exit,
                total_packages, total_weight, total_value, currency,
                duty_amount, tax_amount, total_amount, risk_score,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $declarationNumber, $data['declaration_type'], $data['declarant_id'],
                $data['declarant_name'], json_encode($data['declarant_address']),
                $data['declarant_phone'] ?? null, $data['declarant_email'] ?? null,
                $data['importer_name'] ?? null, json_encode($data['importer_address'] ?? []),
                $data['importer_tax_id'] ?? null, $data['exporter_name'] ?? null,
                json_encode($data['exporter_address'] ?? []), $data['exporter_tax_id'] ?? null,
                $data['transport_mode'], $data['carrier_name'] ?? null,
                $data['vessel_name'] ?? null, $data['flight_number'] ?? null,
                $data['vehicle_plate'] ?? null, $data['origin_country'],
                $data['destination_country'], $data['port_of_entry'],
                $data['port_of_exit'] ?? null, $data['total_packages'],
                $data['total_weight'] ?? null, $data['total_value'], $data['currency'] ?? 'USD',
                $duties['total_duty'], $duties['total_tax'], $totalAmount, $riskScore,
                $data['declarant_id']
            ];

            $declarationId = $this->db->insert($sql, $params);

            // Insert goods details
            $this->insertGoodsDetails($declarationId, $data['goods']);

            // Schedule inspection if required
            if ($riskScore > 70) {
                $this->scheduleInspection($declarationId, 'physical');
            }

            return [
                'success' => true,
                'declaration_id' => $declarationId,
                'declaration_number' => $declarationNumber,
                'risk_score' => $riskScore,
                'total_amount' => $totalAmount
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getDeclarationStatus($declarationNumber, $userId) {
        $sql = "SELECT * FROM customs_declarations WHERE declaration_number = ? AND declarant_id = ?";
        $declaration = $this->db->fetch($sql, [$declarationNumber, $userId]);

        if (!$declaration) {
            return ['success' => false, 'error' => 'Declaration not found'];
        }

        // Get goods details
        $goods = $this->getDeclarationGoods($declaration['id']);

        // Get inspections
        $inspections = $this->getDeclarationInspections($declaration['id']);

        // Get seizures if any
        $seizures = $this->getDeclarationSeizures($declaration['id']);

        return [
            'success' => true,
            'declaration' => $declaration,
            'goods' => $goods,
            'inspections' => $inspections,
            'seizures' => $seizures
        ];
    }

    public function processBorderCrossing($data) {
        try {
            $this->validateBorderCrossingData($data);
            $crossingNumber = $this->generateCrossingNumber();

            $sql = "INSERT INTO border_crossings (
                crossing_number, traveler_id, traveler_name, traveler_passport,
                traveler_nationality, crossing_type, border_post, crossing_date,
                crossing_time, transport_mode, purpose_of_visit, intended_stay_duration,
                destination_address, return_ticket, processed_by, visa_number,
                visa_type, visa_expiry
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $crossingNumber, $data['traveler_id'], $data['traveler_name'],
                $data['traveler_passport'] ?? null, $data['traveler_nationality'],
                $data['crossing_type'], $data['border_post'], $data['crossing_date'],
                $data['crossing_time'], $data['transport_mode'], $data['purpose_of_visit'] ?? null,
                $data['intended_stay_duration'] ?? null, json_encode($data['destination_address'] ?? []),
                $data['return_ticket'] ?? false, $data['processed_by'],
                $data['visa_number'] ?? null, $data['visa_type'] ?? null,
                $data['visa_expiry'] ?? null
            ];

            $crossingId = $this->db->insert($sql, $params);

            return [
                'success' => true,
                'crossing_id' => $crossingId,
                'crossing_number' => $crossingNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForLicense($data) {
        try {
            $this->validateLicenseApplicationData($data);
            $licenseNumber = $this->generateLicenseNumber();

            $sql = "INSERT INTO customs_licenses_permits (
                license_number, license_type, holder_name, holder_address,
                holder_tax_id, holder_phone, holder_email, issue_date,
                expiry_date, issued_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?)";

            $licenseId = $this->db->insert($sql, [
                $licenseNumber, $data['license_type'], $data['holder_name'],
                json_encode($data['holder_address']), $data['holder_tax_id'] ?? null,
                $data['holder_phone'] ?? null, $data['holder_email'] ?? null,
                $data['issued_by']
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

    public function scheduleInspection($declarationId, $inspectionType) {
        $sql = "INSERT INTO customs_inspections (
            declaration_id, inspection_type, scheduled_date, scheduled_time, location
        ) VALUES (?, ?, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', 'Customs Inspection Bay')";

        $inspectionId = $this->db->insert($sql, [$declarationId, $inspectionType]);

        return [
            'success' => true,
            'inspection_id' => $inspectionId
        ];
    }

    public function recordInspectionResult($inspectionId, $result, $findings, $inspectorId) {
        $sql = "UPDATE customs_inspections SET
                status = 'completed',
                inspection_result = ?,
                findings = ?,
                inspector_id = ?,
                inspection_end = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $this->db->query($sql, [$result, $findings, $inspectorId, $inspectionId]);

        // If inspection failed, mark declaration as held
        if ($result === 'fail') {
            $sql = "UPDATE customs_declarations SET status = 'held' WHERE id = (
                SELECT declaration_id FROM customs_inspections WHERE id = ?
            )";
            $this->db->query($sql, [$inspectionId]);
        }

        return ['success' => true];
    }

    public function recordSeizure($data) {
        try {
            $this->validateSeizureData($data);
            $seizureNumber = $this->generateSeizureNumber();

            $sql = "INSERT INTO customs_seizures (
                declaration_id, seizure_number, seizure_date, seizure_reason,
                seized_by, seizure_location, goods_description, quantity_seized,
                estimated_value
            ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)";

            $seizureId = $this->db->insert($sql, [
                $data['declaration_id'], $seizureNumber, $data['seizure_reason'],
                $data['seized_by'], $data['seizure_location'], $data['goods_description'],
                $data['quantity_seized'] ?? null, $data['estimated_value'] ?? null
            ]);

            // Update declaration status
            $sql = "UPDATE customs_declarations SET status = 'seized' WHERE id = ?";
            $this->db->query($sql, [$data['declaration_id']]);

            return [
                'success' => true,
                'seizure_id' => $seizureId,
                'seizure_number' => $seizureNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function searchHSCode($query) {
        $sql = "SELECT * FROM customs_tariffs
                WHERE (hs_code LIKE ? OR description LIKE ?)
                AND is_active = TRUE
                ORDER BY hs_code LIMIT 50";

        $searchTerm = '%' . $query . '%';
        $results = $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);

        return [
            'success' => true,
            'results' => $results
        ];
    }

    // Helper Methods
    private function validateDeclarationData($data) {
        $required = [
            'declaration_type', 'declarant_id', 'declarant_name',
            'declarant_address', 'origin_country', 'destination_country',
            'port_of_entry', 'total_packages', 'total_value', 'goods'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        if (!is_array($data['goods']) || empty($data['goods'])) {
            throw new Exception('Goods information is required');
        }
    }

    private function validateBorderCrossingData($data) {
        $required = [
            'traveler_id', 'traveler_name', 'traveler_nationality',
            'crossing_type', 'border_post', 'crossing_date', 'crossing_time',
            'transport_mode', 'processed_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateLicenseApplicationData($data) {
        $required = [
            'license_type', 'holder_name', 'holder_address', 'issued_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateSeizureData($data) {
        $required = [
            'declaration_id', 'seizure_reason', 'seized_by',
            'seizure_location', 'goods_description'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateDeclarationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CUS{$date}{$random}";
    }

    private function generateCrossingNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BCR{$date}{$random}";
    }

    private function generateLicenseNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CL{$date}{$random}";
    }

    private function generateSeizureNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "SEI{$date}{$random}";
    }

    private function calculateRiskScore($data) {
        $score = 0;

        // High-risk countries
        $highRiskCountries = ['COUNTRY_A', 'COUNTRY_B', 'COUNTRY_C'];
        if (in_array($data['origin_country'], $highRiskCountries)) {
            $score += 30;
        }

        // High-value shipments
        if ($data['total_value'] > 10000) {
            $score += 20;
        }

        // New declarant
        if ($this->isNewDeclarant($data['declarant_id'])) {
            $score += 15;
        }

        // Random factor for additional checks
        $score += rand(0, 10);

        return min($score, 100);
    }

    private function calculateDutiesAndTaxes($goods) {
        $totalDuty = 0;
        $totalTax = 0;

        foreach ($goods as $item) {
            $tariff = $this->getTariffByHSCode($item['hs_code']);
            if ($tariff) {
                $duty = ($item['total_value'] * $tariff['duty_rate']) / 100;
                $tax = ($item['total_value'] * $tariff['tax_rate']) / 100;

                $totalDuty += $duty;
                $totalTax += $tax;
            }
        }

        return [
            'total_duty' => $totalDuty,
            'total_tax' => $totalTax
        ];
    }

    private function getTariffByHSCode($hsCode) {
        $sql = "SELECT * FROM customs_tariffs
                WHERE hs_code = ? AND is_active = TRUE
                AND effective_date <= CURDATE()
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY effective_date DESC LIMIT 1";

        return $this->db->fetch($sql, [$hsCode]);
    }

    private function insertGoodsDetails($declarationId, $goods) {
        foreach ($goods as $index => $item) {
            $tariff = $this->getTariffByHSCode($item['hs_code']);
            $dutyRate = $tariff ? $tariff['duty_rate'] : 0;
            $taxRate = $tariff ? $tariff['tax_rate'] : 0;

            $dutyAmount = ($item['total_value'] * $dutyRate) / 100;
            $taxAmount = ($item['total_value'] * $taxRate) / 100;

            $sql = "INSERT INTO customs_goods (
                declaration_id, line_number, hs_code, description, origin_country,
                quantity, unit, unit_price, total_value, duty_rate, duty_amount,
                tax_rate, tax_amount, weight_kg, package_type, marks_numbers
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->insert($sql, [
                $declarationId, $index + 1, $item['hs_code'], $item['description'],
                $item['origin_country'], $item['quantity'], $item['unit'],
                $item['unit_price'], $item['total_value'], $dutyRate, $dutyAmount,
                $taxRate, $taxAmount, $item['weight_kg'] ?? null,
                $item['package_type'] ?? null, $item['marks_numbers'] ?? null
            ]);
        }
    }

    private function isNewDeclarant($declarantId) {
        $sql = "SELECT COUNT(*) as count FROM customs_declarations WHERE declarant_id = ?";
        $result = $this->db->fetch($sql, [$declarantId]);
        return $result['count'] == 0;
    }

    private function getDeclarationGoods($declarationId) {
        $sql = "SELECT * FROM customs_goods WHERE declaration_id = ? ORDER BY line_number";
        return $this->db->fetchAll($sql, [$declarationId]);
    }

    private function getDeclarationInspections($declarationId) {
        $sql = "SELECT * FROM customs_inspections WHERE declaration_id = ? ORDER BY scheduled_date";
        return $this->db->fetchAll($sql, [$declarationId]);
    }

    private function getDeclarationSeizures($declarationId) {
        $sql = "SELECT * FROM customs_seizures WHERE declaration_id = ? ORDER BY seizure_date";
        return $this->db->fetchAll($sql, [$declarationId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

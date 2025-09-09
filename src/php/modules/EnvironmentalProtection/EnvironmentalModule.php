<?php
/**
 * Environmental Protection Module
 * Handles environmental permits, compliance monitoring, and conservation programs
 */

require_once __DIR__ . '/../ServiceModule.php';

class EnvironmentalModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Environmental Protection Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive environmental protection services including permits, monitoring, and conservation programs',
            'dependencies' => ['IdentityServices', 'HealthServices', 'SocialServices'],
            'permissions' => [
                'environment.permits' => 'Apply for environmental permits',
                'environment.monitoring' => 'Access environmental monitoring',
                'environment.compliance' => 'Manage compliance reporting',
                'environment.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS environmental_permits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permit_number VARCHAR(20) UNIQUE NOT NULL,
                applicant_id INT NOT NULL,

                -- Permit Details
                permit_type ENUM('air_quality', 'water_discharge', 'waste_disposal', 'hazardous_materials', 'land_use', 'mining', 'construction', 'industrial', 'other') NOT NULL,
                permit_category ENUM('major', 'minor', 'general') DEFAULT 'minor',
                permit_description TEXT NOT NULL,

                -- Applicant Information
                applicant_type ENUM('individual', 'business', 'government', 'nonprofit') DEFAULT 'business',
                company_name VARCHAR(200),
                contact_person VARCHAR(100) NOT NULL,
                contact_phone VARCHAR(20) NOT NULL,
                contact_email VARCHAR(255) NOT NULL,

                -- Location Information
                facility_name VARCHAR(200),
                facility_address TEXT NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                county VARCHAR(50),
                zip_code VARCHAR(20),

                -- Permit Status and Timeline
                application_date DATE NOT NULL,
                permit_status ENUM('draft', 'submitted', 'under_review', 'approved', 'denied', 'issued', 'expired', 'revoked', 'suspended') DEFAULT 'draft',
                submitted_date DATE NULL,
                review_start_date DATE NULL,
                approval_date DATE NULL,
                issuance_date DATE NULL,
                expiration_date DATE NULL,
                effective_date DATE NULL,

                -- Environmental Impact
                environmental_impact TEXT,
                mitigation_measures TEXT,
                monitoring_requirements TEXT,

                -- Financial Information
                application_fee DECIMAL(8,2) DEFAULT 0,
                annual_fee DECIMAL(8,2) DEFAULT 0,
                total_fees DECIMAL(10,2) DEFAULT 0,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,
                conditions_of_approval TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_permit_number (permit_number),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_permit_type (permit_type),
                INDEX idx_permit_status (permit_status),
                INDEX idx_application_date (application_date),
                INDEX idx_expiration_date (expiration_date)
            );

            CREATE TABLE IF NOT EXISTS environmental_inspections (
                id INT PRIMARY KEY AUTO_INCREMENT,
                inspection_number VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NULL,

                -- Inspection Details
                inspection_type ENUM('routine', 'complaint', 'follow_up', 'emergency', 'pre_issuance', 'renewal') DEFAULT 'routine',
                inspection_date DATE NOT NULL,
                inspection_time TIME,
                scheduled_date DATE,
                scheduled_time TIME,

                -- Location and Facility
                facility_name VARCHAR(200),
                facility_address TEXT,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),

                -- Inspector Information
                inspector_name VARCHAR(100) NOT NULL,
                inspector_credentials VARCHAR(100),
                inspector_department VARCHAR(100),

                -- Inspection Results
                inspection_status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
                compliance_status ENUM('compliant', 'non_compliant', 'conditional', 'pending') DEFAULT 'pending',
                overall_rating ENUM('excellent', 'good', 'satisfactory', 'poor', 'critical') DEFAULT 'satisfactory',

                -- Findings and Violations
                findings_summary TEXT,
                violations_found INT DEFAULT 0,
                critical_violations INT DEFAULT 0,
                violation_details TEXT,

                -- Corrective Actions
                corrective_actions_required BOOLEAN DEFAULT FALSE,
                corrective_actions TEXT,
                compliance_deadline DATE,
                follow_up_inspection_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE,

                -- Environmental Data
                air_quality_readings TEXT,
                water_quality_readings TEXT,
                noise_level_readings TEXT,
                waste_management_status TEXT,

                -- Documentation
                inspection_report TEXT,
                photographs_taken BOOLEAN DEFAULT FALSE,
                samples_collected BOOLEAN DEFAULT FALSE,

                -- Review and Approval
                supervisor_review_required BOOLEAN DEFAULT FALSE,
                supervisor_review_date DATE,
                supervisor_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (permit_id) REFERENCES environmental_permits(id) ON DELETE SET NULL,
                INDEX idx_inspection_number (inspection_number),
                INDEX idx_permit_id (permit_id),
                INDEX idx_inspection_type (inspection_type),
                INDEX idx_inspection_date (inspection_date),
                INDEX idx_compliance_status (compliance_status)
            );

            CREATE TABLE IF NOT EXISTS environmental_compliance_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_number VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NOT NULL,

                -- Report Details
                report_type ENUM('monthly', 'quarterly', 'annual', 'incident', 'self_audit', 'compliance') DEFAULT 'monthly',
                reporting_period_start DATE NOT NULL,
                reporting_period_end DATE NOT NULL,
                submission_date DATE NOT NULL,
                due_date DATE NOT NULL,

                -- Facility Information
                facility_name VARCHAR(200),
                facility_address TEXT,
                permit_number VARCHAR(20),

                -- Compliance Status
                overall_compliance_status ENUM('compliant', 'non_compliant', 'conditional', 'pending_review') DEFAULT 'pending_review',
                compliance_percentage DECIMAL(5,2),

                -- Environmental Metrics
                emissions_data TEXT,
                discharge_data TEXT,
                waste_generation TEXT,
                energy_consumption TEXT,
                water_usage TEXT,

                -- Violations and Incidents
                violations_reported INT DEFAULT 0,
                incidents_reported INT DEFAULT 0,
                corrective_actions_taken TEXT,

                -- Monitoring Data
                air_monitoring_results TEXT,
                water_monitoring_results TEXT,
                soil_monitoring_results TEXT,
                noise_monitoring_results TEXT,

                -- Review Information
                reviewer_id INT NULL,
                review_date DATE NULL,
                review_notes TEXT,
                approval_status ENUM('pending', 'approved', 'rejected', 'requires_revision') DEFAULT 'pending',

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (permit_id) REFERENCES environmental_permits(id) ON DELETE CASCADE,
                INDEX idx_report_number (report_number),
                INDEX idx_permit_id (permit_id),
                INDEX idx_report_type (report_type),
                INDEX idx_submission_date (submission_date),
                INDEX idx_overall_compliance_status (overall_compliance_status)
            );

            CREATE TABLE IF NOT EXISTS environmental_violations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                violation_number VARCHAR(20) UNIQUE NOT NULL,
                permit_id INT NOT NULL,
                inspection_id INT NULL,

                -- Violation Details
                violation_date DATE NOT NULL,
                violation_type ENUM('air_emission', 'water_discharge', 'waste_disposal', 'hazardous_material', 'noise', 'odor', 'visual', 'documentation', 'other') NOT NULL,
                severity_level ENUM('minor', 'moderate', 'major', 'critical') DEFAULT 'minor',

                -- Violation Description
                violation_description TEXT NOT NULL,
                regulatory_reference VARCHAR(100),
                potential_harm TEXT,

                -- Corrective Actions
                corrective_action_required TEXT,
                corrective_action_deadline DATE,
                corrective_action_completed BOOLEAN DEFAULT FALSE,
                completion_date DATE,
                verification_method TEXT,

                -- Penalties and Fines
                penalty_assessed BOOLEAN DEFAULT FALSE,
                penalty_amount DECIMAL(10,2) DEFAULT 0,
                penalty_type ENUM('warning', 'fine', 'permit_suspension', 'permit_revocation', 'criminal_referral') DEFAULT 'warning',
                penalty_justification TEXT,

                -- Appeal Information
                appeal_filed BOOLEAN DEFAULT FALSE,
                appeal_date DATE NULL,
                appeal_result ENUM('pending', 'upheld', 'overturned', 'modified') NULL,
                appeal_notes TEXT,

                -- Resolution
                resolution_status ENUM('open', 'corrected', 'resolved', 'escalated') DEFAULT 'open',
                resolution_date DATE,
                resolution_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (permit_id) REFERENCES environmental_permits(id) ON DELETE CASCADE,
                FOREIGN KEY (inspection_id) REFERENCES environmental_inspections(id) ON DELETE SET NULL,
                INDEX idx_violation_number (violation_number),
                INDEX idx_permit_id (permit_id),
                INDEX idx_violation_type (violation_type),
                INDEX idx_severity_level (severity_level),
                INDEX idx_resolution_status (resolution_status)
            );

            CREATE TABLE IF NOT EXISTS environmental_monitoring_stations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                station_code VARCHAR(20) UNIQUE NOT NULL,
                station_name VARCHAR(200) NOT NULL,

                -- Location Information
                latitude DECIMAL(10,8) NOT NULL,
                longitude DECIMAL(11,8) NOT NULL,
                address TEXT NOT NULL,
                county VARCHAR(50),
                watershed VARCHAR(100),

                -- Station Details
                station_type ENUM('air_quality', 'water_quality', 'meteorological', 'noise', 'radiation', 'multi_parameter') NOT NULL,
                monitoring_purpose TEXT,
                installation_date DATE,

                -- Equipment and Sensors
                equipment_list TEXT,
                sensor_types TEXT,
                data_collection_frequency ENUM('continuous', 'hourly', 'daily', 'weekly', 'monthly') DEFAULT 'hourly',

                -- Operational Status
                operational_status ENUM('active', 'maintenance', 'calibration', 'offline', 'decommissioned') DEFAULT 'active',
                last_maintenance_date DATE,
                next_maintenance_date DATE,
                last_calibration_date DATE,
                next_calibration_date DATE,

                -- Data Management
                data_storage_location VARCHAR(500),
                real_time_data_available BOOLEAN DEFAULT FALSE,
                public_data_access BOOLEAN DEFAULT TRUE,

                -- Responsible Party
                responsible_agency VARCHAR(100),
                contact_person VARCHAR(100),
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_station_code (station_code),
                INDEX idx_station_type (station_type),
                INDEX idx_operational_status (operational_status)
            );

            CREATE TABLE IF NOT EXISTS environmental_conservation_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('land_conservation', 'water_conservation', 'wildlife_protection', 'wetland_restoration', 'forest_management', 'climate_adaptation', 'pollution_prevention', 'other') NOT NULL,
                program_category ENUM('grant', 'incentive', 'regulatory', 'educational', 'partnership') DEFAULT 'grant',

                -- Eligibility and Requirements
                eligibility_criteria TEXT,
                application_requirements TEXT,
                minimum_participants INT DEFAULT 1,
                maximum_participants INT,

                -- Program Benefits
                financial_incentives TEXT,
                technical_assistance TEXT,
                educational_resources TEXT,

                -- Implementation
                implementation_timeline TEXT,
                success_metrics TEXT,
                monitoring_requirements TEXT,

                -- Program Status
                program_status ENUM('active', 'inactive', 'pilot', 'completed', 'cancelled') DEFAULT 'active',
                start_date DATE,
                end_date DATE,
                application_deadline DATE,

                -- Administration
                administering_agency VARCHAR(100),
                contact_person VARCHAR(100),
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_program_status (program_status)
            );

            CREATE TABLE IF NOT EXISTS environmental_emergency_incidents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                incident_number VARCHAR(20) UNIQUE NOT NULL,

                -- Incident Details
                incident_date DATE NOT NULL,
                incident_time TIME NOT NULL,
                incident_type ENUM('oil_spill', 'chemical_release', 'hazardous_material', 'water_contamination', 'air_emission', 'wildfire', 'flood', 'other') NOT NULL,
                incident_severity ENUM('minor', 'moderate', 'major', 'critical') DEFAULT 'minor',

                -- Location Information
                location_description TEXT NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                affected_area_size DECIMAL(10,2), -- in acres or square meters
                affected_population INT,

                -- Incident Description
                incident_description TEXT NOT NULL,
                cause_of_incident TEXT,
                immediate_actions_taken TEXT,

                -- Response Information
                response_agency VARCHAR(100),
                response_coordinator VARCHAR(100),
                response_start_time TIMESTAMP,
                containment_achieved BOOLEAN DEFAULT FALSE,
                containment_time TIMESTAMP,

                -- Environmental Impact
                environmental_impact TEXT,
                wildlife_affected TEXT,
                water_bodies_affected TEXT,
                air_quality_impact TEXT,

                -- Cleanup and Recovery
                cleanup_required BOOLEAN DEFAULT FALSE,
                cleanup_start_date DATE,
                cleanup_completion_date DATE,
                estimated_cleanup_cost DECIMAL(12,2),

                -- Investigation and Follow-up
                investigation_required BOOLEAN DEFAULT TRUE,
                investigation_findings TEXT,
                preventive_measures TEXT,

                -- Status and Resolution
                incident_status ENUM('reported', 'responding', 'contained', 'cleaning_up', 'resolved', 'under_investigation') DEFAULT 'reported',
                resolution_date DATE,
                final_report TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_incident_number (incident_number),
                INDEX idx_incident_type (incident_type),
                INDEX idx_incident_date (incident_date),
                INDEX idx_incident_severity (incident_severity),
                INDEX idx_incident_status (incident_status)
            );

            CREATE TABLE IF NOT EXISTS environmental_education_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                target_audience ENUM('schools', 'businesses', 'general_public', 'professionals', 'government', 'all') DEFAULT 'general_public',
                program_format ENUM('workshop', 'seminar', 'webinar', 'field_trip', 'online_course', 'conference', 'other') NOT NULL,
                duration_hours DECIMAL(4,1),

                -- Content and Topics
                topics_covered TEXT,
                learning_objectives TEXT,
                materials_provided TEXT,

                -- Schedule and Capacity
                program_schedule TEXT,
                max_participants INT,
                min_participants INT DEFAULT 1,

                -- Delivery Information
                delivery_method ENUM('in_person', 'online', 'hybrid') DEFAULT 'in_person',
                location VARCHAR(200),
                instructor_name VARCHAR(100),
                instructor_credentials VARCHAR(200),

                -- Program Status
                program_status ENUM('active', 'inactive', 'full', 'cancelled') DEFAULT 'active',
                registration_deadline DATE,
                program_fee DECIMAL(6,2) DEFAULT 0,

                -- Evaluation
                evaluation_required BOOLEAN DEFAULT FALSE,
                satisfaction_rating DECIMAL(3,2),
                completion_rate DECIMAL(5,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_target_audience (target_audience),
                INDEX idx_program_status (program_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup permit application workflow
        $permitWorkflow = [
            'name' => 'Environmental Permit Application Process',
            'description' => 'Complete workflow for environmental permit applications',
            'steps' => [
                [
                    'name' => 'Permit Application',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'permit_application_form'
                ],
                [
                    'name' => 'Initial Review',
                    'type' => 'service_task',
                    'service' => 'initial_review_service'
                ],
                [
                    'name' => 'Technical Review',
                    'type' => 'user_task',
                    'assignee' => 'environmental_technician',
                    'form' => 'technical_review_form'
                ],
                [
                    'name' => 'Public Notice',
                    'type' => 'service_task',
                    'service' => 'public_notice_service'
                ],
                [
                    'name' => 'Final Review',
                    'type' => 'user_task',
                    'assignee' => 'environmental_officer',
                    'form' => 'final_review_form'
                ],
                [
                    'name' => 'Permit Issuance',
                    'type' => 'service_task',
                    'service' => 'permit_issuance_service'
                ]
            ]
        ];

        // Setup inspection workflow
        $inspectionWorkflow = [
            'name' => 'Environmental Inspection Process',
            'description' => 'Complete workflow for environmental inspections',
            'steps' => [
                [
                    'name' => 'Inspection Scheduling',
                    'type' => 'service_task',
                    'service' => 'inspection_scheduling_service'
                ],
                [
                    'name' => 'Pre-Inspection Preparation',
                    'type' => 'user_task',
                    'assignee' => 'inspector',
                    'form' => 'inspection_preparation_form'
                ],
                [
                    'name' => 'Field Inspection',
                    'type' => 'user_task',
                    'assignee' => 'inspector',
                    'form' => 'field_inspection_form'
                ],
                [
                    'name' => 'Report Generation',
                    'type' => 'service_task',
                    'service' => 'report_generation_service'
                ],
                [
                    'name' => 'Supervisor Review',
                    'type' => 'user_task',
                    'assignee' => 'supervisor',
                    'form' => 'supervisor_review_form'
                ],
                [
                    'name' => 'Corrective Action Planning',
                    'type' => 'service_task',
                    'service' => 'corrective_action_service'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/permit_workflow.json', json_encode($permitWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/inspection_workflow.json', json_encode($inspectionWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/permit_applications',
            __DIR__ . '/uploads/inspection_reports',
            __DIR__ . '/uploads/compliance_reports',
            __DIR__ . '/uploads/monitoring_data',
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
            'environmental_education_programs',
            'environmental_emergency_incidents',
            'environmental_conservation_programs',
            'environmental_monitoring_stations',
            'environmental_violations',
            'environmental_compliance_reports',
            'environmental_inspections',
            'environmental_permits'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function applyForPermit($data) {
        try {
            $this->validatePermitApplicationData($data);
            $permitNumber = $this->generatePermitNumber();

            $sql = "INSERT INTO environmental_permits (
                permit_number, applicant_id, permit_type, permit_category,
                permit_description, applicant_type, company_name, contact_person,
                contact_phone, contact_email, facility_name, facility_address,
                latitude, longitude, county, zip_code, application_date,
                environmental_impact, mitigation_measures, monitoring_requirements,
                application_fee, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $permitId = $this->db->insert($sql, [
                $permitNumber, $data['applicant_id'], $data['permit_type'],
                $data['permit_category'] ?? 'minor', $data['permit_description'],
                $data['applicant_type'] ?? 'business', $data['company_name'] ?? null,
                $data['contact_person'], $data['contact_phone'], $data['contact_email'],
                $data['facility_name'] ?? null, json_encode($data['facility_address']),
                $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['county'] ?? null, $data['zip_code'] ?? null,
                $data['application_date'], $data['environmental_impact'] ?? null,
                $data['mitigation_measures'] ?? null, $data['monitoring_requirements'] ?? null,
                $data['application_fee'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'permit_id' => $permitId,
                'permit_number' => $permitNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function scheduleInspection($data) {
        try {
            $this->validateInspectionData($data);
            $inspectionNumber = $this->generateInspectionNumber();

            $sql = "INSERT INTO environmental_inspections (
                inspection_number, permit_id, inspection_type, inspection_date,
                inspection_time, scheduled_date, scheduled_time, facility_name,
                facility_address, latitude, longitude, inspector_name,
                inspector_credentials, inspector_department, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $inspectionId = $this->db->insert($sql, [
                $inspectionNumber, $data['permit_id'] ?? null, $data['inspection_type'] ?? 'routine',
                $data['inspection_date'], $data['inspection_time'] ?? null,
                $data['scheduled_date'] ?? null, $data['scheduled_time'] ?? null,
                $data['facility_name'] ?? null, json_encode($data['facility_address'] ?? []),
                $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['inspector_name'], $data['inspector_credentials'] ?? null,
                $data['inspector_department'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'inspection_id' => $inspectionId,
                'inspection_number' => $inspectionNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitComplianceReport($data) {
        try {
            $this->validateComplianceReportData($data);
            $reportNumber = $this->generateReportNumber();

            $sql = "INSERT INTO environmental_compliance_reports (
                report_number, permit_id, report_type, reporting_period_start,
                reporting_period_end, submission_date, due_date, facility_name,
                facility_address, permit_number, emissions_data, discharge_data,
                waste_generation, energy_consumption, water_usage, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $reportId = $this->db->insert($sql, [
                $reportNumber, $data['permit_id'], $data['report_type'] ?? 'monthly',
                $data['reporting_period_start'], $data['reporting_period_end'],
                $data['submission_date'], $data['due_date'], $data['facility_name'] ?? null,
                json_encode($data['facility_address'] ?? []), $data['permit_number'] ?? null,
                json_encode($data['emissions_data'] ?? []), json_encode($data['discharge_data'] ?? []),
                json_encode($data['waste_generation'] ?? []), json_encode($data['energy_consumption'] ?? []),
                json_encode($data['water_usage'] ?? []), $data['created_by']
            ]);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_number' => $reportNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function recordViolation($data) {
        try {
            $this->validateViolationData($data);
            $violationNumber = $this->generateViolationNumber();

            $sql = "INSERT INTO environmental_violations (
                violation_number, permit_id, inspection_id, violation_date,
                violation_type, severity_level, violation_description,
                regulatory_reference, potential_harm, corrective_action_required,
                corrective_action_deadline, penalty_assessed, penalty_amount,
                penalty_type, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $violationId = $this->db->insert($sql, [
                $violationNumber, $data['permit_id'], $data['inspection_id'] ?? null,
                $data['violation_date'], $data['violation_type'], $data['severity_level'] ?? 'minor',
                $data['violation_description'], $data['regulatory_reference'] ?? null,
                $data['potential_harm'] ?? null, $data['corrective_action_required'] ?? null,
                $data['corrective_action_deadline'] ?? null, $data['penalty_assessed'] ?? false,
                $data['penalty_amount'] ?? 0, $data['penalty_type'] ?? 'warning',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'violation_id' => $violationId,
                'violation_number' => $violationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerMonitoringStation($data) {
        try {
            $this->validateMonitoringStationData($data);
            $stationCode = $this->generateStationCode();

            $sql = "INSERT INTO environmental_monitoring_stations (
                station_code, station_name, latitude, longitude, address,
                county, watershed, station_type, monitoring_purpose,
                installation_date, equipment_list, sensor_types,
                data_collection_frequency, responsible_agency, contact_person,
                contact_phone, contact_email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stationId = $this->db->insert($sql, [
                $stationCode, $data['station_name'], $data['latitude'],
                $data['longitude'], json_encode($data['address']),
                $data['county'] ?? null, $data['watershed'] ?? null,
                $data['station_type'], $data['monitoring_purpose'] ?? null,
                $data['installation_date'] ?? null, json_encode($data['equipment_list'] ?? []),
                json_encode($data['sensor_types'] ?? []), $data['data_collection_frequency'] ?? 'hourly',
                $data['responsible_agency'] ?? null, $data['contact_person'] ?? null,
                $data['contact_phone'] ?? null, $data['contact_email'] ?? null
            ]);

            return [
                'success' => true,
                'station_id' => $stationId,
                'station_code' => $stationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function reportEmergencyIncident($data) {
        try {
            $this->validateEmergencyIncidentData($data);
            $incidentNumber = $this->generateIncidentNumber();

            $sql = "INSERT INTO environmental_emergency_incidents (
                incident_number, incident_date, incident_time, incident_type,
                incident_severity, location_description, latitude, longitude,
                affected_area_size, affected_population, incident_description,
                cause_of_incident, immediate_actions_taken, response_agency,
                response_coordinator, cleanup_required, investigation_required,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $incidentId = $this->db->insert($sql, [
                $incidentNumber, $data['incident_date'], $data['incident_time'],
                $data['incident_type'], $data['incident_severity'] ?? 'minor',
                $data['location_description'], $data['latitude'] ?? null,
                $data['longitude'] ?? null, $data['affected_area_size'] ?? null,
                $data['affected_population'] ?? null, $data['incident_description'],
                $data['cause_of_incident'] ?? null, $data['immediate_actions_taken'] ?? null,
                $data['response_agency'] ?? null, $data['response_coordinator'] ?? null,
                $data['cleanup_required'] ?? false, $data['investigation_required'] ?? true,
                $data['created_by']
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

    public function getPermitRecord($permitNumber, $userId) {
        $sql = "SELECT * FROM environmental_permits WHERE permit_number = ?";
        $permit = $this->db->fetch($sql, [$permitNumber]);

        if (!$permit) {
            return ['success' => false, 'error' => 'Permit not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToPermit($permit, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $inspections = $this->getPermitInspections($permit['id']);
        $complianceReports = $this->getPermitComplianceReports($permit['id']);
        $violations = $this->getPermitViolations($permit['id']);

        return [
            'success' => true,
            'permit' => $permit,
            'inspections' => $inspections,
            'compliance_reports' => $complianceReports,
            'violations' => $violations
        ];
    }

    public function getMonitoringStations($stationType = null) {
        $where = ["operational_status = 'active'"];
        $params = [];

        if ($stationType) {
            $where[] = "station_type = ?";
            $params[] = $stationType;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM environmental_monitoring_stations WHERE $whereClause ORDER BY station_name";

        $stations = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'stations' => $stations
        ];
    }

    public function getConservationPrograms($programType = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($programType) {
            $where[] = "program_type = ?";
            $params[] = $programType;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM environmental_conservation_programs WHERE $whereClause ORDER BY program_name";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getEducationPrograms($targetAudience = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($targetAudience) {
            $where[] = "target_audience = ?";
            $params[] = $targetAudience;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM environmental_education_programs WHERE $whereClause ORDER BY program_name";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    // Helper Methods
    private function validatePermitApplicationData($data) {
        $required = [
            'applicant_id', 'permit_type', 'permit_description',
            'contact_person', 'contact_phone', 'contact_email',
            'facility_address', 'application_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateInspectionData($data) {
        $required = [
            'inspection_date', 'inspector_name', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateComplianceReportData($data) {
        $required = [
            'permit_id', 'reporting_period_start', 'reporting_period_end',
            'submission_date', 'due_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateViolationData($data) {
        $required = [
            'permit_id', 'violation_date', 'violation_type',
            'violation_description', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateMonitoringStationData($data) {
        $required = [
            'station_name', 'latitude', 'longitude', 'address', 'station_type'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateEmergencyIncidentData($data) {
        $required = [
            'incident_date', 'incident_time', 'incident_type',
            'location_description', 'incident_description', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generatePermitNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ENV{$date}{$random}";
    }

    private function generateInspectionNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "INS{$date}{$random}";
    }

    private function generateReportNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "REP{$date}{$random}";
    }

    private function generateViolationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "VIO{$date}{$random}";
    }

    private function generateStationCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        return "STA{$date}{$random}";
    }

    private function generateIncidentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "EMI{$date}{$random}";
    }

    private function hasAccessToPermit($permit, $userId) {
        // Check if user is the applicant or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getPermitInspections($permitId) {
        $sql = "SELECT * FROM environmental_inspections WHERE permit_id = ? ORDER BY inspection_date DESC";
        return $this->db->fetchAll($sql, [$permitId]);
    }

    private function getPermitComplianceReports($permitId) {
        $sql = "SELECT * FROM environmental_compliance_reports WHERE permit_id = ? ORDER BY submission_date DESC";
        return $this->db->fetchAll($sql, [$permitId]);
    }

    private function getPermitViolations($permitId) {
        $sql = "SELECT * FROM environmental_violations WHERE permit_id = ? ORDER BY violation_date DESC";
        return $this->db->fetchAll($sql, [$permitId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

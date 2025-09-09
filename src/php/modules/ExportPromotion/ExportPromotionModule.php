<?php
/**
 * Export Promotion Module
 * Handles export assistance, trade missions, and market intelligence
 */

require_once __DIR__ . '/../ServiceModule.php';

class ExportPromotionModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Export Promotion Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive export promotion and trade facilitation system including market intelligence, trade missions, export assistance, and international market development',
            'dependencies' => ['FinancialManagement', 'BusinessLicenses', 'Procurement'],
            'permissions' => [
                'export.view' => 'View export opportunities and market data',
                'export.apply' => 'Apply for export assistance programs',
                'export.admin' => 'Administrative functions for export promotion',
                'export.approve' => 'Approve export assistance applications'
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
            CREATE TABLE IF NOT EXISTS export_opportunities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                opportunity_code VARCHAR(20) UNIQUE NOT NULL,
                opportunity_name VARCHAR(200) NOT NULL,
                opportunity_description TEXT,

                -- Opportunity Details
                target_country VARCHAR(100) NOT NULL,
                target_region VARCHAR(100),
                product_category VARCHAR(100) NOT NULL,
                sub_category VARCHAR(100),

                -- Market Information
                market_size DECIMAL(20,2),
                market_growth_rate DECIMAL(5,2),
                competition_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
                entry_barriers TEXT,

                -- Trade Requirements
                import_tariffs DECIMAL(5,2),
                import_quotas TEXT,
                regulatory_requirements TEXT,
                certification_requirements TEXT,

                -- Market Intelligence
                market_trends TEXT,
                consumer_preferences TEXT,
                distribution_channels TEXT,
                pricing_strategy TEXT,

                -- Opportunity Status
                opportunity_status ENUM('draft', 'published', 'under_review', 'approved', 'closed', 'cancelled') DEFAULT 'draft',
                publish_date DATE,
                expiry_date DATE,

                -- Contact Information
                contact_person VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),
                trade_office VARCHAR(100),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_opportunity_code (opportunity_code),
                INDEX idx_target_country (target_country),
                INDEX idx_product_category (product_category),
                INDEX idx_opportunity_status (opportunity_status),
                INDEX idx_publish_date (publish_date),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS export_assistance_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_category ENUM('financial', 'market_research', 'trade_mission', 'capacity_building', 'certification', 'other') DEFAULT 'financial',
                assistance_type ENUM('grant', 'loan', 'subsidy', 'training', 'consulting', 'marketing') DEFAULT 'grant',

                -- Eligibility Criteria
                eligible_products TEXT,
                eligible_companies TEXT,
                minimum_export_value DECIMAL(15,2),
                maximum_assistance DECIMAL(15,2),

                -- Program Terms
                assistance_percentage DECIMAL(5,2),
                maximum_duration_months INT DEFAULT 12,
                repayment_terms TEXT,

                -- Application Process
                application_deadline DATE,
                review_process TEXT,
                approval_authority VARCHAR(100),

                -- Program Status
                program_status ENUM('draft', 'active', 'inactive', 'expired', 'cancelled') DEFAULT 'draft',
                effective_date DATE,
                expiry_date DATE,

                -- Usage Tracking
                total_allocated DECIMAL(15,2) DEFAULT 0,
                total_utilized DECIMAL(15,2) DEFAULT 0,
                applications_received INT DEFAULT 0,
                applications_approved INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_category (program_category),
                INDEX idx_program_status (program_status),
                INDEX idx_effective_date (effective_date),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS exporter_profiles (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exporter_code VARCHAR(20) UNIQUE NOT NULL,
                company_name VARCHAR(200) NOT NULL,
                company_description TEXT,

                -- Company Details
                company_type ENUM('manufacturer', 'trader', 'service_provider', 'other') DEFAULT 'manufacturer',
                industry_sector VARCHAR(100) NOT NULL,
                export_experience_years INT,

                -- Contact Information
                primary_contact_name VARCHAR(200) NOT NULL,
                primary_contact_title VARCHAR(100),
                primary_contact_email VARCHAR(255),
                primary_contact_phone VARCHAR(20),

                -- Export History
                total_export_value DECIMAL(20,2) DEFAULT 0,
                export_countries TEXT,
                main_export_products TEXT,

                -- Company Financials
                annual_turnover DECIMAL(20,2),
                export_turnover DECIMAL(20,2),
                number_of_employees INT,

                -- Export Capabilities
                production_capacity TEXT,
                quality_certifications TEXT,
                export_licenses TEXT,

                -- Profile Status
                profile_status ENUM('draft', 'active', 'inactive', 'suspended', 'blacklisted') DEFAULT 'draft',
                verification_status ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'unverified',

                -- Additional Information
                website VARCHAR(255),
                linkedin_profile VARCHAR(255),
                references TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_exporter_code (exporter_code),
                INDEX idx_company_type (company_type),
                INDEX idx_industry_sector (industry_sector),
                INDEX idx_profile_status (profile_status),
                INDEX idx_verification_status (verification_status)
            );

            CREATE TABLE IF NOT EXISTS export_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                exporter_id INT NOT NULL,
                program_id INT,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'withdrawn', 'conditional_approval') DEFAULT 'draft',

                -- Export Proposal
                target_markets TEXT,
                export_products TEXT,
                proposed_export_value DECIMAL(15,2) NOT NULL,
                export_description TEXT,

                -- Business Plan
                market_analysis TEXT,
                marketing_strategy TEXT,
                competitive_advantage TEXT,
                risk_assessment TEXT,

                -- Requested Assistance
                assistance_requested TEXT,
                assistance_justification TEXT,
                expected_benefits TEXT,

                -- Review Information
                review_date DATE,
                reviewer_name VARCHAR(100),
                review_notes TEXT,
                approval_date DATE,

                -- Approval Conditions
                approval_conditions TEXT,
                conditions_met BOOLEAN DEFAULT FALSE,
                conditions_met_date DATE,

                -- Implementation Tracking
                implementation_start_date DATE,
                implementation_status ENUM('not_started', 'in_progress', 'completed', 'delayed', 'cancelled') DEFAULT 'not_started',
                actual_export_value DECIMAL(15,2) DEFAULT 0,
                markets_entered TEXT,

                -- Supporting Documents
                business_plan_document VARCHAR(255),
                financial_statements VARCHAR(255),
                export_licenses VARCHAR(255),
                other_documents TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (exporter_id) REFERENCES exporter_profiles(id) ON DELETE CASCADE,
                FOREIGN KEY (program_id) REFERENCES export_assistance_programs(id) ON DELETE SET NULL,
                INDEX idx_application_number (application_number),
                INDEX idx_exporter_id (exporter_id),
                INDEX idx_program_id (program_id),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date),
                INDEX idx_implementation_status (implementation_status)
            );

            CREATE TABLE IF NOT EXISTS trade_missions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                mission_code VARCHAR(20) UNIQUE NOT NULL,
                mission_name VARCHAR(200) NOT NULL,
                mission_description TEXT,

                -- Mission Details
                mission_type ENUM('incoming_buyer', 'outgoing_trade', 'virtual_mission', 'follow_up') DEFAULT 'outgoing_trade',
                target_country VARCHAR(100) NOT NULL,
                target_region VARCHAR(100),

                -- Schedule and Location
                mission_start_date DATE NOT NULL,
                mission_end_date DATE NOT NULL,
                mission_location VARCHAR(200),

                -- Target Participants
                target_sectors TEXT,
                target_company_sizes TEXT,
                expected_participants INT,

                -- Mission Content
                mission_objectives TEXT,
                planned_activities TEXT,
                business_matching_events BOOLEAN DEFAULT TRUE,

                -- Logistics
                participation_fee DECIMAL(8,2) DEFAULT 0,
                application_deadline DATE,
                maximum_participants INT,

                -- Status and Tracking
                mission_status ENUM('planned', 'announced', 'registration_open', 'registration_closed', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
                registered_participants INT DEFAULT 0,
                confirmed_participants INT DEFAULT 0,

                -- Outcomes
                business_meetings_conducted INT DEFAULT 0,
                export_contracts_signed DECIMAL(15,2) DEFAULT 0,
                follow_up_actions TEXT,

                -- Contact Information
                mission_coordinator VARCHAR(200),
                coordinator_email VARCHAR(255),
                coordinator_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_mission_code (mission_code),
                INDEX idx_mission_type (mission_type),
                INDEX idx_target_country (target_country),
                INDEX idx_mission_start_date (mission_start_date),
                INDEX idx_mission_status (mission_status)
            );

            CREATE TABLE IF NOT EXISTS mission_participants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                participant_code VARCHAR(20) UNIQUE NOT NULL,
                mission_id INT NOT NULL,
                exporter_id INT,

                -- Participant Information
                participant_name VARCHAR(200) NOT NULL,
                participant_company VARCHAR(200),
                participant_country VARCHAR(100),
                participant_email VARCHAR(255),
                participant_phone VARCHAR(20),

                -- Registration Details
                registration_date DATE NOT NULL,
                registration_status ENUM('registered', 'confirmed', 'attended', 'no_show', 'cancelled') DEFAULT 'registered',

                -- Participation Tracking
                check_in_time DATETIME,
                check_out_time DATETIME,
                business_meetings_attended INT DEFAULT 0,

                -- Export Interest
                export_interest BOOLEAN DEFAULT FALSE,
                interested_products TEXT,
                target_markets TEXT,
                follow_up_required BOOLEAN DEFAULT FALSE,

                -- Feedback
                mission_feedback TEXT,
                mission_rating DECIMAL(3,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (mission_id) REFERENCES trade_missions(id) ON DELETE CASCADE,
                FOREIGN KEY (exporter_id) REFERENCES exporter_profiles(id) ON DELETE SET NULL,
                INDEX idx_participant_code (participant_code),
                INDEX idx_mission_id (mission_id),
                INDEX idx_exporter_id (exporter_id),
                INDEX idx_registration_status (registration_status),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS market_intelligence_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_code VARCHAR(20) UNIQUE NOT NULL,
                report_title VARCHAR(200) NOT NULL,
                report_description TEXT,

                -- Report Details
                report_type ENUM('market_overview', 'sector_analysis', 'competitor_analysis', 'regulatory_update', 'trade_statistics') DEFAULT 'market_overview',
                target_country VARCHAR(100) NOT NULL,
                target_sector VARCHAR(100),

                -- Report Content
                executive_summary TEXT,
                market_analysis TEXT,
                competitive_landscape TEXT,
                regulatory_environment TEXT,
                trade_barriers TEXT,

                -- Market Data
                market_size DECIMAL(20,2),
                market_growth DECIMAL(5,2),
                import_value DECIMAL(20,2),
                import_growth DECIMAL(5,2),

                -- Key Findings
                opportunities_identified TEXT,
                challenges_identified TEXT,
                recommendations TEXT,

                -- Report Metadata
                report_date DATE NOT NULL,
                report_author VARCHAR(100),
                data_sources TEXT,

                -- Access Control
                access_level ENUM('public', 'registered_exporters', 'premium_members', 'restricted') DEFAULT 'registered_exporters',
                download_count INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_report_code (report_code),
                INDEX idx_report_type (report_type),
                INDEX idx_target_country (target_country),
                INDEX idx_target_sector (target_sector),
                INDEX idx_report_date (report_date),
                INDEX idx_access_level (access_level)
            );

            CREATE TABLE IF NOT EXISTS export_impact_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_code VARCHAR(20) UNIQUE NOT NULL,
                report_period VARCHAR(20) NOT NULL, -- e.g., 'Q1-2024', '2024'

                -- Report Details
                report_type ENUM('quarterly', 'annual', 'sectoral', 'country_specific') DEFAULT 'quarterly',
                report_date DATE NOT NULL,

                -- Export Statistics
                total_export_value DECIMAL(20,2) DEFAULT 0,
                export_growth_rate DECIMAL(5,2) DEFAULT 0,
                number_of_exporters INT DEFAULT 0,

                -- Sector Breakdown
                exports_by_sector TEXT, -- JSON object with sector:amount pairs
                top_export_countries TEXT, -- JSON array of country statistics

                -- Program Impact
                assistance_programs_utilized INT DEFAULT 0,
                assistance_amount_disbursed DECIMAL(15,2) DEFAULT 0,
                assistance_utilization_rate DECIMAL(5,2),

                -- Trade Mission Impact
                trade_missions_conducted INT DEFAULT 0,
                mission_participants INT DEFAULT 0,
                export_contracts_from_missions DECIMAL(15,2) DEFAULT 0,

                -- Market Intelligence Usage
                reports_downloaded INT DEFAULT 0,
                market_intelligence_users INT DEFAULT 0,

                -- Key Achievements
                major_export_contracts TEXT,
                new_markets_entered TEXT,
                policy_impacts TEXT,

                -- Future Outlook
                pipeline_opportunities TEXT,
                upcoming_missions TEXT,
                strategic_recommendations TEXT,

                -- Report Metadata
                prepared_by VARCHAR(100),
                reviewed_by VARCHAR(100),
                approval_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_report_code (report_code),
                INDEX idx_report_period (report_period),
                INDEX idx_report_type (report_type),
                INDEX idx_report_date (report_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup export assistance application workflow
        $exportWorkflow = [
            'name' => 'Export Assistance Application Process',
            'description' => 'Complete workflow for export assistance applications and approvals',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'exporter',
                    'form' => 'export_assistance_application_form'
                ],
                [
                    'name' => 'Initial Screening',
                    'type' => 'service_task',
                    'service' => 'application_screening_service'
                ],
                [
                    'name' => 'Export Capability Assessment',
                    'type' => 'user_task',
                    'assignee' => 'export_officer',
                    'form' => 'export_capability_assessment_form'
                ],
                [
                    'name' => 'Market Analysis Review',
                    'type' => 'user_task',
                    'assignee' => 'market_analyst',
                    'form' => 'market_analysis_review_form'
                ],
                [
                    'name' => 'Financial Assessment',
                    'type' => 'user_task',
                    'assignee' => 'financial_analyst',
                    'form' => 'financial_assessment_form'
                ],
                [
                    'name' => 'Program Recommendation',
                    'type' => 'user_task',
                    'assignee' => 'program_officer',
                    'form' => 'program_recommendation_form'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'export_committee',
                    'form' => 'export_approval_form'
                ],
                [
                    'name' => 'Assistance Disbursement',
                    'type' => 'service_task',
                    'service' => 'assistance_disbursement_service'
                ],
                [
                    'name' => 'Implementation Monitoring',
                    'type' => 'user_task',
                    'assignee' => 'export_monitor',
                    'form' => 'implementation_monitoring_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/export_workflow.json', json_encode($exportWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/export_applications',
            __DIR__ . '/uploads/exporter_documents',
            __DIR__ . '/uploads/market_reports',
            __DIR__ . '/uploads/mission_materials',
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
            'export_impact_reports',
            'market_intelligence_reports',
            'mission_participants',
            'trade_missions',
            'export_applications',
            'exporter_profiles',
            'export_assistance_programs',
            'export_opportunities'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createExportOpportunity($data) {
        try {
            $this->validateOpportunityData($data);
            $opportunityCode = $this->generateOpportunityCode();

            $sql = "INSERT INTO export_opportunities (
                opportunity_code, opportunity_name, opportunity_description, target_country,
                target_region, product_category, sub_category, market_size, market_growth_rate,
                competition_level, entry_barriers, import_tariffs, import_quotas,
                regulatory_requirements, certification_requirements, market_trends,
                consumer_preferences, distribution_channels, pricing_strategy,
                opportunity_status, publish_date, expiry_date, contact_person,
                contact_email, contact_phone, trade_office, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $opportunityId = $this->db->insert($sql, [
                $opportunityCode, $data['opportunity_name'], $data['opportunity_description'] ?? null,
                $data['target_country'], $data['target_region'] ?? null, $data['product_category'],
                $data['sub_category'] ?? null, $data['market_size'] ?? null, $data['market_growth_rate'] ?? null,
                $data['competition_level'] ?? 'medium', $data['entry_barriers'] ?? null,
                $data['import_tariffs'] ?? null, json_encode($data['import_quotas'] ?? []),
                json_encode($data['regulatory_requirements'] ?? []), json_encode($data['certification_requirements'] ?? []),
                $data['market_trends'] ?? null, $data['consumer_preferences'] ?? null,
                $data['distribution_channels'] ?? null, $data['pricing_strategy'] ?? null,
                $data['opportunity_status'] ?? 'draft', $data['publish_date'] ?? null,
                $data['expiry_date'] ?? null, $data['contact_person'] ?? null,
                $data['contact_email'] ?? null, $data['contact_phone'] ?? null,
                $data['trade_office'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'opportunity_id' => $opportunityId,
                'opportunity_code' => $opportunityCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createExportAssistanceProgram($data) {
        try {
            $this->validateProgramData($data);
            $programCode = $this->generateProgramCode();

            $sql = "INSERT INTO export_assistance_programs (
                program_code, program_name, program_description, program_category,
                assistance_type, eligible_products, eligible_companies, minimum_export_value,
                maximum_assistance, assistance_percentage, maximum_duration_months,
                repayment_terms, application_deadline, review_process, approval_authority,
                program_status, effective_date, expiry_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['program_name'], $data['program_description'] ?? null,
                $data['program_category'] ?? 'financial', $data['assistance_type'] ?? 'grant',
                json_encode($data['eligible_products'] ?? []), json_encode($data['eligible_companies'] ?? []),
                $data['minimum_export_value'] ?? null, $data['maximum_assistance'] ?? null,
                $data['assistance_percentage'] ?? null, $data['maximum_duration_months'] ?? 12,
                $data['repayment_terms'] ?? null, $data['application_deadline'] ?? null,
                $data['review_process'] ?? null, $data['approval_authority'] ?? null,
                $data['program_status'] ?? 'draft', $data['effective_date'] ?? null,
                $data['expiry_date'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'program_id' => $programId,
                'program_code' => $programCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerExporter($data) {
        try {
            $this->validateExporterData($data);
            $exporterCode = $this->generateExporterCode();

            $sql = "INSERT INTO exporter_profiles (
                exporter_code, company_name, company_description, company_type,
                industry_sector, export_experience_years, primary_contact_name,
                primary_contact_title, primary_contact_email, primary_contact_phone,
                total_export_value, export_countries, main_export_products,
                annual_turnover, export_turnover, number_of_employees,
                production_capacity, quality_certifications, export_licenses,
                profile_status, verification_status, website, linkedin_profile,
                references, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $exporterId = $this->db->insert($sql, [
                $exporterCode, $data['company_name'], $data['company_description'] ?? null,
                $data['company_type'] ?? 'manufacturer', $data['industry_sector'],
                $data['export_experience_years'] ?? null, $data['primary_contact_name'],
                $data['primary_contact_title'] ?? null, $data['primary_contact_email'],
                $data['primary_contact_phone'] ?? null, $data['total_export_value'] ?? 0,
                json_encode($data['export_countries'] ?? []), json_encode($data['main_export_products'] ?? []),
                $data['annual_turnover'] ?? null, $data['export_turnover'] ?? null,
                $data['number_of_employees'] ?? null, $data['production_capacity'] ?? null,
                json_encode($data['quality_certifications'] ?? []), json_encode($data['export_licenses'] ?? []),
                $data['profile_status'] ?? 'draft', $data['verification_status'] ?? 'unverified',
                $data['website'] ?? null, $data['linkedin_profile'] ?? null,
                json_encode($data['references'] ?? []), $data['created_by']
            ]);

            return [
                'success' => true,
                'exporter_id' => $exporterId,
                'exporter_code' => $exporterCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitExportApplication($data) {
        try {
            $this->validateApplicationData($data);
            $applicationNumber = $this->generateApplicationNumber();

            $sql = "INSERT INTO export_applications (
                application_number, exporter_id, program_id, application_date,
                target_markets, export_products, proposed_export_value, export_description,
                market_analysis, marketing_strategy, competitive_advantage, risk_assessment,
                assistance_requested, assistance_justification, expected_benefits,
                business_plan_document, financial_statements, export_licenses,
                other_documents, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['exporter_id'], $data['program_id'] ?? null,
                $data['application_date'], json_encode($data['target_markets'] ?? []),
                json_encode($data['export_products'] ?? []), $data['proposed_export_value'],
                $data['export_description'] ?? null, $data['market_analysis'] ?? null,
                $data['marketing_strategy'] ?? null, $data['competitive_advantage'] ?? null,
                $data['risk_assessment'] ?? null, json_encode($data['assistance_requested'] ?? []),
                $data['assistance_justification'] ?? null, $data['expected_benefits'] ?? null,
                $data['business_plan_document'] ?? null, $data['financial_statements'] ?? null,
                $data['export_licenses'] ?? null, json_encode($data['other_documents'] ?? []),
                $data['created_by']
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

    public function createTradeMission($data) {
        try {
            $this->validateMissionData($data);
            $missionCode = $this->generateMissionCode();

            $sql = "INSERT INTO trade_missions (
                mission_code, mission_name, mission_description, mission_type,
                target_country, target_region, mission_start_date, mission_end_date,
                mission_location, target_sectors, target_company_sizes, expected_participants,
                mission_objectives, planned_activities, business_matching_events,
                participation_fee, application_deadline, maximum_participants,
                mission_status, mission_coordinator, coordinator_email,
                coordinator_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $missionId = $this->db->insert($sql, [
                $missionCode, $data['mission_name'], $data['mission_description'] ?? null,
                $data['mission_type'] ?? 'outgoing_trade', $data['target_country'],
                $data['target_region'] ?? null, $data['mission_start_date'],
                $data['mission_end_date'], $data['mission_location'] ?? null,
                json_encode($data['target_sectors'] ?? []), json_encode($data['target_company_sizes'] ?? []),
                $data['expected_participants'] ?? null, $data['mission_objectives'] ?? null,
                $data['planned_activities'] ?? null, $data['business_matching_events'] ?? true,
                $data['participation_fee'] ?? 0, $data['application_deadline'] ?? null,
                $data['maximum_participants'] ?? null, $data['mission_status'] ?? 'planned',
                $data['mission_coordinator'] ?? null, $data['coordinator_email'] ?? null,
                $data['coordinator_phone'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'mission_id' => $missionId,
                'mission_code' => $missionCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerForMission($data) {
        try {
            $this->validateMissionRegistrationData($data);
            $participantCode = $this->generateParticipantCode();

            $sql = "INSERT INTO mission_participants (
                participant_code, mission_id, exporter_id, participant_name,
                participant_company, participant_country, participant_email,
                participant_phone, registration_date, registration_status,
                export_interest, interested_products, target_markets,
                follow_up_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $participantId = $this->db->insert($sql, [
                $participantCode, $data['mission_id'], $data['exporter_id'] ?? null,
                $data['participant_name'], $data['participant_company'] ?? null,
                $data['participant_country'] ?? null, $data['participant_email'] ?? null,
                $data['participant_phone'] ?? null, $data['registration_date'],
                $data['registration_status'] ?? 'registered', $data['export_interest'] ?? false,
                json_encode($data['interested_products'] ?? []), json_encode($data['target_markets'] ?? []),
                $data['follow_up_required'] ?? false, $data['created_by']
            ]);

            return [
                'success' => true,
                'participant_id' => $participantId,
                'participant_code' => $participantCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createMarketIntelligenceReport($data) {
        try {
            $this->validateReportData($data);
            $reportCode = $this->generateReportCode();

            $sql = "INSERT INTO market_intelligence_reports (
                report_code, report_title, report_description, report_type,
                target_country, target_sector, executive_summary, market_analysis,
                competitive_landscape, regulatory_environment, trade_barriers,
                market_size, market_growth, import_value, import_growth,
                opportunities_identified, challenges_identified, recommendations,
                report_date, report_author, data_sources, access_level, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $reportId = $this->db->insert($sql, [
                $reportCode, $data['report_title'], $data['report_description'] ?? null,
                $data['report_type'] ?? 'market_overview', $data['target_country'],
                $data['target_sector'] ?? null, $data['executive_summary'] ?? null,
                $data['market_analysis'] ?? null, $data['competitive_landscape'] ?? null,
                $data['regulatory_environment'] ?? null, $data['trade_barriers'] ?? null,
                $data['market_size'] ?? null, $data['market_growth'] ?? null,
                $data['import_value'] ?? null, $data['import_growth'] ?? null,
                $data['opportunities_identified'] ?? null, $data['challenges_identified'] ?? null,
                $data['recommendations'] ?? null, $data['report_date'],
                $data['report_author'] ?? null, json_encode($data['data_sources'] ?? []),
                $data['access_level'] ?? 'registered_exporters', $data['created_by']
            ]);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_code' => $reportCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createExportImpactReport($data) {
        try {
            $this->validateImpactReportData($data);
            $reportCode = $this->generateImpactReportCode();

            $sql = "INSERT INTO export_impact_reports (
                report_code, report_period, report_type, report_date,
                total_export_value, export_growth_rate, number_of_exporters,
                exports_by_sector, top_export_countries, assistance_programs_utilized,
                assistance_amount_disbursed, assistance_utilization_rate,
                trade_missions_conducted, mission_participants,
                export_contracts_from_missions, reports_downloaded,
                market_intelligence_users, major_export_contracts,
                new_markets_entered, policy_impacts, pipeline_opportunities,
                upcoming_missions, strategic_recommendations, prepared_by,
                reviewed_by, approval_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $reportId = $this->db->insert($sql, [
                $reportCode, $data['report_period'], $data['report_type'] ?? 'quarterly',
                $data['report_date'], $data['total_export_value'] ?? 0,
                $data['export_growth_rate'] ?? 0, $data['number_of_exporters'] ?? 0,
                json_encode($data['exports_by_sector'] ?? []), json_encode($data['top_export_countries'] ?? []),
                $data['assistance_programs_utilized'] ?? 0, $data['assistance_amount_disbursed'] ?? 0,
                $data['assistance_utilization_rate'] ?? 0, $data['trade_missions_conducted'] ?? 0,
                $data['mission_participants'] ?? 0, $data['export_contracts_from_missions'] ?? 0,
                $data['reports_downloaded'] ?? 0, $data['market_intelligence_users'] ?? 0,
                json_encode($data['major_export_contracts'] ?? []), json_encode($data['new_markets_entered'] ?? []),
                json_encode($data['policy_impacts'] ?? []), json_encode($data['pipeline_opportunities'] ?? []),
                json_encode($data['upcoming_missions'] ?? []), json_encode($data['strategic_recommendations'] ?? []),
                $data['prepared_by'] ?? null, $data['reviewed_by'] ?? null,
                $data['approval_date'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_code' => $reportCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Validation Methods
    private function validateOpportunityData($data) {
        if (empty($data['opportunity_name'])) {
            throw new Exception('Opportunity name is required');
        }
        if (empty($data['target_country'])) {
            throw new Exception('Target country is required');
        }
        if (empty($data['product_category'])) {
            throw new Exception('Product category is required');
        }
    }

    private function validateProgramData($data) {
        if (empty($data['program_name'])) {
            throw new Exception('Program name is required');
        }
    }

    private function validateExporterData($data) {
        if (empty($data['company_name'])) {
            throw new Exception('Company name is required');
        }
        if (empty($data['industry_sector'])) {
            throw new Exception('Industry sector is required');
        }
        if (empty($data['primary_contact_name'])) {
            throw new Exception('Primary contact name is required');
        }
        if (empty($data['primary_contact_email'])) {
            throw new Exception('Primary contact email is required');
        }
    }

    private function validateApplicationData($data) {
        if (empty($data['exporter_id'])) {
            throw new Exception('Exporter ID is required');
        }
        if (empty($data['proposed_export_value']) || $data['proposed_export_value'] <= 0) {
            throw new Exception('Valid proposed export value is required');
        }
        if (empty($data['application_date'])) {
            throw new Exception('Application date is required');
        }
    }

    private function validateMissionData($data) {
        if (empty($data['mission_name'])) {
            throw new Exception('Mission name is required');
        }
        if (empty($data['target_country'])) {
            throw new Exception('Target country is required');
        }
        if (empty($data['mission_start_date'])) {
            throw new Exception('Mission start date is required');
        }
        if (empty($data['mission_end_date'])) {
            throw new Exception('Mission end date is required');
        }
    }

    private function validateMissionRegistrationData($data) {
        if (empty($data['mission_id'])) {
            throw new Exception('Mission ID is required');
        }
        if (empty($data['participant_name'])) {
            throw new Exception('Participant name is required');
        }
        if (empty($data['registration_date'])) {
            throw new Exception('Registration date is required');
        }
    }

    private function validateReportData($data) {
        if (empty($data['report_title'])) {
            throw new Exception('Report title is required');
        }
        if (empty($data['target_country'])) {
            throw new Exception('Target country is required');
        }
        if (empty($data['report_date'])) {
            throw new Exception('Report date is required');
        }
    }

    private function validateImpactReportData($data) {
        if (empty($data['report_period'])) {
            throw new Exception('Report period is required');
        }
        if (empty($data['report_date'])) {
            throw new Exception('Report date is required');
        }
    }

    // Code Generation Methods
    private function generateOpportunityCode() {
        return 'OPP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateProgramCode() {
        return 'PRG-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateExporterCode() {
        return 'EXP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateApplicationNumber() {
        return 'APP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateMissionCode() {
        return 'MSN-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateParticipantCode() {
        return 'PRT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateReportCode() {
        return 'RPT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateImpactReportCode() {
        return 'IMP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Additional API Methods
    public function getExportOpportunities($filters = []) {
        try {
            $sql = "SELECT * FROM export_opportunities WHERE opportunity_status = 'published'";
            $params = [];

            if (!empty($filters['target_country'])) {
                $sql .= " AND target_country = ?";
                $params[] = $filters['target_country'];
            }

            if (!empty($filters['product_category'])) {
                $sql .= " AND product_category = ?";
                $params[] = $filters['product_category'];
            }

            $sql .= " ORDER BY created_at DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAvailablePrograms($filters = []) {
        try {
            $sql = "SELECT * FROM export_assistance_programs WHERE program_status = 'active'";
            $params = [];

            if (!empty($filters['program_category'])) {
                $sql .= " AND program_category = ?";
                $params[] = $filters['program_category'];
            }

            $sql .= " ORDER BY created_at DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getExporterProfile($exporterId) {
        try {
            $sql = "SELECT * FROM exporter_profiles WHERE id = ?";
            $result = $this->db->query($sql, [$exporterId])->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Decode JSON fields
                $result['export_countries'] = json_decode($result['export_countries'], true);
                $result['main_export_products'] = json_decode($result['main_export_products'], true);
                $result['quality_certifications'] = json_decode($result['quality_certifications'], true);
                $result['export_licenses'] = json_decode($result['export_licenses'], true);
                $result['references'] = json_decode($result['references'], true);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getApplicationStatus($applicationId) {
        try {
            $sql = "SELECT * FROM export_applications WHERE id = ?";
            $result = $this->db->query($sql, [$applicationId])->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Decode JSON fields
                $result['target_markets'] = json_decode($result['target_markets'], true);
                $result['export_products'] = json_decode($result['export_products'], true);
                $result['assistance_requested'] = json_decode($result['assistance_requested'], true);
                $result['other_documents'] = json_decode($result['other_documents'], true);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getUpcomingMissions($limit = 10) {
        try {
            $sql = "SELECT * FROM trade_missions
                    WHERE mission_start_date >= CURRENT_DATE AND mission_status IN ('announced', 'registration_open')
                    ORDER BY mission_start_date ASC LIMIT ?";
            return $this->db->query($sql, [$limit])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getMarketIntelligenceReports($filters = []) {
        try {
            $sql = "SELECT * FROM market_intelligence_reports WHERE access_level != 'restricted'";
            $params = [];

            if (!empty($filters['target_country'])) {
                $sql .= " AND target_country = ?";
                $params[] = $filters['target_country'];
            }

            if (!empty($filters['report_type'])) {
                $sql .= " AND report_type = ?";
                $params[] = $filters['report_type'];
            }

            $sql .= " ORDER BY report_date DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function generateExportReport($period = 'quarterly', $year = null, $quarter = null) {
        try {
            $year = $year ?: date('Y');
            $quarter = $quarter ?: ceil(date('n') / 3);

            $reportData = [
                'report_period' => $period === 'quarterly' ? "Q{$quarter}-{$year}" : $year,
                'report_type' => $period,
                'report_date' => date('Y-m-d')
            ];

            // Calculate export statistics
            $exportStats = $this->calculateExportStatistics($year, $quarter, $period);
            $reportData = array_merge($reportData, $exportStats);

            // Calculate program utilization
            $programStats = $this->calculateProgramStatistics($year, $quarter, $period);
            $reportData = array_merge($reportData, $programStats);

            // Calculate mission impact
            $missionStats = $this->calculateMissionStatistics($year, $quarter, $period);
            $reportData = array_merge($reportData, $missionStats);

            return $this->createExportImpactReport($reportData);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function calculateExportStatistics($year, $quarter, $period) {
        $dateCondition = $this->buildDateCondition($year, $quarter, $period);

        $sql = "SELECT
                SUM(proposed_export_value) as total_export_value,
                COUNT(DISTINCT exporter_id) as number_of_exporters
                FROM export_applications
                WHERE application_status IN ('approved', 'conditional_approval') {$dateCondition}";

        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'total_export_value' => $result['total_export_value'] ?: 0,
            'number_of_exporters' => $result['number_of_exporters'] ?: 0
        ];
    }

    private function calculateProgramStatistics($year, $quarter, $period) {
        $dateCondition = $this->buildDateCondition($year, $quarter, $period);

        $sql = "SELECT
                COUNT(DISTINCT program_id) as assistance_programs_utilized,
                SUM(maximum_assistance) as assistance_amount_disbursed
                FROM export_assistance_programs eap
                JOIN export_applications ea ON eap.id = ea.program_id
                WHERE ea.application_status IN ('approved', 'conditional_approval') {$dateCondition}";

        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        $disbursed = $result['assistance_amount_disbursed'] ?: 0;
        $utilized = $result['assistance_programs_utilized'] ?: 0;

        return [
            'assistance_programs_utilized' => $utilized,
            'assistance_amount_disbursed' => $disbursed,
            'assistance_utilization_rate' => $utilized > 0 ? round(($disbursed / $utilized) * 100, 2) : 0
        ];
    }

    private function calculateMissionStatistics($year, $quarter, $period) {
        $dateCondition = $this->buildDateCondition($year, $quarter, $period);

        $sql = "SELECT
                COUNT(DISTINCT tm.id) as trade_missions_conducted,
                COUNT(mp.id) as mission_participants,
                SUM(tm.export_contracts_signed) as export_contracts_from_missions
                FROM trade_missions tm
                LEFT JOIN mission_participants mp ON tm.id = mp.mission_id
                WHERE tm.mission_status = 'completed' {$dateCondition}";

        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'trade_missions_conducted' => $result['trade_missions_conducted'] ?: 0,
            'mission_participants' => $result['mission_participants'] ?: 0,
            'export_contracts_from_missions' => $result['export_contracts_from_missions'] ?: 0
        ];
    }

    private function buildDateCondition($year, $quarter, $period) {
        if ($period === 'annual') {
            return "AND YEAR(created_at) = {$year}";
        } else {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;
            return "AND YEAR(created_at) = {$year} AND MONTH(created_at) BETWEEN {$startMonth} AND {$endMonth}";
        }
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [
            'database_table_prefix' => 'export_',
            'upload_path' => __DIR__ . '/uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_email' => 'admin@example.com',
            'currency' => 'USD'
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

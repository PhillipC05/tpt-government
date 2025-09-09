<?php
/**
 * Foreign Investment Module
 * Handles foreign direct investment attraction, incentives, and management
 */

require_once __DIR__ . '/../ServiceModule.php';

class ForeignInvestmentModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Foreign Investment Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive foreign direct investment management system including investor attraction, incentives administration, compliance monitoring, and investment tracking',
            'dependencies' => ['FinancialManagement', 'BusinessLicenses', 'Procurement'],
            'permissions' => [
                'investment.view' => 'View investment opportunities and data',
                'investment.apply' => 'Apply for investment incentives',
                'investment.admin' => 'Administrative functions for investment management',
                'investment.approve' => 'Approve investment applications and incentives'
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
            CREATE TABLE IF NOT EXISTS investment_opportunities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                opportunity_code VARCHAR(20) UNIQUE NOT NULL,
                opportunity_name VARCHAR(200) NOT NULL,
                opportunity_description TEXT,

                -- Opportunity Details
                sector VARCHAR(100) NOT NULL,
                sub_sector VARCHAR(100),
                investment_type ENUM('greenfield', 'brownfield', 'joint_venture', 'mergers_acquisitions', 'portfolio_investment') DEFAULT 'greenfield',
                target_countries TEXT,

                -- Investment Requirements
                minimum_investment DECIMAL(15,2),
                maximum_investment DECIMAL(15,2),
                preferred_investment_range VARCHAR(100),

                -- Location Information
                preferred_locations TEXT,
                special_economic_zones TEXT,
                infrastructure_available TEXT,

                -- Incentives Offered
                tax_incentives TEXT,
                financial_incentives TEXT,
                regulatory_incentives TEXT,
                other_benefits TEXT,

                -- Opportunity Status
                opportunity_status ENUM('draft', 'published', 'under_review', 'approved', 'closed', 'cancelled') DEFAULT 'draft',
                publish_date DATE,
                expiry_date DATE,

                -- Contact Information
                contact_person VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),
                department VARCHAR(100),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_opportunity_code (opportunity_code),
                INDEX idx_sector (sector),
                INDEX idx_investment_type (investment_type),
                INDEX idx_opportunity_status (opportunity_status),
                INDEX idx_publish_date (publish_date),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS investment_incentives (
                id INT PRIMARY KEY AUTO_INCREMENT,
                incentive_code VARCHAR(20) UNIQUE NOT NULL,
                incentive_name VARCHAR(200) NOT NULL,
                incentive_description TEXT,

                -- Incentive Details
                incentive_category ENUM('tax', 'financial', 'regulatory', 'infrastructure', 'training', 'other') DEFAULT 'tax',
                incentive_type ENUM('reduction', 'exemption', 'credit', 'grant', 'subsidy', 'rebate') DEFAULT 'reduction',

                -- Eligibility Criteria
                eligible_sectors TEXT,
                minimum_investment DECIMAL(15,2),
                maximum_investment DECIMAL(15,2),
                employment_generation_min INT,
                employment_generation_max INT,

                -- Incentive Terms
                incentive_value DECIMAL(15,2),
                incentive_percentage DECIMAL(5,2),
                duration_years INT DEFAULT 5,
                clawback_conditions TEXT,

                -- Application Process
                application_deadline DATE,
                review_process TEXT,
                approval_authority VARCHAR(100),

                -- Status and Validity
                incentive_status ENUM('draft', 'active', 'inactive', 'expired', 'cancelled') DEFAULT 'draft',
                effective_date DATE,
                expiry_date DATE,

                -- Usage Tracking
                total_allocated DECIMAL(15,2) DEFAULT 0,
                total_utilized DECIMAL(15,2) DEFAULT 0,
                applications_received INT DEFAULT 0,
                applications_approved INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_incentive_code (incentive_code),
                INDEX idx_incentive_category (incentive_category),
                INDEX idx_incentive_status (incentive_status),
                INDEX idx_effective_date (effective_date),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS investor_profiles (
                id INT PRIMARY KEY AUTO_INCREMENT,
                investor_code VARCHAR(20) UNIQUE NOT NULL,
                company_name VARCHAR(200) NOT NULL,
                company_description TEXT,

                -- Company Details
                country_of_origin VARCHAR(100) NOT NULL,
                company_type ENUM('corporation', 'llc', 'partnership', 'individual', 'sovereign_fund', 'private_equity', 'venture_capital', 'other') DEFAULT 'corporation',
                industry_focus TEXT,
                investment_experience_years INT,

                -- Contact Information
                primary_contact_name VARCHAR(200) NOT NULL,
                primary_contact_title VARCHAR(100),
                primary_contact_email VARCHAR(255),
                primary_contact_phone VARCHAR(20),

                -- Company Financials
                total_assets DECIMAL(20,2),
                annual_revenue DECIMAL(20,2),
                net_worth DECIMAL(20,2),

                -- Investment Preferences
                preferred_sectors TEXT,
                preferred_investment_size_min DECIMAL(15,2),
                preferred_investment_size_max DECIMAL(15,2),
                preferred_regions TEXT,
                risk_tolerance ENUM('low', 'medium', 'high') DEFAULT 'medium',

                -- Regulatory Information
                tax_id VARCHAR(50),
                registration_number VARCHAR(50),
                regulatory_approvals TEXT,

                -- Profile Status
                profile_status ENUM('draft', 'active', 'inactive', 'suspended', 'blacklisted') DEFAULT 'draft',
                verification_status ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'unverified',

                -- Additional Information
                website VARCHAR(255),
                linkedin_profile VARCHAR(255),
                references TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_investor_code (investor_code),
                INDEX idx_country_of_origin (country_of_origin),
                INDEX idx_company_type (company_type),
                INDEX idx_profile_status (profile_status),
                INDEX idx_verification_status (verification_status)
            );

            CREATE TABLE IF NOT EXISTS investment_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                investor_id INT NOT NULL,
                opportunity_id INT,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'withdrawn', 'conditional_approval') DEFAULT 'draft',

                -- Investment Proposal
                proposed_investment DECIMAL(15,2) NOT NULL,
                investment_sector VARCHAR(100),
                investment_description TEXT,
                project_location VARCHAR(200),

                -- Business Plan
                business_plan_summary TEXT,
                market_analysis TEXT,
                financial_projections TEXT,
                risk_assessment TEXT,

                -- Employment and Impact
                jobs_to_create INT DEFAULT 0,
                jobs_to_create_local INT DEFAULT 0,
                jobs_to_create_foreign INT DEFAULT 0,
                economic_impact_assessment TEXT,

                -- Requested Incentives
                incentives_requested TEXT,
                incentives_justification TEXT,

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
                actual_investment DECIMAL(15,2) DEFAULT 0,
                jobs_created INT DEFAULT 0,

                -- Supporting Documents
                business_plan_document VARCHAR(255),
                financial_statements VARCHAR(255),
                legal_documents VARCHAR(255),
                other_documents TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (investor_id) REFERENCES investor_profiles(id) ON DELETE CASCADE,
                FOREIGN KEY (opportunity_id) REFERENCES investment_opportunities(id) ON DELETE SET NULL,
                INDEX idx_application_number (application_number),
                INDEX idx_investor_id (investor_id),
                INDEX idx_opportunity_id (opportunity_id),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date),
                INDEX idx_implementation_status (implementation_status)
            );

            CREATE TABLE IF NOT EXISTS incentive_allocations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                allocation_code VARCHAR(20) UNIQUE NOT NULL,
                application_id INT NOT NULL,
                incentive_id INT NOT NULL,

                -- Allocation Details
                allocation_date DATE NOT NULL,
                allocation_status ENUM('allocated', 'utilized', 'cancelled', 'expired') DEFAULT 'allocated',

                -- Financial Details
                allocated_amount DECIMAL(15,2) NOT NULL,
                utilized_amount DECIMAL(15,2) DEFAULT 0,
                remaining_amount DECIMAL(15,2) NOT NULL,

                -- Terms and Conditions
                allocation_conditions TEXT,
                utilization_deadline DATE,
                reporting_requirements TEXT,

                -- Utilization Tracking
                utilization_schedule TEXT,
                last_utilization_date DATE,
                next_reporting_date DATE,

                -- Compliance Monitoring
                compliance_status ENUM('compliant', 'warning', 'non_compliant', 'under_review') DEFAULT 'compliant',
                compliance_notes TEXT,
                compliance_officer VARCHAR(100),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (application_id) REFERENCES investment_applications(id) ON DELETE CASCADE,
                FOREIGN KEY (incentive_id) REFERENCES investment_incentives(id) ON DELETE CASCADE,
                INDEX idx_allocation_code (allocation_code),
                INDEX idx_application_id (application_id),
                INDEX idx_incentive_id (incentive_id),
                INDEX idx_allocation_status (allocation_status),
                INDEX idx_allocation_date (allocation_date),
                INDEX idx_utilization_deadline (utilization_deadline)
            );

            CREATE TABLE IF NOT EXISTS investment_monitoring (
                id INT PRIMARY KEY AUTO_INCREMENT,
                monitoring_code VARCHAR(20) UNIQUE NOT NULL,
                application_id INT NOT NULL,

                -- Monitoring Details
                monitoring_date DATE NOT NULL,
                monitoring_type ENUM('quarterly', 'annual', 'milestone', 'compliance', 'ad_hoc') DEFAULT 'quarterly',

                -- Investment Progress
                investment_made DECIMAL(15,2) DEFAULT 0,
                investment_planned DECIMAL(15,2) DEFAULT 0,
                investment_variance DECIMAL(15,2) DEFAULT 0,

                -- Employment Progress
                jobs_created INT DEFAULT 0,
                jobs_planned INT DEFAULT 0,
                jobs_variance INT DEFAULT 0,

                -- Operational Metrics
                revenue_generated DECIMAL(15,2) DEFAULT 0,
                operational_status TEXT,
                challenges_faced TEXT,
                mitigation_actions TEXT,

                -- Incentive Utilization
                incentives_utilized DECIMAL(15,2) DEFAULT 0,
                incentives_remaining DECIMAL(15,2) DEFAULT 0,

                -- Compliance Status
                compliance_issues TEXT,
                regulatory_compliance BOOLEAN DEFAULT TRUE,
                environmental_compliance BOOLEAN DEFAULT TRUE,
                labor_compliance BOOLEAN DEFAULT TRUE,

                -- Next Steps
                next_milestone TEXT,
                next_monitoring_date DATE,
                action_required TEXT,

                -- Monitoring Officer
                monitoring_officer VARCHAR(100),
                monitoring_notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (application_id) REFERENCES investment_applications(id) ON DELETE CASCADE,
                INDEX idx_monitoring_code (monitoring_code),
                INDEX idx_application_id (application_id),
                INDEX idx_monitoring_date (monitoring_date),
                INDEX idx_monitoring_type (monitoring_type),
                INDEX idx_next_monitoring_date (next_monitoring_date)
            );

            CREATE TABLE IF NOT EXISTS investment_promotion_events (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_code VARCHAR(20) UNIQUE NOT NULL,
                event_name VARCHAR(200) NOT NULL,
                event_description TEXT,

                -- Event Details
                event_type ENUM('roadshow', 'conference', 'webinar', 'workshop', 'trade_mission', 'investor_forum', 'other') DEFAULT 'roadshow',
                event_category ENUM('general', 'sector_specific', 'country_specific', 'regional') DEFAULT 'general',

                -- Schedule and Location
                event_date DATE NOT NULL,
                start_time TIME,
                end_time TIME,
                timezone VARCHAR(50) DEFAULT 'UTC',

                -- Location Details
                venue_name VARCHAR(200),
                city VARCHAR(100) NOT NULL,
                country VARCHAR(100) NOT NULL,
                virtual_platform VARCHAR(100),

                -- Target Audience
                target_countries TEXT,
                target_sectors TEXT,
                target_investor_types TEXT,

                -- Event Content
                agenda TEXT,
                speakers_presenters TEXT,
                expected_attendees INT,

                -- Logistics
                registration_fee DECIMAL(8,2) DEFAULT 0,
                registration_deadline DATE,
                capacity_limit INT,

                -- Status and Tracking
                event_status ENUM('planned', 'announced', 'registration_open', 'registration_closed', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
                registered_attendees INT DEFAULT 0,
                actual_attendees INT DEFAULT 0,

                -- Outcomes
                leads_generated INT DEFAULT 0,
                investments_interested DECIMAL(15,2) DEFAULT 0,
                follow_up_actions TEXT,

                -- Contact Information
                organizer_name VARCHAR(200),
                organizer_email VARCHAR(255),
                organizer_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_event_code (event_code),
                INDEX idx_event_type (event_type),
                INDEX idx_event_date (event_date),
                INDEX idx_city (city),
                INDEX idx_country (country),
                INDEX idx_event_status (event_status)
            );

            CREATE TABLE IF NOT EXISTS event_participants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                participant_code VARCHAR(20) UNIQUE NOT NULL,
                event_id INT NOT NULL,
                investor_id INT,

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
                sessions_attended TEXT,

                -- Investment Interest
                investment_interest BOOLEAN DEFAULT FALSE,
                interested_sectors TEXT,
                interested_investment_range VARCHAR(100),
                follow_up_required BOOLEAN DEFAULT FALSE,

                -- Feedback
                event_feedback TEXT,
                event_rating DECIMAL(3,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (event_id) REFERENCES investment_promotion_events(id) ON DELETE CASCADE,
                FOREIGN KEY (investor_id) REFERENCES investor_profiles(id) ON DELETE SET NULL,
                INDEX idx_participant_code (participant_code),
                INDEX idx_event_id (event_id),
                INDEX idx_investor_id (investor_id),
                INDEX idx_registration_status (registration_status),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS investment_impact_reports (
                id INT PRIMARY KEY AUTO_INCREMENT,
                report_code VARCHAR(20) UNIQUE NOT NULL,
                report_period VARCHAR(20) NOT NULL, -- e.g., 'Q1-2024', '2024'

                -- Report Details
                report_type ENUM('quarterly', 'annual', 'sectoral', 'regional') DEFAULT 'quarterly',
                report_date DATE NOT NULL,

                -- Investment Statistics
                total_investments DECIMAL(15,2) DEFAULT 0,
                new_investments DECIMAL(15,2) DEFAULT 0,
                investment_projects_count INT DEFAULT 0,

                -- Sector Breakdown
                investments_by_sector TEXT, -- JSON object with sector:amount pairs
                top_investing_countries TEXT, -- JSON array of country statistics

                -- Employment Impact
                total_jobs_created INT DEFAULT 0,
                jobs_by_sector TEXT,
                average_jobs_per_investment DECIMAL(8,2),

                -- Economic Impact
                gdp_contribution DECIMAL(15,2) DEFAULT 0,
                export_value DECIMAL(15,2) DEFAULT 0,
                import_substitution DECIMAL(15,2) DEFAULT 0,

                -- Incentive Utilization
                incentives_allocated DECIMAL(15,2) DEFAULT 0,
                incentives_utilized DECIMAL(15,2) DEFAULT 0,
                incentive_utilization_rate DECIMAL(5,2),

                -- Regional Distribution
                investments_by_region TEXT,
                regional_employment_impact TEXT,

                -- Key Achievements
                major_projects TEXT,
                policy_impacts TEXT,
                challenges_addressed TEXT,

                -- Future Outlook
                pipeline_projects TEXT,
                upcoming_events TEXT,
                policy_recommendations TEXT,

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
        // Setup investment application workflow
        $investmentWorkflow = [
            'name' => 'Foreign Investment Application Process',
            'description' => 'Complete workflow for foreign investment applications and approvals',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'investor',
                    'form' => 'investment_application_form'
                ],
                [
                    'name' => 'Initial Screening',
                    'type' => 'service_task',
                    'service' => 'application_screening_service'
                ],
                [
                    'name' => 'Due Diligence Review',
                    'type' => 'user_task',
                    'assignee' => 'due_diligence_officer',
                    'form' => 'due_diligence_review_form'
                ],
                [
                    'name' => 'Sector Analysis',
                    'type' => 'user_task',
                    'assignee' => 'sector_specialist',
                    'form' => 'sector_analysis_form'
                ],
                [
                    'name' => 'Economic Impact Assessment',
                    'type' => 'user_task',
                    'assignee' => 'economic_analyst',
                    'form' => 'economic_impact_form'
                ],
                [
                    'name' => 'Incentive Recommendation',
                    'type' => 'user_task',
                    'assignee' => 'incentive_officer',
                    'form' => 'incentive_recommendation_form'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'investment_committee',
                    'form' => 'investment_approval_form'
                ],
                [
                    'name' => 'Incentive Allocation',
                    'type' => 'service_task',
                    'service' => 'incentive_allocation_service'
                ],
                [
                    'name' => 'Implementation Monitoring',
                    'type' => 'user_task',
                    'assignee' => 'investment_monitor',
                    'form' => 'implementation_monitoring_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/investment_workflow.json', json_encode($investmentWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/investment_applications',
            __DIR__ . '/uploads/investor_documents',
            __DIR__ . '/uploads/monitoring_reports',
            __DIR__ . '/uploads/promotion_materials',
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
            'investment_impact_reports',
            'event_participants',
            'investment_promotion_events',
            'investment_monitoring',
            'incentive_allocations',
            'investment_applications',
            'investor_profiles',
            'investment_incentives',
            'investment_opportunities'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createInvestmentOpportunity($data) {
        try {
            $this->validateOpportunityData($data);
            $opportunityCode = $this->generateOpportunityCode();

            $sql = "INSERT INTO investment_opportunities (
                opportunity_code, opportunity_name, opportunity_description, sector,
                sub_sector, investment_type, target_countries, minimum_investment,
                maximum_investment, preferred_investment_range, preferred_locations,
                special_economic_zones, infrastructure_available, tax_incentives,
                financial_incentives, regulatory_incentives, other_benefits,
                opportunity_status, publish_date, expiry_date, contact_person,
                contact_email, contact_phone, department, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $opportunityId = $this->db->insert($sql, [
                $opportunityCode, $data['opportunity_name'], $data['opportunity_description'] ?? null,
                $data['sector'], $data['sub_sector'] ?? null, $data['investment_type'] ?? 'greenfield',
                json_encode($data['target_countries'] ?? []), $data['minimum_investment'] ?? null,
                $data['maximum_investment'] ?? null, $data['preferred_investment_range'] ?? null,
                json_encode($data['preferred_locations'] ?? []), json_encode($data['special_economic_zones'] ?? []),
                json_encode($data['infrastructure_available'] ?? []), json_encode($data['tax_incentives'] ?? []),
                json_encode($data['financial_incentives'] ?? []), json_encode($data['regulatory_incentives'] ?? []),
                json_encode($data['other_benefits'] ?? []), $data['opportunity_status'] ?? 'draft',
                $data['publish_date'] ?? null, $data['expiry_date'] ?? null,
                $data['contact_person'] ?? null, $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null, $data['department'] ?? null, $data['created_by']
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

    public function createInvestmentIncentive($data) {
        try {
            $this->validateIncentiveData($data);
            $incentiveCode = $this->generateIncentiveCode();

            $sql = "INSERT INTO investment_incentives (
                incentive_code, incentive_name, incentive_description, incentive_category,
                incentive_type, eligible_sectors, minimum_investment, maximum_investment,
                employment_generation_min, employment_generation_max, incentive_value,
                incentive_percentage, duration_years, clawback_conditions, application_deadline,
                review_process, approval_authority, incentive_status, effective_date,
                expiry_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $incentiveId = $this->db->insert($sql, [
                $incentiveCode, $data['incentive_name'], $data['incentive_description'] ?? null,
                $data['incentive_category'] ?? 'tax', $data['incentive_type'] ?? 'reduction',
                json_encode($data['eligible_sectors'] ?? []), $data['minimum_investment'] ?? null,
                $data['maximum_investment'] ?? null, $data['employment_generation_min'] ?? null,
                $data['employment_generation_max'] ?? null, $data['incentive_value'] ?? null,
                $data['incentive_percentage'] ?? null, $data['duration_years'] ?? 5,
                $data['clawback_conditions'] ?? null, $data['application_deadline'] ?? null,
                $data['review_process'] ?? null, $data['approval_authority'] ?? null,
                $data['incentive_status'] ?? 'draft', $data['effective_date'] ?? null,
                $data['expiry_date'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'incentive_id' => $incentiveId,
                'incentive_code' => $incentiveCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerInvestor($data) {
        try {
            $this->validateInvestorData($data);
            $investorCode = $this->generateInvestorCode();

            $sql = "INSERT INTO investor_profiles (
                investor_code, company_name, company_description, country_of_origin,
                company_type, industry_focus, investment_experience_years, primary_contact_name,
                primary_contact_title, primary_contact_email, primary_contact_phone,
                total_assets, annual_revenue, net_worth, preferred_sectors,
                preferred_investment_size_min, preferred_investment_size_max,
                preferred_regions, risk_tolerance, tax_id, registration_number,
                regulatory_approvals, profile_status, verification_status,
                website, linkedin_profile, references, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $investorId = $this->db->insert($sql, [
                $investorCode, $data['company_name'], $data['company_description'] ?? null,
                $data['country_of_origin'], $data['company_type'] ?? 'corporation',
                json_encode($data['industry_focus'] ?? []), $data['investment_experience_years'] ?? null,
                $data['primary_contact_name'], $data['primary_contact_title'] ?? null,
                $data['primary_contact_email'], $data['primary_contact_phone'] ?? null,
                $data['total_assets'] ?? null, $data['annual_revenue'] ?? null,
                $data['net_worth'] ?? null, json_encode($data['preferred_sectors'] ?? []),
                $data['preferred_investment_size_min'] ?? null, $data['preferred_investment_size_max'] ?? null,
                json_encode($data['preferred_regions'] ?? []), $data['risk_tolerance'] ?? 'medium',
                $data['tax_id'] ?? null, $data['registration_number'] ?? null,
                json_encode($data['regulatory_approvals'] ?? []), $data['profile_status'] ?? 'draft',
                $data['verification_status'] ?? 'unverified', $data['website'] ?? null,
                $data['linkedin_profile'] ?? null, json_encode($data['references'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'investor_id' => $investorId,
                'investor_code' => $investorCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitInvestmentApplication($data) {
        try {
            $this->validateApplicationData($data);
            $applicationNumber = $this->generateApplicationNumber();

            $sql = "INSERT INTO investment_applications (
                application_number, investor_id, opportunity_id, application_date,
                proposed_investment, investment_sector, investment_description,
                project_location, business_plan_summary, market_analysis,
                financial_projections, risk_assessment, jobs_to_create,
                jobs_to_create_local, jobs_to_create_foreign, economic_impact_assessment,
                incentives_requested, incentives_justification, business_plan_document,
                financial_statements, legal_documents, other_documents, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['investor_id'], $data['opportunity_id'] ?? null,
                $data['application_date'], $data['proposed_investment'], $data['investment_sector'] ?? null,
                $data['investment_description'] ?? null, $data['project_location'] ?? null,
                $data['business_plan_summary'] ?? null, $data['market_analysis'] ?? null,
                $data['financial_projections'] ?? null, $data['risk_assessment'] ?? null,
                $data['jobs_to_create'] ?? 0, $data['jobs_to_create_local'] ?? 0,
                $data['jobs_to_create_foreign'] ?? 0, $data['economic_impact_assessment'] ?? null,
                json_encode($data['incentives_requested'] ?? []), $data['incentives_justification'] ?? null,
                $data['business_plan_document'] ?? null, $data['financial_statements'] ?? null,
                $data['legal_documents'] ?? null, json_encode($data['other_documents'] ?? []),
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

    public function allocateIncentives($data) {
        try {
            $this->validateAllocationData($data);
            $allocationCode = $this->generateAllocationCode();

            $sql = "INSERT INTO incentive_allocations (
                allocation_code, application_id, incentive_id, allocation_date,
                allocated_amount, remaining_amount, allocation_conditions,
                utilization_deadline, reporting_requirements, utilization_schedule,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $allocationId = $this->db->insert($sql, [
                $allocationCode, $data['application_id'], $data['incentive_id'],
                $data['allocation_date'], $data['allocated_amount'], $data['allocated_amount'],
                $data['allocation_conditions'] ?? null, $data['utilization_deadline'] ?? null,
                $data['reporting_requirements'] ?? null, json_encode($data['utilization_schedule'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'allocation_id' => $allocationId,
                'allocation_code' => $allocationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createMonitoringReport($data) {
        try {
            $this->validateMonitoringData($data);
            $monitoringCode = $this->generateMonitoringCode();

            $sql = "INSERT INTO investment_monitoring (
                monitoring_code, application_id, monitoring_date, monitoring_type,
                investment_made, investment_planned, investment_variance, jobs_created,
                jobs_planned, jobs_variance, revenue_generated, operational_status,
                challenges_faced, mitigation_actions, incentives_utilized,
                incentives_remaining, compliance_issues, regulatory_compliance,
                environmental_compliance, labor_compliance, next_milestone,
                next_monitoring_date, action_required, monitoring_officer,
                monitoring_notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $monitoringId = $this->db->insert($sql, [
                $monitoringCode, $data['application_id'], $data['monitoring_date'],
                $data['monitoring_type'] ?? 'quarterly', $data['investment_made'] ?? 0,
                $data['investment_planned'] ?? 0, $data['investment_variance'] ?? 0,
                $data['jobs_created'] ?? 0, $data['jobs_planned'] ?? 0,
                $data['jobs_variance'] ?? 0, $data['revenue_generated'] ?? 0,
                $data['operational_status'] ?? null, $data['challenges_faced'] ?? null,
                $data['mitigation_actions'] ?? null, $data['incentives_utilized'] ?? 0,
                $data['incentives_remaining'] ?? 0, $data['compliance_issues'] ?? null,
                $data['regulatory_compliance'] ?? true, $data['environmental_compliance'] ?? true,
                $data['labor_compliance'] ?? true, $data['next_milestone'] ?? null,
                $data['next_monitoring_date'] ?? null, $data['action_required'] ?? null,
                $data['monitoring_officer'] ?? null, $data['monitoring_notes'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'monitoring_id' => $monitoringId,
                'monitoring_code' => $monitoringCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createPromotionEvent($data) {
        try {
            $this->validateEventData($data);
            $eventCode = $this->generateEventCode();

            $sql = "INSERT INTO investment_promotion_events (
                event_code, event_name, event_description, event_type,
                event_category, event_date, start_time, end_time, timezone,
                venue_name, city, country, virtual_platform, target_countries,
                target_sectors, target_investor_types, agenda, speakers_presenters,
                expected_attendees, registration_fee, registration_deadline,
                capacity_limit, event_status, organizer_name, organizer_email,
                organizer_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $eventId = $this->db->insert($sql, [
                $eventCode, $data['event_name'], $data['event_description'] ?? null,
                $data['event_type'] ?? 'roadshow', $data['event_category'] ?? 'general',
                $data['event_date'], $data['start_time'] ?? null, $data['end_time'] ?? null,
                $data['timezone'] ?? 'UTC', $data['venue_name'] ?? null,
                $data['city'], $data['country'], $data['virtual_platform'] ?? null,
                json_encode($data['target_countries'] ?? []), json_encode($data['target_sectors'] ?? []),
                json_encode($data['target_investor_types'] ?? []), $data['agenda'] ?? null,
                $data['speakers_presenters'] ?? null, $data['expected_attendees'] ?? null,
                $data['registration_fee'] ?? 0, $data['registration_deadline'] ?? null,
                $data['capacity_limit'] ?? null, $data['event_status'] ?? 'planned',
                $data['organizer_name'] ?? null, $data['organizer_email'] ?? null,
                $data['organizer_phone'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'event_id' => $eventId,
                'event_code' => $eventCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerForEvent($data) {
        try {
            $this->validateEventRegistrationData($data);
            $participantCode = $this->generateParticipantCode();

            $sql = "INSERT INTO event_participants (
                participant_code, event_id, investor_id, participant_name,
                participant_company, participant_country, participant_email,
                participant_phone, registration_date, registration_status,
                investment_interest, interested_sectors, interested_investment_range,
                follow_up_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $participantId = $this->db->insert($sql, [
                $participantCode, $data['event_id'], $data['investor_id'] ?? null,
                $data['participant_name'], $data['participant_company'] ?? null,
                $data['participant_country'] ?? null, $data['participant_email'] ?? null,
                $data['participant_phone'] ?? null, $data['registration_date'],
                $data['registration_status'] ?? 'registered', $data['investment_interest'] ?? false,
                json_encode($data['interested_sectors'] ?? []), $data['interested_investment_range'] ?? null,
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

    public function createImpactReport($data) {
        try {
            $this->validateImpactReportData($data);
            $reportCode = $this->generateReportCode();

            $sql = "INSERT INTO investment_impact_reports (
                report_code, report_period, report_type, report_date,
                total_investments, new_investments, investment_projects_count,
                investments_by_sector, top_investing_countries, total_jobs_created,
                jobs_by_sector, average_jobs_per_investment, gdp_contribution,
                export_value, import_substitution, incentives_allocated,
                incentives_utilized, incentive_utilization_rate, investments_by_region,
                regional_employment_impact, major_projects, policy_impacts,
                challenges_addressed, pipeline_projects, upcoming_events,
                policy_recommendations, prepared_by, reviewed_by, approval_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $reportId = $this->db->insert($sql, [
                $reportCode, $data['report_period'], $data['report_type'] ?? 'quarterly',
                $data['report_date'], $data['total_investments'] ?? 0,
                $data['new_investments'] ?? 0, $data['investment_projects_count'] ?? 0,
                json_encode($data['investments_by_sector'] ?? []), json_encode($data['top_investing_countries'] ?? []),
                $data['total_jobs_created'] ?? 0, json_encode($data['jobs_by_sector'] ?? []),
                $data['average_jobs_per_investment'] ?? 0, $data['gdp_contribution'] ?? 0,
                $data['export_value'] ?? 0, $data['import_substitution'] ?? 0,
                $data['incentives_allocated'] ?? 0, $data['incentives_utilized'] ?? 0,
                $data['incentive_utilization_rate'] ?? 0, json_encode($data['investments_by_region'] ?? []),
                json_encode($data['regional_employment_impact'] ?? []), json_encode($data['major_projects'] ?? []),
                json_encode($data['policy_impacts'] ?? []), json_encode($data['challenges_addressed'] ?? []),
                json_encode($data['pipeline_projects'] ?? []), json_encode($data['upcoming_events'] ?? []),
                json_encode($data['policy_recommendations'] ?? []), $data['prepared_by'] ?? null,
                $data['reviewed_by'] ?? null, $data['approval_date'] ?? null, $data['created_by']
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
        if (empty($data['sector'])) {
            throw new Exception('Sector is required');
        }
        if (!empty($data['minimum_investment']) && !empty($data['maximum_investment']) &&
            $data['minimum_investment'] > $data['maximum_investment']) {
            throw new Exception('Minimum investment cannot be greater than maximum investment');
        }
    }

    private function validateIncentiveData($data) {
        if (empty($data['incentive_name'])) {
            throw new Exception('Incentive name is required');
        }
        if (!empty($data['minimum_investment']) && !empty($data['maximum_investment']) &&
            $data['minimum_investment'] > $data['maximum_investment']) {
            throw new Exception('Minimum investment cannot be greater than maximum investment');
        }
        if (!empty($data['incentive_percentage']) &&
            ($data['incentive_percentage'] < 0 || $data['incentive_percentage'] > 100)) {
            throw new Exception('Incentive percentage must be between 0 and 100');
        }
    }

    private function validateInvestorData($data) {
        if (empty($data['company_name'])) {
            throw new Exception('Company name is required');
        }
        if (empty($data['country_of_origin'])) {
            throw new Exception('Country of origin is required');
        }
        if (empty($data['primary_contact_name'])) {
            throw new Exception('Primary contact name is required');
        }
        if (empty($data['primary_contact_email'])) {
            throw new Exception('Primary contact email is required');
        }
    }

    private function validateApplicationData($data) {
        if (empty($data['investor_id'])) {
            throw new Exception('Investor ID is required');
        }
        if (empty($data['proposed_investment']) || $data['proposed_investment'] <= 0) {
            throw new Exception('Valid proposed investment amount is required');
        }
        if (empty($data['application_date'])) {
            throw new Exception('Application date is required');
        }
    }

    private function validateAllocationData($data) {
        if (empty($data['application_id'])) {
            throw new Exception('Application ID is required');
        }
        if (empty($data['incentive_id'])) {
            throw new Exception('Incentive ID is required');
        }
        if (empty($data['allocated_amount']) || $data['allocated_amount'] <= 0) {
            throw new Exception('Valid allocated amount is required');
        }
        if (empty($data['allocation_date'])) {
            throw new Exception('Allocation date is required');
        }
    }

    private function validateMonitoringData($data) {
        if (empty($data['application_id'])) {
            throw new Exception('Application ID is required');
        }
        if (empty($data['monitoring_date'])) {
            throw new Exception('Monitoring date is required');
        }
    }

    private function validateEventData($data) {
        if (empty($data['event_name'])) {
            throw new Exception('Event name is required');
        }
        if (empty($data['event_date'])) {
            throw new Exception('Event date is required');
        }
        if (empty($data['city'])) {
            throw new Exception('City is required');
        }
        if (empty($data['country'])) {
            throw new Exception('Country is required');
        }
    }

    private function validateEventRegistrationData($data) {
        if (empty($data['event_id'])) {
            throw new Exception('Event ID is required');
        }
        if (empty($data['participant_name'])) {
            throw new Exception('Participant name is required');
        }
        if (empty($data['registration_date'])) {
            throw new Exception('Registration date is required');
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

    private function generateIncentiveCode() {
        return 'INC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateInvestorCode() {
        return 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateApplicationNumber() {
        return 'APP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateAllocationCode() {
        return 'ALL-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateMonitoringCode() {
        return 'MON-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateEventCode() {
        return 'EVT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateParticipantCode() {
        return 'PRT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateReportCode() {
        return 'RPT-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Additional API Methods
    public function getInvestmentOpportunities($filters = []) {
        try {
            $sql = "SELECT * FROM investment_opportunities WHERE opportunity_status = 'published'";
            $params = [];

            if (!empty($filters['sector'])) {
                $sql .= " AND sector = ?";
                $params[] = $filters['sector'];
            }

            if (!empty($filters['investment_type'])) {
                $sql .= " AND investment_type = ?";
                $params[] = $filters['investment_type'];
            }

            if (!empty($filters['minimum_investment'])) {
                $sql .= " AND (maximum_investment >= ? OR maximum_investment IS NULL)";
                $params[] = $filters['minimum_investment'];
            }

            $sql .= " ORDER BY created_at DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAvailableIncentives($filters = []) {
        try {
            $sql = "SELECT * FROM investment_incentives WHERE incentive_status = 'active'";
            $params = [];

            if (!empty($filters['sector'])) {
                $sql .= " AND JSON_CONTAINS(eligible_sectors, JSON_QUOTE(?))";
                $params[] = $filters['sector'];
            }

            if (!empty($filters['minimum_investment'])) {
                $sql .= " AND (maximum_investment >= ? OR maximum_investment IS NULL)";
                $params[] = $filters['minimum_investment'];
            }

            $sql .= " ORDER BY created_at DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getInvestorProfile($investorId) {
        try {
            $sql = "SELECT * FROM investor_profiles WHERE id = ?";
            $result = $this->db->query($sql, [$investorId])->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Decode JSON fields
                $result['industry_focus'] = json_decode($result['industry_focus'], true);
                $result['preferred_sectors'] = json_decode($result['preferred_sectors'], true);
                $result['preferred_regions'] = json_decode($result['preferred_regions'], true);
                $result['regulatory_approvals'] = json_decode($result['regulatory_approvals'], true);
                $result['references'] = json_decode($result['references'], true);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getApplicationStatus($applicationId) {
        try {
            $sql = "SELECT * FROM investment_applications WHERE id = ?";
            $result = $this->db->query($sql, [$applicationId])->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Decode JSON fields
                $result['incentives_requested'] = json_decode($result['incentives_requested'], true);
                $result['other_documents'] = json_decode($result['other_documents'], true);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateApplicationStatus($applicationId, $status, $reviewerId = null, $notes = null) {
        try {
            $sql = "UPDATE investment_applications SET
                    application_status = ?,
                    reviewer_name = ?,
                    review_notes = ?,
                    review_date = CURRENT_DATE
                    WHERE id = ?";

            $this->db->query($sql, [$status, $reviewerId, $notes, $applicationId]);

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getMonitoringReports($applicationId) {
        try {
            $sql = "SELECT * FROM investment_monitoring WHERE application_id = ? ORDER BY monitoring_date DESC";
            return $this->db->query($sql, [$applicationId])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getUpcomingEvents($limit = 10) {
        try {
            $sql = "SELECT * FROM investment_promotion_events
                    WHERE event_date >= CURRENT_DATE AND event_status IN ('announced', 'registration_open')
                    ORDER BY event_date ASC LIMIT ?";
            return $this->db->query($sql, [$limit])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getEventParticipants($eventId) {
        try {
            $sql = "SELECT * FROM event_participants WHERE event_id = ? ORDER BY registration_date DESC";
            $results = $this->db->query($sql, [$eventId])->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['interested_sectors'] = json_decode($result['interested_sectors'], true);
            }

            return $results;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function generateInvestmentReport($period = 'quarterly', $year = null, $quarter = null) {
        try {
            $year = $year ?: date('Y');
            $quarter = $quarter ?: ceil(date('n') / 3);

            $reportData = [
                'report_period' => $period === 'quarterly' ? "Q{$quarter}-{$year}" : $year,
                'report_type' => $period,
                'report_date' => date('Y-m-d')
            ];

            // Calculate investment statistics
            $investmentStats = $this->calculateInvestmentStatistics($year, $quarter, $period);
            $reportData = array_merge($reportData, $investmentStats);

            // Calculate employment impact
            $employmentStats = $this->calculateEmploymentStatistics($year, $quarter, $period);
            $reportData = array_merge($reportData, $employmentStats);

            // Calculate incentive utilization
            $incentiveStats = $this->calculateIncentiveStatistics($year, $quarter, $period);
            $reportData = array_merge($reportData, $incentiveStats);

            return $this->createImpactReport($reportData);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function calculateInvestmentStatistics($year, $quarter, $period) {
        $dateCondition = $this->buildDateCondition($year, $quarter, $period);

        $sql = "SELECT
                COUNT(*) as investment_projects_count,
                SUM(proposed_investment) as total_investments,
                SUM(CASE WHEN YEAR(application_date) = ? THEN proposed_investment ELSE 0 END) as new_investments
                FROM investment_applications
                WHERE application_status IN ('approved', 'conditional_approval') {$dateCondition}";

        $result = $this->db->query($sql, [$year])->fetch(PDO::FETCH_ASSOC);

        return [
            'total_investments' => $result['total_investments'] ?: 0,
            'new_investments' => $result['new_investments'] ?: 0,
            'investment_projects_count' => $result['investment_projects_count'] ?: 0
        ];
    }

    private function calculateEmploymentStatistics($year, $quarter, $period) {
        $dateCondition = $this->buildDateCondition($year, $quarter, $period);

        $sql = "SELECT
                SUM(jobs_to_create) as total_jobs_created,
                AVG(jobs_to_create) as average_jobs_per_investment
                FROM investment_applications
                WHERE application_status IN ('approved', 'conditional_approval') {$dateCondition}";

        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'total_jobs_created' => $result['total_jobs_created'] ?: 0,
            'average_jobs_per_investment' => round($result['average_jobs_per_investment'] ?: 0, 2)
        ];
    }

    private function calculateIncentiveStatistics($year, $quarter, $period) {
        $dateCondition = $this->buildDateCondition($year, $quarter, $period);

        $sql = "SELECT
                SUM(allocated_amount) as incentives_allocated,
                SUM(utilized_amount) as incentives_utilized
                FROM incentive_allocations ia
                JOIN investment_applications ia2 ON ia.application_id = ia2.id
                WHERE ia2.application_status IN ('approved', 'conditional_approval') {$dateCondition}";

        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);

        $allocated = $result['incentives_allocated'] ?: 0;
        $utilized = $result['incentives_utilized'] ?: 0;

        return [
            'incentives_allocated' => $allocated,
            'incentives_utilized' => $utilized,
            'incentive_utilization_rate' => $allocated > 0 ? round(($utilized / $allocated) * 100, 2) : 0
        ];
    }

    private function buildDateCondition($year, $quarter, $period) {
        if ($period === 'annual') {
            return "AND YEAR(application_date) = {$year}";
        } else {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $quarter * 3;
            return "AND YEAR(application_date) = {$year} AND MONTH(application_date) BETWEEN {$startMonth} AND {$endMonth}";
        }
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [
            'database_table_prefix' => 'investment_',
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

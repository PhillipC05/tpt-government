<?php
/**
 * Community Development Module
 * Handles local development, infrastructure projects, and community programs
 */

require_once __DIR__ . '/../ServiceModule.php';

class CommunityDevelopmentModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Community Development Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive community development services including infrastructure projects, local development programs, and community engagement initiatives',
            'dependencies' => ['IdentityServices', 'SocialServices', 'FinancialManagement'],
            'permissions' => [
                'community.projects' => 'Apply for community development projects',
                'community.grants' => 'Apply for community grants',
                'community.permits' => 'Apply for development permits',
                'community.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS community_development_projects (
                id INT PRIMARY KEY AUTO_INCREMENT,
                project_number VARCHAR(20) UNIQUE NOT NULL,
                project_title VARCHAR(200) NOT NULL,
                project_description TEXT NOT NULL,

                -- Project Details
                project_type ENUM('infrastructure', 'housing', 'commercial', 'recreational', 'environmental', 'educational', 'healthcare', 'transportation', 'other') NOT NULL,
                project_category ENUM('new_construction', 'renovation', 'expansion', 'maintenance', 'demolition') DEFAULT 'new_construction',
                project_scale ENUM('small', 'medium', 'large', 'mega') DEFAULT 'medium',

                -- Location Information
                address TEXT NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                city VARCHAR(100) NOT NULL,
                region VARCHAR(100),
                country VARCHAR(100) DEFAULT 'Country',
                neighborhood VARCHAR(100),

                -- Project Scope and Timeline
                project_scope TEXT,
                estimated_start_date DATE,
                estimated_completion_date DATE,
                actual_start_date DATE,
                actual_completion_date DATE,
                project_duration_months INT,

                -- Financial Information
                total_budget DECIMAL(15,2) NOT NULL,
                funding_source VARCHAR(100),
                grant_amount DECIMAL(12,2) DEFAULT 0,
                local_contribution DECIMAL(12,2) DEFAULT 0,
                other_funding DECIMAL(12,2) DEFAULT 0,

                -- Project Status
                project_status ENUM('planning', 'approved', 'in_progress', 'completed', 'on_hold', 'cancelled', 'rejected') DEFAULT 'planning',
                approval_date DATE,
                completion_percentage DECIMAL(5,2) DEFAULT 0,

                -- Stakeholders
                project_manager VARCHAR(100),
                contractor_name VARCHAR(200),
                consultant_name VARCHAR(200),
                community_liaison VARCHAR(100),

                -- Environmental and Social Impact
                environmental_impact TEXT,
                social_impact TEXT,
                economic_impact TEXT,
                sustainability_features TEXT,

                -- Monitoring and Evaluation
                monitoring_plan TEXT,
                evaluation_criteria TEXT,
                success_metrics TEXT,

                -- Documentation
                project_documents TEXT,
                permits_required TEXT,
                regulatory_approvals TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_project_number (project_number),
                INDEX idx_project_type (project_type),
                INDEX idx_project_status (project_status),
                INDEX idx_city (city),
                INDEX idx_estimated_start_date (estimated_start_date),
                INDEX idx_completion_percentage (completion_percentage)
            );

            CREATE TABLE IF NOT EXISTS community_grants_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                grant_number VARCHAR(20) UNIQUE NOT NULL,
                grant_title VARCHAR(200) NOT NULL,
                grant_description TEXT,

                -- Grant Details
                grant_type ENUM('community_development', 'infrastructure', 'economic_development', 'social_services', 'environmental', 'cultural', 'education', 'health', 'other') NOT NULL,
                grant_category ENUM('competitive', 'formula', 'discretionary', 'block_grant') DEFAULT 'competitive',

                -- Eligibility and Requirements
                eligibility_criteria TEXT,
                application_requirements TEXT,
                minimum_grant_amount DECIMAL(10,2) DEFAULT 0,
                maximum_grant_amount DECIMAL(12,2),
                matching_funds_required BOOLEAN DEFAULT FALSE,
                matching_funds_percentage DECIMAL(5,2),

                -- Application Period
                application_start_date DATE NOT NULL,
                application_deadline DATE NOT NULL,
                review_start_date DATE,
                award_announcement_date DATE,

                -- Funding Information
                total_funding_available DECIMAL(15,2) NOT NULL,
                funding_source VARCHAR(100),
                number_of_grants_available INT,

                -- Program Status
                grant_status ENUM('draft', 'open', 'under_review', 'awarded', 'closed', 'cancelled') DEFAULT 'draft',
                applications_received INT DEFAULT 0,
                grants_awarded INT DEFAULT 0,
                total_amount_awarded DECIMAL(15,2) DEFAULT 0,

                -- Administration
                program_administrator VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),
                program_website VARCHAR(255),

                -- Review Process
                review_criteria TEXT,
                review_committee TEXT,
                evaluation_process TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_grant_number (grant_number),
                INDEX idx_grant_type (grant_type),
                INDEX idx_grant_status (grant_status),
                INDEX idx_application_deadline (application_deadline)
            );

            CREATE TABLE IF NOT EXISTS community_grants_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                grant_id INT NOT NULL,
                applicant_id INT NOT NULL,

                -- Application Details
                organization_name VARCHAR(200),
                contact_person VARCHAR(100) NOT NULL,
                contact_phone VARCHAR(20) NOT NULL,
                contact_email VARCHAR(255) NOT NULL,
                organization_address TEXT,

                -- Project Information
                project_title VARCHAR(200) NOT NULL,
                project_description TEXT NOT NULL,
                project_goals TEXT,
                target_beneficiaries TEXT,
                project_location TEXT,

                -- Financial Information
                requested_amount DECIMAL(12,2) NOT NULL,
                project_budget TEXT,
                other_funding_sources TEXT,
                matching_funds_amount DECIMAL(10,2) DEFAULT 0,

                -- Application Status
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'awarded', 'withdrawn') DEFAULT 'draft',
                submission_date DATE,
                review_date DATE,
                approval_date DATE,
                award_date DATE,

                -- Review Information
                reviewer_id INT NULL,
                review_score DECIMAL(5,2),
                review_comments TEXT,
                approval_rationale TEXT,
                denial_reason TEXT,

                -- Award Information
                awarded_amount DECIMAL(12,2) DEFAULT 0,
                award_conditions TEXT,
                reporting_requirements TEXT,

                -- Implementation
                implementation_start_date DATE,
                implementation_end_date DATE,
                progress_reports_required INT DEFAULT 0,
                final_report_due DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (grant_id) REFERENCES community_grants_programs(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_grant_id (grant_id),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_application_status (application_status),
                INDEX idx_submission_date (submission_date)
            );

            CREATE TABLE IF NOT EXISTS community_development_permits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permit_number VARCHAR(20) UNIQUE NOT NULL,
                project_id INT NOT NULL,

                -- Permit Details
                permit_type ENUM('building', 'zoning', 'environmental', 'utility', 'right_of_way', 'demolition', 'occupancy', 'other') NOT NULL,
                permit_category ENUM('major', 'minor', 'routine') DEFAULT 'minor',

                -- Application Details
                application_date DATE NOT NULL,
                permit_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'issued', 'expired', 'revoked') DEFAULT 'draft',
                approval_date DATE,
                issuance_date DATE,
                expiry_date DATE,

                -- Permit Conditions
                permit_conditions TEXT,
                special_requirements TEXT,
                inspection_requirements TEXT,

                -- Financial Information
                application_fee DECIMAL(8,2) DEFAULT 0,
                permit_fee DECIMAL(8,2) DEFAULT 0,
                total_fees DECIMAL(10,2) DEFAULT 0,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                -- Inspection and Compliance
                inspection_required BOOLEAN DEFAULT FALSE,
                inspection_date DATE,
                inspection_passed BOOLEAN DEFAULT FALSE,
                compliance_status ENUM('pending', 'compliant', 'non_compliant', 'conditional') DEFAULT 'pending',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES community_development_projects(id) ON DELETE CASCADE,
                INDEX idx_permit_number (permit_number),
                INDEX idx_project_id (project_id),
                INDEX idx_permit_type (permit_type),
                INDEX idx_permit_status (permit_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS community_partnerships (
                id INT PRIMARY KEY AUTO_INCREMENT,
                partnership_number VARCHAR(20) UNIQUE NOT NULL,
                partnership_name VARCHAR(200) NOT NULL,
                partnership_description TEXT,

                -- Partnership Details
                partnership_type ENUM('public_private', 'nonprofit_government', 'community_business', 'intergovernmental', 'international', 'other') NOT NULL,
                partnership_category ENUM('development', 'service_delivery', 'infrastructure', 'economic', 'social', 'environmental') DEFAULT 'development',

                -- Partners
                lead_partner VARCHAR(200) NOT NULL,
                partner_organizations TEXT NOT NULL,
                contact_person VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),

                -- Partnership Scope
                partnership_scope TEXT,
                target_areas TEXT,
                target_population TEXT,

                -- Agreement Details
                agreement_start_date DATE NOT NULL,
                agreement_end_date DATE,
                agreement_value DECIMAL(15,2),
                funding_commitment DECIMAL(12,2),

                -- Partnership Status
                partnership_status ENUM('proposed', 'negotiating', 'active', 'completed', 'terminated', 'on_hold') DEFAULT 'proposed',
                activation_date DATE,
                completion_date DATE,

                -- Performance and Monitoring
                performance_indicators TEXT,
                monitoring_plan TEXT,
                evaluation_schedule TEXT,

                -- Resources and Support
                resources_provided TEXT,
                technical_support TEXT,
                training_provided TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_partnership_number (partnership_number),
                INDEX idx_partnership_type (partnership_type),
                INDEX idx_partnership_status (partnership_status),
                INDEX idx_agreement_start_date (agreement_start_date)
            );

            CREATE TABLE IF NOT EXISTS community_capacity_building (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_number VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('leadership', 'organizational', 'technical', 'financial', 'community_engagement', 'other') NOT NULL,
                target_group ENUM('community_leaders', 'nonprofits', 'businesses', 'residents', 'youth', 'all') DEFAULT 'all',

                -- Program Content
                program_objectives TEXT,
                curriculum_overview TEXT,
                training_topics TEXT,
                skill_development_areas TEXT,

                -- Implementation
                delivery_method ENUM('workshop', 'training', 'mentoring', 'online_course', 'community_meeting', 'other') DEFAULT 'workshop',
                program_duration_weeks INT,
                session_frequency VARCHAR(50),
                total_sessions INT,

                -- Participation
                max_participants INT,
                min_participants INT DEFAULT 1,
                application_deadline DATE,
                selection_criteria TEXT,

                -- Program Status
                program_status ENUM('planning', 'recruiting', 'active', 'completed', 'cancelled') DEFAULT 'planning',
                start_date DATE,
                end_date DATE,
                participants_enrolled INT DEFAULT 0,

                -- Resources
                program_budget DECIMAL(10,2),
                funding_source VARCHAR(100),
                materials_provided TEXT,
                facilitators TEXT,

                -- Evaluation
                evaluation_method TEXT,
                success_metrics TEXT,
                participant_feedback TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_number (program_number),
                INDEX idx_program_type (program_type),
                INDEX idx_target_group (target_group),
                INDEX idx_program_status (program_status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS community_impact_assessments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assessment_number VARCHAR(20) UNIQUE NOT NULL,
                project_id INT NOT NULL,

                -- Assessment Details
                assessment_type ENUM('environmental', 'social', 'economic', 'cultural', 'health', 'comprehensive') DEFAULT 'comprehensive',
                assessment_date DATE NOT NULL,
                assessment_period VARCHAR(50),

                -- Impact Areas
                environmental_impact TEXT,
                social_impact TEXT,
                economic_impact TEXT,
                cultural_impact TEXT,
                health_impact TEXT,

                -- Quantitative Metrics
                population_affected INT,
                jobs_created INT,
                economic_value DECIMAL(15,2),
                environmental_benefit_score DECIMAL(5,2),

                -- Qualitative Assessment
                community_feedback TEXT,
                stakeholder_input TEXT,
                expert_opinions TEXT,

                -- Mitigation Measures
                mitigation_required BOOLEAN DEFAULT FALSE,
                mitigation_measures TEXT,
                monitoring_plan TEXT,

                -- Recommendations
                recommendations TEXT,
                follow_up_actions TEXT,
                improvement_suggestions TEXT,

                -- Assessment Results
                overall_rating ENUM('excellent', 'good', 'satisfactory', 'poor', 'critical') DEFAULT 'satisfactory',
                assessment_conclusion TEXT,

                -- Review and Approval
                assessor_name VARCHAR(100),
                reviewer_name VARCHAR(100),
                approval_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (project_id) REFERENCES community_development_projects(id) ON DELETE CASCADE,
                INDEX idx_assessment_number (assessment_number),
                INDEX idx_project_id (project_id),
                INDEX idx_assessment_type (assessment_type),
                INDEX idx_assessment_date (assessment_date),
                INDEX idx_overall_rating (overall_rating)
            );

            CREATE TABLE IF NOT EXISTS community_volunteer_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_number VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('community_service', 'environmental', 'educational', 'healthcare', 'emergency_response', 'other') NOT NULL,
                program_category ENUM('one_time', 'ongoing', 'seasonal', 'project_based') DEFAULT 'ongoing',

                -- Volunteer Requirements
                skills_required TEXT,
                time_commitment VARCHAR(100),
                age_requirements VARCHAR(50),
                background_check_required BOOLEAN DEFAULT FALSE,

                -- Program Schedule
                program_schedule TEXT,
                meeting_location VARCHAR(200),
                coordinator_contact VARCHAR(100),

                -- Capacity and Participation
                max_volunteers INT,
                current_volunteers INT DEFAULT 0,
                volunteer_coordinator VARCHAR(100),

                -- Program Status
                program_status ENUM('active', 'inactive', 'full', 'completed', 'cancelled') DEFAULT 'active',
                start_date DATE,
                end_date DATE,

                -- Impact Tracking
                hours_volunteered INT DEFAULT 0,
                projects_completed INT DEFAULT 0,
                community_impact TEXT,

                -- Resources and Support
                training_provided BOOLEAN DEFAULT FALSE,
                equipment_provided TEXT,
                transportation_assistance BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_number (program_number),
                INDEX idx_program_type (program_type),
                INDEX idx_program_status (program_status),
                INDEX idx_start_date (start_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup community development project workflow
        $projectWorkflow = [
            'name' => 'Community Development Project Process',
            'description' => 'Complete workflow for community development project approval and implementation',
            'steps' => [
                [
                    'name' => 'Project Proposal',
                    'type' => 'user_task',
                    'assignee' => 'project_proposer',
                    'form' => 'project_proposal_form'
                ],
                [
                    'name' => 'Initial Review',
                    'type' => 'service_task',
                    'service' => 'initial_review_service'
                ],
                [
                    'name' => 'Impact Assessment',
                    'type' => 'user_task',
                    'assignee' => 'impact_assessor',
                    'form' => 'impact_assessment_form'
                ],
                [
                    'name' => 'Community Consultation',
                    'type' => 'user_task',
                    'assignee' => 'community_liaison',
                    'form' => 'community_consultation_form'
                ],
                [
                    'name' => 'Technical Review',
                    'type' => 'user_task',
                    'assignee' => 'technical_reviewer',
                    'form' => 'technical_review_form'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'project_director',
                    'form' => 'final_approval_form'
                ]
            ]
        ];

        // Setup grant application workflow
        $grantWorkflow = [
            'name' => 'Community Grant Application Process',
            'description' => 'Complete workflow for community grant applications',
            'steps' => [
                [
                    'name' => 'Grant Application',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'grant_application_form'
                ],
                [
                    'name' => 'Eligibility Check',
                    'type' => 'service_task',
                    'service' => 'eligibility_check_service'
                ],
                [
                    'name' => 'Technical Review',
                    'type' => 'user_task',
                    'assignee' => 'grant_reviewer',
                    'form' => 'technical_review_form'
                ],
                [
                    'name' => 'Community Impact Review',
                    'type' => 'user_task',
                    'assignee' => 'community_reviewer',
                    'form' => 'community_impact_form'
                ],
                [
                    'name' => 'Final Decision',
                    'type' => 'user_task',
                    'assignee' => 'grant_committee',
                    'form' => 'final_decision_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/project_workflow.json', json_encode($projectWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/grant_workflow.json', json_encode($grantWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/project_docs',
            __DIR__ . '/uploads/grant_applications',
            __DIR__ . '/uploads/impact_assessments',
            __DIR__ . '/uploads/partnership_agreements',
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
            'community_volunteer_programs',
            'community_impact_assessments',
            'community_capacity_building',
            'community_partnerships',
            'community_development_permits',
            'community_grants_applications',
            'community_grants_programs',
            'community_development_projects'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function submitDevelopmentProject($data) {
        try {
            $this->validateProjectData($data);
            $projectNumber = $this->generateProjectNumber();

            $sql = "INSERT INTO community_development_projects (
                project_number, project_title, project_description, project_type,
                project_category, address, latitude, longitude, city, region,
                project_scope, estimated_start_date, estimated_completion_date,
                total_budget, funding_source, grant_amount, local_contribution,
                project_manager, contractor_name, environmental_impact,
                social_impact, economic_impact, monitoring_plan, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $projectId = $this->db->insert($sql, [
                $projectNumber, $data['project_title'], $data['project_description'],
                $data['project_type'], $data['project_category'] ?? 'new_construction',
                json_encode($data['address']), $data['latitude'] ?? null,
                $data['longitude'] ?? null, $data['city'], $data['region'] ?? null,
                $data['project_scope'] ?? null, $data['estimated_start_date'] ?? null,
                $data['estimated_completion_date'] ?? null, $data['total_budget'],
                $data['funding_source'] ?? null, $data['grant_amount'] ?? 0,
                $data['local_contribution'] ?? 0, $data['project_manager'] ?? null,
                $data['contractor_name'] ?? null, $data['environmental_impact'] ?? null,
                $data['social_impact'] ?? null, $data['economic_impact'] ?? null,
                $data['monitoring_plan'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'project_id' => $projectId,
                'project_number' => $projectNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createGrantProgram($data) {
        try {
            $this->validateGrantProgramData($data);
            $grantNumber = $this->generateGrantNumber();

            $sql = "INSERT INTO community_grants_programs (
                grant_number, grant_title, grant_description, grant_type,
                grant_category, eligibility_criteria, application_requirements,
                minimum_grant_amount, maximum_grant_amount, matching_funds_required,
                application_start_date, application_deadline, total_funding_available,
                funding_source, program_administrator, contact_email,
                contact_phone, review_criteria, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $grantId = $this->db->insert($sql, [
                $grantNumber, $data['grant_title'], $data['grant_description'] ?? null,
                $data['grant_type'], $data['grant_category'] ?? 'competitive',
                $data['eligibility_criteria'] ?? null, $data['application_requirements'] ?? null,
                $data['minimum_grant_amount'] ?? 0, $data['maximum_grant_amount'] ?? null,
                $data['matching_funds_required'] ?? false, $data['application_start_date'],
                $data['application_deadline'], $data['total_funding_available'],
                $data['funding_source'] ?? null, $data['program_administrator'] ?? null,
                $data['contact_email'] ?? null, $data['contact_phone'] ?? null,
                $data['review_criteria'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'grant_id' => $grantId,
                'grant_number' => $grantNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForGrant($data) {
        try {
            $this->validateGrantApplicationData($data);
            $applicationNumber = $this->generateApplicationNumber();

            $sql = "INSERT INTO community_grants_applications (
                application_number, grant_id, applicant_id, organization_name,
                contact_person, contact_phone, contact_email, organization_address,
                project_title, project_description, project_goals, target_beneficiaries,
                requested_amount, project_budget, other_funding_sources,
                matching_funds_amount, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['grant_id'], $data['applicant_id'],
                $data['organization_name'] ?? null, $data['contact_person'],
                $data['contact_phone'], $data['contact_email'],
                json_encode($data['organization_address'] ?? []),
                $data['project_title'], $data['project_description'],
                $data['project_goals'] ?? null, $data['target_beneficiaries'] ?? null,
                $data['requested_amount'], json_encode($data['project_budget'] ?? []),
                $data['other_funding_sources'] ?? null, $data['matching_funds_amount'] ?? 0,
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

    public function applyForDevelopmentPermit($data) {
        try {
            $this->validatePermitApplicationData($data);
            $permitNumber = $this->generatePermitNumber();

            $sql = "INSERT INTO community_development_permits (
                permit_number, project_id, permit_type, permit_category,
                application_date, permit_conditions, special_requirements,
                inspection_requirements, application_fee, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $permitId = $this->db->insert($sql, [
                $permitNumber, $data['project_id'], $data['permit_type'],
                $data['permit_category'] ?? 'minor', $data['application_date'],
                $data['permit_conditions'] ?? null, $data['special_requirements'] ?? null,
                $data['inspection_requirements'] ?? null, $data['application_fee'] ?? 0,
                $data['created_by']
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

    public function createPartnership($data) {
        try {
            $this->validatePartnershipData($data);
            $partnershipNumber = $this->generatePartnershipNumber();

            $sql = "INSERT INTO community_partnerships (
                partnership_number, partnership_name, partnership_description,
                partnership_type, partnership_category, lead_partner,
                partner_organizations, contact_person, contact_email,
                contact_phone, partnership_scope, agreement_start_date,
                agreement_end_date, agreement_value, partnership_status,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $partnershipId = $this->db->insert($sql, [
                $partnershipNumber, $data['partnership_name'],
                $data['partnership_description'] ?? null, $data['partnership_type'],
                $data['partnership_category'] ?? 'development', $data['lead_partner'],
                json_encode($data['partner_organizations']), $data['contact_person'] ?? null,
                $data['contact_email'] ?? null, $data['contact_phone'] ?? null,
                $data['partnership_scope'] ?? null, $data['agreement_start_date'],
                $data['agreement_end_date'] ?? null, $data['agreement_value'] ?? null,
                $data['partnership_status'] ?? 'proposed', $data['created_by']
            ]);

            return [
                'success' => true,
                'partnership_id' => $partnershipId,
                'partnership_number' => $partnershipNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createCapacityBuildingProgram($data) {
        try {
            $this->validateCapacityBuildingData($data);
            $programNumber = $this->generateProgramNumber();

            $sql = "INSERT INTO community_capacity_building (
                program_number, program_name, program_description, program_type,
                target_group, program_objectives, curriculum_overview,
                training_topics, delivery_method, program_duration_weeks,
                max_participants, application_deadline, program_budget,
                funding_source, evaluation_method, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programNumber, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'], $data['target_group'] ?? 'all',
                $data['program_objectives'] ?? null, $data['curriculum_overview'] ?? null,
                $data['training_topics'] ?? null, $data['delivery_method'] ?? 'workshop',
                $data['program_duration_weeks'] ?? null, $data['max_participants'] ?? null,
                $data['application_deadline'] ?? null, $data['program_budget'] ?? null,
                $data['funding_source'] ?? null, $data['evaluation_method'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'program_id' => $programId,
                'program_number' => $programNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function conductImpactAssessment($data) {
        try {
            $this->validateImpactAssessmentData($data);
            $assessmentNumber = $this->generateAssessmentNumber();

            $sql = "INSERT INTO community_impact_assessments (
                assessment_number, project_id, assessment_type, assessment_date,
                assessment_period, environmental_impact, social_impact,
                economic_impact, cultural_impact, health_impact,
                population_affected, jobs_created, economic_value,
                community_feedback, mitigation_required, recommendations,
                overall_rating, assessor_name, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $assessmentId = $this->db->insert($sql, [
                $assessmentNumber, $data['project_id'], $data['assessment_type'] ?? 'comprehensive',
                $data['assessment_date'], $data['assessment_period'] ?? null,
                $data['environmental_impact'] ?? null, $data['social_impact'] ?? null,
                $data['economic_impact'] ?? null, $data['cultural_impact'] ?? null,
                $data['health_impact'] ?? null, $data['population_affected'] ?? null,
                $data['jobs_created'] ?? null, $data['economic_value'] ?? null,
                $data['community_feedback'] ?? null, $data['mitigation_required'] ?? false,
                $data['recommendations'] ?? null, $data['overall_rating'] ?? 'satisfactory',
                $data['assessor_name'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'assessment_id' => $assessmentId,
                'assessment_number' => $assessmentNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createVolunteerProgram($data) {
        try {
            $this->validateVolunteerProgramData($data);
            $programNumber = $this->generateVolunteerProgramNumber();

            $sql = "INSERT INTO community_volunteer_programs (
                program_number, program_name, program_description, program_type,
                program_category, skills_required, time_commitment,
                age_requirements, background_check_required, program_schedule,
                meeting_location, max_volunteers, volunteer_coordinator,
                start_date, end_date, training_provided, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programNumber, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'], $data['program_category'] ?? 'ongoing',
                $data['skills_required'] ?? null, $data['time_commitment'] ?? null,
                $data['age_requirements'] ?? null, $data['background_check_required'] ?? false,
                $data['program_schedule'] ?? null, $data['meeting_location'] ?? null,
                $data['max_volunteers'] ?? null, $data['volunteer_coordinator'] ?? null,
                $data['start_date'] ?? null, $data['end_date'] ?? null,
                $data['training_provided'] ?? false, $data['created_by']
            ]);

            return [
                'success' => true,
                'program_id' => $programId,
                'program_number' => $programNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getProjectRecord($projectNumber, $userId) {
        $sql = "SELECT * FROM community_development_projects WHERE project_number = ?";
        $project = $this->db->fetch($sql, [$projectNumber]);

        if (!$project) {
            return ['success' => false, 'error' => 'Project not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToProject($project, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $permits = $this->getProjectPermits($project['id']);
        $assessments = $this->getProjectAssessments($project['id']);

        return [
            'success' => true,
            'project' => $project,
            'permits' => $permits,
            'assessments' => $assessments
        ];
    }

    public function getGrantPrograms($grantType = null, $status = 'open') {
        $where = [];
        $params = [];

        if ($grantType) {
            $where[] = "grant_type = ?";
            $params[] = $grantType;
        }

        if ($status) {
            $where[] = "grant_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM community_grants_programs{$whereClause} ORDER BY application_deadline DESC";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getCapacityBuildingPrograms($targetGroup = null, $programType = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($targetGroup) {
            $where[] = "target_group = ?";
            $params[] = $targetGroup;
        }

        if ($programType) {
            $where[] = "program_type = ?";
            $params[] = $programType;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM community_capacity_building{$whereClause} ORDER BY start_date";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getVolunteerPrograms($programType = null, $status = 'active') {
        $where = [];
        $params = [];

        if ($programType) {
            $where[] = "program_type = ?";
            $params[] = $programType;
        }

        if ($status) {
            $where[] = "program_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM community_volunteer_programs{$whereClause} ORDER BY start_date";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    // Helper Methods
    private function validateProjectData($data) {
        $required = [
            'project_title', 'project_description', 'project_type',
            'address', 'city', 'total_budget', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateGrantProgramData($data) {
        $required = [
            'grant_title', 'grant_type', 'application_start_date',
            'application_deadline', 'total_funding_available', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateGrantApplicationData($data) {
        $required = [
            'grant_id', 'applicant_id', 'contact_person', 'contact_phone',
            'contact_email', 'project_title', 'project_description',
            'requested_amount', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validatePermitApplicationData($data) {
        $required = ['project_id', 'permit_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validatePartnershipData($data) {
        $required = [
            'partnership_name', 'partnership_type', 'lead_partner',
            'partner_organizations', 'agreement_start_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCapacityBuildingData($data) {
        $required = ['program_name', 'program_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateImpactAssessmentData($data) {
        $required = ['project_id', 'assessment_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateVolunteerProgramData($data) {
        $required = ['program_name', 'program_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateProjectNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CDP{$date}{$random}";
    }

    private function generateGrantNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CGP{$date}{$random}";
    }

    private function generateApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CGA{$date}{$random}";
    }

    private function generatePermitNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CDP{$date}{$random}";
    }

    private function generatePartnershipNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CPT{$date}{$random}";
    }

    private function generateProgramNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CCB{$date}{$random}";
    }

    private function generateAssessmentNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CIA{$date}{$random}";
    }

    private function generateVolunteerProgramNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CVP{$date}{$random}";
    }

    private function hasAccessToProject($project, $userId) {
        // Check if user is the project manager or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getProjectPermits($projectId) {
        $sql = "SELECT * FROM community_development_permits WHERE project_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$projectId]);
    }

    private function getProjectAssessments($projectId) {
        $sql = "SELECT * FROM community_impact_assessments WHERE project_id = ? ORDER BY assessment_date DESC";
        return $this->db->fetchAll($sql, [$projectId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

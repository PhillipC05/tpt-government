<?php
/**
 * Research & Innovation Module
 * Handles research funding, innovation programs, and technology transfer
 */

require_once __DIR__ . '/../ServiceModule.php';

class ResearchInnovationModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Research & Innovation Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive research and innovation management including funding programs, technology transfer, intellectual property, and innovation ecosystems',
            'dependencies' => ['FinancialManagement', 'HigherEducation', 'Procurement'],
            'permissions' => [
                'research.funding' => 'Access research funding',
                'research.innovation' => 'Access innovation programs',
                'research.ip' => 'Access intellectual property services',
                'research.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS research_funding_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('competitive_grant', 'cooperative_agreement', 'contract', 'fellowship', 'prize_competition', 'other') DEFAULT 'competitive_grant',
                funding_agency VARCHAR(200) NOT NULL,
                research_category ENUM('basic', 'applied', 'translational', 'clinical', 'policy', 'other') DEFAULT 'basic',

                -- Funding Information
                total_funding_available DECIMAL(15,2),
                minimum_award_amount DECIMAL(10,2),
                maximum_award_amount DECIMAL(12,2),
                expected_number_awards INT,

                -- Eligibility
                eligible_applicants TEXT,
                citizenship_requirements VARCHAR(100),
                institutional_requirements TEXT,

                -- Application Process
                application_deadline DATE,
                application_instructions TEXT,
                review_process TEXT,
                award_notification_date DATE,

                -- Program Status
                program_status ENUM('draft', 'announced', 'open', 'under_review', 'awards_made', 'closed', 'cancelled') DEFAULT 'draft',
                program_start_date DATE,
                program_end_date DATE,

                -- Contact Information
                program_officer_name VARCHAR(100),
                program_officer_email VARCHAR(255),
                program_officer_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_funding_agency (funding_agency),
                INDEX idx_program_status (program_status),
                INDEX idx_application_deadline (application_deadline)
            );

            CREATE TABLE IF NOT EXISTS research_proposals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                proposal_number VARCHAR(20) UNIQUE NOT NULL,
                program_id INT NOT NULL,

                -- Principal Investigator
                principal_investigator_id INT NOT NULL,
                pi_name VARCHAR(200) NOT NULL,
                pi_institution VARCHAR(200),
                pi_department VARCHAR(100),
                pi_email VARCHAR(255),
                pi_phone VARCHAR(20),

                -- Proposal Details
                proposal_title VARCHAR(300) NOT NULL,
                proposal_abstract TEXT NOT NULL,
                research_field VARCHAR(100) NOT NULL,
                research_subfield VARCHAR(100),

                -- Research Plan
                research_objectives TEXT,
                research_methodology TEXT,
                research_significance TEXT,
                innovation_potential TEXT,

                -- Team and Resources
                co_investigators TEXT,
                research_team TEXT,
                facilities_equipment TEXT,
                collaborators TEXT,

                -- Budget
                total_budget_requested DECIMAL(12,2) NOT NULL,
                budget_justification TEXT,
                budget_breakdown TEXT,

                -- Timeline
                project_start_date DATE,
                project_end_date DATE,
                project_duration_months INT,

                -- Application Status
                application_status ENUM('draft', 'submitted', 'under_review', 'revision_requested', 'approved', 'awarded', 'rejected', 'withdrawn') DEFAULT 'draft',
                submission_date DATE,
                review_deadline DATE,

                -- Review Information
                reviewer_assignments TEXT,
                review_scores TEXT,
                review_comments TEXT,
                final_decision VARCHAR(100),

                -- Award Information
                award_amount DECIMAL(12,2) DEFAULT 0,
                award_date DATE,
                award_conditions TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (program_id) REFERENCES research_funding_programs(id) ON DELETE CASCADE,
                FOREIGN KEY (principal_investigator_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_proposal_number (proposal_number),
                INDEX idx_program_id (program_id),
                INDEX idx_principal_investigator_id (principal_investigator_id),
                INDEX idx_application_status (application_status),
                INDEX idx_submission_date (submission_date)
            );

            CREATE TABLE IF NOT EXISTS innovation_startup_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Focus
                innovation_sector ENUM('technology', 'healthcare', 'energy', 'agriculture', 'education', 'finance', 'manufacturing', 'other') DEFAULT 'technology',
                technology_readiness_level VARCHAR(50),
                target_market VARCHAR(200),

                -- Support Services
                funding_available BOOLEAN DEFAULT TRUE,
                mentorship_available BOOLEAN DEFAULT TRUE,
                workspace_available BOOLEAN DEFAULT FALSE,
                technical_support BOOLEAN DEFAULT TRUE,

                -- Eligibility Criteria
                minimum_team_size INT DEFAULT 1,
                maximum_team_size INT DEFAULT 10,
                required_expertise TEXT,
                previous_funding_experience BOOLEAN DEFAULT FALSE,

                -- Program Structure
                program_duration_months INT,
                milestones_required TEXT,
                reporting_frequency VARCHAR(50),

                -- Resources Provided
                funding_amount DECIMAL(10,2),
                equipment_access TEXT,
                training_programs TEXT,
                networking_opportunities TEXT,

                -- Success Metrics
                success_criteria TEXT,
                graduation_requirements TEXT,
                follow_up_support BOOLEAN DEFAULT TRUE,

                -- Program Status
                program_status ENUM('planning', 'active', 'full', 'completed', 'cancelled') DEFAULT 'planning',
                application_deadline DATE,
                program_start_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_innovation_sector (innovation_sector),
                INDEX idx_program_status (program_status)
            );

            CREATE TABLE IF NOT EXISTS startup_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                program_id INT NOT NULL,

                -- Company Information
                company_name VARCHAR(200) NOT NULL,
                company_description TEXT,
                company_website VARCHAR(255),
                company_stage ENUM('idea', 'prototype', 'mvp', 'beta', 'launched', 'scaling') DEFAULT 'idea',

                -- Founders and Team
                founder_name VARCHAR(200) NOT NULL,
                founder_email VARCHAR(255),
                founder_phone VARCHAR(20),
                team_members TEXT,
                team_expertise TEXT,

                -- Innovation Details
                innovation_summary TEXT NOT NULL,
                technology_description TEXT,
                market_opportunity TEXT,
                competitive_advantage TEXT,

                -- Business Model
                business_model TEXT,
                revenue_model TEXT,
                customer_segments TEXT,
                go_to_market_strategy TEXT,

                -- Financial Information
                funding_requested DECIMAL(10,2),
                funding_used TEXT,
                current_revenue DECIMAL(10,2) DEFAULT 0,
                projected_revenue DECIMAL(12,2),

                -- Application Status
                application_status ENUM('draft', 'submitted', 'under_review', 'interview_scheduled', 'accepted', 'rejected', 'withdrawn') DEFAULT 'draft',
                submission_date DATE,
                review_date DATE,

                -- Review Information
                reviewer_feedback TEXT,
                acceptance_decision VARCHAR(100),
                acceptance_date DATE,

                -- Program Participation
                program_start_date DATE,
                program_end_date DATE,
                milestones_achieved TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (program_id) REFERENCES innovation_startup_programs(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_program_id (program_id),
                INDEX idx_founder_email (founder_email),
                INDEX idx_application_status (application_status),
                INDEX idx_submission_date (submission_date)
            );

            CREATE TABLE IF NOT EXISTS intellectual_property (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ip_number VARCHAR(20) UNIQUE NOT NULL,
                owner_id INT NOT NULL,

                -- IP Details
                ip_type ENUM('patent', 'trademark', 'copyright', 'trade_secret', 'design_right', 'plant_variety') NOT NULL,
                ip_title VARCHAR(300) NOT NULL,
                ip_description TEXT,

                -- Filing Information
                filing_date DATE NOT NULL,
                filing_number VARCHAR(50),
                filing_country VARCHAR(100) DEFAULT 'Domestic',
                filing_status ENUM('draft', 'filed', 'published', 'granted', 'rejected', 'abandoned', 'expired') DEFAULT 'draft',

                -- Protection Details
                protection_start_date DATE,
                protection_end_date DATE,
                renewal_required BOOLEAN DEFAULT FALSE,
                renewal_date DATE,

                -- Commercialization
                commercialization_status ENUM('research', 'development', 'licensing', 'commercialized', 'abandoned') DEFAULT 'research',
                licensing_agreements TEXT,
                revenue_generated DECIMAL(12,2) DEFAULT 0,

                -- Related Research
                related_research TEXT,
                research_institution VARCHAR(200),
                research_funding_source VARCHAR(200),

                -- Legal Information
                patent_attorney VARCHAR(200),
                legal_status TEXT,
                infringement_claims TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (owner_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_ip_number (ip_number),
                INDEX idx_owner_id (owner_id),
                INDEX idx_ip_type (ip_type),
                INDEX idx_filing_status (filing_status),
                INDEX idx_filing_date (filing_date)
            );

            CREATE TABLE IF NOT EXISTS technology_transfer (
                id INT PRIMARY KEY AUTO_INCREMENT,
                transfer_number VARCHAR(20) UNIQUE NOT NULL,
                ip_id INT NOT NULL,

                -- Transfer Details
                transfer_type ENUM('license', 'assignment', 'joint_development', 'spin_off', 'other') NOT NULL,
                transfer_title VARCHAR(300) NOT NULL,
                transfer_description TEXT,

                -- Parties Involved
                licensor_name VARCHAR(200),
                licensee_name VARCHAR(200),
                licensee_type ENUM('company', 'university', 'government', 'nonprofit', 'individual') DEFAULT 'company',

                -- Transfer Terms
                transfer_date DATE,
                effective_date DATE,
                expiration_date DATE,
                royalty_rate DECIMAL(5,3),
                upfront_payment DECIMAL(10,2),

                -- Rights and Restrictions
                exclusive_rights BOOLEAN DEFAULT FALSE,
                territorial_rights TEXT,
                field_of_use TEXT,
                sublicensing_allowed BOOLEAN DEFAULT FALSE,

                -- Financial Terms
                total_value DECIMAL(12,2),
                payment_schedule TEXT,
                milestone_payments TEXT,

                -- Performance Obligations
                development_milestones TEXT,
                commercialization_requirements TEXT,
                reporting_requirements TEXT,

                -- Transfer Status
                transfer_status ENUM('negotiating', 'executed', 'active', 'terminated', 'completed') DEFAULT 'negotiating',
                execution_date DATE,
                termination_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (ip_id) REFERENCES intellectual_property(id) ON DELETE CASCADE,
                INDEX idx_transfer_number (transfer_number),
                INDEX idx_ip_id (ip_id),
                INDEX idx_transfer_type (transfer_type),
                INDEX idx_transfer_status (transfer_status)
            );

            CREATE TABLE IF NOT EXISTS innovation_hubs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                hub_code VARCHAR(20) UNIQUE NOT NULL,
                hub_name VARCHAR(200) NOT NULL,
                hub_description TEXT,

                -- Hub Details
                hub_type ENUM('incubator', 'accelerator', 'coworking', 'research_park', 'innovation_district', 'other') DEFAULT 'incubator',
                focus_area VARCHAR(200),
                geographic_location TEXT,

                -- Facilities and Services
                total_space_sqft INT,
                available_space_sqft INT,
                office_spaces INT DEFAULT 0,
                lab_spaces INT DEFAULT 0,
                meeting_rooms INT DEFAULT 0,

                -- Services Offered
                mentorship_programs BOOLEAN DEFAULT TRUE,
                funding_access BOOLEAN DEFAULT TRUE,
                technical_support BOOLEAN DEFAULT TRUE,
                business_development BOOLEAN DEFAULT TRUE,
                networking_events BOOLEAN DEFAULT TRUE,

                -- Membership and Fees
                membership_fee_monthly DECIMAL(6,2),
                membership_fee_annual DECIMAL(8,2),
                application_fee DECIMAL(6,2),

                -- Eligibility
                eligibility_criteria TEXT,
                target_industries TEXT,
                minimum_commitment_months INT,

                -- Performance Metrics
                total_residents INT DEFAULT 0,
                active_residents INT DEFAULT 0,
                graduated_companies INT DEFAULT 0,
                jobs_created INT DEFAULT 0,

                -- Hub Status
                hub_status ENUM('planning', 'operational', 'full', 'closed') DEFAULT 'planning',
                opening_date DATE,
                contact_person VARCHAR(100),
                contact_email VARCHAR(255),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_hub_code (hub_code),
                INDEX idx_hub_type (hub_type),
                INDEX idx_hub_status (hub_status)
            );

            CREATE TABLE IF NOT EXISTS innovation_challenges (
                id INT PRIMARY KEY AUTO_INCREMENT,
                challenge_code VARCHAR(20) UNIQUE NOT NULL,
                challenge_title VARCHAR(300) NOT NULL,
                challenge_description TEXT,

                -- Challenge Details
                challenge_category ENUM('technology', 'social', 'environmental', 'health', 'education', 'other') DEFAULT 'technology',
                challenge_type ENUM('ideation', 'prototype', 'pilot', 'implementation', 'other') DEFAULT 'ideation',

                -- Problem Statement
                problem_statement TEXT,
                target_population TEXT,
                desired_outcome TEXT,

                -- Challenge Parameters
                max_participants INT,
                team_size_min INT DEFAULT 1,
                team_size_max INT DEFAULT 5,
                eligibility_requirements TEXT,

                -- Timeline
                announcement_date DATE,
                submission_deadline DATE,
                review_period_days INT DEFAULT 30,
                winner_announcement_date DATE,

                -- Prizes and Incentives
                total_prize_pool DECIMAL(10,2),
                first_prize DECIMAL(8,2),
                second_prize DECIMAL(6,2),
                third_prize DECIMAL(6,2),
                additional_prizes TEXT,

                -- Resources Provided
                mentorship_available BOOLEAN DEFAULT TRUE,
                funding_available BOOLEAN DEFAULT FALSE,
                technical_support BOOLEAN DEFAULT TRUE,
                workspace_available BOOLEAN DEFAULT FALSE,

                -- Challenge Status
                challenge_status ENUM('draft', 'announced', 'open', 'closed', 'under_review', 'completed', 'cancelled') DEFAULT 'draft',
                total_submissions INT DEFAULT 0,
                winner_selected BOOLEAN DEFAULT FALSE,

                -- Organizer Information
                organizer_name VARCHAR(200),
                organizer_contact VARCHAR(255),
                sponsoring_organization VARCHAR(200),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_challenge_code (challenge_code),
                INDEX idx_challenge_category (challenge_category),
                INDEX idx_challenge_status (challenge_status),
                INDEX idx_submission_deadline (submission_deadline)
            );

            CREATE TABLE IF NOT EXISTS research_collaborations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                collaboration_number VARCHAR(20) UNIQUE NOT NULL,
                collaboration_title VARCHAR(300) NOT NULL,
                collaboration_description TEXT,

                -- Collaboration Details
                collaboration_type ENUM('academic_industry', 'inter_institutional', 'international', 'government_private', 'other') DEFAULT 'academic_industry',
                research_field VARCHAR(100) NOT NULL,
                primary_objective TEXT,

                -- Participating Organizations
                lead_organization VARCHAR(200) NOT NULL,
                participating_organizations TEXT,
                total_participants INT,

                -- Collaboration Scope
                collaboration_scope TEXT,
                expected_outputs TEXT,
                intellectual_property_arrangement TEXT,

                -- Funding and Resources
                total_budget DECIMAL(12,2),
                funding_sources TEXT,
                resource_contributions TEXT,

                -- Timeline
                start_date DATE,
                end_date DATE,
                key_milestones TEXT,

                -- Governance
                governance_structure TEXT,
                decision_making_process TEXT,
                conflict_resolution TEXT,

                -- Collaboration Status
                collaboration_status ENUM('proposed', 'negotiating', 'active', 'completed', 'terminated', 'on_hold') DEFAULT 'proposed',
                agreement_date DATE,
                termination_date DATE,

                -- Performance Monitoring
                progress_reports TEXT,
                success_metrics TEXT,
                evaluation_plan TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_collaboration_number (collaboration_number),
                INDEX idx_collaboration_type (collaboration_type),
                INDEX idx_research_field (research_field),
                INDEX idx_collaboration_status (collaboration_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup research funding workflow
        $fundingWorkflow = [
            'name' => 'Research Funding Application Process',
            'description' => 'Complete workflow for research funding applications',
            'steps' => [
                [
                    'name' => 'Proposal Submission',
                    'type' => 'user_task',
                    'assignee' => 'researcher',
                    'form' => 'research_proposal_form'
                ],
                [
                    'name' => 'Initial Screening',
                    'type' => 'service_task',
                    'service' => 'proposal_screening_service'
                ],
                [
                    'name' => 'Peer Review Assignment',
                    'type' => 'user_task',
                    'assignee' => 'program_officer',
                    'form' => 'reviewer_assignment_form'
                ],
                [
                    'name' => 'Peer Review',
                    'type' => 'user_task',
                    'assignee' => 'peer_reviewers',
                    'form' => 'peer_review_form'
                ],
                [
                    'name' => 'Program Officer Review',
                    'type' => 'user_task',
                    'assignee' => 'program_officer',
                    'form' => 'program_officer_review_form'
                ],
                [
                    'name' => 'Funding Decision',
                    'type' => 'user_task',
                    'assignee' => 'funding_committee',
                    'form' => 'funding_decision_form'
                ]
            ]
        ];

        // Setup innovation challenge workflow
        $challengeWorkflow = [
            'name' => 'Innovation Challenge Process',
            'description' => 'Complete workflow for innovation challenge management',
            'steps' => [
                [
                    'name' => 'Challenge Announcement',
                    'type' => 'user_task',
                    'assignee' => 'challenge_manager',
                    'form' => 'challenge_announcement_form'
                ],
                [
                    'name' => 'Solution Submission',
                    'type' => 'user_task',
                    'assignee' => 'participants',
                    'form' => 'solution_submission_form'
                ],
                [
                    'name' => 'Initial Screening',
                    'type' => 'service_task',
                    'service' => 'submission_screening_service'
                ],
                [
                    'name' => 'Expert Review',
                    'type' => 'user_task',
                    'assignee' => 'expert_reviewers',
                    'form' => 'expert_review_form'
                ],
                [
                    'name' => 'Final Selection',
                    'type' => 'user_task',
                    'assignee' => 'selection_committee',
                    'form' => 'final_selection_form'
                ],
                [
                    'name' => 'Winner Announcement',
                    'type' => 'user_task',
                    'assignee' => 'challenge_manager',
                    'form' => 'winner_announcement_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/funding_workflow.json', json_encode($fundingWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/challenge_workflow.json', json_encode($challengeWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/proposals',
            __DIR__ . '/uploads/startup_applications',
            __DIR__ . '/uploads/ip_documents',
            __DIR__ . '/uploads/transfer_agreements',
            __DIR__ . '/uploads/challenge_submissions',
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
            'research_collaborations',
            'innovation_challenges',
            'innovation_hubs',
            'technology_transfer',
            'intellectual_property',
            'startup_applications',
            'innovation_startup_programs',
            'research_proposals',
            'research_funding_programs'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createFundingProgram($data) {
        try {
            $this->validateFundingProgramData($data);
            $programCode = $this->generateProgramCode();

            $sql = "INSERT INTO research_funding_programs (
                program_code, program_name, program_description, program_type,
                funding_agency, research_category, total_funding_available,
                minimum_award_amount, maximum_award_amount, expected_number_awards,
                eligible_applicants, citizenship_requirements, institutional_requirements,
                application_deadline, application_instructions, review_process,
                award_notification_date, program_status, program_start_date,
                program_end_date, program_officer_name, program_officer_email,
                program_officer_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'] ?? 'competitive_grant', $data['funding_agency'],
                $data['research_category'] ?? 'basic', $data['total_funding_available'] ?? null,
                $data['minimum_award_amount'] ?? null, $data['maximum_award_amount'] ?? null,
                $data['expected_number_awards'] ?? null, $data['eligible_applicants'] ?? null,
                $data['citizenship_requirements'] ?? null, $data['institutional_requirements'] ?? null,
                $data['application_deadline'] ?? null, $data['application_instructions'] ?? null,
                $data['review_process'] ?? null, $data['award_notification_date'] ?? null,
                $data['program_status'] ?? 'draft', $data['program_start_date'] ?? null,
                $data['program_end_date'] ?? null, $data['program_officer_name'] ?? null,
                $data['program_officer_email'] ?? null, $data['program_officer_phone'] ?? null,
                $data['created_by']
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

    public function submitResearchProposal($data) {
        try {
            $this->validateResearchProposalData($data);
            $proposalNumber = $this->generateProposalNumber();

            $sql = "INSERT INTO research_proposals (
                proposal_number, program_id, principal_investigator_id, pi_name,
                pi_institution, pi_department, pi_email, pi_phone, proposal_title,
                proposal_abstract, research_field, research_subfield, research_objectives,
                research_methodology, research_significance, innovation_potential,
                co_investigators, research_team, facilities_equipment, collaborators,
                total_budget_requested, budget_justification, budget_breakdown,
                project_start_date, project_end_date, project_duration_months,
                submission_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $proposalId = $this->db->insert($sql, [
                $proposalNumber, $data['program_id'], $data['principal_investigator_id'],
                $data['pi_name'], $data['pi_institution'] ?? null, $data['pi_department'] ?? null,
                $data['pi_email'] ?? null, $data['pi_phone'] ?? null, $data['proposal_title'],
                $data['proposal_abstract'], $data['research_field'], $data['research_subfield'] ?? null,
                $data['research_objectives'] ?? null, $data['research_methodology'] ?? null,
                $data['research_significance'] ?? null, $data['innovation_potential'] ?? null,
                $data['co_investigators'] ?? null, $data['research_team'] ?? null,
                $data['facilities_equipment'] ?? null, $data['collaborators'] ?? null,
                $data['total_budget_requested'], $data['budget_justification'] ?? null,
                $data['budget_breakdown'] ?? null, $data['project_start_date'] ?? null,
                $data['project_end_date'] ?? null, $data['project_duration_months'] ?? null,
                $data['submission_date'], $data['created_by']
            ]);

            return [
                'success' => true,
                'proposal_id' => $proposalId,
                'proposal_number' => $proposalNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createInnovationProgram($data) {
        try {
            $this->validateInnovationProgramData($data);
            $programCode = $this->generateInnovationProgramCode();

            $sql = "INSERT INTO innovation_startup_programs (
                program_code, program_name, program_description, innovation_sector,
                technology_readiness_level, target_market, funding_available,
                mentorship_available, workspace_available, technical_support,
                minimum_team_size, maximum_team_size, required_expertise,
                previous_funding_experience, program_duration_months,
                milestones_required, reporting_frequency, funding_amount,
                equipment_access, training_programs, networking_opportunities,
                success_criteria, graduation_requirements, follow_up_support,
                program_status, application_deadline, program_start_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['program_name'], $data['program_description'] ?? null,
                $data['innovation_sector'] ?? 'technology', $data['technology_readiness_level'] ?? null,
                $data['target_market'] ?? null, $data['funding_available'] ?? true,
                $data['mentorship_available'] ?? true, $data['workspace_available'] ?? false,
                $data['technical_support'] ?? true, $data['minimum_team_size'] ?? 1,
                $data['maximum_team_size'] ?? 10, $data['required_expertise'] ?? null,
                $data['previous_funding_experience'] ?? false, $data['program_duration_months'] ?? null,
                $data['milestones_required'] ?? null, $data['reporting_frequency'] ?? null,
                $data['funding_amount'] ?? null, $data['equipment_access'] ?? null,
                $data['training_programs'] ?? null, $data['networking_opportunities'] ?? null,
                $data['success_criteria'] ?? null, $data['graduation_requirements'] ?? null,
                $data['follow_up_support'] ?? true, $data['program_status'] ?? 'planning',
                $data['application_deadline'] ?? null, $data['program_start_date'] ?? null,
                $data['created_by']
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

    public function submitStartupApplication($data) {
        try {
            $this->validateStartupApplicationData($data);
            $applicationNumber = $this->generateStartupApplicationNumber();

            $sql = "INSERT INTO startup_applications (
                application_number, program_id, company_name, company_description,
                company_website, company_stage, founder_name, founder_email,
                founder_phone, team_members, team_expertise, innovation_summary,
                technology_description, market_opportunity, competitive_advantage,
                business_model, revenue_model, customer_segments, go_to_market_strategy,
                funding_requested, funding_used, current_revenue, projected_revenue,
                submission_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['program_id'], $data['company_name'],
                $data['company_description'] ?? null, $data['company_website'] ?? null,
                $data['company_stage'] ?? 'idea', $data['founder_name'],
                $data['founder_email'] ?? null, $data['founder_phone'] ?? null,
                $data['team_members'] ?? null, $data['team_expertise'] ?? null,
                $data['innovation_summary'], $data['technology_description'] ?? null,
                $data['market_opportunity'] ?? null, $data['competitive_advantage'] ?? null,
                $data['business_model'] ?? null, $data['revenue_model'] ?? null,
                $data['customer_segments'] ?? null, $data['go_to_market_strategy'] ?? null,
                $data['funding_requested'] ?? null, $data['funding_used'] ?? null,
                $data['current_revenue'] ?? 0, $data['projected_revenue'] ?? null,
                $data['submission_date'], $data['created_by']
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

    public function registerIntellectualProperty($data) {
        try {
            $this->validateIPData($data);
            $ipNumber = $this->generateIPNumber();

            $sql = "INSERT INTO intellectual_property (
                ip_number, owner_id, ip_type, ip_title, ip_description,
                filing_date, filing_number, filing_country, filing_status,
                protection_start_date, protection_end_date, renewal_required,
                renewal_date, commercialization_status, licensing_agreements,
                revenue_generated, related_research, research_institution,
                research_funding_source, patent_attorney, legal_status,
                infringement_claims, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $ipId = $this->db->insert($sql, [
                $ipNumber, $data['owner_id'], $data['ip_type'], $data['ip_title'],
                $data['ip_description'] ?? null, $data['filing_date'],
                $data['filing_number'] ?? null, $data['filing_country'] ?? 'Domestic',
                $data['filing_status'] ?? 'draft', $data['protection_start_date'] ?? null,
                $data['protection_end_date'] ?? null, $data['renewal_required'] ?? false,
                $data['renewal_date'] ?? null, $data['commercialization_status'] ?? 'research',
                $data['licensing_agreements'] ?? null, $data['revenue_generated'] ?? 0,
                $data['related_research'] ?? null, $data['research_institution'] ?? null,
                $data['research_funding_source'] ?? null, $data['patent_attorney'] ?? null,
                $data['legal_status'] ?? null, $data['infringement_claims'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'ip_id' => $ipId,
                'ip_number' => $ipNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTechnologyTransfer($data) {
        try {
            $this->validateTechnologyTransferData($data);
            $transferNumber = $this->generateTransferNumber();

            $sql = "INSERT INTO technology_transfer (
                transfer_number, ip_id, transfer_type, transfer_title,
                transfer_description, licensor_name, licensee_name,
                licensee_type, transfer_date, effective_date, expiration_date,
                royalty_rate, upfront_payment, exclusive_rights, territorial_rights,
                field_of_use, sublicensing_allowed, total_value, payment_schedule,
                milestone_payments, development_milestones, commercialization_requirements,
                reporting_requirements, transfer_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $transferId = $this->db->insert($sql, [
                $transferNumber, $data['ip_id'], $data['transfer_type'],
                $data['transfer_title'], $data['transfer_description'] ?? null,
                $data['licensor_name'] ?? null, $data['licensee_name'] ?? null,
                $data['licensee_type'] ?? 'company', $data['transfer_date'] ?? null,
                $data['effective_date'] ?? null, $data['expiration_date'] ?? null,
                $data['royalty_rate'] ?? null, $data['upfront_payment'] ?? null,
                $data['exclusive_rights'] ?? false, $data['territorial_rights'] ?? null,
                $data['field_of_use'] ?? null, $data['sublicensing_allowed'] ?? false,
                $data['total_value'] ?? null, $data['payment_schedule'] ?? null,
                $data['milestone_payments'] ?? null, $data['development_milestones'] ?? null,
                $data['commercialization_requirements'] ?? null, $data['reporting_requirements'] ?? null,
                $data['transfer_status'] ?? 'negotiating', $data['created_by']
            ]);

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'transfer_number' => $transferNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createInnovationHub($data) {
        try {
            $this->validateInnovationHubData($data);
            $hubCode = $this->generateHubCode();

            $sql = "INSERT INTO innovation_hubs (
                hub_code, hub_name, hub_description, hub_type, focus_area,
                geographic_location, total_space_sqft, available_space_sqft,
                office_spaces, lab_spaces, meeting_rooms, mentorship_programs,
                funding_access, technical_support, business_development,
                networking_events, membership_fee_monthly, membership_fee_annual,
                application_fee, eligibility_criteria, target_industries,
                minimum_commitment_months, hub_status, opening_date,
                contact_person, contact_email, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $hubId = $this->db->insert($sql, [
                $hubCode, $data['hub_name'], $data['hub_description'] ?? null,
                $data['hub_type'] ?? 'incubator', $data['focus_area'] ?? null,
                json_encode($data['geographic_location'] ?? []), $data['total_space_sqft'] ?? null,
                $data['available_space_sqft'] ?? null, $data['office_spaces'] ?? 0,
                $data['lab_spaces'] ?? 0, $data['meeting_rooms'] ?? 0,
                $data['mentorship_programs'] ?? true, $data['funding_access'] ?? true,
                $data['technical_support'] ?? true, $data['business_development'] ?? true,
                $data['networking_events'] ?? true, $data['membership_fee_monthly'] ?? null,
                $data['membership_fee_annual'] ?? null, $data['application_fee'] ?? null,
                $data['eligibility_criteria'] ?? null, $data['target_industries'] ?? null,
                $data['minimum_commitment_months'] ?? null, $data['hub_status'] ?? 'planning',
                $data['opening_date'] ?? null, $data['contact_person'] ?? null,
                $data['contact_email'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'hub_id' => $hubId,
                'hub_code' => $hubCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createInnovationChallenge($data) {
        try {
            $this->validateInnovationChallengeData($data);
            $challengeCode = $this->generateChallengeCode();

            $sql = "INSERT INTO innovation_challenges (
                challenge_code, challenge_title, challenge_description,
                challenge_category, challenge_type, problem_statement,
                target_population, desired_outcome, max_participants,
                team_size_min, team_size_max, eligibility_requirements,
                announcement_date, submission_deadline, review_period_days,
                winner_announcement_date, total_prize_pool, first_prize,
                second_prize, third_prize, additional_prizes, mentorship_available,
                funding_available, technical_support, workspace_available,
                challenge_status, organizer_name, organizer_contact,
                sponsoring_organization, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $challengeId = $this->db->insert($sql, [
                $challengeCode, $data['challenge_title'], $data['challenge_description'] ?? null,
                $data['challenge_category'] ?? 'technology', $data['challenge_type'] ?? 'ideation',
                $data['problem_statement'] ?? null, $data['target_population'] ?? null,
                $data['desired_outcome'] ?? null, $data['max_participants'] ?? null,
                $data['team_size_min'] ?? 1, $data['team_size_max'] ?? 5,
                $data['eligibility_requirements'] ?? null, $data['announcement_date'] ?? null,
                $data['submission_deadline'] ?? null, $data['review_period_days'] ?? 30,
                $data['winner_announcement_date'] ?? null, $data['total_prize_pool'] ?? null,
                $data['first_prize'] ?? null, $data['second_prize'] ?? null,
                $data['third_prize'] ?? null, $data['additional_prizes'] ?? null,
                $data['mentorship_available'] ?? true, $data['funding_available'] ?? false,
                $data['technical_support'] ?? true, $data['workspace_available'] ?? false,
                $data['challenge_status'] ?? 'draft', $data['organizer_name'] ?? null,
                $data['organizer_contact'] ?? null, $data['sponsoring_organization'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'challenge_id' => $challengeId,
                'challenge_code' => $challengeCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createResearchCollaboration($data) {
        try {
            $this->validateResearchCollaborationData($data);
            $collaborationNumber = $this->generateCollaborationNumber();

            $sql = "INSERT INTO research_collaborations (
                collaboration_number, collaboration_title, collaboration_description,
                collaboration_type, research_field, primary_objective,
                lead_organization, participating_organizations, total_participants,
                collaboration_scope, expected_outputs, intellectual_property_arrangement,
                total_budget, funding_sources, resource_contributions, start_date,
                end_date, key_milestones, governance_structure, decision_making_process,
                conflict_resolution, collaboration_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $collaborationId = $this->db->insert($sql, [
                $collaborationNumber, $data['collaboration_title'],
                $data['collaboration_description'] ?? null, $data['collaboration_type'] ?? 'academic_industry',
                $data['research_field'], $data['primary_objective'] ?? null,
                $data['lead_organization'], $data['participating_organizations'] ?? null,
                $data['total_participants'] ?? null, $data['collaboration_scope'] ?? null,
                $data['expected_outputs'] ?? null, $data['intellectual_property_arrangement'] ?? null,
                $data['total_budget'] ?? null, $data['funding_sources'] ?? null,
                $data['resource_contributions'] ?? null, $data['start_date'] ?? null,
                $data['end_date'] ?? null, $data['key_milestones'] ?? null,
                $data['governance_structure'] ?? null, $data['decision_making_process'] ?? null,
                $data['conflict_resolution'] ?? null, $data['collaboration_status'] ?? 'proposed',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'collaboration_id' => $collaborationId,
                'collaboration_number' => $collaborationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getFundingPrograms($agency = null, $status = null) {
        $where = [];
        $params = [];

        if ($agency) {
            $where[] = "funding_agency = ?";
            $params[] = $agency;
        }

        if ($status) {
            $where[] = "program_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM research_funding_programs{$whereClause} ORDER BY application_deadline DESC";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getInnovationPrograms($sector = null, $status = null) {
        $where = [];
        $params = [];

        if ($sector) {
            $where[] = "innovation_sector = ?";
            $params[] = $sector;
        }

        if ($status) {
            $where[] = "program_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM innovation_startup_programs{$whereClause} ORDER BY program_start_date DESC";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getInnovationChallenges($category = null, $status = null) {
        $where = [];
        $params = [];

        if ($category) {
            $where[] = "challenge_category = ?";
            $params[] = $category;
        }

        if ($status) {
            $where[] = "challenge_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM innovation_challenges{$whereClause} ORDER BY submission_deadline DESC";

        $challenges = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'challenges' => $challenges
        ];
    }

    public function getInnovationHubs($type = null, $status = null) {
        $where = [];
        $params = [];

        if ($type) {
            $where[] = "hub_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $where[] = "hub_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM innovation_hubs{$whereClause} ORDER BY hub_name";

        $hubs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'hubs' => $hubs
        ];
    }

    // Helper Methods
    private function validateFundingProgramData($data) {
        $required = [
            'program_name', 'funding_agency', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateResearchProposalData($data) {
        $required = [
            'program_id', 'principal_investigator_id', 'pi_name',
            'proposal_title', 'proposal_abstract', 'research_field',
            'total_budget_requested', 'submission_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateInnovationProgramData($data) {
        $required = [
            'program_name', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateStartupApplicationData($data) {
        $required = [
            'program_id', 'company_name', 'founder_name',
            'innovation_summary', 'submission_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateIPData($data) {
        $required = [
            'owner_id', 'ip_type', 'ip_title', 'filing_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateTechnologyTransferData($data) {
        $required = [
            'ip_id', 'transfer_type', 'transfer_title', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateInnovationHubData($data) {
        $required = [
            'hub_name', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateInnovationChallengeData($data) {
        $required = [
            'challenge_title', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateResearchCollaborationData($data) {
        $required = [
            'collaboration_title', 'research_field', 'lead_organization', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateProgramCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "RFP{$date}{$random}";
    }

    private function generateProposalNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PROP{$date}{$random}";
    }

    private function generateInnovationProgramCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "INNO{$date}{$random}";
    }

    private function generateStartupApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "START{$date}{$random}";
    }

    private function generateIPNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "IP{$date}{$random}";
    }

    private function generateTransferNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "TRANS{$date}{$random}";
    }

    private function generateHubCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "HUB{$date}{$random}";
    }

    private function generateChallengeCode() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CHAL{$date}{$random}";
    }

    private function generateCollaborationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "COLLAB{$date}{$random}";
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

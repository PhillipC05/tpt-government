<?php
/**
 * Indigenous Affairs Module
 * Handles indigenous rights, cultural preservation, and community services
 */

require_once __DIR__ . '/../ServiceModule.php';

class IndigenousAffairsModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Indigenous Affairs Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive indigenous affairs services including cultural preservation, land rights, and community development programs',
            'dependencies' => ['IdentityServices', 'SocialServices', 'CommunityDevelopment'],
            'permissions' => [
                'indigenous.register' => 'Register indigenous status',
                'indigenous.cultural' => 'Access cultural preservation services',
                'indigenous.land' => 'Access land rights services',
                'indigenous.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS indigenous_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                registration_number VARCHAR(20) UNIQUE NOT NULL,
                individual_id INT NOT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE NOT NULL,
                gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NOT NULL,
                place_of_birth VARCHAR(200),

                -- Indigenous Identity
                indigenous_nation VARCHAR(200) NOT NULL,
                tribal_affiliation VARCHAR(200),
                clan_or_band VARCHAR(100),
                traditional_territory VARCHAR(200),
                language_spoken TEXT,
                cultural_practices TEXT,

                -- Legal Status
                citizenship_status ENUM('citizen', 'permanent_resident', 'temporary_resident', 'refugee', 'other') DEFAULT 'citizen',
                treaty_rights TEXT,
                land_rights TEXT,
                fishing_rights TEXT,
                hunting_rights TEXT,

                -- Family Information
                parents_names TEXT,
                grandparents_names TEXT,
                spouse_name VARCHAR(200),
                children_names TEXT,
                family_tree TEXT,

                -- Contact Information
                primary_address TEXT NOT NULL,
                mailing_address TEXT,
                phone VARCHAR(20),
                email VARCHAR(255),
                emergency_contact_name VARCHAR(200),
                emergency_contact_phone VARCHAR(20),

                -- Documentation
                status_card_number VARCHAR(50),
                status_card_expiry DATE,
                band_council_number VARCHAR(50),
                certificate_of_indian_status VARCHAR(50),

                -- Registration Details
                registration_date DATE NOT NULL,
                registration_status ENUM('pending', 'verified', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
                verification_date DATE,
                verified_by VARCHAR(100),

                -- Cultural Preservation
                traditional_name VARCHAR(200),
                ceremonial_name VARCHAR(200),
                cultural_artifacts TEXT,
                traditional_knowledge TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_registration_number (registration_number),
                INDEX idx_individual_id (individual_id),
                INDEX idx_indigenous_nation (indigenous_nation),
                INDEX idx_registration_status (registration_status),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_land_claims (
                id INT PRIMARY KEY AUTO_INCREMENT,
                claim_number VARCHAR(20) UNIQUE NOT NULL,
                claimant_id INT NOT NULL,

                -- Claim Details
                claim_type ENUM('traditional_territory', 'specific_tract', 'treaty_land', 'reserve_land', 'ceded_land', 'other') NOT NULL,
                claim_title VARCHAR(300) NOT NULL,
                claim_description TEXT NOT NULL,

                -- Location Information
                traditional_territory VARCHAR(200),
                specific_location TEXT,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                land_area_hectares DECIMAL(12,2),
                legal_description TEXT,

                -- Historical Context
                historical_significance TEXT,
                traditional_use TEXT,
                oral_history TEXT,
                archaeological_sites TEXT,

                -- Legal Basis
                treaty_references TEXT,
                legal_precedents TEXT,
                government_agreements TEXT,
                international_law TEXT,

                -- Claim Status
                claim_status ENUM('filed', 'under_review', 'negotiating', 'resolved', 'rejected', 'appealed', 'withdrawn') DEFAULT 'filed',
                filing_date DATE NOT NULL,
                resolution_date DATE,
                resolution_outcome TEXT,

                -- Involved Parties
                claimant_nation VARCHAR(200) NOT NULL,
                government_representative VARCHAR(200),
                legal_counsel VARCHAR(200),
                mediator_arbitrator VARCHAR(200),

                -- Financial Aspects
                compensation_claimed DECIMAL(15,2),
                compensation_awarded DECIMAL(15,2),
                settlement_terms TEXT,

                -- Documentation
                supporting_documents TEXT,
                maps_coordinates TEXT,
                witness_testimonies TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (claimant_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_claim_number (claim_number),
                INDEX idx_claimant_id (claimant_id),
                INDEX idx_claim_type (claim_type),
                INDEX idx_claim_status (claim_status),
                INDEX idx_filing_date (filing_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_cultural_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_number VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('language_preservation', 'cultural_education', 'traditional_knowledge', 'arts_crafts', 'ceremonial_practices', 'land_stewardship', 'other') NOT NULL,
                target_audience ENUM('youth', 'adults', 'elders', 'families', 'community', 'all') DEFAULT 'all',

                -- Cultural Focus
                indigenous_nation VARCHAR(200),
                language_focus VARCHAR(100),
                cultural_practice VARCHAR(200),
                traditional_knowledge_area TEXT,

                -- Program Structure
                program_format ENUM('workshop', 'course', 'mentorship', 'apprenticeship', 'ceremony', 'cultural_camp', 'other') DEFAULT 'workshop',
                duration_hours DECIMAL(6,2),
                number_of_sessions INT,
                session_frequency VARCHAR(50),

                -- Implementation
                start_date DATE,
                end_date DATE,
                program_location VARCHAR(200),
                instructor_facilitator VARCHAR(200),

                -- Resources and Materials
                required_materials TEXT,
                cultural_artifacts TEXT,
                teaching_resources TEXT,

                -- Program Status
                program_status ENUM('planning', 'active', 'completed', 'cancelled', 'on_hold') DEFAULT 'planning',
                max_participants INT,
                current_participants INT DEFAULT 0,

                -- Funding and Support
                funding_source VARCHAR(100),
                program_budget DECIMAL(10,2),
                external_partners TEXT,

                -- Evaluation
                learning_objectives TEXT,
                success_metrics TEXT,
                cultural_impact TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_number (program_number),
                INDEX idx_program_type (program_type),
                INDEX idx_target_audience (target_audience),
                INDEX idx_program_status (program_status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_business_support (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                applicant_id INT NOT NULL,

                -- Business Information
                business_name VARCHAR(200) NOT NULL,
                business_type ENUM('cultural_tourism', 'traditional_crafts', 'natural_resources', 'consulting', 'education', 'other') NOT NULL,
                business_description TEXT,

                -- Indigenous Ownership
                indigenous_ownership_percentage DECIMAL(5,2),
                indigenous_partners TEXT,
                tribal_council_approval BOOLEAN DEFAULT FALSE,

                -- Business Details
                business_address TEXT,
                years_in_operation INT,
                number_of_employees INT,
                annual_revenue DECIMAL(12,2),

                -- Support Requested
                support_type ENUM('grant', 'loan', 'training', 'mentorship', 'marketing', 'equipment', 'other') NOT NULL,
                support_amount DECIMAL(10,2),
                support_description TEXT,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'awarded', 'completed') DEFAULT 'draft',
                review_date DATE,
                approval_date DATE,

                -- Review Information
                reviewer_id INT NULL,
                review_score DECIMAL(5,2),
                review_comments TEXT,
                approval_rationale TEXT,

                -- Award Information
                awarded_amount DECIMAL(10,2) DEFAULT 0,
                award_conditions TEXT,
                implementation_timeline TEXT,

                -- Business Impact
                jobs_created INT DEFAULT 0,
                cultural_preservation_impact TEXT,
                community_benefit TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (applicant_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_business_type (business_type),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_health_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_number VARCHAR(20) UNIQUE NOT NULL,
                patient_id INT NOT NULL,

                -- Service Details
                service_type ENUM('traditional_medicine', 'cultural_mental_health', 'preventive_care', 'chronic_disease', 'emergency_care', 'other') NOT NULL,
                service_date DATE NOT NULL,
                service_time TIME,
                service_location VARCHAR(200),

                -- Traditional Medicine
                traditional_practitioner VARCHAR(200),
                traditional_medicine_used TEXT,
                ceremonial_context TEXT,

                -- Western Medicine Integration
                western_medical_provider VARCHAR(200),
                western_diagnosis TEXT,
                western_treatment TEXT,

                -- Cultural Considerations
                cultural_considerations TEXT,
                traditional_healing_methods TEXT,
                family_involvement TEXT,

                -- Service Outcomes
                service_outcome TEXT,
                patient_satisfaction DECIMAL(3,2),
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE,

                -- Documentation
                treatment_notes TEXT,
                medications_prescribed TEXT,
                referrals_made TEXT,

                -- Cultural Preservation
                traditional_knowledge_documented TEXT,
                cultural_practices_observed TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (patient_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_service_number (service_number),
                INDEX idx_patient_id (patient_id),
                INDEX idx_service_type (service_type),
                INDEX idx_service_date (service_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_education_grants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                grant_number VARCHAR(20) UNIQUE NOT NULL,
                student_id INT NOT NULL,

                -- Student Information
                student_name VARCHAR(200) NOT NULL,
                educational_institution VARCHAR(200),
                program_of_study VARCHAR(200),
                year_of_study INT,
                expected_graduation_date DATE,

                -- Grant Details
                grant_type ENUM('post_secondary', 'vocational_training', 'cultural_education', 'language_training', 'research', 'other') NOT NULL,
                grant_amount DECIMAL(10,2) NOT NULL,
                grant_period_months INT,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'awarded', 'completed', 'terminated') DEFAULT 'draft',
                approval_date DATE,
                award_date DATE,

                -- Academic Performance
                gpa DECIMAL(3,2),
                academic_standing VARCHAR(50),
                courses_completed TEXT,

                -- Cultural Component
                cultural_relevance TEXT,
                indigenous_knowledge_integration TEXT,
                community_benefit TEXT,

                -- Financial Information
                tuition_cost DECIMAL(8,2),
                other_funding_sources TEXT,
                financial_need_assessment TEXT,

                -- Progress Tracking
                progress_reports_required INT DEFAULT 0,
                last_progress_report DATE,
                academic_progress_rating ENUM('excellent', 'good', 'satisfactory', 'needs_improvement') DEFAULT 'good',

                -- Completion and Outcomes
                completion_date DATE,
                graduation_status ENUM('completed', 'withdrawn', 'transferred', 'ongoing') DEFAULT 'ongoing',
                employment_outcome TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (student_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_grant_number (grant_number),
                INDEX idx_student_id (student_id),
                INDEX idx_grant_type (grant_type),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_community_development (
                id INT PRIMARY KEY AUTO_INCREMENT,
                project_number VARCHAR(20) UNIQUE NOT NULL,
                community_id INT NOT NULL,

                -- Project Information
                project_name VARCHAR(200) NOT NULL,
                project_description TEXT,
                project_type ENUM('infrastructure', 'housing', 'economic_development', 'education', 'health', 'cultural', 'environmental', 'other') NOT NULL,

                -- Community Information
                community_name VARCHAR(200) NOT NULL,
                indigenous_nation VARCHAR(200),
                population_size INT,
                geographic_location TEXT,

                -- Project Scope
                project_scope TEXT,
                target_beneficiaries INT,
                expected_outcomes TEXT,

                -- Implementation
                start_date DATE,
                completion_date DATE,
                project_manager VARCHAR(200),
                implementing_partner VARCHAR(200),

                -- Funding
                total_budget DECIMAL(15,2),
                funding_sources TEXT,
                grant_contributions DECIMAL(12,2),

                -- Progress Tracking
                project_status ENUM('planning', 'active', 'completed', 'on_hold', 'cancelled') DEFAULT 'planning',
                completion_percentage DECIMAL(5,2) DEFAULT 0,
                milestones_achieved TEXT,

                -- Cultural Integration
                cultural_considerations TEXT,
                traditional_knowledge_incorporated TEXT,
                community_participation TEXT,

                -- Monitoring and Evaluation
                monitoring_plan TEXT,
                evaluation_method TEXT,
                impact_assessment TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (community_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_project_number (project_number),
                INDEX idx_community_id (community_id),
                INDEX idx_project_type (project_type),
                INDEX idx_project_status (project_status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS indigenous_research_permits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permit_number VARCHAR(20) UNIQUE NOT NULL,
                researcher_id INT NOT NULL,

                -- Research Details
                research_title VARCHAR(300) NOT NULL,
                research_description TEXT NOT NULL,
                research_methodology TEXT,
                research_objectives TEXT,

                -- Researcher Information
                researcher_name VARCHAR(200) NOT NULL,
                institution_affiliation VARCHAR(200),
                researcher_credentials TEXT,
                contact_information TEXT,

                -- Cultural Research
                cultural_sensitivity_training BOOLEAN DEFAULT FALSE,
                community_consultation_plan TEXT,
                benefit_sharing_agreement TEXT,
                intellectual_property_rights TEXT,

                -- Research Location and Scope
                research_location TEXT,
                research_duration_months INT,
                sample_size INT,
                data_collection_methods TEXT,

                -- Permit Details
                permit_type ENUM('academic_research', 'cultural_documentation', 'archaeological', 'ethnographic', 'other') DEFAULT 'academic_research',
                application_date DATE NOT NULL,
                permit_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'issued', 'expired', 'revoked') DEFAULT 'draft',
                approval_date DATE,
                expiry_date DATE,

                -- Review and Approval
                reviewer_id INT NULL,
                review_comments TEXT,
                approval_conditions TEXT,

                -- Monitoring and Compliance
                reporting_requirements TEXT,
                site_visits_required BOOLEAN DEFAULT FALSE,
                compliance_monitoring TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (researcher_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_permit_number (permit_number),
                INDEX idx_researcher_id (researcher_id),
                INDEX idx_permit_type (permit_type),
                INDEX idx_permit_status (permit_status),
                INDEX idx_application_date (application_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup indigenous registration workflow
        $registrationWorkflow = [
            'name' => 'Indigenous Registration Process',
            'description' => 'Complete workflow for indigenous status registration and verification',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'indigenous_registration_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Cultural Verification',
                    'type' => 'user_task',
                    'assignee' => 'cultural_officer',
                    'form' => 'cultural_verification_form'
                ],
                [
                    'name' => 'Community Consultation',
                    'type' => 'user_task',
                    'assignee' => 'community_liaison',
                    'form' => 'community_consultation_form'
                ],
                [
                    'name' => 'Final Approval',
                    'type' => 'user_task',
                    'assignee' => 'indigenous_affairs_officer',
                    'form' => 'final_approval_form'
                ]
            ]
        ];

        // Setup land claims workflow
        $landClaimsWorkflow = [
            'name' => 'Indigenous Land Claims Process',
            'description' => 'Complete workflow for indigenous land claims processing',
            'steps' => [
                [
                    'name' => 'Claim Filing',
                    'type' => 'user_task',
                    'assignee' => 'claimant',
                    'form' => 'land_claim_form'
                ],
                [
                    'name' => 'Initial Assessment',
                    'type' => 'service_task',
                    'service' => 'initial_assessment_service'
                ],
                [
                    'name' => 'Legal Review',
                    'type' => 'user_task',
                    'assignee' => 'legal_officer',
                    'form' => 'legal_review_form'
                ],
                [
                    'name' => 'Community Consultation',
                    'type' => 'user_task',
                    'assignee' => 'community_representative',
                    'form' => 'community_consultation_form'
                ],
                [
                    'name' => 'Negotiation Phase',
                    'type' => 'user_task',
                    'assignee' => 'negotiator',
                    'form' => 'negotiation_form'
                ],
                [
                    'name' => 'Resolution',
                    'type' => 'user_task',
                    'assignee' => 'resolution_officer',
                    'form' => 'resolution_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/registration_workflow.json', json_encode($registrationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/land_claims_workflow.json', json_encode($landClaimsWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/registration_docs',
            __DIR__ . '/uploads/land_claim_docs',
            __DIR__ . '/uploads/cultural_programs',
            __DIR__ . '/uploads/health_records',
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
            'indigenous_research_permits',
            'indigenous_community_development',
            'indigenous_education_grants',
            'indigenous_health_services',
            'indigenous_business_support',
            'indigenous_cultural_programs',
            'indigenous_land_claims',
            'indigenous_registrations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerIndigenousStatus($data) {
        try {
            $this->validateIndigenousRegistrationData($data);
            $registrationNumber = $this->generateRegistrationNumber();

            $sql = "INSERT INTO indigenous_registrations (
                registration_number, individual_id, first_name, middle_name,
                last_name, date_of_birth, gender, place_of_birth, indigenous_nation,
                tribal_affiliation, clan_or_band, traditional_territory,
                language_spoken, cultural_practices, citizenship_status,
                treaty_rights, land_rights, fishing_rights, hunting_rights,
                parents_names, grandparents_names, spouse_name, children_names,
                family_tree, primary_address, mailing_address, phone, email,
                emergency_contact_name, emergency_contact_phone,
                status_card_number, status_card_expiry, band_council_number,
                certificate_of_indian_status, registration_date, traditional_name,
                ceremonial_name, cultural_artifacts, traditional_knowledge,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $registrationId = $this->db->insert($sql, [
                $registrationNumber, $data['individual_id'], $data['first_name'],
                $data['middle_name'] ?? null, $data['last_name'], $data['date_of_birth'],
                $data['gender'], $data['place_of_birth'] ?? null, $data['indigenous_nation'],
                $data['tribal_affiliation'] ?? null, $data['clan_or_band'] ?? null,
                $data['traditional_territory'] ?? null, $data['language_spoken'] ?? null,
                $data['cultural_practices'] ?? null, $data['citizenship_status'] ?? 'citizen',
                $data['treaty_rights'] ?? null, $data['land_rights'] ?? null,
                $data['fishing_rights'] ?? null, $data['hunting_rights'] ?? null,
                $data['parents_names'] ?? null, $data['grandparents_names'] ?? null,
                $data['spouse_name'] ?? null, $data['children_names'] ?? null,
                $data['family_tree'] ?? null, json_encode($data['primary_address']),
                json_encode($data['mailing_address'] ?? []), $data['phone'] ?? null,
                $data['email'] ?? null, $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null, $data['status_card_number'] ?? null,
                $data['status_card_expiry'] ?? null, $data['band_council_number'] ?? null,
                $data['certificate_of_indian_status'] ?? null, date('Y-m-d'),
                $data['traditional_name'] ?? null, $data['ceremonial_name'] ?? null,
                $data['cultural_artifacts'] ?? null, $data['traditional_knowledge'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'registration_id' => $registrationId,
                'registration_number' => $registrationNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fileLandClaim($data) {
        try {
            $this->validateLandClaimData($data);
            $claimNumber = $this->generateClaimNumber();

            $sql = "INSERT INTO indigenous_land_claims (
                claim_number, claimant_id, claim_type, claim_title,
                claim_description, traditional_territory, specific_location,
                latitude, longitude, land_area_hectares, legal_description,
                historical_significance, traditional_use, oral_history,
                archaeological_sites, treaty_references, legal_precedents,
                government_agreements, international_law, filing_date,
                claimant_nation, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $claimId = $this->db->insert($sql, [
                $claimNumber, $data['claimant_id'], $data['claim_type'],
                $data['claim_title'], $data['claim_description'],
                $data['traditional_territory'] ?? null, $data['specific_location'] ?? null,
                $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['land_area_hectares'] ?? null, $data['legal_description'] ?? null,
                $data['historical_significance'] ?? null, $data['traditional_use'] ?? null,
                $data['oral_history'] ?? null, $data['archaeological_sites'] ?? null,
                $data['treaty_references'] ?? null, $data['legal_precedents'] ?? null,
                $data['government_agreements'] ?? null, $data['international_law'] ?? null,
                $data['filing_date'], $data['claimant_nation'], $data['created_by']
            ]);

            return [
                'success' => true,
                'claim_id' => $claimId,
                'claim_number' => $claimNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createCulturalProgram($data) {
        try {
            $this->validateCulturalProgramData($data);
            $programNumber = $this->generateProgramNumber();

            $sql = "INSERT INTO indigenous_cultural_programs (
                program_number, program_name, program_description, program_type,
                target_audience, indigenous_nation, language_focus,
                cultural_practice, traditional_knowledge_area, program_format,
                duration_hours, number_of_sessions, session_frequency,
                start_date, end_date, program_location, instructor_facilitator,
                required_materials, cultural_artifacts, teaching_resources,
                max_participants, funding_source, program_budget,
                external_partners, learning_objectives, success_metrics,
                cultural_impact, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programNumber, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'], $data['target_audience'] ?? 'all',
                $data['indigenous_nation'] ?? null, $data['language_focus'] ?? null,
                $data['cultural_practice'] ?? null, $data['traditional_knowledge_area'] ?? null,
                $data['program_format'] ?? 'workshop', $data['duration_hours'] ?? null,
                $data['number_of_sessions'] ?? null, $data['session_frequency'] ?? null,
                $data['start_date'] ?? null, $data['end_date'] ?? null,
                $data['program_location'] ?? null, $data['instructor_facilitator'] ?? null,
                $data['required_materials'] ?? null, $data['cultural_artifacts'] ?? null,
                $data['teaching_resources'] ?? null, $data['max_participants'] ?? null,
                $data['funding_source'] ?? null, $data['program_budget'] ?? null,
                $data['external_partners'] ?? null, $data['learning_objectives'] ?? null,
                $data['success_metrics'] ?? null, $data['cultural_impact'] ?? null,
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

    public function applyForBusinessSupport($data) {
        try {
            $this->validateBusinessSupportData($data);
            $applicationNumber = $this->generateApplicationNumber();

            $sql = "INSERT INTO indigenous_business_support (
                application_number, applicant_id, business_name, business_type,
                business_description, indigenous_ownership_percentage,
                indigenous_partners, tribal_council_approval, business_address,
                years_in_operation, number_of_employees, annual_revenue,
                support_type, support_amount, support_description,
                application_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['applicant_id'], $data['business_name'],
                $data['business_type'], $data['business_description'] ?? null,
                $data['indigenous_ownership_percentage'] ?? null,
                $data['indigenous_partners'] ?? null, $data['tribal_council_approval'] ?? false,
                json_encode($data['business_address'] ?? []), $data['years_in_operation'] ?? null,
                $data['number_of_employees'] ?? null, $data['annual_revenue'] ?? null,
                $data['support_type'], $data['support_amount'] ?? null,
                $data['support_description'] ?? null, $data['application_date'],
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

    public function recordHealthService($data) {
        try {
            $this->validateHealthServiceData($data);
            $serviceNumber = $this->generateServiceNumber();

            $sql = "INSERT INTO indigenous_health_services (
                service_number, patient_id, service_type, service_date,
                service_time, service_location, traditional_practitioner,
                traditional_medicine_used, ceremonial_context,
                western_medical_provider, western_diagnosis, western_treatment,
                cultural_considerations, traditional_healing_methods,
                family_involvement, service_outcome, patient_satisfaction,
                follow_up_required, follow_up_date, treatment_notes,
                medications_prescribed, referrals_made,
                traditional_knowledge_documented, cultural_practices_observed,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $serviceId = $this->db->insert($sql, [
                $serviceNumber, $data['patient_id'], $data['service_type'],
                $data['service_date'], $data['service_time'] ?? null,
                $data['service_location'] ?? null, $data['traditional_practitioner'] ?? null,
                $data['traditional_medicine_used'] ?? null, $data['ceremonial_context'] ?? null,
                $data['western_medical_provider'] ?? null, $data['western_diagnosis'] ?? null,
                $data['western_treatment'] ?? null, $data['cultural_considerations'] ?? null,
                $data['traditional_healing_methods'] ?? null, $data['family_involvement'] ?? null,
                $data['service_outcome'] ?? null, $data['patient_satisfaction'] ?? null,
                $data['follow_up_required'] ?? false, $data['follow_up_date'] ?? null,
                $data['treatment_notes'] ?? null, $data['medications_prescribed'] ?? null,
                $data['referrals_made'] ?? null, $data['traditional_knowledge_documented'] ?? null,
                $data['cultural_practices_observed'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'service_id' => $serviceId,
                'service_number' => $serviceNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForEducationGrant($data) {
        try {
            $this->validateEducationGrantData($data);
            $grantNumber = $this->generateEducationGrantNumber();

            $sql = "INSERT INTO indigenous_education_grants (
                grant_number, student_id, student_name, educational_institution,
                program_of_study, year_of_study, expected_graduation_date,
                grant_type, grant_amount, grant_period_months,
                application_date, gpa, academic_standing, courses_completed,
                cultural_relevance, indigenous_knowledge_integration,
                community_benefit, tuition_cost, other_funding_sources,
                financial_need_assessment, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $grantId = $this->db->insert($sql, [
                $grantNumber, $data['student_id'], $data['student_name'],
                $data['educational_institution'] ?? null, $data['program_of_study'] ?? null,
                $data['year_of_study'] ?? null, $data['expected_graduation_date'] ?? null,
                $data['grant_type'], $data['grant_amount'], $data['grant_period_months'] ?? null,
                $data['application_date'], $data['gpa'] ?? null,
                $data['academic_standing'] ?? null, $data['courses_completed'] ?? null,
                $data['cultural_relevance'] ?? null, $data['indigenous_knowledge_integration'] ?? null,
                $data['community_benefit'] ?? null, $data['tuition_cost'] ?? null,
                $data['other_funding_sources'] ?? null, $data['financial_need_assessment'] ?? null,
                $data['created_by']
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

    public function createCommunityDevelopmentProject($data) {
        try {
            $this->validateCommunityDevelopmentData($data);
            $projectNumber = $this->generateCommunityProjectNumber();

            $sql = "INSERT INTO indigenous_community_development (
                project_number, community_id, project_name, project_description,
                project_type, community_name, indigenous_nation, population_size,
                geographic_location, project_scope, target_beneficiaries,
                expected_outcomes, start_date, completion_date, project_manager,
                implementing_partner, total_budget, funding_sources,
                grant_contributions, cultural_considerations,
                traditional_knowledge_incorporated, community_participation,
                monitoring_plan, evaluation_method, impact_assessment,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $projectId = $this->db->insert($sql, [
                $projectNumber, $data['community_id'], $data['project_name'],
                $data['project_description'] ?? null, $data['project_type'],
                $data['community_name'], $data['indigenous_nation'] ?? null,
                $data['population_size'] ?? null, $data['geographic_location'] ?? null,
                $data['project_scope'] ?? null, $data['target_beneficiaries'] ?? null,
                $data['expected_outcomes'] ?? null, $data['start_date'] ?? null,
                $data['completion_date'] ?? null, $data['project_manager'] ?? null,
                $data['implementing_partner'] ?? null, $data['total_budget'] ?? null,
                $data['funding_sources'] ?? null, $data['grant_contributions'] ?? null,
                $data['cultural_considerations'] ?? null,
                $data['traditional_knowledge_incorporated'] ?? null,
                $data['community_participation'] ?? null, $data['monitoring_plan'] ?? null,
                $data['evaluation_method'] ?? null, $data['impact_assessment'] ?? null,
                $data['created_by']
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

    public function applyForResearchPermit($data) {
        try {
            $this->validateResearchPermitData($data);
            $permitNumber = $this->generateResearchPermitNumber();

            $sql = "INSERT INTO indigenous_research_permits (
                permit_number, researcher_id, research_title, research_description,
                research_methodology, research_objectives, researcher_name,
                institution_affiliation, researcher_credentials, contact_information,
                cultural_sensitivity_training, community_consultation_plan,
                benefit_sharing_agreement, intellectual_property_rights,
                research_location, research_duration_months, sample_size,
                data_collection_methods, permit_type, application_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $permitId = $this->db->insert($sql, [
                $permitNumber, $data['researcher_id'], $data['research_title'],
                $data['research_description'], $data['research_methodology'] ?? null,
                $data['research_objectives'] ?? null, $data['researcher_name'],
                $data['institution_affiliation'] ?? null, $data['researcher_credentials'] ?? null,
                json_encode($data['contact_information'] ?? []), $data['cultural_sensitivity_training'] ?? false,
                $data['community_consultation_plan'] ?? null, $data['benefit_sharing_agreement'] ?? null,
                $data['intellectual_property_rights'] ?? null, $data['research_location'] ?? null,
                $data['research_duration_months'] ?? null, $data['sample_size'] ?? null,
                $data['data_collection_methods'] ?? null, $data['permit_type'] ?? 'academic_research',
                $data['application_date'], $data['created_by']
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

    public function getIndigenousRecord($registrationNumber, $userId) {
        $sql = "SELECT * FROM indigenous_registrations WHERE registration_number = ?";
        $record = $this->db->fetch($sql, [$registrationNumber]);

        if (!$record) {
            return ['success' => false, 'error' => 'Indigenous registration not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToIndigenousRecord($record, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $landClaims = $this->getIndigenousLandClaims($record['id']);
        $culturalPrograms = $this->getIndigenousCulturalPrograms($record['id']);
        $businessSupport = $this->getIndigenousBusinessSupport($record['id']);
        $healthServices = $this->getIndigenousHealthServices($record['id']);
        $educationGrants = $this->getIndigenousEducationGrants($record['id']);

        return [
            'success' => true,
            'record' => $record,
            'land_claims' => $landClaims,
            'cultural_programs' => $culturalPrograms,
            'business_support' => $businessSupport,
            'health_services' => $healthServices,
            'education_grants' => $educationGrants
        ];
    }

    public function getCulturalPrograms($programType = null, $targetAudience = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($programType) {
            $where[] = "program_type = ?";
            $params[] = $programType;
        }

        if ($targetAudience) {
            $where[] = "target_audience = ?";
            $params[] = $targetAudience;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM indigenous_cultural_programs{$whereClause} ORDER BY start_date";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getLandClaims($claimType = null, $status = null) {
        $where = [];
        $params = [];

        if ($claimType) {
            $where[] = "claim_type = ?";
            $params[] = $claimType;
        }

        if ($status) {
            $where[] = "claim_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM indigenous_land_claims{$whereClause} ORDER BY filing_date DESC";

        $claims = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'claims' => $claims
        ];
    }

    public function getBusinessSupportApplications($businessType = null, $status = null) {
        $where = [];
        $params = [];

        if ($businessType) {
            $where[] = "business_type = ?";
            $params[] = $businessType;
        }

        if ($status) {
            $where[] = "application_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM indigenous_business_support{$whereClause} ORDER BY application_date DESC";

        $applications = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'applications' => $applications
        ];
    }

    // Helper Methods
    private function validateIndigenousRegistrationData($data) {
        $required = [
            'individual_id', 'first_name', 'last_name', 'date_of_birth',
            'gender', 'indigenous_nation', 'primary_address', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateLandClaimData($data) {
        $required = [
            'claimant_id', 'claim_type', 'claim_title', 'claim_description',
            'filing_date', 'claimant_nation', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCulturalProgramData($data) {
        $required = ['program_name', 'program_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateBusinessSupportData($data) {
        $required = [
            'applicant_id', 'business_name', 'business_type',
            'support_type', 'application_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateHealthServiceData($data) {
        $required = [
            'patient_id', 'service_type', 'service_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateEducationGrantData($data) {
        $required = [
            'student_id', 'student_name', 'grant_type', 'grant_amount',
            'application_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCommunityDevelopmentData($data) {
        $required = [
            'community_id', 'project_name', 'project_type',
            'community_name', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateResearchPermitData($data) {
        $required = [
            'researcher_id', 'research_title', 'research_description',
            'researcher_name', 'application_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateRegistrationNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "IND{$date}{$random}";
    }

    private function generateClaimNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "LND{$date}{$random}";
    }

    private function generateProgramNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CUL{$date}{$random}";
    }

    private function generateApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "BUS{$date}{$random}";
    }

    private function generateServiceNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "HEA{$date}{$random}";
    }

    private function generateEducationGrantNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "EDU{$date}{$random}";
    }

    private function generateCommunityProjectNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "COM{$date}{$random}";
    }

    private function generateResearchPermitNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "RES{$date}{$random}";
    }

    private function hasAccessToIndigenousRecord($record, $userId) {
        // Check if user is the individual or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getIndigenousLandClaims($registrationId) {
        $sql = "SELECT * FROM indigenous_land_claims WHERE claimant_id = ? ORDER BY filing_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getIndigenousCulturalPrograms($registrationId) {
        $sql = "SELECT * FROM indigenous_cultural_programs WHERE indigenous_nation = (SELECT indigenous_nation FROM indigenous_registrations WHERE id = ?) ORDER BY start_date";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getIndigenousBusinessSupport($registrationId) {
        $sql = "SELECT * FROM indigenous_business_support WHERE applicant_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getIndigenousHealthServices($registrationId) {
        $sql = "SELECT * FROM indigenous_health_services WHERE patient_id = ? ORDER BY service_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function getIndigenousEducationGrants($registrationId) {
        $sql = "SELECT * FROM indigenous_education_grants WHERE student_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$registrationId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

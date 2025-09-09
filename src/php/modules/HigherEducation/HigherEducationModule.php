<?php
/**
 * Higher Education Module
 * Handles university admissions, student loans, and research funding
 */

require_once __DIR__ . '/../ServiceModule.php';

class HigherEducationModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Higher Education Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive higher education services including university admissions, student financial aid, research funding, and academic program management',
            'dependencies' => ['IdentityServices', 'FinancialManagement', 'SocialServices'],
            'permissions' => [
                'education.admissions' => 'Access university admissions',
                'education.financial_aid' => 'Access student financial aid',
                'education.research' => 'Access research funding',
                'education.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS university_institutions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                institution_code VARCHAR(20) UNIQUE NOT NULL,
                institution_name VARCHAR(200) NOT NULL,
                institution_type ENUM('university', 'college', 'technical_institute', 'community_college', 'research_institute', 'other') NOT NULL,

                -- Institution Details
                accreditation_status VARCHAR(100),
                address TEXT NOT NULL,
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),
                website VARCHAR(255),

                -- Academic Programs
                total_students INT DEFAULT 0,
                total_faculty INT DEFAULT 0,
                undergraduate_programs INT DEFAULT 0,
                graduate_programs INT DEFAULT 0,
                doctoral_programs INT DEFAULT 0,

                -- Rankings and Accreditation
                national_ranking INT,
                international_ranking INT,
                accreditation_body VARCHAR(200),
                last_accreditation_date DATE,

                -- Financial Information
                tuition_range_undergraduate VARCHAR(100),
                tuition_range_graduate VARCHAR(100),
                average_aid_package DECIMAL(10,2),
                endowment_amount DECIMAL(15,2),

                -- Status
                institution_status ENUM('active', 'inactive', 'accreditation_pending', 'under_review') DEFAULT 'active',
                registration_date DATE NOT NULL,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_institution_code (institution_code),
                INDEX idx_institution_type (institution_type),
                INDEX idx_institution_status (institution_status),
                INDEX idx_national_ranking (national_ranking)
            );

            CREATE TABLE IF NOT EXISTS academic_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                institution_id INT NOT NULL,

                -- Program Information
                program_name VARCHAR(200) NOT NULL,
                program_level ENUM('certificate', 'diploma', 'associate', 'bachelor', 'master', 'doctoral', 'postdoctoral') NOT NULL,
                program_type ENUM('arts', 'sciences', 'engineering', 'business', 'education', 'health', 'law', 'other') NOT NULL,

                -- Program Details
                program_description TEXT,
                duration_years DECIMAL(3,1),
                total_credits INT,
                language_of_instruction VARCHAR(50) DEFAULT 'English',

                -- Admission Requirements
                minimum_gpa DECIMAL(3,2),
                required_tests TEXT,
                prerequisite_subjects TEXT,
                english_proficiency VARCHAR(100),

                -- Tuition and Fees
                tuition_per_year DECIMAL(10,2),
                application_fee DECIMAL(6,2) DEFAULT 0,
                additional_fees TEXT,

                -- Program Status
                program_status ENUM('active', 'inactive', 'new', 'under_review') DEFAULT 'active',
                accreditation_status VARCHAR(100),

                -- Enrollment
                max_enrollment INT,
                current_enrollment INT DEFAULT 0,
                international_students INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (institution_id) REFERENCES university_institutions(id) ON DELETE CASCADE,
                INDEX idx_program_code (program_code),
                INDEX idx_institution_id (institution_id),
                INDEX idx_program_level (program_level),
                INDEX idx_program_type (program_type),
                INDEX idx_program_status (program_status)
            );

            CREATE TABLE IF NOT EXISTS student_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_number VARCHAR(20) UNIQUE NOT NULL,
                student_id INT NOT NULL,
                program_id INT NOT NULL,

                -- Application Details
                application_date DATE NOT NULL,
                application_status ENUM('draft', 'submitted', 'under_review', 'accepted', 'rejected', 'waitlisted', 'withdrawn') DEFAULT 'draft',
                application_deadline DATE,

                -- Academic Information
                high_school_gpa DECIMAL(3,2),
                standardized_test_scores TEXT,
                academic_transcript TEXT,
                recommendation_letters TEXT,

                -- Personal Information
                personal_statement TEXT,
                extracurricular_activities TEXT,
                work_experience TEXT,
                awards_honors TEXT,

                -- Financial Information
                financial_need_assessment BOOLEAN DEFAULT FALSE,
                requested_financial_aid DECIMAL(10,2),
                family_income DECIMAL(12,2),

                -- Review Information
                reviewer_id INT NULL,
                review_date DATE NULL,
                review_notes TEXT,
                admission_decision VARCHAR(100),

                -- Acceptance Details
                acceptance_deadline DATE,
                deposit_amount DECIMAL(6,2),
                deposit_deadline DATE,
                orientation_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (student_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                FOREIGN KEY (program_id) REFERENCES academic_programs(id) ON DELETE CASCADE,
                INDEX idx_application_number (application_number),
                INDEX idx_student_id (student_id),
                INDEX idx_program_id (program_id),
                INDEX idx_application_status (application_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS student_financial_aid (
                id INT PRIMARY KEY AUTO_INCREMENT,
                aid_number VARCHAR(20) UNIQUE NOT NULL,
                student_id INT NOT NULL,
                application_id INT NULL,

                -- Aid Details
                aid_type ENUM('grant', 'scholarship', 'loan', 'work_study', 'fellowship', 'other') NOT NULL,
                aid_category ENUM('need_based', 'merit_based', 'athletic', 'research', 'diversity', 'other') DEFAULT 'need_based',

                -- Financial Information
                award_amount DECIMAL(10,2) NOT NULL,
                disbursement_schedule TEXT,
                repayment_terms TEXT,

                -- Eligibility and Requirements
                eligibility_criteria TEXT,
                renewal_requirements TEXT,
                gpa_requirement DECIMAL(3,2),

                -- Award Details
                awarding_institution VARCHAR(200),
                award_period VARCHAR(50),
                tax_implications TEXT,

                -- Status and Tracking
                aid_status ENUM('applied', 'awarded', 'accepted', 'active', 'completed', 'cancelled', 'defaulted') DEFAULT 'applied',
                acceptance_deadline DATE,
                disbursement_date DATE,

                -- Reporting and Compliance
                reporting_requirements TEXT,
                compliance_deadline DATE,
                last_compliance_check DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (student_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                FOREIGN KEY (application_id) REFERENCES student_applications(id) ON DELETE SET NULL,
                INDEX idx_aid_number (aid_number),
                INDEX idx_student_id (student_id),
                INDEX idx_aid_type (aid_type),
                INDEX idx_aid_status (aid_status)
            );

            CREATE TABLE IF NOT EXISTS research_grants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                grant_number VARCHAR(20) UNIQUE NOT NULL,
                principal_investigator_id INT NOT NULL,

                -- Grant Details
                grant_title VARCHAR(300) NOT NULL,
                grant_description TEXT NOT NULL,
                research_field VARCHAR(100) NOT NULL,
                research_methodology TEXT,

                -- Funding Information
                requested_amount DECIMAL(12,2) NOT NULL,
                approved_amount DECIMAL(12,2) DEFAULT 0,
                funding_agency VARCHAR(200),
                grant_type ENUM('basic_research', 'applied_research', 'clinical_research', 'policy_research', 'other') DEFAULT 'basic_research',

                -- Project Timeline
                start_date DATE,
                end_date DATE,
                reporting_schedule TEXT,

                -- Research Team
                co_investigators TEXT,
                research_assistants TEXT,
                collaborating_institutions TEXT,

                -- Project Details
                research_objectives TEXT,
                expected_outcomes TEXT,
                dissemination_plan TEXT,

                -- Review and Approval
                application_date DATE NOT NULL,
                review_date DATE,
                approval_date DATE,
                grant_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'active', 'completed', 'terminated') DEFAULT 'draft',

                -- Compliance and Ethics
                ethics_approval_required BOOLEAN DEFAULT FALSE,
                ethics_approval_date DATE,
                data_management_plan TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (principal_investigator_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_grant_number (grant_number),
                INDEX idx_principal_investigator_id (principal_investigator_id),
                INDEX idx_research_field (research_field),
                INDEX idx_grant_status (grant_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS student_loans (
                id INT PRIMARY KEY AUTO_INCREMENT,
                loan_number VARCHAR(20) UNIQUE NOT NULL,
                student_id INT NOT NULL,

                -- Loan Details
                loan_type ENUM('federal', 'private', 'institutional', 'international') DEFAULT 'federal',
                loan_category ENUM('subsidized', 'unsubsidized', 'plus', 'consolidation', 'other') DEFAULT 'unsubsidized',

                -- Financial Information
                loan_amount DECIMAL(12,2) NOT NULL,
                interest_rate DECIMAL(5,3),
                loan_term_months INT,
                monthly_payment DECIMAL(8,2),

                -- Disbursement
                disbursement_schedule TEXT,
                first_disbursement_date DATE,
                total_disbursed DECIMAL(12,2) DEFAULT 0,

                -- Repayment
                repayment_start_date DATE,
                repayment_plan ENUM('standard', 'graduated', 'extended', 'income_based', 'forgiveness') DEFAULT 'standard',
                grace_period_months INT DEFAULT 6,

                -- Status and Tracking
                loan_status ENUM('approved', 'disbursing', 'in_repayment', 'deferred', 'forbearance', 'defaulted', 'paid_off') DEFAULT 'approved',
                outstanding_balance DECIMAL(12,2),
                last_payment_date DATE,
                next_payment_date DATE,

                -- Servicing Information
                loan_servicer VARCHAR(200),
                contact_information TEXT,
                online_portal_access TEXT,

                -- Default and Collections
                default_date DATE,
                collection_agency VARCHAR(200),
                rehabilitation_eligible BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (student_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_loan_number (loan_number),
                INDEX idx_student_id (student_id),
                INDEX idx_loan_type (loan_type),
                INDEX idx_loan_status (loan_status)
            );

            CREATE TABLE IF NOT EXISTS academic_scholarships (
                id INT PRIMARY KEY AUTO_INCREMENT,
                scholarship_code VARCHAR(20) UNIQUE NOT NULL,
                scholarship_name VARCHAR(200) NOT NULL,
                scholarship_description TEXT,

                -- Scholarship Details
                scholarship_type ENUM('merit', 'need', 'athletic', 'research', 'leadership', 'diversity', 'other') DEFAULT 'merit',
                awarding_institution VARCHAR(200),
                academic_level ENUM('undergraduate', 'graduate', 'doctoral', 'all') DEFAULT 'undergraduate',

                -- Eligibility Criteria
                minimum_gpa DECIMAL(3,2),
                required_major VARCHAR(100),
                citizenship_requirement VARCHAR(100),
                other_requirements TEXT,

                -- Award Information
                award_amount DECIMAL(10,2),
                number_available INT,
                renewable BOOLEAN DEFAULT FALSE,
                renewal_criteria TEXT,

                -- Application Process
                application_deadline DATE,
                application_requirements TEXT,
                selection_process TEXT,

                -- Status
                scholarship_status ENUM('active', 'inactive', 'full', 'expired') DEFAULT 'active',
                total_awarded INT DEFAULT 0,

                -- Contact Information
                contact_person VARCHAR(100),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_scholarship_code (scholarship_code),
                INDEX idx_scholarship_type (scholarship_type),
                INDEX idx_academic_level (academic_level),
                INDEX idx_scholarship_status (scholarship_status),
                INDEX idx_application_deadline (application_deadline)
            );

            CREATE TABLE IF NOT EXISTS international_student_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_number VARCHAR(20) UNIQUE NOT NULL,
                student_id INT NOT NULL,

                -- Visa and Immigration
                visa_type ENUM('f1', 'j1', 'm1', 'h1b', 'other') NOT NULL,
                visa_status ENUM('applied', 'approved', 'issued', 'expired', 'revoked') DEFAULT 'applied',
                visa_expiry_date DATE,
                sevis_number VARCHAR(20),

                -- Academic Information
                institution_name VARCHAR(200),
                program_level VARCHAR(100),
                expected_graduation_date DATE,
                academic_advisor VARCHAR(100),

                -- Support Services
                orientation_completed BOOLEAN DEFAULT FALSE,
                english_support_needed BOOLEAN DEFAULT FALSE,
                academic_support_services TEXT,
                career_services_access BOOLEAN DEFAULT TRUE,

                -- Health and Insurance
                health_insurance_required BOOLEAN DEFAULT TRUE,
                health_insurance_provider VARCHAR(200),
                medical_clearance_completed BOOLEAN DEFAULT FALSE,

                -- Housing and Transportation
                housing_assistance_needed BOOLEAN DEFAULT FALSE,
                housing_arranged BOOLEAN DEFAULT FALSE,
                transportation_support TEXT,

                -- Cultural Integration
                cultural_orientation_completed BOOLEAN DEFAULT FALSE,
                language_exchange_participation BOOLEAN DEFAULT FALSE,
                international_student_organization TEXT,

                -- Emergency Contacts
                emergency_contact_name VARCHAR(200),
                emergency_contact_relationship VARCHAR(100),
                emergency_contact_phone VARCHAR(20),
                emergency_contact_email VARCHAR(255),

                -- Compliance and Reporting
                compliance_status ENUM('compliant', 'warning', 'probation', 'terminated') DEFAULT 'compliant',
                last_compliance_review DATE,
                next_compliance_review DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (student_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_service_number (service_number),
                INDEX idx_student_id (student_id),
                INDEX idx_visa_type (visa_type),
                INDEX idx_visa_status (visa_status),
                INDEX idx_compliance_status (compliance_status)
            );

            CREATE TABLE IF NOT EXISTS alumni_network (
                id INT PRIMARY KEY AUTO_INCREMENT,
                alumni_id VARCHAR(20) UNIQUE NOT NULL,
                individual_id INT NOT NULL,

                -- Personal Information
                graduation_year INT,
                degree_earned VARCHAR(200),
                major_field VARCHAR(100),
                institution_name VARCHAR(200),

                -- Professional Information
                current_employer VARCHAR(200),
                job_title VARCHAR(100),
                industry VARCHAR(100),
                linkedin_profile VARCHAR(255),

                -- Contact Information
                preferred_email VARCHAR(255),
                phone VARCHAR(20),
                mailing_address TEXT,

                -- Alumni Engagement
                alumni_association_member BOOLEAN DEFAULT FALSE,
                mentoring_program_participant BOOLEAN DEFAULT FALSE,
                volunteer_activities TEXT,
                donations_made DECIMAL(10,2) DEFAULT 0,

                -- Career Services
                career_services_access BOOLEAN DEFAULT TRUE,
                job_postings_access BOOLEAN DEFAULT TRUE,
                networking_events_participation BOOLEAN DEFAULT FALSE,

                -- Status
                alumni_status ENUM('active', 'inactive', 'deceased', 'do_not_contact') DEFAULT 'active',
                last_contact_date DATE,
                next_contact_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (individual_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_alumni_id (alumni_id),
                INDEX idx_individual_id (individual_id),
                INDEX idx_graduation_year (graduation_year),
                INDEX idx_alumni_status (alumni_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup university admissions workflow
        $admissionsWorkflow = [
            'name' => 'University Admissions Process',
            'description' => 'Complete workflow for university admissions and enrollment',
            'steps' => [
                [
                    'name' => 'Application Submission',
                    'type' => 'user_task',
                    'assignee' => 'student',
                    'form' => 'admission_application_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Academic Review',
                    'type' => 'user_task',
                    'assignee' => 'admissions_officer',
                    'form' => 'academic_review_form'
                ],
                [
                    'name' => 'Interview Process',
                    'type' => 'user_task',
                    'assignee' => 'interview_committee',
                    'form' => 'interview_assessment_form'
                ],
                [
                    'name' => 'Financial Aid Review',
                    'type' => 'user_task',
                    'assignee' => 'financial_aid_officer',
                    'form' => 'financial_aid_review_form'
                ],
                [
                    'name' => 'Final Decision',
                    'type' => 'user_task',
                    'assignee' => 'admissions_director',
                    'form' => 'final_decision_form'
                ]
            ]
        ];

        // Setup research grant application workflow
        $researchWorkflow = [
            'name' => 'Research Grant Application Process',
            'description' => 'Complete workflow for research grant applications',
            'steps' => [
                [
                    'name' => 'Grant Proposal',
                    'type' => 'user_task',
                    'assignee' => 'researcher',
                    'form' => 'grant_proposal_form'
                ],
                [
                    'name' => 'Institutional Review',
                    'type' => 'user_task',
                    'assignee' => 'research_office',
                    'form' => 'institutional_review_form'
                ],
                [
                    'name' => 'Peer Review',
                    'type' => 'user_task',
                    'assignee' => 'peer_reviewers',
                    'form' => 'peer_review_form'
                ],
                [
                    'name' => 'Ethics Review',
                    'type' => 'user_task',
                    'assignee' => 'ethics_committee',
                    'form' => 'ethics_review_form'
                ],
                [
                    'name' => 'Funding Decision',
                    'type' => 'user_task',
                    'assignee' => 'funding_committee',
                    'form' => 'funding_decision_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/admissions_workflow.json', json_encode($admissionsWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/research_workflow.json', json_encode($researchWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/applications',
            __DIR__ . '/uploads/transcripts',
            __DIR__ . '/uploads/research_proposals',
            __DIR__ . '/uploads/financial_aid',
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
            'alumni_network',
            'international_student_services',
            'academic_scholarships',
            'student_loans',
            'research_grants',
            'student_applications',
            'academic_programs',
            'university_institutions'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerUniversity($data) {
        try {
            $this->validateUniversityData($data);
            $institutionCode = $this->generateInstitutionCode();

            $sql = "INSERT INTO university_institutions (
                institution_code, institution_name, institution_type,
                accreditation_status, address, contact_phone, contact_email,
                website, total_students, total_faculty, undergraduate_programs,
                graduate_programs, doctoral_programs, national_ranking,
                international_ranking, accreditation_body, last_accreditation_date,
                tuition_range_undergraduate, tuition_range_graduate,
                average_aid_package, endowment_amount, registration_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $institutionId = $this->db->insert($sql, [
                $institutionCode, $data['institution_name'], $data['institution_type'],
                $data['accreditation_status'] ?? null, json_encode($data['address']),
                $data['contact_phone'] ?? null, $data['contact_email'] ?? null,
                $data['website'] ?? null, $data['total_students'] ?? 0,
                $data['total_faculty'] ?? 0, $data['undergraduate_programs'] ?? 0,
                $data['graduate_programs'] ?? 0, $data['doctoral_programs'] ?? 0,
                $data['national_ranking'] ?? null, $data['international_ranking'] ?? null,
                $data['accreditation_body'] ?? null, $data['last_accreditation_date'] ?? null,
                $data['tuition_range_undergraduate'] ?? null, $data['tuition_range_graduate'] ?? null,
                $data['average_aid_package'] ?? null, $data['endowment_amount'] ?? null,
                date('Y-m-d'), $data['created_by']
            ]);

            return [
                'success' => true,
                'institution_id' => $institutionId,
                'institution_code' => $institutionCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createAcademicProgram($data) {
        try {
            $this->validateAcademicProgramData($data);
            $programCode = $this->generateProgramCode();

            $sql = "INSERT INTO academic_programs (
                program_code, institution_id, program_name, program_level,
                program_type, program_description, duration_years, total_credits,
                language_of_instruction, minimum_gpa, required_tests,
                prerequisite_subjects, english_proficiency, tuition_per_year,
                application_fee, program_status, max_enrollment, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['institution_id'], $data['program_name'],
                $data['program_level'], $data['program_type'],
                $data['program_description'] ?? null, $data['duration_years'] ?? null,
                $data['total_credits'] ?? null, $data['language_of_instruction'] ?? 'English',
                $data['minimum_gpa'] ?? null, $data['required_tests'] ?? null,
                $data['prerequisite_subjects'] ?? null, $data['english_proficiency'] ?? null,
                $data['tuition_per_year'] ?? null, $data['application_fee'] ?? 0,
                $data['program_status'] ?? 'active', $data['max_enrollment'] ?? null,
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

    public function submitStudentApplication($data) {
        try {
            $this->validateStudentApplicationData($data);
            $applicationNumber = $this->generateApplicationNumber();

            $sql = "INSERT INTO student_applications (
                application_number, student_id, program_id, application_date,
                high_school_gpa, standardized_test_scores, academic_transcript,
                recommendation_letters, personal_statement, extracurricular_activities,
                work_experience, awards_honors, financial_need_assessment,
                requested_financial_aid, family_income, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $applicationId = $this->db->insert($sql, [
                $applicationNumber, $data['student_id'], $data['program_id'],
                $data['application_date'], $data['high_school_gpa'] ?? null,
                $data['standardized_test_scores'] ?? null, $data['academic_transcript'] ?? null,
                $data['recommendation_letters'] ?? null, $data['personal_statement'] ?? null,
                $data['extracurricular_activities'] ?? null, $data['work_experience'] ?? null,
                $data['awards_honors'] ?? null, $data['financial_need_assessment'] ?? false,
                $data['requested_financial_aid'] ?? null, $data['family_income'] ?? null,
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

    public function applyForFinancialAid($data) {
        try {
            $this->validateFinancialAidData($data);
            $aidNumber = $this->generateAidNumber();

            $sql = "INSERT INTO student_financial_aid (
                aid_number, student_id, application_id, aid_type, aid_category,
                award_amount, disbursement_schedule, eligibility_criteria,
                renewal_requirements, gpa_requirement, awarding_institution,
                award_period, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $aidId = $this->db->insert($sql, [
                $aidNumber, $data['student_id'], $data['application_id'] ?? null,
                $data['aid_type'], $data['aid_category'] ?? 'need_based',
                $data['award_amount'], $data['disbursement_schedule'] ?? null,
                $data['eligibility_criteria'] ?? null, $data['renewal_requirements'] ?? null,
                $data['gpa_requirement'] ?? null, $data['awarding_institution'] ?? null,
                $data['award_period'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'aid_id' => $aidId,
                'aid_number' => $aidNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function applyForResearchGrant($data) {
        try {
            $this->validateResearchGrantData($data);
            $grantNumber = $this->generateResearchGrantNumber();

            $sql = "INSERT INTO research_grants (
                grant_number, principal_investigator_id, grant_title,
                grant_description, research_field, research_methodology,
                requested_amount, funding_agency, grant_type, start_date,
                end_date, co_investigators, research_assistants,
                collaborating_institutions, research_objectives,
                expected_outcomes, dissemination_plan, application_date,
                ethics_approval_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $grantId = $this->db->insert($sql, [
                $grantNumber, $data['principal_investigator_id'], $data['grant_title'],
                $data['grant_description'], $data['research_field'],
                $data['research_methodology'] ?? null, $data['requested_amount'],
                $data['funding_agency'] ?? null, $data['grant_type'] ?? 'basic_research',
                $data['start_date'] ?? null, $data['end_date'] ?? null,
                $data['co_investigators'] ?? null, $data['research_assistants'] ?? null,
                $data['collaborating_institutions'] ?? null, $data['research_objectives'] ?? null,
                $data['expected_outcomes'] ?? null, $data['dissemination_plan'] ?? null,
                $data['application_date'], $data['ethics_approval_required'] ?? false,
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

    public function processStudentLoan($data) {
        try {
            $this->validateStudentLoanData($data);
            $loanNumber = $this->generateLoanNumber();

            $sql = "INSERT INTO student_loans (
                loan_number, student_id, loan_type, loan_category,
                loan_amount, interest_rate, loan_term_months, monthly_payment,
                disbursement_schedule, repayment_start_date, repayment_plan,
                grace_period_months, loan_servicer, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $loanId = $this->db->insert($sql, [
                $loanNumber, $data['student_id'], $data['loan_type'] ?? 'federal',
                $data['loan_category'] ?? 'unsubsidized', $data['loan_amount'],
                $data['interest_rate'] ?? null, $data['loan_term_months'] ?? null,
                $data['monthly_payment'] ?? null, $data['disbursement_schedule'] ?? null,
                $data['repayment_start_date'] ?? null, $data['repayment_plan'] ?? 'standard',
                $data['grace_period_months'] ?? 6, $data['loan_servicer'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'loan_id' => $loanId,
                'loan_number' => $loanNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createScholarship($data) {
        try {
            $this->validateScholarshipData($data);
            $scholarshipCode = $this->generateScholarshipCode();

            $sql = "INSERT INTO academic_scholarships (
                scholarship_code, scholarship_name, scholarship_description,
                scholarship_type, awarding_institution, academic_level,
                minimum_gpa, required_major, award_amount, number_available,
                renewable, application_deadline, application_requirements,
                selection_process, contact_person, contact_email,
                contact_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $scholarshipId = $this->db->insert($sql, [
                $scholarshipCode, $data['scholarship_name'], $data['scholarship_description'] ?? null,
                $data['scholarship_type'] ?? 'merit', $data['awarding_institution'] ?? null,
                $data['academic_level'] ?? 'undergraduate', $data['minimum_gpa'] ?? null,
                $data['required_major'] ?? null, $data['award_amount'],
                $data['number_available'] ?? null, $data['renewable'] ?? false,
                $data['application_deadline'] ?? null, $data['application_requirements'] ?? null,
                $data['selection_process'] ?? null, $data['contact_person'] ?? null,
                $data['contact_email'] ?? null, $data['contact_phone'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'scholarship_id' => $scholarshipId,
                'scholarship_code' => $scholarshipCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerInternationalStudent($data) {
        try {
            $this->validateInternationalStudentData($data);
            $serviceNumber = $this->generateServiceNumber();

            $sql = "INSERT INTO international_student_services (
                service_number, student_id, visa_type, sevis_number,
                institution_name, program_level, expected_graduation_date,
                academic_advisor, orientation_completed, english_support_needed,
                health_insurance_required, medical_clearance_completed,
                housing_assistance_needed, cultural_orientation_completed,
                emergency_contact_name, emergency_contact_relationship,
                emergency_contact_phone, emergency_contact_email, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $serviceId = $this->db->insert($sql, [
                $serviceNumber, $data['student_id'], $data['visa_type'],
                $data['sevis_number'] ?? null, $data['institution_name'] ?? null,
                $data['program_level'] ?? null, $data['expected_graduation_date'] ?? null,
                $data['academic_advisor'] ?? null, $data['orientation_completed'] ?? false,
                $data['english_support_needed'] ?? false, $data['health_insurance_required'] ?? true,
                $data['medical_clearance_completed'] ?? false, $data['housing_assistance_needed'] ?? false,
                $data['cultural_orientation_completed'] ?? false, $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_relationship'] ?? null, $data['emergency_contact_phone'] ?? null,
                $data['emergency_contact_email'] ?? null, $data['created_by']
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

    public function registerAlumni($data) {
        try {
            $this->validateAlumniData($data);
            $alumniId = $this->generateAlumniId();

            $sql = "INSERT INTO alumni_network (
                alumni_id, individual_id, graduation_year, degree_earned,
                major_field, institution_name, current_employer, job_title,
                industry, linkedin_profile, preferred_email, phone,
                mailing_address, alumni_association_member, mentoring_program_participant,
                career_services_access, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $alumniRecordId = $this->db->insert($sql, [
                $alumniId, $data['individual_id'], $data['graduation_year'] ?? null,
                $data['degree_earned'] ?? null, $data['major_field'] ?? null,
                $data['institution_name'] ?? null, $data['current_employer'] ?? null,
                $data['job_title'] ?? null, $data['industry'] ?? null,
                $data['linkedin_profile'] ?? null, $data['preferred_email'] ?? null,
                $data['phone'] ?? null, json_encode($data['mailing_address'] ?? []),
                $data['alumni_association_member'] ?? false, $data['mentoring_program_participant'] ?? false,
                $data['career_services_access'] ?? true, $data['created_by']
            ]);

            return [
                'success' => true,
                'alumni_record_id' => $alumniRecordId,
                'alumni_id' => $alumniId
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getStudentApplication($applicationNumber, $userId) {
        $sql = "SELECT * FROM student_applications WHERE application_number = ?";
        $application = $this->db->fetch($sql, [$applicationNumber]);

        if (!$application) {
            return ['success' => false, 'error' => 'Application not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToApplication($application, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $program = $this->getApplicationProgram($application['program_id']);
        $financialAid = $this->getApplicationFinancialAid($application['id']);

        return [
            'success' => true,
            'application' => $application,
            'program' => $program,
            'financial_aid' => $financialAid
        ];
    }

    public function getUniversityPrograms($institutionId = null, $programLevel = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($institutionId) {
            $where[] = "institution_id = ?";
            $params[] = $institutionId;
        }

        if ($programLevel) {
            $where[] = "program_level = ?";
            $params[] = $programLevel;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM academic_programs{$whereClause} ORDER BY program_name";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    public function getAvailableScholarships($academicLevel = null, $scholarshipType = null) {
        $where = ["scholarship_status = 'active'"];
        $params = [];

        if ($academicLevel) {
            $where[] = "academic_level = ?";
            $params[] = $academicLevel;
        }

        if ($scholarshipType) {
            $where[] = "scholarship_type = ?";
            $params[] = $scholarshipType;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM academic_scholarships{$whereClause} ORDER BY application_deadline";

        $scholarships = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'scholarships' => $scholarships
        ];
    }

    public function getResearchGrants($researchField = null, $status = null) {
        $where = [];
        $params = [];

        if ($researchField) {
            $where[] = "research_field = ?";
            $params[] = $researchField;
        }

        if ($status) {
            $where[] = "grant_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM research_grants{$whereClause} ORDER BY application_date DESC";

        $grants = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'grants' => $grants
        ];
    }

    // Helper Methods
    private function validateUniversityData($data) {
        $required = [
            'institution_name', 'institution_type', 'address', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateAcademicProgramData($data) {
        $required = [
            'institution_id', 'program_name', 'program_level', 'program_type', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateStudentApplicationData($data) {
        $required = [
            'student_id', 'program_id', 'application_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateFinancialAidData($data) {
        $required = [
            'student_id', 'aid_type', 'award_amount', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateResearchGrantData($data) {
        $required = [
            'principal_investigator_id', 'grant_title', 'grant_description',
            'research_field', 'requested_amount', 'application_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateStudentLoanData($data) {
        $required = [
            'student_id', 'loan_amount', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateScholarshipData($data) {
        $required = [
            'scholarship_name', 'award_amount', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateInternationalStudentData($data) {
        $required = [
            'student_id', 'visa_type', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateAlumniData($data) {
        $required = [
            'individual_id', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateInstitutionCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "UNI{$date}{$random}";
    }

    private function generateProgramCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PROG{$date}{$random}";
    }

    private function generateApplicationNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "APP{$date}{$random}";
    }

    private function generateAidNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "AID{$date}{$random}";
    }

    private function generateResearchGrantNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "RES{$date}{$random}";
    }

    private function generateLoanNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "LOAN{$date}{$random}";
    }

    private function generateScholarshipCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "SCH{$date}{$random}";
    }

    private function generateServiceNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ISS{$date}{$random}";
    }

    private function generateAlumniId() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ALUM{$date}{$random}";
    }

    private function hasAccessToApplication($application, $userId) {
        // Check if user is the student or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getApplicationProgram($programId) {
        $sql = "SELECT * FROM academic_programs WHERE id = ?";
        return $this->db->fetch($sql, [$programId]);
    }

    private function getApplicationFinancialAid($applicationId) {
        $sql = "SELECT * FROM student_financial_aid WHERE application_id = ? ORDER BY created_at DESC";
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

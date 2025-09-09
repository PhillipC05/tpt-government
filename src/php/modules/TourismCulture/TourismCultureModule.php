<?php
/**
 * Tourism & Culture Module
 * Handles tourism promotion, cultural heritage, and arts programs
 */

require_once __DIR__ . '/../ServiceModule.php';

class TourismCultureModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Tourism & Culture Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive tourism and cultural services including heritage preservation, tourism promotion, and arts programs',
            'dependencies' => ['IdentityServices', 'SocialServices'],
            'permissions' => [
                'tourism.register' => 'Register tourism businesses',
                'tourism.permits' => 'Apply for tourism permits',
                'culture.heritage' => 'Access cultural heritage services',
                'culture.arts' => 'Access arts and culture programs',
                'tourism.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS tourism_business_registrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                registration_number VARCHAR(20) UNIQUE NOT NULL,
                business_owner_id INT NOT NULL,

                -- Business Information
                business_name VARCHAR(200) NOT NULL,
                business_type ENUM('hotel', 'restaurant', 'tour_operator', 'travel_agency', 'attraction', 'transport', 'guide', 'accommodation', 'other') NOT NULL,
                business_category ENUM('luxury', 'mid_range', 'budget', 'eco_tourism', 'cultural', 'adventure', 'business', 'other') DEFAULT 'mid_range',

                -- Contact Information
                contact_person VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(255) NOT NULL,
                website VARCHAR(255),
                address TEXT NOT NULL,

                -- Location Details
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                city VARCHAR(100) NOT NULL,
                region VARCHAR(100),
                country VARCHAR(100) DEFAULT 'Country',

                -- Business Details
                description TEXT,
                capacity INT,
                price_range ENUM('budget', 'moderate', 'expensive', 'luxury') DEFAULT 'moderate',
                operating_hours TEXT,
                languages_spoken TEXT,

                -- Services Offered
                services_offered TEXT,
                amenities TEXT,
                accessibility_features TEXT,

                -- Registration Details
                registration_date DATE NOT NULL,
                registration_status ENUM('pending', 'approved', 'rejected', 'suspended', 'expired') DEFAULT 'pending',
                approval_date DATE NULL,
                expiry_date DATE,

                -- Financial Information
                registration_fee DECIMAL(8,2) DEFAULT 0,
                annual_fee DECIMAL(8,2) DEFAULT 0,
                payment_status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,
                updated_by INT NULL,

                INDEX idx_registration_number (registration_number),
                INDEX idx_business_owner_id (business_owner_id),
                INDEX idx_business_type (business_type),
                INDEX idx_registration_status (registration_status),
                INDEX idx_city (city),
                INDEX idx_registration_date (registration_date)
            );

            CREATE TABLE IF NOT EXISTS tourism_permits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permit_number VARCHAR(20) UNIQUE NOT NULL,
                business_id INT NOT NULL,

                -- Permit Details
                permit_type ENUM('tour_operator', 'tour_guide', 'accommodation', 'restaurant', 'transport', 'attraction', 'event', 'special_activity') NOT NULL,
                permit_category ENUM('individual', 'business', 'group', 'temporary', 'permanent') DEFAULT 'business',

                -- Application Details
                application_date DATE NOT NULL,
                permit_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'issued', 'expired', 'revoked') DEFAULT 'draft',
                approval_date DATE NULL,
                issuance_date DATE NULL,
                expiry_date DATE,

                -- Permit Conditions
                operating_conditions TEXT,
                restrictions TEXT,
                special_requirements TEXT,

                -- Financial Information
                application_fee DECIMAL(8,2) DEFAULT 0,
                permit_fee DECIMAL(8,2) DEFAULT 0,
                total_fees DECIMAL(10,2) DEFAULT 0,

                -- Review Information
                reviewer_id INT NULL,
                review_notes TEXT,
                denial_reason TEXT,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NOT NULL,

                FOREIGN KEY (business_id) REFERENCES tourism_business_registrations(id) ON DELETE CASCADE,
                INDEX idx_permit_number (permit_number),
                INDEX idx_business_id (business_id),
                INDEX idx_permit_type (permit_type),
                INDEX idx_permit_status (permit_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS cultural_heritage_sites (
                id INT PRIMARY KEY AUTO_INCREMENT,
                site_code VARCHAR(20) UNIQUE NOT NULL,
                site_name VARCHAR(200) NOT NULL,

                -- Site Information
                site_type ENUM('historical', 'archaeological', 'architectural', 'cultural', 'natural', 'religious', 'industrial', 'other') NOT NULL,
                site_category ENUM('national', 'regional', 'local', 'international') DEFAULT 'local',

                -- Location Details
                address TEXT NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                city VARCHAR(100) NOT NULL,
                region VARCHAR(100),
                country VARCHAR(100) DEFAULT 'Country',

                -- Site Description
                description TEXT NOT NULL,
                historical_significance TEXT,
                architectural_style VARCHAR(100),
                construction_date VARCHAR(50),
                architect_designer VARCHAR(200),

                -- Status and Protection
                protection_status ENUM('designated', 'registered', 'proposed', 'not_protected') DEFAULT 'not_protected',
                designation_date DATE,
                protection_level ENUM('strict', 'moderate', 'minimal') DEFAULT 'moderate',

                -- Management
                managing_authority VARCHAR(200),
                contact_person VARCHAR(100),
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),

                -- Access Information
                public_access ENUM('open', 'restricted', 'closed', 'appointment_only') DEFAULT 'open',
                access_restrictions TEXT,
                admission_fee DECIMAL(6,2) DEFAULT 0,

                -- Conservation
                conservation_status ENUM('excellent', 'good', 'fair', 'poor', 'critical') DEFAULT 'good',
                last_assessment_date DATE,
                conservation_needs TEXT,

                -- Documentation
                documentation_status ENUM('complete', 'partial', 'minimal', 'none') DEFAULT 'minimal',
                photographs_available BOOLEAN DEFAULT FALSE,
                historical_records_available BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_site_code (site_code),
                INDEX idx_site_type (site_type),
                INDEX idx_site_category (site_category),
                INDEX idx_city (city),
                INDEX idx_protection_status (protection_status)
            );

            CREATE TABLE IF NOT EXISTS arts_culture_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('arts_education', 'cultural_preservation', 'artist_residency', 'cultural_exchange', 'public_art', 'performing_arts', 'visual_arts', 'literary_arts', 'media_arts', 'other') NOT NULL,
                program_category ENUM('education', 'preservation', 'promotion', 'development', 'community', 'professional') DEFAULT 'community',

                -- Target Audience
                target_audience TEXT,
                age_groups TEXT,
                participant_requirements TEXT,

                -- Program Schedule
                start_date DATE,
                end_date DATE,
                application_deadline DATE,
                program_duration_weeks INT,

                -- Implementation
                delivery_method ENUM('in_person', 'online', 'hybrid') DEFAULT 'in_person',
                location VARCHAR(200),
                instructor_facilitator VARCHAR(200),

                -- Resources and Budget
                required_budget DECIMAL(10,2),
                funding_source VARCHAR(100),
                materials_equipment TEXT,

                -- Program Status
                program_status ENUM('planning', 'active', 'completed', 'cancelled', 'on_hold') DEFAULT 'planning',
                max_participants INT,
                current_participants INT DEFAULT 0,

                -- Evaluation
                success_metrics TEXT,
                evaluation_method TEXT,

                -- Administration
                program_coordinator VARCHAR(200),
                contact_phone VARCHAR(20),
                contact_email VARCHAR(255),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_program_status (program_status),
                INDEX idx_start_date (start_date)
            );

            CREATE TABLE IF NOT EXISTS tourism_events (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_code VARCHAR(20) UNIQUE NOT NULL,
                event_name VARCHAR(200) NOT NULL,
                event_description TEXT,

                -- Event Details
                event_type ENUM('festival', 'cultural', 'sports', 'business', 'conference', 'exhibition', 'concert', 'theater', 'other') NOT NULL,
                event_category ENUM('local', 'regional', 'national', 'international') DEFAULT 'local',

                -- Date and Time
                start_date DATE NOT NULL,
                end_date DATE,
                start_time TIME,
                end_time TIME,
                duration_days INT,

                -- Location
                venue_name VARCHAR(200),
                address TEXT NOT NULL,
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                city VARCHAR(100) NOT NULL,
                region VARCHAR(100),

                -- Capacity and Attendance
                max_capacity INT,
                expected_attendance INT,
                ticket_price DECIMAL(8,2) DEFAULT 0,

                -- Organization
                organizer_name VARCHAR(200) NOT NULL,
                organizer_contact VARCHAR(100),
                organizer_phone VARCHAR(20),
                organizer_email VARCHAR(255),

                -- Event Status
                event_status ENUM('planning', 'approved', 'confirmed', 'cancelled', 'postponed', 'completed') DEFAULT 'planning',
                approval_date DATE,
                approval_authority VARCHAR(100),

                -- Marketing and Promotion
                marketing_budget DECIMAL(8,2),
                target_audience TEXT,
                promotional_materials TEXT,

                -- Logistics
                accommodation_needed BOOLEAN DEFAULT FALSE,
                transportation_needed BOOLEAN DEFAULT FALSE,
                security_requirements TEXT,
                medical_services_needed BOOLEAN DEFAULT FALSE,

                -- Economic Impact
                estimated_economic_impact DECIMAL(12,2),
                expected_visitors INT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_event_code (event_code),
                INDEX idx_event_type (event_type),
                INDEX idx_start_date (start_date),
                INDEX idx_city (city),
                INDEX idx_event_status (event_status)
            );

            CREATE TABLE IF NOT EXISTS cultural_grants_funding (
                id INT PRIMARY KEY AUTO_INCREMENT,
                grant_number VARCHAR(20) UNIQUE NOT NULL,
                applicant_id INT NOT NULL,

                -- Grant Details
                grant_type ENUM('arts_project', 'cultural_preservation', 'heritage_restoration', 'artist_development', 'cultural_education', 'tourism_promotion', 'community_cultural', 'other') NOT NULL,
                grant_category ENUM('individual', 'organization', 'community', 'institution') DEFAULT 'individual',

                -- Application Details
                application_date DATE NOT NULL,
                grant_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'awarded', 'completed', 'cancelled') DEFAULT 'draft',
                submission_deadline DATE,

                -- Project Information
                project_title VARCHAR(200) NOT NULL,
                project_description TEXT NOT NULL,
                project_goals TEXT,
                target_completion_date DATE,

                -- Financial Information
                requested_amount DECIMAL(10,2) NOT NULL,
                approved_amount DECIMAL(10,2) DEFAULT 0,
                funding_period_months INT,

                -- Eligibility and Requirements
                eligibility_criteria TEXT,
                reporting_requirements TEXT,
                matching_funds_required BOOLEAN DEFAULT FALSE,
                matching_funds_amount DECIMAL(10,2),

                -- Review Information
                reviewer_id INT NULL,
                review_date DATE NULL,
                review_notes TEXT,
                approval_rationale TEXT,
                denial_reason TEXT,

                -- Award and Implementation
                award_date DATE NULL,
                implementation_start_date DATE,
                implementation_end_date DATE,

                -- Progress and Reporting
                progress_reports_required INT DEFAULT 0,
                final_report_due DATE,
                evaluation_requirements TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (applicant_id) REFERENCES tourism_business_registrations(id) ON DELETE CASCADE,
                INDEX idx_grant_number (grant_number),
                INDEX idx_applicant_id (applicant_id),
                INDEX idx_grant_type (grant_type),
                INDEX idx_grant_status (grant_status),
                INDEX idx_application_date (application_date)
            );

            CREATE TABLE IF NOT EXISTS tourism_statistics (
                id INT PRIMARY KEY AUTO_INCREMENT,
                statistics_id VARCHAR(20) UNIQUE NOT NULL,

                -- Time Period
                year INT NOT NULL,
                month INT,
                quarter INT,
                period_type ENUM('monthly', 'quarterly', 'annual') DEFAULT 'annual',

                -- Visitor Statistics
                total_visitors INT DEFAULT 0,
                international_visitors INT DEFAULT 0,
                domestic_visitors INT DEFAULT 0,
                day_visitors INT DEFAULT 0,
                overnight_visitors INT DEFAULT 0,

                -- Economic Impact
                total_revenue DECIMAL(15,2) DEFAULT 0,
                accommodation_revenue DECIMAL(12,2) DEFAULT 0,
                food_beverage_revenue DECIMAL(12,2) DEFAULT 0,
                transportation_revenue DECIMAL(12,2) DEFAULT 0,
                entertainment_revenue DECIMAL(12,2) DEFAULT 0,

                -- Origin Statistics
                top_visitor_countries TEXT,
                visitor_demographics TEXT,

                -- Seasonal Data
                peak_season_months TEXT,
                average_length_of_stay DECIMAL(4,1),

                -- Infrastructure Usage
                hotel_occupancy_rate DECIMAL(5,2),
                transportation_usage TEXT,

                -- Data Source
                data_source VARCHAR(100),
                collection_method VARCHAR(100),
                data_quality_rating ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_statistics_id (statistics_id),
                INDEX idx_year (year),
                INDEX idx_month (month),
                INDEX idx_period_type (period_type)
            );

            CREATE TABLE IF NOT EXISTS cultural_education_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                target_group ENUM('schools', 'universities', 'community', 'professionals', 'international', 'all') DEFAULT 'schools',
                program_focus ENUM('heritage_education', 'arts_appreciation', 'cultural_awareness', 'traditional_crafts', 'language_preservation', 'museum_studies', 'other') NOT NULL,

                -- Educational Content
                curriculum_overview TEXT,
                learning_objectives TEXT,
                teaching_methodology TEXT,

                -- Program Structure
                duration_hours DECIMAL(6,2),
                number_of_sessions INT,
                session_format ENUM('lecture', 'workshop', 'field_trip', 'online', 'blended') DEFAULT 'workshop',

                -- Target Audience
                minimum_age INT DEFAULT 5,
                maximum_age INT,
                prerequisite_knowledge TEXT,

                -- Resources
                required_materials TEXT,
                technology_requirements TEXT,
                facility_requirements TEXT,

                -- Program Status
                program_status ENUM('active', 'inactive', 'pilot', 'discontinued') DEFAULT 'active',
                development_status ENUM('concept', 'developed', 'tested', 'implemented') DEFAULT 'developed',

                -- Administration
                program_director VARCHAR(200),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),

                -- Evaluation
                evaluation_metrics TEXT,
                participant_satisfaction DECIMAL(3,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_program_code (program_code),
                INDEX idx_target_group (target_group),
                INDEX idx_program_focus (program_focus),
                INDEX idx_program_status (program_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup tourism business registration workflow
        $registrationWorkflow = [
            'name' => 'Tourism Business Registration Process',
            'description' => 'Complete workflow for tourism business registration and approval',
            'steps' => [
                [
                    'name' => 'Business Application',
                    'type' => 'user_task',
                    'assignee' => 'business_owner',
                    'form' => 'business_registration_form'
                ],
                [
                    'name' => 'Document Verification',
                    'type' => 'service_task',
                    'service' => 'document_verification_service'
                ],
                [
                    'name' => 'Site Inspection',
                    'type' => 'user_task',
                    'assignee' => 'tourism_inspector',
                    'form' => 'site_inspection_form'
                ],
                [
                    'name' => 'Quality Assessment',
                    'type' => 'service_task',
                    'service' => 'quality_assessment_service'
                ],
                [
                    'name' => 'Registration Approval',
                    'type' => 'user_task',
                    'assignee' => 'tourism_officer',
                    'form' => 'registration_approval_form'
                ]
            ]
        ];

        // Setup cultural grant application workflow
        $grantWorkflow = [
            'name' => 'Cultural Grant Application Process',
            'description' => 'Complete workflow for cultural grant applications',
            'steps' => [
                [
                    'name' => 'Grant Application',
                    'type' => 'user_task',
                    'assignee' => 'applicant',
                    'form' => 'grant_application_form'
                ],
                [
                    'name' => 'Eligibility Review',
                    'type' => 'service_task',
                    'service' => 'eligibility_review_service'
                ],
                [
                    'name' => 'Project Assessment',
                    'type' => 'user_task',
                    'assignee' => 'cultural_officer',
                    'form' => 'project_assessment_form'
                ],
                [
                    'name' => 'Panel Review',
                    'type' => 'user_task',
                    'assignee' => 'grant_panel',
                    'form' => 'panel_review_form'
                ],
                [
                    'name' => 'Final Decision',
                    'type' => 'user_task',
                    'assignee' => 'grant_director',
                    'form' => 'final_decision_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/registration_workflow.json', json_encode($registrationWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/grant_workflow.json', json_encode($grantWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/business_docs',
            __DIR__ . '/uploads/heritage_photos',
            __DIR__ . '/uploads/grant_applications',
            __DIR__ . '/uploads/event_materials',
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
            'cultural_education_programs',
            'tourism_statistics',
            'cultural_grants_funding',
            'tourism_events',
            'arts_culture_programs',
            'cultural_heritage_sites',
            'tourism_permits',
            'tourism_business_registrations'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerTourismBusiness($data) {
        try {
            $this->validateBusinessRegistrationData($data);
            $registrationNumber = $this->generateRegistrationNumber();

            $sql = "INSERT INTO tourism_business_registrations (
                registration_number, business_owner_id, business_name, business_type,
                business_category, contact_person, phone, email, website, address,
                latitude, longitude, city, region, description, capacity, price_range,
                operating_hours, languages_spoken, services_offered, amenities,
                accessibility_features, registration_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $registrationId = $this->db->insert($sql, [
                $registrationNumber, $data['business_owner_id'], $data['business_name'],
                $data['business_type'], $data['business_category'] ?? 'mid_range',
                $data['contact_person'], $data['phone'], $data['email'],
                $data['website'] ?? null, json_encode($data['address']),
                $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['city'], $data['region'] ?? null, $data['description'] ?? null,
                $data['capacity'] ?? null, $data['price_range'] ?? 'moderate',
                $data['operating_hours'] ?? null, $data['languages_spoken'] ?? null,
                $data['services_offered'] ?? null, $data['amenities'] ?? null,
                $data['accessibility_features'] ?? null, date('Y-m-d'), $data['created_by']
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

    public function applyForTourismPermit($data) {
        try {
            $this->validatePermitApplicationData($data);
            $permitNumber = $this->generatePermitNumber();

            $sql = "INSERT INTO tourism_permits (
                permit_number, business_id, permit_type, permit_category,
                application_date, operating_conditions, restrictions,
                special_requirements, application_fee, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $permitId = $this->db->insert($sql, [
                $permitNumber, $data['business_id'], $data['permit_type'],
                $data['permit_category'] ?? 'business', $data['application_date'],
                $data['operating_conditions'] ?? null, $data['restrictions'] ?? null,
                $data['special_requirements'] ?? null, $data['application_fee'] ?? 0,
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

    public function registerHeritageSite($data) {
        try {
            $this->validateHeritageSiteData($data);
            $siteCode = $this->generateSiteCode();

            $sql = "INSERT INTO cultural_heritage_sites (
                site_code, site_name, site_type, site_category, address,
                latitude, longitude, city, region, description, historical_significance,
                architectural_style, construction_date, architect_designer,
                protection_status, managing_authority, contact_person,
                contact_phone, contact_email, public_access, access_restrictions,
                admission_fee, conservation_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $siteId = $this->db->insert($sql, [
                $siteCode, $data['site_name'], $data['site_type'],
                $data['site_category'] ?? 'local', json_encode($data['address']),
                $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['city'], $data['region'] ?? null, $data['description'],
                $data['historical_significance'] ?? null, $data['architectural_style'] ?? null,
                $data['construction_date'] ?? null, $data['architect_designer'] ?? null,
                $data['protection_status'] ?? 'not_protected', $data['managing_authority'] ?? null,
                $data['contact_person'] ?? null, $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null, $data['public_access'] ?? 'open',
                $data['access_restrictions'] ?? null, $data['admission_fee'] ?? 0,
                $data['conservation_status'] ?? 'good', $data['created_by']
            ]);

            return [
                'success' => true,
                'site_id' => $siteId,
                'site_code' => $siteCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createArtsProgram($data) {
        try {
            $this->validateArtsProgramData($data);
            $programCode = $this->generateProgramCode();

            $sql = "INSERT INTO arts_culture_programs (
                program_code, program_name, program_description, program_type,
                program_category, target_audience, age_groups, participant_requirements,
                start_date, end_date, application_deadline, program_duration_weeks,
                delivery_method, location, instructor_facilitator, required_budget,
                funding_source, materials_equipment, max_participants,
                program_coordinator, contact_phone, contact_email, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'], $data['program_category'] ?? 'community',
                $data['target_audience'] ?? null, $data['age_groups'] ?? null,
                $data['participant_requirements'] ?? null, $data['start_date'] ?? null,
                $data['end_date'] ?? null, $data['application_deadline'] ?? null,
                $data['program_duration_weeks'] ?? null, $data['delivery_method'] ?? 'in_person',
                $data['location'] ?? null, $data['instructor_facilitator'] ?? null,
                $data['required_budget'] ?? null, $data['funding_source'] ?? null,
                $data['materials_equipment'] ?? null, $data['max_participants'] ?? null,
                $data['program_coordinator'] ?? null, $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null, $data['created_by']
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

    public function registerTourismEvent($data) {
        try {
            $this->validateTourismEventData($data);
            $eventCode = $this->generateEventCode();

            $sql = "INSERT INTO tourism_events (
                event_code, event_name, event_description, event_type,
                event_category, start_date, end_date, start_time, end_time,
                duration_days, venue_name, address, latitude, longitude,
                city, region, max_capacity, expected_attendance, ticket_price,
                organizer_name, organizer_contact, organizer_phone,
                organizer_email, marketing_budget, target_audience,
                estimated_economic_impact, expected_visitors, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $eventId = $this->db->insert($sql, [
                $eventCode, $data['event_name'], $data['event_description'] ?? null,
                $data['event_type'], $data['event_category'] ?? 'local',
                $data['start_date'], $data['end_date'] ?? null, $data['start_time'] ?? null,
                $data['end_time'] ?? null, $data['duration_days'] ?? null,
                $data['venue_name'] ?? null, json_encode($data['address']),
                $data['latitude'] ?? null, $data['longitude'] ?? null,
                $data['city'], $data['region'] ?? null, $data['max_capacity'] ?? null,
                $data['expected_attendance'] ?? null, $data['ticket_price'] ?? 0,
                $data['organizer_name'], $data['organizer_contact'] ?? null,
                $data['organizer_phone'] ?? null, $data['organizer_email'] ?? null,
                $data['marketing_budget'] ?? null, $data['target_audience'] ?? null,
                $data['estimated_economic_impact'] ?? null, $data['expected_visitors'] ?? null,
                $data['created_by']
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

    public function applyForCulturalGrant($data) {
        try {
            $this->validateGrantApplicationData($data);
            $grantNumber = $this->generateGrantNumber();

            $sql = "INSERT INTO cultural_grants_funding (
                grant_number, applicant_id, grant_type, grant_category,
                application_date, project_title, project_description,
                project_goals, target_completion_date, requested_amount,
                eligibility_criteria, reporting_requirements,
                matching_funds_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $grantId = $this->db->insert($sql, [
                $grantNumber, $data['applicant_id'], $data['grant_type'],
                $data['grant_category'] ?? 'individual', $data['application_date'],
                $data['project_title'], $data['project_description'],
                $data['project_goals'] ?? null, $data['target_completion_date'] ?? null,
                $data['requested_amount'], $data['eligibility_criteria'] ?? null,
                $data['reporting_requirements'] ?? null, $data['matching_funds_required'] ?? false,
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

    public function recordTourismStatistics($data) {
        try {
            $this->validateTourismStatisticsData($data);
            $statisticsId = $this->generateStatisticsId();

            $sql = "INSERT INTO tourism_statistics (
                statistics_id, year, month, quarter, period_type,
                total_visitors, international_visitors, domestic_visitors,
                day_visitors, overnight_visitors, total_revenue,
                accommodation_revenue, food_beverage_revenue,
                transportation_revenue, entertainment_revenue,
                top_visitor_countries, visitor_demographics,
                peak_season_months, average_length_of_stay,
                hotel_occupancy_rate, data_source, collection_method,
                data_quality_rating
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $statisticsRecordId = $this->db->insert($sql, [
                $statisticsId, $data['year'], $data['month'] ?? null,
                $data['quarter'] ?? null, $data['period_type'] ?? 'annual',
                $data['total_visitors'] ?? 0, $data['international_visitors'] ?? 0,
                $data['domestic_visitors'] ?? 0, $data['day_visitors'] ?? 0,
                $data['overnight_visitors'] ?? 0, $data['total_revenue'] ?? 0,
                $data['accommodation_revenue'] ?? 0, $data['food_beverage_revenue'] ?? 0,
                $data['transportation_revenue'] ?? 0, $data['entertainment_revenue'] ?? 0,
                json_encode($data['top_visitor_countries'] ?? []),
                json_encode($data['visitor_demographics'] ?? []),
                $data['peak_season_months'] ?? null, $data['average_length_of_stay'] ?? null,
                $data['hotel_occupancy_rate'] ?? null, $data['data_source'] ?? null,
                $data['collection_method'] ?? null, $data['data_quality_rating'] ?? 'good'
            ]);

            return [
                'success' => true,
                'statistics_record_id' => $statisticsRecordId,
                'statistics_id' => $statisticsId
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBusinessRecord($registrationNumber, $userId) {
        $sql = "SELECT * FROM tourism_business_registrations WHERE registration_number = ?";
        $business = $this->db->fetch($sql, [$registrationNumber]);

        if (!$business) {
            return ['success' => false, 'error' => 'Business registration not found'];
        }

        // Check access permissions
        if (!$this->hasAccessToBusinessRecord($business, $userId)) {
            return ['success' => false, 'error' => 'Access denied'];
        }

        // Get related records
        $permits = $this->getBusinessPermits($business['id']);
        $grants = $this->getBusinessGrants($business['id']);

        return [
            'success' => true,
            'business' => $business,
            'permits' => $permits,
            'grants' => $grants
        ];
    }

    public function getHeritageSites($siteType = null, $protectionStatus = null) {
        $where = [];
        $params = [];

        if ($siteType) {
            $where[] = "site_type = ?";
            $params[] = $siteType;
        }

        if ($protectionStatus) {
            $where[] = "protection_status = ?";
            $params[] = $protectionStatus;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM cultural_heritage_sites{$whereClause} ORDER BY site_name";

        $sites = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'sites' => $sites
        ];
    }

    public function getTourismEvents($eventType = null, $startDate = null, $endDate = null) {
        $where = ["event_status != 'cancelled'"];
        $params = [];

        if ($eventType) {
            $where[] = "event_type = ?";
            $params[] = $eventType;
        }

        if ($startDate) {
            $where[] = "start_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $where[] = "end_date <= ?";
            $params[] = $endDate;
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM tourism_events{$whereClause} ORDER BY start_date";

        $events = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'events' => $events
        ];
    }

    public function getArtsPrograms($programType = null, $targetAudience = null) {
        $where = ["program_status = 'active'"];
        $params = [];

        if ($programType) {
            $where[] = "program_type = ?";
            $params[] = $programType;
        }

        if ($targetAudience) {
            $where[] = "target_audience LIKE ?";
            $params[] = "%{$targetAudience}%";
        }

        $whereClause = ' WHERE ' . implode(' AND ', $where);
        $sql = "SELECT * FROM arts_culture_programs{$whereClause} ORDER BY program_name";

        $programs = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'programs' => $programs
        ];
    }

    // Helper Methods
    private function validateBusinessRegistrationData($data) {
        $required = [
            'business_owner_id', 'business_name', 'business_type',
            'contact_person', 'phone', 'email', 'address', 'city', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validatePermitApplicationData($data) {
        $required = ['business_id', 'permit_type', 'application_date', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateHeritageSiteData($data) {
        $required = [
            'site_name', 'site_type', 'address', 'city', 'description', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateArtsProgramData($data) {
        $required = ['program_name', 'program_type', 'created_by'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateTourismEventData($data) {
        $required = [
            'event_name', 'event_type', 'start_date', 'address',
            'city', 'organizer_name', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateGrantApplicationData($data) {
        $required = [
            'applicant_id', 'grant_type', 'application_date',
            'project_title', 'project_description', 'requested_amount', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateTourismStatisticsData($data) {
        $required = ['year', 'period_type'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateRegistrationNumber() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "TOUR{$date}{$random}";
    }

    private function generatePermitNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "TPMT{$date}{$random}";
    }

    private function generateSiteCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        return "HER{$date}{$random}";
    }

    private function generateProgramCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ART{$date}{$random}";
    }

    private function generateEventCode() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "EVT{$date}{$random}";
    }

    private function generateGrantNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "GRANT{$date}{$random}";
    }

    private function generateStatisticsId() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "STAT{$date}{$random}";
    }

    private function hasAccessToBusinessRecord($business, $userId) {
        // Check if user is the business owner or has administrative access
        // This would integrate with the identity services module
        return true; // Simplified for now
    }

    private function getBusinessPermits($businessId) {
        $sql = "SELECT * FROM tourism_permits WHERE business_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$businessId]);
    }

    private function getBusinessGrants($businessId) {
        $sql = "SELECT * FROM cultural_grants_funding WHERE applicant_id = ? ORDER BY application_date DESC";
        return $this->db->fetchAll($sql, [$businessId]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

<?php
/**
 * Libraries & Archives Module
 * Handles public library services and historical archives management
 */

require_once __DIR__ . '/../ServiceModule.php';

class LibrariesArchivesModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Libraries & Archives Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive library and archival services including public library management, historical document preservation, research access, and digital collections',
            'dependencies' => ['IdentityServices', 'RecordsManagement', 'EducationServices'],
            'permissions' => [
                'library.access' => 'Access library services',
                'archives.research' => 'Access archival research',
                'digital.collections' => 'Access digital collections',
                'library.admin' => 'Administrative functions'
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
            CREATE TABLE IF NOT EXISTS library_branches (
                id INT PRIMARY KEY AUTO_INCREMENT,
                branch_code VARCHAR(20) UNIQUE NOT NULL,
                branch_name VARCHAR(200) NOT NULL,
                branch_type ENUM('central', 'branch', 'mobile', 'specialized', 'community') DEFAULT 'branch',

                -- Location and Contact
                address TEXT NOT NULL,
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100),
                postal_code VARCHAR(20),
                country VARCHAR(100) DEFAULT 'Domestic',
                phone VARCHAR(20),
                email VARCHAR(255),
                website VARCHAR(255),

                -- Operating Hours
                monday_hours VARCHAR(50),
                tuesday_hours VARCHAR(50),
                wednesday_hours VARCHAR(50),
                thursday_hours VARCHAR(50),
                friday_hours VARCHAR(50),
                saturday_hours VARCHAR(50),
                sunday_hours VARCHAR(50),
                holiday_hours VARCHAR(50),

                -- Facilities and Services
                total_area_sqft INT,
                seating_capacity INT,
                computer_stations INT,
                meeting_rooms INT,
                study_rooms INT,
                parking_spaces INT,

                -- Collections
                total_books INT DEFAULT 0,
                total_periodicals INT DEFAULT 0,
                total_media INT DEFAULT 0,
                total_digital_items INT DEFAULT 0,
                special_collections TEXT,

                -- Services Offered
                wifi_available BOOLEAN DEFAULT TRUE,
                printing_services BOOLEAN DEFAULT TRUE,
                computer_access BOOLEAN DEFAULT TRUE,
                interlibrary_loan BOOLEAN DEFAULT TRUE,
                reference_services BOOLEAN DEFAULT TRUE,
                children_programs BOOLEAN DEFAULT TRUE,
                senior_programs BOOLEAN DEFAULT TRUE,

                -- Status
                branch_status ENUM('active', 'inactive', 'under_renovation', 'temporarily_closed') DEFAULT 'active',
                opening_date DATE,
                last_renovation_date DATE,

                -- Contact Person
                branch_manager VARCHAR(100),
                manager_email VARCHAR(255),
                manager_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_branch_code (branch_code),
                INDEX idx_city (city),
                INDEX idx_branch_status (branch_status)
            );

            CREATE TABLE IF NOT EXISTS library_memberships (
                id INT PRIMARY KEY AUTO_INCREMENT,
                membership_number VARCHAR(20) UNIQUE NOT NULL,
                user_id INT NOT NULL,

                -- Personal Information
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                date_of_birth DATE,
                email VARCHAR(255),
                phone VARCHAR(20),
                address TEXT,

                -- Membership Details
                membership_type ENUM('individual', 'family', 'student', 'senior', 'community', 'institutional') DEFAULT 'individual',
                membership_status ENUM('active', 'inactive', 'suspended', 'expired', 'cancelled') DEFAULT 'active',

                -- Membership Period
                registration_date DATE NOT NULL,
                expiry_date DATE,
                renewal_date DATE,
                grace_period_days INT DEFAULT 30,

                -- Usage Statistics
                books_borrowed INT DEFAULT 0,
                books_returned INT DEFAULT 0,
                overdue_books INT DEFAULT 0,
                total_fines DECIMAL(6,2) DEFAULT 0,
                last_visit_date DATE,

                -- Preferences
                preferred_branch_id INT,
                preferred_languages TEXT,
                special_needs TEXT,
                communication_preferences TEXT,

                -- Family Members (for family memberships)
                family_members TEXT,
                dependent_count INT DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (user_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                FOREIGN KEY (preferred_branch_id) REFERENCES library_branches(id) ON DELETE SET NULL,
                INDEX idx_membership_number (membership_number),
                INDEX idx_user_id (user_id),
                INDEX idx_membership_type (membership_type),
                INDEX idx_membership_status (membership_status),
                INDEX idx_expiry_date (expiry_date)
            );

            CREATE TABLE IF NOT EXISTS library_catalog (
                id INT PRIMARY KEY AUTO_INCREMENT,
                catalog_number VARCHAR(20) UNIQUE NOT NULL,
                isbn VARCHAR(20),
                title VARCHAR(300) NOT NULL,
                subtitle VARCHAR(300),

                -- Author and Publication
                author VARCHAR(200),
                additional_authors TEXT,
                publisher VARCHAR(200),
                publication_year INT,
                edition VARCHAR(50),
                volume VARCHAR(50),

                -- Classification
                dewey_decimal VARCHAR(20),
                library_of_congress VARCHAR(20),
                subject_headings TEXT,
                keywords TEXT,

                -- Physical Description
                pages INT,
                height_cm DECIMAL(5,2),
                width_cm DECIMAL(5,2),
                weight_grams INT,
                binding_type VARCHAR(50),

                -- Content Details
                language VARCHAR(50) DEFAULT 'English',
                summary TEXT,
                table_of_contents TEXT,
                reviews_ratings TEXT,

                -- Acquisition
                acquisition_date DATE,
                acquisition_source VARCHAR(200),
                acquisition_cost DECIMAL(8,2),
                acquisition_method ENUM('purchase', 'donation', 'gift', 'exchange', 'legal_deposit') DEFAULT 'purchase',

                -- Availability
                total_copies INT DEFAULT 1,
                available_copies INT DEFAULT 1,
                on_order_copies INT DEFAULT 0,
                reference_only BOOLEAN DEFAULT FALSE,
                circulating BOOLEAN DEFAULT TRUE,

                -- Condition and Preservation
                condition_rating ENUM('excellent', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good',
                preservation_notes TEXT,
                last_condition_check DATE,

                -- Usage Statistics
                total_checkouts INT DEFAULT 0,
                current_checkouts INT DEFAULT 0,
                total_reservations INT DEFAULT 0,
                popularity_score DECIMAL(5,2) DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_catalog_number (catalog_number),
                INDEX idx_isbn (isbn),
                INDEX idx_title (title(50)),
                INDEX idx_author (author(50)),
                INDEX idx_dewey_decimal (dewey_decimal),
                INDEX idx_subject_headings (subject_headings(50))
            );

            CREATE TABLE IF NOT EXISTS library_checkouts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                checkout_number VARCHAR(20) UNIQUE NOT NULL,
                membership_id INT NOT NULL,
                catalog_id INT NOT NULL,

                -- Checkout Details
                checkout_date DATE NOT NULL,
                due_date DATE NOT NULL,
                return_date DATE,
                checkout_duration_days INT DEFAULT 14,

                -- Item Information
                item_copy_number VARCHAR(10),
                item_condition_at_checkout VARCHAR(100),
                item_condition_at_return VARCHAR(100),

                -- Renewal Information
                renewal_count INT DEFAULT 0,
                max_renewals INT DEFAULT 3,
                last_renewal_date DATE,

                -- Status
                checkout_status ENUM('active', 'returned', 'overdue', 'lost', 'damaged', 'renewed') DEFAULT 'active',
                overdue_days INT DEFAULT 0,
                fine_amount DECIMAL(6,2) DEFAULT 0,

                -- Return Details
                return_branch_id INT,
                returned_by VARCHAR(100),
                return_notes TEXT,

                -- Notifications
                due_date_reminder_sent BOOLEAN DEFAULT FALSE,
                overdue_notice_sent BOOLEAN DEFAULT FALSE,
                final_notice_sent BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (membership_id) REFERENCES library_memberships(id) ON DELETE CASCADE,
                FOREIGN KEY (catalog_id) REFERENCES library_catalog(id) ON DELETE CASCADE,
                FOREIGN KEY (return_branch_id) REFERENCES library_branches(id) ON DELETE SET NULL,
                INDEX idx_checkout_number (checkout_number),
                INDEX idx_membership_id (membership_id),
                INDEX idx_catalog_id (catalog_id),
                INDEX idx_checkout_date (checkout_date),
                INDEX idx_due_date (due_date),
                INDEX idx_checkout_status (checkout_status)
            );

            CREATE TABLE IF NOT EXISTS archival_collections (
                id INT PRIMARY KEY AUTO_INCREMENT,
                collection_code VARCHAR(20) UNIQUE NOT NULL,
                collection_title VARCHAR(300) NOT NULL,
                collection_description TEXT,

                -- Collection Details
                collection_type ENUM('personal_papers', 'organizational_records', 'government_records', 'photographs', 'audio_visual', 'artifacts', 'digital', 'other') DEFAULT 'personal_papers',
                creator_name VARCHAR(200),
                creator_dates VARCHAR(50),
                creator_biography TEXT,

                -- Dates and Scope
                date_range_start DATE,
                date_range_end DATE,
                geographic_scope TEXT,
                subject_scope TEXT,

                -- Physical Description
                total_boxes INT DEFAULT 0,
                total_folders INT DEFAULT 0,
                total_items INT DEFAULT 0,
                linear_feet DECIMAL(6,2),
                storage_location VARCHAR(200),

                -- Access and Use
                access_restrictions TEXT,
                use_restrictions TEXT,
                copyright_status VARCHAR(200),
                finding_aid_available BOOLEAN DEFAULT FALSE,

                -- Acquisition
                acquisition_date DATE,
                acquisition_method ENUM('donation', 'transfer', 'purchase', 'bequest', 'deposit') DEFAULT 'donation',
                donor_name VARCHAR(200),
                donor_contact VARCHAR(255),

                -- Processing Status
                processing_status ENUM('unprocessed', 'partially_processed', 'fully_processed', 'digitized') DEFAULT 'unprocessed',
                processing_priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
                processing_notes TEXT,

                -- Digital Access
                digital_surrogates BOOLEAN DEFAULT FALSE,
                online_exhibit BOOLEAN DEFAULT FALSE,
                digital_collection_url VARCHAR(500),

                -- Research Value
                research_significance TEXT,
                related_collections TEXT,
                citations TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_collection_code (collection_code),
                INDEX idx_collection_title (collection_title(50)),
                INDEX idx_creator_name (creator_name(50)),
                INDEX idx_collection_type (collection_type),
                INDEX idx_processing_status (processing_status)
            );

            CREATE TABLE IF NOT EXISTS archival_records (
                id INT PRIMARY KEY AUTO_INCREMENT,
                record_number VARCHAR(20) UNIQUE NOT NULL,
                collection_id INT NOT NULL,

                -- Record Details
                record_title VARCHAR(300) NOT NULL,
                record_description TEXT,
                record_type ENUM('letter', 'document', 'photograph', 'audio', 'video', 'artifact', 'digital_file', 'other') DEFAULT 'document',

                -- Dates
                creation_date DATE,
                record_date DATE,
                record_date_note TEXT,

                -- Physical Description
                box_number VARCHAR(20),
                folder_number VARCHAR(20),
                item_number VARCHAR(10),
                physical_description TEXT,

                -- Content Details
                subject_matter TEXT,
                key_figures TEXT,
                geographic_locations TEXT,
                keywords TEXT,

                -- Access and Use
                access_level ENUM('open', 'restricted', 'closed') DEFAULT 'open',
                access_restrictions TEXT,
                reproduction_allowed BOOLEAN DEFAULT TRUE,
                reproduction_fee DECIMAL(6,2),

                -- Digital Access
                digital_copy_available BOOLEAN DEFAULT FALSE,
                digital_copy_format VARCHAR(50),
                digital_copy_url VARCHAR(500),

                -- Research and Citations
                research_notes TEXT,
                citation_count INT DEFAULT 0,
                last_researched DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (collection_id) REFERENCES archival_collections(id) ON DELETE CASCADE,
                INDEX idx_record_number (record_number),
                INDEX idx_collection_id (collection_id),
                INDEX idx_record_title (record_title(50)),
                INDEX idx_record_type (record_type),
                INDEX idx_access_level (access_level),
                INDEX idx_creation_date (creation_date)
            );

            CREATE TABLE IF NOT EXISTS research_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                request_number VARCHAR(20) UNIQUE NOT NULL,
                researcher_id INT NOT NULL,

                -- Researcher Information
                researcher_name VARCHAR(200) NOT NULL,
                researcher_institution VARCHAR(200),
                researcher_email VARCHAR(255),
                researcher_phone VARCHAR(20),
                researcher_address TEXT,

                -- Research Details
                research_topic VARCHAR(300) NOT NULL,
                research_purpose TEXT,
                research_methodology TEXT,
                expected_outcomes TEXT,

                -- Materials Requested
                collections_requested TEXT,
                records_requested TEXT,
                time_period_requested TEXT,
                special_materials TEXT,

                -- Research Schedule
                preferred_visit_dates TEXT,
                estimated_research_time VARCHAR(100),
                research_assistants TEXT,

                -- Access Requirements
                access_level_requested ENUM('reading_room_only', 'photocopying', 'digital_access', 'publication_rights') DEFAULT 'reading_room_only',
                special_access_needs TEXT,

                -- Request Status
                request_status ENUM('submitted', 'under_review', 'approved', 'partially_approved', 'denied', 'completed', 'cancelled') DEFAULT 'submitted',
                submission_date DATE NOT NULL,
                review_date DATE,
                approval_date DATE,

                -- Review Information
                reviewer_id INT,
                review_notes TEXT,
                approval_conditions TEXT,

                -- Usage Tracking
                visit_dates TEXT,
                materials_consulted TEXT,
                research_notes TEXT,
                publications_resulting TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (researcher_id) REFERENCES indigenous_registrations(id) ON DELETE CASCADE,
                INDEX idx_request_number (request_number),
                INDEX idx_researcher_id (researcher_id),
                INDEX idx_request_status (request_status),
                INDEX idx_submission_date (submission_date)
            );

            CREATE TABLE IF NOT EXISTS digital_collections (
                id INT PRIMARY KEY AUTO_INCREMENT,
                digital_id VARCHAR(20) UNIQUE NOT NULL,
                title VARCHAR(300) NOT NULL,
                description TEXT,

                -- Digital Content
                content_type ENUM('text', 'image', 'audio', 'video', 'mixed', 'dataset', 'software') DEFAULT 'text',
                file_format VARCHAR(50),
                file_size_bytes INT,
                original_filename VARCHAR(255),

                -- Metadata
                creator VARCHAR(200),
                creation_date DATE,
                subject TEXT,
                keywords TEXT,
                language VARCHAR(50) DEFAULT 'English',

                -- Rights and Access
                copyright_status VARCHAR(200),
                access_level ENUM('public', 'registered_users', 'researchers_only', 'restricted') DEFAULT 'public',
                usage_restrictions TEXT,
                attribution_requirements TEXT,

                -- Digital Preservation
                preservation_status ENUM('not_preserved', 'preserved', 'migrated', 'at_risk') DEFAULT 'not_preserved',
                preservation_date DATE,
                preservation_notes TEXT,
                backup_locations TEXT,

                -- Usage Statistics
                view_count INT DEFAULT 0,
                download_count INT DEFAULT 0,
                last_accessed DATE,

                -- Related Physical Items
                physical_equivalent_id INT,
                collection_id INT,

                -- Digital Management
                storage_location VARCHAR(500),
                checksum VARCHAR(128),
                migration_history TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (collection_id) REFERENCES archival_collections(id) ON DELETE SET NULL,
                INDEX idx_digital_id (digital_id),
                INDEX idx_title (title(50)),
                INDEX idx_content_type (content_type),
                INDEX idx_access_level (access_level),
                INDEX idx_preservation_status (preservation_status)
            );

            CREATE TABLE IF NOT EXISTS library_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                program_code VARCHAR(20) UNIQUE NOT NULL,
                program_name VARCHAR(200) NOT NULL,
                program_description TEXT,

                -- Program Details
                program_type ENUM('story_hour', 'book_club', 'workshop', 'lecture', 'exhibit', 'cultural_event', 'educational', 'community', 'other') DEFAULT 'educational',
                target_audience ENUM('children', 'teens', 'adults', 'seniors', 'families', 'all') DEFAULT 'all',

                -- Schedule and Location
                branch_id INT NOT NULL,
                program_date DATE,
                start_time TIME,
                end_time TIME,
                recurring BOOLEAN DEFAULT FALSE,
                recurrence_pattern VARCHAR(100),

                -- Capacity and Registration
                max_participants INT,
                min_participants INT DEFAULT 1,
                registration_required BOOLEAN DEFAULT FALSE,
                registration_deadline DATE,

                -- Resources and Materials
                required_materials TEXT,
                presenter_facilitator VARCHAR(200),
                partner_organizations TEXT,

                -- Program Content
                learning_objectives TEXT,
                program_outline TEXT,
                preparation_required TEXT,

                -- Status and Tracking
                program_status ENUM('planned', 'confirmed', 'cancelled', 'completed', 'postponed') DEFAULT 'planned',
                actual_participants INT DEFAULT 0,
                participant_feedback TEXT,

                -- Evaluation
                success_metrics TEXT,
                improvement_suggestions TEXT,
                repeat_program BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (branch_id) REFERENCES library_branches(id) ON DELETE CASCADE,
                INDEX idx_program_code (program_code),
                INDEX idx_program_type (program_type),
                INDEX idx_target_audience (target_audience),
                INDEX idx_program_date (program_date),
                INDEX idx_program_status (program_status)
            );

            CREATE TABLE IF NOT EXISTS interlibrary_loans (
                id INT PRIMARY KEY AUTO_INCREMENT,
                loan_number VARCHAR(20) UNIQUE NOT NULL,
                requesting_library_id INT NOT NULL,
                lending_library_id INT,

                -- Item Information
                item_title VARCHAR(300) NOT NULL,
                item_author VARCHAR(200),
                isbn VARCHAR(20),
                item_type ENUM('book', 'periodical', 'media', 'manuscript', 'other') DEFAULT 'book',

                -- Request Details
                request_date DATE NOT NULL,
                needed_by_date DATE,
                requester_name VARCHAR(200),
                requester_email VARCHAR(255),
                requester_institution VARCHAR(200),

                -- Loan Status
                loan_status ENUM('requested', 'approved', 'shipped', 'received', 'returned', 'overdue', 'cancelled', 'lost') DEFAULT 'requested',
                approval_date DATE,
                ship_date DATE,
                receive_date DATE,
                return_date DATE,
                due_date DATE,

                -- Shipping Information
                shipping_method VARCHAR(100),
                tracking_number VARCHAR(100),
                shipping_cost DECIMAL(6,2),

                -- Conditions
                loan_conditions TEXT,
                reproduction_allowed BOOLEAN DEFAULT FALSE,
                renewal_allowed BOOLEAN DEFAULT FALSE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (requesting_library_id) REFERENCES library_branches(id) ON DELETE CASCADE,
                FOREIGN KEY (lending_library_id) REFERENCES library_branches(id) ON DELETE SET NULL,
                INDEX idx_loan_number (loan_number),
                INDEX idx_requesting_library_id (requesting_library_id),
                INDEX idx_lending_library_id (lending_library_id),
                INDEX idx_loan_status (loan_status),
                INDEX idx_request_date (request_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup library membership workflow
        $membershipWorkflow = [
            'name' => 'Library Membership Registration Process',
            'description' => 'Complete workflow for library membership registration and management',
            'steps' => [
                [
                    'name' => 'Membership Application',
                    'type' => 'user_task',
                    'assignee' => 'patron',
                    'form' => 'membership_application_form'
                ],
                [
                    'name' => 'Identity Verification',
                    'type' => 'service_task',
                    'service' => 'identity_verification_service'
                ],
                [
                    'name' => 'Application Review',
                    'type' => 'user_task',
                    'assignee' => 'library_staff',
                    'form' => 'membership_review_form'
                ],
                [
                    'name' => 'Membership Approval',
                    'type' => 'user_task',
                    'assignee' => 'branch_manager',
                    'form' => 'membership_approval_form'
                ],
                [
                    'name' => 'Card Issuance',
                    'type' => 'service_task',
                    'service' => 'card_issuance_service'
                ]
            ]
        ];

        // Setup archival research request workflow
        $researchWorkflow = [
            'name' => 'Archival Research Request Process',
            'description' => 'Complete workflow for archival research access requests',
            'steps' => [
                [
                    'name' => 'Research Request Submission',
                    'type' => 'user_task',
                    'assignee' => 'researcher',
                    'form' => 'research_request_form'
                ],
                [
                    'name' => 'Request Review',
                    'type' => 'user_task',
                    'assignee' => 'archivist',
                    'form' => 'request_review_form'
                ],
                [
                    'name' => 'Access Determination',
                    'type' => 'user_task',
                    'assignee' => 'archives_director',
                    'form' => 'access_determination_form'
                ],
                [
                    'name' => 'Research Appointment',
                    'type' => 'user_task',
                    'assignee' => 'reading_room_staff',
                    'form' => 'appointment_scheduling_form'
                ],
                [
                    'name' => 'Research Session',
                    'type' => 'user_task',
                    'assignee' => 'researcher',
                    'form' => 'research_session_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/membership_workflow.json', json_encode($membershipWorkflow, JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/config/research_workflow.json', json_encode($researchWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/membership_applications',
            __DIR__ . '/uploads/catalog_images',
            __DIR__ . '/uploads/archival_documents',
            __DIR__ . '/uploads/digital_collections',
            __DIR__ . '/uploads/research_requests',
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
            'interlibrary_loans',
            'library_programs',
            'digital_collections',
            'research_requests',
            'archival_records',
            'archival_collections',
            'library_checkouts',
            'library_catalog',
            'library_memberships',
            'library_branches'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function registerLibraryBranch($data) {
        try {
            $this->validateBranchData($data);
            $branchCode = $this->generateBranchCode();

            $sql = "INSERT INTO library_branches (
                branch_code, branch_name, branch_type, address, city,
                state_province, postal_code, country, phone, email, website,
                monday_hours, tuesday_hours, wednesday_hours, thursday_hours,
                friday_hours, saturday_hours, sunday_hours, holiday_hours,
                total_area_sqft, seating_capacity, computer_stations,
                meeting_rooms, study_rooms, parking_spaces, total_books,
                total_periodicals, total_media, total_digital_items,
                special_collections, wifi_available, printing_services,
                computer_access, interlibrary_loan, reference_services,
                children_programs, senior_programs, branch_status,
                opening_date, branch_manager, manager_email, manager_phone,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $branchId = $this->db->insert($sql, [
                $branchCode, $data['branch_name'], $data['branch_type'] ?? 'branch',
                json_encode($data['address']), $data['city'], $data['state_province'] ?? null,
                $data['postal_code'] ?? null, $data['country'] ?? 'Domestic',
                $data['phone'] ?? null, $data['email'] ?? null, $data['website'] ?? null,
                $data['monday_hours'] ?? null, $data['tuesday_hours'] ?? null,
                $data['wednesday_hours'] ?? null, $data['thursday_hours'] ?? null,
                $data['friday_hours'] ?? null, $data['saturday_hours'] ?? null,
                $data['sunday_hours'] ?? null, $data['holiday_hours'] ?? null,
                $data['total_area_sqft'] ?? null, $data['seating_capacity'] ?? null,
                $data['computer_stations'] ?? null, $data['meeting_rooms'] ?? null,
                $data['study_rooms'] ?? null, $data['parking_spaces'] ?? null,
                $data['total_books'] ?? 0, $data['total_periodicals'] ?? 0,
                $data['total_media'] ?? 0, $data['total_digital_items'] ?? 0,
                $data['special_collections'] ?? null, $data['wifi_available'] ?? true,
                $data['printing_services'] ?? true, $data['computer_access'] ?? true,
                $data['interlibrary_loan'] ?? true, $data['reference_services'] ?? true,
                $data['children_programs'] ?? true, $data['senior_programs'] ?? true,
                $data['branch_status'] ?? 'active', $data['opening_date'] ?? null,
                $data['branch_manager'] ?? null, $data['manager_email'] ?? null,
                $data['manager_phone'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'branch_id' => $branchId,
                'branch_code' => $branchCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function registerLibraryMembership($data) {
        try {
            $this->validateMembershipData($data);
            $membershipNumber = $this->generateMembershipNumber();

            $sql = "INSERT INTO library_memberships (
                membership_number, user_id, first_name, last_name,
                date_of_birth, email, phone, address, membership_type,
                membership_status, registration_date, expiry_date,
                preferred_branch_id, preferred_languages, special_needs,
                communication_preferences, family_members, dependent_count,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $membershipId = $this->db->insert($sql, [
                $membershipNumber, $data['user_id'], $data['first_name'],
                $data['last_name'], $data['date_of_birth'] ?? null,
                $data['email'] ?? null, $data['phone'] ?? null,
                json_encode($data['address'] ?? []), $data['membership_type'] ?? 'individual',
                $data['membership_status'] ?? 'active', $data['registration_date'],
                $data['expiry_date'] ?? null, $data['preferred_branch_id'] ?? null,
                $data['preferred_languages'] ?? null, $data['special_needs'] ?? null,
                $data['communication_preferences'] ?? null, $data['family_members'] ?? null,
                $data['dependent_count'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'membership_id' => $membershipId,
                'membership_number' => $membershipNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function addCatalogItem($data) {
        try {
            $this->validateCatalogData($data);
            $catalogNumber = $this->generateCatalogNumber();

            $sql = "INSERT INTO library_catalog (
                catalog_number, isbn, title, subtitle, author,
                additional_authors, publisher, publication_year, edition,
                volume, dewey_decimal, library_of_congress, subject_headings,
                keywords, pages, height_cm, width_cm, weight_grams,
                binding_type, language, summary, table_of_contents,
                reviews_ratings, acquisition_date, acquisition_source,
                acquisition_cost, acquisition_method, total_copies,
                available_copies, reference_only, circulating,
                condition_rating, preservation_notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $catalogId = $this->db->insert($sql, [
                $catalogNumber, $data['isbn'] ?? null, $data['title'],
                $data['subtitle'] ?? null, $data['author'] ?? null,
                $data['additional_authors'] ?? null, $data['publisher'] ?? null,
                $data['publication_year'] ?? null, $data['edition'] ?? null,
                $data['volume'] ?? null, $data['dewey_decimal'] ?? null,
                $data['library_of_congress'] ?? null, $data['subject_headings'] ?? null,
                $data['keywords'] ?? null, $data['pages'] ?? null,
                $data['height_cm'] ?? null, $data['width_cm'] ?? null,
                $data['weight_grams'] ?? null, $data['binding_type'] ?? null,
                $data['language'] ?? 'English', $data['summary'] ?? null,
                $data['table_of_contents'] ?? null, $data['reviews_ratings'] ?? null,
                $data['acquisition_date'] ?? null, $data['acquisition_source'] ?? null,
                $data['acquisition_cost'] ?? null, $data['acquisition_method'] ?? 'purchase',
                $data['total_copies'] ?? 1, $data['available_copies'] ?? 1,
                $data['reference_only'] ?? false, $data['circulating'] ?? true,
                $data['condition_rating'] ?? 'good', $data['preservation_notes'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'catalog_id' => $catalogId,
                'catalog_number' => $catalogNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkoutItem($data) {
        try {
            $this->validateCheckoutData($data);
            $checkoutNumber = $this->generateCheckoutNumber();

            // Check if item is available
            $availability = $this->checkItemAvailability($data['catalog_id']);
            if (!$availability['available']) {
                throw new Exception('Item is not available for checkout');
            }

            $sql = "INSERT INTO library_checkouts (
                checkout_number, membership_id, catalog_id, checkout_date,
                due_date, checkout_duration_days, item_copy_number,
                item_condition_at_checkout, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $checkoutId = $this->db->insert($sql, [
                $checkoutNumber, $data['membership_id'], $data['catalog_id'],
                $data['checkout_date'], $data['due_date'],
                $data['checkout_duration_days'] ?? 14, $data['item_copy_number'] ?? null,
                $data['item_condition_at_checkout'] ?? null, $data['created_by']
            ]);

            // Update catalog availability
            $this->updateCatalogAvailability($data['catalog_id'], -1);

            return [
                'success' => true,
                'checkout_id' => $checkoutId,
                'checkout_number' => $checkoutNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function returnItem($data) {
        try {
            $this->validateReturnData($data);

            $sql = "UPDATE library_checkouts SET
                return_date = ?, return_branch_id = ?, returned_by = ?,
                return_notes = ?, item_condition_at_return = ?,
                checkout_status = 'returned'
                WHERE checkout_number = ?";

            $this->db->query($sql, [
                $data['return_date'], $data['return_branch_id'] ?? null,
                $data['returned_by'] ?? null, $data['return_notes'] ?? null,
                $data['item_condition_at_return'] ?? null, $data['checkout_number']
            ]);

            // Update catalog availability
            $checkout = $this->getCheckoutByNumber($data['checkout_number']);
            $this->updateCatalogAvailability($checkout['catalog_id'], 1);

            return [
                'success' => true,
                'message' => 'Item returned successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createArchivalCollection($data) {
        try {
            $this->validateCollectionData($data);
            $collectionCode = $this->generateCollectionCode();

            $sql = "INSERT INTO archival_collections (
                collection_code, collection_title, collection_description,
                collection_type, creator_name, creator_dates, creator_biography,
                date_range_start, date_range_end, geographic_scope,
                subject_scope, total_boxes, total_folders, total_items,
                linear_feet, storage_location, access_restrictions,
                use_restrictions, copyright_status, finding_aid_available,
                acquisition_date, acquisition_method, donor_name,
                donor_contact, processing_status, processing_priority,
                processing_notes, digital_surrogates, online_exhibit,
                digital_collection_url, research_significance,
                related_collections, citations, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $collectionId = $this->db->insert($sql, [
                $collectionCode, $data['collection_title'], $data['collection_description'] ?? null,
                $data['collection_type'] ?? 'personal_papers', $data['creator_name'] ?? null,
                $data['creator_dates'] ?? null, $data['creator_biography'] ?? null,
                $data['date_range_start'] ?? null, $data['date_range_end'] ?? null,
                $data['geographic_scope'] ?? null, $data['subject_scope'] ?? null,
                $data['total_boxes'] ?? 0, $data['total_folders'] ?? 0,
                $data['total_items'] ?? 0, $data['linear_feet'] ?? null,
                $data['storage_location'] ?? null, $data['access_restrictions'] ?? null,
                $data['use_restrictions'] ?? null, $data['copyright_status'] ?? null,
                $data['finding_aid_available'] ?? false, $data['acquisition_date'] ?? null,
                $data['acquisition_method'] ?? 'donation', $data['donor_name'] ?? null,
                $data['donor_contact'] ?? null, $data['processing_status'] ?? 'unprocessed',
                $data['processing_priority'] ?? 'medium', $data['processing_notes'] ?? null,
                $data['digital_surrogates'] ?? false, $data['online_exhibit'] ?? false,
                $data['digital_collection_url'] ?? null, $data['research_significance'] ?? null,
                $data['related_collections'] ?? null, $data['citations'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'collection_id' => $collectionId,
                'collection_code' => $collectionCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitResearchRequest($data) {
        try {
            $this->validateResearchRequestData($data);
            $requestNumber = $this->generateRequestNumber();

            $sql = "INSERT INTO research_requests (
                request_number, researcher_id, researcher_name,
                researcher_institution, researcher_email, researcher_phone,
                researcher_address, research_topic, research_purpose,
                research_methodology, expected_outcomes, collections_requested,
                records_requested, time_period_requested, special_materials,
                preferred_visit_dates, estimated_research_time,
                research_assistants, access_level_requested,
                special_access_needs, submission_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $requestId = $this->db->insert($sql, [
                $requestNumber, $data['researcher_id'], $data['researcher_name'],
                $data['researcher_institution'] ?? null, $data['researcher_email'] ?? null,
                $data['researcher_phone'] ?? null, json_encode($data['researcher_address'] ?? []),
                $data['research_topic'], $data['research_purpose'] ?? null,
                $data['research_methodology'] ?? null, $data['expected_outcomes'] ?? null,
                $data['collections_requested'] ?? null, $data['records_requested'] ?? null,
                $data['time_period_requested'] ?? null, $data['special_materials'] ?? null,
                $data['preferred_visit_dates'] ?? null, $data['estimated_research_time'] ?? null,
                $data['research_assistants'] ?? null, $data['access_level_requested'] ?? 'reading_room_only',
                $data['special_access_needs'] ?? null, $data['submission_date'],
                $data['created_by']
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'request_number' => $requestNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function addDigitalCollection($data) {
        try {
            $this->validateDigitalCollectionData($data);
            $digitalId = $this->generateDigitalId();

            $sql = "INSERT INTO digital_collections (
                digital_id, title, description, content_type, file_format,
                file_size_bytes, original_filename, creator, creation_date,
                subject, keywords, language, copyright_status, access_level,
                usage_restrictions, attribution_requirements,
                preservation_status, preservation_date, preservation_notes,
                backup_locations, physical_equivalent_id, collection_id,
                storage_location, checksum, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $digitalIdResult = $this->db->insert($sql, [
                $digitalId, $data['title'], $data['description'] ?? null,
                $data['content_type'] ?? 'text', $data['file_format'] ?? null,
                $data['file_size_bytes'] ?? null, $data['original_filename'] ?? null,
                $data['creator'] ?? null, $data['creation_date'] ?? null,
                $data['subject'] ?? null, $data['keywords'] ?? null,
                $data['language'] ?? 'English', $data['copyright_status'] ?? null,
                $data['access_level'] ?? 'public', $data['usage_restrictions'] ?? null,
                $data['attribution_requirements'] ?? null, $data['preservation_status'] ?? 'not_preserved',
                $data['preservation_date'] ?? null, $data['preservation_notes'] ?? null,
                $data['backup_locations'] ?? null, $data['physical_equivalent_id'] ?? null,
                $data['collection_id'] ?? null, $data['storage_location'] ?? null,
                $data['checksum'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'digital_id' => $digitalIdResult,
                'digital_id_code' => $digitalId
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createLibraryProgram($data) {
        try {
            $this->validateProgramData($data);
            $programCode = $this->generateProgramCode();

            $sql = "INSERT INTO library_programs (
                program_code, program_name, program_description, program_type,
                target_audience, branch_id, program_date, start_time,
                end_time, recurring, recurrence_pattern, max_participants,
                min_participants, registration_required, registration_deadline,
                required_materials, presenter_facilitator, partner_organizations,
                learning_objectives, program_outline, preparation_required,
                program_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $programId = $this->db->insert($sql, [
                $programCode, $data['program_name'], $data['program_description'] ?? null,
                $data['program_type'] ?? 'educational', $data['target_audience'] ?? 'all',
                $data['branch_id'], $data['program_date'] ?? null,
                $data['start_time'] ?? null, $data['end_time'] ?? null,
                $data['recurring'] ?? false, $data['recurrence_pattern'] ?? null,
                $data['max_participants'] ?? null, $data['min_participants'] ?? 1,
                $data['registration_required'] ?? false, $data['registration_deadline'] ?? null,
                $data['required_materials'] ?? null, $data['presenter_facilitator'] ?? null,
                $data['partner_organizations'] ?? null, $data['learning_objectives'] ?? null,
                $data['program_outline'] ?? null, $data['preparation_required'] ?? null,
                $data['program_status'] ?? 'planned', $data['created_by']
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

    public function requestInterlibraryLoan($data) {
        try {
            $this->validateILLData($data);
            $loanNumber = $this->generateLoanNumber();

            $sql = "INSERT INTO interlibrary_loans (
                loan_number, requesting_library_id, item_title, item_author,
                isbn, item_type, request_date, needed_by_date,
                requester_name, requester_email, requester_institution,
                shipping_method, loan_conditions, reproduction_allowed,
                renewal_allowed, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $loanId = $this->db->insert($sql, [
                $loanNumber, $data['requesting_library_id'], $data['item_title'],
                $data['item_author'] ?? null, $data['isbn'] ?? null,
                $data['item_type'] ?? 'book', $data['request_date'],
                $data['needed_by_date'] ?? null, $data['requester_name'] ?? null,
                $data['requester_email'] ?? null, $data['requester_institution'] ?? null,
                $data['shipping_method'] ?? null, $data['loan_conditions'] ?? null,
                $data['reproduction_allowed'] ?? false, $data['renewal_allowed'] ?? false,
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

    public function getLibraryBranches($city = null, $status = null) {
        $where = [];
        $params = [];

        if ($city) {
            $where[] = "city = ?";
            $params[] = $city;
        }

        if ($status) {
            $where[] = "branch_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM library_branches{$whereClause} ORDER BY branch_name";

        $branches = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'branches' => $branches
        ];
    }

    public function searchCatalog($query = null, $subject = null, $author = null) {
        $where = [];
        $params = [];

        if ($query) {
            $where[] = "(title LIKE ? OR author LIKE ? OR keywords LIKE ?)";
            $params[] = "%$query%";
            $params[] = "%$query%";
            $params[] = "%$query%";
        }

        if ($subject) {
            $where[] = "subject_headings LIKE ?";
            $params[] = "%$subject%";
        }

        if ($author) {
            $where[] = "author LIKE ?";
            $params[] = "%$author%";
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM library_catalog{$whereClause} ORDER BY title";

        $results = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'results' => $results,
            'total' => count($results)
        ];
    }

    public function getArchivalCollections($type = null, $status = null) {
        $where = [];
        $params = [];

        if ($type) {
            $where[] = "collection_type = ?";
            $params[] = $type;
        }

        if ($status) {
            $where[] = "processing_status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM archival_collections{$whereClause} ORDER BY collection_title";

        $collections = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'collections' => $collections
        ];
    }

    public function getDigitalCollections($accessLevel = null, $contentType = null) {
        $where = [];
        $params = [];

        if ($accessLevel) {
            $where[] = "access_level = ?";
            $params[] = $accessLevel;
        }

        if ($contentType) {
            $where[] = "content_type = ?";
            $params[] = $contentType;
        }

        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM digital_collections{$whereClause} ORDER BY title";

        $collections = $this->db->fetchAll($sql, $params);

        return [
            'success' => true,
            'collections' => $collections
        ];
    }

    // Helper Methods
    private function validateBranchData($data) {
        $required = [
            'branch_name', 'address', 'city', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateMembershipData($data) {
        $required = [
            'user_id', 'first_name', 'last_name', 'registration_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCatalogData($data) {
        $required = [
            'title', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCheckoutData($data) {
        $required = [
            'membership_id', 'catalog_id', 'checkout_date', 'due_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateReturnData($data) {
        $required = [
            'checkout_number', 'return_date'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateCollectionData($data) {
        $required = [
            'collection_title', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateResearchRequestData($data) {
        $required = [
            'researcher_id', 'researcher_name', 'research_topic', 'submission_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateDigitalCollectionData($data) {
        $required = [
            'title', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateProgramData($data) {
        $required = [
            'program_name', 'branch_id', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function validateILLData($data) {
        $required = [
            'requesting_library_id', 'item_title', 'request_date', 'created_by'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
    }

    private function generateBranchCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "LIB{$date}{$random}";
    }

    private function generateMembershipNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "MEM{$date}{$random}";
    }

    private function generateCatalogNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CAT{$date}{$random}";
    }

    private function generateCheckoutNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "CHK{$date}{$random}";
    }

    private function generateCollectionCode() {
        $date = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ARC{$date}{$random}";
    }

    private function generateRequestNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "REQ{$date}{$random}";
    }

    private function generateDigitalId() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "DIG{$date}{$random}";
    }

    private function generateProgramCode() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "PROG{$date}{$random}";
    }

    private function generateLoanNumber() {
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "ILL{$date}{$random}";
    }

    private function checkItemAvailability($catalogId) {
        $sql = "SELECT available_copies, circulating FROM library_catalog WHERE id = ?";
        $item = $this->db->fetch($sql, [$catalogId]);

        if (!$item) {
            return ['available' => false, 'message' => 'Item not found'];
        }

        if (!$item['circulating']) {
            return ['available' => false, 'message' => 'Item is not circulating'];
        }

        return [
            'available' => $item['available_copies'] > 0,
            'available_copies' => $item['available_copies']
        ];
    }

    private function updateCatalogAvailability($catalogId, $change) {
        $sql = "UPDATE library_catalog SET available_copies = available_copies + ? WHERE id = ?";
        $this->db->query($sql, [$change, $catalogId]);
    }

    private function getCheckoutByNumber($checkoutNumber) {
        $sql = "SELECT * FROM library_checkouts WHERE checkout_number = ?";
        return $this->db->fetch($sql, [$checkoutNumber]);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
}

<?php
/**
 * Postal Services Module
 * Handles mail delivery, postal banking, and package tracking
 */

require_once __DIR__ . '/../ServiceModule.php';

class PostalServicesModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Postal Services Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive postal services management including mail delivery, postal banking, package tracking, and postal infrastructure management',
            'dependencies' => ['FinancialManagement', 'IdentityServices', 'Procurement'],
            'permissions' => [
                'postal.view' => 'View postal services and tracking information',
                'postal.ship' => 'Ship packages and mail',
                'postal.track' => 'Track packages and mail',
                'postal.banking' => 'Access postal banking services',
                'postal.admin' => 'Administrative functions for postal services'
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
            CREATE TABLE IF NOT EXISTS postal_addresses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                address_code VARCHAR(20) UNIQUE NOT NULL,
                customer_id INT,

                -- Address Details
                recipient_name VARCHAR(200) NOT NULL,
                company_name VARCHAR(200),
                street_address VARCHAR(255) NOT NULL,
                apartment_unit VARCHAR(50),
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100),
                postal_code VARCHAR(20) NOT NULL,
                country VARCHAR(100) NOT NULL,

                -- Contact Information
                phone VARCHAR(20),
                email VARCHAR(255),
                alternate_phone VARCHAR(20),

                -- Address Type and Status
                address_type ENUM('residential', 'business', 'po_box', 'private_bag') DEFAULT 'residential',
                address_status ENUM('active', 'inactive', 'temporary', 'undeliverable') DEFAULT 'active',

                -- Delivery Preferences
                delivery_instructions TEXT,
                signature_required BOOLEAN DEFAULT FALSE,
                leave_if_absent BOOLEAN DEFAULT TRUE,

                -- Validation and Verification
                address_verified BOOLEAN DEFAULT FALSE,
                verification_date DATE,
                verification_method VARCHAR(50),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_address_code (address_code),
                INDEX idx_customer_id (customer_id),
                INDEX idx_postal_code (postal_code),
                INDEX idx_city (city),
                INDEX idx_address_status (address_status)
            );

            CREATE TABLE IF NOT EXISTS mail_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tracking_number VARCHAR(30) UNIQUE NOT NULL,
                mail_type ENUM('letter', 'parcel', 'package', 'express', 'registered', 'insured') DEFAULT 'letter',

                -- Sender Information
                sender_name VARCHAR(200) NOT NULL,
                sender_address_id INT,
                sender_customer_id INT,

                -- Recipient Information
                recipient_name VARCHAR(200) NOT NULL,
                recipient_address_id INT,
                recipient_customer_id INT,

                -- Mail Details
                weight DECIMAL(8,3),
                dimensions_length DECIMAL(6,2),
                dimensions_width DECIMAL(6,2),
                dimensions_height DECIMAL(6,2),
                declared_value DECIMAL(10,2),

                -- Service Options
                service_type ENUM('standard', 'express', 'priority', 'overnight', 'international') DEFAULT 'standard',
                delivery_option ENUM('signature_required', 'leave_if_absent', 'hold_for_pickup', 'return_if_not_delivered') DEFAULT 'leave_if_absent',
                insurance_amount DECIMAL(10,2) DEFAULT 0,
                tracking_enabled BOOLEAN DEFAULT TRUE,

                -- Postal Processing
                postage_amount DECIMAL(8,2) NOT NULL,
                postage_paid BOOLEAN DEFAULT FALSE,
                payment_method VARCHAR(50),
                payment_reference VARCHAR(100),

                -- Status and Tracking
                current_status ENUM('accepted', 'processed', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'lost', 'damaged') DEFAULT 'accepted',
                status_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                estimated_delivery DATE,
                actual_delivery_date DATE,

                -- Processing Information
                origin_postal_facility VARCHAR(100),
                destination_postal_facility VARCHAR(100),
                current_location VARCHAR(200),

                -- Special Services
                registered_mail BOOLEAN DEFAULT FALSE,
                registered_number VARCHAR(30),
                return_receipt BOOLEAN DEFAULT FALSE,
                return_receipt_number VARCHAR(30),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (sender_address_id) REFERENCES postal_addresses(id),
                FOREIGN KEY (recipient_address_id) REFERENCES postal_addresses(id),
                INDEX idx_tracking_number (tracking_number),
                INDEX idx_sender_customer_id (sender_customer_id),
                INDEX idx_recipient_customer_id (recipient_customer_id),
                INDEX idx_current_status (current_status),
                INDEX idx_estimated_delivery (estimated_delivery),
                INDEX idx_actual_delivery_date (actual_delivery_date)
            );

            CREATE TABLE IF NOT EXISTS mail_tracking_history (
                id INT PRIMARY KEY AUTO_INCREMENT,
                mail_item_id INT NOT NULL,
                tracking_number VARCHAR(30) NOT NULL,

                -- Status Update
                status ENUM('accepted', 'processed', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'lost', 'damaged') NOT NULL,
                status_description TEXT,
                status_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                -- Location Information
                facility_name VARCHAR(200),
                facility_code VARCHAR(20),
                city VARCHAR(100),
                state_province VARCHAR(100),
                country VARCHAR(100),

                -- Additional Information
                carrier_name VARCHAR(100),
                carrier_tracking_number VARCHAR(50),
                delivery_attempted BOOLEAN DEFAULT FALSE,
                delivery_attempt_reason VARCHAR(255),

                -- Staff Information
                processed_by VARCHAR(100),
                notes TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (mail_item_id) REFERENCES mail_items(id) ON DELETE CASCADE,
                INDEX idx_mail_item_id (mail_item_id),
                INDEX idx_tracking_number (tracking_number),
                INDEX idx_status (status),
                INDEX idx_status_timestamp (status_timestamp)
            );

            CREATE TABLE IF NOT EXISTS postal_banking_accounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_number VARCHAR(20) UNIQUE NOT NULL,
                customer_id INT NOT NULL,

                -- Account Details
                account_type ENUM('savings', 'checking', 'money_market', 'certificate_deposit') DEFAULT 'savings',
                account_status ENUM('active', 'inactive', 'frozen', 'closed') DEFAULT 'active',

                -- Account Information
                account_holder_name VARCHAR(200) NOT NULL,
                joint_holder_name VARCHAR(200),

                -- Financial Information
                current_balance DECIMAL(15,2) DEFAULT 0,
                available_balance DECIMAL(15,2) DEFAULT 0,
                minimum_balance DECIMAL(8,2) DEFAULT 0,
                interest_rate DECIMAL(5,3) DEFAULT 0,

                -- Account Settings
                overdraft_protection BOOLEAN DEFAULT FALSE,
                overdraft_limit DECIMAL(10,2) DEFAULT 0,
                atm_card_issued BOOLEAN DEFAULT FALSE,
                online_banking_enabled BOOLEAN DEFAULT TRUE,

                -- Contact Information
                mailing_address_id INT,
                phone VARCHAR(20),
                email VARCHAR(255),

                -- Account History
                opened_date DATE NOT NULL,
                last_transaction_date DATE,
                last_statement_date DATE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (customer_id) REFERENCES postal_addresses(id),
                FOREIGN KEY (mailing_address_id) REFERENCES postal_addresses(id),
                INDEX idx_account_number (account_number),
                INDEX idx_customer_id (customer_id),
                INDEX idx_account_status (account_status),
                INDEX idx_opened_date (opened_date)
            );

            CREATE TABLE IF NOT EXISTS postal_banking_transactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                transaction_id VARCHAR(30) UNIQUE NOT NULL,
                account_id INT NOT NULL,

                -- Transaction Details
                transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'fee', 'interest', 'adjustment') NOT NULL,
                transaction_amount DECIMAL(12,2) NOT NULL,
                transaction_date DATE NOT NULL,
                transaction_time TIME NOT NULL,

                -- Transaction Information
                description VARCHAR(255),
                reference_number VARCHAR(50),
                category VARCHAR(50),

                -- Balances
                balance_before DECIMAL(15,2),
                balance_after DECIMAL(15,2),

                -- Processing Information
                processed_by VARCHAR(100),
                processing_method ENUM('counter', 'atm', 'online', 'mobile', 'phone') DEFAULT 'counter',
                processing_location VARCHAR(100),

                -- Status
                transaction_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'completed',
                reversal_transaction_id VARCHAR(30),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (account_id) REFERENCES postal_banking_accounts(id) ON DELETE CASCADE,
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_account_id (account_id),
                INDEX idx_transaction_type (transaction_type),
                INDEX idx_transaction_date (transaction_date),
                INDEX idx_transaction_status (transaction_status)
            );

            CREATE TABLE IF NOT EXISTS postal_facilities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                facility_code VARCHAR(20) UNIQUE NOT NULL,
                facility_name VARCHAR(200) NOT NULL,

                -- Facility Details
                facility_type ENUM('post_office', 'processing_center', 'delivery_unit', 'collection_box', 'agency') DEFAULT 'post_office',
                facility_status ENUM('active', 'inactive', 'under_maintenance', 'closed') DEFAULT 'active',

                -- Location Information
                street_address VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100),
                postal_code VARCHAR(20) NOT NULL,
                country VARCHAR(100) NOT NULL,

                -- Contact Information
                phone VARCHAR(20),
                email VARCHAR(255),
                manager_name VARCHAR(100),

                -- Operating Hours
                monday_hours VARCHAR(50),
                tuesday_hours VARCHAR(50),
                wednesday_hours VARCHAR(50),
                thursday_hours VARCHAR(50),
                friday_hours VARCHAR(50),
                saturday_hours VARCHAR(50),
                sunday_hours VARCHAR(50),

                -- Services Offered
                services_offered TEXT, -- JSON array of services
                delivery_zones TEXT, -- JSON array of postal codes served

                -- Capacity and Performance
                daily_volume_capacity INT,
                current_daily_volume INT,
                service_area_population INT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_facility_code (facility_code),
                INDEX idx_facility_type (facility_type),
                INDEX idx_city (city),
                INDEX idx_postal_code (postal_code),
                INDEX idx_facility_status (facility_status)
            );

            CREATE TABLE IF NOT EXISTS postal_services_pricing (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_code VARCHAR(20) UNIQUE NOT NULL,
                service_name VARCHAR(100) NOT NULL,

                -- Service Details
                service_category ENUM('domestic', 'international', 'express', 'registered', 'insured', 'bulk') DEFAULT 'domestic',
                service_type ENUM('letter', 'parcel', 'package') DEFAULT 'letter',

                -- Pricing Structure
                base_price DECIMAL(8,2) DEFAULT 0,
                weight_price_per_kg DECIMAL(8,2) DEFAULT 0,
                size_price_per_cm DECIMAL(8,2) DEFAULT 0,
                insurance_rate DECIMAL(5,3) DEFAULT 0, -- percentage

                -- Weight and Size Limits
                min_weight DECIMAL(6,3) DEFAULT 0,
                max_weight DECIMAL(6,3),
                max_length DECIMAL(6,2),
                max_width DECIMAL(6,2),
                max_height DECIMAL(6,2),

                -- Validity
                effective_date DATE NOT NULL,
                expiry_date DATE,
                is_active BOOLEAN DEFAULT TRUE,

                -- Additional Fees
                signature_fee DECIMAL(6,2) DEFAULT 0,
                return_receipt_fee DECIMAL(6,2) DEFAULT 0,
                special_handling_fee DECIMAL(6,2) DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_service_code (service_code),
                INDEX idx_service_category (service_category),
                INDEX idx_service_type (service_type),
                INDEX idx_effective_date (effective_date),
                INDEX idx_is_active (is_active)
            );

            CREATE TABLE IF NOT EXISTS postal_complaints (
                id INT PRIMARY KEY AUTO_INCREMENT,
                complaint_number VARCHAR(20) UNIQUE NOT NULL,
                customer_id INT,

                -- Complaint Details
                complaint_type ENUM('delivery_delay', 'lost_mail', 'damaged_package', 'wrong_delivery', 'service_quality', 'billing', 'other') NOT NULL,
                complaint_description TEXT NOT NULL,
                severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',

                -- Related Items
                tracking_number VARCHAR(30),
                mail_item_id INT,

                -- Status and Resolution
                complaint_status ENUM('open', 'investigating', 'resolved', 'closed', 'escalated') DEFAULT 'open',
                priority_level ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

                -- Resolution Details
                resolution_description TEXT,
                resolution_date DATE,
                resolved_by VARCHAR(100),
                customer_satisfaction_rating DECIMAL(3,2),

                -- Follow-up
                follow_up_required BOOLEAN DEFAULT FALSE,
                follow_up_date DATE,
                follow_up_notes TEXT,

                -- Contact Information
                complainant_name VARCHAR(200),
                complainant_email VARCHAR(255),
                complainant_phone VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (mail_item_id) REFERENCES mail_items(id),
                INDEX idx_complaint_number (complaint_number),
                INDEX idx_customer_id (customer_id),
                INDEX idx_tracking_number (tracking_number),
                INDEX idx_complaint_status (complaint_status),
                INDEX idx_complaint_type (complaint_type),
                INDEX idx_severity_level (severity_level)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup mail processing workflow
        $mailProcessingWorkflow = [
            'name' => 'Mail Processing and Delivery Workflow',
            'description' => 'Complete workflow for mail processing, tracking, and delivery',
            'steps' => [
                [
                    'name' => 'Mail Acceptance',
                    'type' => 'user_task',
                    'assignee' => 'postal_clerk',
                    'form' => 'mail_acceptance_form'
                ],
                [
                    'name' => 'Payment Processing',
                    'type' => 'service_task',
                    'service' => 'payment_processing_service'
                ],
                [
                    'name' => 'Mail Sorting',
                    'type' => 'service_task',
                    'service' => 'mail_sorting_service'
                ],
                [
                    'name' => 'Quality Control',
                    'type' => 'user_task',
                    'assignee' => 'quality_control_officer',
                    'form' => 'quality_control_form'
                ],
                [
                    'name' => 'Dispatch to Delivery',
                    'type' => 'service_task',
                    'service' => 'dispatch_service'
                ],
                [
                    'name' => 'Delivery Attempt',
                    'type' => 'user_task',
                    'assignee' => 'delivery_personnel',
                    'form' => 'delivery_attempt_form'
                ],
                [
                    'name' => 'Delivery Confirmation',
                    'type' => 'user_task',
                    'assignee' => 'recipient',
                    'form' => 'delivery_confirmation_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/postal_workflow.json', json_encode($mailProcessingWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/mail_documents',
            __DIR__ . '/uploads/banking_documents',
            __DIR__ . '/uploads/complaint_documents',
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
            'postal_complaints',
            'postal_services_pricing',
            'postal_facilities',
            'postal_banking_transactions',
            'postal_banking_accounts',
            'mail_tracking_history',
            'mail_items',
            'postal_addresses'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createPostalAddress($data) {
        try {
            $this->validateAddressData($data);
            $addressCode = $this->generateAddressCode();

            $sql = "INSERT INTO postal_addresses (
                address_code, customer_id, recipient_name, company_name,
                street_address, apartment_unit, city, state_province,
                postal_code, country, phone, email, alternate_phone,
                address_type, address_status, delivery_instructions,
                signature_required, leave_if_absent, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $addressId = $this->db->insert($sql, [
                $addressCode, $data['customer_id'] ?? null, $data['recipient_name'],
                $data['company_name'] ?? null, $data['street_address'],
                $data['apartment_unit'] ?? null, $data['city'], $data['state_province'] ?? null,
                $data['postal_code'], $data['country'], $data['phone'] ?? null,
                $data['email'] ?? null, $data['alternate_phone'] ?? null,
                $data['address_type'] ?? 'residential', $data['address_status'] ?? 'active',
                $data['delivery_instructions'] ?? null, $data['signature_required'] ?? false,
                $data['leave_if_absent'] ?? true, $data['created_by']
            ]);

            return [
                'success' => true,
                'address_id' => $addressId,
                'address_code' => $addressCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createMailItem($data) {
        try {
            $this->validateMailData($data);
            $trackingNumber = $this->generateTrackingNumber();

            $sql = "INSERT INTO mail_items (
                tracking_number, mail_type, sender_name, sender_address_id,
                sender_customer_id, recipient_name, recipient_address_id,
                recipient_customer_id, weight, dimensions_length, dimensions_width,
                dimensions_height, declared_value, service_type, delivery_option,
                insurance_amount, tracking_enabled, postage_amount, postage_paid,
                payment_method, payment_reference, estimated_delivery, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $mailId = $this->db->insert($sql, [
                $trackingNumber, $data['mail_type'] ?? 'letter', $data['sender_name'],
                $data['sender_address_id'] ?? null, $data['sender_customer_id'] ?? null,
                $data['recipient_name'], $data['recipient_address_id'] ?? null,
                $data['recipient_customer_id'] ?? null, $data['weight'] ?? null,
                $data['dimensions_length'] ?? null, $data['dimensions_width'] ?? null,
                $data['dimensions_height'] ?? null, $data['declared_value'] ?? null,
                $data['service_type'] ?? 'standard', $data['delivery_option'] ?? 'leave_if_absent',
                $data['insurance_amount'] ?? 0, $data['tracking_enabled'] ?? true,
                $data['postage_amount'], $data['postage_paid'] ?? false,
                $data['payment_method'] ?? null, $data['payment_reference'] ?? null,
                $data['estimated_delivery'] ?? null, $data['created_by']
            ]);

            // Create initial tracking history
            $this->addTrackingHistory($mailId, $trackingNumber, 'accepted', 'Mail item accepted for processing');

            return [
                'success' => true,
                'mail_id' => $mailId,
                'tracking_number' => $trackingNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateMailStatus($trackingNumber, $status, $location = null, $notes = null) {
        try {
            // Update mail item status
            $sql = "UPDATE mail_items SET
                    current_status = ?,
                    status_timestamp = CURRENT_TIMESTAMP,
                    current_location = ?
                    WHERE tracking_number = ?";

            $this->db->query($sql, [$status, $location, $trackingNumber]);

            // Add tracking history
            $mailItem = $this->getMailItemByTracking($trackingNumber);
            if ($mailItem) {
                $this->addTrackingHistory($mailItem['id'], $trackingNumber, $status, $notes, $location);
            }

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getTrackingInfo($trackingNumber) {
        try {
            $mailItem = $this->getMailItemByTracking($trackingNumber);
            if (!$mailItem) {
                return ['success' => false, 'error' => 'Tracking number not found'];
            }

            $trackingHistory = $this->getTrackingHistory($trackingNumber);

            return [
                'success' => true,
                'mail_item' => $mailItem,
                'tracking_history' => $trackingHistory
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createPostalBankingAccount($data) {
        try {
            $this->validateBankingAccountData($data);
            $accountNumber = $this->generateAccountNumber();

            $sql = "INSERT INTO postal_banking_accounts (
                account_number, customer_id, account_type, account_holder_name,
                joint_holder_name, minimum_balance, interest_rate, overdraft_protection,
                overdraft_limit, atm_card_issued, online_banking_enabled,
                mailing_address_id, phone, email, opened_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $accountId = $this->db->insert($sql, [
                $accountNumber, $data['customer_id'], $data['account_type'] ?? 'savings',
                $data['account_holder_name'], $data['joint_holder_name'] ?? null,
                $data['minimum_balance'] ?? 0, $data['interest_rate'] ?? 0,
                $data['overdraft_protection'] ?? false, $data['overdraft_limit'] ?? 0,
                $data['atm_card_issued'] ?? false, $data['online_banking_enabled'] ?? true,
                $data['mailing_address_id'] ?? null, $data['phone'] ?? null,
                $data['email'] ?? null, $data['opened_date'], $data['created_by']
            ]);

            return [
                'success' => true,
                'account_id' => $accountId,
                'account_number' => $accountNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function processBankingTransaction($data) {
        try {
            $this->validateTransactionData($data);
            $transactionId = $this->generateTransactionId();

            // Get current balance
            $account = $this->getBankingAccount($data['account_id']);
            $balanceBefore = $account['current_balance'];

            // Calculate new balance
            $amount = $data['transaction_amount'];
            if (in_array($data['transaction_type'], ['withdrawal', 'fee', 'transfer'])) {
                $amount = -$amount;
            }

            $balanceAfter = $balanceBefore + $amount;

            $sql = "INSERT INTO postal_banking_transactions (
                transaction_id, account_id, transaction_type, transaction_amount,
                transaction_date, transaction_time, description, reference_number,
                category, balance_before, balance_after, processed_by,
                processing_method, processing_location, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $transactionDbId = $this->db->insert($sql, [
                $transactionId, $data['account_id'], $data['transaction_type'],
                abs($data['transaction_amount']), $data['transaction_date'],
                $data['transaction_time'] ?? date('H:i:s'), $data['description'] ?? null,
                $data['reference_number'] ?? null, $data['category'] ?? null,
                $balanceBefore, $balanceAfter, $data['processed_by'] ?? null,
                $data['processing_method'] ?? 'counter', $data['processing_location'] ?? null,
                $data['created_by']
            ]);

            // Update account balance
            $this->updateAccountBalance($data['account_id'], $balanceAfter);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'balance_after' => $balanceAfter
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createPostalFacility($data) {
        try {
            $this->validateFacilityData($data);
            $facilityCode = $this->generateFacilityCode();

            $sql = "INSERT INTO postal_facilities (
                facility_code, facility_name, facility_type, facility_status,
                street_address, city, state_province, postal_code, country,
                phone, email, manager_name, monday_hours, tuesday_hours,
                wednesday_hours, thursday_hours, friday_hours, saturday_hours,
                sunday_hours, services_offered, delivery_zones,
                daily_volume_capacity, service_area_population, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $facilityId = $this->db->insert($sql, [
                $facilityCode, $data['facility_name'], $data['facility_type'] ?? 'post_office',
                $data['facility_status'] ?? 'active', $data['street_address'],
                $data['city'], $data['state_province'] ?? null, $data['postal_code'],
                $data['country'], $data['phone'] ?? null, $data['email'] ?? null,
                $data['manager_name'] ?? null, $data['monday_hours'] ?? null,
                $data['tuesday_hours'] ?? null, $data['wednesday_hours'] ?? null,
                $data['thursday_hours'] ?? null, $data['friday_hours'] ?? null,
                $data['saturday_hours'] ?? null, $data['sunday_hours'] ?? null,
                json_encode($data['services_offered'] ?? []), json_encode($data['delivery_zones'] ?? []),
                $data['daily_volume_capacity'] ?? null, $data['service_area_population'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'facility_id' => $facilityId,
                'facility_code' => $facilityCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function calculatePostage($data) {
        try {
            $this->validatePostageCalculationData($data);

            $pricing = $this->getServicePricing($data['service_type'], $data['mail_type']);

            $postage = $pricing['base_price'];

            // Add weight-based pricing
            if ($data['weight'] > $pricing['min_weight']) {
                $extraWeight = $data['weight'] - $pricing['min_weight'];
                $postage += $extraWeight * $pricing['weight_price_per_kg'];
            }

            // Add insurance
            if ($data['insurance_amount'] > 0) {
                $postage += $data['insurance_amount'] * ($pricing['insurance_rate'] / 100);
            }

            // Add special service fees
            if ($data['signature_required']) {
                $postage += $pricing['signature_fee'];
            }

            if ($data['return_receipt']) {
                $postage += $pricing['return_receipt_fee'];
            }

            return [
                'success' => true,
                'postage_amount' => round($postage, 2),
                'service_details' => $pricing
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fileComplaint($data) {
        try {
            $this->validateComplaintData($data);
            $complaintNumber = $this->generateComplaintNumber();

            $sql = "INSERT INTO postal_complaints (
                complaint_number, customer_id, complaint_type, complaint_description,
                severity_level, tracking_number, mail_item_id, complaint_status,
                priority_level, complainant_name, complainant_email, complainant_phone, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $complaintId = $this->db->insert($sql, [
                $complaintNumber, $data['customer_id'] ?? null, $data['complaint_type'],
                $data['complaint_description'], $data['severity_level'] ?? 'medium',
                $data['tracking_number'] ?? null, $data['mail_item_id'] ?? null,
                $data['complaint_status'] ?? 'open', $data['priority_level'] ?? 'normal',
                $data['complainant_name'] ?? null, $data['complainant_email'] ?? null,
                $data['complainant_phone'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'complaint_id' => $complaintId,
                'complaint_number' => $complaintNumber
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Helper Methods
    private function addTrackingHistory($mailId, $trackingNumber, $status, $description, $location = null) {
        $sql = "INSERT INTO mail_tracking_history (
            mail_item_id, tracking_number, status, status_description,
            facility_name, facility_code, city, state_province, country,
            processed_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->query($sql, [
            $mailId, $trackingNumber, $status, $description,
            $location['facility_name'] ?? null, $location['facility_code'] ?? null,
            $location['city'] ?? null, $location['state_province'] ?? null,
            $location['country'] ?? null, $location['processed_by'] ?? null,
            $location['notes'] ?? null
        ]);
    }

    private function getMailItemByTracking($trackingNumber) {
        $sql = "SELECT * FROM mail_items WHERE tracking_number = ?";
        return $this->db->query($sql, [$trackingNumber])->fetch(PDO::FETCH_ASSOC);
    }

    private function getTrackingHistory($trackingNumber) {
        $sql = "SELECT * FROM mail_tracking_history
                WHERE tracking_number = ?
                ORDER BY status_timestamp DESC";
        return $this->db->query($sql, [$trackingNumber])->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getBankingAccount($accountId) {
        $sql = "SELECT * FROM postal_banking_accounts WHERE id = ?";
        return $this->db->query($sql, [$accountId])->fetch(PDO::FETCH_ASSOC);
    }

    private function updateAccountBalance($accountId, $newBalance) {
        $sql = "UPDATE postal_banking_accounts SET
                current_balance = ?,
                available_balance = ?,
                last_transaction_date = CURRENT_DATE,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $this->db->query($sql, [$newBalance, $newBalance, $accountId]);
    }

    private function getServicePricing($serviceType, $mailType) {
        $sql = "SELECT * FROM postal_services_pricing
                WHERE service_category = ? AND service_type = ? AND is_active = TRUE
                ORDER BY effective_date DESC LIMIT 1";

        $result = $this->db->query($sql, [$serviceType, $mailType])->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception('No pricing found for the specified service');
        }

        return $result;
    }

    // Validation Methods
    private function validateAddressData($data) {
        if (empty($data['recipient_name'])) {
            throw new Exception('Recipient name is required');
        }
        if (empty($data['street_address'])) {
            throw new Exception('Street address is required');
        }
        if (empty($data['city'])) {
            throw new Exception('City is required');
        }
        if (empty($data['postal_code'])) {
            throw new Exception('Postal code is required');
        }
        if (empty($data['country'])) {
            throw new Exception('Country is required');
        }
    }

    private function validateMailData($data) {
        if (empty($data['sender_name'])) {
            throw new Exception('Sender name is required');
        }
        if (empty($data['recipient_name'])) {
            throw new Exception('Recipient name is required');
        }
        if (empty($data['postage_amount']) || $data['postage_amount'] <= 0) {
            throw new Exception('Valid postage amount is required');
        }
    }

    private function validateBankingAccountData($data) {
        if (empty($data['customer_id'])) {
            throw new Exception('Customer ID is required');
        }
        if (empty($data['account_holder_name'])) {
            throw new Exception('Account holder name is required');
        }
        if (empty($data['opened_date'])) {
            throw new Exception('Account opening date is required');
        }
    }

    private function validateTransactionData($data) {
        if (empty($data['account_id'])) {
            throw new Exception('Account ID is required');
        }
        if (empty($data['transaction_type'])) {
            throw new Exception('Transaction type is required');
        }
        if (empty($data['transaction_amount']) || $data['transaction_amount'] <= 0) {
            throw new Exception('Valid transaction amount is required');
        }
        if (empty($data['transaction_date'])) {
            throw new Exception('Transaction date is required');
        }
    }

    private function validateFacilityData($data) {
        if (empty($data['facility_name'])) {
            throw new Exception('Facility name is required');
        }
        if (empty($data['street_address'])) {
            throw new Exception('Street address is required');
        }
        if (empty($data['city'])) {
            throw new Exception('City is required');
        }
        if (empty($data['postal_code'])) {
            throw new Exception('Postal code is required');
        }
        if (empty($data['country'])) {
            throw new Exception('Country is required');
        }
    }

    private function validatePostageCalculationData($data) {
        if (empty($data['service_type'])) {
            throw new Exception('Service type is required');
        }
        if (empty($data['mail_type'])) {
            throw new Exception('Mail type is required');
        }
    }

    private function validateComplaintData($data) {
        if (empty($data['complaint_type'])) {
            throw new Exception('Complaint type is required');
        }
        if (empty($data['complaint_description'])) {
            throw new Exception('Complaint description is required');
        }
    }

    // Code Generation Methods
    private function generateAddressCode() {
        return 'ADDR-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateTrackingNumber() {
        return 'TRK-' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateAccountNumber() {
        return 'PSB-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateTransactionId() {
        return 'TXN-' . date('YmdHis') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateFacilityCode() {
        return 'FAC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateComplaintNumber() {
        return 'CMP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [
            'database_table_prefix' => 'postal_',
            'upload_path' => __DIR__ . '/uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_email' => 'admin@example.com',
            'currency' => 'USD',
            'default_postage_rates' => [
                'domestic' => ['base' => 1.00, 'per_kg' => 0.50],
                'international' => ['base' => 2.50, 'per_kg' => 1.00]
            ]
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
+++++++ REPLACE</diff>

</replace_in_file>

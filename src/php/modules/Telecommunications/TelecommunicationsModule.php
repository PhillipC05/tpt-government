<?php
/**
 * Telecommunications Module
 * Handles internet services, phone services, and broadband expansion
 */

require_once __DIR__ . '/../ServiceModule.php';

class TelecommunicationsModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Telecommunications Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive telecommunications management system including internet services, phone services, broadband expansion, network infrastructure, and service provisioning',
            'dependencies' => ['FinancialManagement', 'IdentityServices', 'Procurement'],
            'permissions' => [
                'telecom.view' => 'View telecommunications services and information',
                'telecom.apply' => 'Apply for telecommunications services',
                'telecom.manage' => 'Manage telecommunications infrastructure',
                'telecom.admin' => 'Administrative functions for telecommunications'
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
            CREATE TABLE IF NOT EXISTS telecom_subscribers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                subscriber_code VARCHAR(20) UNIQUE NOT NULL,
                customer_id INT,

                -- Subscriber Information
                subscriber_type ENUM('individual', 'business', 'government', 'institution') DEFAULT 'individual',
                subscriber_name VARCHAR(200) NOT NULL,
                contact_person VARCHAR(200),
                contact_email VARCHAR(255),
                contact_phone VARCHAR(20),

                -- Service Address
                service_address_id INT,
                installation_address TEXT,

                -- Account Details
                account_status ENUM('active', 'inactive', 'suspended', 'terminated') DEFAULT 'active',
                account_type ENUM('residential', 'business', 'enterprise') DEFAULT 'residential',
                billing_cycle ENUM('monthly', 'quarterly', 'annually') DEFAULT 'monthly',

                -- Service Activation
                activation_date DATE,
                service_start_date DATE,
                contract_end_date DATE,
                auto_renewal BOOLEAN DEFAULT TRUE,

                -- Financial Information
                credit_limit DECIMAL(10,2) DEFAULT 0,
                current_balance DECIMAL(10,2) DEFAULT 0,
                outstanding_balance DECIMAL(10,2) DEFAULT 0,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_subscriber_code (subscriber_code),
                INDEX idx_customer_id (customer_id),
                INDEX idx_account_status (account_status),
                INDEX idx_service_start_date (service_start_date)
            );

            CREATE TABLE IF NOT EXISTS telecom_services (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_code VARCHAR(20) UNIQUE NOT NULL,
                service_name VARCHAR(200) NOT NULL,

                -- Service Details
                service_category ENUM('internet', 'phone', 'mobile', 'tv', 'bundled', 'other') DEFAULT 'internet',
                service_type ENUM('broadband', 'dialup', 'fiber', 'cable', 'dsl', 'wireless', 'satellite', 'voip', 'landline', 'mobile_plan') DEFAULT 'broadband',

                -- Service Specifications
                download_speed INT, -- Mbps
                upload_speed INT, -- Mbps
                data_limit INT, -- GB per month, NULL for unlimited
                included_minutes INT, -- for phone services
                channel_count INT, -- for TV services

                -- Pricing
                base_price DECIMAL(8,2) NOT NULL,
                setup_fee DECIMAL(8,2) DEFAULT 0,
                early_termination_fee DECIMAL(8,2) DEFAULT 0,

                -- Service Terms
                contract_length_months INT DEFAULT 0, -- 0 for month-to-month
                minimum_service_period INT DEFAULT 1,

                -- Availability
                service_status ENUM('available', 'discontinued', 'coming_soon') DEFAULT 'available',
                available_regions TEXT, -- JSON array of region codes
                prerequisites TEXT, -- JSON array of required services/conditions

                -- Technical Details
                technology_type VARCHAR(50),
                equipment_provided BOOLEAN DEFAULT TRUE,
                installation_required BOOLEAN DEFAULT TRUE,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_service_code (service_code),
                INDEX idx_service_category (service_category),
                INDEX idx_service_type (service_type),
                INDEX idx_service_status (service_status)
            );

            CREATE TABLE IF NOT EXISTS telecom_subscriptions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                subscription_code VARCHAR(30) UNIQUE NOT NULL,
                subscriber_id INT NOT NULL,
                service_id INT NOT NULL,

                -- Subscription Details
                subscription_status ENUM('pending', 'active', 'suspended', 'cancelled', 'terminated') DEFAULT 'pending',
                subscription_date DATE NOT NULL,
                activation_date DATE,
                termination_date DATE,

                -- Service Configuration
                custom_speed_limit INT,
                custom_data_limit INT,
                additional_features TEXT, -- JSON array

                -- Billing Information
                billing_start_date DATE,
                next_billing_date DATE,
                monthly_recurring_charge DECIMAL(8,2),
                prorated_charge DECIMAL(8,2) DEFAULT 0,

                -- Equipment and Installation
                equipment_serial_number VARCHAR(50),
                installation_date DATE,
                installer_name VARCHAR(100),
                installation_notes TEXT,

                -- Network Information
                ip_address VARCHAR(45),
                mac_address VARCHAR(17),
                network_id VARCHAR(20),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (subscriber_id) REFERENCES telecom_subscribers(id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES telecom_services(id) ON DELETE CASCADE,
                INDEX idx_subscription_code (subscription_code),
                INDEX idx_subscriber_id (subscriber_id),
                INDEX idx_service_id (service_id),
                INDEX idx_subscription_status (subscription_status),
                INDEX idx_next_billing_date (next_billing_date)
            );

            CREATE TABLE IF NOT EXISTS telecom_billing_records (
                id INT PRIMARY KEY AUTO_INCREMENT,
                billing_code VARCHAR(30) UNIQUE NOT NULL,
                subscription_id INT NOT NULL,

                -- Billing Period
                billing_period_start DATE NOT NULL,
                billing_period_end DATE NOT NULL,
                billing_date DATE NOT NULL,
                due_date DATE NOT NULL,

                -- Charges
                recurring_charges DECIMAL(8,2) DEFAULT 0,
                usage_charges DECIMAL(8,2) DEFAULT 0,
                one_time_charges DECIMAL(8,2) DEFAULT 0,
                taxes DECIMAL(8,2) DEFAULT 0,
                discounts DECIMAL(8,2) DEFAULT 0,
                total_amount DECIMAL(8,2) NOT NULL,

                -- Payment Information
                payment_status ENUM('unpaid', 'paid', 'overdue', 'written_off') DEFAULT 'unpaid',
                payment_date DATE,
                payment_method VARCHAR(50),
                payment_reference VARCHAR(100),

                -- Usage Details
                data_used_gb DECIMAL(10,2) DEFAULT 0,
                minutes_used INT DEFAULT 0,
                additional_charges TEXT, -- JSON array of charge details

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (subscription_id) REFERENCES telecom_subscriptions(id) ON DELETE CASCADE,
                INDEX idx_billing_code (billing_code),
                INDEX idx_subscription_id (subscription_id),
                INDEX idx_billing_date (billing_date),
                INDEX idx_due_date (due_date),
                INDEX idx_payment_status (payment_status)
            );

            CREATE TABLE IF NOT EXISTS telecom_network_infrastructure (
                id INT PRIMARY KEY AUTO_INCREMENT,
                infrastructure_code VARCHAR(20) UNIQUE NOT NULL,
                infrastructure_name VARCHAR(200) NOT NULL,

                -- Infrastructure Details
                infrastructure_type ENUM('cable', 'fiber', 'wireless_tower', 'satellite_dish', 'exchange', 'distribution_point', 'other') DEFAULT 'cable',
                infrastructure_status ENUM('planned', 'under_construction', 'operational', 'maintenance', 'decommissioned') DEFAULT 'planned',

                -- Location Information
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                address VARCHAR(255),
                city VARCHAR(100),
                state_province VARCHAR(100),
                postal_code VARCHAR(20),
                country VARCHAR(100),

                -- Technical Specifications
                capacity INT,
                technology VARCHAR(50),
                coverage_area_km DECIMAL(8,2),
                max_connections INT,

                -- Installation and Maintenance
                installation_date DATE,
                last_maintenance_date DATE,
                next_maintenance_date DATE,
                maintenance_schedule TEXT,

                -- Performance Metrics
                uptime_percentage DECIMAL(5,2),
                average_speed_mbps INT,
                connected_subscribers INT DEFAULT 0,

                -- Cost and Ownership
                installation_cost DECIMAL(15,2),
                monthly_maintenance_cost DECIMAL(10,2),
                ownership_type ENUM('owned', 'leased', 'partner') DEFAULT 'owned',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_infrastructure_code (infrastructure_code),
                INDEX idx_infrastructure_type (infrastructure_type),
                INDEX idx_city (city),
                INDEX idx_infrastructure_status (infrastructure_status)
            );

            CREATE TABLE IF NOT EXISTS telecom_service_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                request_code VARCHAR(20) UNIQUE NOT NULL,
                subscriber_id INT,

                -- Request Details
                request_type ENUM('new_service', 'service_modification', 'troubleshooting', 'equipment_replacement', 'relocation', 'disconnection', 'billing_inquiry', 'other') NOT NULL,
                request_description TEXT NOT NULL,
                urgency_level ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',

                -- Related Information
                subscription_id INT,
                service_id INT,

                -- Status and Resolution
                request_status ENUM('open', 'assigned', 'in_progress', 'pending_customer', 'resolved', 'closed', 'cancelled') DEFAULT 'open',
                priority_level ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

                -- Assignment and Resolution
                assigned_to VARCHAR(100),
                assigned_date DATE,
                estimated_resolution_date DATE,
                actual_resolution_date DATE,
                resolution_description TEXT,

                -- Customer Communication
                customer_contact_method ENUM('phone', 'email', 'portal', 'field_visit') DEFAULT 'phone',
                last_customer_contact DATE,
                customer_satisfaction_rating DECIMAL(3,2),

                -- Technical Details
                troubleshooting_steps TEXT,
                equipment_details TEXT,
                network_diagnostics TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (subscriber_id) REFERENCES telecom_subscribers(id) ON DELETE CASCADE,
                FOREIGN KEY (subscription_id) REFERENCES telecom_subscriptions(id) ON DELETE SET NULL,
                FOREIGN KEY (service_id) REFERENCES telecom_services(id) ON DELETE SET NULL,
                INDEX idx_request_code (request_code),
                INDEX idx_subscriber_id (subscriber_id),
                INDEX idx_request_status (request_status),
                INDEX idx_request_type (request_type),
                INDEX idx_urgency_level (urgency_level)
            );

            CREATE TABLE IF NOT EXISTS telecom_coverage_areas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                area_code VARCHAR(20) UNIQUE NOT NULL,
                area_name VARCHAR(200) NOT NULL,

                -- Geographic Information
                geographic_type ENUM('postal_code', 'neighborhood', 'district', 'city', 'region') DEFAULT 'postal_code',
                boundary_coordinates TEXT, -- JSON polygon coordinates
                center_latitude DECIMAL(10,8),
                center_longitude DECIMAL(11,8),

                -- Coverage Details
                broadband_available BOOLEAN DEFAULT FALSE,
                max_download_speed INT,
                max_upload_speed INT,
                available_providers TEXT, -- JSON array

                -- Infrastructure Status
                infrastructure_status ENUM('no_coverage', 'partial_coverage', 'full_coverage', 'overbuilt') DEFAULT 'no_coverage',
                planned_expansion BOOLEAN DEFAULT FALSE,
                expansion_timeline DATE,

                -- Demographics
                population_served INT,
                households_served INT,
                business_density VARCHAR(20),

                -- Competition and Demand
                competitor_presence BOOLEAN DEFAULT FALSE,
                service_demand ENUM('low', 'medium', 'high', 'very_high') DEFAULT 'medium',
                adoption_rate DECIMAL(5,2),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_area_code (area_code),
                INDEX idx_geographic_type (geographic_type),
                INDEX idx_infrastructure_status (infrastructure_status),
                INDEX idx_broadband_available (broadband_available)
            );

            CREATE TABLE IF NOT EXISTS telecom_regulatory_compliance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                compliance_code VARCHAR(20) UNIQUE NOT NULL,
                compliance_type ENUM('licensing', 'spectrum', 'universal_service', 'consumer_protection', 'net_neutrality', 'data_privacy', 'other') NOT NULL,

                -- Compliance Details
                compliance_description TEXT,
                regulatory_body VARCHAR(100),
                compliance_standard VARCHAR(100),

                -- Requirements
                requirements TEXT, -- JSON array of requirements
                documentation_required TEXT, -- JSON array of documents

                -- Validity and Status
                effective_date DATE NOT NULL,
                expiry_date DATE,
                compliance_status ENUM('compliant', 'non_compliant', 'pending_review', 'under_review', 'expired') DEFAULT 'pending_review',

                -- Monitoring and Enforcement
                last_audit_date DATE,
                next_audit_date DATE,
                audit_findings TEXT,
                corrective_actions TEXT,

                -- Penalties and Fines
                penalties_incurred DECIMAL(15,2) DEFAULT 0,
                penalty_details TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_compliance_code (compliance_code),
                INDEX idx_compliance_type (compliance_type),
                INDEX idx_compliance_status (compliance_status),
                INDEX idx_effective_date (effective_date),
                INDEX idx_expiry_date (expiry_date)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup service provisioning workflow
        $serviceProvisioningWorkflow = [
            'name' => 'Telecommunications Service Provisioning Workflow',
            'description' => 'Complete workflow for telecommunications service provisioning and management',
            'steps' => [
                [
                    'name' => 'Service Application',
                    'type' => 'user_task',
                    'assignee' => 'customer',
                    'form' => 'service_application_form'
                ],
                [
                    'name' => 'Credit Check',
                    'type' => 'service_task',
                    'service' => 'credit_check_service'
                ],
                [
                    'name' => 'Technical Feasibility',
                    'type' => 'user_task',
                    'assignee' => 'technical_assessor',
                    'form' => 'technical_feasibility_form'
                ],
                [
                    'name' => 'Service Configuration',
                    'type' => 'user_task',
                    'assignee' => 'service_configurator',
                    'form' => 'service_configuration_form'
                ],
                [
                    'name' => 'Installation Scheduling',
                    'type' => 'service_task',
                    'service' => 'installation_scheduling_service'
                ],
                [
                    'name' => 'Equipment Installation',
                    'type' => 'user_task',
                    'assignee' => 'installation_technician',
                    'form' => 'installation_form'
                ],
                [
                    'name' => 'Service Activation',
                    'type' => 'service_task',
                    'service' => 'service_activation_service'
                ],
                [
                    'name' => 'Quality Testing',
                    'type' => 'user_task',
                    'assignee' => 'quality_assurance_officer',
                    'form' => 'quality_testing_form'
                ],
                [
                    'name' => 'Service Handover',
                    'type' => 'user_task',
                    'assignee' => 'customer_service_rep',
                    'form' => 'service_handover_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/telecom_workflow.json', json_encode($serviceProvisioningWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/service_applications',
            __DIR__ . '/uploads/billing_documents',
            __DIR__ . '/uploads/network_diagrams',
            __DIR__ . '/uploads/compliance_documents',
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
            'telecom_regulatory_compliance',
            'telecom_coverage_areas',
            'telecom_service_requests',
            'telecom_network_infrastructure',
            'telecom_billing_records',
            'telecom_subscriptions',
            'telecom_services',
            'telecom_subscribers'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createSubscriber($data) {
        try {
            $this->validateSubscriberData($data);
            $subscriberCode = $this->generateSubscriberCode();

            $sql = "INSERT INTO telecom_subscribers (
                subscriber_code, customer_id, subscriber_type, subscriber_name,
                contact_person, contact_email, contact_phone, service_address_id,
                installation_address, account_status, account_type, billing_cycle,
                activation_date, service_start_date, contract_end_date, auto_renewal,
                credit_limit, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $subscriberId = $this->db->insert($sql, [
                $subscriberCode, $data['customer_id'] ?? null, $data['subscriber_type'] ?? 'individual',
                $data['subscriber_name'], $data['contact_person'] ?? null, $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null, $data['service_address_id'] ?? null,
                $data['installation_address'] ?? null, $data['account_status'] ?? 'active',
                $data['account_type'] ?? 'residential', $data['billing_cycle'] ?? 'monthly',
                $data['activation_date'] ?? null, $data['service_start_date'] ?? null,
                $data['contract_end_date'] ?? null, $data['auto_renewal'] ?? true,
                $data['credit_limit'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'subscriber_id' => $subscriberId,
                'subscriber_code' => $subscriberCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTelecomService($data) {
        try {
            $this->validateServiceData($data);
            $serviceCode = $this->generateServiceCode();

            $sql = "INSERT INTO telecom_services (
                service_code, service_name, service_category, service_type,
                download_speed, upload_speed, data_limit, included_minutes,
                channel_count, base_price, setup_fee, early_termination_fee,
                contract_length_months, minimum_service_period, service_status,
                available_regions, prerequisites, technology_type, equipment_provided,
                installation_required, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $serviceId = $this->db->insert($sql, [
                $serviceCode, $data['service_name'], $data['service_category'] ?? 'internet',
                $data['service_type'] ?? 'broadband', $data['download_speed'] ?? null,
                $data['upload_speed'] ?? null, $data['data_limit'] ?? null,
                $data['included_minutes'] ?? null, $data['channel_count'] ?? null,
                $data['base_price'], $data['setup_fee'] ?? 0, $data['early_termination_fee'] ?? 0,
                $data['contract_length_months'] ?? 0, $data['minimum_service_period'] ?? 1,
                $data['service_status'] ?? 'available', json_encode($data['available_regions'] ?? []),
                json_encode($data['prerequisites'] ?? []), $data['technology_type'] ?? null,
                $data['equipment_provided'] ?? true, $data['installation_required'] ?? true,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'service_id' => $serviceId,
                'service_code' => $serviceCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createSubscription($data) {
        try {
            $this->validateSubscriptionData($data);
            $subscriptionCode = $this->generateSubscriptionCode();

            $sql = "INSERT INTO telecom_subscriptions (
                subscription_code, subscriber_id, service_id, subscription_status,
                subscription_date, activation_date, custom_speed_limit, custom_data_limit,
                additional_features, billing_start_date, monthly_recurring_charge,
                equipment_serial_number, installation_date, installer_name,
                installation_notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $subscriptionId = $this->db->insert($sql, [
                $subscriptionCode, $data['subscriber_id'], $data['service_id'],
                $data['subscription_status'] ?? 'pending', $data['subscription_date'],
                $data['activation_date'] ?? null, $data['custom_speed_limit'] ?? null,
                $data['custom_data_limit'] ?? null, json_encode($data['additional_features'] ?? []),
                $data['billing_start_date'] ?? null, $data['monthly_recurring_charge'] ?? 0,
                $data['equipment_serial_number'] ?? null, $data['installation_date'] ?? null,
                $data['installer_name'] ?? null, $data['installation_notes'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'subscription_code' => $subscriptionCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function generateBillingRecord($data) {
        try {
            $this->validateBillingData($data);
            $billingCode = $this->generateBillingCode();

            $sql = "INSERT INTO telecom_billing_records (
                billing_code, subscription_id, billing_period_start, billing_period_end,
                billing_date, due_date, recurring_charges, usage_charges, one_time_charges,
                taxes, discounts, total_amount, data_used_gb, minutes_used,
                additional_charges, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $billingId = $this->db->insert($sql, [
                $billingCode, $data['subscription_id'], $data['billing_period_start'],
                $data['billing_period_end'], $data['billing_date'], $data['due_date'],
                $data['recurring_charges'] ?? 0, $data['usage_charges'] ?? 0,
                $data['one_time_charges'] ?? 0, $data['taxes'] ?? 0,
                $data['discounts'] ?? 0, $data['total_amount'], $data['data_used_gb'] ?? 0,
                $data['minutes_used'] ?? 0, json_encode($data['additional_charges'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'billing_id' => $billingId,
                'billing_code' => $billingCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createNetworkInfrastructure($data) {
        try {
            $this->validateInfrastructureData($data);
            $infrastructureCode = $this->generateInfrastructureCode();

            $sql = "INSERT INTO telecom_network_infrastructure (
                infrastructure_code, infrastructure_name, infrastructure_type,
                infrastructure_status, latitude, longitude, address, city,
                state_province, postal_code, country, capacity, technology,
                coverage_area_km, max_connections, installation_date,
                last_maintenance_date, next_maintenance_date, maintenance_schedule,
                uptime_percentage, average_speed_mbps, installation_cost,
                monthly_maintenance_cost, ownership_type, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $infrastructureId = $this->db->insert($sql, [
                $infrastructureCode, $data['infrastructure_name'], $data['infrastructure_type'] ?? 'cable',
                $data['infrastructure_status'] ?? 'planned', $data['latitude'] ?? null,
                $data['longitude'] ?? null, $data['address'] ?? null, $data['city'] ?? null,
                $data['state_province'] ?? null, $data['postal_code'] ?? null, $data['country'] ?? null,
                $data['capacity'] ?? null, $data['technology'] ?? null, $data['coverage_area_km'] ?? null,
                $data['max_connections'] ?? null, $data['installation_date'] ?? null,
                $data['last_maintenance_date'] ?? null, $data['next_maintenance_date'] ?? null,
                $data['maintenance_schedule'] ?? null, $data['uptime_percentage'] ?? null,
                $data['average_speed_mbps'] ?? null, $data['installation_cost'] ?? null,
                $data['monthly_maintenance_cost'] ?? null, $data['ownership_type'] ?? 'owned',
                $data['created_by']
            ]);

            return [
                'success' => true,
                'infrastructure_id' => $infrastructureId,
                'infrastructure_code' => $infrastructureCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createServiceRequest($data) {
        try {
            $this->validateServiceRequestData($data);
            $requestCode = $this->generateRequestCode();

            $sql = "INSERT INTO telecom_service_requests (
                request_code, subscriber_id, request_type, request_description,
                urgency_level, subscription_id, service_id, request_status,
                priority_level, assigned_to, estimated_resolution_date,
                customer_contact_method, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $requestId = $this->db->insert($sql, [
                $requestCode, $data['subscriber_id'] ?? null, $data['request_type'],
                $data['request_description'], $data['urgency_level'] ?? 'normal',
                $data['subscription_id'] ?? null, $data['service_id'] ?? null,
                $data['request_status'] ?? 'open', $data['priority_level'] ?? 'normal',
                $data['assigned_to'] ?? null, $data['estimated_resolution_date'] ?? null,
                $data['customer_contact_method'] ?? 'phone', $data['created_by']
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'request_code' => $requestCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createCoverageArea($data) {
        try {
            $this->validateCoverageAreaData($data);
            $areaCode = $this->generateAreaCode();

            $sql = "INSERT INTO telecom_coverage_areas (
                area_code, area_name, geographic_type, boundary_coordinates,
                center_latitude, center_longitude, broadband_available, max_download_speed,
                max_upload_speed, available_providers, infrastructure_status,
                planned_expansion, expansion_timeline, population_served,
                households_served, business_density, competitor_presence,
                service_demand, adoption_rate, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $areaId = $this->db->insert($sql, [
                $areaCode, $data['area_name'], $data['geographic_type'] ?? 'postal_code',
                json_encode($data['boundary_coordinates'] ?? []), $data['center_latitude'] ?? null,
                $data['center_longitude'] ?? null, $data['broadband_available'] ?? false,
                $data['max_download_speed'] ?? null, $data['max_upload_speed'] ?? null,
                json_encode($data['available_providers'] ?? []), $data['infrastructure_status'] ?? 'no_coverage',
                $data['planned_expansion'] ?? false, $data['expansion_timeline'] ?? null,
                $data['population_served'] ?? null, $data['households_served'] ?? null,
                $data['business_density'] ?? null, $data['competitor_presence'] ?? false,
                $data['service_demand'] ?? 'medium', $data['adoption_rate'] ?? null,
                $data['created_by']
            ]);

            return [
                'success' => true,
                'area_id' => $areaId,
                'area_code' => $areaCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createComplianceRecord($data) {
        try {
            $this->validateComplianceData($data);
            $complianceCode = $this->generateComplianceCode();

            $sql = "INSERT INTO telecom_regulatory_compliance (
                compliance_code, compliance_type, compliance_description,
                regulatory_body, compliance_standard, requirements,
                documentation_required, effective_date, expiry_date,
                compliance_status, last_audit_date, next_audit_date,
                penalties_incurred, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $complianceId = $this->db->insert($sql, [
                $complianceCode, $data['compliance_type'], $data['compliance_description'] ?? null,
                $data['regulatory_body'] ?? null, $data['compliance_standard'] ?? null,
                json_encode($data['requirements'] ?? []), json_encode($data['documentation_required'] ?? []),
                $data['effective_date'], $data['expiry_date'] ?? null, $data['compliance_status'] ?? 'pending_review',
                $data['last_audit_date'] ?? null, $data['next_audit_date'] ?? null,
                $data['penalties_incurred'] ?? 0, $data['created_by']
            ]);

            return [
                'success' => true,
                'compliance_id' => $complianceId,
                'compliance_code' => $complianceCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Validation Methods
    private function validateSubscriberData($data) {
        if (empty($data['subscriber_name'])) {
            throw new Exception('Subscriber name is required');
        }
    }

    private function validateServiceData($data) {
        if (empty($data['service_name'])) {
            throw new Exception('Service name is required');
        }
        if (empty($data['base_price']) || $data['base_price'] <= 0) {
            throw new Exception('Valid base price is required');
        }
    }

    private function validateSubscriptionData($data) {
        if (empty($data['subscriber_id'])) {
            throw new Exception('Subscriber ID is required');
        }
        if (empty($data['service_id'])) {
            throw new Exception('Service ID is required');
        }
        if (empty($data['subscription_date'])) {
            throw new Exception('Subscription date is required');
        }
    }

    private function validateBillingData($data) {
        if (empty($data['subscription_id'])) {
            throw new Exception('Subscription ID is required');
        }
        if (empty($data['billing_period_start'])) {
            throw new Exception('Billing period start date is required');
        }
        if (empty($data['billing_period_end'])) {
            throw new Exception('Billing period end date is required');
        }
        if (empty($data['billing_date'])) {
            throw new Exception('Billing date is required');
        }
        if (empty($data['due_date'])) {
            throw new Exception('Due date is required');
        }
        if (empty($data['total_amount']) || $data['total_amount'] <= 0) {
            throw new Exception('Valid total amount is required');
        }
    }

    private function validateInfrastructureData($data) {
        if (empty($data['infrastructure_name'])) {
            throw new Exception('Infrastructure name is required');
        }
    }

    private function validateServiceRequestData($data) {
        if (empty($data['request_type'])) {
            throw new Exception('Request type is required');
        }
        if (empty($data['request_description'])) {
            throw new Exception('Request description is required');
        }
    }

    private function validateCoverageAreaData($data) {
        if (empty($data['area_name'])) {
            throw new Exception('Area name is required');
        }
    }

    private function validateComplianceData($data) {
        if (empty($data['compliance_type'])) {
            throw new Exception('Compliance type is required');
        }
        if (empty($data['effective_date'])) {
            throw new Exception('Effective date is required');
        }
    }

    // Code Generation Methods
    private function generateSubscriberCode() {
        return 'SUB-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateServiceCode() {
        return 'SVC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateSubscriptionCode() {
        return 'SUBS-' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateBillingCode() {
        return 'BILL-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateInfrastructureCode() {
        return 'INF-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateRequestCode() {
        return 'REQ-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateAreaCode() {
        return 'AREA-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateComplianceCode() {
        return 'COMP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Additional API Methods
    public function getSubscriberInfo($subscriberId) {
        try {
            $sql = "SELECT * FROM telecom_subscribers WHERE id = ?";
            $result = $this->db->query($sql, [$subscriberId])->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $result['installation_address'] = json_decode($result['installation_address'], true);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getSubscriptionDetails($subscriptionId) {
        try {
            $sql = "SELECT ts.*, tsub.subscriber_name, tsrv.service_name
                    FROM telecom_subscriptions ts
                    JOIN telecom_subscribers tsub ON ts.subscriber_id = tsub.id
                    JOIN telecom_services tsrv ON ts.service_id = tsrv.id
                    WHERE ts.id = ?";
            $result = $this->db->query($sql, [$subscriptionId])->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $result['additional_features'] = json_decode($result['additional_features'], true);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBillingHistory($subscriptionId, $limit = 12) {
        try {
            $sql = "SELECT * FROM telecom_billing_records
                    WHERE subscription_id = ?
                    ORDER BY billing_date DESC LIMIT ?";
            $results = $this->db->query($sql, [$subscriptionId, $limit])->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$result) {
                $result['additional_charges'] = json_decode($result['additional_charges'], true);
            }

            return $results;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAvailableServices($filters = []) {
        try {
            $sql = "SELECT * FROM telecom_services WHERE service_status = 'available'";
            $params = [];

            if (!empty($filters['service_category'])) {
                $sql .= " AND service_category = ?";
                $params[] = $filters['service_category'];
            }

            if (!empty($filters['service_type'])) {
                $sql .= " AND service_type = ?";
                $params[] = $filters['service_type'];
            }

            $sql .= " ORDER BY service_name ASC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getCoverageMap($filters = []) {
        try {
            $sql = "SELECT * FROM telecom_coverage_areas";
            $params = [];

            if (!empty($filters['broadband_available'])) {
                $sql .= " WHERE broadband_available = ?";
                $params[] = $filters['broadband_available'];
            }

            if (!empty($filters['infrastructure_status'])) {
                $whereClause = empty($params) ? " WHERE" : " AND";
                $sql .= "$whereClause infrastructure_status = ?";
                $params[] = $filters['infrastructure_status'];
            }

            $sql .= " ORDER BY area_name ASC";

            $results = $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$result) {
                $result['boundary_coordinates'] = json_decode($result['boundary_coordinates'], true);
                $result['available_providers'] = json_decode($result['available_providers'], true);
            }

            return $results;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getServiceRequests($filters = []) {
        try {
            $sql = "SELECT tsr.*, tsub.subscriber_name, tsrv.service_name
                    FROM telecom_service_requests tsr
                    LEFT JOIN telecom_subscribers tsub ON tsr.subscriber_id = tsub.id
                    LEFT JOIN telecom_services tsrv ON tsr.service_id = tsrv.id";
            $params = [];

            if (!empty($filters['request_status'])) {
                $sql .= " WHERE tsr.request_status = ?";
                $params[] = $filters['request_status'];
            }

            if (!empty($filters['request_type'])) {
                $whereClause = empty($params) ? " WHERE" : " AND";
                $sql .= "$whereClause tsr.request_type = ?";
                $params[] = $filters['request_type'];
            }

            $sql .= " ORDER BY tsr.created_at DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getNetworkInfrastructure($filters = []) {
        try {
            $sql = "SELECT * FROM telecom_network_infrastructure";
            $params = [];

            if (!empty($filters['infrastructure_type'])) {
                $sql .= " WHERE infrastructure_type = ?";
                $params[] = $filters['infrastructure_type'];
            }

            if (!empty($filters['infrastructure_status'])) {
                $whereClause = empty($params) ? " WHERE" : " AND";
                $sql .= "$whereClause infrastructure_status = ?";
                $params[] = $filters['infrastructure_status'];
            }

            $sql .= " ORDER BY infrastructure_name ASC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getComplianceStatus() {
        try {
            $sql = "SELECT * FROM telecom_regulatory_compliance
                    ORDER BY effective_date DESC";
            return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/config/module_config.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [
            'database_table_prefix' => 'telecom_',
            'upload_path' => __DIR__ . '/uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_email' => 'admin@example.com',
            'currency' => 'USD',
            'default_service_rates' => [
                'internet' => ['base' => 50.00, 'per_mbps' => 5.00],
                'phone' => ['base' => 25.00, 'per_minute' => 0.10]
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

<?php
/**
 * Public Transportation Module
 * Handles bus/train services, fare collection, and route planning
 */

require_once __DIR__ . '/../ServiceModule.php';

class PublicTransportationModule extends ServiceModule {
    private $db;
    private $config;

    public function __construct($db = null) {
        parent::__construct();
        $this->db = $db ?: new Database();
        $this->config = $this->loadConfig();
    }

    public function getModuleInfo() {
        return [
            'name' => 'Public Transportation Module',
            'version' => '1.0.0',
            'description' => 'Comprehensive public transportation management system including route planning, fare collection, vehicle tracking, passenger services, and transportation infrastructure management',
            'dependencies' => ['FinancialManagement', 'IdentityServices', 'Procurement'],
            'permissions' => [
                'transport.view' => 'View transportation services and schedules',
                'transport.book' => 'Book transportation tickets',
                'transport.manage' => 'Manage transportation operations',
                'transport.admin' => 'Administrative functions for transportation'
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
            CREATE TABLE IF NOT EXISTS transport_routes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                route_code VARCHAR(20) UNIQUE NOT NULL,
                route_name VARCHAR(200) NOT NULL,
                route_description TEXT,

                -- Route Details
                transport_type ENUM('bus', 'train', 'tram', 'metro', 'ferry', 'other') DEFAULT 'bus',
                route_category ENUM('urban', 'intercity', 'regional', 'express', 'tourist') DEFAULT 'urban',

                -- Geographic Information
                origin_station VARCHAR(100) NOT NULL,
                destination_station VARCHAR(100) NOT NULL,
                total_distance DECIMAL(8,2), -- in kilometers
                estimated_duration INT, -- in minutes

                -- Route Path
                route_path TEXT, -- JSON array of coordinates/stops
                intermediate_stops TEXT, -- JSON array of stop details

                -- Schedule Information
                operating_hours_start TIME,
                operating_hours_end TIME,
                frequency_minutes INT DEFAULT 30,

                -- Service Details
                service_status ENUM('active', 'inactive', 'maintenance', 'seasonal') DEFAULT 'active',
                wheelchair_accessible BOOLEAN DEFAULT TRUE,
                bike_allowed BOOLEAN DEFAULT TRUE,

                -- Fare Information
                base_fare DECIMAL(6,2),
                fare_zones TEXT, -- JSON array of fare zones

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_route_code (route_code),
                INDEX idx_transport_type (transport_type),
                INDEX idx_origin_station (origin_station),
                INDEX idx_destination_station (destination_station),
                INDEX idx_service_status (service_status)
            );

            CREATE TABLE IF NOT EXISTS transport_schedules (
                id INT PRIMARY KEY AUTO_INCREMENT,
                schedule_code VARCHAR(20) UNIQUE NOT NULL,
                route_id INT NOT NULL,

                -- Schedule Details
                schedule_date DATE NOT NULL,
                departure_time TIME NOT NULL,
                arrival_time TIME NOT NULL,
                vehicle_id VARCHAR(20),

                -- Capacity and Booking
                total_capacity INT NOT NULL,
                available_seats INT NOT NULL,
                booked_seats INT DEFAULT 0,

                -- Status
                schedule_status ENUM('scheduled', 'departed', 'in_transit', 'arrived', 'cancelled', 'delayed') DEFAULT 'scheduled',
                delay_minutes INT DEFAULT 0,
                delay_reason VARCHAR(255),

                -- Real-time Tracking
                current_location_lat DECIMAL(10,8),
                current_location_lng DECIMAL(11,8),
                last_update TIMESTAMP,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE,
                INDEX idx_schedule_code (schedule_code),
                INDEX idx_route_id (route_id),
                INDEX idx_schedule_date (schedule_date),
                INDEX idx_departure_time (departure_time),
                INDEX idx_schedule_status (schedule_status)
            );

            CREATE TABLE IF NOT EXISTS transport_tickets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ticket_number VARCHAR(30) UNIQUE NOT NULL,
                passenger_id INT,

                -- Ticket Details
                route_id INT NOT NULL,
                schedule_id INT NOT NULL,
                ticket_type ENUM('single', 'return', 'season', 'multi_ride', 'group') DEFAULT 'single',

                -- Passenger Information
                passenger_name VARCHAR(200) NOT NULL,
                passenger_email VARCHAR(255),
                passenger_phone VARCHAR(20),
                passenger_age_group ENUM('adult', 'child', 'senior', 'student', 'disabled') DEFAULT 'adult',

                -- Journey Details
                boarding_station VARCHAR(100),
                alighting_station VARCHAR(100),
                seat_number VARCHAR(10),
                journey_date DATE NOT NULL,

                -- Fare Information
                base_fare DECIMAL(6,2) NOT NULL,
                discounts DECIMAL(6,2) DEFAULT 0,
                taxes DECIMAL(6,2) DEFAULT 0,
                total_amount DECIMAL(6,2) NOT NULL,

                -- Payment and Status
                payment_status ENUM('pending', 'paid', 'refunded', 'cancelled') DEFAULT 'pending',
                payment_method VARCHAR(50),
                payment_reference VARCHAR(100),

                -- Ticket Status
                ticket_status ENUM('issued', 'used', 'expired', 'cancelled', 'refunded') DEFAULT 'issued',
                issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expiry_date DATE,
                used_date TIMESTAMP,

                -- QR Code and Validation
                qr_code VARCHAR(255),
                validation_code VARCHAR(50),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (route_id) REFERENCES transport_routes(id),
                FOREIGN KEY (schedule_id) REFERENCES transport_schedules(id),
                INDEX idx_ticket_number (ticket_number),
                INDEX idx_passenger_id (passenger_id),
                INDEX idx_route_id (route_id),
                INDEX idx_schedule_id (schedule_id),
                INDEX idx_journey_date (journey_date),
                INDEX idx_ticket_status (ticket_status),
                INDEX idx_payment_status (payment_status)
            );

            CREATE TABLE IF NOT EXISTS transport_vehicles (
                id INT PRIMARY KEY AUTO_INCREMENT,
                vehicle_code VARCHAR(20) UNIQUE NOT NULL,
                vehicle_registration VARCHAR(20) UNIQUE NOT NULL,

                -- Vehicle Details
                vehicle_type ENUM('bus', 'train', 'tram', 'metro', 'ferry', 'other') DEFAULT 'bus',
                manufacturer VARCHAR(100),
                model VARCHAR(100),
                year_manufactured YEAR,

                -- Capacity and Specifications
                passenger_capacity INT NOT NULL,
                standing_capacity INT DEFAULT 0,
                wheelchair_spaces INT DEFAULT 0,
                bike_racks INT DEFAULT 0,

                -- Technical Details
                fuel_type ENUM('diesel', 'electric', 'hybrid', 'hydrogen', 'other') DEFAULT 'diesel',
                engine_capacity VARCHAR(50),
                transmission_type VARCHAR(50),

                -- Status and Maintenance
                vehicle_status ENUM('active', 'maintenance', 'out_of_service', 'retired') DEFAULT 'active',
                last_maintenance_date DATE,
                next_maintenance_date DATE,
                mileage INT DEFAULT 0,

                -- Location and Assignment
                current_location VARCHAR(100),
                assigned_route VARCHAR(20),
                depot VARCHAR(100),

                -- Features and Amenities
                amenities TEXT, -- JSON array of amenities
                accessibility_features TEXT, -- JSON array of accessibility features

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_vehicle_code (vehicle_code),
                INDEX idx_vehicle_registration (vehicle_registration),
                INDEX idx_vehicle_type (vehicle_type),
                INDEX idx_vehicle_status (vehicle_status),
                INDEX idx_assigned_route (assigned_route)
            );

            CREATE TABLE IF NOT EXISTS transport_stations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                station_code VARCHAR(20) UNIQUE NOT NULL,
                station_name VARCHAR(200) NOT NULL,

                -- Location Details
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                address VARCHAR(255),
                city VARCHAR(100) NOT NULL,
                state_province VARCHAR(100),
                postal_code VARCHAR(20),

                -- Station Details
                station_type ENUM('bus_stop', 'train_station', 'tram_stop', 'metro_station', 'ferry_terminal', 'other') DEFAULT 'bus_stop',
                platform_count INT DEFAULT 1,
                station_status ENUM('active', 'inactive', 'under_construction', 'closed') DEFAULT 'active',

                -- Facilities and Services
                facilities TEXT, -- JSON array of facilities
                accessibility_features TEXT, -- JSON array of accessibility features
                parking_available BOOLEAN DEFAULT FALSE,
                parking_spaces INT DEFAULT 0,

                -- Operational Information
                operating_hours_start TIME,
                operating_hours_end TIME,
                ticket_office BOOLEAN DEFAULT TRUE,
                vending_machines BOOLEAN DEFAULT TRUE,

                -- Connectivity
                connected_routes TEXT, -- JSON array of route codes
                nearby_attractions TEXT, -- JSON array of nearby places

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_station_code (station_code),
                INDEX idx_station_type (station_type),
                INDEX idx_city (city),
                INDEX idx_station_status (station_status)
            );

            CREATE TABLE IF NOT EXISTS transport_fare_zones (
                id INT PRIMARY KEY AUTO_INCREMENT,
                zone_code VARCHAR(20) UNIQUE NOT NULL,
                zone_name VARCHAR(100) NOT NULL,

                -- Zone Details
                zone_type ENUM('distance_based', 'zone_based', 'flat_rate') DEFAULT 'zone_based',
                zone_number INT,

                -- Geographic Boundaries
                boundary_coordinates TEXT, -- JSON polygon coordinates
                center_latitude DECIMAL(10,8),
                center_longitude DECIMAL(11,8),

                -- Fare Information
                base_fare DECIMAL(6,2),
                peak_hour_multiplier DECIMAL(3,2) DEFAULT 1.0,
                off_peak_multiplier DECIMAL(3,2) DEFAULT 1.0,

                -- Zone Status
                zone_status ENUM('active', 'inactive', 'proposed') DEFAULT 'active',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_zone_code (zone_code),
                INDEX idx_zone_number (zone_number),
                INDEX idx_zone_status (zone_status)
            );

            CREATE TABLE IF NOT EXISTS transport_passenger_feedback (
                id INT PRIMARY KEY AUTO_INCREMENT,
                feedback_code VARCHAR(20) UNIQUE NOT NULL,
                ticket_id INT,

                -- Feedback Details
                passenger_name VARCHAR(200),
                passenger_email VARCHAR(255),
                journey_date DATE,
                route_id INT,

                -- Ratings
                overall_rating DECIMAL(3,2), -- 1-5 scale
                punctuality_rating DECIMAL(3,2),
                cleanliness_rating DECIMAL(3,2),
                comfort_rating DECIMAL(3,2),
                staff_rating DECIMAL(3,2),

                -- Comments
                positive_comments TEXT,
                negative_comments TEXT,
                suggestions TEXT,

                -- Feedback Status
                feedback_status ENUM('submitted', 'reviewed', 'addressed', 'closed') DEFAULT 'submitted',
                review_date DATE,
                response TEXT,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (ticket_id) REFERENCES transport_tickets(id),
                FOREIGN KEY (route_id) REFERENCES transport_routes(id),
                INDEX idx_feedback_code (feedback_code),
                INDEX idx_ticket_id (ticket_id),
                INDEX idx_route_id (route_id),
                INDEX idx_feedback_status (feedback_status),
                INDEX idx_overall_rating (overall_rating)
            );

            CREATE TABLE IF NOT EXISTS transport_incidents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                incident_code VARCHAR(20) UNIQUE NOT NULL,
                incident_type ENUM('accident', 'mechanical_failure', 'passenger_incident', 'security_issue', 'delay', 'other') NOT NULL,

                -- Incident Details
                incident_description TEXT NOT NULL,
                severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',

                -- Location and Time
                incident_date DATE NOT NULL,
                incident_time TIME NOT NULL,
                location VARCHAR(255),
                route_id INT,
                vehicle_id VARCHAR(20),

                -- Impact Assessment
                passengers_affected INT DEFAULT 0,
                delay_minutes INT DEFAULT 0,
                service_disruption BOOLEAN DEFAULT FALSE,

                -- Response and Resolution
                reported_by VARCHAR(100),
                response_time_minutes INT,
                resolution_description TEXT,
                resolution_date DATE,

                -- Investigation
                investigation_required BOOLEAN DEFAULT FALSE,
                investigation_findings TEXT,
                preventive_measures TEXT,

                -- Status
                incident_status ENUM('reported', 'investigating', 'resolved', 'closed') DEFAULT 'reported',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (route_id) REFERENCES transport_routes(id),
                INDEX idx_incident_code (incident_code),
                INDEX idx_incident_type (incident_type),
                INDEX idx_incident_date (incident_date),
                INDEX idx_severity_level (severity_level),
                INDEX idx_incident_status (incident_status)
            );
        ";

        $this->db->query($sql);
    }

    private function setupWorkflows() {
        // Setup ticket booking workflow
        $ticketBookingWorkflow = [
            'name' => 'Ticket Booking and Payment Workflow',
            'description' => 'Complete workflow for transportation ticket booking and payment processing',
            'steps' => [
                [
                    'name' => 'Route and Schedule Selection',
                    'type' => 'user_task',
                    'assignee' => 'passenger',
                    'form' => 'route_selection_form'
                ],
                [
                    'name' => 'Passenger Information',
                    'type' => 'user_task',
                    'assignee' => 'passenger',
                    'form' => 'passenger_info_form'
                ],
                [
                    'name' => 'Fare Calculation',
                    'type' => 'service_task',
                    'service' => 'fare_calculation_service'
                ],
                [
                    'name' => 'Payment Processing',
                    'type' => 'service_task',
                    'service' => 'payment_processing_service'
                ],
                [
                    'name' => 'Ticket Generation',
                    'type' => 'service_task',
                    'service' => 'ticket_generation_service'
                ],
                [
                    'name' => 'Confirmation and Delivery',
                    'type' => 'user_task',
                    'assignee' => 'passenger',
                    'form' => 'booking_confirmation_form'
                ]
            ]
        ];

        // Save workflow configurations
        file_put_contents(__DIR__ . '/config/transport_workflow.json', json_encode($ticketBookingWorkflow, JSON_PRETTY_PRINT));
    }

    private function createDirectories() {
        $directories = [
            __DIR__ . '/uploads/ticket_documents',
            __DIR__ . '/uploads/route_maps',
            __DIR__ . '/uploads/vehicle_images',
            __DIR__ . '/uploads/station_photos',
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
            'transport_incidents',
            'transport_passenger_feedback',
            'transport_fare_zones',
            'transport_stations',
            'transport_vehicles',
            'transport_tickets',
            'transport_schedules',
            'transport_routes'
        ];

        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS $table");
        }
    }

    // API Methods
    public function createTransportRoute($data) {
        try {
            $this->validateRouteData($data);
            $routeCode = $this->generateRouteCode();

            $sql = "INSERT INTO transport_routes (
                route_code, route_name, route_description, transport_type,
                route_category, origin_station, destination_station, total_distance,
                estimated_duration, route_path, intermediate_stops, operating_hours_start,
                operating_hours_end, frequency_minutes, service_status, wheelchair_accessible,
                bike_allowed, base_fare, fare_zones, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $routeId = $this->db->insert($sql, [
                $routeCode, $data['route_name'], $data['route_description'] ?? null,
                $data['transport_type'] ?? 'bus', $data['route_category'] ?? 'urban',
                $data['origin_station'], $data['destination_station'],
                $data['total_distance'] ?? null, $data['estimated_duration'] ?? null,
                json_encode($data['route_path'] ?? []), json_encode($data['intermediate_stops'] ?? []),
                $data['operating_hours_start'] ?? null, $data['operating_hours_end'] ?? null,
                $data['frequency_minutes'] ?? 30, $data['service_status'] ?? 'active',
                $data['wheelchair_accessible'] ?? true, $data['bike_allowed'] ?? true,
                $data['base_fare'] ?? null, json_encode($data['fare_zones'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'route_id' => $routeId,
                'route_code' => $routeCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTransportSchedule($data) {
        try {
            $this->validateScheduleData($data);
            $scheduleCode = $this->generateScheduleCode();

            $sql = "INSERT INTO transport_schedules (
                schedule_code, route_id, schedule_date, departure_time,
                arrival_time, vehicle_id, total_capacity, available_seats,
                schedule_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $scheduleId = $this->db->insert($sql, [
                $scheduleCode, $data['route_id'], $data['schedule_date'],
                $data['departure_time'], $data['arrival_time'],
                $data['vehicle_id'] ?? null, $data['total_capacity'],
                $data['available_seats'] ?? $data['total_capacity'],
                $data['schedule_status'] ?? 'scheduled', $data['created_by']
            ]);

            return [
                'success' => true,
                'schedule_id' => $scheduleId,
                'schedule_code' => $scheduleCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function bookTicket($data) {
        try {
            $this->validateTicketData($data);
            $ticketNumber = $this->generateTicketNumber();

            // Calculate fare
            $fareDetails = $this->calculateFare($data['route_id'], $data['passenger_age_group'], $data['journey_date']);

            $sql = "INSERT INTO transport_tickets (
                ticket_number, passenger_id, route_id, schedule_id, ticket_type,
                passenger_name, passenger_email, passenger_phone, passenger_age_group,
                boarding_station, alighting_station, journey_date, base_fare,
                discounts, taxes, total_amount, payment_status, ticket_status,
                qr_code, validation_code, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $ticketId = $this->db->insert($sql, [
                $ticketNumber, $data['passenger_id'] ?? null, $data['route_id'],
                $data['schedule_id'], $data['ticket_type'] ?? 'single',
                $data['passenger_name'], $data['passenger_email'] ?? null,
                $data['passenger_phone'] ?? null, $data['passenger_age_group'] ?? 'adult',
                $data['boarding_station'] ?? null, $data['alighting_station'] ?? null,
                $data['journey_date'], $fareDetails['base_fare'],
                $fareDetails['discounts'], $fareDetails['taxes'], $fareDetails['total_amount'],
                $data['payment_status'] ?? 'pending', $data['ticket_status'] ?? 'issued',
                $this->generateQRCode($ticketNumber), $this->generateValidationCode(),
                $data['created_by']
            ]);

            // Update available seats
            $this->updateAvailableSeats($data['schedule_id'], -1);

            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'fare_details' => $fareDetails
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validateTicket($ticketNumber, $validationCode) {
        try {
            $sql = "SELECT * FROM transport_tickets
                    WHERE ticket_number = ? AND validation_code = ?
                    AND ticket_status = 'issued'
                    AND journey_date >= CURRENT_DATE";

            $ticket = $this->db->query($sql, [$ticketNumber, $validationCode])->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                return ['success' => false, 'error' => 'Invalid ticket or ticket already used'];
            }

            // Mark ticket as used
            $this->markTicketAsUsed($ticket['id']);

            return [
                'success' => true,
                'ticket' => $ticket,
                'message' => 'Ticket validated successfully'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTransportVehicle($data) {
        try {
            $this->validateVehicleData($data);
            $vehicleCode = $this->generateVehicleCode();

            $sql = "INSERT INTO transport_vehicles (
                vehicle_code, vehicle_registration, vehicle_type, manufacturer,
                model, year_manufactured, passenger_capacity, standing_capacity,
                wheelchair_spaces, bike_racks, fuel_type, engine_capacity,
                transmission_type, vehicle_status, last_maintenance_date,
                next_maintenance_date, current_location, assigned_route,
                depot, amenities, accessibility_features, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $vehicleId = $this->db->insert($sql, [
                $vehicleCode, $data['vehicle_registration'], $data['vehicle_type'] ?? 'bus',
                $data['manufacturer'] ?? null, $data['model'] ?? null,
                $data['year_manufactured'] ?? null, $data['passenger_capacity'],
                $data['standing_capacity'] ?? 0, $data['wheelchair_spaces'] ?? 0,
                $data['bike_racks'] ?? 0, $data['fuel_type'] ?? 'diesel',
                $data['engine_capacity'] ?? null, $data['transmission_type'] ?? null,
                $data['vehicle_status'] ?? 'active', $data['last_maintenance_date'] ?? null,
                $data['next_maintenance_date'] ?? null, $data['current_location'] ?? null,
                $data['assigned_route'] ?? null, $data['depot'] ?? null,
                json_encode($data['amenities'] ?? []), json_encode($data['accessibility_features'] ?? []),
                $data['created_by']
            ]);

            return [
                'success' => true,
                'vehicle_id' => $vehicleId,
                'vehicle_code' => $vehicleCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function createTransportStation($data) {
        try {
            $this->validateStationData($data);
            $stationCode = $this->generateStationCode();

            $sql = "INSERT INTO transport_stations (
                station_code, station_name, latitude, longitude, address,
                city, state_province, postal_code, station_type, platform_count,
                station_status, facilities, accessibility_features, parking_available,
                parking_spaces, operating_hours_start, operating_hours_end,
                ticket_office, vending_machines, connected_routes, nearby_attractions, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stationId = $this->db->insert($sql, [
                $stationCode, $data['station_name'], $data['latitude'] ?? null,
                $data['longitude'] ?? null, $data['address'] ?? null,
                $data['city'], $data['state_province'] ?? null, $data['postal_code'] ?? null,
                $data['station_type'] ?? 'bus_stop', $data['platform_count'] ?? 1,
                $data['station_status'] ?? 'active', json_encode($data['facilities'] ?? []),
                json_encode($data['accessibility_features'] ?? []), $data['parking_available'] ?? false,
                $data['parking_spaces'] ?? 0, $data['operating_hours_start'] ?? null,
                $data['operating_hours_end'] ?? null, $data['ticket_office'] ?? true,
                $data['vending_machines'] ?? true, json_encode($data['connected_routes'] ?? []),
                json_encode($data['nearby_attractions'] ?? []), $data['created_by']
            ]);

            return [
                'success' => true,
                'station_id' => $stationId,
                'station_code' => $stationCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function submitPassengerFeedback($data) {
        try {
            $this->validateFeedbackData($data);
            $feedbackCode = $this->generateFeedbackCode();

            $sql = "INSERT INTO transport_passenger_feedback (
                feedback_code, ticket_id, passenger_name, passenger_email,
                journey_date, route_id, overall_rating, punctuality_rating,
                cleanliness_rating, comfort_rating, staff_rating, positive_comments,
                negative_comments, suggestions, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $feedbackId = $this->db->insert($sql, [
                $feedbackCode, $data['ticket_id'] ?? null, $data['passenger_name'] ?? null,
                $data['passenger_email'] ?? null, $data['journey_date'],
                $data['route_id'] ?? null, $data['overall_rating'],
                $data['punctuality_rating'] ?? null, $data['cleanliness_rating'] ?? null,
                $data['comfort_rating'] ?? null, $data['staff_rating'] ?? null,
                $data['positive_comments'] ?? null, $data['negative_comments'] ?? null,
                $data['suggestions'] ?? null, $data['created_by']
            ]);

            return [
                'success' => true,
                'feedback_id' => $feedbackId,
                'feedback_code' => $feedbackCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function reportIncident($data) {
        try {
            $this->validateIncidentData($data);
            $incidentCode = $this->generateIncidentCode();

            $sql = "INSERT INTO transport_incidents (
                incident_code, incident_type, incident_description, severity_level,
                incident_date, incident_time, location, route_id, vehicle_id,
                passengers_affected, delay_minutes, service_disruption, reported_by,
                incident_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $incidentId = $this->db->insert($sql, [
                $incidentCode, $data['incident_type'], $data['incident_description'],
                $data['severity_level'] ?? 'medium', $data['incident_date'],
                $data['incident_time'], $data['location'] ?? null,
                $data['route_id'] ?? null, $data['vehicle_id'] ?? null,
                $data['passengers_affected'] ?? 0, $data['delay_minutes'] ?? 0,
                $data['service_disruption'] ?? false, $data['reported_by'] ?? null,
                $data['incident_status'] ?? 'reported', $data['created_by']
            ]);

            return [
                'success' => true,
                'incident_id' => $incidentId,
                'incident_code' => $incidentCode
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Helper Methods
    private function calculateFare($routeId, $ageGroup, $journeyDate) {
        $route = $this->getRouteById($routeId);
        $baseFare = $route['base_fare'];

        // Apply age-based discounts
        $discounts = 0;
        switch ($ageGroup) {
            case 'child':
                $discounts = $baseFare * 0.5; // 50% discount for children
                break;
            case 'senior':
                $discounts = $baseFare * 0.3; // 30% discount for seniors
                break;
            case 'student':
                $discounts = $baseFare * 0.2; // 20% discount for students
                break;
            case 'disabled':
                $discounts = $baseFare * 0.5; // 50% discount for disabled
                break;
        }

        $subtotal = $baseFare - $discounts;
        $taxes = $subtotal * 0.1; // 10% tax
        $totalAmount = $subtotal + $taxes;

        return [
            'base_fare' => $baseFare,
            'discounts' => $discounts,
            'taxes' => $taxes,
            'total_amount' => $totalAmount
        ];
    }

    private function updateAvailableSeats($scheduleId, $change) {
        $sql = "UPDATE transport_schedules SET
                available_seats = available_seats + ?,
                booked_seats = booked_seats - ?
                WHERE id = ? AND available_seats + ? >= 0";

        $this->db->query($sql, [$change, $change, $scheduleId, $change]);
    }

    private function markTicketAsUsed($ticketId) {
        $sql = "UPDATE transport_tickets SET
                ticket_status = 'used',
                used_date = CURRENT_TIMESTAMP
                WHERE id = ?";

        $this->db->query($sql, [$ticketId]);
    }

    private function getRouteById($routeId) {
        $sql = "SELECT * FROM transport_routes WHERE id = ?";
        return $this->db->query($sql, [$routeId])->fetch(PDO::FETCH_ASSOC);
    }

    private function generateQRCode($ticketNumber) {
        // Generate QR code data (simplified)
        return 'QR_' . $ticketNumber . '_' . time();
    }

    private function generateValidationCode() {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }

    // Validation Methods
    private function validateRouteData($data) {
        if (empty($data['route_name'])) {
            throw new Exception('Route name is required');
        }
        if (empty($data['origin_station'])) {
            throw new Exception('Origin station is required');
        }
        if (empty($data['destination_station'])) {
            throw new Exception('Destination station is required');
        }
    }

    private function validateScheduleData($data) {
        if (empty($data['route_id'])) {
            throw new Exception('Route ID is required');
        }
        if (empty($data['schedule_date'])) {
            throw new Exception('Schedule date is required');
        }
        if (empty($data['departure_time'])) {
            throw new Exception('Departure time is required');
        }
        if (empty($data['arrival_time'])) {
            throw new Exception('Arrival time is required');
        }
        if (empty($data['total_capacity']) || $data['total_capacity'] <= 0) {
            throw new Exception('Valid total capacity is required');
        }
    }

    private function validateTicketData($data) {
        if (empty($data['route_id'])) {
            throw new Exception('Route ID is required');
        }
        if (empty($data['schedule_id'])) {
            throw new Exception('Schedule ID is required');
        }
        if (empty($data['passenger_name'])) {
            throw new Exception('Passenger name is required');
        }
        if (empty($data['journey_date'])) {
            throw new Exception('Journey date is required');
        }
    }

    private function validateVehicleData($data) {
        if (empty($data['vehicle_registration'])) {
            throw new Exception('Vehicle registration is required');
        }
        if (empty($data['passenger_capacity']) || $data['passenger_capacity'] <= 0) {
            throw new Exception('Valid passenger capacity is required');
        }
    }

    private function validateStationData($data) {
        if (empty($data['station_name'])) {
            throw new Exception('Station name is required');
        }
        if (empty($data['city'])) {
            throw new Exception('City is required');
        }
    }

    private function validateFeedbackData($data) {
        if (empty($data['journey_date'])) {
            throw new Exception('Journey date is required');
        }
        if (empty($data['overall_rating']) || $data['overall_rating'] < 1 || $data['overall_rating'] > 5) {
            throw new Exception('Valid overall rating (1-5) is required');
        }
    }

    private function validateIncidentData($data) {
        if (empty($data['incident_type'])) {
            throw new Exception('Incident type is required');
        }
        if (empty($data['incident_description'])) {
            throw new Exception('Incident description is required');
        }
        if (empty($data['incident_date'])) {
            throw new Exception('Incident date is required');
        }
        if (empty($data['incident_time'])) {
            throw new Exception('Incident time is required');
        }
    }

    // Code Generation Methods
    private function generateRouteCode() {
        return 'RTE-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateScheduleCode() {
        return 'SCH-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateTicketNumber() {
        return 'TKT-' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateVehicleCode() {
        return 'VEH-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateStationCode() {
        return 'STN-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateFeedbackCode() {
        return 'FBK-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function generateIncidentCode() {
        return 'INC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Additional API Methods
    public function getAvailableRoutes($filters = []) {
        try {
            $sql = "SELECT * FROM transport_routes WHERE service_status = 'active'";
            $params = [];

            if (!empty($filters['transport_type'])) {
                $sql .= " AND transport_type = ?";
                $params[] = $filters['transport_type'];
            }

            if (!empty($filters['origin_station'])) {
                $sql .= " AND origin_station = ?";
                $params[] = $filters['origin_station'];
            }

            if (!empty($filters['destination_station'])) {
                $sql .= " AND destination_station = ?";
                $params[] = $filters['destination_station'];
            }

            $sql .= " ORDER BY route_name ASC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getScheduleByRoute($routeId, $date) {
        try {
            $sql = "SELECT ts.*, tr.route_name, tr.origin_station, tr.destination_station
                    FROM transport_schedules ts
                    JOIN transport_routes tr ON ts.route_id = tr.id
                    WHERE ts.route_id = ? AND ts.schedule_date = ?
                    AND ts.schedule_status IN ('scheduled', 'departed', 'in_transit')
                    ORDER BY ts.departure_time ASC";

            return $this->db->query($sql, [$routeId, $date])->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getTicketDetails($ticketNumber) {
        try {
            $sql = "SELECT tt.*, tr.route_name, tr.origin_station, tr.destination_station,
                           ts.departure_time, ts.arrival_time
                    FROM transport_tickets tt
                    JOIN transport_routes tr ON tt.route_id = tr.id
                    JOIN transport_schedules ts ON tt.schedule_id = ts.id
                    WHERE tt.ticket_number = ?";

            $ticket = $this->db->query($sql, [$ticketNumber])->fetch(PDO::FETCH_ASSOC);

            if ($ticket) {
                return $ticket;
            } else {
                return ['success' => false, 'error' => 'Ticket not found'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getTransportStations($filters = []) {
        try {
            $sql = "SELECT * FROM transport_stations WHERE station_status = 'active'";
            $params = [];

            if (!empty($filters['station_type'])) {
                $sql .= " AND station_type = ?";
                $params[] = $filters['station_type'];
            }

            if (!empty($filters['city'])) {
                $sql .= " AND city = ?";
                $params[] = $filters['city'];
            }

            $sql .= " ORDER BY station_name ASC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getVehicleStatus($vehicleCode) {
        try {
            $sql = "SELECT * FROM transport_vehicles WHERE vehicle_code = ?";
            return $this->db->query($sql, [$vehicleCode])->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPassengerFeedback($filters = []) {
        try {
            $sql = "SELECT tpf.*, tr.route_name
                    FROM transport_passenger_feedback tpf
                    LEFT JOIN transport_routes tr ON tpf.route_id = tr.id";
            $params = [];

            if (!empty($filters['route_id'])) {
                $sql .= " WHERE tpf.route_id = ?";
                $params[] = $filters['route_id'];
            }

            if (!empty($filters['min_rating'])) {
                $whereClause = empty($params) ? " WHERE" : " AND";
                $sql .= "$whereClause tpf.overall_rating >= ?";
                $params[] = $filters['min_rating'];
            }

            $sql .= " ORDER BY tpf.created_at DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getIncidents($filters = []) {
        try {
            $sql = "SELECT ti.*, tr.route_name
                    FROM transport_incidents ti
                    LEFT JOIN transport_routes tr ON ti.route_id = tr.id";
            $params = [];

            if (!empty($filters['incident_type'])) {
                $sql .= " WHERE ti.incident_type = ?";
                $params[] = $filters['incident_type'];
            }

            if (!empty($filters['severity_level'])) {
                $whereClause = empty($params) ? " WHERE" : " AND";
                $sql .= "$whereClause ti.severity_level = ?";
                $params[] = $filters['severity_level'];
            }

            $sql .= " ORDER BY ti.incident_date DESC, ti.incident_time DESC";

            return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

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
            'database_table_prefix' => 'transport_',
            'upload_path' => __DIR__ . '/uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'notification_email' => 'admin@example.com',
            'currency' => 'USD',
            'default_fares' => [
                'adult' => 5.00,
                'child' => 2.50,
                'senior' => 3.50,
                'student' => 4.00,
                'disabled' => 2.50
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

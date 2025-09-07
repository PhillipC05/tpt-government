<?php
/**
 * TPT Government Platform - Traffic & Parking Module
 *
 * Comprehensive traffic citation and parking violation management system
 * supporting ticket issuance, appeals, payment processing, and court integration
 */

namespace Modules\TrafficParking;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class TrafficParkingModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Traffic & Parking',
        'version' => '2.2.0',
        'description' => 'Comprehensive traffic citation and parking violation management system',
        'author' => 'TPT Government Platform',
        'category' => 'public_infrastructure',
        'dependencies' => ['database', 'workflow', 'payment', 'notification']
    ];

    /**
     * Module dependencies
     */
    protected array $dependencies = [
        ['name' => 'Database', 'version' => '>=1.0.0'],
        ['name' => 'WorkflowEngine', 'version' => '>=1.0.0'],
        ['name' => 'PaymentGateway', 'version' => '>=1.0.0'],
        ['name' => 'NotificationManager', 'version' => '>=1.0.0']
    ];

    /**
     * Module permissions
     */
    protected array $permissions = [
        'traffic_tickets.view' => 'View traffic tickets and citations',
        'traffic_tickets.create' => 'Create new traffic tickets',
        'traffic_tickets.edit' => 'Edit traffic ticket information',
        'traffic_tickets.approve' => 'Approve traffic ticket payments',
        'traffic_tickets.appeal' => 'Process traffic ticket appeals',
        'traffic_tickets.settle' => 'Settle traffic ticket disputes',
        'parking_violations.view' => 'View parking violations',
        'parking_violations.create' => 'Create parking violation tickets',
        'parking_violations.edit' => 'Edit parking violation information',
        'parking_violations.approve' => 'Approve parking violation payments',
        'court_integration.view' => 'View court integration data',
        'court_integration.sync' => 'Sync data with court systems'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'traffic_tickets' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'license_plate' => 'VARCHAR(20) NOT NULL',
            'vehicle_make' => 'VARCHAR(50)',
            'vehicle_model' => 'VARCHAR(50)',
            'vehicle_year' => 'INT',
            'violation_type' => 'VARCHAR(100) NOT NULL',
            'violation_code' => 'VARCHAR(10) NOT NULL',
            'violation_description' => 'TEXT',
            'location' => 'VARCHAR(255)',
            'date_time' => 'DATETIME NOT NULL',
            'officer_id' => 'INT NOT NULL',
            'officer_name' => 'VARCHAR(100)',
            'fine_amount' => 'DECIMAL(8,2) NOT NULL',
            'points_assessment' => 'INT DEFAULT 0',
            'status' => "ENUM('issued','paid','appealed','dismissed','court_pending','court_ordered') DEFAULT 'issued'",
            'due_date' => 'DATETIME NOT NULL',
            'payment_date' => 'DATETIME NULL',
            'appeal_deadline' => 'DATETIME NOT NULL',
            'court_date' => 'DATETIME NULL',
            'court_outcome' => 'VARCHAR(100)',
            'notes' => 'TEXT',
            'evidence_photos' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'parking_violations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'violation_number' => 'VARCHAR(20) UNIQUE NOT NULL',
            'license_plate' => 'VARCHAR(20) NOT NULL',
            'violation_type' => 'VARCHAR(100) NOT NULL',
            'violation_code' => 'VARCHAR(10) NOT NULL',
            'location' => 'VARCHAR(255) NOT NULL',
            'zone_type' => 'VARCHAR(50)',
            'date_time' => 'DATETIME NOT NULL',
            'officer_id' => 'INT',
            'fine_amount' => 'DECIMAL(8,2) NOT NULL',
            'status' => "ENUM('issued','paid','appealed','dismissed','towed') DEFAULT 'issued'",
            'due_date' => 'DATETIME NOT NULL',
            'payment_date' => 'DATETIME NULL',
            'appeal_deadline' => 'DATETIME NOT NULL',
            'tow_date' => 'DATETIME NULL',
            'tow_location' => 'VARCHAR(255)',
            'tow_fee' => 'DECIMAL(8,2) DEFAULT 0.00',
            'evidence_photos' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'ticket_appeals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_number' => 'VARCHAR(20) NOT NULL',
            'ticket_type' => "ENUM('traffic','parking') NOT NULL",
            'appellant_name' => 'VARCHAR(100) NOT NULL',
            'appellant_email' => 'VARCHAR(100)',
            'appellant_phone' => 'VARCHAR(20)',
            'appeal_reason' => 'TEXT NOT NULL',
            'appeal_evidence' => 'JSON',
            'status' => "ENUM('submitted','under_review','approved','denied','hearing_scheduled') DEFAULT 'submitted'",
            'hearing_date' => 'DATETIME NULL',
            'decision_date' => 'DATETIME NULL',
            'decision' => 'TEXT',
            'reviewed_by' => 'INT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'driver_license_points' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_number' => 'VARCHAR(20) NOT NULL',
            'driver_name' => 'VARCHAR(100) NOT NULL',
            'total_points' => 'INT DEFAULT 0',
            'suspension_threshold' => 'INT DEFAULT 12',
            'last_violation_date' => 'DATETIME NULL',
            'suspension_date' => 'DATETIME NULL',
            'reinstatement_date' => 'DATETIME NULL',
            'status' => "ENUM('active','suspended','revoked') DEFAULT 'active'",
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'court_integrations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_number' => 'VARCHAR(20) NOT NULL',
            'ticket_type' => "ENUM('traffic','parking') NOT NULL",
            'court_case_number' => 'VARCHAR(50)',
            'court_name' => 'VARCHAR(100)',
            'judge_name' => 'VARCHAR(100)',
            'hearing_date' => 'DATETIME',
            'outcome' => 'VARCHAR(100)',
            'fine_reduction' => 'DECIMAL(8,2) DEFAULT 0.00',
            'points_reduction' => 'INT DEFAULT 0',
            'sync_status' => "ENUM('pending','synced','failed') DEFAULT 'pending'",
            'last_sync' => 'DATETIME NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'GET',
            'path' => '/api/traffic-tickets',
            'handler' => 'getTrafficTickets',
            'auth' => true,
            'permissions' => ['traffic_tickets.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/traffic-tickets',
            'handler' => 'createTrafficTicket',
            'auth' => true,
            'permissions' => ['traffic_tickets.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/traffic-tickets/{id}',
            'handler' => 'getTrafficTicket',
            'auth' => true,
            'permissions' => ['traffic_tickets.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/traffic-tickets/{id}/appeal',
            'handler' => 'appealTrafficTicket',
            'auth' => true,
            'permissions' => ['traffic_tickets.appeal']
        ],
        [
            'method' => 'GET',
            'path' => '/api/parking-violations',
            'handler' => 'getParkingViolations',
            'auth' => true,
            'permissions' => ['parking_violations.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/parking-violations',
            'handler' => 'createParkingViolation',
            'auth' => true,
            'permissions' => ['parking_violations.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/driver-points/{license}',
            'handler' => 'getDriverPoints',
            'auth' => true,
            'permissions' => ['traffic_tickets.view']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'traffic_ticket_process' => [
            'name' => 'Traffic Ticket Processing',
            'description' => 'Workflow for processing traffic ticket citations',
            'steps' => [
                'issued' => ['name' => 'Ticket Issued', 'next' => ['paid', 'appealed', 'court_pending']],
                'paid' => ['name' => 'Ticket Paid', 'next' => null],
                'appealed' => ['name' => 'Appeal Submitted', 'next' => ['appeal_approved', 'appeal_denied', 'hearing_scheduled']],
                'appeal_approved' => ['name' => 'Appeal Approved', 'next' => null],
                'appeal_denied' => ['name' => 'Appeal Denied', 'next' => 'court_pending'],
                'hearing_scheduled' => ['name' => 'Hearing Scheduled', 'next' => ['court_dismissed', 'court_reduced', 'court_upheld']],
                'court_pending' => ['name' => 'Court Pending', 'next' => ['court_dismissed', 'court_reduced', 'court_upheld']],
                'court_dismissed' => ['name' => 'Court Dismissed', 'next' => null],
                'court_reduced' => ['name' => 'Court Reduced Fine', 'next' => 'paid'],
                'court_upheld' => ['name' => 'Court Upheld', 'next' => 'paid']
            ]
        ],
        'parking_violation_process' => [
            'name' => 'Parking Violation Processing',
            'description' => 'Workflow for processing parking violation tickets',
            'steps' => [
                'issued' => ['name' => 'Violation Issued', 'next' => ['paid', 'appealed', 'towed']],
                'paid' => ['name' => 'Violation Paid', 'next' => null],
                'appealed' => ['name' => 'Appeal Submitted', 'next' => ['appeal_approved', 'appeal_denied']],
                'appeal_approved' => ['name' => 'Appeal Approved', 'next' => null],
                'appeal_denied' => ['name' => 'Appeal Denied', 'next' => 'paid'],
                'towed' => ['name' => 'Vehicle Towed', 'next' => ['paid', 'appealed']]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'traffic_ticket' => [
            'name' => 'Traffic Ticket Citation',
            'fields' => [
                'license_plate' => ['type' => 'text', 'required' => true, 'label' => 'License Plate'],
                'vehicle_make' => ['type' => 'text', 'required' => true, 'label' => 'Vehicle Make'],
                'vehicle_model' => ['type' => 'text', 'required' => true, 'label' => 'Vehicle Model'],
                'vehicle_year' => ['type' => 'number', 'required' => true, 'label' => 'Vehicle Year'],
                'violation_type' => ['type' => 'select', 'required' => true, 'label' => 'Violation Type'],
                'violation_code' => ['type' => 'text', 'required' => true, 'label' => 'Violation Code'],
                'location' => ['type' => 'text', 'required' => true, 'label' => 'Location'],
                'date_time' => ['type' => 'datetime-local', 'required' => true, 'label' => 'Date & Time'],
                'officer_name' => ['type' => 'text', 'required' => true, 'label' => 'Officer Name'],
                'fine_amount' => ['type' => 'number', 'required' => true, 'label' => 'Fine Amount', 'step' => '0.01'],
                'points_assessment' => ['type' => 'number', 'required' => true, 'label' => 'Points Assessment'],
                'notes' => ['type' => 'textarea', 'required' => false, 'label' => 'Notes']
            ]
        ],
        'parking_violation' => [
            'name' => 'Parking Violation Ticket',
            'fields' => [
                'license_plate' => ['type' => 'text', 'required' => true, 'label' => 'License Plate'],
                'violation_type' => ['type' => 'select', 'required' => true, 'label' => 'Violation Type'],
                'violation_code' => ['type' => 'text', 'required' => true, 'label' => 'Violation Code'],
                'location' => ['type' => 'text', 'required' => true, 'label' => 'Location'],
                'zone_type' => ['type' => 'select', 'required' => true, 'label' => 'Parking Zone Type'],
                'date_time' => ['type' => 'datetime-local', 'required' => true, 'label' => 'Date & Time'],
                'fine_amount' => ['type' => 'number', 'required' => true, 'label' => 'Fine Amount', 'step' => '0.01'],
                'notes' => ['type' => 'textarea', 'required' => false, 'label' => 'Notes']
            ]
        ],
        'ticket_appeal' => [
            'name' => 'Traffic Ticket Appeal',
            'fields' => [
                'ticket_number' => ['type' => 'text', 'required' => true, 'label' => 'Ticket Number'],
                'appellant_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                'appellant_email' => ['type' => 'email', 'required' => true, 'label' => 'Email Address'],
                'appellant_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Phone Number'],
                'appeal_reason' => ['type' => 'textarea', 'required' => true, 'label' => 'Reason for Appeal'],
                'evidence_description' => ['type' => 'textarea', 'required' => false, 'label' => 'Evidence Description']
            ],
            'documents' => [
                'appeal_evidence' => ['required' => false, 'label' => 'Supporting Evidence (Photos/Documents)']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'traffic_ticket_summary' => [
            'name' => 'Traffic Ticket Summary Report',
            'description' => 'Summary of traffic ticket citations and payments',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'violation_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'ticket_number', 'license_plate', 'violation_type', 'fine_amount',
                'status', 'date_time', 'payment_date'
            ]
        ],
        'parking_violation_summary' => [
            'name' => 'Parking Violation Summary Report',
            'description' => 'Summary of parking violation tickets and payments',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'violation_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'violation_number', 'license_plate', 'violation_type', 'fine_amount',
                'status', 'date_time', 'payment_date'
            ]
        ],
        'appeal_statistics' => [
            'name' => 'Appeal Statistics Report',
            'description' => 'Statistics on ticket appeals and outcomes',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'appeal_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'appeal_type', 'total_appeals', 'approved_appeals', 'denied_appeals',
                'success_rate', 'average_processing_time'
            ]
        ],
        'revenue_report' => [
            'name' => 'Traffic Revenue Report',
            'description' => 'Revenue generated from traffic tickets and parking violations',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'ticket_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'ticket_type', 'total_tickets', 'total_revenue', 'paid_amount',
                'outstanding_amount', 'collection_rate'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'ticket_issued' => [
            'name' => 'Traffic Ticket Issued',
            'template' => 'A traffic ticket has been issued for license plate {license_plate}. Fine: ${fine_amount}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['ticket_created']
        ],
        'ticket_paid' => [
            'name' => 'Traffic Ticket Paid',
            'template' => 'Your traffic ticket {ticket_number} has been paid successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['ticket_paid']
        ],
        'ticket_appeal_submitted' => [
            'name' => 'Ticket Appeal Submitted',
            'template' => 'Your appeal for ticket {ticket_number} has been submitted and is under review.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appeal_submitted']
        ],
        'appeal_approved' => [
            'name' => 'Appeal Approved',
            'template' => 'Your appeal for ticket {ticket_number} has been approved.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appeal_approved']
        ],
        'appeal_denied' => [
            'name' => 'Appeal Denied',
            'template' => 'Your appeal for ticket {ticket_number} has been denied.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appeal_denied']
        ],
        'court_hearing_scheduled' => [
            'name' => 'Court Hearing Scheduled',
            'template' => 'A court hearing has been scheduled for ticket {ticket_number} on {hearing_date}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['hearing_scheduled']
        ],
        'license_suspension_warning' => [
            'name' => 'License Suspension Warning',
            'template' => 'Warning: Your license may be suspended due to excessive points. Current points: {total_points}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['points_threshold_warning']
        ]
    ];

    /**
     * Violation codes and fines
     */
    private array $violationCodes = [];

    /**
     * Court integration settings
     */
    private array $courtSettings = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Get module metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'appeal_deadline_days' => 30,
            'payment_grace_period_days' => 14,
            'court_escalation_threshold' => 90, // days
            'license_suspension_threshold' => 12, // points
            'max_upload_size' => 10485760, // 10MB
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'pdf'],
            'payment_gateway' => 'stripe',
            'court_integration_enabled' => false,
            'notification_settings' => [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true
            ]
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeViolationCodes();
        $this->initializeCourtSettings();
    }

    /**
     * Initialize violation codes and fines
     */
    private function initializeViolationCodes(): void
    {
        $this->violationCodes = [
            // Traffic violations
            'SPD001' => [
                'type' => 'traffic',
                'description' => 'Speeding (1-10 mph over limit)',
                'fine_amount' => 150.00,
                'points' => 2
            ],
            'SPD002' => [
                'type' => 'traffic',
                'description' => 'Speeding (11-20 mph over limit)',
                'fine_amount' => 250.00,
                'points' => 4
            ],
            'SPD003' => [
                'type' => 'traffic',
                'description' => 'Speeding (21+ mph over limit)',
                'fine_amount' => 500.00,
                'points' => 6
            ],
            'RLT001' => [
                'type' => 'traffic',
                'description' => 'Running Red Light',
                'fine_amount' => 200.00,
                'points' => 3
            ],
            'STP001' => [
                'type' => 'traffic',
                'description' => 'Failure to Stop at Stop Sign',
                'fine_amount' => 100.00,
                'points' => 2
            ],
            'DUI001' => [
                'type' => 'traffic',
                'description' => 'Driving Under Influence',
                'fine_amount' => 1000.00,
                'points' => 8
            ],

            // Parking violations
            'PRK001' => [
                'type' => 'parking',
                'description' => 'Expired Parking Meter',
                'fine_amount' => 25.00,
                'points' => 0
            ],
            'PRK002' => [
                'type' => 'parking',
                'description' => 'No Parking Zone',
                'fine_amount' => 50.00,
                'points' => 0
            ],
            'PRK003' => [
                'type' => 'parking',
                'description' => 'Overtime Parking',
                'fine_amount' => 35.00,
                'points' => 0
            ],
            'PRK004' => [
                'type' => 'parking',
                'description' => 'Handicapped Space Violation',
                'fine_amount' => 250.00,
                'points' => 0
            ],
            'PRK005' => [
                'type' => 'parking',
                'description' => 'Blocking Driveway',
                'fine_amount' => 75.00,
                'points' => 0
            ]
        ];
    }

    /**
     * Initialize court integration settings
     */
    private function initializeCourtSettings(): void
    {
        $this->courtSettings = [
            'enabled' => false,
            'court_system_url' => '',
            'api_key' => '',
            'sync_interval' => 3600, // 1 hour
            'auto_escalation' => true,
            'escalation_days' => 90,
            'court_mapping' => [
                'traffic_court' => 'Traffic Violations Court',
                'municipal_court' => 'Municipal Court',
                'district_court' => 'District Court'
            ]
        ];
    }

    /**
     * Create traffic ticket
     */
    public function createTrafficTicket(array $ticketData): array
    {
        // Validate ticket data
        $validation = $this->validateTicketData($ticketData, 'traffic');
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate ticket number
        $ticketNumber = $this->generateTicketNumber('traffic');

        // Get violation details
        $violation = $this->violationCodes[$ticketData['violation_code']] ?? null;
        if (!$violation) {
            return [
                'success' => false,
                'error' => 'Invalid violation code'
            ];
        }

        // Calculate due date
        $dueDate = date('Y-m-d H:i:s', strtotime("+{$this->config['payment_grace_period_days']} days"));

        // Calculate appeal deadline
        $appealDeadline = date('Y-m-d H:i:s', strtotime("+{$this->config['appeal_deadline_days']} days"));

        // Create ticket record
        $ticket = [
            'ticket_number' => $ticketNumber,
            'license_plate' => $ticketData['license_plate'],
            'vehicle_make' => $ticketData['vehicle_make'] ?? '',
            'vehicle_model' => $ticketData['vehicle_model'] ?? '',
            'vehicle_year' => $ticketData['vehicle_year'] ?? null,
            'violation_type' => $violation['description'],
            'violation_code' => $ticketData['violation_code'],
            'violation_description' => $ticketData['notes'] ?? '',
            'location' => $ticketData['location'],
            'date_time' => $ticketData['date_time'],
            'officer_id' => $ticketData['officer_id'],
            'officer_name' => $ticketData['officer_name'],
            'fine_amount' => $violation['fine_amount'],
            'points_assessment' => $violation['points'],
            'status' => 'issued',
            'due_date' => $dueDate,
            'appeal_deadline' => $appealDeadline,
            'evidence_photos' => $ticketData['evidence_photos'] ?? [],
            'notes' => $ticketData['notes'] ?? ''
        ];

        // Save to database
        $this->saveTrafficTicket($ticket);

        // Update driver points if applicable
        if ($violation['points'] > 0) {
            $this->updateDriverPoints($ticketData['license_plate'], $violation['points']);
        }

        // Start workflow
        $this->startTicketWorkflow($ticketNumber, 'traffic');

        // Send notification
        $this->sendNotification('ticket_issued', null, [
            'ticket_number' => $ticketNumber,
            'license_plate' => $ticketData['license_plate'],
            'fine_amount' => $violation['fine_amount']
        ]);

        return [
            'success' => true,
            'ticket_number' => $ticketNumber,
            'fine_amount' => $violation['fine_amount'],
            'due_date' => $dueDate,
            'appeal_deadline' => $appealDeadline
        ];
    }

    /**
     * Create parking violation
     */
    public function createParkingViolation(array $violationData): array
    {
        // Validate violation data
        $validation = $this->validateTicketData($violationData, 'parking');
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate violation number
        $violationNumber = $this->generateTicketNumber('parking');

        // Get violation details
        $violation = $this->violationCodes[$violationData['violation_code']] ?? null;
        if (!$violation) {
            return [
                'success' => false,
                'error' => 'Invalid violation code'
            ];
        }

        // Calculate due date
        $dueDate = date('Y-m-d H:i:s', strtotime("+{$this->config['payment_grace_period_days']} days"));

        // Calculate appeal deadline
        $appealDeadline = date('Y-m-d H:i:s', strtotime("+{$this->config['appeal_deadline_days']} days"));

        // Create violation record
        $violationRecord = [
            'violation_number' => $violationNumber,
            'license_plate' => $violationData['license_plate'],
            'violation_type' => $violation['description'],
            'violation_code' => $violationData['violation_code'],
            'location' => $violationData['location'],
            'zone_type' => $violationData['zone_type'] ?? '',
            'date_time' => $violationData['date_time'],
            'officer_id' => $violationData['officer_id'] ?? null,
            'fine_amount' => $violation['fine_amount'],
            'status' => 'issued',
            'due_date' => $dueDate,
            'appeal_deadline' => $appealDeadline,
            'evidence_photos' => $violationData['evidence_photos'] ?? [],
            'notes' => $violationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveParkingViolation($violationRecord);

        // Start workflow
        $this->startTicketWorkflow($violationNumber, 'parking');

        // Send notification
        $this->sendNotification('ticket_issued', null, [
            'ticket_number' => $violationNumber,
            'license_plate' => $violationData['license_plate'],
            'fine_amount' => $violation['fine_amount']
        ]);

        return [
            'success' => true,
            'violation_number' => $violationNumber,
            'fine_amount' => $violation['fine_amount'],
            'due_date' => $dueDate,
            'appeal_deadline' => $appealDeadline
        ];
    }

    /**
     * Process ticket payment
     */
    public function processTicketPayment(string $ticketNumber, string $ticketType, array $paymentData): array
    {
        // Get ticket details
        $ticket = $this->getTicket($ticketNumber, $ticketType);
        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found'
            ];
        }

        if ($ticket['status'] === 'paid') {
            return [
                'success' => false,
                'error' => 'Ticket already paid'
            ];
        }

        // Process payment
        $paymentGateway = new PaymentGateway();
        $paymentResult = $paymentGateway->processPayment([
            'amount' => $ticket['fine_amount'],
            'currency' => 'USD',
            'method' => $paymentData['method'],
            'description' => ucfirst($ticketType) . " Ticket Payment - {$ticketNumber}",
            'metadata' => [
                'ticket_number' => $ticketNumber,
                'ticket_type' => $ticketType
            ]
        ]);

        if (!$paymentResult['success']) {
            return [
                'success' => false,
                'error' => 'Payment processing failed'
            ];
        }

        // Update ticket status
        $this->updateTicketStatus($ticketNumber, $ticketType, 'paid');
        $this->updateTicketPaymentDate($ticketNumber, $ticketType, date('Y-m-d H:i:s'));

        // Advance workflow
        $this->advanceWorkflow($ticketNumber, 'paid');

        // Send notification
        $this->sendNotification('ticket_paid', null, [
            'ticket_number' => $ticketNumber
        ]);

        return [
            'success' => true,
            'transaction_id' => $paymentResult['transaction_id'],
            'message' => 'Ticket payment processed successfully'
        ];
    }

    /**
     * Submit ticket appeal
     */
    public function submitTicketAppeal(string $ticketNumber, string $ticketType, array $appealData): array
    {
        // Get ticket details
        $ticket = $this->getTicket($ticketNumber, $ticketType);
        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found'
            ];
        }

        // Check if appeal is within deadline
        $currentDate = date('Y-m-d H:i:s');
        if ($currentDate > $ticket['appeal_deadline']) {
            return [
                'success' => false,
                'error' => 'Appeal deadline has passed'
            ];
        }

        // Validate appeal data
        $validation = $this->validateAppealData($appealData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Create appeal record
        $appeal = [
            'ticket_number' => $ticketNumber,
            'ticket_type' => $ticketType,
            'appellant_name' => $appealData['appellant_name'],
            'appellant_email' => $appealData['appellant_email'],
            'appellant_phone' => $appealData['appellant_phone'],
            'appeal_reason' => $appealData['appeal_reason'],
            'appeal_evidence' => $appealData['appeal_evidence'] ?? [],
            'status' => 'submitted'
        ];

        // Save appeal
        $this->saveTicketAppeal($appeal);

        // Update ticket status
        $this->updateTicketStatus($ticketNumber, $ticketType, 'appealed');

        // Advance workflow
        $this->advanceWorkflow($ticketNumber, 'appealed');

        // Send notification
        $this->sendNotification('ticket_appeal_submitted', null, [
            'ticket_number' => $ticketNumber
        ]);

        return [
            'success' => true,
            'message' => 'Appeal submitted successfully'
        ];
    }

    /**
     * Process ticket appeal
     */
    public function processTicketAppeal(int $appealId, string $decision, array $decisionData = []): array
    {
        $appeal = $this->getTicketAppeal($appealId);
        if (!$appeal) {
            return [
                'success' => false,
                'error' => 'Appeal not found'
            ];
        }

        // Update appeal
        $this->updateAppealDecision($appealId, $decision, $decisionData);

        // Update ticket status based on decision
        if ($decision === 'approved') {
            $this->updateTicketStatus($appeal['ticket_number'], $appeal['ticket_type'], 'dismissed');
            $this->advanceWorkflow($appeal['ticket_number'], 'appeal_approved');

            // Send approval notification
            $this->sendNotification('appeal_approved', null, [
                'ticket_number' => $appeal['ticket_number']
            ]);
        } elseif ($decision === 'denied') {
            $this->updateTicketStatus($appeal['ticket_number'], $appeal['ticket_type'], 'court_pending');
            $this->advanceWorkflow($appeal['ticket_number'], 'appeal_denied');

            // Send denial notification
            $this->sendNotification('appeal_denied', null, [
                'ticket_number' => $appeal['ticket_number']
            ]);
        }

        return [
            'success' => true,
            'decision' => $decision,
            'message' => 'Appeal processed successfully'
        ];
    }

    /**
     * Get driver license points
     */
    public function getDriverLicensePoints(string $licenseNumber): array
    {
        $pointsRecord = $this->getDriverPointsRecord($licenseNumber);

        if (!$pointsRecord) {
            return [
                'license_number' => $licenseNumber,
                'total_points' => 0,
                'status' => 'active',
                'suspension_threshold' => $this->config['license_suspension_threshold']
            ];
        }

        // Check for suspension warning
        if ($pointsRecord['total_points'] >= $this->config['license_suspension_threshold'] - 2 &&
            $pointsRecord['status'] === 'active') {
            $this->sendNotification('license_suspension_warning', null, [
                'license_number' => $licenseNumber,
                'total_points' => $pointsRecord['total_points']
            ]);
        }

        return $pointsRecord;
    }

    /**
     * Sync with court system
     */
    public function syncWithCourtSystem(): array
    {
        if (!$this->courtSettings['enabled']) {
            return [
                'success' => false,
                'error' => 'Court integration not enabled'
            ];
        }

        // Get tickets pending court action
        $pendingTickets = $this->getPendingCourtTickets();

        $syncResults = [
            'total_processed' => 0,
            'successful_syncs' => 0,
            'failed_syncs' => 0,
            'errors' => []
        ];

        foreach ($pendingTickets as $ticket) {
            try {
                $courtData = $this->syncTicketWithCourt($ticket);
                $this->updateCourtIntegration($ticket['ticket_number'], $courtData);
                $syncResults['successful_syncs']++;
            } catch (\Exception $e) {
                $syncResults['errors'][] = "Failed to sync ticket {$ticket['ticket_number']}: " . $e->getMessage();
                $syncResults['failed_syncs']++;
            }
            $syncResults['total_processed']++;
        }

        return [
            'success' => true,
            'results' => $syncResults
        ];
    }

    /**
     * Generate ticket report
     */
    public function generateTicketReport(array $filters = []): array
    {
        $query = "SELECT * FROM traffic_tickets WHERE 1=1";

        if (isset($filters['status'])) {
            $query .= " AND status = '{$filters['status']}'";
        }

        if (isset($filters['violation_type'])) {
            $query .= " AND violation_type = '{$filters['violation_type']}'";
        }

        if (isset($filters['date_from'])) {
            $query .= " AND date_time >= '{$filters['date_from']}'";
        }

        if (isset($filters['date_to'])) {
            $query .= " AND date_time <= '{$filters['date_to']}'";
        }

        // Execute query and return results
        return [
            'filters' => $filters,
            'data' => [], // Would contain actual query results
            'generated_at' => date('c')
        ];
    }

    /**
     * Validate ticket data
     */
    private function validateTicketData(array $data, string $type): array
    {
        $errors = [];

        $requiredFields = [
            'license_plate', 'violation_code', 'location', 'date_time'
        ];

        if ($type === 'traffic') {
            $requiredFields = array_merge($requiredFields, ['vehicle_make', 'vehicle_model', 'officer_name']);
        }

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (!isset($this->violationCodes[$data['violation_code'] ?? ''])) {
            $errors[] = "Invalid violation code";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate ticket number
     */
    private function generateTicketNumber(string $type): string
    {
        $prefix = $type === 'traffic' ? 'TT' : 'PV';
        return $prefix . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validate appeal data
     */
    private function validateAppealData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'appellant_name', 'appellant_email', 'appeal_reason'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (isset($data['appellant_email']) && !filter_var($data['appellant_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Save traffic ticket to database
     */
    private function saveTrafficTicket(array $ticket): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO traffic_tickets (
                ticket_number, license_plate, vehicle_make, vehicle_model, vehicle_year,
                violation_type, violation_code, violation_description, location, date_time,
                officer_id, officer_name, fine_amount, points_assessment, status,
                due_date, appeal_deadline, evidence_photos, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $ticket['ticket_number'],
                $ticket['license_plate'],
                $ticket['vehicle_make'],
                $ticket['vehicle_model'],
                $ticket['vehicle_year'],
                $ticket['violation_type'],
                $ticket['violation_code'],
                $ticket['violation_description'],
                $ticket['location'],
                $ticket['date_time'],
                $ticket['officer_id'],
                $ticket['officer_name'],
                $ticket['fine_amount'],
                $ticket['points_assessment'],
                $ticket['status'],
                $ticket['due_date'],
                $ticket['appeal_deadline'],
                json_encode($ticket['evidence_photos']),
                $ticket['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving traffic ticket: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save parking violation to database
     */
    private function saveParkingViolation(array $violation): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO parking_violations (
                violation_number, license_plate, violation_type, violation_code,
                location, zone_type, date_time, officer_id, fine_amount, status,
                due_date, appeal_deadline, evidence_photos, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $violation['violation_number'],
                $violation['license_plate'],
                $violation['violation_type'],
                $violation['violation_code'],
                $violation['location'],
                $violation['zone_type'],
                $violation['date_time'],
                $violation['officer_id'],
                $violation['fine_amount'],
                $violation['status'],
                $violation['due_date'],
                $violation['appeal_deadline'],
                json_encode($violation['evidence_photos']),
                $violation['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving parking violation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get ticket by number and type
     */
    private function getTicket(string $ticketNumber, string $type): ?array
    {
        try {
            $db = Database::getInstance();

            if ($type === 'traffic') {
                $sql = "SELECT * FROM traffic_tickets WHERE ticket_number = ?";
            } else {
                $sql = "SELECT * FROM parking_violations WHERE violation_number = ?";
            }

            $result = $db->fetch($sql, [$ticketNumber]);

            if ($result) {
                if ($type === 'traffic') {
                    $result['evidence_photos'] = json_decode($result['evidence_photos'], true);
                } else {
                    $result['evidence_photos'] = json_decode($result['evidence_photos'], true);
                }
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting ticket: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update ticket status
     */
    private function updateTicketStatus(string $ticketNumber, string $type, string $status): bool
    {
        try {
            $db = Database::getInstance();

            if ($type === 'traffic') {
                $sql = "UPDATE traffic_tickets SET status = ? WHERE ticket_number = ?";
            } else {
                $sql = "UPDATE parking_violations SET status = ? WHERE violation_number = ?";
            }

            return $db->execute($sql, [$status, $ticketNumber]);
        } catch (\Exception $e) {
            error_log("Error updating ticket status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update ticket payment date
     */
    private function updateTicketPaymentDate(string $ticketNumber, string $type, string $date): bool
    {
        try {
            $db = Database::getInstance();

            if ($type === 'traffic') {
                $sql = "UPDATE traffic_tickets SET payment_date = ? WHERE ticket_number = ?";
            } else {
                $sql = "UPDATE parking_violations SET payment_date = ? WHERE violation_number = ?";
            }

            return $db->execute($sql, [$date, $ticketNumber]);
        } catch (\Exception $e) {
            error_log("Error updating ticket payment date: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update driver license points
     */
    private function updateDriverPoints(string $licensePlate, int $points): bool
    {
        try {
            $db = Database::getInstance();

            // First, try to find existing record
            $existingRecord = $db->fetch("SELECT * FROM driver_license_points WHERE license_number = ?", [$licensePlate]);

            if ($existingRecord) {
                // Update existing record
                $newTotal = $existingRecord['total_points'] + $points;
                $sql = "UPDATE driver_license_points SET total_points = ?, last_violation_date = ? WHERE license_number = ?";
                $params = [$newTotal, date('Y-m-d H:i:s'), $licensePlate];

                // Check for suspension
                if ($newTotal >= $this->config['license_suspension_threshold']) {
                    $sql = "UPDATE driver_license_points SET total_points = ?, last_violation_date = ?, status = 'suspended', suspension_date = ? WHERE license_number = ?";
                    $params = [$newTotal, date('Y-m-d H:i:s'), date('Y-m-d'), $licensePlate];
                }

                return $db->execute($sql, $params);
            } else {
                // Create new record
                $sql = "INSERT INTO driver_license_points (license_number, total_points, last_violation_date) VALUES (?, ?, ?)";
                return $db->execute($sql, [$licensePlate, $points, date('Y-m-d H:i:s')]);
            }
        } catch (\Exception $e) {
            error_log("Error updating driver points: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start ticket workflow
     */
    private function startTicketWorkflow(string $ticketNumber, string $type): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            $workflowType = $type === 'traffic' ? 'traffic_ticket_process' : 'parking_violation_process';
            return $workflowEngine->startWorkflow($workflowType, $ticketNumber);
        } catch (\Exception $e) {
            error_log("Error starting ticket workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Advance workflow
     */
    private function advanceWorkflow(string $ticketNumber, string $step): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            return $workflowEngine->advanceWorkflow($ticketNumber, $step);
        } catch (\Exception $e) {
            error_log("Error advancing workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, ?int $userId, array $data): bool
    {
        try {
            $notificationManager = new NotificationManager();
            return $notificationManager->sendNotification($type, $userId, $data);
        } catch (\Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save ticket appeal
     */
    private function saveTicketAppeal(array $appeal): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO ticket_appeals (
                ticket_number, ticket_type, appellant_name, appellant_email,
                appellant_phone, appeal_reason, appeal_evidence, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $appeal['ticket_number'],
                $appeal['ticket_type'],
                $appeal['appellant_name'],
                $appeal['appellant_email'],
                $appeal['appellant_phone'],
                $appeal['appeal_reason'],
                json_encode($appeal['appeal_evidence']),
                $appeal['status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving ticket appeal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get ticket appeal
     */
    private function getTicketAppeal(int $appealId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM ticket_appeals WHERE id = ?";
            $result = $db->fetch($sql, [$appealId]);

            if ($result) {
                $result['appeal_evidence'] = json_decode($result['appeal_evidence'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting ticket appeal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update appeal decision
     */
    private function updateAppealDecision(int $appealId, string $decision, array $data): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE ticket_appeals SET status = ?, decision = ?, decision_date = ?, reviewed_by = ? WHERE id = ?";
            $params = [
                $decision === 'approved' ? 'approved' : 'denied',
                $data['decision_notes'] ?? '',
                date('Y-m-d H:i:s'),
                $data['reviewed_by'] ?? null,
                $appealId
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error updating appeal decision: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get driver points record
     */
    private function getDriverPointsRecord(string $licenseNumber): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM driver_license_points WHERE license_number = ?";
            return $db->fetch($sql, [$licenseNumber]);
        } catch (\Exception $e) {
            error_log("Error getting driver points record: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get pending court tickets
     */
    private function getPendingCourtTickets(): array
    {
        try {
            $db = Database::getInstance();

            // Get traffic tickets pending court action
            $trafficTickets = $db->fetchAll(
                "SELECT ticket_number, 'traffic' as ticket_type FROM traffic_tickets WHERE status = 'court_pending'"
            );

            // Get parking violations pending court action
            $parkingViolations = $db->fetchAll(
                "SELECT violation_number as ticket_number, 'parking' as ticket_type FROM parking_violations WHERE status = 'court_pending'"
            );

            return array_merge($trafficTickets, $parkingViolations);
        } catch (\Exception $e) {
            error_log("Error getting pending court tickets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Sync ticket with court system
     */
    private function syncTicketWithCourt(array $ticket): array
    {
        // This would integrate with actual court system API
        // For now, return mock data
        return [
            'court_case_number' => 'C' . date('Y') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'court_name' => $this->courtSettings['court_mapping']['traffic_court'],
            'judge_name' => 'Judge ' . ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones'][array_rand(['Smith', 'Johnson', 'Williams', 'Brown', 'Jones'])],
            'hearing_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'outcome' => 'pending',
            'sync_status' => 'synced',
            'last_sync' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Update court integration
     */
    private function updateCourtIntegration(string $ticketNumber, array $courtData): bool
    {
        try {
            $db = Database::getInstance();

            // Check if record exists
            $existing = $db->fetch("SELECT id FROM court_integrations WHERE ticket_number = ?", [$ticketNumber]);

            if ($existing) {
                // Update existing
                $sql = "UPDATE court_integrations SET court_case_number = ?, court_name = ?, judge_name = ?, hearing_date = ?, outcome = ?, sync_status = ?, last_sync = ? WHERE ticket_number = ?";
                $params = [
                    $courtData['court_case_number'],
                    $courtData['court_name'],
                    $courtData['judge_name'],
                    $courtData['hearing_date'],
                    $courtData['outcome'],
                    $courtData['sync_status'],
                    $courtData['last_sync'],
                    $ticketNumber
                ];
            } else {
                // Insert new
                $sql = "INSERT INTO court_integrations (ticket_number, ticket_type, court_case_number, court_name, judge_name, hearing_date, outcome, sync_status, last_sync) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $ticketNumber,
                    'traffic', // Would need to determine type
                    $courtData['court_case_number'],
                    $courtData['court_name'],
                    $courtData['judge_name'],
                    $courtData['hearing_date'],
                    $courtData['outcome'],
                    $courtData['sync_status'],
                    $courtData['last_sync']
                ];
            }

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error updating court integration: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get traffic tickets (API handler)
     */
    public function getTrafficTickets(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM traffic_tickets WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['license_plate'])) {
                $sql .= " AND license_plate = ?";
                $params[] = $filters['license_plate'];
            }

            if (isset($filters['officer_id'])) {
                $sql .= " AND officer_id = ?";
                $params[] = $filters['officer_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['evidence_photos'] = json_decode($result['evidence_photos'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting traffic tickets: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve traffic tickets'
            ];
        }
    }

    /**
     * Get traffic ticket (API handler)
     */
    public function getTrafficTicket(string $ticketNumber): array
    {
        $ticket = $this->getTicket($ticketNumber, 'traffic');

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Traffic ticket not found'
            ];
        }

        return [
            'success' => true,
            'data' => $ticket
        ];
    }

    /**
     * Appeal traffic ticket (API handler)
     */
    public function appealTrafficTicket(string $ticketNumber, array $appealData): array
    {
        return $this->submitTicketAppeal($ticketNumber, 'traffic', $appealData);
    }

    /**
     * Get parking violations (API handler)
     */
    public function getParkingViolations(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM parking_violations WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['license_plate'])) {
                $sql .= " AND license_plate = ?";
                $params[] = $filters['license_plate'];
            }

            if (isset($filters['zone_type'])) {
                $sql .= " AND zone_type = ?";
                $params[] = $filters['zone_type'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['evidence_photos'] = json_decode($result['evidence_photos'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting parking violations: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve parking violations'
            ];
        }
    }

    /**
     * Get driver points (API handler)
     */
    public function getDriverPoints(string $licenseNumber): array
    {
        $pointsData = $this->getDriverLicensePoints($licenseNumber);

        return [
            'success' => true,
            'data' => $pointsData
        ];
    }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        try {
            $db = Database::getInstance();

            // Get traffic ticket statistics
            $trafficStats = $db->fetch("
                SELECT
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status = 'paid' THEN fine_amount ELSE 0 END) as paid_amount,
                    COUNT(CASE WHEN status = 'appealed' THEN 1 END) as appealed_tickets,
                    COUNT(CASE WHEN status = 'court_pending' THEN 1 END) as court_pending
                FROM traffic_tickets
            ");

            // Get parking violation statistics
            $parkingStats = $db->fetch("
                SELECT
                    COUNT(*) as total_violations,
                    SUM(CASE WHEN status = 'paid' THEN fine_amount ELSE 0 END) as paid_amount,
                    COUNT(CASE WHEN status = 'appealed' THEN 1 END) as appealed_violations
                FROM parking_violations
            ");

            // Get appeal statistics
            $appealStats = $db->fetch("
                SELECT
                    COUNT(*) as total_appeals,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_appeals,
                    COUNT(CASE WHEN status = 'denied' THEN 1 END) as denied_appeals
                FROM ticket_appeals
            ");

            return [
                'total_traffic_tickets' => $trafficStats['total_tickets'] ?? 0,
                'total_parking_violations' => $parkingStats['total_violations'] ?? 0,
                'total_revenue' => ($trafficStats['paid_amount'] ?? 0) + ($parkingStats['paid_amount'] ?? 0),
                'paid_tickets' => ($trafficStats['paid_amount'] ?? 0) + ($parkingStats['paid_amount'] ?? 0),
                'pending_appeals' => ($trafficStats['appealed_tickets'] ?? 0) + ($parkingStats['appealed_violations'] ?? 0),
                'court_pending' => $trafficStats['court_pending'] ?? 0,
                'appeal_success_rate' => $appealStats['total_appeals'] > 0 ?
                    round(($appealStats['approved_appeals'] / $appealStats['total_appeals']) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            error_log("Error getting module statistics: " . $e->getMessage());
            return [
                'total_traffic_tickets' => 0,
                'total_parking_violations' => 0,
                'total_revenue' => 0.00,
                'paid_tickets' => 0,
                'pending_appeals' => 0,
                'court_pending' => 0,
                'appeal_success_rate' => 0
            ];
        }
    }
}

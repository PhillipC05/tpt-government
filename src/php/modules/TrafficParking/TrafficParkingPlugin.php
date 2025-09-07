<?php
/**
 * TPT Government Platform - Traffic & Parking Module
 *
 * Comprehensive traffic ticket and parking violation management system
 * for local government transportation and parking enforcement.
 */

namespace Core\Modules;

use Core\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class TrafficParkingPlugin extends ServiceModule
{
    /**
     * Service category
     */
    protected string $serviceCategory = 'infrastructure';

    /**
     * Required permissions
     */
    protected array $requiredPermissions = [
        'traffic_tickets.view',
        'traffic_tickets.create',
        'traffic_tickets.approve',
        'parking_violations.manage'
    ];

    /**
     * Database tables
     */
    protected array $databaseTables = [
        'traffic_tickets' => [
            'id SERIAL PRIMARY KEY',
            'ticket_number VARCHAR(50) UNIQUE NOT NULL',
            'vehicle_registration VARCHAR(20) NOT NULL',
            'license_number VARCHAR(20)',
            'driver_name VARCHAR(100)',
            'driver_address TEXT',
            'offense_type VARCHAR(100) NOT NULL',
            'offense_location TEXT NOT NULL',
            'offense_date DATE NOT NULL',
            'offense_time TIME NOT NULL',
            'fine_amount DECIMAL(8,2) NOT NULL',
            'points_deducted INT DEFAULT 0',
            'issued_by INT NOT NULL',
            'issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'status VARCHAR(50) DEFAULT "unpaid"',
            'due_date DATE',
            'paid BOOLEAN DEFAULT FALSE',
            'paid_at TIMESTAMP NULL',
            'payment_reference VARCHAR(100)',
            'court_date DATE NULL',
            'court_outcome VARCHAR(100) NULL',
            'appeal_status VARCHAR(50) DEFAULT "none"',
            'appeal_date DATE NULL',
            'appeal_outcome VARCHAR(100) NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'parking_violations' => [
            'id SERIAL PRIMARY KEY',
            'violation_number VARCHAR(50) UNIQUE NOT NULL',
            'vehicle_registration VARCHAR(20) NOT NULL',
            'street_name VARCHAR(100) NOT NULL',
            'violation_type VARCHAR(100) NOT NULL',
            'violation_date DATE NOT NULL',
            'violation_time TIME NOT NULL',
            'fine_amount DECIMAL(8,2) NOT NULL',
            'issued_by INT NOT NULL',
            'issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'status VARCHAR(50) DEFAULT "unpaid"',
            'due_date DATE',
            'paid BOOLEAN DEFAULT FALSE',
            'paid_at TIMESTAMP NULL',
            'payment_reference VARCHAR(100)',
            'towed BOOLEAN DEFAULT FALSE',
            'tow_location VARCHAR(200) NULL',
            'appeal_status VARCHAR(50) DEFAULT "none"',
            'appeal_date DATE NULL',
            'appeal_outcome VARCHAR(100) NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'driver_licenses' => [
            'id SERIAL PRIMARY KEY',
            'license_number VARCHAR(20) UNIQUE NOT NULL',
            'driver_name VARCHAR(100) NOT NULL',
            'date_of_birth DATE NOT NULL',
            'address TEXT NOT NULL',
            'license_class VARCHAR(10) NOT NULL',
            'issue_date DATE NOT NULL',
            'expiry_date DATE NOT NULL',
            'points_balance INT DEFAULT 0',
            'status VARCHAR(50) DEFAULT "active"',
            'suspended_until DATE NULL',
            'medical_conditions TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'traffic_offenses' => [
            'id SERIAL PRIMARY KEY',
            'offense_code VARCHAR(20) UNIQUE NOT NULL',
            'description TEXT NOT NULL',
            'fine_amount DECIMAL(8,2) NOT NULL',
            'points_deducted INT DEFAULT 0',
            'category VARCHAR(50) NOT NULL',
            'severity VARCHAR(20) DEFAULT "minor"',
            'active BOOLEAN DEFAULT TRUE',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * API endpoints
     */
    protected array $apiEndpoints = [
        [
            'path' => '/api/traffic-tickets',
            'method' => 'GET',
            'handler' => [$this, 'getTickets'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/traffic-tickets',
            'method' => 'POST',
            'handler' => [$this, 'createTicket'],
            'middleware' => ['auth', 'permission:traffic_tickets.create']
        ],
        [
            'path' => '/api/traffic-tickets/{id}',
            'method' => 'GET',
            'handler' => [$this, 'getTicket'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/traffic-tickets/{id}/pay',
            'method' => 'POST',
            'handler' => [$this, 'payTicket'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/traffic-tickets/{id}/appeal',
            'method' => 'POST',
            'handler' => [$this, 'appealTicket'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/parking-violations',
            'method' => 'GET',
            'handler' => [$this, 'getParkingViolations'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/parking-violations',
            'method' => 'POST',
            'handler' => [$this, 'createParkingViolation'],
            'middleware' => ['auth', 'permission:parking_violations.manage']
        ],
        [
            'path' => '/api/driver-license/{license}',
            'method' => 'GET',
            'handler' => [$this, 'getDriverLicense'],
            'middleware' => ['auth']
        ]
    ];

    /**
     * Workflow definitions
     */
    protected array $workflows = [
        'traffic_ticket_appeal' => [
            'name' => 'Traffic Ticket Appeal Process',
            'description' => 'Standard workflow for processing traffic ticket appeals',
            'steps' => [
                'appealed' => ['next' => 'under_review', 'roles' => ['traffic_officer']],
                'under_review' => ['next' => ['upheld', 'dismissed', 'reduced'], 'roles' => ['traffic_officer']],
                'upheld' => ['final' => true, 'roles' => ['traffic_officer']],
                'dismissed' => ['final' => true, 'roles' => ['traffic_officer']],
                'reduced' => ['final' => true, 'roles' => ['traffic_officer']]
            ]
        ]
    ];

    /**
     * Form definitions
     */
    protected array $forms = [
        'traffic_ticket' => [
            'name' => 'Traffic Ticket Issuance Form',
            'description' => 'Form for issuing traffic tickets',
            'fields' => [
                'vehicle_registration' => ['type' => 'text', 'required' => true, 'label' => 'Vehicle Registration'],
                'license_number' => ['type' => 'text', 'required' => false, 'label' => 'License Number'],
                'offense_type' => ['type' => 'select', 'required' => true, 'label' => 'Offense Type'],
                'offense_location' => ['type' => 'text', 'required' => true, 'label' => 'Location'],
                'offense_date' => ['type' => 'date', 'required' => true, 'label' => 'Date'],
                'offense_time' => ['type' => 'time', 'required' => true, 'label' => 'Time']
            ]
        ],
        'parking_violation' => [
            'name' => 'Parking Violation Form',
            'description' => 'Form for recording parking violations',
            'fields' => [
                'vehicle_registration' => ['type' => 'text', 'required' => true, 'label' => 'Vehicle Registration'],
                'street_name' => ['type' => 'text', 'required' => true, 'label' => 'Street Name'],
                'violation_type' => ['type' => 'select', 'required' => true, 'label' => 'Violation Type'],
                'violation_date' => ['type' => 'date', 'required' => true, 'label' => 'Date'],
                'violation_time' => ['type' => 'time', 'required' => true, 'label' => 'Time']
            ]
        ]
    ];

    /**
     * Report definitions
     */
    protected array $reports = [
        'traffic_ticket_summary' => [
            'name' => 'Traffic Ticket Summary Report',
            'description' => 'Summary of traffic tickets issued and collected',
            'type' => 'financial'
        ],
        'parking_violation_report' => [
            'name' => 'Parking Violation Report',
            'description' => 'Report on parking violations and revenue',
            'type' => 'financial'
        ],
        'driver_safety_report' => [
            'name' => 'Driver Safety Report',
            'description' => 'Report on license suspensions and points deductions',
            'type' => 'safety'
        ]
    ];

    /**
     * Get plugin name
     */
    public function getPluginName(): string
    {
        return 'traffic-parking';
    }

    /**
     * Get service icon
     */
    public function getServiceIcon(): string
    {
        return 'fas fa-car';
    }

    /**
     * Get traffic tickets
     */
    public function getTickets(array $filters = []): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $whereClause = '';
            $params = [];

            if (!empty($filters['status'])) {
                $whereClause = 'WHERE status = ?';
                $params[] = $filters['status'];
            }

            if (!empty($filters['vehicle_registration'])) {
                $whereClause = $whereClause ? $whereClause . ' AND' : 'WHERE';
                $whereClause .= ' vehicle_registration = ?';
                $params[] = $filters['vehicle_registration'];
            }

            $tickets = $this->database->select(
                "SELECT * FROM traffic_tickets {$whereClause} ORDER BY issued_at DESC",
                $params
            );

            return [
                'success' => true,
                'data' => $tickets
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create traffic ticket
     */
    public function createTicket(array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Generate ticket number
            $ticketNumber = $this->generateTicketNumber();

            // Get offense details
            $offense = $this->getOffenseDetails($data['offense_type']);
            if (!$offense) {
                return [
                    'success' => false,
                    'error' => 'Invalid offense type'
                ];
            }

            // Calculate due date (30 days from issue)
            $dueDate = date('Y-m-d', strtotime('+30 days'));

            $ticketId = $this->database->insert('traffic_tickets', [
                'ticket_number' => $ticketNumber,
                'vehicle_registration' => $data['vehicle_registration'],
                'license_number' => $data['license_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_address' => $data['driver_address'] ?? null,
                'offense_type' => $data['offense_type'],
                'offense_location' => $data['offense_location'],
                'offense_date' => $data['offense_date'],
                'offense_time' => $data['offense_time'],
                'fine_amount' => $offense['fine_amount'],
                'points_deducted' => $offense['points_deducted'],
                'issued_by' => $data['issued_by'] ?? 1,
                'due_date' => $dueDate
            ]);

            // Deduct points from license if applicable
            if ($offense['points_deducted'] > 0 && !empty($data['license_number'])) {
                $this->deductLicensePoints($data['license_number'], $offense['points_deducted']);
            }

            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get single ticket
     */
    public function getTicket(int $id): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $ticket = $this->database->selectOne(
                'SELECT * FROM traffic_tickets WHERE id = ?',
                [$id]
            );

            if (!$ticket) {
                return [
                    'success' => false,
                    'error' => 'Ticket not found'
                ];
            }

            return [
                'success' => true,
                'ticket' => $ticket
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Pay ticket
     */
    public function payTicket(int $id, array $paymentData): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'traffic_tickets',
                [
                    'status' => 'paid',
                    'paid' => true,
                    'paid_at' => date('Y-m-d H:i:s'),
                    'payment_reference' => $paymentData['payment_reference'] ?? null
                ],
                ['id' => $id]
            );

            return [
                'success' => true,
                'message' => 'Ticket payment processed successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Appeal ticket
     */
    public function appealTicket(int $id, array $appealData): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'traffic_tickets',
                [
                    'appeal_status' => 'appealed',
                    'appeal_date' => date('Y-m-d')
                ],
                ['id' => $id]
            );

            // Trigger appeal workflow
            $this->triggerWorkflow($id, 'appealed', 'traffic_ticket_appeal');

            return [
                'success' => true,
                'message' => 'Appeal submitted successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get parking violations
     */
    public function getParkingViolations(array $filters = []): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $whereClause = '';
            $params = [];

            if (!empty($filters['status'])) {
                $whereClause = 'WHERE status = ?';
                $params[] = $filters['status'];
            }

            $violations = $this->database->select(
                "SELECT * FROM parking_violations {$whereClause} ORDER BY issued_at DESC",
                $params
            );

            return [
                'success' => true,
                'data' => $violations
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create parking violation
     */
    public function createParkingViolation(array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Generate violation number
            $violationNumber = $this->generateViolationNumber();

            // Calculate fine based on violation type
            $fineAmount = $this->calculateParkingFine($data['violation_type']);

            // Calculate due date (14 days for parking violations)
            $dueDate = date('Y-m-d', strtotime('+14 days'));

            $violationId = $this->database->insert('parking_violations', [
                'violation_number' => $violationNumber,
                'vehicle_registration' => $data['vehicle_registration'],
                'street_name' => $data['street_name'],
                'violation_type' => $data['violation_type'],
                'violation_date' => $data['violation_date'],
                'violation_time' => $data['violation_time'],
                'fine_amount' => $fineAmount,
                'issued_by' => $data['issued_by'] ?? 1,
                'due_date' => $dueDate
            ]);

            return [
                'success' => true,
                'violation_id' => $violationId,
                'violation_number' => $violationNumber
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get driver license
     */
    public function getDriverLicense(string $licenseNumber): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $license = $this->database->selectOne(
                'SELECT * FROM driver_licenses WHERE license_number = ?',
                [$licenseNumber]
            );

            if (!$license) {
                return [
                    'success' => false,
                    'error' => 'License not found'
                ];
            }

            return [
                'success' => true,
                'license' => $license
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate ticket number
     */
    private function generateTicketNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $sequence = rand(10000, 99999);
        return "TT{$year}{$month}{$sequence}";
    }

    /**
     * Generate violation number
     */
    private function generateViolationNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $sequence = rand(10000, 99999);
        return "PV{$year}{$month}{$sequence}";
    }

    /**
     * Get offense details
     */
    private function getOffenseDetails(string $offenseCode): ?array
    {
        if (!$this->database) {
            return null;
        }

        try {
            return $this->database->selectOne(
                'SELECT * FROM traffic_offenses WHERE offense_code = ? AND active = TRUE',
                [$offenseCode]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Deduct license points
     */
    private function deductLicensePoints(string $licenseNumber, int $points): void
    {
        if (!$this->database) {
            return;
        }

        try {
            $this->database->query(
                'UPDATE driver_licenses SET points_balance = points_balance - ? WHERE license_number = ?',
                [$points, $licenseNumber]
            );
        } catch (\Exception $e) {
            error_log("Failed to deduct points from license {$licenseNumber}: " . $e->getMessage());
        }
    }

    /**
     * Calculate parking fine
     */
    private function calculateParkingFine(string $violationType): float
    {
        // Default fine amounts based on violation type
        $fineAmounts = [
            'no_parking' => 50.00,
            'expired_meter' => 30.00,
            'double_parked' => 75.00,
            'handicapped_zone' => 200.00,
            'loading_zone' => 100.00
        ];

        return $fineAmounts[$violationType] ?? 50.00;
    }

    /**
     * Trigger workflow
     */
    private function triggerWorkflow(int $itemId, string $action, string $workflowType = 'traffic_ticket_appeal'): void
    {
        // Implementation for workflow triggering
        // This would integrate with the WorkflowEngine
    }

    /**
     * Get service statistics
     */
    protected function getServiceStatistics(): array
    {
        if (!$this->database) {
            return parent::getServiceStatistics();
        }

        try {
            $totalTickets = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM traffic_tickets'
            )['count'] ?? 0;

            $unpaidTickets = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM traffic_tickets WHERE paid = FALSE'
            )['count'] ?? 0;

            $totalViolations = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM parking_violations'
            )['count'] ?? 0;

            $revenue = $this->database->selectOne(
                'SELECT SUM(fine_amount) as total FROM traffic_tickets WHERE paid = TRUE'
            )['total'] ?? 0;

            return [
                'total_tickets' => $totalTickets,
                'unpaid_tickets' => $unpaidTickets,
                'total_violations' => $totalViolations,
                'revenue_this_month' => $revenue
            ];
        } catch (\Exception $e) {
            return parent::getServiceStatistics();
        }
    }

    /**
     * Initialize default configuration
     */
    protected function initializeDefaultConfig(): void
    {
        $this->setServiceConfig('ticket_due_days', 30);
        $this->setServiceConfig('violation_due_days', 14);
        $this->setServiceConfig('appeal_deadline_days', 28);
        $this->setServiceConfig('license_suspension_threshold', 100); // points
        $this->setServiceConfig('court_referral_threshold', 500); // dollar amount
    }
}

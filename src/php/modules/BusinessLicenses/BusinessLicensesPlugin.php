<?php
/**
 * TPT Government Platform - Business Licenses Module
 *
 * Comprehensive business licensing and registration system
 * for local government economic development and compliance.
 */

namespace Core\Modules;

use Core\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class BusinessLicensesPlugin extends ServiceModule
{
    /**
     * Service category
     */
    protected string $serviceCategory = 'permitting';

    /**
     * Required permissions
     */
    protected array $requiredPermissions = [
        'business_licenses.view',
        'business_licenses.create',
        'business_licenses.approve',
        'business_licenses.renew'
    ];

    /**
     * Database tables
     */
    protected array $databaseTables = [
        'business_licenses' => [
            'id SERIAL PRIMARY KEY',
            'license_number VARCHAR(50) UNIQUE NOT NULL',
            'business_name VARCHAR(200) NOT NULL',
            'business_type VARCHAR(100) NOT NULL',
            'business_category VARCHAR(100) NOT NULL',
            'owner_name VARCHAR(100) NOT NULL',
            'owner_address TEXT NOT NULL',
            'owner_phone VARCHAR(20)',
            'owner_email VARCHAR(100)',
            'business_address TEXT NOT NULL',
            'business_phone VARCHAR(20)',
            'business_email VARCHAR(100)',
            'abn VARCHAR(20)',
            'gst_registered BOOLEAN DEFAULT FALSE',
            'license_type VARCHAR(50) NOT NULL',
            'issue_date DATE NOT NULL',
            'expiry_date DATE NOT NULL',
            'status VARCHAR(50) DEFAULT "active"',
            'renewal_due DATE',
            'last_renewal DATE NULL',
            'compliance_status VARCHAR(50) DEFAULT "compliant"',
            'risk_rating VARCHAR(20) DEFAULT "low"',
            'annual_fee DECIMAL(10,2) DEFAULT 0',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'business_license_applications' => [
            'id SERIAL PRIMARY KEY',
            'application_number VARCHAR(50) UNIQUE NOT NULL',
            'business_name VARCHAR(200) NOT NULL',
            'business_type VARCHAR(100) NOT NULL',
            'applicant_name VARCHAR(100) NOT NULL',
            'applicant_address TEXT NOT NULL',
            'applicant_phone VARCHAR(20)',
            'applicant_email VARCHAR(100)',
            'application_type VARCHAR(50) NOT NULL',
            'status VARCHAR(50) DEFAULT "draft"',
            'submitted_at TIMESTAMP NULL',
            'lodged_at TIMESTAMP NULL',
            'decision_at TIMESTAMP NULL',
            'decision_by INT NULL',
            'decision VARCHAR(50) NULL',
            'decision_notes TEXT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'business_license_inspections' => [
            'id SERIAL PRIMARY KEY',
            'license_id INT NOT NULL',
            'inspection_type VARCHAR(100) NOT NULL',
            'scheduled_date DATE NOT NULL',
            'scheduled_time TIME',
            'inspector_id INT',
            'status VARCHAR(50) DEFAULT "scheduled"',
            'inspection_date TIMESTAMP NULL',
            'result VARCHAR(50) NULL',
            'notes TEXT',
            'follow_up_required BOOLEAN DEFAULT FALSE',
            'follow_up_date DATE NULL',
            'compliance_score INT DEFAULT 100',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (license_id) REFERENCES business_licenses(id) ON DELETE CASCADE'
        ],
        'business_license_fees' => [
            'id SERIAL PRIMARY KEY',
            'license_id INT NOT NULL',
            'fee_type VARCHAR(100) NOT NULL',
            'amount DECIMAL(10,2) NOT NULL',
            'description TEXT',
            'due_date DATE',
            'paid BOOLEAN DEFAULT FALSE',
            'paid_at TIMESTAMP NULL',
            'payment_reference VARCHAR(100)',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (license_id) REFERENCES business_licenses(id) ON DELETE CASCADE'
        ],
        'business_license_types' => [
            'id SERIAL PRIMARY KEY',
            'license_code VARCHAR(20) UNIQUE NOT NULL',
            'name VARCHAR(100) NOT NULL',
            'description TEXT',
            'category VARCHAR(50) NOT NULL',
            'annual_fee DECIMAL(10,2) NOT NULL',
            'renewal_period_months INT DEFAULT 12',
            'inspection_required BOOLEAN DEFAULT TRUE',
            'risk_level VARCHAR(20) DEFAULT "medium"',
            'active BOOLEAN DEFAULT TRUE',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * API endpoints
     */
    protected array $apiEndpoints = [
        [
            'path' => '/api/business-licenses',
            'method' => 'GET',
            'handler' => [$this, 'getLicenses'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/business-licenses',
            'method' => 'POST',
            'handler' => [$this, 'createLicense'],
            'middleware' => ['auth', 'permission:business_licenses.create']
        ],
        [
            'path' => '/api/business-licenses/{id}',
            'method' => 'GET',
            'handler' => [$this, 'getLicense'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/business-licenses/{id}/renew',
            'method' => 'POST',
            'handler' => [$this, 'renewLicense'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/business-license-applications',
            'method' => 'GET',
            'handler' => [$this, 'getApplications'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/business-license-applications',
            'method' => 'POST',
            'handler' => [$this, 'createApplication'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/business-license-applications/{id}/submit',
            'method' => 'POST',
            'handler' => [$this, 'submitApplication'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/business-license-applications/{id}/approve',
            'method' => 'POST',
            'handler' => [$this, 'approveApplication'],
            'middleware' => ['auth', 'permission:business_licenses.approve']
        ]
    ];

    /**
     * Workflow definitions
     */
    protected array $workflows = [
        'business_license_application' => [
            'name' => 'Business License Application Process',
            'description' => 'Standard workflow for processing business license applications',
            'steps' => [
                'draft' => ['next' => 'submitted', 'roles' => ['applicant']],
                'submitted' => ['next' => 'under_review', 'roles' => ['licensing_officer']],
                'under_review' => ['next' => ['approved', 'rejected', 'more_info'], 'roles' => ['licensing_officer']],
                'more_info' => ['next' => 'under_review', 'roles' => ['applicant']],
                'approved' => ['next' => 'issued', 'roles' => ['licensing_officer']],
                'rejected' => ['final' => true, 'roles' => ['licensing_officer']],
                'issued' => ['final' => true, 'roles' => ['licensing_officer']]
            ]
        ],
        'business_license_renewal' => [
            'name' => 'Business License Renewal Process',
            'description' => 'Workflow for renewing business licenses',
            'steps' => [
                'renewal_due' => ['next' => 'renewal_submitted', 'roles' => ['license_holder']],
                'renewal_submitted' => ['next' => ['renewal_approved', 'renewal_rejected'], 'roles' => ['licensing_officer']],
                'renewal_approved' => ['final' => true, 'roles' => ['licensing_officer']],
                'renewal_rejected' => ['final' => true, 'roles' => ['licensing_officer']]
            ]
        ]
    ];

    /**
     * Form definitions
     */
    protected array $forms = [
        'business_license_application' => [
            'name' => 'Business License Application Form',
            'description' => 'Form for applying for business licenses',
            'fields' => [
                'business_name' => ['type' => 'text', 'required' => true, 'label' => 'Business Name'],
                'business_type' => ['type' => 'select', 'required' => true, 'label' => 'Business Type'],
                'owner_name' => ['type' => 'text', 'required' => true, 'label' => 'Owner Name'],
                'owner_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Owner Address'],
                'business_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Address'],
                'license_type' => ['type' => 'select', 'required' => true, 'label' => 'License Type']
            ]
        ],
        'business_license_renewal' => [
            'name' => 'Business License Renewal Form',
            'description' => 'Form for renewing business licenses',
            'fields' => [
                'license_number' => ['type' => 'text', 'required' => true, 'label' => 'License Number'],
                'business_name' => ['type' => 'text', 'required' => true, 'label' => 'Business Name'],
                'renewal_period' => ['type' => 'select', 'required' => true, 'label' => 'Renewal Period']
            ]
        ]
    ];

    /**
     * Report definitions
     */
    protected array $reports = [
        'license_summary' => [
            'name' => 'Business License Summary Report',
            'description' => 'Summary of active and expired business licenses',
            'type' => 'operational'
        ],
        'license_revenue' => [
            'name' => 'License Revenue Report',
            'description' => 'Report on license fees and revenue',
            'type' => 'financial'
        ],
        'compliance_report' => [
            'name' => 'Business Compliance Report',
            'description' => 'Report on business compliance and inspections',
            'type' => 'compliance'
        ]
    ];

    /**
     * Get plugin name
     */
    public function getPluginName(): string
    {
        return 'business-licenses';
    }

    /**
     * Get service icon
     */
    public function getServiceIcon(): string
    {
        return 'fas fa-briefcase';
    }

    /**
     * Get business licenses
     */
    public function getLicenses(array $filters = []): array
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

            if (!empty($filters['business_type'])) {
                $whereClause = $whereClause ? $whereClause . ' AND' : 'WHERE';
                $whereClause .= ' business_type = ?';
                $params[] = $filters['business_type'];
            }

            $licenses = $this->database->select(
                "SELECT * FROM business_licenses {$whereClause} ORDER BY created_at DESC",
                $params
            );

            return [
                'success' => true,
                'data' => $licenses
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create business license
     */
    public function createLicense(array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Generate license number
            $licenseNumber = $this->generateLicenseNumber();

            // Get license type details
            $licenseType = $this->getLicenseTypeDetails($data['license_type']);
            if (!$licenseType) {
                return [
                    'success' => false,
                    'error' => 'Invalid license type'
                ];
            }

            // Calculate expiry date
            $expiryDate = date('Y-m-d', strtotime('+1 year'));
            $renewalDue = date('Y-m-d', strtotime('+11 months'));

            $licenseId = $this->database->insert('business_licenses', [
                'license_number' => $licenseNumber,
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'business_category' => $data['business_category'] ?? 'general',
                'owner_name' => $data['owner_name'],
                'owner_address' => $data['owner_address'],
                'owner_phone' => $data['owner_phone'] ?? null,
                'owner_email' => $data['owner_email'] ?? null,
                'business_address' => $data['business_address'],
                'business_phone' => $data['business_phone'] ?? null,
                'business_email' => $data['business_email'] ?? null,
                'license_type' => $data['license_type'],
                'issue_date' => date('Y-m-d'),
                'expiry_date' => $expiryDate,
                'renewal_due' => $renewalDue,
                'annual_fee' => $licenseType['annual_fee'],
                'risk_rating' => $licenseType['risk_level']
            ]);

            return [
                'success' => true,
                'license_id' => $licenseId,
                'license_number' => $licenseNumber
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get single license
     */
    public function getLicense(int $id): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $license = $this->database->selectOne(
                'SELECT * FROM business_licenses WHERE id = ?',
                [$id]
            );

            if (!$license) {
                return [
                    'success' => false,
                    'error' => 'License not found'
                ];
            }

            // Get related inspections
            $inspections = $this->database->select(
                'SELECT * FROM business_license_inspections WHERE license_id = ?',
                [$id]
            );

            // Get fees
            $fees = $this->database->select(
                'SELECT * FROM business_license_fees WHERE license_id = ?',
                [$id]
            );

            return [
                'success' => true,
                'license' => $license,
                'inspections' => $inspections,
                'fees' => $fees
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Renew license
     */
    public function renewLicense(int $id, array $renewalData): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Get current license
            $license = $this->database->selectOne(
                'SELECT * FROM business_licenses WHERE id = ?',
                [$id]
            );

            if (!$license) {
                return [
                    'success' => false,
                    'error' => 'License not found'
                ];
            }

            // Calculate new expiry date
            $currentExpiry = new \DateTime($license['expiry_date']);
            $newExpiry = $currentExpiry->modify('+1 year');
            $newRenewalDue = $newExpiry->modify('-1 month');

            $this->database->update(
                'business_licenses',
                [
                    'expiry_date' => $newExpiry->format('Y-m-d'),
                    'renewal_due' => $newRenewalDue->format('Y-m-d'),
                    'last_renewal' => date('Y-m-d'),
                    'status' => 'active'
                ],
                ['id' => $id]
            );

            return [
                'success' => true,
                'message' => 'License renewed successfully',
                'new_expiry_date' => $newExpiry->format('Y-m-d')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get license applications
     */
    public function getApplications(array $filters = []): array
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

            $applications = $this->database->select(
                "SELECT * FROM business_license_applications {$whereClause} ORDER BY created_at DESC",
                $params
            );

            return [
                'success' => true,
                'data' => $applications
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create license application
     */
    public function createApplication(array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Generate application number
            $applicationNumber = $this->generateApplicationNumber();

            $applicationId = $this->database->insert('business_license_applications', [
                'application_number' => $applicationNumber,
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'applicant_name' => $data['applicant_name'],
                'applicant_address' => $data['applicant_address'],
                'applicant_phone' => $data['applicant_phone'] ?? null,
                'applicant_email' => $data['applicant_email'] ?? null,
                'application_type' => $data['application_type'],
                'status' => 'draft'
            ]);

            return [
                'success' => true,
                'application_id' => $applicationId,
                'application_number' => $applicationNumber
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Submit application
     */
    public function submitApplication(int $id): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'business_license_applications',
                [
                    'status' => 'submitted',
                    'submitted_at' => date('Y-m-d H:i:s')
                ],
                ['id' => $id]
            );

            // Trigger workflow
            $this->triggerWorkflow($id, 'submitted', 'business_license_application');

            return [
                'success' => true,
                'message' => 'Application submitted successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Approve application
     */
    public function approveApplication(int $id, array $approvalData): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'business_license_applications',
                [
                    'status' => 'approved',
                    'decision' => 'approved',
                    'decision_at' => date('Y-m-d H:i:s'),
                    'decision_by' => $approvalData['approved_by'] ?? 1,
                    'decision_notes' => $approvalData['notes'] ?? ''
                ],
                ['id' => $id]
            );

            // Create the actual license
            $application = $this->database->selectOne(
                'SELECT * FROM business_license_applications WHERE id = ?',
                [$id]
            );

            if ($application) {
                $this->createLicense([
                    'business_name' => $application['business_name'],
                    'business_type' => $application['business_type'],
                    'owner_name' => $application['applicant_name'],
                    'owner_address' => $application['applicant_address'],
                    'owner_phone' => $application['applicant_phone'],
                    'owner_email' => $application['applicant_email'],
                    'business_address' => $application['applicant_address'], // Default to owner address
                    'license_type' => $application['application_type']
                ]);
            }

            // Trigger workflow
            $this->triggerWorkflow($id, 'approved', 'business_license_application');

            return [
                'success' => true,
                'message' => 'Application approved and license issued'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate license number
     */
    private function generateLicenseNumber(): string
    {
        $year = date('Y');
        $sequence = rand(10000, 99999);
        return "BL{$year}{$sequence}";
    }

    /**
     * Generate application number
     */
    private function generateApplicationNumber(): string
    {
        $year = date('Y');
        $sequence = rand(10000, 99999);
        return "BLA{$year}{$sequence}";
    }

    /**
     * Get license type details
     */
    private function getLicenseTypeDetails(string $licenseCode): ?array
    {
        if (!$this->database) {
            return null;
        }

        try {
            return $this->database->selectOne(
                'SELECT * FROM business_license_types WHERE license_code = ? AND active = TRUE',
                [$licenseCode]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Trigger workflow
     */
    private function triggerWorkflow(int $itemId, string $action, string $workflowType = 'business_license_application'): void
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
            $totalLicenses = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM business_licenses'
            )['count'] ?? 0;

            $activeLicenses = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM business_licenses WHERE status = "active"'
            )['count'] ?? 0;

            $pendingApplications = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM business_license_applications WHERE status = "submitted"'
            )['count'] ?? 0;

            $revenue = $this->database->selectOne(
                'SELECT SUM(annual_fee) as total FROM business_licenses WHERE status = "active"'
            )['total'] ?? 0;

            return [
                'total_licenses' => $totalLicenses,
                'active_licenses' => $activeLicenses,
                'pending_applications' => $pendingApplications,
                'annual_revenue' => $revenue
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
        $this->setServiceConfig('renewal_reminder_days', 30);
        $this->setServiceConfig('inspection_interval_months', 12);
        $this->setServiceConfig('grace_period_days', 30);
        $this->setServiceConfig('auto_renewal_enabled', false);
        $this->setServiceConfig('compliance_threshold', 80); // percentage
    }
}

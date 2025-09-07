<?php
/**
 * TPT Government Platform - Business Licenses Module
 *
 * Comprehensive business licensing and compliance management system
 * supporting various license types, renewals, and regulatory compliance
 */

namespace Modules\BusinessLicenses;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class BusinessLicensesModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Business Licenses',
        'version' => '2.1.0',
        'description' => 'Comprehensive business licensing and compliance management system',
        'author' => 'TPT Government Platform',
        'category' => 'business_services',
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
        'business_licenses.view' => 'View business license applications',
        'business_licenses.create' => 'Create new business license applications',
        'business_licenses.edit' => 'Edit business license applications',
        'business_licenses.approve' => 'Approve business license applications',
        'business_licenses.reject' => 'Reject business license applications',
        'business_licenses.renew' => 'Renew business licenses',
        'business_licenses.revoke' => 'Revoke business licenses',
        'business_licenses.compliance' => 'Manage compliance requirements'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'business_licenses' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'application_id' => 'VARCHAR(50) UNIQUE NOT NULL',
            'business_name' => 'VARCHAR(255) NOT NULL',
            'business_type' => 'VARCHAR(100) NOT NULL',
            'license_type' => 'VARCHAR(100) NOT NULL',
            'owner_id' => 'INT NOT NULL',
            'status' => "ENUM('draft','submitted','under_review','approved','rejected','expired','revoked') DEFAULT 'draft'",
            'application_date' => 'DATETIME NOT NULL',
            'approval_date' => 'DATETIME NULL',
            'expiry_date' => 'DATETIME NULL',
            'renewal_date' => 'DATETIME NULL',
            'fee_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
            'documents' => 'JSON',
            'requirements' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'license_types' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'code' => 'VARCHAR(50) UNIQUE NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'category' => 'VARCHAR(100) NOT NULL',
            'validity_period' => 'INT NOT NULL', // days
            'fee_amount' => 'DECIMAL(10,2) NOT NULL',
            'requirements' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'license_compliance' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'requirement_type' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'due_date' => 'DATETIME NOT NULL',
            'completion_date' => 'DATETIME NULL',
            'status' => "ENUM('pending','completed','overdue','waived') DEFAULT 'pending'",
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'license_renewals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'license_id' => 'INT NOT NULL',
            'renewal_date' => 'DATETIME NOT NULL',
            'fee_amount' => 'DECIMAL(10,2) NOT NULL',
            'status' => "ENUM('pending','paid','approved','rejected') DEFAULT 'pending'",
            'payment_id' => 'VARCHAR(255) NULL',
            'processed_by' => 'INT NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'GET',
            'path' => '/api/business-licenses',
            'handler' => 'getLicenses',
            'auth' => true,
            'permissions' => ['business_licenses.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/business-licenses',
            'handler' => 'createLicense',
            'auth' => true,
            'permissions' => ['business_licenses.create']
        ],
        [
            'method' => 'GET',
            'path' => '/api/business-licenses/{id}',
            'handler' => 'getLicense',
            'auth' => true,
            'permissions' => ['business_licenses.view']
        ],
        [
            'method' => 'PUT',
            'path' => '/api/business-licenses/{id}',
            'handler' => 'updateLicense',
            'auth' => true,
            'permissions' => ['business_licenses.edit']
        ],
        [
            'method' => 'POST',
            'path' => '/api/business-licenses/{id}/renew',
            'handler' => 'renewLicense',
            'auth' => true,
            'permissions' => ['business_licenses.renew']
        ],
        [
            'method' => 'POST',
            'path' => '/api/business-licenses/{id}/approve',
            'handler' => 'approveLicense',
            'auth' => true,
            'permissions' => ['business_licenses.approve']
        ],
        [
            'method' => 'POST',
            'path' => '/api/business-licenses/{id}/reject',
            'handler' => 'rejectLicense',
            'auth' => true,
            'permissions' => ['business_licenses.reject']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'license_application' => [
            'name' => 'Business License Application',
            'description' => 'Workflow for processing business license applications',
            'steps' => [
                'draft' => ['name' => 'Draft Application', 'next' => 'submitted'],
                'submitted' => ['name' => 'Application Submitted', 'next' => 'document_review'],
                'document_review' => ['name' => 'Document Review', 'next' => ['approved', 'rejected', 'additional_info']],
                'additional_info' => ['name' => 'Additional Information Required', 'next' => 'document_review'],
                'approved' => ['name' => 'License Approved', 'next' => 'issued'],
                'rejected' => ['name' => 'License Rejected', 'next' => null],
                'issued' => ['name' => 'License Issued', 'next' => null]
            ],
            'permissions' => [
                'draft' => ['business_licenses.create'],
                'submitted' => ['business_licenses.view'],
                'document_review' => ['business_licenses.edit'],
                'approved' => ['business_licenses.approve'],
                'rejected' => ['business_licenses.reject']
            ]
        ],
        'license_renewal' => [
            'name' => 'License Renewal',
            'description' => 'Workflow for license renewal process',
            'steps' => [
                'renewal_due' => ['name' => 'Renewal Due', 'next' => 'payment_pending'],
                'payment_pending' => ['name' => 'Payment Pending', 'next' => 'paid'],
                'paid' => ['name' => 'Payment Received', 'next' => 'approved'],
                'approved' => ['name' => 'Renewal Approved', 'next' => 'renewed'],
                'renewed' => ['name' => 'License Renewed', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'license_application' => [
            'name' => 'Business License Application',
            'fields' => [
                'business_name' => ['type' => 'text', 'required' => true, 'label' => 'Business Name'],
                'business_type' => ['type' => 'select', 'required' => true, 'label' => 'Business Type'],
                'license_type' => ['type' => 'select', 'required' => true, 'label' => 'License Type'],
                'owner_name' => ['type' => 'text', 'required' => true, 'label' => 'Owner Name'],
                'owner_email' => ['type' => 'email', 'required' => true, 'label' => 'Owner Email'],
                'owner_phone' => ['type' => 'phone', 'required' => true, 'label' => 'Owner Phone'],
                'business_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Address'],
                'tax_id' => ['type' => 'text', 'required' => true, 'label' => 'Tax ID'],
                'incorporation_date' => ['type' => 'date', 'required' => true, 'label' => 'Incorporation Date'],
                'employee_count' => ['type' => 'number', 'required' => true, 'label' => 'Number of Employees'],
                'annual_revenue' => ['type' => 'number', 'required' => true, 'label' => 'Annual Revenue'],
                'business_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Description']
            ],
            'documents' => [
                'business_registration' => ['required' => true, 'label' => 'Business Registration Certificate'],
                'tax_certificate' => ['required' => true, 'label' => 'Tax Certificate'],
                'owner_id' => ['required' => true, 'label' => 'Owner ID Document'],
                'proof_of_address' => ['required' => true, 'label' => 'Proof of Business Address'],
                'financial_statements' => ['required' => false, 'label' => 'Financial Statements']
            ]
        ],
        'license_renewal' => [
            'name' => 'License Renewal Application',
            'fields' => [
                'license_id' => ['type' => 'hidden', 'required' => true],
                'renewal_period' => ['type' => 'select', 'required' => true, 'options' => ['1_year', '2_years', '3_years']],
                'business_changes' => ['type' => 'textarea', 'required' => false, 'label' => 'Business Changes'],
                'compliance_status' => ['type' => 'checkbox', 'required' => true, 'label' => 'Compliance Requirements Met']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'license_overview' => [
            'name' => 'License Overview Report',
            'description' => 'Summary of all business licenses',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'license_type' => ['type' => 'select', 'required' => false],
                'status' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'application_id', 'business_name', 'license_type', 'status',
                'application_date', 'expiry_date', 'fee_amount'
            ]
        ],
        'license_compliance' => [
            'name' => 'License Compliance Report',
            'description' => 'Compliance status of business licenses',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'compliance_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'business_name', 'license_type', 'requirement_type',
                'due_date', 'status', 'completion_date'
            ]
        ],
        'revenue_report' => [
            'name' => 'License Revenue Report',
            'description' => 'Revenue generated from business licenses',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'license_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'license_type', 'total_applications', 'total_revenue',
                'average_fee', 'monthly_trend'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'application_submitted' => [
            'name' => 'License Application Submitted',
            'template' => 'Your business license application has been submitted successfully.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_created']
        ],
        'application_approved' => [
            'name' => 'License Application Approved',
            'template' => 'Congratulations! Your business license application has been approved.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_approved']
        ],
        'application_rejected' => [
            'name' => 'License Application Rejected',
            'template' => 'Your business license application has been rejected. Please review the feedback.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['application_rejected']
        ],
        'renewal_reminder' => [
            'name' => 'License Renewal Reminder',
            'template' => 'Your business license is due for renewal in {days} days.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['renewal_due']
        ],
        'renewal_overdue' => [
            'name' => 'License Renewal Overdue',
            'template' => 'Your business license renewal is overdue. Please renew immediately.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['renewal_overdue']
        ]
    ];

    /**
     * License types configuration
     */
    private array $licenseTypes = [];

    /**
     * Compliance requirements
     */
    private array $complianceRequirements = [];

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
            'auto_approval' => false,
            'renewal_reminder_days' => 30,
            'grace_period_days' => 7,
            'max_upload_size' => 10485760, // 10MB
            'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
            'fee_calculation' => 'fixed', // fixed, percentage, tiered
            'payment_gateway' => 'stripe',
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
        $this->initializeLicenseTypes();
        $this->initializeComplianceRequirements();
    }

    /**
     * Initialize license types
     */
    private function initializeLicenseTypes(): void
    {
        $this->licenseTypes = [
            'general_business' => [
                'code' => 'GBL',
                'name' => 'General Business License',
                'category' => 'general',
                'validity_period' => 365,
                'fee_amount' => 500.00,
                'requirements' => [
                    'business_registration',
                    'tax_certificate',
                    'owner_id',
                    'proof_of_address'
                ]
            ],
            'professional_services' => [
                'code' => 'PSL',
                'name' => 'Professional Services License',
                'category' => 'professional',
                'validity_period' => 365,
                'fee_amount' => 750.00,
                'requirements' => [
                    'business_registration',
                    'professional_certification',
                    'liability_insurance',
                    'background_check'
                ]
            ],
            'retail_trade' => [
                'code' => 'RTL',
                'name' => 'Retail Trade License',
                'category' => 'retail',
                'validity_period' => 365,
                'fee_amount' => 300.00,
                'requirements' => [
                    'business_registration',
                    'tax_certificate',
                    'zoning_approval',
                    'health_permit'
                ]
            ],
            'food_service' => [
                'code' => 'FSL',
                'name' => 'Food Service License',
                'category' => 'food',
                'validity_period' => 365,
                'fee_amount' => 1000.00,
                'requirements' => [
                    'business_registration',
                    'health_inspection',
                    'food_handler_certificates',
                    'facility_inspection'
                ]
            ],
            'construction' => [
                'code' => 'CNL',
                'name' => 'Construction License',
                'category' => 'construction',
                'validity_period' => 365,
                'fee_amount' => 1200.00,
                'requirements' => [
                    'business_registration',
                    'contractor_certification',
                    'insurance_certificate',
                    'bond_certificate'
                ]
            ]
        ];
    }

    /**
     * Initialize compliance requirements
     */
    private function initializeComplianceRequirements(): void
    {
        $this->complianceRequirements = [
            'annual_report' => [
                'name' => 'Annual Business Report',
                'description' => 'Submit annual business report',
                'frequency' => 'annual',
                'due_month' => 12,
                'grace_period' => 30
            ],
            'tax_filing' => [
                'name' => 'Tax Filing',
                'description' => 'File annual business taxes',
                'frequency' => 'annual',
                'due_month' => 4,
                'grace_period' => 90
            ],
            'insurance_verification' => [
                'name' => 'Insurance Verification',
                'description' => 'Verify current insurance coverage',
                'frequency' => 'annual',
                'due_month' => 1,
                'grace_period' => 30
            ],
            'background_check' => [
                'name' => 'Background Check',
                'description' => 'Renew background check clearance',
                'frequency' => 'biennial',
                'due_month' => 6,
                'grace_period' => 60
            ]
        ];
    }

    /**
     * Create business license application
     */
    public function createLicenseApplication(array $applicationData): array
    {
        // Validate application data
        $validation = $this->validateApplicationData($applicationData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate application ID
        $applicationId = $this->generateApplicationId();

        // Calculate fee
        $licenseType = $this->licenseTypes[$applicationData['license_type']] ?? null;
        if (!$licenseType) {
            return [
                'success' => false,
                'error' => 'Invalid license type'
            ];
        }

        $feeAmount = $this->calculateLicenseFee($licenseType, $applicationData);

        // Create application record
        $application = [
            'application_id' => $applicationId,
            'business_name' => $applicationData['business_name'],
            'business_type' => $applicationData['business_type'],
            'license_type' => $applicationData['license_type'],
            'owner_id' => $applicationData['owner_id'],
            'status' => 'draft',
            'application_date' => date('Y-m-d H:i:s'),
            'fee_amount' => $feeAmount,
            'documents' => $applicationData['documents'] ?? [],
            'requirements' => $licenseType['requirements'],
            'notes' => $applicationData['notes'] ?? ''
        ];

        // Save to database
        $this->saveApplication($application);

        // Start workflow
        $this->startApplicationWorkflow($applicationId);

        // Send notification
        $this->sendNotification('application_submitted', $applicationData['owner_id'], [
            'application_id' => $applicationId,
            'business_name' => $applicationData['business_name']
        ]);

        return [
            'success' => true,
            'application_id' => $applicationId,
            'fee_amount' => $feeAmount,
            'requirements' => $licenseType['requirements']
        ];
    }

    /**
     * Submit license application
     */
    public function submitLicenseApplication(string $applicationId): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        if ($application['status'] !== 'draft') {
            return [
                'success' => false,
                'error' => 'Application already submitted'
            ];
        }

        // Validate all requirements are met
        $validation = $this->validateApplicationRequirements($application);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Update status
        $this->updateApplicationStatus($applicationId, 'submitted');

        // Move workflow to next step
        $this->advanceWorkflow($applicationId, 'submitted');

        // Send notification
        $this->sendNotification('application_submitted', $application['owner_id'], [
            'application_id' => $applicationId
        ]);

        return [
            'success' => true,
            'message' => 'Application submitted successfully'
        ];
    }

    /**
     * Approve license application
     */
    public function approveLicenseApplication(string $applicationId, array $approvalData = []): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Calculate expiry date
        $licenseType = $this->licenseTypes[$application['license_type']];
        $expiryDate = date('Y-m-d H:i:s', strtotime("+{$licenseType['validity_period']} days"));

        // Update application
        $this->updateApplication($applicationId, [
            'status' => 'approved',
            'approval_date' => date('Y-m-d H:i:s'),
            'expiry_date' => $expiryDate,
            'notes' => $approvalData['notes'] ?? ''
        ]);

        // Create compliance requirements
        $this->createComplianceRequirements($applicationId, $licenseType);

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'approved');

        // Send notification
        $this->sendNotification('application_approved', $application['owner_id'], [
            'application_id' => $applicationId,
            'expiry_date' => $expiryDate
        ]);

        return [
            'success' => true,
            'expiry_date' => $expiryDate,
            'message' => 'License application approved'
        ];
    }

    /**
     * Reject license application
     */
    public function rejectLicenseApplication(string $applicationId, string $reason): array
    {
        $application = $this->getApplication($applicationId);
        if (!$application) {
            return [
                'success' => false,
                'error' => 'Application not found'
            ];
        }

        // Update application
        $this->updateApplication($applicationId, [
            'status' => 'rejected',
            'notes' => $reason
        ]);

        // Advance workflow
        $this->advanceWorkflow($applicationId, 'rejected');

        // Send notification
        $this->sendNotification('application_rejected', $application['owner_id'], [
            'application_id' => $applicationId,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => 'License application rejected'
        ];
    }

    /**
     * Renew license
     */
    public function renewLicense(string $licenseId, array $renewalData = []): array
    {
        $license = $this->getLicense($licenseId);
        if (!$license) {
            return [
                'success' => false,
                'error' => 'License not found'
            ];
        }

        // Check if renewal is allowed
        if (!$this->canRenewLicense($license)) {
            return [
                'success' => false,
                'error' => 'License cannot be renewed at this time'
            ];
        }

        // Calculate renewal fee
        $licenseType = $this->licenseTypes[$license['license_type']];
        $renewalFee = $this->calculateRenewalFee($licenseType, $renewalData);

        // Create renewal record
        $renewalId = $this->createRenewalRecord($licenseId, $renewalFee, $renewalData);

        return [
            'success' => true,
            'renewal_id' => $renewalId,
            'fee_amount' => $renewalFee,
            'message' => 'License renewal initiated'
        ];
    }

    /**
     * Process license renewal payment
     */
    public function processRenewalPayment(string $renewalId, array $paymentData): array
    {
        $renewal = $this->getRenewal($renewalId);
        if (!$renewal) {
            return [
                'success' => false,
                'error' => 'Renewal not found'
            ];
        }

        // Process payment
        $paymentGateway = new PaymentGateway();
        $paymentResult = $paymentGateway->processPayment([
            'amount' => $renewal['fee_amount'],
            'currency' => 'USD',
            'method' => $paymentData['method'],
            'description' => "License Renewal - {$renewal['license_id']}",
            'metadata' => [
                'renewal_id' => $renewalId,
                'license_id' => $renewal['license_id']
            ]
        ]);

        if (!$paymentResult['success']) {
            return [
                'success' => false,
                'error' => 'Payment processing failed'
            ];
        }

        // Update renewal status
        $this->updateRenewalStatus($renewalId, 'paid', $paymentResult['transaction_id']);

        // Extend license expiry
        $this->extendLicenseExpiry($renewal['license_id']);

        return [
            'success' => true,
            'transaction_id' => $paymentResult['transaction_id'],
            'message' => 'License renewal payment processed successfully'
        ];
    }

    /**
     * Get license compliance status
     */
    public function getLicenseCompliance(string $licenseId): array
    {
        $complianceRecords = $this->getComplianceRecords($licenseId);

        $compliance = [
            'license_id' => $licenseId,
            'overall_status' => 'compliant',
            'requirements' => []
        ];

        foreach ($complianceRecords as $record) {
            $status = $record['status'];

            if ($status === 'overdue') {
                $compliance['overall_status'] = 'non_compliant';
            } elseif ($status === 'pending' && $compliance['overall_status'] === 'compliant') {
                $compliance['overall_status'] = 'pending';
            }

            $compliance['requirements'][] = [
                'type' => $record['requirement_type'],
                'description' => $record['description'],
                'due_date' => $record['due_date'],
                'status' => $status,
                'completion_date' => $record['completion_date']
            ];
        }

        return $compliance;
    }

    /**
     * Generate license report
     */
    public function generateLicenseReport(array $filters = []): array
    {
        $query = "SELECT * FROM business_licenses WHERE 1=1";

        if (isset($filters['status'])) {
            $query .= " AND status = '{$filters['status']}'";
        }

        if (isset($filters['license_type'])) {
            $query .= " AND license_type = '{$filters['license_type']}'";
        }

        if (isset($filters['date_from'])) {
            $query .= " AND application_date >= '{$filters['date_from']}'";
        }

        if (isset($filters['date_to'])) {
            $query .= " AND application_date <= '{$filters['date_to']}'";
        }

        // Execute query and return results
        return [
            'filters' => $filters,
            'data' => [], // Would contain actual query results
            'generated_at' => date('c')
        ];
    }

    /**
     * Validate application data
     */
    private function validateApplicationData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'business_name', 'business_type', 'license_type',
            'owner_name', 'owner_email', 'business_address'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        if (isset($data['owner_email']) && !filter_var($data['owner_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (!isset($this->licenseTypes[$data['license_type'] ?? ''])) {
            $errors[] = "Invalid license type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate application ID
     */
    private function generateApplicationId(): string
    {
        return 'BL' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate license fee
     */
    private function calculateLicenseFee(array $licenseType, array $applicationData): float
    {
        $baseFee = $licenseType['fee_amount'];

        // Apply any additional calculations based on business size, etc.
        // For now, return base fee
        return $baseFee;
    }

    /**
     * Save application to database
     */
    private function saveApplication(array $application): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO business_licenses (
                application_id, business_name, business_type, license_type,
                owner_id, status, application_date, fee_amount, documents,
                requirements, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $application['application_id'],
                $application['business_name'],
                $application['business_type'],
                $application['license_type'],
                $application['owner_id'],
                $application['status'],
                $application['application_date'],
                $application['fee_amount'],
                json_encode($application['documents']),
                json_encode($application['requirements']),
                $application['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get application by ID
     */
    private function getApplication(string $applicationId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM business_licenses WHERE application_id = ?";
            $result = $db->fetch($sql, [$applicationId]);

            if ($result) {
                $result['documents'] = json_decode($result['documents'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting application: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update application status
     */
    private function updateApplicationStatus(string $applicationId, string $status): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE business_licenses SET status = ? WHERE application_id = ?";
            return $db->execute($sql, [$status, $applicationId]);
        } catch (\Exception $e) {
            error_log("Error updating application status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update application
     */
    private function updateApplication(string $applicationId, array $data): bool
    {
        try {
            $db = Database::getInstance();

            $setParts = [];
            $params = [];

            foreach ($data as $field => $value) {
                if (is_array($value)) {
                    $setParts[] = "{$field} = ?";
                    $params[] = json_encode($value);
                } else {
                    $setParts[] = "{$field} = ?";
                    $params[] = $value;
                }
            }

            $params[] = $applicationId;

            $sql = "UPDATE business_licenses SET " . implode(', ', $setParts) . " WHERE application_id = ?";
            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error updating application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start application workflow
     */
    private function startApplicationWorkflow(string $applicationId): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            return $workflowEngine->startWorkflow('license_application', $applicationId);
        } catch (\Exception $e) {
            error_log("Error starting application workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Advance workflow
     */
    private function advanceWorkflow(string $applicationId, string $step): bool
    {
        try {
            $workflowEngine = new WorkflowEngine();
            return $workflowEngine->advanceWorkflow($applicationId, $step);
        } catch (\Exception $e) {
            error_log("Error advancing workflow: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, int $userId, array $data): bool
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
     * Validate application requirements
     */
    private function validateApplicationRequirements(array $application): array
    {
        $errors = [];
        $requirements = $application['requirements'] ?? [];

        // Check if all required documents are uploaded
        foreach ($requirements as $requirement) {
            if (!isset($application['documents'][$requirement]) || empty($application['documents'][$requirement])) {
                $errors[] = "Required document missing: {$requirement}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Create compliance requirements
     */
    private function createComplianceRequirements(string $applicationId, array $licenseType): bool
    {
        try {
            $db = Database::getInstance();

            foreach ($this->complianceRequirements as $requirementType => $requirement) {
                $sql = "INSERT INTO license_compliance (
                    license_id, requirement_type, description, due_date
                ) VALUES (?, ?, ?, ?)";

                $dueDate = $this->calculateComplianceDueDate($requirement);

                $params = [
                    $applicationId,
                    $requirementType,
                    $requirement['description'],
                    $dueDate
                ];

                if (!$db->execute($sql, $params)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error creating compliance requirements: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate compliance due date
     */
    private function calculateComplianceDueDate(array $requirement): string
    {
        $baseDate = date('Y-m-d');

        if ($requirement['frequency'] === 'annual') {
            return date('Y-m-d', strtotime($baseDate . ' +' . $requirement['due_month'] . ' months'));
        } elseif ($requirement['frequency'] === 'biennial') {
            return date('Y-m-d', strtotime($baseDate . ' +2 years +' . $requirement['due_month'] . ' months'));
        }

        return date('Y-m-d', strtotime($baseDate . ' +1 year'));
    }

    /**
     * Get license by ID
     */
    private function getLicense(string $licenseId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM business_licenses WHERE application_id = ?";
            return $db->fetch($sql, [$licenseId]);
        } catch (\Exception $e) {
            error_log("Error getting license: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if license can be renewed
     */
    private function canRenewLicense(array $license): bool
    {
        $currentDate = date('Y-m-d');
        $expiryDate = $license['expiry_date'];

        // Allow renewal up to 90 days after expiry
        $renewalDeadline = date('Y-m-d', strtotime($expiryDate . ' +90 days'));

        return $currentDate <= $renewalDeadline && $license['status'] !== 'revoked';
    }

    /**
     * Calculate renewal fee
     */
    private function calculateRenewalFee(array $licenseType, array $renewalData): float
    {
        $baseFee = $licenseType['fee_amount'];

        // Apply late fee if renewal is after expiry
        if (isset($renewalData['is_late']) && $renewalData['is_late']) {
            $baseFee *= 1.25; // 25% late fee
        }

        return $baseFee;
    }

    /**
     * Create renewal record
     */
    private function createRenewalRecord(string $licenseId, float $fee, array $data): string
    {
        try {
            $db = Database::getInstance();

            $renewalId = 'RN' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO license_renewals (
                license_id, renewal_date, fee_amount, status
            ) VALUES (?, ?, ?, ?)";

            $params = [
                $licenseId,
                date('Y-m-d H:i:s'),
                $fee,
                'pending'
            ];

            if ($db->execute($sql, $params)) {
                return $renewalId;
            }

            return '';
        } catch (\Exception $e) {
            error_log("Error creating renewal record: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Get renewal by ID
     */
    private function getRenewal(string $renewalId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM license_renewals WHERE id = ?";
            return $db->fetch($sql, [$renewalId]);
        } catch (\Exception $e) {
            error_log("Error getting renewal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update renewal status
     */
    private function updateRenewalStatus(string $renewalId, string $status, string $transactionId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE license_renewals SET status = ?, payment_id = ? WHERE id = ?";
            return $db->execute($sql, [$status, $transactionId, $renewalId]);
        } catch (\Exception $e) {
            error_log("Error updating renewal status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extend license expiry
     */
    private function extendLicenseExpiry(string $licenseId): bool
    {
        try {
            $db = Database::getInstance();

            // Get current license
            $license = $this->getLicense($licenseId);
            if (!$license) {
                return false;
            }

            // Get license type for validity period
            $licenseType = $this->licenseTypes[$license['license_type']];
            $newExpiryDate = date('Y-m-d H:i:s', strtotime($license['expiry_date'] . " +{$licenseType['validity_period']} days"));

            $sql = "UPDATE business_licenses SET expiry_date = ?, renewal_date = ? WHERE application_id = ?";
            return $db->execute($sql, [$newExpiryDate, date('Y-m-d H:i:s'), $licenseId]);
        } catch (\Exception $e) {
            error_log("Error extending license expiry: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get compliance records for license
     */
    private function getComplianceRecords(string $licenseId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM license_compliance WHERE license_id = ?";
            return $db->fetchAll($sql, [$licenseId]);
        } catch (\Exception $e) {
            error_log("Error getting compliance records: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get licenses (API handler)
     */
    public function getLicenses(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM business_licenses WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['license_type'])) {
                $sql .= " AND license_type = ?";
                $params[] = $filters['license_type'];
            }

            if (isset($filters['owner_id'])) {
                $sql .= " AND owner_id = ?";
                $params[] = $filters['owner_id'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['documents'] = json_decode($result['documents'], true);
                $result['requirements'] = json_decode($result['requirements'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting licenses: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve licenses'
            ];
        }
    }

    /**
     * Get license (API handler)
     */
    public function getLicense(string $applicationId): array
    {
        $license = $this->getApplication($applicationId);

        if (!$license) {
            return [
                'success' => false,
                'error' => 'License not found'
            ];
        }

        return [
            'success' => true,
            'data' => $license
        ];
    }

    /**
     * Create license (API handler)
     */
    public function createLicense(array $data): array
    {
        return $this->createLicenseApplication($data);
    }

    /**
     * Update license (API handler)
     */
    public function updateLicense(string $applicationId, array $data): array
    {
        try {
            $license = $this->getApplication($applicationId);

            if (!$license) {
                return [
                    'success' => false,
                    'error' => 'License not found'
                ];
            }

            if ($license['status'] !== 'draft') {
                return [
                    'success' => false,
                    'error' => 'License cannot be modified'
                ];
            }

            $this->updateApplication($applicationId, $data);

            return [
                'success' => true,
                'message' => 'License updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("Error updating license: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update license'
            ];
        }
    }

    /**
     * Renew license (API handler)
     */
    public function renewLicense(string $licenseId, array $renewalData): array
    {
        return $this->renewLicense($licenseId, $renewalData);
    }

    /**
     * Approve license (API handler)
     */
    public function approveLicense(string $applicationId, array $approvalData): array
    {
        return $this->approveLicenseApplication($applicationId, $approvalData);
    }

    /**
     * Reject license (API handler)
     */
    public function rejectLicense(string $applicationId, string $reason): array
    {
        return $this->rejectLicenseApplication($applicationId, $reason);
    }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(): array
    {
        try {
            $db = Database::getInstance();

            // Get license statistics
            $licenseStats = $db->fetch("
                SELECT
                    COUNT(*) as total_applications,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_licenses,
                    COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_applications,
                    COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_licenses,
                    SUM(fee_amount) as total_revenue
                FROM business_licenses
            ");

            // Get compliance statistics
            $complianceStats = $db->fetch("
                SELECT
                    COUNT(*) as total_requirements,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requirements
                FROM license_compliance
            ");

            $complianceRate = $complianceStats['total_requirements'] > 0 ?
                round(($complianceStats['completed_requirements'] / $complianceStats['total_requirements']) * 100, 2) : 0;

            return [
                'total_applications' => $licenseStats['total_applications'] ?? 0,
                'approved_licenses' => $licenseStats['approved_licenses'] ?? 0,
                'pending_applications' => $licenseStats['pending_applications'] ?? 0,
                'expired_licenses' => $licenseStats['expired_licenses'] ?? 0,
                'total_revenue' => $licenseStats['total_revenue'] ?? 0.00,
                'compliance_rate' => $complianceRate
            ];
        } catch (\Exception $e) {
            error_log("Error getting module statistics: " . $e->getMessage());
            return [
                'total_applications' => 0,
                'approved_licenses' => 0,
                'pending_applications' => 0,
                'expired_licenses' => 0,
                'total_revenue' => 0.00,
                'compliance_rate' => 0.0
            ];
        }
    }
}

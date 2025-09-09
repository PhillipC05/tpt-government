<?php
/**
 * TPT Government Platform - Revenue & Taxation Module
 *
 * Comprehensive tax collection, assessment, and compliance management system
 * for government revenue and taxation departments
 */

namespace Modules\RevenueTaxation;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\AIService;

class RevenueModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Revenue & Taxation',
        'version' => '2.0.0',
        'description' => 'Comprehensive tax collection, assessment, and compliance management system',
        'author' => 'TPT Government Platform',
        'category' => 'revenue_services',
        'dependencies' => ['database', 'workflow', 'notification', 'ai']
    ];

    /**
     * Module dependencies
     */
    protected array $dependencies = [
        ['name' => 'Database', 'version' => '>=1.0.0'],
        ['name' => 'WorkflowEngine', 'version' => '>=1.0.0'],
        ['name' => 'NotificationManager', 'version' => '>=1.0.0'],
        ['name' => 'AIService', 'version' => '>=1.0.0']
    ];

    /**
     * Module permissions
     */
    protected array $permissions = [
        'revenue.view' => 'View tax records and filings',
        'revenue.create' => 'Create tax assessments and filings',
        'revenue.update' => 'Update tax information and assessments',
        'revenue.audit' => 'Conduct tax audits and examinations',
        'revenue.collect' => 'Process tax collections and payments',
        'revenue.refund' => 'Process tax refunds and adjustments',
        'revenue.enforce' => 'Tax enforcement and collection actions',
        'revenue.report' => 'Access tax reports and analytics',
        'revenue.admin' => 'Full administrative access to revenue system',
        'revenue.compliance' => 'Tax compliance monitoring and verification'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'tax_payers' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'taxpayer_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'tax_id' => 'VARCHAR(20) UNIQUE NOT NULL', // SSN, EIN, etc.
            'entity_type' => "ENUM('individual','business','organization','trust','other') DEFAULT 'individual'",
            'first_name' => 'VARCHAR(100)',
            'last_name' => 'VARCHAR(100)',
            'business_name' => 'VARCHAR(255)',
            'address' => 'TEXT',
            'city' => 'VARCHAR(100)',
            'state' => 'VARCHAR(50)',
            'zip_code' => 'VARCHAR(20)',
            'country' => 'VARCHAR(50)',
            'phone' => 'VARCHAR(20)',
            'email' => 'VARCHAR(255)',
            'date_of_birth' => 'DATE',
            'registration_date' => 'DATE',
            'status' => "ENUM('active','inactive','suspended','dissolved') DEFAULT 'active'",
            'tax_class' => 'VARCHAR(50)', // Tax classification
            'filing_status' => "ENUM('single','married_joint','married_separate','head_household','widow')",
            'dependents' => 'INT DEFAULT 0',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_returns' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'return_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'tax_year' => 'YEAR NOT NULL',
            'form_type' => 'VARCHAR(20) NOT NULL', // 1040, 1120, etc.
            'filing_method' => "ENUM('paper','electronic','software') DEFAULT 'electronic'",
            'status' => "ENUM('draft','filed','accepted','rejected','amended','audited') DEFAULT 'draft'",
            'filing_date' => 'DATE',
            'due_date' => 'DATE',
            'extension_date' => 'DATE',
            'gross_income' => 'DECIMAL(15,2)',
            'adjusted_gross_income' => 'DECIMAL(15,2)',
            'taxable_income' => 'DECIMAL(15,2)',
            'tax_owed' => 'DECIMAL(12,2)',
            'withholdings' => 'DECIMAL(12,2)',
            'payments' => 'DECIMAL(12,2)',
            'refund_amount' => 'DECIMAL(12,2)',
            'penalty_amount' => 'DECIMAL(10,2)',
            'interest_amount' => 'DECIMAL(10,2)',
            'total_owed' => 'DECIMAL(12,2)',
            'balance_due' => 'DECIMAL(12,2)',
            'verification_status' => "ENUM('pending','verified','flagged','under_review') DEFAULT 'pending'",
            'attachments' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_assessments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'assessment_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'tax_year' => 'YEAR NOT NULL',
            'assessment_type' => "ENUM('income','property','sales','business','other') NOT NULL",
            'assessment_date' => 'DATE DEFAULT CURRENT_DATE',
            'due_date' => 'DATE',
            'original_amount' => 'DECIMAL(12,2) NOT NULL',
            'adjustments' => 'DECIMAL(12,2) DEFAULT 0.00',
            'final_amount' => 'DECIMAL(12,2)',
            'status' => "ENUM('pending','issued','paid','overdue','appealed','abated') DEFAULT 'pending'",
            'payment_plan' => 'BOOLEAN DEFAULT FALSE',
            'installments' => 'JSON',
            'interest_rate' => 'DECIMAL(5,4)',
            'penalty_rate' => 'DECIMAL(5,4)',
            'description' => 'TEXT',
            'basis' => 'TEXT', // Legal basis for assessment
            'appeal_deadline' => 'DATE',
            'collection_actions' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_payments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'payment_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'assessment_id' => 'VARCHAR(20)',
            'return_id' => 'VARCHAR(20)',
            'payment_date' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'amount' => 'DECIMAL(12,2) NOT NULL',
            'payment_method' => "ENUM('check','electronic','credit_card','cash','wire','other') NOT NULL",
            'reference_number' => 'VARCHAR(100)',
            'transaction_id' => 'VARCHAR(100)',
            'status' => "ENUM('pending','processed','cleared','failed','refunded') DEFAULT 'pending'",
            'applied_to' => 'JSON', // How payment was applied
            'reversal_reason' => 'VARCHAR(255)',
            'processed_by' => 'INT',
            'notes' => 'TEXT',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'tax_audits' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'audit_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'tax_year' => 'YEAR NOT NULL',
            'audit_type' => "ENUM('correspondence','office','field','criminal_investigation') DEFAULT 'correspondence'",
            'status' => "ENUM('initiated','in_progress','completed','suspended','terminated') DEFAULT 'initiated'",
            'initiated_date' => 'DATE DEFAULT CURRENT_DATE',
            'due_date' => 'DATE',
            'completed_date' => 'DATE',
            'auditor_id' => 'INT',
            'supervisor_id' => 'INT',
            'scope' => 'TEXT', // What is being audited
            'findings' => 'TEXT',
            'recommendations' => 'TEXT',
            'additional_tax' => 'DECIMAL(12,2)',
            'penalty_amount' => 'DECIMAL(10,2)',
            'interest_amount' => 'DECIMAL(10,2)',
            'appeal_filed' => 'BOOLEAN DEFAULT FALSE',
            'appeal_date' => 'DATE',
            'appeal_result' => 'VARCHAR(255)',
            'attachments' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_appeals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'appeal_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'assessment_id' => 'VARCHAR(20)',
            'audit_id' => 'VARCHAR(20)',
            'appeal_type' => "ENUM('assessment','audit','penalty','other') NOT NULL",
            'status' => "ENUM('filed','under_review','scheduled','decided','withdrawn') DEFAULT 'filed'",
            'filed_date' => 'DATE DEFAULT CURRENT_DATE',
            'hearing_date' => 'DATE',
            'decision_date' => 'DATE',
            'grounds' => 'TEXT', // Basis for appeal
            'relief_requested' => 'TEXT',
            'decision' => 'TEXT',
            'amount_abated' => 'DECIMAL(12,2)',
            'judge_id' => 'INT',
            'appellant_attorney' => 'VARCHAR(255)',
            'government_attorney' => 'VARCHAR(255)',
            'transcript' => 'VARCHAR(500)', // Path to transcript
            'attachments' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_liens' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'lien_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'assessment_id' => 'VARCHAR(20) NOT NULL',
            'lien_type' => "ENUM('federal_tax','state_tax','local_tax','other') NOT NULL",
            'amount' => 'DECIMAL(12,2) NOT NULL',
            'filed_date' => 'DATE DEFAULT CURRENT_DATE',
            'released_date' => 'DATE',
            'status' => "ENUM('active','released','satisfied','withdrawn') DEFAULT 'active'",
            'property_description' => 'TEXT',
            'priority' => 'INT', // Lien priority
            'certificate_number' => 'VARCHAR(50)',
            'recorded_date' => 'DATE',
            'expiration_date' => 'DATE',
            'collection_actions' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_refunds' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'refund_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'return_id' => 'VARCHAR(20) NOT NULL',
            'amount' => 'DECIMAL(12,2) NOT NULL',
            'status' => "ENUM('pending','approved','issued','rejected','stopped') DEFAULT 'pending'",
            'requested_date' => 'DATE DEFAULT CURRENT_DATE',
            'approved_date' => 'DATE',
            'issued_date' => 'DATE',
            'check_number' => 'VARCHAR(50)',
            'direct_deposit' => 'BOOLEAN DEFAULT FALSE',
            'bank_account' => 'VARCHAR(50)', // Last 4 digits
            'routing_number' => 'VARCHAR(20)',
            'rejection_reason' => 'TEXT',
            'offset_amount' => 'DECIMAL(12,2)', // Amount offset against other debts
            'net_refund' => 'DECIMAL(12,2)',
            'processed_by' => 'INT',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_compliance' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'compliance_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'taxpayer_id' => 'VARCHAR(20) NOT NULL',
            'compliance_type' => "ENUM('filing','payment','reporting','registration','other') NOT NULL",
            'status' => "ENUM('compliant','non_compliant','warning','under_review','corrected') DEFAULT 'compliant'",
            'issue_date' => 'DATE DEFAULT CURRENT_DATE',
            'due_date' => 'DATE',
            'resolved_date' => 'DATE',
            'description' => 'TEXT',
            'severity' => "ENUM('low','medium','high','critical') DEFAULT 'low'",
            'penalty_amount' => 'DECIMAL(10,2)',
            'corrective_action' => 'TEXT',
            'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
            'assigned_to' => 'INT',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'tax_analytics' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'date' => 'DATE NOT NULL',
            'tax_year' => 'YEAR',
            'total_revenue' => 'DECIMAL(15,2) DEFAULT 0.00',
            'individual_returns' => 'INT DEFAULT 0',
            'business_returns' => 'INT DEFAULT 0',
            'total_refunds' => 'DECIMAL(12,2) DEFAULT 0.00',
            'avg_processing_time' => 'INT DEFAULT 0', // days
            'audit_rate' => 'DECIMAL(5,4) DEFAULT 0.0000',
            'appeal_rate' => 'DECIMAL(5,4) DEFAULT 0.0000',
            'collection_rate' => 'DECIMAL(5,4) DEFAULT 0.0000',
            'compliance_rate' => 'DECIMAL(5,4) DEFAULT 0.0000',
            'delinquent_accounts' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        // Taxpayer Management
        ['method' => 'GET', 'path' => '/api/revenue/taxpayers', 'handler' => 'getTaxpayers', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/taxpayers', 'handler' => 'createTaxpayer', 'auth' => true, 'permissions' => ['revenue.create']],
        ['method' => 'GET', 'path' => '/api/revenue/taxpayers/{id}', 'handler' => 'getTaxpayer', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/revenue/taxpayers/{id}', 'handler' => 'updateTaxpayer', 'auth' => true, 'permissions' => ['revenue.update']],

        // Tax Returns
        ['method' => 'GET', 'path' => '/api/revenue/returns', 'handler' => 'getReturns', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/returns', 'handler' => 'fileReturn', 'auth' => true, 'permissions' => ['revenue.create']],
        ['method' => 'GET', 'path' => '/api/revenue/returns/{id}', 'handler' => 'getReturn', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/revenue/returns/{id}', 'handler' => 'updateReturn', 'auth' => true, 'permissions' => ['revenue.update']],

        // Tax Assessments
        ['method' => 'GET', 'path' => '/api/revenue/assessments', 'handler' => 'getAssessments', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/assessments', 'handler' => 'createAssessment', 'auth' => true, 'permissions' => ['revenue.create']],
        ['method' => 'GET', 'path' => '/api/revenue/assessments/{id}', 'handler' => 'getAssessment', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/revenue/assessments/{id}', 'handler' => 'updateAssessment', 'auth' => true, 'permissions' => ['revenue.update']],

        // Tax Payments
        ['method' => 'GET', 'path' => '/api/revenue/payments', 'handler' => 'getPayments', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/payments', 'handler' => 'makePayment', 'auth' => true, 'permissions' => ['revenue.collect']],
        ['method' => 'GET', 'path' => '/api/revenue/payments/{id}', 'handler' => 'getPayment', 'auth' => true],

        // Tax Audits
        ['method' => 'GET', 'path' => '/api/revenue/audits', 'handler' => 'getAudits', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/audits', 'handler' => 'initiateAudit', 'auth' => true, 'permissions' => ['revenue.audit']],
        ['method' => 'GET', 'path' => '/api/revenue/audits/{id}', 'handler' => 'getAudit', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/revenue/audits/{id}', 'handler' => 'updateAudit', 'auth' => true, 'permissions' => ['revenue.audit']],

        // Tax Appeals
        ['method' => 'GET', 'path' => '/api/revenue/appeals', 'handler' => 'getAppeals', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/appeals', 'handler' => 'fileAppeal', 'auth' => true, 'permissions' => ['revenue.create']],
        ['method' => 'GET', 'path' => '/api/revenue/appeals/{id}', 'handler' => 'getAppeal', 'auth' => true],

        // Tax Refunds
        ['method' => 'GET', 'path' => '/api/revenue/refunds', 'handler' => 'getRefunds', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/refunds', 'handler' => 'processRefund', 'auth' => true, 'permissions' => ['revenue.refund']],
        ['method' => 'GET', 'path' => '/api/revenue/refunds/{id}', 'handler' => 'getRefund', 'auth' => true],

        // Tax Compliance
        ['method' => 'GET', 'path' => '/api/revenue/compliance', 'handler' => 'getComplianceIssues', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/revenue/compliance', 'handler' => 'createComplianceIssue', 'auth' => true, 'permissions' => ['revenue.compliance']],

        // Analytics and Reporting
        ['method' => 'GET', 'path' => '/api/revenue/analytics', 'handler' => 'getAnalytics', 'auth' => true, 'permissions' => ['revenue.report']],
        ['method' => 'GET', 'path' => '/api/revenue/reports', 'handler' => 'getReports', 'auth' => true, 'permissions' => ['revenue.report']],

        // Public Tax Services
        ['method' => 'GET', 'path' => '/api/public/tax-status', 'handler' => 'getTaxStatus', 'auth' => false],
        ['method' => 'POST', 'path' => '/api/public/tax-payment', 'handler' => 'makeTaxPayment', 'auth' => false],
        ['method' => 'GET', 'path' => '/api/public/tax-calculator', 'handler' => 'getTaxCalculator', 'auth' => false]
    ];

    /**
     * Tax brackets and rates
     */
    private array $taxBrackets = [];

    /**
     * Tax forms and schedules
     */
    private array $taxForms = [];

    /**
     * Due dates for various tax filings
     */
    private array $dueDates = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->initializeTaxBrackets();
        $this->initializeTaxForms();
        $this->initializeDueDates();
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
            'electronic_filing' => true,
            'auto_assessment' => true,
            'payment_processing' => true,
            'refund_processing' => true,
            'audit_selection' => true,
            'compliance_monitoring' => true,
            'analytics_enabled' => true,
            'public_portal' => true,
            'mobile_access' => true,
            'integration_financial' => true,
            'direct_deposit' => true,
            'penalty_calculation' => true,
            'interest_calculation' => true
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeTaxBrackets();
        $this->initializeTaxForms();
        $this->initializeDueDates();
        $this->setupNotificationTemplates();
    }

    /**
     * Initialize tax brackets
     */
    private function initializeTaxBrackets(): void
    {
        $this->taxBrackets = [
            'individual' => [
                '2024' => [
                    ['min' => 0, 'max' => 11000, 'rate' => 0.10],
                    ['min' => 11001, 'max' => 44725, 'rate' => 0.12],
                    ['min' => 44726, 'max' => 95375, 'rate' => 0.22],
                    ['min' => 95376, 'max' => 182100, 'rate' => 0.24],
                    ['min' => 182101, 'max' => 231250, 'rate' => 0.32],
                    ['min' => 231251, 'max' => 578125, 'rate' => 0.35],
                    ['min' => 578126, 'max' => null, 'rate' => 0.37]
                ]
            ],
            'business' => [
                '2024' => [
                    ['min' => 0, 'max' => 50000, 'rate' => 0.15],
                    ['min' => 50001, 'max' => 75000, 'rate' => 0.25],
                    ['min' => 75001, 'max' => 100000, 'rate' => 0.34],
                    ['min' => 100001, 'max' => 335000, 'rate' => 0.39],
                    ['min' => 335001, 'max' => 10000000, 'rate' => 0.34],
                    ['min' => 10000001, 'max' => 15000000, 'rate' => 0.35],
                    ['min' => 15000001, 'max' => 18333333, 'rate' => 0.38],
                    ['min' => 18333334, 'max' => null, 'rate' => 0.35]
                ]
            ]
        ];
    }

    /**
     * Initialize tax forms
     */
    private function initializeTaxForms(): void
    {
        $this->taxForms = [
            '1040' => [
                'name' => 'U.S. Individual Income Tax Return',
                'entity_type' => 'individual',
                'due_date' => '04-15',
                'extensions' => ['4868']
            ],
            '1120' => [
                'name' => 'U.S. Corporation Income Tax Return',
                'entity_type' => 'business',
                'due_date' => '04-15',
                'extensions' => ['7004']
            ],
            '1065' => [
                'name' => 'U.S. Return of Partnership Income',
                'entity_type' => 'business',
                'due_date' => '03-15',
                'extensions' => ['7004']
            ],
            '1120S' => [
                'name' => 'U.S. Income Tax Return for an S Corporation',
                'entity_type' => 'business',
                'due_date' => '03-15',
                'extensions' => ['7004']
            ]
        ];
    }

    /**
     * Initialize due dates
     */
    private function initializeDueDates(): void
    {
        $this->dueDates = [
            'individual_income' => '04-15',
            'corporate_income' => '04-15',
            'partnership' => '03-15',
            's_corporation' => '03-15',
            'trust' => '04-15',
            'estate' => '04-15',
            'employment' => '01-31',
            'excise' => '03-31'
        ];
    }

    /**
     * Setup notification templates
     */
    private function setupNotificationTemplates(): void
    {
        // Setup email and SMS templates for tax notifications
    }

    /**
     * File tax return
     */
    public function fileReturn(array $returnData, array $metadata = []): array
    {
        try {
            // Validate return data
            $validation = $this->validateReturnData($returnData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate return ID
            $returnId = $this->generateReturnId();

            // Calculate tax liability
            $calculations = $this->calculateTaxLiability($returnData);

            // Prepare return data
            $return = [
                'return_id' => $returnId,
                'taxpayer_id' => $returnData['taxpayer_id'],
                'tax_year' => $returnData['tax_year'],
                'form_type' => $returnData['form_type'],
                'filing_method' => $returnData['filing_method'] ?? 'electronic',
                'status' => 'filed',
                'filing_date' => date('Y-m-d'),
                'due_date' => $this->getDueDate($returnData['form_type'], $returnData['tax_year']),
                'gross_income' => $calculations['gross_income'],
                'adjusted_gross_income' => $calculations['adjusted_gross_income'],
                'taxable_income' => $calculations['taxable_income'],
                'tax_owed' => $calculations['tax_owed'],
                'withholdings' => $returnData['withholdings'] ?? 0,
                'payments' => $returnData['payments'] ?? 0,
                'refund_amount' => $calculations['refund_amount'],
                'total_owed' => $calculations['total_owed'],
                'balance_due' => $calculations['balance_due'],
                'metadata' => json_encode($metadata)
            ];

            // Save to database
            $this->saveReturn($return);

            // Process payment if included
            if ($return['balance_due'] > 0 && isset($returnData['payment_info'])) {
                $this->processPayment($returnId, $returnData['payment_info']);
            }

            // Send confirmation
            $this->sendReturnConfirmation($return);

            // Log filing
            $this->logReturnEvent($returnId, 'filed', 'Tax return filed successfully');

            return [
                'success' => true,
                'return_id' => $returnId,
                'tax_owed' => $return['tax_owed'],
                'refund_amount' => $return['refund_amount'],
                'balance_due' => $return['balance_due'],
                'confirmation_number' => $returnId,
                'message' => 'Tax return filed successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error filing tax return: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to file tax return'
            ];
        }
    }

    /**
     * Create tax assessment
     */
    public function createAssessment(array $assessmentData, int $assessorId): array
    {
        try {
            // Validate assessment data
            $validation = $this->validateAssessmentData($assessmentData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate assessment ID
            $assessmentId = $this->generateAssessmentId();

            // Calculate final amount with adjustments
            $finalAmount = $assessmentData['original_amount'] + ($assessmentData['adjustments'] ?? 0);

            // Prepare assessment data
            $assessment = [
                'assessment_id' => $assessmentId,
                'taxpayer_id' => $assessmentData['taxpayer_id'],
                'tax_year' => $assessmentData['tax_year'],
                'assessment_type' => $assessmentData['assessment_type'],
                'assessment_date' => date('Y-m-d'),
                'due_date' => $assessmentData['due_date'] ?? $this->calculateDueDate($assessmentData['tax_year']),
                'original_amount' => $assessmentData['original_amount'],
                'adjustments' => $assessmentData['adjustments'] ?? 0,
                'final_amount' => $finalAmount,
                'status' => 'issued',
                'description' => $assessmentData['description'],
                'basis' => $assessmentData['basis'],
                'appeal_deadline' => $this->calculateAppealDeadline(),
                'metadata' => json_encode($assessmentData['metadata'] ?? [])
            ];

            // Save to database
            $this->saveAssessment($assessment);

            // Send assessment notice
            $this->sendAssessmentNotice($assessment);

            // Log assessment
            $this->logAssessmentEvent($assessmentId, 'created', 'Tax assessment issued');

            return [
                'success' => true,
                'assessment_id' => $assessmentId,
                'final_amount' => $finalAmount,
                'due_date' => $assessment['due_date'],
                'appeal_deadline' => $assessment['appeal_deadline'],
                'message' => 'Tax assessment created successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error creating tax assessment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create tax assessment'
            ];
        }
    }

    /**
     * Make tax payment
     */
    public function makePayment(array $paymentData, int $payerId): array
    {
        try {
            // Validate payment data
            $validation = $this->validatePaymentData($paymentData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate payment ID
            $paymentId = $this->generatePaymentId();

            // Process payment
            $paymentResult = $this->processPaymentTransaction($paymentData);

            // Prepare payment data
            $payment = [
                'payment_id' => $paymentId,
                'taxpayer_id' => $paymentData['taxpayer_id'],
                'assessment_id' => $paymentData['assessment_id'] ?? null,
                'return_id' => $paymentData['return_id'] ?? null,
                'payment_date' => date('Y-m-d H:i:s'),
                'amount' => $paymentData['amount'],
                'payment_method' => $paymentData['payment_method'],
                'reference_number' => $paymentResult['reference_number'] ?? null,
                'transaction_id' => $paymentResult['transaction_id'] ?? null,
                'status' => $paymentResult['status'],
                'applied_to' => json_encode($this->calculatePaymentApplication($paymentData)),
                'processed_by' => $payerId,
                'metadata' => json_encode($paymentData['metadata'] ?? [])
            ];

            // Save to database
            $this->savePayment($payment);

            // Update assessment or return balance
            $this->updateBalances($payment);

            // Send receipt
            $this->sendPaymentReceipt($payment);

            // Log payment
            $this->logPaymentEvent($paymentId, 'processed', 'Tax payment processed');

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'amount' => $payment['amount'],
                'status' => $payment['status'],
                'reference_number' => $payment['reference_number'],
                'message' => 'Payment processed successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error processing tax payment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to process payment'
            ];
        }
    }

    /**
     * Initiate tax audit
     */
    public function initiateAudit(array $auditData, int $initiatorId): array
    {
        try {
            // Validate audit data
            $validation = $this->validateAuditData($auditData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate audit ID
            $auditId = $this->generateAuditId();

            // Prepare audit data
            $audit = [
                'audit_id' => $auditId,
                'taxpayer_id' => $auditData['taxpayer_id'],
                'tax_year' => $auditData['tax_year'],
                'audit_type' => $auditData['audit_type'] ?? 'correspondence',
                'status' => 'initiated',
                'initiated_date' => date('Y-m-d'),
                'due_date' => $auditData['due_date'] ?? $this->calculateAuditDueDate(),
                'auditor_id' => $auditData['auditor_id'] ?? $initiatorId,
                'scope' => $auditData['scope'],
                'metadata' => json_encode($auditData['metadata'] ?? [])
            ];

            // Save to database
            $this->saveAudit($audit);

            // Send audit notification
            $this->sendAuditNotification($audit);

            // Log audit initiation
            $this->logAuditEvent($auditId, 'initiated', 'Tax audit initiated');

            return [
                'success' => true,
                'audit_id' => $auditId,
                'audit_type' => $audit['audit_type'],
                'due_date' => $audit['due_date'],
                'message' => 'Tax audit initiated successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error initiating tax audit: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to initiate tax audit'
            ];
        }
    }

    /**
     * Process tax refund
     */
    public function processRefund(array $refundData, int $processorId): array
    {
        try {
            // Validate refund data
            $validation = $this->validateRefundData($refundData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate refund ID
            $refundId = $this->generateRefundId();

            // Calculate offsets and net refund
            $offsets = $this->calculateOffsets($refundData['taxpayer_id']);
            $netRefund = $refundData['amount'] - $offsets['total_offset'];

            // Prepare refund data
            $refund = [
                'refund_id' => $refundId,
                'taxpayer_id' => $refundData['taxpayer_id'],
                'return_id' => $refundData['return_id'],
                'amount' => $refundData['amount'],
                'status' => 'approved',
                'approved_date' => date('Y-m-d'),
                'offset_amount' => $offsets['total_offset'],
                'net_refund' => $netRefund,
                'direct_deposit' => $refundData['direct_deposit'] ?? false,
                'bank_account' => $refundData['bank_account'] ?? null,
                'routing_number' => $refundData['routing_number'] ?? null,
                'processed_by' => $processorId,
                'metadata' => json_encode($refundData['metadata'] ?? [])
            ];

            // Save to database
            $this->saveRefund($refund);

            // Issue refund
            if ($netRefund > 0) {
                $this->issueRefund($refund);
            }

            // Send refund notification
            $this->sendRefundNotification($refund);

            // Log refund
            $this->logRefundEvent($refundId, 'processed', 'Tax refund processed');

            return [
                'success' => true,
                'refund_id' => $refundId,
                'net_refund' => $netRefund,
                'offset_amount' => $offsets['total_offset'],
                'message' => 'Tax refund processed successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error processing tax refund: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to process tax refund'
            ];
        }
    }

    /**
     * Get tax returns
     */
    public function getReturns(array $filters = [], array $pagination = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM tax_returns WHERE 1=1";
            $params = [];

            // Apply filters
            if (isset($filters['taxpayer_id'])) {
                $sql .= " AND taxpayer_id = ?";
                $params[] = $filters['taxpayer_id'];
            }

            if (isset($filters['tax_year'])) {
                $sql .= " AND tax_year = ?";
                $params[] = $filters['tax_year'];
            }

            if (isset($filters['form_type'])) {
                $sql .= " AND form_type = ?";
                $params[] = $filters['form_type'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND filing_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND filing_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY filing_date DESC";

            // Add pagination
            $limit = $pagination['limit'] ?? 50;
            $offset = $pagination['offset'] ?? 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $returns = $db->fetchAll($sql, $params);

            // Decode JSON fields and enrich data
            foreach ($returns as &$return) {
                $return['attachments'] = json_decode($return['attachments'], true);
                $return['metadata'] = json_decode($return['metadata'], true);

                // Add calculated fields
                $return['days_to_process'] = $this->calculateProcessingTime($return);
                $return['status_description'] = $this->getStatusDescription($return['status']);
            }

            return [
                'success' => true,
                'data' => $returns,
                'count' => count($returns)
            ];

        } catch (\Exception $e) {
            error_log("Error getting tax returns: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve tax returns'
            ];
        }
    }

    /**
     * Get tax assessments
     */
    public function getAssessments(array $filters = [], array $pagination = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM tax_assessments WHERE 1=1";
            $params = [];

            // Apply filters
            if (isset($filters['taxpayer_id'])) {
                $sql .= " AND taxpayer_id = ?";
                $params[] = $filters['taxpayer_id'];
            }

            if (isset($filters['tax_year'])) {
                $sql .= " AND tax_year = ?";
                $params[] = $filters['tax_year'];
            }

            if (isset($filters['assessment_type'])) {
                $sql .= " AND assessment_type = ?";
                $params[] = $filters['assessment_type'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND assessment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND assessment_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY assessment_date DESC";

            // Add pagination
            $limit = $pagination['limit'] ?? 50;
            $offset = $pagination['offset'] ?? 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $assessments = $db->fetchAll($sql, $params);

            // Decode JSON fields and enrich data
            foreach ($assessments as &$assessment) {
                $assessment['installments'] = json_decode($assessment['installments'], true);
                $assessment['collection_actions'] = json_decode($assessment['collection_actions'], true);
                $assessment['metadata'] = json_decode($assessment['metadata'], true);

                // Add calculated fields
                $assessment['days_overdue'] = $this->calculateDaysOverdue($assessment);
                $assessment['total_with_penalties'] = $this->calculateTotalWithPenalties($assessment);
            }

            return [
                'success' => true,
                'data' => $assessments,
                'count' => count($assessments)
            ];

        } catch (\Exception $e) {
            error_log("Error getting tax assessments: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve tax assessments'
            ];
        }
    }

    /**
     * Get tax analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT
                        COUNT(DISTINCT taxpayer_id) as total_taxpayers,
                        COUNT(*) as total_returns,
                        SUM(tax_owed) as total_tax_collected,
                        SUM(refund_amount) as total_refunds_issued,
                        AVG(DATEDIFF(filing_date, due_date)) as avg_days_to_file,
                        COUNT(CASE WHEN status = 'audited' THEN 1 END) as audited_returns,
                        COUNT(CASE WHEN status = 'appealed' THEN 1 END) as appealed_assessments,
                        SUM(penalty_amount + interest_amount) as total_penalties_interest
                    FROM tax_returns tr
                    LEFT JOIN tax_assessments ta ON tr.taxpayer_id = ta.taxpayer_id
                    WHERE 1=1";

            $params = [];

            if (isset($filters['tax_year'])) {
                $sql .= " AND tr.tax_year = ?";
                $params[] = $filters['tax_year'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND tr.filing_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND tr.filing_date <= ?";
                $params[] = $filters['date_to'];
            }

            $result = $db->fetch($sql, $params);

            // Calculate additional metrics
            $result['net_revenue'] = $result['total_tax_collected'] - $result['total_refunds_issued'];
            $result['audit_rate'] = $result['total_returns'] > 0
                ? round(($

<?php
/**
 * TPT Government Platform - Building Consents Module
 *
 * Comprehensive building consent application and processing system
 * for local government building departments.
 */

namespace Core\Modules;

use Core\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class BuildingConsentsPlugin extends ServiceModule
{
    /**
     * Service category
     */
    protected string $serviceCategory = 'permitting';

    /**
     * Required permissions
     */
    protected array $requiredPermissions = [
        'building_consents.view',
        'building_consents.create',
        'building_consents.approve',
        'building_consents.inspect'
    ];

    /**
     * Database tables
     */
    protected array $databaseTables = [
        'building_consent_applications' => [
            'id SERIAL PRIMARY KEY',
            'application_number VARCHAR(50) UNIQUE NOT NULL',
            'applicant_id INT NOT NULL',
            'property_address TEXT NOT NULL',
            'property_legal_description TEXT',
            'application_type VARCHAR(100) NOT NULL',
            'work_description TEXT',
            'estimated_cost DECIMAL(12,2)',
            'consent_type VARCHAR(50) NOT NULL',
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
        'building_consent_documents' => [
            'id SERIAL PRIMARY KEY',
            'application_id INT NOT NULL',
            'document_type VARCHAR(100) NOT NULL',
            'file_name VARCHAR(255) NOT NULL',
            'file_path TEXT NOT NULL',
            'file_size INT',
            'mime_type VARCHAR(100)',
            'uploaded_by INT NOT NULL',
            'uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (application_id) REFERENCES building_consent_applications(id) ON DELETE CASCADE'
        ],
        'building_consent_inspections' => [
            'id SERIAL PRIMARY KEY',
            'application_id INT NOT NULL',
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
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (application_id) REFERENCES building_consent_applications(id) ON DELETE CASCADE'
        ],
        'building_consent_fees' => [
            'id SERIAL PRIMARY KEY',
            'application_id INT NOT NULL',
            'fee_type VARCHAR(100) NOT NULL',
            'amount DECIMAL(10,2) NOT NULL',
            'description TEXT',
            'due_date DATE',
            'paid BOOLEAN DEFAULT FALSE',
            'paid_at TIMESTAMP NULL',
            'payment_reference VARCHAR(100)',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'FOREIGN KEY (application_id) REFERENCES building_consent_applications(id) ON DELETE CASCADE'
        ]
    ];

    /**
     * API endpoints
     */
    protected array $apiEndpoints = [
        [
            'path' => '/api/building-consents',
            'method' => 'GET',
            'handler' => [$this, 'getApplications'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/building-consents',
            'method' => 'POST',
            'handler' => [$this, 'createApplication'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/building-consents/{id}',
            'method' => 'GET',
            'handler' => [$this, 'getApplication'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/building-consents/{id}/submit',
            'method' => 'POST',
            'handler' => [$this, 'submitApplication'],
            'middleware' => ['auth']
        ],
        [
            'path' => '/api/building-consents/{id}/approve',
            'method' => 'POST',
            'handler' => [$this, 'approveApplication'],
            'middleware' => ['auth', 'permission:building_consents.approve']
        ],
        [
            'path' => '/api/building-consents/{id}/reject',
            'method' => 'POST',
            'handler' => [$this, 'rejectApplication'],
            'middleware' => ['auth', 'permission:building_consents.approve']
        ]
    ];

    /**
     * Workflow definitions
     */
    protected array $workflows = [
        'building_consent_approval' => [
            'name' => 'Building Consent Approval Process',
            'description' => 'Standard workflow for processing building consent applications',
            'steps' => [
                'draft' => ['next' => 'submitted', 'roles' => ['applicant']],
                'submitted' => ['next' => 'lodged', 'roles' => ['building_officer']],
                'lodged' => ['next' => ['approved', 'rejected', 'more_info'], 'roles' => ['building_officer']],
                'more_info' => ['next' => 'lodged', 'roles' => ['applicant']],
                'approved' => ['next' => 'issued', 'roles' => ['building_officer']],
                'rejected' => ['final' => true, 'roles' => ['building_officer']],
                'issued' => ['final' => true, 'roles' => ['building_officer']]
            ]
        ]
    ];

    /**
     * Form definitions
     */
    protected array $forms = [
        'building_consent_application' => [
            'name' => 'Building Consent Application Form',
            'description' => 'Standard form for building consent applications',
            'fields' => [
                'property_address' => ['type' => 'text', 'required' => true, 'label' => 'Property Address'],
                'application_type' => ['type' => 'select', 'required' => true, 'label' => 'Application Type'],
                'work_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Work Description'],
                'estimated_cost' => ['type' => 'number', 'required' => true, 'label' => 'Estimated Cost'],
                'consent_type' => ['type' => 'select', 'required' => true, 'label' => 'Consent Type']
            ]
        ]
    ];

    /**
     * Report definitions
     */
    protected array $reports = [
        'consent_processing_times' => [
            'name' => 'Consent Processing Times Report',
            'description' => 'Report on average processing times for building consents',
            'type' => 'performance'
        ],
        'consent_approval_rates' => [
            'name' => 'Consent Approval Rates Report',
            'description' => 'Report on consent approval and rejection rates',
            'type' => 'performance'
        ]
    ];

    /**
     * Get plugin name
     */
    public function getPluginName(): string
    {
        return 'building-consents';
    }

    /**
     * Get service icon
     */
    public function getServiceIcon(): string
    {
        return 'fas fa-building';
    }

    /**
     * Get applications
     */
    public function getApplications(): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $applications = $this->database->select(
                'SELECT * FROM building_consent_applications ORDER BY created_at DESC'
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
     * Create new application
     */
    public function createApplication(array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            // Generate application number
            $applicationNumber = $this->generateApplicationNumber();

            $applicationId = $this->database->insert('building_consent_applications', [
                'application_number' => $applicationNumber,
                'applicant_id' => $data['applicant_id'] ?? 1,
                'property_address' => $data['property_address'],
                'application_type' => $data['application_type'],
                'work_description' => $data['work_description'] ?? '',
                'estimated_cost' => $data['estimated_cost'] ?? 0,
                'consent_type' => $data['consent_type'],
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
     * Get single application
     */
    public function getApplication(int $id): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $application = $this->database->selectOne(
                'SELECT * FROM building_consent_applications WHERE id = ?',
                [$id]
            );

            if (!$application) {
                return [
                    'success' => false,
                    'error' => 'Application not found'
                ];
            }

            // Get related documents
            $documents = $this->database->select(
                'SELECT * FROM building_consent_documents WHERE application_id = ?',
                [$id]
            );

            // Get inspections
            $inspections = $this->database->select(
                'SELECT * FROM building_consent_inspections WHERE application_id = ?',
                [$id]
            );

            // Get fees
            $fees = $this->database->select(
                'SELECT * FROM building_consent_fees WHERE application_id = ?',
                [$id]
            );

            return [
                'success' => true,
                'application' => $application,
                'documents' => $documents,
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
     * Submit application
     */
    public function submitApplication(int $id): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'building_consent_applications',
                [
                    'status' => 'submitted',
                    'submitted_at' => date('Y-m-d H:i:s')
                ],
                ['id' => $id]
            );

            // Trigger workflow
            $this->triggerWorkflow($id, 'submitted');

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
    public function approveApplication(int $id, array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'building_consent_applications',
                [
                    'status' => 'approved',
                    'decision' => 'approved',
                    'decision_at' => date('Y-m-d H:i:s'),
                    'decision_by' => $data['approved_by'] ?? 1,
                    'decision_notes' => $data['notes'] ?? ''
                ],
                ['id' => $id]
            );

            // Trigger workflow
            $this->triggerWorkflow($id, 'approved');

            return [
                'success' => true,
                'message' => 'Application approved successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reject application
     */
    public function rejectApplication(int $id, array $data): array
    {
        if (!$this->database) {
            return ['error' => 'Database not available'];
        }

        try {
            $this->database->update(
                'building_consent_applications',
                [
                    'status' => 'rejected',
                    'decision' => 'rejected',
                    'decision_at' => date('Y-m-d H:i:s'),
                    'decision_by' => $data['rejected_by'] ?? 1,
                    'decision_notes' => $data['notes'] ?? ''
                ],
                ['id' => $id]
            );

            // Trigger workflow
            $this->triggerWorkflow($id, 'rejected');

            return [
                'success' => true,
                'message' => 'Application rejected'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate application number
     */
    private function generateApplicationNumber(): string
    {
        $year = date('Y');
        $sequence = rand(10000, 99999);
        return "BC{$year}{$sequence}";
    }

    /**
     * Trigger workflow
     */
    private function triggerWorkflow(int $applicationId, string $action): void
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
            $totalApplications = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM building_consent_applications'
            )['count'] ?? 0;

            $pendingApprovals = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM building_consent_applications WHERE status = "submitted"'
            )['count'] ?? 0;

            $completedToday = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM building_consent_applications WHERE DATE(decision_at) = CURDATE()'
            )['count'] ?? 0;

            return [
                'total_applications' => $totalApplications,
                'pending_approvals' => $pendingApprovals,
                'completed_today' => $completedToday,
                'revenue_this_month' => 0 // Would calculate from fees table
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
        $this->setServiceConfig('processing_time_target', 20); // days
        $this->setServiceConfig('auto_approval_threshold', 50000); // dollar amount
        $this->setServiceConfig('inspection_required', true);
        $this->setServiceConfig('fee_calculation_method', 'fixed');
    }
}

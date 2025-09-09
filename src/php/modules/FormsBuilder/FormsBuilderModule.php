<?php
/**
 * TPT Government Platform - Forms Builder Module
 *
 * Advanced dynamic form creation system with comprehensive field types
 * supporting government service applications and citizen interactions
 */

namespace Modules\FormsBuilder;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\AIService;

class FormsBuilderModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Forms Builder',
        'version' => '2.0.0',
        'description' => 'Advanced dynamic form creation system with comprehensive field types',
        'author' => 'TPT Government Platform',
        'category' => 'foundation_services',
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
        'forms_builder.view' => 'View forms and submissions',
        'forms_builder.create' => 'Create new forms',
        'forms_builder.edit' => 'Edit existing forms',
        'forms_builder.delete' => 'Delete forms',
        'forms_builder.publish' => 'Publish/unpublish forms',
        'forms_builder.submit' => 'Submit form responses',
        'forms_builder.review' => 'Review form submissions',
        'forms_builder.export' => 'Export form data',
        'forms_builder.templates' => 'Manage form templates'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'forms' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'form_id' => 'VARCHAR(50) UNIQUE NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'category' => 'VARCHAR(100)',
            'status' => "ENUM('draft','published','archived') DEFAULT 'draft'",
            'created_by' => 'INT NOT NULL',
            'updated_by' => 'INT',
            'version' => 'INT DEFAULT 1',
            'schema' => 'JSON',
            'settings' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'form_fields' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'form_id' => 'VARCHAR(50) NOT NULL',
            'field_id' => 'VARCHAR(100) NOT NULL',
            'field_type' => 'VARCHAR(50) NOT NULL',
            'label' => 'VARCHAR(255) NOT NULL',
            'placeholder' => 'VARCHAR(255)',
            'help_text' => 'TEXT',
            'required' => 'BOOLEAN DEFAULT FALSE',
            'validation_rules' => 'JSON',
            'field_options' => 'JSON',
            'conditional_logic' => 'JSON',
            'order_index' => 'INT DEFAULT 0',
            'parent_field_id' => 'VARCHAR(100)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'form_submissions' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'submission_id' => 'VARCHAR(50) UNIQUE NOT NULL',
            'form_id' => 'VARCHAR(50) NOT NULL',
            'submitted_by' => 'INT',
            'ip_address' => 'VARCHAR(45)',
            'user_agent' => 'TEXT',
            'status' => "ENUM('draft','submitted','under_review','approved','rejected') DEFAULT 'submitted'",
            'data' => 'JSON',
            'metadata' => 'JSON',
            'submitted_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'reviewed_by' => 'INT',
            'reviewed_at' => 'DATETIME',
            'review_notes' => 'TEXT'
        ],
        'form_templates' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'template_id' => 'VARCHAR(50) UNIQUE NOT NULL',
            'name' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'category' => 'VARCHAR(100)',
            'schema' => 'JSON',
            'thumbnail' => 'VARCHAR(500)',
            'is_public' => 'BOOLEAN DEFAULT TRUE',
            'created_by' => 'INT NOT NULL',
            'usage_count' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'form_analytics' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'form_id' => 'VARCHAR(50) NOT NULL',
            'date' => 'DATE NOT NULL',
            'views' => 'INT DEFAULT 0',
            'starts' => 'INT DEFAULT 0',
            'submissions' => 'INT DEFAULT 0',
            'completion_rate' => 'DECIMAL(5,2) DEFAULT 0.00',
            'avg_completion_time' => 'INT DEFAULT 0', // seconds
            'field_analytics' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        // Form Management
        ['method' => 'GET', 'path' => '/api/forms', 'handler' => 'getForms', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/forms', 'handler' => 'createForm', 'auth' => true, 'permissions' => ['forms_builder.create']],
        ['method' => 'GET', 'path' => '/api/forms/{id}', 'handler' => 'getForm', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/forms/{id}', 'handler' => 'updateForm', 'auth' => true, 'permissions' => ['forms_builder.edit']],
        ['method' => 'DELETE', 'path' => '/api/forms/{id}', 'handler' => 'deleteForm', 'auth' => true, 'permissions' => ['forms_builder.delete']],
        ['method' => 'POST', 'path' => '/api/forms/{id}/publish', 'handler' => 'publishForm', 'auth' => true, 'permissions' => ['forms_builder.publish']],

        // Form Submissions
        ['method' => 'POST', 'path' => '/api/forms/{id}/submit', 'handler' => 'submitForm', 'auth' => false],
        ['method' => 'GET', 'path' => '/api/forms/{id}/submissions', 'handler' => 'getFormSubmissions', 'auth' => true, 'permissions' => ['forms_builder.view']],
        ['method' => 'GET', 'path' => '/api/submissions/{id}', 'handler' => 'getSubmission', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/submissions/{id}/review', 'handler' => 'reviewSubmission', 'auth' => true, 'permissions' => ['forms_builder.review']],

        // Templates
        ['method' => 'GET', 'path' => '/api/form-templates', 'handler' => 'getTemplates', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/form-templates', 'handler' => 'createTemplate', 'auth' => true, 'permissions' => ['forms_builder.templates']],

        // Analytics
        ['method' => 'GET', 'path' => '/api/forms/{id}/analytics', 'handler' => 'getFormAnalytics', 'auth' => true, 'permissions' => ['forms_builder.view']]
    ];

    /**
     * Advanced field types registry
     */
    private array $fieldTypes = [];

    /**
     * Form validation engine
     */
    private FormValidator $validator;

    /**
     * AI-powered form assistant
     */
    private AIFormAssistant $aiAssistant;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->validator = new FormValidator();
        $this->aiAssistant = new AIFormAssistant();
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
            'max_file_size' => 10485760, // 10MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'],
            'auto_save_interval' => 30, // seconds
            'max_submissions_per_hour' => 10,
            'enable_ai_assistance' => true,
            'captcha_enabled' => true,
            'analytics_enabled' => true,
            'email_notifications' => true,
            'webhook_notifications' => true
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeFieldTypes();
        $this->initializeDefaultTemplates();
        $this->setupFileUploadDirectories();
    }

    /**
     * Initialize advanced field types
     */
    private function initializeFieldTypes(): void
    {
        $this->fieldTypes = [
            // Basic Enhanced Fields
            'rich_text' => [
                'name' => 'Rich Text Editor',
                'icon' => 'fa-edit',
                'category' => 'basic',
                'supports' => ['formatting', 'links', 'images', 'validation']
            ],
            'masked_input' => [
                'name' => 'Masked Input',
                'icon' => 'fa-mask',
                'category' => 'basic',
                'supports' => ['masking', 'validation', 'formatting']
            ],
            'autocomplete' => [
                'name' => 'Auto-complete',
                'icon' => 'fa-search',
                'category' => 'basic',
                'supports' => ['api_integration', 'caching', 'validation']
            ],
            'password_strength' => [
                'name' => 'Password Strength',
                'icon' => 'fa-lock',
                'category' => 'basic',
                'supports' => ['strength_indicator', 'requirements', 'validation']
            ],

            // Date & Time Fields
            'date_range' => [
                'name' => 'Date Range Picker',
                'icon' => 'fa-calendar-alt',
                'category' => 'datetime',
                'supports' => ['range_selection', 'validation', 'formatting']
            ],
            'time_zone' => [
                'name' => 'Time Zone Selector',
                'icon' => 'fa-globe',
                'category' => 'datetime',
                'supports' => ['timezone_conversion', 'auto_detection']
            ],
            'duration' => [
                'name' => 'Duration Picker',
                'icon' => 'fa-clock',
                'category' => 'datetime',
                'supports' => ['time_calculation', 'formatting', 'validation']
            ],
            'recurring_date' => [
                'name' => 'Recurring Date',
                'icon' => 'fa-redo',
                'category' => 'datetime',
                'supports' => ['recurrence_patterns', 'calendar_integration']
            ],

            // Location & Geographic Fields
            'address_autocomplete' => [
                'name' => 'Address Autocomplete',
                'icon' => 'fa-map-marker-alt',
                'category' => 'location',
                'supports' => ['google_maps', 'validation', 'geocoding']
            ],
            'gps_coordinates' => [
                'name' => 'GPS Coordinates',
                'icon' => 'fa-crosshairs',
                'category' => 'location',
                'supports' => ['geolocation', 'map_integration', 'validation']
            ],
            'map_selection' => [
                'name' => 'Map Selection',
                'icon' => 'fa-map',
                'category' => 'location',
                'supports' => ['interactive_map', 'area_selection', 'geofencing']
            ],
            'geofence' => [
                'name' => 'Geofence Selector',
                'icon' => 'fa-draw-polygon',
                'category' => 'location',
                'supports' => ['polygon_drawing', 'area_calculation', 'validation']
            ],

            // Data Collection Fields
            'matrix_rating' => [
                'name' => 'Matrix/Rating Grid',
                'icon' => 'fa-table',
                'category' => 'data',
                'supports' => ['multi_row', 'multi_column', 'calculations', 'validation']
            ],
            'dynamic_table' => [
                'name' => 'Dynamic Table',
                'icon' => 'fa-plus-square',
                'category' => 'data',
                'supports' => ['add_remove_rows', 'calculations', 'validation', 'export']
            ],
            'file_upload_preview' => [
                'name' => 'File Upload with Preview',
                'icon' => 'fa-file-upload',
                'category' => 'data',
                'supports' => ['preview', 'validation', 'compression', 'multiple_files']
            ],
            'bulk_import' => [
                'name' => 'Bulk Data Import',
                'icon' => 'fa-upload',
                'category' => 'data',
                'supports' => ['csv_import', 'excel_import', 'validation', 'preview']
            ],

            // Relationship Fields
            'entity_lookup' => [
                'name' => 'Entity Lookup',
                'icon' => 'fa-search-plus',
                'category' => 'relationship',
                'supports' => ['api_search', 'linking', 'validation', 'caching']
            ],
            'dependent_dropdown' => [
                'name' => 'Dependent Dropdown',
                'icon' => 'fa-list',
                'category' => 'relationship',
                'supports' => ['cascading', 'api_integration', 'validation']
            ],
            'multi_select_search' => [
                'name' => 'Multi-select with Search',
                'icon' => 'fa-check-square',
                'category' => 'relationship',
                'supports' => ['search_filter', 'tagging', 'validation', 'bulk_operations']
            ],
            'reference_field' => [
                'name' => 'Reference Field',
                'icon' => 'fa-link',
                'category' => 'relationship',
                'supports' => ['external_data', 'linking', 'validation', 'sync']
            ],

            // Smart Fields
            'ai_autofill' => [
                'name' => 'AI-Powered Autofill',
                'icon' => 'fa-brain',
                'category' => 'smart',
                'supports' => ['ai_suggestions', 'context_awareness', 'validation']
            ],
            'ocr_extraction' => [
                'name' => 'OCR Text Extraction',
                'icon' => 'fa-eye',
                'category' => 'smart',
                'supports' => ['image_processing', 'text_extraction', 'validation']
            ],
            'voice_input' => [
                'name' => 'Voice-to-Text',
                'icon' => 'fa-microphone',
                'category' => 'smart',
                'supports' => ['speech_recognition', 'real_time', 'validation']
            ],
            'barcode_scanner' => [
                'name' => 'Barcode/QR Scanner',
                'icon' => 'fa-qrcode',
                'category' => 'smart',
                'supports' => ['camera_access', 'real_time', 'validation']
            ],

            // Security & Validation Fields
            'captcha' => [
                'name' => 'CAPTCHA Integration',
                'icon' => 'fa-shield-alt',
                'category' => 'security',
                'supports' => ['multiple_providers', 'accessibility', 'validation']
            ],
            'document_verification' => [
                'name' => 'Document Verification',
                'icon' => 'fa-id-card',
                'category' => 'security',
                'supports' => ['ocr', 'validation', 'biometric', 'blockchain']
            ],
            'signature_capture' => [
                'name' => 'Signature Capture',
                'icon' => 'fa-signature',
                'category' => 'security',
                'supports' => ['digital_signature', 'legal_compliance', 'validation']
            ],
            'biometric_auth' => [
                'name' => 'Biometric Authentication',
                'icon' => 'fa-fingerprint',
                'category' => 'security',
                'supports' => ['fingerprint', 'facial_recognition', 'validation']
            ],

            // Calculation Fields
            'formula_field' => [
                'name' => 'Formula Field',
                'icon' => 'fa-calculator',
                'category' => 'calculation',
                'supports' => ['mathematical', 'conditional', 'real_time', 'validation']
            ],
            'conditional_calculation' => [
                'name' => 'Conditional Calculation',
                'icon' => 'fa-code-branch',
                'category' => 'calculation',
                'supports' => ['if_then_else', 'complex_logic', 'real_time']
            ],
            'currency_converter' => [
                'name' => 'Currency Converter',
                'icon' => 'fa-dollar-sign',
                'category' => 'calculation',
                'supports' => ['real_time_rates', 'multiple_currencies', 'validation']
            ],
            'unit_converter' => [
                'name' => 'Unit Converter',
                'icon' => 'fa-balance-scale',
                'category' => 'calculation',
                'supports' => ['length', 'weight', 'temperature', 'real_time']
            ],

            // Presentation Fields
            'progress_indicator' => [
                'name' => 'Progress Indicator',
                'icon' => 'fa-tasks',
                'category' => 'presentation',
                'supports' => ['multi_step', 'visual_feedback', 'navigation']
            ],
            'collapsible_section' => [
                'name' => 'Collapsible Section',
                'icon' => 'fa-chevron-down',
                'category' => 'presentation',
                'supports' => ['expand_collapse', 'conditional_display', 'organization']
            ],
            'tabbed_interface' => [
                'name' => 'Tabbed Interface',
                'icon' => 'fa-folder',
                'category' => 'presentation',
                'supports' => ['tab_navigation', 'organization', 'conditional_tabs']
            ],
            'wizard_interface' => [
                'name' => 'Wizard Interface',
                'icon' => 'fa-magic',
                'category' => 'presentation',
                'supports' => ['step_by_step', 'progress_tracking', 'navigation']
            ]
        ];
    }

    /**
     * Initialize default form templates
     */
    private function initializeDefaultTemplates(): void
    {
        $defaultTemplates = [
            [
                'template_id' => 'contact_form',
                'name' => 'Contact Form',
                'description' => 'Basic contact information collection',
                'category' => 'general',
                'schema' => $this->getContactFormSchema()
            ],
            [
                'template_id' => 'service_request',
                'name' => 'Service Request',
                'description' => 'General service request form',
                'category' => 'government',
                'schema' => $this->getServiceRequestSchema()
            ],
            [
                'template_id' => 'complaint_form',
                'name' => 'Complaint Form',
                'description' => 'Citizen complaint submission',
                'category' => 'government',
                'schema' => $this->getComplaintFormSchema()
            ],
            [
                'template_id' => 'permit_application',
                'name' => 'Permit Application',
                'description' => 'General permit application form',
                'category' => 'permitting',
                'schema' => $this->getPermitApplicationSchema()
            ]
        ];

        foreach ($defaultTemplates as $template) {
            $this->createFormTemplate($template);
        }
    }

    /**
     * Setup file upload directories
     */
    private function setupFileUploadDirectories(): void
    {
        $directories = [
            UPLOAD_PATH . '/forms',
            UPLOAD_PATH . '/forms/temp',
            UPLOAD_PATH . '/forms/submissions',
            CACHE_PATH . '/forms'
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * Create form
     */
    public function createForm(array $formData): array
    {
        try {
            // Validate form data
            $validation = $this->validateFormData($formData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate form ID
            $formId = $this->generateFormId();

            // Prepare form data
            $form = [
                'form_id' => $formId,
                'title' => $formData['title'],
                'description' => $formData['description'] ?? '',
                'category' => $formData['category'] ?? 'general',
                'status' => 'draft',
                'created_by' => $formData['created_by'],
                'schema' => json_encode($formData['schema'] ?? []),
                'settings' => json_encode($formData['settings'] ?? []),
                'metadata' => json_encode($formData['metadata'] ?? [])
            ];

            // Save to database
            $this->saveForm($form);

            // Create form fields
            if (isset($formData['fields'])) {
                $this->createFormFields($formId, $formData['fields']);
            }

            return [
                'success' => true,
                'form_id' => $formId,
                'message' => 'Form created successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error creating form: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create form'
            ];
        }
    }

    /**
     * Get forms
     */
    public function getForms(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM forms WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['category'])) {
                $sql .= " AND category = ?";
                $params[] = $filters['category'];
            }

            if (isset($filters['created_by'])) {
                $sql .= " AND created_by = ?";
                $params[] = $filters['created_by'];
            }

            $sql .= " ORDER BY created_at DESC";

            $forms = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($forms as &$form) {
                $form['schema'] = json_decode($form['schema'], true);
                $form['settings'] = json_decode($form['settings'], true);
                $form['metadata'] = json_decode($form['metadata'], true);
            }

            return [
                'success' => true,
                'data' => $forms,
                'count' => count($forms)
            ];

        } catch (\Exception $e) {
            error_log("Error getting forms: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve forms'
            ];
        }
    }

    /**
     * Submit form
     */
    public function submitForm(string $formId, array $submissionData, array $metadata = []): array
    {
        try {
            // Get form
            $form = $this->getFormById($formId);
            if (!$form) {
                return [
                    'success' => false,
                    'error' => 'Form not found'
                ];
            }

            if ($form['status'] !== 'published') {
                return [
                    'success' => false,
                    'error' => 'Form is not available for submission'
                ];
            }

            // Validate submission
            $validation = $this->validator->validateSubmission($form, $submissionData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate submission ID
            $submissionId = $this->generateSubmissionId();

            // Prepare submission data
            $submission = [
                'submission_id' => $submissionId,
                'form_id' => $formId,
                'submitted_by' => $metadata['user_id'] ?? null,
                'ip_address' => $metadata['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
                'user_agent' => $metadata['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'],
                'status' => 'submitted',
                'data' => json_encode($submissionData),
                'metadata' => json_encode($metadata)
            ];

            // Save submission
            $this->saveFormSubmission($submission);

            // Process file uploads
            if (isset($submissionData['_files'])) {
                $this->processFileUploads($submissionId, $submissionData['_files']);
            }

            // Send notifications
            $this->sendSubmissionNotifications($form, $submission);

            // Update analytics
            $this->updateFormAnalytics($formId, 'submission');

            return [
                'success' => true,
                'submission_id' => $submissionId,
                'message' => 'Form submitted successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error submitting form: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to submit form'
            ];
        }
    }

    /**
     * Get form submissions
     */
    public function getFormSubmissions(string $formId, array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM form_submissions WHERE form_id = ?";
            $params = [$formId];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND submitted_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND submitted_at <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY submitted_at DESC";

            $submissions = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($submissions as &$submission) {
                $submission['data'] = json_decode($submission['data'], true);
                $submission['metadata'] = json_decode($submission['metadata'], true);
            }

            return [
                'success' => true,
                'data' => $submissions,
                'count' => count($submissions)
            ];

        } catch (\Exception $e) {
            error_log("Error getting form submissions: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve submissions'
            ];
        }
    }

    /**
     * Get available field types
     */
    public function getFieldTypes(): array
    {
        return [
            'success' => true,
            'field_types' => $this->fieldTypes
        ];
    }

    /**
     * Create form from template
     */
    public function createFormFromTemplate(string $templateId, array $formData): array
    {
        try {
            $template = $this->getFormTemplate($templateId);
            if (!$template) {
                return [
                    'success' => false,
                    'error' => 'Template not found'
                ];
            }

            // Merge template with form data
            $formConfig = array_merge($template, $formData);
            $formConfig['schema'] = json_decode($template['schema'], true);

            return $this->createForm($formConfig);

        } catch (\Exception $e) {
            error_log("Error creating form from template: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create form from template'
            ];
        }
    }

    /**
     * Get form analytics
     */
    public function getFormAnalytics(string $formId, array $dateRange = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM form_analytics WHERE form_id = ?";
            $params = [$formId];

            if (isset($dateRange['from'])) {
                $sql .= " AND date >= ?";
                $params[] = $dateRange['from'];
            }

            if (isset($dateRange['to'])) {
                $sql .= " AND date <= ?";
                $params[] = $dateRange['to'];
            }

            $sql .= " ORDER BY date DESC";

            $analytics = $db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $analytics,
                'summary' => $this->calculateAnalyticsSummary($analytics)
            ];

        } catch (\Exception $e) {
            error_log("Error getting form analytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve analytics'
            ];
        }
    }

    /**
     * Export form data
     */
    public function exportFormData(string $formId, string $format = 'csv'): string
    {
        try {
            $submissions = $this->getFormSubmissions($formId)['data'] ?? [];

            switch ($format) {
                case 'csv':
                    return $this->exportToCSV($submissions);
                case 'json':
                    return json_encode($submissions, JSON_PRETTY_PRINT);
                case 'xml':
                    return $this->exportToXML($submissions);
                default:
                    throw new \Exception("Unsupported export format: {$format}");
            }

        } catch (\Exception $e) {
            error_log("Error exporting form data: " . $e->getMessage());
            return '';
        }
    }

    // Helper methods for form schemas
    private function getContactFormSchema(): array
    {
        return [
            'fields' => [
                [
                    'field_id' => 'full_name',
                    'field_type' => 'text',
                    'label' => 'Full Name',
                    'required' => true,
                    'validation_rules' => ['min_length' => 2, 'max_length' => 100]
                ],
                [
                    'field_id' => 'email',
                    'field_type' => 'email',
                    'label' => 'Email Address',
                    'required' => true,
                    'validation_rules' => ['email_format' => true]
                ],
                [
                    'field_id' => 'phone',
                    'field_type' => 'masked_input',
                    'label' => 'Phone Number',
                    'required' => false,
                    'field_options' => ['mask' => '(999) 999-9999']
                ],
                [
                    'field_id' => 'address',
                    'field_type' => 'address_autocomplete',
                    'label' => 'Address',
                    'required' => true
                ],
                [
                    'field_id' => 'message',
                    'field_type' => 'rich_text',
                    'label' => 'Message',
                    'required' => true,
                    'validation_rules' => ['min_length' => 10, 'max_length' => 1000]
                ]
            ]
        ];
    }

    private function getServiceRequestSchema(): array
    {
        return [
            'fields' => [
                [
                    'field_id' => 'service_type',
                    'field_type' => 'select',
                    'label' => 'Service Type',
                    'required' => true,
                    'field_options' => [
                        'options' => [
                            'information' => 'Request Information',
                            'complaint' => 'File Complaint',
                            'service' => 'Request Service',
                            'other' => 'Other'
                        ]
                    ]
                ],
                [
                    'field_id' => 'description',
                    'field_type' => 'rich_text',
                    'label' => 'Description',
                    'required' => true,
                    'help_text' => 'Please provide detailed description of your request'
                ],
                [
                    'field_id' => 'priority',
                    'field_type' => 'select',
                    'label' => 'Priority Level',
                    'required' => true,
                    'field_options' => [
                        'options' => [
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'urgent' => 'Urgent'
                        ]
                    ]
                ],
                [
                    'field_id' => 'attachments',
                    'field_type' => 'file_upload_preview',
                    'label' => 'Attachments',
                    'required' => false,
                    'field_options' => ['multiple' => true, 'max_files' => 5]
                ]
            ]
        ];
    }

    private function getComplaintFormSchema(): array
    {
        return [
            'fields' => [
                [
                    'field_id' => 'complainant_name',
                    'field_type' => 'text',
                    'label' => 'Your Name',
                    'required' => true
                ],
                [
                    'field_id' => 'complainant_contact',
                    'field_type' => 'email',
                    'label' => 'Contact Email',
                    'required' => true
                ],
                [
                    'field_id' => 'complaint_type',
                    'field_type' => 'select',
                    'label' => 'Type of Complaint',
                    'required' => true,
                    'field_options' => [
                        'options' => [
                            'service' => 'Poor Service',
                            'delay' => 'Unreasonable Delay',
                            'error' => 'Administrative Error',
                            'discrimination' => 'Discrimination',
                            'other' => 'Other'
                        ]
                    ]
                ],
                [
                    'field_id' => 'incident_date',
                    'field_type' => 'date',
                    'label' => 'Date of Incident',
                    'required' => true
                ],
                [
                    'field_id' => 'complaint_details',
                    'field_type' => 'rich_text',
                    'label' => 'Complaint Details',
                    'required' => true,
                    'help_text' => 'Please provide as much detail as possible about the incident'
                ],
                [
                    'field_id' => 'desired_resolution',
                    'field_type' => 'textarea',
                    'label' => 'Desired Resolution',
                    'required' => true
                ]
            ]
        ];
    }

    private function getPermitApplicationSchema(): array
    {
        return [
            'fields' => [
                [
                    'field_id' => 'applicant_name',
                    'field_type' => 'text',
                    'label' => 'Applicant Name',
                    'required' => true
                ],
                [
                    'field_id' => 'permit_type',
                    'field_type' => 'select',
                    'label' => 'Permit Type',
                    'required' => true,
                    'field_options' => [
                        'options' => [
                            'building' => 'Building Permit',
                            'business' => 'Business License',
                            'event' => 'Event Permit',
                            'environmental' => 'Environmental Permit',
                            'other' => 'Other'
                        ]
                    ]
                ],
                [
                    'field_id' => 'project_description',
                    'field_type' => 'rich_text',
                    'label' => 'Project Description',
                    'required' => true
                ],
                [
                    'field_id' => 'project_location',
                    'field_type' => 'address_autocomplete',
                    'label' => 'Project Location',
                    'required' => true
                ],
                [
                    'field_id' => 'estimated_cost',
                    'field_type' => 'number',
                    'label' => 'Estimated Cost',
                    'required' => true,
                    'field_options' => ['min' => 0, 'step' => 0.01]
                ],
                [
                    'field_id' => 'timeline',
                    'field_type' => 'date_range',
                    'label' => 'Project Timeline',
                    'required' => true
                ],
                [
                    'field_id' => 'supporting_documents',
                    'field_type' => 'file_upload_preview',
                    'label' => 'Supporting Documents',
                    'required' => true,
                    'field_options' => ['multiple' => true, 'accepted_types' => ['pdf', 'doc', 'dwg']]
                ]
            ]
        ];
    }

    // Additional helper methods would be implemented here...
    // (saveForm, getFormById, validateFormData, etc.)

    /**
     * Generate form ID
     */
    private function generateFormId(): string
    {
        return 'FORM' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate submission ID
     */
    private function generateSubmissionId(): string
    {
        return 'SUB' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Helper methods implementations

    private function validateFormData(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Form title is required';
        }

        if (empty($data['fields'])) {
            $errors[] = 'Form must have at least one field';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateSubmissionData(array $data, array $formFields): array
    {
        $errors = [];

        foreach ($formFields as $field) {
            $fieldId = $field['id'];
            $fieldName = $field['name'] ?? $fieldId;
            $required = $field['required'] ?? false;

            if ($required && (!isset($data[$fieldId]) || empty($data[$fieldId]))) {
                $errors[] = "Field '{$fieldName}' is required";
            }

            // Additional validation based on field type
            if (isset($data[$fieldId])) {
                $validationErrors = $this->validateFieldValue($data[$fieldId], $field);
                $errors = array_merge($errors, $validationErrors);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateFieldValue($value, array $field): array
    {
        $errors = [];
        $fieldType = $field['type'] ?? 'text';
        $fieldName = $field['name'] ?? $field['id'];

        switch ($fieldType) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Field '{$fieldName}' must be a valid email address";
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$fieldName}' must be a number";
                } else {
                    // Check min/max constraints
                    if (isset($field['min']) && $value < $field['min']) {
                        $errors[] = "Field '{$fieldName}' must be at least {$field['min']}";
                    }
                    if (isset($field['max']) && $value > $field['max']) {
                        $errors[] = "Field '{$fieldName}' must be at most {$field['max']}";
                    }
                }
                break;

            case 'date':
                if (!strtotime($value)) {
                    $errors[] = "Field '{$fieldName}' must be a valid date";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "Field '{$fieldName}' must be a valid URL";
                }
                break;
        }

        return $errors;
    }

    private function processFormSubmission(array $submissionData, array $formFields): array
    {
        $processedData = [];

        foreach ($formFields as $field) {
            $fieldId = $field['id'];
            $fieldType = $field['type'] ?? 'text';

            if (isset($submissionData[$fieldId])) {
                $value = $submissionData[$fieldId];

                // Process based on field type
                switch ($fieldType) {
                    case 'checkbox':
                        $processedData[$fieldId] = is_array($value) ? $value : [$value];
                        break;

                    case 'file':
                        $processedData[$fieldId] = $this->processFileUpload($value, $field);
                        break;

                    case 'number':
                        $processedData[$fieldId] = is_numeric($value) ? (float) $value : $value;
                        break;

                    default:
                        $processedData[$fieldId] = $value;
                }
            }
        }

        return $processedData;
    }

    private function processFileUpload($fileData, array $field): array
    {
        // Implementation would handle file upload processing
        return [
            'original_name' => $fileData['name'] ?? '',
            'file_path' => '/uploads/' . uniqid() . '_' . ($fileData['name'] ?? 'file'),
            'file_size' => $fileData['size'] ?? 0,
            'mime_type' => $fileData['type'] ?? ''
        ];
    }

    private function calculateFormCompletionRate(array $formFields, array $submissionData): float
    {
        if (empty($formFields)) {
            return 0.0;
        }

        $totalFields = count($formFields);
        $completedFields = 0;

        foreach ($formFields as $field) {
            $fieldId = $field['id'];
            if (isset($submissionData[$fieldId]) && !empty($submissionData[$fieldId])) {
                $completedFields++;
            }
        }

        return round(($completedFields / $totalFields) * 100, 2);
    }

    // Database helper methods (implementations would be added)
    private function saveForm(array $form): void
    {
        // Implementation would save to database
    }

    private function saveFormSubmission(array $submission): void
    {
        // Implementation would save to database
    }

    private function getFormById(string $formId): ?array
    {
        // Implementation would retrieve from database
        return null;
    }

    private function getSubmissionById(string $submissionId): ?array
    {
        // Implementation would retrieve from database
        return null;
    }

    private function createFormFields(string $formId, array $fields): void
    {
        // Implementation would create form fields
    }

    private function getFormTemplate(string $templateId): ?array
    {
        // Implementation would retrieve template
        return null;
    }

    private function createFormTemplate(array $template): void
    {
        // Implementation would create template
    }

    private function processFileUploads(string $submissionId, array $files): void
    {
        // Implementation would process file uploads
    }

    private function sendSubmissionNotifications(array $form, array $submission): void
    {
        // Implementation would send notifications
    }

    private function updateFormAnalytics(string $formId, string $event): void
    {
        // Implementation would update analytics
    }

    private function calculateAnalyticsSummary(array $analytics): array
    {
        // Implementation would calculate summary
        return [];
    }

    private function exportToCSV(array $data): string
    {
        // Implementation would export to CSV
        return '';
    }

    private function exportToXML(array $data): string
    {
        // Implementation would export to XML
        return '';
    }

    // Notification methods (implementations would be added)
    private function sendFormConfirmation(array $submission): void
    {
        // Implementation would send confirmation
    }

    private function sendFormNotification(array $submission): void
    {
        // Implementation would send notification
    }

    // Logging methods (implementations would be added)
    private function logFormEvent(string $formId, string $event, string $message): void
    {
        // Implementation would log event
    }

    private function logSubmissionEvent(string $submissionId, string $event, string $message): void
    {
        // Implementation would log event
    }
}

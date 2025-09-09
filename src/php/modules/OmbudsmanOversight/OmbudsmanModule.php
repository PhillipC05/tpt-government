<?php
/**
 * TPT Government Platform - Ombudsman & Oversight Module
 *
 * Comprehensive complaint management and government oversight system
 * supporting citizen grievances, investigations, and accountability mechanisms
 */

namespace Modules\OmbudsmanOversight;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class OmbudsmanModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Ombudsman & Oversight',
        'version' => '1.0.0',
        'description' => 'Comprehensive complaint management and government oversight system',
        'author' => 'TPT Government Platform',
        'category' => 'ombudsman_oversight',
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
        'ombudsman.view' => 'View complaint information and case details',
        'ombudsman.submit' => 'Submit complaints and grievances',
        'ombudsman.investigate' => 'Investigate complaints and conduct reviews',
        'ombudsman.resolve' => 'Resolve complaints and implement recommendations',
        'ombudsman.report' => 'Generate oversight reports and analytics',
        'ombudsman.admin' => 'Administer ombudsman operations and case management',
        'oversight.monitor' => 'Monitor government performance and compliance',
        'oversight.audit' => 'Conduct audits and performance reviews'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'complaints' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'complaint_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'complainant_id' => 'INT NOT NULL',
            'complaint_type' => "ENUM('service_delivery','corruption','maladministration','discrimination','privacy','other') NOT NULL",
            'complaint_category' => "ENUM('government_service','law_enforcement','healthcare','education','infrastructure','social_services','other') NOT NULL",
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'priority' => "ENUM('low','medium','high','urgent') DEFAULT 'medium'",
            'status' => "ENUM('submitted','acknowledged','investigating','resolved','closed','escalated') DEFAULT 'submitted'",
            'government_agency' => 'VARCHAR(100) NOT NULL',
            'contact_method' => "ENUM('anonymous','identified','confidential') DEFAULT 'identified'",
            'preferred_language' => 'VARCHAR(50) DEFAULT \'english\'',
            'attachments' => 'JSON',
            'tags' => 'JSON',
            'submitted_date' => 'DATETIME NOT NULL',
            'acknowledgement_date' => 'DATETIME NULL',
            'resolution_date' => 'DATETIME NULL',
            'satisfaction_rating' => 'TINYINT NULL',
            'feedback' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'investigations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'investigation_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'complaint_id' => 'VARCHAR(20) NOT NULL',
            'investigator_id' => 'INT NOT NULL',
            'investigation_type' => "ENUM('preliminary','formal','systemic','follow_up') NOT NULL",
            'scope' => 'TEXT NOT NULL',
            'objectives' => 'JSON',
            'methodology' => 'TEXT',
            'timeline_start' => 'DATE NOT NULL',
            'timeline_end' => 'DATE NULL',
            'status' => "ENUM('planned','active','completed','suspended','cancelled') DEFAULT 'planned'",
            'findings' => 'JSON',
            'recommendations' => 'JSON',
            'evidence' => 'JSON',
            'confidentiality_level' => "ENUM('public','restricted','confidential') DEFAULT 'restricted'",
            'progress_reports' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'case_participants' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'case_id' => 'VARCHAR(20) NOT NULL',
            'participant_type' => "ENUM('complainant','witness','respondent','expert','mediator') NOT NULL",
            'participant_id' => 'INT NOT NULL',
            'role' => 'VARCHAR(100) NOT NULL',
            'contact_details' => 'JSON',
            'participation_status' => "ENUM('active','inactive','withdrawn','completed') DEFAULT 'active'",
            'confidentiality_agreement' => 'BOOLEAN DEFAULT FALSE',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'oversight_reviews' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'review_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'review_type' => "ENUM('performance','compliance','systemic','policy','program') NOT NULL",
            'target_entity' => 'VARCHAR(255) NOT NULL',
            'entity_type' => "ENUM('government_agency','program','policy','service','process') NOT NULL",
            'review_period_start' => 'DATE NOT NULL',
            'review_period_end' => 'DATE NOT NULL',
            'reviewer_id' => 'INT NOT NULL',
            'objectives' => 'JSON',
            'methodology' => 'TEXT',
            'status' => "ENUM('planned','in_progress','completed','published') DEFAULT 'planned'",
            'findings' => 'JSON',
            'recommendations' => 'JSON',
            'implementation_status' => 'JSON',
            'impact_assessment' => 'JSON',
            'public_summary' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'recommendations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'recommendation_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'source_id' => 'VARCHAR(20) NOT NULL',
            'source_type' => "ENUM('complaint','investigation','review','audit') NOT NULL",
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'category' => "ENUM('policy_change','process_improvement','training','resource_allocation','legislation','monitoring') NOT NULL",
            'priority' => "ENUM('low','medium','high','critical') NOT NULL",
            'target_entity' => 'VARCHAR(255) NOT NULL',
            'implementation_timeline' => 'DATE NULL',
            'responsible_party' => 'VARCHAR(255) NOT NULL',
            'status' => "ENUM('proposed','accepted','implemented','rejected','deferred') DEFAULT 'proposed'",
            'implementation_status' => 'JSON',
            'follow_up_required' => 'BOOLEAN DEFAULT TRUE',
            'impact_assessment' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'ombudsman_reports' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'report_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'report_type' => "ENUM('annual','quarterly','special','case_study','trends') NOT NULL",
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'report_period_start' => 'DATE NOT NULL',
            'report_period_end' => 'DATE NOT NULL',
            'author' => 'VARCHAR(255) NOT NULL',
            'status' => "ENUM('draft','review','approved','published','archived') DEFAULT 'draft'",
            'key_findings' => 'JSON',
            'statistics' => 'JSON',
            'recommendations_summary' => 'JSON',
            'methodology' => 'TEXT',
            'executive_summary' => 'TEXT',
            'file_path' => 'VARCHAR(500) NULL',
            'download_count' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'appeals' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'appeal_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'original_case_id' => 'VARCHAR(20) NOT NULL',
            'appellant_id' => 'INT NOT NULL',
            'appeal_type' => "ENUM('decision','process','outcome','service') NOT NULL",
            'grounds_for_appeal' => 'TEXT NOT NULL',
            'requested_outcome' => 'TEXT NOT NULL',
            'appeal_officer_id' => 'INT NULL',
            'status' => "ENUM('submitted','reviewing','upheld','overturned','partially_upheld','dismissed') DEFAULT 'submitted'",
            'decision_date' => 'DATETIME NULL',
            'decision_summary' => 'TEXT',
            'remedial_actions' => 'JSON',
            'appeal_deadline' => 'DATE NOT NULL',
            'documents' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'POST',
            'path' => '/api/ombudsman/complaints',
            'handler' => 'submitComplaint',
            'auth' => true,
            'permissions' => ['ombudsman.submit']
        ],
        [
            'method' => 'GET',
            'path' => '/api/ombudsman/complaints',
            'handler' => 'getComplaints',
            'auth' => true,
            'permissions' => ['ombudsman.view']
        ],
        [
            'method' => 'GET',
            'path' => '/api/ombudsman/complaints/{complaintId}',
            'handler' => 'getComplaintDetails',
            'auth' => true,
            'permissions' => ['ombudsman.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/ombudsman/complaints/{complaintId}/appeal',
            'handler' => 'submitAppeal',
            'auth' => true,
            'permissions' => ['ombudsman.submit']
        ],
        [
            'method' => 'GET',
            'path' => '/api/ombudsman/investigations',
            'handler' => 'getInvestigations',
            'auth' => true,
            'permissions' => ['ombudsman.investigate']
        ],
        [
            'method' => 'GET',
            'path' => '/api/ombudsman/reviews',
            'handler' => 'getOversightReviews',
            'auth' => true,
            'permissions' => ['oversight.monitor']
        ],
        [
            'method' => 'GET',
            'path' => '/api/ombudsman/reports',
            'handler' => 'getOmbudsmanReports',
            'auth' => true,
            'permissions' => ['ombudsman.report']
        ],
        [
            'method' => 'POST',
            'path' => '/api/ombudsman/feedback',
            'handler' => 'submitFeedback',
            'auth' => true,
            'permissions' => ['ombudsman.view']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'complaint_resolution_process' => [
            'name' => 'Complaint Resolution Process',
            'description' => 'Complete workflow for handling citizen complaints from submission to resolution',
            'steps' => [
                'submitted' => ['name' => 'Complaint Submitted', 'next' => 'acknowledged'],
                'acknowledged' => ['name' => 'Complaint Acknowledged', 'next' => 'assessed'],
                'assessed' => ['name' => 'Complaint Assessed', 'next' => ['investigation', 'mediation', 'direct_resolution']],
                'investigation' => ['name' => 'Formal Investigation', 'next' => 'findings'],
                'mediation' => ['name' => 'Mediation Process', 'next' => 'resolution'],
                'direct_resolution' => ['name' => 'Direct Resolution', 'next' => 'resolution'],
                'findings' => ['name' => 'Investigation Findings', 'next' => 'recommendations'],
                'recommendations' => ['name' => 'Recommendations Made', 'next' => 'implementation'],
                'implementation' => ['name' => 'Implementation Monitoring', 'next' => 'resolution'],
                'resolution' => ['name' => 'Case Resolution', 'next' => 'closed'],
                'closed' => ['name' => 'Case Closed', 'next' => null],
                'appeal' => ['name' => 'Appeal Submitted', 'next' => 'appeal_review'],
                'appeal_review' => ['name' => 'Appeal Review', 'next' => ['appeal_upheld', 'appeal_overturned']],
                'appeal_upheld' => ['name' => 'Appeal Upheld', 'next' => 'resolution'],
                'appeal_overturned' => ['name' => 'Appeal Overturned', 'next' => 'closed']
            ]
        ],
        'oversight_review_process' => [
            'name' => 'Oversight Review Process',
            'description' => 'Workflow for conducting oversight reviews and performance audits',
            'steps' => [
                'planned' => ['name' => 'Review Planned', 'next' => 'data_collection'],
                'data_collection' => ['name' => 'Data Collection', 'next' => 'analysis'],
                'analysis' => ['name' => 'Data Analysis', 'next' => 'findings'],
                'findings' => ['name' => 'Findings Documented', 'next' => 'recommendations'],
                'recommendations' => ['name' => 'Recommendations Developed', 'next' => 'review'],
                'review' => ['name' => 'Internal Review', 'next' => 'approval'],
                'approval' => ['name' => 'Final Approval', 'next' => 'publication'],
                'publication' => ['name' => 'Report Published', 'next' => 'follow_up'],
                'follow_up' => ['name' => 'Implementation Follow-up', 'next' => 'completed'],
                'completed' => ['name' => 'Review Completed', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'complaint_submission' => [
            'name' => 'Complaint Submission Form',
            'fields' => [
                'complaint_type' => ['type' => 'select', 'required' => true, 'label' => 'Type of Complaint'],
                'complaint_category' => ['type' => 'select', 'required' => true, 'label' => 'Government Service Category'],
                'title' => ['type' => 'text', 'required' => true, 'label' => 'Complaint Title'],
                'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Detailed Description'],
                'government_agency' => ['type' => 'select', 'required' => true, 'label' => 'Government Agency Involved'],
                'contact_method' => ['type' => 'select', 'required' => true, 'label' => 'Contact Method Preference'],
                'preferred_language' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Language'],
                'priority' => ['type' => 'select', 'required' => false, 'label' => 'Priority Level'],
                'attachments' => ['type' => 'file', 'required' => false, 'label' => 'Supporting Documents']
            ]
        ],
        'appeal_submission' => [
            'name' => 'Appeal Submission Form',
            'fields' => [
                'original_case_id' => ['type' => 'text', 'required' => true, 'label' => 'Original Case ID'],
                'appeal_type' => ['type' => 'select', 'required' => true, 'label' => 'Type of Appeal'],
                'grounds_for_appeal' => ['type' => 'textarea', 'required' => true, 'label' => 'Grounds for Appeal'],
                'requested_outcome' => ['type' => 'textarea', 'required' => true, 'label' => 'Requested Outcome'],
                'additional_evidence' => ['type' => 'file', 'required' => false, 'label' => 'Additional Evidence']
            ]
        ],
        'feedback_form' => [
            'name' => 'Service Feedback Form',
            'fields' => [
                'case_id' => ['type' => 'text', 'required' => true, 'label' => 'Case/Reference ID'],
                'satisfaction_rating' => ['type' => 'rating', 'required' => true, 'label' => 'Overall Satisfaction (1-5)'],
                'resolution_quality' => ['type' => 'rating', 'required' => false, 'label' => 'Resolution Quality'],
                'timeliness' => ['type' => 'rating', 'required' => false, 'label' => 'Timeliness of Response'],
                'communication' => ['type' => 'rating', 'required' => false, 'label' => 'Communication Quality'],
                'feedback_text' => ['type' => 'textarea', 'required' => false, 'label' => 'Additional Feedback'],
                'recommendations' => ['type' => 'textarea', 'required' => false, 'label' => 'Suggestions for Improvement']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'complaint_analysis_report' => [
            'name' => 'Complaint Analysis Report',
            'description' => 'Analysis of complaint patterns, trends, and resolution effectiveness',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'complaint_type' => ['type' => 'select', 'required' => false],
                'agency' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_complaints', 'complaints_by_type', 'complaints_by_agency',
                'resolution_rate', 'average_resolution_time', 'satisfaction_ratings'
            ]
        ],
        'oversight_performance_report' => [
            'name' => 'Oversight Performance Report',
            'description' => 'Assessment of government agency performance and compliance',
            'parameters' => [
                'review_period' => ['type' => 'date_range', 'required' => true],
                'agency' => ['type' => 'select', 'required' => false],
                'review_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'reviews_completed', 'findings_by_category', 'recommendations_made',
                'implementation_rate', 'impact_assessment', 'trends_over_time'
            ]
        ],
        'recommendation_tracking_report' => [
            'name' => 'Recommendation Tracking Report',
            'description' => 'Monitoring implementation of ombudsman recommendations',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => true],
                'status' => ['type' => 'select', 'required' => false],
                'priority' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_recommendations', 'implementation_status', 'by_priority',
                'by_category', 'timeline_compliance', 'impact_measures'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'complaint_acknowledgement' => [
            'name' => 'Complaint Acknowledgement',
            'template' => 'Your complaint (ID: {complaint_id}) has been received and acknowledged. We will begin our review process.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['complaint_acknowledged']
        ],
        'complaint_status_update' => [
            'name' => 'Complaint Status Update',
            'template' => 'Your complaint (ID: {complaint_id}) status has been updated to: {status}. {additional_info}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['complaint_status_changed']
        ],
        'investigation_started' => [
            'name' => 'Investigation Started',
            'template' => 'An investigation has been initiated for your complaint (ID: {complaint_id}). You will be updated on progress.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['investigation_started']
        ],
        'resolution_notification' => [
            'name' => 'Complaint Resolution',
            'template' => 'Your complaint (ID: {complaint_id}) has been resolved. Please review the resolution details.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['complaint_resolved']
        ],
        'appeal_deadline_reminder' => [
            'name' => 'Appeal Deadline Reminder',
            'template' => 'Your appeal deadline for case {case_id} is approaching: {deadline_date}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['appeal_deadline_approaching']
        ],
        'report_publication' => [
            'name' => 'New Ombudsman Report',
            'template' => 'A new ombudsman report "{report_title}" has been published. View it at: {report_url}',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['report_published']
        ]
    ];

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
            'complaint_acknowledgement_days' => 3,
            'investigation_deadline_days' => 30,
            'resolution_target_days' => 90,
            'appeal_deadline_days' => 14,
            'anonymous_complaints_allowed' => true,
            'auto_assign_investigators' => true,
            'escalation_threshold_days' => 60,
            'satisfaction_survey_enabled' => true
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        // Initialize module-specific data
    }

    /**
     * Submit complaint (API handler)
     */
    public function submitComplaint(array $complaintData): array
    {
        // Validate complaint data
        $validation = $this->validateComplaintData($complaintData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate complaint ID
        $complaintId = $this->generateComplaintId();

        // Create complaint record
        $complaint = [
            'complaint_id' => $complaintId,
            'complainant_id' => $complaintData['complainant_id'],
            'complaint_type' => $complaintData['complaint_type'],
            'complaint_category' => $complaintData['complaint_category'],
            'title' => $complaintData['title'],
            'description' => $complaintData['description'],
            'priority' => $complaintData['priority'] ?? 'medium',
            'status' => 'submitted',
            'government_agency' => $complaintData['government_agency'],
            'contact_method' => $complaintData['contact_method'] ?? 'identified',
            'preferred_language' => $complaintData['preferred_language'] ?? 'english',
            'attachments' => json_encode($complaintData['attachments'] ?? []),
            'tags' => json_encode($complaintData['tags'] ?? []),
            'submitted_date' => date('Y-m-d H:i:s')
        ];

        // Save to database
        $this->saveComplaint($complaint);

        // Auto-assign investigator if enabled
        if ($this->config['auto_assign_investigators']) {
            $this->autoAssignInvestigator($complaintId);
        }

        // Send acknowledgement notification
        $this->sendNotification('complaint_acknowledgement', $complaintData['complainant_id'], [
            'complaint_id' => $complaintId
        ]);

        return [
            'success' => true,
            'complaint_id' => $complaintId,
            'status' => 'submitted',
            'estimated_response_time' => $this->config['complaint_acknowledgement_days'] . ' days',
            'message' => 'Complaint submitted successfully'
        ];
    }

    /**
     * Get complaints (API handler)
     */
    public function getComplaints(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM complaints WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['complaint_type'])) {
                $sql .= " AND complaint_type = ?";
                $params[] = $filters['complaint_type'];
            }

            if (isset($filters['complainant_id'])) {
                $sql .= " AND complainant_id = ?";
                $params[] = $filters['complainant_id'];
            }

            if (isset($filters['government_agency'])) {
                $sql .= " AND government_agency = ?";
                $params[] = $filters['government_agency'];
            }

            $sql .= " ORDER BY submitted_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['attachments'] = json_decode($result['attachments'], true);
                $result['tags'] = json_decode($result['tags'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting complaints: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve complaints'
            ];
        }
    }

    /**
     * Get complaint details (API handler)
     */
    public function getComplaintDetails(string $complaintId): array
    {
        $complaint = $this->getComplaint($complaintId);
        if (!$complaint) {
            return [
                'success' => false,
                'error' => 'Complaint not found'
            ];
        }

        // Get related investigations
        $investigations = $this->getComplaintInvestigations($complaintId);

        // Get related recommendations
        $recommendations = $this->getComplaintRecommendations($complaintId);

        // Get case participants
        $participants = $this->getCaseParticipants($complaintId);

        return [
            'success' => true,
            'complaint' => $complaint,
            'investigations' => $investigations,
            'recommendations' => $recommendations,
            'participants' => $participants
        ];
    }

    /**
     * Submit appeal (API handler)
     */
    public function submitAppeal(string $complaintId, array $appealData): array
    {
        // Validate appeal data
        $validation = $this->validateAppealData($appealData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check if complaint exists and can be appealed
        $complaint = $this->getComplaint($complaintId);
        if (!$complaint) {
            return [
                'success' => false,
                'error' => 'Complaint not found'
            ];
        }

        if (!in_array($complaint['status'], ['resolved', 'closed'])) {
            return [
                'success' => false,
                'error' => 'Complaint must be resolved or closed before appeal can be submitted'
            ];
        }

        // Check appeal deadline
        $appealDeadline = $this->calculateAppealDeadline($complaint['resolution_date']);
        if (strtotime(date('Y-m-d')) > strtotime($appealDeadline)) {
            return [
                'success' => false,
                'error' => 'Appeal deadline has passed'
            ];
        }

        // Generate appeal ID
        $appealId = $this->generateAppealId();

        // Create appeal record
        $appeal = [
            'appeal_id' => $appealId,
            'original_case_id' => $complaintId,
            'appellant_id' => $appealData['appellant_id'],
            'appeal_type' => $appealData['appeal_type'],
            'grounds_for_appeal' => $appealData['grounds_for_appeal'],
            'requested_outcome' => $appealData['requested_outcome'],
            'status' => 'submitted',
            'appeal_deadline' => $appealDeadline,
            'documents' => json_encode($appealData['documents'] ?? [])
        ];

        // Save to database
        $this->saveAppeal($appeal);

        // Update complaint status
        $this->updateComplaintStatus($complaintId, 'appeal');

        return [
            'success' => true,
            'appeal_id' => $appealId,
            'status' => 'submitted',
            'appeal_deadline' => $appealDeadline,
            'message' => 'Appeal submitted successfully'
        ];
    }

    /**
     * Get investigations (API handler)
     */
    public function getInvestigations(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM investigations WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['investigator_id'])) {
                $sql .= " AND investigator_id = ?";
                $params[] = $filters['investigator_id'];
            }

            if (isset($filters['investigation_type'])) {
                $sql .= " AND investigation_type = ?";
                $params[] = $filters['investigation_type'];
            }

            $sql .= " ORDER BY timeline_start DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['objectives'] = json_decode($result['objectives'], true);
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
                $result['evidence'] = json_decode($result['evidence'], true);
                $result['progress_reports'] = json_decode($result['progress_reports'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting investigations: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve investigations'
            ];
        }
    }

    /**
     * Get oversight reviews (API handler)
     */
    public function getOversightReviews(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM oversight_reviews WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['review_type'])) {
                $sql .= " AND review_type = ?";
                $params[] = $filters['review_type'];
            }

            if (isset($filters['reviewer_id'])) {
                $sql .= " AND reviewer_id = ?";
                $params[] = $filters['reviewer_id'];
            }

            $sql .= " ORDER BY review_period_start DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['objectives'] = json_decode($result['objectives'], true);
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
                $result['implementation_status'] = json_decode($result['implementation_status'], true);
                $result['impact_assessment'] = json_decode($result['impact_assessment'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting oversight reviews: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve oversight reviews'
            ];
        }
    }

    /**
     * Get ombudsman reports (API handler)
     */
    public function getOmbudsmanReports(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM ombudsman_reports WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['report_type'])) {
                $sql .= " AND report_type = ?";
                $params[] = $filters['report_type'];
            }

            $sql .= " ORDER BY report_period_start DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['key_findings'] = json_decode($result['key_findings'], true);
                $result['statistics'] = json_decode($result['statistics'], true);
                $result['recommendations_summary'] = json_decode($result['recommendations_summary'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting ombudsman reports: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve ombudsman reports'
            ];
        }
    }

    /**
     * Submit feedback (API handler)
     */
    public function submitFeedback(array $feedbackData): array
    {
        // Validate feedback data
        $validation = $this->validateFeedbackData($feedbackData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Update complaint with feedback
        $this->updateComplaintFeedback($feedbackData['case_id'], $feedbackData);

        return [
            'success' => true,
            'message' => 'Feedback submitted successfully. Thank you for helping us improve our services.'
        ];
    }

    /**
     * Generate complaint ID
     */
    private function generateComplaintId(): string
    {
        return 'COMP' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate appeal ID
     */
    private function generateAppealId(): string
    {
        return 'APPEAL' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save complaint
     */
    private function saveComplaint(array $complaint): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO complaints (
                complaint_id, complainant_id, complaint_type, complaint_category,
                title, description, priority, status, government_agency,
                contact_method, preferred_language, attachments, tags, submitted_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $complaint['complaint_id'],
                $complaint['complainant_id'],
                $complaint['complaint_type'],
                $complaint['complaint_category'],
                $complaint['title'],
                $complaint['description'],
                $complaint['priority'],
                $complaint['status'],
                $complaint['government_agency'],
                $complaint['contact_method'],
                $complaint['preferred_language'],
                $complaint['attachments'],
                $complaint['tags'],
                $complaint['submitted_date']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving complaint: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get complaint
     */
    private function getComplaint(string $complaintId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM complaints WHERE complaint_id = ?";
            $result = $db->fetch($sql, [$complaintId]);

            if ($result) {
                $result['attachments'] = json_decode($result['attachments'], true);
                $result['tags'] = json_decode($result['tags'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting complaint: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get complaint investigations
     */
    private function getComplaintInvestigations(string $complaintId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM investigations WHERE complaint_id = ? ORDER BY timeline_start DESC";
            $results = $db->fetchAll($sql, [$complaintId]);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['objectives'] = json_decode($result['objectives'], true);
                $result['findings'] = json_decode($result['findings'], true);
                $result['recommendations'] = json_decode($result['recommendations'], true);
                $result['evidence'] = json_decode($result['evidence'], true);
                $result['progress_reports'] = json_decode($result['progress_reports'], true);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting complaint investigations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get complaint recommendations
     */
    private function getComplaintRecommendations(string $complaintId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT r.* FROM recommendations r
                    JOIN investigations i ON r.source_id = i.investigation_id
                    WHERE i.complaint_id = ? AND r.source_type = 'investigation'
                    ORDER BY r.created_at DESC";
            $results = $db->fetchAll($sql, [$complaintId]);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['implementation_status'] = json_decode($result['implementation_status'], true);
                $result['impact_assessment'] = json_decode($result['impact_assessment'], true);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting complaint recommendations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get case participants
     */
    private function getCaseParticipants(string $caseId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM case_participants WHERE case_id = ? ORDER BY created_at DESC";
            $results = $db->fetchAll($sql, [$caseId]);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['contact_details'] = json_decode($result['contact_details'], true);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting case participants: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Auto-assign investigator
     */
    private function autoAssignInvestigator(string $complaintId): bool
    {
        try {
            // This would implement logic to automatically assign investigators
            // based on workload, expertise, and complaint type
            // For now, return true
            return true;
        } catch (\Exception $e) {
            error_log("Error auto-assigning investigator: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate appeal deadline
     */
    private function calculateAppealDeadline(string $resolutionDate): string
    {
        $resolutionTimestamp = strtotime($resolutionDate);
        $appealDeadline = $resolutionTimestamp + ($this->config['appeal_deadline_days'] * 24 * 60 * 60);
        return date('Y-m-d', $appealDeadline);
    }

    /**
     * Update complaint status
     */
    private function updateComplaintStatus(string $complaintId, string $status): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE complaints SET status = ? WHERE complaint_id = ?";
            return $db->execute($sql, [$status, $complaintId]);
        } catch (\Exception $e) {
            error_log("Error updating complaint status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save appeal
     */
    private function saveAppeal(array $appeal): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO appeals (
                appeal_id, original_case_id, appellant_id, appeal_type,
                grounds_for_appeal, requested_outcome, status, appeal_deadline, documents
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $appeal['appeal_id'],
                $appeal['original_case_id'],
                $appeal['appellant_id'],
                $appeal['appeal_type'],
                $appeal['grounds_for_appeal'],
                $appeal['requested_outcome'],
                $appeal['appeal_status'],
                $appeal['appeal_deadline'],
                $appeal['documents']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving appeal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update complaint feedback
     */
    private function updateComplaintFeedback(string $caseId, array $feedback): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE complaints SET
                    satisfaction_rating = ?,
                    feedback = ?
                    WHERE complaint_id = ?";

            $params = [
                $feedback['satisfaction_rating'],
                json_encode($feedback),
                $caseId
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error updating complaint feedback: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, ?int $userId, array $data = []): bool
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
     * Validate complaint data
     */
    private function validateComplaintData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'complainant_id', 'complaint_type', 'complaint_category',
            'title', 'description', 'government_agency'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate complaint type
        $validTypes = ['service_delivery', 'corruption', 'maladministration', 'discrimination', 'privacy', 'other'];
        if (isset($data['complaint_type']) && !in_array($data['complaint_type'], $validTypes)) {
            $errors[] = "Invalid complaint type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate appeal data
     */
    private function validateAppealData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'appellant_id', 'appeal_type', 'grounds_for_appeal', 'requested_outcome'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate appeal type
        $validTypes = ['decision', 'process', 'outcome', 'service'];
        if (isset($data['appeal_type']) && !in_array($data['appeal_type'], $validTypes)) {
            $errors[] = "Invalid appeal type";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate feedback data
     */
    private function validateFeedbackData(array $data): array
    {
        $errors = [];

        $requiredFields = ['case_id', 'satisfaction_rating'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate satisfaction rating
        if (isset($data['satisfaction_rating'])) {
            $rating = (int)$data['satisfaction_rating'];
            if ($rating < 1 || $rating > 5) {
                $errors[] = "Satisfaction rating must be between 1 and 5";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

<?php
/**
 * TPT Government Platform - Ticketing System Module
 *
 * Unified customer service platform for handling citizen inquiries,
 * complaints, and service requests across all government departments
 */

namespace Modules\Ticketing;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\AIService;

class TicketingModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Ticketing System',
        'version' => '2.0.0',
        'description' => 'Unified customer service platform for government departments',
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
        'ticketing.view' => 'View tickets and customer interactions',
        'ticketing.create' => 'Create new tickets',
        'ticketing.update' => 'Update ticket information',
        'ticketing.assign' => 'Assign tickets to agents/departments',
        'ticketing.close' => 'Close and resolve tickets',
        'ticketing.delete' => 'Delete tickets',
        'ticketing.escalate' => 'Escalate tickets to higher priority',
        'ticketing.merge' => 'Merge duplicate tickets',
        'ticketing.export' => 'Export ticket data',
        'ticketing.admin' => 'Full administrative access to ticketing system',
        'ticketing.reports' => 'Access to ticketing reports and analytics'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'tickets' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'title' => 'VARCHAR(255) NOT NULL',
            'description' => 'TEXT',
            'status' => "ENUM('open','pending','in_progress','waiting_for_customer','resolved','closed','cancelled') DEFAULT 'open'",
            'priority' => "ENUM('low','medium','high','urgent','critical') DEFAULT 'medium'",
            'type' => "ENUM('question','incident','problem','task','feature_request','complaint','feedback') DEFAULT 'question'",
            'category' => 'VARCHAR(100)',
            'subcategory' => 'VARCHAR(100)',
            'department' => 'VARCHAR(100)',
            'channel' => "ENUM('web','email','phone','chat','mobile_app','social_media','walk_in') DEFAULT 'web'",
            'source' => 'VARCHAR(100)', // Specific source within channel
            'customer_id' => 'INT',
            'customer_name' => 'VARCHAR(255)',
            'customer_email' => 'VARCHAR(255)',
            'customer_phone' => 'VARCHAR(50)',
            'assigned_to' => 'INT',
            'assigned_by' => 'INT',
            'assigned_at' => 'DATETIME',
            'created_by' => 'INT',
            'updated_by' => 'INT',
            'resolved_at' => 'DATETIME',
            'closed_at' => 'DATETIME',
            'due_date' => 'DATETIME',
            'sla_breach' => 'BOOLEAN DEFAULT FALSE',
            'sla_breach_time' => 'DATETIME',
            'tags' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'ticket_comments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_id' => 'VARCHAR(20) NOT NULL',
            'comment' => 'TEXT NOT NULL',
            'is_internal' => 'BOOLEAN DEFAULT FALSE',
            'is_resolution' => 'BOOLEAN DEFAULT FALSE',
            'author_id' => 'INT NOT NULL',
            'author_name' => 'VARCHAR(255)',
            'author_type' => "ENUM('agent','customer','system','api') DEFAULT 'agent'",
            'attachments' => 'JSON',
            'metadata' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ticket_attachments' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_id' => 'VARCHAR(20) NOT NULL',
            'comment_id' => 'INT',
            'filename' => 'VARCHAR(255) NOT NULL',
            'original_filename' => 'VARCHAR(255) NOT NULL',
            'file_path' => 'VARCHAR(500) NOT NULL',
            'file_size' => 'INT NOT NULL',
            'mime_type' => 'VARCHAR(100)',
            'uploaded_by' => 'INT NOT NULL',
            'uploaded_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ticket_categories' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'parent_id' => 'INT',
            'department' => 'VARCHAR(100)',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'sort_order' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ticket_sla_policies' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'priority' => "ENUM('low','medium','high','urgent','critical') NOT NULL",
            'response_time_hours' => 'INT NOT NULL', // Hours to first response
            'resolution_time_hours' => 'INT NOT NULL', // Hours to resolution
            'escalation_time_hours' => 'INT', // Hours before escalation
            'business_hours_only' => 'BOOLEAN DEFAULT TRUE',
            'applicable_departments' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ticket_escalations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_id' => 'VARCHAR(20) NOT NULL',
            'escalation_level' => 'INT DEFAULT 1',
            'escalated_by' => 'INT',
            'escalated_to' => 'INT',
            'escalation_reason' => 'TEXT',
            'escalation_time' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'response_time' => 'DATETIME',
            'resolution_time' => 'DATETIME'
        ],
        'ticket_satisfaction' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'ticket_id' => 'VARCHAR(20) NOT NULL',
            'rating' => 'TINYINT', // 1-5 scale
            'feedback' => 'TEXT',
            'submitted_by' => 'INT',
            'submitted_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'follow_up_sent' => 'BOOLEAN DEFAULT FALSE'
        ],
        'ticket_templates' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'name' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT',
            'category' => 'VARCHAR(100)',
            'priority' => "ENUM('low','medium','high','urgent','critical') DEFAULT 'medium'",
            'type' => "ENUM('question','incident','problem','task','feature_request','complaint','feedback') DEFAULT 'question'",
            'subject_template' => 'VARCHAR(255)',
            'message_template' => 'TEXT',
            'auto_assign' => 'BOOLEAN DEFAULT FALSE',
            'assigned_to' => 'INT',
            'tags' => 'JSON',
            'is_active' => 'BOOLEAN DEFAULT TRUE',
            'created_by' => 'INT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'ticket_analytics' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'date' => 'DATE NOT NULL',
            'department' => 'VARCHAR(100)',
            'category' => 'VARCHAR(100)',
            'channel' => 'VARCHAR(50)',
            'tickets_created' => 'INT DEFAULT 0',
            'tickets_resolved' => 'INT DEFAULT 0',
            'avg_response_time' => 'INT DEFAULT 0', // minutes
            'avg_resolution_time' => 'INT DEFAULT 0', // minutes
            'sla_breaches' => 'INT DEFAULT 0',
            'customer_satisfaction' => 'DECIMAL(3,2) DEFAULT 0.00', // 0.00 to 5.00
            'first_contact_resolution' => 'INT DEFAULT 0', // count
            'escalations' => 'INT DEFAULT 0',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'knowledge_base' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'title' => 'VARCHAR(255) NOT NULL',
            'content' => 'TEXT',
            'category' => 'VARCHAR(100)',
            'tags' => 'JSON',
            'is_published' => 'BOOLEAN DEFAULT TRUE',
            'view_count' => 'INT DEFAULT 0',
            'helpful_count' => 'INT DEFAULT 0',
            'not_helpful_count' => 'INT DEFAULT 0',
            'created_by' => 'INT',
            'updated_by' => 'INT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        // Ticket Management
        ['method' => 'GET', 'path' => '/api/tickets', 'handler' => 'getTickets', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/tickets', 'handler' => 'createTicket', 'auth' => false],
        ['method' => 'GET', 'path' => '/api/tickets/{id}', 'handler' => 'getTicket', 'auth' => true],
        ['method' => 'PUT', 'path' => '/api/tickets/{id}', 'handler' => 'updateTicket', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/tickets/{id}/close', 'handler' => 'closeTicket', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/tickets/{id}/escalate', 'handler' => 'escalateTicket', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/tickets/{id}/assign', 'handler' => 'assignTicket', 'auth' => true],

        // Comments and Communication
        ['method' => 'GET', 'path' => '/api/tickets/{id}/comments', 'handler' => 'getTicketComments', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/tickets/{id}/comments', 'handler' => 'addTicketComment', 'auth' => true],

        // Categories and Templates
        ['method' => 'GET', 'path' => '/api/ticket-categories', 'handler' => 'getCategories', 'auth' => true],
        ['method' => 'GET', 'path' => '/api/ticket-templates', 'handler' => 'getTemplates', 'auth' => true],

        // Customer Portal
        ['method' => 'GET', 'path' => '/api/customer/tickets', 'handler' => 'getCustomerTickets', 'auth' => true],
        ['method' => 'POST', 'path' => '/api/customer/tickets/{id}/feedback', 'handler' => 'submitFeedback', 'auth' => true],

        // Analytics and Reporting
        ['method' => 'GET', 'path' => '/api/tickets/analytics', 'handler' => 'getAnalytics', 'auth' => true],
        ['method' => 'GET', 'path' => '/api/tickets/reports', 'handler' => 'getReports', 'auth' => true],

        // Knowledge Base
        ['method' => 'GET', 'path' => '/api/knowledge-base', 'handler' => 'getKnowledgeBase', 'auth' => false],
        ['method' => 'GET', 'path' => '/api/knowledge-base/search', 'handler' => 'searchKnowledgeBase', 'auth' => false],
        ['method' => 'POST', 'path' => '/api/knowledge-base/{id}/feedback', 'handler' => 'submitArticleFeedback', 'auth' => false]
    ];

    /**
     * SLA policies cache
     */
    private array $slaPolicies = [];

    /**
     * Auto-assignment rules
     */
    private array $autoAssignmentRules = [];

    /**
     * AI-powered ticket assistant
     */
    private AITicketAssistant $aiAssistant;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->aiAssistant = new AITicketAssistant();
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
            'auto_assignment' => true,
            'sla_monitoring' => true,
            'customer_notifications' => true,
            'agent_notifications' => true,
            'escalation_enabled' => true,
            'max_attachments_per_ticket' => 5,
            'max_attachment_size' => 10485760, // 10MB
            'auto_close_resolved_tickets' => true,
            'auto_close_days' => 7,
            'customer_satisfaction_survey' => true,
            'knowledge_base_enabled' => true,
            'ai_assistance_enabled' => true,
            'business_hours' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
                'saturday' => null,
                'sunday' => null
            ]
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        $this->initializeSLAPolicies();
        $this->initializeCategories();
        $this->initializeTemplates();
        $this->setupAutoAssignmentRules();
        $this->setupNotificationTemplates();
    }

    /**
     * Initialize SLA policies
     */
    private function initializeSLAPolicies(): void
    {
        $this->slaPolicies = [
            [
                'name' => 'Standard Response',
                'priority' => 'medium',
                'response_time_hours' => 24,
                'resolution_time_hours' => 72,
                'escalation_time_hours' => 48
            ],
            [
                'name' => 'Priority Response',
                'priority' => 'high',
                'response_time_hours' => 4,
                'resolution_time_hours' => 24,
                'escalation_time_hours' => 8
            ],
            [
                'name' => 'Urgent Response',
                'priority' => 'urgent',
                'response_time_hours' => 1,
                'resolution_time_hours' => 8,
                'escalation_time_hours' => 2
            ],
            [
                'name' => 'Critical Response',
                'priority' => 'critical',
                'response_time_hours' => 0.5, // 30 minutes
                'resolution_time_hours' => 4,
                'escalation_time_hours' => 1
            ]
        ];

        // Save to database
        $this->saveSLAPoliciesToDatabase();
    }

    /**
     * Initialize ticket categories
     */
    private function initializeCategories(): void
    {
        $categories = [
            ['name' => 'General Inquiry', 'department' => 'general'],
            ['name' => 'Technical Support', 'department' => 'it'],
            ['name' => 'Billing & Payments', 'department' => 'finance'],
            ['name' => 'Permits & Licenses', 'department' => 'permitting'],
            ['name' => 'Complaints', 'department' => 'ombudsman'],
            ['name' => 'Emergency Services', 'department' => 'emergency'],
            ['name' => 'Health Services', 'department' => 'health'],
            ['name' => 'Education', 'department' => 'education'],
            ['name' => 'Social Services', 'department' => 'social_services'],
            ['name' => 'Transportation', 'department' => 'transportation'],
            ['name' => 'Environment', 'department' => 'environment'],
            ['name' => 'Housing', 'department' => 'housing'],
            ['name' => 'Business Services', 'department' => 'business'],
            ['name' => 'Legal Services', 'department' => 'legal'],
            ['name' => 'Immigration', 'department' => 'immigration']
        ];

        // Save to database
        $this->saveCategoriesToDatabase($categories);
    }

    /**
     * Initialize ticket templates
     */
    private function initializeTemplates(): void
    {
        $templates = [
            [
                'name' => 'Password Reset Request',
                'category' => 'Technical Support',
                'priority' => 'medium',
                'type' => 'task',
                'subject_template' => 'Password Reset Request - {customer_name}',
                'message_template' => 'Customer {customer_name} has requested a password reset for their account.'
            ],
            [
                'name' => 'Service Complaint',
                'category' => 'Complaints',
                'priority' => 'high',
                'type' => 'complaint',
                'subject_template' => 'Service Complaint - {customer_name}',
                'message_template' => 'Customer {customer_name} has submitted a complaint regarding service quality.'
            ],
            [
                'name' => 'Permit Inquiry',
                'category' => 'Permits & Licenses',
                'priority' => 'medium',
                'type' => 'question',
                'subject_template' => 'Permit Inquiry - {customer_name}',
                'message_template' => 'Customer {customer_name} has an inquiry about permits and licensing.'
            ]
        ];

        // Save to database
        $this->saveTemplatesToDatabase($templates);
    }

    /**
     * Setup auto-assignment rules
     */
    private function setupAutoAssignmentRules(): void
    {
        $this->autoAssignmentRules = [
            'category_based' => [
                'Technical Support' => 'it_support_team',
                'Billing & Payments' => 'finance_team',
                'Permits & Licenses' => 'permitting_team',
                'Complaints' => 'ombudsman_office',
                'Emergency Services' => 'emergency_coordinator',
                'Health Services' => 'health_services_team'
            ],
            'priority_based' => [
                'urgent' => 'senior_agents',
                'critical' => 'management_team'
            ],
            'keyword_based' => [
                'password' => 'it_support_team',
                'payment' => 'finance_team',
                'emergency' => 'emergency_coordinator',
                'complaint' => 'ombudsman_office'
            ]
        ];
    }

    /**
     * Setup notification templates
     */
    private function setupNotificationTemplates(): void
    {
        // This would integrate with the NotificationManager
        // to set up email/SMS templates for ticket events
    }

    /**
     * Create ticket
     */
    public function createTicket(array $ticketData, array $metadata = []): array
    {
        try {
            // Validate ticket data
            $validation = $this->validateTicketData($ticketData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors']
                ];
            }

            // Generate ticket ID
            $ticketId = $this->generateTicketId();

            // Determine priority and category using AI
            $aiAnalysis = $this->aiAssistant->analyzeTicketContent($ticketData);
            $priority = $aiAnalysis['priority'] ?? $ticketData['priority'] ?? 'medium';
            $category = $aiAnalysis['category'] ?? $ticketData['category'] ?? 'General Inquiry';

            // Prepare ticket data
            $ticket = [
                'ticket_id' => $ticketId,
                'title' => $ticketData['title'],
                'description' => $ticketData['description'],
                'status' => 'open',
                'priority' => $priority,
                'type' => $ticketData['type'] ?? 'question',
                'category' => $category,
                'channel' => $metadata['channel'] ?? 'web',
                'source' => $metadata['source'] ?? 'web_form',
                'customer_id' => $ticketData['customer_id'] ?? null,
                'customer_name' => $ticketData['customer_name'] ?? '',
                'customer_email' => $ticketData['customer_email'] ?? '',
                'customer_phone' => $ticketData['customer_phone'] ?? '',
                'created_by' => $ticketData['created_by'] ?? null,
                'tags' => json_encode($ticketData['tags'] ?? []),
                'metadata' => json_encode($metadata)
            ];

            // Set SLA based on priority
            $sla = $this->getSLAPolicy($priority);
            if ($sla) {
                $ticket['due_date'] = $this->calculateSLADueDate($sla);
            }

            // Save to database
            $this->saveTicket($ticket);

            // Auto-assign ticket
            if ($this->config['auto_assignment']) {
                $this->autoAssignTicket($ticketId, $ticket);
            }

            // Create initial comment if description provided
            if (!empty($ticketData['description'])) {
                $this->addTicketComment($ticketId, $ticketData['description'], 'customer', $ticketData['customer_id']);
            }

            // Send notifications
            $this->sendTicketNotifications('created', $ticket);

            // Check knowledge base for similar issues
            $similarArticles = $this->findSimilarKnowledgeBaseArticles($ticketData['description']);
            if (!empty($similarArticles)) {
                $this->suggestKnowledgeBaseArticles($ticketId, $similarArticles);
            }

            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'priority' => $priority,
                'estimated_response_time' => $sla ? $sla['response_time_hours'] . ' hours' : null,
                'message' => 'Ticket created successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error creating ticket: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create ticket'
            ];
        }
    }

    /**
     * Get tickets
     */
    public function getTickets(array $filters = [], array $pagination = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM tickets WHERE 1=1";
            $params = [];
            $whereConditions = [];

            // Apply filters
            if (isset($filters['status'])) {
                $whereConditions[] = "status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['priority'])) {
                $whereConditions[] = "priority = ?";
                $params[] = $filters['priority'];
            }

            if (isset($filters['category'])) {
                $whereConditions[] = "category = ?";
                $params[] = $filters['category'];
            }

            if (isset($filters['department'])) {
                $whereConditions[] = "department = ?";
                $params[] = $filters['department'];
            }

            if (isset($filters['assigned_to'])) {
                $whereConditions[] = "assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }

            if (isset($filters['customer_id'])) {
                $whereConditions[] = "customer_id = ?";
                $params[] = $filters['customer_id'];
            }

            if (isset($filters['date_from'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($whereConditions)) {
                $sql .= " AND " . implode(" AND ", $whereConditions);
            }

            // Add search functionality
            if (isset($filters['search'])) {
                $sql .= " AND (title LIKE ? OR description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Add ordering
            $orderBy = $filters['order_by'] ?? 'created_at';
            $orderDir = strtoupper($filters['order_dir'] ?? 'DESC');
            $orderDir = in_array($orderDir, ['ASC', 'DESC']) ? $orderDir : 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";

            // Add pagination
            $limit = $pagination['limit'] ?? 50;
            $offset = $pagination['offset'] ?? 0;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $tickets = $db->fetchAll($sql, $params);

            // Decode JSON fields and enrich data
            foreach ($tickets as &$ticket) {
                $ticket['tags'] = json_decode($ticket['tags'], true);
                $ticket['metadata'] = json_decode($ticket['metadata'], true);
                $ticket['sla_status'] = $this->calculateSLAStatus($ticket);
                $ticket['time_to_resolution'] = $this->calculateTimeToResolution($ticket);
            }

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM tickets WHERE 1=1";
            if (!empty($whereConditions)) {
                $countSql .= " AND " . implode(" AND ", $whereConditions);
            }
            $totalResult = $db->fetch($countSql, array_slice($params, 0, -2)); // Remove LIMIT and OFFSET params
            $total = $totalResult['total'] ?? 0;

            return [
                'success' => true,
                'data' => $tickets,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ],
                'count' => count($tickets)
            ];

        } catch (\Exception $e) {
            error_log("Error getting tickets: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve tickets'
            ];
        }
    }

    /**
     * Update ticket
     */
    public function updateTicket(string $ticketId, array $updateData, int $updatedBy): array
    {
        try {
            $ticket = $this->getTicketById($ticketId);
            if (!$ticket) {
                return [
                    'success' => false,
                    'error' => 'Ticket not found'
                ];
            }

            // Track changes for audit
            $changes = $this->trackTicketChanges($ticket, $updateData);

            // Prepare update data
            $updateData['updated_by'] = $updatedBy;
            $updateData['updated_at'] = date('Y-m-d H:i:s');

            // Handle status changes
            if (isset($updateData['status'])) {
                $this->handleStatusChange($ticketId, $ticket['status'], $updateData['status'], $updatedBy);
            }

            // Handle priority changes
            if (isset($updateData['priority']) && $updateData['priority'] !== $ticket['priority']) {
                $this->handlePriorityChange($ticketId, $updateData['priority']);
            }

            // Update ticket
            $this->updateTicketInDatabase($ticketId, $updateData);

            // Log changes
            $this->logTicketChanges($ticketId, $changes, $updatedBy);

            // Send notifications if needed
            if (!empty($changes)) {
                $this->sendTicketNotifications('updated', array_merge($ticket, $updateData), $changes);
            }

            return [
                'success' => true,
                'message' => 'Ticket updated successfully',
                'changes' => $changes
            ];

        } catch (\Exception $e) {
            error_log("Error updating ticket: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update ticket'
            ];
        }
    }

    /**
     * Assign ticket
     */
    public function assignTicket(string $ticketId, int $assignedTo, int $assignedBy, string $note = ''): array
    {
        try {
            $updateData = [
                'assigned_to' => $assignedTo,
                'assigned_by' => $assignedBy,
                'assigned_at' => date('Y-m-d H:i:s'),
                'status' => 'in_progress'
            ];

            $result = $this->updateTicket($ticketId, $updateData, $assignedBy);

            if ($result['success'] && !empty($note)) {
                $this->addTicketComment($ticketId, $note, 'agent', $assignedBy);
            }

            // Send assignment notification
            $this->sendAssignmentNotification($ticketId, $assignedTo);

            return $result;

        } catch (\Exception $e) {
            error_log("Error assigning ticket: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to assign ticket'
            ];
        }
    }

    /**
     * Add ticket comment
     */
    public function addTicketComment(string $ticketId, string $comment, string $authorType = 'agent', int $authorId = null, array $attachments = []): array
    {
        try {
            $commentData = [
                'ticket_id' => $ticketId,
                'comment' => $comment,
                'author_type' => $authorType,
                'author_id' => $authorId,
                'attachments' => json_encode($attachments),
                'metadata' => json_encode(['ip_address' => $_SERVER['REMOTE_ADDR'] ?? null])
            ];

            // Save comment
            $this->saveTicketComment($commentData);

            // Update ticket's updated_at timestamp
            $this->updateTicketInDatabase($ticketId, ['updated_at' => date('Y-m-d H:i:s')]);

            // Send notifications
            $this->sendCommentNotification($ticketId, $commentData);

            // Check if this resolves the ticket
            if ($this->isResolutionComment($comment)) {
                $this->markTicketAsResolved($ticketId, $authorId);
            }

            return [
                'success' => true,
                'message' => 'Comment added successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error adding ticket comment: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to add comment'
            ];
        }
    }

    /**
     * Close ticket
     */
    public function closeTicket(string $ticketId, int $closedBy, string $resolution = '', bool $sendSurvey = true): array
    {
        try {
            $updateData = [
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s'),
                'updated_by' => $closedBy
            ];

            // If resolution provided, add it as a comment
            if (!empty($resolution)) {
                $this->addTicketComment($ticketId, $resolution, 'agent', $closedBy);
            }

            $result = $this->updateTicket($ticketId, $updateData, $closedBy);

            if ($result['success'] && $sendSurvey && $this->config['customer_satisfaction_survey']) {
                $this->sendSatisfactionSurvey($ticketId);
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Error closing ticket: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to close ticket'
            ];
        }
    }

    /**
     * Escalate ticket
     */
    public function escalateTicket(string $ticketId, int $escalatedBy, string $reason): array
    {
        try {
            $ticket = $this->getTicketById($ticketId);
            if (!$ticket) {
                return [
                    'success' => false,
                    'error' => 'Ticket not found'
                ];
            }

            // Determine escalation level
            $currentLevel = $this->getCurrentEscalationLevel($ticketId);
            $newLevel = $currentLevel + 1;

            // Find escalation target
            $escalationTarget = $this->findEscalationTarget($ticket, $newLevel);

            // Create escalation record
            $escalationData = [
                'ticket_id' => $ticketId,
                'escalation_level' => $newLevel,
                'escalated_by' => $escalatedBy,
                'escalated_to' => $escalationTarget,
                'escalation_reason' => $reason
            ];

            $this->saveEscalation($escalationData);

            // Update ticket priority if needed
            $newPriority = $this->getEscalatedPriority($ticket['priority']);
            if ($newPriority !== $ticket['priority']) {
                $this->updateTicket($ticketId, ['priority' => $newPriority], $escalatedBy);
            }

            // Send escalation notifications
            $this->sendEscalationNotification($ticketId, $escalationData);

            return [
                'success' => true,
                'escalation_level' => $newLevel,
                'escalated_to' => $escalationTarget,
                'message' => 'Ticket escalated successfully'
            ];

        } catch (\Exception $e) {
            error_log("Error escalating ticket: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to escalate ticket'
            ];
        }
    }

    /**
     * Get ticket analytics
     */
    public function getAnalytics(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT
                        COUNT(*) as total_tickets,
                        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_tickets,
                        COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_tickets,
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_resolution_time,
                        COUNT(CASE WHEN sla_breach = 1 THEN 1 END) as sla_breaches,
                        AVG(CASE WHEN rating IS NOT NULL THEN rating END) as avg_satisfaction
                    FROM tickets t
                    LEFT JOIN ticket_satisfaction ts ON t.ticket_id = ts.ticket_id
                    WHERE 1=1";

            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND t.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND t.created_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['department'])) {
                $sql .= " AND t.department = ?";
                $params[] = $filters['department'];
            }

            $result = $db->fetch($sql, $params);

            // Calculate additional metrics
            $result['resolution_rate'] = $result['total_tickets'] > 0
                ? round(($result['resolved_tickets'] / $result['total_tickets']) * 100, 2)
                : 0;

            $result['sla_compliance_rate'] = $result['total_tickets'] > 0
                ? round((($result['total_tickets'] - $result['sla_breaches']) / $result['total_tickets']) * 100, 2)
                : 0;

            return [
                'success' => true,
                'data' => $result,
                'filters' => $filters
            ];

        } catch (\Exception $e) {
            error_log("Error getting analytics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve analytics'
            ];
        }
    }

    /**
     * Search knowledge base
     */
    public function searchKnowledgeBase(string $query, array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT *,
                        MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score
                    FROM knowledge_base
                    WHERE is_published = 1
                        AND MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
                    ORDER BY relevance_score DESC
                    LIMIT 20";

            $articles = $db->fetchAll($sql, [$query, $query]);

            // Decode tags
            foreach ($articles as &$article) {
                $article['tags'] = json_decode($article['tags'], true);
            }

            return [
                'success' => true,
                'data' => $articles,
                'query' => $query,
                'count' => count($articles)
            ];

        } catch (\Exception $e) {
            error_log("Error searching knowledge base: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to search knowledge base'
            ];
        }
    }

    // Helper methods (implementations would be added)

    private function generateTicketId(): string
    {
        return 'TICKET-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function validateTicketData(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }

        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }

        if (!empty($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function getSLAPolicy(string $priority): ?array
    {
        foreach ($this->slaPolicies as $policy) {
            if ($policy['priority'] === $priority) {
                return $policy;
            }
        }
        return null;
    }

    private function calculateSLADueDate(array $sla): string
    {
        $hours = $sla['response_time_hours'];
        return date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
    }

    private function autoAssignTicket(string $ticketId, array $ticket): void
    {
        // Implementation for auto-assignment logic
    }

    private function sendTicketNotifications(string $event, array $ticket, array $changes = []): void
    {
        // Implementation for sending notifications
    }

    private function findSimilarKnowledgeBaseArticles(string $description): array
    {
        // Implementation for finding similar articles
        return [];
    }

    private function suggestKnowledgeBaseArticles(string $ticketId, array $articles): void
    {
        // Implementation for suggesting articles
    }

    private function calculateSLAStatus(array $ticket): string
    {
        // Implementation for SLA status calculation
        return 'within_sla';
    }

    private function calculateTimeToResolution(array $ticket): ?int
    {
        // Implementation for time to resolution calculation
        return null;
    }

    private function trackTicketChanges(array $oldTicket, array $newData): array
    {
        // Implementation for tracking changes
        return [];
    }

    private function handleStatusChange(string $ticketId, string $oldStatus, string $newStatus, int $changedBy): void
    {
        // Implementation for status change handling
    }

    private function handlePriorityChange(string $ticketId, string $newPriority): void
    {
        // Implementation for priority change handling
    }

    private function updateTicketInDatabase(string $ticketId, array $data): void
    {
        // Implementation for database update
    }

    private function logTicketChanges(string $ticketId, array $changes, int $userId): void
    {
        // Implementation for logging changes
    }

    private function sendAssignmentNotification(string $ticketId, int $assignedTo): void
    {
        // Implementation for assignment notification
    }

    private function sendCommentNotification(string $ticketId, array $commentData): void
    {
        // Implementation for comment notification
    }

    private function isResolutionComment(string $comment): bool
    {
        // Implementation for detecting resolution comments
        return false;
    }

    private function markTicketAsResolved(string $ticketId, int $resolvedBy): void
    {
        // Implementation for marking ticket as resolved
    }

    private function sendSatisfactionSurvey(string $ticketId): void
    {
        // Implementation for sending satisfaction survey
    }

    private function getCurrentEscalationLevel(string $ticketId): int
    {
        // Implementation for getting current escalation level
        return 0;
    }

    private function findEscalationTarget(array $ticket, int $level): ?int
    {
        // Implementation for finding escalation target
        return null;
    }

    private function getEscalatedPriority(string $currentPriority): string
    {
        // Implementation for getting escalated priority
        return $currentPriority;
    }

    private function saveEscalation(array $escalationData): void
    {
        // Implementation for saving escalation
    }

    private function sendEscalationNotification(string $ticketId, array $escalationData): void
    {
        // Implementation for escalation notification
    }

    private function saveSLAPoliciesToDatabase(): void
    {
        // Implementation for saving SLA policies
    }

    private function saveCategoriesToDatabase(array $categories): void
    {
        // Implementation for saving categories
    }

    private function saveTemplatesToDatabase(array $templates): void
    {
        // Implementation for saving templates
    }

    private function saveTicket(array $ticket): void
    {
        // Implementation for saving ticket
    }

    private function saveTicketComment(array $commentData): void
    {
        // Implementation for saving comment
    }

    private function getTicketById(string $ticketId): ?array
    {
        // Implementation for getting ticket by ID
        return null;
    }

    // Additional helper methods would be implemented...
}

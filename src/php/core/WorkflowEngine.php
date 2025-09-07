<?php
/**
 * TPT Government Platform - Workflow Engine
 *
 * Comprehensive workflow automation system for government processes.
 * Handles business process automation, approvals, and task management.
 */

namespace Core;

class WorkflowEngine
{
    /**
     * Database instance
     */
    private Database $database;

    /**
     * Available workflow steps
     */
    private array $stepTypes = [
        'start' => 'Start Event',
        'task' => 'User Task',
        'approval' => 'Approval Task',
        'gateway' => 'Decision Gateway',
        'timer' => 'Timer Event',
        'end' => 'End Event',
        'subprocess' => 'Subprocess',
        'script' => 'Script Task'
    ];

    /**
     * Constructor
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Create a new workflow definition
     */
    public function createWorkflow(array $definition): array
    {
        // Validate workflow definition
        $validation = $this->validateWorkflowDefinition($definition);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid workflow definition: ' . implode(', ', $validation['errors'])
            ];
        }

        try {
            $workflowId = $this->database->insert('workflows', [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? '',
                'version' => $definition['version'] ?? '1.0.0',
                'definition' => json_encode($definition),
                'status' => 'draft',
                'created_by' => Session::getUserId(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Store workflow steps
            if (isset($definition['steps'])) {
                $this->storeWorkflowSteps($workflowId, $definition['steps']);
            }

            return [
                'success' => true,
                'workflow_id' => $workflowId,
                'message' => 'Workflow created successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to create workflow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Start a workflow instance
     */
    public function startWorkflow(int $workflowId, array $data = []): array
    {
        try {
            // Get workflow definition
            $workflow = $this->database->selectOne(
                'SELECT * FROM workflows WHERE id = ? AND status = ?',
                [$workflowId, 'active']
            );

            if (!$workflow) {
                return [
                    'success' => false,
                    'error' => 'Workflow not found or not active'
                ];
            }

            $definition = json_decode($workflow['definition'], true);

            // Create workflow instance
            $instanceId = $this->database->insert('workflow_instances', [
                'workflow_id' => $workflowId,
                'status' => 'running',
                'current_step' => $definition['start_step'] ?? 'start',
                'data' => json_encode($data),
                'started_by' => Session::getUserId(),
                'started_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Create initial task
            $this->createInitialTask($instanceId, $definition, $data);

            return [
                'success' => true,
                'instance_id' => $instanceId,
                'message' => 'Workflow started successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to start workflow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Complete a workflow task
     */
    public function completeTask(int $taskId, array $data = []): array
    {
        try {
            // Get task
            $task = $this->database->selectOne(
                'SELECT * FROM workflow_tasks WHERE id = ?',
                [$taskId]
            );

            if (!$task) {
                return [
                    'success' => false,
                    'error' => 'Task not found'
                ];
            }

            // Check if user can complete this task
            if (!$this->canCompleteTask($task, Session::getUserId())) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to complete this task'
                ];
            }

            // Update task
            $this->database->update('workflow_tasks', [
                'status' => 'completed',
                'completed_by' => Session::getUserId(),
                'completed_at' => date('Y-m-d H:i:s'),
                'data' => json_encode($data),
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $taskId]);

            // Process next steps
            $this->processNextSteps($task['instance_id'], $task['step_id'], $data);

            return [
                'success' => true,
                'message' => 'Task completed successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to complete task: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get workflow instance status
     */
    public function getWorkflowStatus(int $instanceId): array
    {
        try {
            $instance = $this->database->selectOne(
                'SELECT * FROM workflow_instances WHERE id = ?',
                [$instanceId]
            );

            if (!$instance) {
                return [
                    'success' => false,
                    'error' => 'Workflow instance not found'
                ];
            }

            // Get current tasks
            $tasks = $this->database->select(
                'SELECT * FROM workflow_tasks WHERE instance_id = ? AND status = ? ORDER BY created_at',
                [$instanceId, 'pending']
            );

            // Get completed tasks
            $completedTasks = $this->database->select(
                'SELECT * FROM workflow_tasks WHERE instance_id = ? AND status = ? ORDER BY completed_at',
                [$instanceId, 'completed']
            );

            return [
                'success' => true,
                'instance' => $instance,
                'current_tasks' => $tasks,
                'completed_tasks' => $completedTasks
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get workflow status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user's pending tasks
     */
    public function getUserTasks(int $userId, array $filters = []): array
    {
        try {
            $where = 'wt.assigned_to = ? AND wt.status = ?';
            $params = [$userId, 'pending'];

            // Add additional filters
            if (isset($filters['workflow_id'])) {
                $where .= ' AND wi.workflow_id = ?';
                $params[] = $filters['workflow_id'];
            }

            if (isset($filters['priority'])) {
                $where .= ' AND wt.priority = ?';
                $params[] = $filters['priority'];
            }

            $tasks = $this->database->select("
                SELECT wt.*, w.name as workflow_name, wi.data as instance_data
                FROM workflow_tasks wt
                JOIN workflow_instances wi ON wt.instance_id = wi.id
                JOIN workflows w ON wi.workflow_id = w.id
                WHERE {$where}
                ORDER BY wt.priority DESC, wt.created_at ASC
            ", $params);

            return [
                'success' => true,
                'tasks' => $tasks
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get user tasks: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a workflow task
     */
    private function createTask(int $instanceId, string $stepId, array $stepDefinition, array $data = []): void
    {
        $assignedTo = $this->determineAssignee($stepDefinition, $data);

        $this->database->insert('workflow_tasks', [
            'instance_id' => $instanceId,
            'step_id' => $stepId,
            'name' => $stepDefinition['name'] ?? $stepId,
            'description' => $stepDefinition['description'] ?? '',
            'type' => $stepDefinition['type'] ?? 'task',
            'assigned_to' => $assignedTo,
            'priority' => $stepDefinition['priority'] ?? 'medium',
            'due_date' => $this->calculateDueDate($stepDefinition),
            'data' => json_encode($data),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Send notification
        $this->notifyTaskAssignment($instanceId, $stepId, $assignedTo);
    }

    /**
     * Process next steps in workflow
     */
    private function processNextSteps(int $instanceId, string $completedStepId, array $data): void
    {
        // Get workflow definition
        $instance = $this->database->selectOne(
            'SELECT * FROM workflow_instances WHERE id = ?',
            [$instanceId]
        );

        $workflow = $this->database->selectOne(
            'SELECT * FROM workflows WHERE id = ?',
            [$instance['workflow_id']]
        );

        $definition = json_decode($workflow['definition'], true);

        // Find next steps
        $nextSteps = $this->findNextSteps($definition, $completedStepId, $data);

        foreach ($nextSteps as $nextStepId) {
            if (isset($definition['steps'][$nextStepId])) {
                $this->createTask($instanceId, $nextStepId, $definition['steps'][$nextStepId], $data);
            }
        }

        // Check if workflow is complete
        $this->checkWorkflowCompletion($instanceId, $definition);
    }

    /**
     * Find next steps based on conditions
     */
    private function findNextSteps(array $definition, string $currentStepId, array $data): array
    {
        $currentStep = $definition['steps'][$currentStepId] ?? null;
        if (!$currentStep || !isset($currentStep['transitions'])) {
            return [];
        }

        $nextSteps = [];

        foreach ($currentStep['transitions'] as $transition) {
            if ($this->evaluateCondition($transition, $data)) {
                $nextSteps[] = $transition['to'];
            }
        }

        return $nextSteps;
    }

    /**
     * Evaluate transition condition
     */
    private function evaluateCondition(array $transition, array $data): bool
    {
        if (!isset($transition['condition'])) {
            return true; // No condition means always true
        }

        $condition = $transition['condition'];

        // Simple field comparison
        if (isset($condition['field']) && isset($condition['operator']) && isset($condition['value'])) {
            $fieldValue = $data[$condition['field']] ?? null;

            switch ($condition['operator']) {
                case 'equals':
                    return $fieldValue == $condition['value'];
                case 'not_equals':
                    return $fieldValue != $condition['value'];
                case 'greater_than':
                    return $fieldValue > $condition['value'];
                case 'less_than':
                    return $fieldValue < $condition['value'];
                case 'contains':
                    return strpos($fieldValue, $condition['value']) !== false;
            }
        }

        return true;
    }

    /**
     * Determine task assignee
     */
    private function determineAssignee(array $stepDefinition, array $data): ?int
    {
        if (isset($stepDefinition['assignee'])) {
            return $stepDefinition['assignee'];
        }

        if (isset($stepDefinition['assignee_field']) && isset($data[$stepDefinition['assignee_field']])) {
            return $data[$stepDefinition['assignee_field']];
        }

        if (isset($stepDefinition['assignee_role'])) {
            return $this->getUserByRole($stepDefinition['assignee_role']);
        }

        return Session::getUserId(); // Default to current user
    }

    /**
     * Calculate task due date
     */
    private function calculateDueDate(array $stepDefinition): ?string
    {
        if (isset($stepDefinition['due_in_days'])) {
            return date('Y-m-d H:i:s', strtotime("+{$stepDefinition['due_in_days']} days"));
        }

        if (isset($stepDefinition['due_date'])) {
            return $stepDefinition['due_date'];
        }

        return null;
    }

    /**
     * Check if user can complete task
     */
    private function canCompleteTask(array $task, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        // Check direct assignment
        if ($task['assigned_to'] == $userId) {
            return true;
        }

        // Check role-based assignment
        if (isset($task['assigned_role'])) {
            return $this->userHasRole($userId, $task['assigned_role']);
        }

        return false;
    }

    /**
     * Get user by role
     */
    private function getUserByRole(string $role): ?int
    {
        try {
            $user = $this->database->selectOne(
                "SELECT id FROM users WHERE roles::jsonb ? ? AND active = true LIMIT 1",
                [$role, true]
            );

            return $user ? $user['id'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if user has role
     */
    private function userHasRole(int $userId, string $role): bool
    {
        try {
            $user = $this->database->selectOne(
                'SELECT roles FROM users WHERE id = ?',
                [$userId]
            );

            if ($user) {
                $roles = json_decode($user['roles'], true);
                return in_array($role, $roles);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create initial task for workflow
     */
    private function createInitialTask(int $instanceId, array $definition, array $data): void
    {
        $startStep = $definition['start_step'] ?? 'start';

        if (isset($definition['steps'][$startStep])) {
            $this->createTask($instanceId, $startStep, $definition['steps'][$startStep], $data);
        }
    }

    /**
     * Check if workflow is complete
     */
    private function checkWorkflowCompletion(int $instanceId, array $definition): void
    {
        // Get pending tasks
        $pendingTasks = $this->database->select(
            'SELECT COUNT(*) as count FROM workflow_tasks WHERE instance_id = ? AND status = ?',
            [$instanceId, 'pending']
        );

        if (($pendingTasks[0]['count'] ?? 0) === 0) {
            // No pending tasks, mark workflow as complete
            $this->database->update('workflow_instances', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $instanceId]);
        }
    }

    /**
     * Store workflow steps
     */
    private function storeWorkflowSteps(int $workflowId, array $steps): void
    {
        foreach ($steps as $stepId => $stepDefinition) {
            $this->database->insert('workflow_steps', [
                'workflow_id' => $workflowId,
                'step_id' => $stepId,
                'name' => $stepDefinition['name'] ?? $stepId,
                'type' => $stepDefinition['type'] ?? 'task',
                'config' => json_encode($stepDefinition),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Validate workflow definition
     */
    private function validateWorkflowDefinition(array $definition): array
    {
        $errors = [];

        if (!isset($definition['name']) || empty($definition['name'])) {
            $errors[] = 'Workflow name is required';
        }

        if (!isset($definition['steps']) || !is_array($definition['steps'])) {
            $errors[] = 'Workflow steps are required';
        }

        if (isset($definition['steps'])) {
            foreach ($definition['steps'] as $stepId => $step) {
                if (!isset($step['type']) || !isset($this->stepTypes[$step['type']])) {
                    $errors[] = "Invalid step type for step '{$stepId}'";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Send task assignment notification
     */
    private function notifyTaskAssignment(int $instanceId, string $stepId, ?int $assignedTo): void
    {
        if (!$assignedTo) {
            return;
        }

        // Implementation for sending notifications
        // This could integrate with email, SMS, or push notifications
        error_log("Task assigned to user {$assignedTo} for instance {$instanceId}, step {$stepId}");
    }

    /**
     * Get available step types
     */
    public function getStepTypes(): array
    {
        return $this->stepTypes;
    }

    /**
     * Get workflow definitions
     */
    public function getWorkflows(array $filters = []): array
    {
        try {
            $where = '1=1';
            $params = [];

            if (isset($filters['status'])) {
                $where .= ' AND status = ?';
                $params[] = $filters['status'];
            }

            if (isset($filters['created_by'])) {
                $where .= ' AND created_by = ?';
                $params[] = $filters['created_by'];
            }

            $workflows = $this->database->select(
                "SELECT * FROM workflows WHERE {$where} ORDER BY created_at DESC",
                $params
            );

            return [
                'success' => true,
                'workflows' => $workflows
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get workflows: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get workflow instances
     */
    public function getWorkflowInstances(array $filters = []): array
    {
        try {
            $where = '1=1';
            $params = [];

            if (isset($filters['workflow_id'])) {
                $where .= ' AND workflow_id = ?';
                $params[] = $filters['workflow_id'];
            }

            if (isset($filters['status'])) {
                $where .= ' AND status = ?';
                $params[] = $filters['status'];
            }

            if (isset($filters['started_by'])) {
                $where .= ' AND started_by = ?';
                $params[] = $filters['started_by'];
            }

            $instances = $this->database->select("
                SELECT wi.*, w.name as workflow_name
                FROM workflow_instances wi
                JOIN workflows w ON wi.workflow_id = w.id
                WHERE {$where}
                ORDER BY wi.started_at DESC
            ", $params);

            return [
                'success' => true,
                'instances' => $instances
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get workflow instances: ' . $e->getMessage()
            ];
        }
    }
}

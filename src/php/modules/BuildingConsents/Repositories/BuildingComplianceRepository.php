<?php
/**
 * TPT Government Platform - Building Compliance Repository
 *
 * Repository for building compliance requirements and tracking
 */

namespace Modules\BuildingConsents\Repositories;

use Core\Repository\BaseRepository;
use Core\Database;

class BuildingComplianceRepository extends BaseRepository
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->table = 'building_compliance';
        $this->primaryKey = 'compliance_id';
        $this->fillable = [
            'application_id',
            'requirement_type',
            'requirement_name',
            'description',
            'status',
            'due_date',
            'completed_date',
            'assigned_to',
            'evidence',
            'notes',
            'priority',
            'category',
            'created_at',
            'updated_at'
        ];
        $this->casts = [
            'assigned_to' => 'int',
            'due_date' => 'date',
            'completed_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'evidence' => 'json'
        ];
    }

    /**
     * Find compliance requirements by application
     */
    public function findByApplication(string $applicationId, array $options = []): array
    {
        return $this->findWhere(['application_id' => $applicationId], $options);
    }

    /**
     * Find compliance requirements by status
     */
    public function findByStatus(string $status, array $options = []): array
    {
        return $this->findWhere(['status' => $status], $options);
    }

    /**
     * Find compliance requirements by type
     */
    public function findByType(string $requirementType, array $options = []): array
    {
        return $this->findWhere(['requirement_type' => $requirementType], $options);
    }

    /**
     * Find compliance requirements by assignee
     */
    public function findByAssignee(int $assignedTo, array $options = []): array
    {
        return $this->findWhere(['assigned_to' => $assignedTo], $options);
    }

    /**
     * Find compliance requirements by priority
     */
    public function findByPriority(string $priority, array $options = []): array
    {
        return $this->findWhere(['priority' => $priority], $options);
    }

    /**
     * Find compliance requirements by category
     */
    public function findByCategory(string $category, array $options = []): array
    {
        return $this->findWhere(['category' => $category], $options);
    }

    /**
     * Get overdue compliance requirements
     */
    public function getOverdueRequirements(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM {$this->table}
                WHERE status IN ('pending', 'in_progress')
                AND due_date < ?
                ORDER BY due_date ASC, priority DESC";

        try {
            $results = $this->db->fetchAll($sql, [$today]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting overdue compliance requirements: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get compliance requirements due within days
     */
    public function getRequirementsDueWithin(int $days = 7): array
    {
        $today = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime("+{$days} days"));

        $sql = "SELECT * FROM {$this->table}
                WHERE status IN ('pending', 'in_progress')
                AND due_date BETWEEN ? AND ?
                ORDER BY due_date ASC, priority DESC";

        try {
            $results = $this->db->fetchAll($sql, [$today, $dueDate]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting compliance requirements due within {$days} days: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create compliance requirement
     */
    public function createRequirement(array $requirementData): int|string|false
    {
        $data = array_merge($requirementData, [
            'status' => $requirementData['status'] ?? 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->create($data);
    }

    /**
     * Update compliance status
     */
    public function updateStatus(int $complianceId, string $status, array $additionalData = []): bool
    {
        $data = array_merge($additionalData, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Set completed date if status is completed
        if ($status === 'completed' && !isset($additionalData['completed_date'])) {
            $data['completed_date'] = date('Y-m-d');
        }

        return $this->update($complianceId, $data);
    }

    /**
     * Assign compliance requirement
     */
    public function assignRequirement(int $complianceId, int $assignedTo): bool
    {
        return $this->update($complianceId, [
            'assigned_to' => $assignedTo,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Add evidence to compliance requirement
     */
    public function addEvidence(int $complianceId, array $evidence): bool
    {
        $requirement = $this->find($complianceId);

        if (!$requirement) {
            return false;
        }

        $existingEvidence = $requirement['evidence'] ?? [];
        $updatedEvidence = array_merge($existingEvidence, $evidence);

        return $this->update($complianceId, [
            'evidence' => $updatedEvidence,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get compliance statistics
     */
    public function getStatistics(): array
    {
        try {
            $sql = "SELECT
                        status,
                        requirement_type,
                        priority,
                        category,
                        COUNT(*) as count
                    FROM {$this->table}
                    GROUP BY status, requirement_type, priority, category
                    ORDER BY status, requirement_type";

            $results = $this->db->fetchAll($sql);

            $stats = [
                'total_requirements' => 0,
                'by_status' => [],
                'by_type' => [],
                'by_priority' => [],
                'by_category' => []
            ];

            foreach ($results as $result) {
                $status = $result['status'];
                $type = $result['requirement_type'];
                $priority = $result['priority'];
                $category = $result['category'];
                $count = (int)$result['count'];

                // By status
                if (!isset($stats['by_status'][$status])) {
                    $stats['by_status'][$status] = 0;
                }
                $stats['by_status'][$status] += $count;

                // By type
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type] += $count;

                // By priority
                if (!isset($stats['by_priority'][$priority])) {
                    $stats['by_priority'][$priority] = 0;
                }
                $stats['by_priority'][$priority] += $count;

                // By category
                if (!isset($stats['by_category'][$category])) {
                    $stats['by_category'][$category] = 0;
                }
                $stats['by_category'][$category] += $count;

                $stats['total_requirements'] += $count;
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting compliance statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get compliance requirements for application
     */
    public function getApplicationCompliance(string $applicationId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE application_id = ?
                ORDER BY priority DESC, due_date ASC";

        try {
            $results = $this->db->fetchAll($sql, [$applicationId]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting compliance requirements for application {$applicationId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get compliance completion rate for application
     */
    public function getCompletionRate(string $applicationId): array
    {
        $sql = "SELECT
                    status,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE application_id = ?
                GROUP BY status";

        try {
            $results = $this->db->fetchAll($sql, [$applicationId]);

            $completion = [
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'overdue' => 0,
                'completion_rate' => 0.0
            ];

            foreach ($results as $result) {
                $count = (int)$result['count'];
                $status = $result['status'];

                $completion[$status] = $count;
                $completion['total'] += $count;

                if ($status === 'completed') {
                    $completion['completed'] += $count;
                }
            }

            if ($completion['total'] > 0) {
                $completion['completion_rate'] = round(($completion['completed'] / $completion['total']) * 100, 2);
            }

            return $completion;
        } catch (\Exception $e) {
            error_log("Error getting completion rate for application {$applicationId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get high priority requirements
     */
    public function getHighPriorityRequirements(): array
    {
        return $this->findWhere([
            'priority' => 'high',
            'status' => ['pending', 'in_progress']
        ], ['orderBy' => 'due_date', 'orderDirection' => 'ASC']);
    }

    /**
     * Get requirements by due date range
     */
    public function findByDueDateRange(string $startDate, string $endDate, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE due_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];

        // Add ordering
        if (isset($options['orderBy'])) {
            $sql .= " ORDER BY {$options['orderBy']}";
            if (isset($options['orderDirection'])) {
                $sql .= " {$options['orderDirection']}";
            }
        }

        // Add limit
        if (isset($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
            if (isset($options['offset'])) {
                $sql .= " OFFSET {$options['offset']}";
            }
        }

        try {
            $results = $this->db->fetchAll($sql, $params);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error finding compliance requirements by due date range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get compliance requirements assigned to user
     */
    public function getAssignedToUser(int $userId): array
    {
        return $this->findWhere([
            'assigned_to' => $userId,
            'status' => ['pending', 'in_progress']
        ], ['orderBy' => 'due_date', 'orderDirection' => 'ASC']);
    }

    /**
     * Bulk update compliance status
     */
    public function bulkUpdateStatus(array $complianceIds, string $status, array $additionalData = []): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($complianceIds as $complianceId) {
            try {
                if ($this->updateStatus($complianceId, $status, $additionalData)) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update compliance ID: {$complianceId}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error updating compliance ID {$complianceId}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get compliance requirements by application and status
     */
    public function findByApplicationAndStatus(string $applicationId, string $status): array
    {
        return $this->findWhere([
            'application_id' => $applicationId,
            'status' => $status
        ], ['orderBy' => 'due_date', 'orderDirection' => 'ASC']);
    }

    /**
     * Check if application compliance is complete
     */
    public function isApplicationComplianceComplete(string $applicationId): bool
    {
        $sql = "SELECT COUNT(*) as pending_count FROM {$this->table}
                WHERE application_id = ?
                AND status IN ('pending', 'in_progress')";

        try {
            $result = $this->db->fetch($sql, [$applicationId]);
            return $result && (int)$result['pending_count'] === 0;
        } catch (\Exception $e) {
            error_log("Error checking application compliance status for {$applicationId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get compliance summary for application
     */
    public function getApplicationSummary(string $applicationId): array
    {
        $requirements = $this->getApplicationCompliance($applicationId);
        $completion = $this->getCompletionRate($applicationId);

        $summary = [
            'application_id' => $applicationId,
            'total_requirements' => count($requirements),
            'completion' => $completion,
            'overdue_count' => 0,
            'high_priority_count' => 0,
            'upcoming_deadlines' => []
        ];

        $today = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));

        foreach ($requirements as $requirement) {
            // Count overdue
            if ($requirement['status'] !== 'completed' &&
                $requirement['due_date'] &&
                $requirement['due_date'] < $today) {
                $summary['overdue_count']++;
            }

            // Count high priority
            if ($requirement['priority'] === 'high') {
                $summary['high_priority_count']++;
            }

            // Collect upcoming deadlines
            if ($requirement['status'] !== 'completed' &&
                $requirement['due_date'] &&
                $requirement['due_date'] >= $today &&
                $requirement['due_date'] <= $nextWeek) {
                $summary['upcoming_deadlines'][] = [
                    'requirement_name' => $requirement['requirement_name'],
                    'due_date' => $requirement['due_date'],
                    'priority' => $requirement['priority']
                ];
            }
        }

        return $summary;
    }
}

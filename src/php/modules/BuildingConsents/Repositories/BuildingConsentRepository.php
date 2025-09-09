<?php
/**
 * TPT Government Platform - Building Consent Repository
 *
 * Repository for building consent applications
 */

namespace Modules\BuildingConsents\Repositories;

use Core\Repository\BaseRepository;
use Core\Database;

class BuildingConsentRepository extends BaseRepository
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->table = 'building_consents';
        $this->primaryKey = 'application_id';
        $this->fillable = [
            'project_name',
            'project_type',
            'property_address',
            'property_type',
            'consent_type',
            'estimated_cost',
            'floor_area',
            'storeys',
            'architect_id',
            'contractor_id',
            'documents',
            'notes',
            'status',
            'owner_id',
            'created_at',
            'updated_at'
        ];
        $this->casts = [
            'estimated_cost' => 'float',
            'floor_area' => 'float',
            'storeys' => 'int',
            'architect_id' => 'int',
            'contractor_id' => 'int',
            'owner_id' => 'int',
            'documents' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Find applications by status
     */
    public function findByStatus(string $status, array $options = []): array
    {
        return $this->findWhere(['status' => $status], $options);
    }

    /**
     * Find applications by owner
     */
    public function findByOwner(int $ownerId, array $options = []): array
    {
        return $this->findWhere(['owner_id' => $ownerId], $options);
    }

    /**
     * Find applications by consent type
     */
    public function findByConsentType(string $consentType, array $options = []): array
    {
        return $this->findWhere(['consent_type' => $consentType], $options);
    }

    /**
     * Find applications within date range
     */
    public function findByDateRange(string $startDate, string $endDate, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE created_at BETWEEN ? AND ?";
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
            error_log("Error finding applications by date range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get applications requiring review
     */
    public function getPendingReview(array $options = []): array
    {
        $conditions = ['status' => 'submitted'];
        return $this->findWhere($conditions, $options);
    }

    /**
     * Get applications by project type
     */
    public function findByProjectType(string $projectType, array $options = []): array
    {
        return $this->findWhere(['project_type' => $projectType], $options);
    }

    /**
     * Update application status
     */
    public function updateStatus(string $applicationId, string $status, array $additionalData = []): bool
    {
        $data = array_merge($additionalData, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->update($applicationId, $data);
    }

    /**
     * Get application statistics
     */
    public function getStatistics(): array
    {
        try {
            $sql = "SELECT
                        status,
                        COUNT(*) as count,
                        SUM(estimated_cost) as total_cost,
                        AVG(estimated_cost) as avg_cost
                    FROM {$this->table}
                    GROUP BY status";

            $results = $this->db->fetchAll($sql);

            $stats = [
                'total_applications' => 0,
                'total_cost' => 0,
                'avg_cost' => 0,
                'by_status' => []
            ];

            foreach ($results as $result) {
                $stats['by_status'][$result['status']] = [
                    'count' => (int)$result['count'],
                    'total_cost' => (float)$result['total_cost'],
                    'avg_cost' => (float)$result['avg_cost']
                ];
                $stats['total_applications'] += (int)$result['count'];
                $stats['total_cost'] += (float)$result['total_cost'];
            }

            if ($stats['total_applications'] > 0) {
                $stats['avg_cost'] = $stats['total_cost'] / $stats['total_applications'];
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting application statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search applications
     */
    public function search(string $query, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE
                project_name LIKE ? OR
                property_address LIKE ? OR
                application_id LIKE ?";

        $searchTerm = "%{$query}%";
        $params = [$searchTerm, $searchTerm, $searchTerm];

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
            error_log("Error searching applications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get applications by cost range
     */
    public function findByCostRange(float $minCost, float $maxCost, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE estimated_cost BETWEEN ? AND ?";
        $params = [$minCost, $maxCost];

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
            error_log("Error finding applications by cost range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get overdue applications
     */
    public function getOverdueApplications(int $daysOverdue = 30): array
    {
        $overdueDate = date('Y-m-d H:i:s', strtotime("-{$daysOverdue} days"));

        $sql = "SELECT * FROM {$this->table}
                WHERE status IN ('submitted', 'approved')
                AND created_at < ?
                ORDER BY created_at ASC";

        try {
            $results = $this->db->fetchAll($sql, [$overdueDate]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting overdue applications: " . $e->getMessage());
            return [];
        }
    }
}

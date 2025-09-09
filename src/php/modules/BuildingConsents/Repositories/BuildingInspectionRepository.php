<?php
/**
 * TPT Government Platform - Building Inspection Repository
 *
 * Repository for building inspections
 */

namespace Modules\BuildingConsents\Repositories;

use Core\Repository\BaseRepository;
use Core\Database;

class BuildingInspectionRepository extends BaseRepository
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->table = 'building_inspections';
        $this->primaryKey = 'inspection_id';
        $this->fillable = [
            'application_id',
            'inspection_type',
            'scheduled_date',
            'scheduled_time',
            'inspector_id',
            'status',
            'result',
            'findings',
            'recommendations',
            'follow_up_required',
            'notes',
            'created_at',
            'updated_at',
            'completed_at'
        ];
        $this->casts = [
            'inspector_id' => 'int',
            'follow_up_required' => 'bool',
            'scheduled_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'completed_at' => 'datetime',
            'findings' => 'json',
            'recommendations' => 'json'
        ];
    }

    /**
     * Find inspections by application
     */
    public function findByApplication(string $applicationId, array $options = []): array
    {
        return $this->findWhere(['application_id' => $applicationId], $options);
    }

    /**
     * Find inspections by status
     */
    public function findByStatus(string $status, array $options = []): array
    {
        return $this->findWhere(['status' => $status], $options);
    }

    /**
     * Find inspections by inspector
     */
    public function findByInspector(int $inspectorId, array $options = []): array
    {
        return $this->findWhere(['inspector_id' => $inspectorId], $options);
    }

    /**
     * Find inspections by type
     */
    public function findByType(string $inspectionType, array $options = []): array
    {
        return $this->findWhere(['inspection_type' => $inspectionType], $options);
    }

    /**
     * Find inspections within date range
     */
    public function findByDateRange(string $startDate, string $endDate, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE scheduled_date BETWEEN ? AND ?";
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
            error_log("Error finding inspections by date range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get scheduled inspections for today
     */
    public function getTodaysInspections(): array
    {
        $today = date('Y-m-d');
        return $this->findWhere([
            'scheduled_date' => $today,
            'status' => 'scheduled'
        ], ['orderBy' => 'scheduled_time']);
    }

    /**
     * Get overdue inspections
     */
    public function getOverdueInspections(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM {$this->table}
                WHERE scheduled_date < ?
                AND status = 'scheduled'
                ORDER BY scheduled_date ASC, scheduled_time ASC";

        try {
            $results = $this->db->fetchAll($sql, [$today]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting overdue inspections: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get upcoming inspections
     */
    public function getUpcomingInspections(int $daysAhead = 7): array
    {
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

        $sql = "SELECT * FROM {$this->table}
                WHERE scheduled_date BETWEEN ? AND ?
                AND status = 'scheduled'
                ORDER BY scheduled_date ASC, scheduled_time ASC";

        try {
            $results = $this->db->fetchAll($sql, [$today, $futureDate]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting upcoming inspections: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Complete inspection
     */
    public function completeInspection(int $inspectionId, array $completionData): bool
    {
        $data = array_merge($completionData, [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->update($inspectionId, $data);
    }

    /**
     * Schedule inspection
     */
    public function scheduleInspection(array $inspectionData): int|string|false
    {
        $data = array_merge($inspectionData, [
            'status' => 'scheduled',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->create($data);
    }

    /**
     * Reschedule inspection
     */
    public function rescheduleInspection(int $inspectionId, string $newDate, string $newTime): bool
    {
        return $this->update($inspectionId, [
            'scheduled_date' => $newDate,
            'scheduled_time' => $newTime,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Cancel inspection
     */
    public function cancelInspection(int $inspectionId, string $reason): bool
    {
        return $this->update($inspectionId, [
            'status' => 'cancelled',
            'notes' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get inspection statistics
     */
    public function getStatistics(): array
    {
        try {
            $sql = "SELECT
                        status,
                        inspection_type,
                        COUNT(*) as count
                    FROM {$this->table}
                    GROUP BY status, inspection_type
                    ORDER BY status, inspection_type";

            $results = $this->db->fetchAll($sql);

            $stats = [
                'total_inspections' => 0,
                'by_status' => [],
                'by_type' => []
            ];

            foreach ($results as $result) {
                $status = $result['status'];
                $type = $result['inspection_type'];
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

                $stats['total_inspections'] += $count;
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting inspection statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get inspections requiring follow-up
     */
    public function getRequiringFollowUp(): array
    {
        return $this->findWhere([
            'follow_up_required' => true,
            'status' => 'completed'
        ], ['orderBy' => 'completed_at', 'orderDirection' => 'DESC']);
    }

    /**
     * Get inspector workload
     */
    public function getInspectorWorkload(int $inspectorId, string $startDate, string $endDate): array
    {
        $sql = "SELECT
                    scheduled_date,
                    COUNT(*) as inspection_count
                FROM {$this->table}
                WHERE inspector_id = ?
                AND scheduled_date BETWEEN ? AND ?
                AND status != 'cancelled'
                GROUP BY scheduled_date
                ORDER BY scheduled_date";

        try {
            $results = $this->db->fetchAll($sql, [$inspectorId, $startDate, $endDate]);

            $workload = [];
            foreach ($results as $result) {
                $workload[$result['scheduled_date']] = (int)$result['inspection_count'];
            }

            return $workload;
        } catch (\Exception $e) {
            error_log("Error getting inspector workload: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get inspection pass/fail rates
     */
    public function getPassFailRates(string $startDate = null, string $endDate = null): array
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');

        $sql = "SELECT
                    result,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE status = 'completed'
                AND completed_at BETWEEN ? AND ?
                GROUP BY result";

        try {
            $results = $this->db->fetchAll($sql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            $rates = [
                'pass' => 0,
                'fail' => 0,
                'total' => 0,
                'pass_rate' => 0.0
            ];

            foreach ($results as $result) {
                $count = (int)$result['count'];
                $rates[$result['result']] = $count;
                $rates['total'] += $count;
            }

            if ($rates['total'] > 0) {
                $rates['pass_rate'] = round(($rates['pass'] / $rates['total']) * 100, 2);
            }

            return $rates;
        } catch (\Exception $e) {
            error_log("Error getting pass/fail rates: " . $e->getMessage());
            return [];
        }
    }
}

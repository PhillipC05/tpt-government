<?php
/**
 * TPT Government Platform - Building Fee Repository
 *
 * Repository for building fees and payments
 */

namespace Modules\BuildingConsents\Repositories;

use Core\Repository\BaseRepository;
use Core\Database;

class BuildingFeeRepository extends BaseRepository
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->table = 'building_fees';
        $this->primaryKey = 'fee_id';
        $this->fillable = [
            'application_id',
            'fee_type',
            'description',
            'amount',
            'due_date',
            'status',
            'invoice_number',
            'payment_method',
            'payment_date',
            'transaction_id',
            'notes',
            'created_at',
            'updated_at'
        ];
        $this->casts = [
            'amount' => 'float',
            'due_date' => 'date',
            'payment_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Find fees by application
     */
    public function findByApplication(string $applicationId, array $options = []): array
    {
        return $this->findWhere(['application_id' => $applicationId], $options);
    }

    /**
     * Find fees by status
     */
    public function findByStatus(string $status, array $options = []): array
    {
        return $this->findWhere(['status' => $status], $options);
    }

    /**
     * Find fees by type
     */
    public function findByType(string $feeType, array $options = []): array
    {
        return $this->findWhere(['fee_type' => $feeType], $options);
    }

    /**
     * Find fees by invoice number
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?array
    {
        return $this->findBy('invoice_number', $invoiceNumber);
    }

    /**
     * Get overdue fees
     */
    public function getOverdueFees(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'pending'
                AND due_date < ?
                ORDER BY due_date ASC";

        try {
            $results = $this->db->fetchAll($sql, [$today]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting overdue fees: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get fees due within days
     */
    public function getFeesDueWithin(int $days = 7): array
    {
        $today = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime("+{$days} days"));

        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'pending'
                AND due_date BETWEEN ? AND ?
                ORDER BY due_date ASC";

        try {
            $results = $this->db->fetchAll($sql, [$today, $dueDate]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting fees due within {$days} days: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create fee invoice
     */
    public function createInvoice(array $feeData): int|string|false
    {
        // Generate invoice number if not provided
        if (!isset($feeData['invoice_number'])) {
            $feeData['invoice_number'] = $this->generateInvoiceNumber();
        }

        $data = array_merge($feeData, [
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->create($data);
    }

    /**
     * Process payment
     */
    public function processPayment(int $feeId, array $paymentData): bool
    {
        $data = array_merge($paymentData, [
            'status' => 'paid',
            'payment_date' => date('Y-m-d'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->update($feeId, $data);
    }

    /**
     * Cancel fee
     */
    public function cancelFee(int $feeId, string $reason): bool
    {
        return $this->update($feeId, [
            'status' => 'cancelled',
            'notes' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get fee statistics
     */
    public function getStatistics(): array
    {
        try {
            $sql = "SELECT
                        status,
                        fee_type,
                        COUNT(*) as count,
                        SUM(amount) as total_amount,
                        AVG(amount) as avg_amount
                    FROM {$this->table}
                    GROUP BY status, fee_type
                    ORDER BY status, fee_type";

            $results = $this->db->fetchAll($sql);

            $stats = [
                'total_fees' => 0,
                'total_amount' => 0,
                'avg_amount' => 0,
                'by_status' => [],
                'by_type' => []
            ];

            foreach ($results as $result) {
                $status = $result['status'];
                $type = $result['fee_type'];
                $count = (int)$result['count'];
                $amount = (float)$result['total_amount'];

                // By status
                if (!isset($stats['by_status'][$status])) {
                    $stats['by_status'][$status] = [
                        'count' => 0,
                        'total_amount' => 0,
                        'avg_amount' => 0
                    ];
                }
                $stats['by_status'][$status]['count'] += $count;
                $stats['by_status'][$status]['total_amount'] += $amount;

                // By type
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = [
                        'count' => 0,
                        'total_amount' => 0,
                        'avg_amount' => 0
                    ];
                }
                $stats['by_type'][$type]['count'] += $count;
                $stats['by_type'][$type]['total_amount'] += $amount;

                $stats['total_fees'] += $count;
                $stats['total_amount'] += $amount;
            }

            // Calculate averages
            foreach ($stats['by_status'] as &$statusData) {
                if ($statusData['count'] > 0) {
                    $statusData['avg_amount'] = $statusData['total_amount'] / $statusData['count'];
                }
            }

            foreach ($stats['by_type'] as &$typeData) {
                if ($typeData['count'] > 0) {
                    $typeData['avg_amount'] = $typeData['total_amount'] / $typeData['count'];
                }
            }

            if ($stats['total_fees'] > 0) {
                $stats['avg_amount'] = $stats['total_amount'] / $stats['total_fees'];
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting fee statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment summary for date range
     */
    public function getPaymentSummary(string $startDate, string $endDate): array
    {
        $sql = "SELECT
                    DATE(payment_date) as payment_date,
                    COUNT(*) as payments_count,
                    SUM(amount) as total_amount
                FROM {$this->table}
                WHERE status = 'paid'
                AND payment_date BETWEEN ? AND ?
                GROUP BY DATE(payment_date)
                ORDER BY payment_date";

        try {
            $results = $this->db->fetchAll($sql, [$startDate, $endDate]);

            $summary = [
                'total_payments' => 0,
                'total_amount' => 0,
                'daily_breakdown' => []
            ];

            foreach ($results as $result) {
                $summary['daily_breakdown'][] = [
                    'date' => $result['payment_date'],
                    'payments_count' => (int)$result['payments_count'],
                    'total_amount' => (float)$result['total_amount']
                ];
                $summary['total_payments'] += (int)$result['payments_count'];
                $summary['total_amount'] += (float)$result['total_amount'];
            }

            return $summary;
        } catch (\Exception $e) {
            error_log("Error getting payment summary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get outstanding fees for application
     */
    public function getOutstandingFees(string $applicationId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE application_id = ?
                AND status = 'pending'
                ORDER BY due_date ASC";

        try {
            $results = $this->db->fetchAll($sql, [$applicationId]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting outstanding fees for application {$applicationId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate total outstanding amount for application
     */
    public function getTotalOutstandingAmount(string $applicationId): float
    {
        $sql = "SELECT SUM(amount) as total FROM {$this->table}
                WHERE application_id = ?
                AND status = 'pending'";

        try {
            $result = $this->db->fetch($sql, [$applicationId]);
            return $result ? (float)$result['total'] : 0.0;
        } catch (\Exception $e) {
            error_log("Error calculating total outstanding amount for application {$applicationId}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get fees by amount range
     */
    public function findByAmountRange(float $minAmount, float $maxAmount, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE amount BETWEEN ? AND ?";
        $params = [$minAmount, $maxAmount];

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
            error_log("Error finding fees by amount range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $timestamp = date('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}{$timestamp}{$random}";
    }

    /**
     * Get fees by due date range
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
            error_log("Error finding fees by due date range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get fees by payment date range
     */
    public function findByPaymentDateRange(string $startDate, string $endDate, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'paid'
                AND payment_date BETWEEN ? AND ?";
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
            error_log("Error finding fees by payment date range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Refund fee
     */
    public function refundFee(int $feeId, float $refundAmount, string $reason): bool
    {
        $fee = $this->find($feeId);

        if (!$fee || $fee['status'] !== 'paid') {
            return false;
        }

        // Create refund record (you might want to create a separate refunds table)
        $refundData = [
            'application_id' => $fee['application_id'],
            'fee_type' => 'refund',
            'description' => "Refund for {$fee['description']}: {$reason}",
            'amount' => -$refundAmount,
            'status' => 'completed',
            'notes' => $reason,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->create($refundData) !== false;
    }
}

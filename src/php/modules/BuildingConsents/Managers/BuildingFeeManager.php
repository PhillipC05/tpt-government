<?php
/**
 * TPT Government Platform - Building Fee Manager
 *
 * Handles building consent fee calculation, payment processing, and management
 */

namespace Modules\BuildingConsents\Managers;

use Core\Database;
use Core\PaymentGateway;
use Core\NotificationManager;
use Exception;

class BuildingFeeManager
{
    private Database $db;
    private PaymentGateway $paymentGateway;
    private NotificationManager $notificationManager;
    private array $feeStructures;

    public function __construct(Database $db, PaymentGateway $paymentGateway, NotificationManager $notificationManager)
    {
        $this->db = $db;
        $this->paymentGateway = $paymentGateway;
        $this->notificationManager = $notificationManager;
        $this->initializeFeeStructures();
    }

    /**
     * Calculate application fees
     */
    public function calculateApplicationFees(array $applicationData, array $consentType): array
    {
        $fees = [];

        // Lodgement fee
        $fees[] = [
            'fee_type' => 'lodgement',
            'amount' => $this->feeStructures['lodgement_fee']['amount'],
            'description' => $this->feeStructures['lodgement_fee']['description'],
            'due_date' => date('Y-m-d', strtotime('+30 days'))
        ];

        // Processing fee (percentage based on estimated cost)
        $processingFee = $applicationData['estimated_cost'] * $this->feeStructures['processing_fee']['percentage'];
        $processingFee = max($processingFee, $this->feeStructures['processing_fee']['min_amount']);
        $processingFee = min($processingFee, $this->feeStructures['processing_fee']['max_amount']);

        $fees[] = [
            'fee_type' => 'processing',
            'amount' => $processingFee,
            'description' => $this->feeStructures['processing_fee']['description'],
            'due_date' => date('Y-m-d', strtotime('+30 days'))
        ];

        // Inspection fees based on required inspections
        foreach ($consentType['inspections_required'] as $inspectionType) {
            $fees[] = [
                'fee_type' => 'inspection',
                'amount' => $this->feeStructures['inspection_fee']['amount'],
                'description' => "Inspection fee for {$inspectionType}",
                'due_date' => date('Y-m-d', strtotime('+60 days'))
            ];
        }

        return $fees;
    }

    /**
     * Create application fees
     */
    public function createApplicationFees(string $applicationId, array $fees): bool
    {
        try {
            foreach ($fees as $fee) {
                $invoiceNumber = $this->generateInvoiceNumber();

                $sql = "INSERT INTO building_fees (
                    application_id, fee_type, amount, description, due_date, invoice_number
                ) VALUES (?, ?, ?, ?, ?, ?)";

                $params = [
                    $applicationId,
                    $fee['fee_type'],
                    $fee['amount'],
                    $fee['description'],
                    $fee['due_date'],
                    $invoiceNumber
                ];

                if (!$this->db->execute($sql, $params)) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Error creating application fees: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process fee payment
     */
    public function processFeePayment(string $invoiceNumber, array $paymentData): array
    {
        $fee = $this->getFee($invoiceNumber);
        if (!$fee) {
            return [
                'success' => false,
                'error' => 'Invoice not found'
            ];
        }

        if ($fee['status'] === 'paid') {
            return [
                'success' => false,
                'error' => 'Invoice already paid'
            ];
        }

        // Process payment
        $paymentResult = $this->paymentGateway->processPayment([
            'amount' => $fee['amount'],
            'currency' => 'USD',
            'method' => $paymentData['method'],
            'description' => "Building Consent Fee - {$fee['fee_type']}",
            'metadata' => [
                'invoice_number' => $invoiceNumber,
                'application_id' => $fee['application_id']
            ]
        ]);

        if (!$paymentResult['success']) {
            return [
                'success' => false,
                'error' => 'Payment processing failed'
            ];
        }

        // Update fee status
        if (!$this->updateFeeStatus($invoiceNumber, 'paid', date('Y-m-d'), $paymentData['method'])) {
            return [
                'success' => false,
                'error' => 'Failed to update fee status'
            ];
        }

        // Send notification
        $application = $this->getApplication($fee['application_id']);
        $this->sendNotification('fee_paid', $application['applicant_id'], [
            'invoice_number' => $invoiceNumber,
            'amount' => $fee['amount'],
            'fee_type' => $fee['fee_type']
        ]);

        return [
            'success' => true,
            'transaction_id' => $paymentResult['transaction_id'],
            'message' => 'Fee payment processed successfully'
        ];
    }

    /**
     * Get building fees
     */
    public function getFees(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM building_fees WHERE 1=1";
            $params = [];

            if (isset($filters['application_id'])) {
                $sql .= " AND application_id = ?";
                $params[] = $filters['application_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['fee_type'])) {
                $sql .= " AND fee_type = ?";
                $params[] = $filters['fee_type'];
            }

            $sql .= " ORDER BY created_at DESC";

            $results = $this->db->fetchAll($sql, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting fees: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve fees'
            ];
        }
    }

    /**
     * Get building fee
     */
    public function getFee(string $invoiceNumber): ?array
    {
        try {
            $sql = "SELECT * FROM building_fees WHERE invoice_number = ?";
            return $this->db->fetch($sql, [$invoiceNumber]);
        } catch (Exception $e) {
            error_log("Error getting fee: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update fee status
     */
    public function updateFeeStatus(string $invoiceNumber, string $status, string $paymentDate, string $paymentMethod): bool
    {
        try {
            $sql = "UPDATE building_fees SET status = ?, payment_date = ?, payment_method = ? WHERE invoice_number = ?";
            return $this->db->execute($sql, [$status, $paymentDate, $paymentMethod, $invoiceNumber]);
        } catch (Exception $e) {
            error_log("Error updating fee status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get overdue fees
     */
    public function getOverdueFees(): array
    {
        try {
            $sql = "SELECT * FROM building_fees
                    WHERE status = 'unpaid'
                    AND due_date < CURDATE()
                    ORDER BY due_date ASC";

            $results = $this->db->fetchAll($sql);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting overdue fees: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve overdue fees'
            ];
        }
    }

    /**
     * Get fees due soon
     */
    public function getFeesDueSoon(int $daysAhead = 7): array
    {
        try {
            $sql = "SELECT * FROM building_fees
                    WHERE status = 'unpaid'
                    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY due_date ASC";

            $results = $this->db->fetchAll($sql, [$daysAhead]);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (Exception $e) {
            error_log("Error getting fees due soon: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve fees due soon'
            ];
        }
    }

    /**
     * Waive fee
     */
    public function waiveFee(string $invoiceNumber, string $reason): array
    {
        $fee = $this->getFee($invoiceNumber);
        if (!$fee) {
            return [
                'success' => false,
                'error' => 'Invoice not found'
            ];
        }

        if ($fee['status'] === 'paid') {
            return [
                'success' => false,
                'error' => 'Cannot waive paid invoice'
            ];
        }

        if (!$this->updateFeeStatus($invoiceNumber, 'waived', date('Y-m-d'), 'waived')) {
            return [
                'success' => false,
                'error' => 'Failed to waive fee'
            ];
        }

        // Log waiver reason
        $this->logFeeAction($invoiceNumber, 'waived', $reason);

        return [
            'success' => true,
            'message' => 'Fee waived successfully'
        ];
    }

    /**
     * Refund fee
     */
    public function refundFee(string $invoiceNumber, float $refundAmount, string $reason): array
    {
        $fee = $this->getFee($invoiceNumber);
        if (!$fee) {
            return [
                'success' => false,
                'error' => 'Invoice not found'
            ];
        }

        if ($fee['status'] !== 'paid') {
            return [
                'success' => false,
                'error' => 'Can only refund paid invoices'
            ];
        }

        if ($refundAmount > $fee['amount']) {
            return [
                'success' => false,
                'error' => 'Refund amount cannot exceed original fee amount'
            ];
        }

        // Process refund
        $refundResult = $this->paymentGateway->processRefund([
            'amount' => $refundAmount,
            'original_transaction_id' => $fee['transaction_id'] ?? null,
            'reason' => $reason,
            'metadata' => [
                'invoice_number' => $invoiceNumber,
                'application_id' => $fee['application_id']
            ]
        ]);

        if (!$refundResult['success']) {
            return [
                'success' => false,
                'error' => 'Refund processing failed'
            ];
        }

        // Update fee status to refunded
        $this->updateFeeStatus($invoiceNumber, 'refunded', date('Y-m-d'), 'refund');

        // Log refund
        $this->logFeeAction($invoiceNumber, 'refunded', $reason, $refundAmount);

        // Send notification
        $application = $this->getApplication($fee['application_id']);
        $this->sendNotification('fee_refunded', $application['applicant_id'], [
            'invoice_number' => $invoiceNumber,
            'refund_amount' => $refundAmount,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'refund_transaction_id' => $refundResult['transaction_id'],
            'message' => 'Fee refunded successfully'
        ];
    }

    /**
     * Generate fee report
     */
    public function generateFeeReport(array $filters = []): array
    {
        try {
            $sql = "SELECT
                        fee_type,
                        status,
                        COUNT(*) as count,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        DATE_FORMAT(created_at, '%Y-%m') as month
                    FROM building_fees
                    WHERE 1=1";

            $params = [];

            if (isset($filters['date_from'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (isset($filters['fee_type'])) {
                $sql .= " AND fee_type = ?";
                $params[] = $filters['fee_type'];
            }

            $sql .= " GROUP BY fee_type, status, DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month DESC, fee_type";

            $results = $this->db->fetchAll($sql, $params);

            // Calculate totals
            $totals = [
                'total_fees' => array_sum(array_column($results, 'count')),
                'total_revenue' => array_sum(array_column($results, 'total_amount')),
                'paid_fees' => 0,
                'unpaid_fees' => 0,
                'paid_revenue' => 0
            ];

            foreach ($results as $result) {
                if ($result['status'] === 'paid') {
                    $totals['paid_fees'] += $result['count'];
                    $totals['paid_revenue'] += $result['total_amount'];
                } elseif ($result['status'] === 'unpaid') {
                    $totals['unpaid_fees'] += $result['count'];
                }
            }

            return [
                'success' => true,
                'data' => $results,
                'totals' => $totals,
                'filters' => $filters,
                'generated_at' => date('c')
            ];
        } catch (Exception $e) {
            error_log("Error generating fee report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate fee report'
            ];
        }
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(string $invoiceNumber): array
    {
        $fee = $this->getFee($invoiceNumber);
        if (!$fee) {
            return [
                'success' => false,
                'error' => 'Invoice not found'
            ];
        }

        if ($fee['status'] === 'paid') {
            return [
                'success' => false,
                'error' => 'Invoice already paid'
            ];
        }

        // Send reminder notification
        $application = $this->getApplication($fee['application_id']);
        $this->sendNotification('payment_reminder', $application['applicant_id'], [
            'invoice_number' => $invoiceNumber,
            'amount' => $fee['amount'],
            'due_date' => $fee['due_date'],
            'fee_type' => $fee['fee_type']
        ]);

        // Log reminder
        $this->logFeeAction($invoiceNumber, 'reminder_sent', 'Payment reminder sent');

        return [
            'success' => true,
            'message' => 'Payment reminder sent successfully'
        ];
    }

    /**
     * Bulk send payment reminders
     */
    public function sendBulkPaymentReminders(): array
    {
        $overdueFees = $this->getOverdueFees();

        if (!$overdueFees['success']) {
            return $overdueFees;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($overdueFees['data'] as $fee) {
            $result = $this->sendPaymentReminder($fee['invoice_number']);
            if ($result['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'success' => true,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'message' => "Sent {$sentCount} payment reminders, {$failedCount} failed"
        ];
    }

    /**
     * Get application total fees
     */
    public function getApplicationTotalFees(string $applicationId): array
    {
        try {
            $sql = "SELECT
                        COUNT(*) as total_fees,
                        SUM(amount) as total_amount,
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                        SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                        MIN(due_date) as earliest_due_date
                    FROM building_fees
                    WHERE application_id = ?";

            $result = $this->db->fetch($sql, [$applicationId]);

            if ($result) {
                $result['outstanding_balance'] = $result['total_amount'] - $result['paid_amount'];
                $result['payment_status'] = $result['outstanding_balance'] > 0 ? 'pending' : 'paid';
            }

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Error getting application total fees: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to get application fees'
            ];
        }
    }

    /**
     * Log fee action
     */
    private function logFeeAction(string $invoiceNumber, string $action, string $reason, ?float $amount = null): void
    {
        try {
            // In a real implementation, this would log to an audit table
            $logData = [
                'invoice_number' => $invoiceNumber,
                'action' => $action,
                'reason' => $reason,
                'amount' => $amount,
                'timestamp' => date('c'),
                'user_id' => null // Would be set from session/auth
            ];

            error_log("Fee action logged: " . json_encode($logData));
        } catch (Exception $e) {
            error_log("Error logging fee action: " . $e->getMessage());
        }
    }

    /**
     * Get application
     */
    private function getApplication(string $applicationId): ?array
    {
        try {
            $sql = "SELECT * FROM building_consent_applications WHERE application_id = ?";
            return $this->db->fetch($sql, [$applicationId]);
        } catch (Exception $e) {
            error_log("Error getting application: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        return 'INV' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, int $userId, array $data = []): bool
    {
        try {
            return $this->notificationManager->sendNotification($type, $userId, $data);
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize fee structures
     */
    private function initializeFeeStructures(): void
    {
        $this->feeStructures = [
            'lodgement_fee' => [
                'type' => 'fixed',
                'amount' => 500.00,
                'description' => 'Application lodgement fee'
            ],
            'processing_fee' => [
                'type' => 'percentage',
                'percentage' => 0.003, // 0.3%
                'min_amount' => 200.00,
                'max_amount' => 5000.00,
                'description' => 'Processing fee based on project cost'
            ],
            'inspection_fee' => [
                'type' => 'fixed',
                'amount' => 150.00,
                'description' => 'Fee per inspection'
            ],
            'certification_fee' => [
                'type' => 'fixed',
                'amount' => 300.00,
                'description' => 'Certificate issuance fee'
            ]
        ];
    }

    /**
     * Get fee structures
     */
    public function getFeeStructures(): array
    {
        return $this->feeStructures;
    }

    /**
     * Update fee structure
     */
    public function updateFeeStructure(string $feeType, array $structure): bool
    {
        try {
            $this->feeStructures[$feeType] = array_merge($this->feeStructures[$feeType] ?? [], $structure);
            return true;
        } catch (Exception $e) {
            error_log("Error updating fee structure: " . $e->getMessage());
            return false;
        }
    }
}

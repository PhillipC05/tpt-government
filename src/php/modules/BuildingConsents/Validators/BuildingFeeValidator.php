<?php
/**
 * TPT Government Platform - Building Fee Validator
 *
 * Validator for building fee and payment data
 */

namespace Modules\BuildingConsents\Validators;

use Core\Validation\BaseValidator;

class BuildingFeeValidator extends BaseValidator
{
    /**
     * Validation rules for creating a fee invoice
     */
    public function getCreateInvoiceRules(): array
    {
        return [
            'application_id' => 'required|string|regex:/^BC\d{4}\d{6}$/',
            'fee_type' => 'required|in:consent_fee,inspection_fee,administration_fee,late_fee,amendment_fee',
            'description' => 'required|string|min:5|max:255',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date|after:today',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for processing payment
     */
    public function getProcessPaymentRules(): array
    {
        return [
            'payment_method' => 'required|in:credit_card,debit_card,bank_transfer,cash,cheque,online',
            'transaction_id' => 'string|max:100',
            'payment_reference' => 'string|max:100',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Validation rules for fee cancellation
     */
    public function getCancelRules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:500'
        ];
    }

    /**
     * Validation rules for fee refund
     */
    public function getRefundRules(): array
    {
        return [
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_reason' => 'required|string|min:10|max:500',
            'refund_method' => 'required|in:original_payment_method,bank_transfer,cheque,cash',
            'notes' => 'string|max:500'
        ];
    }

    /**
     * Custom validation messages
     */
    public function getCustomMessages(): array
    {
        return [
            'application_id.required' => 'Application ID is required',
            'application_id.regex' => 'Invalid application ID format',
            'fee_type.required' => 'Fee type is required',
            'fee_type.in' => 'Invalid fee type selected',
            'description.required' => 'Fee description is required',
            'description.min' => 'Fee description must be at least 5 characters',
            'description.max' => 'Fee description cannot exceed 255 characters',
            'amount.required' => 'Fee amount is required',
            'amount.numeric' => 'Fee amount must be a valid number',
            'amount.min' => 'Fee amount must be greater than 0',
            'due_date.required' => 'Due date is required',
            'due_date.date' => 'Due date must be a valid date',
            'due_date.after' => 'Due date must be in the future',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method selected',
            'transaction_id.string' => 'Transaction ID must be text',
            'transaction_id.max' => 'Transaction ID cannot exceed 100 characters',
            'payment_reference.string' => 'Payment reference must be text',
            'payment_reference.max' => 'Payment reference cannot exceed 100 characters',
            'notes.string' => 'Notes must be text',
            'notes.max' => 'Notes cannot exceed 500 characters',
            'reason.required' => 'Cancellation reason is required',
            'reason.min' => 'Cancellation reason must be at least 10 characters',
            'reason.max' => 'Cancellation reason cannot exceed 500 characters',
            'refund_amount.required' => 'Refund amount is required',
            'refund_amount.numeric' => 'Refund amount must be a valid number',
            'refund_amount.min' => 'Refund amount must be greater than 0',
            'refund_reason.required' => 'Refund reason is required',
            'refund_reason.min' => 'Refund reason must be at least 10 characters',
            'refund_reason.max' => 'Refund reason cannot exceed 500 characters',
            'refund_method.required' => 'Refund method is required',
            'refund_method.in' => 'Invalid refund method selected'
        ];
    }

    /**
     * Validate fee invoice creation
     */
    public function validateCreateInvoice(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getCreateInvoiceRules());
    }

    /**
     * Validate payment processing
     */
    public function validateProcessPayment(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getProcessPaymentRules());
    }

    /**
     * Validate fee cancellation
     */
    public function validateCancel(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getCancelRules());
    }

    /**
     * Validate fee refund
     */
    public function validateRefund(array $data): bool
    {
        return $this->validateWithMessages($data, $this->getRefundRules());
    }

    /**
     * Additional validation for business rules
     */
    protected function validateFeeType(string $field, mixed $value, array $parameters): bool
    {
        $validTypes = ['consent_fee', 'inspection_fee', 'administration_fee', 'late_fee', 'amendment_fee'];
        return in_array($value, $validTypes, true);
    }

    protected function validatePaymentMethod(string $field, mixed $value, array $parameters): bool
    {
        $validMethods = ['credit_card', 'debit_card', 'bank_transfer', 'cash', 'cheque', 'online'];
        return in_array($value, $validMethods, true);
    }

    protected function validateRefundMethod(string $field, mixed $value, array $parameters): bool
    {
        $validMethods = ['original_payment_method', 'bank_transfer', 'cheque', 'cash'];
        return in_array($value, $validMethods, true);
    }

    /**
     * Validate fee amount based on type
     */
    public function validateFeeAmountByType(array $data): bool
    {
        if (!isset($data['fee_type']) || !isset($data['amount'])) {
            return true; // Skip if data not available
        }

        $feeType = $data['fee_type'];
        $amount = (float)$data['amount'];

        // Define reasonable ranges for different fee types
        $ranges = [
            'consent_fee' => ['min' => 100, 'max' => 50000],
            'inspection_fee' => ['min' => 50, 'max' => 2000],
            'administration_fee' => ['min' => 25, 'max' => 500],
            'late_fee' => ['min' => 10, 'max' => 1000],
            'amendment_fee' => ['min' => 50, 'max' => 2000]
        ];

        if (!isset($ranges[$feeType])) {
            return false;
        }

        $range = $ranges[$feeType];
        return $amount >= $range['min'] && $amount <= $range['max'];
    }

    /**
     * Validate due date is reasonable
     */
    public function validateReasonableDueDate(array $data): bool
    {
        if (!isset($data['due_date'])) {
            return true; // Skip if date not provided
        }

        $dueDate = date_create($data['due_date']);
        $now = date_create();

        if (!$dueDate || !$now) {
            return false;
        }

        // Due date should be within 1 year from now
        $interval = date_diff($now, $dueDate);
        $days = (int)$interval->format('%r%a');

        return $days >= 1 && $days <= 365;
    }

    /**
     * Validate refund amount doesn't exceed original payment
     */
    public function validateRefundAmount(int $feeId, float $refundAmount): bool
    {
        // This would typically check the database to ensure refund amount
        // doesn't exceed the original payment amount

        // For now, we'll assume the refund amount is valid
        // In a real implementation, you would query the database

        return $refundAmount > 0;
    }

    /**
     * Validate payment method details
     */
    public function validatePaymentMethodDetails(array $data): bool
    {
        if (!isset($data['payment_method'])) {
            return true; // Skip if payment method not provided
        }

        $method = $data['payment_method'];

        switch ($method) {
            case 'credit_card':
            case 'debit_card':
                // Would validate card details in a real implementation
                return isset($data['card_number']) && isset($data['expiry_date']);
            case 'bank_transfer':
                // Would validate bank details in a real implementation
                return isset($data['account_number']) && isset($data['bank_code']);
            case 'cheque':
                // Would validate cheque details in a real implementation
                return isset($data['cheque_number']);
            case 'online':
                // Would validate online payment details in a real implementation
                return isset($data['payment_provider']) && isset($data['transaction_id']);
            case 'cash':
                // Cash payments typically don't need additional validation
                return true;
            default:
                return false;
        }
    }

    /**
     * Comprehensive validation with business rules
     */
    public function validateWithBusinessRules(array $data, string $operation = 'create_invoice'): array
    {
        $errors = [];

        // Basic validation
        $rules = match ($operation) {
            'create_invoice' => $this->getCreateInvoiceRules(),
            'process_payment' => $this->getProcessPaymentRules(),
            'cancel' => $this->getCancelRules(),
            'refund' => $this->getRefundRules(),
            default => []
        };

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Business rule validations
        if ($operation === 'create_invoice') {
            if (!$this->validateFeeAmountByType($data)) {
                $errors[] = 'Fee amount is outside reasonable range for the selected fee type';
            }

            if (!$this->validateReasonableDueDate($data)) {
                $errors[] = 'Due date must be within 1 year from today';
            }
        }

        if ($operation === 'process_payment') {
            if (!$this->validatePaymentMethodDetails($data)) {
                $errors[] = 'Payment method details are incomplete or invalid';
            }
        }

        if ($operation === 'refund') {
            if (isset($data['fee_id']) && isset($data['refund_amount'])) {
                if (!$this->validateRefundAmount($data['fee_id'], (float)$data['refund_amount'])) {
                    $errors[] = 'Refund amount is invalid or exceeds original payment';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate bulk fee operations
     */
    public function validateBulkOperation(array $feeIds, string $operation): array
    {
        $errors = [];

        if (empty($feeIds)) {
            $errors[] = 'At least one fee must be selected';
            return $errors;
        }

        if (count($feeIds) > 100) {
            $errors[] = 'Cannot process more than 100 fees at once';
        }

        $validOperations = ['cancel', 'process_payment'];
        if (!in_array($operation, $validOperations)) {
            $errors[] = 'Invalid bulk operation specified';
        }

        return $errors;
    }

    /**
     * Validate fee waiver request
     */
    public function validateFeeWaiver(array $data): array
    {
        $errors = [];

        $rules = [
            'fee_id' => 'required|integer|min:1',
            'waiver_reason' => 'required|string|min:20|max:1000',
            'waiver_percentage' => 'required|numeric|min:0|max:100',
            'approved_by' => 'required|integer|min:1',
            'justification' => 'required|string|min:50|max:2000',
            'supporting_documents' => 'array'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for fee waiver
        if (isset($data['waiver_percentage'])) {
            $percentage = (float)$data['waiver_percentage'];

            // Waivers over 50% require additional approval
            if ($percentage > 50 && !isset($data['senior_approval'])) {
                $errors[] = 'Waivers over 50% require senior management approval';
            }

            // Waivers over 90% are not allowed
            if ($percentage > 90) {
                $errors[] = 'Fee waivers cannot exceed 90%';
            }
        }

        return $errors;
    }

    /**
     * Validate fee adjustment
     */
    public function validateFeeAdjustment(array $data): array
    {
        $errors = [];

        $rules = [
            'fee_id' => 'required|integer|min:1',
            'adjustment_type' => 'required|in:increase,decrease',
            'adjustment_amount' => 'required|numeric|min:0.01',
            'adjustment_reason' => 'required|string|min:10|max:500',
            'approved_by' => 'required|integer|min:1',
            'effective_date' => 'required|date'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for fee adjustment
        if (isset($data['adjustment_type']) && isset($data['adjustment_amount'])) {
            $amount = (float)$data['adjustment_amount'];

            if ($data['adjustment_type'] === 'increase') {
                // Increases over 20% require additional approval
                if ($amount > 100) { // Assuming original fee is known
                    $errors[] = 'Fee increases over 20% require senior management approval';
                }
            } else {
                // Decreases over 50% require additional approval
                if ($amount > 250) { // Assuming original fee is known
                    $errors[] = 'Fee decreases over 50% require senior management approval';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate payment plan setup
     */
    public function validatePaymentPlan(array $data): array
    {
        $errors = [];

        $rules = [
            'fee_id' => 'required|integer|min:1',
            'number_of_installments' => 'required|integer|min:2|max:12',
            'installment_amount' => 'required|numeric|min:0.01',
            'first_installment_date' => 'required|date|after:today',
            'frequency' => 'required|in:weekly,monthly,quarterly',
            'setup_fee' => 'numeric|min:0',
            'late_payment_fee' => 'numeric|min:0'
        ];

        $isValid = $this->validateWithMessages($data, $rules);

        if (!$isValid) {
            $errors = array_merge($errors, $this->getMessages());
        }

        // Additional business rules for payment plan
        if (isset($data['number_of_installments']) && isset($data['installment_amount'])) {
            $installments = (int)$data['number_of_installments'];
            $amount = (float)$data['installment_amount'];

            // Minimum installment amount
            if ($amount < 10) {
                $errors[] = 'Installment amount must be at least $10';
            }

            // Maximum number of installments
            if ($installments > 12) {
                $errors[] = 'Payment plans cannot exceed 12 installments';
            }
        }

        return $errors;
    }
}

<?php
/**
 * TPT Government Platform - Payment Gateway System
 *
 * Comprehensive payment processing system supporting 21+ payment gateways,
 * multi-currency transactions, fraud detection, and compliance features
 */

class PaymentGateway
{
    private array $config;
    private array $gateways;
    private array $supportedCurrencies;
    private array $transactionHistory;
    private FraudDetection $fraudDetection;
    private ComplianceManager $complianceManager;

    /**
     * Supported payment gateways configuration
     */
    private array $gatewayConfig = [
        'stripe' => [
            'name' => 'Stripe',
            'type' => 'credit_card',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF'],
            'features' => ['3ds', 'sca', 'apple_pay', 'google_pay', 'recurring'],
            'fees' => ['domestic' => 0.029, 'international' => 0.039, 'fixed' => 0.30],
            'test_mode' => true
        ],
        'paypal' => [
            'name' => 'PayPal',
            'type' => 'digital_wallet',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
            'features' => ['express_checkout', 'recurring', 'refunds', 'disputes'],
            'fees' => ['domestic' => 0.029, 'international' => 0.044, 'fixed' => 0.30],
            'test_mode' => true
        ],
        'adyen' => [
            'name' => 'Adyen',
            'type' => 'payment_processor',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK'],
            'features' => ['3ds', 'sca', 'risk_scoring', 'recurring', 'payouts'],
            'fees' => ['domestic' => 0.025, 'international' => 0.035, 'fixed' => 0.25],
            'test_mode' => true
        ],
        'braintree' => [
            'name' => 'Braintree',
            'type' => 'credit_card',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'features' => ['3ds', 'sca', 'vault', 'recurring', 'apple_pay', 'google_pay'],
            'fees' => ['domestic' => 0.029, 'international' => 0.039, 'fixed' => 0.30],
            'test_mode' => true
        ],
        'square' => [
            'name' => 'Square',
            'type' => 'pos_payment',
            'supported_currencies' => ['USD', 'CAD', 'GBP', 'JPY', 'AUD', 'EUR'],
            'features' => ['in_person', 'online', 'recurring', 'inventory'],
            'fees' => ['domestic' => 0.026, 'international' => 0.030, 'fixed' => 0.10],
            'test_mode' => true
        ],
        'authorize_net' => [
            'name' => 'Authorize.Net',
            'type' => 'credit_card',
            'supported_currencies' => ['USD', 'CAD', 'GBP', 'EUR', 'AUD'],
            'features' => ['cim', 'arb', '3ds', 'fraud_detection'],
            'fees' => ['domestic' => 0.029, 'international' => 0.039, 'fixed' => 0.30],
            'test_mode' => true
        ],
        '2checkout' => [
            'name' => '2Checkout',
            'type' => 'payment_processor',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'JPY', 'CHF'],
            'features' => ['recurring', 'refunds', 'chargebacks', 'fraud_prevention'],
            'fees' => ['domestic' => 0.035, 'international' => 0.045, 'fixed' => 0.45],
            'test_mode' => true
        ],
        'worldpay' => [
            'name' => 'Worldpay',
            'type' => 'payment_processor',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
            'features' => ['3ds', 'sca', 'tokenization', 'recurring'],
            'fees' => ['domestic' => 0.029, 'international' => 0.039, 'fixed' => 0.25],
            'test_mode' => true
        ],
        'cybersource' => [
            'name' => 'CyberSource',
            'type' => 'payment_processor',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF'],
            'features' => ['3ds', 'sca', 'fraud_management', 'tokenization', 'payouts'],
            'fees' => ['domestic' => 0.029, 'international' => 0.039, 'fixed' => 0.25],
            'test_mode' => true
        ],
        'checkout_com' => [
            'name' => 'Checkout.com',
            'type' => 'payment_processor',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK'],
            'features' => ['3ds', 'sca', 'apple_pay', 'google_pay', 'recurring'],
            'fees' => ['domestic' => 0.029, 'international' => 0.039, 'fixed' => 0.25],
            'test_mode' => true
        ],
        'bank_transfer' => [
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF'],
            'features' => ['manual_processing', 'automated_reconciliation'],
            'fees' => ['domestic' => 0.00, 'international' => 0.01, 'fixed' => 0.00],
            'test_mode' => false
        ],
        'cash' => [
            'name' => 'Cash Payment',
            'type' => 'cash',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'features' => ['in_person', 'receipt_generation'],
            'fees' => ['domestic' => 0.00, 'international' => 0.00, 'fixed' => 0.00],
            'test_mode' => false
        ],
        'cheque' => [
            'name' => 'Cheque Payment',
            'type' => 'cheque',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'features' => ['manual_processing', 'automated_reconciliation'],
            'fees' => ['domestic' => 0.00, 'international' => 0.00, 'fixed' => 0.00],
            'test_mode' => false
        ],
        'paddle' => [
            'name' => 'Paddle',
            'type' => 'subscription_billing',
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
            'features' => ['subscriptions', 'tax_calculation', 'multi_currency'],
            'fees' => ['domestic' => 0.05, 'international' => 0.05, 'fixed' => 0.50],
            'test_mode' => true
        ],
        'gocardless' => [
            'name' => 'GoCardless',
            'type' => 'direct_debit',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'features' => ['direct_debit', 'recurring', 'instant_bank_pay'],
            'fees' => ['domestic' => 0.01, 'international' => 0.02, 'fixed' => 0.25],
            'test_mode' => true
        ],
        'bitcoin' => [
            'name' => 'Bitcoin',
            'type' => 'cryptocurrency',
            'supported_currencies' => ['BTC'],
            'features' => ['wallet_integration', 'transaction_monitoring'],
            'fees' => ['domestic' => 0.0001, 'international' => 0.0001, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'ethereum' => [
            'name' => 'Ethereum',
            'type' => 'cryptocurrency',
            'supported_currencies' => ['ETH'],
            'features' => ['smart_contracts', 'defi_integration'],
            'fees' => ['domestic' => 0.001, 'international' => 0.001, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'polygon' => [
            'name' => 'Polygon',
            'type' => 'cryptocurrency',
            'supported_currencies' => ['MATIC'],
            'features' => ['layer2_scaling', 'low_fees'],
            'fees' => ['domestic' => 0.0001, 'international' => 0.0001, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'bsc' => [
            'name' => 'Binance Smart Chain',
            'type' => 'cryptocurrency',
            'supported_currencies' => ['BNB'],
            'features' => ['high_throughput', 'low_cost'],
            'fees' => ['domestic' => 0.0001, 'international' => 0.0001, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'solana' => [
            'name' => 'Solana',
            'type' => 'cryptocurrency',
            'supported_currencies' => ['SOL'],
            'features' => ['high_performance', 'low_fees'],
            'fees' => ['domestic' => 0.000005, 'international' => 0.000005, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'usdc' => [
            'name' => 'USD Coin',
            'type' => 'stablecoin',
            'supported_currencies' => ['USDC'],
            'features' => ['stable_value', 'regulated'],
            'fees' => ['domestic' => 0.001, 'international' => 0.001, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'usdt' => [
            'name' => 'Tether',
            'type' => 'stablecoin',
            'supported_currencies' => ['USDT'],
            'features' => ['stable_value', 'high_liquidity'],
            'fees' => ['domestic' => 0.001, 'international' => 0.001, 'fixed' => 0.00],
            'test_mode' => true
        ],
        'dai' => [
            'name' => 'Dai',
            'type' => 'stablecoin',
            'supported_currencies' => ['DAI'],
            'features' => ['decentralized', 'stable_value'],
            'fees' => ['domestic' => 0.001, 'international' => 0.001, 'fixed' => 0.00],
            'test_mode' => true
        ]
    ];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_gateway' => 'stripe',
            'fallback_gateway' => 'paypal',
            'test_mode' => true,
            'fraud_detection' => true,
            'compliance_check' => true,
            'multi_currency' => true,
            'auto_retry' => true,
            'max_retries' => 3
        ], $config);

        $this->gateways = [];
        $this->transactionHistory = [];
        $this->supportedCurrencies = [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK',
            'BTC', 'ETH', 'MATIC', 'BNB', 'SOL', 'USDC', 'USDT', 'DAI'
        ];

        $this->fraudDetection = new FraudDetection();
        $this->complianceManager = new ComplianceManager();

        $this->initializeGateways();
    }

    /**
     * Initialize payment gateways
     */
    private function initializeGateways(): void
    {
        foreach ($this->gatewayConfig as $gatewayId => $gatewayConfig) {
            $this->gateways[$gatewayId] = $this->createGatewayInstance($gatewayId, $gatewayConfig);
        }
    }

    /**
     * Create gateway instance
     */
    private function createGatewayInstance(string $gatewayId, array $config): PaymentGatewayInterface
    {
        $className = ucfirst(str_replace(['_', '-'], '', $gatewayId)) . 'Gateway';

        if (!class_exists($className)) {
            // Create a generic gateway implementation
            return new GenericPaymentGateway($gatewayId, $config);
        }

        return new $className($config);
    }

    /**
     * Process payment
     */
    public function processPayment(array $paymentData): array
    {
        $gatewayId = $paymentData['gateway'] ?? $this->config['default_gateway'];
        $amount = $paymentData['amount'];
        $currency = $paymentData['currency'] ?? 'USD';
        $customerId = $paymentData['customer_id'] ?? null;

        // Validate payment data
        $validation = $this->validatePaymentData($paymentData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid payment data',
                'details' => $validation['errors']
            ];
        }

        // Fraud detection
        if ($this->config['fraud_detection']) {
            $fraudCheck = $this->fraudDetection->analyzeTransaction($paymentData);
            if ($fraudCheck['risk'] === 'high') {
                return [
                    'success' => false,
                    'error' => 'Transaction flagged for fraud review',
                    'requires_review' => true
                ];
            }
        }

        // Compliance check
        if ($this->config['compliance_check']) {
            $complianceCheck = $this->complianceManager->checkPaymentCompliance($paymentData);
            if (!$complianceCheck['compliant']) {
                return [
                    'success' => false,
                    'error' => 'Payment does not comply with regulations',
                    'compliance_issues' => $complianceCheck['issues']
                ];
            }
        }

        // Process payment with retry logic
        $result = $this->processPaymentWithRetry($gatewayId, $paymentData);

        // Record transaction
        $this->recordTransaction($paymentData, $result);

        return $result;
    }

    /**
     * Process payment with retry logic
     */
    private function processPaymentWithRetry(string $gatewayId, array $paymentData): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->config['max_retries']) {
            try {
                $gateway = $this->gateways[$gatewayId];
                $result = $gateway->processPayment($paymentData);

                if ($result['success']) {
                    return $result;
                }

                // If payment failed and we have fallback gateway, try it
                if ($attempts === 0 && $gatewayId !== $this->config['fallback_gateway']) {
                    $gatewayId = $this->config['fallback_gateway'];
                    $attempts++;
                    continue;
                }

                return $result;

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $attempts++;

                // Log retry attempt
                error_log("Payment retry attempt {$attempts} failed: {$lastError}");

                if ($attempts >= $this->config['max_retries']) {
                    break;
                }

                // Wait before retry (exponential backoff)
                sleep(pow(2, $attempts));
            }
        }

        return [
            'success' => false,
            'error' => 'Payment processing failed after retries',
            'last_error' => $lastError
        ];
    }

    /**
     * Process refund
     */
    public function processRefund(string $transactionId, float $amount = null, string $reason = ''): array
    {
        $transaction = $this->getTransaction($transactionId);

        if (!$transaction) {
            return [
                'success' => false,
                'error' => 'Transaction not found'
            ];
        }

        $gatewayId = $transaction['gateway'];
        $gateway = $this->gateways[$gatewayId];

        $refundData = [
            'transaction_id' => $transactionId,
            'amount' => $amount ?? $transaction['amount'],
            'reason' => $reason,
            'original_transaction' => $transaction
        ];

        $result = $gateway->processRefund($refundData);

        if ($result['success']) {
            $this->recordRefund($transactionId, $result);
        }

        return $result;
    }

    /**
     * Create subscription
     */
    public function createSubscription(array $subscriptionData): array
    {
        $gatewayId = $subscriptionData['gateway'] ?? $this->config['default_gateway'];
        $gateway = $this->gateways[$gatewayId];

        if (!in_array('recurring', $this->gatewayConfig[$gatewayId]['features'])) {
            return [
                'success' => false,
                'error' => 'Gateway does not support recurring payments'
            ];
        }

        $result = $gateway->createSubscription($subscriptionData);

        if ($result['success']) {
            $this->recordSubscription($subscriptionData, $result);
        }

        return $result;
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        $subscription = $this->getSubscription($subscriptionId);

        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'Subscription not found'
            ];
        }

        $gatewayId = $subscription['gateway'];
        $gateway = $this->gateways[$gatewayId];

        $result = $gateway->cancelSubscription($subscriptionId);

        if ($result['success']) {
            $this->updateSubscriptionStatus($subscriptionId, 'cancelled');
        }

        return $result;
    }

    /**
     * Process cryptocurrency payment
     */
    public function processCryptoPayment(array $paymentData): array
    {
        $cryptoType = $paymentData['crypto_type'] ?? 'bitcoin';

        // Generate wallet address for payment
        $walletAddress = $this->generateCryptoWalletAddress($cryptoType);

        // Create payment request
        $paymentRequest = [
            'wallet_address' => $walletAddress,
            'amount' => $paymentData['amount'],
            'currency' => $cryptoType,
            'description' => $paymentData['description'] ?? 'Government service payment',
            'expires_at' => time() + (15 * 60) // 15 minutes
        ];

        // Store payment request
        $this->storeCryptoPaymentRequest($paymentRequest);

        return [
            'success' => true,
            'payment_request' => $paymentRequest,
            'qr_code' => $this->generateCryptoQRCode($walletAddress, $paymentData['amount'], $cryptoType),
            'instructions' => $this->getCryptoPaymentInstructions($cryptoType)
        ];
    }

    /**
     * Validate payment data
     */
    private function validatePaymentData(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['amount', 'currency'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Amount validation
        if (isset($data['amount'])) {
            if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
                $errors[] = 'Invalid amount';
            }
        }

        // Currency validation
        if (isset($data['currency'])) {
            if (!in_array($data['currency'], $this->supportedCurrencies)) {
                $errors[] = 'Unsupported currency';
            }
        }

        // Gateway validation
        if (isset($data['gateway'])) {
            if (!isset($this->gateways[$data['gateway']])) {
                $errors[] = 'Invalid payment gateway';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Record transaction
     */
    private function recordTransaction(array $paymentData, array $result): void
    {
        $transaction = [
            'id' => $result['transaction_id'] ?? uniqid('txn_'),
            'gateway' => $paymentData['gateway'] ?? $this->config['default_gateway'],
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'USD',
            'customer_id' => $paymentData['customer_id'] ?? null,
            'status' => $result['success'] ? 'completed' : 'failed',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => $paymentData['metadata'] ?? [],
            'result' => $result
        ];

        $this->transactionHistory[] = $transaction;

        // Store in database (would be implemented)
        // $this->database->insert('transactions', $transaction);
    }

    /**
     * Record refund
     */
    private function recordRefund(string $transactionId, array $result): void
    {
        $refund = [
            'transaction_id' => $transactionId,
            'refund_id' => $result['refund_id'] ?? uniqid('ref_'),
            'amount' => $result['amount'],
            'reason' => $result['reason'] ?? '',
            'status' => 'completed',
            'created_at' => time()
        ];

        // Store refund record
        // $this->database->insert('refunds', $refund);
    }

    /**
     * Record subscription
     */
    private function recordSubscription(array $subscriptionData, array $result): void
    {
        $subscription = [
            'id' => $result['subscription_id'],
            'customer_id' => $subscriptionData['customer_id'],
            'gateway' => $subscriptionData['gateway'],
            'amount' => $subscriptionData['amount'],
            'currency' => $subscriptionData['currency'],
            'interval' => $subscriptionData['interval'],
            'status' => 'active',
            'created_at' => time(),
            'next_billing' => $this->calculateNextBillingDate($subscriptionData['interval'])
        ];

        // Store subscription record
        // $this->database->insert('subscriptions', $subscription);
    }

    /**
     * Get transaction
     */
    public function getTransaction(string $transactionId): ?array
    {
        // Search in transaction history
        foreach ($this->transactionHistory as $transaction) {
            if ($transaction['id'] === $transactionId) {
                return $transaction;
            }
        }

        // Would query database
        return null;
    }

    /**
     * Get subscription
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        // Would query database
        return null;
    }

    /**
     * Update subscription status
     */
    private function updateSubscriptionStatus(string $subscriptionId, string $status): void
    {
        // Would update database
    }

    /**
     * Calculate next billing date
     */
    private function calculateNextBillingDate(string $interval): int
    {
        $now = time();

        switch ($interval) {
            case 'monthly':
                return strtotime('+1 month', $now);
            case 'yearly':
                return strtotime('+1 year', $now);
            case 'weekly':
                return strtotime('+1 week', $now);
            case 'daily':
                return strtotime('+1 day', $now);
            default:
                return strtotime('+1 month', $now);
        }
    }

    /**
     * Generate crypto wallet address
     */
    private function generateCryptoWalletAddress(string $cryptoType): string
    {
        // This would integrate with crypto wallet service
        // For demo purposes, return a mock address
        return '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa'; // Example Bitcoin address
    }

    /**
     * Generate crypto QR code
     */
    private function generateCryptoQRCode(string $address, float $amount, string $cryptoType): string
    {
        // This would generate a QR code for the crypto payment
        // For demo purposes, return a data URL
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    /**
     * Get crypto payment instructions
     */
    private function getCryptoPaymentInstructions(string $cryptoType): string
    {
        $instructions = [
            'bitcoin' => 'Send the exact amount of BTC to the provided address. The transaction will be confirmed once it has 1 confirmation on the blockchain.',
            'ethereum' => 'Send ETH or ERC-20 tokens to the provided address. Include enough for gas fees.',
            'usdc' => 'Send USDC to the provided Ethereum address. Ensure you are sending on the Ethereum network.'
        ];

        return $instructions[$cryptoType] ?? 'Send the payment to the provided address.';
    }

    /**
     * Store crypto payment request
     */
    private function storeCryptoPaymentRequest(array $request): void
    {
        // Would store in database
    }

    /**
     * Get payment gateway statistics
     */
    public function getGatewayStatistics(): array
    {
        $stats = [];

        foreach ($this->gateways as $gatewayId => $gateway) {
            $stats[$gatewayId] = [
                'name' => $this->gatewayConfig[$gatewayId]['name'],
                'type' => $this->gatewayConfig[$gatewayId]['type'],
                'transaction_count' => $this->getGatewayTransactionCount($gatewayId),
                'success_rate' => $this->getGatewaySuccessRate($gatewayId),
                'average_processing_time' => $this->getGatewayAverageProcessingTime($gatewayId),
                'total_volume' => $this->getGatewayTotalVolume($gatewayId)
            ];
        }

        return $stats;
    }

    /**
     * Get gateway transaction count
     */
    private function getGatewayTransactionCount(string $gatewayId): int
    {
        return count(array_filter($this->transactionHistory, function($txn) use ($gatewayId) {
            return $txn['gateway'] === $gatewayId;
        }));
    }

    /**
     * Get gateway success rate
     */
    private function getGatewaySuccessRate(string $gatewayId): float
    {
        $gatewayTransactions = array_filter($this->transactionHistory, function($txn) use ($gatewayId) {
            return $txn['gateway'] === $gatewayId;
        });

        if (empty($gatewayTransactions)) {
            return 0.0;
        }

        $successfulTransactions = count(array_filter($gatewayTransactions, function($txn) {
            return $txn['status'] === 'completed';
        }));

        return ($successfulTransactions / count($gatewayTransactions)) * 100;
    }

    /**
     * Get gateway average processing time
     */
    private function getGatewayAverageProcessingTime(string $gatewayId): float
    {
        // Would calculate from transaction data
        return 2.5; // Mock value
    }

    /**
     * Get gateway total volume
     */
    private function getGatewayTotalVolume(string $gatewayId): float
    {
        $gatewayTransactions = array_filter($this->transactionHistory, function($txn) use ($gatewayId) {
            return $txn['gateway'] === $gatewayId && $txn['status'] === 'completed';
        });

        return array_sum(array_column($gatewayTransactions, 'amount'));
    }

    /**
     * Get supported gateways
     */
    public function getSupportedGateways(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * Get gateway configuration
     */
    public function getGatewayConfig(string $gatewayId): ?array
    {
        return $this->gatewayConfig[$gatewayId] ?? null;
    }

    /**
     * Check gateway availability
     */
    public function isGatewayAvailable(string $gatewayId): bool
    {
        if (!isset($this->gateways[$gatewayId])) {
            return false;
        }

        $gateway = $this->gateways[$gatewayId];
        return $gateway->isAvailable();
    }

    /**
     * Get optimal gateway for transaction
     */
    public function getOptimalGateway(array $transactionData): string
    {
        $amount = $transactionData['amount'];
        $currency = $transactionData['currency'] ?? 'USD';
        $country = $transactionData['country'] ?? 'US';

        // Simple optimization logic
        // In a real implementation, this would consider fees, success rates, processing time, etc.

        // For small amounts, use gateways with lower fixed fees
        if ($amount < 10) {
            return 'square';
        }

        // For international transactions, use gateways with good international support
        if ($country !== 'US') {
            return 'adyen';
        }

        // Default to Stripe for most transactions
        return 'stripe';
    }

    /**
     * Calculate payment fees
     */
    public function calculateFees(string $gatewayId, float $amount, string $currency, string $country = 'US'): array
    {
        $config = $this->gatewayConfig[$gatewayId];

        $isDomestic = $country === 'US';
        $feePercentage = $isDomestic ? $config['fees']['domestic'] : $config['fees']['international'];
        $fixedFee = $config['fees']['fixed'];

        $percentageFee = $amount * $feePercentage;
        $totalFee = $percentageFee + $fixedFee;

        return [
            'percentage_fee' => $percentageFee,
            'fixed_fee' => $fixedFee,
            'total_fee' => $totalFee,
            'net_amount' => $amount - $totalFee
        ];
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory(array $filters = []): array
    {
        $transactions = $this->transactionHistory;

        // Apply filters
        if (!empty($filters['gateway'])) {
            $transactions = array_filter($transactions, function($txn) use ($filters) {
                return $txn['gateway'] === $filters['gateway'];
            });
        }

        if (!empty($filters['status'])) {
            $transactions = array_filter($transactions, function($txn) use ($filters) {
                return $txn['status'] === $filters['status'];
            });
        }

        if (!empty($filters['date_from'])) {
            $transactions = array_filter($transactions, function($txn) use ($filters) {
                return $txn['created_at'] >= strtotime($filters['date_from']);
            });
        }

        if (!empty($filters['date_to'])) {
            $transactions = array_filter($transactions, function($txn) use ($filters) {
                return $txn['created_at'] <= strtotime($filters['date_to']);
            });
        }

        return array_values($transactions);
    }

    /**
     * Export transactions
     */
    public function exportTransactions(array $filters = [], string $format = 'csv'): string
    {
        $transactions = $this->getTransactionHistory($filters);

        switch ($format) {
            case 'csv':
                return $this->exportToCSV($transactions);
            case 'json':
                return json_encode($transactions);
            case 'xml':
                return $this->exportToXML($transactions);
            default:
                return json_encode($transactions);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCSV(array $transactions): string
    {
        $csv = "Transaction ID,Gateway,Amount,Currency,Status,Customer ID,Created At\n";

        foreach ($transactions as $transaction) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $transaction['id'],
                $transaction['gateway'],
                $transaction['amount'],
                $transaction['currency'],
                $transaction['status'],
                $transaction['customer_id'] ?? '',
                date('Y-m-d H:i:s', $transaction['created_at'])
            );
        }

        return $csv;
    }

    /**
     * Export to XML
     */
    private function exportToXML(array $transactions): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><transactions>';

        foreach ($transactions as $transaction) {
            $xml .= sprintf(
                '<transaction><id>%s</id><gateway>%s</gateway><amount>%s</amount><currency>%s</currency><status>%s</status><customer_id>%s</customer_id><created_at>%s</created_at></transaction>',
                $transaction['id'],
                $transaction['gateway'],
                $transaction['amount'],
                $transaction['currency'],
                $transaction['status'],
                $transaction['customer_id'] ?? '',
                date('Y-m-d H:i:s', $transaction['created_at'])
            );
        }

        $xml .= '</transactions>';

        return $xml;
    }
}

// Placeholder classes for dependencies
class FraudDetection {
    public function analyzeTransaction(array $transactionData): array {
        return ['risk' => 'low', 'score' => 0.1];
    }
}

class ComplianceManager {
    public function checkPaymentCompliance(array $paymentData): array {
        return ['compliant' => true, 'issues' => []];
    }
}

interface PaymentGatewayInterface {
    public function processPayment(array $paymentData): array;
    public function processRefund(array $refundData): array;
    public function createSubscription(array $subscriptionData): array;
    public function cancelSubscription(string $subscriptionId): array;
    public function isAvailable(): bool;
}

class GenericPaymentGateway implements PaymentGatewayInterface {
    private string $gatewayId;
    private array $config;

    public function __construct(string $gatewayId, array $config) {
        $this->gatewayId = $gatewayId;
        $this->config = $config;
    }

    public function processPayment(array $paymentData): array {
        // Mock payment processing
        return [
            'success' => true,
            'transaction_id' => uniqid('txn_'),
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'USD',
            'processing_time' => rand(1, 5)
        ];
    }

    public function processRefund(array $refundData): array {
        return [
            'success' => true,
            'refund_id' => uniqid('ref_'),
            'amount' => $refundData['amount']
        ];
    }

    public function createSubscription(array $subscriptionData): array {
        return [
            'success' => true,
            'subscription_id' => uniqid('sub_')
        ];
    }

    public function cancelSubscription(string $subscriptionId): array {
        return ['success' => true];
    }

    public function isAvailable(): bool {
        return true;
    }
}

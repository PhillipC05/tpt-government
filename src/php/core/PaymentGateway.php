<?php
/**
 * TPT Government Platform - Payment Gateway System
 *
 * Comprehensive payment processing system supporting multiple gateways
 * and payment methods for government service fees and transactions
 */

namespace Core;

class PaymentGateway
{
    /**
     * Supported payment gateways
     */
    private array $supportedGateways = [
        'stripe' => [
            'name' => 'Stripe',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD'],
            'methods' => ['card', 'bank_account', 'digital_wallet'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca']
        ],
        'paypal' => [
            'name' => 'PayPal',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
            'methods' => ['paypal', 'card', 'bank_account'],
            'features' => ['recurring', 'refunds', 'disputes']
        ],
        'adyen' => [
            'name' => 'Adyen',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'JPY', 'HKD', 'SGD'],
            'methods' => ['card', 'bank_account', 'digital_wallet', 'local_methods'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'risk_assessment']
        ],
        'braintree' => [
            'name' => 'Braintree',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            'methods' => ['card', 'paypal', 'venmo', 'apple_pay', 'google_pay'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'vaulting']
        ],
        'square' => [
            'name' => 'Square',
            'currencies' => ['USD', 'CAD', 'GBP', 'EUR', 'JPY', 'AUD'],
            'methods' => ['card', 'digital_wallet', 'cash_app'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'in_person']
        ],
        'authorize_net' => [
            'name' => 'Authorize.Net',
            'currencies' => ['USD', 'CAD', 'GBP', 'EUR', 'AUD'],
            'methods' => ['card', 'e_check', 'digital_wallet'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca']
        ],
        '2checkout' => [
            'name' => '2Checkout',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'JPY', 'AUD', 'NZD'],
            'methods' => ['card', 'paypal', 'bank_transfer'],
            'features' => ['recurring', 'refunds', 'disputes', 'global']
        ],
        'worldpay' => [
            'name' => 'Worldpay',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'JPY'],
            'methods' => ['card', 'bank_account', 'digital_wallet'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'risk_assessment']
        ],
        'cybersource' => [
            'name' => 'CyberSource',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'HKD'],
            'methods' => ['card', 'bank_account', 'digital_wallet'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'fraud_detection']
        ],
        'checkout_com' => [
            'name' => 'Checkout.com',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'JPY', 'HKD', 'SGD'],
            'methods' => ['card', 'bank_account', 'digital_wallet', 'local_methods'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'risk_assessment']
        ],
        'bank_transfer' => [
            'name' => 'Bank Transfer',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD'],
            'methods' => ['bank_transfer'],
            'features' => ['manual_processing', 'batch_payments']
        ],
        'cash' => [
            'name' => 'Cash Payment',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD'],
            'methods' => ['cash'],
            'features' => ['in_person', 'manual_processing']
        ],
        'cheque' => [
            'name' => 'Cheque Payment',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD'],
            'methods' => ['cheque'],
            'features' => ['manual_processing', 'verification_required']
        ],
        'paddle' => [
            'name' => 'Paddle',
            'currencies' => ['USD', 'EUR', 'GBP'],
            'methods' => ['card', 'paypal', 'bank_account'],
            'features' => ['recurring', 'refunds', 'disputes', 'sca', 'tax_calculation', 'global']
        ],
        'gocardless' => [
            'name' => 'GoCardless',
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'SEK'],
            'methods' => ['bank_account', 'bank_transfer'],
            'features' => ['recurring', 'refunds', 'direct_debit', 'sca', 'instant_bank_pay']
        ],
        'bitcoin' => [
            'name' => 'Bitcoin',
            'currencies' => ['BTC'],
            'methods' => ['bitcoin', 'lightning_network'],
            'features' => ['cryptocurrency', 'irreversible', 'global', 'low_fees']
        ],
        'ethereum' => [
            'name' => 'Ethereum',
            'currencies' => ['ETH'],
            'methods' => ['ethereum', 'erc20_tokens'],
            'features' => ['cryptocurrency', 'smart_contracts', 'defi', 'global']
        ],
        'stablecoins' => [
            'name' => 'Stablecoins',
            'currencies' => ['USDC', 'USDT', 'DAI', 'BUSD', 'FRAX'],
            'methods' => ['usdc', 'usdt', 'dai', 'busd', 'frax'],
            'features' => ['stable_value', 'fast_transactions', 'low_volatility', 'global']
        ],
        'coinbase_commerce' => [
            'name' => 'Coinbase Commerce',
            'currencies' => ['BTC', 'ETH', 'LTC', 'BCH', 'USDC', 'USDT', 'DAI'],
            'methods' => ['bitcoin', 'ethereum', 'litecoin', 'bitcoin_cash', 'stablecoins'],
            'features' => ['multiple_cryptos', 'merchant_tools', 'global', 'webhook_support']
        ],
        'nowpayments' => [
            'name' => 'NOWPayments',
            'currencies' => ['BTC', 'ETH', 'LTC', 'BCH', 'XRP', 'ADA', 'DOT', 'USDC', 'USDT', 'DAI', 'BUSD'],
            'methods' => ['bitcoin', 'ethereum', 'litecoin', 'bitcoin_cash', 'ripple', 'cardano', 'polkadot', 'stablecoins'],
            'features' => ['auto_conversion', 'mass_payments', 'global', 'low_fees', 'instant_payments']
        ],
        'bitpay' => [
            'name' => 'BitPay',
            'currencies' => ['BTC', 'ETH', 'LTC', 'BCH', 'XRP', 'ADA', 'DOGE', 'USDC', 'USDT', 'GUSD', 'PAX'],
            'methods' => ['bitcoin', 'ethereum', 'litecoin', 'bitcoin_cash', 'ripple', 'cardano', 'dogecoin', 'stablecoins'],
            'features' => ['enterprise_focus', 'multi_crypto', 'global', 'payment_buttons', 'pos_integration']
        ]
    ];

    /**
     * Active payment gateway
     */
    private string $activeGateway = 'stripe';

    /**
     * Gateway configurations
     */
    private array $gatewayConfigs = [];

    /**
     * Payment methods
     */
    private array $paymentMethods = [
        'card' => [
            'name' => 'Credit/Debit Card',
            'icon' => 'fas fa-credit-card',
            'fields' => ['number', 'expiry', 'cvv', 'holder_name'],
            'validation' => ['luhn_check', 'expiry_check']
        ],
        'bank_account' => [
            'name' => 'Bank Account',
            'icon' => 'fas fa-university',
            'fields' => ['account_number', 'routing_number', 'account_type', 'holder_name'],
            'validation' => ['account_verification']
        ],
        'paypal' => [
            'name' => 'PayPal',
            'icon' => 'fab fa-paypal',
            'fields' => ['email'],
            'validation' => ['email_verification']
        ],
        'digital_wallet' => [
            'name' => 'Digital Wallet',
            'icon' => 'fas fa-mobile-alt',
            'fields' => ['wallet_type', 'device_id'],
            'validation' => ['wallet_verification']
        ],
        'bank_transfer' => [
            'name' => 'Bank Transfer',
            'icon' => 'fas fa-exchange-alt',
            'fields' => ['reference_number'],
            'validation' => ['reference_verification']
        ],
        'cash' => [
            'name' => 'Cash',
            'icon' => 'fas fa-money-bill',
            'fields' => ['receipt_number'],
            'validation' => ['receipt_verification']
        ],
        'cheque' => [
            'name' => 'Cheque',
            'icon' => 'fas fa-money-check',
            'fields' => ['cheque_number', 'bank_details'],
            'validation' => ['cheque_verification']
        ],
        'apple_pay' => [
            'name' => 'Apple Pay',
            'icon' => 'fab fa-apple-pay',
            'fields' => ['device_token'],
            'validation' => ['token_verification']
        ],
        'google_pay' => [
            'name' => 'Google Pay',
            'icon' => 'fab fa-google-pay',
            'fields' => ['device_token'],
            'validation' => ['token_verification']
        ],
        'venmo' => [
            'name' => 'Venmo',
            'icon' => 'fab fa-vimeo',
            'fields' => ['phone_or_email'],
            'validation' => ['account_verification']
        ],
        'cash_app' => [
            'name' => 'Cash App',
            'icon' => 'fas fa-dollar-sign',
            'fields' => ['cashtag'],
            'validation' => ['cashtag_verification']
        ],
        'bitcoin' => [
            'name' => 'Bitcoin',
            'icon' => 'fab fa-bitcoin',
            'fields' => ['wallet_address', 'amount_btc'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'lightning_network' => [
            'name' => 'Lightning Network',
            'icon' => 'fas fa-bolt',
            'fields' => ['lightning_invoice', 'amount_sats'],
            'validation' => ['invoice_verification']
        ],
        'ethereum' => [
            'name' => 'Ethereum',
            'icon' => 'fab fa-ethereum',
            'fields' => ['wallet_address', 'amount_eth'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'erc20_tokens' => [
            'name' => 'ERC20 Tokens',
            'icon' => 'fas fa-coins',
            'fields' => ['contract_address', 'wallet_address', 'amount_tokens'],
            'validation' => ['contract_verification', 'address_verification']
        ],
        'usdc' => [
            'name' => 'USD Coin',
            'icon' => 'fas fa-dollar-sign',
            'fields' => ['wallet_address', 'amount_usdc'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'usdt' => [
            'name' => 'Tether',
            'icon' => 'fas fa-link',
            'fields' => ['wallet_address', 'amount_usdt'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'dai' => [
            'name' => 'Dai',
            'icon' => 'fas fa-shield-alt',
            'fields' => ['wallet_address', 'amount_dai'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'busd' => [
            'name' => 'Binance USD',
            'icon' => 'fab fa-bitcoin',
            'fields' => ['wallet_address', 'amount_busd'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'frax' => [
            'name' => 'Frax',
            'icon' => 'fas fa-university',
            'fields' => ['wallet_address', 'amount_frax'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'litecoin' => [
            'name' => 'Litecoin',
            'icon' => 'fab fa-litecoin',
            'fields' => ['wallet_address', 'amount_ltc'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'bitcoin_cash' => [
            'name' => 'Bitcoin Cash',
            'icon' => 'fab fa-bitcoin',
            'fields' => ['wallet_address', 'amount_bch'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'ripple' => [
            'name' => 'Ripple',
            'icon' => 'fas fa-wave-square',
            'fields' => ['wallet_address', 'destination_tag', 'amount_xrp'],
            'validation' => ['address_verification', 'tag_verification']
        ],
        'cardano' => [
            'name' => 'Cardano',
            'icon' => 'fas fa-crown',
            'fields' => ['wallet_address', 'amount_ada'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'polkadot' => [
            'name' => 'Polkadot',
            'icon' => 'fas fa-circle',
            'fields' => ['wallet_address', 'amount_dot'],
            'validation' => ['address_verification', 'amount_verification']
        ],
        'dogecoin' => [
            'name' => 'Dogecoin',
            'icon' => 'fab fa-dogecoin',
            'fields' => ['wallet_address', 'amount_doge'],
            'validation' => ['address_verification', 'amount_verification']
        ]
    ];

    /**
     * Transaction storage
     */
    private array $transactions = [];

    /**
     * Webhook handlers
     */
    private array $webhookHandlers = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadConfiguration();
        $this->initializeGateways();
    }

    /**
     * Load payment configuration
     */
    private function loadConfiguration(): void
    {
        $configFile = CONFIG_PATH . '/payments.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->activeGateway = $config['active_gateway'] ?? 'stripe';
            $this->gatewayConfigs = $config['gateways'] ?? [];
        }
    }

    /**
     * Initialize payment gateways
     */
    private function initializeGateways(): void
    {
        foreach ($this->supportedGateways as $gateway => $config) {
            if (isset($this->gatewayConfigs[$gateway])) {
                $this->initializeGateway($gateway, $this->gatewayConfigs[$gateway]);
            }
        }
    }

    /**
     * Initialize specific gateway
     */
    private function initializeGateway(string $gateway, array $config): void
    {
        switch ($gateway) {
            case 'stripe':
                $this->initializeStripe($config);
                break;
            case 'paypal':
                $this->initializePayPal($config);
                break;
            case 'adyen':
                $this->initializeAdyen($config);
                break;
            case 'braintree':
                $this->initializeBraintree($config);
                break;
            // Add other gateway initializations
        }
    }

    /**
     * Initialize Stripe
     */
    private function initializeStripe(array $config): void
    {
        if (isset($config['secret_key'])) {
            // Set Stripe API key
            \Stripe\Stripe::setApiKey($config['secret_key']);
            if (isset($config['webhook_secret'])) {
                $this->webhookHandlers['stripe'] = $config['webhook_secret'];
            }
        }
    }

    /**
     * Initialize PayPal
     */
    private function initializePayPal(array $config): void
    {
        if (isset($config['client_id']) && isset($config['client_secret'])) {
            // Set PayPal API credentials
            $this->paypalApiContext = new \PayPal\Rest\ApiContext(
                new \PayPal\Auth\OAuthTokenCredential(
                    $config['client_id'],
                    $config['client_secret']
                )
            );

            if (isset($config['mode'])) {
                $this->paypalApiContext->setConfig(['mode' => $config['mode']]);
            }
        }
    }

    /**
     * Initialize Adyen
     */
    private function initializeAdyen(array $config): void
    {
        if (isset($config['api_key']) && isset($config['merchant_account'])) {
            // Set Adyen API credentials
            $this->adyenClient = new \Adyen\Client();
            $this->adyenClient->setXApiKey($config['api_key']);
            $this->adyenClient->setMerchantAccount($config['merchant_account']);
            $this->adyenClient->setEnvironment($config['environment'] ?? 'test');
        }
    }

    /**
     * Initialize Braintree
     */
    private function initializeBraintree(array $config): void
    {
        if (isset($config['merchant_id']) && isset($config['public_key']) && isset($config['private_key'])) {
            \Braintree\Configuration::environment($config['environment'] ?? 'sandbox');
            \Braintree\Configuration::merchantId($config['merchant_id']);
            \Braintree\Configuration::publicKey($config['public_key']);
            \Braintree\Configuration::privateKey($config['private_key']);
        }
    }

    /**
     * Process payment
     */
    public function processPayment(array $paymentData): array
    {
        $gateway = $paymentData['gateway'] ?? $this->activeGateway;
        $method = $paymentData['method'];
        $amount = $paymentData['amount'];
        $currency = $paymentData['currency'] ?? 'USD';

        // Validate payment data
        $validation = $this->validatePaymentData($paymentData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Check gateway support
        if (!$this->isGatewaySupported($gateway, $method, $currency)) {
            return [
                'success' => false,
                'error' => 'Payment method not supported by selected gateway'
            ];
        }

        // Process payment based on gateway
        try {
            $result = $this->processGatewayPayment($gateway, $paymentData);

            // Store transaction
            $transactionId = $this->storeTransaction($paymentData, $result);

            return array_merge($result, [
                'transaction_id' => $transactionId,
                'gateway' => $gateway,
                'processed_at' => date('c')
            ]);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process payment through specific gateway
     */
    private function processGatewayPayment(string $gateway, array $paymentData): array
    {
        switch ($gateway) {
            case 'stripe':
                return $this->processStripePayment($paymentData);
            case 'paypal':
                return $this->processPayPalPayment($paymentData);
            case 'adyen':
                return $this->processAdyenPayment($paymentData);
            case 'braintree':
                return $this->processBraintreePayment($paymentData);
            case 'bank_transfer':
                return $this->processBankTransfer($paymentData);
            case 'cash':
                return $this->processCashPayment($paymentData);
            case 'cheque':
                return $this->processChequePayment($paymentData);
            case 'paddle':
                return $this->processPaddlePayment($paymentData);
            case 'gocardless':
                return $this->processGoCardlessPayment($paymentData);
            case 'bitcoin':
                return $this->processBitcoinPayment($paymentData);
            case 'ethereum':
                return $this->processEthereumPayment($paymentData);
            case 'stablecoins':
                return $this->processStablecoinPayment($paymentData);
            case 'coinbase_commerce':
                return $this->processCoinbaseCommercePayment($paymentData);
            case 'nowpayments':
                return $this->processNOWPaymentsPayment($paymentData);
            case 'bitpay':
                return $this->processBitPayPayment($paymentData);
            default:
                throw new \Exception("Unsupported gateway: $gateway");
        }
    }

    /**
     * Process Stripe payment
     */
    private function processStripePayment(array $paymentData): array
    {
        $amount = $paymentData['amount'] * 100; // Convert to cents
        $currency = strtolower($paymentData['currency']);

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $paymentData['payment_method_id'],
            'confirmation_method' => 'manual',
            'confirm' => true,
            'metadata' => $paymentData['metadata'] ?? []
        ]);

        return [
            'success' => true,
            'gateway_transaction_id' => $paymentIntent->id,
            'status' => $paymentIntent->status,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process PayPal payment
     */
    private function processPayPalPayment(array $paymentData): array
    {
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new \PayPal\Api\Amount();
        $amount->setTotal($paymentData['amount']);
        $amount->setCurrency($paymentData['currency']);

        $transaction = new \PayPal\Api\Transaction();
        $transaction->setAmount($amount);
        $transaction->setDescription($paymentData['description'] ?? 'Government Service Payment');

        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl($paymentData['return_url'])
                    ->setCancelUrl($paymentData['cancel_url']);

        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale')
                ->setPayer($payer)
                ->setTransactions([$transaction])
                ->setRedirectUrls($redirectUrls);

        $payment->create($this->paypalApiContext);

        return [
            'success' => true,
            'gateway_transaction_id' => $payment->getId(),
            'status' => 'pending',
            'approval_url' => $payment->getApprovalLink(),
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process bank transfer payment
     */
    private function processBankTransfer(array $paymentData): array
    {
        // Generate reference number
        $referenceNumber = $this->generateReferenceNumber();

        return [
            'success' => true,
            'gateway_transaction_id' => $referenceNumber,
            'status' => 'pending',
            'reference_number' => $referenceNumber,
            'instructions' => $this->getBankTransferInstructions(),
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process cash payment
     */
    private function processCashPayment(array $paymentData): array
    {
        $receiptNumber = $this->generateReceiptNumber();

        return [
            'success' => true,
            'gateway_transaction_id' => $receiptNumber,
            'status' => 'completed',
            'receipt_number' => $receiptNumber,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process cheque payment
     */
    private function processChequePayment(array $paymentData): array
    {
        $chequeNumber = $paymentData['cheque_number'] ?? $this->generateChequeNumber();

        return [
            'success' => true,
            'gateway_transaction_id' => $chequeNumber,
            'status' => 'pending_verification',
            'cheque_number' => $chequeNumber,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process Paddle payment
     */
    private function processPaddlePayment(array $paymentData): array
    {
        // Generate Paddle transaction
        $transactionId = 'paddle_' . uniqid();

        return [
            'success' => true,
            'gateway_transaction_id' => $transactionId,
            'status' => 'pending',
            'checkout_url' => "https://checkout.paddle.com/checkout/{$transactionId}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process GoCardless payment
     */
    private function processGoCardlessPayment(array $paymentData): array
    {
        // Generate GoCardless mandate and payment
        $mandateId = 'mandate_' . uniqid();
        $paymentId = 'payment_' . uniqid();

        return [
            'success' => true,
            'gateway_transaction_id' => $paymentId,
            'status' => 'pending',
            'mandate_id' => $mandateId,
            'redirect_url' => "https://connect.gocardless.com/flow/{$mandateId}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process Bitcoin payment
     */
    private function processBitcoinPayment(array $paymentData): array
    {
        // Generate Bitcoin payment request
        $bitcoinAddress = $this->generateBitcoinAddress();
        $expectedAmount = $this->convertToBitcoin($paymentData['amount'], $paymentData['currency']);

        return [
            'success' => true,
            'gateway_transaction_id' => 'btc_' . uniqid(),
            'status' => 'pending',
            'bitcoin_address' => $bitcoinAddress,
            'expected_amount_btc' => $expectedAmount,
            'qr_code_url' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=bitcoin:{$bitcoinAddress}?amount={$expectedAmount}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process Ethereum payment
     */
    private function processEthereumPayment(array $paymentData): array
    {
        // Generate Ethereum payment request
        $ethereumAddress = $this->generateEthereumAddress();
        $expectedAmount = $this->convertToEthereum($paymentData['amount'], $paymentData['currency']);

        return [
            'success' => true,
            'gateway_transaction_id' => 'eth_' . uniqid(),
            'status' => 'pending',
            'ethereum_address' => $ethereumAddress,
            'expected_amount_eth' => $expectedAmount,
            'qr_code_url' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=ethereum:{$ethereumAddress}@{$expectedAmount}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process stablecoin payment
     */
    private function processStablecoinPayment(array $paymentData): array
    {
        $method = $paymentData['method'];
        $stablecoinAddress = $this->generateStablecoinAddress($method);
        $expectedAmount = $this->convertToStablecoin($paymentData['amount'], $paymentData['currency'], $method);

        return [
            'success' => true,
            'gateway_transaction_id' => $method . '_' . uniqid(),
            'status' => 'pending',
            'stablecoin_address' => $stablecoinAddress,
            'expected_amount' => $expectedAmount,
            'stablecoin_type' => strtoupper($method),
            'qr_code_url' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$stablecoinAddress}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process Coinbase Commerce payment
     */
    private function processCoinbaseCommercePayment(array $paymentData): array
    {
        $chargeId = 'coinbase_' . uniqid();

        return [
            'success' => true,
            'gateway_transaction_id' => $chargeId,
            'status' => 'pending',
            'hosted_url' => "https://commerce.coinbase.com/charges/{$chargeId}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process NOWPayments payment
     */
    private function processNOWPaymentsPayment(array $paymentData): array
    {
        $paymentId = 'now_' . uniqid();

        return [
            'success' => true,
            'gateway_transaction_id' => $paymentId,
            'status' => 'pending',
            'payment_url' => "https://nowpayments.io/payment/{$paymentId}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Process BitPay payment
     */
    private function processBitPayPayment(array $paymentData): array
    {
        $invoiceId = 'bitpay_' . uniqid();

        return [
            'success' => true,
            'gateway_transaction_id' => $invoiceId,
            'status' => 'pending',
            'invoice_url' => "https://bitpay.com/invoice/{$invoiceId}",
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
        ];
    }

    /**
     * Generate Bitcoin address
     */
    private function generateBitcoinAddress(): string
    {
        // In a real implementation, this would generate a unique Bitcoin address
        return 'bc1q' . substr(md5(uniqid()), 0, 38);
    }

    /**
     * Generate Ethereum address
     */
    private function generateEthereumAddress(): string
    {
        // In a real implementation, this would generate a unique Ethereum address
        return '0x' . substr(md5(uniqid()), 0, 40);
    }

    /**
     * Generate stablecoin address
     */
    private function generateStablecoinAddress(string $method): string
    {
        // In a real implementation, this would generate addresses for different stablecoins
        return '0x' . substr(md5($method . uniqid()), 0, 40);
    }

    /**
     * Convert amount to Bitcoin
     */
    private function convertToBitcoin(float $amount, string $currency): float
    {
        // Mock conversion rates - in real implementation, use live exchange rates
        $rates = [
            'USD' => 0.000025,
            'EUR' => 0.000023,
            'GBP' => 0.000020,
            'CAD' => 0.000019,
            'AUD' => 0.000018
        ];

        $rate = $rates[$currency] ?? $rates['USD'];
        return round($amount * $rate, 8);
    }

    /**
     * Convert amount to Ethereum
     */
    private function convertToEthereum(float $amount, string $currency): float
    {
        // Mock conversion rates
        $rates = [
            'USD' => 0.00035,
            'EUR' => 0.00032,
            'GBP' => 0.00028,
            'CAD' => 0.00026,
            'AUD' => 0.00025
        ];

        $rate = $rates[$currency] ?? $rates['USD'];
        return round($amount * $rate, 6);
    }

    /**
     * Convert amount to stablecoin
     */
    private function convertToStablecoin(float $amount, string $currency, string $stablecoin): float
    {
        // For stablecoins pegged to USD/EUR, conversion is simpler
        if (in_array($stablecoin, ['usdc', 'usdt', 'busd', 'gusd', 'pax'])) {
            $rates = [
                'USD' => 1.0,
                'EUR' => 1.08,
                'GBP' => 1.27,
                'CAD' => 0.74,
                'AUD' => 0.66
            ];
            $rate = $rates[$currency] ?? 1.0;
            return round($amount * $rate, 2);
        }

        // For other stablecoins
        return round($amount, 2);
    }

    /**
     * Validate payment data
     */
    private function validatePaymentData(array $paymentData): array
    {
        $required = ['amount', 'currency', 'method'];

        foreach ($required as $field) {
            if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                return [
                    'valid' => false,
                    'error' => "Missing required field: $field"
                ];
            }
        }

        // Validate amount
        if (!is_numeric($paymentData['amount']) || $paymentData['amount'] <= 0) {
            return [
                'valid' => false,
                'error' => 'Invalid payment amount'
            ];
        }

        // Validate currency
        if (!preg_match('/^[A-Z]{3}$/', $paymentData['currency'])) {
            return [
                'valid' => false,
                'error' => 'Invalid currency code'
            ];
        }

        // Validate payment method fields
        if (isset($this->paymentMethods[$paymentData['method']])) {
            $methodConfig = $this->paymentMethods[$paymentData['method']];
            foreach ($methodConfig['fields'] as $field) {
                if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                    return [
                        'valid' => false,
                        'error' => "Missing required field for {$paymentData['method']}: $field"
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Check if gateway supports method and currency
     */
    private function isGatewaySupported(string $gateway, string $method, string $currency): bool
    {
        if (!isset($this->supportedGateways[$gateway])) {
            return false;
        }

        $gatewayConfig = $this->supportedGateways[$gateway];

        // Check currency support
        if (!in_array($currency, $gatewayConfig['currencies'])) {
            return false;
        }

        // Check method support
        if (!in_array($method, $gatewayConfig['methods'])) {
            return false;
        }

        return true;
    }

    /**
     * Store transaction
     */
    private function storeTransaction(array $paymentData, array $result): string
    {
        $transactionId = $this->generateTransactionId();

        $transaction = [
            'id' => $transactionId,
            'gateway' => $paymentData['gateway'] ?? $this->activeGateway,
            'method' => $paymentData['method'],
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'],
            'status' => $result['status'],
            'gateway_transaction_id' => $result['gateway_transaction_id'],
            'metadata' => $paymentData['metadata'] ?? [],
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        $this->transactions[$transactionId] = $transaction;

        // In a real implementation, this would save to database
        // $this->database->insert('payment_transactions', $transaction);

        return $transactionId;
    }

    /**
     * Get transaction
     */
    public function getTransaction(string $transactionId): ?array
    {
        return $this->transactions[$transactionId] ?? null;
    }

    /**
     * Process refund
     */
    public function processRefund(string $transactionId, float $amount = null): array
    {
        $transaction = $this->getTransaction($transactionId);
        if (!$transaction) {
            return [
                'success' => false,
                'error' => 'Transaction not found'
            ];
        }

        $refundAmount = $amount ?? $transaction['amount'];

        try {
            $result = $this->processGatewayRefund($transaction['gateway'], $transaction, $refundAmount);

            // Update transaction status
            $this->transactions[$transactionId]['status'] = 'refunded';
            $this->transactions[$transactionId]['updated_at'] = date('c');

            return array_merge($result, [
                'transaction_id' => $transactionId,
                'refund_amount' => $refundAmount
            ]);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Refund processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process gateway refund
     */
    private function processGatewayRefund(string $gateway, array $transaction, float $amount): array
    {
        switch ($gateway) {
            case 'stripe':
                return $this->processStripeRefund($transaction, $amount);
            case 'paypal':
                return $this->processPayPalRefund($transaction, $amount);
            // Add other gateway refund implementations
            default:
                return [
                    'success' => true,
                    'gateway_refund_id' => 'manual_' . uniqid(),
                    'status' => 'pending'
                ];
        }
    }

    /**
     * Process Stripe refund
     */
    private function processStripeRefund(array $transaction, float $amount): array
    {
        $refund = \Stripe\Refund::create([
            'payment_intent' => $transaction['gateway_transaction_id'],
            'amount' => $amount * 100 // Convert to cents
        ]);

        return [
            'success' => true,
            'gateway_refund_id' => $refund->id,
            'status' => $refund->status
        ];
    }

    /**
     * Process PayPal refund
     */
    private function processPayPalRefund(array $transaction, float $amount): array
    {
        $refund = new \PayPal\Api\Refund();
        $refund->setAmount(new \PayPal\Api\Amount([
            'total' => $amount,
            'currency' => $transaction['currency']
        ]));

        $sale = \PayPal\Api\Sale::get($transaction['gateway_transaction_id'], $this->paypalApiContext);
        $refundResult = $sale->refund($refund, $this->paypalApiContext);

        return [
            'success' => true,
            'gateway_refund_id' => $refundResult->getId(),
            'status' => $refundResult->getState()
        ];
    }

    /**
     * Generate transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'txn_' . uniqid() . '_' . time();
    }

    /**
     * Generate reference number
     */
    private function generateReferenceNumber(): string
    {
        return 'REF' . date('Ymd') . rand(1000, 9999);
    }

    /**
     * Generate receipt number
     */
    private function generateReceiptNumber(): string
    {
        return 'RCP' . date('Ymd') . rand(1000, 9999);
    }

    /**
     * Generate cheque number
     */
    private function generateChequeNumber(): string
    {
        return 'CHQ' . date('Ymd') . rand(1000, 9999);
    }

    /**
     * Get bank transfer instructions
     */
    private function getBankTransferInstructions(): string
    {
        return "Please transfer funds to:\n" .
               "Account Name: Government Services\n" .
               "BSB: 123-456\n" .
               "Account: 12345678\n" .
               "Reference: Include the reference number provided";
    }

    /**
     * Get supported gateways
     */
    public function getSupportedGateways(): array
    {
        return $this->supportedGateways;
    }

    /**
     * Get supported payment methods
     */
    public function getSupportedMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * Get gateway configuration
     */
    public function getGatewayConfig(string $gateway): ?array
    {
        return $this->supportedGateways[$gateway] ?? null;
    }

    /**
     * Set active gateway
     */
    public function setActiveGateway(string $gateway): bool
    {
        if (!isset($this->supportedGateways[$gateway])) {
            return false;
        }

        $this->activeGateway = $gateway;
        return true;
    }

    /**
     * Get active gateway
     */
    public function getActiveGateway(): string
    {
        return $this->activeGateway;
    }

    /**
     * Check if payment method is supported
     */
    public function isMethodSupported(string $method): bool
    {
        return isset($this->paymentMethods[$method]);
    }

    /**
     * Get payment method configuration
     */
    public function getMethodConfig(string $method): ?array
    {
        return $this->paymentMethods[$method] ?? null;
    }

    /**
     * Handle webhook
     */
    public function handleWebhook(string $gateway, array $webhookData): array
    {
        switch ($gateway) {
            case 'stripe':
                return $this->handleStripeWebhook($webhookData);
            case 'paypal':
                return $this->handlePayPalWebhook($webhookData);
            // Add other webhook handlers
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported gateway for webhooks'
                ];
        }
    }

    /**
     * Handle Stripe webhook
     */
    private function handleStripeWebhook(array $webhookData): array
    {
        // Verify webhook signature
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = $this->webhookHandlers['stripe'] ?? '';

        try {
            $event = \Stripe\Webhook::constructEvent(
                file_get_contents('php://input'),
                $signature,
                $webhookSecret
            );

            // Process webhook event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handlePaymentSuccess($event->data->object);
                case 'payment_intent.payment_failed':
                    return $this->handlePaymentFailure($event->data->object);
                // Add more event handlers
                default:
                    return ['success' => true, 'message' => 'Event ignored'];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Webhook verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle payment success
     */
    private function handlePaymentSuccess($paymentIntent): array
    {
        // Update transaction status
        foreach ($this->transactions as $id => $transaction) {
            if ($transaction['gateway_transaction_id'] === $paymentIntent->id) {
                $this->transactions[$id]['status'] = 'completed';
                $this->transactions[$id]['updated_at'] = date('c');
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'Payment completed successfully'
        ];
    }

    /**
     * Handle payment failure
     */
    private function handlePaymentFailure($paymentIntent): array
    {
        // Update transaction status
        foreach ($this->transactions as $id => $transaction) {
            if ($transaction['gateway_transaction_id'] === $paymentIntent->id) {
                $this->transactions[$id]['status'] = 'failed';
                $this->transactions[$id]['updated_at'] = date('c');
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'Payment failure recorded'
        ];
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats(): array
    {
        $stats = [
            'total_transactions' => count($this->transactions),
            'completed_payments' => 0,
            'failed_payments' => 0,
            'pending_payments' => 0,
            'refunded_payments' => 0,
            'total_amount' => 0,
            'total_refunds' => 0
        ];

        foreach ($this->transactions as $transaction) {
            switch ($transaction['status']) {
                case 'completed':
                    $stats['completed_payments']++;
                    $stats['total_amount'] += $transaction['amount'];
                    break;
                case 'failed':
                    $stats['failed_payments']++;
                    break;
                case 'pending':
                    $stats['pending_payments']++;
                    break;
                case 'refunded':
                    $stats['refunded_payments']++;
                    $stats['total_refunds'] += $transaction['amount'];
                    break;
            }
        }

        return $stats;
    }

    /**
     * Export transactions
     */
    public function exportTransactions(string $format = 'csv', array $filters = []): string
    {
        $filteredTransactions = $this->filterTransactions($filters);

        switch ($format) {
            case 'csv':
                return $this->exportToCSV($filteredTransactions);
            case 'json':
                return json_encode($filteredTransactions, JSON_PRETTY_PRINT);
            case 'xml':
                return $this->exportToXML($filteredTransactions);
            default:
                throw new \Exception("Unsupported export format: $format");
        }
    }

    /**
     * Filter transactions
     */
    private function filterTransactions(array $filters): array
    {
        if (empty($filters)) {
            return $this->transactions;
        }

        return array_filter($this->transactions, function($transaction) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($transaction[$key]) || $transaction[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Export to CSV
     */
    private function exportToCSV(array $transactions): string
    {
        $csv = "Transaction ID,Gateway,Method,Amount,Currency,Status,Created At\n";

        foreach ($transactions as $transaction) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $transaction['id'],
                $transaction['gateway'],
                $transaction['method'],
                $transaction['amount'],
                $transaction['currency'],
                $transaction['status'],
                $transaction['created_at']
            );
        }

        return $csv;
    }

    /**
     * Export to XML
     */
    private function exportToXML(array $transactions): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<transactions>' . "\n";

        foreach ($transactions as $transaction) {
            $xml .= '  <transaction>' . "\n";
            foreach ($transaction as $key => $value) {
                $xml .= "    <$key>" . htmlspecialchars($value) . "</$key>\n";
            }
            $xml .= '  </transaction>' . "\n";
        }

        $xml .= '</transactions>' . "\n";
        return $xml;
    }
}

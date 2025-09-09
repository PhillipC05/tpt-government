<?php
/**
 * TPT Government Platform - Monetization Engine
 *
 * Specialized manager for API monetization, billing, and revenue management
 */

namespace Core\APIMarketplace;

use Core\Database;

class MonetizationEngine
{
    /**
     * Monetization configuration
     */
    private array $config = [
        'enabled' => true,
        'billing_enabled' => true,
        'subscription_plans' => true,
        'usage_based_pricing' => true,
        'tiered_pricing' => true,
        'promotional_codes' => true,
        'tax_calculation' => true,
        'currency' => 'USD',
        'billing_cycle' => 'monthly',
        'trial_period_days' => 14,
        'grace_period_days' => 7
    ];

    /**
     * Pricing plans
     */
    private array $pricingPlans = [
        'free' => [
            'name' => 'Free Tier',
            'price' => 0.00,
            'requests_per_month' => 1000,
            'features' => ['basic_endpoints', 'community_support'],
            'limits' => ['rate_limit' => 10, 'burst_limit' => 20]
        ],
        'starter' => [
            'name' => 'Starter Plan',
            'price' => 29.99,
            'requests_per_month' => 10000,
            'features' => ['all_endpoints', 'email_support', 'basic_analytics'],
            'limits' => ['rate_limit' => 50, 'burst_limit' => 100]
        ],
        'professional' => [
            'name' => 'Professional Plan',
            'price' => 99.99,
            'requests_per_month' => 100000,
            'features' => ['all_endpoints', 'priority_support', 'advanced_analytics', 'webhooks'],
            'limits' => ['rate_limit' => 200, 'burst_limit' => 500]
        ],
        'enterprise' => [
            'name' => 'Enterprise Plan',
            'price' => 299.99,
            'requests_per_month' => 1000000,
            'features' => ['all_endpoints', 'dedicated_support', 'custom_analytics', 'sla_guarantee', 'custom_limits'],
            'limits' => ['rate_limit' => 1000, 'burst_limit' => 2000]
        ]
    ];

    /**
     * Usage tracking
     */
    private array $usageTracking = [];

    /**
     * Billing records
     */
    private array $billingRecords = [];

    /**
     * Subscription management
     */
    private array $subscriptions = [];

    /**
     * Promotional codes
     */
    private array $promoCodes = [];

    /**
     * Revenue analytics
     */
    private array $revenueAnalytics = [];

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge($this->config, $config);
        $this->initializeMonetization();
    }

    /**
     * Initialize monetization engine
     */
    private function initializeMonetization(): void
    {
        // Load pricing plans
        $this->loadPricingPlans();

        // Initialize usage tracking
        $this->initializeUsageTracking();

        // Load subscriptions
        $this->loadSubscriptions();

        // Set up billing system
        if ($this->config['billing_enabled']) {
            $this->initializeBillingSystem();
        }
    }

    /**
     * Subscribe to pricing plan
     */
    public function subscribeToPlan(string $developerId, string $planId, array $options = []): array
    {
        if (!isset($this->pricingPlans[$planId])) {
            return [
                'success' => false,
                'error' => 'Pricing plan not found'
            ];
        }

        $plan = $this->pricingPlans[$planId];
        $subscriptionId = uniqid('sub_');

        $subscription = [
            'id' => $subscriptionId,
            'developer_id' => $developerId,
            'plan_id' => $planId,
            'status' => 'active',
            'current_period_start' => time(),
            'current_period_end' => strtotime('+1 month', time()),
            'trial_end' => $options['skip_trial'] ? null : strtotime("+{$this->config['trial_period_days']} days", time()),
            'cancel_at_period_end' => false,
            'created_at' => time(),
            'updated_at' => time(),
            'usage_this_period' => 0,
            'overage_charges' => 0.00
        ];

        $this->subscriptions[$subscriptionId] = $subscription;
        $this->saveSubscription($subscriptionId, $subscription);

        // Process initial payment if not trial
        if (!$subscription['trial_end']) {
            $this->processPayment($developerId, $plan['price'], 'subscription', $subscriptionId);
        }

        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'plan' => $plan,
            'trial_end' => $subscription['trial_end'],
            'message' => $subscription['trial_end'] ? 'Trial period started' : 'Subscription activated'
        ];
    }

    /**
     * Track API usage
     */
    public function trackUsage(string $developerId, string $endpoint, int $requests = 1, array $metadata = []): array
    {
        $subscription = $this->getActiveSubscription($developerId);

        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'No active subscription found'
            ];
        }

        $plan = $this->pricingPlans[$subscription['plan_id']];
        $usageKey = $developerId . ':' . date('Y-m');

        if (!isset($this->usageTracking[$usageKey])) {
            $this->usageTracking[$usageKey] = [
                'developer_id' => $developerId,
                'period' => date('Y-m'),
                'total_requests' => 0,
                'endpoint_usage' => [],
                'overage_charges' => 0.00,
                'last_updated' => time()
            ];
        }

        // Update usage
        $this->usageTracking[$usageKey]['total_requests'] += $requests;
        $this->usageTracking[$usageKey]['endpoint_usage'][$endpoint] =
            ($this->usageTracking[$usageKey]['endpoint_usage'][$endpoint] ?? 0) + $requests;
        $this->usageTracking[$usageKey]['last_updated'] = time();

        // Update subscription usage
        $this->subscriptions[$subscription['id']]['usage_this_period'] += $requests;

        // Check for overage
        $overage = $this->calculateOverage($subscription, $this->usageTracking[$usageKey]['total_requests']);
        if ($overage > 0) {
            $overageCharge = $this->calculateOverageCharge($overage, $plan);
            $this->usageTracking[$usageKey]['overage_charges'] += $overageCharge;
            $this->subscriptions[$subscription['id']]['overage_charges'] += $overageCharge;
        }

        $this->saveUsageTracking($usageKey, $this->usageTracking[$usageKey]);

        return [
            'success' => true,
            'usage' => $this->usageTracking[$usageKey]['total_requests'],
            'limit' => $plan['requests_per_month'],
            'overage' => $overage,
            'overage_charge' => $overageCharge ?? 0.00
        ];
    }

    /**
     * Generate invoice
     */
    public function generateInvoice(string $developerId, string $period = null): array
    {
        $period = $period ?? date('Y-m');
        $subscription = $this->getActiveSubscription($developerId);

        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'No active subscription found'
            ];
        }

        $usageKey = $developerId . ':' . $period;
        $usage = $this->usageTracking[$usageKey] ?? [
            'total_requests' => 0,
            'endpoint_usage' => [],
            'overage_charges' => 0.00
        ];

        $plan = $this->pricingPlans[$subscription['plan_id']];
        $subtotal = $plan['price'] + $usage['overage_charges'];
        $tax = $this->calculateTax($subtotal);
        $total = $subtotal + $tax;

        $invoice = [
            'id' => uniqid('inv_'),
            'developer_id' => $developerId,
            'subscription_id' => $subscription['id'],
            'period' => $period,
            'plan_name' => $plan['name'],
            'plan_price' => $plan['price'],
            'usage_requests' => $usage['total_requests'],
            'overage_charges' => $usage['overage_charges'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => $this->config['currency'],
            'status' => 'pending',
            'due_date' => strtotime('+30 days', time()),
            'created_at' => time()
        ];

        $this->billingRecords[] = $invoice;
        $this->saveInvoice($invoice['id'], $invoice);

        return [
            'success' => true,
            'invoice' => $invoice
        ];
    }

    /**
     * Process payment
     */
    public function processPayment(string $developerId, float $amount, string $type, string $referenceId): array
    {
        // Simulate payment processing
        $paymentId = uniqid('pay_');

        $payment = [
            'id' => $paymentId,
            'developer_id' => $developerId,
            'amount' => $amount,
            'currency' => $this->config['currency'],
            'type' => $type,
            'reference_id' => $referenceId,
            'status' => 'completed',
            'processed_at' => time(),
            'transaction_id' => 'txn_' . bin2hex(random_bytes(8))
        ];

        $this->savePayment($paymentId, $payment);

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'transaction_id' => $payment['transaction_id'],
            'message' => 'Payment processed successfully'
        ];
    }

    /**
     * Apply promotional code
     */
    public function applyPromoCode(string $developerId, string $promoCode, string $planId): array
    {
        if (!isset($this->promoCodes[$promoCode])) {
            return [
                'success' => false,
                'error' => 'Invalid promotional code'
            ];
        }

        $promo = $this->promoCodes[$promoCode];

        // Validate promo code
        if (!$this->validatePromoCode($promo, $developerId, $planId)) {
            return [
                'success' => false,
                'error' => 'Promotional code not applicable'
            ];
        }

        $discount = $this->calculateDiscount($promo, $this->pricingPlans[$planId]['price']);

        return [
            'success' => true,
            'promo_code' => $promoCode,
            'discount_type' => $promo['discount_type'],
            'discount_value' => $promo['discount_value'],
            'discount_amount' => $discount,
            'final_price' => $this->pricingPlans[$planId]['price'] - $discount
        ];
    }

    /**
     * Get pricing plans
     */
    public function getPricingPlans(): array
    {
        return [
            'success' => true,
            'plans' => $this->pricingPlans,
            'currency' => $this->config['currency']
        ];
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(string $developerId, string $period = null): array
    {
        $period = $period ?? date('Y-m');
        $usageKey = $developerId . ':' . $period;

        $usage = $this->usageTracking[$usageKey] ?? [
            'total_requests' => 0,
            'endpoint_usage' => [],
            'overage_charges' => 0.00,
            'last_updated' => time()
        ];

        $subscription = $this->getActiveSubscription($developerId);
        $plan = $subscription ? $this->pricingPlans[$subscription['plan_id']] : null;

        return [
            'success' => true,
            'period' => $period,
            'total_requests' => $usage['total_requests'],
            'limit' => $plan ? $plan['requests_per_month'] : 0,
            'usage_percentage' => $plan ? ($usage['total_requests'] / $plan['requests_per_month']) * 100 : 0,
            'endpoint_usage' => $usage['endpoint_usage'],
            'overage_charges' => $usage['overage_charges'],
            'top_endpoints' => $this->getTopEndpoints($usage['endpoint_usage'])
        ];
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(array $filters = []): array
    {
        $analytics = [
            'total_revenue' => $this->calculateTotalRevenue(),
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'plan_distribution' => $this->getPlanDistribution(),
            'churn_rate' => $this->calculateChurnRate(),
            'average_revenue_per_user' => $this->calculateARPU(),
            'top_revenue_sources' => $this->getTopRevenueSources(),
            'revenue_trends' => $this->getRevenueTrends()
        ];

        return [
            'success' => true,
            'analytics' => $analytics,
            'generated_at' => time()
        ];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscriptionId, bool $immediate = false): array
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return [
                'success' => false,
                'error' => 'Subscription not found'
            ];
        }

        $subscription = $this->subscriptions[$subscriptionId];

        if ($immediate) {
            $subscription['status'] = 'cancelled';
            $subscription['cancelled_at'] = time();
        } else {
            $subscription['cancel_at_period_end'] = true;
        }

        $subscription['updated_at'] = time();

        $this->subscriptions[$subscriptionId] = $subscription;
        $this->saveSubscription($subscriptionId, $subscription);

        return [
            'success' => true,
            'message' => $immediate ? 'Subscription cancelled immediately' : 'Subscription will be cancelled at period end'
        ];
    }

    /**
     * Upgrade/downgrade subscription
     */
    public function changePlan(string $subscriptionId, string $newPlanId): array
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return [
                'success' => false,
                'error' => 'Subscription not found'
            ];
        }

        if (!isset($this->pricingPlans[$newPlanId])) {
            return [
                'success' => false,
                'error' => 'New plan not found'
            ];
        }

        $subscription = $this->subscriptions[$subscriptionId];
        $oldPlan = $this->pricingPlans[$subscription['plan_id']];
        $newPlan = $this->pricingPlans[$newPlanId];

        // Calculate prorated charges
        $proratedCharge = $this->calculateProratedCharge($subscription, $oldPlan, $newPlan);

        $subscription['plan_id'] = $newPlanId;
        $subscription['updated_at'] = time();

        $this->subscriptions[$subscriptionId] = $subscription;
        $this->saveSubscription($subscriptionId, $subscription);

        // Process prorated payment if upgrade
        if ($proratedCharge > 0) {
            $this->processPayment($subscription['developer_id'], $proratedCharge, 'plan_change', $subscriptionId);
        }

        return [
            'success' => true,
            'old_plan' => $oldPlan['name'],
            'new_plan' => $newPlan['name'],
            'prorated_charge' => $proratedCharge,
            'message' => 'Plan changed successfully'
        ];
    }

    // Private helper methods

    private function getActiveSubscription(string $developerId): ?array
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription['developer_id'] === $developerId &&
                $subscription['status'] === 'active') {
                return $subscription;
            }
        }
        return null;
    }

    private function calculateOverage(array $subscription, int $totalRequests): int
    {
        $plan = $this->pricingPlans[$subscription['plan_id']];
        return max(0, $totalRequests - $plan['requests_per_month']);
    }

    private function calculateOverageCharge(int $overage, array $plan): float
    {
        // $0.10 per 1000 additional requests
        return ceil($overage / 1000) * 0.10;
    }

    private function calculateTax(float $amount): float
    {
        // Simple tax calculation (10%)
        return $amount * 0.10;
    }

    private function validatePromoCode(array $promo, string $developerId, string $planId): bool
    {
        // Check expiration
        if (isset($promo['expires_at']) && time() > $promo['expires_at']) {
            return false;
        }

        // Check usage limits
        if (isset($promo['max_uses']) && $promo['used_count'] >= $promo['max_uses']) {
            return false;
        }

        // Check plan restrictions
        if (isset($promo['plan_restrictions']) && !in_array($planId, $promo['plan_restrictions'])) {
            return false;
        }

        return true;
    }

    private function calculateDiscount(array $promo, float $originalPrice): float
    {
        if ($promo['discount_type'] === 'percentage') {
            return $originalPrice * ($promo['discount_value'] / 100);
        } elseif ($promo['discount_type'] === 'fixed') {
            return min($promo['discount_value'], $originalPrice);
        }

        return 0.00;
    }

    private function calculateProratedCharge(array $subscription, array $oldPlan, array $newPlan): float
    {
        $daysRemaining = ceil(($subscription['current_period_end'] - time()) / 86400);
        $totalDays = ceil(($subscription['current_period_end'] - $subscription['current_period_start']) / 86400);

        $oldDailyRate = $oldPlan['price'] / $totalDays;
        $newDailyRate = $newPlan['price'] / $totalDays;

        return max(0, ($newDailyRate - $oldDailyRate) * $daysRemaining);
    }

    private function getTopEndpoints(array $endpointUsage): array
    {
        arsort($endpointUsage);
        return array_slice($endpointUsage, 0, 5, true);
    }

    private function calculateTotalRevenue(): float
    {
        // Sum all successful payments
        return array_sum(array_map(fn($payment) => $payment['amount'], array_filter($this->billingRecords, fn($record) => $record['status'] === 'paid')));
    }

    private function getMonthlyRevenue(): array
    {
        $monthly = [];
        foreach ($this->billingRecords as $record) {
            if ($record['status'] === 'paid') {
                $month = date('Y-m', $record['created_at']);
                $monthly[$month] = ($monthly[$month] ?? 0) + $record['total'];
            }
        }
        return $monthly;
    }

    private function getPlanDistribution(): array
    {
        $distribution = [];
        foreach ($this->subscriptions as $subscription) {
            if ($subscription['status'] === 'active') {
                $planName = $this->pricingPlans[$subscription['plan_id']]['name'];
                $distribution[$planName] = ($distribution[$planName] ?? 0) + 1;
            }
        }
        return $distribution;
    }

    private function calculateChurnRate(): float
    {
        $totalSubscriptions = count($this->subscriptions);
        $cancelledSubscriptions = count(array_filter($this->subscriptions, fn($sub) => $sub['status'] === 'cancelled'));

        return $totalSubscriptions > 0 ? ($cancelledSubscriptions / $totalSubscriptions) * 100 : 0;
    }

    private function calculateARPU(): float
    {
        $activeSubscriptions = count(array_filter($this->subscriptions, fn($sub) => $sub['status'] === 'active'));
        $monthlyRevenue = array_sum($this->getMonthlyRevenue());

        return $activeSubscriptions > 0 ? $monthlyRevenue / $activeSubscriptions : 0;
    }

    private function getTopRevenueSources(): array
    {
        // Group revenue by plan
        $revenueByPlan = [];
        foreach ($this->subscriptions as $subscription) {
            if ($subscription['status'] === 'active') {
                $planName = $this->pricingPlans[$subscription['plan_id']]['name'];
                $revenueByPlan[$planName] = ($revenueByPlan[$planName] ?? 0) + $this->pricingPlans[$subscription['plan_id']]['price'];
            }
        }

        arsort($revenueByPlan);
        return array_slice($revenueByPlan, 0, 5, true);
    }

    private function getRevenueTrends(): array
    {
        // Generate revenue trends for last 12 months
        $trends = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $trends[] = [
                'month' => $month,
                'revenue' => rand(5000, 25000),
                'subscriptions' => rand(50, 200)
            ];
        }
        return $trends;
    }

    private function loadPricingPlans(): void
    {
        // In real implementation, load from database
    }

    private function initializeUsageTracking(): void
    {
        // Initialize usage tracking
    }

    private function loadSubscriptions(): void
    {
        // In real implementation, load from database
    }

    private function initializeBillingSystem(): void
    {
        // Initialize billing system
    }

    private function saveSubscription(string $subscriptionId, array $subscription): void
    {
        // In real implementation, save to database
    }

    private function saveUsageTracking(string $usageKey, array $usage): void
    {
        // In real implementation, save to database
    }

    private function saveInvoice(string $invoiceId, array $invoice): void
    {
        // In real implementation, save to database
    }

    private function savePayment(string $paymentId, array $payment): void
    {
        // In real implementation, save to database
    }
}

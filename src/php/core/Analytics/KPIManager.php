<?php
/**
 * TPT Government Platform - KPI Manager
 *
 * Manages Key Performance Indicators tracking and analysis.
 */

namespace Core\Analytics;

class KPIManager
{
    private array $kpis;
    private array $kpiDefinitions;
    private array $alerts;

    public function __construct()
    {
        $this->kpis = [];
        $this->alerts = [];
        $this->initializeKPIDefinitions();
    }

    /**
     * Initialize KPI definitions
     */
    private function initializeKPIDefinitions(): void
    {
        $this->kpiDefinitions = [
            'user_engagement' => [
                'name' => 'User Engagement',
                'description' => 'Measure of citizen interaction with government services',
                'calculation' => 'avg_session_duration * page_views_per_session',
                'target' => 15.0,
                'unit' => 'engagement_score',
                'thresholds' => ['warning' => 12.0, 'critical' => 10.0]
            ],
            'service_efficiency' => [
                'name' => 'Service Efficiency',
                'description' => 'Average time to complete service requests',
                'calculation' => 'avg_completion_time',
                'target' => 2.0,
                'unit' => 'days',
                'thresholds' => ['warning' => 3.0, 'critical' => 5.0]
            ],
            'citizen_satisfaction' => [
                'name' => 'Citizen Satisfaction',
                'description' => 'Average satisfaction rating from service interactions',
                'calculation' => 'avg_satisfaction_score',
                'target' => 4.5,
                'unit' => 'rating',
                'thresholds' => ['warning' => 4.0, 'critical' => 3.5]
            ],
            'digital_adoption' => [
                'name' => 'Digital Adoption Rate',
                'description' => 'Percentage of services accessed digitally',
                'calculation' => '(digital_requests / total_requests) * 100',
                'target' => 85.0,
                'unit' => 'percentage',
                'thresholds' => ['warning' => 70.0, 'critical' => 60.0]
            ],
            'cost_per_transaction' => [
                'name' => 'Cost Per Transaction',
                'description' => 'Average cost to process a service transaction',
                'calculation' => 'total_operational_cost / transaction_count',
                'target' => 5.0,
                'unit' => 'usd',
                'thresholds' => ['warning' => 7.0, 'critical' => 10.0]
            ],
            'error_rate' => [
                'name' => 'System Error Rate',
                'description' => 'Percentage of failed service requests',
                'calculation' => '(failed_requests / total_requests) * 100',
                'target' => 2.0,
                'unit' => 'percentage',
                'thresholds' => ['warning' => 5.0, 'critical' => 10.0]
            ],
            'response_time' => [
                'name' => 'Average Response Time',
                'description' => 'Average time for system responses',
                'calculation' => 'avg_response_time',
                'target' => 1.5,
                'unit' => 'seconds',
                'thresholds' => ['warning' => 2.0, 'critical' => 3.0]
            ],
            'revenue_per_citizen' => [
                'name' => 'Revenue Per Citizen',
                'description' => 'Average revenue generated per citizen',
                'calculation' => 'total_revenue / unique_citizens',
                'target' => 150.0,
                'unit' => 'usd',
                'thresholds' => ['warning' => 120.0, 'critical' => 100.0]
            ],
            'service_completion_rate' => [
                'name' => 'Service Completion Rate',
                'description' => 'Percentage of service requests completed successfully',
                'calculation' => '(completed_requests / total_requests) * 100',
                'target' => 95.0,
                'unit' => 'percentage',
                'thresholds' => ['warning' => 90.0, 'critical' => 85.0]
            ],
            'accessibility_score' => [
                'name' => 'Accessibility Score',
                'description' => 'Measure of system accessibility compliance',
                'calculation' => 'avg_accessibility_rating',
                'target' => 95.0,
                'unit' => 'percentage',
                'thresholds' => ['warning' => 90.0, 'critical' => 85.0]
            ]
        ];
    }

    /**
     * Register a new KPI
     */
    public function registerKPI(string $kpiId, array $definition): void
    {
        $this->kpiDefinitions[$kpiId] = array_merge([
            'name' => $kpiId,
            'description' => '',
            'calculation' => '',
            'target' => 0.0,
            'unit' => '',
            'thresholds' => ['warning' => null, 'critical' => null]
        ], $definition);

        $this->kpis[$kpiId] = [
            'definition' => $this->kpiDefinitions[$kpiId],
            'current_value' => 0.0,
            'target_value' => $definition['target'],
            'trend' => [],
            'last_updated' => time(),
            'status' => 'initialized'
        ];
    }

    /**
     * Update KPI value
     */
    public function updateKPI(string $kpiId, float $value): bool
    {
        if (!isset($this->kpis[$kpiId])) {
            return false;
        }

        $this->kpis[$kpiId]['current_value'] = $value;
        $this->kpis[$kpiId]['last_updated'] = time();

        // Add to trend data
        $this->kpis[$kpiId]['trend'][] = [
            'value' => $value,
            'timestamp' => time()
        ];

        // Keep only recent trend data (last 30 days)
        if (count($this->kpis[$kpiId]['trend']) > 720) { // 30 days * 24 hours
            array_shift($this->kpis[$kpiId]['trend']);
        }

        // Update status
        $this->kpis[$kpiId]['status'] = $this->calculateKPIStatus($kpiId, $value);

        // Check for alerts
        $this->checkKPIAlerts($kpiId, $value);

        return true;
    }

    /**
     * Get KPI data
     */
    public function getKPI(string $kpiId): array
    {
        return $this->kpis[$kpiId] ?? [];
    }

    /**
     * Get all KPIs
     */
    public function getAllKPIs(): array
    {
        return $this->kpis;
    }

    /**
     * Get KPIs by status
     */
    public function getKPIsByStatus(string $status): array
    {
        return array_filter($this->kpis, fn($kpi) => $kpi['status'] === $status);
    }

    /**
     * Calculate KPI performance status
     */
    private function calculateKPIStatus(string $kpiId, float $value): string
    {
        $definition = $this->kpiDefinitions[$kpiId];
        $target = $definition['target'];
        $thresholds = $definition['thresholds'];

        // Determine if higher or lower values are better
        $isHigherBetter = in_array($definition['unit'], ['percentage', 'rating', 'engagement_score', 'usd']);

        if ($isHigherBetter) {
            if ($value >= $target) {
                return 'excellent';
            } elseif ($value >= $thresholds['warning']) {
                return 'good';
            } elseif ($value >= $thresholds['critical']) {
                return 'warning';
            } else {
                return 'critical';
            }
        } else {
            if ($value <= $target) {
                return 'excellent';
            } elseif ($value <= $thresholds['warning']) {
                return 'good';
            } elseif ($value <= $thresholds['critical']) {
                return 'warning';
            } else {
                return 'critical';
            }
        }
    }

    /**
     * Check for KPI alerts
     */
    private function checkKPIAlerts(string $kpiId, float $value): void
    {
        $definition = $this->kpiDefinitions[$kpiId];
        $thresholds = $definition['thresholds'];

        $alertTriggered = false;
        $alertLevel = '';

        // Determine if higher or lower values are better
        $isHigherBetter = in_array($definition['unit'], ['percentage', 'rating', 'engagement_score', 'usd']);

        if ($isHigherBetter) {
            if ($value <= $thresholds['critical']) {
                $alertTriggered = true;
                $alertLevel = 'critical';
            } elseif ($value <= $thresholds['warning']) {
                $alertTriggered = true;
                $alertLevel = 'warning';
            }
        } else {
            if ($value >= $thresholds['critical']) {
                $alertTriggered = true;
                $alertLevel = 'critical';
            } elseif ($value >= $thresholds['warning']) {
                $alertTriggered = true;
                $alertLevel = 'warning';
            }
        }

        if ($alertTriggered) {
            $this->alerts[] = [
                'kpi_id' => $kpiId,
                'kpi_name' => $definition['name'],
                'level' => $alertLevel,
                'current_value' => $value,
                'target_value' => $definition['target'],
                'threshold' => $thresholds[$alertLevel],
                'timestamp' => time(),
                'message' => $this->generateAlertMessage($kpiId, $alertLevel, $value)
            ];
        }
    }

    /**
     * Generate alert message
     */
    private function generateAlertMessage(string $kpiId, string $level, float $value): string
    {
        $definition = $this->kpiDefinitions[$kpiId];
        $target = $definition['target'];

        if ($value < $target) {
            return "KPI '{$definition['name']}' is below target. Current: {$value}, Target: {$target}";
        } else {
            return "KPI '{$definition['name']}' has exceeded acceptable limits. Current: {$value}, Target: {$target}";
        }
    }

    /**
     * Get KPI alerts
     */
    public function getAlerts(array $filters = []): array
    {
        $filteredAlerts = $this->alerts;

        if (isset($filters['level'])) {
            $filteredAlerts = array_filter($filteredAlerts, fn($alert) => $alert['level'] === $filters['level']);
        }

        if (isset($filters['kpi_id'])) {
            $filteredAlerts = array_filter($filteredAlerts, fn($alert) => $alert['kpi_id'] === $filters['kpi_id']);
        }

        if (isset($filters['since'])) {
            $filteredAlerts = array_filter($filteredAlerts, fn($alert) => $alert['timestamp'] >= $filters['since']);
        }

        return array_values($filteredAlerts);
    }

    /**
     * Calculate KPI trend
     */
    public function calculateTrend(string $kpiId, int $period = 7): array
    {
        if (!isset($this->kpis[$kpiId])) {
            return ['error' => 'KPI not found'];
        }

        $trend = $this->kpis[$kpiId]['trend'];

        if (count($trend) < 2) {
            return ['direction' => 'insufficient_data', 'change' => 0, 'percentage' => 0];
        }

        // Get data for the specified period
        $recentData = array_slice($trend, -$period);
        $values = array_column($recentData, 'value');

        $firstValue = $values[0];
        $lastValue = end($values);
        $change = $lastValue - $firstValue;
        $percentage = $firstValue != 0 ? ($change / $firstValue) * 100 : 0;

        $direction = 'stable';
        if ($percentage > 5) {
            $direction = 'increasing';
        } elseif ($percentage < -5) {
            $direction = 'decreasing';
        }

        return [
            'direction' => $direction,
            'change' => $change,
            'percentage' => round($percentage, 2),
            'period_days' => $period,
            'data_points' => count($values)
        ];
    }

    /**
     * Get KPI summary report
     */
    public function getKPISummary(): array
    {
        $summary = [
            'total_kpis' => count($this->kpis),
            'status_breakdown' => [
                'excellent' => 0,
                'good' => 0,
                'warning' => 0,
                'critical' => 0
            ],
            'alerts_count' => count($this->alerts),
            'top_performers' => [],
            'needs_attention' => []
        ];

        foreach ($this->kpis as $kpiId => $kpi) {
            $status = $kpi['status'];
            $summary['status_breakdown'][$status]++;

            if ($status === 'excellent') {
                $summary['top_performers'][] = $kpiId;
            } elseif (in_array($status, ['warning', 'critical'])) {
                $summary['needs_attention'][] = $kpiId;
            }
        }

        return $summary;
    }

    /**
     * Export KPI data
     */
    public function exportKPIs(string $format = 'json'): string
    {
        $data = [
            'kpis' => $this->kpis,
            'definitions' => $this->kpiDefinitions,
            'alerts' => $this->alerts,
            'exported_at' => time()
        ];

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV($data);
            default:
                return json_encode($data);
        }
    }

    /**
     * Export to CSV format
     */
    private function exportToCSV(array $data): string
    {
        $csv = "KPI ID,Name,Current Value,Target,Status,Last Updated\n";

        foreach ($data['kpis'] as $kpiId => $kpi) {
            $csv .= sprintf(
                "%s,%s,%.2f,%.2f,%s,%s\n",
                $kpiId,
                $kpi['definition']['name'],
                $kpi['current_value'],
                $kpi['target_value'],
                $kpi['status'],
                date('Y-m-d H:i:s', $kpi['last_updated'])
            );
        }

        return $csv;
    }
}

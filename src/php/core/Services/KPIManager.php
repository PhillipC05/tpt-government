<?php
/**
 * KPI Management Service
 */

namespace Core\Services;

use Core\Database;
use Core\Config\KPIDefinitions;

class KPIManager
{
    private Database $database;
    private array $kpis;
    private array $kpiDefinitions;

    public function __construct()
    {
        $this->database = new Database();
        $this->kpis = [];
        $this->kpiDefinitions = KPIDefinitions::getDefinitions();
        $this->initializeKPIs();
    }

    /**
     * Initialize KPIs
     */
    private function initializeKPIs(): void
    {
        foreach ($this->kpiDefinitions as $kpiId => $definition) {
            $this->kpis[$kpiId] = [
                'definition' => $definition,
                'current_value' => 0.0,
                'target_value' => $definition['target'],
                'trend' => [],
                'last_updated' => time()
            ];
        }

        $this->loadHistoricalKPIs();
    }

    /**
     * Update KPI value
     */
    public function updateKPI(string $kpiId, float $value): void
    {
        if (!isset($this->kpis[$kpiId])) {
            return;
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

        // Check for KPI alerts
        $this->checkKPIAlerts($kpiId, $value);

        // Store KPI data
        $this->storeKPIHistory($kpiId, $value);
    }

    /**
     * Get KPI value
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
     * Get KPI summary
     */
    public function getKPISummary(): array
    {
        $summary = [];

        foreach ($this->kpis as $kpiId => $kpi) {
            $summary[$kpiId] = [
                'name' => $kpi['definition']['name'],
                'current_value' => $kpi['current_value'],
                'target_value' => $kpi['target_value'],
                'performance' => $this->calculatePerformance($kpi),
                'trend' => $this->getTrendDirection($kpi['trend'])
            ];
        }

        return $summary;
    }

    /**
     * Load historical KPI data
     */
    private function loadHistoricalKPIs(): void
    {
        // Implementation for loading historical data
    }

    /**
     * Check for KPI alerts
     */
    private function checkKPIAlerts(string $kpiId, float $value): void
    {
        $kpi = $this->kpis[$kpiId];
        $target = $kpi['target_value'];

        if ($value < $target * 0.8) {
            // Trigger alert for significant underperformance
            $this->triggerKPIAlert($kpiId, 'critical', 'KPI significantly below target');
        } elseif ($value < $target * 0.9) {
            // Trigger warning for moderate underperformance
            $this->triggerKPIAlert($kpiId, 'warning', 'KPI below target');
        }
    }

    /**
     * Store KPI history
     */
    private function storeKPIHistory(string $kpiId, float $value): void
    {
        // Implementation for storing KPI history
    }

    /**
     * Calculate performance rating
     */
    private function calculatePerformance(array $kpi): string
    {
        $current = $kpi['current_value'];
        $target = $kpi['target_value'];

        if ($current >= $target * 1.1) {
            return 'excellent';
        } elseif ($current >= $target) {
            return 'good';
        } elseif ($current >= $target * 0.9) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get trend direction
     */
    private function getTrendDirection(array $trend): string
    {
        if (count($trend) < 2) {
            return 'stable';
        }

        $recent = array_slice($trend, -10); // Last 10 data points
        $values = array_column($recent, 'value');

        $first = $values[0];
        $last = end($values);
        $change = ($last - $first) / $first;

        if ($change > 0.05) {
            return 'improving';
        } elseif ($change < -0.05) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Trigger KPI alert
     */
    private function triggerKPIAlert(string $kpiId, string $severity, string $message): void
    {
        // Implementation for triggering alerts
    }
}

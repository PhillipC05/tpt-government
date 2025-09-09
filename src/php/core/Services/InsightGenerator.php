<?php
/**
 * Automated Insights Generator Service
 */

namespace Core\Services;

use Core\Database;

class InsightGenerator
{
    private Database $database;
    private array $insights;

    public function __construct()
    {
        $this->database = new Database();
        $this->insights = [];
    }

    /**
     * Generate automated insights
     */
    public function generateInsights(string $insightType = null): array
    {
        $insights = [];

        if ($insightType === null || $insightType === 'trend_analysis') {
            $insights = array_merge($insights, $this->generateTrendInsights());
        }

        if ($insightType === null || $insightType === 'anomaly_detection') {
            $insights = array_merge($insights, $this->generateAnomalyInsights());
        }

        if ($insightType === null || $insightType === 'correlation_discovery') {
            $insights = array_merge($insights, $this->generateCorrelationInsights());
        }

        if ($insightType === null || $insightType === 'predictive_alerts') {
            $insights = array_merge($insights, $this->generatePredictiveAlerts());
        }

        if ($insightType === null || $insightType === 'optimization_recommendations') {
            $insights = array_merge($insights, $this->generateOptimizationRecommendations());
        }

        // Store insights
        $this->storeInsights($insights);

        return $insights;
    }

    /**
     * Generate trend insights
     */
    private function generateTrendInsights(): array
    {
        $insights = [];

        // Get KPI data from database
        $kpis = $this->getKPIData();

        foreach ($kpis as $kpiId => $kpiData) {
            $trend = $this->analyzeTrend($kpiData['trend']);

            if ($trend['direction'] === 'improving' && $trend['significance'] > 0.7) {
                $insights[] = [
                    'type' => 'trend',
                    'title' => "Improving {$kpiData['name']}",
                    'description' => "{$kpiData['name']} has been improving steadily",
                    'metric' => $kpiId,
                    'trend' => $trend,
                    'recommendation' => 'Continue current strategies',
                    'severity' => 'info',
                    'generated_at' => time()
                ];
            } elseif ($trend['direction'] === 'declining' && $trend['significance'] > 0.7) {
                $insights[] = [
                    'type' => 'trend',
                    'title' => "Declining {$kpiData['name']}",
                    'description' => "{$kpiData['name']} is showing a declining trend",
                    'metric' => $kpiId,
                    'trend' => $trend,
                    'recommendation' => 'Investigate causes and implement corrective actions',
                    'severity' => 'warning',
                    'generated_at' => time()
                ];
            }
        }

        return $insights;
    }

    /**
     * Generate anomaly insights
     */
    private function generateAnomalyInsights(): array
    {
        $insights = [];

        // Get KPI data from database
        $kpis = $this->getKPIData();

        foreach ($kpis as $kpiId => $kpiData) {
            $anomaly = $this->detectAnomaly($kpiData);

            if ($anomaly['is_anomaly']) {
                $insights[] = [
                    'type' => 'anomaly',
                    'title' => "Anomaly Detected in {$kpiData['name']}",
                    'description' => "{$kpiData['name']} shows unusual behavior",
                    'metric' => $kpiId,
                    'anomaly' => $anomaly,
                    'severity' => $anomaly['severity'],
                    'recommendation' => 'Investigate the cause of this anomaly',
                    'generated_at' => time()
                ];
            }
        }

        return $insights;
    }

    /**
     * Generate correlation insights
     */
    private function generateCorrelationInsights(): array
    {
        $insights = [];

        // Analyze correlations between KPIs
        $correlations = $this->analyzeCorrelations();

        foreach ($correlations as $correlation) {
            if (abs($correlation['coefficient']) > 0.7) {
                $insights[] = [
                    'type' => 'correlation',
                    'title' => "Strong Correlation Detected",
                    'description' => "{$correlation['metric1']} and {$correlation['metric2']} show strong correlation",
                    'correlation' => $correlation,
                    'recommendation' => 'Monitor these metrics together for better insights',
                    'severity' => 'info',
                    'generated_at' => time()
                ];
            }
        }

        return $insights;
    }

    /**
     * Generate predictive alerts
     */
    private function generatePredictiveAlerts(): array
    {
        $alerts = [];

        // Get KPI data from database
        $kpis = $this->getKPIData();

        foreach ($kpis as $kpiId => $kpiData) {
            $prediction = $this->predictKPIValue($kpiId, 30); // 30 days ahead

            if ($prediction['value'] < $kpiData['target'] * 0.9) {
                $alerts[] = [
                    'type' => 'predictive_alert',
                    'title' => "Predicted KPI Decline",
                    'description' => "{$kpiData['name']} is predicted to fall below target",
                    'metric' => $kpiId,
                    'prediction' => $prediction,
                    'severity' => 'high',
                    'recommendation' => 'Take preventive actions to avoid target miss',
                    'generated_at' => time()
                ];
            }
        }

        return $alerts;
    }

    /**
     * Generate optimization recommendations
     */
    private function generateOptimizationRecommendations(): array
    {
        $recommendations = [];

        // Analyze system performance
        $performanceAnalysis = $this->analyzeSystemPerformance();

        if ($performanceAnalysis['bottleneck'] === 'database') {
            $recommendations[] = [
                'type' => 'optimization',
                'title' => 'Database Performance Optimization',
                'description' => 'Database queries are causing performance bottlenecks',
                'recommendation' => 'Implement query optimization and add database indexes',
                'estimated_impact' => 'high',
                'severity' => 'medium',
                'generated_at' => time()
            ];
        }

        if ($performanceAnalysis['bottleneck'] === 'cache') {
            $recommendations[] = [
                'type' => 'optimization',
                'title' => 'Cache Optimization',
                'description' => 'Cache hit rate is below optimal levels',
                'recommendation' => 'Increase cache TTL and implement better cache warming strategies',
                'estimated_impact' => 'medium',
                'severity' => 'low',
                'generated_at' => time()
            ];
        }

        return $recommendations;
    }

    /**
     * Get recent insights
     */
    public function getRecentInsights(int $limit = 50): array
    {
        // Return most recent insights from database
        return array_slice(array_reverse($this->insights), 0, $limit);
    }

    /**
     * Get insights by type
     */
    public function getInsightsByType(string $type): array
    {
        return array_filter($this->insights, function($insight) use ($type) {
            return $insight['type'] === $type;
        });
    }

    /**
     * Get insights by severity
     */
    public function getInsightsBySeverity(string $severity): array
    {
        return array_filter($this->insights, function($insight) use ($severity) {
            return $insight['severity'] === $severity;
        });
    }

    /**
     * Store insights
     */
    private function storeInsights(array $insights): void
    {
        foreach ($insights as $insight) {
            $this->insights[] = $insight;
        }

        // Keep only recent insights (last 1000)
        if (count($this->insights) > 1000) {
            $this->insights = array_slice($this->insights, -1000);
        }
    }

    // Helper methods (implementations would be more complex in production)

    private function getKPIData(): array {/* Implementation */}
    private function analyzeTrend(array $trend): array {/* Implementation */}
    private function detectAnomaly(array $kpi): array {/* Implementation */}
    private function analyzeCorrelations(): array {/* Implementation */}
    private function predictKPIValue(string $kpiId, int $days): array {/* Implementation */}
    private function analyzeSystemPerformance(): array {/* Implementation */}
}

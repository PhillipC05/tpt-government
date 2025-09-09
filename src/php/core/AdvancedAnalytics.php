<?php
/**
 * TPT Government Platform - Advanced Analytics & Machine Learning System
 *
 * Refactored analytics framework using separate service components
 */

use Core\Config\AnalyticsConfig;
use Core\Services\KPIManager;
use Core\Services\PredictiveAnalyticsService;
use Core\Services\InsightGenerator;
use Core\Services\UserBehaviorTracker;

class AdvancedAnalytics
{
    private array $config;
    private KPIManager $kpiManager;
    private PredictiveAnalyticsService $predictiveService;
    private InsightGenerator $insightGenerator;
    private UserBehaviorTracker $behaviorTracker;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(AnalyticsConfig::getConfig(), $config);

        // Initialize service components
        $this->kpiManager = new KPIManager();
        $this->predictiveService = new PredictiveAnalyticsService();
        $this->insightGenerator = new InsightGenerator();
        $this->behaviorTracker = new UserBehaviorTracker();
    }

    /**
     * Track user event
     */
    public function trackEvent(string $eventType, array $eventData, int $userId = null): void
    {
        $this->behaviorTracker->trackEvent($eventType, $eventData, $userId);
    }

    /**
     * Update KPI value
     */
    public function updateKPI(string $kpiId, float $value): void
    {
        $this->kpiManager->updateKPI($kpiId, $value);
    }

    /**
     * Get KPI value
     */
    public function getKPI(string $kpiId): array
    {
        return $this->kpiManager->getKPI($kpiId);
    }

    /**
     * Get all KPIs
     */
    public function getAllKPIs(): array
    {
        return $this->kpiManager->getAllKPIs();
    }

    /**
     * Generate service demand prediction
     */
    public function predictServiceDemand(array $parameters = []): array
    {
        return $this->predictiveService->predictServiceDemand($parameters);
    }

    /**
     * Detect potential fraud
     */
    public function detectFraud(array $transactionData): array
    {
        return $this->predictiveService->detectFraud($transactionData);
    }

    /**
     * Predict citizen satisfaction
     */
    public function predictCitizenSatisfaction(array $interactionData): array
    {
        return $this->predictiveService->predictCitizenSatisfaction($interactionData);
    }

    /**
     * Optimize service allocation
     */
    public function optimizeServiceAllocation(array $serviceData): array
    {
        return $this->predictiveService->optimizeServiceAllocation($serviceData);
    }

    /**
     * Generate automated insights
     */
    public function generateInsights(string $insightType = null): array
    {
        return $this->insightGenerator->generateInsights($insightType);
    }

    /**
     * Get analytics dashboard data
     */
    public function getDashboardData(): array
    {
        return [
            'kpis' => $this->kpiManager->getKPISummary(),
            'insights' => $this->insightGenerator->getRecentInsights(),
            'predictions' => $this->predictiveService->getAllPredictions('demand_prediction'),
            'user_segments' => $this->behaviorTracker->segmentUsers()
        ];
    }

    /**
     * Generate analytics report
     */
    public function generateReport(string $reportType, array $parameters = []): array
    {
        $report = [
            'type' => $reportType,
            'generated_at' => time(),
            'parameters' => $parameters,
            'data' => []
        ];

        switch ($reportType) {
            case 'executive_summary':
                $report['data'] = $this->generateExecutiveSummary();
                break;
            case 'performance_analysis':
                $report['data'] = $this->generatePerformanceAnalysis($parameters);
                break;
            case 'trend_report':
                $report['data'] = $this->generateTrendReport($parameters);
                break;
            case 'forecast_report':
                $report['data'] = $this->generateForecastReport($parameters);
                break;
            default:
                $report['data'] = ['error' => 'Unknown report type'];
        }

        return $report;
    }

    /**
     * Export analytics data
     */
    public function exportData(string $format = 'json', array $filters = []): string
    {
        $data = [
            'kpis' => $this->kpiManager->getAllKPIs(),
            'insights' => $this->insightGenerator->getRecentInsights(),
            'predictions' => $this->predictiveService->getAllPredictions('demand_prediction'),
            'user_behavior' => $this->behaviorTracker->exportBehaviorData($filters),
            'exported_at' => time(),
            'filters' => $filters
        ];

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV($data);
            case 'xml':
                return $this->exportToXML($data);
            default:
                return json_encode($data);
        }
    }

    /**
     * Segment users based on behavior
     */
    public function segmentUsers(): array
    {
        return $this->behaviorTracker->segmentUsers();
    }

    /**
     * Get user behavior profile
     */
    public function getUserBehaviorProfile(int $userId): array
    {
        return $this->behaviorTracker->getUserBehaviorProfile($userId);
    }

    /**
     * Get model performance metrics
     */
    public function getModelPerformance(string $modelType): array
    {
        return $this->predictiveService->getModelPerformance($modelType);
    }

    /**
     * Get behavioral insights
     */
    public function getBehavioralInsights(): array
    {
        return $this->behaviorTracker->getBehavioralInsights();
    }

    // Private helper methods

    private function generateExecutiveSummary(): array
    {
        return [
            'kpi_summary' => $this->kpiManager->getKPISummary(),
            'recent_insights' => $this->insightGenerator->getRecentInsights(5),
            'user_segments' => $this->behaviorTracker->segmentUsers(),
            'generated_at' => time()
        ];
    }

    private function generatePerformanceAnalysis(array $params): array
    {
        return [
            'kpi_performance' => $this->kpiManager->getKPISummary(),
            'model_performance' => $this->predictiveService->getModelPerformance('demand_prediction'),
            'system_insights' => $this->insightGenerator->getInsightsByType('optimization'),
            'period' => $params['period'] ?? 'monthly'
        ];
    }

    private function generateTrendReport(array $params): array
    {
        return [
            'trend_insights' => $this->insightGenerator->getInsightsByType('trend'),
            'kpi_trends' => $this->kpiManager->getKPISummary(),
            'period' => $params['period'] ?? 'weekly'
        ];
    }

    private function generateForecastReport(array $params): array
    {
        return [
            'forecast_insights' => $this->insightGenerator->getInsightsByType('predictive_alert'),
            'demand_predictions' => $this->predictiveService->getAllPredictions('demand_prediction'),
            'forecast_period' => $params['days'] ?? 30
        ];
    }

    private function exportToCSV(array $data): string
    {
        // Simple CSV export implementation
        $output = "Key,Value\n";

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $output .= $key . "," . json_encode($value) . "\n";
            } else {
                $output .= $key . "," . $value . "\n";
            }
        }

        return $output;
    }

    private function exportToXML(array $data): string
    {
        // Simple XML export implementation
        $xml = new SimpleXMLElement('<analytics/>');

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml->asXML();
    }

    private function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}

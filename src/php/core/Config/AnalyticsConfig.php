<?php
/**
 * Advanced Analytics Configuration
 */

namespace Core\Config;

class AnalyticsConfig
{
    /**
     * Get analytics configuration
     */
    public static function getConfig(): array
    {
        return [
            'kpi_tracking' => [
                'enabled' => true,
                'update_frequency' => 'hourly',
                'retention_period' => 365, // days
                'alert_thresholds' => [
                    'response_time' => 2.0, // seconds
                    'error_rate' => 5.0,    // percentage
                    'user_satisfaction' => 4.0 // out of 5
                ]
            ],
            'predictive_modeling' => [
                'enabled' => true,
                'models' => [
                    'demand_prediction',
                    'fraud_detection',
                    'citizen_satisfaction',
                    'service_optimization',
                    'resource_allocation'
                ],
                'training_frequency' => 'daily',
                'accuracy_threshold' => 0.85
            ],
            'citizen_behavior' => [
                'enabled' => true,
                'tracking_events' => [
                    'page_views',
                    'service_requests',
                    'payment_transactions',
                    'feedback_submissions',
                    'support_interactions'
                ],
                'segmentation_rules' => [
                    'high_value' => 'transaction_volume > 1000',
                    'frequent_user' => 'monthly_requests > 10',
                    'satisfied_customer' => 'satisfaction_score > 4.5'
                ]
            ],
            'automated_insights' => [
                'enabled' => true,
                'generation_frequency' => 'daily',
                'insight_types' => [
                    'trend_analysis',
                    'anomaly_detection',
                    'correlation_discovery',
                    'predictive_alerts',
                    'optimization_recommendations'
                ]
            ],
            'reporting' => [
                'enabled' => true,
                'formats' => ['dashboard', 'pdf', 'excel', 'api'],
                'schedules' => [
                    'daily' => ['executive_summary', 'kpi_report'],
                    'weekly' => ['performance_analysis', 'trend_report'],
                    'monthly' => ['comprehensive_review', 'forecast_report']
                ]
            ]
        ];
    }
}

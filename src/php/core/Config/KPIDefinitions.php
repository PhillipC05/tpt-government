<?php
/**
 * Key Performance Indicators Definitions
 */

namespace Core\Config;

class KPIDefinitions
{
    /**
     * Get KPI definitions
     */
    public static function getDefinitions(): array
    {
        return [
            'user_engagement' => [
                'name' => 'User Engagement',
                'description' => 'Measure of citizen interaction with government services',
                'calculation' => 'avg_session_duration * page_views_per_session',
                'target' => 15.0,
                'unit' => 'engagement_score'
            ],
            'service_efficiency' => [
                'name' => 'Service Efficiency',
                'description' => 'Average time to complete service requests',
                'calculation' => 'avg_completion_time',
                'target' => 2.0,
                'unit' => 'days'
            ],
            'citizen_satisfaction' => [
                'name' => 'Citizen Satisfaction',
                'description' => 'Average satisfaction rating from service interactions',
                'calculation' => 'avg_satisfaction_score',
                'target' => 4.5,
                'unit' => 'rating'
            ],
            'digital_adoption' => [
                'name' => 'Digital Adoption Rate',
                'description' => 'Percentage of services accessed digitally',
                'calculation' => '(digital_requests / total_requests) * 100',
                'target' => 85.0,
                'unit' => 'percentage'
            ],
            'cost_per_transaction' => [
                'name' => 'Cost Per Transaction',
                'description' => 'Average cost to process a service transaction',
                'calculation' => 'total_operational_cost / transaction_count',
                'target' => 5.0,
                'unit' => 'usd'
            ],
            'error_rate' => [
                'name' => 'System Error Rate',
                'description' => 'Percentage of failed service requests',
                'calculation' => '(failed_requests / total_requests) * 100',
                'target' => 2.0,
                'unit' => 'percentage'
            ],
            'response_time' => [
                'name' => 'Average Response Time',
                'description' => 'Average time for system responses',
                'calculation' => 'avg_response_time',
                'target' => 1.5,
                'unit' => 'seconds'
            ],
            'revenue_per_citizen' => [
                'name' => 'Revenue Per Citizen',
                'description' => 'Average revenue generated per citizen',
                'calculation' => 'total_revenue / unique_citizens',
                'target' => 150.0,
                'unit' => 'usd'
            ],
            'service_completion_rate' => [
                'name' => 'Service Completion Rate',
                'description' => 'Percentage of service requests completed successfully',
                'calculation' => '(completed_requests / total_requests) * 100',
                'target' => 95.0,
                'unit' => 'percentage'
            ],
            'accessibility_score' => [
                'name' => 'Accessibility Score',
                'description' => 'Measure of system accessibility compliance',
                'calculation' => 'avg_accessibility_rating',
                'target' => 95.0,
                'unit' => 'percentage'
            ]
        ];
    }
}

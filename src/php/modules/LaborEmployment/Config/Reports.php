<?php
/**
 * Labor & Employment Module - Reports Configuration
 */

namespace Modules\LaborEmployment\Config;

class Reports
{
    /**
     * Get reports configuration
     */
    public static function getReports(): array
    {
        return [
            'employment_overview' => [
                'name' => 'Employment Overview Report',
                'description' => 'Summary of job market and employment statistics',
                'parameters' => [
                    'date_range' => ['type' => 'date_range', 'required' => false],
                    'industry' => ['type' => 'select', 'required' => false],
                    'region' => ['type' => 'select', 'required' => false]
                ],
                'columns' => [
                    'total_job_postings', 'total_applications', 'placement_rate',
                    'average_salary', 'top_industries', 'unemployment_trends'
                ]
            ],
            'training_programs_report' => [
                'name' => 'Training Programs Report',
                'description' => 'Analysis of training program effectiveness and participation',
                'parameters' => [
                    'date_range' => ['type' => 'date_range', 'required' => false],
                    'program_type' => ['type' => 'select', 'required' => false]
                ],
                'columns' => [
                    'program_name', 'participants', 'completion_rate',
                    'employment_rate', 'cost_per_participant', 'roi_analysis'
                ]
            ],
            'labor_compliance_report' => [
                'name' => 'Labor Compliance Report',
                'description' => 'Monitoring of labor law compliance and violations',
                'parameters' => [
                    'date_range' => ['type' => 'date_range', 'required' => false],
                    'compliance_type' => ['type' => 'select', 'required' => false]
                ],
                'columns' => [
                    'inspections_count', 'compliance_rate', 'violations_found',
                    'penalties_issued', 'industry_compliance', 'trends'
                ]
            ]
        ];
    }
}

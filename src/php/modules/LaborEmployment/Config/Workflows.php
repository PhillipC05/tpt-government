<?php
/**
 * Labor & Employment Module - Workflows Configuration
 */

namespace Modules\LaborEmployment\Config;

class Workflows
{
    /**
     * Get workflows configuration
     */
    public static function getWorkflows(): array
    {
        return [
            'job_application_process' => [
                'name' => 'Job Application Process',
                'description' => 'Complete workflow for job applications and hiring',
                'steps' => [
                    'submitted' => ['name' => 'Application Submitted', 'next' => 'under_review'],
                    'under_review' => ['name' => 'Under Review', 'next' => ['shortlisted', 'rejected']],
                    'shortlisted' => ['name' => 'Shortlisted', 'next' => 'interviewed'],
                    'interviewed' => ['name' => 'Interviewed', 'next' => ['offered', 'rejected']],
                    'offered' => ['name' => 'Job Offered', 'next' => ['hired', 'rejected']],
                    'hired' => ['name' => 'Hired', 'next' => null],
                    'rejected' => ['name' => 'Application Rejected', 'next' => null],
                    'withdrawn' => ['name' => 'Application Withdrawn', 'next' => null]
                ]
            ],
            'employer_verification_process' => [
                'name' => 'Employer Verification Process',
                'description' => 'Workflow for employer registration and verification',
                'steps' => [
                    'pending' => ['name' => 'Verification Pending', 'next' => ['verified', 'rejected']],
                    'verified' => ['name' => 'Employer Verified', 'next' => null],
                    'rejected' => ['name' => 'Verification Rejected', 'next' => null]
                ]
            ]
        ];
    }
}

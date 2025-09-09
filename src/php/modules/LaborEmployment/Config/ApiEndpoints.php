<?php
/**
 * Labor & Employment Module - API Endpoints Configuration
 */

namespace Modules\LaborEmployment\Config;

class ApiEndpoints
{
    /**
     * Get API endpoints configuration
     */
    public static function getEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/api/labor/job-seekers',
                'handler' => 'getJobSeekers',
                'auth' => true,
                'permissions' => ['labor.view']
            ],
            [
                'method' => 'POST',
                'path' => '/api/labor/job-seekers',
                'handler' => 'registerJobSeeker',
                'auth' => true,
                'permissions' => ['labor.create']
            ],
            [
                'method' => 'GET',
                'path' => '/api/labor/employers',
                'handler' => 'getEmployers',
                'auth' => true,
                'permissions' => ['labor.view']
            ],
            [
                'method' => 'POST',
                'path' => '/api/labor/employers',
                'handler' => 'registerEmployer',
                'auth' => true,
                'permissions' => ['labor.create']
            ],
            [
                'method' => 'GET',
                'path' => '/api/labor/jobs',
                'handler' => 'getJobPostings',
                'auth' => true,
                'permissions' => ['labor.view']
            ],
            [
                'method' => 'POST',
                'path' => '/api/labor/jobs',
                'handler' => 'createJobPosting',
                'auth' => true,
                'permissions' => ['labor.create']
            ],
            [
                'method' => 'POST',
                'path' => '/api/labor/jobs/{jobId}/apply',
                'handler' => 'applyForJob',
                'auth' => true,
                'permissions' => ['labor.create']
            ],
            [
                'method' => 'GET',
                'path' => '/api/labor/training',
                'handler' => 'getTrainingPrograms',
                'auth' => true,
                'permissions' => ['labor.view']
            ],
            [
                'method' => 'POST',
                'path' => '/api/labor/training/{programId}/enroll',
                'handler' => 'enrollInTraining',
                'auth' => true,
                'permissions' => ['labor.create']
            ]
        ];
    }
}

<?php
/**
 * Labor & Employment Module - Notifications Configuration
 */

namespace Modules\LaborEmployment\Config;

class Notifications
{
    /**
     * Get notifications configuration
     */
    public static function getNotifications(): array
    {
        return [
            'job_seeker_registered' => [
                'name' => 'Job Seeker Registration Confirmed',
                'template' => 'Welcome! Your job seeker profile has been created. Start exploring job opportunities.',
                'channels' => ['email', 'sms', 'in_app'],
                'triggers' => ['job_seeker_registration']
            ],
            'employer_verified' => [
                'name' => 'Employer Verification Complete',
                'template' => 'Congratulations! Your employer account has been verified. You can now post jobs.',
                'channels' => ['email', 'sms', 'in_app'],
                'triggers' => ['employer_verification']
            ],
            'job_application_submitted' => [
                'name' => 'Job Application Submitted',
                'template' => 'Your application for "{job_title}" has been submitted successfully.',
                'channels' => ['email', 'sms', 'in_app'],
                'triggers' => ['job_application_created']
            ],
            'job_application_update' => [
                'name' => 'Job Application Update',
                'template' => 'Update on your application for "{job_title}": {status}',
                'channels' => ['email', 'sms', 'in_app'],
                'triggers' => ['job_application_status_change']
            ],
            'new_job_matches' => [
                'name' => 'New Job Matches Available',
                'template' => 'We found {count} new job opportunities matching your profile.',
                'channels' => ['email', 'sms', 'in_app'],
                'triggers' => ['job_matches_found']
            ],
            'training_enrollment_confirmed' => [
                'name' => 'Training Enrollment Confirmed',
                'template' => 'Your enrollment in "{program_name}" has been confirmed. Program starts on {start_date}.',
                'channels' => ['email', 'sms', 'in_app'],
                'triggers' => ['training_enrollment']
            ]
        ];
    }
}

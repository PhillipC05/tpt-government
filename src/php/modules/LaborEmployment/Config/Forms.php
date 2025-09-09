<?php
/**
 * Labor & Employment Module - Forms Configuration
 */

namespace Modules\LaborEmployment\Config;

class Forms
{
    /**
     * Get forms configuration
     */
    public static function getForms(): array
    {
        return [
            'job_seeker_registration' => [
                'name' => 'Job Seeker Registration',
                'fields' => [
                    'full_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                    'date_of_birth' => ['type' => 'date', 'required' => true, 'label' => 'Date of Birth'],
                    'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                    'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                    'address' => ['type' => 'textarea', 'required' => true, 'label' => 'Address'],
                    'education_level' => ['type' => 'select', 'required' => true, 'label' => 'Education Level'],
                    'field_of_study' => ['type' => 'text', 'required' => false, 'label' => 'Field of Study'],
                    'work_experience_years' => ['type' => 'number', 'required' => true, 'label' => 'Years of Work Experience'],
                    'skills' => ['type' => 'textarea', 'required' => true, 'label' => 'Skills and Competencies'],
                    'preferred_job_type' => ['type' => 'select', 'required' => true, 'label' => 'Preferred Job Type'],
                    'expected_salary_range' => ['type' => 'text', 'required' => false, 'label' => 'Expected Salary Range'],
                    'availability_date' => ['type' => 'date', 'required' => true, 'label' => 'Available From'],
                    'willing_to_relocate' => ['type' => 'checkbox', 'required' => false, 'label' => 'Willing to Relocate']
                ],
                'documents' => [
                    'resume' => ['required' => true, 'label' => 'Resume/CV'],
                    'certificates' => ['required' => false, 'label' => 'Educational Certificates'],
                    'references' => ['required' => false, 'label' => 'References'],
                    'portfolio' => ['required' => false, 'label' => 'Portfolio/Work Samples']
                ]
            ],
            'employer_registration' => [
                'name' => 'Employer Registration',
                'fields' => [
                    'company_name' => ['type' => 'text', 'required' => true, 'label' => 'Company Name'],
                    'company_type' => ['type' => 'select', 'required' => true, 'label' => 'Company Type'],
                    'industry' => ['type' => 'text', 'required' => true, 'label' => 'Industry'],
                    'company_size' => ['type' => 'select', 'required' => true, 'label' => 'Company Size'],
                    'business_address' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Address'],
                    'contact_person' => ['type' => 'text', 'required' => true, 'label' => 'Contact Person'],
                    'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                    'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                    'website' => ['type' => 'url', 'required' => false, 'label' => 'Company Website'],
                    'business_description' => ['type' => 'textarea', 'required' => true, 'label' => 'Business Description'],
                    'employee_count' => ['type' => 'number', 'required' => true, 'label' => 'Current Employee Count']
                ],
                'documents' => [
                    'business_registration' => ['required' => true, 'label' => 'Business Registration Certificate'],
                    'tax_certificate' => ['required' => true, 'label' => 'Tax Certificate'],
                    'financial_statements' => ['required' => false, 'label' => 'Financial Statements'],
                    'company_profile' => ['required' => false, 'label' => 'Company Profile/Brochure']
                ]
            ],
            'job_posting' => [
                'name' => 'Job Posting Form',
                'fields' => [
                    'title' => ['type' => 'text', 'required' => true, 'label' => 'Job Title'],
                    'category' => ['type' => 'select', 'required' => true, 'label' => 'Job Category'],
                    'job_type' => ['type' => 'select', 'required' => true, 'label' => 'Job Type'],
                    'location' => ['type' => 'text', 'required' => true, 'label' => 'Job Location'],
                    'remote_work' => ['type' => 'checkbox', 'required' => false, 'label' => 'Remote Work Available'],
                    'salary_min' => ['type' => 'number', 'required' => false, 'label' => 'Minimum Salary', 'step' => '0.01'],
                    'salary_max' => ['type' => 'number', 'required' => false, 'label' => 'Maximum Salary', 'step' => '0.01'],
                    'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Job Description'],
                    'requirements' => ['type' => 'textarea', 'required' => true, 'label' => 'Job Requirements'],
                    'benefits' => ['type' => 'textarea', 'required' => false, 'label' => 'Benefits and Perks'],
                    'application_deadline' => ['type' => 'date', 'required' => false, 'label' => 'Application Deadline']
                ]
            ]
        ];
    }
}

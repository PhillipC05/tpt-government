<?php
/**
 * Labor & Employment Module - Database Schema Configuration
 */

namespace Modules\LaborEmployment\Config;

class DatabaseSchema
{
    /**
     * Get database tables schema
     */
    public static function getTables(): array
    {
        return [
            'job_seekers' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'seeker_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'user_id' => 'INT NOT NULL',
                'profile_complete' => 'BOOLEAN DEFAULT FALSE',
                'personal_info' => 'JSON',
                'skills_experience' => 'JSON',
                'education_background' => 'JSON',
                'work_preferences' => 'JSON',
                'availability_status' => "ENUM('actively_looking','open_to_offers','not_looking','employed') DEFAULT 'actively_looking'",
                'registration_date' => 'DATETIME NOT NULL',
                'last_active' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'profile_visibility' => 'BOOLEAN DEFAULT TRUE',
                'documents' => 'JSON',
                'notes' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
            ],
            'employers' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'employer_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'user_id' => 'INT NOT NULL',
                'company_name' => 'VARCHAR(255) NOT NULL',
                'company_type' => "ENUM('private','government','non_profit','educational','healthcare','other') NOT NULL",
                'industry' => 'VARCHAR(100) NOT NULL',
                'company_size' => "ENUM('startup','small','medium','large','enterprise') NOT NULL",
                'location' => 'TEXT NOT NULL',
                'contact_info' => 'JSON',
                'verification_status' => "ENUM('pending','verified','rejected') DEFAULT 'pending'",
                'registration_date' => 'DATETIME NOT NULL',
                'documents' => 'JSON',
                'notes' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
            ],
            'job_postings' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'job_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'employer_id' => 'VARCHAR(20) NOT NULL',
                'title' => 'VARCHAR(255) NOT NULL',
                'description' => 'TEXT NOT NULL',
                'job_type' => "ENUM('full_time','part_time','contract','temporary','internship','freelance') NOT NULL",
                'category' => 'VARCHAR(100) NOT NULL',
                'location' => 'TEXT NOT NULL',
                'remote_work' => 'BOOLEAN DEFAULT FALSE',
                'salary_range' => 'JSON',
                'requirements' => 'JSON',
                'benefits' => 'JSON',
                'application_deadline' => 'DATE NULL',
                'status' => "ENUM('draft','active','paused','closed','filled') DEFAULT 'draft'",
                'posted_date' => 'DATETIME NOT NULL',
                'views_count' => 'INT DEFAULT 0',
                'applications_count' => 'INT DEFAULT 0',
                'featured' => 'BOOLEAN DEFAULT FALSE',
                'documents' => 'JSON',
                'notes' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ],
            'job_applications' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'application_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'job_id' => 'VARCHAR(20) NOT NULL',
                'seeker_id' => 'VARCHAR(20) NOT NULL',
                'cover_letter' => 'TEXT',
                'resume_file' => 'VARCHAR(500)',
                'additional_documents' => 'JSON',
                'application_date' => 'DATETIME NOT NULL',
                'status' => "ENUM('submitted','under_review','shortlisted','interviewed','offered','hired','rejected','withdrawn') DEFAULT 'submitted'",
                'employer_notes' => 'TEXT',
                'seeker_notes' => 'TEXT',
                'interview_scheduled' => 'DATETIME NULL',
                'offer_details' => 'JSON',
                'feedback' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ],
            'training_programs' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'program_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'title' => 'VARCHAR(255) NOT NULL',
                'description' => 'TEXT NOT NULL',
                'category' => "ENUM('technical','soft_skills','leadership','industry_specific','certification','language','digital_skills') NOT NULL",
                'provider_id' => 'INT NOT NULL',
                'duration_hours' => 'INT NOT NULL',
                'cost_per_participant' => 'DECIMAL(8,2) DEFAULT 0',
                'funding_available' => 'BOOLEAN DEFAULT FALSE',
                'max_participants' => 'INT NOT NULL',
                'enrolled_count' => 'INT DEFAULT 0',
                'start_date' => 'DATE NOT NULL',
                'end_date' => 'DATE NOT NULL',
                'location' => 'TEXT',
                'online_available' => 'BOOLEAN DEFAULT FALSE',
                'prerequisites' => 'JSON',
                'learning_objectives' => 'JSON',
                'certification_offered' => 'BOOLEAN DEFAULT FALSE',
                'status' => "ENUM('planned','active','completed','cancelled') DEFAULT 'planned'",
                'evaluation_results' => 'JSON',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
            ],
            'workforce_development' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'program_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'program_name' => 'VARCHAR(255) NOT NULL',
                'program_type' => "ENUM('apprenticeship','internship','job_training','career_counseling','entrepreneurship','retraining') NOT NULL",
                'target_group' => 'VARCHAR(100)',
                'description' => 'TEXT',
                'duration_months' => 'INT NOT NULL',
                'funding_amount' => 'DECIMAL(12,2)',
                'funding_source' => 'VARCHAR(100)',
                'partners' => 'JSON',
                'objectives' => 'JSON',
                'success_metrics' => 'JSON',
                'start_date' => 'DATE NOT NULL',
                'end_date' => 'DATE NOT NULL',
                'coordinator_id' => 'INT NOT NULL',
                'status' => "ENUM('planning','active','completed','cancelled','on_hold') DEFAULT 'planning'",
                'progress_reports' => 'JSON',
                'evaluation' => 'JSON',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ],
            'labor_compliance' => [
                'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
                'compliance_id' => 'VARCHAR(20) UNIQUE NOT NULL',
                'employer_id' => 'VARCHAR(20) NOT NULL',
                'inspection_type' => "ENUM('routine','complaint','follow_up') NOT NULL",
                'scheduled_date' => 'DATETIME NOT NULL',
                'actual_date' => 'DATETIME NULL',
                'inspector_id' => 'INT NULL',
                'findings' => 'JSON',
                'violations' => 'JSON',
                'recommendations' => 'TEXT',
                'compliance_status' => "ENUM('compliant','minor_violations','major_violations','critical_violations') NULL",
                'corrective_actions' => 'JSON',
                'follow_up_required' => 'BOOLEAN DEFAULT FALSE',
                'follow_up_date' => 'DATETIME NULL',
                'penalty_amount' => 'DECIMAL(10,2) DEFAULT 0',
                'status' => "ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled'",
                'report_file' => 'VARCHAR(500)',
                'notes' => 'TEXT',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
            ]
        ];
    }
}

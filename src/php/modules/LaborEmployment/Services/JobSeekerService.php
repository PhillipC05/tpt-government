<?php
/**
 * Job Seeker Service
 */

namespace Modules\LaborEmployment\Services;

use Core\Database;
use Core\NotificationManager;

class JobSeekerService
{
    private Database $database;
    private NotificationManager $notificationManager;

    public function __construct()
    {
        $this->database = new Database();
        $this->notificationManager = new NotificationManager();
    }

    /**
     * Register job seeker
     */
    public function registerJobSeeker(array $seekerData): array
    {
        // Validate seeker data
        $validation = $this->validateJobSeekerData($seekerData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate seeker ID
        $seekerId = $this->generateSeekerId();

        // Create job seeker record
        $seeker = [
            'seeker_id' => $seekerId,
            'user_id' => $seekerData['user_id'],
            'profile_complete' => true,
            'personal_info' => json_encode([
                'full_name' => $seekerData['full_name'],
                'date_of_birth' => $seekerData['date_of_birth'],
                'contact_phone' => $seekerData['contact_phone'],
                'contact_email' => $seekerData['contact_email'],
                'address' => $seekerData['address']
            ]),
            'skills_experience' => json_encode([
                'education_level' => $seekerData['education_level'],
                'field_of_study' => $seekerData['field_of_study'] ?? '',
                'work_experience_years' => $seekerData['work_experience_years'],
                'skills' => $seekerData['skills']
            ]),
            'education_background' => json_encode([]),
            'work_preferences' => json_encode([
                'job_type' => $seekerData['preferred_job_type'],
                'salary_range' => $seekerData['expected_salary_range'] ?? '',
                'availability_date' => $seekerData['availability_date'],
                'willing_to_relocate' => $seekerData['willing_to_relocate'] ?? false
            ]),
            'availability_status' => 'actively_looking',
            'registration_date' => date('Y-m-d H:i:s'),
            'documents' => json_encode($seekerData['documents'] ?? []),
            'notes' => $seekerData['notes'] ?? ''
        ];

        // Save to database
        $this->saveJobSeeker($seeker);

        // Send notification
        $this->notificationManager->sendNotification('job_seeker_registered', $seekerData['user_id'], [
            'seeker_id' => $seekerId
        ]);

        return [
            'success' => true,
            'seeker_id' => $seekerId,
            'message' => 'Job seeker profile created successfully'
        ];
    }

    /**
     * Get job seekers
     */
    public function getJobSeekers(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM job_seekers WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND availability_status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            $sql .= " ORDER BY registration_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['personal_info'] = json_decode($result['personal_info'], true);
                $result['skills_experience'] = json_decode($result['skills_experience'], true);
                $result['education_background'] = json_decode($result['education_background'], true);
                $result['work_preferences'] = json_decode($result['work_preferences'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting job seekers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve job seekers'
            ];
        }
    }

    /**
     * Update job seeker profile
     */
    public function updateJobSeeker(string $seekerId, array $updateData): array
    {
        try {
            $db = Database::getInstance();

            // Build update query
            $updateFields = [];
            $params = [];

            if (isset($updateData['personal_info'])) {
                $updateFields[] = "personal_info = ?";
                $params[] = json_encode($updateData['personal_info']);
            }

            if (isset($updateData['skills_experience'])) {
                $updateFields[] = "skills_experience = ?";
                $params[] = json_encode($updateData['skills_experience']);
            }

            if (isset($updateData['work_preferences'])) {
                $updateFields[] = "work_preferences = ?";
                $params[] = json_encode($updateData['work_preferences']);
            }

            if (isset($updateData['availability_status'])) {
                $updateFields[] = "availability_status = ?";
                $params[] = $updateData['availability_status'];
            }

            if (isset($updateData['documents'])) {
                $updateFields[] = "documents = ?";
                $params[] = json_encode($updateData['documents']);
            }

            if (isset($updateData['notes'])) {
                $updateFields[] = "notes = ?";
                $params[] = $updateData['notes'];
            }

            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'error' => 'No valid fields to update'
                ];
            }

            $sql = "UPDATE job_seekers SET " . implode(', ', $updateFields) . " WHERE seeker_id = ?";
            $params[] = $seekerId;

            $success = $db->execute($sql, $params);

            return [
                'success' => $success,
                'message' => $success ? 'Job seeker profile updated successfully' : 'Failed to update profile'
            ];
        } catch (\Exception $e) {
            error_log("Error updating job seeker: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update job seeker profile'
            ];
        }
    }

    /**
     * Get job seeker by ID
     */
    public function getJobSeeker(string $seekerId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM job_seekers WHERE seeker_id = ?";
            $result = $db->fetch($sql, [$seekerId]);

            if ($result) {
                $result['personal_info'] = json_decode($result['personal_info'], true);
                $result['skills_experience'] = json_decode($result['skills_experience'], true);
                $result['education_background'] = json_decode($result['education_background'], true);
                $result['work_preferences'] = json_decode($result['work_preferences'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting job seeker: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete job seeker
     */
    public function deleteJobSeeker(string $seekerId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "DELETE FROM job_seekers WHERE seeker_id = ?";
            return $db->execute($sql, [$seekerId]);
        } catch (\Exception $e) {
            error_log("Error deleting job seeker: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate seeker ID
     */
    private function generateSeekerId(): string
    {
        return 'JS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save job seeker
     */
    private function saveJobSeeker(array $seeker): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO job_seekers (
                seeker_id, user_id, profile_complete, personal_info,
                skills_experience, education_background, work_preferences,
                availability_status, registration_date, documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $seeker['seeker_id'],
                $seeker['user_id'],
                $seeker['profile_complete'],
                $seeker['personal_info'],
                $seeker['skills_experience'],
                $seeker['education_background'],
                $seeker['work_preferences'],
                $seeker['availability_status'],
                $seeker['registration_date'],
                $seeker['documents'],
                $seeker['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving job seeker: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate job seeker data
     */
    private function validateJobSeekerData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'full_name', 'date_of_birth', 'contact_phone',
            'contact_email', 'address', 'education_level', 'work_experience_years',
            'skills', 'preferred_job_type', 'availability_date'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate email format
        if (isset($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Validate date of birth (must be at least 16 years old)
        if (isset($data['date_of_birth'])) {
            $dob = strtotime($data['date_of_birth']);
            $age = (time() - $dob) / (365.25 * 24 * 60 * 60);
            if ($age < 16) {
                $errors[] = "Must be at least 16 years old";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

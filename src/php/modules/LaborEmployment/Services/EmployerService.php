<?php
/**
 * Employer Service
 */

namespace Modules\LaborEmployment\Services;

use Core\Database;
use Core\NotificationManager;

class EmployerService
{
    private Database $database;
    private NotificationManager $notificationManager;

    public function __construct()
    {
        $this->database = new Database();
        $this->notificationManager = new NotificationManager();
    }

    /**
     * Register employer
     */
    public function registerEmployer(array $employerData): array
    {
        // Validate employer data
        $validation = $this->validateEmployerData($employerData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate employer ID
        $employerId = $this->generateEmployerId();

        // Create employer record
        $employer = [
            'employer_id' => $employerId,
            'user_id' => $employerData['user_id'],
            'company_name' => $employerData['company_name'],
            'company_type' => $employerData['company_type'],
            'industry' => $employerData['industry'],
            'company_size' => $employerData['company_size'],
            'location' => $employerData['business_address'],
            'contact_info' => json_encode([
                'contact_person' => $employerData['contact_person'],
                'phone' => $employerData['contact_phone'],
                'email' => $employerData['contact_email'],
                'website' => $employerData['website'] ?? ''
            ]),
            'verification_status' => 'pending',
            'registration_date' => date('Y-m-d H:i:s'),
            'documents' => json_encode($employerData['documents'] ?? []),
            'notes' => $employerData['notes'] ?? ''
        ];

        // Save to database
        $this->saveEmployer($employer);

        return [
            'success' => true,
            'employer_id' => $employerId,
            'verification_status' => 'pending',
            'message' => 'Employer registration submitted for verification'
        ];
    }

    /**
     * Get employers
     */
    public function getEmployers(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM employers WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND verification_status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (isset($filters['industry'])) {
                $sql .= " AND industry = ?";
                $params[] = $filters['industry'];
            }

            $sql .= " ORDER BY registration_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['contact_info'] = json_decode($result['contact_info'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting employers: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve employers'
            ];
        }
    }

    /**
     * Update employer profile
     */
    public function updateEmployer(string $employerId, array $updateData): array
    {
        try {
            $db = Database::getInstance();

            // Build update query
            $updateFields = [];
            $params = [];

            if (isset($updateData['company_name'])) {
                $updateFields[] = "company_name = ?";
                $params[] = $updateData['company_name'];
            }

            if (isset($updateData['company_type'])) {
                $updateFields[] = "company_type = ?";
                $params[] = $updateData['company_type'];
            }

            if (isset($updateData['industry'])) {
                $updateFields[] = "industry = ?";
                $params[] = $updateData['industry'];
            }

            if (isset($updateData['company_size'])) {
                $updateFields[] = "company_size = ?";
                $params[] = $updateData['company_size'];
            }

            if (isset($updateData['location'])) {
                $updateFields[] = "location = ?";
                $params[] = $updateData['location'];
            }

            if (isset($updateData['contact_info'])) {
                $updateFields[] = "contact_info = ?";
                $params[] = json_encode($updateData['contact_info']);
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

            $sql = "UPDATE employers SET " . implode(', ', $updateFields) . " WHERE employer_id = ?";
            $params[] = $employerId;

            $success = $db->execute($sql, $params);

            return [
                'success' => $success,
                'message' => $success ? 'Employer profile updated successfully' : 'Failed to update profile'
            ];
        } catch (\Exception $e) {
            error_log("Error updating employer: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update employer profile'
            ];
        }
    }

    /**
     * Get employer by ID
     */
    public function getEmployer(string $employerId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM employers WHERE employer_id = ?";
            $result = $db->fetch($sql, [$employerId]);

            if ($result) {
                $result['contact_info'] = json_decode($result['contact_info'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting employer: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify employer
     */
    public function verifyEmployer(string $employerId, bool $approved = true): array
    {
        try {
            $db = Database::getInstance();

            $status = $approved ? 'verified' : 'rejected';
            $sql = "UPDATE employers SET verification_status = ? WHERE employer_id = ?";
            $success = $db->execute($sql, [$status, $employerId]);

            if ($success && $approved) {
                // Get employer data for notification
                $employer = $this->getEmployer($employerId);
                if ($employer) {
                    $this->notificationManager->sendNotification('employer_verified', $employer['user_id'], [
                        'employer_id' => $employerId,
                        'company_name' => $employer['company_name']
                    ]);
                }
            }

            return [
                'success' => $success,
                'status' => $status,
                'message' => $success ? "Employer {$status} successfully" : "Failed to update employer status"
            ];
        } catch (\Exception $e) {
            error_log("Error verifying employer: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to verify employer'
            ];
        }
    }

    /**
     * Delete employer
     */
    public function deleteEmployer(string $employerId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "DELETE FROM employers WHERE employer_id = ?";
            return $db->execute($sql, [$employerId]);
        } catch (\Exception $e) {
            error_log("Error deleting employer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get employer statistics
     */
    public function getEmployerStatistics(): array
    {
        try {
            $db = Database::getInstance();

            $stats = [];

            // Total employers
            $result = $db->fetch("SELECT COUNT(*) as total FROM employers");
            $stats['total_employers'] = $result['total'] ?? 0;

            // Verified employers
            $result = $db->fetch("SELECT COUNT(*) as verified FROM employers WHERE verification_status = 'verified'");
            $stats['verified_employers'] = $result['verified'] ?? 0;

            // Pending verification
            $result = $db->fetch("SELECT COUNT(*) as pending FROM employers WHERE verification_status = 'pending'");
            $stats['pending_verification'] = $result['pending'] ?? 0;

            // Employers by industry
            $results = $db->fetchAll("SELECT industry, COUNT(*) as count FROM employers GROUP BY industry ORDER BY count DESC");
            $stats['employers_by_industry'] = $results;

            // Employers by company size
            $results = $db->fetchAll("SELECT company_size, COUNT(*) as count FROM employers GROUP BY company_size ORDER BY count DESC");
            $stats['employers_by_size'] = $results;

            return [
                'success' => true,
                'statistics' => $stats
            ];
        } catch (\Exception $e) {
            error_log("Error getting employer statistics: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve employer statistics'
            ];
        }
    }

    /**
     * Generate employer ID
     */
    private function generateEmployerId(): string
    {
        return 'EMP' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Save employer
     */
    private function saveEmployer(array $employer): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO employers (
                employer_id, user_id, company_name, company_type,
                industry, company_size, location, contact_info,
                verification_status, registration_date, documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $employer['employer_id'],
                $employer['user_id'],
                $employer['company_name'],
                $employer['company_type'],
                $employer['industry'],
                $employer['company_size'],
                $employer['location'],
                $employer['contact_info'],
                $employer['verification_status'],
                $employer['registration_date'],
                $employer['documents'],
                $employer['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving employer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate employer data
     */
    private function validateEmployerData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'company_name', 'company_type', 'industry',
            'company_size', 'business_address', 'contact_person',
            'contact_phone', 'contact_email', 'business_description', 'employee_count'
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

        // Validate website URL if provided
        if (isset($data['website']) && !empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid website URL format";
        }

        // Validate employee count
        if (isset($data['employee_count']) && (!is_numeric($data['employee_count']) || $data['employee_count'] < 0)) {
            $errors[] = "Invalid employee count";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

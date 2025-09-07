<?php
/**
 * TPT Government Platform - Authentication Service
 *
 * Comprehensive authentication and authorization service.
 * Handles user registration, login, password management, and role-based access.
 */

namespace Core;

class Auth
{
    /**
     * Database instance
     */
    private Database $database;

    /**
     * Session instance
     */
    private Session $session;

    /**
     * Constructor
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->session = new Session();
    }

    /**
     * Register a new user
     *
     * @param array $userData User registration data
     * @return array Result with success/error information
     */
    public function register(array $userData): array
    {
        // Validate required fields
        $required = ['email', 'password', 'name'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                return ['success' => false, 'error' => "Field '{$field}' is required"];
            }
        }

        // Validate email format
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        // Check password strength
        if (strlen($userData['password']) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        // Check if email already exists
        if ($this->emailExists($userData['email'])) {
            return ['success' => false, 'error' => 'Email address already registered'];
        }

        try {
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);

            // Prepare user data
            $user = [
                'email' => $userData['email'],
                'password_hash' => $passwordHash,
                'name' => $userData['name'],
                'roles' => json_encode($userData['roles'] ?? ['user']),
                'department' => $userData['department'] ?? null,
                'active' => true,
                'email_verified' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Insert user
            $userId = $this->database->insert('users', $user);

            // Log registration
            $this->logAction($userId, 'user_registered', [
                'email' => $userData['email'],
                'name' => $userData['name']
            ]);

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'User registered successfully'
            ];

        } catch (\Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Registration failed'];
        }
    }

    /**
     * Authenticate user login
     *
     * @param string $email User email
     * @param string $password User password
     * @return array|null User data on success, null on failure
     */
    public function login(string $email, string $password): ?array
    {
        try {
            // Get user by email
            $user = $this->database->selectOne(
                'SELECT id, email, password_hash, name, roles, department, active, email_verified, last_login
                 FROM users WHERE email = ? AND active = true',
                [$email]
            );

            if (!$user) {
                $this->logFailedLogin($email, 'user_not_found');
                return null;
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->logFailedLogin($email, 'invalid_password');
                return null;
            }

            // Update last login
            $this->database->update('users', [
                'last_login' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $user['id']]);

            // Parse roles
            $user['roles'] = json_decode($user['roles'] ?? '[]', true);

            // Log successful login
            $this->logAction($user['id'], 'login', ['email' => $email]);

            return $user;

        } catch (\Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Logout user
     *
     * @return void
     */
    public function logout(): void
    {
        $userId = Session::getUserId();
        if ($userId) {
            $this->logAction($userId, 'logout');
        }
        Session::logout();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return Session::isAuthenticated();
    }

    /**
     * Get current authenticated user
     *
     * @return array|null User data or null
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = Session::getUserId();
        if (!$userId) {
            return null;
        }

        try {
            $user = $this->database->selectOne(
                'SELECT id, email, name, roles, department, active, email_verified, last_login, created_at
                 FROM users WHERE id = ? AND active = true',
                [$userId]
            );

            if ($user) {
                $user['roles'] = json_decode($user['roles'] ?? '[]', true);
            }

            return $user;
        } catch (\Exception $e) {
            error_log('Get current user error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user has specific role
     *
     * @param string $role Role to check
     * @param int|null $userId User ID (current user if null)
     * @return bool
     */
    public function hasRole(string $role, ?int $userId = null): bool
    {
        $user = $userId ? $this->getUserById($userId) : $this->getCurrentUser();

        if (!$user || !isset($user['roles'])) {
            return false;
        }

        return in_array($role, $user['roles']);
    }

    /**
     * Check if user has any of the specified roles
     *
     * @param array $roles Roles to check
     * @param int|null $userId User ID (current user if null)
     * @return bool
     */
    public function hasAnyRole(array $roles, ?int $userId = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $userId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all specified roles
     *
     * @param array $roles Roles to check
     * @param int|null $userId User ID (current user if null)
     * @return bool
     */
    public function hasAllRoles(array $roles, ?int $userId = null): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role, $userId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get user by ID
     *
     * @param int $userId User ID
     * @return array|null User data or null
     */
    public function getUserById(int $userId): ?array
    {
        try {
            $user = $this->database->selectOne(
                'SELECT id, email, name, roles, department, active, email_verified, last_login, created_at
                 FROM users WHERE id = ?',
                [$userId]
            );

            if ($user) {
                $user['roles'] = json_decode($user['roles'] ?? '[]', true);
            }

            return $user;
        } catch (\Exception $e) {
            error_log('Get user by ID error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user password
     *
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Result with success/error information
     */
    public function updatePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        try {
            // Get current user data
            $user = $this->database->selectOne(
                'SELECT password_hash FROM users WHERE id = ?',
                [$userId]
            );

            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $this->logAction($userId, 'password_change_failed', ['reason' => 'invalid_current_password']);
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'error' => 'New password must be at least 8 characters long'];
            }

            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password
            $this->database->update('users', [
                'password_hash' => $newPasswordHash,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $userId]);

            $this->logAction($userId, 'password_changed');
            return ['success' => true, 'message' => 'Password updated successfully'];

        } catch (\Exception $e) {
            error_log('Password update error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Password update failed'];
        }
    }

    /**
     * Generate password reset token
     *
     * @param string $email User email
     * @return array Result with success/error information
     */
    public function generatePasswordResetToken(string $email): array
    {
        try {
            $user = $this->database->selectOne(
                'SELECT id FROM users WHERE email = ? AND active = true',
                [$email]
            );

            if (!$user) {
                // Don't reveal if email exists for security
                return ['success' => true, 'message' => 'If the email exists, a reset link has been sent'];
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token
            $this->database->update('users', [
                'reset_token' => $token,
                'reset_token_expires' => $expires,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $user['id']]);

            $this->logAction($user['id'], 'password_reset_requested', ['email' => $email]);

            return [
                'success' => true,
                'token' => $token,
                'message' => 'Password reset token generated'
            ];

        } catch (\Exception $e) {
            error_log('Password reset token generation error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to generate reset token'];
        }
    }

    /**
     * Reset password using token
     *
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array Result with success/error information
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        try {
            $user = $this->database->selectOne(
                'SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > ?',
                [$token, date('Y-m-d H:i:s')]
            );

            if (!$user) {
                return ['success' => false, 'error' => 'Invalid or expired reset token'];
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
            }

            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password and clear reset token
            $this->database->update('users', [
                'password_hash' => $passwordHash,
                'reset_token' => null,
                'reset_token_expires' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $user['id']]);

            $this->logAction($user['id'], 'password_reset');
            return ['success' => true, 'message' => 'Password reset successfully'];

        } catch (\Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Password reset failed'];
        }
    }

    /**
     * Check if email exists
     *
     * @param string $email Email to check
     * @return bool
     */
    private function emailExists(string $email): bool
    {
        try {
            $count = $this->database->selectOne(
                'SELECT COUNT(*) as count FROM users WHERE email = ?',
                [$email]
            );
            return ($count['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log failed login attempt
     *
     * @param string $email Email used in attempt
     * @param string $reason Reason for failure
     * @return void
     */
    private function logFailedLogin(string $email, string $reason): void
    {
        $this->logAction(null, 'login_failed', [
            'email' => $email,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Log user action
     *
     * @param int|null $userId User ID
     * @param string $action Action performed
     * @param array $data Additional data
     * @return void
     */
    private function logAction(?int $userId, string $action, array $data = []): void
    {
        try {
            $this->database->insert('audit_logs', [
                'user_id' => $userId,
                'action' => $action,
                'data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Log to file as fallback
            error_log("Auth action: {$action} - User: {$userId} - Data: " . json_encode($data));
        }
    }
}

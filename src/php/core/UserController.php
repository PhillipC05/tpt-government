<?php
/**
 * TPT Government Platform - User Controller
 *
 * Handles user profile management and user-related operations.
 */

namespace Core;

class UserController extends Controller
{
    /**
     * Get user profile
     *
     * @return void
     */
    public function profile(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('Not authenticated', 401);
            return;
        }

        $user = $this->getCurrentUser();
        $this->json(['user' => $user]);
    }

    /**
     * Update user profile
     *
     * @return void
     */
    public function updateProfile(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('Not authenticated', 401);
            return;
        }

        $validation = $this->validate([
            'name' => 'required|min:2|max:100',
            'email' => 'required|email'
        ]);

        if (!$validation['valid']) {
            $this->error('Validation failed: ' . implode(', ', array_map(function($errors) {
                return implode(', ', $errors);
            }, $validation['errors'])), 422);
            return;
        }

        $userId = $this->getCurrentUserId();
        $name = $this->request->post('name');
        $email = $this->request->post('email');

        if ($this->database) {
            try {
                $this->database->update('users', [
                    'name' => $name,
                    'email' => $email,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $userId]);

                // Update session data
                $user = $this->getCurrentUser();
                $user['name'] = $name;
                $user['email'] = $email;
                Session::set('user_data', $user);

                $this->logAction('profile_updated', ['name' => $name, 'email' => $email]);
                $this->success(['user' => $user], 'Profile updated successfully');
            } catch (\Exception $e) {
                $this->error('Failed to update profile', 500);
            }
        } else {
            // Mock update for demo
            $user = $this->getCurrentUser();
            $user['name'] = $name;
            $user['email'] = $email;
            Session::set('user_data', $user);

            $this->success(['user' => $user], 'Profile updated successfully');
        }
    }

    /**
     * Change user password
     *
     * @return void
     */
    public function changePassword(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('Not authenticated', 401);
            return;
        }

        $validation = $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required'
        ]);

        if (!$validation['valid']) {
            $this->error('Validation failed', 422);
            return;
        }

        $currentPassword = $this->request->post('current_password');
        $newPassword = $this->request->post('new_password');
        $confirmPassword = $this->request->post('confirm_password');

        if ($newPassword !== $confirmPassword) {
            $this->error('New passwords do not match', 422);
            return;
        }

        $userId = $this->getCurrentUserId();

        if ($this->database) {
            try {
                // Verify current password
                $user = $this->database->selectOne(
                    'SELECT password_hash FROM users WHERE id = ?',
                    [$userId]
                );

                if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                    $this->error('Current password is incorrect', 422);
                    return;
                }

                // Update password
                $this->database->update('users', [
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $userId]);

                $this->logAction('password_changed');
                $this->success([], 'Password changed successfully');
            } catch (\Exception $e) {
                $this->error('Failed to change password', 500);
            }
        } else {
            // Mock password change for demo
            $this->success([], 'Password changed successfully');
        }
    }
}

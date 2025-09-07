<?php
/**
 * TPT Government Platform - Authentication Controller
 *
 * Handles user authentication, login, logout, and session management.
 */

namespace Core;

class AuthController extends Controller
{
    /**
     * Show login form
     *
     * @return void
     */
    public function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth.login', [
            'title' => 'Login - TPT Government Platform'
        ]);
    }

    /**
     * Handle login request
     *
     * @return void
     */
    public function login(): void
    {
        // Validate input
        $validation = $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if (!$validation['valid']) {
            $this->error('Validation failed', 422);
            return;
        }

        $email = $this->request->post('email');
        $password = $this->request->post('password');

        // Authenticate user
        $user = $this->authenticateUser($email, $password);

        if ($user) {
            // Set session
            Session::authenticate($user['id'], $user);

            // Log successful login
            $this->logAction('login', ['email' => $email]);

            $this->success([
                'user' => $user,
                'redirect' => '/dashboard'
            ], 'Login successful');
        } else {
            // Log failed login attempt
            $this->logAction('login_failed', ['email' => $email]);

            $this->error('Invalid email or password', 401);
        }
    }

    /**
     * Handle logout request
     *
     * @return void
     */
    public function logout(): void
    {
        $userId = $this->getCurrentUserId();

        // Log logout
        $this->logAction('logout');

        // Destroy session
        Session::logout();

        $this->success(['redirect' => '/'], 'Logout successful');
    }

    /**
     * Get current user session info
     *
     * @return void
     */
    public function session(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('Not authenticated', 401);
            return;
        }

        $user = $this->getCurrentUser();
        $this->json([
            'authenticated' => true,
            'user' => $user,
            'session_id' => Session::getId(),
            'login_time' => Session::get('login_time')
        ]);
    }

    /**
     * Authenticate user against database
     *
     * @param string $email User email
     * @param string $password User password
     * @return array|null User data or null if authentication fails
     */
    private function authenticateUser(string $email, string $password): ?array
    {
        if (!$this->database) {
            // For demo purposes, return mock user
            if ($email === 'admin@gov.local' && $password === 'password') {
                return [
                    'id' => 1,
                    'email' => $email,
                    'name' => 'Government Administrator',
                    'roles' => ['admin', 'user'],
                    'department' => 'IT Department'
                ];
            }
            return null;
        }

        try {
            // Query database for user
            $user = $this->database->selectOne(
                'SELECT id, email, password_hash, name, roles, department, active
                 FROM users WHERE email = ? AND active = true',
                [$email]
            );

            if ($user && password_verify($password, $user['password_hash'])) {
                // Remove password hash from response
                unset($user['password_hash']);
                $user['roles'] = json_decode($user['roles'] ?? '[]', true);
                return $user;
            }
        } catch (\Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
        }

        return null;
    }
}

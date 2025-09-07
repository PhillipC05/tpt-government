<?php
/**
 * TPT Government Platform - Session Management
 *
 * Secure session management for the government platform.
 * Implements best practices for government-grade security.
 */

namespace Core;

class Session
{
    /**
     * Session configuration
     */
    private static array $config = [
        'name' => 'TPT_GOV_SESSION',
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'gc_maxlifetime' => 3600,
        'save_path' => SESSIONS_PATH
    ];

    /**
     * Start session with secure configuration
     *
     * @param array $options Session configuration options
     * @return bool True if session started successfully
     */
    public static function start(array $options = []): bool
    {
        // Merge with default configuration
        $config = array_merge(self::$config, $options);

        // Set session configuration
        ini_set('session.name', $config['name']);
        ini_set('session.cookie_secure', $config['cookie_secure']);
        ini_set('session.cookie_httponly', $config['cookie_httponly']);
        ini_set('session.cookie_samesite', $config['cookie_samesite']);
        ini_set('session.gc_maxlifetime', $config['gc_maxlifetime']);
        ini_set('session.save_path', $config['save_path']);

        // Ensure session directory exists and is secure
        if (!is_dir($config['save_path'])) {
            mkdir($config['save_path'], 0700, true);
        }

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerate session ID for security
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }

        return true;
    }

    /**
     * Get a session value
     *
     * @param string $key The session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The session value or default
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value
     *
     * @param string $key The session key
     * @param mixed $value The value to store
     * @return void
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists
     *
     * @param string $key The session key
     * @return bool True if key exists, false otherwise
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value
     *
     * @param string $key The session key to remove
     * @return void
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     *
     * @return void
     */
    public static function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Destroy the session
     *
     * @return bool True if session destroyed successfully
     */
    public static function destroy(): bool
    {
        // Clear session data
        self::clear();

        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy session
        return session_destroy();
    }

    /**
     * Regenerate session ID
     *
     * @param bool $deleteOldSession Whether to delete the old session file
     * @return bool True if regeneration successful
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Get session ID
     *
     * @return string The current session ID
     */
    public static function getId(): string
    {
        return session_id();
    }

    /**
     * Set session ID
     *
     * @param string $id The session ID to set
     * @return void
     */
    public static function setId(string $id): void
    {
        session_id($id);
    }

    /**
     * Get session name
     *
     * @return string The session name
     */
    public static function getName(): string
    {
        return session_name();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool True if user is authenticated
     */
    public static function isAuthenticated(): bool
    {
        return self::has('user_id') && self::has('authenticated');
    }

    /**
     * Set user as authenticated
     *
     * @param int $userId The user ID
     * @param array $userData Additional user data
     * @return void
     */
    public static function authenticate(int $userId, array $userData = []): void
    {
        self::set('user_id', $userId);
        self::set('authenticated', true);
        self::set('user_data', $userData);
        self::set('login_time', time());
    }

    /**
     * Log out user
     *
     * @return void
     */
    public static function logout(): void
    {
        self::remove('user_id');
        self::remove('authenticated');
        self::remove('user_data');
        self::remove('login_time');
        self::regenerate();
    }

    /**
     * Get current user ID
     *
     * @return int|null The user ID or null if not authenticated
     */
    public static function getUserId(): ?int
    {
        return self::get('user_id');
    }

    /**
     * Get current user data
     *
     * @return array|null The user data or null if not authenticated
     */
    public static function getUserData(): ?array
    {
        return self::get('user_data');
    }
}

<?php
/**
 * TPT Government Platform - Base Controller
 *
 * Base controller class with common functionality and dependency injection.
 * All controllers should extend this class.
 */

namespace Core;

use Core\DependencyInjection\Container;

abstract class Controller
{
    /**
     * Dependency injection container
     */
    protected Container $container;

    /**
     * Request instance
     */
    protected Request $request;

    /**
     * Response instance
     */
    protected Response $response;

    /**
     * Database instance
     */
    protected ?Database $database = null;

    /**
     * Constructor with dependency injection
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->request = $container->get(Request::class);
        $this->response = $container->get(Response::class);

        // Try to get database from container
        try {
            $this->database = $container->get('database');
        } catch (\Exception $e) {
            $this->database = null;
        }
    }

    /**
     * Legacy setter methods for backward compatibility
     * @deprecated Use constructor injection instead
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function setDatabase(Database $database): void
    {
        $this->database = $database;
    }

    /**
     * Get current user ID
     *
     * @return int|null The user ID or null if not authenticated
     */
    protected function getCurrentUserId(): ?int
    {
        return Session::getUserId();
    }

    /**
     * Get current user data
     *
     * @return array|null The user data or null if not authenticated
     */
    protected function getCurrentUser(): ?array
    {
        return Session::getUserData();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool True if authenticated
     */
    protected function isAuthenticated(): bool
    {
        return Session::isAuthenticated();
    }

    /**
     * Check if user has specific role
     *
     * @param string $role The role to check
     * @return bool True if user has role
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($role, $user['roles'] ?? []);
    }

    /**
     * Validate request data
     *
     * @param array $rules Validation rules
     * @param array|null $data Data to validate (defaults to POST)
     * @return array Validation result
     */
    protected function validate(array $rules, array $data = null): array
    {
        if (!$this->request) {
            return ['valid' => false, 'errors' => ['Request not available']];
        }

        return $this->request->validate($rules, $data);
    }

    /**
     * Log user action for audit trail
     *
     * @param string $action The action performed
     * @param array $data Additional data
     * @return void
     */
    protected function logAction(string $action, array $data = []): void
    {
        $userId = $this->getCurrentUserId();
        $logData = [
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $this->request ? $this->request->getClientIp() : null,
            'user_agent' => $this->request ? $this->request->getUserAgent() : null,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => json_encode($data)
        ];

        // Log to database if available
        if ($this->database) {
            try {
                $this->database->insert('audit_logs', $logData);
            } catch (\Exception $e) {
                // Log to file as fallback
                error_log('Audit log failed: ' . $e->getMessage());
            }
        }

        // Always log to file
        $logMessage = sprintf(
            "[%s] User %s: %s - %s\n",
            $logData['timestamp'],
            $userId ?? 'Anonymous',
            $action,
            json_encode($data)
        );

        if (defined('LOGS_PATH')) {
            file_put_contents(
                LOGS_PATH . '/audit.log',
                $logMessage,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    /**
     * Send success response
     *
     * @param mixed $data The response data
     * @param string $message The success message
     * @return void
     */
    protected function success($data = null, string $message = 'Success'): void
    {
        if ($this->response) {
            $this->response->success($data, $message);
        }
    }

    /**
     * Send error response
     *
     * @param string $message The error message
     * @param int $statusCode The HTTP status code
     * @return void
     */
    protected function error(string $message, int $statusCode = 400): void
    {
        if ($this->response) {
            $this->response->error($message, $statusCode);
        }
    }

    /**
     * Send JSON response
     *
     * @param mixed $data The response data
     * @param int $statusCode The HTTP status code
     * @return void
     */
    protected function json($data, int $statusCode = 200): void
    {
        if ($this->response) {
            $this->response->json($data, $statusCode);
        }
    }

    /**
     * Render HTML view
     *
     * @param string $view The view name
     * @param array $data The view data
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        if ($this->response) {
            // For now, just return JSON. In a full implementation,
            // this would render HTML templates
            $this->response->json([
                'view' => $view,
                'data' => $data
            ]);
        }
    }

    /**
     * Redirect to URL
     *
     * @param string $url The redirect URL
     * @param int $statusCode The HTTP status code
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        if ($this->response) {
            $this->response->redirect($url, $statusCode);
        }
    }
}

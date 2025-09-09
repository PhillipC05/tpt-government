<?php
/**
 * TPT Government Platform - CSRF Protection Middleware
 *
 * Protects against Cross-Site Request Forgery attacks.
 */

namespace Core\Middleware;

use Core\Request;
use Core\Response;
use Core\Session;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * CSRF token name
     */
    private const TOKEN_NAME = '_csrf_token';

    /**
     * CSRF token header name
     */
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * Token expiry time (1 hour)
     */
    private const TOKEN_EXPIRY = 3600;

    /**
     * Excluded routes (GET, HEAD, OPTIONS)
     */
    private array $excludedMethods = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Handle CSRF protection
     *
     * @param Request $request Request object
     * @param Response $response Response object
     * @param callable $next Next middleware
     * @return mixed
     */
    public function handle($request, $response, callable $next)
    {
        // Skip CSRF check for safe HTTP methods
        if (in_array($request->getMethod(), $this->excludedMethods)) {
            return $next($request, $response);
        }

        // Generate token if not exists or expired
        if (!$this->hasValidToken()) {
            $this->generateToken();
        }

        // Validate token for state-changing requests
        if (!$this->validateToken($request)) {
            return $this->handleCsrfFailure($response);
        }

        // Add CSRF token to response for forms
        $this->addTokenToResponse($response);

        return $next($request, $response);
    }

    /**
     * Check if a valid CSRF token exists
     *
     * @return bool
     */
    private function hasValidToken(): bool
    {
        $token = Session::get(self::TOKEN_NAME);

        if (!$token) {
            return false;
        }

        // Check if token has expired
        if (isset($token['expires']) && $token['expires'] < time()) {
            Session::remove(self::TOKEN_NAME);
            return false;
        }

        return true;
    }

    /**
     * Generate a new CSRF token
     *
     * @return void
     */
    private function generateToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenData = [
            'token' => $token,
            'expires' => time() + self::TOKEN_EXPIRY
        ];

        Session::set(self::TOKEN_NAME, $tokenData);
    }

    /**
     * Validate CSRF token from request
     *
     * @param Request $request Request object
     * @return bool
     */
    private function validateToken(Request $request): bool
    {
        $sessionToken = $this->getSessionToken();
        if (!$sessionToken) {
            return false;
        }

        // Check header first (preferred for AJAX requests)
        $headerToken = $request->header(self::HEADER_NAME);
        if ($headerToken && hash_equals($sessionToken, $headerToken)) {
            return true;
        }

        // Check POST data
        $postToken = $request->post('_csrf_token');
        if ($postToken && hash_equals($sessionToken, $postToken)) {
            return true;
        }

        // Check query parameter (less secure, but sometimes needed)
        $queryToken = $request->get('_csrf_token');
        if ($queryToken && hash_equals($sessionToken, $queryToken)) {
            return true;
        }

        return false;
    }

    /**
     * Get CSRF token from session
     *
     * @return string|null
     */
    private function getSessionToken(): ?string
    {
        $tokenData = Session::get(self::TOKEN_NAME);
        return $tokenData['token'] ?? null;
    }

    /**
     * Handle CSRF validation failure
     *
     * @param Response $response Response object
     * @return mixed
     */
    private function handleCsrfFailure(Response $response)
    {
        // Log the security event
        error_log('CSRF validation failed for request: ' . $_SERVER['REQUEST_URI'] .
                  ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        $response->setStatusCode(403);

        if ($this->isApiRequest()) {
            return $response->json([
                'error' => 'CSRF token validation failed',
                'message' => 'Invalid or missing CSRF token'
            ], 403);
        }

        return $response->html(
            '<h1>403 Forbidden</h1><p>CSRF token validation failed. Please refresh the page and try again.</p>',
            403
        );
    }

    /**
     * Add CSRF token to response
     *
     * @param Response $response Response object
     * @return void
     */
    private function addTokenToResponse(Response $response): void
    {
        $token = $this->getSessionToken();
        if ($token) {
            // Add to response headers for AJAX requests
            $response->setHeader(self::HEADER_NAME, $token);

            // Add to response data for template rendering
            $response->setHeader('X-CSRF-Token-Available', 'true');
        }
    }

    /**
     * Check if request is an API request
     *
     * @return bool
     */
    private function isApiRequest(): bool
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 ||
               isset($_SERVER['HTTP_ACCEPT']) &&
               strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }

    /**
     * Get current CSRF token (for forms/templates)
     *
     * @return string|null
     */
    public static function getToken(): ?string
    {
        $tokenData = Session::get(self::TOKEN_NAME);
        return $tokenData['token'] ?? null;
    }

    /**
     * Generate CSRF token field for forms
     *
     * @return string HTML input field
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        if (!$token) {
            return '';
        }

        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Generate CSRF meta tag for AJAX requests
     *
     * @return string HTML meta tag
     */
    public static function getTokenMeta(): string
    {
        $token = self::getToken();
        if (!$token) {
            return '';
        }

        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Validate token manually (for custom validation)
     *
     * @param string $token Token to validate
     * @return bool
     */
    public static function validateTokenManually(string $token): bool
    {
        $sessionToken = self::getToken();
        return $sessionToken && hash_equals($sessionToken, $token);
    }
}

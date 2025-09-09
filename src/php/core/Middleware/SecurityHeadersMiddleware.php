<?php
/**
 * TPT Government Platform - Security Headers Middleware
 *
 * Adds comprehensive security headers to HTTP responses.
 */

namespace Core\Middleware;

use Core\Request;
use Core\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Default security headers
     */
    private array $defaultHeaders = [
        // Prevent clickjacking
        'X-Frame-Options' => 'SAMEORIGIN',

        // Prevent MIME type sniffing
        'X-Content-Type-Options' => 'nosniff',

        // Enable XSS protection
        'X-XSS-Protection' => '1; mode=block',

        // Referrer policy
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Content Security Policy (customizable)
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';",

        // HTTP Strict Transport Security (only for HTTPS)
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',

        // Feature policy / Permissions policy
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), speaker=(), fullscreen=(), payment=()',

        // Remove server information
        'X-Powered-By' => '',

        // Prevent caching of sensitive content
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ];

    /**
     * API-specific headers
     */
    private array $apiHeaders = [
        // CORS headers (basic)
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token',
        'Access-Control-Max-Age' => '86400',

        // API-specific security
        'X-API-Version' => '1.0',
    ];

    /**
     * Headers to skip for certain content types
     */
    private array $skipHeadersForContentTypes = [
        'Cache-Control',
        'Pragma',
        'Expires'
    ];

    /**
     * Handle security headers
     *
     * @param Request $request Request object
     * @param Response $response Response object
     * @param callable $next Next middleware
     * @return mixed
     */
    public function handle($request, $response, callable $next)
    {
        // Execute next middleware first to get the response
        $result = $next($request, $response);

        // Add security headers to response
        $this->addSecurityHeaders($request, $response);

        return $result;
    }

    /**
     * Add security headers to response
     *
     * @param Request $request Request object
     * @param Response $response Response object
     * @return void
     */
    private function addSecurityHeaders(Request $request, Response $response): void
    {
        $headers = $this->getHeadersForRequest($request);

        foreach ($headers as $name => $value) {
            if ($this->shouldAddHeader($name, $response)) {
                $response->setHeader($name, $value);
            }
        }
    }

    /**
     * Get appropriate headers for the request
     *
     * @param Request $request Request object
     * @return array Headers array
     */
    private function getHeadersForRequest(Request $request): array
    {
        $headers = $this->defaultHeaders;

        // Add API-specific headers for API requests
        if ($this->isApiRequest($request)) {
            $headers = array_merge($headers, $this->apiHeaders);
        }

        // Customize CSP for development vs production
        if ($this->isDevelopment()) {
            $headers['Content-Security-Policy'] = str_replace(
                "'unsafe-eval'",
                "'unsafe-eval' localhost:* 127.0.0.1:*",
                $headers['Content-Security-Policy']
            );
        }

        // Only add HSTS for HTTPS
        if (!$this->isHttps()) {
            unset($headers['Strict-Transport-Security']);
        }

        return $headers;
    }

    /**
     * Check if header should be added
     *
     * @param string $headerName Header name
     * @param Response $response Response object
     * @return bool
     */
    private function shouldAddHeader(string $headerName, Response $response): bool
    {
        // Skip certain headers for specific content types
        if (in_array($headerName, $this->skipHeadersForContentTypes)) {
            $contentType = $response->getContentType();
            if ($this->shouldSkipCacheHeaders($contentType)) {
                return false;
            }
        }

        // Skip if header is already set
        return !$response->hasHeader($headerName);
    }

    /**
     * Check if cache headers should be skipped
     *
     * @param string $contentType Content type
     * @return bool
     */
    private function shouldSkipCacheHeaders(string $contentType): bool
    {
        // Skip cache headers for API responses and dynamic content
        return strpos($contentType, 'application/json') !== false ||
               strpos($contentType, 'text/html') !== false;
    }

    /**
     * Check if request is an API request
     *
     * @param Request $request Request object
     * @return bool
     */
    private function isApiRequest(Request $request): bool
    {
        $path = $request->getPath();
        return strpos($path, '/api/') === 0;
    }

    /**
     * Check if running in development mode
     *
     * @return bool
     */
    private function isDevelopment(): bool
    {
        return getenv('APP_ENV') === 'development' ||
               getenv('APP_DEBUG') === 'true' ||
               !isset($_SERVER['HTTPS']) ||
               $_SERVER['HTTPS'] !== 'on';
    }

    /**
     * Check if request is HTTPS
     *
     * @return bool
     */
    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * Set custom security header
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return void
     */
    public function setHeader(string $name, string $value): void
    {
        $this->defaultHeaders[$name] = $value;
    }

    /**
     * Remove security header
     *
     * @param string $name Header name
     * @return void
     */
    public function removeHeader(string $name): void
    {
        unset($this->defaultHeaders[$name]);
    }

    /**
     * Set Content Security Policy
     *
     * @param string $policy CSP policy string
     * @return void
     */
    public function setCSP(string $policy): void
    {
        $this->defaultHeaders['Content-Security-Policy'] = $policy;
    }

    /**
     * Add CSP directive
     *
     * @param string $directive CSP directive (e.g., 'script-src')
     * @param string $value Directive value
     * @return void
     */
    public function addCSPDirective(string $directive, string $value): void
    {
        $currentCSP = $this->defaultHeaders['Content-Security-Policy'] ?? '';
        $this->defaultHeaders['Content-Security-Policy'] = $currentCSP . '; ' . $directive . ' ' . $value;
    }

    /**
     * Set CORS origin
     *
     * @param string $origin Allowed origin
     * @return void
     */
    public function setCORSOrigin(string $origin): void
    {
        $this->apiHeaders['Access-Control-Allow-Origin'] = $origin;
    }

    /**
     * Set CORS allowed methods
     *
     * @param array $methods Allowed HTTP methods
     * @return void
     */
    public function setCORSMethods(array $methods): void
    {
        $this->apiHeaders['Access-Control-Allow-Methods'] = implode(', ', $methods);
    }

    /**
     * Set CORS allowed headers
     *
     * @param array $headers Allowed headers
     * @return void
     */
    public function setCORSHeaders(array $headers): void
    {
        $this->apiHeaders['Access-Control-Allow-Headers'] = implode(', ', $headers);
    }

    /**
     * Get all configured headers
     *
     * @return array All headers
     */
    public function getAllHeaders(): array
    {
        return array_merge($this->defaultHeaders, $this->apiHeaders);
    }

    /**
     * Reset to default headers
     *
     * @return void
     */
    public function resetToDefaults(): void
    {
        $this->defaultHeaders = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), speaker=(), fullscreen=(), payment=()',
            'X-Powered-By' => '',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        $this->apiHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-Token',
            'Access-Control-Max-Age' => '86400',
            'X-API-Version' => '1.0',
        ];
    }

    /**
     * Generate security headers report
     *
     * @return array Security headers report
     */
    public function generateReport(): array
    {
        return [
            'headers_applied' => count($this->getAllHeaders()),
            'csp_enabled' => isset($this->defaultHeaders['Content-Security-Policy']),
            'hsts_enabled' => isset($this->defaultHeaders['Strict-Transport-Security']),
            'cors_enabled' => isset($this->apiHeaders['Access-Control-Allow-Origin']),
            'security_score' => $this->calculateSecurityScore(),
            'recommendations' => $this->getSecurityRecommendations()
        ];
    }

    /**
     * Calculate security score
     *
     * @return int Security score (0-100)
     */
    private function calculateSecurityScore(): int
    {
        $score = 0;
        $maxScore = 100;

        // Core security headers (40 points)
        $coreHeaders = ['X-Frame-Options', 'X-Content-Type-Options', 'X-XSS-Protection'];
        foreach ($coreHeaders as $header) {
            if (isset($this->defaultHeaders[$header])) {
                $score += 15;
            }
        }

        // Advanced security headers (40 points)
        $advancedHeaders = ['Content-Security-Policy', 'Strict-Transport-Security', 'Referrer-Policy'];
        foreach ($advancedHeaders as $header) {
            if (isset($this->defaultHeaders[$header])) {
                $score += 15;
            }
        }

        // API security (20 points)
        if (isset($this->apiHeaders['Access-Control-Allow-Origin'])) {
            $score += 10;
        }
        if (isset($this->apiHeaders['X-API-Version'])) {
            $score += 10;
        }

        return min($score, $maxScore);
    }

    /**
     * Get security recommendations
     *
     * @return array Recommendations
     */
    private function getSecurityRecommendations(): array
    {
        $recommendations = [];

        if (!isset($this->defaultHeaders['Content-Security-Policy'])) {
            $recommendations[] = 'Enable Content Security Policy (CSP) to prevent XSS attacks';
        }

        if (!isset($this->defaultHeaders['Strict-Transport-Security']) && $this->isHttps()) {
            $recommendations[] = 'Enable HTTP Strict Transport Security (HSTS) for HTTPS';
        }

        if ($this->apiHeaders['Access-Control-Allow-Origin'] === '*') {
            $recommendations[] = 'Restrict CORS origin to specific domains in production';
        }

        if (empty($this->defaultHeaders['Permissions-Policy'])) {
            $recommendations[] = 'Implement Permissions Policy to control browser features';
        }

        return $recommendations;
    }
}

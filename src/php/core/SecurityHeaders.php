<?php
/**
 * TPT Government Platform - Security Headers Manager
 *
 * Comprehensive security headers implementation including CSP, HSTS,
 * security headers, and protection against common web vulnerabilities
 */

namespace TPT\Core;

class SecurityHeaders
{
    /**
     * @var array Security headers configuration
     */
    private array $config;

    /**
     * @var array CSP directives
     */
    private array $cspDirectives = [];

    /**
     * @var array Security headers
     */
    private array $headers = [];

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'csp_enabled' => true,
            'csp_report_only' => false,
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000, // 1 year
            'hsts_include_subdomains' => true,
            'hsts_preload' => false,
            'x_frame_options' => 'DENY',
            'x_content_type_options' => 'nosniff',
            'x_xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => [
                'geolocation' => '()',
                'microphone' => '()',
                'camera' => '()',
                'magnetometer' => '()',
                'gyroscope' => '()',
                'speaker' => '()',
                'fullscreen' => '()',
                'payment' => '()'
            ],
            'cross_origin_embedder_policy' => 'require-corp',
            'cross_origin_opener_policy' => 'same-origin',
            'cross_origin_resource_policy' => 'same-origin',
            'origin_agent_cluster' => '?1'
        ], $config);

        $this->initializeCSP();
        $this->initializeHeaders();
    }

    /**
     * Initialize Content Security Policy
     *
     * @return void
     */
    private function initializeCSP(): void
    {
        $this->cspDirectives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data: https:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'media-src' => "'self'",
            'object-src' => "'none'",
            'frame-src' => "'none'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'upgrade-insecure-requests' => '',
            'block-all-mixed-content' => ''
        ];
    }

    /**
     * Initialize security headers
     *
     * @return void
     */
    private function initializeHeaders(): void
    {
        $this->headers = [];

        // Content Security Policy
        if ($this->config['csp_enabled']) {
            $cspHeader = $this->config['csp_report_only'] ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $this->headers[$cspHeader] = $this->buildCSPHeader();
        }

        // HTTP Strict Transport Security
        if ($this->config['hsts_enabled']) {
            $hstsValue = "max-age={$this->config['hsts_max_age']}";
            if ($this->config['hsts_include_subdomains']) {
                $hstsValue .= '; includeSubDomains';
            }
            if ($this->config['hsts_preload']) {
                $hstsValue .= '; preload';
            }
            $this->headers['Strict-Transport-Security'] = $hstsValue;
        }

        // X-Frame-Options
        if ($this->config['x_frame_options']) {
            $this->headers['X-Frame-Options'] = $this->config['x_frame_options'];
        }

        // X-Content-Type-Options
        if ($this->config['x_content_type_options']) {
            $this->headers['X-Content-Type-Options'] = $this->config['x_content_type_options'];
        }

        // X-XSS-Protection
        if ($this->config['x_xss_protection']) {
            $this->headers['X-XSS-Protection'] = $this->config['x_xss_protection'];
        }

        // Referrer-Policy
        if ($this->config['referrer_policy']) {
            $this->headers['Referrer-Policy'] = $this->config['referrer_policy'];
        }

        // Permissions-Policy
        if (!empty($this->config['permissions_policy'])) {
            $permissions = [];
            foreach ($this->config['permissions_policy'] as $directive => $value) {
                $permissions[] = "{$directive}={$value}";
            }
            $this->headers['Permissions-Policy'] = implode(', ', $permissions);
        }

        // Cross-Origin policies
        if ($this->config['cross_origin_embedder_policy']) {
            $this->headers['Cross-Origin-Embedder-Policy'] = $this->config['cross_origin_embedder_policy'];
        }

        if ($this->config['cross_origin_opener_policy']) {
            $this->headers['Cross-Origin-Opener-Policy'] = $this->config['cross_origin_opener_policy'];
        }

        if ($this->config['cross_origin_resource_policy']) {
            $this->headers['Cross-Origin-Resource-Policy'] = $this->config['cross_origin_resource_policy'];
        }

        if ($this->config['origin_agent_cluster']) {
            $this->headers['Origin-Agent-Cluster'] = $this->config['origin_agent_cluster'];
        }

        // Additional security headers
        $this->headers['X-Permitted-Cross-Domain-Policies'] = 'none';
        $this->headers['X-Download-Options'] = 'noopen';
        $this->headers['X-DNS-Prefetch-Control'] = 'off';
    }

    /**
     * Build CSP header string
     *
     * @return string
     */
    private function buildCSPHeader(): string
    {
        $directives = [];
        foreach ($this->cspDirectives as $directive => $value) {
            if (!empty($value)) {
                $directives[] = "{$directive} {$value}";
            } else {
                $directives[] = $directive;
            }
        }
        return implode('; ', $directives);
    }

    /**
     * Set CSP directive
     *
     * @param string $directive
     * @param string $value
     * @return void
     */
    public function setCSPDirective(string $directive, string $value): void
    {
        $this->cspDirectives[$directive] = $value;
        $this->updateCSPHeader();
    }

    /**
     * Add CSP source to directive
     *
     * @param string $directive
     * @param string $source
     * @return void
     */
    public function addCSPSource(string $directive, string $source): void
    {
        if (!isset($this->cspDirectives[$directive])) {
            $this->cspDirectives[$directive] = "'self'";
        }

        if (strpos($this->cspDirectives[$directive], $source) === false) {
            $this->cspDirectives[$directive] .= " {$source}";
        }

        $this->updateCSPHeader();
    }

    /**
     * Remove CSP source from directive
     *
     * @param string $directive
     * @param string $source
     * @return void
     */
    public function removeCSPSource(string $directive, string $source): void
    {
        if (isset($this->cspDirectives[$directive])) {
            $sources = explode(' ', $this->cspDirectives[$directive]);
            $sources = array_filter($sources, fn($s) => $s !== $source);
            $this->cspDirectives[$directive] = implode(' ', $sources);

            $this->updateCSPHeader();
        }
    }

    /**
     * Update CSP header after directive changes
     *
     * @return void
     */
    private function updateCSPHeader(): void
    {
        if ($this->config['csp_enabled']) {
            $cspHeader = $this->config['csp_report_only'] ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $this->headers[$cspHeader] = $this->buildCSPHeader();
        }
    }

    /**
     * Set security header
     *
     * @param string $header
     * @param string $value
     * @return void
     */
    public function setHeader(string $header, string $value): void
    {
        $this->headers[$header] = $value;
    }

    /**
     * Remove security header
     *
     * @param string $header
     * @return void
     */
    public function removeHeader(string $header): void
    {
        unset($this->headers[$header]);
    }

    /**
     * Get all security headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get CSP directives
     *
     * @return array
     */
    public function getCSPDirectives(): array
    {
        return $this->cspDirectives;
    }

    /**
     * Apply security headers to response
     *
     * @return void
     */
    public function applyHeaders(): void
    {
        foreach ($this->headers as $header => $value) {
            header("{$header}: {$value}");
        }
    }

    /**
     * Generate nonce for CSP
     *
     * @return string
     */
    public function generateNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $this->addCSPSource('script-src', "'nonce-{$nonce}'");
        $this->addCSPSource('style-src', "'nonce-{$nonce}'");
        return $nonce;
    }

    /**
     * Configure CSP for development
     *
     * @return void
     */
    public function configureForDevelopment(): void
    {
        $this->setCSPDirective('script-src', "'self' 'unsafe-inline' 'unsafe-eval' localhost:* 127.0.0.1:*");
        $this->setCSPDirective('style-src', "'self' 'unsafe-inline' localhost:* 127.0.0.1:*");
        $this->setCSPDirective('connect-src', "'self' localhost:* 127.0.0.1:* ws://localhost:* wss://localhost:*");
        $this->setCSPDirective('img-src', "'self' data: localhost:* 127.0.0.1:*");
    }

    /**
     * Configure CSP for production
     *
     * @param array $allowedDomains
     * @return void
     */
    public function configureForProduction(array $allowedDomains = []): void
    {
        $defaultDomains = "'self'";
        $allowedList = $defaultDomains;

        if (!empty($allowedDomains)) {
            $allowedList .= ' ' . implode(' ', $allowedDomains);
        }

        $this->setCSPDirective('script-src', $allowedList);
        $this->setCSPDirective('style-src', $allowedList);
        $this->setCSPDirective('connect-src', $allowedList);
        $this->setCSPDirective('img-src', $allowedList);
        $this->setCSPDirective('font-src', $allowedList);
        $this->setCSPDirective('media-src', $allowedList);
    }

    /**
     * Enable CSP reporting
     *
     * @param string $reportUri
     * @return void
     */
    public function enableCSPReporting(string $reportUri): void
    {
        $this->setCSPDirective('report-uri', $reportUri);
        $this->setCSPDirective('report-to', 'csp-endpoint');
    }

    /**
     * Configure HSTS for preload
     *
     * @return void
     */
    public function enableHSTSPreload(): void
    {
        $this->config['hsts_preload'] = true;
        $this->config['hsts_max_age'] = 63072000; // 2 years for preload
        $this->initializeHeaders();
    }

    /**
     * Get security headers summary
     *
     * @return array
     */
    public function getSecuritySummary(): array
    {
        return [
            'csp_enabled' => $this->config['csp_enabled'],
            'csp_report_only' => $this->config['csp_report_only'],
            'hsts_enabled' => $this->config['hsts_enabled'],
            'hsts_preload' => $this->config['hsts_preload'],
            'total_headers' => count($this->headers),
            'csp_directives_count' => count($this->cspDirectives),
            'headers_list' => array_keys($this->headers),
            'csp_directives_list' => array_keys($this->cspDirectives)
        ];
    }

    /**
     * Validate security headers configuration
     *
     * @return array
     */
    public function validateConfiguration(): array
    {
        $issues = [];
        $warnings = [];
        $recommendations = [];

        // Check CSP configuration
        if ($this->config['csp_enabled']) {
            if (empty($this->cspDirectives['default-src'])) {
                $issues[] = 'CSP default-src directive is not set';
            }

            if (strpos($this->cspDirectives['script-src'] ?? '', 'unsafe-inline') !== false) {
                $warnings[] = 'CSP allows unsafe-inline scripts';
                $recommendations[] = 'Consider using nonces or hashes instead of unsafe-inline';
            }

            if (strpos($this->cspDirectives['style-src'] ?? '', 'unsafe-inline') !== false) {
                $warnings[] = 'CSP allows unsafe-inline styles';
                $recommendations[] = 'Consider using nonces or hashes instead of unsafe-inline';
            }
        }

        // Check HSTS configuration
        if ($this->config['hsts_enabled']) {
            if ($this->config['hsts_max_age'] < 31536000) {
                $warnings[] = 'HSTS max-age is less than 1 year';
                $recommendations[] = 'Consider increasing HSTS max-age to at least 1 year';
            }
        }

        // Check frame options
        if ($this->config['x_frame_options'] !== 'DENY' && $this->config['x_frame_options'] !== 'SAMEORIGIN') {
            $warnings[] = 'X-Frame-Options is not set to DENY or SAMEORIGIN';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Generate security headers for different environments
     *
     * @param string $environment
     * @return array
     */
    public function getEnvironmentHeaders(string $environment): array
    {
        $headers = $this->headers;

        switch ($environment) {
            case 'development':
                // Relax CSP for development
                $headers['Content-Security-Policy'] = str_replace(
                    "'self'",
                    "'self' localhost:* 127.0.0.1:*",
                    $headers['Content-Security-Policy'] ?? ''
                );
                break;

            case 'staging':
                // Moderate security for staging
                if (isset($headers['Strict-Transport-Security'])) {
                    $headers['Strict-Transport-Security'] = 'max-age=86400'; // 1 day for staging
                }
                break;

            case 'production':
                // Maximum security for production
                // Headers are already configured for production
                break;
        }

        return $headers;
    }

    /**
     * Add custom security header
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function addCustomHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Remove CSP directive
     *
     * @param string $directive
     * @return void
     */
    public function removeCSPDirective(string $directive): void
    {
        unset($this->cspDirectives[$directive]);
        $this->updateCSPHeader();
    }

    /**
     * Get CSP violations report structure
     *
     * @return array
     */
    public function getCSPViolationReportStructure(): array
    {
        return [
            'document-uri' => '',
            'violated-directive' => '',
            'effective-directive' => '',
            'original-policy' => '',
            'blocked-uri' => '',
            'status-code' => 0,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ];
    }

    /**
     * Process CSP violation report
     *
     * @param array $violationReport
     * @return array
     */
    public function processCSPViolation(array $violationReport): array
    {
        // Log CSP violation for analysis
        $logEntry = [
            'timestamp' => date('c'),
            'document_uri' => $violationReport['document-uri'] ?? '',
            'violated_directive' => $violationReport['violated-directive'] ?? '',
            'blocked_uri' => $violationReport['blocked-uri'] ?? '',
            'user_agent' => $violationReport['user_agent'] ?? '',
            'ip_address' => $violationReport['ip_address'] ?? '',
            'request_uri' => $violationReport['request_uri'] ?? ''
        ];

        // In production, this would be stored in database or sent to monitoring system
        error_log('CSP Violation: ' . json_encode($logEntry));

        return [
            'processed' => true,
            'violation' => $logEntry,
            'recommendation' => $this->getCSPViolationRecommendation($violationReport)
        ];
    }

    /**
     * Get recommendation for CSP violation
     *
     * @param array $violationReport
     * @return string
     */
    private function getCSPViolationRecommendation(array $violationReport): string
    {
        $directive = $violationReport['violated-directive'] ?? '';
        $blockedUri = $violationReport['blocked-uri'] ?? '';

        if (strpos($directive, 'script-src') !== false) {
            return "Consider adding '{$blockedUri}' to script-src directive or using a nonce/hash";
        }

        if (strpos($directive, 'style-src') !== false) {
            return "Consider adding '{$blockedUri}' to style-src directive or using a nonce/hash";
        }

        if (strpos($directive, 'img-src') !== false) {
            return "Consider adding '{$blockedUri}' to img-src directive";
        }

        return "Review CSP policy for {$directive} directive";
    }

    /**
     * Export security headers configuration
     *
     * @return array
     */
    public function exportConfiguration(): array
    {
        return [
            'config' => $this->config,
            'headers' => $this->headers,
            'csp_directives' => $this->cspDirectives,
            'export_timestamp' => date('c'),
            'version' => '1.0'
        ];
    }

    /**
     * Import security headers configuration
     *
     * @param array $configuration
     * @return void
     */
    public function importConfiguration(array $configuration): void
    {
        if (isset($configuration['config'])) {
            $this->config = array_merge($this->config, $configuration['config']);
        }

        if (isset($configuration['headers'])) {
            $this->headers = $configuration['headers'];
        }

        if (isset($configuration['csp_directives'])) {
            $this->cspDirectives = $configuration['csp_directives'];
        }
    }
}

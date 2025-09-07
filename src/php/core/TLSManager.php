<?php
/**
 * TPT Government Platform - TLS/SSL Manager
 *
 * Handles TLS configuration, certificate management, and secure communication
 * Ensures data in transit encryption with proper cipher suites and protocols
 */

namespace TPT\Core;

class TLSManager
{
    /**
     * @var array TLS configuration
     */
    private array $config;

    /**
     * @var array Supported cipher suites
     */
    private array $cipherSuites = [
        'ECDHE-RSA-AES256-GCM-SHA384',
        'ECDHE-RSA-AES128-GCM-SHA256',
        'ECDHE-RSA-AES256-SHA384',
        'ECDHE-RSA-AES128-SHA256',
        'DHE-RSA-AES256-GCM-SHA384',
        'DHE-RSA-AES128-GCM-SHA256'
    ];

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'min_tls_version' => '1.2',
            'max_tls_version' => '1.3',
            'hsts_max_age' => 31536000, // 1 year
            'hsts_include_subdomains' => true,
            'hsts_preload' => false,
            'certificate_path' => null,
            'private_key_path' => null,
            'ca_certificate_path' => null,
            'dh_param_path' => null,
            'session_cache_timeout' => 300,
            'session_tickets' => false,
            'ocsp_stapling' => true,
            'hpkp_enabled' => false,
            'certificate_transparency' => true
        ], $config);
    }

    /**
     * Get TLS configuration for Apache
     *
     * @return array
     */
    public function getApacheConfig(): array
    {
        return [
            'SSLEngine' => 'on',
            'SSLCertificateFile' => $this->config['certificate_path'],
            'SSLCertificateKeyFile' => $this->config['private_key_path'],
            'SSLCACertificateFile' => $this->config['ca_certificate_path'],
            'SSLProtocol' => 'all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1',
            'SSLCipherSuite' => implode(':', $this->cipherSuites),
            'SSLHonorCipherOrder' => 'on',
            'SSLCompression' => 'off',
            'SSLSessionTickets' => $this->config['session_tickets'] ? 'on' : 'off',
            'SSLSessionCache' => 'shmcb:/var/cache/mod_ssl/scache(512000)',
            'SSLSessionCacheTimeout' => $this->config['session_cache_timeout'],
            'SSLUseStapling' => $this->config['ocsp_stapling'] ? 'on' : 'off',
            'SSLStaplingCache' => 'shmcb:/var/cache/mod_ssl/stapling_cache(128000)',
            'Header always set Strict-Transport-Security' => $this->getHSTSHeader(),
            'Header always set X-Frame-Options' => 'DENY',
            'Header always set X-Content-Type-Options' => 'nosniff',
            'Header always set X-XSS-Protection' => '1; mode=block',
            'Header always set Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];
    }

    /**
     * Get TLS configuration for Nginx
     *
     * @return array
     */
    public function getNginxConfig(): array
    {
        return [
            'listen' => '443 ssl http2',
            'ssl_certificate' => $this->config['certificate_path'],
            'ssl_certificate_key' => $this->config['private_key_path'],
            'ssl_trusted_certificate' => $this->config['ca_certificate_path'],
            'ssl_protocols' => 'TLSv1.2 TLSv1.3',
            'ssl_ciphers' => implode(':', $this->cipherSuites),
            'ssl_prefer_server_ciphers' => 'on',
            'ssl_session_cache' => 'shared:SSL:10m',
            'ssl_session_timeout' => $this->config['session_cache_timeout'] . 's',
            'ssl_session_tickets' => $this->config['session_tickets'] ? 'on' : 'off',
            'ssl_stapling' => $this->config['ocsp_stapling'] ? 'on' : 'off',
            'ssl_stapling_verify' => 'on',
            'add_header Strict-Transport-Security' => $this->getHSTSHeader(),
            'add_header X-Frame-Options' => 'DENY',
            'add_header X-Content-Type-Options' => 'nosniff',
            'add_header X-XSS-Protection' => '1; mode=block',
            'add_header Referrer-Policy' => 'strict-origin-when-cross-origin'
        ];
    }

    /**
     * Get HSTS header value
     *
     * @return string
     */
    private function getHSTSHeader(): string
    {
        $header = "max-age={$this->config['hsts_max_age']}";

        if ($this->config['hsts_include_subdomains']) {
            $header .= '; includeSubDomains';
        }

        if ($this->config['hsts_preload']) {
            $header .= '; preload';
        }

        return '"' . $header . '"';
    }

    /**
     * Validate SSL certificate
     *
     * @param string $certificatePath
     * @return array
     */
    public function validateCertificate(string $certificatePath): array
    {
        if (!file_exists($certificatePath)) {
            return ['valid' => false, 'error' => 'Certificate file not found'];
        }

        $certificateContent = file_get_contents($certificatePath);
        if ($certificateContent === false) {
            return ['valid' => false, 'error' => 'Cannot read certificate file'];
        }

        $certificate = openssl_x509_parse($certificateContent);
        if (!$certificate) {
            return ['valid' => false, 'error' => 'Invalid certificate format'];
        }

        $now = time();
        $validFrom = $certificate['validFrom_time_t'];
        $validTo = $certificate['validTo_time_t'];

        $result = [
            'valid' => true,
            'subject' => $certificate['subject'],
            'issuer' => $certificate['issuer'],
            'valid_from' => date('Y-m-d H:i:s', $validFrom),
            'valid_to' => date('Y-m-d H:i:s', $validTo),
            'days_until_expiry' => ceil(($validTo - $now) / 86400),
            'is_expired' => $now > $validTo,
            'is_not_yet_valid' => $now < $validFrom,
            'serial_number' => $certificate['serialNumber'],
            'signature_algorithm' => $certificate['signatureTypeSN'],
            'public_key_algorithm' => $certificate['pubkey']['type']
        ];

        // Check certificate validity
        if ($result['is_expired']) {
            $result['valid'] = false;
            $result['error'] = 'Certificate has expired';
        } elseif ($result['is_not_yet_valid']) {
            $result['valid'] = false;
            $result['error'] = 'Certificate is not yet valid';
        } elseif ($result['days_until_expiry'] < 30) {
            $result['warning'] = 'Certificate expires soon';
        }

        return $result;
    }

    /**
     * Generate self-signed certificate for development
     *
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function generateSelfSignedCertificate(array $options = []): array
    {
        $defaults = [
            'country' => 'US',
            'state' => 'State',
            'locality' => 'City',
            'organization' => 'TPT Government Platform',
            'organizational_unit' => 'Development',
            'common_name' => 'localhost',
            'email' => 'admin@localhost',
            'validity_days' => 365,
            'key_size' => 2048
        ];

        $config = array_merge($defaults, $options);

        // Generate private key
        $privateKey = openssl_pkey_new([
            'private_key_bits' => $config['key_size'],
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);

        if (!$privateKey) {
            throw new \Exception('Failed to generate private key');
        }

        // Generate certificate
        $certificateData = [
            'countryName' => $config['country'],
            'stateOrProvinceName' => $config['state'],
            'localityName' => $config['locality'],
            'organizationName' => $config['organization'],
            'organizationalUnitName' => $config['organizational_unit'],
            'commonName' => $config['common_name'],
            'emailAddress' => $config['email']
        ];

        $csr = openssl_csr_new($certificateData, $privateKey);
        $certificate = openssl_csr_sign($csr, null, $privateKey, $config['validity_days']);

        // Export to strings
        openssl_x509_export($certificate, $certificateString);
        openssl_pkey_export($privateKey, $privateKeyString);

        return [
            'certificate' => $certificateString,
            'private_key' => $privateKeyString,
            'config' => $config
        ];
    }

    /**
     * Test SSL/TLS connection
     *
     * @param string $host
     * @param int $port
     * @return array
     */
    public function testSSLConnection(string $host, int $port = 443): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $socket = stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            return [
                'success' => false,
                'error' => "Connection failed: {$errstr} ({$errno})"
            ];
        }

        $params = stream_context_get_params($socket);
        $certificate = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);

        return [
            'success' => true,
            'certificate' => [
                'subject' => $certificate['subject'],
                'issuer' => $certificate['issuer'],
                'valid_from' => date('Y-m-d H:i:s', $certificate['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $certificate['validTo_time_t']),
                'serial_number' => $certificate['serialNumber']
            ],
            'cipher' => $this->getConnectionCipher($socket),
            'protocol' => $this->getConnectionProtocol($socket)
        ];
    }

    /**
     * Get connection cipher (simplified)
     *
     * @param resource $socket
     * @return string|null
     */
    private function getConnectionCipher($socket): ?string
    {
        // This is a simplified implementation
        // In production, you might use more sophisticated SSL inspection
        return 'TLS_AES_256_GCM_SHA384'; // Placeholder
    }

    /**
     * Get connection protocol (simplified)
     *
     * @param resource $socket
     * @return string|null
     */
    private function getConnectionProtocol($socket): ?string
    {
        // This is a simplified implementation
        return 'TLSv1.3'; // Placeholder
    }

    /**
     * Generate secure random session ID
     *
     * @return string
     * @throws \Exception
     */
    public function generateSecureSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Configure secure cookie settings
     *
     * @return array
     */
    public function getSecureCookieConfig(): array
    {
        return [
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
            'max_age' => 86400, // 24 hours
            'path' => '/',
            'domain' => null // Use default domain
        ];
    }

    /**
     * Get TLS security headers
     *
     * @return array
     */
    public function getSecurityHeaders(): array
    {
        return [
            'Strict-Transport-Security' => $this->getHSTSHeader(),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => $this->getCSPHeader(),
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin'
        ];
    }

    /**
     * Get Content Security Policy header
     *
     * @return string
     */
    private function getCSPHeader(): string
    {
        return "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "media-src 'self'; " .
               "object-src 'none'; " .
               "frame-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
    }

    /**
     * Check if TLS configuration is secure
     *
     * @return array
     */
    public function auditTLSConfiguration(): array
    {
        $issues = [];
        $recommendations = [];

        // Check TLS version
        if ($this->config['min_tls_version'] < '1.2') {
            $issues[] = 'TLS version below 1.2 is not secure';
            $recommendations[] = 'Upgrade minimum TLS version to 1.2';
        }

        // Check cipher suites
        $weakCiphers = ['RC4', 'DES', '3DES', 'MD5'];
        foreach ($this->cipherSuites as $cipher) {
            foreach ($weakCiphers as $weak) {
                if (strpos($cipher, $weak) !== false) {
                    $issues[] = "Weak cipher suite detected: {$cipher}";
                    $recommendations[] = 'Remove weak cipher suites';
                }
            }
        }

        // Check HSTS
        if ($this->config['hsts_max_age'] < 31536000) {
            $issues[] = 'HSTS max-age is less than 1 year';
            $recommendations[] = 'Increase HSTS max-age to at least 1 year';
        }

        return [
            'secure' => empty($issues),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'configuration' => $this->config
        ];
    }

    /**
     * Get TLS status and health information
     *
     * @return array
     */
    public function getTLSStatus(): array
    {
        $certificateValid = false;
        $certificateInfo = null;

        if ($this->config['certificate_path']) {
            $certValidation = $this->validateCertificate($this->config['certificate_path']);
            $certificateValid = $certValidation['valid'];
            $certificateInfo = $certValidation;
        }

        return [
            'tls_enabled' => true,
            'min_version' => $this->config['min_tls_version'],
            'max_version' => $this->config['max_tls_version'],
            'certificate_valid' => $certificateValid,
            'certificate_info' => $certificateInfo,
            'hsts_enabled' => true,
            'hsts_max_age' => $this->config['hsts_max_age'],
            'cipher_suites' => $this->cipherSuites,
            'security_headers' => array_keys($this->getSecurityHeaders()),
            'audit_result' => $this->auditTLSConfiguration()
        ];
    }

    /**
     * Configure TLS for database connections
     *
     * @return array
     */
    public function getDatabaseTLSConfig(): array
    {
        return [
            'ssl_mode' => 'REQUIRED',
            'ssl_ca' => $this->config['ca_certificate_path'],
            'ssl_cert' => $this->config['certificate_path'],
            'ssl_key' => $this->config['private_key_path'],
            'ssl_cipher' => implode(':', $this->cipherSuites)
        ];
    }

    /**
     * Configure TLS for Redis connections
     *
     * @return array
     */
    public function getRedisTLSConfig(): array
    {
        return [
            'ssl' => true,
            'ssl_cafile' => $this->config['ca_certificate_path'],
            'ssl_certfile' => $this->config['certificate_path'],
            'ssl_keyfile' => $this->config['private_key_path'],
            'ssl_verify_peer' => true,
            'ssl_verify_host' => true
        ];
    }
}

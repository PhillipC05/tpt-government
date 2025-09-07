<?php
/**
 * TPT Government Platform - OAuth 2.0 & OpenID Connect Manager
 *
 * Comprehensive OAuth 2.0 and OpenID Connect implementation for secure
 * authentication and authorization with government identity providers
 */

namespace TPT\Core;

class OAuthManager
{
    /**
     * @var array OAuth configuration
     */
    private array $config;

    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var HttpClient
     */
    private HttpClient $httpClient;

    /**
     * OAuth 2.0 grant types
     */
    const GRANT_TYPES = [
        'authorization_code' => 'Authorization Code',
        'implicit' => 'Implicit',
        'password' => 'Resource Owner Password Credentials',
        'client_credentials' => 'Client Credentials',
        'refresh_token' => 'Refresh Token',
        'urn:ietf:params:oauth:grant-type:jwt-bearer' => 'JWT Bearer',
        'urn:ietf:params:oauth:grant-type:saml2-bearer' => 'SAML 2.0 Bearer'
    ];

    /**
     * OpenID Connect response types
     */
    const RESPONSE_TYPES = [
        'code' => 'Authorization Code',
        'id_token' => 'ID Token',
        'token' => 'Access Token',
        'code id_token' => 'Authorization Code + ID Token',
        'code token' => 'Authorization Code + Access Token',
        'id_token token' => 'ID Token + Access Token',
        'code id_token token' => 'Authorization Code + ID Token + Access Token'
    ];

    /**
     * OAuth scopes
     */
    const SCOPES = [
        'openid' => 'OpenID Connect authentication',
        'profile' => 'User profile information',
        'email' => 'User email address',
        'address' => 'User address information',
        'phone' => 'User phone number',
        'offline_access' => 'Offline access for refresh tokens',
        'gov_identity' => 'Government identity verification',
        'gov_credentials' => 'Government credentials access',
        'gov_services' => 'Government services access'
    ];

    /**
     * Constructor
     *
     * @param Database $database
     * @param HttpClient $httpClient
     * @param array $config
     */
    public function __construct(Database $database, HttpClient $httpClient, array $config = [])
    {
        $this->database = $database;
        $this->httpClient = $httpClient;
        $this->config = array_merge([
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',
            'authorization_endpoint' => '',
            'token_endpoint' => '',
            'userinfo_endpoint' => '',
            'end_session_endpoint' => '',
            'jwks_uri' => '',
            'issuer' => '',
            'scopes' => ['openid', 'profile', 'email'],
            'response_type' => 'code',
            'grant_type' => 'authorization_code',
            'pkce_enabled' => true,
            'state_parameter' => true,
            'nonce_parameter' => true,
            'token_storage' => 'session', // session, database, jwt
            'token_encryption' => true,
            'refresh_token_rotation' => true,
            'access_token_lifetime' => 3600, // 1 hour
            'refresh_token_lifetime' => 2592000, // 30 days
            'id_token_lifetime' => 3600, // 1 hour
            'max_login_attempts' => 5,
            'login_attempt_window' => 300, // 5 minutes
            'device_flow_enabled' => false,
            'backchannel_logout_enabled' => true
        ], $config);
    }

    /**
     * Generate authorization URL for OAuth 2.0 flow
     *
     * @param array $additionalParams
     * @return array
     */
    public function getAuthorizationUrl(array $additionalParams = []): array
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'response_type' => $this->config['response_type'],
            'scope' => implode(' ', $this->config['scopes']),
            'redirect_uri' => $this->config['redirect_uri']
        ];

        // Add state parameter for CSRF protection
        if ($this->config['state_parameter']) {
            $params['state'] = $this->generateState();
        }

        // Add nonce for OpenID Connect
        if ($this->config['nonce_parameter'] && in_array('openid', $this->config['scopes'])) {
            $params['nonce'] = $this->generateNonce();
        }

        // Add PKCE challenge
        if ($this->config['pkce_enabled']) {
            $pkce = $this->generatePKCE();
            $params['code_challenge'] = $pkce['challenge'];
            $params['code_challenge_method'] = $pkce['method'];
        }

        // Merge additional parameters
        $params = array_merge($params, $additionalParams);

        $queryString = http_build_query($params);
        $authorizationUrl = $this->config['authorization_endpoint'] . '?' . $queryString;

        return [
            'url' => $authorizationUrl,
            'params' => $params,
            'state' => $params['state'] ?? null,
            'nonce' => $params['nonce'] ?? null,
            'pkce_verifier' => $pkce['verifier'] ?? null
        ];
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code
     * @param string|null $state
     * @param string|null $pkceVerifier
     * @return array
     */
    public function exchangeCodeForToken(string $code, ?string $state = null, ?string $pkceVerifier = null): array
    {
        try {
            // Validate state parameter
            if ($this->config['state_parameter'] && !$this->validateState($state)) {
                throw new \Exception('Invalid state parameter');
            }

            $params = [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->config['redirect_uri'],
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret']
            ];

            // Add PKCE verifier
            if ($this->config['pkce_enabled'] && $pkceVerifier) {
                $params['code_verifier'] = $pkceVerifier;
            }

            $response = $this->httpClient->post($this->config['token_endpoint'], [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret'])
                ],
                'body' => http_build_query($params)
            ]);

            if ($response['status'] !== 200) {
                throw new \Exception('Token exchange failed: ' . ($response['body'] ?? 'Unknown error'));
            }

            $tokenData = json_decode($response['body'], true);
            if (!$tokenData) {
                throw new \Exception('Invalid token response');
            }

            // Store tokens
            $this->storeTokens($tokenData);

            // Validate ID token if present
            if (isset($tokenData['id_token'])) {
                $this->validateIdToken($tokenData['id_token']);
            }

            return [
                'success' => true,
                'tokens' => $tokenData,
                'user_info' => $this->getUserInfo($tokenData['access_token'] ?? null)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user information from userinfo endpoint
     *
     * @param string|null $accessToken
     * @return array|null
     */
    public function getUserInfo(?string $accessToken): ?array
    {
        if (!$accessToken || !$this->config['userinfo_endpoint']) {
            return null;
        }

        try {
            $response = $this->httpClient->get($this->config['userinfo_endpoint'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);

            if ($response['status'] !== 200) {
                return null;
            }

            return json_decode($response['body'], true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Refresh access token
     *
     * @param string $refreshToken
     * @return array
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $params = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret']
            ];

            $response = $this->httpClient->post($this->config['token_endpoint'], [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => http_build_query($params)
            ]);

            if ($response['status'] !== 200) {
                throw new \Exception('Token refresh failed');
            }

            $tokenData = json_decode($response['body'], true);
            if (!$tokenData) {
                throw new \Exception('Invalid refresh response');
            }

            // Store new tokens
            $this->storeTokens($tokenData);

            // Rotate refresh token if enabled
            if ($this->config['refresh_token_rotation'] && isset($tokenData['refresh_token'])) {
                $this->rotateRefreshToken($refreshToken, $tokenData['refresh_token']);
            }

            return [
                'success' => true,
                'tokens' => $tokenData
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate ID token
     *
     * @param string $idToken
     * @return array
     */
    public function validateIdToken(string $idToken): array
    {
        try {
            $parts = explode('.', $idToken);
            if (count($parts) !== 3) {
                throw new \Exception('Invalid ID token format');
            }

            $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

            // Validate issuer
            if ($payload['iss'] !== $this->config['issuer']) {
                throw new \Exception('Invalid token issuer');
            }

            // Validate audience
            if (!in_array($this->config['client_id'], (array) $payload['aud'])) {
                throw new \Exception('Invalid token audience');
            }

            // Validate expiration
            if ($payload['exp'] < time()) {
                throw new \Exception('Token has expired');
            }

            // Validate nonce if present
            if (isset($payload['nonce'])) {
                if (!$this->validateNonce($payload['nonce'])) {
                    throw new \Exception('Invalid nonce');
                }
            }

            return [
                'valid' => true,
                'payload' => $payload,
                'header' => $header
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Revoke token
     *
     * @param string $token
     * @param string $tokenTypeHint
     * @return bool
     */
    public function revokeToken(string $token, string $tokenTypeHint = 'access_token'): bool
    {
        try {
            $params = [
                'token' => $token,
                'token_type_hint' => $tokenTypeHint,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret']
            ];

            $response = $this->httpClient->post($this->config['revocation_endpoint'] ?? '', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => http_build_query($params)
            ]);

            // Remove from local storage
            $this->removeStoredTokens();

            return $response['status'] === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Initiate logout
     *
     * @param string|null $idTokenHint
     * @param string|null $postLogoutRedirectUri
     * @param string|null $state
     * @return array
     */
    public function initiateLogout(?string $idTokenHint = null, ?string $postLogoutRedirectUri = null, ?string $state = null): array
    {
        if (!$this->config['end_session_endpoint']) {
            return [
                'success' => false,
                'error' => 'End session endpoint not configured'
            ];
        }

        $params = [];
        if ($idTokenHint) {
            $params['id_token_hint'] = $idTokenHint;
        }
        if ($postLogoutRedirectUri) {
            $params['post_logout_redirect_uri'] = $postLogoutRedirectUri;
        }
        if ($state) {
            $params['state'] = $state;
        }

        $logoutUrl = $this->config['end_session_endpoint'];
        if (!empty($params)) {
            $logoutUrl .= '?' . http_build_query($params);
        }

        // Clear local session
        $this->clearSession();

        return [
            'success' => true,
            'logout_url' => $logoutUrl,
            'params' => $params
        ];
    }

    /**
     * Generate PKCE challenge and verifier
     *
     * @return array
     */
    private function generatePKCE(): array
    {
        $verifier = bin2hex(random_bytes(32));
        $challenge = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', $verifier, true)));

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
            'method' => 'S256'
        ];
    }

    /**
     * Generate state parameter
     *
     * @return string
     */
    private function generateState(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        return $state;
    }

    /**
     * Validate state parameter
     *
     * @param string|null $state
     * @return bool
     */
    private function validateState(?string $state): bool
    {
        if (!$state) {
            return false;
        }

        $storedState = $_SESSION['oauth_state'] ?? null;
        unset($_SESSION['oauth_state']);

        return hash_equals($storedState, $state);
    }

    /**
     * Generate nonce parameter
     *
     * @return string
     */
    private function generateNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['oauth_nonce'] = $nonce;
        return $nonce;
    }

    /**
     * Validate nonce parameter
     *
     * @param string $nonce
     * @return bool
     */
    private function validateNonce(string $nonce): bool
    {
        $storedNonce = $_SESSION['oauth_nonce'] ?? null;
        unset($_SESSION['oauth_nonce']);

        return hash_equals($storedNonce, $nonce);
    }

    /**
     * Store tokens based on configuration
     *
     * @param array $tokenData
     * @return void
     */
    private function storeTokens(array $tokenData): void
    {
        switch ($this->config['token_storage']) {
            case 'session':
                $this->storeTokensInSession($tokenData);
                break;
            case 'database':
                $this->storeTokensInDatabase($tokenData);
                break;
            case 'jwt':
                $this->storeTokensAsJWT($tokenData);
                break;
        }
    }

    /**
     * Store tokens in session
     *
     * @param array $tokenData
     * @return void
     */
    private function storeTokensInSession(array $tokenData): void
    {
        $_SESSION['oauth_tokens'] = $tokenData;
        $_SESSION['oauth_token_expires'] = time() + ($tokenData['expires_in'] ?? 3600);
    }

    /**
     * Store tokens in database
     *
     * @param array $tokenData
     * @return void
     */
    private function storeTokensInDatabase(array $tokenData): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return;
            }

            $this->database->execute("
                INSERT INTO oauth_tokens (
                    user_id, access_token, refresh_token, id_token,
                    token_type, expires_at, scope, created_at
                ) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, NOW())
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    id_token = VALUES(id_token),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()
            ", [
                $userId,
                $tokenData['access_token'] ?? null,
                $tokenData['refresh_token'] ?? null,
                $tokenData['id_token'] ?? null,
                $tokenData['token_type'] ?? 'Bearer',
                $tokenData['expires_in'] ?? 3600,
                $tokenData['scope'] ?? null
            ]);
        } catch (\Exception $e) {
            error_log('Failed to store OAuth tokens in database: ' . $e->getMessage());
        }
    }

    /**
     * Store tokens as JWT
     *
     * @param array $tokenData
     * @return void
     */
    private function storeTokensAsJWT(array $tokenData): void
    {
        // Implementation for JWT-based token storage
        // This would create a JWT containing the token data
        $_SESSION['oauth_jwt'] = $this->createTokenJWT($tokenData);
    }

    /**
     * Remove stored tokens
     *
     * @return void
     */
    private function removeStoredTokens(): void
    {
        unset($_SESSION['oauth_tokens'], $_SESSION['oauth_token_expires'], $_SESSION['oauth_jwt']);

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $this->database->execute("DELETE FROM oauth_tokens WHERE user_id = ?", [$userId]);
            }
        } catch (\Exception $e) {
            error_log('Failed to remove stored OAuth tokens: ' . $e->getMessage());
        }
    }

    /**
     * Clear OAuth session
     *
     * @return void
     */
    private function clearSession(): void
    {
        unset(
            $_SESSION['oauth_state'],
            $_SESSION['oauth_nonce'],
            $_SESSION['oauth_tokens'],
            $_SESSION['oauth_token_expires'],
            $_SESSION['oauth_jwt']
        );
    }

    /**
     * Rotate refresh token
     *
     * @param string $oldToken
     * @param string $newToken
     * @return void
     */
    private function rotateRefreshToken(string $oldToken, string $newToken): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $this->database->execute("
                    UPDATE oauth_tokens
                    SET refresh_token = ?, rotated_at = NOW()
                    WHERE user_id = ? AND refresh_token = ?
                ", [$newToken, $userId, $oldToken]);
            }
        } catch (\Exception $e) {
            error_log('Failed to rotate refresh token: ' . $e->getMessage());
        }
    }

    /**
     * Create JWT for token storage
     *
     * @param array $tokenData
     * @return string
     */
    private function createTokenJWT(array $tokenData): string
    {
        // Simplified JWT creation - in production, use a proper JWT library
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'tokens' => $tokenData,
            'iat' => time(),
            'exp' => time() + 3600
        ]);

        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $this->config['client_secret']);
        $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Get current token status
     *
     * @return array
     */
    public function getTokenStatus(): array
    {
        $tokens = $_SESSION['oauth_tokens'] ?? null;
        $expires = $_SESSION['oauth_token_expires'] ?? 0;

        return [
            'has_tokens' => !empty($tokens),
            'access_token_expires' => $expires,
            'is_expired' => time() > $expires,
            'time_to_expiry' => max(0, $expires - time()),
            'scopes' => $tokens['scope'] ?? null,
            'token_type' => $tokens['token_type'] ?? null
        ];
    }

    /**
     * Configure for government identity provider
     *
     * @param array $govConfig
     * @return void
     */
    public function configureForGovernmentIdP(array $govConfig): void
    {
        $this->config = array_merge($this->config, [
            'scopes' => array_merge($this->config['scopes'], ['gov_identity', 'gov_credentials']),
            'response_type' => 'code id_token',
            'token_storage' => 'database',
            'token_encryption' => true,
            'backchannel_logout_enabled' => true,
            'max_login_attempts' => 3,
            'login_attempt_window' => 600 // 10 minutes
        ], $govConfig);
    }

    /**
     * Handle backchannel logout
     *
     * @param string $logoutToken
     * @return bool
     */
    public function handleBackchannelLogout(string $logoutToken): bool
    {
        if (!$this->config['backchannel_logout_enabled']) {
            return false;
        }

        try {
            // Validate logout token (simplified)
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], explode('.', $logoutToken)[1])), true);

            if (!$payload || !isset($payload['sub'])) {
                return false;
            }

            // Find and logout user
            $userId = $payload['sub'];
            $this->database->execute("DELETE FROM oauth_tokens WHERE user_id = ?", [$userId]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get OAuth configuration summary
     *
     * @return array
     */
    public function getConfigurationSummary(): array
    {
        return [
            'client_id' => substr($this->config['client_id'], 0, 8) . '...',
            'authorization_endpoint' => $this->config['authorization_endpoint'],
            'token_endpoint' => $this->config['token_endpoint'],
            'userinfo_endpoint' => $this->config['userinfo_endpoint'],
            'scopes' => $this->config['scopes'],
            'response_type' => $this->config['response_type'],
            'pkce_enabled' => $this->config['pkce_enabled'],
            'token_storage' => $this->config['token_storage'],
            'token_encryption' => $this->config['token_encryption'],
            'backchannel_logout' => $this->config['backchannel_logout_enabled']
        ];
    }
}

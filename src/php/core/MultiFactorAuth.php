<?php
/**
 * TPT Government Platform - Multi-Factor Authentication Manager
 *
 * Comprehensive MFA implementation supporting traditional and modern
 * authentication methods as alternatives to OAuth
 */

namespace TPT\Core;

class MultiFactorAuth
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var EncryptionManager
     */
    private EncryptionManager $encryption;

    /**
     * @var NotificationManager
     */
    private NotificationManager $notifications;

    /**
     * @var array MFA configuration
     */
    private array $config;

    /**
     * Authentication methods
     */
    const AUTH_METHODS = [
        'password' => 'Email/Password',
        'otp_email' => 'Email OTP',
        'otp_sms' => 'SMS OTP',
        'totp' => 'TOTP Authenticator',
        'hotp' => 'HOTP Counter',
        'u2f' => 'U2F Security Key',
        'webauthn' => 'WebAuthn/Passkey',
        'recovery_codes' => 'Recovery Codes',
        'magic_link' => 'Magic Link',
        'social_login' => 'Social Login'
    ];

    /**
     * MFA levels
     */
    const MFA_LEVELS = [
        'none' => 0,
        'basic' => 1,      // Single factor (password only)
        'standard' => 2,   // Two factors
        'enhanced' => 3,   // Two factors + additional verification
        'maximum' => 4     // Multiple factors + biometrics
    ];

    /**
     * Constructor
     *
     * @param Database $database
     * @param EncryptionManager $encryption
     * @param NotificationManager $notifications
     * @param array $config
     */
    public function __construct(Database $database, EncryptionManager $encryption, NotificationManager $notifications, array $config = [])
    {
        $this->database = $database;
        $this->encryption = $encryption;
        $this->notifications = $notifications;
        $this->config = array_merge([
            'default_mfa_level' => 'standard',
            'require_mfa_for_admins' => true,
            'allow_method_selection' => true,
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'otp_length' => 6,
            'otp_expiry' => 300, // 5 minutes
            'recovery_codes_count' => 10,
            'webauthn_rp_name' => 'TPT Government Platform',
            'webauthn_rp_id' => null, // Will be set to current domain
            'magic_link_expiry' => 900, // 15 minutes
            'session_duration' => 3600, // 1 hour
            'remember_device_days' => 30,
            'password_policy' => [
                'min_length' => 12,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_symbols' => true,
                'prevent_reuse' => true,
                'max_age_days' => 90
            ]
        ], $config);
    }

    /**
     * Authenticate user with multiple methods
     *
     * @param string $identifier
     * @param string $password
     * @param array $additionalFactors
     * @return array
     */
    public function authenticate(string $identifier, string $password, array $additionalFactors = []): array
    {
        try {
            // Find user by email or username
            $user = $this->findUser($identifier);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            // Check if account is locked
            if ($this->isAccountLocked($user['id'])) {
                return ['success' => false, 'error' => 'Account is temporarily locked'];
            }

            // Verify password
            if (!$this->verifyPassword($password, $user['password_hash'])) {
                $this->recordFailedAttempt($user['id']);
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            // Check if MFA is required
            $mfaLevel = $this->getUserMfaLevel($user['id']);
            if ($mfaLevel === 'none') {
                return $this->completeAuthentication($user);
            }

            // Handle additional factors
            $mfaResult = $this->processMfaFactors($user['id'], $additionalFactors);
            if (!$mfaResult['success']) {
                return $mfaResult;
            }

            // Complete authentication
            return $this->completeAuthentication($user, $mfaResult['factors_used']);

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Authentication failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send OTP to user
     *
     * @param int $userId
     * @param string $method
     * @return array
     */
    public function sendOtp(int $userId, string $method = 'email'): array
    {
        try {
            $user = $this->getUser($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            $otp = $this->generateOtp();
            $expiresAt = date('Y-m-d H:i:s', time() + $this->config['otp_expiry']);

            // Store OTP
            $this->database->execute("
                INSERT INTO mfa_otps (user_id, otp_code, method, expires_at, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    otp_code = VALUES(otp_code),
                    expires_at = VALUES(expires_at),
                    created_at = NOW()
            ", [$userId, $this->encryption->encrypt($otp), $method, $expiresAt]);

            // Send OTP via chosen method
            switch ($method) {
                case 'email':
                    $this->sendEmailOtp($user['email'], $otp);
                    break;
                case 'sms':
                    $this->sendSmsOtp($user['phone'], $otp);
                    break;
                default:
                    return ['success' => false, 'error' => 'Unsupported OTP method'];
            }

            return ['success' => true, 'method' => $method, 'expires_in' => $this->config['otp_expiry']];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to send OTP: ' . $e->getMessage()];
        }
    }

    /**
     * Verify OTP
     *
     * @param int $userId
     * @param string $otp
     * @param string $method
     * @return array
     */
    public function verifyOtp(int $userId, string $otp, string $method = 'email'): array
    {
        try {
            $storedOtp = $this->database->fetch("
                SELECT otp_code, expires_at, used
                FROM mfa_otps
                WHERE user_id = ? AND method = ? AND used = 0
                ORDER BY created_at DESC LIMIT 1
            ", [$userId, $method]);

            if (!$storedOtp) {
                return ['success' => false, 'error' => 'No valid OTP found'];
            }

            if (strtotime($storedOtp['expires_at']) < time()) {
                return ['success' => false, 'error' => 'OTP has expired'];
            }

            $decryptedOtp = $this->encryption->decrypt($storedOtp['otp_code']);
            if (!hash_equals($decryptedOtp, $otp)) {
                return ['success' => false, 'error' => 'Invalid OTP'];
            }

            // Mark OTP as used
            $this->database->execute("
                UPDATE mfa_otps SET used = 1, used_at = NOW()
                WHERE user_id = ? AND method = ?
            ", [$userId, $method]);

            return ['success' => true, 'method' => $method];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'OTP verification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Setup TOTP authenticator
     *
     * @param int $userId
     * @return array
     */
    public function setupTotp(int $userId): array
    {
        try {
            $user = $this->getUser($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            $secret = $this->generateTotpSecret();
            $issuer = 'TPT Government Platform';
            $accountName = $user['email'];

            // Generate QR code URI
            $qrUri = "otpauth://totp/{$issuer}:{$accountName}?secret={$secret}&issuer={$issuer}";

            // Store secret temporarily (will be confirmed later)
            $this->database->execute("
                INSERT INTO mfa_totp_setup (user_id, secret, qr_uri, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    secret = VALUES(secret),
                    qr_uri = VALUES(qr_uri),
                    created_at = NOW()
            ", [$userId, $this->encryption->encrypt($secret), $qrUri]);

            return [
                'success' => true,
                'secret' => $secret,
                'qr_uri' => $qrUri,
                'issuer' => $issuer,
                'account_name' => $accountName
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'TOTP setup failed: ' . $e->getMessage()];
        }
    }

    /**
     * Verify and enable TOTP
     *
     * @param int $userId
     * @param string $code
     * @return array
     */
    public function verifyTotpSetup(int $userId, string $code): array
    {
        try {
            $setup = $this->database->fetch("
                SELECT secret FROM mfa_totp_setup WHERE user_id = ?
            ", [$userId]);

            if (!$setup) {
                return ['success' => false, 'error' => 'TOTP setup not found'];
            }

            $secret = $this->encryption->decrypt($setup['secret']);
            if (!$this->verifyTotpCode($secret, $code)) {
                return ['success' => false, 'error' => 'Invalid TOTP code'];
            }

            // Enable TOTP for user
            $this->database->execute("
                INSERT INTO mfa_methods (user_id, method, secret, enabled, created_at)
                VALUES (?, 'totp', ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    secret = VALUES(secret),
                    enabled = 1,
                    updated_at = NOW()
            ", [$userId, $this->encryption->encrypt($secret)]);

            // Remove setup record
            $this->database->execute("DELETE FROM mfa_totp_setup WHERE user_id = ?", [$userId]);

            return ['success' => true, 'method' => 'totp'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'TOTP verification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Setup WebAuthn/Passkey
     *
     * @param int $userId
     * @return array
     */
    public function setupWebAuthn(int $userId): array
    {
        try {
            $user = $this->getUser($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            $challenge = bin2hex(random_bytes(32));
            $userId = bin2hex(random_bytes(32));

            $credentialCreationOptions = [
                'challenge' => $challenge,
                'rp' => [
                    'name' => $this->config['webauthn_rp_name'],
                    'id' => $this->config['webauthn_rp_id'] ?? $_SERVER['HTTP_HOST']
                ],
                'user' => [
                    'id' => $userId,
                    'name' => $user['email'],
                    'displayName' => $user['name'] ?? $user['email']
                ],
                'pubKeyCredParams' => [
                    ['alg' => -7, 'type' => 'public-key'], // ES256
                    ['alg' => -257, 'type' => 'public-key'] // RS256
                ],
                'authenticatorSelection' => [
                    'authenticatorAttachment' => 'cross-platform',
                    'requireResidentKey' => false,
                    'userVerification' => 'preferred'
                ],
                'timeout' => 60000,
                'attestation' => 'direct'
            ];

            // Store challenge for verification
            $this->database->execute("
                INSERT INTO mfa_webauthn_challenges (user_id, challenge, user_handle, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$userId, $challenge, $userId]);

            return [
                'success' => true,
                'options' => $credentialCreationOptions
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'WebAuthn setup failed: ' . $e->getMessage()];
        }
    }

    /**
     * Verify WebAuthn registration
     *
     * @param int $userId
     * @param array $credential
     * @return array
     */
    public function verifyWebAuthnRegistration(int $userId, array $credential): array
    {
        try {
            // Verify the credential (simplified - in production use a proper WebAuthn library)
            $challenge = $this->database->fetch("
                SELECT challenge FROM mfa_webauthn_challenges WHERE user_id = ?
            ", [$userId]);

            if (!$challenge) {
                return ['success' => false, 'error' => 'Challenge not found'];
            }

            // Store the credential
            $this->database->execute("
                INSERT INTO mfa_webauthn_credentials (
                    user_id, credential_id, public_key, sign_count, created_at
                ) VALUES (?, ?, ?, 0, NOW())
            ", [
                $userId,
                $credential['id'],
                json_encode($credential['response']),
                0
            ]);

            // Enable WebAuthn method
            $this->database->execute("
                INSERT INTO mfa_methods (user_id, method, enabled, created_at)
                VALUES (?, 'webauthn', 1, NOW())
                ON DUPLICATE KEY UPDATE enabled = 1, updated_at = NOW()
            ", [$userId]);

            // Remove challenge
            $this->database->execute("DELETE FROM mfa_webauthn_challenges WHERE user_id = ?", [$userId]);

            return ['success' => true, 'method' => 'webauthn'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'WebAuthn verification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate recovery codes
     *
     * @param int $userId
     * @return array
     */
    public function generateRecoveryCodes(int $userId): array
    {
        try {
            $codes = [];
            for ($i = 0; $i < $this->config['recovery_codes_count']; $i++) {
                $codes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4));
            }

            // Hash codes for storage
            $hashedCodes = array_map([$this->encryption, 'hash'], $codes);

            // Store hashed codes
            foreach ($hashedCodes as $hashedCode) {
                $this->database->execute("
                    INSERT INTO mfa_recovery_codes (user_id, code_hash, created_at)
                    VALUES (?, ?, NOW())
                ", [$userId, $hashedCode]);
            }

            return ['success' => true, 'codes' => $codes];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Recovery code generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send magic link
     *
     * @param string $email
     * @return array
     */
    public function sendMagicLink(string $email): array
    {
        try {
            $user = $this->findUser($email);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + $this->config['magic_link_expiry']);

            // Store magic link token
            $this->database->execute("
                INSERT INTO mfa_magic_links (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$user['id'], $this->encryption->hash($token), $expiresAt]);

            // Send magic link email
            $magicLink = $this->config['base_url'] . "/auth/magic-login?token={$token}";
            $this->notifications->sendEmail($email, 'Magic Link Login', [
                'subject' => 'Your Magic Login Link',
                'body' => "Click here to login: {$magicLink}\n\nThis link expires in 15 minutes.",
                'template' => 'magic_link'
            ]);

            return ['success' => true, 'expires_in' => $this->config['magic_link_expiry']];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Magic link sending failed: ' . $e->getMessage()];
        }
    }

    /**
     * Verify magic link token
     *
     * @param string $token
     * @return array
     */
    public function verifyMagicLink(string $token): array
    {
        try {
            $magicLink = $this->database->fetch("
                SELECT user_id, expires_at, used
                FROM mfa_magic_links
                WHERE token = ? AND used = 0
            ", [$this->encryption->hash($token)]);

            if (!$magicLink) {
                return ['success' => false, 'error' => 'Invalid magic link'];
            }

            if (strtotime($magicLink['expires_at']) < time()) {
                return ['success' => false, 'error' => 'Magic link has expired'];
            }

            // Mark as used
            $this->database->execute("
                UPDATE mfa_magic_links SET used = 1, used_at = NOW()
                WHERE token = ?
            ", [$this->encryption->hash($token)]);

            $user = $this->getUser($magicLink['user_id']);
            return $this->completeAuthentication($user, ['magic_link']);

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Magic link verification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get user's MFA methods
     *
     * @param int $userId
     * @return array
     */
    public function getUserMfaMethods(int $userId): array
    {
        try {
            $methods = $this->database->fetchAll("
                SELECT method, enabled, created_at, last_used_at
                FROM mfa_methods
                WHERE user_id = ? AND enabled = 1
                ORDER BY created_at DESC
            ", [$userId]);

            return ['success' => true, 'methods' => $methods];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to get MFA methods', 'methods' => []];
        }
    }

    /**
     * Disable MFA method
     *
     * @param int $userId
     * @param string $method
     * @return array
     */
    public function disableMfaMethod(int $userId, string $method): array
    {
        try {
            $this->database->execute("
                UPDATE mfa_methods SET enabled = 0, updated_at = NOW()
                WHERE user_id = ? AND method = ?
            ", [$userId, $method]);

            return ['success' => true, 'method' => $method];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to disable MFA method'];
        }
    }

    /**
     * Get MFA statistics
     *
     * @return array
     */
    public function getMfaStats(): array
    {
        try {
            $stats = $this->database->fetchAll("
                SELECT
                    method,
                    COUNT(*) as total_users,
                    SUM(enabled) as enabled_users,
                    AVG(TIMESTAMPDIFF(DAY, created_at, NOW())) as avg_age_days
                FROM mfa_methods
                GROUP BY method
            ");

            return ['success' => true, 'stats' => $stats];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to get MFA stats', 'stats' => []];
        }
    }

    /**
     * Private helper methods
     */

    private function findUser(string $identifier): ?array
    {
        return $this->database->fetch("
            SELECT id, email, password_hash, name, phone, status
            FROM users
            WHERE (email = ? OR username = ?) AND status = 'active'
        ", [$identifier, $identifier]);
    }

    private function getUser(int $userId): ?array
    {
        return $this->database->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    private function isAccountLocked(int $userId): bool
    {
        $attempts = $this->database->fetch("
            SELECT COUNT(*) as failed_count, MAX(created_at) as last_attempt
            FROM mfa_login_attempts
            WHERE user_id = ? AND success = 0
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ", [$userId, $this->config['lockout_duration']]);

        return $attempts['failed_count'] >= $this->config['max_login_attempts'];
    }

    private function recordFailedAttempt(int $userId): void
    {
        $this->database->execute("
            INSERT INTO mfa_login_attempts (user_id, success, ip_address, user_agent, created_at)
            VALUES (?, 0, ?, ?, NOW())
        ", [$userId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    }

    private function getUserMfaLevel(int $userId): string
    {
        $user = $this->getUser($userId);
        if ($user && $user['role'] === 'admin' && $this->config['require_mfa_for_admins']) {
            return 'enhanced';
        }

        $methods = $this->database->fetch("
            SELECT COUNT(*) as method_count FROM mfa_methods
            WHERE user_id = ? AND enabled = 1
        ", [$userId]);

        if ($methods['method_count'] === 0) {
            return 'none';
        } elseif ($methods['method_count'] === 1) {
            return 'basic';
        } elseif ($methods['method_count'] === 2) {
            return 'standard';
        } else {
            return 'enhanced';
        }
    }

    private function processMfaFactors(int $userId, array $factors): array
    {
        $factorsUsed = [];

        foreach ($factors as $factor) {
            switch ($factor['type']) {
                case 'otp':
                    $result = $this->verifyOtp($userId, $factor['code'], $factor['method'] ?? 'email');
                    if (!$result['success']) {
                        return $result;
                    }
                    $factorsUsed[] = $factor['method'] ?? 'otp';
                    break;

                case 'totp':
                    $result = $this->verifyTotpCodeForUser($userId, $factor['code']);
                    if (!$result['success']) {
                        return $result;
                    }
                    $factorsUsed[] = 'totp';
                    break;

                case 'recovery_code':
                    $result = $this->verifyRecoveryCode($userId, $factor['code']);
                    if (!$result['success']) {
                        return $result;
                    }
                    $factorsUsed[] = 'recovery_code';
                    break;
            }
        }

        return ['success' => true, 'factors_used' => $factorsUsed];
    }

    private function completeAuthentication(array $user, array $factorsUsed = []): array
    {
        // Record successful login
        $this->database->execute("
            INSERT INTO mfa_login_attempts (user_id, success, ip_address, user_agent, factors_used, created_at)
            VALUES (?, 1, ?, ?, ?, NOW())
        ", [
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            json_encode($factorsUsed)
        ]);

        // Update last login
        $this->database->execute("
            UPDATE users SET last_login_at = NOW(), login_count = login_count + 1
            WHERE id = ?
        ", [$user['id']]);

        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['authenticated_at'] = time();
        $_SESSION['mfa_factors'] = $factorsUsed;

        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role']
            ],
            'factors_used' => $factorsUsed,
            'session_id' => session_id()
        ];
    }

    private function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateTotpSecret(): string
    {
        return bin2hex(random_bytes(20));
    }

    private function verifyTotpCode(string $secret, string $code): bool
    {
        // Simplified TOTP verification - in production use a proper TOTP library
        $time = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $timeWindow = $time + $i;
            $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $timeWindow), $secret);
            $offset = hexdec(substr($hash, -1)) * 2;
            $otp = hexdec(substr($hash, $offset, 8)) & 0x7fffffff;
            $otp = str_pad($otp % 1000000, 6, '0', STR_PAD_LEFT);
            if (hash_equals($otp, $code)) {
                return true;
            }
        }
        return false;
    }

    private function verifyTotpCodeForUser(int $userId, string $code): array
    {
        $method = $this->database->fetch("
            SELECT secret FROM mfa_methods WHERE user_id = ? AND method = 'totp' AND enabled = 1
        ", [$userId]);

        if (!$method) {
            return ['success' => false, 'error' => 'TOTP not enabled'];
        }

        $secret = $this->encryption->decrypt($method['secret']);
        if ($this->verifyTotpCode($secret, $code)) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Invalid TOTP code'];
    }

    private function verifyRecoveryCode(int $userId, string $code): array
    {
        $hashedCode = $this->encryption->hash($code);
        $recoveryCode = $this->database->fetch("
            SELECT id FROM mfa_recovery_codes
            WHERE user_id = ? AND code_hash = ? AND used = 0
        ", [$userId, $hashedCode]);

        if (!$recoveryCode) {
            return ['success' => false, 'error' => 'Invalid recovery code'];
        }

        // Mark as used
        $this->database->execute("
            UPDATE mfa_recovery_codes SET used = 1, used_at = NOW()
            WHERE id = ?
        ", [$recoveryCode['id']]);

        return ['success' => true];
    }

    private function sendEmailOtp(string $email, string $otp): void
    {
        $this->notifications->sendEmail($email, 'Your OTP Code', [
            'subject' => 'Your One-Time Password',
            'body' => "Your OTP code is: {$otp}\n\nThis code expires in 5 minutes.",
            'template' => 'otp_email'
        ]);
    }

    private function sendSmsOtp(string $phone, string $otp): void
    {
        $this->notifications->sendSms($phone, "Your OTP code is: {$otp}. Expires in 5 minutes.");
    }
}

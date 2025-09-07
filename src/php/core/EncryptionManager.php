<?php
/**
 * TPT Government Platform - Encryption Manager
 *
 * Handles data encryption at rest and in transit for sensitive data
 * Supports AES-256 encryption with proper key management
 */

namespace TPT\Core;

class EncryptionManager
{
    /**
     * @var string Encryption method
     */
    private string $method = 'aes-256-gcm';

    /**
     * @var string Master encryption key
     */
    private string $masterKey;

    /**
     * @var array Encryption configuration
     */
    private array $config;

    /**
     * Constructor
     *
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'key_rotation_days' => 90,
            'backup_keys' => true,
            'hsm_enabled' => false,
            'key_derivation' => 'pbkdf2',
            'cipher_suites' => [
                'TLS_AES_256_GCM_SHA384',
                'TLS_CHACHA20_POLY1305_SHA256',
                'TLS_AES_128_GCM_SHA256'
            ]
        ], $config);

        $this->initializeMasterKey();
    }

    /**
     * Initialize master encryption key
     *
     * @throws \Exception
     */
    private function initializeMasterKey(): void
    {
        // Check for environment variable first
        $envKey = getenv('ENCRYPTION_MASTER_KEY');
        if ($envKey) {
            $this->masterKey = $envKey;
            return;
        }

        // Check for key file
        $keyFile = $this->config['key_file'] ?? __DIR__ . '/../../config/encryption.key';
        if (file_exists($keyFile)) {
            $this->masterKey = trim(file_get_contents($keyFile));
            return;
        }

        // Generate new key if none exists
        $this->masterKey = $this->generateKey();
        $this->saveKeyToFile($keyFile, $this->masterKey);
    }

    /**
     * Generate a new encryption key
     *
     * @return string
     * @throws \Exception
     */
    public function generateKey(): string
    {
        return bin2hex(random_bytes(32)); // 256-bit key
    }

    /**
     * Save key to file with proper permissions
     *
     * @param string $filePath
     * @param string $key
     * @throws \Exception
     */
    private function saveKeyToFile(string $filePath, string $key): void
    {
        $keyDir = dirname($filePath);
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }

        if (file_put_contents($filePath, $key) === false) {
            throw new \Exception('Failed to save encryption key');
        }

        // Set restrictive permissions
        chmod($filePath, 0600);
        chown($filePath, 'www-data');
        chgrp($filePath, 'www-data');
    }

    /**
     * Encrypt data with AES-256-GCM
     *
     * @param string $data
     * @param string|null $key
     * @return array
     * @throws \Exception
     */
    public function encrypt(string $data, ?string $key = null): array
    {
        $encryptionKey = $key ?? $this->masterKey;
        $iv = random_bytes(16); // 128-bit IV for GCM
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            $this->method,
            hex2bin($encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16 // GCM tag length
        );

        if ($encrypted === false) {
            throw new \Exception('Encryption failed: ' . openssl_error_string());
        }

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'method' => $this->method,
            'timestamp' => time()
        ];
    }

    /**
     * Decrypt data with AES-256-GCM
     *
     * @param array $encryptedData
     * @param string|null $key
     * @return string
     * @throws \Exception
     */
    public function decrypt(array $encryptedData, ?string $key = null): string
    {
        $encryptionKey = $key ?? $this->masterKey;

        $data = base64_decode($encryptedData['data']);
        $iv = base64_decode($encryptedData['iv']);
        $tag = base64_decode($encryptedData['tag']);

        $decrypted = openssl_decrypt(
            $data,
            $encryptedData['method'] ?? $this->method,
            hex2bin($encryptionKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ''
        );

        if ($decrypted === false) {
            throw new \Exception('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Encrypt file content
     *
     * @param string $filePath
     * @param string|null $key
     * @return array
     * @throws \Exception
     */
    public function encryptFile(string $filePath, ?string $key = null): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File does not exist: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception('Failed to read file: ' . $filePath);
        }

        $encrypted = $this->encrypt($content, $key);

        // Add file metadata
        $encrypted['original_filename'] = basename($filePath);
        $encrypted['original_size'] = filesize($filePath);
        $encrypted['mime_type'] = mime_content_type($filePath);

        return $encrypted;
    }

    /**
     * Decrypt file content
     *
     * @param array $encryptedFileData
     * @param string $outputPath
     * @param string|null $key
     * @return bool
     * @throws \Exception
     */
    public function decryptFile(array $encryptedFileData, string $outputPath, ?string $key = null): bool
    {
        $content = $this->decrypt($encryptedFileData, $key);

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        return file_put_contents($outputPath, $content) !== false;
    }

    /**
     * Encrypt database field
     *
     * @param string $value
     * @param string $table
     * @param string $column
     * @return string
     * @throws \Exception
     */
    public function encryptForDatabase(string $value, string $table, string $column): string
    {
        $encrypted = $this->encrypt($value);
        return json_encode($encrypted);
    }

    /**
     * Decrypt database field
     *
     * @param string $encryptedJson
     * @param string $table
     * @param string $column
     * @return string
     * @throws \Exception
     */
    public function decryptFromDatabase(string $encryptedJson, string $table, string $column): string
    {
        $encrypted = json_decode($encryptedJson, true);
        if (!$encrypted) {
            throw new \Exception('Invalid encrypted data format');
        }
        return $this->decrypt($encrypted);
    }

    /**
     * Generate key for specific purpose
     *
     * @param string $purpose
     * @param string $identifier
     * @return string
     */
    public function generatePurposeKey(string $purpose, string $identifier): string
    {
        $context = $purpose . ':' . $identifier;
        return hash_hkdf('sha256', $this->masterKey, 32, $context);
    }

    /**
     * Rotate encryption keys
     *
     * @return array
     * @throws \Exception
     */
    public function rotateKeys(): array
    {
        $oldKey = $this->masterKey;
        $newKey = $this->generateKey();

        // Store old key for decryption of existing data
        $this->storeOldKey($oldKey);

        // Update to new key
        $this->masterKey = $newKey;
        $this->saveKeyToFile($this->config['key_file'] ?? __DIR__ . '/../../config/encryption.key', $newKey);

        return [
            'old_key_hash' => hash('sha256', $oldKey),
            'new_key_hash' => hash('sha256', $newKey),
            'rotation_timestamp' => time()
        ];
    }

    /**
     * Store old key for decryption compatibility
     *
     * @param string $oldKey
     * @throws \Exception
     */
    private function storeOldKey(string $oldKey): void
    {
        $oldKeysFile = $this->config['old_keys_file'] ?? __DIR__ . '/../../config/encryption.old.keys';

        $oldKeys = [];
        if (file_exists($oldKeysFile)) {
            $content = file_get_contents($oldKeysFile);
            $oldKeys = json_decode($content, true) ?? [];
        }

        $oldKeys[] = [
            'key' => $oldKey,
            'hash' => hash('sha256', $oldKey),
            'deprecated_at' => time(),
            'expires_at' => time() + (365 * 24 * 60 * 60) // 1 year expiry
        ];

        // Keep only last 10 old keys
        $oldKeys = array_slice($oldKeys, -10);

        file_put_contents($oldKeysFile, json_encode($oldKeys));
        chmod($oldKeysFile, 0600);
    }

    /**
     * Hash sensitive data (one-way)
     *
     * @param string $data
     * @return string
     */
    public function hashData(string $data): string
    {
        return password_hash($data, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64MB
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Verify hashed data
     *
     * @param string $data
     * @param string $hash
     * @return bool
     */
    public function verifyHash(string $data, string $hash): bool
    {
        return password_verify($data, $hash);
    }

    /**
     * Generate HMAC for data integrity
     *
     * @param string $data
     * @param string|null $key
     * @return string
     */
    public function generateHMAC(string $data, ?string $key = null): string
    {
        $hmacKey = $key ?? $this->masterKey;
        return hash_hmac('sha256', $data, $hmacKey);
    }

    /**
     * Verify HMAC for data integrity
     *
     * @param string $data
     * @param string $hmac
     * @param string|null $key
     * @return bool
     */
    public function verifyHMAC(string $data, string $hmac, ?string $key = null): bool
    {
        $expectedHMAC = $this->generateHMAC($data, $key);
        return hash_equals($expectedHMAC, $hmac);
    }

    /**
     * Encrypt data for transmission (hybrid encryption)
     *
     * @param string $data
     * @return array
     * @throws \Exception
     */
    public function encryptForTransmission(string $data): array
    {
        // Generate session key
        $sessionKey = $this->generateKey();

        // Encrypt data with session key
        $encryptedData = $this->encrypt($data, $sessionKey);

        // Encrypt session key with master key
        $encryptedKey = $this->encrypt($sessionKey);

        return [
            'encrypted_data' => $encryptedData,
            'encrypted_key' => $encryptedKey,
            'timestamp' => time(),
            'protocol' => 'hybrid-aes256'
        ];
    }

    /**
     * Decrypt data from transmission
     *
     * @param array $transmissionData
     * @return string
     * @throws \Exception
     */
    public function decryptFromTransmission(array $transmissionData): string
    {
        // Decrypt session key
        $sessionKey = $this->decrypt($transmissionData['encrypted_key']);

        // Decrypt data with session key
        return $this->decrypt($transmissionData['encrypted_data'], $sessionKey);
    }

    /**
     * Get encryption status and health
     *
     * @return array
     */
    public function getEncryptionStatus(): array
    {
        $keyFile = $this->config['key_file'] ?? __DIR__ . '/../../config/encryption.key';
        $oldKeysFile = $this->config['old_keys_file'] ?? __DIR__ . '/../../config/encryption.old.keys';

        return [
            'encryption_enabled' => true,
            'method' => $this->method,
            'key_file_exists' => file_exists($keyFile),
            'key_file_permissions' => file_exists($keyFile) ? substr(sprintf('%o', fileperms($keyFile)), -4) : null,
            'old_keys_file_exists' => file_exists($oldKeysFile),
            'openssl_available' => function_exists('openssl_encrypt'),
            'mcrypt_available' => function_exists('mcrypt_encrypt'),
            'supported_ciphers' => openssl_get_cipher_methods(),
            'last_key_rotation' => file_exists($keyFile) ? filemtime($keyFile) : null
        ];
    }

    /**
     * Secure random string generation
     *
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Encrypt sensitive configuration values
     *
     * @param array $config
     * @param array $sensitiveKeys
     * @return array
     * @throws \Exception
     */
    public function encryptConfiguration(array $config, array $sensitiveKeys): array
    {
        $encryptedConfig = $config;

        foreach ($sensitiveKeys as $key) {
            if (isset($config[$key])) {
                $encryptedConfig[$key] = $this->encryptForDatabase($config[$key], 'config', $key);
                $encryptedConfig[$key . '_encrypted'] = true;
            }
        }

        return $encryptedConfig;
    }

    /**
     * Decrypt sensitive configuration values
     *
     * @param array $config
     * @param array $sensitiveKeys
     * @return array
     * @throws \Exception
     */
    public function decryptConfiguration(array $config, array $sensitiveKeys): array
    {
        $decryptedConfig = $config;

        foreach ($sensitiveKeys as $key) {
            if (isset($config[$key]) && isset($config[$key . '_encrypted'])) {
                $decryptedConfig[$key] = $this->decryptFromDatabase($config[$key], 'config', $key);
                unset($decryptedConfig[$key . '_encrypted']);
            }
        }

        return $decryptedConfig;
    }
}

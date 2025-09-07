<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Mobile App Companion Manager
 *
 * This class provides comprehensive mobile app companion features including:
 * - Mobile app registration and management
 * - Push notification handling for mobile devices
 * - Offline data synchronization
 * - Mobile-specific API endpoints
 * - Device management and security
 * - Mobile app analytics and usage tracking
 */
class MobileAppManager
{
    private PDO $pdo;
    private array $config;
    private NotificationManager $notificationManager;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'app_name' => 'TPT Government',
            'app_version' => '1.0.0',
            'supported_platforms' => ['ios', 'android', 'web'],
            'max_devices_per_user' => 5,
            'offline_sync_enabled' => true,
            'push_notifications_enabled' => true,
            'biometric_auth_enabled' => true,
            'location_services_enabled' => false,
            'background_refresh_enabled' => true,
            'data_compression_enabled' => true,
            'sync_batch_size' => 100,
            'sync_conflict_resolution' => 'server_wins'
        ], $config);

        $this->notificationManager = new NotificationManager($pdo);
        $this->createMobileTables();
    }

    /**
     * Create mobile app management tables
     */
    private function createMobileTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS mobile_devices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                device_id VARCHAR(255) NOT NULL UNIQUE,
                user_id INT NOT NULL,
                platform ENUM('ios', 'android', 'web') NOT NULL,
                device_model VARCHAR(100) DEFAULT NULL,
                os_version VARCHAR(50) DEFAULT NULL,
                app_version VARCHAR(20) DEFAULT NULL,
                push_token TEXT DEFAULT NULL,
                is_active BOOLEAN DEFAULT true,
                last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_device (device_id),
                INDEX idx_platform (platform),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS mobile_app_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(20) NOT NULL UNIQUE,
                platform ENUM('ios', 'android', 'web') NOT NULL,
                download_url VARCHAR(500) DEFAULT NULL,
                release_notes TEXT DEFAULT NULL,
                is_mandatory BOOLEAN DEFAULT false,
                is_latest BOOLEAN DEFAULT false,
                released_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_platform (platform),
                INDEX idx_latest (is_latest)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS offline_sync_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                device_id VARCHAR(255) NOT NULL,
                user_id INT NOT NULL,
                sync_type ENUM('upload', 'download', 'bidirectional') DEFAULT 'bidirectional',
                data_type VARCHAR(100) NOT NULL,
                data_payload JSON NOT NULL,
                priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                retry_count INT DEFAULT 0,
                max_retries INT DEFAULT 3,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                error_message TEXT DEFAULT NULL,
                INDEX idx_device (device_id),
                INDEX idx_user (user_id),
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS mobile_analytics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                device_id VARCHAR(255) NOT NULL,
                user_id INT DEFAULT NULL,
                event_type VARCHAR(100) NOT NULL,
                event_category VARCHAR(50) NOT NULL,
                event_data JSON DEFAULT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_device (device_id),
                INDEX idx_user (user_id),
                INDEX idx_event (event_type),
                INDEX idx_session (session_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS mobile_app_features (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                feature_name VARCHAR(100) NOT NULL UNIQUE,
                feature_description TEXT DEFAULT NULL,
                platform_support JSON NOT NULL, -- {'ios': true, 'android': true, 'web': false}
                is_enabled BOOLEAN DEFAULT true,
                requires_permission BOOLEAN DEFAULT false,
                permission_name VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_enabled (is_enabled)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create mobile tables: " . $e->getMessage());
        }
    }

    /**
     * Register a mobile device
     */
    public function registerDevice(array $deviceData): array
    {
        try {
            // Validate required fields
            $requiredFields = ['device_id', 'user_id', 'platform'];
            foreach ($requiredFields as $field) {
                if (!isset($deviceData[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            // Check device limit per user
            if (!$this->canRegisterDevice($deviceData['user_id'])) {
                throw new Exception("Maximum devices per user exceeded");
            }

            // Check if device already exists
            $existingDevice = $this->getDeviceById($deviceData['device_id']);
            if ($existingDevice) {
                // Update existing device
                return $this->updateDevice($deviceData['device_id'], $deviceData);
            }

            // Register new device
            $stmt = $this->pdo->prepare("
                INSERT INTO mobile_devices
                (device_id, user_id, platform, device_model, os_version, app_version, push_token)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $deviceData['device_id'],
                $deviceData['user_id'],
                $deviceData['platform'],
                $deviceData['device_model'] ?? null,
                $deviceData['os_version'] ?? null,
                $deviceData['app_version'] ?? null,
                $deviceData['push_token'] ?? null
            ]);

            $deviceId = (int)$this->pdo->lastInsertId();

            // Log device registration
            $this->logMobileEvent($deviceData['device_id'], $deviceData['user_id'], 'device_registered', [
                'platform' => $deviceData['platform'],
                'app_version' => $deviceData['app_version']
            ]);

            return [
                'success' => true,
                'device_id' => $deviceId,
                'message' => 'Device registered successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to register device: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update device information
     */
    public function updateDevice(string $deviceId, array $updateData): array
    {
        try {
            $updateFields = [];
            $params = [];

            $allowedFields = ['device_model', 'os_version', 'app_version', 'push_token', 'is_active'];

            foreach ($updateData as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $value;
                }
            }

            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'error' => 'No valid fields to update'
                ];
            }

            $params[] = $deviceId;
            $updateFields[] = "last_login = NOW()";

            $stmt = $this->pdo->prepare("
                UPDATE mobile_devices
                SET " . implode(', ', $updateFields) . "
                WHERE device_id = ?
            ");

            $stmt->execute($params);

            return [
                'success' => true,
                'message' => 'Device updated successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to update device: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Unregister a device
     */
    public function unregisterDevice(string $deviceId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE mobile_devices
                SET is_active = false
                WHERE device_id = ? AND user_id = ?
            ");

            $stmt->execute([$deviceId, $userId]);

            if ($stmt->rowCount() > 0) {
                // Log device unregistration
                $this->logMobileEvent($deviceId, $userId, 'device_unregistered');

                return [
                    'success' => true,
                    'message' => 'Device unregistered successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Device not found or access denied'
                ];
            }

        } catch (PDOException $e) {
            error_log("Failed to unregister device: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Send push notification to device
     */
    public function sendPushNotification(string $deviceId, array $notificationData): array
    {
        try {
            // Get device information
            $device = $this->getDeviceById($deviceId);
            if (!$device || !$device['is_active']) {
                return [
                    'success' => false,
                    'error' => 'Device not found or inactive'
                ];
            }

            // Prepare notification payload
            $payload = [
                'to' => $device['push_token'],
                'title' => $notificationData['title'] ?? 'TPT Government',
                'body' => $notificationData['body'] ?? '',
                'data' => $notificationData['data'] ?? [],
                'priority' => $notificationData['priority'] ?? 'normal',
                'ttl' => $notificationData['ttl'] ?? 86400 // 24 hours
            ];

            // Send notification based on platform
            $result = $this->sendPlatformNotification($device['platform'], $payload);

            // Log notification
            $this->logMobileEvent($deviceId, $device['user_id'], 'push_notification_sent', [
                'title' => $payload['title'],
                'success' => $result['success']
            ]);

            return $result;

        } catch (Exception $e) {
            error_log("Failed to send push notification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send notification'
            ];
        }
    }

    /**
     * Send push notification to all user devices
     */
    public function sendPushNotificationToUser(int $userId, array $notificationData): array
    {
        try {
            $devices = $this->getUserDevices($userId);
            $results = [];

            foreach ($devices as $device) {
                if ($device['is_active'] && $device['push_token']) {
                    $result = $this->sendPushNotification($device['device_id'], $notificationData);
                    $results[] = [
                        'device_id' => $device['device_id'],
                        'platform' => $device['platform'],
                        'success' => $result['success']
                    ];
                }
            }

            return [
                'success' => true,
                'total_devices' => count($devices),
                'successful_sends' => count(array_filter($results, fn($r) => $r['success'])),
                'results' => $results
            ];

        } catch (Exception $e) {
            error_log("Failed to send user notifications: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send notifications'
            ];
        }
    }

    /**
     * Queue data for offline synchronization
     */
    public function queueOfflineSync(string $deviceId, string $dataType, array $dataPayload, array $options = []): array
    {
        try {
            $options = array_merge([
                'sync_type' => 'bidirectional',
                'priority' => 'medium',
                'user_id' => null
            ], $options);

            // Get user ID from device if not provided
            if (!$options['user_id']) {
                $device = $this->getDeviceById($deviceId);
                if (!$device) {
                    return [
                        'success' => false,
                        'error' => 'Device not found'
                    ];
                }
                $options['user_id'] = $device['user_id'];
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO offline_sync_queue
                (device_id, user_id, sync_type, data_type, data_payload, priority)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $deviceId,
                $options['user_id'],
                $options['sync_type'],
                $dataType,
                json_encode($dataPayload),
                $options['priority']
            ]);

            return [
                'success' => true,
                'sync_id' => (int)$this->pdo->lastInsertId(),
                'message' => 'Data queued for synchronization'
            ];

        } catch (PDOException $e) {
            error_log("Failed to queue offline sync: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Process offline sync queue
     */
    public function processOfflineSync(string $deviceId = null, int $batchSize = null): array
    {
        $batchSize = $batchSize ?? $this->config['sync_batch_size'];

        try {
            // Build query
            $query = "
                SELECT * FROM offline_sync_queue
                WHERE status = 'pending'
                AND retry_count < max_retries
            ";

            $params = [];
            if ($deviceId) {
                $query .= " AND device_id = ?";
                $params[] = $deviceId;
            }

            $query .= " ORDER BY priority DESC, created_at ASC LIMIT ?";

            $stmt = $this->pdo->prepare($query);
            $params[] = $batchSize;
            $stmt->execute($params);

            $syncItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $processed = 0;
            $successful = 0;
            $failed = 0;

            foreach ($syncItems as $item) {
                $result = $this->processSyncItem($item);

                if ($result['success']) {
                    $successful++;
                } else {
                    $failed++;
                }

                $processed++;
            }

            return [
                'success' => true,
                'processed' => $processed,
                'successful' => $successful,
                'failed' => $failed
            ];

        } catch (PDOException $e) {
            error_log("Failed to process offline sync: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Get synchronization data for device
     */
    public function getSyncData(string $deviceId, string $lastSyncTimestamp = null): array
    {
        try {
            $device = $this->getDeviceById($deviceId);
            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found'
                ];
            }

            $syncData = [
                'user_data' => $this->getUserDataForSync($device['user_id'], $lastSyncTimestamp),
                'application_data' => $this->getApplicationDataForSync($lastSyncTimestamp),
                'notifications' => $this->getNotificationsForSync($device['user_id'], $lastSyncTimestamp),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            return [
                'success' => true,
                'data' => $syncData
            ];

        } catch (Exception $e) {
            error_log("Failed to get sync data: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve sync data'
            ];
        }
    }

    /**
     * Check for app updates
     */
    public function checkAppUpdate(string $platform, string $currentVersion): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mobile_app_versions
                WHERE platform = ?
                AND is_latest = true
                LIMIT 1
            ");

            $stmt->execute([$platform]);
            $latestVersion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$latestVersion) {
                return [
                    'update_available' => false,
                    'message' => 'No version information available'
                ];
            }

            $updateAvailable = version_compare($latestVersion['version'], $currentVersion, '>');

            return [
                'update_available' => $updateAvailable,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion['version'],
                'download_url' => $latestVersion['download_url'],
                'release_notes' => $latestVersion['release_notes'],
                'is_mandatory' => (bool)$latestVersion['is_mandatory']
            ];

        } catch (PDOException $e) {
            error_log("Failed to check app update: " . $e->getMessage());
            return [
                'update_available' => false,
                'error' => 'Failed to check for updates'
            ];
        }
    }

    /**
     * Track mobile app analytics
     */
    public function trackMobileEvent(string $deviceId, ?int $userId, string $eventType, array $eventData = []): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO mobile_analytics
                (device_id, user_id, event_type, event_category, event_data, session_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $deviceId,
                $userId,
                $eventType,
                $this->getEventCategory($eventType),
                !empty($eventData) ? json_encode($eventData) : null,
                $eventData['session_id'] ?? null
            ]);

            return true;

        } catch (PDOException $e) {
            error_log("Failed to track mobile event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mobile app statistics
     */
    public function getMobileStats(): array
    {
        try {
            $stats = [];

            // Device statistics
            $stmt = $this->pdo->query("
                SELECT
                    platform,
                    COUNT(*) as total_devices,
                    COUNT(CASE WHEN is_active THEN 1 END) as active_devices
                FROM mobile_devices
                GROUP BY platform
            ");
            $stats['devices_by_platform'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // User engagement
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(DISTINCT user_id) as total_users,
                    COUNT(DISTINCT device_id) as total_devices,
                    AVG(datediff(NOW(), last_login)) as avg_days_since_login
                FROM mobile_devices
                WHERE is_active = true
            ");
            $stats['engagement'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Sync queue status
            $stmt = $this->pdo->query("
                SELECT
                    status,
                    COUNT(*) as count
                FROM offline_sync_queue
                GROUP BY status
            ");
            $stats['sync_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Popular events
            $stmt = $this->pdo->query("
                SELECT
                    event_type,
                    COUNT(*) as count
                FROM mobile_analytics
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['popular_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;

        } catch (PDOException $e) {
            error_log("Failed to get mobile stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Configure mobile app features
     */
    public function configureFeature(string $featureName, array $config): array
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO mobile_app_features
                (feature_name, feature_description, platform_support, is_enabled, requires_permission, permission_name)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                feature_description = VALUES(feature_description),
                platform_support = VALUES(platform_support),
                is_enabled = VALUES(is_enabled),
                requires_permission = VALUES(requires_permission),
                permission_name = VALUES(permission_name)
            ");

            $stmt->execute([
                $featureName,
                $config['description'] ?? null,
                json_encode($config['platform_support'] ?? ['ios' => true, 'android' => true, 'web' => true]),
                $config['enabled'] ?? true,
                $config['requires_permission'] ?? false,
                $config['permission_name'] ?? null
            ]);

            return [
                'success' => true,
                'message' => 'Feature configured successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to configure feature: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Get feature configuration for platform
     */
    public function getFeatureConfig(string $platform): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    feature_name,
                    feature_description,
                    JSON_EXTRACT(platform_support, '$." . $platform . "') as supported,
                    is_enabled,
                    requires_permission,
                    permission_name
                FROM mobile_app_features
                WHERE is_enabled = true
            ");

            $features = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['supported']) {
                    $features[$row['feature_name']] = [
                        'description' => $row['feature_description'],
                        'enabled' => (bool)$row['is_enabled'],
                        'requires_permission' => (bool)$row['requires_permission'],
                        'permission_name' => $row['permission_name']
                    ];
                }
            }

            return [
                'success' => true,
                'platform' => $platform,
                'features' => $features
            ];

        } catch (PDOException $e) {
            error_log("Failed to get feature config: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    // Private helper methods

    private function canRegisterDevice(int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as device_count
            FROM mobile_devices
            WHERE user_id = ? AND is_active = true
        ");
        $stmt->execute([$userId]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['device_count'];

        return $count < $this->config['max_devices_per_user'];
    }

    private function getDeviceById(string $deviceId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM mobile_devices WHERE device_id = ?
        ");
        $stmt->execute([$deviceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getUserDevices(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM mobile_devices
            WHERE user_id = ? AND is_active = true
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function sendPlatformNotification(string $platform, array $payload): array
    {
        // Implementation would vary by platform (FCM for Android, APNS for iOS, etc.)
        // This is a placeholder implementation
        return [
            'success' => true,
            'message_id' => uniqid('msg_'),
            'platform' => $platform
        ];
    }

    private function processSyncItem(array $item): array
    {
        try {
            // Mark as processing
            $this->updateSyncStatus($item['id'], 'processing');

            // Process based on sync type and data type
            $result = $this->processSyncData($item);

            if ($result['success']) {
                $this->updateSyncStatus($item['id'], 'completed');
            } else {
                $this->updateSyncStatus($item['id'], 'failed', $result['error'] ?? 'Unknown error');
            }

            return $result;

        } catch (Exception $e) {
            $this->updateSyncStatus($item['id'], 'failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function updateSyncStatus(int $syncId, string $status, string $errorMessage = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE offline_sync_queue
            SET status = ?, processed_at = NOW(), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $errorMessage, $syncId]);
    }

    private function processSyncData(array $item): array
    {
        // Implementation would handle different data types and sync operations
        // This is a placeholder
        return ['success' => true];
    }

    private function getUserDataForSync(int $userId, ?string $lastSyncTimestamp): array
    {
        // Implementation would retrieve user-specific data for sync
        return [];
    }

    private function getApplicationDataForSync(?string $lastSyncTimestamp): array
    {
        // Implementation would retrieve application data for sync
        return [];
    }

    private function getNotificationsForSync(int $userId, ?string $lastSyncTimestamp): array
    {
        // Implementation would retrieve notifications for sync
        return [];
    }

    private function getEventCategory(string $eventType): string
    {
        $categories = [
            'device_registered' => 'device',
            'device_unregistered' => 'device',
            'push_notification_sent' => 'notification',
            'app_launched' => 'engagement',
            'app_backgrounded' => 'engagement',
            'feature_used' => 'engagement',
            'error_occurred' => 'error'
        ];

        return $categories[$eventType] ?? 'other';
    }

    private function logMobileEvent(string $deviceId, ?int $userId, string $eventType, array $eventData = []): void
    {
        $this->trackMobileEvent($deviceId, $userId, $eventType, $eventData);
    }
}

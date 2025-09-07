<?php
/**
 * TPT Government Platform - Notification Manager
 *
 * Comprehensive notification system supporting multiple channels.
 * Handles email, SMS, push notifications, and in-app notifications.
 */

namespace Core;

class NotificationManager
{
    /**
     * Database instance
     */
    private Database $database;

    /**
     * HTTP client for external services
     */
    private HttpClient $httpClient;

    /**
     * Supported notification channels
     */
    private array $channels = [
        'email' => 'Email',
        'sms' => 'SMS',
        'push' => 'Push Notification',
        'in_app' => 'In-App Notification'
    ];

    /**
     * Notification templates
     */
    private array $templates = [];

    /**
     * Constructor
     */
    public function __construct(Database $database, HttpClient $httpClient)
    {
        $this->database = $database;
        $this->httpClient = $httpClient;
        $this->loadTemplates();
    }

    /**
     * Send notification
     */
    public function send(array $notification): array
    {
        // Validate notification
        $validation = $this->validateNotification($notification);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid notification: ' . implode(', ', $validation['errors'])
            ];
        }

        try {
            $results = [];

            // Send to each channel
            foreach ($notification['channels'] as $channel) {
                $result = $this->sendToChannel($channel, $notification);
                $results[$channel] = $result;
            }

            // Store notification
            $notificationId = $this->storeNotification($notification, $results);

            return [
                'success' => true,
                'notification_id' => $notificationId,
                'results' => $results
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to send notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to specific channel
     */
    private function sendToChannel(string $channel, array $notification): array
    {
        try {
            switch ($channel) {
                case 'email':
                    return $this->sendEmail($notification);
                case 'sms':
                    return $this->sendSMS($notification);
                case 'push':
                    return $this->sendPush($notification);
                case 'in_app':
                    return $this->sendInApp($notification);
                default:
                    return [
                        'success' => false,
                        'error' => "Unsupported channel: {$channel}"
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail(array $notification): array
    {
        // Get email configuration
        $config = $this->getChannelConfig('email');
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Email configuration not found'
            ];
        }

        // Prepare email content
        $content = $this->prepareEmailContent($notification);

        // Send email using configured provider
        $result = $this->sendViaEmailProvider($config, $content, $notification['recipients']);

        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null
        ];
    }

    /**
     * Send SMS notification
     */
    private function sendSMS(array $notification): array
    {
        $config = $this->getChannelConfig('sms');
        if (!$config) {
            return [
                'success' => false,
                'error' => 'SMS configuration not found'
            ];
        }

        $content = $this->prepareSMSContent($notification);
        $result = $this->sendViaSMSProvider($config, $content, $notification['recipients']);

        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null
        ];
    }

    /**
     * Send push notification
     */
    private function sendPush(array $notification): array
    {
        $config = $this->getChannelConfig('push');
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Push notification configuration not found'
            ];
        }

        $content = $this->preparePushContent($notification);
        $result = $this->sendViaPushProvider($config, $content, $notification['recipients']);

        return [
            'success' => $result['success'],
            'message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null
        ];
    }

    /**
     * Send in-app notification
     */
    private function sendInApp(array $notification): array
    {
        try {
            // Store in-app notification in database
            foreach ($notification['recipients'] as $recipient) {
                $this->database->insert('in_app_notifications', [
                    'user_id' => $recipient['id'],
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'type' => $notification['type'] ?? 'info',
                    'data' => json_encode($notification['data'] ?? []),
                    'read' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            return [
                'success' => true,
                'message' => 'In-app notification stored'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prepare email content
     */
    private function prepareEmailContent(array $notification): array
    {
        $template = $this->getTemplate($notification['template'] ?? 'default', 'email');

        $content = [
            'subject' => $this->replacePlaceholders(
                $template['subject'] ?? $notification['title'],
                $notification['data'] ?? []
            ),
            'html_body' => $this->replacePlaceholders(
                $template['html_body'] ?? $notification['message'],
                $notification['data'] ?? []
            ),
            'text_body' => $this->replacePlaceholders(
                $template['text_body'] ?? strip_tags($notification['message']),
                $notification['data'] ?? []
            )
        ];

        return $content;
    }

    /**
     * Prepare SMS content
     */
    private function prepareSMSContent(array $notification): string
    {
        $template = $this->getTemplate($notification['template'] ?? 'default', 'sms');
        $content = $template['body'] ?? $notification['message'];

        return $this->replacePlaceholders($content, $notification['data'] ?? []);
    }

    /**
     * Prepare push notification content
     */
    private function preparePushContent(array $notification): array
    {
        $template = $this->getTemplate($notification['template'] ?? 'default', 'push');

        return [
            'title' => $this->replacePlaceholders(
                $template['title'] ?? $notification['title'],
                $notification['data'] ?? []
            ),
            'body' => $this->replacePlaceholders(
                $template['body'] ?? $notification['message'],
                $notification['data'] ?? []
            ),
            'icon' => $template['icon'] ?? '/icons/icon-192x192.png',
            'badge' => $template['badge'] ?? '/icons/icon-96x96.png',
            'data' => $notification['data'] ?? []
        ];
    }

    /**
     * Replace placeholders in content
     */
    private function replacePlaceholders(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        return $content;
    }

    /**
     * Send email via provider
     */
    private function sendViaEmailProvider(array $config, array $content, array $recipients): array
    {
        // This would integrate with email service providers like SendGrid, Mailgun, etc.
        // For now, return mock success
        return [
            'success' => true,
            'message_id' => 'email_' . uniqid(),
            'recipients' => count($recipients)
        ];
    }

    /**
     * Send SMS via provider
     */
    private function sendViaSMSProvider(array $config, string $content, array $recipients): array
    {
        // This would integrate with SMS providers like Twilio, AWS SNS, etc.
        // For now, return mock success
        return [
            'success' => true,
            'message_id' => 'sms_' . uniqid(),
            'recipients' => count($recipients)
        ];
    }

    /**
     * Send push notification via provider
     */
    private function sendViaPushProvider(array $config, array $content, array $recipients): array
    {
        // This would integrate with push notification services
        // For now, return mock success
        return [
            'success' => true,
            'message_id' => 'push_' . uniqid(),
            'recipients' => count($recipients)
        ];
    }

    /**
     * Store notification in database
     */
    private function storeNotification(array $notification, array $results): int
    {
        return $this->database->insert('notifications', [
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'] ?? 'info',
            'channels' => json_encode($notification['channels']),
            'recipients' => json_encode($notification['recipients']),
            'data' => json_encode($notification['data'] ?? []),
            'results' => json_encode($results),
            'sent_by' => Session::getUserId(),
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get user's notifications
     */
    public function getUserNotifications(int $userId, array $filters = []): array
    {
        try {
            $where = 'user_id = ?';
            $params = [$userId];

            if (isset($filters['read'])) {
                $where .= ' AND read = ?';
                $params[] = $filters['read'];
            }

            if (isset($filters['type'])) {
                $where .= ' AND type = ?';
                $params[] = $filters['type'];
            }

            $notifications = $this->database->select(
                "SELECT * FROM in_app_notifications WHERE {$where} ORDER BY created_at DESC",
                $params
            );

            return [
                'success' => true,
                'notifications' => $notifications
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get notifications: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): array
    {
        try {
            $this->database->update('in_app_notifications', [
                'read' => true,
                'read_at' => date('Y-m-d H:i:s')
            ], [
                'id' => $notificationId,
                'user_id' => $userId
            ]);

            return [
                'success' => true,
                'message' => 'Notification marked as read'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to mark notification as read: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get notification templates
     */
    public function getTemplates(string $channel = null): array
    {
        if ($channel) {
            return array_filter($this->templates, function($template) use ($channel) {
                return $template['channel'] === $channel;
            });
        }

        return $this->templates;
    }

    /**
     * Get specific template
     */
    private function getTemplate(string $name, string $channel): ?array
    {
        foreach ($this->templates as $template) {
            if ($template['name'] === $name && $template['channel'] === $channel) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Load notification templates
     */
    private function loadTemplates(): void
    {
        // Default templates
        $this->templates = [
            [
                'name' => 'default',
                'channel' => 'email',
                'subject' => '{{title}}',
                'html_body' => '<h1>{{title}}</h1><p>{{message}}</p>',
                'text_body' => '{{title}}\n\n{{message}}'
            ],
            [
                'name' => 'default',
                'channel' => 'sms',
                'body' => '{{title}}: {{message}}'
            ],
            [
                'name' => 'default',
                'channel' => 'push',
                'title' => '{{title}}',
                'body' => '{{message}}'
            ],
            [
                'name' => 'task_assigned',
                'channel' => 'email',
                'subject' => 'New Task Assigned: {{task_name}}',
                'html_body' => '<h1>New Task Assigned</h1><p>You have been assigned a new task: <strong>{{task_name}}</strong></p><p>{{task_description}}</p><p>Due date: {{due_date}}</p>',
                'text_body' => 'New Task Assigned: {{task_name}}\n\n{{task_description}}\n\nDue date: {{due_date}}'
            ],
            [
                'name' => 'task_assigned',
                'channel' => 'push',
                'title' => 'New Task Assigned',
                'body' => '{{task_name}} - Due: {{due_date}}'
            ]
        ];
    }

    /**
     * Get channel configuration
     */
    private function getChannelConfig(string $channel): ?array
    {
        try {
            $config = $this->database->selectOne(
                'SELECT config FROM notification_channels WHERE channel = ? AND active = true',
                [$channel]
            );

            return $config ? json_decode($config['config'], true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate notification
     */
    private function validateNotification(array $notification): array
    {
        $errors = [];

        if (!isset($notification['title']) || empty($notification['title'])) {
            $errors[] = 'Title is required';
        }

        if (!isset($notification['message']) || empty($notification['message'])) {
            $errors[] = 'Message is required';
        }

        if (!isset($notification['channels']) || !is_array($notification['channels'])) {
            $errors[] = 'Channels are required';
        } else {
            foreach ($notification['channels'] as $channel) {
                if (!isset($this->channels[$channel])) {
                    $errors[] = "Invalid channel: {$channel}";
                }
            }
        }

        if (!isset($notification['recipients']) || !is_array($notification['recipients'])) {
            $errors[] = 'Recipients are required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get supported channels
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Configure notification channel
     */
    public function configureChannel(string $channel, array $config): array
    {
        if (!isset($this->channels[$channel])) {
            return [
                'success' => false,
                'error' => "Unsupported channel: {$channel}"
            ];
        }

        try {
            $this->database->query(
                "INSERT INTO notification_channels (channel, config, active, updated_at)
                 VALUES (?, ?, true, CURRENT_TIMESTAMP)
                 ON CONFLICT (channel) DO UPDATE SET
                 config = EXCLUDED.config,
                 updated_at = CURRENT_TIMESTAMP",
                [$channel, json_encode($config)]
            );

            return [
                'success' => true,
                'message' => "Channel '{$channel}' configured successfully"
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to configure channel: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk(array $notifications): array
    {
        $results = [];

        foreach ($notifications as $notification) {
            $result = $this->send($notification);
            $results[] = $result;
        }

        $successCount = count(array_filter($results, function($result) {
            return $result['success'];
        }));

        return [
            'success' => true,
            'total_sent' => count($notifications),
            'successful' => $successCount,
            'failed' => count($notifications) - $successCount,
            'results' => $results
        ];
    }
}

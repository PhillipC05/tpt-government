<?php
/**
 * TPT Government Platform - Webhook Manager
 *
 * Comprehensive webhook system for real-time integrations.
 * Supports Zapier integration, custom webhooks, and event-driven notifications.
 */

namespace Core;

class WebhookManager
{
    /**
     * Database instance
     */
    private Database $database;

    /**
     * HTTP client
     */
    private HttpClient $httpClient;

    /**
     * Supported events
     */
    private array $supportedEvents = [
        'user.created' => 'User Created',
        'user.updated' => 'User Updated',
        'user.deleted' => 'User Deleted',
        'application.submitted' => 'Application Submitted',
        'application.approved' => 'Application Approved',
        'application.rejected' => 'Application Rejected',
        'document.uploaded' => 'Document Uploaded',
        'document.processed' => 'Document Processed',
        'workflow.started' => 'Workflow Started',
        'workflow.completed' => 'Workflow Completed',
        'task.assigned' => 'Task Assigned',
        'task.completed' => 'Task Completed',
        'payment.received' => 'Payment Received',
        'notification.sent' => 'Notification Sent'
    ];

    /**
     * Zapier webhook URL pattern
     */
    private const ZAPIER_WEBHOOK_PATTERN = 'https://hooks.zapier.com/hooks/catch/';

    /**
     * Constructor
     */
    public function __construct(Database $database, HttpClient $httpClient)
    {
        $this->database = $database;
        $this->httpClient = $httpClient;
    }

    /**
     * Register webhook
     */
    public function registerWebhook(array $webhookData): array
    {
        // Validate webhook data
        $validation = $this->validateWebhook($webhookData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Invalid webhook: ' . implode(', ', $validation['errors'])
            ];
        }

        try {
            $webhookId = $this->database->insert('webhooks', [
                'name' => $webhookData['name'],
                'url' => $webhookData['url'],
                'events' => json_encode($webhookData['events']),
                'headers' => json_encode($webhookData['headers'] ?? []),
                'secret' => $webhookData['secret'] ?? $this->generateSecret(),
                'active' => $webhookData['active'] ?? true,
                'created_by' => Session::getUserId(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'webhook_id' => $webhookId,
                'secret' => $webhookData['secret'] ?? $this->getWebhookSecret($webhookId),
                'message' => 'Webhook registered successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to register webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update webhook
     */
    public function updateWebhook(int $webhookId, array $webhookData): array
    {
        try {
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $allowedFields = ['name', 'url', 'events', 'headers', 'active'];
            foreach ($allowedFields as $field) {
                if (isset($webhookData[$field])) {
                    $updateData[$field] = is_array($webhookData[$field]) ?
                        json_encode($webhookData[$field]) : $webhookData[$field];
                }
            }

            $this->database->update('webhooks', $updateData, ['id' => $webhookId]);

            return [
                'success' => true,
                'message' => 'Webhook updated successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to update webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(int $webhookId): array
    {
        try {
            $this->database->delete('webhooks', ['id' => $webhookId]);

            return [
                'success' => true,
                'message' => 'Webhook deleted successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to delete webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Trigger webhook event
     */
    public function triggerEvent(string $event, array $data = []): array
    {
        if (!isset($this->supportedEvents[$event])) {
            return [
                'success' => false,
                'error' => "Unsupported event: {$event}"
            ];
        }

        try {
            // Get active webhooks for this event
            $webhooks = $this->getActiveWebhooksForEvent($event);

            if (empty($webhooks)) {
                return [
                    'success' => true,
                    'message' => 'No webhooks registered for this event',
                    'webhooks_triggered' => 0
                ];
            }

            $results = [];
            $successCount = 0;

            foreach ($webhooks as $webhook) {
                $result = $this->sendWebhook($webhook, $event, $data);
                $results[] = $result;

                if ($result['success']) {
                    $successCount++;
                }

                // Log webhook delivery
                $this->logWebhookDelivery($webhook['id'], $event, $result);
            }

            return [
                'success' => true,
                'webhooks_triggered' => count($webhooks),
                'successful_deliveries' => $successCount,
                'failed_deliveries' => count($webhooks) - $successCount,
                'results' => $results
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to trigger webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send webhook to URL
     */
    private function sendWebhook(array $webhook, string $event, array $data): array
    {
        $payload = [
            'event' => $event,
            'timestamp' => date('c'),
            'webhook_id' => $webhook['id'],
            'data' => $data
        ];

        // Add signature if secret exists
        if (!empty($webhook['secret'])) {
            $payload['signature'] = $this->generateSignature($payload, $webhook['secret']);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'TPT-Government-Platform-Webhook/1.0'
        ];

        // Add custom headers
        if (!empty($webhook['headers'])) {
            $customHeaders = json_decode($webhook['headers'], true);
            $headers = array_merge($headers, $customHeaders);
        }

        try {
            $response = $this->httpClient->post($webhook['url'], json_encode($payload), $headers);

            return [
                'success' => $response['success'],
                'http_code' => $response['http_code'] ?? null,
                'response_time' => $response['response_time'] ?? null,
                'error' => $response['error'] ?? null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test webhook
     */
    public function testWebhook(int $webhookId): array
    {
        try {
            $webhook = $this->database->selectOne(
                'SELECT * FROM webhooks WHERE id = ?',
                [$webhookId]
            );

            if (!$webhook) {
                return [
                    'success' => false,
                    'error' => 'Webhook not found'
                ];
            }

            $testData = [
                'test' => true,
                'timestamp' => date('c'),
                'message' => 'This is a test webhook from TPT Government Platform'
            ];

            $result = $this->sendWebhook($webhook, 'test', $testData);

            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Test webhook sent successfully' : 'Test webhook failed',
                'details' => $result
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to test webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get webhooks
     */
    public function getWebhooks(array $filters = []): array
    {
        try {
            $where = '1=1';
            $params = [];

            if (isset($filters['active'])) {
                $where .= ' AND active = ?';
                $params[] = $filters['active'];
            }

            if (isset($filters['created_by'])) {
                $where .= ' AND created_by = ?';
                $params[] = $filters['created_by'];
            }

            $webhooks = $this->database->select(
                "SELECT * FROM webhooks WHERE {$where} ORDER BY created_at DESC",
                $params
            );

            // Decode JSON fields
            foreach ($webhooks as &$webhook) {
                $webhook['events'] = json_decode($webhook['events'], true);
                $webhook['headers'] = json_decode($webhook['headers'], true);
            }

            return [
                'success' => true,
                'webhooks' => $webhooks
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get webhooks: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get webhook by ID
     */
    public function getWebhook(int $webhookId): array
    {
        try {
            $webhook = $this->database->selectOne(
                'SELECT * FROM webhooks WHERE id = ?',
                [$webhookId]
            );

            if (!$webhook) {
                return [
                    'success' => false,
                    'error' => 'Webhook not found'
                ];
            }

            // Decode JSON fields
            $webhook['events'] = json_decode($webhook['events'], true);
            $webhook['headers'] = json_decode($webhook['headers'], true);

            return [
                'success' => true,
                'webhook' => $webhook
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get webhook: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get active webhooks for event
     */
    private function getActiveWebhooksForEvent(string $event): array
    {
        try {
            $webhooks = $this->database->select(
                'SELECT * FROM webhooks WHERE active = true'
            );

            $matchingWebhooks = [];

            foreach ($webhooks as $webhook) {
                $events = json_decode($webhook['events'], true);
                if (in_array($event, $events) || in_array('*', $events)) {
                    $webhook['events'] = $events;
                    $webhook['headers'] = json_decode($webhook['headers'], true);
                    $matchingWebhooks[] = $webhook;
                }
            }

            return $matchingWebhooks;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate webhook secret
     */
    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get webhook secret
     */
    private function getWebhookSecret(int $webhookId): ?string
    {
        try {
            $webhook = $this->database->selectOne(
                'SELECT secret FROM webhooks WHERE id = ?',
                [$webhookId]
            );

            return $webhook ? $webhook['secret'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate signature for webhook payload
     */
    private function generateSignature(array $payload, string $secret): string
    {
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $payloadString, $secret);
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = $this->generateSignature(json_decode($payload, true), $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Log webhook delivery
     */
    private function logWebhookDelivery(int $webhookId, string $event, array $result): void
    {
        try {
            $this->database->insert('webhook_deliveries', [
                'webhook_id' => $webhookId,
                'event' => $event,
                'success' => $result['success'],
                'http_code' => $result['http_code'] ?? null,
                'response_time' => $result['response_time'] ?? null,
                'error_message' => $result['error'] ?? null,
                'delivered_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log webhook delivery: " . $e->getMessage());
        }
    }

    /**
     * Get webhook delivery logs
     */
    public function getDeliveryLogs(int $webhookId, array $filters = []): array
    {
        try {
            $where = 'webhook_id = ?';
            $params = [$webhookId];

            if (isset($filters['success'])) {
                $where .= ' AND success = ?';
                $params[] = $filters['success'];
            }

            if (isset($filters['event'])) {
                $where .= ' AND event = ?';
                $params[] = $filters['event'];
            }

            $logs = $this->database->select(
                "SELECT * FROM webhook_deliveries WHERE {$where} ORDER BY delivered_at DESC LIMIT 100",
                $params
            );

            return [
                'success' => true,
                'logs' => $logs
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get delivery logs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create Zapier webhook
     */
    public function createZapierWebhook(string $name, array $events): array
    {
        // Generate Zapier webhook URL
        $webhookUrl = self::ZAPIER_WEBHOOK_PATTERN . bin2hex(random_bytes(16)) . '/';

        return $this->registerWebhook([
            'name' => $name,
            'url' => $webhookUrl,
            'events' => $events,
            'headers' => [],
            'active' => true
        ]);
    }

    /**
     * Get Zapier integration status
     */
    public function getZapierStatus(): array
    {
        try {
            $zapierWebhooks = $this->database->select(
                "SELECT COUNT(*) as count FROM webhooks WHERE url LIKE ? AND active = true",
                [self::ZAPIER_WEBHOOK_PATTERN . '%']
            );

            return [
                'success' => true,
                'zapier_integrated' => ($zapierWebhooks[0]['count'] ?? 0) > 0,
                'active_webhooks' => $zapierWebhooks[0]['count'] ?? 0
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get Zapier status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get supported events
     */
    public function getSupportedEvents(): array
    {
        return $this->supportedEvents;
    }

    /**
     * Validate webhook data
     */
    private function validateWebhook(array $webhookData): array
    {
        $errors = [];

        if (!isset($webhookData['name']) || empty($webhookData['name'])) {
            $errors[] = 'Name is required';
        }

        if (!isset($webhookData['url']) || empty($webhookData['url'])) {
            $errors[] = 'URL is required';
        } elseif (!filter_var($webhookData['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL format';
        }

        if (!isset($webhookData['events']) || !is_array($webhookData['events'])) {
            $errors[] = 'Events are required';
        } else {
            foreach ($webhookData['events'] as $event) {
                if (!isset($this->supportedEvents[$event]) && $event !== '*') {
                    $errors[] = "Unsupported event: {$event}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Retry failed webhook deliveries
     */
    public function retryFailedDeliveries(int $webhookId, int $limit = 10): array
    {
        try {
            $failedDeliveries = $this->database->select(
                'SELECT * FROM webhook_deliveries WHERE webhook_id = ? AND success = false ORDER BY delivered_at DESC LIMIT ?',
                [$webhookId, $limit]
            );

            $results = [];
            $retryCount = 0;

            foreach ($failedDeliveries as $delivery) {
                // Get webhook details
                $webhook = $this->database->selectOne(
                    'SELECT * FROM webhooks WHERE id = ?',
                    [$webhookId]
                );

                if ($webhook) {
                    // Retry delivery (simplified - would need original payload)
                    $testData = ['retry' => true, 'original_delivery_id' => $delivery['id']];
                    $result = $this->sendWebhook($webhook, $delivery['event'], $testData);

                    if ($result['success']) {
                        $retryCount++;
                    }

                    $results[] = [
                        'delivery_id' => $delivery['id'],
                        'event' => $delivery['event'],
                        'retry_success' => $result['success']
                    ];
                }
            }

            return [
                'success' => true,
                'total_retried' => count($failedDeliveries),
                'successful_retries' => $retryCount,
                'results' => $results
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retry deliveries: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get webhook statistics
     */
    public function getWebhookStats(int $webhookId): array
    {
        try {
            $stats = $this->database->selectOne("
                SELECT
                    COUNT(*) as total_deliveries,
                    COUNT(CASE WHEN success = true THEN 1 END) as successful_deliveries,
                    COUNT(CASE WHEN success = false THEN 1 END) as failed_deliveries,
                    AVG(response_time) as avg_response_time,
                    MAX(delivered_at) as last_delivery
                FROM webhook_deliveries
                WHERE webhook_id = ?
            ", [$webhookId]);

            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get webhook stats: ' . $e->getMessage()
            ];
        }
    }
}

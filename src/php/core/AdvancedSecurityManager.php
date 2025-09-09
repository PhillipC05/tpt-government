<?php
/**
 * TPT Government Platform - Advanced Security Manager
 *
 * Comprehensive security management with adaptive rate limiting,
 * threat detection, security analytics, and automated response
 */

namespace Core;

use PDO;
use PDOException;
use Exception;

class AdvancedSecurityManager
{
    /**
     * Database connection
     */
    private PDO $pdo;

    /**
     * Security configuration
     */
    private array $config;

    /**
     * Rate limiting storage
     */
    private array $rateLimiters = [];

    /**
     * Threat detection patterns
     */
    private array $threatPatterns = [];

    /**
     * Security events
     */
    private array $securityEvents = [];

    /**
     * Blocked IPs/identities
     */
    private array $blockedEntities = [];

    /**
     * Security analytics
     */
    private array $securityAnalytics = [];

    /**
     * Adaptive thresholds
     */
    private array $adaptiveThresholds = [];

    /**
     * Constructor
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'enable_adaptive_rate_limiting' => true,
            'enable_threat_detection' => true,
            'enable_security_analytics' => true,
            'enable_automated_response' => false,
            'max_requests_per_minute' => 60,
            'max_requests_per_hour' => 1000,
            'block_duration_minutes' => 15,
            'threat_detection_sensitivity' => 0.7,
            'anomaly_detection_threshold' => 3.0,
            'security_event_retention_days' => 30,
            'enable_ip_whitelisting' => false,
            'enable_ip_blacklisting' => true,
            'enable_user_behavior_analysis' => true,
            'enable_session_anomaly_detection' => true
        ], $config);

        $this->initializeSecuritySystem();
        $this->loadThreatPatterns();
        $this->loadBlockedEntities();
    }

    /**
     * Check rate limit for request
     */
    public function checkRateLimit(string $identifier, string $action = 'general', array $context = []): array
    {
        $currentTime = time();
        $minuteKey = date('Y-m-d-H-i', $currentTime);
        $hourKey = date('Y-m-d-H', $currentTime);

        // Initialize rate limiter for this identifier if not exists
        if (!isset($this->rateLimiters[$identifier])) {
            $this->rateLimiters[$identifier] = [
                'minute_counts' => [],
                'hour_counts' => [],
                'last_request' => 0,
                'block_until' => 0,
                'violation_count' => 0
            ];
        }

        $limiter = &$this->rateLimiters[$identifier];

        // Check if currently blocked
        if ($currentTime < $limiter['block_until']) {
            $remainingTime = $limiter['block_until'] - $currentTime;
            $this->logSecurityEvent('rate_limit_blocked', $identifier, [
                'action' => $action,
                'remaining_time' => $remainingTime,
                'violation_count' => $limiter['violation_count']
            ]);

            return [
                'allowed' => false,
                'reason' => 'rate_limit_exceeded',
                'retry_after' => $remainingTime,
                'violations' => $limiter['violation_count']
            ];
        }

        // Clean up old entries
        $this->cleanupOldEntries($limiter, $currentTime);

        // Count requests in current minute and hour
        $minuteCount = $limiter['minute_counts'][$minuteKey] ?? 0;
        $hourCount = $limiter['hour_counts'][$hourKey] ?? 0;

        // Get adaptive limits
        $limits = $this->getAdaptiveLimits($identifier, $action, $context);

        // Check limits
        if ($minuteCount >= $limits['per_minute'] || $hourCount >= $limits['per_hour']) {
            // Increment violation count
            $limiter['violation_count']++;

            // Calculate block duration (exponential backoff)
            $blockDuration = min(
                $this->config['block_duration_minutes'] * pow(2, $limiter['violation_count'] - 1),
                1440 // Max 24 hours
            );

            $limiter['block_until'] = $currentTime + ($blockDuration * 60);

            $this->logSecurityEvent('rate_limit_violation', $identifier, [
                'action' => $action,
                'minute_count' => $minuteCount,
                'hour_count' => $hourCount,
                'limits' => $limits,
                'block_duration' => $blockDuration,
                'violation_count' => $limiter['violation_count']
            ]);

            return [
                'allowed' => false,
                'reason' => 'rate_limit_exceeded',
                'retry_after' => $blockDuration * 60,
                'violations' => $limiter['violation_count']
            ];
        }

        // Record request
        $limiter['minute_counts'][$minuteKey] = $minuteCount + 1;
        $limiter['hour_counts'][$hourKey] = $hourCount + 1;
        $limiter['last_request'] = $currentTime;

        // Reset violation count on successful request
        if ($limiter['violation_count'] > 0) {
            $limiter['violation_count'] = max(0, $limiter['violation_count'] - 1);
        }

        return [
            'allowed' => true,
            'remaining_minute' => $limits['per_minute'] - $minuteCount - 1,
            'remaining_hour' => $limits['per_hour'] - $hourCount - 1
        ];
    }

    /**
     * Detect security threats
     */
    public function detectThreats(array $requestData, array $context = []): array
    {
        $threats = [];
        $threatScore = 0;

        // Check IP-based threats
        if (isset($requestData['ip'])) {
            $ipThreats = $this->checkIPThreats($requestData['ip'], $context);
            $threats = array_merge($threats, $ipThreats['threats']);
            $threatScore += $ipThreats['score'];
        }

        // Check request pattern threats
        $patternThreats = $this->checkRequestPatterns($requestData, $context);
        $threats = array_merge($threats, $patternThreats['threats']);
        $threatScore += $patternThreats['score'];

        // Check user behavior threats
        if ($this->config['enable_user_behavior_analysis'] && isset($context['user_id'])) {
            $behaviorThreats = $this->checkUserBehavior($context['user_id'], $requestData, $context);
            $threats = array_merge($threats, $behaviorThreats['threats']);
            $threatScore += $behaviorThreats['score'];
        }

        // Check for known attack patterns
        $attackThreats = $this->checkAttackPatterns($requestData, $context);
        $threats = array_merge($threats, $attackThreats['threats']);
        $threatScore += $attackThreats['score'];

        // Determine threat level
        $threatLevel = $this->calculateThreatLevel($threatScore);

        // Log threat detection
        if ($threatScore > 0) {
            $this->logSecurityEvent('threat_detected', $requestData['ip'] ?? 'unknown', [
                'threat_score' => $threatScore,
                'threat_level' => $threatLevel,
                'threats' => $threats,
                'request_data' => $requestData,
                'context' => $context
            ]);
        }

        return [
            'threat_detected' => $threatScore > $this->config['threat_detection_sensitivity'],
            'threat_score' => $threatScore,
            'threat_level' => $threatLevel,
            'threats' => $threats,
            'recommendations' => $this->getThreatRecommendations($threatLevel, $threats)
        ];
    }

    /**
     * Analyze user behavior for anomalies
     */
    public function analyzeUserBehavior(int $userId, array $behaviorData): array
    {
        $baseline = $this->getUserBehaviorBaseline($userId);
        $anomalies = [];

        // Compare current behavior with baseline
        foreach ($behaviorData as $metric => $value) {
            if (isset($baseline[$metric])) {
                $deviation = abs($value - $baseline[$metric]['mean']) / $baseline[$metric]['std_dev'];
                if ($deviation > $this->config['anomaly_detection_threshold']) {
                    $anomalies[] = [
                        'metric' => $metric,
                        'value' => $value,
                        'expected' => $baseline[$metric]['mean'],
                        'deviation' => $deviation,
                        'severity' => $deviation > 5.0 ? 'high' : 'medium'
                    ];
                }
            }
        }

        // Update baseline with new data
        $this->updateUserBehaviorBaseline($userId, $behaviorData);

        return [
            'anomalies_detected' => !empty($anomalies),
            'anomalies' => $anomalies,
            'risk_score' => $this->calculateBehaviorRiskScore($anomalies)
        ];
    }

    /**
     * Block entity (IP, user, etc.)
     */
    public function blockEntity(string $entityType, string $entityValue, int $durationMinutes = null, string $reason = ''): bool
    {
        $durationMinutes = $durationMinutes ?? $this->config['block_duration_minutes'];
        $blockUntil = time() + ($durationMinutes * 60);

        $this->blockedEntities[$entityType][$entityValue] = [
            'blocked_at' => time(),
            'block_until' => $blockUntil,
            'reason' => $reason,
            'block_count' => ($this->blockedEntities[$entityType][$entityValue]['block_count'] ?? 0) + 1
        ];

        // Store in database
        $this->storeBlockedEntity($entityType, $entityValue, $blockUntil, $reason);

        $this->logSecurityEvent('entity_blocked', $entityValue, [
            'entity_type' => $entityType,
            'duration_minutes' => $durationMinutes,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Check if entity is blocked
     */
    public function isEntityBlocked(string $entityType, string $entityValue): bool
    {
        if (!isset($this->blockedEntities[$entityType][$entityValue])) {
            return false;
        }

        $entity = $this->blockedEntities[$entityType][$entityValue];

        // Check if block has expired
        if (time() > $entity['block_until']) {
            unset($this->blockedEntities[$entityType][$entityValue]);
            return false;
        }

        return true;
    }

    /**
     * Get security analytics
     */
    public function getSecurityAnalytics(array $filters = []): array
    {
        $analytics = [
            'total_events' => count($this->securityEvents),
            'threat_distribution' => $this->getThreatDistribution($filters),
            'rate_limit_violations' => $this->getRateLimitViolations($filters),
            'blocked_entities' => $this->getBlockedEntitiesStats($filters),
            'attack_patterns' => $this->getAttackPatternsStats($filters),
            'response_effectiveness' => $this->getResponseEffectiveness($filters),
            'security_score' => $this->calculateSecurityScore($filters)
        ];

        return $analytics;
    }

    /**
     * Generate security report
     */
    public function generateSecurityReport(array $filters = []): array
    {
        $reportId = uniqid('security_report_');

        $report = [
            'id' => $reportId,
            'generated_at' => time(),
            'period' => $filters['period'] ?? 'last_24h',
            'analytics' => $this->getSecurityAnalytics($filters),
            'top_threats' => $this->getTopThreats($filters),
            'recommendations' => $this->getSecurityRecommendations($filters),
            'incidents' => $this->getSecurityIncidents($filters),
            'summary' => $this->generateSecuritySummary($filters)
        ];

        $this->storeSecurityReport($report);

        return $report;
    }

    /**
     * Automated security response
     */
    public function automatedResponse(string $threatType, array $threatData): array
    {
        if (!$this->config['enable_automated_response']) {
            return ['action' => 'none', 'reason' => 'automated_response_disabled'];
        }

        $response = ['actions_taken' => [], 'escalation_required' => false];

        switch ($threatType) {
            case 'brute_force':
                $response['actions_taken'][] = $this->blockEntity('ip', $threatData['ip'], 60, 'brute_force_attack');
                $response['actions_taken'][] = $this->increaseRateLimit($threatData['identifier'], 0.5);
                break;

            case 'sql_injection':
                $response['actions_taken'][] = $this->blockEntity('ip', $threatData['ip'], 120, 'sql_injection_attempt');
                $response['escalation_required'] = true;
                break;

            case 'xss_attack':
                $response['actions_taken'][] = $this->blockEntity('ip', $threatData['ip'], 30, 'xss_attack');
                break;

            case 'unusual_behavior':
                if ($threatData['risk_score'] > 0.8) {
                    $response['actions_taken'][] = $this->blockEntity('user', $threatData['user_id'], 15, 'unusual_behavior');
                    $response['escalation_required'] = true;
                }
                break;

            default:
                $response['actions_taken'][] = $this->logSecurityEvent('unknown_threat', $threatData['identifier'] ?? 'unknown', $threatData);
        }

        return $response;
    }

    // Private helper methods

    private function initializeSecuritySystem(): void
    {
        $this->createSecurityTables();
        $this->initializeDefaultThreatPatterns();
        $this->initializeAdaptiveThresholds();
    }

    private function createSecurityTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS security_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                identifier VARCHAR(255),
                event_data JSON,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_identifier (identifier),
                INDEX idx_severity (severity),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS blocked_entities (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_value VARCHAR(255) NOT NULL,
                blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                block_until TIMESTAMP,
                reason TEXT,
                block_count INT DEFAULT 1,
                INDEX idx_entity_type (entity_type),
                INDEX idx_entity_value (entity_value),
                INDEX idx_block_until (block_until)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS security_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(64) NOT NULL UNIQUE,
                report_data JSON,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_report_id (report_id),
                INDEX idx_generated_at (generated_at)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS user_behavior_baselines (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                metric_name VARCHAR(100) NOT NULL,
                mean DECIMAL(15,4),
                std_dev DECIMAL(15,4),
                sample_count INT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_metric (user_id, metric_name),
                INDEX idx_user_id (user_id),
                INDEX idx_last_updated (last_updated)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create security tables: " . $e->getMessage());
        }
    }

    private function initializeDefaultThreatPatterns(): void
    {
        $this->threatPatterns = [
            'sql_injection' => [
                'patterns' => [
                    '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
                    '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
                    '/\w*((\%27)|(\'))(\s)*((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i'
                ],
                'weight' => 1.0,
                'description' => 'SQL injection attempt'
            ],
            'xss_attack' => [
                'patterns' => [
                    '/<script[^>]*>.*?<\/script>/i',
                    '/javascript:/i',
                    '/on\w+\s*=/i'
                ],
                'weight' => 0.8,
                'description' => 'Cross-site scripting attempt'
            ],
            'path_traversal' => [
                'patterns' => [
                    '/\.\./',
                    '/\.\//',
                    '/%2e%2e/',
                    '/%2e/'
                ],
                'weight' => 0.9,
                'description' => 'Path traversal attempt'
            ],
            'brute_force' => [
                'threshold' => 5,
                'time_window' => 300, // 5 minutes
                'weight' => 0.7,
                'description' => 'Brute force attack pattern'
            ]
        ];
    }

    private function initializeAdaptiveThresholds(): void
    {
        $this->adaptiveThresholds = [
            'general' => [
                'base_minute_limit' => $this->config['max_requests_per_minute'],
                'base_hour_limit' => $this->config['max_requests_per_hour'],
                'adjustment_factor' => 0.1
            ],
            'api' => [
                'base_minute_limit' => 30,
                'base_hour_limit' => 500,
                'adjustment_factor' => 0.15
            ],
            'auth' => [
                'base_minute_limit' => 5,
                'base_hour_limit' => 20,
                'adjustment_factor' => 0.2
            ]
        ];
    }

    private function loadThreatPatterns(): void
    {
        // Load custom threat patterns from database
        // Simplified implementation
    }

    private function loadBlockedEntities(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM blocked_entities WHERE block_until > NOW()");
            $blocked = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($blocked as $block) {
                $this->blockedEntities[$block['entity_type']][$block['entity_value']] = [
                    'blocked_at' => strtotime($block['blocked_at']),
                    'block_until' => strtotime($block['block_until']),
                    'reason' => $block['reason'],
                    'block_count' => $block['block_count']
                ];
            }
        } catch (PDOException $e) {
            error_log("Failed to load blocked entities: " . $e->getMessage());
        }
    }

    private function getAdaptiveLimits(string $identifier, string $action, array $context): array
    {
        $baseLimits = $this->adaptiveThresholds[$action] ?? $this->adaptiveThresholds['general'];

        // Adjust limits based on context and threat level
        $threatLevel = $this->getThreatLevel($identifier);
        $adjustment = 1.0 - ($threatLevel * $baseLimits['adjustment_factor']);

        return [
            'per_minute' => max(1, (int)($baseLimits['base_minute_limit'] * $adjustment)),
            'per_hour' => max(10, (int)($baseLimits['base_hour_limit'] * $adjustment))
        ];
    }

    private function getThreatLevel(string $identifier): float
    {
        // Simplified threat level calculation
        return 0.0;
    }

    private function cleanupOldEntries(array &$limiter, int $currentTime): void
    {
        $currentMinute = date('Y-m-d-H-i', $currentTime);
        $currentHour = date('Y-m-d-H', $currentTime);

        // Remove entries older than current minute
        foreach ($limiter['minute_counts'] as $key => $count) {
            if ($key !== $currentMinute) {
                unset($limiter['minute_counts'][$key]);
            }
        }

        // Remove entries older than current hour
        foreach ($limiter['hour_counts'] as $key => $count) {
            if ($key !== $currentHour) {
                unset($limiter['hour_counts'][$key]);
            }
        }
    }

    private function checkIPThreats(string $ip, array $context): array
    {
        $threats = [];
        $score = 0;

        // Check if IP is blocked
        if ($this->isEntityBlocked('ip', $ip)) {
            $threats[] = 'IP is blocked';
            $score += 1.0;
        }

        // Check IP reputation (simplified)
        if ($this->isSuspiciousIP($ip)) {
            $threats[] = 'Suspicious IP address';
            $score += 0.5;
        }

        return ['threats' => $threats, 'score' => $score];
    }

    private function checkRequestPatterns(array $requestData, array $context): array
    {
        $threats = [];
        $score = 0;

        // Check for suspicious patterns in request
        foreach ($this->threatPatterns as $patternName => $pattern) {
            if (isset($pattern['patterns'])) {
                foreach ($pattern['patterns'] as $regex) {
                    $checkData = json_encode($requestData);
                    if (preg_match($regex, $checkData)) {
                        $threats[] = $pattern['description'];
                        $score += $pattern['weight'];
                        break;
                    }
                }
            }
        }

        return ['threats' => $threats, 'score' => $score];
    }

    private function checkUserBehavior(int $userId, array $requestData, array $context): array
    {
        $behaviorData = [
            'request_frequency' => 1,
            'unusual_hours' => $this->isUnusualHour(),
            'unusual_location' => $this->isUnusualLocation($context),
            'failed_attempts' => $context['failed_attempts'] ?? 0
        ];

        $analysis = $this->analyzeUserBehavior($userId, $behaviorData);

        return [
            'threats' => $analysis['anomalies_detected'] ? ['Unusual user behavior detected'] : [],
            'score' => $analysis['risk_score']
        ];
    }

    private function checkAttackPatterns(array $requestData, array $context): array
    {
        $threats = [];
        $score = 0;

        // Check for brute force patterns
        if (isset($context['failed_attempts']) && $context['failed_attempts'] > 3) {
            $threats[] = 'Potential brute force attack';
            $score += 0.8;
        }

        // Check for unusual request patterns
        if (isset($requestData['user_agent']) && $this->isSuspiciousUserAgent($requestData['user_agent'])) {
            $threats[] = 'Suspicious user agent';
            $score += 0.3;
        }

        return ['threats' => $threats, 'score' => $score];
    }

    private function calculateThreatLevel(float $threatScore): string
    {
        if ($threatScore >= 2.0) return 'critical';
        if ($threatScore >= 1.5) return 'high';
        if ($threatScore >= 1.0) return 'medium';
        if ($threatScore >= 0.5) return 'low';
        return 'none';
    }

    private function getThreatRecommendations(string $threatLevel, array $threats): array
    {
        $recommendations = [];

        switch ($threatLevel) {
            case 'critical':
                $recommendations[] = 'Immediate blocking and investigation required';
                $recommendations[] = 'Alert security team';
                break;
            case 'high':
                $recommendations[] = 'Implement temporary blocking';
                $recommendations[] = 'Increase monitoring for this entity';
                break;
            case 'medium':
                $recommendations[] = 'Log and monitor closely';
                $recommendations[] = 'Consider rate limiting';
                break;
            case 'low':
                $recommendations[] = 'Log for analysis';
                break;
        }

        return $recommendations;
    }

    private function getUserBehaviorBaseline(int $userId): array
    {
        // Simplified implementation
        return [];
    }

    private function updateUserBehaviorBaseline(int $userId, array $behaviorData): void
    {
        // Simplified implementation
    }

    private function calculateBehaviorRiskScore(array $anomalies): float
    {
        $score = 0;
        foreach ($anomalies as $anomaly) {
            $score += $anomaly['deviation'] * 0.1;
        }
        return min(1.0, $score);
    }

    private function isSuspiciousIP(string $ip): bool
    {
        // Simplified IP reputation check
        return false;
    }

    private function isUnusualHour(): bool
    {
        $hour = (int)date('H');
        return $hour < 6 || $hour > 22;
    }

    private function isUnusualLocation(array $context): bool
    {
        // Simplified location check
        return false;
    }

    private function isSuspiciousUserAgent(string $userAgent): bool
    {
        $suspiciousPatterns = [
            'sqlmap',
            'nmap',
            'nikto',
            'dirbuster',
            'gobuster'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function increaseRateLimit(string $identifier, float $factor): void
    {
        // Simplified rate limit adjustment
    }

    private function logSecurityEvent(string $eventType, string $identifier, array $eventData): void
    {
        $event = [
            'type' => $eventType,
            'identifier' => $identifier,
            'data' => $eventData,
            'timestamp' => time()
        ];

        $this->securityEvents[] = $event;

        // Keep events within limit
        if (count($this->securityEvents) > 10000) {
            array_shift($this->securityEvents);
        }

        // Store in database
        $this->storeSecurityEvent($event);
    }

    private function getThreatDistribution(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function getRateLimitViolations(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function getBlockedEntitiesStats(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function getAttackPatternsStats(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function getResponseEffectiveness(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function calculateSecurityScore(array $filters = []): float
    {
        // Simplified security score calculation
        return 85.0;
    }

    private function getTopThreats(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function getSecurityRecommendations(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function getSecurityIncidents(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    private function generateSecuritySummary(array $filters = []): array
    {
        // Simplified implementation
        return [];
    }

    // Database storage methods

    private function storeSecurityEvent(array $event): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_events
                (event_type, identifier, event_data, severity)
                VALUES (?, ?, ?, ?)
            ");

            $severity = $this->determineEventSeverity($event['type']);

            $stmt->execute([
                $event['type'],
                $event['identifier'],
                json_encode($event['data']),
                $severity
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store security event: " . $e->getMessage());
        }
    }

    private function storeBlockedEntity(string $entityType, string $entityValue, int $blockUntil, string $reason): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO blocked_entities
                (entity_type, entity_value, block_until, reason)
                VALUES (?, ?, FROM_UNIXTIME(?), ?)
                ON DUPLICATE KEY UPDATE
                block_until = VALUES(block_until),
                reason = VALUES(reason),
                block_count = block_count + 1
            ");

            $stmt->execute([$entityType, $entityValue, $blockUntil, $reason]);
        } catch (PDOException $e) {
            error_log("Failed to store blocked entity: " . $e->getMessage());
        }
    }

    private function storeSecurityReport(array $report): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_reports
                (report_id, report_data, generated_at)
                VALUES (?, ?, FROM_UNIXTIME(?))
            ");

            $stmt->execute([
                $report['id'],
                json_encode($report),
                $report['generated_at']
            ]);
        } catch (PDOException $e) {
            error_log("Failed to store security report: " . $e->getMessage());
        }
    }

    private function determineEventSeverity(string $eventType): string
    {
        $severityMap = [
            'rate_limit_violation' => 'low',
            'threat_detected' => 'medium',
            'brute_force' => 'high',
            'sql_injection' => 'critical',
            'entity_blocked' => 'medium'
        ];

        return $severityMap[$eventType] ?? 'low';
    }
}

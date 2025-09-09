<?php
/**
 * TPT Government Platform - User Behavior Tracker
 *
 * Specialized manager for citizen behavior analysis and user segmentation
 */

namespace Core\Analytics;

use Core\Database;

class UserBehaviorTracker
{
    /**
     * Behavior tracking configuration
     */
    private array $config = [
        'enabled' => true,
        'tracking_events' => [
            'page_views',
            'service_requests',
            'payment_transactions',
            'feedback_submissions',
            'support_interactions',
            'document_downloads',
            'form_submissions',
            'search_queries'
        ],
        'segmentation_rules' => [
            'high_value' => 'transaction_volume > 1000 AND service_requests > 5',
            'frequent_user' => 'monthly_requests > 10',
            'satisfied_customer' => 'satisfaction_score > 4.5',
            'power_user' => 'session_duration > 30 AND page_views > 20',
            'casual_user' => 'session_duration < 5 AND page_views < 3',
            'mobile_user' => 'device_type = "mobile"',
            'desktop_user' => 'device_type = "desktop"'
        ],
        'retention_period' => 365, // days
        'real_time_processing' => true,
        'anomaly_detection' => true
    ];

    /**
     * User behavior profiles
     */
    private array $userProfiles = [];

    /**
     * Behavior patterns
     */
    private array $behaviorPatterns = [];

    /**
     * User segments
     */
    private array $userSegments = [];

    /**
     * Event queue for real-time processing
     */
    private array $eventQueue = [];

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge($this->config, $config);
        $this->initializeBehaviorTracking();
    }

    /**
     * Initialize behavior tracking system
     */
    private function initializeBehaviorTracking(): void
    {
        // Load existing user profiles
        $this->loadUserProfiles();

        // Initialize behavior patterns
        $this->initializeBehaviorPatterns();

        // Set up real-time processing if enabled
        if ($this->config['real_time_processing']) {
            $this->setupRealTimeProcessing();
        }

        // Initialize anomaly detection
        if ($this->config['anomaly_detection']) {
            $this->initializeAnomalyDetection();
        }
    }

    /**
     * Track user event
     */
    public function trackEvent(string $eventType, array $eventData, int $userId = null): array
    {
        if (!in_array($eventType, $this->config['tracking_events'])) {
            return [
                'success' => false,
                'error' => 'Event type not supported'
            ];
        }

        $event = [
            'id' => uniqid('event_'),
            'type' => $eventType,
            'user_id' => $userId,
            'data' => $eventData,
            'timestamp' => time(),
            'session_id' => $eventData['session_id'] ?? session_id(),
            'ip_address' => $eventData['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $eventData['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'device_info' => $this->extractDeviceInfo($eventData),
            'location_info' => $this->extractLocationInfo($eventData)
        ];

        // Add to processing queue
        $this->eventQueue[] = $event;

        // Process event immediately if real-time processing is enabled
        if ($this->config['real_time_processing']) {
            $this->processEvent($event);
        }

        // Store event for later analysis
        $this->storeEvent($event);

        // Update user profile
        if ($userId) {
            $this->updateUserProfile($userId, $event);
        }

        return [
            'success' => true,
            'event_id' => $event['id'],
            'processed' => $this->config['real_time_processing']
        ];
    }

    /**
     * Get user behavior profile
     */
    public function getUserProfile(int $userId): array
    {
        if (!isset($this->userProfiles[$userId])) {
            $this->userProfiles[$userId] = $this->buildUserProfile($userId);
        }

        return $this->userProfiles[$userId];
    }

    /**
     * Get user segment
     */
    public function getUserSegment(int $userId): array
    {
        $profile = $this->getUserProfile($userId);

        return [
            'user_id' => $userId,
            'segment' => $this->classifyUserSegment($profile),
            'confidence' => $this->calculateSegmentConfidence($profile),
            'segmentation_factors' => $this->getSegmentationFactors($profile),
            'last_updated' => time()
        ];
    }

    /**
     * Analyze user behavior patterns
     */
    public function analyzeBehaviorPatterns(int $userId = null, array $filters = []): array
    {
        $events = $this->getFilteredEvents($userId, $filters);

        return [
            'user_id' => $userId,
            'total_events' => count($events),
            'event_distribution' => $this->analyzeEventDistribution($events),
            'temporal_patterns' => $this->analyzeTemporalPatterns($events),
            'behavior_clusters' => $this->identifyBehaviorClusters($events),
            'anomalies' => $this->detectBehavioralAnomalies($events),
            'insights' => $this->generateBehaviorInsights($events)
        ];
    }

    /**
     * Get user segmentation summary
     */
    public function getSegmentationSummary(): array
    {
        $segments = $this->calculateUserSegments();

        return [
            'total_users' => count($this->userProfiles),
            'segment_distribution' => $segments,
            'segment_characteristics' => $this->analyzeSegmentCharacteristics(),
            'segment_transitions' => $this->analyzeSegmentTransitions(),
            'segment_performance' => $this->calculateSegmentPerformance()
        ];
    }

    /**
     * Detect behavioral anomalies
     */
    public function detectAnomalies(int $userId = null): array
    {
        $events = $this->getFilteredEvents($userId, ['hours' => 24]); // Last 24 hours

        $anomalies = [];

        // Check for unusual activity patterns
        $activityAnomaly = $this->detectActivityAnomaly($events);
        if ($activityAnomaly) {
            $anomalies[] = $activityAnomaly;
        }

        // Check for unusual location patterns
        $locationAnomaly = $this->detectLocationAnomaly($events);
        if ($locationAnomaly) {
            $anomalies[] = $locationAnomaly;
        }

        // Check for unusual timing patterns
        $timingAnomaly = $this->detectTimingAnomaly($events);
        if ($timingAnomaly) {
            $anomalies[] = $timingAnomaly;
        }

        return [
            'user_id' => $userId,
            'anomalies_detected' => count($anomalies),
            'anomalies' => $anomalies,
            'risk_score' => $this->calculateAnomalyRiskScore($anomalies)
        ];
    }

    /**
     * Generate behavior insights
     */
    public function generateInsights(int $userId = null): array
    {
        $insights = [];

        if ($userId) {
            // User-specific insights
            $profile = $this->getUserProfile($userId);
            $insights = array_merge($insights, $this->generateUserInsights($profile));
        } else {
            // General insights
            $insights = array_merge($insights, $this->generateGeneralInsights());
        }

        return [
            'user_id' => $userId,
            'insights_generated' => count($insights),
            'insights' => $insights,
            'generated_at' => time()
        ];
    }

    /**
     * Export behavior data
     */
    public function exportBehaviorData(int $userId = null, string $format = 'json'): string
    {
        $data = [
            'export_timestamp' => time(),
            'user_id' => $userId,
            'profiles' => $userId ? [$userId => $this->getUserProfile($userId)] : $this->userProfiles,
            'segments' => $this->getSegmentationSummary(),
            'patterns' => $this->behaviorPatterns,
            'insights' => $this->generateInsights($userId)
        ];

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportBehaviorToCSV($data);
            default:
                return json_encode($data);
        }
    }

    // Private helper methods

    private function processEvent(array $event): void
    {
        // Update real-time metrics
        $this->updateRealTimeMetrics($event);

        // Check for immediate insights
        $this->checkForImmediateInsights($event);

        // Update behavior patterns
        $this->updateBehaviorPatterns($event);
    }

    private function updateUserProfile(int $userId, array $event): void
    {
        if (!isset($this->userProfiles[$userId])) {
            $this->userProfiles[$userId] = $this->initializeUserProfile($userId);
        }

        $profile = $this->userProfiles[$userId];

        // Update event counts
        $profile['event_counts'][$event['type']] = ($profile['event_counts'][$event['type']] ?? 0) + 1;

        // Update session information
        if ($event['session_id'] !== $profile['current_session']) {
            $profile['session_count']++;
            $profile['current_session'] = $event['session_id'];
        }

        // Update temporal patterns
        $hour = (int)date('H', $event['timestamp']);
        $profile['hourly_activity'][$hour] = ($profile['hourly_activity'][$hour] ?? 0) + 1;

        // Update device preferences
        $deviceType = $event['device_info']['type'] ?? 'unknown';
        $profile['device_preferences'][$deviceType] = ($profile['device_preferences'][$deviceType] ?? 0) + 1;

        // Update last activity
        $profile['last_activity'] = $event['timestamp'];
        $profile['total_events']++;

        $this->userProfiles[$userId] = $profile;
    }

    private function buildUserProfile(int $userId): array
    {
        // Load historical data from database
        $historicalEvents = $this->loadUserEvents($userId);

        $profile = $this->initializeUserProfile($userId);

        // Build profile from historical data
        foreach ($historicalEvents as $event) {
            $this->updateUserProfile($userId, $event);
        }

        // Calculate derived metrics
        $profile['engagement_score'] = $this->calculateEngagementScore($profile);
        $profile['loyalty_score'] = $this->calculateLoyaltyScore($profile);
        $profile['satisfaction_score'] = $this->calculateSatisfactionScore($profile);

        return $profile;
    }

    private function initializeUserProfile(int $userId): array
    {
        return [
            'user_id' => $userId,
            'first_seen' => time(),
            'last_activity' => time(),
            'total_events' => 0,
            'session_count' => 0,
            'current_session' => null,
            'event_counts' => [],
            'hourly_activity' => array_fill(0, 24, 0),
            'device_preferences' => [],
            'behavior_patterns' => [],
            'engagement_score' => 0.0,
            'loyalty_score' => 0.0,
            'satisfaction_score' => 0.0
        ];
    }

    private function classifyUserSegment(array $profile): string
    {
        // Apply segmentation rules
        foreach ($this->config['segmentation_rules'] as $segment => $rule) {
            if ($this->evaluateSegmentationRule($profile, $rule)) {
                return $segment;
            }
        }

        return 'standard_user';
    }

    private function evaluateSegmentationRule(array $profile, string $rule): bool
    {
        // Simple rule evaluation (in real implementation, this would be more sophisticated)
        // For demo purposes, using basic pattern matching

        if (strpos($rule, 'transaction_volume > 1000') !== false) {
            return ($profile['event_counts']['payment_transactions'] ?? 0) > 10;
        }

        if (strpos($rule, 'monthly_requests > 10') !== false) {
            return ($profile['event_counts']['service_requests'] ?? 0) > 10;
        }

        if (strpos($rule, 'satisfaction_score > 4.5') !== false) {
            return $profile['satisfaction_score'] > 4.5;
        }

        return false;
    }

    private function calculateSegmentConfidence(array $profile): float
    {
        // Calculate confidence based on data completeness and consistency
        $dataPoints = count(array_filter($profile['event_counts']));
        $totalEvents = $profile['total_events'];

        if ($totalEvents < 5) return 0.3; // Low confidence for new users
        if ($dataPoints < 3) return 0.5; // Medium confidence with limited data

        return min(0.95, 0.6 + ($totalEvents / 100) * 0.3); // Up to 95% confidence
    }

    private function getSegmentationFactors(array $profile): array
    {
        $factors = [];

        if (($profile['event_counts']['payment_transactions'] ?? 0) > 5) {
            $factors[] = 'high_transaction_volume';
        }

        if (($profile['event_counts']['service_requests'] ?? 0) > 10) {
            $factors[] = 'frequent_service_user';
        }

        if ($profile['satisfaction_score'] > 4.5) {
            $factors[] = 'high_satisfaction';
        }

        if ($profile['engagement_score'] > 15) {
            $factors[] = 'high_engagement';
        }

        return $factors;
    }

    private function calculateUserSegments(): array
    {
        $segments = [];

        foreach ($this->userProfiles as $userId => $profile) {
            $segment = $this->classifyUserSegment($profile);
            $segments[$segment] = ($segments[$segment] ?? 0) + 1;
        }

        return $segments;
    }

    private function analyzeSegmentCharacteristics(): array
    {
        $characteristics = [];

        foreach ($this->userSegments as $segment => $users) {
            $characteristics[$segment] = [
                'avg_engagement' => $this->calculateAverageEngagement($users),
                'avg_satisfaction' => $this->calculateAverageSatisfaction($users),
                'common_behaviors' => $this->identifyCommonBehaviors($users),
                'peak_activity_hours' => $this->identifyPeakHours($users)
            ];
        }

        return $characteristics;
    }

    private function analyzeEventDistribution(array $events): array
    {
        $distribution = [];

        foreach ($events as $event) {
            $type = $event['type'];
            $distribution[$type] = ($distribution[$type] ?? 0) + 1;
        }

        // Sort by frequency
        arsort($distribution);

        return $distribution;
    }

    private function analyzeTemporalPatterns(array $events): array
    {
        $patterns = [
            'hourly' => array_fill(0, 24, 0),
            'daily' => array_fill(0, 7, 0), // 0 = Sunday
            'monthly' => array_fill(1, 12, 0)
        ];

        foreach ($events as $event) {
            $hour = (int)date('H', $event['timestamp']);
            $day = (int)date('w', $event['timestamp']);
            $month = (int)date('n', $event['timestamp']);

            $patterns['hourly'][$hour]++;
            $patterns['daily'][$day]++;
            $patterns['monthly'][$month]++;
        }

        return $patterns;
    }

    private function identifyBehaviorClusters(array $events): array
    {
        // Simple clustering based on event types and timing
        $clusters = [];

        // Group events by time windows (e.g., hourly)
        $timeWindows = [];
        foreach ($events as $event) {
            $hour = date('Y-m-d H', $event['timestamp']);
            $timeWindows[$hour][] = $event;
        }

        foreach ($timeWindows as $window => $windowEvents) {
            if (count($windowEvents) > 5) { // Significant activity
                $clusters[] = [
                    'time_window' => $window,
                    'event_count' => count($windowEvents),
                    'primary_activity' => $this->getPrimaryActivityType($windowEvents),
                    'intensity' => $this->calculateActivityIntensity($windowEvents)
                ];
            }
        }

        return $clusters;
    }

    private function detectBehavioralAnomalies(array $events): array
    {
        $anomalies = [];

        if (empty($events)) return $anomalies;

        // Check for unusual activity spikes
        $recentActivity = $this->getRecentActivity($events, 3600); // Last hour
        $normalActivity = $this->getNormalActivityLevel($events);

        if ($recentActivity > $normalActivity * 3) {
            $anomalies[] = [
                'type' => 'activity_spike',
                'description' => 'Unusual spike in user activity',
                'severity' => 'medium',
                'recent_activity' => $recentActivity,
                'normal_activity' => $normalActivity
            ];
        }

        return $anomalies;
    }

    private function generateBehaviorInsights(array $events): array
    {
        $insights = [];

        $patterns = $this->analyzeTemporalPatterns($events);

        // Find peak activity hours
        $peakHour = array_search(max($patterns['hourly']), $patterns['hourly']);
        if ($peakHour !== false) {
            $insights[] = [
                'type' => 'temporal_pattern',
                'title' => 'Peak Activity Hour',
                'description' => "Most active at {$peakHour}:00",
                'data' => ['peak_hour' => $peakHour]
            ];
        }

        // Identify preferred device
        $devicePrefs = $this->analyzeDevicePreferences($events);
        if (!empty($devicePrefs)) {
            $preferredDevice = array_key_first($devicePrefs);
            $insights[] = [
                'type' => 'device_preference',
                'title' => 'Preferred Device',
                'description' => "Most active on {$preferredDevice}",
                'data' => ['preferred_device' => $preferredDevice]
            ];
        }

        return $insights;
    }

    // Additional helper methods

    private function extractDeviceInfo(array $eventData): array
    {
        $userAgent = $eventData['user_agent'] ?? '';

        return [
            'type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'mobile' => $this->isMobileDevice($userAgent)
        ];
    }

    private function extractLocationInfo(array $eventData): array
    {
        // In real implementation, this would use IP geolocation
        return [
            'country' => 'Unknown',
            'region' => 'Unknown',
            'city' => 'Unknown'
        ];
    }

    private function detectDeviceType(string $userAgent): string
    {
        if (stripos($userAgent, 'mobile') !== false || stripos($userAgent, 'android') !== false) {
            return 'mobile';
        }
        if (stripos($userAgent, 'tablet') !== false) {
            return 'tablet';
        }
        return 'desktop';
    }

    private function detectBrowser(string $userAgent): string
    {
        if (stripos($userAgent, 'chrome') !== false) return 'Chrome';
        if (stripos($userAgent, 'firefox') !== false) return 'Firefox';
        if (stripos($userAgent, 'safari') !== false) return 'Safari';
        if (stripos($userAgent, 'edge') !== false) return 'Edge';
        return 'Unknown';
    }

    private function detectOS(string $userAgent): string
    {
        if (stripos($userAgent, 'windows') !== false) return 'Windows';
        if (stripos($userAgent, 'mac') !== false) return 'macOS';
        if (stripos($userAgent, 'linux') !== false) return 'Linux';
        if (stripos($userAgent, 'android') !== false) return 'Android';
        if (stripos($userAgent, 'ios') !== false) return 'iOS';
        return 'Unknown';
    }

    private function isMobileDevice(string $userAgent): bool
    {
        return stripos($userAgent, 'mobile') !== false ||
               stripos($userAgent, 'android') !== false ||
               stripos($userAgent, 'iphone') !== false;
    }

    private function calculateEngagementScore(array $profile): float
    {
        $eventsPerSession = $profile['total_events'] / max(1, $profile['session_count']);
        $avgHourlyActivity = array_sum($profile['hourly_activity']) / 24;

        return ($eventsPerSession * 0.6) + ($avgHourlyActivity * 0.4);
    }

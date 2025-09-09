<?php
/**
 * TPT Government Platform - Screen Reader Manager
 *
 * Specialized manager for screen reader accessibility support
 */

namespace Core\Accessibility;

class ScreenReaderManager
{
    /**
     * Supported screen readers
     */
    private array $supportedReaders = [
        'NVDA', 'JAWS', 'VoiceOver', 'TalkBack', 'Orca'
    ];

    /**
     * Screen reader configuration
     */
    private array $config = [
        'enabled' => true,
        'auto_detection' => true,
        'announcements' => true,
        'landmarks' => true,
        'live_regions' => true,
        'focus_announcements' => true,
        'role_announcements' => true
    ];

    /**
     * Active screen reader
     */
    private ?string $activeReader = null;

    /**
     * Announcement queue
     */
    private array $announcementQueue = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->initialize();
    }

    /**
     * Initialize screen reader support
     */
    private function initialize(): void
    {
        if ($this->config['auto_detection']) {
            $this->detectScreenReader();
        }

        if ($this->config['announcements']) {
            $this->setupAnnouncements();
        }

        if ($this->config['landmarks']) {
            $this->setupLandmarks();
        }

        if ($this->config['live_regions']) {
            $this->setupLiveRegions();
        }
    }

    /**
     * Detect active screen reader
     */
    public function detectScreenReader(): ?string
    {
        // Check user agent for screen reader indicators
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        foreach ($this->supportedReaders as $reader) {
            if (stripos($userAgent, $reader) !== false) {
                $this->activeReader = $reader;
                return $reader;
            }
        }

        // Check for screen reader specific headers
        if (isset($_SERVER['HTTP_X_SCREEN_READER'])) {
            $this->activeReader = $_SERVER['HTTP_X_SCREEN_READER'];
            return $this->activeReader;
        }

        return null;
    }

    /**
     * Announce content to screen reader
     */
    public function announce(string $message, string $priority = 'polite'): void
    {
        if (!$this->config['announcements']) {
            return;
        }

        $announcement = [
            'message' => $message,
            'priority' => $priority,
            'timestamp' => time(),
            'id' => uniqid('announce_')
        ];

        $this->announcementQueue[] = $announcement;

        // Output announcement for screen readers
        $this->outputAnnouncement($announcement);
    }

    /**
     * Announce page navigation
     */
    public function announceNavigation(string $from, string $to): void
    {
        $message = "Navigated from {$from} to {$to}";
        $this->announce($message, 'assertive');
    }

    /**
     * Announce form validation errors
     */
    public function announceValidationErrors(array $errors): void
    {
        $count = count($errors);
        $message = "{$count} validation error" . ($count > 1 ? 's' : '') . " found. " .
                  implode('. ', $errors);

        $this->announce($message, 'assertive');
    }

    /**
     * Announce successful action
     */
    public function announceSuccess(string $action): void
    {
        $message = "Success: {$action} completed";
        $this->announce($message, 'polite');
    }

    /**
     * Set up ARIA landmarks
     */
    public function setupLandmarks(): void
    {
        // This would inject JavaScript to set up ARIA landmarks
        // In a real implementation, this would work with the frontend
    }

    /**
     * Set up live regions for dynamic content
     */
    public function setupLiveRegions(): void
    {
        // Configure live regions for dynamic content updates
    }

    /**
     * Get screen reader status
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->config['enabled'],
            'active_reader' => $this->activeReader,
            'supported_readers' => $this->supportedReaders,
            'announcements_enabled' => $this->config['announcements'],
            'landmarks_enabled' => $this->config['landmarks'],
            'live_regions_enabled' => $this->config['live_regions'],
            'pending_announcements' => count($this->announcementQueue)
        ];
    }

    /**
     * Check if screen reader is active
     */
    public function isActive(): bool
    {
        return $this->activeReader !== null;
    }

    /**
     * Get supported screen readers
     */
    public function getSupportedReaders(): array
    {
        return $this->supportedReaders;
    }

    /**
     * Clear announcement queue
     */
    public function clearAnnouncements(): void
    {
        $this->announcementQueue = [];
    }

    /**
     * Get announcement history
     */
    public function getAnnouncementHistory(int $limit = 10): array
    {
        return array_slice(array_reverse($this->announcementQueue), 0, $limit);
    }

    /**
     * Output announcement (helper method)
     */
    private function outputAnnouncement(array $announcement): void
    {
        // In a real implementation, this would output appropriate markup/headers
        // for screen readers to pick up the announcement
    }

    /**
     * Set up announcements (helper method)
     */
    private function setupAnnouncements(): void
    {
        // Initialize announcement system
    }
}

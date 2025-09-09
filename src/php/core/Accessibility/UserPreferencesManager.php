<?php
/**
 * TPT Government Platform - User Preferences Manager
 *
 * Manages accessibility user preferences and settings.
 */

namespace Core\Accessibility;

class UserPreferencesManager
{
    private array $defaultPreferences = [
        'high_contrast' => false,
        'large_text' => false,
        'reduced_motion' => false,
        'screen_reader' => false,
        'keyboard_navigation' => true,
        'color_blindness_mode' => null,
        'font_family' => 'default',
        'font_size' => 'medium',
        'line_spacing' => 'normal',
        'letter_spacing' => 'normal'
    ];

    private array $accessibilityProfiles = [
        'motor_impaired' => [
            'large_click_targets' => true,
            'keyboard_navigation' => true,
            'reduced_motion' => true,
            'sticky_keys' => true,
            'slow_keys' => true
        ],
        'visually_impaired' => [
            'high_contrast' => true,
            'large_text' => true,
            'screen_reader' => true,
            'zoom_level' => 150,
            'color_blindness_mode' => 'high_contrast'
        ],
        'cognitively_impaired' => [
            'plain_language' => true,
            'reduced_motion' => true,
            'progress_indicators' => true,
            'error_prevention' => true,
            'help_and_support' => true
        ],
        'hearing_impaired' => [
            'visual_alerts' => true,
            'caption_generation' => true,
            'transcript_generation' => true,
            'sign_language_support' => true
        ],
        'elderly_users' => [
            'large_text' => true,
            'high_contrast' => true,
            'reduced_motion' => true,
            'simple_navigation' => true,
            'voice_commands' => true
        ]
    ];

    /**
     * Get user preferences
     */
    public function getUserPreferences(int $userId): array
    {
        // In a real implementation, this would load from database
        // For now, return defaults
        return $this->defaultPreferences;
    }

    /**
     * Set user preferences
     */
    public function setUserPreferences(int $userId, array $preferences): array
    {
        // Validate preferences
        $validatedPreferences = $this->validatePreferences($preferences);

        // In a real implementation, this would save to database
        // For now, just return the validated preferences
        return $validatedPreferences;
    }

    /**
     * Apply accessibility profile
     */
    public function applyAccessibilityProfile(int $userId, string $profileName): array
    {
        if (!isset($this->accessibilityProfiles[$profileName])) {
            throw new \InvalidArgumentException("Accessibility profile '{$profileName}' not found");
        }

        $profileSettings = $this->accessibilityProfiles[$profileName];
        return $this->setUserPreferences($userId, $profileSettings);
    }

    /**
     * Get available accessibility profiles
     */
    public function getAccessibilityProfiles(): array
    {
        return array_keys($this->accessibilityProfiles);
    }

    /**
     * Validate preferences
     */
    private function validatePreferences(array $preferences): array
    {
        $validated = [];

        foreach ($preferences as $key => $value) {
            if (isset($this->defaultPreferences[$key])) {
                $validated[$key] = $this->validatePreferenceValue($key, $value);
            }
        }

        return $validated;
    }

    /**
     * Validate individual preference value
     */
    private function validatePreferenceValue(string $key, $value)
    {
        switch ($key) {
            case 'high_contrast':
            case 'large_text':
            case 'reduced_motion':
            case 'screen_reader':
            case 'keyboard_navigation':
                return (bool) $value;

            case 'color_blindness_mode':
                $validModes = ['protanopia', 'deuteranopia', 'tritanopia', 'high_contrast', null];
                return in_array($value, $validModes) ? $value : null;

            case 'font_family':
                $validFonts = ['default', 'sans-serif', 'serif', 'monospace'];
                return in_array($value, $validFonts) ? $value : 'default';

            case 'font_size':
                $validSizes = ['small', 'medium', 'large', 'extra-large'];
                return in_array($value, $validSizes) ? $value : 'medium';

            case 'line_spacing':
            case 'letter_spacing':
                $validSpacing = ['normal', 'relaxed', 'loose'];
                return in_array($value, $validSpacing) ? $value : 'normal';

            default:
                return $value;
        }
    }

    /**
     * Export user preferences
     */
    public function exportPreferences(int $userId): array
    {
        $preferences = $this->getUserPreferences($userId);
        return [
            'user_id' => $userId,
            'preferences' => $preferences,
            'exported_at' => time(),
            'version' => '1.0'
        ];
    }

    /**
     * Import user preferences
     */
    public function importPreferences(int $userId, array $preferencesData): array
    {
        if (!isset($preferencesData['preferences'])) {
            throw new \InvalidArgumentException('Invalid preferences data format');
        }

        return $this->setUserPreferences($userId, $preferencesData['preferences']);
    }
}

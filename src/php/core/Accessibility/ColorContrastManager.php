<?php
/**
 * TPT Government Platform - Color Contrast Manager
 *
 * Specialized manager for color contrast analysis and visual accessibility
 */

namespace Core\Accessibility;

class ColorContrastManager
{
    /**
     * WCAG contrast requirements
     */
    private const WCAG_AA_NORMAL = 4.5;
    private const WCAG_AA_LARGE = 3.0;
    private const WCAG_AAA_NORMAL = 7.0;
    private const WCAG_AAA_LARGE = 4.5;

    /**
     * Color contrast configuration
     */
    private array $config = [
        'enabled' => true,
        'standard' => 'WCAG_AA',
        'auto_analysis' => true,
        'color_blindness_support' => true,
        'high_contrast_mode' => true,
        'color_suggestions' => true
    ];

    /**
     * Color blindness simulation matrices
     */
    private array $colorBlindnessMatrices = [
        'protanopia' => [
            [0.567, 0.433, 0.000],
            [0.558, 0.442, 0.000],
            [0.000, 0.242, 0.758]
        ],
        'deuteranopia' => [
            [0.625, 0.375, 0.000],
            [0.700, 0.300, 0.000],
            [0.000, 0.300, 0.700]
        ],
        'tritanopia' => [
            [0.950, 0.050, 0.000],
            [0.000, 0.433, 0.567],
            [0.000, 0.475, 0.525]
        ]
    ];

    /**
     * Cached contrast calculations
     */
    private array $contrastCache = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Analyze contrast between two colors
     */
    public function analyzeContrast(string $foreground, string $background): array
    {
        $cacheKey = $foreground . ':' . $background;

        if (isset($this->contrastCache[$cacheKey])) {
            return $this->contrastCache[$cacheKey];
        }

        // Parse colors
        $fgRgb = $this->parseColor($foreground);
        $bgRgb = $this->parseColor($background);

        if (!$fgRgb || !$bgRgb) {
            return [
                'error' => 'Invalid color format',
                'ratio' => 1.0,
                'passes_aa' => false,
                'passes_aaa' => false
            ];
        }

        // Calculate contrast ratio
        $ratio = $this->calculateContrastRatio($fgRgb, $bgRgb);

        // Determine compliance
        $passesAa = $this->passesAA($ratio);
        $passesAaa = $this->passesAAA($ratio);

        // Generate suggestions if needed
        $suggestions = [];
        if (!$passesAa) {
            $suggestions = $this->generateColorSuggestions($fgRgb, $bgRgb);
        }

        $result = [
            'ratio' => round($ratio, 2),
            'passes_aa' => $passesAa,
            'passes_aaa' => $passesAaa,
            'level' => $this->getContrastLevel($ratio),
            'suggestions' => $suggestions,
            'foreground_rgb' => $fgRgb,
            'background_rgb' => $bgRgb,
            'luminance_fg' => $this->calculateLuminance($fgRgb),
            'luminance_bg' => $this->calculateLuminance($bgRgb)
        ];

        // Cache result
        $this->contrastCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Simulate color for different types of color blindness
     */
    public function simulateColorBlindness(string $color, string $type = 'deuteranopia'): array
    {
        $rgb = $this->parseColor($color);
        if (!$rgb) {
            return ['error' => 'Invalid color format'];
        }

        if (!isset($this->colorBlindnessMatrices[$type])) {
            return ['error' => 'Unsupported color blindness type'];
        }

        $matrix = $this->colorBlindnessMatrices[$type];
        $simulatedRgb = $this->applyColorMatrix($rgb, $matrix);

        return [
            'original' => $rgb,
            'simulated' => $simulatedRgb,
            'type' => $type,
            'hex' => $this->rgbToHex($simulatedRgb)
        ];
    }

    /**
     * Generate high contrast color palette
     */
    public function generateHighContrastPalette(string $baseColor, int $count = 5): array
    {
        $baseRgb = $this->parseColor($baseColor);
        if (!$baseRgb) {
            return ['error' => 'Invalid base color'];
        }

        $palette = [];
        $luminance = $this->calculateLuminance($baseRgb);

        for ($i = 0; $i < $count; $i++) {
            // Generate variations with high contrast
            $variation = $this->generateHighContrastVariation($baseRgb, $luminance > 0.5, $i);
            $contrast = $this->analyzeContrast(
                $this->rgbToHex($variation),
                $luminance > 0.5 ? '#000000' : '#FFFFFF'
            );

            $palette[] = [
                'color' => $this->rgbToHex($variation),
                'rgb' => $variation,
                'contrast_ratio' => $contrast['ratio'],
                'passes_aa' => $contrast['passes_aa']
            ];
        }

        return $palette;
    }

    /**
     * Suggest accessible color combinations
     */
    public function suggestAccessibleColors(string $background, string $context = 'text'): array
    {
        $bgRgb = $this->parseColor($background);
        if (!$bgRgb) {
            return ['error' => 'Invalid background color'];
        }

        $suggestions = [];

        // Generate suggestions based on context
        switch ($context) {
            case 'text':
                $suggestions = $this->suggestTextColors($bgRgb);
                break;
            case 'link':
                $suggestions = $this->suggestLinkColors($bgRgb);
                break;
            case 'button':
                $suggestions = $this->suggestButtonColors($bgRgb);
                break;
            case 'border':
                $suggestions = $this->suggestBorderColors($bgRgb);
                break;
        }

        return [
            'background' => $background,
            'context' => $context,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Check if color combination meets WCAG standards
     */
    public function checkCompliance(string $foreground, string $background, string $standard = 'AA', bool $largeText = false): array
    {
        $analysis = $this->analyzeContrast($foreground, $background);

        $threshold = $this->getContrastThreshold($standard, $largeText);
        $passes = $analysis['ratio'] >= $threshold;

        return [
            'passes' => $passes,
            'required_ratio' => $threshold,
            'actual_ratio' => $analysis['ratio'],
            'deficit' => max(0, $threshold - $analysis['ratio']),
            'recommendations' => $passes ? [] : $this->getComplianceRecommendations($analysis, $threshold)
        ];
    }

    /**
     * Analyze entire color palette for accessibility
     */
    public function analyzePalette(array $colors): array
    {
        $results = [];
        $issues = [];

        // Check all color combinations
        foreach ($colors as $i => $color1) {
            foreach ($colors as $j => $color2) {
                if ($i !== $j) {
                    $analysis = $this->analyzeContrast($color1, $color2);

                    if (!$analysis['passes_aa']) {
                        $issues[] = [
                            'colors' => [$color1, $color2],
                            'ratio' => $analysis['ratio'],
                            'required' => self::WCAG_AA_NORMAL
                        ];
                    }

                    $results["{$color1}_{$color2}"] = $analysis;
                }
            }
        }

        return [
            'total_combinations' => count($results),
            'passing_combinations' => count($results) - count($issues),
            'issues' => $issues,
            'compliance_rate' => count($results) > 0 ?
                round(((count($results) - count($issues)) / count($results)) * 100, 1) : 0,
            'recommendations' => $this->getPaletteRecommendations($issues)
        ];
    }

    /**
     * Get color contrast statistics
     */
    public function getStats(): array
    {
        return [
            'cache_size' => count($this->contrastCache),
            'supported_color_blindness_types' => array_keys($this->colorBlindnessMatrices),
            'standard' => $this->config['standard'],
            'auto_analysis' => $this->config['auto_analysis'],
            'color_blindness_support' => $this->config['color_blindness_support']
        ];
    }

    /**
     * Clear contrast cache
     */
    public function clearCache(): void
    {
        $this->contrastCache = [];
    }

    // Helper methods

    private function parseColor(string $color): ?array
    {
        $color = trim($color);

        // Hex color
        if (preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $color, $matches)) {
            $hex = $matches[1];
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2))
            ];
        }

        // RGB color
        if (preg_match('/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/', $color, $matches)) {
            return [
                'r' => (int)$matches[1],
                'g' => (int)$matches[2],
                'b' => (int)$matches[3]
            ];
        }

        return null;
    }

    private function calculateContrastRatio(array $fgRgb, array $bgRgb): float
    {
        $l1 = $this->calculateLuminance($fgRgb);
        $l2 = $this->calculateLuminance($bgRgb);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function calculateLuminance(array $rgb): float
    {
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        // Convert to linear RGB
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function passesAA(float $ratio): bool
    {
        return $ratio >= self::WCAG_AA_NORMAL;
    }

    private function passesAAA(float $ratio): bool
    {
        return $ratio >= self::WCAG_AAA_NORMAL;
    }

    private function getContrastLevel(float $ratio): string
    {
        if ($ratio >= self::WCAG_AAA_NORMAL) {
            return 'AAA';
        } elseif ($ratio >= self::WCAG_AA_NORMAL) {
            return 'AA';
        } elseif ($ratio >= self::WCAG_AA_LARGE) {
            return 'AA (Large Text)';
        } else {
            return 'Fail';
        }
    }

    private function getContrastThreshold(string $standard, bool $largeText): float
    {
        switch ($standard) {
            case 'AAA':
                return $largeText ? self::WCAG_AAA_LARGE : self::WCAG_AAA_NORMAL;
            case 'AA':
            default:
                return $largeText ? self::WCAG_AA_LARGE : self::WCAG_AA_NORMAL;
        }
    }

    private function rgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    private function applyColorMatrix(array $rgb, array $matrix): array
    {
        $r = $rgb['r'] * $matrix[0][0] + $rgb['g'] * $matrix[0][1] + $rgb['b'] * $matrix[0][2];
        $g = $rgb['r'] * $matrix[1][0] + $rgb['g'] * $matrix[1][1] + $rgb['b'] * $matrix[1][2];
        $b = $rgb['r'] * $matrix[2][0] + $rgb['g'] * $matrix[2][1] + $rgb['b'] * $matrix[2][2];

        return [
            'r' => max(0, min(255, round($r))),
            'g' => max(0, min(255, round($g))),
            'b' => max(0, min(255, round($b)))
        ];
    }

    private function generateHighContrastVariation(array $baseRgb, bool $isLight, int $index): array
    {
        $factor = 0.1 + ($index * 0.15); // Vary the adjustment factor

        if ($isLight) {
            // Darken for light backgrounds
            return [
                'r' => max(0, round($baseRgb['r'] * (1 - $factor))),
                'g' => max(0, round($baseRgb['g'] * (1 - $factor))),
                'b' => max(0, round($baseRgb['b'] * (1 - $factor)))
            ];
        } else {
            // Lighten for dark backgrounds
            return [
                'r' => min(255, round($baseRgb['r'] + (255 - $baseRgb['r']) * $factor)),
                'g' => min(255, round($baseRgb['g'] + (255 - $baseRgb['g']) * $factor)),
                'b' => min(255, round($baseRgb['b'] + (255 - $baseRgb['b']) * $factor))
            ];
        }
    }

    private function generateColorSuggestions(array $fgRgb, array $bgRgb): array
    {
        $suggestions = [];

        // Suggest darker foreground for light backgrounds
        if ($this->calculateLuminance($bgRgb) > 0.5) {
            $suggestions[] = '#000000'; // Black
            $suggestions[] = '#1a1a1a'; // Dark gray
        } else {
            $suggestions[] = '#ffffff'; // White
            $suggestions[] = '#f0f0f0'; // Light gray
        }

        return $suggestions;
    }

    private function suggestTextColors(array $bgRgb): array
    {
        // Suggest high contrast text colors
        $bgLuminance = $this->calculateLuminance($bgRgb);

        if ($bgLuminance > 0.5) {
            return [
                ['color' => '#000000', 'name' => 'Black'],
                ['color' => '#1a1a1a', 'name' => 'Dark Gray'],
                ['color' => '#2c2c2c', 'name' => 'Charcoal']
            ];
        } else {
            return [
                ['color' => '#ffffff', 'name' => 'White'],
                ['color' => '#f0f0f0', 'name' => 'Light Gray'],
                ['color' => '#e0e0e0', 'name' => 'Off White']
            ];
        }
    }

    private function suggestLinkColors(array $bgRgb): array
    {
        return [
            ['color' => '#0066cc', 'name' => 'Blue'],
            ['color' => '#0052a3', 'name' => 'Dark Blue'],
            ['color' => '#008000', 'name' => 'Green']
        ];
    }

    private function suggestButtonColors(array $bgRgb): array
    {
        return [
            ['color' => '#007bff', 'name' => 'Primary Blue'],
            ['color' => '#28a745', 'name' => 'Success Green'],
            ['color' => '#dc3545', 'name' => 'Danger Red']
        ];
    }

    private function suggestBorderColors(array $bgRgb): array
    {
        return [
            ['color' => '#cccccc', 'name' => 'Light Gray'],
            ['color' => '#999999', 'name' => 'Medium Gray'],
            ['color' => '#666666', 'name' => 'Dark Gray']
        ];
    }

    private function getComplianceRecommendations(array $analysis, float $threshold): array
    {
        $recommendations = [];

        if ($analysis['ratio'] < $threshold) {
            $recommendations[] = "Increase contrast ratio to at least {$threshold}:1";
            $recommendations[] = 'Consider using darker text on light backgrounds';
            $recommendations[] = 'Consider using lighter text on dark backgrounds';
            $recommendations[] = 'Test with color blindness simulators';
        }

        return $recommendations;
    }

    private function getPaletteRecommendations(array $issues): array
    {
        if (empty($issues)) {
            return ['All color combinations meet accessibility standards'];
        }

        $recommendations = [];
        $recommendations[] = count($issues) . ' color combinations need improvement';
        $recommendations[] = 'Consider using a predefined accessible color palette';
        $recommendations[] = 'Test all color combinations with automated tools';

        return $recommendations;
    }
}

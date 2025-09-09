<?php
/**
 * TPT Government Platform - Accessibility Audit Engine
 *
 * Automated accessibility auditing and compliance checking.
 */

namespace Core\Accessibility;

class AccessibilityAuditEngine
{
    private array $wcagCriteria;
    private array $auditResults;
    private array $remediationSuggestions;

    public function __construct()
    {
        $this->initializeWCAGCriteria();
        $this->auditResults = [];
        $this->remediationSuggestions = [];
    }

    /**
     * Initialize WCAG 2.1 AA criteria
     */
    private function initializeWCAGCriteria(): void
    {
        $this->wcagCriteria = [
            '1.1.1' => [
                'name' => 'Non-text Content',
                'level' => 'A',
                'description' => 'All non-text content has a text alternative',
                'check_function' => 'checkNonTextContent'
            ],
            '1.2.1' => [
                'name' => 'Audio-only and Video-only (Prerecorded)',
                'level' => 'A',
                'description' => 'Prerecorded audio-only and video-only content has alternatives',
                'check_function' => 'checkAudioVideoContent'
            ],
            '1.3.1' => [
                'name' => 'Info and Relationships',
                'level' => 'A',
                'description' => 'Information and relationships conveyed through presentation can be programmatically determined',
                'check_function' => 'checkInfoRelationships'
            ],
            '1.4.3' => [
                'name' => 'Contrast (Minimum)',
                'level' => 'AA',
                'description' => 'Contrast ratio between text and background is at least 4.5:1',
                'check_function' => 'checkColorContrast'
            ],
            '2.1.1' => [
                'name' => 'Keyboard',
                'level' => 'A',
                'description' => 'All functionality available via keyboard interface',
                'check_function' => 'checkKeyboardAccess'
            ],
            '2.4.2' => [
                'name' => 'Page Titled',
                'level' => 'A',
                'description' => 'Web pages have titles that describe topic or purpose',
                'check_function' => 'checkPageTitle'
            ],
            '3.3.2' => [
                'name' => 'Labels or Instructions',
                'level' => 'A',
                'description' => 'Labels or instructions are provided when content requires user input',
                'check_function' => 'checkFormLabels'
            ],
            '4.1.2' => [
                'name' => 'Name, Role, Value',
                'level' => 'A',
                'description' => 'For all user interface components, name and role can be programmatically determined',
                'check_function' => 'checkNameRoleValue'
            ]
        ];
    }

    /**
     * Audit HTML content for accessibility
     */
    public function auditHTML(string $html, string $url = null): array
    {
        $auditId = uniqid('audit_');
        $issues = [];
        $score = 100;

        // Load HTML into DOM for analysis
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);

        // Run checks for each WCAG criterion
        foreach ($this->wcagCriteria as $criterionId => $criterion) {
            $checkMethod = $criterion['check_function'];
            if (method_exists($this, $checkMethod)) {
                $result = $this->$checkMethod($dom, $xpath);
                if (!$result['passed']) {
                    $issues[] = [
                        'criterion' => $criterionId,
                        'name' => $criterion['name'],
                        'level' => $criterion['level'],
                        'description' => $criterion['description'],
                        'issues' => $result['issues'],
                        'severity' => $this->calculateSeverity($criterion['level'], count($result['issues']))
                    ];
                    $score -= $this->calculateScorePenalty($criterion['level'], count($result['issues']));
                }
            }
        }

        // Ensure score doesn't go below 0
        $score = max(0, $score);

        $this->auditResults[$auditId] = [
            'id' => $auditId,
            'url' => $url,
            'timestamp' => time(),
            'score' => $score,
            'issues' => $issues,
            'total_issues' => count($issues),
            'compliance_level' => $this->determineComplianceLevel($score),
            'remediation_suggestions' => $this->generateRemediationSuggestions($issues)
        ];

        return $this->auditResults[$auditId];
    }

    /**
     * Check for non-text content accessibility
     */
    private function checkNonTextContent(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // Check images without alt attributes
        $images = $xpath->query('//img[not(@alt) or @alt=""]');
        foreach ($images as $img) {
            $issues[] = [
                'element' => 'img',
                'src' => $img->getAttribute('src'),
                'issue' => 'Missing alt attribute',
                'suggestion' => 'Add descriptive alt text for the image'
            ];
        }

        // Check for other non-text content
        $nonTextElements = $xpath->query('//area[not(@alt)] | //input[@type="image"][not(@alt)]');
        foreach ($nonTextElements as $element) {
            $issues[] = [
                'element' => $element->tagName,
                'issue' => 'Missing alt attribute for non-text content',
                'suggestion' => 'Add appropriate alt text'
            ];
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check audio/video content
     */
    private function checkAudioVideoContent(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // Check for audio/video elements without alternatives
        $mediaElements = $xpath->query('//audio | //video');
        foreach ($mediaElements as $media) {
            $hasCaptions = $xpath->query('.//track[@kind="captions"]', $media)->length > 0;
            $hasTranscript = $xpath->query('.//a[contains(@href, "transcript")]', $media)->length > 0;

            if (!$hasCaptions && !$hasTranscript) {
                $issues[] = [
                    'element' => $media->tagName,
                    'src' => $media->getAttribute('src'),
                    'issue' => 'Missing captions or transcript',
                    'suggestion' => 'Add captions or link to transcript'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check information relationships
     */
    private function checkInfoRelationships(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // Check for proper heading hierarchy
        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $headingLevels = [];
        foreach ($headings as $heading) {
            $level = (int) substr($heading->tagName, 1);
            $headingLevels[] = $level;
        }

        // Check for skipped heading levels
        for ($i = 1; $i < count($headingLevels); $i++) {
            if ($headingLevels[$i] > $headingLevels[$i - 1] + 1) {
                $issues[] = [
                    'element' => 'heading',
                    'issue' => 'Skipped heading level',
                    'suggestion' => 'Use proper heading hierarchy (h1, h2, h3, etc.)'
                ];
                break;
            }
        }

        // Check for tables without proper structure
        $tables = $xpath->query('//table');
        foreach ($tables as $table) {
            $hasHeaders = $xpath->query('.//th', $table)->length > 0;
            if (!$hasHeaders) {
                $issues[] = [
                    'element' => 'table',
                    'issue' => 'Table missing header cells',
                    'suggestion' => 'Add <th> elements to table headers'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check color contrast
     */
    private function checkColorContrast(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // This is a simplified check - in practice, you'd need to analyze actual colors
        // For now, we'll check for potential contrast issues
        $textElements = $xpath->query('//*[text()[normalize-space()]]');
        foreach ($textElements as $element) {
            $style = $element->getAttribute('style');
            if (strpos($style, 'color:') !== false && strpos($style, 'background:') !== false) {
                // Basic check for inline styles that might have contrast issues
                $issues[] = [
                    'element' => $element->tagName,
                    'issue' => 'Potential color contrast issue',
                    'suggestion' => 'Ensure contrast ratio is at least 4.5:1'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check keyboard accessibility
     */
    private function checkKeyboardAccess(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // Check for interactive elements without keyboard access
        $interactiveElements = $xpath->query('//button | //input | //select | //textarea | //a');
        foreach ($interactiveElements as $element) {
            $tabindex = $element->getAttribute('tabindex');
            if ($tabindex !== null && $tabindex < 0) {
                $issues[] = [
                    'element' => $element->tagName,
                    'issue' => 'Element removed from tab order',
                    'suggestion' => 'Ensure interactive elements are keyboard accessible'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check page title
     */
    private function checkPageTitle(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        $titleElements = $xpath->query('//title');
        if ($titleElements->length === 0) {
            $issues[] = [
                'element' => 'title',
                'issue' => 'Missing page title',
                'suggestion' => 'Add a descriptive <title> element'
            ];
        } else {
            $title = trim($titleElements->item(0)->textContent);
            if (empty($title)) {
                $issues[] = [
                    'element' => 'title',
                    'issue' => 'Empty page title',
                    'suggestion' => 'Add meaningful content to the title element'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check form labels
     */
    private function checkFormLabels(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // Check for form inputs without labels
        $inputs = $xpath->query('//input[@type="text"] | //input[@type="email"] | //input[@type="password"] | //textarea | //select');
        foreach ($inputs as $input) {
            $id = $input->getAttribute('id');
            $hasLabel = false;

            if ($id) {
                $label = $xpath->query("//label[@for='$id']");
                $hasLabel = $label->length > 0;
            }

            // Check for aria-label or aria-labelledby
            $ariaLabel = $input->getAttribute('aria-label');
            $ariaLabelledBy = $input->getAttribute('aria-labelledby');

            if (!$hasLabel && empty($ariaLabel) && empty($ariaLabelledBy)) {
                $issues[] = [
                    'element' => $input->tagName,
                    'type' => $input->getAttribute('type'),
                    'issue' => 'Form control without label',
                    'suggestion' => 'Add <label> element or aria-label attribute'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Check name, role, value
     */
    private function checkNameRoleValue(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $issues = [];

        // Check for interactive elements without proper ARIA attributes
        $interactiveElements = $xpath->query('//*[@role] | //button | //input | //select | //textarea');
        foreach ($interactiveElements as $element) {
            $role = $element->getAttribute('role');
            $ariaLabel = $element->getAttribute('aria-label');
            $ariaLabelledBy = $element->getAttribute('aria-labelledby');

            // Custom elements with role should have appropriate ARIA attributes
            if ($role && !$ariaLabel && !$ariaLabelledBy) {
                $issues[] = [
                    'element' => $element->tagName,
                    'role' => $role,
                    'issue' => 'Custom role without accessible name',
                    'suggestion' => 'Add aria-label or aria-labelledby attribute'
                ];
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Calculate severity based on WCAG level and issue count
     */
    private function calculateSeverity(string $level, int $issueCount): string
    {
        if ($level === 'A' && $issueCount > 5) {
            return 'critical';
        } elseif ($level === 'AA' && $issueCount > 3) {
            return 'high';
        } elseif ($issueCount > 10) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Calculate score penalty
     */
    private function calculateScorePenalty(string $level, int $issueCount): int
    {
        $basePenalty = $level === 'A' ? 5 : 3;
        return $basePenalty * min($issueCount, 5); // Cap at 5 issues per criterion
    }

    /**
     * Determine compliance level
     */
    private function determineComplianceLevel(int $score): string
    {
        if ($score >= 95) {
            return 'AAA';
        } elseif ($score >= 85) {
            return 'AA';
        } elseif ($score >= 70) {
            return 'A';
        } else {
            return 'Non-compliant';
        }
    }

    /**
     * Generate remediation suggestions
     */
    private function generateRemediationSuggestions(array $issues): array
    {
        $suggestions = [];

        foreach ($issues as $issue) {
            $suggestion = [
                'criterion' => $issue['criterion'],
                'priority' => $issue['severity'],
                'description' => $issue['issues'][0]['suggestion'] ?? 'Fix accessibility issue',
                'estimated_effort' => $this->estimateEffort($issue['severity'])
            ];
            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Estimate effort for fixing issues
     */
    private function estimateEffort(string $severity): string
    {
        switch ($severity) {
            case 'critical':
                return 'High - Requires immediate attention';
            case 'high':
                return 'Medium - Should be addressed soon';
            case 'medium':
                return 'Low - Can be addressed in regular maintenance';
            default:
                return 'Low';
        }
    }

    /**
     * Get audit results
     */
    public function getAuditResults(string $auditId = null): array
    {
        if ($auditId) {
            return $this->auditResults[$auditId] ?? [];
        }

        return $this->auditResults;
    }

    /**
     * Generate audit report
     */
    public function generateReport(string $auditId): array
    {
        if (!isset($this->auditResults[$auditId])) {
            throw new \InvalidArgumentException('Audit not found');
        }

        $audit = $this->auditResults[$auditId];

        return [
            'audit_id' => $auditId,
            'generated_at' => time(),
            'url' => $audit['url'],
            'compliance_score' => $audit['score'],
            'compliance_level' => $audit['compliance_level'],
            'total_issues' => $audit['total_issues'],
            'issues_by_severity' => $this->groupIssuesBySeverity($audit['issues']),
            'issues_by_criterion' => $this->groupIssuesByCriterion($audit['issues']),
            'remediation_plan' => $audit['remediation_suggestions'],
            'recommendations' => $this->generateGeneralRecommendations($audit)
        ];
    }

    /**
     * Group issues by severity
     */
    private function groupIssuesBySeverity(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $severity = $issue['severity'];
            if (!isset($grouped[$severity])) {
                $grouped[$severity] = 0;
            }
            $grouped[$severity]++;
        }
        return $grouped;
    }

    /**
     * Group issues by WCAG criterion
     */
    private function groupIssuesByCriterion(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $criterion = $issue['criterion'];
            if (!isset($grouped[$criterion])) {
                $grouped[$criterion] = [];
            }
            $grouped[$criterion][] = $issue;
        }
        return $grouped;
    }

    /**
     * Generate general recommendations
     */
    private function generateGeneralRecommendations(array $audit): array
    {
        $recommendations = [];

        if ($audit['score'] < 85) {
            $recommendations[] = 'Conduct comprehensive accessibility training for development team';
            $recommendations[] = 'Implement automated accessibility testing in CI/CD pipeline';
            $recommendations[] = 'Establish accessibility review process for all new features';
        }

        if ($audit['total_issues'] > 20) {
            $recommendations[] = 'Prioritize critical accessibility issues for immediate remediation';
            $recommendations[] = 'Consider hiring accessibility expert for consultation';
        }

        $recommendations[] = 'Regular accessibility audits should be conducted quarterly';
        $recommendations[] = 'Include accessibility testing in QA process';

        return $recommendations;
    }
}

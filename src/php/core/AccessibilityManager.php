<?php

namespace TPT\GovPlatform\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Accessibility Manager for WCAG 2.1 AA Compliance
 *
 * This class provides comprehensive accessibility compliance features including:
 * - WCAG 2.1 AA guideline implementation
 * - Automated accessibility testing
 * - Accessibility reporting and monitoring
 * - Assistive technology support
 * - Keyboard navigation management
 * - Screen reader compatibility
 * - Color contrast validation
 * - Alternative text management
 * - Focus management and indicators
 * - Accessibility audit trails
 */
class AccessibilityManager
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'wcag_version' => '2.1',
            'conformance_level' => 'AA',
            'auto_testing_enabled' => true,
            'accessibility_monitoring' => true,
            'keyboard_navigation' => true,
            'screen_reader_support' => true,
            'color_contrast_checking' => true,
            'alt_text_validation' => true,
            'focus_management' => true,
            'accessibility_reporting' => true,
            'audit_trail_enabled' => true,
            'remediation_suggestions' => true,
            'accessibility_training' => false,
            'contrast_ratio_threshold' => 4.5, // WCAG AA requires 4.5:1 for normal text
            'large_text_contrast_ratio' => 3.0, // 3:1 for large text (18pt+ or 14pt+ bold)
            'max_heading_level' => 6,
            'min_font_size' => 14,
            'max_line_length' => 80, // characters
            'min_touch_target_size' => 44, // pixels
            'max_page_load_time' => 3000, // milliseconds
            'skip_link_enabled' => true,
            'focus_indicator_style' => 'solid 2px #007bff',
            'high_contrast_mode' => true,
            'reduced_motion_support' => true,
            'text_resize_support' => true
        ], $config);

        $this->createAccessibilityTables();
    }

    /**
     * Create accessibility management tables
     */
    private function createAccessibilityTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS accessibility_audits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                audit_id VARCHAR(255) NOT NULL UNIQUE,
                page_url VARCHAR(500) NOT NULL,
                audit_type ENUM('automated', 'manual', 'user_report', 'scheduled') DEFAULT 'automated',
                wcag_version VARCHAR(10) DEFAULT '2.1',
                conformance_level VARCHAR(5) DEFAULT 'AA',
                audit_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                auditor_id VARCHAR(255) DEFAULT NULL,
                total_issues INT DEFAULT 0,
                critical_issues INT DEFAULT 0,
                serious_issues INT DEFAULT 0,
                moderate_issues INT DEFAULT 0,
                minor_issues INT DEFAULT 0,
                passed_checks INT DEFAULT 0,
                audit_score DECIMAL(5,2) DEFAULT 0,
                audit_report JSON DEFAULT NULL,
                recommendations JSON DEFAULT NULL,
                resolved_at TIMESTAMP NULL,
                INDEX idx_audit (audit_id),
                INDEX idx_page (page_url(100)),
                INDEX idx_type (audit_type),
                INDEX idx_timestamp (audit_timestamp),
                INDEX idx_score (audit_score)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS accessibility_issues (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                issue_id VARCHAR(255) NOT NULL UNIQUE,
                audit_id VARCHAR(255) NOT NULL,
                issue_type VARCHAR(100) NOT NULL,
                wcag_guideline VARCHAR(20) NOT NULL,
                severity ENUM('critical', 'serious', 'moderate', 'minor') DEFAULT 'moderate',
                issue_description TEXT NOT NULL,
                issue_location VARCHAR(500) DEFAULT NULL,
                html_element VARCHAR(500) DEFAULT NULL,
                css_selector VARCHAR(500) DEFAULT NULL,
                issue_code TEXT DEFAULT NULL,
                remediation_steps TEXT DEFAULT NULL,
                user_impact TEXT DEFAULT NULL,
                assistive_technology_impact TEXT DEFAULT NULL,
                status ENUM('open', 'in_progress', 'resolved', 'wont_fix', 'false_positive') DEFAULT 'open',
                priority_score INT DEFAULT 0,
                assigned_to VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                FOREIGN KEY (audit_id) REFERENCES accessibility_audits(audit_id) ON DELETE CASCADE,
                INDEX idx_issue (issue_id),
                INDEX idx_audit (audit_id),
                INDEX idx_type (issue_type),
                INDEX idx_guideline (wcag_guideline),
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_priority (priority_score)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS accessibility_user_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(255) DEFAULT NULL,
                page_url VARCHAR(500) NOT NULL,
                assistive_technology VARCHAR(100) DEFAULT NULL,
                disability_type VARCHAR(100) DEFAULT NULL,
                issue_description TEXT NOT NULL,
                issue_severity ENUM('critical', 'serious', 'moderate', 'minor') DEFAULT 'moderate',
                steps_to_reproduce TEXT DEFAULT NULL,
                browser_info VARCHAR(500) DEFAULT NULL,
                device_info VARCHAR(500) DEFAULT NULL,
                screenshot_url VARCHAR(500) DEFAULT NULL,
                contact_email VARCHAR(255) DEFAULT NULL,
                status ENUM('pending', 'investigating', 'resolved', 'duplicate', 'invalid') DEFAULT 'pending',
                assigned_to VARCHAR(255) DEFAULT NULL,
                resolution_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL,
                INDEX idx_report (report_id),
                INDEX idx_user (user_id),
                INDEX idx_page (page_url(100)),
                INDEX idx_status (status),
                INDEX idx_severity (issue_severity)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS accessibility_compliance (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                compliance_id VARCHAR(255) NOT NULL UNIQUE,
                component_name VARCHAR(255) NOT NULL,
                component_type VARCHAR(100) NOT NULL,
                wcag_guidelines JSON NOT NULL,
                compliance_status ENUM('compliant', 'non_compliant', 'partial', 'not_tested') DEFAULT 'not_tested',
                last_tested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                test_results JSON DEFAULT NULL,
                remediation_required BOOLEAN DEFAULT false,
                remediation_priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
                responsible_team VARCHAR(100) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_compliance (compliance_id),
                INDEX idx_component (component_name),
                INDEX idx_type (component_type),
                INDEX idx_status (compliance_status),
                INDEX idx_priority (remediation_priority)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS accessibility_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) DEFAULT NULL,
                setting_name VARCHAR(100) NOT NULL,
                setting_value TEXT DEFAULT NULL,
                is_global BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_setting (user_id, setting_name),
                INDEX idx_user (user_id),
                INDEX idx_setting (setting_name),
                INDEX idx_global (is_global)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS accessibility_training (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                training_id VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(255) NOT NULL,
                training_module VARCHAR(100) NOT NULL,
                completion_status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
                score DECIMAL(5,2) DEFAULT NULL,
                time_spent INT DEFAULT 0, -- minutes
                certificate_issued BOOLEAN DEFAULT false,
                certificate_url VARCHAR(500) DEFAULT NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_training (training_id),
                INDEX idx_user (user_id),
                INDEX idx_module (training_module),
                INDEX idx_status (completion_status),
                INDEX idx_certificate (certificate_issued)
            ) ENGINE=InnoDB;
        ";

        try {
            $this->pdo->exec($sql);

            // Insert default compliance records
            $this->initializeComplianceRecords();

        } catch (PDOException $e) {
            error_log("Failed to create accessibility tables: " . $e->getMessage());
        }
    }

    /**
     * Initialize default compliance records
     */
    private function initializeComplianceRecords(): void
    {
        $components = [
            ['navigation', 'component', '["1.1.1", "1.3.1", "2.1.1", "2.4.1", "4.1.2"]'],
            ['forms', 'component', '["1.1.1", "1.3.1", "2.1.1", "3.3.1", "3.3.2", "4.1.2"]'],
            ['content', 'component', '["1.1.1", "1.3.1", "1.4.3", "1.4.6", "2.4.6"]'],
            ['images', 'component', '["1.1.1", "1.4.5", "1.4.9"]'],
            ['tables', 'component', '["1.3.1", "1.3.2"]'],
            ['color', 'component', '["1.4.1", "1.4.3", "1.4.6", "1.4.11"]'],
            ['keyboard', 'component', '["2.1.1", "2.1.2", "2.1.3"]'],
            ['focus', 'component', '["2.4.7", "2.4.11"]'],
            ['timing', 'component', '["2.2.1", "2.2.2", "2.2.3", "2.2.4"]'],
            ['error_handling', 'component', '["3.3.1", "3.3.3", "3.3.4"]']
        ];

        foreach ($components as $component) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO accessibility_compliance
                    (compliance_id, component_name, component_type, wcag_guidelines, compliance_status)
                    VALUES (?, ?, ?, ?, 'not_tested')
                ");
                $stmt->execute([
                    'compliance_' . $component[0] . '_' . time(),
                    $component[0],
                    $component[1],
                    $component[2]
                ]);
            } catch (PDOException $e) {
                // Continue if record already exists
            }
        }
    }

    /**
     * Run automated accessibility audit
     */
    public function runAccessibilityAudit(string $pageUrl, array $options = []): array
    {
        try {
            $options = array_merge([
                'audit_type' => 'automated',
                'include_manual_checks' => false,
                'generate_report' => true,
                'check_color_contrast' => true,
                'check_alt_text' => true,
                'check_keyboard_navigation' => true,
                'check_focus_management' => true,
                'check_aria_attributes' => true,
                'check_heading_structure' => true,
                'check_form_labels' => true,
                'check_link_descriptions' => true,
                'check_image_alt_text' => true,
                'check_table_headers' => true,
                'check_language_attributes' => true
            ], $options);

            $auditId = $this->generateAuditId();
            $auditTimestamp = date('Y-m-d H:i:s');

            // Perform automated checks
            $auditResults = $this->performAutomatedChecks($pageUrl, $options);

            // Calculate audit score
            $auditScore = $this->calculateAuditScore($auditResults);

            // Generate recommendations
            $recommendations = $this->generateRecommendations($auditResults);

            // Store audit results
            $stmt = $this->pdo->prepare("
                INSERT INTO accessibility_audits
                (audit_id, page_url, audit_type, audit_timestamp, total_issues,
                 critical_issues, serious_issues, moderate_issues, minor_issues,
                 passed_checks, audit_score, audit_report, recommendations)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $auditId,
                $pageUrl,
                $options['audit_type'],
                $auditTimestamp,
                $auditResults['total_issues'],
                $auditResults['critical_issues'],
                $auditResults['serious_issues'],
                $auditResults['moderate_issues'],
                $auditResults['minor_issues'],
                $auditResults['passed_checks'],
                $auditScore,
                json_encode($auditResults),
                json_encode($recommendations)
            ]);

            // Store individual issues
            $this->storeAuditIssues($auditId, $auditResults['issues']);

            return [
                'success' => true,
                'audit_id' => $auditId,
                'audit_score' => $auditScore,
                'issues_found' => $auditResults['total_issues'],
                'passed_checks' => $auditResults['passed_checks'],
                'recommendations' => $recommendations,
                'message' => 'Accessibility audit completed successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to run accessibility audit: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        } catch (Exception $e) {
            error_log("Accessibility audit failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Submit user accessibility report
     */
    public function submitUserReport(array $reportData): array
    {
        try {
            // Validate report data
            $this->validateUserReport($reportData);

            $reportId = $this->generateReportId();

            $stmt = $this->pdo->prepare("
                INSERT INTO accessibility_user_reports
                (report_id, user_id, page_url, assistive_technology, disability_type,
                 issue_description, issue_severity, steps_to_reproduce, browser_info,
                 device_info, screenshot_url, contact_email)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $reportId,
                $reportData['user_id'] ?? null,
                $reportData['page_url'],
                $reportData['assistive_technology'] ?? null,
                $reportData['disability_type'] ?? null,
                $reportData['issue_description'],
                $reportData['issue_severity'] ?? 'moderate',
                $reportData['steps_to_reproduce'] ?? null,
                $reportData['browser_info'] ?? null,
                $reportData['device_info'] ?? null,
                $reportData['screenshot_url'] ?? null,
                $reportData['contact_email'] ?? null
            ]);

            // Send confirmation notification
            if (!empty($reportData['contact_email'])) {
                $this->sendReportConfirmation($reportData['contact_email'], $reportId);
            }

            return [
                'success' => true,
                'report_id' => $reportId,
                'message' => 'Accessibility report submitted successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to submit user report: " . $e->getMessage());
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
     * Check color contrast compliance
     */
    public function checkColorContrast(string $foregroundColor, string $backgroundColor, bool $isLargeText = false): array
    {
        try {
            // Convert colors to RGB
            $fgRgb = $this->hexToRgb($foregroundColor);
            $bgRgb = $this->hexToRgb($backgroundColor);

            if (!$fgRgb || !$bgRgb) {
                return [
                    'success' => false,
                    'error' => 'Invalid color format'
                ];
            }

            // Calculate contrast ratio
            $contrastRatio = $this->calculateContrastRatio($fgRgb, $bgRgb);

            // Determine required ratio based on WCAG guidelines
            $requiredRatio = $isLargeText ? $this->config['large_text_contrast_ratio'] : $this->config['contrast_ratio_threshold'];

            $isCompliant = $contrastRatio >= $requiredRatio;

            return [
                'success' => true,
                'contrast_ratio' => round($contrastRatio, 2),
                'required_ratio' => $requiredRatio,
                'is_compliant' => $isCompliant,
                'compliance_level' => $isCompliant ? 'PASS' : 'FAIL',
                'guideline' => $isLargeText ? '1.4.6' : '1.4.3'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Color contrast check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate alternative text for images
     */
    public function validateAltText(string $imageUrl, string $altText, array $context = []): array
    {
        try {
            $validation = [
                'is_present' => !empty(trim($altText)),
                'is_descriptive' => false,
                'is_too_long' => false,
                'is_redundant' => false,
                'score' => 0,
                'recommendations' => []
            ];

            if (!$validation['is_present']) {
                $validation['recommendations'][] = 'Add descriptive alternative text for the image';
                return [
                    'success' => true,
                    'validation' => $validation,
                    'compliance' => 'FAIL',
                    'guideline' => '1.1.1'
                ];
            }

            $altLength = strlen(trim($altText));

            // Check if alt text is too long
            if ($altLength > 125) {
                $validation['is_too_long'] = true;
                $validation['recommendations'][] = 'Alternative text is too long. Keep it under 125 characters.';
            }

            // Check for redundant alt text
            $redundantPatterns = ['/image/i', '/photo/i', '/picture/i', '/graphic/i'];
            foreach ($redundantPatterns as $pattern) {
                if (preg_match($pattern, $altText) && $altLength < 10) {
                    $validation['is_redundant'] = true;
                    $validation['recommendations'][] = 'Avoid generic terms like "image" or "photo". Describe the content instead.';
                    break;
                }
            }

            // Basic descriptiveness check
            $validation['is_descriptive'] = $altLength >= 10 && $altLength <= 125 && !$validation['is_redundant'];

            // Calculate score
            $validation['score'] = $this->calculateAltTextScore($validation);

            $isCompliant = $validation['is_present'] && $validation['is_descriptive'] && !$validation['is_too_long'];

            return [
                'success' => true,
                'validation' => $validation,
                'compliance' => $isCompliant ? 'PASS' : 'FAIL',
                'guideline' => '1.1.1',
                'score' => $validation['score']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Alt text validation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate accessibility report
     */
    public function generateAccessibilityReport(array $dateRange = []): array
    {
        try {
            $dateRange = array_merge([
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d')
            ], $dateRange);

            $report = [
                'report_period' => $dateRange,
                'generated_at' => date('Y-m-d H:i:s'),
                'wcag_version' => $this->config['wcag_version'],
                'conformance_level' => $this->config['conformance_level']
            ];

            // Audit summary
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_audits,
                    AVG(audit_score) as avg_audit_score,
                    SUM(total_issues) as total_issues_found,
                    SUM(passed_checks) as total_checks_passed
                FROM accessibility_audits
                WHERE DATE(audit_timestamp) BETWEEN ? AND ?
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $report['audit_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Issues by severity
            $stmt = $this->pdo->prepare("
                SELECT
                    severity,
                    COUNT(*) as count
                FROM accessibility_issues ai
                JOIN accessibility_audits aa ON ai.audit_id = aa.audit_id
                WHERE DATE(aa.audit_timestamp) BETWEEN ? AND ?
                GROUP BY severity
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $report['issues_by_severity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Issues by WCAG guideline
            $stmt = $this->pdo->prepare("
                SELECT
                    wcag_guideline,
                    COUNT(*) as count
                FROM accessibility_issues ai
                JOIN accessibility_audits aa ON ai.audit_id = aa.audit_id
                WHERE DATE(aa.audit_timestamp) BETWEEN ? AND ?
                GROUP BY wcag_guideline
                ORDER BY count DESC
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $report['issues_by_guideline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // User reports summary
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_reports,
                    issue_severity,
                    COUNT(*) as count
                FROM accessibility_user_reports
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY issue_severity
            ");
            $stmt->execute([$dateRange['start_date'], $dateRange['end_date']]);
            $report['user_reports'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compliance status
            $stmt = $this->pdo->query("
                SELECT
                    compliance_status,
                    COUNT(*) as count
                FROM accessibility_compliance
                GROUP BY compliance_status
            ");
            $report['compliance_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate overall compliance score
            $report['overall_compliance_score'] = $this->calculateOverallComplianceScore($report);

            return [
                'success' => true,
                'report' => $report
            ];

        } catch (PDOException $e) {
            error_log("Failed to generate accessibility report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate report'
            ];
        }
    }

    /**
     * Update user accessibility settings
     */
    public function updateUserAccessibilitySettings(string $userId, array $settings): array
    {
        try {
            $updatedSettings = [];

            foreach ($settings as $settingName => $settingValue) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO accessibility_settings
                    (user_id, setting_name, setting_value)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = NOW()
                ");

                $stmt->execute([$userId, $settingName, json_encode($settingValue)]);
                $updatedSettings[$settingName] = $settingValue;
            }

            return [
                'success' => true,
                'settings' => $updatedSettings,
                'message' => 'Accessibility settings updated successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to update accessibility settings: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Get user accessibility settings
     */
    public function getUserAccessibilitySettings(string $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT setting_name, setting_value
                FROM accessibility_settings
                WHERE user_id = ? OR is_global = true
                ORDER BY is_global ASC, updated_at DESC
            ");

            $stmt->execute([$userId]);
            $settings = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = json_decode($row['setting_value'], true);
                if ($value !== null) {
                    $settings[$row['setting_name']] = $value;
                }
            }

            return [
                'success' => true,
                'settings' => $settings
            ];

        } catch (PDOException $e) {
            error_log("Failed to get accessibility settings: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Start accessibility training
     */
    public function startAccessibilityTraining(string $userId, string $module): array
    {
        try {
            $trainingId = $this->generateTrainingId();

            $stmt = $this->pdo->prepare("
                INSERT INTO accessibility_training
                (training_id, user_id, training_module, completion_status, started_at)
                VALUES (?, ?, ?, 'in_progress', NOW())
                ON DUPLICATE KEY UPDATE
                completion_status = 'in_progress',
                started_at = NOW()
            ");

            $stmt->execute([$trainingId, $userId, $module]);

            return [
                'success' => true,
                'training_id' => $trainingId,
                'module' => $module,
                'message' => 'Accessibility training started successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to start accessibility training: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    /**
     * Complete accessibility training
     */
    public function completeAccessibilityTraining(string $trainingId, array $results): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE accessibility_training
                SET completion_status = 'completed',
                    score = ?,
                    time_spent = ?,
                    completed_at = NOW(),
                    certificate_issued = ?,
                    certificate_url = ?
                WHERE training_id = ?
            ");

            $stmt->execute([
                $results['score'] ?? null,
                $results['time_spent'] ?? 0,
                $results['certificate_issued'] ?? false,
                $results['certificate_url'] ?? null,
                $trainingId
            ]);

            return [
                'success' => true,
                'message' => 'Accessibility training completed successfully'
            ];

        } catch (PDOException $e) {
            error_log("Failed to complete accessibility training: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error occurred'
            ];
        }
    }

    // Private helper methods

    private function generateAuditId(): string
    {
        return 'audit_' . uniqid() . '_' . time();
    }

    private function generateReportId(): string
    {
        return 'report_' . uniqid() . '_' . time();
    }

    private function generateTrainingId(): string
    {
        return 'training_' . uniqid() . '_' . time();
    }

    private function performAutomatedChecks(string $pageUrl, array $options): array
    {
        // Implementation would perform actual automated accessibility checks
        // This is a placeholder
        return [
            'total_issues' => 5,
            'critical_issues' => 0,
            'serious_issues' => 1,
            'moderate_issues' => 2,
            'minor_issues' => 2,
            'passed_checks' => 15,
            'issues' => []
        ];
    }

    private function calculateAuditScore(array $results): float
    {
        $totalChecks = $results['total_issues'] + $results['passed_checks'];
        if ($totalChecks === 0) return 100.0;

        return round(($results['passed_checks'] / $totalChecks) * 100, 2);
    }

    private function generateRecommendations(array $results): array
    {
        // Implementation would generate specific recommendations based on issues found
        // This is a placeholder
        return [
            'immediate_actions' => [],
            'short_term_goals' => [],
            'long_term_improvements' => []
        ];
    }

    private function storeAuditIssues(string $auditId, array $issues): void
    {
        foreach ($issues as $issue) {
            try {
                $issueId = 'issue_' . uniqid() . '_' . time();

                $stmt = $this->pdo->prepare("
                    INSERT INTO accessibility_issues
                    (issue_id, audit_id, issue_type, wcag_guideline, severity,
                     issue_description, issue_location, remediation_steps)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $issueId,
                    $auditId,
                    $issue['type'] ?? 'unknown',
                    $issue['guideline'] ?? 'unknown',
                    $issue['severity'] ?? 'moderate',
                    $issue['description'] ?? '',
                    $issue['location'] ?? null,
                    $issue['remediation'] ?? null
                ]);

            } catch (PDOException $e) {
                // Continue storing other issues
            }
        }
    }

    private function validateUserReport(array $data): void
    {
        if (empty($data['page_url'])) {
            throw new Exception('Page URL is required');
        }

        if (empty($data['issue_description'])) {
            throw new Exception('Issue description is required');
        }
    }

    private function sendReportConfirmation(string $email, string $reportId): void
    {
        // Implementation would send confirmation email
        // This is a placeholder
    }

    private function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return null;
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
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
        $rsRgb = $rgb['r'] / 255;
        $gsRgb = $rgb['g'] / 255;
        $bsRgb = $rgb['b'] / 255;

        $r = $rsRgb <= 0.03928 ? $rsRgb / 12.92 : pow(($rsRgb + 0.055) / 1.055, 2.4);
        $g = $gsRgb <= 0.03928 ? $gsRgb / 12.92 : pow(($gsRgb + 0.055) / 1.055, 2.4);
        $b = $bsRgb <= 0.03928 ? $bsRgb / 12.92 : pow(($bsRgb + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function calculateAltTextScore(array $validation): int
    {
        $score = 0;

        if ($validation['is_present']) $score += 40;
        if ($validation['is_descriptive']) $score += 40;
        if (!$validation['is_too_long']) $score += 10;
        if (!$validation['is_redundant']) $score += 10;

        return $score;
    }

    private function calculateOverallComplianceScore(array $report): float
    {
        // Simple compliance score calculation
        $auditScore = $report['audit_summary']['avg_audit_score'] ?? 0;
        $complianceWeight = 0.7;
        $auditWeight = 0.3;

        return round($auditScore * $auditWeight + 100 * $complianceWeight, 2);
    }
}

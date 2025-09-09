<?php
/**
 * TPT Government Platform - AI Form Assistant
 *
 * AI-powered form assistance with intelligent suggestions,
 * auto-completion, and form optimization features
 */

namespace Modules\FormsBuilder;

use Core\AIService;

class AIFormAssistant
{
    /**
     * AI service instance
     */
    private AIService $aiService;

    /**
     * Form analysis cache
     */
    private array $analysisCache = [];

    /**
     * Suggestion cache
     */
    private array $suggestionCache = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->aiService = new AIService();
    }

    /**
     * Analyze form and provide suggestions
     */
    public function analyzeForm(array $formSchema): array
    {
        $cacheKey = md5(json_encode($formSchema));
        if (isset($this->analysisCache[$cacheKey])) {
            return $this->analysisCache[$cacheKey];
        }

        $analysis = [
            'field_suggestions' => $this->suggestFieldImprovements($formSchema),
            'logic_suggestions' => $this->suggestConditionalLogic($formSchema),
            'usability_suggestions' => $this->analyzeUsability($formSchema),
            'completion_estimate' => $this->estimateCompletionTime($formSchema),
            'accessibility_score' => $this->calculateAccessibilityScore($formSchema),
            'optimization_suggestions' => $this->suggestOptimizations($formSchema)
        ];

        $this->analysisCache[$cacheKey] = $analysis;
        return $analysis;
    }

    /**
     * Provide intelligent field suggestions
     */
    public function suggestFieldValue(string $fieldId, array $field, array $formData, string $partialValue = ''): array
    {
        $cacheKey = md5($fieldId . json_encode($field) . json_encode($formData) . $partialValue);
        if (isset($this->suggestionCache[$cacheKey])) {
            return $this->suggestionCache[$cacheKey];
        }

        $suggestions = [];

        switch ($field['field_type']) {
            case 'address_autocomplete':
                $suggestions = $this->suggestAddresses($partialValue);
                break;
            case 'entity_lookup':
                $suggestions = $this->suggestEntities($field, $partialValue);
                break;
            case 'ai_autofill':
                $suggestions = $this->generateSmartSuggestions($field, $formData);
                break;
            case 'text':
            case 'textarea':
                $suggestions = $this->suggestTextCompletions($field, $partialValue, $formData);
                break;
            case 'select':
            case 'radio':
                $suggestions = $this->suggestOptionSelections($field, $formData);
                break;
            default:
                $suggestions = $this->getGenericSuggestions($field, $formData);
        }

        $this->suggestionCache[$cacheKey] = $suggestions;
        return $suggestions;
    }

    /**
     * Generate smart form suggestions based on user context
     */
    public function generateSmartSuggestions(array $field, array $formData): array
    {
        $prompt = $this->buildSmartSuggestionPrompt($field, $formData);

        try {
            $response = $this->aiService->generateText($prompt, [
                'max_tokens' => 150,
                'temperature' => 0.7,
                'model' => 'gpt-3.5-turbo'
            ]);

            return $this->parseAISuggestions($response);
        } catch (\Exception $e) {
            return $this->getFallbackSuggestions($field);
        }
    }

    /**
     * Analyze form completion patterns
     */
    public function analyzeCompletionPatterns(array $submissions): array
    {
        $patterns = [
            'common_drop_off_points' => [],
            'field_completion_rates' => [],
            'time_spent_per_field' => [],
            'error_patterns' => [],
            'improvement_suggestions' => []
        ];

        if (empty($submissions)) {
            return $patterns;
        }

        // Analyze completion rates
        $totalSubmissions = count($submissions);
        $fieldCounts = [];

        foreach ($submissions as $submission) {
            $data = json_decode($submission['data'], true);
            foreach ($data as $fieldId => $value) {
                if (!isset($fieldCounts[$fieldId])) {
                    $fieldCounts[$fieldId] = 0;
                }
                if (!empty($value)) {
                    $fieldCounts[$fieldId]++;
                }
            }
        }

        // Calculate completion rates
        foreach ($fieldCounts as $fieldId => $count) {
            $patterns['field_completion_rates'][$fieldId] = [
                'completed' => $count,
                'total' => $totalSubmissions,
                'rate' => round(($count / $totalSubmissions) * 100, 2)
            ];
        }

        // Identify drop-off points
        $patterns['common_drop_off_points'] = $this->identifyDropOffPoints($submissions);

        // Generate improvement suggestions
        $patterns['improvement_suggestions'] = $this->generateImprovementSuggestions($patterns);

        return $patterns;
    }

    /**
     * Optimize form layout based on AI analysis
     */
    public function optimizeFormLayout(array $formSchema, array $analytics): array
    {
        $optimizedSchema = $formSchema;

        // Reorder fields based on completion patterns
        if (isset($analytics['field_completion_rates'])) {
            $optimizedSchema = $this->reorderFieldsByCompletion($formSchema, $analytics['field_completion_rates']);
        }

        // Group related fields
        $optimizedSchema = $this->groupRelatedFields($optimizedSchema);

        // Add progress indicators for long forms
        if (count($optimizedSchema['fields'] ?? []) > 10) {
            $optimizedSchema = $this->addProgressIndicators($optimizedSchema);
        }

        // Optimize field types
        $optimizedSchema = $this->optimizeFieldTypes($optimizedSchema);

        return $optimizedSchema;
    }

    /**
     * Detect form fraud and suspicious submissions
     */
    public function detectFormFraud(array $submission, array $historicalData = []): array
    {
        $riskScore = 0;
        $flags = [];

        // Check submission speed (too fast)
        if (isset($submission['time_spent']) && $submission['time_spent'] < 30) {
            $riskScore += 20;
            $flags[] = 'unusually_fast_completion';
        }

        // Check for repeated identical values
        $data = json_decode($submission['data'], true);
        if ($this->hasRepeatedPatterns($data)) {
            $riskScore += 15;
            $flags[] = 'repeated_patterns';
        }

        // Check IP reputation
        if (isset($submission['ip_address'])) {
            $ipRisk = $this->checkIPRReputation($submission['ip_address']);
            $riskScore += $ipRisk['score'];
            if (!empty($ipRisk['flags'])) {
                $flags = array_merge($flags, $ipRisk['flags']);
            }
        }

        // Check against historical patterns
        if (!empty($historicalData)) {
            $patternRisk = $this->checkHistoricalPatterns($submission, $historicalData);
            $riskScore += $patternRisk['score'];
            if (!empty($patternRisk['flags'])) {
                $flags = array_merge($flags, $patternRisk['flags']);
            }
        }

        return [
            'risk_score' => min($riskScore, 100),
            'risk_level' => $this->calculateRiskLevel($riskScore),
            'flags' => array_unique($flags),
            'recommendations' => $this->getFraudRecommendations($riskScore, $flags)
        ];
    }

    /**
     * Generate form from natural language description
     */
    public function generateFormFromDescription(string $description, string $formType = 'general'): array
    {
        $prompt = $this->buildFormGenerationPrompt($description, $formType);

        try {
            $response = $this->aiService->generateText($prompt, [
                'max_tokens' => 1000,
                'temperature' => 0.3,
                'model' => 'gpt-4'
            ]);

            return $this->parseGeneratedForm($response);
        } catch (\Exception $e) {
            return $this->getFallbackFormTemplate($formType);
        }
    }

    /**
     * Provide real-time form assistance
     */
    public function getRealTimeAssistance(string $fieldId, array $field, array $currentData, string $userInput): array
    {
        $assistance = [
            'suggestions' => [],
            'warnings' => [],
            'tips' => [],
            'auto_corrections' => []
        ];

        // Get field-specific suggestions
        $assistance['suggestions'] = $this->suggestFieldValue($fieldId, $field, $currentData, $userInput);

        // Check for potential issues
        $assistance['warnings'] = $this->identifyPotentialIssues($field, $userInput, $currentData);

        // Provide helpful tips
        $assistance['tips'] = $this->getContextualTips($field, $currentData);

        // Auto-correct common mistakes
        $assistance['auto_corrections'] = $this->suggestAutoCorrections($field, $userInput);

        return $assistance;
    }

    // Private helper methods

    private function suggestFieldImprovements(array $formSchema): array
    {
        $suggestions = [];

        foreach ($formSchema['fields'] ?? [] as $field) {
            $fieldType = $field['field_type'];

            // Suggest better field types
            if ($fieldType === 'text' && isset($field['validation_rules']['email'])) {
                $suggestions[] = [
                    'field' => $field['field_id'],
                    'type' => 'optimization',
                    'message' => 'Consider using email field type for better validation and UX',
                    'suggested_type' => 'email'
                ];
            }

            // Suggest adding placeholders
            if (empty($field['placeholder']) && in_array($fieldType, ['text', 'textarea', 'email'])) {
                $suggestions[] = [
                    'field' => $field['field_id'],
                    'type' => 'ux_improvement',
                    'message' => 'Add a placeholder to guide users on expected input'
                ];
            }

            // Suggest help text for complex fields
            if (empty($field['help_text']) && in_array($fieldType, ['date_range', 'coordinates', 'matrix_rating'])) {
                $suggestions[] = [
                    'field' => $field['field_id'],
                    'type' => 'ux_improvement',
                    'message' => 'Add help text to explain how to use this field type'
                ];
            }
        }

        return $suggestions;
    }

    private function suggestConditionalLogic(array $formSchema): array
    {
        $suggestions = [];
        $fields = $formSchema['fields'] ?? [];

        // Look for fields that could benefit from conditional logic
        foreach ($fields as $i => $field) {
            if ($field['field_type'] === 'select' || $field['field_type'] === 'radio') {
                // Check if there are related fields that could be conditional
                for ($j = $i + 1; $j < count($fields); $j++) {
                    if ($this->areFieldsRelated($field, $fields[$j])) {
                        $suggestions[] = [
                            'type' => 'conditional_logic',
                            'message' => "Consider making '{$fields[$j]['label']}' conditional based on '{$field['label']}' selection",
                            'trigger_field' => $field['field_id'],
                            'target_field' => $fields[$j]['field_id']
                        ];
                        break;
                    }
                }
            }
        }

        return $suggestions;
    }

    private function analyzeUsability(array $formSchema): array
    {
        $issues = [];
        $fields = $formSchema['fields'] ?? [];

        // Check form length
        if (count($fields) > 15) {
            $issues[] = [
                'severity' => 'medium',
                'message' => 'Form is quite long. Consider breaking it into multiple steps or sections.',
                'suggestion' => 'Use progress indicators and logical grouping'
            ];
        }

        // Check for required fields clustering
        $requiredCount = 0;
        foreach ($fields as $field) {
            if (($field['required'] ?? false)) {
                $requiredCount++;
            }
        }

        if ($requiredCount > 8) {
            $issues[] = [
                'severity' => 'low',
                'message' => 'Many required fields may overwhelm users.',
                'suggestion' => 'Consider making some fields optional or providing clear guidance'
            ];
        }

        // Check field type variety
        $fieldTypes = array_column($fields, 'field_type');
        if (count(array_unique($fieldTypes)) < 3) {
            $issues[] = [
                'severity' => 'low',
                'message' => 'Limited field type variety may make the form less engaging.',
                'suggestion' => 'Consider using more interactive field types where appropriate'
            ];
        }

        return $issues;
    }

    private function estimateCompletionTime(array $formSchema): int
    {
        $fields = $formSchema['fields'] ?? [];
        $estimatedTime = 0;

        foreach ($fields as $field) {
            $fieldType = $field['field_type'];

            // Base time estimates per field type (in seconds)
            $timeEstimates = [
                'text' => 15,
                'textarea' => 30,
                'email' => 20,
                'phone' => 25,
                'number' => 20,
                'date' => 30,
                'date_range' => 45,
                'select' => 15,
                'radio' => 20,
                'checkbox' => 15,
                'file_upload' => 60,
                'address_autocomplete' => 40,
                'coordinates' => 35,
                'signature_capture' => 45,
                'matrix_rating' => 60,
                'rich_text' => 90
            ];

            $estimatedTime += $timeEstimates[$fieldType] ?? 30;

            // Add time for required fields (users think more)
            if (($field['required'] ?? false)) {
                $estimatedTime += 10;
            }

            // Add time for complex validation
            if (!empty($field['validation_rules'])) {
                $estimatedTime += 15;
            }
        }

        // Add overhead time
        $overhead = count($fields) * 5; // 5 seconds per field for navigation
        $estimatedTime += $overhead;

        return $estimatedTime;
    }

    private function calculateAccessibilityScore(array $formSchema): array
    {
        $score = 100;
        $issues = [];
        $fields = $formSchema['fields'] ?? [];

        foreach ($fields as $field) {
            // Check for labels
            if (empty($field['label'])) {
                $score -= 10;
                $issues[] = 'Missing field label';
            }

            // Check for help text on complex fields
            if (empty($field['help_text']) && in_array($field['field_type'], ['date_range', 'coordinates', 'matrix_rating'])) {
                $score -= 5;
                $issues[] = 'Complex field missing help text';
            }

            // Check for placeholders on input fields
            if (empty($field['placeholder']) && in_array($field['field_type'], ['text', 'email', 'phone'])) {
                $score -= 3;
                $issues[] = 'Input field missing placeholder';
            }
        }

        return [
            'score' => max(0, $score),
            'grade' => $this->getAccessibilityGrade($score),
            'issues' => $issues
        ];
    }

    private function suggestOptimizations(array $formSchema): array
    {
        $suggestions = [];

        // Check for field grouping opportunities
        $fields = $formSchema['fields'] ?? [];
        if (count($fields) > 8) {
            $suggestions[] = [
                'type' => 'organization',
                'message' => 'Consider grouping related fields into sections',
                'impact' => 'high'
            ];
        }

        // Suggest mobile optimization
        $mobileUnfriendlyTypes = ['matrix_rating', 'dynamic_table'];
        $hasMobileUnfriendly = false;
        foreach ($fields as $field) {
            if (in_array($field['field_type'], $mobileUnfriendlyTypes)) {
                $hasMobileUnfriendly = true;
                break;
            }
        }

        if ($hasMobileUnfriendly) {
            $suggestions[] = [
                'type' => 'mobile_optimization',
                'message' => 'Some field types may not work well on mobile devices',
                'impact' => 'medium'
            ];
        }

        return $suggestions;
    }

    private function suggestAddresses(string $partial): array
    {
        // This would integrate with a geocoding service
        // For now, return mock suggestions
        if (empty($partial)) {
            return [];
        }

        return [
            $partial . ' Main Street',
            $partial . ' Oak Avenue',
            $partial . ' Park Road',
            $partial . ' Elm Street'
        ];
    }

    private function suggestEntities(array $field, string $partial): array
    {
        // This would search a database of entities
        // For now, return mock suggestions
        if (empty($partial)) {
            return [];
        }

        return [
            'Entity: ' . $partial . ' Corp',
            'Entity: ' . $partial . ' LLC',
            'Entity: ' . $partial . ' Inc'
        ];
    }

    private function suggestTextCompletions(array $field, string $partial, array $formData): array
    {
        // Use AI to suggest text completions
        if (strlen($partial) < 3) {
            return [];
        }

        $context = $this->buildContextFromFormData($formData);
        $prompt = "Complete this text based on the context: '{$partial}'. Context: {$context}";

        try {
            $response = $this->aiService->generateText($prompt, [
                'max_tokens' => 50,
                'temperature' => 0.8
            ]);

            return [$response];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function suggestOptionSelections(array $field, array $formData): array
    {
        // Analyze form data to suggest most likely selections
        $options = $field['field_options']['options'] ?? [];

        // This would use historical data to rank options
        // For now, return all options
        return array_keys($options);
    }

    private function getGenericSuggestions(array $field, array $formData): array
    {
        // Provide generic helpful suggestions based on field type
        $suggestions = [];

        switch ($field['field_type']) {
            case 'phone':
                $suggestions = ['Use format: (123) 456-7890'];
                break;
            case 'date':
                $suggestions = ['Use format: MM/DD/YYYY'];
                break;
            case 'postal_code':
                $suggestions = ['Use format: 12345 or 12345-6789'];
                break;
        }

        return $suggestions;
    }

    private function buildSmartSuggestionPrompt(array $field, array $formData): string
    {
        $fieldType = $field['field_type'];
        $label = $field['label'];

        $context = "Form field: {$label} (type: {$fieldType})\n";
        $context .= "Form context: " . json_encode($formData) . "\n";
        $context .= "Provide 3-5 intelligent suggestions for this field value.";

        return $context;
    }

    private function parseAISuggestions(string $response): array
    {
        // Parse AI response into structured suggestions
        $lines = explode("\n", trim($response));
        $suggestions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !preg_match('/^\d+\./', $line)) {
                $line = preg_replace('/^\d+\.\s*/', '', $line);
                $suggestions[] = $line;
            }
        }

        return array_slice($suggestions, 0, 5);
    }

    private function getFallbackSuggestions(array $field): array
    {
        // Provide fallback suggestions when AI is unavailable
        return [
            'Sample suggestion 1',
            'Sample suggestion 2',
            'Sample suggestion 3'
        ];
    }

    private function identifyDropOffPoints(array $submissions): array
    {
        $dropOffs = [];

        foreach ($submissions as $submission) {
            $data = json_decode($submission['data'], true);
            $incompleteFields = [];

            foreach ($data as $fieldId => $value) {
                if (empty($value)) {
                    $incompleteFields[] = $fieldId;
                }
            }

            if (!empty($incompleteFields)) {
                $dropOffs[] = [
                    'submission_id' => $submission['submission_id'],
                    'incomplete_fields' => $incompleteFields,
                    'last_completed_field' => $this->findLastCompletedField($data)
                ];
            }
        }

        return $dropOffs;
    }

    private function generateImprovementSuggestions(array $patterns): array
    {
        $suggestions = [];

        // Analyze completion rates
        if (isset($patterns['field_completion_rates'])) {
            foreach ($patterns['field_completion_rates'] as $fieldId => $stats) {
                if ($stats['rate'] < 70) {
                    $suggestions[] = [
                        'type' => 'field_improvement',
                        'field' => $fieldId,
                        'message' => "Low completion rate ({$stats['rate']}%). Consider simplifying or adding help text.",
                        'priority' => 'high'
                    ];
                }
            }
        }

        // Analyze drop-off points
        if (!empty($patterns['common_drop_off_points'])) {
            $suggestions[] = [
                'type' => 'form_structure',
                'message' => 'Users are dropping off at specific points. Consider reordering fields or adding progress indicators.',
                'priority' => 'medium'
            ];
        }

        return $suggestions;
    }

    private function reorderFieldsByCompletion(array $formSchema, array $completionRates): array
    {
        $fields = $formSchema['fields'] ?? [];

        // Sort fields by completion rate (highest first)
        usort($fields, function($a, $b) use ($completionRates) {
            $rateA = $completionRates[$a['field_id']]['rate'] ?? 50;
            $rateB = $completionRates[$b['field_id']]['rate'] ?? 50;
            return $rateB <=> $rateA;
        });

        $formSchema['fields'] = $fields;
        return $formSchema;
    }

    private function groupRelatedFields(array $formSchema): array
    {
        // This would use AI to identify related fields and group them
        // For now, return unchanged
        return $formSchema;
    }

    private function addProgressIndicators(array $formSchema): array
    {
        $formSchema['settings']['show_progress'] = true;
        $formSchema['settings']['progress_type'] = 'steps';

        return $formSchema;
    }

    private function optimizeFieldTypes(array $formSchema): array
    {
        // This would suggest better field types based on usage patterns
        // For now, return unchanged
        return $formSchema;
    }

    private function hasRepeatedPatterns(array $data): bool
    {
        $values = array_values($data);
        $uniqueValues = array_unique($values);

        // If more than 70% of values are the same, flag as suspicious
        return (count($uniqueValues) / count($values)) < 0.3;
    }

    private function checkIPRReputation(string $ip): array
    {
        // This would integrate with IP reputation services
        // For now, return low risk
        return [
            'score' => 5,
            'flags' => []
        ];
    }

    private function checkHistoricalPatterns(array $submission, array $historicalData): array
    {
        // This would analyze patterns against historical submissions
        // For now, return low risk
        return [
            'score' => 0,
            'flags' => []
        ];
    }

    private function calculateRiskLevel(int $score): string
    {
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }

    private function getFraudRecommendations(int $score, array $flags): array
    {
        $recommendations = [];

        if ($score >= 70) {
            $recommendations[] = 'Flag for manual review';
            $recommendations[] = 'Request additional verification';
        } elseif ($score >= 40) {
            $recommendations[] = 'Monitor closely';
            $recommendations[] = 'Log for pattern analysis';
        }

        return $recommendations;
    }

    private function buildFormGenerationPrompt(string $description, string $formType): string
    {
        return "Generate a form schema based on this description: '{$description}'.
                Form type: {$formType}
                Return a JSON structure with fields array containing field definitions.
                Each field should have: field_id, field_type, label, required, validation_rules.";
    }

    private function parseGeneratedForm(string $response): array
    {
        // Try to parse JSON from AI response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            $parsed = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        // Fallback to basic form structure
        return $this->getFallbackFormTemplate('general');
    }

    private function getFallbackFormTemplate(string $formType): array
    {
        return [
            'title' => 'Generated Form',
            'description' => 'Auto-generated form',
            'fields' => [
                [
                    'field_id' => 'name',
                    'field_type' => 'text',
                    'label' => 'Name',
                    'required' => true
                ],
                [
                    'field_id' => 'email',
                    'field_type' => 'email',
                    'label' => 'Email',
                    'required' => true
                ]
            ]
        ];
    }

    private function identifyPotentialIssues(array $field, string $userInput, array $currentData): array
    {
        $warnings = [];

        // Check for common issues based on field type
        switch ($field['field_type']) {
            case 'email':
                if (!filter_var($userInput, FILTER_VALIDATE_EMAIL)) {
                    $warnings[] = 'Email format appears invalid';
                }
                break;
            case 'phone':
                if (!preg_match('/[\d\s\-\(\)\+]{10,}/', $userInput)) {
                    $warnings[] = 'Phone number format may be incorrect';
                }
                break;
            case 'date':
                if (strtotime($userInput) === false) {
                    $warnings[] = 'Date format may be incorrect';
                }
                break;
        }

        return $warnings;
    }

    private function getContextualTips(array $field, array $currentData): array
    {
        $tips = [];

        // Provide contextual help based on field type and form progress
        switch ($field['field_type']) {
            case 'file_upload':
                $tips[] = 'Accepted formats: PDF, DOC, JPG. Max size: 10MB';
                break;
            case 'address_autocomplete':
                $tips[] = 'Start typing your address for suggestions';
                break;
            case 'coordinates':
                $tips[] = 'Click on the map or enter latitude,longitude';
                break;
        }

        return $tips;
    }

    private function suggestAutoCorrections(array $field, string $userInput): array
    {
        $corrections = [];

        // Suggest auto-corrections for common mistakes
        switch ($field['field_type']) {
            case 'email':
                // Fix common email typos
                $corrections = $this->suggestEmailCorrections($userInput);
                break;
            case 'phone':
                // Format phone numbers
                $corrections = $this->suggestPhoneFormatting($userInput);
                break;
        }

        return $corrections;
    }

    private function suggestEmailCorrections(string $email): array
    {
        $corrections = [];

        // Common domain corrections
        $corrections = [
            'gmail.co' => 'gmail.com',
            'yahoo.co' => 'yahoo.com',
            'hotmail.co' => 'hotmail.com'
        ];

        foreach ($corrections as $wrong => $right) {
            if (strpos($email, $wrong) !== false) {
                $corrections[] = str_replace($wrong, $right, $email);
            }
        }

        return $corrections;
    }

    private function suggestPhoneFormatting(string $phone): array
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            // US format
            return ['(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6)];
        }

        return [];
    }

    private function areFieldsRelated(array $field1, array $field2): bool
    {
        // Simple heuristic for field relationships
        $label1 = strtolower($field1['label']);
        $label2 = strtolower($field2['label']);

        $relatedTerms = [
            ['address', 'city', 'state', 'zip', 'postal'],
            ['phone', 'mobile', 'home', 'work'],
            ['name', 'first', 'last', 'middle'],
            ['date', 'time', 'start', 'end']
        ];

        foreach ($relatedTerms as $group) {
            $matches1 = 0;
            $matches2 = 0;

            foreach ($group as $term) {
                if (strpos($label1, $term) !== false) $matches1++;
                if (strpos($label2, $term) !== false) $matches2++;
            }

            if ($matches1 > 0 && $matches2 > 0) {
                return true;
            }
        }

        return false;
    }

    private function findLastCompletedField(array $data): ?string
    {
        $lastCompleted = null;

        foreach ($data as $fieldId => $value) {
            if (!empty($value)) {
                $lastCompleted = $fieldId;
            } else {
                break; // Stop at first empty field
            }
        }

        return $lastCompleted;
    }

    private function buildContextFromFormData(array $formData): string
    {
        $context = '';

        foreach ($formData as $key => $value) {
            if (!empty($value) && !is_array($value)) {
                $context .= "{$key}: {$value}; ";
            }
        }

        return trim($context);
    }

    private function getAccessibilityGrade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}

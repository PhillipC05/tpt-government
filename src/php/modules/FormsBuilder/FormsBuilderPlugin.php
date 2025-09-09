<?php
/**
 * TPT Government Platform - Forms Builder Plugin
 *
 * Plugin interface for the Forms Builder module providing
 * extensibility and integration capabilities
 */

namespace Modules\FormsBuilder;

use Core\Plugin;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;

class FormsBuilderPlugin extends Plugin
{
    /**
     * Plugin metadata
     */
    protected array $metadata = [
        'name' => 'Forms Builder Plugin',
        'version' => '2.0.0',
        'description' => 'Plugin interface for advanced form building capabilities',
        'author' => 'TPT Government Platform',
        'type' => 'module_plugin'
    ];

    /**
     * Plugin hooks
     */
    protected array $hooks = [
        'form_pre_submit' => 'onFormPreSubmit',
        'form_post_submit' => 'onFormPostSubmit',
        'form_validation' => 'onFormValidation',
        'form_render' => 'onFormRender',
        'field_render' => 'onFieldRender',
        'form_analytics' => 'onFormAnalytics'
    ];

    /**
     * Custom field types registry
     */
    private array $customFieldTypes = [];

    /**
     * Custom validation rules
     */
    private array $customValidationRules = [];

    /**
     * Plugin initialization
     */
    public function initialize(): bool
    {
        // Register custom field types
        $this->registerCustomFieldTypes();

        // Register custom validation rules
        $this->registerCustomValidationRules();

        // Set up plugin hooks
        $this->setupHooks();

        return true;
    }

    /**
     * Register custom field types
     */
    private function registerCustomFieldTypes(): void
    {
        // Government-specific field types
        $this->customFieldTypes = [
            'tax_id_field' => [
                'name' => 'Tax ID Field',
                'icon' => 'fa-id-card',
                'category' => 'government',
                'handler' => [$this, 'renderTaxIdField'],
                'validator' => [$this, 'validateTaxIdField'],
                'supports' => ['validation', 'formatting', 'masking']
            ],
            'social_security_field' => [
                'name' => 'Social Security Number',
                'icon' => 'fa-user-shield',
                'category' => 'government',
                'handler' => [$this, 'renderSSNField'],
                'validator' => [$this, 'validateSSNField'],
                'supports' => ['masking', 'encryption', 'validation']
            ],
            'license_plate_field' => [
                'name' => 'License Plate Field',
                'icon' => 'fa-car',
                'category' => 'government',
                'handler' => [$this, 'renderLicensePlateField'],
                'validator' => [$this, 'validateLicensePlateField'],
                'supports' => ['validation', 'auto_format', 'state_detection']
            ],
            'passport_field' => [
                'name' => 'Passport Field',
                'icon' => 'fa-passport',
                'category' => 'government',
                'handler' => [$this, 'renderPassportField'],
                'validator' => [$this, 'validatePassportField'],
                'supports' => ['validation', 'country_detection', 'expiry_check']
            ],
            'medicare_field' => [
                'name' => 'Medicare Number',
                'icon' => 'fa-heartbeat',
                'category' => 'healthcare',
                'handler' => [$this, 'renderMedicareField'],
                'validator' => [$this, 'validateMedicareField'],
                'supports' => ['validation', 'formatting', 'checksum']
            ],
            'business_number_field' => [
                'name' => 'Business Number',
                'icon' => 'fa-building',
                'category' => 'business',
                'handler' => [$this, 'renderBusinessNumberField'],
                'validator' => [$this, 'validateBusinessNumberField'],
                'supports' => ['validation', 'lookup', 'auto_fill']
            ],
            'property_id_field' => [
                'name' => 'Property ID',
                'icon' => 'fa-home',
                'category' => 'property',
                'handler' => [$this, 'renderPropertyIdField'],
                'validator' => [$this, 'validatePropertyIdField'],
                'supports' => ['validation', 'lookup', 'auto_fill', 'map_integration']
            ],
            'permit_number_field' => [
                'name' => 'Permit Number',
                'icon' => 'fa-file-contract',
                'category' => 'permitting',
                'handler' => [$this, 'renderPermitNumberField'],
                'validator' => [$this, 'validatePermitNumberField'],
                'supports' => ['validation', 'lookup', 'status_check', 'auto_fill']
            ]
        ];
    }

    /**
     * Register custom validation rules
     */
    private function registerCustomValidationRules(): void
    {
        $this->customValidationRules = [
            'australian_business_number' => [
                'message' => 'Please enter a valid Australian Business Number (ABN)',
                'validator' => [$this, 'validateABN']
            ],
            'new_zealand_ird_number' => [
                'message' => 'Please enter a valid New Zealand IRD number',
                'validator' => [$this, 'validateIRDNumber']
            ],
            'canadian_sin' => [
                'message' => 'Please enter a valid Canadian Social Insurance Number',
                'validator' => [$this, 'validateCanadianSIN']
            ],
            'us_ein' => [
                'message' => 'Please enter a valid US Employer Identification Number',
                'validator' => [$this, 'validateUSEIN']
            ],
            'iban' => [
                'message' => 'Please enter a valid International Bank Account Number',
                'validator' => [$this, 'validateIBAN']
            ],
            'swift_bic' => [
                'message' => 'Please enter a valid SWIFT/BIC code',
                'validator' => [$this, 'validateSWIFTBIC']
            ],
            'medicare_australia' => [
                'message' => 'Please enter a valid Australian Medicare number',
                'validator' => [$this, 'validateMedicareAustralia']
            ],
            'nhs_uk' => [
                'message' => 'Please enter a valid UK NHS number',
                'validator' => [$this, 'validateNHSUK']
            ]
        ];
    }

    /**
     * Setup plugin hooks
     */
    private function setupHooks(): void
    {
        // Hook into form submission process
        add_action('form_pre_submit', [$this, 'onFormPreSubmit'], 10, 2);
        add_action('form_post_submit', [$this, 'onFormPostSubmit'], 10, 2);
        add_action('form_validation', [$this, 'onFormValidation'], 10, 2);
        add_action('form_render', [$this, 'onFormRender'], 10, 2);
        add_action('field_render', [$this, 'onFieldRender'], 10, 3);
        add_action('form_analytics', [$this, 'onFormAnalytics'], 10, 2);
    }

    /**
     * Hook: Before form submission
     */
    public function onFormPreSubmit(array $formData, array $metadata): array
    {
        // Add plugin-specific preprocessing
        $processedData = $this->preprocessFormData($formData);

        // Validate plugin-specific requirements
        $validation = $this->validatePluginRequirements($processedData);
        if (!$validation['valid']) {
            throw new \Exception('Plugin validation failed: ' . implode(', ', $validation['errors']));
        }

        return $processedData;
    }

    /**
     * Hook: After form submission
     */
    public function onFormPostSubmit(array $submissionData, array $metadata): void
    {
        // Process plugin-specific post-submission tasks
        $this->processPostSubmission($submissionData, $metadata);

        // Send plugin-specific notifications
        $this->sendPluginNotifications($submissionData, $metadata);

        // Update plugin-specific analytics
        $this->updatePluginAnalytics($submissionData);
    }

    /**
     * Hook: Form validation
     */
    public function onFormValidation(array $formData, array $fieldErrors): array
    {
        // Add plugin-specific validation
        $pluginErrors = $this->validatePluginSpecificRules($formData);

        return array_merge($fieldErrors, $pluginErrors);
    }

    /**
     * Hook: Form rendering
     */
    public function onFormRender(array $formSchema, array $context): array
    {
        // Add plugin-specific form modifications
        $modifiedSchema = $this->modifyFormSchema($formSchema, $context);

        // Add plugin-specific CSS/JS
        $this->enqueuePluginAssets($formSchema);

        return $modifiedSchema;
    }

    /**
     * Hook: Field rendering
     */
    public function onFieldRender(array $field, string $fieldHtml, array $context): string
    {
        // Add plugin-specific field modifications
        if (isset($this->customFieldTypes[$field['field_type']])) {
            $fieldHtml = $this->renderCustomField($field, $context);
        }

        // Add plugin-specific field enhancements
        $fieldHtml = $this->enhanceFieldHtml($field, $fieldHtml, $context);

        return $fieldHtml;
    }

    /**
     * Hook: Form analytics
     */
    public function onFormAnalytics(array $analyticsData, array $context): array
    {
        // Add plugin-specific analytics
        $pluginAnalytics = $this->generatePluginAnalytics($analyticsData, $context);

        return array_merge($analyticsData, $pluginAnalytics);
    }

    /**
     * Render custom field types
     */
    private function renderCustomField(array $field, array $context): string
    {
        $fieldType = $field['field_type'];

        if (!isset($this->customFieldTypes[$fieldType])) {
            return '';
        }

        $handler = $this->customFieldTypes[$fieldType]['handler'];
        return call_user_func($handler, $field, $context);
    }

    /**
     * Render Tax ID field
     */
    public function renderTaxIdField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Tax ID';
        $required = ($field['required'] ?? false) ? ' required' : '';
        $placeholder = $field['placeholder'] ?? 'Enter tax ID number';

        $html = "<div class='form-field tax-id-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='{$placeholder}'{$required}";
        $html .= " data-field-type='tax_id' data-mask='99-9999999'>";
        $html .= "<div class='field-help'>Format: XX-XXXXXXX</div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render SSN field
     */
    public function renderSSNField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Social Security Number';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field ssn-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='XXX-XX-XXXX'{$required}";
        $html .= " data-field-type='ssn' data-mask='999-99-9999' data-encrypt='true'>";
        $html .= "<div class='field-help'>This information is encrypted and secure</div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render License Plate field
     */
    public function renderLicensePlateField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'License Plate';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field license-plate-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<div class='license-plate-input-group'>";
        $html .= "<select name='{$fieldId}_state' id='{$fieldId}_state'{$required}>";
        $html .= "<option value=''>Select State</option>";
        // Add state options
        $html .= "</select>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='ABC-123'{$required} maxlength='10'>";
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render Passport field
     */
    public function renderPassportField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Passport Number';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field passport-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<div class='passport-input-group'>";
        $html .= "<select name='{$fieldId}_country' id='{$fieldId}_country'{$required}>";
        $html .= "<option value=''>Select Country</option>";
        // Add country options
        $html .= "</select>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='A1234567'{$required} maxlength='15'>";
        $html .= "</div>";
        $html .= "<input type='date' id='{$fieldId}_expiry' name='{$fieldId}_expiry'{$required}>";
        $html .= "<label for='{$fieldId}_expiry'>Expiry Date</label>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render Medicare field
     */
    public function renderMedicareField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Medicare Number';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field medicare-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='1234 56789 0'{$required}";
        $html .= " data-field-type='medicare' data-mask='9999 99999 9'>";
        $html .= "<div class='field-help'>Format: XXXX XXXXX X</div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render Business Number field
     */
    public function renderBusinessNumberField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Business Number';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field business-number-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<div class='business-number-input-group'>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='Enter business number'{$required}>";
        $html .= "<button type='button' class='lookup-btn' data-field='{$fieldId}'>Lookup</button>";
        $html .= "</div>";
        $html .= "<div class='business-info' id='{$fieldId}_info'></div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render Property ID field
     */
    public function renderPropertyIdField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Property ID';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field property-id-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<div class='property-id-input-group'>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='Enter property ID or address'{$required}>";
        $html .= "<button type='button' class='search-btn' data-field='{$fieldId}'>Search</button>";
        $html .= "</div>";
        $html .= "<div class='property-info' id='{$fieldId}_info'></div>";
        $html .= "<div class='property-map' id='{$fieldId}_map'></div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Render Permit Number field
     */
    public function renderPermitNumberField(array $field, array $context): string
    {
        $fieldId = $field['field_id'];
        $label = $field['label'] ?? 'Permit Number';
        $required = ($field['required'] ?? false) ? ' required' : '';

        $html = "<div class='form-field permit-number-field'>";
        $html .= "<label for='{$fieldId}'>{$label}</label>";
        $html .= "<div class='permit-number-input-group'>";
        $html .= "<input type='text' id='{$fieldId}' name='{$fieldId}'";
        $html .= " placeholder='Enter permit number'{$required}>";
        $html .= "<button type='button' class='verify-btn' data-field='{$fieldId}'>Verify</button>";
        $html .= "</div>";
        $html .= "<div class='permit-info' id='{$fieldId}_info'></div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Validation methods for custom fields
     */
    public function validateTaxIdField($value, $param = null): bool
    {
        // Basic tax ID validation - can be customized per country
        return preg_match('/^\d{2}-\d{7}$/', $value);
    }

    public function validateSSNField($value, $param = null): bool
    {
        return preg_match('/^\d{3}-\d{2}-\d{4}$/', $value);
    }

    public function validateLicensePlateField($value, $param = null): bool
    {
        // Basic license plate validation
        return preg_match('/^[A-Z0-9\-\s]{1,10}$/i', $value);
    }

    public function validatePassportField($value, $param = null): bool
    {
        // Basic passport validation
        return preg_match('/^[A-Z0-9]{6,15}$/i', $value);
    }

    public function validateMedicareField($value, $param = null): bool
    {
        // Australian Medicare number validation
        $cleaned = preg_replace('/\s/', '', $value);
        if (!preg_match('/^\d{10}$/', $cleaned)) {
            return false;
        }

        // Checksum validation for Medicare numbers
        $weights = [1, 4, 3, 7, 5, 8, 6, 9, 10];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cleaned[$i] * $weights[$i];
        }

        $checkDigit = (11 - ($sum % 11)) % 11;
        return $checkDigit == (int)$cleaned[9];
    }

    public function validateBusinessNumberField($value, $param = null): bool
    {
        // Basic business number validation
        return preg_match('/^[0-9A-Z\-]{8,15}$/i', $value);
    }

    public function validatePropertyIdField($value, $param = null): bool
    {
        // Basic property ID validation
        return !empty($value) && strlen($value) >= 3;
    }

    public function validatePermitNumberField($value, $param = null): bool
    {
        // Basic permit number validation
        return preg_match('/^[A-Z0-9\-\/]{5,20}$/i', $value);
    }

    /**
     * International validation methods
     */
    public function validateABN($value): bool
    {
        // Australian Business Number validation
        $cleaned = preg_replace('/\s/', '', $value);
        if (!preg_match('/^\d{11}$/', $cleaned)) {
            return false;
        }

        // ABN checksum validation
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum += (int)$cleaned[$i] * $weights[$i];
        }

        return $sum % 89 === 0;
    }

    public function validateIRDNumber($value): bool
    {
        // New Zealand IRD number validation
        $cleaned = preg_replace('/\s/', '', $value);
        if (!preg_match('/^\d{8,9}$/', $cleaned)) {
            return false;
        }

        // IRD checksum validation
        $weights = [3, 2, 7, 6, 5, 4, 3, 2];
        if (strlen($cleaned) === 9) {
            $weights = [7, 6, 5, 4, 3, 2, 1, 0, 0];
        }

        $sum = 0;
        for ($i = 0; $i < strlen($cleaned); $i++) {
            $sum += (int)$cleaned[$i] * $weights[$i];
        }

        return $sum % 11 === 0;
    }

    public function validateCanadianSIN($value): bool
    {
        // Canadian Social Insurance Number validation
        $cleaned = preg_replace('/\s/', '', $value);
        if (!preg_match('/^\d{9}$/', $cleaned)) {
            return false;
        }

        // SIN checksum validation
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int)$cleaned[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = $digit - 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    public function validateUSEIN($value): bool
    {
        // US Employer Identification Number validation
        return preg_match('/^\d{2}-\d{7}$/', $value);
    }

    public function validateIBAN($value): bool
    {
        // International Bank Account Number validation
        $value = strtoupper(str_replace(' ', '', $value));
        return preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value);
    }

    public function validateSWIFTBIC($value): bool
    {
        return preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper($value));
    }

    public function validateMedicareAustralia($value): bool
    {
        return $this->validateMedicareField($value);
    }

    public function validateNHSUK($value): bool
    {
        // UK NHS number validation
        $cleaned = preg_replace('/\s/', '', $value);
        if (!preg_match('/^\d{10}$/', $cleaned)) {
            return false;
        }

        // NHS checksum validation
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cleaned[$i] * (10 - $i);
        }

        $checkDigit = (11 - ($sum % 11)) % 11;
        if ($checkDigit === 10) {
            $checkDigit = 0;
        }

        return $checkDigit == (int)$cleaned[9];
    }

    /**
     * Plugin-specific helper methods
     */
    private function preprocessFormData(array $formData): array
    {
        // Add plugin-specific preprocessing logic
        foreach ($formData as $key => $value) {
            if (strpos($key, '_encrypted') !== false) {
                // Handle encrypted fields
                $formData[$key] = $this->decryptField($value);
            }
        }

        return $formData;
    }

    private function validatePluginRequirements(array $formData): array
    {
        $errors = [];

        // Add plugin-specific validation requirements
        // This could include checking for required custom fields,
        // validating against external systems, etc.

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function processPostSubmission(array $submissionData, array $metadata): void
    {
        // Process plugin-specific post-submission tasks
        // This could include sending data to external systems,
        // triggering workflows, updating related records, etc.

        // Example: Send to external API
        if (isset($submissionData['send_to_external']) && $submissionData['send_to_external']) {
            $this->sendToExternalSystem($submissionData);
        }
    }

    private function sendPluginNotifications(array $submissionData, array $metadata): void
    {
        // Send plugin-specific notifications
        $notificationManager = new NotificationManager();

        // Example: Send to specific departments based on form type
        if (isset($submissionData['department_notification'])) {
            $notificationManager->sendNotification(
                'department_form_submission',
                $submissionData['department_notification'],
                ['submission_data' => $submissionData]
            );
        }
    }

    private function updatePluginAnalytics(array $submissionData): void
    {
        // Update plugin-specific analytics
        // This could include tracking custom field usage,
        // form completion rates for specific field types, etc.
    }

    private function validatePluginSpecificRules(array $formData): array
    {
        $errors = [];

        // Add plugin-specific validation rules
        // This could include cross-field validation for custom field types,
        // external system validation, etc.

        return $errors;
    }

    private function modifyFormSchema(array $formSchema, array $context): array
    {
        // Add plugin-specific modifications to form schema
        // This could include adding custom fields, modifying existing fields,
        // adding conditional logic, etc.

        return $formSchema;
    }

    private function enqueuePluginAssets(array $formSchema): void
    {
        // Enqueue plugin-specific CSS and JavaScript files
        // This ensures that custom field types have their required assets loaded
    }

    private function enhanceFieldHtml(array $field, string $fieldHtml, array $context): string
    {
        // Add plugin-specific enhancements to field HTML
        // This could include adding data attributes, additional markup,
        // accessibility features, etc.

        return $fieldHtml;
    }

    private function generatePluginAnalytics(array $analyticsData, array $context): array
    {
        $pluginAnalytics = [];

        // Generate plugin-specific analytics
        // This could include usage statistics for custom field types,
        // error rates for specific validations, etc.

        return $pluginAnalytics;
    }

    private function decryptField(string $encryptedValue): string
    {
        // Decrypt encrypted field values
        // This would use the platform's encryption system
        return $encryptedValue; // Placeholder
    }

    private function sendToExternalSystem(array $submissionData): void
    {
        // Send submission data to external systems
        // This could integrate with government databases,
        // third-party services, etc.
    }

    /**
     * Get custom field types
     */
    public function getCustomFieldTypes(): array
    {
        return $this->customFieldTypes;
    }

    /**
     * Get custom validation rules
     */
    public function getCustomValidationRules(): array
    {
        return $this->customValidationRules;
    }

    /**
     * Add custom field type
     */
    public function addCustomFieldType(string $typeId, array $fieldTypeConfig): void
    {
        $this->customFieldTypes[$typeId] = $fieldTypeConfig;
    }

    /**
     * Add custom validation rule
     */
    public function addCustomValidationRule(string $ruleId, array $ruleConfig): void
    {
        $this->customValidationRules[$ruleId] = $ruleConfig;
    }

    /**
     * Plugin cleanup
     */
    public function cleanup(): bool
    {
        // Clean up plugin resources
        // Remove hooks, clear caches, etc.
        return true;
    }
}

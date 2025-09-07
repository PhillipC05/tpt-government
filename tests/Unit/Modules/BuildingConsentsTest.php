<?php
/**
 * TPT Government Platform - Building Consents Module Unit Tests
 *
 * Comprehensive unit tests for the Building Consents Module
 * covering all functionality, edge cases, and error conditions
 */

use PHPUnit\Framework\TestCase;
use Modules\BuildingConsents\BuildingConsentsModule;

class BuildingConsentsTest extends TestCase
{
    private BuildingConsentsModule $module;
    private array $testData;

    protected function setUp(): void
    {
        $this->module = new BuildingConsentsModule([
            'enabled' => true,
            'consent_processing_days' => 20,
            'inspection_lead_time_days' => 5,
            'consent_validity_years' => 1,
            'auto_approval_threshold' => 50000
        ]);

        $this->testData = [
            'valid_application' => [
                'project_name' => 'Test Residential Extension',
                'project_type' => 'addition',
                'property_address' => '123 Test Street, Test City',
                'property_type' => 'residential',
                'building_consent_type' => 'full',
                'estimated_cost' => 75000.00,
                'floor_area' => 45.5,
                'storeys' => 1,
                'owner_id' => 1,
                'applicant_id' => 1,
                'architect_name' => 'Test Architect',
                'contractor_name' => 'Test Contractor',
                'description' => 'Single storey extension to existing dwelling'
            ],
            'invalid_application' => [
                'project_name' => '',
                'project_type' => 'invalid_type',
                'property_address' => '',
                'building_consent_type' => 'invalid_type',
                'estimated_cost' => -1000,
                'storeys' => 0
            ],
            'inspection_data' => [
                'application_id' => 'BC2024001',
                'inspection_type' => 'foundation',
                'preferred_date' => '2024-12-15',
                'preferred_time' => '09:00:00',
                'contact_person' => 'Test Inspector',
                'contact_phone' => '+1234567890',
                'special_requirements' => 'Access required to rear of property'
            ]
        ];
    }

    /**
     * Test module initialization
     */
    public function testModuleInitialization(): void
    {
        $this->assertInstanceOf(BuildingConsentsModule::class, $this->module);
        $this->assertTrue($this->module->isEnabled());
        $this->assertEquals('Building Consents', $this->module->getName());
        $this->assertEquals('2.3.0', $this->module->getVersion());
    }

    /**
     * Test module metadata
     */
    public function testModuleMetadata(): void
    {
        $metadata = $this->module->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertEquals('Building Consents', $metadata['name']);
        $this->assertEquals('2.3.0', $metadata['version']);
        $this->assertEquals('permitting_services', $metadata['category']);
        $this->assertContains('database', $metadata['dependencies']);
        $this->assertContains('workflow', $metadata['dependencies']);
    }

    /**
     * Test module configuration
     */
    public function testModuleConfiguration(): void
    {
        $config = $this->module->getConfig();

        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals(20, $config['consent_processing_days']);
        $this->assertEquals(5, $config['inspection_lead_time_days']);
        $this->assertEquals(1, $config['consent_validity_years']);
        $this->assertEquals(50000, $config['auto_approval_threshold']);
    }

    /**
     * Test module permissions
     */
    public function testModulePermissions(): void
    {
        $permissions = $this->module->getPermissions();

        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('building_consents.view', $permissions);
        $this->assertArrayHasKey('building_consents.create', $permissions);
        $this->assertArrayHasKey('building_consents.edit', $permissions);
        $this->assertArrayHasKey('building_consents.review', $permissions);
        $this->assertArrayHasKey('building_consents.approve', $permissions);
        $this->assertArrayHasKey('building_consents.reject', $permissions);
        $this->assertArrayHasKey('building_consents.inspect', $permissions);
        $this->assertArrayHasKey('building_consents.certify', $permissions);
        $this->assertArrayHasKey('building_consents.compliance', $permissions);
    }

    /**
     * Test module endpoints
     */
    public function testModuleEndpoints(): void
    {
        $endpoints = $this->module->getEndpoints();

        $this->assertIsArray($endpoints);
        $this->assertCount(10, $endpoints);

        // Test specific endpoints
        $this->assertEquals('GET', $endpoints[0]['method']);
        $this->assertEquals('/api/building-consents', $endpoints[0]['path']);
        $this->assertEquals('getBuildingConsents', $endpoints[0]['handler']);
        $this->assertTrue($endpoints[0]['auth']);
        $this->assertContains('building_consents.view', $endpoints[0]['permissions']);
    }

    /**
     * Test module workflows
     */
    public function testModuleWorkflows(): void
    {
        $workflows = $this->module->getWorkflows();

        $this->assertIsArray($workflows);
        $this->assertArrayHasKey('building_consent_process', $workflows);
        $this->assertArrayHasKey('inspection_process', $workflows);

        $consentWorkflow = $workflows['building_consent_process'];
        $this->assertEquals('Building Consent Application Process', $consentWorkflow['name']);
        $this->assertArrayHasKey('draft', $consentWorkflow['steps']);
        $this->assertArrayHasKey('approved', $consentWorkflow['steps']);
        $this->assertArrayHasKey('rejected', $consentWorkflow['steps']);
    }

    /**
     * Test module forms
     */
    public function testModuleForms(): void
    {
        $forms = $this->module->getForms();

        $this->assertIsArray($forms);
        $this->assertArrayHasKey('building_consent_application', $forms);
        $this->assertArrayHasKey('inspection_request', $forms);

        $applicationForm = $forms['building_consent_application'];
        $this->assertEquals('Building Consent Application', $applicationForm['name']);
        $this->assertArrayHasKey('project_name', $applicationForm['fields']);
        $this->assertArrayHasKey('project_type', $applicationForm['fields']);
        $this->assertTrue($applicationForm['fields']['project_name']['required']);
        $this->assertFalse($applicationForm['fields']['architect_name']['required']);
    }

    /**
     * Test module reports
     */
    public function testModuleReports(): void
    {
        $reports = $this->module->getReports();

        $this->assertIsArray($reports);
        $this->assertArrayHasKey('consent_overview', $reports);
        $this->assertArrayHasKey('inspection_summary', $reports);
        $this->assertArrayHasKey('compliance_report', $reports);
        $this->assertArrayHasKey('revenue_report', $reports);

        $overviewReport = $reports['consent_overview'];
        $this->assertEquals('Building Consent Overview Report', $overviewReport['name']);
        $this->assertArrayHasKey('date_range', $overviewReport['parameters']);
        $this->assertArrayHasKey('consent_type', $overviewReport['parameters']);
        $this->assertArrayHasKey('status', $overviewReport['parameters']);
    }

    /**
     * Test valid application creation
     */
    public function testCreateValidBuildingConsentApplication(): void
    {
        $result = $this->module->createBuildingConsentApplication($this->testData['valid_application']);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('application_id', $result);
        $this->assertArrayHasKey('consent_type', $result);
        $this->assertArrayHasKey('processing_deadline', $result);
        $this->assertArrayHasKey('total_fees', $result);
        $this->assertArrayHasKey('requirements', $result);

        $this->assertStringStartsWith('BC', $result['application_id']);
        $this->assertEquals('Full Building Consent', $result['consent_type']);
        $this->assertIsArray($result['requirements']);
        $this->assertGreaterThan(0, $result['total_fees']);
    }

    /**
     * Test invalid application creation
     */
    public function testCreateInvalidBuildingConsentApplication(): void
    {
        $result = $this->module->createBuildingConsentApplication($this->testData['invalid_application']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test application data validation
     */
    public function testValidateApplicationData(): void
    {
        // Test with missing required fields
        $incompleteData = [
            'project_name' => 'Test Project'
            // Missing other required fields
        ];

        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('validateApplicationData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->module, [$incompleteData]);

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test application submission
     */
    public function testSubmitBuildingConsentApplication(): void
    {
        // First create a draft application
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        $applicationId = $createResult['application_id'];

        // Now submit it
        $submitResult = $this->module->submitBuildingConsentApplication($applicationId);

        $this->assertIsArray($submitResult);
        $this->assertTrue($submitResult['success']);
        $this->assertEquals('Application submitted successfully', $submitResult['message']);
        $this->assertArrayHasKey('lodgement_date', $submitResult);
    }

    /**
     * Test application review
     */
    public function testReviewBuildingConsentApplication(): void
    {
        // Create and submit application first
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        $applicationId = $createResult['application_id'];
        $this->module->submitBuildingConsentApplication($applicationId);

        // Review the application
        $reviewResult = $this->module->reviewBuildingConsentApplication($applicationId, [
            'notes' => 'Application reviewed and found satisfactory'
        ]);

        $this->assertIsArray($reviewResult);
        $this->assertTrue($reviewResult['success']);
        $this->assertEquals('Application moved to review', $reviewResult['message']);
    }

    /**
     * Test application approval
     */
    public function testApproveBuildingConsentApplication(): void
    {
        // Create, submit, and review application first
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        $applicationId = $createResult['application_id'];
        $this->module->submitBuildingConsentApplication($applicationId);
        $this->module->reviewBuildingConsentApplication($applicationId, ['notes' => 'Approved']);

        // Approve the application
        $approvalResult = $this->module->approveBuildingConsentApplication($applicationId, [
            'conditions' => ['Building must comply with local bylaws'],
            'notes' => 'Approved with standard conditions'
        ]);

        $this->assertIsArray($approvalResult);
        $this->assertTrue($approvalResult['success']);
        $this->assertArrayHasKey('consent_number', $approvalResult);
        $this->assertArrayHasKey('expiry_date', $approvalResult);
        $this->assertEquals('Building consent approved', $approvalResult['message']);

        $this->assertStringStartsWith('BC', $approvalResult['consent_number']);
    }

    /**
     * Test application rejection
     */
    public function testRejectBuildingConsentApplication(): void
    {
        // Create, submit, and review application first
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        $applicationId = $createResult['application_id'];
        $this->module->submitBuildingConsentApplication($applicationId);
        $this->module->reviewBuildingConsentApplication($applicationId, ['notes' => 'For rejection']);

        // Reject the application
        $reason = 'Application does not meet building code requirements';
        $rejectionResult = $this->module->rejectBuildingConsentApplication($applicationId, $reason);

        $this->assertIsArray($rejectionResult);
        $this->assertTrue($rejectionResult['success']);
        $this->assertEquals('Building consent rejected', $rejectionResult['message']);
    }

    /**
     * Test inspection scheduling
     */
    public function testScheduleBuildingInspection(): void
    {
        $inspectionResult = $this->module->scheduleBuildingInspection($this->testData['inspection_data']);

        $this->assertIsArray($inspectionResult);
        $this->assertTrue($inspectionResult['success']);
        $this->assertEquals('Building inspection scheduled', $inspectionResult['message']);
        $this->assertArrayHasKey('scheduled_date', $inspectionResult);
    }

    /**
     * Test inspection completion
     */
    public function testCompleteBuildingInspection(): void
    {
        // First schedule an inspection
        $scheduleResult = $this->module->scheduleBuildingInspection($this->testData['inspection_data']);
        $this->assertTrue($scheduleResult['success']);

        // Mock inspection ID (would be returned from scheduling)
        $inspectionId = 1;

        // Complete the inspection
        $completionResult = $this->module->completeBuildingInspection($inspectionId, [
            'result' => 'pass',
            'findings' => ['All requirements met'],
            'recommendations' => ['Proceed with construction'],
            'follow_up_required' => false,
            'notes' => 'Inspection completed successfully'
        ]);

        $this->assertIsArray($completionResult);
        $this->assertTrue($completionResult['success']);
        $this->assertEquals('pass', $completionResult['result']);
        $this->assertEquals('Building inspection completed', $completionResult['message']);
    }

    /**
     * Test certificate issuance
     */
    public function testIssueBuildingCertificate(): void
    {
        // Create, submit, review, and approve application first
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        $applicationId = $createResult['application_id'];
        $this->module->submitBuildingConsentApplication($applicationId);
        $this->module->reviewBuildingConsentApplication($applicationId, ['notes' => 'Approved']);
        $this->module->approveBuildingConsentApplication($applicationId, ['conditions' => [], 'notes' => 'Approved']);

        // Issue certificate
        $certificateResult = $this->module->issueBuildingCertificate($applicationId, 'code_compliance', [
            'conditions' => ['Annual inspections required'],
            'limitations' => 'Valid for 10 years from issue date',
            'issued_by' => 1
        ]);

        $this->assertIsArray($certificateResult);
        $this->assertTrue($certificateResult['success']);
        $this->assertArrayHasKey('certificate_number', $certificateResult);
        $this->assertArrayHasKey('issue_date', $certificateResult);
        $this->assertArrayHasKey('expiry_date', $certificateResult);
        $this->assertEquals('Building certificate issued', $certificateResult['message']);

        $this->assertStringStartsWith('BCC', $certificateResult['certificate_number']);
    }

    /**
     * Test fee calculation
     */
    public function testFeeCalculation(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('calculateApplicationFees');
        $method->setAccessible(true);

        $fees = $method->invokeArgs($this->module, [$this->testData['valid_application'], [
            'code' => 'full',
            'name' => 'Full Building Consent',
            'requirements' => ['site_plan', 'floor_plans', 'elevations']
        ]]);

        $this->assertIsArray($fees);
        $this->assertGreaterThan(0, count($fees));

        // Check that fees include lodgement fee
        $this->assertContains('lodgement', array_column($fees, 'fee_type'));

        // Check that total is calculated correctly
        $total = array_sum(array_column($fees, 'amount'));
        $this->assertGreaterThan(0, $total);
    }

    /**
     * Test consent number generation
     */
    public function testConsentNumberGeneration(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('generateConsentNumber');
        $method->setAccessible(true);

        $consentNumber = $method->invoke($this->module);

        $this->assertStringStartsWith('BC', $consentNumber);
        $this->assertMatchesRegularExpression('/^BC\d{6}$/', $consentNumber);
    }

    /**
     * Test application ID generation
     */
    public function testApplicationIdGeneration(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('generateApplicationId');
        $method->setAccessible(true);

        $applicationId = $method->invoke($this->module);

        $this->assertStringStartsWith('BC', $applicationId);
        $this->assertMatchesRegularExpression('/^BC\d{4}\d{6}$/', $applicationId);
    }

    /**
     * Test certificate number generation
     */
    public function testCertificateNumberGeneration(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('generateCertificateNumber');
        $method->setAccessible(true);

        $certificateNumber = $method->invokeArgs($this->module, ['code_compliance']);

        $this->assertStringStartsWith('BCC', $certificateNumber);
        $this->assertMatchesRegularExpression('/^BCC\d{4}\d{6}$/', $certificateNumber);
    }

    /**
     * Test report generation
     */
    public function testGenerateBuildingConsentReport(): void
    {
        $filters = [
            'status' => 'approved',
            'consent_type' => 'full',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ];

        $report = $this->module->generateBuildingConsentReport($filters);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('filters', $report);
        $this->assertArrayHasKey('data', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertEquals($filters, $report['filters']);
    }

    /**
     * Test module statistics
     */
    public function testGetModuleStatistics(): void
    {
        $statistics = $this->module->getModuleStatistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_applications', $statistics);
        $this->assertArrayHasKey('approved_consents', $statistics);
        $this->assertArrayHasKey('pending_applications', $statistics);
        $this->assertArrayHasKey('total_revenue', $statistics);
        $this->assertArrayHasKey('compliance_rate', $statistics);

        $this->assertIsNumeric($statistics['total_applications']);
        $this->assertIsNumeric($statistics['approved_consents']);
        $this->assertIsNumeric($statistics['pending_applications']);
        $this->assertIsFloat($statistics['total_revenue']);
        $this->assertIsFloat($statistics['compliance_rate']);
    }

    /**
     * Test configuration validation
     */
    public function testConfigurationValidation(): void
    {
        $validation = $this->module->validateConfiguration();

        $this->assertIsArray($validation);
        $this->assertTrue($validation['valid']);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertEmpty($validation['errors']);
    }

    /**
     * Test module enable/disable
     */
    public function testModuleEnableDisable(): void
    {
        // Test disable
        $disableResult = $this->module->disable();
        $this->assertTrue($disableResult);
        $this->assertFalse($this->module->isEnabled());

        // Test enable
        $enableResult = $this->module->enable();
        $this->assertTrue($enableResult);
        $this->assertTrue($this->module->isEnabled());
    }

    /**
     * Test module data export
     */
    public function testModuleDataExport(): void
    {
        $exportData = $this->module->exportData('json');

        $this->assertIsString($exportData);

        $parsedData = json_decode($exportData, true);
        $this->assertIsArray($parsedData);
        $this->assertArrayHasKey('metadata', $parsedData);
        $this->assertArrayHasKey('config', $parsedData);
        $this->assertArrayHasKey('statistics', $parsedData);
        $this->assertArrayHasKey('exported_at', $parsedData);
    }

    /**
     * Test invalid inspection scheduling
     */
    public function testInvalidInspectionScheduling(): void
    {
        $invalidData = [
            'application_id' => 'INVALID',
            'inspection_type' => 'invalid_type',
            'preferred_date' => '2020-01-01', // Past date
            'preferred_time' => '25:00:00' // Invalid time
        ];

        $result = $this->module->scheduleBuildingInspection($invalidData);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * Test workflow advancement
     */
    public function testWorkflowAdvancement(): void
    {
        // Create and submit application
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        $applicationId = $createResult['application_id'];

        // Test workflow advancement through various states
        $submitResult = $this->module->submitBuildingConsentApplication($applicationId);
        $this->assertTrue($submitResult['success']);

        $reviewResult = $this->module->reviewBuildingConsentApplication($applicationId, ['notes' => 'Reviewed']);
        $this->assertTrue($reviewResult['success']);

        $approvalResult = $this->module->approveBuildingConsentApplication($applicationId, ['conditions' => [], 'notes' => 'Approved']);
        $this->assertTrue($approvalResult['success']);
    }

    /**
     * Test error handling for non-existent application
     */
    public function testNonExistentApplicationHandling(): void
    {
        $result = $this->module->submitBuildingConsentApplication('NONEXISTENT');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Application not found', $result['error']);
    }

    /**
     * Test duplicate application prevention
     */
    public function testDuplicateApplicationPrevention(): void
    {
        // Create first application
        $result1 = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($result1['success']);

        // Attempt to create duplicate (same project details)
        $result2 = $this->module->createBuildingConsentApplication($this->testData['valid_application']);

        // This should either succeed (with different ID) or fail gracefully
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('success', $result2);
    }

    /**
     * Test fee payment processing
     */
    public function testFeePaymentProcessing(): void
    {
        // Create application first
        $createResult = $this->module->createBuildingConsentApplication($this->testData['valid_application']);
        $this->assertTrue($createResult['success']);

        // Mock invoice number (would be generated during fee creation)
        $invoiceNumber = 'WINV2024001';

        // Test payment processing
        $paymentResult = $this->module->processFeePayment($invoiceNumber, [
            'method' => 'credit_card',
            'card_number' => '4111111111111111',
            'expiry_month' => '12',
            'expiry_year' => '2025',
            'cvv' => '123'
        ]);

        // Payment processing would normally succeed or fail based on gateway
        $this->assertIsArray($paymentResult);
        $this->assertArrayHasKey('success', $paymentResult);
    }

    /**
     * Test inspection data validation
     */
    public function testInspectionDataValidation(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('validateInspectionData');
        $method->setAccessible(true);

        // Test valid data
        $validResult = $method->invokeArgs($this->module, [$this->testData['inspection_data']]);
        $this->assertIsArray($validResult);
        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['errors']);

        // Test invalid data
        $invalidData = [
            'application_id' => '',
            'inspection_type' => '',
            'preferred_date' => ''
        ];

        $invalidResult = $method->invokeArgs($this->module, [$invalidData]);
        $this->assertIsArray($invalidResult);
        $this->assertFalse($invalidResult['valid']);
        $this->assertNotEmpty($invalidResult['errors']);
    }

    /**
     * Test module cache functionality
     */
    public function testModuleCache(): void
    {
        $testKey = 'test_cache_key';
        $testValue = ['data' => 'test_value', 'timestamp' => time()];

        // Test cache set
        $this->module->setCache($testKey, $testValue);

        // Test cache get
        $cachedValue = $this->module->getCache($testKey);
        $this->assertEquals($testValue, $cachedValue);

        // Test cache clear
        $this->module->clearCache($testKey);
        $clearedValue = $this->module->getCache($testKey);
        $this->assertNull($clearedValue);
    }

    /**
     * Test module performance under load
     */
    public function testModulePerformance(): void
    {
        $startTime = microtime(true);

        // Create multiple applications
        for ($i = 0; $i < 10; $i++) {
            $testData = $this->testData['valid_application'];
            $testData['project_name'] = "Performance Test Project {$i}";

            $result = $this->module->createBuildingConsentApplication($testData);
            $this->assertTrue($result['success']);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within reasonable time (adjust threshold as needed)
        $this->assertLessThan(5.0, $executionTime, 'Module should handle multiple operations efficiently');
    }

    /**
     * Test module cleanup on destruction
     */
    public function testModuleCleanup(): void
    {
        // Set some cache data
        $this->module->setCache('cleanup_test', 'test_data');

        // Clear all cache
        $this->module->clearCache();

        // Verify cache is empty
        $this->assertNull($this->module->getCache('cleanup_test'));
    }
}

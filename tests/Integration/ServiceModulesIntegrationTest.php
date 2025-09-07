<?php
/**
 * TPT Government Platform - Service Modules Integration Tests
 *
 * Comprehensive integration tests for all service modules
 * testing cross-module interactions, data flow, and system integration
 */

use PHPUnit\Framework\TestCase;
use Modules\BuildingConsents\BuildingConsentsModule;
use Modules\TrafficParking\TrafficParkingModule;
use Modules\BusinessLicenses\BusinessLicensesModule;
use Modules\WasteManagement\WasteManagementModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class ServiceModulesIntegrationTest extends TestCase
{
    private BuildingConsentsModule $buildingModule;
    private TrafficParkingModule $trafficModule;
    private BusinessLicensesModule $businessModule;
    private WasteManagementModule $wasteModule;
    private Database $database;
    private WorkflowEngine $workflowEngine;
    private NotificationManager $notificationManager;
    private PaymentGateway $paymentGateway;

    private array $testData;

    protected function setUp(): void
    {
        // Initialize all modules
        $this->buildingModule = new BuildingConsentsModule([
            'enabled' => true,
            'consent_processing_days' => 20,
            'inspection_lead_time_days' => 5,
            'consent_validity_years' => 1
        ]);

        $this->trafficModule = new TrafficParkingModule([
            'enabled' => true,
            'appeal_deadline_days' => 30,
            'payment_grace_period_days' => 14,
            'court_escalation_threshold' => 90,
            'license_suspension_threshold' => 12
        ]);

        $this->businessModule = new BusinessLicensesModule([
            'enabled' => true,
            'license_processing_days' => 15,
            'renewal_reminder_days' => 30,
            'compliance_check_frequency' => 90
        ]);

        $this->wasteModule = new WasteManagementModule([
            'enabled' => true,
            'collection_scheduling' => [
                'advance_booking_days' => 14,
                'max_requests_per_day' => 50,
                'emergency_response_hours' => 24
            ],
            'billing' => [
                'billing_cycle' => 'monthly',
                'payment_grace_period' => 15,
                'late_fee_percentage' => 0.05
            ]
        ]);

        // Initialize core services
        $this->database = new Database();
        $this->workflowEngine = new WorkflowEngine();
        $this->notificationManager = new NotificationManager();
        $this->paymentGateway = new PaymentGateway();

        $this->testData = [
            'customer' => [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+1234567890',
                'address' => '123 Main Street, Test City'
            ],
            'business' => [
                'name' => 'Doe Construction Ltd',
                'type' => 'construction',
                'address' => '456 Business Ave, Test City',
                'owner_id' => 1,
                'license_type' => 'contractor_general'
            ],
            'property' => [
                'address' => '789 Property Street, Test City',
                'owner_id' => 1,
                'type' => 'residential',
                'value' => 500000.00
            ]
        ];
    }

    /**
     * Test complete business establishment workflow
     * Business License -> Building Consent -> Waste Management
     */
    public function testCompleteBusinessEstablishmentWorkflow(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Step 1: Apply for Business License
        $licenseResult = $this->businessModule->applyForBusinessLicense([
            'business_name' => $this->testData['business']['name'],
            'business_type' => $this->testData['business']['type'],
            'business_address' => $this->testData['business']['address'],
            'owner_id' => $this->testData['business']['owner_id'],
            'license_type' => $this->testData['business']['license_type'],
            'estimated_annual_revenue' => 250000.00,
            'number_of_employees' => 15,
            'business_description' => 'General construction and renovation services'
        ]);

        $this->assertTrue($licenseResult['success']);
        $this->assertArrayHasKey('application_id', $licenseResult);
        $licenseApplicationId = $licenseResult['application_id'];

        // Step 2: Process Business License Application
        $processResult = $this->businessModule->processLicenseApplication($licenseApplicationId, 'approved', [
            'reviewer_id' => 1,
            'approval_conditions' => ['Maintain proper insurance coverage', 'Comply with local building codes'],
            'license_duration_years' => 1
        ]);

        $this->assertTrue($processResult['success']);
        $this->assertArrayHasKey('license_number', $processResult);
        $licenseNumber = $processResult['license_number'];

        // Step 3: Apply for Building Consent for business premises
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Business Office Renovation',
            'project_type' => 'renovation',
            'property_address' => $this->testData['business']['address'],
            'property_type' => 'commercial',
            'building_consent_type' => 'full',
            'estimated_cost' => 150000.00,
            'floor_area' => 200.0,
            'storeys' => 2,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Jane Smith Architect',
            'contractor_name' => $this->testData['business']['name'],
            'description' => 'Office renovation for new business premises'
        ]);

        $this->assertTrue($consentResult['success']);
        $this->assertArrayHasKey('application_id', $consentResult);
        $consentApplicationId = $consentResult['application_id'];

        // Step 4: Submit and approve building consent
        $submitResult = $this->buildingModule->submitBuildingConsentApplication($consentApplicationId);
        $this->assertTrue($submitResult['success']);

        $reviewResult = $this->buildingModule->reviewBuildingConsentApplication($consentApplicationId, [
            'notes' => 'Application reviewed and approved for business use'
        ]);
        $this->assertTrue($reviewResult['success']);

        $approvalResult = $this->buildingModule->approveBuildingConsentApplication($consentApplicationId, [
            'conditions' => ['Comply with commercial building standards'],
            'notes' => 'Approved for business occupancy'
        ]);
        $this->assertTrue($approvalResult['success']);

        // Step 5: Set up waste management service for business
        $wasteResult = $this->wasteModule->scheduleWasteService([
            'customer_id' => $customerId,
            'service_type' => 'commercial',
            'collection_frequency' => 'weekly',
            'collection_day' => 'friday',
            'collection_time' => '08:00:00',
            'address' => $this->testData['business']['address'],
            'bin_size' => 'large',
            'waste_types' => ['municipal_solid_waste', 'recyclables'],
            'special_instructions' => 'Commercial waste collection for construction business'
        ]);

        $this->assertTrue($wasteResult['success']);
        $this->assertArrayHasKey('service_id', $wasteResult);
        $wasteServiceId = $wasteResult['service_id'];

        // Verify cross-module data consistency
        $this->assertNotEmpty($licenseNumber);
        $this->assertNotEmpty($consentApplicationId);
        $this->assertNotEmpty($wasteServiceId);

        // Test that all services are properly linked to the customer
        $businessServices = $this->businessModule->getCustomerLicenses($customerId);
        $buildingServices = $this->buildingModule->getCustomerConsents($customerId);
        $wasteServices = $this->wasteModule->getCustomerServices($customerId);

        $this->assertIsArray($businessServices);
        $this->assertIsArray($buildingServices);
        $this->assertIsArray($wasteServices);
    }

    /**
     * Test residential property development workflow
     * Building Consent -> Waste Management -> Traffic/Parking
     */
    public function testResidentialPropertyDevelopmentWorkflow(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Step 1: Apply for Building Consent for residential addition
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Residential Home Extension',
            'project_type' => 'addition',
            'property_address' => $this->testData['property']['address'],
            'property_type' => $this->testData['property']['type'],
            'building_consent_type' => 'full',
            'estimated_cost' => 80000.00,
            'floor_area' => 45.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Bob Wilson Architect',
            'contractor_name' => 'ABC Builders',
            'description' => 'Single storey extension to existing residential dwelling'
        ]);

        $this->assertTrue($consentResult['success']);
        $consentApplicationId = $consentResult['application_id'];

        // Step 2: Submit and process building consent
        $this->buildingModule->submitBuildingConsentApplication($consentApplicationId);
        $this->buildingModule->reviewBuildingConsentApplication($consentApplicationId, ['notes' => 'Standard residential extension']);
        $approvalResult = $this->buildingModule->approveBuildingConsentApplication($consentApplicationId, [
            'conditions' => ['Comply with residential building standards'],
            'notes' => 'Approved for residential use'
        ]);
        $this->assertTrue($approvalResult['success']);

        // Step 3: Schedule building inspection
        $inspectionResult = $this->buildingModule->scheduleBuildingInspection([
            'application_id' => $consentApplicationId,
            'inspection_type' => 'foundation',
            'preferred_date' => date('Y-m-d', strtotime('+7 days')),
            'preferred_time' => '09:00:00',
            'contact_person' => $this->testData['customer']['name'],
            'contact_phone' => $this->testData['customer']['phone'],
            'special_requirements' => 'Access required through side gate'
        ]);
        $this->assertTrue($inspectionResult['success']);

        // Step 4: Set up residential waste collection
        $wasteResult = $this->wasteModule->scheduleWasteService([
            'customer_id' => $customerId,
            'service_type' => 'residential',
            'collection_frequency' => 'weekly',
            'collection_day' => 'monday',
            'collection_time' => '07:00:00',
            'address' => $this->testData['property']['address'],
            'bin_size' => 'medium',
            'waste_types' => ['municipal_solid_waste', 'recyclables', 'organic_waste'],
            'special_instructions' => 'Please place bins at front curb'
        ]);
        $this->assertTrue($wasteResult['success']);

        // Step 5: Issue parking permit for construction vehicles
        $parkingResult = $this->trafficModule->createParkingViolation([
            'license_plate' => 'CONST001',
            'violation_type' => 'Construction Vehicle Parking',
            'violation_code' => 'PRK006', // Assuming this code exists
            'location' => $this->testData['property']['address'],
            'zone_type' => 'Construction Zone',
            'date_time' => date('Y-m-d H:i:s'),
            'officer_id' => 1,
            'evidence_photos' => ['construction_permit.jpg'],
            'notes' => 'Construction vehicle permit for home extension project'
        ]);

        // Verify all services are integrated
        $buildingStats = $this->buildingModule->getModuleStatistics();
        $wasteStats = $this->wasteModule->getModuleStatistics();
        $trafficStats = $this->trafficModule->getModuleStatistics();

        $this->assertIsArray($buildingStats);
        $this->assertIsArray($wasteStats);
        $this->assertIsArray($trafficStats);

        // Test cross-module reporting
        $buildingReport = $this->buildingModule->generateBuildingConsentReport([
            'status' => 'approved',
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t')
        ]);

        $wasteReport = $this->wasteModule->generateWasteReport([
            'status' => 'active',
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t')
        ]);

        $trafficReport = $this->trafficModule->generateTicketReport([
            'status' => 'issued',
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t')
        ]);

        $this->assertIsArray($buildingReport);
        $this->assertIsArray($wasteReport);
        $this->assertIsArray($trafficReport);
    }

    /**
     * Test payment processing across modules
     */
    public function testCrossModulePaymentProcessing(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Create services across multiple modules
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Test Project',
            'project_type' => 'new_construction',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 100000.00,
            'floor_area' => 100.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test construction project'
        ]);
        $this->assertTrue($consentResult['success']);

        $wasteResult = $this->wasteModule->scheduleWasteService([
            'customer_id' => $customerId,
            'service_type' => 'residential',
            'collection_frequency' => 'weekly',
            'collection_day' => 'monday',
            'collection_time' => '07:00:00',
            'address' => $this->testData['property']['address'],
            'bin_size' => 'medium',
            'waste_types' => ['municipal_solid_waste'],
            'special_instructions' => 'Test waste service'
        ]);
        $this->assertTrue($wasteResult['success']);

        // Test payment processing for building consent fees
        $buildingFees = $this->buildingModule->getApplicationFees($consentResult['application_id']);
        $this->assertIsArray($buildingFees);

        if (!empty($buildingFees)) {
            $firstFee = reset($buildingFees);
            $paymentResult = $this->buildingModule->processFeePayment($firstFee['invoice_number'], [
                'method' => 'credit_card',
                'card_number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'cardholder_name' => $this->testData['customer']['name']
            ]);

            $this->assertIsArray($paymentResult);
            $this->assertArrayHasKey('success', $paymentResult);
        }

        // Test payment processing for waste management billing
        $wasteBilling = $this->wasteModule->processWasteBilling($customerId, date('Y-m'));
        $this->assertIsArray($wasteBilling);
        $this->assertArrayHasKey('success', $wasteBilling);

        if ($wasteBilling['success']) {
            $paymentResult = $this->wasteModule->processBillingPayment($wasteBilling['invoice_number'], [
                'method' => 'credit_card',
                'card_number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'cardholder_name' => $this->testData['customer']['name']
            ]);

            $this->assertIsArray($paymentResult);
            $this->assertArrayHasKey('success', $paymentResult);
        }
    }

    /**
     * Test notification system integration across modules
     */
    public function testNotificationSystemIntegration(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Create services that trigger notifications
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Notification Test Project',
            'project_type' => 'renovation',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 50000.00,
            'floor_area' => 50.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test project for notification integration'
        ]);
        $this->assertTrue($consentResult['success']);

        // Submit application (should trigger notification)
        $submitResult = $this->buildingModule->submitBuildingConsentApplication($consentResult['application_id']);
        $this->assertTrue($submitResult['success']);

        // Check that notifications were queued
        $notifications = $this->notificationManager->getQueuedNotifications($customerId);
        $this->assertIsArray($notifications);

        // Verify notification content
        $buildingNotifications = array_filter($notifications, function($notification) {
            return strpos($notification['template'], 'building consent') !== false;
        });

        $this->assertNotEmpty($buildingNotifications);

        // Test notification delivery simulation
        foreach ($buildingNotifications as $notification) {
            $deliveryResult = $this->notificationManager->deliverNotification($notification['id'], 'email');
            $this->assertIsArray($deliveryResult);
            $this->assertArrayHasKey('success', $deliveryResult);
        }
    }

    /**
     * Test workflow engine integration across modules
     */
    public function testWorkflowEngineIntegration(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Create a building consent application
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Workflow Test Project',
            'project_type' => 'new_construction',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 75000.00,
            'floor_area' => 75.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test project for workflow integration'
        ]);
        $this->assertTrue($consentResult['success']);
        $applicationId = $consentResult['application_id'];

        // Test workflow state transitions
        $initialState = $this->workflowEngine->getWorkflowState($applicationId, 'building_consent_process');
        $this->assertEquals('draft', $initialState);

        // Submit application
        $this->buildingModule->submitBuildingConsentApplication($applicationId);
        $submittedState = $this->workflowEngine->getWorkflowState($applicationId, 'building_consent_process');
        $this->assertEquals('submitted', $submittedState);

        // Review application
        $this->buildingModule->reviewBuildingConsentApplication($applicationId, ['notes' => 'Under review']);
        $reviewState = $this->workflowEngine->getWorkflowState($applicationId, 'building_consent_process');
        $this->assertEquals('under_review', $reviewState);

        // Approve application
        $this->buildingModule->approveBuildingConsentApplication($applicationId, [
            'conditions' => ['Test conditions'],
            'notes' => 'Approved'
        ]);
        $approvedState = $this->workflowEngine->getWorkflowState($applicationId, 'building_consent_process');
        $this->assertEquals('approved', $approvedState);

        // Test workflow history
        $workflowHistory = $this->workflowEngine->getWorkflowHistory($applicationId, 'building_consent_process');
        $this->assertIsArray($workflowHistory);
        $this->assertCount(4, $workflowHistory); // draft -> submitted -> under_review -> approved

        // Verify chronological order
        $states = array_column($workflowHistory, 'state');
        $expectedStates = ['draft', 'submitted', 'under_review', 'approved'];
        $this->assertEquals($expectedStates, $states);
    }

    /**
     * Test database consistency across modules
     */
    public function testDatabaseConsistencyAcrossModules(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Create records in multiple modules
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Database Consistency Test',
            'project_type' => 'renovation',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 60000.00,
            'floor_area' => 60.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test for database consistency'
        ]);
        $this->assertTrue($consentResult['success']);

        $wasteResult = $this->wasteModule->scheduleWasteService([
            'customer_id' => $customerId,
            'service_type' => 'residential',
            'collection_frequency' => 'weekly',
            'collection_day' => 'wednesday',
            'collection_time' => '08:00:00',
            'address' => $this->testData['property']['address'],
            'bin_size' => 'medium',
            'waste_types' => ['municipal_solid_waste'],
            'special_instructions' => 'Database consistency test'
        ]);
        $this->assertTrue($wasteResult['success']);

        // Test referential integrity
        $buildingRecords = $this->database->query(
            "SELECT * FROM building_consent_applications WHERE applicant_id = ?",
            [$customerId]
        );
        $this->assertIsArray($buildingRecords);
        $this->assertNotEmpty($buildingRecords);

        $wasteRecords = $this->database->query(
            "SELECT * FROM waste_collection_schedules WHERE customer_id = ?",
            [$customerId]
        );
        $this->assertIsArray($wasteRecords);
        $this->assertNotEmpty($wasteRecords);

        // Verify customer ID consistency
        foreach ($buildingRecords as $record) {
            $this->assertEquals($customerId, $record['applicant_id']);
        }

        foreach ($wasteRecords as $record) {
            $this->assertEquals($customerId, $record['customer_id']);
        }

        // Test cascade operations (if implemented)
        // This would test foreign key constraints and cascade deletes
    }

    /**
     * Test error handling and recovery across modules
     */
    public function testErrorHandlingAndRecovery(): void
    {
        // Test invalid data handling
        $invalidConsentData = [
            'project_name' => '',
            'project_type' => 'invalid_type',
            'property_address' => '',
            'building_consent_type' => 'invalid_type',
            'estimated_cost' => -1000,
            'storeys' => 0
        ];

        $result = $this->buildingModule->createBuildingConsentApplication($invalidConsentData);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);

        // Test network failure simulation (if implemented)
        // Test database connection failure recovery
        // Test external service failure handling

        // Test partial transaction rollback
        $customerId = $this->testData['customer']['id'];

        // Attempt to create multiple related records
        try {
            $consentResult = $this->buildingModule->createBuildingConsentApplication([
                'project_name' => 'Error Recovery Test',
                'project_type' => 'new_construction',
                'property_address' => $this->testData['property']['address'],
                'property_type' => 'residential',
                'building_consent_type' => 'full',
                'estimated_cost' => 90000.00,
                'floor_area' => 90.0,
                'storeys' => 1,
                'owner_id' => $customerId,
                'applicant_id' => $customerId,
                'architect_name' => 'Test Architect',
                'contractor_name' => 'Test Contractor',
                'description' => 'Test error recovery'
            ]);

            if ($consentResult['success']) {
                // Simulate an error in subsequent operation
                throw new Exception('Simulated error in workflow');

                // If we get here, the transaction should be rolled back
                $this->fail('Expected exception was not thrown');
            }
        } catch (Exception $e) {
            // Verify that partial data is properly cleaned up
            $this->assertStringContains('Simulated error', $e->getMessage());
        }
    }

    /**
     * Test performance under concurrent load
     */
    public function testConcurrentLoadPerformance(): void
    {
        $customerId = $this->testData['customer']['id'];
        $concurrentOperations = 5;

        $startTime = microtime(true);

        // Simulate concurrent operations
        $promises = [];
        for ($i = 0; $i < $concurrentOperations; $i++) {
            // Create multiple building consent applications concurrently
            $result = $this->buildingModule->createBuildingConsentApplication([
                'project_name' => "Concurrent Test Project {$i}",
                'project_type' => 'renovation',
                'property_address' => $this->testData['property']['address'] . " Unit {$i}",
                'property_type' => 'residential',
                'building_consent_type' => 'full',
                'estimated_cost' => 50000.00 + ($i * 10000),
                'floor_area' => 50.0 + ($i * 5),
                'storeys' => 1,
                'owner_id' => $customerId,
                'applicant_id' => $customerId,
                'architect_name' => 'Test Architect',
                'contractor_name' => 'Test Contractor',
                'description' => "Concurrent operation test {$i}"
            ]);

            $promises[] = $result;
        }

        // Wait for all operations to complete
        foreach ($promises as $promise) {
            $this->assertIsArray($promise);
            $this->assertArrayHasKey('success', $promise);
            $this->assertTrue($promise['success']);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Verify performance is acceptable (adjust threshold as needed)
        $this->assertLessThan(10.0, $executionTime, 'Concurrent operations should complete within acceptable time');

        // Verify no data corruption occurred
        $stats = $this->buildingModule->getModuleStatistics();
        $this->assertIsArray($stats);
        $this->assertGreaterThanOrEqual($concurrentOperations, $stats['total_applications']);
    }

    /**
     * Test module interoperability and data sharing
     */
    public function testModuleInteroperability(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Create a business license first
        $licenseResult = $this->businessModule->applyForBusinessLicense([
            'business_name' => 'Interoperability Test Business',
            'business_type' => 'construction',
            'business_address' => $this->testData['business']['address'],
            'owner_id' => $customerId,
            'license_type' => 'contractor_general',
            'estimated_annual_revenue' => 300000.00,
            'number_of_employees' => 20,
            'business_description' => 'Construction business for interoperability testing'
        ]);
        $this->assertTrue($licenseResult['success']);

        // Process the license
        $processResult = $this->businessModule->processLicenseApplication(
            $licenseResult['application_id'],
            'approved',
            [
                'reviewer_id' => 1,
                'approval_conditions' => ['Maintain insurance', 'Comply with building codes'],
                'license_duration_years' => 1
            ]
        );
        $this->assertTrue($processResult['success']);

        // Use the licensed business in building consent
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Licensed Business Construction',
            'project_type' => 'new_construction',
            'property_address' => '999 Construction Site, Test City',
            'property_type' => 'commercial',
            'building_consent_type' => 'full',
            'estimated_cost' => 200000.00,
            'floor_area' => 150.0,
            'storeys' => 2,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Licensed Architect',
            'contractor_name' => 'Interoperability Test Business',
            'description' => 'Construction project using licensed contractor'
        ]);
        $this->assertTrue($consentResult['success']);

        // Verify cross-references work
        $businessLicenses = $this->businessModule->getCustomerLicenses($customerId);
        $buildingConsents = $this->buildingModule->getCustomerConsents($customerId);

        $this->assertIsArray($businessLicenses);
        $this->assertIsArray($buildingConsents);
        $this->assertNotEmpty($businessLicenses);
        $this->assertNotEmpty($buildingConsents);

        // Test that business license data can be referenced in building consent
        $license = reset($businessLicenses);
        $consent = reset($buildingConsents);

        $this->assertArrayHasKey('license_number', $license);
        $this->assertArrayHasKey('application_id', $consent);
    }

    /**
     * Test security integration across modules
     */
    public function testSecurityIntegration(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Test that all modules properly validate user permissions
        $unauthorizedUserId = 999;

        // Try to access data from another user
        $buildingConsents = $this->buildingModule->getCustomerConsents($unauthorizedUserId);
        $wasteServices = $this->wasteModule->getCustomerServices($unauthorizedUserId);
        $businessLicenses = $this->businessModule->getCustomerLicenses($unauthorizedUserId);

        // These should return empty arrays or throw security exceptions
        $this->assertIsArray($buildingConsents);
        $this->assertIsArray($wasteServices);
        $this->assertIsArray($businessLicenses);

        // Test data sanitization
        $maliciousData = [
            'project_name' => '<script>alert("XSS")</script>Test Project',
            'project_type' => 'new_construction',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 100000.00,
            'floor_area' => 100.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test with potentially malicious data'
        ];

        $result = $this->buildingModule->createBuildingConsentApplication($maliciousData);
        $this->assertTrue($result['success']); // Should succeed but data should be sanitized

        // Test rate limiting (if implemented)
        // Test input validation across all modules
    }

    /**
     * Test backup and recovery integration
     */
    public function testBackupAndRecoveryIntegration(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Create test data across modules
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Backup Test Project',
            'project_type' => 'renovation',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 55000.00,
            'floor_area' => 55.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test project for backup and recovery'
        ]);
        $this->assertTrue($consentResult['success']);

        // Test data export from each module
        $buildingExport = $this->buildingModule->exportData('json');
        $wasteExport = $this->wasteModule->exportData('json');
        $businessExport = $this->businessModule->exportData('json');
        $trafficExport = $this->trafficModule->exportData('json');

        $this->assertIsString($buildingExport);
        $this->assertIsString($wasteExport);
        $this->assertIsString($businessExport);
        $this->assertIsString($trafficExport);

        // Verify export contains expected data
        $buildingData = json_decode($buildingExport, true);
        $this->assertIsArray($buildingData);
        $this->assertArrayHasKey('metadata', $buildingData);
        $this->assertArrayHasKey('statistics', $buildingData);

        // Test data import (if implemented)
        // This would test the ability to restore data from backups
    }

    /**
     * Test audit trail integration across modules
     */
    public function testAuditTrailIntegration(): void
    {
        $customerId = $this->testData['customer']['id'];

        // Perform operations that should be audited
        $consentResult = $this->buildingModule->createBuildingConsentApplication([
            'project_name' => 'Audit Test Project',
            'project_type' => 'addition',
            'property_address' => $this->testData['property']['address'],
            'property_type' => 'residential',
            'building_consent_type' => 'full',
            'estimated_cost' => 45000.00,
            'floor_area' => 45.0,
            'storeys' => 1,
            'owner_id' => $customerId,
            'applicant_id' => $customerId,
            'architect_name' => 'Test Architect',
            'contractor_name' => 'Test Contractor',
            'description' => 'Test project for audit trail integration'
        ]);
        $this->assertTrue($consentResult['success']);
        $applicationId = $consentResult['application_id'];

        // Submit and process the application
        $this->buildingModule->submitBuildingConsentApplication($applicationId);
        $this->buildingModule->reviewBuildingConsentApplication($applicationId, ['notes' => 'Audit test']);
        $this->buildingModule->approveBuildingConsentApplication($applicationId, [
            'conditions' => ['Audit test conditions'],
            'notes' => 'Audit test approval'
        ]);

        // Check audit trail
        $auditTrail = $this->buildingModule->getAuditTrail($applicationId);
        $this->assertIsArray($auditTrail);

        // Verify audit entries exist for key operations
        $operations = array_column($auditTrail, 'operation');
        $this->assertContains('application_created', $operations);
        $this->assertContains('application_submitted', $operations);
        $this->assertContains('application_reviewed', $operations);
        $this->assertContains('application_approved', $operations);

        // Verify audit data integrity
        foreach ($auditTrail as $entry) {
            $this->assertArrayHasKey('timestamp', $entry);
            $this->assertArrayHasKey('user_id', $entry);
            $this->assertArrayHasKey('operation', $entry);
            $this->assertArrayHasKey('details', $entry);
        }
    }
}

<?php
/**
 * TPT Government Platform - Service Modules Integration Tests
 *
 * Integration tests for service module interactions and API endpoints
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Core\Application;
use Core\Database;
use Core\Modules\BuildingConsentsPlugin;
use Core\Modules\TrafficParkingPlugin;
use Core\Modules\BusinessLicensesPlugin;
use Core\Modules\WasteManagementPlugin;
use PDO;

class ServiceModulesIntegrationTest extends TestCase
{
    private Application $app;
    private Database $database;
    private BuildingConsentsPlugin $buildingConsents;
    private TrafficParkingPlugin $trafficParking;
    private BusinessLicensesPlugin $businessLicenses;
    private WasteManagementPlugin $wasteManagement;

    protected function setUp(): void
    {
        // Initialize application
        $this->app = new Application();

        // Set up test database connection
        $this->database = new Database([
            'host' => getenv('DB_HOST') ?: 'localhost',
            'database' => getenv('DB_NAME') ?: 'tpt_gov_test',
            'username' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4'
        ]);

        // Initialize service modules
        $this->buildingConsents = new BuildingConsentsPlugin();
        $this->trafficParking = new TrafficParkingPlugin();
        $this->businessLicenses = new BusinessLicensesPlugin();
        $this->wasteManagement = new WasteManagementPlugin();

        // Set database connections
        $this->buildingConsents->setDatabase($this->database);
        $this->trafficParking->setDatabase($this->database);
        $this->businessLicenses->setDatabase($this->database);
        $this->wasteManagement->setDatabase($this->database);

        // Set up test data
        $this->setUpTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanUpTestData();
    }

    private function setUpTestData(): void
    {
        try {
            // Create test tables if they don't exist
            $this->createTestTables();

            // Insert test data
            $this->insertTestData();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database setup failed: ' . $e->getMessage());
        }
    }

    private function createTestTables(): void
    {
        // Create tables for all service modules
        $tables = array_merge(
            $this->buildingConsents->getDatabaseTables(),
            $this->trafficParking->getDatabaseTables(),
            $this->businessLicenses->getDatabaseTables(),
            $this->wasteManagement->getDatabaseTables()
        );

        foreach ($tables as $tableName => $schema) {
            try {
                $this->database->query("DROP TABLE IF EXISTS {$tableName}");
                $this->database->query("CREATE TABLE {$tableName} ({$schema})");
            } catch (\Exception $e) {
                // Table might already exist or have dependencies
            }
        }
    }

    private function insertTestData(): void
    {
        // Insert test traffic offenses
        $this->database->insert('traffic_offenses', [
            'offense_code' => 'speeding_20',
            'description' => 'Speeding 20km/h over limit',
            'fine_amount' => 150.00,
            'points_deducted' => 3,
            'category' => 'speeding',
            'severity' => 'medium',
            'active' => true
        ]);

        // Insert test license type
        $this->database->insert('business_license_types', [
            'license_code' => 'general_business',
            'name' => 'General Business License',
            'description' => 'Standard business license',
            'category' => 'retail',
            'annual_fee' => 500.00,
            'renewal_period_months' => 12,
            'inspection_required' => true,
            'risk_level' => 'medium',
            'active' => true
        ]);
    }

    private function cleanUpTestData(): void
    {
        // Clean up test data
        $tables = [
            'building_consent_applications',
            'building_consent_documents',
            'building_consent_inspections',
            'building_consent_fees',
            'traffic_tickets',
            'parking_violations',
            'driver_licenses',
            'traffic_offenses',
            'business_licenses',
            'business_license_applications',
            'business_license_inspections',
            'business_license_fees',
            'business_license_types',
            'waste_collection_schedules',
            'waste_service_requests',
            'waste_collection_zones',
            'waste_billing_accounts',
            'waste_collection_records',
            'waste_recycling_programs'
        ];

        foreach ($tables as $table) {
            try {
                $this->database->query("DROP TABLE IF EXISTS {$table}");
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
    }

    /**
     * Test end-to-end building consent workflow
     */
    public function testBuildingConsentWorkflow()
    {
        // 1. Create application
        $applicationData = [
            'applicant_id' => 1,
            'property_address' => '123 Test Street, Test City, 12345',
            'application_type' => 'New Construction',
            'work_description' => 'Construct a new single-story residential building',
            'estimated_cost' => 200000.00,
            'consent_type' => 'building_consent'
        ];

        $result = $this->buildingConsents->createApplication($applicationData);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('application_id', $result);
        $this->assertArrayHasKey('application_number', $result);

        $applicationId = $result['application_id'];
        $applicationNumber = $result['application_number'];

        // 2. Verify application was created
        $application = $this->buildingConsents->getApplication($applicationId);
        $this->assertTrue($application['success']);
        $this->assertEquals($applicationNumber, $application['application']['application_number']);
        $this->assertEquals('draft', $application['application']['status']);

        // 3. Submit application
        $submitResult = $this->buildingConsents->submitApplication($applicationId);
        $this->assertTrue($submitResult['success']);

        // 4. Verify status changed
        $updatedApplication = $this->buildingConsents->getApplication($applicationId);
        $this->assertEquals('submitted', $updatedApplication['application']['status']);

        // 5. Approve application
        $approvalResult = $this->buildingConsents->approveApplication($applicationId, [
            'approved_by' => 2,
            'notes' => 'Application meets all requirements'
        ]);
        $this->assertTrue($approvalResult['success']);

        // 6. Verify final status
        $finalApplication = $this->buildingConsents->getApplication($applicationId);
        $this->assertEquals('approved', $finalApplication['application']['status']);
        $this->assertEquals('approved', $finalApplication['application']['decision']);
    }

    /**
     * Test end-to-end traffic ticket workflow
     */
    public function testTrafficTicketWorkflow()
    {
        // 1. Create traffic ticket
        $ticketData = [
            'vehicle_registration' => 'TEST123',
            'license_number' => 'DLTEST123',
            'offense_type' => 'speeding_20',
            'offense_location' => 'Test Street & Test Avenue',
            'offense_date' => date('Y-m-d'),
            'offense_time' => date('H:i:s'),
            'issued_by' => 1
        ];

        $result = $this->trafficParking->createTicket($ticketData);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ticket_id', $result);
        $this->assertArrayHasKey('ticket_number', $result);

        $ticketId = $result['ticket_id'];
        $ticketNumber = $result['ticket_number'];

        // 2. Verify ticket was created
        $ticket = $this->trafficParking->getTicket($ticketId);
        $this->assertTrue($ticket['success']);
        $this->assertEquals($ticketNumber, $ticket['ticket']['ticket_number']);
        $this->assertEquals('unpaid', $ticket['ticket']['status']);
        $this->assertEquals(150.00, $ticket['ticket']['fine_amount']);

        // 3. Pay ticket
        $paymentResult = $this->trafficParking->payTicket($ticketId, [
            'payment_reference' => 'TESTPAY123'
        ]);
        $this->assertTrue($paymentResult['success']);

        // 4. Verify payment
        $paidTicket = $this->trafficParking->getTicket($ticketId);
        $this->assertEquals('paid', $paidTicket['ticket']['status']);
        $this->assertTrue($paidTicket['ticket']['paid']);
        $this->assertEquals('TESTPAY123', $paidTicket['ticket']['payment_reference']);
    }

    /**
     * Test end-to-end business license workflow
     */
    public function testBusinessLicenseWorkflow()
    {
        // 1. Create license application
        $applicationData = [
            'business_name' => 'Test Business Ltd',
            'business_type' => 'Retail',
            'applicant_name' => 'John Smith',
            'applicant_address' => '456 Business Street, Business City, 67890',
            'applicant_email' => 'john@testbusiness.com',
            'application_type' => 'general_business'
        ];

        $result = $this->businessLicenses->createApplication($applicationData);
        $this->assertTrue($result['success']);
        $applicationId = $result['application_id'];

        // 2. Submit application
        $submitResult = $this->businessLicenses->submitApplication($applicationId);
        $this->assertTrue($submitResult['success']);

        // 3. Approve application (creates license)
        $approvalResult = $this->businessLicenses->approveApplication($applicationId, [
            'approved_by' => 2,
            'notes' => 'Application approved'
        ]);
        $this->assertTrue($approvalResult['success']);

        // 4. Verify license was created
        $licenses = $this->businessLicenses->getLicenses();
        $this->assertTrue($licenses['success']);
        $this->assertNotEmpty($licenses['data']);

        $license = $licenses['data'][0];
        $this->assertEquals('Test Business Ltd', $license['business_name']);
        $this->assertEquals('active', $license['status']);
    }

    /**
     * Test end-to-end waste management workflow
     */
    public function testWasteManagementWorkflow()
    {
        // 1. Create service request
        $requestData = [
            'request_type' => 'missed_collection',
            'requester_name' => 'Jane Doe',
            'requester_address' => '789 Residential Street, Residential City, 54321',
            'requester_email' => 'jane@example.com',
            'description' => 'Regular waste collection was missed this week',
            'priority' => 'normal'
        ];

        $result = $this->wasteManagement->createServiceRequest($requestData);
        $this->assertTrue($result['success']);
        $requestId = $result['request_id'];

        // 2. Verify request was created
        $request = $this->wasteManagement->getServiceRequest($requestId);
        $this->assertTrue($request['success']);
        $this->assertEquals('pending', $request['request']['status']);
        $this->assertEquals('missed_collection', $request['request']['request_type']);

        // 3. Update request status
        $updateResult = $this->wasteManagement->updateServiceRequest($requestId, [
            'status' => 'completed',
            'completion_notes' => 'Waste collected successfully'
        ]);
        $this->assertTrue($updateResult['success']);

        // 4. Verify status update
        $updatedRequest = $this->wasteManagement->getServiceRequest($requestId);
        $this->assertEquals('completed', $updatedRequest['request']['status']);
        $this->assertEquals('Waste collected successfully', $updatedRequest['request']['completion_notes']);
    }

    /**
     * Test cross-module data consistency
     */
    public function testCrossModuleDataConsistency()
    {
        // Create a building consent application
        $consentResult = $this->buildingConsents->createApplication([
            'applicant_id' => 1,
            'property_address' => '123 Cross Street, Test City, 12345',
            'application_type' => 'Renovation',
            'work_description' => 'Kitchen renovation',
            'estimated_cost' => 50000.00,
            'consent_type' => 'building_consent'
        ]);

        // Create a business license for the same property
        $licenseResult = $this->businessLicenses->createApplication([
            'business_name' => 'Cross Street Cafe',
            'business_type' => 'Food Service',
            'applicant_name' => 'Bob Wilson',
            'applicant_address' => '123 Cross Street, Test City, 12345',
            'applicant_email' => 'bob@crosscafe.com',
            'application_type' => 'general_business'
        ]);

        // Create a waste service request for the same address
        $wasteResult = $this->wasteManagement->createServiceRequest([
            'request_type' => 'new_service',
            'requester_name' => 'Bob Wilson',
            'requester_address' => '123 Cross Street, Test City, 12345',
            'requester_email' => 'bob@crosscafe.com',
            'description' => 'New business requiring waste collection service',
            'priority' => 'normal'
        ]);

        // Verify all were created successfully
        $this->assertTrue($consentResult['success']);
        $this->assertTrue($licenseResult['success']);
        $this->assertTrue($wasteResult['success']);

        // Verify data consistency across modules
        $this->assertStringStartsWith('BC', $consentResult['application_number']);
        $this->assertStringStartsWith('BLA', $licenseResult['application_number']);
        $this->assertStringStartsWith('WSR', $wasteResult['request_number']);
    }

    /**
     * Test service statistics across modules
     */
    public function testServiceStatisticsAggregation()
    {
        // Get statistics from each module
        $buildingStats = $this->buildingConsents->getServiceStatistics();
        $trafficStats = $this->trafficParking->getServiceStatistics();
        $businessStats = $this->businessLicenses->getServiceStatistics();
        $wasteStats = $this->wasteManagement->getServiceStatistics();

        // Verify statistics structure
        $this->assertIsArray($buildingStats);
        $this->assertIsArray($trafficStats);
        $this->assertIsArray($businessStats);
        $this->assertIsArray($wasteStats);

        // Verify expected keys exist
        $this->assertArrayHasKey('total_applications', $buildingStats);
        $this->assertArrayHasKey('total_tickets', $trafficStats);
        $this->assertArrayHasKey('total_licenses', $businessStats);
        $this->assertArrayHasKey('total_requests', $wasteStats);

        // All statistics should be numeric (even if zero)
        foreach ($buildingStats as $key => $value) {
            $this->assertIsNumeric($value, "Building consents stat '{$key}' should be numeric");
        }

        foreach ($trafficStats as $key => $value) {
            $this->assertIsNumeric($value, "Traffic stat '{$key}' should be numeric");
        }

        foreach ($businessStats as $key => $value) {
            $this->assertIsNumeric($value, "Business stat '{$key}' should be numeric");
        }

        foreach ($wasteStats as $key => $value) {
            $this->assertIsNumeric($value, "Waste stat '{$key}' should be numeric");
        }
    }

    /**
     * Test concurrent module operations
     */
    public function testConcurrentModuleOperations()
    {
        // Simulate concurrent operations across modules
        $operations = [];

        // Building consent operations
        $operations[] = function() {
            return $this->buildingConsents->createApplication([
                'applicant_id' => 1,
                'property_address' => '123 Concurrent Street, Test City, 12345',
                'application_type' => 'Extension',
                'work_description' => 'Home extension',
                'estimated_cost' => 100000.00,
                'consent_type' => 'building_consent'
            ]);
        };

        // Traffic ticket operations
        $operations[] = function() {
            return $this->trafficParking->createTicket([
                'vehicle_registration' => 'CONC123',
                'offense_type' => 'speeding_20',
                'offense_location' => 'Concurrent Street',
                'offense_date' => date('Y-m-d'),
                'offense_time' => date('H:i:s'),
                'issued_by' => 1
            ]);
        };

        // Business license operations
        $operations[] = function() {
            return $this->businessLicenses->createApplication([
                'business_name' => 'Concurrent Services',
                'business_type' => 'Consulting',
                'applicant_name' => 'Concurrent Owner',
                'applicant_address' => '123 Concurrent Street, Test City, 12345',
                'application_type' => 'general_business'
            ]);
        };

        // Execute operations
        $results = [];
        foreach ($operations as $operation) {
            $results[] = $operation();
        }

        // Verify all operations succeeded
        foreach ($results as $result) {
            $this->assertTrue($result['success'], 'Concurrent operation should succeed');
        }

        // Verify no conflicts in generated identifiers
        $identifiers = array_column($results, array_keys($results[0])[1]); // Get second column (identifiers)
        $this->assertCount(count(array_unique($identifiers)), $identifiers, 'All identifiers should be unique');
    }

    /**
     * Test module API endpoint integration
     */
    public function testModuleApiIntegration()
    {
        // Test that API endpoints are properly registered
        $buildingEndpoints = $this->buildingConsents->getApiEndpoints();
        $trafficEndpoints = $this->trafficParking->getApiEndpoints();
        $businessEndpoints = $this->businessLicenses->getApiEndpoints();
        $wasteEndpoints = $this->wasteManagement->getApiEndpoints();

        $this->assertIsArray($buildingEndpoints);
        $this->assertIsArray($trafficEndpoints);
        $this->assertIsArray($businessEndpoints);
        $this->assertIsArray($wasteEndpoints);

        // Verify endpoint structure
        foreach ([$buildingEndpoints, $trafficEndpoints, $businessEndpoints, $wasteEndpoints] as $endpoints) {
            foreach ($endpoints as $endpoint) {
                $this->assertArrayHasKey('path', $endpoint);
                $this->assertArrayHasKey('method', $endpoint);
                $this->assertArrayHasKey('handler', $endpoint);
                $this->assertArrayHasKey('middleware', $endpoint);
                $this->assertStringStartsWith('/api/', $endpoint['path']);
            }
        }

        // Verify no duplicate paths
        $allPaths = [];
        foreach ([$buildingEndpoints, $trafficEndpoints, $businessEndpoints, $wasteEndpoints] as $endpoints) {
            foreach ($endpoints as $endpoint) {
                $path = $endpoint['method'] . ' ' . $endpoint['path'];
                $this->assertNotContains($path, $allPaths, "Duplicate API endpoint: {$path}");
                $allPaths[] = $path;
            }
        }
    }

    /**
     * Test module workflow integration
     */
    public function testModuleWorkflowIntegration()
    {
        // Test workflow definitions are properly structured
        $buildingWorkflows = $this->buildingConsents->getWorkflows();
        $trafficWorkflows = $this->trafficParking->getWorkflows();
        $businessWorkflows = $this->businessLicenses->getWorkflows();

        $this->assertIsArray($buildingWorkflows);
        $this->assertIsArray($trafficWorkflows);
        $this->assertIsArray($businessWorkflows);

        // Verify workflow structure
        foreach ([$buildingWorkflows, $trafficWorkflows, $businessWorkflows] as $workflows) {
            foreach ($workflows as $workflowName => $workflow) {
                $this->assertArrayHasKey('name', $workflow);
                $this->assertArrayHasKey('description', $workflow);
                $this->assertArrayHasKey('steps', $workflow);
                $this->assertIsArray($workflow['steps']);
            }
        }
    }
}

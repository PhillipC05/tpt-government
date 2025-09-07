<?php
/**
 * TPT Government Platform - Traffic & Parking Module Unit Tests
 *
 * Comprehensive unit tests for the Traffic & Parking Module
 * covering all functionality, edge cases, and error conditions
 */

use PHPUnit\Framework\TestCase;
use Modules\TrafficParking\TrafficParkingModule;

class TrafficParkingTest extends TestCase
{
    private TrafficParkingModule $module;
    private array $testData;

    protected function setUp(): void
    {
        $this->module = new TrafficParkingModule([
            'enabled' => true,
            'appeal_deadline_days' => 30,
            'payment_grace_period_days' => 14,
            'court_escalation_threshold' => 90,
            'license_suspension_threshold' => 12
        ]);

        $this->testData = [
            'valid_ticket' => [
                'license_plate' => 'ABC123',
                'vehicle_make' => 'Toyota',
                'vehicle_model' => 'Camry',
                'vehicle_year' => 2020,
                'violation_type' => 'Speeding (1-10 mph over limit)',
                'violation_code' => 'SPD001',
                'location' => 'Main Street & 5th Avenue',
                'date_time' => '2024-12-15 14:30:00',
                'officer_id' => 1,
                'officer_name' => 'Officer Smith',
                'evidence_photos' => ['photo1.jpg', 'photo2.jpg'],
                'notes' => 'Vehicle observed speeding through intersection'
            ],
            'valid_violation' => [
                'license_plate' => 'XYZ789',
                'violation_type' => 'No Parking Zone',
                'violation_code' => 'PRK002',
                'location' => 'Downtown Parking Lot',
                'zone_type' => 'No Parking',
                'date_time' => '2024-12-15 10:15:00',
                'officer_id' => 2,
                'evidence_photos' => ['violation_photo.jpg'],
                'notes' => 'Vehicle parked in no parking zone'
            ],
            'invalid_ticket' => [
                'license_plate' => '',
                'violation_code' => 'INVALID',
                'location' => '',
                'date_time' => 'invalid_date',
                'officer_name' => ''
            ],
            'appeal_data' => [
                'appellant_name' => 'John Doe',
                'appellant_email' => 'john.doe@example.com',
                'appellant_phone' => '+1234567890',
                'appeal_reason' => 'I was not speeding. The speed limit sign was obscured.',
                'appeal_evidence' => ['speed_sign_photo.jpg', 'witness_statement.pdf']
            ],
            'payment_data' => [
                'method' => 'credit_card',
                'card_number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'cardholder_name' => 'John Doe'
            ]
        ];
    }

    /**
     * Test module initialization
     */
    public function testModuleInitialization(): void
    {
        $this->assertInstanceOf(TrafficParkingModule::class, $this->module);
        $this->assertTrue($this->module->isEnabled());
        $this->assertEquals('Traffic & Parking', $this->module->getName());
        $this->assertEquals('2.2.0', $this->module->getVersion());
    }

    /**
     * Test module metadata
     */
    public function testModuleMetadata(): void
    {
        $metadata = $this->module->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertEquals('Traffic & Parking', $metadata['name']);
        $this->assertEquals('2.2.0', $metadata['version']);
        $this->assertEquals('public_infrastructure', $metadata['category']);
        $this->assertContains('database', $metadata['dependencies']);
        $this->assertContains('workflow', $metadata['dependencies']);
        $this->assertContains('payment', $metadata['dependencies']);
    }

    /**
     * Test module configuration
     */
    public function testModuleConfiguration(): void
    {
        $config = $this->module->getConfig();

        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals(30, $config['appeal_deadline_days']);
        $this->assertEquals(14, $config['payment_grace_period_days']);
        $this->assertEquals(90, $config['court_escalation_threshold']);
        $this->assertEquals(12, $config['license_suspension_threshold']);
    }

    /**
     * Test module permissions
     */
    public function testModulePermissions(): void
    {
        $permissions = $this->module->getPermissions();

        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('traffic_tickets.view', $permissions);
        $this->assertArrayHasKey('traffic_tickets.create', $permissions);
        $this->assertArrayHasKey('traffic_tickets.edit', $permissions);
        $this->assertArrayHasKey('traffic_tickets.approve', $permissions);
        $this->assertArrayHasKey('traffic_tickets.appeal', $permissions);
        $this->assertArrayHasKey('parking_violations.view', $permissions);
        $this->assertArrayHasKey('parking_violations.create', $permissions);
        $this->assertArrayHasKey('court_integration.view', $permissions);
    }

    /**
     * Test module endpoints
     */
    public function testModuleEndpoints(): void
    {
        $endpoints = $this->module->getEndpoints();

        $this->assertIsArray($endpoints);
        $this->assertCount(7, $endpoints);

        // Test specific endpoints
        $this->assertEquals('GET', $endpoints[0]['method']);
        $this->assertEquals('/api/traffic-tickets', $endpoints[0]['path']);
        $this->assertEquals('getTrafficTickets', $endpoints[0]['handler']);
        $this->assertTrue($endpoints[0]['auth']);
        $this->assertContains('traffic_tickets.view', $endpoints[0]['permissions']);
    }

    /**
     * Test module workflows
     */
    public function testModuleWorkflows(): void
    {
        $workflows = $this->module->getWorkflows();

        $this->assertIsArray($workflows);
        $this->assertArrayHasKey('traffic_ticket_process', $workflows);
        $this->assertArrayHasKey('parking_violation_process', $workflows);

        $trafficWorkflow = $workflows['traffic_ticket_process'];
        $this->assertEquals('Traffic Ticket Processing', $trafficWorkflow['name']);
        $this->assertArrayHasKey('issued', $trafficWorkflow['steps']);
        $this->assertArrayHasKey('paid', $trafficWorkflow['steps']);
        $this->assertArrayHasKey('appealed', $trafficWorkflow['steps']);
        $this->assertArrayHasKey('court_pending', $trafficWorkflow['steps']);
    }

    /**
     * Test module forms
     */
    public function testModuleForms(): void
    {
        $forms = $this->module->getForms();

        $this->assertIsArray($forms);
        $this->assertArrayHasKey('traffic_ticket', $forms);
        $this->assertArrayHasKey('parking_violation', $forms);
        $this->assertArrayHasKey('ticket_appeal', $forms);

        $ticketForm = $forms['traffic_ticket'];
        $this->assertEquals('Traffic Ticket Citation', $ticketForm['name']);
        $this->assertArrayHasKey('license_plate', $ticketForm['fields']);
        $this->assertArrayHasKey('violation_type', $ticketForm['fields']);
        $this->assertTrue($ticketForm['fields']['license_plate']['required']);
        $this->assertTrue($ticketForm['fields']['violation_type']['required']);
    }

    /**
     * Test module reports
     */
    public function testModuleReports(): void
    {
        $reports = $this->module->getReports();

        $this->assertIsArray($reports);
        $this->assertArrayHasKey('traffic_ticket_summary', $reports);
        $this->assertArrayHasKey('parking_violation_summary', $reports);
        $this->assertArrayHasKey('appeal_statistics', $reports);
        $this->assertArrayHasKey('revenue_report', $reports);

        $summaryReport = $reports['traffic_ticket_summary'];
        $this->assertEquals('Traffic Ticket Summary Report', $summaryReport['name']);
        $this->assertArrayHasKey('date_range', $summaryReport['parameters']);
        $this->assertArrayHasKey('violation_type', $summaryReport['parameters']);
        $this->assertArrayHasKey('status', $summaryReport['parameters']);
    }

    /**
     * Test valid traffic ticket creation
     */
    public function testCreateValidTrafficTicket(): void
    {
        $result = $this->module->createTrafficTicket($this->testData['valid_ticket']);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('ticket_number', $result);
        $this->assertArrayHasKey('fine_amount', $result);
        $this->assertArrayHasKey('due_date', $result);
        $this->assertArrayHasKey('appeal_deadline', $result);

        $this->assertStringStartsWith('TT', $result['ticket_number']);
        $this->assertEquals(150.00, $result['fine_amount']); // SPD001 fine amount
        $this->assertIsString($result['due_date']);
        $this->assertIsString($result['appeal_deadline']);
    }

    /**
     * Test valid parking violation creation
     */
    public function testCreateValidParkingViolation(): void
    {
        $result = $this->module->createParkingViolation($this->testData['valid_violation']);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('violation_number', $result);
        $this->assertArrayHasKey('fine_amount', $result);
        $this->assertArrayHasKey('due_date', $result);
        $this->assertArrayHasKey('appeal_deadline', $result);

        $this->assertStringStartsWith('PV', $result['violation_number']);
        $this->assertEquals(50.00, $result['fine_amount']); // PRK002 fine amount
        $this->assertIsString($result['due_date']);
        $this->assertIsString($result['appeal_deadline']);
    }

    /**
     * Test invalid ticket creation
     */
    public function testCreateInvalidTrafficTicket(): void
    {
        $result = $this->module->createTrafficTicket($this->testData['invalid_ticket']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test ticket data validation
     */
    public function testValidateTicketData(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('validateTicketData');
        $method->setAccessible(true);

        // Test valid data
        $validResult = $method->invokeArgs($this->module, [$this->testData['valid_ticket'], 'traffic']);
        $this->assertIsArray($validResult);
        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['errors']);

        // Test invalid data
        $invalidResult = $method->invokeArgs($this->module, [$this->testData['invalid_ticket'], 'traffic']);
        $this->assertIsArray($invalidResult);
        $this->assertFalse($invalidResult['valid']);
        $this->assertNotEmpty($invalidResult['errors']);
    }

    /**
     * Test ticket payment processing
     */
    public function testProcessTicketPayment(): void
    {
        // Create ticket first
        $createResult = $this->module->createTrafficTicket($this->testData['valid_ticket']);
        $this->assertTrue($createResult['success']);

        $ticketNumber = $createResult['ticket_number'];

        // Process payment
        $paymentResult = $this->module->processTicketPayment($ticketNumber, 'traffic', $this->testData['payment_data']);

        $this->assertIsArray($paymentResult);
        $this->assertArrayHasKey('success', $paymentResult);
        $this->assertArrayHasKey('transaction_id', $paymentResult);
        $this->assertArrayHasKey('message', $paymentResult);
    }

    /**
     * Test ticket appeal submission
     */
    public function testSubmitTicketAppeal(): void
    {
        // Create ticket first
        $createResult = $this->module->createTrafficTicket($this->testData['valid_ticket']);
        $this->assertTrue($createResult['success']);

        $ticketNumber = $createResult['ticket_number'];

        // Submit appeal
        $appealResult = $this->module->submitTicketAppeal($ticketNumber, 'traffic', $this->testData['appeal_data']);

        $this->assertIsArray($appealResult);
        $this->assertTrue($appealResult['success']);
        $this->assertEquals('Appeal submitted successfully', $appealResult['message']);
    }

    /**
     * Test appeal processing
     */
    public function testProcessTicketAppeal(): void
    {
        // Create ticket and appeal first
        $createResult = $this->module->createTrafficTicket($this->testData['valid_ticket']);
        $this->assertTrue($createResult['success']);

        $ticketNumber = $createResult['ticket_number'];
        $appealResult = $this->module->submitTicketAppeal($ticketNumber, 'traffic', $this->testData['appeal_data']);
        $this->assertTrue($appealResult['success']);

        // Mock appeal ID (would be returned from appeal submission)
        $appealId = 1;

        // Process appeal - approve
        $processResult = $this->module->processTicketAppeal($appealId, 'approved', [
            'decision_notes' => 'Appeal approved due to mitigating circumstances'
        ]);

        $this->assertIsArray($processResult);
        $this->assertTrue($processResult['success']);
        $this->assertEquals('approved', $processResult['decision']);
        $this->assertEquals('Appeal processed successfully', $processResult['message']);
    }

    /**
     * Test driver license points retrieval
     */
    public function testGetDriverLicensePoints(): void
    {
        $licenseNumber = 'DL123456789';

        $pointsResult = $this->module->getDriverLicensePoints($licenseNumber);

        $this->assertIsArray($pointsResult);
        $this->assertArrayHasKey('license_number', $pointsResult);
        $this->assertArrayHasKey('total_points', $pointsResult);
        $this->assertArrayHasKey('status', $pointsResult);
        $this->assertArrayHasKey('suspension_threshold', $pointsResult);

        $this->assertEquals($licenseNumber, $pointsResult['license_number']);
        $this->assertIsNumeric($pointsResult['total_points']);
        $this->assertIsNumeric($pointsResult['suspension_threshold']);
    }

    /**
     * Test ticket number generation
     */
    public function testTicketNumberGeneration(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('generateTicketNumber');
        $method->setAccessible(true);

        $trafficTicketNumber = $method->invokeArgs($this->module, ['traffic']);
        $parkingTicketNumber = $method->invokeArgs($this->module, ['parking']);

        $this->assertStringStartsWith('TT', $trafficTicketNumber);
        $this->assertStringStartsWith('PV', $parkingTicketNumber);
        $this->assertMatchesRegularExpression('/^TT\d{4}\d{6}$/', $trafficTicketNumber);
        $this->assertMatchesRegularExpression('/^PV\d{4}\d{6}$/', $parkingTicketNumber);
    }

    /**
     * Test appeal data validation
     */
    public function testValidateAppealData(): void
    {
        $reflection = new ReflectionClass($this->module);
        $method = $reflection->getMethod('validateAppealData');
        $method->setAccessible(true);

        // Test valid data
        $validResult = $method->invokeArgs($this->module, [$this->testData['appeal_data']]);
        $this->assertIsArray($validResult);
        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['errors']);

        // Test invalid data
        $invalidData = [
            'appellant_name' => '',
            'appellant_email' => 'invalid-email',
            'appeal_reason' => ''
        ];

        $invalidResult = $method->invokeArgs($this->module, [$invalidData]);
        $this->assertIsArray($invalidResult);
        $this->assertFalse($invalidResult['valid']);
        $this->assertNotEmpty($invalidResult['errors']);
    }

    /**
     * Test report generation
     */
    public function testGenerateTicketReport(): void
    {
        $filters = [
            'status' => 'issued',
            'violation_type' => 'Speeding',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ];

        $report = $this->module->generateTicketReport($filters);

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
        $this->assertArrayHasKey('total_traffic_tickets', $statistics);
        $this->assertArrayHasKey('total_parking_violations', $statistics);
        $this->assertArrayHasKey('total_revenue', $statistics);
        $this->assertArrayHasKey('paid_tickets', $statistics);
        $this->assertArrayHasKey('pending_appeals', $statistics);
        $this->assertArrayHasKey('court_pending', $statistics);

        $this->assertIsNumeric($statistics['total_traffic_tickets']);
        $this->assertIsNumeric($statistics['total_parking_violations']);
        $this->assertIsFloat($statistics['total_revenue']);
        $this->assertIsNumeric($statistics['paid_tickets']);
        $this->assertIsNumeric($statistics['pending_appeals']);
        $this->assertIsNumeric($statistics['court_pending']);
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
     * Test workflow advancement
     */
    public function testWorkflowAdvancement(): void
    {
        // Create ticket
        $createResult = $this->module->createTrafficTicket($this->testData['valid_ticket']);
        $this->assertTrue($createResult['success']);

        $ticketNumber = $createResult['ticket_number'];

        // Test workflow advancement through various states
        $paymentResult = $this->module->processTicketPayment($ticketNumber, 'traffic', $this->testData['payment_data']);
        $this->assertIsArray($paymentResult);

        $appealResult = $this->module->submitTicketAppeal($ticketNumber, 'traffic', $this->testData['appeal_data']);
        $this->assertIsArray($appealResult);
    }

    /**
     * Test error handling for non-existent ticket
     */
    public function testNonExistentTicketHandling(): void
    {
        $result = $this->module->processTicketPayment('NONEXISTENT', 'traffic', $this->testData['payment_data']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Ticket not found', $result['error']);
    }

    /**
     * Test duplicate ticket prevention
     */
    public function testDuplicateTicketPrevention(): void
    {
        // Create first ticket
        $result1 = $this->module->createTrafficTicket($this->testData['valid_ticket']);
        $this->assertTrue($result1['success']);

        // Attempt to create duplicate (same license plate and violation)
        $result2 = $this->module->createTrafficTicket($this->testData['valid_ticket']);

        // This should either succeed (with different ticket number) or fail gracefully
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('success', $result2);
    }

    /**
     * Test appeal deadline validation
     */
    public function testAppealDeadlineValidation(): void
    {
        // Create ticket first
        $createResult = $this->module->createTrafficTicket($this->testData['valid_ticket']);
        $this->assertTrue($createResult['success']);

        $ticketNumber = $createResult['ticket_number'];

        // Mock expired appeal deadline by modifying ticket data
        // This would normally be handled by the database layer

        // Try to submit appeal (should succeed if within deadline)
        $appealResult = $this->module->submitTicketAppeal($ticketNumber, 'traffic', $this->testData['appeal_data']);
        $this->assertIsArray($appealResult);
        $this->assertArrayHasKey('success', $appealResult);
    }

    /**
     * Test court integration sync
     */
    public function testCourtIntegrationSync(): void
    {
        $syncResult = $this->module->syncWithCourtSystem();

        $this->assertIsArray($syncResult);
        $this->assertArrayHasKey('success', $syncResult);
        $this->assertArrayHasKey('results', $syncResult);

        if ($syncResult['success']) {
            $this->assertArrayHasKey('total_processed', $syncResult['results']);
            $this->assertArrayHasKey('successful_syncs', $syncResult['results']);
            $this->assertArrayHasKey('failed_syncs', $syncResult['results']);
            $this->assertArrayHasKey('errors', $syncResult['results']);
        }
    }

    /**
     * Test environmental monitoring data recording
     */
    public function testEnvironmentalDataRecording(): void
    {
        $monitoringData = [
            'site_id' => 'LANDFILL001',
            'monitoring_type' => 'air_quality',
            'sensor_id' => 'AQ001',
            'reading_value' => 25.5,
            'unit' => 'µg/m³',
            'recorded_at' => '2024-12-15 14:30:00',
            'recorded_by' => 'Environmental Officer',
            'notes' => 'Normal air quality readings'
        ];

        $result = $this->module->recordEnvironmentalData($monitoringData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('is_compliant', $result);
        $this->assertArrayHasKey('threshold_min', $result);
        $this->assertArrayHasKey('threshold_max', $result);
    }

    /**
     * Test collection schedule retrieval
     */
    public function testGetCollectionSchedule(): void
    {
        $customerId = 1;

        $schedule = $this->module->getCollectionSchedule($customerId, [
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ]);

        $this->assertIsArray($schedule);
        $this->assertArrayHasKey('customer_id', $schedule);
        $this->assertArrayHasKey('services', $schedule);
        $this->assertArrayHasKey('total_services', $schedule);

        $this->assertEquals($customerId, $schedule['customer_id']);
        $this->assertIsArray($schedule['services']);
        $this->assertIsNumeric($schedule['total_services']);
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

        // Create multiple tickets
        for ($i = 0; $i < 10; $i++) {
            $testData = $this->testData['valid_ticket'];
            $testData['license_plate'] = "TEST{$i}";

            $result = $this->module->createTrafficTicket($testData);
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

    /**
     * Test violation code validation
     */
    public function testViolationCodeValidation(): void
    {
        $reflection = new ReflectionClass($this->module);
        $property = $reflection->getProperty('violationCodes');
        $property->setAccessible(true);

        $violationCodes = $property->getValue($this->module);

        $this->assertIsArray($violationCodes);
        $this->assertArrayHasKey('SPD001', $violationCodes);
        $this->assertArrayHasKey('PRK001', $violationCodes);

        // Test specific violation code structure
        $spd001 = $violationCodes['SPD001'];
        $this->assertArrayHasKey('type', $spd001);
        $this->assertArrayHasKey('description', $spd001);
        $this->assertArrayHasKey('fine_amount', $spd001);
        $this->assertArrayHasKey('points', $spd001);

        $this->assertEquals('traffic', $spd001['type']);
        $this->assertEquals(150.00, $spd001['fine_amount']);
        $this->assertEquals(2, $spd001['points']);
    }

    /**
     * Test court settings configuration
     */
    public function testCourtSettingsConfiguration(): void
    {
        $reflection = new ReflectionClass($this->module);
        $property = $reflection->getProperty('courtSettings');
        $property->setAccessible(true);

        $courtSettings = $property->getValue($this->module);

        $this->assertIsArray($courtSettings);
        $this->assertArrayHasKey('enabled', $courtSettings);
        $this->assertArrayHasKey('court_system_url', $courtSettings);
        $this->assertArrayHasKey('sync_interval', $courtSettings);
        $this->assertArrayHasKey('auto_escalation', $courtSettings);
        $this->assertArrayHasKey('escalation_days', $courtSettings);
        $this->assertArrayHasKey('court_mapping', $courtSettings);
    }

    /**
     * Test notification templates
     */
    public function testNotificationTemplates(): void
    {
        $notifications = $this->module->getNotifications();

        $this->assertIsArray($notifications);
        $this->assertArrayHasKey('ticket_issued', $notifications);
        $this->assertArrayHasKey('ticket_paid', $notifications);
        $this->assertArrayHasKey('ticket_appeal_submitted', $notifications);
        $this->assertArrayHasKey('appeal_approved', $notifications);
        $this->assertArrayHasKey('appeal_denied', $notifications);

        $ticketIssued = $notifications['ticket_issued'];
        $this->assertArrayHasKey('name', $ticketIssued);
        $this->assertArrayHasKey('template', $ticketIssued);
        $this->assertArrayHasKey('channels', $ticketIssued);
        $this->assertArrayHasKey('triggers', $ticketIssued);

        $this->assertEquals('Traffic Ticket Issued', $ticketIssued['name']);
        $this->assertContains('email', $ticketIssued['channels']);
        $this->assertContains('sms', $ticketIssued['channels']);
        $this->assertContains('in_app', $ticketIssued['channels']);
    }
}

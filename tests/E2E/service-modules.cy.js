/**
 * TPT Government Platform - Service Modules End-to-End Tests
 *
 * Comprehensive E2E tests for all service modules
 * testing complete user journeys and browser-based functionality
 */

describe('TPT Government Platform - Service Modules E2E Tests', () => {
    const baseUrl = Cypress.config('baseUrl') || 'http://localhost:3000';

    // Test user credentials
    const testUser = {
        email: 'test.user@example.com',
        password: 'TestPassword123!',
        firstName: 'Test',
        lastName: 'User'
    };

    // Test data
    const testData = {
        business: {
            name: 'E2E Test Construction Ltd',
            type: 'construction',
            address: '123 Test Business St, Test City, TC 12345',
            revenue: 250000,
            employees: 15,
            description: 'General construction and renovation services for E2E testing'
        },
        property: {
            address: '456 Test Property Ave, Test City, TC 12345',
            type: 'residential',
            value: 500000
        },
        project: {
            name: 'E2E Test Home Extension',
            type: 'addition',
            cost: 75000,
            area: 45,
            storeys: 1,
            description: 'Single storey extension for additional living space'
        }
    };

    before(() => {
        // Reset database and create test user
        cy.request('POST', `${baseUrl}/api/test/reset-database`);
        cy.request('POST', `${baseUrl}/api/test/create-user`, testUser);
    });

    beforeEach(() => {
        // Login before each test
        cy.visit(`${baseUrl}/login`);
        cy.get('[data-cy="email"]').type(testUser.email);
        cy.get('[data-cy="password"]').type(testUser.password);
        cy.get('[data-cy="login-button"]').click();
        cy.url().should('not.include', '/login');
    });

    describe('Complete Business Establishment Journey', () => {
        it('should complete full business establishment workflow', () => {
            // Step 1: Navigate to business licenses
            cy.visit(`${baseUrl}/services/business-licenses`);
            cy.contains('Business Licenses').should('be.visible');

            // Step 2: Start new business license application
            cy.get('[data-cy="new-application-button"]').click();
            cy.url().should('include', '/business-licenses/apply');

            // Step 3: Fill out business license application
            cy.get('[data-cy="business-name"]').type(testData.business.name);
            cy.get('[data-cy="business-type"]').select(testData.business.type);
            cy.get('[data-cy="business-address"]').type(testData.business.address);
            cy.get('[data-cy="estimated-revenue"]').type(testData.business.revenue.toString());
            cy.get('[data-cy="employee-count"]').type(testData.business.employees.toString());
            cy.get('[data-cy="business-description"]').type(testData.business.description);

            // Upload required documents
            cy.get('[data-cy="business-plan-upload"]').selectFile('cypress/fixtures/test-business-plan.pdf', { force: true });
            cy.get('[data-cy="insurance-certificate-upload"]').selectFile('cypress/fixtures/test-insurance.pdf', { force: true });

            // Step 4: Submit application
            cy.get('[data-cy="submit-application"]').click();
            cy.contains('Application submitted successfully').should('be.visible');

            // Get application ID from URL or response
            cy.url().should('match', /\/business-licenses\/application\/[A-Z]{2}\d{4}\d{6}$/);
            cy.url().then(url => {
                const applicationId = url.split('/').pop();

                // Step 5: Verify application status
                cy.contains('Application Status: Submitted').should('be.visible');
                cy.contains('Processing Time: 15 days').should('be.visible');

                // Step 6: Navigate to building consents
                cy.visit(`${baseUrl}/services/building-consents`);
                cy.contains('Building Consents').should('be.visible');

                // Step 7: Start building consent application
                cy.get('[data-cy="new-consent-button"]').click();
                cy.url().should('include', '/building-consents/apply');

                // Step 8: Fill out building consent application
                cy.get('[data-cy="project-name"]').type(testData.project.name);
                cy.get('[data-cy="project-type"]').select(testData.project.type);
                cy.get('[data-cy="property-address"]').type(testData.property.address);
                cy.get('[data-cy="property-type"]').select(testData.property.type);
                cy.get('[data-cy="consent-type"]').select('full');
                cy.get('[data-cy="estimated-cost"]').type(testData.project.cost.toString());
                cy.get('[data-cy="floor-area"]').type(testData.project.area.toString());
                cy.get('[data-cy="storeys"]').type(testData.project.storeys.toString());
                cy.get('[data-cy="architect-name"]').type('Test Architect');
                cy.get('[data-cy="contractor-name"]').type(testData.business.name);
                cy.get('[data-cy="project-description"]').type(testData.project.description);

                // Upload plans and documents
                cy.get('[data-cy="site-plan-upload"]').selectFile('cypress/fixtures/test-site-plan.pdf', { force: true });
                cy.get('[data-cy="floor-plans-upload"]').selectFile('cypress/fixtures/test-floor-plans.pdf', { force: true });
                cy.get('[data-cy="elevations-upload"]').selectFile('cypress/fixtures/test-elevations.pdf', { force: true });
                cy.get('[data-cy="specifications-upload"]').selectFile('cypress/fixtures/test-specifications.pdf', { force: true });

                // Step 9: Submit building consent
                cy.get('[data-cy="submit-consent"]').click();
                cy.contains('Building consent application submitted successfully').should('be.visible');

                // Step 10: Navigate to waste management
                cy.visit(`${baseUrl}/services/waste-management`);
                cy.contains('Waste Management').should('be.visible');

                // Step 11: Set up waste collection service
                cy.get('[data-cy="new-service-button"]').click();
                cy.get('[data-cy="service-type"]').select('commercial');
                cy.get('[data-cy="collection-frequency"]').select('weekly');
                cy.get('[data-cy="collection-day"]').select('friday');
                cy.get('[data-cy="collection-time"]').type('08:00');
                cy.get('[data-cy="service-address"]').type(testData.business.address);
                cy.get('[data-cy="bin-size"]').select('large');
                cy.get('[data-cy="waste-types"]').select(['municipal_solid_waste', 'recyclables']);
                cy.get('[data-cy="special-instructions"]').type('Commercial waste for construction business');

                // Step 12: Submit waste service request
                cy.get('[data-cy="submit-service"]').click();
                cy.contains('Waste collection service scheduled successfully').should('be.visible');

                // Step 13: Verify dashboard shows all services
                cy.visit(`${baseUrl}/dashboard`);
                cy.contains('My Services').should('be.visible');
                cy.contains(testData.business.name).should('be.visible');
                cy.contains(testData.project.name).should('be.visible');
                cy.contains('Commercial Waste Collection').should('be.visible');
            });
        });
    });

    describe('Building Consent Application Process', () => {
        it('should handle building consent application lifecycle', () => {
            // Navigate to building consents
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.get('[data-cy="new-consent-button"]').click();

            // Fill out application
            cy.get('[data-cy="project-name"]').type('E2E Building Test');
            cy.get('[data-cy="project-type"]').select('new_construction');
            cy.get('[data-cy="property-address"]').type('789 Construction St, Test City');
            cy.get('[data-cy="property-type"]').select('residential');
            cy.get('[data-cy="consent-type"]').select('full');
            cy.get('[data-cy="estimated-cost"]').type('100000');
            cy.get('[data-cy="floor-area"]').type('100');
            cy.get('[data-cy="storeys"]').type('2');
            cy.get('[data-cy="architect-name"]').type('E2E Architect');
            cy.get('[data-cy="contractor-name"]').type('E2E Builder');
            cy.get('[data-cy="project-description"]').type('Two storey residential construction');

            // Submit application
            cy.get('[data-cy="submit-consent"]').click();
            cy.contains('Building consent application submitted successfully').should('be.visible');

            // Verify application appears in list
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.contains('E2E Building Test').should('be.visible');
            cy.contains('Draft').should('be.visible');

            // Test application editing
            cy.contains('E2E Building Test').click();
            cy.get('[data-cy="edit-application"]').click();
            cy.get('[data-cy="project-description"]').clear().type('Updated two storey residential construction');
            cy.get('[data-cy="save-changes"]').click();
            cy.contains('Application updated successfully').should('be.visible');

            // Test document upload
            cy.get('[data-cy="upload-documents"]').click();
            cy.get('[data-cy="file-upload"]').selectFile('cypress/fixtures/test-document.pdf', { force: true });
            cy.get('[data-cy="upload-button"]').click();
            cy.contains('Document uploaded successfully').should('be.visible');
        });
    });

    describe('Traffic & Parking Services', () => {
        it('should handle traffic ticket and parking violation workflows', () => {
            // Navigate to traffic tickets
            cy.visit(`${baseUrl}/services/traffic-tickets`);

            // Check for existing tickets
            cy.contains('Traffic Tickets').should('be.visible');

            // Test license plate lookup
            cy.get('[data-cy="license-lookup"]').type('ABC123');
            cy.get('[data-cy="search-button"]').click();

            // Should show ticket history or no tickets found
            cy.get('body').should('contain', 'No tickets found').or('contain', 'Ticket History');

            // Navigate to parking violations
            cy.visit(`${baseUrl}/services/parking-violations`);
            cy.contains('Parking Violations').should('be.visible');

            // Test appeal submission
            cy.get('[data-cy="new-appeal-button"]').click();
            cy.get('[data-cy="ticket-number"]').type('TT2024001');
            cy.get('[data-cy="appellant-name"]').type('John Doe');
            cy.get('[data-cy="appellant-email"]').type('john.doe@example.com');
            cy.get('[data-cy="appeal-reason"]').type('I was not speeding. The speed limit sign was obscured by construction.');
            cy.get('[data-cy="evidence-upload"]').selectFile('cypress/fixtures/test-evidence.jpg', { force: true });
            cy.get('[data-cy="submit-appeal"]').click();
            cy.contains('Appeal submitted successfully').should('be.visible');
        });
    });

    describe('Waste Management Services', () => {
        it('should handle waste collection requests and service management', () => {
            // Navigate to waste management
            cy.visit(`${baseUrl}/services/waste-management`);
            cy.contains('Waste Management').should('be.visible');

            // Test collection request
            cy.get('[data-cy="new-request-button"]').click();
            cy.get('[data-cy="request-type"]').select('one_time');
            cy.get('[data-cy="waste-type"]').select('construction_debris');
            cy.get('[data-cy="quantity"]').type('5');
            cy.get('[data-cy="unit"]').select('tons');
            cy.get('[data-cy="pickup-address"]').type('123 Construction Site, Test City');
            cy.get('[data-cy="pickup-date"]').type('2024-12-20');
            cy.get('[data-cy="pickup-time-slot"]').select('morning');
            cy.get('[data-cy="special-handling"]').check();
            cy.get('[data-cy="description"]').type('Construction waste from demolition project');

            cy.get('[data-cy="submit-request"]').click();
            cy.contains('Collection request submitted successfully').should('be.visible');

            // Test service scheduling
            cy.get('[data-cy="schedule-service-button"]').click();
            cy.get('[data-cy="service-type"]').select('residential');
            cy.get('[data-cy="collection-frequency"]').select('weekly');
            cy.get('[data-cy="collection-day"]').select('monday');
            cy.get('[data-cy="collection-time"]').type('07:00');
            cy.get('[data-cy="service-address"]').type('456 Residential St, Test City');
            cy.get('[data-cy="bin-size"]').select('medium');
            cy.get('[data-cy="waste-types"]').select(['municipal_solid_waste', 'recyclables', 'organic_waste']);

            cy.get('[data-cy="submit-service"]').click();
            cy.contains('Waste collection service scheduled successfully').should('be.visible');

            // Test billing
            cy.visit(`${baseUrl}/services/waste-management/billing`);
            cy.contains('Waste Management Billing').should('be.visible');
            cy.get('[data-cy="billing-period"]').select('current_month');
            cy.get('[data-cy="generate-bill"]').click();
            cy.contains('Bill generated successfully').should('be.visible');
        });
    });

    describe('Business License Management', () => {
        it('should handle business license application and renewal', () => {
            // Navigate to business licenses
            cy.visit(`${baseUrl}/services/business-licenses`);
            cy.get('[data-cy="new-application-button"]').click();

            // Fill out application
            cy.get('[data-cy="business-name"]').type('E2E Business License Test');
            cy.get('[data-cy="business-type"]').select('retail');
            cy.get('[data-cy="business-address"]').type('789 Business Ave, Test City');
            cy.get('[data-cy="license-type"]').select('retail_general');
            cy.get('[data-cy="estimated-revenue"]').type('150000');
            cy.get('[data-cy="employee-count"]').type('8');
            cy.get('[data-cy="business-description"]').type('General retail store selling various goods');

            // Upload documents
            cy.get('[data-cy="business-plan-upload"]').selectFile('cypress/fixtures/test-business-plan.pdf', { force: true });
            cy.get('[data-cy="financial-statements-upload"]').selectFile('cypress/fixtures/test-financials.pdf', { force: true });
            cy.get('[data-cy="owner-id-upload"]').selectFile('cypress/fixtures/test-id.pdf', { force: true });

            // Submit application
            cy.get('[data-cy="submit-application"]').click();
            cy.contains('Business license application submitted successfully').should('be.visible');

            // Test renewal process
            cy.visit(`${baseUrl}/services/business-licenses`);
            cy.contains('E2E Business License Test').click();
            cy.get('[data-cy="renew-license"]').click();
            cy.get('[data-cy="renewal-reason"]').type('Annual renewal');
            cy.get('[data-cy="submit-renewal"]').click();
            cy.contains('License renewal submitted successfully').should('be.visible');
        });
    });

    describe('Payment Processing', () => {
        it('should handle payments across all service modules', () => {
            // Navigate to payments section
            cy.visit(`${baseUrl}/payments`);
            cy.contains('Payment History').should('be.visible');

            // Test payment method setup
            cy.get('[data-cy="add-payment-method"]').click();
            cy.get('[data-cy="card-number"]').type('4111111111111111');
            cy.get('[data-cy="expiry-month"]').select('12');
            cy.get('[data-cy="expiry-year"]').select('2025');
            cy.get('[data-cy="cvv"]').type('123');
            cy.get('[data-cy="cardholder-name"]').type('Test User');
            cy.get('[data-cy="save-card"]').click();
            cy.contains('Payment method saved successfully').should('be.visible');

            // Test outstanding payments
            cy.get('[data-cy="outstanding-payments"]').click();
            cy.get('body').should('contain', 'Outstanding Payments').or('contain', 'No outstanding payments');

            // Test payment history
            cy.get('[data-cy="payment-history"]').click();
            cy.get('body').should('contain', 'Payment History').or('contain', 'No payment history');
        });
    });

    describe('Notifications and Communication', () => {
        it('should handle notifications and communication features', () => {
            // Navigate to notifications
            cy.visit(`${baseUrl}/notifications`);
            cy.contains('Notifications').should('be.visible');

            // Test notification preferences
            cy.get('[data-cy="notification-settings"]').click();
            cy.get('[data-cy="email-notifications"]').should('be.checked');
            cy.get('[data-cy="sms-notifications"]').check();
            cy.get('[data-cy="push-notifications"]').check();
            cy.get('[data-cy="save-preferences"]').click();
            cy.contains('Notification preferences saved').should('be.visible');

            // Test notification history
            cy.get('[data-cy="notification-history"]').click();
            cy.get('body').should('contain', 'Notification History').or('contain', 'No notifications');

            // Test message center
            cy.visit(`${baseUrl}/messages`);
            cy.contains('Messages').should('be.visible');
            cy.get('[data-cy="compose-message"]').click();
            cy.get('[data-cy="recipient"]').type('admin@gov.example.com');
            cy.get('[data-cy="subject"]').type('Test Message from E2E');
            cy.get('[data-cy="message-body"]').type('This is a test message from E2E testing suite.');
            cy.get('[data-cy="send-message"]').click();
            cy.contains('Message sent successfully').should('be.visible');
        });
    });

    describe('Reports and Analytics', () => {
        it('should generate and display reports correctly', () => {
            // Navigate to reports
            cy.visit(`${baseUrl}/reports`);
            cy.contains('Reports & Analytics').should('be.visible');

            // Test building consent reports
            cy.get('[data-cy="building-consent-reports"]').click();
            cy.get('[data-cy="report-type"]').select('consent_overview');
            cy.get('[data-cy="date-from"]').type('2024-01-01');
            cy.get('[data-cy="date-to"]').type('2024-12-31');
            cy.get('[data-cy="generate-report"]').click();
            cy.contains('Report generated successfully').should('be.visible');

            // Test traffic reports
            cy.get('[data-cy="traffic-reports"]').click();
            cy.get('[data-cy="report-type"]').select('traffic_ticket_summary');
            cy.get('[data-cy="generate-report"]').click();
            cy.contains('Report generated successfully').should('be.visible');

            // Test waste management reports
            cy.get('[data-cy="waste-reports"]').click();
            cy.get('[data-cy="report-type"]').select('waste_collection_summary');
            cy.get('[data-cy="generate-report"]').click();
            cy.contains('Report generated successfully').should('be.visible');

            // Test business license reports
            cy.get('[data-cy="business-reports"]').click();
            cy.get('[data-cy="report-type"]').select('license_overview');
            cy.get('[data-cy="generate-report"]').click();
            cy.contains('Report generated successfully').should('be.visible');
        });
    });

    describe('Search and Navigation', () => {
        it('should handle search and navigation features', () => {
            // Test global search
            cy.get('[data-cy="global-search"]').type('building consent');
            cy.get('[data-cy="search-button"]').click();
            cy.get('body').should('contain', 'Search Results').or('contain', 'No results found');

            // Test service navigation
            cy.visit(`${baseUrl}/services`);
            cy.contains('Government Services').should('be.visible');

            // Test navigation between services
            cy.get('[data-cy="building-consents-link"]').click();
            cy.url().should('include', '/building-consents');
            cy.go('back');

            cy.get('[data-cy="business-licenses-link"]').click();
            cy.url().should('include', '/business-licenses');
            cy.go('back');

            cy.get('[data-cy="traffic-parking-link"]').click();
            cy.url().should('include', '/traffic-parking');
            cy.go('back');

            cy.get('[data-cy="waste-management-link"]').click();
            cy.url().should('include', '/waste-management');
        });
    });

    describe('Accessibility and Responsiveness', () => {
        it('should be accessible and responsive', () => {
            // Test keyboard navigation
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.get('body').tab().tab().tab(); // Navigate through focusable elements

            // Test screen reader compatibility
            cy.get('[data-cy="new-consent-button"]').should('have.attr', 'aria-label');
            cy.get('[data-cy="project-name"]').should('have.attr', 'aria-describedby');

            // Test mobile responsiveness
            cy.viewport('iphone-6');
            cy.visit(`${baseUrl}/dashboard`);
            cy.contains('Dashboard').should('be.visible');

            // Test tablet responsiveness
            cy.viewport('ipad-2');
            cy.visit(`${baseUrl}/services`);
            cy.contains('Government Services').should('be.visible');

            // Test desktop responsiveness
            cy.viewport('macbook-15');
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.contains('Building Consents').should('be.visible');
        });
    });

    describe('Error Handling and Edge Cases', () => {
        it('should handle errors and edge cases gracefully', () => {
            // Test invalid form submission
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.get('[data-cy="new-consent-button"]').click();
            cy.get('[data-cy="submit-consent"]').click();
            cy.contains('Please fill in all required fields').should('be.visible');

            // Test file upload validation
            cy.get('[data-cy="site-plan-upload"]').selectFile('cypress/fixtures/invalid-file.txt', { force: true });
            cy.contains('Invalid file type').should('be.visible');

            // Test network error simulation
            cy.intercept('POST', '**/api/building-consents', { forceNetworkError: true });
            cy.get('[data-cy="project-name"]').type('Network Error Test');
            cy.get('[data-cy="submit-consent"]').click();
            cy.contains('Network error').should('be.visible');

            // Test session timeout
            cy.window().then((win) => {
                // Simulate session expiry
                win.localStorage.removeItem('auth_token');
            });
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.url().should('include', '/login');
        });
    });

    describe('Performance and Load Testing', () => {
        it('should handle performance under load', () => {
            const startTime = Date.now();

            // Perform multiple rapid operations
            for (let i = 0; i < 5; i++) {
                cy.visit(`${baseUrl}/services/building-consents`);
                cy.get('[data-cy="new-consent-button"]').click();
                cy.get('[data-cy="project-name"]').type(`Performance Test ${i}`);
                cy.get('[data-cy="project-type"]').select('renovation');
                cy.get('[data-cy="property-address"]').type(`Test Address ${i}`);
                cy.get('[data-cy="property-type"]').select('residential');
                cy.get('[data-cy="consent-type"]').select('full');
                cy.get('[data-cy="estimated-cost"]').type('50000');
                cy.get('[data-cy="submit-consent"]').click();
                cy.contains('Building consent application submitted successfully').should('be.visible');
            }

            const endTime = Date.now();
            const duration = endTime - startTime;

            // Should complete within reasonable time (adjust threshold as needed)
            expect(duration).to.be.lessThan(30000); // 30 seconds for 5 operations
        });
    });

    describe('Security Testing', () => {
        it('should handle security scenarios', () => {
            // Test XSS prevention
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.get('[data-cy="new-consent-button"]').click();
            cy.get('[data-cy="project-name"]').type('<script>alert("XSS")</script>');
            cy.get('[data-cy="submit-consent"]').click();
            cy.on('window:alert', () => {
                throw new Error('XSS vulnerability detected');
            });

            // Test SQL injection prevention
            cy.get('[data-cy="project-name"]').clear().type("'; DROP TABLE users; --");
            cy.get('[data-cy="submit-consent"]').click();
            cy.contains('Invalid input').should('be.visible');

            // Test CSRF protection
            cy.window().then((win) => {
                // Attempt to make request without CSRF token
                cy.request({
                    method: 'POST',
                    url: `${baseUrl}/api/building-consents`,
                    body: { test: 'data' },
                    failOnStatusCode: false
                }).then((response) => {
                    expect(response.status).to.be.oneOf([403, 419]); // CSRF error codes
                });
            });

            // Test rate limiting
            for (let i = 0; i < 10; i++) {
                cy.request({
                    method: 'GET',
                    url: `${baseUrl}/api/services`,
                    failOnStatusCode: false
                });
            }
            cy.request({
                method: 'GET',
                url: `${baseUrl}/api/services`,
                failOnStatusCode: false
            }).then((response) => {
                if (response.status === 429) {
                    cy.log('Rate limiting is working correctly');
                }
            });
        });
    });

    describe('Multi-language Support', () => {
        it('should support multiple languages', () => {
            // Test language switching
            cy.visit(`${baseUrl}/settings`);
            cy.get('[data-cy="language-selector"]').select('es'); // Spanish
            cy.contains('Configuración').should('be.visible');

            // Test that services are available in selected language
            cy.visit(`${baseUrl}/services`);
            cy.contains('Servicios Gubernamentales').should('be.visible');

            // Switch back to English
            cy.get('[data-cy="language-selector"]').select('en');
            cy.contains('Government Services').should('be.visible');
        });
    });

    describe('Offline Functionality', () => {
        it('should work offline when supported', () => {
            // Test service worker registration
            cy.window().then((win) => {
                expect(win.navigator.serviceWorker).to.exist;
            });

            // Test offline indicator
            cy.visit(`${baseUrl}/dashboard`);
            cy.get('body').should('not.have.class', 'offline');

            // Simulate going offline
            cy.window().then((win) => {
                win.dispatchEvent(new Event('offline'));
            });

            // Check offline indicator appears
            cy.get('body').should('have.class', 'offline');
            cy.contains('You are currently offline').should('be.visible');

            // Test offline form submission (should be queued)
            cy.visit(`${baseUrl}/services/building-consents`);
            cy.get('[data-cy="new-consent-button"]').click();
            cy.get('[data-cy="project-name"]').type('Offline Test Project');
            cy.get('[data-cy="submit-consent"]').click();
            cy.contains('Request queued for submission when online').should('be.visible');
        });
    });

    describe('Integration with External Systems', () => {
        it('should integrate with external systems', () => {
            // Test payment gateway integration
            cy.visit(`${baseUrl}/payments`);
            cy.get('[data-cy="add-payment-method"]').click();
            cy.get('[data-cy="payment-gateway"]').should('contain', 'Stripe').and('contain', 'PayPal');

            // Test document storage integration
            cy.visit(`${baseUrl}/documents`);
            cy.get('[data-cy="upload-document"]').selectFile('cypress/fixtures/test-document.pdf', { force: true });
            cy.get('[data-cy="upload-button"]').click();
            cy.contains('Document uploaded to cloud storage').should('be.visible');

            // Test email service integration
            cy.visit(`${baseUrl}/messages`);
            cy.get('[data-cy="compose-message"]').click();
            cy.get('[data-cy="send-message"]').click();
            cy.contains('Message sent via email service').should('be.visible');
        });
    });
});

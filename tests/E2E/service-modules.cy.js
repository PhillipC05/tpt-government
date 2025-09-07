/**
 * TPT Government Platform - Service Modules End-to-End Tests
 *
 * Cypress tests for complete user workflows across service modules
 */

describe('Government Service Modules E2E Tests', () => {
    beforeEach(() => {
        // Visit the application
        cy.visit('/')

        // Login as test user (assuming login functionality exists)
        cy.login('test@example.com', 'password')
    })

    describe('Building Consents Module', () => {
        it('should complete full building consent application workflow', () => {
            // Navigate to building consents
            cy.visit('/services/building-consents')
            cy.contains('Building Consents').should('be.visible')

            // Start new application
            cy.contains('New Application').click()

            // Fill out application form
            cy.get('[data-cy="property-address"]').type('123 Test Street, Test City, 12345')
            cy.get('[data-cy="application-type"]').select('New Construction')
            cy.get('[data-cy="work-description"]').type('Construct a new two-story residential building with garage')
            cy.get('[data-cy="estimated-cost"]').type('250000')
            cy.get('[data-cy="consent-type"]').select('building_consent')

            // Upload documents
            cy.get('[data-cy="document-upload"]').selectFile([
                'cypress/fixtures/test-plans.pdf',
                'cypress/fixtures/test-drawings.pdf'
            ])

            // Submit application
            cy.contains('Submit Application').click()

            // Verify application was created
            cy.contains('Application submitted successfully').should('be.visible')
            cy.contains('BC2025').should('be.visible') // Application number

            // Check application status
            cy.contains('Status: Draft').should('be.visible')

            // Submit for processing
            cy.contains('Submit for Review').click()
            cy.contains('Application submitted for review').should('be.visible')

            // Verify status changed
            cy.contains('Status: Submitted').should('be.visible')
        })

        it('should handle document uploads correctly', () => {
            cy.visit('/services/building-consents')

            // Start new application
            cy.contains('New Application').click()

            // Upload various document types
            cy.get('[data-cy="document-upload"]').selectFile([
                'cypress/fixtures/site-plan.pdf',
                'cypress/fixtures/floor-plans.pdf',
                'cypress/fixtures/elevation-drawings.pdf',
                'cypress/fixtures/specification.pdf'
            ])

            // Verify documents are listed
            cy.contains('site-plan.pdf').should('be.visible')
            cy.contains('floor-plans.pdf').should('be.visible')
            cy.contains('elevation-drawings.pdf').should('be.visible')
            cy.contains('specification.pdf').should('be.visible')

            // Verify file size and type validation
            cy.get('[data-cy="file-size-warning"]').should('not.exist')
            cy.get('[data-cy="file-type-error"]').should('not.exist')
        })

        it('should validate required fields', () => {
            cy.visit('/services/building-consents')
            cy.contains('New Application').click()

            // Try to submit without required fields
            cy.contains('Submit Application').click()

            // Check validation errors
            cy.contains('Property address is required').should('be.visible')
            cy.contains('Application type is required').should('be.visible')
            cy.contains('Work description is required').should('be.visible')
            cy.contains('Estimated cost is required').should('be.visible')
            cy.contains('Consent type is required').should('be.visible')
        })

        it('should display application history and status', () => {
            cy.visit('/services/building-consents')

            // Click on existing application
            cy.contains('BC202500001').click()

            // Verify application details are displayed
            cy.contains('Application Details').should('be.visible')
            cy.contains('Property Address').should('be.visible')
            cy.contains('Application Status').should('be.visible')

            // Check status timeline
            cy.contains('Application Timeline').should('be.visible')
            cy.contains('Draft').should('be.visible')
            cy.contains('Submitted').should('be.visible')

            // Verify documents section
            cy.contains('Supporting Documents').should('be.visible')
        })
    })

    describe('Traffic & Parking Module', () => {
        it('should complete traffic ticket payment workflow', () => {
            // Navigate to traffic tickets
            cy.visit('/services/traffic-tickets')
            cy.contains('Traffic Tickets').should('be.visible')

            // Search for ticket
            cy.get('[data-cy="ticket-search"]').type('TT20250100001')
            cy.contains('Search').click()

            // Verify ticket details
            cy.contains('TT20250100001').should('be.visible')
            cy.contains('Speeding 20km/h over limit').should('be.visible')
            cy.contains('$150.00').should('be.visible')
            cy.contains('Status: Unpaid').should('be.visible')

            // Click pay ticket
            cy.contains('Pay Ticket').click()

            // Fill payment details
            cy.get('[data-cy="payment-method"]').select('credit_card')
            cy.get('[data-cy="card-number"]').type('4111111111111111')
            cy.get('[data-cy="expiry-date"]').type('1225')
            cy.get('[data-cy="cvv"]').type('123')
            cy.get('[data-cy="cardholder-name"]').type('John Doe')

            // Submit payment
            cy.contains('Pay $150.00').click()

            // Verify payment success
            cy.contains('Payment successful').should('be.visible')
            cy.contains('Receipt #').should('be.visible')

            // Verify ticket status updated
            cy.contains('Status: Paid').should('be.visible')
        })

        it('should handle ticket appeal process', () => {
            cy.visit('/services/traffic-tickets')

            // Find unpaid ticket
            cy.contains('TT20250100002').click()

            // Click appeal ticket
            cy.contains('Appeal Ticket').click()

            // Fill appeal form
            cy.get('[data-cy="appeal-reason"]').select('dispute_violation')
            cy.get('[data-cy="appeal-details"]').type('I was not exceeding the speed limit. The radar may have been miscalibrated.')
            cy.get('[data-cy="contact-phone"]').type('555-0123')
            cy.get('[data-cy="contact-email"]').type('driver@example.com')

            // Upload evidence
            cy.get('[data-cy="evidence-upload"]').selectFile('cypress/fixtures/appeal-evidence.pdf')

            // Submit appeal
            cy.contains('Submit Appeal').click()

            // Verify appeal submission
            cy.contains('Appeal submitted successfully').should('be.visible')
            cy.contains('Appeal Status: Under Review').should('be.visible')
        })

        it('should display parking violations correctly', () => {
            cy.visit('/services/parking-violations')
            cy.contains('Parking Violations').should('be.visible')

            // Verify violation list
            cy.get('[data-cy="violation-list"]').should('be.visible')
            cy.contains('PV20250100001').should('be.visible')
            cy.contains('No Parking Zone').should('be.visible')
            cy.contains('$50.00').should('be.visible')

            // Click on violation for details
            cy.contains('PV20250100001').click()

            // Verify violation details
            cy.contains('Violation Details').should('be.visible')
            cy.contains('Location: Main Street').should('be.visible')
            cy.contains('Date:').should('be.visible')
            cy.contains('Time:').should('be.visible')
        })

        it('should handle bulk ticket payments', () => {
            cy.visit('/services/traffic-tickets')

            // Select multiple tickets
            cy.get('[data-cy="ticket-checkbox"]').first().check()
            cy.get('[data-cy="ticket-checkbox"]').eq(1).check()

            // Click bulk pay
            cy.contains('Pay Selected ($300.00)').click()

            // Verify bulk payment interface
            cy.contains('Pay Multiple Tickets').should('be.visible')
            cy.contains('Total Amount: $300.00').should('be.visible')
            cy.contains('2 tickets selected').should('be.visible')

            // Complete payment
            cy.get('[data-cy="payment-method"]').select('bank_transfer')
            cy.contains('Complete Payment').click()

            // Verify bulk payment success
            cy.contains('All payments processed successfully').should('be.visible')
        })
    })

    describe('Business Licenses Module', () => {
        it('should complete business license application workflow', () => {
            cy.visit('/services/business-licenses')
            cy.contains('Business Licenses').should('be.visible')

            // Start new application
            cy.contains('Apply for License').click()

            // Fill business details
            cy.get('[data-cy="business-name"]').type('Test Cafe & Restaurant')
            cy.get('[data-cy="business-type"]').select('Food Service')
            cy.get('[data-cy="business-category"]').select('Restaurant')
            cy.get('[data-cy="owner-name"]').type('Sarah Johnson')
            cy.get('[data-cy="owner-address"]').type('456 Business Ave, Business City, 67890')
            cy.get('[data-cy="owner-phone"]').type('555-0199')
            cy.get('[data-cy="owner-email"]').type('sarah@testcafe.com')
            cy.get('[data-cy="business-address"]').type('456 Business Ave, Business City, 67890')
            cy.get('[data-cy="license-type"]').select('general_business')

            // Business details
            cy.get('[data-cy="abn"]').type('12345678901')
            cy.get('[data-cy="gst-registered"]').check()
            cy.get('[data-cy="employee-count"]').type('15')
            cy.get('[data-cy="annual-turnover"]').type('500000')

            // Upload required documents
            cy.get('[data-cy="business-plan-upload"]').selectFile('cypress/fixtures/business-plan.pdf')
            cy.get('[data-cy="financial-statements-upload"]').selectFile('cypress/fixtures/financial-statements.pdf')
            cy.get('[data-cy="insurance-upload"]').selectFile('cypress/fixtures/insurance-certificate.pdf')

            // Submit application
            cy.contains('Submit Application').click()

            // Verify submission
            cy.contains('Application submitted successfully').should('be.visible')
            cy.contains('BLA2025').should('be.visible') // Application number

            // Check application status
            cy.contains('Status: Draft').should('be.visible')
        })

        it('should handle license renewal process', () => {
            cy.visit('/services/business-licenses')

            // Find license due for renewal
            cy.contains('Renewal Due').click()

            // Verify renewal interface
            cy.contains('License Renewal').should('be.visible')
            cy.contains('Current Expiry:').should('be.visible')
            cy.contains('Renewal Fee: $500.00').should('be.visible')

            // Confirm renewal
            cy.contains('Renew License').click()

            // Verify renewal success
            cy.contains('License renewed successfully').should('be.visible')
            cy.contains('New Expiry Date:').should('be.visible')
        })

        it('should display license compliance status', () => {
            cy.visit('/services/business-licenses')

            // Click on active license
            cy.contains('BL202500001').click()

            // Verify compliance dashboard
            cy.contains('Compliance Status').should('be.visible')
            cy.contains('Last Inspection:').should('be.visible')
            cy.contains('Next Inspection:').should('be.visible')
            cy.contains('Risk Rating:').should('be.visible')

            // Check inspection history
            cy.contains('Inspection History').should('be.visible')
            cy.get('[data-cy="inspection-list"]').should('be.visible')
        })

        it('should validate business license requirements', () => {
            cy.visit('/services/business-licenses')
            cy.contains('Apply for License').click()

            // Try to submit without required fields
            cy.contains('Submit Application').click()

            // Check validation
            cy.contains('Business name is required').should('be.visible')
            cy.contains('Business type is required').should('be.visible')
            cy.contains('Owner name is required').should('be.visible')
            cy.contains('Business address is required').should('be.visible')
            cy.contains('License type is required').should('be.visible')
        })
    })

    describe('Waste Management Module', () => {
        it('should submit waste service request', () => {
            cy.visit('/services/waste-management')
            cy.contains('Waste Management').should('be.visible')

            // Click request service
            cy.contains('Request Service').click()

            // Fill service request form
            cy.get('[data-cy="request-type"]').select('missed_collection')
            cy.get('[data-cy="requester-name"]').type('Mike Thompson')
            cy.get('[data-cy="requester-address"]').type('789 Residential St, Residential City, 54321')
            cy.get('[data-cy="requester-phone"]').type('555-0155')
            cy.get('[data-cy="requester-email"]').type('mike@example.com')
            cy.get('[data-cy="request-description"]').type('Regular waste collection was missed on Tuesday. Bin was full and overflowing.')
            cy.get('[data-cy="priority"]').select('high')

            // Add photos if available
            cy.get('[data-cy="photo-upload"]').selectFile([
                'cypress/fixtures/waste-bin-photo1.jpg',
                'cypress/fixtures/waste-bin-photo2.jpg'
            ])

            // Submit request
            cy.contains('Submit Request').click()

            // Verify submission
            cy.contains('Service request submitted successfully').should('be.visible')
            cy.contains('WSR2025').should('be.visible') // Request number
            cy.contains('Estimated response time: 24-48 hours').should('be.visible')
        })

        it('should display collection schedules', () => {
            cy.visit('/services/waste-management')

            // Check collection schedule section
            cy.contains('Collection Schedules').should('be.visible')

            // Verify schedule display
            cy.contains('Monday').should('be.visible')
            cy.contains('Wednesday').should('be.visible')
            cy.contains('Friday').should('be.visible')

            // Check collection zones
            cy.contains('Your Zone:').should('be.visible')
            cy.contains('Collection Time:').should('be.visible')
        })

        it('should show recycling information', () => {
            cy.visit('/services/waste-management')

            // Navigate to recycling section
            cy.contains('Recycling').click()

            // Verify recycling programs
            cy.contains('Recycling Programs').should('be.visible')
            cy.get('[data-cy="recycling-program-list"]').should('be.visible')

            // Check specific programs
            cy.contains('Paper Recycling').should('be.visible')
            cy.contains('Plastic Recycling').should('be.visible')
            cy.contains('Garden Waste').should('be.visible')
        })

        it('should handle billing account lookup', () => {
            cy.visit('/services/waste-management')

            // Navigate to billing section
            cy.contains('Billing').click()

            // Enter account number
            cy.get('[data-cy="account-lookup"]').type('WASTE001234')
            cy.contains('Look Up Account').click()

            // Verify account details
            cy.contains('Account Details').should('be.visible')
            cy.contains('Balance:').should('be.visible')
            cy.contains('Next Billing Date:').should('be.visible')
            cy.contains('Service Type:').should('be.visible')
        })

        it('should validate service request fields', () => {
            cy.visit('/services/waste-management')
            cy.contains('Request Service').click()

            // Try to submit without required fields
            cy.contains('Submit Request').click()

            // Check validation errors
            cy.contains('Request type is required').should('be.visible')
            cy.contains('Your name is required').should('be.visible')
            cy.contains('Property address is required').should('be.visible')
            cy.contains('Description is required').should('be.visible')
        })
    })

    describe('Cross-Module Integration', () => {
        it('should handle property-related services consistently', () => {
            const propertyAddress = '123 Integration Street, Test City, 12345'

            // Create building consent for property
            cy.visit('/services/building-consents')
            cy.contains('New Application').click()
            cy.get('[data-cy="property-address"]').type(propertyAddress)
            cy.get('[data-cy="application-type"]').select('Renovation')
            cy.get('[data-cy="work-description"]').type('Kitchen and bathroom renovation')
            cy.get('[data-cy="estimated-cost"]').type('75000')
            cy.get('[data-cy="consent-type"]').select('building_consent')
            cy.contains('Submit Application').click()

            // Create business license for same property
            cy.visit('/services/business-licenses')
            cy.contains('Apply for License').click()
            cy.get('[data-cy="business-name"]').type('Integration Services Ltd')
            cy.get('[data-cy="business-type"]').select('Consulting')
            cy.get('[data-cy="owner-name"]').type('Integration Owner')
            cy.get('[data-cy="owner-address"]').type(propertyAddress)
            cy.get('[data-cy="business-address"]').type(propertyAddress)
            cy.get('[data-cy="license-type"]').select('general_business')
            cy.contains('Submit Application').click()

            // Create waste service request for same property
            cy.visit('/services/waste-management')
            cy.contains('Request Service').click()
            cy.get('[data-cy="request-type"]').select('new_service')
            cy.get('[data-cy="requester-name"]').type('Integration Owner')
            cy.get('[data-cy="requester-address"]').type(propertyAddress)
            cy.get('[data-cy="request-description"]').type('New business setup requiring waste collection service')
            cy.contains('Submit Request').click()

            // Verify all services show same property address
            cy.visit('/dashboard')
            cy.contains('My Services').should('be.visible')
            cy.contains(propertyAddress).should('be.visible')
        })

        it('should maintain consistent user experience across modules', () => {
            // Test common UI patterns across all modules
            const modules = [
                '/services/building-consents',
                '/services/traffic-tickets',
                '/services/business-licenses',
                '/services/waste-management'
            ]

            modules.forEach(moduleUrl => {
                cy.visit(moduleUrl)

                // Check common UI elements
                cy.get('[data-cy="page-title"]').should('be.visible')
                cy.get('[data-cy="user-menu"]').should('be.visible')
                cy.get('[data-cy="navigation"]').should('be.visible')

                // Check responsive design
                cy.viewport('iphone-6')
                cy.get('[data-cy="mobile-menu"]').should('be.visible')
                cy.viewport('macbook-15')
                cy.get('[data-cy="desktop-nav"]').should('be.visible')
            })
        })

        it('should handle notifications consistently', () => {
            // Submit applications across modules
            cy.visit('/services/building-consents')
            cy.contains('New Application').click()
            cy.get('[data-cy="property-address"]').type('123 Notification St, Test City, 12345')
            cy.get('[data-cy="application-type"]').select('Extension')
            cy.get('[data-cy="work-description"]').type('Home extension')
            cy.get('[data-cy="estimated-cost"]').type('100000')
            cy.get('[data-cy="consent-type"]').select('building_consent')
            cy.contains('Submit Application').click()

            // Check notifications
            cy.get('[data-cy="notification-bell"]').click()
            cy.contains('Building consent application submitted').should('be.visible')
            cy.contains('Application #BC2025').should('be.visible')
        })
    })

    describe('Performance and Accessibility', () => {
        it('should load service pages within acceptable time', () => {
            const pages = [
                '/services/building-consents',
                '/services/traffic-tickets',
                '/services/business-licenses',
                '/services/waste-management'
            ]

            pages.forEach(page => {
                cy.visit(page, { timeout: 10000 })
                cy.contains(/Building|Traffic|Business|Waste/i).should('be.visible')
            })
        })

        it('should be keyboard navigable', () => {
            cy.visit('/services/building-consents')

            // Test tab navigation
            cy.get('body').tab()
            cy.focused().should('have.attr', 'data-cy', 'new-application-btn')

            cy.focused().type('{enter}')
            cy.get('[data-cy="application-form"]').should('be.visible')

            // Test form navigation
            cy.focused().tab()
            cy.focused().should('have.attr', 'data-cy', 'property-address')
        })

        it('should have proper ARIA labels and roles', () => {
            cy.visit('/services/traffic-tickets')

            // Check ARIA labels
            cy.get('[aria-label]').should('have.length.greaterThan', 5)
            cy.get('[role]').should('have.length.greaterThan', 3)

            // Check form labels
            cy.get('label').should('have.length.greaterThan', 10)
            cy.get('input[aria-labelledby]').should('exist')
        })

        it('should be mobile responsive', () => {
            cy.visit('/services/business-licenses')

            // Test mobile viewport
            cy.viewport('iphone-6')
            cy.get('[data-cy="mobile-header"]').should('be.visible')
            cy.get('[data-cy="mobile-menu-toggle"]').should('be.visible')

            // Test tablet viewport
            cy.viewport('ipad-2')
            cy.get('[data-cy="tablet-layout"]').should('be.visible')

            // Test desktop viewport
            cy.viewport('macbook-15')
            cy.get('[data-cy="desktop-layout"]').should('be.visible')
        })
    })

    describe('Error Handling and Edge Cases', () => {
        it('should handle network errors gracefully', () => {
            // Simulate network failure
            cy.intercept('POST', '/api/building-consents', { forceNetworkError: true })

            cy.visit('/services/building-consents')
            cy.contains('New Application').click()
            cy.get('[data-cy="property-address"]').type('123 Error St, Test City, 12345')
            cy.get('[data-cy="application-type"]').select('New Construction')
            cy.get('[data-cy="work-description"]').type('Test application')
            cy.get('[data-cy="estimated-cost"]').type('50000')
            cy.get('[data-cy="consent-type"]').select('building_consent')
            cy.contains('Submit Application').click()

            // Verify error handling
            cy.contains('Network error').should('be.visible')
            cy.contains('Please try again').should('be.visible')
        })

        it('should handle session timeout', () => {
            // Simulate session timeout
            cy.window().then((win) => {
                win.localStorage.setItem('session_expired', 'true')
            })

            cy.visit('/services/traffic-tickets')

            // Should redirect to login
            cy.url().should('include', '/login')
            cy.contains('Session expired').should('be.visible')
        })

        it('should handle invalid file uploads', () => {
            cy.visit('/services/building-consents')
            cy.contains('New Application').click()

            // Try to upload invalid file type
            cy.get('[data-cy="document-upload"]').selectFile('cypress/fixtures/invalid-file.exe')

            // Verify error message
            cy.contains('Invalid file type').should('be.visible')
            cy.contains('Allowed formats: PDF, DOC, DOCX, JPG, PNG').should('be.visible')
        })

        it('should handle large file uploads', () => {
            cy.visit('/services/business-licenses')
            cy.contains('Apply for License').click()

            // Try to upload very large file
            cy.get('[data-cy="document-upload"]').selectFile('cypress/fixtures/large-file.pdf')

            // Verify file size warning
            cy.contains('File size exceeds limit').should('be.visible')
            cy.contains('Maximum file size: 10MB').should('be.visible')
        })
    })
})

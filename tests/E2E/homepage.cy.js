/**
 * E2E tests for homepage functionality
 *
 * @package TPT
 * @subpackage Tests
 */

describe('Homepage', () => {
  beforeEach(() => {
    // Mock service worker for PWA testing
    cy.mockServiceWorker();

    // Visit homepage
    cy.visit('/');
  });

  it('should load the homepage successfully', () => {
    // Check that the page loads
    cy.url().should('include', '/');

    // Check for main content
    cy.get('[data-cy="main-content"]').should('be.visible');

    // Check for navigation
    cy.get('[data-cy="navigation"]').should('be.visible');

    // Check for footer
    cy.get('[data-cy="footer"]').should('be.visible');
  });

  it('should display the application title', () => {
    cy.get('[data-cy="app-title"]').should('contain', 'TPT Government Platform');
  });

  it('should have working navigation links', () => {
    // Check navigation links exist
    cy.get('[data-cy="nav-home"]').should('be.visible');
    cy.get('[data-cy="nav-services"]').should('be.visible');
    cy.get('[data-cy="nav-about"]').should('be.visible');
    cy.get('[data-cy="nav-contact"]').should('be.visible');
  });

  it('should display government services section', () => {
    cy.get('[data-cy="services-section"]').should('be.visible');

    // Check for service cards
    cy.get('[data-cy="service-card"]').should('have.length.greaterThan', 0);

    // Check service card content
    cy.get('[data-cy="service-card"]').first().within(() => {
      cy.get('[data-cy="service-title"]').should('be.visible');
      cy.get('[data-cy="service-description"]').should('be.visible');
    });
  });

  it('should have responsive design', () => {
    // Test mobile viewport
    cy.viewport('iphone-6');
    cy.get('[data-cy="mobile-menu"]').should('be.visible');

    // Test tablet viewport
    cy.viewport('ipad-2');
    cy.get('[data-cy="navigation"]').should('be.visible');

    // Test desktop viewport
    cy.viewport(1280, 720);
    cy.get('[data-cy="navigation"]').should('be.visible');
  });

  it('should support theme switching', () => {
    // Check default theme
    cy.get('body').should('have.class', 'theme-light');

    // Switch to dark theme
    cy.get('[data-cy="theme-toggle"]').click();
    cy.get('body').should('have.class', 'theme-dark');

    // Switch back to light theme
    cy.get('[data-cy="theme-toggle"]').click();
    cy.get('body').should('have.class', 'theme-light');
  });

  it('should have working search functionality', () => {
    cy.get('[data-cy="search-input"]').type('permit');
    cy.get('[data-cy="search-button"]').click();

    // Check for search results
    cy.get('[data-cy="search-results"]').should('be.visible');
    cy.get('[data-cy="search-result"]').should('contain', 'permit');
  });

  it('should display notifications correctly', () => {
    // Trigger a notification (this would be done via API call in real scenario)
    cy.window().then((win) => {
      win.dispatchEvent(new CustomEvent('notification', {
        detail: { type: 'success', message: 'Test notification' }
      }));
    });

    // Check notification appears
    cy.get('[data-cy="notification-success"]').should('be.visible');
    cy.get('[data-cy="notification-success"]').should('contain', 'Test notification');

    // Dismiss notification
    cy.get('[data-cy="notification-close"]').click();
    cy.get('[data-cy="notification-success"]').should('not.exist');
  });

  it('should handle offline mode', () => {
    // Test offline functionality
    cy.testOfflineMode(() => {
      // Check offline indicator
      cy.get('[data-cy="offline-indicator"]').should('be.visible');

      // Try to perform an action that requires network
      cy.get('[data-cy="network-action"]').click();

      // Should show offline message
      cy.get('[data-cy="offline-message"]').should('be.visible');
    });

    // Back online
    cy.get('[data-cy="offline-indicator"]').should('not.exist');
  });

  it('should have proper accessibility attributes', () => {
    // Check for proper heading structure
    cy.get('h1').should('have.length', 1);

    // Check for alt text on images
    cy.get('img').each(($img) => {
      cy.wrap($img).should('have.attr', 'alt');
    });

    // Check for proper form labels
    cy.get('input, select, textarea').each(($input) => {
      const id = $input.attr('id');
      if (id) {
        cy.get(`label[for="${id}"]`).should('exist');
      }
    });

    // Check for proper ARIA labels
    cy.get('[data-cy="search-input"]').should('have.attr', 'aria-label');
  });

  it('should support keyboard navigation', () => {
    // Test tab navigation through main elements
    cy.get('[data-cy="nav-home"]').focus();

    cy.realPress('Tab');
    cy.get('[data-cy="nav-services"]').should('be.focused');

    cy.realPress('Tab');
    cy.get('[data-cy="nav-about"]').should('be.focused');

    cy.realPress('Tab');
    cy.get('[data-cy="search-input"]').should('be.focused');
  });

  it('should load performance optimized', () => {
    // Check for lazy loading images
    cy.get('img[loading="lazy"]').should('exist');

    // Check for optimized assets
    cy.window().then((win) => {
      const resources = win.performance.getEntriesByType('resource');
      const largeResources = resources.filter(resource =>
        resource.transferSize > 100000 // 100KB
      );

      // Should have minimal large resources
      expect(largeResources.length).to.be.lessThan(3);
    });
  });

  it('should handle browser back/forward navigation', () => {
    // Navigate to services page
    cy.get('[data-cy="nav-services"]').click();
    cy.url().should('include', '/services');

    // Go back
    cy.go('back');
    cy.url().should('include', '/');

    // Go forward
    cy.go('forward');
    cy.url().should('include', '/services');
  });

  it('should display loading states properly', () => {
    // Trigger an action that shows loading
    cy.get('[data-cy="load-more"]').click();

    // Check loading state
    cy.get('[data-cy="loading-spinner"]').should('be.visible');
    cy.get('[data-cy="content"]').should('not.be.visible');

    // Wait for loading to complete
    cy.get('[data-cy="loading-spinner"]').should('not.exist');
    cy.get('[data-cy="content"]').should('be.visible');
  });

  it('should handle error states gracefully', () => {
    // Mock API error
    cy.mockApiResponse('GET', '/api/services', { error: 'Server error' }, 500);

    // Trigger action that calls the API
    cy.get('[data-cy="refresh-services"]').click();

    // Check error handling
    cy.get('[data-cy="error-message"]').should('be.visible');
    cy.get('[data-cy="error-message"]').should('contain', 'Server error');
  });
});

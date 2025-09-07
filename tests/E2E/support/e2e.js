// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands';

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Global test configuration
beforeEach(() => {
  // Clear all cookies and local storage before each test
  cy.clearCookies();
  cy.clearLocalStorage();

  // Set viewport for consistent testing
  cy.viewport(1280, 720);

  // Disable service worker for testing
  if (window.navigator && navigator.serviceWorker) {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
      for (let registration of registrations) {
        registration.unregister();
      }
    });
  }
});

// Global error handling
Cypress.on('uncaught:exception', (err, runnable) => {
  // returning false here prevents Cypress from failing the test
  // for uncaught exceptions that we expect
  if (err.message.includes('Service worker')) {
    return false;
  }
  if (err.message.includes('Network Error')) {
    return false;
  }
  if (err.message.includes('Loading chunk')) {
    return false;
  }
  // Let other exceptions fail the test
  return true;
});

// Add custom commands for common actions
Cypress.Commands.add('login', (email, password) => {
  cy.session([email, password], () => {
    cy.visit('/login');
    cy.get('[data-cy="email-input"]').type(email);
    cy.get('[data-cy="password-input"]').type(password);
    cy.get('[data-cy="login-button"]').click();
    cy.url().should('not.include', '/login');
  });
});

Cypress.Commands.add('logout', () => {
  cy.get('[data-cy="user-menu"]').click();
  cy.get('[data-cy="logout-button"]').click();
  cy.url().should('include', '/login');
});

Cypress.Commands.add('waitForLoading', () => {
  cy.get('[data-cy="loading-spinner"]', { timeout: 10000 }).should('not.exist');
});

Cypress.Commands.add('checkNotification', (type, message) => {
  cy.get(`[data-cy="notification-${type}"]`)
    .should('be.visible')
    .and('contain', message);
});

Cypress.Commands.add('dismissNotification', () => {
  cy.get('[data-cy="notification-close"]').click();
  cy.get('[data-cy="notification"]').should('not.exist');
});

// Mock API responses for testing
Cypress.Commands.add('mockApiResponse', (method, url, response, status = 200) => {
  cy.intercept(method, url, {
    statusCode: status,
    body: response,
  }).as(`${method.toLowerCase()}${url.replace(/\//g, '-')}`);
});

// Mock service worker for PWA testing
Cypress.Commands.add('mockServiceWorker', () => {
  cy.window().then((win) => {
    // Mock service worker registration
    Object.defineProperty(win.navigator, 'serviceWorker', {
      value: {
        register: cy.stub().resolves({
          scope: '/',
          update: cy.stub(),
          unregister: cy.stub(),
        }),
        ready: Promise.resolve({
          active: { state: 'activated' },
          waiting: null,
          installing: null,
        }),
        getRegistrations: cy.stub().resolves([]),
        getRegistration: cy.stub().resolves(null),
      },
      writable: true,
    });
  });
});

// Accessibility testing helpers
Cypress.Commands.add('checkA11y', (context, options) => {
  cy.injectAxe();
  cy.checkA11y(context, options);
});

// Performance testing helpers
Cypress.Commands.add('measurePerformance', (actionName) => {
  const startTime = performance.now();

  cy.wrap(null).then(() => {
    return new Cypress.Promise((resolve) => {
      cy.window().then((win) => {
        const observer = new win.PerformanceObserver((list) => {
          const entries = list.getEntries();
          resolve(entries);
          observer.disconnect();
        });
        observer.observe({ entryTypes: ['measure'] });

        // Execute the action
        cy.then(() => {
          win.performance.mark(`${actionName}-start`);
        });
      });
    });
  }).then((entries) => {
    const endTime = performance.now();
    const duration = endTime - startTime;

    cy.task('log', `Performance: ${actionName} took ${duration.toFixed(2)}ms`);

    // Log performance metrics
    entries.forEach((entry) => {
      cy.task('log', `Performance Entry: ${entry.name} - ${entry.duration}ms`);
    });
  });
});

// Visual regression testing helpers
Cypress.Commands.add('takeSnapshot', (name) => {
  cy.screenshot(name, { capture: 'viewport' });
});

// Database seeding for E2E tests
Cypress.Commands.add('seedDatabase', (fixtures) => {
  cy.request('POST', '/api/test/seed', { fixtures });
});

// Clean up after tests
Cypress.Commands.add('cleanupDatabase', () => {
  cy.request('POST', '/api/test/cleanup');
});

// API testing helpers
Cypress.Commands.add('apiRequest', (method, endpoint, data = null) => {
  const config = {
    method,
    url: `${Cypress.env('API_URL')}${endpoint}`,
    failOnStatusCode: false,
  };

  if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
    config.body = data;
  }

  return cy.request(config);
});

// Form filling helpers
Cypress.Commands.add('fillForm', (formData) => {
  Object.keys(formData).forEach((field) => {
    const selector = `[data-cy="${field}-input"], [name="${field}"]`;
    cy.get(selector).type(formData[field]);
  });
});

// Wait for network idle
Cypress.Commands.add('waitForNetworkIdle', (timeout = 10000) => {
  cy.window().then((win) => {
    return new Cypress.Promise((resolve) => {
      let lastRequestTime = Date.now();

      const checkIdle = () => {
        if (Date.now() - lastRequestTime > 500) {
          resolve();
        } else {
          setTimeout(checkIdle, 100);
        }
      };

      // Override XMLHttpRequest to track requests
      const originalOpen = win.XMLHttpRequest.prototype.open;
      win.XMLHttpRequest.prototype.open = function() {
        lastRequestTime = Date.now();
        return originalOpen.apply(this, arguments);
      };

      // Override fetch to track requests
      const originalFetch = win.fetch;
      win.fetch = function() {
        lastRequestTime = Date.now();
        return originalFetch.apply(this, arguments);
      };

      setTimeout(() => resolve(), timeout);
      checkIdle();
    });
  });
});

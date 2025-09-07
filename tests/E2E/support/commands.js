// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

// Custom command to check if element is visible in viewport
Cypress.Commands.add('isInViewport', { prevSubject: 'element' }, (subject) => {
  const bottom = Cypress.$(cy.state('window')).height();
  const rect = subject[0].getBoundingClientRect();

  expect(rect.top).to.be.greaterThan(0);
  expect(rect.bottom).to.be.lessThan(bottom);
  expect(rect.left).to.be.greaterThan(0);
});

// Custom command to check if element is focused
Cypress.Commands.add('isFocused', { prevSubject: 'element' }, (subject) => {
  expect(subject[0]).to.eq(document.activeElement);
});

// Custom command to check contrast ratio
Cypress.Commands.add('checkContrast', { prevSubject: 'element' }, (subject) => {
  cy.window().then((win) => {
    const element = subject[0];
    const style = win.getComputedStyle(element);
    const backgroundColor = style.backgroundColor;
    const color = style.color;

    // This is a simplified contrast check
    // In a real implementation, you'd use a proper color contrast library
    cy.task('log', `Element contrast - BG: ${backgroundColor}, Text: ${color}`);
  });
});

// Custom command to test keyboard navigation
Cypress.Commands.add('testKeyboardNavigation', (startSelector, expectedPath) => {
  cy.get(startSelector).focus();

  expectedPath.forEach((selector) => {
    cy.realPress('Tab');
    cy.get(selector).should('be.focused');
  });
});

// Custom command to test form submission with validation
Cypress.Commands.add('testFormValidation', (formSelector, invalidData, validData) => {
  // Test with invalid data
  cy.fillForm(invalidData);
  cy.get(`${formSelector} [type="submit"]`).click();

  // Should show validation errors
  cy.get('[data-cy="error-message"]').should('be.visible');

  // Test with valid data
  cy.fillForm(validData);
  cy.get(`${formSelector} [type="submit"]`).click();

  // Should submit successfully
  cy.get('[data-cy="success-message"]').should('be.visible');
});

// Custom command to test responsive design
Cypress.Commands.add('testResponsive', (breakpoints) => {
  breakpoints.forEach(({ width, height, tests }) => {
    cy.viewport(width, height);
    cy.task('log', `Testing viewport: ${width}x${height}`);

    tests.forEach((test) => {
      test();
    });
  });
});

// Custom command to test PWA installation
Cypress.Commands.add('testPWAInstall', () => {
  cy.window().then((win) => {
    // Mock the beforeinstallprompt event
    const installPromptEvent = new Event('beforeinstallprompt');
    installPromptEvent.prompt = cy.stub().resolves({ outcome: 'accepted' });
    installPromptEvent.userChoice = Promise.resolve({ outcome: 'accepted' });

    win.dispatchEvent(installPromptEvent);

    // Check if install button is shown
    cy.get('[data-cy="install-button"]').should('be.visible').click();

    // Verify the prompt was called
    expect(installPromptEvent.prompt).to.have.been.called;
  });
});

// Custom command to test offline functionality
Cypress.Commands.add('testOfflineMode', (callback) => {
  // Go offline
  cy.window().then((win) => {
    cy.stub(win.navigator, 'onLine').value(false);
    win.dispatchEvent(new Event('offline'));
  });

  // Run test callback
  callback();

  // Go back online
  cy.window().then((win) => {
    cy.stub(win.navigator, 'onLine').value(true);
    win.dispatchEvent(new Event('online'));
  });
});

// Custom command to test local storage
Cypress.Commands.add('testLocalStorage', (key, expectedValue) => {
  cy.window().then((win) => {
    const value = win.localStorage.getItem(key);
    expect(value).to.equal(expectedValue);
  });
});

// Custom command to test session storage
Cypress.Commands.add('testSessionStorage', (key, expectedValue) => {
  cy.window().then((win) => {
    const value = win.sessionStorage.getItem(key);
    expect(value).to.equal(expectedValue);
  });
});

// Custom command to test API error handling
Cypress.Commands.add('testApiError', (endpoint, errorResponse, expectedErrorMessage) => {
  cy.mockApiResponse('GET', endpoint, errorResponse, 500);

  cy.visit(endpoint);
  cy.get('[data-cy="error-message"]')
    .should('be.visible')
    .and('contain', expectedErrorMessage);
});

// Custom command to test loading states
Cypress.Commands.add('testLoadingState', (triggerAction, loadingSelector, contentSelector) => {
  // Trigger the action that shows loading
  triggerAction();

  // Check loading state
  cy.get(loadingSelector).should('be.visible');
  cy.get(contentSelector).should('not.be.visible');

  // Wait for loading to complete
  cy.get(loadingSelector).should('not.exist');
  cy.get(contentSelector).should('be.visible');
});

// Custom command to test search functionality
Cypress.Commands.add('testSearch', (searchInput, searchTerm, expectedResults) => {
  cy.get(searchInput).type(searchTerm);

  // Wait for search results
  cy.waitForNetworkIdle();

  // Check results
  expectedResults.forEach((result) => {
    cy.get('[data-cy="search-result"]').should('contain', result);
  });
});

// Custom command to test pagination
Cypress.Commands.add('testPagination', (totalPages, itemsPerPage) => {
  // Test first page
  cy.get('[data-cy="page-1"]').should('have.class', 'active');
  cy.get('[data-cy="item"]').should('have.length', itemsPerPage);

  // Test navigation to next page
  cy.get('[data-cy="next-page"]').click();
  cy.get('[data-cy="page-2"]').should('have.class', 'active');

  // Test navigation to last page
  cy.get('[data-cy="last-page"]').click();
  cy.get(`[data-cy="page-${totalPages}"]`).should('have.class', 'active');

  // Test previous page navigation
  cy.get('[data-cy="prev-page"]').click();
  cy.get(`[data-cy="page-${totalPages - 1}"]`).should('have.class', 'active');
});

// Custom command to test modal dialogs
Cypress.Commands.add('testModal', (triggerSelector, modalSelector, closeSelector) => {
  // Open modal
  cy.get(triggerSelector).click();
  cy.get(modalSelector).should('be.visible');

  // Test modal content is accessible
  cy.get(modalSelector).within(() => {
    cy.get('[data-cy="modal-title"]').should('be.visible');
    cy.get('[data-cy="modal-content"]').should('be.visible');
  });

  // Close modal
  cy.get(closeSelector).click();
  cy.get(modalSelector).should('not.be.visible');
});

// Custom command to test theme switching
Cypress.Commands.add('testThemeSwitch', () => {
  // Check default theme
  cy.get('body').should('have.class', 'theme-light');

  // Switch to dark theme
  cy.get('[data-cy="theme-toggle"]').click();
  cy.get('body').should('have.class', 'theme-dark');

  // Switch back to light theme
  cy.get('[data-cy="theme-toggle"]').click();
  cy.get('body').should('have.class', 'theme-light');
});

// Custom command to test language switching
Cypress.Commands.add('testLanguageSwitch', (language, expectedTexts) => {
  // Switch language
  cy.get('[data-cy="language-select"]').select(language);

  // Check if texts are translated
  expectedTexts.forEach(({ selector, text }) => {
    cy.get(selector).should('contain', text);
  });
});

// Custom command to test file upload
Cypress.Commands.add('testFileUpload', (inputSelector, fileName, fileType) => {
  cy.get(inputSelector).selectFile({
    contents: Cypress.Buffer.from('file contents'),
    fileName: fileName,
    mimeType: fileType,
  });

  // Verify file was uploaded
  cy.get('[data-cy="uploaded-file"]').should('contain', fileName);
});

// Custom command to test drag and drop
Cypress.Commands.add('testDragAndDrop', (sourceSelector, targetSelector) => {
  cy.get(sourceSelector).trigger('mousedown', { button: 0 });
  cy.get(targetSelector).trigger('mousemove').trigger('mouseup', { force: true });
});

// Custom command to test infinite scroll
Cypress.Commands.add('testInfiniteScroll', (containerSelector, itemSelector, scrollDistance) => {
  const initialItemCount = cy.get(itemSelector).its('length');

  // Scroll down
  cy.get(containerSelector).scrollTo(0, scrollDistance);

  // Wait for new items to load
  cy.waitForNetworkIdle();

  // Check that more items were loaded
  cy.get(itemSelector).its('length').should('be.gt', initialItemCount);
});

// Custom command to test real-time updates
Cypress.Commands.add('testRealTimeUpdates', (websocketUrl, expectedMessage) => {
  cy.window().then((win) => {
    const ws = new win.WebSocket(websocketUrl);

    ws.onmessage = (event) => {
      const message = JSON.parse(event.data);
      expect(message.type).to.equal(expectedMessage.type);
      expect(message.data).to.equal(expectedMessage.data);
      ws.close();
    };

    // Send a test message
    ws.onopen = () => {
      ws.send(JSON.stringify({ type: 'test', data: 'hello' }));
    };
  });
});

// Custom command to test print functionality
Cypress.Commands.add('testPrintFunctionality', (printButtonSelector) => {
  // Mock window.print
  cy.window().then((win) => {
    cy.stub(win, 'print').as('print');
  });

  // Click print button
  cy.get(printButtonSelector).click();

  // Verify print was called
  cy.get('@print').should('have.been.called');
});

// Custom command to test export functionality
Cypress.Commands.add('testExportFunctionality', (exportButtonSelector, expectedFileName) => {
  cy.get(exportButtonSelector).click();

  // Verify download was triggered
  cy.readFile(`cypress/downloads/${expectedFileName}`).should('exist');
});

// Custom command to test import functionality
Cypress.Commands.add('testImportFunctionality', (filePath, importButtonSelector) => {
  cy.get('[data-cy="file-input"]').selectFile(filePath);
  cy.get(importButtonSelector).click();

  // Verify import was successful
  cy.get('[data-cy="import-success"]').should('be.visible');
});

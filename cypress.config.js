const { defineConfig } = require('cypress');

module.exports = defineConfig({
  projectId: 'tpt-government-platform',
  e2e: {
    baseUrl: 'http://localhost:3000',
    viewportWidth: 1280,
    viewportHeight: 720,
    defaultCommandTimeout: 10000,
    requestTimeout: 15000,
    responseTimeout: 15000,
    video: true,
    screenshotOnRunFailure: true,
    watchForFileChanges: false,
    retries: {
      runMode: 2,
      openMode: 0,
    },
    env: {
      API_URL: 'http://localhost:8000/api',
      TEST_USER_EMAIL: 'test@example.com',
      TEST_USER_PASSWORD: 'password123',
      ADMIN_USER_EMAIL: 'admin@gov.local',
      ADMIN_USER_PASSWORD: 'password',
    },
    setupNodeEvents(on, config) {
      // implement node event listeners here
      on('task', {
        log(message) {
          console.log(message);
          return null;
        },
        table(message) {
          console.table(message);
          return null;
        }
      });
    },
    specPattern: 'tests/E2E/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/E2E/support/e2e.js',
  },
  component: {
    devServer: {
      framework: 'create-react-app',
      bundler: 'webpack',
    },
    specPattern: 'tests/E2E/components/**/*.cy.{js,jsx,ts,tsx}',
    supportFile: 'tests/E2E/support/component.js',
  },
});

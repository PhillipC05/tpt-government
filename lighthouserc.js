module.exports = {
  ci: {
    collect: {
      numberOfRuns: 3,
      startServerCommand: 'npm run serve',
      startServerReadyPattern: 'Local:.+localhost:3000',
      url: [
        'http://localhost:3000/',
        'http://localhost:3000/login',
        'http://localhost:3000/dashboard',
        'http://localhost:3000/services'
      ]
    },
    assert: {
      assertions: {
        // Performance assertions
        'categories.performance': ['error', { minScore: 0.8 }],
        'categories.accessibility': ['error', { minScore: 0.9 }],
        'categories.best-practices': ['error', { minScore: 0.9 }],
        'categories.seo': ['error', { minScore: 0.9 }],
        'categories.pwa': ['error', { minScore: 0.8 }],

        // Core Web Vitals
        'first-contentful-paint': ['error', { maxNumericValue: 2000 }],
        'largest-contentful-paint': ['error', { maxNumericValue: 2500 }],
        'first-meaningful-paint': ['error', { maxNumericValue: 2000 }],
        'speed-index': ['error', { maxNumericValue: 3000 }],
        'total-blocking-time': ['error', { maxNumericValue: 300 }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1 }],

        // Resource sizes
        'total-byte-weight': ['error', { maxNumericValue: 2048000 }], // 2MB
        'dom-size': ['error', { maxNumericValue: 1500 }],

        // Accessibility
        'color-contrast': 'error',
        'image-alt': 'error',
        'link-name': 'error',
        'button-name': 'error',

        // Best practices
        'is-on-https': 'error',
        'external-anchors-use-rel-noopener': 'error',
        'no-vulnerable-libraries': 'error',

        // SEO
        'document-title': 'error',
        'meta-description': 'error',
        'http-status-code': 'error',
        'link-text': 'error',

        // PWA
        'installable-manifest': 'error',
        'service-worker': 'error',
        'works-offline': 'error',
        'offline-start-url': 'error'
      }
    },
    upload: {
      target: 'temporary-public-storage'
    }
  }
};

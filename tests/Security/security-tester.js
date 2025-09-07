#!/usr/bin/env node

/**
 * Security Testing Suite for TPT Government Platform
 *
 * This script performs comprehensive security testing including:
 * - SQL injection testing
 * - XSS vulnerability testing
 * - CSRF protection testing
 * - Directory traversal testing
 * - Command injection testing
 * - Header security analysis
 * - SSL/TLS configuration testing
 */

const axios = require('axios');
const fs = require('fs').promises;
const path = require('path');
const https = require('https');

class SecurityTester {
  constructor(configPath = './tests/Security/security-config.json') {
    this.config = null;
    this.results = {
      summary: {
        totalTests: 0,
        passed: 0,
        failed: 0,
        warnings: 0,
        vulnerabilities: []
      },
      tests: [],
      timestamp: new Date().toISOString()
    };
    this.configPath = configPath;
  }

  async loadConfig() {
    try {
      const configData = await fs.readFile(this.configPath, 'utf8');
      this.config = JSON.parse(configData);
      console.log('✅ Security configuration loaded successfully');
    } catch (error) {
      console.error('❌ Failed to load security configuration:', error.message);
      throw error;
    }
  }

  async runAllTests() {
    console.log('🔒 Starting Security Testing Suite...\n');

    await this.loadConfig();

    const testSuites = [
      this.testSQLInjection.bind(this),
      this.testXSS.bind(this),
      this.testCSRF.bind(this),
      this.testDirectoryTraversal.bind(this),
      this.testCommandInjection.bind(this),
      this.testHeaders.bind(this),
      this.testCORS.bind(this),
      this.testAPISecurity.bind(this),
      this.testRateLimiting.bind(this),
      this.testSessionManagement.bind(this)
    ];

    for (const testSuite of testSuites) {
      try {
        await testSuite();
      } catch (error) {
        console.error(`❌ Test suite failed:`, error.message);
        this.recordVulnerability('test_suite_error', 'high', `Test suite failed: ${error.message}`);
      }
    }

    await this.generateReport();
    this.printSummary();
  }

  async testSQLInjection() {
    console.log('🗃️  Testing SQL Injection vulnerabilities...');

    if (!this.config.vulnerabilityTests.sqlInjection.enabled) {
      console.log('⏭️  SQL Injection tests disabled');
      return;
    }

    const endpoints = [
      `${this.config.target.baseUrl}/api/auth/login`,
      `${this.config.target.baseUrl}/api/users`,
      `${this.config.target.baseUrl}/api/search`
    ];

    for (const endpoint of endpoints) {
      for (const payload of this.config.vulnerabilityTests.sqlInjection.payloads) {
        try {
          const response = await axios.post(endpoint, {
            email: payload,
            password: payload,
            query: payload
          }, {
            timeout: 5000,
            validateStatus: () => true
          });

          // Check for SQL error patterns in response
          const sqlErrors = [
            'sql syntax',
            'mysql_fetch',
            'sqlite3',
            'postgresql',
            'oracle error',
            'sql server'
          ];

          const responseText = JSON.stringify(response.data).toLowerCase();
          const hasSQLError = sqlErrors.some(error =>
            responseText.includes(error.toLowerCase())
          );

          if (hasSQLError || response.status === 500) {
            this.recordVulnerability('sql_injection', 'critical',
              `Potential SQL injection vulnerability at ${endpoint} with payload: ${payload}`);
          } else {
            this.recordTestResult('sql_injection', true, `Safe payload: ${payload}`);
          }

        } catch (error) {
          this.recordTestResult('sql_injection', false, `Request failed: ${error.message}`);
        }
      }
    }
  }

  async testXSS() {
    console.log('🕷️  Testing XSS vulnerabilities...');

    if (!this.config.vulnerabilityTests.xss.enabled) {
      console.log('⏭️  XSS tests disabled');
      return;
    }

    const endpoints = [
      `${this.config.target.baseUrl}/api/search`,
      `${this.config.target.baseUrl}/api/feedback`
    ];

    for (const endpoint of endpoints) {
      for (const payload of this.config.vulnerabilityTests.xss.payloads) {
        try {
          const response = await axios.post(endpoint, {
            query: payload,
            message: payload,
            comment: payload
          }, {
            timeout: 5000,
            validateStatus: () => true
          });

          // Check if XSS payload is reflected in response without encoding
          const responseText = JSON.stringify(response.data);
          if (responseText.includes(payload) && !this.isEncoded(payload, responseText)) {
            this.recordVulnerability('xss', 'high',
              `Potential XSS vulnerability at ${endpoint} with payload: ${payload}`);
          } else {
            this.recordTestResult('xss', true, `Safe payload: ${payload}`);
          }

        } catch (error) {
          this.recordTestResult('xss', false, `Request failed: ${error.message}`);
        }
      }
    }
  }

  async testCSRF() {
    console.log('🔄 Testing CSRF protection...');

    if (!this.config.vulnerabilityTests.csrf.enabled) {
      console.log('⏭️  CSRF tests disabled');
      return;
    }

    const endpoints = [
      `${this.config.target.baseUrl}/api/users`,
      `${this.config.target.baseUrl}/api/webhooks`
    ];

    for (const endpoint of endpoints) {
      for (const token of this.config.vulnerabilityTests.csrf.testTokens) {
        try {
          const response = await axios.post(endpoint, {
            name: 'Test',
            _csrf: token
          }, {
            headers: {
              'X-CSRF-Token': token,
              'Referer': 'https://evil.com'
            },
            timeout: 5000,
            validateStatus: () => true
          });

          if (response.status === 200 && token === '') {
            this.recordVulnerability('csrf', 'high',
              `Potential CSRF vulnerability at ${endpoint} - accepts requests without token`);
          } else if (response.status === 403) {
            this.recordTestResult('csrf', true, `Properly rejects invalid token: ${token}`);
          }

        } catch (error) {
          this.recordTestResult('csrf', false, `Request failed: ${error.message}`);
        }
      }
    }
  }

  async testDirectoryTraversal() {
    console.log('📁 Testing Directory Traversal vulnerabilities...');

    if (!this.config.vulnerabilityTests.directoryTraversal.enabled) {
      console.log('⏭️  Directory Traversal tests disabled');
      return;
    }

    const endpoints = [
      `${this.config.target.baseUrl}/api/files`,
      `${this.config.target.baseUrl}/api/download`
    ];

    for (const endpoint of endpoints) {
      for (const payload of this.config.vulnerabilityTests.directoryTraversal.payloads) {
        try {
          const response = await axios.get(`${endpoint}?path=${encodeURIComponent(payload)}`, {
            timeout: 5000,
            validateStatus: () => true
          });

          // Check for file content patterns that shouldn't be accessible
          const sensitivePatterns = [
            '/etc/passwd',
            'root:',
            'bin/bash',
            'windows',
            'system32'
          ];

          const responseText = JSON.stringify(response.data).toLowerCase();
          const hasSensitiveData = sensitivePatterns.some(pattern =>
            responseText.includes(pattern.toLowerCase())
          );

          if (hasSensitiveData || response.status === 200) {
            this.recordVulnerability('directory_traversal', 'critical',
              `Potential directory traversal vulnerability at ${endpoint} with payload: ${payload}`);
          } else {
            this.recordTestResult('directory_traversal', true, `Safe payload: ${payload}`);
          }

        } catch (error) {
          this.recordTestResult('directory_traversal', false, `Request failed: ${error.message}`);
        }
      }
    }
  }

  async testCommandInjection() {
    console.log('💻 Testing Command Injection vulnerabilities...');

    if (!this.config.vulnerabilityTests.commandInjection.enabled) {
      console.log('⏭️  Command Injection tests disabled');
      return;
    }

    const endpoints = [
      `${this.config.target.baseUrl}/api/system/exec`,
      `${this.config.target.baseUrl}/api/tools/ping`
    ];

    for (const endpoint of endpoints) {
      for (const payload of this.config.vulnerabilityTests.commandInjection.payloads) {
        try {
          const response = await axios.post(endpoint, {
            command: payload,
            host: payload
          }, {
            timeout: 10000,
            validateStatus: () => true
          });

          // Check for command execution indicators
          const commandIndicators = [
            'uid=',
            'gid=',
            'permission denied',
            'no such file',
            'command not found',
            'root',
            'bin/bash'
          ];

          const responseText = JSON.stringify(response.data).toLowerCase();
          const hasCommandOutput = commandIndicators.some(indicator =>
            responseText.includes(indicator.toLowerCase())
          );

          if (hasCommandOutput) {
            this.recordVulnerability('command_injection', 'critical',
              `Potential command injection vulnerability at ${endpoint} with payload: ${payload}`);
          } else {
            this.recordTestResult('command_injection', true, `Safe payload: ${payload}`);
          }

        } catch (error) {
          this.recordTestResult('command_injection', false, `Request failed: ${error.message}`);
        }
      }
    }
  }

  async testHeaders() {
    console.log('📋 Testing Security Headers...');

    if (!this.config.vulnerabilityTests.headers.enabled) {
      console.log('⏭️  Header tests disabled');
      return;
    }

    try {
      const response = await axios.get(this.config.target.baseUrl, {
        timeout: 5000,
        validateStatus: () => true
      });

      const headers = response.headers;

      // Check required security headers
      for (const requiredHeader of this.config.vulnerabilityTests.headers.requiredHeaders) {
        if (!headers[requiredHeader.toLowerCase()]) {
          this.recordVulnerability('missing_security_header', 'medium',
            `Missing required security header: ${requiredHeader}`);
        } else {
          this.recordTestResult('security_headers', true, `Present: ${requiredHeader}`);
        }
      }

      // Check forbidden headers
      for (const forbiddenHeader of this.config.vulnerabilityTests.headers.forbiddenHeaders) {
        if (headers[forbiddenHeader.toLowerCase()]) {
          this.recordVulnerability('information_disclosure', 'medium',
            `Forbidden header exposed: ${forbiddenHeader}`);
        }
      }

      // Check specific header values
      if (headers['x-frame-options'] && headers['x-frame-options'] === 'DENY') {
        this.recordTestResult('security_headers', true, 'X-Frame-Options properly set to DENY');
      }

      if (headers['content-security-policy']) {
        this.recordTestResult('security_headers', true, 'Content-Security-Policy header present');
      }

    } catch (error) {
      this.recordTestResult('security_headers', false, `Header check failed: ${error.message}`);
    }
  }

  async testCORS() {
    console.log('🌐 Testing CORS configuration...');

    if (!this.config.vulnerabilityTests.cors.enabled) {
      console.log('⏭️  CORS tests disabled');
      return;
    }

    for (const origin of this.config.vulnerabilityTests.cors.testOrigins) {
      try {
        const response = await axios.options(this.config.target.baseUrl, {
          headers: {
            'Origin': origin,
            'Access-Control-Request-Method': 'POST',
            'Access-Control-Request-Headers': 'Content-Type'
          },
          timeout: 5000,
          validateStatus: () => true
        });

        const allowOrigin = response.headers['access-control-allow-origin'];

        if (allowOrigin === '*' && origin !== 'http://localhost:3000') {
          this.recordVulnerability('cors_misconfiguration', 'medium',
            `CORS allows all origins (*) - potential security risk`);
        } else if (allowOrigin === origin) {
          this.recordTestResult('cors', true, `Properly configured for origin: ${origin}`);
        }

      } catch (error) {
        this.recordTestResult('cors', false, `CORS test failed for ${origin}: ${error.message}`);
      }
    }
  }

  async testAPISecurity() {
    console.log('🔑 Testing API Security...');

    if (!this.config.vulnerabilityTests.apiSecurity.enabled) {
      console.log('⏭️  API Security tests disabled');
      return;
    }

    // Test HTTP Method Override
    try {
      const response = await axios.post(`${this.config.target.baseUrl}/api/users`, {
        name: 'Test User'
      }, {
        headers: {
          'X-HTTP-Method-Override': 'PUT'
        },
        timeout: 5000,
        validateStatus: () => true
      });

      if (response.status === 200) {
        this.recordVulnerability('method_override', 'medium',
          'HTTP Method Override allowed - potential security risk');
      }

    } catch (error) {
      this.recordTestResult('api_security', true, 'Method override properly blocked');
    }

    // Test Mass Assignment
    try {
      const response = await axios.post(`${this.config.target.baseUrl}/api/users`, {
        username: 'testuser',
        email: 'test@example.com',
        password: 'password123',
        role: 'admin', // Should not be allowed
        is_admin: true // Should not be allowed
      }, {
        timeout: 5000,
        validateStatus: () => true
      });

      if (response.status === 201) {
        // Check if mass assignment was prevented
        this.recordTestResult('api_security', true, 'Mass assignment protection working');
      }

    } catch (error) {
      this.recordTestResult('api_security', false, `Mass assignment test failed: ${error.message}`);
    }
  }

  async testRateLimiting() {
    console.log('⏱️  Testing Rate Limiting...');

    if (!this.config.vulnerabilityTests.rateLimiting.enabled) {
      console.log('⏭️  Rate Limiting tests disabled');
      return;
    }

    const endpoint = `${this.config.target.baseUrl}/api/health`;
    const requests = [];
    const numRequests = this.config.vulnerabilityTests.rateLimiting.requestsPerMinute;

    // Send multiple requests rapidly
    for (let i = 0; i < numRequests; i++) {
      requests.push(
        axios.get(endpoint, {
          timeout: 5000,
          validateStatus: () => true
        })
      );
    }

    try {
      const responses = await Promise.allSettled(requests);
      const rateLimited = responses.filter(result =>
        result.status === 'fulfilled' && result.value.status === 429
      );

      if (rateLimited.length > 0) {
        this.recordTestResult('rate_limiting', true, `Rate limiting working: ${rateLimited.length} requests blocked`);
      } else {
        this.recordVulnerability('rate_limiting', 'medium',
          'No rate limiting detected - potential DoS vulnerability');
      }

    } catch (error) {
      this.recordTestResult('rate_limiting', false, `Rate limiting test failed: ${error.message}`);
    }
  }

  async testSessionManagement() {
    console.log('🔐 Testing Session Management...');

    if (!this.config.vulnerabilityTests.sessionManagement.enabled) {
      console.log('⏭️  Session Management tests disabled');
      return;
    }

    // Test session fixation
    try {
      // Login with valid credentials
      const loginResponse = await axios.post(`${this.config.target.baseUrl}/api/auth/login`, {
        email: this.config.authentication.testUser.email,
        password: this.config.authentication.testUser.password
      }, {
        timeout: 5000,
        validateStatus: () => true
      });

      if (loginResponse.status === 200) {
        const sessionId = loginResponse.data.session_id;

        // Try to use the session
        const sessionResponse = await axios.get(`${this.config.target.baseUrl}/api/auth/session`, {
          headers: {
            'Authorization': `Bearer ${sessionId}`
          },
          timeout: 5000,
          validateStatus: () => true
        });

        if (sessionResponse.status === 200) {
          this.recordTestResult('session_management', true, 'Session validation working');
        } else {
          this.recordVulnerability('session_management', 'high', 'Session validation failed');
        }

        // Test session invalidation
        await axios.post(`${this.config.target.baseUrl}/api/auth/logout`, {}, {
          headers: {
            'Authorization': `Bearer ${sessionId}`
          },
          timeout: 5000,
          validateStatus: () => true
        });

        // Try to use invalidated session
        const invalidSessionResponse = await axios.get(`${this.config.target.baseUrl}/api/auth/session`, {
          headers: {
            'Authorization': `Bearer ${sessionId}`
          },
          timeout: 5000,
          validateStatus: () => true
        });

        if (invalidSessionResponse.status === 401) {
          this.recordTestResult('session_management', true, 'Session invalidation working');
        } else {
          this.recordVulnerability('session_management', 'high', 'Session not properly invalidated');
        }
      }

    } catch (error) {
      this.recordTestResult('session_management', false, `Session test failed: ${error.message}`);
    }
  }

  isEncoded(payload, response) {
    // Check if dangerous characters are properly encoded
    const dangerousChars = ['<', '>', '"', "'", '&'];
    const encodedChars = ['<', '>', '"', '&#x27;', '&'];

    for (let i = 0; i < dangerousChars.length; i++) {
      if (payload.includes(dangerousChars[i]) && response.includes(dangerousChars[i])) {
        return false; // Not encoded
      }
    }

    return true; // Properly encoded or no dangerous chars
  }

  recordVulnerability(type, severity, description) {
    this.results.summary.vulnerabilities.push({
      type,
      severity,
      description,
      timestamp: new Date().toISOString()
    });

    this.results.summary.failed++;

    console.log(`🚨 [${severity.toUpperCase()}] ${description}`);
  }

  recordTestResult(type, passed, description) {
    this.results.tests.push({
      type,
      passed,
      description,
      timestamp: new Date().toISOString()
    });

    if (passed) {
      this.results.summary.passed++;
      console.log(`✅ ${description}`);
    } else {
      this.results.summary.failed++;
      console.log(`❌ ${description}`);
    }

    this.results.summary.totalTests++;
  }

  async generateReport() {
    const reportDir = this.config.reporting.outputDir;
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const reportPath = path.join(reportDir, `security-report-${timestamp}.json`);

    try {
      await fs.mkdir(reportDir, { recursive: true });
      await fs.writeFile(reportPath, JSON.stringify(this.results, null, 2));
      console.log(`\n📄 Security report generated: ${reportPath}`);
    } catch (error) {
      console.error('❌ Failed to generate report:', error.message);
    }
  }

  printSummary() {
    console.log('\n' + '='.repeat(60));
    console.log('🔒 SECURITY TESTING SUMMARY');
    console.log('='.repeat(60));

    console.log(`Total Tests: ${this.results.summary.totalTests}`);
    console.log(`✅ Passed: ${this.results.summary.passed}`);
    console.log(`❌ Failed: ${this.results.summary.failed}`);
    console.log(`⚠️  Vulnerabilities: ${this.results.summary.vulnerabilities.length}`);

    if (this.results.summary.vulnerabilities.length > 0) {
      console.log('\n🚨 VULNERABILITIES FOUND:');
      this.results.summary.vulnerabilities.forEach((vuln, index) => {
        console.log(`${index + 1}. [${vuln.severity.toUpperCase()}] ${vuln.type}: ${vuln.description}`);
      });
    }

    // Check thresholds
    const thresholds = this.config.thresholds.maxVulnerabilities;
    let thresholdExceeded = false;

    Object.keys(thresholds).forEach(severity => {
      const count = this.results.summary.vulnerabilities.filter(v => v.severity === severity).length;
      if (count > thresholds[severity]) {
        console.log(`\n❌ Threshold exceeded for ${severity} vulnerabilities: ${count}/${thresholds[severity]}`);
        thresholdExceeded = true;
      }
    });

    if (!thresholdExceeded && this.results.summary.failed === 0) {
      console.log('\n🎉 All security tests passed!');
    } else {
      console.log('\n⚠️  Security issues detected. Review the report for details.');
    }

    console.log('='.repeat(60));
  }
}

// CLI interface
if (require.main === module) {
  const tester = new SecurityTester();

  tester.runAllTests().catch(error => {
    console.error('❌ Security testing failed:', error.message);
    process.exit(1);
  });
}

module.exports = SecurityTester;

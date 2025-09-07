/**
 * Artillery Load Testing Processor
 *
 * Custom processor for Artillery load testing scenarios
 * Handles request/response processing, metrics collection, and custom logic
 */

const fs = require('fs').promises;
const path = require('path');

class LoadTestProcessor {
  constructor() {
    this.metrics = {
      startTime: null,
      endTime: null,
      totalRequests: 0,
      successfulRequests: 0,
      failedRequests: 0,
      responseTimes: [],
      statusCodes: {},
      errors: [],
      customMetrics: {}
    };

    this.sessions = new Map();
    this.testData = {
      users: [],
      workflows: [],
      webhooks: []
    };
  }

  // Initialize the processor
  async initialize() {
    console.log('🚀 Initializing Load Test Processor...');
    this.metrics.startTime = new Date();

    // Generate test data
    await this.generateTestData();

    console.log('✅ Load Test Processor initialized');
  }

  // Generate test data for load testing
  async generateTestData() {
    // Generate test users
    for (let i = 0; i < 1000; i++) {
      this.testData.users.push({
        id: i + 1,
        email: `testuser${i}@example.com`,
        username: `testuser${i}`,
        password: 'password123',
        role: i % 10 === 0 ? 'admin' : 'user'
      });
    }

    // Generate test workflows
    for (let i = 0; i < 500; i++) {
      this.testData.workflows.push({
        id: i + 1,
        title: `Test Workflow ${i}`,
        description: `Load testing workflow ${i}`,
        priority: ['low', 'normal', 'high'][i % 3],
        status: 'pending'
      });
    }

    // Generate test webhooks
    for (let i = 0; i < 200; i++) {
      this.testData.webhooks.push({
        id: i + 1,
        name: `Test Webhook ${i}`,
        url: `https://httpbin.org/post/${i}`,
        events: ['user.created', 'workflow.completed'],
        secret: `secret_${i}`
      });
    }

    console.log(`📊 Generated test data: ${this.testData.users.length} users, ${this.testData.workflows.length} workflows, ${this.testData.webhooks.length} webhooks`);
  }

  // Handle beforeRequest event
  async beforeRequest(requestParams, context, ee, next) {
    // Add custom headers
    requestParams.headers = requestParams.headers || {};
    requestParams.headers['X-Load-Test'] = 'true';
    requestParams.headers['X-Test-ID'] = context.vars.$testId || 'unknown';
    requestParams.headers['X-Timestamp'] = new Date().toISOString();

    // Add authentication if session exists
    if (context.vars.sessionId) {
      requestParams.headers['Authorization'] = `Bearer ${context.vars.sessionId}`;
    }

    // Track request start time
    context.vars.requestStartTime = Date.now();

    return next();
  }

  // Handle afterResponse event
  async afterResponse(requestParams, response, context, ee, next) {
    const responseTime = Date.now() - context.vars.requestStartTime;

    // Update metrics
    this.metrics.totalRequests++;
    this.metrics.responseTimes.push(responseTime);

    // Track status codes
    const statusCode = response.statusCode || response.status;
    this.metrics.statusCodes[statusCode] = (this.metrics.statusCodes[statusCode] || 0) + 1;

    // Track success/failure
    if (statusCode >= 200 && statusCode < 400) {
      this.metrics.successfulRequests++;
    } else {
      this.metrics.failedRequests++;

      // Log errors
      if (statusCode >= 400) {
        this.metrics.errors.push({
          url: requestParams.url,
          method: requestParams.method,
          statusCode,
          responseTime,
          timestamp: new Date().toISOString(),
          body: this.truncateString(response.body, 500)
        });
      }
    }

    // Extract and store session data
    if (response.body && typeof response.body === 'string') {
      try {
        const jsonBody = JSON.parse(response.body);

        // Store session ID if login successful
        if (jsonBody.session_id) {
          context.vars.sessionId = jsonBody.session_id;
          this.sessions.set(context.vars.$testId, jsonBody.session_id);
        }

        // Store user ID
        if (jsonBody.user && jsonBody.user.id) {
          context.vars.userId = jsonBody.user.id;
        }

        // Store workflow ID
        if (jsonBody.id && requestParams.url.includes('/workflows')) {
          context.vars.workflowId = jsonBody.id;
        }

        // Store webhook ID
        if (jsonBody.id && requestParams.url.includes('/webhooks')) {
          context.vars.webhookId = jsonBody.id;
        }

      } catch (e) {
        // Response is not JSON, skip parsing
      }
    }

    // Custom metrics based on response
    this.updateCustomMetrics(requestParams, response, responseTime);

    return next();
  }

  // Update custom metrics
  updateCustomMetrics(requestParams, response, responseTime) {
    const url = requestParams.url;
    const method = requestParams.method;

    // API endpoint metrics
    if (url.includes('/api/')) {
      const endpoint = url.split('/api/')[1].split('/')[0];
      this.metrics.customMetrics[`api_${endpoint}_${method.toLowerCase()}`] =
        (this.metrics.customMetrics[`api_${endpoint}_${method.toLowerCase()}`] || 0) + 1;
    }

    // Response time percentiles
    if (responseTime > 5000) {
      this.metrics.customMetrics.slow_responses = (this.metrics.customMetrics.slow_responses || 0) + 1;
    } else if (responseTime > 2000) {
      this.metrics.customMetrics.medium_responses = (this.metrics.customMetrics.medium_responses || 0) + 1;
    } else {
      this.metrics.customMetrics.fast_responses = (this.metrics.customMetrics.fast_responses || 0) + 1;
    }

    // Error rate by endpoint
    if (response.statusCode >= 400) {
      const endpoint = url.split('/').pop();
      this.metrics.customMetrics[`errors_${endpoint}`] = (this.metrics.customMetrics[`errors_${endpoint}`] || 0) + 1;
    }
  }

  // Generate random test data
  generateRandomUser() {
    const user = this.testData.users[Math.floor(Math.random() * this.testData.users.length)];
    return {
      email: user.email,
      password: user.password,
      username: user.username
    };
  }

  generateRandomWorkflow() {
    const workflow = this.testData.workflows[Math.floor(Math.random() * this.testData.workflows.length)];
    return {
      title: workflow.title,
      description: workflow.description,
      priority: workflow.priority,
      data: { test_field: `test_value_${Math.random()}` }
    };
  }

  generateRandomWebhook() {
    const webhook = this.testData.webhooks[Math.floor(Math.random() * this.testData.webhooks.length)];
    return {
      name: webhook.name,
      url: webhook.url,
      events: webhook.events,
      secret: webhook.secret
    };
  }

  // Utility function to truncate strings
  truncateString(str, maxLength) {
    if (!str || str.length <= maxLength) return str;
    return str.substring(0, maxLength) + '...';
  }

  // Handle test completion
  async onTestComplete() {
    this.metrics.endTime = new Date();
    const duration = this.metrics.endTime - this.metrics.startTime;

    console.log('\n📊 Load Test Summary:');
    console.log('='.repeat(50));
    console.log(`Duration: ${Math.round(duration / 1000)}s`);
    console.log(`Total Requests: ${this.metrics.totalRequests}`);
    console.log(`Successful: ${this.metrics.successfulRequests}`);
    console.log(`Failed: ${this.metrics.failedRequests}`);
    console.log(`Success Rate: ${((this.metrics.successfulRequests / this.metrics.totalRequests) * 100).toFixed(2)}%`);

    if (this.metrics.responseTimes.length > 0) {
      const avgResponseTime = this.metrics.responseTimes.reduce((a, b) => a + b, 0) / this.metrics.responseTimes.length;
      const minResponseTime = Math.min(...this.metrics.responseTimes);
      const maxResponseTime = Math.max(...this.metrics.responseTimes);

      console.log(`\nResponse Times:`);
      console.log(`Average: ${avgResponseTime.toFixed(2)}ms`);
      console.log(`Min: ${minResponseTime}ms`);
      console.log(`Max: ${maxResponseTime}ms`);
    }

    console.log(`\nStatus Codes:`);
    Object.entries(this.metrics.statusCodes).forEach(([code, count]) => {
      console.log(`${code}: ${count}`);
    });

    // Save detailed report
    await this.saveReport();
  }

  // Save detailed test report
  async saveReport() {
    const reportDir = 'tests/LoadTesting/reports';
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const reportPath = path.join(reportDir, `load-test-report-${timestamp}.json`);

    try {
      await fs.mkdir(reportDir, { recursive: true });

      const report = {
        summary: {
          ...this.metrics,
          duration: this.metrics.endTime - this.metrics.startTime,
          requestsPerSecond: this.metrics.totalRequests / ((this.metrics.endTime - this.metrics.startTime) / 1000),
          successRate: (this.metrics.successfulRequests / this.metrics.totalRequests) * 100
        },
        customMetrics: this.metrics.customMetrics,
        errors: this.metrics.errors.slice(0, 100), // Limit error logs
        sessions: Array.from(this.sessions.entries())
      };

      await fs.writeFile(reportPath, JSON.stringify(report, null, 2));
      console.log(`📄 Detailed report saved: ${reportPath}`);

    } catch (error) {
      console.error('❌ Failed to save load test report:', error.message);
    }
  }

  // Custom functions for Artillery scenarios
  getRandomUser(context, events, done) {
    const user = this.generateRandomUser();
    context.vars.user = user;
    return done();
  }

  getRandomWorkflow(context, events, done) {
    const workflow = this.generateRandomWorkflow();
    context.vars.workflow = workflow;
    return done();
  }

  getRandomWebhook(context, events, done) {
    const webhook = this.generateRandomWebhook();
    context.vars.webhook = webhook;
    return done();
  }

  // Validate response function
  validateResponse(requestParams, response, context, events, done) {
    const isValid = response.statusCode >= 200 && response.statusCode < 400;

    if (!isValid) {
      events.emit('counter', 'failed_responses', 1);
      console.log(`❌ Invalid response: ${response.statusCode} for ${requestParams.url}`);
    } else {
      events.emit('counter', 'successful_responses', 1);
    }

    return done();
  }

  // Rate limiting test function
  testRateLimit(requestParams, response, context, events, done) {
    if (response.statusCode === 429) {
      events.emit('counter', 'rate_limited', 1);
      console.log('🚦 Rate limit triggered');
    }

    return done();
  }
}

// Export functions for Artillery
const processor = new LoadTestProcessor();

// Initialize on module load
processor.initialize().catch(console.error);

module.exports = {
  beforeRequest: processor.beforeRequest.bind(processor),
  afterResponse: processor.afterResponse.bind(processor),
  getRandomUser: processor.getRandomUser.bind(processor),
  getRandomWorkflow: processor.getRandomWorkflow.bind(processor),
  getRandomWebhook: processor.getRandomWebhook.bind(processor),
  validateResponse: processor.validateResponse.bind(processor),
  testRateLimit: processor.testRateLimit.bind(processor),
  onTestComplete: processor.onTestComplete.bind(processor)
};

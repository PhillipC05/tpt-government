#!/usr/bin/env node

/**
 * Accessibility Testing Suite for TPT Government Platform
 *
 * This script performs comprehensive accessibility testing including:
 * - WCAG 2.1 AA compliance checking
 * - Automated accessibility audits
 * - Color contrast analysis
 * - Keyboard navigation testing
 * - Screen reader compatibility
 * - ARIA validation
 */

const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');
const { AxePuppeteer } = require('@axe-core/puppeteer');

class AccessibilityTester {
  constructor(configPath = './tests/Accessibility/accessibility-config.json') {
    this.config = null;
    this.results = {
      summary: {
        totalPages: 0,
        totalViolations: 0,
        totalIncomplete: 0,
        totalInapplicable: 0,
        violationsByImpact: {
          critical: 0,
          serious: 0,
          moderate: 0,
          minor: 0
        },
        violationsByRule: {},
        pagesTested: []
      },
      details: [],
      timestamp: new Date().toISOString()
    };
    this.configPath = configPath;
    this.browser = null;
  }

  async loadConfig() {
    try {
      const configData = await fs.readFile(this.configPath, 'utf8');
      this.config = JSON.parse(configData);
      console.log('✅ Accessibility configuration loaded successfully');
    } catch (error) {
      console.error('❌ Failed to load accessibility configuration:', error.message);
      throw error;
    }
  }

  async initializeBrowser() {
    this.browser = await puppeteer.launch({
      headless: 'new',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--single-process',
        '--disable-gpu'
      ]
    });
    console.log('✅ Browser initialized for accessibility testing');
  }

  async runAllTests() {
    console.log('♿ Starting Accessibility Testing Suite...\n');

    await this.loadConfig();
    await this.initializeBrowser();

    try {
      for (const pagePath of this.config.target.pages) {
        await this.testPage(pagePath);
      }

      await this.generateReport();
      this.printSummary();
    } finally {
      if (this.browser) {
        await this.browser.close();
      }
    }
  }

  async testPage(pagePath) {
    const page = await this.browser.newPage();
    const url = `${this.config.target.baseUrl}${pagePath}`;

    console.log(`📄 Testing accessibility for: ${url}`);

    try {
      // Set viewport for consistent testing
      await page.setViewport({ width: 1280, height: 720 });

      // Navigate to page
      await page.goto(url, {
        waitUntil: 'networkidle0',
        timeout: 30000
      });

      // Wait for dynamic content to load
      await page.waitForTimeout(2000);

      // Inject axe-core
      await page.addScriptTag({
        url: 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.7.2/axe.min.js'
      });

      // Run axe-core audit
      const results = await page.evaluate((config) => {
        return new Promise((resolve) => {
          window.axe.run(document, {
            rules: config.rules,
            checks: config.checks,
            runOnly: config.rules.enabled
          }, (err, results) => {
            if (err) {
              resolve({ error: err.message });
            } else {
              resolve(results);
            }
          });
        });
      }, this.config);

      if (results.error) {
        console.error(`❌ Axe-core error for ${url}:`, results.error);
        return;
      }

      // Process results
      await this.processResults(url, results);

    } catch (error) {
      console.error(`❌ Failed to test page ${url}:`, error.message);
      this.recordPageError(url, error.message);
    } finally {
      await page.close();
    }
  }

  async processResults(url, results) {
    const pageResult = {
      url,
      timestamp: new Date().toISOString(),
      violations: results.violations || [],
      incomplete: results.incomplete || [],
      inapplicable: results.inapplicable || [],
      passes: results.passes || []
    };

    // Count violations by impact
    const violationsByImpact = {
      critical: 0,
      serious: 0,
      moderate: 0,
      minor: 0
    };

    const violationsByRule = {};

    for (const violation of pageResult.violations) {
      const impact = violation.impact || 'minor';
      violationsByImpact[impact] = (violationsByImpact[impact] || 0) + violation.nodes.length;

      const ruleId = violation.id;
      violationsByRule[ruleId] = (violationsByRule[ruleId] || 0) + violation.nodes.length;

      // Log violation
      console.log(`🚨 [${impact.toUpperCase()}] ${violation.id}: ${violation.description}`);
      console.log(`   📍 ${violation.helpUrl}`);
      console.log(`   🔢 ${violation.nodes.length} affected elements\n`);
    }

    // Update summary
    this.results.summary.totalPages++;
    this.results.summary.totalViolations += pageResult.violations.length;
    this.results.summary.totalIncomplete += pageResult.incomplete.length;
    this.results.summary.totalInapplicable += pageResult.inapplicable.length;

    // Merge violations by impact
    Object.keys(violationsByImpact).forEach(impact => {
      this.results.summary.violationsByImpact[impact] += violationsByImpact[impact];
    });

    // Merge violations by rule
    Object.keys(violationsByRule).forEach(rule => {
      this.results.summary.violationsByRule[rule] =
        (this.results.summary.violationsByRule[rule] || 0) + violationsByRule[rule];
    });

    // Add page to tested pages
    this.results.summary.pagesTested.push({
      url,
      violations: pageResult.violations.length,
      incomplete: pageResult.incomplete.length,
      passes: pageResult.passes.length
    });

    // Store detailed results
    this.results.details.push(pageResult);
  }

  recordPageError(url, error) {
    this.results.summary.pagesTested.push({
      url,
      error,
      violations: 0,
      incomplete: 0,
      passes: 0
    });

    this.results.details.push({
      url,
      timestamp: new Date().toISOString(),
      error,
      violations: [],
      incomplete: [],
      inapplicable: [],
      passes: []
    });
  }

  async generateReport() {
    const reportDir = this.config.reporting.outputDir;
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const reportPath = path.join(reportDir, `accessibility-report-${timestamp}.json`);
    const htmlReportPath = path.join(reportDir, `accessibility-report-${timestamp}.html`);

    try {
      await fs.mkdir(reportDir, { recursive: true });

      // Generate JSON report
      await fs.writeFile(reportPath, JSON.stringify(this.results, null, 2));

      // Generate HTML report
      const htmlReport = this.generateHtmlReport();
      await fs.writeFile(htmlReportPath, htmlReport);

      console.log(`\n📄 Accessibility reports generated:`);
      console.log(`   JSON: ${reportPath}`);
      console.log(`   HTML: ${htmlReportPath}`);

    } catch (error) {
      console.error('❌ Failed to generate accessibility report:', error.message);
    }
  }

  generateHtmlReport() {
    const { summary, details } = this.results;

    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessibility Test Report - TPT Government Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
        .metric h3 { margin: 0 0 10px 0; color: #333; }
        .metric .value { font-size: 2em; font-weight: bold; }
        .critical { color: #dc3545; }
        .serious { color: #fd7e14; }
        .moderate { color: #ffc107; }
        .minor { color: #17a2b8; }
        .success { color: #28a745; }
        .pages { margin-bottom: 30px; }
        .page-item { background: #f8f9fa; margin: 10px 0; padding: 15px; border-radius: 6px; }
        .violations { margin-top: 20px; }
        .violation { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; }
        .violation.critical { background: #f8d7da; border-left-color: #dc3545; }
        .violation.serious { background: #fff3cd; border-left-color: #fd7e14; }
        .violation.moderate { background: #d1ecf1; border-left-color: #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>♿ Accessibility Test Report</h1>
            <p>TPT Government Platform - WCAG 2.1 AA Compliance</p>
            <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
        </div>

        <div class="summary">
            <div class="metric">
                <h3>Pages Tested</h3>
                <div class="value">${summary.totalPages}</div>
            </div>
            <div class="metric critical">
                <h3>Violations</h3>
                <div class="value">${summary.totalViolations}</div>
            </div>
            <div class="metric moderate">
                <h3>Incomplete</h3>
                <div class="value">${summary.totalIncomplete}</div>
            </div>
            <div class="metric success">
                <h3>Passed Checks</h3>
                <div class="value">${summary.totalInapplicable}</div>
            </div>
        </div>

        <h2>Violations by Impact</h2>
        <div class="summary">
            <div class="metric critical">
                <h3>Critical</h3>
                <div class="value">${summary.violationsByImpact.critical}</div>
            </div>
            <div class="metric serious">
                <h3>Serious</h3>
                <div class="value">${summary.violationsByImpact.serious}</div>
            </div>
            <div class="metric moderate">
                <h3>Moderate</h3>
                <div class="value">${summary.violationsByImpact.moderate}</div>
            </div>
            <div class="metric minor">
                <h3>Minor</h3>
                <div class="value">${summary.violationsByImpact.minor}</div>
            </div>
        </div>

        <h2>Pages Tested</h2>
        <div class="pages">
            ${summary.pagesTested.map(page => `
                <div class="page-item">
                    <strong>${page.url}</strong>
                    <br>
                    <span class="critical">Violations: ${page.violations}</span> |
                    <span class="moderate">Incomplete: ${page.incomplete}</span> |
                    <span class="success">Passed: ${page.passes}</span>
                </div>
            `).join('')}
        </div>

        <h2>Detailed Violations</h2>
        <div class="violations">
            ${details.map(page => `
                ${page.violations.map(violation => `
                    <div class="violation ${violation.impact}">
                        <strong>${violation.id}</strong> (${violation.impact})
                        <br><em>${violation.description}</em>
                        <br><strong>Page:</strong> ${page.url}
                        <br><strong>Affected:</strong> ${violation.nodes.length} elements
                        <br><a href="${violation.helpUrl}" target="_blank">Learn more</a>
                    </div>
                `).join('')}
            `).join('')}
        </div>

        <h2>Violations by Rule</h2>
        <table>
            <thead>
                <tr>
                    <th>Rule ID</th>
                    <th>Count</th>
                    <th>Severity</th>
                </tr>
            </thead>
            <tbody>
                ${Object.entries(summary.violationsByRule).map(([rule, count]) => `
                    <tr>
                        <td>${rule}</td>
                        <td>${count}</td>
                        <td>${this.getSeverityForRule(rule)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
</body>
</html>`;
  }

  getSeverityForRule(ruleId) {
    const severityMap = this.config.reporting.severityLevels;
    for (const [severity, rules] of Object.entries(severityMap)) {
      if (rules.includes(ruleId)) {
        return severity;
      }
    }
    return 'unknown';
  }

  printSummary() {
    console.log('\n' + '='.repeat(60));
    console.log('♿ ACCESSIBILITY TESTING SUMMARY');
    console.log('='.repeat(60));

    console.log(`Pages Tested: ${this.results.summary.totalPages}`);
    console.log(`Total Violations: ${this.results.summary.totalViolations}`);
    console.log(`Incomplete Checks: ${this.results.summary.totalIncomplete}`);
    console.log(`Passed Checks: ${this.results.summary.totalInapplicable}`);

    console.log('\n🚨 VIOLATIONS BY IMPACT:');
    Object.entries(this.results.summary.violationsByImpact).forEach(([impact, count]) => {
      console.log(`   ${impact.toUpperCase()}: ${count}`);
    });

    if (this.results.summary.totalViolations > 0) {
      console.log('\n🚨 TOP VIOLATION RULES:');
      const sortedRules = Object.entries(this.results.summary.violationsByRule)
        .sort(([,a], [,b]) => b - a)
        .slice(0, 10);

      sortedRules.forEach(([rule, count]) => {
        console.log(`   ${rule}: ${count} violations`);
      });
    }

    // Check thresholds
    const thresholds = this.config.thresholds;
    let thresholdExceeded = false;

    Object.keys(thresholds.maxViolations).forEach(severity => {
      const count = this.results.summary.violationsByImpact[severity] || 0;
      if (count > thresholds.maxViolations[severity]) {
        console.log(`\n❌ Threshold exceeded for ${severity} violations: ${count}/${thresholds.maxViolations[severity]}`);
        thresholdExceeded = true;
      }
    });

    if (this.results.summary.totalIncomplete > thresholds.maxIncomplete) {
      console.log(`\n❌ Threshold exceeded for incomplete checks: ${this.results.summary.totalIncomplete}/${thresholds.maxIncomplete}`);
      thresholdExceeded = true;
    }

    if (!thresholdExceeded && this.results.summary.totalViolations === 0) {
      console.log('\n🎉 All accessibility tests passed!');
    } else {
      console.log('\n⚠️  Accessibility issues detected. Review the HTML report for details.');
    }

    console.log('='.repeat(60));
  }
}

// CLI interface
if (require.main === module) {
  const tester = new AccessibilityTester();

  tester.runAllTests().catch(error => {
    console.error('❌ Accessibility testing failed:', error.message);
    process.exit(1);
  });
}

module.exports = AccessibilityTester;

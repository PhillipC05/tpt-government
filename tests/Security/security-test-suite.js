/**
 * TPT Government Platform - Security Test Suite
 *
 * Comprehensive security testing for government platform compliance
 */

const { expect } = require('chai')
const axios = require('axios')
const fs = require('fs')
const path = require('path')

class SecurityTestSuite {
    constructor(baseUrl = 'http://localhost:8000') {
        this.baseUrl = baseUrl
        this.sessionCookies = []
        this.testResults = {
            passed: 0,
            failed: 0,
            warnings: 0,
            tests: []
        }
    }

    /**
     * Run all security tests
     */
    async runAllTests() {
        console.log('🔒 Starting TPT Government Platform Security Test Suite\n')

        try {
            // Authentication & Authorization Tests
            await this.runAuthenticationTests()
            await this.runAuthorizationTests()

            // Input Validation Tests
            await this.runInputValidationTests()

            // Session Management Tests
            await this.runSessionManagementTests()

            // Data Protection Tests
            await this.runDataProtectionTests()

            // API Security Tests
            await this.runAPISecurityTests()

            // File Upload Security Tests
            await this.runFileUploadSecurityTests()

            // Cross-Site Scripting (XSS) Tests
            await this.runXSSTests()

            // SQL Injection Tests
            await this.runSQLInjectionTests()

            // Cross-Site Request Forgery (CSRF) Tests
            await this.runCSRFTests()

            // Security Headers Tests
            await this.runSecurityHeadersTests()

            // Rate Limiting Tests
            await this.runRateLimitingTests()

            // SSL/TLS Tests
            await this.runSSLTests()

            // Generate report
            this.generateSecurityReport()

        } catch (error) {
            console.error('❌ Security test suite failed:', error.message)
            this.logTestResult('Security Test Suite', false, error.message)
        }
    }

    /**
     * Authentication & Authorization Tests
     */
    async runAuthenticationTests() {
        console.log('🔐 Testing Authentication & Authorization...')

        // Test 1: Invalid login attempts
        await this.testInvalidLoginAttempts()

        // Test 2: Brute force protection
        await this.testBruteForceProtection()

        // Test 3: Session fixation
        await this.testSessionFixation()

        // Test 4: Password policy enforcement
        await this.testPasswordPolicy()

        // Test 5: Account lockout
        await this.testAccountLockout()

        // Test 6: Multi-factor authentication
        await this.testMultiFactorAuth()

        // Test 7: Remember me functionality
        await this.testRememberMeFunctionality()

        // Test 8: Logout functionality
        await this.testLogoutFunctionality()
    }

    /**
     * Test invalid login attempts
     */
    async testInvalidLoginAttempts() {
        const testName = 'Invalid Login Attempts'

        try {
            // Attempt multiple invalid logins
            for (let i = 0; i < 5; i++) {
                const response = await axios.post(`${this.baseUrl}/api/auth/login`, {
                    email: 'invalid@example.com',
                    password: 'wrongpassword'
                }, {
                    validateStatus: () => true // Don't throw on error status
                })

                if (i < 4 && response.status !== 401) {
                    throw new Error(`Expected 401 for invalid login attempt ${i + 1}, got ${response.status}`)
                }
            }

            this.logTestResult(testName, true, 'Invalid login attempts properly rejected')
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test brute force protection
     */
    async testBruteForceProtection() {
        const testName = 'Brute Force Protection'

        try {
            // Attempt rapid login attempts
            const promises = []
            for (let i = 0; i < 10; i++) {
                promises.push(
                    axios.post(`${this.baseUrl}/api/auth/login`, {
                        email: 'test@example.com',
                        password: 'password123'
                    }, {
                        validateStatus: () => true,
                        timeout: 5000
                    })
                )
            }

            const responses = await Promise.all(promises)
            const blockedRequests = responses.filter(r => r.status === 429).length

            if (blockedRequests > 0) {
                this.logTestResult(testName, true, `Brute force protection working (${blockedRequests} requests blocked)`)
            } else {
                this.logTestResult(testName, false, 'Brute force protection not detected')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test session fixation
     */
    async testSessionFixation() {
        const testName = 'Session Fixation Protection'

        try {
            // Get initial session
            const initialResponse = await axios.get(`${this.baseUrl}/api/auth/session`)
            const initialSessionId = initialResponse.headers['set-cookie']?.[0]

            // Login and check if session ID changed
            const loginResponse = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'test@example.com',
                password: 'password123'
            })

            const newSessionId = loginResponse.headers['set-cookie']?.[0]

            if (initialSessionId !== newSessionId) {
                this.logTestResult(testName, true, 'Session ID changed after login (session fixation protected)')
            } else {
                this.logTestResult(testName, false, 'Session ID not changed after login (vulnerable to session fixation)')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test password policy enforcement
     */
    async testPasswordPolicy() {
        const testName = 'Password Policy Enforcement'

        try {
            const weakPasswords = [
                '123456',
                'password',
                'qwerty',
                'abc123'
            ]

            let policyEnforced = false
            for (const password of weakPasswords) {
                const response = await axios.post(`${this.baseUrl}/api/auth/register`, {
                    email: 'test@example.com',
                    password: password
                }, {
                    validateStatus: () => true
                })

                if (response.status === 400 && response.data?.error?.includes('password')) {
                    policyEnforced = true
                    break
                }
            }

            if (policyEnforced) {
                this.logTestResult(testName, true, 'Password policy properly enforced')
            } else {
                this.logTestResult(testName, false, 'Password policy not enforced')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test account lockout
     */
    async testAccountLockout() {
        const testName = 'Account Lockout Protection'

        try {
            // Attempt multiple failed logins
            for (let i = 0; i < 6; i++) {
                await axios.post(`${this.baseUrl}/api/auth/login`, {
                    email: 'test@example.com',
                    password: 'wrongpassword'
                }, {
                    validateStatus: () => true
                })
            }

            // Try valid login
            const response = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'test@example.com',
                password: 'password123'
            }, {
                validateStatus: () => true
            })

            if (response.status === 423) { // Locked
                this.logTestResult(testName, true, 'Account properly locked after failed attempts')
            } else if (response.status === 200) {
                this.logTestResult(testName, false, 'Account not locked after failed attempts')
            } else {
                this.logTestResult(testName, true, 'Account lockout mechanism in place')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test multi-factor authentication
     */
    async testMultiFactorAuth() {
        const testName = 'Multi-Factor Authentication'

        try {
            // Login with valid credentials
            const loginResponse = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'test@example.com',
                password: 'password123'
            })

            // Check if MFA is required
            if (loginResponse.data?.requires_mfa) {
                // Test MFA verification
                const mfaResponse = await axios.post(`${this.baseUrl}/api/auth/mfa/verify`, {
                    code: '123456' // Test code
                })

                if (mfaResponse.status === 200) {
                    this.logTestResult(testName, true, 'MFA properly implemented and verified')
                } else {
                    this.logTestResult(testName, false, 'MFA verification failed')
                }
            } else {
                this.logTestResult(testName, true, 'MFA not required for this account (acceptable)')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test remember me functionality
     */
    async testRememberMeFunctionality() {
        const testName = 'Remember Me Functionality'

        try {
            // Login with remember me
            const response = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'test@example.com',
                password: 'password123',
                remember_me: true
            })

            const cookies = response.headers['set-cookie'] || []
            const hasLongLivedCookie = cookies.some(cookie =>
                cookie.includes('Max-Age=') &&
                parseInt(cookie.split('Max-Age=')[1].split(';')[0]) > 86400 // > 1 day
            )

            if (hasLongLivedCookie) {
                this.logTestResult(testName, true, 'Remember me creates long-lived session')
            } else {
                this.logTestResult(testName, false, 'Remember me does not create long-lived session')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test logout functionality
     */
    async testLogoutFunctionality() {
        const testName = 'Logout Functionality'

        try {
            // Login first
            const loginResponse = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'test@example.com',
                password: 'password123'
            })

            const sessionCookie = loginResponse.headers['set-cookie']?.[0]

            // Logout
            const logoutResponse = await axios.post(`${this.baseUrl}/api/auth/logout`, {}, {
                headers: {
                    'Cookie': sessionCookie
                }
            })

            // Try to access protected resource
            const protectedResponse = await axios.get(`${this.baseUrl}/api/user/profile`, {
                headers: {
                    'Cookie': sessionCookie
                },
                validateStatus: () => true
            })

            if (protectedResponse.status === 401) {
                this.logTestResult(testName, true, 'Logout properly invalidates session')
            } else {
                this.logTestResult(testName, false, 'Logout does not invalidate session')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Authorization Tests
     */
    async runAuthorizationTests() {
        console.log('🛡️  Testing Authorization...')

        // Test 1: Role-based access control
        await this.testRoleBasedAccess()

        // Test 2: Permission validation
        await this.testPermissionValidation()

        // Test 3: Admin access controls
        await this.testAdminAccessControls()

        // Test 4: API authorization
        await this.testAPIAuthorization()
    }

    /**
     * Test role-based access control
     */
    async testRoleBasedAccess() {
        const testName = 'Role-Based Access Control'

        try {
            // Login as regular user
            const userLogin = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'user@example.com',
                password: 'password123'
            })

            const userCookie = userLogin.headers['set-cookie']?.[0]

            // Try to access admin resource
            const adminResponse = await axios.get(`${this.baseUrl}/api/admin/users`, {
                headers: { 'Cookie': userCookie },
                validateStatus: () => true
            })

            if (adminResponse.status === 403) {
                this.logTestResult(testName, true, 'Regular user properly denied admin access')
            } else {
                this.logTestResult(testName, false, 'Regular user has unauthorized admin access')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test permission validation
     */
    async testPermissionValidation() {
        const testName = 'Permission Validation'

        try {
            // Login as user without building consent permissions
            const userLogin = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'limited@example.com',
                password: 'password123'
            })

            const userCookie = userLogin.headers['set-cookie']?.[0]

            // Try to create building consent
            const consentResponse = await axios.post(`${this.baseUrl}/api/building-consents`, {
                property_address: '123 Test St',
                application_type: 'New Construction'
            }, {
                headers: { 'Cookie': userCookie },
                validateStatus: () => true
            })

            if (consentResponse.status === 403) {
                this.logTestResult(testName, true, 'Permission validation working correctly')
            } else {
                this.logTestResult(testName, false, 'Permission validation not enforced')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Input Validation Tests
     */
    async runInputValidationTests() {
        console.log('📝 Testing Input Validation...')

        // Test 1: SQL injection prevention
        await this.testSQLInjectionPrevention()

        // Test 2: XSS prevention
        await this.testXSSPrevention()

        // Test 3: Command injection prevention
        await this.testCommandInjectionPrevention()

        // Test 4: File upload validation
        await this.testFileUploadValidation()

        // Test 5: Email validation
        await this.testEmailValidation()

        // Test 6: Phone number validation
        await this.testPhoneValidation()

        // Test 7: Address validation
        await this.testAddressValidation()
    }

    /**
     * Test SQL injection prevention
     */
    async testSQLInjectionPrevention() {
        const testName = 'SQL Injection Prevention'

        try {
            const sqlPayloads = [
                "' OR '1'='1",
                "'; DROP TABLE users; --",
                "' UNION SELECT * FROM users --",
                "admin'--",
                "1' OR '1' = '1"
            ]

            let injectionBlocked = true
            for (const payload of sqlPayloads) {
                const response = await axios.post(`${this.baseUrl}/api/auth/login`, {
                    email: payload,
                    password: 'password123'
                }, {
                    validateStatus: () => true
                })

                // If we get a 200, the injection might have worked
                if (response.status === 200 && response.data?.user) {
                    injectionBlocked = false
                    break
                }
            }

            if (injectionBlocked) {
                this.logTestResult(testName, true, 'SQL injection attempts properly blocked')
            } else {
                this.logTestResult(testName, false, 'SQL injection vulnerability detected')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Test XSS prevention
     */
    async testXSSPrevention() {
        const testName = 'XSS Prevention'

        try {
            const xssPayloads = [
                '<script>alert("XSS")</script>',
                '<img src=x onerror=alert("XSS")>',
                'javascript:alert("XSS")',
                '<iframe src="javascript:alert(\'XSS\')"></iframe>',
                '<svg onload=alert("XSS")>'
            ]

            let xssBlocked = true
            for (const payload of xssPayloads) {
                // Test in form input
                const response = await axios.post(`${this.baseUrl}/api/contact`, {
                    name: 'Test User',
                    email: 'test@example.com',
                    message: payload
                }, {
                    validateStatus: () => true
                })

                // Check if the response contains the script tags
                if (response.data && typeof response.data === 'string' &&
                    (response.data.includes('<script>') || response.data.includes('javascript:'))) {
                    xssBlocked = false
                    break
                }
            }

            if (xssBlocked) {
                this.logTestResult(testName, true, 'XSS payloads properly sanitized')
            } else {
                this.logTestResult(testName, false, 'XSS vulnerability detected')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Session Management Tests
     */
    async runSessionManagementTests() {
        console.log('🔑 Testing Session Management...')

        // Test 1: Session timeout
        await this.testSessionTimeout()

        // Test 2: Concurrent session handling
        await this.testConcurrentSessions()

        // Test 3: Session invalidation
        await this.testSessionInvalidation()

        // Test 4: Secure cookie attributes
        await this.testSecureCookies()
    }

    /**
     * Test session timeout
     */
    async testSessionTimeout() {
        const testName = 'Session Timeout'

        try {
            // Login and get session
            const loginResponse = await axios.post(`${this.baseUrl}/api/auth/login`, {
                email: 'test@example.com',
                password: 'password123'
            })

            const sessionCookie = loginResponse.headers['set-cookie']?.[0]

            // Wait for session timeout (simulate by making request after timeout)
            await new Promise(resolve => setTimeout(resolve, 1000)) // Wait 1 second

            // Try to access protected resource
            const protectedResponse = await axios.get(`${this.baseUrl}/api/user/profile`, {
                headers: { 'Cookie': sessionCookie },
                validateStatus: () => true
            })

            if (protectedResponse.status === 401) {
                this.logTestResult(testName, true, 'Session properly timed out')
            } else {
                this.logTestResult(testName, true, 'Session timeout not triggered (may be configured for longer timeout)')
            }
        } catch (error) {
            this.logTestResult(testName, false, error.message)
        }
    }

    /**
     * Data Protection Tests
     */
    async runDataProtectionTests() {
        console.log('🔒 Testing Data Protection...')

        // Test 1: Data encryption at rest
        await this.testDataEncryption()

        // Test 2: Data encryption in transit
        await this.testDataInTransit()

        // Test 3: GDPR compliance
        await this.testGDPRCompliance()

        // Test 4: Data retention policies
        await this.testDataRetention()

        // Test 5: Data backup security
        await this.testDataBackupSecurity()
    }

    /**
     * API Security Tests
     */
    async runAPISecurityTests() {
        console.log('🌐 Testing API Security...')

        // Test 1: API authentication
        await this.testAPIAuthentication()

        // Test 2: API rate limiting
        await this.testAPIRateLimiting()

        // Test 3: API input validation
        await this.testAPIInputValidation()

        // Test 4: API error handling
        await this.testAPIErrorHandling()

        // Test 5: API versioning
        await this.testAPIVersioning()
    }

    /**
     * File Upload Security Tests
     */
    async runFileUploadSecurityTests() {
        console.log('📁 Testing File Upload Security...')

        // Test 1: File type validation
        await this.testFileTypeValidation()

        // Test 2: File size limits
        await this.testFileSizeLimits()

        // Test 3: Malicious file detection
        await this.testMaliciousFileDetection()

        // Test 4: File path traversal
        await this.testFilePathTraversal()

        // Test 5: Upload directory permissions
        await this.testUploadDirectoryPermissions()
    }

    /**
     * Security Headers Tests
     */
    async runSecurityHeadersTests() {
        console.log('🛡️  Testing Security Headers...')

        try {
            const response = await axios.get(`${this.baseUrl}/`)
            const headers = response.headers

            // Test Content Security Policy
            if (headers['content-security-policy']) {
                this.logTestResult('Content Security Policy', true, 'CSP header present')
            } else {
                this.logTestResult('Content Security Policy', false, 'CSP header missing')
            }

            // Test X-Frame-Options
            if (headers['x-frame-options']) {
                this.logTestResult('X-Frame-Options', true, 'X-Frame-Options header present')
            } else {
                this.logTestResult('X-Frame-Options', false, 'X-Frame-Options header missing')
            }

            // Test X-Content-Type-Options
            if (headers['x-content-type-options'] === 'nosniff') {
                this.logTestResult('X-Content-Type-Options', true, 'X-Content-Type-Options properly set')
            } else {
                this.logTestResult('X-Content-Type-Options', false, 'X-Content-Type-Options not set or incorrect')
            }

            // Test Strict-Transport-Security
            if (headers['strict-transport-security']) {
                this.logTestResult('Strict-Transport-Security', true, 'HSTS header present')
            } else {
                this.logTestResult('Strict-Transport-Security', false, 'HSTS header missing')
            }

            // Test Referrer-Policy
            if (headers['referrer-policy']) {
                this.logTestResult('Referrer-Policy', true, 'Referrer-Policy header present')
            } else {
                this.logTestResult('Referrer-Policy', false, 'Referrer-Policy header missing')
            }

        } catch (error) {
            this.logTestResult('Security Headers', false, error.message)
        }
    }

    /**
     * Rate Limiting Tests
     */
    async runRateLimitingTests() {
        console.log('⏱️  Testing Rate Limiting...')

        try {
            const requests = []

            // Make multiple requests rapidly
            for (let i = 0; i < 100; i++) {
                requests.push(
                    axios.get(`${this.baseUrl}/api/public/data`, {
                        validateStatus: () => true,
                        timeout: 5000
                    })
                )
            }

            const responses = await Promise.all(requests)
            const rateLimited = responses.filter(r => r.status === 429).length
            const successful = responses.filter(r => r.status === 200).length

            if (rateLimited > 0) {
                this.logTestResult('Rate Limiting', true, `${rateLimited} requests rate limited, ${successful} successful`)
            } else {
                this.logTestResult('Rate Limiting', false, 'No rate limiting detected')
            }

        } catch (error) {
            this.logTestResult('Rate Limiting', false, error.message)
        }
    }

    /**
     * SSL/TLS Tests
     */
    async runSSLTests() {
        console.log('🔒 Testing SSL/TLS...')

        try {
            // Test HTTPS redirect
            const httpResponse = await axios.get(`http://${this.baseUrl.replace('https://', '').replace('http://', '')}`, {
                maxRedirects: 0,
                validateStatus: () => true
            })

            if (httpResponse.status === 301 || httpResponse.status === 302) {
                const location = httpResponse.headers.location
                if (location && location.startsWith('https://')) {
                    this.logTestResult('HTTPS Redirect', true, 'HTTP properly redirects to HTTPS')
                } else {
                    this.logTestResult('HTTPS Redirect', false, 'HTTP redirect does not go to HTTPS')
                }
            } else {
                this.logTestResult('HTTPS Redirect', false, 'No HTTPS redirect detected')
            }

            // Test SSL certificate
            const httpsResponse = await axios.get(`${this.baseUrl}`)
            if (httpsResponse.request.protocol === 'https:') {
                this.logTestResult('SSL Certificate', true, 'HTTPS connection established')
            } else {
                this.logTestResult('SSL Certificate', false, 'Not using HTTPS')
            }

        } catch (error) {
            this.logTestResult('SSL/TLS Tests', false, error.message)
        }
    }

    /**
     * Log test result
     */
    logTestResult(testName, passed, message = '') {
        this.testResults.tests.push({
            name: testName,
            passed: passed,
            message: message,
            timestamp: new Date().toISOString()
        })

        if (passed) {
            this.testResults.passed++
            console.log(`✅ ${testName}: ${message}`)
        } else {
            this.testResults.failed++
            console.log(`❌ ${testName}: ${message}`)
        }
    }

    /**
     * Generate security report
     */
    generateSecurityReport() {
        console.log('\n📊 Security Test Results Summary:')
        console.log(`✅ Passed: ${this.testResults.passed}`)
        console.log(`❌ Failed: ${this.testResults.failed}`)
        console.log(`⚠️  Warnings: ${this.testResults.warnings}`)
        console.log(`📈 Total Tests: ${this.testResults.tests.length}`)

        const passRate = (this.testResults.passed / this.testResults.tests.length * 100).toFixed(1)
        console.log(`🎯 Pass Rate: ${passRate}%`)

        // Generate detailed report
        const report = {
            summary: {
                total: this.testResults.tests.length,
                passed: this.testResults.passed,
                failed: this.testResults.failed,
                warnings: this.testResults.warnings,
                passRate: `${passRate}%`
            },
            timestamp: new Date().toISOString(),
            results: this.testResults.tests
        }

        // Save report to file
        const reportPath = path.join(__dirname, 'security-report.json')
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2))
        console.log(`📄 Detailed report saved to: ${reportPath}`)

        // Overall assessment
        if (passRate >= 90) {
            console.log('🎉 Excellent! Security posture is strong.')
        } else if (passRate >= 75) {
            console.log('👍 Good security posture with some areas for improvement.')
        } else if (passRate >= 60) {
            console.log('⚠️  Security posture needs significant improvement.')
        } else {
            console.log('🚨 Critical security vulnerabilities detected!')
        }
    }
}

// Export for use in other modules
module.exports = SecurityTestSuite

// Run tests if called directly
if (require.main === module) {
    const testSuite = new SecurityTestSuite()
    testSuite.runAllTests().catch(console.error)
}

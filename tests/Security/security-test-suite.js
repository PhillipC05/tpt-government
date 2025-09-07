/**
 * TPT Government Platform - Comprehensive Security Test Suite
 *
 * Advanced security testing covering authentication, authorization,
 * data protection, network security, and compliance validation
 */

const { expect } = require('chai');
const axios = require('axios');
const jwt = require('jsonwebtoken');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

describe('TPT Government Platform - Security Test Suite', () => {
    const baseUrl = process.env.BASE_URL || 'http://localhost:3000';
    const apiUrl = `${baseUrl}/api`;

    // Test credentials
    const testCredentials = {
        admin: {
            email: 'admin@test.gov',
            password: 'AdminPass123!',
            role: 'admin'
        },
        user: {
            email: 'user@test.gov',
            password: 'UserPass123!',
            role: 'user'
        },
        service: {
            email: 'service@test.gov',
            password: 'ServicePass123!',
            role: 'service_provider'
        }
    };

    // Test tokens
    let adminToken = '';
    let userToken = '';
    let serviceToken = '';

    // Test data
    const testData = {
        sensitiveDocument: {
            title: 'Confidential Government Document',
            content: 'This contains sensitive government information',
            classification: 'TOP_SECRET'
        },
        userData: {
            ssn: '123-45-6789',
            medicalInfo: 'Patient has diabetes',
            financialInfo: 'Bank account: 123456789'
        }
    };

    before(async () => {
        // Setup test environment
        await setupTestEnvironment();

        // Authenticate test users
        adminToken = await authenticateUser(testCredentials.admin);
        userToken = await authenticateUser(testCredentials.user);
        serviceToken = await authenticateUser(testCredentials.service);
    });

    describe('Authentication Security', () => {
        it('should prevent brute force attacks', async () => {
            const maxAttempts = 5;
            let blocked = false;

            for (let i = 0; i < maxAttempts + 2; i++) {
                try {
                    await axios.post(`${apiUrl}/auth/login`, {
                        email: testCredentials.user.email,
                        password: 'wrongpassword'
                    });
                } catch (error) {
                    if (error.response?.status === 429) {
                        blocked = true;
                        break;
                    }
                }
            }

            expect(blocked).to.be.true;
        });

        it('should enforce password complexity requirements', async () => {
            const weakPasswords = [
                '123456',
                'password',
                'qwerty',
                'abc123',
                'password123'
            ];

            for (const password of weakPasswords) {
                try {
                    await axios.post(`${apiUrl}/auth/register`, {
                        email: `test${Date.now()}@example.com`,
                        password: password,
                        firstName: 'Test',
                        lastName: 'User'
                    });
                    throw new Error('Weak password was accepted');
                } catch (error) {
                    expect(error.response?.status).to.be.oneOf([400, 422]);
                    expect(error.response?.data?.message).to.include('password');
                }
            }
        });

        it('should implement secure session management', async () => {
            const response = await axios.post(`${apiUrl}/auth/login`, testCredentials.user);
            const sessionToken = response.data.token;

            // Verify token structure
            const decoded = jwt.decode(sessionToken);
            expect(decoded).to.have.property('exp');
            expect(decoded).to.have.property('iat');
            expect(decoded).to.have.property('userId');

            // Test token expiration
            const expiredToken = jwt.sign(
                { userId: 1, exp: Math.floor(Date.now() / 1000) - 3600 },
                'test-secret'
            );

            try {
                await axios.get(`${apiUrl}/user/profile`, {
                    headers: { Authorization: `Bearer ${expiredToken}` }
                });
                throw new Error('Expired token was accepted');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([401, 403]);
            }
        });

        it('should prevent session fixation attacks', async () => {
            // Login with user credentials
            const loginResponse = await axios.post(`${apiUrl}/auth/login`, testCredentials.user);
            const initialToken = loginResponse.data.token;

            // Attempt to use the same session for different user
            const adminLoginResponse = await axios.post(`${apiUrl}/auth/login`, testCredentials.admin);
            const adminToken = adminLoginResponse.data.token;

            // Verify tokens are different
            expect(initialToken).to.not.equal(adminToken);

            // Verify user cannot access admin resources with user token
            try {
                await axios.get(`${apiUrl}/admin/users`, {
                    headers: { Authorization: `Bearer ${initialToken}` }
                });
                throw new Error('User token granted admin access');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([403, 401]);
            }
        });

        it('should implement secure logout', async () => {
            const loginResponse = await axios.post(`${apiUrl}/auth/login`, testCredentials.user);
            const token = loginResponse.data.token;

            // Verify token works before logout
            const profileResponse = await axios.get(`${apiUrl}/user/profile`, {
                headers: { Authorization: `Bearer ${token}` }
            });
            expect(profileResponse.status).to.equal(200);

            // Logout
            await axios.post(`${apiUrl}/auth/logout`, {}, {
                headers: { Authorization: `Bearer ${token}` }
            });

            // Verify token is invalidated after logout
            try {
                await axios.get(`${apiUrl}/user/profile`, {
                    headers: { Authorization: `Bearer ${token}` }
                });
                throw new Error('Token still valid after logout');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([401, 403]);
            }
        });
    });

    describe('Authorization & Access Control', () => {
        it('should enforce role-based access control', async () => {
            const endpoints = [
                { path: '/admin/users', method: 'GET', requiredRole: 'admin' },
                { path: '/service-provider/dashboard', method: 'GET', requiredRole: 'service_provider' },
                { path: '/user/profile', method: 'GET', requiredRole: 'user' }
            ];

            for (const endpoint of endpoints) {
                // Test with user token (should fail for admin/service endpoints)
                if (endpoint.requiredRole !== 'user') {
                    try {
                        await axios.get(`${apiUrl}${endpoint.path}`, {
                            headers: { Authorization: `Bearer ${userToken}` }
                        });
                        throw new Error(`User accessed ${endpoint.requiredRole} endpoint`);
                    } catch (error) {
                        expect(error.response?.status).to.be.oneOf([403, 401]);
                    }
                }

                // Test with appropriate token (should succeed)
                const appropriateToken = getTokenForRole(endpoint.requiredRole);
                const response = await axios.get(`${apiUrl}${endpoint.path}`, {
                    headers: { Authorization: `Bearer ${appropriateToken}` }
                });
                expect(response.status).to.equal(200);
            }
        });

        it('should prevent privilege escalation', async () => {
            // Attempt to modify user role through API
            try {
                await axios.put(`${apiUrl}/user/profile`, {
                    role: 'admin',
                    email: testCredentials.user.email
                }, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                throw new Error('User was able to escalate privileges');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([403, 400]);
            }

            // Verify user still has original role
            const profileResponse = await axios.get(`${apiUrl}/user/profile`, {
                headers: { Authorization: `Bearer ${userToken}` }
            });
            expect(profileResponse.data.role).to.equal('user');
        });

        it('should implement object-level permissions', async () => {
            // Create a document as admin
            const documentResponse = await axios.post(`${apiUrl}/documents`, {
                title: 'Test Document',
                content: 'Test content',
                classification: 'CONFIDENTIAL'
            }, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });

            const documentId = documentResponse.data.id;

            // Try to access document as different user
            try {
                await axios.get(`${apiUrl}/documents/${documentId}`, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                throw new Error('User accessed unauthorized document');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([403, 404]);
            }

            // Admin should be able to access
            const adminAccessResponse = await axios.get(`${apiUrl}/documents/${documentId}`, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });
            expect(adminAccessResponse.status).to.equal(200);
        });

        it('should enforce data classification restrictions', async () => {
            const classifications = ['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'SECRET', 'TOP_SECRET'];

            for (const classification of classifications) {
                // Create document with specific classification
                const docResponse = await axios.post(`${apiUrl}/documents`, {
                    title: `Test ${classification} Document`,
                    content: 'Test content',
                    classification: classification
                }, {
                    headers: { Authorization: `Bearer ${adminToken}` }
                });

                const docId = docResponse.data.id;

                // Test access based on user clearance level
                const userClearanceResponse = await axios.get(`${apiUrl}/user/clearance`, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                const userClearance = userClearanceResponse.data.clearanceLevel;

                const requiredClearance = getRequiredClearance(classification);

                if (userClearance < requiredClearance) {
                    try {
                        await axios.get(`${apiUrl}/documents/${docId}`, {
                            headers: { Authorization: `Bearer ${userToken}` }
                        });
                        throw new Error(`User with clearance ${userClearance} accessed ${classification} document`);
                    } catch (error) {
                        expect(error.response?.status).to.be.oneOf([403, 404]);
                    }
                }
            }
        });
    });

    describe('Data Protection & Encryption', () => {
        it('should encrypt sensitive data at rest', async () => {
            // Create user with sensitive information
            const sensitiveUserResponse = await axios.post(`${apiUrl}/users`, {
                email: 'sensitive@test.gov',
                password: 'TempPass123!',
                firstName: 'Sensitive',
                lastName: 'User',
                ssn: testData.userData.ssn,
                medicalInfo: testData.userData.medicalInfo,
                financialInfo: testData.userData.financialInfo
            }, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });

            const userId = sensitiveUserResponse.data.id;

            // Retrieve user data
            const userDataResponse = await axios.get(`${apiUrl}/users/${userId}`, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });

            // Verify sensitive fields are encrypted in database
            const rawDatabaseData = await queryDatabase(`SELECT * FROM users WHERE id = ${userId}`);
            expect(rawDatabaseData[0].ssn).to.not.equal(testData.userData.ssn);
            expect(rawDatabaseData[0].medical_info).to.not.equal(testData.userData.medicalInfo);
            expect(rawDatabaseData[0].financial_info).to.not.equal(testData.userData.financialInfo);

            // Verify data is properly decrypted in API response
            expect(userDataResponse.data.ssn).to.equal(testData.userData.ssn);
            expect(userDataResponse.data.medicalInfo).to.equal(testData.userData.medicalInfo);
            expect(userDataResponse.data.financialInfo).to.equal(testData.userData.financialInfo);
        });

        it('should implement secure data transmission', async () => {
            // Test HTTPS enforcement
            try {
                await axios.get('http://localhost:3000/api/user/profile', {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                throw new Error('HTTP request was not redirected to HTTPS');
            } catch (error) {
                // Should redirect to HTTPS or fail
                expect(error.code).to.be.oneOf(['ECONNREFUSED', 'ENOTFOUND']);
            }

            // Test secure headers
            const response = await axios.get(`${apiUrl}/user/profile`, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            expect(response.headers).to.have.property('strict-transport-security');
            expect(response.headers).to.have.property('x-content-type-options');
            expect(response.headers).to.have.property('x-frame-options');
            expect(response.headers).to.have.property('x-xss-protection');
            expect(response.headers['content-security-policy']).to.exist;
        });

        it('should protect against data leakage', async () => {
            // Test error messages don't leak sensitive information
            try {
                await axios.get(`${apiUrl}/users/99999`, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
            } catch (error) {
                expect(error.response?.data?.message).to.not.include('SQL');
                expect(error.response?.data?.message).to.not.include('database');
                expect(error.response?.data?.message).to.not.include('stack trace');
            }

            // Test API responses don't include sensitive fields by default
            const usersResponse = await axios.get(`${apiUrl}/users`, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });

            usersResponse.data.forEach(user => {
                expect(user).to.not.have.property('password');
                expect(user).to.not.have.property('passwordHash');
                expect(user).to.not.have.property('ssn');
                expect(user).to.not.have.property('medicalInfo');
            });
        });

        it('should implement secure file upload', async () => {
            const maliciousFile = Buffer.from('<script>alert("XSS")</script>', 'utf8');
            const maliciousFileName = '../../../etc/passwd';

            try {
                await axios.post(`${apiUrl}/documents/upload`, {
                    file: maliciousFile,
                    filename: maliciousFileName,
                    contentType: 'text/html'
                }, {
                    headers: {
                        Authorization: `Bearer ${userToken}`,
                        'Content-Type': 'multipart/form-data'
                    }
                });
                throw new Error('Malicious file upload was allowed');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([400, 403, 422]);
            }

            // Test file type validation
            const validFile = fs.readFileSync(path.join(__dirname, 'fixtures/valid-document.pdf'));
            const uploadResponse = await axios.post(`${apiUrl}/documents/upload`, {
                file: validFile,
                filename: 'valid-document.pdf',
                contentType: 'application/pdf'
            }, {
                headers: {
                    Authorization: `Bearer ${userToken}`,
                    'Content-Type': 'multipart/form-data'
                }
            });

            expect(uploadResponse.status).to.equal(200);
            expect(uploadResponse.data).to.have.property('fileId');
        });
    });

    describe('Network Security', () => {
        it('should prevent common web vulnerabilities', async () => {
            // Test XSS prevention
            const xssPayload = '<script>alert("XSS")</script>';
            try {
                await axios.post(`${apiUrl}/documents`, {
                    title: xssPayload,
                    content: 'Test content'
                }, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                throw new Error('XSS payload was accepted');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([400, 422]);
            }

            // Test SQL injection prevention
            const sqlPayload = "'; DROP TABLE users; --";
            try {
                await axios.get(`${apiUrl}/users/search?q=${encodeURIComponent(sqlPayload)}`, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
            } catch (error) {
                // Should not execute SQL or return database errors
                expect(error.response?.data?.message).to.not.include('SQL');
                expect(error.response?.data?.message).to.not.include('syntax');
            }

            // Test command injection prevention
            const commandPayload = '; rm -rf /';
            try {
                await axios.post(`${apiUrl}/system/execute`, {
                    command: commandPayload
                }, {
                    headers: { Authorization: `Bearer ${adminToken}` }
                });
                throw new Error('Command injection was allowed');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([403, 400]);
            }
        });

        it('should implement rate limiting', async () => {
            const requests = [];

            // Make multiple rapid requests
            for (let i = 0; i < 100; i++) {
                requests.push(
                    axios.get(`${apiUrl}/user/profile`, {
                        headers: { Authorization: `Bearer ${userToken}` }
                    }).catch(error => error)
                );
            }

            const responses = await Promise.all(requests);
            const rateLimitedResponses = responses.filter(response =>
                response.response?.status === 429
            );

            expect(rateLimitedResponses.length).to.be.greaterThan(0);
        });

        it('should prevent CSRF attacks', async () => {
            // Attempt request without CSRF token
            try {
                await axios.post(`${apiUrl}/user/profile`, {
                    firstName: 'Hacked'
                }, {
                    headers: { Authorization: `Bearer ${userToken}` }
                    // Missing CSRF token
                });
                throw new Error('CSRF protection failed');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([403, 419]);
            }

            // Test with valid CSRF token
            const csrfToken = await getCsrfToken();
            const validResponse = await axios.post(`${apiUrl}/user/profile`, {
                firstName: 'Updated'
            }, {
                headers: {
                    Authorization: `Bearer ${userToken}`,
                    'X-CSRF-Token': csrfToken
                }
            });

            expect(validResponse.status).to.equal(200);
        });

        it('should implement secure CORS policy', async () => {
            // Test preflight request
            const preflightResponse = await axios.options(`${apiUrl}/user/profile`, {
                headers: {
                    'Origin': 'https://malicious-site.com',
                    'Access-Control-Request-Method': 'GET',
                    'Access-Control-Request-Headers': 'Authorization'
                }
            });

            // Should not allow malicious origin
            expect(preflightResponse.headers['access-control-allow-origin']).to.not.equal('https://malicious-site.com');

            // Test with allowed origin
            const allowedOrigin = baseUrl.replace('http://', 'https://');
            const allowedResponse = await axios.get(`${apiUrl}/user/profile`, {
                headers: {
                    'Origin': allowedOrigin,
                    Authorization: `Bearer ${userToken}`
                }
            });

            expect(allowedResponse.headers['access-control-allow-origin']).to.equal(allowedOrigin);
        });
    });

    describe('Compliance & Audit', () => {
        it('should maintain comprehensive audit logs', async () => {
            // Perform various operations
            await axios.post(`${apiUrl}/documents`, {
                title: 'Audit Test Document',
                content: 'Test content for audit logging'
            }, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            await axios.put(`${apiUrl}/user/profile`, {
                firstName: 'Updated'
            }, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            // Retrieve audit logs
            const auditResponse = await axios.get(`${apiUrl}/audit/logs`, {
                headers: { Authorization: `Bearer ${adminToken}` },
                params: {
                    userId: getUserIdFromToken(userToken),
                    dateFrom: new Date(Date.now() - 3600000).toISOString(), // Last hour
                    dateTo: new Date().toISOString()
                }
            });

            const auditLogs = auditResponse.data;
            expect(auditLogs.length).to.be.greaterThan(0);

            // Verify audit log structure
            auditLogs.forEach(log => {
                expect(log).to.have.property('timestamp');
                expect(log).to.have.property('userId');
                expect(log).to.have.property('action');
                expect(log).to.have.property('resource');
                expect(log).to.have.property('ipAddress');
                expect(log).to.have.property('userAgent');
            });

            // Verify specific actions are logged
            const actions = auditLogs.map(log => log.action);
            expect(actions).to.include('DOCUMENT_CREATE');
            expect(actions).to.include('USER_PROFILE_UPDATE');
        });

        it('should comply with GDPR requirements', async () => {
            const userId = getUserIdFromToken(userToken);

            // Test data portability (export user data)
            const exportResponse = await axios.get(`${apiUrl}/user/data-export`, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            expect(exportResponse.status).to.equal(200);
            expect(exportResponse.data).to.have.property('personalData');
            expect(exportResponse.data).to.have.property('documents');
            expect(exportResponse.data).to.have.property('activityLogs');

            // Test right to erasure
            const erasureResponse = await axios.post(`${apiUrl}/user/delete-account`, {
                reason: 'Testing GDPR compliance',
                confirmDeletion: true
            }, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            expect(erasureResponse.status).to.equal(200);

            // Verify user data is anonymized/deleted
            try {
                await axios.get(`${apiUrl}/user/profile`, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                throw new Error('User data still accessible after deletion');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([401, 404]);
            }
        });

        it('should implement data retention policies', async () => {
            // Create temporary data
            const tempDocResponse = await axios.post(`${apiUrl}/documents`, {
                title: 'Temporary Document',
                content: 'This document should be automatically deleted',
                retentionPeriod: 1 // 1 day
            }, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            const docId = tempDocResponse.data.id;

            // Fast-forward time (simulate retention period expiry)
            await simulateTimeAdvance(25 * 60 * 60 * 1000); // 25 hours

            // Check if document is automatically deleted
            try {
                await axios.get(`${apiUrl}/documents/${docId}`, {
                    headers: { Authorization: `Bearer ${userToken}` }
                });
                throw new Error('Document not automatically deleted');
            } catch (error) {
                expect(error.response?.status).to.equal(404);
            }
        });

        it('should detect and prevent security anomalies', async () => {
            // Simulate suspicious activity
            const suspiciousRequests = [];

            // Multiple failed login attempts
            for (let i = 0; i < 10; i++) {
                suspiciousRequests.push(
                    axios.post(`${apiUrl}/auth/login`, {
                        email: testCredentials.user.email,
                        password: 'wrongpassword'
                    }).catch(error => error)
                );
            }

            await Promise.all(suspiciousRequests);

            // Check if security alert is generated
            const alertsResponse = await axios.get(`${apiUrl}/admin/security-alerts`, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });

            const bruteForceAlerts = alertsResponse.data.filter(alert =>
                alert.type === 'BRUTE_FORCE_ATTACK'
            );

            expect(bruteForceAlerts.length).to.be.greaterThan(0);

            // Verify account is temporarily locked
            try {
                await axios.post(`${apiUrl}/auth/login`, testCredentials.user);
                throw new Error('Account not locked after brute force attempt');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([423, 429]); // Locked or rate limited
            }
        });
    });

    describe('Infrastructure Security', () => {
        it('should secure API endpoints', async () => {
            const endpoints = [
                '/api/admin/users',
                '/api/admin/system-config',
                '/api/admin/database-backup',
                '/api/user/profile',
                '/api/documents/confidential'
            ];

            for (const endpoint of endpoints) {
                // Test without authentication
                try {
                    await axios.get(`${baseUrl}${endpoint}`);
                    throw new Error(`Endpoint ${endpoint} accessible without authentication`);
                } catch (error) {
                    expect(error.response?.status).to.be.oneOf([401, 403]);
                }

                // Test with invalid token
                try {
                    await axios.get(`${baseUrl}${endpoint}`, {
                        headers: { Authorization: 'Bearer invalid-token' }
                    });
                    throw new Error(`Endpoint ${endpoint} accessible with invalid token`);
                } catch (error) {
                    expect(error.response?.status).to.be.oneOf([401, 403]);
                }
            }
        });

        it('should implement secure configuration management', async () => {
            // Test that sensitive configuration is not exposed
            const configResponse = await axios.get(`${apiUrl}/system/config`, {
                headers: { Authorization: `Bearer ${adminToken}` }
            });

            // Should not expose sensitive data
            expect(configResponse.data).to.not.have.property('databasePassword');
            expect(configResponse.data).to.not.have.property('jwtSecret');
            expect(configResponse.data).to.not.have.property('apiKeys');

            // Test configuration validation
            try {
                await axios.put(`${apiUrl}/system/config`, {
                    databaseHost: '', // Invalid empty value
                    jwtSecret: 'short'
                }, {
                    headers: { Authorization: `Bearer ${adminToken}` }
                });
                throw new Error('Invalid configuration was accepted');
            } catch (error) {
                expect(error.response?.status).to.be.oneOf([400, 422]);
            }
        });

        it('should secure third-party integrations', async () => {
            // Test payment gateway integration security
            const paymentResponse = await axios.post(`${apiUrl}/payments/process`, {
                amount: 100.00,
                currency: 'USD',
                paymentMethod: 'credit_card',
                cardNumber: '4111111111111111',
                expiryMonth: 12,
                expiryYear: 2025,
                cvv: '123'
            }, {
                headers: { Authorization: `Bearer ${userToken}` }
            });

            // Verify payment data is not logged in plain text
            const logsResponse = await axios.get(`${apiUrl}/admin/logs`, {
                headers: { Authorization: `Bearer ${adminToken}` },
                params: { type: 'payment' }
            });

            logsResponse.data.forEach(log => {
                expect(log.message).to.not.include('4111111111111111');
                expect(log.message).to.not.include('123');
            });

            // Test external API rate limiting
            const externalRequests = [];
            for (let i = 0; i < 50; i++) {
                externalRequests.push(
                    axios.get(`${apiUrl}/external/weather`, {
                        headers: { Authorization: `Bearer ${userToken}` }
                    }).catch(error => error)
                );
            }

            const externalResponses = await Promise.all(externalRequests);
            const rateLimited = externalResponses.filter(response =>
                response.response?.status === 429
            );

            expect(rateLimited.length).to.be.greaterThan(0);
        });
    });

    // Helper functions
    async function authenticateUser(credentials) {
        const response = await axios.post(`${apiUrl}/auth/login`, credentials);
        return response.data.token;
    }

    function getTokenForRole(role) {
        switch (role) {
            case 'admin': return adminToken;
            case 'service_provider': return serviceToken;
            case 'user': return userToken;
            default: return userToken;
        }
    }

    function getRequiredClearance(classification) {
        const clearanceLevels = {
            'PUBLIC': 1,
            'INTERNAL': 2,
            'CONFIDENTIAL': 3,
            'SECRET': 4,
            'TOP_SECRET': 5
        };
        return clearanceLevels[classification] || 1;
    }

    function getUserIdFromToken(token) {
        const decoded = jwt.decode(token);
        return decoded.userId;
    }

    async function getCsrfToken() {
        const response = await axios.get(`${apiUrl}/csrf-token`, {
            headers: { Authorization: `Bearer ${userToken}` }
        });
        return response.data.token;
    }

    async function queryDatabase(query) {
        // This would be implemented to directly query the test database
        return [];
    }

    async function setupTestEnvironment() {
        // Setup test database, users, and initial data
        await axios.post(`${baseUrl}/test/setup`);
    }

    async function simulateTimeAdvance(milliseconds) {
        // This would be implemented to fast-forward time in test environment
        await axios.post(`${baseUrl}/test/advance-time`, { milliseconds });
    }
});

/**
 * TPT Government Platform - API Client
 * Handles all communication with the backend API
 */

class APIClient {
    constructor() {
        this.baseURL = window.location.origin;
        this.token = null;
        this.refreshPromise = null;

        // Load token from storage
        this.loadToken();
    }

    /**
     * Load authentication token from storage
     */
    loadToken() {
        this.token = StorageUtils.get('auth_token');
    }

    /**
     * Save authentication token to storage
     */
    saveToken(token) {
        this.token = token;
        StorageUtils.set('auth_token', token);
    }

    /**
     * Clear authentication token
     */
    clearToken() {
        this.token = null;
        StorageUtils.remove('auth_token');
    }

    /**
     * Make HTTP request to API
     */
    async request(method, endpoint, data = null, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${this.baseURL}/api${endpoint}`;

        const config = {
            method: method.toUpperCase(),
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...options.headers
            }
        };

        // Add authentication header if token exists
        if (this.token) {
            config.headers['Authorization'] = `Bearer ${this.token}`;
        }

        // Add request body for non-GET requests
        if (data && method.toUpperCase() !== 'GET') {
            config.body = JSON.stringify(data);
        }

        // Add query parameters for GET requests
        if (data && method.toUpperCase() === 'GET') {
            const params = new URLSearchParams();
            Object.keys(data).forEach(key => {
                if (data[key] !== null && data[key] !== undefined) {
                    params.append(key, data[key]);
                }
            });
            const separator = url.includes('?') ? '&' : '?';
            config.url = url + separator + params.toString();
        }

        try {
            const response = await fetch(config.url || url, config);
            const responseData = await this.parseResponse(response);

            // Handle authentication errors
            if (response.status === 401) {
                this.clearToken();
                // Redirect to login if not already there
                if (!window.location.pathname.includes('/login')) {
                    window.location.href = '/login';
                }
                throw new Error('Authentication required');
            }

            // Handle other HTTP errors
            if (!response.ok) {
                throw new Error(responseData.message || `HTTP ${response.status}`);
            }

            return responseData;

        } catch (error) {
            console.error('API request failed:', error);

            // Handle network errors
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('Network error - please check your connection');
            }

            throw error;
        }
    }

    /**
     * Parse API response
     */
    async parseResponse(response) {
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        } else {
            return await response.text();
        }
    }

    /**
     * GET request
     */
    async get(endpoint, params = {}, options = {}) {
        return this.request('GET', endpoint, params, options);
    }

    /**
     * POST request
     */
    async post(endpoint, data = {}, options = {}) {
        return this.request('POST', endpoint, data, options);
    }

    /**
     * PUT request
     */
    async put(endpoint, data = {}, options = {}) {
        return this.request('PUT', endpoint, data, options);
    }

    /**
     * DELETE request
     */
    async delete(endpoint, options = {}) {
        return this.request('DELETE', endpoint, null, options);
    }

    /**
     * PATCH request
     */
    async patch(endpoint, data = {}, options = {}) {
        return this.request('PATCH', endpoint, data, options);
    }

    // Authentication methods

    /**
     * Login user
     */
    async login(credentials) {
        try {
            const response = await this.post('/auth/login', credentials);

            if (response.success && response.token) {
                this.saveToken(response.token);
            }

            return response;
        } catch (error) {
            throw new Error('Login failed: ' + error.message);
        }
    }

    /**
     * Logout user
     */
    async logout() {
        try {
            await this.post('/auth/logout');
        } catch (error) {
            // Ignore logout errors
        } finally {
            this.clearToken();
        }
    }

    /**
     * Check if user is authenticated
     */
    async checkAuth() {
        if (!this.token) {
            return false;
        }

        try {
            const response = await this.get('/auth/session');
            return response.authenticated || false;
        } catch (error) {
            this.clearToken();
            return false;
        }
    }

    /**
     * Get current user profile
     */
    async getProfile() {
        return this.get('/user/profile');
    }

    /**
     * Update user profile
     */
    async updateProfile(data) {
        return this.put('/user/profile', data);
    }

    /**
     * Change user password
     */
    async changePassword(data) {
        return this.post('/user/change-password', data);
    }

    // Service methods

    /**
     * Get available services
     */
    async getServices() {
        return this.get('/services');
    }

    /**
     * Get service details
     */
    async getService(serviceId) {
        return this.get(`/services/${serviceId}`);
    }

    /**
     * Submit service application
     */
    async submitApplication(serviceId, data) {
        return this.post(`/services/${serviceId}/apply`, data);
    }

    /**
     * Get user's applications
     */
    async getApplications(params = {}) {
        return this.get('/applications', params);
    }

    /**
     * Get application details
     */
    async getApplication(applicationId) {
        return this.get(`/applications/${applicationId}`);
    }

    /**
     * Update application
     */
    async updateApplication(applicationId, data) {
        return this.put(`/applications/${applicationId}`, data);
    }

    // Document methods

    /**
     * Get user's documents
     */
    async getDocuments(params = {}) {
        return this.get('/documents', params);
    }

    /**
     * Upload document
     */
    async uploadDocument(file, metadata = {}) {
        const formData = new FormData();
        formData.append('file', file);

        Object.keys(metadata).forEach(key => {
            formData.append(key, metadata[key]);
        });

        return this.request('POST', '/documents/upload', formData, {
            headers: {
                // Don't set Content-Type, let browser set it with boundary
                'Accept': 'application/json'
            }
        });
    }

    /**
     * Download document
     */
    async downloadDocument(documentId) {
        const response = await fetch(`${this.baseURL}/api/documents/${documentId}/download`, {
            method: 'GET',
            headers: {
                'Authorization': this.token ? `Bearer ${this.token}` : ''
            }
        });

        if (!response.ok) {
            throw new Error('Download failed');
        }

        return response.blob();
    }

    /**
     * Delete document
     */
    async deleteDocument(documentId) {
        return this.delete(`/documents/${documentId}`);
    }

    // Dashboard methods

    /**
     * Get dashboard data
     */
    async getDashboard() {
        return this.get('/dashboard');
    }

    /**
     * Get dashboard statistics
     */
    async getDashboardStats() {
        return this.get('/dashboard/stats');
    }

    /**
     * Mark notification as read
     */
    async markNotificationRead(notificationId) {
        return this.post('/dashboard/notifications/read', { notification_id: notificationId });
    }

    // Admin methods

    /**
     * Get admin statistics
     */
    async getAdminStats() {
        return this.get('/admin/stats');
    }

    /**
     * Get system logs
     */
    async getSystemLogs(params = {}) {
        return this.get('/admin/logs', params);
    }

    /**
     * Clear system cache
     */
    async clearCache() {
        return this.post('/admin/cache/clear');
    }

    /**
     * Get system configuration
     */
    async getSystemConfig() {
        return this.get('/admin/config');
    }

    // AI methods

    /**
     * Generate text using AI
     */
    async generateText(prompt, options = {}) {
        return this.post('/ai/generate-text', { prompt, options });
    }

    /**
     * Analyze document with AI
     */
    async analyzeDocument(content, options = {}) {
        return this.post('/ai/analyze-document', { content, options });
    }

    /**
     * Classify content with AI
     */
    async classifyContent(content, categories, options = {}) {
        return this.post('/ai/classify', { content, categories, options });
    }

    /**
     * Extract information with AI
     */
    async extractInformation(text, fields, options = {}) {
        return this.post('/ai/extract', { text, fields, options });
    }

    // Webhook methods

    /**
     * Get webhooks
     */
    async getWebhooks() {
        return this.get('/webhooks');
    }

    /**
     * Create webhook
     */
    async createWebhook(data) {
        return this.post('/webhooks', data);
    }

    /**
     * Update webhook
     */
    async updateWebhook(webhookId, data) {
        return this.put(`/webhooks/${webhookId}`, data);
    }

    /**
     * Delete webhook
     */
    async deleteWebhook(webhookId) {
        return this.delete(`/webhooks/${webhookId}`);
    }

    /**
     * Test webhook
     */
    async testWebhook(webhookId) {
        return this.post(`/webhooks/${webhookId}/test`);
    }

    // Utility methods

    /**
     * Test API connectivity
     */
    async testConnection() {
        try {
            const response = await this.get('/health');
            return response.status === 'healthy';
        } catch (error) {
            return false;
        }
    }

    /**
     * Get API information
     */
    async getApiInfo() {
        return this.get('/info');
    }

    /**
     * Handle offline requests
     */
    queueOfflineRequest(method, endpoint, data) {
        const offlineRequests = StorageUtils.get('offline_requests', []);
        offlineRequests.push({
            method,
            endpoint,
            data,
            timestamp: Date.now(),
            id: Math.random().toString(36).substr(2, 9)
        });
        StorageUtils.set('offline_requests', offlineRequests);
    }

    /**
     * Process queued offline requests
     */
    async processOfflineRequests() {
        const offlineRequests = StorageUtils.get('offline_requests', []);

        if (offlineRequests.length === 0) {
            return;
        }

        const successfulRequests = [];

        for (const request of offlineRequests) {
            try {
                await this.request(request.method, request.endpoint, request.data);
                successfulRequests.push(request.id);
            } catch (error) {
                console.error('Failed to process offline request:', error);
            }
        }

        // Remove successful requests from queue
        const remainingRequests = offlineRequests.filter(
            req => !successfulRequests.includes(req.id)
        );

        StorageUtils.set('offline_requests', remainingRequests);
    }
}

// Create global API client instance
window.API = new APIClient();

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = APIClient;
}
